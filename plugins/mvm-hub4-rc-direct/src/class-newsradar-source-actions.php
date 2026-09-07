<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Explicit source-run endpoints. Existing /sources/{id}/check keeps its historic
 * bookkeeping contract; these endpoints mean "actually run the crawler".
 */
final class MvM_Hub4_Newsradar_Source_Actions {
    public static function init(): void {
        add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
    }

    public static function register_routes(): void {
        register_rest_route(
            'mvm-hub4/v1',
            '/sources/(?P<id>\d+)/run',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'permission_callback' => MvM_Hub4_Security::require_capability( MvM_Hub4_Capabilities::SOURCE_CHECK ),
                'callback'            => array( __CLASS__, 'run_one' ),
            )
        );
        register_rest_route(
            'mvm-hub4/v1',
            '/sources/bulk-run',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'permission_callback' => MvM_Hub4_Security::require_capability( MvM_Hub4_Capabilities::SOURCE_CHECK ),
                'callback'            => array( __CLASS__, 'run_bulk' ),
            )
        );
    }

    public static function run_one( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $source_id = absint( $request['id'] );
        if ( $source_id < 1 ) {
            return new WP_Error( 'mvm_newsradar_invalid_source', 'Ongeldige bron.', array( 'status' => 400 ) );
        }

        $queue = MvM_Hub4_Newsradar_Schedule::queue_manual_sources( array( $source_id ) );
        if ( empty( $queue['queued'] ) ) {
            MvM_Hub4_Audit::log( 'source.manual_run', 'error', array( 'object_type' => 'source', 'object_id' => $source_id ) );
            return new WP_Error( 'mvm_newsradar_source_not_queued', 'De bron kon niet worden ingepland. Een gestopte bron moet eerst op Doorgaan.', array( 'status' => 409 ) );
        }

        MvM_Hub4_Audit::log( 'source.manual_run', 'success', array( 'object_type' => 'source', 'object_id' => $source_id ) );
        return rest_ensure_response( array( 'ok' => true, 'queued' => array( $source_id ) ) );
    }

    public static function run_bulk( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $params = (array) $request->get_json_params();
        $ids = array_slice( array_values( array_unique( array_filter( array_map( 'absint', (array) ( $params['ids'] ?? array() ) ) ) ) ), 0, 50 );
        if ( ! $ids ) {
            return new WP_Error( 'mvm_newsradar_no_sources', 'Selecteer minimaal één bron.', array( 'status' => 400 ) );
        }

        $result = MvM_Hub4_Newsradar_Schedule::queue_manual_sources( $ids );
        MvM_Hub4_Audit::log(
            'source.manual_bulk_run',
            empty( $result['failed'] ) ? 'success' : 'error',
            array(
                'object_type' => 'source_bulk',
                'context'     => array(
                    'requested' => count( $ids ),
                    'queued'    => count( $result['queued'] ),
                    'failed'    => count( $result['failed'] ),
                ),
            )
        );

        return rest_ensure_response(
            array(
                'ok'     => empty( $result['failed'] ),
                'queued' => array_values( $result['queued'] ),
                'failed' => array_values( $result['failed'] ),
            )
        );
    }
}
