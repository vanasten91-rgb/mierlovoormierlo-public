<?php
/**
 * Plugin Name: MvM Nieuws/Redactie Hub
 * Description: Security-first newsroom, beheerwerkplek en geïntegreerd lokaal MvM-platform.
 * Version: 1.3.0-rc6
 * Author: Mierlo voor Mierlo
 * Requires at least: 7.1
 * Requires PHP: 8.4
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'MVM_HUB4_VERSION', '1.3.0-rc6' );
define( 'MVM_HUB4_FILE', __FILE__ );
define( 'MVM_HUB4_DIR', plugin_dir_path( __FILE__ ) );
define( 'MVM_HUB4_URL', plugin_dir_url( __FILE__ ) );
define( 'MVM_HUB4_HUB_CAPS_SCHEMA', '2026.09.01-2' );

// Canonical MvM Nieuws/Redactie Hub classes can always load from the plugin.
require_once MVM_HUB4_DIR . 'src/compat/class-hub-security.php';
require_once MVM_HUB4_DIR . 'src/compat/class-hub-audit.php';
require_once MVM_HUB4_DIR . 'src/compat/class-smart-links-assets.php';
require_once MVM_HUB4_DIR . 'src/compat/class-theme-guard.php';
require_once MVM_HUB4_DIR . 'src/compat/class-mail-readonly-guard.php';
require_once MVM_HUB4_DIR . 'src/compat/class-encyclopedia-runtime.php';

/**
 * Load the function-based compatibility UI only when it is safe for the plugin
 * to own those global names. The class-based security/audit layer remains
 * plugin-owned in both transition modes.
 */
function mvm_hub4_load_function_compat(): void {
    if ( function_exists( 'mvm_hubs_v3_routes' ) ) {
        return;
    }

    require_once MVM_HUB4_DIR . 'src/compat/mvm-news-redactie-hub-help.php';
    require_once MVM_HUB4_DIR . 'src/compat/mvm-news-redactie-hub-onboarding.php';
    require_once MVM_HUB4_DIR . 'src/compat/mvm-news-redactie-hub-organization-requests.php';
    require_once MVM_HUB4_DIR . 'src/compat/mvm-news-redactie-hub-ui.php';
}

// WordPress activates a plugin inside a request where the active theme may
// already be loaded. If the legacy theme functions already exist, never
// redeclare them. On normal requests a guarded theme lets Hub 1.3 load its
// canonical functions before the theme. An unguarded legacy theme remains the
// temporary function owner until after_setup_theme, where the plugin only fills
// the gap if the theme did not provide them.
if ( function_exists( 'mvm_hubs_v3_routes' ) ) {
    // Activation request with the legacy theme already loaded: intentionally no-op.
} elseif ( MvM_Hub4_Theme_Guard::active_theme_has_hub_guard() ) {
    mvm_hub4_load_function_compat();
} else {
    add_action( 'after_setup_theme', 'mvm_hub4_load_function_compat', PHP_INT_MAX );
}

require_once MVM_HUB4_DIR . 'src/class-legacy-services.php';
MvM_Hub4_Legacy_Services::bootstrap();
MvM_Hub4_Mail_Readonly_Guard::boot();

require_once MVM_HUB4_DIR . 'src/class-audit.php';
require_once MVM_HUB4_DIR . 'src/class-session.php';
require_once MVM_HUB4_DIR . 'src/class-security.php';
require_once MVM_HUB4_DIR . 'src/class-response-hardening.php';
require_once MVM_HUB4_DIR . 'src/class-capabilities.php';
require_once MVM_HUB4_DIR . 'src/class-sources.php';
require_once MVM_HUB4_DIR . 'src/class-source-repository.php';
require_once MVM_HUB4_DIR . 'src/class-assignments.php';
require_once MVM_HUB4_DIR . 'src/class-publication-checklist.php';
require_once MVM_HUB4_DIR . 'src/class-system-stats.php';
require_once MVM_HUB4_DIR . 'src/class-health-route-checks.php';
require_once MVM_HUB4_DIR . 'src/class-health-check.php';
require_once MVM_HUB4_DIR . 'src/class-news-repository.php';
require_once MVM_HUB4_DIR . 'src/class-news-categories.php';
require_once MVM_HUB4_DIR . 'src/class-agenda-repository.php';
require_once MVM_HUB4_DIR . 'src/class-team-repository.php';
require_once MVM_HUB4_DIR . 'src/class-tools.php';
require_once MVM_HUB4_DIR . 'src/class-workflow-guide.php';
require_once MVM_HUB4_DIR . 'src/class-rest.php';
require_once MVM_HUB4_DIR . 'src/class-newsroom-rest.php';
require_once MVM_HUB4_DIR . 'src/class-ai-agenda-radar.php';
require_once MVM_HUB4_DIR . 'src/class-ai-agenda-radar-rest.php';
require_once MVM_HUB4_DIR . 'src/class-system-stats-rest.php';
require_once MVM_HUB4_DIR . 'src/class-health-check-rest.php';
require_once MVM_HUB4_DIR . 'src/class-launch.php';
require_once MVM_HUB4_DIR . 'src/class-source-launch-links.php';
require_once MVM_HUB4_DIR . 'src/class-admin-skin.php';
require_once MVM_HUB4_DIR . 'src/class-editor-guard.php';
require_once MVM_HUB4_DIR . 'src/class-editor-checklist.php';
require_once MVM_HUB4_DIR . 'src/class-auth-audit.php';
require_once MVM_HUB4_DIR . 'src/class-admin-audit.php';
require_once MVM_HUB4_DIR . 'src/class-contrast.php';
require_once MVM_HUB4_DIR . 'src/class-staff-login-recaptcha.php';
require_once MVM_HUB4_DIR . 'src/class-site-integrations-v1.php';
require_once MVM_HUB4_DIR . 'src/class-frontend-repairs.php';
require_once MVM_HUB4_DIR . 'src/class-forum-layout.php';
require_once MVM_HUB4_DIR . 'src/class-encyclopedia-theme.php';
require_once MVM_HUB4_DIR . 'src/class-encyclopedia-detail-theme.php';
require_once MVM_HUB4_DIR . 'src/class-contact-theme.php';
require_once MVM_HUB4_DIR . 'src/class-homepage-contrast.php';
require_once MVM_HUB4_DIR . 'src/class-newsradar-local-scope.php';
require_once MVM_HUB4_DIR . 'src/class-newsradar-source-policy.php';
require_once MVM_HUB4_DIR . 'src/class-newsradar-schedule.php';
require_once MVM_HUB4_DIR . 'src/class-newsradar-source-actions.php';
require_once MVM_HUB4_DIR . 'src/class-newsradar-curated-sources.php';
require_once MVM_HUB4_DIR . 'src/class-newsradar-source-policy-reconcile.php';
require_once MVM_HUB4_DIR . 'src/class-peepso-event-comments.php';
require_once MVM_HUB4_DIR . 'src/class-peepso-event-dedupe.php';
require_once MVM_HUB4_DIR . 'src/class-peepso-login-redirect.php';
require_once MVM_HUB4_DIR . 'src/class-app.php';
require_once MVM_HUB4_DIR . 'src/class-site-canonical.php';
require_once MVM_HUB4_DIR . 'src/class-canonical-hub.php';

require_once MVM_HUB4_DIR . 'src/class-platform-schema.php';
require_once MVM_HUB4_DIR . 'src/class-signals.php';
require_once MVM_HUB4_DIR . 'src/class-dossiers.php';
require_once MVM_HUB4_DIR . 'src/class-editorial-calendar.php';
require_once MVM_HUB4_DIR . 'src/class-media-desk.php';
require_once MVM_HUB4_DIR . 'src/class-distribution.php';
require_once MVM_HUB4_DIR . 'src/class-corrections.php';
require_once MVM_HUB4_DIR . 'src/class-personalization.php';
require_once MVM_HUB4_DIR . 'src/class-source-radar.php';
require_once MVM_HUB4_DIR . 'src/class-legacy-newsradar.php';
require_once MVM_HUB4_DIR . 'src/class-legacy-newsradar-writes.php';
require_once MVM_HUB4_DIR . 'src/class-dashboard.php';
require_once MVM_HUB4_DIR . 'src/class-platform-object-access.php';
require_once MVM_HUB4_DIR . 'src/class-platform-writes.php';
require_once MVM_HUB4_DIR . 'src/class-abilities.php';
require_once MVM_HUB4_DIR . 'src/class-platform-rest.php';
require_once MVM_HUB4_DIR . 'src/class-public-rest.php';
require_once MVM_HUB4_DIR . 'src/class-public-ui.php';
require_once MVM_HUB4_DIR . 'src/class-public-sharing.php';
require_once MVM_HUB4_DIR . 'src/class-signal-conversion.php';
require_once MVM_HUB4_DIR . 'src/class-personalized-feed-ui.php';

require_once MVM_HUB4_DIR . 'src/class-integrated-platform.php';
MvM_Hub4_Integrated_Platform::load();

// Hub 1.2.0: first-party copies of the former production Code Snippets, retained for rollback.
// Each module remains guarded by its original snippet ID until that snippet is
// explicitly disabled after its smoke-test, preventing duplicate runtime hooks.
require_once MVM_HUB4_DIR . 'src/consolidated-snippets/bootstrap.php';

function mvm_hub4_activate(): void {
    MvM_Hub4_Hub_Security::activate();
    MvM_Hub4_Capabilities::activate();
    MvM_Hub4_Audit::activate();
    MvM_Hub4_Sources::activate();
    MvM_Hub4_Assignments::activate();
    MvM_Hub4_Publication_Checklist::activate();
    MvM_Hub4_Platform_Schema::activate();
    MvM_Hub4_Integrated_Platform::activate();
    MvM_Hub4_Encyclopedia_Runtime::activate();
    MvM_Hub4_Launch::activate();
    MvM_Hub4_App::activate();
}

function mvm_hub4_deactivate(): void {
    MvM_Hub4_Integrated_Platform::deactivate();
    MvM_Hub4_Newsradar_Schedule::deactivate();
    MvM_Hub4_AI_Agenda_Radar::deactivate();
    wp_clear_scheduled_hook( MvM_Hub4_Audit::CRON_HOOK );
    flush_rewrite_rules( false );
}

register_activation_hook( __FILE__, 'mvm_hub4_activate' );
register_deactivation_hook( __FILE__, 'mvm_hub4_deactivate' );

add_action(
    'plugins_loaded',
    static function (): void {
        MvM_Hub4_Hub_Security::boot();
        MvM_Hub4_Hub_Audit::boot();
        MvM_Hub4_Smart_Links_Assets::boot();
        MvM_Hub4_Audit::init();
        MvM_Hub4_Capabilities::init();
        MvM_Hub4_Platform_Schema::init();
        MvM_Hub4_Security::init();
        MvM_Hub4_News_Categories::init();
        MvM_Hub4_Auth_Audit::init();
        MvM_Hub4_Admin_Audit::init();
        MvM_Hub4_REST::init();
        MvM_Hub4_Newsroom_REST::init();
        MvM_Hub4_AI_Agenda_Radar::init();
        MvM_Hub4_AI_Agenda_Radar_REST::init();
        MvM_Hub4_System_Stats_REST::init();
        MvM_Hub4_Health_Check_REST::init();
        MvM_Hub4_Platform_REST::init();
        MvM_Hub4_Public_REST::init();
        MvM_Hub4_Public_UI::init();
        MvM_Hub4_Public_Sharing::init();
        MvM_Hub4_Signal_Conversion::init();
        MvM_Hub4_Personalized_Feed_UI::init();
        MvM_Hub4_Launch::init();
        MvM_Hub4_Source_Launch_Links::init();
        MvM_Hub4_Response_Hardening::init();
        MvM_Hub4_Admin_Skin::init();
        MvM_Hub4_Editor_Guard::init();
        MvM_Hub4_Editor_Checklist::init();
        MvM_Hub4_Contrast::init();
        MvM_Hub4_Staff_Login_Recaptcha::init();
        MvM_Hub4_Site_Integrations_V1::init();
        MvM_Hub4_Frontend_Repairs::init();
        MvM_Hub4_Forum_Layout::init();
        MvM_Hub4_Encyclopedia_Runtime::init();
        MvM_Hub4_Encyclopedia_Theme::init();
        MvM_Hub4_Encyclopedia_Detail_Theme::init();
        MvM_Hub4_Contact_Theme::init();
        MvM_Hub4_Homepage_Contrast::init();
        MvM_Hub4_Newsradar_Schedule::init();
        MvM_Hub4_Newsradar_Source_Actions::init();
        MvM_Hub4_Newsradar_Curated_Sources::init();
        MvM_Hub4_Newsradar_Source_Policy_Reconcile::init();
        MvM_Hub4_PeepSo_Event_Comments::init();
        MvM_Hub4_PeepSo_Event_Dedupe::init();
        MvM_Hub4_PeepSo_Login_Redirect::init();
        MvM_Hub4_Integrated_Platform::boot();
        MvM_Hub4_Abilities::init();
        MvM_Hub4_App::init();
        MvM_Hub4_Site_Canonical::init();
        MvM_Hub4_Canonical_Hub::init();
    },
    20
);
