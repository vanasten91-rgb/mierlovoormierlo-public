<?php

namespace MVM\Hub\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Generic fail-closed workflow registry for Hub V5.
 *
 * Domain modules register immutable workflow definitions during bootstrap. The
 * registry performs structural validation only; actual transition execution
 * must still enforce capability, object access, classification and audit.
 */
final class Workflow_Registry {
    /** @var array<string,array<string,mixed>> */
    private static array $workflows = array();

    /**
     * @param array<string,mixed> $definition
     */
    public static function register( string $key, array $definition ): bool {
        $key = sanitize_key( $key );
        if ( '' === $key || isset( self::$workflows[ $key ] ) ) {
            return false;
        }

        $normalized = self::normalize_definition( $definition );
        if ( null === $normalized ) {
            return false;
        }

        self::$workflows[ $key ] = $normalized;
        return true;
    }

    /** @return array<string,mixed>|null */
    public static function get( string $key ): ?array {
        $key = sanitize_key( $key );
        return self::$workflows[ $key ] ?? null;
    }

    /** @return array<string,array<string,mixed>> */
    public static function all(): array {
        return self::$workflows;
    }

    public static function is_known_state( string $key, string $state ): bool {
        $workflow = self::get( $key );
        if ( null === $workflow ) {
            return false;
        }

        return in_array( sanitize_key( $state ), $workflow['states'], true );
    }

    /** @return array<string,mixed>|null */
    public static function transition( string $key, string $from, string $to ): ?array {
        $workflow = self::get( $key );
        if ( null === $workflow ) {
            return null;
        }

        $from = sanitize_key( $from );
        $to   = sanitize_key( $to );

        return $workflow['transitions'][ $from ][ $to ] ?? null;
    }

    /**
     * @param array<string,mixed> $definition
     * @return array<string,mixed>|null
     */
    private static function normalize_definition( array $definition ): ?array {
        $version = isset( $definition['version'] ) ? (int) $definition['version'] : 0;
        $states  = isset( $definition['states'] ) && is_array( $definition['states'] )
            ? array_values( array_unique( array_filter( array_map( 'sanitize_key', $definition['states'] ) ) ) )
            : array();
        $initial = isset( $definition['initial'] ) ? sanitize_key( (string) $definition['initial'] ) : '';

        if ( $version < 1 || empty( $states ) || '' === $initial || ! in_array( $initial, $states, true ) ) {
            return null;
        }

        $transitions = array();
        $raw         = isset( $definition['transitions'] ) && is_array( $definition['transitions'] )
            ? $definition['transitions']
            : array();

        foreach ( $raw as $from => $targets ) {
            $from = sanitize_key( (string) $from );
            if ( ! in_array( $from, $states, true ) || ! is_array( $targets ) ) {
                return null;
            }

            foreach ( $targets as $to => $policy ) {
                $to = sanitize_key( (string) $to );
                if ( ! in_array( $to, $states, true ) || ! is_array( $policy ) ) {
                    return null;
                }

                $capability = isset( $policy['capability'] ) ? sanitize_key( (string) $policy['capability'] ) : '';
                if ( '' === $capability ) {
                    return null;
                }

                $transitions[ $from ][ $to ] = array(
                    'capability'            => $capability,
                    'requires_object_check' => ! empty( $policy['requires_object_check'] ),
                    'requires_step_up'      => ! empty( $policy['requires_step_up'] ),
                    'audit_event'           => isset( $policy['audit_event'] )
                        ? sanitize_key( (string) $policy['audit_event'] )
                        : '',
                );
            }
        }

        if ( empty( $transitions ) ) {
            return null;
        }

        return array(
            'version'     => $version,
            'initial'     => $initial,
            'states'      => $states,
            'transitions' => $transitions,
        );
    }

    private function __construct() {}
}
