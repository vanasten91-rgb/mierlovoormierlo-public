<?php

declare(strict_types=1);

// Standalone executable security smoke for dormant Hub V5 policy primitives.
// It intentionally boots no WordPress runtime and performs no database/network IO.
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
require_once __DIR__ . '/../plugins/mvm-hub/core/class-v5-policy-resolver.php';

use MVM\Hub\Core\Data_Classification;
use MVM\Hub\Core\My_Work_Read_Model;
use MVM\Hub\Core\V5_Policy_Resolver;
use MVM\Hub\Core\Work_Item_Schema;

function mvm_v5_assert( bool $condition, string $message ): void {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
}

$protected_request = array(
    'action'                => 'source.read',
    'capability'            => 'mvm_sources_view',
    'classification'        => Data_Classification::SOURCE_PROTECTED,
    'object_type'           => 'source_case',
    'object_id'             => 77,
    'explicit_acl_granted'  => false,
    'requires_object_check' => true,
    'requires_step_up'      => true,
);

$deny_capability = V5_Policy_Resolver::evaluate(
    $protected_request,
    static fn( string $capability, array $context ): bool => false,
    static fn( array $context ): bool => true,
    static fn( array $context ): bool => true
);
mvm_v5_assert( false === $deny_capability['allowed'], 'missing capability grant must deny' );
mvm_v5_assert( 'capability_denied' === $deny_capability['reason'], 'capability denial must be explicit' );

$missing_acl = V5_Policy_Resolver::evaluate(
    $protected_request,
    static fn( string $capability, array $context ): bool => true,
    static fn( array $context ): bool => true,
    static fn( array $context ): bool => true
);
mvm_v5_assert( false === $missing_acl['allowed'], 'source-protected object without explicit ACL must deny' );
mvm_v5_assert( 'explicit_acl_required' === $missing_acl['reason'], 'protected-source denial must name ACL requirement' );

$acl_request = $protected_request;
$acl_request['explicit_acl_granted'] = true;

$idor_attempt = V5_Policy_Resolver::evaluate(
    $acl_request,
    static fn( string $capability, array $context ): bool => true,
    static fn( array $context ): bool => 88 === (int) $context['object_id'],
    static fn( array $context ): bool => true
);
mvm_v5_assert( false === $idor_attempt['allowed'], 'wrong-object access must be denied even with capability and ACL flag' );
mvm_v5_assert( 'object_access_denied' === $idor_attempt['reason'], 'IDOR-style denial must be object scoped' );

$missing_step_up = V5_Policy_Resolver::evaluate(
    $acl_request,
    static fn( string $capability, array $context ): bool => true,
    static fn( array $context ): bool => 77 === (int) $context['object_id'],
    static fn( array $context ): bool => false
);
mvm_v5_assert( false === $missing_step_up['allowed'], 'sensitive action without step-up must deny' );
mvm_v5_assert( 'step_up_required' === $missing_step_up['reason'], 'step-up denial must be explicit' );

$allowed = V5_Policy_Resolver::evaluate(
    $acl_request,
    static fn( string $capability, array $context ): bool => 'mvm_sources_view' === $capability,
    static fn( array $context ): bool => 'source_case' === $context['object_type'] && 77 === (int) $context['object_id'],
    static fn( array $context ): bool => true
);
mvm_v5_assert( true === $allowed['allowed'], 'fully authorized protected-source request should allow' );

$now = new DateTimeImmutable( '2026-09-04T00:00:00Z' );
$base_item = array(
    'type'                => 'news_task',
    'domain'              => 'newsroom',
    'object_type'         => 'post',
    'title'               => 'Controleer artikel',
    'owner_team'          => '',
    'status'              => Work_Item_Schema::STATUS_OPEN,
    'workflow_key'        => 'news_regular',
    'workflow_version'    => 1,
    'workflow_state'      => 'review',
    'required_capability' => 'mvm_news_review',
    'dependency_ids'      => array(),
    'blocker_reason'      => '',
    'checklist'           => array(),
);

$records = array(
    array_merge(
        $base_item,
        array(
            'id'             => 1,
            'object_id'      => 101,
            'owner_user_id'  => 42,
            'priority'       => Work_Item_Schema::PRIORITY_HIGH,
            'deadline_utc'   => '2026-09-04T01:00:00Z',
            'classification' => Data_Classification::INTERNAL,
        )
    ),
    array_merge(
        $base_item,
        array(
            'id'             => 2,
            'object_id'      => 102,
            'owner_user_id'  => 99,
            'priority'       => Work_Item_Schema::PRIORITY_URGENT,
            'deadline_utc'   => '2026-09-03T23:00:00Z',
            'classification' => Data_Classification::SOURCE_PROTECTED,
        )
    ),
    array_merge(
        $base_item,
        array(
            'id'             => 3,
            'object_id'      => 103,
            'owner_user_id'  => 42,
            'priority'       => Work_Item_Schema::PRIORITY_NORMAL,
            'deadline_utc'   => '',
            'classification' => Data_Classification::INTERNAL,
        )
    ),
    array_merge(
        $base_item,
        array(
            'id'             => 4,
            'object_id'      => 104,
            'owner_user_id'  => 42,
            'priority'       => Work_Item_Schema::PRIORITY_URGENT,
            'status'         => Work_Item_Schema::STATUS_DONE,
            'deadline_utc'   => '2026-09-03T22:00:00Z',
            'classification' => Data_Classification::INTERNAL,
        )
    ),
);

$read_model = My_Work_Read_Model::build(
    $records,
    static fn( array $item ): array => array( 'allowed' => 42 === (int) $item['owner_user_id'] ),
    $now,
    50
);

mvm_v5_assert( 2 === count( $read_model ), 'My Work must return only authorized active items' );
mvm_v5_assert( 1 === (int) $read_model[0]['id'], 'nearest high-priority authorized deadline should rank first' );
mvm_v5_assert( 3 === (int) $read_model[1]['id'], 'normal authorized item should remain in read model' );
$ids = array_map( static fn( array $item ): int => (int) $item['id'], $read_model );
mvm_v5_assert( ! in_array( 2, $ids, true ), 'unauthorized source-protected item must never leak into My Work' );
mvm_v5_assert( ! in_array( 4, $ids, true ), 'completed item must not appear in active My Work' );
mvm_v5_assert( is_int( $read_model[0]['my_work_score'] ), 'authorized work must carry deterministic score' );
mvm_v5_assert( ! empty( $read_model[0]['my_work_reasons'] ), 'authorized work must explain ranking' );

echo "PASS: MvM Hub V5 policy, IDOR and My Work authorization smoke\n";
