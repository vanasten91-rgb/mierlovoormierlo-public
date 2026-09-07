<?php

namespace MVM\Hub\Modules\Communications;

use MVM\Hub\Integrations\Mail\Attachment_Scanner;
use MVM\Hub\Integrations\Mail\ClamAV_Attachment_Scanner;
use MVM\Hub\Integrations\Mail\Cloudmersive_Attachment_Scanner;
use MVM\Hub\Integrations\Mail\Configured_Mailbox_Access;
use MVM\Hub\Integrations\Mail\Default_Attachment_Normalizer;
use MVM\Hub\Integrations\Mail\Default_Communications_Action_Security;
use MVM\Hub\Integrations\Mail\Default_Mail_Delivery_Security;
use MVM\Hub\Integrations\Mail\Dedicated_IMAP_SMTP_Mail_Provider;
use MVM\Hub\Integrations\Mail\Dedicated_SMTP_Mail_Provider;
use MVM\Hub\Integrations\Mail\Encrypted_Filesystem_Draft_Store;
use MVM\Hub\Integrations\Mail\Filesystem_Private_Attachment_Store;
use MVM\Hub\Integrations\Mail\Filter_Attachment_Scanner;
use MVM\Hub\Integrations\Mail\Folder_Scoped_IMAP_Mail_Provider;
use MVM\Hub\Integrations\Mail\Hub4_Communications_Audit;
use MVM\Hub\Integrations\Mail\Legacy_Readonly_Mail_Provider;
use MVM\Hub\Integrations\Mail\Mail_Provider;
use MVM\Hub\Integrations\Mail\Mailbox_Access;
use MVM\Hub\Integrations\Mail\Private_HTTP_Attachment_Scanner;
use MVM\Hub\Integrations\Mail\Sent_Archiving_Mail_Provider;
use MVM\Hub\Integrations\Mail\V5_IMAP_Attachment_Reader;
use MVM\Hub\Integrations\PeepSo\Internal_Message_Provider;
use MVM\Hub\Integrations\PeepSo\Legacy_Readonly_Internal_Message_Provider;
use MVM\Hub\Integrations\PeepSo\PeepSo8_Internal_Message_Provider;

if ( ! defined( 'ABSPATH' ) ) exit;

final class Communications_Service_Factory {
    public static function mail_read_service(): Mail_Read_Service { return new Mail_Read_Service( self::mail_provider(), self::mailbox_access() ); }
    public static function draft_service(): Mail_Draft_Service { return new Mail_Draft_Service( self::draft_store(), self::attachment_store(), self::audit() ); }
    public static function attachment_service(): Mail_Attachment_Service { return new Mail_Attachment_Service( self::draft_store(), self::attachment_store(), new Default_Attachment_Normalizer(), self::attachment_scanner(), self::audit() ); }
    public static function incoming_attachment_service(): Incoming_Mail_Attachment_Service { return new Incoming_Mail_Attachment_Service( self::mailbox_access(), self::action_security(), self::attachment_scanner(), self::audit(), new V5_IMAP_Attachment_Reader() ); }
    public static function folder_service(): Mail_Folder_Service { return new Mail_Folder_Service( self::mail_provider(), self::mailbox_access(), self::action_security(), self::audit() ); }
    public static function delivery_service(): Mail_Delivery_Service {
        $audit = self::audit();
        $action_security = self::action_security();
        return new Mail_Delivery_Service( self::mail_provider(), self::attachment_store(), new Default_Mail_Delivery_Security( $audit, $action_security ) );
    }
    public static function internal_message_read_service(): Internal_Message_Read_Service { return new Internal_Message_Read_Service( self::internal_message_provider() ); }
    public static function internal_message_service(): Internal_Message_Service { return new Internal_Message_Service( self::internal_message_provider(), self::action_security(), self::audit() ); }

    public static function mail_provider(): Mail_Provider {
        $legacy = new Legacy_Readonly_Mail_Provider();
        $smtp   = new Dedicated_SMTP_Mail_Provider( $legacy );
        if ( self::imap_configured() ) {
            $imap    = new Dedicated_IMAP_SMTP_Mail_Provider( $smtp );
            $scoped  = new Folder_Scoped_IMAP_Mail_Provider( $imap );
            $default = new Sent_Archiving_Mail_Provider( $scoped );
        } else {
            $default = $smtp;
        }
        $provided = apply_filters( 'mvm_hub_mail_provider_v1', $default );
        return $provided instanceof Mail_Provider ? $provided : $default;
    }

    public static function mailbox_access(): Mailbox_Access {
        $default = new Configured_Mailbox_Access();
        $provided = apply_filters( 'mvm_hub_mailbox_access_v1', $default );
        return $provided instanceof Mailbox_Access ? $provided : $default;
    }

    public static function internal_message_provider(): Internal_Message_Provider {
        $default = PeepSo8_Internal_Message_Provider::supported()
            ? new PeepSo8_Internal_Message_Provider()
            : new Legacy_Readonly_Internal_Message_Provider();
        $provided = apply_filters( 'mvm_hub_internal_message_provider_v1', $default );
        return $provided instanceof Internal_Message_Provider ? $provided : $default;
    }

    private static function attachment_scanner(): Attachment_Scanner {
        if ( ! class_exists( Cloudmersive_Attachment_Scanner::class ) ) {
            require_once MVM_HUB_DIR . 'integrations/mail/class-cloudmersive-attachment-scanner.php';
        }

        if ( ClamAV_Attachment_Scanner::configured() ) {
            $default = new ClamAV_Attachment_Scanner();
        } elseif ( Private_HTTP_Attachment_Scanner::configured() ) {
            $default = new Private_HTTP_Attachment_Scanner();
        } elseif ( Cloudmersive_Attachment_Scanner::configured() ) {
            $default = new Cloudmersive_Attachment_Scanner();
        } else {
            $default = new Filter_Attachment_Scanner();
        }
        $provided = apply_filters( 'mvm_hub_attachment_scanner_v1', $default );
        return $provided instanceof Attachment_Scanner ? $provided : $default;
    }

    private static function draft_store(): Encrypted_Filesystem_Draft_Store { return new Encrypted_Filesystem_Draft_Store(); }
    private static function attachment_store(): Filesystem_Private_Attachment_Store { return new Filesystem_Private_Attachment_Store(); }
    private static function audit(): Hub4_Communications_Audit { return new Hub4_Communications_Audit(); }
    private static function action_security(): Default_Communications_Action_Security { return new Default_Communications_Action_Security(); }
    private static function imap_configured(): bool {
        foreach ( array( 'MVM_HUB_IMAP_HOST', 'MVM_HUB_IMAP_PORT', 'MVM_HUB_IMAP_USERNAME', 'MVM_HUB_IMAP_PASSWORD' ) as $constant ) {
            if ( ! defined( $constant ) || '' === trim( (string) constant( $constant ) ) ) return false;
        }
        return function_exists( 'imap_open' );
    }
    private function __construct() {}
}
