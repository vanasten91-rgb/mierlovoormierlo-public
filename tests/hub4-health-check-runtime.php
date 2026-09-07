<?php

declare( strict_types=1 );

define( 'ABSPATH', __DIR__ . '/' );

function get_option( string $name, mixed $default = false ): mixed {
    unset( $name );
    return $default;
}

function is_multisite(): bool {
    return false;
}

$GLOBALS['wpdb'] = new class() {
    public string $prefix = 'wp_';

    public function prepare( string $query, mixed ...$values ): string {
        unset( $values );
        return $query;
    }

    public function get_var( string $query ): mixed {
        unset( $query );
        return null;
    }
};

require_once dirname( __DIR__ ) . '/plugins/mvm-hub4-rc-direct/src/class-health-check.php';

$checks = array(
    array( 'status' => 'ok' ),
    array( 'status' => 'ok' ),
    array( 'status' => 'warning' ),
    array( 'status' => 'critical' ),
    array( 'status' => 'unknown' ),
    array( 'status' => 'invalid-status' ),
);

$summary = MvM_Hub4_Health_Check::summarize( $checks );
$expected = array( 'ok' => 2, 'warning' => 1, 'critical' => 1, 'unknown' => 2, 'total' => 6 );

if ( $expected !== $summary ) {
    fwrite( STDERR, 'Health Check summary does not match the status contract.' . PHP_EOL );
    exit( 1 );
}

if ( count( MvM_Hub4_Health_Check::definitions() ) < 26 ) {
    fwrite( STDERR, 'Health Check must define at least 26 local checks.' . PHP_EOL );
    exit( 1 );
}

$reflection = new ReflectionClass( MvM_Hub4_Health_Check::class );
foreach ( array( 'check_action_scheduler', 'check_temp_snippets' ) as $method_name ) {
    $result = $reflection->getMethod( $method_name )->invoke( null, gmdate( 'c' ) );
    if ( ! is_array( $result ) || 'unknown' !== ( $result['status'] ?? '' ) ) {
        fwrite( STDERR, "Missing optional dependency did not fail safely in {$method_name}." . PHP_EOL );
        exit( 1 );
    }
}

echo 'Hub 4 health check runtime: summary, definitions and optional-dependency fallback passed.' . PHP_EOL;
