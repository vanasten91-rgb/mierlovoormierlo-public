<?php

namespace MVM\Hub\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Pure exact-head CI evidence gate for Hub V5 release preparation.
 *
 * It binds one successful GitHub Actions run to the exact release head and
 * verified artifact checksum. A run without executed job steps is never valid
 * evidence, which prevents runner/pre-checkout failures from being mistaken for
 * a green release signal.
 */
final class V5_Exact_Head_CI_Evidence {
    /**
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public static function evaluate( array $input, string $expected_head_sha, string $expected_artifact_sha256 ): array {
        $expected_head     = self::sha40( $expected_head_sha );
        $expected_artifact = self::sha64( $expected_artifact_sha256 );
        $head              = self::sha40( (string) ( $input['headSha'] ?? '' ) );
        $artifact          = self::sha64( (string) ( $input['artifactSha256'] ?? '' ) );
        $workflow          = self::text( (string) ( $input['workflowName'] ?? '' ), 120 );
        $status            = strtolower( trim( (string) ( $input['status'] ?? '' ) ) );
        $conclusion        = strtolower( trim( (string) ( $input['conclusion'] ?? '' ) ) );
        $run_id            = max( 0, (int) ( $input['runId'] ?? 0 ) );
        $run_number        = max( 0, (int) ( $input['runNumber'] ?? 0 ) );
        $job_id            = max( 0, (int) ( $input['jobId'] ?? 0 ) );
        $steps_present     = true === ( $input['stepsPresent'] ?? false );
        $tests_executed    = true === ( $input['testsExecuted'] ?? false );
        $artifact_uploaded = true === ( $input['artifactUploaded'] ?? false );
        $artifact_verified = true === ( $input['artifactVerified'] ?? false );

        $blockers = array();

        if ( '' === $expected_head ) {
            $blockers[] = 'expected_head_missing';
        }
        if ( '' === $expected_artifact ) {
            $blockers[] = 'expected_artifact_missing';
        }
        if ( $run_id < 1 ) {
            $blockers[] = 'ci_run_id_missing';
        }
        if ( $run_number < 1 ) {
            $blockers[] = 'ci_run_number_missing';
        }
        if ( $job_id < 1 ) {
            $blockers[] = 'ci_job_id_missing';
        }
        if ( 'MvM Hub Newsroom' !== $workflow ) {
            $blockers[] = 'ci_workflow_mismatch';
        }
        if ( 'completed' !== $status ) {
            $blockers[] = 'ci_not_completed';
        }
        if ( 'success' !== $conclusion ) {
            $blockers[] = 'ci_not_successful';
        }
        if ( ! $steps_present ) {
            $blockers[] = 'ci_job_steps_missing';
        }
        if ( ! $tests_executed ) {
            $blockers[] = 'ci_tests_not_executed';
        }
        if ( ! $artifact_uploaded ) {
            $blockers[] = 'ci_artifact_not_uploaded';
        }
        if ( ! $artifact_verified ) {
            $blockers[] = 'ci_artifact_not_verified';
        }
        if ( '' === $head || '' === $expected_head || ! hash_equals( $expected_head, $head ) ) {
            $blockers[] = 'ci_head_sha_mismatch';
        }
        if ( '' === $artifact || '' === $expected_artifact || ! hash_equals( $expected_artifact, $artifact ) ) {
            $blockers[] = 'ci_artifact_sha_mismatch';
        }

        $blockers = array_values( array_unique( $blockers ) );
        $ready    = array() === $blockers;

        return array(
            'ciReady'                       => $ready,
            'canRequestReleaseCandidate'    => $ready,
            'canPromoteProduction'          => false,
            'requiresExplicitApproval'      => true,
            'runId'                         => $run_id,
            'runNumber'                     => $run_number,
            'jobId'                         => $job_id,
            'workflowName'                  => $workflow,
            'status'                        => $status,
            'conclusion'                    => $conclusion,
            'stepsPresent'                  => $steps_present,
            'testsExecuted'                 => $tests_executed,
            'artifactUploaded'              => $artifact_uploaded,
            'artifactVerified'              => $artifact_verified,
            'headSha'                       => $head,
            'artifactSha256'                => $artifact,
            'blockers'                      => $blockers,
        );
    }

    private static function sha40( string $value ): string {
        $value = strtolower( trim( $value ) );
        return 1 === preg_match( '/^[a-f0-9]{40}$/', $value ) ? $value : '';
    }

    private static function sha64( string $value ): string {
        $value = strtolower( trim( $value ) );
        return 1 === preg_match( '/^[a-f0-9]{64}$/', $value ) ? $value : '';
    }

    private static function text( string $value, int $max ): string {
        $value = trim( strip_tags( $value ) );
        $value = preg_replace( '/[\x00-\x1F\x7F]/u', ' ', $value ) ?? '';
        return mb_substr( preg_replace( '/\s+/', ' ', $value ) ?? '', 0, $max );
    }

    private function __construct() {}
}
