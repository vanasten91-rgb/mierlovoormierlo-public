<?php

declare(strict_types=1);

define( 'ABSPATH', __DIR__ );

function is_admin(): bool {
    return false;
}

function wp_doing_ajax(): bool {
    return false;
}

function is_feed(): bool {
    return false;
}

function is_trackback(): bool {
    return false;
}

function is_singular( $post_types = '' ): bool {
    return is_array( $post_types ) && in_array( 'event_listing', $post_types, true );
}

function home_url( $path = '' ): string {
    return 'https://www.mierlovoormierlo.nl/' . ltrim( (string) $path, '/' );
}

function esc_url_raw( $url ): string {
    return (string) $url;
}

function esc_attr( $value ): string {
    return htmlspecialchars( (string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
}

function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ): void {
    unset( $hook, $callback, $priority, $accepted_args );
}

require_once __DIR__ . '/../plugins/mvm-hub4-rc-direct/src/class-peepso-login-redirect.php';

ob_start();
MvM_Hub4_PeepSo_Login_Redirect::start_buffer();
echo '<form class="ps-js-form-login"><input type="hidden" name="redirect_to" value="https://www.mierlovoormierlo.nl/evenement/how-about-rita/"></form>';
echo '<form class="ps-js-form-alt-login-widget"><input name="redirect_to" type="hidden"></form>';
ob_end_flush();
$output = (string) ob_get_clean();

$canonical = 'https://www.mierlovoormierlo.nl/';
if ( 2 !== substr_count( $output, 'value="' . $canonical . '"' ) ) {
    fwrite( STDERR, "Runtime redirect rewrite did not update every PeepSo login field to the homepage.\n" );
    exit( 1 );
}

if ( str_contains( $output, 'value="https://www.mierlovoormierlo.nl/evenement/how-about-rita/"' ) ) {
    fwrite( STDERR, "Current event redirect survived the homepage rewrite.\n" );
    exit( 1 );
}

fwrite( STDOUT, "MvM PeepSo server-rendered homepage login redirect runtime: OK\n" );
