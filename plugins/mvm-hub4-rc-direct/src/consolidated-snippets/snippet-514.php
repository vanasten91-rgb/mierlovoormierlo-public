<?php
// Consolidated from production Code Snippet #514.
defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'mvm_ai_agenda_table_events_v3' ) ) {
    function mvm_ai_agenda_table_events_v3( $html, $base ) {
        if ( ! class_exists( 'DOMDocument' ) ) { return []; }
        $dom = new DOMDocument();
        libxml_use_internal_errors( true );
        @$dom->loadHTML( '<?xml encoding="utf-8" ?>' . substr( (string) $html, 0, 900000 ) );
        $xp = new DOMXPath( $dom );
        $tables = $xp->query( '//table' );
        if ( ! $tables ) { return []; }

        $months = [ 'januari'=>1,'februari'=>2,'maart'=>3,'april'=>4,'mei'=>5,'juni'=>6,'juli'=>7,'augustus'=>8,'september'=>9,'oktober'=>10,'november'=>11,'december'=>12, 'jan'=>1,'feb'=>2,'mrt'=>3,'apr'=>4,'jun'=>6,'jul'=>7,'aug'=>8,'sep'=>9,'okt'=>10,'nov'=>11,'dec'=>12 ];
        $today = wp_date( 'Y-m-d' );
        $out = [];

        foreach ( $tables as $table ) {
            $rows = $xp->query( './/tr', $table );
            if ( ! $rows || ! $rows->length ) { continue; }
            $header = null; $date_i = -1; $title_i = -1; $time_i = -1; $year = 0; $month = 0;

            foreach ( $rows as $ri => $tr ) {
                $cells = [];
                foreach ( $tr->childNodes as $node ) {
                    if ( $node instanceof DOMElement && in_array( strtolower( $node->tagName ), [ 'td','th' ], true ) ) {
                        $cells[] = trim( preg_replace( '/\s+/u', ' ', (string) $node->textContent ) );
                    }
                }
                if ( ! $cells ) { continue; }
                $norm = array_map( static fn($v) => strtolower( remove_accents( trim( (string) $v ) ) ), $cells );

                if ( null === $header ) {
                    foreach ( $norm as $i => $label ) {
                        if ( false !== strpos( $label, 'datum' ) || 'date' === $label ) { $date_i = $i; }
                        if ( false !== strpos( $label, 'evenement' ) || false !== strpos( $label, 'activiteit' ) || 'event' === $label ) { $title_i = $i; }
                        if ( false !== strpos( $label, 'tijd' ) || 'time' === $label ) { $time_i = $i; }
                        if ( ! $year && preg_match( '/\b(20\d{2})\b/', $label, $ym ) ) { $year = absint( $ym[1] ); }
                    }
                    if ( $date_i >= 0 && $title_i >= 0 ) { $header = $ri; if ( ! $year ) { $year = absint( wp_date( 'Y' ) ); } }
                    continue;
                }

                $nonempty = array_values( array_filter( $norm, static fn($v) => '' !== $v ) );
                if ( 1 === count( $nonempty ) ) {
                    $m = $nonempty[0];
                    if ( isset( $months[$m] ) ) { $month = $months[$m]; continue; }
                }
                if ( ! $month ) { continue; }

                $day_raw = isset( $cells[$date_i] ) ? trim( (string) $cells[$date_i] ) : '';
                $title = isset( $cells[$title_i] ) ? trim( (string) $cells[$title_i] ) : '';
                if ( '' === $title || ! preg_match( '/^([0-3]?\d)$/', $day_raw, $dm ) ) { continue; }
                $day = absint( $dm[1] );
                if ( $day < 1 || $day > 31 ) { continue; }

                $row_text = strtolower( remove_accents( implode( ' ', $cells ) ) );
                if ( preg_match( '/\b(afgelast|geannuleerd|cancelled|canceled)\b/u', $row_text ) ) { continue; }

                $date = sprintf( '%04d-%02d-%02d', $year, $month, $day );
                if ( $date < $today ) { continue; }
                $time = '';
                if ( $time_i >= 0 && isset( $cells[$time_i] ) && preg_match( '/\b([01]?\d|2[0-3])[.:]([0-5]\d)\b/', (string) $cells[$time_i], $tm ) ) {
                    $time = sprintf( '%02d:%02d:00', absint( $tm[1] ), absint( $tm[2] ) );
                }
                $start = $date . ( $time ? 'T' . $time : 'T00:00:00' );
                $event_key = hash( 'sha256', strtolower( $base . '|' . $date . '|' . $title ) );
                $out[] = [ 'title'=>$title, 'url'=>esc_url_raw( $base ), 'start'=>$start, 'end'=>'', 'published'=>'', 'excerpt'=>trim( implode( ' · ', array_values( array_filter( $cells ) ) ) ), 'event_key'=>$event_key, 'table_date'=>$date ];
                if ( count( $out ) >= 12 ) { break 2; }
            }
        }
        return $out;
    }
}

if ( ! function_exists( 'mvm_ai_agenda_merge_table_v3' ) ) {
    function mvm_ai_agenda_merge_table_v3( array $source, array $event ) {
        $url = esc_url_raw( (string) ( $event['url'] ?? $source['url'] ?? '' ) );
        $title = sanitize_text_field( (string) ( $event['title'] ?? '' ) );
        $start_ts = strtotime( (string) ( $event['start'] ?? '' ) ) ?: 0;
        if ( ! $url || '' === $title || ! $start_ts || $start_ts < strtotime( wp_date( 'Y-m-d' ) . ' 00:00:00' ) ) { return false; }
        $id = ! empty( $event['event_key'] ) ? sanitize_text_field( (string) $event['event_key'] ) : hash( 'sha256', strtolower( absint($source['id']??0) . '|' . $url . '|' . $start_ts . '|' . $title ) );
        $rows = get_option( 'mvm_bh_news_radar_results', [] );
        if ( ! is_array( $rows ) ) { $rows = []; }
        foreach ( $rows as $r ) {
            if ( is_array( $r ) && (string) ( $r['id'] ?? '' ) === $id ) { return false; }
        }
        $now = time();
        $rows[] = [
            'id'=>$id,'track_id'=>$id,'source_id'=>absint($source['id']??0),'source'=>sanitize_text_field((string)($source['name']??'')),'source_url'=>esc_url_raw((string)($source['url']??'')),
            'title'=>$title,'url'=>$url,'published'=>'','published_ts'=>0,'excerpt'=>mb_substr(sanitize_textarea_field((string)($event['excerpt']??'')),0,3000),'previous_excerpt'=>'',
            'event_type'=>'nieuw','found_at'=>wp_date(DATE_ATOM,$now),'found_ts'=>$now,'local_score'=>0,'ai_pending'=>1,'status'=>'controleren','category'=>'Evenementen / Agenda','summary'=>'','confidence'=>0,
            'reason'=>'Agenda-item uit duidelijke bronagenda gevonden; wacht op bestaande Nieuwsradar AI-beoordeling.','change_relevant'=>null,'change_summary'=>'','date_issues'=>['Publicatiedatum wordt door deze agenda niet vermeld.'],
            'relative_terms'=>[],'date_source'=>'niet vermeld','date_confidence'=>0,'date_checked_at'=>wp_date(DATE_ATOM,$now),'reviewed'=>0,'dismissed'=>0,'email_pending'=>0,'email_sent'=>0,'lifecycle'=>'actief','related_post_id'=>0,'relationship_type'=>'geen',
            'agenda_start_ts'=>$start_ts,'agenda_date'=>(string)($event['table_date']??'')
        ];
        usort( $rows, static fn($a,$b) => (int)($b['found_ts']??0) <=> (int)($a['found_ts']??0) );
        $legacy = get_option( 'mvm_bh_news_radar_settings', [] );
        $keep = max( 100, min( 1000, absint( is_array($legacy) ? ($legacy['retention']??500) : 500 ) ) );
        update_option( 'mvm_bh_news_radar_results', array_slice( $rows, 0, $keep ), false );
        return true;
    }
}

if ( ! function_exists( 'mvm_ai_agenda_scan_v3' ) ) {
    function mvm_ai_agenda_scan_v3() {
        $s = mvm_ai_agenda_settings_v1();
        if ( empty( $s['enabled'] ) ) { return [ 'added'=>0, 'message'=>'AI-agendacrawl staat uit.' ]; }
        $sources = get_option( 'mvm_bh_sources', [] );
        $sources = array_values( array_filter( is_array($sources)?$sources:[], static fn($x)=>is_array($x)&&!empty($x['active'])&&empty($x['excluded'])&&!empty($x['url']) ) );
        if ( ! $sources ) { $s['last_run']=time();$s['last_message']='Geen actieve bronnen met URL.';mvm_ai_agenda_save_settings_v1($s);return ['added'=>0,'message'=>$s['last_message']]; }
        $batch=max(1,min(5,absint($s['batch'])));$cursor=absint($s['cursor'])%count($sources);$added=0;$checked=0;$agenda_pages=0;$structured=0;$table_events=0;
        for($n=0;$n<$batch;$n++){
            $source=$sources[($cursor+$n)%count($sources)];$html=mvm_ai_agenda_fetch_v1($source['url']);$checked++;if(is_wp_error($html))continue;
            $events=mvm_ai_agenda_events_v1($html,$source['url']);$mode='structured';$agenda_url=$source['url'];
            if(!$events){$agenda=mvm_ai_agenda_discover_v2($html,$source['url']);if($agenda&&$agenda!==$source['url']){$agenda_pages++;$agenda_url=$agenda;$ahtml=mvm_ai_agenda_fetch_v1($agenda);if(!is_wp_error($ahtml)){$events=mvm_ai_agenda_events_v1($ahtml,$agenda);if(!$events){$events=mvm_ai_agenda_table_events_v3($ahtml,$agenda);$mode='table';}}}}
            if($events){if('table'===$mode)$table_events+=count($events);else $structured+=count($events);}
            foreach(array_slice($events,0,3) as $event){$ok='table'===$mode?mvm_ai_agenda_merge_table_v3($source,$event):mvm_ai_agenda_merge_result_v1($source,$event);if($ok)$added++;}
        }
        $s['cursor']=($cursor+$batch)%count($sources);$s['last_run']=time();$s['last_message']=$checked.' bron(nen) gecontroleerd; '.$agenda_pages.' agenda-/kalenderpagina(s); '.$structured.' structured event(s); '.$table_events.' tabel-event(s); '.$added.' nieuw(e) item(s) naar de Nieuwsradar AI-wachtrij.';mvm_ai_agenda_save_settings_v1($s);
        return ['added'=>$added,'checked'=>$checked,'agendaPages'=>$agenda_pages,'structuredEvents'=>$structured,'tableEvents'=>$table_events,'message'=>$s['last_message']];
    }
}

add_action('init',static function(){remove_action('mvm_hub_ai_agenda_scan_v1','mvm_ai_agenda_scan_v1');remove_action('mvm_hub_ai_agenda_scan_v1','mvm_ai_agenda_scan_v2');add_action('mvm_hub_ai_agenda_scan_v1','mvm_ai_agenda_scan_v3');},2);
add_action('rest_api_init',static function(){if(!class_exists('MvM_Hub4_Security')||!class_exists('MvM_Hub4_Capabilities'))return;$manage=MvM_Hub4_Security::require_capability(MvM_Hub4_Capabilities::SOURCE_MANAGE);register_rest_route('mvm-hub4/v1','/agenda-crawl/run',['methods'=>WP_REST_Server::CREATABLE,'permission_callback'=>$manage,'callback'=>static function(){if(empty(mvm_ai_agenda_settings_v1()['enabled']))return new WP_Error('mvm_agenda_disabled','Zet AI-agendacrawl eerst aan.',['status'=>400]);return rest_ensure_response(mvm_ai_agenda_scan_v3());}],true);},100);