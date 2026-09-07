<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Suppresses the legacy lower PeepSo event-comments renderer.
 *
 * The adopted event bridge checks for data-mvm-peepso-event-comments="1"
 * before injecting its fallback section into the final HTML. Hub 1.1 already
 * owns the proven upper PeepSo BlogPosts component, so it publishes that
 * compatibility marker early and lets the legacy bridge skip its duplicate.
 * PeepSo configuration and event data remain untouched.
 */
final class MvM_Hub4_PeepSo_Event_Dedupe {
    public static function init(): void {
        add_action( 'wp_head', array( __CLASS__, 'render_legacy_suppression_marker' ), -10000 );
    }

    public static function render_legacy_suppression_marker(): void {
        if ( is_admin() || wp_doing_ajax() || ! is_singular( 'event_listing' ) ) {
            return;
        }

        echo '<meta name="mvm-peepso-event-comments-owner" content="hub4" data-mvm-peepso-event-comments="1">';
    }
}
