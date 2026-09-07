'use strict';

const fs = require('fs');
const path = require('path');

const root = path.resolve(__dirname, '..');
const files = {
  folders: 'plugins/mvm-hub/modules/communications/class-v5-mail-folder-list-model.php',
  actions: 'plugins/mvm-hub/modules/communications/class-v5-mail-message-action-policy.php',
  bootstrap: 'plugins/mvm-hub/mvm-hub.php',
};

const source = Object.fromEntries(
  Object.entries(files).map(([key, relative]) => [key, fs.readFileSync(path.join(root, relative), 'utf8')])
);

const assert = (condition, message) => {
  if (!condition) throw new Error(message);
};

for (const token of [
  'private const MAX_FOLDERS = 100',
  "'currentProductionMode' => 'read_only'",
  "'productionWritesEnabled' => false",
  "'currentActions'",
  "'targetActions'",
  "'searchVisibility' => 'none'",
  "'aiVisibility'     => 'none'",
]) {
  assert(source.folders.includes(token), `folder model must preserve ${token}`);
}
assert(source.folders.includes("$target_mutable = $can_manage_folders && ! $is_system"), 'system folders must remain protected from rename/delete');
assert(source.folders.includes("if ( '' === $mailbox_id || ! $can_read )"), 'folder model must fail closed without mailbox read grant');

for (const token of [
  "'currentProductionMode' => 'read_only'",
  "'productionWritesEnabled' => false",
  "'currentActions'",
  "'targetActions'",
  "'requiresStepUp'      => true",
  "'requiresIdempotency' => true",
  "'requiresRateLimit'   => true",
  "'requiresCutover'     => true",
  "'searchVisibility' => 'none'",
  "'aiVisibility'     => 'none'",
]) {
  assert(source.actions.includes(token), `message action policy must preserve ${token}`);
}
assert(source.actions.includes("'reply'              => false"), 'current production must not expose reply');
assert(source.actions.includes("$can_read && $can_send && $ready_send && $folder_scoped_read"), 'target reply must require explicit read, send and folder-scoped readiness');

const forbiddenPatterns = [
  /add_action\s*\(/,
  /add_filter\s*\(/,
  /register_rest_route\s*\(/,
  /wp_insert_post\s*\(/,
  /wp_update_post\s*\(/,
  /update_post_meta\s*\(/,
  /delete_post_meta\s*\(/,
  /\$wpdb\s*->/,
  /wp_mail\s*\(/,
  /imap_open\s*\(/,
  /curl_exec\s*\(/,
];

for (const key of ['folders', 'actions']) {
  for (const pattern of forbiddenPatterns) {
    assert(!pattern.test(source[key]), `${key} must remain dormant/pure: ${pattern}`);
  }
}

for (const basename of [
  'class-v5-mail-folder-list-model.php',
  'class-v5-mail-message-action-policy.php',
]) {
  assert(!source.bootstrap.includes(basename), `${basename} must not be loaded by live bootstrap`);
}

console.log('PASS: MvM Hub V5 Mail folder and message action projections remain read-only in production, explicit in target readiness and dormant');
