<?php

namespace MVM\Hub\Modules\Communications;

use MVM\Hub\Core\Capabilities;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Internal_Message_Read_REST_Controller {
    private const NAMESPACE = 'mvm-hub/v1';

    public function register_routes(): void {
        register_rest_route( self::NAMESPACE, '/communications/messages', array(
            'methods' => \WP_REST_Server::READABLE,
            'callback' => array( $this, 'list' ),
            'permission_callback' => array( $this, 'can_read' ),
            'args' => array(
                'page' => array( 'default' => 1, 'sanitize_callback' => 'absint', 'validate_callback' => static fn( mixed $value ): bool => (int) $value >= 1 && (int) $value <= 100 ),
                'per_page' => array( 'default' => 20, 'sanitize_callback' => 'absint', 'validate_callback' => static fn( mixed $value ): bool => (int) $value >= 1 && (int) $value <= 50 ),
            ),
        ) );
        register_rest_route( self::NAMESPACE, '/communications/messages/(?P<id>\d+)', array(
            'methods' => \WP_REST_Server::READABLE,
            'callback' => array( $this, 'get' ),
            'permission_callback' => array( $this, 'can_read' ),
            'args' => array( 'id' => array( 'sanitize_callback' => 'absint', 'validate_callback' => static fn( mixed $value ): bool => (int) $value > 0 ) ),
        ) );
        register_rest_route( self::NAMESPACE, '/communications/participants', array(
            'methods' => \WP_REST_Server::READABLE,
            'callback' => array( $this, 'participants' ),
            'permission_callback' => array( $this, 'can_read' ),
            'args' => array(
                'search' => array(
                    'required' => true,
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => static fn( mixed $value ): bool => is_string( $value ) && mb_strlen( trim( $value ) ) >= 2 && mb_strlen( trim( $value ) ) <= 80,
                ),
            ),
        ) );
    }

    public function can_read(): bool {
        return is_user_logged_in()
            && Capabilities::can_access_communications()
            && ( current_user_can( 'manage_options' ) || current_user_can( Capabilities::COMMUNICATIONS_ADMIN ) || current_user_can( Capabilities::MESSAGES_ACCESS ) );
    }

    public function list( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
        $payload = Communications_Service_Factory::internal_message_read_service()->list(
            (int) ( $request->get_param( 'page' ) ?: 1 ),
            (int) ( $request->get_param( 'per_page' ) ?: 20 )
        );
        return is_wp_error( $payload ) ? $payload : rest_ensure_response( $payload );
    }

    public function get( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
        $payload = Communications_Service_Factory::internal_message_read_service()->get( absint( $request->get_param( 'id' ) ) );
        return is_wp_error( $payload ) ? $payload : rest_ensure_response( $payload );
    }

    public function participants( \WP_REST_Request $request ): \WP_REST_Response {
        global $wpdb;

        $search = trim( sanitize_text_field( (string) $request->get_param( 'search' ) ) );
        if ( mb_strlen( $search ) < 2 ) {
            return rest_ensure_response( array( 'items' => array() ) );
        }

        $like = '%' . $wpdb->esc_like( $search ) . '%';
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT ID,display_name FROM {$wpdb->users} WHERE ID <> %d AND display_name LIKE %s ORDER BY display_name ASC LIMIT 24",
                get_current_user_id(),
                $like
            ),
            ARRAY_A
        );

        $items = array();
        foreach ( is_array( $rows ) ? $rows : array() as $row ) {
            $user_id = absint( $row['ID'] ?? 0 );
            if ( $user_id <= 0 ) {
                continue;
            }
            if (
                ! user_can( $user_id, 'manage_options' )
                && ! user_can( $user_id, Capabilities::COMMUNICATIONS_ADMIN )
                && ! user_can( $user_id, Capabilities::MESSAGES_ACCESS )
            ) {
                continue;
            }
            $items[] = array(
                'id'          => $user_id,
                'displayName' => mb_substr( sanitize_text_field( (string) ( $row['display_name'] ?? '' ) ), 0, 120 ),
            );
            if ( count( $items ) >= 12 ) {
                break;
            }
        }

        return rest_ensure_response( array( 'items' => array_values( $items ) ) );
    }
}
