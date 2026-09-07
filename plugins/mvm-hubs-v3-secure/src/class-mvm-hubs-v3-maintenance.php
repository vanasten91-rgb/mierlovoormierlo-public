<?php

defined( 'ABSPATH' ) || exit;

final class MvM_Hubs_V3_Maintenance {
	private const ACTIONS = array(
		'sync_hub_capabilities' => array(
			'label' => 'Hub-rechten synchroniseren',
			'impact' => 'Past uitsluitend de beheerde Hubs v3-capabilities toe volgens de vaste rolmatrix.',
		),
		'flush_rewrites' => array(
			'label' => 'Routes vernieuwen',
			'impact' => 'Bouwt WordPress rewrite-regels opnieuw op; inhoud en instellingen blijven behouden.',
		),
	);

	public static function boot(): void {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	public static function register_routes(): void {
		register_rest_route(
			'mvm-hubs/v3',
			'/admin/maintenance',
			array(
				array(
					'methods' => WP_REST_Server::READABLE,
					'permission_callback' => array( __CLASS__, 'can_manage' ),
					'callback' => array( __CLASS__, 'index' ),
				),
				array(
					'methods' => WP_REST_Server::CREATABLE,
					'permission_callback' => array( __CLASS__, 'can_manage' ),
					'callback' => array( __CLASS__, 'run' ),
					'args' => array(
						'action' => array(
							'type' => 'string',
							'required' => true,
							'sanitize_callback' => 'sanitize_key',
						),
					),
				),
			)
		);
	}

	public static function can_manage(): bool {
		return MvM_Hubs_V3_Security::can_access( 'admin' );
	}

	public static function index(): WP_REST_Response {
		$items = array();
		foreach ( self::ACTIONS as $key => $config ) {
			$items[] = array(
				'key' => $key,
				'label' => $config['label'],
				'impact' => $config['impact'],
				'destructive' => false,
			);
		}
		return self::private_response( array( 'actions' => $items ) );
	}

	public static function run( WP_REST_Request $request ) {
		$action = sanitize_key( (string) $request->get_param( 'action' ) );
		if ( ! isset( self::ACTIONS[ $action ] ) ) {
			return new WP_Error( 'mvm_hubs_v3_maintenance_action', 'Deze onderhoudsactie is niet toegestaan.', array( 'status' => 400 ) );
		}
		if ( MvM_Hubs_V3_Security::is_draft_preview() ) {
			return new WP_Error( 'mvm_hubs_v3_preview_write_blocked', 'Previewmodus: onderhoudsacties worden niet uitgevoerd.', array( 'status' => 409 ) );
		}

		$audit_ready = MvM_Hubs_V3_Audit::record(
			'maintenance_authorized',
			array(
				'hub' => 'admin',
				'action' => $action,
				'object_type' => 'maintenance',
				'object_key' => $action,
				'status' => 'authorized',
			)
		);
		if ( ! $audit_ready ) {
			return new WP_Error(
				'mvm_hubs_v3_audit_unavailable',
				'De onderhoudsactie is niet uitgevoerd omdat de beveiligde auditregistratie niet beschikbaar is.',
				array( 'status' => 503 )
			);
		}

		if ( 'sync_hub_capabilities' === $action ) {
			if ( ! MvM_Hubs_V3_Security::sync_capabilities() ) {
				return new WP_Error( 'mvm_hubs_v3_capability_sync_blocked', 'De Hub-rechten zijn niet gewijzigd.', array( 'status' => 409 ) );
			}
		} elseif ( 'flush_rewrites' === $action ) {
			flush_rewrite_rules( false );
		}

		MvM_Hubs_V3_Audit::record(
			'maintenance_run',
			array(
				'hub' => 'admin',
				'action' => $action,
				'object_type' => 'maintenance',
				'object_key' => $action,
				'status' => 'completed',
			)
		);

		return self::private_response(
			array(
				'ok' => true,
				'action' => $action,
				'message' => self::ACTIONS[ $action ]['label'] . ' is uitgevoerd.',
			)
		);
	}

	private static function private_response( array $data ): WP_REST_Response {
		$response = rest_ensure_response( $data );
		$response->header( 'Cache-Control', 'private, no-store, max-age=0, must-revalidate' );
		return $response;
	}
}
