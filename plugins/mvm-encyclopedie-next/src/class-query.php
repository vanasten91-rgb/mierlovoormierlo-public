<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class MVM_Encyclopedie_Next_Query {
    private const MAX_PER_PAGE = 24;
    private const CACHE_GROUP = 'mvm_encyclopedie_next';

    /** @var string */
    private static $title_prefix = '';

    public static function init(): void {
        add_action( 'rest_api_init', array( __CLASS__, 'register_rest_routes' ) );
    }

    /** @return array<int,array<string,mixed>> */
    private static function public_visibility_meta_query(): array {
        return array(
            'relation' => 'OR',
            array(
                'key' => '_mvm_public_hidden',
                'compare' => 'NOT EXISTS',
            ),
            array(
                'key' => '_mvm_public_hidden',
                'value' => '1',
                'compare' => '!=',
            ),
        );
    }

    public static function is_allowed_post_type( string $post_type ): bool {
        return in_array( $post_type, MVM_Encyclopedie_Next_Content_Model::post_type_slugs(), true );
    }

    /** @return array<string,int> */
    public static function counts(): array {
        $cache_key = 'counts_v2';
        $cached = wp_cache_get( $cache_key, self::CACHE_GROUP );
        if ( is_array( $cached ) ) {
            return $cached;
        }

        $counts = array();
        foreach ( MVM_Encyclopedie_Next_Content_Model::post_type_slugs() as $post_type ) {
            $query = new WP_Query(
                array(
                    'post_type'              => $post_type,
                    'post_status'            => 'publish',
                    'posts_per_page'         => 1,
                    'fields'                 => 'ids',
                    'ignore_sticky_posts'    => true,
                    'no_found_rows'          => false,
                    'meta_query'             => self::public_visibility_meta_query(),
                    'update_post_meta_cache' => false,
                    'update_post_term_cache' => false,
                )
            );
            $counts[ $post_type ] = (int) $query->found_posts;
        }

        wp_cache_set( $cache_key, $counts, self::CACHE_GROUP, 300 );
        return $counts;
    }

    /**
     * Bounded public query used by shortcodes and REST.
     *
     * @param array<string,mixed> $args
     */
    public static function search( array $args = array() ): WP_Query {
        $page = max( 1, isset( $args['page'] ) ? (int) $args['page'] : 1 );
        $per_page = min( self::MAX_PER_PAGE, max( 1, isset( $args['per_page'] ) ? (int) $args['per_page'] : self::MAX_PER_PAGE ) );
        $post_type = isset( $args['post_type'] ) ? sanitize_key( (string) $args['post_type'] ) : '';
        $post_types = self::is_allowed_post_type( $post_type )
            ? array( $post_type )
            : MVM_Encyclopedie_Next_Content_Model::post_type_slugs();

        $query_args = array(
            'post_type' => $post_types,
            'post_status' => 'publish',
            'posts_per_page' => $per_page,
            'paged' => $page,
            'ignore_sticky_posts' => true,
            'no_found_rows' => false,
            'meta_query' => self::public_visibility_meta_query(),
            'update_post_meta_cache' => false,
            'update_post_term_cache' => true,
        );

        $search = isset( $args['search'] ) ? trim( sanitize_text_field( (string) $args['search'] ) ) : '';
        if ( '' !== $search ) {
            $query_args['s'] = $search;
            $query_args['orderby'] = array( 'relevance' => 'DESC', 'title' => 'ASC' );
        } else {
            $query_args['orderby'] = 'title';
            $query_args['order'] = 'ASC';
        }

        if ( ! empty( $args['tax_query'] ) && is_array( $args['tax_query'] ) ) {
            $query_args['tax_query'] = $args['tax_query'];
        }

        $letter = isset( $args['letter'] ) ? strtoupper( substr( sanitize_text_field( (string) $args['letter'] ), 0, 1 ) ) : '';
        if ( preg_match( '/^[A-Z]$/', $letter ) ) {
            self::$title_prefix = $letter;
            add_filter( 'posts_where', array( __CLASS__, 'filter_title_prefix' ), 10, 2 );
        }

        $query = new WP_Query( $query_args );

        if ( '' !== self::$title_prefix ) {
            remove_filter( 'posts_where', array( __CLASS__, 'filter_title_prefix' ), 10 );
            self::$title_prefix = '';
        }

        return $query;
    }

    public static function filter_title_prefix( string $where, WP_Query $query ): string {
        if ( '' === self::$title_prefix ) {
            return $where;
        }

        global $wpdb;
        $where .= $wpdb->prepare( " AND {$wpdb->posts}.post_title LIKE %s", $wpdb->esc_like( self::$title_prefix ) . '%' );
        return $where;
    }

    /** @return array<string,mixed> */
    public static function summary( WP_Post $post ): array {
        $excerpt = trim( wp_strip_all_tags( (string) get_the_excerpt( $post ) ) );
        if ( '' === $excerpt ) {
            $excerpt = wp_trim_words( wp_strip_all_tags( (string) $post->post_content ), 26, '…' );
        }

        return array(
            'id' => (int) $post->ID,
            'title' => get_the_title( $post ),
            'type' => (string) $post->post_type,
            'type_label' => MVM_Encyclopedie_Next_Content_Model::label_for( (string) $post->post_type ),
            'url' => get_permalink( $post ),
            'excerpt' => $excerpt,
            'thumbnail' => get_the_post_thumbnail_url( $post, 'medium' ) ?: '',
        );
    }

    /** @return int[] */
    public static function relation_ids( int $post_id ): array {
        $raw = get_post_meta( $post_id, '_mvm_relations', true );
        $raw = maybe_unserialize( $raw );
        if ( ! is_array( $raw ) ) {
            return array();
        }

        $ids = array_values( array_unique( array_filter( array_map( 'absint', $raw ) ) ) );
        return array_values( array_filter( $ids, static function ( int $id ): bool {
            if ( $id <= 0 || 'publish' !== get_post_status( $id ) ) {
                return false;
            }

            $type = get_post_type( $id );
            if ( ! is_string( $type ) || ! self::is_allowed_post_type( $type ) ) {
                return false;
            }

            return '1' !== (string) get_post_meta( $id, '_mvm_public_hidden', true );
        } ) );
    }

    /** @return array<int,array<string,mixed>> */
    public static function relations( int $post_id, int $offset = 0, int $per_page = 12 ): array {
        $ids = self::relation_ids( $post_id );
        if ( ! $ids ) {
            return array();
        }

        $per_page = min( self::MAX_PER_PAGE, max( 1, $per_page ) );
        $slice = array_slice( $ids, max( 0, $offset ), $per_page );
        if ( ! $slice ) {
            return array();
        }

        $posts = get_posts(
            array(
                'post_type' => MVM_Encyclopedie_Next_Content_Model::post_type_slugs(),
                'post_status' => 'publish',
                'post__in' => $slice,
                'orderby' => 'post__in',
                'posts_per_page' => $per_page,
                'ignore_sticky_posts' => true,
                'no_found_rows' => true,
                'update_post_meta_cache' => false,
                'update_post_term_cache' => false,
            )
        );

        return array_map( array( __CLASS__, 'summary' ), $posts );
    }

    /** @return WP_Post[] */
    public static function timeline( int $limit = 8 ): array {
        return get_posts(
            array(
                'post_type' => 'mvm_gebeurtenis',
                'post_status' => 'publish',
                'posts_per_page' => min( 12, max( 1, $limit ) ),
                'meta_key' => '_mvm_start_date',
                'orderby' => 'meta_value',
                'order' => 'DESC',
                'meta_query' => self::public_visibility_meta_query(),
                'ignore_sticky_posts' => true,
                'no_found_rows' => true,
            )
        );
    }

    public static function register_rest_routes(): void {
        register_rest_route(
            'mvm-encyclopedie/v1',
            '/search',
            array(
                'methods' => WP_REST_Server::READABLE,
                'permission_callback' => '__return_true',
                'callback' => array( __CLASS__, 'rest_search' ),
                'args' => array(
                    'q' => array( 'sanitize_callback' => 'sanitize_text_field', 'default' => '' ),
                    'type' => array( 'sanitize_callback' => 'sanitize_key', 'default' => '' ),
                    'letter' => array( 'sanitize_callback' => 'sanitize_text_field', 'default' => '' ),
                    'page' => array( 'sanitize_callback' => 'absint', 'default' => 1 ),
                    'per_page' => array( 'sanitize_callback' => 'absint', 'default' => 12 ),
                ),
            )
        );

        register_rest_route(
            'mvm-encyclopedie/v1',
            '/items/(?P<id>\d+)/relations',
            array(
                'methods' => WP_REST_Server::READABLE,
                'permission_callback' => '__return_true',
                'callback' => array( __CLASS__, 'rest_relations' ),
                'args' => array(
                    'id' => array( 'sanitize_callback' => 'absint' ),
                    'offset' => array( 'sanitize_callback' => 'absint', 'default' => 0 ),
                    'per_page' => array( 'sanitize_callback' => 'absint', 'default' => 12 ),
                ),
            )
        );
    }

    public static function rest_search( WP_REST_Request $request ): WP_REST_Response {
        $query = self::search(
            array(
                'search' => (string) $request->get_param( 'q' ),
                'post_type' => (string) $request->get_param( 'type' ),
                'letter' => (string) $request->get_param( 'letter' ),
                'page' => (int) $request->get_param( 'page' ),
                'per_page' => min( self::MAX_PER_PAGE, max( 1, (int) $request->get_param( 'per_page' ) ) ),
            )
        );

        $items = array_map( array( __CLASS__, 'summary' ), $query->posts );
        return new WP_REST_Response(
            array(
                'items' => $items,
                'total' => (int) $query->found_posts,
                'pages' => (int) $query->max_num_pages,
            ),
            200
        );
    }

    public static function rest_relations( WP_REST_Request $request ): WP_REST_Response {
        $post_id = absint( $request->get_param( 'id' ) );
        $post_type = get_post_type( $post_id );
        if (
            ! is_string( $post_type ) ||
            ! self::is_allowed_post_type( $post_type ) ||
            'publish' !== get_post_status( $post_id ) ||
            '1' === (string) get_post_meta( $post_id, '_mvm_public_hidden', true )
        ) {
            return new WP_REST_Response( array( 'items' => array(), 'total' => 0 ), 404 );
        }

        $ids = self::relation_ids( $post_id );
        $offset = max( 0, absint( $request->get_param( 'offset' ) ) );
        $per_page = min( self::MAX_PER_PAGE, max( 1, absint( $request->get_param( 'per_page' ) ) ) );

        return new WP_REST_Response(
            array(
                'items' => self::relations( $post_id, $offset, $per_page ),
                'total' => count( $ids ),
            ),
            200
        );
    }
}
