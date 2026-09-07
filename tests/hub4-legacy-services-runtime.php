<?php

declare(strict_types=1);

define( 'ABSPATH', __DIR__ . '/runtime-wordpress/' );
define( 'WP_PLUGIN_DIR', sys_get_temp_dir() . '/mvm-hub4-legacy-runtime/plugins' );
define( 'MVM_HUB4_DIR', dirname( __DIR__ ) . '/plugins/mvm-hub4-rc-direct/' );

function get_option( string $name, mixed $default = false ): mixed {
    if ( 'active_plugins' === $name ) {
        return array( 'mierlo-voor-mierlo-hub/mierlo-voor-mierlo-hub.php' );
    }

    return $default;
}

function trailingslashit( string $value ): string {
    return rtrim( $value, "/\\" ) . '/';
}

function add_action( mixed ...$args ): void {
    unset( $args );
}

function current_user_can( string $capability ): bool {
    unset( $capability );
    return true;
}

function esc_html__( string $text, string $domain = 'default' ): string {
    unset( $domain );
    return $text;
}

require dirname( __DIR__ ) . '/plugins/mvm-hub4-rc-direct/src/class-legacy-services.php';

MvM_Hub4_Legacy_Services::bootstrap();
$status = MvM_Hub4_Legacy_Services::status();

$assert = static function ( bool $condition, string $message ): void {
    if ( ! $condition ) {
        fwrite( STDERR, "Assertion failed: {$message}\n" );
        exit( 1 );
    }
};

$assert( 'external_active' === $status['state'], 'active Hub3 must suppress adopted payload loading' );
$assert( 'hub3-active' === $status['source'], 'runtime state must identify active Hub3 as the source' );
$assert( '' === $status['root'], 'runtime must not touch a payload root while Hub3 is active' );
$assert( '3.8.2' === $status['legacyVersion'], 'legacy version must remain pinned' );
$assert(
    '70673d5bdbe9c107103adb5eeccd4b55754b766729a0f21630b3ab6bb346fc39' === $status['expectedTreeSha256'],
    'full reCAPTCHA-hardened migration tree digest must remain pinned'
);

if ( defined( 'MVM_HUB4_LEGACY_SERVICES_ADOPTED' ) ) {
    fwrite( STDERR, "Assertion failed: adopted marker must not be set while Hub3 is active\n" );
    exit( 1 );
}

echo "Hub 4 legacy services runtime: active-Hub3 no-double-load gate passed.\n";
