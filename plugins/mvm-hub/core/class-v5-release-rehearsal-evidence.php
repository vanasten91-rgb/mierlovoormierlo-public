<?php

namespace MVM\Hub\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Pure evidence evaluator for a product-like V5 staging rehearsal.
 *
 * It cannot deploy, promote, migrate or change routes. It only validates that
 * the exact code/artifact and required rehearsal evidence are present.
 */
final class V5_Release_Rehearsal_Evidence {
    /** @return list<string> */
    public static function required_boolean_evidence(): array {
        return array(
            'artifactChecksumVerified',
            'stagingE2EGreen',
            'migrationsDryRunVerified',
            'rollbackVerified',
            'queueCronOwnershipVerified',
            'observabilityReady',
            'roleUatComplete',
            'sourceLeakageTestsGreen',
            'mailLeakageTestsGreen',
            'attachmentSecurityGreen',
            'registrationRouteOwnershipResolved',
            'privateRestPermissionCallbacksVerified',
            'legacySendRouteAbsent',
        );
    }

    /**
     * @param array<string,mixed> $evidence
     * @return array{complete:bool,canRequestProductionApproval:bool,missing:list<string>,headMatches:bool,artifactValid:bool,artifactMatches:bool,productionActivated:bool,requiresExplicitApproval:bool}
     */
    public static function evaluate( array $evidence, string $expected_head_sha, string $expected_artifact_sha256 ): array {
        $expected_head_sha      = strtolower( trim( $expected_head_sha ) );
        $expected_artifact_sha  = strtolower( trim( $expected_artifact_sha256 ) );
        $actual_head_sha        = strtolower( trim( (string) ( $evidence['exactHeadSha'] ?? '' ) ) );
        $artifact_sha           = strtolower( trim( (string) ( $evidence['artifactSha256'] ?? '' ) ) );

        $head_valid             = 1 === preg_match( '/^[a-f0-9]{40}$/', $expected_head_sha );
        $actual_valid           = 1 === preg_match( '/^[a-f0-9]{40}$/', $actual_head_sha );
        $expected_artifact_valid= 1 === preg_match( '/^[a-f0-9]{64}$/', $expected_artifact_sha );
        $artifact_valid         = 1 === preg_match( '/^[a-f0-9]{64}$/', $artifact_sha );
        $head_matches           = $head_valid && $actual_valid && hash_equals( $expected_head_sha, $actual_head_sha );
        $artifact_matches       = $expected_artifact_valid && $artifact_valid && hash_equals( $expected_artifact_sha, $artifact_sha );

        $missing = array();
        if ( ! $head_matches ) {
            $missing[] = 'exactHeadSha';
        }
        if ( ! $artifact_valid ) {
            $missing[] = 'artifactSha256';
        } elseif ( ! $artifact_matches ) {
            $missing[] = 'artifactSha256Mismatch';
        }
        if ( '' === trim( (string) ( $evidence['stagingSnapshotId'] ?? '' ) ) ) {
            $missing[] = 'stagingSnapshotId';
        }
        if ( '' === trim( (string) ( $evidence['rollbackEvidenceId'] ?? '' ) ) ) {
            $missing[] = 'rollbackEvidenceId';
        }

        foreach ( self::required_boolean_evidence() as $key ) {
            if ( true !== ( $evidence[ $key ] ?? false ) ) {
                $missing[] = $key;
            }
        }

        $missing = array_values( array_unique( $missing ) );

        return array(
            'complete' => array() === $missing,
            'canRequestProductionApproval' => array() === $missing,
            'missing' => $missing,
            'headMatches' => $head_matches,
            'artifactValid' => $artifact_valid,
            'artifactMatches' => $artifact_matches,
            'productionActivated' => false,
            'requiresExplicitApproval' => true,
        );
    }

    private function __construct() {}
}
