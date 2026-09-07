<?php

namespace MVM\Hub\Integrations\Mail;

use MVM\Hub\Core\Capabilities;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Transitional ACL for the single existing editorial mailbox.
 *
 * It provides a concrete horizontal read boundary while all mutation methods
 * remain unavailable with the legacy read-only provider.
 */
final class Legacy_Editorial_Mailbox_Access implements Mailbox_Access {
    private const MAILBOX_ID = 'editorial';

    public function authorize_read( int $user_id, string $mailbox_id ): true|\WP_Error {
        if (
            $user_id <= 0
            || $user_id !== get_current_user_id()
            || self::MAILBOX_ID !== sanitize_key( $mailbox_id )
            || ! Capabilities::can_read_mail()
        ) {
            return new \WP_Error( 'mvm_mailbox_forbidden', 'Geen toegang tot deze mailbox.' );
        }

        return true;
    }

    public function authorize_compose( int $user_id, string $mailbox_id ): true|\WP_Error {
        unset( $user_id, $mailbox_id );
        return $this->read_only_error();
    }

    public function authorize_manage_folders( int $user_id, string $mailbox_id ): true|\WP_Error {
        unset( $user_id, $mailbox_id );
        return $this->read_only_error();
    }

    public function authorize_message( int $user_id, string $mailbox_id, string $message_id ): true|\WP_Error {
        $read = $this->authorize_read( $user_id, $mailbox_id );
        if ( is_wp_error( $read ) ) {
            return $read;
        }

        if ( ! ctype_digit( $message_id ) || (int) $message_id <= 0 ) {
            return new \WP_Error( 'mvm_mail_message_forbidden', 'Dit bericht is niet beschikbaar.' );
        }

        return true;
    }

    private function read_only_error(): \WP_Error {
        return new \WP_Error( 'mvm_mail_read_only', 'De bestaande redactionele mailbox is tijdens de migratie alleen-lezen.' );
    }
}
