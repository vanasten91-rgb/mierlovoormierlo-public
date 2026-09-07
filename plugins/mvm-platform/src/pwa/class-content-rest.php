<?php

defined( 'ABSPATH' ) || exit;

final class MvM_Platform_Content_REST {
	private const ENCYCLOPEDIA_TYPES = array(
		'mvm_encyclopedie',
		'mvm_persoon',
		'mvm_locatie',
		'mvm_gebouw',
		'mvm_gebeurtenis',
		'mvm_vereniging',
		'mvm_bedrijf',
		'mvm_beeld',
		'mvm_bron',
	);

	public static function register_routes(): void {
		foreach (
			array(
				'/content/news'         => 'news',
				'/content/events'       => 'events',
				'/content/encyclopedia' => 'encyclopedia',
			) as $route => $callback
		) {
			register_rest_route(
				MvM_Platform_REST::NAMESPACE,
				$route,
				array(
					'methods'             => WP_REST_Server::READABLE,
					'permission_callback' => '__return_true',
					'callback'            => array( __CLASS__, $callback ),
					'args'                => self::common_args(),
				)
			);
		}
	}

	private static function common_args(): array {
		return array(
			'page' => array(
				'type'              => 'integer',
				'default'           => 1,
				'sanitize_callback' => 'absint',
				'validate_callback' => static fn( $value ): bool => absint( $value ) >= 1,
			),
			'per_page' => array(
				'type'              => 'integer',
				'default'           => 12,
				'sanitize_callback' => 'absint',
				'validate_callback' => static fn( $value ): bool => absint( $value ) >= 1 && absint( $value ) <= 24,
			),
			'search' => array(
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'category' => array(
				'type'              => 'integer',
				'default'           => 0,
				'sanitize_callback' => 'absint',
			),
			'type' => array(
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'sanitize_key',
			),
		);
	}

	private static function paging( WP_REST_Request $request ): array {
		return array(
			max( 1, absint( $request->get_param( 'page' ) ) ),
			min( 24, max( 1, absint( $request->get_param( 'per_page' ) ) ?: 12 ) ),
		);
	}

	public static function news( WP_REST_Request $request ): WP_REST_Response {
		list( $page, $per_page ) = self::paging( $request );
		$args = array(
			'post_type'              => 'post',
			'post_status'            => 'publish',
			'ignore_sticky_posts'    => true,
			'paged'                  => $page,
			'posts_per_page'         => $per_page,
			'orderby'                => 'date',
			'order'                  => 'DESC',
			's'                      => sanitize_text_field( (string) $request->get_param( 'search' ) ),
			'no_found_rows'          => false,
			'update_post_meta_cache' => false,
		);
		$category = absint( $request->get_param( 'category' ) );
		if ( $category > 0 ) {
			$args['cat'] = $category;
		}
		$query = new WP_Query( $args );
		$items = array_map( array( __CLASS__, 'post_card' ), $query->posts );
		return self::public_collection( $items, $query );
	}

	public static function events( WP_REST_Request $request ): WP_REST_Response {
		list( $page, $per_page ) = self::paging( $request );
		$args = array(
			'post_type'              => 'event_listing',
			'post_status'            => 'publish',
			'paged'                  => $page,
			'posts_per_page'         => $per_page,
			'orderby'                => 'meta_value',
			'meta_key'               => '_event_start_date',
			'order'                  => 'ASC',
			's'                      => sanitize_text_field( (string) $request->get_param( 'search' ) ),
			'no_found_rows'          => false,
			'update_post_meta_cache' => true,
		);
		$category = absint( $request->get_param( 'category' ) );
		if ( $category > 0 && taxonomy_exists( 'event_listing_category' ) ) {
			$args['tax_query'] = array(
				array(
					'taxonomy' => 'event_listing_category',
					'field'    => 'term_id',
					'terms'    => array( $category ),
				),
			);
		}
		$query = new WP_Query( $args );
		$items = array_map( array( __CLASS__, 'event_card' ), $query->posts );
		return self::public_collection( $items, $query );
	}

	public static function encyclopedia( WP_REST_Request $request ): WP_REST_Response {
		list( $page, $per_page ) = self::paging( $request );
		$type = sanitize_key( (string) $request->get_param( 'type' ) );
		$types = self::ENCYCLOPEDIA_TYPES;
		if ( $type && in_array( $type, self::ENCYCLOPEDIA_TYPES, true ) ) {
			$types = array( $type );
		}
		$query = new WP_Query(
			array(
				'post_type'              => $types,
				'post_status'            => 'publish',
				'paged'                  => $page,
				'posts_per_page'         => $per_page,
				'orderby'                => 'title',
				'order'                  => 'ASC',
				's'                      => sanitize_text_field( (string) $request->get_param( 'search' ) ),
				'no_found_rows'          => false,
				'update_post_meta_cache' => false,
			)
		);
		$items = array_map( array( __CLASS__, 'encyclopedia_card' ), $query->posts );
		return self::public_collection( $items, $query );
	}

	private static function post_card( WP_Post $post ): array {
		$category_terms = get_the_category( $post->ID );
		$categories = array_map(
			static fn( WP_Term $term ): array => array( 'id' => (int) $term->term_id, 'name' => $term->name, 'slug' => $term->slug ),
			$category_terms
		);
		return array(
			'id'         => (int) $post->ID,
			'type'       => 'news',
			'title'      => get_the_title( $post ),
			'excerpt'    => self::excerpt( $post ),
			'url'        => get_permalink( $post ),
			'image'      => get_the_post_thumbnail_url( $post, 'large' ) ?: '',
			'published'  => get_post_time( DATE_ATOM, true, $post ),
			'categories' => $categories,
		);
	}

	private static function event_card( WP_Post $post ): array {
		$start = function_exists( 'get_event_start_date' ) ? (string) get_event_start_date( $post ) : (string) get_post_meta( $post->ID, '_event_start_date', true );
		$end   = function_exists( 'get_event_end_date' ) ? (string) get_event_end_date( $post ) : (string) get_post_meta( $post->ID, '_event_end_date', true );
		$location = function_exists( 'get_event_location' ) ? (string) get_event_location( $post ) : (string) get_post_meta( $post->ID, '_event_location', true );
		$categories = array();
		if ( taxonomy_exists( 'event_listing_category' ) ) {
			$terms = wp_get_post_terms( $post->ID, 'event_listing_category' );
			if ( ! is_wp_error( $terms ) ) {
				$categories = array_map(
					static fn( WP_Term $term ): array => array( 'id' => (int) $term->term_id, 'name' => $term->name, 'slug' => $term->slug ),
					$terms
				);
			}
		}
		return array(
			'id'         => (int) $post->ID,
			'type'       => 'event',
			'title'      => get_the_title( $post ),
			'excerpt'    => self::excerpt( $post ),
			'url'        => get_permalink( $post ),
			'image'      => get_the_post_thumbnail_url( $post, 'large' ) ?: '',
			'start'      => sanitize_text_field( $start ),
			'end'        => sanitize_text_field( $end ),
			'location'   => sanitize_text_field( wp_strip_all_tags( $location ) ),
			'categories' => $categories,
		);
	}

	private static function encyclopedia_card( WP_Post $post ): array {
		return array(
			'id'      => (int) $post->ID,
			'type'    => $post->post_type,
			'title'   => get_the_title( $post ),
			'excerpt' => self::excerpt( $post ),
			'url'     => get_permalink( $post ),
			'image'   => get_the_post_thumbnail_url( $post, 'large' ) ?: '',
		);
	}

	private static function excerpt( WP_Post $post ): string {
		$text = $post->post_excerpt ?: $post->post_content;
		$text = strip_shortcodes( $text );
		$text = wp_strip_all_tags( $text, true );
		return wp_trim_words( $text, 38, '…' );
	}

	private static function public_collection( array $items, WP_Query $query ): WP_REST_Response {
		$response = rest_ensure_response( $items );
		$response->header( 'X-WP-Total', (string) $query->found_posts );
		$response->header( 'X-WP-TotalPages', (string) $query->max_num_pages );
		$response->header( 'Cache-Control', 'public, max-age=60, stale-while-revalidate=120' );
		return $response;
	}
}
