<?php

namespace MVM\Hub\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Dormant cross-domain My Work aggregator.
 *
 * Domain adapters supply candidate work items. Authorization is applied once,
 * before global prioritization, so urgency never bypasses object/classification
 * policy and the resulting dashboard has one consistent ordering model.
 */
final class V5_Cross_Domain_My_Work_Model {
    /**
     * @param array<string,list<array<string,mixed>>> $domain_candidates
     * @param callable(array<string,mixed>):(bool|array<string,mixed>) $authorize
     * @return array<string,mixed>
     */
    public static function build(
        array $domain_candidates,
        callable $authorize,
        ?\DateTimeImmutable $now = null,
        int $limit = 200
    ): array {
        $records = array();

        foreach ( $domain_candidates as $domain => $items ) {
            if ( ! is_array( $items ) ) {
                continue;
            }

            foreach ( $items as $item ) {
                if ( ! is_array( $item ) ) {
                    continue;
                }

                $declared_domain = sanitize_key( (string) ( $item['domain'] ?? '' ) );
                if ( '' === $declared_domain || $declared_domain !== sanitize_key( (string) $domain ) ) {
                    continue;
                }
                $records[] = $item;
            }
        }

        $items = My_Work_Read_Model::build( $records, $authorize, $now, $limit );
        $domain_counts = array();
        foreach ( $items as $item ) {
            $domain = sanitize_key( (string) ( $item['domain'] ?? '' ) );
            if ( '' === $domain ) {
                continue;
            }
            $domain_counts[ $domain ] = (int) ( $domain_counts[ $domain ] ?? 0 ) + 1;
        }
        ksort( $domain_counts );

        return array(
            'workspace' => 'my-work',
            'items'     => $items,
            'preview'   => V5_My_Work_Preview_Model::build( $items, $now ),
            'domainCounts' => $domain_counts,
            'readOnly'  => true,
            'authorizationBeforeRanking' => true,
            'routeOwnership'      => false,
            'productionActivated' => false,
        );
    }

    private function __construct() {}
}
