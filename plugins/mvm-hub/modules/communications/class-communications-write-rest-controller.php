<?php

namespace MVM\Hub\Modules\Communications;

use MVM\Hub\Core\Capabilities;
use MVM\Hub\Core\Communications_Policy;
use MVM\Hub\Core\Runtime_Gates;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Nonce-protected feature-gated write API for Communications.
 * Services always re-check capability, session and object authorization.
 */
final class Communications_Write_REST_Controller {
    private const NAMESPACE = 'mvm-hub/v1';

    public function register_routes(): void {
        if ( ! Runtime_Gates::communications_writes_enabled() ) {
            return;
        }

        register_rest_route( self::NAMESPACE, '/communications/mail/drafts', array(
            array( 'methods' => \WP_REST_Server::READABLE, 'callback' => array( $this, 'drafts' ), 'permission_callback' => array( $this, 'can_write_mail' ), 'args' => self::pagination_args() ),
            array( 'methods' => \WP_REST_Server::CREATABLE, 'callback' => array( $this, 'create_draft' ), 'permission_callback' => array( $this, 'can_write_mail' ) ),
        ) );
        register_rest_route( self::NAMESPACE, '/communications/mail/drafts/(?P<id>[A-Za-z0-9._:-]+)', array(
            array( 'methods' => \WP_REST_Server::READABLE, 'callback' => array( $this, 'get_draft' ), 'permission_callback' => array( $this, 'can_write_mail' ) ),
            array( 'methods' => \WP_REST_Server::EDITABLE, 'callback' => array( $this, 'update_draft' ), 'permission_callback' => array( $this, 'can_write_mail' ) ),
            array( 'methods' => \WP_REST_Server::DELETABLE, 'callback' => array( $this, 'delete_draft' ), 'permission_callback' => array( $this, 'can_write_mail' ) ),
        ) );
        register_rest_route( self::NAMESPACE, '/communications/mail/drafts/(?P<id>[A-Za-z0-9._:-]+)/attachments', array(
            'methods' => \WP_REST_Server::CREATABLE, 'callback' => array( $this, 'upload_attachment' ), 'permission_callback' => array( $this, 'can_manage_attachments' ),
        ) );
        register_rest_route( self::NAMESPACE, '/communications/mail/drafts/(?P<id>[A-Za-z0-9._:-]+)/attachments/(?P<attachment>[A-Za-z0-9._:-]+)', array(
            'methods' => \WP_REST_Server::DELETABLE, 'callback' => array( $this, 'delete_attachment' ), 'permission_callback' => array( $this, 'can_manage_attachments' ),
        ) );

        register_rest_route( self::NAMESPACE, '/communications/mail/folders', array(
            'methods' => \WP_REST_Server::CREATABLE, 'callback' => array( $this, 'create_folder' ), 'permission_callback' => array( $this, 'can_manage_folders' ),
        ) );
        register_rest_route( self::NAMESPACE, '/communications/mail/folders/(?P<id>[A-Za-z0-9._:-]+)', array(
            array( 'methods' => \WP_REST_Server::EDITABLE, 'callback' => array( $this, 'rename_folder' ), 'permission_callback' => array( $this, 'can_manage_folders' ) ),
            array( 'methods' => \WP_REST_Server::DELETABLE, 'callback' => array( $this, 'delete_folder' ), 'permission_callback' => array( $this, 'can_manage_folders' ) ),
        ) );
        foreach ( array( 'move', 'read-state', 'flag-state' ) as $action ) {
            register_rest_route( self::NAMESPACE, '/communications/mail/messages/(?P<id>[A-Za-z0-9._:-]+)/' . $action, array(
                'methods' => \WP_REST_Server::CREATABLE,
                'callback' => array( $this, str_replace( '-', '_', $action ) ),
                'permission_callback' => array( $this, 'can_manage_messages' ),
            ) );
        }
        foreach ( array( 'archive', 'trash', 'spam' ) as $action ) {
            register_rest_route( self::NAMESPACE, '/communications/mail/messages/(?P<id>[A-Za-z0-9._:-]+)/' . $action, array(
                'methods' => \WP_REST_Server::CREATABLE,
                'callback' => array( $this, $action ),
                'permission_callback' => array( $this, 'can_manage_messages' ),
            ) );
        }
        register_rest_route( self::NAMESPACE, '/communications/mail/messages/(?P<id>[A-Za-z0-9._:-]+)/pin-state', array(
            'methods' => \WP_REST_Server::CREATABLE, 'callback' => array( $this, 'pin_state' ), 'permission_callback' => array( $this, 'can_manage_messages' ),
        ) );
        register_rest_route( self::NAMESPACE, '/communications/mail/messages/(?P<id>[A-Za-z0-9._:-]+)/report', array(
            'methods' => \WP_REST_Server::CREATABLE, 'callback' => array( $this, 'report_mail' ), 'permission_callback' => array( $this, 'can_manage_messages' ),
        ) );
        register_rest_route( self::NAMESPACE, '/communications/mail/messages/(?P<id>[A-Za-z0-9._:-]+)/block-sender', array(
            'methods' => \WP_REST_Server::CREATABLE, 'callback' => array( $this, 'block_sender' ), 'permission_callback' => array( $this, 'can_manage_messages' ),
        ) );
        register_rest_route( self::NAMESPACE, '/communications/mail/messages/bulk', array(
            'methods' => \WP_REST_Server::CREATABLE, 'callback' => array( $this, 'bulk_messages' ), 'permission_callback' => array( $this, 'can_manage_messages' ),
        ) );

        register_rest_route( self::NAMESPACE, '/communications/messages', array(
            'methods' => \WP_REST_Server::CREATABLE, 'callback' => array( $this, 'create_internal_message' ), 'permission_callback' => array( $this, 'can_send_internal' ),
        ) );
        register_rest_route( self::NAMESPACE, '/communications/messages/(?P<id>\d+)/reply', array(
            'methods' => \WP_REST_Server::CREATABLE, 'callback' => array( $this, 'reply_internal_message' ), 'permission_callback' => array( $this, 'can_send_internal' ),
        ) );
        register_rest_route( self::NAMESPACE, '/communications/messages/(?P<id>\d+)/read', array(
            'methods' => \WP_REST_Server::CREATABLE, 'callback' => array( $this, 'read_internal_message' ), 'permission_callback' => array( $this, 'can_manage_internal' ),
        ) );
        register_rest_route( self::NAMESPACE, '/communications/messages/(?P<id>\d+)/archive', array(
            'methods' => \WP_REST_Server::CREATABLE, 'callback' => array( $this, 'archive_internal_message' ), 'permission_callback' => array( $this, 'can_manage_internal' ),
        ) );
        register_rest_route( self::NAMESPACE, '/communications/messages/(?P<id>\d+)', array(
            'methods' => \WP_REST_Server::DELETABLE, 'callback' => array( $this, 'delete_internal_message' ), 'permission_callback' => array( $this, 'can_manage_internal' ),
        ) );

        if ( Runtime_Gates::mail_writes_enabled() ) {
            register_rest_route( self::NAMESPACE, '/communications/mail/send', array(
                'methods' => \WP_REST_Server::CREATABLE, 'callback' => array( $this, 'send_mail' ), 'permission_callback' => array( $this, 'can_send_mail' ),
            ) );
        }
    }

    public function can_write_mail( \WP_REST_Request $request ): bool|\WP_Error { return $this->authorize( $request, Capabilities::MAIL_COMPOSE ); }
    public function can_manage_attachments( \WP_REST_Request $request ): bool|\WP_Error { return $this->authorize( $request, Capabilities::MAIL_MANAGE_OWN_ATTACHMENTS ); }
    public function can_manage_folders( \WP_REST_Request $request ): bool|\WP_Error { return $this->authorize( $request, Capabilities::MAIL_MANAGE_FOLDERS ); }
    public function can_manage_messages( \WP_REST_Request $request ): bool|\WP_Error { return $this->authorize( $request, Capabilities::MAIL_MANAGE_MESSAGES ); }
    public function can_send_internal( \WP_REST_Request $request ): bool|\WP_Error { return $this->authorize( $request, Capabilities::MESSAGES_SEND, false ); }
    public function can_manage_internal( \WP_REST_Request $request ): bool|\WP_Error { return $this->authorize( $request, Capabilities::MESSAGES_MANAGE_OWN, false ); }

    public function can_send_mail( \WP_REST_Request $request ): bool|\WP_Error {
        if ( ! Runtime_Gates::mail_writes_enabled() ) {
            return new \WP_Error( 'mvm_mail_delivery_disabled', 'Externe e-mailverzending is niet vrijgegeven.', array( 'status' => 403 ) );
        }
        return $this->authorize( $request, Capabilities::MAIL_SEND );
    }

    public function drafts( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
        return self::response( Communications_Service_Factory::draft_service()->list( (int) ( $request->get_param( 'page' ) ?: 1 ), (int) ( $request->get_param( 'per_page' ) ?: 20 ) ) );
    }
    public function create_draft( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error { return self::response( Communications_Service_Factory::draft_service()->create( self::json( $request ) ) ); }
    public function get_draft( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error { return self::response( Communications_Service_Factory::draft_service()->get( (string) $request->get_param( 'id' ) ) ); }
    public function update_draft( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
        $input = self::json( $request );
        $version = max( 0, (int) ( $input['expectedVersion'] ?? 0 ) );
        unset( $input['expectedVersion'] );
        return self::response( Communications_Service_Factory::draft_service()->update( (string) $request->get_param( 'id' ), $input, $version ) );
    }
    public function delete_draft( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error { return self::response( Communications_Service_Factory::draft_service()->delete( (string) $request->get_param( 'id' ) ) ); }

    public function upload_attachment( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
        $files = $request->get_file_params();
        $file = isset( $files['file'] ) && is_array( $files['file'] ) ? $files['file'] : null;
        if ( null === $file || UPLOAD_ERR_OK !== (int) ( $file['error'] ?? UPLOAD_ERR_NO_FILE ) ) {
            return new \WP_Error( 'mvm_mail_upload_missing', 'Kies één geldig bestand.', array( 'status' => 400 ) );
        }
        $size = max( 0, (int) ( $file['size'] ?? 0 ) );
        if ( $size <= 0 || $size > Communications_Policy::max_attachment_bytes() ) {
            return new \WP_Error( 'mvm_mail_upload_size', 'Het bestand is te groot of leeg.', array( 'status' => 400 ) );
        }
        return self::response( Communications_Service_Factory::attachment_service()->upload( (string) $request->get_param( 'id' ), $file ) );
    }
    public function delete_attachment( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
        return self::response( Communications_Service_Factory::draft_service()->delete_attachment( (string) $request->get_param( 'id' ), (string) $request->get_param( 'attachment' ) ) );
    }

    public function create_folder( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
        $input = self::json( $request );
        return self::response( Communications_Service_Factory::folder_service()->create_folder( self::mailbox_id(), (string) ( $input['name'] ?? '' ), isset( $input['parentId'] ) ? (string) $input['parentId'] : null ) );
    }
    public function rename_folder( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
        $input = self::json( $request );
        return self::response( Communications_Service_Factory::folder_service()->rename_folder( self::mailbox_id(), (string) $request->get_param( 'id' ), (string) ( $input['name'] ?? '' ) ) );
    }
    public function delete_folder( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error { return self::response( Communications_Service_Factory::folder_service()->delete_folder( self::mailbox_id(), (string) $request->get_param( 'id' ) ) ); }
    public function move( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
        $input = self::json( $request );
        return self::response( Communications_Service_Factory::folder_service()->move_message( self::mailbox_id(), (string) $request->get_param( 'id' ), (string) ( $input['folderId'] ?? '' ) ) );
    }
    public function read_state( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
        $input = self::json( $request );
        return self::response( Communications_Service_Factory::folder_service()->set_read_state( self::mailbox_id(), (string) $request->get_param( 'id' ), true === ( $input['read'] ?? false ) ) );
    }
    public function flag_state( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
        $input = self::json( $request );
        return self::response( Communications_Service_Factory::folder_service()->set_flagged_state( self::mailbox_id(), (string) $request->get_param( 'id' ), true === ( $input['flagged'] ?? false ) ) );
    }
    public function pin_state( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
        $input = self::json( $request );
        return self::response( Communications_Service_Factory::folder_service()->set_pinned_state( self::mailbox_id(), (string) $request->get_param( 'id' ), true === ( $input['pinned'] ?? false ) ) );
    }
    public function archive( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error { return self::response( Communications_Service_Factory::folder_service()->move_message_to_special( self::mailbox_id(), (string) $request->get_param( 'id' ), 'archive' ) ); }
    public function trash( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error { return self::response( Communications_Service_Factory::folder_service()->move_message_to_special( self::mailbox_id(), (string) $request->get_param( 'id' ), 'trash' ) ); }
    public function spam( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error { return self::response( Communications_Service_Factory::folder_service()->move_message_to_special( self::mailbox_id(), (string) $request->get_param( 'id' ), 'junk' ) ); }
    public function report_mail( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
        $input = self::json( $request );
        return self::response( Communications_Service_Factory::folder_service()->report_message( self::mailbox_id(), (string) $request->get_param( 'id' ), (string) ( $input['reason'] ?? '' ) ) );
    }
    public function block_sender( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
        $input = self::json( $request );
        return self::response( Communications_Service_Factory::folder_service()->set_sender_blocked( self::mailbox_id(), (string) $request->get_param( 'id' ), true === ( $input['blocked'] ?? false ) ) );
    }
    public function bulk_messages( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
        $input = self::json( $request );
        $ids = array();
        foreach ( is_array( $input['ids'] ?? null ) ? array_slice( $input['ids'], 0, 100 ) : array() as $candidate ) {
            if ( is_string( $candidate ) && strlen( $candidate ) <= 260 && 1 === preg_match( '/^[A-Za-z0-9._:-]+$/', $candidate ) ) {
                $ids[] = $candidate;
            }
        }
        $ids = array_values( array_unique( $ids ) );
        $action = sanitize_key( (string) ( $input['action'] ?? '' ) );
        if ( array() === $ids || ! in_array( $action, array( 'read', 'unread', 'flag', 'unflag', 'pin', 'unpin', 'move', 'archive', 'trash', 'spam' ), true ) ) {
            return new \WP_Error( 'mvm_mail_bulk_input', 'Kies geldige berichten en een bulkactie.', array( 'status' => 400 ) );
        }
        $service = Communications_Service_Factory::folder_service();
        $processed = 0;
        $failures = array();
        foreach ( $ids as $id ) {
            $result = match ( $action ) {
                'read', 'unread' => $service->set_read_state( self::mailbox_id(), $id, 'read' === $action ),
                'flag', 'unflag' => $service->set_flagged_state( self::mailbox_id(), $id, 'flag' === $action ),
                'pin', 'unpin'   => $service->set_pinned_state( self::mailbox_id(), $id, 'pin' === $action ),
                'move'           => $service->move_message( self::mailbox_id(), $id, (string) ( $input['folderId'] ?? '' ) ),
                'archive'        => $service->move_message_to_special( self::mailbox_id(), $id, 'archive' ),
                'trash'          => $service->move_message_to_special( self::mailbox_id(), $id, 'trash' ),
                'spam'           => $service->move_message_to_special( self::mailbox_id(), $id, 'junk' ),
            };
            if ( is_wp_error( $result ) ) {
                $failures[] = sanitize_key( $result->get_error_code() );
            } else {
                $processed++;
            }
        }
        return self::response( array(
            'processed' => $processed,
            'failed'    => count( $failures ),
            'failureCodes' => array_values( array_unique( array_slice( $failures, 0, 10 ) ) ),
        ) );
    }

    public function send_mail( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
        return self::response( Communications_Service_Factory::delivery_service()->deliver( self::mailbox_id(), self::json( $request ), trim( (string) $request->get_header( 'Idempotency-Key' ) ) ) );
    }

    public function create_internal_message( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
        $input = self::json( $request );
        return self::response( Communications_Service_Factory::internal_message_service()->create( is_array( $input['participants'] ?? null ) ? $input['participants'] : array(), (string) ( $input['body'] ?? '' ) ) );
    }
    public function reply_internal_message( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
        $input = self::json( $request );
        return self::response( Communications_Service_Factory::internal_message_service()->reply( absint( $request->get_param( 'id' ) ), (string) ( $input['body'] ?? '' ) ) );
    }
    public function read_internal_message( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error { return self::response( Communications_Service_Factory::internal_message_service()->mark_read( absint( $request->get_param( 'id' ) ) ) ); }
    public function archive_internal_message( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error { return self::response( Communications_Service_Factory::internal_message_service()->archive( absint( $request->get_param( 'id' ) ) ) ); }
    public function delete_internal_message( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error { return self::response( Communications_Service_Factory::internal_message_service()->delete( absint( $request->get_param( 'id' ) ) ) ); }

    private function authorize( \WP_REST_Request $request, string $capability, bool $mail = true ): bool|\WP_Error {
        if ( ! Runtime_Gates::communications_writes_enabled() || ! is_user_logged_in() || ! Capabilities::can_access_communications() ) {
            return new \WP_Error( 'mvm_communications_write_forbidden', 'Schrijfacties zijn niet beschikbaar.', array( 'status' => 403 ) );
        }
        if ( $mail && ! Capabilities::can_access_mail() ) {
            return new \WP_Error( 'mvm_mail_write_forbidden', 'Geen toegang tot de mailbox.', array( 'status' => 403 ) );
        }
        if ( ! current_user_can( 'manage_options' ) && ! current_user_can( Capabilities::COMMUNICATIONS_ADMIN ) && ! current_user_can( $capability ) ) {
            return new \WP_Error( 'mvm_communications_capability', 'Je hebt geen toestemming voor deze actie.', array( 'status' => 403 ) );
        }
        $nonce = (string) $request->get_header( 'X-WP-Nonce' );
        if ( '' === $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
            return new \WP_Error( 'mvm_communications_nonce', 'De beveiligingstoken is ongeldig of verlopen.', array( 'status' => 403 ) );
        }
        return true;
    }

    /** @return array<string,mixed> */
    private static function json( \WP_REST_Request $request ): array { $input = $request->get_json_params(); return is_array( $input ) ? $input : array(); }
    private static function mailbox_id(): string { return defined( 'MVM_HUB_MAILBOX_ID' ) ? sanitize_key( (string) MVM_HUB_MAILBOX_ID ) : 'editorial'; }
    /** @return array<string,array<string,mixed>> */
    private static function pagination_args(): array {
        return array(
            'page' => array( 'default' => 1, 'sanitize_callback' => 'absint', 'validate_callback' => static fn( mixed $value ): bool => (int) $value >= 1 && (int) $value <= 100 ),
            'per_page' => array( 'default' => 20, 'sanitize_callback' => 'absint', 'validate_callback' => static fn( mixed $value ): bool => (int) $value >= 1 && (int) $value <= 50 ),
        );
    }
    private static function response( mixed $result ): \WP_REST_Response|\WP_Error { return is_wp_error( $result ) ? $result : rest_ensure_response( $result ); }
}
