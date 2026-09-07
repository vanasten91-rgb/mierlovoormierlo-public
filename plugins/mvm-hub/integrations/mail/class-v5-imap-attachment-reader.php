<?php

namespace MVM\Hub\Integrations\Mail;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Folder-scoped incoming attachment reader for Mail V2.
 *
 * This class is transport-only: callers must perform mailbox/message object
 * authorization before invoking it. It never creates a public URL and never
 * writes incoming attachments into the WordPress media library.
 */
final class V5_IMAP_Attachment_Reader {
    private const MAX_BYTES      = 26214400; // 25 MiB hard read bound.
    private const MAX_PART_DEPTH = 8;

    public function supported( string $mailbox_id ): bool {
        return function_exists( 'imap_open' ) && $this->configured( $mailbox_id );
    }

    /** @return array<string,mixed>|\WP_Error */
    public function fetch( string $mailbox_id, string $folder_id, string $message_id, string $attachment_id ): array|\WP_Error {
        $identity = self::message_identity( $folder_id, $message_id );
        if ( is_wp_error( $identity ) ) {
            return $identity;
        }
        $part_number = self::part_number( $attachment_id );
        if ( is_wp_error( $part_number ) ) {
            return $part_number;
        }
        if ( ! $this->supported( $mailbox_id ) ) {
            return new \WP_Error( 'mvm_mail_attachment_imap_unavailable', 'IMAP-bijlagen zijn niet beschikbaar.' );
        }

        $mailbox = $this->server_prefix() . $identity['remote'];
        $stream = @imap_open( $mailbox, (string) MVM_HUB_IMAP_USERNAME, (string) MVM_HUB_IMAP_PASSWORD, 0, 1 );
        if ( false === $stream ) {
            return new \WP_Error( 'mvm_mail_attachment_imap_connect', 'De mailboxmap kon niet veilig worden geopend.' );
        }

        try {
            $uid = $identity['uid'];
            if ( imap_msgno( $stream, $uid ) <= 0 ) {
                return new \WP_Error( 'mvm_mail_attachment_message_missing', 'Het bericht is niet beschikbaar.' );
            }

            $structure = imap_fetchstructure( $stream, $uid, FT_UID );
            if ( ! is_object( $structure ) ) {
                return new \WP_Error( 'mvm_mail_attachment_structure', 'De bijlagenstructuur kon niet worden gelezen.' );
            }
            $part = self::part_at( $structure, $part_number );
            if ( is_wp_error( $part ) ) {
                return $part;
            }

            $declared_size = max( 0, (int) ( $part->bytes ?? 0 ) );
            if ( $declared_size > self::MAX_BYTES ) {
                return new \WP_Error( 'mvm_mail_attachment_too_large', 'Deze bijlage overschrijdt de veilige downloadgrens.' );
            }

            $raw = imap_fetchbody( $stream, $uid, $part_number, FT_UID | FT_PEEK );
            if ( false === $raw ) {
                return new \WP_Error( 'mvm_mail_attachment_fetch', 'De bijlage kon niet worden gelezen.' );
            }
            $decoded = self::decode_transfer( (string) $raw, (int) ( $part->encoding ?? 0 ) );
            if ( is_wp_error( $decoded ) ) {
                return $decoded;
            }
            if ( strlen( $decoded ) > self::MAX_BYTES ) {
                return new \WP_Error( 'mvm_mail_attachment_too_large', 'Deze bijlage overschrijdt de veilige downloadgrens.' );
            }

            $name = sanitize_file_name( self::part_filename( $part ) );
            if ( '' === $name ) {
                $name = 'attachment-' . str_replace( '.', '-', $part_number ) . '.bin';
            }
            $mime = self::mime_type( (int) ( $part->type ?? 3 ), strtolower( (string) ( $part->subtype ?? '' ) ) );

            return array(
                'id'                   => $part_number,
                'name'                 => $name,
                'mimeType'             => $mime,
                'sizeBytes'            => strlen( $decoded ),
                'content'              => $decoded,
                'disposition'          => 'attachment',
                'searchVisibility'     => 'none',
                'aiVisibility'         => 'none',
                'auditBodyAllowed'     => false,
                'externalContentLoad'  => false,
                'publicMediaPromotion' => false,
            );
        } finally {
            imap_close( $stream );
        }
    }

    /** @return array{remote:string,uid:int}|\WP_Error */
    private static function message_identity( string $folder_id, string $message_id ): array|\WP_Error {
        $folder_id = trim( $folder_id );
        $message_id = trim( $message_id );
        if (
            '' === $folder_id
            || strlen( $folder_id ) > 128
            || 1 !== preg_match( '/^[A-Za-z0-9_-]+$/', $folder_id )
            || strlen( $message_id ) > 260
            || 1 !== preg_match( '/^([A-Za-z0-9_-]+)\.(\d+)$/', $message_id, $matches )
            || ! hash_equals( $folder_id, $matches[1] )
        ) {
            return new \WP_Error( 'mvm_mail_attachment_reference', 'Ongeldige folder-scoped berichtreferentie.' );
        }

        $uid = (int) $matches[2];
        if ( $uid < 1 ) {
            return new \WP_Error( 'mvm_mail_attachment_reference', 'Ongeldige folder-scoped berichtreferentie.' );
        }
        $remote = self::folder_from_id( $folder_id );
        if ( is_wp_error( $remote ) ) {
            return $remote;
        }
        return array( 'remote' => $remote, 'uid' => $uid );
    }

    /** @return string|\WP_Error */
    private static function part_number( string $value ): string|\WP_Error {
        $value = trim( $value );
        if (
            '' === $value
            || strlen( $value ) > 64
            || 1 !== preg_match( '/^\d+(?:\.\d+){0,' . ( self::MAX_PART_DEPTH - 1 ) . '}$/', $value )
        ) {
            return new \WP_Error( 'mvm_mail_attachment_part', 'Ongeldige bijlagereferentie.' );
        }
        foreach ( explode( '.', $value ) as $segment ) {
            if ( (int) $segment < 1 ) {
                return new \WP_Error( 'mvm_mail_attachment_part', 'Ongeldige bijlagereferentie.' );
            }
        }
        return $value;
    }

    /** @return object|\WP_Error */
    private static function part_at( object $structure, string $part_number ): object {
        $segments = array_map( 'intval', explode( '.', $part_number ) );
        $current = $structure;
        foreach ( $segments as $segment ) {
            $parts = isset( $current->parts ) && is_array( $current->parts ) ? $current->parts : array();
            $index = $segment - 1;
            if ( ! isset( $parts[ $index ] ) || ! is_object( $parts[ $index ] ) ) {
                return new \WP_Error( 'mvm_mail_attachment_part_missing', 'De bijlage bestaat niet in dit bericht.' );
            }
            $current = $parts[ $index ];
        }
        return $current;
    }

    /** @return string|\WP_Error */
    private static function decode_transfer( string $body, int $encoding ): string|\WP_Error {
        if ( 3 === $encoding ) {
            $decoded = base64_decode( $body, true );
            return false === $decoded
                ? new \WP_Error( 'mvm_mail_attachment_decode', 'De bijlage kon niet veilig worden gedecodeerd.' )
                : $decoded;
        }
        if ( 4 === $encoding ) {
            return quoted_printable_decode( $body );
        }
        return $body;
    }

    private static function part_filename( object $part ): string {
        foreach ( array( 'dparameters', 'parameters' ) as $property ) {
            $params = is_array( $part->{$property} ?? null ) ? $part->{$property} : array();
            foreach ( $params as $param ) {
                if ( ! is_object( $param ) ) {
                    continue;
                }
                $attribute = strtolower( (string) ( $param->attribute ?? '' ) );
                if ( in_array( $attribute, array( 'filename', 'name' ), true ) ) {
                    return self::decode_header( (string) ( $param->value ?? '' ) );
                }
            }
        }
        return '';
    }

    private static function decode_header( string $value ): string {
        if ( '' === $value || ! function_exists( 'imap_mime_header_decode' ) ) {
            return sanitize_text_field( $value );
        }
        $parts = imap_mime_header_decode( $value );
        $output = '';
        foreach ( is_array( $parts ) ? $parts : array() as $part ) {
            $text = (string) ( $part->text ?? '' );
            $charset = strtoupper( (string) ( $part->charset ?? 'UTF-8' ) );
            if ( '' !== $text && ! in_array( $charset, array( 'DEFAULT', 'UTF-8' ), true ) && function_exists( 'mb_convert_encoding' ) ) {
                $text = (string) @mb_convert_encoding( $text, 'UTF-8', $charset );
            }
            $output .= $text;
        }
        return sanitize_text_field( $output );
    }

    private static function mime_type( int $type, string $subtype ): string {
        $top = array(
            0 => 'text',
            1 => 'multipart',
            2 => 'message',
            3 => 'application',
            4 => 'audio',
            5 => 'image',
            6 => 'video',
        )[ $type ] ?? 'application';
        $subtype = '' !== $subtype ? $subtype : 'octet-stream';
        return sanitize_mime_type( $top . '/' . $subtype );
    }

    /** @return string|\WP_Error */
    private static function folder_from_id( string $id ): string|\WP_Error {
        $pad = strlen( $id ) % 4;
        $encoded = strtr( $id, '-_', '+/' ) . ( 0 === $pad ? '' : str_repeat( '=', 4 - $pad ) );
        $decoded = base64_decode( $encoded, true );
        if (
            false === $decoded
            || '' === $decoded
            || str_contains( $decoded, "\0" )
            || str_contains( $decoded, "\r" )
            || str_contains( $decoded, "\n" )
        ) {
            return new \WP_Error( 'mvm_mail_folder_invalid', 'Ongeldige mailboxmap.' );
        }
        return $decoded;
    }

    private function configured( string $mailbox_id ): bool {
        $configured = defined( 'MVM_HUB_MAILBOX_ID' ) ? sanitize_key( (string) MVM_HUB_MAILBOX_ID ) : 'editorial';
        if ( sanitize_key( $mailbox_id ) !== $configured ) {
            return false;
        }
        foreach ( array( 'MVM_HUB_IMAP_HOST', 'MVM_HUB_IMAP_PORT', 'MVM_HUB_IMAP_USERNAME', 'MVM_HUB_IMAP_PASSWORD' ) as $constant ) {
            if ( ! defined( $constant ) || '' === trim( (string) constant( $constant ) ) ) {
                return false;
            }
        }
        return true;
    }

    private function server_prefix(): string {
        $host = preg_replace( '/[^A-Za-z0-9.:-]/', '', (string) MVM_HUB_IMAP_HOST );
        $port = max( 1, min( 65535, (int) MVM_HUB_IMAP_PORT ) );
        $enc  = defined( 'MVM_HUB_IMAP_ENCRYPTION' ) ? sanitize_key( (string) MVM_HUB_IMAP_ENCRYPTION ) : 'ssl';
        $mode = 'tls' === $enc ? '/imap/tls' : ( 'none' === $enc ? '/imap' : '/imap/ssl' );
        return '{' . $host . ':' . $port . $mode . '}';
    }
}
