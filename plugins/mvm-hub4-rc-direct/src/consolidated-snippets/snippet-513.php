<?php
// Consolidated from production Code Snippet #513.
defined( 'ABSPATH' ) || exit;

if(!function_exists('mvm_ai_agenda_ai_health_v4')){function mvm_ai_agenda_ai_health_v4(){
 $rows=get_option('mvm_bh_news_radar_results',[]);$pending=0;$rate=0;$quota=0;
 foreach(is_array($rows)?$rows:[] as $r){if(!is_array($r)||empty($r['ai_pending']))continue;$pending++;$reason=(string)($r['reason']??'');if(false!==strpos($reason,'429')||false!==stripos($reason,'OpenAI tijdelijk niet beschikbaar'))$rate++;if(false!==stripos($reason,'exceeded your current quota')||false!==stripos($reason,'no credits remaining'))$quota++;}
 return ['pending'=>$pending,'rate_errors'=>$rate,'quota_errors'=>$quota,'blocked'=>($quota>0||($pending>=25&&$rate>=10))];
}}

if(!function_exists('mvm_ai_agenda_ai_health_v7')){function mvm_ai_agenda_ai_health_v7(){
 $base=mvm_ai_agenda_ai_health_v4();$rows=get_option('mvm_bh_news_radar_results',[]);
 $g=get_option('mvm_newsradar_gemini_fallback_status_v1',[]);$status_ts=0;if(is_array($g)&&!empty($g['time'])){$status_ts=strtotime((string)$g['time']);if(!$status_ts)$status_ts=0;}
 $recent_row_ts=0;foreach(is_array($rows)?$rows:[] as $r){if(!is_array($r)||(string)($r['ai_provider']??'')!=='google'||empty($r['ai_checked_at']))continue;$ts=strtotime((string)$r['ai_checked_at']);if($ts>$recent_row_ts)$recent_row_ts=$ts;}
 $gemini_ok=(is_array($g)&&!empty($g['ok'])&&$status_ts>=(time()-15*MINUTE_IN_SECONDS))||$recent_row_ts>=(time()-15*MINUTE_IN_SECONDS);
 $pending=(int)($base['pending']??0);$quota=(int)($base['quota_errors']??0);$rate=(int)($base['rate_errors']??0);$backlog_blocked=$pending>=25;$provider_blocked=!$gemini_ok&&($quota>0||($pending>=10&&$rate>=5));
 return array_merge($base,['gemini_ok'=>$gemini_ok,'gemini_status_ts'=>$status_ts,'gemini_recent_row_ts'=>$recent_row_ts,'backlog_limit'=>25,'backlog_blocked'=>$backlog_blocked,'provider_blocked'=>$provider_blocked,'blocked'=>($backlog_blocked||$provider_blocked)]);
}}

if(!function_exists('mvm_ai_agenda_scan_v7')){function mvm_ai_agenda_scan_v7(){
 $s=mvm_ai_agenda_settings_v1();if(empty($s['enabled']))return ['added'=>0,'paused'=>false,'message'=>'AI-agendacrawl staat uit.'];$h=mvm_ai_agenda_ai_health_v7();
 if(!empty($h['blocked'])){$s['last_run']=time();if(!empty($h['backlog_blocked'])){$s['last_message']='AI-agendacrawl tijdelijk gepauzeerd terwijl de bestaande Nieuwsradar-wachtrij wordt verwerkt: '.$h['pending'].' item(s) wachten nog op AI. De crawl hervat automatisch zodra de wachtrij onder '.$h['backlog_limit'].' items komt.';}elseif(!empty($h['quota_errors'])){$s['last_message']='AI-agendacrawl gepauzeerd: OpenAI heeft geen credits en er is geen recente succesvolle Gemini-verwerking. De schakelaar blijft aan; automatische retry blijft actief.';}else{$s['last_message']='AI-agendacrawl tijdelijk gepauzeerd omdat de AI-verwerking niet gezond genoeg is. Automatische retry blijft actief.';}mvm_ai_agenda_save_settings_v1($s);return ['added'=>0,'checked'=>0,'paused'=>true,'pendingAi'=>$h['pending'],'rateErrors'=>$h['rate_errors'],'quotaErrors'=>$h['quota_errors'],'geminiOk'=>$h['gemini_ok'],'message'=>$s['last_message']];}
 if(function_exists('mvm_ai_agenda_scan_v3'))return mvm_ai_agenda_scan_v3();if(function_exists('mvm_ai_agenda_scan_v2'))return mvm_ai_agenda_scan_v2();return mvm_ai_agenda_scan_v1();
}}

add_action('init',static function(){remove_action('mvm_hub_ai_agenda_scan_v1','mvm_ai_agenda_scan_v1');remove_action('mvm_hub_ai_agenda_scan_v1','mvm_ai_agenda_scan_v2');remove_action('mvm_hub_ai_agenda_scan_v1','mvm_ai_agenda_scan_v3');remove_action('mvm_hub_ai_agenda_scan_v1','mvm_ai_agenda_scan_v4');remove_action('mvm_hub_ai_agenda_scan_v1','mvm_ai_agenda_scan_v6');add_action('mvm_hub_ai_agenda_scan_v1','mvm_ai_agenda_scan_v7');},4);

add_action('rest_api_init',static function(){if(!class_exists('MvM_Hub4_Security')||!class_exists('MvM_Hub4_Capabilities'))return;$manage=MvM_Hub4_Security::require_capability(MvM_Hub4_Capabilities::SOURCE_MANAGE);register_rest_route('mvm-hub4/v1','/agenda-crawl/run',['methods'=>WP_REST_Server::CREATABLE,'permission_callback'=>$manage,'callback'=>static function(){ $s=mvm_ai_agenda_settings_v1();if(empty($s['enabled']))return new WP_Error('mvm_agenda_disabled','Zet AI-agendacrawl eerst aan.',['status'=>400]);return rest_ensure_response(mvm_ai_agenda_scan_v7());}],true);},103);