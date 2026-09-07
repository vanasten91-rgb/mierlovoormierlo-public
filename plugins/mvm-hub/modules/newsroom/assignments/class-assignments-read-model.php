<?php

namespace MVM\Hub\Modules\Newsroom\Assignments;

use MVM\Hub\Core\Capabilities;
use MVM\Hub\Core\Object_Access;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Assignments_Read_Model {
    private \wpdb $wpdb;

    public function __construct( ?\wpdb $database = null ) {
        global $wpdb;
        $this->wpdb = $database ?? $wpdb;
    }

    /** @return array<string,mixed> */
    public function list( int $page = 1, int $per_page = 20, string $status = '' ): array {
        $page     = max( 1, min( 100, $page ) );
        $per_page = max( 1, min( 50, $per_page ) );
        $table    = $this->wpdb->prefix . 'mvm_hub4_assignments';

        $exists = $this->wpdb->get_var(
            $this->wpdb->prepare( 'SHOW TABLES LIKE %s', $this->wpdb->esc_like( $table ) )
        );
        if ( $exists !== $table ) {
            return $this->payload( array(), $page, $per_page, false, 'own' );
        }

        $legacy_statuses = array( 'signal', 'assigned', 'in_progress', 'review', 'ready', 'scheduled', 'published', 'cancelled' );
        $status = sanitize_key( $status );
        $params = array();
        $where  = array();
        $team   = Capabilities::can_manage_assignments();

        if ( ! $team ) {
            $user_id = get_current_user_id();
            $where[] = '(assignee_user_id = %d OR created_by_user_id = %d)';
            $params[] = $user_id;
            $params[] = $user_id;
        }

        if ( in_array( $status, $legacy_statuses, true ) ) {
            $where[] = 'status = %s';
            $params[] = $status;
        }

        $where_sql = array() === $where ? '1=1' : implode( ' AND ', $where );
        $params[] = $per_page + 1;
        $params[] = ( $page - 1 ) * $per_page;

        $sql = "SELECT id,type,status,priority,title,source_post_id,event_post_id,news_post_id,assignee_user_id,created_by_user_id,due_at_utc,created_at_utc,updated_at_utc,completed_at_utc
            FROM {$table}
            WHERE {$where_sql}
            ORDER BY CASE WHEN due_at_utc IS NULL THEN 1 ELSE 0 END,due_at_utc ASC,priority ASC,id DESC
            LIMIT %d OFFSET %d";
        $prepared = $this->wpdb->prepare( $sql, ...$params );
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- internally constructed SQL with prepared placeholders.
        $rows = $this->wpdb->get_results( $prepared, ARRAY_A );
        $rows = is_array( $rows ) ? array_values( $rows ) : array();

        $items = array();
        foreach ( $rows as $row ) {
            if ( ! Object_Access::can_read_assignment( $row ) ) {
                continue;
            }

            $items[] = array(
                'id'              => (int) $row['id'],
                'type'            => sanitize_key( (string) $row['type'] ),
                'legacyStatus'    => sanitize_key( (string) $row['status'] ),
                'workflowState'   => Assignment_Workflow::from_legacy_status( (string) $row['status'] ),
                'priority'        => (int) $row['priority'],
                'title'           => sanitize_text_field( (string) $row['title'] ),
                'sourcePostId'    => (int) $row['source_post_id'],
                'eventPostId'     => (int) $row['event_post_id'],
                'newsPostId'      => (int) $row['news_post_id'],
                'assigneeUserId'  => (int) $row['assignee_user_id'],
                'createdByUserId' => (int) $row['created_by_user_id'],
                'dueAtUtc'        => $row['due_at_utc'] ?: null,
                'createdAtUtc'    => (string) $row['created_at_utc'],
                'updatedAtUtc'    => (string) $row['updated_at_utc'],
                'completedAtUtc'  => $row['completed_at_utc'] ?: null,
            );
        }

        $has_more = count( $items ) > $per_page;
        $items = array_slice( $items, 0, $per_page );
        return $this->payload( $items, $page, $per_page, $has_more, $team ? 'team' : 'own' );
    }

    /** @param array<int,array<string,mixed>> $items */
    private function payload( array $items, int $page, int $per_page, bool $has_more, string $scope ): array {
        return array(
            'items'          => array_values( $items ),
            'page'           => $page,
            'perPage'        => $per_page,
            'hasMore'        => $has_more,
            'scope'          => $scope,
            'generatedAtUtc' => gmdate( 'c' ),
        );
    }
}
