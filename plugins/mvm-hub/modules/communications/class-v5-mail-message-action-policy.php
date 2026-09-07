<?php

namespace MVM\Hub\Modules\Communications;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Dormant UI policy for Mail V2 message actions.
 *
 * The class separates current live read-only behavior from future target
 * readiness. It never performs authorization itself; callers must pass already
 * resolved mailbox grants and provider readiness.
 */
final class V5_Mail_Message_Action_Policy {
    /**
     * @param array<string,mixed> $access
     * @param array<string,mixed> $provider
     * @return array<string,mixed>
     */
    public static function evaluate( array $access, array $provider ): array {
        $can_read = true === ( $access['canRead'] ?? false );
        $can_send = true === ( $access['canSend'] ?? false );

        $folder_scoped_read = true === ( $provider['providerCapabilities']['folderScopedRead'] ?? false );
        $folder_mutations   = true === ( $provider['providerCapabilities']['folderScopedMutations'] ?? false );
        $attachment_fetch   = true === ( $provider['providerCapabilities']['attachmentFetch'] ?? false );
        $ready_send         = true === ( $provider['readyForSend'] ?? false );

        $current = array(
            'open'               => $can_read,
            'downloadAttachment' => false,
            'markRead'           => false,
            'flag'               => false,
            'move'               => false,
            'reply'              => false,
            'replyAll'           => false,
            'forward'            => false,
        );

        $target = array(
            'open'               => $can_read && $folder_scoped_read,
            'downloadAttachment' => $can_read && $folder_scoped_read && $attachment_fetch,
            'markRead'           => $can_read && $folder_mutations,
            'flag'               => $can_read && $folder_mutations,
            'move'               => $can_read && $folder_mutations,
            'reply'              => $can_read && $can_send && $ready_send && $folder_scoped_read,
            'replyAll'           => $can_read && $can_send && $ready_send && $folder_scoped_read,
            'forward'            => $can_read && $can_send && $ready_send && $folder_scoped_read,
        );

        return array(
            'currentProductionMode' => 'read_only',
            'productionWritesEnabled' => false,
            'currentActions' => $current,
            'targetActions'  => $target,
            'sendPolicy' => array(
                'requiresStepUp'      => true,
                'requiresIdempotency' => true,
                'requiresRateLimit'   => true,
                'requiresCutover'     => true,
            ),
            'security' => array(
                'searchVisibility' => 'none',
                'aiVisibility'     => 'none',
            ),
        );
    }

    private function __construct() {}
}
