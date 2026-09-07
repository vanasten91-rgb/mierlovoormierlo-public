<?php

define( 'ABSPATH', __DIR__ . '/' );

require_once __DIR__ . '/../plugins/mvm-hub/modules/communications/class-v5-mail-message-reference.php';
require_once __DIR__ . '/../plugins/mvm-hub/modules/communications/class-v5-mail-provider-profile.php';
require_once __DIR__ . '/../plugins/mvm-hub/modules/communications/class-v5-mail-sync-contract.php';
require_once __DIR__ . '/../plugins/mvm-hub/modules/communications/class-v5-mailbox-access-profile.php';
require_once __DIR__ . '/../plugins/mvm-hub/modules/communications/class-v5-mail-cutover-gate.php';
require_once __DIR__ . '/../plugins/mvm-hub/modules/communications/class-v5-mail-bidirectional-contract.php';
require_once __DIR__ . '/../plugins/mvm-hub/modules/communications/class-v5-mail-workspace-model.php';
require_once __DIR__ . '/../plugins/mvm-hub/modules/communications/class-v5-mail-message-list-model.php';
require_once __DIR__ . '/../plugins/mvm-hub/modules/communications/class-v5-mail-message-detail-model.php';

use MVM\Hub\Modules\Communications\V5_Mail_Bidirectional_Contract;
use MVM\Hub\Modules\Communications\V5_Mail_Cutover_Gate;
use MVM\Hub\Modules\Communications\V5_Mail_Message_Detail_Model;
use MVM\Hub\Modules\Communications\V5_Mail_Message_List_Model;
use MVM\Hub\Modules\Communications\V5_Mail_Message_Reference;
use MVM\Hub\Modules\Communications\V5_Mail_Provider_Profile;
use MVM\Hub\Modules\Communications\V5_Mail_Sync_Contract;
use MVM\Hub\Modules\Communications\V5_Mail_Workspace_Model;
use MVM\Hub\Modules\Communications\V5_Mailbox_Access_Profile;

function mvm_v5_mail_assert( bool $condition, string $message ): void {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
}

$ref = V5_Mail_Message_Reference::normalize( 'personal_42', 'INBOX', '1234' );
mvm_v5_mail_assert( is_array( $ref ), 'valid folder-scoped reference should normalize' );
mvm_v5_mail_assert( 64 === strlen( (string) $ref['key'] ), 'reference key should be a sha256 digest' );
mvm_v5_mail_assert( null === V5_Mail_Message_Reference::normalize( 'personal_42', 'INBOX', '../1234' ), 'path-like message ids must fail closed' );

$provider_caps = array(
    'read'                  => true,
    'folders'               => true,
    'attachments'           => true,
    'flags'                 => true,
    'move'                  => true,
    'folderScopedRead'      => true,
    'folderScopedMutations' => true,
    'attachmentFetch'       => true,
    'send'                  => true,
);
$sync_caps = array(
    'initial'     => true,
    'delta'       => true,
    'deletions'   => true,
    'cursorReset' => true,
);

$profile = V5_Mail_Provider_Profile::evaluate( $provider_caps, $sync_caps );
mvm_v5_mail_assert( true === $profile['readyForReceive'], 'provider should be receive-ready with folder-scoped mailbox + sync capabilities' );
mvm_v5_mail_assert( true === $profile['readyForSend'], 'provider should be send-ready' );
mvm_v5_mail_assert( true === $profile['readyForBidirectional'], 'provider should be bidirectional-ready' );

$legacy_like = V5_Mail_Provider_Profile::evaluate(
    array(
        'read'        => true,
        'folders'     => true,
        'attachments' => true,
        'flags'       => true,
        'move'        => true,
        'send'        => true,
    ),
    $sync_caps
);
mvm_v5_mail_assert( false === $legacy_like['readyForReceive'], 'legacy mailbox provider without folder-scoped operations must not be considered receive-ready' );
mvm_v5_mail_assert( in_array( 'folderScopedRead', $legacy_like['missingReceive'], true ), 'legacy provider must explain missing folder-scoped read' );
mvm_v5_mail_assert( in_array( 'folderScopedMutations', $legacy_like['missingReceive'], true ), 'legacy provider must explain missing folder-scoped mutations' );
mvm_v5_mail_assert( in_array( 'attachmentFetch', $legacy_like['missingReceive'], true ), 'legacy provider must explain missing attachment fetch' );

$without_send = V5_Mail_Provider_Profile::evaluate( array_replace( $provider_caps, array( 'send' => false ) ), $sync_caps );
mvm_v5_mail_assert( false === $without_send['readyForBidirectional'], 'read-only provider must not be considered Mail V2 ready' );

$batch = V5_Mail_Sync_Contract::normalize_batch(
    array(
        'cursor' => 'uidv:123:456',
        'upserts' => array(
            array(
                'mailboxId' => 'personal_42',
                'folderId'  => 'INBOX',
                'messageId' => '1234',
                'revision'  => 'modseq-99',
                'seen'      => true,
                'flagged'   => false,
                'size'      => 4812,
                'subject'   => 'must not leak',
                'body'      => 'must not leak',
                'from'      => 'must not leak',
            ),
        ),
        'deleted' => array(
            array(
                'mailboxId' => 'personal_42',
                'folderId'  => 'INBOX',
                'messageId' => '999',
            ),
        ),
    )
);
mvm_v5_mail_assert( 1 === count( $batch['upserts'] ), 'valid sync upsert should survive normalization' );
mvm_v5_mail_assert( ! isset( $batch['upserts'][0]['subject'] ), 'sync plane must not carry subject' );
mvm_v5_mail_assert( ! isset( $batch['upserts'][0]['body'] ), 'sync plane must not carry body' );
mvm_v5_mail_assert( ! isset( $batch['upserts'][0]['from'] ), 'sync plane must not carry addresses' );
mvm_v5_mail_assert( 'none' === $batch['searchVisibility'], 'mail sync must stay outside shared search' );
mvm_v5_mail_assert( 'none' === $batch['aiVisibility'], 'mail sync must stay outside generic AI context' );
mvm_v5_mail_assert( true === V5_Mail_Sync_Contract::valid_cursor( 'uidv:123:456' ), 'valid opaque cursor should be accepted' );
mvm_v5_mail_assert( false === V5_Mail_Sync_Contract::valid_cursor( str_repeat( 'x', 513 ) ), 'oversized cursor must fail closed' );

$personal = V5_Mailbox_Access_Profile::normalize(
    array(
        'type'          => 'personal',
        'read'          => true,
        'send'          => true,
        'sendAs'        => false,
        'manageFolders' => true,
    )
);
mvm_v5_mail_assert( true === $personal['canSend'], 'personal mailbox may send without separate send-as grant' );

$shared = V5_Mailbox_Access_Profile::normalize(
    array(
        'type'   => 'shared',
        'read'   => true,
        'send'   => true,
        'sendAs' => false,
    )
);
mvm_v5_mail_assert( false === $shared['canSend'], 'shared mailbox send must require send-as' );

$workspace = V5_Mail_Workspace_Model::build(
    array(
        array(
            'mailboxId' => 'personal_42',
            'label' => '<b>Mijn mailbox</b>',
            'unread' => 7,
            'access' => array(
                'type' => 'personal',
                'read' => true,
                'send' => true,
                'manageFolders' => true,
            ),
            'providerCapabilities' => $provider_caps,
            'syncCapabilities' => $sync_caps,
            'subject' => 'must never be projected',
            'body' => 'must never be projected',
        ),
        array(
            'mailboxId' => 'redactie_shared',
            'label' => 'Redactie',
            'unread' => 3,
            'access' => array(
                'type'   => 'shared',
                'read'   => true,
                'send'   => true,
                'sendAs' => false,
            ),
            'providerCapabilities' => $provider_caps,
            'syncCapabilities' => $sync_caps,
        ),
        array(
            'mailboxId' => 'forbidden_box',
            'label' => 'Niet zichtbaar',
            'access' => array(
                'type' => 'shared',
                'read' => false,
                'send' => false,
            ),
        ),
    )
);
mvm_v5_mail_assert( 2 === $workspace['summary']['mailboxCount'], 'workspace must exclude mailbox descriptors without read/send grants' );
mvm_v5_mail_assert( 10 === $workspace['summary']['unread'], 'workspace should aggregate bounded unread counts' );
mvm_v5_mail_assert( true === $workspace['mailboxes'][0]['currentActions']['openInbox'], 'authorized personal mailbox should expose current read action' );
mvm_v5_mail_assert( false === $workspace['mailboxes'][0]['currentActions']['receiveSync'], 'current production must not expose V5 receive-sync mutation ownership' );
mvm_v5_mail_assert( false === $workspace['mailboxes'][0]['currentActions']['compose'], 'current production must keep compose disabled' );
mvm_v5_mail_assert( true === $workspace['mailboxes'][0]['targetActions']['receiveSync'], 'ready personal mailbox should be target receive-sync ready' );
mvm_v5_mail_assert( true === $workspace['mailboxes'][0]['targetActions']['compose'], 'ready personal mailbox should be target compose ready' );
mvm_v5_mail_assert( false === $workspace['mailboxes'][0]['actions']['compose'], 'compatibility action alias must reflect current read-only production state' );
mvm_v5_mail_assert( 'Mijn mailbox' === $workspace['mailboxes'][0]['label'], 'mailbox label should be stripped of markup' );
mvm_v5_mail_assert( false === $workspace['mailboxes'][1]['targetActions']['compose'], 'shared mailbox without send-as must not become target compose-ready' );
mvm_v5_mail_assert( ! isset( $workspace['mailboxes'][0]['subject'] ), 'workspace must not project message subject' );
mvm_v5_mail_assert( ! isset( $workspace['mailboxes'][0]['body'] ), 'workspace must not project message body' );
mvm_v5_mail_assert( false === $workspace['productionWritesEnabled'], 'workspace preview must not activate production writes' );
mvm_v5_mail_assert( 'read_only' === $workspace['currentProductionMode'], 'workspace preview must preserve live read-only baseline' );
mvm_v5_mail_assert( 'none' === $workspace['security']['searchVisibility'], 'workspace must stay outside shared search' );
mvm_v5_mail_assert( 'none' === $workspace['security']['aiVisibility'], 'workspace must stay outside generic AI context' );

$list = V5_Mail_Message_List_Model::build(
    'personal_42',
    'INBOX',
    array(
        array(
            'id' => '1234',
            'subject' => '<b>Nieuws</b>',
            'from' => "Redactie\nMierlo",
            'date' => '2026-09-04T09:00:00Z',
            'seen' => false,
            'flagged' => true,
            'size' => 4812,
            'attachments' => array( array( 'id' => '1' ) ),
            'body' => 'list projection must not expose this',
        ),
    )
);
mvm_v5_mail_assert( 1 === $list['count'], 'authorized message list should project valid folder-scoped rows' );
mvm_v5_mail_assert( 'Nieuws' === $list['messages'][0]['subject'], 'message-list subject should be plain text' );
mvm_v5_mail_assert( 'INBOX' === $list['messages'][0]['reference']['folderId'], 'message-list reference must preserve folder scope' );
mvm_v5_mail_assert( 1 === $list['messages'][0]['attachmentCount'], 'message-list should expose bounded attachment count only' );
mvm_v5_mail_assert( ! isset( $list['messages'][0]['body'] ), 'message-list must not expose body content' );
mvm_v5_mail_assert( 'none' === $list['security']['searchVisibility'], 'message-list must stay outside shared search' );

$detail = V5_Mail_Message_Detail_Model::build(
    array(
        'mailboxId' => 'personal_42',
        'folderId' => 'INBOX',
        'id' => '1234',
        'subject' => '<b>Welkom</b>',
        'from' => 'Redactie <redactie@example.test>',
        'fromAddress' => 'redactie@example.test',
        'to' => array( 'user@example.test', 'not-an-address' ),
        'cc' => array( 'cc@example.test' ),
        'date' => '2026-09-04T09:00:00Z',
        'seen' => true,
        'flagged' => false,
        'text' => "Hallo\0 wereld",
        'html' => '<p>Hallo</p><script>alert(1)</script>',
        'attachments' => array(
            array(
                'id' => 'part-1',
                'name' => '<b>foto.jpg</b>',
                'mime' => 'image/jpeg',
                'size' => 1024,
                'path' => '/private/secret/foto.jpg',
                'url' => 'https://example.test/private-file',
            ),
        ),
        'rfcMessageId' => '<message-123@example.test>',
        'inReplyTo' => '<parent@example.test>',
        'references' => array( '<root@example.test>', '<parent@example.test>' ),
    ),
    static function ( string $html ): string {
        return preg_replace( '#<script\b[^>]*>.*?</script>#is', '', $html ) ?? '';
    }
);
mvm_v5_mail_assert( is_array( $detail ), 'authorized message detail should project' );
mvm_v5_mail_assert( 'Welkom' === $detail['subject'], 'detail subject should be plain text' );
mvm_v5_mail_assert( false === str_contains( $detail['html'], '<script' ), 'trusted sanitizer output should be used' );
mvm_v5_mail_assert( false === $detail['contentPolicy']['remoteImagesAllowed'], 'remote images must remain disabled by default' );
mvm_v5_mail_assert( false === $detail['contentPolicy']['externalContentAutoLoad'], 'external content must not auto-load' );
mvm_v5_mail_assert( 1 === count( $detail['attachments'] ), 'authorized attachment metadata should project' );
mvm_v5_mail_assert( ! isset( $detail['attachments'][0]['path'] ), 'attachment filesystem paths must not project' );
mvm_v5_mail_assert( ! isset( $detail['attachments'][0]['url'] ), 'direct attachment URLs must not project' );
mvm_v5_mail_assert( array( 'user@example.test' ) === $detail['to'], 'invalid recipient metadata must be dropped' );
mvm_v5_mail_assert( 'none' === $detail['security']['aiVisibility'], 'message detail must stay outside generic AI context' );

$signals = array(
    'explicitApproval'                 => true,
    'productionBaselineCaptured'       => true,
    'rollbackReady'                    => true,
    'mailboxAclReady'                  => true,
    'mfaReady'                         => true,
    'stepUpReady'                      => true,
    'providerCredentialsProtected'     => true,
    'oauthOrEncryptedCredentialReady'  => true,
    'privateAttachmentStoreReady'      => true,
    'malwareScannerReady'              => true,
    'idempotencyReady'                 => true,
    'rateLimitReady'                   => true,
    'auditMetadataOnly'                => true,
    'legacySendRouteAbsent'            => true,
    'syncCheckpointReady'              => true,
    'providerHealthGreen'              => true,
    'inboundTested'                    => true,
    'outboundTested'                   => true,
);

$gate = V5_Mail_Cutover_Gate::evaluate( $profile, $signals );
mvm_v5_mail_assert( true === $gate['canPromoteBidirectional'], 'complete evidence should make the cutover gate eligible' );
mvm_v5_mail_assert( 'read_only' === $gate['currentProductionMode'], 'gate must preserve current live read-only baseline' );

$signals['explicitApproval'] = false;
$blocked = V5_Mail_Cutover_Gate::evaluate( $profile, $signals );
mvm_v5_mail_assert( false === $blocked['canPromoteBidirectional'], 'production promotion must fail without separate approval' );
mvm_v5_mail_assert( in_array( 'explicitApproval', $blocked['missing'], true ), 'missing approval must remain explainable' );

$definition = V5_Mail_Bidirectional_Contract::definition();
mvm_v5_mail_assert( 'bidirectional' === $definition['mode'], 'Mail V2 target must stay bidirectional' );
mvm_v5_mail_assert( true === $definition['inbound']['required'], 'receive must remain required' );
mvm_v5_mail_assert( true === $definition['inbound']['folderScopedIdentity'], 'folder-scoped message identity must remain required' );
mvm_v5_mail_assert( true === $definition['outbound']['required'], 'send must remain required' );
mvm_v5_mail_assert( true === V5_Mail_Bidirectional_Contract::provider_ready( $profile['providerCapabilities'] ), 'full folder-scoped provider capabilities should satisfy provider readiness' );
mvm_v5_mail_assert( true === V5_Mail_Bidirectional_Contract::sync_ready( $profile['syncCapabilities'] ), 'full sync capabilities should satisfy sync readiness' );
mvm_v5_mail_assert( 'read_only' === $definition['promotion']['currentProductionMode'], 'current production gate must remain read-only until cutover' );

echo "PASS: MvM Hub V5 Mail V2 remains bidirectional, folder-scoped, privacy-preserving, UI-safe and cutover-gated\n";
