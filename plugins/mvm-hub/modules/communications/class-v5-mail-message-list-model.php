<?php

namespace MVM\Hub\Modules\Communications;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Dormant, provider-neutral projection for one already-authorized mailbox folder.
 *
 * This model is intentionally UI-specific: unlike receive-sync it may expose
 * subject/correspondent metadata to the mailbox user, but it never participates
 * in shared search, generic AI context or audit-body logging.
 */
final class V5_Mail_Message_List_Model {
    private const MAX_MESSAGES = 100;

    /**
     * @param list<array<string,mixed>> $messages
     * @return array<string,mixed>
     */
    public static function build( string $mailbox_id, string $folder_id, array $messages ): array {
        $mailbox_id = self::opaque_id( $mailbox_id, 128 );
        $folder_id  = self::opaque_id( $folder_id, 190 );

        if ( '' === $mailbox_id || '' === $folder_id ) {
            return self::empty_projection();
        }

        $items = array();
        foreach ( array_slice( $messages, 0, self::MAX_MESSAGES ) as $candidate ) {
            if ( ! is_array( $candidate ) ) {
                continue;
            }

            $message_id = self::opaque_id(
                (string) ( $candidate['messageId'] ?? $candidate['id'] ?? '' ),
                190
            );
            if ( '' === $message_id ) {
                continue;
            }

            $reference = V5_Mail_Message_Reference::normalize( $mailbox_id, $folder_id, $message_id );
            if ( ! is_array( $reference ) ) {
                continue;
            }

            $attachment_count = 0;
            if ( is_array( $candidate['attachments'] ?? null ) ) {
                $attachment_count = count( array_slice( $candidate['attachments'], 0, 100 ) );
            } elseif ( isset( $candidate['attachmentCount'] ) ) {
                $attachment_count = max( 0, min( 100, (int) $candidate['attachmentCount'] ) );
            }

            $items[] = array(
                'reference'       => $reference,
                'subject'         => self::text( (string) ( $candidate['subject'] ?? '(geen onderwerp)' ), 255 ),
                'correspondent'   => self::text( (string) ( $candidate['from'] ?? $candidate['correspondent'] ?? '' ), 200 ),
                'date'            => self::text( (string) ( $candidate['date'] ?? '' ), 100 ),
                'seen'            => true === ( $candidate['seen'] ?? false ),
                'flagged'         => true === ( $candidate['flagged'] ?? false ),
                'size'            => max( 0, min( PHP_INT_MAX, (int) ( $candidate['size'] ?? 0 ) ) ),
                'attachmentCount' => $attachment_count,
            );
        }

        return array(
            'mailboxId' => $mailbox_id,
            'folderId'  => $folder_id,
            'messages'  => $items,
            'count'     => count( $items ),
            'security'  => array(
                'searchVisibility' => 'none',
                'aiVisibility'     => 'none',
                'auditBodyAllowed' => false,
            ),
        );
    }

    /** @return array<string,mixed> */
    private static function empty_projection(): array {
        return array(
            'mailboxId' => '',
            'folderId'  => '',
            'messages'  => array(),
            'count'     => 0,
            'security'  => array(
                'searchVisibility' => 'none',
                'aiVisibility'     => 'none',
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

    private static function text( string $value, int $max_length ): string {
        $value = strip_tags( $value );
        $value = preg_replace( '/[\x00-\x1F\x7F]+/u', ' ', $value ) ?? '';
        $value = trim( preg_replace( '/\s+/u', ' ', $value ) ?? '' );
        if ( function_exists( 'mb_substr' ) ) {
            return mb_substr( $value, 0, $max_length );
        }
        return substr( $value, 0, $max_length );
    }

    private function __construct() {}
}
