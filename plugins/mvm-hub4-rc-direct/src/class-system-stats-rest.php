<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class MvM_Hub4_System_Stats_REST {
    public static function init(): void {
        add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
    }

    public static function register_routes(): void {
        register_rest_route(
            'mvm-hub4/v1',
            '/system/stats',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( __CLASS__, 'stats' ),
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

    public static function stats( WP_REST_Request $request ): WP_REST_Response {
        $fresh = rest_sanitize_boolean( $request->get_param( 'fresh' ) );
        $data  = MvM_Hub4_System_Stats::overview( $fresh );

        MvM_Hub4_Audit::log(
            'system.stats_view',
            'success',
            array(
                'object_type' => 'system_overview',
                'context'     => array( 'fresh' => (bool) $fresh ),
            )
        );

        return rest_ensure_response( $data );
    }
}
