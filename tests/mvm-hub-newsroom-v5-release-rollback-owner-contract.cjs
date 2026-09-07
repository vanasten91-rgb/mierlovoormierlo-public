'use strict';

const fs = require('node:fs');
const path = require('node:path');

const root = path.resolve(__dirname, '..');
const controllerPath = path.join(root, 'plugins', 'mvm-hub', 'release', 'class-release-control-rest-controller.php');
const cronPath = path.join(root, 'plugins', 'mvm-hub', 'core', 'class-cron-release.php');
const controller = fs.readFileSync(controllerPath, 'utf8');
const cron = fs.readFileSync(cronPath, 'utf8');

function assert(condition, message) {
  if (!condition) {
    console.error(`FAIL: ${message}`);
    process.exit(1);
  }
}

assert(controller.includes('public static function rollback_owner_status_for'), 'pure rollback-owner evaluator missing');
assert(controller.includes("'hub4'"), 'Hub4 rollback-owner path missing');
assert(controller.includes("'persistent_legacy_services'"), 'persistent adopted rollback-owner path missing');
assert(controller.includes("'unavailable'"), 'fail-closed rollback-owner state missing');
assert(controller.includes("'adopted' !== (string) ( $legacy_status['state'] ?? '' )"), 'adopted state must be mandatory');
assert(controller.includes("'persistent' !== (string) ( $legacy_status['source'] ?? '' )"), 'persistent source must be mandatory');
assert(controller.includes("empty( $legacy_status['persistentReady'] )"), 'persistent integrity readiness must be mandatory');
assert(controller.includes("(bool) ( $legacy_status['hub4PluginRequired'] ?? true )"), 'missing Hub4 retirement evidence must fail closed');
assert(controller.includes('$rollback_owner = $this->rollback_owner_status();'), 'release preflight must consume rollback-owner evaluator');
assert(controller.includes("'mvm_release_rollback_owner'"), 'release preflight must expose explicit rollback-owner denial');
assert(!controller.includes("if ( ! $this->hub4_active() ) {\n            return new \\WP_Error( 'mvm_release_hub4'"), 'legacy Hub4-only preflight must not return');
assert(controller.includes("'rollbackOwner' => $this->rollback_owner_status()"), 'release status must expose resolved rollback owner');
assert(controller.includes("'rollback_owner' => $this->rollback_owner_status()"), 'release audit must record resolved rollback owner');

assert(cron.includes('Release_Control_REST_Controller::rollback_owner_status_for'), 'cron preflight must reuse the release rollback-owner evaluator');
assert(cron.includes("'mvm_cron_release_rollback_owner'"), 'cron preflight must expose explicit rollback-owner denial');
assert(!cron.includes('if ( ! is_plugin_active( self::HUB4_PLUGIN ) )'), 'cron handoff must not require active Hub4 when persistent rollback owner is proven');
assert(cron.includes("'rollback_owner'           => $rollback_owner"), 'cron success audit must record the resolved rollback owner');
assert(cron.includes("'rollbackOwner' => $rollback_owner"), 'cron preflight must preserve the resolved rollback owner for the promotion audit');
assert(cron.includes("'rollbackOwner' => Release_Control_REST_Controller::rollback_owner_status_for"), 'cron snapshot must expose the resolved rollback owner');
assert(cron.includes("$schema_version = (int) ( $roles['schemaVersion'] ?? 0 )"), 'cron role check must read the declared capability schema version');
assert(cron.includes("$stored_version = (int) ( $roles['storedVersion'] ?? 0 )"), 'cron role check must read the reconciled stored schema version');
assert(cron.includes('0 < $schema_version && $schema_version === $stored_version'), 'cron role check must require a positive matching schema version');
assert(!cron.includes("return 2 === (int) ( $roles['storedVersion'] ?? 0 )"), 'cron release must not hardcode an obsolete capability schema version');

console.log('PASS: MvM Hub release rollback-owner state-machine contract');
