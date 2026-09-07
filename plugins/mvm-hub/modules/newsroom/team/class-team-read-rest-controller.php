<?php

namespace MVM\Hub\Modules\Newsroom\Team;

use MVM\Hub\Core\Capabilities;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Team_Read_REST_Controller {
    private const NAMESPACE = 'mvm-hub/v1';

    public function register_routes(): void {
        register_rest_route(
            self::NAMESPACE,
            '/newsroom/team',
            array(
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => array( $this, 'list' ),
                'permission_callback' => static fn(): bool => is_user_logged_in()
                    && Capabilities::can_access_newsroom()
                    && Capabilities::can_read( Capabilities::TEAM_VIEW, 'mvm_hub4_team_view' ),
                'args'                => array(
                    'page' => array(
                        'default'           => 1,
                        'sanitize_callback' => 'absint',
                        'validate_callback' => static fn( mixed $value ): bool => (int) $value >= 1 && (int) $value <= 100,
                    ),
                    'per_page' => array(
                        'default'           => 20,
                        'sanitize_callback' => 'absint',
                        'validate_callback' => static fn( mixed $value ): bool => (int) $value >= 1 && (int) $value <= 50,
                    ),
                ),
            )
        );
    }

    public function list( \WP_REST_Request $request ): \WP_REST_Response {
        $payload = ( new Team_Read_Model() )->list(
            (int) ( $request->get_param( 'page' ) ?: 1 ),
            (int) ( $request->get_param( 'per_page' ) ?: 20 )
        );
        return rest_ensure_response( $payload );
    }
}
