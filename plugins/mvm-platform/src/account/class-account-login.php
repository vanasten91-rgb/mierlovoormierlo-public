<?php

defined( 'ABSPATH' ) || exit;

/**
 * Fully branded public login page backed by WordPress core authentication.
 * Passwords remain inside wp_signon(); MvM only adds a same-origin nonce,
 * fail-closed reCAPTCHA, throttling and a generic response surface.
 */
final class MvM_Account_Login {
	private const PATH = '/inloggen/';
	private const AJAX_ACTION = 'mvm_account_login';
	private const RATE_WINDOW = 15 * MINUTE_IN_SECONDS;
	private const RATE_LIMIT = 5;
	private const RATE_IP_LIMIT = 25;
	private const RESPONSE_FLOOR_SECONDS = 1.0;
	private const RECAPTCHA_FALLBACK_SITE_KEY = '6LfOr5QtAAAAAAboQoTYEiWU1R11LN4qjz6rPMCY';

	public static function boot(): void {
		add_action( 'template_redirect', array( __CLASS__, 'render_login_page' ), -1000 );
		add_action( 'login_init', array( __CLASS__, 'redirect_core_login' ), 1 );
		add_filter( 'login_url', array( __CLASS__, 'filter_login_url' ), 20, 3 );
		add_action( 'wp_ajax_nopriv_' . self::AJAX_ACTION, array( __CLASS__, 'authenticate' ) );
	}

	public static function filter_login_url( string $login_url, string $redirect, bool $force_reauth ): string {
		unset( $login_url );
		$url = home_url( self::PATH );
		if ( '' !== $redirect ) {
			$url = add_query_arg( 'redirect_to', wp_validate_redirect( $redirect, home_url( '/mijn-mierlo/' ) ), $url );
		}
		if ( $force_reauth ) {
			$url = add_query_arg( 'reauth', '1', $url );
		}
		return $url;
	}

	public static function redirect_core_login(): void {
		if ( 'GET' !== strtoupper( (string) ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) ) {
			return;
		}
		$action = isset( $_REQUEST['action'] ) ? sanitize_key( (string) wp_unslash( $_REQUEST['action'] ) ) : 'login'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- navigation only.
		if ( 'login' !== $action || ! empty( $_REQUEST['interim-login'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- navigation only.
			return;
		}

		$redirect = isset( $_GET['redirect_to'] ) ? esc_url_raw( (string) wp_unslash( $_GET['redirect_to'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- navigation only.
		wp_safe_redirect( self::filter_login_url( '', $redirect, ! empty( $_GET['reauth'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- navigation only.
		exit;
	}

	public static function render_login_page(): void {
		if ( ! self::is_login_path() ) {
			return;
		}

		$redirect = isset( $_GET['redirect_to'] ) ? esc_url_raw( (string) wp_unslash( $_GET['redirect_to'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- navigation only.
		$redirect = wp_validate_redirect( $redirect, home_url( '/mijn-mierlo/' ) );
		$style    = MVM_PLATFORM_DIR . 'assets/account-login.css';
		$script   = MVM_PLATFORM_DIR . 'assets/account-login.js';

		status_header( 200 );
		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow, noarchive', true );
		wp_enqueue_style( 'mvm-account-login', MVM_PLATFORM_URL . 'assets/account-login.css', array(), is_readable( $style ) ? (string) filemtime( $style ) : MVM_PLATFORM_VERSION );
		wp_enqueue_script( 'mvm-google-recaptcha', 'https://www.google.com/recaptcha/api.js?render=explicit', array(), null, true );
		wp_script_add_data( 'mvm-google-recaptcha', 'async', true );
		wp_script_add_data( 'mvm-google-recaptcha', 'defer', true );
		wp_enqueue_script( 'mvm-account-login', MVM_PLATFORM_URL . 'assets/account-login.js', array(), is_readable( $script ) ? (string) filemtime( $script ) : MVM_PLATFORM_VERSION, true );
		wp_localize_script(
			'mvm-account-login',
			'MvMAccountLogin',
			array(
				'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
				'nonce'      => wp_create_nonce( self::AJAX_ACTION ),
				'siteKey'    => self::recaptcha_site_key(),
				'redirect'   => $redirect,
				'accountUrl' => home_url( '/mijn-mierlo/' ),
				'loggedIn'   => is_user_logged_in(),
			)
		);

		$template = MVM_PLATFORM_DIR . 'templates/account/login.php';
		if ( is_readable( $template ) ) {
			include $template;
			exit;
		}
	}

	public static function authenticate(): void {
		$started_at = microtime( true );
		if ( ! check_ajax_referer( self::AJAX_ACTION, 'nonce', false ) ) {
			self::deny( $started_at, 403 );
		}

		$identifier = isset( $_POST['login'] ) ? trim( sanitize_text_field( (string) wp_unslash( $_POST['login'] ) ) ) : '';
		if ( ! self::consume_login_allowance( $identifier ) ) {
			self::deny( $started_at, 429 );
		}

		$recaptcha_token = isset( $_POST['recaptcha_token'] ) ? (string) wp_unslash( $_POST['recaptcha_token'] ) : '';
		if ( ! function_exists( 'mvm_hub_password_recaptcha_verify_v12' ) || ! mvm_hub_password_recaptcha_verify_v12( $recaptcha_token ) ) {
			$recaptcha_token = '';
			self::deny( $started_at, 403 );
		}
		$recaptcha_token = '';

		$password = isset( $_POST['password'] ) ? (string) wp_unslash( $_POST['password'] ) : '';
		if ( '' === $identifier || '' === $password ) {
			$password = '';
			self::deny( $started_at, 403 );
		}
		$remember = ! empty( $_POST['remember'] );
		$user     = wp_signon(
			array(
				'user_login'    => $identifier,
				'user_password' => $password,
				'remember'      => $remember,
			),
			is_ssl()
		);
		$password = '';
		if ( is_wp_error( $user ) ) {
			self::deny( $started_at, 403 );
		}

		$redirect = isset( $_POST['redirect_to'] ) ? esc_url_raw( (string) wp_unslash( $_POST['redirect_to'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized as URL, then same-host validated.
		$redirect = wp_validate_redirect( $redirect, home_url( '/mijn-mierlo/' ) );
		delete_transient( self::rate_key( $identifier ) );
		do_action( 'mvm_platform_audit_event', 'account', 'login_succeeded', (int) $user->ID, (int) $user->ID );
		self::wait_for_response_floor( $started_at );
		wp_send_json_success( array( 'redirect' => $redirect ) );
	}

	private static function is_login_path(): bool {
		$request_path = wp_parse_url( wp_unslash( (string) ( $_SERVER['REQUEST_URI'] ?? '' ) ), PHP_URL_PATH );
		$login_path   = wp_parse_url( home_url( self::PATH ), PHP_URL_PATH );
		return is_string( $request_path ) && is_string( $login_path ) && untrailingslashit( $request_path ) === untrailingslashit( $login_path );
	}

	private static function recaptcha_site_key(): string {
		$site_key = trim( (string) get_option( 'elementor_pro_recaptcha_site_key', '' ) );
		if ( '' === $site_key ) {
			$site_key = self::RECAPTCHA_FALLBACK_SITE_KEY;
		}
		return sanitize_text_field( (string) apply_filters( 'mvm_account_login_recaptcha_site_key', $site_key ) );
	}

	private static function consume_login_allowance( string $identifier ): bool {
		$key      = self::rate_key( $identifier );
		$ip_key   = self::rate_key( '' );
		$count    = (int) get_transient( $key );
		$ip_count = (int) get_transient( $ip_key );
		if ( $count >= self::RATE_LIMIT || $ip_count >= self::RATE_IP_LIMIT ) {
			return false;
		}
		set_transient( $key, $count + 1, self::RATE_WINDOW );
		set_transient( $ip_key, $ip_count + 1, self::RATE_WINDOW );
		return true;
	}

	private static function rate_key( string $identifier ): string {
		$ip          = trim( (string) wp_unslash( $_SERVER['REMOTE_ADDR'] ?? 'unknown' ) );
		$fingerprint = hash_hmac( 'sha256', $ip . '|' . strtolower( $identifier ), wp_salt( 'nonce' ) );
		return 'mvm_account_login_' . substr( $fingerprint, 0, 32 );
	}

	private static function deny( float $started_at, int $status ): never {
		do_action( 'mvm_platform_audit_event', 'account', 'login_denied', 0, 0 );
		self::wait_for_response_floor( $started_at );
		wp_send_json_error(
			array( 'message' => 429 === $status ? 'Er zijn te veel inlogpogingen gedaan. Probeer het over 15 minuten opnieuw.' : 'Inloggen is niet gelukt. Controleer je gegevens en probeer opnieuw.' ),
			$status
		);
	}

	private static function wait_for_response_floor( float $started_at ): void {
		$remaining = self::RESPONSE_FLOOR_SECONDS - ( microtime( true ) - $started_at );
		if ( $remaining > 0 ) {
			usleep( (int) min( 1000000, $remaining * 1000000 ) );
		}
	}
}
