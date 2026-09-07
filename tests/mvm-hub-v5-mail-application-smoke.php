<?php

namespace {
    define( 'ABSPATH', __DIR__ . '/' );

    class WP_Error {
        public function __construct(
            private string $code,
            private string $message = '',
            private mixed $data = null
        ) {}
        public function get_error_code(): string { return $this->code; }
        public function get_error_data(): mixed { return $this->data; }
    }

    function is_wp_error( mixed $value ): bool { return $value instanceof WP_Error; }
    function sanitize_key( string $value ): string { return strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', $value ) ?? '' ); }
    function is_user_logged_in(): bool { return true; }
    function get_current_user_id(): int { return 7; }
}

namespace MVM\Hub\Core {
    final class Capabilities {
        public static function can_read_mail(): bool { return true; }
    }
}

namespace MVM\Hub\Integrations\Mail {
    interface Mailbox_Access {
        public function authorize_read( int $user_id, string $mailbox_id ): true|\WP_Error;
        public function authorize_compose( int $user_id, string $mailbox_id ): true|\WP_Error;
        public function authorize_manage_folders( int $user_id, string $mailbox_id ): true|\WP_Error;
        public function authorize_message( int $user_id, string $mailbox_id, string $message_id ): true|\WP_Error;
    }

    interface Mail_V2_Lifecycle_Provider {
        public function fetch_attachment( string $mailbox_id, string $folder_id, string $message_id, string $attachment_id ): array|\WP_Error;
        public function initial_sync( string $mailbox_id, string $folder_id, int $limit = 100 ): array|\WP_Error;
        public function delta_sync( string $mailbox_id, string $folder_id, string $cursor, int $limit = 100 ): array|\WP_Error;
    }

    final class Test_Access implements Mailbox_Access {
        public array $calls = array();
        public function authorize_read( int $user_id, string $mailbox_id ): true|\WP_Error { $this->calls[] = array( 'read', $user_id, $mailbox_id ); return true; }
        public function authorize_compose( int $user_id, string $mailbox_id ): true|\WP_Error { return true; }
        public function authorize_manage_folders( int $user_id, string $mailbox_id ): true|\WP_Error { return true; }
        public function authorize_message( int $user_id, string $mailbox_id, string $message_id ): true|\WP_Error { $this->calls[] = array( 'message', $user_id, $mailbox_id, $message_id ); return true; }
    }

    final class Test_Provider implements Mail_V2_Lifecycle_Provider {
        public int $sync_calls = 0;
        public int $attachment_calls = 0;

        public function fetch_attachment( string $mailbox_id, string $folder_id, string $message_id, string $attachment_id ): array|\WP_Error {
            $this->attachment_calls++;
            return array( 'mailboxId' => $mailbox_id, 'folderId' => $folder_id, 'messageId' => $message_id, 'attachmentId' => $attachment_id );
        }

        public function initial_sync( string $mailbox_id, string $folder_id, int $limit = 100 ): array|\WP_Error {
            $this->sync_calls++;
            return array( 'kind' => 'initial', 'limit' => $limit );
        }

        public function delta_sync( string $mailbox_id, string $folder_id, string $cursor, int $limit = 100 ): array|\WP_Error {
            $this->sync_calls++;
            return array( 'kind' => 'delta', 'cursor' => $cursor, 'limit' => $limit );
        }
    }
}

namespace MVM\Hub\Modules\Communications {
    final class Mail_Read_Service {
        public array $calls = array();
        public function folders( string $mailbox_id ): array|\WP_Error { $this->calls[] = array( 'folders', $mailbox_id ); return array( 'folders' => array() ); }
        public function messages( string $mailbox_id, string $folder_id, array $input = array() ): array|\WP_Error { $this->calls[] = array( 'messages', $mailbox_id, $folder_id ); return array( 'messages' => array() ); }
        public function message( string $mailbox_id, string $message_id ): array|\WP_Error { $this->calls[] = array( 'message', $mailbox_id, $message_id ); return array( 'id' => $message_id ); }
    }

    final class Mail_Draft_Service {
        public int $calls = 0;
        public function create( array $input ): array|\WP_Error { $this->calls++; return array( 'id' => 'draft-1' ); }
        public function update( string $draft_id, array $input, int $expected_version ): array|\WP_Error { $this->calls++; return array( 'id' => $draft_id ); }
        public function delete( string $draft_id ): true|\WP_Error { $this->calls++; return true; }
    }

    final class Mail_Folder_Service {
        public int $calls = 0;
        public function create_folder( string $mailbox_id, string $name, ?string $parent_id = null ): array|\WP_Error { $this->calls++; return array( 'id' => 'folder-1' ); }
        public function rename_folder( string $mailbox_id, string $folder_id, string $name ): true|\WP_Error { $this->calls++; return true; }
        public function delete_folder( string $mailbox_id, string $folder_id ): true|\WP_Error { $this->calls++; return true; }
        public function move_message( string $mailbox_id, string $message_id, string $folder_id ): true|\WP_Error { $this->calls++; return true; }
        public function set_read_state( string $mailbox_id, string $message_id, bool $is_read ): true|\WP_Error { $this->calls++; return true; }
        public function set_flagged_state( string $mailbox_id, string $message_id, bool $is_flagged ): true|\WP_Error { $this->calls++; return true; }
    }

    final class Mail_Delivery_Service {
        public int $calls = 0;
        public function deliver( string $mailbox_id, array $input, string $idempotency_key ): array|\WP_Error { $this->calls++; return array( 'delivered' => true ); }
    }
}

namespace {
    require_once __DIR__ . '/../plugins/mvm-hub/modules/communications/class-v5-mail-operation-gate.php';
    require_once __DIR__ . '/../plugins/mvm-hub/modules/communications/class-v5-mail-application-service.php';

    use MVM\Hub\Integrations\Mail\Test_Access;
    use MVM\Hub\Integrations\Mail\Test_Provider;
    use MVM\Hub\Modules\Communications\Mail_Delivery_Service;
    use MVM\Hub\Modules\Communications\Mail_Draft_Service;
    use MVM\Hub\Modules\Communications\Mail_Folder_Service;
    use MVM\Hub\Modules\Communications\Mail_Read_Service;
    use MVM\Hub\Modules\Communications\V5_Mail_Application_Service;
    use MVM\Hub\Modules\Communications\V5_Mail_Operation_Gate;

    function mvm_mail_app_assert( bool $condition, string $message ): void {
        if ( ! $condition ) {
            fwrite( STDERR, "FAIL: {$message}\n" );
            exit( 1 );
        }
    }

    $read_only = array(
        'canPromoteReceiveSync'   => false,
        'canPromoteSendRoute'     => false,
        'canPromoteBidirectional' => false,
    );

    $provider = new Test_Provider();
    $access   = new Test_Access();
    $reads    = new Mail_Read_Service();
    $drafts   = new Mail_Draft_Service();
    $folders  = new Mail_Folder_Service();
    $delivery = new Mail_Delivery_Service();
    $service  = new V5_Mail_Application_Service( $provider, $access, $reads, $drafts, $folders, $delivery, $read_only );

    mvm_mail_app_assert( true === V5_Mail_Operation_Gate::evaluate( 'folders_read', $read_only )['allowed'], 'read operations must remain available in read-only mode' );
    mvm_mail_app_assert( false === V5_Mail_Operation_Gate::evaluate( 'receive_sync', $read_only )['allowed'], 'receive sync must be blocked before promotion' );
    mvm_mail_app_assert( false === V5_Mail_Operation_Gate::evaluate( 'deliver', $read_only )['allowed'], 'delivery must be blocked before promotion' );
    mvm_mail_app_assert( false === V5_Mail_Operation_Gate::evaluate( 'folder_create', $read_only )['allowed'], 'mail mutations must be blocked before bidirectional promotion' );
    foreach ( array( 'message_pin_state', 'message_archive', 'message_trash', 'message_spam', 'message_report', 'sender_block_state' ) as $operation ) {
        mvm_mail_app_assert( false === V5_Mail_Operation_Gate::evaluate( $operation, $read_only )['allowed'], $operation . ' must remain blocked before bidirectional promotion' );
    }

    $folders_result = $service->folders( 'editorial' );
    mvm_mail_app_assert( ! is_wp_error( $folders_result ), 'read-only application service should allow folders read' );

    $message = $service->message( 'editorial', 'SU5CT1g', 'SU5CT1g.42' );
    mvm_mail_app_assert( ! is_wp_error( $message ) && 'SU5CT1g.42' === $message['id'], 'message read must preserve strict folder-scoped identity' );

    $mismatch = $service->message( 'editorial', 'SU5CT1g', 'T3RoZXI.42' );
    mvm_mail_app_assert( is_wp_error( $mismatch ) && 'mvm_mail_v2_message_reference' === $mismatch->get_error_code(), 'folder/message mismatch must fail closed' );

    $attachment = $service->incoming_attachment( 'editorial', 'SU5CT1g', 'SU5CT1g.42', 'part-1' );
    mvm_mail_app_assert( ! is_wp_error( $attachment ), 'authorized incoming attachment read should be allowed' );
    mvm_mail_app_assert( 1 === $provider->attachment_calls, 'attachment transport must be reached only after object authorization' );
    mvm_mail_app_assert( 2 === count( $access->calls ), 'attachment read must perform mailbox and message authorization' );

    $sync_blocked = $service->initial_sync( 'editorial', 'SU5CT1g', 100 );
    mvm_mail_app_assert( is_wp_error( $sync_blocked ) && 'mvm_mail_v2_operation_blocked' === $sync_blocked->get_error_code(), 'receive sync must remain blocked in current production mode' );
    mvm_mail_app_assert( 0 === $provider->sync_calls, 'blocked receive sync must not touch provider state' );

    $send_blocked = $service->deliver( 'editorial', array( 'to' => array( 'x@example.test' ) ), 'idem-1' );
    mvm_mail_app_assert( is_wp_error( $send_blocked ), 'delivery must remain blocked before cutover' );
    mvm_mail_app_assert( 0 === $delivery->calls, 'blocked delivery must not reach secure delivery service' );

    $promoted = array(
        'canPromoteReceiveSync'   => true,
        'canPromoteSendRoute'     => true,
        'canPromoteBidirectional' => true,
    );
    $service_promoted = new V5_Mail_Application_Service( $provider, $access, $reads, $drafts, $folders, $delivery, $promoted );
    foreach ( array( 'message_pin_state', 'message_archive', 'message_trash', 'message_spam', 'message_report', 'sender_block_state' ) as $operation ) {
        mvm_mail_app_assert( true === V5_Mail_Operation_Gate::evaluate( $operation, $promoted )['allowed'], $operation . ' may open only after bidirectional promotion' );
    }

    $sync = $service_promoted->initial_sync( 'editorial', 'SU5CT1g', 500 );
    mvm_mail_app_assert( ! is_wp_error( $sync ) && 200 === $sync['limit'], 'promoted receive sync should call provider with bounded limit' );

    $created = $service_promoted->create_folder( 'editorial', 'Archief' );
    mvm_mail_app_assert( ! is_wp_error( $created ) && 1 === $folders->calls, 'promoted folder mutation must delegate to security-aware folder service' );

    $sent = $service_promoted->deliver( 'editorial', array( 'to' => array( 'x@example.test' ) ), 'idem-2' );
    mvm_mail_app_assert( ! is_wp_error( $sent ) && 1 === $delivery->calls, 'promoted delivery must delegate to secure delivery service' );

    echo "OK: Mail V2 application orchestration smoke passed\n";
}
