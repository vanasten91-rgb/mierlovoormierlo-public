<?php

defined( 'ABSPATH' ) || exit;

final class MvM_Platform_Featured_Image_Alt {
	private const BACKFILL_OPTION = 'mvm_featured_alt_backfill_v1_done';

	public static function boot(): void {
		// The legacy snippet defines this function. During staged migration,
		// let that implementation remain authoritative until it is disabled.
		if ( function_exists( 'mvm_featured_alt_fallback_v1' ) ) {
			return;
		}

		add_action( 'save_post', array( __CLASS__, 'sync_for_post' ), 120 );
		add_action( 'init', array( __CLASS__, 'maybe_backfill' ), 200 );
	}

	public static function sync_for_post( int $post_id ): void {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		$attachment_id = get_post_thumbnail_id( $post_id );
		if ( $attachment_id ) {
			self::set_if_missing( $post_id, (int) $attachment_id );
		}
	}

	public static function maybe_backfill(): void {
		if ( get_option( self::BACKFILL_OPTION ) ) {
			return;
		}

		global $wpdb;
		$rows = $wpdb->get_results(
			"SELECT p.ID AS post_id, CAST(pm.meta_value AS UNSIGNED) AS attachment_id
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} pm
				ON pm.post_id = p.ID AND pm.meta_key = '_thumbnail_id'
			WHERE p.post_status = 'publish'
				AND CAST(pm.meta_value AS UNSIGNED) > 0
			LIMIT 5000",
			ARRAY_A
		);

		foreach ( (array) $rows as $row ) {
			self::set_if_missing( (int) $row['post_id'], (int) $row['attachment_id'] );
		}

		update_option( self::BACKFILL_OPTION, time(), false );
	}

	private static function set_if_missing( int $post_id, int $attachment_id ): void {
		$post_id       = absint( $post_id );
		$attachment_id = absint( $attachment_id );
		if ( ! $post_id || ! $attachment_id || ! wp_attachment_is_image( $attachment_id ) ) {
			return;
		}
		if ( 'publish' !== get_post_status( $post_id ) ) {
			return;
		}

		$current = trim( (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) );
		if ( '' !== $current ) {
			return;
		}

		$title = trim( wp_strip_all_tags( (string) get_the_title( $post_id ) ) );
		if ( '' === $title ) {
			return;
		}

		update_post_meta( $attachment_id, '_wp_attachment_image_alt', $title );
	}
}
