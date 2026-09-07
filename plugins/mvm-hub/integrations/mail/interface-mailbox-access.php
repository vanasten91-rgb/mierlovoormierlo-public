<?php

namespace MVM\Hub\Integrations\Mail;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Horizontal authorization boundary for mailboxes/messages/folders.
 * Capabilities grant the kind of action; this interface decides whether the
 * current user may perform it on this concrete mailbox/message object.
 */
interface Mailbox_Access {
    public function authorize_read( int $user_id, string $mailbox_id ): true|\WP_Error;

    public function authorize_compose( int $user_id, string $mailbox_id ): true|\WP_Error;

    public function authorize_manage_folders( int $user_id, string $mailbox_id ): true|\WP_Error;

    public function authorize_message( int $user_id, string $mailbox_id, string $message_id ): true|\WP_Error;
}
