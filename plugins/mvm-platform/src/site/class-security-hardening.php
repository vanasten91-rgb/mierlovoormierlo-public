<?php

defined( 'ABSPATH' ) || exit;

final class MvM_Platform_Security_Hardening {
	public static function boot(): void {
		if ( ! defined( 'DISALLOW_FILE_EDIT' ) ) {
			define( 'DISALLOW_FILE_EDIT', true );
		}

		add_filter( 'xmlrpc_methods', array( __CLASS__, 'remove_pingback_methods' ) );
		add_filter( 'wp_headers', array( __CLASS__, 'remove_pingback_header' ) );
		add_filter( 'pings_open', array( __CLASS__, 'disable_frontend_pings' ), 99, 2 );
	}

	public static function remove_pingback_methods( array $methods ): array {
		unset( $methods['pingback.ping'], $methods['pingback.extensions.getPingbacks'] );
		return $methods;
	}

	public static function remove_pingback_header( array $headers ): array {
		unset( $headers['X-Pingback'] );
		return $headers;
	}

	public static function disable_frontend_pings( bool $open, int $post_id ): bool {
		unset( $post_id );
		return is_admin() ? $open : false;
	}
}
