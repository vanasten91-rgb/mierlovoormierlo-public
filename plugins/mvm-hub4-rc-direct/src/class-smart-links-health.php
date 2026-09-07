<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Read-only adapter that folds Smart Links diagnostics from the active theme
 * into the existing Hub 4 system-health response.
 *
 * The Smart Links renderer owns the domain-specific checks. Hub 4 only
 * validates, caches and normalizes that snapshot so Systeemstatus remains the
 * canonical UI without duplicating link-index logic in the plugin.
 */
final class MvM_Hub4_Smart_Links_Health {
    private const CACHE_KEY   = 'smart_links_health_v1';
    private const CACHE_GROUP = 'mvm_hub4';
    private const CACHE_TTL   = 5 * MINUTE_IN_SECONDS;
    private const VALID_STATUSES = array( 'ok', 'warning', 'critical', 'unknown' );

    /**
     * @param array<string,mixed> $base Existing Hub 4 health payload.
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

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function checks(): array {
        $found  = false;
        $cached = wp_cache_get( self::CACHE_KEY, self::CACHE_GROUP, false, $found );
        if ( $found && is_array( $cached ) ) {
            return $cached;
        }

        $checked_at = gmdate( 'c' );
        try {
            if ( ! function_exists( 'mvm_smart_links_health_snapshot_v1' ) ) {
                $checks = array(
                    self::normalize(
                        array(
                            'id'       => 'smart_links_runtime',
                            'label'    => 'Smart Links Health Check',
                            'status'   => 'unknown',
                            'summary'  => 'De actieve theme-laag levert nog geen Smart Links Health Check-snapshot.',
                            'expected' => 'health snapshot beschikbaar',
                            'actual'   => 'niet beschikbaar',
                        ),
                        $checked_at
                    ),
                );
            } else {
                $raw = mvm_smart_links_health_snapshot_v1();
                if ( ! is_array( $raw ) ) {
                    $raw = array();
                }

                $checks = array();
                foreach ( $raw as $item ) {
                    if ( ! is_array( $item ) ) {
                        continue;
                    }
                    $id = sanitize_key( (string) ( $item['id'] ?? '' ) );
                    if ( ! str_starts_with( $id, 'smart_links_' ) ) {
                        continue;
                    }
                    $checks[] = self::normalize( $item, $checked_at );
                }

                if ( empty( $checks ) ) {
                    $checks[] = self::normalize(
                        array(
                            'id'      => 'smart_links_runtime',
                            'label'   => 'Smart Links Health Check',
                            'status'  => 'unknown',
                            'summary' => 'De Smart Links-snapshot bevatte geen geldige controles.',
                        ),
                        $checked_at
                    );
                }
            }
        } catch ( Throwable $error ) {
            unset( $error );
            $checks = array(
                self::normalize(
                    array(
                        'id'      => 'smart_links_runtime',
                        'label'   => 'Smart Links Health Check',
                        'status'  => 'unknown',
                        'summary' => 'De Smart Links Health Check kon veilig niet worden afgerond.',
                    ),
                    $checked_at
                ),
            );
        }

        wp_cache_set( self::CACHE_KEY, $checks, self::CACHE_GROUP, self::CACHE_TTL );
        return $checks;
    }

    /**
     * @param array<string,mixed> $item Raw Smart Links check.
     * @return array<string,mixed>
     */
    private static function normalize( array $item, string $checked_at ): array {
        $status = sanitize_key( (string) ( $item['status'] ?? 'unknown' ) );
        $status = in_array( $status, self::VALID_STATUSES, true ) ? $status : 'unknown';

        $clean = array(
            'id'         => sanitize_key( (string) ( $item['id'] ?? 'smart_links_runtime' ) ),
            'label'      => sanitize_text_field( (string) ( $item['label'] ?? 'Smart Links' ) ),
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

    /**
     * @param array{ok:int,warning:int,critical:int,unknown:int,total:int} $summary Summary.
     */
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
