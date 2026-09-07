<?php

defined( 'ABSPATH' ) || exit;

final class MvM_Marketplace {
	public const POST_TYPE        = 'mvm_listing';
	public const TAXONOMY         = 'mvm_listing_category';
	public const EXPIRE_HOOK      = 'mvm_marketplace_expire_listings';
	public const DEFAULT_LIFETIME = 30 * DAY_IN_SECONDS;
	public const MAX_GALLERY      = 8;

	private const META_PRICE_CENTS = '_mvm_marketplace_price_cents';
	private const META_PRICE_TYPE  = '_mvm_marketplace_price_type';
	private const META_CONDITION   = '_mvm_marketplace_condition';
	private const META_STATUS      = '_mvm_marketplace_status';
	private const META_LOCATION    = '_mvm_marketplace_location';
	private const META_EXPIRES_AT  = '_mvm_marketplace_expires_at';
	private const META_GALLERY     = '_mvm_marketplace_gallery';
	private const META_REPORTS     = '_mvm_marketplace_reports';
	private const META_AUDIT       = '_mvm_marketplace_audit';

	public static function boot(): void {
		add_action( 'init', array( __CLASS__, 'register_content_types' ), 10 );
		add_action( 'init', array( __CLASS__, 'register_meta' ), 11 );
		add_action( self::EXPIRE_HOOK, array( __CLASS__, 'expire_due_listings' ) );
	}

	public static function activate(): void {
		self::register_content_types();
		self::register_default_categories();
		if ( ! wp_next_scheduled( self::EXPIRE_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', self::EXPIRE_HOOK );
		}
		flush_rewrite_rules( false );
	}

	public static function deactivate(): void {
		wp_clear_scheduled_hook( self::EXPIRE_HOOK );
		flush_rewrite_rules( false );
	}

	public static function register_content_types(): void {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels' => array(
					'name'          => 'Marktplaats',
					'singular_name' => 'Advertentie',
				),
				'public'              => true,
				'publicly_queryable'  => true,
				'exclude_from_search' => false,
				'show_ui'             => false,
				'show_in_rest'        => false,
				'has_archive'         => 'marktplaats',
				'rewrite'             => array( 'slug' => 'marktplaats', 'with_front' => false ),
				'supports'            => array( 'title', 'editor', 'thumbnail', 'author' ),
				'capability_type'     => array( 'mvm_listing', 'mvm_listings' ),
				'map_meta_cap'        => true,
				'menu_icon'           => 'dashicons-store',
			)
		);

		register_taxonomy(
			self::TAXONOMY,
			self::POST_TYPE,
			array(
				'labels' => array(
					'name'          => 'Marktplaatscategorieën',
					'singular_name' => 'Marktplaatscategorie',
				),
				'public'       => true,
				'show_ui'      => false,
				'show_in_rest' => false,
				'hierarchical' => true,
				'rewrite'      => array( 'slug' => 'marktplaats-categorie', 'with_front' => false ),
			)
		);
	}

	public static function register_meta(): void {
		$auth = static fn(): bool => current_user_can( MvM_Platform_Capabilities::MARKETPLACE_MODERATE );
		foreach (
			array(
				self::META_PRICE_CENTS => 'integer',
				self::META_PRICE_TYPE  => 'string',
				self::META_CONDITION   => 'string',
				self::META_STATUS      => 'string',
				self::META_LOCATION    => 'string',
				self::META_EXPIRES_AT  => 'integer',
			) as $key => $type
		) {
			register_post_meta(
				self::POST_TYPE,
				$key,
				array(
					'type'          => $type,
					'single'        => true,
					'show_in_rest'  => false,
					'auth_callback' => $auth,
				)
			);
		}
	}

	private static function register_default_categories(): void {
		foreach (
			array(
				'Huis & wonen',
				'Elektronica',
				'Fietsen & vervoer',
				'Kleding & accessoires',
				'Kinderen & speelgoed',
				'Hobby & vrije tijd',
				'Boeken & media',
				'Tuin & buiten',
				'Gratis afhalen',
				'Overig',
			) as $category
		) {
			if ( ! term_exists( $category, self::TAXONOMY ) ) {
				wp_insert_term( $category, self::TAXONOMY );
			}
		}
	}

	public static function price_types(): array {
		return array( 'fixed', 'free', 'swap' );
	}

	public static function conditions(): array {
		return array( 'new', 'like_new', 'good', 'fair', 'used' );
	}

	public static function public_statuses(): array {
		return array( 'active', 'reserved', 'sold' );
	}

	public static function owner_statuses(): array {
		return array( 'active', 'reserved', 'sold' );
	}

	public static function all_statuses(): array {
		return array( 'active', 'reserved', 'sold', 'expired', 'moderated' );
	}

	/**
	 * Every normal logged-in WordPress member may use MvM Marktplaats.
	 * The basic `read` capability is deliberately used here: it is present on
	 * the site's ordinary subscriber/Lid role, but does not grant any wp-admin,
	 * publishing or moderation privilege. Moderation remains a separate MvM
	 * capability. Sites can still suspend marketplace use for a specific user
	 * through the mvm_marketplace_user_can_list filter.
	 */
	public static function can_create_listing( int $user_id = 0 ): bool {
		$user_id = $user_id ?: get_current_user_id();
		$user    = $user_id > 0 ? get_userdata( $user_id ) : false;
		if ( ! ( $user instanceof WP_User ) || ! user_can( $user, 'read' ) ) {
			return false;
		}
		return (bool) apply_filters( 'mvm_marketplace_user_can_list', true, $user_id );
	}

	public static function is_owner( int $post_id, int $user_id = 0 ): bool {
		$user_id = $user_id ?: get_current_user_id();
		$post    = get_post( $post_id );
		return $post instanceof WP_Post
			&& self::POST_TYPE === $post->post_type
			&& $user_id > 0
			&& (int) $post->post_author === $user_id;
	}

	public static function validate_listing_text( string $title, string $description ) {
		$text = strtolower( wp_strip_all_tags( $title . ' ' . $description ) );
		$patterns = array(
			'/\b(vuurwapen|pistool|revolver|geweer|munitie|patronen|wapenonderdeel)\b/u',
			'/\b(vuurwerk|explosief|springstof)\b/u',
			'/\b(cannabis|wiet|hasj|coca[iï]ne|xtc|mdma|drugs)\b/u',
			'/\b(sigaret|tabak|vape|e-sigaret|nicotine)\b/u',
			'/\b(bier|wijn|whisky|vodka|sterke drank)\b/u',
			'/\b(receptplichtig|medicijn|geneesmiddel)\b/u',
		);
		foreach ( $patterns as $pattern ) {
			if ( preg_match( $pattern, $text ) ) {
				return new WP_Error( 'mvm_marketplace_prohibited_item', 'Dit type product mag niet via MvM Marktplaats worden aangeboden.' );
			}
		}
		return true;
	}

	public static function normalize_price( string $price_type, $price_cents ): array {
		$price_type = sanitize_key( $price_type );
		if ( ! in_array( $price_type, self::price_types(), true ) ) {
			$price_type = 'fixed';
		}
		$cents = max( 0, min( 10000000, absint( $price_cents ) ) );
		if ( 'fixed' !== $price_type ) {
			$cents = 0;
		}
		return array( $price_type, $cents );
	}

	public static function sanitize_location( string $location ): string {
		$location = sanitize_text_field( $location );
		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( $location, 0, 80, 'UTF-8' );
		}
		return substr( $location, 0, 80 );
	}

	public static function set_fields( int $post_id, array $fields ): void {
		list( $price_type, $price_cents ) = self::normalize_price(
			(string) ( $fields['price_type'] ?? 'fixed' ),
			$fields['price_cents'] ?? 0
		);
		$condition = sanitize_key( (string) ( $fields['condition'] ?? 'used' ) );
		if ( ! in_array( $condition, self::conditions(), true ) ) {
			$condition = 'used';
		}
		$status = sanitize_key( (string) ( $fields['status'] ?? 'active' ) );
		if ( ! in_array( $status, self::all_statuses(), true ) ) {
			$status = 'active';
		}
		$expires_at = isset( $fields['expires_at'] ) ? absint( $fields['expires_at'] ) : ( time() + self::DEFAULT_LIFETIME );
		$expires_at = max( time() + HOUR_IN_SECONDS, min( $expires_at, time() + ( 90 * DAY_IN_SECONDS ) ) );

		update_post_meta( $post_id, self::META_PRICE_TYPE, $price_type );
		update_post_meta( $post_id, self::META_PRICE_CENTS, $price_cents );
		update_post_meta( $post_id, self::META_CONDITION, $condition );
		update_post_meta( $post_id, self::META_STATUS, $status );
		update_post_meta( $post_id, self::META_LOCATION, self::sanitize_location( (string) ( $fields['location'] ?? '' ) ) );
		update_post_meta( $post_id, self::META_EXPIRES_AT, $expires_at );

		if ( array_key_exists( 'gallery', $fields ) ) {
			$gallery = array_values( array_unique( array_filter( array_map( 'absint', (array) $fields['gallery'] ) ) ) );
			update_post_meta( $post_id, self::META_GALLERY, array_slice( $gallery, 0, self::MAX_GALLERY ) );
		}
	}

	public static function listing_data( WP_Post $post, bool $include_private = false ): array {
		$author_id = (int) $post->post_author;
		$author    = get_userdata( $author_id );
		$gallery   = array_values( array_filter( array_map( 'absint', (array) get_post_meta( $post->ID, self::META_GALLERY, true ) ) ) );
		$gallery_urls = array();
		foreach ( array_slice( $gallery, 0, self::MAX_GALLERY ) as $attachment_id ) {
			$url = wp_get_attachment_image_url( $attachment_id, 'large' );
			if ( $url ) {
				$gallery_urls[] = esc_url_raw( $url );
			}
		}

		$data = array(
			'id'             => (int) $post->ID,
			'title'          => get_the_title( $post ),
			'description'    => wpautop( wp_kses_post( $post->post_content ) ),
			'url'            => get_permalink( $post ),
			'price_type'     => (string) get_post_meta( $post->ID, self::META_PRICE_TYPE, true ),
			'price_cents'    => (int) get_post_meta( $post->ID, self::META_PRICE_CENTS, true ),
			'condition'      => (string) get_post_meta( $post->ID, self::META_CONDITION, true ),
			'status'         => (string) get_post_meta( $post->ID, self::META_STATUS, true ),
			'location'       => (string) get_post_meta( $post->ID, self::META_LOCATION, true ),
			'expires_at'     => (int) get_post_meta( $post->ID, self::META_EXPIRES_AT, true ),
			'categories'     => wp_get_post_terms( $post->ID, self::TAXONOMY, array( 'fields' => 'id=>name' ) ),
			'gallery'        => $gallery_urls,
			'featured_image' => get_the_post_thumbnail_url( $post, 'large' ) ?: '',
			'seller'         => array(
				'display_name' => $author instanceof WP_User ? $author->display_name : 'MvM-inwoner',
				'profile_url'  => apply_filters( 'mvm_marketplace_seller_profile_url', get_author_posts_url( $author_id ), $author_id ),
			),
			'created_at'     => get_post_time( DATE_ATOM, true, $post ),
			'updated_at'     => get_post_modified_time( DATE_ATOM, true, $post ),
		);

		if ( $include_private ) {
			$data['owner_id']     = $author_id;
			$data['post_status']  = $post->post_status;
			$data['gallery_ids']  = $gallery;
			$data['report_count'] = count( (array) get_post_meta( $post->ID, self::META_REPORTS, true ) );
		}
		return $data;
	}

	public static function add_report( int $post_id, int $user_id, string $reason ): bool {
		$reason = sanitize_textarea_field( $reason );
		$reason = function_exists( 'mb_substr' ) ? mb_substr( $reason, 0, 500, 'UTF-8' ) : substr( $reason, 0, 500 );
		$reports = (array) get_post_meta( $post_id, self::META_REPORTS, true );
		$reports[] = array(
			'user_id' => $user_id,
			'reason'  => $reason,
			'at'      => time(),
		);
		update_post_meta( $post_id, self::META_REPORTS, array_slice( $reports, -100 ) );
		self::audit( $post_id, 'reported', $user_id, array( 'reason' => $reason ) );
		return true;
	}

	public static function audit( int $post_id, string $event, int $actor_id, array $context = array() ): void {
		$entries = (array) get_post_meta( $post_id, self::META_AUDIT, true );
		$entries[] = array(
			'event'    => sanitize_key( $event ),
			'actor_id' => $actor_id,
			'at'       => time(),
			'context'  => $context,
		);
		update_post_meta( $post_id, self::META_AUDIT, array_slice( $entries, -50 ) );
		do_action( 'mvm_platform_audit_event', 'marketplace', $event, $post_id, $actor_id, $context );
	}

	public static function expire_due_listings(): void {
		$ids = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'publish',
				'fields'         => 'ids',
				'posts_per_page' => 100,
				'meta_query'     => array(
					array(
						'key'     => self::META_EXPIRES_AT,
						'value'   => time(),
						'compare' => '<=',
						'type'    => 'NUMERIC',
					),
				),
			)
		);
		foreach ( $ids as $post_id ) {
			update_post_meta( $post_id, self::META_STATUS, 'expired' );
			wp_update_post( array( 'ID' => (int) $post_id, 'post_status' => 'draft' ) );
			self::audit( (int) $post_id, 'expired', 0 );
		}
	}

	public static function meta_key( string $field ): string {
		$map = array(
			'price_cents' => self::META_PRICE_CENTS,
			'price_type'  => self::META_PRICE_TYPE,
			'condition'   => self::META_CONDITION,
			'status'      => self::META_STATUS,
			'location'    => self::META_LOCATION,
			'expires_at'  => self::META_EXPIRES_AT,
			'gallery'     => self::META_GALLERY,
			'reports'     => self::META_REPORTS,
			'audit'       => self::META_AUDIT,
		);
		return $map[ $field ] ?? '';
	}
}
