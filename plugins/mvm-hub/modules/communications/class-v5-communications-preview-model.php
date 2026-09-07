<?php

namespace MVM\Hub\Modules\Communications;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Dormant preview model for the future V5 Communications workspace.
 *
 * Board/chat inputs must already be capability-scoped by their current owner.
 * Mail V2 is explicitly designed as bidirectional (receive + send), while the
 * current production Mail runtime remains hard read-only until a separately
 * approved promotion/cutover enables the new transport and sync ownership.
 */
final class V5_Communications_Preview_Model {
    /**
     * @param list<array<string,mixed>> $board_items
     * @param list<array<string,mixed>> $chat_items
     * @return array<string,mixed>
     */
    public static function build( array $board_items, array $chat_items ): array {
        $board = V5_Collaboration_Read_Adapter::board( $board_items );
        $chat  = V5_Collaboration_Read_Adapter::chat( $chat_items );

        $important = 0;
        $pinned    = 0;
        foreach ( $board as $item ) {
            if ( true === ( $item['pinned'] ?? false ) ) {
                $pinned++;
            }
            if ( 'important' === ( $item['priority'] ?? 'normal' ) ) {
                $important++;
            }
        }

        return array(
            'workspace' => 'communications',
            'ownerMode' => 'coexistence_read_only',
            'board'     => array(
                'items'     => $board,
                'total'     => count( $board ),
                'pinned'    => $pinned,
                'important' => $important,
                'canWrite'  => false,
            ),
            'teamChat'  => array(
                'items'         => $chat,
                'total'         => count( $chat ),
                'retentionDays' => 30,
                'canSend'       => false,
            ),
            'mail'      => array(
                'targetMode'             => 'bidirectional',
                'receiveRequired'        => true,
                'sendRequired'           => true,
                'replyRequired'          => true,
                'forwardRequired'        => true,
                'draftsRequired'         => true,
                'attachmentsRequired'    => true,
                'currentProductionMode'  => 'read_only',
                'v2PromotionEnabled'     => false,
                'receiveSyncPromoted'    => false,
                'sendRoutePromoted'      => false,
            ),
            'security'  => array(
                'searchVisibility' => 'none',
                'aiVisibility'     => 'none',
                'auditBodyAllowed' => false,
            ),
        );
    }

    private function __construct() {}
}
