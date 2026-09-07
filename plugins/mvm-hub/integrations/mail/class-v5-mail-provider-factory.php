<?php

namespace MVM\Hub\Integrations\Mail;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Dormant construction boundary for the Mail V2 transport stack.
 *
 * This factory has no hooks/routes and is intentionally not loaded by the
 * alpha5 bootstrap. Constructing the provider cannot promote receive-sync or
 * outbound delivery; those remain subject to mailbox ACL, step-up and cutover.
 */
final class V5_Mail_Provider_Factory {
    /** @return Mail_V2_Lifecycle_Provider|\WP_Error */
    public static function build(): Mail_V2_Lifecycle_Provider|\WP_Error {
        if ( ! self::imap_configured() ) {
            return new \WP_Error( 'mvm_mail_v2_imap_unavailable', 'Mail V2 IMAP is niet gereed.' );
        }

        $legacy      = new Legacy_Readonly_Mail_Provider();
        $smtp        = new Dedicated_SMTP_Mail_Provider( $legacy );
        $imap        = new Dedicated_IMAP_SMTP_Mail_Provider( $smtp );
        $foldered    = new Folder_Scoped_IMAP_Mail_Provider( $imap );
        $state_store = new Option_Mail_Sync_State_Store();
        $sync        = new V5_IMAP_Mail_Sync_Provider( $state_store );
        $attachments = new V5_IMAP_Attachment_Reader();

        return new V5_Folder_Scoped_Mail_Provider_Adapter(
            $foldered,
            $sync,
            $attachments
        );
    }

    /** @return array<string,mixed> */
    public static function readiness( string $mailbox_id ): array {
        $provider = self::build();
        if ( is_wp_error( $provider ) ) {
            return array(
                'available'          => false,
                'receiveReady'       => false,
                'sendReady'          => false,
                'lifecycleReady'     => false,
                'bidirectionalReady' => false,
                'reason'             => $provider->get_error_code(),
            );
        }

        $mail = $provider->capabilities( $mailbox_id );
        $sync = $provider->sync_capabilities( $mailbox_id );

        $receive_required = array(
            'read',
            'folders',
            'attachments',
            'flags',
            'move',
            'folderScopedRead',
            'folderScopedMutations',
            'attachmentFetch',
        );
        $lifecycle_required = array( 'folderLifecycle', 'draftLifecycle' );
        $sync_required      = array( 'initial', 'delta', 'deletions', 'cursorReset' );

        $receive   = self::all_true( $mail, $receive_required ) && self::all_true( $sync, $sync_required );
        $send      = true === ( $mail['send'] ?? false );
        $lifecycle = self::all_true( $mail, $lifecycle_required );

        return array(
            'available'          => true,
            'receiveReady'       => $receive,
            'sendReady'          => $send,
            'lifecycleReady'     => $lifecycle,
            'bidirectionalReady' => $receive && $send && $lifecycle,
            'mailCapabilities'   => self::project(
                $mail,
                array_merge( $receive_required, $lifecycle_required, array( 'send' ) )
            ),
            'syncCapabilities'   => self::project( $sync, $sync_required ),
            'productionPromoted' => false,
            'requiresCutover'    => true,
        );
    }

    /** @param array<string,bool|int|string> $values @param list<string> $required */
    private static function all_true( array $values, array $required ): bool {
        foreach ( $required as $key ) {
            if ( true !== ( $values[ $key ] ?? false ) ) {
                return false;
            }
        }
        return true;
    }

    /** @param array<string,bool|int|string> $values @param list<string> $keys @return array<string,bool> */
    private static function project( array $values, array $keys ): array {
        $out = array();
        foreach ( $keys as $key ) {
            $out[ $key ] = true === ( $values[ $key ] ?? false );
        }
        return $out;
    }

    private static function imap_configured(): bool {
        if ( ! function_exists( 'imap_open' ) ) {
            return false;
        }
        foreach ( array( 'MVM_HUB_IMAP_HOST', 'MVM_HUB_IMAP_PORT', 'MVM_HUB_IMAP_USERNAME', 'MVM_HUB_IMAP_PASSWORD' ) as $constant ) {
            if ( ! defined( $constant ) || '' === trim( (string) constant( $constant ) ) ) {
                return false;
            }
        }
        return true;
    }

    private function __construct() {}
}
