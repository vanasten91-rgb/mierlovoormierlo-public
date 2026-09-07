<?php

namespace MVM\Hub\Integrations\Mail;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Optional V5 receive-sync boundary for mailbox providers.
 *
 * This is deliberately separate from Mail_Provider so existing providers do not
 * silently gain sync ownership. A provider must opt in and pass the V5 sync
 * contract before receive-sync can ever be promoted.
 */
interface Mail_Sync_Provider {
    /**
     * Return an initial bounded synchronization batch for one concrete folder.
     *
     * Expected keys are normalized later by V5_Mail_Sync_Contract:
     * cursor, upserts, deletedIds, hasMore and resetRequired.
     *
     * @return array<string,mixed>|\WP_Error
     */
    public function initial_sync( string $mailbox_id, string $folder_id, int $limit = 100 ): array|\WP_Error;

    /**
     * Continue from a provider-owned opaque cursor.
     *
     * The cursor must never contain credentials or message bodies. Invalidated
     * cursors must return resetRequired=true rather than silently replaying an
     * unbounded full mailbox.
     *
     * @return array<string,mixed>|\WP_Error
     */
    public function delta_sync( string $mailbox_id, string $folder_id, string $cursor, int $limit = 100 ): array|\WP_Error;

    /** @return array<string,bool|int|string> */
    public function sync_capabilities( string $mailbox_id ): array;
}
