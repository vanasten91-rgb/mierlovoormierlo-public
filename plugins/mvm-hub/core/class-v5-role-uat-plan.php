<?php

namespace MVM\Hub\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Dormant role-based UAT evidence plan for Hub V5.
 *
 * This class defines test evidence only. It does not grant capabilities, inspect
 * WordPress roles or alter authorization decisions.
 */
final class V5_Role_UAT_Plan {
    /** @return array<string,list<string>> */
    public static function scenarios(): array {
        return array(
            'journalist' => array(
                'my_work_visible',
                'assigned_news_edit_allowed',
                'unassigned_protected_source_denied',
                'final_publish_denied',
            ),
            'redacteur' => array(
                'intake_triage_allowed',
                'assignment_create_allowed',
                'protected_identity_without_acl_denied',
                'technical_workspace_denied',
            ),
            'editor' => array(
                'review_allowed',
                'correction_flow_allowed',
                'publish_regular_allowed',
                'protected_identity_without_acl_denied',
            ),
            'fotograaf' => array(
                'media_assignment_visible',
                'private_upload_not_public_by_default',
                'rights_metadata_required',
                'news_publish_denied',
            ),
            'moderator' => array(
                'moderation_inbox_visible',
                'community_action_allowed',
                'newsroom_publish_denied',
                'protected_source_denied',
            ),
            'vertaler' => array(
                'translation_task_visible',
                'translation_submit_allowed',
                'publish_denied',
                'protected_source_denied',
            ),
            'teamleider' => array(
                'blocked_work_visible',
                'escalation_allowed',
                'protected_source_requires_acl',
                'technical_workspace_not_implicit',
            ),
            'agenda_editor' => array(
                'agenda_queue_visible',
                'agenda_review_allowed',
                'news_publish_not_implicit',
            ),
            'encyclopedie_editor' => array(
                'encyclopedie_queue_visible',
                'contextlinks_review_allowed',
                'runtime_parity_gate_respected',
            ),
            'mierlokaal_editor' => array(
                'staff_mierlokaal_visible',
                'partner_self_service_not_staff_access',
                'newsroom_access_not_implicit',
            ),
            'communications' => array(
                'staff_messages_visible',
                'mail_read_allowed_when_enabled',
                'mail_send_denied_until_cutover',
                'mail_body_not_in_shared_search',
            ),
            'sysop' => array(
                'technical_health_visible',
                'release_gate_visible',
                'source_protected_content_not_implicit',
                'mail_body_not_implicit',
            ),
            'partner' => array(
                'organization_portal_visible',
                'staff_workspace_denied',
                'team_chat_denied',
                'protected_source_denied',
            ),
        );
    }

    /**
     * @param array<string,array<string,bool>> $evidence
     * @return array{complete:bool,missingByRole:array<string,list<string>>,testedRoles:list<string>,productionActivated:bool}
     */
    public static function evaluate( array $evidence ): array {
        $missing_by_role = array();
        $tested_roles    = array();

        foreach ( self::scenarios() as $role => $scenarios ) {
            $role_evidence = is_array( $evidence[ $role ] ?? null ) ? $evidence[ $role ] : array();
            $role_complete = true;

            foreach ( $scenarios as $scenario ) {
                if ( true !== ( $role_evidence[ $scenario ] ?? false ) ) {
                    $missing_by_role[ $role ][] = $scenario;
                    $role_complete = false;
                }
            }

            if ( $role_complete ) {
                $tested_roles[] = $role;
            }
        }

        return array(
            'complete' => array() === $missing_by_role,
            'missingByRole' => $missing_by_role,
            'testedRoles' => array_values( $tested_roles ),
            'productionActivated' => false,
        );
    }

    private function __construct() {}
}
