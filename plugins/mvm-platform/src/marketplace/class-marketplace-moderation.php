<?php

defined( 'ABSPATH' ) || exit;

final class MvM_Marketplace_Moderation {
	public static function boot(): void {
		add_action( 'mvm_platform_audit_event', array( __CLASS__, 'clear_resolved_reports' ), 10, 6 );
	}

	public static function clear_resolved_reports( string $service, string $event, int $post_id, int $actor_id, array $context = array(), $unused = null ): void {
		unset( $actor_id, $context, $unused );
		if ( 'marketplace' !== $service || ! str_starts_with( $event, 'moderation_' ) || $post_id < 1 ) {
			return;
		}
		delete_post_meta( $post_id, MvM_Marketplace::meta_key( 'reports' ) );
	}
}
