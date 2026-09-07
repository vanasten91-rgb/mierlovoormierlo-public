const fs = require('fs');
const path = require('path');

const root = path.resolve(__dirname, '..');
const files = [
  'plugins/mvm-hub/integrations/mail/interface-mail-sync-state-store.php',
  'plugins/mvm-hub/integrations/mail/interface-mail-v2-lifecycle-provider.php',
  'plugins/mvm-hub/integrations/mail/class-option-mail-sync-state-store.php',
  'plugins/mvm-hub/integrations/mail/class-v5-imap-mail-sync-provider.php',
  'plugins/mvm-hub/integrations/mail/class-v5-imap-attachment-reader.php',
  'plugins/mvm-hub/integrations/mail/class-v5-folder-scoped-mail-provider-adapter.php',
  'plugins/mvm-hub/integrations/mail/class-v5-mail-provider-factory.php',
  'plugins/mvm-hub/modules/communications/class-v5-mail-sync-state-machine.php',
];

function fail(message) {
  console.error(`FAIL: ${message}`);
  process.exit(1);
}

for (const relative of files) {
  const full = path.join(root, relative);
  if (!fs.existsSync(full)) fail(`${relative} is missing`);
  const source = fs.readFileSync(full, 'utf8');
  for (const forbidden of ['register_rest_route(', 'admin_post_', 'wp_ajax_', 'add_action(', 'add_filter(']) {
    if (source.includes(forbidden)) fail(`${relative} must remain hook-free (${forbidden})`);
  }
}

// The strict V5 provider/sync construction stack remains dormant. The incoming
// IMAP attachment reader is the sole shared primitive intentionally loaded by
// alpha5 because the existing GET attachment route uses it behind login,
// capability, mailbox/message ACL, session + step-up, content validation and a
// fail-closed malware scan. Loading that pure reader does not promote Mail V2
// sync, lifecycle or writes.
const bootstrap = fs.readFileSync(path.join(root, 'plugins/mvm-hub/mvm-hub.php'), 'utf8');
const alpha5RuntimeShared = new Set([
  'class-v5-imap-attachment-reader.php',
]);
for (const relative of files) {
  const basename = path.basename(relative);
  if (alpha5RuntimeShared.has(basename)) {
    if (!bootstrap.includes(basename)) fail(`${basename} must be loaded for the protected alpha5 incoming-attachment read path`);
  } else if (bootstrap.includes(basename)) {
    fail(`${basename} must not be loaded by the live alpha5 bootstrap`);
  }
}

const incomingService = fs.readFileSync(path.join(root, 'plugins/mvm-hub/modules/communications/class-incoming-mail-attachment-service.php'), 'utf8');
for (const required of [
  'V5_IMAP_Attachment_Reader',
  'authorize_session',
  'authorize_step_up',
  'authorize_read',
  'authorize_message',
  'attachment_content_allowed',
  "'clean' !== sanitize_key",
]) {
  if (!incomingService.includes(required)) fail(`alpha5 incoming attachment service must enforce ${required}`);
}
const readController = fs.readFileSync(path.join(root, 'plugins/mvm-hub/modules/communications/class-mail-read-rest-controller.php'), 'utf8');
if (!readController.includes('\\WP_REST_Server::READABLE') || !readController.includes('Capabilities::can_read_mail()')) {
  fail('incoming attachment route must remain GET-only and require Mail read capability');
}
if (!readController.includes("'/communications/mail/messages/(?P<id>[A-Za-z0-9._:-]+)/attachments/")) {
  fail('incoming attachment reader must remain scoped to the explicit Mail message attachment read route');
}
if (!readController.includes("'Cache-Control','private, no-store, max-age=0, must-revalidate'")) {
  fail('incoming attachment response must remain private and non-cacheable');
}

const lifecycle = fs.readFileSync(path.join(root, 'plugins/mvm-hub/integrations/mail/interface-mail-v2-lifecycle-provider.php'), 'utf8');
if (!lifecycle.includes('extends Mail_V2_Provider')) fail('Mail V2 lifecycle contract must extend the strict Mail_V2_Provider boundary');
for (const method of ['save_draft(', 'create_folder(', 'rename_folder(', 'delete_folder(']) {
  if (!lifecycle.includes(method)) fail(`Mail V2 lifecycle contract must expose ${method}`);
}

const adapter = fs.readFileSync(path.join(root, 'plugins/mvm-hub/integrations/mail/class-v5-folder-scoped-mail-provider-adapter.php'), 'utf8');
if (!adapter.includes('implements Mail_V2_Lifecycle_Provider')) fail('strict V5 adapter must implement Mail_V2_Lifecycle_Provider');
if (!adapter.includes('folderScopedRead') || !adapter.includes('folderScopedMutations') || !adapter.includes('attachmentFetch')) {
  fail('strict V5 adapter must advertise explicit receive-boundary capabilities');
}
for (const required of ['folderLifecycle', 'draftLifecycle', 'save_draft(', 'create_folder(', 'rename_folder(', 'delete_folder(']) {
  if (!adapter.includes(required)) fail(`strict V5 adapter must expose lifecycle capability ${required}`);
}
if (!adapter.includes("preg_match( '/^([A-Za-z0-9_-]+)\\.(\\d+)$/")) fail('strict V5 adapter must require folder-scoped message ids');
if (adapter.includes('ctype_digit( $message_id ) ?')) fail('strict V5 adapter must not contain the legacy bare-UID fallback');

const syncProvider = fs.readFileSync(path.join(root, 'plugins/mvm-hub/integrations/mail/class-v5-imap-mail-sync-provider.php'), 'utf8');
for (const required of ['UIDVALIDITY', 'resetRequired', 'MAX_FOLDER_MESSAGES', "hash_hmac( 'sha256'", 'MAX_BATCH']) {
  if (!syncProvider.includes(required)) fail(`IMAP sync provider must contain ${required}`);
}
for (const forbiddenContent of ["'subject' =>", "'body' =>", "'from' =>", "'filename' =>", "'password' =>"]) {
  if (syncProvider.includes(forbiddenContent)) fail(`generic sync provider must not persist/project ${forbiddenContent}`);
}

const stateStore = fs.readFileSync(path.join(root, 'plugins/mvm-hub/integrations/mail/class-option-mail-sync-state-store.php'), 'utf8');
if (!stateStore.includes("add_option( $key, $normalized, '', false )")) fail('sync checkpoint options must be non-autoloaded');
if (!stateStore.includes("hash_hmac( 'sha256'")) fail('sync checkpoint option names must be opaque/hash-derived');

const attachment = fs.readFileSync(path.join(root, 'plugins/mvm-hub/integrations/mail/class-v5-imap-attachment-reader.php'), 'utf8');
for (const required of ['MAX_BYTES', 'FT_PEEK', "'publicMediaPromotion' => false", "'searchVisibility'", "'aiVisibility'"]) {
  if (!attachment.includes(required)) fail(`incoming attachment reader must contain ${required}`);
}
if (attachment.includes('wp_insert_attachment') || attachment.includes('media_handle_sideload')) {
  fail('incoming mail attachments must not be promoted into public WordPress media');
}

const stateMachine = fs.readFileSync(path.join(root, 'plugins/mvm-hub/modules/communications/class-v5-mail-sync-state-machine.php'), 'utf8');
for (const forbiddenContent of ['subject', 'body', 'fromAddress', 'filename', 'credential', 'token']) {
  if (stateMachine.includes(`'${forbiddenContent}' =>`)) fail(`pure sync state machine must not model ${forbiddenContent}`);
}

const factory = fs.readFileSync(path.join(root, 'plugins/mvm-hub/integrations/mail/class-v5-mail-provider-factory.php'), 'utf8');
for (const required of ['V5_Folder_Scoped_Mail_Provider_Adapter', 'Option_Mail_Sync_State_Store', 'V5_IMAP_Mail_Sync_Provider', 'V5_IMAP_Attachment_Reader']) {
  if (!factory.includes(required)) fail(`Mail V2 factory must compose ${required}`);
}
if (!factory.includes("'productionPromoted' => false") || !factory.includes("'requiresCutover'    => true")) {
  fail('Mail V2 factory readiness must not promote production');
}

console.log('OK: Mail V2 provider stack remains dormant except for the protected alpha5 incoming-attachment reader');
