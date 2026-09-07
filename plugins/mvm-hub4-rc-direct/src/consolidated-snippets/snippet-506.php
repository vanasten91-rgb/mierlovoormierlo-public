<?php
// Consolidated from production Code Snippet #506.
defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'mvm_hub_radar_missing_dates_v1' ) ) {
    function mvm_hub_radar_missing_dates_v1() {
        $rows = get_option( 'mvm_bh_news_radar_results', [] );
        if ( ! is_array( $rows ) ) { return 0; }
        $n = 0;
        foreach ( $rows as $r ) {
            if ( is_array( $r ) && empty( $r['published_ts'] ) && ! empty( $r['url'] ) ) { $n++; }
        }
        return $n;
    }
}

if ( ! function_exists( 'mvm_hub_radar_backfill_dates_v1' ) ) {
    function mvm_hub_radar_backfill_dates_v1( $limit = 3 ) {
        $rows = get_option( 'mvm_bh_news_radar_results', [] );
        if ( ! is_array( $rows ) || ! $rows ) { return [ 'checked'=>0, 'filled'=>0, 'missing'=>0, 'message'=>'Geen Radar-items beschikbaar.' ]; }
        if ( ! function_exists( 'mvm_ai_agenda_fetch_v1' ) || ! function_exists( 'mvm_ai_agenda_published_v1' ) ) {
            return new WP_Error( 'mvm_date_extractor_missing', 'De datumextractor is niet beschikbaar.' );
        }
        $state = get_option( 'mvm_hub_radar_date_backfill_v1', [] );
        if ( ! is_array( $state ) ) { $state = []; }
        $total = count( $rows );
        $cursor = absint( $state['cursor'] ?? 0 ) % max( 1, $total );
        $limit = max( 1, min( 5, absint( $limit ) ) );
        $checked = 0; $filled = 0; $scanned = 0; $changed = false;
        while ( $scanned < $total && $checked < $limit ) {
            $i = ( $cursor + $scanned ) % $total;
            $scanned++;
            $r = $rows[$i] ?? null;
            if ( ! is_array( $r ) || ! empty( $r['published_ts'] ) || empty( $r['url'] ) ) { continue; }
            if ( ! empty( $r['dismissed'] ) || ( isset( $r['lifecycle'] ) && 'actief' !== (string) $r['lifecycle'] ) ) { continue; }
            $url = esc_url_raw( (string) $r['url'] );
            if ( ! $url || ! wp_http_validate_url( $url ) ) { continue; }
            $checked++;
            $html = mvm_ai_agenda_fetch_v1( $url );
            $rows[$i]['date_backfill_checked_at'] = wp_date( DATE_ATOM );
            if ( is_wp_error( $html ) ) {
                $rows[$i]['date_backfill_error'] = sanitize_text_field( $html->get_error_message() );
                $changed = true;
                continue;
            }
            $date = mvm_ai_agenda_published_v1( $html );
            if ( ! empty( $date['ts'] ) ) {
                $rows[$i]['published_ts'] = absint( $date['ts'] );
                $rows[$i]['published'] = sanitize_text_field( (string) $date['raw'] );
                $rows[$i]['date_source'] = sanitize_text_field( (string) $date['source'] );
                $rows[$i]['date_confidence'] = max( 0, min( 100, absint( $date['confidence'] ) ) );
                $rows[$i]['date_checked_at'] = wp_date( DATE_ATOM );
                $issues = array_values( array_filter( (array) ( $rows[$i]['date_issues'] ?? [] ), static fn( $issue ) => false === stripos( (string) $issue, 'Publicatiedatum kon niet' ) ) );
                $rows[$i]['date_issues'] = $issues;
                unset( $rows[$i]['date_backfill_error'] );
                $filled++;
            } else {
                $rows[$i]['date_backfill_error'] = 'Geen betrouwbare publicatiedatum gevonden.';
            }
            $changed = true;
        }
        if ( $changed ) { update_option( 'mvm_bh_news_radar_results', $rows, false ); }
        $state['cursor'] = ( $cursor + max( 1, $scanned ) ) % max( 1, $total );
        $state['last_run'] = time();
        $state['last_checked'] = $checked;
        $state['last_filled'] = $filled;
        update_option( 'mvm_hub_radar_date_backfill_v1', $state, false );
        $missing = mvm_hub_radar_missing_dates_v1();
        return [ 'checked'=>$checked, 'filled'=>$filled, 'missing'=>$missing, 'message'=>$checked . ' item(s) gecontroleerd; ' . $filled . ' publicatiedatum(s) aangevuld; ' . $missing . ' nog zonder datum.' ];
    }
}

add_action( 'mvm_hub_radar_date_backfill_v1', static function () { mvm_hub_radar_backfill_dates_v1( 3 ); } );
add_action( 'init', static function () {
    if ( get_option( 'mvm_hub_radar_date_backfill_once_v1', false ) ) {
        delete_option( 'mvm_hub_radar_date_backfill_once_v1' );
        mvm_hub_radar_backfill_dates_v1( 3 );
    }
}, 80 );

add_action( 'rest_api_init', static function () {
    if ( ! class_exists( 'MvM_Hub4_Security' ) || ! class_exists( 'MvM_Hub4_Capabilities' ) ) { return; }
    $manage = MvM_Hub4_Security::require_capability( MvM_Hub4_Capabilities::SOURCE_MANAGE );
    register_rest_route( 'mvm-hub4/v1', '/news-radar/date-backfill', [
        [ 'methods'=>WP_REST_Server::READABLE, 'permission_callback'=>$manage, 'callback'=>static function(){ $s=get_option('mvm_hub_radar_date_backfill_v1',[]); return rest_ensure_response([ 'missing'=>mvm_hub_radar_missing_dates_v1(), 'lastRun'=>!empty($s['last_run'])?wp_date(DATE_ATOM,(int)$s['last_run']):null, 'lastChecked'=>absint($s['last_checked']??0), 'lastFilled'=>absint($s['last_filled']??0) ]); } ],
        [ 'methods'=>WP_REST_Server::CREATABLE, 'permission_callback'=>$manage, 'callback'=>static function(){ $r=mvm_hub_radar_backfill_dates_v1(3); return is_wp_error($r)?$r:rest_ensure_response($r); } ]
    ] );
} );

add_action( 'template_redirect', static function () {
    if ( is_admin() || wp_doing_ajax() ) { return; }
    $path = trim( (string) wp_parse_url( isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '', PHP_URL_PATH ), '/' );
    if ( ! preg_match( '#^hub4?(?:/|$)#', $path ) ) { return; }
    ob_start( static function ( $html ) {
        if ( false === strpos( $html, 'class="mvm-hub4"' ) && false === strpos( $html, "class='mvm-hub4'" ) ) { return $html; }
        if ( false !== strpos( $html, 'id="mvm-radar-date-backfill-ui-v1"' ) ) { return $html; }
        $ui = <<<'HTML'
<script id="mvm-radar-date-backfill-ui-v1">
(()=>{'use strict';const c=window.MvMHub4Config||{};if(!c.restRoot||!c.restNonce)return;const api=async(method='GET')=>{const r=await fetch(new URL('news-radar/date-backfill',c.restRoot),{method,credentials:'same-origin',cache:'no-store',headers:{'X-WP-Nonce':c.restNonce,'Accept':'application/json','Content-Type':'application/json'},body:method==='POST'?'{}':undefined});let j={};try{j=await r.json()}catch(e){}if(!r.ok)throw new Error(j.message||'Datumcontrole mislukt.');return j};let mounted=false;const mount=async()=>{if(mounted)return;const h=document.querySelector('.mvm-news-radar__toolbar');if(!h)return;try{const s=await api();const box=document.createElement('div');box.className='mvm-radar-date-backfill-v1';const text=document.createElement('span');text.textContent=`${s.missing||0} zonder publicatiedatum`;const b=document.createElement('button');b.type='button';b.className='mvm-hub4__button';b.textContent='Datums aanvullen';b.disabled=!(s.missing>0);b.onclick=async()=>{b.disabled=true;b.textContent='Controleren…';try{const r=await api('POST');text.textContent=`${r.missing||0} zonder publicatiedatum`;b.textContent=r.missing>0?'Nog een batch':'Datums bijgewerkt';b.disabled=!(r.missing>0)}catch(e){b.textContent='Opnieuw proberen';b.disabled=false;alert(e.message)}};box.append(text,b);h.appendChild(box);mounted=true}catch(e){}};new MutationObserver(mount).observe(document.documentElement,{childList:true,subtree:true});mount();})();
</script><style id="mvm-radar-date-backfill-css-v1">.mvm-radar-date-backfill-v1{display:flex;align-items:center;gap:10px;flex-wrap:wrap}.mvm-radar-date-backfill-v1 span{font-size:.88rem;font-weight:700;color:var(--mvm-hub4-muted,#46596b)}</style>
HTML;
        $p = stripos( $html, '</head>' );
        return false !== $p ? substr_replace( $html, $ui . '</head>', $p, 7 ) : $ui . $html;
    } );
}, -820 );