<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class MvM_Hub4_Abilities {
    private const CATEGORY = 'mvm-newsroom';

    public static function init(): void {
        if ( function_exists( 'wp_register_ability_category' ) ) {
            add_action( 'wp_abilities_api_categories_init', array( __CLASS__, 'register_category' ) );
        }
        if ( function_exists( 'wp_register_ability' ) ) {
            add_action( 'wp_abilities_api_init', array( __CLASS__, 'register_abilities' ) );
        }
    }

    public static function register_category(): void {
        wp_register_ability_category(
            self::CATEGORY,
            array(
                'label'       => 'MvM Newsroom',
                'description' => 'Beveiligde redactionele acties van Mierlo voor Mierlo Hub 4.',
            )
        );
    }

    public static function register_abilities(): void {
        foreach ( self::definitions() as $name => $definition ) {
            wp_register_ability(
                $name,
                array(
                    'label'               => $definition['label'],
                    'description'         => $definition['description'],
                    'category'            => self::CATEGORY,
                    'execute_callback'    => $definition['execute'],
                    'permission_callback' => self::permission_callback( $definition['capability'] ),
                )
            );
        }
    }

    public static function definitions(): array {
        return array(
            'mvm-hub4/create-signal' => array(
                'label'       => 'Signaal toevoegen',
                'description' => 'Voegt een intern redactioneel signaal toe aan de MvM-signaleninbox.',
                'capability'  => MvM_Hub4_Capabilities::SIGNAL_CREATE,
                'execute'     => static fn( mixed $input ): array|WP_Error => MvM_Hub4_Signals::create_internal( is_array( $input ) ? $input : array() ),
            ),
            'mvm-hub4/triage-signal' => array(
                'label'       => 'Signaal triëren',
                'description' => 'Wijzigt status, prioriteit of toewijzing van een bestaand redactioneel signaal.',
                'capability'  => MvM_Hub4_Capabilities::SIGNAL_TRIAGE,
                'execute'     => static function ( mixed $input ): array|WP_Error {
                    $data = is_array( $input ) ? $input : array();
                    return MvM_Hub4_Signals::update( absint( $data['id'] ?? 0 ), $data );
                },
            ),
            'mvm-hub4/create-dossier' => array(
                'label'       => 'Dossier maken',
                'description' => 'Maakt een redactioneel dossier met interne briefing en verantwoordelijke.',
                'capability'  => MvM_Hub4_Capabilities::DOSSIER_MANAGE,
                'execute'     => static fn( mixed $input ): array|WP_Error => MvM_Hub4_Dossiers::create( is_array( $input ) ? $input : array() ),
            ),
            'mvm-hub4/create-calendar-item' => array(
                'label'       => 'Redactieplanning toevoegen',
                'description' => 'Voegt een planning- of deadline-item toe aan de redactiekalender.',
                'capability'  => MvM_Hub4_Capabilities::CALENDAR_MANAGE,
                'execute'     => static fn( mixed $input ): array|WP_Error => MvM_Hub4_Platform_Writes::create_calendar( is_array( $input ) ? $input : array() ),
            ),
            'mvm-hub4/create-media-item' => array(
                'label'       => 'Media-item toevoegen',
                'description' => 'Maakt een foto-, video-, audio- of documentopdracht in de mediad esk.',
                'capability'  => MvM_Hub4_Capabilities::MEDIA_MANAGE,
                'execute'     => static fn( mixed $input ): array|WP_Error => MvM_Hub4_Platform_Writes::create_media( is_array( $input ) ? $input : array() ),
            ),
            'mvm-hub4/prepare-distribution' => array(
                'label'       => 'Distributie voorbereiden',
                'description' => 'Maakt een menselijke reviewbare distributietekst voor een gepubliceerd bericht.',
                'capability'  => MvM_Hub4_Capabilities::DISTRIBUTION_PREPARE,
                'execute'     => static fn( mixed $input ): array|WP_Error => MvM_Hub4_Platform_Writes::create_distribution( is_array( $input ) ? $input : array() ),
            ),
            'mvm-hub4/review-correction' => array(
                'label'       => 'Correctieverzoek beoordelen',
                'description' => 'Beoordeelt een correctieverzoek en kan een openbare correctienotitie vastleggen.',
                'capability'  => MvM_Hub4_Capabilities::CORRECTION_MANAGE,
                'execute'     => static function ( mixed $input ): array|WP_Error {
                    $data = is_array( $input ) ? $input : array();
                    return MvM_Hub4_Corrections::update( absint( $data['id'] ?? 0 ), $data );
                },
            ),
        );
    }

    private static function permission_callback( string $capability ): Closure {
        return static function () use ( $capability ): bool {
            $hub = MvM_Hub4_Security::require_hub_access();
            return true === $hub && ( current_user_can( $capability ) || current_user_can( 'manage_options' ) );
        };
    }
}
