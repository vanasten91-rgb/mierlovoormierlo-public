<?php

define( 'ABSPATH', __DIR__ . '/' );

if ( ! function_exists( 'sanitize_file_name' ) ) {
    function sanitize_file_name( string $name ): string {
        return preg_replace( '/[^A-Za-z0-9._-]/', '-', basename( str_replace( '\\', '/', $name ) ) ) ?? '';
    }
}
if ( ! function_exists( 'sanitize_mime_type' ) ) {
    function sanitize_mime_type( string $mime ): string {
        return preg_replace( '/[^A-Za-z0-9.+\/-]/', '', $mime ) ?? '';
    }
}

require_once __DIR__ . '/../plugins/mvm-hub/core/class-communications-policy.php';
require_once __DIR__ . '/../plugins/mvm-hub/modules/communications/class-v5-mail-message-reference.php';
require_once __DIR__ . '/../plugins/mvm-hub/modules/communications/class-v5-mail-compose-intent.php';
require_once __DIR__ . '/../plugins/mvm-hub/modules/communications/class-v5-mail-attachment-policy.php';

use MVM\Hub\Modules\Communications\V5_Mail_Attachment_Policy;
use MVM\Hub\Modules\Communications\V5_Mail_Compose_Intent;

function mvm_v5_mail_ui_assert( bool $condition, string $message ): void {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
}

$sanitize = static function ( string $html ): string {
    return preg_replace( '#<script\b[^>]*>.*?</script>#is', '', $html ) ?? '';
};

$compose = V5_Mail_Compose_Intent::normalize(
    array(
        'mailboxId' => 'personal_42',
        'mode' => 'compose',
        'to' => array( 'editor@example.test' ),
        'cc' => array(),
        'bcc' => array(),
        'subject' => "Test\nonderwerp",
        'html' => '<p>Veilige tekst</p><script>alert(1)</script>',
        'text' => 'Veilige tekst',
        'attachmentIds' => array( 'draft-att-1' ),
    ),
    $sanitize
);
mvm_v5_mail_ui_assert( is_array( $compose ), 'valid compose intent should normalize' );
mvm_v5_mail_ui_assert( 'personal_42' === $compose['mailboxId'], 'compose must stay mailbox-scoped' );
mvm_v5_mail_ui_assert( false === $compose['senderPolicy']['clientControlled'], 'client must never control sender identity' );
mvm_v5_mail_ui_assert( true === $compose['senderPolicy']['deriveFromAuthorizedMailbox'], 'sender must derive from authorized mailbox' );
mvm_v5_mail_ui_assert( false === $compose['deliveryPolicy']['productionEligible'], 'compose intent must not enable production delivery' );
mvm_v5_mail_ui_assert( true === $compose['deliveryPolicy']['requiresStepUp'], 'external send must remain step-up gated' );
mvm_v5_mail_ui_assert( true === $compose['deliveryPolicy']['requiresIdempotency'], 'external send must require idempotency' );
mvm_v5_mail_ui_assert( true === $compose['deliveryPolicy']['requiresRateLimit'], 'external send must require rate limiting' );
mvm_v5_mail_ui_assert( false === str_contains( $compose['html'], '<script' ), 'compose HTML must pass the trusted sanitizer' );

$spoofed = V5_Mail_Compose_Intent::normalize(
    array(
        'mailboxId' => 'personal_42',
        'mode' => 'compose',
        'to' => array( 'editor@example.test' ),
        'from' => 'spoof@example.test',
        'html' => '<p>test</p>',
    ),
    $sanitize
);
mvm_v5_mail_ui_assert( null === $spoofed, 'client-supplied sender identity must fail closed' );

$reply = V5_Mail_Compose_Intent::normalize(
    array(
        'mailboxId' => 'personal_42',
        'mode' => 'reply',
        'source' => array(
            'mailboxId' => 'personal_42',
            'folderId' => 'INBOX',
            'messageId' => '1234',
        ),
        'to' => array( 'sender@example.test' ),
        'subject' => 'Re: Vraag',
        'html' => '<p>Antwoord</p>',
        'inReplyTo' => '<original@example.test>',
        'references' => array( '<root@example.test>', '<original@example.test>' ),
    ),
    $sanitize
);
mvm_v5_mail_ui_assert( is_array( $reply ), 'valid reply intent should normalize' );
mvm_v5_mail_ui_assert( 'INBOX' === $reply['source']['folderId'], 'reply source must remain folder-scoped' );

$cross_mailbox_reply = V5_Mail_Compose_Intent::normalize(
    array(
        'mailboxId' => 'personal_42',
        'mode' => 'reply',
        'source' => array(
            'mailboxId' => 'shared_redactie',
            'folderId' => 'INBOX',
            'messageId' => '1234',
        ),
        'to' => array( 'sender@example.test' ),
        'html' => '<p>Antwoord</p>',
        'inReplyTo' => '<original@example.test>',
    ),
    $sanitize
);
mvm_v5_mail_ui_assert( null === $cross_mailbox_reply, 'reply must fail closed when source mailbox differs' );

$clean_attachment = V5_Mail_Attachment_Policy::evaluate(
    array(
        'id' => 'draft-att-1',
        'name' => 'photo.jpg',
        'mime' => 'image/jpeg',
        'size' => 1024,
        'privateStorage' => true,
        'ownerAuthorized' => true,
        'draftLinked' => true,
        'contentValidationStatus' => 'verified',
        'scanStatus' => 'clean',
    ),
    array(
        'allowedMimes' => array( 'image/jpeg', 'application/pdf' ),
        'maxBytes' => 5_000_000,
    )
);
mvm_v5_mail_ui_assert( true === $clean_attachment['allowedForSend'], 'clean private authorized attachment should be target-send eligible' );
mvm_v5_mail_ui_assert( false === $clean_attachment['security']['publicUrlAllowed'], 'private attachment policy must never allow public URL ownership' );

$pending_attachment = V5_Mail_Attachment_Policy::evaluate(
    array(
        'id' => 'draft-att-2',
        'name' => 'document.pdf',
        'mime' => 'application/pdf',
        'size' => 2048,
        'privateStorage' => true,
        'ownerAuthorized' => true,
        'draftLinked' => true,
        'contentValidationStatus' => 'verified',
        'scanStatus' => 'pending',
    )
);
mvm_v5_mail_ui_assert( false === $pending_attachment['allowedForSend'], 'unscanned attachment must fail closed' );
mvm_v5_mail_ui_assert( in_array( 'malware_scan_pending', $pending_attachment['reasons'], true ), 'pending scan must remain explainable' );

$public_attachment = V5_Mail_Attachment_Policy::evaluate(
    array(
        'id' => 'draft-att-3',
        'name' => 'photo.jpg',
        'mime' => 'image/jpeg',
        'size' => 1024,
        'privateStorage' => false,
        'ownerAuthorized' => true,
        'draftLinked' => true,
        'contentValidationStatus' => 'verified',
        'scanStatus' => 'clean',
    )
);
mvm_v5_mail_ui_assert( false === $public_attachment['allowedForSend'], 'attachment outside private storage must fail closed' );
mvm_v5_mail_ui_assert( in_array( 'private_storage_required', $public_attachment['reasons'], true ), 'private-storage denial must remain explainable' );

echo "PASS: MvM Hub V5 Mail compose and attachment policies remain mailbox-scoped, private and production-gated\n";
