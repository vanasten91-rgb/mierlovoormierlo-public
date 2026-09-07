'use strict';

const fs = require('node:fs');
const path = require('node:path');

const root = path.resolve(__dirname, '..');

function fail(message) {
  console.error(`FAIL: ${message}`);
  process.exitCode = 1;
}

function ok(condition, message) {
  if (!condition) fail(message);
}

function read(relative) {
  return fs.readFileSync(path.join(root, relative), 'utf8');
}

const bootstrap = read('plugins/mvm-hub/mvm-hub.php');
const capabilities = read('plugins/mvm-hub/core/class-capabilities.php');
const workspaces = read('plugins/mvm-hub/core/class-workspaces.php');
const shell = read('plugins/mvm-hub/core/class-shell.php');
const css = read('plugins/mvm-hub/assets/hub.css');
const readModel = read('plugins/mvm-hub/modules/newsroom/read/class-newsroom-read-model.php');
const readController = read('plugins/mvm-hub/modules/newsroom/read/class-newsroom-read-rest-controller.php');
const moduleFile = read('plugins/mvm-hub/modules/newsroom/class-newsroom-module.php');

// Central shell is present but still inert: no production route or asset ownership yet.
ok(bootstrap.includes("core/class-shell.php"), 'bootstrap must load the inert shell contract');
ok(shell.includes('Workspaces::available()'), 'shell navigation must derive from capability-filtered workspaces');
ok(shell.includes('Router::hub_url'), 'shell URLs must use the canonical Hub router');
ok(!/add_action|add_filter|wp_enqueue_(?:script|style)|add_rewrite_rule|flush_rewrite_rules/m.test(shell), 'shell contract must not hook, enqueue or claim routes yet');

// Workspace navigation is capability driven, never role-name driven.
['mvm_my_mierlo_access', 'mvm_organization_access', 'mvm_newsroom_access', 'mvm_technical_access'].forEach((cap) => {
  ok(capabilities.includes(`'${cap}'`), `workspace capability missing ${cap}`);
});
['my-mierlo', 'organization', 'newsroom', 'communications', 'technical'].forEach((workspace) => {
  ok(workspaces.includes(`'${workspace}'`), `workspace registry missing ${workspace}`);
});
ok(!/journalist|editor|moderator|teamleider|fotograaf|vertaler/i.test(workspaces), 'workspace routing must not hardcode editorial roles');

// Shared design system remains scoped to the Hub and includes accessibility/dark/mobile foundations.
ok(css.includes('.mvm-hub {'), 'shared design system must be scoped under .mvm-hub');
ok(css.includes('--mvm-blue: #1966AE'), 'shared design system must use canonical MvM blue');
ok(css.includes('[data-theme="dark"]') || css.includes('html[data-theme="dark"]'), 'shared design system must include dark-mode tokens');
ok(css.includes(':focus-visible'), 'shared design system must include visible keyboard focus');
ok(css.includes('@media (max-width: 600px)'), 'shared design system must include mobile behavior');
ok(!/(^|\n)\s*(?:html|body|a|button|input|table)\s*\{/m.test(css), 'shared design system must not introduce unscoped global element selectors');

// First Newsroom screens are GET-only, separately capability protected and bounded.
['/newsroom/sources', '/newsroom/radar', '/newsroom/agenda'].forEach((route) => {
  ok(readController.includes(`'${route}'`), `read controller missing ${route}`);
});
ok(readController.includes('\\WP_REST_Server::READABLE'), 'Newsroom list routes must be GET/read-only');
ok(readController.includes('Capabilities::can_access_newsroom()'), 'Newsroom list routes must require workspace access');
ok(readController.includes('Capabilities::can_read( $capability, $legacy_capability )'), 'each Newsroom list route must enforce its own capability');
ok(readController.includes("'per_page'") && readController.includes('<= 50'), 'REST list size must be capped at 50');
ok(readController.includes("'page'") && readController.includes('<= 100'), 'REST page depth must be capped');
ok(moduleFile.includes('Newsroom_Read_REST_Controller'), 'Newsroom module must register the bounded read controller');

// Read models adopt existing production tables but never leak sensitive source/signal detail in generic lists.
ok(readModel.includes("'mvm_hub4_' . $suffix"), 'phase 2 read models must adopt existing Hub4 data rather than copy it');
['contact_name', 'contact_email', 'contact_phone', 'private_note', 'source_url'].forEach((field) => {
  ok(!readModel.includes(field), `generic Newsroom list model must not expose ${field}`);
});
ok(!/\bsummary\b/m.test(readModel), 'generic Radar list must not expose free-text signal summaries');
ok(readModel.includes('min( 50, $per_page )'), 'read model must enforce a hard per-page limit');
ok(readModel.includes('min( 100, $page )'), 'read model must limit deep offset pagination');
ok(readModel.includes('$per_page + 1'), 'read model should use one extra row to determine hasMore');
ok(!/SELECT\s+COUNT\s*\(/mi.test(readModel), 'list endpoints must not run total-count scans');
ok(readModel.includes('30 * DAY_IN_SECONDS'), 'Agenda list must use a bounded default time horizon');

// Phase 2 remains read-only and cannot mutate production data.
ok(!/->\s*(?:insert|update|delete|replace)\s*\(/m.test(readModel), 'phase 2 read model must not mutate DB rows');
ok(!/wp_insert_post|wp_update_post|update_post_meta|update_option|delete_option/m.test(readModel + readController), 'phase 2 list layer must not mutate WordPress state');

if (!process.exitCode) {
  console.log('PASS: MvM Hub phase-2 shell/read contract');
}
