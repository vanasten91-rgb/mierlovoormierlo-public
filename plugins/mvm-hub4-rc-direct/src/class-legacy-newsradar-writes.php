<?php
/**
 * Narrow transitional writes into the legacy Hub 3 Nieuwsradar dataset.
 *
 * The crawler remains owned by Hub 3. Every write in this class must mirror one
 * explicitly audited legacy action and must fail closed while a live crawler
 * lock is present. Generic row mutation is intentionally not provided.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class MvM_Hub4_Legacy_Newsradar_Writes {
    private const OPTION_LOCK = 'mvm_bh_news_radar_lock';

    /**
     * Mark exactly one legacy radar row as reviewed.
     *
     * This mirrors Hub 3 news_mark_reviewed(): reviewed=1 + timestamp + user.
     * Repeated calls are idempotent and preserve the original reviewer/time.
     *
     * @return array<string,mixed>|WP_Error
     */
    public static function mark_reviewed( string $id ): array|WP_Error {
        $id = strtolower( trim( $id ) );
        if ( ! self::valid_result_id( $id ) ) {
            return new WP_Error(
                'mvm_news_radar_invalid_id',
                'Ongeldig Nieuwsradar-ID.',
                array( 'status' => 400 )
            );
        }

        if ( self::crawler_busy() ) {
            return self::busy_error();
        }

        $rows = get_option( MvM_Hub4_Legacy_Newsradar::OPTION_RESULTS, array() );
        if ( ! is_array( $rows ) ) {
            return new WP_Error(
                'mvm_news_radar_unavailable',
                'De legacy Nieuwsradar-data is niet beschikbaar.',
                array( 'status' => 503 )
            );
        }

        $index = self::find_row_index( $rows, $id );
        if ( null === $index ) {
            return new WP_Error(
                'mvm_news_radar_not_found',
                'Het Nieuwsradar-item bestaat niet meer.',
                array( 'status' => 404 )
            );
        }

        $row = is_array( $rows[ $index ] ) ? $rows[ $index ] : array();
        if ( ! empty( $row['reviewed'] ) ) {
            return self::review_response( $row, false );
        }

        // Recheck immediately before preparing the write. Production then uses
        // a compare-and-swap against the exact serialized option value, so a
        // crawler update between this read and our write becomes HTTP 409 rather
        // than a lost update.
        if ( self::crawler_busy() ) {
            return self::busy_error();
        }

        $expected_rows      = $rows;
        $row['reviewed']    = 1;
        $row['reviewed_at'] = wp_date( DATE_ATOM );
        $row['reviewed_by'] = get_current_user_id();
        $rows[ $index ]     = $row;

        $persisted = self::persist_rows( $expected_rows, $rows );
        if ( is_wp_error( $persisted ) ) {
            return $persisted;
        }

        return self::review_response( $row, true );
    }

    public static function crawler_busy(): bool {
        $lock = get_option( self::OPTION_LOCK, array() );
        if ( ! is_array( $lock ) || empty( $lock['expires'] ) ) {
            return false;
        }

        return (int) $lock['expires'] >= time();
    }

    /**
     * Persist the whole legacy option without overwriting a concurrent crawler
     * update. On WordPress production the options row is atomically updated only
     * when its serialized value still matches the value we originally read.
     *
     * The update_option fallback exists for the isolated PHP runtime harness,
     * where no wpdb object is available.
     *
     * @param array<int,mixed> $expected_rows
     * @param array<int,mixed> $new_rows
     * @return true|WP_Error
     */
    private static function persist_rows( array $expected_rows, array $new_rows ): true|WP_Error {
        global $wpdb;

        if (
            isset( $wpdb )
            && is_object( $wpdb )
            && isset( $wpdb->options )
            && method_exists( $wpdb, 'prepare' )
            && method_exists( $wpdb, 'query' )
            && function_exists( 'maybe_serialize' )
        ) {
            $option_name = MvM_Hub4_Legacy_Newsradar::OPTION_RESULTS;
            $sql = $wpdb->prepare(
                "UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND option_value = %s",
                maybe_serialize( $new_rows ),
                $option_name,
                maybe_serialize( $expected_rows )
            );
            $affected = $wpdb->query( $sql );

            if ( 1 === $affected ) {
                if ( function_exists( 'wp_cache_delete' ) ) {
                    wp_cache_delete( $option_name, 'options' );
                    wp_cache_delete( 'alloptions', 'options' );
                    wp_cache_delete( 'notoptions', 'options' );
                }
                return true;
            }

            if ( 0 === $affected ) {
                return new WP_Error(
                    'mvm_news_radar_conflict',
                    'De Nieuwsradar veranderde tijdens de reviewactie. Vernieuw de lijst en probeer opnieuw.',
                    array( 'status' => 409 )
                );
            }

            return new WP_Error(
                'mvm_news_radar_write_failed',
                'De reviewstatus kon niet veilig worden opgeslagen.',
                array( 'status' => 500 )
            );
        }

        if ( update_option( MvM_Hub4_Legacy_Newsradar::OPTION_RESULTS, $new_rows, false ) ) {
            return true;
        }

        return new WP_Error(
            'mvm_news_radar_write_failed',
            'De reviewstatus kon niet veilig worden opgeslagen.',
            array( 'status' => 500 )
        );
    }

    private static function busy_error(): WP_Error {
        return new WP_Error(
            'mvm_news_radar_busy',
            'De Nieuwsradar-crawler is bezig. Probeer de reviewactie na de scan opnieuw.',
            array( 'status' => 409 )
        );
    }

    private static function valid_result_id( string $id ): bool {
        return 1 === preg_match( '/^[a-f0-9]{64}$/', $id );
    }

    /**
     * @param array<int,mixed> $rows
     */
    private static function find_row_index( array $rows, string $id ): ?int {
        foreach ( $rows as $index => $row ) {
            if ( ! is_array( $row ) || ! isset( $row['id'] ) || ! is_scalar( $row['id'] ) ) {
                continue;
            }
            $candidate = strtolower( (string) $row['id'] );
            if ( strlen( $candidate ) === strlen( $id ) && hash_equals( $candidate, $id ) ) {
                return (int) $index;
            }
        }
        return null;
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private static function review_response( array $row, bool $changed ): array {
        return array(
            'id'         => sanitize_text_field( (string) ( $row['id'] ?? '' ) ),
            'reviewed'   => ! empty( $row['reviewed'] ),
            'reviewedAt' => sanitize_text_field( (string) ( $row['reviewed_at'] ?? '' ) ),
            'reviewedBy' => absint( $row['reviewed_by'] ?? 0 ),
            'changed'    => $changed,
        );
    }
}
