<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Adopts the 118 legacy Nieuwsradar sources into the canonical Hub 1.1 source
 * registry without deleting or rewriting mvm_bh_sources.
 */
final class MvM_Hub4_Newsradar_Source_Adoption {
    public const MIGRATION_OPTION = 'mvm_newsradar_source_adoption_v1';
    public const LEGACY_ID_META   = '_mvm_newsradar_legacy_source_id';
    public const SOURCE_META      = '_mvm_newsradar_source';

    public static function init(): void {
        add_action( 'init', array( __CLASS__, 'maybe_adopt' ), 45 );
        add_action( 'pre_get_posts', array( __CLASS__, 'hide_monitoring_sources_from_public_queries' ), 20 );
    }

    public static function maybe_adopt(): void {
        $migration = get_option( self::MIGRATION_OPTION, 0 );
        if ( ( is_array( $migration ) && (int) ( $migration['version'] ?? 0 ) >= 1 ) || 1 === (int) $migration ) {
            return;
        }

        $sources = get_option( MvM_Hub4_Legacy_Newsradar::OPTION_SOURCES, array() );
        if ( ! is_array( $sources ) || ! $sources ) {
            return;
        }

        $active = array_values(
            array_filter(
                $sources,
                static fn( $source ): bool => is_array( $source ) && ! empty( $source['active'] ) && empty( $source['excluded'] )
            )
        );
        $slot_seconds = $active ? max( 300, (int) floor( DAY_IN_SECONDS / count( $active ) ) ) : DAY_IN_SECONDS;
        $base         = self::initial_schedule_base();
        $slot_index   = 0;
        $source_state = get_option( 'mvm_bh_news_source_state', array() );
        $source_state = is_array( $source_state ) ? $source_state : array();
        $created      = 0;
        $adopted      = 0;

        foreach ( $sources as $source ) {
            if ( ! is_array( $source ) ) {
                continue;
            }

            $legacy_id = absint( $source['id'] ?? 0 );
            $name      = sanitize_text_field( (string) ( $source['name'] ?? '' ) );
            $url       = esc_url_raw( (string) ( $source['url'] ?? '' ) );
            if ( ! $legacy_id || '' === $name || '' === $url ) {
                continue;
            }

            $post_id = self::find_adopted_post( $legacy_id );
            if ( ! $post_id ) {
                $post_id = wp_insert_post(
                    array(
                        'post_type'    => 'mvm_bron',
                        'post_status'  => 'publish',
                        'post_title'   => $name,
                        'post_name'    => 'nieuwsbron-' . $legacy_id . '-' . sanitize_title( $name ),
                        'post_content' => self::build_post_content( $source ),
                    ),
                    true
                );
                if ( is_wp_error( $post_id ) ) {
                    continue;
                }
                $created++;
            }

            self::persist_legacy_meta( (int) $post_id, $source );

            $enabled = ! empty( $source['active'] ) && empty( $source['excluded'] );
            $state   = isset( $source_state[ $legacy_id ] ) && is_array( $source_state[ $legacy_id ] ) ? $source_state[ $legacy_id ] : array();
            $checked = ! empty( $state['checked_ts'] ) ? gmdate( 'Y-m-d H:i:s', (int) $state['checked_ts'] ) : self::legacy_date_to_utc( (string) ( $source['last_checked'] ?? '' ) );
            $next    = null;

            if ( $enabled ) {
                $next = gmdate( 'Y-m-d H:i:s', $base + ( $slot_index * $slot_seconds ) );
                $slot_index++;
            }

            global $wpdb;
            $wpdb->replace(
                MvM_Hub4_Sources::table_name(),
                array(
                    'source_post_id'   => (int) $post_id,
                    'monitor_enabled'  => $enabled ? 1 : 0,
                    'category'         => self::map_category( (string) ( $source['category'] ?? '' ) ),
                    'frequency'        => 'daily',
                    'status'           => $enabled ? 'active' : ( ! empty( $source['excluded'] ) ? 'stopped' : 'paused' ),
                    'last_checked_utc' => $checked,
                    'last_checked_by'  => 0,
                    'next_check_utc'   => $next,
                    'private_note'     => self::private_note( $source ),
                    'updated_at_utc'   => current_time( 'mysql', true ),
                ),
                array( '%d', '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s' )
            );
            $adopted++;
        }

        if ( $adopted > 0 ) {
            update_option(
                self::MIGRATION_OPTION,
                array(
                    'version'   => 1,
                    'adopted'   => $adopted,
                    'created'   => $created,
                    'completed' => current_time( 'mysql', true ),
                ),
                false
            );
        }
    }

    /**
     * Monitoring records use mvm_bron because the Hub source repository already
     * owns that contract. They are operational records, not encyclopaedia source
     * pages, so keep them out of public mvm_bron archive/custom queries.
     */
    public static function hide_monitoring_sources_from_public_queries( WP_Query $query ): void {
        if ( is_admin() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
            return;
        }

        $post_type = $query->get( 'post_type' );
        $is_bron   = $query->is_post_type_archive( 'mvm_bron' )
            || 'mvm_bron' === $post_type
            || ( is_array( $post_type ) && in_array( 'mvm_bron', $post_type, true ) );
        if ( ! $is_bron ) {
            return;
        }

        $meta_query   = (array) $query->get( 'meta_query' );
        $meta_query[] = array(
            'key'     => self::SOURCE_META,
            'compare' => 'NOT EXISTS',
        );
        $query->set( 'meta_query', $meta_query );
    }

    public static function legacy_id_for_post( int $post_id ): int {
        return absint( get_post_meta( $post_id, self::LEGACY_ID_META, true ) );
    }

    private static function find_adopted_post( int $legacy_id ): int {
        $ids = get_posts(
            array(
                'post_type'              => 'mvm_bron',
                'post_status'            => 'any',
                'fields'                 => 'ids',
                'posts_per_page'         => 1,
                'no_found_rows'          => true,
                'suppress_filters'       => true,
                'meta_key'               => self::LEGACY_ID_META,
                'meta_value'             => (string) $legacy_id,
                'update_post_meta_cache' => false,
                'update_post_term_cache' => false,
            )
        );
        return $ids ? absint( $ids[0] ) : 0;
    }

    private static function persist_legacy_meta( int $post_id, array $source ): void {
        update_post_meta( $post_id, self::LEGACY_ID_META, absint( $source['id'] ?? 0 ) );
        update_post_meta( $post_id, self::SOURCE_META, 1 );
        update_post_meta( $post_id, '_mvm_newsradar_priority', sanitize_text_field( (string) ( $source['priority'] ?? '' ) ) );
        update_post_meta( $post_id, '_mvm_newsradar_usage', sanitize_textarea_field( (string) ( $source['usage'] ?? '' ) ) );
        update_post_meta( $post_id, '_mvm_newsradar_original_category', sanitize_text_field( (string) ( $source['category'] ?? '' ) ) );
        update_post_meta( $post_id, '_mvm_newsradar_original_frequency', sanitize_text_field( (string) ( $source['frequency'] ?? '' ) ) );
        update_post_meta( $post_id, '_mvm_newsradar_social', esc_url_raw( (string) ( $source['social'] ?? '' ) ) );
        update_post_meta( $post_id, '_mvm_newsradar_web_checked', sanitize_text_field( (string) ( $source['web_checked'] ?? '' ) ) );
        update_post_meta( $post_id, '_mvm_newsradar_legacy_last_checked', sanitize_text_field( (string) ( $source['last_checked'] ?? '' ) ) );
        update_post_meta( $post_id, '_mvm_newsradar_legacy_next_check', sanitize_text_field( (string) ( $source['next_check'] ?? '' ) ) );
        update_post_meta( $post_id, '_mvm_newsradar_excluded', ! empty( $source['excluded'] ) ? 1 : 0 );
        update_post_meta( $post_id, '_mvm_newsradar_active', ! empty( $source['active'] ) ? 1 : 0 );
        update_post_meta( $post_id, '_yoast_wpseo_meta-robots-noindex', '1' );
    }

    private static function build_post_content( array $source ): string {
        $rows = array(
            'URL'                     => esc_url( (string) ( $source['url'] ?? '' ) ),
            'Gebruik'                 => sanitize_text_field( (string) ( $source['usage'] ?? '' ) ),
            'Categorie'               => sanitize_text_field( (string) ( $source['category'] ?? '' ) ),
            'Prioriteit'              => sanitize_text_field( (string) ( $source['priority'] ?? '' ) ),
            'Oude controlefrequentie' => sanitize_text_field( (string) ( $source['frequency'] ?? '' ) ),
        );
        if ( ! empty( $source['social'] ) ) {
            $rows['Sociaal kanaal'] = esc_url( (string) $source['social'] );
        }

        $html = '<p><strong>Nieuwsradar-bron.</strong> Geadopteerd uit het bestaande MvM-bronregister.</p>';
        foreach ( $rows as $label => $value ) {
            if ( '' !== $value ) {
                $html .= '<p><strong>' . esc_html( $label ) . ':</strong> ' . esc_html( $value ) . '</p>';
            }
        }
        return $html;
    }

    private static function private_note( array $source ): string {
        $parts = array();
        if ( ! empty( $source['note'] ) ) {
            $parts[] = sanitize_textarea_field( (string) $source['note'] );
        }
        $parts[] = 'Legacy Nieuwsradar-ID: ' . absint( $source['id'] ?? 0 );
        $parts[] = 'Oorspronkelijke frequentie: ' . sanitize_text_field( (string) ( $source['frequency'] ?? '' ) );
        $parts[] = 'Prioriteit: ' . sanitize_text_field( (string) ( $source['priority'] ?? '' ) );
        return mb_substr( implode( "\n", $parts ), 0, 4000 );
    }

    private static function map_category( string $category ): string {
        $key = strtolower( remove_accents( trim( $category ) ) );
        $map = array(
            'sport' => 'sport', 'verenigingen' => 'verenigingen', 'cultuur' => 'cultuur',
            'politiek' => 'politiek', 'gemeente' => 'officieel', 'vergunningen' => 'officieel',
            'verkeer' => 'officieel', 'veiligheid' => 'veiligheid', '112' => 'veiligheid',
            'buurt' => 'veiligheid', 'onderwijs' => 'onderwijs', 'kinderopvang' => 'onderwijs',
            'ondernemers' => 'ondernemers', 'bedrijven' => 'ondernemers', 'media' => 'nieuwssites',
        );
        return $map[ $key ] ?? 'lokaal_sociaal';
    }

    private static function initial_schedule_base(): int {
        return time() + ( 5 * MINUTE_IN_SECONDS );
    }

    private static function legacy_date_to_utc( string $date ): ?string {
        $date = trim( $date );
        if ( '' === $date ) {
            return null;
        }
        try {
            $local = new DateTimeImmutable( $date . ' 12:00:00', wp_timezone() );
            return $local->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );
        } catch ( Exception $e ) {
            return null;
        }
    }
}
