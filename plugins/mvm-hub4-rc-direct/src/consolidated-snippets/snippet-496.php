<?php
// Consolidated from production Code Snippet #496.
defined( 'ABSPATH' ) || exit;

add_filter( 'do_shortcode_tag', static function ( $output, $tag ) {
    if ( 'mvm_mijn_mierlo_dashboard_v1' !== $tag || ! is_user_logged_in() || ! is_string( $output ) ) {
        return $output;
    }
    if ( ! class_exists( 'PeepSoUser' ) ) {
        return $output;
    }

    $user = wp_get_current_user();
    $peepso_user = PeepSoUser::get_instance( $user->ID );
    if ( ! is_object( $peepso_user ) || ! method_exists( $peepso_user, 'get_avatar' ) ) {
        return $output;
    }

    $avatar_url = (string) $peepso_user->get_avatar();
    if ( '' === $avatar_url ) {
        return $output;
    }

    $name = trim( (string) $user->display_name );
    if ( '' === $name ) { $name = 'Mierlonaar'; }
    $avatar = '<img class="mvm-my-dashboard-v1__avatar mvm-my-dashboard-v1__avatar--peepso" src="' . esc_url( $avatar_url ) . '" width="72" height="72" alt="' . esc_attr( $name . ' profielfoto' ) . '" decoding="async">';

    return preg_replace( '/(<div class="mvm-my-dashboard-v1__welcome">)\s*<img\b[^>]*>/i', '$1' . $avatar, $output, 1 );
}, 20, 2 );