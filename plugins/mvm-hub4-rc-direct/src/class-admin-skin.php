<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class MvM_Hub4_Admin_Skin {
    public static function init(): void {
        add_filter( 'admin_body_class', array( __CLASS__, 'body_class' ) );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ), 1000 );
        add_filter( 'admin_footer_text', array( __CLASS__, 'footer_text' ) );
    }

    public static function body_class( string $classes ): string {
        if ( ! self::is_staff_user() ) {
            return $classes;
        }

        $classes .= ' mvm-hub4-admin-skin';

        if ( self::is_complex_editor_screen() ) {
            $classes .= ' mvm-hub4-admin-skin--chrome-only';
        }

        return trim( $classes );
    }

    public static function enqueue(): void {
        if ( ! self::is_staff_user() ) {
            return;
        }

        $filename = self::is_complex_editor_screen() ? 'admin-chrome.css' : 'admin.css';
        $path     = MVM_HUB4_DIR . 'assets/' . $filename;
        if ( ! is_readable( $path ) ) {
            return;
        }

        wp_enqueue_style(
            'mvm-hub4-admin',
            MVM_HUB4_URL . 'assets/' . $filename,
            array(),
            (string) filemtime( $path )
        );
    }

    public static function footer_text( string $text ): string {
        if ( ! self::is_staff_user() ) {
            return $text;
        }

        return '<span class="mvm-hub4-admin-footer">Mierlo voor Mierlo · veilige werkplek</span>';
    }

    private static function is_staff_user(): bool {
        return is_user_logged_in()
            && ( current_user_can( MvM_Hub4_Security::HUB_CAPABILITY ) || current_user_can( 'manage_options' ) );
    }

    private static function is_complex_editor_screen(): bool {
        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        $id     = $screen ? (string) $screen->id : '';
        $page   = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
        $action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';

        if ( 'elementor' === $action || str_contains( $id, 'elementor' ) || str_contains( $page, 'elementor' ) ) {
            return true;
        }

        $complex_markers = array( 'ultp', 'postx', 'wpforo', 'peepso', 'site-kit', 'googlesitekit', 'ultimate-member', 'um-' );
        foreach ( $complex_markers as $marker ) {
            if ( str_contains( $id, $marker ) || str_contains( $page, $marker ) ) {
                return true;
            }
        }

        return false;
    }
}
