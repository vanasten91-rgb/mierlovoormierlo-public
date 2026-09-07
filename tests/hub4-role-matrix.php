<?php

declare( strict_types=1 );

define( 'ABSPATH', __DIR__ . '/' );

require_once dirname( __DIR__ ) . '/plugins/mvm-hub4-rc-direct/src/class-capabilities.php';

$failures = array();
$check    = static function ( bool $condition, string $message ) use ( &$failures ): void {
    if ( ! $condition ) {
        $failures[] = $message;
    }
};

$map = MvM_Hub4_Capabilities::role_map();
$set = static fn( string $role ): array => array_fill_keys( $map[ $role ] ?? array(), true );

$low_role_forbidden = array(
    MvM_Hub4_Capabilities::NEWS_REVIEW,
    MvM_Hub4_Capabilities::ASSIGNMENT_CREATE,
    MvM_Hub4_Capabilities::ASSIGNMENT_MANAGE,
    MvM_Hub4_Capabilities::CHECKLIST_USE,
    MvM_Hub4_Capabilities::SOURCE_MANAGE,
    MvM_Hub4_Capabilities::AUDIT_VIEW,
    MvM_Hub4_Capabilities::SYSTEM_OVERVIEW,
);

foreach ( array( 'mvm_fotograaf', 'mvm_moderator', 'mvm_vertaler' ) as $role ) {
    $capabilities = $set( $role );
    $check( isset( $capabilities[ MvM_Hub4_Capabilities::ASSIGNMENT_VIEW ] ), "{$role} mist assignment-view." );
    $check( isset( $capabilities[ MvM_Hub4_Capabilities::TOOLS_USE ] ), "{$role} mist veilige tool-launches." );
    foreach ( $low_role_forbidden as $capability ) {
        $check( ! isset( $capabilities[ $capability ] ), "{$role} heeft te ruime capability {$capability}." );
    }
}

$photographer = $set( 'mvm_fotograaf' );
$moderator    = $set( 'mvm_moderator' );
$translator   = $set( 'mvm_vertaler' );
$editor       = $set( 'mvm_editor' );
$teamlead     = $set( 'mvm_teamleider' );
$journalist   = $set( 'mvm_journalist' );
$redacteur    = $set( 'mvm_redacteur' );
$sysop        = $set( 'mvm_sysop' );

$check( isset( $photographer[ MvM_Hub4_Capabilities::AGENDA_VIEW ] ), 'Fotograaf mist agenda-context.' );
$check( ! isset( $moderator[ MvM_Hub4_Capabilities::AGENDA_VIEW ] ), 'Moderator heeft onnodige agenda-inzage.' );
$check( isset( $translator[ MvM_Hub4_Capabilities::SOURCE_VIEW ] ), 'Vertaler mist read-only bronnencontext.' );
$check( ! isset( $editor[ MvM_Hub4_Capabilities::SOURCE_MANAGE ] ), 'Editor mag bronconfiguratie niet beheren.' );

$check( isset( $editor[ MvM_Hub4_Capabilities::NEWS_REVIEW ] ), 'Editor mist Nieuwsradar review.' );
$check( isset( $teamlead[ MvM_Hub4_Capabilities::NEWS_REVIEW ] ), 'Teamleider mist Nieuwsradar review.' );
$check( isset( $sysop[ MvM_Hub4_Capabilities::NEWS_REVIEW ] ), 'SysOp mist Nieuwsradar review.' );
$check( ! isset( $journalist[ MvM_Hub4_Capabilities::NEWS_REVIEW ] ), 'Journalist mag Nieuwsradar niet aftekenen.' );
$check( ! isset( $redacteur[ MvM_Hub4_Capabilities::NEWS_REVIEW ] ), 'Redacteur mag Nieuwsradar niet aftekenen.' );

foreach ( MvM_Hub4_Capabilities::managed_capabilities() as $capability ) {
    $check( isset( $sysop[ $capability ] ), "SysOp mist {$capability}." );
}

if ( $failures ) {
    fwrite( STDERR, implode( PHP_EOL, $failures ) . PHP_EOL );
    exit( 1 );
}

echo 'Hub 4 role matrix: least-privilege assertions passed.' . PHP_EOL;
