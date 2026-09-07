<?php

namespace MVM\Hub\Integrations\Mail;

use MVM\Hub\Core\Hub_Security_Policy;

if ( ! defined( 'ABSPATH' ) ) exit;

/** Session-token bound reauthentication for sensitive Communications actions. */
final class WordPress_Step_Up_Authenticator {
    private const MAX_ATTEMPTS = 5;
    private const ATTEMPT_WINDOW = 15 * MINUTE_IN_SECONDS;

    public static function register():void{
        add_filter('mvm_hub_communications_step_up_authorized_v1',array(self::class,'filter_authorized'),10,3);
        add_action('rest_api_init',array(self::class,'register_route'));
    }

    public static function register_route():void{
        register_rest_route('mvm-hub/v1','/communications/step-up',array(
            'methods'=>\WP_REST_Server::CREATABLE,
            'callback'=>array(self::class,'reauthenticate'),
            'permission_callback'=>array(self::class,'can_reauthenticate'),
        ));
    }

    public static function can_reauthenticate(\WP_REST_Request $request):bool|\WP_Error{
        if(!is_user_logged_in())return new \WP_Error('mvm_step_up_login','Log opnieuw in.',array('status'=>401));
        $nonce=(string)$request->get_header('X-WP-Nonce');if(''===$nonce||!wp_verify_nonce($nonce,'wp_rest'))return new \WP_Error('mvm_step_up_nonce','De beveiligingstoken is ongeldig of verlopen.',array('status'=>403));
        return true;
    }

    public static function reauthenticate(\WP_REST_Request $request):\WP_REST_Response|\WP_Error{
        $input=$request->get_json_params();$input=is_array($input)?$input:array();$action=sanitize_key((string)($input['action']??''));
        if(!Hub_Security_Policy::requires_step_up($action))return new \WP_Error('mvm_step_up_action','Deze actie ondersteunt geen herauthenticatie.',array('status'=>400));
        $user_id=get_current_user_id();$session=self::session_token();if($user_id<=0||''===$session)return new \WP_Error('mvm_step_up_session','De sessie is niet geldig.',array('status'=>401));
        $attempt_key=self::attempt_key($user_id,$session);$attempts=max(0,(int)get_transient($attempt_key));if($attempts>=self::MAX_ATTEMPTS)return new \WP_Error('mvm_step_up_rate','Te veel mislukte bevestigingen. Probeer later opnieuw.',array('status'=>429));
        $password=(string)($input['password']??'');if(''===$password||strlen($password)>4096){self::record_failure($attempt_key,$attempts);return new \WP_Error('mvm_step_up_invalid','De identiteitsbevestiging is mislukt.',array('status'=>403));}
        $user=get_userdata($user_id);if(!$user instanceof \WP_User||!wp_check_password($password,(string)$user->user_pass,$user_id)){self::record_failure($attempt_key,$attempts);return new \WP_Error('mvm_step_up_invalid','De identiteitsbevestiging is mislukt.',array('status'=>403));}
        delete_transient($attempt_key);set_transient(self::authorization_key($user_id,$session,$action),1,Hub_Security_Policy::STEP_UP_WINDOW_SECONDS);
        return rest_ensure_response(array('authorized'=>true,'action'=>$action,'expiresIn'=>Hub_Security_Policy::STEP_UP_WINDOW_SECONDS));
    }

    public static function filter_authorized(bool $allowed,int $user_id,string $action):bool{
        if($allowed)return true;$session=self::session_token();if($user_id<=0||$user_id!==get_current_user_id()||''===$session)return false;$action=sanitize_key($action);if(!Hub_Security_Policy::requires_step_up($action))return false;return 1===(int)get_transient(self::authorization_key($user_id,$session,$action));
    }

    private static function record_failure(string $key,int $attempts):void{set_transient($key,$attempts+1,self::ATTEMPT_WINDOW);}
    private static function session_token():string{return function_exists('wp_get_session_token')?(string)wp_get_session_token():'';}
    private static function authorization_key(int $user_id,string $session,string $action):string{return 'mvm_step_'.substr(wp_hash($user_id.'|'.$session.'|'.$action,'mvm_hub_step_up'),0,40);}
    private static function attempt_key(int $user_id,string $session):string{return 'mvm_step_try_'.substr(wp_hash($user_id.'|'.$session,'mvm_hub_step_up_attempt'),0,36);}
    private function __construct(){}
}
