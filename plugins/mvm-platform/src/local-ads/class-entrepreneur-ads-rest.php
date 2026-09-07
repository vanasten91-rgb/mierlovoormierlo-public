<?php

defined( 'ABSPATH' ) || exit;

final class MvM_Entrepreneur_Ads_REST {
	private const MAX_OPEN_REVIEW_ITEMS = 5;
	private const CREATE_RATE_LIMIT_SECONDS = 30;

	public static function register_routes(): void {
		register_rest_route(
			MvM_Platform_REST::NAMESPACE,
			'/entrepreneur/ads',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'permission_callback' => array( __CLASS__, 'can_self_manage' ),
					'callback'            => array( __CLASS__, 'index' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'permission_callback' => array( __CLASS__, 'can_self_manage' ),
					'callback'            => array( __CLASS__, 'create' ),
				),
			)
		);

		register_rest_route(
			MvM_Platform_REST::NAMESPACE,
			'/entrepreneur/ads/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'permission_callback' => array( __CLASS__, 'can_edit_own' ),
					'callback'            => array( __CLASS__, 'update' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'permission_callback' => array( __CLASS__, 'can_edit_own' ),
					'callback'            => array( __CLASS__, 'trash' ),
				),
			)
		);

		register_rest_route(
			MvM_Platform_REST::NAMESPACE,
			'/entrepreneur/media',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'permission_callback' => array( __CLASS__, 'can_self_manage' ),
				'callback'            => array( __CLASS__, 'upload_media' ),
			)
		);
	}

	public static function can_self_manage() {
		return MvM_Platform_REST::require_capability( MvM_Platform_Capabilities::LOCAL_ADS_SELF_MANAGE );
	}

	public static function can_edit_own( WP_REST_Request $request ) {
		$allowed = self::can_self_manage();
		if ( is_wp_error( $allowed ) ) {
			return $allowed;
		}
		$post = self::get_own_ad( absint( $request['id'] ) );
		if ( ! $post ) {
			return new WP_Error( 'mvm_entrepreneur_ad_forbidden', 'Je kunt alleen je eigen advertenties beheren.', array( 'status' => 403 ) );
		}
		return true;
	}

	public static function index(): WP_REST_Response {
		$posts = get_posts(
			array(
				'post_type'      => MvM_Local_Business_Ads::POST_TYPE,
				'post_status'    => array( 'publish', 'draft' ),
				'author'         => get_current_user_id(),
				'posts_per_page' => 100,
				'orderby'        => array( 'date' => 'DESC' ),
			)
		);
		$response = rest_ensure_response(
			array(
				'items'      => array_map( static fn( WP_Post $post ): array => MvM_Local_Business_Ads::ad_data( $post ), $posts ),
				'placements' => MvM_Local_Business_Ads::placements(),
			)
		);
		return MvM_Platform_REST::no_store_private( $response );
	}

	public static function create( WP_REST_Request $request ) {
		$payload = self::payload( $request );
		$valid   = self::validate_payload( $payload );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}
		$guard = self::create_guard();
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}
		$audit_gate = self::audit_gate( 'create_for_review', 0, 'draft' );
		if ( is_wp_error( $audit_gate ) ) {
			return $audit_gate;
		}

		$post_id = wp_insert_post(
			array(
				'post_type'   => MvM_Local_Business_Ads::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => sanitize_text_field( (string) $payload['headline'] ),
				'post_author' => get_current_user_id(),
			),
			true
		);
		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		$payload['status'] = 'draft';
		MvM_Local_Business_Ads::set_ad_fields( (int) $post_id, $payload );
		set_transient( self::rate_limit_key(), 1, self::CREATE_RATE_LIMIT_SECONDS );
		self::audit( 'self_created_for_review', (int) $post_id, array( 'status' => $payload['status'] ) );
		$response = rest_ensure_response( MvM_Local_Business_Ads::ad_data( get_post( (int) $post_id ) ) );
		$response->set_status( 201 );
		return MvM_Platform_REST::no_store_private( $response );
	}

	public static function update( WP_REST_Request $request ) {
		$post = self::get_own_ad( absint( $request['id'] ) );
		if ( ! $post ) {
			return new WP_Error( 'mvm_entrepreneur_ad_forbidden', 'Je kunt alleen je eigen advertenties beheren.', array( 'status' => 403 ) );
		}
		$current = MvM_Local_Business_Ads::ad_data( $post );
		if ( 'suspended' === (string) $current['status'] ) {
			return new WP_Error( 'mvm_entrepreneur_ad_suspended', 'Deze advertentie is door MvM-staf geschorst en kan niet door het ondernemersaccount worden gewijzigd of opnieuw geactiveerd.', array( 'status' => 409 ) );
		}
		$payload = array_merge( $current, self::payload( $request ) );
		$valid   = self::validate_payload( $payload );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}
		$payload['status'] = self::self_status( (string) ( $payload['status'] ?? 'draft' ) );
		if ( 'draft' === $payload['status'] && 'draft' !== (string) ( $current['status'] ?? '' ) ) {
			$capacity = self::review_capacity_guard( $post->ID );
			if ( is_wp_error( $capacity ) ) {
				return $capacity;
			}
		}
		$audit_gate = self::audit_gate( 'update', $post->ID, $payload['status'] );
		if ( is_wp_error( $audit_gate ) ) {
			return $audit_gate;
		}
		$updated = wp_update_post( array( 'ID' => $post->ID, 'post_title' => sanitize_text_field( (string) $payload['headline'] ) ), true );
		if ( is_wp_error( $updated ) ) {
			return $updated;
		}
		MvM_Local_Business_Ads::set_ad_fields( $post->ID, $payload );
		self::audit( 'self_updated_for_review', $post->ID, array( 'status' => $payload['status'] ) );
		return MvM_Platform_REST::no_store_private( rest_ensure_response( MvM_Local_Business_Ads::ad_data( get_post( $post->ID ) ) ) );
	}

	public static function trash( WP_REST_Request $request ) {
		$post = self::get_own_ad( absint( $request['id'] ) );
		if ( ! $post ) {
			return new WP_Error( 'mvm_entrepreneur_ad_forbidden', 'Je kunt alleen je eigen advertenties beheren.', array( 'status' => 403 ) );
		}
		$audit_gate = self::audit_gate( 'trash', $post->ID, 'trash' );
		if ( is_wp_error( $audit_gate ) ) {
			return $audit_gate;
		}
		if ( ! wp_trash_post( $post->ID ) ) {
			return new WP_Error( 'mvm_entrepreneur_ad_trash_failed', 'Advertentie kon niet worden verwijderd.', array( 'status' => 500 ) );
		}
		self::audit( 'self_trashed', $post->ID, array( 'status' => 'trash' ) );
		return MvM_Platform_REST::no_store_private( rest_ensure_response( array( 'deleted' => true, 'id' => $post->ID ) ) );
	}

	public static function upload_media( WP_REST_Request $request ) {
		$files = $request->get_file_params();
		$file  = $files['file'] ?? null;
		if ( ! is_array( $file ) || empty( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
			return new WP_Error( 'mvm_entrepreneur_media_missing', 'Geen geldige afbeelding ontvangen.', array( 'status' => 400 ) );
		}
		if ( (int) ( $file['error'] ?? UPLOAD_ERR_OK ) !== UPLOAD_ERR_OK || (int) ( $file['size'] ?? 0 ) < 1 || (int) $file['size'] > 8 * MB_IN_BYTES ) {
			return new WP_Error( 'mvm_entrepreneur_media_size', 'De afbeelding is ongeldig of groter dan 8 MB.', array( 'status' => 400 ) );
		}
		$checked = wp_check_filetype_and_ext( $file['tmp_name'], (string) $file['name'] );
		if ( empty( $checked['type'] ) || ! in_array( $checked['type'], array( 'image/jpeg', 'image/png', 'image/webp' ), true ) ) {
			return new WP_Error( 'mvm_entrepreneur_media_type', 'Gebruik een JPEG-, PNG- of WebP-afbeelding.', array( 'status' => 400 ) );
		}
		$audit_gate = self::audit_gate( 'media_upload', 0, 'authorized' );
		if ( is_wp_error( $audit_gate ) ) {
			return $audit_gate;
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$attachment_id = media_handle_sideload( $file, 0, 'MvM ondernemersadvertentie' );
		if ( is_wp_error( $attachment_id ) ) {
			return $attachment_id;
		}
		wp_update_post( array( 'ID' => (int) $attachment_id, 'post_author' => get_current_user_id() ) );
		self::audit( 'self_media_uploaded', (int) $attachment_id, array( 'status' => 'completed' ) );
		$response = rest_ensure_response(
			array(
				'id'  => (int) $attachment_id,
				'url' => wp_get_attachment_image_url( (int) $attachment_id, 'medium_large' ) ?: '',
			)
		);
		$response->set_status( 201 );
		return MvM_Platform_REST::no_store_private( $response );
	}

	private static function validate_payload( array $payload ) {
		$advertiser = trim( sanitize_text_field( (string) ( $payload['advertiser'] ?? '' ) ) );
		$headline   = trim( sanitize_text_field( (string) ( $payload['headline'] ?? '' ) ) );
		$url        = MvM_Local_Business_Ads::sanitize_url( (string) ( $payload['url'] ?? '' ) );
		$placements = MvM_Local_Business_Ads::normalize_placements( $payload['placements'] ?? array() );
		if ( '' === $advertiser || '' === $headline || '' === $url || empty( $placements ) ) {
			return new WP_Error( 'mvm_entrepreneur_ad_required', 'Bedrijfsnaam, kop, doel-URL en minimaal één plaatsing zijn verplicht.', array( 'status' => 400 ) );
		}

		$start_at = self::parse_timestamp( $payload['start_at'] ?? 0 );
		$end_at   = self::parse_timestamp( $payload['end_at'] ?? 0 );
		if ( false === $start_at || false === $end_at ) {
			return new WP_Error( 'mvm_entrepreneur_ad_date', 'Gebruik een geldige start- en einddatum.', array( 'status' => 400 ) );
		}
		if ( $start_at > 0 && $end_at > 0 && $end_at <= $start_at ) {
			return new WP_Error( 'mvm_entrepreneur_ad_date_order', 'De einddatum moet na de startdatum liggen.', array( 'status' => 400 ) );
		}

		$image_id = absint( $payload['image_id'] ?? 0 );
		if ( $image_id ) {
			if ( ! wp_attachment_is_image( $image_id ) ) {
				return new WP_Error( 'mvm_entrepreneur_ad_image', 'De gekozen media is geen geldige afbeelding.', array( 'status' => 400 ) );
			}
			$attachment = get_post( $image_id );
			if ( ! ( $attachment instanceof WP_Post ) || (int) $attachment->post_author !== get_current_user_id() ) {
				return new WP_Error( 'mvm_entrepreneur_ad_image_owner', 'Je kunt alleen eigen geüploade afbeeldingen gebruiken.', array( 'status' => 403 ) );
			}
		}
		return true;
	}

	private static function self_status( string $status ): string {
		$status = sanitize_key( $status );
		return in_array( $status, array( 'draft', 'paused', 'ended' ), true ) ? $status : 'draft';
	}

	private static function create_guard() {
		if ( get_transient( self::rate_limit_key() ) ) {
			return new WP_Error( 'mvm_entrepreneur_ad_rate_limited', 'Wacht even voordat je nog een advertentieaanvraag aanmaakt.', array( 'status' => 429 ) );
		}
		return self::review_capacity_guard();
	}

	private static function review_capacity_guard( int $exclude_post_id = 0 ) {
		$args = array(
			'post_type'      => MvM_Local_Business_Ads::POST_TYPE,
			'post_status'    => array( 'publish', 'draft' ),
			'author'         => get_current_user_id(),
			'posts_per_page' => self::MAX_OPEN_REVIEW_ITEMS,
			'no_found_rows'  => true,
			'fields'         => 'ids',
			'meta_key'       => '_mvm_local_ad_status',
			'meta_value'     => 'draft',
		);
		if ( $exclude_post_id > 0 ) {
			$args['post__not_in'] = array( $exclude_post_id );
		}
		$open_review_ids = get_posts( $args );
		if ( count( $open_review_ids ) >= self::MAX_OPEN_REVIEW_ITEMS ) {
			return new WP_Error( 'mvm_entrepreneur_ad_open_limit', 'Je hebt al het maximale aantal advertenties ter beoordeling. Rond eerst een bestaande aanvraag af.', array( 'status' => 429 ) );
		}
		return true;
	}

	private static function audit_gate( string $action, int $object_id = 0, string $status = '' ) {
		if ( ! class_exists( 'MvM_Hubs_V3_Audit' ) ) {
			return true;
		}

		$logged = MvM_Hubs_V3_Audit::record(
			'platform_local_ads_' . sanitize_key( $action ) . '_authorized',
			array(
				'source' => 'mvm_platform',
				'action' => sanitize_key( $action ),
				'object_type' => 'local_ads',
				'object_id' => absint( $object_id ),
				'status' => sanitize_key( $status ),
			)
		);

		return $logged
			? true
			: new WP_Error(
				'mvm_entrepreneur_ad_audit_unavailable',
				'De actie is niet uitgevoerd omdat de beveiligde auditregistratie niet beschikbaar is.',
				array( 'status' => 503 )
			);
	}

	private static function rate_limit_key(): string {
		return 'mvm_entrepreneur_ad_create_' . get_current_user_id();
	}

	private static function parse_timestamp( $value ) {
		if ( is_numeric( $value ) ) {
			return max( 0, (int) $value );
		}
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return 0;
		}
		$timestamp = strtotime( $value );
		return false === $timestamp ? false : max( 0, (int) $timestamp );
	}

	private static function payload( WP_REST_Request $request ): array {
		$json = $request->get_json_params();
		return is_array( $json ) ? $json : (array) $request->get_body_params();
	}

	private static function get_own_ad( int $id ): ?WP_Post {
		$post = get_post( $id );
		return $post instanceof WP_Post
			&& MvM_Local_Business_Ads::POST_TYPE === $post->post_type
			&& (int) $post->post_author === get_current_user_id()
			? $post
			: null;
	}

	private static function audit( string $event, int $object_id, array $context = array() ): void {
		do_action( 'mvm_platform_audit_event', 'local_ads', $event, $object_id, get_current_user_id(), $context );
	}
}
