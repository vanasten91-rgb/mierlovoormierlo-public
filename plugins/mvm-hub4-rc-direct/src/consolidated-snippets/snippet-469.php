<?php
// Consolidated from production Code Snippet #469.
defined( 'ABSPATH' ) || exit;

add_shortcode( 'mvm_mijn_mierlo_agenda_v1', static function () {
    if ( ! is_user_logged_in() ) { return ''; }
    $now = current_time( 'mysql' );
    $events = new WP_Query( [
        'post_type' => 'event_listing',
        'post_status' => 'publish',
        'posts_per_page' => 3,
        'meta_key' => '_event_start_date',
        'orderby' => 'meta_value',
        'order' => 'ASC',
        'meta_query' => [ [ 'key' => '_event_start_date', 'value' => $now, 'compare' => '>=', 'type' => 'DATETIME' ] ],
        'no_found_rows' => true,
    ] );
    if ( ! $events->have_posts() ) { return ''; }
    ob_start();
    echo '<section class="mvm-public-v1 mvm-public-v1__box" aria-labelledby="mvm-my-agenda-title">';
    echo '<h2 id="mvm-my-agenda-title">Binnenkort in Mierlo</h2>';
    echo '<p class="mvm-public-v1__intro">De eerstvolgende activiteiten uit de agenda.</p><div class="mvm-public-v1__grid">';
    while ( $events->have_posts() ) {
        $events->the_post();
        $id = get_the_ID();
        $start = (string) get_post_meta( $id, '_event_start_date', true );
        $location = trim( (string) get_post_meta( $id, '_event_location', true ) );
        $date = $start ? DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $start, wp_timezone() ) : false;
        echo '<article class="mvm-public-v1__card mvm-public-v1__news-card">';
        if ( has_post_thumbnail( $id ) ) {
            echo '<a class="mvm-public-v1__thumb" href="' . esc_url( get_permalink( $id ) ) . '" tabindex="-1" aria-hidden="true">' . get_the_post_thumbnail( $id, 'medium', [ 'loading' => 'lazy', 'decoding' => 'async', 'alt' => '' ] ) . '</a>';
        }
        echo '<div class="mvm-public-v1__news-copy"><h3><a href="' . esc_url( get_permalink( $id ) ) . '">' . esc_html( get_the_title( $id ) ) . '</a></h3>';
        if ( $date instanceof DateTimeInterface ) {
            $time = wp_date( 'H:i', $date->getTimestamp(), wp_timezone() );
            $label = '00:00' === $time ? wp_date( 'j F Y', $date->getTimestamp(), wp_timezone() ) : wp_date( 'j F Y · H:i', $date->getTimestamp(), wp_timezone() );
            echo '<p class="mvm-public-v1__meta">' . esc_html( $label ) . '</p>';
        }
        if ( $location ) { echo '<p>' . esc_html( $location ) . '</p>'; }
        echo '<p><a class="mvm-public-v1__button" href="' . esc_url( get_permalink( $id ) ) . '">Bekijk evenement</a></p></div></article>';
    }
    wp_reset_postdata();
    echo '</div><p><a href="' . esc_url( home_url( '/evenementen/' ) ) . '">Bekijk de volledige agenda →</a></p></section>';
    return ob_get_clean();
} );