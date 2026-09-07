<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/class-newsradar-source-adoption.php';

/**
 * Owns the production Nieuwsradar schedule previously supplied by snippets.
 * The legacy crawler remains the fetch engine. Hub 4 selects one due source at
 * a time, honours each source frequency and also exposes a bounded manual queue.
 */
final class MvM_Hub4_Newsradar_Schedule {
    public const HOOK = 'mvm_newsradar_staggered_v2';
    public const MANUAL_HOOK = 'mvm_newsradar_manual_source_v1';
    private const OLD_HOOK = 'mvm_newsradar_fixed_slot_v1';
    private const LOCK_OPTION = 'mvm_newsradar_stagger_lock_v2';
    private const MAX_MANUAL_ATTEMPTS = 6;
    private static int $managed_legacy_source_id = 0;

    public static function init(): void {
        MvM_Hub4_Newsradar_Source_Adoption::init();
        add_filter( 'cron_schedules', array( __CLASS__, 'cron_schedules' ) );
        add_filter( 'option_mvm_bh_sources', array( __CLASS__, 'filter_sources' ), 20 );
        add_filter( 'option_mvm_bh_news_radar_settings', array( __CLASS__, 'gate_legacy_cron' ), 30 );
        add_action( 'init', array( __CLASS__, 'ensure_schedule' ), 50 );
        add_action( self::HOOK, array( __CLASS__, 'run_due_source' ) );
        add_action( self::MANUAL_HOOK, array( __CLASS__, 'run_manual_source' ), 10, 2 );
    }

    public static function deactivate(): void {
        wp_clear_scheduled_hook( self::HOOK );
        wp_clear_scheduled_hook( self::MANUAL_HOOK );
        wp_clear_scheduled_hook( self::OLD_HOOK, array( 'morning' ) );
        wp_clear_scheduled_hook( self::OLD_HOOK, array( 'evening' ) );
        delete_option( self::LOCK_OPTION );
    }

    /** @param array<string,array<string,int|string>> $schedules */
    public static function cron_schedules( array $schedules ): array {
        $schedules['mvm_five_minutes'] = array(
            'interval' => 5 * MINUTE_IN_SECONDS,
            'display'  => 'Elke 5 minuten (MvM Nieuwsradar)',
        );
        return $schedules;
    }

    /** @param mixed $sources */
    public static function filter_sources( $sources ) {
        if ( ! is_array( $sources ) ) {
            return $sources;
        }

        if ( self::$managed_legacy_source_id > 0 && doing_action( 'mvm_bh_news_radar_cron' ) ) {
            foreach ( $sources as $source ) {
                if ( is_array( $source ) && absint( $source['id'] ?? 0 ) === self::$managed_legacy_source_id ) {
                    // A manual one-shot check is allowed for a paused source without
                    // persisting a resume into the legacy source registry.
                    $source['active']   = true;
                    $source['excluded'] = false;
                    return array( $source );
                }
            }
            return array();
        }

        $rank = array( 'A' => 0, 'B' => 1, 'C' => 2 );
        usort(
            $sources,
            static function ( $a, $b ) use ( $rank ): int {
                $priority_a = is_array( $a ) ? (string) ( $a['priority'] ?? '' ) : '';
                $priority_b = is_array( $b ) ? (string) ( $b['priority'] ?? '' ) : '';
                $pa = strtoupper( sanitize_text_field( $priority_a ) );
                $pb = strtoupper( sanitize_text_field( $priority_b ) );
                $ra = $rank[ $pa ] ?? 99;
                $rb = $rank[ $pb ] ?? 99;
                if ( $ra !== $rb ) {
                    return $ra <=> $rb;
                }
                $name_a = is_array( $a ) ? (string) ( $a['name'] ?? '' ) : '';
                $name_b = is_array( $b ) ? (string) ( $b['name'] ?? '' ) : '';
                $na = sanitize_text_field( $name_a );
                $nb = sanitize_text_field( $name_b );
                return strnatcasecmp( $na, $nb );
            }
        );

        return $sources;
    }

    /** @param mixed $settings */
    public static function gate_legacy_cron( $settings ) {
        if ( ! is_array( $settings ) || ! doing_action( 'mvm_bh_news_radar_cron' ) ) {
            return $settings;
        }

        if ( self::$managed_legacy_source_id < 1 ) {
            $settings['enabled'] = 0;
            return $settings;
        }

        $settings['enabled']      = 1;
        $settings['source_batch'] = 1;
        return $settings;
    }

    public static function ensure_schedule(): void {
        wp_clear_scheduled_hook( self::OLD_HOOK, array( 'morning' ) );
        wp_clear_scheduled_hook( self::OLD_HOOK, array( 'evening' ) );

        if ( ! wp_next_scheduled( self::HOOK ) ) {
            wp_schedule_event( time() + MINUTE_IN_SECONDS, 'mvm_five_minutes', self::HOOK );
        }
    }

    public static function run_due_source(): void {
        $due = self::next_due_source();
        if ( ! $due ) {
            return;
        }

        self::run_source_now( absint( $due['source_post_id'] ?? 0 ), false );
    }

    /**
     * Queue one or more sources for a manual check without launching them in parallel.
     * Paused sources are allowed for one-shot manual checks; stopped sources are not.
     *
     * @param array<int,int|string> $ids
     * @return array{queued:array<int,int>,failed:array<int,int>}
     */
    public static function queue_manual_sources( array $ids ): array {
        $ids = array_slice( array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) ), 0, 50 );
        $queued = array();
        $failed = array();
        $offset = 1;

        foreach ( $ids as $source_id ) {
            $state = MvM_Hub4_Sources::get_state( $source_id );
            if ( ! is_array( $state ) || 'stopped' === (string) ( $state['status'] ?? '' ) || self::legacy_id_for_source( $source_id ) < 1 ) {
                $failed[] = $source_id;
                continue;
            }

            $args = array( $source_id, 0 );
            if ( ! wp_next_scheduled( self::MANUAL_HOOK, $args ) ) {
                wp_schedule_single_event( time() + $offset, self::MANUAL_HOOK, $args );
            }
            $queued[] = $source_id;
            $offset += 20;
        }

        return array( 'queued' => $queued, 'failed' => $failed );
    }

    public static function run_manual_source( int $source_post_id, int $attempt = 0 ): void {
        $result = self::run_source_now( absint( $source_post_id ), true );
        if ( is_wp_error( $result ) && 'mvm_newsradar_busy' === $result->get_error_code() && $attempt < self::MAX_MANUAL_ATTEMPTS ) {
            wp_schedule_single_event( time() + 60, self::MANUAL_HOOK, array( absint( $source_post_id ), $attempt + 1 ) );
        }
    }

    /**
     * Run exactly one canonical source through the existing legacy fetch engine.
     *
     * @return array{ok:bool,source_post_id:int,legacy_id:int,checked_ts:int}|WP_Error
     */
    public static function run_source_now( int $source_post_id, bool $manual = true ): array|WP_Error {
        $source_post_id = absint( $source_post_id );
        $state = MvM_Hub4_Sources::get_state( $source_post_id );
        if ( ! $source_post_id || ! is_array( $state ) ) {
            return new WP_Error( 'mvm_newsradar_source_missing', 'De bron bestaat niet in het canonieke bronregister.' );
        }
        if ( 'stopped' === (string) ( $state['status'] ?? '' ) ) {
            return new WP_Error( 'mvm_newsradar_source_stopped', 'Deze bron staat op Gestopt. Kies eerst Doorgaan.' );
        }
        if ( ! $manual && ( empty( $state['monitor_enabled'] ) || 'active' !== (string) ( $state['status'] ?? '' ) ) ) {
            return new WP_Error( 'mvm_newsradar_source_not_due', 'Deze bron staat niet aan voor automatische monitoring.' );
        }
        if ( ! self::acquire_lock() ) {
            return new WP_Error( 'mvm_newsradar_busy', 'Nieuwsradar is al met een andere bron bezig.' );
        }

        try {
            if ( class_exists( 'MvM_Hub4_Legacy_Newsradar_Writes' ) && MvM_Hub4_Legacy_Newsradar_Writes::crawler_busy() ) {
                return new WP_Error( 'mvm_newsradar_busy', 'Nieuwsradar is al met een andere bron bezig.' );
            }

            $legacy_id = self::legacy_id_for_source( $source_post_id );
            if ( $legacy_id < 1 ) {
                return new WP_Error( 'mvm_newsradar_legacy_source_missing', 'Deze bron heeft geen geldige Nieuwsradar-koppeling.' );
            }

            $before = self::legacy_checked_timestamp( $legacy_id );
            self::$managed_legacy_source_id = $legacy_id;
            do_action( 'mvm_bh_news_radar_cron' );
            self::$managed_legacy_source_id = 0;
            $after = self::legacy_checked_timestamp( $legacy_id );

            if ( $after <= $before ) {
                return new WP_Error( 'mvm_newsradar_source_not_reached', 'De broncontrole is gestart maar de bron is niet als bereikt bevestigd.' );
            }

            self::advance_source_state( $source_post_id, $after );
            return array(
                'ok'             => true,
                'source_post_id' => $source_post_id,
                'legacy_id'      => $legacy_id,
                'checked_ts'     => $after,
            );
        } finally {
            self::$managed_legacy_source_id = 0;
            self::release_lock();
        }
    }

    /** @return array{source_post_id:int,legacy_id:int}|null */
    private static function next_due_source(): ?array {
        global $wpdb;

        $table = MvM_Hub4_Sources::table_name();
        $meta  = $wpdb->postmeta;
        $now   = current_time( 'mysql', true );
        $key   = MvM_Hub4_Newsradar_Source_Adoption::LEGACY_ID_META;
        $priority_key = '_mvm_newsradar_priority';

        $sql = $wpdb->prepare(
            "SELECT s.source_post_id, CAST(pm.meta_value AS UNSIGNED) AS legacy_id
             FROM {$table} s
             INNER JOIN {$meta} pm ON pm.post_id = s.source_post_id AND pm.meta_key = %s
             LEFT JOIN {$meta} pr ON pr.post_id = s.source_post_id AND pr.meta_key = %s
             WHERE s.monitor_enabled = 1
               AND s.status = 'active'
               AND s.next_check_utc IS NOT NULL
               AND s.next_check_utc <= %s
             ORDER BY
               CASE UPPER(COALESCE(pr.meta_value, '')) WHEN 'A' THEN 0 WHEN 'B' THEN 1 WHEN 'C' THEN 2 ELSE 3 END ASC,
               s.next_check_utc ASC,
               s.source_post_id ASC
             LIMIT 1",
            $key,
            $priority_key,
            $now
        );
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- all values prepared above.
        $row = $wpdb->get_row( $sql, ARRAY_A );
        if ( ! is_array( $row ) ) {
            return null;
        }

        return array(
            'source_post_id' => absint( $row['source_post_id'] ?? 0 ),
            'legacy_id'      => absint( $row['legacy_id'] ?? 0 ),
        );
    }

    private static function legacy_id_for_source( int $source_post_id ): int {
        return absint( get_post_meta( absint( $source_post_id ), MvM_Hub4_Newsradar_Source_Adoption::LEGACY_ID_META, true ) );
    }

    private static function legacy_checked_timestamp( int $legacy_id ): int {
        $state = get_option( 'mvm_bh_news_source_state', array() );
        if ( ! is_array( $state ) || empty( $state[ $legacy_id ] ) || ! is_array( $state[ $legacy_id ] ) ) {
            return 0;
        }
        return absint( $state[ $legacy_id ]['checked_ts'] ?? 0 );
    }

    private static function advance_source_state( int $post_id, int $checked_ts ): void {
        global $wpdb;

        $state = MvM_Hub4_Sources::get_state( $post_id );
        if ( ! $state ) {
            return;
        }

        $frequency = MvM_Hub4_Sources::sanitize_frequency( (string) ( $state['frequency'] ?? 'weekly' ) );
        $next = MvM_Hub4_Sources::next_check_for_frequency( $frequency, max( time(), $checked_ts ) );

        // Paused sources may be checked manually but remain paused afterwards.
        if ( empty( $state['monitor_enabled'] ) || 'active' !== (string) ( $state['status'] ?? '' ) ) {
            $next = null;
        }

        $wpdb->update(
            MvM_Hub4_Sources::table_name(),
            array(
                'last_checked_utc' => gmdate( 'Y-m-d H:i:s', $checked_ts ),
                'last_checked_by'  => 0,
                'next_check_utc'   => $next,
                'updated_at_utc'   => current_time( 'mysql', true ),
            ),
            array( 'source_post_id' => $post_id ),
            array( '%s', '%d', '%s', '%s' ),
            array( '%d' )
        );
    }

    private static function acquire_lock(): bool {
        $now = time();
        if ( add_option( self::LOCK_OPTION, $now + ( 4 * MINUTE_IN_SECONDS ), '', false ) ) {
            return true;
        }

        $expires = (int) get_option( self::LOCK_OPTION, 0 );
        if ( $expires > 0 && $expires < $now ) {
            delete_option( self::LOCK_OPTION );
            return add_option( self::LOCK_OPTION, $now + ( 4 * MINUTE_IN_SECONDS ), '', false );
        }
        return false;
    }

    private static function release_lock(): void {
        delete_option( self::LOCK_OPTION );
    }
}
