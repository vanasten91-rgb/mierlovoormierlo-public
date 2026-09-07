<?php

declare(strict_types=1);

define( 'ABSPATH', __DIR__ . '/' );

$hooks       = array();
$post_types  = array();
$taxonomies  = array();
$options     = array();
$flush_count = 0;
$existing_post_types = array();
$existing_taxonomies = array();

function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
    global $hooks;
    $hooks[] = array( $hook, $callback, $priority, $accepted_args );
}

function post_type_exists( $post_type ) {
    global $existing_post_types;
    return in_array( $post_type, $existing_post_types, true );
}

function taxonomy_exists( $taxonomy ) {
    global $existing_taxonomies;
    return in_array( $taxonomy, $existing_taxonomies, true );
}

function register_post_type( $post_type, $args ) {
    global $post_types;
    $post_types[ $post_type ] = $args;
    return (object) array( 'name' => $post_type );
}

function register_taxonomy( $taxonomy, $object_type, $args ) {
    global $taxonomies;
    $taxonomies[ $taxonomy ] = array( 'object_type' => $object_type, 'args' => $args );
    return (object) array( 'name' => $taxonomy );
}

function get_option( $name, $default = false ) {
    global $options;
    return array_key_exists( $name, $options ) ? $options[ $name ] : $default;
}

function update_option( $name, $value, $autoload = null ) {
    global $options;
    $options[ $name ] = $value;
    return true;
}

function flush_rewrite_rules( $hard = true ) {
    global $flush_count;
    $flush_count++;
}

require_once __DIR__ . '/../plugins/mvm-hub4-rc-direct/src/compat/class-encyclopedia-runtime.php';

function assert_true( bool $condition, string $message ): void {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
}

MvM_Hub4_Encyclopedia_Runtime::init();
assert_true( count( $hooks ) === 3, 'Runtime must register init, taxonomy init and admin rewrite hooks.' );
assert_true( $hooks[0][0] === 'init' && $hooks[0][2] === 0, 'CPT registration must run at init priority 0.' );
assert_true( $hooks[1][0] === 'init' && $hooks[1][2] === 0, 'Taxonomy registration must run at init priority 0.' );
assert_true( $hooks[2][0] === 'admin_init', 'Rewrite refresh must be admin-only after normal boot.' );

MvM_Hub4_Encyclopedia_Runtime::register_content_types();
MvM_Hub4_Encyclopedia_Runtime::register_taxonomies();

$expected_rewrites = array(
    'mvm_encyclopedie' => 'encyclopedie/artikel',
    'mvm_persoon'      => 'encyclopedie/personen',
    'mvm_locatie'      => 'encyclopedie/locaties',
    'mvm_gebouw'       => 'encyclopedie/gebouwen',
    'mvm_gebeurtenis'  => 'encyclopedie/gebeurtenissen',
    'mvm_vereniging'   => 'encyclopedie/verenigingen',
    'mvm_bedrijf'      => 'encyclopedie/bedrijven',
    'mvm_beeld'        => 'encyclopedie/beeldbank',
    'mvm_bron'         => 'encyclopedie/bronnen',
);
assert_true( count( $post_types ) === 9, 'All nine canonical Encyclopedia post types must be restored.' );
foreach ( $expected_rewrites as $post_type => $rewrite ) {
    assert_true( isset( $post_types[ $post_type ] ), "Missing {$post_type}." );
    assert_true( $post_types[ $post_type ]['public'] === true, "{$post_type} must stay public." );
    assert_true( $post_types[ $post_type ]['show_in_rest'] === true, "{$post_type} must stay REST-visible." );
    assert_true( $post_types[ $post_type ]['rewrite']['slug'] === $rewrite, "Unexpected rewrite for {$post_type}." );
    assert_true( $post_types[ $post_type ]['rewrite']['with_front'] === false, "{$post_type} must not use front prefixes." );
}
assert_true( $post_types['mvm_encyclopedie']['rewrite']['slug'] === 'encyclopedie/artikel', 'The live /encyclopedie/artikel/{slug}/ contract must be restored.' );

$expected_taxonomies = array(
    'mvm_thema'   => 'encyclopedie/thema',
    'mvm_periode' => 'encyclopedie/periode',
    'mvm_status'  => 'encyclopedie/status',
    'mvm_gebied'  => 'encyclopedie/gebied',
);
assert_true( count( $taxonomies ) === 4, 'All four canonical Encyclopedia taxonomies must be restored.' );
foreach ( $expected_taxonomies as $taxonomy => $rewrite ) {
    assert_true( isset( $taxonomies[ $taxonomy ] ), "Missing {$taxonomy}." );
    assert_true( $taxonomies[ $taxonomy ]['args']['rewrite']['slug'] === $rewrite, "Unexpected rewrite for {$taxonomy}." );
    assert_true( count( $taxonomies[ $taxonomy ]['object_type'] ) === 9, "{$taxonomy} must attach to all Encyclopedia content types." );
}

// Existing registrations are never overwritten.
$post_types = array();
$existing_post_types = array( 'mvm_encyclopedie' );
MvM_Hub4_Encyclopedia_Runtime::register_content_types();
assert_true( ! isset( $post_types['mvm_encyclopedie'] ), 'An existing canonical post type must remain owned by its original runtime.' );
assert_true( count( $post_types ) === 8, 'Only missing post types may be filled in.' );

$taxonomies = array();
$existing_taxonomies = array( 'mvm_thema' );
MvM_Hub4_Encyclopedia_Runtime::register_taxonomies();
assert_true( ! isset( $taxonomies['mvm_thema'] ), 'An existing canonical taxonomy must remain owned by its original runtime.' );
assert_true( count( $taxonomies ) === 3, 'Only missing taxonomies may be filled in.' );

// Rewrite refresh is one-shot and does not touch encyclopedia content.
$existing_post_types = array();
$existing_taxonomies = array();
MvM_Hub4_Encyclopedia_Runtime::maybe_flush_rewrite_rules();
assert_true( $flush_count === 1, 'First rc5 admin boot must refresh rewrite rules exactly once.' );
MvM_Hub4_Encyclopedia_Runtime::maybe_flush_rewrite_rules();
assert_true( $flush_count === 1, 'Rewrite refresh must be idempotent after the version marker is stored.' );

// If the canonical 2.94.0 class is restored later, compatibility becomes dormant.
eval( 'class MVM_Encyclopedie {}' );
$post_types = array();
$taxonomies = array();
MvM_Hub4_Encyclopedia_Runtime::register_content_types();
MvM_Hub4_Encyclopedia_Runtime::register_taxonomies();
assert_true( $post_types === array() && $taxonomies === array(), 'Canonical MVM_Encyclopedie runtime must always take precedence.' );

fwrite( STDOUT, "Hub4 Encyclopedia runtime regression: PASS\n" );
