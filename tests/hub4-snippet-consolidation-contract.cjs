'use strict';

const fs = require('fs');
const path = require('path');
const assert = require('assert');

const root = path.resolve(__dirname, '..');
const pluginDir = path.join(root, 'plugins', 'mvm-hub4-rc-direct');
const mainFile = path.join(pluginDir, 'mvm-hub4.php');
const moduleDir = path.join(pluginDir, 'src', 'consolidated-snippets');
const bootstrapFile = path.join(moduleDir, 'bootstrap.php');

const ids = [
  387,388,438,454,465,469,470,471,472,473,474,475,476,477,478,479,480,
  481,482,483,484,489,491,492,493,496,497,498,499,501,502,503,504,505,
  506,509,513,514,519,521,522,525,529,
];

assert.strictEqual(ids.length, 43, 'Releasecontract moet exact 43 snippet-ID’s bevatten.');

const main = fs.readFileSync(mainFile, 'utf8');
const bootstrap = fs.readFileSync(bootstrapFile, 'utf8');
const sourceFor = (id) => fs.readFileSync(path.join(moduleDir, `snippet-${id}.php`), 'utf8');
const headerVersion = main.match(/Version:\s*([^\s]+)/)?.[1] || '';
const runtimeVersion = main.match(/MVM_HUB4_VERSION',\s*'([^']+)'/)?.[1] || '';
assert.match(headerVersion, /^\d+\.\d+\.\d+(?:-rc\d+)?$/);
assert.strictEqual(runtimeVersion, headerVersion, 'Runtime- en headerversie moeten gelijk zijn.');
assert.ok(main.includes("src/consolidated-snippets/bootstrap.php"), 'Hoofdplugin moet de consolidatieloader laden.');
assert.ok(bootstrap.includes('WHERE active = 1'), 'Loader moet alleen actieve Code Snippets als guard gebruiken.');
assert.ok(bootstrap.includes('mvm_hub12_should_load_consolidated_snippet'), 'Guardfunctie ontbreekt.');

for (const id of ids) {
  const filename = `snippet-${id}.php`;
  const file = path.join(moduleDir, filename);
  assert.ok(fs.existsSync(file), `First-party module ontbreekt: ${filename}`);
  const source = fs.readFileSync(file, 'utf8');
  assert.ok(source.startsWith('<?php'), `${filename} moet een PHP-bestand zijn.`);
  assert.ok(!/\beval\s*\(/i.test(source), `${filename} mag geen eval gebruiken.`);
  assert.ok(bootstrap.includes(`${id} => array( 'file' => '${filename}'`), `Manifestkoppeling ontbreekt voor ${id}.`);
}

const manifestMatches = [...bootstrap.matchAll(/^\s*(\d+)\s*=>\s*array\(\s*'file'\s*=>\s*'snippet-\d+\.php'/gm)].map(m => Number(m[1]));
assert.deepStrictEqual(manifestMatches, ids, 'Bootstrapmanifest moet exact dezelfde 43 IDs in vaste volgorde bevatten.');

// Frozen visual contracts: verify what each production style module actually owns.
assert.ok(sourceFor(387).includes('#1966AE') && sourceFor(387).includes('--mvm-contrast-blue'), 'Globale MvM contrasttokens ontbreken.');
assert.ok(sourceFor(388).includes('#1966AE') && sourceFor(388).includes('mvm-hub4-contrast-v1.css'), 'Hub standalone contrastcontract ontbreekt.');
assert.ok(sourceFor(493).includes('mvm-blue-button-contrast-lock') && sourceFor(493).includes('color:#fff !important'), 'Sitewide blauwe-knoppen/white-text contract ontbreekt.');
assert.ok(sourceFor(497).includes('data-mvm-home-quick') && sourceFor(497).includes("'Mijn Mierlo'") && sourceFor(497).includes("'MierLokaal'"), 'Homepage Snel naar-contract ontbreekt.');
assert.ok(sourceFor(498).includes('#1966AE') && sourceFor(498).includes('mvm-theme1-action-card--blue-links'), 'Homepage actiekaart label/linkkleurcontract ontbreekt.');
assert.ok(sourceFor(499).includes('#1966AE') && sourceFor(499).includes('mvm-hub4-light-contrast-lock-v1'), 'Hub light-mode contrastcontract ontbreekt.');
assert.ok(sourceFor(522).includes('#1966AE') && sourceFor(522).includes('body.onetap-light-contrast'), 'OneTap licht-contrastcorrectie ontbreekt.');
assert.ok(sourceFor(522).includes('outline:3px solid #ffbf47'), 'OneTap focuscontract ontbreekt.');

console.log('Hub 1.2 snippet consolidation contract: 43/43 modules present, guarded and style baseline frozen.');
