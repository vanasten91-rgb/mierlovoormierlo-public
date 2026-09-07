<?php

define( 'ABSPATH', __DIR__ . '/' );
function sanitize_key( string $value ): string { $value = strtolower( trim( $value ) ); return preg_replace( '/[^a-z0-9_\-]/', '', $value ) ?? ''; }
function wp_strip_all_tags( string $value, bool $remove_breaks = false ): string { $value = strip_tags( $value ); return $remove_breaks ? preg_replace( '/[\r\n\t]+/', ' ', $value ) ?? $value : $value; }
function wp_json_encode( mixed $value, int $flags = 0 ): string|false { return json_encode( $value, $flags ); }

require_once __DIR__ . '/../plugins/mvm-hub/core/class-data-classification.php';
require_once __DIR__ . '/../plugins/mvm-hub/core/class-v5-domain-event-outbox-contract.php';

use MVM\Hub\Core\V5_Domain_Event_Outbox_Contract;

function mvm_v5_outbox_assert( bool $condition, string $message ): void {
    if ( ! $condition ) { fwrite( STDERR, "FAIL: {$message}\n" ); exit( 1 ); }
}

$event = V5_Domain_Event_Outbox_Contract::normalize_event( array(
    'name' => 'news.published',
    'aggregateType' => 'post',
    'aggregateId' => 'post:42',
    'correlationId' => 'req:abc123',
    'classification' => 'internal',
    'payload' => array(
        'postId' => 42,
        'published' => true,
        'body' => 'must not leak',
        'subject' => 'must not leak',
        'source_contact' => 'must not leak',
        'reason' => '<b>publish</b>',
    ),
) );
mvm_v5_outbox_assert( is_array( $event ), 'valid metadata event should normalize' );
mvm_v5_outbox_assert( 42 === $event['payload']['postid'], 'safe scalar metadata should survive' );
mvm_v5_outbox_assert( ! isset( $event['payload']['body'] ), 'event outbox must drop body' );
mvm_v5_outbox_assert( ! isset( $event['payload']['subject'] ), 'event outbox must drop subject' );
mvm_v5_outbox_assert( ! isset( $event['payload']['source_contact'] ), 'event outbox must drop protected source contact' );
mvm_v5_outbox_assert( 'publish' === $event['payload']['reason'], 'safe text metadata should be stripped of markup' );
mvm_v5_outbox_assert( 'none' === $event['searchVisibility'] && 'none' === $event['aiVisibility'], 'outbox payload must never become discoverability/AI context' );

$too_many = array();
for ( $i = 0; $i < 33; $i++ ) $too_many[ 'k' . $i ] = $i;
mvm_v5_outbox_assert( null === V5_Domain_Event_Outbox_Contract::normalize_event( array(
    'name' => 'news.published', 'aggregateType' => 'post', 'aggregateId' => 'post:42', 'correlationId' => 'req:2', 'classification' => 'internal', 'payload' => $too_many,
) ), 'oversized key-count payload must fail closed' );

$storage = V5_Domain_Event_Outbox_Contract::storage_contract( 'wpmvm' );
mvm_v5_outbox_assert( true === $storage['valid'], 'valid DB prefix should produce outbox contract' );
mvm_v5_outbox_assert( false === $storage['productionWrites'], 'outbox storage contract must not execute production writes' );
mvm_v5_outbox_assert( str_contains( $storage['sql'], 'UNIQUE KEY correlation_event' ), 'outbox must deduplicate correlation/event identity' );
mvm_v5_outbox_assert( str_contains( $storage['sql'], 'pending_available' ), 'outbox must support bounded pending scheduling' );
mvm_v5_outbox_assert( false === V5_Domain_Event_Outbox_Contract::storage_contract( 'bad-prefix!' )['valid'], 'unsafe DB prefix must fail closed' );

echo "PASS: MvM Hub V5 durable domain-event outbox safety smoke\n";
