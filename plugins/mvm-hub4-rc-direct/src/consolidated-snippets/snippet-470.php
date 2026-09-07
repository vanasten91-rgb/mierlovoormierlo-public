<?php
// Consolidated from production Code Snippet #470.
defined( 'ABSPATH' ) || exit;

add_shortcode( 'mvm_mijn_mierlo_dashboard_v1', static function () {
    if ( ! is_user_logged_in() ) { return ''; }
    $user = wp_get_current_user();
    $name = trim( (string) $user->display_name );
    if ( '' === $name ) { $name = 'Mierlonaar'; }
    $topics = get_user_meta( $user->ID, '_mvm_hub4_topics', true );
    $topic_count = is_array( $topics ) ? count( array_filter( array_map( 'absint', $topics ) ) ) : 0;
    $saved_count = function_exists( 'mvm_my_saved_ids_v1' ) ? count( mvm_my_saved_ids_v1( $user->ID ) ) : 0;
    $favorite_count = function_exists( 'mvm_my_favorite_vereniging_ids_v1' ) ? count( mvm_my_favorite_vereniging_ids_v1( $user->ID ) ) : 0;
    $ency_count = function_exists( 'mvm_my_ency_favorite_ids_v1' ) ? count( mvm_my_ency_favorite_ids_v1( $user->ID ) ) : 0;
    $saved_event_count = 0;
    if ( function_exists( 'mvm_my_saved_event_ids_v1' ) ) {
        $now = current_time( 'mysql' );
        foreach ( mvm_my_saved_event_ids_v1( $user->ID ) as $event_id ) {
            if ( 'event_listing' !== get_post_type( $event_id ) || 'publish' !== get_post_status( $event_id ) ) { continue; }
            $start = (string) get_post_meta( $event_id, '_event_start_date', true );
            if ( ! $start || $start >= $now ) { $saved_event_count++; }
        }
    }
    $profile = min( 100, max( 0, absint( get_user_meta( $user->ID, 'peepso_profile_completeness', true ) ) ) );
    $unread = 0;
    global $wpdb;
    $notification_table = $wpdb->prefix . 'peepso_notifications';
    if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $notification_table ) ) === $notification_table ) {
        $unread = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$notification_table} WHERE not_user_id=%d AND not_read=0", $user->ID ) );
    }
    $links = [
        [ 'Mijn profiel', 'Bekijk en beheer je eigen profiel en accountgegevens.', '/profile/', 'Open profiel' ],
        [ 'Tijdlijn', 'Bekijk de nieuwste activiteiten en berichten uit de community.', '/activity/', 'Open tijdlijn' ],
        [ 'Berichten', 'Ga rechtstreeks naar je privéberichten binnen Mierlo voor Mierlo.', '/messages/', 'Open berichten' ],
        [ 'Groepen', 'Volg gesprekken en activiteiten binnen groepen waar je bij betrokken bent.', '/groepen/', 'Bekijk groepen' ],
        [ "Mijn Pagina's", 'Bekijk en beheer je PeepSo-pagina’s binnen Mierlo voor Mierlo.', '/paginas/', "Open Mijn Pagina's" ],
        [ 'Forum', 'Stel een vraag, help een dorpsgenoot of praat mee over wat er in Mierlo speelt.', '/forum/', 'Naar het forum' ],
        [ 'Evenementen', 'Bekijk wat er binnenkort in Mierlo te doen is.', '/evenementen/', 'Bekijk agenda' ],
        [ 'Encyclopedie', 'Ontdek personen, plekken, verenigingen en verhalen uit de geschiedenis van Mierlo.', '/encyclopedie/', 'Open encyclopedie' ],
        [ 'Leden', 'Vind andere inwoners en bekijk de openbare ledenlijst.', '/members/', 'Bekijk leden' ],
    ];
    ob_start();
    echo '<section class="mvm-public-v1 mvm-public-v1__box mvm-my-dashboard-v1" aria-labelledby="mvm-my-dashboard-title">';
    echo '<div class="mvm-my-dashboard-v1__welcome">' . get_avatar( $user->ID, 72, '', $name, [ 'class' => 'mvm-my-dashboard-v1__avatar' ] ) . '<div><p class="mvm-public-v1__eyebrow">Persoonlijk startpunt</p><h2 id="mvm-my-dashboard-title">Welkom, ' . esc_html( $name ) . '</h2><p class="mvm-public-v1__intro">Vanuit één plek naar je profiel, community, agenda, opgeslagen nieuws en je persoonlijke Mierlose favorieten.</p></div></div>';
    echo '<div class="mvm-my-dashboard-v1__stats" aria-label="Mijn Mierlo samenvatting">';
    echo '<div><strong>' . esc_html( (string) $topic_count ) . '</strong><span>onderwerpen gevolgd</span></div>';
    echo '<div><strong>' . esc_html( (string) $saved_count ) . '</strong><span>artikelen opgeslagen</span></div>';
    echo '<div><strong>' . esc_html( (string) $saved_event_count ) . '</strong><span>evenementen in Mijn agenda</span></div>';
    echo '<div><strong>' . esc_html( (string) $ency_count ) . '</strong><span>encyclopediefavorieten</span></div>';
    echo '<div><strong>' . esc_html( (string) $favorite_count ) . '</strong><span>favoriete verenigingen</span></div>';
    echo '<div><strong>' . esc_html( (string) $unread ) . '</strong><span>meldingen ongelezen</span></div>';
    echo '<div><strong>' . esc_html( (string) $profile ) . '%</strong><span>profiel ingevuld</span></div>';
    echo '</div>';
    echo '<div class="mvm-my-dashboard-v1__grid">';
    foreach ( $links as $link ) {
        echo '<article class="mvm-public-v1__card"><h3>' . esc_html( $link[0] ) . '</h3><p>' . esc_html( $link[1] ) . '</p><p><a class="mvm-public-v1__button" href="' . esc_url( home_url( $link[2] ) ) . '">' . esc_html( $link[3] ) . '</a></p></article>';
    }
    echo '</div></section>';
    echo '<style id="mvm-my-dashboard-v1-css">.mvm-my-dashboard-v1{margin-bottom:24px}.mvm-my-dashboard-v1__welcome{display:flex;align-items:center;gap:16px}.mvm-my-dashboard-v1__avatar{width:72px;height:72px;border-radius:50%;object-fit:cover}.mvm-my-dashboard-v1__stats{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin:18px 0}.mvm-my-dashboard-v1__stats>div{padding:14px;border:1px solid var(--mvm-border,#d7e1e8);border-radius:12px;background:var(--mvm-bg,#f4f7fa)}.mvm-my-dashboard-v1__stats strong{display:block;font-size:26px;line-height:1.1}.mvm-my-dashboard-v1__stats span{display:block;margin-top:4px;font-size:13px}.mvm-my-dashboard-v1__grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin-top:18px}.mvm-my-dashboard-v1__grid .mvm-public-v1__card{display:flex;flex-direction:column;min-height:190px}.mvm-my-dashboard-v1__grid .mvm-public-v1__card h3{margin-top:0}.mvm-my-dashboard-v1__grid .mvm-public-v1__card p:last-child{margin-top:auto}.mvm-my-dashboard-v1__grid .mvm-public-v1__button{display:inline-flex;align-items:center;justify-content:center;text-align:center}@media(max-width:1000px){.mvm-my-dashboard-v1__stats,.mvm-my-dashboard-v1__grid{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:560px){.mvm-my-dashboard-v1__welcome{align-items:flex-start}.mvm-my-dashboard-v1__avatar{width:56px;height:56px}.mvm-my-dashboard-v1__stats,.mvm-my-dashboard-v1__grid{grid-template-columns:1fr}.mvm-my-dashboard-v1__grid .mvm-public-v1__card{min-height:0}.mvm-my-dashboard-v1__grid .mvm-public-v1__button{width:100%}}</style>';
    return ob_get_clean();
} );