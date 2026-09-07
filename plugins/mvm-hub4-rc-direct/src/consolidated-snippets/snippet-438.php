<?php
// Consolidated from production Code Snippet #438.
defined( 'ABSPATH' ) || exit;
add_action( 'wp_head', function () {
    if ( is_admin() ) { return; }
    echo '<meta property="fb:app_id" content="1043488711754956">';
}, 1 );