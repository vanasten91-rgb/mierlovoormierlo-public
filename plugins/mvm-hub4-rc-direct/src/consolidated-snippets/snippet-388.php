<?php
// Consolidated from production Code Snippet #388.
defined( 'ABSPATH' ) || exit;

function mvm_hub4_contrast_rules_v2() {
    return <<<'CSS'
body.mvm-hub4,.mvm-hub4{--blue:#1966AE;--blue-dark:#124f88;--surface:#fff;--surface-alt:#f6f9fc;--border:#c5d3df;--text:#17222d;--muted:#46596b;--mvm-hub4-blue:var(--blue);--mvm-hub4-blue-dark:var(--blue-dark);--mvm-hub4-surface:var(--surface);--mvm-hub4-soft:var(--surface-alt);--mvm-hub4-border:var(--border);--mvm-hub4-text:var(--text);--mvm-hub4-muted:var(--muted);--mvm-hub4-link:#1966AE;color:var(--text);color-scheme:light}.mvm-hub4 a:not(.mvm-hub4__button):not(.mvm-hub4__nav-item):not(.mvm-hub4__platform-button):not(.mvm-news-radar__review-button){color:var(--mvm-hub4-link)}.mvm-hub4 input::placeholder,.mvm-hub4 textarea::placeholder{color:#607284;opacity:1}.mvm-hub4 button:disabled,.mvm-hub4 input:disabled,.mvm-hub4 select:disabled,.mvm-hub4 textarea:disabled{opacity:.72}.mvm-hub4 .mvm-hub4__platform-toolbar p,.mvm-hub4 .mvm-hub4__platform-card p,.mvm-hub4 .mvm-hub4__platform-row small,.mvm-hub4 .mvm-hub4__platform-row p,.mvm-hub4 .mvm-hub4__platform-empty,.mvm-hub4 .mvm-hub4__platform-message{color:var(--muted)!important}.mvm-hub4 .mvm-hub4__platform-card,.mvm-hub4 .mvm-hub4__platform-row,.mvm-hub4 .mvm-hub4__platform-table{background:var(--surface)!important;color:var(--text)!important;border-color:var(--border)!important}.mvm-hub4 .mvm-hub4__platform-form{background:var(--surface-alt)!important;border-color:var(--border)!important;color:var(--text)!important}.mvm-hub4 .mvm-hub4__platform-form label,.mvm-hub4 .mvm-hub4__platform-card h3,.mvm-hub4 .mvm-hub4__platform-row strong,.mvm-hub4 .mvm-hub4__platform-table td{color:var(--text)!important}.mvm-hub4 .mvm-hub4__platform-form input,.mvm-hub4 .mvm-hub4__platform-form select,.mvm-hub4 .mvm-hub4__platform-form textarea{background:var(--surface)!important;color:var(--text)!important;border-color:#8299ad!important}.mvm-hub4 .mvm-hub4__platform-button--secondary{background:var(--surface)!important;color:var(--mvm-hub4-link)!important}.mvm-hub4 .mvm-hub4__platform-row,.mvm-hub4 .mvm-hub4__platform-table th,.mvm-hub4 .mvm-hub4__platform-table td{border-color:var(--border)!important}.mvm-hub4 .mvm-news-radar__toolbar p,.mvm-hub4 .mvm-news-radar__metric span,.mvm-hub4 .mvm-news-radar__metric small,.mvm-hub4 .mvm-news-radar__sources-head p,.mvm-hub4 .mvm-news-radar__section-title p,.mvm-hub4 .mvm-news-radar__sort span,.mvm-hub4 .mvm-news-radar__source-main small,.mvm-hub4 .mvm-news-radar__source-dates dt,.mvm-hub4 .mvm-news-radar__card small,.mvm-hub4 .mvm-news-radar__update small,.mvm-hub4 .mvm-news-radar__loading,.mvm-hub4 .mvm-news-radar__empty{color:var(--muted)!important}.mvm-hub4 .mvm-news-radar__metric,.mvm-hub4 .mvm-news-radar__card,.mvm-hub4 .mvm-news-radar__sources,.mvm-hub4 .mvm-news-radar__sort select{background:var(--surface)!important;color:var(--text)!important;border-color:var(--border)!important}.mvm-hub4 .mvm-news-radar__source-card,.mvm-hub4 .mvm-news-radar__update,.mvm-hub4 .mvm-news-radar__badge{background:var(--surface-alt);color:var(--text);border-color:var(--border)}.mvm-hub4 .mvm-news-radar__source-link,.mvm-hub4 .mvm-news-radar__history summary{color:var(--mvm-hub4-link)!important}@media(prefers-color-scheme:dark){body.mvm-hub4,.mvm-hub4{--surface:#18222d;--surface-alt:#111923;--border:#405467;--text:#f5f8fb;--muted:#c9d5e1;--blue-soft:#173858;--mvm-hub4-link:#6DB8F2;background:#111923!important;color:#f5f8fb!important;color-scheme:dark}.mvm-hub4 input::placeholder,.mvm-hub4 textarea::placeholder{color:#aebdca}.mvm-hub4 .mvm-news-radar__badge--priority-a{background:#153756!important;color:#dceeff!important;border-color:#4b83ad!important}.mvm-hub4 .mvm-news-radar__badge--priority-b{background:#4a3612!important;color:#ffe2a8!important;border-color:#8f6a20!important}.mvm-hub4 .mvm-news-radar__badge--priority-c,.mvm-hub4 .mvm-news-radar__badge--inactive{background:#2b3946!important;color:#d7e1ea!important;border-color:#607284!important}.mvm-hub4 .mvm-news-radar__badge--active,.mvm-hub4 .mvm-news-radar__badge--reviewed{background:#183c28!important;color:#c9f0d4!important;border-color:#4e8a61!important}}body.mvm-hub4[data-theme=dark],html[data-theme=dark] body.mvm-hub4{--surface:#18222d;--surface-alt:#111923;--border:#405467;--text:#f5f8fb;--muted:#c9d5e1;--blue-soft:#173858;--mvm-hub4-link:#6DB8F2;background:#111923!important;color:#f5f8fb!important;color-scheme:dark}body.mvm-hub4[data-theme=light],html[data-theme=light] body.mvm-hub4{--surface:#fff;--surface-alt:#f6f9fc;--border:#c5d3df;--text:#17222d;--muted:#46596b;--blue-soft:#eaf3fb;--mvm-hub4-link:#1966AE;background:#eef3f8!important;color:#17222d!important;color-scheme:light}
CSS;
}

add_action( 'template_redirect', static function () {
    $path = (string) wp_parse_url( isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '', PHP_URL_PATH );
    if ( '/mvm-hub4-contrast-v1.css' !== $path ) {
        return;
    }
    nocache_headers();
    status_header( 200 );
    header( 'Content-Type: text/css; charset=UTF-8' );
    header( 'X-Content-Type-Options: nosniff' );
    echo mvm_hub4_contrast_rules_v2(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    exit;
}, -2000 );

add_action( 'template_redirect', static function () {
    if ( is_admin() || wp_doing_ajax() ) {
        return;
    }
    $path = trim( (string) wp_parse_url( isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '', PHP_URL_PATH ), '/' );
    if ( ! preg_match( '#^hub4?(?:/|$)#', $path ) ) {
        return;
    }
    ob_start( static function ( $html ) {
        if ( false === strpos( $html, 'class="mvm-hub4"' ) || false !== strpos( $html, 'id="mvm-hub4-contrast-link-v1"' ) ) {
            return $html;
        }
        $href = esc_url( home_url( '/mvm-hub4-contrast-v1.css?ver=2' ) );
        $link = '<link id="mvm-hub4-contrast-link-v1" rel="stylesheet" href="' . $href . '">';
        $pos = stripos( $html, '</head>' );
        return false !== $pos ? substr( $html, 0, $pos ) . $link . substr( $html, $pos ) : $html;
    } );
}, -1000 );