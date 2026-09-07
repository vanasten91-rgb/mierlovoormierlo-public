<?php

namespace MVM\Hub\Modules\Communications;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Provider-neutral bounded mailbox query normalization.
 */
final class Mail_Query {
    private const SORTS  = array( 'date', 'sender', 'subject', 'size', 'status' );
    private const STATES = array( 'all', 'read', 'unread', 'flagged', 'unflagged' );

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public static function normalize( array $input ): array {
        $sort      = sanitize_key( (string) ( $input['sort'] ?? 'date' ) );
        $direction = strtolower( sanitize_key( (string) ( $input['direction'] ?? 'desc' ) ) );
        $state     = sanitize_key( (string) ( $input['state'] ?? 'all' ) );
        $search    = trim( sanitize_text_field( (string) ( $input['search'] ?? '' ) ) );
        $cursor    = self::cursor( (string) ( $input['cursor'] ?? '' ) );

        return array(
            'page'      => max( 1, min( 100, (int) ( $input['page'] ?? 1 ) ) ),
            'perPage'   => max( 1, min( 50, (int) ( $input['perPage'] ?? $input['per_page'] ?? 20 ) ) ),
            'sort'      => in_array( $sort, self::SORTS, true ) ? $sort : 'date',
            'direction' => in_array( $direction, array( 'asc', 'desc' ), true ) ? $direction : 'desc',
            'state'     => in_array( $state, self::STATES, true ) ? $state : 'all',
            'search'    => mb_substr( $search, 0, 120 ),
            'cursor'    => $cursor,
        );
    }

    private static function cursor( string $value ): string {
        $value = trim( $value );
        if ( '' === $value ) {
            return '';
        }

        return strlen( $value ) <= 256 && 1 === preg_match( '/^[A-Za-z0-9._~:-]+$/', $value ) ? $value : '';
    }

    private function __construct() {}
}
