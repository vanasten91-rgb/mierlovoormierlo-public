<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Read-only adapter for route checks performed outside a Hub page request.
 *
 * A scheduled/external monitor may publish a small, non-sensitive snapshot to
 * OPTION_SNAPSHOT. This adapter never performs self-HTTP requests and never
 * writes the snapshot itself. Missing or stale data is deliberately neutral.
 */
final class MvM_Hub4_Health_Route_Checks {
    public const OPTION_SNAPSHOT = 'mvm_hub4_external_route_health_v1';

    private const MAX_AGE = 30 * MINUTE_IN_SECONDS;

    /**
     * @return array<int,array{id:string,label:string}>
     */
    public static function definitions(): array {
        return array(
            array( 'id' => 'route_homepage', 'label' => 'Homepage bereikbaar' ),
            array( 'id' => 'route_forum', 'label' => 'Forum bereikbaar' ),
            array( 'id' => 'route_events', 'label' => 'Evenementenoverzicht bereikbaar' ),
            array( 'id' => 'route_about_mierlo', 'label' => 'Over Mierlo bereikbaar' ),
            array( 'id' => 'route_members', 'label' => 'Ledenpagina bereikbaar' ),
            array( 'id' => 'route_semantics', 'label' => 'Kernroutes hebben main en H1' ),
            array( 'id' => 'route_og_image_http', 'label' => 'Open Graph-afbeelding bereikbaar' ),
        );
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function checks( string $fallback_checked_at ): array {
        $snapshot = get_option( self::OPTION_SNAPSHOT, array() );
        $snapshot = is_array( $snapshot ) ? $snapshot : array();
        $checks   = array();

        foreach ( self::definitions() as $definition ) {
            $record = $snapshot[ $definition['id'] ] ?? null;
            $checks[] = self::from_snapshot( $definition, $record, $fallback_checked_at );
        }

        return $checks;
    }

    /**
     * @param array{id:string,label:string} $definition Check definition.
     * @param mixed                        $record     External snapshot record.
     * @return array<string,mixed>
     */
    private static function from_snapshot( array $definition, mixed $record, string $fallback_checked_at ): array {
        if ( ! is_array( $record ) ) {
            return self::unknown( $definition, $fallback_checked_at );
        }

        $status     = sanitize_key( (string) ( $record['status'] ?? '' ) );
        $checked_at = sanitize_text_field( (string) ( $record['checked_at'] ?? '' ) );
        $timestamp  = '' !== $checked_at ? strtotime( $checked_at ) : false;

        if ( ! in_array( $status, array( 'ok', 'warning', 'critical' ), true ) || false === $timestamp ) {
            return self::unknown( $definition, $fallback_checked_at );
        }

        if ( $timestamp < ( time() - self::MAX_AGE ) ) {
            return array(
                'id'         => $definition['id'],
                'label'      => $definition['label'],
                'status'     => 'unknown',
                'summary'    => 'De laatste externe routecontrole is verouderd.',
                'checked_at' => gmdate( 'c', $timestamp ),
            );
        }

        $summaries = array(
            'ok'       => 'De externe routecontrole is geslaagd.',
            'warning'  => 'De externe routecontrole meldt een aandachtspunt.',
            'critical' => 'De externe routecontrole meldt een fout.',
        );

        return array(
            'id'         => $definition['id'],
            'label'      => $definition['label'],
            'status'     => $status,
            'summary'    => $summaries[ $status ],
            'checked_at' => gmdate( 'c', $timestamp ),
        );
    }

    /**
     * @param array{id:string,label:string} $definition Check definition.
     * @return array<string,string>
     */
    private static function unknown( array $definition, string $checked_at ): array {
        return array(
            'id'         => $definition['id'],
            'label'      => $definition['label'],
            'status'     => 'unknown',
            'summary'    => 'Nog niet extern gecontroleerd; de Hub doet geen self-request.',
            'checked_at' => $checked_at,
        );
    }
}
