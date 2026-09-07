<?php

namespace MVM\Hub\Core;

use MVM\Hub\Modules\Communications\Communications_Runtime_Renderer;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Standalone private /hub/ runtime used only when route takeover is explicitly
 * enabled. Newsroom 2.0 is backend-only; legacy /hub/nieuwsroom paths redirect
 * authorized staff to wp-admin and are never rendered by this shell.
 */
final class Hub_Runtime {
    private static bool $registered = false;

    public static function register(): void {
        if ( self::$registered || ! Runtime_Gates::route_takeover_enabled() ) {
            return;
        }

        self::$registered = true;
        add_action( 'template_redirect', array( self::class, 'maybe_render' ), -100 );
    }

    public static function maybe_render(): void {
        if ( ! Runtime_Gates::route_takeover_enabled() || ! Router::is_hub_request() ) {
            return;
        }

        if ( ! is_user_logged_in() ) {
            // The integrity-pinned adopted Hub 3 payload keeps ownership of the
            // branded logged-out /hub/ gateway and password-reset UX. The new
            // Hub hardens its staff-login action through Staff_Login_Recaptcha.
            // Nested Hub routes first return to the gateway instead of exposing
            // wp-login.php or allowing a legacy nested route to render.
            $request_uri  = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
            $request_path = (string) wp_parse_url( $request_uri, PHP_URL_PATH );
            $hub_path     = (string) wp_parse_url( Router::hub_url(), PHP_URL_PATH );
            if ( untrailingslashit( $request_path ) !== untrailingslashit( $hub_path ) ) {
                wp_safe_redirect( Router::hub_url() );
                exit;
            }
            return;
        }

        $resolved = self::resolve_request();
        if ( is_wp_error( $resolved ) ) {
            self::send_private_headers();
            status_header( 404 );
            echo self::error_document( __( 'Hub-onderdeel niet beschikbaar.', 'mvm-hub' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            exit;
        }

        $requested_workspace = $resolved['workspace'];
        $requested_section   = $resolved['section'];

        if ( 'newsroom' === $requested_workspace ) {
            if ( ! Capabilities::can_access_newsroom() ) {
                self::not_found();
            }
            self::send_private_headers();
            wp_safe_redirect( admin_url( 'admin.php?page=mvm-newsroom' ) );
            exit;
        }

        if ( null !== $requested_workspace && ! Workspaces::can_access( $requested_workspace ) ) {
            self::not_found();
        }
        if (
            null !== $requested_workspace
            && null !== $requested_section
            && ! Workspace_Sections::can_access( $requested_workspace, $requested_section )
        ) {
            self::not_found();
        }

        $view = Shell::view_model( $requested_workspace, $requested_section );
        $active_workspace = is_string( $view['activeWorkspace'] ?? null ) ? (string) $view['activeWorkspace'] : '';
        $active_section   = is_string( $view['activeSection'] ?? null ) ? (string) $view['activeSection'] : '';

        if ( '' === $active_workspace ) {
            self::send_private_headers();
            status_header( 403 );
            echo self::error_document( __( 'Er zijn geen Hub-werkruimtes beschikbaar voor dit account.', 'mvm-hub' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            exit;
        }

        $module_html = match ( $active_workspace ) {
            'communications' => Communications_Runtime_Renderer::render( $active_section ),
            default          => '',
        };

        $title = self::page_title( $active_workspace, $active_section );
        $shell = Shell_Renderer::render( $active_workspace, $title, '' !== $active_section ? $active_section : null, $module_html );

        self::send_private_headers();
        status_header( 200 );
        echo self::document( $title, $shell, $active_workspace ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- trusted server renderers.
        exit;
    }

    /** @return array{workspace:?string,section:?string}|\WP_Error */
    private static function resolve_request(): array|\WP_Error {
        $request_uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
        $path = (string) wp_parse_url( $request_uri, PHP_URL_PATH );
        $hub_path = Router::HUB_PATH;

        if ( $hub_path === trailingslashit( $path ) ) {
            return array( 'workspace' => null, 'section' => null );
        }

        if ( ! str_starts_with( trailingslashit( $path ), $hub_path ) ) {
            return new \WP_Error( 'mvm_hub_runtime_path', 'Invalid Hub path.' );
        }

        $relative = trim( substr( $path, strlen( rtrim( $hub_path, '/' ) ) ), '/' );
        if ( '' === $relative ) {
            return array( 'workspace' => null, 'section' => null );
        }
        $relative .= '/';

        foreach ( Workspace_Sections::all() as $workspace => $sections ) {
            foreach ( $sections as $section => $config ) {
                if ( $relative === (string) ( $config['path'] ?? '' ) ) {
                    return array(
                        'workspace' => sanitize_key( (string) $workspace ),
                        'section'   => sanitize_key( (string) $section ),
                    );
                }
            }
        }

        foreach ( Workspaces::all() as $workspace => $config ) {
            if ( $relative === (string) ( $config['path'] ?? '' ) ) {
                return array(
                    'workspace' => sanitize_key( (string) $workspace ),
                    'section'   => null,
                );
            }
        }

        return new \WP_Error( 'mvm_hub_runtime_path', 'Unknown Hub route.' );
    }

    private static function not_found(): never {
        self::send_private_headers();
        status_header( 404 );
        echo self::error_document( __( 'Hub-onderdeel niet beschikbaar.', 'mvm-hub' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        exit;
    }

    private static function send_private_headers(): void {
        foreach ( Hub_Security_Policy::response_headers() as $name => $value ) {
            header( $name . ': ' . $value, true );
        }
        header( 'Content-Type: text/html; charset=' . get_bloginfo( 'charset' ), true );
    }

    private static function page_title( string $workspace, string $section ): string {
        $workspace_config = Workspaces::all()[ $workspace ] ?? array();
        $workspace_label  = sanitize_text_field( (string) ( $workspace_config['label'] ?? 'MvM Hub' ) );
        $section_config   = Workspace_Sections::all()[ $workspace ][ $section ] ?? array();
        $section_label    = sanitize_text_field( (string) ( $section_config['label'] ?? '' ) );

        return '' !== $section_label
            ? $workspace_label . ' — ' . $section_label
            : $workspace_label;
    }

    private static function document( string $title, string $shell, string $workspace = '' ): string {
        $assets  = Assets::manifest();
        $styles  = array( 'style', 'sectionsStyle', 'newsroomPreviewStyle' );
        $scripts = array( 'script' );
        if ( 'communications' === $workspace ) {
            $styles[]  = 'communicationsStyle';
            $styles[]  = 'communicationsUiStyle';
            $scripts[] = 'communicationsScript';
            $scripts[] = 'communicationsUiScript';
        }
        $charset = get_bloginfo( 'charset' );
        $lang    = get_bloginfo( 'language' );

        ob_start();
        ?>
<!doctype html>
<html lang="<?php echo esc_attr( $lang ); ?>">
<head>
<meta charset="<?php echo esc_attr( $charset ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo esc_html( $title ); ?> · Mierlo voor Mierlo</title>
<?php foreach ( $styles as $asset_key ) : ?>
    <?php $asset = is_array( $assets[ $asset_key ] ?? null ) ? $assets[ $asset_key ] : array(); ?>
    <?php if ( '' !== (string) ( $asset['src'] ?? '' ) ) : ?>
<link rel="stylesheet" href="<?php echo esc_url( add_query_arg( 'ver', (string) ( $asset['version'] ?? MVM_HUB_VERSION ), (string) $asset['src'] ) ); ?>">
    <?php endif; ?>
<?php endforeach; ?>
</head>
<body>
<?php echo $shell; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- trusted Shell_Renderer output. ?>
<?php foreach ( $scripts as $asset_key ) : ?>
    <?php $script = is_array( $assets[ $asset_key ] ?? null ) ? $assets[ $asset_key ] : array(); ?>
    <?php if ( '' !== (string) ( $script['src'] ?? '' ) ) : ?>
<script src="<?php echo esc_url( add_query_arg( 'ver', (string) ( $script['version'] ?? MVM_HUB_VERSION ), (string) $script['src'] ) ); ?>" defer></script>
    <?php endif; ?>
<?php endforeach; ?>
</body>
</html>
        <?php
        return (string) ob_get_clean();
    }

    private static function error_document( string $message ): string {
        $title = __( 'MvM Hub', 'mvm-hub' );
        $body  = '<div class="mvm-hub"><main class="mvm-hub__main"><div class="mvm-alert mvm-alert--warning" role="alert">'
            . esc_html( $message )
            . '</div></main></div>';
        return self::document( $title, $body );
    }

    private function __construct() {}
}
