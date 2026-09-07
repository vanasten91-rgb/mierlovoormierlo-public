'use strict';

const fs = require('fs');
const assert = require('assert');

const pwa = fs.readFileSync('plugins/mvm-platform/src/pwa/class-pwa.php', 'utf8');
const content = fs.readFileSync('plugins/mvm-platform/src/pwa/class-content-rest.php', 'utf8');
const register = fs.readFileSync('plugins/mvm-platform/assets/pwa-register.js', 'utf8');
const platform = fs.readFileSync('plugins/mvm-platform/src/class-platform.php', 'utf8');

for (const route of ['/mvm.webmanifest', '/mvm-sw.js', '/mvm-offline/']) {
  assert.ok(pwa.includes(route), `missing PWA route ${route}`);
}
assert.match(pwa, /application\/manifest\+json/);
assert.match(pwa, /Service-Worker-Allowed: \//);
assert.match(pwa, /display'\s*=>\s*'standalone'/);
assert.match(pwa, /theme_color'\s*=>\s*'#1966AE'/);
assert.match(pwa, /apple-touch-icon/);
assert.match(pwa, /'start_url'\s*=>\s*home_url\( '\/' \)/, 'PWA must always start on the real homepage root.');
assert.match(pwa, /url\.pathname === '\/'/, 'Service worker must bypass the homepage root entirely.');
assert.match(pwa, /root-home-v2/, 'Service-worker URL must be version-busted after homepage hardening.');
assert.match(register, /navigator\.serviceWorker\.register/);
assert.match(register, /window\.isSecureContext/);
assert.match(register, /updateViaCache:\s*'none'/);
assert.match(register, /registration\.update\(\)/);

for (const privatePrefix of ["'/hub'", "'/wp-admin'", "'/wp-login.php'", "'/wp-json'", "'/account'"]) {
  assert.ok(pwa.includes(privatePrefix), `service worker must bypass ${privatePrefix}`);
}
const navigationStart = pwa.indexOf("if (request.mode === 'navigate')");
const assetStart = pwa.indexOf('if (MVM_ASSET_PREFIX', navigationStart);
assert.ok(navigationStart >= 0 && assetStart > navigationStart, 'navigation and static-asset branches must remain separate');
const navigationBranch = pwa.slice(navigationStart, assetStart);
assert.match(navigationBranch, /event\.respondWith\(fetch\(request\)\.catch/);
assert.ok(!navigationBranch.includes('cache.put('), 'navigation responses must never be cached');
assert.match(pwa, /url\.pathname\.startsWith\(MVM_ASSET_PREFIX\)/);
assert.match(pwa, /credentials: 'omit'/);
assert.match(pwa, /X-Robots-Tag: noindex, nofollow, noarchive/);

for (const route of ['/content/news', '/content/events', '/content/encyclopedia']) {
  assert.ok(content.includes(route), `missing mobile content endpoint ${route}`);
}
assert.match(content, /'post_status'\s*=>\s*'publish'/);
assert.match(content, /min\( 24/);
assert.match(content, /'event_listing'/);
assert.match(content, /'_event_start_date'/);
for (const type of [
  'mvm_encyclopedie', 'mvm_persoon', 'mvm_locatie', 'mvm_gebouw', 'mvm_gebeurtenis',
  'mvm_vereniging', 'mvm_bedrijf', 'mvm_beeld', 'mvm_bron'
]) {
  assert.ok(content.includes(`'${type}'`), `missing encyclopedia public type ${type}`);
}
for (const privateType of ['mvm_assignment', 'mvm_staff_message', 'peepso-post', 'peepso-message']) {
  assert.ok(!content.includes(`'${privateType}'`), `private type ${privateType} must not enter mobile public API`);
}
assert.ok(!content.includes("'content' => $post->post_content"), 'mobile list API must not expose raw full content');
assert.match(content, /wp_strip_all_tags/);
assert.match(content, /Cache-Control', 'public, max-age=60/);
assert.match(platform, /MvM_Platform_PWA::boot/);
assert.match(platform, /MvM_Platform_Content_REST/);

console.log('MvM PWA privacy/mobile API contract: OK');
