<?php

namespace MVM\Hub\Integrations\Mail;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Durable state boundary for Mail V2 receive synchronization.
 *
 * Implementations may persist only mailbox/folder identifiers, provider
 * generations and bounded message-state metadata. Message subjects, bodies,
 * addresses, filenames, credentials and tokens do not belong in this store.
 */
interface Mail_Sync_State_Store {
    /** @return array<string,mixed>|null|\WP_Error */
    public function load( string $mailbox_id, string $folder_id ): array|null|\WP_Error;

    /** @param array<string,mixed> $state */
    public function save( string $mailbox_id, string $folder_id, array $state ): true|\WP_Error;

    public function clear( string $mailbox_id, string $folder_id ): true|\WP_Error;
}
