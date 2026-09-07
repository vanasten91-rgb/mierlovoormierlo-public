<?php
// Consolidated from production Code Snippet #454.
defined( 'ABSPATH' ) || exit;

add_filter( 'wpseo_opengraph_image', function ( $url ) {
    if ( is_front_page() || is_home() ) {
        return 'https://www.mierlovoormierlo.nl/wp-content/uploads/2026/08/mvm-facebook-og-final-v3.jpg';
    }
    return $url;
} );

add_filter( 'wpseo_opengraph_image_width', function ( $width ) {
    return ( is_front_page() || is_home() ) ? '1200' : $width;
} );

add_filter( 'wpseo_opengraph_image_height', function ( $height ) {
    return ( is_front_page() || is_home() ) ? '630' : $height;
} );

add_filter( 'wpseo_opengraph_image_type', function ( $type ) {
    return ( is_front_page() || is_home() ) ? 'image/jpeg' : $type;
} );