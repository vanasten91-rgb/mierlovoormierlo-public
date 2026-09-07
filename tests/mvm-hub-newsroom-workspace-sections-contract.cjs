'use strict';

const fs = require('node:fs');
const path = require('node:path');

const root = path.resolve(__dirname, '..');
const read = (relative) => fs.readFileSync(path.join(root, relative), 'utf8');

const sections = read('plugins/mvm-hub/core/class-workspace-sections.php');
const shell = read('plugins/mvm-hub/core/class-shell.php');
const renderer = read('plugins/mvm-hub/core/class-shell-renderer.php');
const css = read('plugins/mvm-hub/assets/hub-sections.css');
const assets = read('plugins/mvm-hub/core/class-assets.php');
const bootstrap = read('plugins/mvm-hub/mvm-hub.php');

function fail(message) {
  console.error(`FAIL: ${message}`);
  process.exitCode = 1;
}
function ok(condition, message) {
  if (!condition) fail(message);
}

['today','news','assignments','radar','sources','agenda','media','dossiers','corrections','distribution','team'].forEach((section) => {
  ok(sections.includes(`'${section}'`), `Newsroom section missing ${section}`);
});
['messages','mail'].forEach((section) => ok(sections.includes(`'${section}'`), `Communications section missing ${section}`));

[
  'ASSIGNMENTS_VIEW','RADAR_VIEW','SOURCES_VIEW','AGENDA_VIEW','MEDIA_VIEW',
  'DOSSIERS_VIEW','CORRECTIONS_VIEW','DISTRIBUTION_VIEW','TEAM_VIEW',
  'MESSAGES_ACCESS'
].forEach((cap) => ok(sections.includes(`Capabilities::${cap}`), `section navigation missing capability guard ${cap}`));
ok(sections.includes('Capabilities::can_access_mail()'), 'mail section must use central mail access policy');
ok(!/mvm_(?:sysop|teamleider|editor|journalist|redacteur|fotograaf|moderator|vertaler)/m.test(sections), 'section navigation must not hardcode editorial role names');
ok(sections.includes('Workspaces::can_access( $workspace )'), 'section access must require parent workspace access');

ok(shell.includes('Workspace_Sections::available( $active )'), 'shell must derive subnavigation from allowed sections');
ok(shell.includes('Workspace_Sections::default_section( $active )'), 'unauthorized/unknown requested section must fall back to an allowed section');
ok(shell.includes("'activeSection'"), 'shell must expose active section');
ok(shell.includes("'sectionNavigation'"), 'shell must expose section navigation');

ok(renderer.includes('mvm-hub__subnav'), 'shell renderer must render secondary navigation');
ok(renderer.includes("aria-label=\"<?php echo esc_attr__( 'Onderdelen', 'mvm-hub' ); ?>\""), 'secondary navigation must have an accessible label');
ok(renderer.includes('data-section='), 'shell root must expose active section for module rendering');
ok(renderer.includes('aria-current="page"'), 'active shell navigation must expose aria-current');

ok(css.includes('.mvm-hub .mvm-hub__subnav'), 'section CSS must be scoped under .mvm-hub');
ok(!/(^|\n)\s*(?:html|body|a|button|nav)\s*\{/m.test(css), 'section CSS must not contain unscoped global selectors');
ok(css.includes('@media (max-width: 600px)'), 'section navigation must include mobile behavior');
ok(assets.includes("'hub-sections.css'"), 'asset manifest must include modular section stylesheet');
ok(bootstrap.includes("core/class-workspace-sections.php"), 'bootstrap must load section registry before shell use');

if (!process.exitCode) {
  console.log('PASS: MvM Hub capability-aware workspace section contract');
}
