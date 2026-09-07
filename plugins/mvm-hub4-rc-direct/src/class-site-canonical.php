<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Canonical public-route hardening discovered during the full-site audit.
 *
 * Keeps legacy inbound URLs useful without reviving superseded forms,
 * dashboards or old PeepSo slugs. wpForo participant routes are deliberately
 * excluded because wpForo still owns that namespace.
 */
final class MvM_Hub4_Site_Canonical {
    public static function init(): void {
        add_action( 'template_redirect', array( __CLASS__, 'redirect_legacy_routes' ), -90 );
        add_filter( 'wp_robots', array( __CLASS__, 'portal_robots' ) );
    }

    public static function redirect_legacy_routes(): void {
        $path = self::request_path();
        if ( '' === $path ) {
            return;
        }

        $redirects = array(
            '/een-evenement-posten/'          => '/organisatoren/',
            '/evenement-dashboard/'           => '/organisatoren/',
            '/organisatorformulier-verzenden/' => '/organisatoren/',
            '/organisator-dashboard/'         => '/organisatoren/',
            '/locatieformulier-verzenden/'    => '/organisatoren/',
            '/locatie-dashboard/'             => '/organisatoren/',
            '/evenement-organisatoren/'       => '/evenementen/',
            '/evenementlocaties/'             => '/evenementen/',
            '/community/'                     => '/activity/',
            '/community-2/'                   => '/activity/',
            '/groups/'                        => '/groepen/',
            '/pages/'                         => '/paginas/',
        );

        $normalized = trailingslashit( $path );
        if ( ! isset( $redirects[ $normalized ] ) ) {
            return;
        }

        nocache_headers();
        wp_safe_redirect( home_url( $redirects[ $normalized ] ), 301, 'MvM canonical route' );
        exit;
    }

    public static function portal_robots( array $robots ): array {
        $path = trailingslashit( self::request_path() );
        if ( in_array( $path, array( '/organisatoren/', '/ondernemers/' ), true ) ) {
            $robots['noindex']   = true;
            $robots['nofollow']  = true;
            $robots['noarchive'] = true;
        }

        return $robots;
    }

    private static function request_path(): string {
        if ( empty( $_SERVER['REQUEST_URI'] ) ) {
            return '';
        }

        $uri  = wp_unslash( (string) $_SERVER['REQUEST_URI'] );
        $path = wp_parse_url( $uri, PHP_URL_PATH );
        return is_string( $path ) ? $path : '';
    }
}
