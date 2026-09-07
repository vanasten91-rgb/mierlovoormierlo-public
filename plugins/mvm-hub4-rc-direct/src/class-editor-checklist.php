<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class MvM_Hub4_Editor_Checklist {
    public static function init(): void {
        add_action( 'add_meta_boxes_post', array( __CLASS__, 'register_meta_box' ) );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ), 1210 );
    }

    public static function register_meta_box(): void {
        if ( ! self::can_use_current_post() ) {
            return;
        }

        add_meta_box(
            'mvm-hub4-publication-checklist',
            'MvM publicatiechecklist',
            array( __CLASS__, 'render_meta_box' ),
            'post',
            'side',
            'high'
        );
    }

    public static function render_meta_box( WP_Post $post ): void {
        if ( ! self::can_use_post( (int) $post->ID ) ) {
            return;
        }

        echo '<div class="mvm-hub4-editor-checklist" data-mvm-editor-checklist data-post-id="' . esc_attr( (string) $post->ID ) . '">';
        echo '<p class="mvm-hub4-editor-checklist__intro">Controleer de basis vóór review of publicatie. Afvinken slaat direct op zonder de editor te verversen.</p>';
        echo '<div class="mvm-hub4-editor-checklist__progress" data-mvm-checklist-progress role="status" aria-live="polite">Checklist laden…</div>';
        echo '<div class="mvm-hub4-editor-checklist__items" data-mvm-checklist-items></div>';
        echo '</div>';
    }

    public static function enqueue(): void {
        if ( ! self::can_use_current_post() ) {
            return;
        }

        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        if ( ! $screen || 'post' !== (string) $screen->post_type || ! in_array( (string) $screen->base, array( 'post', 'post-new' ), true ) ) {
            return;
        }

        $post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
        if ( ! $post_id ) {
            return;
        }

        $script = MVM_HUB4_DIR . 'assets/editor-checklist.js';
        $style  = MVM_HUB4_DIR . 'assets/editor-checklist.css';
        if ( is_readable( $style ) ) {
            wp_enqueue_style(
                'mvm-hub4-editor-checklist',
                MVM_HUB4_URL . 'assets/editor-checklist.css',
                array(),
                (string) filemtime( $style )
            );
        }
        if ( ! is_readable( $script ) ) {
            return;
        }

        wp_enqueue_script(
            'mvm-hub4-editor-checklist',
            MVM_HUB4_URL . 'assets/editor-checklist.js',
            array(),
            (string) filemtime( $script ),
            true
        );
        wp_localize_script(
            'mvm-hub4-editor-checklist',
            'MvMHub4ChecklistConfig',
            array(
                'restRoot'  => esc_url_raw( rest_url( 'mvm-hub4/v1/' ) ),
                'restNonce' => wp_create_nonce( 'wp_rest' ),
                'postId'    => $post_id,
            )
        );
    }

    private static function can_use_current_post(): bool {
        if ( ! is_admin() || ! is_user_logged_in() ) {
            return false;
        }

        $post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
        if ( $post_id ) {
            return self::can_use_post( $post_id );
        }

        return current_user_can( MvM_Hub4_Capabilities::CHECKLIST_USE ) || current_user_can( 'manage_options' );
    }

    private static function can_use_post( int $post_id ): bool {
        return current_user_can( MvM_Hub4_Capabilities::CHECKLIST_USE )
            && current_user_can( 'edit_post', $post_id )
            || current_user_can( 'manage_options' );
    }
}
