'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');

const slots = fs.readFileSync('plugins/mvm-platform/src/local-ads/class-homepage-ad-slots.php', 'utf8');
const rest = fs.readFileSync('plugins/mvm-platform/src/local-ads/class-homepage-ad-slots-rest.php', 'utf8');
const examples = fs.readFileSync('plugins/mvm-platform/src/local-ads/class-ad-examples-page.php', 'utf8');
const bootstrap = fs.readFileSync('plugins/mvm-platform/bootstrap.php', 'utf8');
const platform = fs.readFileSync('plugins/mvm-platform/src/class-platform.php', 'utf8');
const css = fs.readFileSync('plugins/mvm-platform/assets/homepage-ad-slots.css', 'utf8');
const exampleCss = fs.readFileSync('plugins/mvm-platform/assets/ad-examples.css', 'utf8');

for (const key of ["'top'", "'stream'", "'bottom'"]) assert.ok(slots.includes(key), `missing homepage slot ${key}`);
assert.match(slots, /3 !== self::\$home_post_count/, 'stream slot must render after two articles, before article three');
assert.match(slots, /render_slot\( 'home_stream', 2/, 'stream slot must support two ads');
assert.match(slots, /array_slice\( \$ads, 0, max\( 1, min\( 2, \$limit \) \) \)/, 'no homepage slot may render more than two ads');
assert.match(slots, /if \( ! \$ads \) \{\s*return '';\s*\}/s, 'empty slots must render no wrapper/whitespace reservation');
assert.match(slots, /rel="sponsored nofollow noopener noreferrer"/, 'homepage ads must keep sponsored/nofollow link semantics');
assert.match(slots, /\/aanbiedingen\//, 'homepage slots must link to the offers page');
assert.match(css, /grid-template-columns:repeat\(2,minmax\(0,1fr\)\)/, 'desktop stream slot must show two cards');
assert.match(css, /@media\(max-width:700px\)/, 'homepage slots must stack responsively');
assert.match(css, /background:#f2f4f6/, 'grey MvM card zone must use the approved light-grey surface');
assert.match(css, /color:#111/, 'grey MvM card zone must use black text');

assert.match(rest, /LOCAL_ADS_ADMIN/, 'only SysOp/Admin capability may toggle global homepage slots');
assert.match(rest, /\/local-ads\/homepage-slots/);
assert.match(rest, /homepage_slots_updated/, 'slot changes must be audited');
assert.match(rest, /no_store_private/, 'slot settings must never be cached as public data');

assert.match(examples, /adverteren\/voorbeelden/);
assert.match(examples, /adverteren\/voorbeelden\/homepage/);
assert.match(examples, /Voorbeeldadvertentie/);
for (const variant of ["'wide'", "'compact'", "'inline'"]) assert.ok(examples.includes(variant), `missing demo variant ${variant}`);
assert.match(examples, /homepage_demo\(\)/, 'fourth demo must be the double homepage format');
for (const fictive of ['Fictief · Bakkerij De Dorpsoven', 'Fictief · Fietsen Mierlo', 'Fictief · Groen & Goed Mierlo', 'Fictief · Koffiehuis De Brink']) {
    assert.ok(examples.includes(fictive), `missing clearly fictive advertiser: ${fictive}`);
}
assert.ok(!/https?:\/\/[^'\"]+/.test(examples), 'demo page must not contain real external advertiser destinations');
assert.match(exampleCss, /#1966AE/i, 'demo pages must use MvM blue');
assert.match(exampleCss, /color:#17222d/, 'white/light demo surfaces must use dark text');

for (const required of ['class-homepage-ad-slots.php', 'class-homepage-ad-slots-rest.php', 'class-ad-examples-page.php']) {
    assert.ok(bootstrap.includes(required), `bootstrap missing ${required}`);
}
assert.match(platform, /MvM_Homepage_Ad_Slots::boot\(\)/);
assert.match(platform, /MvM_Ad_Examples_Page::boot\(\)/);
assert.match(platform, /MvM_Homepage_Ad_Slots_REST/);

console.log('MvM homepage ad slots/examples contract: OK');
