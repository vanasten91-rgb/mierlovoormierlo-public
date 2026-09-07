<?php

defined( 'ABSPATH' ) || exit;

final class MvM_Newsletter {
	public const DB_VERSION        = '1';
	public const DB_VERSION_OPTION = 'mvm_newsletter_db_version';
	public const CRON_HOOK         = 'mvm_newsletter_dispatch_queue';
	public const CONSENT_VERSION   = '2026-08-v1';

	public static function boot(): void {
		add_filter( 'cron_schedules', array( __CLASS__, 'cron_schedules' ) );
		add_filter( 'rest_pre_dispatch', array( __CLASS__, 'enforce_subscribe_consent' ), 10, 3 );
		add_action( self::CRON_HOOK, array( 'MvM_Newsletter_Delivery', 'dispatch' ) );
		if ( self::DB_VERSION !== (string) get_option( self::DB_VERSION_OPTION, '' ) ) {
			self::install_tables();
		}
		self::ensure_cron();
	}

	public static function activate(): void {
		self::install_tables();
		self::ensure_cron();
	}

	public static function deactivate(): void {
		wp_clear_scheduled_hook( self::CRON_HOOK );
	}

	public static function cron_schedules( array $schedules ): array {
		if ( ! isset( $schedules['mvm_five_minutes'] ) ) {
			$schedules['mvm_five_minutes'] = array(
				'interval' => 5 * MINUTE_IN_SECONDS,
				'display'  => 'Elke vijf minuten (MvM)',
			);
		}
		return $schedules;
	}

	private static function ensure_cron(): void {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + MINUTE_IN_SECONDS, 'mvm_five_minutes', self::CRON_HOOK );
		}
	}

	/**
	 * Public subscription is intentionally available without a WordPress login,
	 * but explicit consent remains mandatory server-side. This filter runs
	 * before the route callback, so direct API callers cannot bypass the UI
	 * checkbox.
	 *
	 * @param mixed $result  Pre-dispatch result.
	 * @param mixed $server  REST server instance.
	 * @param mixed $request REST request.
	 * @return mixed
	 */
	public static function enforce_subscribe_consent( $result, $server, $request ) {
		unset( $server );
		if ( null !== $result || ! is_object( $request ) || ! method_exists( $request, 'get_route' ) || ! method_exists( $request, 'get_method' ) ) {
			return $result;
		}
		if ( '/' . MvM_Platform_REST::NAMESPACE . '/newsletter/subscribe' !== (string) $request->get_route() || 'POST' !== strtoupper( (string) $request->get_method() ) ) {
			return $result;
		}
		$consent = $request->get_param( 'consent' );
		if ( true === $consent || 1 === $consent || '1' === $consent || 'true' === strtolower( (string) $consent ) ) {
			return $result;
		}
		return new WP_Error(
			'mvm_newsletter_consent_required',
			'Geef expliciet toestemming om de nieuwsbriefinschrijving aan te vragen.',
			array( 'status' => 400 )
		);
	}

	public static function table( string $which ): string {
		global $wpdb;
		$map = array(
			'subscribers' => $wpdb->prefix . 'mvm_newsletter_subscribers',
			'campaigns'   => $wpdb->prefix . 'mvm_newsletter_campaigns',
			'queue'       => $wpdb->prefix . 'mvm_newsletter_queue',
		);
		return $map[ $which ] ?? '';
	}

	public static function install_tables(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();
		$subscribers = self::table( 'subscribers' );
		$campaigns   = self::table( 'campaigns' );
		$queue       = self::table( 'queue' );

		dbDelta(
			"CREATE TABLE {$subscribers} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				email varchar(320) NOT NULL,
				email_hash char(64) NOT NULL,
				status varchar(20) NOT NULL DEFAULT 'pending',
				topics text NOT NULL,
				consent_at datetime NULL,
				consent_source varchar(100) NOT NULL DEFAULT '',
				consent_version varchar(32) NOT NULL DEFAULT '',
				confirm_token_hash char(64) NOT NULL DEFAULT '',
				confirm_expires datetime NULL,
				created_at datetime NOT NULL,
				updated_at datetime NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY email_hash (email_hash),
				KEY status (status)
			) {$charset};"
		);

		dbDelta(
			"CREATE TABLE {$campaigns} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				subject varchar(255) NOT NULL,
				preheader varchar(255) NOT NULL DEFAULT '',
				body longtext NOT NULL,
				status varchar(20) NOT NULL DEFAULT 'draft',
				topics text NOT NULL,
				created_by bigint(20) unsigned NOT NULL DEFAULT 0,
				scheduled_at datetime NULL,
				sent_at datetime NULL,
				total_queued int(10) unsigned NOT NULL DEFAULT 0,
				total_sent int(10) unsigned NOT NULL DEFAULT 0,
				total_failed int(10) unsigned NOT NULL DEFAULT 0,
				created_at datetime NOT NULL,
				updated_at datetime NOT NULL,
				PRIMARY KEY  (id),
				KEY status_schedule (status,scheduled_at)
			) {$charset};"
		);

		dbDelta(
			"CREATE TABLE {$queue} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				campaign_id bigint(20) unsigned NOT NULL,
				subscriber_id bigint(20) unsigned NOT NULL,
				status varchar(20) NOT NULL DEFAULT 'pending',
				attempts smallint(5) unsigned NOT NULL DEFAULT 0,
				available_at datetime NOT NULL,
				sent_at datetime NULL,
				last_error text NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY campaign_subscriber (campaign_id,subscriber_id),
				KEY dispatch (status,available_at),
				KEY campaign (campaign_id)
			) {$charset};"
		);

		update_option( self::DB_VERSION_OPTION, self::DB_VERSION, false );
	}

	public static function topics(): array {
		return array(
			'news'         => 'Laatste nieuws',
			'events'       => 'Evenementen',
			'sport'        => 'Sport',
			'associations' => 'Verenigingen',
			'politics'     => 'Politiek',
			'culture'      => 'Cultuur',
			'marketplace'  => 'Marktplaats',
		);
	}

	public static function normalize_topics( $topics ): array {
		$allowed = array_keys( self::topics() );
		$topics  = array_values( array_unique( array_map( 'sanitize_key', (array) $topics ) ) );
		return array_values( array_intersect( $allowed, $topics ) );
	}

	public static function normalize_email( string $email ): string {
		return strtolower( trim( sanitize_email( $email ) ) );
	}

	public static function email_hash( string $email ): string {
		return hash_hmac( 'sha256', self::normalize_email( $email ), wp_salt( 'auth' ) . '|mvm-newsletter-email' );
	}

	public static function token_hash( string $token, string $scope ): string {
		return hash_hmac( 'sha256', trim( $token ), wp_salt( 'nonce' ) . '|mvm-newsletter|' . sanitize_key( $scope ) );
	}

	public static function generate_confirmation_token(): string {
		return wp_generate_password( 48, false, false );
	}

	public static function unsubscribe_token( object $subscriber ): string {
		$id = absint( $subscriber->id ?? 0 );
		$email_hash = (string) ( $subscriber->email_hash ?? '' );
		$consent_at = (string) ( $subscriber->consent_at ?? '' );
		$encoded = rtrim( strtr( base64_encode( (string) $id ), '+/', '-_' ), '=' );
		$signature = hash_hmac( 'sha256', $id . '|' . $email_hash . '|' . $consent_at, wp_salt( 'auth' ) . '|mvm-newsletter-unsubscribe' );
		return $encoded . '.' . $signature;
	}

	public static function subscriber_from_unsubscribe_token( string $token ) {
		global $wpdb;
		$parts = explode( '.', trim( $token ), 2 );
		if ( 2 !== count( $parts ) || ! preg_match( '/^[A-Za-z0-9_-]{1,32}$/', $parts[0] ) || ! preg_match( '/^[a-f0-9]{64}$/', $parts[1] ) ) {
			return null;
		}
		$padding = strlen( $parts[0] ) % 4;
		$encoded = strtr( $parts[0], '-_', '+/' );
		if ( $padding ) {
			$encoded .= str_repeat( '=', 4 - $padding );
		}
		$decoded = base64_decode( $encoded, true );
		$id = is_string( $decoded ) && ctype_digit( $decoded ) ? absint( $decoded ) : 0;
		if ( $id < 1 ) {
			return null;
		}
		$subscriber = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table( 'subscribers' ) . ' WHERE id = %d LIMIT 1', $id ) );
		if ( ! $subscriber ) {
			return null;
		}
		$expected = hash_hmac(
			'sha256',
			$id . '|' . (string) $subscriber->email_hash . '|' . (string) $subscriber->consent_at,
			wp_salt( 'auth' ) . '|mvm-newsletter-unsubscribe'
		);
		return hash_equals( $expected, $parts[1] ) ? $subscriber : null;
	}

	public static function subscriber_by_email( string $email ) {
		global $wpdb;
		$hash = self::email_hash( $email );
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table( 'subscribers' ) . ' WHERE email_hash = %s LIMIT 1', $hash ) );
	}

	public static function decode_topics( $value ): array {
		$decoded = json_decode( (string) $value, true );
		return self::normalize_topics( is_array( $decoded ) ? $decoded : array() );
	}

	public static function now_mysql(): string {
		return current_time( 'mysql', true );
	}
}
