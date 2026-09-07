<?php

namespace MVM\Hub\Integrations\Mail;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Non-autoloaded WordPress option store for bounded Mail V2 sync metadata.
 *
 * Only opaque identifiers and message state are persisted. No subjects, bodies,
 * addresses, filenames, credentials or tokens are accepted into this store.
 */
final class Option_Mail_Sync_State_Store implements Mail_Sync_State_Store {
    private const VERSION      = 1;
    private const MAX_MESSAGES = 5000;
    private const MAX_EVENTS   = 5000;

    /** @return array<string,mixed>|null|\WP_Error */
    public function load( string $mailbox_id, string $folder_id ): array|null|\WP_Error {
        $key = $this->option_key( $mailbox_id, $folder_id );
        if ( is_wp_error( $key ) ) {
            return $key;
        }

        $raw = get_option( $key, null );
        if ( null === $raw || false === $raw ) {
            return null;
        }
        if ( ! is_array( $raw ) ) {
            return new \WP_Error( 'mvm_mail_sync_state_invalid', 'De synchronisatiestatus is ongeldig.' );
        }

        return $this->normalize_state( $raw );
    }

    /** @param array<string,mixed> $state */
    public function save( string $mailbox_id, string $folder_id, array $state ): true|\WP_Error {
        $key = $this->option_key( $mailbox_id, $folder_id );
        if ( is_wp_error( $key ) ) {
            return $key;
        }

        $normalized = $this->normalize_state( $state );
        if ( is_wp_error( $normalized ) ) {
            return $normalized;
        }

        if ( false === get_option( $key, false ) ) {
            $ok = add_option( $key, $normalized, '', false );
        } else {
            $ok = update_option( $key, $normalized, false );
        }

        return $ok || $normalized === get_option( $key, null )
            ? true
            : new \WP_Error( 'mvm_mail_sync_state_write', 'De synchronisatiestatus kon niet worden opgeslagen.' );
    }

    public function clear( string $mailbox_id, string $folder_id ): true|\WP_Error {
        $key = $this->option_key( $mailbox_id, $folder_id );
        if ( is_wp_error( $key ) ) {
            return $key;
        }

        if ( false === get_option( $key, false ) ) {
            return true;
        }

        return delete_option( $key )
            ? true
            : new \WP_Error( 'mvm_mail_sync_state_delete', 'De synchronisatiestatus kon niet worden verwijderd.' );
    }

    /** @return string|\WP_Error */
    private function option_key( string $mailbox_id, string $folder_id ): string|\WP_Error {
        $mailbox_id = self::opaque_id( $mailbox_id, 128 );
        $folder_id  = self::opaque_id( $folder_id, 128 );
        if ( '' === $mailbox_id || '' === $folder_id ) {
            return new \WP_Error( 'mvm_mail_sync_scope', 'Ongeldige mailbox- of mapreferentie.' );
        }

        $digest = hash_hmac( 'sha256', $mailbox_id . "\n" . $folder_id, wp_salt( 'auth' ) );
        return 'mvm_hub_mail_sync_v2_' . substr( $digest, 0, 40 );
    }

    /** @param array<string,mixed> $state @return array<string,mixed>|\WP_Error */
    private function normalize_state( array $state ): array|\WP_Error {
        $version = (int) ( $state['version'] ?? self::VERSION );
        if ( self::VERSION !== $version ) {
            return new \WP_Error( 'mvm_mail_sync_state_version', 'Onbekende synchronisatiestatusversie.' );
        }

        $generation = self::opaque_id( (string) ( $state['generation'] ?? '' ), 96 );
        $uid_validity = max( 0, (int) ( $state['uidValidity'] ?? 0 ) );
        if ( '' === $generation || $uid_validity < 1 ) {
            return new \WP_Error( 'mvm_mail_sync_state_identity', 'Synchronisatiestatus mist geldige provideridentiteit.' );
        }

        $messages = array();
        foreach ( array_slice( (array) ( $state['messages'] ?? array() ), 0, self::MAX_MESSAGES, true ) as $uid => $candidate ) {
            if ( ! ctype_digit( (string) $uid ) || (int) $uid < 1 || ! is_array( $candidate ) ) {
                continue;
            }
            $revision = self::opaque_id( (string) ( $candidate['revision'] ?? '' ), 128 );
            if ( '' === $revision ) {
                continue;
            }
            $messages[ (string) (int) $uid ] = array(
                'revision' => $revision,
                'seen'     => true === ( $candidate['seen'] ?? false ),
                'flagged'  => true === ( $candidate['flagged'] ?? false ),
                'size'     => max( 0, min( 100000000, (int) ( $candidate['size'] ?? 0 ) ) ),
            );
        }

        $pending = null;
        if ( is_array( $state['pending'] ?? null ) ) {
            $mode = sanitize_key( (string) ( $state['pending']['mode'] ?? '' ) );
            $pending_generation = self::opaque_id( (string) ( $state['pending']['generation'] ?? '' ), 96 );
            $offset = max( 0, min( self::MAX_EVENTS, (int) ( $state['pending']['offset'] ?? 0 ) ) );
            if ( in_array( $mode, array( 'initial', 'diff' ), true ) && '' !== $pending_generation ) {
                $events = array();
                foreach ( array_slice( (array) ( $state['pending']['events'] ?? array() ), 0, self::MAX_EVENTS ) as $event ) {
                    $normalized_event = self::normalize_event( $event );
                    if ( null !== $normalized_event ) {
                        $events[] = $normalized_event;
                    }
                }
                $pending = array(
                    'mode'       => $mode,
                    'generation' => $pending_generation,
                    'offset'     => min( $offset, count( $events ) ),
                    'events'     => $events,
                );
            }
        }

        return array(
            'version'     => self::VERSION,
            'generation'  => $generation,
            'uidValidity' => $uid_validity,
            'messages'    => $messages,
            'pending'     => $pending,
            'updatedAt'   => max( 0, (int) ( $state['updatedAt'] ?? time() ) ),
        );
    }

    /** @return array<string,mixed>|null */
    private static function normalize_event( mixed $event ): ?array {
        if ( ! is_array( $event ) ) {
            return null;
        }
        $type = sanitize_key( (string) ( $event['type'] ?? '' ) );
        $uid  = (string) (int) ( $event['uid'] ?? 0 );
        if ( ! in_array( $type, array( 'upsert', 'deleted' ), true ) || '0' === $uid ) {
            return null;
        }

        if ( 'deleted' === $type ) {
            return array( 'type' => 'deleted', 'uid' => $uid );
        }

        $revision = self::opaque_id( (string) ( $event['revision'] ?? '' ), 128 );
        if ( '' === $revision ) {
            return null;
        }

        return array(
            'type'     => 'upsert',
            'uid'      => $uid,
            'revision' => $revision,
            'seen'     => true === ( $event['seen'] ?? false ),
            'flagged'  => true === ( $event['flagged'] ?? false ),
            'size'     => max( 0, min( 100000000, (int) ( $event['size'] ?? 0 ) ) ),
        );
    }

    private static function opaque_id( string $value, int $max_length ): string {
        $value = trim( $value );
        if ( '' === $value || strlen( $value ) > $max_length ) {
            return '';
        }
        return 1 === preg_match( '/^[A-Za-z0-9._:-]+$/', $value ) ? $value : '';
    }
}
