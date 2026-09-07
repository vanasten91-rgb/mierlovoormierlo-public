<?php

namespace MVM\Hub\Modules\Communications;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Pure state machine for bounded Mail V2 synchronization.
 *
 * It accepts provider-safe message state only: UID, revision, seen, flagged and
 * size. It does not know IMAP, WordPress persistence, credentials or content.
 */
final class V5_Mail_Sync_State_Machine {
    private const MAX_EVENTS = 5000;
    private const MAX_BATCH  = 200;
    private const MAX_UID    = 4294967295;

    /**
     * @param array<int|string,array<string,mixed>> $messages
     * @return array<int,array<string,mixed>>
     */
    public static function initial_events( array $messages ): array {
        $events = array();
        foreach ( self::normalize_messages( $messages ) as $uid => $message ) {
            $events[] = self::upsert_event( $uid, $message );
        }
        return array_slice( $events, 0, self::MAX_EVENTS );
    }

    /**
     * @param array<int|string,array<string,mixed>> $previous
     * @param array<int|string,array<string,mixed>> $current
     * @return array<int,array<string,mixed>>
     */
    public static function diff_events( array $previous, array $current ): array {
        $previous = self::normalize_messages( $previous );
        $current  = self::normalize_messages( $current );
        $events   = array();

        foreach ( $current as $uid => $message ) {
            if ( ! isset( $previous[ $uid ] ) || $previous[ $uid ] !== $message ) {
                $events[] = self::upsert_event( $uid, $message );
            }
        }
        foreach ( $previous as $uid => $_message ) {
            if ( ! isset( $current[ $uid ] ) ) {
                $events[] = array( 'type' => 'deleted', 'uid' => (string) $uid );
            }
        }

        return array_slice( $events, 0, self::MAX_EVENTS );
    }

    /**
     * @param array<int,array<string,mixed>> $events
     * @return array{events:array<int,array<string,mixed>>,nextOffset:int,hasMore:bool}
     */
    public static function page( array $events, int $offset, int $limit ): array {
        $events = array_values( array_slice( $events, 0, self::MAX_EVENTS ) );
        $offset = max( 0, min( self::MAX_EVENTS, $offset ) );
        $limit  = max( 1, min( self::MAX_BATCH, $limit ) );
        $slice  = array_slice( $events, $offset, $limit );
        $next   = $offset + count( $slice );

        return array(
            'events'     => $slice,
            'nextOffset' => $next,
            'hasMore'    => $next < count( $events ),
        );
    }

    /**
     * IMAP UIDs are unsigned 32-bit positive integers. Persisted/provider state
     * is accepted only when its UID is already in canonical decimal form. This
     * prevents leading-zero aliases and integer-overflow collisions from being
     * silently normalized into a different message identity.
     *
     * PHP converts canonical numeric-string array keys to integer keys, so the
     * returned map intentionally uses integer keys while emitted event UIDs stay
     * strings at the API boundary.
     *
     * @param array<int|string,array<string,mixed>> $messages
     * @return array<int,array<string,mixed>>
     */
    public static function normalize_messages( array $messages ): array {
        $normalized = array();
        foreach ( array_slice( $messages, 0, self::MAX_EVENTS, true ) as $uid => $message ) {
            $canonical_uid = self::canonical_uid( $uid );
            if ( null === $canonical_uid || ! is_array( $message ) ) {
                continue;
            }
            $revision = self::opaque( (string) ( $message['revision'] ?? '' ), 128 );
            if ( '' === $revision ) {
                continue;
            }
            $normalized[ $canonical_uid ] = array(
                'revision' => $revision,
                'seen'     => true === ( $message['seen'] ?? false ),
                'flagged'  => true === ( $message['flagged'] ?? false ),
                'size'     => max( 0, min( 100000000, (int) ( $message['size'] ?? 0 ) ) ),
            );
        }
        ksort( $normalized, SORT_NUMERIC );
        return $normalized;
    }

    /** @param array<string,mixed> $message @return array<string,mixed> */
    private static function upsert_event( int|string $uid, array $message ): array {
        return array(
            'type'     => 'upsert',
            'uid'      => (string) $uid,
            'revision' => (string) $message['revision'],
            'seen'     => true === $message['seen'],
            'flagged'  => true === $message['flagged'],
            'size'     => (int) $message['size'],
        );
    }

    private static function canonical_uid( int|string $uid ): ?string {
        $uid = (string) $uid;
        if ( 1 !== preg_match( '/^[1-9][0-9]{0,9}$/D', $uid ) ) {
            return null;
        }
        if ( 10 === strlen( $uid ) && strcmp( $uid, (string) self::MAX_UID ) > 0 ) {
            return null;
        }
        return $uid;
    }

    private static function opaque( string $value, int $max ): string {
        $value = trim( $value );
        if ( '' === $value || strlen( $value ) > $max ) {
            return '';
        }
        return 1 === preg_match( '/^[A-Za-z0-9._:-]+$/', $value ) ? $value : '';
    }

    private function __construct() {}
}
