<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class MvM_Hub4_Launch {
    private const SOURCE_QUERY_VAR = 'mvm_hub4_open_source';
    private const KIND_QUERY_VAR   = 'mvm_hub4_open_kind';

    public static function init(): void {
        add_action( 'init', array( __CLASS__, 'register_redirect_routes' ) );
        add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
        add_action( 'template_redirect', array( __CLASS__, 'handle_source_redirect' ), 0 );
        add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
    }

    public static function activate(): void {
        self::register_redirect_routes();
    }

    public static function register_redirect_routes(): void {
        add_rewrite_rule(
            '^hub4/open/source/([0-9]+)/(external|record)/?$',
            'index.php?' . self::SOURCE_QUERY_VAR . '=$matches[1]&' . self::KIND_QUERY_VAR . '=$matches[2]',
            'top'
        );
    }

    public static function query_vars( array $vars ): array {
        $vars[] = self::SOURCE_QUERY_VAR;
        $vars[] = self::KIND_QUERY_VAR;
        return $vars;
    }

    public static function source_launch_url( int $source_id, string $kind ): string {
        $kind = 'external' === $kind ? 'external' : 'record';
        return home_url( '/hub4/open/source/' . absint( $source_id ) . '/' . $kind . '/' );
    }

    public static function handle_source_redirect(): void {
        $source_id = absint( get_query_var( self::SOURCE_QUERY_VAR ) );
        $kind      = sanitize_key( (string) get_query_var( self::KIND_QUERY_VAR ) );
        if ( $source_id < 1 || ! in_array( $kind, array( 'external', 'record' ), true ) ) {
            return;
        }

        nocache_headers();

        if ( ! is_user_logged_in() ) {
            wp_safe_redirect( home_url( '/hub/' ) );
            exit;
        }

        $access = MvM_Hub4_Security::require_capability( MvM_Hub4_Capabilities::SOURCE_VIEW )();
        if ( true !== $access ) {
            status_header( is_wp_error( $access ) ? (int) ( $access->get_error_data()['status'] ?? 403 ) : 403 );
            wp_die( esc_html__( 'Je hebt geen toegang tot deze bron.', 'mvm-hub4' ), esc_html__( 'Geen toegang', 'mvm-hub4' ), array( 'response' => 403 ) );
        }

        $post       = self::published_source( $source_id, 'external' === $kind ? 'source.external_open' : 'source.record_open' );
        $event_code = 'external' === $kind ? 'source.external_open' : 'source.record_open';
        if ( is_wp_error( $post ) ) {
            status_header( 404 );
            wp_die( esc_html( $post->get_error_message() ), esc_html__( 'Bron niet beschikbaar', 'mvm-hub4' ), array( 'response' => 404 ) );
        }

        if ( 'external' === $kind ) {
            $url = MvM_Hub4_Source_Repository::extract_external_url( (string) $post->post_content );
            if ( '' === $url || ! wp_http_validate_url( $url ) ) {
                MvM_Hub4_Audit::log( $event_code, 'error', array( 'object_type' => 'source', 'object_id' => $source_id ) );
                status_header( 404 );
                wp_die( esc_html__( 'Deze bron heeft geen geldige externe URL.', 'mvm-hub4' ), esc_html__( 'Bron niet beschikbaar', 'mvm-hub4' ), array( 'response' => 404 ) );
            }
            MvM_Hub4_Audit::log( $event_code, 'success', array( 'object_type' => 'source', 'object_id' => $source_id ) );
            wp_redirect( esc_url_raw( $url ), 302, 'MvM Hub 4' ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- external source is explicitly validated.
            exit;
        }

        $url = get_permalink( $post );
        if ( ! $url ) {
            MvM_Hub4_Audit::log( $event_code, 'error', array( 'object_type' => 'source', 'object_id' => $source_id ) );
            status_header( 500 );
            wp_die( esc_html__( 'Het bronrecord heeft geen geldige URL.', 'mvm-hub4' ), esc_html__( 'Bron niet beschikbaar', 'mvm-hub4' ), array( 'response' => 500 ) );
        }

        MvM_Hub4_Audit::log( $event_code, 'success', array( 'object_type' => 'source', 'object_id' => $source_id ) );
        wp_safe_redirect( $url, 302, 'MvM Hub 4' );
        exit;
    }

    public static function register_routes(): void {
        register_rest_route(
            'mvm-hub4/v1',
            '/news/(?P<id>\d+)/open',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( __CLASS__, 'open_news' ),
                'permission_callback' => MvM_Hub4_Security::require_capability( MvM_Hub4_Capabilities::NEWS_VIEW ),
                'args'                => self::positive_id_args(),
            )
        );

        register_rest_route(
            'mvm-hub4/v1',
            '/agenda/event/(?P<id>\d+)/open',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( __CLASS__, 'open_event' ),
                'permission_callback' => MvM_Hub4_Security::require_capability( MvM_Hub4_Capabilities::AGENDA_VIEW ),
                'args'                => self::positive_id_args(),
            )
        );

        register_rest_route(
            'mvm-hub4/v1',
            '/sources/(?P<id>\d+)/open-external',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( __CLASS__, 'open_source_external' ),
                'permission_callback' => MvM_Hub4_Security::require_capability( MvM_Hub4_Capabilities::SOURCE_VIEW ),
                'args'                => self::positive_id_args(),
            )
        );

        register_rest_route(
            'mvm-hub4/v1',
            '/sources/(?P<id>\d+)/open-record',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( __CLASS__, 'open_source_record' ),
                'permission_callback' => MvM_Hub4_Security::require_capability( MvM_Hub4_Capabilities::SOURCE_VIEW ),
                'args'                => self::positive_id_args(),
            )
        );
    }

    public static function open_news( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        return self::open_editable_post( absint( $request['id'] ), 'post', 'news.edit_open' );
    }

    public static function open_event( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        return self::open_editable_post( absint( $request['id'] ), 'event_listing', 'agenda.event_edit_open' );
    }

    public static function open_source_external( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $source_id = absint( $request['id'] );
        $post      = self::published_source( $source_id, 'source.external_open' );
        if ( is_wp_error( $post ) ) {
            return $post;
        }

        $url = MvM_Hub4_Source_Repository::extract_external_url( (string) $post->post_content );
        if ( '' === $url || ! wp_http_validate_url( $url ) ) {
            MvM_Hub4_Audit::log( 'source.external_open', 'error', array( 'object_type' => 'source', 'object_id' => $source_id ) );
            return new WP_Error( 'mvm_hub4_source_url_missing', 'Deze bron heeft geen geldige externe URL.', array( 'status' => 404 ) );
        }

        MvM_Hub4_Audit::log( 'source.external_open', 'success', array( 'object_type' => 'source', 'object_id' => $source_id ) );
        return rest_ensure_response( array( 'url' => esc_url_raw( $url ) ) );
    }

    public static function open_source_record( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $source_id = absint( $request['id'] );
        $post      = self::published_source( $source_id, 'source.record_open' );
        if ( is_wp_error( $post ) ) {
            return $post;
        }

        $url = get_permalink( $post );
        if ( ! $url ) {
            MvM_Hub4_Audit::log( 'source.record_open', 'error', array( 'object_type' => 'source', 'object_id' => $source_id ) );
            return new WP_Error( 'mvm_hub4_source_record_url_missing', 'Het bronrecord heeft geen geldige URL.', array( 'status' => 500 ) );
        }

        MvM_Hub4_Audit::log( 'source.record_open', 'success', array( 'object_type' => 'source', 'object_id' => $source_id ) );
        return rest_ensure_response( array( 'url' => esc_url_raw( $url ) ) );
    }

    private static function published_source( int $source_id, string $event_code ): WP_Post|WP_Error {
        $post = get_post( $source_id );
        if ( ! $post instanceof WP_Post || 'mvm_bron' !== $post->post_type || 'publish' !== $post->post_status ) {
            MvM_Hub4_Audit::log( $event_code, 'error', array( 'object_type' => 'source', 'object_id' => $source_id ) );
            return new WP_Error( 'mvm_hub4_source_missing', 'Deze bron bestaat niet of is niet gepubliceerd.', array( 'status' => 404 ) );
        }
        return $post;
    }

    private static function open_editable_post( int $post_id, string $expected_type, string $event_code ): WP_REST_Response|WP_Error {
        $post = get_post( $post_id );
        if ( ! $post instanceof WP_Post || $expected_type !== $post->post_type ) {
            MvM_Hub4_Audit::log( $event_code, 'error', array( 'object_type' => $expected_type, 'object_id' => $post_id ) );
            return new WP_Error( 'mvm_hub4_object_missing', 'Dit item bestaat niet meer.', array( 'status' => 404 ) );
        }

        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            MvM_Hub4_Audit::log( $event_code, 'denied', array( 'object_type' => $expected_type, 'object_id' => $post_id ) );
            return new WP_Error( 'mvm_hub4_object_forbidden', 'Je hebt geen rechten om dit item te bewerken.', array( 'status' => 403 ) );
        }

        $url = get_edit_post_link( $post_id, 'raw' );
        if ( ! $url ) {
            MvM_Hub4_Audit::log( $event_code, 'error', array( 'object_type' => $expected_type, 'object_id' => $post_id ) );
            return new WP_Error( 'mvm_hub4_edit_url_missing', 'De beheerlink kon niet veilig worden vastgesteld.', array( 'status' => 500 ) );
        }

        MvM_Hub4_Audit::log( $event_code, 'success', array( 'object_type' => $expected_type, 'object_id' => $post_id ) );
        return rest_ensure_response( array( 'url' => esc_url_raw( $url ) ) );
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
