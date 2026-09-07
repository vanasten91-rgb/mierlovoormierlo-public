<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Loads the shared MvM platform as an internal Hub subsystem.
 *
 * Release packages contain modules/mvm-platform inside this plugin. The
 * sibling plugins/mvm-platform directory is accepted only for repository CI
 * and development checkouts, so production has one installable plugin.
 */
final class MvM_Hub4_Integrated_Platform {
    private static bool $loaded = false;
    private static string $source = 'missing';

    public static function load(): bool {
        if ( self::$loaded || class_exists( 'MvM_Platform_Module', false ) ) {
            self::$loaded = true;
            return true;
        }

        $bundled = trailingslashit( MVM_HUB4_DIR . 'modules/mvm-platform' );
        $sibling = trailingslashit( dirname( untrailingslashit( MVM_HUB4_DIR ) ) . '/mvm-platform' );
        $module_dir = '';

        if ( is_readable( $bundled . 'bootstrap.php' ) ) {
            $module_dir = $bundled;
            self::$source = 'bundled';
        } elseif ( is_readable( $sibling . 'bootstrap.php' ) ) {
            $module_dir = $sibling;
            self::$source = 'development-sibling';
        }

        if ( '' === $module_dir ) {
            return false;
        }

        if ( ! defined( 'MVM_PLATFORM_VERSION' ) ) {
            define( 'MVM_PLATFORM_VERSION', '0.6.0' );
        }
        if ( ! defined( 'MVM_PLATFORM_FILE' ) ) {
            define( 'MVM_PLATFORM_FILE', $module_dir . 'bootstrap.php' );
        }
        if ( ! defined( 'MVM_PLATFORM_DIR' ) ) {
            define( 'MVM_PLATFORM_DIR', $module_dir );
        }
        if ( ! defined( 'MVM_PLATFORM_URL' ) ) {
            $url = 'bundled' === self::$source
                ? MVM_HUB4_URL . 'modules/mvm-platform/'
                : plugins_url( 'mvm-platform/' );
            define( 'MVM_PLATFORM_URL', trailingslashit( $url ) );
        }

        require_once $module_dir . 'bootstrap.php';
        self::$loaded = class_exists( 'MvM_Platform_Module', false );
        return self::$loaded;
    }

    public static function activate(): void {
        if ( ! self::load() ) {
            wp_die(
                esc_html__( 'Hub 1.1 mist de geïntegreerde MvM-platformmodule. Installeer de volledige release-ZIP.', 'mvm-hub4' ),
                esc_html__( 'Onvolledige Hub-release', 'mvm-hub4' ),
                array( 'response' => 500 )
            );
        }
        MvM_Platform_Module::activate();
    }

    public static function deactivate(): void {
        if ( self::load() ) {
            MvM_Platform_Module::deactivate();
        }
    }

    public static function boot(): void {
        if ( self::load() ) {
            MvM_Platform_Module::boot();
        }
    }

    public static function status(): array {
        return array(
            'loaded'  => self::load(),
            'source'  => self::$source,
            'version' => defined( 'MVM_PLATFORM_VERSION' ) ? MVM_PLATFORM_VERSION : '',
        );
    }
}
