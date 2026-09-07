<?php

namespace MVM\Hub\Modules\Newsroom;

use MVM\Hub\Core\Capabilities;
use MVM\Hub\Core\Runtime_Gates;
use MVM\Hub\Modules\Newsroom\Assignments\Assignment_Write_Service;
use MVM\Hub\Modules\Newsroom\News\News_Write_Service;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Newsroom_Write_REST_Controller {
    private const NAMESPACE = 'mvm-hub/v1';

    public function register_read_routes(): void {
        register_rest_route( self::NAMESPACE, '/newsroom/news/(?P<id>\d+)', array(
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => array( $this, 'news_detail' ),
            'permission_callback' => array( $this, 'can_read_news' ),
            'args'                => self::id_args(),
        ) );
        register_rest_route( self::NAMESPACE, '/newsroom/assignments/(?P<id>\d+)', array(
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => array( $this, 'assignment_detail' ),
            'permission_callback' => array( $this, 'can_read_assignments' ),
            'args'                => self::id_args(),
        ) );
    }

    public function register_write_routes(): void {
        if ( ! Runtime_Gates::newsroom_writes_enabled() ) {
            return;
        }

        register_rest_route( self::NAMESPACE, '/newsroom/news', array(
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => array( $this, 'create_news' ),
            'permission_callback' => array( $this, 'can_create_news' ),
        ) );
        register_rest_route( self::NAMESPACE, '/newsroom/news/(?P<id>\d+)', array(
            'methods'             => \WP_REST_Server::EDITABLE,
            'callback'            => array( $this, 'update_news' ),
            'permission_callback' => array( $this, 'can_edit_news' ),
            'args'                => self::id_args(),
        ) );
        register_rest_route( self::NAMESPACE, '/newsroom/news/(?P<id>\d+)/transition', array(
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => array( $this, 'transition_news' ),
            'permission_callback' => array( $this, 'can_edit_news' ),
            'args'                => self::id_args(),
        ) );

        register_rest_route( self::NAMESPACE, '/newsroom/assignments', array(
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => array( $this, 'create_assignment' ),
            'permission_callback' => array( $this, 'can_create_assignment' ),
        ) );
        register_rest_route( self::NAMESPACE, '/newsroom/assignments/(?P<id>\d+)', array(
            'methods'             => \WP_REST_Server::EDITABLE,
            'callback'            => array( $this, 'update_assignment' ),
            'permission_callback' => array( $this, 'can_update_assignment' ),
            'args'                => self::id_args(),
        ) );
    }

    public function can_read_news(): bool {
        return is_user_logged_in() && Capabilities::can_access_newsroom();
    }

    public function can_read_assignments(): bool {
        return is_user_logged_in()
            && Capabilities::can_access_newsroom()
            && Capabilities::can_read( Capabilities::ASSIGNMENTS_VIEW, 'mvm_hub4_assignment_view' );
    }

    public function can_create_news( \WP_REST_Request $request ): bool|\WP_Error {
        return $this->authorize_write( $request, array( Capabilities::NEWS_CREATE ) );
    }

    public function can_edit_news( \WP_REST_Request $request ): bool|\WP_Error {
        return $this->authorize_write(
            $request,
            array(
                Capabilities::NEWS_EDIT_OWN,
                Capabilities::NEWS_EDIT_TEAM,
                Capabilities::NEWS_REVIEW,
                Capabilities::NEWS_PUBLISH,
                Capabilities::CORRECTIONS_MANAGE,
            )
        );
    }

    public function can_create_assignment( \WP_REST_Request $request ): bool|\WP_Error {
        return $this->authorize_write( $request, array( Capabilities::ASSIGNMENTS_MANAGE, Capabilities::NEWS_CREATE ) );
    }

    public function can_update_assignment( \WP_REST_Request $request ): bool|\WP_Error {
        return $this->authorize_write( $request, array( Capabilities::ASSIGNMENTS_MANAGE, Capabilities::ASSIGNMENTS_VIEW ) );
    }

    public function news_detail( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
        return self::response( ( new News_Write_Service() )->detail( absint( $request['id'] ) ) );
    }

    public function assignment_detail( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
        return self::response( ( new Assignment_Write_Service() )->get( absint( $request['id'] ) ) );
    }

    public function create_news( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
        return self::response( ( new News_Write_Service() )->create( self::json( $request ) ) );
    }

    public function update_news( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
        return self::response( ( new News_Write_Service() )->update( absint( $request['id'] ), self::json( $request ) ) );
    }

    public function transition_news( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
        $input = self::json( $request );
        return self::response(
            ( new News_Write_Service() )->transition(
                absint( $request['id'] ),
                (string) ( $input['to'] ?? '' ),
                $input
            )
        );
    }

    public function create_assignment( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
        return self::response( ( new Assignment_Write_Service() )->create( self::json( $request ) ) );
    }

    public function update_assignment( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
        return self::response( ( new Assignment_Write_Service() )->update( absint( $request['id'] ), self::json( $request ) ) );
    }

    /** @param array<int,string> $capabilities */
    private function authorize_write( \WP_REST_Request $request, array $capabilities ): bool|\WP_Error {
        if ( ! Runtime_Gates::newsroom_writes_enabled() || ! is_user_logged_in() || ! Capabilities::can_access_newsroom() ) {
            return new \WP_Error( 'mvm_newsroom_writes_disabled', 'Nieuwsroom-schrijfacties zijn niet beschikbaar.', array( 'status' => 403 ) );
        }

        $allowed = current_user_can( 'manage_options' );
        foreach ( $capabilities as $capability ) {
            if ( current_user_can( $capability ) ) {
                $allowed = true;
                break;
            }
        }
        if ( ! $allowed ) {
            return new \WP_Error( 'mvm_newsroom_write_capability', 'Je hebt geen toestemming voor deze actie.', array( 'status' => 403 ) );
        }

        $nonce = (string) $request->get_header( 'X-WP-Nonce' );
        if ( '' === $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
            return new \WP_Error( 'mvm_newsroom_nonce', 'De beveiligingstoken is ongeldig of verlopen.', array( 'status' => 403 ) );
        }
        return true;
    }

    /** @return array<string,array<string,mixed>> */
    private static function id_args(): array {
        return array(
            'id' => array(
                'required'          => true,
                'sanitize_callback' => 'absint',
                'validate_callback' => static fn( mixed $value ): bool => absint( $value ) > 0,
            ),
        );
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
