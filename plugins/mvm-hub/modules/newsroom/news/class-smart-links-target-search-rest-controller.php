<?php

namespace MVM\Hub\Modules\Newsroom\News;

use MVM\Hub\Core\Capabilities;
use MVM\Hub\Integrations\Encyclopedie\Smart_Links_Target_Search;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Read-only target discovery for the newsroom editor.
 * Search results are suggestions only; no link/content mutation occurs here.
 */
final class Smart_Links_Target_Search_REST_Controller {
    private const NAMESPACE = 'mvm-hub/v1';
    private const ROUTE     = '/newsroom/encyclopedia/targets';

    public function register_routes(): void {
        register_rest_route(
            self::NAMESPACE,
            self::ROUTE,
            array(
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => array( $this, 'search' ),
                'permission_callback' => array( $this, 'can_search' ),
                'args'                => array(
                    'q' => array(
                        'required'          => true,
                        'sanitize_callback' => 'sanitize_text_field',
                        'validate_callback' => static function ( mixed $value ): bool {
                            $value = trim( (string) $value );
                            $length = function_exists( 'mb_strlen' ) ? mb_strlen( $value ) : strlen( $value );
                            return $length >= 2 && $length <= 120;
                        },
                    ),
                    'limit' => array(
                        'default'           => 8,
                        'sanitize_callback' => 'absint',
                        'validate_callback' => static fn( mixed $value ): bool => (int) $value >= 1 && (int) $value <= 12,
                    ),
                ),
            )
        );
    }

    public function can_search(): bool {
        if ( ! is_user_logged_in() || ! Capabilities::can_access_newsroom() ) {
            return false;
        }

        return current_user_can( 'manage_options' )
            || current_user_can( Capabilities::NEWS_CREATE )
            || current_user_can( Capabilities::NEWS_EDIT_OWN )
            || current_user_can( Capabilities::NEWS_EDIT_TEAM )
            || current_user_can( Capabilities::NEWS_REVIEW )
            || current_user_can( Capabilities::NEWS_PUBLISH )
            || current_user_can( 'mvm_hub4_news_view' );
    }

    public function search( \WP_REST_Request $request ): \WP_REST_Response {
        return rest_ensure_response(
            Smart_Links_Target_Search::search(
                (string) $request->get_param( 'q' ),
                (int) ( $request->get_param( 'limit' ) ?: 8 )
            )
        );
    }
}
