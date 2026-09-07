<?php

namespace MVM\Hub\Modules\Communications;

use MVM\Hub\Core\Communications_Policy;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Canonical validation/projection for draft and delivery payloads.
 *
 * Sender identity is intentionally absent. The transport must derive From and
 * Return-Path from the server-side authorized mailbox; client input cannot spoof
 * either header.
 */
final class Mail_Compose_Validator {
    private const MAX_RECIPIENTS  = 50;
    private const MAX_ATTACHMENTS = 20;
    private const MAX_SUBJECT     = 255;
    private const MAX_HTML_BYTES  = 250000;

    /** @param array<string,mixed> $input @return array<string,mixed>|\WP_Error */
    public static function validate( array $input ): array|\WP_Error {
        return self::normalize( $input, true );
    }

    /**
     * Drafts may be incomplete while autosaving, but all supplied addresses,
     * threading metadata, attachments and rich text still pass the same security
     * validation as a delivery payload.
     *
     * @param array<string,mixed> $input
     * @return array<string,mixed>|\WP_Error
     */
    public static function validate_draft( array $input ): array|\WP_Error {
        return self::normalize( $input, false );
    }

    /** @param array<string,mixed> $input @return array<string,mixed>|\WP_Error */
    private static function normalize( array $input, bool $recipient_required ): array|\WP_Error {
        foreach ( array( 'from', 'sender', 'returnPath', 'return_path' ) as $forbidden ) {
            if ( array_key_exists( $forbidden, $input ) ) {
                return new \WP_Error( 'mvm_mail_sender_forbidden', 'De afzender wordt uitsluitend door de geautoriseerde mailbox bepaald.' );
            }
        }

        $to  = self::recipients( $input['to'] ?? array() );
        $cc  = self::recipients( $input['cc'] ?? array() );
        $bcc = self::recipients( $input['bcc'] ?? array() );

        if ( is_wp_error( $to ) ) {
            return $to;
        }
        if ( is_wp_error( $cc ) ) {
            return $cc;
        }
        if ( is_wp_error( $bcc ) ) {
            return $bcc;
        }

        $recipient_count = count( $to ) + count( $cc ) + count( $bcc );
        if ( $recipient_required && 0 === $recipient_count ) {
            return new \WP_Error( 'mvm_mail_recipient_required', 'Voeg minimaal één geldige ontvanger toe.' );
        }
        if ( $recipient_count > self::MAX_RECIPIENTS ) {
            return new \WP_Error( 'mvm_mail_recipient_limit', 'Te veel ontvangers voor één bericht.' );
        }

        $subject = self::single_line( (string) ( $input['subject'] ?? '' ) );
        $subject = mb_substr( $subject, 0, self::MAX_SUBJECT );

        $html = (string) ( $input['htmlBody'] ?? '' );
        if ( strlen( $html ) > self::MAX_HTML_BYTES ) {
            return new \WP_Error( 'mvm_mail_body_too_large', 'De berichttekst is te groot.' );
        }
        $html = Communications_Policy::sanitize_composer_html( $html );
        $text = trim( wp_strip_all_tags( str_replace( array( '<br>', '<br/>', '<br />' ), "\n", $html ) ) );

        $attachments = self::attachment_ids( $input['attachmentIds'] ?? array() );
        if ( is_wp_error( $attachments ) ) {
            return $attachments;
        }

        $mode = sanitize_key( (string) ( $input['mode'] ?? 'compose' ) );
        if ( ! in_array( $mode, array( 'compose', 'reply', 'reply_all', 'forward' ), true ) ) {
            return new \WP_Error( 'mvm_mail_mode_invalid', 'Onbekende e-mailactie.' );
        }

        $threading = self::threading( $input );
        if ( is_wp_error( $threading ) ) {
            return $threading;
        }

        return array(
            'mode'              => $mode,
            'to'                => $to,
            'cc'                => $cc,
            'bcc'               => $bcc,
            'subject'           => $subject,
            'htmlBody'          => $html,
            'textBody'          => $text,
            'attachmentIds'     => $attachments,
            'draftId'           => sanitize_text_field( (string) ( $input['draftId'] ?? '' ) ),
            'originalMessageId' => sanitize_text_field( (string) ( $input['originalMessageId'] ?? '' ) ),
            'inReplyTo'         => $threading['inReplyTo'],
            'references'        => $threading['references'],
            'recipientCount'    => $recipient_count,
        );
    }

    /** @return array<int,string>|\WP_Error */
    private static function recipients( mixed $value ): array|\WP_Error {
        if ( ! is_array( $value ) ) {
            return new \WP_Error( 'mvm_mail_recipient_shape', 'Ontvangers moeten als lijst worden aangeleverd.' );
        }

        $result = array();
        foreach ( $value as $candidate ) {
            if ( ! is_string( $candidate ) || str_contains( $candidate, "\r" ) || str_contains( $candidate, "\n" ) ) {
                return new \WP_Error( 'mvm_mail_recipient_invalid', 'Een ontvanger is ongeldig.' );
            }

            $email = sanitize_email( trim( $candidate ) );
            if ( '' === $email || false === is_email( $email ) ) {
                return new \WP_Error( 'mvm_mail_recipient_invalid', 'Een ontvanger is ongeldig.' );
            }
            $result[] = strtolower( $email );
        }

        return array_values( array_unique( $result ) );
    }

    /** @return array<int,string>|\WP_Error */
    private static function attachment_ids( mixed $value ): array|\WP_Error {
        if ( ! is_array( $value ) ) {
            return new \WP_Error( 'mvm_mail_attachment_shape', 'Bijlagen moeten als lijst worden aangeleverd.' );
        }

        $result = array();
        foreach ( $value as $candidate ) {
            if ( ! is_string( $candidate ) && ! is_int( $candidate ) ) {
                return new \WP_Error( 'mvm_mail_attachment_invalid', 'Een bijlageverwijzing is ongeldig.' );
            }
            $id = trim( (string) $candidate );
            if ( '' === $id || strlen( $id ) > 128 || 1 !== preg_match( '/^[A-Za-z0-9._:-]+$/', $id ) ) {
                return new \WP_Error( 'mvm_mail_attachment_invalid', 'Een bijlageverwijzing is ongeldig.' );
            }
            $result[] = $id;
        }

        $result = array_values( array_unique( $result ) );
        if ( count( $result ) > self::MAX_ATTACHMENTS ) {
            return new \WP_Error( 'mvm_mail_attachment_limit', 'Te veel bijlagen voor één bericht.' );
        }

        return $result;
    }

    /** @return array{inReplyTo:string,references:array<int,string>}|\WP_Error */
    private static function threading( array $input ): array|\WP_Error {
        $in_reply_to = trim( (string) ( $input['inReplyTo'] ?? '' ) );
        if ( '' !== $in_reply_to && ! self::message_id_valid( $in_reply_to ) ) {
            return new \WP_Error( 'mvm_mail_thread_invalid', 'De reply-header is ongeldig.' );
        }

        $references = $input['references'] ?? array();
        if ( ! is_array( $references ) ) {
            return new \WP_Error( 'mvm_mail_thread_invalid', 'De referentielijst is ongeldig.' );
        }

        $clean = array();
        foreach ( array_slice( $references, -20 ) as $reference ) {
            if ( ! is_string( $reference ) || ! self::message_id_valid( $reference ) ) {
                return new \WP_Error( 'mvm_mail_thread_invalid', 'Een berichtreferentie is ongeldig.' );
            }
            $clean[] = trim( $reference );
        }

        return array(
            'inReplyTo'  => $in_reply_to,
            'references' => array_values( array_unique( $clean ) ),
        );
    }

    private static function message_id_valid( string $value ): bool {
        $value = trim( $value );
        return strlen( $value ) <= 998
            && ! str_contains( $value, "\r" )
            && ! str_contains( $value, "\n" )
            && 1 === preg_match( '/^<[^<>\s]+@[^<>\s]+>$/', $value );
    }

    private static function single_line( string $value ): string {
        return sanitize_text_field( str_replace( array( "\r", "\n" ), ' ', $value ) );
    }

    private function __construct() {}
}
