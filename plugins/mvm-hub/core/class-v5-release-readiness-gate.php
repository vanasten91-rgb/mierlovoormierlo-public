<?php

namespace MVM\Hub\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Pure, dormant release-readiness evaluator for Hub V5.
 *
 * This class performs no WordPress writes, owns no route/hook and cannot
 * activate V5. It only evaluates explicit evidence signals so a release cannot
 * be promoted because a UI happens to look complete.
 */
final class V5_Release_Readiness_Gate {
    /**
     * @return array<string,list<string>>
     */
    public static function required_signals(): array {
        return array(
            'repository' => array(
                'exactHeadCiGreen',
                'mainProtected',
                'requiredChecksEnabled',
                'releaseArtifactVerified',
            ),
            'auth' => array(
                'registrationRouteOwnershipResolved',
                'privateRestPermissionCallbacksVerified',
                'stepUpMfaReady',
            ),
            'parity' => array(
                'productionInventoryCaptured',
                'encyclopedieParityResolved',
                'legacyOwnerInventoryComplete',
            ),
            'domains' => array(
                'workItemsPersistentReady',
                'newsroomReady',
                'agendaReady',
                'encyclopedieReady',
                'communityModerationReady',
                'mierlokaalReady',
                'intakeProtectedReady',
                'communicationsReady',
                'mailV2BidirectionalReady',
                'layoutStudioReady',
                'searchSegregationReady',
            ),
            'operations' => array(
                'migrationsDryRunVerified',
                'rollbackVerified',
                'durableEventOutboxReady',
                'queueCronOwnershipVerified',
                'observabilityReady',
                'roleUatComplete',
                'stagingE2EGreen',
            ),
            'security' => array(
                'sourceLeakageTestsGreen',
                'mailLeakageTestsGreen',
                'attachmentSecurityGreen',
                'auditMetadataOnly',
                'legacySendRouteAbsent',
            ),
            'release' => array(
                'explicitProductionApproval',
            ),
        );
    }

    /**
     * @param array<string,bool> $signals
     * @return array<string,mixed>
     */
    public static function evaluate( array $signals ): array {
        $missing_by_group = array();
        foreach ( self::required_signals() as $group => $required ) {
            foreach ( $required as $signal ) {
                if ( true !== ( $signals[ $signal ] ?? false ) ) {
                    $missing_by_group[ $group ][] = $signal;
                }
            }
        }

        $pre_production_missing = $missing_by_group;
        if ( isset( $pre_production_missing['release'] ) ) {
            $pre_production_missing['release'] = array_values(
                array_diff( $pre_production_missing['release'], array( 'explicitProductionApproval' ) )
            );
            if ( array() === $pre_production_missing['release'] ) {
                unset( $pre_production_missing['release'] );
            }
        }

        $missing = array();
        foreach ( $missing_by_group as $group => $items ) {
            foreach ( $items as $signal ) {
                $missing[] = $group . ':' . $signal;
            }
        }

        return array(
            'canRequestProductionApproval' => array() === $pre_production_missing,
            'canPromoteProduction'         => array() === $missing,
            'productionActivated'          => false,
            'routeOwnership'               => false,
            'requiresExplicitApproval'     => true,
            'missing'                      => $missing,
            'missingByGroup'               => $missing_by_group,
        );
    }

    private function __construct() {}
}
