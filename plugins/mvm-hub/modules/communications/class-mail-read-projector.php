<?php

namespace MVM\Hub\Modules\Communications;

use MVM\Hub\Core\Communications_Policy;

if ( ! defined( 'ABSPATH' ) ) exit;

final class Mail_Read_Projector {
    /** @param array<int,array<string,mixed>> $folders @return array<int,array<string,mixed>> */
    public static function folders( array $folders ): array {
        $projected=array();foreach(array_slice($folders,0,200)as$folder){if(!is_array($folder))continue;$id=self::opaque_id((string)($folder['id']??''),128);if(''===$id)continue;$projected[]=array('id'=>$id,'name'=>mb_substr(sanitize_text_field((string)($folder['name']??'')),0,120),'specialUse'=>sanitize_key((string)($folder['specialUse']??$folder['type']??'')),'system'=>(bool)($folder['system']??false),'readOnly'=>(bool)($folder['readOnly']??false),'unreadCount'=>max(0,(int)($folder['unreadCount']??0)));}return array_values($projected);
    }

    /** @param array<string,mixed> $payload @return array<string,mixed> */
    public static function message_list( array $payload, array $query ): array {
        $source=is_array($payload['messages']??null)?$payload['messages']:array();$messages=array();
        foreach(array_slice($source,0,(int)$query['perPage']+1)as$message){if(!is_array($message))continue;$id=self::opaque_id((string)($message['id']??$message['uid']??''),260);if(''===$id)continue;$messages[]=array('id'=>$id,'subject'=>mb_substr(sanitize_text_field((string)($message['subject']??'')),0,255),'fromLabel'=>mb_substr(sanitize_text_field((string)($message['fromLabel']??$message['from']??'')),0,190),'date'=>mb_substr(sanitize_text_field((string)($message['date']??'')),0,80),'seen'=>(bool)($message['seen']??$message['isRead']??false),'flagged'=>(bool)($message['flagged']??false),'senderBlocked'=>(bool)($message['senderBlocked']??false),'sizeBytes'=>max(0,(int)($message['sizeBytes']??$message['size']??0)),'hasAttachments'=>(bool)($message['hasAttachments']??false));}
        $page=(int)$query['page'];$per=(int)$query['perPage'];$has=true===($payload['hasMore']??false)||max(0,(int)($payload['total']??0))>($page*$per)||count($messages)>$per;$messages=array_slice($messages,0,$per);
        return array('messages'=>array_values($messages),'page'=>$page,'perPage'=>$per,'hasMore'=>$has,'cursor'=>self::cursor((string)($payload['cursor']??$payload['nextCursor']??'')),'readonly'=>(bool)($payload['readonly']??false));
    }

    /** @param array<string,mixed> $message @return array<string,mixed>|\WP_Error */
    public static function message_detail( array $message ): array|\WP_Error {
        $id=self::opaque_id((string)($message['id']??$message['uid']??''),260);if(''===$id)return new \WP_Error('mvm_mail_provider_payload','Het bericht kon niet veilig worden weergegeven.');
        $html=(string)($message['html']??$message['htmlBody']??'');$text=(string)($message['text']??$message['textBody']??$message['body']??'');
        return array(
            'id'=>$id,
            'folderId'=>self::opaque_id((string)($message['folderId']??''),128),
            'subject'=>mb_substr(sanitize_text_field((string)($message['subject']??'')),0,255),
            'fromLabel'=>mb_substr(sanitize_text_field((string)($message['fromLabel']??$message['from']??'')),0,190),
            'fromAddress'=>self::email($message['fromAddress']??$message['from_email']??''),
            'to'=>self::emails($message['to']??array()),
            'cc'=>self::emails($message['cc']??array()),
            'date'=>mb_substr(sanitize_text_field((string)($message['date']??'')),0,80),
            'seen'=>(bool)($message['seen']??$message['isRead']??false),
            'flagged'=>(bool)($message['flagged']??false),
            'htmlBody'=>''!==$html?Communications_Policy::sanitize_incoming_html($html):'',
            'textBody'=>sanitize_textarea_field(wp_strip_all_tags($text)),
            'messageId'=>self::message_id((string)($message['messageId']??'')),
            'inReplyTo'=>self::message_id((string)($message['inReplyTo']??'')),
            'references'=>self::message_ids($message['references']??array()),
            'attachments'=>self::attachments($message['attachments']??array()),
            'remoteImagesBlocked'=>!Communications_Policy::remote_images_allowed_by_default(),
        );
    }

    /** @param mixed $attachments @return array<int,array<string,mixed>> */ private static function attachments(mixed $attachments):array{if(!is_array($attachments))return array();$safe=array();foreach(array_slice($attachments,0,100)as$a){if(!is_array($a))continue;$id=self::opaque_id((string)($a['id']??$a['part']??''),128);if(''===$id)continue;$safe[]=array('id'=>$id,'name'=>sanitize_file_name((string)($a['name']??$a['filename']??'')),'mimeType'=>sanitize_mime_type((string)($a['mimeType']??$a['mime']??$a['type']??'')),'sizeBytes'=>max(0,(int)($a['sizeBytes']??$a['size']??0)));}return array_values($safe);}
    /** @param mixed $values @return array<int,string> */ private static function emails(mixed $values):array{if(!is_array($values))$values=is_string($values)&&''!==trim($values)?array($values):array();$safe=array();foreach(array_slice($values,0,50)as$v){$e=self::email($v);if(''!==$e)$safe[]=$e;}return array_values(array_unique($safe));}
    private static function email(mixed $v):string{if(!is_string($v)||str_contains($v,"\r")||str_contains($v,"\n"))return '';$e=sanitize_email(trim($v));return false!==is_email($e)?strtolower($e):'';}
    /** @param mixed $values @return array<int,string> */ private static function message_ids(mixed $values):array{if(!is_array($values))return array();$safe=array();foreach(array_slice($values,-20)as$v){$id=self::message_id((string)$v);if(''!==$id)$safe[]=$id;}return array_values(array_unique($safe));}
    private static function message_id(string $v):string{$v=trim($v);return strlen($v)<=998&&1===preg_match('/^<[^<>\s]+@[^<>\s]+>$/',$v)?$v:'';}
    private static function opaque_id(string $v,int $max=128):string{$v=trim($v);return strlen($v)<=$max&&1===preg_match('/^[A-Za-z0-9._:-]+$/',$v)?$v:'';}
    private static function cursor(string $v):string{$v=trim($v);return strlen($v)<=256&&1===preg_match('/^[A-Za-z0-9._~:-]+$/',$v)?$v:'';}
    private function __construct() {}
}
