<?php

namespace MVM\Hub\Modules\Communications;

use MVM\Hub\Core\Communications_Policy;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Builds a safe provider-neutral reply draft from an already authorized source
 * message. This class does not persist or deliver anything.
 */
final class Mail_Reply_Builder {
    /**
     * @param array<string,mixed> $original
     * @param array<string,mixed> $input
     * @return array<string,mixed>|\WP_Error
     */
    public static function build( array $original, array $input ): array|\WP_Error {
        $recipient = self::reply_recipient( $original );
        if ( '' === $recipient ) {
            return new \WP_Error( 'mvm_mail_reply_recipient_missing', 'Voor dit bericht is geen veilig antwoordadres beschikbaar.' );
        }

        $html = Communications_Policy::sanitize_composer_html( (string) ( $input['html'] ?? '' ) );
        $text = sanitize_textarea_field( (string) ( $input['text'] ?? '' ) );
        if ( '' === trim( wp_strip_all_tags( $html ) ) && '' === trim( $text ) ) {
            return new \WP_Error( 'mvm_mail_reply_empty', 'Een antwoord mag niet leeg zijn.' );
        }

        $subject = sanitize_text_field( (string) ( $original['subject'] ?? '' ) );
        if ( ! preg_match( '/^\s*re\s*:/i', $subject ) ) {
            $subject = 'Re: ' . $subject;
        }

        $draft = array(
            'mode'              => 'reply',
            'to'                => array( $recipient ),
            'cc'                => self::safe_address_list( $input['cc'] ?? array() ),
            'bcc'               => self::safe_address_list( $input['bcc'] ?? array() ),
            'subject'           => $subject,
            'html'              => $html,
            'text'              => $text,
            'originalMessageId' => sanitize_text_field( (string) ( $original['id'] ?? '' ) ),
            'threadId'          => sanitize_text_field( (string) ( $original['threadId'] ?? $original['id'] ?? '' ) ),
            'inReplyTo'         => sanitize_text_field( (string) ( $original['messageId'] ?? '' ) ),
            'references'        => self::references( $original ),
            'attachmentIds'     => self::safe_attachment_ids( $input['attachmentIds'] ?? array() ),
        );

        return $draft;
    }

    /** @param array<string,mixed> $original */
    private static function reply_recipient( array $original ): string {
        $candidates = array(
            $original['replyToEmail'] ?? '',
            $original['fromEmail'] ?? '',
            $original['reply_to_email'] ?? '',
            $original['from_email'] ?? '',
        );

        foreach ( $candidates as $candidate ) {
            $email = sanitize_email( (string) $candidate );
            if ( '' !== $email && is_email( $email ) ) {
                return $email;
            }
        }

        return '';
    }

    private static function safe_address_list( mixed $addresses ): array {
        if ( ! is_array( $addresses ) ) {
            return array();
        }

        $safe = array();
        foreach ( $addresses as $address ) {
            $email = sanitize_email( (string) $address );
            if ( '' !== $email && is_email( $email ) ) {
                $safe[] = $email;
            }
        }

        return array_values( array_unique( $safe ) );
    }

    /** @param array<string,mixed> $original */
    private static function references( array $original ): array {
        $references = $original['references'] ?? array();
        if ( is_string( $references ) ) {
            $references = preg_split( '/\s+/', trim( $references ) ) ?: array();
        }
        if ( ! is_array( $references ) ) {
            $references = array();
        }

        $message_id = sanitize_text_field( (string) ( $original['messageId'] ?? '' ) );
        if ( '' !== $message_id ) {
            $references[] = $message_id;
        }

        $safe = array();
        foreach ( $references as $reference ) {
            $reference = sanitize_text_field( (string) $reference );
            if ( '' !== $reference ) {
                $safe[] = $reference;
            }
        }

        return array_values( array_unique( $safe ) );
    }

    private static function safe_attachment_ids( mixed $attachment_ids ): array {
        if ( ! is_array( $attachment_ids ) ) {
            return array();
        }

        $safe = array();
        foreach ( $attachment_ids as $attachment_id ) {
            $attachment_id = sanitize_text_field( (string) $attachment_id );
            if ( '' !== $attachment_id && strlen( $attachment_id ) <= 190 ) {
                $safe[] = $attachment_id;
            }
        }

        return array_values( array_unique( $safe ) );
    }

    private function __construct() {}
}
