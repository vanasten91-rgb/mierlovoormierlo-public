<?php

declare( strict_types=1 );

define( 'ABSPATH', __DIR__ . '/' );

require_once dirname( __DIR__ ) . '/plugins/mvm-hub4-rc-direct/src/class-audit.php';
require_once dirname( __DIR__ ) . '/plugins/mvm-hub4-rc-direct/src/class-response-hardening.php';

$response_method = new ReflectionMethod( MvM_Hub4_Response_Hardening::class, 'is_sensitive_key' );
$audit_method    = new ReflectionMethod( MvM_Hub4_Audit::class, 'is_sensitive_context_key' );

$failures = array();
$check    = static function ( bool $condition, string $message ) use ( &$failures ): void {
    if ( ! $condition ) {
        $failures[] = $message;
    }
};

foreach ( array( 'privateNote', 'userEmail', 'sessionToken', 'access_token', 'authorizationHeader' ) as $key ) {
    $check( true === $response_method->invoke( null, $key ), "REST-redactie mist {$key}." );
}

foreach ( array( 'sessionStatus', 'absoluteRemaining', 'requestId' ) as $key ) {
    $check( false === $response_method->invoke( null, $key ), "REST-redactie verwijdert veilig veld {$key}." );
}

foreach ( array( 'actor_email', 'access_token', 'session_token', 'private_note' ) as $key ) {
    $check( true === $audit_method->invoke( null, $key ), "Audit-redactie mist {$key}." );
}

foreach ( array( 'session_status', 'deleted_count', 'retention_days' ) as $key ) {
    $check( false === $audit_method->invoke( null, $key ), "Audit-redactie verwijdert veilig veld {$key}." );
}

if ( $failures ) {
    fwrite( STDERR, implode( PHP_EOL, $failures ) . PHP_EOL );
    exit( 1 );
}

echo 'Hub 4 audit/REST redaction: sensitive keys blocked, safe session metadata retained.' . PHP_EOL;
