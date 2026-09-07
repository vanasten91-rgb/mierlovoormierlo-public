<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class MvM_Hub4_Editor_Guard {
    public static function init(): void {
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ), 1200 );
        add_action( 'admin_notices', array( __CLASS__, 'notice' ) );
    }

    public static function enqueue(): void {
        if ( ! self::is_news_editor_screen() ) {
            return;
        }

        $script_path = MVM_HUB4_DIR . 'assets/editor-guard.js';
        $style_path  = MVM_HUB4_DIR . 'assets/editor-guard.css';

        if ( is_readable( $style_path ) ) {
            wp_enqueue_style(
                'mvm-hub4-editor-guard',
                MVM_HUB4_URL . 'assets/editor-guard.css',
                array(),
                (string) filemtime( $style_path )
            );
        }

        if ( ! is_readable( $script_path ) ) {
            return;
        }

        wp_enqueue_script(
            'mvm-hub4-editor-guard',
            MVM_HUB4_URL . 'assets/editor-guard.js',
            array( 'wp-data' ),
            (string) filemtime( $script_path ),
            true
        );

        wp_localize_script(
            'mvm-hub4-editor-guard',
            'MvMHub4EditorGuard',
            array(
                'message' => 'Je hebt nog niet-opgeslagen wijzigingen. Sla het artikel eerst op voordat je deze pagina verlaat of ververst.',
            )
        );
    }

    public static function notice(): void {
        if ( ! self::is_news_editor_screen() ) {
            return;
        }

        echo '<div class="notice mvm-hub4-writing-guard" role="status"><p><strong>Schrijfbeveiliging actief.</strong> Niet-opgeslagen wijzigingen worden beschermd tegen per ongeluk verversen of verlaten. WordPress-autosave blijft actief.</p></div>';
    }

    private static function is_news_editor_screen(): bool {
        if ( ! is_admin() || ! is_user_logged_in() ) {
            return false;
        }

        if ( ! current_user_can( MvM_Hub4_Security::HUB_CAPABILITY ) && ! current_user_can( 'manage_options' ) ) {
            return false;
        }

        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        if ( ! $screen ) {
            return false;
        }

        if ( 'post' !== (string) $screen->post_type ) {
            return false;
        }

        return in_array( (string) $screen->base, array( 'post', 'post-new' ), true );
    }
}
