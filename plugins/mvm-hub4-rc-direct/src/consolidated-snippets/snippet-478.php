<?php
// Consolidated from production Code Snippet #478.
defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'mvm_my_ency_favorite_ids_v1' ) ) {
    function mvm_my_ency_favorite_ids_v1( $user_id ) {
        $ids = get_user_meta( absint( $user_id ), 'mvm_my_ency_favorites_v1', true );
        if ( ! is_array( $ids ) ) { $ids = []; }
        return array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );
    }
}

if ( ! function_exists( 'mvm_my_ency_allowed_types_v1' ) ) {
    function mvm_my_ency_allowed_types_v1() {
        return [ 'mvm_encyclopedie', 'mvm_persoon', 'mvm_locatie', 'mvm_gebouw', 'mvm_gebeurtenis', 'mvm_bedrijf' ];
    }
}

add_action( 'admin_post_mvm_my_toggle_ency_v1', static function () {
    if ( ! is_user_logged_in() ) { auth_redirect(); }
    check_admin_referer( 'mvm_my_toggle_ency_v1' );
    $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
    if ( ! $post_id || ! in_array( get_post_type( $post_id ), mvm_my_ency_allowed_types_v1(), true ) || 'publish' !== get_post_status( $post_id ) ) {
        wp_die( esc_html__( 'Dit encyclopedie-item kan niet worden opgeslagen.', 'mvm' ), '', [ 'response' => 400 ] );
    }
    $user_id = get_current_user_id();
    $ids = mvm_my_ency_favorite_ids_v1( $user_id );
    if ( in_array( $post_id, $ids, true ) ) {
        $ids = array_values( array_diff( $ids, [ $post_id ] ) );
        $saved = false;
    } else {
        array_unshift( $ids, $post_id );
        $ids = array_slice( array_values( array_unique( $ids ) ), 0, 100 );
        $saved = true;
    }
    update_user_meta( $user_id, 'mvm_my_ency_favorites_v1', $ids );
    $fallback = get_permalink( $post_id );
    $redirect = isset( $_POST['redirect_to'] ) ? wp_validate_redirect( wp_unslash( $_POST['redirect_to'] ), $fallback ) : $fallback;
    wp_safe_redirect( add_query_arg( 'mvm_ency_saved', $saved ? '1' : '0', $redirect ) );
    exit;
} );

add_filter( 'the_content', static function ( $content ) {
    if ( ! is_user_logged_in() || ! is_main_query() || ! in_the_loop() || ! is_singular( mvm_my_ency_allowed_types_v1() ) ) { return $content; }
    $post_id = get_the_ID();
    $saved = in_array( $post_id, mvm_my_ency_favorite_ids_v1( get_current_user_id() ), true );
    ob_start();
    echo '<aside class="mvm-my-ency-save-v1 mvm-public-v1 mvm-public-v1__box" aria-label="Bewaren in Mijn Mierlo"><p class="mvm-public-v1__eyebrow">Mijn Mierlo</p><h2>Bewaar dit encyclopedie-item</h2><p>' . esc_html( $saved ? 'Dit item staat in je persoonlijke encyclopediefavorieten.' : 'Bewaar dit item om het later snel vanuit Mijn Mierlo terug te vinden.' ) . '</p>';
    echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="mvm_my_toggle_ency_v1"><input type="hidden" name="post_id" value="' . esc_attr( $post_id ) . '"><input type="hidden" name="redirect_to" value="' . esc_url( get_permalink( $post_id ) ) . '">';
    wp_nonce_field( 'mvm_my_toggle_ency_v1' );
    echo '<button class="mvm-public-v1__button" type="submit">' . esc_html( $saved ? 'Verwijder uit favorieten' : 'Bewaar in Mijn Mierlo' ) . '</button></form></aside>';
    return $content . ob_get_clean();
}, 46 );

add_shortcode( 'mvm_mijn_mierlo_ency_favorites_v1', static function () {
    if ( ! is_user_logged_in() ) { return ''; }
    $labels = [ 'mvm_encyclopedie' => 'Encyclopedie', 'mvm_persoon' => 'Persoon', 'mvm_locatie' => 'Locatie', 'mvm_gebouw' => 'Gebouw', 'mvm_gebeurtenis' => 'Gebeurtenis', 'mvm_bedrijf' => 'Bedrijf' ];
    $ids = mvm_my_ency_favorite_ids_v1( get_current_user_id() );
    $valid = [];
    foreach ( $ids as $id ) {
        $type = get_post_type( $id );
        if ( isset( $labels[ $type ] ) && 'publish' === get_post_status( $id ) ) { $valid[] = $id; }
        if ( count( $valid ) >= 6 ) { break; }
    }
    ob_start();
    echo '<section class="mvm-public-v1 mvm-public-v1__box mvm-my-ency-favorites-v1" aria-labelledby="mvm-my-ency-title"><p class="mvm-public-v1__eyebrow">Mierlose Encyclopedie</p><h2 id="mvm-my-ency-title">Mijn encyclopediefavorieten</h2>';
    if ( empty( $valid ) ) {
        echo '<p class="mvm-public-v1__intro">Je hebt nog geen persoon, locatie, gebouw, gebeurtenis of bedrijf bewaard. Open een encyclopedie-item en kies <strong>Bewaar in Mijn Mierlo</strong>.</p><p><a href="' . esc_url( home_url( '/encyclopedie/' ) ) . '">Ontdek de Mierlose Encyclopedie →</a></p></section>';
        return ob_get_clean();
    }
    echo '<div class="mvm-my-ency-favorites-v1__grid">';
    foreach ( $valid as $id ) {
        $type = get_post_type( $id );
        echo '<article class="mvm-my-ency-favorites-v1__item"><span>' . esc_html( $labels[ $type ] ) . '</span><h3><a href="' . esc_url( get_permalink( $id ) ) . '">' . esc_html( get_the_title( $id ) ) . '</a></h3>';
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="mvm_my_toggle_ency_v1"><input type="hidden" name="post_id" value="' . esc_attr( $id ) . '"><input type="hidden" name="redirect_to" value="' . esc_url( home_url( '/mijn-mierlo/' ) ) . '">';
        wp_nonce_field( 'mvm_my_toggle_ency_v1' );
        echo '<button class="mvm-public-v1__button" type="submit">Verwijderen</button></form></article>';
    }
    echo '</div><p><a href="' . esc_url( home_url( '/encyclopedie/' ) ) . '">Ontdek meer in de Mierlose Encyclopedie →</a></p></section>';
    echo '<style id="mvm-my-ency-favorites-v1-css">.mvm-my-ency-favorites-v1__grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin-top:16px}.mvm-my-ency-favorites-v1__item{padding:14px;border:1px solid var(--mvm-border,#c5d3df);border-radius:10px}.mvm-my-ency-favorites-v1__item>span{font-size:.78rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:#1966AE}.mvm-my-ency-favorites-v1__item h3{margin:6px 0 12px}@media(max-width:820px){.mvm-my-ency-favorites-v1__grid{grid-template-columns:1fr}}</style>';
    return ob_get_clean();
} );