'use strict';

const fs = require('node:fs');
const path = require('node:path');

const root = path.resolve(__dirname, '..');
const read = (relative) => fs.readFileSync(path.join(root, relative), 'utf8');

const service = read('plugins/mvm-hub/modules/communications/class-internal-message-service.php');
const projector = read('plugins/mvm-hub/modules/communications/class-internal-message-projector.php');
const provider = read('plugins/mvm-hub/integrations/peepso/interface-internal-message-provider.php');
const actionSecurity = read('plugins/mvm-hub/integrations/mail/interface-communications-action-security.php');
const capabilities = read('plugins/mvm-hub/core/class-capabilities.php');
const bootstrap = read('plugins/mvm-hub/mvm-hub.php');

function fail(message) {
  console.error(`FAIL: ${message}`);
  process.exitCode = 1;
}
function ok(condition, message) {
  if (!condition) fail(message);
}

// Every read/write remains participant scoped at the provider boundary.
ok(provider.includes('list_threads_for_user'), 'internal message provider must list only current-user threads');
ok(provider.includes('get_thread_for_user'), 'internal message provider must fetch only current-user threads');
ok(service.includes('get_thread_for_user( $user_id, $thread_id )'), 'service must verify participant access before thread mutation');
ok(!/role__in|mvm_(?:sysop|teamleider|editor|journalist|redacteur|fotograaf|moderator|vertaler)/m.test(service + projector), 'internal message privacy must not depend on editorial role names');

// Provider payloads are explicitly projected instead of proxied raw.
ok(service.includes('Internal_Message_Projector::thread_list'), 'thread lists must pass through the privacy projector');
ok(service.includes('Internal_Message_Projector::thread_detail'), 'thread details/mutations must pass through the privacy projector');
ok(!/return\s+\$this->provider->(?:list_threads_for_user|get_thread_for_user|create_thread|reply)/m.test(service), 'service must not return raw provider payloads');
['id', 'title', 'participants', 'unreadCount', 'updatedAtUtc', 'archived'].forEach((field) => {
  ok(projector.includes(`'${field}'`), `thread projection missing ${field}`);
});
['authorUserId', 'authorName', 'htmlBody', 'createdAtUtc', 'isOwn'].forEach((field) => {
  ok(projector.includes(`'${field}'`), `message projection missing ${field}`);
});
['user_email', 'user_login', 'email', 'phone', 'address', 'storage_key', 'password'].forEach((field) => {
  ok(!new RegExp(`['\"]${field}['\"]\\s*=>`, 'i').test(projector), `projector must not emit ${field}`);
  ok(!new RegExp(`->${field}\\b`, 'i').test(projector), `projector must not read ${field}`);
});
ok(projector.includes('sanitize_composer_html'), 'internal message bodies must be sanitized on output');
ok(projector.includes('array_slice( $participants, 0, 25 )'), 'participant projection must be bounded');

// Writes require dedicated capability + active session; HTTP CSRF remains future controller responsibility.
ok(capabilities.includes("'mvm_messages_send'"), 'internal send capability missing');
ok(capabilities.includes("'mvm_messages_manage_own'"), 'internal own-message management capability missing');
ok(service.includes('Capabilities::MESSAGES_SEND'), 'create/reply must require send capability');
ok(service.includes('Capabilities::MESSAGES_MANAGE_OWN'), 'read/archive/delete mutations must require own-management capability');
ok(service.includes('security->authorize_session( $user_id )'), 'internal message mutations must verify active session');
ok(actionSecurity.includes('authorize_session'), 'communications action security must expose session verification');
ok(service.includes("'read'    => $this->provider->mark_read"), 'read mutation must use explicit action mapping');
ok(service.includes("'archive' => $this->provider->archive_for_user"), 'archive mutation must use explicit action mapping');
ok(service.includes("'delete'  => $this->provider->delete_for_user"), 'delete mutation must use explicit action mapping');
ok(!/->\{\$method\}/m.test(service), 'dynamic provider method dispatch must not be used');

// Content and participant inputs are bounded and audits remain metadata-only.
ok(service.includes('MAX_PARTICIPANTS = 25'), 'thread participant count must be capped');
ok(service.includes('MAX_BODY_BYTES   = 50000'), 'internal message body size must be capped');
ok(service.includes('sanitize_composer_html'), 'internal message writes must sanitize rich text');
ok(service.includes("array( 'participant_count' => count( $participants ) )"), 'thread creation audit may retain participant count only');
const auditLines = service.split('\n').filter((line) => line.includes('audit->record') || line.includes('participant_count'));
ok(!auditLines.some((line) => /\bbody\b|email|participant_user_ids/i.test(line)), 'internal message audit calls must not include bodies/emails/recipient IDs');
ok(service.includes('safe_ref'), 'thread audit identifiers must be pseudonymized');

// Service is dormant: no write REST/AJAX routes are added yet.
ok(bootstrap.includes('class-internal-message-projector.php'), 'bootstrap must load internal message projector');
ok(bootstrap.includes('class-internal-message-service.php'), 'bootstrap must load internal message service');
ok(!/register_rest_route|wp_ajax_/m.test(service + projector), 'internal message service/projector must not expose HTTP mutation routes yet');

if (!process.exitCode) {
  console.log('PASS: MvM Hub internal messaging participant privacy/session contract');
}
