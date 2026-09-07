<?php

namespace MVM\Hub\Modules\Agenda;

use MVM\Hub\Core\Data_Classification;
use MVM\Hub\Core\V5_Read_Provider_Contract;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Dormant read-provider declarations for the Agenda domain.
 *
 * These declarations do not fetch records themselves. They describe which
 * already-authorized source projections V5 may accept during coexistence.
 */
final class V5_Agenda_Read_Providers {
    /** @return array<string,array<string,mixed>> */
    public static function all(): array {
        return array(
            'public_events' => array(
                'provider_key'           => 'wp_event_manager_public_events',
                'domain'                 => 'agenda',
                'source_key'             => 'public_events',
                'declared_owner'         => 'wp-event-manager',
                'read_mode'              => V5_Read_Provider_Contract::MODE_PROJECTION,
                'authorization_boundary' => 'pre_authorized_input',
                'write_capable'          => false,
                'classification'         => Data_Classification::PUBLIC,
                'required_capability'    => 'mvm_agenda_view',
                'input_contract'         => 'authorized_event_listing_projection',
                'adapter'                => 'wp_event_manager_event_projection_v1',
            ),
            'editorial_calendar' => array(
                'provider_key'           => 'current_newsroom_editorial_calendar',
                'domain'                 => 'agenda',
                'source_key'             => 'editorial_calendar',
                'declared_owner'         => 'current-newsroom-editorial-calendar',
                'read_mode'              => V5_Read_Provider_Contract::MODE_PROJECTION,
                'authorization_boundary' => 'pre_authorized_input',
                'write_capable'          => false,
                'classification'         => Data_Classification::INTERNAL,
                'required_capability'    => 'mvm_agenda_view',
                'input_contract'         => 'authorized_editorial_calendar_projection',
                'adapter'                => V5_Agenda_Read_Adapter::class,
            ),
        );
    }

    /** @return array<string,mixed>|null */
    public static function resolve( string $source_key ): ?array {
        $source_key = sanitize_key( $source_key );
        $providers  = self::all();
        $provider   = $providers[ $source_key ] ?? null;
        $owner      = V5_Agenda_Owner_Catalog::get( $source_key );

        if ( null === $provider || null === $owner ) {
            return null;
        }

        $decision = V5_Read_Provider_Contract::decision( $provider, $owner );
        if ( true !== $decision['allowed'] ) {
            return null;
        }

        $provider['owner_decision'] = $decision;
        $provider['read_only_projection'] = true;
        return $provider;
    }

    public static function can_read( string $source_key ): bool {
        return null !== self::resolve( $source_key );
    }

    private function __construct() {}
}
