<?php

namespace MVM\Hub\Integrations\Mail;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Optional Mail V2 lifecycle contract for providers that support Hub-managed
 * drafts and folder mutations in addition to strict read/sync/send behavior.
 */
interface Mail_V2_Lifecycle_Provider extends Mail_V2_Provider {
    /** @param array<string,mixed> $draft @return array<string,mixed>|\WP_Error */
    public function save_draft( string $mailbox_id, array $draft, ?string $draft_id = null ): array|\WP_Error;

    /** @return array<string,mixed>|\WP_Error */
    public function create_folder( string $mailbox_id, string $name, ?string $parent_id = null ): array|\WP_Error;

    public function rename_folder( string $mailbox_id, string $folder_id, string $name ): bool|\WP_Error;

    public function delete_folder( string $mailbox_id, string $folder_id ): bool|\WP_Error;
}
