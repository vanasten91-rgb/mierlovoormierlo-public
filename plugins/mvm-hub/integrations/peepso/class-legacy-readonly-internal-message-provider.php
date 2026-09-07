<?php

namespace MVM\Hub\Integrations\PeepSo;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Read-only adapter for the existing PeepSo message tables.
 *
 * Thread access is proven by an mpart_user_id membership row for the current
 * user. The adapter never treats a WordPress role or manage_options as thread
 * membership and never exposes provider/database internals to the Hub UI.
 */
final class Legacy_Readonly_Internal_Message_Provider implements Internal_Message_Provider {
    private \wpdb $wpdb;

    public function __construct( ?\wpdb $database = null ) {
        global $wpdb;
        $this->wpdb = $database ?? $wpdb;
    }

    /** @return array<string,mixed> */
    public function list_threads_for_user( int $user_id, array $query = array() ): array {
        if ( $user_id <= 0 || $user_id !== get_current_user_id() ) {
            return array( 'items' => array(), 'hasMore' => false );
        }

        $page     = max( 1, min( 100, (int) ( $query['page'] ?? 1 ) ) );
        $per_page = max( 1, min( 50, (int) ( $query['perPage'] ?? 20 ) ) );
        $limit    = $per_page + 1;
        $offset   = ( $page - 1 ) * $per_page;
        $participants = $this->participants_table();
        $recipients   = $this->recipients_table();
        $posts        = $this->wpdb->posts;

        if ( ! $this->table_exists( $participants ) || ! $this->table_exists( $recipients ) ) {
            return array( 'items' => array(), 'hasMore' => false );
        }

        $sql = "SELECT p.ID AS thread_id,p.post_title,p.post_modified_gmt,mp.mpart_last_activity,
                (SELECT COUNT(*) FROM {$recipients} r
                    WHERE r.mrec_parent_id=p.ID AND r.mrec_user_id=%d
                    AND COALESCE(r.mrec_deleted,0)=0 AND COALESCE(r.mrec_viewed,0)=0) AS unread_count
            FROM {$participants} mp
            INNER JOIN {$posts} p ON p.ID=mp.mpart_msg_id
            WHERE mp.mpart_user_id=%d AND p.post_type='peepso-message' AND p.post_parent=0 AND p.post_status='publish'
            ORDER BY COALESCE(mp.mpart_last_activity,p.post_modified_gmt) DESC,p.ID DESC
            LIMIT %d OFFSET %d";

        $prepared = $this->wpdb->prepare( $sql, $user_id, $user_id, $limit, $offset );
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- tables are internal fixed names and placeholders are prepared.
        $rows = $this->wpdb->get_results( $prepared, ARRAY_A );
        $rows = is_array( $rows ) ? array_values( $rows ) : array();
        $has_more = count( $rows ) > $per_page;
        $rows = array_slice( $rows, 0, $per_page );

        $items = array();
        foreach ( $rows as $row ) {
            $thread_id = absint( $row['thread_id'] ?? 0 );
            if ( $thread_id <= 0 ) {
                continue;
            }
            $title = sanitize_text_field( (string) ( $row['post_title'] ?? '' ) );
            $items[] = array(
                'id'           => $thread_id,
                'title'        => '' !== $title ? $title : 'Gesprek',
                'participants' => $this->participant_ids( $thread_id ),
                'unreadCount'  => max( 0, (int) ( $row['unread_count'] ?? 0 ) ),
                'updatedAtUtc' => $this->utc_value( (string) ( $row['mpart_last_activity'] ?: $row['post_modified_gmt'] ?? '' ) ),
                'archived'     => false,
            );
        }

        return array( 'items' => $items, 'hasMore' => $has_more );
    }

    /** @return array<string,mixed>|\WP_Error */
    public function get_thread_for_user( int $user_id, int $thread_id ): array|\WP_Error {
        if ( ! $this->is_participant( $user_id, $thread_id ) ) {
            return new \WP_Error( 'mvm_messages_thread_forbidden', 'Dit gesprek is niet beschikbaar.' );
        }

        $root = get_post( $thread_id );
        if ( ! $root instanceof \WP_Post || 'peepso-message' !== $root->post_type || 0 !== (int) $root->post_parent ) {
            return new \WP_Error( 'mvm_messages_thread_missing', 'Dit gesprek is niet beschikbaar.' );
        }

        $recipients = $this->recipients_table();
        $posts      = $this->wpdb->posts;
        $sql = "SELECT p.ID,p.post_author,p.post_content,p.post_date_gmt
            FROM {$posts} p
            WHERE p.post_type='peepso-message' AND p.post_status='publish'
              AND (p.ID=%d OR p.post_parent=%d)
              AND EXISTS (
                SELECT 1 FROM {$recipients} r
                WHERE r.mrec_msg_id=p.ID AND r.mrec_user_id=%d AND COALESCE(r.mrec_deleted,0)=0
              )
            ORDER BY p.post_date_gmt ASC,p.ID ASC
            LIMIT 500";
        $prepared = $this->wpdb->prepare( $sql, $thread_id, $thread_id, $user_id );
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- internal fixed tables with prepared values.
        $rows = $this->wpdb->get_results( $prepared, ARRAY_A );
        $rows = is_array( $rows ) ? array_values( $rows ) : array();

        $messages = array_map(
            static fn( array $row ): array => array(
                'id'           => absint( $row['ID'] ?? 0 ),
                'authorUserId' => absint( $row['post_author'] ?? 0 ),
                'body'         => (string) ( $row['post_content'] ?? '' ),
                'createdAtUtc' => (string) ( $row['post_date_gmt'] ?? '' ),
            ),
            $rows
        );

        $updated = '';
        if ( array() !== $messages ) {
            $last = end( $messages );
            $updated = (string) ( $last['createdAtUtc'] ?? '' );
            reset( $messages );
        }

        return array(
            'id'           => $thread_id,
            'title'        => '' !== trim( (string) $root->post_title ) ? (string) $root->post_title : 'Gesprek',
            'participants' => $this->participant_ids( $thread_id ),
            'messages'     => $messages,
            'unreadCount'  => $this->unread_count( $user_id, $thread_id ),
            'updatedAtUtc' => $updated,
            'archived'     => false,
        );
    }

    /** @return array<string,mixed>|\WP_Error */
    public function create_thread( int $actor_user_id, array $participant_user_ids, string $body ): array|\WP_Error {
        unset( $actor_user_id, $participant_user_ids, $body );
        return $this->read_only();
    }

    /** @return array<string,mixed>|\WP_Error */
    public function reply( int $actor_user_id, int $thread_id, string $body ): array|\WP_Error {
        unset( $actor_user_id, $thread_id, $body );
        return $this->read_only();
    }

    public function mark_read( int $actor_user_id, int $thread_id ): bool|\WP_Error {
        unset( $actor_user_id, $thread_id );
        return $this->read_only();
    }

    public function archive_for_user( int $actor_user_id, int $thread_id ): bool|\WP_Error {
        unset( $actor_user_id, $thread_id );
        return $this->read_only();
    }

    public function delete_for_user( int $actor_user_id, int $thread_id ): bool|\WP_Error {
        unset( $actor_user_id, $thread_id );
        return $this->read_only();
    }

    private function is_participant( int $user_id, int $thread_id ): bool {
        if ( $user_id <= 0 || $thread_id <= 0 || $user_id !== get_current_user_id() ) {
            return false;
        }
        $table = $this->participants_table();
        if ( ! $this->table_exists( $table ) ) {
            return false;
        }
        $sql = $this->wpdb->prepare(
            "SELECT 1 FROM {$table} WHERE mpart_msg_id=%d AND mpart_user_id=%d LIMIT 1",
            $thread_id,
            $user_id
        );
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- internal fixed table with prepared values.
        return '1' === (string) $this->wpdb->get_var( $sql );
    }

    /** @return array<int,int> */
    private function participant_ids( int $thread_id ): array {
        $table = $this->participants_table();
        $sql = $this->wpdb->prepare(
            "SELECT DISTINCT mpart_user_id FROM {$table} WHERE mpart_msg_id=%d ORDER BY mpart_user_id ASC LIMIT 25",
            $thread_id
        );
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- internal fixed table with prepared value.
        $values = $this->wpdb->get_col( $sql );
        return array_values( array_filter( array_map( 'absint', is_array( $values ) ? $values : array() ) ) );
    }

    private function unread_count( int $user_id, int $thread_id ): int {
        $table = $this->recipients_table();
        $sql = $this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE mrec_parent_id=%d AND mrec_user_id=%d AND COALESCE(mrec_deleted,0)=0 AND COALESCE(mrec_viewed,0)=0",
            $thread_id,
            $user_id
        );
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- internal fixed table with prepared values.
        return max( 0, (int) $this->wpdb->get_var( $sql ) );
    }

    private function table_exists( string $table ): bool {
        $found = $this->wpdb->get_var( $this->wpdb->prepare( 'SHOW TABLES LIKE %s', $this->wpdb->esc_like( $table ) ) );
        return $table === $found;
    }

    private function participants_table(): string {
        return $this->wpdb->prefix . 'peepso_message_participants';
    }

    private function recipients_table(): string {
        return $this->wpdb->prefix . 'peepso_message_recipients';
    }

    private function utc_value( string $value ): string {
        $value = trim( $value );
        return mb_substr( sanitize_text_field( $value ), 0, 40 );
    }

    private function read_only(): \WP_Error {
        return new \WP_Error( 'mvm_messages_read_only', 'De bestaande PeepSo-berichtopslag is tijdens de migratie alleen-lezen.' );
    }
}
