<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class MvM_Hub4_Dashboard {
    public static function overview(): array {
        global $wpdb;

        $signals = current_user_can( MvM_Hub4_Capabilities::SIGNAL_VIEW ) || current_user_can( 'manage_options' )
            ? MvM_Hub4_Signals::counts()
            : array();
        $dossiers = current_user_can( MvM_Hub4_Capabilities::DOSSIER_VIEW ) || current_user_can( 'manage_options' )
            ? MvM_Hub4_Dossiers::counts()
            : array();
        $radar = current_user_can( MvM_Hub4_Capabilities::SOURCE_VIEW ) || current_user_can( 'manage_options' )
            ? MvM_Hub4_Source_Radar::summary()
            : array();

        $assignment_table = MvM_Hub4_Assignments::table_name();
        $user_id = get_current_user_id();
        if ( current_user_can( MvM_Hub4_Capabilities::ASSIGNMENT_MANAGE ) || current_user_can( 'manage_options' ) ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed internal table.
            $overdue_assignments = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$assignment_table} WHERE due_at_utc IS NOT NULL AND due_at_utc < %s AND status NOT IN ('published','cancelled')", current_time( 'mysql', true ) ) );
        } else {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed internal table.
            $overdue_assignments = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$assignment_table} WHERE assignee_user_id = %d AND due_at_utc IS NOT NULL AND due_at_utc < %s AND status NOT IN ('published','cancelled')", $user_id, current_time( 'mysql', true ) ) );
        }

        $pending_news = 0;
        if ( current_user_can( MvM_Hub4_Capabilities::NEWS_VIEW ) || current_user_can( 'manage_options' ) ) {
            $pending_news = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = %s", 'post', 'pending' ) );
        }

        $checklist_incomplete = 0;
        if ( current_user_can( MvM_Hub4_Capabilities::CHECKLIST_USE ) || current_user_can( 'manage_options' ) ) {
            $checklist_table = MvM_Hub4_Publication_Checklist::table_name();
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed internal table.
            $checklist_incomplete = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$checklist_table} WHERE source_verified = 0 OR facts_verified = 0 OR facebook_ready = 0 OR second_review = 0" );
        }

        $media_review = current_user_can( MvM_Hub4_Capabilities::MEDIA_VIEW ) || current_user_can( 'manage_options' )
            ? MvM_Hub4_Media_Desk::review_count()
            : 0;
        $corrections = current_user_can( MvM_Hub4_Capabilities::CORRECTION_VIEW ) || current_user_can( 'manage_options' )
            ? MvM_Hub4_Corrections::waiting_count()
            : 0;
        $distribution = current_user_can( MvM_Hub4_Capabilities::DISTRIBUTION_VIEW ) || current_user_can( 'manage_options' )
            ? MvM_Hub4_Distribution::review_count()
            : 0;
        $calendar = current_user_can( MvM_Hub4_Capabilities::CALENDAR_VIEW ) || current_user_can( 'manage_options' )
            ? MvM_Hub4_Editorial_Calendar::upcoming_count( 7 )
            : 0;

        return array(
            'roleHome'    => self::role_home(),
            'signals'     => $signals,
            'dossiers'    => $dossiers,
            'sourceRadar' => $radar,
            'metrics'     => array(
                'overdueAssignments'   => $overdue_assignments,
                'pendingNews'          => $pending_news,
                'incompleteChecklists' => $checklist_incomplete,
                'upcomingCalendar'     => $calendar,
                'mediaForReview'       => $media_review,
                'correctionsWaiting'   => $corrections,
                'distributionReview'   => $distribution,
            ),
        );
    }

    public static function role_home(): array {
        $user  = wp_get_current_user();
        $roles = $user instanceof WP_User ? (array) $user->roles : array();
        $role  = sanitize_key( (string) ( reset( $roles ) ?: 'staff' ) );

        $orders = array(
            'administrator'  => array( 'dashboard', 'signals', 'dossiers', 'calendar', 'radar', 'corrections', 'distribution', 'media', 'system' ),
            'mvm_sysop'      => array( 'dashboard', 'signals', 'dossiers', 'calendar', 'radar', 'corrections', 'distribution', 'media', 'system' ),
            'mvm_teamleider' => array( 'signals', 'calendar', 'dossiers', 'radar', 'corrections', 'media', 'distribution', 'dashboard' ),
            'mvm_editor'     => array( 'dossiers', 'signals', 'calendar', 'corrections', 'distribution', 'media', 'dashboard' ),
            'mvm_journalist' => array( 'dashboard', 'signals', 'dossiers', 'calendar', 'radar', 'distribution' ),
            'mvm_redacteur'  => array( 'dashboard', 'signals', 'dossiers', 'calendar', 'radar', 'distribution' ),
            'mvm_fotograaf'  => array( 'media', 'calendar', 'dashboard', 'signals', 'dossiers' ),
            'mvm_moderator'  => array( 'corrections', 'signals', 'dashboard' ),
            'mvm_vertaler'   => array( 'dashboard', 'dossiers', 'signals', 'distribution', 'radar' ),
        );

        return array(
            'role'  => $role,
            'order' => $orders[ $role ] ?? array( 'dashboard', 'signals' ),
        );
    }
}
