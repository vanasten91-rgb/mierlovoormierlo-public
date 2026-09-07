<?php

defined( 'ABSPATH' ) || exit;

/**
 * Authorization boundary for future internal staff chat/mail adapters.
 *
 * This class deliberately stores no messages and exposes no REST route. The
 * source system remains responsible for loading conversation records. Callers
 * must pass the current member IDs and may only load content after one of the
 * authorization methods below has returned true.
 */
final class MvM_Hubs_V3_Communications {
	private const EMERGENCY_REASON_CODES = array(
		'incident_response',
		'security_review',
		'user_support',
	);

	/**
	 * A stored participant record is not sufficient by itself. The account must
	 * still have current editorial access when the conversation is opened.
	 */
	public static function can_open_conversation( array $member_ids, int $user_id = 0 ): bool {
		$user_id = $user_id ?: get_current_user_id();
		if ( $user_id < 1 || ! MvM_Hubs_V3_Security::can_access( 'editorial', $user_id ) ) {
			return false;
		}

		$member_ids = array_values(
			array_unique(
				array_filter(
					array_map( 'absint', $member_ids )
				)
			)
		);

		return in_array( $user_id, $member_ids, true );
	}

	/**
	 * Exceptional administrator access is fail-closed: the audit record must be
	 * durably written before a caller is allowed to load conversation content.
	 */
	public static function authorize_admin_emergency_view( int $conversation_id, string $reason_code ): bool {
		$user_id = get_current_user_id();
		if ( $user_id < 1 || $conversation_id < 1 || ! MvM_Hubs_V3_Security::can_access( 'admin', $user_id ) ) {
			return false;
		}

		$reason_code = sanitize_key( $reason_code );
		if ( ! in_array( $reason_code, self::EMERGENCY_REASON_CODES, true ) ) {
			return false;
		}

		$audit_written = MvM_Hubs_V3_Audit::record(
			'communication_emergency_view_authorized',
			array(
				'hub' => 'admin',
				'action' => 'emergency_view',
				'object_type' => 'conversation',
				'object_id' => $conversation_id,
				'reason_code' => $reason_code,
				'status' => 'authorized',
			)
		);

		return true === $audit_written;
	}

	public static function emergency_reason_codes(): array {
		return self::EMERGENCY_REASON_CODES;
	}
}
