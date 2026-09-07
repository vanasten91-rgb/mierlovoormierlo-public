<?php

define( 'ABSPATH', __DIR__ . '/' );

require_once __DIR__ . '/../plugins/mvm-hub/modules/communications/class-v5-mail-folder-list-model.php';
require_once __DIR__ . '/../plugins/mvm-hub/modules/communications/class-v5-mail-message-action-policy.php';

use MVM\Hub\Modules\Communications\V5_Mail_Folder_List_Model;
use MVM\Hub\Modules\Communications\V5_Mail_Message_Action_Policy;

function mvm_v5_mail_read_assert( bool $condition, string $message ): void {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
}

$folders = V5_Mail_Folder_List_Model::build(
    'personal_42',
    array(
        array( 'folderId' => 'archive', 'label' => '<b>Archief</b>', 'role' => 'archive', 'unread' => 2, 'total' => 12 ),
        array( 'folderId' => 'INBOX', 'label' => 'Inbox', 'role' => 'inbox', 'unread' => 7, 'total' => 30 ),
        array( 'folderId' => 'sent', 'label' => 'Verzonden', 'role' => 'sent', 'unread' => 0, 'total' => 18 ),
        array( 'folderId' => '../bad', 'label' => 'Niet geldig', 'role' => 'custom' ),
    ),
    true,
    true
);

mvm_v5_mail_read_assert( 3 === $folders['count'], 'invalid folder identity must fail closed' );
mvm_v5_mail_read_assert( 'INBOX' === $folders['folders'][0]['folderId'], 'Inbox must sort first' );
mvm_v5_mail_read_assert( 'Archief' === $folders['folders'][2]['label'], 'folder label must strip markup' );
mvm_v5_mail_read_assert( false === $folders['folders'][0]['currentActions']['rename'], 'current read-only production must not rename folders' );
mvm_v5_mail_read_assert( false === $folders['folders'][0]['targetActions']['delete'], 'system Inbox must never become deletable' );
mvm_v5_mail_read_assert( true === $folders['folders'][2]['targetActions']['rename'], 'custom/archive folder may become target-mutable with explicit grant' );
mvm_v5_mail_read_assert( false === $folders['productionWritesEnabled'], 'folder preview must not enable production writes' );
mvm_v5_mail_read_assert( 'none' === $folders['security']['searchVisibility'], 'folder projection must stay outside shared search' );

$denied = V5_Mail_Folder_List_Model::build( 'personal_42', array( array( 'folderId' => 'INBOX' ) ), false, true );
mvm_v5_mail_read_assert( 0 === $denied['count'], 'folder list must fail closed without read grant' );

$actions = V5_Mail_Message_Action_Policy::evaluate(
    array(
        'canRead' => true,
        'canSend' => true,
    ),
    array(
        'readyForSend' => true,
        'providerCapabilities' => array(
            'folderScopedRead' => true,
            'folderScopedMutations' => true,
            'attachmentFetch' => true,
        ),
    )
);

mvm_v5_mail_read_assert( true === $actions['currentActions']['open'], 'authorized message may be opened in current read-only mode' );
mvm_v5_mail_read_assert( false === $actions['currentActions']['markRead'], 'current production must not expose state mutation' );
mvm_v5_mail_read_assert( false === $actions['currentActions']['reply'], 'current production must not expose reply/send' );
mvm_v5_mail_read_assert( true === $actions['targetActions']['downloadAttachment'], 'target attachment read requires explicit provider fetch readiness' );
mvm_v5_mail_read_assert( true === $actions['targetActions']['move'], 'target move requires folder-scoped mutation readiness' );
mvm_v5_mail_read_assert( true === $actions['targetActions']['reply'], 'target reply requires read+send+folder-scoped read+send readiness' );
mvm_v5_mail_read_assert( true === $actions['sendPolicy']['requiresStepUp'], 'target send must remain step-up gated' );
mvm_v5_mail_read_assert( false === $actions['productionWritesEnabled'], 'message action policy must not enable production writes' );

$read_only_target = V5_Mail_Message_Action_Policy::evaluate(
    array( 'canRead' => true, 'canSend' => false ),
    array(
        'readyForSend' => true,
        'providerCapabilities' => array(
            'folderScopedRead' => true,
            'folderScopedMutations' => false,
            'attachmentFetch' => true,
        ),
    )
);
mvm_v5_mail_read_assert( false === $read_only_target['targetActions']['reply'], 'read grant must never imply target send' );
mvm_v5_mail_read_assert( false === $read_only_target['targetActions']['move'], 'provider without mutation readiness must not expose target move' );

echo "PASS: MvM Hub V5 Mail folders and message actions remain read-only in production and explicit in target readiness\n";
