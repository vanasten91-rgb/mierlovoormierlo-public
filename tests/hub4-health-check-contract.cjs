'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const root = path.resolve(__dirname, '..');
const plugin = path.join(root, 'plugins', 'mvm-hub4-rc-direct');
const read = (file) => fs.readFileSync(path.join(plugin, file), 'utf8');

const main = read('mvm-hub4.php');
const health = read('src/class-health-check.php');
const routeAdapter = read('src/class-health-route-checks.php');
const smartLinksHealth = read('src/class-smart-links-health.php');
const healthRest = read('src/class-health-check-rest.php');
const template = read('templates/system-overview.php');
const javascript = read('assets/system-overview.js');
const css = read('assets/system-overview.css');

assert.match(main, /class-health-route-checks\.php/);
assert.match(main, /class-health-check\.php/);
assert.match(main, /class-health-check-rest\.php/);
assert.match(main, /MvM_Hub4_Health_Check_REST::init\(\)/);

const localIds = [...health.matchAll(/array\( 'id' => '([^']+)', 'label'/g)].map((match) => match[1]);
const routeIds = [...routeAdapter.matchAll(/array\( 'id' => '([^']+)', 'label'/g)].map((match) => match[1]);
assert.ok(localIds.length >= 26, `expected at least 26 local checks, received ${localIds.length}`);
assert.equal(new Set([...localIds, ...routeIds]).size, localIds.length + routeIds.length, 'check IDs must be unique');
assert.ok(routeIds.length >= 5, 'public route adapter must cover the core routes');

for (const status of ['ok', 'warning', 'critical', 'unknown']) {
    assert.ok(health.includes(`'${status}'`), `status ${status} must be supported`);
    assert.ok(smartLinksHealth.includes(`'${status}'`), `Smart Links adapter must support status ${status}`);
    assert.ok(template.includes(`'${status}'`), `UI label for ${status} must exist`);
}
assert.match(health, /private const VALID_STATUSES = array\( 'ok', 'warning', 'critical', 'unknown' \)/);
assert.match(health, /public static function summarize\( array \$checks \): array/);
assert.match(health, /summary\['total'\]\+\+/);

assert.match(health, /function_exists\( 'as_has_scheduled_action' \)/);
assert.match(health, /class_exists\( 'ActionScheduler' \)/);
assert.match(health, /table_exists/);
assert.match(health, /Code Snippets is niet beschikbaar/);
assert.match(health, /set_error_handler/);
assert.match(health, /restore_error_handler/);

assert.match(health, /EXPECTED_CANONICAL_SOURCES = 118/);
assert.match(health, /EXPECTED_STOPPED_DUPLICATES = 118/);
for (const source of [health, routeAdapter, smartLinksHealth]) {
    assert.doesNotMatch(source, /\b(?:update_option|add_option|delete_option|wp_insert_post|wp_update_post|wp_delete_post)\s*\(/);
    assert.doesNotMatch(source, /\$wpdb->(?:update|replace|insert|delete)\s*\(/);
    assert.doesNotMatch(source, /\b(?:wp_remote_get|wp_remote_post|wp_remote_request|curl_exec|fsockopen)\s*\(/);
}

assert.match(healthRest, /'\/system\/health'/);
assert.match(healthRest, /'permission_callback'\s*=>\s*MvM_Hub4_Security::require_capability\( MvM_Hub4_Capabilities::SYSTEM_OVERVIEW \)/);
assert.match(healthRest, /class-smart-links-health\.php/);
assert.match(healthRest, /MvM_Hub4_Smart_Links_Health::extend\( \$data, \$fresh \)/);
assert.doesNotMatch(healthRest, /__return_true/);

assert.match(smartLinksHealth, /function_exists\( 'mvm_smart_links_health_snapshot_v1' \)/);
assert.match(smartLinksHealth, /str_starts_with\( \$id, 'smart_links_' \)/);
assert.match(smartLinksHealth, /MvM_Hub4_Health_Check::summarize\( \$checks \)/);
assert.match(smartLinksHealth, /CACHE_TTL\s*=\s*5 \* MINUTE_IN_SECONDS/);
assert.match(smartLinksHealth, /wp_cache_get/);
assert.match(smartLinksHealth, /wp_cache_set/);
assert.match(smartLinksHealth, /wp_cache_delete/);
assert.match(smartLinksHealth, /catch \( Throwable \$error \)/);

for (const source of [health, smartLinksHealth]) {
    const outputKeys = [...source.matchAll(/['"]([a-zA-Z0-9_-]+)['"]\s*=>/g)].map((match) => match[1]);
    for (const key of outputKeys) {
        assert.doesNotMatch(key, /password|passwd|token|nonce|cookie|authorization|secret|api[_-]?key|e[_-]?mail|private/i);
    }
}

assert.match(health, /CACHE_TTL\s*=\s*5 \* MINUTE_IN_SECONDS/);
assert.match(health, /public static function invalidate_cache/);
assert.match(health, /wp_cache_delete/);
assert.match(routeAdapter, /never performs self-HTTP requests/);

assert.match(template, /<h2 id="mvm-system-health-title">Systeemstatus<\/h2>/);
assert.match(template, /<details[^>]*data-mvm-health-details/);
assert.match(template, /<summary>/);
assert.match(template, /mvm-system-health__item-status/);
assert.match(javascript, /healthLabels/);
assert.match(javascript, /document\.createElement/);
assert.match(javascript, /textContent/);
assert.doesNotMatch(javascript, /\b(?:innerHTML|outerHTML|insertAdjacentHTML|eval)\b/);
assert.match(javascript, /'X-WP-Nonce'/);
assert.match(javascript, /credentials: 'same-origin'/);

assert.match(css, /#1966AE/i);
assert.match(css, /prefers-color-scheme:dark/);
assert.match(css, /html\[data-theme=dark\]/);
assert.match(css, /@media\(max-width:560px\)/);
assert.match(css, /summary:focus-visible/);

console.log(`Hub 4 health check contract: ${localIds.length + routeIds.length} core checks plus Smart Links adapter contracts passed.`);
