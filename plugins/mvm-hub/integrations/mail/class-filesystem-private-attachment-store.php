<?php

namespace MVM\Hub\Integrations\Mail;

use MVM\Hub\Core\Capabilities;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Private filesystem attachment store with encrypted metadata outside webroot.
 */
final class Filesystem_Private_Attachment_Store implements Private_Attachment_Store {
    /** @return array<string,mixed>|\WP_Error */
    public function store_for_draft( int $owner_user_id, string $draft_id, array $upload ): array|\WP_Error {
        if ( $owner_user_id <= 0 || ! $this->valid_id( $draft_id ) || ! function_exists( 'sodium_crypto_secretbox' ) ) {
            return new \WP_Error( 'mvm_mail_attachment_storage', 'Private bijlageopslag is niet beschikbaar.' );
        }

        $source = (string) ( $upload['privateHandle'] ?? '' );
        if ( '' === $source || ! is_file( $source ) || ! is_readable( $source ) ) {
            return new \WP_Error( 'mvm_mail_attachment_source', 'De tijdelijke bijlage is niet beschikbaar.' );
        }

        $data_dir = Private_Storage_Root::directory( 'attachment-data' );
        $meta_dir = Private_Storage_Root::directory( 'attachment-meta' );
        if ( is_wp_error( $data_dir ) || is_wp_error( $meta_dir ) ) {
            return is_wp_error( $data_dir ) ? $data_dir : $meta_dir;
        }

        $id = wp_generate_uuid4();
        $data_path = $data_dir . DIRECTORY_SEPARATOR . $id . '.blob';
        if ( ! @copy( $source, $data_path ) ) {
            return new \WP_Error( 'mvm_mail_attachment_store', 'De bijlage kon niet naar private opslag worden gekopieerd.' );
        }
        @chmod( $data_path, 0600 );

        $meta = array(
            'id'          => $id,
            'ownerUserId' => $owner_user_id,
            'draftId'     => $draft_id,
            'name'        => sanitize_file_name( (string) ( $upload['name'] ?? '' ) ),
            'mimeType'    => sanitize_mime_type( (string) ( $upload['mimeType'] ?? '' ) ),
            'sizeBytes'   => max( 0, (int) ( $upload['sizeBytes'] ?? filesize( $data_path ) ) ),
            'scanStatus'  => sanitize_key( (string) ( $upload['scanStatus'] ?? '' ) ),
            'contentValidationStatus' => sanitize_key( (string) ( $upload['contentValidationStatus'] ?? '' ) ),
            'createdAtUtc'=> gmdate( 'c' ),
        );

        $written = $this->write_meta( $meta_dir . DIRECTORY_SEPARATOR . $id . '.bin', $meta );
        if ( is_wp_error( $written ) ) {
            @unlink( $data_path );
            return $written;
        }

        @unlink( $source );
        return self::project( $meta );
    }

    /** @return array<int,array<string,mixed>> */
    public function list_for_draft( int $owner_user_id, string $draft_id ): array {
        if ( $owner_user_id <= 0 || ! $this->valid_id( $draft_id ) ) {
            return array();
        }
        $meta_dir = Private_Storage_Root::directory( 'attachment-meta' );
        if ( is_wp_error( $meta_dir ) ) {
            return array();
        }
        $paths = glob( $meta_dir . DIRECTORY_SEPARATOR . '*.bin' );
        $paths = is_array( $paths ) ? array_slice( $paths, 0, 5000 ) : array();
        $items = array();
        foreach ( $paths as $path ) {
            $meta = $this->read_meta( $path );
            if ( is_wp_error( $meta ) ) {
                continue;
            }
            if ( (int) ( $meta['ownerUserId'] ?? 0 ) === $owner_user_id && (string) ( $meta['draftId'] ?? '' ) === $draft_id ) {
                $items[] = self::project( $meta );
            }
        }
        usort( $items, static fn( array $a, array $b ): int => strcmp( (string) ( $a['createdAtUtc'] ?? '' ), (string) ( $b['createdAtUtc'] ?? '' ) ) );
        return $items;
    }

    public function delete_own( int $owner_user_id, string $draft_id, string $attachment_id ): bool|\WP_Error {
        $meta = $this->owned_meta( $owner_user_id, $draft_id, $attachment_id );
        if ( is_wp_error( $meta ) ) {
            return $meta;
        }
        $paths = $this->paths( $attachment_id );
        if ( is_wp_error( $paths ) ) {
            return $paths;
        }
        $ok_data = ! file_exists( $paths['data'] ) || @unlink( $paths['data'] );
        $ok_meta = ! file_exists( $paths['meta'] ) || @unlink( $paths['meta'] );
        return $ok_data && $ok_meta ? true : new \WP_Error( 'mvm_mail_attachment_delete', 'Bijlage kon niet volledig worden verwijderd.' );
    }

    /** @return array<string,mixed>|\WP_Error */
    public function authorize_download( int $viewer_user_id, string $attachment_id ): array|\WP_Error {
        $meta = $this->meta_by_id( $attachment_id );
        if ( is_wp_error( $meta ) ) {
            return $meta;
        }
        $owner = (int) ( $meta['ownerUserId'] ?? 0 );
        $allowed = $viewer_user_id > 0 && $viewer_user_id === get_current_user_id() && (
            $viewer_user_id === $owner
            || current_user_can( 'manage_options' )
            || current_user_can( Capabilities::COMMUNICATIONS_ADMIN )
        );
        if (
            ! $allowed
            || 'clean' !== sanitize_key( (string) ( $meta['scanStatus'] ?? '' ) )
            || 'verified' !== sanitize_key( (string) ( $meta['contentValidationStatus'] ?? '' ) )
        ) {
            return new \WP_Error( 'mvm_mail_attachment_forbidden', 'Deze bijlage is niet beschikbaar.' );
        }
        $paths = $this->paths( $attachment_id );
        if ( is_wp_error( $paths ) || ! is_readable( $paths['data'] ) ) {
            return new \WP_Error( 'mvm_mail_attachment_missing', 'Deze bijlage is niet beschikbaar.' );
        }
        return array_merge( self::project( $meta ), array( 'path' => $paths['data'] ) );
    }

    /** @param array<int,string> $attachment_ids @return array<int,array<string,mixed>>|\WP_Error */
    public function authorize_for_send( int $owner_user_id, string $draft_id, array $attachment_ids ): array|\WP_Error {
        $resolved = array();
        foreach ( array_values( array_unique( $attachment_ids ) ) as $attachment_id ) {
            $meta = $this->owned_meta( $owner_user_id, $draft_id, $attachment_id );
            if (
                is_wp_error( $meta )
                || 'clean' !== sanitize_key( (string) ( is_array( $meta ) ? ( $meta['scanStatus'] ?? '' ) : '' ) )
                || 'verified' !== sanitize_key( (string) ( is_array( $meta ) ? ( $meta['contentValidationStatus'] ?? '' ) : '' ) )
            ) {
                return new \WP_Error( 'mvm_mail_attachment_send_denied', 'Een bijlage is niet vrijgegeven voor verzending.' );
            }
            $paths = $this->paths( $attachment_id );
            if ( is_wp_error( $paths ) || ! is_readable( $paths['data'] ) ) {
                return new \WP_Error( 'mvm_mail_attachment_send_denied', 'Een bijlage is niet beschikbaar voor verzending.' );
            }
            $resolved[] = array_merge( self::project( $meta ), array( 'path' => $paths['data'] ) );
        }
        return $resolved;
    }

    /** @return array<string,mixed>|\WP_Error */
    private function owned_meta( int $owner_user_id, string $draft_id, string $attachment_id ): array|\WP_Error {
        if ( $owner_user_id <= 0 || ! $this->valid_id( $draft_id ) ) {
            return new \WP_Error( 'mvm_mail_attachment_forbidden', 'Deze bijlage is niet beschikbaar.' );
        }
        $meta = $this->meta_by_id( $attachment_id );
        if ( is_wp_error( $meta ) || (int) ( is_array( $meta ) ? ( $meta['ownerUserId'] ?? 0 ) : 0 ) !== $owner_user_id || (string) ( is_array( $meta ) ? ( $meta['draftId'] ?? '' ) : '' ) !== $draft_id ) {
            return new \WP_Error( 'mvm_mail_attachment_forbidden', 'Deze bijlage is niet beschikbaar.' );
        }
        return $meta;
    }

    /** @return array<string,mixed>|\WP_Error */
    private function meta_by_id( string $attachment_id ): array|\WP_Error {
        if ( ! $this->valid_id( $attachment_id ) ) {
            return new \WP_Error( 'mvm_mail_attachment_id', 'Ongeldige bijlageverwijzing.' );
        }
        $paths = $this->paths( $attachment_id );
        return is_wp_error( $paths ) ? $paths : $this->read_meta( $paths['meta'] );
    }

    /** @return array{data:string,meta:string}|\WP_Error */
    private function paths( string $attachment_id ): array|\WP_Error {
        if ( ! $this->valid_id( $attachment_id ) ) {
            return new \WP_Error( 'mvm_mail_attachment_id', 'Ongeldige bijlageverwijzing.' );
        }
        $data_dir = Private_Storage_Root::directory( 'attachment-data' );
        $meta_dir = Private_Storage_Root::directory( 'attachment-meta' );
        if ( is_wp_error( $data_dir ) || is_wp_error( $meta_dir ) ) {
            return new \WP_Error( 'mvm_mail_attachment_storage', 'Private bijlageopslag is niet beschikbaar.' );
        }
        return array(
            'data' => $data_dir . DIRECTORY_SEPARATOR . $attachment_id . '.blob',
            'meta' => $meta_dir . DIRECTORY_SEPARATOR . $attachment_id . '.bin',
        );
    }

    /** @param array<string,mixed> $meta @return true|\WP_Error */
    private function write_meta( string $path, array $meta ): true|\WP_Error {
        $json = wp_json_encode( $meta, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
        if ( false === $json ) {
            return new \WP_Error( 'mvm_mail_attachment_meta', 'Bijlagemetadata kon niet veilig worden opgeslagen.' );
        }
        $nonce = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
        $payload = base64_encode( $nonce . sodium_crypto_secretbox( $json, $nonce, $this->key() ) );
        $tmp = $path . '.tmp-' . bin2hex( random_bytes( 6 ) );
        if ( false === @file_put_contents( $tmp, $payload, LOCK_EX ) || ! @rename( $tmp, $path ) ) {
            @unlink( $tmp );
            return new \WP_Error( 'mvm_mail_attachment_meta', 'Bijlagemetadata kon niet atomisch worden opgeslagen.' );
        }
        @chmod( $path, 0600 );
        return true;
    }

    /** @return array<string,mixed>|\WP_Error */
    private function read_meta( string $path ): array|\WP_Error {
        if ( ! is_readable( $path ) || ! function_exists( 'sodium_crypto_secretbox_open' ) ) {
            return new \WP_Error( 'mvm_mail_attachment_missing', 'Deze bijlage is niet beschikbaar.' );
        }
        $raw = file_get_contents( $path );
        $decoded = is_string( $raw ) ? base64_decode( $raw, true ) : false;
        if ( false === $decoded || strlen( $decoded ) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ) {
            return new \WP_Error( 'mvm_mail_attachment_meta', 'Bijlagemetadata is ongeldig.' );
        }
        $nonce = substr( $decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
        $plain = sodium_crypto_secretbox_open( substr( $decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ), $nonce, $this->key() );
        if ( false === $plain ) {
            return new \WP_Error( 'mvm_mail_attachment_meta', 'Bijlagemetadata kon niet worden ontsleuteld.' );
        }
        $meta = json_decode( $plain, true );
        return is_array( $meta ) ? $meta : new \WP_Error( 'mvm_mail_attachment_meta', 'Bijlagemetadata is ongeldig.' );
    }

    /** @param array<string,mixed> $meta @return array<string,mixed> */
    private static function project( array $meta ): array {
        return array(
            'id'           => sanitize_text_field( (string) ( $meta['id'] ?? '' ) ),
            'name'         => sanitize_file_name( (string) ( $meta['name'] ?? '' ) ),
            'mimeType'     => sanitize_mime_type( (string) ( $meta['mimeType'] ?? '' ) ),
            'sizeBytes'    => max( 0, (int) ( $meta['sizeBytes'] ?? 0 ) ),
            'scanStatus'   => sanitize_key( (string) ( $meta['scanStatus'] ?? '' ) ),
            'contentValidationStatus' => sanitize_key( (string) ( $meta['contentValidationStatus'] ?? '' ) ),
            'createdAtUtc' => sanitize_text_field( (string) ( $meta['createdAtUtc'] ?? '' ) ),
        );
    }

    private function key(): string {
        return hash_hmac( 'sha256', 'mvm-hub-mail-attachments-v1', wp_salt( 'secure_auth' ), true );
    }

    private function valid_id( string $id ): bool {
        return 1 === preg_match( '/^[a-f0-9-]{36}$/i', $id );
    }
}
