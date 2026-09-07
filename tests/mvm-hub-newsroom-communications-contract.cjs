'use strict';

const fs = require('node:fs');
const path = require('node:path');

const root = path.resolve(__dirname, '..');
const pluginDir = path.join(root, 'plugins', 'mvm-hub');

function read(relative) { return fs.readFileSync(path.join(root, relative), 'utf8'); }
function walk(dir) {
  return fs.readdirSync(dir, { withFileTypes: true }).flatMap((entry) => {
    const full = path.join(dir, entry.name);
    return entry.isDirectory() ? walk(full) : [full];
  });
}
function fail(message) { console.error(`FAIL: ${message}`); process.exitCode = 1; }
function ok(condition, message) { if (!condition) fail(message); }

const allPhp = walk(pluginDir).filter((file) => file.endsWith('.php')).map((file) => fs.readFileSync(file, 'utf8')).join('\n');
const communicationsPhp = [
  ...walk(path.join(pluginDir, 'modules', 'communications')),
  ...walk(path.join(pluginDir, 'integrations', 'mail')),
].filter((file) => file.endsWith('.php')).map((file) => fs.readFileSync(file, 'utf8')).join('\n');
const policy = read('plugins/mvm-hub/core/class-communications-policy.php');
const runtimeGates = read('plugins/mvm-hub/core/class-runtime-gates.php');
const capabilities = read('plugins/mvm-hub/core/class-capabilities.php');
const provider = read('plugins/mvm-hub/integrations/mail/class-legacy-readonly-mail-provider.php');
const providerInterface = read('plugins/mvm-hub/integrations/mail/interface-mail-provider.php');
const draftStore = read('plugins/mvm-hub/integrations/mail/interface-draft-store.php');
const attachmentStore = read('plugins/mvm-hub/integrations/mail/interface-private-attachment-store.php');
const replyBuilder = read('plugins/mvm-hub/modules/communications/class-mail-reply-builder.php');
const deliveryGuard = read('plugins/mvm-hub/modules/communications/class-mail-delivery-guard.php');
const readController = read('plugins/mvm-hub/modules/communications/class-mail-read-rest-controller.php');
const dormantWriteController = read('plugins/mvm-hub/modules/communications/class-communications-write-rest-controller.php');
const mailWriteController = read('plugins/mvm-hub/modules/communications/class-mail-write-rest-controller.php');
const internalWriteController = read('plugins/mvm-hub/modules/communications/class-internal-message-write-rest-controller.php');
const communicationsModule = read('plugins/mvm-hub/modules/communications/class-communications-module.php');
const kernel = read('plugins/mvm-hub/core/class-kernel.php');

// Delivery primitives may remain for an explicitly controlled release, but production must stay fail-closed.
ok(providerInterface.includes('deliver('), 'mail provider contract may retain delivery primitive for an explicit release');
ok(capabilities.includes("'mvm_mail_send'"), 'send capability definition must remain explicit rather than implicit');
ok(replyBuilder.includes("'mode'              => 'reply'"), 'reply builder must create an explicit reply draft');
ok(replyBuilder.includes("'inReplyTo'") && replyBuilder.includes("'references'") && replyBuilder.includes("'originalMessageId'"), 'reply drafts must preserve threading/source context');
ok(replyBuilder.includes('sanitize_composer_html') && replyBuilder.includes('is_email'), 'reply drafts must sanitize HTML and validate recipients');

ok(draftStore.includes('update_own') && draftStore.includes('delete_own'), 'draft store must retain owner-scoped update/delete contracts');
ok(attachmentStore.includes('store_for_draft') && attachmentStore.includes('delete_own') && attachmentStore.includes('authorize_download'), 'private attachment storage must remain owner scoped');
ok(!/wp_get_attachment_url|wp_upload_dir/m.test(attachmentStore), 'private mail attachment contract must not depend on public Media Library URLs');

// Current invariant: only the exact product-like staging host may rehearse Mail,
// and only with both the explicit staging opt-in and signed mail scope. No
// WP_ENVIRONMENT_TYPE value is allowed to broaden this host boundary.
ok(policy.includes('return Runtime_Gates::mail_writes_enabled();'), 'external delivery policy must delegate to the fail-closed runtime gate');
ok(runtimeGates.includes("'MVM_HUB_ENABLE_COMMUNICATION_WRITES'") && runtimeGates.includes("'MVM_HUB_ALLOW_PRODUCTION_COMMUNICATION_WRITES'"), 'internal Communications writes must remain explicitly gated');
const mailGate = runtimeGates.slice(runtimeGates.indexOf('public static function mail_writes_enabled'), runtimeGates.indexOf('private static function environment_gate'));
ok(mailGate.includes('if ( ! self::verified_mail_staging_host() )') && mailGate.includes('return false;'), 'MvM Mail must fail closed unless the exact staging-host rehearsal pin matches');
ok(mailGate.includes("return 'staging.mierlovoormierlo.nl' === $host;"), 'Mail staging exception must be pinned to the exact staging host');
ok(!mailGate.includes('wp_get_environment_type'), 'Mail write eligibility must not broaden to arbitrary non-production hosts');
ok(mailGate.includes("MVM_HUB_ENABLE_STAGING_MAIL_WRITES") && mailGate.includes("true !== constant( 'MVM_HUB_ENABLE_STAGING_MAIL_WRITES' )"), 'staging Mail writes must require an explicit staging-only opt-in');
ok(mailGate.includes("Release_Control::flag_enabled( 'mail_writes' )"), 'staging Mail writes must also require the signed mail release scope');
ok(!mailGate.includes('MVM_HUB_ALLOW_PRODUCTION_MAIL_WRITES') && !mailGate.includes('MVM_HUB_ALLOW_PRODUCTION_EXTERNAL_MAIL_DELIVERY'), 'Mail gate must not expose a production override constant');
ok(runtimeGates.includes("if ( ! defined( $enable_constant ) || true !== constant( $enable_constant ) )"), 'remaining environment runtime gates must fail closed');

ok(communicationsModule.includes('new Mail_Read_REST_Controller()'), 'communications module must expose Mail reads');
ok(communicationsModule.includes('new Internal_Message_Write_REST_Controller()'), 'internal staff messages must use their isolated write controller');
ok(communicationsModule.includes("'modules/communications/class-communications-write-rest-controller.php'"), 'Mail implementation callbacks must be loaded for the dedicated registrar');
ok(communicationsModule.includes("'modules/communications/class-mail-write-rest-controller.php'"), 'dedicated Mail write registrar must be loaded');
ok(communicationsModule.includes('new Mail_Write_REST_Controller()'), 'dedicated Mail write registrar must be runtime reachable');
ok(communicationsModule.includes('if ( Runtime_Gates::mail_writes_enabled() )'), 'Mail write registrar must be behind the Mail runtime gate');
ok(!communicationsModule.includes('new Communications_Write_REST_Controller()'), 'combined implementation controller must never be registered directly');
ok(internalWriteController.includes('Runtime_Gates::communications_writes_enabled()'), 'internal message writes must remain gated');
ok(internalWriteController.includes("'/communications/messages'"), 'internal message write controller must expose staff-message writes');
ok(!internalWriteController.includes('/communications/mail/'), 'internal message write controller must not own Mail routes');

ok(mailWriteController.includes('Runtime_Gates::mail_writes_enabled()'), 'dedicated Mail registrar must fail closed when the Mail gate is shut');
ok(mailWriteController.includes("'/communications/mail/send'"), 'dedicated Mail registrar must expose the send route when promoted');
ok(mailWriteController.includes("'/communications/mail/drafts'"), 'dedicated Mail registrar must expose draft routes when promoted');
ok(mailWriteController.includes("'/communications/mail/folders'"), 'dedicated Mail registrar must expose folder mutation routes when promoted');
ok(!mailWriteController.includes("'/communications/messages'"), 'dedicated Mail registrar must not own internal staff-message routes');
ok(mailWriteController.includes('Communications_Write_REST_Controller $delegate'), 'dedicated Mail registrar must reuse the existing hardened implementation callbacks');

// Implementation source remains explicit and auditable, but its route registrar is never called directly.
ok(dormantWriteController.includes("'/communications/mail/send'"), 'Mail transport implementation should remain explicit for auditability');
ok(dormantWriteController.includes('Capabilities::MAIL_SEND'), 'Mail send implementation must retain dedicated capability check');
ok(dormantWriteController.includes('Runtime_Gates::mail_writes_enabled()'), 'Mail send implementation must re-check the Mail gate');

ok(deliveryGuard.includes('Capabilities::can_send_mail()') && deliveryGuard.includes('external_mail_delivery_enabled()'), 'delivery guard must remain fail-closed behind capability and policy');
ok(deliveryGuard.includes('valid_idempotency_key') && deliveryGuard.includes("'rateLimit'") && deliveryGuard.includes("'auditEvent'"), 'delivery guard must retain idempotency/rate-limit/audit requirements');
ok(!/\bwp_mail\s*\(/m.test(allPhp), 'central Hub must not use wp_mail as a hidden transport');
ok(!/phpmailer_init/m.test(allPhp), 'central Hub must not configure SMTP through phpmailer_init');
ok(!/wp_ajax_/mi.test(communicationsPhp), 'communications/mail code must not expose AJAX handlers');
ok(!/mvm_hub_mail_ajax_send|wp_ajax_mvm_hub_mail_send/mi.test(allPhp), 'legacy hidden mail send AJAX handlers must never return');

ok(provider.includes("'/mvm/v1/editorial/mail/messages'") && provider.includes('rest_do_request'), 'legacy provider must reuse existing read-only mailbox owner in-process');
ok(provider.includes("'mvm_mail_read_only'") && provider.includes("'send'         => false") && provider.includes("'drafts'       => false"), 'legacy provider must remain explicitly read-only');
ok(readController.includes("'/communications/mail/messages'"), 'central Hub must expose a mailbox list facade');
ok(readController.includes("'/communications/mail/messages/(?P<id>[A-Za-z0-9._:-]+)'"), 'central Hub must expose a bounded opaque-id message detail facade');
ok(readController.includes('strlen($v)<=260') && readController.includes("preg_match('/^[A-Za-z0-9._:-]+$/',$v)"), 'message ids must be bounded and allowlisted');
ok(readController.includes('Capabilities::can_read_mail()') && readController.includes('\\WP_REST_Server::READABLE'), 'Mail read routes must require read capability and remain GET-only');
ok(kernel.includes('Communications_Module::register()'), 'central kernel must register communications module');

ok(policy.includes('incoming_message_allowed_html') && policy.includes('sanitize_incoming_html'), 'incoming mail must have a dedicated sanitizer');
ok(!/['"]img['"]\s*=>/m.test(policy), 'incoming HTML allow-list must not permit raw img tags by default');
ok(/remote_images_allowed_by_default\(\): bool\s*\{\s*return false;/m.test(policy), 'remote images must be blocked by default');

if (!process.exitCode) console.log('PASS: MvM Hub production-closed Mail / exact-staging rehearsal / internal Communications contract');
