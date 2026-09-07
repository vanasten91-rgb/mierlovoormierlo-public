<?php

defined( 'ABSPATH' ) || exit;

final class MvM_Ad_Settings_Page {
	private const QUERY_VAR = 'mvm_ad_settings';
	private const PATH      = 'adverteren/instellingen';
	private const NONCE     = 'mvm_ad_settings_save_v1';

	public static function boot(): void {
		add_action( 'init', array( __CLASS__, 'register_rewrite' ), 15 );
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
		add_action( 'template_redirect', array( __CLASS__, 'render_if_requested' ), 1 );
	}

	public static function activate(): void {
		self::register_rewrite();
		flush_rewrite_rules( false );
	}

	public static function deactivate(): void {
		flush_rewrite_rules( false );
	}

	public static function register_rewrite(): void {
		add_rewrite_rule( '^' . preg_quote( self::PATH, '#' ) . '/?$', 'index.php?' . self::QUERY_VAR . '=1', 'top' );
	}

	public static function query_vars( array $vars ): array {
		$vars[] = self::QUERY_VAR;
		return $vars;
	}

	public static function render_if_requested(): void {
		if ( '1' !== (string) get_query_var( self::QUERY_VAR ) ) {
			return;
		}
		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( wp_login_url( home_url( '/' . self::PATH . '/' ) ) );
			exit;
		}
		if ( ! current_user_can( MvM_Platform_Capabilities::LOCAL_ADS_ADMIN ) ) {
			status_header( 403 );
			nocache_headers();
			wp_die( esc_html__( 'Alleen Administrator en SysOp kunnen advertentieplekken beheren.', 'mvm-platform' ), esc_html__( 'Geen toegang', 'mvm-platform' ), array( 'response' => 403 ) );
		}

		$notice = '';
		if ( 'POST' === strtoupper( (string) ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) ) {
			$nonce = isset( $_POST['_mvm_ad_settings_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_mvm_ad_settings_nonce'] ) ) : '';
			if ( ! wp_verify_nonce( $nonce, self::NONCE ) ) {
				status_header( 403 );
				wp_die( esc_html__( 'De beveiligingscontrole is verlopen. Vernieuw de pagina en probeer opnieuw.', 'mvm-platform' ), esc_html__( 'Ongeldige aanvraag', 'mvm-platform' ), array( 'response' => 403 ) );
			}
			$settings = MvM_Homepage_Ad_Slots::update_settings(
				array(
					'top'    => isset( $_POST['top'] ),
					'stream' => isset( $_POST['stream'] ),
					'bottom' => isset( $_POST['bottom'] ),
				)
			);
			do_action( 'mvm_platform_audit_event', 'local_ads', 'homepage_slots_updated', 0, get_current_user_id(), array( 'settings' => $settings ) );
			$notice = 'Instellingen opgeslagen.';
		}

		nocache_headers();
		self::enqueue_style();
		$settings = MvM_Homepage_Ad_Slots::settings();
		$labels   = MvM_Homepage_Ad_Slots::labels();
		get_header();
		?>
		<main class="mvm-ad-settings" aria-labelledby="mvm-ad-settings-title">
			<section class="mvm-ad-settings__hero">
				<p class="mvm-ad-settings__eyebrow">MvM advertentiebeheer</p>
				<h1 id="mvm-ad-settings-title">Voorpagina-advertenties</h1>
				<p>Zet iedere advertentieplek afzonderlijk aan of uit. Een verborgen plek wordt helemaal niet gerenderd en laat dus geen lege ruimte achter.</p>
			</section>
			<?php if ( '' !== $notice ) : ?><p class="mvm-ad-settings__notice" role="status"><?php echo esc_html( $notice ); ?></p><?php endif; ?>
			<form method="post" class="mvm-ad-settings__panel">
				<?php wp_nonce_field( self::NONCE, '_mvm_ad_settings_nonce' ); ?>
				<h2>Zichtbare plekken</h2>
				<?php foreach ( array( 'top', 'stream', 'bottom' ) as $key ) : ?>
					<label class="mvm-ad-settings__toggle">
						<input type="checkbox" name="<?php echo esc_attr( $key ); ?>" value="1" <?php checked( ! empty( $settings[ $key ] ) ); ?>>
						<span><strong><?php echo esc_html( $labels[ $key ] ?? $key ); ?></strong><small><?php echo esc_html( 'stream' === $key ? 'Maximaal twee advertenties naast elkaar op desktop.' : 'Deze zone verdwijnt volledig als hij wordt uitgezet.' ); ?></small></span>
					</label>
				<?php endforeach; ?>
				<div class="mvm-ad-settings__actions">
					<button type="submit">Instellingen opslaan</button>
					<a href="<?php echo esc_url( home_url( '/adverteren/voorbeelden/homepage/' ) ); ?>">Bekijk voorbeeld</a>
					<a href="<?php echo esc_url( home_url( '/hub4/' ) ); ?>">Terug naar Hub</a>
				</div>
			</form>
		</main>
		<?php
		get_footer();
		exit;
	}

	private static function enqueue_style(): void {
		$path = MVM_PLATFORM_DIR . 'assets/ad-settings.css';
		wp_enqueue_style(
			'mvm-ad-settings',
			MVM_PLATFORM_URL . 'assets/ad-settings.css',
			array(),
			is_readable( $path ) ? (string) filemtime( $path ) : MVM_PLATFORM_VERSION
		);
	}
}
