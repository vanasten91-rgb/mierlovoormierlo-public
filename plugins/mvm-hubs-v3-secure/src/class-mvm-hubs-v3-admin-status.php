<?php

defined( 'ABSPATH' ) || exit;

final class MvM_Hubs_V3_Admin_Status {
	public static function boot(): void {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	public static function register_routes(): void {
		register_rest_route(
			'mvm-hubs/v3',
			'/admin/status',
			array(
				'methods' => WP_REST_Server::READABLE,
				'permission_callback' => static function (): bool {
					return MvM_Hubs_V3_Security::can_access( 'admin' );
				},
				'callback' => array( __CLASS__, 'status' ),
			)
		);
	}

	public static function status(): WP_REST_Response {
		global $wp_version;
		$theme = wp_get_theme();
		$active_plugins = array_values( (array) get_option( 'active_plugins', array() ) );
		$themes = wp_get_themes();

		$data = array(
			'wordpress' => (string) $wp_version,
			'php' => PHP_VERSION,
			'theme' => array(
				'name' => $theme->get( 'Name' ),
				'version' => $theme->get( 'Version' ),
				'stylesheet' => $theme->get_stylesheet(),
			),
			'hubs_secure' => MVM_HUBS_V3_SECURE_VERSION,
			'capabilities' => MvM_Hubs_V3_Security::capability_health(),
			'integrations' => array(
				'peepso' => self::plugin_prefix_active( $active_plugins, 'peepso-core-' ),
				'wpforo' => in_array( 'wpforo/wpforo.php', $active_plugins, true ),
				'wp_event_manager' => in_array( 'wp-event-manager/wp-event-manager.php', $active_plugins, true ),
				'mvm_platform' => self::plugin_prefix_active( $active_plugins, 'mvm-platform/' ) || self::plugin_prefix_active( $active_plugins, 'mvm-hub4-rc-direct/' ),
				'backuply' => in_array( 'backuply/backuply.php', $active_plugins, true ),
			),
			'protection' => array(
				'theme_policy_saved' => false !== get_option( 'mvm_theme_change_policy_v1', false ),
				'theme_snapshot_present' => isset( $themes['newsup-pro-child-wpvibe-backup-wpvibe-backup'] ),
			),
		);

		$response = rest_ensure_response( $data );
		$response->header( 'Cache-Control', 'private, no-store, max-age=0, must-revalidate' );
		return $response;
	}

	private static function plugin_prefix_active( array $plugins, string $prefix ): bool {
		foreach ( $plugins as $plugin ) {
			if ( str_starts_with( (string) $plugin, $prefix ) ) {
				return true;
			}
		}
		return false;
	}
}
