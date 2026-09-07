'use strict';

const fs = require('fs');
const assert = require('assert');

const css = fs.readFileSync('plugins/mvm-hub4-rc-direct/src/class-encyclopedia-detail-theme.php', 'utf8');
const main = fs.readFileSync('plugins/mvm-hub4-rc-direct/mvm-hub4.php', 'utf8');

assert.match(main, /class-encyclopedia-detail-theme\.php/);
assert.match(main, /MvM_Hub4_Encyclopedia_Detail_Theme::init\(\)/);
for (const selector of ['.mvm-facts-card', '.mvm-dossier-breadcrumb', '.mvm-anchor-toc', '.mvm-dossier-content', '.mvm-dossier-neighbors', '.mvm-related']) {
  assert.ok(css.includes(selector), `missing encyclopedia detail selector ${selector}`);
}
assert.ok(css.includes('--mvm-ency-blue:#1966AE'), 'MvM blue must be the detail-theme brand colour');
assert.match(css, /background:var\(--mvm-ency-white\)!important;[\s\S]*color:var\(--mvm-ency-ink\)!important/);
assert.match(css, /background:var\(--mvm-ency-grey\)!important;[\s\S]*color:var\(--mvm-ency-ink\)!important/);
assert.match(css, /html\[data-theme="dark"\]/);
assert.match(css, /background:#17222d!important/);
assert.match(css, /color:#f5f7fa!important/);
assert.match(css, /color:#8bc7fb!important/);
assert.ok(!css.includes('update_post_'), 'detail theme must not mutate encyclopedia content');
assert.ok(!css.includes('update_option('), 'detail theme must remain presentation-only');

console.log('MvM encyclopedia detail theme contract: OK');
