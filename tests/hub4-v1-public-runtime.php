<?php

declare(strict_types=1);

// Runtime contract for the public Hub 4 v1 boundary. This intentionally loads
// only class-public-rest.php with minimal WordPress stubs.
define( 'ABSPATH', __DIR__ . '/' );

final class WP_Error {
    public function __construct(
        private string $code,
        private string $message = '',
        private array $data = array()
    ) {}

    public function get_error_code(): string { return $this->code; }
    public function get_error_message(): string { return $this->message; }
    public function get_error_data(): array { return $this->data; }
}

final class WP_REST_Response {
    public function __construct( private mixed $data ) {}
    public function get_data(): mixed { return $this->data; }
}

final class WP_REST_Server {
    public const READABLE  = 'GET';
    public const CREATABLE = 'POST';
}

final class WP_REST_Request implements ArrayAccess {
    public function __construct(
        private string $method,
        private array $params = array(),
        private array $json = array()
    ) {}

    public function get_method(): string { return $this->method; }
    public function get_param( string $key ): mixed { return $this->params[ $key ] ?? $this->json[ $key ] ?? null; }
    public function get_json_params(): array { return $this->json; }
    public function get_params(): array { return array_merge( $this->params, $this->json ); }
    public function offsetExists( mixed $offset ): bool { return array_key_exists( (string) $offset, $this->params ); }
    public function offsetGet( mixed $offset ): mixed { return $this->params[ (string) $offset ] ?? null; }
    public function offsetSet( mixed $offset, mixed $value ): void { $this->params[ (string) $offset ] = $value; }
    public function offsetUnset( mixed $offset ): void { unset( $this->params[ (string) $offset ] ); }
}

$GLOBALS['mvm_test_transients'] = array();
$GLOBALS['mvm_test_routes'] = array();
$GLOBALS['mvm_test_logged_in'] = false;
$GLOBALS['mvm_test_can_read'] = false;
$GLOBALS['mvm_test_signal_payload'] = null;
$GLOBALS['mvm_test_correction_payload'] = null;

function add_action( string $hook, callable $callback ): void { unset( $hook, $callback ); }
function register_rest_route( string $namespace, string $route, array $definition ): void {
    $GLOBALS['mvm_test_routes'][] = array( $namespace, $route, $definition );
}
function is_user_logged_in(): bool { return (bool) $GLOBALS['mvm_test_logged_in']; }
function current_user_can( string $capability ): bool { return 'read' === $capability && (bool) $GLOBALS['mvm_test_can_read']; }
function sanitize_key( string $value ): string { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $value ) ) ?? ''; }
function sanitize_title( string $value ): string { return trim( preg_replace( '/[^a-z0-9]+/', '-', strtolower( $value ) ) ?? '', '-' ); }
function absint( mixed $value ): int { return abs( (int) $value ); }
function wp_unslash( mixed $value ): mixed { return $value; }
function wp_salt( string $scheme = 'auth' ): string { return 'unit-test-salt-' . $scheme; }
function get_transient( string $key ): mixed { return $GLOBALS['mvm_test_transients'][ $key ] ?? false; }
function set_transient( string $key, mixed $value, int $expiration ): bool {
    unset( $expiration );
    $GLOBALS['mvm_test_transients'][ $key ] = $value;
    return true;
}
function is_wp_error( mixed $value ): bool { return $value instanceof WP_Error; }
function rest_ensure_response( mixed $value ): WP_REST_Response { return $value instanceof WP_REST_Response ? $value : new WP_REST_Response( $value ); }

final class MvM_Hub4_Signals {
    public static function create_public( array $payload ): int|WP_Error {
        $GLOBALS['mvm_test_signal_payload'] = $payload;
        return 4242;
    }
}

final class MvM_Hub4_Corrections {
    public static function create_public( array $payload ): int|WP_Error {
        $GLOBALS['mvm_test_correction_payload'] = $payload;
        return 5252;
    }

    public static function public_for_post( int $post_id ): array { return array( array( 'postId' => $post_id ) ); }
}

final class MvM_Hub4_Dossiers {
    public static function public_list( int $limit ): array { return array( array( 'limit' => $limit ) ); }
    public static function public_by_slug( string $slug ): array|WP_Error { return array( 'slug' => $slug ); }
}

final class MvM_Hub4_Personalization {
    public static function get_current(): array { return array( 'topics' => array() ); }
    public static function update_current( array $topics ): array|WP_Error { return array( 'topics' => $topics ); }
    public static function feed( int $limit ): array { return array( 'items' => array(), 'limit' => $limit ); }
}

require_once dirname( __DIR__ ) . '/plugins/mvm-hub4-rc-direct/src/class-public-rest.php';

$assertions = 0;
$check = static function ( bool $condition, string $message ) use ( &$assertions ): void {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
    ++$assertions;
};

$error_code = static fn( mixed $result ): string => $result instanceof WP_Error ? $result->get_error_code() : '';
$error_status = static fn( mixed $result ): int => $result instanceof WP_Error ? (int) ( $result->get_error_data()['status'] ?? 0 ) : 0;

// Route registrations must preserve the split public namespace and named permissions.
MvM_Hub4_Public_REST::register_routes();
$check( 7 === count( $GLOBALS['mvm_test_routes'] ), 'Expected seven public v1 route registrations.' );
foreach ( $GLOBALS['mvm_test_routes'] as [ $namespace, $route, $definition ] ) {
    $check( 'mvm-public/v1' === $namespace, "Unexpected public namespace for {$route}." );
    $variants = isset( $definition[0] ) && is_array( $definition[0] ) ? $definition : array( $definition );
    foreach ( $variants as $variant ) {
        $check( isset( $variant['permission_callback'] ) && is_callable( $variant['permission_callback'] ), "Missing callable permission callback for {$route}." );
    }
}

// Public reads are GET-only.
$check( true === MvM_Hub4_Public_REST::allow_public_read( new WP_REST_Request( 'GET' ) ), 'GET public read should be allowed.' );
$check( false === MvM_Hub4_Public_REST::allow_public_read( new WP_REST_Request( 'POST' ) ), 'POST may not pass the public read policy.' );

// Mijn Mierlo requires a real logged-in reader.
$auth = MvM_Hub4_Public_REST::allow_logged_in_reader( new WP_REST_Request( 'GET' ) );
$check( 'mvm_public_auth_required' === $error_code( $auth ) && 401 === $error_status( $auth ), 'Anonymous My Mierlo access must fail 401.' );
$GLOBALS['mvm_test_logged_in'] = true;
$auth = MvM_Hub4_Public_REST::allow_logged_in_reader( new WP_REST_Request( 'GET' ) );
$check( 'mvm_public_auth_required' === $error_code( $auth ), 'Logged-in account without read capability must fail.' );
$GLOBALS['mvm_test_can_read'] = true;
$check( true === MvM_Hub4_Public_REST::allow_logged_in_reader( new WP_REST_Request( 'GET' ) ), 'Logged-in reader should pass My Mierlo policy.' );

// Intake rejects wrong methods before touching a rate bucket.
$_SERVER['REMOTE_ADDR'] = '203.0.113.10';
$GLOBALS['mvm_test_transients'] = array();
$wrong_method = MvM_Hub4_Public_REST::allow_tip_intake( new WP_REST_Request( 'GET' ) );
$check( 'mvm_public_method' === $error_code( $wrong_method ) && 405 === $error_status( $wrong_method ), 'Tip intake must reject non-POST method.' );
$check( array() === $GLOBALS['mvm_test_transients'], 'Wrong-method request may not consume the rate limit.' );

// Honeypot is fail-closed and does not consume the legitimate quota.
$honeypot = MvM_Hub4_Public_REST::allow_tip_intake( new WP_REST_Request( 'POST', array( 'website' => 'spam.example' ) ) );
$check( 'mvm_public_invalid' === $error_code( $honeypot ) && 400 === $error_status( $honeypot ), 'Filled honeypot must be rejected generically.' );
$check( array() === $GLOBALS['mvm_test_transients'], 'Honeypot rejection may not consume the legitimate rate bucket.' );

// Five clean tip attempts are accepted; the sixth is rate limited.
for ( $i = 1; $i <= 5; ++$i ) {
    $check( true === MvM_Hub4_Public_REST::allow_tip_intake( new WP_REST_Request( 'POST', array( 'website' => '' ) ) ), "Tip attempt {$i} should be within quota." );
}
$limited = MvM_Hub4_Public_REST::allow_tip_intake( new WP_REST_Request( 'POST', array( 'website' => '' ) ) );
$check( 'mvm_public_rate_limited' === $error_code( $limited ) && 429 === $error_status( $limited ), 'Sixth tip attempt must be rate limited.' );
$transient_keys = array_keys( $GLOBALS['mvm_test_transients'] );
$check( 1 === count( $transient_keys ), 'Tip attempts from one address should use one rate bucket.' );
$check( ! str_contains( $transient_keys[0], '203.0.113.10' ), 'Rate bucket key may not contain the raw IP address.' );
$check( str_starts_with( $transient_keys[0], 'mvm_hub4_public_tip_' ), 'Tip rate bucket must be namespaced by intake kind.' );

// Correction intake uses a separate bucket, so an exhausted tip bucket cannot block it.
$check( true === MvM_Hub4_Public_REST::allow_correction_intake( new WP_REST_Request( 'POST', array( 'website' => '' ) ) ), 'Correction intake must have an independent rate bucket.' );
$check( 2 === count( $GLOBALS['mvm_test_transients'] ), 'Tip and correction quotas must be stored separately.' );

// Public create responses must stay generic even when services return internal IDs.
$tip_response = MvM_Hub4_Public_REST::create_tip(
    new WP_REST_Request(
        'POST',
        array(),
        array(
            'kind'         => 'news',
            'title'        => 'Testtip',
            'summary'      => 'Testomschrijving',
            'contactEmail' => 'private@example.test',
        )
    )
);
$check( $tip_response instanceof WP_REST_Response, 'Successful tip intake must return a REST response.' );
$tip_data = $tip_response->get_data();
$check( true === ( $tip_data['accepted'] ?? false ), 'Tip response must only acknowledge acceptance.' );
$check( ! array_key_exists( 'id', $tip_data ), 'Tip response may not reveal the internal signal ID.' );
$check( ! str_contains( json_encode( $tip_data, JSON_THROW_ON_ERROR ), 'private@example.test' ), 'Tip response may not reflect submitter contact data.' );
$check( 'private@example.test' === ( $GLOBALS['mvm_test_signal_payload']['contactEmail'] ?? '' ), 'Contact data must still reach the internal signal service.' );

$correction_response = MvM_Hub4_Public_REST::create_correction(
    new WP_REST_Request(
        'POST',
        array(),
        array(
            'postId'       => 77,
            'message'      => 'Feit klopt niet.',
            'contactEmail' => 'correction@example.test',
        )
    )
);
$check( $correction_response instanceof WP_REST_Response, 'Successful correction intake must return a REST response.' );
$correction_data = $correction_response->get_data();
$check( true === ( $correction_data['accepted'] ?? false ) && ! array_key_exists( 'id', $correction_data ), 'Correction response must be generic and hide its internal ID.' );
$check( ! str_contains( json_encode( $correction_data, JSON_THROW_ON_ERROR ), 'correction@example.test' ), 'Correction response may not reflect contact data.' );

fwrite( STDOUT, "Hub 4 v1 public runtime: {$assertions} assertions passed.\n" );
