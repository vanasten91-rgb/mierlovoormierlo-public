<?php

defined( 'ABSPATH' ) || exit;

final class MvM_Platform_Event_Meta_Normalizer {
	public static function boot(): void {
		add_action( 'save_post_event_listing', array( __CLASS__, 'normalize_empty_end_date' ), PHP_INT_MAX, 1 );
	}

	public static function normalize_empty_end_date( int $post_id ): void {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		$end_date = get_post_meta( $post_id, '_event_end_date', true );
		if ( '' === trim( (string) $end_date ) && metadata_exists( 'post', $post_id, '_event_end_date' ) ) {
			delete_post_meta( $post_id, '_event_end_date' );
		}
	}
}
