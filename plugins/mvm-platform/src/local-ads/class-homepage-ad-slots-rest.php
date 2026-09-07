<?php

defined( 'ABSPATH' ) || exit;

final class MvM_Homepage_Ad_Slots_REST {
	public static function register_routes(): void {
		register_rest_route(
			MvM_Platform_REST::NAMESPACE,
			'/local-ads/homepage-slots',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'permission_callback' => array( __CLASS__, 'can_admin' ),
					'callback'            => array( __CLASS__, 'get_settings' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'permission_callback' => array( __CLASS__, 'can_admin' ),
					'callback'            => array( __CLASS__, 'update_settings' ),
				),
			)
		);
	}

	public static function can_admin() {
		return MvM_Platform_REST::require_capability( MvM_Platform_Capabilities::LOCAL_ADS_ADMIN );
	}

	public static function get_settings(): WP_REST_Response {
		return MvM_Platform_REST::no_store_private(
			rest_ensure_response(
				array(
					'settings' => MvM_Homepage_Ad_Slots::settings(),
					'labels'   => MvM_Homepage_Ad_Slots::labels(),
				)
			)
		);
	}

	public static function update_settings( WP_REST_Request $request ): WP_REST_Response {
		$json = $request->get_json_params();
		$payload = is_array( $json ) ? $json : (array) $request->get_body_params();
		$settings = MvM_Homepage_Ad_Slots::update_settings( $payload );
		do_action(
			'mvm_platform_audit_event',
			'local_ads',
			'homepage_slots_updated',
			0,
			get_current_user_id(),
			array( 'settings' => $settings )
		);
		return MvM_Platform_REST::no_store_private(
			rest_ensure_response(
				array(
					'updated'  => true,
					'settings' => $settings,
					'labels'   => MvM_Homepage_Ad_Slots::labels(),
				)
			)
		);
	}
}
