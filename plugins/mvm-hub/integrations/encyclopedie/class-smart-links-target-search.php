<?php

namespace MVM\Hub\Integrations\Encyclopedie;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Lightweight read-only search adapter to the existing Smart Links target owner.
 *
 * This complements automatic alias context. It is intended for editorial manual
 * discovery and never makes an automatic linking decision or mutates content.
 */
final class Smart_Links_Target_Search {
    private const MAX_QUERY_CHARS = 120;
    private const MAX_RESULTS     = 12;

    /** @return array<string,mixed> */
    public static function search( string $query, int $limit = 8 ): array {
        $query = trim( sanitize_text_field( $query ) );
        $query = function_exists( 'mb_substr' )
            ? mb_substr( $query, 0, self::MAX_QUERY_CHARS )
            : substr( $query, 0, self::MAX_QUERY_CHARS );
        $limit = min( self::MAX_RESULTS, max( 1, $limit ) );

        $query_length = function_exists( 'mb_strlen' ) ? mb_strlen( $query ) : strlen( $query );
        if ( $query_length < 2 ) {
            return array(
                'available' => true,
                'items'     => array(),
                'query'     => $query,
            );
        }

        $request = new \WP_REST_Request( 'GET', '/mvm/v1/admin/smart-links/targets' );
        $request->set_param( 'search', $query );
        $request->set_param( 'limit', $limit );
        $response = rest_do_request( $request );

        if ( $response->is_error() ) {
            return array(
                'available' => false,
                'items'     => array(),
                'query'     => $query,
            );
        }

        $data = $response->get_data();
        if ( ! is_array( $data ) ) {
            return array(
                'available' => false,
                'items'     => array(),
                'query'     => $query,
            );
        }

        $source = array_is_list( $data )
            ? $data
            : ( isset( $data['items'] ) && is_array( $data['items'] ) ? $data['items'] : array() );
        $items = array();

        foreach ( $source as $item ) {
            if ( ! is_array( $item ) ) {
                continue;
            }

            $id    = absint( $item['id'] ?? $item['target_id'] ?? 0 );
            $title = trim( sanitize_text_field( (string) ( $item['title'] ?? $item['target_title'] ?? '' ) ) );
            $url   = esc_url_raw( (string) ( $item['url'] ?? $item['target_url'] ?? '' ) );
            if ( $id <= 0 || '' === $title || '' === $url ) {
                continue;
            }

            $items[] = array(
                'targetId'    => $id,
                'targetTitle' => $title,
                'targetType'  => sanitize_key( (string) ( $item['type'] ?? $item['target_type'] ?? '' ) ),
                'targetUrl'   => $url,
                'kind'        => 'related-search',
            );

            if ( count( $items ) >= $limit ) {
                break;
            }
        }

        return array(
            'available' => true,
            'items'     => array_values( $items ),
            'query'     => $query,
        );
    }

    private function __construct() {}
}
