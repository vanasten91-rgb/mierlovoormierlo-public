<?php
// Consolidated from production Code Snippet #475.
defined( 'ABSPATH' ) || exit;

add_shortcode( 'mvm_mijn_mierlo_contributions_v1', static function () {
    if ( ! is_user_logged_in() ) { return ''; }
    global $wpdb;
    $activities = $wpdb->prefix . 'peepso_activities';
    if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $activities ) ) !== $activities ) { return ''; }
    $rows = $wpdb->get_results( $wpdb->prepare( "SELECT p.ID,p.post_type,p.post_content FROM {$activities} a JOIN {$wpdb->posts} p ON p.ID=a.act_external_id WHERE a.act_owner_id=%d AND p.post_status='publish' AND ((p.post_type='peepso-post' AND TRIM(p.post_content)<>'' AND p.post_content NOT LIKE '{%%' AND p.post_content NOT LIKE 'Nieuw op het Mierlo voor Mierlo Forum:%%') OR (p.post_type='peepso-comment' AND TRIM(p.post_content)<>'')) ORDER BY a.act_id DESC LIMIT 5", get_current_user_id() ), ARRAY_A );
    ob_start();
    echo '<section class="mvm-public-v1 mvm-public-v1__box mvm-my-contributions-v1" aria-labelledby="mvm-my-contributions-title"><p class="mvm-public-v1__eyebrow">Community</p><h2 id="mvm-my-contributions-title">Mijn recente bijdragen</h2>';
    if ( empty( $rows ) ) {
        echo '<p class="mvm-public-v1__intro">Je hebt nog geen recente eigen communityberichten of reacties.</p><p><a href="' . esc_url( home_url( '/activity/' ) ) . '">Open de communitytijdlijn →</a></p></section>';
        return ob_get_clean();
    }
    echo '<div class="mvm-my-contributions-v1__list">';
    foreach ( $rows as $row ) {
        $id = absint( $row['ID'] );
        $type = 'peepso-comment' === $row['post_type'] ? 'Reactie' : 'Bericht';
        $text = trim( wp_strip_all_tags( (string) $row['post_content'] ) );
        $text = wp_trim_words( $text, 28, '…' );
        $date = '';
        $datetime = get_post_datetime( $id );
        if ( $datetime ) { $date = wp_date( 'j F Y · H:i', $datetime->getTimestamp(), wp_timezone() ); }
        echo '<article class="mvm-my-contributions-v1__item"><div class="mvm-my-contributions-v1__meta"><strong>' . esc_html( $type ) . '</strong>';
        if ( $date ) { echo '<span>' . esc_html( $date ) . '</span>'; }
        echo '</div><p>' . esc_html( $text ) . '</p></article>';
    }
    echo '</div><p><a href="' . esc_url( home_url( '/activity/' ) ) . '">Bekijk je activiteit op de communitytijdlijn →</a></p></section>';
    echo '<style id="mvm-my-contributions-v1-css">.mvm-my-contributions-v1__list{display:grid;gap:10px;margin-top:16px}.mvm-my-contributions-v1__item{padding:14px;border:1px solid var(--mvm-border,#c5d3df);border-radius:10px}.mvm-my-contributions-v1__item p{margin:8px 0 0}.mvm-my-contributions-v1__meta{display:flex;justify-content:space-between;gap:12px}.mvm-my-contributions-v1__meta span{color:var(--mvm-muted,#46596b);font-size:.88rem}@media(max-width:560px){.mvm-my-contributions-v1__meta{flex-direction:column;gap:2px}}</style>';
    return ob_get_clean();
} );