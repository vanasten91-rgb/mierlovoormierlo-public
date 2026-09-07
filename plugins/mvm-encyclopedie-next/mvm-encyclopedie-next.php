<?php
/**
 * Plugin Name: MvM Digitale Encyclopedie Next
 * Description: Zelfstandige, read-only publieke runtime voor de Digitale Encyclopedie van Mierlo.
 * Version: 3.0.0-alpha4
 * Author: Mierlo voor Mierlo
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'MVM_ENCYCLOPEDIE_NEXT_VERSION', '3.0.0-alpha4' );
define( 'MVM_ENCYCLOPEDIE_NEXT_FILE', __FILE__ );
define( 'MVM_ENCYCLOPEDIE_NEXT_DIR', plugin_dir_path( __FILE__ ) );
define( 'MVM_ENCYCLOPEDIE_NEXT_URL', plugin_dir_url( __FILE__ ) );

require_once MVM_ENCYCLOPEDIE_NEXT_DIR . 'src/class-content-model.php';
require_once MVM_ENCYCLOPEDIE_NEXT_DIR . 'src/class-query.php';
require_once MVM_ENCYCLOPEDIE_NEXT_DIR . 'src/class-visibility.php';
require_once MVM_ENCYCLOPEDIE_NEXT_DIR . 'src/class-hotlinks.php';
require_once MVM_ENCYCLOPEDIE_NEXT_DIR . 'src/class-frontend.php';
require_once MVM_ENCYCLOPEDIE_NEXT_DIR . 'src/class-theme-groups.php';
require_once MVM_ENCYCLOPEDIE_NEXT_DIR . 'src/class-page-template.php';

/**
 * Standalone runtime.
 *
 * This replaces the historical `final class MVM_Encyclopedie` declaration as
 * the actual hook owner while retaining that public marker through a safe alias.
 * WordPress activates a newly installed plugin inside a request where the active
 * theme and all currently active plugins have already loaded. Older MvM runtimes
 * may therefore already own the historical MVM_Encyclopedie class name during
 * that activation request. Reusing that global name here would fatally redeclare
 * the class.
 */
final class MVM_Encyclopedie_Next_Runtime {
    public static function boot(): void {
        MVM_Encyclopedie_Next_Content_Model::init();
        MVM_Encyclopedie_Next_Query::init();
        MVM_Encyclopedie_Next_Visibility::init();
        MVM_Encyclopedie_Next_Hotlinks::init();
        MVM_Encyclopedie_Next_Frontend::init();
        MVM_Encyclopedie_Next_Theme_Groups::init();
        MVM_Encyclopedie_Next_Page_Template::init();
        add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_hotlink_emphasis' ), 20 );
    }

    public static function enqueue_hotlink_emphasis(): void {
        if ( ! MVM_Encyclopedie_Next_Frontend::is_surface() ) {
            return;
        }

        wp_add_inline_style(
            'mvm-encyclopedie-next',
            '.mvm-encyclopedie-next a.mvm-e3-hotlink{font-weight:800;text-decoration-thickness:2px;text-underline-offset:3px;}'
        );
    }

    public static function activate(): void {
        MVM_Encyclopedie_Next_Content_Model::register_all();
        flush_rewrite_rules( false );
        update_option( 'mvm_encyclopedie_next_rewrite_version', MVM_ENCYCLOPEDIE_NEXT_VERSION, false );
    }

    public static function deactivate(): void {
        flush_rewrite_rules( false );
    }

    /**
     * Publish the historical marker only after the active theme has loaded.
     *
     * Hub rc6 checks class_exists( 'MVM_Encyclopedie', false ) during its init
     * callbacks. Creating the alias after_setup_theme keeps that hand-off intact
     * without stealing the class name from a legacy theme/plugin that already
     * owns it. If an older runtime exists, Next still boots independently while
     * the Hub compatibility layer remains dormant because the marker exists.
     */
    public static function ensure_legacy_marker(): void {
        if ( class_exists( 'MVM_Encyclopedie', false ) ) {
            return;
        }

        class_alias( __CLASS__, 'MVM_Encyclopedie' );
    }
}

add_action( 'plugins_loaded', array( 'MVM_Encyclopedie_Next_Runtime', 'boot' ), 1 );
add_action( 'after_setup_theme', array( 'MVM_Encyclopedie_Next_Runtime', 'ensure_legacy_marker' ), PHP_INT_MAX );
register_activation_hook( __FILE__, array( 'MVM_Encyclopedie_Next_Runtime', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'MVM_Encyclopedie_Next_Runtime', 'deactivate' ) );
