<?php

define( 'ABSPATH', __DIR__ . '/' );

function sanitize_key( string $value ): string { $value = strtolower( trim( $value ) ); return preg_replace( '/[^a-z0-9_\-]/', '', $value ) ?? ''; }
function wp_strip_all_tags( string $value, bool $remove_breaks = false ): string { $value = strip_tags( $value ); return $remove_breaks ? preg_replace( '/[\r\n\t]+/', ' ', $value ) ?? $value : $value; }

require_once __DIR__ . '/../plugins/mvm-hub/core/class-data-classification.php';
require_once __DIR__ . '/../plugins/mvm-hub/modules/community/class-v5-community-owner-catalog.php';
require_once __DIR__ . '/../plugins/mvm-hub/modules/community/class-v5-community-moderation-model.php';
require_once __DIR__ . '/../plugins/mvm-hub/modules/layout/class-v5-layout-template-registry.php';
require_once __DIR__ . '/../plugins/mvm-hub/modules/layout/class-v5-layout-studio-model.php';

use MVM\Hub\Modules\Community\V5_Community_Moderation_Model;
use MVM\Hub\Modules\Layout\V5_Layout_Studio_Model;
use MVM\Hub\Modules\Layout\V5_Layout_Template_Registry;

function mvm_v5_cl_assert( bool $condition, string $message ): void {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
}

$community = V5_Community_Moderation_Model::build(
    array(
        array( 'owner' => 'peepso_activity', 'objectId' => 'activity:42', 'reportId' => 'r1', 'severity' => 'urgent', 'reason' => '<b>Persoonlijke aanval</b>', 'body' => 'must not leak' ),
        array( 'owner' => 'wpforo_content', 'objectId' => 'topic:7', 'reportId' => 'r2', 'severity' => 'normal', 'reason' => 'Spam' ),
        array( 'owner' => 'unknown', 'objectId' => 'x', 'reportId' => 'r3', 'severity' => 'urgent', 'reason' => 'Unknown owner' ),
        array( 'owner' => 'peepso_activity', 'objectId' => 'activity:99', 'reportId' => 'blocked', 'severity' => 'urgent', 'reason' => 'Should be hidden' ),
    ),
    static fn( array $request ): bool => 'blocked' !== ( $request['reportId'] ?? '' )
);

mvm_v5_cl_assert( 2 === $community['count'], 'unknown and unauthorized moderation candidates must be excluded' );
mvm_v5_cl_assert( 'r1' === $community['items'][0]['reportId'], 'urgent authorized report should sort first' );
mvm_v5_cl_assert( 'Persoonlijke aanval' === $community['items'][0]['reason'], 'moderation reason should be plain text' );
mvm_v5_cl_assert( ! isset( $community['items'][0]['body'] ), 'community body must not leak into generic moderation queue' );
mvm_v5_cl_assert( 'none' === $community['items'][0]['searchVisibility'], 'moderation queue must stay outside shared search' );
mvm_v5_cl_assert( false === $community['items'][0]['v5WriteOwner'], 'V5 must not steal PeepSo/wpForo write ownership during coexistence' );

$layout = V5_Layout_Template_Registry::normalize(
    'news-main',
    'news-grid-b',
    array( 'items' => 999, 'category' => 'Politiek<script>', 'show_excerpt' => false, 'php' => '<?php evil(); ?>' )
);
mvm_v5_cl_assert( is_array( $layout ), 'known slot/template should normalize' );
mvm_v5_cl_assert( 12 === $layout['settings']['items'], 'layout integer settings must be bounded' );
mvm_v5_cl_assert( 'politiekscript' === $layout['settings']['category'], 'layout key settings must be sanitized' );
mvm_v5_cl_assert( ! isset( $layout['settings']['php'] ), 'unknown/executable settings must be dropped' );
mvm_v5_cl_assert( false === $layout['executableContentAllowed'], 'Layout Studio must never permit executable content' );
mvm_v5_cl_assert( null === V5_Layout_Template_Registry::normalize( 'agenda-main', 'news-grid-b', array() ), 'template cannot be attached to a different slot' );

$draft = V5_Layout_Studio_Model::draft( array(
    'slot' => 'news-main',
    'template' => 'news-grid-b',
    'settings' => array( 'items' => 6 ),
    'status' => 'approved',
    'revision' => 3,
) );
mvm_v5_cl_assert( is_array( $draft ), 'valid layout draft should project' );
mvm_v5_cl_assert( false === $draft['productionActivated'], 'Layout Studio preview must never activate production' );

$signals = array(
    'reviewedRendererExists' => true,
    'desktopPreviewApproved' => true,
    'tabletPreviewApproved' => true,
    'mobilePreviewApproved' => true,
    'lightPreviewApproved' => true,
    'darkPreviewApproved' => true,
    'accessibilityChecked' => true,
    'rollbackRevisionExists' => true,
);
$ready = V5_Layout_Studio_Model::release_readiness( $draft, $signals );
mvm_v5_cl_assert( true === $ready['canRequestPublish'], 'approved and fully checked layout may request publish' );
mvm_v5_cl_assert( false === $ready['canPublishProduction'], 'pure readiness model must never publish production' );

$not_ready = V5_Layout_Studio_Model::release_readiness( array_replace( $draft, array( 'status' => 'review' ) ), array_replace( $signals, array( 'accessibilityChecked' => false ) ) );
mvm_v5_cl_assert( false === $not_ready['canRequestPublish'], 'missing accessibility/editorial approval must block publish request' );
mvm_v5_cl_assert( in_array( 'accessibilityChecked', $not_ready['missing'], true ), 'layout readiness must explain missing accessibility check' );
mvm_v5_cl_assert( in_array( 'editorialApproval', $not_ready['missing'], true ), 'layout readiness must explain missing editorial approval' );

echo "PASS: MvM Hub V5 Community moderation and Layout Studio safety smoke\n";
