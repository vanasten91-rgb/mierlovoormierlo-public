<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class MvM_Hub4_Source_Launch_Links {
    public static function init(): void {
        add_filter( 'rest_post_dispatch', array( __CLASS__, 'decorate' ), 900, 3 );
    }

    public static function decorate( WP_HTTP_Response $response, WP_REST_Server $server, WP_REST_Request $request ): WP_HTTP_Response {
        unset( $server );

        if ( '/mvm-hub4/v1/sources' !== $request->get_route() || 'GET' !== $request->get_method() ) {
            return $response;
        }

        $data = $response->get_data();
        if ( ! is_array( $data ) || ! isset( $data['items'] ) || ! is_array( $data['items'] ) ) {
            return $response;
        }

        foreach ( $data['items'] as &$item ) {
            if ( ! is_array( $item ) ) {
                continue;
            }

            $source_id = absint( $item['id'] ?? 0 );
            if ( $source_id < 1 ) {
                continue;
            }

            $item['sourcePageUrl'] = esc_url_raw( MvM_Hub4_Launch::source_launch_url( $source_id, 'record' ) );
            $item['externalUrl']   = ! empty( $item['hasExternalUrl'] )
                ? esc_url_raw( MvM_Hub4_Launch::source_launch_url( $source_id, 'external' ) )
                : '';
        }
        unset( $item );

        $response->set_data( $data );
        return $response;
    }
}
