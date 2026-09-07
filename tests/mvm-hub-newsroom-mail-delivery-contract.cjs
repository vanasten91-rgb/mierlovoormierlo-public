'use strict';

const fs = require('node:fs');
const path = require('node:path');
const root = path.resolve(__dirname, '..');
const read = (relative) => fs.readFileSync(path.join(root, relative), 'utf8');
const pluginDir = path.join(root, 'plugins', 'mvm-hub');
const walk = (dir) => fs.readdirSync(dir, { withFileTypes: true }).flatMap((entry) => {
  const full = path.join(dir, entry.name);
  return entry.isDirectory() ? walk(full) : [full];
});

const allPhp = walk(pluginDir).filter((file) => file.endsWith('.php')).map((file) => fs.readFileSync(file, 'utf8')).join('\n');
const mailPhp = [
  ...walk(path.join(pluginDir, 'modules', 'communications')),
  ...walk(path.join(pluginDir, 'integrations', 'mail')),
].filter((file) => file.endsWith('.php')).map((file) => fs.readFileSync(file, 'utf8')).join('\n');
const validator = read('plugins/mvm-hub/modules/communications/class-mail-compose-validator.php');
const service = read('plugins/mvm-hub/modules/communications/class-mail-delivery-service.php');
const guard = read('plugins/mvm-hub/modules/communications/class-mail-delivery-guard.php');
const security = read('plugins/mvm-hub/integrations/mail/interface-mail-delivery-security.php');
const defaultSecurity = read('plugins/mvm-hub/integrations/mail/class-default-mail-delivery-security.php');
const attachments = read('plugins/mvm-hub/integrations/mail/interface-private-attachment-store.php');
const attachmentStore = read('plugins/mvm-hub/integrations/mail/class-filesystem-private-attachment-store.php');
const policy = read('plugins/mvm-hub/core/class-communications-policy.php');
const gates = read('plugins/mvm-hub/core/class-runtime-gates.php');
const privacy = read('plugins/mvm-hub/core/class-privacy.php');
const communicationsModule = read('plugins/mvm-hub/modules/communications/class-communications-module.php');
const delegateWriteController = read('plugins/mvm-hub/modules/communications/class-communications-write-rest-controller.php');
const mailWriteController = read('plugins/mvm-hub/modules/communications/class-mail-write-rest-controller.php');
const internalWriteController = read('plugins/mvm-hub/modules/communications/class-internal-message-write-rest-controller.php');
const smtpProvider = read('plugins/mvm-hub/integrations/mail/class-dedicated-smtp-mail-provider.php');

function fail(message) { console.error(`FAIL: ${message}`); process.exitCode = 1; }
function ok(condition, message) { if (!condition) fail(message); }

// Compose/delivery remains security hardened even during a controlled staging rehearsal.
['from', 'sender', 'returnPath', 'return_path'].forEach((field) => ok(validator.includes(`'${field}'`), `compose validator must reject client-controlled ${field}`));
ok(validator.includes('mvm_mail_sender_forbidden'), 'sender spoofing must fail closed');
ok(validator.includes('str_contains( $candidate, "\\r" )') && validator.includes('str_contains( $candidate, "\\n" )'), 'recipient validation must reject CR/LF injection');
ok(validator.includes('sanitize_email') && validator.includes('is_email'), 'recipients must be normalized and validated');
ok(validator.includes('MAX_RECIPIENTS') && validator.includes('MAX_ATTACHMENTS'), 'compose payload must remain bounded');
ok(validator.includes('sanitize_composer_html') && validator.includes('message_id_valid'), 'outgoing content/threading data must remain sanitized');
ok(!/['\"]from['\"]\s*=>\s*\$message\[['\"]from['\"]\]|['\"]returnPath['\"]\s*=>/m.test(service), 'transport orchestration must not accept client-controlled sender headers');

['Mail_Delivery_Guard::authorize','authorize_session','authorize_step_up','authorize_mailbox_send','claim_idempotency','consume_rate_limit',"requires_step_up( 'mail_send' )","provider_capabilities['send']",'authorize_for_send','provider->deliver','finish_idempotency'].forEach((needle) => ok(service.includes(needle), `mail delivery service missing gate ${needle}`));
ok(security.includes('authorize_session') && security.includes('authorize_step_up') && security.includes('authorize_mailbox_send'), 'delivery security contract must retain session/step-up/mailbox ACL checks');
ok(security.includes('claim_idempotency') && security.includes('consume_rate_limit'), 'delivery security contract must retain idempotency/rate limiting');
ok(defaultSecurity.includes('GET_LOCK') || defaultSecurity.includes('get_lock'), 'delivery security must serialize state');
ok(attachments.includes('authorize_for_send'), 'attachment store must authorize ownership/scan state');
ok(policy.includes('attachment_content_allowed'), 'attachment policy must validate file signatures/content after extension and MIME');
ok(attachmentStore.includes('contentValidationStatus'), 'private attachment authorization must retain the content-validation gate');

const auditBlock = service.slice(service.indexOf('private function audit'));
['subject','to','cc','bcc','html_body','text_body','attachment_name','filename'].forEach((field) => ok(!new RegExp(`['\"]${field}['\"]\\s*=>`, 'i').test(auditBlock), `mail delivery audit must not receive ${field}`));
ok(auditBlock.includes('recipient_count') && auditBlock.includes('attachment_count') && auditBlock.includes('wp_hash') && auditBlock.includes('Privacy::redact_for_audit'), 'delivery audit must stay metadata-only and pseudonymized');
ok(privacy.includes("'subject'           => self::CONFIDENTIAL_DATA"), 'subject must remain confidential');

// Runtime cutover contract: only the exact product-like staging host can ever
// open Mail writes. It still needs both the explicit opt-in constant and the
// signed cumulative mail scope; WP_ENVIRONMENT_TYPE cannot broaden this boundary.
ok(policy.includes('return Runtime_Gates::mail_writes_enabled();'), 'delivery policy must delegate to Mail runtime gate');
const mailGate = gates.slice(gates.indexOf('public static function mail_writes_enabled'), gates.indexOf('private static function environment_gate'));
ok(mailGate.includes('if ( ! self::verified_mail_staging_host() )') && mailGate.includes('return false;'), 'Mail runtime gate must remain hard read-only unless the exact staging clone is verified');
ok(mailGate.includes("return 'staging.mierlovoormierlo.nl' === $host;"), 'Mail rehearsal host must be pinned to the exact staging hostname');
ok(!mailGate.includes('wp_get_environment_type'), 'Mail rehearsal eligibility must not broaden to arbitrary non-production hosts');
ok(mailGate.includes('MVM_HUB_ENABLE_STAGING_MAIL_WRITES') && mailGate.includes("true !== constant( 'MVM_HUB_ENABLE_STAGING_MAIL_WRITES' )"), 'staging Mail must require the explicit literal-true staging opt-in constant');
ok(mailGate.includes("Release_Control::flag_enabled( 'mail_writes' )"), 'staging Mail must also require signed mail release scope');
ok(!mailGate.includes('MVM_HUB_ALLOW_PRODUCTION_MAIL_WRITES') && !mailGate.includes('MVM_HUB_ENABLE_EXTERNAL_MAIL_DELIVERY'), 'Mail must have no production/config-only bypass');
ok(guard.includes('external_mail_delivery_enabled()'), 'delivery guard must still enforce central policy');

// Read and internal-message routes remain independent. Mail mutations are registered
// by a dedicated registrar only after the double gate opens. The older combined
// controller may be loaded solely as a method delegate; it must never register routes directly.
ok(communicationsModule.includes('new Mail_Read_REST_Controller()'), 'Mail read controller must remain registered');
ok(communicationsModule.includes('new Internal_Message_Write_REST_Controller()'), 'isolated internal-message writes must remain registered behind Communications gate');
ok(communicationsModule.includes('Runtime_Gates::mail_writes_enabled()') && communicationsModule.includes('new Mail_Write_REST_Controller()'), 'dedicated Mail write registrar must be conditional on the Mail runtime gate');
ok(!communicationsModule.includes('new Communications_Write_REST_Controller()'), 'combined controller must never be directly registered by Communications_Module');
ok(communicationsModule.includes('class-mail-write-rest-controller.php'), 'dedicated Mail write registrar must be loaded');
ok(!internalWriteController.includes('/communications/mail/'), 'internal write controller must not expose mailbox mutation routes');
ok(mailWriteController.includes('Runtime_Gates::mail_writes_enabled()'), 'dedicated Mail registrar must re-check the Mail gate');
ok(mailWriteController.includes("'/communications/mail/send'") && mailWriteController.includes("'can_send_mail'"), 'dedicated Mail registrar must expose the capability-guarded send route');
ok(mailWriteController.includes('new Communications_Write_REST_Controller()'), 'dedicated registrar must reuse the hardened delivery implementation rather than duplicate it');
ok(delegateWriteController.includes("'/communications/mail/send'") && delegateWriteController.includes('Capabilities::MAIL_SEND'), 'delegated delivery implementation must retain explicit send capability enforcement');

ok(smtpProvider.includes('MVM_HUB_SMTP_HOST') && smtpProvider.includes('MVM_HUB_SMTP_USERNAME') && smtpProvider.includes('MVM_HUB_MAIL_FROM_ADDRESS'), 'SMTP provider must derive config server-side');
ok(!/\$message\[['\"]from['\"]\]/m.test(smtpProvider), 'SMTP provider must not derive From from browser payload');
ok(!/\bwp_mail\s*\(|phpmailer_init/m.test(allPhp), 'mail code must not bypass dedicated transport through wp_mail/phpmailer hooks');
ok(!/wp_ajax_/mi.test(mailPhp), 'mail/communications code must not expose AJAX handlers');
ok(!/mvm_hub_mail_ajax_send|wp_ajax_mvm_hub_mail_send/mi.test(allPhp), 'legacy hidden mail send AJAX path must never return');

if (!process.exitCode) console.log('PASS: MvM Hub Mail delivery security + production-closed / exact-staging signed-write runtime contract');
