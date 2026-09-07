<?php

declare(strict_types=1);

$root = dirname( __DIR__ );

define( 'ABSPATH', $root . '/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );
define( 'MVM_HUB_ENABLE_ROUTE_TAKEOVER', true );

$GLOBALS['mvm_test_actions'] = array();
$GLOBALS['mvm_test_filters'] = array();

function plugin_dir_path( string $file ): string {
    return rtrim( dirname( $file ), '/\\' ) . '/';
}

function plugin_dir_url( string $file ): string {
    return 'https://example.test/wp-content/plugins/' . basename( dirname( $file ) ) . '/';
}

function add_action( string $hook, callable $callback, int $priority = 10, int $accepted_args = 1 ): bool {
    unset( $priority, $accepted_args );
    $GLOBALS['mvm_test_actions'][ $hook ][] = $callback;
    return true;
}

function add_filter( string $hook, callable $callback, int $priority = 10, int $accepted_args = 1 ): bool {
    unset( $priority, $accepted_args );
    $GLOBALS['mvm_test_filters'][ $hook ][] = $callback;
    return true;
}

function wp_get_environment_type(): string {
    return 'staging';
}

require $root . '/plugins/mvm-hub/mvm-hub.php';

$plugins_loaded = $GLOBALS['mvm_test_actions']['plugins_loaded'] ?? array();
if ( 1 !== count( $plugins_loaded ) ) {
    fwrite( STDERR, "FAIL: expected exactly one plugins_loaded bootstrap callback\n" );
    exit( 1 );
}

call_user_func( $plugins_loaded[0] );

if ( ! \MVM\Hub\Core\Runtime_Gates::route_takeover_enabled() ) {
    fwrite( STDERR, "FAIL: staging route gate did not enable with explicit constant\n" );
    exit( 1 );
}

$template_redirect = $GLOBALS['mvm_test_actions']['template_redirect'] ?? array();
if ( 2 !== count( $template_redirect ) ) {
    fwrite( STDERR, "FAIL: staging route gate must register the staff-gateway hardener and Hub runtime callbacks\n" );
    exit( 1 );
}

$registered = array();
foreach ( $template_redirect as $callback ) {
    if ( is_array( $callback ) && 2 === count( $callback ) ) {
        $owner = is_string( $callback[0] ) ? $callback[0] : ( is_object( $callback[0] ) ? get_class( $callback[0] ) : '' );
        $registered[] = $owner . '::' . (string) $callback[1];
    }
}
sort( $registered );
$expected = array(
    'MVM\\Hub\\Core\\Hub_Runtime::maybe_render',
    'MVM\\Hub\\Core\\Staff_Login_Recaptcha::start_gateway_patch',
);
sort( $expected );
if ( $expected !== $registered ) {
    fwrite( STDERR, "FAIL: staging route callbacks differ from the expected secure gateway/runtime ownership\n" );
    exit( 1 );
}

if ( \MVM\Hub\Core\Runtime_Gates::shell_preview_enabled() ) {
    fwrite( STDERR, "FAIL: route takeover must not implicitly enable shell preview\n" );
    exit( 1 );
}

if ( \MVM\Hub\Core\Runtime_Gates::communications_writes_enabled() ) {
    fwrite( STDERR, "FAIL: route takeover must not implicitly enable communications writes\n" );
    exit( 1 );
}

if ( \MVM\Hub\Core\Runtime_Gates::mail_writes_enabled() ) {
    fwrite( STDERR, "FAIL: route takeover must not implicitly enable mail delivery\n" );
    exit( 1 );
}

echo "PASS: MvM Hub staging route runtime registration smoke\n";
