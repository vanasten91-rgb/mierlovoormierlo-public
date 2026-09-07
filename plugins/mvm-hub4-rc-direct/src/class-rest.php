<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class MvM_Hub4_REST {
    public static function init(): void {
        add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
    }

    public static function register_routes(): void {
        register_rest_route(
            'mvm-hub4/v1',
            '/status',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( __CLASS__, 'status' ),
                'permission_callback' => array( 'MvM_Hub4_Security', 'require_hub_access' ),
            )
        );

        register_rest_route(
            'mvm-hub4/v1',
            '/session/touch',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( __CLASS__, 'session_touch' ),
                'permission_callback' => array( 'MvM_Hub4_Security', 'require_hub_access' ),
            )
        );

        register_rest_route(
            'mvm-hub4/v1',
            '/news',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( __CLASS__, 'news' ),
                'permission_callback' => MvM_Hub4_Security::require_capability( MvM_Hub4_Capabilities::NEWS_VIEW ),
            )
        );

        register_rest_route(
            'mvm-hub4/v1',
            '/agenda',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( __CLASS__, 'agenda' ),
                'permission_callback' => MvM_Hub4_Security::require_capability( MvM_Hub4_Capabilities::AGENDA_VIEW ),
            )
        );

        register_rest_route(
            'mvm-hub4/v1',
            '/team',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( __CLASS__, 'team' ),
                'permission_callback' => MvM_Hub4_Security::require_capability( MvM_Hub4_Capabilities::TEAM_VIEW ),
            )
        );

        register_rest_route(
            'mvm-hub4/v1',
            '/tools',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( __CLASS__, 'tools' ),
                'permission_callback' => MvM_Hub4_Security::require_capability( MvM_Hub4_Capabilities::TOOLS_USE ),
            )
        );

        register_rest_route(
            'mvm-hub4/v1',
            '/tools/(?P<id>[a-z0-9_-]+)/open',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( __CLASS__, 'open_tool' ),
                'permission_callback' => MvM_Hub4_Security::require_capability( MvM_Hub4_Capabilities::TOOLS_USE ),
                'args'                => array(
                    'id' => array(
                        'required'          => true,
                        'sanitize_callback' => 'sanitize_key',
                    ),
                ),
            )
        );

        register_rest_route(
            'mvm-hub4/v1',
            '/sources/options',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( __CLASS__, 'source_options' ),
                'permission_callback' => MvM_Hub4_Security::require_capability( MvM_Hub4_Capabilities::SOURCE_VIEW ),
            )
        );

        register_rest_route(
            'mvm-hub4/v1',
            '/sources',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( __CLASS__, 'sources' ),
                'permission_callback' => MvM_Hub4_Security::require_capability( MvM_Hub4_Capabilities::SOURCE_VIEW ),
            )
        );

        register_rest_route(
            'mvm-hub4/v1',
            '/sources/(?P<id>\d+)',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( __CLASS__, 'update_source' ),
                'permission_callback' => MvM_Hub4_Security::require_capability( MvM_Hub4_Capabilities::SOURCE_MANAGE ),
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
            '/sources/(?P<id>\d+)/check',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( __CLASS__, 'check_source' ),
                'permission_callback' => MvM_Hub4_Security::require_capability( MvM_Hub4_Capabilities::SOURCE_CHECK ),
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
            '/sources/bulk-check',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( __CLASS__, 'bulk_check_sources' ),
                'permission_callback' => MvM_Hub4_Security::require_capability( MvM_Hub4_Capabilities::SOURCE_CHECK ),
            )
        );

        register_rest_route(
            'mvm-hub4/v1',
            '/sources/bulk-update',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( __CLASS__, 'bulk_update_sources' ),
                'permission_callback' => MvM_Hub4_Security::require_capability( MvM_Hub4_Capabilities::SOURCE_MANAGE ),
            )
        );

        register_rest_route(
            'mvm-hub4/v1',
            '/audit',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( __CLASS__, 'audit' ),
                'permission_callback' => MvM_Hub4_Security::require_capability( MvM_Hub4_Capabilities::AUDIT_VIEW ),
            )
        );
    }

    public static function status( WP_REST_Request $request ): WP_REST_Response {
        unset( $request );

        $user            = wp_get_current_user();
        $can_view_sources = current_user_can( MvM_Hub4_Capabilities::SOURCE_VIEW ) || current_user_can( 'manage_options' );
        $source_counts    = array( 'overdue' => 0, 'today' => 0, 'thisWeek' => 0 );

        if ( $can_view_sources ) {
            $overdue = MvM_Hub4_Source_Repository::query( array( 'due' => 'overdue', 'per_page' => 1 ) );
            $today   = MvM_Hub4_Source_Repository::query( array( 'due' => 'today', 'per_page' => 1 ) );
            $week    = MvM_Hub4_Source_Repository::query( array( 'due' => 'week', 'per_page' => 1 ) );

            $source_counts = array(
                'overdue'  => (int) $overdue['total'],
                'today'    => (int) $today['total'],
                'thisWeek' => (int) $week['total'],
            );
        }

        return rest_ensure_response(
            array(
                'version' => MVM_HUB4_VERSION,
                'user'    => array(
                    'displayName' => sanitize_text_field( (string) $user->display_name ),
                ),
                'sources' => $source_counts,
                'auditRetentionDays' => MvM_Hub4_Audit::retention_days(),
                'sessionStatus'      => MvM_Hub4_Session::status(),
                'permissions'        => array(
                    'audit' => current_user_can( MvM_Hub4_Capabilities::AUDIT_VIEW ) || current_user_can( 'manage_options' ),
                    'tools' => current_user_can( MvM_Hub4_Capabilities::TOOLS_USE ) || current_user_can( 'manage_options' ),
                ),
            )
        );
    }

    public static function session_touch( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        unset( $request );

        $session = MvM_Hub4_Session::validate_and_touch();
        if ( true !== $session ) {
            return $session;
        }

        return rest_ensure_response( array( 'ok' => true, 'sessionStatus' => MvM_Hub4_Session::status() ) );
    }

    public static function news( WP_REST_Request $request ): WP_REST_Response {
        $limit = min( 50, max( 1, absint( $request->get_param( 'limit' ) ?: 20 ) ) );
        $data  = MvM_Hub4_News_Repository::overview( $limit );

        MvM_Hub4_Audit::log(
            'news.list_view',
            'success',
            array(
                'object_type' => 'news_list',
                'context'     => array( 'count' => count( $data['items'] ) ),
            )
        );

        return rest_ensure_response( $data );
    }

    public static function agenda( WP_REST_Request $request ): WP_REST_Response {
        $limit = min( 50, max( 1, absint( $request->get_param( 'limit' ) ?: 20 ) ) );
        $data  = MvM_Hub4_Agenda_Repository::overview( $limit );

        MvM_Hub4_Audit::log(
            'agenda.view',
            'success',
            array(
                'object_type' => 'agenda',
                'context' => array(
                    'events'         => count( $data['events'] ),
                    'scheduled_news' => count( $data['scheduledNews'] ),
                ),
            )
        );

        return rest_ensure_response( $data );
    }

    public static function team( WP_REST_Request $request ): WP_REST_Response {
        unset( $request );
        $data = MvM_Hub4_Team_Repository::overview();

        MvM_Hub4_Audit::log(
            'team.directory_view',
            'success',
            array(
                'object_type' => 'team_directory',
                'context'     => array( 'count' => (int) $data['total'] ),
            )
        );

        return rest_ensure_response( $data );
    }

    public static function tools( WP_REST_Request $request ): WP_REST_Response {
        unset( $request );
        $items = MvM_Hub4_Tools::available_for_current_user();

        MvM_Hub4_Audit::log(
            'tools.list_view',
            'success',
            array(
                'object_type' => 'tool_registry',
                'context'     => array( 'count' => count( $items ) ),
            )
        );

        return rest_ensure_response( array( 'items' => $items ) );
    }

    public static function open_tool( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $tool_id = sanitize_key( (string) $request['id'] );
        $tool    = MvM_Hub4_Tools::resolve_for_current_user( $tool_id );

        if ( is_wp_error( $tool ) ) {
            MvM_Hub4_Audit::log(
                'tools.open',
                'denied',
                array(
                    'object_type' => 'tool',
                    'context'     => array( 'tool_id' => $tool_id ),
                )
            );
            return $tool;
        }

        MvM_Hub4_Audit::log(
            'tools.open',
            'success',
            array(
                'object_type' => 'tool',
                'context'     => array( 'tool_id' => $tool_id ),
            )
        );

        return rest_ensure_response( $tool );
    }

    public static function source_options( WP_REST_Request $request ): WP_REST_Response {
        unset( $request );

        return rest_ensure_response(
            array(
                'categories'  => self::map_options( MvM_Hub4_Sources::categories() ),
                'frequencies' => self::map_frequency_options(),
                'statuses'    => self::map_options( MvM_Hub4_Sources::statuses() ),
                'permissions' => array(
                    'canCheck'  => current_user_can( MvM_Hub4_Capabilities::SOURCE_CHECK ) || current_user_can( 'manage_options' ),
                    'canManage' => current_user_can( MvM_Hub4_Capabilities::SOURCE_MANAGE ) || current_user_can( 'manage_options' ),
                ),
            )
        );
    }

    public static function sources( WP_REST_Request $request ): WP_REST_Response {
        $result = MvM_Hub4_Source_Repository::query(
            array(
                'page'       => $request->get_param( 'page' ),
                'per_page'   => $request->get_param( 'per_page' ),
                'search'     => $request->get_param( 'search' ),
                'due'        => $request->get_param( 'due' ),
                'category'   => $request->get_param( 'category' ),
                'frequency'  => $request->get_param( 'frequency' ),
                'status'     => $request->get_param( 'status' ),
                'configured' => $request->get_param( 'configured' ),
            )
        );

        MvM_Hub4_Audit::log(
            'source.list_view',
            'success',
            array(
                'object_type' => 'source_list',
                'context' => array(
                    'count' => count( $result['items'] ),
                    'page'  => (int) $result['page'],
                    'due'   => sanitize_key( (string) $request->get_param( 'due' ) ),
                ),
            )
        );

        return rest_ensure_response( $result );
    }

    public static function update_source( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $source_id = absint( $request['id'] );
        $params    = (array) $request->get_json_params();
        $changes   = array();

        if ( array_key_exists( 'monitorEnabled', $params ) ) {
            $changes['monitor_enabled'] = rest_sanitize_boolean( $params['monitorEnabled'] );
        }
        if ( array_key_exists( 'category', $params ) ) {
            $changes['category'] = MvM_Hub4_Sources::sanitize_category( (string) $params['category'] );
        }
        if ( array_key_exists( 'frequency', $params ) ) {
            $changes['frequency'] = MvM_Hub4_Sources::sanitize_frequency( (string) $params['frequency'] );
        }
        if ( array_key_exists( 'status', $params ) ) {
            $changes['status'] = MvM_Hub4_Sources::sanitize_status( (string) $params['status'] );
        }
        if ( array_key_exists( 'privateNote', $params ) ) {
            $changes['private_note'] = (string) $params['privateNote'];
        }

        if ( ! $changes ) {
            return new WP_Error( 'mvm_hub4_no_changes', 'Geen geldige wijzigingen ontvangen.', array( 'status' => 400 ) );
        }

        $saved = MvM_Hub4_Sources::save_state( $source_id, $changes );
        if ( is_wp_error( $saved ) ) {
            MvM_Hub4_Audit::log( 'source.settings_update', 'error', array( 'object_type' => 'source', 'object_id' => $source_id ) );
            return $saved;
        }

        MvM_Hub4_Audit::log(
            'source.settings_update',
            'success',
            array(
                'object_type' => 'source',
                'object_id'   => $source_id,
                'context'     => array( 'changed_fields' => implode( ',', array_keys( $changes ) ) ),
            )
        );

        return rest_ensure_response( array( 'ok' => true, 'state' => self::public_source_state( MvM_Hub4_Sources::get_state( $source_id ) ) ) );
    }

    public static function check_source( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $source_id = absint( $request['id'] );
        $result    = MvM_Hub4_Sources::mark_checked( $source_id, get_current_user_id() );

        if ( is_wp_error( $result ) ) {
            MvM_Hub4_Audit::log( 'source.checked', 'error', array( 'object_type' => 'source', 'object_id' => $source_id ) );
            return $result;
        }

        MvM_Hub4_Audit::log( 'source.checked', 'success', array( 'object_type' => 'source', 'object_id' => $source_id ) );

        return rest_ensure_response( array( 'ok' => true, 'state' => self::public_source_state( MvM_Hub4_Sources::get_state( $source_id ) ) ) );
    }

    public static function bulk_check_sources( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $ids = self::sanitize_ids( (array) ( $request->get_json_params()['ids'] ?? array() ) );
        if ( ! $ids ) {
            return new WP_Error( 'mvm_hub4_no_sources', 'Selecteer minimaal één bron.', array( 'status' => 400 ) );
        }

        $success = array();
        $failed  = array();
        foreach ( $ids as $source_id ) {
            $result = MvM_Hub4_Sources::mark_checked( $source_id, get_current_user_id() );
            if ( is_wp_error( $result ) ) {
                $failed[] = $source_id;
                MvM_Hub4_Audit::log( 'source.checked', 'error', array( 'object_type' => 'source', 'object_id' => $source_id ) );
            } else {
                $success[] = $source_id;
                MvM_Hub4_Audit::log( 'source.checked', 'success', array( 'object_type' => 'source', 'object_id' => $source_id ) );
            }
        }

        MvM_Hub4_Audit::log(
            'source.bulk_checked',
            $failed ? 'error' : 'success',
            array(
                'object_type' => 'source_bulk',
                'context' => array(
                    'requested' => count( $ids ),
                    'succeeded' => count( $success ),
                    'failed'    => count( $failed ),
                ),
            )
        );

        return rest_ensure_response( array( 'ok' => ! $failed, 'success' => $success, 'failed' => $failed ) );
    }

    public static function bulk_update_sources( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $params  = (array) $request->get_json_params();
        $ids     = self::sanitize_ids( (array) ( $params['ids'] ?? array() ) );
        $changes = array();

        if ( ! $ids ) {
            return new WP_Error( 'mvm_hub4_no_sources', 'Selecteer minimaal één bron.', array( 'status' => 400 ) );
        }

        if ( array_key_exists( 'monitorEnabled', $params ) ) {
            $changes['monitor_enabled'] = rest_sanitize_boolean( $params['monitorEnabled'] );
        }
        if ( array_key_exists( 'category', $params ) ) {
            $changes['category'] = MvM_Hub4_Sources::sanitize_category( (string) $params['category'] );
        }
        if ( array_key_exists( 'frequency', $params ) ) {
            $changes['frequency'] = MvM_Hub4_Sources::sanitize_frequency( (string) $params['frequency'] );
        }
        if ( array_key_exists( 'status', $params ) ) {
            $changes['status'] = MvM_Hub4_Sources::sanitize_status( (string) $params['status'] );
        }

        if ( ! $changes ) {
            return new WP_Error( 'mvm_hub4_no_changes', 'Geen geldige bulk-wijziging ontvangen.', array( 'status' => 400 ) );
        }

        $success = array();
        $failed  = array();
        foreach ( $ids as $source_id ) {
            $result = MvM_Hub4_Sources::save_state( $source_id, $changes );
            if ( is_wp_error( $result ) ) {
                $failed[] = $source_id;
            } else {
                $success[] = $source_id;
            }
        }

        MvM_Hub4_Audit::log(
            'source.bulk_update',
            $failed ? 'error' : 'success',
            array(
                'object_type' => 'source_bulk',
                'context' => array(
                    'requested'      => count( $ids ),
                    'succeeded'      => count( $success ),
                    'failed'         => count( $failed ),
                    'changed_fields' => implode( ',', array_keys( $changes ) ),
                ),
            )
        );

        return rest_ensure_response( array( 'ok' => ! $failed, 'success' => $success, 'failed' => $failed ) );
    }

    public static function audit( WP_REST_Request $request ): WP_REST_Response {
        global $wpdb;

        $page     = max( 1, absint( $request->get_param( 'page' ) ?: 1 ) );
        $per_page = min( 100, max( 1, absint( $request->get_param( 'per_page' ) ?: 50 ) ) );
        $offset   = ( $page - 1 ) * $per_page;
        $table    = MvM_Hub4_Audit::table_name();
        $where    = array( '1=1' );
        $params   = array();

        $event = sanitize_text_field( (string) $request->get_param( 'event' ) );
        if ( '' !== $event ) {
            $where[]  = 'event_code = %s';
            $params[] = $event;
        }

        $result_filter = sanitize_key( (string) $request->get_param( 'result' ) );
        if ( in_array( $result_filter, array( 'success', 'denied', 'error', 'cancelled' ), true ) ) {
            $where[]  = 'result = %s';
            $params[] = $result_filter;
        }

        $where_sql = implode( ' AND ', $where );
        $count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- fixed internal table and values are prepared.
        $total = (int) $wpdb->get_var( $params ? $wpdb->prepare( $count_sql, ...$params ) : $count_sql );

        $sql_params   = $params;
        $sql_params[] = $per_page;
        $sql_params[] = $offset;
        $sql = "SELECT id, occurred_at_utc, actor_user_id, actor_role, event_code, object_type, object_id, result, request_id, transition_from, transition_to, context_json
                FROM {$table}
                WHERE {$where_sql}
                ORDER BY id DESC
                LIMIT %d OFFSET %d";
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- fixed internal table and all values prepared.
        $rows = $wpdb->get_results( $wpdb->prepare( $sql, ...$sql_params ), ARRAY_A );
        $rows = is_array( $rows ) ? $rows : array();

        foreach ( $rows as &$row ) {
            $row['context'] = $row['context_json'] ? json_decode( $row['context_json'], true ) : array();
            unset( $row['context_json'] );
        }
        unset( $row );

        MvM_Hub4_Audit::log( 'audit.view', 'success', array( 'object_type' => 'audit_log', 'context' => array( 'page' => $page ) ) );

        return rest_ensure_response(
            array(
                'items'      => $rows,
                'page'       => $page,
                'perPage'    => $per_page,
                'total'      => $total,
                'totalPages' => max( 1, (int) ceil( $total / $per_page ) ),
            )
        );
    }

    private static function public_source_state( ?array $state ): array {
        if ( ! is_array( $state ) ) {
            return array();
        }

        return array(
            'source_post_id'   => absint( $state['source_post_id'] ?? 0 ),
            'monitor_enabled'  => (int) (bool) ( $state['monitor_enabled'] ?? 0 ),
            'category'         => MvM_Hub4_Sources::sanitize_category( (string) ( $state['category'] ?? 'overig' ) ),
            'frequency'        => MvM_Hub4_Sources::sanitize_frequency( (string) ( $state['frequency'] ?? 'weekly' ) ),
            'status'           => MvM_Hub4_Sources::sanitize_status( (string) ( $state['status'] ?? 'active' ) ),
            'last_checked_utc' => sanitize_text_field( (string) ( $state['last_checked_utc'] ?? '' ) ),
            'last_checked_by'  => absint( $state['last_checked_by'] ?? 0 ),
            'next_check_utc'   => sanitize_text_field( (string) ( $state['next_check_utc'] ?? '' ) ),
            'updated_at_utc'   => sanitize_text_field( (string) ( $state['updated_at_utc'] ?? '' ) ),
        );
    }

    private static function sanitize_ids( array $ids ): array {
        $ids = array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );
        return array_slice( $ids, 0, 100 );
    }

    private static function map_options( array $options ): array {
        $mapped = array();
        foreach ( $options as $value => $label ) {
            $mapped[] = array( 'value' => (string) $value, 'label' => (string) $label );
        }
        return $mapped;
    }

    private static function map_frequency_options(): array {
        $mapped = array();
        foreach ( MvM_Hub4_Sources::frequencies() as $value => $config ) {
            $mapped[] = array( 'value' => (string) $value, 'label' => (string) $config['label'] );
        }
        return $mapped;
    }
}
