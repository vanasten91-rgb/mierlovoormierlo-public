<?php

namespace MVM\Hub\Modules\Communications;

use MVM\Hub\Core\Communications_Policy;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Dormant fail-closed policy projection for private Mail V2 attachments.
 *
 * It evaluates metadata only. Storage, malware scanning and download/delivery
 * authorization remain owned by dedicated server-side services.
 */
final class V5_Mail_Attachment_Policy {
    private const DEFAULT_MAX_BYTES = 25_000_000;

    /**
     * @param array<string,mixed> $attachment
     * @param array<string,mixed> $policy
     * @return array<string,mixed>
     */
    public static function evaluate( array $attachment, array $policy = array() ): array {
        $id = self::opaque_id( (string) ( $attachment['id'] ?? '' ), 190 );
        $scan = strtolower( trim( (string) ( $attachment['scanStatus'] ?? 'unknown' ) ) );
        if ( ! in_array( $scan, array( 'pending', 'clean', 'infected', 'error', 'quarantined', 'unknown' ), true ) ) {
            $scan = 'unknown';
        }

        $private_storage  = true === ( $attachment['privateStorage'] ?? false );
        $owner_authorized = true === ( $attachment['ownerAuthorized'] ?? false );
        $draft_linked     = true === ( $attachment['draftLinked'] ?? false );
        $size             = max( 0, (int) ( $attachment['size'] ?? 0 ) );
        $max_bytes        = max( 1, (int) ( $policy['maxBytes'] ?? self::DEFAULT_MAX_BYTES ) );
        $name             = sanitize_file_name( (string) ( $attachment['name'] ?? '' ) );
        $extension        = strtolower( (string) pathinfo( $name, PATHINFO_EXTENSION ) );
        $mime             = self::mime( (string) ( $attachment['mime'] ?? 'application/octet-stream' ) );
        $content_status   = strtolower( trim( (string) ( $attachment['contentValidationStatus'] ?? 'unknown' ) ) );
        if ( ! in_array( $content_status, array( 'verified', 'failed', 'unknown' ), true ) ) {
            $content_status = 'unknown';
        }

        $allowed_mimes = is_array( $policy['allowedMimes'] ?? null )
            ? array_values( array_filter( array_map( static fn( $value ) => self::mime( (string) $value ), $policy['allowedMimes'] ) ) )
            : array();
        $allowed_extensions = is_array( $policy['allowedExtensions'] ?? null )
            ? array_values( array_filter( array_map( static fn( $value ) => self::extension( (string) $value ), $policy['allowedExtensions'] ) ) )
            : array();
        $extension_allowed = Communications_Policy::attachment_extension_allowed( $name )
            && ( ! array_key_exists( 'allowedExtensions', $policy ) || in_array( $extension, $allowed_extensions, true ) );
        $mime_allowed = $extension_allowed
            && Communications_Policy::attachment_mime_allowed( $name, $mime )
            && ( ! array_key_exists( 'allowedMimes', $policy ) || in_array( $mime, $allowed_mimes, true ) );

        $reasons = array();
        if ( '' === $id ) {
            $reasons[] = 'attachment_identity_invalid';
        }
        if ( ! $private_storage ) {
            $reasons[] = 'private_storage_required';
        }
        if ( ! $owner_authorized ) {
            $reasons[] = 'owner_authorization_required';
        }
        if ( ! $draft_linked ) {
            $reasons[] = 'draft_link_required';
        }
        if ( $size <= 0 || $size > $max_bytes ) {
            $reasons[] = 'size_limit_exceeded';
        }
        if ( ! $extension_allowed ) {
            $reasons[] = 'extension_not_allowed';
        }
        if ( ! $mime_allowed ) {
            $reasons[] = 'mime_not_allowed';
        }
        if ( 'verified' !== $content_status ) {
            $reasons[] = 'failed' === $content_status
                ? 'content_validation_failed'
                : 'content_validation_required';
        }
        if ( 'clean' !== $scan ) {
            $reasons[] = match ( $scan ) {
                'pending'     => 'malware_scan_pending',
                'infected'    => 'malware_detected',
                'quarantined' => 'attachment_quarantined',
                'error'       => 'malware_scan_error',
                default       => 'malware_scan_required',
            };
        }

        return array(
            'allowedForSend' => array() === $reasons,
            'attachmentId'   => $id,
            'name'           => $name,
            'extension'      => $extension,
            'mime'           => $mime,
            'size'           => $size,
            'scanStatus'     => $scan,
            'contentValidationStatus' => $content_status,
            'reasons'        => $reasons,
            'security'       => array(
                'privateStorageRequired' => true,
                'ownerAuthorizationRequired' => true,
                'draftLinkRequired' => true,
                'extensionAllowlistRequired' => true,
                'mimeAllowlistRequired' => true,
                'contentValidationRequired' => true,
                'malwareScanRequired' => true,
                'publicUrlAllowed' => false,
            ),
        );
    }

    private static function mime( string $value ): string {
        $value = strtolower( trim( $value ) );
        return 1 === preg_match( '#^[a-z0-9.+-]+/[a-z0-9.+-]+$#', $value )
            ? $value
            : 'application/octet-stream';
    }

    private static function extension( string $value ): string {
        $value = strtolower( ltrim( trim( $value ), '.' ) );
        return 1 === preg_match( '/^[a-z0-9]{1,10}$/', $value ) ? $value : '';
    }

    private static function opaque_id( string $value, int $max_length ): string {
        $value = trim( $value );
        if ( '' === $value || strlen( $value ) > $max_length ) {
            return '';
        }
        return 1 === preg_match( '/^[A-Za-z0-9._:-]+$/', $value ) ? $value : '';
    }

    private function __construct() {}
}
