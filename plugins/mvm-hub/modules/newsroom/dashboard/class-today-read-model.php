<?php

namespace MVM\Hub\Modules\Newsroom\Dashboard;

use MVM\Hub\Core\Capabilities;
use MVM\Hub\Core\Object_Access;
use MVM\Hub\Core\Privacy;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Today_Read_Model {
    private \wpdb $wpdb;

    /** @var array<string,bool> */
    private array $table_exists = array();

    public function __construct( ?\wpdb $database = null ) {
        global $wpdb;
        $this->wpdb = $database ?? $wpdb;
    }

    /**
     * Return a small capability-aware snapshot for the Newsroom home screen.
     * No write, migration, external HTTP request or full-table payload is allowed.
     *
     * @return array<string,mixed>
     */
    public function snapshot(): array {
        $metrics = array();
        $items   = array( 'assignments' => array() );

        if ( Capabilities::can_read( Capabilities::ASSIGNMENTS_VIEW, 'mvm_hub4_assignment_view' ) ) {
            $assignment = $this->assignment_snapshot();
            $metrics    = array_merge( $metrics, $assignment['metrics'] );
            $items['assignments'] = $assignment['items'];
        }

        if ( Capabilities::can_access_newsroom() ) {
            $metrics['newsAwaitingAction'] = $this->news_awaiting_action();
        }

        if ( Capabilities::can_read( Capabilities::RADAR_VIEW, 'mvm_hub4_signal_view' ) ) {
            $metrics['signalsNeedingTriage'] = $this->count_where(
                'signals',
                "status IN ('new','triage')"
            );
        }

        if ( Capabilities::can_read( Capabilities::SOURCES_VIEW, 'mvm_hub4_sources_view' ) ) {
            $metrics['sourcesNeedingCheck'] = $this->count_where(
                'sources',
                "monitor_enabled = 1 AND status = 'active' AND (next_check_utc IS NULL OR next_check_utc <= %s)",
                array( $this->now_utc() )
            );
        }

        if ( Capabilities::can_read( Capabilities::AGENDA_VIEW, 'mvm_hub4_calendar_view' ) ) {
            $metrics['upcomingCalendar'] = $this->count_where(
                'editorial_calendar',
                "starts_at_utc >= %s AND starts_at_utc <= %s AND status NOT IN ('cancelled','published')",
                array( $this->now_utc(), $this->future_utc( 7 * DAY_IN_SECONDS ) )
            );
        }

        if ( Capabilities::can_read( Capabilities::MEDIA_VIEW, 'mvm_hub4_media_view' ) ) {
            $metrics['mediaNeedsAction'] = $this->count_where(
                'media_items',
                "status IN ('requested','submitted','review')"
            );
        }

        if ( Capabilities::can_read( Capabilities::CORRECTIONS_VIEW, 'mvm_hub4_correction_view' ) ) {
            $metrics['correctionsWaiting'] = $this->count_where(
                'corrections',
                "status NOT IN ('resolved','closed','rejected')"
            );
        }

        if ( Capabilities::can_read( Capabilities::DISTRIBUTION_VIEW, 'mvm_hub4_distribution_view' ) ) {
            $metrics['distributionNeedsAction'] = $this->count_where(
                'distribution_items',
                "status IN ('prepared','review','ready')"
            );
        }

        return array(
            'generatedAtUtc' => gmdate( 'c' ),
            'scope'          => Capabilities::can_manage_assignments() ? 'team' : 'own',
            'metrics'        => $metrics,
            'items'          => $items,
            'guidance'       => Next_Actions::from_metrics( $metrics ),
        );
    }

    /**
     * @return array{metrics:array<string,int>,items:array<int,array<string,mixed>>}
     */
    private function assignment_snapshot(): array {
        $table = $this->table( 'assignments' );
        if ( ! $this->table_exists( $table ) ) {
            return array(
                'metrics' => array(
                    'overdueAssignments' => 0,
                    'dueSoonAssignments' => 0,
                    'reviewAssignments'  => 0,
                ),
                'items' => array(),
            );
        }

        $now      = $this->now_utc();
        $tomorrow = $this->future_utc( DAY_IN_SECONDS );
        $params   = array( $now, $now, $tomorrow );
        $scope    = $this->assignment_scope_sql( $params );

        $sql = "SELECT
            SUM(CASE WHEN due_at_utc IS NOT NULL AND due_at_utc < %s AND status NOT IN ('published','cancelled') THEN 1 ELSE 0 END) AS overdue_count,
            SUM(CASE WHEN due_at_utc IS NOT NULL AND due_at_utc >= %s AND due_at_utc <= %s AND status NOT IN ('published','cancelled') THEN 1 ELSE 0 END) AS due_soon_count,
            SUM(CASE WHEN status = 'review' THEN 1 ELSE 0 END) AS review_count
            FROM {$table}
            WHERE {$scope}";

        $row = $this->get_row( $sql, $params );

        $item_params   = array();
        $item_scope    = $this->assignment_scope_sql( $item_params );
        $item_params[] = 8;
        $item_sql = "SELECT id,type,status,priority,title,source_post_id,event_post_id,news_post_id,assignee_user_id,created_by_user_id,due_at_utc,updated_at_utc
            FROM {$table}
            WHERE {$item_scope} AND status NOT IN ('published','cancelled')
            ORDER BY CASE WHEN due_at_utc IS NULL THEN 1 ELSE 0 END, due_at_utc ASC, priority ASC, id DESC
            LIMIT %d";

        $raw_items = $this->get_results( $item_sql, $item_params );
        $items     = array();

        foreach ( $raw_items as $raw ) {
            if ( ! Object_Access::can_read_assignment( $raw ) ) {
                continue;
            }

            $projected = Privacy::project_today_assignment( $raw );
            $items[] = array(
                'id'           => (int) ( $projected['id'] ?? 0 ),
                'type'         => sanitize_key( (string) ( $projected['type'] ?? '' ) ),
                'status'       => sanitize_key( (string) ( $projected['status'] ?? '' ) ),
                'priority'     => (int) ( $projected['priority'] ?? 0 ),
                'title'        => (string) ( $projected['title'] ?? '' ),
                'dueAtUtc'     => ! empty( $projected['due_at_utc'] ) ? (string) $projected['due_at_utc'] : null,
                'newsPostId'   => (int) ( $projected['news_post_id'] ?? 0 ),
                'eventPostId'  => (int) ( $projected['event_post_id'] ?? 0 ),
                'sourcePostId' => (int) ( $projected['source_post_id'] ?? 0 ),
                'updatedAtUtc' => (string) ( $projected['updated_at_utc'] ?? '' ),
            );
        }

        return array(
            'metrics' => array(
                'overdueAssignments' => (int) ( $row['overdue_count'] ?? 0 ),
                'dueSoonAssignments' => (int) ( $row['due_soon_count'] ?? 0 ),
                'reviewAssignments'  => (int) ( $row['review_count'] ?? 0 ),
            ),
            'items' => $items,
        );
    }

    private function news_awaiting_action(): int {
        $user_id         = get_current_user_id();
        $can_review_team = Capabilities::can_read( Capabilities::NEWS_REVIEW, 'mvm_hub4_news_review' );

        if ( $can_review_team ) {
            $sql = "SELECT COUNT(*) FROM {$this->wpdb->posts} WHERE post_type = %s AND post_status = %s";
            return (int) $this->get_var( $sql, array( 'post', 'pending' ) );
        }

        if ( $user_id <= 0 ) {
            return 0;
        }

        $sql = "SELECT COUNT(*) FROM {$this->wpdb->posts} WHERE post_type = %s AND post_author = %d AND post_status IN ('draft','pending')";
        return (int) $this->get_var( $sql, array( 'post', $user_id ) );
    }

    /**
     * @param array<int,mixed> $params
     */
    private function assignment_scope_sql( array &$params ): string {
        if ( Capabilities::can_manage_assignments() ) {
            return '1=1';
        }

        $user_id  = get_current_user_id();
        $params[] = $user_id;
        $params[] = $user_id;

        return '(assignee_user_id = %d OR created_by_user_id = %d)';
    }

    /**
     * @param array<int,mixed> $params
     */
    private function count_where( string $suffix, string $where, array $params = array() ): int {
        $table = $this->table( $suffix );
        if ( ! $this->table_exists( $table ) ) {
            return 0;
        }

        return (int) $this->get_var( "SELECT COUNT(*) FROM {$table} WHERE {$where}", $params );
    }

    private function table( string $suffix ): string {
        $allowed = array(
            'assignments',
            'signals',
            'sources',
            'editorial_calendar',
            'media_items',
            'corrections',
            'distribution_items',
        );

        if ( ! in_array( $suffix, $allowed, true ) ) {
            throw new \InvalidArgumentException( 'Unknown Newsroom read-model table.' );
        }

        return $this->wpdb->prefix . 'mvm_hub4_' . $suffix;
    }

    private function table_exists( string $table ): bool {
        if ( array_key_exists( $table, $this->table_exists ) ) {
            return $this->table_exists[ $table ];
        }

        $found = $this->wpdb->get_var(
            $this->wpdb->prepare( 'SHOW TABLES LIKE %s', $this->wpdb->esc_like( $table ) )
        );

        $this->table_exists[ $table ] = $table === $found;
        return $this->table_exists[ $table ];
    }

    /**
     * @param array<int,mixed> $params
     * @return array<string,mixed>
     */
    private function get_row( string $sql, array $params ): array {
        $prepared = $this->prepare( $sql, $params );
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- SQL is internally constructed and placeholders are prepared.
        $row = $this->wpdb->get_row( $prepared, ARRAY_A );
        return is_array( $row ) ? $row : array();
    }

    /**
     * @param array<int,mixed> $params
     * @return array<int,array<string,mixed>>
     */
    private function get_results( string $sql, array $params ): array {
        $prepared = $this->prepare( $sql, $params );
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- SQL is internally constructed and placeholders are prepared.
        $rows = $this->wpdb->get_results( $prepared, ARRAY_A );
        return is_array( $rows ) ? array_values( $rows ) : array();
    }

    /**
     * @param array<int,mixed> $params
     */
    private function get_var( string $sql, array $params = array() ): mixed {
        $prepared = $this->prepare( $sql, $params );
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- SQL is internally constructed and placeholders are prepared.
        return $this->wpdb->get_var( $prepared );
    }

    /**
     * @param array<int,mixed> $params
     */
    private function prepare( string $sql, array $params ): string {
        if ( array() === $params ) {
            return $sql;
        }

        return $this->wpdb->prepare( $sql, ...$params );
    }

    private function now_utc(): string {
        return current_time( 'mysql', true );
    }

    private function future_utc( int $seconds ): string {
        return gmdate( 'Y-m-d H:i:s', time() + $seconds );
    }
}
