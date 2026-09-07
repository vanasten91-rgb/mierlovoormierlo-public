<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Read-only health adapter for the Digitale Encyclopedie van Mierlo.
 *
 * Release baselines are minimums: history may grow, but a release must never
 * silently lose published canonical objects. Completeness diagnostics are
 * warnings; inventory loss or a missing runtime contract is critical.
 */
final class MvM_Hub4_Encyclopedia_Health {
    private const CACHE_KEY   = 'encyclopedia_health_v1';
    private const CACHE_GROUP = 'mvm_hub4';
    private const CACHE_TTL   = 5 * MINUTE_IN_SECONDS;

    private const BASELINE = array(
        'mvm_encyclopedie' => 715,
        'mvm_persoon'      => 96,
        'mvm_locatie'      => 79,
        'mvm_gebouw'       => 69,
        'mvm_gebeurtenis'  => 58,
        'mvm_vereniging'   => 53,
        'mvm_bedrijf'      => 18,
        'mvm_beeld'        => 134,
        'mvm_bron'         => 844,
    );

    private const LABELS = array(
        'mvm_encyclopedie' => 'Artikelen',
        'mvm_persoon'      => 'Personen',
        'mvm_locatie'      => 'Locaties',
        'mvm_gebouw'       => 'Gebouwen',
        'mvm_gebeurtenis'  => 'Gebeurtenissen',
        'mvm_vereniging'   => 'Verenigingen',
        'mvm_bedrijf'      => 'Bedrijven',
        'mvm_beeld'        => 'Beeldbank-items',
        'mvm_bron'         => 'Bronrecords',
    );

    private const NARRATIVE_TYPES = array(
        'mvm_encyclopedie',
        'mvm_persoon',
        'mvm_locatie',
        'mvm_gebouw',
        'mvm_gebeurtenis',
        'mvm_vereniging',
        'mvm_bedrijf',
    );

    private const REQUIRED_TAXONOMIES = array( 'mvm_thema', 'mvm_periode', 'mvm_status', 'mvm_gebied' );
    private const VALID_STATUSES      = array( 'ok', 'warning', 'critical', 'unknown' );

    /**
     * @param array<string,mixed> $base Existing Hub health payload.
     * @return array<string,mixed>
     */
    public static function extend( array $base, bool $fresh = false ): array {
        if ( $fresh ) {
            wp_cache_delete( self::CACHE_KEY, self::CACHE_GROUP );
        }

        $checks = isset( $base['checks'] ) && is_array( $base['checks'] ) ? $base['checks'] : array();
        foreach ( self::checks() as $check ) {
            $checks[] = $check;
        }

        $base['checks']  = $checks;
        $base['summary'] = MvM_Hub4_Health_Check::summarize( $checks );
        $base['status']  = self::overall_status( $base['summary'] );
        return $base;
    }

    /** @return array<int,array<string,mixed>> */
    private static function checks(): array {
        $found  = false;
        $cached = wp_cache_get( self::CACHE_KEY, self::CACHE_GROUP, false, $found );
        if ( $found && is_array( $cached ) ) {
            return $cached;
        }

        $checked_at = gmdate( 'c' );
        try {
            $checks = array_merge(
                self::inventory_checks( $checked_at ),
                array(
                    self::taxonomy_contract_check( $checked_at ),
                    self::content_quality_check( $checked_at ),
                    self::duplicate_identity_check( $checked_at ),
                    self::relation_coverage_check( $checked_at ),
                    self::imagebank_media_check( $checked_at ),
                    self::source_content_check( $checked_at ),
                    self::history_preservation_check( $checked_at ),
                )
            );
        } catch ( Throwable $error ) {
            unset( $error );
            $checks = array(
                self::normalize(
                    array(
                        'id'      => 'encyclopedia_runtime',
                        'label'   => 'Encyclopedie Health Check',
                        'status'  => 'unknown',
                        'summary' => 'De Encyclopedie Health Check kon veilig niet worden afgerond.',
                    ),
                    $checked_at
                ),
            );
        }

        wp_cache_set( self::CACHE_KEY, $checks, self::CACHE_GROUP, self::CACHE_TTL );
        return $checks;
    }

    /** @return array<int,array<string,mixed>> */
    private static function inventory_checks( string $checked_at ): array {
        $checks = array();
        foreach ( self::BASELINE as $post_type => $minimum ) {
            $exists = post_type_exists( $post_type );
            $counts = $exists ? wp_count_posts( $post_type ) : null;
            $actual = is_object( $counts ) ? (int) ( $counts->publish ?? 0 ) : 0;
            $ok     = $exists && $actual >= $minimum;
            $checks[] = self::normalize(
                array(
                    'id'       => 'encyclopedia_inventory_' . $post_type,
                    'label'    => 'Encyclopedie inventaris — ' . ( self::LABELS[ $post_type ] ?? $post_type ),
                    'status'   => $ok ? 'ok' : 'critical',
                    'summary'  => $ok ? 'De gepubliceerde canonieke set is niet kleiner dan de releasebaseline.' : 'De gepubliceerde canonieke set is kleiner dan de releasebaseline of het posttype ontbreekt.',
                    'expected' => 'minimaal ' . $minimum,
                    'actual'   => $actual,
                ),
                $checked_at
            );
        }
        return $checks;
    }

    private static function taxonomy_contract_check( string $checked_at ): array {
        $missing = array();
        foreach ( self::REQUIRED_TAXONOMIES as $taxonomy ) {
            if ( ! taxonomy_exists( $taxonomy ) ) {
                $missing[] = $taxonomy;
                continue;
            }
            foreach ( array_keys( self::BASELINE ) as $post_type ) {
                if ( ! is_object_in_taxonomy( $post_type, $taxonomy ) ) {
                    $missing[] = $taxonomy . ':' . $post_type;
                }
            }
        }

        return self::normalize(
            array(
                'id'       => 'encyclopedia_taxonomy_contract',
                'label'    => 'Encyclopedie thema/relatiecontract',
                'status'   => empty( $missing ) ? 'ok' : 'critical',
                'summary'  => empty( $missing ) ? 'Alle encyclopedie-objecttypen delen de vereiste thema-, periode-, status- en gebiedstaxonomieën.' : 'Een vereist encyclopedie-taxonomiecontract ontbreekt.',
                'expected' => 0,
                'actual'   => count( $missing ),
            ),
            $checked_at
        );
    }

    private static function content_quality_check( string $checked_at ): array {
        global $wpdb;
        $short = 0;
        foreach ( self::NARRATIVE_TYPES as $post_type ) {
            $short += (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish' AND CHAR_LENGTH(TRIM(post_content)) < %d",
                    $post_type,
                    160
                )
            );
        }

        return self::normalize(
            array(
                'id'       => 'encyclopedia_short_content',
                'label'    => 'Encyclopedie inhoudsdekking',
                'status'   => 0 === $short ? 'ok' : 'warning',
                'summary'  => 0 === $short ? 'Alle narratieve encyclopedie-objecten bevatten minimaal basistekst.' : 'Er zijn gepubliceerde narratieve objecten met lege of zeer korte inhoud; deze blijven staan maar vragen redactionele controle.',
                'expected' => 0,
                'actual'   => $short,
            ),
            $checked_at
        );
    }

    private static function duplicate_identity_check( string $checked_at ): array {
        global $wpdb;
        $types        = self::NARRATIVE_TYPES;
        $placeholders = implode( ',', array_fill( 0, count( $types ), '%s' ) );
        $query        = $wpdb->prepare(
            "SELECT ID, post_type, post_title, post_name FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type IN ({$placeholders}) ORDER BY ID ASC",
            ...$types
        );
        $rows         = $wpdb->get_results( $query );
        $seen_title   = array();
        $seen_slug    = array();
        $duplicates   = 0;

        foreach ( (array) $rows as $row ) {
            $type      = (string) $row->post_type;
            $title_key = $type . '|' . sanitize_title( html_entity_decode( (string) $row->post_title, ENT_QUOTES, get_bloginfo( 'charset' ) ) );
            $slug_key  = $type . '|' . (string) $row->post_name;
            if ( isset( $seen_title[ $title_key ] ) ) {
                $duplicates++;
            } else {
                $seen_title[ $title_key ] = (int) $row->ID;
            }
            if ( '' !== (string) $row->post_name ) {
                if ( isset( $seen_slug[ $slug_key ] ) ) {
                    $duplicates++;
                } else {
                    $seen_slug[ $slug_key ] = (int) $row->ID;
                }
            }
        }

        return self::normalize(
            array(
                'id'       => 'encyclopedia_duplicate_identity',
                'label'    => 'Encyclopedie dubbele records',
                'status'   => 0 === $duplicates ? 'ok' : 'warning',
                'summary'  => 0 === $duplicates ? 'Binnen ieder canoniek objecttype zijn geen dubbele titel-/slugidentiteiten gevonden.' : 'Er zijn mogelijke dubbele gepubliceerde identiteiten. Er wordt niets automatisch verwijderd; consolidatie vereist expliciete review.',
                'expected' => 0,
                'actual'   => $duplicates,
            ),
            $checked_at
        );
    }

    private static function relation_coverage_check( string $checked_at ): array {
        global $wpdb;
        $missing = 0;
        foreach ( self::NARRATIVE_TYPES as $post_type ) {
            foreach ( array( 'mvm_thema', 'mvm_gebied' ) as $taxonomy ) {
                $missing += (int) $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT COUNT(*) FROM {$wpdb->posts} p WHERE p.post_type = %s AND p.post_status = 'publish' AND NOT EXISTS (SELECT 1 FROM {$wpdb->term_relationships} tr INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id WHERE tr.object_id = p.ID AND tt.taxonomy = %s)",
                        $post_type,
                        $taxonomy
                    )
                );
            }
        }

        return self::normalize(
            array(
                'id'       => 'encyclopedia_relation_coverage',
                'label'    => 'Encyclopedie ontbrekende relaties',
                'status'   => 0 === $missing ? 'ok' : 'warning',
                'summary'  => 0 === $missing ? 'Alle narratieve objecten hebben thema- en gebiedsrelaties.' : 'Een deel van de gepubliceerde narratieve objecten mist een thema- of gebiedsrelatie. Dit wordt niet automatisch ingevuld.',
                'expected' => 0,
                'actual'   => $missing,
            ),
            $checked_at
        );
    }

    private static function imagebank_media_check( string $checked_at ): array {
        global $wpdb;
        $missing = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} p LEFT JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = '_thumbnail_id' LEFT JOIN {$wpdb->posts} a ON a.ID = CAST(pm.meta_value AS UNSIGNED) AND a.post_type = 'attachment' AND a.post_status = 'inherit' WHERE p.post_type = 'mvm_beeld' AND p.post_status = 'publish' AND a.ID IS NULL"
        );

        return self::normalize(
            array(
                'id'       => 'encyclopedia_imagebank_media',
                'label'    => 'Encyclopedie beeldbankbestanden',
                'status'   => 0 === $missing ? 'ok' : 'warning',
                'summary'  => 0 === $missing ? 'Alle gepubliceerde beeldbank-items hebben een bestaand media-object.' : 'Er zijn beeldbank-items zonder geldig gekoppeld media-object.',
                'expected' => 0,
                'actual'   => $missing,
            ),
            $checked_at
        );
    }

    private static function source_content_check( string $checked_at ): array {
        global $wpdb;
        $empty = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'mvm_bron' AND post_status = 'publish' AND CHAR_LENGTH(TRIM(post_content)) < 20"
        );

        return self::normalize(
            array(
                'id'       => 'encyclopedia_source_content',
                'label'    => 'Encyclopedie bronrecords',
                'status'   => 0 === $empty ? 'ok' : 'warning',
                'summary'  => 0 === $empty ? 'Alle gepubliceerde bronrecords bevatten broninformatie.' : 'Er zijn gepubliceerde bronrecords zonder bruikbare bronbeschrijving. Historie blijft behouden.',
                'expected' => 0,
                'actual'   => $empty,
            ),
            $checked_at
        );
    }

    private static function history_preservation_check( string $checked_at ): array {
        $history = 0;
        foreach ( array_keys( self::BASELINE ) as $post_type ) {
            $counts  = wp_count_posts( $post_type );
            $history += is_object( $counts ) ? (int) ( $counts->trash ?? 0 ) : 0;
        }
        return self::normalize(
            array(
                'id'      => 'encyclopedia_history_preserved',
                'label'   => 'Encyclopedie historiebehoud',
                'status'  => 'ok',
                'summary' => 'Niet-gepubliceerde historische records blijven buiten de canonieke set bewaard; de health check voert geen hard-delete uit.',
                'metric'  => $history,
            ),
            $checked_at
        );
    }

    /** @param array<string,mixed> $item */
    private static function normalize( array $item, string $checked_at ): array {
        $status = sanitize_key( (string) ( $item['status'] ?? 'unknown' ) );
        $status = in_array( $status, self::VALID_STATUSES, true ) ? $status : 'unknown';
        $clean  = array(
            'id'         => sanitize_key( (string) ( $item['id'] ?? 'encyclopedia_runtime' ) ),
            'label'      => sanitize_text_field( (string) ( $item['label'] ?? 'Encyclopedie' ) ),
            'status'     => $status,
            'summary'    => sanitize_text_field( (string) ( $item['summary'] ?? 'Geen aanvullende informatie.' ) ),
            'checked_at' => $checked_at,
        );
        foreach ( array( 'metric', 'expected', 'actual' ) as $key ) {
            if ( isset( $item[ $key ] ) && is_scalar( $item[ $key ] ) ) {
                $clean[ $key ] = is_numeric( $item[ $key ] ) ? 0 + $item[ $key ] : sanitize_text_field( (string) $item[ $key ] );
            }
        }
        return $clean;
    }

    /** @param array{ok:int,warning:int,critical:int,unknown:int,total:int} $summary */
    private static function overall_status( array $summary ): string {
        if ( (int) ( $summary['critical'] ?? 0 ) > 0 ) {
            return 'critical';
        }
        if ( (int) ( $summary['warning'] ?? 0 ) > 0 ) {
            return 'warning';
        }
        return (int) ( $summary['ok'] ?? 0 ) > 0 ? 'ok' : 'unknown';
    }
}
