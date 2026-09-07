<?php

namespace MVM\Hub\Integrations\Mail;

use MVM\Hub\Core\Capabilities;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Concrete mail-delivery security state using non-autoloaded options plus a
 * short MySQL advisory lock for race-safe hourly recipient limits.
 */
final class Default_Mail_Delivery_Security implements Mail_Delivery_Security {
    private \wpdb $wpdb;

    public function __construct(
        private readonly Communications_Audit $audit_sink,
        private readonly Communications_Action_Security $action_security,
        ?\wpdb $database = null
    ) {
        global $wpdb;
        $this->wpdb = $database ?? $wpdb;
    }

    public function authorize_session( int $user_id ): true|\WP_Error {
        return $this->action_security->authorize_session( $user_id );
    }

    public function authorize_step_up( int $user_id, string $action ): true|\WP_Error {
        return $this->action_security->authorize_step_up( $user_id, $action );
    }

    public function authorize_mailbox_send( int $user_id, string $mailbox_id ): true|\WP_Error {
        $configured = defined( 'MVM_HUB_MAILBOX_ID' ) ? sanitize_key( (string) MVM_HUB_MAILBOX_ID ) : 'editorial';
        if (
            $user_id <= 0
            || $user_id !== get_current_user_id()
            || sanitize_key( $mailbox_id ) !== $configured
            || ! Capabilities::can_send_mail()
        ) {
            return new \WP_Error( 'mvm_mail_send_forbidden', 'Je hebt geen toestemming om vanuit deze mailbox te verzenden.' );
        }
        return true;
    }

    public function claim_idempotency( int $user_id, string $mailbox_id, string $idempotency_key ): true|\WP_Error {
        $key = $this->idempotency_option( $user_id, $mailbox_id, $idempotency_key );
        $now = time();
        $existing = get_option( $key, null );
        if ( is_array( $existing ) ) {
            if ( (int) ( $existing['expires'] ?? 0 ) > $now ) {
                return new \WP_Error( 'mvm_mail_duplicate_delivery', 'Deze verzendactie is al verwerkt of wordt al verwerkt.' );
            }
            delete_option( $key );
        }

        $record = array( 'state' => 'claimed', 'created' => $now, 'expires' => $now + DAY_IN_SECONDS );
        if ( ! add_option( $key, $record, '', false ) ) {
            return new \WP_Error( 'mvm_mail_duplicate_delivery', 'Deze verzendactie is al verwerkt of wordt al verwerkt.' );
        }
        return true;
    }

    public function consume_rate_limit( int $user_id, string $mailbox_id, int $recipient_count ): true|\WP_Error {
        $recipient_count = max( 1, $recipient_count );
        $limit = (int) apply_filters( 'mvm_hub_mail_recipient_rate_limit_per_hour', 100, $user_id, $mailbox_id );
        $limit = max( 10, min( 500, $limit ) );
        if ( $recipient_count > $limit ) {
            return new \WP_Error( 'mvm_mail_rate_limit', 'Deze verzending overschrijdt de toegestane verzendlimiet.' );
        }

        $bucket = gmdate( 'YmdH' );
        $key = '_mvm_hub_mail_rate_' . substr( hash_hmac( 'sha256', $user_id . '|' . sanitize_key( $mailbox_id ) . '|' . $bucket, wp_salt( 'nonce' ) ), 0, 40 );
        $lock_name = 'mvm-mail-rate-' . substr( hash( 'sha256', $key ), 0, 32 );
        $locked = (int) $this->wpdb->get_var( $this->wpdb->prepare( 'SELECT GET_LOCK(%s,2)', $lock_name ) );
        if ( 1 !== $locked ) {
            return new \WP_Error( 'mvm_mail_rate_lock', 'De verzendlimiet kon niet veilig worden gecontroleerd.' );
        }

        try {
            $record = get_option( $key, array( 'count' => 0, 'expires' => time() + HOUR_IN_SECONDS + 300 ) );
            $count = is_array( $record ) ? max( 0, (int) ( $record['count'] ?? 0 ) ) : 0;
            if ( $count + $recipient_count > $limit ) {
                return new \WP_Error( 'mvm_mail_rate_limit', 'De verzendlimiet voor dit uur is bereikt.' );
            }
            update_option(
                $key,
                array( 'count' => $count + $recipient_count, 'expires' => time() + HOUR_IN_SECONDS + 300 ),
                false
            );
            return true;
        } finally {
            $this->wpdb->get_var( $this->wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock_name ) );
        }
    }

    public function finish_idempotency( int $user_id, string $mailbox_id, string $idempotency_key, string $state ): void {
        $key = $this->idempotency_option( $user_id, $mailbox_id, $idempotency_key );
        $record = get_option( $key, array() );
        $record = is_array( $record ) ? $record : array();
        $record['state'] = sanitize_key( $state );
        $record['finished'] = time();
        $record['expires'] = time() + DAY_IN_SECONDS;
        update_option( $key, $record, false );
    }

    /** @param array<string,mixed> $context */
    public function audit( string $event, string $result, array $context = array() ): void {
        $this->audit_sink->record(
            $event,
            $result,
            get_current_user_id(),
            '',
            $context
        );
    }

    private function idempotency_option( int $user_id, string $mailbox_id, string $idempotency_key ): string {
        $hash = hash_hmac(
            'sha256',
            $user_id . '|' . sanitize_key( $mailbox_id ) . '|' . trim( $idempotency_key ),
            wp_salt( 'nonce' )
        );
        return '_mvm_hub_mail_idem_' . substr( $hash, 0, 40 );
    }
}
