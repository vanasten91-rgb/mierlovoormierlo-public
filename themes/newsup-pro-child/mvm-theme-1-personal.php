<?php
/**
 * MvM Theme 1.0 — privacyvriendelijke persoonlijke homepage-laag.
 *
 * Privédata wordt uitsluitend via een ingelogde AJAX-call geleverd en komt
 * niet server-side in de publieke homepagecache terecht.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function mvm_theme1_personal_assets() {
    if ( ! is_user_logged_in() || ( ! is_front_page() && ! is_home() ) ) {
        return;
    }

    $css = get_stylesheet_directory() . '/mvm-theme-1-personal.css';
    $js  = get_stylesheet_directory() . '/mvm-theme-1-personal.js';

    if ( file_exists( $css ) ) {
        wp_enqueue_style(
            'mvm-theme-1-personal',
            get_stylesheet_directory_uri() . '/mvm-theme-1-personal.css',
            array(),
            (string) filemtime( $css )
        );
    }

    if ( file_exists( $js ) ) {
        wp_enqueue_script(
            'mvm-theme-1-personal',
            get_stylesheet_directory_uri() . '/mvm-theme-1-personal.js',
            array(),
            (string) filemtime( $js ),
            true
        );
        wp_localize_script(
            'mvm-theme-1-personal',
            'MvMPersonalHomeV1',
            array(
                'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
                'nonce'         => wp_create_nonce( 'mvm_personal_home_v1' ),
                'bookmarkNonce' => wp_create_nonce( 'mvm_bookmark_v1' ),
            )
        );
    }
}
add_action( 'wp_enqueue_scripts', 'mvm_theme1_personal_assets', 1180 );

function mvm_theme1_render_personal_home() {
    if ( ! is_user_logged_in() || ( ! is_front_page() && ! is_home() ) ) {
        return;
    }

    echo '<section id="mvm-personal-home-v1" class="mvm-personal-home-v1" aria-labelledby="mvm-personal-home-title-v1" aria-busy="true">';
    echo '<div class="mvm-personal-home-v1__shell">';
    echo '<div class="mvm-personal-home-v1__head"><div><span>Persoonlijk</span><h2 id="mvm-personal-home-title-v1">Voor mij</h2><p>Jouw community, forum en evenementen in één overzicht.</p></div><button type="button" class="mvm-personal-home-v1__toggle" aria-expanded="false" aria-controls="mvm-personal-home-panel-v1" disabled><span class="mvm-personal-home-v1__toggle-label">Uitklappen</span><span class="mvm-personal-home-v1__toggle-icon" aria-hidden="true">⌄</span></button></div>';
    echo '<p class="mvm-personal-home-v1__loading" aria-live="polite">Jouw overzicht laden…</p>';
    echo '</div></section>';
}
add_action( 'mvm_theme1_home_sections', 'mvm_theme1_render_personal_home', 16 );

function mvm_theme1_personal_table_exists( $table ) {
    global $wpdb;
    return $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
}

add_action( 'wp_ajax_mvm_personal_home_v1', static function () {
    if ( ! is_user_logged_in() ) {
        wp_send_json_error( array( 'message' => 'Log in om je persoonlijke overzicht te bekijken.' ), 401 );
    }
    if ( ! check_ajax_referer( 'mvm_personal_home_v1', 'nonce', false ) ) {
        wp_send_json_error( array( 'message' => 'De beveiligingscontrole is verlopen. Vernieuw de pagina.' ), 403 );
    }

    nocache_headers();

    global $wpdb;
    $user_id = get_current_user_id();

    $counts = array(
        'saved'          => 0,
        'savedCommunity' => 0,
        'savedMvm'       => 0,
        'forumBookmarks' => 0,
        'forumFollows'   => 0,
        'events'         => 0,
    );

    $saved_table = $wpdb->prefix . 'peepso_saved_posts';
    if ( mvm_theme1_personal_table_exists( $saved_table ) ) {
        $counts['savedCommunity'] = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$saved_table} WHERE user_id = %d", $user_id ) );
    }

    $saved_items = array();
    if ( function_exists( 'mvm_bookmark_get_user_items_v1' ) ) {
        $mvm_saved = mvm_bookmark_get_user_items_v1( $user_id, 500 );
        $counts['savedMvm'] = count( $mvm_saved );

        foreach ( array_slice( $mvm_saved, 0, 12 ) as $item ) {
            $group = 'encyclopedia';
            if ( 'post' === $item['type'] ) {
                $group = 'news';
            } elseif ( 'event_listing' === $item['type'] ) {
                $group = 'events';
            }

            $saved_items[] = array(
                'id'           => (int) $item['id'],
                'title'        => $item['title'],
                'url'          => $item['url'],
                'type'         => $group,
                'typeLabel'    => function_exists( 'mvm_bookmark_type_label_v1' ) ? mvm_bookmark_type_label_v1( $item['type'] ) : 'Opgeslagen',
                'eventStatus'  => isset( $item['event_status'] ) ? $item['event_status'] : '',
            );
        }
    }

    $counts['saved'] = $counts['savedCommunity'] + $counts['savedMvm'];

    $bookmark_table = $wpdb->prefix . 'wpforo_bookmarks';
    if ( mvm_theme1_personal_table_exists( $bookmark_table ) ) {
        $counts['forumBookmarks'] = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$bookmark_table} WHERE userid = %d AND status = 1", $user_id ) );
    }

    $subscribe_table = $wpdb->prefix . 'wpforo_subscribes';
    if ( mvm_theme1_personal_table_exists( $subscribe_table ) ) {
        $counts['forumFollows'] = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$subscribe_table} WHERE userid = %d AND active = 1", $user_id ) );
    }

    $events = array();
    $rsvp_table = $wpdb->prefix . 'peepso_wpem_rsvp';
    if ( mvm_theme1_personal_table_exists( $rsvp_table ) ) {
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT event_id, status FROM {$rsvp_table} WHERE user_id = %d AND status IN ('yes','maybe') ORDER BY date_modified_gmt DESC LIMIT 30",
                $user_id
            )
        );

        $now = current_datetime();
        foreach ( (array) $rows as $row ) {
            $event_id = absint( $row->event_id );
            if ( ! $event_id || 'event_listing' !== get_post_type( $event_id ) || 'publish' !== get_post_status( $event_id ) ) {
                continue;
            }

            $start_raw = trim( (string) get_post_meta( $event_id, '_event_start_date', true ) );
            $end_raw   = trim( (string) get_post_meta( $event_id, '_event_end_date', true ) );
            $start     = $start_raw ? date_create_immutable( $start_raw, wp_timezone() ) : false;
            $end       = $end_raw ? date_create_immutable( $end_raw, wp_timezone() ) : false;

            if ( $end instanceof DateTimeImmutable && $end < $now ) {
                continue;
            }
            if ( ! $end && $start instanceof DateTimeImmutable && $start < $now ) {
                continue;
            }

            $events[] = array(
                'title'  => get_the_title( $event_id ),
                'url'    => get_permalink( $event_id ),
                'date'   => $start instanceof DateTimeImmutable ? wp_date( 'j F Y', $start->getTimestamp(), wp_timezone() ) : 'Datum volgt',
                'status' => 'yes' === $row->status ? 'Ik ga' : 'Geïnteresseerd',
                'sort'   => $start instanceof DateTimeImmutable ? $start->getTimestamp() : PHP_INT_MAX,
            );
        }

        usort(
            $events,
            static function ( $a, $b ) {
                return (int) $a['sort'] <=> (int) $b['sort'];
            }
        );
        $counts['events'] = count( $events );
        $events = array_slice( $events, 0, 3 );
        foreach ( $events as &$event ) {
            unset( $event['sort'] );
        }
        unset( $event );
    }

    $profile_url = function_exists( 'mvm_get_current_profile_url' )
        ? mvm_get_current_profile_url()
        : home_url( '/profile/' );

    wp_send_json_success(
        array(
            'counts'     => $counts,
            'events'     => $events,
            'savedItems' => $saved_items,
            'links'      => array(
                'profile'  => $profile_url,
                'activity' => home_url( '/activity/' ),
                'messages' => home_url( '/messages/' ),
                'groups'   => home_url( '/groepen/' ),
                'forum'    => home_url( '/forum/' ),
                'events'   => home_url( '/evenementen/' ),
            ),
        )
    );
} );
