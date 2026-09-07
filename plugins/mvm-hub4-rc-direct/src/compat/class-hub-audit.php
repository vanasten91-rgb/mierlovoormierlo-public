<?php

defined( 'ABSPATH' ) || exit;

final class MvM_Hub4_Hub_Audit {
	private const ALLOWED_CONTEXT_KEYS = array(
		'hub', 'status', 'source', 'action', 'layout', 'placement', 'object_type', 'object_id', 'object_key', 'reason_code',
	);

	public static function boot(): void {
		add_action( 'init', array( __CLASS__, 'register_post_type' ) );
		add_action( 'mvm_platform_audit_event', array( __CLASS__, 'record_platform_event' ), 10, 5 );
	}

	public static function register_post_type(): void {
		register_post_type(
			'mvm_hub_audit_v3',
			array(
				'label' => 'MvM Nieuws\/Redactie Hub audit',
				'public' => false,
				'publicly_queryable' => false,
				'show_ui' => false,
				'show_in_rest' => false,
				'exclude_from_search' => true,
				'supports' => array( 'title', 'editor', 'author' ),
			)
		);
	}

	public static function record_platform_event( $domain, $event, $object_id, $actor_user_id, $context = array() ): bool {
		$current_user_id = get_current_user_id();
		$actor_user_id   = absint( $actor_user_id );
		if ( $current_user_id < 1 || ( $actor_user_id > 0 && $actor_user_id !== $current_user_id ) ) {
			return false;
		}

		$domain = sanitize_key( (string) $domain );
		$event  = sanitize_key( (string) $event );
		if ( '' === $domain || '' === $event ) {
			return false;
		}

		$status = '';
		if ( is_array( $context ) && isset( $context['status'] ) && ! is_array( $context['status'] ) && ! is_object( $context['status'] ) ) {
			$status = sanitize_key( (string) $context['status'] );
		}

		return self::record(
			'platform_' . $domain . '_' . $event,
			array(
				'source' => 'mvm_platform',
				'action' => $event,
				'object_type' => $domain,
				'object_id' => absint( $object_id ),
				'status' => $status,
			)
		);
	}

	public static function record( string $event, array $context = array() ): bool {
		if ( ! is_user_logged_in() ) {
			return false;
		}

		$event = sanitize_key( $event );
		if ( '' === $event ) {
			return false;
		}

		$safe = array();
		foreach ( self::ALLOWED_CONTEXT_KEYS as $key ) {
			if ( ! array_key_exists( $key, $context ) || is_array( $context[ $key ] ) || is_object( $context[ $key ] ) ) {
				continue;
			}
			$safe[ $key ] = 'object_id' === $key
				? absint( $context[ $key ] )
				: sanitize_text_field( (string) $context[ $key ] );
		}

		$post_id = wp_insert_post(
			array(
				'post_type' => 'mvm_hub_audit_v3',
				'post_status' => 'private',
				'post_title' => $event,
				'post_author' => get_current_user_id(),
				'post_content' => wp_json_encode( $safe, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
			),
			true
		);

		return ! is_wp_error( $post_id ) && $post_id > 0;
	}
}
