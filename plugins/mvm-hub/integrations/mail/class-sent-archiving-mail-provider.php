<?php

namespace MVM\Hub\Integrations\Mail;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Adds a canonical IMAP Sent copy after successful dedicated SMTP delivery.
 *
 * The SMTP provider hands the exact generated MIME to this decorator only in
 * process memory. The MIME is stripped before results leave the provider stack.
 * Sent-folder readiness is proven before SMTP, so known archive failures fail
 * closed without sending. A rare append failure after SMTP remains terminal and
 * is reported as delivered_unarchived by the application service to prevent a
 * duplicate retry.
 */
final class Sent_Archiving_Mail_Provider implements Mail_Provider {
    private const MAX_SENT_MIME_BYTES = 50 * 1024 * 1024;

    public function __construct( private readonly Mail_Provider $inner ) {}

    public function list_folders( string $mailbox_id ): array {
        return $this->inner->list_folders( $mailbox_id );
    }

    public function list_messages( string $mailbox_id, string $folder_id, array $query = array() ): array {
        return $this->inner->list_messages( $mailbox_id, $folder_id, $query );
    }

    public function get_message( string $mailbox_id, string $message_id ): array|\WP_Error {
        return $this->inner->get_message( $mailbox_id, $message_id );
    }

    public function save_draft( string $mailbox_id, array $draft, ?string $draft_id = null ): array|\WP_Error {
        return $this->inner->save_draft( $mailbox_id, $draft, $draft_id );
    }

    public function create_folder( string $mailbox_id, string $name, ?string $parent_id = null ): array|\WP_Error {
        return $this->inner->create_folder( $mailbox_id, $name, $parent_id );
    }

    public function rename_folder( string $mailbox_id, string $folder_id, string $name ): bool|\WP_Error {
        return $this->inner->rename_folder( $mailbox_id, $folder_id, $name );
    }

    public function delete_folder( string $mailbox_id, string $folder_id ): bool|\WP_Error {
        return $this->inner->delete_folder( $mailbox_id, $folder_id );
    }

    public function move_message( string $mailbox_id, string $message_id, string $folder_id ): bool|\WP_Error {
        return $this->inner->move_message( $mailbox_id, $message_id, $folder_id );
    }

    public function set_read_state( string $mailbox_id, string $message_id, bool $is_read ): bool|\WP_Error {
        return $this->inner->set_read_state( $mailbox_id, $message_id, $is_read );
    }

    public function set_flagged_state( string $mailbox_id, string $message_id, bool $is_flagged ): bool|\WP_Error {
        return $this->inner->set_flagged_state( $mailbox_id, $message_id, $is_flagged );
    }

    /** @return array<string,mixed>|\WP_Error */
    public function deliver( string $mailbox_id, array $message, string $idempotency_key ): array|\WP_Error {
        $sent_remote = $this->sent_folder_remote( $mailbox_id );
        if ( is_wp_error( $sent_remote ) ) {
            return $sent_remote;
        }

        // Open the IMAP session before SMTP so a known-bad archive path never
        // allows an external delivery side effect.
        $stream = $this->connect( $mailbox_id );
        if ( is_wp_error( $stream ) ) {
            return $stream;
        }

        try {
            $result = $this->inner->deliver( $mailbox_id, $message, $idempotency_key );
            if ( is_wp_error( $result ) ) {
                return $result;
            }

            $sent_mime = (string) ( $result['sentMime'] ?? '' );
            unset( $result['sentMime'] );

            if ( '' === $sent_mime || strlen( $sent_mime ) > self::MAX_SENT_MIME_BYTES ) {
                $result['sentArchived']     = false;
                $result['sentArchiveStatus'] = 'failed';
                $result['sentArchiveCode']   = 'mime_invalid';
                return $result;
            }

            $target = $this->server_prefix() . $sent_remote;
            $stored = @imap_append(
                $stream,
                $target,
                $sent_mime,
                '\\Seen',
                gmdate( 'd-M-Y H:i:s O' )
            );

            // Drain extension errors here so no server detail can leak through
            // later unrelated IMAP calls or public error payloads.
            if ( function_exists( 'imap_errors' ) ) {
                imap_errors();
            }

            $result['sentArchived']      = true === $stored;
            $result['sentArchiveStatus'] = true === $stored ? 'archived' : 'failed';
            if ( true !== $stored ) {
                $result['sentArchiveCode'] = 'append_failed';
            }
            return $result;
        } finally {
            imap_close( $stream );
        }
    }

    public function capabilities( string $mailbox_id ): array {
        $capabilities = $this->inner->capabilities( $mailbox_id );
        $sent_remote  = $this->sent_folder_remote( $mailbox_id );
        $capabilities['sentArchive'] = ! is_wp_error( $sent_remote );
        return $capabilities;
    }

    /** @return string|\WP_Error */
    private function sent_folder_remote( string $mailbox_id ): string|\WP_Error {
        if ( ! $this->configured( $mailbox_id ) || ! function_exists( 'imap_append' ) ) {
            return new \WP_Error( 'mvm_mail_sent_archive_unavailable', 'De map Verzonden is niet veilig beschikbaar.' );
        }

        $matches = array();
        foreach ( $this->inner->list_folders( $mailbox_id ) as $folder ) {
            if ( ! is_array( $folder ) || 'sent' !== (string) ( $folder['specialUse'] ?? '' ) ) {
                continue;
            }
            $remote = self::folder_from_id( (string) ( $folder['id'] ?? '' ) );
            if ( ! is_wp_error( $remote ) ) {
                $matches[$remote] = true;
            }
        }

        $matches = array_keys( $matches );
        if ( 1 !== count( $matches ) ) {
            return new \WP_Error( 'mvm_mail_sent_archive_ambiguous', 'De map Verzonden kon niet eenduidig worden vastgesteld.' );
        }
        return (string) $matches[0];
    }

    /** @return resource|\IMAP\Connection|\WP_Error */
    private function connect( string $mailbox_id ): mixed {
        if ( ! $this->configured( $mailbox_id ) ) {
            return new \WP_Error( 'mvm_mail_sent_archive_unavailable', 'IMAP-archivering is niet beschikbaar.' );
        }
        $stream = @imap_open(
            $this->server_prefix() . 'INBOX',
            (string) MVM_HUB_IMAP_USERNAME,
            (string) MVM_HUB_IMAP_PASSWORD,
            0,
            1
        );
        if ( false === $stream ) {
            if ( function_exists( 'imap_errors' ) ) {
                imap_errors();
            }
            return new \WP_Error( 'mvm_mail_sent_archive_connect', 'De map Verzonden kon niet veilig worden geopend.' );
        }
        return $stream;
    }

    private function configured( string $mailbox_id ): bool {
        $configured = defined( 'MVM_HUB_MAILBOX_ID' ) ? sanitize_key( (string) MVM_HUB_MAILBOX_ID ) : 'editorial';
        if ( sanitize_key( $mailbox_id ) !== $configured || ! function_exists( 'imap_open' ) ) {
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

    /** @return string|\WP_Error */
    private static function folder_from_id( string $id ): string|\WP_Error {
        $id = trim( $id );
        if ( '' === $id || strlen( $id ) > 128 || 1 !== preg_match( '/^[A-Za-z0-9_-]+$/', $id ) ) {
            return new \WP_Error( 'mvm_mail_folder_invalid', 'Ongeldige mailboxmap.' );
        }
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
}
