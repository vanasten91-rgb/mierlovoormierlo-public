<?php

defined( 'ABSPATH' ) || exit;

final class MvM_Newsletter_REST {
	public static function register_routes(): void {
		register_rest_route(
			MvM_Platform_REST::NAMESPACE,
			'/newsletter/topics',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'permission_callback' => '__return_true',
				'callback'            => static fn(): WP_REST_Response => rest_ensure_response( MvM_Newsletter::topics() ),
			)
		);

		register_rest_route(
			MvM_Platform_REST::NAMESPACE,
			'/newsletter/subscribe',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'permission_callback' => '__return_true',
				'callback'            => array( __CLASS__, 'subscribe' ),
			)
		);

		register_rest_route(
			MvM_Platform_REST::NAMESPACE,
			'/newsletter/confirm',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'permission_callback' => '__return_true',
				'callback'            => array( __CLASS__, 'confirm' ),
			)
		);

		register_rest_route(
			MvM_Platform_REST::NAMESPACE,
			'/newsletter/unsubscribe',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'permission_callback' => '__return_true',
				'callback'            => array( __CLASS__, 'unsubscribe' ),
			)
		);

		register_rest_route(
			MvM_Platform_REST::NAMESPACE,
			'/newsletter/preferences',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'permission_callback' => array( 'MvM_Platform_REST', 'require_authenticated' ),
					'callback'            => array( __CLASS__, 'preferences' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'permission_callback' => array( 'MvM_Platform_REST', 'require_authenticated' ),
					'callback'            => array( __CLASS__, 'update_preferences' ),
				),
			)
		);

		register_rest_route(
			MvM_Platform_REST::NAMESPACE,
			'/newsletter/campaigns',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'permission_callback' => array( __CLASS__, 'can_manage' ),
					'callback'            => array( __CLASS__, 'campaigns' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'permission_callback' => array( __CLASS__, 'can_manage' ),
					'callback'            => array( __CLASS__, 'create_campaign' ),
				),
			)
		);

		register_rest_route(
			MvM_Platform_REST::NAMESPACE,
			'/newsletter/campaigns/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'permission_callback' => array( __CLASS__, 'can_manage' ),
				'callback'            => array( __CLASS__, 'campaign' ),
			)
		);

		foreach ( array( 'schedule', 'cancel', 'test' ) as $action ) {
			register_rest_route(
				MvM_Platform_REST::NAMESPACE,
				'/newsletter/campaigns/(?P<id>\d+)/' . $action,
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'permission_callback' => array( __CLASS__, 'can_manage' ),
					'callback'            => array( __CLASS__, 'campaign_' . $action ),
				)
			);
		}
	}

	public static function can_manage() {
		return MvM_Platform_REST::require_capability( MvM_Platform_Capabilities::NEWSLETTER_MANAGE );
	}

	public static function subscribe( WP_REST_Request $request ): WP_REST_Response {
		global $wpdb;
		$email = MvM_Newsletter::normalize_email( (string) $request->get_param( 'email' ) );
		$topics = MvM_Newsletter::normalize_topics( $request->get_param( 'topics' ) );
		$source = sanitize_key( (string) $request->get_param( 'source' ) );
		if ( '' === $source ) {
			$source = 'website';
		}

		if ( ! self::rate_allowed( 'subscribe_ip', self::client_ip(), 12, HOUR_IN_SECONDS ) ) {
			return self::generic_subscribe_response();
		}
		if ( ! is_email( $email ) || empty( $topics ) || ! self::rate_allowed( 'subscribe_email', MvM_Newsletter::email_hash( $email ), 4, HOUR_IN_SECONDS ) ) {
			return self::generic_subscribe_response();
		}

		$existing = MvM_Newsletter::subscriber_by_email( $email );
		if ( $existing && 'active' === (string) $existing->status ) {
			/* Een anonieme derde mag voorkeuren van een bestaand actief adres niet wijzigen. */
			return self::generic_subscribe_response();
		}

		$token = MvM_Newsletter::generate_confirmation_token();
		$now   = MvM_Newsletter::now_mysql();
		$data = array(
			'email'              => $email,
			'email_hash'         => MvM_Newsletter::email_hash( $email ),
			'status'             => 'pending',
			'topics'             => wp_json_encode( $topics ),
			'consent_at'         => null,
			'consent_source'     => $source,
			'consent_version'    => MvM_Newsletter::CONSENT_VERSION,
			'confirm_token_hash' => MvM_Newsletter::token_hash( $token, 'confirm' ),
			'confirm_expires'    => gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS ),
			'updated_at'         => $now,
		);

		if ( $existing ) {
			$wpdb->update(
				MvM_Newsletter::table( 'subscribers' ),
				$data,
				array( 'id' => absint( $existing->id ) ),
				null,
				array( '%d' )
			);
		} else {
			$data['created_at'] = $now;
			$wpdb->insert( MvM_Newsletter::table( 'subscribers' ), $data );
		}

		MvM_Newsletter_Delivery::send_confirmation( $email, $token );
		return self::generic_subscribe_response();
	}

	private static function generic_subscribe_response(): WP_REST_Response {
		$response = rest_ensure_response(
			array(
				'message' => 'Als het adres geldig is en nog bevestiging nodig heeft, ontvang je een e-mail. Controleer ook je spammap.',
			)
		);
		return MvM_Platform_REST::no_store_private( $response );
	}

	public static function confirm( WP_REST_Request $request ) {
		global $wpdb;
		$token = trim( (string) $request->get_param( 'token' ) );
		if ( strlen( $token ) < 20 || strlen( $token ) > 100 || ! preg_match( '/^[A-Za-z0-9]+$/', $token ) ) {
			return new WP_Error( 'mvm_newsletter_invalid_confirmation', 'Deze bevestigingslink is ongeldig of verlopen.', array( 'status' => 400 ) );
		}
		$subscriber = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . MvM_Newsletter::table( 'subscribers' ) . " WHERE status = 'pending' AND confirm_token_hash = %s AND confirm_expires >= %s LIMIT 1",
				MvM_Newsletter::token_hash( $token, 'confirm' ),
				MvM_Newsletter::now_mysql()
			)
		);
		if ( ! $subscriber ) {
			return new WP_Error( 'mvm_newsletter_invalid_confirmation', 'Deze bevestigingslink is ongeldig of verlopen.', array( 'status' => 400 ) );
		}
		$now = MvM_Newsletter::now_mysql();
		$wpdb->update(
			MvM_Newsletter::table( 'subscribers' ),
			array(
				'status'             => 'active',
				'consent_at'         => $now,
				'confirm_token_hash' => '',
				'confirm_expires'    => null,
				'updated_at'         => $now,
			),
			array( 'id' => absint( $subscriber->id ) ),
			null,
			array( '%d' )
		);
		$response = rest_ensure_response( array( 'confirmed' => true, 'message' => 'Je inschrijving is bevestigd.' ) );
		return MvM_Platform_REST::no_store_private( $response );
	}

	public static function unsubscribe( WP_REST_Request $request ): WP_REST_Response {
		global $wpdb;
		$token = trim( (string) $request->get_param( 'token' ) );
		$subscriber = MvM_Newsletter::subscriber_from_unsubscribe_token( $token );
		if ( $subscriber ) {
			$wpdb->update(
				MvM_Newsletter::table( 'subscribers' ),
				array(
					'status'             => 'unsubscribed',
					'topics'             => '[]',
					'confirm_token_hash' => '',
					'confirm_expires'    => null,
					'updated_at'         => MvM_Newsletter::now_mysql(),
				),
				array( 'id' => absint( $subscriber->id ) ),
				null,
				array( '%d' )
			);
		}
		/* Idempotent en niet-enumererend: ook een reeds afgemeld/ongeldig token geeft dezelfde respons. */
		$response = rest_ensure_response( array( 'unsubscribed' => true, 'message' => 'Je afmelding is verwerkt.' ) );
		return MvM_Platform_REST::no_store_private( $response );
	}

	public static function preferences() {
		$user = wp_get_current_user();
		$subscriber = MvM_Newsletter::subscriber_by_email( (string) $user->user_email );
		$data = array(
			'status' => $subscriber ? (string) $subscriber->status : 'none',
			'topics' => $subscriber && 'active' === (string) $subscriber->status ? MvM_Newsletter::decode_topics( $subscriber->topics ) : array(),
		);
		return MvM_Platform_REST::no_store_private( rest_ensure_response( $data ) );
	}

	public static function update_preferences( WP_REST_Request $request ) {
		global $wpdb;
		$user = wp_get_current_user();
		$subscriber = MvM_Newsletter::subscriber_by_email( (string) $user->user_email );
		if ( ! $subscriber || 'active' !== (string) $subscriber->status ) {
			return new WP_Error( 'mvm_newsletter_not_active', 'Je hebt geen actieve nieuwsbriefinschrijving.', array( 'status' => 409 ) );
		}
		$topics = MvM_Newsletter::normalize_topics( $request->get_param( 'topics' ) );
		if ( empty( $topics ) ) {
			return new WP_Error( 'mvm_newsletter_topics_required', 'Kies minimaal één onderwerp of meld je volledig af.', array( 'status' => 400 ) );
		}
		$wpdb->update(
			MvM_Newsletter::table( 'subscribers' ),
			array( 'topics' => wp_json_encode( $topics ), 'updated_at' => MvM_Newsletter::now_mysql() ),
			array( 'id' => absint( $subscriber->id ) ),
			array( '%s', '%s' ),
			array( '%d' )
		);
		return MvM_Platform_REST::no_store_private( rest_ensure_response( array( 'status' => 'active', 'topics' => $topics ) ) );
	}

	public static function campaigns(): WP_REST_Response {
		global $wpdb;
		$rows = $wpdb->get_results(
			'SELECT id,subject,preheader,status,topics,created_by,scheduled_at,sent_at,total_queued,total_sent,total_failed,created_at,updated_at FROM ' . MvM_Newsletter::table( 'campaigns' ) . ' ORDER BY id DESC LIMIT 100' // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		);
		$items = array_map( array( __CLASS__, 'campaign_public_admin_data' ), (array) $rows );
		return MvM_Platform_REST::no_store_private( rest_ensure_response( $items ) );
	}

	public static function campaign( WP_REST_Request $request ) {
		$campaign = self::get_campaign( absint( $request['id'] ) );
		if ( ! $campaign ) {
			return new WP_Error( 'mvm_newsletter_campaign_not_found', 'Campagne niet gevonden.', array( 'status' => 404 ) );
		}
		$data = self::campaign_public_admin_data( $campaign );
		$data['body'] = (string) $campaign->body;
		return MvM_Platform_REST::no_store_private( rest_ensure_response( $data ) );
	}

	public static function create_campaign( WP_REST_Request $request ) {
		global $wpdb;
		$payload = self::payload( $request );
		$subject = trim( sanitize_text_field( (string) ( $payload['subject'] ?? '' ) ) );
		$preheader = trim( sanitize_text_field( (string) ( $payload['preheader'] ?? '' ) ) );
		$body = wp_kses_post( (string) ( $payload['body'] ?? '' ) );
		$topics = MvM_Newsletter::normalize_topics( $payload['topics'] ?? array() );
		if ( '' === $subject || strlen( $subject ) > 180 || '' === trim( wp_strip_all_tags( $body ) ) || empty( $topics ) ) {
			return new WP_Error( 'mvm_newsletter_campaign_invalid', 'Onderwerp, inhoud en minimaal één doelgroep zijn verplicht.', array( 'status' => 400 ) );
		}
		if ( strlen( $preheader ) > 220 || strlen( $body ) > 200000 ) {
			return new WP_Error( 'mvm_newsletter_campaign_too_large', 'De campagne-inhoud is te lang.', array( 'status' => 400 ) );
		}
		$now = MvM_Newsletter::now_mysql();
		$inserted = $wpdb->insert(
			MvM_Newsletter::table( 'campaigns' ),
			array(
				'subject'    => $subject,
				'preheader'  => $preheader,
				'body'       => $body,
				'status'     => 'draft',
				'topics'     => wp_json_encode( $topics ),
				'created_by' => get_current_user_id(),
				'created_at' => $now,
				'updated_at' => $now,
			)
		);
		if ( false === $inserted ) {
			return new WP_Error( 'mvm_newsletter_campaign_create_failed', 'Campagne kon niet worden opgeslagen.', array( 'status' => 500 ) );
		}
		$campaign = self::get_campaign( (int) $wpdb->insert_id );
		$response = rest_ensure_response( self::campaign_public_admin_data( $campaign ) );
		$response->set_status( 201 );
		return MvM_Platform_REST::no_store_private( $response );
	}

	public static function campaign_schedule( WP_REST_Request $request ) {
		global $wpdb;
		$campaign = self::get_campaign( absint( $request['id'] ) );
		if ( ! $campaign || ! in_array( (string) $campaign->status, array( 'draft', 'scheduled' ), true ) ) {
			return new WP_Error( 'mvm_newsletter_campaign_not_schedulable', 'Deze campagne kan niet worden ingepland.', array( 'status' => 409 ) );
		}
		$input = trim( (string) $request->get_param( 'scheduled_at' ) );
		$timestamp = $input ? strtotime( $input ) : time();
		if ( false === $timestamp || $timestamp > time() + YEAR_IN_SECONDS ) {
			return new WP_Error( 'mvm_newsletter_schedule_invalid', 'Ongeldige verzenddatum.', array( 'status' => 400 ) );
		}
		$scheduled = gmdate( 'Y-m-d H:i:s', max( time(), $timestamp ) );
		$wpdb->update(
			MvM_Newsletter::table( 'campaigns' ),
			array( 'status' => 'scheduled', 'scheduled_at' => $scheduled, 'updated_at' => MvM_Newsletter::now_mysql() ),
			array( 'id' => absint( $campaign->id ) ),
			array( '%s', '%s', '%s' ),
			array( '%d' )
		);
		do_action( 'mvm_platform_audit_event', 'newsletter', 'campaign_scheduled', absint( $campaign->id ), get_current_user_id(), array( 'scheduled_at' => $scheduled ) );
		return MvM_Platform_REST::no_store_private( rest_ensure_response( self::campaign_public_admin_data( self::get_campaign( absint( $campaign->id ) ) ) ) );
	}

	public static function campaign_cancel( WP_REST_Request $request ) {
		global $wpdb;
		$campaign = self::get_campaign( absint( $request['id'] ) );
		if ( ! $campaign || ! in_array( (string) $campaign->status, array( 'draft', 'scheduled' ), true ) ) {
			return new WP_Error( 'mvm_newsletter_campaign_not_cancelable', 'Deze campagne kan niet meer worden geannuleerd.', array( 'status' => 409 ) );
		}
		$wpdb->update(
			MvM_Newsletter::table( 'campaigns' ),
			array( 'status' => 'cancelled', 'updated_at' => MvM_Newsletter::now_mysql() ),
			array( 'id' => absint( $campaign->id ) ),
			array( '%s', '%s' ),
			array( '%d' )
		);
		do_action( 'mvm_platform_audit_event', 'newsletter', 'campaign_cancelled', absint( $campaign->id ), get_current_user_id() );
		return MvM_Platform_REST::no_store_private( rest_ensure_response( array( 'cancelled' => true ) ) );
	}

	public static function campaign_test( WP_REST_Request $request ) {
		$campaign = self::get_campaign( absint( $request['id'] ) );
		if ( ! $campaign ) {
			return new WP_Error( 'mvm_newsletter_campaign_not_found', 'Campagne niet gevonden.', array( 'status' => 404 ) );
		}
		$user = wp_get_current_user();
		if ( ! is_email( $user->user_email ) ) {
			return new WP_Error( 'mvm_newsletter_test_email_missing', 'Je stafaccount heeft geen geldig e-mailadres.', array( 'status' => 409 ) );
		}
		$sent = MvM_Newsletter_Delivery::send_test( $campaign, (string) $user->user_email );
		if ( ! $sent ) {
			return new WP_Error( 'mvm_newsletter_test_failed', 'Testmail kon niet worden verzonden.', array( 'status' => 502 ) );
		}
		return MvM_Platform_REST::no_store_private( rest_ensure_response( array( 'sent' => true, 'message' => 'Testmail verzonden naar je eigen stafadres.' ) ) );
	}

	private static function campaign_public_admin_data( object $campaign ): array {
		return array(
			'id'           => absint( $campaign->id ),
			'subject'      => (string) $campaign->subject,
			'preheader'    => (string) $campaign->preheader,
			'status'       => (string) $campaign->status,
			'topics'       => MvM_Newsletter::decode_topics( $campaign->topics ),
			'created_by'   => absint( $campaign->created_by ),
			'scheduled_at' => (string) ( $campaign->scheduled_at ?? '' ),
			'sent_at'      => (string) ( $campaign->sent_at ?? '' ),
			'total_queued' => (int) $campaign->total_queued,
			'total_sent'   => (int) $campaign->total_sent,
			'total_failed' => (int) $campaign->total_failed,
			'created_at'   => (string) $campaign->created_at,
			'updated_at'   => (string) $campaign->updated_at,
		);
	}

	private static function get_campaign( int $id ) {
		global $wpdb;
		if ( $id < 1 ) {
			return null;
		}
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . MvM_Newsletter::table( 'campaigns' ) . ' WHERE id = %d LIMIT 1', $id ) );
	}

	private static function payload( WP_REST_Request $request ): array {
		$json = $request->get_json_params();
		return is_array( $json ) ? $json : (array) $request->get_body_params();
	}

	private static function client_ip(): string {
		return isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : 'unknown';
	}

	private static function rate_allowed( string $scope, string $value, int $limit, int $ttl ): bool {
		$key = 'mvm_newsletter_rate_' . hash_hmac( 'sha256', sanitize_key( $scope ) . '|' . $value, wp_salt( 'nonce' ) );
		$count = (int) get_transient( $key );
		if ( $count >= $limit ) {
			return false;
		}
		set_transient( $key, $count + 1, $ttl );
		return true;
	}
}
