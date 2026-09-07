<?php
// Consolidated from production Code Snippet #491.
defined( 'ABSPATH' ) || exit;

add_filter( 'do_shortcode_tag', static function ( $output, $tag ) {
    if ( 'mvm_mijn_mierlo_dashboard_v1' !== $tag || ! is_user_logged_in() || ! is_string( $output ) || false === strpos( $output, 'mvm-my-dashboard-v1__grid' ) ) {
        return $output;
    }

    $user = wp_get_current_user();
    $profile_base = '';
    if ( class_exists( 'PeepSoUser' ) ) {
        $peepso_user = PeepSoUser::get_instance( $user->ID );
        if ( is_object( $peepso_user ) && method_exists( $peepso_user, 'get_profileurl' ) ) {
            $profile_base = trailingslashit( (string) $peepso_user->get_profileurl() );
        }
    }
    if ( '' === $profile_base ) {
        $profile_base = trailingslashit( home_url( '/profile/' . rawurlencode( $user->user_nicename ) . '/' ) );
    }

    $cards = [
        [ "Mijn Foto's", 'Bekijk je eigen foto’s en albums binnen de PeepSo-community.', 'photos/', "Open Mijn Foto's" ],
        [ 'Mijn Volgers', 'Bekijk welke communityleden jou volgen.', 'followers/', 'Bekijk Mijn Volgers' ],
        [ 'Mijn Vrienden', 'Bekijk en beheer je vrienden binnen de community.', 'friends/', 'Bekijk Mijn Vrienden' ],
    ];

    $markup = '';
    foreach ( $cards as $card ) {
        $markup .= '<article class="mvm-public-v1__card"><h3>' . esc_html( $card[0] ) . '</h3><p>' . esc_html( $card[1] ) . '</p><p><a class="mvm-public-v1__button" href="' . esc_url( $profile_base . $card[2] ) . '">' . esc_html( $card[3] ) . '</a></p></article>';
    }

    $needle = '</div></section>';
    $pos = strrpos( $output, $needle );
    if ( false === $pos ) { return $output; }
    return substr_replace( $output, $markup . $needle, $pos, strlen( $needle ) );
}, 25, 2 );