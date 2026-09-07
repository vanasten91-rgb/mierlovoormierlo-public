<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class MvM_Hub4_Response_Hardening {
    public static function init(): void {
        add_filter( 'rest_post_dispatch', array( __CLASS__, 'filter_response' ), 1000, 3 );
    }

    public static function filter_response( WP_HTTP_Response $response, WP_REST_Server $server, WP_REST_Request $request ): WP_HTTP_Response {
        unset( $server );

        if ( 0 !== strpos( $request->get_route(), '/mvm-hub4/v1/' ) ) {
            return $response;
        }

        $response->set_data( self::sanitize_value( $response->get_data() ) );
        return $response;
    }

    private static function sanitize_value( mixed $value ): mixed {
        if ( ! is_array( $value ) ) {
            return $value;
        }

        $clean = array();
        foreach ( $value as $key => $item ) {
            if ( is_string( $key ) && self::is_sensitive_key( $key ) ) {
                continue;
            }
            $clean[ $key ] = self::sanitize_value( $item );
        }
        return $clean;
    }

    private static function is_sensitive_key( string $key ): bool {
        $key = (string) preg_replace( '/([a-z0-9])([A-Z])/', '$1_$2', $key );

        return 1 === preg_match(
            '/password|passwd|passphrase|(^|[_-])(pass|pwd)([_-]|$)|token|nonce|cookie|authorization|secret|api[_-]?key|(^|[_-])session($|[_-](id|key|token|cookie|secret)([_-]|$))|e[_-]?mail|email|private[_-]?note/i',
            $key
        );
    }
}
