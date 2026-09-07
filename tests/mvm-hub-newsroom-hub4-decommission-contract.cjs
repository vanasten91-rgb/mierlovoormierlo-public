'use strict';

const fs = require('node:fs');
const path = require('node:path');
const root = path.resolve(__dirname, '..');
const read = (relative) => fs.readFileSync(path.join(root, relative), 'utf8');

const bootstrap = read('plugins/mvm-hub/mvm-hub.php');
const kernel = read('plugins/mvm-hub/core/class-kernel.php');
const legacy = read('plugins/mvm-hub/core/class-legacy-services.php');
const gates = read('plugins/mvm-hub/core/class-runtime-gates.php');
const cron = read('plugins/mvm-hub/core/class-cron-ownership.php');
const cutover = read('plugins/mvm-hub/core/class-cron-cutover.php');
const release = read('plugins/mvm-hub/release/class-release-control.php');
const controller = read('plugins/mvm-hub/release/class-release-control-rest-controller.php');

function fail(message) { console.error(`FAIL: ${message}`); process.exitCode = 1; }
function ok(condition, message) { if (!condition) fail(message); }

ok(bootstrap.includes("Version: 0.1.0-alpha5") && bootstrap.includes("MVM_HUB_VERSION', '0.1.0-alpha5"), 'Hub4 decommission build must retain the alpha5 release identity');
ok(kernel.includes("require_once MVM_HUB_DIR . 'core/class-legacy-services.php';") && kernel.indexOf('Legacy_Services::bootstrap();') < kernel.indexOf('final class Kernel'), 'legacy services must bootstrap before the central Hub runtime');

ok(legacy.includes("public const LEGACY_VERSION = '3.8.2'"), 'legacy service version must be pinned');
ok(legacy.includes("70673d5bdbe9c107103adb5eeccd4b55754b766729a0f21630b3ab6bb346fc39"), 'legacy service tree hash must be pinned');
ok(legacy.includes("mvm-hub4-legacy/hub3"), 'decommission must depend on the persistent service payload');
ok(legacy.includes("modules/runtime-patches/auth/010-258.php") && legacy.includes("modules/runtime-patches/auth/010-288.php"), 'critical auth patches must be integrity checked');
ok(!legacy.includes('MVM_HUB4_DIR') && !legacy.includes("mvm-hub4-rc-direct/legacy"), 'new Hub must not load services from the removable Hub4 plugin directory');
ok(legacy.includes("'hub4PluginRequired' => false"), 'legacy status must explicitly declare Hub4 plugin files unnecessary');
ok(legacy.includes("'wordpress_paths_unavailable'"), 'minimal/bootstrap contexts must fail closed when WordPress content paths are absent');

ok(release.includes("private const RELEASE_ID = 'hub4-decommission-rc1'"), 'signed release id must remain unique to Hub4 decommission');
ok(release.includes("'cron_takeover'"), 'signed decommission release must include cron takeover');
ok(release.includes("private const SCOPE_COMMUNICATIONS = 'communications'") && release.includes("private const SCOPE_MAIL = 'mail'"), 'communications/mail scopes must remain explicit for signed-state compatibility');
ok(release.includes("if ( self::SCOPE_FULL === $scope )") && release.includes("array( 'route_takeover', 'newsroom_writes', 'capability_reconciliation', 'cron_takeover' )"), 'full decommission scope must still keep communications and mail flags closed');

const cronGate = gates.slice(gates.indexOf('public static function cron_takeover_enabled'), gates.indexOf('public static function communications_writes_enabled'));
ok(cronGate.includes("Release_Control::flag_enabled( 'cron_takeover' )") && cronGate.includes('Legacy_Services::persistent_ready()'), 'cron takeover must require signed approval plus persistent payload integrity');
const communicationsGate = gates.slice(gates.indexOf('public static function communications_writes_enabled'), gates.indexOf('public static function mail_writes_enabled'));
const mailGate = gates.slice(gates.indexOf('public static function mail_writes_enabled'), gates.indexOf('private static function environment_gate'));
ok(communicationsGate.includes("Release_Control::flag_enabled( 'communications_writes' )"), 'internal Communications writes may open only through their explicit environment/signed gate');
ok(mailGate.includes('if ( ! self::verified_mail_staging_host() )') && mailGate.includes('return false;'), 'MvM Mail must remain hard read-only unless the exact staging clone is verified');
ok(mailGate.includes("return 'staging.mierlovoormierlo.nl' === $host;"), 'Mail rehearsal must be pinned to the exact staging hostname');
ok(!mailGate.includes('wp_get_environment_type'), 'Mail rehearsal eligibility must not broaden to arbitrary non-production hosts');
ok(mailGate.includes('MVM_HUB_ENABLE_STAGING_MAIL_WRITES') && mailGate.includes("true !== constant( 'MVM_HUB_ENABLE_STAGING_MAIL_WRITES' )"), 'staging Mail rehearsal must require the explicit staging-only constant set to literal true');
ok(mailGate.includes("Release_Control::flag_enabled( 'mail_writes' )"), 'staging Mail rehearsal must additionally require signed mail scope');
ok(!mailGate.includes('MVM_HUB_ALLOW_PRODUCTION_MAIL_WRITES') && !mailGate.includes('MVM_HUB_ENABLE_EXTERNAL_MAIL_DELIVERY'), 'no production/config-only Mail override may exist');

['mvm_newsradar_staggered_v2','mvm_newsradar_manual_source_v1','mvm_hub_ai_agenda_scan_v1','mvm_hub4_audit_cleanup'].forEach((hook) => ok(cron.includes(hook), `cron owner missing ${hook}`));
ok(cutover.includes('remove_all_actions') && cutover.includes('Cron_Ownership::ensure_schedules()'), 'cutover must replace legacy callbacks and preserve scheduled events');
ok(cutover.includes('Legacy_Services::persistent_ready()'), 'cutover itself must recheck persistent payload integrity');

ok(controller.includes('! Legacy_Services::persistent_ready()'), 'promotion preflight must block Hub4 retirement without persistent payload');
ok(controller.includes('Cron_Cutover::activate()'), 'full promotion must retain the separate cron ownership path');
ok(controller.includes("Release_Control::set_mode( 'rollback'"), 'promotion failures must return signed release state to rollback');
ok(controller.includes("private const HUB4_PLUGIN = 'mvm-hub4-rc-direct/mvm-hub4.php'"), 'Hub4 must still be present as rollback owner during legacy promotion');
ok(controller.includes("'legacyServices' => Legacy_Services::status()") && controller.includes("'cron' => Cron_Ownership::status()"), 'release status must expose legacy-payload and cron ownership diagnostics');
ok(controller.includes("'communications_writes' => false") && controller.includes("'mail_writes' => false"), 'decommission promotion audit must record communications/mail as closed at that phase');
ok(!/wp_mail\s*\(|phpmailer_init|MVM_HUB_ENABLE_COMMUNICATION_WRITES|MVM_HUB_ENABLE_EXTERNAL_MAIL_DELIVERY/m.test(legacy + cutover + release + controller), 'decommission boundary must not add a mail-send or production constant-override path');

if (!process.exitCode) console.log('PASS: MvM Hub4 decommission dependency with production-closed / exact-staging signed Mail contract');
