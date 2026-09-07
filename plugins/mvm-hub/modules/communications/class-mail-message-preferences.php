<?php

namespace MVM\Hub\Modules\Communications;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Private per-user Mail UI preferences.
 *
 * Only keyed HMAC references are stored. Provider message IDs, subjects,
 * correspondents and message bodies never enter WordPress user metadata.
 */
final class Mail_Message_Preferences {
    private const PIN_META = '_mvm_hub_mail_pins_v1';
    private const MAX_PINS = 500;

    public static function is_pinned( int $user_id, string $mailbox_id, string $message_id ): bool {
        $reference = self::reference( $mailbox_id, $message_id );
        return $user_id > 0 && '' !== $reference && in_array( $reference, self::pins( $user_id ), true );
    }

    public static function set_pinned( int $user_id, string $mailbox_id, string $message_id, bool $pinned ): true|\WP_Error {
        $reference = self::reference( $mailbox_id, $message_id );
        if ( $user_id <= 0 || '' === $reference ) {
            return new \WP_Error( 'mvm_mail_pin_reference', 'Dit bericht kan niet veilig worden vastgezet.' );
        }

        $pins = self::pins( $user_id );
        $existing = in_array( $reference, $pins, true );
        if ( $pinned && ! $existing ) {
            array_unshift( $pins, $reference );
            $pins = array_slice( array_values( array_unique( $pins ) ), 0, self::MAX_PINS );
        } elseif ( ! $pinned && $existing ) {
            $pins = array_values( array_diff( $pins, array( $reference ) ) );
        } else {
            return true;
        }

        return false !== update_user_meta( $user_id, self::PIN_META, $pins )
            ? true
            : new \WP_Error( 'mvm_mail_pin_store', 'De vastzetstatus kon niet worden opgeslagen.' );
    }

    /** @return array<int,string> */
    private static function pins( int $user_id ): array {
        $stored = get_user_meta( $user_id, self::PIN_META, true );
        if ( ! is_array( $stored ) ) {
            return array();
        }
        $pins = array();
        foreach ( array_slice( $stored, 0, self::MAX_PINS ) as $candidate ) {
            $candidate = is_string( $candidate ) ? strtolower( trim( $candidate ) ) : '';
            if ( 1 === preg_match( '/^[a-f0-9]{40}$/', $candidate ) ) {
                $pins[] = $candidate;
            }
        }
        return array_values( array_unique( $pins ) );
    }

    private static function reference( string $mailbox_id, string $message_id ): string {
        $mailbox_id = self::opaque_id( $mailbox_id, 128 );
        $message_id = self::opaque_id( $message_id, 260 );
        return '' === $mailbox_id || '' === $message_id
            ? ''
            : substr( wp_hash( $mailbox_id . '|' . $message_id, 'mvm_hub_mail_pin' ), 0, 40 );
    }

    private static function opaque_id( string $value, int $max_length ): string {
        $value = trim( $value );
        return strlen( $value ) <= $max_length && 1 === preg_match( '/^[A-Za-z0-9._:-]+$/', $value ) ? $value : '';
    }

    private function __construct() {}
}
