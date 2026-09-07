<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Explicit MvM visual contract for encyclopedia dossier/detail surfaces.
 * This is presentation-only and never mutates encyclopedia content or meta.
 */
final class MvM_Hub4_Encyclopedia_Detail_Theme {
    public static function init(): void {
        add_action( 'wp_head', array( __CLASS__, 'render_css' ), PHP_INT_MAX );
    }

    public static function render_css(): void {
        if ( ! self::is_encyclopedia_request() ) {
            return;
        }
        ?>
        <style id="mvm-hub4-encyclopedia-detail-theme">
        :root{
            --mvm-ency-blue:#1966AE;
            --mvm-ency-blue-dark:#124f88;
            --mvm-ency-ink:#17202a;
            --mvm-ency-muted:#465b6c;
            --mvm-ency-white:#fff;
            --mvm-ency-grey:#eef3f8;
            --mvm-ency-border:#d2dee8;
        }
        main article:has(.mvm-dossier-content),
        main article:has(.mvm-facts-card){
            color:var(--mvm-ency-ink)!important;
            -webkit-text-fill-color:var(--mvm-ency-ink)!important;
        }
        main article:has(.mvm-dossier-content)>header h1,
        main article:has(.mvm-facts-card)>header h1{
            color:var(--mvm-ency-ink)!important;
            -webkit-text-fill-color:var(--mvm-ency-ink)!important;
            text-transform:none!important;
        }
        .mvm-dossier-breadcrumb,
        .mvm-secondary-themes,
        .mvm-facts-card,
        .mvm-anchor-toc,
        .mvm-dossier-neighbors,
        .mvm-related,
        .mvm-see-also,
        .mvm-back-to-top{
            background:var(--mvm-ency-white)!important;
            color:var(--mvm-ency-ink)!important;
            -webkit-text-fill-color:var(--mvm-ency-ink)!important;
            border:1px solid var(--mvm-ency-border)!important;
            border-radius:12px!important;
            box-shadow:0 5px 16px rgba(23,32,42,.05)!important;
        }
        .mvm-dossier-breadcrumb,
        .mvm-secondary-themes,
        .mvm-facts-card,
        .mvm-anchor-toc,
        .mvm-dossier-neighbors,
        .mvm-related,
        .mvm-see-also{
            padding:16px 18px!important;
            margin-block:16px!important;
        }
        .mvm-facts-card .mvm-eyebrow,
        .mvm-breadcrumb-heading,
        .mvm-neighbor-label{
            color:var(--mvm-ency-blue)!important;
            -webkit-text-fill-color:var(--mvm-ency-blue)!important;
            font-weight:800!important;
        }
        .mvm-facts-card dl>div,
        .mvm-anchor-toc details,
        .mvm-dossier-neighbors>div{
            background:var(--mvm-ency-grey)!important;
            color:var(--mvm-ency-ink)!important;
            -webkit-text-fill-color:var(--mvm-ency-ink)!important;
            border-color:var(--mvm-ency-border)!important;
        }
        .mvm-dossier-content,
        .mvm-dossier-content p,
        .mvm-dossier-content li,
        .mvm-dossier-content strong,
        .mvm-dossier-content h2,
        .mvm-dossier-content h3,
        .mvm-dossier-content h4,
        .mvm-related h2,
        .mvm-related h3,
        .mvm-see-also h2,
        .mvm-see-also h3{
            color:var(--mvm-ency-ink)!important;
            -webkit-text-fill-color:var(--mvm-ency-ink)!important;
            text-transform:none!important;
        }
        .mvm-dossier-content a,
        .mvm-dossier-breadcrumb a,
        .mvm-secondary-themes a,
        .mvm-anchor-toc a,
        .mvm-dossier-neighbors a,
        .mvm-related a,
        .mvm-see-also a,
        .mvm-back-to-top a,
        .mvm-heading-anchor{
            color:var(--mvm-ency-blue)!important;
            -webkit-text-fill-color:var(--mvm-ency-blue)!important;
            text-decoration-thickness:1px!important;
            text-underline-offset:2px!important;
        }
        .mvm-dossier-neighbors a{
            background:var(--mvm-ency-grey)!important;
            border:1px solid var(--mvm-ency-border)!important;
            border-radius:9px!important;
        }
        .mvm-dossier-content a:focus-visible,
        .mvm-dossier-breadcrumb a:focus-visible,
        .mvm-anchor-toc a:focus-visible,
        .mvm-dossier-neighbors a:focus-visible,
        .mvm-related a:focus-visible,
        .mvm-back-to-top a:focus-visible,
        .mvm-anchor-toc summary:focus-visible{
            outline:3px solid #f4a722!important;
            outline-offset:3px!important;
        }
        body.mvm-header-dark main article:has(.mvm-dossier-content),
        body.mvm-header-dark main article:has(.mvm-facts-card),
        html[data-theme="dark"] main article:has(.mvm-dossier-content),
        html[data-theme="dark"] main article:has(.mvm-facts-card){
            color:#f5f7fa!important;
            -webkit-text-fill-color:#f5f7fa!important;
        }
        body.mvm-header-dark main article:has(.mvm-dossier-content)>header h1,
        body.mvm-header-dark main article:has(.mvm-facts-card)>header h1,
        html[data-theme="dark"] main article:has(.mvm-dossier-content)>header h1,
        html[data-theme="dark"] main article:has(.mvm-facts-card)>header h1{
            color:#f5f7fa!important;
            -webkit-text-fill-color:#f5f7fa!important;
        }
        body.mvm-header-dark :where(.mvm-dossier-breadcrumb,.mvm-secondary-themes,.mvm-facts-card,.mvm-anchor-toc,.mvm-dossier-neighbors,.mvm-related,.mvm-see-also,.mvm-back-to-top),
        html[data-theme="dark"] :where(.mvm-dossier-breadcrumb,.mvm-secondary-themes,.mvm-facts-card,.mvm-anchor-toc,.mvm-dossier-neighbors,.mvm-related,.mvm-see-also,.mvm-back-to-top){
            background:#17222d!important;
            color:#f5f7fa!important;
            -webkit-text-fill-color:#f5f7fa!important;
            border-color:#344757!important;
        }
        body.mvm-header-dark :where(.mvm-facts-card dl>div,.mvm-anchor-toc details,.mvm-dossier-neighbors>div,.mvm-dossier-neighbors a),
        html[data-theme="dark"] :where(.mvm-facts-card dl>div,.mvm-anchor-toc details,.mvm-dossier-neighbors>div,.mvm-dossier-neighbors a){
            background:#253442!important;
            color:#f5f7fa!important;
            -webkit-text-fill-color:#f5f7fa!important;
            border-color:#42576a!important;
        }
        body.mvm-header-dark :where(.mvm-dossier-content,.mvm-dossier-content p,.mvm-dossier-content li,.mvm-dossier-content strong,.mvm-dossier-content h2,.mvm-dossier-content h3,.mvm-dossier-content h4,.mvm-related h2,.mvm-related h3,.mvm-see-also h2,.mvm-see-also h3),
        html[data-theme="dark"] :where(.mvm-dossier-content,.mvm-dossier-content p,.mvm-dossier-content li,.mvm-dossier-content strong,.mvm-dossier-content h2,.mvm-dossier-content h3,.mvm-dossier-content h4,.mvm-related h2,.mvm-related h3,.mvm-see-also h2,.mvm-see-also h3){
            color:#f5f7fa!important;
            -webkit-text-fill-color:#f5f7fa!important;
        }
        body.mvm-header-dark :where(.mvm-dossier-content a,.mvm-dossier-breadcrumb a,.mvm-secondary-themes a,.mvm-anchor-toc a,.mvm-dossier-neighbors a,.mvm-related a,.mvm-see-also a,.mvm-back-to-top a,.mvm-heading-anchor),
        html[data-theme="dark"] :where(.mvm-dossier-content a,.mvm-dossier-breadcrumb a,.mvm-secondary-themes a,.mvm-anchor-toc a,.mvm-dossier-neighbors a,.mvm-related a,.mvm-see-also a,.mvm-back-to-top a,.mvm-heading-anchor){
            color:#8bc7fb!important;
            -webkit-text-fill-color:#8bc7fb!important;
        }
        @media(max-width:767px){
            .mvm-dossier-breadcrumb,.mvm-secondary-themes,.mvm-facts-card,.mvm-anchor-toc,.mvm-dossier-neighbors,.mvm-related,.mvm-see-also{padding:13px 14px!important;border-radius:10px!important}
            .mvm-dossier-neighbors>div{display:grid!important;grid-template-columns:1fr!important;gap:10px!important}
        }
        </style>
        <?php
    }

    private static function is_encyclopedia_request(): bool {
        $path = untrailingslashit( (string) wp_parse_url( (string) ( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH ) );
        $root = untrailingslashit( (string) wp_parse_url( home_url( '/encyclopedie/' ), PHP_URL_PATH ) );
        return '' !== $root && ( $path === $root || str_starts_with( $path, $root . '/' ) );
    }
}
