<?php
// Consolidated from production Code Snippet #505.
defined( 'ABSPATH' ) || exit;
add_filter('rest_request_after_callbacks',static function($response,$handler,$request){
    if(!($request instanceof WP_REST_Request)||'POST'!==strtoupper($request->get_method()))return $response;
    if(!preg_match('#^/mvm-hub4/v1/source-admin/(\d+)$#',$request->get_route(),$m))return $response;
    if(is_wp_error($response)||!function_exists('mvm_hub_source_admin_payload_v1')||!function_exists('mvm_hub_source_admin_legacy_v1'))return $response;
    $data=mvm_hub_source_admin_payload_v1($request);if(is_wp_error($data))return $response;$id=absint($m[1]);if($id&&'publish'===get_post_status($id))mvm_hub_source_admin_legacy_v1($id,$data,true);return $response;
},20,3);