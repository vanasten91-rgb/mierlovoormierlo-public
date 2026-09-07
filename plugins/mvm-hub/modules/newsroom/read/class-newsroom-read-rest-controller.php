<?php

namespace MVM\Hub\Modules\Newsroom\Read;

use MVM\Hub\Core\Capabilities;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Newsroom_Read_REST_Controller {
    private const NAMESPACE = 'mvm-hub/v1';

    public function register_routes(): void {
        $this->register_list_route(
            '/newsroom/sources',
            Capabilities::SOURCES_VIEW,
            'mvm_hub4_sources_view',
            'sources'
        );
        $this->register_list_route(
            '/newsroom/radar',
            Capabilities::RADAR_VIEW,
            'mvm_hub4_signal_view',
            'radar'
        );
        $this->register_list_route(
            '/newsroom/agenda',
            Capabilities::AGENDA_VIEW,
            'mvm_hub4_calendar_view',
            'agenda'
        );
    }

    private function register_list_route( string $route, string $capability, string $legacy_capability, string $method ): void {
        register_rest_route(
            self::NAMESPACE,
            $route,
            array(
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => function ( \WP_REST_Request $request ) use ( $method ): \WP_REST_Response {
                    $model = new Newsroom_Read_Model();
                    $page = (int) $request->get_param( 'page' );
                    $per_page = (int) $request->get_param( 'per_page' );
                    $status = sanitize_key( (string) $request->get_param( 'status' ) );

                    /** @var array<string,mixed> $payload */
                    $payload = $model->{$method}( $page ?: 1, $per_page ?: 20, $status );
                    return rest_ensure_response( $payload );
                },
                'permission_callback' => static function () use ( $capability, $legacy_capability ): bool {
                    return is_user_logged_in()
                        && Capabilities::can_access_newsroom()
                        && Capabilities::can_read( $capability, $legacy_capability );
                },
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
                    'status' => array(
                        'default'           => '',
                        'sanitize_callback' => 'sanitize_key',
                    ),
                ),
            )
        );
    }
}
