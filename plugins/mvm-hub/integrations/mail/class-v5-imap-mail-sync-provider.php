<?php

namespace MVM\Hub\Integrations\Mail;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Bounded IMAP receive-sync implementation for Mail V2.
 *
 * The provider snapshots only UID/state metadata. It never stores subjects,
 * bodies, addresses, filenames or credentials. A hard mailbox-size bound keeps
 * synchronization predictable; oversized folders fail closed and require a
 * provider/API strategy with stronger delta primitives.
 */
final class V5_IMAP_Mail_Sync_Provider implements Mail_Sync_Provider {
    private const MAX_BATCH           = 200;
    private const MAX_FOLDER_MESSAGES = 5000;
    private const CURSOR_VERSION      = 1;

    public function __construct(
        private readonly Mail_Sync_State_Store $state_store
    ) {}

    /** @return array<string,mixed>|\WP_Error */
    public function initial_sync( string $mailbox_id, string $folder_id, int $limit = 100 ): array|\WP_Error {
        $limit = self::bounded_limit( $limit );
        $scan  = $this->scan_folder( $mailbox_id, $folder_id );
        if ( is_wp_error( $scan ) ) {
            return $scan;
        }

        $generation = self::generation();
        $events     = self::initial_events( $scan['messages'] );
        $has_more   = count( $events ) > $limit;

        $state = array(
            'version'     => 1,
            'generation'  => $generation,
            'uidValidity' => $scan['uidValidity'],
            'messages'    => $scan['messages'],
            'pending'     => $has_more ? array(
                'mode'       => 'initial',
                'generation' => $generation,
                'offset'     => $limit,
                'events'     => $events,
            ) : null,
            'updatedAt'   => time(),
        );

        $saved = $this->state_store->save( $mailbox_id, $folder_id, $state );
        if ( is_wp_error( $saved ) ) {
            return $saved;
        }

        return self::batch_from_events(
            array_slice( $events, 0, $limit ),
            $mailbox_id,
            $folder_id,
            $this->cursor( $mailbox_id, $folder_id, $generation, $has_more ? 'p' : 's', $has_more ? $limit : 0 ),
            $has_more,
            false
        );
    }

    /** @return array<string,mixed>|\WP_Error */
    public function delta_sync( string $mailbox_id, string $folder_id, string $cursor, int $limit = 100 ): array|\WP_Error {
        $limit  = self::bounded_limit( $limit );
        $parsed = $this->parse_cursor( $mailbox_id, $folder_id, $cursor );
        if ( is_wp_error( $parsed ) ) {
            return self::reset_batch( $cursor );
        }

        $state = $this->state_store->load( $mailbox_id, $folder_id );
        if ( is_wp_error( $state ) ) {
            return $state;
        }
        if ( ! is_array( $state ) ) {
            return self::reset_batch( $cursor );
        }

        if ( 'p' === $parsed['mode'] ) {
            return $this->continue_pending( $mailbox_id, $folder_id, $parsed, $state, $limit, $cursor );
        }

        if ( 's' !== $parsed['mode'] || $parsed['generation'] !== (string) ( $state['generation'] ?? '' ) ) {
            return self::reset_batch( $cursor );
        }
        if ( null !== ( $state['pending'] ?? null ) ) {
            return self::reset_batch( $cursor );
        }

        $scan = $this->scan_folder( $mailbox_id, $folder_id );
        if ( is_wp_error( $scan ) ) {
            return $scan;
        }
        if ( (int) ( $state['uidValidity'] ?? 0 ) !== (int) $scan['uidValidity'] ) {
            return self::reset_batch( $cursor );
        }

        $events = self::diff_events( (array) ( $state['messages'] ?? array() ), $scan['messages'] );
        if ( array() === $events ) {
            $state['updatedAt'] = time();
            $saved = $this->state_store->save( $mailbox_id, $folder_id, $state );
            if ( is_wp_error( $saved ) ) {
                return $saved;
            }
            return self::batch_from_events( array(), $mailbox_id, $folder_id, $cursor, false, false );
        }

        $generation = self::generation();
        $has_more   = count( $events ) > $limit;
        $state = array(
            'version'     => 1,
            'generation'  => $generation,
            'uidValidity' => $scan['uidValidity'],
            'messages'    => $scan['messages'],
            'pending'     => $has_more ? array(
                'mode'       => 'diff',
                'generation' => $generation,
                'offset'     => $limit,
                'events'     => $events,
            ) : null,
            'updatedAt'   => time(),
        );
        $saved = $this->state_store->save( $mailbox_id, $folder_id, $state );
        if ( is_wp_error( $saved ) ) {
            return $saved;
        }

        return self::batch_from_events(
            array_slice( $events, 0, $limit ),
            $mailbox_id,
            $folder_id,
            $this->cursor( $mailbox_id, $folder_id, $generation, $has_more ? 'p' : 's', $has_more ? $limit : 0 ),
            $has_more,
            false
        );
    }

    /** @return array<string,bool|int|string> */
    public function sync_capabilities( string $mailbox_id ): array {
        $ready = $this->configured( $mailbox_id ) && function_exists( 'imap_open' );
        return array(
            'initial'           => $ready,
            'delta'             => $ready,
            'deletions'         => $ready,
            'cursorReset'       => $ready,
            'maxBatch'          => self::MAX_BATCH,
            'maxFolderMessages' => self::MAX_FOLDER_MESSAGES,
            'provider'          => 'imap-bounded-snapshot-v1',
        );
    }

    /** @param array<string,mixed> $parsed @param array<string,mixed> $state @return array<string,mixed>|\WP_Error */
    private function continue_pending( string $mailbox_id, string $folder_id, array $parsed, array $state, int $limit, string $original_cursor ): array|\WP_Error {
        $pending = is_array( $state['pending'] ?? null ) ? $state['pending'] : null;
        if (
            null === $pending
            || (string) ( $pending['generation'] ?? '' ) !== $parsed['generation']
            || (int) ( $pending['offset'] ?? -1 ) !== $parsed['offset']
        ) {
            return self::reset_batch( $original_cursor );
        }

        $events   = array_values( (array) ( $pending['events'] ?? array() ) );
        $offset   = $parsed['offset'];
        $slice    = array_slice( $events, $offset, $limit );
        $next     = $offset + count( $slice );
        $has_more = $next < count( $events );

        if ( $has_more ) {
            $state['pending']['offset'] = $next;
        } else {
            $state['pending'] = null;
        }
        $state['updatedAt'] = time();

        $saved = $this->state_store->save( $mailbox_id, $folder_id, $state );
        if ( is_wp_error( $saved ) ) {
            return $saved;
        }

        return self::batch_from_events(
            $slice,
            $mailbox_id,
            $folder_id,
            $this->cursor( $mailbox_id, $folder_id, $parsed['generation'], $has_more ? 'p' : 's', $has_more ? $next : 0 ),
            $has_more,
            false
        );
    }

    /** @return array{uidValidity:int,messages:array<string,array<string,mixed>>}|\WP_Error */
    private function scan_folder( string $mailbox_id, string $folder_id ): array|\WP_Error {
        $remote = self::folder_from_id( $folder_id );
        if ( is_wp_error( $remote ) ) {
            return $remote;
        }
        if ( ! $this->configured( $mailbox_id ) || ! function_exists( 'imap_open' ) ) {
            return new \WP_Error( 'mvm_mail_sync_unavailable', 'IMAP synchronisatie is niet beschikbaar.' );
        }

        $mailbox = $this->server_prefix() . $remote;
        $stream  = @imap_open( $mailbox, (string) MVM_HUB_IMAP_USERNAME, (string) MVM_HUB_IMAP_PASSWORD, 0, 1 );
        if ( false === $stream ) {
            return new \WP_Error( 'mvm_mail_sync_connect', 'De mailboxmap kon niet veilig worden geopend.' );
        }

        try {
            $count = imap_num_msg( $stream );
            if ( $count > self::MAX_FOLDER_MESSAGES ) {
                return new \WP_Error(
                    'mvm_mail_sync_folder_too_large',
                    'Deze mailboxmap overschrijdt de veilige IMAP-synchronisatiegrens.'
                );
            }

            $status = @imap_status( $stream, $mailbox, defined( 'SA_UIDVALIDITY' ) ? SA_UIDVALIDITY : 0 );
            $uid_validity = is_object( $status ) ? (int) ( $status->uidvalidity ?? 0 ) : 0;
            if ( $uid_validity < 1 ) {
                return new \WP_Error( 'mvm_mail_sync_uidvalidity', 'De provider leverde geen geldige UIDVALIDITY.' );
            }

            if ( 0 === $count ) {
                return array( 'uidValidity' => $uid_validity, 'messages' => array() );
            }

            $overview = imap_fetch_overview( $stream, '1:*', 0 );
            if ( ! is_array( $overview ) ) {
                return new \WP_Error( 'mvm_mail_sync_overview', 'De mailboxstatus kon niet worden gelezen.' );
            }

            $messages = array();
            foreach ( array_slice( $overview, 0, self::MAX_FOLDER_MESSAGES ) as $row ) {
                if ( ! is_object( $row ) ) {
                    continue;
                }
                $uid = (int) ( $row->uid ?? 0 );
                if ( $uid < 1 ) {
                    continue;
                }
                $seen    = ! empty( $row->seen );
                $flagged = ! empty( $row->flagged );
                $size    = max( 0, min( 100000000, (int) ( $row->size ?? 0 ) ) );
                $messages[ (string) $uid ] = array(
                    'revision' => self::revision( $uid_validity, $uid, $seen, $flagged, $size ),
                    'seen'     => $seen,
                    'flagged'  => $flagged,
                    'size'     => $size,
                );
            }
            ksort( $messages, SORT_NUMERIC );

            return array( 'uidValidity' => $uid_validity, 'messages' => $messages );
        } finally {
            imap_close( $stream );
        }
    }

    /** @param array<string,array<string,mixed>> $messages @return array<int,array<string,mixed>> */
    private static function initial_events( array $messages ): array {
        $events = array();
        foreach ( $messages as $uid => $message ) {
            $events[] = self::upsert_event( (string) $uid, $message );
        }
        return $events;
    }

    /** @param array<string,array<string,mixed>> $previous @param array<string,array<string,mixed>> $current @return array<int,array<string,mixed>> */
    private static function diff_events( array $previous, array $current ): array {
        $events = array();
        foreach ( $current as $uid => $message ) {
            $before = $previous[ $uid ] ?? null;
            if ( ! is_array( $before ) || $before !== $message ) {
                $events[] = self::upsert_event( (string) $uid, $message );
            }
        }
        foreach ( $previous as $uid => $_message ) {
            if ( ! array_key_exists( $uid, $current ) ) {
                $events[] = array( 'type' => 'deleted', 'uid' => (string) $uid );
            }
        }
        return $events;
    }

    /** @param array<string,mixed> $message @return array<string,mixed> */
    private static function upsert_event( string $uid, array $message ): array {
        return array(
            'type'     => 'upsert',
            'uid'      => (string) (int) $uid,
            'revision' => (string) ( $message['revision'] ?? '' ),
            'seen'     => true === ( $message['seen'] ?? false ),
            'flagged'  => true === ( $message['flagged'] ?? false ),
            'size'     => max( 0, min( 100000000, (int) ( $message['size'] ?? 0 ) ) ),
        );
    }

    /** @param array<int,array<string,mixed>> $events @return array<string,mixed> */
    private static function batch_from_events( array $events, string $mailbox_id, string $folder_id, string $cursor, bool $has_more, bool $reset_required ): array {
        $upserts = array();
        $deleted = array();
        foreach ( $events as $event ) {
            $uid = (string) (int) ( $event['uid'] ?? 0 );
            if ( '0' === $uid ) {
                continue;
            }
            $reference = array(
                'mailboxId' => $mailbox_id,
                'folderId'  => $folder_id,
                'messageId' => $folder_id . '.' . $uid,
            );
            if ( 'deleted' === ( $event['type'] ?? '' ) ) {
                $deleted[] = $reference;
                continue;
            }
            $upserts[] = array_merge(
                $reference,
                array(
                    'revision' => (string) ( $event['revision'] ?? '' ),
                    'seen'     => true === ( $event['seen'] ?? false ),
                    'flagged'  => true === ( $event['flagged'] ?? false ),
                    'size'     => max( 0, min( 100000000, (int) ( $event['size'] ?? 0 ) ) ),
                )
            );
        }

        return array(
            'cursor'        => $cursor,
            'upserts'       => $upserts,
            'deletedIds'    => $deleted,
            'hasMore'       => $has_more,
            'resetRequired' => $reset_required,
        );
    }

    /** @return array<string,mixed> */
    private static function reset_batch( string $cursor ): array {
        return array(
            'cursor'        => '' !== trim( $cursor ) ? trim( $cursor ) : 'reset-required',
            'upserts'       => array(),
            'deletedIds'    => array(),
            'hasMore'       => false,
            'resetRequired' => true,
        );
    }

    private function cursor( string $mailbox_id, string $folder_id, string $generation, string $mode, int $offset ): string {
        $payload = array(
            'v' => self::CURSOR_VERSION,
            'k' => self::context_key( $mailbox_id, $folder_id ),
            'g' => $generation,
            'm' => $mode,
            'o' => max( 0, $offset ),
        );
        $json = wp_json_encode( $payload );
        $encoded = self::base64url_encode( is_string( $json ) ? $json : '{}' );
        $mac = self::base64url_encode( hash_hmac( 'sha256', $encoded, wp_salt( 'auth' ), true ) );
        return 'v1.' . $encoded . '.' . $mac;
    }

    /** @return array{generation:string,mode:string,offset:int}|\WP_Error */
    private function parse_cursor( string $mailbox_id, string $folder_id, string $cursor ): array|\WP_Error {
        $cursor = trim( $cursor );
        if ( strlen( $cursor ) > 512 || 1 !== preg_match( '/^v1\.([A-Za-z0-9_-]+)\.([A-Za-z0-9_-]+)$/', $cursor, $matches ) ) {
            return new \WP_Error( 'mvm_mail_sync_cursor', 'Ongeldige synchronisatiecursor.' );
        }
        $expected = self::base64url_encode( hash_hmac( 'sha256', $matches[1], wp_salt( 'auth' ), true ) );
        if ( ! hash_equals( $expected, $matches[2] ) ) {
            return new \WP_Error( 'mvm_mail_sync_cursor_mac', 'Synchronisatiecursor is niet vertrouwd.' );
        }
        $decoded = self::base64url_decode( $matches[1] );
        if ( is_wp_error( $decoded ) ) {
            return $decoded;
        }
        $payload = json_decode( $decoded, true );
        if ( ! is_array( $payload ) || self::CURSOR_VERSION !== (int) ( $payload['v'] ?? 0 ) ) {
            return new \WP_Error( 'mvm_mail_sync_cursor_payload', 'Synchronisatiecursor heeft een onbekende versie.' );
        }
        if ( ! hash_equals( self::context_key( $mailbox_id, $folder_id ), (string) ( $payload['k'] ?? '' ) ) ) {
            return new \WP_Error( 'mvm_mail_sync_cursor_scope', 'Synchronisatiecursor hoort bij een andere mailboxmap.' );
        }
        $generation = self::opaque_id( (string) ( $payload['g'] ?? '' ), 96 );
        $mode = sanitize_key( (string) ( $payload['m'] ?? '' ) );
        $offset = max( 0, min( self::MAX_FOLDER_MESSAGES, (int) ( $payload['o'] ?? 0 ) ) );
        if ( '' === $generation || ! in_array( $mode, array( 'p', 's' ), true ) ) {
            return new \WP_Error( 'mvm_mail_sync_cursor_fields', 'Synchronisatiecursor mist geldige velden.' );
        }
        return array( 'generation' => $generation, 'mode' => $mode, 'offset' => $offset );
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

    private static function revision( int $uid_validity, int $uid, bool $seen, bool $flagged, int $size ): string {
        return substr( hash( 'sha256', implode( ':', array( $uid_validity, $uid, $seen ? 1 : 0, $flagged ? 1 : 0, $size ) ) ), 0, 32 );
    }

    private static function generation(): string {
        return str_replace( '-', '', wp_generate_uuid4() );
    }

    private static function context_key( string $mailbox_id, string $folder_id ): string {
        return substr( hash_hmac( 'sha256', $mailbox_id . "\n" . $folder_id, wp_salt( 'auth' ) ), 0, 32 );
    }

    private static function bounded_limit( int $limit ): int {
        return max( 1, min( self::MAX_BATCH, $limit ) );
    }

    private static function opaque_id( string $value, int $max_length ): string {
        $value = trim( $value );
        if ( '' === $value || strlen( $value ) > $max_length ) {
            return '';
        }
        return 1 === preg_match( '/^[A-Za-z0-9._:-]+$/', $value ) ? $value : '';
    }

    private static function base64url_encode( string $value ): string {
        return rtrim( strtr( base64_encode( $value ), '+/', '-_' ), '=' );
    }

    /** @return string|\WP_Error */
    private static function base64url_decode( string $value ): string|\WP_Error {
        $pad = strlen( $value ) % 4;
        $encoded = strtr( $value, '-_', '+/' ) . ( 0 === $pad ? '' : str_repeat( '=', 4 - $pad ) );
        $decoded = base64_decode( $encoded, true );
        return false === $decoded
            ? new \WP_Error( 'mvm_mail_sync_cursor_decode', 'Synchronisatiecursor kan niet worden gelezen.' )
            : $decoded;
    }
}
