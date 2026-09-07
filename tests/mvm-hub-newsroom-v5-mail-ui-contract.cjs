'use strict';

const fs = require('fs');
const path = require('path');

const root = path.resolve(__dirname, '..');
const files = {
  compose: 'plugins/mvm-hub/modules/communications/class-v5-mail-compose-intent.php',
  attachment: 'plugins/mvm-hub/modules/communications/class-v5-mail-attachment-policy.php',
  bootstrap: 'plugins/mvm-hub/mvm-hub.php',
};

const source = Object.fromEntries(
  Object.entries(files).map(([key, relative]) => [key, fs.readFileSync(path.join(root, relative), 'utf8')])
);

const assert = (condition, message) => {
  if (!condition) throw new Error(message);
};

for (const token of [
  "'currentProductionMode' => 'read_only'",
  "'productionEligible'    => false",
  "'requiresStepUp'        => true",
  "'requiresIdempotency'   => true",
  "'requiresRateLimit'     => true",
  "'requiresCutover'       => true",
  "'clientControlled' => false",
  "'deriveFromAuthorizedMailbox' => true",
]) {
  assert(source.compose.includes(token), `compose intent must preserve ${token}`);
}

for (const forbidden of ["'from' =>", "'sender' =>", "'returnPath' =>", "'smtpFrom' =>"]) {
  assert(!source.compose.includes(forbidden), `compose output must not project client sender field ${forbidden}`);
}

assert(source.compose.includes("array( 'from', 'sender', 'returnPath', 'return_path', 'smtpFrom' )"), 'compose input must explicitly reject sender spoof fields');
assert(source.compose.includes("$mailbox_id !== (string) ( $source['mailboxId'] ?? '' )"), 'reply/forward source must remain same-mailbox scoped');

for (const token of [
  "'allowedForSend'",
  "'privateStorageRequired' => true",
  "'ownerAuthorizationRequired' => true",
  "'draftLinkRequired' => true",
  "'malwareScanRequired' => true",
  "'publicUrlAllowed' => false",
  "'malware_scan_pending'",
  "'malware_detected'",
  "'attachment_quarantined'",
]) {
  assert(source.attachment.includes(token), `attachment policy must preserve ${token}`);
}

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

for (const key of ['compose', 'attachment']) {
  for (const pattern of forbiddenPatterns) {
    assert(!pattern.test(source[key]), `${key} must remain dormant/pure: ${pattern}`);
  }
}

for (const basename of [
  'class-v5-mail-compose-intent.php',
  'class-v5-mail-attachment-policy.php',
]) {
  assert(!source.bootstrap.includes(basename), `${basename} must not be loaded by live bootstrap`);
}

console.log('PASS: MvM Hub V5 Mail UI intent and attachment policies remain private, fail-closed and dormant');
