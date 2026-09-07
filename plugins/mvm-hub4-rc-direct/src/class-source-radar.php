<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class MvM_Hub4_Source_Radar {
    public static function summary(): array {
        $overdue = MvM_Hub4_Source_Repository::query( array( 'due' => 'overdue', 'per_page' => 1 ) );
        $today   = MvM_Hub4_Source_Repository::query( array( 'due' => 'today', 'per_page' => 1 ) );
        $week    = MvM_Hub4_Source_Repository::query( array( 'due' => 'week', 'per_page' => 1 ) );
        $all     = MvM_Hub4_Source_Repository::query( array( 'per_page' => 1 ) );

        return array(
            'overdue'  => (int) ( $overdue['total'] ?? 0 ),
            'today'    => (int) ( $today['total'] ?? 0 ),
            'thisWeek' => (int) ( $week['total'] ?? 0 ),
            'total'    => (int) ( $all['total'] ?? 0 ),
        );
    }

    public static function attention( int $limit = 20 ): array {
        $limit = min( 50, max( 1, $limit ) );
        $overdue = MvM_Hub4_Source_Repository::query( array( 'due' => 'overdue', 'per_page' => $limit ) );
        $today   = MvM_Hub4_Source_Repository::query( array( 'due' => 'today', 'per_page' => $limit ) );
        return array(
            'summary' => self::summary(),
            'overdue' => array_values( (array) ( $overdue['items'] ?? array() ) ),
            'today'   => array_values( (array) ( $today['items'] ?? array() ) ),
        );
    }
}
