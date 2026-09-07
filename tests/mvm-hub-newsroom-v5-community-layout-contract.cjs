'use strict';

const fs = require('fs');
const assert = require('assert');

const owner = fs.readFileSync('plugins/mvm-hub/modules/community/class-v5-community-owner-catalog.php', 'utf8');
const community = fs.readFileSync('plugins/mvm-hub/modules/community/class-v5-community-moderation-model.php', 'utf8');
const registry = fs.readFileSync('plugins/mvm-hub/modules/layout/class-v5-layout-template-registry.php', 'utf8');
const layout = fs.readFileSync('plugins/mvm-hub/modules/layout/class-v5-layout-studio-model.php', 'utf8');
const bootstrap = fs.readFileSync('plugins/mvm-hub/mvm-hub.php', 'utf8');

assert.match(owner, /canonical_owner'\s*=>\s*'peepso'/);
assert.match(owner, /canonical_owner'\s*=>\s*'wpforo'/);
assert.match(owner, /v5_write_owner'\s*=>\s*false/);

assert.match(community, /requiresObjectAuthorization/);
assert.match(community, /searchVisibility'\s*=>\s*'none'/);
assert.match(community, /aiVisibility'\s*=>\s*'none'/);
assert.match(community, /contentBodyIncluded'\s*=>\s*false/);
assert.match(community, /v5WriteOwner'\s*=>\s*false/);
assert.doesNotMatch(community, /register_rest_route/);
assert.doesNotMatch(community, /add_action\s*\(/);

assert.match(registry, /executableContentAllowed'\s*=>\s*false/);
assert.match(registry, /arbitraryHtmlAllowed'\s*=>\s*false/);
assert.doesNotMatch(registry, /eval\s*\(/);
assert.doesNotMatch(registry, /register_rest_route/);

assert.match(layout, /desktopPreviewApproved/);
assert.match(layout, /mobilePreviewApproved/);
assert.match(layout, /darkPreviewApproved/);
assert.match(layout, /accessibilityChecked/);
assert.match(layout, /rollbackRevisionExists/);
assert.match(layout, /canPublishProduction'\s*=>\s*false/);

for (const path of [
  'modules/community/class-v5-community-owner-catalog.php',
  'modules/community/class-v5-community-moderation-model.php',
  'modules/layout/class-v5-layout-template-registry.php',
  'modules/layout/class-v5-layout-studio-model.php',
]) {
  assert(!bootstrap.includes(path), `${path} must remain dormant before approved cutover`);
}

console.log('PASS: V5 Community and Layout Studio remain safe, owner-preserving and dormant');
