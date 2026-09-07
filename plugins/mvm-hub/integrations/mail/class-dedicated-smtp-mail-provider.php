<?php

namespace MVM\Hub\Integrations\Mail;

use MVM\Hub\Core\Communications_Policy;
use PHPMailer\PHPMailer\PHPMailer;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Dedicated SMTP transport with the existing read-only provider as read facade.
 *
 * Credentials and From identity are server-side constants only. Client payloads
 * cannot select sender/return-path. No WordPress global mail hooks are used.
 */
final class Dedicated_SMTP_Mail_Provider implements Mail_Provider {
    public function __construct( private readonly Mail_Provider $read_provider ) {}

    public function list_folders( string $mailbox_id ): array {
        return $this->read_provider->list_folders( $mailbox_id );
    }

    public function list_messages( string $mailbox_id, string $folder_id, array $query = array() ): array {
        return $this->read_provider->list_messages( $mailbox_id, $folder_id, $query );
    }

    public function get_message( string $mailbox_id, string $message_id ): array|\WP_Error {
        return $this->read_provider->get_message( $mailbox_id, $message_id );
    }

    public function save_draft( string $mailbox_id, array $draft, ?string $draft_id = null ): array|\WP_Error {
        return $this->read_provider->save_draft( $mailbox_id, $draft, $draft_id );
    }

    public function create_folder( string $mailbox_id, string $name, ?string $parent_id = null ): array|\WP_Error {
        return $this->read_provider->create_folder( $mailbox_id, $name, $parent_id );
    }

    public function rename_folder( string $mailbox_id, string $folder_id, string $name ): bool|\WP_Error {
        return $this->read_provider->rename_folder( $mailbox_id, $folder_id, $name );
    }

    public function delete_folder( string $mailbox_id, string $folder_id ): bool|\WP_Error {
        return $this->read_provider->delete_folder( $mailbox_id, $folder_id );
    }

    public function move_message( string $mailbox_id, string $message_id, string $folder_id ): bool|\WP_Error {
        return $this->read_provider->move_message( $mailbox_id, $message_id, $folder_id );
    }

    public function set_read_state( string $mailbox_id, string $message_id, bool $is_read ): bool|\WP_Error {
        return $this->read_provider->set_read_state( $mailbox_id, $message_id, $is_read );
    }

    public function set_flagged_state( string $mailbox_id, string $message_id, bool $is_flagged ): bool|\WP_Error {
        return $this->read_provider->set_flagged_state( $mailbox_id, $message_id, $is_flagged );
    }

    /** @return array<string,mixed>|\WP_Error */
    public function deliver( string $mailbox_id, array $message, string $idempotency_key ): array|\WP_Error {
        unset( $idempotency_key );

        if ( ! Communications_Policy::external_mail_delivery_enabled() || ! $this->configured( $mailbox_id ) ) {
            return new \WP_Error( 'mvm_mail_smtp_disabled', 'De beveiligde SMTP-transportlaag is niet geconfigureerd of niet vrijgegeven.' );
        }

        $loaded = $this->load_phpmailer();
        if ( is_wp_error( $loaded ) ) {
            return $loaded;
        }

        try {
            $mail = new PHPMailer( true );
            $mail->isSMTP();
            $mail->Host       = (string) MVM_HUB_SMTP_HOST;
            $mail->Port       = (int) MVM_HUB_SMTP_PORT;
            $mail->Timeout    = defined( 'MVM_HUB_SMTP_TIMEOUT' ) ? max( 5, min( 60, (int) MVM_HUB_SMTP_TIMEOUT ) ) : 20;
            $mail->SMTPDebug  = 0;
            $mail->CharSet    = 'UTF-8';
            $mail->SMTPAuth   = defined( 'MVM_HUB_SMTP_USERNAME' ) && '' !== (string) MVM_HUB_SMTP_USERNAME;

            if ( $mail->SMTPAuth ) {
                $mail->Username = (string) MVM_HUB_SMTP_USERNAME;
                $mail->Password = defined( 'MVM_HUB_SMTP_PASSWORD' ) ? (string) MVM_HUB_SMTP_PASSWORD : '';
            }

            $encryption = defined( 'MVM_HUB_SMTP_ENCRYPTION' ) ? strtolower( (string) MVM_HUB_SMTP_ENCRYPTION ) : 'tls';
            if ( in_array( $encryption, array( 'tls', 'ssl' ), true ) ) {
                $mail->SMTPSecure = $encryption;
            }

            $mail->setFrom( (string) MVM_HUB_MAIL_FROM_ADDRESS, (string) MVM_HUB_MAIL_FROM_NAME, false );
            foreach ( (array) ( $message['to'] ?? array() ) as $address ) {
                $mail->addAddress( (string) $address );
            }
            foreach ( (array) ( $message['cc'] ?? array() ) as $address ) {
                $mail->addCC( (string) $address );
            }
            foreach ( (array) ( $message['bcc'] ?? array() ) as $address ) {
                $mail->addBCC( (string) $address );
            }

            $mail->Subject = (string) ( $message['subject'] ?? '' );
            $html = (string) ( $message['htmlBody'] ?? '' );
            $text = (string) ( $message['textBody'] ?? '' );
            if ( '' !== $html ) {
                $mail->isHTML( true );
                $mail->Body    = $html;
                $mail->AltBody = $text;
            } else {
                $mail->isHTML( false );
                $mail->Body = $text;
            }

            $in_reply_to = trim( (string) ( $message['inReplyTo'] ?? '' ) );
            if ( '' !== $in_reply_to ) {
                $mail->addCustomHeader( 'In-Reply-To', $in_reply_to );
            }
            $references = array_values( array_filter( array_map( 'strval', (array) ( $message['references'] ?? array() ) ) ) );
            if ( array() !== $references ) {
                $mail->addCustomHeader( 'References', implode( ' ', array_slice( $references, -20 ) ) );
            }

            foreach ( (array) ( $message['attachments'] ?? array() ) as $attachment ) {
                if ( ! is_array( $attachment ) ) {
                    continue;
                }
                $path = $this->safe_attachment_path( (string) ( $attachment['path'] ?? '' ) );
                if ( is_wp_error( $path ) ) {
                    return $path;
                }
                $name = sanitize_file_name( (string) ( $attachment['name'] ?? basename( $path ) ) );
                $mime = sanitize_mime_type( (string) ( $attachment['mimeType'] ?? '' ) );
                $mail->addAttachment( $path, $name, PHPMailer::ENCODING_BASE64, $mime );
            }

            $mail->send();
            $message_id = method_exists( $mail, 'getLastMessageID' ) ? (string) $mail->getLastMessageID() : '';
            $sent_mime  = method_exists( $mail, 'getSentMIMEMessage' ) ? (string) $mail->getSentMIMEMessage() : '';

            return array(
                'provider'  => 'dedicated-smtp',
                'messageId' => sanitize_text_field( $message_id ),
                // Internal hand-off only. The outer Sent archiver strips this
                // before the application service constructs its public result.
                'sentMime'  => $sent_mime,
            );
        } catch ( \Throwable $error ) {
            unset( $error );
            return new \WP_Error( 'mvm_mail_smtp_failed', 'De e-mail kon niet veilig via SMTP worden verzonden.' );
        }
    }

    public function capabilities( string $mailbox_id ): array {
        $read = $this->read_provider->capabilities( $mailbox_id );
        $read['send'] = $this->configured( $mailbox_id );
        return $read;
    }

    private function configured( string $mailbox_id ): bool {
        $configured_mailbox = defined( 'MVM_HUB_MAILBOX_ID' ) ? sanitize_key( (string) MVM_HUB_MAILBOX_ID ) : 'editorial';
        if ( sanitize_key( $mailbox_id ) !== $configured_mailbox ) {
            return false;
        }

        foreach ( array( 'MVM_HUB_SMTP_HOST', 'MVM_HUB_SMTP_PORT', 'MVM_HUB_MAIL_FROM_ADDRESS', 'MVM_HUB_MAIL_FROM_NAME' ) as $constant ) {
            if ( ! defined( $constant ) || '' === trim( (string) constant( $constant ) ) ) {
                return false;
            }
        }

        $from = sanitize_email( (string) MVM_HUB_MAIL_FROM_ADDRESS );
        return '' !== $from && false !== is_email( $from );
    }

    /** @return true|\WP_Error */
    private function load_phpmailer(): true|\WP_Error {
        if ( class_exists( PHPMailer::class ) ) {
            return true;
        }

        $base = ABSPATH . WPINC . '/PHPMailer/';
        foreach ( array( 'Exception.php', 'PHPMailer.php', 'SMTP.php' ) as $file ) {
            $path = $base . $file;
            if ( ! is_readable( $path ) ) {
                return new \WP_Error( 'mvm_mail_smtp_runtime', 'De SMTP-runtime is niet beschikbaar.' );
            }
            require_once $path;
        }

        return class_exists( PHPMailer::class ) ? true : new \WP_Error( 'mvm_mail_smtp_runtime', 'De SMTP-runtime is niet beschikbaar.' );
    }

    /** @return string|\WP_Error */
    private function safe_attachment_path( string $path ): string|\WP_Error {
        if ( ! defined( 'MVM_HUB_PRIVATE_STORAGE_DIR' ) ) {
            return new \WP_Error( 'mvm_mail_private_storage', 'Private bijlageopslag is niet geconfigureerd.' );
        }

        $root = realpath( (string) MVM_HUB_PRIVATE_STORAGE_DIR );
        $real = realpath( $path );
        if ( false === $root || false === $real || ! is_file( $real ) || ! str_starts_with( $real . DIRECTORY_SEPARATOR, rtrim( $root, DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR ) ) {
            return new \WP_Error( 'mvm_mail_attachment_path', 'Een bijlage kon niet veilig worden geopend.' );
        }
        return $real;
    }
}
