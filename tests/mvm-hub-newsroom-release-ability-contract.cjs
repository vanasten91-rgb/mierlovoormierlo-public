'use strict';

const fs = require('node:fs');
const path = require('node:path');
const root = process.argv[2] ? path.resolve(process.argv[2]) : path.resolve(__dirname, '..');
const read = (relative) => fs.readFileSync(path.join(root, relative), 'utf8');

const bootstrap = read('plugins/mvm-hub/mvm-hub.php');
const kernel = read('plugins/mvm-hub/core/class-kernel.php');
const ability = read('plugins/mvm-hub/core/class-release-control-abilities.php');
const controller = read('plugins/mvm-hub/release/class-release-control-rest-controller.php');

function fail(message) { console.error(`FAIL: ${message}`); process.exitCode = 1; }
function ok(condition, message) { if (!condition) fail(message); }

ok(bootstrap.includes("core/class-release-control-abilities.php"), 'bootstrap must load release abilities before kernel boot');
ok(kernel.includes('Release_Control_Abilities::register();'), 'kernel must register the WordPress Ability surface');
ok(ability.includes("'wp_abilities_api_categories_init'") && ability.includes("'wp_abilities_api_init'"), 'ability surface must register on the dedicated WordPress Abilities hooks');
ok(ability.includes("private const CATEGORY = 'mvm-hub-release'") && /private const ABILITY_NEWSROOM2\s*=\s*'mvm-hub\/promote-newsroom2'/.test(ability), 'Newsroom ability name must remain stable and namespaced');
ok(ability.includes("'show_in_rest' => true") && /'public'\s*=>\s*true/.test(ability), 'release abilities must be discoverable to authenticated clients');
ok(ability.includes("current_user_can( 'manage_options' )") && ability.includes("current_user_can( 'activate_plugins' )"), 'release ability must require administrative plugin permissions');
ok(/'readonly'\s*=>\s*false/.test(ability) && /'destructive'\s*=>\s*false/.test(ability) && /'idempotent'\s*=>\s*true/.test(ability), 'release ability annotations must describe a reversible idempotent state transition');
ok(ability.includes("new Release_Control_REST_Controller()") && ability.includes('->newsroom2_promote( $request )'), 'Newsroom ability must delegate to the existing signed Newsroom2 release controller');
ok(!/Release_Control::set_mode|Role_Capability_Bundles::reconcile_if_enabled|Cron_Cutover::activate/m.test(ability), 'ability wrappers must not duplicate release mutation logic');
ok(controller.includes("'newsroom2'") && controller.includes('Runtime_Gates::cron_takeover_enabled()') && controller.includes('Runtime_Gates::communications_writes_enabled()') && controller.includes('Runtime_Gates::mail_writes_enabled()'), 'delegated controller must preserve Newsroom-only scope and closed cron/communications/mail postchecks');

if (!process.exitCode) console.log('PASS: MvM Hub WordPress Ability release contract');
