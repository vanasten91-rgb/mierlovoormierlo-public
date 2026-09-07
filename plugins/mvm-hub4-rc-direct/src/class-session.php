<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class MvM_Hub4_Session {
    public const DEFAULT_IDLE_SECONDS     = 30 * MINUTE_IN_SECONDS;
    public const DEFAULT_ABSOLUTE_SECONDS = 8 * HOUR_IN_SECONDS;

    public static function idle_seconds(): int {
        $value = (int) apply_filters( 'mvm_hub4_idle_timeout_seconds', self::DEFAULT_IDLE_SECONDS );
        return min( HOUR_IN_SECONDS, max( 10 * MINUTE_IN_SECONDS, $value ) );
    }

    public static function absolute_seconds(): int {
        $value = (int) apply_filters( 'mvm_hub4_absolute_timeout_seconds', self::DEFAULT_ABSOLUTE_SECONDS );
        return min( 12 * HOUR_IN_SECONDS, max( HOUR_IN_SECONDS, $value ) );
    }

    public static function validate_and_touch( bool $touch = true ): bool|WP_Error {
        if ( ! is_user_logged_in() ) {
            return new WP_Error( 'mvm_hub4_auth_required', 'Aanmelden is vereist.', array( 'status' => 401 ) );
        }

        $user_id = get_current_user_id();
        $token   = wp_get_session_token();
        if ( $user_id < 1 || '' === $token ) {
            return new WP_Error( 'mvm_hub4_session_missing', 'De beveiligde sessie ontbreekt. Meld opnieuw aan.', array( 'status' => 401 ) );
        }

        $now  = time();
        $key  = self::transient_key( $user_id, $token );
        $data = get_transient( $key );

        if ( ! is_array( $data ) ) {
            $data = array(
                'opened_at'     => $now,
                'last_activity' => $now,
            );
            self::save( $key, $data, $now );
            return true;
        }

        $opened_at     = absint( $data['opened_at'] ?? 0 ) ?: $now;
        $last_activity = absint( $data['last_activity'] ?? 0 ) ?: $opened_at;
        $idle_age      = max( 0, $now - $last_activity );
        $absolute_age  = max( 0, $now - $opened_at );

        if ( $idle_age > self::idle_seconds() ) {
            return self::expire( $key, 'idle_timeout' );
        }

        if ( $absolute_age > self::absolute_seconds() ) {
            return self::expire( $key, 'absolute_timeout' );
        }

        if ( $touch ) {
            $data['last_activity'] = $now;
            self::save( $key, $data, $now );
        }

        return true;
    }

    public static function status(): array {
        if ( ! is_user_logged_in() ) {
            return array(
                'active' => false,
                'idleSeconds' => self::idle_seconds(),
                'absoluteSeconds' => self::absolute_seconds(),
                'idleRemaining' => 0,
                'absoluteRemaining' => 0,
            );
        }

        $user_id = get_current_user_id();
        $token   = wp_get_session_token();
        $now     = time();
        $data    = $token ? get_transient( self::transient_key( $user_id, $token ) ) : false;

        if ( ! is_array( $data ) ) {
            $data = array( 'opened_at' => $now, 'last_activity' => $now );
        }

        $opened_at     = absint( $data['opened_at'] ?? $now );
        $last_activity = absint( $data['last_activity'] ?? $now );

        return array(
            'active'            => true,
            'idleSeconds'       => self::idle_seconds(),
            'absoluteSeconds'   => self::absolute_seconds(),
            'idleRemaining'     => max( 0, self::idle_seconds() - ( $now - $last_activity ) ),
            'absoluteRemaining' => max( 0, self::absolute_seconds() - ( $now - $opened_at ) ),
        );
    }

    public static function destroy_current_tracking(): void {
        if ( ! is_user_logged_in() ) {
            return;
        }

        $token = wp_get_session_token();
        if ( '' !== $token ) {
            delete_transient( self::transient_key( get_current_user_id(), $token ) );
        }
    }

    private static function save( string $key, array $data, int $now ): void {
        $opened_at = absint( $data['opened_at'] ?? $now ) ?: $now;
        $remaining = max( MINUTE_IN_SECONDS, self::absolute_seconds() - max( 0, $now - $opened_at ) );
        set_transient( $key, $data, $remaining );
    }

    private static function expire( string $key, string $reason ): WP_Error {
        delete_transient( $key );

        if ( class_exists( 'MvM_Hub4_Audit' ) ) {
            MvM_Hub4_Audit::log(
                'auth.session_expired',
                'denied',
                array(
                    'object_type' => 'session',
                    'context'     => array( 'reason' => sanitize_key( $reason ) ),
                )
            );
        }

        wp_destroy_current_session();
        wp_clear_auth_cookie();

        return new WP_Error(
            'mvm_hub4_session_expired',
            'Je Hub-sessie is verlopen. Meld opnieuw aan.',
            array( 'status' => 401 )
        );
    }

    private static function transient_key( int $user_id, string $token ): string {
        $fingerprint = substr( hash_hmac( 'sha256', $token, wp_salt( 'auth' ) ), 0, 24 );
        return 'mvm_hub4_s_' . absint( $user_id ) . '_' . $fingerprint;
    }
}
