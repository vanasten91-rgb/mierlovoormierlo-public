<?php

namespace MVM\Hub\Integrations\Mail;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Full mailbox provider: IMAP for folders/messages/state, dedicated SMTP for
 * delivery. Credentials remain server-side constants and are never returned.
 */
final class Dedicated_IMAP_SMTP_Mail_Provider implements Mail_Provider {
    public function __construct( private readonly Dedicated_SMTP_Mail_Provider $smtp ) {}

    public function list_folders( string $mailbox_id ): array {
        $stream = $this->connect( $mailbox_id, 'INBOX', true );
        if ( is_wp_error( $stream ) ) {
            return array();
        }
        try {
            $server = $this->server_prefix();
            $folders = imap_getmailboxes( $stream, $server, '*' );
            if ( ! is_array( $folders ) ) {
                return array();
            }
            $items = array();
            foreach ( array_slice( $folders, 0, 200 ) as $folder ) {
                $full = is_object( $folder ) ? (string) ( $folder->name ?? '' ) : '';
                if ( '' === $full || ! str_starts_with( $full, $server ) ) {
                    continue;
                }
                $remote = substr( $full, strlen( $server ) );
                $display = $this->decode_folder_name( $remote );
                if ( '' === $display ) {
                    continue;
                }
                $special = self::special_use( $display );
                $items[] = array(
                    'id'          => self::folder_id( $remote ),
                    'name'        => $display,
                    'specialUse'  => $special,
                    'system'      => '' !== $special,
                    'readOnly'    => false,
                    'unreadCount' => 0,
                );
            }
            return array_values( $items );
        } finally {
            imap_close( $stream );
        }
    }

    public function list_messages( string $mailbox_id, string $folder_id, array $query = array() ): array {
        $remote = self::folder_from_id( $folder_id );
        if ( is_wp_error( $remote ) ) {
            return array( 'messages' => array(), 'total' => 0, 'hasMore' => false );
        }
        $stream = $this->connect( $mailbox_id, $remote );
        if ( is_wp_error( $stream ) ) {
            return array( 'messages' => array(), 'total' => 0, 'hasMore' => false );
        }
        try {
            $limit  = max( 1, min( 51, (int) ( $query['limit'] ?? 20 ) ) );
            $offset = max( 0, min( 5000, (int) ( $query['offset'] ?? 0 ) ) );
            $criteria = self::search_criteria( $query );
            $reverse = 'asc' !== sanitize_key( (string) ( $query['direction'] ?? 'desc' ) );
            $uids = imap_sort( $stream, SORTDATE, $reverse, SE_UID, $criteria, 'UTF-8' );
            $uids = is_array( $uids ) ? array_values( $uids ) : array();
            $total = count( $uids );
            $slice = array_slice( $uids, $offset, $limit );
            $messages = array();
            foreach ( $slice as $uid ) {
                $overview = imap_fetch_overview( $stream, (string) absint( $uid ), FT_UID );
                $row = is_array( $overview ) && isset( $overview[0] ) ? $overview[0] : null;
                if ( ! is_object( $row ) ) {
                    continue;
                }
                $messages[] = array(
                    'id'      => (string) absint( $uid ),
                    'subject' => self::decode_header( (string) ( $row->subject ?? '' ) ),
                    'from'    => self::decode_header( (string) ( $row->from ?? '' ) ),
                    'fromAddress' => self::first_address_text( (string) ( $row->from ?? '' ) ),
                    'date'    => sanitize_text_field( (string) ( $row->date ?? '' ) ),
                    'seen'    => ! empty( $row->seen ),
                    'flagged' => ! empty( $row->flagged ),
                    'size'    => max( 0, (int) ( $row->size ?? 0 ) ),
                );
            }
            return array(
                'messages' => $messages,
                'total'    => $total,
                'hasMore'  => $total > $offset + count( $messages ),
                'readonly' => false,
            );
        } finally {
            imap_close( $stream );
        }
    }

    /** @return array<string,mixed>|\WP_Error */
    public function get_message( string $mailbox_id, string $message_id ): array|\WP_Error {
        if ( ! ctype_digit( $message_id ) || (int) $message_id <= 0 ) {
            return new \WP_Error( 'mvm_mail_message_invalid', 'Ongeldig bericht-ID.' );
        }
        $stream = $this->connect( $mailbox_id, 'INBOX' );
        if ( is_wp_error( $stream ) ) {
            return $stream;
        }
        try {
            $uid = (int) $message_id;
            $sequence = imap_msgno( $stream, $uid );
            if ( $sequence <= 0 ) {
                return new \WP_Error( 'mvm_mail_message_missing', 'Het bericht is niet beschikbaar.' );
            }
            $overview = imap_fetch_overview( $stream, (string) $uid, FT_UID );
            $row = is_array( $overview ) && isset( $overview[0] ) ? $overview[0] : null;
            if ( ! is_object( $row ) ) {
                return new \WP_Error( 'mvm_mail_message_missing', 'Het bericht is niet beschikbaar.' );
            }
            $header = imap_headerinfo( $stream, $sequence );
            $structure = imap_fetchstructure( $stream, $uid, FT_UID );
            $parts = $structure ? self::extract_parts( $stream, $uid, $structure ) : array( 'text' => '', 'html' => '', 'attachments' => array() );
            $raw_header = (string) imap_fetchheader( $stream, $uid, FT_UID );

            return array(
                'id'          => (string) $uid,
                'subject'     => self::decode_header( (string) ( $row->subject ?? '' ) ),
                'from'        => self::decode_header( (string) ( $row->from ?? '' ) ),
                'fromAddress' => self::first_address( is_object( $header ) ? ( $header->from ?? array() ) : array() ),
                'to'          => self::addresses( is_object( $header ) ? ( $header->to ?? array() ) : array() ),
                'cc'          => self::addresses( is_object( $header ) ? ( $header->cc ?? array() ) : array() ),
                'date'        => sanitize_text_field( (string) ( $row->date ?? '' ) ),
                'seen'        => ! empty( $row->seen ),
                'flagged'     => ! empty( $row->flagged ),
                'size'        => max( 0, (int) ( $row->size ?? 0 ) ),
                'text'        => (string) $parts['text'],
                'html'        => (string) $parts['html'],
                'attachments' => (array) $parts['attachments'],
                'inReplyTo'   => self::header_message_id( $raw_header, 'In-Reply-To' ),
                'references'  => self::header_references( $raw_header ),
            );
        } finally {
            imap_close( $stream );
        }
    }

    public function save_draft( string $mailbox_id, array $draft, ?string $draft_id = null ): array|\WP_Error {
        unset( $mailbox_id, $draft, $draft_id );
        return new \WP_Error( 'mvm_mail_draft_local_canonical', 'Concepten worden veilig door de centrale private conceptopslag beheerd.' );
    }

    /** @return array<string,mixed>|\WP_Error */
    public function create_folder( string $mailbox_id, string $name, ?string $parent_id = null ): array|\WP_Error {
        $stream = $this->connect( $mailbox_id, 'INBOX', true );
        if ( is_wp_error( $stream ) ) {
            return $stream;
        }
        try {
            $name = self::safe_folder_name( $name );
            if ( '' === $name ) {
                return new \WP_Error( 'mvm_mail_folder_name', 'Ongeldige mapnaam.' );
            }
            $parent = '';
            if ( null !== $parent_id ) {
                $decoded = self::folder_from_id( $parent_id );
                if ( is_wp_error( $decoded ) ) {
                    return $decoded;
                }
                $parent = $decoded . $this->delimiter();
            }
            $remote = $parent . $this->encode_folder_name( $name );
            $ok = imap_createmailbox( $stream, $this->server_prefix() . $remote );
            return $ok
                ? array( 'id' => self::folder_id( $remote ), 'name' => $name, 'system' => false, 'readOnly' => false )
                : new \WP_Error( 'mvm_mail_folder_create', 'Map kon niet worden aangemaakt.' );
        } finally {
            imap_close( $stream );
        }
    }

    public function rename_folder( string $mailbox_id, string $folder_id, string $name ): bool|\WP_Error {
        $remote = self::folder_from_id( $folder_id );
        if ( is_wp_error( $remote ) || '' !== self::special_use( $this->decode_folder_name( is_string( $remote ) ? $remote : '' ) ) ) {
            return new \WP_Error( 'mvm_mail_folder_protected', 'Deze map kan niet worden hernoemd.' );
        }
        $stream = $this->connect( $mailbox_id, 'INBOX', true );
        if ( is_wp_error( $stream ) ) {
            return $stream;
        }
        try {
            $name = self::safe_folder_name( $name );
            if ( '' === $name ) {
                return new \WP_Error( 'mvm_mail_folder_name', 'Ongeldige mapnaam.' );
            }
            $delimiter = $this->delimiter();
            $parent = str_contains( $remote, $delimiter ) ? substr( $remote, 0, strrpos( $remote, $delimiter ) + strlen( $delimiter ) ) : '';
            $new_remote = $parent . $this->encode_folder_name( $name );
            return imap_renamemailbox( $stream, $this->server_prefix() . $remote, $this->server_prefix() . $new_remote )
                ? true
                : new \WP_Error( 'mvm_mail_folder_rename', 'Map kon niet worden hernoemd.' );
        } finally {
            imap_close( $stream );
        }
    }

    public function delete_folder( string $mailbox_id, string $folder_id ): bool|\WP_Error {
        $remote = self::folder_from_id( $folder_id );
        if ( is_wp_error( $remote ) || '' !== self::special_use( $this->decode_folder_name( is_string( $remote ) ? $remote : '' ) ) ) {
            return new \WP_Error( 'mvm_mail_folder_protected', 'Deze map kan niet worden verwijderd.' );
        }
        $stream = $this->connect( $mailbox_id, 'INBOX', true );
        if ( is_wp_error( $stream ) ) {
            return $stream;
        }
        try {
            return imap_deletemailbox( $stream, $this->server_prefix() . $remote )
                ? true
                : new \WP_Error( 'mvm_mail_folder_delete', 'Map kon niet worden verwijderd.' );
        } finally {
            imap_close( $stream );
        }
    }

    public function move_message( string $mailbox_id, string $message_id, string $folder_id ): bool|\WP_Error {
        $target = self::folder_from_id( $folder_id );
        if ( is_wp_error( $target ) || ! ctype_digit( $message_id ) ) {
            return new \WP_Error( 'mvm_mail_move_invalid', 'Ongeldige verplaatsactie.' );
        }
        $stream = $this->connect( $mailbox_id, 'INBOX' );
        if ( is_wp_error( $stream ) ) {
            return $stream;
        }
        try {
            $ok = imap_mail_move( $stream, $message_id, $target, CP_UID );
            if ( $ok ) {
                imap_expunge( $stream );
                return true;
            }
            return new \WP_Error( 'mvm_mail_move_failed', 'Bericht kon niet worden verplaatst.' );
        } finally {
            imap_close( $stream );
        }
    }

    public function set_read_state( string $mailbox_id, string $message_id, bool $is_read ): bool|\WP_Error {
        return $this->set_flag( $mailbox_id, $message_id, '\\Seen', $is_read );
    }

    public function set_flagged_state( string $mailbox_id, string $message_id, bool $is_flagged ): bool|\WP_Error {
        return $this->set_flag( $mailbox_id, $message_id, '\\Flagged', $is_flagged );
    }

    public function deliver( string $mailbox_id, array $message, string $idempotency_key ): array|\WP_Error {
        return $this->smtp->deliver( $mailbox_id, $message, $idempotency_key );
    }

    public function capabilities( string $mailbox_id ): array {
        $imap = function_exists( 'imap_open' ) && $this->configured( $mailbox_id );
        $smtp = $this->smtp->capabilities( $mailbox_id );
        return array(
            'read'         => $imap,
            'folders'      => $imap,
            'drafts'       => true,
            'move'         => $imap,
            'flags'        => $imap,
            'attachments'  => true,
            'send'         => true === ( $smtp['send'] ?? false ),
            'remoteImages' => false,
        );
    }

    /** @return resource|\IMAP\Connection|\WP_Error */
    private function connect( string $mailbox_id, string $remote, bool $half_open = false ): mixed {
        if ( ! function_exists( 'imap_open' ) || ! $this->configured( $mailbox_id ) ) {
            return new \WP_Error( 'mvm_mail_imap_unavailable', 'IMAP is niet beschikbaar of niet geconfigureerd.' );
        }
        $mailbox = $this->server_prefix() . $remote;
        $flags = $half_open && defined( 'OP_HALFOPEN' ) ? OP_HALFOPEN : 0;
        $stream = @imap_open( $mailbox, (string) MVM_HUB_IMAP_USERNAME, (string) MVM_HUB_IMAP_PASSWORD, $flags, 1 );
        return false === $stream ? new \WP_Error( 'mvm_mail_imap_connect', 'De mailbox kon niet veilig worden geopend.' ) : $stream;
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

    private function delimiter(): string {
        return defined( 'MVM_HUB_IMAP_DELIMITER' ) && '' !== (string) MVM_HUB_IMAP_DELIMITER ? mb_substr( (string) MVM_HUB_IMAP_DELIMITER, 0, 1 ) : '/';
    }

    private function encode_folder_name( string $name ): string {
        return function_exists( 'imap_utf7_encode' ) ? (string) imap_utf7_encode( $name ) : $name;
    }

    private function decode_folder_name( string $name ): string {
        $decoded = function_exists( 'imap_utf7_decode' ) ? (string) imap_utf7_decode( $name ) : $name;
        return sanitize_text_field( $decoded );
    }

    private function set_flag( string $mailbox_id, string $message_id, string $flag, bool $enabled ): bool|\WP_Error {
        if ( ! ctype_digit( $message_id ) || (int) $message_id <= 0 ) {
            return new \WP_Error( 'mvm_mail_message_invalid', 'Ongeldig bericht-ID.' );
        }
        $stream = $this->connect( $mailbox_id, 'INBOX' );
        if ( is_wp_error( $stream ) ) {
            return $stream;
        }
        try {
            $ok = $enabled
                ? imap_setflag_full( $stream, $message_id, $flag, ST_UID )
                : imap_clearflag_full( $stream, $message_id, $flag, ST_UID );
            return $ok ? true : new \WP_Error( 'mvm_mail_flag_failed', 'Berichtstatus kon niet worden gewijzigd.' );
        } finally {
            imap_close( $stream );
        }
    }

    /** @return array{text:string,html:string,attachments:array<int,array<string,mixed>>} */
    private static function extract_parts( mixed $stream, int $uid, object $structure, string $prefix = '' ): array {
        $result = array( 'text' => '', 'html' => '', 'attachments' => array() );
        $parts = isset( $structure->parts ) && is_array( $structure->parts ) ? $structure->parts : array();
        if ( array() === $parts ) {
            $body = (string) imap_body( $stream, $uid, FT_UID | FT_PEEK );
            $decoded = self::decode_transfer( $body, (int) ( $structure->encoding ?? 0 ) );
            $subtype = strtoupper( (string) ( $structure->subtype ?? 'PLAIN' ) );
            $result['html' === strtolower( $subtype ) ? 'html' : 'text'] = $decoded;
            return $result;
        }
        foreach ( $parts as $index => $part ) {
            if ( ! is_object( $part ) ) {
                continue;
            }
            $number = '' === $prefix ? (string) ( $index + 1 ) : $prefix . '.' . ( $index + 1 );
            if ( isset( $part->parts ) && is_array( $part->parts ) ) {
                $nested = self::extract_parts( $stream, $uid, $part, $number );
                $result['text'] .= $nested['text'];
                $result['html'] .= $nested['html'];
                $result['attachments'] = array_merge( $result['attachments'], $nested['attachments'] );
                continue;
            }
            $filename = self::part_filename( $part );
            $type = (int) ( $part->type ?? 0 );
            $subtype = strtolower( (string) ( $part->subtype ?? '' ) );
            if ( '' !== $filename ) {
                $result['attachments'][] = array(
                    'id'   => $number,
                    'name' => sanitize_file_name( $filename ),
                    'mime' => self::mime_type( $type, $subtype ),
                    'size' => max( 0, (int) ( $part->bytes ?? 0 ) ),
                );
                continue;
            }
            if ( 0 === $type && in_array( $subtype, array( 'plain', 'html' ), true ) ) {
                $body = (string) imap_fetchbody( $stream, $uid, $number, FT_UID | FT_PEEK );
                $decoded = self::decode_transfer( $body, (int) ( $part->encoding ?? 0 ) );
                $result[ 'html' === $subtype ? 'html' : 'text' ] .= $decoded;
            }
        }
        return $result;
    }

    private static function decode_transfer( string $body, int $encoding ): string {
        return match ( $encoding ) {
            3 => (string) base64_decode( $body, true ),
            4 => quoted_printable_decode( $body ),
            default => $body,
        };
    }

    private static function part_filename( object $part ): string {
        foreach ( array( 'dparameters', 'parameters' ) as $property ) {
            foreach ( is_array( $part->{$property} ?? null ) ? $part->{$property} : array() as $parameter ) {
                $attribute = strtolower( (string) ( $parameter->attribute ?? '' ) );
                if ( in_array( $attribute, array( 'filename', 'name' ), true ) ) {
                    return self::decode_header( (string) ( $parameter->value ?? '' ) );
                }
            }
        }
        return '';
    }

    private static function mime_type( int $type, string $subtype ): string {
        $top = array( 0 => 'text', 1 => 'multipart', 2 => 'message', 3 => 'application', 4 => 'audio', 5 => 'image', 6 => 'video' )[ $type ] ?? 'application';
        return sanitize_mime_type( $top . '/' . ( '' !== $subtype ? $subtype : 'octet-stream' ) );
    }

    private static function decode_header( string $value ): string {
        if ( '' === $value || ! function_exists( 'imap_mime_header_decode' ) ) {
            return sanitize_text_field( $value );
        }
        $parts = imap_mime_header_decode( $value );
        $out = '';
        foreach ( is_array( $parts ) ? $parts : array() as $part ) {
            $text = (string) ( $part->text ?? '' );
            $charset = strtoupper( (string) ( $part->charset ?? 'UTF-8' ) );
            if ( '' !== $text && ! in_array( $charset, array( 'DEFAULT', 'UTF-8' ), true ) && function_exists( 'mb_convert_encoding' ) ) {
                $text = @mb_convert_encoding( $text, 'UTF-8', $charset );
            }
            $out .= $text;
        }
        return sanitize_text_field( $out );
    }

    /** @return array<int,string> */
    private static function addresses( mixed $values ): array {
        $result = array();
        foreach ( is_array( $values ) ? array_slice( $values, 0, 50 ) : array() as $address ) {
            $mailbox = sanitize_text_field( (string) ( $address->mailbox ?? '' ) );
            $host = sanitize_text_field( (string) ( $address->host ?? '' ) );
            $email = sanitize_email( $mailbox . '@' . $host );
            if ( '' !== $email && false !== is_email( $email ) ) {
                $result[] = strtolower( $email );
            }
        }
        return array_values( array_unique( $result ) );
    }

    private static function first_address( mixed $values ): string {
        $addresses = self::addresses( $values );
        return (string) ( $addresses[0] ?? '' );
    }

    private static function first_address_text( string $value ): string {
        if ( '' === trim( $value ) || ! function_exists( 'imap_rfc822_parse_adrlist' ) ) {
            return '';
        }
        $parsed = imap_rfc822_parse_adrlist( $value, '' );
        return self::first_address( is_array( $parsed ) ? $parsed : array() );
    }

    private static function header_message_id( string $headers, string $name ): string {
        if ( 1 !== preg_match( '/^' . preg_quote( $name, '/' ) . ':\s*(<[^<>\s]+@[^<>\s]+>)/mi', $headers, $match ) ) {
            return '';
        }
        return mb_substr( (string) $match[1], 0, 998 );
    }

    /** @return array<int,string> */
    private static function header_references( string $headers ): array {
        if ( 1 !== preg_match( '/^References:\s*([^\r\n]*(?:\r?\n[ \t]+[^\r\n]*)*)/mi', $headers, $match ) ) {
            return array();
        }
        preg_match_all( '/<[^<>\s]+@[^<>\s]+>/', (string) $match[1], $ids );
        return array_slice( array_values( array_unique( $ids[0] ?? array() ) ), -20 );
    }

    private static function folder_id( string $remote ): string {
        return rtrim( strtr( base64_encode( $remote ), '+/', '-_' ), '=' );
    }

    /** @return string|\WP_Error */
    private static function folder_from_id( string $id ): string|\WP_Error {
        $id = trim( $id );
        if ( '' === $id || strlen( $id ) > 128 || 1 !== preg_match( '/^[A-Za-z0-9_-]+$/', $id ) ) {
            return new \WP_Error( 'mvm_mail_folder_invalid', 'Ongeldige mailboxmap.' );
        }
        $pad = strlen( $id ) % 4;
        $encoded = strtr( $id, '-_', '+/' ) . ( 0 === $pad ? '' : str_repeat( '=', 4 - $pad ) );
        $decoded = base64_decode( $encoded, true );
        if ( false === $decoded || '' === $decoded || str_contains( $decoded, "\0" ) || str_contains( $decoded, "\r" ) || str_contains( $decoded, "\n" ) ) {
            return new \WP_Error( 'mvm_mail_folder_invalid', 'Ongeldige mailboxmap.' );
        }
        return $decoded;
    }

    private static function special_use( string $name ): string {
        $leaf = strtolower( trim( preg_replace( '#^.*/#', '', $name ) ) );
        return match ( $leaf ) {
            'inbox' => 'inbox',
            'sent', 'sent items', 'verzonden' => 'sent',
            'drafts', 'concepten' => 'drafts',
            'archive', 'archief' => 'archive',
            'trash', 'deleted items', 'prullenbak' => 'trash',
            'junk', 'spam', 'ongewenst' => 'junk',
            default => '',
        };
    }

    private static function safe_folder_name( string $name ): string {
        $name = trim( sanitize_text_field( str_replace( array( "\r", "\n", "\0", '/', '\\' ), ' ', $name ) ) );
        return in_array( $name, array( '', '.', '..' ), true ) ? '' : mb_substr( $name, 0, 100 );
    }

    /** @param array<string,mixed> $query */
    private static function search_criteria( array $query ): string {
        $criteria = match ( sanitize_key( (string) ( $query['state'] ?? 'all' ) ) ) {
            'unread' => 'UNSEEN',
            'read'   => 'SEEN',
            'flagged'=> 'FLAGGED',
            default  => 'ALL',
        };
        $search = trim( sanitize_text_field( (string) ( $query['search'] ?? '' ) ) );
        if ( '' !== $search ) {
            $search = str_replace( array( '\\', '"' ), array( '\\\\', '\\"' ), mb_substr( $search, 0, 120 ) );
            $criteria .= ' TEXT "' . $search . '"';
        }
        return $criteria;
    }
}
