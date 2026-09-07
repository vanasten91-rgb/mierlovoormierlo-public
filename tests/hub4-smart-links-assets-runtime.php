<?php

declare(strict_types=1);

define( 'ABSPATH', __DIR__ . '/' );
define( 'MVM_HUB4_DIR', dirname( __DIR__ ) . '/plugins/mvm-hub4-rc-direct/' );
define( 'MVM_HUB4_URL', 'https://example.test/wp-content/plugins/mvm-hub4-rc-direct/' );

final class Test_Smart_Links_Scripts {
    public array $data = array();

    public function get_data( string $handle, string $key ): mixed {
        return $this->data[ $handle ][ $key ] ?? false;
    }
}

$GLOBALS['smart_actions']   = array();
$GLOBALS['smart_registered'] = array();
$GLOBALS['smart_enqueued']   = array();
$GLOBALS['smart_localized']  = array();
$GLOBALS['smart_scripts']    = new Test_Smart_Links_Scripts();
$GLOBALS['smart_hub']        = 'user';
$GLOBALS['smart_admin']      = false;
$GLOBALS['smart_preview']    = false;

function add_action( string $hook, mixed $callback, int $priority = 10 ): void {
    $GLOBALS['smart_actions'][] = array( $hook, $callback, $priority );
}
function wp_register_script( string $handle, string $src, array $deps = array(), string|bool|null $ver = false, bool $in_footer = false ): bool {
    $GLOBALS['smart_registered'][ $handle ] = compact( 'src', 'deps', 'ver', 'in_footer' );
    return true;
}
function wp_script_is( string $handle, string $status = 'enqueued' ): bool {
    if ( 'registered' === $status ) {
        return isset( $GLOBALS['smart_registered'][ $handle ] );
    }
    return in_array( $handle, $GLOBALS['smart_enqueued'], true );
}
function wp_enqueue_script( string $handle ): void {
    if ( ! in_array( $handle, $GLOBALS['smart_enqueued'], true ) ) {
        $GLOBALS['smart_enqueued'][] = $handle;
    }
}
function wp_scripts(): Test_Smart_Links_Scripts {
    return $GLOBALS['smart_scripts'];
}
function wp_localize_script( string $handle, string $object_name, array $data ): bool {
    $GLOBALS['smart_localized'][] = array( $handle, $object_name, $data );
    $GLOBALS['smart_scripts']->data[ $handle ]['data'] = 'var ' . $object_name . ' = {};';
    return true;
}
function sanitize_key( string $value ): string {
    return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $value ) ) ?? '';
}
function wp_unslash( string $value ): string { return $value; }
function rest_url( string $path = '' ): string { return 'https://example.test/wp-json/' . ltrim( $path, '/' ); }
function esc_url_raw( string $url ): string { return $url; }
function wp_create_nonce( string $action ): string { return 'nonce-' . $action; }
function mvm_hubs_v3_current_hub(): string { return (string) $GLOBALS['smart_hub']; }
function mvm_hubs_v3_can_access( string $hub ): bool { return 'admin' === $hub && true === $GLOBALS['smart_admin']; }
function mvm_hubs_v3_is_draft_preview(): bool { return true === $GLOBALS['smart_preview']; }

function expect_true( bool $condition, string $message ): void {
    if ( ! $condition ) {
        fwrite( STDERR, $message . PHP_EOL );
        exit( 1 );
    }
}

require dirname( __DIR__ ) . '/plugins/mvm-hub4-rc-direct/src/compat/class-smart-links-assets.php';

MvM_Hub4_Smart_Links_Assets::boot();
expect_true( in_array( array( 'wp_enqueue_scripts', array( 'MvM_Hub4_Smart_Links_Assets', 'register' ), 1 ), $GLOBALS['smart_actions'], true ), 'Canonical Smart Links registratiehook ontbreekt.' );
expect_true( in_array( array( 'wp_enqueue_scripts', array( 'MvM_Hub4_Smart_Links_Assets', 'ensure_hub_asset' ), 2501 ), $GLOBALS['smart_actions'], true ), 'Standalone Smart Links runtimehook ontbreekt.' );

MvM_Hub4_Smart_Links_Assets::register();
expect_true( isset( $GLOBALS['smart_registered']['mvm-smart-links-v3'] ), 'Canonical Smart Links handle is niet geregistreerd.' );
$registered = $GLOBALS['smart_registered']['mvm-smart-links-v3'];
expect_true( MVM_HUB4_URL . 'assets/mvm-smart-links-hub.js' === $registered['src'], 'Smart Links gebruikt niet de plugin-URL.' );
expect_true( array( 'mvm-hubs-v3' ) === $registered['deps'], 'Smart Links mist de Hub-v3 basisdependency.' );

$_GET['deel'] = 'integraties';
MvM_Hub4_Smart_Links_Assets::ensure_hub_asset();
expect_true( ! $GLOBALS['smart_enqueued'], 'Niet-admin context mocht Smart Links niet enqueuen.' );
expect_true( ! $GLOBALS['smart_localized'], 'Niet-admin context mocht Smart Links niet lokaliseren.' );

$GLOBALS['smart_hub']   = 'admin';
$GLOBALS['smart_admin'] = true;
MvM_Hub4_Smart_Links_Assets::ensure_hub_asset();
expect_true( in_array( 'mvm-smart-links-v3', $GLOBALS['smart_enqueued'], true ), 'Plugin-owned Smart Links is niet zelfstandig gequeued.' );
expect_true( 1 === count( $GLOBALS['smart_localized'] ), 'Plugin-owned Smart Links config is niet exact één keer toegevoegd.' );
$config = $GLOBALS['smart_localized'][0];
expect_true( 'mvm-smart-links-v3' === $config[0], 'Smart Links config hangt aan de verkeerde handle.' );
expect_true( 'MvMHubs3SmartLinksConfig' === $config[1], 'Smart Links gebruikt de verkeerde bridge-objectnaam.' );
expect_true( 'https://example.test/wp-json/mvm/v1/admin/' === $config[2]['restRoot'], 'Smart Links gebruikt de verkeerde REST-root.' );
expect_true( false === $config[2]['readOnly'], 'Normale adminruntime werd ten onrechte read-only.' );

MvM_Hub4_Smart_Links_Assets::ensure_hub_asset();
expect_true( 1 === count( $GLOBALS['smart_localized'] ), 'Bestaande Smart Links config werd dubbel gelokaliseerd.' );

echo "MvM Smart Links standalone asset runtime: OK\n";
