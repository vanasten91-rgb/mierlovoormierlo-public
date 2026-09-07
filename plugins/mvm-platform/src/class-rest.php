<?php

defined( 'ABSPATH' ) || exit;

final class MvM_Platform_REST {
	public const NAMESPACE = 'mvm/v1';

	public static function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/platform',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'permission_callback' => '__return_true',
				'callback'            => array( __CLASS__, 'platform_status' ),
			)
		);
	}

	public static function platform_status(): WP_REST_Response {
		return rest_ensure_response(
			array(
				'name'       => 'MvM Platform',
				'version'    => MVM_PLATFORM_VERSION,
				'api'        => self::NAMESPACE,
				'marketplace'=> false,
				'pwa'        => false,
				'newsletter' => false,
			)
		);
	}

	/**
	 * Cookie-authenticated REST requests must carry the normal WordPress REST
	 * nonce. Core validates that nonce before permission callbacks and clears
	 * the current user when validation fails.
	 *
	 * @return true|WP_Error
	 */
	public static function require_authenticated() {
		if ( is_user_logged_in() && get_current_user_id() > 0 ) {
			return true;
		}

		return new WP_Error(
			'mvm_rest_authentication_required',
			'Je moet ingelogd zijn om deze actie uit te voeren.',
			array( 'status' => 401 )
		);
	}

	/**
	 * @return true|WP_Error
	 */
	public static function require_capability( string $capability ) {
		$authenticated = self::require_authenticated();
		if ( is_wp_error( $authenticated ) ) {
			return $authenticated;
		}

		if ( current_user_can( $capability ) ) {
			return true;
		}

		return new WP_Error(
			'mvm_rest_forbidden',
			'Je hebt geen toestemming voor deze actie.',
			array( 'status' => 403 )
		);
	}

	public static function no_store_private( WP_REST_Response $response ): WP_REST_Response {
		$response->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0' );
		$response->header( 'Pragma', 'no-cache' );
		return $response;
	}
}
