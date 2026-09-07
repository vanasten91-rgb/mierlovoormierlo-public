<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * One-time, rollback-friendly normalization of canonical Nieuwsradar monitoring
 * sources. Historical stopped duplicate generations are retained and merely
 * marked as superseded; no source post or audit history is deleted.
 */
final class MvM_Hub4_Newsradar_Source_Policy_Reconcile {
    private const OPTION_SCHEMA = 'mvm_newsradar_source_policy_schema_v1';
    private const OPTION_BACKUP = 'mvm_newsradar_source_policy_backup_v1';
    private const SCHEMA_VERSION = 1;

    public static function init(): void {
        add_action( 'init', array( __CLASS__, 'maybe_reconcile' ), 65 );
    }

    public static function maybe_reconcile(): void {
        if ( self::SCHEMA_VERSION === (int) get_option( self::OPTION_SCHEMA, 0 ) ) {
            return;
        }
        if ( ! class_exists( 'MvM_Hub4_Sources' ) || ! class_exists( 'MvM_Hub4_Newsradar_Source_Policy' ) ) {
            return;
        }

        $result = self::reconcile();
        if ( is_wp_error( $result ) ) {
            if ( class_exists( 'MvM_Hub4_Audit' ) ) {
                MvM_Hub4_Audit::log(
                    'newsradar.source_policy_reconcile',
                    'error',
                    array( 'object_type' => 'source_policy', 'context' => array( 'message' => $result->get_error_message() ) )
                );
            }
            return;
        }

        update_option( self::OPTION_SCHEMA, self::SCHEMA_VERSION, false );
        if ( class_exists( 'MvM_Hub4_Audit' ) ) {
            MvM_Hub4_Audit::log(
                'newsradar.source_policy_reconcile',
                'success',
                array(
                    'object_type' => 'source_policy',
                    'context' => array(
                        'schema'            => self::SCHEMA_VERSION,
                        'active_sources'    => (int) $result['active_sources'],
                        'changed_sources'   => (int) $result['changed_sources'],
                        'superseded_marked' => (int) $result['superseded_marked'],
                    ),
                )
            );
        }
    }

    /** @return array{active_sources:int,changed_sources:int,superseded_marked:int}|WP_Error */
    public static function reconcile(): array|WP_Error {
        global $wpdb;

        $table = MvM_Hub4_Sources::table_name();
        $posts = $wpdb->posts;
        $meta  = $wpdb->postmeta;
        $legacy_key   = MvM_Hub4_Newsradar_Source_Adoption::LEGACY_ID_META;
        $priority_key = '_mvm_newsradar_priority';

        // Select exactly one legacy ID and priority per source. This remains
        // deterministic even if historical postmeta accidentally contains
        // duplicate keys.
        $sql = $wpdb->prepare(
            "SELECT p.ID, p.post_title, s.category, s.frequency, s.next_check_utc,
                    COALESCE((SELECT pm.meta_value FROM {$meta} pm WHERE pm.post_id = p.ID AND pm.meta_key = %s ORDER BY pm.meta_id DESC LIMIT 1), 'B') AS priority,
                    CAST(COALESCE((SELECT pm.meta_value FROM {$meta} pm WHERE pm.post_id = p.ID AND pm.meta_key = %s ORDER BY pm.meta_id DESC LIMIT 1), '0') AS UNSIGNED) AS legacy_id
             FROM {$posts} p
             INNER JOIN {$table} s ON s.source_post_id = p.ID
             WHERE p.post_type = 'mvm_bron'
               AND p.post_status = 'publish'
               AND s.monitor_enabled = 1
               AND s.status = 'active'
               AND EXISTS (SELECT 1 FROM {$meta} lm WHERE lm.post_id = p.ID AND lm.meta_key = %s)
             ORDER BY p.ID ASC",
            $priority_key,
            $legacy_key,
            $legacy_key
        );
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- identifiers are internal and keys are prepared.
        $rows = $wpdb->get_results( $sql, ARRAY_A );
        if ( ! is_array( $rows ) ) {
            return new WP_Error( 'mvm_newsradar_policy_query_failed', 'De actieve Nieuwsradar-bronnen konden niet worden gelezen.' );
        }

        $backup           = array();
        $plans            = array();
        $active_by_legacy = array();

        // Phase 1: calculate the complete plan and rollback image without writes.
        foreach ( $rows as $row ) {
            $source_id = absint( $row['ID'] ?? 0 );
            $legacy_id = absint( $row['legacy_id'] ?? 0 );
            if ( ! $source_id || ! $legacy_id ) {
                continue;
            }
            $active_by_legacy[ $legacy_id ] = $source_id;

            $current_priority  = strtoupper( sanitize_text_field( (string) ( $row['priority'] ?? 'B' ) ) );
            $current_frequency = MvM_Hub4_Sources::sanitize_frequency( (string) ( $row['frequency'] ?? 'weekly' ) );
            $recommended       = MvM_Hub4_Newsradar_Source_Policy::recommend(
                (string) ( $row['post_title'] ?? '' ),
                (string) ( $row['category'] ?? 'overig' ),
                $current_priority
            );

            $backup[ $source_id ] = array(
                'title'          => sanitize_text_field( (string) ( $row['post_title'] ?? '' ) ),
                'legacy_id'      => $legacy_id,
                'priority'       => $current_priority,
                'frequency'      => $current_frequency,
                'next_check_utc' => sanitize_text_field( (string) ( $row['next_check_utc'] ?? '' ) ),
            );
            $plans[ $source_id ] = array(
                'current_priority'  => $current_priority,
                'current_frequency' => $current_frequency,
                'priority'          => (string) $recommended['priority'],
                'frequency'         => (string) $recommended['frequency'],
                'reason'            => sanitize_text_field( (string) $recommended['reason'] ),
            );
        }

        // Rollback must exist before the first policy mutation. A previously
        // persisted snapshot is never overwritten.
        if ( false === get_option( self::OPTION_BACKUP, false ) ) {
            $stored = update_option( self::OPTION_BACKUP, $backup, false );
            if ( false === $stored && false === get_option( self::OPTION_BACKUP, false ) ) {
                return new WP_Error( 'mvm_newsradar_policy_backup_failed', 'De rollback-snapshot kon niet worden opgeslagen; bronpolicy is niet gewijzigd.' );
            }
        }

        // Phase 2: only after the rollback image is durable may live source
        // policy fields be changed.
        $changed = 0;
        foreach ( $plans as $source_id => $plan ) {
            $needs_priority  = (string) $plan['current_priority'] !== (string) $plan['priority'];
            $needs_frequency = (string) $plan['current_frequency'] !== (string) $plan['frequency'];
            if ( ! $needs_priority && ! $needs_frequency ) {
                continue;
            }

            if ( $needs_frequency ) {
                $saved = MvM_Hub4_Sources::save_state( absint( $source_id ), array( 'frequency' => (string) $plan['frequency'] ) );
                if ( is_wp_error( $saved ) ) {
                    return $saved;
                }
            }
            if ( $needs_priority ) {
                $priority_updated = update_post_meta( absint( $source_id ), $priority_key, (string) $plan['priority'] );
                if ( false === $priority_updated ) {
                    return new WP_Error( 'mvm_newsradar_policy_priority_failed', 'De bronprioriteit kon niet veilig worden opgeslagen. Rollback-snapshot is beschikbaar.' );
                }
            }
            update_post_meta( absint( $source_id ), '_mvm_newsradar_policy_reason', (string) $plan['reason'] );
            $changed++;
        }

        $superseded = 0;
        if ( $active_by_legacy ) {
            foreach ( $active_by_legacy as $legacy_id => $active_id ) {
                $duplicate_ids = get_posts(
                    array(
                        'post_type'      => 'mvm_bron',
                        'post_status'    => 'publish',
                        'posts_per_page' => 20,
                        'fields'         => 'ids',
                        'post__not_in'   => array( $active_id ),
                        'meta_query'     => array(
                            array( 'key' => $legacy_key, 'value' => (string) $legacy_id, 'compare' => '=' ),
                        ),
                    )
                );
                foreach ( $duplicate_ids as $duplicate_id ) {
                    $duplicate_id = absint( $duplicate_id );
                    $state = MvM_Hub4_Sources::get_state( $duplicate_id );
                    if ( ! is_array( $state ) || 'stopped' !== (string) ( $state['status'] ?? '' ) || ! empty( $state['monitor_enabled'] ) ) {
                        continue;
                    }
                    if ( absint( get_post_meta( $duplicate_id, '_mvm_newsradar_superseded_by', true ) ) !== $active_id ) {
                        update_post_meta( $duplicate_id, '_mvm_newsradar_superseded_by', $active_id );
                        $superseded++;
                    }
                }
            }
        }

        return array(
            'active_sources'    => count( $rows ),
            'changed_sources'   => $changed,
            'superseded_marked' => $superseded,
        );
    }
}
