<?php

namespace MVM\Hub\Integrations\Encyclopedie;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Read-only adapter from Newsroom authoring context to the existing Smart Links
 * owner. It never writes links or duplicates the public Smart Links runtime.
 */
final class Smart_Links_Context {
    private const MAX_TEXT_BYTES = 30000;
    private const MAX_RESULTS    = 12;

    /**
     * @return array<string,mixed>
     */
    public static function for_news_post( int $post_id, int $limit = 8 ): array {
        $post = get_post( $post_id );
        if ( ! $post instanceof \WP_Post || 'post' !== $post->post_type ) {
            return array(
                'available'   => false,
                'suggestions' => array(),
                'conflicts'   => 0,
            );
        }

        $limit = min( self::MAX_RESULTS, max( 1, $limit ) );
        $text  = trim( wp_strip_all_tags( $post->post_title . "\n" . $post->post_excerpt . "\n" . $post->post_content ) );
        if ( function_exists( 'mb_substr' ) ) {
            $text = mb_substr( $text, 0, self::MAX_TEXT_BYTES );
        } else {
            $text = substr( $text, 0, self::MAX_TEXT_BYTES );
        }

        /**
         * A future standalone Smart Links service can provide suggestions directly
         * without changing Newsroom code.
         *
         * @param array<int,array<string,mixed>> $suggestions
         */
        $provided = apply_filters( 'mvm_smart_links_newsroom_context_v1', array(), $post_id, $text, $limit );
        if ( is_array( $provided ) && array() !== $provided ) {
            return array(
                'available'   => true,
                'suggestions' => self::normalize_suggestions( $provided, $limit ),
                'conflicts'   => 0,
                'source'      => 'provider',
            );
        }

        // Transition adapter: dispatch the existing in-process REST controller.
        // This is not self-HTTP and preserves the active route's permission model.
        $request  = new \WP_REST_Request( 'GET', '/mvm/v1/admin/smart-links' );
        $response = rest_do_request( $request );
        if ( $response->is_error() ) {
            return array(
                'available'   => false,
                'suggestions' => array(),
                'conflicts'   => 0,
                'source'      => 'unavailable',
            );
        }

        $data = $response->get_data();
        if ( ! is_array( $data ) ) {
            return array(
                'available'   => false,
                'suggestions' => array(),
                'conflicts'   => 0,
                'source'      => 'invalid',
            );
        }

        $aliases = array_merge(
            isset( $data['manual_aliases'] ) && is_array( $data['manual_aliases'] ) ? $data['manual_aliases'] : array(),
            isset( $data['automatic_aliases'] ) && is_array( $data['automatic_aliases'] ) ? $data['automatic_aliases'] : array()
        );

        $matches = array();
        $seen    = array();

        foreach ( $aliases as $item ) {
            if ( ! is_array( $item ) ) {
                continue;
            }

            $label = trim( sanitize_text_field( (string) ( $item['label'] ?? '' ) ) );
            $id    = absint( $item['target_id'] ?? 0 );
            $url   = esc_url_raw( (string) ( $item['target_url'] ?? '' ) );
            if ( '' === $label || $id <= 0 || '' === $url || isset( $seen[ $id . '|' . strtolower( $label ) ] ) ) {
                continue;
            }

            $position = function_exists( 'mb_stripos' ) ? mb_stripos( $text, $label ) : stripos( $text, $label );
            if ( false === $position ) {
                continue;
            }

            $seen[ $id . '|' . strtolower( $label ) ] = true;
            $matches[] = array(
                'label'       => $label,
                'targetId'    => $id,
                'targetTitle' => sanitize_text_field( (string) ( $item['target_title'] ?? '' ) ),
                'targetType'  => sanitize_key( (string) ( $item['target_type'] ?? '' ) ),
                'targetUrl'   => $url,
                'position'    => (int) $position,
            );
        }

        usort(
            $matches,
            static function ( array $left, array $right ): int {
                $position = (int) $left['position'] <=> (int) $right['position'];
                if ( 0 !== $position ) {
                    return $position;
                }
                return strlen( (string) $right['label'] ) <=> strlen( (string) $left['label'] );
            }
        );

        $matches = array_slice( $matches, 0, $limit );
        foreach ( $matches as &$match ) {
            unset( $match['position'] );
        }
        unset( $match );

        $conflicts = 0;
        if ( isset( $data['diagnostics']['conflicts'] ) && is_array( $data['diagnostics']['conflicts'] ) ) {
            $conflicts = count( $data['diagnostics']['conflicts'] );
        }

        return array(
            'available'   => true,
            'suggestions' => $matches,
            'conflicts'   => $conflicts,
            'source'      => 'legacy-smart-links-adapter',
        );
    }

    /**
     * @param array<int,mixed> $items
     * @return array<int,array<string,mixed>>
     */
    private static function normalize_suggestions( array $items, int $limit ): array {
        $clean = array();
        foreach ( $items as $item ) {
            if ( ! is_array( $item ) ) {
                continue;
            }
            $id  = absint( $item['targetId'] ?? $item['target_id'] ?? 0 );
            $url = esc_url_raw( (string) ( $item['targetUrl'] ?? $item['target_url'] ?? '' ) );
            if ( $id <= 0 || '' === $url ) {
                continue;
            }
            $clean[] = array(
                'label'       => sanitize_text_field( (string) ( $item['label'] ?? '' ) ),
                'targetId'    => $id,
                'targetTitle' => sanitize_text_field( (string) ( $item['targetTitle'] ?? $item['target_title'] ?? '' ) ),
                'targetType'  => sanitize_key( (string) ( $item['targetType'] ?? $item['target_type'] ?? '' ) ),
                'targetUrl'   => $url,
            );
            if ( count( $clean ) >= $limit ) {
                break;
            }
        }
        return $clean;
    }

    private function __construct() {}
}
