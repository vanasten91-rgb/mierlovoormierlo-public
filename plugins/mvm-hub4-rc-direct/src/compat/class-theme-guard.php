<?php
/**
 * Fail-closed detection for the transitional Hub theme guard.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class MvM_Hub4_Theme_Guard {
    /**
     * Check whether the active theme explicitly guards its legacy Hub include.
     */
    public static function active_theme_has_hub_guard(): bool {
        $functions_file = trailingslashit( get_stylesheet_directory() ) . 'functions.php';
        if ( ! is_readable( $functions_file ) ) {
            return false;
        }

        $source = file_get_contents( $functions_file );
        if ( ! is_string( $source ) || '' === $source ) {
            return false;
        }

        return self::source_has_hub_guard( $source );
    }

    /**
     * Require the actual mvm-hubs-v3.php include to be inside the route guard.
     * Any uncertainty deliberately returns false so Hub compatibility loading is
     * deferred until after_setup_theme instead of risking a global redeclare.
     */
    public static function source_has_hub_guard( string $source ): bool {
        $guard = "if ( ! function_exists( 'mvm_hubs_v3_routes' ) ) {";
        $guard_at = strpos( $source, $guard );
        if ( false === $guard_at ) {
            return false;
        }

        $block_end = strpos( $source, '}', $guard_at + strlen( $guard ) );
        if ( false === $block_end ) {
            return false;
        }

        $block = substr( $source, $guard_at, $block_end - $guard_at + 1 );
        if ( ! is_string( $block ) ) {
            return false;
        }

        return false !== strpos(
            $block,
            "require_once get_stylesheet_directory() . '/mvm-hubs-v3.php';"
        );
    }
}
