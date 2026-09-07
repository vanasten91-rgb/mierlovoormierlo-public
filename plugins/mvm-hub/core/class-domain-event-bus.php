<?php

namespace MVM\Hub\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Dormant synchronous domain-event bus for MvM Hub V5.
 *
 * It is deliberately framework-local: no WordPress hooks are registered and no
 * event is dispatched across requests. Domain services may later wrap this bus
 * with queue/outbox persistence once that storage has its own migration gate.
 */
final class Domain_Event_Bus {
    /** @var array<string,list<callable>> */
    private array $listeners = array();

    public function subscribe( string $event_name, callable $listener ): bool {
        $event_name = self::normalize_event_name( $event_name );
        if ( '' === $event_name ) {
            return false;
        }

        $this->listeners[ $event_name ] ??= array();
        $this->listeners[ $event_name ][] = $listener;
        return true;
    }

    /**
     * Dispatch an already-authorized, already-sanitized domain event.
     *
     * Event payloads are intentionally plain arrays. Sensitive domains must
     * publish metadata-only payloads when the event crosses a trust boundary.
     *
     * @param array<string,mixed> $payload
     * @return list<array{listener:int,ok:bool,error:string}>
     */
    public function dispatch( string $event_name, array $payload = array() ): array {
        $event_name = self::normalize_event_name( $event_name );
        if ( '' === $event_name ) {
            return array();
        }

        $results = array();
        foreach ( $this->listeners[ $event_name ] ?? array() as $index => $listener ) {
            try {
                $listener( $event_name, $payload );
                $results[] = array(
                    'listener' => (int) $index,
                    'ok'       => true,
                    'error'    => '',
                );
            } catch ( \Throwable $error ) {
                // The bus reports bounded error metadata only. It does not copy
                // event payloads into logs or exceptions itself.
                $results[] = array(
                    'listener' => (int) $index,
                    'ok'       => false,
                    'error'    => sanitize_text_field( $error->getMessage() ),
                );
            }
        }

        return $results;
    }

    /** @return list<string> */
    public function subscribed_events(): array {
        $events = array_keys( $this->listeners );
        sort( $events, SORT_STRING );
        return array_values( $events );
    }

    private static function normalize_event_name( string $event_name ): string {
        $event_name = strtolower( trim( $event_name ) );
        if ( '' === $event_name || ! preg_match( '/^[a-z][a-z0-9_.-]{2,79}$/', $event_name ) ) {
            return '';
        }

        return $event_name;
    }
}
