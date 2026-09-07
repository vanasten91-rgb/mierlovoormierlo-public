<?php

namespace MVM\Hub\Modules\Communications;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Dormant provider-neutral compose/reply/forward intent for Mail V2.
 *
 * This is not a delivery service. It validates and projects an already
 * authorized mailbox action into a safe target payload. Sender identity is
 * never accepted from the client and production delivery remains cutover-gated.
 */
final class V5_Mail_Compose_Intent {
    private const MAX_RECIPIENTS  = 50;
    private const MAX_ATTACHMENTS = 20;
    private const MAX_SUBJECT     = 255;
    private const MAX_BODY_BYTES  = 250000;

    /**
     * @param array<string,mixed> $input
     * @param callable(string):string $html_sanitizer
     * @return array<string,mixed>|null
     */
    public static function normalize( array $input, callable $html_sanitizer ): ?array {
        foreach ( array( 'from', 'sender', 'returnPath', 'return_path', 'smtpFrom' ) as $forbidden ) {
            if ( array_key_exists( $forbidden, $input ) ) {
                return null;
            }
        }

        $mailbox_id = self::opaque_id( (string) ( $input['mailboxId'] ?? '' ), 128 );
        if ( '' === $mailbox_id ) {
            return null;
        }

        $mode = strtolower( trim( (string) ( $input['mode'] ?? 'compose' ) ) );
        if ( ! in_array( $mode, array( 'compose', 'reply', 'reply_all', 'forward' ), true ) ) {
            return null;
        }

        $source = null;
        if ( 'compose' !== $mode ) {
            $source_input = is_array( $input['source'] ?? null ) ? $input['source'] : array();
            $source = V5_Mail_Message_Reference::normalize(
                (string) ( $source_input['mailboxId'] ?? '' ),
                (string) ( $source_input['folderId'] ?? '' ),
                (string) ( $source_input['messageId'] ?? '' )
            );
            if ( ! is_array( $source ) || $mailbox_id !== (string) ( $source['mailboxId'] ?? '' ) ) {
                return null;
            }
        }

        $to  = self::emails( $input['to'] ?? array() );
        $cc  = self::emails( $input['cc'] ?? array() );
        $bcc = self::emails( $input['bcc'] ?? array() );
        if ( null === $to || null === $cc || null === $bcc ) {
            return null;
        }

        $recipient_count = count( $to ) + count( $cc ) + count( $bcc );
        if ( 0 === $recipient_count || $recipient_count > self::MAX_RECIPIENTS ) {
            return null;
        }

        $subject = self::text( (string) ( $input['subject'] ?? '' ), self::MAX_SUBJECT );

        $html = (string) ( $input['html'] ?? $input['htmlBody'] ?? '' );
        $text = (string) ( $input['text'] ?? $input['textBody'] ?? '' );
        if ( strlen( $html ) > self::MAX_BODY_BYTES || strlen( $text ) > self::MAX_BODY_BYTES ) {
            return null;
        }
        $html = (string) $html_sanitizer( $html );
        $text = trim( str_replace( "\0", '', $text ) );
        if ( '' === trim( strip_tags( $html ) ) && '' === $text ) {
            return null;
        }

        $attachments = self::attachment_ids( $input['attachmentIds'] ?? array() );
        if ( null === $attachments ) {
            return null;
        }

        $in_reply_to = self::message_id( (string) ( $input['inReplyTo'] ?? '' ) );
        if ( '' !== trim( (string) ( $input['inReplyTo'] ?? '' ) ) && '' === $in_reply_to ) {
            return null;
        }
        $references = self::message_ids( $input['references'] ?? array() );
        if ( null === $references ) {
            return null;
        }

        if ( 'compose' === $mode && ( '' !== $in_reply_to || array() !== $references ) ) {
            return null;
        }
        if ( in_array( $mode, array( 'reply', 'reply_all' ), true ) && '' === $in_reply_to ) {
            return null;
        }

        return array(
            'mailboxId'       => $mailbox_id,
            'mode'            => $mode,
            'source'          => $source,
            'to'              => $to,
            'cc'              => $cc,
            'bcc'             => $bcc,
            'recipientCount'  => $recipient_count,
            'subject'         => $subject,
            'html'            => $html,
            'text'            => $text,
            'attachmentIds'   => $attachments,
            'threading'       => array(
                'inReplyTo'  => $in_reply_to,
                'references' => $references,
            ),
            'senderPolicy'    => array(
                'clientControlled' => false,
                'deriveFromAuthorizedMailbox' => true,
            ),
            'deliveryPolicy'  => array(
                'currentProductionMode' => 'read_only',
                'productionEligible'    => false,
                'requiresStepUp'        => true,
                'requiresIdempotency'   => true,
                'requiresRateLimit'     => true,
                'requiresCutover'       => true,
            ),
        );
    }

    /** @return list<string>|null */
    private static function emails( mixed $values ): ?array {
        if ( ! is_array( $values ) ) {
            return null;
        }
        $items = array();
        foreach ( $values as $value ) {
            if ( ! is_string( $value ) || str_contains( $value, "\r" ) || str_contains( $value, "\n" ) ) {
                return null;
            }
            $email = strtolower( trim( $value ) );
            if ( false === filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
                return null;
            }
            $items[] = $email;
        }
        return array_values( array_unique( $items ) );
    }

    /** @return list<string>|null */
    private static function attachment_ids( mixed $values ): ?array {
        if ( ! is_array( $values ) ) {
            return null;
        }
        $items = array();
        foreach ( $values as $value ) {
            if ( ! is_string( $value ) && ! is_int( $value ) ) {
                return null;
            }
            $id = self::opaque_id( (string) $value, 128 );
            if ( '' === $id ) {
                return null;
            }
            $items[] = $id;
        }
        $items = array_values( array_unique( $items ) );
        return count( $items ) <= self::MAX_ATTACHMENTS ? $items : null;
    }

    /** @return list<string>|null */
    private static function message_ids( mixed $values ): ?array {
        if ( ! is_array( $values ) ) {
            return null;
        }
        if ( count( $values ) > 20 ) {
            $values = array_slice( $values, -20 );
        }
        $items = array();
        foreach ( $values as $value ) {
            if ( ! is_string( $value ) ) {
                return null;
            }
            $id = self::message_id( $value );
            if ( '' === $id ) {
                return null;
            }
            $items[] = $id;
        }
        return array_values( array_unique( $items ) );
    }

    private static function message_id( string $value ): string {
        $value = trim( $value );
        if ( '' === $value ) {
            return '';
        }
        return strlen( $value ) <= 998 && 1 === preg_match( '/^<[^<>\s]+@[^<>\s]+>$/', $value )
            ? $value
            : '';
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
        return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $max_length ) : substr( $value, 0, $max_length );
    }

    private function __construct() {}
}
