<?php

declare(strict_types=1);

// Standalone executable smoke for dormant V5 Agenda, Encyclopedie, Intake and
// cross-domain My Work projections. No WordPress boot, database or network IO.
define( 'ABSPATH', __DIR__ . '/' );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );

if ( ! function_exists( 'sanitize_key' ) ) {
    function sanitize_key( mixed $value ): string {
        $value = strtolower( (string) $value );
        return (string) preg_replace( '/[^a-z0-9_\-]/', '', $value );
    }
}
if ( ! function_exists( 'sanitize_text_field' ) ) {
    function sanitize_text_field( mixed $value ): string {
        return trim( strip_tags( (string) $value ) );
    }
}
if ( ! function_exists( 'sanitize_textarea_field' ) ) {
    function sanitize_textarea_field( mixed $value ): string {
        return trim( strip_tags( (string) $value ) );
    }
}

require_once __DIR__ . '/../plugins/mvm-hub/core/class-data-classification.php';
require_once __DIR__ . '/../plugins/mvm-hub/core/class-work-item-schema.php';
require_once __DIR__ . '/../plugins/mvm-hub/core/class-my-work-prioritizer.php';
require_once __DIR__ . '/../plugins/mvm-hub/core/class-my-work-read-model.php';
require_once __DIR__ . '/../plugins/mvm-hub/core/class-v5-my-work-preview-model.php';
require_once __DIR__ . '/../plugins/mvm-hub/core/class-v5-cross-domain-my-work-model.php';
require_once __DIR__ . '/../plugins/mvm-hub/modules/agenda/class-v5-agenda-read-adapter.php';
require_once __DIR__ . '/../plugins/mvm-hub/modules/agenda/class-v5-agenda-preview-model.php';
require_once __DIR__ . '/../plugins/mvm-hub/integrations/encyclopedie/class-v5-encyclopedie-workspace-model.php';
require_once __DIR__ . '/../plugins/mvm-hub/modules/intake/class-v5-intake-preview-model.php';

use MVM\Hub\Core\Data_Classification;
use MVM\Hub\Core\V5_Cross_Domain_My_Work_Model;
use MVM\Hub\Integrations\Encyclopedie\V5_Encyclopedie_Workspace_Model;
use MVM\Hub\Modules\Agenda\V5_Agenda_Preview_Model;
use MVM\Hub\Modules\Intake\V5_Intake_Preview_Model;

function mvm_v5_cross_domain_assert( bool $condition, string $message ): void {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
}

$now = new DateTimeImmutable( '2026-09-04T00:00:00Z' );
$authorize_owner_42 = static fn( array $item ): array => array(
    'allowed' => 42 === (int) ( $item['owner_user_id'] ?? 0 ) || 'intake' === (string) ( $item['owner_team'] ?? '' ),
);

$agenda = V5_Agenda_Preview_Model::build(
    array(
        array(
            'id' => 601,
            'kind' => 'event',
            'status' => 'scheduled',
            'title' => 'Kermis voorbereiden',
            'startsAtUtc' => '2026-09-04T10:00:00Z',
            'endsAtUtc' => '2026-09-04T20:00:00Z',
            'postId' => 1601,
            'assignmentId' => 2601,
            'dossierId' => 0,
            'ownerUserId' => 42,
        ),
        array(
            'id' => 602,
            'kind' => 'event',
            'status' => 'planned',
            'title' => 'Raadsagenda controleren',
            'startsAtUtc' => '2026-09-07T10:00:00Z',
            'endsAtUtc' => '',
            'postId' => 1602,
            'assignmentId' => 0,
            'dossierId' => 0,
            'ownerUserId' => 42,
        ),
        array(
            'id' => 603,
            'kind' => 'event',
            'status' => 'scheduled',
            'title' => 'Niet geautoriseerde agenda',
            'startsAtUtc' => '2026-09-04T08:00:00Z',
            'endsAtUtc' => '',
            'postId' => 1603,
            'assignmentId' => 0,
            'dossierId' => 0,
            'ownerUserId' => 99,
        ),
    ),
    $authorize_owner_42,
    $now,
    50
);

mvm_v5_cross_domain_assert( true === $agenda['readOnly'], 'Agenda projection must remain read-only' );
mvm_v5_cross_domain_assert( true === $agenda['legacyOwnershipPreserved'], 'current editorial calendar remains canonical owner' );
mvm_v5_cross_domain_assert( 2 === count( $agenda['items'] ), 'Agenda must exclude unauthorized rows' );
mvm_v5_cross_domain_assert( 1 === $agenda['counts']['next24h'], 'near agenda item must be in next24h queue' );
mvm_v5_cross_domain_assert( 1 === $agenda['counts']['next7d'], 'three-day agenda item must be in next7d queue' );

$encyclopedie = V5_Encyclopedie_Workspace_Model::build(
    array(
        'available' => true,
        'productionVersion' => '3.0.0-alpha2',
        'repositoryVersion' => '3.0.0-alpha4',
        'maintenance' => array(
            array(
                'id' => 801,
                'type' => 'integrity_issue',
                'title' => 'Controleer gebroken bronrelatie',
                'severity' => 'high',
                'status' => 'open',
                'ownerUserId' => 42,
                'dueAtUtc' => '2026-09-05T12:00:00Z',
                'classification' => Data_Classification::INTERNAL,
            ),
            array(
                'id' => 802,
                'type' => 'integrity_issue',
                'title' => 'Niet geautoriseerde onderhoudstaak',
                'severity' => 'critical',
                'status' => 'open',
                'ownerUserId' => 99,
                'dueAtUtc' => '2026-09-04T01:00:00Z',
                'classification' => Data_Classification::INTERNAL,
            ),
        ),
    ),
    $authorize_owner_42,
    $now,
    50
);

mvm_v5_cross_domain_assert( true === $encyclopedie['providerAvailable'], 'Encyclopedie provider availability must be projected' );
mvm_v5_cross_domain_assert( false === $encyclopedie['parity']['matched'], 'alpha2 versus alpha4 must remain an explicit parity mismatch' );
mvm_v5_cross_domain_assert( false === $encyclopedie['parity']['hardDependencyAllowed'], 'parity mismatch must block hard V5 dependency' );
mvm_v5_cross_domain_assert( 1 === count( $encyclopedie['items'] ), 'Encyclopedie workspace must exclude unauthorized maintenance items' );

$intake = V5_Intake_Preview_Model::build(
    array(
        array(
            'id' => 901,
            'status' => 'new',
            'priority' => 'urgent',
            'classification' => Data_Classification::SOURCE_PROTECTED,
            'ownerUserId' => 42,
            'explicitAclGranted' => false,
            'title' => 'Dit mag nooit uitlekken',
            'body' => 'Geheime broninhoud',
        ),
        array(
            'id' => 902,
            'status' => 'assigned',
            'priority' => 'urgent',
            'classification' => Data_Classification::SOURCE_PROTECTED,
            'ownerUserId' => 42,
            'explicitAclGranted' => true,
            'title' => 'Ook dit mag niet als titel verschijnen',
            'body' => 'Nog meer geheime broninhoud',
        ),
        array(
            'id' => 903,
            'status' => 'new',
            'priority' => 'normal',
            'classification' => Data_Classification::CONFIDENTIAL,
            'ownerUserId' => 42,
            'safeTitle' => 'Vertrouwelijke redactie-intake',
            'contactEmail' => 'bron@example.invalid',
        ),
    ),
    $authorize_owner_42,
    $now,
    50
);

mvm_v5_cross_domain_assert( 2 === $intake['counts']['total'], 'protected case without explicit ACL must be dropped before output' );
mvm_v5_cross_domain_assert( 1 === $intake['counts']['protected'], 'only explicitly ACL-granted protected case may reach authorized projection' );
mvm_v5_cross_domain_assert( false === $intake['sensitivePayloadIncluded'], 'Intake projection must never include sensitive payload bodies' );
mvm_v5_cross_domain_assert( 'case-id-and-hub-link-only' === $intake['notificationPayloadPolicy'], 'sensitive notifications must remain metadata-only' );
mvm_v5_cross_domain_assert( 'Beschermde tip #902' === $intake['items'][0]['title'], 'protected case title must be redacted to case identity' );
foreach ( $intake['items'] as $item ) {
    mvm_v5_cross_domain_assert( ! array_key_exists( 'body', $item ), 'Intake work item must not contain message body' );
    mvm_v5_cross_domain_assert( ! array_key_exists( 'contactEmail', $item ), 'Intake work item must not contain contact details' );
}

$cross_domain = V5_Cross_Domain_My_Work_Model::build(
    array(
        'agenda' => $agenda['items'],
        'encyclopedie' => $encyclopedie['items'],
        'intake' => $intake['items'],
    ),
    $authorize_owner_42,
    $now,
    100
);

mvm_v5_cross_domain_assert( true === $cross_domain['authorizationBeforeRanking'], 'cross-domain My Work must authorize before global ranking' );
mvm_v5_cross_domain_assert( 5 === count( $cross_domain['items'] ), 'cross-domain My Work must combine all authorized domain items' );
mvm_v5_cross_domain_assert( 2 === $cross_domain['domainCounts']['agenda'], 'Agenda count must survive global aggregation' );
mvm_v5_cross_domain_assert( 1 === $cross_domain['domainCounts']['encyclopedie'], 'Encyclopedie count must survive global aggregation' );
mvm_v5_cross_domain_assert( 2 === $cross_domain['domainCounts']['intake'], 'Intake count must survive global aggregation' );
mvm_v5_cross_domain_assert( 5 === $cross_domain['preview']['summary']['total'], 'global My Work preview must represent all authorized domains' );
mvm_v5_cross_domain_assert( 1 === $cross_domain['preview']['summary']['protected'], 'global summary must account for authorized protected work without leaking denied cases' );

echo "PASS: MvM Hub V5 Agenda, Encyclopedie, protected Intake and cross-domain My Work smoke\n";
