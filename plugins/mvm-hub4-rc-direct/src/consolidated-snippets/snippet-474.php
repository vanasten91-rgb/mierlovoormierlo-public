<?php
// Consolidated from production Code Snippet #474.
defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'mvm_my_saved_event_ids_v1' ) ) {
    function mvm_my_saved_event_ids_v1( $user_id ) {
        $ids = get_user_meta( absint( $user_id ), 'mvm_my_saved_events_v1', true );
        if ( ! is_array( $ids ) ) { $ids = []; }
        return array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );
    }
}

add_action( 'admin_post_mvm_my_toggle_event_v1', static function () {
    if ( ! is_user_logged_in() ) { auth_redirect(); }
    check_admin_referer( 'mvm_my_toggle_event_v1' );
    $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
    if ( ! $post_id || 'event_listing' !== get_post_type( $post_id ) || 'publish' !== get_post_status( $post_id ) ) {
        wp_die( esc_html__( 'Dit evenement kan niet worden opgeslagen.', 'mvm' ), '', [ 'response' => 400 ] );
    }
    $user_id = get_current_user_id();
    $ids = mvm_my_saved_event_ids_v1( $user_id );
    if ( in_array( $post_id, $ids, true ) ) {
        $ids = array_values( array_diff( $ids, [ $post_id ] ) );
        $saved = false;
    } else {
        array_unshift( $ids, $post_id );
        $ids = array_slice( array_values( array_unique( $ids ) ), 0, 100 );
        $saved = true;
    }
    update_user_meta( $user_id, 'mvm_my_saved_events_v1', $ids );
    $fallback = get_permalink( $post_id );
    $redirect = isset( $_POST['redirect_to'] ) ? wp_validate_redirect( wp_unslash( $_POST['redirect_to'] ), $fallback ) : $fallback;
    wp_safe_redirect( add_query_arg( 'mvm_event_saved', $saved ? '1' : '0', $redirect ) );
    exit;
} );

add_filter( 'the_content', static function ( $content ) {
    if ( ! is_singular( 'event_listing' ) || ! is_main_query() || ! in_the_loop() || ! is_user_logged_in() ) { return $content; }
    $post_id = get_the_ID();
    $saved = in_array( $post_id, mvm_my_saved_event_ids_v1( get_current_user_id() ), true );
    ob_start();
    echo '<aside class="mvm-my-event-save-v1 mvm-public-v1 mvm-public-v1__box" aria-label="Evenement bewaren in Mijn Mierlo"><h2>Mijn agenda</h2>';
    echo '<p>' . esc_html( $saved ? 'Dit evenement staat in je persoonlijke Mijn Mierlo-agenda.' : 'Bewaar dit evenement in Mijn Mierlo om het later snel terug te vinden.' ) . '</p>';
    echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="mvm_my_toggle_event_v1"><input type="hidden" name="post_id" value="' . esc_attr( $post_id ) . '"><input type="hidden" name="redirect_to" value="' . esc_url( get_permalink( $post_id ) ) . '">';
    wp_nonce_field( 'mvm_my_toggle_event_v1' );
    echo '<button class="mvm-public-v1__button" type="submit">' . esc_html( $saved ? 'Verwijder uit Mijn agenda' : 'Bewaar in Mijn agenda' ) . '</button></form></aside>';
    return $content . ob_get_clean();
}, 44 );

add_shortcode( 'mvm_mijn_mierlo_saved_events_v1', static function () {
    if ( ! is_user_logged_in() ) { return ''; }
    $ids = mvm_my_saved_event_ids_v1( get_current_user_id() );
    $valid = [];
    $now = current_time( 'mysql' );
    foreach ( $ids as $id ) {
        if ( 'event_listing' !== get_post_type( $id ) || 'publish' !== get_post_status( $id ) ) { continue; }
        $start = (string) get_post_meta( $id, '_event_start_date', true );
        if ( $start && $start < $now ) { continue; }
        $valid[] = $id;
    }
    ob_start();
    echo '<section class="mvm-public-v1 mvm-public-v1__box mvm-my-saved-events-v1" aria-labelledby="mvm-my-saved-events-title"><p class="mvm-public-v1__eyebrow">Persoonlijk</p><h2 id="mvm-my-saved-events-title">Mijn agenda</h2>';
    if ( empty( $valid ) ) {
        echo '<p class="mvm-public-v1__intro">Je hebt nog geen komende evenementen opgeslagen. Open een evenement en kies <strong>Bewaar in Mijn agenda</strong>.</p><p><a href="' . esc_url( home_url( '/evenementen/' ) ) . '">Bekijk de volledige agenda →</a></p></section>';
        return ob_get_clean();
    }
    $query = new WP_Query( [
        'post_type' => 'event_listing', 'post_status' => 'publish', 'post__in' => $valid, 'posts_per_page' => 6,
        'meta_key' => '_event_start_date', 'orderby' => 'meta_value', 'order' => 'ASC', 'no_found_rows' => true,
    ] );
    echo '<p class="mvm-public-v1__intro">Evenementen die je zelf hebt bewaard.</p><div class="mvm-public-v1__grid">';
    while ( $query->have_posts() ) {
        $query->the_post(); $id = get_the_ID();
        $start = (string) get_post_meta( $id, '_event_start_date', true );
        $location = trim( (string) get_post_meta( $id, '_event_location', true ) );
        $date_label = '';
        $date = $start ? DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $start, wp_timezone() ) : false;
        if ( $date instanceof DateTimeInterface ) {
            $time = wp_date( 'H:i', $date->getTimestamp(), wp_timezone() );
            $date_label = '00:00' === $time ? wp_date( 'j F Y', $date->getTimestamp(), wp_timezone() ) : wp_date( 'j F Y · H:i', $date->getTimestamp(), wp_timezone() );
        }
        echo '<article class="mvm-public-v1__card mvm-public-v1__news-card">';
        if ( has_post_thumbnail( $id ) ) { echo '<a class="mvm-public-v1__thumb" href="' . esc_url( get_permalink( $id ) ) . '" tabindex="-1" aria-hidden="true">' . get_the_post_thumbnail( $id, 'medium', [ 'loading' => 'lazy', 'decoding' => 'async' ] ) . '</a>'; }
        echo '<div class="mvm-public-v1__news-copy"><h3><a href="' . esc_url( get_permalink( $id ) ) . '">' . esc_html( get_the_title( $id ) ) . '</a></h3>';
        if ( $date_label ) { echo '<p class="mvm-public-v1__meta">' . esc_html( $date_label ) . '</p>'; }
        if ( $location ) { echo '<p>' . esc_html( $location ) . '</p>'; }
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="mvm_my_toggle_event_v1"><input type="hidden" name="post_id" value="' . esc_attr( $id ) . '"><input type="hidden" name="redirect_to" value="' . esc_url( home_url( '/mijn-mierlo/' ) ) . '">';
        wp_nonce_field( 'mvm_my_toggle_event_v1' );
        echo '<button class="mvm-public-v1__button" type="submit">Verwijderen</button></form></div></article>';
    }
    wp_reset_postdata();
    echo '</div><p><a href="' . esc_url( home_url( '/evenementen/' ) ) . '">Bekijk de volledige agenda →</a></p></section>';
    return ob_get_clean();
} );