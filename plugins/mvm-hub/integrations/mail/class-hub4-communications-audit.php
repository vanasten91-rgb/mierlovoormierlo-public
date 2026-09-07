<?php

namespace MVM\Hub\Integrations\Mail;

use MVM\Hub\Core\Privacy;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Transitional metadata-only audit sink using the existing Hub4 audit table.
 *
 * No role is used for authorization/business logic. actor_role is intentionally
 * stored empty because the legacy schema requires the column.
 */
final class Hub4_Communications_Audit implements Communications_Audit {
    private \wpdb $wpdb;

    public function __construct( ?\wpdb $database = null ) {
        global $wpdb;
        $this->wpdb = $database ?? $wpdb;
    }

    /** @param array<string,mixed> $context */
    public function record( string $event, string $result, int $user_id, string $object_ref = '', array $context = array() ): void {
        $table = $this->wpdb->prefix . 'mvm_hub4_audit';
        if ( ! $this->table_exists( $table ) ) {
            return;
        }

        $event = mb_substr( sanitize_key( str_replace( '.', '_', $event ) ), 0, 190 );
        $result = mb_substr( sanitize_key( $result ), 0, 32 );
        $safe_context = Privacy::redact_for_audit( $context );
        if ( '' !== $object_ref ) {
            $safe_context['object_ref'] = mb_substr( sanitize_text_field( $object_ref ), 0, 64 );
        }

        $json = wp_json_encode( $safe_context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
        if ( false === $json ) {
            $json = '{}';
        }

        $this->wpdb->insert(
            $table,
            array(
                'occurred_at_utc' => gmdate( 'Y-m-d H:i:s' ),
                'actor_user_id'   => max( 0, $user_id ),
                'actor_role'      => '',
                'event_code'      => $event,
                'object_type'     => 'communications',
                'object_id'       => 0,
                'result'          => '' !== $result ? $result : 'unknown',
                'request_id'      => function_exists( 'wp_generate_uuid4' ) ? wp_generate_uuid4() : '00000000-0000-4000-8000-000000000000',
                'transition_from' => '',
                'transition_to'   => '',
                'context_json'    => $json,
            ),
            array( '%s', '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s' )
        );
    }

    private function table_exists( string $table ): bool {
        $found = $this->wpdb->get_var( $this->wpdb->prepare( 'SHOW TABLES LIKE %s', $this->wpdb->esc_like( $table ) ) );
        return $table === $found;
    }
}
