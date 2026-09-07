<?php

namespace MVM\Hub\Integrations\Mail;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Private server-side storage for mail compose/reply drafts.
 * Draft bodies and recipients are confidential and must never be stored in
 * client-local persistent storage as the authoritative copy.
 */
interface Draft_Store {
    /**
     * Returned drafts must include an opaque id and integer version >= 1.
     *
     * @return array<string,mixed>|\WP_Error
     */
    public function create( int $owner_user_id, array $draft ): array|\WP_Error;

    /**
     * Optimistic concurrency prevents two tabs/autosaves from silently
     * overwriting each other. Implementations must reject stale versions.
     *
     * @return array<string,mixed>|\WP_Error
     */
    public function update_own( int $owner_user_id, string $draft_id, array $draft, int $expected_version ): array|\WP_Error;

    /** @return array<string,mixed>|\WP_Error */
    public function get_own( int $owner_user_id, string $draft_id ): array|\WP_Error;

    /** @return array<string,mixed> */
    public function list_own( int $owner_user_id, int $page = 1, int $per_page = 20 ): array;

    public function delete_own( int $owner_user_id, string $draft_id ): bool|\WP_Error;
}
