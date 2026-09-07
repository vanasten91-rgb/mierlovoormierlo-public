<?php

namespace MVM\Hub\Modules\Communications;

use MVM\Hub\Core\Capabilities;
use MVM\Hub\Core\Communications_Policy;
use MVM\Hub\Core\Hub_Security_Policy;
use MVM\Hub\Integrations\Mail\Attachment_Scanner;
use MVM\Hub\Integrations\Mail\Communications_Action_Security;
use MVM\Hub\Integrations\Mail\Communications_Audit;
use MVM\Hub\Integrations\Mail\Mailbox_Access;
use MVM\Hub\Integrations\Mail\Private_Storage_Root;
use MVM\Hub\Integrations\Mail\V5_IMAP_Attachment_Reader;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/** Step-up protected, malware-scanned download boundary for incoming Mail. */
final class Incoming_Mail_Attachment_Service {
    public function __construct(
        private readonly Mailbox_Access $access,
        private readonly Communications_Action_Security $security,
        private readonly Attachment_Scanner $scanner,
        private readonly Communications_Audit $audit,
        private readonly V5_IMAP_Attachment_Reader $reader
    ) {}

    /** @return array<string,mixed>|\WP_Error */
    public function download( string $mailbox_id, string $message_id, string $attachment_id ): array|\WP_Error {
        $context = $this->authorize( $mailbox_id, $message_id );
        if ( is_wp_error( $context ) ) {
            return $context;
        }
        if ( ! Hub_Security_Policy::requires_step_up( 'mail_attachment_download' ) ) {
            return new \WP_Error( 'mvm_mail_step_up_policy', 'De beveiligingspolicy voor bijlagen is ongeldig.' );
        }
        $step_up = $this->security->authorize_step_up( $context['userId'], 'mail_attachment_download' );
        if ( is_wp_error( $step_up ) ) {
            return $step_up;
        }

        $attachment_id = self::attachment_id( $attachment_id );
        if ( '' === $attachment_id ) {
            return new \WP_Error( 'mvm_mail_attachment_reference', 'Ongeldige bijlageverwijzing.' );
        }

        $fetched = $this->reader->fetch(
            $context['mailboxId'],
            $context['folderId'],
            $context['messageId'],
            $attachment_id
        );
        if ( is_wp_error( $fetched ) ) {
            $this->audit->record( 'mail.attachment.download', 'fetch_failed', $context['userId'], self::safe_ref( $context['messageId'] ) );
            return $fetched;
        }

        $name    = sanitize_file_name( (string) ( $fetched['name'] ?? '' ) );
        $mime    = sanitize_mime_type( (string) ( $fetched['mimeType'] ?? '' ) );
        $content = is_string( $fetched['content'] ?? null ) ? $fetched['content'] : '';
        $size    = strlen( $content );
        if ( '' === $name || '' === $mime || $size <= 0 || $size > Communications_Policy::max_attachment_bytes() ) {
            $this->audit->record( 'mail.attachment.download', 'policy_denied', $context['userId'], self::safe_ref( $context['messageId'] ), array( 'size_bytes' => $size ) );
            return new \WP_Error( 'mvm_mail_attachment_download_policy', 'Deze bijlage kan niet veilig worden gedownload.' );
        }

        $directory = Private_Storage_Root::directory( 'incoming-mail-downloads' );
        if ( is_wp_error( $directory ) ) {
            return $directory;
        }
        try {
            $random = function_exists( 'wp_generate_uuid4' ) ? wp_generate_uuid4() : bin2hex( random_bytes( 16 ) );
        } catch ( \Throwable ) {
            return new \WP_Error( 'mvm_mail_attachment_temp', 'De bijlage kan niet veilig worden voorbereid.' );
        }
        $temporary = $directory . DIRECTORY_SEPARATOR . 'mail-' . preg_replace( '/[^A-Za-z0-9-]/', '', $random ) . '.scan';
        $written = file_put_contents( $temporary, $content, LOCK_EX );
        if ( false === $written || $written !== $size ) {
            if ( is_file( $temporary ) ) {
                @unlink( $temporary );
            }
            return new \WP_Error( 'mvm_mail_attachment_temp', 'De bijlage kan niet veilig worden voorbereid.' );
        }
        @chmod( $temporary, 0600 );

        try {
            if (
                ! Communications_Policy::attachment_type_allowed( $name, $mime, $size )
                || ! Communications_Policy::attachment_content_allowed( $name, $mime, $size, $temporary )
            ) {
                $this->audit->record( 'mail.attachment.download', 'content_denied', $context['userId'], self::safe_ref( $context['messageId'] ), array( 'size_bytes' => $size ) );
                return new \WP_Error( 'mvm_mail_attachment_download_type', 'Dit bestandstype is niet toegestaan.' );
            }

            $scan = $this->scanner->scan( array(
                'name'          => $name,
                'mimeType'      => $mime,
                'sizeBytes'     => $size,
                'privateHandle' => $temporary,
            ) );
            if ( is_wp_error( $scan ) || 'clean' !== sanitize_key( (string) ( is_array( $scan ) ? ( $scan['status'] ?? '' ) : '' ) ) ) {
                $this->audit->record( 'mail.attachment.download', 'scan_denied', $context['userId'], self::safe_ref( $context['messageId'] ), array( 'size_bytes' => $size ) );
                return is_wp_error( $scan ) ? $scan : new \WP_Error( 'mvm_mail_attachment_scan', 'De bijlage is niet vrijgegeven door de beveiligingsscan.' );
            }

            $this->audit->record( 'mail.attachment.download', 'success', $context['userId'], self::safe_ref( $context['messageId'] ), array( 'size_bytes' => $size ) );
            return array(
                'id'            => $attachment_id,
                'name'          => $name,
                'mimeType'      => $mime,
                'sizeBytes'     => $size,
                'scanStatus'    => 'clean',
                'contentBase64' => base64_encode( $content ),
            );
        } finally {
            @unlink( $temporary );
        }
    }

    /** @return array{userId:int,mailboxId:string,folderId:string,messageId:string}|\WP_Error */
    private function authorize( string $mailbox_id, string $message_id ): array|\WP_Error {
        $user_id    = get_current_user_id();
        $mailbox_id = self::opaque_id( $mailbox_id, 128 );
        $message_id = self::opaque_id( $message_id, 260 );
        if ( ! is_user_logged_in() || ! Capabilities::can_read_mail() || $user_id <= 0 || '' === $mailbox_id || '' === $message_id ) {
            return new \WP_Error( 'mvm_mail_attachment_forbidden', 'Geen toegang tot deze bijlage.' );
        }
        $session = $this->security->authorize_session( $user_id );
        if ( is_wp_error( $session ) ) {
            return $session;
        }
        $mailbox_access = $this->access->authorize_read( $user_id, $mailbox_id );
        if ( is_wp_error( $mailbox_access ) ) {
            return $mailbox_access;
        }
        $message_access = $this->access->authorize_message( $user_id, $mailbox_id, $message_id );
        if ( is_wp_error( $message_access ) ) {
            return new \WP_Error( 'mvm_mail_attachment_forbidden', 'Geen toegang tot deze bijlage.' );
        }
        if ( 1 !== preg_match( '/^([A-Za-z0-9_-]+)\.(\d+)$/', $message_id, $matches ) || (int) $matches[2] < 1 ) {
            return new \WP_Error( 'mvm_mail_attachment_reference', 'Ongeldige folder-scoped berichtreferentie.' );
        }
        return array(
            'userId'    => $user_id,
            'mailboxId' => $mailbox_id,
            'folderId'  => $matches[1],
            'messageId' => $matches[1] . '.' . (string) (int) $matches[2],
        );
    }

    private static function attachment_id( string $value ): string {
        $value = trim( $value );
        return strlen( $value ) <= 64 && 1 === preg_match( '/^\d+(?:\.\d+){0,7}$/', $value ) ? $value : '';
    }

    private static function opaque_id( string $value, int $max_length ): string {
        $value = trim( $value );
        return strlen( $value ) <= $max_length && 1 === preg_match( '/^[A-Za-z0-9._:-]+$/', $value ) ? $value : '';
    }

    private static function safe_ref( string $value ): string {
        return '' === $value ? '' : substr( wp_hash( $value, 'mvm_hub_mail_object' ), 0, 24 );
    }
}
