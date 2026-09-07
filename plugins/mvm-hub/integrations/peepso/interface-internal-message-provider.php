<?php

namespace MVM\Hub\Integrations\PeepSo;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Adapter boundary for existing PeepSo internal messages.
 *
 * The central Hub must not read PeepSo message rows directly from arbitrary UI
 * code. All reads/writes pass through this participant-scoped provider contract.
 */
interface Internal_Message_Provider {
    /** @return array<string,mixed> */
    public function list_threads_for_user( int $user_id, array $query = array() ): array;

    /** @return array<string,mixed>|\WP_Error */
    public function get_thread_for_user( int $user_id, int $thread_id ): array|\WP_Error;

    /** @return array<string,mixed>|\WP_Error */
    public function create_thread( int $actor_user_id, array $participant_user_ids, string $body ): array|\WP_Error;

    /** @return array<string,mixed>|\WP_Error */
    public function reply( int $actor_user_id, int $thread_id, string $body ): array|\WP_Error;

    public function mark_read( int $actor_user_id, int $thread_id ): bool|\WP_Error;

    public function archive_for_user( int $actor_user_id, int $thread_id ): bool|\WP_Error;

    /**
     * Deletion is user-scoped. A normal staff capability does not grant access to
     * other users' private threads or allow silent global deletion.
     */
    public function delete_for_user( int $actor_user_id, int $thread_id ): bool|\WP_Error;
}
