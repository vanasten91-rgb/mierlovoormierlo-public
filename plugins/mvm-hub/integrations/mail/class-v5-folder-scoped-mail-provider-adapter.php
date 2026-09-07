<?php

namespace MVM\Hub\Integrations\Mail;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Strict Mail V2 adapter around the existing folder-scoped IMAP/SMTP provider.
 *
 * This adapter deliberately refuses bare message UIDs. Every read and mutation
 * must carry a message id that is already bound to the supplied folder id.
 * Mailbox/message authorization and the production cutover gate remain caller
 * responsibilities; this class owns only transport and identity boundaries.
 */
final class V5_Folder_Scoped_Mail_Provider_Adapter implements Mail_V2_Lifecycle_Provider {
    public function __construct(
        private readonly Folder_Scoped_IMAP_Mail_Provider $mail,
        private readonly Mail_Sync_Provider $sync,
        private readonly V5_IMAP_Attachment_Reader $attachments
    ) {}

    /** @return array<int,array<string,mixed>> */
    public function list_folders( string $mailbox_id ): array {
        $folders = array();
        foreach ( array_slice( $this->mail->list_folders( $mailbox_id ), 0, 200 ) as $folder ) {
            if ( ! is_array( $folder ) ) {
                continue;
            }
            $id = self::folder_id( (string) ( $folder['id'] ?? '' ) );
            if ( '' === $id ) {
                continue;
            }
            $folders[] = array(
                'id'          => $id,
                'name'        => mb_substr( sanitize_text_field( (string) ( $folder['name'] ?? '' ) ), 0, 160 ),
                'specialUse'  => sanitize_key( (string) ( $folder['specialUse'] ?? '' ) ),
                'system'      => true === ( $folder['system'] ?? false ),
                'readOnly'    => true === ( $folder['readOnly'] ?? false ),
                'unreadCount' => max( 0, min( 1000000, (int) ( $folder['unreadCount'] ?? 0 ) ) ),
            );
        }
        return $folders;
    }

    /** @return array<string,mixed> */
    public function list_messages( string $mailbox_id, string $folder_id, array $query = array() ): array {
        $folder_id = self::folder_id( $folder_id );
        if ( '' === $folder_id ) {
            return array( 'messages' => array(), 'total' => 0, 'hasMore' => false, 'readonly' => true );
        }

        $query['limit'] = max( 1, min( 100, (int) ( $query['limit'] ?? 20 ) ) );
        $query['offset'] = max( 0, min( 5000, (int) ( $query['offset'] ?? 0 ) ) );
        $query['direction'] = 'asc' === sanitize_key( (string) ( $query['direction'] ?? 'desc' ) ) ? 'asc' : 'desc';

        $payload = $this->mail->list_messages( $mailbox_id, $folder_id, $query );
        $messages = array();
        foreach ( array_slice( (array) ( $payload['messages'] ?? array() ), 0, 100 ) as $message ) {
            if ( ! is_array( $message ) ) {
                continue;
            }
            $message_id = self::scoped_message_id( $folder_id, (string) ( $message['id'] ?? '' ) );
            if ( is_wp_error( $message_id ) ) {
                continue;
            }
            $message['id']       = $message_id;
            $message['folderId'] = $folder_id;
            $messages[] = $message;
        }

        return array(
            'messages' => $messages,
            'total'    => max( 0, (int) ( $payload['total'] ?? count( $messages ) ) ),
            'hasMore'  => true === ( $payload['hasMore'] ?? false ),
            'readonly' => true === ( $payload['readonly'] ?? false ),
        );
    }

    /** @return array<string,mixed>|\WP_Error */
    public function get_message( string $mailbox_id, string $folder_id, string $message_id ): array|\WP_Error {
        $scoped = self::scoped_message_id( $folder_id, $message_id );
        if ( is_wp_error( $scoped ) ) {
            return $scoped;
        }
        $message = $this->mail->get_message( $mailbox_id, $scoped );
        if ( is_wp_error( $message ) ) {
            return $message;
        }
        if ( ! hash_equals( $folder_id, (string) ( $message['folderId'] ?? '' ) ) ) {
            return new \WP_Error( 'mvm_mail_v2_folder_mismatch', 'Het bericht hoort niet bij deze mailboxmap.' );
        }
        $message['id'] = $scoped;
        return $message;
    }

    /** @return array<string,mixed>|\WP_Error */
    public function fetch_attachment( string $mailbox_id, string $folder_id, string $message_id, string $attachment_id ): array|\WP_Error {
        $scoped = self::scoped_message_id( $folder_id, $message_id );
        if ( is_wp_error( $scoped ) ) {
            return $scoped;
        }
        return $this->attachments->fetch( $mailbox_id, $folder_id, $scoped, $attachment_id );
    }

    /** @param array<string,mixed> $draft @return array<string,mixed>|\WP_Error */
    public function save_draft( string $mailbox_id, array $draft, ?string $draft_id = null ): array|\WP_Error {
        return $this->mail->save_draft( $mailbox_id, $draft, $draft_id );
    }

    /** @return array<string,mixed>|\WP_Error */
    public function create_folder( string $mailbox_id, string $name, ?string $parent_id = null ): array|\WP_Error {
        return $this->mail->create_folder( $mailbox_id, $name, $parent_id );
    }

    public function rename_folder( string $mailbox_id, string $folder_id, string $name ): bool|\WP_Error {
        $folder_id = self::folder_id( $folder_id );
        if ( '' === $folder_id ) {
            return new \WP_Error( 'mvm_mail_v2_folder_reference', 'Ongeldige mailboxmap.' );
        }
        return $this->mail->rename_folder( $mailbox_id, $folder_id, $name );
    }

    public function delete_folder( string $mailbox_id, string $folder_id ): bool|\WP_Error {
        $folder_id = self::folder_id( $folder_id );
        if ( '' === $folder_id ) {
            return new \WP_Error( 'mvm_mail_v2_folder_reference', 'Ongeldige mailboxmap.' );
        }
        return $this->mail->delete_folder( $mailbox_id, $folder_id );
    }

    public function move_message( string $mailbox_id, string $source_folder_id, string $message_id, string $target_folder_id ): bool|\WP_Error {
        $scoped = self::scoped_message_id( $source_folder_id, $message_id );
        if ( is_wp_error( $scoped ) ) {
            return $scoped;
        }
        $target_folder_id = self::folder_id( $target_folder_id );
        if ( '' === $target_folder_id ) {
            return new \WP_Error( 'mvm_mail_v2_target_folder', 'Ongeldige doelmap.' );
        }
        return $this->mail->move_message( $mailbox_id, $scoped, $target_folder_id );
    }

    public function set_read_state( string $mailbox_id, string $folder_id, string $message_id, bool $is_read ): bool|\WP_Error {
        $scoped = self::scoped_message_id( $folder_id, $message_id );
        return is_wp_error( $scoped ) ? $scoped : $this->mail->set_read_state( $mailbox_id, $scoped, $is_read );
    }

    public function set_flagged_state( string $mailbox_id, string $folder_id, string $message_id, bool $is_flagged ): bool|\WP_Error {
        $scoped = self::scoped_message_id( $folder_id, $message_id );
        return is_wp_error( $scoped ) ? $scoped : $this->mail->set_flagged_state( $mailbox_id, $scoped, $is_flagged );
    }

    /** @return array<string,mixed>|\WP_Error */
    public function deliver( string $mailbox_id, array $message, string $idempotency_key ): array|\WP_Error {
        return $this->mail->deliver( $mailbox_id, $message, $idempotency_key );
    }

    /** @return array<string,bool|int|string> */
    public function capabilities( string $mailbox_id ): array {
        $base = $this->mail->capabilities( $mailbox_id );
        return array_merge(
            $base,
            array(
                'folderScopedRead'      => true === ( $base['read'] ?? false ),
                'folderScopedMutations' => true === ( $base['move'] ?? false ) && true === ( $base['flags'] ?? false ),
                'attachmentFetch'       => $this->attachments->supported( $mailbox_id ),
                'folderLifecycle'       => true === ( $base['folders'] ?? false ),
                'draftLifecycle'        => true === ( $base['drafts'] ?? false ),
                'remoteImages'          => false,
            )
        );
    }

    /** @return array<string,mixed>|\WP_Error */
    public function initial_sync( string $mailbox_id, string $folder_id, int $limit = 100 ): array|\WP_Error {
        return $this->sync->initial_sync( $mailbox_id, $folder_id, $limit );
    }

    /** @return array<string,mixed>|\WP_Error */
    public function delta_sync( string $mailbox_id, string $folder_id, string $cursor, int $limit = 100 ): array|\WP_Error {
        return $this->sync->delta_sync( $mailbox_id, $folder_id, $cursor, $limit );
    }

    /** @return array<string,bool|int|string> */
    public function sync_capabilities( string $mailbox_id ): array {
        return $this->sync->sync_capabilities( $mailbox_id );
    }

    private static function folder_id( string $value ): string {
        $value = trim( $value );
        if ( '' === $value || strlen( $value ) > 128 ) {
            return '';
        }
        return 1 === preg_match( '/^[A-Za-z0-9_-]+$/', $value ) ? $value : '';
    }

    /** @return string|\WP_Error */
    private static function scoped_message_id( string $folder_id, string $message_id ): string|\WP_Error {
        $folder_id = self::folder_id( $folder_id );
        $message_id = trim( $message_id );
        if (
            '' === $folder_id
            || strlen( $message_id ) > 260
            || 1 !== preg_match( '/^([A-Za-z0-9_-]+)\.(\d+)$/', $message_id, $matches )
            || ! hash_equals( $folder_id, $matches[1] )
            || (int) $matches[2] < 1
        ) {
            return new \WP_Error( 'mvm_mail_v2_message_reference', 'Mail V2 vereist een folder-scoped berichtreferentie.' );
        }
        return $folder_id . '.' . (string) (int) $matches[2];
    }
}
