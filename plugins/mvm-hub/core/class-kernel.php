<?php

namespace MVM\Hub\Core;

use MVM\Hub\Modules\Communications\Communications_Module;
use MVM\Hub\Modules\Newsroom\Newsroom_Admin;
use MVM\Hub\Modules\Newsroom\Newsroom_Module;
use MVM\Hub\Modules\Newsroom\Staff_Collaboration;

if ( ! defined( 'ABSPATH' ) ) exit;

require_once MVM_HUB_DIR . 'core/class-legacy-services.php';
require_once MVM_HUB_DIR . 'modules/newsroom/class-staff-collaboration.php';
require_once MVM_HUB_DIR . 'modules/newsroom/class-newsroom-admin.php';
Legacy_Services::bootstrap();

final class Kernel {
    private static bool $booted = false;

    public static function boot(): void {
        if ( self::$booted ) return;
        self::$booted = true;

        // Private editorial backend and internal staff collaboration. Neither
        // surface registers public routes or logged-out actions.
        Staff_Collaboration::register();
        Newsroom_Admin::register();

        // Data/API surfaces remain independently capability/object guarded.
        Newsroom_Module::register();
        Communications_Module::register();
        Cron_Ownership::register();

        add_action( 'rest_api_init', static function (): void {
            ( new Release_Control_REST_Controller() )->register_routes();
        } );
        Release_Control_Abilities::register();

        if ( Runtime_Gates::capability_reconciliation_enabled() ) {
            add_action( 'init', array( Role_Capability_Bundles::class, 'reconcile_if_enabled' ), 4 );
        }

        if ( Runtime_Gates::shell_preview_enabled() ) {
            add_action( 'rest_api_init', static function (): void {
                ( new Shell_Preview_REST_Controller() )->register_routes();
            } );
        }

        if ( Runtime_Gates::route_takeover_enabled() ) {
            Staff_Login_Recaptcha::register();
            Hub_Runtime::register();
        }
    }

    private function __construct() {}
}
