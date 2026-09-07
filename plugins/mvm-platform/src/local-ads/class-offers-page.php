<?php

defined( 'ABSPATH' ) || exit;

final class MvM_Offers_Page {
	private const QUERY_VAR = 'mvm_offers_page';
	private static bool $assets_enqueued = false;

	public static function boot(): void {
		add_shortcode( 'mvm_aanbiedingen', array( __CLASS__, 'shortcode' ) );
		add_action( 'init', array( __CLASS__, 'register_route' ) );
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
		add_filter( 'template_include', array( __CLASS__, 'template_include' ), 65 );
	}

	public static function activate(): void {
		self::register_route();
		flush_rewrite_rules( false );
	}

	public static function deactivate(): void {
		flush_rewrite_rules( false );
	}

	public static function register_route(): void {
		$page = get_page_by_path( 'aanbiedingen', OBJECT, 'page' );
		if ( $page instanceof WP_Post && 'trash' !== $page->post_status ) {
			return;
		}
		add_rewrite_rule( '^aanbiedingen/?$', 'index.php?' . self::QUERY_VAR . '=1', 'top' );
	}

	public static function query_vars( array $vars ): array {
		$vars[] = self::QUERY_VAR;
		return $vars;
	}

	public static function is_request(): bool {
		return '1' === (string) get_query_var( self::QUERY_VAR );
	}

	public static function template_include( string $template ): string {
		if ( ! self::is_request() ) {
			return $template;
		}
		$page = MVM_PLATFORM_DIR . 'templates/offers/page.php';
		return is_readable( $page ) ? $page : $template;
	}

	public static function shortcode(): string {
		self::enqueue_assets();
		$offers = self::active_offers();
		ob_start();
		self::render_content( $offers );
		return (string) ob_get_clean();
	}

	public static function render_content( array $offers ): void {
		?>
		<section class="mvm-offers" aria-labelledby="mvm-offers-title">
			<header class="mvm-offers__hero">
				<p class="mvm-offers__eyebrow">Lokale ondernemers</p>
				<h1 id="mvm-offers-title">Aanbiedingen</h1>
				<p>Actuele aanbiedingen en acties van ondernemers uit Mierlo. Dit is commerciële inhoud en staat volledig los van de particuliere Marktplaats.</p>
			</header>

			<section class="mvm-offers__content" aria-labelledby="mvm-offers-list-title">
				<div class="mvm-offers__head">
					<div>
						<p class="mvm-offers__eyebrow">In Mierlo</p>
						<h2 id="mvm-offers-list-title">Actuele aanbiedingen</h2>
					</div>
					<p><?php echo esc_html( count( $offers ) ); ?> actieve aanbieding<?php echo 1 === count( $offers ) ? '' : 'en'; ?></p>
				</div>

				<?php if ( $offers ) : ?>
					<div class="mvm-offers__grid">
						<?php foreach ( $offers as $offer ) : ?>
							<?php
							$data = MvM_Local_Business_Ads::ad_data( $offer );
							$cta  = $data['cta'] ?: 'Bekijk ondernemer';
							?>
							<article class="mvm-offers__card">
								<div class="mvm-offers__label">Advertentie <span aria-hidden="true">·</span> Lokale ondernemer</div>
								<?php if ( $data['image_url'] ) : ?>
									<div class="mvm-offers__media">
										<img src="<?php echo esc_url( $data['image_url'] ); ?>" alt="" loading="lazy" decoding="async">
									</div>
								<?php endif; ?>
								<div class="mvm-offers__body">
									<p class="mvm-offers__advertiser"><?php echo esc_html( $data['advertiser'] ); ?></p>
									<h3><?php echo esc_html( $data['headline'] ); ?></h3>
									<?php if ( $data['text'] ) : ?>
										<p class="mvm-offers__text"><?php echo esc_html( $data['text'] ); ?></p>
									<?php endif; ?>
									<?php if ( $data['end_at'] > time() ) : ?>
										<p class="mvm-offers__until">Geldig t/m <?php echo esc_html( wp_date( 'j F Y', $data['end_at'] ) ); ?></p>
									<?php endif; ?>
									<a class="mvm-offers__cta" href="<?php echo esc_url( $data['url'] ); ?>" target="_blank" rel="sponsored nofollow noopener noreferrer">
										<?php echo esc_html( $cta ); ?> <span aria-hidden="true">→</span>
									</a>
								</div>
							</article>
						<?php endforeach; ?>
					</div>
				<?php else : ?>
					<div class="mvm-offers__empty">
						<h3>Er zijn nu geen actieve aanbiedingen.</h3>
						<p>Zodra lokale ondernemers een actie activeren, verschijnt die hier automatisch.</p>
					</div>
				<?php endif; ?>
			</section>
		</section>
		<?php
	}

	public static function active_offers(): array {
		$posts = get_posts(
			array(
				'post_type'      => MvM_Local_Business_Ads::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 100,
				'orderby'        => array( 'menu_order' => 'ASC', 'date' => 'DESC' ),
				'no_found_rows'  => true,
			)
		);
		$now = time();
		return array_values(
			array_filter(
				$posts,
				static function ( $post ) use ( $now ): bool {
					if ( ! ( $post instanceof WP_Post ) ) {
						return false;
					}
					$data = MvM_Local_Business_Ads::ad_data( $post );
					if ( 'active' !== $data['status'] ) {
						return false;
					}
					if ( $data['start_at'] > 0 && $data['start_at'] > $now ) {
						return false;
					}
					if ( $data['end_at'] > 0 && $data['end_at'] < $now ) {
						return false;
					}
					return '' !== $data['advertiser'] && '' !== $data['headline'] && '' !== $data['url'];
				}
			)
		);
	}

	public static function enqueue_assets(): void {
		if ( self::$assets_enqueued ) {
			return;
		}
		self::$assets_enqueued = true;
		MvM_Local_Business_Ads::enqueue_style();
		$css = MVM_PLATFORM_DIR . 'assets/offers.css';
		wp_enqueue_style(
			'mvm-offers',
			MVM_PLATFORM_URL . 'assets/offers.css',
			array( 'mvm-local-business-ads' ),
			is_readable( $css ) ? (string) filemtime( $css ) : MVM_PLATFORM_VERSION
		);
	}
}
