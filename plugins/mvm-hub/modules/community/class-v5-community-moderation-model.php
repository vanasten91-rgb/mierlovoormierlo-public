<?php

namespace MVM\Hub\Modules\Community;

use MVM\Hub\Core\Data_Classification;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Pure, read-only moderation projection for already-authorized PeepSo/wpForo
 * moderation candidates. V5 coordinates a queue but does not own community
 * storage or moderation writes during coexistence.
 */
final class V5_Community_Moderation_Model {
    /**
     * @param list<array<string,mixed>> $candidates
     * @param callable(array<string,mixed>):bool|array<string,mixed> $authorize
     * @return array<string,mixed>
     */
    public static function build( array $candidates, callable $authorize, int $limit = 50 ): array {
        $limit = max( 1, min( 100, $limit ) );
        $items = array();

        foreach ( $candidates as $candidate ) {
            if ( count( $items ) >= $limit || ! is_array( $candidate ) ) {
                break;
            }

            $owner_key = sanitize_key( (string) ( $candidate['owner'] ?? '' ) );
            $owner = V5_Community_Owner_Catalog::get( $owner_key );
            if ( null === $owner ) {
                continue;
            }

            $object_id = trim( (string) ( $candidate['objectId'] ?? '' ) );
            $report_id = trim( (string) ( $candidate['reportId'] ?? '' ) );
            if ( '' === $object_id || '' === $report_id ) {
                continue;
            }

            $decision = $authorize( array(
                'owner' => $owner_key,
                'objectType' => (string) $owner['object_type'],
                'objectId' => $object_id,
                'reportId' => $report_id,
                'requiresObjectAuthorization' => true,
            ) );
            if ( ! self::allows( $decision ) ) {
                continue;
            }

            $severity = sanitize_key( (string) ( $candidate['severity'] ?? 'normal' ) );
            if ( ! in_array( $severity, array( 'low', 'normal', 'high', 'urgent' ), true ) ) {
                $severity = 'normal';
            }

            $reason = self::text( (string) ( $candidate['reason'] ?? '' ), 240 );
            if ( '' === $reason ) {
                $reason = 'Moderatiecontrole vereist';
            }

            $items[] = array(
                'reportId' => self::opaque( $report_id, 128 ),
                'owner' => $owner_key,
                'canonicalOwner' => (string) $owner['canonical_owner'],
                'objectType' => (string) $owner['object_type'],
                'objectId' => self::opaque( $object_id, 160 ),
                'severity' => $severity,
                'reason' => $reason,
                'reportedAtUtc' => self::utc( (string) ( $candidate['reportedAtUtc'] ?? '' ) ),
                'reportCount' => max( 1, min( 999, (int) ( $candidate['reportCount'] ?? 1 ) ) ),
                'classification' => Data_Classification::INTERNAL,
                'searchVisibility' => 'none',
                'aiVisibility' => 'none',
                'contentBodyIncluded' => false,
                'readOnly' => true,
                'v5WriteOwner' => false,
            );
        }

        usort( $items, static function ( array $a, array $b ): int {
            $weight = array( 'urgent' => 4, 'high' => 3, 'normal' => 2, 'low' => 1 );
            $cmp = ( $weight[ $b['severity'] ] ?? 0 ) <=> ( $weight[ $a['severity'] ] ?? 0 );
            if ( 0 !== $cmp ) return $cmp;
            return strcmp( (string) $a['reportId'], (string) $b['reportId'] );
        } );

        return array(
            'items' => $items,
            'count' => count( $items ),
            'readOnly' => true,
            'canonicalOwnersPreserved' => true,
            'productionActivated' => false,
        );
    }

    /** @param bool|array<string,mixed> $decision */
    private static function allows( bool|array $decision ): bool {
        return true === $decision || ( is_array( $decision ) && true === ( $decision['allow'] ?? false ) );
    }

    private static function opaque( string $value, int $max ): string {
        $value = trim( $value );
        if ( '' === $value || strlen( $value ) > $max ) return '';
        return 1 === preg_match( '/^[A-Za-z0-9._:-]+$/', $value ) ? $value : hash( 'sha256', $value );
    }

    private static function text( string $value, int $max ): string {
        $value = trim( wp_strip_all_tags( $value, true ) );
        return function_exists( 'mb_substr' ) ? (string) mb_substr( $value, 0, $max ) : substr( $value, 0, $max );
    }

    private static function utc( string $value ): string {
        $timestamp = '' === trim( $value ) ? false : strtotime( $value );
        return false === $timestamp ? '' : gmdate( 'c', $timestamp );
    }

    private function __construct() {}
}
