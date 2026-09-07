<?php

namespace MVM\Hub\Integrations\Mail;

use MVM\Hub\Core\Capabilities;

if ( ! defined( 'ABSPATH' ) ) exit;

final class Configured_Mailbox_Access implements Mailbox_Access {
    public function authorize_read(int $user_id,string $mailbox_id):true|\WP_Error{return $this->authorize($user_id,$mailbox_id,Capabilities::MAIL_READ);}
    public function authorize_compose(int $user_id,string $mailbox_id):true|\WP_Error{return $this->authorize($user_id,$mailbox_id,Capabilities::MAIL_COMPOSE);}
    public function authorize_manage_folders(int $user_id,string $mailbox_id):true|\WP_Error{return $this->authorize($user_id,$mailbox_id,Capabilities::MAIL_MANAGE_FOLDERS);}
    public function authorize_message(int $user_id,string $mailbox_id,string $message_id):true|\WP_Error{$read=$this->authorize_read($user_id,$mailbox_id);if(is_wp_error($read))return $read;$message_id=trim($message_id);if(''===$message_id||strlen($message_id)>260||1!==preg_match('/^[A-Za-z0-9._:-]+$/',$message_id))return new \WP_Error('mvm_mail_message_forbidden','Dit bericht is niet beschikbaar.');return true;}
    private function authorize(int $user_id,string $mailbox_id,string $capability):true|\WP_Error{$configured=defined('MVM_HUB_MAILBOX_ID')?sanitize_key((string)MVM_HUB_MAILBOX_ID):'editorial';$allowed=$user_id>0&&$user_id===get_current_user_id()&&sanitize_key($mailbox_id)===$configured&&is_user_logged_in()&&Capabilities::can_access_mail()&&(current_user_can('manage_options')||current_user_can(Capabilities::COMMUNICATIONS_ADMIN)||current_user_can($capability));return $allowed?true:new \WP_Error('mvm_mailbox_forbidden','Geen toegang tot deze mailboxactie.');}
}
