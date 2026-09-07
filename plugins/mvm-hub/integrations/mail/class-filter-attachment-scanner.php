<?php

namespace MVM\Hub\Integrations\Mail;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Malware-scanner adapter. No attachment is considered clean unless a dedicated
 * scanner integration explicitly returns status=clean.
 */
final class Filter_Attachment_Scanner implements Attachment_Scanner {
    /** @return array<string,mixed>|\WP_Error */
    public function scan( array $normalized_upload ): array|\WP_Error {
        $path = (string) ( $normalized_upload['privateHandle'] ?? '' );
        if ( '' === $path || ! is_file( $path ) || ! is_readable( $path ) ) {
            return new \WP_Error( 'mvm_mail_attachment_scan_source', 'De bijlage kan niet veilig worden gescand.' );
        }

        $result = apply_filters(
            'mvm_hub_private_attachment_scan_v1',
            null,
            array(
                'path'      => $path,
                'mimeType'  => sanitize_mime_type( (string) ( $normalized_upload['mimeType'] ?? '' ) ),
                'sizeBytes' => max( 0, (int) ( $normalized_upload['sizeBytes'] ?? 0 ) ),
            )
        );

        if ( is_wp_error( $result ) ) {
            return $result;
        }
        if ( is_array( $result ) && 'clean' === sanitize_key( (string) ( $result['status'] ?? '' ) ) ) {
            return array( 'status' => 'clean' );
        }

        return new \WP_Error( 'mvm_mail_attachment_scanner_unavailable', 'De beveiligingsscan heeft de bijlage niet vrijgegeven.' );
    }
}
