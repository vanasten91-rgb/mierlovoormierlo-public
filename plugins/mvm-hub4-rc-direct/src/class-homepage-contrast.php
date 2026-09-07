<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Final contrast guard for the MvM-owned community cards on the homepage.
 *
 * Theme layers historically disagreed about the background of the primary
 * card while keeping its foreground white. Keep this source-owned and narrow:
 * no PeepSo, Elementor, PostX or third-party markup is changed.
 */
final class MvM_Hub4_Homepage_Contrast {
    public static function init(): void {
        add_action( 'wp_head', array( __CLASS__, 'render_css' ), PHP_INT_MAX );
    }

    public static function render_css(): void {
        if ( ! is_home() && ! is_front_page() ) {
            return;
        }
        ?>
        <style id="mvm-hub4-homepage-contrast">
        html:not([data-theme="dark"]) body.home:not(.mvm-header-dark) main#content .mvm-theme1-action-card--primary{
            background:#fff!important;
            border:1px solid #d8e2ea!important;
            color:#17202a!important;
            box-shadow:0 8px 24px rgba(23,32,42,.06)!important;
        }
        html:not([data-theme="dark"]) body.home:not(.mvm-header-dark) main#content .mvm-theme1-action-card--primary h3,
        html:not([data-theme="dark"]) body.home:not(.mvm-header-dark) main#content .mvm-theme1-action-card--primary p{
            color:#17202a!important;
            -webkit-text-fill-color:#17202a!important;
        }
        html:not([data-theme="dark"]) body.home:not(.mvm-header-dark) main#content .mvm-theme1-action-card--primary small,
        html:not([data-theme="dark"]) body.home:not(.mvm-header-dark) main#content .mvm-theme1-action-card--primary a{
            color:#1966AE!important;
            -webkit-text-fill-color:#1966AE!important;
        }
        html:not([data-theme="dark"]) body.home:not(.mvm-header-dark) main#content .mvm-theme1-action-card--primary .mvm-theme1-action-icon{
            background:#1966AE!important;
            color:#fff!important;
            -webkit-text-fill-color:#fff!important;
        }
        body.home.mvm-header-dark main#content .mvm-theme1-action-card--primary,
        html[data-theme="dark"] body.home main#content .mvm-theme1-action-card--primary{
            background:#17222d!important;
            border-color:#344757!important;
            color:#f3f7fa!important;
        }
        body.home.mvm-header-dark main#content .mvm-theme1-action-card--primary h3,
        body.home.mvm-header-dark main#content .mvm-theme1-action-card--primary p,
        html[data-theme="dark"] body.home main#content .mvm-theme1-action-card--primary h3,
        html[data-theme="dark"] body.home main#content .mvm-theme1-action-card--primary p{
            color:#f3f7fa!important;
            -webkit-text-fill-color:#f3f7fa!important;
        }
        body.home.mvm-header-dark main#content .mvm-theme1-action-card--primary small,
        body.home.mvm-header-dark main#content .mvm-theme1-action-card--primary a,
        html[data-theme="dark"] body.home main#content .mvm-theme1-action-card--primary small,
        html[data-theme="dark"] body.home main#content .mvm-theme1-action-card--primary a{
            color:#8bc7fb!important;
            -webkit-text-fill-color:#8bc7fb!important;
        }
        </style>
        <?php
    }
}
