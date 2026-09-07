<?php

namespace MVM\Hub\Modules\Newsroom\Dashboard;

use MVM\Hub\Core\Capabilities;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Today_REST_Controller {
    private const NAMESPACE = 'mvm-hub/v1';
    private const ROUTE     = '/newsroom/today';

    public function register_routes(): void {
        register_rest_route(
            self::NAMESPACE,
            self::ROUTE,
            array(
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_today' ),
                'permission_callback' => array( $this, 'can_read_today' ),
            )
        );
    }

    public function can_read_today( \WP_REST_Request $request ): bool|\WP_Error {
        unset( $request );

        if ( ! is_user_logged_in() ) {
            return new \WP_Error(
                'mvm_hub_auth_required',
                'Log in om de MvM Hub te gebruiken.',
                array( 'status' => 401 )
            );
        }

        if ( ! Capabilities::can_access_newsroom() ) {
            return new \WP_Error(
                'mvm_hub_newsroom_forbidden',
                'Je hebt geen toegang tot de Newsroom.',
                array( 'status' => 403 )
            );
        }

        return true;
    }

    public function get_today( \WP_REST_Request $request ): \WP_REST_Response {
        unset( $request );
        return rest_ensure_response( ( new Today_Read_Model() )->snapshot() );
    }
}
