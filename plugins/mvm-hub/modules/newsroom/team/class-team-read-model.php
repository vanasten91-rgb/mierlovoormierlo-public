<?php

namespace MVM\Hub\Modules\Newsroom\Team;

use MVM\Hub\Core\Capabilities;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Team directory based on capabilities, never editorial role names.
 * No e-mail address, login name or profile/contact metadata is exposed.
 */
final class Team_Read_Model {
    /** @return array<string,mixed> */
    public function list( int $page = 1, int $per_page = 20 ): array {
        $page     = max( 1, min( 100, $page ) );
        $per_page = max( 1, min( 50, $per_page ) );

        $query = new \WP_User_Query(
            array(
                'capability__in' => array(
                    Capabilities::NEWSROOM_ACCESS,
                    'mvm_hub4_dashboard_view',
                    'mvm_hub4_news_view',
                    'mvm_hub4_team_view',
                ),
                'orderby'     => 'display_name',
                'order'       => 'ASC',
                'number'      => $per_page + 1,
                'offset'      => ( $page - 1 ) * $per_page,
                'count_total' => false,
                'fields'      => 'all',
            )
        );

        $items = array();
        foreach ( (array) $query->get_results() as $user ) {
            if ( ! $user instanceof \WP_User ) {
                continue;
            }

            $items[] = array(
                'id'          => (int) $user->ID,
                'displayName' => sanitize_text_field( (string) $user->display_name ),
                'isCurrent'   => (int) $user->ID === get_current_user_id(),
                'areas'       => self::areas_for_user( $user ),
            );
        }

        $has_more = count( $items ) > $per_page;
        $items = array_slice( $items, 0, $per_page );

        return array(
            'items'          => array_values( $items ),
            'page'           => $page,
            'perPage'        => $per_page,
            'hasMore'        => $has_more,
            'generatedAtUtc' => gmdate( 'c' ),
        );
    }

    /** @return array<int,string> */
    private static function areas_for_user( \WP_User $user ): array {
        $checks = array(
            'nieuws'      => array( Capabilities::NEWS_CREATE, Capabilities::NEWS_EDIT_OWN, Capabilities::NEWS_EDIT_TEAM, 'mvm_hub4_news_view' ),
            'review'      => array( Capabilities::NEWS_REVIEW, 'mvm_hub4_news_review' ),
            'opdrachten'  => array( Capabilities::ASSIGNMENTS_VIEW, 'mvm_hub4_assignment_view' ),
            'radar'       => array( Capabilities::RADAR_VIEW, 'mvm_hub4_signal_view' ),
            'bronnen'     => array( Capabilities::SOURCES_VIEW, 'mvm_hub4_sources_view' ),
            'agenda'      => array( Capabilities::AGENDA_VIEW, 'mvm_hub4_calendar_view' ),
            'media'       => array( Capabilities::MEDIA_VIEW, 'mvm_hub4_media_view' ),
            'dossiers'    => array( Capabilities::DOSSIERS_VIEW, 'mvm_hub4_dossier_view' ),
            'correcties'  => array( Capabilities::CORRECTIONS_VIEW, 'mvm_hub4_correction_view' ),
            'distributie' => array( Capabilities::DISTRIBUTION_VIEW, 'mvm_hub4_distribution_view' ),
        );

        $areas = array();
        foreach ( $checks as $label => $capabilities ) {
            foreach ( $capabilities as $capability ) {
                if ( $user->has_cap( $capability ) || $user->has_cap( 'manage_options' ) ) {
                    $areas[] = $label;
                    break;
                }
            }
        }

        return array_values( array_unique( $areas ) );
    }
}
