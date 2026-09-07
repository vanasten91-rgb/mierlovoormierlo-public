<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Hub 1/2 inspired visual contract for the complete public MvM encyclopedia.
 * Presentation only: encyclopedia content, relations, timelines and metadata remain untouched.
 */
final class MvM_Hub4_Encyclopedia_Theme {
    public static function init(): void {
        add_action( 'wp_head', array( __CLASS__, 'render_css' ), PHP_INT_MAX );
    }

    public static function render_css(): void {
        if ( ! self::is_encyclopedia_request() ) {
            return;
        }
        ?>
        <style id="mvm-hub4-encyclopedia-theme">
        :root{
            --mvm-ency-blue:#1966AE;
            --mvm-ency-blue-dark:#124f88;
            --mvm-ency-blue-soft:#eaf3fb;
            --mvm-ency-ink:#17202a;
            --mvm-ency-muted:#526575;
            --mvm-ency-white:#fff;
            --mvm-ency-soft:#f4f7fa;
            --mvm-ency-border:#d5e0e9;
            --mvm-ency-focus:#f4a722;
            --mvm-ency-radius:18px;
            --mvm-ency-shadow:0 10px 28px rgba(24,49,72,.08);
        }

        /* Shared encyclopedia canvas: overview, theme pages and all taxonomy-style chapters. */
        .mvm-public-home,
        main.mvm-page,
        #content.mvm-page{
            width:min(100% - 32px,1152px)!important;
            max-width:1152px!important;
            margin-inline:auto!important;
            box-sizing:border-box!important;
            color:var(--mvm-ency-ink)!important;
            -webkit-text-fill-color:var(--mvm-ency-ink)!important;
        }
        .mvm-public-home{padding:0 0 42px!important}
        main.mvm-page .mvm-page-inner,
        #content.mvm-page .mvm-page-inner{max-width:none!important;width:100%!important}
        main.mvm-page .mvm-page-card,
        #content.mvm-page .mvm-page-card{
            background:transparent!important;
            border:0!important;
            box-shadow:none!important;
            padding:0!important;
        }

        /* Hub 1/2 style blue masthead. */
        .mvm-public-home .mvm-hero,
        .mvm-page .mvm-page-head,
        .mvm-themes-index .mvm-theme-intro{
            position:relative!important;
            overflow:hidden!important;
            margin:0 0 22px!important;
            padding:clamp(26px,4vw,46px)!important;
            border:0!important;
            border-radius:20px!important;
            background:linear-gradient(135deg,var(--mvm-ency-blue) 0%,var(--mvm-ency-blue-dark) 100%)!important;
            color:#fff!important;
            -webkit-text-fill-color:#fff!important;
            box-shadow:0 14px 34px rgba(18,79,136,.2)!important;
        }
        .mvm-public-home .mvm-hero::after,
        .mvm-page .mvm-page-head::after,
        .mvm-themes-index .mvm-theme-intro::after{
            content:"";
            position:absolute;
            width:220px;
            height:220px;
            right:-72px;
            top:-90px;
            border-radius:50%;
            background:rgba(255,255,255,.09);
            pointer-events:none;
        }
        .mvm-public-home .mvm-hero *,
        .mvm-page .mvm-page-head *,
        .mvm-themes-index .mvm-theme-intro *{
            color:#fff!important;
            -webkit-text-fill-color:#fff!important;
        }
        .mvm-public-home .mvm-hero h1,
        .mvm-page .mvm-page-head h1,
        .mvm-themes-index .mvm-theme-intro h2{
            margin:.15em 0 .35em!important;
            font-size:clamp(2rem,4vw,3.05rem)!important;
            line-height:1.08!important;
            letter-spacing:-.025em!important;
            text-transform:none!important;
        }
        .mvm-public-home .mvm-hero p,
        .mvm-themes-index .mvm-theme-intro>p{
            max-width:760px!important;
            font-size:1.05rem!important;
            line-height:1.65!important;
        }

        /* Search behaves as a single strong control inside the masthead. */
        .mvm-public-home .mvm-public-search{
            display:grid!important;
            grid-template-columns:minmax(0,1fr) auto!important;
            gap:10px!important;
            margin-top:20px!important;
        }
        .mvm-public-home .mvm-public-search input{
            min-height:50px!important;
            padding:0 16px!important;
            background:#fff!important;
            color:var(--mvm-ency-ink)!important;
            -webkit-text-fill-color:var(--mvm-ency-ink)!important;
            border:1px solid rgba(255,255,255,.55)!important;
            border-radius:12px!important;
            box-shadow:0 6px 18px rgba(0,0,0,.09)!important;
        }
        .mvm-public-home .mvm-public-search button,
        .mvm-public-home .mvm-primary-button{
            min-height:50px!important;
            padding-inline:20px!important;
            background:#fff!important;
            color:var(--mvm-ency-blue)!important;
            -webkit-text-fill-color:var(--mvm-ency-blue)!important;
            border:1px solid #fff!important;
            border-radius:12px!important;
            font-weight:800!important;
            box-shadow:0 6px 18px rgba(0,0,0,.09)!important;
        }

        /* Clear chapter menu: old Hub dashboard feeling, but semantic nav is preserved. */
        .mvm-public-home .mvm-entry-grid{
            display:grid!important;
            grid-template-columns:repeat(6,minmax(0,1fr))!important;
            gap:12px!important;
            margin:0 0 28px!important;
            padding:18px!important;
            border:1px solid var(--mvm-ency-border)!important;
            border-radius:var(--mvm-ency-radius)!important;
            background:var(--mvm-ency-soft)!important;
            box-shadow:var(--mvm-ency-shadow)!important;
        }
        .mvm-public-home .mvm-entry-grid::before{
            content:"Hoofdstukken";
            grid-column:1/-1;
            display:block;
            margin-bottom:2px;
            color:var(--mvm-ency-ink)!important;
            -webkit-text-fill-color:var(--mvm-ency-ink)!important;
            font-size:1.2rem;
            font-weight:850;
            letter-spacing:-.01em;
        }
        .mvm-public-home .mvm-entry-grid a{
            display:flex!important;
            align-items:center!important;
            justify-content:center!important;
            min-height:54px!important;
            padding:10px 12px!important;
            text-align:center!important;
            text-decoration:none!important;
            background:#fff!important;
            color:var(--mvm-ency-blue)!important;
            -webkit-text-fill-color:var(--mvm-ency-blue)!important;
            border:1px solid var(--mvm-ency-border)!important;
            border-radius:13px!important;
            box-shadow:0 5px 14px rgba(24,49,72,.05)!important;
            font-weight:800!important;
            transition:transform .16s ease,border-color .16s ease,box-shadow .16s ease!important;
        }
        .mvm-public-home .mvm-entry-grid a:hover,
        .mvm-public-home .mvm-entry-grid a:focus-visible{
            transform:translateY(-2px)!important;
            border-color:var(--mvm-ency-blue)!important;
            box-shadow:0 9px 18px rgba(25,102,174,.14)!important;
        }

        /* Section framing gives the overview a dashboard instead of one endless list. */
        .mvm-public-home .mvm-public-section{
            margin:28px 0 0!important;
            padding:20px!important;
            background:#fff!important;
            color:var(--mvm-ency-ink)!important;
            -webkit-text-fill-color:var(--mvm-ency-ink)!important;
            border:1px solid var(--mvm-ency-border)!important;
            border-radius:var(--mvm-ency-radius)!important;
            box-shadow:var(--mvm-ency-shadow)!important;
        }
        .mvm-public-home .mvm-section-head{
            display:flex!important;
            align-items:end!important;
            justify-content:space-between!important;
            gap:18px!important;
            margin:0 0 16px!important;
            padding:0 0 13px!important;
            border-bottom:1px solid var(--mvm-ency-border)!important;
        }
        .mvm-public-home .mvm-section-head h2{
            margin:0!important;
            color:var(--mvm-ency-ink)!important;
            -webkit-text-fill-color:var(--mvm-ency-ink)!important;
            font-size:clamp(1.45rem,2.5vw,2rem)!important;
            letter-spacing:-.015em!important;
        }
        .mvm-public-home .mvm-section-head span{
            color:var(--mvm-ency-muted)!important;
            -webkit-text-fill-color:var(--mvm-ency-muted)!important;
        }

        /* Featured dossiers: rounded editorial cards. */
        .mvm-public-home .mvm-card-grid{
            display:grid!important;
            grid-template-columns:repeat(3,minmax(0,1fr))!important;
            gap:16px!important;
        }
        .mvm-public-home .mvm-public-card{
            overflow:hidden!important;
            background:#fff!important;
            color:var(--mvm-ency-ink)!important;
            -webkit-text-fill-color:var(--mvm-ency-ink)!important;
            border:1px solid var(--mvm-ency-border)!important;
            border-radius:16px!important;
            box-shadow:0 7px 20px rgba(24,49,72,.07)!important;
            transition:transform .16s ease,box-shadow .16s ease!important;
        }
        .mvm-public-home .mvm-public-card:hover{
            transform:translateY(-3px)!important;
            box-shadow:0 12px 26px rgba(25,102,174,.13)!important;
        }
        .mvm-public-home .mvm-card-link{text-decoration:none!important}
        .mvm-public-home .mvm-card-body{padding:16px!important}
        .mvm-public-home .mvm-card-body,
        .mvm-public-home .mvm-card-body p,
        .mvm-public-home .mvm-card-body h3{
            color:var(--mvm-ency-ink)!important;
            -webkit-text-fill-color:var(--mvm-ency-ink)!important;
        }
        .mvm-public-home .mvm-card-body h3{margin:0 0 8px!important;font-size:1.08rem!important;line-height:1.3!important}
        .mvm-public-home .mvm-card-body p{font-size:.94rem!important;line-height:1.55!important}
        .mvm-public-home .mvm-card-body a,
        .mvm-public-home .mvm-read-more{
            color:var(--mvm-ency-blue)!important;
            -webkit-text-fill-color:var(--mvm-ency-blue)!important;
            font-weight:750!important;
        }
        .mvm-public-home .mvm-card-meta{
            color:var(--mvm-ency-muted)!important;
            -webkit-text-fill-color:var(--mvm-ency-muted)!important;
            font-size:.84rem!important;
        }
        .mvm-public-home .mvm-card-image{
            width:100%!important;
            aspect-ratio:16/10!important;
            height:auto!important;
            object-fit:cover!important;
            border-radius:0!important;
        }

        /* A-Z becomes a true letter dashboard, not a scrolling document. */
        .mvm-public-home .mvm-az-bundled{
            display:grid!important;
            gap:14px!important;
        }
        .mvm-public-home .mvm-az-nav{
            display:grid!important;
            grid-template-columns:repeat(13,minmax(36px,1fr))!important;
            gap:7px!important;
            position:sticky!important;
            top:8px!important;
            z-index:5!important;
            padding:10px!important;
            background:rgba(244,247,250,.96)!important;
            border:1px solid var(--mvm-ency-border)!important;
            border-radius:14px!important;
            backdrop-filter:blur(8px)!important;
        }
        .mvm-public-home .mvm-az-nav a{
            display:grid!important;
            place-items:center!important;
            min-height:38px!important;
            padding:4px!important;
            background:#fff!important;
            color:var(--mvm-ency-blue)!important;
            -webkit-text-fill-color:var(--mvm-ency-blue)!important;
            border:1px solid var(--mvm-ency-border)!important;
            border-radius:9px!important;
            text-decoration:none!important;
            font-weight:850!important;
            box-shadow:0 3px 9px rgba(24,49,72,.04)!important;
        }
        .mvm-public-home .mvm-az-nav a:hover,
        .mvm-public-home .mvm-az-nav a:focus-visible{
            background:var(--mvm-ency-blue)!important;
            color:#fff!important;
            -webkit-text-fill-color:#fff!important;
            border-color:var(--mvm-ency-blue)!important;
        }
        .mvm-public-home .mvm-az-scroll{
            max-height:none!important;
            overflow:visible!important;
            border:0!important;
            background:transparent!important;
        }
        .mvm-public-home .mvm-scroll-hint{display:none!important}
        .mvm-public-home .mvm-az-letter{
            margin:0 0 10px!important;
            overflow:hidden!important;
            background:#fff!important;
            color:var(--mvm-ency-ink)!important;
            -webkit-text-fill-color:var(--mvm-ency-ink)!important;
            border:1px solid var(--mvm-ency-border)!important;
            border-radius:14px!important;
            box-shadow:0 5px 16px rgba(24,49,72,.055)!important;
        }
        .mvm-public-home .mvm-az-letter summary{
            display:flex!important;
            align-items:center!important;
            justify-content:space-between!important;
            gap:12px!important;
            padding:14px 16px!important;
            background:var(--mvm-ency-blue-soft)!important;
            color:var(--mvm-ency-ink)!important;
            -webkit-text-fill-color:var(--mvm-ency-ink)!important;
            border:0!important;
            border-radius:0!important;
            cursor:pointer!important;
            list-style:none!important;
        }
        .mvm-public-home .mvm-az-letter summary strong{
            display:grid!important;
            place-items:center!important;
            width:38px!important;
            height:38px!important;
            border-radius:10px!important;
            background:var(--mvm-ency-blue)!important;
            color:#fff!important;
            -webkit-text-fill-color:#fff!important;
            font-size:1.05rem!important;
        }
        .mvm-public-home .mvm-az-letter summary span{
            color:var(--mvm-ency-muted)!important;
            -webkit-text-fill-color:var(--mvm-ency-muted)!important;
            font-weight:700!important;
        }
        .mvm-public-home .mvm-az-letter-body{
            padding:14px 16px 16px!important;
            background:#fff!important;
            color:var(--mvm-ency-ink)!important;
            -webkit-text-fill-color:var(--mvm-ency-ink)!important;
        }
        .mvm-public-home .mvm-az-letter-body ul{
            display:grid!important;
            grid-template-columns:repeat(3,minmax(0,1fr))!important;
            gap:7px 14px!important;
            margin:0!important;
            padding:0!important;
            list-style:none!important;
        }
        .mvm-public-home .mvm-az-topic{
            margin:0!important;
            padding:8px 10px!important;
            border-radius:9px!important;
            background:var(--mvm-ency-soft)!important;
        }
        .mvm-public-home .mvm-az-letter-body a{
            color:var(--mvm-ency-blue)!important;
            -webkit-text-fill-color:var(--mvm-ency-blue)!important;
            font-weight:650!important;
            text-decoration:none!important;
        }

        /* Timeline: strong era navigation + clean vertical story cards. */
        .mvm-public-home .mvm-timeline-v2940{
            display:grid!important;
            gap:16px!important;
        }
        .mvm-public-home .mvm-timeline-toolbar{
            display:grid!important;
            grid-template-columns:1fr 1.25fr!important;
            gap:16px!important;
            padding:16px!important;
            background:var(--mvm-ency-soft)!important;
            border:1px solid var(--mvm-ency-border)!important;
            border-radius:15px!important;
        }
        .mvm-public-home .mvm-timeline-overview{
            display:grid!important;
            grid-template-columns:repeat(3,minmax(0,1fr))!important;
            gap:8px!important;
        }
        .mvm-public-home .mvm-timeline-overview span{
            display:grid!important;
            gap:2px!important;
            padding:10px!important;
            text-align:center!important;
            background:#fff!important;
            border:1px solid var(--mvm-ency-border)!important;
            border-radius:11px!important;
            color:var(--mvm-ency-muted)!important;
            -webkit-text-fill-color:var(--mvm-ency-muted)!important;
        }
        .mvm-public-home .mvm-timeline-overview strong{
            color:var(--mvm-ency-blue)!important;
            -webkit-text-fill-color:var(--mvm-ency-blue)!important;
            font-size:1.35rem!important;
        }
        .mvm-public-home .mvm-timeline-controls{display:grid!important;gap:10px!important}
        .mvm-public-home .mvm-timeline-search{display:grid!important;gap:5px!important;color:var(--mvm-ency-ink)!important;-webkit-text-fill-color:var(--mvm-ency-ink)!important;font-weight:750!important}
        .mvm-public-home .mvm-timeline-search input{
            min-height:44px!important;
            padding:0 13px!important;
            background:#fff!important;
            color:var(--mvm-ency-ink)!important;
            -webkit-text-fill-color:var(--mvm-ency-ink)!important;
            border:1px solid var(--mvm-ency-border)!important;
            border-radius:10px!important;
        }
        .mvm-public-home .mvm-timeline-actions{display:flex!important;flex-wrap:wrap!important;gap:7px!important}
        .mvm-public-home .mvm-timeline-action,
        .mvm-public-home .mvm-timeline-clear{
            min-height:38px!important;
            padding:7px 11px!important;
            background:#fff!important;
            color:var(--mvm-ency-blue)!important;
            -webkit-text-fill-color:var(--mvm-ency-blue)!important;
            border:1px solid var(--mvm-ency-border)!important;
            border-radius:9px!important;
            font-weight:750!important;
        }
        .mvm-public-home .mvm-timeline-era-nav{
            display:grid!important;
            grid-template-columns:repeat(3,minmax(0,1fr))!important;
            gap:9px!important;
        }
        .mvm-public-home .mvm-timeline-era-nav a{
            display:flex!important;
            align-items:center!important;
            justify-content:space-between!important;
            gap:8px!important;
            min-height:48px!important;
            padding:10px 12px!important;
            background:#fff!important;
            color:var(--mvm-ency-ink)!important;
            -webkit-text-fill-color:var(--mvm-ency-ink)!important;
            border:1px solid var(--mvm-ency-border)!important;
            border-radius:11px!important;
            text-decoration:none!important;
            box-shadow:0 4px 12px rgba(24,49,72,.045)!important;
        }
        .mvm-public-home .mvm-timeline-era-nav a strong{color:var(--mvm-ency-blue)!important;-webkit-text-fill-color:var(--mvm-ency-blue)!important}
        .mvm-public-home .mvm-timeline-era-nav a span{
            display:grid!important;
            place-items:center!important;
            min-width:30px!important;
            height:30px!important;
            border-radius:9px!important;
            background:var(--mvm-ency-blue-soft)!important;
            color:var(--mvm-ency-blue)!important;
            -webkit-text-fill-color:var(--mvm-ency-blue)!important;
            font-weight:850!important;
        }
        .mvm-public-home .mvm-timeline-era{
            overflow:hidden!important;
            margin:0 0 10px!important;
            background:#fff!important;
            border:1px solid var(--mvm-ency-border)!important;
            border-radius:15px!important;
            box-shadow:0 6px 18px rgba(24,49,72,.055)!important;
        }
        .mvm-public-home .mvm-timeline-era-summary{
            padding:15px 16px!important;
            background:linear-gradient(90deg,var(--mvm-ency-blue-soft),#fff)!important;
            color:var(--mvm-ency-ink)!important;
            -webkit-text-fill-color:var(--mvm-ency-ink)!important;
        }
        .mvm-public-home .mvm-timeline-era-dot{
            width:14px!important;
            height:14px!important;
            border-radius:50%!important;
            background:var(--mvm-ency-blue)!important;
            box-shadow:0 0 0 5px rgba(25,102,174,.12)!important;
        }
        .mvm-public-home .mvm-timeline-era-copy strong,
        .mvm-public-home .mvm-timeline-era-count strong{
            color:var(--mvm-ency-ink)!important;
            -webkit-text-fill-color:var(--mvm-ency-ink)!important;
        }
        .mvm-public-home .mvm-timeline-era-copy small,
        .mvm-public-home .mvm-timeline-era-count small{
            color:var(--mvm-ency-muted)!important;
            -webkit-text-fill-color:var(--mvm-ency-muted)!important;
        }
        .mvm-public-home .mvm-timeline-era-body{padding:4px 16px 16px!important;background:#fff!important}
        .mvm-public-home .mvm-timeline-track{
            position:relative!important;
            display:grid!important;
            gap:10px!important;
            margin-left:5px!important;
            padding:8px 0 4px 22px!important;
            border-left:3px solid #c8ddf0!important;
        }
        .mvm-public-home .mvm-timeline-entry{
            position:relative!important;
            display:grid!important;
            grid-template-columns:minmax(120px,180px) 1fr!important;
            gap:14px!important;
            padding:13px!important;
            background:var(--mvm-ency-soft)!important;
            border:1px solid var(--mvm-ency-border)!important;
            border-radius:12px!important;
        }
        .mvm-public-home .mvm-timeline-entry::before{
            content:"";
            position:absolute;
            left:-31px;
            top:18px;
            width:12px;
            height:12px;
            border-radius:50%;
            background:var(--mvm-ency-blue);
            border:3px solid #fff;
            box-shadow:0 0 0 2px var(--mvm-ency-blue);
        }
        .mvm-public-home .mvm-timeline-date time{
            display:inline-flex!important;
            padding:6px 9px!important;
            border-radius:8px!important;
            background:#fff!important;
            color:var(--mvm-ency-blue)!important;
            -webkit-text-fill-color:var(--mvm-ency-blue)!important;
            border:1px solid var(--mvm-ency-border)!important;
            font-weight:800!important;
        }
        .mvm-public-home .mvm-timeline-entry-content h3{margin:2px 0!important;font-size:1rem!important}
        .mvm-public-home .mvm-timeline-entry-content a{
            color:var(--mvm-ency-blue)!important;
            -webkit-text-fill-color:var(--mvm-ency-blue)!important;
            font-weight:700!important;
            text-decoration:none!important;
        }
        .mvm-public-home .mvm-timeline-entry-list{columns:2;column-gap:22px;margin:8px 0 0!important}
        .mvm-public-home .mvm-timeline-entry-list li{break-inside:avoid;margin:0 0 5px!important}

        /* Theme / chapter overview: clear bundle cards and chapter menu. */
        .mvm-themes-index{display:grid!important;gap:18px!important}
        .mvm-themes-index .mvm-theme-summary{
            display:grid!important;
            grid-template-columns:repeat(4,minmax(0,1fr))!important;
            gap:9px!important;
            margin-top:18px!important;
        }
        .mvm-themes-index .mvm-theme-summary span{
            display:grid!important;
            gap:2px!important;
            padding:10px!important;
            background:rgba(255,255,255,.12)!important;
            border:1px solid rgba(255,255,255,.22)!important;
            border-radius:11px!important;
            text-align:center!important;
        }
        .mvm-themes-index .mvm-theme-summary strong{font-size:1.3rem!important}
        .mvm-themes-index .mvm-theme-jump{
            display:grid!important;
            grid-template-columns:repeat(5,minmax(0,1fr))!important;
            gap:10px!important;
            padding:16px!important;
            background:var(--mvm-ency-soft)!important;
            border:1px solid var(--mvm-ency-border)!important;
            border-radius:16px!important;
            box-shadow:var(--mvm-ency-shadow)!important;
        }
        .mvm-themes-index .mvm-theme-jump>strong{grid-column:1/-1;color:var(--mvm-ency-ink)!important;-webkit-text-fill-color:var(--mvm-ency-ink)!important;font-size:1.1rem!important}
        .mvm-themes-index .mvm-theme-jump a{
            display:grid!important;
            gap:5px!important;
            padding:12px!important;
            background:#fff!important;
            color:var(--mvm-ency-ink)!important;
            -webkit-text-fill-color:var(--mvm-ency-ink)!important;
            border:1px solid var(--mvm-ency-border)!important;
            border-radius:12px!important;
            text-decoration:none!important;
            font-weight:750!important;
        }
        .mvm-themes-index .mvm-theme-jump a span{
            display:grid!important;
            place-items:center!important;
            width:34px!important;
            height:34px!important;
            border-radius:10px!important;
            background:var(--mvm-ency-blue)!important;
            color:#fff!important;
            -webkit-text-fill-color:#fff!important;
            font-weight:850!important;
        }
        .mvm-themes-index .mvm-theme-bundle{
            overflow:hidden!important;
            background:#fff!important;
            border:1px solid var(--mvm-ency-border)!important;
            border-radius:18px!important;
            box-shadow:var(--mvm-ency-shadow)!important;
        }
        .mvm-themes-index .mvm-theme-bundle-head{
            display:grid!important;
            grid-template-columns:auto 1fr auto!important;
            gap:16px!important;
            align-items:center!important;
            padding:18px!important;
            background:linear-gradient(90deg,var(--mvm-ency-blue-soft),#fff)!important;
        }
        .mvm-themes-index .mvm-bundle-index{
            display:grid!important;
            place-items:center!important;
            width:54px!important;
            height:54px!important;
            border-radius:14px!important;
            background:var(--mvm-ency-blue)!important;
            color:#fff!important;
            -webkit-text-fill-color:#fff!important;
            font-weight:900!important;
            font-size:1.2rem!important;
        }
        .mvm-themes-index .mvm-bundle-copy h3{margin:2px 0 5px!important;color:var(--mvm-ency-ink)!important;-webkit-text-fill-color:var(--mvm-ency-ink)!important}
        .mvm-themes-index .mvm-bundle-copy p,
        .mvm-themes-index .mvm-bundle-stats span{color:var(--mvm-ency-muted)!important;-webkit-text-fill-color:var(--mvm-ency-muted)!important}
        .mvm-themes-index .mvm-bundle-stats{display:grid!important;grid-template-columns:auto auto!important;gap:3px 8px!important;text-align:right!important}
        .mvm-themes-index .mvm-bundle-stats strong{color:var(--mvm-ency-blue)!important;-webkit-text-fill-color:var(--mvm-ency-blue)!important}
        .mvm-themes-index .mvm-theme-stack{display:grid!important;gap:10px!important;padding:14px!important}
        .mvm-themes-index .mvm-theme-dossier-block{
            overflow:hidden!important;
            border:1px solid var(--mvm-ency-border)!important;
            border-radius:13px!important;
            background:#fff!important;
        }
        .mvm-themes-index .mvm-theme-dossier-block summary{
            padding:13px 14px!important;
            background:var(--mvm-ency-soft)!important;
            color:var(--mvm-ency-ink)!important;
            -webkit-text-fill-color:var(--mvm-ency-ink)!important;
        }
        .mvm-themes-index .mvm-theme-number{color:var(--mvm-ency-blue)!important;-webkit-text-fill-color:var(--mvm-ency-blue)!important;font-weight:850!important}
        .mvm-themes-index .mvm-theme-dossier-body{padding:14px!important;color:var(--mvm-ency-ink)!important;-webkit-text-fill-color:var(--mvm-ency-ink)!important}
        .mvm-themes-index .mvm-dossier-family-mini-grid{display:grid!important;grid-template-columns:repeat(3,minmax(0,1fr))!important;gap:10px!important}
        .mvm-themes-index .mvm-dossier-family-mini{
            display:grid!important;
            gap:4px!important;
            padding:12px!important;
            background:var(--mvm-ency-soft)!important;
            border:1px solid var(--mvm-ency-border)!important;
            border-radius:11px!important;
            text-decoration:none!important;
        }
        .mvm-themes-index .mvm-dossier-family-mini strong{color:var(--mvm-ency-ink)!important;-webkit-text-fill-color:var(--mvm-ency-ink)!important}
        .mvm-themes-index .mvm-dossier-family-code,
        .mvm-themes-index .mvm-theme-open-page a{color:var(--mvm-ency-blue)!important;-webkit-text-fill-color:var(--mvm-ency-blue)!important;font-weight:800!important}

        /* Focus and hover state shared by encyclopedia navigation. */
        .mvm-public-home a:focus-visible,
        .mvm-public-home button:focus-visible,
        .mvm-public-home input:focus-visible,
        .mvm-public-home summary:focus-visible,
        .mvm-page a:focus-visible,
        .mvm-page summary:focus-visible{
            outline:3px solid var(--mvm-ency-focus)!important;
            outline-offset:3px!important;
        }

        /* Dark mode keeps the same information hierarchy. */
        body.mvm-header-dark .mvm-public-home,
        body.mvm-header-dark .mvm-page,
        html[data-theme="dark"] .mvm-public-home,
        html[data-theme="dark"] .mvm-page{
            --mvm-ency-ink:#f5f7fa;
            --mvm-ency-muted:#c5d1dc;
            --mvm-ency-white:#17222d;
            --mvm-ency-soft:#202e3a;
            --mvm-ency-blue-soft:#18344d;
            --mvm-ency-border:#42576a;
            color:var(--mvm-ency-ink)!important;
            -webkit-text-fill-color:var(--mvm-ency-ink)!important;
        }
        body.mvm-header-dark .mvm-public-home .mvm-public-section,
        body.mvm-header-dark .mvm-public-home .mvm-public-card,
        body.mvm-header-dark .mvm-public-home .mvm-az-letter,
        body.mvm-header-dark .mvm-public-home .mvm-timeline-era,
        body.mvm-header-dark .mvm-themes-index .mvm-theme-bundle,
        html[data-theme="dark"] .mvm-public-home .mvm-public-section,
        html[data-theme="dark"] .mvm-public-home .mvm-public-card,
        html[data-theme="dark"] .mvm-public-home .mvm-az-letter,
        html[data-theme="dark"] .mvm-public-home .mvm-timeline-era,
        html[data-theme="dark"] .mvm-themes-index .mvm-theme-bundle{
            background:#17222d!important;
            color:#f5f7fa!important;
            -webkit-text-fill-color:#f5f7fa!important;
            border-color:#42576a!important;
        }
        body.mvm-header-dark .mvm-public-home .mvm-card-body,
        body.mvm-header-dark .mvm-public-home .mvm-card-body p,
        body.mvm-header-dark .mvm-public-home .mvm-card-body h3,
        body.mvm-header-dark .mvm-public-home .mvm-section-head h2,
        html[data-theme="dark"] .mvm-public-home .mvm-card-body,
        html[data-theme="dark"] .mvm-public-home .mvm-card-body p,
        html[data-theme="dark"] .mvm-public-home .mvm-card-body h3,
        html[data-theme="dark"] .mvm-public-home .mvm-section-head h2{
            color:#f5f7fa!important;
            -webkit-text-fill-color:#f5f7fa!important;
        }
        body.mvm-header-dark .mvm-public-home .mvm-card-body a,
        body.mvm-header-dark .mvm-public-home .mvm-read-more,
        body.mvm-header-dark .mvm-public-home .mvm-az-letter-body a,
        body.mvm-header-dark .mvm-public-home .mvm-timeline-entry-content a,
        html[data-theme="dark"] .mvm-public-home .mvm-card-body a,
        html[data-theme="dark"] .mvm-public-home .mvm-read-more,
        html[data-theme="dark"] .mvm-public-home .mvm-az-letter-body a,
        html[data-theme="dark"] .mvm-public-home .mvm-timeline-entry-content a{
            color:#8bc7fb!important;
            -webkit-text-fill-color:#8bc7fb!important;
        }

        @media(max-width:980px){
            .mvm-public-home .mvm-entry-grid{grid-template-columns:repeat(3,minmax(0,1fr))!important}
            .mvm-public-home .mvm-card-grid{grid-template-columns:repeat(2,minmax(0,1fr))!important}
            .mvm-public-home .mvm-az-nav{grid-template-columns:repeat(8,minmax(36px,1fr))!important}
            .mvm-public-home .mvm-az-letter-body ul{grid-template-columns:repeat(2,minmax(0,1fr))!important}
            .mvm-public-home .mvm-timeline-toolbar{grid-template-columns:1fr!important}
            .mvm-public-home .mvm-timeline-era-nav{grid-template-columns:repeat(2,minmax(0,1fr))!important}
            .mvm-themes-index .mvm-theme-jump{grid-template-columns:repeat(2,minmax(0,1fr))!important}
            .mvm-themes-index .mvm-dossier-family-mini-grid{grid-template-columns:repeat(2,minmax(0,1fr))!important}
        }
        @media(max-width:640px){
            .mvm-public-home,
            main.mvm-page,
            #content.mvm-page{width:min(100% - 20px,1152px)!important}
            .mvm-public-home .mvm-hero,
            .mvm-page .mvm-page-head,
            .mvm-themes-index .mvm-theme-intro{padding:22px 18px!important;border-radius:15px!important}
            .mvm-public-home .mvm-public-search{grid-template-columns:1fr!important}
            .mvm-public-home .mvm-entry-grid{grid-template-columns:repeat(2,minmax(0,1fr))!important;padding:12px!important}
            .mvm-public-home .mvm-card-grid{grid-template-columns:1fr!important}
            .mvm-public-home .mvm-public-section{padding:14px!important;border-radius:15px!important}
            .mvm-public-home .mvm-section-head{display:grid!important;gap:4px!important}
            .mvm-public-home .mvm-az-nav{position:static!important;grid-template-columns:repeat(6,minmax(34px,1fr))!important;padding:8px!important}
            .mvm-public-home .mvm-az-letter-body ul{grid-template-columns:1fr!important}
            .mvm-public-home .mvm-timeline-overview{grid-template-columns:1fr 1fr 1fr!important}
            .mvm-public-home .mvm-timeline-era-nav{grid-template-columns:1fr!important}
            .mvm-public-home .mvm-timeline-entry{grid-template-columns:1fr!important;gap:8px!important}
            .mvm-public-home .mvm-timeline-entry-list{columns:1!important}
            .mvm-themes-index .mvm-theme-summary{grid-template-columns:repeat(2,minmax(0,1fr))!important}
            .mvm-themes-index .mvm-theme-jump{grid-template-columns:1fr!important}
            .mvm-themes-index .mvm-theme-bundle-head{grid-template-columns:auto 1fr!important}
            .mvm-themes-index .mvm-bundle-stats{grid-column:1/-1!important;display:flex!important;gap:6px 14px!important;text-align:left!important}
            .mvm-themes-index .mvm-dossier-family-mini-grid{grid-template-columns:1fr!important}
        }
        </style>
        <?php
    }

    private static function is_encyclopedia_request(): bool {
        $path = untrailingslashit( (string) wp_parse_url( (string) ( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH ) );
        $root = untrailingslashit( (string) wp_parse_url( home_url( '/encyclopedie/' ), PHP_URL_PATH ) );
        return $root !== '' && ( $path === $root || str_starts_with( $path, $root . '/' ) );
    }
}
