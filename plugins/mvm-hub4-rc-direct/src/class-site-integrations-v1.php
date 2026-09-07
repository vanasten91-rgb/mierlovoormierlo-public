<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Source-owned replacements for small MvM presentation snippets.
 *
 * This class only touches MvM-owned wrappers around existing integrations. It
 * does not modify PeepSo, WP Event Manager, Elementor, PostX or wpForo code.
 */
final class MvM_Hub4_Site_Integrations_V1 {
    /** @var int[] */
    private const COMMUNITY_PAGE_IDS = array( 1197, 1198, 1199, 1200, 1201, 1202, 1203, 1204, 1205, 1206, 1207 );

    public static function init(): void {
        add_filter( 'the_content', array( __CLASS__, 'filter_content' ), 99999 );
        add_action( 'wp', array( __CLASS__, 'remove_legacy_today_from_hub' ), 999 );
        add_action( 'template_redirect', array( __CLASS__, 'remove_legacy_today_from_hub' ), 999 );
        add_action( 'wp_footer', array( __CLASS__, 'remove_legacy_today_from_hub' ), 1 );
        add_action( 'wp_head', array( __CLASS__, 'render_public_css' ), PHP_INT_MAX );
        add_action( 'admin_footer', array( __CLASS__, 'render_source_sort_ui' ), 99 );
    }

    public static function filter_content( $content ) {
        if ( ! is_string( $content ) ) {
            return $content;
        }

        // Generic MvM Delen/Opslaan is disabled sitewide in Hub 1.1.
        if ( false !== strpos( $content, 'mvm-share-v1' ) ) {
            $cleaned = preg_replace( '~<section class="mvm-share-v1"[^>]*>.*?</section>~is', '', $content );
            if ( is_string( $cleaned ) ) {
                $content = $cleaned;
            }
        }

        // WP Event Manager's full practical-information block remains; only
        // the old compact MvM duplicate is removed.
        if ( ! is_admin() && is_singular( 'event_listing' ) && false !== strpos( $content, 'mvm-event-practical' ) ) {
            $cleaned = preg_replace( '/<section class="mvm-event-practical"[^>]*>.*?<\/section>/is', '', $content, 1 );
            if ( is_string( $cleaned ) ) {
                $content = $cleaned;
            }
        }

        return $content;
    }

    public static function remove_legacy_today_from_hub(): void {
        if ( ! self::is_hub_request() ) {
            return;
        }
        if ( function_exists( 'mvm_today_hub_panel_v1' ) ) {
            remove_action( 'wp_footer', 'mvm_today_hub_panel_v1', 97 );
        }
        if ( function_exists( 'mvm_today_render_v1' ) ) {
            remove_action( 'newsup_action_banner_exclusive_posts', 'mvm_today_render_v1', 20 );
        }
    }

    public static function render_public_css(): void {
        ?>
        <style id="mvm-hub4-site-integrations-v1">
            .mvm-share-v1{display:none!important}
            <?php if ( is_singular( 'event_listing' ) ) : ?>
            body.single-event_listing .wpem-single-event-body-content .wp-block-embed.is-type-video,
            body.single-event_listing .wpem-single-event-body-content .wp-block-video{width:100%!important;max-width:100%!important}
            body.single-event_listing .wpem-single-event-body-content .wp-block-embed.is-type-video .wp-block-embed__wrapper{width:100%!important;max-width:100%!important}
            body.single-event_listing .wpem-single-event-body-content .wp-block-embed.is-type-video iframe{display:block!important;width:100%!important;max-width:100%!important;height:auto!important;border:0!important}
            body.single-event_listing .wpem-single-event-body-content .wp-embed-aspect-21-9 iframe{aspect-ratio:21/9!important}
            body.single-event_listing .wpem-single-event-body-content .wp-embed-aspect-18-9 iframe{aspect-ratio:18/9!important}
            body.single-event_listing .wpem-single-event-body-content .wp-embed-aspect-16-9 iframe{aspect-ratio:16/9!important}
            body.single-event_listing .wpem-single-event-body-content .wp-embed-aspect-4-3 iframe{aspect-ratio:4/3!important}
            body.single-event_listing .wpem-single-event-body-content .wp-embed-aspect-1-1 iframe{aspect-ratio:1/1!important}
            body.single-event_listing .wpem-single-event-body-content .wp-embed-aspect-2-3 iframe{aspect-ratio:2/3!important}
            body.single-event_listing .wpem-single-event-body-content .wp-embed-aspect-9-16 iframe{aspect-ratio:9/16!important}
            body.single-event_listing .wpem-single-event-body-content .wp-block-video video,
            body.single-event_listing .wpem-single-event-body-content video.wp-video-shortcode{display:block!important;width:100%!important;max-width:100%!important;height:auto!important;object-fit:contain!important}
            <?php endif; ?>
            <?php if ( is_page( self::COMMUNITY_PAGE_IDS ) ) : ?>
            .mvm-peepso-userbar-strip{display:none!important}
            <?php endif; ?>
            <?php if ( self::is_hub_request() ) : ?>
            #mvm-today-hub-panel-v1,[data-mvm-today-nav="1"],.mvm-today-section,#mvm-today{display:none!important;visibility:hidden!important;position:absolute!important;left:-99999px!important;width:1px!important;height:1px!important;overflow:hidden!important}
            <?php endif; ?>
        </style>
        <?php
    }

    public static function render_source_sort_ui(): void {
        if ( empty( $_GET['page'] ) || 'mvm-bh-bronnen' !== sanitize_key( wp_unslash( (string) $_GET['page'] ) ) ) {
            return;
        }

        $sources = get_option( 'mvm_bh_sources', array() );
        $meta    = array();
        if ( is_array( $sources ) ) {
            foreach ( $sources as $source ) {
                if ( ! is_array( $source ) || empty( $source['id'] ) ) {
                    continue;
                }
                $id = absint( $source['id'] );
                $meta[ $id ] = array(
                    'priority'     => strtoupper( sanitize_text_field( (string) ( $source['priority'] ?? '' ) ) ),
                    'name'         => sanitize_text_field( (string) ( $source['name'] ?? '' ) ),
                    'frequency'    => sanitize_text_field( (string) ( $source['frequency'] ?? '' ) ),
                    'last_checked' => sanitize_text_field( (string) ( $source['last_checked'] ?? '' ) ),
                );
            }
        }
        ?>
        <style id="mvm-source-sort-style">
            .mvm-source-sort{display:inline-flex;align-items:center;gap:8px;margin-left:auto}.mvm-source-sort label{font-weight:600}.mvm-source-sort select{min-width:230px}@media(max-width:782px){.mvm-source-sort{width:100%;margin:8px 0 0}.mvm-source-sort select{width:100%;max-width:none}}
        </style>
        <script id="mvm-source-sort-script">
        (function(){
            'use strict';
            var meta=<?php echo wp_json_encode( $meta ); ?>;
            var table=document.querySelector('table.mvm-bh-sources');
            var toolbar=document.querySelector('.mvm-bh-toolbar');
            if(!table||!toolbar||!table.tBodies.length)return;
            var wrap=document.createElement('div');wrap.className='mvm-source-sort';
            var label=document.createElement('label');label.htmlFor='mvm-source-sort';label.textContent='Sorteren';
            var select=document.createElement('select');select.id='mvm-source-sort';
            [
                ['priority-asc','Prioriteit: hoog → laag'],['priority-desc','Prioriteit: laag → hoog'],['name-asc','Naam: A → Z'],['name-desc','Naam: Z → A'],['frequency','Controlefrequentie: vaakst eerst'],['checked-desc','Datum/tijd laatste controle: nieuw → oud'],['checked-asc','Datum/tijd laatste controle: oud → nieuw'],['arrival-desc','Binnenkomst: nieuwste eerst'],['arrival-asc','Binnenkomst: oudste eerst']
            ].forEach(function(pair){var option=document.createElement('option');option.value=pair[0];option.textContent=pair[1];select.appendChild(option);});
            wrap.appendChild(label);wrap.appendChild(select);toolbar.appendChild(wrap);
            var params=new URLSearchParams(window.location.search);var saved=params.get('mvm_sort')||'priority-asc';
            if(Array.prototype.some.call(select.options,function(o){return o.value===saved;}))select.value=saved;
            function rowId(row){var link=row.querySelector('a[href*="edit_source="]');if(!link)return 0;var match=link.href.match(/[?&]edit_source=(\d+)/);return match?parseInt(match[1],10):0;}
            function data(row){var id=rowId(row),d=meta[id]||{};return{id:id,priority:d.priority||'',name:d.name||'',frequency:d.frequency||'',checked:d.last_checked||''};}
            function cmpText(a,b){return String(a).localeCompare(String(b),'nl',{sensitivity:'base',numeric:true});}
            function rankPriority(v){var ranks={A:0,B:1,C:2};return ranks[v]!==undefined?ranks[v]:99;}
            function rankFrequency(v){v=String(v||'').toLowerCase();if(v.indexOf('dag')!==-1)return 0;if(v.indexOf('2-3')!==-1)return 1;if(v.indexOf('week')!==-1)return 2;if(v.indexOf('maand')!==-1)return 3;return 9;}
            function checkedTs(v){var t=Date.parse(String(v||''));return isNaN(t)?0:t;}
            function sortRows(mode){var body=table.tBodies[0],rows=Array.prototype.slice.call(body.rows);rows.sort(function(ra,rb){var a=data(ra),b=data(rb),c=0;if(mode==='priority-asc')c=rankPriority(a.priority)-rankPriority(b.priority)||cmpText(a.name,b.name);else if(mode==='priority-desc')c=rankPriority(b.priority)-rankPriority(a.priority)||cmpText(a.name,b.name);else if(mode==='name-asc')c=cmpText(a.name,b.name);else if(mode==='name-desc')c=cmpText(b.name,a.name);else if(mode==='frequency')c=rankFrequency(a.frequency)-rankFrequency(b.frequency)||rankPriority(a.priority)-rankPriority(b.priority)||cmpText(a.name,b.name);else if(mode==='checked-desc')c=checkedTs(b.checked)-checkedTs(a.checked)||rankPriority(a.priority)-rankPriority(b.priority)||cmpText(a.name,b.name);else if(mode==='checked-asc')c=(checkedTs(a.checked)||Number.MAX_SAFE_INTEGER)-(checkedTs(b.checked)||Number.MAX_SAFE_INTEGER)||rankPriority(a.priority)-rankPriority(b.priority)||cmpText(a.name,b.name);else if(mode==='arrival-desc')c=b.id-a.id;else if(mode==='arrival-asc')c=a.id-b.id;return c;});rows.forEach(function(row){body.appendChild(row);});}
            select.addEventListener('change',function(){sortRows(select.value);var url=new URL(window.location.href);url.searchParams.set('mvm_sort',select.value);window.history.replaceState({},'',url.toString());});sortRows(select.value);
        }());
        </script>
        <?php
    }

    private static function is_hub_request(): bool {
        $request_path = untrailingslashit( (string) wp_parse_url( (string) ( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH ) );
        $hub_paths = array(
            untrailingslashit( (string) wp_parse_url( home_url( '/hub/' ), PHP_URL_PATH ) ),
            untrailingslashit( (string) wp_parse_url( home_url( '/hub4/' ), PHP_URL_PATH ) ),
        );
        return in_array( $request_path, $hub_paths, true );
    }
}
