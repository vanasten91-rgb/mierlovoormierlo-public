<?php

namespace MVM\Hub\Modules\Communications;

use MVM\Hub\Core\Capabilities;
use MVM\Hub\Core\Runtime_Gates;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Write API for internal staff messages only.
 *
 * Mailbox mutations and external mail delivery intentionally do not live in this
 * controller. MvM Mail is a read-only surface; Communications writes are kept
 * solely for the private internal staff-message workflow.
 */
final class Internal_Message_Write_REST_Controller {
    private const NAMESPACE = 'mvm-hub/v1';

    public function register_routes(): void {
        if ( ! Runtime_Gates::communications_writes_enabled() ) {
            return;
        }

        register_rest_route( self::NAMESPACE, '/communications/messages', array(
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => array( $this, 'create_internal_message' ),
            'permission_callback' => array( $this, 'can_send_internal' ),
        ) );
        register_rest_route( self::NAMESPACE, '/communications/messages/(?P<id>\d+)/reply', array(
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => array( $this, 'reply_internal_message' ),
            'permission_callback' => array( $this, 'can_send_internal' ),
        ) );
        register_rest_route( self::NAMESPACE, '/communications/messages/(?P<id>\d+)/read', array(
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => array( $this, 'read_internal_message' ),
            'permission_callback' => array( $this, 'can_manage_internal' ),
        ) );
        register_rest_route( self::NAMESPACE, '/communications/messages/(?P<id>\d+)/archive', array(
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => array( $this, 'archive_internal_message' ),
            'permission_callback' => array( $this, 'can_manage_internal' ),
        ) );
        register_rest_route( self::NAMESPACE, '/communications/messages/(?P<id>\d+)', array(
            'methods'             => \WP_REST_Server::DELETABLE,
            'callback'            => array( $this, 'delete_internal_message' ),
            'permission_callback' => array( $this, 'can_manage_internal' ),
        ) );
    }

    public function can_send_internal( \WP_REST_Request $request ): bool|\WP_Error {
        return $this->authorize( $request, Capabilities::MESSAGES_SEND );
    }

    public function can_manage_internal( \WP_REST_Request $request ): bool|\WP_Error {
        return $this->authorize( $request, Capabilities::MESSAGES_MANAGE_OWN );
    }

    public function create_internal_message( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
        $input = self::json( $request );
        return self::response(
            Communications_Service_Factory::internal_message_service()->create(
                is_array( $input['participants'] ?? null ) ? $input['participants'] : array(),
                (string) ( $input['body'] ?? '' )
            )
        );
    }

    public function reply_internal_message( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
        $input = self::json( $request );
        return self::response(
            Communications_Service_Factory::internal_message_service()->reply(
                absint( $request->get_param( 'id' ) ),
                (string) ( $input['body'] ?? '' )
            )
        );
    }

    public function read_internal_message( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
        return self::response(
            Communications_Service_Factory::internal_message_service()->mark_read( absint( $request->get_param( 'id' ) ) )
        );
    }

    public function archive_internal_message( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
        return self::response(
            Communications_Service_Factory::internal_message_service()->archive( absint( $request->get_param( 'id' ) ) )
        );
    }

    public function delete_internal_message( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
        return self::response(
            Communications_Service_Factory::internal_message_service()->delete( absint( $request->get_param( 'id' ) ) )
        );
    }

    private function authorize( \WP_REST_Request $request, string $capability ): bool|\WP_Error {
        if ( ! Runtime_Gates::communications_writes_enabled() || ! is_user_logged_in() || ! Capabilities::can_access_communications() ) {
            return new \WP_Error( 'mvm_communications_write_forbidden', 'Schrijfacties zijn niet beschikbaar.', array( 'status' => 403 ) );
        }
        if ( ! current_user_can( 'manage_options' ) && ! current_user_can( Capabilities::COMMUNICATIONS_ADMIN ) && ! current_user_can( $capability ) ) {
            return new \WP_Error( 'mvm_communications_capability', 'Je hebt geen toestemming voor deze actie.', array( 'status' => 403 ) );
        }
        $nonce = (string) $request->get_header( 'X-WP-Nonce' );
        if ( '' === $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
            return new \WP_Error( 'mvm_communications_nonce', 'De beveiligingstoken is ongeldig of verlopen.', array( 'status' => 403 ) );
        }
        return true;
    }

    /** @return array<string,mixed> */
    private static function json( \WP_REST_Request $request ): array {
        $input = $request->get_json_params();
        return is_array( $input ) ? $input : array();
    }

    private static function response( mixed $result ): \WP_REST_Response|\WP_Error {
        return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
    }
}
