'use strict';

const fs = require('node:fs');
const path = require('node:path');
const root = path.resolve(__dirname, '..');
const read = (relative) => fs.readFileSync(path.join(root, relative), 'utf8');

const bootstrap = read('plugins/mvm-hub/mvm-hub.php');
const gates = read('plugins/mvm-hub/core/class-runtime-gates.php');
const kernel = read('plugins/mvm-hub/core/class-kernel.php');
const legacy = read('plugins/mvm-hub/core/class-legacy-services.php');
const cronCutover = read('plugins/mvm-hub/core/class-cron-cutover.php');
const bridge = read('plugins/mvm-hub/core/class-release-control.php');
const controllerBridge = read('plugins/mvm-hub/core/class-release-control-rest-controller.php');
const release = read('plugins/mvm-hub/release/class-release-control.php');
const controller = read('plugins/mvm-hub/release/class-release-control-rest-controller.php');

function fail(message) { console.error(`FAIL: ${message}`); process.exitCode = 1; }
function ok(condition, message) { if (!condition) fail(message); }

ok(bridge.includes("release/class-release-control.php"), 'core release bridge must delegate into isolated release boundary');
ok(controllerBridge.includes("release/class-release-control-rest-controller.php"), 'core release controller bridge must delegate into isolated release boundary');
ok(!/update_option|add_option|delete_option|\$wpdb\s*->\s*(?:insert|update|delete|replace)/m.test(bridge + controllerBridge), 'core release bridges must remain mutation-free');
ok(bootstrap.includes("core/class-release-control.php") && bootstrap.includes("core/class-release-control-rest-controller.php"), 'bootstrap must load release bridges before kernel boot');
ok(bootstrap.includes("core/class-cron-cutover.php"), 'bootstrap must load guarded cron cutover');

ok(release.includes("private const RELEASE_ID = 'hub4-decommission-rc1'"), 'release control must remain pinned to Hub4 decommission release id');
ok(release.includes("wp_salt( 'secure_auth' )") && release.includes("hash_hmac( 'sha256'") && release.includes('hash_equals('), 'release approval must remain HMAC signed and timing-safe');
ok(release.includes("private const SCOPE_COMMUNICATIONS = 'communications'") && release.includes("private const SCOPE_MAIL = 'mail'"), 'historical cumulative scopes must remain parseable for rollback compatibility');
ok(release.includes("if ( self::SCOPE_FULL === $scope )") && release.includes("array( 'route_takeover', 'newsroom_writes', 'capability_reconciliation', 'cron_takeover' )"), 'full release may only enable core takeover flags');
ok(release.includes("if ( self::SCOPE_COMMUNICATIONS === $scope )") && release.includes("return 'mail_writes' !== $flag;"), 'communications scope must keep historical mail flag closed');
ok(release.includes("return self::SCOPE_MAIL === $scope;"), 'historical mail scope must remain verifiable for existing signed records');
ok(release.includes("'live' === $mode") && release.includes("'artifact_sha256'") && release.includes("'inner_sha256'"), 'live promotion must bind signed record to verified hashes');
ok(release.includes("update_option( self::OPTION") && release.includes("'rollback'"), 'release boundary must retain signed rollback mode');

const routeMethod = gates.slice(gates.indexOf('public static function route_takeover_enabled'), gates.indexOf('public static function newsroom_writes_enabled'));
const newsroomMethod = gates.slice(gates.indexOf('public static function newsroom_writes_enabled'), gates.indexOf('public static function capability_reconciliation_enabled'));
const capsMethod = gates.slice(gates.indexOf('public static function capability_reconciliation_enabled'), gates.indexOf('public static function cron_takeover_enabled'));
const cronMethod = gates.slice(gates.indexOf('public static function cron_takeover_enabled'), gates.indexOf('public static function communications_writes_enabled'));
const communicationsMethod = gates.slice(gates.indexOf('public static function communications_writes_enabled'), gates.indexOf('public static function mail_writes_enabled'));
const mailMethod = gates.slice(gates.indexOf('public static function mail_writes_enabled'), gates.indexOf('private static function environment_gate'));
ok(routeMethod.includes("Release_Control::flag_enabled( 'route_takeover' )"), 'route takeover must support signed release control');
ok(newsroomMethod.includes("Release_Control::flag_enabled( 'newsroom_writes' )"), 'Newsroom writes must support signed release control');
ok(capsMethod.includes("Release_Control::flag_enabled( 'capability_reconciliation' )"), 'capability reconciliation must support signed release control');
ok(cronMethod.includes("Release_Control::flag_enabled( 'cron_takeover' )") && cronMethod.includes('Legacy_Services::persistent_ready()'), 'cron release gate must require signed control plus persistent payload');
ok(communicationsMethod.includes("Release_Control::flag_enabled( 'communications_writes' )"), 'internal Communications writes may use the signed communications scope');
ok(mailMethod.includes('if ( ! self::verified_mail_staging_host() )') && mailMethod.includes('return false;'), 'Mail writes must remain hard disabled unless the exact staging clone is verified');
ok(mailMethod.includes("return 'staging.mierlovoormierlo.nl' === $host;"), 'Mail rehearsal must be pinned to the exact staging hostname');
ok(!mailMethod.includes('wp_get_environment_type'), 'Mail rehearsal eligibility must not broaden to arbitrary non-production hosts');
ok(mailMethod.includes('MVM_HUB_ENABLE_STAGING_MAIL_WRITES') && mailMethod.includes("true !== constant( 'MVM_HUB_ENABLE_STAGING_MAIL_WRITES' )"), 'staging Mail rehearsal must require the explicit staging-only opt-in set to literal true');
ok(mailMethod.includes("Release_Control::flag_enabled( 'mail_writes' )"), 'staging Mail rehearsal must additionally require signed mail scope');
ok(!mailMethod.includes('MVM_HUB_ALLOW_PRODUCTION_MAIL_WRITES') && !mailMethod.includes('MVM_HUB_ENABLE_EXTERNAL_MAIL_DELIVERY'), 'Mail runtime must expose no production/config-only reopen path');

ok(kernel.includes("core/class-legacy-services.php") && kernel.includes('Legacy_Services::bootstrap();'), 'kernel must adopt legacy services before Hub runtime boot');
ok(legacy.includes("mvm-hub4-legacy/hub3") && legacy.includes('EXPECTED_TREE_SHA256'), 'legacy adopter must pin persistent Hub3 payload');
ok(!legacy.includes('MVM_HUB4_DIR') && !legacy.includes("mvm-hub4-rc-direct/legacy"), 'legacy adopter must never depend on Hub4 plugin files');
ok(cronCutover.includes('remove_all_actions') && cronCutover.includes('Cron_Ownership::ensure_schedules()'), 'cron cutover must replace old callbacks and preserve schedules');

ok(kernel.includes("( new Release_Control_REST_Controller() )->register_routes()"), 'kernel must register release-control REST surface');
['/release/status','/release/promote','/release/rollback'].forEach((route) => ok(controller.includes(`'${route}'`), `release controller missing ${route}`));
ok(controller.includes("current_user_can( 'manage_options' )") && controller.includes("current_user_can( 'activate_plugins' )"), 'release control must require manage_options and activate_plugins');
ok(controller.includes("Release_Control::release_id() !==") && controller.includes('backupConfirmed') && controller.includes('rest_sanitize_boolean'), 'promotion must require exact release id and confirmed backup');
ok(
  controller.includes("private const HUB4_PLUGIN = 'mvm-hub4-rc-direct/mvm-hub4.php'")
    && controller.includes('rollback_owner_status_for')
    && controller.includes("'adopted' !==")
    && controller.includes("'persistent' !==")
    && controller.includes("'mvm_release_rollback_owner'"),
  'promotion preflight must retain fail-closed rollback-owner checks for Hub4 or an integrity-checked persistent adopted owner'
);
ok(controller.includes('! Legacy_Services::persistent_ready()'), 'promotion preflight must require verified persistent Hub3 payload');
ok(controller.includes('Cron_Cutover::activate()') && controller.includes("Release_Control::set_mode( 'rollback'"), 'promotion must activate cron ownership and automatically rollback on failure');
ok(controller.includes('Runtime_Gates::communications_writes_enabled()') && controller.includes('Runtime_Gates::mail_writes_enabled()'), 'release preflight/status must inspect both Communications and Mail gates');
['sources','assignments','dossiers','signals'].forEach((key) => ok(controller.includes(`$counts['${key}']`), `promotion preflight missing ${key} baseline`));
ok(controller.includes('Role_Capability_Bundles::reconcile_if_enabled()') && controller.includes("Release_Control::set_mode( 'rollback'"), 'promotion must reconcile roles and rollback on drift');
ok(/Audit::record\(\s*'release\.promote'/m.test(controller) && /Audit::record\(\s*'release\.rollback'/m.test(controller), 'promotion and rollback must be metadata-audited');
ok(!/wp_mail\s*\(|phpmailer_init|MVM_HUB_ENABLE_COMMUNICATION_WRITES|MVM_HUB_ENABLE_EXTERNAL_MAIL_DELIVERY/m.test(release + controller), 'release boundary must not contain transport or constant override paths');

if (!process.exitCode) console.log('PASS: MvM Hub signed release control with production-closed, signed staging Mail rehearsal');
