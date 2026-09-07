'use strict';

const fs = require('node:fs');
const path = require('node:path');
const root = path.resolve(__dirname, '..');
const read = (relative) => fs.readFileSync(path.join(root, relative), 'utf8');

const bootstrap = read('plugins/mvm-hub/mvm-hub.php');
const kernel = read('plugins/mvm-hub/core/class-kernel.php');
const runtime = read('plugins/mvm-hub/core/class-hub-runtime.php');
const gateway = read('plugins/mvm-hub/core/class-staff-login-recaptcha.php');
const release = read('plugins/mvm-hub/release/class-release-control.php');
const controller = read('plugins/mvm-hub/release/class-release-control-rest-controller.php');

function fail(message) { console.error(`FAIL: ${message}`); process.exitCode = 1; }
function ok(condition, message) { if (!condition) fail(message); }

ok(bootstrap.includes("core/class-staff-login-recaptcha.php"), 'bootstrap must load the new-Hub-owned staff gateway hardener');
ok(kernel.includes('Staff_Login_Recaptcha::register();') && kernel.indexOf('Staff_Login_Recaptcha::register();') < kernel.indexOf('Hub_Runtime::register();'), 'staff gateway hardener must register before Hub route runtime');

ok(!runtime.includes('wp_login_url('), 'route takeover must not regress logged-out staff to the generic wp-login screen');
ok(runtime.includes('integrity-pinned adopted Hub 3 payload') && runtime.includes('wp_safe_redirect( Router::hub_url() )') && runtime.includes('return;'), 'logged-out /hub must preserve adopted branded gateway while nested routes return to it');

ok(gateway.includes("private const LOGIN_ACTION = 'mvm_hub_password_staff_login_v2'"), 'new Hub must own the adopted staff-login action');
ok(gateway.includes("add_action( 'wp_ajax_nopriv_' . self::LOGIN_ACTION") && gateway.includes("add_action( 'wp_ajax_' . self::LOGIN_ACTION"), 'staff authentication may expose only its dedicated logged-out AJAX boundary');
ok(!gateway.includes('wp_ajax_nopriv_mvm_nr2') && !gateway.includes('mvm_nr2_board_') && !gateway.includes('mvm_nr2_chat_'), 'staff gateway must never introduce guest Prikbord/Teamchat actions');
ok(gateway.includes("check_ajax_referer( 'mvm_hub_password_staff_login_v1', 'nonce', false )"), 'staff login must verify its nonce');
ok(gateway.includes("mvm_hub_password_gateway_proof_valid_v1") && gateway.includes("mvm_hub_password_recaptcha_verify_v12"), 'staff login must fail closed behind gateway proof and reCAPTCHA');
ok(gateway.includes("mvm_hub_auth_v4_login") && gateway.includes("mvm_hub_auth_v3_login") && gateway.includes("mvm_hub_auth_v2_login"), 'new Hub must delegate credentials to the newest integrity-pinned adopted handler');
ok(gateway.includes("remove_action( 'wp_ajax_nopriv_' . self::LOGIN_ACTION, array( 'MvM_Hub4_Staff_Login_Recaptcha', 'login' ) )"), 'new Hub must deterministically take login-gate ownership while Hub4 remains rollback-active');
ok(gateway.includes("array( 'message' => 'Inloggen is niet gelukt. Vernieuw de pagina en probeer opnieuw.' )"), 'authentication failures must remain generic and non-enumerating');
ok(gateway.includes("Audit::record(") && gateway.includes("'reason' => sanitize_key( $reason )") && !gateway.includes("$_POST['password']") && !gateway.includes("$_POST['identifier']"), 'new hardener must audit metadata only and never copy credentials');
ok(gateway.includes("dependencies_ready()") && gateway.includes("dependency_status()"), 'release preflight must be able to fail closed on adopted gateway dependency drift');

ok(release.includes("private const SCOPE_NEWSROOM2 = 'newsroom2'"), 'release control must define a dedicated Newsroom 2 scope');
ok(release.includes("return in_array( $flag, array( 'route_takeover', 'newsroom_writes', 'capability_reconciliation' ), true );"), 'Newsroom 2 scope may open only route/newsroom/capability flags');
ok(release.includes("'scope'               => $scope") && release.includes("array_key_exists( 'scope', $record )"), 'scoped release approval must be signed while remaining compatible with earlier rollback records');

ok(controller.includes("'/release/newsroom2-promote'"), 'controller must expose an explicit Newsroom 2 promotion route');
const start = controller.indexOf('public function newsroom2_promote');
const end = controller.indexOf('public function rollback', start);
ok(start >= 0 && end > start, 'Newsroom 2 promotion method boundary must exist');
const newsroomPromote = controller.slice(start, end);
ok(newsroomPromote.includes("'newsroom2'") && newsroomPromote.includes('Role_Capability_Bundles::reconcile_if_enabled()'), 'Newsroom 2 promotion must bind signed scope and reconcile schema v2');
ok(newsroomPromote.includes('Runtime_Gates::route_takeover_enabled()') && newsroomPromote.includes('Runtime_Gates::newsroom_writes_enabled()') && newsroomPromote.includes('Runtime_Gates::capability_reconciliation_enabled()'), 'Newsroom 2 postcheck must require its three intended gates');
ok(newsroomPromote.includes('Runtime_Gates::cron_takeover_enabled()') && newsroomPromote.includes('Runtime_Gates::communications_writes_enabled()') && newsroomPromote.includes('Runtime_Gates::mail_writes_enabled()'), 'Newsroom 2 postcheck must explicitly require cron, Communications and Mail to remain closed');
ok(!newsroomPromote.includes('Cron_Cutover::activate()'), 'Newsroom 2 promotion must never activate cron ownership');
ok(newsroomPromote.includes('Staff_Login_Recaptcha::dependencies_ready()'), 'Newsroom 2 promotion must require the secure staff gateway after promotion');
ok(newsroomPromote.includes("Release_Control::set_mode( 'rollback'") && newsroomPromote.includes("Audit::record( 'release.newsroom2_promote'"), 'Newsroom 2 promotion must rollback on failure and metadata-audit the result');
ok(controller.includes("! Staff_Login_Recaptcha::dependencies_ready()") && controller.includes("mvm_release_staff_gateway"), 'preflight must reject promotion when adopted gateway dependencies drift');

if (!process.exitCode) console.log('PASS: MvM Hub staff gateway ownership and Newsroom-only cutover contract');
