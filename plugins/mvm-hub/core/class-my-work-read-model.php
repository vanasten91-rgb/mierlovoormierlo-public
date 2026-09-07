<?php

namespace MVM\Hub\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Pure read-model assembler for the V5 Mijn Werk dashboard.
 *
 * The caller supplies candidate records from an already scoped repository and
 * an authorization callback. This class never queries WordPress by itself and
 * never exposes a record before authorization has returned an explicit allow.
 */
final class My_Work_Read_Model {
    /**
     * @param list<array<string,mixed>> $records
     * @param callable(array<string,mixed>):(bool|array<string,mixed>) $authorize
     * @return list<array<string,mixed>>
     */
    public static function build(
        array $records,
        callable $authorize,
        ?\DateTimeImmutable $now = null,
        int $limit = 100
    ): array {
        $now   = $now ?: new \DateTimeImmutable( 'now', new \DateTimeZone( 'UTC' ) );
        $limit = max( 1, min( 200, $limit ) );

        $authorized = array();
        foreach ( $records as $record ) {
            if ( ! is_array( $record ) ) {
                continue;
            }

            $item = Work_Item_Schema::normalize( $record );
            if ( null === $item ) {
                continue;
            }

            $item['id'] = max( 0, (int) ( $record['id'] ?? 0 ) );

            if ( in_array(
                (string) $item['status'],
                array( Work_Item_Schema::STATUS_DONE, Work_Item_Schema::STATUS_CANCELLED ),
                true
            ) ) {
                continue;
            }

            // Security boundary: authorization occurs before prioritization,
            // enrichment or inclusion in the returned read model.
            $decision = $authorize( $item );
            $allowed  = is_array( $decision )
                ? true === ( $decision['allowed'] ?? false )
                : true === $decision;

            if ( ! $allowed ) {
                continue;
            }

            $priority = My_Work_Prioritizer::score( $item, $now );
            $item['my_work_score']   = $priority['score'];
            $item['my_work_reasons'] = $priority['reasons'];
            $authorized[]            = $item;
        }

        usort(
            $authorized,
            static fn( array $left, array $right ): int => My_Work_Prioritizer::compare( $left, $right, $now )
        );

        return array_slice( $authorized, 0, $limit );
    }

    private function __construct() {}
}
