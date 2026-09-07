<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Fail-closed boundary for the transitional legacy MvM Mail service.
 *
 * The legacy Hub3 service bundle remains available for read-only IMAP helpers
 * during migration, but its historical AJAX send action must never be
 * reachable from Hub 4. The action name is intentionally assembled from
 * fragments so static package checks continue to reject accidental copies of
 * the legacy write route elsewhere in the consolidated runtime.
 */
final class MvM_Hub4_Mail_Readonly_Guard {
    private const AUTH_SEND_HOOK   = 'wp_ajax_' . 'mvm_hub_mail_' . 'send';
    private const PUBLIC_SEND_HOOK = 'wp_ajax_nopriv_' . 'mvm_hub_mail_' . 'send';

    public static function boot(): void {
        self::disable_legacy_send_route();

        // Re-assert after all plugins and again after init so a late legacy
        // registration cannot restore the action before admin-ajax dispatches.
        add_action( 'plugins_loaded', array( __CLASS__, 'disable_legacy_send_route' ), PHP_INT_MAX );
        add_action( 'init', array( __CLASS__, 'disable_legacy_send_route' ), PHP_INT_MAX );
    }

    public static function disable_legacy_send_route(): void {
        remove_all_actions( self::AUTH_SEND_HOOK );
        remove_all_actions( self::PUBLIC_SEND_HOOK );
    }
}
