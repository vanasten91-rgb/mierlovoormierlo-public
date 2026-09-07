<?php

namespace MVM\Hub\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Dormant V5 authorization decision primitive.
 *
 * This resolver deliberately does not call WordPress capability helpers, query
 * the database, inspect WordPress roles or register hooks. Callers must provide
 * already scoped capability/object/step-up checks. This keeps policy decisions
 * testable and prevents UI visibility from becoming authorization.
 */
final class V5_Policy_Resolver {
    /**
     * @param array<string,mixed> $request
     * @param callable(string,array<string,mixed>):bool $capability_check
     * @param callable(array<string,mixed>):bool $object_check
     * @param callable(array<string,mixed>):bool $step_up_check
     * @return array{allowed:bool,reason:string,classification:string,requires_explicit_acl:bool,requires_step_up:bool}
     */
    public static function evaluate(
        array $request,
        callable $capability_check,
        callable $object_check,
        callable $step_up_check
    ): array {
        $action         = isset( $request['action'] ) ? sanitize_key( (string) $request['action'] ) : '';
        $capability     = isset( $request['capability'] ) ? sanitize_key( (string) $request['capability'] ) : '';
        $classification = Data_Classification::normalize( (string) ( $request['classification'] ?? '' ) );
        $object_type    = isset( $request['object_type'] ) ? sanitize_key( (string) $request['object_type'] ) : '';
        $object_id      = isset( $request['object_id'] ) ? max( 0, (int) $request['object_id'] ) : 0;
        $requires_acl   = Data_Classification::requires_explicit_acl( $classification );
        $requires_object_check = ! empty( $request['requires_object_check'] ) || $requires_acl;
        $requires_step_up      = ! empty( $request['requires_step_up'] );

        $context = array(
            'action'                => $action,
            'capability'            => $capability,
            'classification'        => $classification,
            'object_type'           => $object_type,
            'object_id'             => $object_id,
            'requires_object_check' => $requires_object_check,
            'requires_explicit_acl' => $requires_acl,
            'requires_step_up'      => $requires_step_up,
            'explicit_acl_granted'  => true === ( $request['explicit_acl_granted'] ?? false ),
        );

        if ( '' === $action ) {
            return self::deny( 'missing_action', $classification, $requires_acl, $requires_step_up );
        }

        if ( '' === $capability ) {
            return self::deny( 'missing_capability', $classification, $requires_acl, $requires_step_up );
        }

        if ( ! (bool) $capability_check( $capability, $context ) ) {
            return self::deny( 'capability_denied', $classification, $requires_acl, $requires_step_up );
        }

        if ( $requires_acl ) {
            if ( '' === $object_type || $object_id <= 0 ) {
                return self::deny( 'protected_object_identity_required', $classification, true, $requires_step_up );
            }

            if ( true !== $context['explicit_acl_granted'] ) {
                return self::deny( 'explicit_acl_required', $classification, true, $requires_step_up );
            }
        }

        if ( $requires_object_check && ! (bool) $object_check( $context ) ) {
            return self::deny( 'object_access_denied', $classification, $requires_acl, $requires_step_up );
        }

        if ( $requires_step_up && ! (bool) $step_up_check( $context ) ) {
            return self::deny( 'step_up_required', $classification, $requires_acl, true );
        }

        return array(
            'allowed'               => true,
            'reason'                => 'allowed',
            'classification'        => $classification,
            'requires_explicit_acl' => $requires_acl,
            'requires_step_up'      => $requires_step_up,
        );
    }

    /**
     * @return array{allowed:bool,reason:string,classification:string,requires_explicit_acl:bool,requires_step_up:bool}
     */
    private static function deny(
        string $reason,
        string $classification,
        bool $requires_acl,
        bool $requires_step_up
    ): array {
        return array(
            'allowed'               => false,
            'reason'                => sanitize_key( $reason ),
            'classification'        => Data_Classification::normalize( $classification ),
            'requires_explicit_acl' => $requires_acl,
            'requires_step_up'      => $requires_step_up,
        );
    }

    private function __construct() {}
}
