<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class MvM_Hub4_Security {
    public const HUB_CAPABILITY = 'mvm_access_hub';

    public static function init(): void {
        add_filter( 'rest_pre_serve_request', array( __CLASS__, 'add_private_rest_headers' ), 10, 4 );
    }

    public static function can_access_hub( ?WP_User $user = null ): bool {
        $user = $user ?: wp_get_current_user();

        if ( ! $user instanceof WP_User || ! $user->exists() ) {
            return false;
        }

        return user_can( $user, self::HUB_CAPABILITY ) || user_can( $user, 'manage_options' );
    }

    public static function require_hub_access( ?WP_REST_Request $request = null ): bool|WP_Error {
        unset( $request );

        if ( ! is_user_logged_in() ) {
            MvM_Hub4_Audit::log(
                'security.access_denied',
                'denied',
                array(
                    'object_type' => 'hub',
                    'context'     => array( 'reason' => 'not_authenticated' ),
                )
            );

            return new WP_Error( 'mvm_hub4_auth_required', 'Aanmelden is vereist.', array( 'status' => 401 ) );
        }

        if ( ! self::can_access_hub() ) {
            MvM_Hub4_Audit::log(
                'security.access_denied',
                'denied',
                array(
                    'object_type' => 'hub',
                    'context'     => array( 'reason' => 'missing_hub_capability' ),
                )
            );

            return new WP_Error( 'mvm_hub4_forbidden', 'Je hebt geen toegang tot de Hub.', array( 'status' => 403 ) );
        }

        $session = MvM_Hub4_Session::validate_and_touch();
        if ( true !== $session ) {
            return $session;
        }

        return true;
    }

    public static function require_capability( string $capability, string $event_code = 'security.capability_denied' ): callable {
        return static function () use ( $capability, $event_code ): bool|WP_Error {
            $hub_access = self::require_hub_access();
            if ( true !== $hub_access ) {
                return $hub_access;
            }

            if ( ! current_user_can( $capability ) && ! current_user_can( 'manage_options' ) ) {
                MvM_Hub4_Audit::log(
                    $event_code,
                    'denied',
                    array(
                        'object_type' => 'capability',
                        'context'     => array( 'required_capability' => sanitize_key( $capability ) ),
                    )
                );

                return new WP_Error( 'mvm_hub4_forbidden', 'Onvoldoende rechten voor deze actie.', array( 'status' => 403 ) );
            }

            return true;
        };
    }

    public static function request_id(): string {
        static $request_id = null;

        if ( null === $request_id ) {
            $request_id = wp_generate_uuid4();
        }

        return $request_id;
    }

    public static function add_private_rest_headers( bool $served, WP_HTTP_Response $result, WP_REST_Request $request, WP_REST_Server $server ): bool {
        unset( $result, $server );

        if ( 0 !== strpos( $request->get_route(), '/mvm-hub4/v1/' ) ) {
            return $served;
        }

        if ( ! headers_sent() ) {
            header( 'Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0' );
            header( 'Pragma: no-cache' );
            header( 'Expires: 0' );
            header( 'X-Content-Type-Options: nosniff' );
            header( 'Referrer-Policy: same-origin' );
        }

        return $served;
    }
}
