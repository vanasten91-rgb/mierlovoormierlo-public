<?php
// Consolidated from production Code Snippet #472.
defined( 'ABSPATH' ) || exit;

add_shortcode( 'mvm_mijn_mierlo_notifications_v1', static function () {
    if ( ! is_user_logged_in() ) { return ''; }
    global $wpdb;
    $table = $wpdb->prefix . 'peepso_notifications';
    if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) { return ''; }
    $user_id = get_current_user_id();
    $rows = $wpdb->get_results( $wpdb->prepare( "SELECT n.not_id,n.not_from_user_id,n.not_timestamp,n.not_type,n.not_message,n.not_read,n.not_url,u.display_name AS from_name FROM {$table} n LEFT JOIN {$wpdb->users} u ON u.ID=n.not_from_user_id WHERE n.not_user_id=%d ORDER BY n.not_timestamp DESC LIMIT 5", $user_id ), ARRAY_A );
    $unread = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE not_user_id=%d AND not_read=0", $user_id ) );
    $labels = [
        'user_comment' => 'reageerde op je bericht',
        'like_post' => 'vond je bericht leuk',
        'friends_requests' => 'accepteerde je vriendschapsverzoek',
        'future_to_publish' => 'Je geplande bericht is gepubliceerd',
        'wall_post' => 'plaatste iets op je profiel',
        'tag' => 'heeft je genoemd',
        'tag_comment' => 'heeft je genoemd in een reactie',
        'stream_reply_comment' => 'reageerde op een gesprek waar je aan deelnam',
    ];
    ob_start();
    echo '<section class="mvm-public-v1 mvm-public-v1__box mvm-my-notifications-v1" aria-labelledby="mvm-my-notifications-title">';
    echo '<div class="mvm-my-notifications-v1__head"><div><p class="mvm-public-v1__eyebrow">Community</p><h2 id="mvm-my-notifications-title">Mijn meldingen</h2></div>';
    if ( $unread > 0 ) { echo '<span class="mvm-my-notifications-v1__badge">' . esc_html( $unread ) . ' ongelezen</span>'; }
    echo '</div>';
    if ( empty( $rows ) ) { echo '<p class="mvm-public-v1__intro">Je hebt nog geen communitymeldingen.</p></section>'; return ob_get_clean(); }
    echo '<ul class="mvm-my-notifications-v1__list">';
    foreach ( $rows as $row ) {
        $type = (string) $row['not_type'];
        $from = trim( (string) $row['from_name'] );
        $text = isset( $labels[ $type ] ) ? $labels[ $type ] : trim( wp_strip_all_tags( (string) $row['not_message'] ) );
        if ( '' === $text ) { $text = 'Nieuwe melding'; }
        $message = ( $from && 'future_to_publish' !== $type ) ? $from . ' ' . $text : $text;
        $time = '';
        if ( ! empty( $row['not_timestamp'] ) ) {
            $local = DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', (string) $row['not_timestamp'], wp_timezone() );
            if ( $local instanceof DateTimeInterface ) { $time = wp_date( 'j F Y · H:i', $local->getTimestamp(), wp_timezone() ); }
        }
        $url = trim( (string) $row['not_url'] );
        if ( false !== strpos( $url, '/activity/status//' ) ) { $url = ''; }
        $safe_url = $url ? wp_validate_redirect( $url, '' ) : '';
        $class = empty( $row['not_read'] ) ? ' is-unread' : '';
        echo '<li class="mvm-my-notifications-v1__item' . esc_attr( $class ) . '"><div><strong>' . esc_html( $message ) . '</strong>';
        if ( $time ) { echo '<span>' . esc_html( $time ) . '</span>'; }
        echo '</div>';
        if ( $safe_url ) { echo '<a href="' . esc_url( $safe_url ) . '">Bekijken</a>'; }
        echo '</li>';
    }
    echo '</ul><p><a href="' . esc_url( home_url( '/activity/' ) ) . '">Open de communitytijdlijn →</a></p></section>';
    echo '<style id="mvm-my-notifications-v1-css">.mvm-my-notifications-v1__head{display:flex;align-items:flex-start;justify-content:space-between;gap:16px}.mvm-my-notifications-v1__badge{display:inline-flex;padding:5px 9px;border-radius:999px;background:#1966AE;color:#fff;font-size:.85rem;font-weight:700}.mvm-my-notifications-v1__list{list-style:none;margin:16px 0;padding:0;display:grid;gap:8px}.mvm-my-notifications-v1__item{display:flex;justify-content:space-between;align-items:center;gap:14px;padding:12px 14px;border:1px solid var(--mvm-border,#c5d3df);border-radius:10px}.mvm-my-notifications-v1__item.is-unread{border-left:4px solid #1966AE}.mvm-my-notifications-v1__item strong,.mvm-my-notifications-v1__item span{display:block}.mvm-my-notifications-v1__item span{margin-top:2px;color:var(--mvm-muted,#46596b);font-size:.88rem}@media(max-width:560px){.mvm-my-notifications-v1__head,.mvm-my-notifications-v1__item{align-items:flex-start;flex-direction:column}}</style>';
    return ob_get_clean();
} );