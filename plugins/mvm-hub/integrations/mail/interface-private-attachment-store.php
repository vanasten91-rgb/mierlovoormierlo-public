<?php

namespace MVM\Hub\Integrations\Mail;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Private attachment storage boundary for mail drafts/replies.
 *
 * Implementations must not use public Media Library URLs as the canonical
 * storage surface for confidential newsroom/mail attachments.
 */
interface Private_Attachment_Store {
    /**
     * @param array<string,mixed> $upload Normalized upload metadata/file handle.
     * @return array<string,mixed>|\WP_Error Opaque id + safe metadata only.
     */
    public function store_for_draft( int $owner_user_id, string $draft_id, array $upload ): array|\WP_Error;

    /** @return array<int,array<string,mixed>> */
    public function list_for_draft( int $owner_user_id, string $draft_id ): array;

    /**
     * Deletion is owner-scoped unless a dedicated communications-admin policy
     * explicitly authorizes otherwise.
     */
    public function delete_own( int $owner_user_id, string $draft_id, string $attachment_id ): bool|\WP_Error;

    /**
     * Returns a short-lived authorized download response/reference, never a
     * permanent public URL.
     *
     * @return array<string,mixed>|\WP_Error
     */
    public function authorize_download( int $viewer_user_id, string $attachment_id ): array|\WP_Error;

    /**
     * Resolve attachments for delivery only after ownership, draft linkage,
     * extension/MIME/size policy and malware-scan status have all passed.
     * Implementations return transport-safe opaque references, never public URLs.
     *
     * @param array<int,string> $attachment_ids
     * @return array<int,array<string,mixed>>|\WP_Error
     */
    public function authorize_for_send( int $owner_user_id, string $draft_id, array $attachment_ids ): array|\WP_Error;
}
