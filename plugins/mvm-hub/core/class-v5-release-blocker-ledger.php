<?php

namespace MVM\Hub\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Pure release-blocker ledger.
 *
 * P0 items are fail-closed: only status=resolved removes them from the RC
 * blocker set. A note, waiver label or acknowledgement never promotes a P0.
 */
final class V5_Release_Blocker_Ledger {
    private const SEVERITIES = array( 'p0', 'p1', 'p2' );
    private const STATUSES   = array( 'open', 'in_progress', 'resolved' );

    /**
     * @param array<int,array<string,mixed>> $items
     * @return array<string,mixed>
     */
    public static function evaluate( array $items ): array {
        $normalized = array();
        $open_p0    = array();
        $open_other = array();

        foreach ( array_slice( $items, 0, 100 ) as $candidate ) {
            if ( ! is_array( $candidate ) ) {
                continue;
            }

            $item = self::normalize_item( $candidate );
            if ( null === $item ) {
                continue;
            }

            $normalized[] = $item;
            if ( 'resolved' === $item['status'] ) {
                continue;
            }

            if ( 'p0' === $item['severity'] ) {
                $open_p0[] = $item['id'];
            } else {
                $open_other[] = $item['id'];
            }
        }

        $open_p0    = array_values( array_unique( $open_p0 ) );
        $open_other = array_values( array_unique( $open_other ) );

        return array(
            'rcEligible'                 => array() === $open_p0,
            'canRequestProductionApproval' => array() === $open_p0,
            'canPromoteProduction'       => false,
            'requiresExplicitApproval'   => true,
            'openP0'                     => $open_p0,
            'openOther'                  => $open_other,
            'items'                      => $normalized,
            'counts'                     => self::counts( $normalized ),
        );
    }

    /** @param array<string,mixed> $input @return array<string,mixed>|null */
    private static function normalize_item( array $input ): ?array {
        $id       = self::token( (string) ( $input['id'] ?? '' ), 120 );
        $severity = strtolower( trim( (string) ( $input['severity'] ?? '' ) ) );
        $status   = strtolower( trim( (string) ( $input['status'] ?? '' ) ) );

        if ( '' === $id || ! in_array( $severity, self::SEVERITIES, true ) || ! in_array( $status, self::STATUSES, true ) ) {
            return null;
        }

        return array(
            'id'          => $id,
            'severity'    => $severity,
            'status'      => $status,
            'title'       => self::text( (string) ( $input['title'] ?? '' ), 180 ),
            'evidenceRef' => self::token( (string) ( $input['evidenceRef'] ?? '' ), 180 ),
            'owner'       => self::token( (string) ( $input['owner'] ?? '' ), 80 ),
        );
    }

    /** @param array<int,array<string,mixed>> $items @return array<string,int> */
    private static function counts( array $items ): array {
        $counts = array(
            'p0Open'     => 0,
            'p0Resolved' => 0,
            'p1Open'     => 0,
            'p1Resolved' => 0,
            'p2Open'     => 0,
            'p2Resolved' => 0,
        );

        foreach ( $items as $item ) {
            $severity = (string) $item['severity'];
            $resolved = 'resolved' === (string) $item['status'];
            $key      = $severity . ( $resolved ? 'Resolved' : 'Open' );
            if ( isset( $counts[ $key ] ) ) {
                $counts[ $key ]++;
            }
        }

        return $counts;
    }

    private static function text( string $value, int $max ): string {
        $value = trim( strip_tags( $value ) );
        $value = preg_replace( '/[\x00-\x1F\x7F]/u', ' ', $value ) ?? '';
        return mb_substr( preg_replace( '/\s+/', ' ', $value ) ?? '', 0, $max );
    }

    private static function token( string $value, int $max ): string {
        $value = strtolower( trim( $value ) );
        $value = preg_replace( '/[^a-z0-9._:\/-]+/', '-', $value ) ?? '';
        return mb_substr( trim( $value, '-' ), 0, $max );
    }

    private function __construct() {}
}
