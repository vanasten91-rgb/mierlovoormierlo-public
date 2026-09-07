<?php

namespace MVM\Hub\Modules\Communications;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Privacy-minimised blocklist for the shared editorial mailbox.
 *
 * Mailbox and sender values are stored only as keyed HMAC references. This is
 * intentionally a Hub policy layer: no provider rule or broad permission is
 * created behind an editor's back.
 */
final class Mail_Sender_Blocklist {
    private const OPTION = 'mvm_hub_mail_sender_blocklist_v1';
    private const MAX_MAILBOXES = 20;
    private const MAX_SENDERS_PER_MAILBOX = 2000;

    public static function is_blocked( string $mailbox_id, string $email ): bool {
        $mailbox = self::mailbox_reference( $mailbox_id );
        $sender  = self::sender_reference( $mailbox_id, $email );
        if ( '' === $mailbox || '' === $sender ) {
            return false;
        }
        $all = self::all();
        return in_array( $sender, $all[ $mailbox ] ?? array(), true );
    }

    public static function set_blocked( string $mailbox_id, string $email, bool $blocked ): true|\WP_Error {
        $mailbox = self::mailbox_reference( $mailbox_id );
        $sender  = self::sender_reference( $mailbox_id, $email );
        if ( '' === $mailbox || '' === $sender ) {
            return new \WP_Error( 'mvm_mail_sender_invalid', 'De afzender kan niet veilig worden geblokkeerd.' );
        }

        $all = self::all();
        $senders = $all[ $mailbox ] ?? array();
        $existing = in_array( $sender, $senders, true );
        if ( $blocked && ! $existing ) {
            array_unshift( $senders, $sender );
            $all[ $mailbox ] = array_slice( array_values( array_unique( $senders ) ), 0, self::MAX_SENDERS_PER_MAILBOX );
        } elseif ( ! $blocked && $existing ) {
            $all[ $mailbox ] = array_values( array_diff( $senders, array( $sender ) ) );
        } else {
            return true;
        }

        if ( count( $all ) > self::MAX_MAILBOXES ) {
            $all = array_slice( $all, 0, self::MAX_MAILBOXES, true );
        }
        if ( false === update_option( self::OPTION, $all, false ) && self::all() !== $all ) {
            return new \WP_Error( 'mvm_mail_sender_block_store', 'De blokkering kon niet worden opgeslagen.' );
        }
        return true;
    }

    /** @return array<string,array<int,string>> */
    private static function all(): array {
        $stored = get_option( self::OPTION, array() );
        if ( ! is_array( $stored ) ) {
            return array();
        }
        $safe = array();
        foreach ( array_slice( $stored, 0, self::MAX_MAILBOXES, true ) as $mailbox => $senders ) {
            $mailbox = is_string( $mailbox ) ? strtolower( trim( $mailbox ) ) : '';
            if ( 1 !== preg_match( '/^[a-f0-9]{40}$/', $mailbox ) || ! is_array( $senders ) ) {
                continue;
            }
            $safe[ $mailbox ] = array_values( array_unique( array_filter(
                array_map(
                    static fn( mixed $value ): string => is_string( $value ) && 1 === preg_match( '/^[a-f0-9]{40}$/', strtolower( trim( $value ) ) ) ? strtolower( trim( $value ) ) : '',
                    array_slice( $senders, 0, self::MAX_SENDERS_PER_MAILBOX )
                )
            ) ) );
        }
        return $safe;
    }

    private static function mailbox_reference( string $mailbox_id ): string {
        $mailbox_id = self::opaque_id( $mailbox_id, 128 );
        return '' === $mailbox_id ? '' : substr( wp_hash( $mailbox_id, 'mvm_hub_mail_block_mailbox' ), 0, 40 );
    }

    private static function sender_reference( string $mailbox_id, string $email ): string {
        $email = sanitize_email( strtolower( trim( $email ) ) );
        return '' === self::opaque_id( $mailbox_id, 128 ) || false === is_email( $email )
            ? ''
            : substr( wp_hash( $mailbox_id . '|' . $email, 'mvm_hub_mail_block_sender' ), 0, 40 );
    }

    private static function opaque_id( string $value, int $max_length ): string {
        $value = trim( $value );
        return strlen( $value ) <= $max_length && 1 === preg_match( '/^[A-Za-z0-9._:-]+$/', $value ) ? $value : '';
    }

    private function __construct() {}
}
