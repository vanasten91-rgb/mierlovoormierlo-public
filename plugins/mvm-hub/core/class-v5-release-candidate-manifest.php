<?php

namespace MVM\Hub\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Pure, dormant release-candidate evidence manifest.
 *
 * This class binds an exact Git head, verified artifact, exact-head CI evidence,
 * production-baseline decision, release blocker ledger and the existing
 * readiness/rehearsal decisions into one fail-closed RC decision. It never
 * deploys, merges, activates routes or mutates WordPress.
 */
final class V5_Release_Candidate_Manifest {
    /**
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public static function evaluate( array $input ): array {
        $head_sha          = self::sha40( (string) ( $input['exactHeadSha'] ?? '' ) );
        $base_sha          = self::sha40( (string) ( $input['baseMainSha'] ?? '' ) );
        $artifact_sha      = self::sha64( (string) ( $input['artifactSha256'] ?? '' ) );
        $baseline          = self::baseline( (array) ( $input['productionBaseline'] ?? array() ) );
        $baseline_decision = is_array( $input['productionBaselineDecision'] ?? null ) ? $input['productionBaselineDecision'] : array();
        $ci                = is_array( $input['ciDecision'] ?? null ) ? $input['ciDecision'] : array();
        $readiness         = is_array( $input['readinessDecision'] ?? null ) ? $input['readinessDecision'] : array();
        $rehearsal         = is_array( $input['rehearsalDecision'] ?? null ) ? $input['rehearsalDecision'] : array();
        $ledger            = is_array( $input['blockerLedgerDecision'] ?? null ) ? $input['blockerLedgerDecision'] : array();
        $blockers          = self::blockers( (array) ( $input['blockers'] ?? array() ) );

        $missing = array();
        if ( '' === $head_sha ) {
            $missing[] = 'exactHeadSha';
        }
        if ( '' === $base_sha ) {
            $missing[] = 'baseMainSha';
        }
        if ( '' === $artifact_sha ) {
            $missing[] = 'artifactSha256';
        }
        if ( ! self::baseline_complete( $baseline ) ) {
            $missing[] = 'productionBaseline';
        }
        if (
            true !== ( $baseline_decision['baselineReady'] ?? false )
            || true !== ( $baseline_decision['canRequestReleaseCandidate'] ?? false )
            || array() !== (array) ( $baseline_decision['blockers'] ?? array() )
        ) {
            $missing[] = 'productionBaselineDecision';
        }
        if ( ! self::ci_matches_candidate( $ci, $head_sha, $artifact_sha ) ) {
            $missing[] = 'exactHeadCi';
        }
        if ( true !== ( $readiness['canRequestProductionApproval'] ?? false ) ) {
            $missing[] = 'releaseReadiness';
        }
        if ( true !== ( $rehearsal['complete'] ?? false ) || true !== ( $rehearsal['canRequestProductionApproval'] ?? false ) ) {
            $missing[] = 'releaseRehearsal';
        }
        if (
            true !== ( $ledger['rcEligible'] ?? false )
            || true !== ( $ledger['canRequestProductionApproval'] ?? false )
            || array() !== (array) ( $ledger['openP0'] ?? array() )
        ) {
            $missing[] = 'blockerLedger';
        }
        if ( array() !== $blockers ) {
            $missing[] = 'p0Blockers';
        }

        $missing  = array_values( array_unique( $missing ) );
        $eligible = array() === $missing;

        return array(
            'candidateEligible'            => $eligible,
            'canRequestProductionApproval' => $eligible,
            'canPromoteProduction'         => false,
            'productionPromoted'           => false,
            'requiresExplicitApproval'     => true,
            'exactHeadSha'                 => $head_sha,
            'baseMainSha'                  => $base_sha,
            'artifactSha256'               => $artifact_sha,
            'productionBaseline'           => $baseline,
            'blockers'                     => $blockers,
            'missing'                      => $missing,
            'productionBaselineDecision'   => array(
                'baselineReady'             => true === ( $baseline_decision['baselineReady'] ?? false ),
                'canRequestReleaseCandidate'=> true === ( $baseline_decision['canRequestReleaseCandidate'] ?? false ),
                'blockers'                  => self::blockers( (array) ( $baseline_decision['blockers'] ?? array() ) ),
            ),
            'ci'                           => array(
                'ciReady'                    => true === ( $ci['ciReady'] ?? false ),
                'canRequestReleaseCandidate' => true === ( $ci['canRequestReleaseCandidate'] ?? false ),
                'runId'                      => max( 0, (int) ( $ci['runId'] ?? 0 ) ),
                'runNumber'                  => max( 0, (int) ( $ci['runNumber'] ?? 0 ) ),
                'jobId'                      => max( 0, (int) ( $ci['jobId'] ?? 0 ) ),
                'headSha'                    => self::sha40( (string) ( $ci['headSha'] ?? '' ) ),
                'artifactSha256'             => self::sha64( (string) ( $ci['artifactSha256'] ?? '' ) ),
                'stepsPresent'               => true === ( $ci['stepsPresent'] ?? false ),
                'testsExecuted'              => true === ( $ci['testsExecuted'] ?? false ),
                'artifactUploaded'           => true === ( $ci['artifactUploaded'] ?? false ),
                'artifactVerified'           => true === ( $ci['artifactVerified'] ?? false ),
            ),
            'blockerLedger'                => array(
                'rcEligible'                  => true === ( $ledger['rcEligible'] ?? false ),
                'canRequestProductionApproval'=> true === ( $ledger['canRequestProductionApproval'] ?? false ),
                'openP0'                      => self::blockers( (array) ( $ledger['openP0'] ?? array() ) ),
            ),
            'readiness'                    => array(
                'canRequestProductionApproval' => true === ( $readiness['canRequestProductionApproval'] ?? false ),
                'canPromoteProduction'         => true === ( $readiness['canPromoteProduction'] ?? false ),
            ),
            'rehearsal'                    => array(
                'complete'                     => true === ( $rehearsal['complete'] ?? false ),
                'canRequestProductionApproval' => true === ( $rehearsal['canRequestProductionApproval'] ?? false ),
            ),
        );
    }

    /** @return array<string,string> */
    private static function baseline( array $baseline ): array {
        $allowed = array(
            'wordpress',
            'php',
            'database',
            'mvmHub',
            'mvmPlatform',
            'encyclopedieNext',
            'royalMcp',
        );
        $out = array();
        foreach ( $allowed as $key ) {
            $value = trim( strip_tags( (string) ( $baseline[ $key ] ?? '' ) ) );
            $out[ $key ] = mb_substr( $value, 0, 80 );
        }
        return $out;
    }

    /** @param array<string,string> $baseline */
    private static function baseline_complete( array $baseline ): bool {
        foreach ( array( 'wordpress', 'php', 'database', 'mvmHub', 'mvmPlatform', 'encyclopedieNext', 'royalMcp' ) as $key ) {
            if ( '' === ( $baseline[ $key ] ?? '' ) ) {
                return false;
            }
        }
        return true;
    }

    /** @param array<string,mixed> $ci */
    private static function ci_matches_candidate( array $ci, string $head_sha, string $artifact_sha ): bool {
        if ( '' === $head_sha || '' === $artifact_sha ) {
            return false;
        }

        $ci_head     = self::sha40( (string) ( $ci['headSha'] ?? '' ) );
        $ci_artifact = self::sha64( (string) ( $ci['artifactSha256'] ?? '' ) );

        return true === ( $ci['ciReady'] ?? false )
            && true === ( $ci['canRequestReleaseCandidate'] ?? false )
            && (int) ( $ci['runId'] ?? 0 ) > 0
            && (int) ( $ci['jobId'] ?? 0 ) > 0
            && true === ( $ci['stepsPresent'] ?? false )
            && true === ( $ci['testsExecuted'] ?? false )
            && true === ( $ci['artifactUploaded'] ?? false )
            && true === ( $ci['artifactVerified'] ?? false )
            && '' !== $ci_head
            && '' !== $ci_artifact
            && hash_equals( $head_sha, $ci_head )
            && hash_equals( $artifact_sha, $ci_artifact );
    }

    /** @return list<string> */
    private static function blockers( array $blockers ): array {
        $out = array();
        foreach ( array_slice( $blockers, 0, 50 ) as $blocker ) {
            $blocker = strtolower( trim( (string) $blocker ) );
            $blocker = preg_replace( '/[^a-z0-9._:-]+/', '-', $blocker ) ?? '';
            $blocker = trim( $blocker, '-' );
            if ( '' !== $blocker ) {
                $out[] = mb_substr( $blocker, 0, 120 );
            }
        }
        return array_values( array_unique( $out ) );
    }

    private static function sha40( string $value ): string {
        $value = strtolower( trim( $value ) );
        return 1 === preg_match( '/^[a-f0-9]{40}$/', $value ) ? $value : '';
    }

    private static function sha64( string $value ): string {
        $value = strtolower( trim( $value ) );
        return 1 === preg_match( '/^[a-f0-9]{64}$/', $value ) ? $value : '';
    }

    private function __construct() {}
}
