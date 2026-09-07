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
require_once __DIR__ . '/../plugins/mvm-hub/core/class-v5-search-document-policy.php';

use MVM\Hub\Core\Data_Classification;
use MVM\Hub\Core\V5_Search_Document_Policy;

function mvm_v5_search_assert( bool $condition, string $message ): void {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
}

$public = array( 'id' => 1, 'classification' => Data_Classification::PUBLIC );
$internal = array( 'id' => 2, 'classification' => Data_Classification::INTERNAL );
$confidential = array( 'id' => 3, 'classification' => Data_Classification::CONFIDENTIAL );
$protected = array( 'id' => 4, 'classification' => Data_Classification::SOURCE_PROTECTED );
$secret = array( 'id' => 5, 'classification' => Data_Classification::SECRET );
$unknown = array( 'id' => 6, 'classification' => 'mystery' );
$collaboration = array(
    'id' => 7,
    'classification' => Data_Classification::INTERNAL,
    'search_visibility' => 'none',
);

$public_context = array( 'scope' => V5_Search_Document_Policy::SCOPE_PUBLIC );
$staff_context = array( 'scope' => V5_Search_Document_Policy::SCOPE_STAFF );

mvm_v5_search_assert( V5_Search_Document_Policy::allows( $public, $public_context ), 'public data must be eligible for public search' );
mvm_v5_search_assert( ! V5_Search_Document_Policy::allows( $internal, $public_context ), 'internal data must never enter public search' );
mvm_v5_search_assert( ! V5_Search_Document_Policy::allows( $confidential, $public_context ), 'confidential data must never enter public search' );
mvm_v5_search_assert( ! V5_Search_Document_Policy::allows( $protected, $public_context ), 'source-protected data must never enter public search' );
mvm_v5_search_assert( ! V5_Search_Document_Policy::allows( $secret, $public_context ), 'secret data must never enter public search' );

mvm_v5_search_assert( V5_Search_Document_Policy::allows( $public, $staff_context ), 'public data may enter shared staff search' );
mvm_v5_search_assert( V5_Search_Document_Policy::allows( $internal, $staff_context ), 'internal data may enter shared staff search unless explicitly hidden' );
mvm_v5_search_assert( ! V5_Search_Document_Policy::allows( $confidential, $staff_context ), 'confidential data must not enter shared staff search' );
mvm_v5_search_assert( ! V5_Search_Document_Policy::allows( $protected, $staff_context ), 'source-protected data must not enter shared staff search' );
mvm_v5_search_assert( ! V5_Search_Document_Policy::allows( $secret, $staff_context ), 'secret data must never be indexed' );
mvm_v5_search_assert( ! V5_Search_Document_Policy::allows( $unknown, $staff_context ), 'unknown classification must fail closed as confidential' );

$collaboration_decision = V5_Search_Document_Policy::decision( $collaboration, $staff_context );
mvm_v5_search_assert( false === $collaboration_decision['allowed'], 'internal collaboration content may opt out of general staff search' );
mvm_v5_search_assert( 'search_visibility_denied' === $collaboration_decision['reason'], 'search opt-out denial must be explicit' );
mvm_v5_search_assert( 'none' === $collaboration_decision['search_visibility'], 'decision must expose normalized search visibility' );

$confidential_direct = array(
    'scope' => V5_Search_Document_Policy::SCOPE_STAFF,
    'direct_object_lookup' => true,
    'object_access_allowed' => true,
);
mvm_v5_search_assert( V5_Search_Document_Policy::allows( $confidential, $confidential_direct ), 'authorized confidential direct lookup may pass outside the shared index' );

$confidential_denied = $confidential_direct;
$confidential_denied['object_access_allowed'] = false;
$confidential_decision = V5_Search_Document_Policy::decision( $confidential, $confidential_denied );
mvm_v5_search_assert( false === $confidential_decision['allowed'], 'confidential direct lookup must require object policy' );
mvm_v5_search_assert( 'object_access_denied' === $confidential_decision['reason'], 'confidential object denial must be explicit' );

$protected_direct = array(
    'scope' => V5_Search_Document_Policy::SCOPE_STAFF,
    'direct_object_lookup' => true,
    'object_access_allowed' => true,
    'step_up_satisfied' => true,
);
mvm_v5_search_assert( V5_Search_Document_Policy::allows( $protected, $protected_direct ), 'source-protected direct lookup may pass only after object ACL plus step-up' );

$protected_no_step_up = $protected_direct;
$protected_no_step_up['step_up_satisfied'] = false;
$step_up_decision = V5_Search_Document_Policy::decision( $protected, $protected_no_step_up );
mvm_v5_search_assert( false === $step_up_decision['allowed'], 'source-protected lookup must require step-up' );
mvm_v5_search_assert( 'step_up_required' === $step_up_decision['reason'], 'source-protected step-up denial must be explicit' );

$protected_shared_decision = V5_Search_Document_Policy::decision( $protected, $staff_context );
mvm_v5_search_assert( 'source_protected_shared_index_denied' === $protected_shared_decision['reason'], 'protected data must be excluded from shared indexes' );

$results = V5_Search_Document_Policy::filter_results(
    array( $public, $internal, $confidential, $protected, $secret, $unknown, $collaboration ),
    $staff_context
);
mvm_v5_search_assert( 2 === count( $results ), 'staff shared result filter must only return safe searchable public and internal data' );
mvm_v5_search_assert( 1 === $results[0]['id'] && 2 === $results[1]['id'], 'staff result filter must preserve only safe searchable documents' );

mvm_v5_search_assert( ! V5_Search_Document_Policy::allows( $public, array( 'scope' => 'invalid' ) ), 'unknown search scope must fail closed' );

echo "PASS: MvM Hub V5 search policy prevents classification and explicit-visibility leakage\n";
