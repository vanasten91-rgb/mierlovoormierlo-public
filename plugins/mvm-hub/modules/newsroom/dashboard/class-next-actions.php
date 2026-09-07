<?php

namespace MVM\Hub\Modules\Newsroom\Dashboard;

use MVM\Hub\Core\Capabilities;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Generates safe, capability-aware guidance from Today metrics.
 * It never grants access and never replaces endpoint permission checks.
 */
final class Next_Actions {
    /**
     * @param array<string,int> $metrics
     * @return array<int,array<string,mixed>>
     */
    public static function from_metrics( array $metrics ): array {
        $actions = array();

        if (
            (int) ( $metrics['overdueAssignments'] ?? 0 ) > 0
            && Capabilities::can_read( Capabilities::ASSIGNMENTS_VIEW, 'mvm_hub4_assignment_view' )
        ) {
            $actions[] = self::action(
                'assignments_overdue',
                10,
                'Controleer verlopen opdrachten',
                'Werk eerst verlopen opdrachten bij, wijs zo nodig opnieuw toe en leg de nieuwe deadline vast.',
                'assignments'
            );
        }

        if (
            (int) ( $metrics['reviewAssignments'] ?? 0 ) > 0
            && Capabilities::can_read( Capabilities::NEWS_REVIEW, 'mvm_hub4_news_review' )
        ) {
            $actions[] = self::action(
                'review_queue',
                20,
                'Beoordeel stukken in review',
                'Controleer bron, feiten, beeld en Smart Links-context voordat je gereed of aanpassing gevraagd kiest.',
                'news'
            );
        }

        if (
            (int) ( $metrics['signalsNeedingTriage'] ?? 0 ) > 0
            && Capabilities::can_read( Capabilities::RADAR_VIEW, 'mvm_hub4_signal_view' )
        ) {
            $actions[] = self::action(
                'signals_triage',
                30,
                'Beoordeel nieuwe signalen',
                'Controleer lokale relevantie en bronbetrouwbaarheid; koppel daarna aan dossier of opdracht, of archiveer als niet relevant.',
                'radar'
            );
        }

        if (
            (int) ( $metrics['correctionsWaiting'] ?? 0 ) > 0
            && Capabilities::can_read( Capabilities::CORRECTIONS_VIEW, 'mvm_hub4_correction_view' )
        ) {
            $actions[] = self::action(
                'corrections_waiting',
                15,
                'Behandel open correcties',
                'Koppel de melding aan het artikel, bepaal ernst en eigenaar en publiceer pas na inhoudelijke controle een correctienotitie.',
                'corrections'
            );
        }

        if (
            (int) ( $metrics['mediaNeedsAction'] ?? 0 ) > 0
            && Capabilities::can_read( Capabilities::MEDIA_VIEW, 'mvm_hub4_media_view' )
        ) {
            $actions[] = self::action(
                'media_action',
                40,
                'Controleer ontbrekend of nieuw beeld',
                'Verifieer bron, rechten, credits, alttekst en eventuele toestemming voordat beeld aan publicatie wordt gekoppeld.',
                'media'
            );
        }

        if (
            (int) ( $metrics['sourcesNeedingCheck'] ?? 0 ) > 0
            && Capabilities::can_read( Capabilities::SOURCES_VIEW, 'mvm_hub4_sources_view' )
        ) {
            $actions[] = self::action(
                'sources_check',
                50,
                'Controleer bronnen die aandacht nodig hebben',
                'Controleer bereikbaarheid en betrouwbaarheid zonder private bronnotities in het dashboard te tonen.',
                'sources'
            );
        }

        usort(
            $actions,
            static fn( array $left, array $right ): int => (int) $left['priority'] <=> (int) $right['priority']
        );

        return array_slice( $actions, 0, 6 );
    }

    /** @return array<string,mixed> */
    private static function action( string $id, int $priority, string $title, string $instruction, string $module ): array {
        return array(
            'id'          => sanitize_key( $id ),
            'priority'    => $priority,
            'title'       => $title,
            'instruction' => $instruction,
            'module'      => sanitize_key( $module ),
        );
    }

    private function __construct() {}
}
