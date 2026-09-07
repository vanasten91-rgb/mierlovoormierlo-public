<?php

declare(strict_types=1);

define( 'ABSPATH', __DIR__ . '/' );

if ( ! function_exists( 'sanitize_key' ) ) {
    function sanitize_key( mixed $value ): string {
        $value = strtolower( (string) $value );
        return (string) preg_replace( '/[^a-z0-9_\-]/', '', $value );
    }
}
if ( ! function_exists( 'absint' ) ) {
    function absint( mixed $value ): int {
        return abs( (int) $value );
    }
}
if ( ! function_exists( 'wp_strip_all_tags' ) ) {
    function wp_strip_all_tags( mixed $value, bool $remove_breaks = false ): string {
        $value = strip_tags( (string) $value );
        return $remove_breaks ? preg_replace( '/[\r\n\t ]+/', ' ', $value ) ?? '' : $value;
    }
}

require_once __DIR__ . '/../plugins/mvm-hub/core/class-data-classification.php';
require_once __DIR__ . '/../plugins/mvm-hub/core/class-v5-search-document-policy.php';
require_once __DIR__ . '/../plugins/mvm-hub/core/class-v5-search-shared-read-model.php';

use MVM\Hub\Core\Data_Classification;
use MVM\Hub\Core\V5_Search_Document_Policy;
use MVM\Hub\Core\V5_Search_Shared_Read_Model;

function mvm_v5_search_read_assert( bool $condition, string $message ): void {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
}

$documents = array(
    array(
        'domain' => 'newsroom',
        'object_type' => 'post',
        'object_id' => 101,
        'title' => '<strong>Nieuws</strong> titel',
        'excerpt' => "Veilige\npreview",
        'classification' => Data_Classification::PUBLIC,
        'score' => 42,
        'provider_payload' => 'must not leak',
        'source_notes' => 'must not leak',
    ),
    array(
        'domain' => 'encyclopedie',
        'object_type' => 'mvm_encyclopedie',
        'object_id' => 202,
        'title' => 'Interne conceptverwijzing',
        'excerpt' => 'Alleen staf',
        'classification' => Data_Classification::INTERNAL,
        'score' => 25,
    ),
    array(
        'domain' => 'intake',
        'object_type' => 'case',
        'object_id' => 303,
        'title' => 'Vertrouwelijke tip',
        'excerpt' => 'Niet in gedeelde search',
        'classification' => Data_Classification::CONFIDENTIAL,
        'score' => 99,
    ),
    array(
        'domain' => 'sources',
        'object_type' => 'source',
        'object_id' => 404,
        'title' => 'Beschermde bron',
        'excerpt' => 'Nooit delen',
        'classification' => Data_Classification::SOURCE_PROTECTED,
        'score' => 100,
    ),
);

$public = V5_Search_Shared_Read_Model::build(
    $documents,
    array( 'scope' => V5_Search_Document_Policy::SCOPE_PUBLIC )
);
mvm_v5_search_read_assert( 1 === $public['total'], 'public shared model must expose only public document' );
mvm_v5_search_read_assert( 101 === $public['results'][0]['object_id'], 'public result must be the safe public document' );
mvm_v5_search_read_assert( 'Nieuws titel' === $public['results'][0]['title'], 'shared result titles must be stripped of markup' );
mvm_v5_search_read_assert( ! array_key_exists( 'provider_payload', $public['results'][0] ), 'arbitrary provider payload must never pass through' );
mvm_v5_search_read_assert( ! array_key_exists( 'source_notes', $public['results'][0] ), 'source notes must never pass through' );

$staff = V5_Search_Shared_Read_Model::build(
    $documents,
    array(
        'scope' => V5_Search_Document_Policy::SCOPE_STAFF,
        // Attempted escalation must be ignored by the shared model.
        'direct_object_lookup' => true,
        'object_access_allowed' => true,
        'step_up_satisfied' => true,
    )
);
mvm_v5_search_read_assert( 2 === $staff['total'], 'staff shared model must expose public + internal only' );
mvm_v5_search_read_assert( 2 === $staff['dropped'], 'confidential and protected candidates must be dropped' );
mvm_v5_search_read_assert( 101 === $staff['results'][0]['object_id'], 'first safe staff result must remain public document' );
mvm_v5_search_read_assert( 202 === $staff['results'][1]['object_id'], 'second safe staff result must remain internal document' );

$invalid = V5_Search_Shared_Read_Model::build(
    array(
        array(
            'domain' => '',
            'object_type' => 'post',
            'object_id' => 1,
            'title' => 'Invalid',
            'classification' => Data_Classification::PUBLIC,
        ),
    ),
    array( 'scope' => V5_Search_Document_Policy::SCOPE_PUBLIC )
);
mvm_v5_search_read_assert( 0 === $invalid['total'], 'invalid shared-search documents must be dropped' );

echo "PASS: MvM Hub V5 shared-search read model is bounded, stripped and leak-safe\n";
