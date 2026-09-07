<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Contact page contrast guard.
 * Keeps the blue hero strip readable without changing the rest of Elementor.
 */
final class MvM_Hub4_Contact_Theme {
    private const CONTACT_PAGE_ID = 140;

    public static function init(): void {
        add_action( 'wp_head', array( __CLASS__, 'render_css' ), PHP_INT_MAX );
    }

    public static function render_css(): void {
        if ( ! is_page( self::CONTACT_PAGE_ID ) ) {
            return;
        }
        ?>
        <style id="mvm-hub4-contact-theme">
        body.elementor-page-140 .elementor-element-c0a1b2c3,
        body.elementor-page-140 .elementor-element-c0a1b2c3 .elementor-heading-title,
        body.elementor-page-140 .elementor-element-c0a1b2c3 .elementor-widget-text-editor,
        body.elementor-page-140 .elementor-element-c0a1b2c3 .elementor-widget-text-editor p{
            color:#fff!important;
            -webkit-text-fill-color:#fff!important;
        }
        </style>
        <?php
    }
}
