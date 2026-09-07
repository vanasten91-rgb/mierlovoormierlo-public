<?php
/**
 * Read-only compatibility adapter for the Hub 3 Nieuwsradar dataset.
 *
 * This class intentionally does not mutate legacy options. It exists to make
 * the Hub 3 -> Hub 4 consolidation observable and testable before any crawler
 * or route ownership changes are attempted.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class MvM_Hub4_Legacy_Newsradar {
    public const OPTION_SOURCES       = 'mvm_bh_sources';
    public const OPTION_RESULTS       = 'mvm_bh_news_radar_results';
    public const OPTION_TRACKS        = 'mvm_bh_news_radar_tracks';
    public const OPTION_SEEN          = 'mvm_bh_news_radar_seen';
    public const OPTION_DETAIL_QUEUE  = 'mvm_bh_news_radar_detail_queue';
    public const OPTION_TOMBSTONES    = 'mvm_bh_news_radar_tombstones';
    public const OPTION_SETTINGS      = 'mvm_bh_news_radar_settings';
    public const OPTION_STATE         = 'mvm_bh_news_radar_state';

    /**
     * Return a non-sensitive inventory of the live legacy dataset.
     *
     * @return array<string,int|bool>
     */
    public static function snapshot(): array {
        $sources    = self::option_array( self::OPTION_SOURCES );
        $results    = self::option_array( self::OPTION_RESULTS );
        $tracks     = self::option_array( self::OPTION_TRACKS );
        $seen       = self::option_array( self::OPTION_SEEN );
        $queue      = self::option_array( self::OPTION_DETAIL_QUEUE );
        $tombstones = self::option_array( self::OPTION_TOMBSTONES );
        $settings   = self::option_array( self::OPTION_SETTINGS );
        $state      = self::option_array( self::OPTION_STATE );

        return array(
            'legacy_available' => self::legacy_available(),
            'sources'          => count( $sources ),
            'results'          => count( $results ),
            'tracks'           => count( $tracks ),
            'seen'             => count( $seen ),
            'queue_jobs'       => isset( $queue['jobs'] ) && is_array( $queue['jobs'] ) ? count( $queue['jobs'] ) : 0,
            'tombstones'       => count( $tombstones ),
            'enabled'          => ! empty( $settings['enabled'] ),
            'running'          => ! empty( $state['running'] ),
        );
    }

    public static function legacy_available(): bool {
        return false !== get_option( self::OPTION_SOURCES, false )
            || false !== get_option( self::OPTION_RESULTS, false );
    }

    /**
     * Return a strictly allow-listed view of legacy monitoring sources.
     *
     * Source IDs are preserved so the UI can offer deterministic arrival/order
     * sorting without exposing notes, internal diagnostics or crawler state.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function sources(): array {
        $out = array();

        foreach ( self::option_array( self::OPTION_SOURCES ) as $source ) {
            if ( ! is_array( $source ) ) {
                continue;
            }

            $out[] = self::pick(
                $source,
                array(
                    'id', 'name', 'url', 'category', 'priority', 'frequency',
                    'active', 'last_checked', 'next_check', 'web_checked',
                )
            );
        }

        return $out;
    }

    /**
     * Return an allow-listed view of the current legacy result queue.
     *
     * Existing array order is preserved because Hub 3 prepends new detections.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function results(): array {
        $out = array();

        foreach ( self::option_array( self::OPTION_RESULTS ) as $row ) {
            if ( ! is_array( $row ) ) {
                continue;
            }

            $out[] = self::pick(
                $row,
                array(
                    'id', 'track_id', 'source_id', 'source', 'source_url',
                    'title', 'url', 'published', 'published_ts', 'excerpt',
                    'previous_excerpt', 'event_type', 'found_at', 'found_ts',
                    'local_score', 'ai_pending', 'status', 'category', 'summary',
                    'confidence', 'reason', 'change_relevant', 'change_summary',
                    'date_issues', 'relative_terms', 'date_source',
                    'date_confidence', 'date_checked_at', 'reviewed', 'dismissed',
                    'email_pending', 'email_sent', 'lifecycle', 'related_post_id',
                    'relationship_type', 'agenda_start_ts', 'agenda_date',
                    'agenda_location', 'detection_method', 'evidence',
                    'ai_provider', 'ai_checked_at',
                )
            );
        }

        return $out;
    }

    /**
     * Group repeated detections of one source article under one subject.
     *
     * @param array<int,array<string,mixed>>|null $rows Optional rows for tests.
     * @return array<int,array{subject_key:string,primary:array<string,mixed>,updates:array<int,array<string,mixed>>,notification_count:int}>
     */
    public static function grouped_results( ?array $rows = null ): array {
        $rows   = null === $rows ? self::results() : $rows;
        $groups = array();
        $order  = array();

        foreach ( $rows as $row ) {
            if ( ! is_array( $row ) ) {
                continue;
            }

            $key = self::subject_key( $row );
            if ( ! isset( $groups[ $key ] ) ) {
                $groups[ $key ] = array(
                    'subject_key'       => $key,
                    'primary'           => $row,
                    'updates'           => array(),
                    'notification_count'=> 1,
                );
                $order[] = $key;
                continue;
            }

            $groups[ $key ]['updates'][] = $row;
            $groups[ $key ]['notification_count']++;
        }

        $out = array();
        foreach ( $order as $key ) {
            $out[] = $groups[ $key ];
        }

        return $out;
    }

    /**
     * Build the same stable article identity already present in legacy
     * tombstones (article:<host>:<numeric-id>) whenever possible.
     *
     * @param array<string,mixed> $row
     */
    public static function subject_key( array $row ): string {
        $url = isset( $row['url'] ) && is_scalar( $row['url'] ) ? trim( (string) $row['url'] ) : '';

        if ( '' !== $url ) {
            $parts = wp_parse_url( $url );
            if ( is_array( $parts ) && ! empty( $parts['host'] ) ) {
                $host = strtolower( (string) $parts['host'] );
                $path = isset( $parts['path'] ) ? (string) $parts['path'] : '/';

                if ( preg_match( '#/(?:nieuws|news|artikel|article|berichten?|hulpvragen|events?)/(\d+)(?:/|$)#i', $path, $match ) ) {
                    return 'article:' . $host . ':' . $match[1];
                }

                $path = rawurldecode( $path );
                $path = '/' . ltrim( $path, '/' );
                $path = '/' === $path ? '/' : rtrim( $path, '/' );

                return 'url:' . $host . strtolower( $path );
            }
        }

        $source = isset( $row['source'] ) && is_scalar( $row['source'] ) ? (string) $row['source'] : '';
        $title  = isset( $row['title'] ) && is_scalar( $row['title'] ) ? (string) $row['title'] : '';

        return 'title:' . self::normalize_text( $source ) . '|' . self::normalize_text( $title );
    }

    /** @return array<mixed> */
    private static function option_array( string $name ): array {
        $value = get_option( $name, array() );
        return is_array( $value ) ? $value : array();
    }

    /**
     * @param array<string,mixed> $row
     * @param array<int,string>   $keys
     * @return array<string,mixed>
     */
    private static function pick( array $row, array $keys ): array {
        $out = array();
        foreach ( $keys as $key ) {
            if ( array_key_exists( $key, $row ) ) {
                $out[ $key ] = $row[ $key ];
            }
        }
        return $out;
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
