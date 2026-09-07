<?php
// Consolidated from production Code Snippet #471.
defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'mvm_my_saved_ids_v1' ) ) {
    function mvm_my_saved_ids_v1( $user_id ) {
        static $cache = [];
        $user_id = absint( $user_id );
        if ( isset( $cache[ $user_id ] ) ) { return $cache[ $user_id ]; }
        $ids = get_user_meta( $user_id, 'mvm_my_saved_posts_v1', true );
        if ( ! is_array( $ids ) ) { $ids = []; }
        $ids = array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );
        global $wpdb;
        $legacy_keys = $wpdb->get_col( $wpdb->prepare( "SELECT meta_key FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key LIKE %s", $user_id, $wpdb->esc_like( 'mvm_bookmark_v1_' ) . '%' ) );
        $migrated = false;
        foreach ( (array) $legacy_keys as $legacy_key ) {
            if ( preg_match( '/^mvm_bookmark_v1_(\d+)$/', (string) $legacy_key, $match ) ) {
                $legacy_id = absint( $match[1] );
                if ( $legacy_id && 'post' === get_post_type( $legacy_id ) && 'publish' === get_post_status( $legacy_id ) && ! in_array( $legacy_id, $ids, true ) ) {
                    $ids[] = $legacy_id;
                    $migrated = true;
                }
            }
        }
        if ( $migrated ) { update_user_meta( $user_id, 'mvm_my_saved_posts_v1', array_slice( $ids, 0, 100 ) ); }
        $cache[ $user_id ] = array_slice( $ids, 0, 100 );
        return $cache[ $user_id ];
    }
}

add_action( 'admin_post_mvm_my_save_article_v1', static function () {
    if ( ! is_user_logged_in() ) { auth_redirect(); }
    check_admin_referer( 'mvm_my_save_article_v1' );
    $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
    if ( ! $post_id || 'post' !== get_post_type( $post_id ) || 'publish' !== get_post_status( $post_id ) ) {
        wp_die( esc_html__( 'Dit artikel kan niet worden opgeslagen.', 'mvm' ), '', [ 'response' => 400 ] );
    }
    $user_id = get_current_user_id();
    $ids = mvm_my_saved_ids_v1( $user_id );
    $exists = in_array( $post_id, $ids, true );
    if ( $exists ) {
        $ids = array_values( array_diff( $ids, [ $post_id ] ) );
        $saved = false;
    } else {
        array_unshift( $ids, $post_id );
        $ids = array_slice( array_values( array_unique( $ids ) ), 0, 100 );
        $saved = true;
    }
    update_user_meta( $user_id, 'mvm_my_saved_posts_v1', $ids );
    $fallback = get_permalink( $post_id );
    $redirect = isset( $_POST['redirect_to'] ) ? wp_validate_redirect( wp_unslash( $_POST['redirect_to'] ), $fallback ) : $fallback;
    wp_safe_redirect( add_query_arg( 'mvm_saved', $saved ? '1' : '0', $redirect ) );
    exit;
} );

add_filter( 'the_content', static function ( $content ) {
    if ( ! is_singular( 'post' ) || ! is_main_query() || ! in_the_loop() || ! is_user_logged_in() ) { return $content; }
    $post_id = get_the_ID();
    $saved = in_array( $post_id, mvm_my_saved_ids_v1( get_current_user_id() ), true );
    ob_start();
    echo '<aside class="mvm-public-v1 mvm-public-v1__box mvm-my-save-v1" aria-label="Bewaren in Mijn Mierlo"><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
    echo '<input type="hidden" name="action" value="mvm_my_save_article_v1"><input type="hidden" name="post_id" value="' . esc_attr( $post_id ) . '"><input type="hidden" name="redirect_to" value="' . esc_url( get_permalink( $post_id ) ) . '">';
    wp_nonce_field( 'mvm_my_save_article_v1' );
    echo '<button class="mvm-public-v1__button" type="submit">' . esc_html( $saved ? 'Verwijder uit Mijn Mierlo' : 'Bewaar in Mijn Mierlo' ) . '</button>';
    echo '</form><p>' . esc_html( $saved ? 'Dit artikel staat in je persoonlijke overzicht.' : 'Bewaar dit artikel om het later snel terug te vinden.' ) . '</p></aside>';
    return $content . ob_get_clean();
}, 40 );

add_shortcode( 'mvm_mijn_mierlo_saved_v1', static function () {
    if ( ! is_user_logged_in() ) { return ''; }
    $ids = mvm_my_saved_ids_v1( get_current_user_id() );
    $valid = [];
    foreach ( $ids as $id ) { if ( 'post' === get_post_type( $id ) && 'publish' === get_post_status( $id ) ) { $valid[] = $id; } }
    ob_start();
    echo '<section class="mvm-public-v1 mvm-public-v1__box" aria-labelledby="mvm-my-saved-title"><h2 id="mvm-my-saved-title">Opgeslagen artikelen</h2>';
    if ( ! $valid ) {
        echo '<p class="mvm-public-v1__intro">Je hebt nog geen artikelen opgeslagen. Gebruik bij een nieuwsbericht de knop <strong>Bewaar in Mijn Mierlo</strong>.</p></section>';
        return ob_get_clean();
    }
    $query = new WP_Query( [ 'post_type' => 'post', 'post_status' => 'publish', 'post__in' => array_slice( $valid, 0, 6 ), 'orderby' => 'post__in', 'posts_per_page' => 6, 'no_found_rows' => true ] );
    echo '<p class="mvm-public-v1__intro">Nieuws dat je zelf hebt bewaard om later terug te lezen.</p><div class="mvm-public-v1__grid">';
    while ( $query->have_posts() ) {
        $query->the_post(); $id = get_the_ID();
        echo '<article class="mvm-public-v1__card mvm-public-v1__news-card">';
        if ( has_post_thumbnail( $id ) ) { echo '<a class="mvm-public-v1__thumb" href="' . esc_url( get_permalink( $id ) ) . '" tabindex="-1" aria-hidden="true">' . get_the_post_thumbnail( $id, 'medium', [ 'loading' => 'lazy', 'decoding' => 'async' ] ) . '</a>'; }
        echo '<div class="mvm-public-v1__news-copy"><h3><a href="' . esc_url( get_permalink( $id ) ) . '">' . esc_html( get_the_title( $id ) ) . '</a></h3><p class="mvm-public-v1__meta">' . esc_html( get_the_date( 'j F Y', $id ) ) . '</p>';
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="mvm_my_save_article_v1"><input type="hidden" name="post_id" value="' . esc_attr( $id ) . '"><input type="hidden" name="redirect_to" value="' . esc_url( home_url( '/mijn-mierlo/' ) ) . '">';
        wp_nonce_field( 'mvm_my_save_article_v1' );
        echo '<button class="mvm-public-v1__button" type="submit">Verwijderen</button></form></div></article>';
    }
    wp_reset_postdata();
    echo '</div></section>';
    return ob_get_clean();
} );

add_action( 'wp_head', static function () {
    if ( ! is_user_logged_in() ) { return; }
    echo '<style id="mvm-my-save-v1-css">.mvm-my-save-v1{margin-top:24px;margin-bottom:24px}.mvm-my-save-v1 form{margin:0 0 8px}.mvm-my-save-v1 p{margin:0}</style>';
}, 30 );