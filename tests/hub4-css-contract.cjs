'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const cssPath = path.resolve(__dirname, '..', 'plugins', 'mvm-hub4-rc-direct', 'assets', 'responsive-hardening.css');
const css = fs.readFileSync(cssPath, 'utf8');

const withoutComments = css.replace(/\/\*[\s\S]*?\*\//g, '');
let depth = 0;
for (const character of withoutComments) {
    if (character === '{') depth += 1;
    if (character === '}') depth -= 1;
    assert.ok(depth >= 0, 'CSS contains an unexpected closing brace.');
}
assert.equal(depth, 0, 'CSS contains an unclosed block.');

const desktop = css.match(/\.mvm-hub4__sidebar\s*\{([\s\S]*?)\}/);
assert.ok(desktop, 'Desktop sidebar rule is missing.');
assert.match(desktop[1], /min-width:\s*260px/);
assert.match(desktop[1], /max-width:\s*260px/);
assert.match(desktop[1], /overflow:\s*visible/);

const tablet = css.match(/@media\s*\(max-width:\s*900px\)\s*\{([\s\S]*)\}\s*@media\s*\(max-width:\s*620px\)/);
assert.ok(tablet, 'Tablet breakpoint is missing.');
assert.match(tablet[1], /\.mvm-hub4__sidebar[\s\S]*?width:\s*100%/);
assert.match(tablet[1], /\.mvm-hub4__nav[\s\S]*?grid-template-columns:\s*repeat\(auto-fit/);
assert.doesNotMatch(tablet[1], /overflow(?:-x)?:\s*auto/);

for (const property of ['word-break: normal', 'overflow-wrap: break-word', 'writing-mode: horizontal-tb']) {
    assert.ok(css.includes(property), `Responsive text rule is missing: ${property}`);
}

console.log('Hub 4 CSS contract: desktop, tablet and mobile constraints passed.');
