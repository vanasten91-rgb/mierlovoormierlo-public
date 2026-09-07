<?php

define( 'ABSPATH', __DIR__ . '/' );

function mvm_test_require( bool $condition, string $message ): void {
	if ( $condition ) {
		return;
	}
	fwrite( STDERR, "FAIL: {$message}\n" );
	exit( 1 );
}

class WP_Role {
	public array $caps = array();
	public function __construct( array $caps = array() ) { $this->caps = $caps; }
	public function add_cap( string $cap ): void { $this->caps[ $cap ] = true; }
	public function remove_cap( string $cap ): void { unset( $this->caps[ $cap ] ); }
}

final class MvM_Test_Roles {
	public array $roles;
	public function __construct( array $names ) {
		$this->roles = array_fill_keys( $names, array() );
	}
}

$role_names = array(
	'administrator',
	'mvm_sysop',
	'mvm_teamleider',
	'mvm_editor',
	'mvm_moderator',
	'mvm_journalist',
	'mvm_redacteur',
	'mvm_vertaler',
	'mvm_fotograaf',
	'subscriber',
);
$role_objects = array();
foreach ( $role_names as $name ) {
	$role_objects[ $name ] = new WP_Role( 'subscriber' === $name ? array( 'read' => true ) : array() );
}
$options = array();

function wp_roles(): MvM_Test_Roles {
	global $role_names;
	return new MvM_Test_Roles( $role_names );
}

function get_role( string $name ): ?WP_Role {
	global $role_objects;
	return $role_objects[ $name ] ?? null;
}

function add_role( string $name, string $display_name, array $caps = array() ): WP_Role {
	global $role_names, $role_objects;
	unset( $display_name );
	$role = new WP_Role( $caps );
	$role_objects[ $name ] = $role;
	if ( ! in_array( $name, $role_names, true ) ) {
		$role_names[] = $name;
	}
	return $role;
}

function get_option( string $name, $default = false ) {
	global $options;
	return $options[ $name ] ?? $default;
}

function update_option( string $name, $value, bool $autoload = true ): bool {
	global $options;
	unset( $autoload );
	$options[ $name ] = $value;
	return true;
}

require_once __DIR__ . '/../plugins/mvm-platform/src/class-capabilities.php';

MvM_Platform_Capabilities::sync();

$staff = array(
	MvM_Platform_Capabilities::MARKETPLACE_MODERATE,
	MvM_Platform_Capabilities::NEWSLETTER_MANAGE,
	MvM_Platform_Capabilities::PWA_MANAGE,
	MvM_Platform_Capabilities::LOCAL_ADS_MANAGE,
);

foreach ( array( 'administrator', 'mvm_sysop', 'mvm_teamleider', 'mvm_editor' ) as $role ) {
	foreach ( $staff as $cap ) {
		mvm_test_require( isset( $role_objects[ $role ]->caps[ $cap ] ), $role . ' should have ' . $cap );
	}
	mvm_test_require( ! isset( $role_objects[ $role ]->caps[ MvM_Platform_Capabilities::LOCAL_ADS_SELF_MANAGE ] ), $role . ' must not enter entrepreneur self-service by default' );
}

foreach ( array( 'administrator', 'mvm_sysop' ) as $role ) {
	mvm_test_require( isset( $role_objects[ $role ]->caps[ MvM_Platform_Capabilities::LOCAL_ADS_ADMIN ] ), $role . ' should fully create/edit/delete local ads' );
}
foreach ( array( 'mvm_teamleider', 'mvm_editor', 'mvm_moderator' ) as $role ) {
	mvm_test_require( ! isset( $role_objects[ $role ]->caps[ MvM_Platform_Capabilities::LOCAL_ADS_ADMIN ] ), $role . ' must not receive admin-only local ad CRUD' );
}

mvm_test_require( isset( $role_objects['mvm_moderator']->caps[ MvM_Platform_Capabilities::MARKETPLACE_MODERATE ] ), 'moderator should moderate marketplace' );
mvm_test_require( ! isset( $role_objects['mvm_moderator']->caps[ MvM_Platform_Capabilities::NEWSLETTER_MANAGE ] ), 'moderator must not manage newsletter' );
mvm_test_require( ! isset( $role_objects['mvm_moderator']->caps[ MvM_Platform_Capabilities::PWA_MANAGE ] ), 'moderator must not manage PWA' );
mvm_test_require( ! isset( $role_objects['mvm_moderator']->caps[ MvM_Platform_Capabilities::LOCAL_ADS_SELF_MANAGE ] ), 'moderator must not enter entrepreneur center' );

foreach ( array( 'mvm_journalist', 'mvm_redacteur', 'mvm_vertaler', 'mvm_fotograaf', 'subscriber' ) as $role ) {
	foreach ( array_merge( $staff, array( MvM_Platform_Capabilities::LOCAL_ADS_SELF_MANAGE, MvM_Platform_Capabilities::LOCAL_ADS_ADMIN ) ) as $cap ) {
		mvm_test_require( ! isset( $role_objects[ $role ]->caps[ $cap ] ), $role . ' must not have ' . $cap );
	}
}

$entrepreneur = $role_objects[ MvM_Platform_Capabilities::ENTREPRENEUR_ROLE ] ?? null;
mvm_test_require( $entrepreneur instanceof WP_Role, 'Ondernemer role must be created' );
mvm_test_require( isset( $entrepreneur->caps['read'] ), 'Ondernemer needs only basic read access' );
mvm_test_require( isset( $entrepreneur->caps[ MvM_Platform_Capabilities::LOCAL_ADS_SELF_MANAGE ] ), 'Ondernemer must self-manage local ads' );
foreach ( array_merge( $staff, array( MvM_Platform_Capabilities::LOCAL_ADS_ADMIN ) ) as $cap ) {
	mvm_test_require( ! isset( $entrepreneur->caps[ $cap ] ), 'Ondernemer must not receive staff capability ' . $cap );
}

mvm_test_require( MvM_Platform_Capabilities::VERSION === (string) $options[ MvM_Platform_Capabilities::VERSION_OPTION ], 'capability version not persisted' );

echo "MvM platform capability runtime: OK\n";
