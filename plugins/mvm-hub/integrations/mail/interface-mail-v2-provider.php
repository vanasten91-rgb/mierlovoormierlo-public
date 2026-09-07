<?php

namespace MVM\Hub\Integrations\Mail;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Target Mail V2 provider boundary.
 *
 * Unlike the legacy Mail_Provider contract, every message operation is folder
 * scoped. This avoids treating an IMAP UID as globally unique and prevents
 * folder-confusion/IDOR classes during read and mutation operations.
 */
interface Mail_V2_Provider extends Mail_Sync_Provider {
    /** @return array<int,array<string,mixed>> */
    public function list_folders( string $mailbox_id ): array;

    /** @return array<string,mixed> */
    public function list_messages( string $mailbox_id, string $folder_id, array $query = array() ): array;

    /** @return array<string,mixed>|\WP_Error */
    public function get_message( string $mailbox_id, string $folder_id, string $message_id ): array|\WP_Error;

    /** @return array<string,mixed>|\WP_Error */
    public function fetch_attachment( string $mailbox_id, string $folder_id, string $message_id, string $attachment_id ): array|\WP_Error;

    public function move_message( string $mailbox_id, string $source_folder_id, string $message_id, string $target_folder_id ): bool|\WP_Error;

    public function set_read_state( string $mailbox_id, string $folder_id, string $message_id, bool $is_read ): bool|\WP_Error;

    public function set_flagged_state( string $mailbox_id, string $folder_id, string $message_id, bool $is_flagged ): bool|\WP_Error;

    /** @return array<string,mixed>|\WP_Error */
    public function deliver( string $mailbox_id, array $message, string $idempotency_key ): array|\WP_Error;

    /** @return array<string,bool|int|string> */
    public function capabilities( string $mailbox_id ): array;
}
