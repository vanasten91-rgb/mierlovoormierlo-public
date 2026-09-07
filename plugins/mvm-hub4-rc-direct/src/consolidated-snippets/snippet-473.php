<?php
// Consolidated from production Code Snippet #473.
defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'mvm_my_favorite_vereniging_ids_v1' ) ) {
    function mvm_my_favorite_vereniging_ids_v1( $user_id ) {
        $ids = get_user_meta( absint( $user_id ), 'mvm_my_favorite_verenigingen_v1', true );
        if ( ! is_array( $ids ) ) { $ids = []; }
        return array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );
    }
}

add_action( 'admin_post_mvm_my_toggle_vereniging_v1', static function () {
    if ( ! is_user_logged_in() ) { auth_redirect(); }
    check_admin_referer( 'mvm_my_toggle_vereniging_v1' );
    $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
    if ( ! $post_id || 'mvm_vereniging' !== get_post_type( $post_id ) || 'publish' !== get_post_status( $post_id ) ) {
        wp_die( esc_html__( 'Deze vereniging kan niet als favoriet worden opgeslagen.', 'mvm' ), '', [ 'response' => 400 ] );
    }
    $user_id = get_current_user_id();
    $ids = mvm_my_favorite_vereniging_ids_v1( $user_id );
    if ( in_array( $post_id, $ids, true ) ) {
        $ids = array_values( array_diff( $ids, [ $post_id ] ) );
        $favorite = false;
    } else {
        array_unshift( $ids, $post_id );
        $ids = array_slice( array_values( array_unique( $ids ) ), 0, 100 );
        $favorite = true;
    }
    update_user_meta( $user_id, 'mvm_my_favorite_verenigingen_v1', $ids );
    $fallback = get_permalink( $post_id );
    $redirect = isset( $_POST['redirect_to'] ) ? wp_validate_redirect( wp_unslash( $_POST['redirect_to'] ), $fallback ) : $fallback;
    wp_safe_redirect( add_query_arg( 'mvm_vereniging', $favorite ? '1' : '0', $redirect ) );
    exit;
} );

add_filter( 'the_content', static function ( $content ) {
    if ( ! is_singular( 'mvm_vereniging' ) || ! is_main_query() || ! in_the_loop() || ! is_user_logged_in() ) { return $content; }
    $post_id = get_the_ID();
    $favorite = in_array( $post_id, mvm_my_favorite_vereniging_ids_v1( get_current_user_id() ), true );
    ob_start();
    echo '<aside class="mvm-my-vereniging-follow-v1 mvm-public-v1 mvm-public-v1__box" aria-label="Vereniging bewaren in Mijn Mierlo">';
    echo '<h2>Bewaar deze vereniging</h2><p>' . esc_html( $favorite ? 'Deze vereniging staat als favoriet in je persoonlijke Mijn Mierlo-overzicht.' : 'Bewaar deze vereniging in Mijn Mierlo om haar later snel terug te vinden.' ) . '</p>';
    echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="mvm_my_toggle_vereniging_v1"><input type="hidden" name="post_id" value="' . esc_attr( $post_id ) . '"><input type="hidden" name="redirect_to" value="' . esc_url( get_permalink( $post_id ) ) . '">';
    wp_nonce_field( 'mvm_my_toggle_vereniging_v1' );
    echo '<button class="mvm-public-v1__button" type="submit">' . esc_html( $favorite ? 'Verwijder uit favorieten' : 'Bewaar als favoriet' ) . '</button></form></aside>';
    return $content . ob_get_clean();
}, 45 );

add_shortcode( 'mvm_mijn_mierlo_verenigingen_v1', static function () {
    if ( ! is_user_logged_in() ) { return ''; }
    $ids = mvm_my_favorite_vereniging_ids_v1( get_current_user_id() );
    $valid = [];
    foreach ( $ids as $id ) { if ( 'mvm_vereniging' === get_post_type( $id ) && 'publish' === get_post_status( $id ) ) { $valid[] = $id; } }
    $discover = empty( $valid );
    $args = [ 'post_type' => 'mvm_vereniging', 'post_status' => 'publish', 'posts_per_page' => 6, 'no_found_rows' => true ];
    if ( $discover ) { $args['orderby'] = 'title'; $args['order'] = 'ASC'; }
    else { $args['post__in'] = array_slice( $valid, 0, 6 ); $args['orderby'] = 'post__in'; }
    $query = new WP_Query( $args );
    ob_start();
    echo '<section class="mvm-public-v1 mvm-public-v1__box mvm-my-verenigingen-v1" aria-labelledby="mvm-my-verenigingen-title"><p class="mvm-public-v1__eyebrow">Lokale organisaties</p><h2 id="mvm-my-verenigingen-title">' . esc_html( $discover ? 'Ontdek verenigingen' : 'Mijn favoriete verenigingen' ) . '</h2>';
    echo '<p class="mvm-public-v1__intro">' . esc_html( $discover ? 'Je hebt nog geen favoriete vereniging. Kies hieronder een vereniging om in Mijn Mierlo te bewaren.' : 'Verenigingen die je zelf als favoriet hebt opgeslagen vanuit de Mierlose Encyclopedie.' ) . '</p>';
    if ( $query->have_posts() ) {
        echo '<div class="mvm-public-v1__grid">';
        while ( $query->have_posts() ) { $query->the_post(); $id = get_the_ID();
            $favorite = in_array( $id, $valid, true );
            echo '<article class="mvm-public-v1__card"><h3><a href="' . esc_url( get_permalink( $id ) ) . '">' . esc_html( get_the_title( $id ) ) . '</a></h3><p><a href="' . esc_url( get_permalink( $id ) ) . '">Bekijk vereniging →</a></p>';
            echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="mvm_my_toggle_vereniging_v1"><input type="hidden" name="post_id" value="' . esc_attr( $id ) . '"><input type="hidden" name="redirect_to" value="' . esc_url( home_url( '/mijn-mierlo/' ) ) . '">';
            wp_nonce_field( 'mvm_my_toggle_vereniging_v1' );
            echo '<button class="mvm-public-v1__button" type="submit">' . esc_html( $favorite ? 'Verwijderen' : 'Favoriet maken' ) . '</button></form></article>';
        }
        wp_reset_postdata();
        echo '</div>';
    }
    echo '<p><a href="' . esc_url( home_url( '/encyclopedie/' ) ) . '">Ontdek meer in de Mierlose Encyclopedie →</a></p></section>';
    return ob_get_clean();
} );