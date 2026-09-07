<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Makes /hub/ the canonical Hub 4 application route without taking ownership
 * of the legacy unauthenticated staff gateway yet.
 *
 * Hub 3 remains loaded for legacy services (crawler, mail, encyclopaedia,
 * event bridge, forum and public modules) until their individual migration
 * gates are complete.
 */
final class MvM_Hub4_Canonical_Hub {
    public static function init(): void {
        add_action( 'template_redirect', array( __CLASS__, 'route' ), -100 );
    }

    public static function route(): void {
        $path = self::request_path();
        if ( '' === $path ) {
            return;
        }

        if ( self::paths_equal( $path, self::hub4_path() ) ) {
            nocache_headers();
            wp_safe_redirect( home_url( '/hub/' ), 302, 'MvM Hub 4' );
            exit;
        }

        if ( ! self::paths_equal( $path, self::hub_path() ) ) {
            return;
        }

        // The legacy gateway remains the unauthenticated login boundary until
        // Hub 4 owns the complete login/reset/reCAPTCHA flow itself.
        if ( ! is_user_logged_in() ) {
            return;
        }

        // Force the already hardened Hub 4 route pipeline. That pipeline still
        // performs capability checks, browser-bound session validation/touch,
        // audit logging and response security headers before rendering.
        set_query_var( 'mvm_hub4', '1' );
        MvM_Hub4_App::render_if_requested();
    }

    private static function request_path(): string {
        if ( empty( $_SERVER['REQUEST_URI'] ) ) {
            return '';
        }

        $uri  = wp_unslash( (string) $_SERVER['REQUEST_URI'] );
        $path = wp_parse_url( $uri, PHP_URL_PATH );
        return is_string( $path ) ? $path : '';
    }

    private static function hub_path(): string {
        $path = wp_parse_url( home_url( '/hub/' ), PHP_URL_PATH );
        return is_string( $path ) ? $path : '/hub/';
    }

    private static function hub4_path(): string {
        $path = wp_parse_url( home_url( '/hub4/' ), PHP_URL_PATH );
        return is_string( $path ) ? $path : '/hub4/';
    }

    private static function paths_equal( string $left, string $right ): bool {
        return untrailingslashit( $left ) === untrailingslashit( $right );
    }
}
