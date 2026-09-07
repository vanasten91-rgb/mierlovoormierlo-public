<?php

define( 'ABSPATH', __DIR__ . '/' );
define( 'DAY_IN_SECONDS', 86400 );

define( 'HOUR_IN_SECONDS', 3600 );

final class WP_Error {
	public function __construct( private string $code, private string $message ) {}
	public function get_error_code(): string { return $this->code; }
	public function get_error_message(): string { return $this->message; }
}

function is_wp_error( $value ): bool { return $value instanceof WP_Error; }
function wp_strip_all_tags( string $value ): string { return strip_tags( $value ); }
function sanitize_key( string $value ): string { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $value ) ) ?? ''; }
function sanitize_text_field( string $value ): string { return trim( strip_tags( $value ) ); }
function absint( $value ): int { return abs( (int) $value ); }

function mvm_mp_require( bool $condition, string $message ): void {
	if ( $condition ) return;
	fwrite( STDERR, "FAIL: {$message}\n" );
	exit( 1 );
}

require_once __DIR__ . '/../plugins/mvm-platform/src/marketplace/class-marketplace.php';

$normal = MvM_Marketplace::validate_listing_text( 'Houten tafel', 'Gebruikte eettafel in goede staat.' );
mvm_mp_require( true === $normal, 'normal household item should be accepted' );

foreach ( array(
	array( 'Pistool te koop', 'met doos' ),
	array( 'Vuurwerk pakket', 'over van oud en nieuw' ),
	array( 'Vape', 'met nicotine' ),
	array( 'Fles whisky', 'ongeopend' ),
	array( 'Medicijn', 'receptplichtig middel' ),
) as $case ) {
	$result = MvM_Marketplace::validate_listing_text( $case[0], $case[1] );
	mvm_mp_require( is_wp_error( $result ), 'prohibited item should be rejected: ' . $case[0] );
	mvm_mp_require( 'mvm_marketplace_prohibited_item' === $result->get_error_code(), 'wrong prohibited-item error code' );
}

list( $type, $cents ) = MvM_Marketplace::normalize_price( 'fixed', 12345 );
mvm_mp_require( 'fixed' === $type && 12345 === $cents, 'fixed price normalization failed' );
list( $type, $cents ) = MvM_Marketplace::normalize_price( 'free', 99999 );
mvm_mp_require( 'free' === $type && 0 === $cents, 'free listing must have zero price' );
list( $type, $cents ) = MvM_Marketplace::normalize_price( 'swap', 99999 );
mvm_mp_require( 'swap' === $type && 0 === $cents, 'swap listing must have zero price' );
list( $type, $cents ) = MvM_Marketplace::normalize_price( 'fixed', 999999999 );
mvm_mp_require( 10000000 === $cents, 'price upper bound failed' );

mvm_mp_require( in_array( 'reserved', MvM_Marketplace::public_statuses(), true ), 'reserved should remain publicly visible' );
mvm_mp_require( ! in_array( 'moderated', MvM_Marketplace::public_statuses(), true ), 'moderated listing must never be public' );
mvm_mp_require( ! in_array( 'expired', MvM_Marketplace::public_statuses(), true ), 'expired listing must never be public' );
mvm_mp_require( 8 === MvM_Marketplace::MAX_GALLERY, 'gallery bound changed unexpectedly' );
mvm_mp_require( 30 * DAY_IN_SECONDS === MvM_Marketplace::DEFAULT_LIFETIME, 'listing lifetime changed unexpectedly' );

$location = MvM_Marketplace::sanitize_location( str_repeat( 'a', 120 ) );
mvm_mp_require( strlen( $location ) <= 80, 'location must be capped at 80 characters' );

echo "MvM marketplace validation runtime: OK\n";
