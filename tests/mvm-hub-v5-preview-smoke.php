<?php

declare(strict_types=1);

// Standalone executable smoke for dormant V5 navigation, My Work preview,
// Newsroom queue projection and ContextLinks read models. No WP boot/database IO.
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
if ( ! function_exists( 'esc_url_raw' ) ) {
    function esc_url_raw( mixed $value ): string {
        $value = trim( (string) $value );
        return preg_match( '#^https?://#i', $value ) ? $value : '';
    }
}
if ( ! function_exists( 'esc_html' ) ) {
    function esc_html( mixed $value ): string {
        return htmlspecialchars( (string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
    }
}
if ( ! function_exists( 'esc_attr' ) ) {
    function esc_attr( mixed $value ): string {
        return esc_html( $value );
    }
}

require_once __DIR__ . '/../plugins/mvm-hub/core/class-data-classification.php';
require_once __DIR__ . '/../plugins/mvm-hub/core/class-work-item-schema.php';
require_once __DIR__ . '/../plugins/mvm-hub/core/class-my-work-prioritizer.php';
require_once __DIR__ . '/../plugins/mvm-hub/core/class-my-work-read-model.php';
require_once __DIR__ . '/../plugins/mvm-hub/core/class-v5-workspace-catalog.php';
require_once __DIR__ . '/../plugins/mvm-hub/core/class-v5-workspace-policy.php';
require_once __DIR__ . '/../plugins/mvm-hub/core/class-v5-shell-preview-model.php';
require_once __DIR__ . '/../plugins/mvm-hub/core/class-v5-my-work-preview-model.php';
require_once __DIR__ . '/../plugins/mvm-hub/core/class-v5-shell-preview-renderer.php';
require_once __DIR__ . '/../plugins/mvm-hub/modules/newsroom/class-v5-newsroom-read-adapter.php';
require_once __DIR__ . '/../plugins/mvm-hub/modules/newsroom/class-v5-newsroom-queue-model.php';
require_once __DIR__ . '/../plugins/mvm-hub/integrations/encyclopedie/class-v5-context-links-read-model.php';

use MVM\Hub\Core\Data_Classification;
use MVM\Hub\Core\My_Work_Read_Model;
use MVM\Hub\Core\V5_My_Work_Preview_Model;
use MVM\Hub\Core\V5_Shell_Preview_Model;
use MVM\Hub\Core\V5_Shell_Preview_Renderer;
use MVM\Hub\Core\V5_Workspace_Policy;
use MVM\Hub\Core\Work_Item_Schema;
use MVM\Hub\Integrations\Encyclopedie\V5_Context_Links_Read_Model;
use MVM\Hub\Modules\Newsroom\V5_Newsroom_Queue_Model;

function mvm_v5_preview_assert( bool $condition, string $message ): void {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
}

$granted = array(
    'mvm_newsroom_access',
    'mvm_media_view',
    'mvm_contextlinks_review',
);
$capability_check = static fn( string $capability, array $context ): bool => in_array( $capability, $granted, true );
$allowed_workspaces = V5_Workspace_Policy::allowed_workspaces( $capability_check );

mvm_v5_preview_assert( in_array( 'my-work', $allowed_workspaces, true ), 'staff with Newsroom rights must receive My Work preview navigation' );
mvm_v5_preview_assert( in_array( 'newsroom', $allowed_workspaces, true ), 'Newsroom capability must expose Newsroom preview navigation' );
mvm_v5_preview_assert( in_array( 'media', $allowed_workspaces, true ), 'media capability must expose Media preview navigation' );
mvm_v5_preview_assert( in_array( 'encyclopedie', $allowed_workspaces, true ), 'ContextLinks review capability must expose Encyclopedie preview navigation' );
mvm_v5_preview_assert( ! in_array( 'technical', $allowed_workspaces, true ), 'Newsroom rights must not imply technical workspace access' );
mvm_v5_preview_assert( ! in_array( 'mierlokaal', $allowed_workspaces, true ), 'ordinary Newsroom rights must not imply staff MierLokaal access' );

$mierlokaal_denied = V5_Workspace_Policy::decide( 'mierlokaal', $capability_check );
mvm_v5_preview_assert( false === $mierlokaal_denied['allowed'], 'staff MierLokaal workspace must deny without an internal management capability' );
mvm_v5_preview_assert( 'capability_denied' === $mierlokaal_denied['reason'], 'resolved MierLokaal policy must deny by capability rather than unresolved inventory' );

$mierlokaal_internal_check = static fn( string $capability, array $context ): bool => 'mvm_local_ads_manage' === $capability;
$mierlokaal_internal = V5_Workspace_Policy::decide( 'mierlokaal', $mierlokaal_internal_check );
mvm_v5_preview_assert( true === $mierlokaal_internal['allowed'], 'internal local-ads management capability must expose staff MierLokaal workspace' );
mvm_v5_preview_assert( 'mvm_local_ads_manage' === $mierlokaal_internal['matched_capability'], 'MierLokaal decision must explain the exact internal capability grant' );

$mierlokaal_marketplace_check = static fn( string $capability, array $context ): bool => 'mvm_marketplace_moderate' === $capability;
$mierlokaal_marketplace = V5_Workspace_Policy::decide( 'mierlokaal', $mierlokaal_marketplace_check );
mvm_v5_preview_assert( true === $mierlokaal_marketplace['allowed'], 'marketplace moderation capability must expose staff MierLokaal workspace' );

$partner_self_service_check = static fn( string $capability, array $context ): bool => 'mvm_local_ads_self_manage' === $capability;
$partner_mierlokaal = V5_Workspace_Policy::decide( 'mierlokaal', $partner_self_service_check );
mvm_v5_preview_assert( false === $partner_mierlokaal['allowed'], 'partner self-service capability must never grant the staff MierLokaal workspace' );

$legacy_business_check = static fn( string $capability, array $context ): bool => 'mvm_hub3_business_access' === $capability;
$legacy_mierlokaal = V5_Workspace_Policy::decide( 'mierlokaal', $legacy_business_check );
mvm_v5_preview_assert( false === $legacy_mierlokaal['allowed'], 'legacy Hub3 business access must not become a V5 staff workspace dependency' );

$shell = V5_Shell_Preview_Model::build( $allowed_workspaces, 'technical' );
mvm_v5_preview_assert( 'my-work' === $shell['activeWorkspace'], 'forbidden requested workspace must fall back to authorized My Work' );
mvm_v5_preview_assert( false === $shell['routeOwnership'], 'preview shell must not own live routing' );

$now = new DateTimeImmutable( '2026-09-04T00:00:00Z' );
$base = array(
    'type'                => 'news_task',
    'domain'              => 'newsroom',
    'object_type'         => 'post',
    'owner_user_id'       => 42,
    'owner_team'          => '',
    'workflow_key'        => 'news_regular',
    'workflow_version'    => 1,
    'required_capability' => 'mvm_newsroom_access',
    'dependency_ids'      => array(),
    'checklist'           => array(),
    'classification'      => Data_Classification::INTERNAL,
);
$records = array(
    array_merge( $base, array(
        'id'             => 11,
        'object_id'      => 201,
        'title'          => 'Controleer bronnen en feiten',
        'status'         => Work_Item_Schema::STATUS_IN_PROGRESS,
        'priority'       => Work_Item_Schema::PRIORITY_HIGH,
        'workflow_state' => 'source_check',
        'deadline_utc'   => '2026-09-04T03:00:00Z',
        'blocker_reason' => '',
    ) ),
    array_merge( $base, array(
        'id'             => 12,
        'object_id'      => 202,
        'title'          => 'Wacht op aanvullende foto',
        'status'         => Work_Item_Schema::STATUS_BLOCKED,
        'priority'       => Work_Item_Schema::PRIORITY_NORMAL,
        'workflow_state' => 'media',
        'deadline_utc'   => '',
        'blocker_reason' => 'Fotografie nog niet aangeleverd',
    ) ),
);
$authorized_items = My_Work_Read_Model::build(
    $records,
    static fn( array $item ): array => array( 'allowed' => 42 === (int) $item['owner_user_id'] ),
    $now,
    50
);
$my_work = V5_My_Work_Preview_Model::build( $authorized_items, $now );

mvm_v5_preview_assert( 2 === $my_work['summary']['total'], 'My Work preview must summarize authorized active items only' );
mvm_v5_preview_assert( 1 === $my_work['summary']['blocked'], 'blocked work must receive its own lane' );
mvm_v5_preview_assert( 1 === $my_work['summary']['today'], 'near deadline work must appear in Today lane' );
mvm_v5_preview_assert( 1 === count( $my_work['lanes']['blocked'] ), 'blocked lane must contain blocked item' );
mvm_v5_preview_assert( 1 === count( $my_work['lanes']['today'] ), 'today lane must contain near-deadline item' );

$html = V5_Shell_Preview_Renderer::render( $shell, $my_work );
mvm_v5_preview_assert( str_contains( $html, 'data-route-ownership="0"' ), 'rendered preview must identify itself as non-routing' );
mvm_v5_preview_assert( str_contains( $html, 'Waarom staat dit bovenaan?' ), 'rendered cards must explain prioritization' );
mvm_v5_preview_assert( ! str_contains( $html, 'href=' ), 'preview navigation must not create live V5 links before route takeover' );
mvm_v5_preview_assert( str_contains( $html, 'Controleer bronnen en feiten' ), 'authorized task title must render in preview' );

$newsroom = V5_Newsroom_Queue_Model::build(
    array(
        array(
            'id'             => 301,
            'title'          => 'Interview voorbereiden',
            'workflowState'  => 'review',
            'assigneeUserId' => 42,
            'priority'       => 2,
            'dueAtUtc'       => '2026-09-04T05:00:00Z',
        ),
    ),
    array(
        array(
            'id'           => 401,
            'title'        => 'Kermisbericht',
            'workflowState'=> 'scheduled',
            'authorUserId' => 42,
        ),
    ),
    static fn( array $item ): array => array( 'allowed' => 42 === (int) $item['owner_user_id'] ),
    $now,
    50
);

mvm_v5_preview_assert( true === $newsroom['readOnly'], 'Newsroom V5 queue must remain read-only in coexistence' );
mvm_v5_preview_assert( true === $newsroom['legacyOwnershipPreserved'], 'current Newsroom owner must remain canonical during coexistence' );
mvm_v5_preview_assert( 1 === $newsroom['counts']['assignments'], 'assignment must project into V5 queue' );
mvm_v5_preview_assert( 1 === $newsroom['counts']['articles'], 'news article must project into V5 queue' );
mvm_v5_preview_assert( 1 === $newsroom['counts']['review'], 'review-state assignment must be visible in review queue' );
mvm_v5_preview_assert( 1 === $newsroom['counts']['scheduled'], 'scheduled article must be visible in scheduled queue' );

$context_links = V5_Context_Links_Read_Model::build(
    401,
    array(
        'available' => true,
        'source' => 'legacy-smart-links-adapter',
        'conflicts' => 1,
        'suggestions' => array(
            array(
                'targetId' => 501,
                'label' => 'Molenplein',
                'targetTitle' => 'Molenplein',
                'targetType' => 'artikel',
                'targetUrl' => 'https://www.mierlovoormierlo.nl/encyclopedie/artikel/molenplein/',
            ),
            array(
                'targetId' => 501,
                'label' => 'Molenplein',
                'targetTitle' => 'Duplicaat',
                'targetType' => 'artikel',
                'targetUrl' => 'https://www.mierlovoormierlo.nl/encyclopedie/artikel/molenplein/',
            ),
            array(
                'targetId' => 502,
                'label' => 'Puur Sang',
                'targetTitle' => 'Moderne basisscholen',
                'targetType' => 'artikel',
                'targetUrl' => 'https://www.mierlovoormierlo.nl/encyclopedie/artikel/moderne-basisscholen/',
            ),
        ),
    ),
    12
);

mvm_v5_preview_assert( true === $context_links['available'], 'ContextLinks preview must expose available read context' );
mvm_v5_preview_assert( true === $context_links['readOnly'], 'ContextLinks preview must remain read-only' );
mvm_v5_preview_assert( 2 === count( $context_links['links'] ), 'ContextLinks preview must deduplicate identical suggestions' );
mvm_v5_preview_assert( 'proposed' === $context_links['links'][0]['reviewState'], 'ContextLinks candidates must begin as proposed review state' );
mvm_v5_preview_assert( 'existing-smart-links-runtime' === $context_links['owner'], 'existing Smart Links runtime must remain canonical owner during preview' );

echo "PASS: MvM Hub V5 workspace, MierLokaal boundary, My Work preview, Newsroom queue and ContextLinks smoke\n";
