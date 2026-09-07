<?php

namespace MVM\Hub\Modules\Communications;

use MVM\Hub\Core\Capabilities;
use MVM\Hub\Core\Communications_Policy;
use MVM\Hub\Integrations\Mail\Attachment_Normalizer;
use MVM\Hub\Integrations\Mail\Attachment_Scanner;
use MVM\Hub\Integrations\Mail\Communications_Audit;
use MVM\Hub\Integrations\Mail\Draft_Store;
use MVM\Hub\Integrations\Mail\Private_Attachment_Store;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Private attachment ingest service for mail drafts.
 *
 * No HTTP upload route exists yet. Future controllers must add nonce/session,
 * request-size and upload-error validation before invoking this service.
 */
final class Mail_Attachment_Service {
    public function __construct(
        private readonly Draft_Store $drafts,
        private readonly Private_Attachment_Store $attachments,
        private readonly Attachment_Normalizer $normalizer,
        private readonly Attachment_Scanner $scanner,
        private readonly Communications_Audit $audit
    ) {}

    /** @param array<string,mixed> $upload @return array<string,mixed>|\WP_Error */
    public function upload( string $draft_id, array $upload ): array|\WP_Error {
        $user_id = $this->authorized_user();
        if ( is_wp_error( $user_id ) ) {
            return $user_id;
        }

        $draft_id = self::opaque_id( $draft_id );
        if ( '' === $draft_id ) {
            return new \WP_Error( 'mvm_mail_draft_id', 'Ongeldig concept-ID.' );
        }

        $draft = $this->drafts->get_own( $user_id, $draft_id );
        if ( is_wp_error( $draft ) ) {
            return new \WP_Error( 'mvm_mail_attachment_draft_denied', 'De bijlage kan niet aan dit concept worden gekoppeld.' );
        }

        $normalized = $this->normalizer->normalize( $upload );
        if ( is_wp_error( $normalized ) ) {
            $this->audit->record( 'mail.attachment.upload', 'normalize_failed', $user_id, self::safe_ref( $draft_id ) );
            return $normalized;
        }

        $name = sanitize_file_name( (string) ( $normalized['name'] ?? '' ) );
        $mime = sanitize_mime_type( (string) ( $normalized['mimeType'] ?? '' ) );
        $size = max( 0, (int) ( $normalized['sizeBytes'] ?? 0 ) );
        $handle = (string) ( $normalized['privateHandle'] ?? '' );

        if (
            ! Communications_Policy::attachment_type_allowed( $name, $mime, $size )
            || ! Communications_Policy::attachment_content_allowed( $name, $mime, $size, $handle )
        ) {
            self::discard_private_handle( $handle );
            $this->audit->record(
                'mail.attachment.upload',
                'policy_denied',
                $user_id,
                self::safe_ref( $draft_id ),
                array( 'size_bytes' => $size )
            );
            return new \WP_Error( 'mvm_mail_attachment_type', 'Dit bestandstype of deze bestandsgrootte is niet toegestaan.' );
        }

        $scan = $this->scanner->scan( $normalized );
        if ( is_wp_error( $scan ) || 'clean' !== sanitize_key( (string) ( is_array( $scan ) ? ( $scan['status'] ?? '' ) : '' ) ) ) {
            self::discard_private_handle( $handle );
            $this->audit->record(
                'mail.attachment.upload',
                'scan_denied',
                $user_id,
                self::safe_ref( $draft_id ),
                array( 'size_bytes' => $size )
            );
            return is_wp_error( $scan ) ? $scan : new \WP_Error( 'mvm_mail_attachment_scan', 'De bijlage is niet vrijgegeven door de beveiligingsscan.' );
        }

        $normalized['name']       = $name;
        $normalized['mimeType']   = $mime;
        $normalized['sizeBytes']  = $size;
        $normalized['scanStatus'] = 'clean';
        $normalized['contentValidationStatus'] = 'verified';

        $stored = $this->attachments->store_for_draft( $user_id, $draft_id, $normalized );
        if ( is_wp_error( $stored ) ) {
            self::discard_private_handle( $handle );
            $this->audit->record( 'mail.attachment.upload', 'store_failed', $user_id, self::safe_ref( $draft_id ), array( 'size_bytes' => $size ) );
            return $stored;
        }

        $attachment_id = self::opaque_id( (string) ( $stored['id'] ?? '' ) );
        $this->audit->record(
            'mail.attachment.upload',
            'success',
            $user_id,
            self::safe_ref( $attachment_id ),
            array( 'size_bytes' => $size )
        );

        return array(
            'id'         => $attachment_id,
            'name'       => sanitize_file_name( (string) ( $stored['name'] ?? $name ) ),
            'mimeType'   => sanitize_mime_type( (string) ( $stored['mimeType'] ?? $mime ) ),
            'sizeBytes'  => max( 0, (int) ( $stored['sizeBytes'] ?? $size ) ),
            'scanStatus' => 'clean',
            'contentValidationStatus' => 'verified',
        );
    }

    /** @return int|\WP_Error */
    private function authorized_user(): int|\WP_Error {
        if ( ! is_user_logged_in() || ! Capabilities::can_access_mail() ) {
            return new \WP_Error( 'mvm_mail_auth_required', 'Geen toegang tot deze mailbox.' );
        }

        if (
            ! current_user_can( 'manage_options' )
            && ! current_user_can( Capabilities::COMMUNICATIONS_ADMIN )
            && ! current_user_can( Capabilities::MAIL_MANAGE_OWN_ATTACHMENTS )
        ) {
            return new \WP_Error( 'mvm_mail_attachment_forbidden', 'Je hebt geen toestemming om bijlagen te beheren.' );
        }

        return get_current_user_id();
    }

    private static function opaque_id( string $value ): string {
        $value = trim( $value );
        return strlen( $value ) <= 128 && 1 === preg_match( '/^[A-Za-z0-9._:-]+$/', $value ) ? $value : '';
    }

    private static function safe_ref( string $value ): string {
        $id = self::opaque_id( $value );
        return '' === $id ? '' : substr( wp_hash( $id, 'mvm_hub_mail_object' ), 0, 24 );
    }

    private static function discard_private_handle( string $path ): void {
        if ( '' !== $path && is_file( $path ) ) {
            @unlink( $path );
        }
    }
}
