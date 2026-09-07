<?php

defined( 'ABSPATH' ) || exit;

final class MvM_Newsletter_Frontend {
	private const QUERY_VAR = 'mvm_newsletter_route';
	private static bool $assets_enqueued = false;

	public static function boot(): void {
		add_action( 'init', array( __CLASS__, 'register_routes' ) );
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
		add_filter( 'template_include', array( __CLASS__, 'template_include' ), 60 );
		add_filter( 'wp_robots', array( __CLASS__, 'robots' ) );
		add_shortcode( 'mvm_newsletter_signup', array( __CLASS__, 'signup_shortcode' ) );
	}

	public static function activate(): void {
		self::register_routes();
		flush_rewrite_rules( false );
	}

	public static function deactivate(): void {
		flush_rewrite_rules( false );
	}

	public static function register_routes(): void {
		// During staged coexistence snippet 582 remains the live newsletter owner.
		// Do not let Platform claim /nieuwsbrief/ until that legacy runtime has
		// been deliberately deactivated during the newsletter cutover.
		if ( function_exists( 'mvm_newsletter_table_v1' ) ) {
			return;
		}

		add_rewrite_rule( '^nieuwsbrief/?$', 'index.php?' . self::QUERY_VAR . '=signup', 'top' );
		add_rewrite_rule( '^nieuwsbrief/bevestigen/?$', 'index.php?' . self::QUERY_VAR . '=confirm', 'top' );
		add_rewrite_rule( '^nieuwsbrief/afmelden/?$', 'index.php?' . self::QUERY_VAR . '=unsubscribe', 'top' );
	}

	public static function query_vars( array $vars ): array {
		$vars[] = self::QUERY_VAR;
		return $vars;
	}

	public static function route(): string {
		$route = sanitize_key( (string) get_query_var( self::QUERY_VAR ) );
		return in_array( $route, array( 'signup', 'confirm', 'unsubscribe' ), true ) ? $route : '';
	}

	public static function is_request(): bool {
		return '' !== self::route();
	}

	public static function template_include( string $template ): string {
		if ( ! self::is_request() ) {
			return $template;
		}
		$plugin_template = MVM_PLATFORM_DIR . 'templates/newsletter/page.php';
		return is_readable( $plugin_template ) ? $plugin_template : $template;
	}

	public static function robots( array $robots ): array {
		if ( in_array( self::route(), array( 'confirm', 'unsubscribe' ), true ) ) {
			$robots['noindex']   = true;
			$robots['nofollow']  = true;
			$robots['noarchive'] = true;
		}
		return $robots;
	}

	public static function enqueue_assets(): void {
		if ( self::$assets_enqueued ) {
			return;
		}
		self::$assets_enqueued = true;
		$css = MVM_PLATFORM_DIR . 'assets/newsletter.css';
		$js  = MVM_PLATFORM_DIR . 'assets/newsletter.js';
		wp_enqueue_style(
			'mvm-newsletter',
			MVM_PLATFORM_URL . 'assets/newsletter.css',
			array(),
			is_readable( $css ) ? (string) filemtime( $css ) : MVM_PLATFORM_VERSION
		);
		wp_enqueue_script(
			'mvm-newsletter',
			MVM_PLATFORM_URL . 'assets/newsletter.js',
			array(),
			is_readable( $js ) ? (string) filemtime( $js ) : MVM_PLATFORM_VERSION,
			true
		);
		wp_localize_script(
			'mvm-newsletter',
			'MvMNewsletter',
			array(
				'api'      => esc_url_raw( rest_url( MvM_Platform_REST::NAMESPACE . '/newsletter' ) ),
				'nonce'    => is_user_logged_in() ? wp_create_nonce( 'wp_rest' ) : '',
				'loggedIn' => is_user_logged_in(),
				'route'    => self::route(),
				'topics'   => MvM_Newsletter::topics(),
				'baseUrl'  => esc_url_raw( home_url( '/nieuwsbrief/' ) ),
			)
		);
	}

	public static function signup_shortcode( array $atts = array() ): string {
		$atts = shortcode_atts(
			array(
				'title'       => 'Blijf op de hoogte van Mierlo',
				'description' => 'Ontvang het weekoverzicht met lokaal nieuws en kies zelf welke onderwerpen je daarnaast wilt volgen.',
				'compact'     => '0',
			),
			$atts,
			'mvm_newsletter_signup'
		);

		self::enqueue_assets();
		$compact = in_array( strtolower( (string) $atts['compact'] ), array( '1', 'true', 'yes', 'ja' ), true );
		$title = sanitize_text_field( (string) $atts['title'] );
		$description = sanitize_text_field( (string) $atts['description'] );

		ob_start();
		?>
		<section class="mvm-newsletter mvm-newsletter--embed<?php echo $compact ? ' mvm-newsletter--compact' : ''; ?>" data-mvm-newsletter-app aria-labelledby="mvm-newsletter-signup-title">
			<article class="mvm-newsletter__card mvm-newsletter__signup-card">
				<p class="mvm-newsletter__eyebrow mvm-newsletter__eyebrow--blue">Mierlo voor Mierlo nieuwsbrief</p>
				<h2 id="mvm-newsletter-signup-title"><?php echo esc_html( $title ); ?></h2>
				<?php if ( '' !== $description ) : ?>
					<p><?php echo esc_html( $description ); ?></p>
				<?php endif; ?>
				<?php self::render_signup_form(); ?>
			</article>
		</section>
		<?php
		return (string) ob_get_clean();
	}

	public static function render_signup_form(): void {
		?>
		<form class="mvm-newsletter__form" data-mvm-newsletter-subscribe>
			<label>
				<span>E-mailadres</span>
				<input type="email" name="email" autocomplete="email" inputmode="email" maxlength="320" required aria-describedby="mvm-newsletter-privacy">
			</label>
			<fieldset>
				<legend>Kies je onderwerpen</legend>
				<div class="mvm-newsletter__topics">
					<?php self::render_topic_checkboxes(); ?>
				</div>
			</fieldset>
			<label class="mvm-newsletter__consent">
				<input type="checkbox" name="consent" value="1" required>
				<span>Ja, ik wil de Mierlo voor Mierlo-nieuwsbrief ontvangen. Ik bevestig mijn inschrijving daarna via e-mail en kan mij op ieder moment afmelden.</span>
			</label>
			<p id="mvm-newsletter-privacy" class="mvm-newsletter__privacy">We gebruiken je e-mailadres alleen voor de nieuwsbrief en je gekozen onderwerpen. Geen openbare abonneelijst en geen trackingpixel voor leesgedrag.</p>
			<p class="mvm-newsletter__status" data-mvm-newsletter-subscribe-status role="status" aria-live="polite"></p>
			<button type="submit" class="mvm-newsletter__button">Schrijf mij in</button>
		</form>
		<?php
	}

	public static function render_topic_checkboxes( string $name = 'topics[]' ): void {
		foreach ( MvM_Newsletter::topics() as $key => $label ) {
			?>
			<label class="mvm-newsletter__topic">
				<input type="checkbox" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $key ); ?>" checked>
				<span><?php echo esc_html( $label ); ?></span>
			</label>
			<?php
		}
	}
}
