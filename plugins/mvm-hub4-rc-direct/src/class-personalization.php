<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class MvM_Hub4_Personalization {
    private const META_TOPICS = '_mvm_hub4_topics';

    public static function get_current(): array {
        if ( ! is_user_logged_in() ) {
            return array( 'topics' => array(), 'available' => self::available_topics() );
        }
        $stored = get_user_meta( get_current_user_id(), self::META_TOPICS, true );
        $topics = self::sanitize_topics( is_array( $stored ) ? $stored : array() );
        return array( 'topics' => $topics, 'available' => self::available_topics() );
    }

    public static function update_current( array $topics ): array|WP_Error {
        if ( ! is_user_logged_in() ) {
            return new WP_Error( 'mvm_public_auth_required', 'Log in om Mijn Mierlo te gebruiken.', array( 'status' => 401 ) );
        }
        $clean = self::sanitize_topics( $topics );
        update_user_meta( get_current_user_id(), self::META_TOPICS, $clean );
        MvM_Hub4_Audit::log(
            'personalization.update',
            'success',
            array(
                'object_type' => 'user_preferences',
                'object_id'   => get_current_user_id(),
                'context'     => array( 'topic_count' => count( $clean ) ),
            )
        );
        return array( 'topics' => $clean, 'available' => self::available_topics() );
    }

    public static function feed( int $limit = 20 ): array {
        $limit = min( 50, max( 1, $limit ) );
        $topics = array();
        if ( is_user_logged_in() ) {
            $stored = get_user_meta( get_current_user_id(), self::META_TOPICS, true );
            $topics = self::sanitize_topics( is_array( $stored ) ? $stored : array() );
        }
        $args = array(
            'post_type'              => 'post',
            'post_status'            => 'publish',
            'posts_per_page'         => $limit,
            'ignore_sticky_posts'    => false,
            'no_found_rows'          => true,
            'update_post_meta_cache' => true,
            'update_post_term_cache' => true,
        );
        if ( $topics ) {
            $args['category__in'] = $topics;
        }
        $query = new WP_Query( $args );
        $items = array();
        foreach ( $query->posts as $post ) {
            if ( ! $post instanceof WP_Post ) {
                continue;
            }
            $image_url = '';
            $image_alt = '';
            $thumbnail_id = get_post_thumbnail_id( $post );
            if ( $thumbnail_id ) {
                $image_url = (string) ( wp_get_attachment_image_url( $thumbnail_id, 'medium' ) ?: '' );
                $image_alt = trim( (string) get_post_meta( $thumbnail_id, '_wp_attachment_image_alt', true ) );
                if ( '' === $image_alt ) {
                    $image_alt = sanitize_text_field( get_the_title( $post ) );
                }
            }
            $items[] = array(
                'id'         => (int) $post->ID,
                'title'      => sanitize_text_field( get_the_title( $post ) ),
                'url'        => esc_url_raw( get_permalink( $post ) ?: '' ),
                'dateUtc'    => get_post_time( 'Y-m-d H:i:s', true, $post ),
                'excerpt'    => sanitize_textarea_field( wp_trim_words( wp_strip_all_tags( get_the_excerpt( $post ) ), 32, '…' ) ),
                'categories' => array_map( 'absint', wp_get_post_categories( $post->ID, array( 'fields' => 'ids' ) ) ),
                'imageUrl'   => esc_url_raw( $image_url ),
                'imageAlt'   => sanitize_text_field( $image_alt ),
            );
        }
        wp_reset_postdata();
        return array( 'personalized' => (bool) $topics, 'topics' => $topics, 'items' => $items );
    }

    public static function available_topics(): array {
        $terms = get_categories( array( 'hide_empty' => false ) );
        $result = array();
        foreach ( is_array( $terms ) ? $terms : array() as $term ) {
            if ( ! $term instanceof WP_Term ) {
                continue;
            }
            $result[] = array(
                'id'   => (int) $term->term_id,
                'slug' => sanitize_title( $term->slug ),
                'name' => sanitize_text_field( $term->name ),
            );
        }
        return $result;
    }

    private static function sanitize_topics( array $topics ): array {
        $clean = array_values( array_unique( array_filter( array_map( 'absint', $topics ) ) ) );
        $valid = array();
        foreach ( array_slice( $clean, 0, 30 ) as $term_id ) {
            $term = get_term( $term_id, 'category' );
            if ( $term instanceof WP_Term ) {
                $valid[] = $term_id;
            }
        }
        return $valid;
    }
}
