<?php

namespace MVM\Hub\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Pure comparison gate for a read-only production baseline.
 *
 * Callers provide both observed production facts and the exact baseline the
 * release candidate expects. This class does not query WordPress, mutate state
 * or repair drift; it only normalizes evidence and fails closed on mismatch.
 */
final class V5_Production_Baseline_Gate {
    private const VERSION_FIELDS = array(
        'wordpress',
        'php',
        'database',
        'mvmHub',
        'mvmPlatform',
        'encyclopedieNext',
        'royalMcp',
    );

    private const ROUTE_FIELDS = array(
        'royalMcpRootRegisterOwned',
        'royalMcpRootAuthorizeOwned',
        'royalMcpRootTokenOwned',
    );

    /**
     * @param array<string,mixed> $observed
     * @param array<string,mixed> $expected
     * @return array<string,mixed>
     */
    public static function evaluate( array $observed, array $expected ): array {
        $observed_normalized = self::normalize( $observed );
        $expected_normalized = self::normalize( $expected );
        $blockers            = array();
        $mismatches          = array();

        foreach ( self::VERSION_FIELDS as $field ) {
            $actual = (string) ( $observed_normalized[ $field ] ?? '' );
            $wanted = (string) ( $expected_normalized[ $field ] ?? '' );

            if ( '' === $actual || '' === $wanted ) {
                $blockers[] = 'baseline_missing:' . $field;
                continue;
            }

            if ( ! hash_equals( $wanted, $actual ) ) {
                $mismatches[ $field ] = array( 'expected' => $wanted, 'observed' => $actual );
                $blockers[] = 'baseline_mismatch:' . $field;
            }
        }

        foreach ( self::ROUTE_FIELDS as $field ) {
            if ( ! array_key_exists( $field, $observed_normalized ) || ! array_key_exists( $field, $expected_normalized ) ) {
                $blockers[] = 'baseline_missing:' . $field;
                continue;
            }

            $actual = true === $observed_normalized[ $field ];
            $wanted = true === $expected_normalized[ $field ];
            if ( $actual !== $wanted ) {
                $mismatches[ $field ] = array( 'expected' => $wanted, 'observed' => $actual );
                $blockers[] = 'route_ownership_mismatch:' . $field;
            }
        }

        if ( true === ( $observed_normalized['royalMcpRootRegisterOwned'] ?? false ) ) {
            $blockers[] = 'royal_mcp_root_register_conflict';
        }

        $blockers = array_values( array_unique( $blockers ) );

        return array(
            'baselineReady'                => array() === $blockers,
            'canRequestReleaseCandidate'    => array() === $blockers,
            'canPromoteProduction'          => false,
            'requiresExplicitApproval'      => true,
            'observed'                      => $observed_normalized,
            'expected'                      => $expected_normalized,
            'mismatches'                    => $mismatches,
            'blockers'                      => $blockers,
            'capturedAt'                    => self::timestamp( (string) ( $observed['capturedAt'] ?? '' ) ),
        );
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    private static function normalize( array $input ): array {
        $out = array();
        foreach ( self::VERSION_FIELDS as $field ) {
            $out[ $field ] = self::safe_value( (string) ( $input[ $field ] ?? '' ) );
        }
        foreach ( self::ROUTE_FIELDS as $field ) {
            if ( array_key_exists( $field, $input ) ) {
                $out[ $field ] = true === $input[ $field ];
            }
        }
        return $out;
    }

    private static function safe_value( string $value ): string {
        $value = trim( strip_tags( $value ) );
        $value = preg_replace( '/[\x00-\x1F\x7F]/u', '', $value ) ?? '';
        return mb_substr( $value, 0, 120 );
    }

    private static function timestamp( string $value ): string {
        $value = trim( $value );
        if ( '' === $value || strlen( $value ) > 40 ) {
            return '';
        }
        return false === strtotime( $value ) ? '' : $value;
    }

    private function __construct() {}
}
