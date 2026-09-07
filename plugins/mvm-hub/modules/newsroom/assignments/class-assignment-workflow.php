<?php

namespace MVM\Hub\Modules\Newsroom\Assignments;

use MVM\Hub\Core\Capabilities;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/** Canonical assignment workflow for the rebuilt Newsroom. */
final class Assignment_Workflow {
    private const TRANSITIONS = array(
        'new' => array(
            'assigned'  => Capabilities::ASSIGNMENTS_MANAGE,
            'cancelled' => Capabilities::ASSIGNMENTS_MANAGE,
        ),
        'assigned' => array(
            'in_progress' => Capabilities::ASSIGNMENTS_VIEW,
            'cancelled'   => Capabilities::ASSIGNMENTS_MANAGE,
        ),
        'in_progress' => array(
            'assigned'  => Capabilities::ASSIGNMENTS_MANAGE,
            'review'    => Capabilities::ASSIGNMENTS_VIEW,
            'cancelled' => Capabilities::ASSIGNMENTS_MANAGE,
        ),
        'review' => array(
            'in_progress' => Capabilities::ASSIGNMENTS_MANAGE,
            'completed'   => Capabilities::ASSIGNMENTS_MANAGE,
            'cancelled'   => Capabilities::ASSIGNMENTS_MANAGE,
        ),
        'completed' => array(),
        'cancelled' => array(),
    );

    /** @return array<string,string> */
    public static function transitions_from( string $state ): array {
        return self::TRANSITIONS[ sanitize_key( $state ) ] ?? array();
    }

    public static function required_capability( string $from, string $to ): ?string {
        return self::TRANSITIONS[ sanitize_key( $from ) ][ sanitize_key( $to ) ] ?? null;
    }

    public static function can_transition( string $from, string $to ): bool {
        $capability = self::required_capability( $from, $to );
        return null !== $capability
            && ( current_user_can( 'manage_options' ) || current_user_can( $capability ) );
    }

    public static function from_legacy_status( string $status ): ?string {
        return match ( sanitize_key( $status ) ) {
            'signal'      => 'new',
            'assigned'    => 'assigned',
            'in_progress' => 'in_progress',
            'review'      => 'review',
            'ready', 'scheduled', 'published' => 'completed',
            'cancelled'   => 'cancelled',
            default       => null,
        };
    }

    private function __construct() {}
}
