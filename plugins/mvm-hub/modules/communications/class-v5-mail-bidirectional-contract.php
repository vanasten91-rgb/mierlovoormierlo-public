<?php

namespace MVM\Hub\Modules\Communications;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Dormant contract for the target MvM Mail V2 behavior.
 *
 * This class declares the required bidirectional feature set only. It registers
 * no hooks/routes and performs no provider, network, database or filesystem I/O.
 */
final class V5_Mail_Bidirectional_Contract {
    /** @return array<string,mixed> */
    public static function definition(): array {
        return array(
            'mode' => 'bidirectional',
            'inbound' => array(
                'required'             => true,
                'listFolders'          => true,
                'listMessages'         => true,
                'readMessage'          => true,
                'folderScopedIdentity' => true,
                'receiveSync'          => true,
                'incrementalSync'      => true,
                'attachmentRead'       => true,
                'flags'                => true,
                'move'                 => true,
                'providerPushIdeal'    => true,
                'pollFallback'         => true,
            ),
            'outbound' => array(
                'required'        => true,
                'compose'         => true,
                'reply'           => true,
                'replyAll'        => true,
                'forward'         => true,
                'drafts'          => true,
                'attachments'     => true,
                'idempotency'     => true,
                'rateLimit'       => true,
                'stepUpForSend'   => true,
                'providerSend'    => true,
            ),
            'mailboxes' => array(
                'personal'       => true,
                'shared'         => true,
                'perUserAccess'  => true,
                'sendAsPolicy'   => true,
                'delegation'     => true,
            ),
            'provider' => array(
                'profileRequired'      => true,
                'syncBoundaryRequired' => true,
                'cutoverGateRequired'  => true,
            ),
            'security' => array(
                'oauthOrProviderApiPreferred' => true,
                'encryptedCredentialFallback' => true,
                'privateAttachments'          => true,
                'malwareScanBeforeOpenSend'   => true,
                'bodyExcludedFromAudit'       => true,
                'searchVisibility'            => 'none',
                'aiVisibility'                => 'none',
            ),
            'promotion' => array(
                'currentProductionMode' => 'read_only',
                'receiveSyncPromoted'   => false,
                'sendRoutePromoted'     => false,
                'requiresSeparateCutoverApproval' => true,
            ),
        );
    }

    /** @param array<string,bool|int|string> $provider_capabilities */
    public static function provider_ready( array $provider_capabilities ): bool {
        foreach (
            array(
                'read',
                'folders',
                'attachments',
                'flags',
                'move',
                'folderScopedRead',
                'folderScopedMutations',
                'attachmentFetch',
                'send',
            ) as $capability
        ) {
            if ( true !== ( $provider_capabilities[ $capability ] ?? false ) ) {
                return false;
            }
        }
        return true;
    }

    /** @param array<string,bool|int|string> $sync_capabilities */
    public static function sync_ready( array $sync_capabilities ): bool {
        foreach ( array( 'initial', 'delta', 'deletions', 'cursorReset' ) as $capability ) {
            if ( true !== ( $sync_capabilities[ $capability ] ?? false ) ) {
                return false;
            }
        }
        return true;
    }

    private function __construct() {}
}
