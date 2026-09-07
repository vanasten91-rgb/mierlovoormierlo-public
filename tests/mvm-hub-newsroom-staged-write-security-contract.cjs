'use strict';

const fs = require('node:fs');
const path = require('node:path');
const root = path.resolve(__dirname, '..');
const read = (relative) => fs.readFileSync(path.join(root, relative), 'utf8');

const gates = read('plugins/mvm-hub/core/class-runtime-gates.php');
const moduleFile = read('plugins/mvm-hub/modules/communications/class-communications-module.php');
const delegateController = read('plugins/mvm-hub/modules/communications/class-communications-write-rest-controller.php');
const mailController = read('plugins/mvm-hub/modules/communications/class-mail-write-rest-controller.php');
const internalController = read('plugins/mvm-hub/modules/communications/class-internal-message-write-rest-controller.php');
const factory = read('plugins/mvm-hub/modules/communications/class-communications-service-factory.php');
const validator = read('plugins/mvm-hub/modules/communications/class-mail-compose-validator.php');
const delivery = read('plugins/mvm-hub/modules/communications/class-mail-delivery-service.php');
const deliverySecurity = read('plugins/mvm-hub/integrations/mail/class-default-mail-delivery-security.php');
const storageRoot = read('plugins/mvm-hub/integrations/mail/class-private-storage-root.php');
const draftStore = read('plugins/mvm-hub/integrations/mail/class-encrypted-filesystem-draft-store.php');
const attachmentStore = read('plugins/mvm-hub/integrations/mail/class-filesystem-private-attachment-store.php');
const scanner = read('plugins/mvm-hub/integrations/mail/class-filter-attachment-scanner.php');
const imapSmtp = read('plugins/mvm-hub/integrations/mail/class-dedicated-imap-smtp-mail-provider.php');
const smtp = read('plugins/mvm-hub/integrations/mail/class-dedicated-smtp-mail-provider.php');
const legacy = read('plugins/mvm-hub/integrations/mail/class-legacy-readonly-mail-provider.php');

function fail(message) { console.error(`FAIL: ${message}`); process.exitCode = 1; }
function ok(condition, message) { if (!condition) fail(message); }

// Internal Communications writes remain explicitly gated. Mail is stricter:
// only the exact product-like staging host can open writes, and only with both
// the explicit staging constant and the signed cumulative `mail` release scope.
['MVM_HUB_ENABLE_COMMUNICATION_WRITES','MVM_HUB_ALLOW_PRODUCTION_COMMUNICATION_WRITES'].forEach((constant) => {
  ok(gates.includes(`'${constant}'`), `runtime gates missing ${constant}`);
});
const mailGate = gates.slice(gates.indexOf('public static function mail_writes_enabled'), gates.indexOf('private static function environment_gate'));
ok(mailGate.includes('verified_mail_staging_host()') && mailGate.includes('return false;'), 'Mail writes must fail closed unless the exact staging host is verified');
ok(mailGate.includes("'staging.mierlovoormierlo.nl'") && mailGate.includes("'MVM_HUB_ENABLE_STAGING_MAIL_WRITES'") && mailGate.includes("Release_Control::flag_enabled( 'mail_writes' )"), 'Mail writes must require exact staging host, explicit staging constant and signed mail scope');
ok(!mailGate.includes('wp_get_environment_type'), 'Mail write eligibility must not broaden to arbitrary non-production hosts');
ok(!mailGate.includes('MVM_HUB_ENABLE_EXTERNAL_MAIL_DELIVERY') && !mailGate.includes('MVM_HUB_ALLOW_PRODUCTION_MAIL_DELIVERY'), 'historical delivery constants must not reopen Mail');
ok(moduleFile.includes('Runtime_Gates::communications_writes_enabled()'), 'internal write controller must register conditionally');
ok(moduleFile.includes('new Internal_Message_Write_REST_Controller()'), 'module must register isolated internal-message writes');
ok(moduleFile.includes('Runtime_Gates::mail_writes_enabled()'), 'Mail route registrar must be guarded by the Mail runtime gate');
ok(moduleFile.includes('new Mail_Write_REST_Controller()'), 'module must register the dedicated Mail write registrar only behind the Mail gate');
ok(!moduleFile.includes('( new Communications_Write_REST_Controller() )->register_routes()'), 'combined delegate must never be registered directly');
ok(moduleFile.includes('class-communications-write-rest-controller.php') && moduleFile.includes('class-mail-write-rest-controller.php'), 'Mail registrar must load the hardened delegate and dedicated registrar');
ok(internalController.includes('Runtime_Gates::communications_writes_enabled()'), 'internal controller itself must fail closed');
ok(!internalController.includes('/communications/mail/'), 'internal controller must never expose Mail mutation routes');
ok(mailController.includes('Runtime_Gates::mail_writes_enabled()'), 'dedicated Mail registrar must independently fail closed');
ok(mailController.includes("'/communications/mail/send'"), 'dedicated Mail registrar must expose the audited send route only when promoted');
ok(mailController.includes('new Communications_Write_REST_Controller()'), 'dedicated Mail registrar must delegate to the existing hardened implementation');

// Internal HTTP mutations require capability plus REST nonce/session context.
ok(internalController.includes("get_header( 'X-WP-Nonce' )"), 'internal write controller must require REST nonce');
ok(internalController.includes("wp_verify_nonce( $nonce, 'wp_rest' )"), 'REST nonce must be verified');
['MESSAGES_SEND','MESSAGES_MANAGE_OWN'].forEach((cap) => ok(internalController.includes(`Capabilities::${cap}`), `internal write controller missing capability ${cap}`));

// The delegated Mail implementation stays security-hardened even though registration
// is now owned by the dedicated, staging-gated Mail registrar.
['MAIL_COMPOSE','MAIL_MANAGE_OWN_ATTACHMENTS','MAIL_MANAGE_FOLDERS','MAIL_MANAGE_MESSAGES','MAIL_SEND'].forEach((cap) => ok(delegateController.includes(`Capabilities::${cap}`), `Mail implementation missing capability ${cap}`));
ok(delegateController.includes("'/communications/mail/send'"), 'Mail send implementation must remain explicit/auditable');
ok(delegateController.includes('Runtime_Gates::mail_writes_enabled()'), 'delegated Mail implementation must retain its own fail-closed gate');

['from','sender','returnPath','return_path'].forEach((field) => ok(validator.includes(`'${field}'`), `compose validator must reject ${field}`));
ok(validator.includes('mvm_mail_sender_forbidden'), 'sender spoofing must fail closed');

ok(storageRoot.includes('MVM_HUB_PRIVATE_STORAGE_DIR'), 'private storage must require explicit server configuration');
ok(storageRoot.includes('mvm_private_storage_webroot'), 'private storage must reject WordPress webroot placement');
ok(storageRoot.includes('@chmod( $dir, 0700 )'), 'private storage directory must use restrictive permissions');
ok(/sodium_|SODIUM_/i.test(draftStore), 'draft storage must use authenticated encryption');
ok(draftStore.includes('expected_version') || draftStore.includes('expectedVersion') || draftStore.includes('$expected_version'), 'draft storage must implement optimistic concurrency');
ok(attachmentStore.includes("'scanStatus'") && attachmentStore.includes("'contentValidationStatus'") && attachmentStore.includes('authorize_for_send'), 'private attachment metadata/send authorization must require content validation and malware scan state');
ok(scanner.includes("'clean' === sanitize_key") && scanner.includes('mvm_mail_attachment_scanner_unavailable'), 'malware scanning must fail closed');

['MVM_HUB_IMAP_HOST','MVM_HUB_IMAP_USERNAME','MVM_HUB_SMTP_HOST','MVM_HUB_SMTP_USERNAME','MVM_HUB_MAIL_FROM_ADDRESS'].forEach((constant) => {
  ok(factory.includes(constant) || imapSmtp.includes(constant) || smtp.includes(constant), `mail stack missing server-side config ${constant}`);
});
ok(factory.includes('new Legacy_Readonly_Mail_Provider()'), 'unconfigured mail stack must retain legacy read-only fallback');
ok(legacy.includes("'send'         => false"), 'legacy fallback must report send disabled');
ok(!/\bwp_mail\s*\(|phpmailer_init/m.test(imapSmtp + smtp + delivery), 'dedicated mail stack must not bypass through wp_mail/phpmailer_init');

['authorize_session','authorize_step_up','authorize_mailbox_send','claim_idempotency','consume_rate_limit','authorize_for_send','finish_idempotency'].forEach((needle) => ok(delivery.includes(needle), `Mail delivery orchestration missing ${needle}`));
ok(/GET_LOCK|get_lock/i.test(deliverySecurity), 'delivery security must serialize rate/idempotency state');
ok(deliverySecurity.includes('recipient_count') || deliverySecurity.includes('recipientCount'), 'rate limiting must account for recipient volume');

if (!process.exitCode) console.log('PASS: isolated internal Communications writes + exact-host, signed staging Mail security gate');
