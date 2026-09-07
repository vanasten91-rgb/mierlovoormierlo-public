<?php

declare(strict_types=1);

define( 'ABSPATH', __DIR__ . '/' );

if ( ! function_exists( 'sanitize_key' ) ) {
    function sanitize_key( mixed $value ): string {
        $value = strtolower( (string) $value );
        return (string) preg_replace( '/[^a-z0-9_\-]/', '', $value );
    }
}

require_once __DIR__ . '/../plugins/mvm-hub/core/class-data-classification.php';
require_once __DIR__ . '/../plugins/mvm-hub/core/class-v5-read-provider-contract.php';
require_once __DIR__ . '/../plugins/mvm-hub/modules/agenda/class-v5-agenda-owner-catalog.php';
require_once __DIR__ . '/../plugins/mvm-hub/modules/agenda/class-v5-agenda-read-providers.php';
require_once __DIR__ . '/../plugins/mvm-hub/modules/intake/class-v5-intake-owner-catalog.php';
require_once __DIR__ . '/../plugins/mvm-hub/modules/intake/class-v5-intake-read-providers.php';

use MVM\Hub\Core\V5_Read_Provider_Contract;
use MVM\Hub\Modules\Agenda\V5_Agenda_Read_Providers;
use MVM\Hub\Modules\Intake\V5_Intake_Read_Providers;

function mvm_v5_read_provider_assert( bool $condition, string $message ): void {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
}

$public = V5_Agenda_Read_Providers::resolve( 'public_events' );
mvm_v5_read_provider_assert( is_array( $public ), 'WP Event Manager public-event read provider must resolve' );
mvm_v5_read_provider_assert( 'wp-event-manager' === $public['declared_owner'], 'public-event provider owner must stay pinned' );
mvm_v5_read_provider_assert( false === $public['write_capable'], 'public-event provider must remain read-only' );

$editorial = V5_Agenda_Read_Providers::resolve( 'editorial_calendar' );
mvm_v5_read_provider_assert( is_array( $editorial ), 'editorial calendar read provider must resolve' );
mvm_v5_read_provider_assert( 'current-newsroom-editorial-calendar' === $editorial['declared_owner'], 'editorial provider owner must stay pinned' );

$contact = V5_Intake_Read_Providers::resolve( 'general_contact' );
mvm_v5_read_provider_assert( is_array( $contact ), 'resolved CF7 contract-metadata provider must be accepted' );
mvm_v5_read_provider_assert( V5_Read_Provider_Contract::MODE_METADATA_ONLY === $contact['read_mode'], 'general contact must remain metadata-only' );
mvm_v5_read_provider_assert( false === $contact['payload_access'], 'general contact provider must not expose submission bodies' );

mvm_v5_read_provider_assert( null === V5_Intake_Read_Providers::resolve( 'news_tip' ), 'unresolved news-tip owner must fail closed' );
$tip_decision = V5_Intake_Read_Providers::decision( 'news_tip' );
mvm_v5_read_provider_assert( false === $tip_decision['allowed'], 'news-tip provider must not be allowed while owner is unresolved' );
mvm_v5_read_provider_assert( 'owner_unresolved' === $tip_decision['reason'], 'unresolved news-tip provider must explain owner_unresolved' );

$forged = array(
    'provider_key'           => 'forged_agenda_provider',
    'domain'                 => 'agenda',
    'source_key'             => 'public_events',
    'declared_owner'         => 'forged-owner',
    'read_mode'              => V5_Read_Provider_Contract::MODE_PROJECTION,
    'authorization_boundary' => 'pre_authorized_input',
    'write_capable'          => false,
);
$owner = array(
    'current_owner'    => 'wp-event-manager',
    'owner_resolution' => 'resolved',
);
$forged_decision = V5_Read_Provider_Contract::decision( $forged, $owner );
mvm_v5_read_provider_assert( false === $forged_decision['allowed'], 'owner mismatch must be denied' );
mvm_v5_read_provider_assert( 'owner_mismatch' === $forged_decision['reason'], 'owner mismatch must be explicit' );

$write_capable = $forged;
$write_capable['declared_owner'] = 'wp-event-manager';
$write_capable['write_capable'] = true;
$write_decision = V5_Read_Provider_Contract::decision( $write_capable, $owner );
mvm_v5_read_provider_assert( false === $write_decision['allowed'], 'write-capable provider must be rejected' );
mvm_v5_read_provider_assert( 'write_boundary_violation' === $write_decision['reason'], 'write boundary rejection must be explicit' );

$unresolved_owner = array(
    'current_owner'    => 'wp-event-manager',
    'owner_resolution' => 'resolved_noncanonical',
);
$unresolved_provider = $forged;
$unresolved_provider['declared_owner'] = 'wp-event-manager';
$unresolved_decision = V5_Read_Provider_Contract::decision( $unresolved_provider, $unresolved_owner );
mvm_v5_read_provider_assert( false === $unresolved_decision['allowed'], 'noncanonical owner must fail closed' );
mvm_v5_read_provider_assert( 'owner_unresolved' === $unresolved_decision['reason'], 'noncanonical owner must not silently pass as canonical' );

echo "PASS: MvM Hub V5 read providers are owner-validated, read-only and fail closed\n";
