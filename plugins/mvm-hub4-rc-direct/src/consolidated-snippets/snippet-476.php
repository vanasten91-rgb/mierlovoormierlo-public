<?php
// Consolidated from production Code Snippet #476.
defined( 'ABSPATH' ) || exit;

add_filter( 'do_shortcode_tag', static function ( $output, $tag ) {
    if ( 'mvm_mijn_mierlo' !== $tag || ! is_string( $output ) || '' === $output ) { return $output; }
    $home = esc_url( home_url( '/' ) );
    $mine = esc_url( home_url( '/mijn-mierlo/' ) );
    $needle = 'name="redirect_to" value="' . $home . '"';
    $replace = 'name="redirect_to" value="' . $mine . '"';
    return str_replace( $needle, $replace, $output );
}, 20, 2 );