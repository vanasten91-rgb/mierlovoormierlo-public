<?php

define( 'ABSPATH', __DIR__ . '/' );

require_once __DIR__ . '/../plugins/mvm-hub/modules/communications/class-v5-mail-sync-state-machine.php';
require_once __DIR__ . '/../plugins/mvm-hub/modules/communications/class-v5-mail-sync-contract.php';
require_once __DIR__ . '/../plugins/mvm-hub/modules/communications/class-v5-mail-message-reference.php';
require_once __DIR__ . '/../plugins/mvm-hub/modules/communications/class-v5-mail-provider-profile.php';

use MVM\Hub\Modules\Communications\V5_Mail_Provider_Profile;
use MVM\Hub\Modules\Communications\V5_Mail_Sync_Contract;
use MVM\Hub\Modules\Communications\V5_Mail_Sync_State_Machine;

function mvm_mail_stack_assert( bool $condition, string $message ): void {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
}

$previous = array(
    '10' => array( 'revision' => 'r10', 'seen' => false, 'flagged' => false, 'size' => 100, 'subject' => 'must disappear' ),
    '20' => array( 'revision' => 'r20', 'seen' => false, 'flagged' => false, 'size' => 200 ),
    '30' => array( 'revision' => 'r30', 'seen' => false, 'flagged' => false, 'size' => 300 ),
);
$current = array(
    '10' => array( 'revision' => 'r10', 'seen' => false, 'flagged' => false, 'size' => 100 ),
    '20' => array( 'revision' => 'r20b', 'seen' => true, 'flagged' => false, 'size' => 200, 'body' => 'must disappear' ),
    '40' => array( 'revision' => 'r40', 'seen' => false, 'flagged' => true, 'size' => 400 ),
    '../bad' => array( 'revision' => 'evil', 'seen' => true ),
    '0001' => array( 'revision' => 'leading-zero-alias', 'seen' => true ),
    '0' => array( 'revision' => 'zero', 'seen' => true ),
    '4294967296' => array( 'revision' => 'overflow', 'seen' => true ),
);

$normalized = V5_Mail_Sync_State_Machine::normalize_messages( $current );
mvm_mail_stack_assert( array( '10', '20', '40' ) === array_map( 'strval', array_keys( $normalized ) ), 'only canonical positive 32-bit IMAP UIDs may enter sync state' );
mvm_mail_stack_assert( ! isset( $normalized['20']['body'] ), 'sync state must strip message bodies' );
mvm_mail_stack_assert( ! isset( $normalized['10']['subject'] ), 'sync state must strip subjects' );

$events = V5_Mail_Sync_State_Machine::diff_events( $previous, $current );
mvm_mail_stack_assert( 3 === count( $events ), 'diff must contain changed, added and deleted message events' );
mvm_mail_stack_assert( 'upsert' === $events[0]['type'] && '20' === $events[0]['uid'], 'changed state must become an upsert' );
mvm_mail_stack_assert( 'upsert' === $events[1]['type'] && '40' === $events[1]['uid'], 'new state must become an upsert' );
mvm_mail_stack_assert( 'deleted' === $events[2]['type'] && '30' === $events[2]['uid'], 'missing state must become a deletion' );

$page1 = V5_Mail_Sync_State_Machine::page( $events, 0, 2 );
mvm_mail_stack_assert( 2 === count( $page1['events'] ), 'first sync page should honor the limit' );
mvm_mail_stack_assert( true === $page1['hasMore'] && 2 === $page1['nextOffset'], 'first sync page should expose continuation state' );
$page2 = V5_Mail_Sync_State_Machine::page( $events, $page1['nextOffset'], 2 );
mvm_mail_stack_assert( 1 === count( $page2['events'] ) && false === $page2['hasMore'], 'final sync page should terminate cleanly' );

$initial = V5_Mail_Sync_State_Machine::initial_events( $current );
mvm_mail_stack_assert( 3 === count( $initial ), 'initial state should emit one safe event per valid message' );
foreach ( $initial as $event ) {
    mvm_mail_stack_assert( ! isset( $event['subject'], $event['body'], $event['from'], $event['filename'] ), 'sync events must remain metadata-only' );
}

$normalized_batch = V5_Mail_Sync_Contract::normalize_batch(
    array(
        'cursor' => 'v1.safe.cursor',
        'upserts' => array(
            array(
                'mailboxId' => 'editorial',
                'folderId' => 'SU5CT1g',
                'messageId' => 'SU5CT1g.20',
                'revision' => 'r20b',
                'seen' => true,
                'flagged' => false,
                'size' => 200,
                'subject' => 'never generic sync',
            ),
        ),
    )
);
mvm_mail_stack_assert( 1 === count( $normalized_batch['upserts'] ), 'provider batch should normalize into V5 sync contract' );
mvm_mail_stack_assert( ! isset( $normalized_batch['upserts'][0]['subject'] ), 'generic sync contract must strip provider content' );
mvm_mail_stack_assert( 'none' === $normalized_batch['searchVisibility'], 'sync state must stay outside search' );
mvm_mail_stack_assert( 'none' === $normalized_batch['aiVisibility'], 'sync state must stay outside AI context' );

$profile = V5_Mail_Provider_Profile::evaluate(
    array(
        'read' => true,
        'folders' => true,
        'attachments' => true,
        'flags' => true,
        'move' => true,
        'folderScopedRead' => true,
        'folderScopedMutations' => true,
        'attachmentFetch' => true,
        'send' => true,
    ),
    array(
        'initial' => true,
        'delta' => true,
        'deletions' => true,
        'cursorReset' => true,
    )
);
mvm_mail_stack_assert( true === $profile['readyForBidirectional'], 'strict provider profile should become bidirectional-ready only with receive, sync and send gates' );

$without_attachment_fetch = V5_Mail_Provider_Profile::evaluate(
    array_replace( $profile['providerCapabilities'], array( 'attachmentFetch' => false ) ),
    $profile['syncCapabilities']
);
mvm_mail_stack_assert( false === $without_attachment_fetch['readyForReceive'], 'missing incoming attachment fetch must fail receive readiness' );

$plugin_root = __DIR__ . '/../plugins/mvm-hub/';
$smtp_source = (string) file_get_contents( $plugin_root . 'integrations/mail/class-dedicated-smtp-mail-provider.php' );
$sent_source = (string) file_get_contents( $plugin_root . 'integrations/mail/class-sent-archiving-mail-provider.php' );
$factory_source = (string) file_get_contents( $plugin_root . 'modules/communications/class-communications-service-factory.php' );
$delivery_source = (string) file_get_contents( $plugin_root . 'modules/communications/class-mail-delivery-service.php' );
$bootstrap_source = (string) file_get_contents( $plugin_root . 'mvm-hub.php' );

mvm_mail_stack_assert( str_contains( $smtp_source, "'sentMime'" ) && str_contains( $smtp_source, 'getSentMIMEMessage' ), 'SMTP provider must hand the actual generated MIME to the Sent archiver' );
mvm_mail_stack_assert( str_contains( $sent_source, 'final class Sent_Archiving_Mail_Provider implements Mail_Provider' ), 'Sent archiver must remain a provider decorator' );
mvm_mail_stack_assert( str_contains( $sent_source, "'sent' !== (string) ( \$folder['specialUse'] ?? '' )" ), 'Sent archiver must resolve the canonical special-use Sent folder' );
mvm_mail_stack_assert( str_contains( $sent_source, '1 !== count( $matches )' ), 'ambiguous or missing Sent folders must fail closed before SMTP' );
mvm_mail_stack_assert( str_contains( $sent_source, 'imap_append' ) && str_contains( $sent_source, "'\\\\Seen'" ), 'successful SMTP must be archived into IMAP Sent as seen' );
mvm_mail_stack_assert( str_contains( $sent_source, 'MAX_SENT_MIME_BYTES' ), 'Sent archiver must bound in-memory MIME before append' );
mvm_mail_stack_assert( str_contains( $sent_source, "unset( \$result['sentMime'] )" ), 'raw MIME must be stripped before returning from the provider stack' );
mvm_mail_stack_assert( str_contains( $factory_source, 'new Sent_Archiving_Mail_Provider( $scoped )' ), 'factory must wrap the folder-scoped IMAP provider with Sent archiving' );
mvm_mail_stack_assert( str_contains( $bootstrap_source, "class-sent-archiving-mail-provider.php" ), 'Hub bootstrap must load the Sent archiver' );

$sent_gate = strpos( $delivery_source, "\$provider_capabilities['sentArchive']" );
$deliver_call = strpos( $delivery_source, '$this->provider->deliver' );
mvm_mail_stack_assert( false !== $sent_gate && false !== $deliver_call && $sent_gate < $deliver_call, 'delivery must require Sent readiness before the SMTP side effect' );
mvm_mail_stack_assert( str_contains( $delivery_source, "'delivered_unarchived'" ), 'post-SMTP archive failure must remain terminal to prevent duplicate retry' );
mvm_mail_stack_assert( str_contains( $delivery_source, "'sentArchived'" ) && str_contains( $delivery_source, "'sentArchiveStatus'" ), 'delivery result must expose Sent archive status without exposing MIME' );

echo "OK: Mail V2 provider/sync and fail-closed Sent archive smoke passed\n";
