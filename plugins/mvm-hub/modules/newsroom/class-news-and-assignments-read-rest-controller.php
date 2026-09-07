<?php

namespace MVM\Hub\Modules\Newsroom;

use MVM\Hub\Core\Capabilities;
use MVM\Hub\Modules\Newsroom\Assignments\Assignments_Read_Model;
use MVM\Hub\Modules\Newsroom\News\News_Read_Model;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class News_And_Assignments_Read_REST_Controller {
    private const NAMESPACE = 'mvm-hub/v1';

    public function register_routes(): void {
        register_rest_route(
            self::NAMESPACE,
            '/newsroom/news',
            array(
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => array( $this, 'news' ),
                'permission_callback' => static fn(): bool => is_user_logged_in()
                    && Capabilities::can_access_newsroom(),
                'args'                => $this->list_args(),
            )
        );

        register_rest_route(
            self::NAMESPACE,
            '/newsroom/assignments',
            array(
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => array( $this, 'assignments' ),
                'permission_callback' => static fn(): bool => is_user_logged_in()
                    && Capabilities::can_access_newsroom()
                    && Capabilities::can_read( Capabilities::ASSIGNMENTS_VIEW, 'mvm_hub4_assignment_view' ),
                'args'                => $this->list_args(),
            )
        );
    }

    public function news( \WP_REST_Request $request ): \WP_REST_Response {
        $payload = ( new News_Read_Model() )->list(
            (int) ( $request->get_param( 'page' ) ?: 1 ),
            (int) ( $request->get_param( 'per_page' ) ?: 20 ),
            sanitize_key( (string) $request->get_param( 'status' ) )
        );
        return rest_ensure_response( $payload );
    }

    public function assignments( \WP_REST_Request $request ): \WP_REST_Response {
        $payload = ( new Assignments_Read_Model() )->list(
            (int) ( $request->get_param( 'page' ) ?: 1 ),
            (int) ( $request->get_param( 'per_page' ) ?: 20 ),
            sanitize_key( (string) $request->get_param( 'status' ) )
        );
        return rest_ensure_response( $payload );
    }

    /** @return array<string,array<string,mixed>> */
    private function list_args(): array {
        return array(
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
            'status' => array(
                'default'           => '',
                'sanitize_callback' => 'sanitize_key',
            ),
        );
    }
}
