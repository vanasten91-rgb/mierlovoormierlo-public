<?php

namespace MVM\Hub\Modules\Newsroom\Read;

use MVM\Hub\Core\Capabilities;
use MVM\Hub\Core\Cron_Ownership;
use MVM\Hub\Core\Runtime_Gates;

if ( ! defined( 'ABSPATH' ) ) exit;

final class Newsroom_Platform_Write_REST_Controller {
    private const NAMESPACE = 'mvm-hub/v1';

    public function register_read_routes(): void {
        foreach ( array(
            'sources'      => array( 'callback' => 'source_detail', 'cap' => Capabilities::SOURCES_VIEW ),
            'radar'        => array( 'callback' => 'signal_detail', 'cap' => Capabilities::RADAR_VIEW ),
            'agenda'       => array( 'callback' => 'agenda_detail', 'cap' => Capabilities::AGENDA_VIEW ),
            'media'        => array( 'callback' => 'media_detail', 'cap' => Capabilities::MEDIA_VIEW ),
            'dossiers'     => array( 'callback' => 'dossier_detail', 'cap' => Capabilities::DOSSIERS_VIEW ),
            'corrections'  => array( 'callback' => 'correction_detail', 'cap' => Capabilities::CORRECTIONS_VIEW ),
            'distribution' => array( 'callback' => 'distribution_detail', 'cap' => Capabilities::DISTRIBUTION_VIEW ),
        ) as $path => $config ) {
            register_rest_route( self::NAMESPACE, '/newsroom/' . $path . '/(?P<id>\d+)', array(
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => array( $this, (string) $config['callback'] ),
                'permission_callback' => fn(): bool => $this->can_read( (string) $config['cap'] ),
                'args'                => self::id_args(),
            ) );
        }
    }

    public function register_write_routes(): void {
        if ( ! Runtime_Gates::newsroom_writes_enabled() ) return;
        $routes = array(
            array( '/newsroom/sources/(?P<id>\d+)', \WP_REST_Server::EDITABLE, 'update_source', Capabilities::SOURCES_MANAGE ),
            array( '/newsroom/sources/(?P<id>\d+)/checked', \WP_REST_Server::CREATABLE, 'queue_source_check', Capabilities::SOURCES_MANAGE ),
            array( '/newsroom/radar', \WP_REST_Server::CREATABLE, 'create_signal', Capabilities::NEWS_CREATE ),
            array( '/newsroom/radar/(?P<id>\d+)', \WP_REST_Server::EDITABLE, 'update_signal', Capabilities::RADAR_TRIAGE ),
            array( '/newsroom/agenda', \WP_REST_Server::CREATABLE, 'create_agenda', Capabilities::AGENDA_MANAGE ),
            array( '/newsroom/agenda/(?P<id>\d+)', \WP_REST_Server::EDITABLE, 'update_agenda', Capabilities::AGENDA_MANAGE ),
            array( '/newsroom/media', \WP_REST_Server::CREATABLE, 'create_media', Capabilities::MEDIA_MANAGE ),
            array( '/newsroom/media/(?P<id>\d+)', \WP_REST_Server::EDITABLE, 'update_media', Capabilities::MEDIA_MANAGE ),
            array( '/newsroom/dossiers', \WP_REST_Server::CREATABLE, 'create_dossier', Capabilities::DOSSIERS_MANAGE ),
            array( '/newsroom/dossiers/(?P<id>\d+)', \WP_REST_Server::EDITABLE, 'update_dossier', Capabilities::DOSSIERS_MANAGE ),
            array( '/newsroom/corrections', \WP_REST_Server::CREATABLE, 'create_correction', Capabilities::CORRECTIONS_MANAGE ),
            array( '/newsroom/corrections/(?P<id>\d+)', \WP_REST_Server::EDITABLE, 'update_correction', Capabilities::CORRECTIONS_MANAGE ),
            array( '/newsroom/distribution', \WP_REST_Server::CREATABLE, 'create_distribution', Capabilities::DISTRIBUTION_MANAGE ),
            array( '/newsroom/distribution/(?P<id>\d+)', \WP_REST_Server::EDITABLE, 'update_distribution', Capabilities::DISTRIBUTION_MANAGE ),
        );
        foreach ( $routes as [ $route, $method, $callback, $capability ] ) {
            register_rest_route( self::NAMESPACE, $route, array(
                'methods'             => $method,
                'callback'            => array( $this, $callback ),
                'permission_callback' => fn( \WP_REST_Request $request ): bool|\WP_Error => $this->authorize_write( $request, $capability ),
                'args'                => str_contains( $route, '(?P<id>' ) ? self::id_args() : array(),
            ) );
        }
    }

    public function source_detail( \WP_REST_Request $r ): \WP_REST_Response|\WP_Error { return self::response( (new Newsroom_Platform_Write_Service())->source_detail( absint($r['id']) ) ); }
    public function signal_detail( \WP_REST_Request $r ): \WP_REST_Response|\WP_Error { return self::response( (new Newsroom_Platform_Write_Service())->signal_detail( absint($r['id']) ) ); }
    public function agenda_detail( \WP_REST_Request $r ): \WP_REST_Response|\WP_Error { return self::response( (new Newsroom_Editor_Detail_Service())->agenda( absint($r['id']) ) ); }
    public function media_detail( \WP_REST_Request $r ): \WP_REST_Response|\WP_Error { return self::response( (new Newsroom_Editor_Detail_Service())->media( absint($r['id']) ) ); }
    public function dossier_detail( \WP_REST_Request $r ): \WP_REST_Response|\WP_Error { return self::response( (new Newsroom_Platform_Write_Service())->dossier_detail( absint($r['id']) ) ); }
    public function correction_detail( \WP_REST_Request $r ): \WP_REST_Response|\WP_Error { return self::response( (new Newsroom_Platform_Write_Service())->correction_detail( absint($r['id']) ) ); }
    public function distribution_detail( \WP_REST_Request $r ): \WP_REST_Response|\WP_Error { return self::response( (new Newsroom_Platform_Write_Service())->distribution_detail( absint($r['id']) ) ); }

    public function update_source( \WP_REST_Request $r ): \WP_REST_Response|\WP_Error { return self::response((new Newsroom_Platform_Write_Service())->update_source(absint($r['id']),self::json($r))); }
    public function queue_source_check( \WP_REST_Request $r ): \WP_REST_Response|\WP_Error { return self::response(Cron_Ownership::queue_manual_newsradar_source(absint($r['id']))); }
    public function create_signal( \WP_REST_Request $r ): \WP_REST_Response|\WP_Error { return self::response((new Newsroom_Platform_Write_Service())->create_signal(self::json($r))); }
    public function update_signal( \WP_REST_Request $r ): \WP_REST_Response|\WP_Error { return self::response((new Newsroom_Platform_Write_Service())->update_signal(absint($r['id']),self::json($r))); }
    public function create_agenda( \WP_REST_Request $r ): \WP_REST_Response|\WP_Error { return self::response((new Newsroom_Platform_Write_Service())->create_agenda(self::json($r))); }
    public function update_agenda( \WP_REST_Request $r ): \WP_REST_Response|\WP_Error { return self::response((new Newsroom_Platform_Write_Service())->update_agenda(absint($r['id']),self::json($r))); }
    public function create_media( \WP_REST_Request $r ): \WP_REST_Response|\WP_Error { return self::response((new Newsroom_Platform_Write_Service())->create_media(self::json($r))); }
    public function update_media( \WP_REST_Request $r ): \WP_REST_Response|\WP_Error { return self::response((new Newsroom_Platform_Write_Service())->update_media(absint($r['id']),self::json($r))); }
    public function create_dossier( \WP_REST_Request $r ): \WP_REST_Response|\WP_Error { return self::response((new Newsroom_Platform_Write_Service())->create_dossier(self::json($r))); }
    public function update_dossier( \WP_REST_Request $r ): \WP_REST_Response|\WP_Error { return self::response((new Newsroom_Platform_Write_Service())->update_dossier(absint($r['id']),self::json($r))); }
    public function create_correction( \WP_REST_Request $r ): \WP_REST_Response|\WP_Error { return self::response((new Newsroom_Platform_Write_Service())->create_correction(self::json($r))); }
    public function update_correction( \WP_REST_Request $r ): \WP_REST_Response|\WP_Error { return self::response((new Newsroom_Platform_Write_Service())->update_correction(absint($r['id']),self::json($r))); }
    public function create_distribution( \WP_REST_Request $r ): \WP_REST_Response|\WP_Error { return self::response((new Newsroom_Platform_Write_Service())->create_distribution(self::json($r))); }
    public function update_distribution( \WP_REST_Request $r ): \WP_REST_Response|\WP_Error { return self::response((new Newsroom_Platform_Write_Service())->update_distribution(absint($r['id']),self::json($r))); }

    private function can_read( string $capability ): bool { return is_user_logged_in() && Capabilities::can_access_newsroom() && Capabilities::can_read( $capability ); }

    private function authorize_write( \WP_REST_Request $request, string $capability ): bool|\WP_Error {
        if ( ! Runtime_Gates::newsroom_writes_enabled() || ! is_user_logged_in() || ! Capabilities::can_access_newsroom() ) return new \WP_Error( 'mvm_newsroom_writes_disabled', 'Nieuwsroom-schrijfacties zijn niet beschikbaar.', array( 'status' => 403 ) );
        if ( ! current_user_can( 'manage_options' ) && ! current_user_can( $capability ) ) {
            if ( Capabilities::NEWS_CREATE !== $capability || ! current_user_can( Capabilities::NEWS_CREATE ) ) return new \WP_Error( 'mvm_newsroom_write_capability', 'Je hebt geen toestemming voor deze actie.', array( 'status' => 403 ) );
        }
        $nonce = (string) $request->get_header( 'X-WP-Nonce' );
        if ( '' === $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) return new \WP_Error( 'mvm_newsroom_nonce', 'De beveiligingstoken is ongeldig of verlopen.', array( 'status' => 403 ) );
        return true;
    }

    /** @return array<string,mixed> */ private static function json(\WP_REST_Request $r):array{$v=$r->get_json_params();return is_array($v)?$v:array();}
    /** @return array<string,array<string,mixed>> */ private static function id_args():array{return array('id'=>array('required'=>true,'sanitize_callback'=>'absint','validate_callback'=>static fn(mixed $v):bool=>absint($v)>0));}
    private static function response(mixed $r):\WP_REST_Response|\WP_Error{return is_wp_error($r)?$r:rest_ensure_response($r);}
}
