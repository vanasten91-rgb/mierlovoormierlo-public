<?php

namespace MVM\Hub\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Dormant V5 journalism workflow definitions.
 *
 * Definitions are versioned and registration-only. They do not mutate posts,
 * assignments, capabilities or production state. Existing Newsroom workflow
 * state remains authoritative until a later migration release explicitly maps
 * current objects into these V5 workflow families.
 */
final class V5_News_Workflows {
    public const SHORT      = 'news_short';
    public const REGULAR    = 'news_regular';
    public const SENSITIVE  = 'news_sensitive';
    public const CORRECTION = 'news_correction';

    public static function register_all(): bool {
        $ok = true;
        foreach ( self::definitions() as $key => $definition ) {
            $ok = Workflow_Registry::register( $key, $definition ) && $ok;
        }
        return $ok;
    }

    /** @return array<string,array<string,mixed>> */
    public static function definitions(): array {
        return array(
            self::SHORT => array(
                'version' => 1,
                'initial' => 'signal',
                'states'  => array( 'signal', 'verify', 'draft', 'review', 'ready', 'published', 'cancelled' ),
                'transitions' => array(
                    'signal' => array(
                        'verify' => self::policy( Capabilities::RADAR_TRIAGE, 'news_short_verify' ),
                        'cancelled' => self::policy( Capabilities::RADAR_TRIAGE, 'news_short_cancelled' ),
                    ),
                    'verify' => array(
                        'draft' => self::policy( Capabilities::NEWS_CREATE, 'news_short_draft' ),
                        'cancelled' => self::policy( Capabilities::RADAR_TRIAGE, 'news_short_cancelled' ),
                    ),
                    'draft' => array(
                        'review' => self::policy( Capabilities::NEWS_EDIT_OWN, 'news_short_review_requested', true ),
                    ),
                    'review' => array(
                        'draft' => self::policy( Capabilities::NEWS_REVIEW, 'news_short_changes_requested', true ),
                        'ready' => self::policy( Capabilities::NEWS_REVIEW, 'news_short_ready', true ),
                    ),
                    'ready' => array(
                        'published' => self::policy( Capabilities::NEWS_PUBLISH, 'news_published', true ),
                    ),
                ),
            ),
            self::REGULAR => array(
                'version' => 1,
                'initial' => 'signal',
                'states'  => array(
                    'signal', 'triage', 'assigned', 'research', 'draft', 'source_check',
                    'media', 'review', 'changes_requested', 'ready', 'scheduled', 'published', 'cancelled',
                ),
                'transitions' => array(
                    'signal' => array(
                        'triage' => self::policy( Capabilities::RADAR_TRIAGE, 'news_triage_started' ),
                        'cancelled' => self::policy( Capabilities::RADAR_TRIAGE, 'news_cancelled' ),
                    ),
                    'triage' => array(
                        'assigned' => self::policy( Capabilities::ASSIGNMENTS_MANAGE, 'news_assigned' ),
                        'cancelled' => self::policy( Capabilities::ASSIGNMENTS_MANAGE, 'news_cancelled' ),
                    ),
                    'assigned' => array(
                        'research' => self::policy( Capabilities::NEWS_EDIT_OWN, 'news_research_started', true ),
                    ),
                    'research' => array(
                        'draft' => self::policy( Capabilities::NEWS_EDIT_OWN, 'news_draft_started', true ),
                    ),
                    'draft' => array(
                        'source_check' => self::policy( Capabilities::NEWS_EDIT_OWN, 'news_source_check_requested', true ),
                    ),
                    'source_check' => array(
                        'media' => self::policy( Capabilities::SOURCES_VIEW, 'news_sources_checked', true ),
                        'draft' => self::policy( Capabilities::SOURCES_VIEW, 'news_source_changes_required', true ),
                    ),
                    'media' => array(
                        'review' => self::policy( Capabilities::MEDIA_VIEW, 'news_media_ready', true ),
                        'draft' => self::policy( Capabilities::MEDIA_VIEW, 'news_media_changes_required', true ),
                    ),
                    'review' => array(
                        'changes_requested' => self::policy( Capabilities::NEWS_REVIEW, 'news_changes_requested', true ),
                        'ready' => self::policy( Capabilities::NEWS_REVIEW, 'news_ready', true ),
                    ),
                    'changes_requested' => array(
                        'draft' => self::policy( Capabilities::NEWS_EDIT_OWN, 'news_redraft_started', true ),
                    ),
                    'ready' => array(
                        'scheduled' => self::policy( Capabilities::NEWS_PUBLISH, 'news_scheduled', true ),
                        'published' => self::policy( Capabilities::NEWS_PUBLISH, 'news_published', true ),
                    ),
                    'scheduled' => array(
                        'published' => self::policy( Capabilities::NEWS_PUBLISH, 'news_published', true ),
                        'ready' => self::policy( Capabilities::NEWS_PUBLISH, 'news_unscheduled', true ),
                    ),
                ),
            ),
            self::SENSITIVE => array(
                'version' => 1,
                'initial' => 'protected_tip',
                'states'  => array(
                    'protected_tip', 'restricted_triage', 'research', 'multi_source_verify',
                    'editor_review', 'teamlead_decision', 'changes_requested', 'ready',
                    'published', 'closed',
                ),
                'transitions' => array(
                    'protected_tip' => array(
                        'restricted_triage' => self::protected_policy( 'mvm_intake_triage', 'protected_tip_triage_started' ),
                        'closed' => self::protected_policy( 'mvm_intake_manage', 'protected_tip_closed' ),
                    ),
                    'restricted_triage' => array(
                        'research' => self::protected_policy( Capabilities::ASSIGNMENTS_MANAGE, 'sensitive_research_started' ),
                        'closed' => self::protected_policy( 'mvm_intake_manage', 'protected_tip_closed' ),
                    ),
                    'research' => array(
                        'multi_source_verify' => self::protected_policy( Capabilities::NEWS_EDIT_OWN, 'sensitive_multisource_verification_requested' ),
                    ),
                    'multi_source_verify' => array(
                        'editor_review' => self::protected_policy( Capabilities::SOURCES_VIEW, 'sensitive_sources_verified' ),
                        'research' => self::protected_policy( Capabilities::SOURCES_VIEW, 'sensitive_source_work_required' ),
                    ),
                    'editor_review' => array(
                        'teamlead_decision' => self::protected_policy( Capabilities::NEWS_REVIEW, 'sensitive_editor_review_complete' ),
                        'changes_requested' => self::protected_policy( Capabilities::NEWS_REVIEW, 'sensitive_changes_requested' ),
                    ),
                    'changes_requested' => array(
                        'research' => self::protected_policy( Capabilities::NEWS_EDIT_OWN, 'sensitive_research_resumed' ),
                    ),
                    'teamlead_decision' => array(
                        'ready' => self::protected_policy( Capabilities::NEWS_PUBLISH, 'sensitive_publication_approved', true ),
                        'changes_requested' => self::protected_policy( Capabilities::NEWS_REVIEW, 'sensitive_changes_requested', true ),
                        'closed' => self::protected_policy( Capabilities::NEWS_PUBLISH, 'sensitive_case_closed', true ),
                    ),
                    'ready' => array(
                        'published' => self::protected_policy( Capabilities::NEWS_PUBLISH, 'news_published', true ),
                    ),
                ),
            ),
            self::CORRECTION => array(
                'version' => 1,
                'initial' => 'report',
                'states'  => array(
                    'report', 'triage', 'investigate', 'decision', 'correction_ready',
                    'published_correction', 'rejected', 'closed',
                ),
                'transitions' => array(
                    'report' => array(
                        'triage' => self::policy( Capabilities::CORRECTIONS_VIEW, 'correction_triage_started' ),
                    ),
                    'triage' => array(
                        'investigate' => self::policy( Capabilities::CORRECTIONS_MANAGE, 'correction_investigation_started', true ),
                        'rejected' => self::policy( Capabilities::CORRECTIONS_MANAGE, 'correction_rejected', true ),
                    ),
                    'investigate' => array(
                        'decision' => self::policy( Capabilities::CORRECTIONS_MANAGE, 'correction_decision_requested', true ),
                    ),
                    'decision' => array(
                        'correction_ready' => self::policy( Capabilities::NEWS_REVIEW, 'correction_approved', true ),
                        'rejected' => self::policy( Capabilities::NEWS_REVIEW, 'correction_rejected', true ),
                    ),
                    'correction_ready' => array(
                        'published_correction' => self::policy( Capabilities::NEWS_PUBLISH, 'correction_published', true ),
                    ),
                    'published_correction' => array(
                        'closed' => self::policy( Capabilities::CORRECTIONS_MANAGE, 'correction_closed', true ),
                    ),
                    'rejected' => array(
                        'closed' => self::policy( Capabilities::CORRECTIONS_MANAGE, 'correction_closed', true ),
                    ),
                ),
            ),
        );
    }

    /** @return array<string,mixed> */
    private static function policy( string $capability, string $audit_event, bool $object_check = false ): array {
        return array(
            'capability'            => $capability,
            'requires_object_check' => $object_check,
            'requires_step_up'      => false,
            'audit_event'           => $audit_event,
        );
    }

    /** @return array<string,mixed> */
    private static function protected_policy( string $capability, string $audit_event, bool $step_up = false ): array {
        return array(
            'capability'            => $capability,
            'requires_object_check' => true,
            'requires_step_up'      => $step_up,
            'audit_event'           => $audit_event,
        );
    }

    private function __construct() {}
}
