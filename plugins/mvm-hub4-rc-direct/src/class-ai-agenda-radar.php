<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Source-native AI agenda discovery for the existing Nieuwsradar dataset.
 *
 * The class deliberately preserves the legacy Radar option as the hand-off
 * boundary. It only creates review candidates and never creates or publishes
 * WordPress content. While the permanent snippet implementation is still
 * loaded, the class runs in compatibility mode and delegates manual runs.
 */
final class MvM_Hub4_AI_Agenda_Radar {
    public const HOOK             = 'mvm_hub_ai_agenda_scan_v1';
    public const ANALYSIS_HOOK    = 'mvm_hub4_ai_agenda_analyze_v2';
    public const SCHEDULE         = 'mvm_ai_agenda_15m';
    public const OPTION_SETTINGS  = 'mvm_hub_ai_agenda_v1';
    public const OPTION_RESULTS   = 'mvm_bh_news_radar_results';
    public const EXPECTED_ACTIVE  = 118;
    public const EXPECTED_STOPPED = 118;
    public const MAX_BATCH        = 2;

    private const OPTION_LOCK          = 'mvm_hub_ai_agenda_lock_v2';
    private const OPTION_PROVIDER      = 'mvm_hub_ai_agenda_provider_v2';
    private const OPTION_ANALYSIS_LOCK = 'mvm_hub_ai_agenda_analysis_lock_v2';
    private const MAX_EVENTS_SOURCE    = 3;
    private const MAX_BODY_BYTES       = 900000;
    private const MAX_PENDING_AI       = 25;

    private static bool $native_owner = false;

    public static function init(): void {
        add_filter( 'cron_schedules', array( __CLASS__, 'cron_schedules' ) );

        self::$native_owner = ! self::legacy_owner_active();
        if ( ! self::$native_owner ) {
            return;
        }

        add_action( 'init', array( __CLASS__, 'ensure_schedule' ), 60 );
        add_action( self::HOOK, array( __CLASS__, 'run' ) );
        add_action( self::ANALYSIS_HOOK, array( __CLASS__, 'analyze_pending' ) );
    }

    public static function deactivate(): void {
        if ( ! self::legacy_owner_active() ) {
            wp_clear_scheduled_hook( self::HOOK );
        }
        wp_clear_scheduled_hook( self::ANALYSIS_HOOK );
        delete_option( self::OPTION_LOCK );
        delete_option( self::OPTION_ANALYSIS_LOCK );
    }

    /** @param array<string,array<string,int|string>> $schedules */
    public static function cron_schedules( array $schedules ): array {
        if ( ! isset( $schedules[ self::SCHEDULE ] ) ) {
            $schedules[ self::SCHEDULE ] = array(
                'interval' => 15 * MINUTE_IN_SECONDS,
                'display'  => 'MvM AI-agendacrawl iedere 15 minuten',
            );
        }
        return $schedules;
    }

    public static function ensure_schedule(): void {
        if ( self::legacy_owner_active() ) {
            self::$native_owner = false;
            remove_action( self::HOOK, array( __CLASS__, 'run' ) );
            return;
        }
        if ( ! self::$native_owner ) {
            return;
        }

        $settings = self::settings();
        $next     = wp_next_scheduled( self::HOOK );
        if ( ! empty( $settings['enabled'] ) ) {
            if ( ! $next ) {
                wp_schedule_event( time() + ( 2 * MINUTE_IN_SECONDS ), self::SCHEDULE, self::HOOK );
            }
            return;
        }

        if ( $next ) {
            wp_clear_scheduled_hook( self::HOOK );
        }
    }

    /** @return array<string,mixed> */
    public static function settings(): array {
        $stored = get_option( self::OPTION_SETTINGS, array() );
        return self::sanitize_settings( is_array( $stored ) ? $stored : array() );
    }

    /**
     * @param array<string,mixed> $value
     * @return array<string,mixed>
     */
    public static function sanitize_settings( array $value ): array {
        $sort = sanitize_key( (string) ( $value['radar_sort'] ?? $value['radarSort'] ?? 'recent' ) );
        if ( ! in_array( $sort, array( 'recent', 'relevance', 'radar' ), true ) ) {
            $sort = 'recent';
        }

        return array(
            'enabled'      => ! empty( $value['enabled'] ),
            'batch'        => min( self::MAX_BATCH, max( 1, absint( $value['batch'] ?? self::MAX_BATCH ) ) ),
            'cursor'       => absint( $value['cursor'] ?? 0 ),
            'last_run'     => absint( $value['last_run'] ?? 0 ),
            'last_success' => absint( $value['last_success'] ?? 0 ),
            'last_message' => mb_substr( sanitize_text_field( (string) ( $value['last_message'] ?? 'Nog niet uitgevoerd.' ) ), 0, 500 ),
            'radar_sort'   => $sort,
        );
    }

    /** @param array<string,mixed> $changes */
    public static function update_settings( array $changes ): array {
        $settings = self::settings();
        foreach ( array( 'enabled', 'batch', 'radar_sort', 'radarSort' ) as $key ) {
            if ( array_key_exists( $key, $changes ) ) {
                $settings[ $key ] = $changes[ $key ];
            }
        }
        $settings = self::sanitize_settings( $settings );
        update_option( self::OPTION_SETTINGS, $settings, false );
        self::ensure_schedule();
        return $settings;
    }

    /** @return array<string,mixed> */
    public static function status(): array {
        $settings = self::settings();
        $counts   = self::source_counts();
        $provider = self::provider_health();
        $lock     = get_option( self::OPTION_LOCK, array() );
        $lock     = is_array( $lock ) ? $lock : array();
        $expires  = absint( $lock['expires'] ?? 0 );
        $next     = wp_next_scheduled( self::HOOK );

        return array(
            'enabled'           => ! empty( $settings['enabled'] ),
            'batch'             => (int) $settings['batch'],
            'radarSort'         => (string) $settings['radar_sort'],
            'ownership'         => self::legacy_owner_active() ? 'compatibility' : 'native',
            'lastRun'           => $settings['last_run'] ? wp_date( DATE_ATOM, (int) $settings['last_run'] ) : null,
            'lastSuccess'       => $settings['last_success'] ? wp_date( DATE_ATOM, (int) $settings['last_success'] ) : null,
            'lastMessage'       => (string) $settings['last_message'],
            'nextRun'           => $next ? wp_date( DATE_ATOM, (int) $next ) : null,
            'scheduled'         => (bool) $next,
            'running'           => $expires >= time(),
            'lockStale'         => $expires > 0 && $expires < time(),
            'activeSources'     => (int) $counts['active'],
            'stoppedDuplicates' => (int) $counts['stopped'],
            'pendingAi'         => (int) $provider['pending'],
            'providerBlocked'   => ! empty( $provider['blocked'] ),
            'providerStatus'    => (string) $provider['status'],
            'providerMessage'   => (string) $provider['message'],
            'maxBatch'          => self::MAX_BATCH,
            'publishesContent'  => false,
        );
    }

    /**
     * Run one bounded batch. Compatibility mode delegates to the latest live
     * implementation so the transition can be deployed before snippets are
     * disabled.
     *
     * @return array<string,mixed>|WP_Error
     */
    public static function run(): array|WP_Error {
        $settings = self::settings();
        if ( empty( $settings['enabled'] ) ) {
            return new WP_Error( 'mvm_agenda_disabled', 'Zet AI-agendacrawl eerst aan.', array( 'status' => 400 ) );
        }

        $counts = self::source_counts();
        if ( self::EXPECTED_ACTIVE !== (int) $counts['active'] || self::EXPECTED_STOPPED !== (int) $counts['stopped'] ) {
            return new WP_Error(
                'mvm_agenda_source_baseline',
                'De beschermde Nieuwsradar-bronbaseline wijkt af; de crawl is niet gestart.',
                array( 'status' => 409 )
            );
        }

        $legacy = self::delegate_legacy_run();
        if ( null !== $legacy ) {
            return $legacy;
        }

        $provider = self::provider_health();
        if ( ! empty( $provider['blocked'] ) ) {
            $settings['last_run']     = time();
            $settings['last_message'] = 'AI-agendacrawl tijdelijk gepauzeerd: ' . $provider['message'];
            update_option( self::OPTION_SETTINGS, self::sanitize_settings( $settings ), false );
            return array(
                'added'     => 0,
                'checked'   => 0,
                'paused'    => true,
                'pendingAi' => (int) $provider['pending'],
                'message'   => $settings['last_message'],
            );
        }

        if ( class_exists( 'MvM_Hub4_Legacy_Newsradar_Writes' ) && MvM_Hub4_Legacy_Newsradar_Writes::crawler_busy() ) {
            return array(
                'added'   => 0,
                'checked' => 0,
                'paused'  => true,
                'message' => 'De gewone Nieuwsradar verwerkt nu gegevens; de agendacrawl probeert het later opnieuw.',
            );
        }

        if ( ! self::acquire_lock( self::OPTION_LOCK, 8 * MINUTE_IN_SECONDS ) ) {
            return new WP_Error( 'mvm_agenda_busy', 'Er loopt al een AI-agendacrawl.', array( 'status' => 409 ) );
        }

        try {
            $sources = self::active_sources();
            if ( self::EXPECTED_ACTIVE !== count( $sources ) ) {
                return new WP_Error(
                    'mvm_agenda_source_urls',
                    'Niet alle 118 actieve bronnen hebben een geldige, veilige URL.',
                    array( 'status' => 409 )
                );
            }

            $batch   = min( self::MAX_BATCH, max( 1, (int) $settings['batch'] ) );
            $cursor  = (int) $settings['cursor'] % count( $sources );
            $summary = array(
                'added'            => 0,
                'updated'          => 0,
                'duplicates'       => 0,
                'checked'          => 0,
                'failed'           => 0,
                'agendaPages'      => 0,
                'structuredEvents' => 0,
                'tableEvents'      => 0,
                'paused'           => false,
            );

            for ( $offset = 0; $offset < $batch; $offset++ ) {
                $source = $sources[ ( $cursor + $offset ) % count( $sources ) ];
                self::scan_source( $source, $summary );
            }

            $settings['cursor']       = ( $cursor + $batch ) % count( $sources );
            $settings['last_run']     = time();
            $settings['last_success'] = time();
            $settings['last_message'] = sprintf(
                '%d bron(nen) gecontroleerd; %d agenda-/kalenderpagina(s); %d structured event(s); %d tabel-event(s); %d nieuw(e) item(s) naar de Nieuwsradar AI-wachtrij.',
                $summary['checked'],
                $summary['agendaPages'],
                $summary['structuredEvents'],
                $summary['tableEvents'],
                $summary['added']
            );
            update_option( self::OPTION_SETTINGS, self::sanitize_settings( $settings ), false );

            if ( $summary['added'] > 0 && ! self::legacy_analysis_owner_active() && ! wp_next_scheduled( self::ANALYSIS_HOOK ) ) {
                wp_schedule_single_event( time() + 5, self::ANALYSIS_HOOK );
            }

            $summary['message'] = $settings['last_message'];
            return $summary;
        } finally {
            self::release_lock( self::OPTION_LOCK );
        }
    }

    /**
     * Native provider adapter for agenda candidates. Existing snippet-owned
     * analysis remains authoritative until it is disabled.
     */
    public static function analyze_pending(): void {
        if ( self::legacy_analysis_owner_active() || ! self::acquire_lock( self::OPTION_ANALYSIS_LOCK, 5 * MINUTE_IN_SECONDS ) ) {
            return;
        }

        try {
            if ( class_exists( 'MvM_Hub4_Legacy_Newsradar_Writes' ) && MvM_Hub4_Legacy_Newsradar_Writes::crawler_busy() ) {
                self::schedule_analysis_retry( 60 );
                return;
            }
            $rows    = self::result_rows();
            $pending = array();
            foreach ( $rows as $row ) {
                if ( ! is_array( $row ) || empty( $row['ai_pending'] ) || empty( $row['agenda_start_ts'] ) || ! empty( $row['dismissed'] ) ) {
                    continue;
                }
                $pending[] = $row;
                if ( count( $pending ) >= 2 ) {
                    break;
                }
            }
            if ( ! $pending ) {
                return;
            }

            $prompt = self::analysis_prompt( $pending );
            $text   = self::generate_analysis_text( $prompt );
            if ( is_wp_error( $text ) ) {
                self::record_provider_state( false, 'De AI-provider is tijdelijk niet beschikbaar.' );
                self::schedule_analysis_retry( 60 );
                return;
            }

            $analysis = self::validate_analysis_payload( $text, array_column( $pending, 'id' ) );
            if ( is_wp_error( $analysis ) ) {
                self::record_provider_state( false, 'De AI-provider gaf geen geldig gestructureerd antwoord.' );
                self::schedule_analysis_retry( 60 );
                return;
            }

            $processed = self::apply_analysis( $analysis );
            self::record_provider_state( true, sprintf( '%d agenda-item(s) beoordeeld.', $processed ) );
            if ( self::provider_health()['pending'] > 0 ) {
                self::schedule_analysis_retry( 20 );
            }
        } finally {
            self::release_lock( self::OPTION_ANALYSIS_LOCK );
        }
    }

    /**
     * Stable sort used by the existing Nieuwsradar response.
     *
     * @param array<int,array<string,mixed>> $groups
     * @return array<int,array<string,mixed>>
     */
    public static function sort_groups( array $groups ): array {
        $mode = (string) self::settings()['radar_sort'];
        if ( 'radar' === $mode ) {
            return array_values( $groups );
        }

        usort(
            $groups,
            static function ( array $left, array $right ) use ( $mode ): int {
                $a = is_array( $left['primary'] ?? null ) ? $left['primary'] : array();
                $b = is_array( $right['primary'] ?? null ) ? $right['primary'] : array();
                if ( 'relevance' === $mode ) {
                    $score = absint( $b['local_score'] ?? 0 ) <=> absint( $a['local_score'] ?? 0 );
                    if ( 0 !== $score ) {
                        return $score;
                    }
                }
                return self::result_timestamp( $b ) <=> self::result_timestamp( $a );
            }
        );
        return array_values( $groups );
    }

    /**
     * Parse strictly structured Event JSON-LD. Dates without an explicit year
     * are rejected; the crawler never infers a date from today's date.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function parse_json_ld_events( string $html, string $base_url ): array {
        if ( ! preg_match_all( '~<script[^>]+type=["\']application/ld\+json["\'][^>]*>(.*?)</script>~is', substr( $html, 0, self::MAX_BODY_BYTES ), $blocks ) ) {
            return array();
        }

        $events = array();
        $walk   = static function ( mixed $value ) use ( &$walk, &$events, $base_url ): void {
            if ( ! is_array( $value ) ) {
                return;
            }

            $types = array_map( 'strtolower', array_map( 'strval', (array) ( $value['@type'] ?? array() ) ) );
            if ( in_array( 'event', $types, true ) ) {
                $title = sanitize_text_field( (string) ( $value['name'] ?? $value['headline'] ?? '' ) );
                $start = self::explicit_timestamp( (string) ( $value['startDate'] ?? '' ), true );
                if ( '' !== $title && $start > 0 ) {
                    $location = self::event_location( $value['location'] ?? array() );
                    $events[] = array(
                        'title'            => $title,
                        'url'              => self::absolute_url( (string) ( $value['url'] ?? '' ), $base_url ) ?: $base_url,
                        'start_ts'         => $start,
                        'start_raw'        => sanitize_text_field( (string) ( $value['startDate'] ?? '' ) ),
                        'end_ts'           => self::explicit_timestamp( (string) ( $value['endDate'] ?? '' ), true ),
                        'published_raw'    => sanitize_text_field( (string) ( $value['datePublished'] ?? '' ) ),
                        'published_ts'     => self::explicit_timestamp( (string) ( $value['datePublished'] ?? '' ), false ),
                        'excerpt'          => mb_substr( sanitize_textarea_field( wp_strip_all_tags( (string) ( $value['description'] ?? '' ) ) ), 0, 1600 ),
                        'location'         => $location,
                        'detection_method' => 'json_ld_event',
                        'evidence'         => 'JSON-LD Event met expliciete startDate.',
                    );
                }
            }

            foreach ( $value as $child ) {
                if ( is_array( $child ) ) {
                    $walk( $child );
                }
            }
        };

        foreach ( $blocks[1] as $raw ) {
            $json = json_decode( html_entity_decode( (string) $raw, ENT_QUOTES | ENT_HTML5, 'UTF-8' ), true );
            if ( is_array( $json ) ) {
                $walk( $json );
            }
            if ( count( $events ) >= 12 ) {
                break;
            }
        }
        return array_slice( $events, 0, 12 );
    }

    /**
     * Parse simple agenda tables only when every event row contains an
     * explicit year. This intentionally rejects the old current-year guess.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function parse_explicit_table_events( string $html, string $base_url ): array {
        if ( ! class_exists( 'DOMDocument' ) ) {
            return array();
        }

        $dom = new DOMDocument();
        libxml_use_internal_errors( true );
        @$dom->loadHTML( '<?xml encoding="utf-8" ?>' . substr( $html, 0, self::MAX_BODY_BYTES ) );
        $xpath  = new DOMXPath( $dom );
        $tables = $xpath->query( '//table' );
        $events = array();
        if ( ! $tables ) {
            return array();
        }

        foreach ( $tables as $table ) {
            $rows = $xpath->query( './/tr', $table );
            if ( ! $rows ) {
                continue;
            }
            foreach ( $rows as $row ) {
                $cells = array();
                foreach ( $row->childNodes as $node ) {
                    if ( $node instanceof DOMElement && in_array( strtolower( $node->tagName ), array( 'td', 'th' ), true ) ) {
                        $cells[] = trim( preg_replace( '/\s+/u', ' ', (string) $node->textContent ) );
                    }
                }
                if ( count( $cells ) < 2 ) {
                    continue;
                }

                $date_index = null;
                $start      = 0;
                foreach ( $cells as $index => $cell ) {
                    $candidate = self::explicit_timestamp( $cell, true );
                    if ( $candidate > 0 ) {
                        $date_index = $index;
                        $start      = $candidate;
                        break;
                    }
                }
                if ( null === $date_index || $start < self::today_start() ) {
                    continue;
                }

                $titles = $cells;
                unset( $titles[ $date_index ] );
                $title = sanitize_text_field( (string) reset( $titles ) );
                if ( '' === $title || preg_match( '/\b(afgelast|geannuleerd|cancelled|canceled)\b/iu', implode( ' ', $cells ) ) ) {
                    continue;
                }

                $events[] = array(
                    'title'            => $title,
                    'url'              => $base_url,
                    'start_ts'         => $start,
                    'start_raw'        => wp_date( DATE_ATOM, $start ),
                    'end_ts'           => 0,
                    'published_raw'    => '',
                    'published_ts'     => 0,
                    'excerpt'          => mb_substr( sanitize_textarea_field( implode( ' · ', $cells ) ), 0, 1600 ),
                    'location'         => '',
                    'detection_method' => 'html_table_explicit_year',
                    'evidence'         => 'HTML-agendatabel met expliciet jaartal.',
                );
                if ( count( $events ) >= 12 ) {
                    break 2;
                }
            }
        }
        return $events;
    }

    public static function candidate_id( int $source_id, string $url, int $start_ts, string $title ): string {
        return hash( 'sha256', $source_id . '|' . self::canonical_url( $url ) . '|' . $start_ts . '|' . self::normalize_text( $title ) );
    }

    public static function canonical_url( string $url ): string {
        $parts = wp_parse_url( trim( $url ) );
        if ( ! is_array( $parts ) || empty( $parts['host'] ) ) {
            return '';
        }

        $scheme = strtolower( (string) ( $parts['scheme'] ?? 'https' ) );
        if ( ! in_array( $scheme, array( 'http', 'https' ), true ) ) {
            return '';
        }
        $host = strtolower( (string) $parts['host'] );
        $path = '/' . ltrim( rawurldecode( (string) ( $parts['path'] ?? '/' ) ), '/' );
        $path = '/' === $path ? '/' : rtrim( $path, '/' );

        $query = array();
        if ( ! empty( $parts['query'] ) ) {
            parse_str( (string) $parts['query'], $query );
            foreach ( array_keys( $query ) as $key ) {
                if ( preg_match( '/^(utm_|fbclid$|gclid$)/i', (string) $key ) ) {
                    unset( $query[ $key ] );
                }
            }
            ksort( $query );
        }

        $port = isset( $parts['port'] ) ? ':' . absint( $parts['port'] ) : '';
        return $scheme . '://' . $host . $port . $path . ( $query ? '?' . http_build_query( $query, '', '&', PHP_QUERY_RFC3986 ) : '' );
    }

    /**
     * Validate provider JSON against a strict allowlist.
     *
     * @param string|array<string,mixed> $payload
     * @param array<int,string>          $allowed_ids
     * @return array<int,array<string,mixed>>|WP_Error
     */
    public static function validate_analysis_payload( string|array $payload, array $allowed_ids ): array|WP_Error {
        if ( is_string( $payload ) ) {
            $text = trim( preg_replace( '/^```(?:json)?\s*|\s*```$/i', '', $payload ) );
            $first = strpos( $text, '{' );
            $last  = strrpos( $text, '}' );
            if ( false !== $first && false !== $last && $last >= $first ) {
                $text = substr( $text, $first, $last - $first + 1 );
            }
            $payload = json_decode( $text, true );
        }
        if ( ! is_array( $payload ) || ! isset( $payload['items'] ) || ! is_array( $payload['items'] ) ) {
            return new WP_Error( 'mvm_agenda_ai_schema', 'De AI-respons voldoet niet aan het afgesproken schema.' );
        }

        $allowed = array_fill_keys( array_map( 'strval', $allowed_ids ), true );
        $clean   = array();
        foreach ( $payload['items'] as $item ) {
            if ( ! is_array( $item ) ) {
                continue;
            }
            $id = strtolower( sanitize_text_field( (string) ( $item['id'] ?? '' ) ) );
            if ( ! isset( $allowed[ $id ] ) || 1 !== preg_match( '/^[a-f0-9]{64}$/', $id ) ) {
                continue;
            }
            $status = sanitize_key( (string) ( $item['status'] ?? 'controleren' ) );
            if ( ! in_array( $status, array( 'publiceerbaar', 'controleren', 'niet_mierlo', 'dubbel' ), true ) ) {
                $status = 'controleren';
            }
            $confidence = is_numeric( $item['confidence'] ?? null ) ? (float) $item['confidence'] : 0.0;
            if ( $confidence > 0 && $confidence <= 1 ) {
                $confidence *= 100;
            }
            $relationship = sanitize_key( (string) ( $item['relationship_type'] ?? 'geen' ) );
            if ( ! in_array( $relationship, array( 'geen', 'eerder_bericht', 'vervolg', 'gerelateerd' ), true ) ) {
                $relationship = 'geen';
            }
            $clean[] = array(
                'id'                => $id,
                'status'            => $status,
                'category'          => 'Evenementen / Agenda',
                'summary'           => mb_substr( sanitize_textarea_field( (string) ( $item['summary'] ?? '' ) ), 0, 800 ),
                'confidence'        => max( 0, min( 100, (int) round( $confidence ) ) ),
                'reason'            => mb_substr( sanitize_textarea_field( (string) ( $item['reason'] ?? '' ) ), 0, 1000 ),
                'relationship_type' => $relationship,
            );
        }

        return $clean ?: new WP_Error( 'mvm_agenda_ai_empty', 'De AI-respons bevat geen bruikbare agendaresultaten.' );
    }

    /** @return array{active:int,stopped:int} */
    public static function source_counts(): array {
        global $wpdb;
        $table = MvM_Hub4_Sources::table_name();
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed internal table; read-only counts.
        $row = $wpdb->get_row(
            "SELECT
                SUM(CASE WHEN monitor_enabled = 1 AND status = 'active' THEN 1 ELSE 0 END) AS active_count,
                SUM(CASE WHEN monitor_enabled = 0 AND status = 'stopped' THEN 1 ELSE 0 END) AS stopped_count
             FROM {$table}",
            ARRAY_A
        );
        return array(
            'active'  => absint( is_array( $row ) ? ( $row['active_count'] ?? 0 ) : 0 ),
            'stopped' => absint( is_array( $row ) ? ( $row['stopped_count'] ?? 0 ) : 0 ),
        );
    }

    /** @return array{pending:int,blocked:bool,status:string,message:string} */
    public static function provider_health(): array {
        $pending = 0;
        foreach ( self::result_rows() as $row ) {
            if ( is_array( $row ) && ! empty( $row['ai_pending'] ) && empty( $row['dismissed'] ) ) {
                $pending++;
            }
        }

        $state   = get_option( self::OPTION_PROVIDER, array() );
        $state   = is_array( $state ) ? $state : array();
        $last_ok = absint( $state['last_ok'] ?? 0 );
        $blocked = $pending >= self::MAX_PENDING_AI;
        $status  = $blocked ? 'backlog' : ( $last_ok > time() - HOUR_IN_SECONDS ? 'ok' : 'unknown' );
        $message = $blocked
            ? sprintf( '%d items wachten op AI; limiet is %d.', $pending, self::MAX_PENDING_AI )
            : sanitize_text_field( (string) ( $state['message'] ?? 'Nog geen recente bronvaste providerstatus.' ) );

        return array(
            'pending' => $pending,
            'blocked' => $blocked,
            'status'  => $status,
            'message' => $message,
        );
    }

    /** @param array<string,mixed> $source @param array<string,int|bool> $summary */
    private static function scan_source( array $source, array &$summary ): void {
        $summary['checked']++;
        $document = self::fetch_document( (string) $source['url'] );
        if ( is_wp_error( $document ) ) {
            $summary['failed']++;
            return;
        }

        $event_base = (string) $source['url'];
        $events     = self::parse_json_ld_events( $document, $event_base );
        $mode       = 'structured';

        if ( ! $events ) {
            $agenda_url = self::discover_agenda_url( $document, $event_base );
            if ( $agenda_url && self::canonical_url( $agenda_url ) !== self::canonical_url( $event_base ) ) {
                $summary['agendaPages']++;
                $agenda = self::fetch_document( $agenda_url );
                if ( ! is_wp_error( $agenda ) ) {
                    $event_base = $agenda_url;
                    $events     = self::parse_json_ld_events( $agenda, $event_base );
                    if ( ! $events ) {
                        $events = self::parse_explicit_table_events( $agenda, $event_base );
                        $mode   = 'table';
                    }
                }
            }
        }

        if ( $events ) {
            if ( 'table' === $mode ) {
                $summary['tableEvents'] += count( $events );
            } else {
                $summary['structuredEvents'] += count( $events );
            }
        }

        foreach ( array_slice( $events, 0, self::MAX_EVENTS_SOURCE ) as $event ) {
            $candidate = self::build_candidate( $source, $event );
            if ( is_wp_error( $candidate ) ) {
                continue;
            }
            $stored = self::upsert_candidate( $candidate );
            if ( is_wp_error( $stored ) ) {
                $summary['failed']++;
            } elseif ( 'added' === $stored ) {
                $summary['added']++;
            } elseif ( 'updated' === $stored ) {
                $summary['updated']++;
            } else {
                $summary['duplicates']++;
            }
        }
    }

    /** @return array<int,array<string,mixed>> */
    private static function active_sources(): array {
        global $wpdb;
        $table = MvM_Hub4_Sources::table_name();
        $key   = '_mvm_newsradar_legacy_source_id';
        $sql   = $wpdb->prepare(
            "SELECT p.ID, p.post_title, p.post_content, s.category, pm.meta_value AS legacy_id
             FROM {$table} s
             INNER JOIN {$wpdb->posts} p ON p.ID = s.source_post_id
             LEFT JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = %s
             WHERE s.monitor_enabled = 1 AND s.status = 'active'
               AND p.post_type = 'mvm_bron' AND p.post_status = 'publish'
             ORDER BY p.ID ASC",
            $key
        );
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- value prepared above.
        $rows = $wpdb->get_results( $sql, ARRAY_A );
        $rows = is_array( $rows ) ? $rows : array();

        $legacy_urls = array();
        $legacy      = get_option( 'mvm_bh_sources', array() );
        foreach ( is_array( $legacy ) ? $legacy : array() as $source ) {
            if ( is_array( $source ) && ! empty( $source['id'] ) && ! empty( $source['url'] ) ) {
                $legacy_urls[ absint( $source['id'] ) ] = esc_url_raw( (string) $source['url'] );
            }
        }

        $sources = array();
        foreach ( $rows as $row ) {
            $legacy_id = absint( $row['legacy_id'] ?? 0 );
            $url       = $legacy_urls[ $legacy_id ] ?? self::extract_source_url( (string) ( $row['post_content'] ?? '' ) );
            $url       = self::canonical_url( $url );
            if ( ! $url || ! wp_http_validate_url( $url ) ) {
                continue;
            }
            $sources[] = array(
                'id'        => $legacy_id ?: absint( $row['ID'] ?? 0 ),
                'post_id'   => absint( $row['ID'] ?? 0 ),
                'name'      => sanitize_text_field( (string) ( $row['post_title'] ?? '' ) ),
                'url'       => $url,
                'category'  => sanitize_key( (string) ( $row['category'] ?? 'overig' ) ),
            );
        }
        return $sources;
    }

    private static function fetch_document( string $url ): string|WP_Error {
        $url = self::canonical_url( $url );
        if ( ! $url || ! wp_http_validate_url( $url ) ) {
            return new WP_Error( 'mvm_agenda_unsafe_url', 'De bron-URL is niet veilig.' );
        }
        $response = wp_safe_remote_get(
            $url,
            array(
                'timeout'             => 8,
                'redirection'         => 3,
                'reject_unsafe_urls'  => true,
                'limit_response_size' => self::MAX_BODY_BYTES,
                'user-agent'          => 'MvM-Hub-AgendaCrawler/2.0 (+' . home_url( '/' ) . ')',
                'headers'             => array( 'Accept' => 'text/html,application/xhtml+xml' ),
            )
        );
        if ( is_wp_error( $response ) ) {
            return $response;
        }
        $code = (int) wp_remote_retrieve_response_code( $response );
        if ( $code < 200 || $code >= 400 ) {
            return new WP_Error( 'mvm_agenda_http', 'De bron gaf geen bruikbare HTTP-respons.' );
        }
        $content_type = strtolower( (string) wp_remote_retrieve_header( $response, 'content-type' ) );
        if ( '' !== $content_type && ! str_contains( $content_type, 'text/html' ) && ! str_contains( $content_type, 'application/xhtml+xml' ) ) {
            return new WP_Error( 'mvm_agenda_content_type', 'De bron leverde geen HTML.' );
        }
        $body = (string) wp_remote_retrieve_body( $response );
        return '' !== $body ? substr( $body, 0, self::MAX_BODY_BYTES ) : new WP_Error( 'mvm_agenda_empty', 'De bronpagina is leeg.' );
    }

    private static function discover_agenda_url( string $html, string $base_url ): string {
        if ( ! class_exists( 'DOMDocument' ) ) {
            return '';
        }
        $dom = new DOMDocument();
        libxml_use_internal_errors( true );
        @$dom->loadHTML( '<?xml encoding="utf-8" ?>' . substr( $html, 0, 650000 ) );
        $links = ( new DOMXPath( $dom ) )->query( '//a[@href]' );
        if ( ! $links ) {
            return '';
        }

        $candidates = array();
        foreach ( $links as $link ) {
            $href = trim( (string) $link->getAttribute( 'href' ) );
            $text = self::normalize_text( (string) $link->textContent );
            $url  = self::absolute_url( $href, $base_url );
            if ( ! $url || ! wp_http_validate_url( $url ) ) {
                continue;
            }
            $path  = self::normalize_text( (string) wp_parse_url( $url, PHP_URL_PATH ) );
            $score = 0;
            if ( preg_match( '/\b(agenda|kalender)\b/', $path ) ) {
                $score += 120;
            } elseif ( preg_match( '/\b(evenement|evenementen|event|events)\b/', $path ) ) {
                $score += 105;
            } elseif ( preg_match( '/\bactiviteiten\b/', $path ) ) {
                $score += 70;
            }
            if ( preg_match( '/^(agenda|kalender|evenementen|events|activiteitenkalender)$/', $text ) ) {
                $score += 80;
            }
            if ( preg_match( '/\b(nieuws|artikel|product|shop|hulpvraag)\b/', $path ) ) {
                $score -= 150;
            }
            if ( $score > 0 ) {
                $candidates[] = array( 'url' => $url, 'score' => $score, 'length' => strlen( $path ) );
            }
        }
        usort( $candidates, static fn( array $a, array $b ): int => $b['score'] <=> $a['score'] ?: $a['length'] <=> $b['length'] );
        return $candidates ? esc_url_raw( (string) $candidates[0]['url'] ) : '';
    }

    private static function absolute_url( string $url, string $base_url ): string {
        $url = trim( $url );
        if ( '' === $url || str_starts_with( $url, '#' ) || preg_match( '/^(?:mailto|tel|javascript):/i', $url ) ) {
            return '';
        }
        if ( preg_match( '#^https?://#i', $url ) ) {
            return self::canonical_url( $url );
        }
        $base = wp_parse_url( $base_url );
        if ( ! is_array( $base ) || empty( $base['host'] ) ) {
            return '';
        }
        $root = ( $base['scheme'] ?? 'https' ) . '://' . $base['host'] . ( isset( $base['port'] ) ? ':' . absint( $base['port'] ) : '' );
        if ( str_starts_with( $url, '//' ) ) {
            return self::canonical_url( ( $base['scheme'] ?? 'https' ) . ':' . $url );
        }
        if ( str_starts_with( $url, '/' ) ) {
            return self::canonical_url( $root . $url );
        }
        $path = (string) ( $base['path'] ?? '/' );
        $dir  = preg_replace( '#/[^/]*$#', '/', $path );
        return self::canonical_url( $root . $dir . $url );
    }

    /** @param array<string,mixed> $source @param array<string,mixed> $event */
    private static function build_candidate( array $source, array $event ): array|WP_Error {
        $url      = self::canonical_url( (string) ( $event['url'] ?? $source['url'] ?? '' ) );
        $title    = sanitize_text_field( (string) ( $event['title'] ?? '' ) );
        $start_ts = absint( $event['start_ts'] ?? 0 );
        if ( ! $url || '' === $title || $start_ts < self::today_start() ) {
            return new WP_Error( 'mvm_agenda_invalid_event', 'Het agenda-item bevat onvoldoende expliciete brongegevens.' );
        }

        $published_ts  = absint( $event['published_ts'] ?? 0 );
        $published_raw = sanitize_text_field( (string) ( $event['published_raw'] ?? '' ) );
        if ( ! $published_ts && self::canonical_url( $url ) !== self::canonical_url( (string) $source['url'] ) ) {
            $detail = self::fetch_document( $url );
            if ( ! is_wp_error( $detail ) ) {
                $published     = self::published_metadata( $detail );
                $published_ts  = (int) $published['ts'];
                $published_raw = (string) $published['raw'];
            }
        }

        $now = time();
        $id  = self::candidate_id( absint( $source['id'] ?? 0 ), $url, $start_ts, $title );
        return array(
            'id'                => $id,
            'track_id'          => $id,
            'source_id'         => absint( $source['id'] ?? 0 ),
            'source'            => sanitize_text_field( (string) ( $source['name'] ?? '' ) ),
            'source_url'        => esc_url_raw( (string) ( $source['url'] ?? '' ) ),
            'title'             => $title,
            'url'               => $url,
            'published'         => $published_raw,
            'published_ts'      => $published_ts,
            'excerpt'           => mb_substr( sanitize_textarea_field( (string) ( $event['excerpt'] ?? '' ) ), 0, 3000 ),
            'previous_excerpt'  => '',
            'event_type'        => 'nieuw',
            'found_at'          => wp_date( DATE_ATOM, $now ),
            'found_ts'          => $now,
            'local_score'       => 0,
            'ai_pending'        => 1,
            'status'            => 'controleren',
            'category'          => 'Evenementen / Agenda',
            'summary'           => '',
            'confidence'        => 0,
            'reason'            => 'Agenda-item met expliciete evenementdatum gevonden; wacht op redactionele AI-beoordeling.',
            'change_relevant'   => null,
            'change_summary'    => '',
            'date_issues'       => $published_ts ? array() : array( 'Publicatiedatum kon niet betrouwbaar worden vastgesteld.' ),
            'relative_terms'    => array(),
            'date_source'       => $published_ts ? 'expliciete bronmetadata' : 'niet gevonden',
            'date_confidence'   => $published_ts ? 95 : 0,
            'date_checked_at'   => wp_date( DATE_ATOM, $now ),
            'reviewed'          => 0,
            'dismissed'         => 0,
            'email_pending'     => 0,
            'email_sent'        => 0,
            'lifecycle'         => 'actief',
            'related_post_id'   => 0,
            'relationship_type' => 'geen',
            'agenda_start_ts'   => $start_ts,
            'agenda_date'       => wp_date( 'Y-m-d', $start_ts ),
            'agenda_location'   => sanitize_text_field( (string) ( $event['location'] ?? '' ) ),
            'detection_method'  => sanitize_key( (string) ( $event['detection_method'] ?? 'structured' ) ),
            'evidence'          => mb_substr( sanitize_text_field( (string) ( $event['evidence'] ?? 'Expliciete bronagenda.' ) ), 0, 500 ),
            'ai_provider'       => '',
            'ai_checked_at'     => '',
        );
    }

    /** @param array<string,mixed> $candidate @return string|WP_Error */
    private static function upsert_candidate( array $candidate ): string|WP_Error {
        $rows = self::result_rows();
        foreach ( $rows as $index => $row ) {
            if ( ! is_array( $row ) ) {
                continue;
            }
            $same_id   = hash_equals( (string) ( $row['id'] ?? '' ), (string) $candidate['id'] );
            $same_url  = self::canonical_url( (string) ( $row['url'] ?? '' ) ) === self::canonical_url( (string) $candidate['url'] );
            $same_date = absint( $row['agenda_start_ts'] ?? 0 ) === absint( $candidate['agenda_start_ts'] );
            $same_name = self::normalize_text( (string) ( $row['title'] ?? '' ) ) === self::normalize_text( (string) $candidate['title'] );
            if ( ! $same_id && ! ( $same_url && $same_date ) && ! ( $same_date && $same_name && absint( $row['source_id'] ?? 0 ) === absint( $candidate['source_id'] ) ) ) {
                continue;
            }

            if ( empty( $row['published_ts'] ) && ! empty( $candidate['published_ts'] ) ) {
                $expected                    = $rows;
                $rows[ $index ]['published'] = $candidate['published'];
                $rows[ $index ]['published_ts'] = $candidate['published_ts'];
                $rows[ $index ]['date_source'] = $candidate['date_source'];
                $rows[ $index ]['date_confidence'] = $candidate['date_confidence'];
                $saved = self::persist_results( $expected, $rows );
                return is_wp_error( $saved ) ? $saved : 'updated';
            }
            return 'duplicate';
        }

        $expected = $rows;
        array_unshift( $rows, $candidate );
        $settings = get_option( 'mvm_bh_news_radar_settings', array() );
        $keep     = max( 100, min( 1000, absint( is_array( $settings ) ? ( $settings['retention'] ?? 500 ) : 500 ) ) );
        $saved    = self::persist_results( $expected, array_slice( $rows, 0, $keep ) );
        return is_wp_error( $saved ) ? $saved : 'added';
    }

    /** @return array{raw:string,ts:int} */
    private static function published_metadata( string $html ): array {
        $candidates = array();
        if ( preg_match_all( '~<meta\b[^>]*>~i', substr( $html, 0, 450000 ), $tags ) ) {
            foreach ( $tags[0] as $tag ) {
                if ( ! preg_match( '/(?:property|name|itemprop)=["\']([^"\']+)["\']/i', $tag, $key ) || ! preg_match( '/content=["\']([^"\']+)["\']/i', $tag, $value ) ) {
                    continue;
                }
                if ( in_array( strtolower( $key[1] ), array( 'article:published_time', 'datepublished', 'pubdate', 'publishdate' ), true ) ) {
                    $ts = self::explicit_timestamp( (string) $value[1], false );
                    if ( $ts > 0 ) {
                        $candidates[] = array( 'raw' => sanitize_text_field( (string) $value[1] ), 'ts' => $ts );
                    }
                }
            }
        }
        return $candidates ? $candidates[0] : array( 'raw' => '', 'ts' => 0 );
    }

    /** @return array<int,array<string,mixed>> */
    private static function result_rows(): array {
        $rows = get_option( self::OPTION_RESULTS, array() );
        return is_array( $rows ) ? $rows : array();
    }

    /** @param array<int,mixed> $expected @param array<int,mixed> $new_rows @return true|WP_Error */
    private static function persist_results( array $expected, array $new_rows ): true|WP_Error {
        global $wpdb;
        if ( isset( $wpdb->options ) && method_exists( $wpdb, 'query' ) && function_exists( 'maybe_serialize' ) ) {
            $sql = $wpdb->prepare(
                "UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND option_value = %s",
                maybe_serialize( $new_rows ),
                self::OPTION_RESULTS,
                maybe_serialize( $expected )
            );
            $affected = $wpdb->query( $sql );
            if ( 1 === $affected ) {
                wp_cache_delete( self::OPTION_RESULTS, 'options' );
                wp_cache_delete( 'alloptions', 'options' );
                return true;
            }
            return new WP_Error( 'mvm_agenda_conflict', 'De Nieuwsradar veranderde tijdens de crawl; de kandidaat is niet overschreven.', array( 'status' => 409 ) );
        }
        return update_option( self::OPTION_RESULTS, $new_rows, false )
            ? true
            : new WP_Error( 'mvm_agenda_write_failed', 'De Radarkandidaat kon niet veilig worden opgeslagen.' );
    }

    /** @param array<int,array<string,mixed>> $pending */
    private static function analysis_prompt( array $pending ): string {
        $items = array();
        foreach ( $pending as $row ) {
            $items[] = array(
                'id'          => (string) ( $row['id'] ?? '' ),
                'source'      => (string) ( $row['source'] ?? '' ),
                'title'       => (string) ( $row['title'] ?? '' ),
                'agenda_date' => (string) ( $row['agenda_date'] ?? '' ),
                'location'    => (string) ( $row['agenda_location'] ?? '' ),
                'excerpt'     => mb_substr( (string) ( $row['excerpt'] ?? '' ), 0, 1800 ),
                'evidence'    => (string) ( $row['evidence'] ?? '' ),
            );
        }
        $schema = array(
            'items' => array(
                array(
                    'id'                => 'exact aangeleverd id',
                    'status'            => 'publiceerbaar|controleren|niet_mierlo|dubbel',
                    'summary'           => 'korte Nederlandse samenvatting',
                    'confidence'        => 80,
                    'reason'            => 'korte reden',
                    'relationship_type' => 'geen|eerder_bericht|vervolg|gerelateerd',
                ),
            ),
        );
        return 'Je ondersteunt de redactie van Mierlo voor Mierlo. Beoordeel alleen lokale relevantie en duidelijkheid. '
            . 'De expliciete agenda_date is de evenementdatum, niet de publicatiedatum. Verzin geen feiten en publiceer niets. '
            . 'Geef uitsluitend geldige JSON volgens dit schema: ' . wp_json_encode( $schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES )
            . "\nItems: " . wp_json_encode( $items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
    }

    private static function generate_analysis_text( string $prompt ): string|WP_Error {
        $injected = apply_filters( 'mvm_hub4_ai_agenda_generate_text', null, $prompt );
        if ( is_string( $injected ) && '' !== trim( $injected ) ) {
            return trim( $injected );
        }
        if ( ! class_exists( 'WordPress\\AiClient\\AiClient' ) ) {
            return new WP_Error( 'mvm_agenda_ai_unavailable', 'De WordPress AI Client is niet beschikbaar.' );
        }

        $models = apply_filters(
            'mvm_hub4_ai_agenda_models',
            array(
                array( 'google', 'gemini-3.5-flash-lite' ),
                array( 'openai', 'gpt-5-mini' ),
            )
        );
        foreach ( is_array( $models ) ? $models : array() as $preference ) {
            if ( ! is_array( $preference ) || count( $preference ) !== 2 ) {
                continue;
            }
            try {
                $result = \WordPress\AiClient\AiClient::prompt( $prompt )
                    ->usingModelPreference( array_map( 'sanitize_key', $preference ) )
                    ->generateTextResult();
                $text = method_exists( $result, 'toText' ) ? trim( (string) $result->toText() ) : '';
                if ( '' !== $text ) {
                    return $text;
                }
            } catch ( Throwable $error ) {
                unset( $error );
            }
        }
        return new WP_Error( 'mvm_agenda_ai_request', 'Geen geconfigureerde AI-provider kon de agenda-items beoordelen.' );
    }

    /** @param array<int,array<string,mixed>> $analysis */
    private static function apply_analysis( array $analysis ): int {
        $rows     = self::result_rows();
        $expected = $rows;
        $map      = array();
        foreach ( $analysis as $item ) {
            $map[ (string) $item['id'] ] = $item;
        }
        $processed = 0;
        foreach ( $rows as &$row ) {
            $id = (string) ( is_array( $row ) ? ( $row['id'] ?? '' ) : '' );
            if ( ! isset( $map[ $id ] ) ) {
                continue;
            }
            $item                     = $map[ $id ];
            $row['status']            = $item['status'];
            $row['category']          = 'Evenementen / Agenda';
            $row['summary']           = $item['summary'];
            $row['confidence']        = $item['confidence'];
            $row['reason']            = $item['reason'];
            $row['relationship_type'] = $item['relationship_type'];
            $row['ai_pending']        = 0;
            $row['ai_provider']       = 'wordpress-ai-client';
            $row['ai_checked_at']     = wp_date( DATE_ATOM );
            $processed++;
        }
        unset( $row );
        if ( $processed > 0 ) {
            $saved = self::persist_results( $expected, $rows );
            return is_wp_error( $saved ) ? 0 : $processed;
        }
        return 0;
    }

    private static function record_provider_state( bool $ok, string $message ): void {
        $previous = get_option( self::OPTION_PROVIDER, array() );
        $previous = is_array( $previous ) ? $previous : array();
        update_option(
            self::OPTION_PROVIDER,
            array(
                'ok'      => $ok,
                'time'    => time(),
                'last_ok' => $ok ? time() : absint( $previous['last_ok'] ?? 0 ),
                'message' => sanitize_text_field( $message ),
            ),
            false
        );
    }

    private static function schedule_analysis_retry( int $delay ): void {
        if ( ! wp_next_scheduled( self::ANALYSIS_HOOK ) ) {
            wp_schedule_single_event( time() + max( 20, min( 300, $delay ) ), self::ANALYSIS_HOOK );
        }
    }

    /** @return array<string,mixed>|WP_Error|null */
    private static function delegate_legacy_run(): array|WP_Error|null {
        foreach ( array( 'mvm_ai_agenda_scan_v7', 'mvm_ai_agenda_scan_v6', 'mvm_ai_agenda_scan_v4', 'mvm_ai_agenda_scan_v3', 'mvm_ai_agenda_scan_v2', 'mvm_ai_agenda_scan_v1' ) as $function ) {
            if ( function_exists( $function ) ) {
                $result = call_user_func( $function );
                if ( is_wp_error( $result ) ) {
                    return $result;
                }
                return is_array( $result ) ? $result : array( 'added' => 0, 'message' => 'De compatibiliteitscrawl gaf geen geldig resultaat.' );
            }
        }
        return null;
    }

    private static function legacy_owner_active(): bool {
        foreach ( array( 'mvm_ai_agenda_settings_v1', 'mvm_ai_agenda_scan_v7', 'mvm_ai_agenda_scan_v3', 'mvm_ai_agenda_scan_v2', 'mvm_ai_agenda_scan_v1' ) as $function ) {
            if ( function_exists( $function ) ) {
                return true;
            }
        }
        return false;
    }

    private static function legacy_analysis_owner_active(): bool {
        return function_exists( 'mvm_newsradar_agenda_ai_hook_v1' );
    }

    private static function acquire_lock( string $option, int $ttl ): bool {
        $now = time();
        if ( add_option( $option, array( 'created' => $now, 'expires' => $now + $ttl ), '', false ) ) {
            return true;
        }
        $lock    = get_option( $option, array() );
        $expires = is_array( $lock ) ? absint( $lock['expires'] ?? 0 ) : 0;
        if ( $expires > 0 && $expires < $now ) {
            delete_option( $option );
            return add_option( $option, array( 'created' => $now, 'expires' => $now + $ttl ), '', false );
        }
        return false;
    }

    private static function release_lock( string $option ): void {
        delete_option( $option );
    }

    private static function result_timestamp( array $row ): int {
        $published = absint( $row['published_ts'] ?? 0 );
        if ( $published > 0 ) {
            return $published;
        }
        $found = absint( $row['found_ts'] ?? 0 );
        return $found > 0 ? $found : ( strtotime( (string) ( $row['found_at'] ?? '' ) ) ?: 0 );
    }

    private static function explicit_timestamp( string $raw, bool $event_date ): int {
        $raw = trim( html_entity_decode( $raw, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
        if ( '' === $raw || ! preg_match( '/\b(20\d{2})\b/', $raw ) ) {
            return 0;
        }

        $months = array(
            'januari' => '01', 'februari' => '02', 'maart' => '03', 'april' => '04', 'mei' => '05', 'juni' => '06',
            'juli' => '07', 'augustus' => '08', 'september' => '09', 'oktober' => '10', 'november' => '11', 'december' => '12',
        );
        $normalized = strtolower( function_exists( 'remove_accents' ) ? remove_accents( $raw ) : $raw );
        foreach ( $months as $name => $number ) {
            $normalized = preg_replace( '/\b' . preg_quote( $name, '/' ) . '\b/u', $number, $normalized );
        }
        if ( preg_match( '/\b([0-3]?\d)[-\/\s](0?[1-9]|1[0-2])[-\/\s](20\d{2})(?:\s+(?:om\s+)?([0-2]?\d)[:.]([0-5]\d))?/u', $normalized, $match ) ) {
            $normalized = sprintf( '%04d-%02d-%02dT%02d:%02d:00', $match[3], $match[2], $match[1], $match[4] ?? 0, $match[5] ?? 0 );
        }

        try {
            $date = new DateTimeImmutable( $normalized, wp_timezone() );
        } catch ( Throwable $error ) {
            unset( $error );
            return 0;
        }
        $timestamp = $date->getTimestamp();
        if ( $event_date ) {
            return $timestamp >= self::today_start() ? $timestamp : 0;
        }
        return $timestamp <= time() + DAY_IN_SECONDS ? $timestamp : 0;
    }

    private static function today_start(): int {
        return ( new DateTimeImmutable( 'today', wp_timezone() ) )->getTimestamp();
    }

    private static function event_location( mixed $location ): string {
        if ( is_string( $location ) ) {
            return sanitize_text_field( $location );
        }
        if ( ! is_array( $location ) ) {
            return '';
        }
        $parts = array();
        if ( ! empty( $location['name'] ) ) {
            $parts[] = (string) $location['name'];
        }
        $address = $location['address'] ?? array();
        if ( is_string( $address ) ) {
            $parts[] = $address;
        } elseif ( is_array( $address ) ) {
            foreach ( array( 'streetAddress', 'postalCode', 'addressLocality' ) as $key ) {
                if ( ! empty( $address[ $key ] ) ) {
                    $parts[] = (string) $address[ $key ];
                }
            }
        }
        return mb_substr( sanitize_text_field( implode( ', ', array_unique( array_filter( $parts ) ) ) ), 0, 300 );
    }

    private static function extract_source_url( string $content ): string {
        $text = html_entity_decode( wp_strip_all_tags( $content ), ENT_QUOTES, 'UTF-8' );
        if ( preg_match( '/\bURL\s*:\s*(https?:\/\/[^\s<>"\']+)/iu', $text, $match ) ) {
            return esc_url_raw( rtrim( $match[1], '.,);]' ) );
        }
        if ( preg_match( '/https?:\/\/[^\s<>"\']+/iu', $text, $match ) ) {
            return esc_url_raw( rtrim( $match[0], '.,);]' ) );
        }
        return '';
    }

    private static function normalize_text( string $value ): string {
        if ( function_exists( 'remove_accents' ) ) {
            $value = remove_accents( $value );
        }
        $value = strtolower( html_entity_decode( wp_strip_all_tags( $value ), ENT_QUOTES, 'UTF-8' ) );
        $value = preg_replace( '/[^a-z0-9]+/', ' ', $value );
        return trim( preg_replace( '/\s+/', ' ', (string) $value ) );
    }
}
