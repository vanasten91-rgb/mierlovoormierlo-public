<?php

namespace MVM\Hub\Modules\Newsroom\Read;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Bounded read models for the first Newsroom module screens.
 *
 * Generic list payloads deliberately exclude signal contact data, summaries,
 * source URLs and source private notes. Those belong in later need-to-know
 * detail endpoints with stronger object-access rules.
 */
final class Newsroom_Read_Model {
    private \wpdb $wpdb;

    /** @var array<string,bool> */
    private array $table_exists = array();

    public function __construct( ?\wpdb $database = null ) {
        global $wpdb;
        $this->wpdb = $database ?? $wpdb;
    }

    /** @return array<string,mixed> */
    public function sources( int $page = 1, int $per_page = 20, string $status = '' ): array {
        $table = $this->table( 'sources' );
        if ( ! $this->table_exists( $table ) ) {
            return $this->empty_page( $page, $per_page );
        }

        $page     = $this->page( $page );
        $per_page = $this->per_page( $per_page );
        $offset   = ( $page - 1 ) * $per_page;
        $limit    = $per_page + 1;
        $params   = array();
        $where    = '1=1';

        $status = sanitize_key( $status );
        if ( '' !== $status ) {
            $where   .= ' AND s.status = %s';
            $params[] = $status;
        }

        $params[] = $limit;
        $params[] = $offset;

        $sql = "SELECT s.source_post_id,s.monitor_enabled,s.category,s.frequency,s.status,s.last_checked_utc,s.next_check_utc,s.updated_at_utc,p.post_title
            FROM {$table} s
            LEFT JOIN {$this->wpdb->posts} p ON p.ID = s.source_post_id
            WHERE {$where}
            ORDER BY s.source_post_id DESC
            LIMIT %d OFFSET %d";

        $rows = $this->get_results( $sql, $params );
        $has_more = count( $rows ) > $per_page;
        $rows = array_slice( $rows, 0, $per_page );

        $items = array_map(
            static fn( array $row ): array => array(
                'sourcePostId'   => (int) $row['source_post_id'],
                'title'          => (string) ( $row['post_title'] ?? '' ),
                'monitorEnabled' => 1 === (int) $row['monitor_enabled'],
                'category'       => sanitize_key( (string) $row['category'] ),
                'frequency'      => sanitize_key( (string) $row['frequency'] ),
                'status'         => sanitize_key( (string) $row['status'] ),
                'lastCheckedUtc' => $row['last_checked_utc'] ?: null,
                'nextCheckUtc'   => $row['next_check_utc'] ?: null,
                'updatedAtUtc'   => (string) $row['updated_at_utc'],
            ),
            $rows
        );

        return $this->page_payload( $items, $page, $per_page, $has_more );
    }

    /** @return array<string,mixed> */
    public function radar( int $page = 1, int $per_page = 20, string $status = '' ): array {
        $table = $this->table( 'signals' );
        if ( ! $this->table_exists( $table ) ) {
            return $this->empty_page( $page, $per_page );
        }

        $page     = $this->page( $page );
        $per_page = $this->per_page( $per_page );
        $offset   = ( $page - 1 ) * $per_page;
        $limit    = $per_page + 1;
        $params   = array();
        $where    = '1=1';

        $status = sanitize_key( $status );
        if ( '' !== $status ) {
            $where   .= ' AND status = %s';
            $params[] = $status;
        }

        $params[] = $limit;
        $params[] = $offset;

        $sql = "SELECT id,kind,status,priority,title,verification_status,incident_status,assignee_user_id,dossier_id,assignment_id,created_at_utc,updated_at_utc
            FROM {$table}
            WHERE {$where}
            ORDER BY id DESC
            LIMIT %d OFFSET %d";

        $rows = $this->get_results( $sql, $params );
        $has_more = count( $rows ) > $per_page;
        $rows = array_slice( $rows, 0, $per_page );

        $items = array_map(
            static fn( array $row ): array => array(
                'id'                 => (int) $row['id'],
                'kind'               => sanitize_key( (string) $row['kind'] ),
                'status'             => sanitize_key( (string) $row['status'] ),
                'priority'           => (int) $row['priority'],
                'title'              => (string) $row['title'],
                'verificationStatus' => sanitize_key( (string) $row['verification_status'] ),
                'incidentStatus'     => sanitize_key( (string) $row['incident_status'] ),
                'assigneeUserId'     => (int) $row['assignee_user_id'],
                'dossierId'          => (int) $row['dossier_id'],
                'assignmentId'       => (int) $row['assignment_id'],
                'createdAtUtc'       => (string) $row['created_at_utc'],
                'updatedAtUtc'       => (string) $row['updated_at_utc'],
            ),
            $rows
        );

        return $this->page_payload( $items, $page, $per_page, $has_more );
    }

    /** @return array<string,mixed> */
    public function agenda( int $page = 1, int $per_page = 20, string $status = '' ): array {
        $table = $this->table( 'editorial_calendar' );
        if ( ! $this->table_exists( $table ) ) {
            return $this->empty_page( $page, $per_page );
        }

        $page     = $this->page( $page );
        $per_page = $this->per_page( $per_page );
        $offset   = ( $page - 1 ) * $per_page;
        $limit    = $per_page + 1;
        $params   = array( $this->past_utc( DAY_IN_SECONDS ), $this->future_utc( 30 * DAY_IN_SECONDS ) );
        $where    = 'starts_at_utc >= %s AND starts_at_utc <= %s';

        $status = sanitize_key( $status );
        if ( '' !== $status ) {
            $where   .= ' AND status = %s';
            $params[] = $status;
        }

        $params[] = $limit;
        $params[] = $offset;

        $sql = "SELECT id,kind,status,title,starts_at_utc,ends_at_utc,dossier_id,post_id,assignment_id,owner_user_id,updated_at_utc
            FROM {$table}
            WHERE {$where}
            ORDER BY starts_at_utc ASC,id ASC
            LIMIT %d OFFSET %d";

        $rows = $this->get_results( $sql, $params );
        $has_more = count( $rows ) > $per_page;
        $rows = array_slice( $rows, 0, $per_page );

        $items = array_map(
            static fn( array $row ): array => array(
                'id'           => (int) $row['id'],
                'kind'         => sanitize_key( (string) $row['kind'] ),
                'status'       => sanitize_key( (string) $row['status'] ),
                'title'        => (string) $row['title'],
                'startsAtUtc'  => (string) $row['starts_at_utc'],
                'endsAtUtc'    => $row['ends_at_utc'] ?: null,
                'dossierId'    => (int) $row['dossier_id'],
                'postId'       => (int) $row['post_id'],
                'assignmentId' => (int) $row['assignment_id'],
                'ownerUserId'  => (int) $row['owner_user_id'],
                'updatedAtUtc' => (string) $row['updated_at_utc'],
            ),
            $rows
        );

        return $this->page_payload( $items, $page, $per_page, $has_more );
    }

    private function page( int $page ): int {
        return max( 1, min( 100, $page ) );
    }

    private function per_page( int $per_page ): int {
        return max( 1, min( 50, $per_page ) );
    }

    /** @param array<int,array<string,mixed>> $items */
    private function page_payload( array $items, int $page, int $per_page, bool $has_more ): array {
        return array(
            'items'      => array_values( $items ),
            'page'       => $page,
            'perPage'    => $per_page,
            'hasMore'    => $has_more,
            'generatedAtUtc' => gmdate( 'c' ),
        );
    }

    private function empty_page( int $page, int $per_page ): array {
        return $this->page_payload( array(), $this->page( $page ), $this->per_page( $per_page ), false );
    }

    private function table( string $suffix ): string {
        $allowed = array( 'sources', 'signals', 'editorial_calendar' );
        if ( ! in_array( $suffix, $allowed, true ) ) {
            throw new \InvalidArgumentException( 'Unknown Newsroom read table.' );
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

    /** @param array<int,mixed> $params */
    private function get_results( string $sql, array $params ): array {
        $prepared = $this->wpdb->prepare( $sql, ...$params );
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- internally constructed SQL with prepared placeholders.
        $rows = $this->wpdb->get_results( $prepared, ARRAY_A );
        return is_array( $rows ) ? array_values( $rows ) : array();
    }

    private function past_utc( int $seconds ): string {
        return gmdate( 'Y-m-d H:i:s', time() - $seconds );
    }

    private function future_utc( int $seconds ): string {
        return gmdate( 'Y-m-d H:i:s', time() + $seconds );
    }
}
