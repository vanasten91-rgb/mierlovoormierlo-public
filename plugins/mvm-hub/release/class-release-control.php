<?php

namespace MVM\Hub\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Signed, release-scoped runtime approval for managed production cutovers.
 *
 * The final Hub4 decommission release may enable route ownership, Newsroom
 * writes, role reconciliation and recurring-Hub cron ownership for the exact
 * installed release. Cron execution remains additionally fail-closed behind
 * Legacy_Services::persistent_ready(). Communications and external mail use
 * stronger cumulative scopes so they can be promoted and rolled back independently.
 */
final class Release_Control {
    private const OPTION = 'mvm_hub_release_control_v1';
    private const RELEASE_ID = 'hub4-decommission-rc1';
    private const SCOPE_FULL = 'full';
    private const SCOPE_NEWSROOM2 = 'newsroom2';
    private const SCOPE_COMMUNICATIONS = 'communications';
    private const SCOPE_MAIL = 'mail';

    /** @return array<string,mixed> */
    public static function status(): array {
        if ( ! function_exists( 'get_option' ) ) {
            return self::staged_status();
        }

        $record = get_option( self::OPTION, array() );
        $valid = is_array( $record ) && self::verify_record( $record );
        $mode = $valid ? sanitize_key( (string) ( $record['mode'] ?? 'staged' ) ) : 'staged';

        $scope = $valid ? sanitize_key( (string) ( $record['scope'] ?? self::SCOPE_FULL ) ) : self::SCOPE_FULL;
        if ( ! in_array( $scope, array( self::SCOPE_FULL, self::SCOPE_NEWSROOM2, self::SCOPE_COMMUNICATIONS, self::SCOPE_MAIL ), true ) ) {
            $scope = self::SCOPE_FULL;
        }

        return array(
            'releaseId'      => self::RELEASE_ID,
            'mode'           => in_array( $mode, array( 'staged', 'live', 'rollback' ), true ) ? $mode : 'staged',
            'scope'          => $scope,
            'valid'          => $valid,
            'approvedAtUtc'  => $valid ? sanitize_text_field( (string) ( $record['approved_at_utc'] ?? '' ) ) : '',
            'approvedBy'     => $valid ? absint( $record['approved_by_user_id'] ?? 0 ) : 0,
            'artifactSha256' => $valid ? self::sha256( $record['artifact_sha256'] ?? '' ) : '',
            'innerSha256'    => $valid ? self::sha256( $record['inner_sha256'] ?? '' ) : '',
        );
    }

    public static function flag_enabled( string $flag ): bool {
        if ( ! in_array(
            $flag,
            array( 'route_takeover', 'newsroom_writes', 'capability_reconciliation', 'cron_takeover', 'communications_writes', 'mail_writes' ),
            true
        ) ) {
            return false;
        }

        $status = self::status();
        if ( true !== ( $status['valid'] ?? false ) || 'live' !== ( $status['mode'] ?? 'staged' ) ) {
            return false;
        }

        $scope = (string) ( $status['scope'] ?? self::SCOPE_FULL );
        if ( self::SCOPE_NEWSROOM2 === $scope ) {
            return in_array( $flag, array( 'route_takeover', 'newsroom_writes', 'capability_reconciliation' ), true );
        }
        if ( self::SCOPE_FULL === $scope ) {
            return in_array( $flag, array( 'route_takeover', 'newsroom_writes', 'capability_reconciliation', 'cron_takeover' ), true );
        }
        if ( self::SCOPE_COMMUNICATIONS === $scope ) {
            return 'mail_writes' !== $flag;
        }

        return self::SCOPE_MAIL === $scope;
    }

    /** @return array<string,mixed>|\WP_Error */
    public static function set_mode(
        string $mode,
        int $actor_user_id,
        string $artifact_sha256 = '',
        string $inner_sha256 = '',
        string $scope = self::SCOPE_FULL
    ): array|\WP_Error {
        $mode = sanitize_key( $mode );
        if ( ! in_array( $mode, array( 'staged', 'live', 'rollback' ), true ) ) {
            return new \WP_Error( 'mvm_release_mode', 'Ongeldige release-modus.', array( 'status' => 400 ) );
        }

        $scope = sanitize_key( $scope );
        if ( ! in_array( $scope, array( self::SCOPE_FULL, self::SCOPE_NEWSROOM2, self::SCOPE_COMMUNICATIONS, self::SCOPE_MAIL ), true ) ) {
            return new \WP_Error( 'mvm_release_scope', 'Ongeldige release-scope.', array( 'status' => 400 ) );
        }

        $artifact_sha256 = self::sha256( $artifact_sha256 );
        $inner_sha256 = self::sha256( $inner_sha256 );
        if ( 'live' === $mode && ( '' === $artifact_sha256 || '' === $inner_sha256 ) ) {
            return new \WP_Error(
                'mvm_release_hashes',
                'Voor productiepromotie zijn de geverifieerde artifact- en install-ZIP hashes verplicht.',
                array( 'status' => 400 )
            );
        }

        $record = array(
            'release_id'          => self::RELEASE_ID,
            'mode'                => $mode,
            'scope'               => $scope,
            'approved_at_utc'     => gmdate( 'c' ),
            'approved_by_user_id' => max( 0, $actor_user_id ),
            'artifact_sha256'     => $artifact_sha256,
            'inner_sha256'        => $inner_sha256,
        );
        $record['signature'] = self::sign_record( $record );

        if ( ! update_option( self::OPTION, $record, false ) ) {
            $stored = get_option( self::OPTION, array() );
            if ( ! is_array( $stored ) || ! hash_equals(
                wp_json_encode( $record ) ?: '',
                wp_json_encode( $stored ) ?: ''
            ) ) {
                return new \WP_Error( 'mvm_release_store', 'Releasegoedkeuring kon niet worden opgeslagen.', array( 'status' => 500 ) );
            }
        }

        return self::status();
    }

    public static function release_id(): string {
        return self::RELEASE_ID;
    }

    /** @return array<string,mixed> */
    private static function staged_status(): array {
        return array(
            'releaseId'      => self::RELEASE_ID,
            'mode'           => 'staged',
            'scope'          => self::SCOPE_FULL,
            'valid'          => false,
            'approvedAtUtc'  => '',
            'approvedBy'     => 0,
            'artifactSha256' => '',
            'innerSha256'    => '',
        );
    }

    /** @param array<string,mixed> $record */
    private static function verify_record( array $record ): bool {
        if ( self::RELEASE_ID !== (string) ( $record['release_id'] ?? '' ) ) {
            return false;
        }

        $mode = sanitize_key( (string) ( $record['mode'] ?? '' ) );
        if ( ! in_array( $mode, array( 'staged', 'live', 'rollback' ), true ) ) {
            return false;
        }

        if ( array_key_exists( 'scope', $record ) ) {
            $scope = sanitize_key( (string) $record['scope'] );
            if ( ! in_array( $scope, array( self::SCOPE_FULL, self::SCOPE_NEWSROOM2, self::SCOPE_COMMUNICATIONS, self::SCOPE_MAIL ), true ) ) {
                return false;
            }
        }

        $signature = strtolower( trim( (string) ( $record['signature'] ?? '' ) ) );
        if ( 64 !== strlen( $signature ) || ! ctype_xdigit( $signature ) ) {
            return false;
        }

        $unsigned = $record;
        unset( $unsigned['signature'] );
        return hash_equals( self::sign_record( $unsigned ), $signature );
    }

    /** @param array<string,mixed> $record */
    private static function sign_record( array $record ): string {
        $payload = array(
            'release_id'          => (string) ( $record['release_id'] ?? '' ),
            'mode'                => sanitize_key( (string) ( $record['mode'] ?? '' ) ),
        );
        if ( array_key_exists( 'scope', $record ) ) {
            $payload['scope'] = sanitize_key( (string) $record['scope'] );
        }
        $payload += array(
            'approved_at_utc'     => sanitize_text_field( (string) ( $record['approved_at_utc'] ?? '' ) ),
            'approved_by_user_id' => absint( $record['approved_by_user_id'] ?? 0 ),
            'artifact_sha256'     => self::sha256( $record['artifact_sha256'] ?? '' ),
            'inner_sha256'        => self::sha256( $record['inner_sha256'] ?? '' ),
        );
        $json = wp_json_encode( $payload, JSON_UNESCAPED_SLASHES );
        return hash_hmac( 'sha256', is_string( $json ) ? $json : '{}', wp_salt( 'secure_auth' ) );
    }

    private static function sha256( mixed $value ): string {
        $hash = strtolower( trim( (string) $value ) );
        return 64 === strlen( $hash ) && ctype_xdigit( $hash ) ? $hash : '';
    }

    private function __construct() {}
}
