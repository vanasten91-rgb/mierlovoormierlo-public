'use strict';

const fs = require('node:fs');
const path = require('node:path');
const root = path.resolve(__dirname, '..');
const read = (relative) => fs.readFileSync(path.join(root, relative), 'utf8');

const gates = read('plugins/mvm-hub/core/class-runtime-gates.php');
const runtime = read('plugins/mvm-hub/core/class-hub-runtime.php');
const workspaces = read('plugins/mvm-hub/core/class-workspaces.php');
const communicationsRuntime = read('plugins/mvm-hub/modules/communications/class-communications-runtime-renderer.php');
const mailReadService = read('plugins/mvm-hub/modules/communications/class-mail-read-service.php');
const internalMessageReadService = read('plugins/mvm-hub/modules/communications/class-internal-message-read-service.php');
const kernel = read('plugins/mvm-hub/core/class-kernel.php');
const router = read('plugins/mvm-hub/core/class-router.php');
const shellRenderer = read('plugins/mvm-hub/core/class-shell-renderer.php');
const bootstrap = read('plugins/mvm-hub/mvm-hub.php');

function fail(message) { console.error(`FAIL: ${message}`); process.exitCode = 1; }
function ok(condition, message) { if (!condition) fail(message); }

const routeGate = gates.slice(gates.indexOf('public static function route_takeover_enabled'), gates.indexOf('public static function communications_writes_enabled'));
ok(routeGate.includes("'MVM_HUB_ENABLE_ROUTE_TAKEOVER'"), 'route takeover must require explicit enablement');
ok(routeGate.includes("'MVM_HUB_ALLOW_PRODUCTION_ROUTE_TAKEOVER'"), 'production route takeover must require separate acknowledgement');
ok(routeGate.includes('self::environment_gate('), 'route takeover must use the shared fail-closed environment gate');
ok(!/COMMUNICATION_WRITES|EXTERNAL_MAIL_DELIVERY/.test(routeGate), 'route takeover must not depend on or enable communications writes/delivery');

ok(kernel.includes('if ( Runtime_Gates::route_takeover_enabled() )'), 'kernel must register Hub runtime only behind route gate');
ok(kernel.includes('Staff_Login_Recaptcha::register();') && kernel.includes('Hub_Runtime::register()'), 'kernel must register the secure staff gateway before Hub runtime ownership');
ok(runtime.includes("add_action( 'template_redirect'"), 'staged runtime must hook only after route gate is open');
ok(runtime.includes('! Runtime_Gates::route_takeover_enabled() || ! Router::is_hub_request()'), 'runtime must recheck gate and exact Hub request before rendering');
ok(!/add_rewrite_rule|flush_rewrite_rules/m.test(runtime + router), 'Hub runtime must not add or flush rewrite rules');

ok(runtime.includes('! is_user_logged_in()'), 'Hub runtime must explicitly handle anonymous requests');
ok(!runtime.includes('wp_login_url('), 'anonymous Hub requests must not regress to the generic WordPress login flow');
ok(runtime.includes('integrity-pinned adopted Hub 3 payload'), 'logged-out /hub/ must preserve the integrity-pinned branded staff gateway');
ok(runtime.includes('wp_safe_redirect( Router::hub_url() )'), 'anonymous nested Hub requests must return to the canonical staff gateway');
ok(runtime.includes('Workspaces::can_access( $requested_workspace )'), 'explicit standalone workspace routes must enforce workspace capability access');
ok(runtime.includes('Workspace_Sections::can_access( $requested_workspace, $requested_section )'), 'explicit standalone section routes must enforce section capability access');
ok(runtime.includes("return new \\WP_Error( 'mvm_hub_runtime_path'"), 'unknown Hub paths must fail closed');
ok(runtime.includes('status_header( 404 )'), 'unknown/unauthorized Hub routes must not silently fall back to another workspace');

ok(runtime.includes('Workspace_Sections::all()'), 'section paths must be resolved from the central allowlisted section registry');
ok(runtime.includes('Workspaces::all()'), 'workspace paths must be resolved from the central allowlisted workspace registry');
ok(!/\$_GET|\$_POST/m.test(runtime), 'runtime route selection must not trust arbitrary query/body input');

ok(runtime.includes('Hub_Security_Policy::response_headers()'), 'standalone Hub runtime must apply private security headers');
ok(runtime.includes("header( 'Content-Type: text/html; charset='"), 'standalone runtime must set an explicit HTML content type');
ok(!/wp_enqueue_scripts|admin_enqueue_scripts/m.test(runtime), 'route runtime must not install site-wide enqueue hooks');

// Newsroom 2.0 is intentionally backend-only. Legacy /hub/nieuwsroom URLs remain
// allowlisted only for compatibility and hand authorized staff to the private
// wp-admin Newsroom. The standalone shell must never render or load its editor.
ok(runtime.includes("if ( 'newsroom' === $requested_workspace )"), 'legacy Newsroom routes need an explicit backend handoff');
ok(runtime.includes('Capabilities::can_access_newsroom()'), 'Newsroom handoff must recheck Newsroom capability access');
ok(/self::send_private_headers\(\);\s*wp_safe_redirect\( admin_url\( 'admin\.php\?page=mvm-newsroom' \) \);/m.test(runtime), 'Newsroom handoff must send private headers before the safe backend redirect');
ok(runtime.includes("wp_safe_redirect( admin_url( 'admin.php?page=mvm-newsroom' ) )"), 'authorized legacy Newsroom routes must redirect to wp-admin Newsroom');
ok(!runtime.includes('Newsroom_Runtime_Renderer::render('), 'standalone /hub/ runtime must not render Newsroom 2.0');
ok(!runtime.includes('newsroomScript'), 'standalone /hub/ runtime must not load the Newsroom editor client');
ok(!runtime.includes('newsroomRuntimeStyle'), 'standalone /hub/ runtime must not load Newsroom editor runtime CSS');
ok(workspaces.includes("if ( 'newsroom' === $key )"), 'Newsroom must be hidden from standalone /hub/ workspace navigation');
ok(kernel.includes('Newsroom_Admin::register();'), 'kernel must register the private backend Newsroom');

ok(runtime.includes('Communications_Runtime_Renderer::render( $active_section )'), 'staging route must still render Communications through the gated standalone runtime');
ok(communicationsRuntime.includes('Communications_Service_Factory::mail_read_service()'), 'Communications runtime mail must use the central read-service boundary');
ok(communicationsRuntime.includes('Communications_Service_Factory::internal_message_read_service()'), 'Communications runtime messages must use the central participant-scoped read-service boundary');
ok(mailReadService.includes('Mail_Read_Projector::message_list') && mailReadService.includes('Mail_Read_Projector::message_detail'), 'runtime mail reads must remain privacy projected');
ok(internalMessageReadService.includes('Internal_Message_Projector::thread_list') && internalMessageReadService.includes('Internal_Message_Projector::thread_detail'), 'runtime internal-message reads must remain privacy projected');
ok(shellRenderer.includes('string $module_html ='), 'shell renderer must accept server-rendered module content explicitly');
ok(shellRenderer.includes('only trusted server renderers may supply this parameter'), 'module HTML injection boundary must be documented as trusted server-rendered only');

ok(!/MVM_HUB_ENABLE_COMMUNICATION_WRITES|MVM_HUB_ENABLE_EXTERNAL_MAIL_DELIVERY/m.test(runtime), 'route runtime must never set or inspect communications write/delivery enable constants');
ok(bootstrap.includes("core/class-hub-runtime.php"), 'bootstrap must load Hub runtime before kernel boot');
ok(bootstrap.includes("core/class-staff-login-recaptcha.php"), 'bootstrap must load secure staff gateway ownership before kernel boot');
ok(bootstrap.includes('class-communications-runtime-renderer.php'), 'bootstrap must load Communications runtime renderer before kernel boot');

if (!process.exitCode) console.log('PASS: MvM Hub double-gated standalone route runtime, secure staff gateway and backend Newsroom handoff contract');
