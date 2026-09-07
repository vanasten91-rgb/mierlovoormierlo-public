<?php

namespace MVM\Hub\Modules\Newsroom;

use MVM\Hub\Core\Runtime_Gates;
use MVM\Hub\Modules\Newsroom\Dashboard\Today_REST_Controller;
use MVM\Hub\Modules\Newsroom\News\Smart_Links_REST_Controller;
use MVM\Hub\Modules\Newsroom\News\Smart_Links_Target_Search_REST_Controller;
use MVM\Hub\Modules\Newsroom\Read\Newsroom_Read_REST_Controller;
use MVM\Hub\Modules\Newsroom\Read\Newsroom_Secondary_Read_REST_Controller;
use MVM\Hub\Modules\Newsroom\Read\Newsroom_Platform_Write_REST_Controller;
use MVM\Hub\Modules\Newsroom\Team\Team_Read_REST_Controller;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Newsroom_Module {
    private static bool $registered = false;

    public static function register(): void {
        if ( self::$registered ) {
            return;
        }

        self::$registered = true;

        add_action(
            'rest_api_init',
            static function (): void {
                ( new Today_REST_Controller() )->register_routes();
                ( new News_And_Assignments_Read_REST_Controller() )->register_routes();
                ( new Smart_Links_REST_Controller() )->register_routes();
                ( new Smart_Links_Target_Search_REST_Controller() )->register_routes();
                ( new Newsroom_Read_REST_Controller() )->register_routes();
                ( new Newsroom_Secondary_Read_REST_Controller() )->register_routes();
                ( new Team_Read_REST_Controller() )->register_routes();

                $primary_writes = new Newsroom_Write_REST_Controller();
                $primary_writes->register_read_routes();
                $platform_writes = new Newsroom_Platform_Write_REST_Controller();
                $platform_writes->register_read_routes();

                if ( Runtime_Gates::newsroom_writes_enabled() ) {
                    $primary_writes->register_write_routes();
                    $platform_writes->register_write_routes();
                }
            }
        );
    }

    private function __construct() {}
}
