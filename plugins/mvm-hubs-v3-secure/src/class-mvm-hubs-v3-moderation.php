<?php

defined( 'ABSPATH' ) || exit;

final class MvM_Hubs_V3_Moderation {
	public static function boot(): void {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	public static function register_routes(): void {
		register_rest_route(
			'mvm-hubs/v3',
			'/moderation/sources',
			array(
				'methods' => WP_REST_Server::READABLE,
				'permission_callback' => static function (): bool {
					return MvM_Hubs_V3_Security::can_moderate();
				},
				'callback' => array( __CLASS__, 'sources' ),
			)
		);
	}

	public static function sources(): WP_REST_Response {
		$plugins = array_values( (array) get_option( 'active_plugins', array() ) );
		$peepso  = self::plugin_prefix_active( $plugins, 'peepso-core-' );
		$wpforo  = in_array( 'wpforo/wpforo.php', $plugins, true );

		$data = array(
			'sources' => array(
				array(
					'key' => 'news_comments',
					'label' => 'Nieuwsreacties',
					'available' => $peepso,
					'provider' => $peepso ? 'peepso' : 'unavailable',
					'detail_mode' => 'on_demand',
					'actions_enabled' => false,
				),
				array(
					'key' => 'forum',
					'label' => 'Forum',
					'available' => $wpforo,
					'provider' => $wpforo ? 'wpforo' : 'unavailable',
					'detail_mode' => 'on_demand',
					'actions_enabled' => false,
				),
				array(
					'key' => 'community',
					'label' => 'Community',
					'available' => $peepso,
					'provider' => $peepso ? 'peepso' : 'unavailable',
					'detail_mode' => 'on_demand',
					'actions_enabled' => false,
				),
			),
			'privacy' => array(
				'content_included' => false,
				'personal_data_included' => false,
				'destructive_actions_enabled' => false,
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
