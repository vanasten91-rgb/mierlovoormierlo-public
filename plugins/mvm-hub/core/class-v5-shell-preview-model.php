<?php

namespace MVM\Hub\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Side-by-side preview model for the future Hub V5 shell.
 *
 * The caller passes already authorized workspace keys. This class claims no
 * routes, templates, hooks or assets and therefore cannot replace /hub/ by
 * merely existing on the branch.
 */
final class V5_Shell_Preview_Model {
    /**
     * @param list<string> $allowed_workspaces
     * @return array<string,mixed>
     */
    public static function build( array $allowed_workspaces, string $requested_workspace = '' ): array {
        $allowed = array_values(
            array_unique(
                array_filter(
                    array_map( 'sanitize_key', $allowed_workspaces ),
                    static fn( string $workspace ): bool => null !== V5_Workspace_Catalog::get( $workspace )
                )
            )
        );

        $navigation = array();
        foreach ( V5_Workspace_Catalog::all() as $key => $config ) {
            if ( ! in_array( $key, $allowed, true ) ) {
                continue;
            }

            $navigation[] = array(
                'key'      => $key,
                'label'    => (string) $config['label'],
                'path'     => (string) $config['path'],
                'priority' => (int) $config['priority'],
                'policy'   => (string) $config['policy'],
            );
        }

        usort(
            $navigation,
            static fn( array $left, array $right ): int => (int) $left['priority'] <=> (int) $right['priority']
        );

        $requested = sanitize_key( $requested_workspace );
        $active    = in_array( $requested, $allowed, true )
            ? $requested
            : ( in_array( V5_Workspace_Catalog::default_workspace(), $allowed, true )
                ? V5_Workspace_Catalog::default_workspace()
                : (string) ( $navigation[0]['key'] ?? '' ) );

        return array(
            'product'             => 'MvM Hub V5',
            'activeWorkspace'     => $active,
            'navigation'          => $navigation,
            'hasWorkspaces'       => array() !== $navigation,
            'routeOwnership'      => false,
            'productionActivated' => false,
        );
    }

    private function __construct() {}
}
