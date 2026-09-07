<?php

namespace MVM\Hub\Modules\Communications;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Fail-closed mailbox authorization profile for V5 UI/read-model decisions.
 *
 * This does not replace Mailbox_Access. It projects already-resolved grants into
 * a small capability surface for personal and shared mailboxes.
 */
final class V5_Mailbox_Access_Profile {
    /**
     * @param array<string,mixed> $grants
     * @return array<string,mixed>
     */
    public static function normalize( array $grants ): array {
        $type = in_array( (string) ( $grants['type'] ?? '' ), array( 'personal', 'shared' ), true )
            ? (string) $grants['type']
            : 'shared';

        $can_read          = true === ( $grants['read'] ?? false );
        $can_send          = true === ( $grants['send'] ?? false );
        $can_send_as       = true === ( $grants['sendAs'] ?? false );
        $can_manage_folder = true === ( $grants['manageFolders'] ?? false );
        $can_delegate      = true === ( $grants['delegate'] ?? false );

        if ( 'shared' === $type && ! $can_send_as ) {
            $can_send = false;
        }

        return array(
            'type'             => $type,
            'canRead'          => $can_read,
            'canSend'          => $can_send,
            'canSendAs'        => $can_send_as,
            'canManageFolders' => $can_manage_folder,
            'canDelegate'      => $can_delegate,
            'searchVisibility' => 'none',
            'aiVisibility'     => 'none',
        );
    }

    private function __construct() {}
}
