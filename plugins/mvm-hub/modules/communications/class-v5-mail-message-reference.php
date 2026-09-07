<?php

namespace MVM\Hub\Modules\Communications;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Folder-scoped message identity for Mail V2.
 *
 * IMAP UIDs are only stable inside a concrete mailbox/folder context. V5 must
 * therefore never treat a bare message id as globally unique.
 */
final class V5_Mail_Message_Reference {
    /**
     * @return array{mailboxId:string,folderId:string,messageId:string,key:string}|null
     */
    public static function normalize( string $mailbox_id, string $folder_id, string $message_id ): ?array {
        $mailbox_id = self::opaque_id( $mailbox_id, 128 );
        $folder_id  = self::opaque_id( $folder_id, 128 );
        $message_id = self::opaque_id( $message_id, 260 );

        if ( '' === $mailbox_id || '' === $folder_id || '' === $message_id ) {
            return null;
        }

        return array(
            'mailboxId' => $mailbox_id,
            'folderId'  => $folder_id,
            'messageId' => $message_id,
            'key'       => hash( 'sha256', $mailbox_id . "\n" . $folder_id . "\n" . $message_id ),
        );
    }

    private static function opaque_id( string $value, int $max_length ): string {
        $value = trim( $value );
        if ( '' === $value || strlen( $value ) > $max_length ) {
            return '';
        }

        return 1 === preg_match( '/^[A-Za-z0-9._:-]+$/', $value ) ? $value : '';
    }

    private function __construct() {}
}
