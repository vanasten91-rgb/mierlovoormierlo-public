<?php

defined( 'ABSPATH' ) || exit;

final class MvM_Local_Business_Ads_REST {
	public static function register_routes(): void {
		register_rest_route(
			MvM_Platform_REST::NAMESPACE,
			'/local-ads',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'permission_callback' => array( __CLASS__, 'can_manage' ),
					'callback'            => array( __CLASS__, 'index' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'permission_callback' => array( __CLASS__, 'can_admin' ),
					'callback'            => array( __CLASS__, 'create' ),
				),
			)
		);

		register_rest_route(
			MvM_Platform_REST::NAMESPACE,
			'/local-ads/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'permission_callback' => array( __CLASS__, 'can_manage' ),
					'callback'            => array( __CLASS__, 'get_item' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'permission_callback' => array( __CLASS__, 'can_admin' ),
					'callback'            => array( __CLASS__, 'update' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'permission_callback' => array( __CLASS__, 'can_admin' ),
					'callback'            => array( __CLASS__, 'trash' ),
				),
			)
		);

		register_rest_route(
			MvM_Platform_REST::NAMESPACE,
			'/local-ads/(?P<id>\d+)/moderate',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'permission_callback' => array( __CLASS__, 'can_manage' ),
				'callback'            => array( __CLASS__, 'moderate' ),
			)
		);
	}

	public static function can_manage() {
		return MvM_Platform_REST::require_capability( MvM_Platform_Capabilities::LOCAL_ADS_MANAGE );
	}

	public static function can_admin() {
		return MvM_Platform_REST::require_capability( MvM_Platform_Capabilities::LOCAL_ADS_ADMIN );
	}

	public static function index(): WP_REST_Response {
		$posts = get_posts(
			array(
				'post_type'      => MvM_Local_Business_Ads::POST_TYPE,
				'post_status'    => array( 'publish', 'draft' ),
				'posts_per_page' => 100,
				'orderby'        => array( 'menu_order' => 'ASC', 'date' => 'DESC' ),
			)
		);
		$response = rest_ensure_response(
			array(
				'items'      => array_map( static fn( WP_Post $post ): array => MvM_Local_Business_Ads::ad_data( $post ), $posts ),
				'placements' => MvM_Local_Business_Ads::placements(),
				'can_admin'  => current_user_can( MvM_Platform_Capabilities::LOCAL_ADS_ADMIN ),
			)
		);
		return MvM_Platform_REST::no_store_private( $response );
	}

	public static function get_item( WP_REST_Request $request ) {
		$post = self::get_ad( absint( $request['id'] ) );
		if ( ! $post ) {
			return new WP_Error( 'mvm_local_ad_not_found', 'Advertentie niet gevonden.', array( 'status' => 404 ) );
		}
		return MvM_Platform_REST::no_store_private( rest_ensure_response( MvM_Local_Business_Ads::ad_data( $post ) ) );
	}

	public static function create( WP_REST_Request $request ) {
		$payload = self::payload( $request );
		$valid   = self::validate_admin_payload( $payload );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		$owner = self::resolve_owner( absint( $payload['owner_id'] ?? 0 ) );
		if ( is_wp_error( $owner ) ) {
			return $owner;
		}

		$post_id = wp_insert_post(
			array(
				'post_type'   => MvM_Local_Business_Ads::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => sanitize_text_field( (string) $payload['headline'] ),
				'post_author' => (int) $owner,
			),
			true
		);
		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		MvM_Local_Business_Ads::set_ad_fields( (int) $post_id, $payload );
		self::audit( 'admin_created', (int) $post_id, array( 'owner_id' => (int) $owner ) );
		$response = rest_ensure_response( MvM_Local_Business_Ads::ad_data( get_post( (int) $post_id ) ) );
		$response->set_status( 201 );
		return MvM_Platform_REST::no_store_private( $response );
	}

	public static function update( WP_REST_Request $request ) {
		$post = self::get_ad( absint( $request['id'] ) );
		if ( ! $post ) {
			return new WP_Error( 'mvm_local_ad_not_found', 'Advertentie niet gevonden.', array( 'status' => 404 ) );
		}

		$current = MvM_Local_Business_Ads::ad_data( $post );
		$incoming = self::payload( $request );
		$payload = array_merge( $current, $incoming );
		$valid = self::validate_admin_payload( $payload );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		$owner = (int) $post->post_author;
		if ( array_key_exists( 'owner_id', $incoming ) ) {
			$resolved = self::resolve_owner( absint( $incoming['owner_id'] ) );
			if ( is_wp_error( $resolved ) ) {
				return $resolved;
			}
			$owner = (int) $resolved;
		}

		$updated = wp_update_post(
			array(
				'ID'          => $post->ID,
				'post_title'  => sanitize_text_field( (string) $payload['headline'] ),
				'post_author' => $owner,
			),
			true
		);
		if ( is_wp_error( $updated ) ) {
			return $updated;
		}

		MvM_Local_Business_Ads::set_ad_fields( $post->ID, $payload );
		self::audit( 'admin_updated', $post->ID, array( 'owner_id' => $owner, 'status' => sanitize_key( (string) $payload['status'] ) ) );
		return MvM_Platform_REST::no_store_private( rest_ensure_response( MvM_Local_Business_Ads::ad_data( get_post( $post->ID ) ) ) );
	}

	public static function trash( WP_REST_Request $request ) {
		$post = self::get_ad( absint( $request['id'] ) );
		if ( ! $post ) {
			return new WP_Error( 'mvm_local_ad_not_found', 'Advertentie niet gevonden.', array( 'status' => 404 ) );
		}

		self::audit( 'admin_trashed', $post->ID, array( 'owner_id' => (int) $post->post_author ) );
		if ( ! wp_trash_post( $post->ID ) ) {
			return new WP_Error( 'mvm_local_ad_trash_failed', 'Advertentie kon niet worden verwijderd.', array( 'status' => 500 ) );
		}
		return MvM_Platform_REST::no_store_private( rest_ensure_response( array( 'deleted' => true, 'id' => $post->ID ) ) );
	}

	public static function moderate( WP_REST_Request $request ) {
		$post = self::get_ad( absint( $request['id'] ) );
		if ( ! $post ) {
			return new WP_Error( 'mvm_local_ad_not_found', 'Advertentie niet gevonden.', array( 'status' => 404 ) );
		}

		$action = sanitize_key( (string) $request->get_param( 'moderation_action' ) );
		$reason = self::moderation_reason( (string) $request->get_param( 'reason' ) );
		if ( ! in_array( $action, array( 'suspend', 'restore' ), true ) ) {
			return new WP_Error( 'mvm_local_ad_moderation_action', 'Kies schorsen of herstellen.', array( 'status' => 400 ) );
		}
		if ( strlen( $reason ) < 5 ) {
			return new WP_Error( 'mvm_local_ad_moderation_reason', 'Geef een korte reden van minimaal 5 tekens.', array( 'status' => 400 ) );
		}

		$current = MvM_Local_Business_Ads::ad_data( $post );
		$from    = sanitize_key( (string) ( $current['status'] ?? 'draft' ) );
		if ( 'restore' === $action && 'suspended' !== $from ) {
			return new WP_Error( 'mvm_local_ad_not_suspended', 'Alleen een geschorste advertentie kan worden hersteld.', array( 'status' => 409 ) );
		}

		$to = 'suspend' === $action ? 'suspended' : 'paused';
		$current['status'] = $to;
		MvM_Local_Business_Ads::set_ad_fields( $post->ID, $current );
		self::audit(
			'moderation_' . $action,
			$post->ID,
			array(
				'from_status' => $from,
				'to_status'   => $to,
				'reason'      => $reason,
			)
		);

		$response = rest_ensure_response(
			array(
				'moderated' => true,
				'action'    => $action,
				'item'      => MvM_Local_Business_Ads::ad_data( get_post( $post->ID ) ),
			)
		);
		return MvM_Platform_REST::no_store_private( $response );
	}

	private static function validate_admin_payload( array $payload ) {
		$advertiser = trim( sanitize_text_field( (string) ( $payload['advertiser'] ?? '' ) ) );
		$headline   = trim( sanitize_text_field( (string) ( $payload['headline'] ?? '' ) ) );
		$url        = MvM_Local_Business_Ads::sanitize_url( (string) ( $payload['url'] ?? '' ) );
		$placements = MvM_Local_Business_Ads::normalize_placements( $payload['placements'] ?? array() );
		$status     = sanitize_key( (string) ( $payload['status'] ?? 'draft' ) );
		if ( '' === $advertiser || '' === $headline || '' === $url || empty( $placements ) ) {
			return new WP_Error( 'mvm_local_ad_required', 'Bedrijfsnaam, kop, doel-URL en minimaal één plaatsing zijn verplicht.', array( 'status' => 400 ) );
		}
		if ( ! in_array( $status, MvM_Local_Business_Ads::statuses(), true ) ) {
			return new WP_Error( 'mvm_local_ad_status', 'Ongeldige advertentiestatus.', array( 'status' => 400 ) );
		}
		$image_id = absint( $payload['image_id'] ?? 0 );
		if ( $image_id && ! wp_attachment_is_image( $image_id ) ) {
			return new WP_Error( 'mvm_local_ad_image', 'De gekozen media is geen geldige afbeelding.', array( 'status' => 400 ) );
		}
		return true;
	}

	private static function resolve_owner( int $owner_id ) {
		if ( $owner_id < 1 ) {
			return get_current_user_id();
		}
		$user = get_user_by( 'id', $owner_id );
		if ( ! ( $user instanceof WP_User ) || ! user_can( $user, MvM_Platform_Capabilities::LOCAL_ADS_SELF_MANAGE ) ) {
			return new WP_Error( 'mvm_local_ad_owner', 'Kies een geldig Ondernemer-account als eigenaar.', array( 'status' => 400 ) );
		}
		return $owner_id;
	}

	private static function payload( WP_REST_Request $request ): array {
		$json = $request->get_json_params();
		return is_array( $json ) ? $json : (array) $request->get_body_params();
	}

	private static function moderation_reason( string $reason ): string {
		$reason = trim( sanitize_text_field( $reason ) );
		return function_exists( 'mb_substr' ) ? mb_substr( $reason, 0, 250, 'UTF-8' ) : substr( $reason, 0, 250 );
	}

	private static function get_ad( int $id ): ?WP_Post {
		$post = get_post( $id );
		return $post instanceof WP_Post && MvM_Local_Business_Ads::POST_TYPE === $post->post_type ? $post : null;
	}

	private static function audit( string $event, int $object_id, array $context = array() ): void {
		do_action( 'mvm_platform_audit_event', 'local_ads', $event, $object_id, get_current_user_id(), $context );
	}
}
