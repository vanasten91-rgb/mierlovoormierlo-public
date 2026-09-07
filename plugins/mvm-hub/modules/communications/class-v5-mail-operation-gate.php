<?php

namespace MVM\Hub\Modules\Communications;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Pure application-layer operation gate for Mail V2.
 *
 * The current production baseline remains read-only. This class translates a
 * previously evaluated V5_Mail_Cutover_Gate decision into per-operation
 * permission without registering routes, hooks or changing runtime state.
 */
final class V5_Mail_Operation_Gate {
    /** @return array{allowed:bool,reason:string,currentProductionMode:string,requiresSeparateApproval:bool} */
    public static function evaluate( string $operation, array $cutover_decision ): array {
        $operation = sanitize_key( $operation );

        $read_operations = array(
            'folders_read',
            'messages_read',
            'message_read',
            'attachment_read',
        );

        if ( in_array( $operation, $read_operations, true ) ) {
            return self::decision( true, 'read_allowed' );
        }

        if ( 'receive_sync' === $operation ) {
            $allowed = true === ( $cutover_decision['canPromoteReceiveSync'] ?? false );
            return self::decision( $allowed, $allowed ? 'receive_sync_promoted' : 'receive_sync_not_promoted' );
        }

        if ( 'deliver' === $operation ) {
            $allowed = true === ( $cutover_decision['canPromoteSendRoute'] ?? false );
            return self::decision( $allowed, $allowed ? 'send_route_promoted' : 'send_route_not_promoted' );
        }

        $mutation_operations = array(
            'draft_create',
            'draft_update',
            'draft_delete',
            'folder_create',
            'folder_rename',
            'folder_delete',
            'message_move',
            'message_read_state',
            'message_flag_state',
            'message_pin_state',
            'message_archive',
            'message_trash',
            'message_spam',
            'message_report',
            'sender_block_state',
        );

        if ( in_array( $operation, $mutation_operations, true ) ) {
            $allowed = true === ( $cutover_decision['canPromoteBidirectional'] ?? false );
            return self::decision( $allowed, $allowed ? 'bidirectional_promoted' : 'bidirectional_not_promoted' );
        }

        return self::decision( false, 'unknown_operation' );
    }

    /** @return array{allowed:bool,reason:string,currentProductionMode:string,requiresSeparateApproval:bool} */
    private static function decision( bool $allowed, string $reason ): array {
        return array(
            'allowed'                   => $allowed,
            'reason'                    => $reason,
            'currentProductionMode'     => 'read_only',
            'requiresSeparateApproval'  => true,
        );
    }

    private function __construct() {}
}
