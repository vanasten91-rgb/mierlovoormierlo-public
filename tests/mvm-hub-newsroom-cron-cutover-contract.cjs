'use strict';

const fs = require('node:fs');
const path = require('node:path');
const root = process.argv[2] ? path.resolve(process.argv[2]) : path.resolve(__dirname, '..');
const read = (relative) => fs.readFileSync(path.join(root, relative), 'utf8');

const ability = read('plugins/mvm-hub/core/class-release-control-abilities.php');
const cronRelease = read('plugins/mvm-hub/core/class-cron-release.php');
const cronOwnership = read('plugins/mvm-hub/core/class-cron-ownership.php');
const cronCutover = read('plugins/mvm-hub/core/class-cron-cutover.php');

function fail(message) { console.error(`FAIL: ${message}`); process.exitCode = 1; }
function ok(condition, message) { if (!condition) fail(message); }

ok(ability.includes("require_once __DIR__ . '/class-cron-release.php';"), 'release ability surface must load the cron release continuation');
ok(/private const ABILITY_CRON\s*=\s*'mvm-hub\/promote-cron'/.test(ability), 'cron promotion ability must have a stable namespaced name');
ok(ability.includes("'execute_callback'    => array( self::class, 'execute_cron_promote' )"), 'cron ability must delegate to a dedicated execution method');
ok(ability.includes('return Cron_Release::promote( $input );'), 'cron ability must delegate mutation logic to Cron_Release');
ok(!/Release_Control::set_mode|Cron_Cutover::activate/m.test(ability), 'ability wrapper itself must not mutate release or cron ownership');

ok(cronRelease.includes("'newsroom2' !== (string) ( $release['scope'] ?? '' )"), 'cron handoff must require an already-live Newsroom2 scope');
ok(cronRelease.includes("is_plugin_active( self::HUB4_PLUGIN )"), 'Hub4 must remain active as rollback owner during cron promotion');
ok(cronRelease.includes("Release_Control::set_mode( 'live', get_current_user_id(), $artifact, $inner, 'full' )"), 'cron promotion must upgrade the signed scope to full');
ok(cronRelease.includes('Cron_Cutover::activate()'), 'cron promotion must transfer callbacks through Cron_Cutover');
ok(cronRelease.includes("Release_Control::set_mode( 'live', get_current_user_id(), $artifact, $inner, 'newsroom2' )"), 'failed cron handoff must restore Newsroom2 scope rather than full rollback');
ok(!cronRelease.includes("Release_Control::set_mode( 'rollback'"), 'cron continuation must never disable an already-working Newsroom2 release');

ok(cronRelease.includes("! empty( $cron['newsradar']['nextRun'] )"), 'cron postcheck must require a Newsradar next run');
ok(cronRelease.includes("empty( $cron['aiAgenda']['enabled'] )") && cronRelease.includes("! empty( $cron['aiAgenda']['nextRun'] )"), 'enabled AI agenda must retain a next run');
ok(cronRelease.includes("! empty( $cron['audit']['nextRun'] )"), 'cron postcheck must require an audit cleanup next run');
ok(!/ai_ready\s*=/.test(cronRelease), 'AI provider readiness must be diagnostic, not a hard cron handoff blocker');
ok(cronRelease.includes("'ai_agenda_provider_ready'"), 'AI provider readiness must still be recorded diagnostically');

ok(cronRelease.includes('! Runtime_Gates::communications_writes_enabled()') && cronRelease.includes('! Runtime_Gates::mail_writes_enabled()'), 'cron handoff must keep Communications and Mail closed');
ok(cronRelease.includes('Staff_Login_Recaptcha::dependencies_ready()'), 'cron handoff must preserve the secured staff gateway');
ok(cronRelease.includes('Legacy_Services::persistent_ready()'), 'cron handoff must require the pinned persistent legacy payload');
ok(cronRelease.includes("$counts['sources'] < 238") && cronRelease.includes("$counts['assignments'] < 3") && cronRelease.includes("$counts['dossiers'] < 1") && cronRelease.includes("$counts['signals'] < 1"), 'cron preflight must preserve the validated production baseline');

['mvm_newsradar_staggered_v2','mvm_newsradar_manual_source_v1','mvm_hub_ai_agenda_scan_v1','mvm_hub4_audit_cleanup'].forEach((hook) => ok(cronOwnership.includes(hook), `cron owner missing ${hook}`));
ok(cronCutover.includes('remove_all_actions') && cronCutover.includes('Cron_Ownership::ensure_schedules()'), 'cron cutover must replace legacy callbacks while retaining/recreating schedules');

if (!process.exitCode) console.log('PASS: MvM Hub cron-only continuation and safe Hub4 retirement contract');
