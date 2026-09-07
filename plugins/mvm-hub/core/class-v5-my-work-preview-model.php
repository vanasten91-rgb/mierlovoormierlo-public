<?php

namespace MVM\Hub\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Dormant presentation model for already-authorized My Work items.
 *
 * Input must come from My_Work_Read_Model or another equally authorized source.
 * This class performs no data access and never broadens access; it only groups
 * authorized work into an explainable preview structure for the future V5 UI.
 */
final class V5_My_Work_Preview_Model {
    /**
     * @param list<array<string,mixed>> $authorized_items
     * @return array<string,mixed>
     */
    public static function build( array $authorized_items, ?\DateTimeImmutable $now = null ): array {
        $now = $now ?: new \DateTimeImmutable( 'now', new \DateTimeZone( 'UTC' ) );

        $lanes = array(
            'blocked'  => array(),
            'overdue'  => array(),
            'today'    => array(),
            'upcoming' => array(),
        );

        $summary = array(
            'total'      => 0,
            'blocked'    => 0,
            'overdue'    => 0,
            'today'      => 0,
            'upcoming'   => 0,
            'urgent'     => 0,
            'protected'  => 0,
        );

        foreach ( $authorized_items as $item ) {
            if ( ! is_array( $item ) ) {
                continue;
            }

            $status = sanitize_key( (string) ( $item['status'] ?? '' ) );
            if ( in_array( $status, array( Work_Item_Schema::STATUS_DONE, Work_Item_Schema::STATUS_CANCELLED ), true ) ) {
                continue;
            }

            $card = self::card( $item );
            if ( null === $card ) {
                continue;
            }

            $lane = self::lane_for( $item, $now );
            $lanes[ $lane ][] = $card;
            ++$summary['total'];
            ++$summary[ $lane ];

            if ( Work_Item_Schema::PRIORITY_URGENT === (string) $card['priority'] ) {
                ++$summary['urgent'];
            }

            if ( in_array(
                (string) $card['classification'],
                array( Data_Classification::SOURCE_PROTECTED, Data_Classification::SECRET ),
                true
            ) ) {
                ++$summary['protected'];
            }
        }

        return array(
            'workspace'           => 'my-work',
            'title'               => 'Mijn Werk',
            'subtitle'            => 'Wat nu aandacht nodig heeft, met reden en deadline.',
            'summary'             => $summary,
            'lanes'               => $lanes,
            'routeOwnership'      => false,
            'productionActivated' => false,
        );
    }

    /** @param array<string,mixed> $item @return array<string,mixed>|null */
    private static function card( array $item ): ?array {
        $id    = max( 0, (int) ( $item['id'] ?? 0 ) );
        $title = sanitize_text_field( (string) ( $item['title'] ?? '' ) );
        if ( 0 === $id || '' === $title ) {
            return null;
        }

        $reasons = array();
        foreach ( is_array( $item['my_work_reasons'] ?? null ) ? $item['my_work_reasons'] : array() as $reason ) {
            $reason = sanitize_text_field( (string) $reason );
            if ( '' !== $reason ) {
                $reasons[] = $reason;
            }
        }

        return array(
            'id'             => $id,
            'title'          => $title,
            'domain'         => sanitize_key( (string) ( $item['domain'] ?? '' ) ),
            'status'         => sanitize_key( (string) ( $item['status'] ?? '' ) ),
            'priority'       => sanitize_key( (string) ( $item['priority'] ?? '' ) ),
            'workflowState'  => sanitize_key( (string) ( $item['workflow_state'] ?? '' ) ),
            'deadlineUtc'    => (string) ( $item['deadline_utc'] ?? '' ),
            'classification' => Data_Classification::normalize( (string) ( $item['classification'] ?? '' ) ),
            'score'          => (int) ( $item['my_work_score'] ?? 0 ),
            'reasons'        => array_values( array_unique( $reasons ) ),
            'blockedReason'  => sanitize_textarea_field( (string) ( $item['blocker_reason'] ?? '' ) ),
        );
    }

    /** @param array<string,mixed> $item */
    private static function lane_for( array $item, \DateTimeImmutable $now ): string {
        if ( Work_Item_Schema::STATUS_BLOCKED === sanitize_key( (string) ( $item['status'] ?? '' ) ) ) {
            return 'blocked';
        }

        $deadline = self::deadline( $item['deadline_utc'] ?? '' );
        if ( null === $deadline ) {
            return 'upcoming';
        }

        if ( $deadline < $now ) {
            return 'overdue';
        }

        if ( $deadline->getTimestamp() <= $now->getTimestamp() + DAY_IN_SECONDS ) {
            return 'today';
        }

        return 'upcoming';
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
