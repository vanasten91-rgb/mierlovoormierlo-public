<?php

namespace MVM\Hub\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Fail-closed hardening for the adopted legacy staff gateway during Hub4
 * decommission. The integrity-pinned Hub 3 service payload remains responsible
 * for the logged-out /hub/ document, password reset UX, credential handling and
 * session cookies; the new Hub owns the anti-CSRF/reCAPTCHA gate once route
 * takeover is explicitly promoted.
 */
final class Staff_Login_Recaptcha {
    private const LOGIN_ACTION = 'mvm_hub_password_staff_login_v2';

    private static bool $registered = false;

    public static function register(): void {
        if ( self::$registered || ! Runtime_Gates::route_takeover_enabled() ) {
            return;
        }

        self::$registered = true;
        add_action( 'template_redirect', array( self::class, 'start_gateway_patch' ), -100000 );
        // Hub4 currently installs its compatibility gate at init:1000. Running
        // later makes ownership deterministic while Hub4 is still the rollback
        // plugin, and remains valid after Hub4 is deactivated.
        add_action( 'init', array( self::class, 'replace_legacy_login_handler' ), 1100 );
    }

    /**
     * The adopted gateway already renders one invisible reCAPTCHA widget. Patch
     * its action allowlist so staff login, not only password reset, must obtain a
     * token. Exact-string replacement is intentionally narrow and idempotent.
     */
    public static function start_gateway_patch(): void {
        if ( is_user_logged_in() || ! self::is_logged_out_hub_root_request() ) {
            return;
        }

        ob_start(
            static function ( string $html ): string {
                $pairs = array(
                    "[ 'mvm_hub_password_reset_request_v2' ]" => "[ 'mvm_hub_password_reset_request_v2', 'mvm_hub_password_staff_login_v2' ]",
                    "[ 'mvm_hub_password_reset_request_v1' ]" => "[ 'mvm_hub_password_reset_request_v1', 'mvm_hub_password_staff_login_v1' ]",
                );

                foreach ( $pairs as $old => $new ) {
                    if ( str_contains( $html, $old ) && ! str_contains( $html, $new ) ) {
                        $html = str_replace( $old, $new, $html );
                    }
                }

                return $html;
            }
        );
    }

    /**
     * Replace every known adopted-legacy handler and Hub4's temporary wrapper
     * with one new-Hub-owned gate. Board/Teamchat remain authenticated-only;
     * this logged-out action is exclusively the staff authentication boundary.
     */
    public static function replace_legacy_login_handler(): void {
        foreach ( array( 'mvm_hub_auth_v2_login', 'mvm_hub_auth_v3_login', 'mvm_hub_auth_v4_login' ) as $handler ) {
            remove_action( 'wp_ajax_nopriv_' . self::LOGIN_ACTION, $handler );
            remove_action( 'wp_ajax_' . self::LOGIN_ACTION, $handler );
        }

        if ( class_exists( 'MvM_Hub4_Staff_Login_Recaptcha', false ) ) {
            remove_action( 'wp_ajax_nopriv_' . self::LOGIN_ACTION, array( 'MvM_Hub4_Staff_Login_Recaptcha', 'login' ) );
            remove_action( 'wp_ajax_' . self::LOGIN_ACTION, array( 'MvM_Hub4_Staff_Login_Recaptcha', 'login' ) );
        }

        add_action( 'wp_ajax_nopriv_' . self::LOGIN_ACTION, array( self::class, 'login' ) );
        add_action( 'wp_ajax_' . self::LOGIN_ACTION, array( self::class, 'login' ) );
    }

    public static function login(): void {
        $started_at = microtime( true );

        if (
            ! function_exists( 'mvm_hub_password_gateway_proof_valid_v1' )
            || ! mvm_hub_password_gateway_proof_valid_v1()
            || ! check_ajax_referer( 'mvm_hub_password_staff_login_v1', 'nonce', false )
        ) {
            self::deny( $started_at, 403, 'gateway' );
        }

        $token = isset( $_POST['recaptcha_token'] ) ? (string) wp_unslash( $_POST['recaptcha_token'] ) : '';
        if (
            ! function_exists( 'mvm_hub_password_recaptcha_verify_v12' )
            || ! mvm_hub_password_recaptcha_verify_v12( $token )
        ) {
            self::deny( $started_at, 403, 'recaptcha' );
        }
        $token = '';

        foreach ( array( 'mvm_hub_auth_v4_login', 'mvm_hub_auth_v3_login', 'mvm_hub_auth_v2_login' ) as $handler ) {
            if ( function_exists( $handler ) ) {
                $handler();
            }
        }

        self::deny( $started_at, 503, 'handler_missing' );
    }

    /** @return array<string,bool> */
    public static function dependency_status(): array {
        return array(
            'gatewayProof'  => function_exists( 'mvm_hub_password_gateway_proof_valid_v1' ),
            'recaptcha'     => function_exists( 'mvm_hub_password_recaptcha_verify_v12' ),
            'loginHandler'  => function_exists( 'mvm_hub_auth_v4_login' )
                || function_exists( 'mvm_hub_auth_v3_login' )
                || function_exists( 'mvm_hub_auth_v2_login' ),
        );
    }

    public static function dependencies_ready(): bool {
        foreach ( self::dependency_status() as $ready ) {
            if ( ! $ready ) {
                return false;
            }
        }
        return true;
    }

    private static function deny( float $started_at, int $status, string $reason ): never {
        Audit::record(
            'auth.staff_login_gate_denied',
            'denied',
            'staff_login',
            0,
            array( 'reason' => sanitize_key( $reason ) )
        );

        if ( function_exists( 'mvm_hub_password_login_error_v1' ) ) {
            mvm_hub_password_login_error_v1( $started_at, $status );
        }

        wp_send_json_error(
            array( 'message' => 'Inloggen is niet gelukt. Vernieuw de pagina en probeer opnieuw.' ),
            $status
        );
    }

    private static function is_logged_out_hub_root_request(): bool {
        $request_uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
        $request_path = wp_parse_url( $request_uri, PHP_URL_PATH );
        $hub_path = wp_parse_url( Router::hub_url(), PHP_URL_PATH );

        return is_string( $request_path )
            && is_string( $hub_path )
            && untrailingslashit( $request_path ) === untrailingslashit( $hub_path );
    }

    private function __construct() {}
}
