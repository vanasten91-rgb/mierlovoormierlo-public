'use strict';

const fs = require('fs');
const assert = require('assert');

const caps = fs.readFileSync('plugins/mvm-platform/src/class-capabilities.php', 'utf8');
const rest = fs.readFileSync('plugins/mvm-platform/src/local-ads/class-local-business-ads-rest.php', 'utf8');
const ui = fs.readFileSync('plugins/mvm-hub4-rc-direct/assets/local-ads-admin-v1.js', 'utf8');
const template = fs.readFileSync('plugins/mvm-hub4-rc-direct/templates/app.php', 'utf8');

assert.match(caps, /LOCAL_ADS_ADMIN\s*=\s*'mvm_local_ads_admin'/);
for (const role of ["'administrator'", "'mvm_sysop'"]) {
    const start = caps.indexOf(role);
    assert.ok(start >= 0, `missing role ${role}`);
    const section = caps.slice(start, start + 500);
    assert.ok(section.includes('self::LOCAL_ADS_ADMIN'), `${role} must receive full local-ad CRUD`);
}
for (const role of ["'mvm_teamleider'", "'mvm_editor'"]) {
    const start = caps.indexOf(role);
    assert.ok(start >= 0, `missing role ${role}`);
    const section = caps.slice(start, start + 400);
    assert.ok(!section.includes('self::LOCAL_ADS_ADMIN'), `${role} must not receive admin-only CRUD`);
}

assert.match(rest, /function can_admin\(\)/);
assert.match(rest, /LOCAL_ADS_ADMIN/);
assert.match(rest, /'callback'\s*=>\s*array\( __CLASS__, 'create' \)/);
assert.match(rest, /'callback'\s*=>\s*array\( __CLASS__, 'update' \)/);
assert.match(rest, /'callback'\s*=>\s*array\( __CLASS__, 'trash' \)/);
assert.match(rest, /admin_created/);
assert.match(rest, /admin_updated/);
assert.match(rest, /admin_trashed/);
assert.match(rest, /user_can\( \$user, MvM_Platform_Capabilities::LOCAL_ADS_SELF_MANAGE \)/, 'owner reassignment must be limited to an Ondernemer account');

assert.ok(!ui.includes('innerHTML'), 'admin ad UI must not render API data with innerHTML');
assert.match(ui, /X-WP-Nonce/);
assert.match(ui, /credentials:\s*'same-origin'/);
assert.match(ui, /cache:\s*'no-store'/);
assert.match(ui, /data\.can_admin/);
assert.match(ui, /request\('local-ads', 'POST'/);
assert.match(ui, /request\(`local-ads\/\$\{id\.value\}`, 'PUT'/);
assert.match(ui, /request\(`local-ads\/\$\{item\.id\}`, 'DELETE'/);
assert.match(template, /assets\/local-ads-admin-v1\.js/);
assert.ok(!ui.includes("entrepreneur/ads"), 'SysOp/Admin UI must not impersonate the entrepreneur self-service API');

console.log('MvM SysOp/Admin local business ads contract: OK');
