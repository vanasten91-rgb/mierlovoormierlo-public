<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class MvM_Hub4_Contrast {
    private const HANDLE = 'mvm-hub4-contrast-v1';
    private const UI_HANDLE = 'mvm-hub4-ui-contract-v1';
    private const SITE_HANDLE = 'mvm-hub4-sitewide-v1';
    private const ENCYCLOPEDIA_DETAIL_HANDLE = 'mvm-hub4-encyclopedia-hub12-detail';

    public static function init(): void {
        add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ), PHP_INT_MAX );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ), PHP_INT_MAX );
    }

    public static function enqueue(): void {
        wp_enqueue_style(
            self::HANDLE,
            MVM_HUB4_URL . 'assets/contrast-v1.css',
            array(),
            MVM_HUB4_VERSION
        );

        wp_enqueue_style(
            self::UI_HANDLE,
            MVM_HUB4_URL . 'assets/ui-contract-v1.css',
            array( self::HANDLE ),
            MVM_HUB4_VERSION
        );

        if ( 'wp_enqueue_scripts' === current_filter() ) {
            $sitewide = MVM_HUB4_DIR . 'assets/mvm-sitewide-v1.css';
            wp_enqueue_style(
                self::SITE_HANDLE,
                MVM_HUB4_URL . 'assets/mvm-sitewide-v1.css',
                array( self::UI_HANDLE ),
                is_readable( $sitewide ) ? (string) filemtime( $sitewide ) : MVM_HUB4_VERSION
            );

            if ( self::is_encyclopedia_request() ) {
                $detail = MVM_HUB4_DIR . 'assets/encyclopedia-hub12-detail.css';
                wp_enqueue_style(
                    self::ENCYCLOPEDIA_DETAIL_HANDLE,
                    MVM_HUB4_URL . 'assets/encyclopedia-hub12-detail.css',
                    array( self::SITE_HANDLE ),
                    is_readable( $detail ) ? (string) filemtime( $detail ) : MVM_HUB4_VERSION
                );
            }
        }
    }

    private static function is_encyclopedia_request(): bool {
        $path = untrailingslashit( (string) wp_parse_url( (string) ( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH ) );
        $root = untrailingslashit( (string) wp_parse_url( home_url( '/encyclopedie/' ), PHP_URL_PATH ) );
        return $root !== '' && ( $path === $root || str_starts_with( $path, $root . '/' ) );
    }
}
