<?php

namespace MVM\Hub\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * State-preserving cron owner for the rebuilt Hub.
 *
 * The existing hook names are deliberately retained so pending cron timestamps
 * survive cutover. When takeover is enabled, every callback on the owned hooks
 * is replaced before the new callback is attached. This prevents duplicate
 * Hub4/snippet callbacks while preserving existing options/tables/queues.
 */
final class Cron_Ownership {
    public const NEWSRADAR_HOOK = 'mvm_newsradar_staggered_v2';
    public const NEWSRADAR_MANUAL_HOOK = 'mvm_newsradar_manual_source_v1';
    public const AI_AGENDA_HOOK = 'mvm_hub_ai_agenda_scan_v1';
    public const AUDIT_HOOK = 'mvm_hub4_audit_cleanup';

    private const NEWSRADAR_LOCK = 'mvm_hub_newsradar_lock_v1';
    private const NEWSRADAR_SCHEDULE = 'mvm_five_minutes';
    private const AI_SCHEDULE = 'mvm_ai_agenda_15m';
    private const AUDIT_RETENTION_OPTION = 'mvm_hub4_audit_retention_days';
    private const MAX_PENDING_AI = 25;

    private static bool $registered = false;
    private static int $managed_legacy_source_id = 0;

    public static function register(): void {
        if ( self::$registered ) {
            return;
        }
        self::$registered = true;

        add_filter( 'cron_schedules', array( self::class, 'cron_schedules' ) );
        add_filter( 'option_mvm_bh_sources', array( self::class, 'filter_legacy_sources' ), 90 );
        add_filter( 'option_mvm_bh_news_radar_settings', array( self::class, 'filter_legacy_radar_settings' ), 90 );
        add_action( 'rest_api_init', array( self::class, 'register_status_route' ) );

        if ( ! Runtime_Gates::cron_takeover_enabled() ) {
            return;
        }

        // These hooks are explicitly owned by the central Hub after the gate is
        // enabled. Remove every old callback first so one scheduled event can
        // never invoke both Hub4 and the rebuilt implementation.
        remove_all_actions( self::NEWSRADAR_HOOK );
        remove_all_actions( self::NEWSRADAR_MANUAL_HOOK );
        remove_all_actions( self::AI_AGENDA_HOOK );
        remove_all_actions( self::AUDIT_HOOK );

        add_action( self::NEWSRADAR_HOOK, array( self::class, 'run_due_newsradar_source' ) );
        add_action( self::NEWSRADAR_MANUAL_HOOK, array( self::class, 'run_manual_newsradar_source' ), 10, 2 );
        add_action( self::AI_AGENDA_HOOK, array( self::class, 'run_ai_agenda' ) );
        add_action( self::AUDIT_HOOK, array( self::class, 'cleanup_audit' ) );
        add_action( 'init', array( self::class, 'ensure_schedules' ), 999 );
    }

    /** @param array<string,array<string,int|string>> $schedules */
    public static function cron_schedules( array $schedules ): array {
        if ( ! isset( $schedules[ self::NEWSRADAR_SCHEDULE ] ) ) {
            $schedules[ self::NEWSRADAR_SCHEDULE ] = array(
                'interval' => 5 * MINUTE_IN_SECONDS,
                'display'  => 'Elke 5 minuten (MvM Nieuwsradar)',
            );
        }
        if ( ! isset( $schedules[ self::AI_SCHEDULE ] ) ) {
            $schedules[ self::AI_SCHEDULE ] = array(
                'interval' => 15 * MINUTE_IN_SECONDS,
                'display'  => 'MvM AI-agendacrawl iedere 15 minuten',
            );
        }
        return $schedules;
    }

    public static function ensure_schedules(): void {
        if ( ! Runtime_Gates::cron_takeover_enabled() ) {
            return;
        }
        if ( ! wp_next_scheduled( self::NEWSRADAR_HOOK ) ) {
            wp_schedule_event( time() + MINUTE_IN_SECONDS, self::NEWSRADAR_SCHEDULE, self::NEWSRADAR_HOOK );
        }
        $settings = self::ai_settings();
        if ( ! empty( $settings['enabled'] ) ) {
            if ( ! wp_next_scheduled( self::AI_AGENDA_HOOK ) ) {
                wp_schedule_event( time() + ( 2 * MINUTE_IN_SECONDS ), self::AI_SCHEDULE, self::AI_AGENDA_HOOK );
            }
        } elseif ( wp_next_scheduled( self::AI_AGENDA_HOOK ) ) {
            wp_clear_scheduled_hook( self::AI_AGENDA_HOOK );
        }
        if ( ! wp_next_scheduled( self::AUDIT_HOOK ) ) {
            wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::AUDIT_HOOK );
        }
    }

    public static function run_due_newsradar_source(): void {
        $row = self::next_due_source();
        if ( ! $row ) {
            return;
        }
        self::run_newsradar_source( absint( $row['source_post_id'] ?? 0 ), false, 0 );
    }

    public static function queue_manual_newsradar_source( int $source_post_id ): array|\WP_Error {
        if ( ! Runtime_Gates::cron_takeover_enabled() ) {
            return new \WP_Error( 'mvm_newsradar_takeover_off', 'Nieuwsradar-cron is nog niet door de nieuwe Hub overgenomen.' );
        }
        $state = self::source_state( $source_post_id );
        if ( ! $state || 'stopped' === (string) ( $state['status'] ?? '' ) ) {
            return new \WP_Error( 'mvm_newsradar_source_unavailable', 'Deze bron kan niet handmatig worden gecontroleerd.' );
        }
        if ( self::legacy_id_for_source( $source_post_id ) < 1 ) {
            return new \WP_Error( 'mvm_newsradar_legacy_missing', 'Deze bron heeft geen geldige Nieuwsradar-koppeling.' );
        }

        $args = array( $source_post_id, 0 );
        if ( ! wp_next_scheduled( self::NEWSRADAR_MANUAL_HOOK, $args ) ) {
            wp_schedule_single_event( time() + 1, self::NEWSRADAR_MANUAL_HOOK, $args );
        }
        Audit::record( 'newsradar.manual_queued', 'success', 'source', $source_post_id );
        return array( 'queued' => true, 'sourcePostId' => $source_post_id );
    }

    public static function run_manual_newsradar_source( int $source_post_id, int $attempt = 0 ): void {
        $result = self::run_newsradar_source( absint( $source_post_id ), true, get_current_user_id() );
        if ( is_wp_error( $result ) && 'mvm_newsradar_busy' === $result->get_error_code() && $attempt < 6 ) {
            wp_schedule_single_event( time() + 60, self::NEWSRADAR_MANUAL_HOOK, array( absint( $source_post_id ), $attempt + 1 ) );
        }
    }

    /** @return array<string,mixed>|\WP_Error */
    public static function run_newsradar_source( int $source_post_id, bool $manual, int $actor_user_id = 0 ): array|\WP_Error {
        if ( ! Runtime_Gates::cron_takeover_enabled() ) {
            return new \WP_Error( 'mvm_newsradar_takeover_off', 'Nieuwsradar-cron is nog niet door de nieuwe Hub overgenomen.' );
        }
        $state = self::source_state( $source_post_id );
        if ( ! $state ) {
            return new \WP_Error( 'mvm_newsradar_source_missing', 'Deze bron bestaat niet in het canonieke bronregister.' );
        }
        if ( 'stopped' === (string) ( $state['status'] ?? '' ) ) {
            return new \WP_Error( 'mvm_newsradar_source_stopped', 'Deze bron staat op Gestopt.' );
        }
        if ( ! $manual && ( empty( $state['monitor_enabled'] ) || 'active' !== (string) ( $state['status'] ?? '' ) ) ) {
            return new \WP_Error( 'mvm_newsradar_source_not_due', 'Deze bron staat niet aan voor automatische monitoring.' );
        }
        $legacy_id = self::legacy_id_for_source( $source_post_id );
        if ( $legacy_id < 1 ) {
            return new \WP_Error( 'mvm_newsradar_legacy_missing', 'Deze bron heeft geen geldige Nieuwsradar-koppeling.' );
        }
        $token = self::acquire_newsradar_lock();
        if ( is_wp_error( $token ) ) {
            return $token;
        }

        try {
            $before = self::legacy_checked_timestamp( $legacy_id );
            self::$managed_legacy_source_id = $legacy_id;
            do_action( 'mvm_bh_news_radar_cron' );
            self::$managed_legacy_source_id = 0;
            $after = self::legacy_checked_timestamp( $legacy_id );
            if ( $after <= $before ) {
                return new \WP_Error( 'mvm_newsradar_source_not_reached', 'De broncontrole werd niet als voltooid bevestigd.' );
            }

            self::advance_source_state( $source_post_id, $state, $after, $actor_user_id );
            Audit::record(
                'newsradar.source_checked',
                'success',
                'source',
                $source_post_id,
                array( 'manual' => $manual, 'legacy_id' => $legacy_id )
            );
            return array(
                'ok'             => true,
                'sourcePostId'   => $source_post_id,
                'legacySourceId' => $legacy_id,
                'checkedAtUtc'   => gmdate( 'c', $after ),
            );
        } finally {
            self::$managed_legacy_source_id = 0;
            self::release_newsradar_lock( is_string( $token ) ? $token : '' );
        }
    }

    /** @param mixed $sources */
    public static function filter_legacy_sources( mixed $sources ): mixed {
        if ( self::$managed_legacy_source_id < 1 || ! doing_action( 'mvm_bh_news_radar_cron' ) || ! is_array( $sources ) ) {
            return $sources;
        }
        foreach ( $sources as $source ) {
            if ( is_array( $source ) && absint( $source['id'] ?? 0 ) === self::$managed_legacy_source_id ) {
                $source['active']   = true;
                $source['excluded'] = false;
                return array( $source );
            }
        }
        return array();
    }

    /** @param mixed $settings */
    public static function filter_legacy_radar_settings( mixed $settings ): mixed {
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

    public static function run_ai_agenda(): void {
        if ( ! Runtime_Gates::cron_takeover_enabled() ) {
            return;
        }
        $settings = self::ai_settings();
        if ( empty( $settings['enabled'] ) ) {
            return;
        }
        $health = self::ai_provider_health();
        if ( $health['blocked'] ) {
            self::update_ai_status( 'Gepauzeerd: AI-wachtrij heeft ' . $health['pending'] . ' items (limiet ' . self::MAX_PENDING_AI . '). Rond eerst AI-afwachting af.', false );
            return;
        }

        // The existing crawler function is a stable platform integration and not
        // part of Hub4. Ownership here is scheduling/capability/health; data and
        // result options are intentionally retained in-place.
        if ( function_exists( 'mvm_ai_agenda_run' ) ) {
            $result = mvm_ai_agenda_run();
            self::update_ai_status( is_wp_error( $result ) ? $result->get_error_message() : 'AI-agendaradar uitgevoerd via bestaande platformprovider.', ! is_wp_error( $result ) );
            return;
        }

        /** @var mixed $provider */
        $provider = apply_filters( 'mvm_hub_ai_agenda_run_provider_v1', null );
        if ( is_callable( $provider ) ) {
            $result = $provider();
            self::update_ai_status( is_wp_error( $result ) ? $result->get_error_message() : 'AI-agendaradar uitgevoerd via Hub-provider.', ! is_wp_error( $result ) );
            return;
        }

        self::update_ai_status( 'AI-agendaprovider ontbreekt; bestaande radarstate is ongewijzigd bewaard.', false );
    }

    public static function cleanup_audit(): void {
        global $wpdb;
        if ( ! Runtime_Gates::cron_takeover_enabled() ) {
            return;
        }
        $days  = max( 14, absint( get_option( self::AUDIT_RETENTION_OPTION, 30 ) ) ?: 30 );
        $table = $wpdb->prefix . 'mvm_hub4_audit';
        $cutoff = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed internal table name.
        $deleted = $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE occurred_at_utc < %s", $cutoff ) );
        Audit::record( 'system.audit_cleanup', false === $deleted ? 'error' : 'success', 'audit', 0, array( 'deleted_count' => false === $deleted ? 0 : (int) $deleted, 'retention_days' => $days ) );
    }

    public static function register_status_route(): void {
        register_rest_route( 'mvm-hub/v1', '/technical/cron-status', array(
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => static fn(): \WP_REST_Response => rest_ensure_response( self::status() ),
            'permission_callback' => static fn(): bool => is_user_logged_in() && ( current_user_can( 'manage_options' ) || Capabilities::can_access_technical() ),
        ) );
    }

    /** @return array<string,mixed> */
    public static function status(): array {
        $ai = self::ai_provider_health();
        return array(
            'takeoverEnabled' => Runtime_Gates::cron_takeover_enabled(),
            'newsradar' => array(
                'hook'          => self::NEWSRADAR_HOOK,
                'nextRun'       => self::next_run_iso( self::NEWSRADAR_HOOK ),
                'providerReady' => function_exists( 'do_action' ) && is_array( get_option( 'mvm_bh_sources', array() ) ),
                'dueSources'    => self::count_due_sources(),
            ),
            'aiAgenda' => array(
                'hook'          => self::AI_AGENDA_HOOK,
                'nextRun'       => self::next_run_iso( self::AI_AGENDA_HOOK ),
                'enabled'       => ! empty( self::ai_settings()['enabled'] ),
                'providerReady' => function_exists( 'mvm_ai_agenda_run' ) || is_callable( apply_filters( 'mvm_hub_ai_agenda_run_provider_v1', null ) ),
                'pending'       => $ai['pending'],
                'blocked'       => $ai['blocked'],
                'status'        => $ai['status'],
                'message'       => $ai['message'],
            ),
            'audit' => array(
                'hook'    => self::AUDIT_HOOK,
                'nextRun' => self::next_run_iso( self::AUDIT_HOOK ),
            ),
            'stateResetPerformed' => false,
        );
    }

    /** @return array<string,mixed>|null */
    private static function next_due_source(): ?array {
        global $wpdb;
        $table = $wpdb->prefix . 'mvm_hub4_sources';
        $sql = $wpdb->prepare(
            "SELECT source_post_id,frequency,status,monitor_enabled,next_check_utc FROM {$table} WHERE monitor_enabled=1 AND status='active' AND next_check_utc IS NOT NULL AND next_check_utc <= %s ORDER BY next_check_utc ASC,source_post_id ASC LIMIT 1",
            current_time( 'mysql', true )
        );
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- prepared above.
        $row = $wpdb->get_row( $sql, ARRAY_A );
        return is_array( $row ) ? $row : null;
    }

    private static function count_due_sources(): int {
        global $wpdb;
        $table = $wpdb->prefix . 'mvm_hub4_sources';
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed internal table; value prepared.
        return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE monitor_enabled=1 AND status='active' AND next_check_utc IS NOT NULL AND next_check_utc <= %s", current_time( 'mysql', true ) ) );
    }

    /** @return array<string,mixed>|null */
    private static function source_state( int $source_post_id ): ?array {
        global $wpdb;
        $source_post_id = absint( $source_post_id );
        if ( $source_post_id < 1 ) {
            return null;
        }
        $table = $wpdb->prefix . 'mvm_hub4_sources';
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed internal table; value prepared.
        $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE source_post_id=%d", $source_post_id ), ARRAY_A );
        return is_array( $row ) ? $row : null;
    }

    private static function legacy_id_for_source( int $source_post_id ): int {
        return absint( get_post_meta( $source_post_id, '_mvm_newsradar_legacy_source_id', true ) );
    }

    private static function legacy_checked_timestamp( int $legacy_id ): int {
        foreach ( (array) get_option( 'mvm_bh_sources', array() ) as $source ) {
            if ( ! is_array( $source ) || absint( $source['id'] ?? 0 ) !== $legacy_id ) {
                continue;
            }
            $value = $source['last_checked'] ?? $source['lastChecked'] ?? 0;
            if ( is_numeric( $value ) ) {
                return absint( $value );
            }
            $timestamp = is_string( $value ) ? strtotime( $value ) : false;
            return false === $timestamp ? 0 : $timestamp;
        }
        return 0;
    }

    /** @param array<string,mixed> $state */
    private static function advance_source_state( int $source_post_id, array $state, int $checked_at, int $actor_user_id ): void {
        global $wpdb;
        $hours = self::frequency_hours( (string) ( $state['frequency'] ?? 'weekly' ) );
        $next  = $hours > 0 ? gmdate( 'Y-m-d H:i:s', $checked_at + ( $hours * HOUR_IN_SECONDS ) ) : null;
        $table = $wpdb->prefix . 'mvm_hub4_sources';
        $wpdb->update(
            $table,
            array(
                'last_checked_utc' => gmdate( 'Y-m-d H:i:s', $checked_at ),
                'last_checked_by'  => max( 0, $actor_user_id ),
                'next_check_utc'   => $next,
                'updated_at_utc'   => current_time( 'mysql', true ),
            ),
            array( 'source_post_id' => $source_post_id ),
            array( '%s', '%d', '%s', '%s' ),
            array( '%d' )
        );
    }

    private static function frequency_hours( string $frequency ): int {
        return array(
            'four_daily'   => 6,
            'twice_daily'  => 12,
            'daily'        => 24,
            'three_weekly' => 48,
            'twice_weekly' => 72,
            'weekly'       => 168,
            'biweekly'     => 336,
            'monthly'      => 720,
            'seasonal'     => 0,
            'on_demand'    => 0,
        )[ sanitize_key( $frequency ) ] ?? 168;
    }

    /** @return string|\WP_Error */
    private static function acquire_newsradar_lock(): string|\WP_Error {
        $existing = get_option( self::NEWSRADAR_LOCK, array() );
        if ( is_array( $existing ) && absint( $existing['expires'] ?? 0 ) < time() ) {
            delete_option( self::NEWSRADAR_LOCK );
        }
        $token = wp_generate_uuid4();
        $ok = add_option( self::NEWSRADAR_LOCK, array( 'token' => $token, 'expires' => time() + 8 * MINUTE_IN_SECONDS ), '', false );
        return $ok ? $token : new \WP_Error( 'mvm_newsradar_busy', 'Nieuwsradar is al met een andere bron bezig.' );
    }

    private static function release_newsradar_lock( string $token ): void {
        $existing = get_option( self::NEWSRADAR_LOCK, array() );
        if ( is_array( $existing ) && hash_equals( (string) ( $existing['token'] ?? '' ), $token ) ) {
            delete_option( self::NEWSRADAR_LOCK );
        }
    }

    /** @return array<string,mixed> */
    private static function ai_settings(): array {
        $value = get_option( 'mvm_hub_ai_agenda_v1', array() );
        return is_array( $value ) ? $value : array();
    }

    /** @return array{pending:int,blocked:bool,status:string,message:string} */
    private static function ai_provider_health(): array {
        $pending = 0;
        $rows = get_option( 'mvm_bh_news_radar_results', array() );
        foreach ( is_array( $rows ) ? $rows : array() as $row ) {
            if ( is_array( $row ) && ! empty( $row['ai_pending'] ) && empty( $row['dismissed'] ) ) {
                $pending++;
            }
        }
        $state = get_option( 'mvm_hub_ai_agenda_provider_v2', array() );
        $state = is_array( $state ) ? $state : array();
        $last_ok = absint( $state['last_ok'] ?? 0 );
        $blocked = $pending >= self::MAX_PENDING_AI;
        $status = $blocked ? 'backlog' : ( $last_ok > time() - HOUR_IN_SECONDS ? 'ok' : 'unknown' );
        $message = $blocked
            ? sprintf( '%d items wachten op AI; limiet is %d.', $pending, self::MAX_PENDING_AI )
            : sanitize_text_field( (string) ( $state['message'] ?? 'Nog geen recente bronvaste providerstatus.' ) );
        return array( 'pending' => $pending, 'blocked' => $blocked, 'status' => $status, 'message' => $message );
    }

    private static function update_ai_status( string $message, bool $success ): void {
        $settings = self::ai_settings();
        $settings['last_run'] = time();
        if ( $success ) {
            $settings['last_success'] = time();
        }
        $settings['last_message'] = mb_substr( sanitize_text_field( $message ), 0, 500 );
        update_option( 'mvm_hub_ai_agenda_v1', $settings, false );
        if ( $success ) {
            update_option( 'mvm_hub_ai_agenda_provider_v2', array( 'last_ok' => time(), 'message' => 'Hub cronprovider bevestigd.' ), false );
        }
    }

    private static function next_run_iso( string $hook ): ?string {
        $next = wp_next_scheduled( $hook );
        return $next ? gmdate( 'c', (int) $next ) : null;
    }

    private function __construct() {}
}
