<?php

namespace MVM\Hub\Modules\Communications;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Dormant Mail V2 workspace projection.
 *
 * Input mailbox descriptors must already be scoped to the current user by the
 * mailbox authorization layer. This model applies a second fail-closed access
 * projection and exposes only mailbox-level operational metadata. It never
 * projects message content, addresses, filenames or credentials.
 */
final class V5_Mail_Workspace_Model {
    private const MAX_MAILBOXES = 25;

    /**
     * @param list<array<string,mixed>> $mailboxes
     * @return array<string,mixed>
     */
    public static function build( array $mailboxes ): array {
        $items = array();

        foreach ( array_slice( $mailboxes, 0, self::MAX_MAILBOXES ) as $candidate ) {
            if ( ! is_array( $candidate ) ) {
                continue;
            }

            $mailbox_id = self::opaque_id( (string) ( $candidate['mailboxId'] ?? '' ), 128 );
            if ( '' === $mailbox_id ) {
                continue;
            }

            $access = V5_Mailbox_Access_Profile::normalize( (array) ( $candidate['access'] ?? array() ) );
            if ( true !== ( $access['canRead'] ?? false ) && true !== ( $access['canSend'] ?? false ) ) {
                continue;
            }

            $profile = V5_Mail_Provider_Profile::evaluate(
                (array) ( $candidate['providerCapabilities'] ?? array() ),
                (array) ( $candidate['syncCapabilities'] ?? array() )
            );

            $can_read    = true === ( $access['canRead'] ?? false );
            $can_send    = true === ( $access['canSend'] ?? false ) && true === ( $profile['readyForSend'] ?? false );
            $can_sync    = $can_read && true === ( $profile['readyForReceive'] ?? false );
            $can_folders = $can_read
                && true === ( $access['canManageFolders'] ?? false )
                && true === ( $profile['providerCapabilities']['folders'] ?? false )
                && true === ( $profile['providerCapabilities']['folderScopedMutations'] ?? false );

            $target_actions = array(
                'openInbox'     => $can_read,
                'receiveSync'   => $can_sync,
                'compose'       => $can_send,
                'manageFolders' => $can_folders,
            );

            // Current production remains hard read-only. Read access can be
            // represented, but V5-owned sync or mutation actions stay disabled.
            $current_actions = array(
                'openInbox'     => $can_read,
                'receiveSync'   => false,
                'compose'       => false,
                'manageFolders' => false,
            );

            $items[] = array(
                'mailboxId' => $mailbox_id,
                'label'     => self::label( (string) ( $candidate['label'] ?? 'Mailbox' ) ),
                'type'      => (string) ( $access['type'] ?? 'shared' ),
                'unread'    => max( 0, min( 9999, (int) ( $candidate['unread'] ?? 0 ) ) ),
                'access'    => array(
                    'canRead'          => $can_read,
                    'canSend'          => $can_send,
                    'canSendAs'        => true === ( $access['canSendAs'] ?? false ),
                    'canManageFolders' => $can_folders,
                    'canDelegate'      => true === ( $access['canDelegate'] ?? false ),
                ),
                'provider' => array(
                    'readyForReceive'       => true === ( $profile['readyForReceive'] ?? false ),
                    'readyForSend'          => true === ( $profile['readyForSend'] ?? false ),
                    'readyForBidirectional' => true === ( $profile['readyForBidirectional'] ?? false ),
                    'missingReceive'        => array_values( (array) ( $profile['missingReceive'] ?? array() ) ),
                    'missingSend'           => array_values( (array) ( $profile['missingSend'] ?? array() ) ),
                    'missingSync'           => array_values( (array) ( $profile['missingSync'] ?? array() ) ),
                ),
                'currentActions' => $current_actions,
                'targetActions'  => $target_actions,
                // Compatibility alias is deliberately current-state, not target-state.
                'actions' => $current_actions,
                'searchVisibility' => 'none',
                'aiVisibility'     => 'none',
            );
        }

        $unread = 0;
        $receive_ready = 0;
        $send_ready = 0;
        foreach ( $items as $item ) {
            $unread += (int) ( $item['unread'] ?? 0 );
            if ( true === ( $item['targetActions']['receiveSync'] ?? false ) ) {
                $receive_ready++;
            }
            if ( true === ( $item['targetActions']['compose'] ?? false ) ) {
                $send_ready++;
            }
        }

        return array(
            'workspace' => 'mail',
            'targetMode' => 'bidirectional',
            'currentProductionMode' => 'read_only',
            'productionWritesEnabled' => false,
            'receiveSyncPromoted' => false,
            'sendRoutePromoted' => false,
            'mailboxes' => $items,
            'summary' => array(
                'mailboxCount' => count( $items ),
                'unread' => min( 99999, $unread ),
                'receiveReady' => $receive_ready,
                'sendReady' => $send_ready,
            ),
            'security' => array(
                'searchVisibility' => 'none',
                'aiVisibility' => 'none',
                'auditBodyAllowed' => false,
            ),
        );
    }

    private static function opaque_id( string $value, int $max_length ): string {
        $value = trim( $value );
        if ( '' === $value || strlen( $value ) > $max_length ) {
            return '';
        }
        return 1 === preg_match( '/^[A-Za-z0-9._:-]+$/', $value ) ? $value : '';
    }

    private static function label( string $value ): string {
        $value = strip_tags( $value );
        $value = preg_replace( '/[\x00-\x1F\x7F]+/u', ' ', $value ) ?? '';
        $value = trim( preg_replace( '/\s+/u', ' ', $value ) ?? '' );
        if ( '' === $value ) {
            return 'Mailbox';
        }
        return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, 80 ) : substr( $value, 0, 80 );
    }

    private function __construct() {}
}
