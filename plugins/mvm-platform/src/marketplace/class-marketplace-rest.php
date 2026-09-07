<?php

defined( 'ABSPATH' ) || exit;

final class MvM_Marketplace_REST {
	public static function register_routes(): void {
		register_rest_route(
			MvM_Platform_REST::NAMESPACE,
			'/marketplace',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'permission_callback' => '__return_true',
					'callback'            => array( __CLASS__, 'list_public' ),
					'args'                => self::list_args(),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'permission_callback' => array( __CLASS__, 'can_create' ),
					'callback'            => array( __CLASS__, 'create' ),
				),
			)
		);

		register_rest_route(
			MvM_Platform_REST::NAMESPACE,
			'/marketplace/mine',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'permission_callback' => array( 'MvM_Platform_REST', 'require_authenticated' ),
				'callback'            => array( __CLASS__, 'list_mine' ),
			)
		);

		register_rest_route(
			MvM_Platform_REST::NAMESPACE,
			'/marketplace/media',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'permission_callback' => array( __CLASS__, 'can_create' ),
				'callback'            => array( __CLASS__, 'upload_media' ),
			)
		);

		register_rest_route(
			MvM_Platform_REST::NAMESPACE,
			'/marketplace/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'permission_callback' => '__return_true',
					'callback'            => array( __CLASS__, 'get_public' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'permission_callback' => array( __CLASS__, 'can_edit_item' ),
					'callback'            => array( __CLASS__, 'update' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'permission_callback' => array( __CLASS__, 'can_edit_item' ),
					'callback'            => array( __CLASS__, 'trash' ),
				),
			)
		);

		register_rest_route(
			MvM_Platform_REST::NAMESPACE,
			'/marketplace/(?P<id>\d+)/status',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'permission_callback' => array( __CLASS__, 'can_edit_item' ),
				'callback'            => array( __CLASS__, 'set_owner_status' ),
			)
		);

		register_rest_route(
			MvM_Platform_REST::NAMESPACE,
			'/marketplace/(?P<id>\d+)/report',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'permission_callback' => array( 'MvM_Platform_REST', 'require_authenticated' ),
				'callback'            => array( __CLASS__, 'report' ),
			)
		);

		register_rest_route(
			MvM_Platform_REST::NAMESPACE,
			'/marketplace/moderation',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'permission_callback' => array( __CLASS__, 'can_moderate' ),
				'callback'            => array( __CLASS__, 'moderation_queue' ),
			)
		);

		register_rest_route(
			MvM_Platform_REST::NAMESPACE,
			'/marketplace/(?P<id>\d+)/moderate',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'permission_callback' => array( __CLASS__, 'can_moderate' ),
				'callback'            => array( __CLASS__, 'moderate' ),
			)
		);
	}

	private static function list_args(): array {
		return array(
			'page' => array(
				'type'              => 'integer',
				'default'           => 1,
				'sanitize_callback' => 'absint',
				'validate_callback' => static fn( $value ): bool => absint( $value ) >= 1,
			),
			'per_page' => array(
				'type'              => 'integer',
				'default'           => 12,
				'sanitize_callback' => 'absint',
				'validate_callback' => static fn( $value ): bool => absint( $value ) >= 1 && absint( $value ) <= 24,
			),
			'search' => array(
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'category' => array(
				'type'              => 'integer',
				'default'           => 0,
				'sanitize_callback' => 'absint',
			),
			'status' => array(
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'sanitize_key',
			),
		);
	}

	public static function can_create() {
		$auth = MvM_Platform_REST::require_authenticated();
		if ( is_wp_error( $auth ) ) {
			return $auth;
		}
		if ( MvM_Marketplace::can_create_listing() ) {
			return true;
		}
		return new WP_Error( 'mvm_marketplace_listing_not_allowed', 'Je account kan momenteel geen advertentie plaatsen.', array( 'status' => 403 ) );
	}

	public static function can_edit_item( WP_REST_Request $request ) {
		$auth = MvM_Platform_REST::require_authenticated();
		if ( is_wp_error( $auth ) ) {
			return $auth;
		}
		$post_id = absint( $request['id'] );
		if ( MvM_Marketplace::is_owner( $post_id ) || current_user_can( MvM_Platform_Capabilities::MARKETPLACE_MODERATE ) ) {
			return true;
		}
		return new WP_Error( 'mvm_marketplace_forbidden', 'Je mag deze advertentie niet wijzigen.', array( 'status' => 403 ) );
	}

	public static function can_moderate() {
		return MvM_Platform_REST::require_capability( MvM_Platform_Capabilities::MARKETPLACE_MODERATE );
	}

	public static function list_public( WP_REST_Request $request ): WP_REST_Response {
		$page     = max( 1, absint( $request->get_param( 'page' ) ) );
		$per_page = min( 24, max( 1, absint( $request->get_param( 'per_page' ) ) ?: 12 ) );
		$status   = sanitize_key( (string) $request->get_param( 'status' ) );
		$statuses = MvM_Marketplace::public_statuses();
		if ( $status && in_array( $status, $statuses, true ) ) {
			$statuses = array( $status );
		}

		$args = array(
			'post_type'      => MvM_Marketplace::POST_TYPE,
			'post_status'    => 'publish',
			'paged'          => $page,
			'posts_per_page' => $per_page,
			'orderby'        => 'date',
			'order'          => 'DESC',
			's'              => sanitize_text_field( (string) $request->get_param( 'search' ) ),
			'meta_query'     => array(
				array(
					'key'     => MvM_Marketplace::meta_key( 'status' ),
					'value'   => $statuses,
					'compare' => 'IN',
				),
			),
		);
		$category = absint( $request->get_param( 'category' ) );
		if ( $category ) {
			$args['tax_query'] = array(
				array(
					'taxonomy' => MvM_Marketplace::TAXONOMY,
					'field'    => 'term_id',
					'terms'    => array( $category ),
				),
			);
		}

		$query = new WP_Query( $args );
		$items = array_map(
			static fn( WP_Post $post ): array => MvM_Marketplace::listing_data( $post, false ),
			$query->posts
		);
		$response = rest_ensure_response( $items );
		$response->header( 'X-WP-Total', (string) $query->found_posts );
		$response->header( 'X-WP-TotalPages', (string) $query->max_num_pages );
		$response->header( 'Cache-Control', 'public, max-age=60' );
		return $response;
	}

	public static function get_public( WP_REST_Request $request ) {
		$post = get_post( absint( $request['id'] ) );
		if ( ! self::is_public_listing( $post ) ) {
			return new WP_Error( 'mvm_marketplace_not_found', 'Advertentie niet gevonden.', array( 'status' => 404 ) );
		}
		$response = rest_ensure_response( MvM_Marketplace::listing_data( $post, false ) );
		$response->header( 'Cache-Control', 'public, max-age=60' );
		return $response;
	}

	private static function is_public_listing( $post ): bool {
		if ( ! ( $post instanceof WP_Post ) || MvM_Marketplace::POST_TYPE !== $post->post_type || 'publish' !== $post->post_status ) {
			return false;
		}
		$status = (string) get_post_meta( $post->ID, MvM_Marketplace::meta_key( 'status' ), true );
		return in_array( $status, MvM_Marketplace::public_statuses(), true );
	}

	public static function list_mine(): WP_REST_Response {
		$posts = get_posts(
			array(
				'post_type'      => MvM_Marketplace::POST_TYPE,
				'post_status'    => array( 'publish', 'draft', 'private' ),
				'author'         => get_current_user_id(),
				'posts_per_page' => 100,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);
		$response = rest_ensure_response(
			array_map(
				static fn( WP_Post $post ): array => MvM_Marketplace::listing_data( $post, true ),
				$posts
			)
		);
		return MvM_Platform_REST::no_store_private( $response );
	}

	public static function create( WP_REST_Request $request ) {
		if ( ! self::rate_allowed( 'create', get_current_user_id(), 5, HOUR_IN_SECONDS ) ) {
			return new WP_Error( 'mvm_marketplace_rate_limited', 'Je hebt in korte tijd te veel advertenties geplaatst. Probeer later opnieuw.', array( 'status' => 429 ) );
		}
		$payload = self::payload( $request );
		$valid = self::validate_payload( $payload );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}
		$gallery = self::validated_gallery( (array) ( $payload['gallery'] ?? array() ), get_current_user_id() );
		if ( is_wp_error( $gallery ) ) {
			return $gallery;
		}

		$post_id = wp_insert_post(
			array(
				'post_type'    => MvM_Marketplace::POST_TYPE,
				'post_status'  => 'publish',
				'post_author'  => get_current_user_id(),
				'post_title'   => sanitize_text_field( (string) $payload['title'] ),
				'post_content' => wp_kses_post( (string) $payload['description'] ),
			),
			true
		);
		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		$payload['gallery'] = $gallery;
		$payload['status']  = 'active';
		MvM_Marketplace::set_fields( (int) $post_id, $payload );
		self::set_categories( (int) $post_id, (array) ( $payload['categories'] ?? array() ) );
		if ( $gallery ) {
			set_post_thumbnail( (int) $post_id, (int) $gallery[0] );
		}
		MvM_Marketplace::audit( (int) $post_id, 'created', get_current_user_id() );
		$post = get_post( (int) $post_id );
		$response = rest_ensure_response( MvM_Marketplace::listing_data( $post, true ) );
		$response->set_status( 201 );
		return MvM_Platform_REST::no_store_private( $response );
	}

	public static function update( WP_REST_Request $request ) {
		$post_id = absint( $request['id'] );
		$post = get_post( $post_id );
		if ( ! ( $post instanceof WP_Post ) || MvM_Marketplace::POST_TYPE !== $post->post_type ) {
			return new WP_Error( 'mvm_marketplace_not_found', 'Advertentie niet gevonden.', array( 'status' => 404 ) );
		}
		if ( 'moderated' === (string) get_post_meta( $post_id, MvM_Marketplace::meta_key( 'status' ), true ) && ! current_user_can( MvM_Platform_Capabilities::MARKETPLACE_MODERATE ) ) {
			return new WP_Error( 'mvm_marketplace_moderated', 'Deze advertentie is door moderatie geblokkeerd.', array( 'status' => 409 ) );
		}

		$payload = self::payload( $request );
		$title = array_key_exists( 'title', $payload ) ? sanitize_text_field( (string) $payload['title'] ) : $post->post_title;
		$description = array_key_exists( 'description', $payload ) ? wp_kses_post( (string) $payload['description'] ) : $post->post_content;
		$valid_text = MvM_Marketplace::validate_listing_text( $title, $description );
		if ( is_wp_error( $valid_text ) ) {
			return new WP_Error( $valid_text->get_error_code(), $valid_text->get_error_message(), array( 'status' => 400 ) );
		}
		if ( '' === trim( $title ) || '' === trim( wp_strip_all_tags( $description ) ) ) {
			return new WP_Error( 'mvm_marketplace_required_fields', 'Titel en omschrijving zijn verplicht.', array( 'status' => 400 ) );
		}

		$updated = wp_update_post(
			array(
				'ID'           => $post_id,
				'post_title'   => $title,
				'post_content' => $description,
			),
			true
		);
		if ( is_wp_error( $updated ) ) {
			return $updated;
		}

		$fields = array();
		foreach ( array( 'price_type', 'price_cents', 'condition', 'location', 'expires_at' ) as $field ) {
			if ( array_key_exists( $field, $payload ) ) {
				$fields[ $field ] = $payload[ $field ];
			} else {
				$key = MvM_Marketplace::meta_key( $field );
				$fields[ $field ] = get_post_meta( $post_id, $key, true );
			}
		}
		$fields['status'] = (string) get_post_meta( $post_id, MvM_Marketplace::meta_key( 'status' ), true );
		if ( array_key_exists( 'gallery', $payload ) ) {
			$gallery = self::validated_gallery( (array) $payload['gallery'], (int) $post->post_author );
			if ( is_wp_error( $gallery ) ) {
				return $gallery;
			}
			$fields['gallery'] = $gallery;
			if ( $gallery ) {
				set_post_thumbnail( $post_id, (int) $gallery[0] );
			} else {
				delete_post_thumbnail( $post_id );
			}
		}
		MvM_Marketplace::set_fields( $post_id, $fields );
		if ( array_key_exists( 'categories', $payload ) ) {
			self::set_categories( $post_id, (array) $payload['categories'] );
		}
		MvM_Marketplace::audit( $post_id, 'updated', get_current_user_id() );
		$response = rest_ensure_response( MvM_Marketplace::listing_data( get_post( $post_id ), true ) );
		return MvM_Platform_REST::no_store_private( $response );
	}

	public static function trash( WP_REST_Request $request ) {
		$post_id = absint( $request['id'] );
		$post = get_post( $post_id );
		if ( ! ( $post instanceof WP_Post ) || MvM_Marketplace::POST_TYPE !== $post->post_type ) {
			return new WP_Error( 'mvm_marketplace_not_found', 'Advertentie niet gevonden.', array( 'status' => 404 ) );
		}
		MvM_Marketplace::audit( $post_id, 'trashed', get_current_user_id() );
		$result = wp_trash_post( $post_id );
		if ( ! $result ) {
			return new WP_Error( 'mvm_marketplace_trash_failed', 'Advertentie kon niet worden verwijderd.', array( 'status' => 500 ) );
		}
		$response = rest_ensure_response( array( 'deleted' => true, 'id' => $post_id ) );
		return MvM_Platform_REST::no_store_private( $response );
	}

	public static function set_owner_status( WP_REST_Request $request ) {
		$post_id = absint( $request['id'] );
		$post = get_post( $post_id );
		if ( ! ( $post instanceof WP_Post ) || MvM_Marketplace::POST_TYPE !== $post->post_type ) {
			return new WP_Error( 'mvm_marketplace_not_found', 'Advertentie niet gevonden.', array( 'status' => 404 ) );
		}
		$current = (string) get_post_meta( $post_id, MvM_Marketplace::meta_key( 'status' ), true );
		if ( 'moderated' === $current && ! current_user_can( MvM_Platform_Capabilities::MARKETPLACE_MODERATE ) ) {
			return new WP_Error( 'mvm_marketplace_moderated', 'Een geblokkeerde advertentie kan alleen door moderatie worden hersteld.', array( 'status' => 409 ) );
		}
		$status = sanitize_key( (string) $request->get_param( 'status' ) );
		if ( ! in_array( $status, MvM_Marketplace::owner_statuses(), true ) ) {
			return new WP_Error( 'mvm_marketplace_invalid_status', 'Ongeldige advertentiestatus.', array( 'status' => 400 ) );
		}
		update_post_meta( $post_id, MvM_Marketplace::meta_key( 'status' ), $status );
		if ( 'publish' !== $post->post_status ) {
			wp_update_post( array( 'ID' => $post_id, 'post_status' => 'publish' ) );
		}
		MvM_Marketplace::audit( $post_id, 'status_' . $status, get_current_user_id() );
		$response = rest_ensure_response( MvM_Marketplace::listing_data( get_post( $post_id ), true ) );
		return MvM_Platform_REST::no_store_private( $response );
	}

	public static function report( WP_REST_Request $request ) {
		$post_id = absint( $request['id'] );
		$post = get_post( $post_id );
		if ( ! self::is_public_listing( $post ) ) {
			return new WP_Error( 'mvm_marketplace_not_found', 'Advertentie niet gevonden.', array( 'status' => 404 ) );
		}
		$user_id = get_current_user_id();
		if ( (int) $post->post_author === $user_id ) {
			return new WP_Error( 'mvm_marketplace_own_report', 'Je kunt je eigen advertentie niet rapporteren.', array( 'status' => 400 ) );
		}
		if ( ! self::rate_allowed( 'report', $user_id, 10, HOUR_IN_SECONDS ) ) {
			return new WP_Error( 'mvm_marketplace_rate_limited', 'Je hebt in korte tijd te veel meldingen verstuurd.', array( 'status' => 429 ) );
		}
		$reports = (array) get_post_meta( $post_id, MvM_Marketplace::meta_key( 'reports' ), true );
		foreach ( array_reverse( $reports ) as $existing ) {
			if ( (int) ( $existing['user_id'] ?? 0 ) === $user_id && (int) ( $existing['at'] ?? 0 ) > time() - DAY_IN_SECONDS ) {
				return new WP_Error( 'mvm_marketplace_duplicate_report', 'Je hebt deze advertentie al gemeld.', array( 'status' => 409 ) );
			}
		}
		$reason = trim( sanitize_textarea_field( (string) $request->get_param( 'reason' ) ) );
		if ( strlen( $reason ) < 5 ) {
			return new WP_Error( 'mvm_marketplace_report_reason', 'Geef kort aan waarom je deze advertentie meldt.', array( 'status' => 400 ) );
		}
		MvM_Marketplace::add_report( $post_id, $user_id, $reason );
		$response = rest_ensure_response( array( 'reported' => true ) );
		return MvM_Platform_REST::no_store_private( $response );
	}

	public static function moderation_queue(): WP_REST_Response {
		$posts = get_posts(
			array(
				'post_type'      => MvM_Marketplace::POST_TYPE,
				'post_status'    => array( 'publish', 'private' ),
				'posts_per_page' => 100,
				'orderby'        => 'modified',
				'order'          => 'DESC',
				'meta_query'     => array(
					'relation' => 'OR',
					array(
						'key'     => MvM_Marketplace::meta_key( 'reports' ),
						'compare' => 'EXISTS',
					),
					array(
						'key'     => MvM_Marketplace::meta_key( 'status' ),
						'value'   => 'moderated',
					),
				),
			)
		);
		$response = rest_ensure_response(
			array_map(
				static fn( WP_Post $post ): array => MvM_Marketplace::listing_data( $post, true ),
				$posts
			)
		);
		return MvM_Platform_REST::no_store_private( $response );
	}

	public static function moderate( WP_REST_Request $request ) {
		$post_id = absint( $request['id'] );
		$post = get_post( $post_id );
		if ( ! ( $post instanceof WP_Post ) || MvM_Marketplace::POST_TYPE !== $post->post_type ) {
			return new WP_Error( 'mvm_marketplace_not_found', 'Advertentie niet gevonden.', array( 'status' => 404 ) );
		}
		$action = sanitize_key( (string) $request->get_param( 'moderation_action' ) );
		$reason = trim( sanitize_textarea_field( (string) $request->get_param( 'reason' ) ) );
		if ( ! in_array( $action, array( 'block', 'restore' ), true ) || strlen( $reason ) < 5 ) {
			return new WP_Error( 'mvm_marketplace_moderation_input', 'Kies blokkeren of herstellen en geef een reden.', array( 'status' => 400 ) );
		}
		if ( 'block' === $action ) {
			update_post_meta( $post_id, MvM_Marketplace::meta_key( 'status' ), 'moderated' );
			wp_update_post( array( 'ID' => $post_id, 'post_status' => 'private' ) );
		} else {
			update_post_meta( $post_id, MvM_Marketplace::meta_key( 'status' ), 'active' );
			update_post_meta( $post_id, MvM_Marketplace::meta_key( 'expires_at' ), time() + MvM_Marketplace::DEFAULT_LIFETIME );
			wp_update_post( array( 'ID' => $post_id, 'post_status' => 'publish' ) );
		}
		MvM_Marketplace::audit( $post_id, 'moderation_' . $action, get_current_user_id(), array( 'reason' => $reason ) );
		$response = rest_ensure_response( MvM_Marketplace::listing_data( get_post( $post_id ), true ) );
		return MvM_Platform_REST::no_store_private( $response );
	}

	public static function upload_media( WP_REST_Request $request ) {
		if ( ! self::rate_allowed( 'media', get_current_user_id(), 30, HOUR_IN_SECONDS ) ) {
			return new WP_Error( 'mvm_marketplace_rate_limited', 'Je hebt in korte tijd te veel afbeeldingen geüpload.', array( 'status' => 429 ) );
		}
		$files = $request->get_file_params();
		$file = $files['file'] ?? null;
		if ( ! is_array( $file ) || empty( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
			return new WP_Error( 'mvm_marketplace_media_missing', 'Geen geldige afbeelding ontvangen.', array( 'status' => 400 ) );
		}
		if ( (int) ( $file['error'] ?? UPLOAD_ERR_OK ) !== UPLOAD_ERR_OK || (int) ( $file['size'] ?? 0 ) < 1 || (int) $file['size'] > 8 * MB_IN_BYTES ) {
			return new WP_Error( 'mvm_marketplace_media_size', 'De afbeelding is ongeldig of groter dan 8 MB.', array( 'status' => 400 ) );
		}
		$checked = wp_check_filetype_and_ext( $file['tmp_name'], (string) $file['name'] );
		$allowed = array( 'image/jpeg', 'image/png', 'image/webp' );
		if ( empty( $checked['type'] ) || ! in_array( $checked['type'], $allowed, true ) ) {
			return new WP_Error( 'mvm_marketplace_media_type', 'Gebruik een JPEG-, PNG- of WebP-afbeelding.', array( 'status' => 400 ) );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$attachment_id = media_handle_sideload( $file, 0, 'MvM Marktplaats afbeelding' );
		if ( is_wp_error( $attachment_id ) ) {
			return $attachment_id;
		}
		wp_update_post( array( 'ID' => (int) $attachment_id, 'post_author' => get_current_user_id() ) );
		$response = rest_ensure_response(
			array(
				'id'  => (int) $attachment_id,
				'url' => wp_get_attachment_image_url( (int) $attachment_id, 'large' ) ?: '',
			)
		);
		$response->set_status( 201 );
		return MvM_Platform_REST::no_store_private( $response );
	}

	private static function payload( WP_REST_Request $request ): array {
		$json = $request->get_json_params();
		if ( is_array( $json ) ) {
			return $json;
		}
		$params = $request->get_body_params();
		return is_array( $params ) ? $params : array();
	}

	private static function validate_payload( array $payload ) {
		$title = trim( sanitize_text_field( (string) ( $payload['title'] ?? '' ) ) );
		$description = trim( wp_kses_post( (string) ( $payload['description'] ?? '' ) ) );
		if ( '' === $title || '' === trim( wp_strip_all_tags( $description ) ) ) {
			return new WP_Error( 'mvm_marketplace_required_fields', 'Titel en omschrijving zijn verplicht.', array( 'status' => 400 ) );
		}
		if ( strlen( $title ) > 140 || strlen( wp_strip_all_tags( $description ) ) > 10000 ) {
			return new WP_Error( 'mvm_marketplace_field_length', 'Titel of omschrijving is te lang.', array( 'status' => 400 ) );
		}
		$valid = MvM_Marketplace::validate_listing_text( $title, $description );
		if ( is_wp_error( $valid ) ) {
			return new WP_Error( $valid->get_error_code(), $valid->get_error_message(), array( 'status' => 400 ) );
		}
		return true;
	}

	private static function validated_gallery( array $ids, int $owner_id ) {
		$ids = array_slice( array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) ), 0, MvM_Marketplace::MAX_GALLERY );
		foreach ( $ids as $attachment_id ) {
			$attachment = get_post( $attachment_id );
			if ( ! ( $attachment instanceof WP_Post ) || 'attachment' !== $attachment->post_type || ! wp_attachment_is_image( $attachment_id ) ) {
				return new WP_Error( 'mvm_marketplace_invalid_media', 'Een gekozen afbeelding is ongeldig.', array( 'status' => 400 ) );
			}
			if ( (int) $attachment->post_author !== $owner_id && ! current_user_can( MvM_Platform_Capabilities::MARKETPLACE_MODERATE ) ) {
				return new WP_Error( 'mvm_marketplace_media_forbidden', 'Je mag deze afbeelding niet gebruiken.', array( 'status' => 403 ) );
			}
		}
		return $ids;
	}

	private static function set_categories( int $post_id, array $ids ): void {
		$valid = array();
		foreach ( array_slice( array_unique( array_map( 'absint', $ids ) ), 0, 3 ) as $term_id ) {
			$term = get_term( $term_id, MvM_Marketplace::TAXONOMY );
			if ( $term instanceof WP_Term ) {
				$valid[] = $term_id;
			}
		}
		wp_set_object_terms( $post_id, $valid, MvM_Marketplace::TAXONOMY, false );
	}

	private static function rate_allowed( string $scope, int $user_id, int $limit, int $ttl ): bool {
		$key = 'mvm_mp_rate_' . md5( sanitize_key( $scope ) . '|' . $user_id );
		$count = (int) get_transient( $key );
		if ( $count >= $limit ) {
			return false;
		}
		set_transient( $key, $count + 1, $ttl );
		return true;
	}
}
