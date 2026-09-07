'use strict';

const fs = require('fs');
const assert = require('assert');

const hub = fs.readFileSync('plugins/mvm-hub4-rc-direct/mvm-hub4.php', 'utf8');
const loader = fs.readFileSync('plugins/mvm-hub4-rc-direct/src/class-integrated-platform.php', 'utf8');
const moduleBootstrap = fs.readFileSync('plugins/mvm-platform/bootstrap.php', 'utf8');
const portal = fs.readFileSync('plugins/mvm-platform/src/local-ads/class-entrepreneur-portal.php', 'utf8');
const capabilities = fs.readFileSync('plugins/mvm-platform/src/class-capabilities.php', 'utf8');
const hubApp = fs.readFileSync('plugins/mvm-hub4-rc-direct/src/class-app.php', 'utf8');
const hubUi = fs.readFileSync('plugins/mvm-hub4-rc-direct/assets/ui-contract-v1.css', 'utf8');
const platformUi = fs.readFileSync('plugins/mvm-platform/assets/design-system.css', 'utf8');

const version = hub.match(/MVM_HUB4_VERSION', '([^']+)'/)?.[1] || '';
assert.match(version, /^\d+\.\d+\.\d+(?:-rc\d+)?$/, 'Integrated Hub version missing or unsupported');
assert.match(hub, /class-integrated-platform\.php/);
assert.match(hub, /MvM_Hub4_Integrated_Platform::boot\(\)/);
assert.match(hub, /MvM_Hub4_Integrated_Platform::activate\(\)/);
assert.match(loader, /modules\/mvm-platform/, 'release must prefer bundled platform module');
assert.match(loader, /development-sibling/, 'repository checkout may use sibling module for CI only');
assert.match(moduleBootstrap, /final class MvM_Platform_Module/);

assert.match(portal, /\^ondernemers\/advertentiecentrum\/\?\$/);
assert.ok(!portal.includes('/hub4/'), 'entrepreneur portal must remain outside staff Hub');
assert.ok(!portal.includes('mvm_hub4'), 'entrepreneur portal must not depend on Hub query vars/sessions');
assert.match(portal, /LOCAL_ADS_SELF_MANAGE/);
assert.match(capabilities, /ENTREPRENEUR_ROLE\s*=\s*'mvm_ondernemer'/);
assert.match(hubApp, /LOCAL_ADS_MANAGE/);
assert.ok(!hubApp.includes('LOCAL_ADS_SELF_MANAGE'), 'staff Hub must never use entrepreneur self-management capability');

/* Hub4 keeps its legacy embedded visual contract until its own decommission path
 * no longer needs compatibility checks. Platform itself now owns the canonical
 * sitewide semantic design system. */
assert.match(hubUi, /#1966AE/i, 'Hub MvM blue missing');
assert.match(hubUi, /#ffffff/i, 'Hub white text/surface token missing');
assert.match(hubUi, /#111111/i, 'Hub black text token missing');
assert.match(hubUi, /#eef3f8/i, 'Hub light-grey card token missing');
assert.match(hubUi, /Blue = white text\. White = black text\. Light grey = black text/);

assert.match(platformUi, /--mvm-color-brand-600:\s*#1966ae/i, 'Canonical Platform MvM blue missing');
assert.match(platformUi, /--mvm-color-on-brand:\s*#ffffff/i, 'Platform on-brand text token missing');
assert.match(platformUi, /--mvm-color-text:\s*#112b50/i, 'Platform light-theme text token missing');
assert.match(platformUi, /--mvm-color-surface-muted:\s*#eef3f8/i, 'Platform muted surface token missing');
assert.match(platformUi, /html\[data-theme="dark"\]/, 'Platform dark-theme selector missing');
assert.match(platformUi, /--mvm-color-canvas:\s*#0f1e2e/i, 'Platform dark canvas missing');
assert.match(platformUi, /--mvm-color-surface:\s*#172a3e/i, 'Platform dark surface missing');
assert.match(platformUi, /prefers-reduced-motion/, 'Platform reduced-motion contract missing');
assert.match(platformUi, /:focus-visible/, 'Platform keyboard focus contract missing');
assert.ok(!platformUi.includes('--mvm-ui-white: #ffffff'), 'Platform must not reintroduce the legacy dark-mode-as-light override');

console.log(`Hub ${version} integrated platform contract: OK`);
