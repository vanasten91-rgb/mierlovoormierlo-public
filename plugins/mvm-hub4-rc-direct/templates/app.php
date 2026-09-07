<?php

if ( ! defined( 'ABSPATH' ) || ! MvM_Hub4_App::is_request() || ! MvM_Hub4_Security::can_access_hub() ) {
    exit;
}

$user                 = wp_get_current_user();
$navigation           = MvM_Hub4_App::navigation();
$platform_navigation  = MvM_Hub4_App::platform_navigation();
$service_navigation   = MvM_Hub4_App::service_navigation();
$config               = MvM_Hub4_App::app_config();
$nonce                = MvM_Hub4_App::csp_nonce();
$workflow_guide       = MvM_Hub4_Workflow_Guide::current();
$app_css              = MVM_HUB4_URL . 'assets/app.css?ver=' . rawurlencode( MVM_HUB4_VERSION );
$modules_css          = MVM_HUB4_URL . 'assets/modules.css?ver=' . rawurlencode( MVM_HUB4_VERSION );
$workflow_css         = MVM_HUB4_URL . 'assets/workflow-guide.css?ver=' . rawurlencode( MVM_HUB4_VERSION );
$my_work_css          = MVM_HUB4_URL . 'assets/my-work.css?ver=' . rawurlencode( MVM_HUB4_VERSION );
$platform_css         = MVM_HUB4_URL . 'assets/platform-v1.css?ver=' . rawurlencode( MVM_HUB4_VERSION );
$news_radar_css       = MVM_HUB4_URL . 'assets/news-radar-v1.css?ver=' . rawurlencode( MVM_HUB4_VERSION );
$responsive_css       = MVM_HUB4_URL . 'assets/responsive-hardening.css?ver=' . rawurlencode( MVM_HUB4_VERSION );
$contrast_css         = MVM_HUB4_URL . 'assets/contrast-v1.css?ver=' . rawurlencode( MVM_HUB4_VERSION );
$app_js               = MVM_HUB4_URL . 'assets/app-v2.js?ver=' . rawurlencode( MVM_HUB4_VERSION );
$my_work_js           = MVM_HUB4_URL . 'assets/my-work.js?ver=' . rawurlencode( MVM_HUB4_VERSION );
$platform_js          = MVM_HUB4_URL . 'assets/platform-v1.js?ver=' . rawurlencode( MVM_HUB4_VERSION );
$platform_services_js = MVM_HUB4_URL . 'assets/platform-services-v1.js?ver=' . rawurlencode( MVM_HUB4_VERSION );
$local_ads_admin_js   = MVM_HUB4_URL . 'assets/local-ads-admin-v1.js?ver=' . rawurlencode( MVM_HUB4_VERSION );
$news_radar_js        = MVM_HUB4_URL . 'assets/news-radar-v1.js?ver=' . rawurlencode( MVM_HUB4_VERSION );
$signal_conversion_js = MVM_HUB4_URL . 'assets/signal-conversion-ui.js?ver=' . rawurlencode( MVM_HUB4_VERSION );
$nav_labels           = array_column( MvM_Hub4_App::navigation_base(), 'label', 'id' );
$can_view_news_radar  = current_user_can( MvM_Hub4_Capabilities::NEWS_VIEW ) || current_user_can( 'manage_options' );
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow,noarchive,nosnippet">
    <meta name="referrer" content="same-origin">
    <title><?php echo esc_html( 'MvM Hub · ' . get_bloginfo( 'name' ) ); ?></title>
    <link rel="stylesheet" href="<?php echo esc_url( $app_css ); ?>">
    <link rel="stylesheet" href="<?php echo esc_url( $modules_css ); ?>">
    <link rel="stylesheet" href="<?php echo esc_url( $workflow_css ); ?>">
    <link rel="stylesheet" href="<?php echo esc_url( $my_work_css ); ?>">
    <link rel="stylesheet" href="<?php echo esc_url( $platform_css ); ?>">
    <?php if ( $can_view_news_radar ) : ?>
        <link rel="stylesheet" href="<?php echo esc_url( $news_radar_css ); ?>">
    <?php endif; ?>
    <link rel="stylesheet" href="<?php echo esc_url( $responsive_css ); ?>">
    <link rel="stylesheet" href="<?php echo esc_url( $contrast_css ); ?>">
</head>
<body class="mvm-hub4">
<a class="mvm-hub4__skip" href="#mvm-hub4-main">Naar hoofdinhoud</a>
<div class="mvm-hub4__app" data-mvm-hub4-app>
    <aside class="mvm-hub4__sidebar" aria-label="Hub navigatie">
        <div class="mvm-hub4__brand">
            <span class="mvm-hub4__mark" aria-hidden="true">MvM</span>
            <span class="mvm-hub4__brand-text">
                <strong>Mierlo voor Mierlo</strong>
                <span>Stafwerkplek</span>
            </span>
        </div>

        <nav class="mvm-hub4__nav" aria-label="Hoofdnavigatie">
            <?php foreach ( $navigation as $index => $item ) : ?>
                <button
                    type="button"
                    class="mvm-hub4__nav-item<?php echo 0 === $index ? ' is-active' : ''; ?>"
                    data-mvm-view="<?php echo esc_attr( $item['id'] ); ?>"
                    aria-pressed="<?php echo 0 === $index ? 'true' : 'false'; ?>"
                >
                    <?php echo esc_html( $item['label'] ); ?>
                </button>
            <?php endforeach; ?>
        </nav>

        <?php if ( $platform_navigation || $can_view_news_radar ) : ?>
            <p class="mvm-hub4__platform-label">Newsroom 1.0</p>
            <nav class="mvm-hub4__platform-nav" aria-label="Newsroom 1.0">
                <?php foreach ( $platform_navigation as $item ) : ?>
                    <button
                        type="button"
                        data-mvm-platform-view="<?php echo esc_attr( $item['id'] ); ?>"
                        aria-pressed="false"
                    >
                        <?php echo esc_html( $item['label'] ); ?>
                    </button>
                <?php endforeach; ?>
                <?php if ( $can_view_news_radar ) : ?>
                    <button
                        type="button"
                        data-mvm-news-radar-view
                        aria-pressed="false"
                    >
                        Nieuwsradar
                    </button>
                <?php endif; ?>
            </nav>
        <?php endif; ?>

        <?php if ( $service_navigation ) : ?>
            <p class="mvm-hub4__platform-label">Platformdiensten</p>
            <nav class="mvm-hub4__platform-nav" aria-label="Platformdiensten">
                <?php foreach ( $service_navigation as $item ) : ?>
                    <button
                        type="button"
                        data-mvm-service-view="<?php echo esc_attr( $item['id'] ); ?>"
                        aria-pressed="false"
                    >
                        <?php echo esc_html( $item['label'] ); ?>
                    </button>
                <?php endforeach; ?>
            </nav>
        <?php endif; ?>

        <div class="mvm-hub4__sidebar-footer">
            <?php if ( MvM_Hub4_App::can_view_system_overview() ) : ?>
                <a href="<?php echo esc_url( $config['systemOverviewUrl'] ); ?>">Systeemoverzicht</a>
            <?php endif; ?>
            <a href="<?php echo esc_url( home_url( '/' ) ); ?>">Bekijk website</a>
            <a href="<?php echo esc_url( wp_logout_url( home_url( '/hub/' ) ) ); ?>">Uitloggen</a>
        </div>
    </aside>

    <div class="mvm-hub4__workspace">
        <header class="mvm-hub4__topbar">
            <div>
                <p class="mvm-hub4__eyebrow">Interne newsroom</p>
                <h1 data-mvm-view-title>Vandaag</h1>
            </div>
            <div class="mvm-hub4__user" aria-label="Ingelogde medewerker">
                <span class="mvm-hub4__user-avatar" aria-hidden="true"><?php echo esc_html( mb_strtoupper( mb_substr( $user->display_name ?: $user->user_login, 0, 1 ) ) ); ?></span>
                <span class="mvm-hub4__user-meta">
                    <strong><?php echo esc_html( $user->display_name ?: $user->user_login ); ?></strong>
                    <small><?php echo esc_html( $workflow_guide['roleLabel'] ); ?></small>
                </span>
            </div>
        </header>

        <main id="mvm-hub4-main" class="mvm-hub4__main" tabindex="-1">
            <details class="mvm-hub4__workflow-guide" data-mvm-workflow-guide>
                <summary>
                    <span>
                        <strong>Zo gebruik je jouw workflow</strong>
                        <small><?php echo esc_html( $workflow_guide['roleLabel'] . ' · ' . $workflow_guide['title'] ); ?></small>
                    </span>
                    <span aria-hidden="true">+</span>
                </summary>
                <div class="mvm-hub4__workflow-guide-body">
                    <p><?php echo esc_html( $workflow_guide['description'] ); ?></p>

                    <div class="mvm-hub4__workflow-order" aria-label="Aanbevolen volgorde">
                        <strong>Aanbevolen volgorde</strong>
                        <ol>
                            <?php foreach ( $workflow_guide['navOrder'] as $view_id ) : ?>
                                <?php if ( isset( $nav_labels[ $view_id ] ) ) : ?>
                                    <li><?php echo esc_html( $nav_labels[ $view_id ] ); ?></li>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </ol>
                    </div>

                    <div class="mvm-hub4__workflow-columns">
                        <section>
                            <h2>Zo werk je stap voor stap</h2>
                            <ol>
                                <?php foreach ( $workflow_guide['steps'] as $step ) : ?>
                                    <li><?php echo esc_html( $step ); ?></li>
                                <?php endforeach; ?>
                            </ol>
                        </section>
                        <section>
                            <h2>Tips voor jouw rol</h2>
                            <ul>
                                <?php foreach ( $workflow_guide['tips'] as $tip ) : ?>
                                    <li><?php echo esc_html( $tip ); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </section>
                    </div>
                </div>
            </details>

            <?php if ( current_user_can( MvM_Hub4_Capabilities::ASSIGNMENT_VIEW ) || current_user_can( 'manage_options' ) ) : ?>
                <section class="mvm-hub4__my-work" data-mvm-my-work aria-labelledby="mvm-my-work-title">
                    <div class="mvm-hub4__my-work-head">
                        <div>
                            <h2 id="mvm-my-work-title">Mijn werk vandaag</h2>
                            <p>Alleen jouw signalen en toegewezen werk. Status wijzigen gebeurt zonder paginaverversing.</p>
                        </div>
                        <div class="mvm-hub4__my-work-status" data-mvm-my-work-status role="status" aria-live="polite"></div>
                    </div>
                    <div class="mvm-hub4__my-work-list" data-mvm-my-work-list aria-live="polite"></div>

                    <?php if ( current_user_can( MvM_Hub4_Capabilities::ASSIGNMENT_CREATE ) || current_user_can( 'manage_options' ) ) : ?>
                        <form class="mvm-hub4__signal-form" data-mvm-signal-form>
                            <label class="screen-reader-text" for="mvm-signal-title">Nieuw redactioneel signaal</label>
                            <input id="mvm-signal-title" type="text" maxlength="190" autocomplete="off" placeholder="Kort nieuwssignaal of opdracht…" data-mvm-signal-title required>
                            <label class="screen-reader-text" for="mvm-signal-type">Type signaal</label>
                            <select id="mvm-signal-type" data-mvm-signal-type>
                                <option value="news">Nieuws</option>
                                <option value="photo">Fotografie</option>
                                <option value="event">Evenement</option>
                                <option value="source">Bron</option>
                                <option value="general">Algemeen</option>
                            </select>
                            <button type="submit" class="mvm-hub4__button" data-mvm-signal-submit>Signaal toevoegen</button>
                        </form>
                    <?php endif; ?>
                </section>
            <?php endif; ?>

            <div class="mvm-hub4__status" data-mvm-status role="status" aria-live="polite"></div>
            <div class="mvm-hub4__platform-message" data-mvm-platform-message role="status" aria-live="polite"></div>
            <section class="mvm-hub4__platform-view" data-mvm-platform-container aria-live="polite" aria-busy="false" hidden></section>
            <section class="mvm-hub4__platform-view" data-mvm-service-container aria-live="polite" aria-busy="false" hidden></section>
            <section class="mvm-hub4__view" data-mvm-view-container aria-live="polite" aria-busy="true">
                <div class="mvm-hub4__loading" role="status">Hub laden…</div>
            </section>
        </main>
    </div>
</div>

<script nonce="<?php echo esc_attr( $nonce ); ?>">window.MvMHub4Config=<?php echo wp_json_encode( $config, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ); ?>;</script>
<script src="<?php echo esc_url( $app_js ); ?>" defer></script>
<script src="<?php echo esc_url( $my_work_js ); ?>" defer></script>
<script src="<?php echo esc_url( $platform_js ); ?>" defer></script>
<script src="<?php echo esc_url( $platform_services_js ); ?>" defer></script>
<script src="<?php echo esc_url( $local_ads_admin_js ); ?>" defer></script>
<?php if ( $can_view_news_radar ) : ?>
    <script src="<?php echo esc_url( $news_radar_js ); ?>" defer></script>
<?php endif; ?>
<script src="<?php echo esc_url( $signal_conversion_js ); ?>" defer></script>
</body>
</html>