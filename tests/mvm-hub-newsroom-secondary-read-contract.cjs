'use strict';

const fs = require('node:fs');
const path = require('node:path');

const root = path.resolve(__dirname, '..');
const model = fs.readFileSync(path.join(root, 'plugins/mvm-hub/modules/newsroom/read/class-newsroom-secondary-read-model.php'), 'utf8');
const controller = fs.readFileSync(path.join(root, 'plugins/mvm-hub/modules/newsroom/read/class-newsroom-secondary-read-rest-controller.php'), 'utf8');
const moduleFile = fs.readFileSync(path.join(root, 'plugins/mvm-hub/modules/newsroom/class-newsroom-module.php'), 'utf8');

function fail(message) {
  console.error(`FAIL: ${message}`);
  process.exitCode = 1;
}

function ok(condition, message) {
  if (!condition) fail(message);
}

['/newsroom/media', '/newsroom/dossiers', '/newsroom/corrections', '/newsroom/distribution'].forEach((route) => {
  ok(controller.includes(`'${route}'`), `secondary read controller missing ${route}`);
});
ok(controller.includes('\\WP_REST_Server::READABLE'), 'secondary Newsroom routes must be GET/read-only');
ok(controller.includes('Capabilities::can_access_newsroom()'), 'secondary routes must require Newsroom workspace access');
ok(controller.includes('Capabilities::can_read( $capability, $legacy_capability )'), 'secondary routes must enforce feature capabilities');
ok(moduleFile.includes('Newsroom_Secondary_Read_REST_Controller'), 'Newsroom module must register secondary read routes');

// Generic list projections intentionally exclude privacy-sensitive and free-text fields.
[
  'internal_brief',
  'contact_name',
  'contact_email',
  'public_note',
  'copy_text',
  'target_url',
  'alt_text',
].forEach((field) => ok(!model.includes(field), `secondary generic lists must not expose ${field}`));
ok(!/['"]message['"]/m.test(model), 'Corrections generic list must not expose correction message text');
ok(!/['"]summary['"]/m.test(model), 'Dossiers generic list must not expose dossier summary text');

// Unknown/restricted dossier visibility must fail closed for ordinary viewers.
ok(model.includes("visibility IN ('internal','public')"), 'dossier viewers must have a fail-closed visibility allow-list');
ok(model.includes('Capabilities::DOSSIERS_MANAGE'), 'dossier managers must be checked separately from viewers');

// Lists stay bounded and avoid total scans.
ok(model.includes('min( 50, $per_page )'), 'secondary reads must cap per-page at 50');
ok(model.includes('min( 100, $page )'), 'secondary reads must cap page depth');
ok(model.includes('$per_page + 1'), 'secondary reads must use one extra row for hasMore');
ok(!/SELECT\s+COUNT\s*\(/mi.test(model), 'secondary list endpoints must not run total-count scans');

// No write path belongs in these phase-2 models/controllers.
ok(!/->\s*(?:insert|update|delete|replace)\s*\(/m.test(model), 'secondary read model must not mutate DB rows');
ok(!/wp_insert_post|wp_update_post|update_post_meta|update_option|delete_option/m.test(model + controller), 'secondary read layer must not mutate WordPress state');

if (!process.exitCode) {
  console.log('PASS: MvM Hub secondary Newsroom read/privacy contract');
}
