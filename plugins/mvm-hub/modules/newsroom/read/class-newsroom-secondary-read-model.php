<?php

namespace MVM\Hub\Modules\Newsroom\Read;

use MVM\Hub\Core\Capabilities;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Secondary bounded Newsroom list models.
 *
 * List payloads intentionally omit free-text briefs/messages/copy, direct
 * correction contacts, distribution target URLs and media location/credit text.
 */
final class Newsroom_Secondary_Read_Model {
    private \wpdb $wpdb;

    /** @var array<string,bool> */
    private array $table_exists = array();

    public function __construct( ?\wpdb $database = null ) {
        global $wpdb;
        $this->wpdb = $database ?? $wpdb;
    }

    /** @return array<string,mixed> */
    public function media( int $page = 1, int $per_page = 20, string $status = '' ): array {
        return $this->simple_list(
            'media_items',
            'id,status,kind,title,attachment_id,assignment_id,dossier_id,photographer_user_id,consent_status,created_at_utc,updated_at_utc',
            static fn( array $row ): array => array(
                'id'                 => (int) $row['id'],
                'status'             => sanitize_key( (string) $row['status'] ),
                'kind'               => sanitize_key( (string) $row['kind'] ),
                'title'              => (string) $row['title'],
                'attachmentId'       => (int) $row['attachment_id'],
                'assignmentId'       => (int) $row['assignment_id'],
                'dossierId'          => (int) $row['dossier_id'],
                'photographerUserId' => (int) $row['photographer_user_id'],
                'consentStatus'      => sanitize_key( (string) $row['consent_status'] ),
                'createdAtUtc'       => (string) $row['created_at_utc'],
                'updatedAtUtc'       => (string) $row['updated_at_utc'],
            ),
            $page,
            $per_page,
            $status
        );
    }

    /** @return array<string,mixed> */
    public function dossiers( int $page = 1, int $per_page = 20, string $status = '' ): array {
        $extra_where = Capabilities::can_read( Capabilities::DOSSIERS_MANAGE, 'mvm_hub4_dossier_manage' )
            ? ''
            : "visibility IN ('internal','public')";

        return $this->simple_list(
            'dossiers',
            'id,status,visibility,title,slug,category_term_id,lead_user_id,created_at_utc,updated_at_utc,published_at_utc',
            static fn( array $row ): array => array(
                'id'             => (int) $row['id'],
                'status'         => sanitize_key( (string) $row['status'] ),
                'visibility'     => sanitize_key( (string) $row['visibility'] ),
                'title'          => (string) $row['title'],
                'slug'           => sanitize_title( (string) $row['slug'] ),
                'categoryTermId' => (int) $row['category_term_id'],
                'leadUserId'     => (int) $row['lead_user_id'],
                'createdAtUtc'   => (string) $row['created_at_utc'],
                'updatedAtUtc'   => (string) $row['updated_at_utc'],
                'publishedAtUtc' => $row['published_at_utc'] ?: null,
            ),
            $page,
            $per_page,
            $status,
            $extra_where
        );
    }

    /** @return array<string,mixed> */
    public function corrections( int $page = 1, int $per_page = 20, string $status = '' ): array {
        return $this->simple_list(
            'corrections',
            'id,post_id,status,submitted_via,reviewer_user_id,created_at_utc,updated_at_utc,resolved_at_utc',
            static fn( array $row ): array => array(
                'id'             => (int) $row['id'],
                'postId'         => (int) $row['post_id'],
                'status'         => sanitize_key( (string) $row['status'] ),
                'submittedVia'   => sanitize_key( (string) $row['submitted_via'] ),
                'reviewerUserId' => (int) $row['reviewer_user_id'],
                'createdAtUtc'   => (string) $row['created_at_utc'],
                'updatedAtUtc'   => (string) $row['updated_at_utc'],
                'resolvedAtUtc'  => $row['resolved_at_utc'] ?: null,
            ),
            $page,
            $per_page,
            $status
        );
    }

    /** @return array<string,mixed> */
    public function distribution( int $page = 1, int $per_page = 20, string $status = '' ): array {
        return $this->simple_list(
            'distribution_items',
            'id,post_id,dossier_id,channel,status,prepared_by_user_id,approved_by_user_id,created_at_utc,updated_at_utc,sent_at_utc',
            static fn( array $row ): array => array(
                'id'               => (int) $row['id'],
                'postId'           => (int) $row['post_id'],
                'dossierId'        => (int) $row['dossier_id'],
                'channel'          => sanitize_key( (string) $row['channel'] ),
                'status'           => sanitize_key( (string) $row['status'] ),
                'preparedByUserId' => (int) $row['prepared_by_user_id'],
                'approvedByUserId' => (int) $row['approved_by_user_id'],
                'createdAtUtc'     => (string) $row['created_at_utc'],
                'updatedAtUtc'     => (string) $row['updated_at_utc'],
                'sentAtUtc'        => $row['sent_at_utc'] ?: null,
            ),
            $page,
            $per_page,
            $status
        );
    }

    /**
     * @param callable(array<string,mixed>):array<string,mixed> $project
     * @return array<string,mixed>
     */
    private function simple_list(
        string $suffix,
        string $columns,
        callable $project,
        int $page,
        int $per_page,
        string $status,
        string $extra_where = ''
    ): array {
        $table = $this->table( $suffix );
        $page = max( 1, min( 100, $page ) );
        $per_page = max( 1, min( 50, $per_page ) );

        if ( ! $this->table_exists( $table ) ) {
            return $this->payload( array(), $page, $per_page, false );
        }

        $params = array();
        $where = array();
        $status = sanitize_key( $status );

        if ( '' !== $status ) {
            $where[] = 'status = %s';
            $params[] = $status;
        }
        if ( '' !== $extra_where ) {
            $where[] = $extra_where;
        }

        $where_sql = array() === $where ? '1=1' : implode( ' AND ', $where );
        $params[] = $per_page + 1;
        $params[] = ( $page - 1 ) * $per_page;

        $sql = "SELECT {$columns} FROM {$table} WHERE {$where_sql} ORDER BY id DESC LIMIT %d OFFSET %d";
        $prepared = $this->wpdb->prepare( $sql, ...$params );
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- columns/table/where are internal allow-listed constants.
        $rows = $this->wpdb->get_results( $prepared, ARRAY_A );
        $rows = is_array( $rows ) ? array_values( $rows ) : array();
        $has_more = count( $rows ) > $per_page;
        $rows = array_slice( $rows, 0, $per_page );

        $items = array_map( $project, $rows );
        return $this->payload( $items, $page, $per_page, $has_more );
    }

    /** @param array<int,array<string,mixed>> $items */
    private function payload( array $items, int $page, int $per_page, bool $has_more ): array {
        return array(
            'items'          => array_values( $items ),
            'page'           => $page,
            'perPage'        => $per_page,
            'hasMore'        => $has_more,
            'generatedAtUtc' => gmdate( 'c' ),
        );
    }

    private function table( string $suffix ): string {
        $allowed = array( 'media_items', 'dossiers', 'corrections', 'distribution_items' );
        if ( ! in_array( $suffix, $allowed, true ) ) {
            throw new \InvalidArgumentException( 'Unknown secondary Newsroom read table.' );
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
}
