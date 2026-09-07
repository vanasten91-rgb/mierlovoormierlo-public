<?php

namespace MVM\Hub\Modules\Communications;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Dormant UI projection for one already-authorized Mail V2 message.
 *
 * The caller must authorize mailbox + folder + message before calling this
 * projector and must provide a trusted HTML sanitizer callback. The model never
 * emits provider credentials, filesystem paths or direct attachment URLs.
 */
final class V5_Mail_Message_Detail_Model {
    private const MAX_BODY_BYTES  = 250000;
    private const MAX_ATTACHMENTS = 20;

    /**
     * @param array<string,mixed> $message
     * @param callable(string):string $html_sanitizer
     * @return array<string,mixed>|null
     */
    public static function build( array $message, callable $html_sanitizer ): ?array {
        $mailbox_id = self::opaque_id( (string) ( $message['mailboxId'] ?? '' ), 128 );
        $folder_id  = self::opaque_id( (string) ( $message['folderId'] ?? '' ), 190 );
        $message_id = self::opaque_id( (string) ( $message['messageId'] ?? $message['id'] ?? '' ), 190 );

        $reference = V5_Mail_Message_Reference::normalize( $mailbox_id, $folder_id, $message_id );
        if ( ! is_array( $reference ) ) {
            return null;
        }

        $html = (string) ( $message['html'] ?? $message['htmlBody'] ?? '' );
        if ( strlen( $html ) > self::MAX_BODY_BYTES ) {
            $html = substr( $html, 0, self::MAX_BODY_BYTES );
        }
        $html = (string) $html_sanitizer( $html );

        $text = (string) ( $message['text'] ?? $message['textBody'] ?? '' );
        if ( strlen( $text ) > self::MAX_BODY_BYTES ) {
            $text = substr( $text, 0, self::MAX_BODY_BYTES );
        }
        $text = self::body_text( $text );

        return array(
            'reference'    => $reference,
            'subject'      => self::text( (string) ( $message['subject'] ?? '(geen onderwerp)' ), 255 ),
            'from'         => self::text( (string) ( $message['from'] ?? '' ), 200 ),
            'fromAddress'  => self::email( (string) ( $message['fromAddress'] ?? '' ) ),
            'to'           => self::emails( $message['to'] ?? array() ),
            'cc'           => self::emails( $message['cc'] ?? array() ),
            'date'         => self::text( (string) ( $message['date'] ?? '' ), 100 ),
            'seen'         => true === ( $message['seen'] ?? false ),
            'flagged'      => true === ( $message['flagged'] ?? false ),
            'text'         => $text,
            'html'         => $html,
            'attachments'  => self::attachments( $message['attachments'] ?? array() ),
            'threading'    => array(
                'messageId'  => self::message_id( (string) ( $message['messageIdHeader'] ?? $message['messageIdRaw'] ?? $message['rfcMessageId'] ?? '' ) ),
                'inReplyTo'  => self::message_id( (string) ( $message['inReplyTo'] ?? '' ) ),
                'references' => self::message_ids( $message['references'] ?? array() ),
            ),
            'contentPolicy' => array(
                'remoteImagesAllowed' => false,
                'externalContentAutoLoad' => false,
            ),
            'security' => array(
                'searchVisibility' => 'none',
                'aiVisibility'     => 'none',
                'auditBodyAllowed' => false,
            ),
        );
    }

    /** @return list<array<string,mixed>> */
    private static function attachments( mixed $attachments ): array {
        if ( ! is_array( $attachments ) ) {
            return array();
        }

        $items = array();
        foreach ( array_slice( $attachments, 0, self::MAX_ATTACHMENTS ) as $candidate ) {
            if ( ! is_array( $candidate ) ) {
                continue;
            }
            $id = self::opaque_id( (string) ( $candidate['id'] ?? '' ), 190 );
            if ( '' === $id ) {
                continue;
            }
            $items[] = array(
                'id'   => $id,
                'name' => self::text( (string) ( $candidate['name'] ?? 'bijlage' ), 180 ),
                'mime' => self::mime( (string) ( $candidate['mime'] ?? 'application/octet-stream' ) ),
                'size' => max( 0, min( PHP_INT_MAX, (int) ( $candidate['size'] ?? 0 ) ) ),
            );
        }
        return $items;
    }

    /** @return list<string> */
    private static function emails( mixed $values ): array {
        if ( ! is_array( $values ) ) {
            return array();
        }
        $items = array();
        foreach ( array_slice( $values, 0, 50 ) as $value ) {
            $email = self::email( (string) $value );
            if ( '' !== $email ) {
                $items[] = $email;
            }
        }
        return array_values( array_unique( $items ) );
    }

    private static function email( string $value ): string {
        $value = trim( strtolower( $value ) );
        return false !== filter_var( $value, FILTER_VALIDATE_EMAIL ) ? $value : '';
    }

    /** @return list<string> */
    private static function message_ids( mixed $values ): array {
        if ( is_string( $values ) ) {
            $values = preg_split( '/\s+/', trim( $values ) ) ?: array();
        }
        if ( ! is_array( $values ) ) {
            return array();
        }
        $items = array();
        foreach ( array_slice( $values, -20 ) as $value ) {
            $id = self::message_id( (string) $value );
            if ( '' !== $id ) {
                $items[] = $id;
            }
        }
        return array_values( array_unique( $items ) );
    }

    private static function message_id( string $value ): string {
        $value = trim( $value );
        return strlen( $value ) <= 998
            && 1 === preg_match( '/^<[^<>\s]+@[^<>\s]+>$/', $value )
            ? $value
            : '';
    }

    private static function mime( string $value ): string {
        $value = strtolower( trim( $value ) );
        return 1 === preg_match( '#^[a-z0-9.+-]+/[a-z0-9.+-]+$#', $value )
            ? $value
            : 'application/octet-stream';
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

    private static function body_text( string $value ): string {
        $value = str_replace( "\0", '', $value );
        return trim( $value );
    }

    private function __construct() {}
}
