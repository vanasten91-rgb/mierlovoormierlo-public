<?php

declare(strict_types=1);

define( 'ABSPATH', __DIR__ . '/' );
define( 'MVM_HUB4_VERSION', '1.3.0-rc4' );
define( 'MVM_HUB4_HUB_CAPS_SCHEMA', '2026.09.01-2' );

final class MvM_Hubs_V3_Security {}

final class Test_Hub_Coexistence_Role {
    public array $caps = array();
    public function add_cap( string $cap ): void { $this->caps[ $cap ] = true; }
    public function remove_cap( string $cap ): void { unset( $this->caps[ $cap ] ); }
    public function has_cap( string $cap ): bool { return ! empty( $this->caps[ $cap ] ); }
}

final class WP_REST_Server {
    public const READABLE = 'GET';
}

$GLOBALS['hub_roles'] = array(
    'administrator'  => new Test_Hub_Coexistence_Role(),
    'mvm_journalist' => new Test_Hub_Coexistence_Role(),
);
$GLOBALS['hub_options'] = array(
    'mvm_hub4_compat_caps_schema' => 'legacy-value',
    'mvm_hub4_compat_security_version' => '1.2.0',
);
$GLOBALS['hub_stylesheet'] = 'newsup-pro-child';
$GLOBALS['hub_routes'] = array();
$GLOBALS['hub_actions'] = array();
$GLOBALS['hub_updates'] = array();

function wp_roles(): object { return (object) array( 'roles' => array_fill_keys( array_keys( $GLOBALS['hub_roles'] ), array() ) ); }
function get_role( string $name ): ?Test_Hub_Coexistence_Role { return $GLOBALS['hub_roles'][ $name ] ?? null; }
function get_stylesheet(): string { return $GLOBALS['hub_stylesheet']; }
function get_option( string $key, mixed $default = false ): mixed { return $GLOBALS['hub_options'][ $key ] ?? $default; }
function update_option( string $key, mixed $value, bool $autoload = false ): bool {
    $GLOBALS['hub_updates'][ $key ] = $value;
    $GLOBALS['hub_options'][ $key ] = $value;
    return true;
}
function sanitize_text_field( string $value ): string { return trim( $value ); }
function wp_unslash( string $value ): string { return $value; }
function add_action( string $hook, mixed $callback, int $priority = 10 ): void { $GLOBALS['hub_actions'][] = array( $hook, $callback, $priority ); }
function register_rest_route( string $namespace, string $route, array $args ): void { $GLOBALS['hub_routes'][] = $namespace . $route; }
function is_user_logged_in(): bool { return true; }

require dirname( __DIR__ ) . '/plugins/mvm-hub4-rc-direct/src/compat/class-hub-security.php';

function expect_coexistence( bool $condition, string $message ): void {
    if ( ! $condition ) {
        fwrite( STDERR, $message . PHP_EOL );
        exit( 1 );
    }
}

MvM_Hub4_Hub_Security::activate();
expect_coexistence( array() === $GLOBALS['hub_updates'], 'Activatie schreef opties terwijl Secure Core actief is.' );
expect_coexistence( array() === $GLOBALS['hub_roles']['administrator']->caps, 'Activatie wijzigde Administrator-capabilities tijdens coexistence.' );
expect_coexistence( array() === $GLOBALS['hub_roles']['mvm_journalist']->caps, 'Activatie wijzigde journalist-capabilities tijdens coexistence.' );

MvM_Hub4_Hub_Security::maybe_sync_capabilities();
expect_coexistence( array() === $GLOBALS['hub_updates'], 'maybe_sync_capabilities schreef opties tijdens coexistence.' );
expect_coexistence( array() === $GLOBALS['hub_roles']['administrator']->caps, 'maybe_sync_capabilities wijzigde capabilities tijdens coexistence.' );

expect_coexistence( false === MvM_Hub4_Hub_Security::sync_capabilities(), 'Directe capability-sync moet fail-closed zijn tijdens coexistence.' );
expect_coexistence( array() === $GLOBALS['hub_updates'], 'Directe capability-sync schreef opties tijdens coexistence.' );
expect_coexistence( array() === $GLOBALS['hub_roles']['mvm_journalist']->caps, 'Directe capability-sync wijzigde capabilities tijdens coexistence.' );

MvM_Hub4_Hub_Security::register_rest_routes();
expect_coexistence( in_array( 'mvm-hub4/v1/context', $GLOBALS['hub_routes'], true ), 'Canonieke context-route ontbreekt tijdens coexistence.' );
expect_coexistence( ! in_array( 'mvm-hubs/v3/context', $GLOBALS['hub_routes'], true ), 'Legacy context-route mag niet dubbel registreren zolang Secure Core actief is.' );

echo "MvM Nieuws/Redactie Hub Secure-Core coexistence runtime: OK\n";
