<?php

namespace MVM\Hub\Modules\Communications;

use MVM\Hub\Core\Runtime_Gates;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Dedicated runtime registrar for Mail mutations.
 *
 * The implementation remains in Communications_Write_REST_Controller so the
 * existing authorization, nonce, idempotency, rate-limit, audit and service
 * boundaries are reused. This wrapper deliberately registers Mail routes only;
 * internal staff messaging remains owned by Internal_Message_Write_REST_Controller.
 */
final class Mail_Write_REST_Controller {
    private const NAMESPACE = 'mvm-hub/v1';

    private Communications_Write_REST_Controller $delegate;

    public function __construct() {
        $this->delegate = new Communications_Write_REST_Controller();
    }

    public function register_routes(): void {
        if ( ! Runtime_Gates::mail_writes_enabled() ) {
            return;
        }

        register_rest_route( self::NAMESPACE, '/communications/mail/drafts', array(
            array( 'methods' => \WP_REST_Server::READABLE, 'callback' => array( $this->delegate, 'drafts' ), 'permission_callback' => array( $this->delegate, 'can_write_mail' ), 'args' => self::pagination_args() ),
            array( 'methods' => \WP_REST_Server::CREATABLE, 'callback' => array( $this->delegate, 'create_draft' ), 'permission_callback' => array( $this->delegate, 'can_write_mail' ) ),
        ) );
        register_rest_route( self::NAMESPACE, '/communications/mail/drafts/(?P<id>[A-Za-z0-9._:-]+)', array(
            array( 'methods' => \WP_REST_Server::READABLE, 'callback' => array( $this->delegate, 'get_draft' ), 'permission_callback' => array( $this->delegate, 'can_write_mail' ) ),
            array( 'methods' => \WP_REST_Server::EDITABLE, 'callback' => array( $this->delegate, 'update_draft' ), 'permission_callback' => array( $this->delegate, 'can_write_mail' ) ),
            array( 'methods' => \WP_REST_Server::DELETABLE, 'callback' => array( $this->delegate, 'delete_draft' ), 'permission_callback' => array( $this->delegate, 'can_write_mail' ) ),
        ) );
        register_rest_route( self::NAMESPACE, '/communications/mail/drafts/(?P<id>[A-Za-z0-9._:-]+)/attachments', array(
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => array( $this->delegate, 'upload_attachment' ),
            'permission_callback' => array( $this->delegate, 'can_manage_attachments' ),
        ) );
        register_rest_route( self::NAMESPACE, '/communications/mail/drafts/(?P<id>[A-Za-z0-9._:-]+)/attachments/(?P<attachment>[A-Za-z0-9._:-]+)', array(
            'methods' => \WP_REST_Server::DELETABLE,
            'callback' => array( $this->delegate, 'delete_attachment' ),
            'permission_callback' => array( $this->delegate, 'can_manage_attachments' ),
        ) );

        register_rest_route( self::NAMESPACE, '/communications/mail/folders', array(
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => array( $this->delegate, 'create_folder' ),
            'permission_callback' => array( $this->delegate, 'can_manage_folders' ),
        ) );
        register_rest_route( self::NAMESPACE, '/communications/mail/folders/(?P<id>[A-Za-z0-9._:-]+)', array(
            array( 'methods' => \WP_REST_Server::EDITABLE, 'callback' => array( $this->delegate, 'rename_folder' ), 'permission_callback' => array( $this->delegate, 'can_manage_folders' ) ),
            array( 'methods' => \WP_REST_Server::DELETABLE, 'callback' => array( $this->delegate, 'delete_folder' ), 'permission_callback' => array( $this->delegate, 'can_manage_folders' ) ),
        ) );
        foreach ( array( 'move', 'read-state', 'flag-state' ) as $action ) {
            register_rest_route( self::NAMESPACE, '/communications/mail/messages/(?P<id>[A-Za-z0-9._:-]+)/' . $action, array(
                'methods' => \WP_REST_Server::CREATABLE,
                'callback' => array( $this->delegate, str_replace( '-', '_', $action ) ),
                'permission_callback' => array( $this->delegate, 'can_manage_messages' ),
            ) );
        }
        foreach ( array( 'archive', 'trash', 'spam' ) as $action ) {
            register_rest_route( self::NAMESPACE, '/communications/mail/messages/(?P<id>[A-Za-z0-9._:-]+)/' . $action, array(
                'methods' => \WP_REST_Server::CREATABLE,
                'callback' => array( $this->delegate, $action ),
                'permission_callback' => array( $this->delegate, 'can_manage_messages' ),
            ) );
        }
        register_rest_route( self::NAMESPACE, '/communications/mail/messages/(?P<id>[A-Za-z0-9._:-]+)/pin-state', array(
            'methods' => \WP_REST_Server::CREATABLE, 'callback' => array( $this->delegate, 'pin_state' ), 'permission_callback' => array( $this->delegate, 'can_manage_messages' ),
        ) );
        register_rest_route( self::NAMESPACE, '/communications/mail/messages/(?P<id>[A-Za-z0-9._:-]+)/report', array(
            'methods' => \WP_REST_Server::CREATABLE, 'callback' => array( $this->delegate, 'report_mail' ), 'permission_callback' => array( $this->delegate, 'can_manage_messages' ),
        ) );
        register_rest_route( self::NAMESPACE, '/communications/mail/messages/(?P<id>[A-Za-z0-9._:-]+)/block-sender', array(
            'methods' => \WP_REST_Server::CREATABLE, 'callback' => array( $this->delegate, 'block_sender' ), 'permission_callback' => array( $this->delegate, 'can_manage_messages' ),
        ) );
        register_rest_route( self::NAMESPACE, '/communications/mail/messages/bulk', array(
            'methods' => \WP_REST_Server::CREATABLE, 'callback' => array( $this->delegate, 'bulk_messages' ), 'permission_callback' => array( $this->delegate, 'can_manage_messages' ),
        ) );

        register_rest_route( self::NAMESPACE, '/communications/mail/send', array(
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => array( $this->delegate, 'send_mail' ),
            'permission_callback' => array( $this->delegate, 'can_send_mail' ),
        ) );
    }

    /** @return array<string,array<string,mixed>> */
    private static function pagination_args(): array {
        return array(
            'page' => array(
                'default' => 1,
                'sanitize_callback' => 'absint',
                'validate_callback' => static fn( mixed $value ): bool => (int) $value >= 1 && (int) $value <= 100,
            ),
            'per_page' => array(
                'default' => 20,
                'sanitize_callback' => 'absint',
                'validate_callback' => static fn( mixed $value ): bool => (int) $value >= 1 && (int) $value <= 100,
            ),
        );
    }
}
