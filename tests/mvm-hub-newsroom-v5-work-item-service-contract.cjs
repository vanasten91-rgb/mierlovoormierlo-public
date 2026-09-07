'use strict';

const fs = require('fs');
const assert = require('assert');

function read(path) {
  return fs.readFileSync(path, 'utf8');
}

const repo = read('plugins/mvm-hub/core/interface-work-item-repository.php');
const service = read('plugins/mvm-hub/core/class-work-item-service.php');
const migration = read('plugins/mvm-hub/core/class-v5-work-item-migration-plan.php');
const wpdbRepo = read('plugins/mvm-hub/core/class-wpdb-work-item-repository.php');
const bootstrap = read('plugins/mvm-hub/mvm-hub.php');

assert.match(repo, /expected_version/);
assert.match(repo, /transaction\s*\(/);
assert.match(repo, /append_transition/);

assert.match(service, /mvm_work_version_conflict/);
assert.match(service, /requires_object_check/);
assert.match(service, /append_transition/);
assert.match(service, /request_id/);
assert.doesNotMatch(service, /\$wpdb/);
assert.doesNotMatch(service, /register_rest_route/);
assert.doesNotMatch(service, /add_action\s*\(/);

assert.match(migration, /expand_contract/);
assert.match(migration, /requiresSeparateApproval/);
assert.match(migration, /productionWrites'\s*=>\s*false/);
assert.match(migration, /UNIQUE KEY request_id/);
assert.doesNotMatch(migration, /dbDelta\s*\(/);
assert.doesNotMatch(migration, /->query\s*\(/);

assert.match(wpdbRepo, /START TRANSACTION/);
assert.match(wpdbRepo, /ROLLBACK/);
assert.match(wpdbRepo, /COMMIT/);
assert.match(wpdbRepo, /version=version\+1 WHERE id=%d AND version=%d/);
assert.match(wpdbRepo, /UNIQUE|request_id/);
assert.doesNotMatch(wpdbRepo, /register_rest_route/);
assert.doesNotMatch(wpdbRepo, /add_action\s*\(/);

for (const path of [
  'core/interface-work-item-repository.php',
  'core/class-work-item-service.php',
  'core/class-v5-work-item-migration-plan.php',
  'core/class-wpdb-work-item-repository.php',
]) {
  assert(!bootstrap.includes(path), `${path} must stay dormant before approved migration/cutover`);
}

console.log('PASS: V5 durable work-item service, WPDB repository and migration remain fail-closed and dormant');
