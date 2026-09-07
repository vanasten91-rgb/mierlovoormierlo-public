<?php

declare( strict_types=1 );

define( 'ABSPATH', __DIR__ . '/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );

final class WP_Error {
    public function __construct(
        private string $code,
        private string $message,
        private array $data = array()
    ) {}

    public function get_error_code(): string {
        return $this->code;
    }

    public function get_error_data(): array {
        return $this->data;
    }
}

final class WP_User {
    public int $ID = 7;
    public array $roles = array( 'mvm_redacteur' );
    public array $caps = array();

    public function exists(): bool {
        return $this->ID > 0;
    }
}

final class MvM_Hub4_Audit {
    public static array $events = array();

    public static function log( string $event, string $result, array $data = array() ): void {
        self::$events[] = compact( 'event', 'result', 'data' );
    }
}

$GLOBALS['hub4_test_user']       = new WP_User();
$GLOBALS['hub4_test_logged_in']  = false;
$GLOBALS['hub4_test_token']      = '';
$GLOBALS['hub4_test_transients'] = array();
$GLOBALS['hub4_test_destroyed']  = false;
$GLOBALS['hub4_test_cookie']     = false;

function add_filter(): void {}
function apply_filters( string $hook, mixed $value ): mixed { return $value; }
function is_user_logged_in(): bool { return (bool) $GLOBALS['hub4_test_logged_in']; }
function wp_get_current_user(): WP_User { return $GLOBALS['hub4_test_user']; }
function get_current_user_id(): int { return is_user_logged_in() ? $GLOBALS['hub4_test_user']->ID : 0; }
function wp_get_session_token(): string { return (string) $GLOBALS['hub4_test_token']; }
function user_can( WP_User $user, string $capability ): bool { return ! empty( $user->caps[ $capability ] ); }
function current_user_can( string $capability ): bool { return user_can( $GLOBALS['hub4_test_user'], $capability ); }
function get_transient( string $key ): mixed { return $GLOBALS['hub4_test_transients'][ $key ] ?? false; }
function set_transient( string $key, mixed $value, int $expiration ): bool {
    unset( $expiration );
    $GLOBALS['hub4_test_transients'][ $key ] = $value;
    return true;
}
function delete_transient( string $key ): bool {
    unset( $GLOBALS['hub4_test_transients'][ $key ] );
    return true;
}
function wp_destroy_current_session(): void { $GLOBALS['hub4_test_destroyed'] = true; }
function wp_clear_auth_cookie(): void { $GLOBALS['hub4_test_cookie'] = true; }
function wp_salt(): string { return 'hub4-test-salt'; }
function wp_generate_uuid4(): string { return '00000000-0000-4000-8000-000000000001'; }
function absint( mixed $value ): int { return abs( (int) $value ); }
function sanitize_key( string $value ): string { return preg_replace( '/[^a-z0-9_-]/', '', strtolower( $value ) ) ?? ''; }

require_once dirname( __DIR__ ) . '/plugins/mvm-hub4-rc-direct/src/class-session.php';
require_once dirname( __DIR__ ) . '/plugins/mvm-hub4-rc-direct/src/class-security.php';

$failures = array();
$check    = static function ( bool $condition, string $message ) use ( &$failures ): void {
    if ( ! $condition ) {
        $failures[] = $message;
    }
};

$result = MvM_Hub4_Security::require_hub_access();
$check( $result instanceof WP_Error && 401 === ( $result->get_error_data()['status'] ?? 0 ), 'Unauthenticated access must return 401.' );

$GLOBALS['hub4_test_logged_in'] = true;
$result = MvM_Hub4_Security::require_hub_access();
$check( $result instanceof WP_Error && 403 === ( $result->get_error_data()['status'] ?? 0 ), 'Missing Hub capability must return 403.' );

$GLOBALS['hub4_test_user']->caps[ MvM_Hub4_Security::HUB_CAPABILITY ] = true;
$result = MvM_Hub4_Security::require_hub_access();
$check( $result instanceof WP_Error && 'mvm_hub4_session_missing' === $result->get_error_code(), 'Missing session token must fail closed.' );

$GLOBALS['hub4_test_token'] = 'wordpress-session-token';
$result = MvM_Hub4_Security::require_hub_access();
$check( true === $result && 1 === count( $GLOBALS['hub4_test_transients'] ), 'Valid Hub access must create token-bound tracking.' );

$permission = MvM_Hub4_Security::require_capability( 'mvm_hub4_runtime_test' );
$result     = $permission();
$check( $result instanceof WP_Error && 403 === ( $result->get_error_data()['status'] ?? 0 ), 'Missing route capability must return 403.' );

$GLOBALS['hub4_test_user']->caps['mvm_hub4_runtime_test'] = true;
$check( true === $permission(), 'Authorized route capability must pass.' );

$transient_key = (string) array_key_first( $GLOBALS['hub4_test_transients'] );
$GLOBALS['hub4_test_transients'][ $transient_key ]['last_activity'] = time() - MvM_Hub4_Session::idle_seconds() - 1;
$result = MvM_Hub4_Session::validate_and_touch();
$check( $result instanceof WP_Error && 'mvm_hub4_session_expired' === $result->get_error_code(), 'Idle Hub session must expire.' );
$check( $GLOBALS['hub4_test_destroyed'] && $GLOBALS['hub4_test_cookie'], 'Session expiry must invalidate token and auth cookie.' );

if ( $failures ) {
    fwrite( STDERR, implode( PHP_EOL, $failures ) . PHP_EOL );
    exit( 1 );
}

echo 'Hub 4 permission/session runtime: authorized and unauthorized paths passed.' . PHP_EOL;
