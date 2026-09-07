<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class MvM_Hub4_Newsroom_REST {
    public static function init(): void {
        add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
    }

    public static function register_routes(): void {
        register_rest_route(
            'mvm-hub4/v1',
            '/assignments',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( __CLASS__, 'assignments' ),
                    'permission_callback' => MvM_Hub4_Security::require_capability( MvM_Hub4_Capabilities::ASSIGNMENT_VIEW ),
                    'args'                => array(
                        'scope' => array( 'sanitize_callback' => 'sanitize_key' ),
                        'status' => array( 'sanitize_callback' => 'sanitize_key' ),
                        'limit' => array( 'sanitize_callback' => 'absint' ),
                    ),
                ),
                array(
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => array( __CLASS__, 'create_assignment' ),
                    'permission_callback' => MvM_Hub4_Security::require_capability( MvM_Hub4_Capabilities::ASSIGNMENT_CREATE ),
                ),
            )
        );

        register_rest_route(
            'mvm-hub4/v1',
            '/assignments/(?P<id>\d+)',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( __CLASS__, 'update_assignment' ),
                'permission_callback' => MvM_Hub4_Security::require_capability( MvM_Hub4_Capabilities::ASSIGNMENT_VIEW ),
                'args'                => array(
                    'id' => array(
                        'required'          => true,
                        'sanitize_callback' => 'absint',
                        'validate_callback' => static fn( mixed $value ): bool => absint( $value ) > 0,
                    ),
                ),
            )
        );

        register_rest_route(
            'mvm-hub4/v1',
            '/news/(?P<id>\d+)/checklist',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( __CLASS__, 'checklist' ),
                    'permission_callback' => MvM_Hub4_Security::require_capability( MvM_Hub4_Capabilities::NEWS_VIEW ),
                    'args'                => array(
                        'id' => array(
                            'required'          => true,
                            'sanitize_callback' => 'absint',
                            'validate_callback' => static fn( mixed $value ): bool => absint( $value ) > 0,
                        ),
                    ),
                ),
                array(
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => array( __CLASS__, 'update_checklist' ),
                    'permission_callback' => MvM_Hub4_Security::require_capability( MvM_Hub4_Capabilities::CHECKLIST_USE ),
                    'args'                => array(
                        'id' => array(
                            'required'          => true,
                            'sanitize_callback' => 'absint',
                            'validate_callback' => static fn( mixed $value ): bool => absint( $value ) > 0,
                        ),
                    ),
                ),
            )
        );

        register_rest_route(
            'mvm-hub4/v1',
            '/news-radar',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( __CLASS__, 'news_radar' ),
                'permission_callback' => MvM_Hub4_Security::require_capability( MvM_Hub4_Capabilities::NEWS_VIEW ),
                'args'                => array(
                    'limit' => array(
                        'sanitize_callback' => 'absint',
                        'validate_callback' => static fn( mixed $value ): bool => absint( $value ) <= 200,
                    ),
                ),
            )
        );

        register_rest_route(
            'mvm-hub4/v1',
            '/news-radar/(?P<id>[a-f0-9]{64})/reviewed',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( __CLASS__, 'news_radar_mark_reviewed' ),
                'permission_callback' => MvM_Hub4_Security::require_capability( MvM_Hub4_Capabilities::NEWS_REVIEW ),
                'args'                => array(
                    'id' => array(
                        'required'          => true,
                        'sanitize_callback' => static fn( mixed $value ): string => strtolower( sanitize_text_field( (string) $value ) ),
                        'validate_callback' => static fn( mixed $value ): bool => 1 === preg_match( '/^[a-f0-9]{64}$/', strtolower( (string) $value ) ),
                    ),
                ),
            )
        );
    }

    public static function assignments( WP_REST_Request $request ): WP_REST_Response {
        $items = MvM_Hub4_Assignments::list_for_current_user(
            array(
                'scope'  => (string) ( $request->get_param( 'scope' ) ?: 'mine' ),
                'status' => (string) ( $request->get_param( 'status' ) ?: '' ),
                'limit'  => absint( $request->get_param( 'limit' ) ?: 20 ),
            )
        );

        MvM_Hub4_Audit::log(
            'assignment.list_view',
            'success',
            array(
                'object_type' => 'assignment_list',
                'context'     => array( 'count' => count( $items ) ),
            )
        );

        return rest_ensure_response(
            array(
                'items'    => $items,
                'statuses' => MvM_Hub4_Assignments::statuses(),
                'canManage'=> current_user_can( MvM_Hub4_Capabilities::ASSIGNMENT_MANAGE ) || current_user_can( 'manage_options' ),
            )
        );
    }

    public static function create_assignment( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $input  = self::json_input( $request );
        $result = MvM_Hub4_Assignments::create( $input );
        return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
    }

    public static function update_assignment( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $input  = self::json_input( $request );
        $result = MvM_Hub4_Assignments::update( absint( $request['id'] ), $input );
        return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
    }

    public static function checklist( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $post_id = absint( $request['id'] );
        $result  = MvM_Hub4_Publication_Checklist::summary( $post_id );
        if ( is_wp_error( $result ) ) {
            return $result;
        }

        MvM_Hub4_Audit::log(
            'news.checklist_view',
            'success',
            array(
                'object_type' => 'post',
                'object_id'   => $post_id,
                'context'     => array( 'complete' => (bool) $result['complete'] ),
            )
        );

        return rest_ensure_response( $result );
    }

    public static function update_checklist( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $result = MvM_Hub4_Publication_Checklist::update( absint( $request['id'] ), self::json_input( $request ) );
        return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
    }

    public static function news_radar( WP_REST_Request $request ): WP_REST_Response {
        $limit   = absint( $request->get_param( 'limit' ) ?: 50 );
        $limit   = max( 1, min( 200, $limit ) );
        $groups  = MvM_Hub4_Legacy_Newsradar::grouped_results();
        $groups  = MvM_Hub4_AI_Agenda_Radar::sort_groups( $groups );
        $groups  = array_slice( $groups, 0, $limit );
        $sources = MvM_Hub4_Legacy_Newsradar::sources();
        $can_review = current_user_can( MvM_Hub4_Capabilities::NEWS_REVIEW ) || current_user_can( 'manage_options' );

        MvM_Hub4_Audit::log(
            'news.radar_view',
            'success',
            array(
                'object_type' => 'legacy_news_radar',
                'context'     => array(
                    'subjects' => count( $groups ),
                    'sources'  => count( $sources ),
                    'read_only'=> true,
                ),
            )
        );

        return rest_ensure_response(
            array(
                'readOnly'    => true,
                'snapshot'    => MvM_Hub4_Legacy_Newsradar::snapshot(),
                'items'       => $groups,
                'sources'     => $sources,
                'permissions' => array(
                    'canReview' => $can_review,
                ),
            )
        );
    }

    public static function news_radar_mark_reviewed( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $id     = strtolower( sanitize_text_field( (string) $request['id'] ) );
        $result = MvM_Hub4_Legacy_Newsradar_Writes::mark_reviewed( $id );

        if ( is_wp_error( $result ) ) {
            MvM_Hub4_Audit::log(
                'news.radar_review',
                'error',
                array(
                    'object_type' => 'legacy_news_radar_item',
                    'context'     => array(
                        'result_id' => $id,
                        'error'     => $result->get_error_code(),
                    ),
                )
            );
            return $result;
        }

        MvM_Hub4_Audit::log(
            'news.radar_review',
            'success',
            array(
                'object_type' => 'legacy_news_radar_item',
                'context'     => array(
                    'result_id' => $id,
                    'changed'   => ! empty( $result['changed'] ),
                ),
            )
        );

        return rest_ensure_response( $result );
    }

    private static function json_input( WP_REST_Request $request ): array {
        $json = $request->get_json_params();
        return is_array( $json ) ? $json : array();
    }
}
