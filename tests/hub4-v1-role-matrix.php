<?php

declare( strict_types=1 );

define( 'ABSPATH', __DIR__ . '/' );

require_once dirname( __DIR__ ) . '/plugins/mvm-hub4-rc-direct/src/class-capabilities.php';

$failures = array();
$check = static function ( bool $condition, string $message ) use ( &$failures ): void {
    if ( ! $condition ) {
        $failures[] = $message;
    }
};

$map = MvM_Hub4_Capabilities::role_map();
$set = static fn( string $role ): array => array_fill_keys( array_unique( $map[ $role ] ?? array() ), true );
$has = static fn( string $role, string $cap ): bool => isset( $set( $role )[ $cap ] );

$sysop = $set( 'mvm_sysop' );
foreach ( MvM_Hub4_Capabilities::managed_capabilities() as $capability ) {
    $check( isset( $sysop[ $capability ] ), "SysOp mist {$capability}." );
}

$check( $has( 'mvm_teamleider', MvM_Hub4_Capabilities::SIGNAL_TRIAGE ), 'Teamleider mist signal triage.' );
$check( $has( 'mvm_teamleider', MvM_Hub4_Capabilities::DOSSIER_PUBLISH ), 'Teamleider mist dossier publish.' );
$check( $has( 'mvm_teamleider', MvM_Hub4_Capabilities::DISTRIBUTION_APPROVE ), 'Teamleider mist distribution approve.' );
$check( $has( 'mvm_teamleider', MvM_Hub4_Capabilities::CORRECTION_MANAGE ), 'Teamleider mist correction management.' );
$check( $has( 'mvm_teamleider', MvM_Hub4_Capabilities::NEWS_REVIEW ), 'Teamleider mist Nieuwsradar review.' );

$check( $has( 'mvm_editor', MvM_Hub4_Capabilities::SIGNAL_TRIAGE ), 'Editor mist signal triage.' );
$check( $has( 'mvm_editor', MvM_Hub4_Capabilities::DOSSIER_PUBLISH ), 'Editor mist dossier publish.' );
$check( $has( 'mvm_editor', MvM_Hub4_Capabilities::DISTRIBUTION_APPROVE ), 'Editor mist distribution approve.' );
$check( $has( 'mvm_editor', MvM_Hub4_Capabilities::NEWS_REVIEW ), 'Editor mist Nieuwsradar review.' );
$check( ! $has( 'mvm_editor', MvM_Hub4_Capabilities::SOURCE_MANAGE ), 'Editor mag bronnenconfiguratie niet beheren.' );
$check( ! $has( 'mvm_editor', MvM_Hub4_Capabilities::SYSTEM_OVERVIEW ), 'Editor mag geen system overview krijgen.' );

foreach ( array( 'mvm_journalist', 'mvm_redacteur' ) as $role ) {
    $check( $has( $role, MvM_Hub4_Capabilities::SIGNAL_CREATE ), "{$role} mist signal create." );
    $check( $has( $role, MvM_Hub4_Capabilities::DOSSIER_MANAGE ), "{$role} mist own dossier management." );
    $check( $has( $role, MvM_Hub4_Capabilities::CALENDAR_MANAGE ), "{$role} mist own calendar management." );
    $check( $has( $role, MvM_Hub4_Capabilities::DISTRIBUTION_PREPARE ), "{$role} mist distribution prepare." );
    $check( ! $has( $role, MvM_Hub4_Capabilities::DOSSIER_PUBLISH ), "{$role} mag dossiers niet publiek maken." );
    $check( ! $has( $role, MvM_Hub4_Capabilities::DISTRIBUTION_APPROVE ), "{$role} mag distributie niet goedkeuren." );
    $check( ! $has( $role, MvM_Hub4_Capabilities::NEWS_REVIEW ), "{$role} mag Nieuwsradar niet aftekenen." );
}

$check( $has( 'mvm_fotograaf', MvM_Hub4_Capabilities::MEDIA_MANAGE ), 'Fotograaf mist eigen media management.' );
$check( $has( 'mvm_fotograaf', MvM_Hub4_Capabilities::MEDIA_VIEW ), 'Fotograaf mist media view.' );
$check( $has( 'mvm_fotograaf', MvM_Hub4_Capabilities::SIGNAL_CREATE ), 'Fotograaf mist signal create.' );
foreach ( array(
    MvM_Hub4_Capabilities::MEDIA_REVIEW,
    MvM_Hub4_Capabilities::SIGNAL_TRIAGE,
    MvM_Hub4_Capabilities::CHECKLIST_USE,
    MvM_Hub4_Capabilities::ASSIGNMENT_CREATE,
    MvM_Hub4_Capabilities::DOSSIER_PUBLISH,
    MvM_Hub4_Capabilities::NEWS_REVIEW,
) as $forbidden ) {
    $check( ! $has( 'mvm_fotograaf', $forbidden ), "Fotograaf heeft te ruime capability {$forbidden}." );
}

$check( $has( 'mvm_moderator', MvM_Hub4_Capabilities::CORRECTION_VIEW ), 'Moderator mist correction view.' );
$check( $has( 'mvm_moderator', MvM_Hub4_Capabilities::SIGNAL_CREATE ), 'Moderator mist signal create.' );
$check( ! $has( 'mvm_moderator', MvM_Hub4_Capabilities::CALENDAR_MANAGE ), 'Moderator mag kalender niet beheren.' );
$check( ! $has( 'mvm_moderator', MvM_Hub4_Capabilities::CORRECTION_MANAGE ), 'Moderator mag correcties niet publiceren.' );
$check( ! $has( 'mvm_moderator', MvM_Hub4_Capabilities::CHECKLIST_USE ), 'Moderator mag checklist niet wijzigen.' );
$check( ! $has( 'mvm_moderator', MvM_Hub4_Capabilities::NEWS_REVIEW ), 'Moderator mag Nieuwsradar niet aftekenen.' );

$check( $has( 'mvm_vertaler', MvM_Hub4_Capabilities::DOSSIER_VIEW ), 'Vertaler mist dossier context.' );
$check( $has( 'mvm_vertaler', MvM_Hub4_Capabilities::DISTRIBUTION_VIEW ), 'Vertaler mist distribution view.' );
$check( ! $has( 'mvm_vertaler', MvM_Hub4_Capabilities::DISTRIBUTION_PREPARE ), 'Vertaler mag distributie niet voorbereiden zonder aparte workflow.' );
$check( ! $has( 'mvm_vertaler', MvM_Hub4_Capabilities::DOSSIER_MANAGE ), 'Vertaler mag dossiers niet wijzigen.' );
$check( ! $has( 'mvm_vertaler', MvM_Hub4_Capabilities::CALENDAR_MANAGE ), 'Vertaler mag kalender niet beheren.' );
$check( ! $has( 'mvm_vertaler', MvM_Hub4_Capabilities::NEWS_REVIEW ), 'Vertaler mag Nieuwsradar niet aftekenen.' );

foreach ( array( 'mvm_fotograaf', 'mvm_moderator', 'mvm_vertaler' ) as $role ) {
    $check( $has( $role, MvM_Hub4_Capabilities::DASHBOARD_VIEW ), "{$role} mist role dashboard." );
    $check( ! $has( $role, MvM_Hub4_Capabilities::AUDIT_VIEW ), "{$role} mag audit niet bekijken." );
    $check( ! $has( $role, MvM_Hub4_Capabilities::SOURCE_MANAGE ), "{$role} mag bronnen niet beheren." );
    $check( ! $has( $role, MvM_Hub4_Capabilities::SYSTEM_OVERVIEW ), "{$role} mag system overview niet bekijken." );
}

if ( $failures ) {
    fwrite( STDERR, implode( PHP_EOL, $failures ) . PHP_EOL );
    exit( 1 );
}

echo 'Hub 4 v1 role matrix: least-privilege assertions passed.' . PHP_EOL;
