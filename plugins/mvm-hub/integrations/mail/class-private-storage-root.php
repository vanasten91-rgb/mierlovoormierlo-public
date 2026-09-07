<?php

namespace MVM\Hub\Integrations\Mail;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Shared policy for confidential filesystem storage.
 *
 * Storage must be explicitly configured and live outside the WordPress webroot.
 */
final class Private_Storage_Root {
    /** @return string|\WP_Error */
    public static function path( bool $create = false ): string|\WP_Error {
        if ( ! defined( 'MVM_HUB_PRIVATE_STORAGE_DIR' ) || '' === trim( (string) MVM_HUB_PRIVATE_STORAGE_DIR ) ) {
            return new \WP_Error( 'mvm_private_storage_unconfigured', 'Private opslag is niet geconfigureerd.' );
        }

        $configured = rtrim( (string) MVM_HUB_PRIVATE_STORAGE_DIR, '/\\' );
        if ( $create && ! is_dir( $configured ) ) {
            if ( ! wp_mkdir_p( $configured ) ) {
                return new \WP_Error( 'mvm_private_storage_create', 'Private opslag kon niet worden aangemaakt.' );
            }
        }

        $root = realpath( $configured );
        if ( false === $root || ! is_dir( $root ) || ! is_readable( $root ) ) {
            return new \WP_Error( 'mvm_private_storage_missing', 'Private opslag is niet beschikbaar.' );
        }

        $webroot = realpath( ABSPATH );
        if ( false !== $webroot ) {
            $root_prefix = rtrim( $root, DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR;
            $web_prefix  = rtrim( $webroot, DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR;
            if ( str_starts_with( $root_prefix, $web_prefix ) || str_starts_with( $web_prefix, $root_prefix ) ) {
                return new \WP_Error( 'mvm_private_storage_webroot', 'Private opslag moet buiten de publieke WordPress-webroot staan.' );
            }
        }

        if ( ! is_writable( $root ) && $create ) {
            return new \WP_Error( 'mvm_private_storage_writable', 'Private opslag is niet schrijfbaar.' );
        }

        return $root;
    }

    /** @return string|\WP_Error */
    public static function directory( string $name ): string|\WP_Error {
        $root = self::path( true );
        if ( is_wp_error( $root ) ) {
            return $root;
        }
        $name = sanitize_key( $name );
        if ( '' === $name ) {
            return new \WP_Error( 'mvm_private_storage_dir', 'Ongeldige private opslagmap.' );
        }
        $dir = $root . DIRECTORY_SEPARATOR . $name;
        if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
            return new \WP_Error( 'mvm_private_storage_dir', 'Private opslagmap kon niet worden aangemaakt.' );
        }
        @chmod( $dir, 0700 );
        return $dir;
    }

    private function __construct() {}
}
