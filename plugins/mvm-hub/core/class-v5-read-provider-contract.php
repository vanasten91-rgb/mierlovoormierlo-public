<?php

namespace MVM\Hub\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Dormant fail-closed contract for side-by-side V5 read providers.
 *
 * Providers only receive already-authorized input records. This contract never
 * queries WordPress, registers hooks/routes, or performs persistence.
 */
final class V5_Read_Provider_Contract {
    public const MODE_PROJECTION    = 'projection';
    public const MODE_METADATA_ONLY = 'metadata_only';

    /**
     * @param array<string,mixed> $provider
     * @param array<string,mixed> $owner_record
     * @return array{allowed:bool,reason:string,provider_key:string,declared_owner:string,current_owner:string,owner_resolution:string,read_mode:string}
     */
    public static function decision( array $provider, array $owner_record ): array {
        $provider_key     = self::key( (string) ( $provider['provider_key'] ?? '' ) );
        $declared_owner   = trim( (string) ( $provider['declared_owner'] ?? '' ) );
        $current_owner    = trim( (string) ( $owner_record['current_owner'] ?? '' ) );
        $owner_resolution = self::key( (string) ( $owner_record['owner_resolution'] ?? '' ) );
        $read_mode        = self::key( (string) ( $provider['read_mode'] ?? '' ) );

        $base = array(
            'allowed'          => false,
            'reason'           => 'invalid_provider',
            'provider_key'     => $provider_key,
            'declared_owner'   => $declared_owner,
            'current_owner'    => $current_owner,
            'owner_resolution' => $owner_resolution,
            'read_mode'        => $read_mode,
        );

        if (
            '' === $provider_key
            || '' === self::key( (string) ( $provider['domain'] ?? '' ) )
            || '' === self::key( (string) ( $provider['source_key'] ?? '' ) )
            || '' === $declared_owner
            || ! in_array( $read_mode, array( self::MODE_PROJECTION, self::MODE_METADATA_ONLY ), true )
            || 'pre_authorized_input' !== (string) ( $provider['authorization_boundary'] ?? '' )
        ) {
            return $base;
        }

        if ( true === ( $provider['write_capable'] ?? false ) ) {
            $base['reason'] = 'write_boundary_violation';
            return $base;
        }

        if ( 'resolved' !== $owner_resolution ) {
            $base['reason'] = 'owner_unresolved';
            return $base;
        }

        if ( '' === $current_owner ) {
            $base['reason'] = 'owner_unknown';
            return $base;
        }

        if ( $declared_owner !== $current_owner ) {
            $base['reason'] = 'owner_mismatch';
            return $base;
        }

        $base['allowed'] = true;
        $base['reason']  = 'ok';
        return $base;
    }

    /** @param array<string,mixed> $provider @param array<string,mixed> $owner_record */
    public static function allows( array $provider, array $owner_record ): bool {
        return true === self::decision( $provider, $owner_record )['allowed'];
    }

    private static function key( string $value ): string {
        $value = strtolower( trim( $value ) );
        $value = preg_replace( '/[^a-z0-9_-]+/', '_', $value );
        return trim( (string) $value, '_' );
    }

    private function __construct() {}
}
