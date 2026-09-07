'use strict';

const fs = require('fs');
const assert = require('assert');

const boundary = fs.readFileSync('plugins/mvm-hub/core/interface-domain-event-outbox.php', 'utf8');
const contract = fs.readFileSync('plugins/mvm-hub/core/class-v5-domain-event-outbox-contract.php', 'utf8');
const bootstrap = fs.readFileSync('plugins/mvm-hub/mvm-hub.php', 'utf8');

assert.match(boundary, /at-least-once/);
assert.match(boundary, /idempotent/);
assert.match(boundary, /mark_delivered/);
assert.match(boundary, /mark_failed/);

assert.match(contract, /MAX_PAYLOAD_KEYS/);
assert.match(contract, /MAX_PAYLOAD_BYTES/);
assert.match(contract, /forbidden_payload_key/);
assert.match(contract, /searchVisibility'\s*=>\s*'none'/);
assert.match(contract, /aiVisibility'\s*=>\s*'none'/);
assert.match(contract, /containsBody'\s*=>\s*false/);
assert.match(contract, /containsCredentials'\s*=>\s*false/);
assert.match(contract, /UNIQUE KEY correlation_event/);
assert.match(contract, /productionWrites'\s*=>\s*false/);
assert.match(contract, /requiresSeparateApproval'\s*=>\s*true/);
assert.doesNotMatch(contract, /register_rest_route/);
assert.doesNotMatch(contract, /add_action\s*\(/);
assert.doesNotMatch(contract, /->query\s*\(/);

for (const path of [
  'core/interface-domain-event-outbox.php',
  'core/class-v5-domain-event-outbox-contract.php',
]) {
  assert(!bootstrap.includes(path), `${path} must remain dormant until schema/release approval`);
}

console.log('PASS: V5 domain-event outbox stays metadata-only, idempotent and dormant');
