<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * MvM-owned forum presentation only.
 *
 * Keeps wpForo itself untouched while aligning the public forum with the
 * regular MvM page width and removing the custom MvM new-topic action bar.
 */
final class MvM_Hub4_Forum_Layout {
    public static function init(): void {
        add_action( 'wp_head', array( __CLASS__, 'render_css' ), PHP_INT_MAX );
    }

    public static function render_css(): void {
        if ( ! self::is_forum_request() ) {
            return;
        }
        ?>
        <style id="mvm-hub4-forum-layout">
        body.is_wpforo_page-1 .mvm-forum-actions,
        body.is_wpforo_url-1 .mvm-forum-actions{
            display:none!important;
            margin:0!important;
            padding:0!important;
            height:0!important;
            min-height:0!important;
            overflow:hidden!important;
        }
        body.is_wpforo_page-1 #wpforo,
        body.is_wpforo_url-1 #wpforo,
        body.is_wpforo_page-1 #wpforo-wrap,
        body.is_wpforo_url-1 #wpforo-wrap{
            width:100%!important;
            max-width:1152px!important;
            margin-left:auto!important;
            margin-right:auto!important;
            box-sizing:border-box!important;
        }
        body.is_wpforo_page-1 #wpforo,
        body.is_wpforo_url-1 #wpforo{
            padding-left:24px!important;
            padding-right:24px!important;
        }
        @media(max-width:767px){
            body.is_wpforo_page-1 #wpforo,
            body.is_wpforo_url-1 #wpforo{
                padding-left:14px!important;
                padding-right:14px!important;
            }
        }
        </style>
        <?php
    }

    private static function is_forum_request(): bool {
        if ( function_exists( 'is_wpforo_page' ) && is_wpforo_page() ) {
            return true;
        }
        $path = untrailingslashit( (string) wp_parse_url( (string) ( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH ) );
        $root = untrailingslashit( (string) wp_parse_url( home_url( '/forum/' ), PHP_URL_PATH ) );
        return $root !== '' && ( $path === $root || str_starts_with( $path, $root . '/' ) );
    }
}
