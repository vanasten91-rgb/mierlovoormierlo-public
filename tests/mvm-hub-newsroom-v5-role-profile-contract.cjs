const fs = require('fs');
const path = require('path');

const bundles = fs.readFileSync(path.join(__dirname, '..', 'plugins', 'mvm-hub', 'core', 'class-role-capability-bundles.php'), 'utf8');
const map = fs.readFileSync(path.join(__dirname, '..', 'plugins', 'mvm-hub', 'core', 'class-v5-role-uat-role-map.php'), 'utf8');

function assert(condition, message) {
  if (!condition) {
    console.error(`FAIL: ${message}`);
    process.exit(1);
  }
}

for (const role of ['mvm_agenda_editor', 'mvm_encyclopedie_editor', 'mvm_mierlokaal_editor', 'mvm_communications']) {
  assert(bundles.includes(`'${role}'`), `missing dedicated V5 role ${role}`);
}

for (const capability of [
  'mvm_intake_triage',
  'mvm_intake_assign',
  'mvm_community_moderate',
  'mvm_community_escalate',
  'mvm_encyclopedie_view_editorial',
  'mvm_contextlinks_review',
]) {
  assert(bundles.includes(`'${capability}'`), `role bundles must include ${capability}`);
}

assert(bundles.includes('private const SCHEMA_VERSION = 3;'), 'capability schema must advance for the V5 role profiles');
assert(bundles.includes("Runtime_Gates::capability_reconciliation_enabled()"), 'role reconciliation must remain behind the explicit runtime gate');
assert(bundles.includes("'mvm_marketplace_moderate'"), 'MierLokaal role must retain marketplace moderation');
assert(bundles.includes('externally_owned_capabilities'), 'cross-plugin capabilities must be excluded from destructive Hub reconciliation');

for (const persona of [
  'journalist', 'redacteur', 'editor', 'fotograaf', 'moderator', 'vertaler', 'teamleider',
  'agenda_editor', 'encyclopedie_editor', 'mierlokaal_editor', 'communications', 'sysop', 'partner',
]) {
  assert(map.includes(`'${persona}' =>`), `missing UAT persona mapping for ${persona}`);
}

assert(map.includes("array( 'mvm_agenda_editor' )"), 'agenda_editor must use its dedicated least-privilege role');
assert(map.includes("array( 'mvm_encyclopedie_editor' )"), 'encyclopedie_editor must use its dedicated least-privilege role');
assert(map.includes("array( 'mvm_mierlokaal_editor' )"), 'mierlokaal_editor must use its dedicated least-privilege role');
assert(map.includes("array( 'mvm_communications' )"), 'communications must use its dedicated least-privilege role');
assert(map.includes("Capabilities::NEWSROOM_ACCESS, Capabilities::NEWS_PUBLISH, Capabilities::TECHNICAL_ACCESS"), 'MierLokaal persona must explicitly deny Newsroom, publish and technical access');
assert(map.includes("Capabilities::NEWS_PUBLISH, Capabilities::TECHNICAL_ACCESS"), 'specialist personas must preserve publish/technical negative boundaries');

console.log('PASS: V5 UAT personas map to explicit least-privilege WordPress role profiles.');
