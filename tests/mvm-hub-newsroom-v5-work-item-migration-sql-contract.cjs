const fs = require('fs');
const path = require('path');

const file = path.join(__dirname, '..', 'plugins', 'mvm-hub', 'core', 'class-v5-work-item-migration-plan.php');
const source = fs.readFileSync(file, 'utf8');

function assert(condition, message) {
  if (!condition) {
    console.error(`FAIL: ${message}`);
    process.exit(1);
  }
}

assert(source.includes('schema-delta parsing is line-oriented'), 'migration plan must document the line-oriented schema parser contract');
assert(source.includes('display widths'), 'migration plan must document MariaDB display-width stability');
assert(!source.includes(', KEY '), 'multiple KEY declarations must never share one SQL line');
assert(!source.includes(', PRIMARY KEY'), 'PRIMARY KEY must start on its own SQL line');
assert(!source.includes(', UNIQUE KEY'), 'UNIQUE KEY must start on its own SQL line');
assert(!source.includes('BIGINT UNSIGNED'), 'migration SQL must use MariaDB-canonical BIGINT(20) display width');
assert(!source.includes('INT UNSIGNED'), 'migration SQL must use MariaDB-canonical INT(10) display width');

const primaryKeys = source.match(/PRIMARY KEY  \(/g) || [];
assert(primaryKeys.length === 4, `expected 4 schema-helper-compatible PRIMARY KEY declarations, got ${primaryKeys.length}`);

for (const indexName of [
  'status_owner_deadline',
  'status_team_deadline',
  'domain_object',
  'workflow_state',
  'classification_status',
  'updated_at_utc',
  'depends_on',
  'work_item_position',
  'work_item_time',
  'workflow_to_state',
  'actor_time',
  'request_id',
]) {
  assert(source.includes(`KEY ${indexName} (`), `missing index declaration ${indexName}`);
}

for (const declaration of [
  'id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT',
  'type VARCHAR(64) NOT NULL',
  'domain VARCHAR(64) NOT NULL',
  "object_type VARCHAR(64) NOT NULL DEFAULT ''",
  'depends_on_work_item_id BIGINT(20) UNSIGNED NOT NULL',
  'item_key VARCHAR(96) NOT NULL',
  'workflow_version INT(10) UNSIGNED NOT NULL',
  'position INT(10) UNSIGNED NOT NULL DEFAULT 0',
]) {
  assert(source.includes(`. \"  ${declaration}`), `expected dedicated SQL declaration line for ${declaration}`);
}

console.log('PASS: V5 Work Item migration SQL remains line-oriented, width-stable and index declarations are isolated.');
