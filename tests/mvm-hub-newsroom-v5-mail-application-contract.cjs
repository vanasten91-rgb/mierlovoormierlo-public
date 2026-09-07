const fs = require('fs');
const path = require('path');

const root = path.resolve(__dirname, '..');
const servicePath = path.join(root, 'plugins/mvm-hub/modules/communications/class-v5-mail-application-service.php');
const gatePath = path.join(root, 'plugins/mvm-hub/modules/communications/class-v5-mail-operation-gate.php');
const bootstrapPath = path.join(root, 'plugins/mvm-hub/mvm-hub.php');

function fail(message) {
  console.error(`FAIL: ${message}`);
  process.exit(1);
}

for (const file of [servicePath, gatePath]) {
  if (!fs.existsSync(file)) fail(`${path.relative(root, file)} is missing`);
  const source = fs.readFileSync(file, 'utf8');
  for (const forbidden of ['register_rest_route(', 'admin_post_', 'wp_ajax_', 'add_action(', 'add_filter(']) {
    if (source.includes(forbidden)) fail(`${path.basename(file)} must stay dormant and hook-free (${forbidden})`);
  }
}

const service = fs.readFileSync(servicePath, 'utf8');
const gate = fs.readFileSync(gatePath, 'utf8');
const bootstrap = fs.readFileSync(bootstrapPath, 'utf8');

for (const required of [
  'Mail_Delivery_Service $delivery',
  'Mail_Folder_Service $folders',
  'Mail_Draft_Service $drafts',
  "gate( 'receive_sync' )",
  "gate( 'deliver' )",
  'authorize_read_object',
  'fetch_attachment(',
  'scoped_message_id',
]) {
  if (!service.includes(required)) fail(`application service must contain ${required}`);
}

if (service.includes('$this->provider->deliver(')) {
  fail('application service must never bypass Mail_Delivery_Service for outbound delivery');
}
if (service.includes('$this->provider->create_folder(') || service.includes('$this->provider->move_message(')) {
  fail('application service must delegate folder/message mutations to security-aware services');
}

for (const required of ['folders_read', 'messages_read', 'message_read', 'attachment_read', 'receive_sync', 'deliver', 'canPromoteBidirectional']) {
  if (!gate.includes(required)) fail(`operation gate must model ${required}`);
}

for (const basename of ['class-v5-mail-operation-gate.php', 'class-v5-mail-application-service.php']) {
  if (bootstrap.includes(basename)) fail(`${basename} must not be loaded by live alpha5 bootstrap before cutover`);
}

console.log('OK: Mail V2 application orchestration contract passed');
