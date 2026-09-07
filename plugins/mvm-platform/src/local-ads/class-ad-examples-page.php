<?php

defined( 'ABSPATH' ) || exit;

final class MvM_Ad_Examples_Page {
	private const PAGE_PATH = 'adverteren/voorbeelden';
	private const HOME_PATH = 'adverteren/voorbeelden/homepage';

	public static function boot(): void {
		add_action( 'init', array( __CLASS__, 'register_rewrites' ), 15 );
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
		add_action( 'template_redirect', array( __CLASS__, 'render_if_requested' ), 1 );
	}

	public static function activate(): void {
		self::register_rewrites();
		flush_rewrite_rules( false );
	}

	public static function deactivate(): void {
		flush_rewrite_rules( false );
	}

	public static function register_rewrites(): void {
		add_rewrite_rule( '^' . preg_quote( self::PAGE_PATH, '#' ) . '/?$', 'index.php?mvm_ad_examples=all', 'top' );
		add_rewrite_rule( '^' . preg_quote( self::HOME_PATH, '#' ) . '/?$', 'index.php?mvm_ad_examples=homepage', 'top' );
	}

	public static function query_vars( array $vars ): array {
		$vars[] = 'mvm_ad_examples';
		return $vars;
	}

	public static function render_if_requested(): void {
		$mode = sanitize_key( (string) get_query_var( 'mvm_ad_examples' ) );
		if ( ! in_array( $mode, array( 'all', 'homepage' ), true ) ) {
			return;
		}

		status_header( 200 );
		nocache_headers();
		self::enqueue_assets();
		get_header();
		?>
		<main class="mvm-ad-examples" aria-labelledby="mvm-ad-examples-title">
			<section class="mvm-ad-examples__hero">
				<p class="mvm-ad-examples__eyebrow">MvM Ondernemers · voorbeelden</p>
				<h1 id="mvm-ad-examples-title"><?php echo esc_html( 'homepage' === $mode ? 'Voorbeeld: advertenties op de voorpagina' : 'Zo kan jouw advertentie eruitzien' ); ?></h1>
				<p>Alle advertenties hieronder zijn fictief en uitsluitend bedoeld om lokale ondernemers de beschikbare MvM-formaten te laten zien.</p>
				<p class="mvm-ad-examples__notice"><strong>Voorbeeldadvertenties.</strong> Dit zijn geen echte aanbiedingen en geen betaalde plaatsingen.</p>
			</section>

			<?php if ( 'homepage' === $mode ) : ?>
				<?php echo self::homepage_demo(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<p class="mvm-ad-examples__back"><a href="<?php echo esc_url( home_url( '/' . self::PAGE_PATH . '/' ) ); ?>">Bekijk ook de andere advertentieformaten →</a></p>
			<?php else : ?>
				<section class="mvm-ad-examples__section" aria-labelledby="mvm-demo-wide">
					<h2 id="mvm-demo-wide">1. Brede banner</h2>
					<p>Geschikt voor brede contentzones en promoties met wat meer tekst.</p>
					<?php echo self::demo_ad( 'wide', 'Fictief · Bakkerij De Dorpsoven', 'Vers uit Mierlo: proef onze weekendbroden', 'Zaterdag en zondag vier ambachtelijke broden voor een vaste kennismakingsprijs.', 'Bekijk voorbeeld', 'demo-bakery' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</section>

				<section class="mvm-ad-examples__section" aria-labelledby="mvm-demo-compact">
					<h2 id="mvm-demo-compact">2. Compacte kaart</h2>
					<p>Voor zijbalken en compacte posities waar de advertentie snel scanbaar moet blijven.</p>
					<div class="mvm-ad-examples__compact-wrap">
						<?php echo self::demo_ad( 'compact', 'Fictief · Fietsen Mierlo', 'Gratis fietscheck bij onderhoud', 'Laat remmen, banden en verlichting controleren tijdens je onderhoudsbeurt.', 'Bekijk voorbeeld', 'demo-bike' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</div>
				</section>

				<section class="mvm-ad-examples__section" aria-labelledby="mvm-demo-inline">
					<h2 id="mvm-demo-inline">3. Inline advertentie</h2>
					<p>Dit formaat kan tussen redactionele content verschijnen en blijft altijd duidelijk als advertentie gemarkeerd.</p>
					<?php echo self::demo_ad( 'inline', 'Fictief · Groen & Goed Mierlo', 'Voorjaarsbeurt voor tuin en terras', 'Een lokale voorbeeldactie voor snoeiwerk, terrasreiniging en voorjaarsklaar maken.', 'Bekijk voorbeeld', 'demo-garden' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</section>

				<section class="mvm-ad-examples__section" aria-labelledby="mvm-demo-home">
					<h2 id="mvm-demo-home">4. Dubbel blok op de voorpagina</h2>
					<p>Twee lokale advertenties naast elkaar op desktop en onder elkaar op mobiel.</p>
					<?php echo self::homepage_demo(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<p><a class="mvm-ad-examples__cta" href="<?php echo esc_url( home_url( '/' . self::HOME_PATH . '/' ) ); ?>">Open dit formaat op een eigen voorbeeldpagina</a></p>
				</section>
			<?php endif; ?>
		</main>
		<?php
		get_footer();
		exit;
	}

	private static function homepage_demo(): string {
		return '<section class="mvm-home-ads mvm-home-ads--demo" aria-label="Voorbeeld van twee ondernemersadvertenties">'
			. '<div class="mvm-home-ads__heading"><span>Lokale ondernemers</span><strong>Voorbeeldweergave</strong></div>'
			. '<div class="mvm-home-ads__grid">'
			. self::demo_ad( 'compact', 'Fictief · Koffiehuis De Brink', 'Koffie + gebak als voorbeeldactie', 'Een fictieve aanbieding om te laten zien hoe een MvM-homepagekaart oogt.', 'Bekijk voorbeeld', 'demo-coffee' )
			. self::demo_ad( 'compact', 'Fictief · Mierlo Bloeit', 'Lokaal boeket van de week', 'Ook dit is een fictieve advertentie en vertegenwoordigt geen echte ondernemer.', 'Bekijk voorbeeld', 'demo-flowers' )
			. '</div></section>';
	}

	private static function demo_ad( string $variant, string $advertiser, string $headline, string $text, string $cta, string $media_class ): string {
		return '<aside class="mvm-local-ad mvm-local-ad--' . esc_attr( $variant ) . ' mvm-local-ad--demo" aria-label="Fictieve voorbeeldadvertentie">'
			. '<div class="mvm-local-ad__label">Voorbeeldadvertentie <span aria-hidden="true">·</span> Lokale ondernemer</div>'
			. '<div class="mvm-local-ad__card">'
			. '<div class="mvm-local-ad__media mvm-local-ad__media--demo ' . esc_attr( $media_class ) . '" aria-hidden="true"><span>MvM</span></div>'
			. '<div class="mvm-local-ad__body">'
			. '<p class="mvm-local-ad__advertiser">' . esc_html( $advertiser ) . '</p>'
			. '<h2 class="mvm-local-ad__headline">' . esc_html( $headline ) . '</h2>'
			. '<p class="mvm-local-ad__text">' . esc_html( $text ) . '</p>'
			. '<span class="mvm-local-ad__cta mvm-local-ad__cta--demo">' . esc_html( $cta ) . '<span aria-hidden="true"> →</span></span>'
			. '</div></div></aside>';
	}

	private static function enqueue_assets(): void {
		MvM_Local_Business_Ads::enqueue_style();
		$css = MVM_PLATFORM_DIR . 'assets/ad-examples.css';
		wp_enqueue_style(
			'mvm-ad-examples',
			MVM_PLATFORM_URL . 'assets/ad-examples.css',
			array( 'mvm-local-business-ads' ),
			is_readable( $css ) ? (string) filemtime( $css ) : MVM_PLATFORM_VERSION
		);
	}
}
