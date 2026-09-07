<?php

namespace MVM\Hub\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Dormant capability policy for Hub V5 workspace navigation.
 *
 * This class performs no WordPress capability lookup, role inspection or hook
 * registration. A caller supplies a capability checker so navigation stays a
 * projection of server-side authorization rather than becoming authorization.
 */
final class V5_Workspace_Policy {
    /** @return array<string,array{any_of:list<string>,inventory_required:bool}> */
    public static function policies(): array {
        return array(
            'staff' => array(
                'any_of' => array(
                    'mvm_work_view_own',
                    'mvm_newsroom_access',
                    'mvm_staff_board_view',
                    'mvm_team_chat_access',
                    'mvm_messages_access',
                    'mvm_mail_access',
                    'mvm_technical_access',
                    'mvm_organization_access',
                ),
                'inventory_required' => false,
            ),
            'newsroom' => array(
                'any_of' => array( 'mvm_newsroom_access' ),
                'inventory_required' => false,
            ),
            'agenda' => array(
                'any_of' => array( 'mvm_agenda_view', 'mvm_agenda_manage' ),
                'inventory_required' => false,
            ),
            'encyclopedie' => array(
                'any_of' => array(
                    'mvm_encyclopedie_view_editorial',
                    'mvm_encyclopedie_edit',
                    'mvm_encyclopedie_review',
                    'mvm_encyclopedie_publish',
                    'mvm_contextlinks_review',
                    'mvm_contextlinks_manage',
                ),
                'inventory_required' => false,
            ),
            'community' => array(
                'any_of' => array(
                    'mvm_community_moderate',
                    'mvm_community_escalate',
                    'mvm_community_manage_policy',
                ),
                'inventory_required' => false,
            ),
            'mierlokaal' => array(
                // Exact production inventory confirmed on 2026-09-04. Only
                // internal management capabilities grant the staff workspace.
                // Partner self-service remains behind the organization portal.
                'any_of' => array(
                    'mvm_marketplace_moderate',
                    'mvm_local_ads_manage',
                    'mvm_local_ads_admin',
                    'mvm_vacatures_manage_all',
                ),
                'inventory_required' => false,
            ),
            'intake' => array(
                'any_of' => array(
                    'mvm_intake_view',
                    'mvm_intake_triage',
                    'mvm_intake_assign',
                    'mvm_intake_manage',
                ),
                'inventory_required' => false,
            ),
            'media' => array(
                'any_of' => array( 'mvm_media_view', 'mvm_media_manage' ),
                'inventory_required' => false,
            ),
            'communications' => array(
                'any_of' => array(
                    'mvm_messages_access',
                    'mvm_mail_access',
                    'mvm_communications_admin',
                ),
                'inventory_required' => false,
            ),
            'layout' => array(
                'any_of' => array(
                    'mvm_layout_view',
                    'mvm_layout_edit',
                    'mvm_layout_review',
                    'mvm_layout_publish',
                    'mvm_layout_rollback',
                ),
                'inventory_required' => false,
            ),
            'technical' => array(
                'any_of' => array( 'mvm_technical_access' ),
                'inventory_required' => false,
            ),
        );
    }

    /**
     * @param callable(string,array<string,mixed>):bool $capability_check
     * @return array{allowed:bool,reason:string,workspace:string,policy:string,matched_capability:string}
     */
    public static function decide( string $workspace, callable $capability_check ): array {
        $workspace = sanitize_key( $workspace );
        $config    = V5_Workspace_Catalog::get( $workspace );
        if ( null === $config ) {
            return self::deny( $workspace, '', 'unknown_workspace' );
        }

        $policy = sanitize_key( (string) ( $config['policy'] ?? '' ) );
        $rules  = self::policies()[ $policy ] ?? null;
        if ( null === $rules ) {
            return self::deny( $workspace, $policy, 'unknown_policy' );
        }

        if ( true === $rules['inventory_required'] ) {
            return self::deny( $workspace, $policy, 'policy_inventory_required' );
        }

        if ( array() === $rules['any_of'] ) {
            return self::deny( $workspace, $policy, 'no_capability_contract' );
        }

        $context = array(
            'workspace' => $workspace,
            'policy'    => $policy,
        );

        foreach ( $rules['any_of'] as $capability ) {
            if ( true === (bool) $capability_check( $capability, $context ) ) {
                return array(
                    'allowed'            => true,
                    'reason'             => 'allowed',
                    'workspace'          => $workspace,
                    'policy'             => $policy,
                    'matched_capability' => $capability,
                );
            }
        }

        return self::deny( $workspace, $policy, 'capability_denied' );
    }

    /**
     * @param callable(string,array<string,mixed>):bool $capability_check
     * @return list<string>
     */
    public static function allowed_workspaces( callable $capability_check ): array {
        $allowed = array();
        foreach ( array_keys( V5_Workspace_Catalog::all() ) as $workspace ) {
            $decision = self::decide( $workspace, $capability_check );
            if ( true === $decision['allowed'] ) {
                $allowed[] = $workspace;
            }
        }

        return $allowed;
    }

    /**
     * @return array{allowed:bool,reason:string,workspace:string,policy:string,matched_capability:string}
     */
    private static function deny( string $workspace, string $policy, string $reason ): array {
        return array(
            'allowed'            => false,
            'reason'             => sanitize_key( $reason ),
            'workspace'          => sanitize_key( $workspace ),
            'policy'             => sanitize_key( $policy ),
            'matched_capability' => '',
        );
    }

    private function __construct() {}
}
