<?php

namespace MVM\Hub\Core;

use MVM\Hub\Modules\Communications\Communications_Preview_Renderer;
use MVM\Hub\Modules\Newsroom\Newsroom_Preview_Renderer;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Admin-only runtime smoke probe for the inert shell.
 *
 * The route is not registered unless Runtime_Gates explicitly enables preview.
 * It never owns /hub/, writes state, reconciles roles or enables mail delivery.
 */
final class Shell_Preview_REST_Controller {
    private const NAMESPACE = 'mvm-hub/v1';
    private const ROUTE     = '/runtime/shell-preview';

    public function register_routes(): void {
        if ( ! Runtime_Gates::shell_preview_enabled() ) {
            return;
        }

        register_rest_route(
            self::NAMESPACE,
            self::ROUTE,
            array(
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => array( $this, 'preview' ),
                'permission_callback' => array( $this, 'can_preview' ),
                'args'                => array(
                    'workspace' => array(
                        'default'           => '',
                        'sanitize_callback' => 'sanitize_key',
                    ),
                    'section' => array(
                        'default'           => '',
                        'sanitize_callback' => 'sanitize_key',
                    ),
                ),
            )
        );
    }

    public function can_preview(): bool {
        return Runtime_Gates::shell_preview_enabled()
            && is_user_logged_in()
            && current_user_can( 'manage_options' );
    }

    public function preview( \WP_REST_Request $request ): \WP_REST_Response {
        $workspace = sanitize_key( (string) $request->get_param( 'workspace' ) );
        $section   = sanitize_key( (string) $request->get_param( 'section' ) );
        $workspace_arg = '' !== $workspace ? $workspace : null;
        $section_arg   = '' !== $section ? $section : null;
        $view = Shell::view_model( $workspace_arg, $section_arg );

        $active_workspace = sanitize_key( (string) ( $view['activeWorkspace'] ?? '' ) );
        $active_section   = sanitize_key( (string) ( $view['activeSection'] ?? '' ) );
        $module_html = match ( $active_workspace ) {
            'newsroom'       => Newsroom_Preview_Renderer::render( $active_section ),
            'communications' => Communications_Preview_Renderer::render( $active_section ),
            default          => '',
        };

        return rest_ensure_response(
            array(
                'version'         => MVM_HUB_VERSION,
                'environment'     => function_exists( 'wp_get_environment_type' ) ? wp_get_environment_type() : 'production',
                'activeWorkspace' => $active_workspace,
                'activeSection'   => $active_section,
                'html'            => Shell_Renderer::render( $workspace_arg, 'MvM Hub preview', $section_arg ),
                'moduleHtml'      => $module_html,
                'assets'          => Assets::manifest(),
                'headers'         => Hub_Security_Policy::response_headers(),
                'routeOwned'      => false,
                'mailWrites'      => Runtime_Gates::mail_writes_enabled(),
            )
        );
    }
}
