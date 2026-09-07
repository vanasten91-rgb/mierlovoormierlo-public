<?php

namespace MVM\Hub\Modules\Communications;

use MVM\Hub\Core\Runtime_Gates;
use MVM\Hub\Integrations\Mail\WordPress_Step_Up_Authenticator;

if ( ! defined( 'ABSPATH' ) ) exit;

final class Communications_Module {
    private static bool $registered = false;
    private static bool $runtime_loaded = false;

    public static function register(): void {
        if ( self::$registered ) return;
        self::$registered = true;
        self::load_runtime();

        // Incoming attachment reads are sensitive even in read-only mode and
        // therefore need the same session-bound step-up route as Mail writes.
        WordPress_Step_Up_Authenticator::register();

        add_action( 'rest_api_init', static function (): void {
            ( new Mail_Read_REST_Controller() )->register_routes();
            ( new Internal_Message_Read_REST_Controller() )->register_routes();

            if ( Runtime_Gates::communications_writes_enabled() ) {
                ( new Internal_Message_Write_REST_Controller() )->register_routes();
            }

            // Mail mutation routes exist only during an explicitly promoted,
            // non-production Mail rehearsal. Production cannot open this gate.
            if ( Runtime_Gates::mail_writes_enabled() ) {
                ( new Mail_Write_REST_Controller() )->register_routes();
            }
        } );
    }

    private static function load_runtime(): void {
        if ( self::$runtime_loaded ) return;
        self::$runtime_loaded = true;
        $files = array(
            'integrations/mail/class-private-storage-root.php',
            'integrations/mail/class-encrypted-filesystem-draft-store.php',
            'integrations/mail/class-filesystem-private-attachment-store.php',
            'integrations/mail/class-default-attachment-normalizer.php',
            'integrations/mail/class-filter-attachment-scanner.php',
            'integrations/mail/class-clamav-attachment-scanner.php',
            'integrations/mail/class-hub4-communications-audit.php',
            'integrations/mail/class-default-communications-action-security.php',
            'integrations/mail/class-default-mail-delivery-security.php',
            'integrations/mail/class-wordpress-step-up-authenticator.php',
            'integrations/mail/class-dedicated-smtp-mail-provider.php',
            'integrations/mail/class-dedicated-imap-smtp-mail-provider.php',
            'integrations/mail/class-folder-scoped-imap-mail-provider.php',
            'integrations/mail/class-v5-imap-attachment-reader.php',
            'integrations/mail/class-configured-mailbox-access.php',
            'integrations/peepso/class-peepso8-internal-message-provider.php',
            'modules/communications/class-communications-service-factory.php',
            'modules/communications/class-incoming-mail-attachment-service.php',
            'modules/communications/class-mail-message-preferences.php',
            'modules/communications/class-mail-sender-blocklist.php',
            'modules/communications/class-internal-message-write-rest-controller.php',
            'modules/communications/class-communications-write-rest-controller.php',
            'modules/communications/class-mail-write-rest-controller.php',
            'modules/communications/class-communications-runtime-renderer.php',
        );
        foreach ( $files as $file ) require_once MVM_HUB_DIR . $file;
    }

    private function __construct() {}
}
