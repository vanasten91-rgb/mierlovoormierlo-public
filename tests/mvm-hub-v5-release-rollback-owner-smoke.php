<?php

declare(strict_types=1);

define( 'ABSPATH', __DIR__ . '/' );

require_once __DIR__ . '/../plugins/mvm-hub/release/class-release-control-rest-controller.php';

use MVM\Hub\Core\Release_Control_REST_Controller;

function mvm_release_owner_assert( bool $condition, string $message ): void {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
}

$adopted_persistent = array(
    'state' => 'adopted',
    'source' => 'persistent',
    'persistentReady' => true,
    'hub4PluginRequired' => false,
);

mvm_release_owner_assert(
    'hub4' === Release_Control_REST_Controller::rollback_owner_status_for( true, array() ),
    'active Hub4 must remain an accepted rollback owner'
);

mvm_release_owner_assert(
    'persistent_legacy_services' === Release_Control_REST_Controller::rollback_owner_status_for( false, $adopted_persistent ),
    'inactive Hub4 with integrity-checked persistent adopted legacy services must be accepted'
);

$unsafe_cases = array(
    'missing evidence' => array(),
    'not adopted' => array_replace( $adopted_persistent, array( 'state' => 'external_loaded' ) ),
    'non-persistent source' => array_replace( $adopted_persistent, array( 'source' => 'legacy-fallback' ) ),
    'persistent payload not ready' => array_replace( $adopted_persistent, array( 'persistentReady' => false ) ),
    'Hub4 still required' => array_replace( $adopted_persistent, array( 'hub4PluginRequired' => true ) ),
);

foreach ( $unsafe_cases as $label => $status ) {
    mvm_release_owner_assert(
        'unavailable' === Release_Control_REST_Controller::rollback_owner_status_for( false, $status ),
        "Hub4-inactive unsafe rollback state must fail closed: {$label}"
    );
}

echo "PASS: MvM Hub release rollback-owner fail-closed smoke\n";
