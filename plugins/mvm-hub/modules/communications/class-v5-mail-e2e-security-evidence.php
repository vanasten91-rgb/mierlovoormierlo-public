<?php

namespace MVM\Hub\Modules\Communications;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Pure fail-closed evaluator for Mail V2 product-like staging E2E evidence.
 *
 * This class cannot send, sync, mutate mail, promote routes or activate production.
 * It only validates that security evidence is bound to one exact release candidate.
 * Transport claims must explicitly prove test-mailbox-only execution, real inbound
 * and outbound delivery, and isolation between two distinct provider accounts.
 */
final class V5_Mail_E2E_Security_Evidence {
    /** @return list<string> */
    public static function required_boolean_evidence(): array {
        return array(
            'receiveSyncGreen',
            'sendGreen',
            'replyGreen',
            'replyAllGreen',
            'forwardGreen',
            'draftsGreen',
            'foldersGreen',
            'messageActionsGreen',
            'mailboxAclPositiveGreen',
            'mailboxAclNegativeGreen',
            'crossUserIdorBlocked',
            'crossMailboxIsolationGreen',
            'providerAccountIsolationGreen',
            'stepUpMfaGreen',
            'attachmentReadAclGreen',
            'maliciousAttachmentRejected',
            'malwareScanGreen',
            'uploadPolicyGreen',
            'searchLeakageGreen',
            'aiLeakageGreen',
            'sharedProjectionLeakageGreen',
            'rateLimitGreen',
            'abuseControlsGreen',
            'metadataOnlyAuditGreen',
            'replyTargetIntegrityGreen',
            'providerAssociationIntegrityGreen',
            'rollbackGreen',
            'testMailboxOnly',
            'externalDeliveryPerformed',
            'inboundDeliveryObserved',
            'outboundDeliveryObserved',
            'providerAccountsDistinct',
        );
    }

    /**
     * @param array<string,mixed> $evidence
     * @return array{complete:bool,canRequestMailCutover:bool,missing:list<string>,headMatches:bool,artifactValid:bool,artifactMatches:bool,stagingBound:bool,transportEvidenceBound:bool,providerIsolationBound:bool,requiresSeparateApproval:bool,productionActivated:bool}
     */
    public static function evaluate( array $evidence, string $expected_head_sha, string $expected_artifact_sha256 ): array {
        $expected_head_sha        = strtolower( trim( $expected_head_sha ) );
        $expected_artifact_sha    = strtolower( trim( $expected_artifact_sha256 ) );
        $actual_head_sha          = strtolower( trim( (string) ( $evidence['exactHeadSha'] ?? '' ) ) );
        $artifact_sha             = strtolower( trim( (string) ( $evidence['artifactSha256'] ?? '' ) ) );
        $environment_type         = trim( (string) ( $evidence['environmentType'] ?? '' ) );
        $staging_snapshot_id      = trim( (string) ( $evidence['stagingSnapshotId'] ?? '' ) );
        $mail_e2e_evidence_id     = trim( (string) ( $evidence['mailE2EEvidenceId'] ?? '' ) );
        $rollback_evidence_id     = trim( (string) ( $evidence['rollbackEvidenceId'] ?? '' ) );
        $transport_evidence_id    = trim( (string) ( $evidence['transportEvidenceId'] ?? '' ) );
        $provider_isolation_id    = trim( (string) ( $evidence['providerIsolationEvidenceId'] ?? '' ) );
        $primary_provider_ref     = trim( (string) ( $evidence['primaryProviderAccountRef'] ?? '' ) );
        $secondary_provider_ref   = trim( (string) ( $evidence['secondaryProviderAccountRef'] ?? '' ) );
        $executed_at              = trim( (string) ( $evidence['executedAt'] ?? '' ) );

        $head_valid               = 1 === preg_match( '/^[a-f0-9]{40}$/', $expected_head_sha );
        $actual_valid             = 1 === preg_match( '/^[a-f0-9]{40}$/', $actual_head_sha );
        $expected_artifact_valid  = 1 === preg_match( '/^[a-f0-9]{64}$/', $expected_artifact_sha );
        $artifact_valid           = 1 === preg_match( '/^[a-f0-9]{64}$/', $artifact_sha );
        $head_matches             = $head_valid && $actual_valid && hash_equals( $expected_head_sha, $actual_head_sha );
        $artifact_matches         = $expected_artifact_valid && $artifact_valid && hash_equals( $expected_artifact_sha, $artifact_sha );
        $staging_bound            = 'product_like_staging' === $environment_type && '' !== $staging_snapshot_id;
        $transport_evidence_bound = '' !== $transport_evidence_id;
        $provider_isolation_bound = '' !== $provider_isolation_id
            && '' !== $primary_provider_ref
            && '' !== $secondary_provider_ref
            && ! hash_equals( $primary_provider_ref, $secondary_provider_ref );

        $missing = array();

        if ( ! $head_matches ) {
            $missing[] = 'exactHeadSha';
        }
        if ( ! $artifact_valid ) {
            $missing[] = 'artifactSha256';
        } elseif ( ! $artifact_matches ) {
            $missing[] = 'artifactSha256Mismatch';
        }
        if ( 'product_like_staging' !== $environment_type ) {
            $missing[] = 'environmentType';
        }
        if ( '' === $staging_snapshot_id ) {
            $missing[] = 'stagingSnapshotId';
        }
        if ( '' === $mail_e2e_evidence_id ) {
            $missing[] = 'mailE2EEvidenceId';
        }
        if ( '' === $rollback_evidence_id ) {
            $missing[] = 'rollbackEvidenceId';
        }
        if ( ! $transport_evidence_bound ) {
            $missing[] = 'transportEvidenceId';
        }
        if ( '' === $provider_isolation_id ) {
            $missing[] = 'providerIsolationEvidenceId';
        }
        if ( '' === $primary_provider_ref ) {
            $missing[] = 'primaryProviderAccountRef';
        }
        if ( '' === $secondary_provider_ref ) {
            $missing[] = 'secondaryProviderAccountRef';
        }
        if ( '' !== $primary_provider_ref && '' !== $secondary_provider_ref && hash_equals( $primary_provider_ref, $secondary_provider_ref ) ) {
            $missing[] = 'providerAccountRefsNotDistinct';
        }
        if ( 1 !== preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $executed_at ) ) {
            $missing[] = 'executedAt';
        }

        foreach ( self::required_boolean_evidence() as $key ) {
            if ( true !== ( $evidence[ $key ] ?? false ) ) {
                $missing[] = $key;
            }
        }

        $missing = array_values( array_unique( $missing ) );

        return array(
            'complete'                  => array() === $missing,
            'canRequestMailCutover'      => array() === $missing,
            'missing'                   => $missing,
            'headMatches'               => $head_matches,
            'artifactValid'             => $artifact_valid,
            'artifactMatches'           => $artifact_matches,
            'stagingBound'              => $staging_bound,
            'transportEvidenceBound'    => $transport_evidence_bound,
            'providerIsolationBound'    => $provider_isolation_bound,
            'requiresSeparateApproval'  => true,
            'productionActivated'       => false,
        );
    }

    private function __construct() {}
}
