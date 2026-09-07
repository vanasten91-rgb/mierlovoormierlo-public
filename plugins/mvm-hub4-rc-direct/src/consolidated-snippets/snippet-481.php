<?php
// Consolidated from production Code Snippet #481.
defined( 'ABSPATH' ) || exit;

add_shortcode( 'mvm_organisaties_gids_v1', static function () {
    $query = new WP_Query( [
        'post_type'      => [ 'mvm_vereniging', 'mvm_bedrijf' ],
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
        'no_found_rows'  => true,
    ] );

    $vereniging_count = 0;
    $bedrijf_count = 0;
    foreach ( $query->posts as $item ) {
        if ( 'mvm_vereniging' === $item->post_type ) { $vereniging_count++; }
        if ( 'mvm_bedrijf' === $item->post_type ) { $bedrijf_count++; }
    }

    $user_id = get_current_user_id();
    $vereniging_favs = [];
    $ency_favs = [];
    if ( $user_id ) {
        $vereniging_favs = function_exists( 'mvm_my_favorite_vereniging_ids_v1' ) ? mvm_my_favorite_vereniging_ids_v1( $user_id ) : (array) get_user_meta( $user_id, 'mvm_my_favorite_verenigingen_v1', true );
        $ency_favs = function_exists( 'mvm_my_ency_favorite_ids_v1' ) ? mvm_my_ency_favorite_ids_v1( $user_id ) : (array) get_user_meta( $user_id, 'mvm_my_ency_favorites_v1', true );
        $vereniging_favs = array_map( 'absint', $vereniging_favs );
        $ency_favs = array_map( 'absint', $ency_favs );
    }

    ob_start();
    echo '<section class="mvm-org-guide-v1" data-mvm-org-guide>';
    echo '<header class="mvm-org-guide-v1__intro"><p class="mvm-org-guide-v1__eyebrow">Ontdek Mierlo</p><h2>Verenigingen &amp; organisaties</h2><p>Doorzoek verenigingen en bedrijven uit de Mierlose Encyclopedie. De gids bevat zowel actuele als historische organisaties; het volledige dossier geeft de inhoudelijke context.</p><div class="mvm-org-guide-v1__counts"><span><strong>' . esc_html( (string) $vereniging_count ) . '</strong> verenigingen</span><span><strong>' . esc_html( (string) $bedrijf_count ) . '</strong> bedrijven</span></div></header>';

    echo '<div class="mvm-org-guide-v1__controls">';
    echo '<label for="mvm-org-guide-search">Zoeken</label><input id="mvm-org-guide-search" type="search" placeholder="Zoek op naam of inhoud…" autocomplete="off" data-mvm-org-search>';
    echo '<div class="mvm-org-guide-v1__filters" role="group" aria-label="Filter organisaties"><button type="button" class="is-active" data-mvm-org-filter="all" aria-pressed="true">Alles</button><button type="button" data-mvm-org-filter="vereniging" aria-pressed="false">Verenigingen</button><button type="button" data-mvm-org-filter="bedrijf" aria-pressed="false">Bedrijven</button></div>';
    echo '<p class="mvm-org-guide-v1__status" aria-live="polite"><span data-mvm-org-result-count>' . esc_html( (string) ( $vereniging_count + $bedrijf_count ) ) . '</span> resultaten</p></div>';

    echo '<div class="mvm-org-guide-v1__grid" data-mvm-org-grid>';
    foreach ( $query->posts as $item ) {
        $id = absint( $item->ID );
        $type = 'mvm_vereniging' === $item->post_type ? 'vereniging' : 'bedrijf';
        $type_label = 'vereniging' === $type ? 'Vereniging' : 'Bedrijf';
        $content = preg_replace( '#^\s*<h[1-6][^>]*>.*?</h[1-6]>\s*#is', '', (string) $item->post_content, 1 );
        $summary = wp_trim_words( wp_strip_all_tags( strip_shortcodes( $content ) ), 30, '…' );
        if ( '' === trim( $summary ) ) { $summary = 'Lees het volledige dossier in de Mierlose Encyclopedie.'; }
        $search_text = function_exists( 'mb_strtolower' ) ? mb_strtolower( $item->post_title . ' ' . $summary, 'UTF-8' ) : strtolower( $item->post_title . ' ' . $summary );
        $is_fav = 'vereniging' === $type ? in_array( $id, $vereniging_favs, true ) : in_array( $id, $ency_favs, true );
        echo '<article class="mvm-org-guide-v1__card" data-mvm-org-item data-type="' . esc_attr( $type ) . '" data-search="' . esc_attr( $search_text ) . '">';
        echo '<div class="mvm-org-guide-v1__card-top"><span class="mvm-org-guide-v1__type">' . esc_html( $type_label ) . '</span>';
        if ( $is_fav ) { echo '<span class="mvm-org-guide-v1__saved">In Mijn Mierlo</span>'; }
        echo '</div><h3><a href="' . esc_url( get_permalink( $id ) ) . '">' . esc_html( get_the_title( $id ) ) . '</a></h3><p>' . esc_html( $summary ) . '</p><div class="mvm-org-guide-v1__actions"><a class="mvm-org-guide-v1__primary" href="' . esc_url( get_permalink( $id ) ) . '">Bekijk dossier</a>';
        if ( $user_id ) {
            $action = 'vereniging' === $type ? 'mvm_my_toggle_vereniging_v1' : 'mvm_my_toggle_ency_v1';
            $nonce = 'vereniging' === $type ? 'mvm_my_toggle_vereniging_v1' : 'mvm_my_toggle_ency_v1';
            echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="' . esc_attr( $action ) . '"><input type="hidden" name="post_id" value="' . esc_attr( $id ) . '"><input type="hidden" name="redirect_to" value="' . esc_url( home_url( '/organisaties/' ) ) . '">';
            wp_nonce_field( $nonce );
            echo '<button type="submit" class="mvm-org-guide-v1__secondary">' . esc_html( $is_fav ? 'Verwijder favoriet' : 'Bewaar in Mijn Mierlo' ) . '</button></form>';
        } else {
            echo '<a class="mvm-org-guide-v1__secondary" href="' . esc_url( wp_login_url( home_url( '/organisaties/' ) ) ) . '">Log in om te bewaren</a>';
        }
        echo '</div></article>';
    }
    echo '</div><p class="mvm-org-guide-v1__empty" data-mvm-org-empty hidden>Geen organisaties gevonden met deze zoekopdracht.</p></section>';

    echo <<<'MVMORG'
<style id="mvm-org-guide-v1-css">
.mvm-org-guide-v1{--mvm-blue:#1966AE;max-width:1240px;margin:0 auto}.mvm-org-guide-v1__intro{margin-bottom:22px}.mvm-org-guide-v1__eyebrow{margin:0 0 6px;color:var(--mvm-blue);font-weight:800;text-transform:uppercase;letter-spacing:.04em;font-size:.8rem}.mvm-org-guide-v1__intro h2{margin:0 0 10px;text-transform:none}.mvm-org-guide-v1__counts{display:flex;flex-wrap:wrap;gap:10px;margin-top:14px}.mvm-org-guide-v1__counts span{display:inline-flex;gap:5px;padding:7px 10px;border:1px solid var(--mvm-border,#c9d6e0);border-radius:999px}.mvm-org-guide-v1__controls{margin:0 0 22px;padding:16px;border:1px solid var(--mvm-border,#c9d6e0);border-radius:14px;background:var(--mvm-bg,#f5f8fb)}.mvm-org-guide-v1__controls label{display:block;margin-bottom:6px;font-weight:700}.mvm-org-guide-v1__controls input[type=search]{width:100%;min-height:46px;padding:10px 12px;border:1px solid var(--mvm-border,#aebdca);border-radius:9px;background:var(--mvm-surface,#fff);color:inherit}.mvm-org-guide-v1__filters{display:flex;flex-wrap:wrap;gap:8px;margin-top:12px}.mvm-org-guide-v1__filters button,.mvm-org-guide-v1__primary,.mvm-org-guide-v1__secondary{min-height:40px;padding:8px 12px;border:1px solid var(--mvm-blue);border-radius:9px;background:transparent;color:var(--mvm-blue);font:inherit;font-weight:700;text-decoration:none;cursor:pointer}.mvm-org-guide-v1__filters button.is-active,.mvm-org-guide-v1__primary{background:var(--mvm-blue);color:#fff}.mvm-org-guide-v1__filters button:focus-visible,.mvm-org-guide-v1__primary:focus-visible,.mvm-org-guide-v1__secondary:focus-visible,.mvm-org-guide-v1__card a:focus-visible{outline:3px solid currentColor;outline-offset:2px}.mvm-org-guide-v1__status{margin:10px 0 0}.mvm-org-guide-v1__grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px}.mvm-org-guide-v1__card{display:flex;flex-direction:column;min-height:300px;padding:18px;border:1px solid var(--mvm-border,#c9d6e0);border-radius:14px;background:var(--mvm-surface,#fff)}.mvm-org-guide-v1__card[hidden]{display:none!important}.mvm-org-guide-v1__card-top{display:flex;align-items:center;justify-content:space-between;gap:8px}.mvm-org-guide-v1__type,.mvm-org-guide-v1__saved{display:inline-flex;padding:4px 8px;border-radius:999px;font-size:.78rem;font-weight:800}.mvm-org-guide-v1__type{background:rgba(25,102,174,.10);color:var(--mvm-blue)}.mvm-org-guide-v1__saved{background:rgba(28,126,66,.12);color:#176a39}.mvm-org-guide-v1__card h3{margin:13px 0 8px;text-transform:none;font-size:1.12rem}.mvm-org-guide-v1__card h3 a{text-decoration:none}.mvm-org-guide-v1__card>p{margin:0 0 16px;line-height:1.55}.mvm-org-guide-v1__actions{display:flex;flex-wrap:wrap;align-items:center;gap:8px;margin-top:auto}.mvm-org-guide-v1__actions form{margin:0}.mvm-org-guide-v1__empty{padding:20px;text-align:center;border:1px dashed var(--mvm-border,#c9d6e0);border-radius:12px}.dark-mode .mvm-org-guide-v1__saved,[data-theme=dark] .mvm-org-guide-v1__saved{color:#9fe0b9}.dark-mode .mvm-org-guide-v1__type,[data-theme=dark] .mvm-org-guide-v1__type{color:#8fc7f3}@media(max-width:980px){.mvm-org-guide-v1__grid{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:620px){.mvm-org-guide-v1__grid{grid-template-columns:1fr}.mvm-org-guide-v1__filters{display:grid;grid-template-columns:1fr}.mvm-org-guide-v1__filters button,.mvm-org-guide-v1__primary,.mvm-org-guide-v1__secondary{width:100%;text-align:center;justify-content:center}.mvm-org-guide-v1__actions,.mvm-org-guide-v1__actions form{width:100%}}
</style>
<script id="mvm-org-guide-v1-js">
(function(){
  var root=document.querySelector('[data-mvm-org-guide]'); if(!root) return;
  var input=root.querySelector('[data-mvm-org-search]'), buttons=root.querySelectorAll('[data-mvm-org-filter]'), items=root.querySelectorAll('[data-mvm-org-item]'), count=root.querySelector('[data-mvm-org-result-count]'), empty=root.querySelector('[data-mvm-org-empty]');
  var type='all';
  function norm(v){return (v||'').toLocaleLowerCase('nl-NL').trim();}
  function run(){var term=norm(input.value), visible=0; items.forEach(function(item){var okType=type==='all'||item.getAttribute('data-type')===type; var okSearch=!term||norm(item.getAttribute('data-search')).indexOf(term)!==-1; var show=okType&&okSearch; item.hidden=!show; if(show) visible++;}); count.textContent=String(visible); empty.hidden=visible!==0;}
  input.addEventListener('input',run);
  buttons.forEach(function(btn){btn.addEventListener('click',function(){type=btn.getAttribute('data-mvm-org-filter')||'all'; buttons.forEach(function(b){var active=b===btn;b.classList.toggle('is-active',active);b.setAttribute('aria-pressed',active?'true':'false');});run();});});
}());
</script>
MVMORG;
    wp_reset_postdata();
    return ob_get_clean();
} );