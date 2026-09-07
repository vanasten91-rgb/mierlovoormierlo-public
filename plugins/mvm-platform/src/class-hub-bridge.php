<?php

defined( 'ABSPATH' ) || exit;

final class MvM_Platform_Hub_Bridge {
	public static function boot(): void {
		add_action( 'mvm_platform_audit_event', array( __CLASS__, 'audit_event' ), 10, 5 );
	}

	public static function audit_event( string $module, string $event, int $object_id, int $actor_id, array $context = array() ): void {
		if ( ! class_exists( 'MvM_Hub4_Audit' ) ) {
			return;
		}
		$module = sanitize_key( $module );
		$event  = sanitize_key( $event );
		if ( '' === $module || '' === $event ) {
			return;
		}
		$context['source_actor_id'] = absint( $actor_id );
		MvM_Hub4_Audit::log(
			'platform.' . $module . '.' . $event,
			'success',
			array(
				'object_type' => $module,
				'object_id'   => absint( $object_id ),
				'context'     => $context,
			)
		);
	}
}
