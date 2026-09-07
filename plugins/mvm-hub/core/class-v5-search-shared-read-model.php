<?php

namespace MVM\Hub\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Dormant shared-search result assembler.
 *
 * Input rows are assumed to be provider-matched candidate documents. This class
 * re-applies the classification policy before exposing a bounded read model.
 * It never queries WordPress or a search backend itself.
 */
final class V5_Search_Shared_Read_Model {
    private const MAX_RESULTS = 100;

    /**
     * @param list<array<string,mixed>> $documents
     * @param array<string,mixed> $context
     * @return array{scope:string,total:int,results:list<array<string,mixed>>,dropped:int}
     */
    public static function build( array $documents, array $context ): array {
        $scope = sanitize_key( (string) ( $context['scope'] ?? '' ) );

        // Shared search must never be repurposed for direct sensitive-object lookup.
        $policy_context = array(
            'scope'                => $scope,
            'direct_object_lookup' => false,
        );

        $safe    = V5_Search_Document_Policy::filter_results( $documents, $policy_context );
        $results = array();

        foreach ( $safe as $document ) {
            if ( count( $results ) >= self::MAX_RESULTS ) {
                break;
            }

            $projected = self::project( $document );
            if ( null !== $projected ) {
                $results[] = $projected;
            }
        }

        return array(
            'scope'   => $scope,
            'total'   => count( $results ),
            'results' => $results,
            'dropped' => max( 0, count( $documents ) - count( $results ) ),
        );
    }

    /**
     * Keep shared-search output deliberately small. Arbitrary provider payloads,
     * source notes, contact data and opaque metadata never pass through.
     *
     * @param array<string,mixed> $document
     * @return array<string,mixed>|null
     */
    private static function project( array $document ): ?array {
        $classification = Data_Classification::normalize( (string) ( $document['classification'] ?? '' ) );
        $domain         = sanitize_key( (string) ( $document['domain'] ?? '' ) );
        $object_type    = sanitize_key( (string) ( $document['object_type'] ?? '' ) );
        $object_id      = absint( $document['object_id'] ?? 0 );
        $title          = trim( (string) ( $document['title'] ?? '' ) );

        if ( '' === $domain || '' === $object_type || 1 > $object_id || '' === $title ) {
            return null;
        }

        $score = is_numeric( $document['score'] ?? null ) ? (float) $document['score'] : 0.0;
        $score = max( 0.0, min( 1000.0, $score ) );

        return array(
            'domain'         => $domain,
            'object_type'    => $object_type,
            'object_id'      => $object_id,
            'title'          => self::text( $title, 180 ),
            'excerpt'        => self::text( (string) ( $document['excerpt'] ?? '' ), 320 ),
            'classification' => $classification,
            'score'          => $score,
        );
    }

    private static function text( string $value, int $max_length ): string {
        $value = trim( wp_strip_all_tags( $value, true ) );
        if ( function_exists( 'mb_substr' ) ) {
            return (string) mb_substr( $value, 0, $max_length );
        }
        return substr( $value, 0, $max_length );
    }

    private function __construct() {}
}
