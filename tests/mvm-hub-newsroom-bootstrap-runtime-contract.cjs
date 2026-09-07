'use strict';

const fs = require('node:fs');
const path = require('node:path');

const root = path.resolve(__dirname, '..');
const bootstrap = fs.readFileSync(path.join(root, 'plugins/mvm-hub/mvm-hub.php'), 'utf8');

function fail(message) {
  console.error(`FAIL: ${message}`);
  process.exitCode = 1;
}
function ok(condition, message) {
  if (!condition) fail(message);
}

const requiredInOrder = [
  'integrations/mail/interface-mail-provider.php',
  'integrations/mail/interface-mailbox-access.php',
  'integrations/mail/class-legacy-readonly-mail-provider.php',
  'integrations/mail/class-legacy-editorial-mailbox-access.php',
  'modules/communications/class-mail-query.php',
  'modules/communications/class-mail-read-projector.php',
  'modules/communications/class-mail-read-service.php',
  'modules/communications/class-mail-read-rest-controller.php',
  'modules/communications/class-communications-module.php',
  'core/class-kernel.php',
];

let previous = -1;
for (const relative of requiredInOrder) {
  const needle = `require_once MVM_HUB_DIR . '${relative}';`;
  const current = bootstrap.indexOf(needle);
  ok(current >= 0, `bootstrap missing runtime dependency ${relative}`);
  if (current >= 0) {
    ok(current > previous, `bootstrap load order is unsafe around ${relative}`);
    previous = current;
  }
}

const mailController = fs.readFileSync(path.join(root, 'plugins/mvm-hub/modules/communications/class-mail-read-rest-controller.php'), 'utf8');
const factory = fs.readFileSync(path.join(root, 'plugins/mvm-hub/modules/communications/class-communications-service-factory.php'), 'utf8');
const service = fs.readFileSync(path.join(root, 'plugins/mvm-hub/modules/communications/class-mail-read-service.php'), 'utf8');

ok(mailController.includes('Communications_Service_Factory::mail_read_service()'), 'mail controller must delegate provider/ACL composition to the central factory');
ok(!mailController.includes('new Legacy_Readonly_Mail_Provider('), 'mail controller must not choose a concrete provider itself');
ok(!mailController.includes('new Legacy_Editorial_Mailbox_Access('), 'mail controller must not choose a concrete mailbox ACL itself');

ok(factory.includes('new Mail_Read_Service( self::mail_provider(), self::mailbox_access() )'), 'central factory must compose mail reads through Mail_Read_Service');
ok(factory.includes('new Legacy_Readonly_Mail_Provider()'), 'central factory must retain a fail-closed legacy read fallback');
ok(factory.includes('self::imap_configured()'), 'central factory must only select IMAP when server-side configuration is complete');
ok(factory.includes('new Configured_Mailbox_Access()'), 'central factory must enforce configured mailbox object ACL');
ok(factory.includes('instanceof Mail_Provider'), 'provider filter replacements must be type checked');
ok(factory.includes('instanceof Mailbox_Access'), 'mailbox ACL filter replacements must be type checked');

ok(service.includes('Mail_Read_Projector::'), 'mail read service must privacy-project provider output');
ok(service.includes('authorize_read'), 'mail read service must enforce mailbox ACL');
ok(service.includes('authorize_message'), 'mail detail service must enforce message ACL');

if (!process.exitCode) {
  console.log('PASS: MvM Hub bootstrap/runtime dependency contract');
}
