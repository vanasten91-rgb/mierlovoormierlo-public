<?php

defined( 'ABSPATH' ) || exit;

final class MvM_Local_Business_Ads {
	public const POST_TYPE = 'mvm_local_ad';

	private const META_ADVERTISER = '_mvm_local_ad_advertiser';
	private const META_HEADLINE   = '_mvm_local_ad_headline';
	private const META_TEXT       = '_mvm_local_ad_text';
	private const META_CTA        = '_mvm_local_ad_cta';
	private const META_URL        = '_mvm_local_ad_url';
	private const META_IMAGE_ID   = '_mvm_local_ad_image_id';
	private const META_PLACEMENTS = '_mvm_local_ad_placements';
	private const META_START_AT   = '_mvm_local_ad_start_at';
	private const META_END_AT     = '_mvm_local_ad_end_at';
	private const META_STATUS     = '_mvm_local_ad_status';

	private static bool $style_enqueued = false;
	private static bool $inline_rendered = false;

	public static function boot(): void {
		add_action( 'init', array( __CLASS__, 'register_content_type' ), 12 );
		add_action( 'widgets_init', array( __CLASS__, 'register_widget' ) );
		add_shortcode( 'mvm_local_ad', array( __CLASS__, 'shortcode' ) );
		add_filter( 'the_content', array( __CLASS__, 'inject_contextual_ad' ), 35 );
	}

	public static function activate(): void {
		self::register_content_type();
		flush_rewrite_rules( false );
	}

	public static function deactivate(): void {
		flush_rewrite_rules( false );
	}

	public static function register_content_type(): void {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels' => array(
					'name'          => 'Lokale advertenties',
					'singular_name' => 'Lokale advertentie',
				),
				'public'              => false,
				'publicly_queryable'  => false,
				'exclude_from_search' => true,
				'show_ui'             => false,
				'show_in_rest'        => false,
				'supports'            => array( 'title', 'author' ),
				'capability_type'     => 'post',
				'map_meta_cap'        => true,
			)
		);
	}

	public static function register_widget(): void {
		if ( class_exists( 'WP_Widget' ) && class_exists( 'MvM_Local_Business_Ad_Widget' ) ) {
			register_widget( 'MvM_Local_Business_Ad_Widget' );
		}
	}

	public static function placements(): array {
		return array(
			'home_banner'         => 'Homepage banner',
			'home_stream'         => 'Homepage nieuwsstroom',
			'sidebar'             => 'Zijbalk',
			'article_inline'      => 'Nieuwsartikel inline',
			'event_inline'        => 'Evenement inline',
			'encyclopedia_inline' => 'Encyclopedie inline',
			'before_footer'       => 'Voor footer',
		);
	}

	public static function statuses(): array {
		return array( 'draft', 'active', 'paused', 'ended', 'suspended' );
	}

	public static function normalize_placements( $placements ): array {
		$allowed = array_keys( self::placements() );
		$clean   = array_values( array_unique( array_map( 'sanitize_key', (array) $placements ) ) );
		return array_values( array_intersect( $allowed, $clean ) );
	}

	public static function sanitize_url( string $url ): string {
		$url = esc_url_raw( trim( $url ), array( 'http', 'https' ) );
		return $url ?: '';
	}

	public static function set_ad_fields( int $post_id, array $fields ): void {
		$advertiser = mb_substr( sanitize_text_field( (string) ( $fields['advertiser'] ?? '' ) ), 0, 120 );
		$headline   = mb_substr( sanitize_text_field( (string) ( $fields['headline'] ?? '' ) ), 0, 160 );
		$text       = mb_substr( sanitize_textarea_field( (string) ( $fields['text'] ?? '' ) ), 0, 600 );
		$cta        = mb_substr( sanitize_text_field( (string) ( $fields['cta'] ?? 'Bekijk ondernemer' ) ), 0, 60 );
		$url        = self::sanitize_url( (string) ( $fields['url'] ?? '' ) );
		$image_id   = absint( $fields['image_id'] ?? 0 );
		$placements = self::normalize_placements( $fields['placements'] ?? array() );
		$status     = sanitize_key( (string) ( $fields['status'] ?? 'draft' ) );
		$status     = in_array( $status, self::statuses(), true ) ? $status : 'draft';
		$start_at   = self::normalize_timestamp( $fields['start_at'] ?? 0 );
		$end_at     = self::normalize_timestamp( $fields['end_at'] ?? 0 );

		if ( $end_at && $start_at && $end_at <= $start_at ) {
			$end_at = 0;
		}

		update_post_meta( $post_id, self::META_ADVERTISER, $advertiser );
		update_post_meta( $post_id, self::META_HEADLINE, $headline );
		update_post_meta( $post_id, self::META_TEXT, $text );
		update_post_meta( $post_id, self::META_CTA, $cta );
		update_post_meta( $post_id, self::META_URL, $url );
		update_post_meta( $post_id, self::META_IMAGE_ID, $image_id );
		update_post_meta( $post_id, self::META_PLACEMENTS, $placements );
		update_post_meta( $post_id, self::META_START_AT, $start_at );
		update_post_meta( $post_id, self::META_END_AT, $end_at );
		update_post_meta( $post_id, self::META_STATUS, $status );
	}

	private static function normalize_timestamp( $value ): int {
		if ( is_numeric( $value ) ) {
			return max( 0, (int) $value );
		}
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return 0;
		}
		$timestamp = strtotime( $value );
		return false === $timestamp ? 0 : $timestamp;
	}

	public static function ad_data( WP_Post $post ): array {
		return array(
			'id'           => (int) $post->ID,
			'owner_id'     => (int) $post->post_author,
			'advertiser'   => (string) get_post_meta( $post->ID, self::META_ADVERTISER, true ),
			'headline'     => (string) get_post_meta( $post->ID, self::META_HEADLINE, true ),
			'text'         => (string) get_post_meta( $post->ID, self::META_TEXT, true ),
			'cta'          => (string) get_post_meta( $post->ID, self::META_CTA, true ),
			'url'          => (string) get_post_meta( $post->ID, self::META_URL, true ),
			'image_id'     => (int) get_post_meta( $post->ID, self::META_IMAGE_ID, true ),
			'image_url'    => wp_get_attachment_image_url( (int) get_post_meta( $post->ID, self::META_IMAGE_ID, true ), 'medium_large' ) ?: '',
			'placements'   => self::normalize_placements( get_post_meta( $post->ID, self::META_PLACEMENTS, true ) ),
			'start_at'     => (int) get_post_meta( $post->ID, self::META_START_AT, true ),
			'end_at'       => (int) get_post_meta( $post->ID, self::META_END_AT, true ),
			'status'       => (string) get_post_meta( $post->ID, self::META_STATUS, true ),
			'created_at'   => get_post_time( DATE_ATOM, true, $post ),
			'updated_at'   => get_post_modified_time( DATE_ATOM, true, $post ),
		);
	}

	public static function active_ads( string $placement, int $limit = 20 ): array {
		$placement = sanitize_key( $placement );
		if ( ! isset( self::placements()[ $placement ] ) ) {
			return array();
		}

		$posts = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => min( 100, max( 1, $limit ) ),
				'orderby'        => array( 'menu_order' => 'ASC', 'date' => 'DESC' ),
				'no_found_rows'  => true,
			)
		);
		$now = time();
		$eligible = array();
		foreach ( $posts as $post ) {
			if ( ! ( $post instanceof WP_Post ) ) {
				continue;
			}
			$data = self::ad_data( $post );
			if ( 'active' !== $data['status'] || ! in_array( $placement, $data['placements'], true ) ) {
				continue;
			}
			if ( $data['start_at'] > 0 && $data['start_at'] > $now ) {
				continue;
			}
			if ( $data['end_at'] > 0 && $data['end_at'] < $now ) {
				continue;
			}
			if ( '' === $data['advertiser'] || '' === $data['headline'] || '' === $data['url'] ) {
				continue;
			}
			$eligible[] = $post;
		}
		return $eligible;
	}

	public static function choose_ad( string $placement ): ?WP_Post {
		$ads = self::active_ads( $placement );
		if ( ! $ads ) {
			return null;
		}
		$bucket = gmdate( 'Y-m-d-H' );
		$index  = abs( crc32( sanitize_key( $placement ) . '|' . $bucket ) ) % count( $ads );
		return $ads[ $index ] ?? $ads[0];
	}

	public static function render( string $placement, string $variant = 'wide' ): string {
		$post = self::choose_ad( $placement );
		if ( ! $post ) {
			return '';
		}
		self::enqueue_style();
		$data = self::ad_data( $post );
		$variant = in_array( $variant, array( 'wide', 'compact', 'inline' ), true ) ? $variant : 'wide';
		$image = '';
		if ( $data['image_url'] ) {
			$image = '<div class="mvm-local-ad__media"><img src="' . esc_url( $data['image_url'] ) . '" alt="" loading="lazy" decoding="async"></div>';
		}
		$cta = $data['cta'] ?: 'Bekijk ondernemer';
		return '<aside class="mvm-local-ad mvm-local-ad--' . esc_attr( $variant ) . '" data-mvm-local-ad data-placement="' . esc_attr( $placement ) . '" aria-label="Advertentie van lokale ondernemer">'
			. '<div class="mvm-local-ad__label">Advertentie <span aria-hidden="true">·</span> Lokale ondernemer</div>'
			. '<div class="mvm-local-ad__card">'
			. $image
			. '<div class="mvm-local-ad__body">'
			. '<p class="mvm-local-ad__advertiser">' . esc_html( $data['advertiser'] ) . '</p>'
			. '<h2 class="mvm-local-ad__headline">' . esc_html( $data['headline'] ) . '</h2>'
			. ( $data['text'] ? '<p class="mvm-local-ad__text">' . esc_html( $data['text'] ) . '</p>' : '' )
			. '<a class="mvm-local-ad__cta" href="' . esc_url( $data['url'] ) . '" target="_blank" rel="sponsored nofollow noopener noreferrer">' . esc_html( $cta ) . '<span aria-hidden="true"> →</span></a>'
			. '</div></div></aside>';
	}

	public static function shortcode( array $atts = array() ): string {
		$atts = shortcode_atts(
			array(
				'placement' => 'home_stream',
				'variant'   => 'wide',
			),
			$atts,
			'mvm_local_ad'
		);
		return self::render( sanitize_key( (string) $atts['placement'] ), sanitize_key( (string) $atts['variant'] ) );
	}

	public static function inject_contextual_ad( string $content ): string {
		if ( self::$inline_rendered || is_admin() || is_feed() || ! is_singular() || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		$post_type = get_post_type();
		$placement = '';
		$paragraph = 3;
		if ( 'post' === $post_type ) {
			$placement = 'article_inline';
		} elseif ( 'event_listing' === $post_type ) {
			$placement = 'event_inline';
			$paragraph = 2;
		} elseif ( in_array( $post_type, self::encyclopedia_post_types(), true ) ) {
			$placement = 'encyclopedia_inline';
		}
		if ( '' === $placement ) {
			return $content;
		}
		$ad = self::render( $placement, 'inline' );
		if ( '' === $ad ) {
			return $content;
		}
		self::$inline_rendered = true;
		return self::insert_after_paragraph( $content, $ad, $paragraph );
	}

	private static function encyclopedia_post_types(): array {
		return array(
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
	}

	private static function insert_after_paragraph( string $content, string $insert, int $paragraph ): string {
		$closing = '</p>';
		$parts   = explode( $closing, $content );
		if ( count( $parts ) <= 1 ) {
			return $content . $insert;
		}
		$output = '';
		foreach ( $parts as $index => $part ) {
			if ( '' === $part && $index === count( $parts ) - 1 ) {
				continue;
			}
			$output .= $part . $closing;
			if ( ( $index + 1 ) === $paragraph ) {
				$output .= $insert;
			}
		}
		return $output;
	}

	public static function enqueue_style(): void {
		if ( self::$style_enqueued ) {
			return;
		}
		self::$style_enqueued = true;
		$css = MVM_PLATFORM_DIR . 'assets/local-business-ads.css';
		wp_enqueue_style(
			'mvm-local-business-ads',
			MVM_PLATFORM_URL . 'assets/local-business-ads.css',
			array(),
			is_readable( $css ) ? (string) filemtime( $css ) : MVM_PLATFORM_VERSION
		);
	}
}
