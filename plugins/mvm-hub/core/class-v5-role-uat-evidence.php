<?php

namespace MVM\Hub\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Pure, dormant evidence binding for Hub V5 role UAT.
 *
 * This evaluator does not inspect or mutate WordPress roles/capabilities. It
 * only verifies that scenario evidence belongs to one exact candidate and one
 * product-like staging snapshot.
 */
final class V5_Role_UAT_Evidence {
    /**
     * @param array<string,mixed> $evidence
     * @return array{complete:bool,missing:list<string>,headMatches:bool,artifactMatches:bool,scenarioComplete:bool,testedRoles:list<string>,productionActivated:bool}
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
        foreach ( array( 'stagingSnapshotId', 'uatEvidenceId', 'executedAtUtc' ) as $required_string ) {
            if ( '' === trim( (string) ( $evidence[ $required_string ] ?? '' ) ) ) {
                $missing[] = $required_string;
            }
        }
        if ( 'product_like_staging' !== (string) ( $evidence['environmentType'] ?? '' ) ) {
            $missing[] = 'environmentType';
        }

        $scenario_evidence = is_array( $evidence['scenarios'] ?? null ) ? $evidence['scenarios'] : array();
        $scenario_result   = V5_Role_UAT_Plan::evaluate( $scenario_evidence );
        if ( ! $scenario_result['complete'] ) {
            $missing[] = 'roleScenarioEvidence';
        }

        $missing = array_values( array_unique( $missing ) );

        return array(
            'complete' => array() === $missing,
            'missing' => $missing,
            'headMatches' => $head_matches,
            'artifactMatches' => $artifact_matches,
            'scenarioComplete' => $scenario_result['complete'],
            'testedRoles' => $scenario_result['testedRoles'],
            'productionActivated' => false,
        );
    }

    private function __construct() {}
}
