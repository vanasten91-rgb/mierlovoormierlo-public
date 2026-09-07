<?php

namespace MVM\Hub\Integrations\Mail;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Server-side normalization boundary for incoming private attachments.
 *
 * Implementations must derive MIME/type/size from the actual private temporary
 * file rather than trusting browser metadata. Supported images must be safely
 * re-encoded or stripped of EXIF/GPS metadata before malware scanning/storage.
 */
interface Attachment_Normalizer {
    /**
     * @param array<string,mixed> $upload Raw server-side upload descriptor.
     * @return array{name:string,mimeType:string,sizeBytes:int,privateHandle:mixed}|\WP_Error
     */
    public function normalize( array $upload ): array|\WP_Error;
}
