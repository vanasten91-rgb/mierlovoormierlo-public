<?php

namespace MVM\Hub\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/class-cron-release.php';
require_once __DIR__ . '/class-communications-mail-release.php';

/**
 * Machine-readable, admin-only release operations exposed through the core
 * WordPress Abilities API. Each ability delegates to one release operation so
 * preflight, rollback and post-promotion gate invariants remain testable.
 */
final class Release_Control_Abilities {
    private const CATEGORY = 'mvm-hub-release';
    private const ABILITY_NEWSROOM2      = 'mvm-hub/promote-newsroom2';
    private const ABILITY_CRON           = 'mvm-hub/promote-cron';
    private const ABILITY_COMMUNICATIONS = 'mvm-hub/promote-communications';
    private const ABILITY_MAIL           = 'mvm-hub/promote-mail';
    private const ABILITY_COMM_HEALTH    = 'mvm-hub/communications-health';

    public static function register(): void {
        if ( ! function_exists( 'wp_register_ability' ) || ! function_exists( 'wp_register_ability_category' ) ) {
            return;
        }
        add_action( 'wp_abilities_api_categories_init', array( self::class, 'register_category' ) );
        add_action( 'wp_abilities_api_init', array( self::class, 'register_abilities' ) );
    }

    public static function register_category(): void {
        wp_register_ability_category(
            self::CATEGORY,
            array(
                'label'       => 'MvM Hub release',
                'description' => 'Gecontroleerde, herstelbare MvM Hub releasehandelingen.',
            )
        );
    }

    public static function register_abilities(): void {
        self::register_newsroom2_ability();
        self::register_cron_ability();
        self::register_communications_ability();
        self::register_mail_ability();
        self::register_communications_health_ability();
    }

    private static function register_newsroom2_ability(): void {
        wp_register_ability(
            self::ABILITY_NEWSROOM2,
            array(
                'label'               => 'Promote Newsroom 2.0',
                'description'         => 'Promoveert exact één geverifieerd MvM Hub-artifact naar de Newsroom 2.0-scope. Alleen route takeover, Newsroom writes en capability-reconciliatie mogen openen; cron, Communications en Mail blijven dicht. Vereist een bevestigde herstelbare backup.',
                'category'            => self::CATEGORY,
                'input_schema'        => self::release_input_schema(),
                'output_schema'       => array(
                    'type'                 => 'object',
                    'required'             => array( 'ok', 'release', 'gates', 'hub4Active', 'capabilities' ),
                    'additionalProperties' => true,
                    'properties'           => array(
                        'ok' => array( 'type' => 'boolean' ),
                        'release' => array( 'type' => 'object' ),
                        'gates' => array( 'type' => 'object' ),
                        'hub4Active' => array( 'type' => 'boolean' ),
                        'capabilities' => array( 'type' => 'object' ),
                    ),
                ),
                'execute_callback'    => array( self::class, 'execute_newsroom2_promote' ),
                'permission_callback' => array( self::class, 'can_release' ),
                'meta'                => self::ability_meta(),
            )
        );
    }

    private static function register_cron_ability(): void {
        wp_register_ability(
            self::ABILITY_CRON,
            array(
                'label'               => 'Promote MvM Hub cron ownership',
                'description'         => 'Draagt uitsluitend de bestaande MvM recurring-job ownership over van Hub4 naar de nieuwe Hub. Vereist een reeds live Newsroom 2.0-release en behoudt Communications en Mail fail-closed. Bij een mislukte postcheck blijft Newsroom 2.0 live.',
                'category'            => self::CATEGORY,
                'input_schema'        => self::release_input_schema(),
                'output_schema'       => array(
                    'type'                 => 'object',
                    'required'             => array( 'ok', 'release', 'gates', 'cron', 'hub4Active', 'capabilities' ),
                    'additionalProperties' => true,
                    'properties'           => array(
                        'ok' => array( 'type' => 'boolean' ),
                        'release' => array( 'type' => 'object' ),
                        'gates' => array( 'type' => 'object' ),
                        'cron' => array( 'type' => 'object' ),
                        'hub4Active' => array( 'type' => 'boolean' ),
                        'capabilities' => array( 'type' => 'object' ),
                    ),
                ),
                'execute_callback'    => array( self::class, 'execute_cron_promote' ),
                'permission_callback' => array( self::class, 'can_release' ),
                'meta'                => self::ability_meta(),
            )
        );
    }

    private static function register_communications_ability(): void {
        wp_register_ability(
            self::ABILITY_COMMUNICATIONS,
            array(
                'label'               => 'Promote MvM Communications',
                'description'         => 'Opent alleen Communications writes bovenop de reeds live volledige Hub-runtime. Mail delivery blijft dicht. Vereist private opslag, mailbox-readiness, PeepSo interne berichten en een bevestigde rollbackbackup.',
                'category'            => self::CATEGORY,
                'input_schema'        => self::release_input_schema(),
                'output_schema'       => self::release_output_schema(),
                'execute_callback'    => array( self::class, 'execute_communications_promote' ),
                'permission_callback' => array( self::class, 'can_release' ),
                'meta'                => self::ability_meta(),
            )
        );
    }

    private static function register_mail_ability(): void {
        wp_register_ability(
            self::ABILITY_MAIL,
            array(
                'label'               => 'Promote MvM Mail delivery',
                'description'         => 'Opent dedicated externe mail delivery bovenop een zelfstandig groene Communications-release. Dedicated SMTP moet server-side volledig geconfigureerd zijn.',
                'category'            => self::CATEGORY,
                'input_schema'        => self::release_input_schema(),
                'output_schema'       => self::release_output_schema(),
                'execute_callback'    => array( self::class, 'execute_mail_promote' ),
                'permission_callback' => array( self::class, 'can_release' ),
                'meta'                => self::ability_meta(),
            )
        );
    }

    private static function register_communications_health_ability(): void {
        wp_register_ability(
            self::ABILITY_COMM_HEALTH,
            array(
                'label'               => 'MvM Communications health',
                'description'         => 'Geeft alleen niet-geheime readinessmetadata terug voor mailbox, SMTP, private opslag, interne berichten en cron.',
                'category'            => self::CATEGORY,
                'input_schema'        => array(
                    'type'                 => 'object',
                    'additionalProperties' => false,
                    'properties'           => array(),
                ),
                'output_schema'       => array(
                    'type'                 => 'object',
                    'additionalProperties' => true,
                ),
                'execute_callback'    => array( self::class, 'execute_communications_health' ),
                'permission_callback' => array( self::class, 'can_release' ),
                'meta'                => self::readonly_ability_meta(),
            )
        );
    }

    /** @param mixed $input */
    public static function can_release( $input = null ): bool {
        return is_user_logged_in() && current_user_can( 'manage_options' ) && current_user_can( 'activate_plugins' );
    }

    /** @param array<string,mixed> $input */
    public static function execute_newsroom2_promote( array $input ): array|\WP_Error {
        $request = new \WP_REST_Request( 'POST', '/mvm-hub/v1/release/newsroom2-promote' );
        foreach ( array( 'releaseId', 'artifactSha256', 'innerSha256', 'backupConfirmed' ) as $key ) {
            $request->set_param( $key, $input[ $key ] ?? null );
        }
        $response = ( new Release_Control_REST_Controller() )->newsroom2_promote( $request );
        if ( is_wp_error( $response ) ) {
            return $response;
        }
        $data = $response->get_data();
        return array(
            'ok'           => true,
            'release'      => (array) ( $data['release'] ?? array() ),
            'gates'        => (array) ( $data['gates'] ?? array() ),
            'hub4Active'   => ! empty( $data['hub4Active'] ),
            'capabilities' => (array) ( $data['capabilities'] ?? array() ),
        );
    }

    /** @param array<string,mixed> $input */
    public static function execute_cron_promote( array $input ): array|\WP_Error {
        return Cron_Release::promote( $input );
    }

    /** @param array<string,mixed> $input */
    public static function execute_communications_promote( array $input ): array|\WP_Error {
        return Communications_Mail_Release::promote_communications( $input );
    }

    /** @param array<string,mixed> $input */
    public static function execute_mail_promote( array $input ): array|\WP_Error {
        return Communications_Mail_Release::promote_mail( $input );
    }

    /** @param array<string,mixed> $input */
    public static function execute_communications_health( array $input = array() ): array {
        unset( $input );
        return Communications_Mail_Release::health();
    }

    /** @return array<string,mixed> */
    private static function release_input_schema(): array {
        return array(
            'type'                 => 'object',
            'required'             => array( 'releaseId', 'artifactSha256', 'innerSha256', 'backupConfirmed' ),
            'additionalProperties' => false,
            'properties'           => array(
                'releaseId' => array( 'type' => 'string', 'minLength' => 1 ),
                'artifactSha256' => array( 'type' => 'string', 'minLength' => 64, 'maxLength' => 64 ),
                'innerSha256' => array( 'type' => 'string', 'minLength' => 64, 'maxLength' => 64 ),
                'backupConfirmed' => array( 'type' => 'boolean' ),
            ),
        );
    }

    /** @return array<string,mixed> */
    private static function release_output_schema(): array {
        return array(
            'type'                 => 'object',
            'required'             => array( 'ok', 'release', 'gates', 'health' ),
            'additionalProperties' => true,
            'properties'           => array(
                'ok' => array( 'type' => 'boolean' ),
                'release' => array( 'type' => 'object' ),
                'gates' => array( 'type' => 'object' ),
                'health' => array( 'type' => 'object' ),
            ),
        );
    }

    /** @return array<string,mixed> */
    private static function readonly_ability_meta(): array {
        return array(
            'public'       => true,
            'show_in_rest' => true,
            'annotations'  => array(
                'readonly' => true,
                'destructive' => false,
                'idempotent' => true,
            ),
        );
    }

    /** @return array<string,mixed> */
    private static function ability_meta(): array {
        return array(
            'public'       => true,
            'show_in_rest' => true,
            'annotations'  => array(
                'readonly' => false,
                'destructive' => false,
                'idempotent' => true,
            ),
        );
    }

    private function __construct() {}
}
