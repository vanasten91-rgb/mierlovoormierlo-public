<?php
// Consolidated from production Code Snippet #519.
defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'mvm_newsradar_text_sub_v1' ) ) {
    function mvm_newsradar_text_sub_v1( $text, $start, $length ) {
        $text = (string) $text;
        return function_exists( 'mb_substr' ) ? mb_substr( $text, $start, $length ) : substr( $text, $start, $length );
    }
}

if ( ! function_exists( 'mvm_newsradar_radar_provider_blocked_v2' ) ) {
    function mvm_newsradar_radar_provider_blocked_v2() {
        $state = get_option( 'mvm_bh_news_radar_state', array() );
        if ( ! is_array( $state ) ) return false;
        $message = strtolower( sanitize_text_field( (string) ( $state['last_message'] ?? '' ) ) );
        $provider_error = false;
        foreach ( array( 'http 429', '429', 'no credits', 'credit', 'quota', 'rate limit', 'rate_limit', 'insufficient_quota', 'openai http', 'billing' ) as $needle ) {
            if ( '' !== $message && false !== strpos( $message, $needle ) ) { $provider_error = true; break; }
        }
        $ai_stalled = 'error' === (string) ( $state['phase'] ?? '' )
            && (int) ( $state['details_processed'] ?? 0 ) > 0
            && (int) ( $state['ai_processed'] ?? 0 ) < 1;
        return $provider_error || $ai_stalled;
    }
}

if ( ! function_exists( 'mvm_newsradar_should_google_fallback_v1' ) ) {
    function mvm_newsradar_should_google_fallback_v1() {
        $blocked = false;
        if ( function_exists( 'mvm_ai_agenda_ai_health_v4' ) ) {
            $health = mvm_ai_agenda_ai_health_v4();
            $blocked = ! empty( $health['blocked'] );
        }
        if ( function_exists( 'mvm_newsradar_radar_provider_blocked_v2' ) && mvm_newsradar_radar_provider_blocked_v2() ) {
            $blocked = true;
        }
        if ( $blocked ) {
            set_transient( 'mvm_newsradar_google_fallback_v1', 1, 15 * MINUTE_IN_SECONDS );
            return true;
        }
        return (bool) get_transient( 'mvm_newsradar_google_fallback_v1' );
    }
}

if ( ! function_exists( 'mvm_newsradar_recent_context_v1' ) ) {
    function mvm_newsradar_recent_context_v1( array $rows, $exclude_id ) {
        $out = [];
        foreach ( $rows as $r ) {
            if ( ! is_array( $r ) || (string) ( $r['id'] ?? '' ) === (string) $exclude_id ) { continue; }
            if ( ! in_array( (string) ( $r['status'] ?? '' ), [ 'publiceerbaar', 'controleren' ], true ) ) { continue; }
            if ( (int) ( $r['found_ts'] ?? 0 ) < time() - 7 * DAY_IN_SECONDS ) { continue; }
            $out[] = [ 'title'=>(string)($r['title']??''), 'source'=>(string)($r['source']??''), 'summary'=>mvm_newsradar_text_sub_v1((string)($r['summary']??''),0,500), 'url'=>(string)($r['url']??'') ];
            if ( count( $out ) >= 4 ) { break; }
        }
        return $out;
    }
}

if ( ! function_exists( 'mvm_newsradar_gemini_analyze_v1' ) ) {
    function mvm_newsradar_gemini_analyze_v1( array $pending, array $all_rows ) {
        if ( ! class_exists( 'WordPress\\AiClient\\AiClient' ) ) return new WP_Error('mvm_gemini_client_missing','WordPress AI Client is niet beschikbaar.');
        $payload=[];
        foreach($pending as $r){
            $scope = class_exists('MvM_Hub4_Newsradar_Local_Scope') ? MvM_Hub4_Newsradar_Local_Scope::assess($r) : array('score'=>(int)($r['local_score']??0),'decision'=>'review','reasons'=>array());
            $payload[]=[
                'id'=>(string)($r['id']??''),'event_type'=>(string)($r['event_type']??'nieuw'),'source'=>(string)($r['source']??''),'source_url'=>(string)($r['source_url']??''),'title'=>(string)($r['title']??''),'url'=>(string)($r['url']??''),'published'=>(string)($r['published']??''),
                'article_text'=>mvm_newsradar_text_sub_v1((string)($r['excerpt']??''),0,4500),'previous_text'=>mvm_newsradar_text_sub_v1((string)($r['previous_excerpt']??''),0,1800),'local_score'=>(int)($scope['score']??0),'local_scope'=>(string)($scope['decision']??'review'),'local_reasons'=>(array)($scope['reasons']??[]),'date_issues'=>(array)($r['date_issues']??[]),'relative_terms'=>(array)($r['relative_terms']??[]),'date_source'=>(string)($r['date_source']??'onbekend'),'date_confidence'=>(int)($r['date_confidence']??0),'recent_related'=>mvm_newsradar_recent_context_v1($all_rows,(string)($r['id']??''))
            ];
        }
        $today=wp_date('l d-m-Y');
        $instruction="Je bent de nieuwsselectie van Mierlo voor Mierlo. Vandaag is {$today} in tijdzone Europe/Amsterdam. Beoordeel uitsluitend de aangeleverde broninhoud. Mierlo betekent uitsluitend het dorp Mierlo in Noord-Brabant. Mierlo-Hout, Geldrop, Helmond, Eindhoven, Heeze en andere plaatsen zijn niet relevant tenzij het item aantoonbaar directe gevolgen voor inwoners, organisaties, locaties of voorzieningen in Mierlo heeft. Respecteer local_scope: reject mag nooit publiceerbaar worden; review vereist concreet bewijs voordat je publiceerbaar kiest. Controleer datums zorgvuldig: publicatiedatum, date_source/date_confidence, kalenderdata, weekdagen en relatieve woorden moeten logisch bij elkaar passen. Als actualiteit onzeker of tegenstrijdig is: status controleren. Voor event_type update vergelijk previous_text met article_text. change_relevant is alleen true bij inhoudelijk nieuwe feiten of nieuwswaardige wijzigingen; niet bij opmaak of cosmetische wijzigingen. Gebruik recent_related om dubbelen te herkennen. Markeer publiceerbaar alleen als concreet, Mierlo-lokaal en nieuwswaardig; controleren als verificatie nodig is; niet_mierlo als het niet over Mierlo gaat. Verzin geen feiten. Schrijf een korte Nederlandse samenvatting en bij updates in change_summary exact wat inhoudelijk nieuw is.";
        $shape=['items'=>[['id'=>'exact aangeleverd id','status'=>'publiceerbaar|controleren|niet_mierlo|dubbel','category'=>'korte categorie','summary'=>'korte Nederlandse samenvatting','confidence'=>0,'reason'=>'korte reden','change_relevant'=>false,'change_summary'=>'','relationship_type'=>'geen|eerder_bericht|vervolg|gerelateerd']]];
        $prompt=$instruction."\n\nGeef uitsluitend geldige JSON, zonder markdown of extra tekst. Geef voor ELK aangeleverd item exact één resultaat terug. Structuur:\n".wp_json_encode($shape,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n\nAangeleverde items:\n".wp_json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        $models=['gemini-3.7-flash','gemini-3.6-flash','gemini-3.5-flash-lite'];$text='';$used='';$errors=[];
        foreach($models as $model){try{$result=\WordPress\AiClient\AiClient::prompt($prompt)->usingModelPreference(['google',$model])->generateTextResult();$candidate=method_exists($result,'toText')?trim((string)$result->toText()):'';if($candidate!==''){$text=$candidate;$used=$model;break;}$errors[]=$model.': leeg antwoord';}catch(Throwable $e){$errors[]=$model.': '.sanitize_text_field($e->getMessage());}}
        if($text==='')return new WP_Error('mvm_gemini_request',mvm_newsradar_text_sub_v1(implode(' | ',$errors),0,500));
        update_option('mvm_newsradar_gemini_last_model_v1',$used,false);
        $text=preg_replace('/^```(?:json)?\s*/i','',$text);$text=preg_replace('/\s*```$/','',$text);$first=strpos($text,'{');$last=strrpos($text,'}');if(false!==$first&&false!==$last&&$last>=$first)$text=substr($text,$first,$last-$first+1);
        $json=json_decode($text,true);if(!is_array($json)||!isset($json['items'])||!is_array($json['items']))return new WP_Error('mvm_gemini_parse','Gemini gaf geen geldige Nieuwsradar-JSON terug.');
        $allowed=array_fill_keys(array_map(static fn($r)=>(string)($r['id']??''),$pending),true);$clean=[];
        foreach($json['items'] as $item){if(!is_array($item))continue;$id=(string)($item['id']??'');if($id===''||!isset($allowed[$id]))continue;$status=sanitize_key((string)($item['status']??'controleren'));if(!in_array($status,['publiceerbaar','controleren','niet_mierlo','dubbel'],true))$status='controleren';$rel=sanitize_key((string)($item['relationship_type']??'geen'));if(!in_array($rel,['geen','eerder_bericht','vervolg','gerelateerd'],true))$rel='geen';$clean[$id]=['id'=>$id,'status'=>$status,'category'=>sanitize_text_field((string)($item['category']??'Algemeen')),'summary'=>sanitize_textarea_field((string)($item['summary']??'')),'confidence'=>max(0,min(100,(int)($item['confidence']??0))),'reason'=>sanitize_textarea_field((string)($item['reason']??'')),'change_relevant'=>!empty($item['change_relevant']),'change_summary'=>sanitize_textarea_field((string)($item['change_summary']??'')),'relationship_type'=>$rel];}
        if(!$clean)return new WP_Error('mvm_gemini_no_items','Gemini gaf geen bruikbare itembeoordelingen terug.');return array_values($clean);
    }
}

if ( ! function_exists( 'mvm_newsradar_apply_gemini_v1' ) ) {
    function mvm_newsradar_apply_gemini_v1( array $analysis ) {
        $rows=get_option('mvm_bh_news_radar_results',[]);$tracks=get_option('mvm_bh_news_radar_tracks',[]);$settings=get_option('mvm_bh_news_radar_settings',[]);if(!is_array($rows))$rows=[];if(!is_array($tracks))$tracks=[];if(!is_array($settings))$settings=[];$map=[];foreach($analysis as $a)if(!empty($a['id']))$map[(string)$a['id']]=$a;$processed=0;$model=(string)get_option('mvm_newsradar_gemini_last_model_v1','gemini-3.7-flash');
        foreach($rows as &$r){$rid=(string)($r['id']??'');if($rid===''||!isset($map[$rid])||empty($r['ai_pending']))continue;$a=$map[$rid];$scope=class_exists('MvM_Hub4_Newsradar_Local_Scope')?MvM_Hub4_Newsradar_Local_Scope::assess($r):array('decision'=>'review','score'=>(int)($r['local_score']??0),'reasons'=>array());$r['ai_pending']=0;$r['status']=$a['status'];$r['category']=$a['category']?:((string)($r['category']??'Algemeen'));$r['summary']=$a['summary'];$r['confidence']=$a['confidence'];$r['reason']=$a['reason'];$r['change_relevant']=!empty($a['change_relevant'])?1:0;$r['change_summary']=$a['change_summary'];$r['relationship_type']=$a['relationship_type'];$r['ai_checked_at']=wp_date(DATE_ATOM);$r['ai_provider']='google';$r['ai_model']=$model;$r['mvm_scope_score']=(int)($scope['score']??0);$r['mvm_scope_decision']=(string)($scope['decision']??'review');
            if(($scope['decision']??'')==='reject'){$r['status']='niet_mierlo';$r['reason']='Mierlo-scopefilter: '.implode(' ',(array)($scope['reasons']??[]));}
            elseif(($scope['decision']??'')==='review'&&$r['status']==='publiceerbaar'&&(int)$r['confidence']<85){$r['status']='controleren';$r['reason']=trim($r['reason'].' Mierlo-scope vereist aanvullende controle.');}
            if(!empty($r['date_issues'])&&$r['status']==='publiceerbaar'){$r['status']='controleren';$r['reason']=trim($r['reason'].' Datumcontrole: '.implode(' ',(array)$r['date_issues']));}
            $tid=(string)($r['track_id']??'');if($tid&&isset($tracks[$tid])&&is_array($tracks[$tid])){if(!empty($r['content_hash']))$tracks[$tid]['last_content_hash']=$r['content_hash'];$tracks[$tid]['last_content_excerpt']=mvm_newsradar_text_sub_v1((string)($r['excerpt']??''),0,2200);$tracks[$tid]['last_title']=(string)($r['title']??'');unset($tracks[$tid]['pending_content_hash'],$tracks[$tid]['pending_content_excerpt'],$tracks[$tid]['pending_title']);$tracks[$tid]['last_seen_ts']=time();$relevant=in_array($r['status'],['publiceerbaar','controleren'],true)&&((string)($r['event_type']??'nieuw')==='nieuw'||!empty($r['change_relevant']));if($r['status']==='niet_mierlo'){$tracks[$tid]['completed']=1;$r['lifecycle']='voltooid';}elseif($relevant){$tracks[$tid]['last_relevant_ts']=time();$max_age=max(1,(int)($settings['max_age_days']??14));$follow=max(1,(int)($settings['follow_days']??7));$pub=(int)($tracks[$tid]['published_ts']??0);$base=$pub?:((int)($tracks[$tid]['first_seen_ts']??time()));$tracks[$tid]['follow_until_ts']=min(time()+$follow*DAY_IN_SECONDS,$base+$max_age*DAY_IN_SECONDS);$tracks[$tid]['completed']=0;$r['email_pending']=1;}}$processed++;}
        unset($r);if($processed){update_option('mvm_bh_news_radar_results',$rows,false);update_option('mvm_bh_news_radar_tracks',$tracks,false);}return $processed;
    }
}

if ( ! function_exists( 'mvm_newsradar_remove_legacy_ai_once_v1' ) ) {
    function mvm_newsradar_remove_legacy_ai_once_v1() { global $wp_filter;$hook='mvm_bh_news_radar_ai_batch';if(empty($wp_filter[$hook])||empty($wp_filter[$hook]->callbacks[10]))return;foreach($wp_filter[$hook]->callbacks[10] as $cb){$fn=$cb['function']??null;if(is_array($fn)&&isset($fn[1])&&'news_radar_ai_batch'===(string)$fn[1])remove_action($hook,$fn,10);}}
}
