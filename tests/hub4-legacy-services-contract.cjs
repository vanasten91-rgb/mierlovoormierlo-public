'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const root = path.resolve(__dirname, '..', 'plugins', 'mvm-hub4-rc-direct');
const main = fs.readFileSync(path.join(root, 'mvm-hub4.php'), 'utf8');
const loader = fs.readFileSync(path.join(root, 'src', 'class-legacy-services.php'), 'utf8');
let assertions = 0;
const check = (condition, message) => {
    assert.ok(condition, message);
    assertions += 1;
};

check(main.includes("require_once MVM_HUB4_DIR . 'src/class-legacy-services.php'"), 'Hub4 must load the legacy services owner explicitly.');
check(main.includes('MvM_Hub4_Legacy_Services::bootstrap();'), 'Hub4 must bootstrap adopted legacy services.');
check(main.indexOf('MvM_Hub4_Legacy_Services::bootstrap();') < main.indexOf("require_once MVM_HUB4_DIR . 'src/class-audit.php'"), 'Legacy services must load during the plugin-load phase before Hub4 hooks initialize.');

check(loader.includes("LEGACY_PLUGIN_BASENAME = 'mierlo-voor-mierlo-hub/mierlo-voor-mierlo-hub.php'"), 'Standalone Hub3 plugin identity must be explicit.');
check(loader.includes("LEGACY_VERSION         = '3.8.2'"), 'Adopted legacy version must be pinned.');
check(loader.includes('EXPECTED_FILE_COUNT    = 400'), 'Migration file count must be pinned.');
check(loader.includes("EXPECTED_TREE_SHA256   = '70673d5bdbe9c107103adb5eeccd4b55754b766729a0f21630b3ab6bb346fc39'"), 'Full reCAPTCHA-hardened legacy tree digest must be pinned.');
check(loader.includes("'persistent'      => trailingslashit( WP_CONTENT_DIR ) . 'mvm-hub4-legacy/hub3'"), 'Persistent wp-content payload must be preferred for upgrade safety.');
check(loader.indexOf("'persistent'") < loader.indexOf("'embedded'"), 'Persistent payload must be checked before the plugin-local embedded tree.');
check(loader.includes("'embedded'        => trailingslashit( MVM_HUB4_DIR ) . 'legacy/hub3'"), 'Embedded Hub4 payload must remain an explicit migration fallback.');
check(loader.includes("'legacy-fallback' => trailingslashit( WP_PLUGIN_DIR ) . 'mierlo-voor-mierlo-hub'"), 'Old plugin directory may only remain as an explicit rollback fallback.');

check(loader.includes("defined( 'MVM_SUITE_VERSION' ) || class_exists( 'MVM_Suite', false )"), 'Already-loaded Hub3 suite must prevent duplicate loading.');
check(loader.includes("in_array( self::LEGACY_PLUGIN_BASENAME, $active_plugins, true )"), 'Active standalone Hub3 must prevent adoption loading.');
check(loader.includes("hash_file( 'sha256', $path )") && loader.includes('hash_equals( $expected_hash, $actual_hash )'), 'Critical payload files must be integrity checked before loading.');
check(loader.includes('require_once $bootstrap;'), 'Only the validated legacy bootstrap may be executed.');
check(loader.includes("self::$state  = 'adopted'"), 'Successful adoption must expose an explicit runtime state.');
check(loader.includes("define( 'MVM_HUB4_LEGACY_SERVICES_ADOPTED', true )"), 'Successful ownership transfer must set a process-level marker.');

for (const required of [
    'includes/class-mvm-suite.php',
    'modules/hub/mvm-beheerhub.php',
    'modules/redactie/mvm-redactiehub.php',
    'modules/encyclopedie/includes/class-mvm-encyclopedie-v2940.php',
    'modules/event-bridge/mierlo-event-migrator-bridge.php',
    'modules/forum/mvm-forum.php',
    'modules/mail/mvm-mail.php',
    'modules/mierlo-vandaag/mvm-mierlo-vandaag.php',
    'modules/site-integrations/mvm-site-integrations.php',
    'modules/staff-docs/mvm-staff-docs.php',
    'modules/runtime-patches/mvm-runtime-patches.php',
    'modules/runtime-patches/auth/010-258.php',
    'modules/runtime-patches/auth/010-288.php',
]) {
    check(loader.includes(`'${required}'`), `Critical legacy service ${required} must be pinned by hash.`);
}
check(loader.includes("'modules/runtime-patches/auth/010-288.php'                         => '994e69c61c7d8f63e301b9d7724028c3100a057238556f706020a7c41b6b2713'"), 'The live reCAPTCHA-hardened staff-login patch must be pinned exactly.');

check(!/\b(?:unlink|rmdir|delete_option|deactivate_plugins|wp_delete|DROP\s+TABLE)\b/.test(loader), 'Runtime adoption loader must not delete data, files or plugins.');
check(loader.includes("MVM_HUB4_DISABLE_LEGACY_SERVICES"), 'Emergency fail-safe disable switch must exist.');
check(loader.includes('public static function status(): array'), 'Release smoke must be able to inspect adoption state.');

console.log(`Hub 4 legacy services contract: ${assertions} assertions passed.`);
