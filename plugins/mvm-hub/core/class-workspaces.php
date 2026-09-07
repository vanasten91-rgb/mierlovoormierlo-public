<?php

namespace MVM\Hub\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Central workspace registry for the shared Hub shell.
 *
 * Newsroom remains an authorization domain for its services, but its new
 * interface is deliberately backend-only and is therefore not exposed in the
 * standalone /hub/ navigation.
 */
final class Workspaces {
    /** @return array<string,array<string,string|int>> */
    public static function all(): array {
        return array(
            'my-mierlo' => array(
                'label'    => 'Mijn Mierlo',
                'path'     => 'mijn-mierlo/',
                'priority' => 10,
            ),
            'organization' => array(
                'label'    => 'Organisatie',
                'path'     => 'organisatie/',
                'priority' => 20,
            ),
            'newsroom' => array(
                'label'    => 'Nieuwsroom',
                'path'     => 'nieuwsroom/',
                'priority' => 30,
            ),
            'communications' => array(
                'label'    => 'Communicatie',
                'path'     => 'communicatie/',
                'priority' => 40,
            ),
            'technical' => array(
                'label'    => 'Techniek',
                'path'     => 'techniek/',
                'priority' => 50,
            ),
        );
    }

    public static function can_access( string $workspace ): bool {
        return match ( sanitize_key( $workspace ) ) {
            'my-mierlo'      => Capabilities::can_access_my_mierlo(),
            'organization'   => Capabilities::can_access_organization(),
            'newsroom'       => Capabilities::can_access_newsroom(),
            'communications' => Capabilities::can_access_communications(),
            'technical'      => Capabilities::can_access_technical(),
            default          => false,
        };
    }

    /** @return array<string,array<string,string|int>> */
    public static function available(): array {
        $available = array_filter(
            self::all(),
            static function ( array $config, string $key ): bool {
                if ( 'newsroom' === $key ) {
                    return false;
                }
                return self::can_access( $key );
            },
            ARRAY_FILTER_USE_BOTH
        );

        uasort(
            $available,
            static fn( array $left, array $right ): int => (int) $left['priority'] <=> (int) $right['priority']
        );

        return $available;
    }

    public static function default_workspace(): ?string {
        foreach ( array_keys( self::available() ) as $workspace ) {
            return (string) $workspace;
        }

        return null;
    }

    private function __construct() {}
}
