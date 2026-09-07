<?php

namespace MVM\Hub\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Pure evidence evaluator for the V5 Work Item expand/contract rehearsal.
 *
 * It never executes SQL, migrates data or changes production. It only binds
 * staging migration evidence to one exact release candidate.
 */
final class V5_Work_Item_Migration_Rehearsal_Evidence {
    /** @return list<string> */
    public static function required_boolean_evidence(): array {
        return array(
            'artifactChecksumVerified',
            'schemaDryRunVerified',
            'expandAppliedOnStaging',
            'idempotencyVerified',
            'postConditionsVerified',
            'legacyAssignmentOwnerUnchanged',
            'noSourceBodyCopied',
            'noMailBodyCopied',
            'noChatBodyCopied',
            'rollbackVerified',
        );
    }

    /**
     * @param array<string,mixed> $evidence
     * @return array{complete:bool,missing:list<string>,headMatches:bool,artifactMatches:bool,productionWrites:bool,requiresSeparateApproval:bool,productionActivated:bool}
     */
    public static function evaluate( array $evidence, string $expected_head_sha, string $expected_artifact_sha256 ): array {
        $expected_head     = strtolower( trim( $expected_head_sha ) );
        $expected_artifact = strtolower( trim( $expected_artifact_sha256 ) );
        $actual_head       = strtolower( trim( (string) ( $evidence['exactHeadSha'] ?? '' ) ) );
        $actual_artifact   = strtolower( trim( (string) ( $evidence['artifactSha256'] ?? '' ) ) );

        $head_matches = 1 === preg_match( '/^[a-f0-9]{40}$/', $expected_head )
            && 1 === preg_match( '/^[a-f0-9]{40}$/', $actual_head )
            && hash_equals( $expected_head, $actual_head );
        $artifact_matches = 1 === preg_match( '/^[a-f0-9]{64}$/', $expected_artifact )
            && 1 === preg_match( '/^[a-f0-9]{64}$/', $actual_artifact )
            && hash_equals( $expected_artifact, $actual_artifact );

        $missing = array();
        if ( ! $head_matches ) {
            $missing[] = 'exactHeadSha';
        }
        if ( ! $artifact_matches ) {
            $missing[] = 'artifactSha256';
        }
        foreach ( array( 'stagingSnapshotId', 'backupEvidenceId', 'dryRunEvidenceId', 'rollbackEvidenceId', 'migrationEvidenceId' ) as $required_string ) {
            if ( '' === trim( (string) ( $evidence[ $required_string ] ?? '' ) ) ) {
                $missing[] = $required_string;
            }
        }
        if ( 'product_like_staging' !== (string) ( $evidence['environmentType'] ?? '' ) ) {
            $missing[] = 'environmentType';
        }
        if ( V5_Work_Item_Migration_Plan::TARGET_SCHEMA !== (string) ( $evidence['targetSchemaVersion'] ?? '' ) ) {
            $missing[] = 'targetSchemaVersion';
        }

        foreach ( self::required_boolean_evidence() as $key ) {
            if ( true !== ( $evidence[ $key ] ?? false ) ) {
                $missing[] = $key;
            }
        }

        $missing = array_values( array_unique( $missing ) );

        return array(
            'complete' => array() === $missing,
            'missing' => $missing,
            'headMatches' => $head_matches,
            'artifactMatches' => $artifact_matches,
            'productionWrites' => false,
            'requiresSeparateApproval' => true,
            'productionActivated' => false,
        );
    }

    private function __construct() {}
}
