<?php
// Consolidated from production Code Snippet #477.
defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'mvm_my_recent_items_v1' ) ) {
    function mvm_my_recent_items_v1( $user_id ) {
        $items = get_user_meta( absint( $user_id ), 'mvm_my_recent_items_v1', true );
        if ( ! is_array( $items ) ) { return []; }
        $clean = [];
        foreach ( $items as $item ) {
            if ( ! is_array( $item ) || empty( $item['id'] ) ) { continue; }
            $id = absint( $item['id'] );
            if ( ! $id ) { continue; }
            $clean[] = [ 'id' => $id, 'viewed' => isset( $item['viewed'] ) ? absint( $item['viewed'] ) : 0 ];
        }
        return array_slice( $clean, 0, 20 );
    }
}

add_action( 'template_redirect', static function () {
    if ( ! is_user_logged_in() || is_admin() || wp_doing_ajax() || wp_doing_cron() || defined( 'REST_REQUEST' ) && REST_REQUEST ) { return; }
    if ( ! is_singular() ) { return; }
    $id = get_queried_object_id();
    if ( ! $id || 10367 === $id || 'publish' !== get_post_status( $id ) ) { return; }
    $allowed = [ 'post', 'event_listing', 'mvm_vereniging', 'mvm_locatie', 'mvm_gebouw', 'mvm_persoon', 'mvm_gebeurtenis', 'mvm_encyclopedie', 'mvm_bedrijf' ];
    $type = get_post_type( $id );
    if ( ! in_array( $type, $allowed, true ) ) { return; }
    $user_id = get_current_user_id();
    $items = mvm_my_recent_items_v1( $user_id );
    $items = array_values( array_filter( $items, static fn( $item ) => absint( $item['id'] ?? 0 ) !== $id ) );
    array_unshift( $items, [ 'id' => $id, 'viewed' => time() ] );
    update_user_meta( $user_id, 'mvm_my_recent_items_v1', array_slice( $items, 0, 20 ) );
}, 30 );

add_action( 'admin_post_mvm_my_clear_recent_v1', static function () {
    if ( ! is_user_logged_in() ) { auth_redirect(); }
    check_admin_referer( 'mvm_my_clear_recent_v1' );
    delete_user_meta( get_current_user_id(), 'mvm_my_recent_items_v1' );
    wp_safe_redirect( add_query_arg( 'mvm_recent_cleared', '1', home_url( '/mijn-mierlo/' ) ) );
    exit;
} );

add_shortcode( 'mvm_mijn_mierlo_recent_v1', static function () {
    if ( ! is_user_logged_in() ) { return ''; }
    $items = mvm_my_recent_items_v1( get_current_user_id() );
    $labels = [
        'post' => 'Nieuws', 'event_listing' => 'Evenement', 'mvm_vereniging' => 'Vereniging',
        'mvm_locatie' => 'Locatie', 'mvm_gebouw' => 'Gebouw', 'mvm_persoon' => 'Persoon',
        'mvm_gebeurtenis' => 'Gebeurtenis', 'mvm_encyclopedie' => 'Encyclopedie', 'mvm_bedrijf' => 'Bedrijf',
    ];
    $valid = [];
    foreach ( $items as $item ) {
        $id = absint( $item['id'] ?? 0 );
        if ( ! $id || 'publish' !== get_post_status( $id ) ) { continue; }
        $type = get_post_type( $id );
        if ( ! isset( $labels[ $type ] ) ) { continue; }
        $valid[] = [ 'id' => $id, 'viewed' => absint( $item['viewed'] ?? 0 ), 'type' => $type ];
        if ( count( $valid ) >= 6 ) { break; }
    }
    ob_start();
    echo '<section class="mvm-public-v1 mvm-public-v1__box mvm-my-recent-v1" aria-labelledby="mvm-my-recent-title"><p class="mvm-public-v1__eyebrow">Snel terug</p><h2 id="mvm-my-recent-title">Recent bekeken</h2>';
    if ( empty( $valid ) ) {
        echo '<p class="mvm-public-v1__intro">Hier verschijnen nieuwsberichten, evenementen en encyclopediepagina’s die je onlangs hebt bekeken.</p></section>';
        return ob_get_clean();
    }
    echo '<div class="mvm-my-recent-v1__grid">';
    foreach ( $valid as $item ) {
        $id = $item['id'];
        $label = $labels[ $item['type'] ];
        echo '<article class="mvm-my-recent-v1__item"><span class="mvm-my-recent-v1__type">' . esc_html( $label ) . '</span><h3><a href="' . esc_url( get_permalink( $id ) ) . '">' . esc_html( get_the_title( $id ) ) . '</a></h3>';
        if ( $item['viewed'] ) { echo '<p class="mvm-public-v1__meta">Bekeken ' . esc_html( wp_date( 'j F · H:i', $item['viewed'], wp_timezone() ) ) . '</p>'; }
        echo '</article>';
    }
    echo '</div><form class="mvm-my-recent-v1__clear" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="mvm_my_clear_recent_v1">';
    wp_nonce_field( 'mvm_my_clear_recent_v1' );
    echo '<button type="submit" class="mvm-public-v1__button">Geschiedenis wissen</button></form></section>';
    echo '<style id="mvm-my-recent-v1-css">.mvm-my-recent-v1__grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin-top:16px}.mvm-my-recent-v1__item{padding:14px;border:1px solid var(--mvm-border,#c5d3df);border-radius:10px}.mvm-my-recent-v1__item h3{margin:6px 0}.mvm-my-recent-v1__type{font-size:.78rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:#1966AE}.mvm-my-recent-v1__clear{margin-top:14px}@media(max-width:820px){.mvm-my-recent-v1__grid{grid-template-columns:1fr}}</style>';
    return ob_get_clean();
} );