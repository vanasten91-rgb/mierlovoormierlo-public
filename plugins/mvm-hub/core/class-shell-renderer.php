<?php

namespace MVM\Hub\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Pure server-side renderer for the future central Hub shell.
 *
 * No hooks, rewrites, templates or assets are claimed here. The renderer can be
 * runtime-smoked independently before /hub/ ownership is enabled.
 */
final class Shell_Renderer {
    public static function render(
        ?string $requested_workspace = null,
        string $page_title = 'MvM Hub',
        ?string $requested_section = null,
        string $module_html = ''
    ): string {
        $view_model = Shell::view_model( $requested_workspace, $requested_section );
        $workspace  = is_string( $view_model['activeWorkspace'] ?? null ) ? (string) $view_model['activeWorkspace'] : '';
        $section    = is_string( $view_model['activeSection'] ?? null ) ? (string) $view_model['activeSection'] : '';
        $module     = is_array( $view_model['activeModule'] ?? null ) ? $view_model['activeModule'] : array();
        $stage      = sanitize_key( (string) ( $module['stage'] ?? 'planned' ) );
        $page_title = sanitize_text_field( $page_title );

        ob_start();
        ?>
        <div
            class="mvm-hub"
            data-mvm-hub
            data-workspace="<?php echo esc_attr( $workspace ); ?>"
            data-section="<?php echo esc_attr( $section ); ?>"
        >
            <a class="mvm-hub__skip-link" href="#mvm-hub-content"><?php echo esc_html__( 'Ga naar inhoud', 'mvm-hub' ); ?></a>
            <div class="mvm-hub__layout">
                <aside class="mvm-hub__sidebar" id="mvm-hub-sidebar" aria-label="<?php echo esc_attr__( 'MvM Hub navigatie', 'mvm-hub' ); ?>">
                    <div class="mvm-hub__brand">
                        <a href="<?php echo esc_url( (string) $view_model['hubUrl'] ); ?>" class="mvm-hub__brand-link">Mierlo voor Mierlo</a>
                        <span class="mvm-hub__brand-label">Hub</span>
                    </div>

                    <button class="mvm-hub__nav-toggle" type="button" data-mvm-hub-nav-toggle aria-controls="mvm-hub-workspaces" aria-expanded="false">
                        <span><?php echo esc_html__( 'Werkruimtes', 'mvm-hub' ); ?></span>
                    </button>

                    <?php if ( ! empty( $view_model['hasWorkspaces'] ) ) : ?>
                        <nav class="mvm-hub__nav" id="mvm-hub-workspaces" aria-label="<?php echo esc_attr__( 'Werkruimtes', 'mvm-hub' ); ?>">
                            <?php foreach ( (array) $view_model['navigation'] as $item ) : ?>
                                <a
                                    class="mvm-hub__nav-link"
                                    href="<?php echo esc_url( (string) ( $item['url'] ?? '' ) ); ?>"
                                    <?php if ( ! empty( $item['active'] ) ) : ?>aria-current="page"<?php endif; ?>
                                ><?php echo esc_html( (string) ( $item['label'] ?? '' ) ); ?></a>
                            <?php endforeach; ?>
                        </nav>
                    <?php else : ?>
                        <div class="mvm-alert mvm-alert--warning" role="status">
                            <?php echo esc_html__( 'Er zijn geen Hub-werkruimtes beschikbaar voor dit account.', 'mvm-hub' ); ?>
                        </div>
                    <?php endif; ?>
                </aside>

                <main class="mvm-hub__main" id="mvm-hub-content" tabindex="-1">
                    <header class="mvm-hub__topbar">
                        <div>
                            <p class="mvm-hub__eyebrow">MvM Hub</p>
                            <h1 class="mvm-hub__title"><?php echo esc_html( '' !== $page_title ? $page_title : 'MvM Hub' ); ?></h1>
                        </div>
                        <button class="mvm-button mvm-button--secondary" type="button" data-mvm-hub-theme-toggle aria-pressed="false">
                            <?php echo esc_html__( 'Licht/donker', 'mvm-hub' ); ?>
                        </button>
                    </header>

                    <?php if ( ! empty( $view_model['hasSections'] ) ) : ?>
                        <nav class="mvm-hub__subnav" aria-label="<?php echo esc_attr__( 'Onderdelen', 'mvm-hub' ); ?>">
                            <?php foreach ( (array) $view_model['sectionNavigation'] as $item ) : ?>
                                <a
                                    class="mvm-hub__subnav-link"
                                    href="<?php echo esc_url( (string) ( $item['url'] ?? '' ) ); ?>"
                                    <?php if ( ! empty( $item['active'] ) ) : ?>aria-current="page"<?php endif; ?>
                                ><?php echo esc_html( (string) ( $item['label'] ?? '' ) ); ?></a>
                            <?php endforeach; ?>
                        </nav>
                    <?php endif; ?>

                    <section
                        class="mvm-hub__workspace"
                        data-mvm-hub-workspace-view
                        data-module-stage="<?php echo esc_attr( $stage ); ?>"
                        aria-live="polite"
                        aria-busy="false"
                    >
                        <?php if ( array() !== $module ) : ?>
                            <div class="mvm-card mvm-hub__module-status">
                                <div>
                                    <span class="mvm-badge"><?php echo esc_html( self::stage_label( $stage ) ); ?></span>
                                    <h2 class="mvm-hub__module-title"><?php echo esc_html( (string) ( $module['label'] ?? '' ) ); ?></h2>
                                </div>
                                <?php if ( empty( $module['writesEnabled'] ) ) : ?>
                                    <p class="mvm-hub__module-note"><?php echo esc_html__( 'Schrijfacties blijven tijdens de parallelle migratie geblokkeerd.', 'mvm-hub' ); ?></p>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <?php if ( '' !== $module_html ) : ?>
                            <?php echo $module_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- only trusted server renderers may supply this parameter. ?>
                        <?php else : ?>
                            <div class="mvm-skeleton" role="status"><?php echo esc_html__( 'Werkruimte gereed voor moduleweergave.', 'mvm-hub' ); ?></div>
                        <?php endif; ?>
                    </section>
                </main>
            </div>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    private static function stage_label( string $stage ): string {
        return match ( $stage ) {
            'read-ready'    => __( 'Leeslaag gereed', 'mvm-hub' ),
            'service-ready' => __( 'Servicelaag gereed', 'mvm-hub' ),
            default         => __( 'In voorbereiding', 'mvm-hub' ),
        };
    }

    private function __construct() {}
}
