'use strict';

const fs = require('node:fs');
const path = require('node:path');

const root = path.resolve(__dirname, '..');
const read = (relative) => fs.readFileSync(path.join(root, relative), 'utf8');

const validator = read('plugins/mvm-hub/modules/communications/class-mail-compose-validator.php');
const drafts = read('plugins/mvm-hub/modules/communications/class-mail-draft-service.php');
const draftStore = read('plugins/mvm-hub/integrations/mail/interface-draft-store.php');
const upload = read('plugins/mvm-hub/modules/communications/class-mail-attachment-service.php');
const normalizer = read('plugins/mvm-hub/integrations/mail/interface-attachment-normalizer.php');
const scanner = read('plugins/mvm-hub/integrations/mail/interface-attachment-scanner.php');
const folders = read('plugins/mvm-hub/modules/communications/class-mail-folder-service.php');
const mailboxAccess = read('plugins/mvm-hub/integrations/mail/interface-mailbox-access.php');
const actionSecurity = read('plugins/mvm-hub/integrations/mail/interface-communications-action-security.php');
const query = read('plugins/mvm-hub/modules/communications/class-mail-query.php');
const capabilities = read('plugins/mvm-hub/core/class-capabilities.php');
const bootstrap = read('plugins/mvm-hub/mvm-hub.php');

function fail(message) {
  console.error(`FAIL: ${message}`);
  process.exitCode = 1;
}
function ok(condition, message) {
  if (!condition) fail(message);
}

// Autosave drafts are allowed to be incomplete, never unsanitized.
ok(validator.includes('validate_draft'), 'compose validator must expose incomplete-draft validation');
ok(validator.includes('normalize( $input, false )'), 'draft validation must not require recipients while autosaving');
ok(validator.includes('sanitize_composer_html'), 'draft HTML must use the same sanitizer as delivery');
ok(draftStore.includes('expected_version'), 'draft storage must require optimistic concurrency');
ok(drafts.includes('validate_draft'), 'draft service must validate create/update content');
ok(drafts.includes('update_own( $user_id, $draft_id, $draft, $expected_version )'), 'draft update must be owner-scoped and versioned');
ok(drafts.includes('get_own( $user_id, $draft_id )'), 'draft detail must be owner-scoped');
ok(drafts.includes('project_attachments'), 'draft detail must project attachment metadata explicitly');
ok(!/return\s+\$this->drafts->list_own/m.test(drafts), 'draft list must not proxy raw store payloads');
ok(drafts.includes("'subject'      => mb_substr"), 'draft list may expose only a sanitized subject metadata field');
ok(drafts.includes("'items'   => array_values( $items )"), 'draft list must explicitly project list items');
ok(drafts.includes('safe_ref'), 'draft mutation audit must pseudonymize object references');

// Uploads trust the server-side normalizer, not browser metadata.
ok(normalizer.includes('derive MIME/type/size from the actual private temporary'), 'attachment normalizer must require server-derived file metadata');
ok(normalizer.includes('EXIF/GPS'), 'image normalizer contract must remove sensitive image metadata');
const normalizePos = upload.indexOf('normalizer->normalize');
const policyPos = upload.indexOf('attachment_type_allowed');
const contentPolicyPos = upload.indexOf('attachment_content_allowed');
const scanPos = upload.indexOf('scanner->scan');
const storePos = upload.indexOf('store_for_draft');
ok(normalizePos >= 0 && normalizePos < policyPos && policyPos < contentPolicyPos && contentPolicyPos < scanPos && scanPos < storePos, 'attachment pipeline order must be normalize -> extension/MIME allowlist -> content validation -> scan -> private store');
ok(upload.includes("'clean' !=="), 'attachments must fail closed unless malware scan returns clean');
ok(upload.includes("'contentValidationStatus'] = 'verified'"), 'attachments must persist successful content validation before private storage');
ok(upload.includes('discard_private_handle'), 'rejected or failed attachment ingest must remove its private temporary file');
ok(upload.includes('drafts->get_own'), 'attachment upload must verify ownership of the destination draft');
ok(upload.includes('MAIL_MANAGE_OWN_ATTACHMENTS'), 'attachment upload must require dedicated attachment capability');
ok(scanner.includes('status:string'), 'scanner contract must return an explicit status');

// Folder/message mutations use separate capability, object ACL and step-up policy.
ok(capabilities.includes("'mvm_mail_manage_messages'"), 'message-state mutation capability missing');
ok(mailboxAccess.includes('authorize_manage_folders') && mailboxAccess.includes('authorize_message'), 'mailbox access contract must enforce folder/message object ACLs');
ok(actionSecurity.includes('authorize_session') && actionSecurity.includes('authorize_step_up'), 'communications mutations must have session and step-up security boundary');
ok(folders.includes('MAIL_MANAGE_FOLDERS'), 'folder mutation must require folder-management capability');
ok(folders.includes('MAIL_MANAGE_MESSAGES'), 'message mutation must require separate message-management capability');
ok(folders.includes('authorize_manage_folders'), 'folder mutation must enforce mailbox object access');
ok(folders.includes('authorize_message'), 'message mutation must enforce message object access');
ok(folders.includes("requires_step_up( 'mail_folder_delete' )"), 'folder deletion must require central step-up policy');
ok(folders.includes("authorize_step_up( $context['userId'], 'mail_folder_delete' )"), 'folder deletion must verify step-up state');
ok(folders.includes('protected_folder'), 'system/special-use folder deletion/rename must be blocked');
['inbox', 'sent', 'drafts', 'trash', 'junk', 'spam'].forEach((folder) => ok(folders.includes(`'${folder}'`), `protected folder list missing ${folder}`));

// Search/sort/filter parameters are allowlisted and bounded before providers see them.
['date', 'sender', 'subject', 'size', 'status'].forEach((sort) => ok(query.includes(`'${sort}'`), `mail query sort allowlist missing ${sort}`));
['read', 'unread', 'flagged', 'unflagged'].forEach((state) => ok(query.includes(`'${state}'`), `mail query state allowlist missing ${state}`));
ok(query.includes('min( 100') && query.includes('min( 50'), 'mail query pagination must be bounded');
ok(query.includes('mb_substr( $search, 0, 120 )'), 'mail search term must be length bounded');
ok(query.includes('strlen( $value ) <= 256'), 'provider cursor must be bounded and opaque');

// Services are loaded for future implementation, not exposed as mutation routes yet.
[
  'interface-attachment-normalizer.php',
  'interface-mailbox-access.php',
  'interface-communications-action-security.php',
  'class-mail-draft-service.php',
  'class-mail-attachment-service.php',
  'class-mail-folder-service.php',
  'class-mail-query.php',
].forEach((file) => ok(bootstrap.includes(file), `bootstrap missing dormant client service ${file}`));

if (!process.exitCode) {
  console.log('PASS: MvM Hub full mail client draft upload folder query service contract');
}
