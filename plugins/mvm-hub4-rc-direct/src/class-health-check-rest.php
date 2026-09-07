<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/class-encyclopedia-health.php';
require_once __DIR__ . '/class-smart-links-health.php';

final class MvM_Hub4_Health_Check_REST {
    public static function init(): void {
        add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
    }

    public static function register_routes(): void {
        register_rest_route(
            'mvm-hub4/v1',
            '/system/health',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( __CLASS__, 'health' ),
                'permission_callback' => MvM_Hub4_Security::require_capability( MvM_Hub4_Capabilities::SYSTEM_OVERVIEW ),
                'args'                => array(
                    'fresh' => array(
                        'sanitize_callback' => 'rest_sanitize_boolean',
                        'validate_callback' => 'rest_validate_request_arg',
                    ),
                ),
            )
        );
    }

    public static function health( WP_REST_Request $request ): WP_REST_Response {
        $fresh = rest_sanitize_boolean( $request->get_param( 'fresh' ) );
        $data  = MvM_Hub4_Health_Check::run( $fresh );
        $data  = MvM_Hub4_Encyclopedia_Health::extend( $data, $fresh );
        $data  = MvM_Hub4_Smart_Links_Health::extend( $data, $fresh );

        MvM_Hub4_Audit::log(
            'system.health_view',
            'success',
            array(
                'object_type' => 'system_health',
                'context'     => array(
                    'fresh'    => (bool) $fresh,
                    'status'   => sanitize_key( (string) $data['status'] ),
                    'critical' => (int) ( $data['summary']['critical'] ?? 0 ),
                    'warning'  => (int) ( $data['summary']['warning'] ?? 0 ),
                ),
            )
        );

        return rest_ensure_response( $data );
    }
}
