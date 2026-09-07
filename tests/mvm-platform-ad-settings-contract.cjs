'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');

const page = fs.readFileSync('plugins/mvm-platform/src/local-ads/class-ad-settings-page.php', 'utf8');
const css = fs.readFileSync('plugins/mvm-platform/assets/ad-settings.css', 'utf8');
const bootstrap = fs.readFileSync('plugins/mvm-platform/bootstrap.php', 'utf8');
const platform = fs.readFileSync('plugins/mvm-platform/src/class-platform.php', 'utf8');

assert.match(page, /adverteren\/instellingen/);
assert.match(page, /LOCAL_ADS_ADMIN/, 'only SysOp/Admin may access placement settings');
assert.match(page, /wp_verify_nonce/, 'frontend settings write must be nonce protected');
assert.match(page, /MvM_Homepage_Ad_Slots::update_settings/);
assert.match(page, /homepage_slots_updated/, 'visibility change must be audited');
assert.match(page, /top/);
assert.match(page, /stream/);
assert.match(page, /bottom/);
assert.match(page, /Een verborgen plek wordt helemaal niet gerenderd/);
assert.match(page, /adverteren\/voorbeelden\/homepage/);
assert.match(css, /#1966AE/i);
assert.match(css, /background:#f2f4f6/);
assert.match(css, /color:#111/);
assert.ok(bootstrap.includes('class-ad-settings-page.php'));
assert.match(platform, /MvM_Ad_Settings_Page::boot\(\)/);

console.log('MvM protected ad settings contract: OK');
