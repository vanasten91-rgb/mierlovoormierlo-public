<?php
// Consolidated from production Code Snippet #484.
defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'mvm_radar_norm_v1' ) ) {
    function mvm_radar_norm_v1( $text ) {
        $text = wp_strip_all_tags( (string) $text );
        $text = remove_accents( $text );
        $text = function_exists( 'mb_strtolower' ) ? mb_strtolower( $text, 'UTF-8' ) : strtolower( $text );
        return trim( preg_replace( '/\s+/u', ' ', $text ) );
    }
}

if ( ! function_exists( 'mvm_radar_title_tokens_v1' ) ) {
    function mvm_radar_title_tokens_v1( $text ) {
        $stop = [ 'de','het','een','en','van','voor','met','op','in','bij','te','naar','uit','aan','is','jaar','mierlo' ];
        $parts = preg_split( '/[^a-z0-9]+/u', mvm_radar_norm_v1( $text ), -1, PREG_SPLIT_NO_EMPTY );
        $tokens = [];
        foreach ( $parts as $part ) {
            if ( in_array( $part, $stop, true ) || strlen( $part ) < 2 ) { continue; }
            $tokens[] = $part;
        }
        return array_values( array_unique( $tokens ) );
    }
}

if ( ! function_exists( 'mvm_radar_category_v1' ) ) {
    function mvm_radar_category_v1( $text ) {
        $t = mvm_radar_norm_v1( $text );
        if ( preg_match( '/\b(politie|brandweer|ongeval|aanrijding|112|veiligheid|vermist|inbraak)\b/u', $t ) ) { return '112 & Veiligheid'; }
        if ( preg_match( '/\b(gemeente|gemeenteraad|wethouder|burgemeester|raad|college|verkiezing|politiek)\b/u', $t ) ) { return 'Politiek'; }
        if ( preg_match( '/\b(agenda|evenement|festival|concert|tentoonstelling|expositie|kermis|markt|open dag|bijeenkomst)\b/u', $t ) ) { return 'Evenementen / Agenda'; }
        if ( preg_match( '/\b(voetbal|hockey|tennis|sport|wedstrijd|club|vereniging|gilde|harmonie|ivn|seniorenvereniging)\b/u', $t ) ) { return 'Verenigingen / Sport'; }
        if ( preg_match( '/\b(cultuur|theater|muziek|kunst|historie|erfgoed)\b/u', $t ) ) { return 'Cultuur'; }
        return 'Algemeen';
    }
}

if ( ! function_exists( 'mvm_radar_suggestions_v1' ) ) {
    function mvm_radar_suggestions_v1( $limit = 12 ) {
        $results = get_option( 'mvm_bh_news_radar_results', [] );
        if ( ! is_array( $results ) ) { return []; }
        $known_titles = [];
        foreach ( get_posts( [ 'post_type' => 'post', 'post_status' => [ 'publish','draft','pending','future' ], 'posts_per_page' => 100, 'orderby' => 'date', 'order' => 'DESC' ] ) as $known ) {
            $norm = mvm_radar_norm_v1( $known->post_title );
            $known_titles[ $norm ] = [ 'id' => (int) $known->ID, 'tokens' => mvm_radar_title_tokens_v1( $norm ) ];
        }
        $now = time(); $items_by_key = [];
        foreach ( $results as $row ) {
            if ( ! is_array( $row ) || ! empty( $row['dismissed'] ) || ! empty( $row['reviewed'] ) ) { continue; }
            if ( isset( $row['lifecycle'] ) && 'actief' !== (string) $row['lifecycle'] ) { continue; }
            $title = trim( (string) ( $row['title'] ?? '' ) );
            $excerpt = trim( (string) ( $row['excerpt'] ?? '' ) );
            $source = trim( (string) ( $row['source'] ?? '' ) );
            $url = esc_url_raw( (string) ( $row['url'] ?? '' ) );
            if ( '' === $title ) { continue; }
            $title_norm = mvm_radar_norm_v1( $title );
            $content_hay = mvm_radar_norm_v1( $title . ' ' . $excerpt );
            $source_norm = mvm_radar_norm_v1( $source );
            $score = 0; $reasons = [];
            $content_mentions_mierlo = (bool) preg_match( '/\bmierlo\b/u', $content_hay );
            $title_mentions_mierlo = (bool) preg_match( '/\bmierlo\b/u', $title_norm );
            $has_mierlo_postcode = (bool) preg_match( '/\b5731\b/u', $content_hay );
            if ( $content_mentions_mierlo ) { $score += 42; $reasons[] = 'Mierlo genoemd'; }
            if ( preg_match( '/geldrop[- –]mierlo/u', $content_hay ) ) { $score += 12; $reasons[] = 'Geldrop-Mierlo context'; }
            if ( $has_mierlo_postcode ) { $score += 18; $reasons[] = 'Mierlose postcode'; }
            if ( $title_mentions_mierlo ) { $score += 18; $reasons[] = 'Mierlo in titel'; }
            $local_source = false !== strpos( $source_norm, 'geldrop-mierlo' ) || false !== strpos( $source_norm, 'mierlo' );
            if ( $local_source && ( $content_mentions_mierlo || $has_mierlo_postcode ) ) { $score += 8; $reasons[] = 'Lokale bron'; }
            $local_score = isset( $row['local_score'] ) ? max( 0, min( 100, (int) $row['local_score'] ) ) : 0;
            if ( $local_score > 0 && ( $content_mentions_mierlo || $has_mierlo_postcode || $title_mentions_mierlo ) ) { $score += min( 12, (int) round( $local_score / 8 ) ); $reasons[] = 'Bestaande lokale score'; }
            $published_ts = ! empty( $row['published_ts'] ) ? (int) $row['published_ts'] : 0;
            if ( $published_ts > 0 && $published_ts <= $now ) {
                $age = $now - $published_ts;
                if ( $age <= 3 * DAY_IN_SECONDS ) { $score += 10; $reasons[] = 'Recent'; }
                elseif ( $age <= 7 * DAY_IN_SECONDS ) { $score += 5; $reasons[] = 'Deze week'; }
            }
            if ( 'new' === (string) ( $row['event_type'] ?? '' ) ) { $score += 6; }
            if ( ! empty( $row['related_post_id'] ) ) { $score -= 35; $reasons[] = 'Al gekoppeld aan artikel'; }
            if ( isset( $known_titles[ $title_norm ] ) ) {
                $score -= 45; $reasons[] = 'Titel bestaat al op MvM';
            } else {
                $tokens = mvm_radar_title_tokens_v1( $title_norm );
                if ( count( $tokens ) >= 2 ) {
                    foreach ( $known_titles as $known ) {
                        if ( count( $known['tokens'] ) < 2 ) { continue; }
                        $intersection = count( array_intersect( $tokens, $known['tokens'] ) );
                        $base = min( count( $tokens ), count( $known['tokens'] ) );
                        if ( $base >= 2 && ( $intersection / $base ) >= 0.75 ) {
                            $score -= 35; $reasons[] = 'Lijkt op bestaand artikel'; break;
                        }
                    }
                }
            }
            $is_mierlo_hout = false !== strpos( $content_hay, 'mierlo-hout' ) || false !== strpos( $content_hay, 'mierlo hout' );
            if ( $is_mierlo_hout && false === strpos( $content_hay, 'geldrop-mierlo' ) && ! $has_mierlo_postcode ) { $score -= 70; $reasons[] = 'Waarschijnlijk Mierlo-Hout'; }
            if ( false !== strpos( $content_hay, 'geldrop' ) && ! $content_mentions_mierlo ) { $score -= 35; $reasons[] = 'Alleen Geldrop genoemd'; }
            if ( false !== strpos( $title_norm, 'geldrop' ) && ! $title_mentions_mierlo ) { $score -= 20; $reasons[] = 'Geldrop in titel'; }
            $score = max( 0, min( 100, $score ) );
            if ( $score < 35 ) { continue; }
            $item = [
                'id' => (string) ( $row['id'] ?? '' ), 'title' => $title, 'url' => $url, 'source' => $source,
                'score' => $score, 'reasons' => array_values( array_unique( $reasons ) ),
                'category' => mvm_radar_category_v1( $title . ' ' . $excerpt ),
                'event_type' => (string) ( $row['event_type'] ?? '' ), 'published_ts' => $published_ts,
            ];
            $key = $url ? 'url:' . sha1( strtolower( $url ) ) : ( ! empty( $item['id'] ) ? 'id:' . $item['id'] : 'title:' . sha1( $title_norm ) );
            if ( ! isset( $items_by_key[ $key ] ) || $item['score'] > $items_by_key[ $key ]['score'] || ( $item['score'] === $items_by_key[ $key ]['score'] && $item['published_ts'] > $items_by_key[ $key ]['published_ts'] ) ) {
                $items_by_key[ $key ] = $item;
            }
        }
        $items = array_values( $items_by_key );
        usort( $items, static function ( $a, $b ) { if ( $a['score'] === $b['score'] ) { return $b['published_ts'] <=> $a['published_ts']; } return $b['score'] <=> $a['score']; } );
        return array_slice( $items, 0, max( 1, absint( $limit ) ) );
    }
}

if ( ! function_exists( 'mvm_radar_suggestions_markup_v1' ) ) {
    function mvm_radar_suggestions_markup_v1( $compact = false ) {
        $items = mvm_radar_suggestions_v1( $compact ? 6 : 12 );
        ob_start();
        echo '<section class="mvm-radar-suggestions-v1' . ( $compact ? ' is-compact' : '' ) . '" aria-labelledby="mvm-radar-suggestions-title">';
        echo '<header><div><p>Nieuwsradar</p><h2 id="mvm-radar-suggestions-title">Redactiesuggesties</h2><span>Lokale relevance zonder AI: Mierlo-context, bron, actualiteit en bestaande relaties.</span></div><strong>' . esc_html( (string) count( $items ) ) . ' relevant</strong></header>';
        if ( empty( $items ) ) { echo '<p class="mvm-radar-suggestions-v1__empty">Op dit moment zijn er geen open radarresultaten boven de lokale relevantiedrempel.</p></section>'; return ob_get_clean(); }
        echo '<div class="mvm-radar-suggestions-v1__list">';
        foreach ( $items as $item ) {
            echo '<article><div class="mvm-radar-suggestions-v1__score"><strong>' . esc_html( (string) $item['score'] ) . '%</strong><span>' . esc_html( $item['category'] ) . '</span></div><div class="mvm-radar-suggestions-v1__copy"><h3>';
            if ( $item['url'] ) { echo '<a href="' . esc_url( $item['url'] ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $item['title'] ) . '</a>'; } else { echo esc_html( $item['title'] ); }
            echo '</h3><p><strong>' . esc_html( $item['source'] ?: 'Onbekende bron' ) . '</strong>';
            if ( $item['published_ts'] ) { echo ' · ' . esc_html( wp_date( 'j F Y · H:i', $item['published_ts'], wp_timezone() ) ); }
            echo '</p><small>' . esc_html( implode( ' · ', array_slice( $item['reasons'], 0, 5 ) ) ) . '</small></div></article>';
        }
        echo '</div><p class="mvm-radar-suggestions-v1__note">Dit zijn suggesties, geen feitencontrole en geen automatische publicatie. De redactie beslist altijd zelf.</p></section>';
        return ob_get_clean();
    }
}

if ( ! function_exists( 'mvm_radar_suggestions_css_v1' ) ) {
    function mvm_radar_suggestions_css_v1() { return '.mvm-radar-suggestions-v1{--blue:#1966AE;margin:18px 0;padding:18px;border:1px solid var(--mvm-hub4-border,var(--mvm-border,#c5d3df));border-radius:14px;background:var(--mvm-hub4-surface,var(--mvm-surface,#fff));color:var(--mvm-hub4-text,inherit)}.mvm-radar-suggestions-v1>header{display:flex;justify-content:space-between;align-items:flex-start;gap:16px}.mvm-radar-suggestions-v1>header p{margin:0 0 4px;color:var(--blue);font-size:.78rem;font-weight:800;text-transform:uppercase}.mvm-radar-suggestions-v1>header h2{margin:0 0 5px}.mvm-radar-suggestions-v1>header span,.mvm-radar-suggestions-v1__copy p,.mvm-radar-suggestions-v1__copy small,.mvm-radar-suggestions-v1__note{color:var(--mvm-hub4-muted,var(--mvm-muted,#46596b))}.mvm-radar-suggestions-v1>header>strong{padding:6px 9px;border-radius:999px;background:#eaf3fb;color:#124f88;white-space:nowrap}.mvm-radar-suggestions-v1__list{display:grid;gap:9px;margin-top:16px}.mvm-radar-suggestions-v1__list article{display:grid;grid-template-columns:88px 1fr;gap:12px;padding:12px;border:1px solid var(--mvm-hub4-border,var(--mvm-border,#c5d3df));border-radius:10px}.mvm-radar-suggestions-v1__score strong,.mvm-radar-suggestions-v1__score span{display:block}.mvm-radar-suggestions-v1__score strong{font-size:1.35rem;color:var(--blue)}.mvm-radar-suggestions-v1__score span{margin-top:3px;font-size:.75rem;font-weight:700}.mvm-radar-suggestions-v1__copy h3{margin:0 0 5px;font-size:1rem}.mvm-radar-suggestions-v1__copy p{margin:0 0 3px;font-size:.88rem}.mvm-radar-suggestions-v1__copy small{display:block}.mvm-radar-suggestions-v1__note{margin:14px 0 0;font-size:.88rem}@media(max-width:620px){.mvm-radar-suggestions-v1>header{flex-direction:column}.mvm-radar-suggestions-v1__list article{grid-template-columns:1fr}.mvm-radar-suggestions-v1__score{display:flex;gap:8px;align-items:center}.mvm-radar-suggestions-v1__score span{margin:0}}'; }
}

add_shortcode( 'mvm_nieuwsradar_suggesties_v1', static function () {
    if ( ! is_user_logged_in() || ! ( current_user_can( 'edit_posts' ) || current_user_can( 'mvm_access_hub' ) || current_user_can( 'manage_options' ) ) ) { return ''; }
    return mvm_radar_suggestions_markup_v1( false ) . '<style id="mvm-radar-suggestions-v1-css">' . mvm_radar_suggestions_css_v1() . '</style>';
} );

add_action( 'template_redirect', static function () {
    if ( is_admin() || wp_doing_ajax() ) { return; }
    $path = trim( (string) wp_parse_url( isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '', PHP_URL_PATH ), '/' );
    if ( ! preg_match( '#^hub4?(?:/|$)#', $path ) ) { return; }
    ob_start( static function ( $html ) {
        if ( false !== strpos( $html, 'id="mvm-hub-radar-suggestions-v1"' ) ) { return $html; }
        if ( false === strpos( $html, 'class="mvm-hub4"' ) && false === strpos( $html, "class='mvm-hub4'" ) ) { return $html; }
        $markup = str_replace( '<section class="mvm-radar-suggestions-v1 is-compact"', '<section id="mvm-hub-radar-suggestions-v1" class="mvm-radar-suggestions-v1 is-compact"', mvm_radar_suggestions_markup_v1( true ) );
        $style = '<style id="mvm-radar-suggestions-v1-css">' . mvm_radar_suggestions_css_v1() . '</style>';
        $head = stripos( $html, '</head>' );
        if ( false !== $head ) { $html = substr_replace( $html, $style . '</head>', $head, 7 ); } else { $markup = $style . $markup; }
        $main = strripos( $html, '</main>' );
        if ( false !== $main ) { return substr_replace( $html, $markup . '</main>', $main, 7 ); }
        $body = strripos( $html, '</body>' );
        return false !== $body ? substr_replace( $html, $markup . '</body>', $body, 7 ) : $html . $markup;
    } );
}, -1300 );