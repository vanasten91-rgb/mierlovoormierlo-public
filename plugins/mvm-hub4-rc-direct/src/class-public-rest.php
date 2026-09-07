<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class MvM_Hub4_Public_REST {
    private const RATE_WINDOW = 900;
    private const RATE_LIMIT  = 5;

    public static function init(): void {
        add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
    }

    public static function register_routes(): void {
        register_rest_route(
            'mvm-public/v1',
            '/tips',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( __CLASS__, 'create_tip' ),
                'permission_callback' => array( __CLASS__, 'allow_tip_intake' ),
            )
        );

        register_rest_route(
            'mvm-public/v1',
            '/corrections',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( __CLASS__, 'create_correction' ),
                'permission_callback' => array( __CLASS__, 'allow_correction_intake' ),
            )
        );

        register_rest_route(
            'mvm-public/v1',
            '/corrections/(?P<post_id>\d+)',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( __CLASS__, 'public_corrections' ),
                'permission_callback' => array( __CLASS__, 'allow_public_read' ),
                'args'                => array(
                    'post_id' => array(
                        'required'          => true,
                        'sanitize_callback' => 'absint',
                        'validate_callback' => static fn( mixed $value ): bool => absint( $value ) > 0,
                    ),
                ),
            )
        );

        register_rest_route(
            'mvm-public/v1',
            '/dossiers',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( __CLASS__, 'public_dossiers' ),
                'permission_callback' => array( __CLASS__, 'allow_public_read' ),
            )
        );

        register_rest_route(
            'mvm-public/v1',
            '/dossiers/(?P<slug>[a-z0-9-]+)',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( __CLASS__, 'public_dossier' ),
                'permission_callback' => array( __CLASS__, 'allow_public_read' ),
                'args'                => array(
                    'slug' => array(
                        'required'          => true,
                        'sanitize_callback' => 'sanitize_title',
                        'validate_callback' => static fn( mixed $value ): bool => '' !== sanitize_title( (string) $value ),
                    ),
                ),
            )
        );

        register_rest_route(
            'mvm-public/v1',
            '/my-mierlo',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( __CLASS__, 'my_mierlo' ),
                    'permission_callback' => array( __CLASS__, 'allow_logged_in_reader' ),
                ),
                array(
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => array( __CLASS__, 'update_my_mierlo' ),
                    'permission_callback' => array( __CLASS__, 'allow_logged_in_reader' ),
                ),
            )
        );

        register_rest_route(
            'mvm-public/v1',
            '/feed',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( __CLASS__, 'feed' ),
                'permission_callback' => array( __CLASS__, 'allow_public_read' ),
            )
        );
    }

    public static function allow_public_read( WP_REST_Request $request ): bool {
        return 'GET' === strtoupper( $request->get_method() );
    }

    public static function allow_logged_in_reader( WP_REST_Request $request ): bool|WP_Error {
        unset( $request );
        if ( ! is_user_logged_in() || ! current_user_can( 'read' ) ) {
            return new WP_Error( 'mvm_public_auth_required', 'Log in om Mijn Mierlo te gebruiken.', array( 'status' => 401 ) );
        }
        return true;
    }

    public static function allow_tip_intake( WP_REST_Request $request ): bool|WP_Error {
        return self::allow_intake( $request, 'tip' );
    }

    public static function allow_correction_intake( WP_REST_Request $request ): bool|WP_Error {
        return self::allow_intake( $request, 'correction' );
    }

    private static function allow_intake( WP_REST_Request $request, string $kind ): bool|WP_Error {
        if ( 'POST' !== strtoupper( $request->get_method() ) ) {
            return new WP_Error( 'mvm_public_method', 'Deze aanvraagmethode is niet toegestaan.', array( 'status' => 405 ) );
        }
        $honeypot = trim( (string) $request->get_param( 'website' ) );
        if ( '' !== $honeypot ) {
            return new WP_Error( 'mvm_public_invalid', 'De inzending kon niet worden verwerkt.', array( 'status' => 400 ) );
        }
        $key = self::rate_key( $kind );
        $count = (int) get_transient( $key );
        if ( $count >= self::RATE_LIMIT ) {
            return new WP_Error( 'mvm_public_rate_limited', 'Er zijn te veel inzendingen gedaan. Probeer het later opnieuw.', array( 'status' => 429 ) );
        }
        set_transient( $key, $count + 1, self::RATE_WINDOW );
        return true;
    }

    public static function create_tip( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $input = self::input( $request );
        $allowed_kinds = array( 'news', 'photo', 'event', 'source', 'safety', 'general' );
        $kind = sanitize_key( (string) ( $input['kind'] ?? 'general' ) );
        if ( ! in_array( $kind, $allowed_kinds, true ) ) {
            $kind = 'general';
        }
        $payload = array(
            'kind'         => $kind,
            'title'        => $input['title'] ?? '',
            'summary'      => $input['summary'] ?? '',
            'sourceUrl'    => $input['sourceUrl'] ?? '',
            'location'     => $input['location'] ?? '',
            'contactName'  => $input['contactName'] ?? '',
            'contactEmail' => $input['contactEmail'] ?? '',
            'contactPhone' => $input['contactPhone'] ?? '',
        );
        $result = MvM_Hub4_Signals::create_public( $payload );
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        return rest_ensure_response( array( 'accepted' => true, 'message' => 'Bedankt. Je tip is ontvangen door de redactie.' ) );
    }

    public static function create_correction( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $input = self::input( $request );
        $result = MvM_Hub4_Corrections::create_public(
            array(
                'postId'       => $input['postId'] ?? 0,
                'message'      => $input['message'] ?? '',
                'contactName'  => $input['contactName'] ?? '',
                'contactEmail' => $input['contactEmail'] ?? '',
            )
        );
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        return rest_ensure_response( array( 'accepted' => true, 'message' => 'Bedankt. Je melding is ontvangen door de redactie.' ) );
    }

    public static function public_corrections( WP_REST_Request $request ): WP_REST_Response {
        return rest_ensure_response( array( 'items' => MvM_Hub4_Corrections::public_for_post( absint( $request['post_id'] ) ) ) );
    }

    public static function public_dossiers( WP_REST_Request $request ): WP_REST_Response {
        return rest_ensure_response( array( 'items' => MvM_Hub4_Dossiers::public_list( absint( $request->get_param( 'limit' ) ?: 20 ) ) ) );
    }

    public static function public_dossier( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $result = MvM_Hub4_Dossiers::public_by_slug( sanitize_title( (string) $request['slug'] ) );
        return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
    }

    public static function my_mierlo( WP_REST_Request $request ): WP_REST_Response {
        unset( $request );
        return rest_ensure_response( MvM_Hub4_Personalization::get_current() );
    }

    public static function update_my_mierlo( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $input = self::input( $request );
        $topics = isset( $input['topics'] ) && is_array( $input['topics'] ) ? $input['topics'] : array();
        $result = MvM_Hub4_Personalization::update_current( $topics );
        return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
    }

    public static function feed( WP_REST_Request $request ): WP_REST_Response {
        return rest_ensure_response( MvM_Hub4_Personalization::feed( absint( $request->get_param( 'limit' ) ?: 20 ) ) );
    }

    private static function input( WP_REST_Request $request ): array {
        $json = $request->get_json_params();
        if ( is_array( $json ) && $json ) {
            return $json;
        }
        return (array) $request->get_params();
    }

    private static function rate_key( string $kind ): string {
        $ip = isset( $_SERVER['REMOTE_ADDR'] ) ? trim( (string) wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
        $fingerprint = hash_hmac( 'sha256', $ip, wp_salt( 'nonce' ) );
        return 'mvm_hub4_public_' . sanitize_key( $kind ) . '_' . substr( $fingerprint, 0, 32 );
    }
}
