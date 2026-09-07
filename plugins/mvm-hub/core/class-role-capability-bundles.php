<?php

namespace MVM\Hub\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/** Explicit capability bundles for MvM staff roles. */
final class Role_Capability_Bundles {
    private const OPTION_SCHEMA  = 'mvm_hub_capability_schema';
    private const SCHEMA_VERSION = 3;

    /** @return array<string,array<int,string>> */
    public static function role_map(): array {
        $staff_collaboration = array(
            Capabilities::TEAM_VIEW,
            Capabilities::STAFF_BOARD_VIEW,
            Capabilities::STAFF_BOARD_POST,
            Capabilities::TEAM_CHAT_ACCESS,
            Capabilities::TEAM_CHAT_SEND,
            Capabilities::MESSAGES_ACCESS,
            Capabilities::MESSAGES_SEND,
            Capabilities::MESSAGES_MANAGE_OWN,
        );

        $newsroom_base = array_merge(
            array(
                Capabilities::NEWSROOM_ACCESS,
                Capabilities::ASSIGNMENTS_VIEW,
                Capabilities::RADAR_VIEW,
                Capabilities::SOURCES_VIEW,
                Capabilities::AGENDA_VIEW,
                Capabilities::MEDIA_VIEW,
                Capabilities::DOSSIERS_VIEW,
                Capabilities::CORRECTIONS_VIEW,
                Capabilities::DISTRIBUTION_VIEW,
            ),
            $staff_collaboration
        );

        $reporter = array_merge(
            $newsroom_base,
            array(
                Capabilities::NEWS_CREATE,
                Capabilities::NEWS_EDIT_OWN,
                Capabilities::AGENDA_MANAGE,
                Capabilities::DOSSIERS_MANAGE,
                Capabilities::DISTRIBUTION_MANAGE,
            )
        );

        // Redacteur is deliberately a triage/assignment role, not a publisher.
        $redacteur = array_merge(
            $reporter,
            array(
                Capabilities::ASSIGNMENTS_MANAGE,
                Capabilities::RADAR_TRIAGE,
                'mvm_intake_view',
                'mvm_intake_triage',
                'mvm_intake_assign',
            )
        );

        $editor = array_merge(
            $reporter,
            array(
                Capabilities::NEWS_EDIT_TEAM,
                Capabilities::NEWS_REVIEW,
                Capabilities::NEWS_PUBLISH,
                Capabilities::ASSIGNMENTS_MANAGE,
                Capabilities::RADAR_TRIAGE,
                Capabilities::SOURCES_MANAGE,
                Capabilities::MEDIA_MANAGE,
                Capabilities::CORRECTIONS_MANAGE,
                Capabilities::STAFF_BOARD_MODERATE,
                Capabilities::TEAM_CHAT_MODERATE,
                Capabilities::MAIL_ACCESS,
                Capabilities::MAIL_READ,
                Capabilities::MAIL_COMPOSE,
                Capabilities::MAIL_SEND,
                Capabilities::MAIL_MANAGE_MESSAGES,
                Capabilities::MAIL_MANAGE_FOLDERS,
                Capabilities::MAIL_MANAGE_OWN_ATTACHMENTS,
            )
        );

        $team_lead = array_merge(
            $editor,
            array(
                Capabilities::ORGANIZATION_ACCESS,
                Capabilities::COMMUNICATIONS_ADMIN,
                Capabilities::COMMUNICATIONS_AUDIT,
                'mvm_intake_manage',
                'mvm_community_moderate',
                'mvm_community_escalate',
            )
        );

        $technical = array_merge(
            $team_lead,
            array( Capabilities::TECHNICAL_ACCESS )
        );

        $limited_staff = array_merge(
            array( Capabilities::NEWSROOM_ACCESS ),
            $staff_collaboration
        );

        $moderator = array_merge(
            $limited_staff,
            array(
                Capabilities::NEWS_REVIEW,
                Capabilities::CORRECTIONS_VIEW,
                'mvm_community_moderate',
            )
        );

        // These V5 domain roles are intentionally narrow. They share staff
        // collaboration but do not inherit Newsroom or technical privileges.
        $agenda_editor = array_merge(
            $staff_collaboration,
            array(
                Capabilities::AGENDA_VIEW,
                Capabilities::AGENDA_MANAGE,
            )
        );

        $encyclopedie_editor = array_merge(
            $staff_collaboration,
            array(
                'mvm_encyclopedie_view_editorial',
                'mvm_encyclopedie_edit',
                'mvm_encyclopedie_review',
                'mvm_contextlinks_review',
            )
        );

        $mierlokaal_editor = array_merge(
            $staff_collaboration,
            array(
                'mvm_marketplace_moderate',
                'mvm_local_ads_manage',
                'mvm_vacatures_manage_all',
            )
        );

        $communications = array_merge(
            $staff_collaboration,
            array(
                Capabilities::MAIL_ACCESS,
                Capabilities::MAIL_READ,
                Capabilities::MAIL_COMPOSE,
                Capabilities::MAIL_SEND,
                Capabilities::MAIL_MANAGE_OWN_ATTACHMENTS,
            )
        );

        return array(
            'administrator'            => $technical,
            'mvm_sysop'                => $technical,
            'mvm_teamleider'           => $team_lead,
            'mvm_editor'               => $editor,
            'mvm_journalist'           => $reporter,
            'mvm_redacteur'            => $redacteur,
            'mvm_fotograaf'            => array_merge(
                $limited_staff,
                array(
                    Capabilities::NEWS_EDIT_OWN,
                    Capabilities::ASSIGNMENTS_VIEW,
                    Capabilities::RADAR_VIEW,
                    Capabilities::MEDIA_VIEW,
                    Capabilities::MEDIA_MANAGE,
                )
            ),
            'mvm_moderator'            => $moderator,
            'mvm_vertaler'             => array_merge(
                $limited_staff,
                array(
                    Capabilities::NEWS_EDIT_OWN,
                    Capabilities::ASSIGNMENTS_VIEW,
                    Capabilities::SOURCES_VIEW,
                    Capabilities::DOSSIERS_VIEW,
                    Capabilities::DISTRIBUTION_VIEW,
                )
            ),
            'mvm_agenda_editor'         => $agenda_editor,
            'mvm_encyclopedie_editor'  => $encyclopedie_editor,
            'mvm_mierlokaal_editor'     => $mierlokaal_editor,
            'mvm_communications'        => $communications,
        );
    }

    /** @return array<string,string> */
    public static function provisionable_roles(): array {
        return array(
            'mvm_agenda_editor'        => 'Agenda-editor',
            'mvm_encyclopedie_editor' => 'Encyclopedie-editor',
            'mvm_mierlokaal_editor'    => 'MierLokaal-editor',
            'mvm_communications'       => 'Communications',
        );
    }

    /**
     * Capabilities owned by another canonical plugin may be granted to a V5
     * role, but Hub reconciliation must never remove them from existing roles.
     *
     * @return array<int,string>
     */
    private static function externally_owned_capabilities(): array {
        return array(
            'mvm_marketplace_moderate',
            'mvm_local_ads_manage',
            'mvm_vacatures_manage_all',
        );
    }

    /** @return array<int,string> */
    public static function managed_capabilities(): array {
        $all = array_values( array_unique( array_merge( ...array_values( self::role_map() ) ) ) );
        $external = array_fill_keys( self::externally_owned_capabilities(), true );
        return array_values( array_filter(
            $all,
            static fn( string $capability ): bool => ! isset( $external[ $capability ] )
        ) );
    }

    /** @return array<string,mixed> */
    public static function status(): array {
        $roles = array();
        foreach ( self::role_map() as $role_name => $expected_caps ) {
            $role = get_role( $role_name );
            if ( ! $role ) {
                $roles[ $role_name ] = array( 'exists' => false, 'matches' => false, 'missing' => array_values( array_unique( $expected_caps ) ), 'extra' => array() );
                continue;
            }

            $expected = array_fill_keys( array_unique( $expected_caps ), true );
            $missing  = array();
            $extra    = array();
            foreach ( array_keys( $expected ) as $capability ) {
                if ( ! $role->has_cap( $capability ) ) {
                    $missing[] = $capability;
                }
            }
            foreach ( self::managed_capabilities() as $capability ) {
                if ( ! isset( $expected[ $capability ] ) && $role->has_cap( $capability ) ) {
                    $extra[] = $capability;
                }
            }
            $roles[ $role_name ] = array(
                'exists'  => true,
                'matches' => array() === $missing && array() === $extra,
                'missing' => $missing,
                'extra'   => $extra,
            );
        }

        return array(
            'schemaVersion' => self::SCHEMA_VERSION,
            'storedVersion' => (int) get_option( self::OPTION_SCHEMA, 0 ),
            'gateEnabled'   => Runtime_Gates::capability_reconciliation_enabled(),
            'roles'         => $roles,
        );
    }

    public static function reconcile_if_enabled(): void {
        if ( ! Runtime_Gates::capability_reconciliation_enabled() ) {
            return;
        }

        $status = self::status();
        $all_match = self::SCHEMA_VERSION === (int) ( $status['storedVersion'] ?? 0 );
        foreach ( (array) ( $status['roles'] ?? array() ) as $role_status ) {
            if ( empty( $role_status['exists'] ) || empty( $role_status['matches'] ) ) {
                $all_match = false;
                break;
            }
        }
        if ( $all_match ) {
            return;
        }

        $managed = self::managed_capabilities();
        $provisionable = self::provisionable_roles();
        foreach ( self::role_map() as $role_name => $capabilities ) {
            $role = get_role( $role_name );
            if ( ! $role && isset( $provisionable[ $role_name ] ) && function_exists( 'add_role' ) ) {
                add_role( $role_name, $provisionable[ $role_name ], array( 'read' => true ) );
                $role = get_role( $role_name );
            }
            if ( ! $role ) {
                continue;
            }
            foreach ( $managed as $capability ) {
                $role->remove_cap( $capability );
            }
            foreach ( array_unique( $capabilities ) as $capability ) {
                $role->add_cap( $capability );
            }
        }

        update_option( self::OPTION_SCHEMA, self::SCHEMA_VERSION, false );
    }

    private function __construct() {}
}
