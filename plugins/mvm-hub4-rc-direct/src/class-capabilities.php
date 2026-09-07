<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class MvM_Hub4_Capabilities {
    private const OPTION_SCHEMA = 'mvm_hub4_capability_schema';
    private const SCHEMA_VERSION = 4;

    public const SOURCE_VIEW          = 'mvm_hub4_sources_view';
    public const SOURCE_CHECK         = 'mvm_hub4_sources_check';
    public const SOURCE_MANAGE        = 'mvm_hub4_sources_manage';
    public const NEWS_VIEW            = 'mvm_hub4_news_view';
    public const NEWS_REVIEW          = 'mvm_hub4_news_review';
    public const AGENDA_VIEW          = 'mvm_hub4_agenda_view';
    public const TEAM_VIEW            = 'mvm_hub4_team_view';
    public const AUDIT_VIEW           = 'mvm_hub4_audit_view';
    public const TOOLS_USE            = 'mvm_hub4_tools_use';
    public const ASSIGNMENT_VIEW      = 'mvm_hub4_assignment_view';
    public const ASSIGNMENT_CREATE    = 'mvm_hub4_assignment_create';
    public const ASSIGNMENT_MANAGE    = 'mvm_hub4_assignment_manage';
    public const CHECKLIST_USE        = 'mvm_hub4_checklist_use';
    public const SYSTEM_OVERVIEW      = 'mvm_hub4_system_overview';

    public const SIGNAL_VIEW          = 'mvm_hub4_signal_view';
    public const SIGNAL_CREATE        = 'mvm_hub4_signal_create';
    public const SIGNAL_TRIAGE        = 'mvm_hub4_signal_triage';
    public const DOSSIER_VIEW         = 'mvm_hub4_dossier_view';
    public const DOSSIER_MANAGE       = 'mvm_hub4_dossier_manage';
    public const DOSSIER_PUBLISH      = 'mvm_hub4_dossier_publish';
    public const CALENDAR_VIEW        = 'mvm_hub4_calendar_view';
    public const CALENDAR_MANAGE      = 'mvm_hub4_calendar_manage';
    public const MEDIA_VIEW           = 'mvm_hub4_media_view';
    public const MEDIA_MANAGE         = 'mvm_hub4_media_manage';
    public const MEDIA_REVIEW         = 'mvm_hub4_media_review';
    public const DISTRIBUTION_VIEW    = 'mvm_hub4_distribution_view';
    public const DISTRIBUTION_PREPARE = 'mvm_hub4_distribution_prepare';
    public const DISTRIBUTION_APPROVE = 'mvm_hub4_distribution_approve';
    public const CORRECTION_VIEW      = 'mvm_hub4_correction_view';
    public const CORRECTION_MANAGE    = 'mvm_hub4_correction_manage';
    public const DASHBOARD_VIEW       = 'mvm_hub4_dashboard_view';

    public static function init(): void {
        add_action( 'init', array( __CLASS__, 'maybe_reconcile' ), 5 );
    }

    public static function activate(): void {
        self::reconcile();
        self::store_schema_version();
    }

    public static function maybe_reconcile(): void {
        if (
            self::SCHEMA_VERSION === (int) get_option( self::OPTION_SCHEMA, 0 )
            && self::role_map_is_current()
        ) {
            return;
        }

        self::reconcile();
        self::store_schema_version();

        if ( class_exists( 'MvM_Hub4_Audit' ) ) {
            MvM_Hub4_Audit::log(
                'system.capabilities_reconciled',
                'success',
                array(
                    'object_type' => 'role_matrix',
                    'context'     => array( 'schema_version' => self::SCHEMA_VERSION ),
                )
            );
        }
    }

    /**
     * Hub 4 owns only the capabilities in managed_capabilities(). Reconciliation
     * removes stale Hub 4 grants before applying this explicit least-privilege map.
     */
    public static function role_map(): array {
        $editorial = array(
            self::NEWS_VIEW,
            self::AGENDA_VIEW,
            self::TEAM_VIEW,
            self::ASSIGNMENT_VIEW,
            self::ASSIGNMENT_CREATE,
            self::CHECKLIST_USE,
            self::SIGNAL_VIEW,
            self::SIGNAL_CREATE,
            self::DOSSIER_VIEW,
            self::CALENDAR_VIEW,
            self::DASHBOARD_VIEW,
        );

        $editorial_manage = array_merge(
            $editorial,
            array(
                self::NEWS_REVIEW,
                self::SIGNAL_TRIAGE,
                self::DOSSIER_MANAGE,
                self::CALENDAR_MANAGE,
                self::MEDIA_VIEW,
                self::MEDIA_REVIEW,
                self::DISTRIBUTION_VIEW,
                self::DISTRIBUTION_PREPARE,
                self::CORRECTION_VIEW,
            )
        );

        $full = array_merge(
            $editorial_manage,
            array(
                self::SOURCE_VIEW,
                self::SOURCE_CHECK,
                self::SOURCE_MANAGE,
                self::AUDIT_VIEW,
                self::TOOLS_USE,
                self::ASSIGNMENT_MANAGE,
                self::SYSTEM_OVERVIEW,
                self::DOSSIER_PUBLISH,
                self::MEDIA_MANAGE,
                self::DISTRIBUTION_APPROVE,
                self::CORRECTION_MANAGE,
            )
        );

        return array(
            'administrator' => $full,
            'mvm_sysop'     => $full,
            'mvm_teamleider' => array_merge(
                $editorial_manage,
                array(
                    self::SOURCE_VIEW,
                    self::SOURCE_CHECK,
                    self::SOURCE_MANAGE,
                    self::AUDIT_VIEW,
                    self::TOOLS_USE,
                    self::ASSIGNMENT_MANAGE,
                    self::DOSSIER_PUBLISH,
                    self::MEDIA_MANAGE,
                    self::DISTRIBUTION_APPROVE,
                    self::CORRECTION_MANAGE,
                )
            ),
            'mvm_editor' => array_merge(
                $editorial_manage,
                array(
                    self::SOURCE_VIEW,
                    self::SOURCE_CHECK,
                    self::TOOLS_USE,
                    self::ASSIGNMENT_MANAGE,
                    self::DOSSIER_PUBLISH,
                    self::DISTRIBUTION_APPROVE,
                    self::CORRECTION_MANAGE,
                )
            ),
            'mvm_journalist' => array_merge(
                $editorial,
                array(
                    self::SOURCE_VIEW,
                    self::SOURCE_CHECK,
                    self::TOOLS_USE,
                    self::DOSSIER_MANAGE,
                    self::CALENDAR_MANAGE,
                    self::MEDIA_VIEW,
                    self::DISTRIBUTION_VIEW,
                    self::DISTRIBUTION_PREPARE,
                )
            ),
            'mvm_redacteur' => array_merge(
                $editorial,
                array(
                    self::SOURCE_VIEW,
                    self::SOURCE_CHECK,
                    self::TOOLS_USE,
                    self::DOSSIER_MANAGE,
                    self::CALENDAR_MANAGE,
                    self::MEDIA_VIEW,
                    self::DISTRIBUTION_VIEW,
                    self::DISTRIBUTION_PREPARE,
                )
            ),
            'mvm_fotograaf' => array(
                self::NEWS_VIEW,
                self::AGENDA_VIEW,
                self::TEAM_VIEW,
                self::ASSIGNMENT_VIEW,
                self::TOOLS_USE,
                self::SIGNAL_VIEW,
                self::SIGNAL_CREATE,
                self::DOSSIER_VIEW,
                self::CALENDAR_VIEW,
                self::MEDIA_VIEW,
                self::MEDIA_MANAGE,
                self::DASHBOARD_VIEW,
            ),
            'mvm_moderator' => array(
                self::NEWS_VIEW,
                self::TEAM_VIEW,
                self::ASSIGNMENT_VIEW,
                self::TOOLS_USE,
                self::SIGNAL_VIEW,
                self::SIGNAL_CREATE,
                self::CORRECTION_VIEW,
                self::DASHBOARD_VIEW,
            ),
            'mvm_vertaler' => array(
                self::SOURCE_VIEW,
                self::NEWS_VIEW,
                self::TEAM_VIEW,
                self::ASSIGNMENT_VIEW,
                self::TOOLS_USE,
                self::SIGNAL_VIEW,
                self::SIGNAL_CREATE,
                self::DOSSIER_VIEW,
                self::DISTRIBUTION_VIEW,
                self::DASHBOARD_VIEW,
            ),
        );
    }

    public static function managed_capabilities(): array {
        return array(
            self::SOURCE_VIEW,
            self::SOURCE_CHECK,
            self::SOURCE_MANAGE,
            self::NEWS_VIEW,
            self::NEWS_REVIEW,
            self::AGENDA_VIEW,
            self::TEAM_VIEW,
            self::AUDIT_VIEW,
            self::TOOLS_USE,
            self::ASSIGNMENT_VIEW,
            self::ASSIGNMENT_CREATE,
            self::ASSIGNMENT_MANAGE,
            self::CHECKLIST_USE,
            self::SYSTEM_OVERVIEW,
            self::SIGNAL_VIEW,
            self::SIGNAL_CREATE,
            self::SIGNAL_TRIAGE,
            self::DOSSIER_VIEW,
            self::DOSSIER_MANAGE,
            self::DOSSIER_PUBLISH,
            self::CALENDAR_VIEW,
            self::CALENDAR_MANAGE,
            self::MEDIA_VIEW,
            self::MEDIA_MANAGE,
            self::MEDIA_REVIEW,
            self::DISTRIBUTION_VIEW,
            self::DISTRIBUTION_PREPARE,
            self::DISTRIBUTION_APPROVE,
            self::CORRECTION_VIEW,
            self::CORRECTION_MANAGE,
            self::DASHBOARD_VIEW,
        );
    }

    private static function reconcile(): void {
        foreach ( self::role_map() as $role_name => $capabilities ) {
            $role = get_role( $role_name );
            if ( ! $role ) {
                continue;
            }

            foreach ( self::managed_capabilities() as $capability ) {
                $role->remove_cap( $capability );
            }

            foreach ( array_unique( $capabilities ) as $capability ) {
                $role->add_cap( $capability );
            }
        }
    }

    private static function role_map_is_current(): bool {
        $managed = self::managed_capabilities();

        foreach ( self::role_map() as $role_name => $capabilities ) {
            $role = get_role( $role_name );
            if ( ! $role ) {
                continue;
            }

            $expected = array_fill_keys( array_unique( $capabilities ), true );
            foreach ( $managed as $capability ) {
                if ( $role->has_cap( $capability ) !== isset( $expected[ $capability ] ) ) {
                    return false;
                }
            }
        }

        return true;
    }

    private static function store_schema_version(): void {
        if ( false === get_option( self::OPTION_SCHEMA, false ) ) {
            add_option( self::OPTION_SCHEMA, self::SCHEMA_VERSION, '', false );
            return;
        }

        update_option( self::OPTION_SCHEMA, self::SCHEMA_VERSION, false );
    }
}
