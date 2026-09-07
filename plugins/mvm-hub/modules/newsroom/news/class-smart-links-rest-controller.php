<?php

namespace MVM\Hub\Modules\Newsroom\News;

use MVM\Hub\Core\Capabilities;
use MVM\Hub\Integrations\Encyclopedie\Smart_Links_Context;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Smart_Links_REST_Controller {
    private const NAMESPACE = 'mvm-hub/v1';
    private const ROUTE     = '/newsroom/news/(?P<id>\d+)/smart-links';

    public function register_routes(): void {
        register_rest_route(
            self::NAMESPACE,
            self::ROUTE,
            array(
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_context' ),
                'permission_callback' => array( $this, 'permissions_check' ),
                'args'                => array(
                    'id' => array(
                        'type'              => 'integer',
                        'required'          => true,
                        'sanitize_callback' => 'absint',
                    ),
                    'limit' => array(
                        'type'              => 'integer',
                        'default'           => 8,
                        'minimum'           => 1,
                        'maximum'           => 12,
                        'sanitize_callback' => 'absint',
                    ),
                ),
            )
        );
    }

    public function permissions_check( \WP_REST_Request $request ): bool|\WP_Error {
        if ( ! is_user_logged_in() || ! Capabilities::can_access_newsroom() ) {
            return new \WP_Error( 'mvm_hub_newsroom_forbidden', 'Geen toegang tot de Newsroom.', array( 'status' => 403 ) );
        }

        $post_id = absint( $request['id'] );
        $post    = get_post( $post_id );
        if ( ! $post instanceof \WP_Post || 'post' !== $post->post_type ) {
            return new \WP_Error( 'mvm_hub_news_missing', 'Nieuwsartikel niet gevonden.', array( 'status' => 404 ) );
        }

        if ( current_user_can( 'manage_options' ) || current_user_can( 'edit_post', $post_id ) ) {
            return true;
        }

        if ( Capabilities::can_read( Capabilities::NEWS_REVIEW, 'mvm_hub4_news_review' ) ) {
            return true;
        }

        return new \WP_Error( 'mvm_hub_news_object_forbidden', 'Geen toegang tot dit nieuwsartikel.', array( 'status' => 403 ) );
    }

    public function get_context( \WP_REST_Request $request ): \WP_REST_Response {
        $post_id = absint( $request['id'] );
        $limit   = min( 12, max( 1, absint( $request->get_param( 'limit' ) ?: 8 ) ) );

        return new \WP_REST_Response(
            array(
                'postId'     => $post_id,
                'smartLinks' => Smart_Links_Context::for_news_post( $post_id, $limit ),
            ),
            200
        );
    }
}
