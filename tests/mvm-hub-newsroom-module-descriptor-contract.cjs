'use strict';

const fs = require('node:fs');
const path = require('node:path');

const root = path.resolve(__dirname, '..');
const read = (relative) => fs.readFileSync(path.join(root, relative), 'utf8');

const descriptors = read('plugins/mvm-hub/core/class-module-descriptors.php');
const shell = read('plugins/mvm-hub/core/class-shell.php');
const renderer = read('plugins/mvm-hub/core/class-shell-renderer.php');
const bootstrap = read('plugins/mvm-hub/mvm-hub.php');

function fail(message) {
  console.error(`FAIL: ${message}`);
  process.exitCode = 1;
}
function ok(condition, message) {
  if (!condition) fail(message);
}

[
  '/mvm-hub/v1/newsroom/today',
  '/mvm-hub/v1/newsroom/news',
  '/mvm-hub/v1/newsroom/assignments',
  '/mvm-hub/v1/newsroom/radar',
  '/mvm-hub/v1/newsroom/sources',
  '/mvm-hub/v1/newsroom/agenda',
  '/mvm-hub/v1/newsroom/media',
  '/mvm-hub/v1/newsroom/dossiers',
  '/mvm-hub/v1/newsroom/corrections',
  '/mvm-hub/v1/newsroom/distribution',
  '/mvm-hub/v1/newsroom/team',
  '/mvm-hub/v1/communications/messages',
  '/mvm-hub/v1/communications/messages/{id}',
  '/mvm-hub/v1/communications/mail/folders',
  '/mvm-hub/v1/communications/mail/messages',
  '/mvm-hub/v1/communications/mail/messages/{id}',
].forEach((route) => ok(descriptors.includes(`'${route}'`), `module descriptor missing read route ${route}`));

ok(descriptors.includes("'/mvm-hub/v1/newsroom/news/{id}/smart-links'"), 'News module descriptor must expose Smart Links as a read-only auxiliary route');
ok(descriptors.includes("'/mvm-hub/v1/newsroom/encyclopedia/targets'"), 'News module descriptor must expose manual encyclopedia target search');
ok(descriptors.includes("'communications:messages'"), 'internal messages descriptor missing');
ok(/['"]communications:messages['"][\s\S]*?['"]stage['"]\s*=>\s*['"]read-ready['"]/m.test(descriptors), 'internal messages must be read-ready once participant-scoped GET routes exist');
ok(/['"]communications:messages['"][\s\S]*?['"]writeState['"]\s*=>\s*['"]provider-write-adapter-not-enabled['"]/m.test(descriptors), 'internal message writes must remain blocked until a safe write adapter exists');
ok(/['"]communications:mail['"][\s\S]*?['"]writeState['"]\s*=>\s*['"]transport-security-gated['"]/m.test(descriptors), 'mail descriptor must keep transport writes gated');
ok(!/['"]writesEnabled['"]\s*=>\s*true/m.test(descriptors), 'no module descriptor may enable writes during parallel migration');
ok(descriptors.includes('Workspace_Sections::can_access( $workspace, $section )'), 'descriptor access must be capability-filtered before route metadata is exposed');

ok(shell.includes('Module_Descriptors::for( $active, $active_section )'), 'shell must resolve the active module through the central descriptor registry');
ok(shell.includes("'activeModule'"), 'shell must expose the active module descriptor');
ok(renderer.includes('data-module-stage='), 'renderer must expose non-sensitive module stage for progressive enhancement');
ok(renderer.includes('Schrijfacties blijven tijdens de parallelle migratie geblokkeerd.'), 'renderer must clearly state write migration state');
ok(!renderer.includes('readRoutes') && !renderer.includes('auxiliaryRoutes'), 'renderer must not dump technical route inventory into visible shell HTML');
ok(bootstrap.includes("core/class-module-descriptors.php"), 'bootstrap must load module descriptors before shell use');

if (!process.exitCode) {
  console.log('PASS: MvM Hub central module descriptor contract');
}
