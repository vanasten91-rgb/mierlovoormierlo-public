<?php
// Consolidated from production Code Snippet #499.
defined( 'ABSPATH' ) || exit;

add_action( 'template_redirect', static function () {
    if ( is_admin() || wp_doing_ajax() ) { return; }
    $path = trim( (string) wp_parse_url( isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '', PHP_URL_PATH ), '/' );
    if ( ! preg_match( '#^hub4?(?:/|$)#', $path ) ) { return; }

    ob_start( static function ( $html ) {
        if ( false === strpos( $html, 'class="mvm-hub4"' ) && false === strpos( $html, "class='mvm-hub4'" ) ) { return $html; }
        if ( false !== strpos( $html, 'id="mvm-hub4-light-contrast-lock-v1"' ) ) { return $html; }

        $css = <<<'CSS'
<style id="mvm-hub4-light-contrast-lock-v1">
body.mvm-hub4[data-theme="light"],
html[data-theme="light"] body.mvm-hub4{
  --surface:#fff;--surface-alt:#f6f9fc;--border:#c5d3df;--text:#17222d;--muted:#46596b;--mvm-hub4-link:#1966AE;
  background:#eef3f8!important;color:#17222d!important;color-scheme:light;
}
body.mvm-hub4[data-theme="light"] h1,body.mvm-hub4[data-theme="light"] h2,body.mvm-hub4[data-theme="light"] h3,body.mvm-hub4[data-theme="light"] h4,body.mvm-hub4[data-theme="light"] h5,body.mvm-hub4[data-theme="light"] h6,body.mvm-hub4[data-theme="light"] label,body.mvm-hub4[data-theme="light"] legend,body.mvm-hub4[data-theme="light"] strong,body.mvm-hub4[data-theme="light"] th,body.mvm-hub4[data-theme="light"] td,body.mvm-hub4[data-theme="light"] summary,
html[data-theme="light"] body.mvm-hub4 h1,html[data-theme="light"] body.mvm-hub4 h2,html[data-theme="light"] body.mvm-hub4 h3,html[data-theme="light"] body.mvm-hub4 h4,html[data-theme="light"] body.mvm-hub4 h5,html[data-theme="light"] body.mvm-hub4 h6,html[data-theme="light"] body.mvm-hub4 label,html[data-theme="light"] body.mvm-hub4 legend,html[data-theme="light"] body.mvm-hub4 strong,html[data-theme="light"] body.mvm-hub4 th,html[data-theme="light"] body.mvm-hub4 td,html[data-theme="light"] body.mvm-hub4 summary{color:#17222d!important;-webkit-text-fill-color:#17222d!important}
body.mvm-hub4[data-theme="light"] p,body.mvm-hub4[data-theme="light"] small,body.mvm-hub4[data-theme="light"] dt,body.mvm-hub4[data-theme="light"] dd,body.mvm-hub4[data-theme="light"] .description,body.mvm-hub4[data-theme="light"] .meta,
html[data-theme="light"] body.mvm-hub4 p,html[data-theme="light"] body.mvm-hub4 small,html[data-theme="light"] body.mvm-hub4 dt,html[data-theme="light"] body.mvm-hub4 dd,html[data-theme="light"] body.mvm-hub4 .description,html[data-theme="light"] body.mvm-hub4 .meta{color:#46596b!important;-webkit-text-fill-color:#46596b!important}
body.mvm-hub4[data-theme="light"] input,body.mvm-hub4[data-theme="light"] textarea,body.mvm-hub4[data-theme="light"] select,
html[data-theme="light"] body.mvm-hub4 input,html[data-theme="light"] body.mvm-hub4 textarea,html[data-theme="light"] body.mvm-hub4 select{background:#fff!important;color:#17222d!important;-webkit-text-fill-color:#17222d!important;border-color:#8299ad!important}
body.mvm-hub4[data-theme="light"] input::placeholder,body.mvm-hub4[data-theme="light"] textarea::placeholder,
html[data-theme="light"] body.mvm-hub4 input::placeholder,html[data-theme="light"] body.mvm-hub4 textarea::placeholder{color:#607284!important;-webkit-text-fill-color:#607284!important;opacity:1!important}
body.mvm-hub4[data-theme="light"] a:not(.mvm-hub4__button):not(.mvm-hub4__nav-item):not(.mvm-hub4__platform-button):not(.mvm-news-radar__review-button),
html[data-theme="light"] body.mvm-hub4 a:not(.mvm-hub4__button):not(.mvm-hub4__nav-item):not(.mvm-hub4__platform-button):not(.mvm-news-radar__review-button){color:#1966AE!important;-webkit-text-fill-color:#1966AE!important}
body.mvm-hub4[data-theme="light"] .mvm-hub4__button,body.mvm-hub4[data-theme="light"] .mvm-hub4__button *,body.mvm-hub4[data-theme="light"] .mvm-hub4__platform-button:not(.mvm-hub4__platform-button--secondary),body.mvm-hub4[data-theme="light"] .mvm-hub4__platform-button:not(.mvm-hub4__platform-button--secondary) *,body.mvm-hub4[data-theme="light"] .mvm-news-radar__review-button,body.mvm-hub4[data-theme="light"] .mvm-news-radar__review-button *,
html[data-theme="light"] body.mvm-hub4 .mvm-hub4__button,html[data-theme="light"] body.mvm-hub4 .mvm-hub4__button *,html[data-theme="light"] body.mvm-hub4 .mvm-hub4__platform-button:not(.mvm-hub4__platform-button--secondary),html[data-theme="light"] body.mvm-hub4 .mvm-hub4__platform-button:not(.mvm-hub4__platform-button--secondary) *,html[data-theme="light"] body.mvm-hub4 .mvm-news-radar__review-button,html[data-theme="light"] body.mvm-hub4 .mvm-news-radar__review-button *{color:#fff!important;-webkit-text-fill-color:#fff!important}
body.mvm-hub4[data-theme="light"] .mvm-hub4__platform-button--secondary,html[data-theme="light"] body.mvm-hub4 .mvm-hub4__platform-button--secondary{background:#fff!important;color:#1966AE!important;-webkit-text-fill-color:#1966AE!important}
</style>
CSS;
        $pos = stripos( $html, '</head>' );
        return false !== $pos ? substr_replace( $html, $css . '</head>', $pos, 7 ) : $css . $html;
    } );
}, -900 );