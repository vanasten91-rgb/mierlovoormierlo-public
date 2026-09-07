<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class MvM_Hub4_Auth_Audit {
    public static function init(): void {
        add_action( 'wp_login', array( __CLASS__, 'login_success' ), 20, 2 );
        add_action( 'wp_login_failed', array( __CLASS__, 'login_failed' ), 20, 2 );
        add_action( 'clear_auth_cookie', array( __CLASS__, 'logout_started' ), 5 );
    }

    public static function login_success( string $user_login, WP_User $user ): void {
        unset( $user_login );

        if ( ! MvM_Hub4_Security::can_access_hub( $user ) ) {
            return;
        }

        MvM_Hub4_Audit::log(
            'auth.login_success',
            'success',
            array(
                'object_type' => 'user',
                'object_id'   => (int) $user->ID,
            )
        );
    }

    public static function login_failed( string $username, WP_Error $error ): void {
        unset( $error );

        $username = trim( $username );
        if ( '' === $username ) {
            return;
        }

        $user = get_user_by( 'login', $username );
        if ( ! $user && is_email( $username ) ) {
            $user = get_user_by( 'email', $username );
        }

        if ( ! $user instanceof WP_User || ! MvM_Hub4_Security::can_access_hub( $user ) ) {
            return;
        }

        $rate_key = 'mvm_hub4_login_failed_' . (int) $user->ID;
        if ( get_transient( $rate_key ) ) {
            return;
        }
        set_transient( $rate_key, 1, MINUTE_IN_SECONDS );

        MvM_Hub4_Audit::log(
            'auth.login_failed',
            'denied',
            array(
                'object_type' => 'user',
                'object_id'   => (int) $user->ID,
                'context'     => array( 'reason' => 'invalid_credentials' ),
            )
        );
    }

    public static function logout_started(): void {
        $user = wp_get_current_user();
        if ( ! $user instanceof WP_User || ! $user->exists() || ! MvM_Hub4_Security::can_access_hub( $user ) ) {
            return;
        }

        MvM_Hub4_Audit::log(
            'auth.logout',
            'success',
            array(
                'object_type' => 'user',
                'object_id'   => (int) $user->ID,
            )
        );

        MvM_Hub4_Session::destroy_current_tracking();
    }
}
