<?php

namespace MVM\Hub\Modules\Communications;

use MVM\Hub\Core\Capabilities;
use MVM\Hub\Core\Communications_Policy;
use MVM\Hub\Integrations\Mail\Communications_Audit;
use MVM\Hub\Integrations\Mail\Draft_Store;
use MVM\Hub\Integrations\Mail\Private_Attachment_Store;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Owner-scoped mail draft/application service.
 *
 * No REST route is exposed during the parallel migration. A future controller
 * must still add CSRF/session checks; this service enforces capability and
 * ownership boundaries at the business layer.
 */
final class Mail_Draft_Service {
    public function __construct(
        private readonly Draft_Store $drafts,
        private readonly Private_Attachment_Store $attachments,
        private readonly Communications_Audit $audit
    ) {}

    /** @param array<string,mixed> $input @return array<string,mixed>|\WP_Error */
    public function create( array $input ): array|\WP_Error {
        $user_id = $this->authorized_user();
        if ( is_wp_error( $user_id ) ) {
            return $user_id;
        }

        $draft = Mail_Compose_Validator::validate_draft( $input );
        if ( is_wp_error( $draft ) ) {
            return $draft;
        }

        unset( $draft['draftId'], $draft['recipientCount'] );
        $result = $this->drafts->create( $user_id, $draft );
        if ( is_wp_error( $result ) ) {
            $this->audit->record( 'mail.draft.create', 'failed', $user_id );
            return $result;
        }

        $this->audit->record( 'mail.draft.create', 'success', $user_id, self::safe_ref( $result['id'] ?? '' ) );
        return self::project_draft_result( $result );
    }

    /** @param array<string,mixed> $input @return array<string,mixed>|\WP_Error */
    public function update( string $draft_id, array $input, int $expected_version ): array|\WP_Error {
        $user_id = $this->authorized_user();
        if ( is_wp_error( $user_id ) ) {
            return $user_id;
        }

        $draft_id = self::opaque_id( $draft_id );
        if ( '' === $draft_id || $expected_version < 1 ) {
            return new \WP_Error( 'mvm_mail_draft_version', 'Concept-ID en geldige versie zijn vereist.' );
        }

        $draft = Mail_Compose_Validator::validate_draft( $input );
        if ( is_wp_error( $draft ) ) {
            return $draft;
        }

        unset( $draft['draftId'], $draft['recipientCount'] );
        $result = $this->drafts->update_own( $user_id, $draft_id, $draft, $expected_version );
        if ( is_wp_error( $result ) ) {
            $this->audit->record( 'mail.draft.update', 'failed', $user_id, self::safe_ref( $draft_id ), array( 'expected_version' => $expected_version ) );
            return $result;
        }

        $this->audit->record( 'mail.draft.update', 'success', $user_id, self::safe_ref( $draft_id ), array( 'expected_version' => $expected_version ) );
        return self::project_draft_result( $result );
    }

    /** @return array<string,mixed>|\WP_Error */
    public function get( string $draft_id ): array|\WP_Error {
        $user_id = $this->authorized_user();
        if ( is_wp_error( $user_id ) ) {
            return $user_id;
        }

        $draft_id = self::opaque_id( $draft_id );
        if ( '' === $draft_id ) {
            return new \WP_Error( 'mvm_mail_draft_id', 'Ongeldig concept-ID.' );
        }

        $stored = $this->drafts->get_own( $user_id, $draft_id );
        if ( is_wp_error( $stored ) ) {
            return $stored;
        }

        $validated = Mail_Compose_Validator::validate_draft( $stored );
        if ( is_wp_error( $validated ) ) {
            return new \WP_Error( 'mvm_mail_draft_corrupt', 'Het opgeslagen concept kon niet veilig worden geladen.' );
        }

        $attachment_meta = $this->attachments->list_for_draft( $user_id, $draft_id );

        return array_merge(
            self::project_draft_result( $stored ),
            array(
                'mode'              => $validated['mode'],
                'to'                => $validated['to'],
                'cc'                => $validated['cc'],
                'bcc'               => $validated['bcc'],
                'subject'           => $validated['subject'],
                'htmlBody'          => $validated['htmlBody'],
                'textBody'          => $validated['textBody'],
                'originalMessageId' => $validated['originalMessageId'],
                'inReplyTo'         => $validated['inReplyTo'],
                'references'        => $validated['references'],
                'attachments'       => self::project_attachments( $attachment_meta ),
            )
        );
    }

    /** @return array<string,mixed>|\WP_Error */
    public function list( int $page = 1, int $per_page = 20 ): array|\WP_Error {
        $user_id = $this->authorized_user();
        if ( is_wp_error( $user_id ) ) {
            return $user_id;
        }

        $page     = max( 1, min( 100, $page ) );
        $per_page = max( 1, min( 50, $per_page ) );
        $stored   = $this->drafts->list_own( $user_id, $page, $per_page );
        $items    = array();

        foreach ( (array) ( $stored['items'] ?? array() ) as $item ) {
            if ( ! is_array( $item ) ) {
                continue;
            }
            $items[] = array(
                'id'           => self::opaque_id( (string) ( $item['id'] ?? '' ) ),
                'version'      => max( 0, (int) ( $item['version'] ?? 0 ) ),
                'mode'         => sanitize_key( (string) ( $item['mode'] ?? 'compose' ) ),
                'subject'      => mb_substr( sanitize_text_field( (string) ( $item['subject'] ?? '' ) ), 0, 255 ),
                'updatedAtUtc' => sanitize_text_field( (string) ( $item['updatedAtUtc'] ?? '' ) ),
            );
        }

        return array(
            'items'   => array_values( $items ),
            'page'    => $page,
            'perPage' => $per_page,
            'hasMore' => true === ( $stored['hasMore'] ?? false ),
        );
    }

    public function delete( string $draft_id ): true|\WP_Error {
        $user_id = $this->authorized_user();
        if ( is_wp_error( $user_id ) ) {
            return $user_id;
        }

        $draft_id = self::opaque_id( $draft_id );
        if ( '' === $draft_id ) {
            return new \WP_Error( 'mvm_mail_draft_id', 'Ongeldig concept-ID.' );
        }

        $deleted = $this->drafts->delete_own( $user_id, $draft_id );
        if ( is_wp_error( $deleted ) || true !== $deleted ) {
            $this->audit->record( 'mail.draft.delete', 'failed', $user_id, self::safe_ref( $draft_id ) );
            return is_wp_error( $deleted ) ? $deleted : new \WP_Error( 'mvm_mail_draft_delete', 'Concept kon niet worden verwijderd.' );
        }

        $this->audit->record( 'mail.draft.delete', 'success', $user_id, self::safe_ref( $draft_id ) );
        return true;
    }

    public function delete_attachment( string $draft_id, string $attachment_id ): true|\WP_Error {
        $user_id = $this->authorized_user( true );
        if ( is_wp_error( $user_id ) ) {
            return $user_id;
        }

        $draft_id      = self::opaque_id( $draft_id );
        $attachment_id = self::opaque_id( $attachment_id );
        if ( '' === $draft_id || '' === $attachment_id ) {
            return new \WP_Error( 'mvm_mail_attachment_id', 'Ongeldige bijlageverwijzing.' );
        }

        $deleted = $this->attachments->delete_own( $user_id, $draft_id, $attachment_id );
        if ( is_wp_error( $deleted ) || true !== $deleted ) {
            $this->audit->record( 'mail.attachment.delete', 'failed', $user_id, self::safe_ref( $attachment_id ) );
            return is_wp_error( $deleted ) ? $deleted : new \WP_Error( 'mvm_mail_attachment_delete', 'Bijlage kon niet worden verwijderd.' );
        }

        $this->audit->record( 'mail.attachment.delete', 'success', $user_id, self::safe_ref( $attachment_id ) );
        return true;
    }

    /** @return int|\WP_Error */
    private function authorized_user( bool $attachment_action = false ): int|\WP_Error {
        if ( ! is_user_logged_in() || ! Capabilities::can_access_mail() ) {
            return new \WP_Error( 'mvm_mail_auth_required', 'Geen toegang tot deze mailbox.' );
        }

        $allowed = current_user_can( 'manage_options' )
            || current_user_can( Capabilities::COMMUNICATIONS_ADMIN )
            || current_user_can( $attachment_action ? Capabilities::MAIL_MANAGE_OWN_ATTACHMENTS : Capabilities::MAIL_COMPOSE );

        if ( ! $allowed ) {
            return new \WP_Error( 'mvm_mail_draft_forbidden', 'Je hebt geen toestemming voor deze actie.' );
        }

        return get_current_user_id();
    }

    /** @param array<string,mixed> $result @return array<string,mixed> */
    private static function project_draft_result( array $result ): array {
        return array(
            'id'           => self::opaque_id( (string) ( $result['id'] ?? '' ) ),
            'version'      => max( 0, (int) ( $result['version'] ?? 0 ) ),
            'updatedAtUtc' => sanitize_text_field( (string) ( $result['updatedAtUtc'] ?? '' ) ),
        );
    }

    /** @param array<int,array<string,mixed>> $items @return array<int,array<string,mixed>> */
    private static function project_attachments( array $items ): array {
        $projected = array();
        foreach ( $items as $item ) {
            if ( ! is_array( $item ) ) {
                continue;
            }
            $projected[] = array(
                'id'         => self::opaque_id( (string) ( $item['id'] ?? '' ) ),
                'name'       => sanitize_file_name( (string) ( $item['name'] ?? '' ) ),
                'mimeType'   => sanitize_mime_type( (string) ( $item['mimeType'] ?? '' ) ),
                'sizeBytes'  => max( 0, (int) ( $item['sizeBytes'] ?? 0 ) ),
                'scanStatus' => sanitize_key( (string) ( $item['scanStatus'] ?? 'pending' ) ),
            );
        }
        return array_values( $projected );
    }

    private static function opaque_id( string $value ): string {
        $value = trim( $value );
        return strlen( $value ) <= 128 && 1 === preg_match( '/^[A-Za-z0-9._:-]+$/', $value ) ? $value : '';
    }

    private static function safe_ref( mixed $value ): string {
        $id = self::opaque_id( (string) $value );
        return '' === $id ? '' : substr( wp_hash( $id, 'mvm_hub_mail_object' ), 0, 24 );
    }
}
