<?php

declare(strict_types=1);

$mode = $argv[1] ?? 'clean';
if ( ! in_array( $mode, array( 'clean', 'legacy' ), true ) ) {
    fwrite( STDERR, "Usage: php encyclopedie-next-activation-collision.php [clean|legacy]\n" );
    exit( 2 );
}

define( 'ABSPATH', __DIR__ . '/wordpress-test/' );

if ( 'legacy' === $mode ) {
    final class MVM_Encyclopedie {
        public const OWNER = 'legacy';
    }
}

$GLOBALS['mvm_e3_test_actions'] = array();
$GLOBALS['mvm_e3_test_activation_hook'] = null;
$GLOBALS['mvm_e3_test_deactivation_hook'] = null;
$GLOBALS['mvm_e3_test_post_types'] = array();
$GLOBALS['mvm_e3_test_taxonomies'] = array();
$GLOBALS['mvm_e3_test_flushes'] = 0;
$GLOBALS['mvm_e3_test_options'] = array();

function plugin_dir_path( string $file ): string {
    return dirname( $file ) . '/';
}

function plugin_dir_url( string $file ): string {
    return 'https://example.invalid/wp-content/plugins/' . basename( dirname( $file ) ) . '/';
}

function add_action( string $hook, $callback, int $priority = 10, int $accepted_args = 1 ): void {
    $GLOBALS['mvm_e3_test_actions'][] = array( $hook, $callback, $priority, $accepted_args );
}

function register_activation_hook( string $file, $callback ): void {
    $GLOBALS['mvm_e3_test_activation_hook'] = array( $file, $callback );
}

function register_deactivation_hook( string $file, $callback ): void {
    $GLOBALS['mvm_e3_test_deactivation_hook'] = array( $file, $callback );
}

function post_type_exists( string $post_type ): bool {
    return isset( $GLOBALS['mvm_e3_test_post_types'][ $post_type ] );
}

function register_post_type( string $post_type, array $args ) {
    $GLOBALS['mvm_e3_test_post_types'][ $post_type ] = $args;
    return (object) array( 'name' => $post_type );
}

function taxonomy_exists( string $taxonomy ): bool {
    return isset( $GLOBALS['mvm_e3_test_taxonomies'][ $taxonomy ] );
}

function register_taxonomy( string $taxonomy, $object_type, array $args ) {
    $GLOBALS['mvm_e3_test_taxonomies'][ $taxonomy ] = array(
        'object_type' => $object_type,
        'args' => $args,
    );
    return (object) array( 'name' => $taxonomy );
}

function flush_rewrite_rules( bool $hard = true ): void {
    $GLOBALS['mvm_e3_test_flushes']++;
}

function update_option( string $option, $value, $autoload = null ): bool {
    $GLOBALS['mvm_e3_test_options'][ $option ] = $value;
    return true;
}

require dirname( __DIR__ ) . '/plugins/mvm-encyclopedie-next/mvm-encyclopedie-next.php';

if ( ! class_exists( 'MVM_Encyclopedie_Next_Runtime', false ) ) {
    fwrite( STDERR, "Next runtime class did not load.\n" );
    exit( 1 );
}

$activation = $GLOBALS['mvm_e3_test_activation_hook'][1] ?? null;
if ( ! is_array( $activation ) || 'MVM_Encyclopedie_Next_Runtime' !== ( $activation[0] ?? null ) || 'activate' !== ( $activation[1] ?? null ) ) {
    fwrite( STDERR, "Activation hook is not owned by the collision-safe Next runtime.\n" );
    exit( 1 );
}

call_user_func( $activation );

if ( 9 !== count( $GLOBALS['mvm_e3_test_post_types'] ) ) {
    fwrite( STDERR, "Activation did not register all 9 encyclopedia post types.\n" );
    exit( 1 );
}
if ( 4 !== count( $GLOBALS['mvm_e3_test_taxonomies'] ) ) {
    fwrite( STDERR, "Activation did not register all 4 encyclopedia taxonomies.\n" );
    exit( 1 );
}
if ( 1 !== $GLOBALS['mvm_e3_test_flushes'] ) {
    fwrite( STDERR, "Activation must flush rewrites exactly once.\n" );
    exit( 1 );
}
if ( '3.0.0-alpha4' !== ( $GLOBALS['mvm_e3_test_options']['mvm_encyclopedie_next_rewrite_version'] ?? null ) ) {
    fwrite( STDERR, "Activation did not persist the alpha4 rewrite version.\n" );
    exit( 1 );
}

$marker_hook_found = false;
foreach ( $GLOBALS['mvm_e3_test_actions'] as $action ) {
    if (
        'after_setup_theme' === $action[0]
        && is_array( $action[1] )
        && 'MVM_Encyclopedie_Next_Runtime' === ( $action[1][0] ?? null )
        && 'ensure_legacy_marker' === ( $action[1][1] ?? null )
    ) {
        $marker_hook_found = true;
        break;
    }
}
if ( ! $marker_hook_found ) {
    fwrite( STDERR, "Legacy marker hand-off is not deferred until after_setup_theme.\n" );
    exit( 1 );
}

MVM_Encyclopedie_Next_Runtime::ensure_legacy_marker();

if ( 'legacy' === $mode ) {
    if ( ! class_exists( 'MVM_Encyclopedie', false ) || 'legacy' !== MVM_Encyclopedie::OWNER ) {
        fwrite( STDERR, "Next replaced an already-loaded legacy MVM_Encyclopedie owner.\n" );
        exit( 1 );
    }
} else {
    if ( ! class_exists( 'MVM_Encyclopedie', false ) ) {
        fwrite( STDERR, "Next did not publish the historical marker when the name was free.\n" );
        exit( 1 );
    }
    if ( ! is_a( 'MVM_Encyclopedie', 'MVM_Encyclopedie_Next_Runtime', true ) ) {
        fwrite( STDERR, "Historical marker is not an alias of the Next runtime.\n" );
        exit( 1 );
    }
}

fwrite( STDOUT, "Encyclopedie Next activation collision test ({$mode}): OK\n" );
