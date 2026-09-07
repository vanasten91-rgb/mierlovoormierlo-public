<?php

namespace MVM\Hub\Integrations\Mail;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Mailbox provider boundary.
 *
 * Implementations may use IMAP/SMTP or a provider API, but Hub modules must not
 * know credentials, transport details or provider-specific identifiers beyond
 * opaque mailbox/message/folder IDs.
 */
interface Mail_Provider {
    /** @return array<int,array<string,mixed>> */
    public function list_folders( string $mailbox_id ): array;

    /** @return array<string,mixed> */
    public function list_messages( string $mailbox_id, string $folder_id, array $query = array() ): array;

    /** @return array<string,mixed>|\WP_Error */
    public function get_message( string $mailbox_id, string $message_id ): array|\WP_Error;

    /** @return array<string,mixed>|\WP_Error */
    public function save_draft( string $mailbox_id, array $draft, ?string $draft_id = null ): array|\WP_Error;

    /** @return array<string,mixed>|\WP_Error */
    public function create_folder( string $mailbox_id, string $name, ?string $parent_id = null ): array|\WP_Error;

    public function rename_folder( string $mailbox_id, string $folder_id, string $name ): bool|\WP_Error;

    public function delete_folder( string $mailbox_id, string $folder_id ): bool|\WP_Error;

    public function move_message( string $mailbox_id, string $message_id, string $folder_id ): bool|\WP_Error;

    public function set_read_state( string $mailbox_id, string $message_id, bool $is_read ): bool|\WP_Error;

    public function set_flagged_state( string $mailbox_id, string $message_id, bool $is_flagged ): bool|\WP_Error;

    /**
     * The interface exposes delivery for the future transport release, but phase
     * 1 code must not call it while Communications_Policy keeps delivery disabled.
     *
     * @return array<string,mixed>|\WP_Error
     */
    public function deliver( string $mailbox_id, array $message, string $idempotency_key ): array|\WP_Error;

    /** @return array<string,bool|int|string> */
    public function capabilities( string $mailbox_id ): array;
}
