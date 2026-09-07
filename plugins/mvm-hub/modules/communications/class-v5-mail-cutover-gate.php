<?php

namespace MVM\Hub\Modules\Communications;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Pure release gate for the eventual Mail V2 receive + send cutover.
 *
 * It performs no activation and owns no route. A true decision only means the
 * declared release evidence is complete enough to request a separately
 * approved production promotion.
 */
final class V5_Mail_Cutover_Gate {
    /**
     * @param array<string,mixed> $provider_profile Output of V5_Mail_Provider_Profile::evaluate().
     * @param array<string,bool> $signals
     * @return array<string,mixed>
     */
    public static function evaluate( array $provider_profile, array $signals ): array {
        $required = array(
            'explicitApproval',
            'productionBaselineCaptured',
            'rollbackReady',
            'mailboxAclReady',
            'mfaReady',
            'stepUpReady',
            'providerCredentialsProtected',
            'oauthOrEncryptedCredentialReady',
            'privateAttachmentStoreReady',
            'malwareScannerReady',
            'idempotencyReady',
            'rateLimitReady',
            'auditMetadataOnly',
            'legacySendRouteAbsent',
            'syncCheckpointReady',
            'providerHealthGreen',
            'inboundTested',
            'outboundTested',
        );

        $missing = array();
        foreach ( $required as $signal ) {
            if ( true !== ( $signals[ $signal ] ?? false ) ) {
                $missing[] = $signal;
            }
        }

        if ( true !== ( $provider_profile['readyForReceive'] ?? false ) ) {
            $missing[] = 'providerReceiveReady';
        }
        if ( true !== ( $provider_profile['readyForSend'] ?? false ) ) {
            $missing[] = 'providerSendReady';
        }
        if ( true !== ( $provider_profile['readyForBidirectional'] ?? false ) ) {
            $missing[] = 'providerBidirectionalReady';
        }

        $missing = array_values( array_unique( $missing ) );

        return array(
            'canPromoteReceiveSync'  => array() === $missing,
            'canPromoteSendRoute'    => array() === $missing,
            'canPromoteBidirectional'=> array() === $missing,
            'missing'                => $missing,
            'currentProductionMode'  => 'read_only',
            'targetMode'             => 'bidirectional',
            'requiresSeparateApproval'=> true,
        );
    }

    private function __construct() {}
}
