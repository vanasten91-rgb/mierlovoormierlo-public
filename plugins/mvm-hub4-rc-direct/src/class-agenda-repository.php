<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class MvM_Hub4_Agenda_Repository {
    public static function overview( int $limit = 20 ): array {
        $limit = min( 50, max( 1, $limit ) );

        return array(
            'events'        => self::upcoming_events( $limit ),
            'scheduledNews' => self::scheduled_news( $limit ),
        );
    }

    private static function upcoming_events( int $limit ): array {
        if ( ! post_type_exists( 'event_listing' ) ) {
            return array();
        }

        $today = current_time( 'Y-m-d' );
        $query = new WP_Query(
            array(
                'post_type'              => 'event_listing',
                'post_status'            => array( 'publish', 'draft', 'pending' ),
                'posts_per_page'         => $limit,
                'meta_key'               => '_event_start_date',
                'orderby'                => 'meta_value',
                'order'                  => 'ASC',
                'meta_query'             => array(
                    array(
                        'key'     => '_event_start_date',
                        'value'   => $today,
                        'compare' => '>=',
                        'type'    => 'DATE',
                    ),
                ),
                'no_found_rows'          => true,
                'update_post_term_cache' => false,
            )
        );

        $items = array();
        foreach ( (array) $query->posts as $post ) {
            if ( ! $post instanceof WP_Post ) {
                continue;
            }
            if ( 'publish' !== $post->post_status && ! current_user_can( 'edit_post', $post->ID ) ) {
                continue;
            }

            $items[] = array(
                'id'          => (int) $post->ID,
                'title'       => get_the_title( $post ) ?: '(Zonder titel)',
                'status'      => (string) $post->post_status,
                'statusLabel' => self::status_label( (string) $post->post_status ),
                'startDate'   => sanitize_text_field( (string) get_post_meta( $post->ID, '_event_start_date', true ) ),
                'startTime'   => sanitize_text_field( (string) get_post_meta( $post->ID, '_event_start_time', true ) ),
                'location'    => sanitize_text_field( (string) get_post_meta( $post->ID, '_event_location', true ) ),
                'editUrl'     => current_user_can( 'edit_post', $post->ID ) ? esc_url_raw( (string) get_edit_post_link( $post->ID, 'raw' ) ) : '',
                'viewUrl'     => 'publish' === $post->post_status ? esc_url_raw( (string) get_permalink( $post ) ) : '',
            );
        }

        return $items;
    }

    private static function scheduled_news( int $limit ): array {
        $query = new WP_Query(
            array(
                'post_type'              => 'post',
                'post_status'            => 'future',
                'posts_per_page'         => $limit,
                'orderby'                => 'date',
                'order'                  => 'ASC',
                'no_found_rows'          => true,
                'ignore_sticky_posts'    => true,
                'update_post_meta_cache' => false,
                'update_post_term_cache' => false,
            )
        );

        $items = array();
        foreach ( (array) $query->posts as $post ) {
            if ( ! $post instanceof WP_Post || ! current_user_can( 'edit_post', $post->ID ) ) {
                continue;
            }

            $items[] = array(
                'id'       => (int) $post->ID,
                'title'    => get_the_title( $post ) ?: '(Zonder titel)',
                'dateUtc'  => get_post_time( 'Y-m-d H:i:s', true, $post ),
                'editUrl'  => esc_url_raw( (string) get_edit_post_link( $post->ID, 'raw' ) ),
            );
        }

        return $items;
    }

    private static function status_label( string $status ): string {
        return match ( $status ) {
            'publish' => 'Gepubliceerd',
            'draft'   => 'Concept',
            'pending' => 'Te beoordelen',
            default   => ucfirst( sanitize_key( $status ) ),
        };
    }
}
