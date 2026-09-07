<?php

namespace MVM\Hub\Integrations\Mail;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Decorates the IMAP/SMTP provider so an IMAP UID is always bound to the folder
 * in which it was obtained. Public message IDs are opaque `folderId.uid` values.
 */
final class Folder_Scoped_IMAP_Mail_Provider implements Mail_Provider {
    public function __construct(
        private readonly Dedicated_IMAP_SMTP_Mail_Provider $inner
    ) {}

    public function list_folders( string $mailbox_id ): array { return $this->inner->list_folders( $mailbox_id ); }

    public function list_messages( string $mailbox_id, string $folder_id, array $query = array() ): array {
        $payload = $this->inner->list_messages( $mailbox_id, $folder_id, $query );
        $messages = is_array( $payload['messages'] ?? null ) ? $payload['messages'] : array();
        $remote = self::folder_from_id( $folder_id );
        $stream = is_wp_error( $remote ) ? $remote : $this->connect( $mailbox_id, $remote );

        try {
            foreach ( $messages as &$message ) {
                if ( ! is_array( $message ) ) continue;
                $uid = (string) ( $message['id'] ?? '' );
                if ( ! ctype_digit( $uid ) || (int) $uid <= 0 ) continue;

                $message['id'] = $folder_id . '.' . $uid;
                $message['hasAttachments'] = false;
                if ( ! is_wp_error( $stream ) ) {
                    $structure = @imap_fetchstructure( $stream, (int) $uid, FT_UID );
                    $message['hasAttachments'] = is_object( $structure ) && self::structure_has_attachment( $structure );
                }
            }
            unset( $message );
        } finally {
            if ( ! is_wp_error( $stream ) ) imap_close( $stream );
        }

        $payload['messages'] = $messages;
        return $payload;
    }

    /** @return array<string,mixed>|\WP_Error */
    public function get_message( string $mailbox_id, string $message_id ): array|\WP_Error {
        $identity = self::identity( $message_id );
        if ( is_wp_error( $identity ) ) {
            // Backward-compatible transition for the old inbox-only bridge.
            return ctype_digit( $message_id ) ? $this->inner->get_message( $mailbox_id, $message_id ) : $identity;
        }
        $stream = $this->connect( $mailbox_id, $identity['remote'] );
        if ( is_wp_error( $stream ) ) return $stream;
        try {
            $uid = $identity['uid'];
            $sequence = imap_msgno( $stream, $uid );
            if ( $sequence <= 0 ) return new \WP_Error( 'mvm_mail_message_missing', 'Het bericht is niet beschikbaar.' );
            $overview = imap_fetch_overview( $stream, (string) $uid, FT_UID );
            $row = is_array( $overview ) && isset( $overview[0] ) ? $overview[0] : null;
            if ( ! is_object( $row ) ) return new \WP_Error( 'mvm_mail_message_missing', 'Het bericht is niet beschikbaar.' );
            $header = imap_headerinfo( $stream, $sequence );
            $structure = imap_fetchstructure( $stream, $uid, FT_UID );
            $parts = $structure ? self::extract_parts( $stream, $uid, $structure ) : array( 'text'=>'','html'=>'','attachments'=>array() );
            $raw_header = (string) imap_fetchheader( $stream, $uid, FT_UID );
            return array(
                'id'          => $message_id,
                'subject'     => self::decode_header( (string) ( $row->subject ?? '' ) ),
                'from'        => self::decode_header( (string) ( $row->from ?? '' ) ),
                'fromAddress' => self::first_address( is_object($header) ? ($header->from ?? array()) : array() ),
                'to'          => self::addresses( is_object($header) ? ($header->to ?? array()) : array() ),
                'cc'          => self::addresses( is_object($header) ? ($header->cc ?? array()) : array() ),
                'date'        => sanitize_text_field( (string) ( $row->date ?? '' ) ),
                'seen'        => ! empty( $row->seen ),
                'flagged'     => ! empty( $row->flagged ),
                'size'        => max(0,(int)($row->size??0)),
                'text'        => (string)$parts['text'],
                'html'        => (string)$parts['html'],
                'attachments' => (array)$parts['attachments'],
                'messageId'   => self::header_message_id( $raw_header, 'Message-ID' ),
                'inReplyTo'   => self::header_message_id( $raw_header, 'In-Reply-To' ),
                'references'  => self::header_references( $raw_header ),
                'folderId'    => $identity['folderId'],
            );
        } finally { imap_close( $stream ); }
    }

    public function save_draft( string $mailbox_id, array $draft, ?string $draft_id = null ): array|\WP_Error { return $this->inner->save_draft($mailbox_id,$draft,$draft_id); }
    public function create_folder( string $mailbox_id, string $name, ?string $parent_id = null ): array|\WP_Error { return $this->inner->create_folder($mailbox_id,$name,$parent_id); }
    public function rename_folder( string $mailbox_id, string $folder_id, string $name ): bool|\WP_Error { return $this->inner->rename_folder($mailbox_id,$folder_id,$name); }
    public function delete_folder( string $mailbox_id, string $folder_id ): bool|\WP_Error { return $this->inner->delete_folder($mailbox_id,$folder_id); }

    public function move_message( string $mailbox_id, string $message_id, string $folder_id ): bool|\WP_Error {
        $source = self::identity($message_id); $target = self::folder_from_id($folder_id);
        if(is_wp_error($source)||is_wp_error($target))return new \WP_Error('mvm_mail_move_invalid','Ongeldige verplaatsactie.');
        $stream=$this->connect($mailbox_id,$source['remote']); if(is_wp_error($stream))return $stream;
        try{ $ok=imap_mail_move($stream,(string)$source['uid'],$target,CP_UID); if($ok){imap_expunge($stream);return true;} return new \WP_Error('mvm_mail_move_failed','Bericht kon niet worden verplaatst.'); } finally { imap_close($stream); }
    }
    public function set_read_state( string $mailbox_id, string $message_id, bool $is_read ): bool|\WP_Error { return $this->set_flag($mailbox_id,$message_id,'\\Seen',$is_read); }
    public function set_flagged_state( string $mailbox_id, string $message_id, bool $is_flagged ): bool|\WP_Error { return $this->set_flag($mailbox_id,$message_id,'\\Flagged',$is_flagged); }
    public function deliver( string $mailbox_id, array $message, string $idempotency_key ): array|\WP_Error { return $this->inner->deliver($mailbox_id,$message,$idempotency_key); }
    public function capabilities( string $mailbox_id ): array { return $this->inner->capabilities($mailbox_id); }

    /** @return array{folderId:string,remote:string,uid:int}|\WP_Error */
    private static function identity(string $message_id):array|\WP_Error{
        $message_id=trim($message_id); if(strlen($message_id)>260||1!==preg_match('/^([A-Za-z0-9_-]+)\.(\d+)$/',$message_id,$m))return new \WP_Error('mvm_mail_message_invalid','Ongeldig bericht-ID.');
        $remote=self::folder_from_id($m[1]); if(is_wp_error($remote))return $remote; $uid=(int)$m[2]; if($uid<1)return new \WP_Error('mvm_mail_message_invalid','Ongeldig bericht-ID.');
        return array('folderId'=>$m[1],'remote'=>$remote,'uid'=>$uid);
    }
    /** @return string|\WP_Error */ private static function folder_from_id(string $id):string|\WP_Error{$id=trim($id);if(''===$id||strlen($id)>128||1!==preg_match('/^[A-Za-z0-9_-]+$/',$id))return new \WP_Error('mvm_mail_folder_invalid','Ongeldige mailboxmap.');$pad=strlen($id)%4;$encoded=strtr($id,'-_','+/').(0===$pad?'':str_repeat('=',4-$pad));$decoded=base64_decode($encoded,true);if(false===$decoded||''===$decoded||str_contains($decoded,"\0")||str_contains($decoded,"\r")||str_contains($decoded,"\n"))return new \WP_Error('mvm_mail_folder_invalid','Ongeldige mailboxmap.');return $decoded;}
    /** @return resource|\IMAP\Connection|\WP_Error */ private function connect(string $mailbox_id,string $remote):mixed{if(!function_exists('imap_open')||!$this->configured($mailbox_id))return new \WP_Error('mvm_mail_imap_unavailable','IMAP is niet beschikbaar of niet geconfigureerd.');$stream=@imap_open($this->server_prefix().$remote,(string)MVM_HUB_IMAP_USERNAME,(string)MVM_HUB_IMAP_PASSWORD,0,1);return false===$stream?new \WP_Error('mvm_mail_imap_connect','De mailbox kon niet veilig worden geopend.'):$stream;}
    private function configured(string $mailbox_id):bool{$configured=defined('MVM_HUB_MAILBOX_ID')?sanitize_key((string)MVM_HUB_MAILBOX_ID):'editorial';if(sanitize_key($mailbox_id)!==$configured)return false;foreach(array('MVM_HUB_IMAP_HOST','MVM_HUB_IMAP_PORT','MVM_HUB_IMAP_USERNAME','MVM_HUB_IMAP_PASSWORD')as$c)if(!defined($c)||''===trim((string)constant($c)))return false;return true;}
    private function server_prefix():string{$host=preg_replace('/[^A-Za-z0-9.:-]/','',(string)MVM_HUB_IMAP_HOST);$port=max(1,min(65535,(int)MVM_HUB_IMAP_PORT));$enc=defined('MVM_HUB_IMAP_ENCRYPTION')?sanitize_key((string)MVM_HUB_IMAP_ENCRYPTION):'ssl';$mode='tls'===$enc?'/imap/tls':('none'===$enc?'/imap':'/imap/ssl');return '{'.$host.':'.$port.$mode.'}';}
    private function set_flag(string $mailbox_id,string $message_id,string $flag,bool $enabled):bool|\WP_Error{$id=self::identity($message_id);if(is_wp_error($id))return $id;$stream=$this->connect($mailbox_id,$id['remote']);if(is_wp_error($stream))return $stream;try{$ok=$enabled?imap_setflag_full($stream,(string)$id['uid'],$flag,ST_UID):imap_clearflag_full($stream,(string)$id['uid'],$flag,ST_UID);return $ok?true:new \WP_Error('mvm_mail_flag_failed','Berichtstatus kon niet worden gewijzigd.');}finally{imap_close($stream);}}

    private static function structure_has_attachment(object $structure): bool {
        if ( '' !== self::part_filename( $structure ) || 'attachment' === strtolower( (string) ( $structure->disposition ?? '' ) ) ) {
            return true;
        }
        $parts = isset( $structure->parts ) && is_array( $structure->parts ) ? $structure->parts : array();
        foreach ( $parts as $part ) {
            if ( is_object( $part ) && self::structure_has_attachment( $part ) ) return true;
        }
        return false;
    }

    /** @return array{text:string,html:string,attachments:array<int,array<string,mixed>>} */
    private static function extract_parts(mixed $stream,int $uid,object $structure,string $prefix=''):array{
        $r=array('text'=>'','html'=>'','attachments'=>array());$parts=isset($structure->parts)&&is_array($structure->parts)?$structure->parts:array();
        if(!$parts){$body=(string)imap_body($stream,$uid,FT_UID|FT_PEEK);$d=self::decode_transfer($body,(int)($structure->encoding??0));$sub=strtolower((string)($structure->subtype??'plain'));$r['html'===$sub?'html':'text']=$d;return $r;}
        foreach($parts as $i=>$part){if(!is_object($part))continue;$number=''===$prefix?(string)($i+1):$prefix.'.'.($i+1);if(isset($part->parts)&&is_array($part->parts)){$n=self::extract_parts($stream,$uid,$part,$number);$r['text'].=$n['text'];$r['html'].=$n['html'];$r['attachments']=array_merge($r['attachments'],$n['attachments']);continue;}$filename=self::part_filename($part);$type=(int)($part->type??0);$sub=strtolower((string)($part->subtype??''));if(''!==$filename){$r['attachments'][]=array('id'=>$number,'name'=>sanitize_file_name($filename),'mime'=>self::mime_type($type,$sub),'size'=>max(0,(int)($part->bytes??0)));continue;}if(0===$type&&in_array($sub,array('plain','html'),true)){$body=(string)imap_fetchbody($stream,$uid,$number,FT_UID|FT_PEEK);$r['html'===$sub?'html':'text'].=self::decode_transfer($body,(int)($part->encoding??0));}}
        return $r;
    }
    private static function decode_transfer(string $body,int $encoding):string{return match($encoding){3=>(string)base64_decode($body,true),4=>quoted_printable_decode($body),default=>$body};}
    private static function part_filename(object $part):string{foreach(array('dparameters','parameters')as$p)foreach(is_array($part->{$p}??null)?$part->{$p}:array()as$param){$a=strtolower((string)($param->attribute??''));if(in_array($a,array('filename','name'),true))return self::decode_header((string)($param->value??''));}return '';}
    private static function mime_type(int $type,string $sub):string{$top=array(0=>'text',1=>'multipart',2=>'message',3=>'application',4=>'audio',5=>'image',6=>'video')[$type]??'application';return sanitize_mime_type($top.'/'.(''!==$sub?$sub:'octet-stream'));}
    private static function decode_header(string $value):string{if(''===$value||!function_exists('imap_mime_header_decode'))return sanitize_text_field($value);$parts=imap_mime_header_decode($value);$out='';foreach(is_array($parts)?$parts:array()as$part){$text=(string)($part->text??'');$charset=strtoupper((string)($part->charset??'UTF-8'));if(''!==$text&&!in_array($charset,array('DEFAULT','UTF-8'),true)&&function_exists('mb_convert_encoding'))$text=@mb_convert_encoding($text,'UTF-8',$charset);$out.=$text;}return sanitize_text_field($out);}
    /** @return array<int,string> */ private static function addresses(mixed $values):array{$r=array();foreach(is_array($values)?array_slice($values,0,50):array()as$a){$email=sanitize_email(sanitize_text_field((string)($a->mailbox??'')).'@'.sanitize_text_field((string)($a->host??'')));if(''!==$email&&false!==is_email($email))$r[]=strtolower($email);}return array_values(array_unique($r));}
    private static function first_address(mixed $v):string{$a=self::addresses($v);return(string)($a[0]??'');}
    private static function header_message_id(string $headers,string $name):string{if(1!==preg_match('/^'.preg_quote($name,'/').':\s*(<[^<>\s]+@[^<>\s]+>)/mi',$headers,$m))return '';return mb_substr((string)$m[1],0,998);}
    /** @return array<int,string> */ private static function header_references(string $headers):array{if(1!==preg_match('/^References:\s*([^\r\n]*(?:\r?\n[ \t]+[^\r\n]*)*)/mi',$headers,$m))return array();preg_match_all('/<[^<>\s]+@[^<>\s]+>/',(string)$m[1],$ids);return array_slice(array_values(array_unique($ids[0]??array())),-20);}
}