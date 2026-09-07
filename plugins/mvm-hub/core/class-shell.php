<?php

namespace MVM\Hub\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * View-model only shell contract.
 *
 * This class deliberately owns no hooks, rewrites or asset enqueueing. It
 * prepares capability-filtered workspace and section navigation for the future
 * central /hub/ shell. Navigation never replaces endpoint/object authorization.
 */
final class Shell {
    /** @return array<string,mixed> */
    public static function view_model( ?string $requested_workspace = null, ?string $requested_section = null ): array {
        $available = Workspaces::available();
        $requested = null === $requested_workspace ? '' : sanitize_key( $requested_workspace );
        $active    = isset( $available[ $requested ] ) ? $requested : Workspaces::default_workspace();
        $navigation = array();

        foreach ( $available as $key => $config ) {
            $navigation[] = array(
                'id'     => (string) $key,
                'label'  => (string) $config['label'],
                'url'    => Router::hub_url( (string) $config['path'] ),
                'active' => $active === $key,
            );
        }

        $sections          = is_string( $active ) && '' !== $active ? Workspace_Sections::available( $active ) : array();
        $requested_subview = null === $requested_section ? '' : sanitize_key( $requested_section );
        $active_section    = isset( $sections[ $requested_subview ] )
            ? $requested_subview
            : ( is_string( $active ) ? Workspace_Sections::default_section( $active ) : null );
        $section_navigation = array();

        foreach ( $sections as $key => $config ) {
            $section_navigation[] = array(
                'id'     => (string) $key,
                'label'  => (string) $config['label'],
                'url'    => Router::hub_url( (string) $config['path'] ),
                'active' => $active_section === $key,
            );
        }

        $active_module = is_string( $active ) && is_string( $active_section )
            ? Module_Descriptors::for( $active, $active_section )
            : null;

        return array(
            'activeWorkspace'   => $active,
            'activeSection'     => $active_section,
            'activeModule'      => $active_module,
            'navigation'        => $navigation,
            'sectionNavigation' => $section_navigation,
            'hasWorkspaces'     => array() !== $navigation,
            'hasSections'       => array() !== $section_navigation,
            'hubUrl'            => Router::hub_url(),
        );
    }

    private function __construct() {}
}
