<?php

namespace MVM\Hub\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/** Metadata-only shared audit sink using the existing Hub4 audit table. */
final class Audit {
    private static bool $writing = false;

    /** @param array<string,mixed> $context */
    public static function record(
        string $event,
        string $result = 'success',
        string $object_type = '',
        int $object_id = 0,
        array $context = array(),
        string $transition_from = '',
        string $transition_to = ''
    ): void {
        if ( self::$writing ) {
            return;
        }
        self::$writing = true;
        try {
            global $wpdb;
            $table = $wpdb->prefix . 'mvm_hub4_audit';
            $exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
            if ( $table !== $exists ) {
                return;
            }

            $event = strtolower( trim( $event ) );
            $event = preg_replace( '/[^a-z0-9._-]/', '', $event );
            $event = mb_substr( (string) $event, 0, 190 );
            $result = sanitize_key( $result );
            if ( ! in_array( $result, array( 'success', 'denied', 'error', 'cancelled' ), true ) ) {
                $result = 'success';
            }

            $safe_context = Privacy::redact_for_audit( $context );
            $json = wp_json_encode( $safe_context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
            if ( false === $json ) {
                $json = '{}';
            }

            $wpdb->insert(
                $table,
                array(
                    'occurred_at_utc' => gmdate( 'Y-m-d H:i:s' ),
                    'actor_user_id'   => max( 0, get_current_user_id() ),
                    'actor_role'      => '',
                    'event_code'      => $event,
                    'object_type'     => sanitize_key( $object_type ),
                    'object_id'       => max( 0, $object_id ),
                    'result'          => $result,
                    'request_id'      => function_exists( 'wp_generate_uuid4' ) ? wp_generate_uuid4() : '00000000-0000-4000-8000-000000000000',
                    'transition_from' => sanitize_key( $transition_from ),
                    'transition_to'   => sanitize_key( $transition_to ),
                    'context_json'    => $json,
                ),
                array( '%s', '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s' )
            );
        } finally {
            self::$writing = false;
        }
    }

    private function __construct() {}
}
