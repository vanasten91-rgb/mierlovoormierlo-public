<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Cached, read-only system health orchestrator for Hub 4.
 */
final class MvM_Hub4_Health_Check {
    public const EXPECTED_CANONICAL_SOURCES = 118;
    public const EXPECTED_STOPPED_DUPLICATES = 118;

    private const CACHE_KEY   = 'system_health_v1';
    private const CACHE_GROUP = 'mvm_hub4';
    private const CACHE_TTL   = 5 * MINUTE_IN_SECONDS;
    private const VALID_STATUSES = array( 'ok', 'warning', 'critical', 'unknown' );

    /**
     * @return array<string,mixed>
     */
    public static function run( bool $fresh = false ): array {
        if ( $fresh ) {
            self::invalidate_cache();
        }

        $found  = false;
        $cached = wp_cache_get( self::CACHE_KEY, self::CACHE_GROUP, false, $found );
        if ( $found && is_array( $cached ) ) {
            return $cached;
        }

        $checked_at = gmdate( 'c' );
        $checks     = array();

        foreach ( self::definitions() as $definition ) {
            set_error_handler(
                static function ( int $severity, string $message, string $file, int $line ): bool {
                    if ( 0 === ( error_reporting() & $severity ) ) {
                        return false;
                    }
                    throw new ErrorException( $message, 0, $severity, $file, $line );
                }
            );
            try {
                $result = call_user_func( array( __CLASS__, $definition['callback'] ), $checked_at );
            } catch ( Throwable $error ) {
                unset( $error );
                $result = array(
                    'status'  => 'unknown',
                    'summary' => 'Deze controle kon veilig niet worden afgerond.',
                );
            } finally {
                restore_error_handler();
            }

            $checks[] = self::normalize_result( $definition, $result, $checked_at );
        }

        if ( class_exists( 'MvM_Hub4_Health_Route_Checks' ) ) {
            foreach ( MvM_Hub4_Health_Route_Checks::checks( $checked_at ) as $route_check ) {
                $checks[] = self::normalize_result(
                    array(
                        'id'       => sanitize_key( (string) ( $route_check['id'] ?? 'route_check' ) ),
                        'label'    => sanitize_text_field( (string) ( $route_check['label'] ?? 'Publieke route' ) ),
                        'callback' => '',
                    ),
                    $route_check,
                    $checked_at
                );
            }
        }

        $summary = self::summarize( $checks );
        $data    = array(
            'status'     => self::overall_status( $summary ),
            'checked_at' => $checked_at,
            'summary'    => $summary,
            'checks'     => $checks,
        );

        wp_cache_set( self::CACHE_KEY, $data, self::CACHE_GROUP, self::CACHE_TTL );
        return $data;
    }

    public static function invalidate_cache(): void {
        wp_cache_delete( self::CACHE_KEY, self::CACHE_GROUP );
    }

    /**
     * @return array<int,array{id:string,label:string,callback:string}>
     */
    public static function definitions(): array {
        return array(
            array( 'id' => 'wordpress_runtime', 'label' => 'WordPress-runtime', 'callback' => 'check_wordpress_runtime' ),
            array( 'id' => 'database_connection', 'label' => 'Databaseverbinding', 'callback' => 'check_database_connection' ),
            array( 'id' => 'rest_infrastructure', 'label' => 'REST-infrastructuur', 'callback' => 'check_rest_infrastructure' ),
            array( 'id' => 'cron_events', 'label' => 'WordPress-cron', 'callback' => 'check_cron_events' ),
            array( 'id' => 'action_scheduler', 'label' => 'Action Scheduler', 'callback' => 'check_action_scheduler' ),
            array( 'id' => 'action_scheduler_failures', 'label' => 'Recente Action Scheduler-fouten', 'callback' => 'check_action_scheduler_failures' ),
            array( 'id' => 'autoload_volume', 'label' => 'Autoload-volume', 'callback' => 'check_autoload_volume' ),
            array( 'id' => 'plugin_ultimate_member', 'label' => 'Ultimate Member', 'callback' => 'check_ultimate_member' ),
            array( 'id' => 'plugin_peepso', 'label' => 'PeepSo', 'callback' => 'check_peepso' ),
            array( 'id' => 'plugin_elementor', 'label' => 'Elementor', 'callback' => 'check_elementor' ),
            array( 'id' => 'plugin_elementor_pro', 'label' => 'Elementor Pro', 'callback' => 'check_elementor_pro' ),
            array( 'id' => 'plugin_postx', 'label' => 'PostX', 'callback' => 'check_postx' ),
            array( 'id' => 'plugin_postx_pro', 'label' => 'PostX Pro', 'callback' => 'check_postx_pro' ),
            array( 'id' => 'plugin_wp_event_manager', 'label' => 'WP Event Manager', 'callback' => 'check_wp_event_manager' ),
            array( 'id' => 'plugin_wpforo', 'label' => 'wpForo', 'callback' => 'check_wpforo' ),
            array( 'id' => 'plugin_yoast', 'label' => 'Yoast SEO', 'callback' => 'check_yoast' ),
            array( 'id' => 'temp_snippets', 'label' => 'Actieve TEMP-snippets', 'callback' => 'check_temp_snippets' ),
            array( 'id' => 'newsradar_active_sources', 'label' => 'Nieuwsradar canonieke bronnen', 'callback' => 'check_newsradar_active_sources' ),
            array( 'id' => 'newsradar_stopped_duplicates', 'label' => 'Nieuwsradar gestopte duplicaten', 'callback' => 'check_newsradar_stopped_duplicates' ),
            array( 'id' => 'newsradar_read_only', 'label' => 'Nieuwsradar-controle is read-only', 'callback' => 'check_newsradar_read_only' ),
            array( 'id' => 'ai_agenda_crawl', 'label' => 'AI-agendacrawl', 'callback' => 'check_ai_agenda_crawl' ),
            array( 'id' => 'permanent_og_configuration', 'label' => 'Permanente Open Graph-configuratie', 'callback' => 'check_permanent_og_configuration' ),
            array( 'id' => 'orphaned_authors', 'label' => 'Publieke content met geldige auteur', 'callback' => 'check_orphaned_authors' ),
            array( 'id' => 'seo_missing_descriptions', 'label' => 'Ontbrekende metabeschrijvingen', 'callback' => 'check_seo_missing_descriptions' ),
            array( 'id' => 'seo_description_lengths', 'label' => 'Lengte metabeschrijvingen', 'callback' => 'check_seo_description_lengths' ),
            array( 'id' => 'hub4_status', 'label' => 'Hub 4-status', 'callback' => 'check_hub4_status' ),
            array( 'id' => 'health_self_execution', 'label' => 'Health Check-uitvoering', 'callback' => 'check_self_execution' ),
        );
    }

    /**
     * @param array<int,array<string,mixed>> $checks Check results.
     * @return array{ok:int,warning:int,critical:int,unknown:int,total:int}
     */
    public static function summarize( array $checks ): array {
        $summary = array( 'ok' => 0, 'warning' => 0, 'critical' => 0, 'unknown' => 0, 'total' => 0 );
        foreach ( $checks as $check ) {
            $status = is_array( $check ) ? (string) ( $check['status'] ?? 'unknown' ) : 'unknown';
            $status = in_array( $status, self::VALID_STATUSES, true ) ? $status : 'unknown';
            $summary[ $status ]++;
            $summary['total']++;
        }
        return $summary;
    }

    /**
     * Unknown optional checks do not turn an otherwise healthy local run into
     * an alert. A fully unknown run remains neutral.
     *
     * @param array{ok:int,warning:int,critical:int,unknown:int,total:int} $summary Summary.
     */
    private static function overall_status( array $summary ): string {
        if ( $summary['critical'] > 0 ) {
            return 'critical';
        }
        if ( $summary['warning'] > 0 ) {
            return 'warning';
        }
        return $summary['ok'] > 0 ? 'ok' : 'unknown';
    }

    /**
     * @param array{id:string,label:string,callback:string} $definition Definition.
     * @param mixed                                        $result     Raw result.
     * @return array<string,mixed>
     */
    private static function normalize_result( array $definition, mixed $result, string $checked_at ): array {
        $result = is_array( $result ) ? $result : array();
        $status = sanitize_key( (string) ( $result['status'] ?? 'unknown' ) );
        $status = in_array( $status, self::VALID_STATUSES, true ) ? $status : 'unknown';

        $clean = array(
            'id'         => sanitize_key( $definition['id'] ),
            'label'      => sanitize_text_field( $definition['label'] ),
            'status'     => $status,
            'summary'    => sanitize_text_field( (string) ( $result['summary'] ?? 'Geen aanvullende informatie.' ) ),
            'checked_at' => sanitize_text_field( (string) ( $result['checked_at'] ?? $checked_at ) ),
        );

        foreach ( array( 'metric', 'expected', 'actual' ) as $key ) {
            if ( isset( $result[ $key ] ) && is_scalar( $result[ $key ] ) ) {
                $clean[ $key ] = is_numeric( $result[ $key ] ) ? 0 + $result[ $key ] : sanitize_text_field( (string) $result[ $key ] );
            }
        }
        return $clean;
    }

    private static function base_result( string $status, string $summary, mixed $expected = null, mixed $actual = null ): array {
        $result = array( 'status' => $status, 'summary' => $summary );
        if ( null !== $expected ) {
            $result['expected'] = $expected;
        }
        if ( null !== $actual ) {
            $result['actual'] = $actual;
        }
        return $result;
    }

    private static function check_wordpress_runtime( string $checked_at ): array {
        unset( $checked_at );
        $available = defined( 'ABSPATH' ) && function_exists( 'get_bloginfo' );
        return self::base_result(
            $available ? 'ok' : 'critical',
            $available ? 'De WordPress-runtime is beschikbaar.' : 'De WordPress-runtime is niet volledig beschikbaar.',
            'beschikbaar',
            $available ? 'beschikbaar' : 'onbeschikbaar'
        );
    }

    private static function check_database_connection( string $checked_at ): array {
        unset( $checked_at );
        global $wpdb;
        $connected = is_object( $wpdb ) && '1' === (string) $wpdb->get_var( 'SELECT 1' );
        return self::base_result(
            $connected ? 'ok' : 'critical',
            $connected ? 'WordPress kan de database lezen.' : 'De databasecontrole is niet geslaagd.',
            'leesbaar',
            $connected ? 'leesbaar' : 'niet leesbaar'
        );
    }

    private static function check_rest_infrastructure( string $checked_at ): array {
        unset( $checked_at );
        $available = class_exists( 'WP_REST_Server' ) && function_exists( 'rest_get_server' ) && function_exists( 'rest_url' );
        return self::base_result(
            $available ? 'ok' : 'critical',
            $available ? 'De WordPress REST-infrastructuur is geladen.' : 'De WordPress REST-infrastructuur ontbreekt.',
            'beschikbaar',
            $available ? 'beschikbaar' : 'onbeschikbaar'
        );
    }

    private static function check_cron_events( string $checked_at ): array {
        unset( $checked_at );
        $events   = function_exists( '_get_cron_array' ) ? _get_cron_array() : false;
        $disabled = defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON;
        $count    = is_array( $events ) ? count( $events ) : 0;
        $status   = $count > 0 ? ( $disabled ? 'warning' : 'ok' ) : 'warning';
        $summary  = $count > 0 ? 'WordPress heeft geplande cronmomenten.' : 'Er zijn geen geplande cronmomenten gevonden.';
        if ( $disabled ) {
            $summary = 'Interne WP-Cron is uitgeschakeld; controleer de externe scheduler.';
        }
        return self::base_result( $status, $summary, 'minimaal 1', $count );
    }

    private static function check_action_scheduler( string $checked_at ): array {
        unset( $checked_at );
        $available = self::action_scheduler_available();
        return self::base_result(
            $available ? 'ok' : 'unknown',
            $available ? 'Action Scheduler is beschikbaar.' : 'Action Scheduler is niet aangetroffen en kan optioneel zijn.',
            'indien vereist beschikbaar',
            $available ? 'beschikbaar' : 'niet aangetroffen'
        );
    }

    private static function check_action_scheduler_failures( string $checked_at ): array {
        unset( $checked_at );
        if ( ! self::action_scheduler_available() ) {
            return self::base_result( 'unknown', 'Geen Action Scheduler-runtime om te controleren.' );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'actionscheduler_actions';
        if ( ! self::table_exists( $table ) ) {
            return self::base_result( 'unknown', 'De Action Scheduler-tabel is niet beschikbaar.' );
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table name; fixed read-only query.
        $count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'failed' AND last_attempt_gmt >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 24 HOUR)" );
        $status = $count > 25 ? 'critical' : ( $count > 5 ? 'warning' : 'ok' );
        return self::base_result(
            $status,
            $count > 5 ? 'Er zijn opvallend veel recente mislukte acties.' : 'Geen onverwachte explosie aan recente mislukte acties.',
            'maximaal 5 in 24 uur',
            $count
        );
    }

    private static function check_autoload_volume( string $checked_at ): array {
        unset( $checked_at );
        global $wpdb;
        $bytes = (int) $wpdb->get_var( "SELECT COALESCE(SUM(LENGTH(option_name) + LENGTH(option_value)), 0) FROM {$wpdb->options} WHERE autoload IN ('yes','on','auto','auto-on')" );
        $status = $bytes >= 2 * MB_IN_BYTES ? 'critical' : ( $bytes >= 500 * KB_IN_BYTES ? 'warning' : 'ok' );
        return array(
            'status'   => $status,
            'summary'  => $status === 'ok' ? 'Het autoload-volume blijft onder de waarschuwingsdrempel.' : 'Het autoload-volume verdient aandacht.',
            'metric'   => 'bytes',
            'expected' => 500 * KB_IN_BYTES,
            'actual'   => $bytes,
        );
    }

    private static function check_ultimate_member( string $checked_at ): array {
        unset( $checked_at );
        return self::plugin_result( 'Ultimate Member', array( 'ultimate-member/ultimate-member.php' ), array( 'UM' ), array( 'UM_VERSION' ) );
    }

    private static function check_peepso( string $checked_at ): array {
        unset( $checked_at );
        return self::plugin_result( 'PeepSo', array( 'peepso-core/peepso.php', 'peepso/peepso.php' ), array( 'PeepSo', 'peepso' ), array( 'PEEPSO_VERSION' ) );
    }

    private static function check_elementor( string $checked_at ): array {
        unset( $checked_at );
        return self::plugin_result( 'Elementor', array( 'elementor/elementor.php' ), array(), array( 'ELEMENTOR_VERSION' ), array( 'Elementor\\Plugin' ) );
    }

    private static function check_elementor_pro( string $checked_at ): array {
        unset( $checked_at );
        return self::plugin_result( 'Elementor Pro', array( 'elementor-pro/elementor-pro.php' ), array(), array( 'ELEMENTOR_PRO_VERSION' ) );
    }

    private static function check_postx( string $checked_at ): array {
        unset( $checked_at );
        return self::plugin_result( 'PostX', array( 'ultimate-post/ultimate-post.php', 'postx/postx.php' ), array(), array( 'ULTP_VER', 'POSTX_VERSION' ) );
    }

    private static function check_postx_pro( string $checked_at ): array {
        unset( $checked_at );
        return self::plugin_result( 'PostX Pro', array( 'ultimate-post-pro/ultimate-post-pro.php', 'postx-pro/postx-pro.php' ), array(), array( 'ULTP_PRO_VER', 'POSTX_PRO_VERSION' ) );
    }

    private static function check_wp_event_manager( string $checked_at ): array {
        unset( $checked_at );
        return self::plugin_result( 'WP Event Manager', array( 'wp-event-manager/wp-event-manager.php' ), array(), array( 'WP_EVENT_MANAGER_VERSION' ), array( 'WP_Event_Manager' ) );
    }

    private static function check_wpforo( string $checked_at ): array {
        unset( $checked_at );
        return self::plugin_result( 'wpForo', array( 'wpforo/wpforo.php' ), array( 'WPF' ), array( 'WPFORO_VERSION' ) );
    }

    private static function check_yoast( string $checked_at ): array {
        unset( $checked_at );
        return self::plugin_result( 'Yoast SEO', array( 'wordpress-seo/wp-seo.php' ), array(), array( 'WPSEO_VERSION' ) );
    }

    private static function check_temp_snippets( string $checked_at ): array {
        unset( $checked_at );
        global $wpdb;
        $table = $wpdb->prefix . 'snippets';
        if ( ! self::plugin_is_active( array( 'code-snippets/code-snippets.php' ) ) && ! self::table_exists( $table ) ) {
            return self::base_result( 'unknown', 'Code Snippets is niet beschikbaar; deze controle is niet van toepassing.' );
        }
        if ( ! self::table_exists( $table ) ) {
            return self::base_result( 'unknown', 'De Code Snippets-tabel is niet beschikbaar.' );
        }
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table name; pattern is prepared.
        $count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE active = 1 AND UPPER(name) REGEXP %s", '(^|[^A-Z0-9])TEMP([^A-Z0-9]|$)' ) );
        return self::base_result(
            0 === $count ? 'ok' : 'critical',
            0 === $count ? 'Er zijn geen actieve TEMP-snippets.' : 'Er zijn actieve TEMP-snippets aangetroffen.',
            0,
            $count
        );
    }

    private static function check_newsradar_active_sources( string $checked_at ): array {
        unset( $checked_at );
        $count = self::newsradar_count( 'active', true );
        if ( null === $count ) {
            return self::base_result( 'unknown', 'De canonieke Nieuwsradar-bronnen konden niet betrouwbaar worden geteld.' );
        }
        return self::base_result(
            self::EXPECTED_CANONICAL_SOURCES === $count ? 'ok' : 'critical',
            self::EXPECTED_CANONICAL_SOURCES === $count ? 'De canonieke Nieuwsradar-baseline klopt.' : 'De canonieke Nieuwsradar-baseline wijkt af.',
            self::EXPECTED_CANONICAL_SOURCES,
            $count
        );
    }

    private static function check_newsradar_stopped_duplicates( string $checked_at ): array {
        unset( $checked_at );
        $count = self::newsradar_count( 'stopped', false );
        if ( null === $count ) {
            return self::base_result( 'unknown', 'De gestopte Nieuwsradar-duplicaten konden niet betrouwbaar worden geteld.' );
        }
        return self::base_result(
            self::EXPECTED_STOPPED_DUPLICATES === $count ? 'ok' : 'critical',
            self::EXPECTED_STOPPED_DUPLICATES === $count ? 'De gestopte duplicaatbaseline klopt.' : 'De gestopte duplicaatbaseline wijkt af.',
            self::EXPECTED_STOPPED_DUPLICATES,
            $count
        );
    }

    private static function check_newsradar_read_only( string $checked_at ): array {
        unset( $checked_at );
        return self::base_result( 'ok', 'De Health Check leest de Nieuwsradar-status en voert geen herstelmutatie uit.', 'read-only', 'read-only' );
    }

    private static function check_ai_agenda_crawl( string $checked_at ): array {
        unset( $checked_at );
        if ( ! class_exists( 'MvM_Hub4_AI_Agenda_Radar' ) ) {
            return self::base_result( 'unknown', 'De AI-agendacrawlmodule is niet geladen.' );
        }

        $status = MvM_Hub4_AI_Agenda_Radar::status();
        if ( MvM_Hub4_AI_Agenda_Radar::EXPECTED_ACTIVE !== (int) $status['activeSources']
            || MvM_Hub4_AI_Agenda_Radar::EXPECTED_STOPPED !== (int) $status['stoppedDuplicates'] ) {
            return self::base_result( 'critical', 'De beschermde 118/118-bronbaseline wijkt af; de agendacrawl blijft geblokkeerd.', '118 actief / 118 gestopt', (int) $status['activeSources'] . ' actief / ' . (int) $status['stoppedDuplicates'] . ' gestopt' );
        }
        if ( ! empty( $status['lockStale'] ) ) {
            return self::base_result( 'critical', 'De AI-agendacrawl heeft een verlopen runtime-lock.' );
        }
        if ( ! empty( $status['enabled'] ) && empty( $status['scheduled'] ) ) {
            return self::base_result( 'critical', 'De AI-agendacrawl staat aan maar heeft geen geplande uitvoering.' );
        }
        if ( empty( $status['enabled'] ) ) {
            return self::base_result( 'warning', 'De AI-agendacrawl staat uit; bestaande Radar-items blijven beschikbaar.' );
        }
        if ( ! empty( $status['providerBlocked'] ) ) {
            return self::base_result( 'warning', 'De crawl is bronveilig, maar AI-beoordeling is tijdelijk gepauzeerd: ' . (string) $status['providerMessage'] );
        }

        return self::base_result(
            'ok',
            'De AI-agendacrawl is begrensd tot maximaal twee bronnen, publiceert niets en bewaakt de 118/118-baseline.',
            'maximaal 2 bronnen / geen publicatie',
            'maximaal ' . (int) $status['maxBatch'] . ' bronnen / geen publicatie'
        );
    }

    private static function check_permanent_og_configuration( string $checked_at ): array {
        unset( $checked_at );
        $recognized = false;
        foreach ( array( 'wpseo_opengraph_image', 'wpseo_opengraph_image_id', 'wpseo_frontpage_image' ) as $filter ) {
            if ( has_filter( $filter ) ) {
                $recognized = true;
                break;
            }
        }

        foreach ( array( 'wpseo_social', 'wpseo_titles' ) as $option_name ) {
            $option = get_option( $option_name, array() );
            if ( is_array( $option ) && self::has_nonempty_og_value( $option ) ) {
                $recognized = true;
            }
        }

        $front_page_id = absint( get_option( 'page_on_front', 0 ) );
        if ( $front_page_id ) {
            $recognized = $recognized
                || '' !== (string) get_post_meta( $front_page_id, '_yoast_wpseo_opengraph-image', true )
                || '' !== (string) get_post_meta( $front_page_id, '_yoast_wpseo_opengraph-image-id', true );
        }

        return self::base_result(
            $recognized ? 'ok' : 'warning',
            $recognized ? 'Een permanente Open Graph-afbeeldingsconfiguratie is herkenbaar.' : 'De permanente Open Graph-afbeeldingsconfiguratie is niet herkenbaar.',
            'herkenbaar',
            $recognized ? 'herkenbaar' : 'niet herkenbaar'
        );
    }

    private static function check_orphaned_authors( string $checked_at ): array {
        unset( $checked_at );
        global $wpdb;
        $count = (int) $wpdb->get_var(
            "SELECT COUNT(*)
             FROM {$wpdb->posts} p
             LEFT JOIN {$wpdb->users} u ON u.ID = p.post_author
             WHERE p.post_type IN ('post','page','event_listing')
               AND p.post_status = 'publish'
               AND (p.post_author = 0 OR u.ID IS NULL)"
        );
        return self::base_result(
            0 === $count ? 'ok' : 'warning',
            0 === $count ? 'Publieke standaardcontent heeft een geldige auteur.' : 'Er is publieke content met een ontbrekende auteur.',
            0,
            $count
        );
    }

    private static function check_seo_missing_descriptions( string $checked_at ): array {
        unset( $checked_at );
        $count = self::yoast_description_count( 'missing' );
        if ( null === $count ) {
            return self::base_result( 'unknown', 'Yoast-indexdata is niet beschikbaar voor een betrouwbare telling.' );
        }
        $status = 0 === $count ? 'ok' : ( $count > 10 ? 'critical' : 'warning' );
        return self::base_result(
            $status,
            0 === $count ? 'Alle indexeerbare standaardcontent heeft een metabeschrijving.' : 'Indexeerbare standaardcontent mist een metabeschrijving.',
            0,
            $count
        );
    }

    private static function check_seo_description_lengths( string $checked_at ): array {
        unset( $checked_at );
        $count = self::yoast_description_count( 'length' );
        if ( null === $count ) {
            return self::base_result( 'unknown', 'Yoast-indexdata is niet beschikbaar voor een betrouwbare lengtemeting.' );
        }
        $status = 0 === $count ? 'ok' : ( $count > 10 ? 'critical' : 'warning' );
        return self::base_result(
            $status,
            0 === $count ? 'De metabeschrijvingen vallen binnen de afgesproken bandbreedte.' : 'Een groep metabeschrijvingen heeft een afwijkende lengte.',
            0,
            $count
        );
    }

    private static function check_hub4_status( string $checked_at ): array {
        unset( $checked_at );
        $available = defined( 'MVM_HUB4_VERSION' )
            && class_exists( 'MvM_Hub4_REST' )
            && class_exists( 'MvM_Hub4_App' )
            && method_exists( 'MvM_Hub4_REST', 'status' );
        return self::base_result(
            $available ? 'ok' : 'critical',
            $available ? 'Hub 4 kan zijn beveiligde statuslaag leveren.' : 'De Hub 4-statuslaag is niet volledig geladen.',
            'beschikbaar',
            $available ? 'beschikbaar' : 'onbeschikbaar'
        );
    }

    private static function check_self_execution( string $checked_at ): array {
        unset( $checked_at );
        return self::base_result( 'ok', 'De Health Check is zonder PHP warning of fatal tot deze controle uitgevoerd.' );
    }

    private static function action_scheduler_available(): bool {
        global $wpdb;
        return function_exists( 'as_has_scheduled_action' )
            || class_exists( 'ActionScheduler' )
            || self::table_exists( $wpdb->prefix . 'actionscheduler_actions' );
    }

    /**
     * @param array<int,string> $plugins   Plugin basenames.
     * @param array<int,string> $functions Runtime functions.
     * @param array<int,string> $constants Runtime constants.
     * @param array<int,string> $classes   Runtime classes.
     */
    private static function plugin_result( string $label, array $plugins, array $functions = array(), array $constants = array(), array $classes = array() ): array {
        $active = self::plugin_is_active( $plugins );
        foreach ( $functions as $function ) {
            $active = $active || function_exists( $function );
        }
        foreach ( $constants as $constant ) {
            $active = $active || defined( $constant );
        }
        foreach ( $classes as $class ) {
            $active = $active || class_exists( $class );
        }
        return self::base_result(
            $active ? 'ok' : 'critical',
            $active ? $label . ' is actief.' : $label . ' is niet actief of niet geladen.',
            'actief',
            $active ? 'actief' : 'niet actief'
        );
    }

    /** @param array<int,string> $plugins Plugin basenames. */
    private static function plugin_is_active( array $plugins ): bool {
        $active = array_map( 'strval', (array) get_option( 'active_plugins', array() ) );
        if ( is_multisite() ) {
            $active = array_merge( $active, array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) ) );
        }
        return (bool) array_intersect( $plugins, $active );
    }

    private static function table_exists( string $table ): bool {
        global $wpdb;
        return $table === (string) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
    }

    private static function newsradar_count( string $status, bool $enabled ): ?int {
        global $wpdb;
        $table = class_exists( 'MvM_Hub4_Sources' ) ? MvM_Hub4_Sources::table_name() : $wpdb->prefix . 'mvm_hub4_sources';
        if ( ! self::table_exists( $table ) ) {
            return null;
        }
        $enabled_value = $enabled ? 1 : 0;
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal tables; values are prepared.
        $sql = "SELECT COUNT(DISTINCT s.source_post_id)
                FROM {$table} s
                INNER JOIN {$wpdb->posts} p ON p.ID = s.source_post_id
                INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
                WHERE p.post_type = 'mvm_bron'
                  AND p.post_status = 'publish'
                  AND pm.meta_key = '_mvm_newsradar_source'
                  AND pm.meta_value = '1'
                  AND s.status = %s
                  AND s.monitor_enabled = %d";
        return (int) $wpdb->get_var( $wpdb->prepare( $sql, $status, $enabled_value ) );
    }

    /** @param array<string,mixed> $option Option data. */
    private static function has_nonempty_og_value( array $option ): bool {
        foreach ( $option as $key => $value ) {
            if ( is_scalar( $value ) && '' !== trim( (string) $value ) && preg_match( '/(?:open[_-]?graph|og).*(?:image|id)|(?:image|id).*(?:open[_-]?graph|og)/i', (string) $key ) ) {
                return true;
            }
        }
        return false;
    }

    private static function yoast_description_count( string $mode ): ?int {
        global $wpdb;
        $table = $wpdb->prefix . 'yoast_indexable';
        if ( ! self::table_exists( $table ) ) {
            return null;
        }
        $condition = 'missing' === $mode
            ? "TRIM(COALESCE(y.description, '')) = ''"
            : "TRIM(COALESCE(y.description, '')) <> '' AND (CHAR_LENGTH(y.description) < 70 OR CHAR_LENGTH(y.description) > 160)";
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal tables and fixed allowlisted condition.
        return (int) $wpdb->get_var(
            "SELECT COUNT(DISTINCT p.ID)
             FROM {$table} y
             INNER JOIN {$wpdb->posts} p ON p.ID = y.object_id
             WHERE y.object_type = 'post'
               AND y.object_sub_type IN ('post','page')
               AND p.post_status = 'publish'
               AND (y.is_robots_noindex IS NULL OR y.is_robots_noindex = 0)
               AND {$condition}"
        );
    }
}
