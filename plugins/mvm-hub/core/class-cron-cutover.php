<?php

namespace MVM\Hub\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Executes the final recurring-job handoff inside the signed decommission
 * promotion request. Cron_Ownership remains the implementation owner; this
 * class only switches callbacks once the release gate is live.
 */
final class Cron_Cutover {
    private static bool $activated = false;

    public static function activate(): bool {
        if ( self::$activated ) {
            return Runtime_Gates::cron_takeover_enabled();
        }
        if ( ! Runtime_Gates::cron_takeover_enabled() || ! Legacy_Services::persistent_ready() ) {
            return false;
        }

        remove_all_actions( Cron_Ownership::NEWSRADAR_HOOK );
        remove_all_actions( Cron_Ownership::NEWSRADAR_MANUAL_HOOK );
        remove_all_actions( Cron_Ownership::AI_AGENDA_HOOK );
        remove_all_actions( Cron_Ownership::AUDIT_HOOK );

        add_action( Cron_Ownership::NEWSRADAR_HOOK, array( Cron_Ownership::class, 'run_due_newsradar_source' ) );
        add_action( Cron_Ownership::NEWSRADAR_MANUAL_HOOK, array( Cron_Ownership::class, 'run_manual_newsradar_source' ), 10, 2 );
        add_action( Cron_Ownership::AI_AGENDA_HOOK, array( Cron_Ownership::class, 'run_ai_agenda' ) );
        add_action( Cron_Ownership::AUDIT_HOOK, array( Cron_Ownership::class, 'cleanup_audit' ) );

        Cron_Ownership::ensure_schedules();
        self::$activated = true;
        return true;
    }

    private function __construct() {}
}
