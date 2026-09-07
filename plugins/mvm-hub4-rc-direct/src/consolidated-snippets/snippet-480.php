<?php
// Consolidated from production Code Snippet #480.
defined( 'ABSPATH' ) || exit;

add_action( 'template_redirect', static function () {
    if ( ! is_user_logged_in() || is_admin() || wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ! is_page( 10367 ) ) { return; }
    $GLOBALS['mvm_my_previous_visit_v1'] = absint( get_user_meta( get_current_user_id(), 'mvm_my_last_visit_v1', true ) );
    $GLOBALS['mvm_my_current_visit_v1'] = time();
}, 40 );

add_action( 'shutdown', static function () {
    if ( empty( $GLOBALS['mvm_my_current_visit_v1'] ) || ! is_user_logged_in() ) { return; }
    update_user_meta( get_current_user_id(), 'mvm_my_last_visit_v1', absint( $GLOBALS['mvm_my_current_visit_v1'] ) );
}, 1 );

add_shortcode( 'mvm_mijn_mierlo_new_since_v1', static function () {
    if ( ! is_user_logged_in() ) { return ''; }
    $user_id = get_current_user_id();
    $last = isset( $GLOBALS['mvm_my_previous_visit_v1'] ) ? absint( $GLOBALS['mvm_my_previous_visit_v1'] ) : absint( get_user_meta( $user_id, 'mvm_my_last_visit_v1', true ) );
    global $wpdb;
    $unread = 0;
    $notification_table = $wpdb->prefix . 'peepso_notifications';
    if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $notification_table ) ) === $notification_table ) {
        $unread = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$notification_table} WHERE not_user_id=%d AND not_read=0", $user_id ) );
    }
    ob_start();
    echo '<section class="mvm-public-v1 mvm-public-v1__box mvm-my-new-v1" aria-labelledby="mvm-my-new-title"><p class="mvm-public-v1__eyebrow">Jouw update</p><h2 id="mvm-my-new-title">Nieuw sinds je laatste bezoek</h2>';
    if ( ! $last ) {
        echo '<p class="mvm-public-v1__intro">Vanaf nu onthoudt Mijn Mierlo wanneer je dit dashboard bezoekt. Bij je volgende bezoek zie je hier direct wat er intussen nieuw is.</p>';
        if ( $unread > 0 ) { echo '<p><strong>' . esc_html( (string) $unread ) . '</strong> ongelezen communitymelding' . ( 1 === $unread ? '' : 'en' ) . '.</p>'; }
        echo '</section>';
        return ob_get_clean();
    }
    $since_gmt = gmdate( 'Y-m-d H:i:s', $last );
    $new_posts = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='post' AND post_status='publish' AND post_date_gmt>%s", $since_gmt ) );
    $new_events = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='event_listing' AND post_status='publish' AND post_date_gmt>%s", $since_gmt ) );
    echo '<p class="mvm-public-v1__intro">Sinds ' . esc_html( wp_date( 'j F Y · H:i', $last, wp_timezone() ) ) . ':</p><div class="mvm-my-new-v1__stats">';
    echo '<a href="' . esc_url( home_url( '/' ) ) . '"><strong>' . esc_html( (string) $new_posts ) . '</strong><span>nieuwe nieuwsberichten</span></a>';
    echo '<a href="' . esc_url( home_url( '/evenementen/' ) ) . '"><strong>' . esc_html( (string) $new_events ) . '</strong><span>nieuwe evenementen</span></a>';
    echo '<a href="' . esc_url( home_url( '/activity/' ) ) . '"><strong>' . esc_html( (string) $unread ) . '</strong><span>ongelezen meldingen</span></a>';
    echo '</div></section>';
    echo '<style id="mvm-my-new-v1-css">.mvm-my-new-v1__stats{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin-top:14px}.mvm-my-new-v1__stats a{display:block;padding:14px;border:1px solid var(--mvm-border,#c5d3df);border-radius:10px;text-decoration:none}.mvm-my-new-v1__stats strong,.mvm-my-new-v1__stats span{display:block}.mvm-my-new-v1__stats strong{font-size:26px;line-height:1.1;color:#1966AE}.mvm-my-new-v1__stats span{margin-top:4px}@media(max-width:650px){.mvm-my-new-v1__stats{grid-template-columns:1fr}}</style>';
    return ob_get_clean();
} );