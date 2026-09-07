<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class MvM_Hub4_Platform_REST {
    public static function init(): void {
        add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
    }

    public static function register_routes(): void {
        register_rest_route(
            'mvm-hub4/v1',
            '/platform',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( __CLASS__, 'platform_status' ),
                'permission_callback' => array( 'MvM_Hub4_Security', 'require_hub_access' ),
            )
        );

        self::collection_route( 'signals', MvM_Hub4_Capabilities::SIGNAL_VIEW, MvM_Hub4_Capabilities::SIGNAL_CREATE, 'signals', 'create_signal' );
        self::item_route( 'signals', MvM_Hub4_Capabilities::SIGNAL_VIEW, MvM_Hub4_Capabilities::SIGNAL_TRIAGE, 'signal', 'update_signal' );

        self::collection_route( 'dossiers', MvM_Hub4_Capabilities::DOSSIER_VIEW, MvM_Hub4_Capabilities::DOSSIER_MANAGE, 'dossiers', 'create_dossier' );
        self::item_route( 'dossiers', MvM_Hub4_Capabilities::DOSSIER_VIEW, MvM_Hub4_Capabilities::DOSSIER_MANAGE, 'dossier', 'update_dossier' );
        register_rest_route(
            'mvm-hub4/v1',
            '/dossiers/(?P<id>\d+)/links',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( __CLASS__, 'add_dossier_link' ),
                'permission_callback' => MvM_Hub4_Security::require_capability( MvM_Hub4_Capabilities::DOSSIER_MANAGE ),
                'args'                => self::positive_id_args(),
            )
        );

        self::collection_route( 'calendar', MvM_Hub4_Capabilities::CALENDAR_VIEW, MvM_Hub4_Capabilities::CALENDAR_MANAGE, 'calendar', 'create_calendar_item' );
        self::item_route( 'calendar', MvM_Hub4_Capabilities::CALENDAR_VIEW, MvM_Hub4_Capabilities::CALENDAR_MANAGE, 'calendar_item', 'update_calendar_item' );

        self::collection_route( 'media', MvM_Hub4_Capabilities::MEDIA_VIEW, MvM_Hub4_Capabilities::MEDIA_MANAGE, 'media', 'create_media_item' );
        self::item_route( 'media', MvM_Hub4_Capabilities::MEDIA_VIEW, MvM_Hub4_Capabilities::MEDIA_MANAGE, 'media_item', 'update_media_item' );

        self::collection_route( 'distribution', MvM_Hub4_Capabilities::DISTRIBUTION_VIEW, MvM_Hub4_Capabilities::DISTRIBUTION_PREPARE, 'distribution', 'create_distribution_item' );
        self::item_route( 'distribution', MvM_Hub4_Capabilities::DISTRIBUTION_VIEW, MvM_Hub4_Capabilities::DISTRIBUTION_PREPARE, 'distribution_item', 'update_distribution_item' );

        self::collection_route( 'corrections', MvM_Hub4_Capabilities::CORRECTION_VIEW, MvM_Hub4_Capabilities::CORRECTION_MANAGE, 'corrections', 'create_correction' );
        self::item_route( 'corrections', MvM_Hub4_Capabilities::CORRECTION_VIEW, MvM_Hub4_Capabilities::CORRECTION_MANAGE, 'correction', 'update_correction' );

        register_rest_route(
            'mvm-hub4/v1',
            '/dashboard',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( __CLASS__, 'dashboard' ),
                'permission_callback' => MvM_Hub4_Security::require_capability( MvM_Hub4_Capabilities::DASHBOARD_VIEW ),
            )
        );

        register_rest_route(
            'mvm-hub4/v1',
            '/source-radar',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( __CLASS__, 'source_radar' ),
                'permission_callback' => MvM_Hub4_Security::require_capability( MvM_Hub4_Capabilities::SOURCE_VIEW ),
            )
        );
    }

    private static function collection_route( string $path, string $view_cap, string $write_cap, string $get_callback, string $post_callback ): void {
        register_rest_route(
            'mvm-hub4/v1',
            '/' . $path,
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( __CLASS__, $get_callback ),
                    'permission_callback' => MvM_Hub4_Security::require_capability( $view_cap ),
                ),
                array(
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => array( __CLASS__, $post_callback ),
                    'permission_callback' => MvM_Hub4_Security::require_capability( $write_cap ),
                ),
            )
        );
    }

    private static function item_route( string $path, string $view_cap, string $write_cap, string $get_callback, string $post_callback ): void {
        register_rest_route(
            'mvm-hub4/v1',
            '/' . $path . '/(?P<id>\d+)',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( __CLASS__, $get_callback ),
                    'permission_callback' => MvM_Hub4_Security::require_capability( $view_cap ),
                    'args'                => self::positive_id_args(),
                ),
                array(
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => array( __CLASS__, $post_callback ),
                    'permission_callback' => MvM_Hub4_Security::require_capability( $write_cap ),
                    'args'                => self::positive_id_args(),
                ),
            )
        );
    }

    public static function platform_status( WP_REST_Request $request ): WP_REST_Response {
        unset( $request );
        $can = static fn( string $capability ): bool => current_user_can( $capability ) || current_user_can( 'manage_options' );
        return rest_ensure_response(
            array(
                'version'       => MVM_HUB4_VERSION,
                'schemaVersion' => MvM_Hub4_Platform_Schema::schema_version(),
                'modules'       => array(
                    'dashboard'    => $can( MvM_Hub4_Capabilities::DASHBOARD_VIEW ),
                    'signals'      => $can( MvM_Hub4_Capabilities::SIGNAL_VIEW ),
                    'dossiers'     => $can( MvM_Hub4_Capabilities::DOSSIER_VIEW ),
                    'calendar'     => $can( MvM_Hub4_Capabilities::CALENDAR_VIEW ),
                    'media'        => $can( MvM_Hub4_Capabilities::MEDIA_VIEW ),
                    'distribution' => $can( MvM_Hub4_Capabilities::DISTRIBUTION_VIEW ),
                    'corrections'  => $can( MvM_Hub4_Capabilities::CORRECTION_VIEW ),
                    'sourceRadar'  => $can( MvM_Hub4_Capabilities::SOURCE_VIEW ),
                ),
                'writes'        => array(
                    'signalCreate'        => $can( MvM_Hub4_Capabilities::SIGNAL_CREATE ),
                    'signalTriage'        => $can( MvM_Hub4_Capabilities::SIGNAL_TRIAGE ),
                    'assignmentCreate'    => $can( MvM_Hub4_Capabilities::ASSIGNMENT_CREATE ),
                    'dossierManage'       => $can( MvM_Hub4_Capabilities::DOSSIER_MANAGE ),
                    'dossierPublish'      => $can( MvM_Hub4_Capabilities::DOSSIER_PUBLISH ),
                    'calendarManage'      => $can( MvM_Hub4_Capabilities::CALENDAR_MANAGE ),
                    'mediaManage'         => $can( MvM_Hub4_Capabilities::MEDIA_MANAGE ),
                    'mediaReview'         => $can( MvM_Hub4_Capabilities::MEDIA_REVIEW ),
                    'distributionPrepare' => $can( MvM_Hub4_Capabilities::DISTRIBUTION_PREPARE ),
                    'distributionApprove' => $can( MvM_Hub4_Capabilities::DISTRIBUTION_APPROVE ),
                    'correctionManage'    => $can( MvM_Hub4_Capabilities::CORRECTION_MANAGE ),
                ),
                'roleHome'      => MvM_Hub4_Dashboard::role_home(),
            )
        );
    }

    public static function signals( WP_REST_Request $request ): WP_REST_Response {
        return rest_ensure_response(
            array(
                'items' => MvM_Hub4_Signals::list_for_current_user(
                    array(
                        'status'   => $request->get_param( 'status' ),
                        'kind'     => $request->get_param( 'kind' ),
                        'assignee' => $request->get_param( 'assignee' ),
                        'limit'    => $request->get_param( 'limit' ),
                    )
                ),
                'counts' => MvM_Hub4_Signals::counts(),
            )
        );
    }

    public static function create_signal( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        return self::respond( MvM_Hub4_Signals::create_internal( self::json( $request ) ) );
    }

    public static function signal( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        return self::respond( MvM_Hub4_Signals::get( absint( $request['id'] ) ) );
    }

    public static function update_signal( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        return self::respond( MvM_Hub4_Signals::update( absint( $request['id'] ), self::json( $request ) ) );
    }

    public static function dossiers( WP_REST_Request $request ): WP_REST_Response {
        return rest_ensure_response(
            array(
                'items'  => MvM_Hub4_Dossiers::list_for_current_user( array( 'status' => $request->get_param( 'status' ), 'limit' => $request->get_param( 'limit' ) ) ),
                'counts' => MvM_Hub4_Dossiers::counts(),
            )
        );
    }

    public static function create_dossier( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        return self::respond( MvM_Hub4_Dossiers::create( self::json( $request ) ) );
    }

    public static function dossier( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        return self::respond( MvM_Hub4_Dossiers::get( absint( $request['id'] ) ) );
    }

    public static function update_dossier( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        return self::respond( MvM_Hub4_Dossiers::update( absint( $request['id'] ), self::json( $request ) ) );
    }

    public static function add_dossier_link( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        return self::respond( MvM_Hub4_Platform_Writes::add_dossier_link( absint( $request['id'] ), self::json( $request ) ) );
    }

    public static function calendar( WP_REST_Request $request ): WP_REST_Response {
        return rest_ensure_response(
            array(
                'items' => MvM_Hub4_Editorial_Calendar::list_for_current_user(
                    array(
                        'from'   => $request->get_param( 'from' ),
                        'to'     => $request->get_param( 'to' ),
                        'owner'  => $request->get_param( 'owner' ),
                        'kind'   => $request->get_param( 'kind' ),
                        'status' => $request->get_param( 'status' ),
                        'limit'  => $request->get_param( 'limit' ),
                    )
                ),
            )
        );
    }

    public static function create_calendar_item( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        return self::respond( MvM_Hub4_Platform_Writes::create_calendar( self::json( $request ) ) );
    }

    public static function calendar_item( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        return self::respond( MvM_Hub4_Editorial_Calendar::get( absint( $request['id'] ) ) );
    }

    public static function update_calendar_item( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        return self::respond( MvM_Hub4_Platform_Writes::update_calendar( absint( $request['id'] ), self::json( $request ) ) );
    }

    public static function media( WP_REST_Request $request ): WP_REST_Response {
        return rest_ensure_response(
            array(
                'items' => MvM_Hub4_Media_Desk::list_for_current_user(
                    array(
                        'status' => $request->get_param( 'status' ),
                        'limit'  => $request->get_param( 'limit' ),
                    )
                ),
            )
        );
    }

    public static function create_media_item( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        return self::respond( MvM_Hub4_Platform_Writes::create_media( self::json( $request ) ) );
    }

    public static function media_item( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        return self::respond( MvM_Hub4_Media_Desk::get( absint( $request['id'] ) ) );
    }

    public static function update_media_item( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        return self::respond( MvM_Hub4_Media_Desk::update( absint( $request['id'] ), self::json( $request ) ) );
    }

    public static function distribution( WP_REST_Request $request ): WP_REST_Response {
        return rest_ensure_response(
            array(
                'items' => MvM_Hub4_Distribution::list_for_current_user(
                    array(
                        'status'  => $request->get_param( 'status' ),
                        'channel' => $request->get_param( 'channel' ),
                        'limit'   => $request->get_param( 'limit' ),
                    )
                ),
            )
        );
    }

    public static function create_distribution_item( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        return self::respond( MvM_Hub4_Platform_Writes::create_distribution( self::json( $request ) ) );
    }

    public static function distribution_item( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        return self::respond( MvM_Hub4_Distribution::get( absint( $request['id'] ) ) );
    }

    public static function update_distribution_item( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        return self::respond( MvM_Hub4_Distribution::update( absint( $request['id'] ), self::json( $request ) ) );
    }

    public static function corrections( WP_REST_Request $request ): WP_REST_Response {
        return rest_ensure_response( array( 'items' => MvM_Hub4_Corrections::list_for_current_user( array( 'status' => $request->get_param( 'status' ), 'postId' => $request->get_param( 'postId' ), 'limit' => $request->get_param( 'limit' ) ) ) ) );
    }

    public static function create_correction( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        return self::respond( MvM_Hub4_Corrections::create_internal( self::json( $request ) ) );
    }

    public static function correction( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        return self::respond( MvM_Hub4_Corrections::get( absint( $request['id'] ) ) );
    }

    public static function update_correction( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        return self::respond( MvM_Hub4_Corrections::update( absint( $request['id'] ), self::json( $request ) ) );
    }

    public static function dashboard( WP_REST_Request $request ): WP_REST_Response {
        unset( $request );
        return rest_ensure_response( MvM_Hub4_Dashboard::overview() );
    }

    public static function source_radar( WP_REST_Request $request ): WP_REST_Response {
        return rest_ensure_response( MvM_Hub4_Source_Radar::attention( absint( $request->get_param( 'limit' ) ?: 20 ) ) );
    }

    private static function json( WP_REST_Request $request ): array {
        $json = $request->get_json_params();
        return is_array( $json ) ? $json : array();
    }

    private static function respond( array|WP_Error $result ): WP_REST_Response|WP_Error {
        return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
    }

    private static function positive_id_args(): array {
        return array(
            'id' => array(
                'required'          => true,
                'sanitize_callback' => 'absint',
                'validate_callback' => static fn( mixed $value ): bool => absint( $value ) > 0,
            ),
        );
    }
}