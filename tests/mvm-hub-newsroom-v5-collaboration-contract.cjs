'use strict';

const fs = require('fs');
const path = require('path');

const root = path.resolve(__dirname, '..');
const ownerPath = 'plugins/mvm-hub/modules/communications/class-v5-collaboration-owner-catalog.php';
const adapterPath = 'plugins/mvm-hub/modules/communications/class-v5-collaboration-read-adapter.php';
const previewPath = 'plugins/mvm-hub/modules/communications/class-v5-communications-preview-model.php';
const runtimePath = 'plugins/mvm-hub/modules/newsroom/class-staff-collaboration.php';
const bootstrapPath = 'plugins/mvm-hub/mvm-hub.php';

const owner = fs.readFileSync(path.join(root, ownerPath), 'utf8');
const adapter = fs.readFileSync(path.join(root, adapterPath), 'utf8');
const preview = fs.readFileSync(path.join(root, previewPath), 'utf8');
const runtime = fs.readFileSync(path.join(root, runtimePath), 'utf8');
const bootstrap = fs.readFileSync(path.join(root, bootstrapPath), 'utf8');

const assert = (condition, message) => {
  if (!condition) throw new Error(message);
};

for (const token of [
  "'current_owner'          => 'current-newsroom-staff-collaboration'",
  "'storage_type'            => 'mvm_staff_notice'",
  "'storage_type'            => 'mvm_staff_chat'",
  "'read_capability'         => 'mvm_staff_board_view'",
  "'read_capability'         => 'mvm_team_chat_access'",
  "'write_promotion_enabled' => false",
  "'search_visibility'       => 'none'",
]) {
  assert(owner.includes(token), `collaboration owner inventory must preserve ${token}`);
}

assert(owner.includes("'retention_days'          => 30"), 'team chat retention inventory must stay 30 days');
assert(owner.includes("'max_message_length'      => 1500"), 'team chat max length inventory must stay 1500 chars');

for (const token of [
  "'search_visibility'    => 'none'",
  "'ai_visibility'        => 'none'",
  "'audit_body_allowed'   => false",
  "'read_only_projection' => true",
]) {
  assert(adapter.includes(token), `collaboration adapter must preserve ${token}`);
}

for (const token of [
  "'ownerMode' => 'coexistence_read_only'",
  "'canWrite'  => false",
  "'canSend'       => false",
  "'targetMode'             => 'bidirectional'",
  "'receiveRequired'        => true",
  "'sendRequired'           => true",
  "'currentProductionMode'  => 'read_only'",
  "'v2PromotionEnabled'     => false",
  "'receiveSyncPromoted'    => false",
  "'sendRoutePromoted'      => false",
  "'searchVisibility' => 'none'",
  "'aiVisibility'     => 'none'",
  "'auditBodyAllowed' => false",
]) {
  assert(preview.includes(token), `communications preview must preserve ${token}`);
}

assert(runtime.includes("private const BOARD_TYPE          = 'mvm_staff_notice'"), 'current runtime board storage owner must remain discoverable');
assert(runtime.includes("private const CHAT_TYPE           = 'mvm_staff_chat'"), 'current runtime chat storage owner must remain discoverable');
assert(runtime.includes('private const CHAT_RETENTION_DAYS = 30'), 'current runtime retention parity must remain discoverable');
assert(runtime.includes('self::post_string( \'message\', 1500 )'), 'current runtime max chat length parity must remain discoverable');

const forbidden = [
  /add_action\s*\(/,
  /add_filter\s*\(/,
  /register_rest_route\s*\(/,
  /wp_insert_post\s*\(/,
  /wp_update_post\s*\(/,
  /wp_delete_post\s*\(/,
  /update_post_meta\s*\(/,
  /delete_post_meta\s*\(/,
  /register_post_type\s*\(/,
  /\$wpdb\s*->/,
];

for (const [label, source] of [['owner', owner], ['adapter', adapter], ['preview', preview]]) {
  for (const pattern of forbidden) {
    assert(!pattern.test(source), `${label} V5 collaboration layer must remain dormant/read-only: ${pattern}`);
  }
}

for (const basename of [
  'class-v5-collaboration-owner-catalog.php',
  'class-v5-collaboration-read-adapter.php',
  'class-v5-communications-preview-model.php',
]) {
  assert(!bootstrap.includes(basename), `${basename} must not be loaded by live bootstrap`);
}

console.log('PASS: MvM Hub V5 collaboration/communications stays current-owner-bound, Mail production-read-only, bidirectional-targeted, non-searchable and dormant');
