<?php

namespace MVM\Hub\Integrations\Mail;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Encrypted, owner-scoped mail draft store outside the webroot.
 *
 * Draft payloads contain recipients and bodies, so plaintext persistence is not
 * accepted. Libsodium secretbox is required and writes are atomic/versioned.
 */
final class Encrypted_Filesystem_Draft_Store implements Draft_Store {
    /** @return array<string,mixed>|\WP_Error */
    public function create( int $owner_user_id, array $draft ): array|\WP_Error {
        $dir = $this->owner_dir( $owner_user_id );
        if ( is_wp_error( $dir ) ) {
            return $dir;
        }
        $id = wp_generate_uuid4();
        $record = array_merge(
            $draft,
            array(
                'id'           => $id,
                'ownerUserId'  => $owner_user_id,
                'version'      => 1,
                'createdAtUtc' => gmdate( 'c' ),
                'updatedAtUtc' => gmdate( 'c' ),
            )
        );
        $written = $this->write_record( $dir . DIRECTORY_SEPARATOR . $id . '.bin', $record );
        return is_wp_error( $written ) ? $written : $record;
    }

    /** @return array<string,mixed>|\WP_Error */
    public function update_own( int $owner_user_id, string $draft_id, array $draft, int $expected_version ): array|\WP_Error {
        $path = $this->draft_path( $owner_user_id, $draft_id );
        if ( is_wp_error( $path ) ) {
            return $path;
        }
        $lock = $this->lock( $path . '.lock' );
        if ( is_wp_error( $lock ) ) {
            return $lock;
        }
        try {
            $current = $this->read_record( $path );
            if ( is_wp_error( $current ) ) {
                return $current;
            }
            if ( (int) ( $current['ownerUserId'] ?? 0 ) !== $owner_user_id ) {
                return new \WP_Error( 'mvm_mail_draft_forbidden', 'Dit concept is niet beschikbaar.' );
            }
            if ( (int) ( $current['version'] ?? 0 ) !== $expected_version ) {
                return new \WP_Error( 'mvm_mail_draft_conflict', 'Dit concept is inmiddels gewijzigd. Vernieuw voordat je opnieuw opslaat.' );
            }
            $record = array_merge(
                $draft,
                array(
                    'id'           => $draft_id,
                    'ownerUserId'  => $owner_user_id,
                    'version'      => $expected_version + 1,
                    'createdAtUtc' => (string) ( $current['createdAtUtc'] ?? gmdate( 'c' ) ),
                    'updatedAtUtc' => gmdate( 'c' ),
                )
            );
            $written = $this->write_record( $path, $record );
            return is_wp_error( $written ) ? $written : $record;
        } finally {
            $this->unlock( $lock );
        }
    }

    /** @return array<string,mixed>|\WP_Error */
    public function get_own( int $owner_user_id, string $draft_id ): array|\WP_Error {
        $path = $this->draft_path( $owner_user_id, $draft_id );
        if ( is_wp_error( $path ) ) {
            return $path;
        }
        $record = $this->read_record( $path );
        if ( is_wp_error( $record ) || (int) ( $record['ownerUserId'] ?? 0 ) !== $owner_user_id ) {
            return new \WP_Error( 'mvm_mail_draft_not_found', 'Dit concept is niet beschikbaar.' );
        }
        return $record;
    }

    /** @return array<string,mixed> */
    public function list_own( int $owner_user_id, int $page = 1, int $per_page = 20 ): array {
        $dir = $this->owner_dir( $owner_user_id, false );
        if ( is_wp_error( $dir ) || ! is_dir( $dir ) ) {
            return array( 'items' => array(), 'hasMore' => false );
        }
        $page = max( 1, min( 100, $page ) );
        $per_page = max( 1, min( 50, $per_page ) );
        $paths = glob( $dir . DIRECTORY_SEPARATOR . '*.bin' );
        $paths = is_array( $paths ) ? array_slice( $paths, 0, 5000 ) : array();
        $items = array();
        foreach ( $paths as $path ) {
            $record = $this->read_record( $path );
            if ( is_wp_error( $record ) || (int) ( $record['ownerUserId'] ?? 0 ) !== $owner_user_id ) {
                continue;
            }
            $items[] = array(
                'id'           => (string) ( $record['id'] ?? '' ),
                'version'      => max( 1, (int) ( $record['version'] ?? 1 ) ),
                'mode'         => sanitize_key( (string) ( $record['mode'] ?? 'compose' ) ),
                'subject'      => sanitize_text_field( (string) ( $record['subject'] ?? '' ) ),
                'updatedAtUtc' => sanitize_text_field( (string) ( $record['updatedAtUtc'] ?? '' ) ),
            );
        }
        usort( $items, static fn( array $a, array $b ): int => strcmp( (string) $b['updatedAtUtc'], (string) $a['updatedAtUtc'] ) );
        $offset = ( $page - 1 ) * $per_page;
        return array(
            'items'   => array_slice( $items, $offset, $per_page ),
            'hasMore' => count( $items ) > $offset + $per_page,
        );
    }

    public function delete_own( int $owner_user_id, string $draft_id ): bool|\WP_Error {
        $path = $this->draft_path( $owner_user_id, $draft_id );
        if ( is_wp_error( $path ) ) {
            return $path;
        }
        $record = $this->read_record( $path );
        if ( is_wp_error( $record ) || (int) ( $record['ownerUserId'] ?? 0 ) !== $owner_user_id ) {
            return new \WP_Error( 'mvm_mail_draft_not_found', 'Dit concept is niet beschikbaar.' );
        }
        return ! file_exists( $path ) || @unlink( $path )
            ? true
            : new \WP_Error( 'mvm_mail_draft_delete', 'Concept kon niet uit private opslag worden verwijderd.' );
    }

    /** @return string|\WP_Error */
    private function owner_dir( int $owner_user_id, bool $create = true ): string|\WP_Error {
        if ( $owner_user_id <= 0 || ! function_exists( 'sodium_crypto_secretbox' ) ) {
            return new \WP_Error( 'mvm_mail_draft_crypto', 'Versleutelde conceptopslag is niet beschikbaar.' );
        }
        $base = Private_Storage_Root::directory( 'drafts' );
        if ( is_wp_error( $base ) ) {
            return $base;
        }
        $owner = substr( hash_hmac( 'sha256', (string) $owner_user_id, $this->key() ), 0, 32 );
        $dir = $base . DIRECTORY_SEPARATOR . $owner;
        if ( $create && ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
            return new \WP_Error( 'mvm_mail_draft_storage', 'Private conceptmap kon niet worden aangemaakt.' );
        }
        if ( is_dir( $dir ) ) {
            @chmod( $dir, 0700 );
        }
        return $dir;
    }

    /** @return string|\WP_Error */
    private function draft_path( int $owner_user_id, string $draft_id ): string|\WP_Error {
        if ( ! $this->valid_id( $draft_id ) ) {
            return new \WP_Error( 'mvm_mail_draft_id', 'Ongeldig concept-ID.' );
        }
        $dir = $this->owner_dir( $owner_user_id, false );
        if ( is_wp_error( $dir ) ) {
            return $dir;
        }
        $path = $dir . DIRECTORY_SEPARATOR . $draft_id . '.bin';
        return is_file( $path ) ? $path : new \WP_Error( 'mvm_mail_draft_not_found', 'Dit concept is niet beschikbaar.' );
    }

    /** @param array<string,mixed> $record @return true|\WP_Error */
    private function write_record( string $path, array $record ): true|\WP_Error {
        $json = wp_json_encode( $record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
        if ( false === $json ) {
            return new \WP_Error( 'mvm_mail_draft_encode', 'Concept kon niet veilig worden opgeslagen.' );
        }
        $nonce = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
        $cipher = sodium_crypto_secretbox( $json, $nonce, $this->key() );
        $payload = base64_encode( $nonce . $cipher );
        $tmp = $path . '.tmp-' . bin2hex( random_bytes( 6 ) );
        if ( false === @file_put_contents( $tmp, $payload, LOCK_EX ) ) {
            return new \WP_Error( 'mvm_mail_draft_write', 'Concept kon niet naar private opslag worden geschreven.' );
        }
        @chmod( $tmp, 0600 );
        if ( ! @rename( $tmp, $path ) ) {
            @unlink( $tmp );
            return new \WP_Error( 'mvm_mail_draft_write', 'Concept kon niet atomisch worden opgeslagen.' );
        }
        @chmod( $path, 0600 );
        return true;
    }

    /** @return array<string,mixed>|\WP_Error */
    private function read_record( string $path ): array|\WP_Error {
        if ( ! is_readable( $path ) || ! function_exists( 'sodium_crypto_secretbox_open' ) ) {
            return new \WP_Error( 'mvm_mail_draft_not_found', 'Dit concept is niet beschikbaar.' );
        }
        $raw = file_get_contents( $path );
        $decoded = is_string( $raw ) ? base64_decode( $raw, true ) : false;
        if ( false === $decoded || strlen( $decoded ) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ) {
            return new \WP_Error( 'mvm_mail_draft_corrupt', 'Conceptopslag is ongeldig.' );
        }
        $nonce = substr( $decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
        $cipher = substr( $decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
        $plain = sodium_crypto_secretbox_open( $cipher, $nonce, $this->key() );
        if ( false === $plain ) {
            return new \WP_Error( 'mvm_mail_draft_corrupt', 'Concept kon niet worden ontsleuteld.' );
        }
        $record = json_decode( $plain, true );
        return is_array( $record ) ? $record : new \WP_Error( 'mvm_mail_draft_corrupt', 'Conceptopslag is ongeldig.' );
    }

    private function key(): string {
        return hash_hmac( 'sha256', 'mvm-hub-mail-drafts-v1', wp_salt( 'auth' ), true );
    }

    /** @return resource|\WP_Error */
    private function lock( string $path ): mixed {
        $handle = @fopen( $path, 'c+' );
        if ( false === $handle || ! flock( $handle, LOCK_EX ) ) {
            if ( is_resource( $handle ) ) {
                fclose( $handle );
            }
            return new \WP_Error( 'mvm_mail_draft_lock', 'Concept kon niet veilig worden vergrendeld.' );
        }
        return $handle;
    }

    private function unlock( mixed $handle ): void {
        if ( is_resource( $handle ) ) {
            flock( $handle, LOCK_UN );
            fclose( $handle );
        }
    }

    private function valid_id( string $id ): bool {
        return 1 === preg_match( '/^[a-f0-9-]{36}$/i', $id );
    }
}
