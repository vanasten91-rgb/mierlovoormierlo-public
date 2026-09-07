const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const root = path.resolve(__dirname, '..');
const read = (relative) => fs.readFileSync(path.join(root, relative), 'utf8');

const app = read('plugins/mvm-hub4-rc-direct/assets/app-v2.js');
const css = read('plugins/mvm-hub4-rc-direct/assets/modules.css');
const repository = read('plugins/mvm-hub4-rc-direct/src/class-news-repository.php');
const checklist = read('plugins/mvm-hub4-rc-direct/src/class-publication-checklist.php');
const toolRegistry = read('plugins/mvm-hub4-rc-direct/src/class-tools.php');

assert.ok(app.includes("api('news?limit=50')"), 'newsroom loads a bounded recent set');
assert.ok(app.includes("id: 'mvm-news-search'"), 'newsroom exposes an accessible search field');
assert.ok(app.includes("id: 'mvm-news-status'"), 'newsroom exposes a status filter');
assert.ok(app.includes("'/checklist'"), 'newsroom reuses the existing checklist route');
assert.ok(app.includes('checklistInputKeys'), 'manual checklist fields use an explicit allowlist');
assert.ok(app.includes('data.permissions?.canCreate'), 'new-article action is capability-gated');
assert.ok(!app.includes('innerHTML ='), 'newsroom must not inject HTML strings');

assert.ok(repository.includes("'canViewChecklist'"), 'news overview exposes checklist visibility');
assert.ok(checklist.includes("'canUpdate'   => $can_update"), 'checklist response exposes object-aware update permission');
assert.ok(toolRegistry.includes("'news_new' => array"), 'new article uses the existing audited tool launcher');
assert.ok(toolRegistry.includes("admin_url( 'post-new.php' )"), 'new article opens the native WordPress editor');
assert.ok(css.includes('.mvm-hub4__checklist-progress'), 'checklist progress has dedicated styling');
assert.ok(css.includes('@media(max-width:620px){.mvm-hub4__news-filters'), 'newsroom stays responsive on phones');
assert.ok(css.includes('@media(prefers-color-scheme:dark){.mvm-hub4__news-filters'), 'newsroom preserves dark mode');

console.log('Hub 4 newsroom desk contract: all checks passed.');
