<?php

defined( 'ABSPATH' ) || exit;

final class MvM_Hubs_V3_Home_Settings {
	private const OPTION = 'mvm_home_blocks_v3';
	private const MAX_SLOTS = 4;
	private const LAYOUTS = array( '1x2', '2x2', '3x2', 'lead-4', 'compact-list' );
	private const TYPES = array( 'news', 'events' );

	public static function boot(): void {
		add_action( 'admin_post_mvm_hubs_v3_save_home_blocks', array( __CLASS__, 'save' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	public static function save(): void {
		if ( ! is_user_logged_in() || ! MvM_Hubs_V3_Security::can_access( 'admin' ) ) {
			wp_die( esc_html__( 'Geen toegang tot deze instellingen.', 'mvm-hubs-v3' ), '', array( 'response' => 403 ) );
		}
		if ( MvM_Hubs_V3_Security::is_draft_preview() ) {
			wp_die( esc_html__( 'Previewmodus: deze wijziging wordt niet opgeslagen.', 'mvm-hubs-v3' ), '', array( 'response' => 409 ) );
		}
		check_admin_referer( 'mvm_hubs_v3_save_home_blocks', 'mvm_hubs_v3_nonce' );

		$raw = isset( $_POST['slots'] ) && is_array( $_POST['slots'] ) ? wp_unslash( $_POST['slots'] ) : array();
		$clean = self::sanitize_slots( $raw );

		$audit_ready = MvM_Hubs_V3_Audit::record(
			'home_blocks_update_authorized',
			array(
				'hub' => 'admin',
				'action' => 'update',
				'object_type' => 'option',
				'object_key' => self::OPTION,
				'status' => 'authorized',
			)
		);
		if ( ! $audit_ready ) {
			wp_die( esc_html__( 'De wijziging is niet uitgevoerd omdat de beveiligde auditregistratie niet beschikbaar is.', 'mvm-hubs-v3' ), '', array( 'response' => 503 ) );
		}

		update_option( self::OPTION, $clean, false );
		MvM_Hubs_V3_Audit::record(
			'home_blocks_updated',
			array(
				'hub' => 'admin',
				'action' => 'update',
				'object_type' => 'option',
				'object_key' => self::OPTION,
				'status' => 'completed',
			)
		);

		wp_safe_redirect( add_query_arg( array( 'deel' => 'homeblokken', 'saved' => '1' ), home_url( '/beheer-hub/' ) ) );
		exit;
	}

	public static function register_routes(): void {
		register_rest_route(
			'mvm-hubs/v3',
			'/admin/home-blocks',
			array(
				'methods' => WP_REST_Server::READABLE,
				'permission_callback' => static function (): bool {
					return MvM_Hubs_V3_Security::can_access( 'admin' );
				},
				'callback' => static function (): WP_REST_Response {
					$raw = get_option( self::OPTION, array() );
					$response = rest_ensure_response(
						array(
							'max_slots' => self::MAX_SLOTS,
							'layouts' => self::LAYOUTS,
							'types' => self::TYPES,
							'slots' => self::sanitize_slots( is_array( $raw ) ? $raw : array() ),
						)
					);
					$response->header( 'Cache-Control', 'private, no-store, max-age=0, must-revalidate' );
					return $response;
				},
			)
		);
	}

	private static function sanitize_slots( array $raw ): array {
		$clean = array();
		foreach ( array_slice( $raw, 0, self::MAX_SLOTS, true ) as $slot ) {
			if ( ! is_array( $slot ) ) {
				continue;
			}
			$type = isset( $slot['type'] ) ? sanitize_key( (string) $slot['type'] ) : 'news';
			$layout = isset( $slot['layout'] ) ? sanitize_key( (string) $slot['layout'] ) : '3x2';
			$clean[] = array(
				'enabled' => ! empty( $slot['enabled'] ) ? 1 : 0,
				'type' => in_array( $type, self::TYPES, true ) ? $type : 'news',
				'layout' => in_array( $layout, self::LAYOUTS, true ) ? $layout : '3x2',
				'title' => isset( $slot['title'] ) ? sanitize_text_field( (string) $slot['title'] ) : '',
				'category' => isset( $slot['category'] ) ? sanitize_title( (string) $slot['category'] ) : '',
				'limit' => isset( $slot['limit'] ) ? max( 1, min( 12, absint( $slot['limit'] ) ) ) : 6,
			);
		}
		return $clean;
	}
}
