<?php

namespace MVM\Hub\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Pure, dormant operational-health evaluator for Hub V5.
 *
 * The model accepts only bounded numeric/boolean health signals. It performs no
 * WordPress reads/writes, emits no logs and intentionally cannot carry message,
 * source, chat or mailbox bodies into observability output.
 */
final class V5_Operational_Health_Model {
    /** @return array<string,array{warning:int,critical:int,direction:string}> */
    public static function policies(): array {
        return array(
            'queue_lag_seconds' => array( 'warning' => 120, 'critical' => 600, 'direction' => 'high' ),
            'cron_age_seconds' => array( 'warning' => 900, 'critical' => 1800, 'direction' => 'high' ),
            'search_index_age_seconds' => array( 'warning' => 900, 'critical' => 3600, 'direction' => 'high' ),
            'mail_sync_age_seconds' => array( 'warning' => 900, 'critical' => 3600, 'direction' => 'high' ),
            'backup_age_seconds' => array( 'warning' => 86400, 'critical' => 172800, 'direction' => 'high' ),
            'failed_workflow_transitions' => array( 'warning' => 1, 'critical' => 5, 'direction' => 'high' ),
            'open_security_alerts' => array( 'warning' => 1, 'critical' => 1, 'direction' => 'high' ),
            'dead_letter_events' => array( 'warning' => 1, 'critical' => 5, 'direction' => 'high' ),
        );
    }

    /**
     * @param array<string,mixed> $metrics
     * @return array{status:string,healthy:bool,signals:list<array<string,mixed>>,metrics:array<string,int>,sensitivePayloadAllowed:bool,productionActivated:bool}
     */
    public static function evaluate( array $metrics ): array {
        $normalized = self::normalize_metrics( $metrics );
        $signals    = array();
        $overall    = 'healthy';

        foreach ( self::policies() as $key => $policy ) {
            if ( ! array_key_exists( $key, $normalized ) ) {
                continue;
            }

            $value  = $normalized[ $key ];
            $status = self::status_for( $value, $policy );
            if ( 'healthy' !== $status ) {
                $signals[] = array(
                    'metric' => $key,
                    'status' => $status,
                    'value' => $value,
                    'warningThreshold' => $policy['warning'],
                    'criticalThreshold' => $policy['critical'],
                );
            }

            if ( 'critical' === $status ) {
                $overall = 'critical';
            } elseif ( 'degraded' === $status && 'critical' !== $overall ) {
                $overall = 'degraded';
            }
        }

        return array(
            'status' => $overall,
            'healthy' => 'healthy' === $overall,
            'signals' => array_values( $signals ),
            'metrics' => $normalized,
            'sensitivePayloadAllowed' => false,
            'productionActivated' => false,
        );
    }

    /** @param array<string,mixed> $metrics @return array<string,int> */
    private static function normalize_metrics( array $metrics ): array {
        $allowed = array_keys( self::policies() );
        $out     = array();

        foreach ( $allowed as $key ) {
            if ( ! array_key_exists( $key, $metrics ) || ! is_numeric( $metrics[ $key ] ) ) {
                continue;
            }
            $out[ $key ] = max( 0, min( 2147483647, (int) $metrics[ $key ] ) );
        }

        return $out;
    }

    /** @param array{warning:int,critical:int,direction:string} $policy */
    private static function status_for( int $value, array $policy ): string {
        if ( 'high' === $policy['direction'] ) {
            if ( $value >= $policy['critical'] ) {
                return 'critical';
            }
            if ( $value >= $policy['warning'] ) {
                return 'degraded';
            }
        }

        return 'healthy';
    }

    private function __construct() {}
}
