<?php

namespace MVM\Hub\Modules\Communications;

use MVM\Hub\Core\Capabilities;

if ( ! defined( 'ABSPATH' ) ) exit;

final class Mail_Read_REST_Controller {
    private const NAMESPACE = 'mvm-hub/v1';

    public function register_routes(): void {
        register_rest_route(self::NAMESPACE,'/communications/mail/folders',array('methods'=>\WP_REST_Server::READABLE,'callback'=>array($this,'folders'),'permission_callback'=>array($this,'can_read')));
        register_rest_route(self::NAMESPACE,'/communications/mail/messages',array(
            'methods'=>\WP_REST_Server::READABLE,'callback'=>array($this,'messages'),'permission_callback'=>array($this,'can_read'),
            'args'=>array(
                'folder'=>array('default'=>'','sanitize_callback'=>'sanitize_text_field'),
                'page'=>array('default'=>1,'sanitize_callback'=>'absint','validate_callback'=>static fn(mixed $v):bool=>(int)$v>=1&&(int)$v<=100),
                'per_page'=>array('default'=>20,'sanitize_callback'=>'absint','validate_callback'=>static fn(mixed $v):bool=>(int)$v>=1&&(int)$v<=50),
                'sort'=>array('default'=>'date','sanitize_callback'=>'sanitize_key'),
                'direction'=>array('default'=>'desc','sanitize_callback'=>'sanitize_key'),
                'state'=>array('default'=>'all','sanitize_callback'=>'sanitize_key'),
                'search'=>array('default'=>'','sanitize_callback'=>'sanitize_text_field'),
                'cursor'=>array('default'=>'','sanitize_callback'=>'sanitize_text_field'),
            ),
        ));
        register_rest_route(self::NAMESPACE,'/communications/mail/messages/(?P<id>[A-Za-z0-9._:-]+)',array(
            'methods'=>\WP_REST_Server::READABLE,'callback'=>array($this,'message'),'permission_callback'=>array($this,'can_read'),
            'args'=>array('id'=>array('sanitize_callback'=>'sanitize_text_field','validate_callback'=>static fn(mixed $v):bool=>is_string($v)&&strlen($v)<=260&&1===preg_match('/^[A-Za-z0-9._:-]+$/',$v))),
        ));
        register_rest_route(self::NAMESPACE,'/communications/mail/messages/(?P<id>[A-Za-z0-9._:-]+)/attachments/(?P<attachment>\d+(?:\.\d+){0,7})',array(
            'methods'=>\WP_REST_Server::READABLE,'callback'=>array($this,'attachment'),'permission_callback'=>array($this,'can_read'),
            'args'=>array(
                'id'=>array('sanitize_callback'=>'sanitize_text_field','validate_callback'=>static fn(mixed $v):bool=>is_string($v)&&strlen($v)<=260&&1===preg_match('/^[A-Za-z0-9._:-]+$/',$v)),
                'attachment'=>array('sanitize_callback'=>'sanitize_text_field','validate_callback'=>static fn(mixed $v):bool=>is_string($v)&&strlen($v)<=64&&1===preg_match('/^\d+(?:\.\d+){0,7}$/',$v)),
            ),
        ));
    }

    public function can_read(): bool { return is_user_logged_in() && Capabilities::can_read_mail(); }
    public function folders(): \WP_REST_Response|\WP_Error { return self::response($this->service()->folders(self::mailbox_id())); }
    public function messages(\WP_REST_Request $request):\WP_REST_Response|\WP_Error{
        $folder=trim((string)$request->get_param('folder'));if(''===$folder){$folder=$this->inbox_folder_id();if(''===$folder)return new \WP_Error('mvm_mail_inbox_missing','De inbox is niet beschikbaar.');}
        return self::response($this->service()->messages(self::mailbox_id(),$folder,array('page'=>(int)$request->get_param('page'),'per_page'=>(int)$request->get_param('per_page'),'sort'=>(string)$request->get_param('sort'),'direction'=>(string)$request->get_param('direction'),'state'=>(string)$request->get_param('state'),'search'=>(string)$request->get_param('search'),'cursor'=>(string)$request->get_param('cursor'))));
    }
    public function message(\WP_REST_Request $request):\WP_REST_Response|\WP_Error{return self::response($this->service()->message(self::mailbox_id(),(string)$request->get_param('id')));}
    public function attachment(\WP_REST_Request $request):\WP_REST_Response|\WP_Error{
        $payload=Communications_Service_Factory::incoming_attachment_service()->download(self::mailbox_id(),(string)$request->get_param('id'),(string)$request->get_param('attachment'));
        if(is_wp_error($payload))return $payload;$response=rest_ensure_response($payload);$response->header('Cache-Control','private, no-store, max-age=0, must-revalidate');$response->header('X-Content-Type-Options','nosniff');return $response;
    }
    private function inbox_folder_id():string{$p=$this->service()->folders(self::mailbox_id());if(is_wp_error($p))return '';foreach((array)($p['folders']??array())as$f)if(is_array($f)&&'inbox'===sanitize_key((string)($f['specialUse']??'')))return(string)($f['id']??'');return '';}
    private function service():Mail_Read_Service{return Communications_Service_Factory::mail_read_service();}
    private static function mailbox_id():string{return defined('MVM_HUB_MAILBOX_ID')?sanitize_key((string)MVM_HUB_MAILBOX_ID):'editorial';}
    private static function response(mixed $p):\WP_REST_Response|\WP_Error{return is_wp_error($p)?$p:rest_ensure_response($p);}
}
