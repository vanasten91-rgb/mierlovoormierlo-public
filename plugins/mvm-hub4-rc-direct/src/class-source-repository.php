<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class MvM_Hub4_Source_Repository {
    public static function query( array $args = array() ): array {
        global $wpdb;

        $defaults = array(
            'page'               => 1,
            'per_page'           => 50,
            'search'             => '',
            'due'                => '',
            'category'           => '',
            'frequency'          => '',
            'status'             => '',
            'configured'         => '',
            'include_superseded' => false,
        );
        $args = wp_parse_args( $args, $defaults );

        $page     = max( 1, absint( $args['page'] ) );
        $per_page = min( 100, max( 1, absint( $args['per_page'] ) ) );
        $offset   = ( $page - 1 ) * $per_page;
        $state    = MvM_Hub4_Sources::table_name();
        $posts    = $wpdb->posts;
        $postmeta = $wpdb->postmeta;
        $where    = array(
            "p.post_type = 'mvm_bron'",
            "p.post_status = 'publish'",
        );
        if ( empty( $args['include_superseded'] ) ) {
            $where[] = "NOT EXISTS (
                SELECT 1 FROM {$postmeta} sup
                WHERE sup.post_id = p.ID
                  AND sup.meta_key = '_mvm_newsradar_superseded_by'
                  AND CAST(sup.meta_value AS UNSIGNED) > 0
            )";
        }
        $params = array();

        $search = trim( (string) $args['search'] );
        if ( '' !== $search ) {
            $like     = '%' . $wpdb->esc_like( $search ) . '%';
            $where[]  = '(p.post_title LIKE %s OR p.post_content LIKE %s)';
            $params[] = $like;
            $params[] = $like;
        }

        $category = sanitize_key( (string) $args['category'] );
        if ( '' !== $category && array_key_exists( $category, MvM_Hub4_Sources::categories() ) ) {
            $where[]  = 's.category = %s';
            $params[] = $category;
        }

        $frequency = sanitize_key( (string) $args['frequency'] );
        if ( '' !== $frequency && array_key_exists( $frequency, MvM_Hub4_Sources::frequencies() ) ) {
            $where[]  = 's.frequency = %s';
            $params[] = $frequency;
        }

        $status = sanitize_key( (string) $args['status'] );
        if ( '' !== $status && array_key_exists( $status, MvM_Hub4_Sources::statuses() ) ) {
            $where[]  = 's.status = %s';
            $params[] = $status;
        }

        $configured = sanitize_key( (string) $args['configured'] );
        if ( 'yes' === $configured ) {
            $where[] = 's.source_post_id IS NOT NULL';
        } elseif ( 'no' === $configured ) {
            $where[] = 's.source_post_id IS NULL';
        }

        $due = sanitize_key( (string) $args['due'] );
        if ( '' !== $due ) {
            $range = self::due_range_utc();
            if ( 'overdue' === $due ) {
                $where[]  = 's.monitor_enabled = 1 AND s.status = \'active\' AND s.next_check_utc IS NOT NULL AND s.next_check_utc < %s';
                $params[] = $range['now'];
            } elseif ( 'today' === $due ) {
                $where[]  = 's.monitor_enabled = 1 AND s.status = \'active\' AND s.next_check_utc >= %s AND s.next_check_utc < %s';
                $params[] = $range['today_start'];
                $params[] = $range['tomorrow_start'];
            } elseif ( 'week' === $due ) {
                $where[]  = 's.monitor_enabled = 1 AND s.status = \'active\' AND s.next_check_utc >= %s AND s.next_check_utc < %s';
                $params[] = $range['tomorrow_start'];
                $params[] = $range['week_end'];
            } elseif ( 'later' === $due ) {
                $where[]  = 's.monitor_enabled = 1 AND s.status = \'active\' AND s.next_check_utc >= %s';
                $params[] = $range['week_end'];
            } elseif ( 'unscheduled' === $due ) {
                $where[] = 's.monitor_enabled = 1 AND s.status = \'active\' AND s.next_check_utc IS NULL';
            }
        }

        $where_sql = implode( ' AND ', $where );
        $from_sql  = "FROM {$posts} p LEFT JOIN {$state} s ON s.source_post_id = p.ID";

        $count_sql = "SELECT COUNT(*) {$from_sql} WHERE {$where_sql}";
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- SQL structure is fixed; values are prepared below.
        $total = (int) $wpdb->get_var( $params ? $wpdb->prepare( $count_sql, ...$params ) : $count_sql );

        // Correlated single-value meta lookups avoid duplicate rows and remain valid
        // under ONLY_FULL_GROUP_BY even if historical postmeta accidentally contains
        // duplicate keys. No permissive GROUP BY behavior is required.
        $priority_expr = "COALESCE((SELECT pm.meta_value FROM {$postmeta} pm WHERE pm.post_id = p.ID AND pm.meta_key = '_mvm_newsradar_priority' ORDER BY pm.meta_id DESC LIMIT 1), '')";
        $policy_expr   = "COALESCE((SELECT pm.meta_value FROM {$postmeta} pm WHERE pm.post_id = p.ID AND pm.meta_key = '_mvm_newsradar_policy_reason' ORDER BY pm.meta_id DESC LIMIT 1), '')";
        $legacy_expr   = "COALESCE((SELECT pm.meta_value FROM {$postmeta} pm WHERE pm.post_id = p.ID AND pm.meta_key = '_mvm_newsradar_legacy_source_id' ORDER BY pm.meta_id DESC LIMIT 1), '')";
        $sup_expr      = "COALESCE((SELECT pm.meta_value FROM {$postmeta} pm WHERE pm.post_id = p.ID AND pm.meta_key = '_mvm_newsradar_superseded_by' ORDER BY pm.meta_id DESC LIMIT 1), '')";

        $select_sql = "SELECT
            p.ID,
            p.post_title,
            p.post_content,
            s.monitor_enabled,
            s.category,
            s.frequency,
            s.status,
            s.last_checked_utc,
            s.last_checked_by,
            s.next_check_utc,
            s.updated_at_utc,
            {$priority_expr} AS source_priority,
            {$policy_expr} AS policy_reason,
            {$legacy_expr} AS legacy_source_id,
            {$sup_expr} AS superseded_by
            {$from_sql}
            WHERE {$where_sql}
            ORDER BY
                CASE UPPER({$priority_expr}) WHEN 'A' THEN 0 WHEN 'B' THEN 1 WHEN 'C' THEN 2 ELSE 3 END ASC,
                CASE WHEN s.monitor_enabled = 1 AND s.status = 'active' AND s.next_check_utc IS NOT NULL THEN 0 ELSE 1 END ASC,
                s.next_check_utc ASC,
                p.post_title ASC
            LIMIT %d OFFSET %d";

        $select_params   = $params;
        $select_params[] = $per_page;
        $select_params[] = $offset;
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- SQL structure is fixed and all values are prepared.
        $rows = $wpdb->get_results( $wpdb->prepare( $select_sql, ...$select_params ), ARRAY_A );
        $rows = is_array( $rows ) ? $rows : array();

        $user_ids = array_values(
            array_unique(
                array_filter(
                    array_map(
                        static fn( array $row ): int => absint( $row['last_checked_by'] ?? 0 ),
                        $rows
                    )
                )
            )
        );
        $users = array();
        if ( $user_ids ) {
            foreach ( get_users( array( 'include' => $user_ids, 'fields' => array( 'ID', 'display_name' ) ) ) as $user ) {
                $users[ (int) $user->ID ] = (string) $user->display_name;
            }
        }

        $items = array_map(
            static function ( array $row ) use ( $users ): array {
                $source_id  = absint( $row['ID'] );
                $category   = MvM_Hub4_Sources::sanitize_category( (string) ( $row['category'] ?: 'overig' ) );
                $frequency  = MvM_Hub4_Sources::sanitize_frequency( (string) ( $row['frequency'] ?: 'weekly' ) );
                $status     = MvM_Hub4_Sources::sanitize_status( (string) ( $row['status'] ?: 'active' ) );
                $checked_by = absint( $row['last_checked_by'] ?? 0 );
                $priority   = strtoupper( sanitize_text_field( (string) ( $row['source_priority'] ?? '' ) ) );
                if ( ! in_array( $priority, array( 'A', 'B', 'C' ), true ) ) {
                    $priority = '';
                }

                return array(
                    'id'               => $source_id,
                    'title'            => html_entity_decode( wp_strip_all_tags( (string) $row['post_title'] ), ENT_QUOTES, get_bloginfo( 'charset' ) ),
                    'hasExternalUrl'   => '' !== self::extract_external_url( (string) $row['post_content'] ),
                    'configured'       => null !== $row['monitor_enabled'],
                    'monitorEnabled'   => 1 === (int) $row['monitor_enabled'],
                    'category'         => $category,
                    'categoryLabel'    => MvM_Hub4_Sources::categories()[ $category ],
                    'frequency'        => $frequency,
                    'frequencyLabel'   => MvM_Hub4_Sources::frequencies()[ $frequency ]['label'],
                    'priority'         => $priority,
                    'policyReason'     => sanitize_text_field( (string) ( $row['policy_reason'] ?? '' ) ),
                    'legacySourceId'   => absint( $row['legacy_source_id'] ?? 0 ),
                    'supersededBy'     => absint( $row['superseded_by'] ?? 0 ),
                    'status'           => $status,
                    'statusLabel'      => MvM_Hub4_Sources::statuses()[ $status ],
                    'lastCheckedUtc'   => $row['last_checked_utc'] ?: null,
                    'lastCheckedBy'    => $checked_by,
                    'lastCheckedName'  => $checked_by && isset( $users[ $checked_by ] ) ? $users[ $checked_by ] : '',
                    'nextCheckUtc'     => $row['next_check_utc'] ?: null,
                    'dueState'         => self::due_state( $row['next_check_utc'] ?: null, 1 === (int) $row['monitor_enabled'], $status ),
                );
            },
            $rows
        );

        return array(
            'items'      => $items,
            'page'       => $page,
            'perPage'    => $per_page,
            'total'      => $total,
            'totalPages' => max( 1, (int) ceil( $total / $per_page ) ),
        );
    }

    public static function extract_external_url( string $content ): string {
        $text = html_entity_decode( wp_strip_all_tags( str_replace( array( '</p>', '<br>', '<br/>', '<br />' ), "\n", $content ) ), ENT_QUOTES, get_bloginfo( 'charset' ) );

        if ( preg_match( '/\bURL\s*:\s*(https?:\/\/[^\s<>"\']+)/iu', $text, $matches ) ) {
            return esc_url_raw( rtrim( $matches[1], '.,);]' ) );
        }

        if ( preg_match( '/https?:\/\/[^\s<>"\']+/iu', $text, $matches ) ) {
            return esc_url_raw( rtrim( $matches[0], '.,);]' ) );
        }

        return '';
    }

    private static function due_range_utc(): array {
        $timezone       = wp_timezone();
        $utc            = new DateTimeZone( 'UTC' );
        $now_local      = new DateTimeImmutable( 'now', $timezone );
        $today_start    = $now_local->setTime( 0, 0, 0 );
        $tomorrow_start = $today_start->modify( '+1 day' );
        $week_end       = $today_start->modify( '+8 days' );

        return array(
            'now'            => $now_local->setTimezone( $utc )->format( 'Y-m-d H:i:s' ),
            'today_start'    => $today_start->setTimezone( $utc )->format( 'Y-m-d H:i:s' ),
            'tomorrow_start' => $tomorrow_start->setTimezone( $utc )->format( 'Y-m-d H:i:s' ),
            'week_end'       => $week_end->setTimezone( $utc )->format( 'Y-m-d H:i:s' ),
        );
    }

    private static function due_state( ?string $next_check_utc, bool $enabled, string $status ): string {
        if ( ! $enabled || 'active' !== $status ) {
            return 'inactive';
        }
        if ( ! $next_check_utc ) {
            return 'unscheduled';
        }

        $range = self::due_range_utc();
        if ( $next_check_utc < $range['now'] ) {
            return 'overdue';
        }
        if ( $next_check_utc < $range['tomorrow_start'] ) {
            return 'today';
        }
        if ( $next_check_utc < $range['week_end'] ) {
            return 'week';
        }
        return 'later';
    }
}
