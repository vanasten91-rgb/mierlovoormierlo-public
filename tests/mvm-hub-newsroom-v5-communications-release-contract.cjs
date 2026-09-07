const fs = require('fs');
const path = require('path');

const release = fs.readFileSync(
  path.join(__dirname, '..', 'plugins', 'mvm-hub', 'core', 'class-communications-mail-release.php'),
  'utf8'
);

function assert(condition, message) {
  if (!condition) {
    console.error(`FAIL: ${message}`);
    process.exit(1);
  }
}

assert(
  release.includes("$schema_version = (int) ( $roles['schemaVersion'] ?? 0 );"),
  'Communications release must read the declared capability schema version dynamically'
);
assert(
  release.includes("$stored_version = (int) ( $roles['storedVersion'] ?? 0 );"),
  'Communications release must read the stored capability schema version'
);
assert(
  release.includes('$schema_version < 1 || $stored_version !== $schema_version'),
  'Communications release must fail closed unless stored and declared capability schemas match'
);
assert(
  !/\b[0-9]+\s*!==\s*\(int\)\s*\(\s*\$roles\['storedVersion'\]/.test(release),
  'Communications release must not hardcode a capability schema number'
);
assert(
  !release.includes('Capabilityschema 2 is niet actief.'),
  'legacy capability schema 2 error must not remain in Communications release preflight'
);
assert(
  release.includes("new \\WP_Error( 'mvm_communications_release_caps'"),
  'capability schema drift must remain a fail-closed release error'
);

console.log('PASS: Communications release follows the declared capability schema and rejects drift without a stale magic number.');
