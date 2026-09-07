<?php

namespace MVM\Hub\Modules\Agenda;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Dormant ownership catalog for the Agenda domain.
 *
 * It separates public event content ownership from editorial planning ownership.
 * The catalog does not query WordPress, register hooks or perform writes.
 */
final class V5_Agenda_Owner_Catalog {
    /** @return array<string,array<string,mixed>> */
    public static function all(): array {
        return array(
            'public_events' => array(
                'label'                    => 'Publieke evenementen',
                'current_owner'            => 'wp-event-manager',
                'plugin_version_observed'  => '3.4.1',
                'post_type'                => 'event_listing',
                'rest_namespace'           => 'wp/v2',
                'rest_base'                => 'event_listing',
                'public_archive_page_id'   => 9385,
                'public_archive_route'     => '/evenementen/',
                'public_archive_shortcode' => 'events',
                'submit_page_id'           => 9383,
                'submit_route'             => '/een-evenement-posten/',
                'submit_shortcode'         => 'submit_event_form',
                'dashboard_page_id'        => 9384,
                'dashboard_route'          => '/evenement-dashboard/',
                'dashboard_shortcode'      => 'event_dashboard',
                'owner_resolution'         => 'resolved',
                'v5_write_provider_allowed'=> false,
            ),
            'editorial_calendar' => array(
                'label'                    => 'Redactionele agenda/planning',
                'current_owner'            => 'current-newsroom-editorial-calendar',
                'compatibility_source'     => 'mvm_hub4_editorial_calendar',
                'owner_resolution'         => 'resolved',
                'v5_write_provider_allowed'=> false,
            ),
            'platform_event_meta_compat' => array(
                'label'                    => 'Platform event-meta compatibiliteit',
                'current_owner'            => 'mvm-platform-event-meta-normalizer',
                'scope'                    => 'compatibility-normalizer-only',
                'observed_behavior'        => 'remove-empty-_event_end_date-on-save',
                'owner_resolution'         => 'resolved_noncanonical',
                'v5_write_provider_allowed'=> false,
            ),
            'community_event_integration' => array(
                'label'                    => 'PeepSo event integratie',
                'current_owner'            => 'peepso-wp-event-manager-integration',
                'plugin_version_observed'  => '8.0.0.0',
                'scope'                    => 'community-integration-only',
                'owner_resolution'         => 'resolved_noncanonical',
                'v5_write_provider_allowed'=> false,
            ),
        );
    }

    /** @return array<string,mixed>|null */
    public static function get( string $key ): ?array {
        $key = sanitize_key( $key );
        $all = self::all();
        return $all[ $key ] ?? null;
    }

    public static function canonical_public_owner(): string {
        return (string) ( self::all()['public_events']['current_owner'] ?? '' );
    }

    public static function canonical_editorial_owner(): string {
        return (string) ( self::all()['editorial_calendar']['current_owner'] ?? '' );
    }

    public static function can_promote_to_v5_write_provider( string $key ): bool {
        $record = self::get( $key );
        if ( null === $record ) {
            return false;
        }

        return 'resolved' === (string) ( $record['owner_resolution'] ?? '' )
            && true === ( $record['v5_write_provider_allowed'] ?? false );
    }

    private function __construct() {}
}
