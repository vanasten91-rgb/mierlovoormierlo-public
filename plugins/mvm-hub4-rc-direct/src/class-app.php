<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class MvM_Hub4_App {
    private const QUERY_VAR        = 'mvm_hub4';
    private const SYSTEM_QUERY_VAR = 'mvm_hub4_system_overview';
    private static ?string $csp_nonce = null;

    public static function init(): void {
        add_action( 'init', array( __CLASS__, 'register_route' ) );
        add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
        add_action( 'template_redirect', array( __CLASS__, 'render_if_requested' ), 0 );
    }

    public static function activate(): void {
        self::register_route();
        flush_rewrite_rules( false );
    }

    public static function register_route(): void {
        add_rewrite_rule( '^hub4/?$', 'index.php?' . self::QUERY_VAR . '=1', 'top' );
        add_rewrite_rule( '^hub4/overzicht/?$', 'index.php?' . self::SYSTEM_QUERY_VAR . '=1', 'top' );
    }

    public static function query_vars( array $vars ): array {
        $vars[] = self::QUERY_VAR;
        $vars[] = self::SYSTEM_QUERY_VAR;
        return $vars;
    }

    public static function is_request(): bool {
        return '1' === (string) get_query_var( self::QUERY_VAR );
    }

    public static function is_system_overview_request(): bool {
        return '1' === (string) get_query_var( self::SYSTEM_QUERY_VAR );
    }

    public static function csp_nonce(): string {
        if ( null === self::$csp_nonce ) {
            try {
                self::$csp_nonce = rtrim( strtr( base64_encode( random_bytes( 18 ) ), '+/', '-_' ), '=' );
            } catch ( Throwable $error ) {
                self::$csp_nonce = wp_generate_password( 24, false, false );
            }
        }
        return self::$csp_nonce;
    }

    public static function render_if_requested(): void {
        if ( ! self::is_request() && ! self::is_system_overview_request() ) {
            return;
        }

        if ( ! is_user_logged_in() ) {
            wp_safe_redirect( home_url( '/hub/' ) );
            exit;
        }

        if ( ! MvM_Hub4_Security::can_access_hub() ) {
            MvM_Hub4_Audit::log( 'security.hub4_route_denied', 'denied', array( 'object_type' => 'hub' ) );
            status_header( 403 );
            nocache_headers();
            wp_die( esc_html__( 'Je hebt geen toegang tot de MvM Hub.', 'mvm-hub4' ), esc_html__( 'Geen toegang', 'mvm-hub4' ), array( 'response' => 403 ) );
        }

        if ( self::is_system_overview_request() && ! self::can_view_system_overview() ) {
            MvM_Hub4_Audit::log( 'security.system_overview_denied', 'denied', array( 'object_type' => 'system_overview' ) );
            status_header( 403 );
            nocache_headers();
            wp_die( esc_html__( 'Je hebt geen toegang tot het systeemoverzicht.', 'mvm-hub4' ), esc_html__( 'Geen toegang', 'mvm-hub4' ), array( 'response' => 403 ) );
        }

        $session = MvM_Hub4_Session::validate_and_touch();
        if ( true !== $session ) {
            wp_safe_redirect( home_url( '/hub/' ) );
            exit;
        }

        self::send_security_headers();

        if ( self::is_system_overview_request() ) {
            MvM_Hub4_Audit::log( 'system.overview_view', 'success', array( 'object_type' => 'system_overview' ) );
            self::include_template( 'system-overview.php', 'De template voor het systeemoverzicht ontbreekt.' );
        }

        MvM_Hub4_Audit::log( 'hub.session_open', 'success', array( 'object_type' => 'hub' ) );
        self::include_template( 'app.php', 'De Hub 4-template ontbreekt.' );
    }

    public static function app_config(): array {
        return array(
            'restRoot'          => esc_url_raw( rest_url( 'mvm-hub4/v1/' ) ),
            'publicRestRoot'    => esc_url_raw( rest_url( 'mvm-public/v1/' ) ),
            'platformRestRoot'  => esc_url_raw( rest_url( 'mvm/v1/' ) ),
            'restNonce'         => wp_create_nonce( 'wp_rest' ),
            'baseUrl'           => esc_url_raw( home_url( '/hub4/' ) ),
            'systemOverviewUrl' => esc_url_raw( home_url( '/hub4/overzicht/' ) ),
            'loginUrl'          => esc_url_raw( home_url( '/hub/' ) ),
            'brand'             => 'Mierlo voor Mierlo',
            'session'           => MvM_Hub4_Session::status(),
            'platformVersion'   => MVM_HUB4_VERSION,
        );
    }

    public static function navigation_base(): array {
        return array(
            array( 'id' => 'today', 'label' => 'Vandaag', 'cap' => MvM_Hub4_Security::HUB_CAPABILITY ),
            array( 'id' => 'news', 'label' => 'Nieuws', 'cap' => MvM_Hub4_Capabilities::NEWS_VIEW ),
            array( 'id' => 'sources', 'label' => 'Bronnen', 'cap' => MvM_Hub4_Capabilities::SOURCE_VIEW ),
            array( 'id' => 'agenda', 'label' => 'Agenda', 'cap' => MvM_Hub4_Capabilities::AGENDA_VIEW ),
            array( 'id' => 'team', 'label' => 'Team', 'cap' => MvM_Hub4_Capabilities::TEAM_VIEW ),
            array( 'id' => 'more', 'label' => 'Meer', 'cap' => MvM_Hub4_Security::HUB_CAPABILITY ),
        );
    }

    public static function navigation(): array {
        $allowed = array_values(
            array_filter(
                self::navigation_base(),
                static function ( array $item ): bool {
                    return current_user_can( $item['cap'] ) || current_user_can( 'manage_options' );
                }
            )
        );

        $order = class_exists( 'MvM_Hub4_Workflow_Guide' )
            ? MvM_Hub4_Workflow_Guide::navigation_order()
            : array_column( self::navigation_base(), 'id' );

        $rank = array_flip( $order );
        usort(
            $allowed,
            static function ( array $a, array $b ) use ( $rank ): int {
                $a_rank = $rank[ $a['id'] ] ?? 999;
                $b_rank = $rank[ $b['id'] ] ?? 999;
                return $a_rank <=> $b_rank;
            }
        );

        return $allowed;
    }

    public static function platform_navigation(): array {
        $base = array(
            array( 'id' => 'dashboard', 'label' => 'Dashboard', 'cap' => MvM_Hub4_Capabilities::DASHBOARD_VIEW ),
            array( 'id' => 'signals', 'label' => 'Signalen', 'cap' => MvM_Hub4_Capabilities::SIGNAL_VIEW ),
            array( 'id' => 'dossiers', 'label' => 'Dossiers', 'cap' => MvM_Hub4_Capabilities::DOSSIER_VIEW ),
            array( 'id' => 'calendar', 'label' => 'Kalender', 'cap' => MvM_Hub4_Capabilities::CALENDAR_VIEW ),
            array( 'id' => 'media', 'label' => 'Media', 'cap' => MvM_Hub4_Capabilities::MEDIA_VIEW ),
            array( 'id' => 'distribution', 'label' => 'Distributie', 'cap' => MvM_Hub4_Capabilities::DISTRIBUTION_VIEW ),
            array( 'id' => 'corrections', 'label' => 'Correcties', 'cap' => MvM_Hub4_Capabilities::CORRECTION_VIEW ),
            array( 'id' => 'radar', 'label' => 'Mierlo-radar', 'cap' => MvM_Hub4_Capabilities::SOURCE_VIEW ),
        );

        $allowed = array_values(
            array_filter(
                $base,
                static fn( array $item ): bool => current_user_can( $item['cap'] ) || current_user_can( 'manage_options' )
            )
        );

        $role_home = class_exists( 'MvM_Hub4_Dashboard' ) ? MvM_Hub4_Dashboard::role_home() : array( 'order' => array() );
        $rank = array_flip( (array) ( $role_home['order'] ?? array() ) );
        usort(
            $allowed,
            static function ( array $a, array $b ) use ( $rank ): int {
                return ( $rank[ $a['id'] ] ?? 999 ) <=> ( $rank[ $b['id'] ] ?? 999 );
            }
        );
        return $allowed;
    }

    public static function service_navigation(): array {
        if ( ! class_exists( 'MvM_Platform_Capabilities' ) ) {
            return array();
        }

        $base = array(
            array( 'id' => 'marketplace', 'label' => 'Marktplaats', 'cap' => MvM_Platform_Capabilities::MARKETPLACE_MODERATE ),
            array( 'id' => 'local_ads', 'label' => 'Ondernemersadvertenties', 'cap' => MvM_Platform_Capabilities::LOCAL_ADS_MANAGE ),
            array( 'id' => 'newsletter', 'label' => 'Nieuwsbrief', 'cap' => MvM_Platform_Capabilities::NEWSLETTER_MANAGE ),
            array( 'id' => 'pwa', 'label' => 'PWA', 'cap' => MvM_Platform_Capabilities::PWA_MANAGE ),
        );

        return array_values(
            array_filter(
                $base,
                static fn( array $item ): bool => current_user_can( $item['cap'] ) || current_user_can( 'manage_options' )
            )
        );
    }

    public static function can_view_system_overview(): bool {
        return current_user_can( MvM_Hub4_Capabilities::SYSTEM_OVERVIEW ) || current_user_can( 'manage_options' );
    }

    private static function include_template( string $filename, string $error_message ): never {
        $template = MVM_HUB4_DIR . 'templates/' . $filename;
        if ( ! is_readable( $template ) ) {
            status_header( 500 );
            wp_die( esc_html( $error_message ) );
        }

        include $template;
        exit;
    }

    private static function send_security_headers(): void {
        nocache_headers();

        if ( headers_sent() ) {
            return;
        }

        $nonce = self::csp_nonce();
        header( 'Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0' );
        header( 'Pragma: no-cache' );
        header( 'Expires: 0' );
        header( 'X-Content-Type-Options: nosniff' );
        header( 'Referrer-Policy: same-origin' );
        header( 'Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(), usb=()' );
        header( "Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-{$nonce}'; style-src 'self'; img-src 'self' data: https:; connect-src 'self'; font-src 'self' data:; object-src 'none'; base-uri 'none'; frame-ancestors 'none'; form-action 'self'" );
    }
}
