<?php

declare(strict_types=1);

// Standalone executable contract for the dormant Agenda owner catalog.
define( 'ABSPATH', __DIR__ . '/' );

if ( ! function_exists( 'sanitize_key' ) ) {
    function sanitize_key( mixed $value ): string {
        $value = strtolower( (string) $value );
        return (string) preg_replace( '/[^a-z0-9_\-]/', '', $value );
    }
}

require_once __DIR__ . '/../plugins/mvm-hub/modules/agenda/class-v5-agenda-owner-catalog.php';

use MVM\Hub\Modules\Agenda\V5_Agenda_Owner_Catalog;

function mvm_v5_agenda_owner_assert( bool $condition, string $message ): void {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
}

$public = V5_Agenda_Owner_Catalog::get( 'public_events' );
mvm_v5_agenda_owner_assert( is_array( $public ), 'public event owner must be catalogued' );
mvm_v5_agenda_owner_assert( 'wp-event-manager' === $public['current_owner'], 'WP Event Manager must remain canonical public event owner' );
mvm_v5_agenda_owner_assert( 'event_listing' === $public['post_type'], 'event_listing must remain the public event post type contract' );
mvm_v5_agenda_owner_assert( 'wp/v2' === $public['rest_namespace'], 'event REST namespace must stay explicit' );
mvm_v5_agenda_owner_assert( 'event_listing' === $public['rest_base'], 'event REST base must stay explicit' );
mvm_v5_agenda_owner_assert( 9385 === $public['public_archive_page_id'], 'public event archive page must stay pinned' );
mvm_v5_agenda_owner_assert( '/evenementen/' === $public['public_archive_route'], 'public event archive route must stay pinned' );
mvm_v5_agenda_owner_assert( 'events' === $public['public_archive_shortcode'], 'public event archive shortcode must stay pinned' );
mvm_v5_agenda_owner_assert( 9383 === $public['submit_page_id'], 'event submit page must stay pinned' );
mvm_v5_agenda_owner_assert( 'submit_event_form' === $public['submit_shortcode'], 'event submit shortcode must stay pinned' );
mvm_v5_agenda_owner_assert( false === $public['v5_write_provider_allowed'], 'resolved WP Event Manager ownership must not imply V5 write takeover' );

$editorial = V5_Agenda_Owner_Catalog::get( 'editorial_calendar' );
mvm_v5_agenda_owner_assert( is_array( $editorial ), 'editorial calendar owner must be catalogued' );
mvm_v5_agenda_owner_assert( 'current-newsroom-editorial-calendar' === $editorial['current_owner'], 'editorial planning must remain separate from public event storage' );
mvm_v5_agenda_owner_assert( 'mvm_hub4_editorial_calendar' === $editorial['compatibility_source'], 'current editorial compatibility source must remain explicit' );
mvm_v5_agenda_owner_assert( false === $editorial['v5_write_provider_allowed'], 'editorial planning must remain read-only to V5 during coexistence' );

$platform = V5_Agenda_Owner_Catalog::get( 'platform_event_meta_compat' );
mvm_v5_agenda_owner_assert( is_array( $platform ), 'Platform event meta compatibility owner must be catalogued' );
mvm_v5_agenda_owner_assert( 'resolved_noncanonical' === $platform['owner_resolution'], 'Platform event meta normalizer must never be mistaken for canonical event ownership' );
mvm_v5_agenda_owner_assert( 'remove-empty-_event_end_date-on-save' === $platform['observed_behavior'], 'Platform compatibility behavior must remain narrowly described' );

$community = V5_Agenda_Owner_Catalog::get( 'community_event_integration' );
mvm_v5_agenda_owner_assert( is_array( $community ), 'PeepSo event integration must be catalogued' );
mvm_v5_agenda_owner_assert( 'resolved_noncanonical' === $community['owner_resolution'], 'community integration must remain noncanonical' );

mvm_v5_agenda_owner_assert( 'wp-event-manager' === V5_Agenda_Owner_Catalog::canonical_public_owner(), 'canonical public owner helper must resolve WP Event Manager' );
mvm_v5_agenda_owner_assert( 'current-newsroom-editorial-calendar' === V5_Agenda_Owner_Catalog::canonical_editorial_owner(), 'canonical editorial owner helper must resolve current Newsroom calendar' );

foreach ( array_keys( V5_Agenda_Owner_Catalog::all() ) as $key ) {
    mvm_v5_agenda_owner_assert( false === V5_Agenda_Owner_Catalog::can_promote_to_v5_write_provider( $key ), "Agenda owner {$key} must remain blocked from V5 write promotion" );
}
mvm_v5_agenda_owner_assert( false === V5_Agenda_Owner_Catalog::can_promote_to_v5_write_provider( 'unknown' ), 'unknown Agenda owner must fail closed' );

echo "PASS: MvM Hub V5 Agenda owner boundaries and write-promotion gate\n";
