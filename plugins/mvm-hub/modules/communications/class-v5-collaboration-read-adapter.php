<?php

namespace MVM\Hub\Modules\Communications;

use MVM\Hub\Core\Data_Classification;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Dormant, pure projection adapter for current Newsroom staff collaboration.
 *
 * Input is already capability-scoped by the current canonical owner. The
 * adapter performs no WordPress query and owns no mutation, hook or route.
 */
final class V5_Collaboration_Read_Adapter {
    /** @param list<array<string,mixed>> $items @return list<array<string,mixed>> */
    public static function board( array $items ): array {
        $owner = V5_Collaboration_Owner_Catalog::get( 'staff_board' );
        if ( ! is_array( $owner ) ) {
            return array();
        }

        return self::project_many( $items, $owner, 'board' );
    }

    /** @param list<array<string,mixed>> $items @return list<array<string,mixed>> */
    public static function chat( array $items ): array {
        $owner = V5_Collaboration_Owner_Catalog::get( 'team_chat' );
        if ( ! is_array( $owner ) ) {
            return array();
        }

        return self::project_many( $items, $owner, 'chat' );
    }

    /**
     * @param list<array<string,mixed>> $items
     * @param array<string,mixed> $owner
     * @return list<array<string,mixed>>
     */
    private static function project_many( array $items, array $owner, string $kind ): array {
        $max     = max( 1, min( 100, (int) ( $owner['max_items'] ?? 50 ) ) );
        $results = array();

        foreach ( $items as $item ) {
            if ( ! is_array( $item ) || count( $results ) >= $max ) {
                break;
            }

            $projected = 'board' === $kind
                ? self::project_board_item( $item, $owner )
                : self::project_chat_item( $item, $owner );

            if ( null !== $projected ) {
                $results[] = $projected;
            }
        }

        return array_values( $results );
    }

    /** @param array<string,mixed> $item @param array<string,mixed> $owner @return array<string,mixed>|null */
    private static function project_board_item( array $item, array $owner ): ?array {
        $id      = max( 0, (int) ( $item['id'] ?? 0 ) );
        $title   = trim( (string) ( $item['title'] ?? '' ) );
        $message = trim( (string) ( $item['message'] ?? '' ) );

        if ( 1 > $id || '' === $title || '' === $message ) {
            return null;
        }

        $priority = sanitize_key( (string) ( $item['priority'] ?? 'normal' ) );
        $priority = in_array( $priority, array( 'normal', 'important' ), true ) ? $priority : 'normal';

        return array(
            'id'                   => $id,
            'kind'                 => 'staff_board',
            'title'                => self::text( $title, 120 ),
            'message'              => self::text( $message, 4000 ),
            'authorId'             => max( 0, (int) ( $item['authorId'] ?? 0 ) ),
            'authorName'           => self::text( (string) ( $item['authorName'] ?? 'Staf' ), 120 ),
            'createdUtc'           => self::utc( (string) ( $item['createdUtc'] ?? '' ) ),
            'pinned'               => true === ( $item['pinned'] ?? false ),
            'priority'             => $priority,
            'canonical_owner'      => (string) $owner['current_owner'],
            'classification'       => Data_Classification::INTERNAL,
            'search_visibility'    => 'none',
            'ai_visibility'        => 'none',
            'audit_body_allowed'   => false,
            'read_only_projection' => true,
        );
    }

    /** @param array<string,mixed> $item @param array<string,mixed> $owner @return array<string,mixed>|null */
    private static function project_chat_item( array $item, array $owner ): ?array {
        $id      = max( 0, (int) ( $item['id'] ?? 0 ) );
        $message = trim( (string) ( $item['message'] ?? '' ) );

        if ( 1 > $id || '' === $message ) {
            return null;
        }

        return array(
            'id'                   => $id,
            'kind'                 => 'team_chat',
            'message'              => self::text( $message, (int) ( $owner['max_message_length'] ?? 1500 ) ),
            'authorId'             => max( 0, (int) ( $item['authorId'] ?? 0 ) ),
            'authorName'           => self::text( (string) ( $item['authorName'] ?? 'Staf' ), 120 ),
            'createdUtc'           => self::utc( (string) ( $item['createdUtc'] ?? '' ) ),
            'mine'                 => true === ( $item['mine'] ?? false ),
            'retentionDays'        => max( 1, (int) ( $owner['retention_days'] ?? 30 ) ),
            'canonical_owner'      => (string) $owner['current_owner'],
            'classification'       => Data_Classification::INTERNAL,
            'search_visibility'    => 'none',
            'ai_visibility'        => 'none',
            'audit_body_allowed'   => false,
            'read_only_projection' => true,
        );
    }

    private static function text( string $value, int $max_length ): string {
        $value      = trim( wp_strip_all_tags( $value, true ) );
        $max_length = max( 1, min( 4000, $max_length ) );

        if ( function_exists( 'mb_substr' ) ) {
            return (string) mb_substr( $value, 0, $max_length );
        }

        return substr( $value, 0, $max_length );
    }

    private static function utc( string $value ): string {
        $value = trim( $value );
        if ( '' === $value ) {
            return '';
        }

        $timestamp = strtotime( $value );
        return false === $timestamp ? '' : gmdate( 'c', $timestamp );
    }

    private function __construct() {}
}
