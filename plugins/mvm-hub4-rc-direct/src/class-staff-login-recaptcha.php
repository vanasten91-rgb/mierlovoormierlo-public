<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Hardens the adopted legacy staff gateway without modifying the embedded
 * Hub 3 payload. The legacy gateway remains responsible for credentials,
 * session cookies and password-reset UX; Hub 4 adds a fail-closed reCAPTCHA
 * gate to the currently adopted v2 staff-login action.
 */
final class MvM_Hub4_Staff_Login_Recaptcha {
    private const LOGIN_ACTION = 'mvm_hub_password_staff_login_v2';

    public static function init(): void {
        add_action( 'template_redirect', array( __CLASS__, 'start_gateway_patch' ), -100000 );
        add_action( 'init', array( __CLASS__, 'replace_legacy_login_handler' ), 1000 );
    }

    /**
     * The adopted legacy gateway already renders one invisible reCAPTCHA
     * widget. Its JS currently asks for a token only on reset requests. Patch
     * the final logged-out /hub document so the same widget also runs before
     * the staff-login POST. Exact-string replacement keeps the change narrow
     * and leaves password-reset/first-change flows untouched.
     */
    public static function start_gateway_patch(): void {
        if ( is_user_logged_in() || ! self::is_logged_out_hub_request() ) {
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
     * Legacy runtime patch 288 owns the v2 action at init priority 100. Run
     * after it, remove every known legacy handler for that action, then install
     * one Hub 4 gate. The gate delegates to the newest available legacy
     * credential handler only after the anti-CSRF gateway proof and reCAPTCHA
     * have succeeded.
     */
    public static function replace_legacy_login_handler(): void {
        $handlers = array(
            'mvm_hub_auth_v2_login',
            'mvm_hub_auth_v3_login',
            'mvm_hub_auth_v4_login',
        );

        foreach ( $handlers as $handler ) {
            remove_action( 'wp_ajax_nopriv_' . self::LOGIN_ACTION, $handler );
            remove_action( 'wp_ajax_' . self::LOGIN_ACTION, $handler );
        }

        add_action( 'wp_ajax_nopriv_' . self::LOGIN_ACTION, array( __CLASS__, 'login' ) );
        add_action( 'wp_ajax_' . self::LOGIN_ACTION, array( __CLASS__, 'login' ) );
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

        if ( function_exists( 'mvm_hub_auth_v4_login' ) ) {
            mvm_hub_auth_v4_login();
        }
        if ( function_exists( 'mvm_hub_auth_v3_login' ) ) {
            mvm_hub_auth_v3_login();
        }
        if ( function_exists( 'mvm_hub_auth_v2_login' ) ) {
            mvm_hub_auth_v2_login();
        }

        self::deny( $started_at, 503, 'handler_missing' );
    }

    private static function deny( float $started_at, int $status, string $reason ): never {
        if ( class_exists( 'MvM_Hub4_Audit' ) ) {
            MvM_Hub4_Audit::log(
                'auth.staff_login_gate_denied',
                'denied',
                array(
                    'object_type' => 'staff_login',
                    'context'     => array( 'reason' => sanitize_key( $reason ) ),
                )
            );
        }

        if ( function_exists( 'mvm_hub_password_login_error_v1' ) ) {
            mvm_hub_password_login_error_v1( $started_at, $status );
        }

        wp_send_json_error(
            array( 'message' => 'Inloggen is niet gelukt. Vernieuw de pagina en probeer opnieuw.' ),
            $status
        );
    }

    private static function is_logged_out_hub_request(): bool {
        if ( empty( $_SERVER['REQUEST_URI'] ) ) {
            return false;
        }

        $request_path = wp_parse_url( wp_unslash( (string) $_SERVER['REQUEST_URI'] ), PHP_URL_PATH );
        $hub_path     = wp_parse_url( home_url( '/hub/' ), PHP_URL_PATH );
        return is_string( $request_path )
            && is_string( $hub_path )
            && untrailingslashit( $request_path ) === untrailingslashit( $hub_path );
    }
}
