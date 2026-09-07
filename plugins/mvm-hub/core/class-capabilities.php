<?php

namespace MVM\Hub\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Capabilities {
    public const MY_MIERLO_ACCESS     = 'mvm_my_mierlo_access';
    public const ORGANIZATION_ACCESS  = 'mvm_organization_access';
    public const TECHNICAL_ACCESS     = 'mvm_technical_access';

    public const NEWSROOM_ACCESS      = 'mvm_newsroom_access';
    public const NEWS_CREATE         = 'mvm_news_create';
    public const NEWS_EDIT_OWN       = 'mvm_news_edit_own';
    public const NEWS_EDIT_TEAM      = 'mvm_news_edit_team';
    public const NEWS_REVIEW         = 'mvm_news_review';
    public const NEWS_PUBLISH        = 'mvm_news_publish';
    public const ASSIGNMENTS_VIEW    = 'mvm_assignments_view';
    public const ASSIGNMENTS_MANAGE  = 'mvm_assignments_manage';
    public const RADAR_VIEW          = 'mvm_radar_view';
    public const RADAR_TRIAGE        = 'mvm_radar_triage';
    public const SOURCES_VIEW        = 'mvm_sources_view';
    public const SOURCES_MANAGE      = 'mvm_sources_manage';
    public const AGENDA_VIEW         = 'mvm_agenda_view';
    public const AGENDA_MANAGE       = 'mvm_agenda_manage';
    public const MEDIA_VIEW          = 'mvm_media_view';
    public const MEDIA_MANAGE        = 'mvm_media_manage';
    public const DOSSIERS_VIEW       = 'mvm_dossiers_view';
    public const DOSSIERS_MANAGE     = 'mvm_dossiers_manage';
    public const CORRECTIONS_VIEW    = 'mvm_corrections_view';
    public const CORRECTIONS_MANAGE  = 'mvm_corrections_manage';
    public const DISTRIBUTION_VIEW   = 'mvm_distribution_view';
    public const DISTRIBUTION_MANAGE = 'mvm_distribution_manage';
    public const TEAM_VIEW           = 'mvm_team_view';

    // Private staff collaboration. These capabilities are intentionally
    // separate from PeepSo/community messaging and from public site roles.
    public const STAFF_BOARD_VIEW     = 'mvm_staff_board_view';
    public const STAFF_BOARD_POST     = 'mvm_staff_board_post';
    public const STAFF_BOARD_MODERATE = 'mvm_staff_board_moderate';
    public const TEAM_CHAT_ACCESS     = 'mvm_team_chat_access';
    public const TEAM_CHAT_SEND       = 'mvm_team_chat_send';
    public const TEAM_CHAT_MODERATE   = 'mvm_team_chat_moderate';

    // Shared communications capabilities. These are deliberately independent
    // from Newsroom access so mailbox/thread privacy cannot be inferred from role.
    public const MESSAGES_ACCESS     = 'mvm_messages_access';
    public const MESSAGES_SEND       = 'mvm_messages_send';
    public const MESSAGES_MANAGE_OWN = 'mvm_messages_manage_own';

    public const MAIL_ACCESS                 = 'mvm_mail_access';
    public const MAIL_READ                   = 'mvm_mail_read';
    public const MAIL_COMPOSE                = 'mvm_mail_compose';
    public const MAIL_SEND                   = 'mvm_mail_send';
    public const MAIL_MANAGE_MESSAGES        = 'mvm_mail_manage_messages';
    public const MAIL_MANAGE_FOLDERS         = 'mvm_mail_manage_folders';
    public const MAIL_MANAGE_OWN_ATTACHMENTS = 'mvm_mail_manage_own_attachments';

    public const COMMUNICATIONS_ADMIN = 'mvm_communications_admin';
    public const COMMUNICATIONS_AUDIT = 'mvm_communications_audit';

    public static function can_access_my_mierlo(): bool {
        if ( ! is_user_logged_in() ) {
            return false;
        }

        return current_user_can( 'read' )
            || current_user_can( self::MY_MIERLO_ACCESS )
            || current_user_can( 'manage_options' );
    }

    public static function can_access_organization(): bool {
        return current_user_can( 'manage_options' )
            || current_user_can( self::ORGANIZATION_ACCESS );
    }

    public static function can_access_newsroom(): bool {
        return current_user_can( 'manage_options' )
            || current_user_can( self::NEWSROOM_ACCESS )
            || current_user_can( 'mvm_hub4_dashboard_view' )
            || current_user_can( 'mvm_hub4_news_view' );
    }

    public static function can_access_communications(): bool {
        return current_user_can( 'manage_options' )
            || current_user_can( self::COMMUNICATIONS_ADMIN )
            || current_user_can( self::MESSAGES_ACCESS )
            || current_user_can( self::MAIL_ACCESS );
    }

    public static function can_access_technical(): bool {
        return current_user_can( 'manage_options' )
            || current_user_can( self::TECHNICAL_ACCESS );
    }

    public static function can_read( string $new_capability, string $legacy_capability = '' ): bool {
        if ( current_user_can( 'manage_options' ) || current_user_can( $new_capability ) ) {
            return true;
        }

        return '' !== $legacy_capability && current_user_can( $legacy_capability );
    }

    public static function can_manage_assignments(): bool {
        return self::can_read( self::ASSIGNMENTS_MANAGE, 'mvm_hub4_assignment_manage' );
    }

    public static function can_access_staff_board(): bool {
        return is_user_logged_in()
            && ( current_user_can( 'manage_options' ) || current_user_can( self::STAFF_BOARD_VIEW ) );
    }

    public static function can_post_staff_board(): bool {
        return self::can_access_staff_board()
            && ( current_user_can( 'manage_options' ) || current_user_can( self::STAFF_BOARD_POST ) );
    }

    public static function can_access_team_chat(): bool {
        return is_user_logged_in()
            && ( current_user_can( 'manage_options' ) || current_user_can( self::TEAM_CHAT_ACCESS ) );
    }

    public static function can_send_team_chat(): bool {
        return self::can_access_team_chat()
            && ( current_user_can( 'manage_options' ) || current_user_can( self::TEAM_CHAT_SEND ) );
    }

    /**
     * Communications deliberately has no broad legacy fallback. Existing Mail
     * remains under its current read-only guard until the dedicated migration.
     */
    public static function can_access_mail(): bool {
        return current_user_can( 'manage_options' )
            || current_user_can( self::COMMUNICATIONS_ADMIN )
            || current_user_can( self::MAIL_ACCESS );
    }

    public static function can_read_mail(): bool {
        return self::can_access_mail()
            && ( current_user_can( 'manage_options' )
                || current_user_can( self::COMMUNICATIONS_ADMIN )
                || current_user_can( self::MAIL_READ ) );
    }

    public static function can_send_mail(): bool {
        return self::can_access_mail()
            && ( current_user_can( 'manage_options' )
                || current_user_can( self::COMMUNICATIONS_ADMIN )
                || current_user_can( self::MAIL_SEND ) );
    }

    private function __construct() {}
}
