<?php

namespace MVM\Hub\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Capability-aware secondary navigation for Hub workspaces.
 *
 * This registry is presentation metadata only. Endpoint and object-level
 * authorization remain mandatory regardless of whether a link is visible.
 */
final class Workspace_Sections {
    /** @return array<string,array<string,array<string,string|int>>> */
    public static function all(): array {
        return array(
            'newsroom' => array(
                'today' => array( 'label' => 'Vandaag', 'path' => 'nieuwsroom/vandaag/', 'priority' => 10 ),
                'news' => array( 'label' => 'Nieuws', 'path' => 'nieuwsroom/nieuws/', 'priority' => 20 ),
                'assignments' => array( 'label' => 'Opdrachten', 'path' => 'nieuwsroom/opdrachten/', 'priority' => 30 ),
                'radar' => array( 'label' => 'Radar', 'path' => 'nieuwsroom/radar/', 'priority' => 40 ),
                'sources' => array( 'label' => 'Bronnen', 'path' => 'nieuwsroom/bronnen/', 'priority' => 50 ),
                'agenda' => array( 'label' => 'Agenda', 'path' => 'nieuwsroom/agenda/', 'priority' => 60 ),
                'media' => array( 'label' => 'Media', 'path' => 'nieuwsroom/media/', 'priority' => 70 ),
                'dossiers' => array( 'label' => 'Dossiers', 'path' => 'nieuwsroom/dossiers/', 'priority' => 80 ),
                'corrections' => array( 'label' => 'Correcties', 'path' => 'nieuwsroom/correcties/', 'priority' => 90 ),
                'distribution' => array( 'label' => 'Distributie', 'path' => 'nieuwsroom/distributie/', 'priority' => 100 ),
                'team' => array( 'label' => 'Team', 'path' => 'nieuwsroom/team/', 'priority' => 110 ),
            ),
            'communications' => array(
                'messages' => array( 'label' => 'Interne berichten', 'path' => 'communicatie/berichten/', 'priority' => 10 ),
                'mail' => array( 'label' => 'Mail', 'path' => 'communicatie/mail/', 'priority' => 20 ),
            ),
        );
    }

    public static function can_access( string $workspace, string $section ): bool {
        $workspace = sanitize_key( $workspace );
        $section   = sanitize_key( $section );

        if ( ! Workspaces::can_access( $workspace ) ) {
            return false;
        }

        if ( 'newsroom' === $workspace ) {
            return match ( $section ) {
                'today'        => Capabilities::can_access_newsroom(),
                'news'         => self::can_access_news(),
                'assignments'  => Capabilities::can_read( Capabilities::ASSIGNMENTS_VIEW, 'mvm_hub4_assignment_view' ),
                'radar'        => Capabilities::can_read( Capabilities::RADAR_VIEW, 'mvm_hub4_signal_view' ),
                'sources'      => Capabilities::can_read( Capabilities::SOURCES_VIEW, 'mvm_hub4_sources_view' ),
                'agenda'       => Capabilities::can_read( Capabilities::AGENDA_VIEW, 'mvm_hub4_calendar_view' ),
                'media'        => Capabilities::can_read( Capabilities::MEDIA_VIEW, 'mvm_hub4_media_view' ),
                'dossiers'     => Capabilities::can_read( Capabilities::DOSSIERS_VIEW, 'mvm_hub4_dossier_view' ),
                'corrections'  => Capabilities::can_read( Capabilities::CORRECTIONS_VIEW, 'mvm_hub4_correction_view' ),
                'distribution' => Capabilities::can_read( Capabilities::DISTRIBUTION_VIEW, 'mvm_hub4_distribution_view' ),
                'team'         => Capabilities::can_read( Capabilities::TEAM_VIEW, 'mvm_hub4_team_view' ),
                default        => false,
            };
        }

        if ( 'communications' === $workspace ) {
            return match ( $section ) {
                'messages' => current_user_can( 'manage_options' )
                    || current_user_can( Capabilities::COMMUNICATIONS_ADMIN )
                    || current_user_can( Capabilities::MESSAGES_ACCESS ),
                'mail' => Capabilities::can_access_mail(),
                default => false,
            };
        }

        return false;
    }

    /** @return array<string,array<string,string|int>> */
    public static function available( string $workspace ): array {
        $workspace = sanitize_key( $workspace );
        $all       = self::all()[ $workspace ] ?? array();
        $available = array_filter(
            $all,
            static fn( array $config, string $section ): bool => self::can_access( $workspace, $section ),
            ARRAY_FILTER_USE_BOTH
        );

        uasort(
            $available,
            static fn( array $left, array $right ): int => (int) $left['priority'] <=> (int) $right['priority']
        );

        return $available;
    }

    public static function default_section( string $workspace ): ?string {
        foreach ( array_keys( self::available( $workspace ) ) as $section ) {
            return (string) $section;
        }
        return null;
    }

    private static function can_access_news(): bool {
        return current_user_can( 'manage_options' )
            || current_user_can( Capabilities::NEWS_CREATE )
            || current_user_can( Capabilities::NEWS_EDIT_OWN )
            || current_user_can( Capabilities::NEWS_EDIT_TEAM )
            || current_user_can( Capabilities::NEWS_REVIEW )
            || current_user_can( Capabilities::NEWS_PUBLISH )
            || current_user_can( 'mvm_hub4_news_view' );
    }

    private function __construct() {}
}
