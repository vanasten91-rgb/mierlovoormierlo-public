<?php

namespace MVM\Hub\Modules\Agenda;

use MVM\Hub\Core\My_Work_Read_Model;
use MVM\Hub\Core\Work_Item_Schema;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Dormant V5 Agenda workspace projection over the current editorial calendar.
 * It never queries or mutates calendar storage; the caller supplies scoped rows.
 */
final class V5_Agenda_Preview_Model {
    /**
     * @param list<array<string,mixed>> $agenda_rows
     * @param callable(array<string,mixed>):(bool|array<string,mixed>) $authorize
     * @return array<string,mixed>
     */
    public static function build(
        array $agenda_rows,
        callable $authorize,
        ?\DateTimeImmutable $now = null,
        int $limit = 100
    ): array {
        $now = $now ?: new \DateTimeImmutable( 'now', new \DateTimeZone( 'UTC' ) );
        $candidates = array();

        foreach ( $agenda_rows as $row ) {
            if ( ! is_array( $row ) ) {
                continue;
            }
            $candidate = V5_Agenda_Read_Adapter::to_work_item( $row );
            if ( null !== $candidate ) {
                $candidates[] = $candidate;
            }
        }

        $items = My_Work_Read_Model::build( $candidates, $authorize, $now, $limit );
        $queues = array(
            'next24h' => array(),
            'next7d'  => array(),
            'later'   => array(),
            'blocked' => array(),
        );

        foreach ( $items as $item ) {
            $status = sanitize_key( (string) ( $item['status'] ?? '' ) );
            if ( Work_Item_Schema::STATUS_BLOCKED === $status ) {
                $queues['blocked'][] = $item;
                continue;
            }

            $deadline = self::deadline( $item['deadline_utc'] ?? '' );
            if ( null === $deadline ) {
                $queues['later'][] = $item;
                continue;
            }

            $seconds = $deadline->getTimestamp() - $now->getTimestamp();
            if ( $seconds <= DAY_IN_SECONDS ) {
                $queues['next24h'][] = $item;
            } elseif ( $seconds <= 7 * DAY_IN_SECONDS ) {
                $queues['next7d'][] = $item;
            } else {
                $queues['later'][] = $item;
            }
        }

        return array(
            'workspace'            => 'agenda',
            'counts'               => array_map( 'count', $queues ),
            'queues'               => $queues,
            'items'                => $items,
            'readOnly'             => true,
            'canonicalOwner'       => 'current-newsroom-editorial-calendar',
            'legacyOwnershipPreserved' => true,
            'routeOwnership'       => false,
            'productionActivated'  => false,
        );
    }

    /** @param mixed $value */
    private static function deadline( mixed $value ): ?\DateTimeImmutable {
        $value = is_string( $value ) ? trim( $value ) : '';
        if ( '' === $value ) {
            return null;
        }

        try {
            return ( new \DateTimeImmutable( $value ) )->setTimezone( new \DateTimeZone( 'UTC' ) );
        } catch ( \Throwable ) {
            return null;
        }
    }

    private function __construct() {}
}
