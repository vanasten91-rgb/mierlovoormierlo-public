<?php

namespace MVM\Hub\Modules\Communications;

use MVM\Hub\Core\Capabilities;
use MVM\Hub\Integrations\Mail\Mailbox_Access;
use MVM\Hub\Integrations\Mail\Mail_V2_Lifecycle_Provider;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Dormant application facade for Mail V2.
 *
 * UI/controllers must eventually call this facade instead of transport
 * providers directly. Existing security-aware services remain the mutation
 * owners; the V2 provider is used directly only for folder-scoped incoming
 * attachment reads and receive-sync after explicit promotion.
 */
final class V5_Mail_Application_Service {
    /** @param array<string,mixed> $cutover_decision */
    public function __construct(
        private readonly Mail_V2_Lifecycle_Provider $provider,
        private readonly Mailbox_Access $access,
        private readonly Mail_Read_Service $reads,
        private readonly Mail_Draft_Service $drafts,
        private readonly Mail_Folder_Service $folders,
        private readonly Mail_Delivery_Service $delivery,
        private readonly array $cutover_decision
    ) {}

    /** @return array<string,mixed>|\WP_Error */
    public function folders( string $mailbox_id ): array|\WP_Error {
        $gate = $this->gate( 'folders_read' );
        return is_wp_error( $gate ) ? $gate : $this->reads->folders( $mailbox_id );
    }

    /** @param array<string,mixed> $input @return array<string,mixed>|\WP_Error */
    public function messages( string $mailbox_id, string $folder_id, array $input = array() ): array|\WP_Error {
        $gate = $this->gate( 'messages_read' );
        return is_wp_error( $gate ) ? $gate : $this->reads->messages( $mailbox_id, $folder_id, $input );
    }

    /** @return array<string,mixed>|\WP_Error */
    public function message( string $mailbox_id, string $folder_id, string $message_id ): array|\WP_Error {
        $gate = $this->gate( 'message_read' );
        if ( is_wp_error( $gate ) ) {
            return $gate;
        }
        $scoped = self::scoped_message_id( $folder_id, $message_id );
        return is_wp_error( $scoped ) ? $scoped : $this->reads->message( $mailbox_id, $scoped );
    }

    /** @return array<string,mixed>|\WP_Error */
    public function incoming_attachment( string $mailbox_id, string $folder_id, string $message_id, string $attachment_id ): array|\WP_Error {
        $gate = $this->gate( 'attachment_read' );
        if ( is_wp_error( $gate ) ) {
            return $gate;
        }
        $context = $this->authorize_read_object( $mailbox_id, $folder_id, $message_id );
        if ( is_wp_error( $context ) ) {
            return $context;
        }
        $attachment_id = self::opaque_id( $attachment_id, 128 );
        if ( '' === $attachment_id ) {
            return new \WP_Error( 'mvm_mail_v2_attachment_invalid', 'Ongeldige bijlageverwijzing.' );
        }
        return $this->provider->fetch_attachment(
            $context['mailboxId'],
            $context['folderId'],
            $context['messageId'],
            $attachment_id
        );
    }

    /** @return array<string,mixed>|\WP_Error */
    public function initial_sync( string $mailbox_id, string $folder_id, int $limit = 100 ): array|\WP_Error {
        $gate = $this->gate( 'receive_sync' );
        if ( is_wp_error( $gate ) ) {
            return $gate;
        }
        $context = $this->authorize_mailbox_read( $mailbox_id );
        if ( is_wp_error( $context ) ) {
            return $context;
        }
        $folder_id = self::folder_id( $folder_id );
        if ( '' === $folder_id ) {
            return new \WP_Error( 'mvm_mail_v2_folder_invalid', 'Ongeldige mailboxmap.' );
        }
        return $this->provider->initial_sync( $context['mailboxId'], $folder_id, max( 1, min( 200, $limit ) ) );
    }

    /** @return array<string,mixed>|\WP_Error */
    public function delta_sync( string $mailbox_id, string $folder_id, string $cursor, int $limit = 100 ): array|\WP_Error {
        $gate = $this->gate( 'receive_sync' );
        if ( is_wp_error( $gate ) ) {
            return $gate;
        }
        $context = $this->authorize_mailbox_read( $mailbox_id );
        if ( is_wp_error( $context ) ) {
            return $context;
        }
        $folder_id = self::folder_id( $folder_id );
        $cursor    = trim( $cursor );
        if ( '' === $folder_id || '' === $cursor || strlen( $cursor ) > 512 ) {
            return new \WP_Error( 'mvm_mail_v2_sync_context', 'Ongeldige synchronisatiecontext.' );
        }
        return $this->provider->delta_sync( $context['mailboxId'], $folder_id, $cursor, max( 1, min( 200, $limit ) ) );
    }

    /** @param array<string,mixed> $input @return array<string,mixed>|\WP_Error */
    public function create_draft( array $input ): array|\WP_Error {
        $gate = $this->gate( 'draft_create' );
        return is_wp_error( $gate ) ? $gate : $this->drafts->create( $input );
    }

    /** @param array<string,mixed> $input @return array<string,mixed>|\WP_Error */
    public function update_draft( string $draft_id, array $input, int $expected_version ): array|\WP_Error {
        $gate = $this->gate( 'draft_update' );
        return is_wp_error( $gate ) ? $gate : $this->drafts->update( $draft_id, $input, $expected_version );
    }

    public function delete_draft( string $draft_id ): true|\WP_Error {
        $gate = $this->gate( 'draft_delete' );
        return is_wp_error( $gate ) ? $gate : $this->drafts->delete( $draft_id );
    }

    /** @return array<string,mixed>|\WP_Error */
    public function create_folder( string $mailbox_id, string $name, ?string $parent_id = null ): array|\WP_Error {
        $gate = $this->gate( 'folder_create' );
        return is_wp_error( $gate ) ? $gate : $this->folders->create_folder( $mailbox_id, $name, $parent_id );
    }

    public function rename_folder( string $mailbox_id, string $folder_id, string $name ): true|\WP_Error {
        $gate = $this->gate( 'folder_rename' );
        return is_wp_error( $gate ) ? $gate : $this->folders->rename_folder( $mailbox_id, $folder_id, $name );
    }

    public function delete_folder( string $mailbox_id, string $folder_id ): true|\WP_Error {
        $gate = $this->gate( 'folder_delete' );
        return is_wp_error( $gate ) ? $gate : $this->folders->delete_folder( $mailbox_id, $folder_id );
    }

    public function move_message( string $mailbox_id, string $folder_id, string $message_id, string $target_folder_id ): true|\WP_Error {
        $gate = $this->gate( 'message_move' );
        if ( is_wp_error( $gate ) ) {
            return $gate;
        }
        $scoped = self::scoped_message_id( $folder_id, $message_id );
        return is_wp_error( $scoped ) ? $scoped : $this->folders->move_message( $mailbox_id, $scoped, $target_folder_id );
    }

    public function set_read_state( string $mailbox_id, string $folder_id, string $message_id, bool $is_read ): true|\WP_Error {
        $gate = $this->gate( 'message_read_state' );
        if ( is_wp_error( $gate ) ) {
            return $gate;
        }
        $scoped = self::scoped_message_id( $folder_id, $message_id );
        return is_wp_error( $scoped ) ? $scoped : $this->folders->set_read_state( $mailbox_id, $scoped, $is_read );
    }

    public function set_flagged_state( string $mailbox_id, string $folder_id, string $message_id, bool $is_flagged ): true|\WP_Error {
        $gate = $this->gate( 'message_flag_state' );
        if ( is_wp_error( $gate ) ) {
            return $gate;
        }
        $scoped = self::scoped_message_id( $folder_id, $message_id );
        return is_wp_error( $scoped ) ? $scoped : $this->folders->set_flagged_state( $mailbox_id, $scoped, $is_flagged );
    }

    /** @param array<string,mixed> $input @return array<string,mixed>|\WP_Error */
    public function deliver( string $mailbox_id, array $input, string $idempotency_key ): array|\WP_Error {
        $gate = $this->gate( 'deliver' );
        return is_wp_error( $gate ) ? $gate : $this->delivery->deliver( $mailbox_id, $input, $idempotency_key );
    }

    /** @return true|\WP_Error */
    private function gate( string $operation ): true|\WP_Error {
        $decision = V5_Mail_Operation_Gate::evaluate( $operation, $this->cutover_decision );
        if ( true === ( $decision['allowed'] ?? false ) ) {
            return true;
        }
        return new \WP_Error(
            'mvm_mail_v2_operation_blocked',
            'Deze Mail V2-actie is nog niet vrijgegeven voor productie.',
            array(
                'operation' => sanitize_key( $operation ),
                'reason'    => sanitize_key( (string) ( $decision['reason'] ?? 'blocked' ) ),
                'mode'      => 'read_only',
            )
        );
    }

    /** @return array{userId:int,mailboxId:string}|\WP_Error */
    private function authorize_mailbox_read( string $mailbox_id ): array|\WP_Error {
        if ( ! is_user_logged_in() || ! Capabilities::can_read_mail() ) {
            return new \WP_Error( 'mvm_mail_v2_read_forbidden', 'Geen toegang tot deze mailbox.' );
        }
        $mailbox_id = self::opaque_id( $mailbox_id, 128 );
        $user_id    = get_current_user_id();
        if ( '' === $mailbox_id || $user_id <= 0 ) {
            return new \WP_Error( 'mvm_mail_v2_read_forbidden', 'Geen toegang tot deze mailbox.' );
        }
        $allowed = $this->access->authorize_read( $user_id, $mailbox_id );
        return is_wp_error( $allowed ) ? $allowed : array( 'userId' => $user_id, 'mailboxId' => $mailbox_id );
    }

    /** @return array{userId:int,mailboxId:string,folderId:string,messageId:string}|\WP_Error */
    private function authorize_read_object( string $mailbox_id, string $folder_id, string $message_id ): array|\WP_Error {
        $mailbox = $this->authorize_mailbox_read( $mailbox_id );
        if ( is_wp_error( $mailbox ) ) {
            return $mailbox;
        }
        $scoped = self::scoped_message_id( $folder_id, $message_id );
        if ( is_wp_error( $scoped ) ) {
            return $scoped;
        }
        $allowed = $this->access->authorize_message( $mailbox['userId'], $mailbox['mailboxId'], $scoped );
        if ( is_wp_error( $allowed ) ) {
            return $allowed;
        }
        return array(
            'userId'    => $mailbox['userId'],
            'mailboxId' => $mailbox['mailboxId'],
            'folderId'  => self::folder_id( $folder_id ),
            'messageId' => $scoped,
        );
    }

    private static function folder_id( string $value ): string {
        $value = trim( $value );
        return strlen( $value ) <= 128 && 1 === preg_match( '/^[A-Za-z0-9_-]+$/', $value ) ? $value : '';
    }

    /** @return string|\WP_Error */
    private static function scoped_message_id( string $folder_id, string $message_id ): string|\WP_Error {
        $folder_id  = self::folder_id( $folder_id );
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

    private static function opaque_id( string $value, int $max ): string {
        $value = trim( $value );
        return strlen( $value ) <= $max && 1 === preg_match( '/^[A-Za-z0-9._:-]+$/', $value ) ? $value : '';
    }
}
