<?php

namespace MVM\Hub\Integrations\Mail;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Security scanner boundary for private mail attachments.
 * A storage implementation must not make an upload available for download or
 * delivery until scanning returns an explicit clean result.
 */
interface Attachment_Scanner {
    /**
     * @param array<string,mixed> $file Server-side private file metadata/handle.
     * @return array{status:string,engine?:string,reason?:string}|\WP_Error
     */
    public function scan( array $file ): array|\WP_Error;
}
