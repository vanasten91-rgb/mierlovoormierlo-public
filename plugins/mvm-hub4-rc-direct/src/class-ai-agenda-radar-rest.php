<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/** Beveiligde Hub 4-routes voor de bronvaste AI-agendacrawl. */
final class MvM_Hub4_AI_Agenda_Radar_REST {
    public static function init(): void {
        add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ), 120 );
    }

    public static function register_routes(): void {
        $view   = MvM_Hub4_Security::require_capability( MvM_Hub4_Capabilities::SOURCE_VIEW );
        $manage = MvM_Hub4_Security::require_capability( MvM_Hub4_Capabilities::SOURCE_MANAGE );

        register_rest_route(
            'mvm-hub4/v1',
            '/agenda-crawl/settings',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( __CLASS__, 'status' ),
                    'permission_callback' => $view,
                ),
                array(
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => array( __CLASS__, 'update_settings' ),
                    'permission_callback' => $manage,
                ),
            ),
            true
        );

        register_rest_route(
            'mvm-hub4/v1',
            '/agenda-crawl/run',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( __CLASS__, 'run' ),
                'permission_callback' => $manage,
            ),
            true
        );
    }

    public static function status( WP_REST_Request $request ): WP_REST_Response {
        unset( $request );
        $status = MvM_Hub4_AI_Agenda_Radar::status();
        $status['permissions'] = array(
            'canView'   => current_user_can( MvM_Hub4_Capabilities::SOURCE_VIEW ) || current_user_can( 'manage_options' ),
            'canManage' => current_user_can( MvM_Hub4_Capabilities::SOURCE_MANAGE ) || current_user_can( 'manage_options' ),
        );
        return rest_ensure_response( $status );
    }

    public static function update_settings( WP_REST_Request $request ): WP_REST_Response {
        $input   = $request->get_json_params();
        $input   = is_array( $input ) ? $input : array();
        $allowed = array();
        if ( array_key_exists( 'enabled', $input ) ) {
            $allowed['enabled'] = rest_sanitize_boolean( $input['enabled'] );
        }
        if ( array_key_exists( 'batch', $input ) ) {
            $allowed['batch'] = min( MvM_Hub4_AI_Agenda_Radar::MAX_BATCH, max( 1, absint( $input['batch'] ) ) );
        }
        if ( array_key_exists( 'radarSort', $input ) ) {
            $allowed['radar_sort'] = sanitize_key( (string) $input['radarSort'] );
        }

        $before = MvM_Hub4_AI_Agenda_Radar::settings();
        $after  = MvM_Hub4_AI_Agenda_Radar::update_settings( $allowed );
        MvM_Hub4_Audit::log(
            'news.agenda_crawl_settings',
            'success',
            array(
                'object_type' => 'agenda_crawl',
                'context'     => array(
                    'changed_fields' => implode( ',', array_keys( $allowed ) ),
                    'enabled_before' => ! empty( $before['enabled'] ),
                    'enabled_after'  => ! empty( $after['enabled'] ),
                ),
            )
        );
        return self::status( $request );
    }

    public static function run( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        unset( $request );
        $result = MvM_Hub4_AI_Agenda_Radar::run();
        if ( is_wp_error( $result ) ) {
            MvM_Hub4_Audit::log(
                'news.agenda_crawl_run',
                'error',
                array(
                    'object_type' => 'agenda_crawl',
                    'context'     => array( 'error' => $result->get_error_code() ),
                )
            );
            return $result;
        }

        MvM_Hub4_Audit::log(
            'news.agenda_crawl_run',
            'success',
            array(
                'object_type' => 'agenda_crawl',
                'context'     => array(
                    'checked' => absint( $result['checked'] ?? 0 ),
                    'added'   => absint( $result['added'] ?? 0 ),
                    'paused'  => ! empty( $result['paused'] ),
                ),
            )
        );
        return rest_ensure_response( $result );
    }
}
