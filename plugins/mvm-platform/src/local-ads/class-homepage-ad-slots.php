<?php

defined( 'ABSPATH' ) || exit;

final class MvM_Homepage_Ad_Slots {
	private const OPTION_KEY = 'mvm_local_ads_homepage_slots_v1';
	private static bool $top_rendered = false;
	private static bool $stream_rendered = false;
	private static bool $bottom_rendered = false;
	private static int $home_post_count = 0;

	public static function boot(): void {
		add_action( 'loop_start', array( __CLASS__, 'render_top' ), 5 );
		add_action( 'the_post', array( __CLASS__, 'maybe_render_stream' ), 20 );
		add_action( 'loop_end', array( __CLASS__, 'render_bottom' ), 30 );
	}

	public static function defaults(): array {
		return array(
			'top'    => true,
			'stream' => true,
			'bottom' => true,
		);
	}

	public static function settings(): array {
		$stored = get_option( self::OPTION_KEY, array() );
		$stored = is_array( $stored ) ? $stored : array();
		$out = self::defaults();
		foreach ( array_keys( $out ) as $key ) {
			if ( array_key_exists( $key, $stored ) ) {
				$out[ $key ] = (bool) $stored[ $key ];
			}
		}
		return $out;
	}

	public static function update_settings( array $incoming ): array {
		$clean = self::defaults();
		foreach ( array_keys( $clean ) as $key ) {
			$clean[ $key ] = ! empty( $incoming[ $key ] );
		}
		update_option( self::OPTION_KEY, $clean, false );
		return $clean;
	}

	public static function labels(): array {
		return array(
			'top'    => 'Voorpagina · boven de nieuwsstroom',
			'stream' => 'Voorpagina · na 2 nieuwsberichten (2 advertenties)',
			'bottom' => 'Voorpagina · onder de nieuwsstroom',
		);
	}

	private static function is_home_loop( $query = null ): bool {
		if ( is_admin() || is_feed() || ! ( is_home() || is_front_page() ) ) {
			return false;
		}
		if ( $query instanceof WP_Query && ! $query->is_main_query() ) {
			return false;
		}
		return is_main_query();
	}

	public static function render_top( WP_Query $query ): void {
		if ( self::$top_rendered || ! self::is_home_loop( $query ) || empty( self::settings()['top'] ) ) {
			return;
		}
		self::$top_rendered = true;
		echo self::render_slot( 'home_banner', 1, 'mvm-home-ads--top', 'Lokale ondernemers' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	public static function maybe_render_stream( WP_Post $post ): void {
		if ( ! self::is_home_loop() || 'post' !== $post->post_type ) {
			return;
		}
		self::$home_post_count++;
		// the_post fires before each article renders; count 3 places the block after article 2.
		if ( self::$stream_rendered || 3 !== self::$home_post_count || empty( self::settings()['stream'] ) ) {
			return;
		}
		self::$stream_rendered = true;
		echo self::render_slot( 'home_stream', 2, 'mvm-home-ads--stream', 'Uitgelicht door lokale ondernemers' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	public static function render_bottom( WP_Query $query ): void {
		if ( self::$bottom_rendered || ! self::is_home_loop( $query ) || empty( self::settings()['bottom'] ) ) {
			return;
		}
		self::$bottom_rendered = true;
		echo self::render_slot( 'before_footer', 2, 'mvm-home-ads--bottom', 'Meer uit Mierlo' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	private static function render_slot( string $placement, int $limit, string $class, string $title ): string {
		$ads = MvM_Local_Business_Ads::active_ads( $placement, 20 );
		if ( ! $ads ) {
			return '';
		}
		$ads = self::rotate_ads( $ads, $placement );
		$ads = array_slice( $ads, 0, max( 1, min( 2, $limit ) ) );
		if ( ! $ads ) {
			return '';
		}
		MvM_Local_Business_Ads::enqueue_style();
		$css = MVM_PLATFORM_DIR . 'assets/homepage-ad-slots.css';
		wp_enqueue_style(
			'mvm-homepage-ad-slots',
			MVM_PLATFORM_URL . 'assets/homepage-ad-slots.css',
			array( 'mvm-local-business-ads' ),
			is_readable( $css ) ? (string) filemtime( $css ) : MVM_PLATFORM_VERSION
		);
		ob_start();
		?>
		<section class="mvm-home-ads <?php echo esc_attr( $class ); ?>" data-mvm-home-ad-slot="<?php echo esc_attr( $placement ); ?>" aria-label="<?php echo esc_attr( $title ); ?>">
			<div class="mvm-home-ads__heading">
				<span><?php echo esc_html( $title ); ?></span>
				<a href="<?php echo esc_url( home_url( '/aanbiedingen/' ) ); ?>">Alle aanbiedingen</a>
			</div>
			<div class="mvm-home-ads__grid<?php echo 1 === count( $ads ) ? ' is-single' : ''; ?>">
				<?php foreach ( $ads as $post ) : ?>
					<?php echo self::render_post( $post ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php endforeach; ?>
			</div>
		</section>
		<?php
		return (string) ob_get_clean();
	}

	private static function rotate_ads( array $ads, string $placement ): array {
		$count = count( $ads );
		if ( $count < 2 ) {
			return $ads;
		}
		$offset = abs( crc32( sanitize_key( $placement ) . '|' . gmdate( 'Y-m-d-H' ) ) ) % $count;
		return array_merge( array_slice( $ads, $offset ), array_slice( $ads, 0, $offset ) );
	}

	private static function render_post( WP_Post $post ): string {
		$data = MvM_Local_Business_Ads::ad_data( $post );
		$image = '';
		if ( ! empty( $data['image_url'] ) ) {
			$image = '<div class="mvm-local-ad__media"><img src="' . esc_url( (string) $data['image_url'] ) . '" alt="" loading="lazy" decoding="async"></div>';
		}
		$cta = ! empty( $data['cta'] ) ? (string) $data['cta'] : 'Bekijk ondernemer';
		return '<aside class="mvm-local-ad mvm-local-ad--compact" data-mvm-local-ad aria-label="Advertentie van lokale ondernemer">'
			. '<div class="mvm-local-ad__label">Advertentie <span aria-hidden="true">·</span> Lokale ondernemer</div>'
			. '<div class="mvm-local-ad__card">' . $image
			. '<div class="mvm-local-ad__body">'
			. '<p class="mvm-local-ad__advertiser">' . esc_html( (string) $data['advertiser'] ) . '</p>'
			. '<h2 class="mvm-local-ad__headline">' . esc_html( (string) $data['headline'] ) . '</h2>'
			. ( ! empty( $data['text'] ) ? '<p class="mvm-local-ad__text">' . esc_html( (string) $data['text'] ) . '</p>' : '' )
			. '<a class="mvm-local-ad__cta" href="' . esc_url( (string) $data['url'] ) . '" target="_blank" rel="sponsored nofollow noopener noreferrer">' . esc_html( $cta ) . '<span aria-hidden="true"> →</span></a>'
			. '</div></div></aside>';
	}
}
