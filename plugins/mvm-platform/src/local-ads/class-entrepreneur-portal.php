<?php

defined( 'ABSPATH' ) || exit;

final class MvM_Entrepreneur_Portal {
	private const QUERY_VAR = 'mvm_entrepreneur_ads_portal';
	private static bool $assets_enqueued = false;

	public static function boot(): void {
		add_action( 'init', array( __CLASS__, 'register_route' ) );
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
		add_filter( 'template_include', array( __CLASS__, 'template_include' ), 70 );
		add_filter( 'wp_robots', array( __CLASS__, 'robots' ) );
	}

	public static function activate(): void {
		self::register_route();
		flush_rewrite_rules( false );
	}

	public static function deactivate(): void {
		flush_rewrite_rules( false );
	}

	public static function register_route(): void {
		add_rewrite_rule( '^ondernemers/advertentiecentrum/?$', 'index.php?' . self::QUERY_VAR . '=1', 'top' );
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
		nocache_headers();
		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( wp_login_url( home_url( '/ondernemers/advertentiecentrum/' ) ) );
			exit;
		}
		if ( ! current_user_can( MvM_Platform_Capabilities::LOCAL_ADS_SELF_MANAGE ) ) {
			status_header( 403 );
			wp_die( esc_html__( 'Dit advertentiecentrum is alleen beschikbaar voor accounts met de rol Ondernemer.', 'mvm-platform' ), esc_html__( 'Geen toegang', 'mvm-platform' ), array( 'response' => 403 ) );
		}
		$portal = MVM_PLATFORM_DIR . 'templates/entrepreneur/ads-center.php';
		return is_readable( $portal ) ? $portal : $template;
	}

	public static function robots( array $robots ): array {
		if ( self::is_request() ) {
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
		$css = MVM_PLATFORM_DIR . 'assets/entrepreneur-ads.css';
		$js  = MVM_PLATFORM_DIR . 'assets/entrepreneur-ads.js';
		wp_enqueue_style(
			'mvm-entrepreneur-ads',
			MVM_PLATFORM_URL . 'assets/entrepreneur-ads.css',
			array(),
			is_readable( $css ) ? (string) filemtime( $css ) : MVM_PLATFORM_VERSION
		);
		wp_enqueue_script(
			'mvm-entrepreneur-ads',
			MVM_PLATFORM_URL . 'assets/entrepreneur-ads.js',
			array(),
			is_readable( $js ) ? (string) filemtime( $js ) : MVM_PLATFORM_VERSION,
			true
		);
		wp_localize_script(
			'mvm-entrepreneur-ads',
			'MvMEntrepreneurAds',
			array(
				'api'        => esc_url_raw( rest_url( MvM_Platform_REST::NAMESPACE . '/entrepreneur/' ) ),
				'nonce'      => wp_create_nonce( 'wp_rest' ),
				'placements' => MvM_Local_Business_Ads::placements(),
			)
		);
	}
}
