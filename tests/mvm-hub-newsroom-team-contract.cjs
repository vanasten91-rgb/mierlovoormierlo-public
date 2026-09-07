'use strict';

const fs = require('node:fs');
const path = require('node:path');

const root = path.resolve(__dirname, '..');
const model = fs.readFileSync(path.join(root, 'plugins/mvm-hub/modules/newsroom/team/class-team-read-model.php'), 'utf8');
const controller = fs.readFileSync(path.join(root, 'plugins/mvm-hub/modules/newsroom/team/class-team-read-rest-controller.php'), 'utf8');
const moduleFile = fs.readFileSync(path.join(root, 'plugins/mvm-hub/modules/newsroom/class-newsroom-module.php'), 'utf8');

function fail(message) {
  console.error(`FAIL: ${message}`);
  process.exitCode = 1;
}
function ok(condition, message) {
  if (!condition) fail(message);
}

ok(model.includes("'capability__in'"), 'Team directory must discover staff by capabilities');
ok(!/role__in|mvm_(?:sysop|teamleider|editor|journalist|redacteur|fotograaf|moderator|vertaler)/m.test(model), 'new Team business logic must not hardcode editorial role names');
ok(model.includes("'number'      => $per_page + 1"), 'Team list must use bounded lookahead pagination');
ok(model.includes("'offset'      => ( $page - 1 ) * $per_page"), 'Team pagination must not skip records');
ok(model.includes("'count_total' => false"), 'Team list must avoid total-count scans');
ok(model.includes("'displayName'"), 'Team list must expose display name');
ok(model.includes("'areas'"), 'Team list must expose capability-derived work areas');

// Guard executable payload/property access rather than prose comments. The model
// may document forbidden fields by name, but it must never read or emit them.
[
  'user_email',
  'user_login',
  'phone',
  'telephone',
  'address',
].forEach((field) => {
  ok(!new RegExp(`['\"]${field}['\"]\\s*=>`, 'i').test(model), `Team payload must not emit ${field}`);
  ok(!new RegExp(`->${field}\\b`, 'i').test(model), `Team model must not read ${field}`);
});

ok(model.includes('$user->has_cap'), 'Team work areas must derive from actual user capabilities');

ok(controller.includes("'/newsroom/team'"), 'Team route missing');
ok(controller.includes('\\WP_REST_Server::READABLE'), 'Team route must be GET/read-only');
ok(controller.includes('Capabilities::TEAM_VIEW'), 'Team route must require team-view capability');
ok(controller.includes('Capabilities::can_access_newsroom()'), 'Team route must require Newsroom workspace access');
ok(moduleFile.includes('Team_Read_REST_Controller'), 'Newsroom module must register Team controller');

if (!process.exitCode) {
  console.log('PASS: MvM Hub Team capability/privacy contract');
}
