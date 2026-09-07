<?php
// Consolidated from production Code Snippet #483.
defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'mvm_hub_publish_bridge_scan_v1' ) ) {
    function mvm_hub_publish_bridge_scan_v1() {
        $posts = get_posts( [
            'post_type' => 'post',
            'post_status' => [ 'draft', 'pending', 'future' ],
            'posts_per_page' => 12,
            'orderby' => 'modified',
            'order' => 'DESC',
        ] );
        $rows = []; $counts = [ 'critical' => 0, 'warning' => 0, 'ok' => 0 ];
        foreach ( $posts as $post ) {
            $critical = []; $warning = [];
            $plain = trim( wp_strip_all_tags( strip_shortcodes( (string) $post->post_content ) ) );
            $words = '' === $plain ? 0 : count( preg_split( '/\s+/u', $plain, -1, PREG_SPLIT_NO_EMPTY ) );
            if ( '' === trim( wp_strip_all_tags( (string) $post->post_title ) ) ) { $critical[] = 'Titel ontbreekt'; }
            if ( $words < 55 ) { $critical[] = 'Inhoud erg kort'; } elseif ( $words < 100 ) { $warning[] = 'Inhoud vrij kort'; }
            $thumb = get_post_thumbnail_id( $post->ID );
            if ( ! $thumb ) { $critical[] = 'Uitgelichte afbeelding ontbreekt'; } else {
                if ( '' === trim( (string) get_post_meta( $thumb, '_wp_attachment_image_alt', true ) ) ) { $warning[] = 'Alt-tekst ontbreekt'; }
            }
            if ( '' === trim( (string) get_post_meta( $post->ID, '_yoast_wpseo_metadesc', true ) ) ) { $warning[] = 'SEO-description ontbreekt'; }
            if ( empty( wp_get_post_categories( $post->ID ) ) ) { $critical[] = 'Categorie ontbreekt'; }
            if ( ! get_userdata( (int) $post->post_author ) ) { $critical[] = 'Auteur ontbreekt'; }
            if ( preg_match( '/<h1\b/i', (string) $post->post_content ) ) { $warning[] = 'Extra H1 in inhoud'; }
            $state = $critical ? 'critical' : ( $warning ? 'warning' : 'ok' );
            $counts[ $state ]++;
            $rows[] = [ 'title' => (string) $post->post_title, 'status' => (string) $post->post_status, 'critical' => $critical, 'warning' => $warning, 'state' => $state ];
        }
        return [ 'rows' => $rows, 'counts' => $counts ];
    }
}

add_action( 'template_redirect', static function () {
    if ( is_admin() || wp_doing_ajax() ) { return; }
    $path = trim( (string) wp_parse_url( isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '', PHP_URL_PATH ), '/' );
    if ( ! preg_match( '#^hub4?(?:/|$)#', $path ) ) { return; }

    ob_start( static function ( $html ) {
        if ( false !== strpos( $html, 'id="mvm-hub-publish-bridge-v1"' ) ) { return $html; }
        $authorized = false !== strpos( $html, 'class="mvm-hub4"' ) || false !== strpos( $html, "class='mvm-hub4'" );
        if ( ! $authorized ) { return $html; }

        $scan = mvm_hub_publish_bridge_scan_v1();
        $counts = $scan['counts'];
        $total = count( $scan['rows'] );
        $status_labels = [ 'draft' => 'Concept', 'pending' => 'Te beoordelen', 'future' => 'Gepland' ];
        ob_start();
        echo '<section id="mvm-hub-publish-bridge-v1" class="mvm-hub-publish-bridge-v1" aria-labelledby="mvm-hub-publish-bridge-title">';
        echo '<div class="mvm-hub-publish-bridge-v1__head"><div><p>Redactiecontrole</p><h2 id="mvm-hub-publish-bridge-title">Publicatiecheck</h2><span>Read-only kwaliteitscontrole van openstaande nieuwsberichten.</span></div><a href="#mvm-hub-publish-bridge-list">Bekijk details</a></div>';
        echo '<div class="mvm-hub-publish-bridge-v1__stats"><div><strong>' . esc_html( (string) $total ) . '</strong><span>openstaand</span></div><div class="is-critical"><strong>' . esc_html( (string) $counts['critical'] ) . '</strong><span>kritiek</span></div><div class="is-warning"><strong>' . esc_html( (string) $counts['warning'] ) . '</strong><span>waarschuwing</span></div><div class="is-ok"><strong>' . esc_html( (string) $counts['ok'] ) . '</strong><span>in orde</span></div></div>';
        if ( $total ) {
            echo '<div id="mvm-hub-publish-bridge-list" class="mvm-hub-publish-bridge-v1__list">';
            foreach ( array_slice( $scan['rows'], 0, 6 ) as $row ) {
                $issues = array_merge( $row['critical'], $row['warning'] );
                echo '<article class="is-' . esc_attr( $row['state'] ) . '"><div><span>' . esc_html( $status_labels[ $row['status'] ] ?? $row['status'] ) . '</span><strong>' . esc_html( $row['title'] ?: '(Zonder titel)' ) . '</strong></div>';
                if ( $issues ) { echo '<small>' . esc_html( implode( ' · ', $issues ) ) . '</small>'; } else { echo '<small>Basiscontroles in orde</small>'; }
                echo '</article>';
            }
            echo '</div>';
        } else { echo '<p class="mvm-hub-publish-bridge-v1__empty">Geen openstaande artikelen.</p>'; }
        echo '</section>';
        $card = ob_get_clean();

        $style = '<style id="mvm-hub-publish-bridge-v1-css">.mvm-hub-publish-bridge-v1{margin:18px 0;padding:18px;border:1px solid var(--mvm-hub4-border,#c5d3df);border-radius:14px;background:var(--mvm-hub4-surface,#fff);color:var(--mvm-hub4-text,#17222d)}.mvm-hub-publish-bridge-v1__head{display:flex;justify-content:space-between;align-items:flex-start;gap:16px}.mvm-hub-publish-bridge-v1__head p{margin:0 0 4px;color:var(--mvm-hub4-blue,#1966AE);font-size:.78rem;font-weight:800;text-transform:uppercase}.mvm-hub-publish-bridge-v1__head h2{margin:0 0 5px}.mvm-hub-publish-bridge-v1__head span{color:var(--mvm-hub4-muted,#46596b)}.mvm-hub-publish-bridge-v1__head a{font-weight:800}.mvm-hub-publish-bridge-v1__stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;margin:16px 0}.mvm-hub-publish-bridge-v1__stats>div{padding:12px;border:1px solid var(--mvm-hub4-border,#c5d3df);border-radius:10px}.mvm-hub-publish-bridge-v1__stats strong,.mvm-hub-publish-bridge-v1__stats span{display:block}.mvm-hub-publish-bridge-v1__stats strong{font-size:1.5rem}.mvm-hub-publish-bridge-v1__stats .is-critical{border-left:5px solid #b42318}.mvm-hub-publish-bridge-v1__stats .is-warning{border-left:5px solid #b26a00}.mvm-hub-publish-bridge-v1__stats .is-ok{border-left:5px solid #1c7e42}.mvm-hub-publish-bridge-v1__list{display:grid;gap:8px}.mvm-hub-publish-bridge-v1__list article{padding:10px 12px;border:1px solid var(--mvm-hub4-border,#c5d3df);border-left-width:4px;border-radius:9px}.mvm-hub-publish-bridge-v1__list article.is-critical{border-left-color:#b42318}.mvm-hub-publish-bridge-v1__list article.is-warning{border-left-color:#b26a00}.mvm-hub-publish-bridge-v1__list article.is-ok{border-left-color:#1c7e42}.mvm-hub-publish-bridge-v1__list article div{display:flex;gap:8px;align-items:baseline}.mvm-hub-publish-bridge-v1__list article div span{font-size:.78rem;color:var(--mvm-hub4-muted,#46596b)}.mvm-hub-publish-bridge-v1__list small{display:block;margin-top:4px;color:var(--mvm-hub4-muted,#46596b)}@media(max-width:680px){.mvm-hub-publish-bridge-v1__head{flex-direction:column}.mvm-hub-publish-bridge-v1__stats{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:420px){.mvm-hub-publish-bridge-v1__stats{grid-template-columns:1fr}}</style>';
        $head_pos = stripos( $html, '</head>' );
        if ( false !== $head_pos ) { $html = substr_replace( $html, $style . '</head>', $head_pos, 7 ); } else { $card = $style . $card; }
        $main_pos = strripos( $html, '</main>' );
        if ( false !== $main_pos ) { return substr_replace( $html, $card . '</main>', $main_pos, 7 ); }
        $body_pos = strripos( $html, '</body>' );
        return false !== $body_pos ? substr_replace( $html, $card . '</body>', $body_pos, 7 ) : $html . $card;
    } );
}, -1400 );