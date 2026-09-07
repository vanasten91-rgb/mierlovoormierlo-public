<?php

namespace MVM\Hub\Integrations\Mail;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Security/state boundary required by the mail delivery service.
 *
 * Implementations own session/step-up verification, mailbox ACLs, rate limits,
 * idempotency state and confidential audit persistence. No default permissive
 * implementation belongs in the Hub plugin.
 */
interface Mail_Delivery_Security {
    public function authorize_session( int $user_id ): true|\WP_Error;

    public function authorize_step_up( int $user_id, string $action ): true|\WP_Error;

    public function authorize_mailbox_send( int $user_id, string $mailbox_id ): true|\WP_Error;

    public function claim_idempotency( int $user_id, string $mailbox_id, string $idempotency_key ): true|\WP_Error;

    public function consume_rate_limit( int $user_id, string $mailbox_id, int $recipient_count ): true|\WP_Error;

    public function finish_idempotency( int $user_id, string $mailbox_id, string $idempotency_key, string $state ): void;

    /** @param array<string,mixed> $context */
    public function audit( string $event, string $result, array $context = array() ): void;
}
