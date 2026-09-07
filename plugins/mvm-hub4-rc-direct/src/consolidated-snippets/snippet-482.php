<?php
// Consolidated from production Code Snippet #482.
defined( 'ABSPATH' ) || exit;

add_shortcode( 'mvm_publicatiecheck_v1', static function () {
    if ( ! is_user_logged_in() || ! ( current_user_can( 'edit_posts' ) || current_user_can( 'mvm_access_hub' ) || current_user_can( 'manage_options' ) ) ) {
        return '<section class="mvm-publish-check-v1 mvm-publish-check-v1--denied"><h2>Publicatiecheck</h2><p>Deze redactietool is alleen beschikbaar voor bevoegde medewerkers.</p></section>';
    }

    $posts = get_posts( [
        'post_type'      => 'post',
        'post_status'    => [ 'draft', 'pending', 'future' ],
        'posts_per_page' => 30,
        'orderby'        => 'modified',
        'order'          => 'DESC',
    ] );

    $rows = [];
    $summary = [ 'critical' => 0, 'warning' => 0, 'ok' => 0 ];
    foreach ( $posts as $post ) {
        $critical = [];
        $warnings = [];
        $title = trim( wp_strip_all_tags( (string) $post->post_title ) );
        $title_len = function_exists( 'mb_strlen' ) ? mb_strlen( $title, 'UTF-8' ) : strlen( $title );
        $plain = trim( wp_strip_all_tags( strip_shortcodes( (string) $post->post_content ) ) );
        $content_len = function_exists( 'mb_strlen' ) ? mb_strlen( $plain, 'UTF-8' ) : strlen( $plain );
        $words = '' === $plain ? 0 : count( preg_split( '/\s+/u', $plain, -1, PREG_SPLIT_NO_EMPTY ) );

        if ( '' === $title ) { $critical[] = 'Titel ontbreekt.'; }
        elseif ( $title_len < 20 ) { $warnings[] = 'Titel is erg kort (' . $title_len . ' tekens).'; }
        elseif ( $title_len > 95 ) { $warnings[] = 'Titel is erg lang (' . $title_len . ' tekens).'; }

        if ( $content_len < 300 ) { $critical[] = 'Inhoud is te kort om publicatierijp te zijn (' . $words . ' woorden).'; }
        elseif ( $content_len < 800 ) { $warnings[] = 'Inhoud is nog vrij kort (' . $words . ' woorden).'; }

        $thumb_id = get_post_thumbnail_id( $post->ID );
        if ( ! $thumb_id ) { $critical[] = 'Uitgelichte afbeelding ontbreekt.'; }
        else {
            $alt = trim( (string) get_post_meta( $thumb_id, '_wp_attachment_image_alt', true ) );
            if ( '' === $alt ) { $warnings[] = 'Alt-tekst van de uitgelichte afbeelding ontbreekt.'; }
        }

        $seo = trim( (string) get_post_meta( $post->ID, '_yoast_wpseo_metadesc', true ) );
        if ( '' === $seo ) { $warnings[] = 'SEO-description ontbreekt.'; }

        $categories = wp_get_post_categories( $post->ID );
        if ( empty( $categories ) ) { $critical[] = 'Categorie ontbreekt.'; }

        if ( ! get_userdata( (int) $post->post_author ) ) { $critical[] = 'Geldige auteur ontbreekt.'; }
        if ( preg_match( '/<h1\b/i', (string) $post->post_content ) ) { $warnings[] = 'Inhoud bevat een H1; de paginatitel is al de H1.'; }
        if ( preg_match( '/\b(test|voorbeeld|niet publiceren)\b/ui', $title ) ) { $warnings[] = 'Titel bevat een mogelijke test- of placeholderterm.'; }

        if ( '' !== $title ) {
            $dupes = get_posts( [ 'post_type' => 'post', 'post_status' => [ 'publish','draft','pending','future' ], 'title' => $title, 'posts_per_page' => 3, 'fields' => 'ids' ] );
            $dupes = array_values( array_diff( array_map( 'absint', $dupes ), [ (int) $post->ID ] ) );
            if ( ! empty( $dupes ) ) { $warnings[] = 'Er bestaat al een bericht met dezelfde titel.'; }
        }

        if ( 'future' === $post->post_status && $post->post_date <= current_time( 'mysql' ) ) { $warnings[] = 'Gepland bericht heeft geen toekomstige publicatietijd.'; }

        $state = ! empty( $critical ) ? 'critical' : ( ! empty( $warnings ) ? 'warning' : 'ok' );
        $summary[ $state ]++;
        $rows[] = [ 'post' => $post, 'critical' => $critical, 'warnings' => $warnings, 'state' => $state, 'words' => $words ];
    }

    $labels = [ 'draft' => 'Concept', 'pending' => 'Te beoordelen', 'future' => 'Gepland' ];
    ob_start();
    echo '<section class="mvm-publish-check-v1" aria-labelledby="mvm-publish-check-title">';
    echo '<header class="mvm-publish-check-v1__header"><p class="mvm-publish-check-v1__eyebrow">Redactie</p><h2 id="mvm-publish-check-title">Publicatiecheck</h2><p>Read-only controle van openstaande nieuwsberichten. De check waarschuwt alleen en publiceert of wijzigt niets.</p><p class="mvm-publish-check-v1__time">Laatste controle: ' . esc_html( wp_date( 'j F Y · H:i' ) ) . '</p></header>';
    echo '<div class="mvm-publish-check-v1__summary"><div><strong>' . esc_html( (string) count( $rows ) ) . '</strong><span>openstaand</span></div><div class="is-critical"><strong>' . esc_html( (string) $summary['critical'] ) . '</strong><span>kritiek</span></div><div class="is-warning"><strong>' . esc_html( (string) $summary['warning'] ) . '</strong><span>waarschuwing</span></div><div class="is-ok"><strong>' . esc_html( (string) $summary['ok'] ) . '</strong><span>publicatierijp</span></div></div>';

    if ( empty( $rows ) ) { echo '<div class="mvm-publish-check-v1__empty"><strong>Geen openstaande artikelen.</strong><p>Er zijn momenteel geen concepten, pending of geplande nieuwsberichten om te controleren.</p></div></section>'; return ob_get_clean(); }

    echo '<div class="mvm-publish-check-v1__list">';
    foreach ( $rows as $row ) {
        $post = $row['post'];
        $state_label = 'critical' === $row['state'] ? 'Kritiek' : ( 'warning' === $row['state'] ? 'Waarschuwing' : 'In orde' );
        echo '<article class="mvm-publish-check-v1__item is-' . esc_attr( $row['state'] ) . '">';
        echo '<div class="mvm-publish-check-v1__item-head"><div><span class="mvm-publish-check-v1__status">' . esc_html( $state_label ) . '</span><span class="mvm-publish-check-v1__post-status">' . esc_html( $labels[ $post->post_status ] ?? $post->post_status ) . '</span><h3>' . esc_html( $post->post_title ?: '(Zonder titel)' ) . '</h3></div><strong class="mvm-publish-check-v1__words">' . esc_html( (string) $row['words'] ) . ' woorden</strong></div>';
        if ( ! empty( $row['critical'] ) || ! empty( $row['warnings'] ) ) {
            echo '<ul class="mvm-publish-check-v1__issues">';
            foreach ( $row['critical'] as $issue ) { echo '<li class="is-critical">' . esc_html( $issue ) . '</li>'; }
            foreach ( $row['warnings'] as $issue ) { echo '<li class="is-warning">' . esc_html( $issue ) . '</li>'; }
            echo '</ul>';
        } else { echo '<p class="mvm-publish-check-v1__ready">Alle basiscontroles zijn in orde.</p>'; }
        echo '<div class="mvm-publish-check-v1__actions">';
        $preview = get_preview_post_link( $post );
        if ( $preview ) { echo '<a href="' . esc_url( $preview ) . '">Voorbeeld bekijken</a>'; }
        echo '<a href="' . esc_url( home_url( '/hub/' ) ) . '">Open MvM Hub</a></div></article>';
    }
    echo '</div></section>';

    echo <<<'MVMPUBCSS'
<style id="mvm-publish-check-v1-css">
.mvm-publish-check-v1{--blue:#1966AE;max-width:1150px;margin:0 auto}.mvm-publish-check-v1__header{margin-bottom:18px}.mvm-publish-check-v1__eyebrow{margin:0 0 5px;color:var(--blue);font-size:.8rem;font-weight:800;text-transform:uppercase;letter-spacing:.04em}.mvm-publish-check-v1__header h2{margin:0 0 8px;text-transform:none}.mvm-publish-check-v1__time{font-size:.9rem;opacity:.75}.mvm-publish-check-v1__summary{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;margin:18px 0}.mvm-publish-check-v1__summary>div{padding:14px;border:1px solid var(--mvm-border,#c9d6e0);border-radius:12px;background:var(--mvm-surface,#fff)}.mvm-publish-check-v1__summary strong,.mvm-publish-check-v1__summary span{display:block}.mvm-publish-check-v1__summary strong{font-size:1.7rem}.mvm-publish-check-v1__summary .is-critical{border-left:5px solid #b42318}.mvm-publish-check-v1__summary .is-warning{border-left:5px solid #b26a00}.mvm-publish-check-v1__summary .is-ok{border-left:5px solid #1c7e42}.mvm-publish-check-v1__list{display:grid;gap:12px}.mvm-publish-check-v1__item{padding:16px;border:1px solid var(--mvm-border,#c9d6e0);border-left-width:5px;border-radius:12px;background:var(--mvm-surface,#fff)}.mvm-publish-check-v1__item.is-critical{border-left-color:#b42318}.mvm-publish-check-v1__item.is-warning{border-left-color:#b26a00}.mvm-publish-check-v1__item.is-ok{border-left-color:#1c7e42}.mvm-publish-check-v1__item-head{display:flex;justify-content:space-between;gap:16px}.mvm-publish-check-v1__item h3{margin:8px 0 0;text-transform:none}.mvm-publish-check-v1__status,.mvm-publish-check-v1__post-status{display:inline-flex;margin-right:6px;padding:4px 8px;border-radius:999px;background:var(--mvm-bg,#eef3f7);font-size:.78rem;font-weight:800}.mvm-publish-check-v1__words{white-space:nowrap}.mvm-publish-check-v1__issues{margin:14px 0;padding-left:22px}.mvm-publish-check-v1__issues li{margin:5px 0}.mvm-publish-check-v1__issues li.is-critical::marker{color:#b42318}.mvm-publish-check-v1__issues li.is-warning::marker{color:#b26a00}.mvm-publish-check-v1__ready{font-weight:700;color:#1c7e42}.mvm-publish-check-v1__actions{display:flex;flex-wrap:wrap;gap:8px;margin-top:12px}.mvm-publish-check-v1__actions a{display:inline-flex;padding:8px 11px;border:1px solid var(--blue);border-radius:8px;color:var(--blue);font-weight:700;text-decoration:none}.mvm-publish-check-v1__actions a:focus-visible{outline:3px solid currentColor;outline-offset:2px}.mvm-publish-check-v1__empty{padding:18px;border:1px dashed var(--mvm-border,#c9d6e0);border-radius:12px}@media(max-width:700px){.mvm-publish-check-v1__summary{grid-template-columns:repeat(2,minmax(0,1fr))}.mvm-publish-check-v1__item-head{flex-direction:column}.mvm-publish-check-v1__words{white-space:normal}}@media(max-width:420px){.mvm-publish-check-v1__summary{grid-template-columns:1fr}.mvm-publish-check-v1__actions a{width:100%;justify-content:center}}
</style>
MVMPUBCSS;
    return ob_get_clean();
} );