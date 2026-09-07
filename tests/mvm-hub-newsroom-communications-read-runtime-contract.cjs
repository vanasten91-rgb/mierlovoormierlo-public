'use strict';

const fs = require('node:fs');
const path = require('node:path');
const root = path.resolve(__dirname, '..');
const read = (relative) => fs.readFileSync(path.join(root, relative), 'utf8');

const provider = read('plugins/mvm-hub/integrations/peepso/class-legacy-readonly-internal-message-provider.php');
const service = read('plugins/mvm-hub/modules/communications/class-internal-message-read-service.php');
const controller = read('plugins/mvm-hub/modules/communications/class-internal-message-read-rest-controller.php');
const preview = read('plugins/mvm-hub/modules/communications/class-communications-preview-renderer.php');
const communicationsModule = read('plugins/mvm-hub/modules/communications/class-communications-module.php');
const shellPreview = read('plugins/mvm-hub/core/class-shell-preview-rest-controller.php');
const bootstrap = read('plugins/mvm-hub/mvm-hub.php');

function fail(message) { console.error(`FAIL: ${message}`); process.exitCode = 1; }
function ok(condition, message) { if (!condition) fail(message); }

ok(provider.includes('mpart_user_id=%d'), 'PeepSo read adapter must prove participant membership in SQL');
ok(provider.includes('$user_id !== get_current_user_id()'), 'PeepSo adapter must not read another user\'s threads');
ok(provider.includes("p.post_parent=0"), 'thread list must only expose conversation roots');
ok(provider.includes('COALESCE(r.mrec_deleted,0)=0'), 'deleted recipient rows must not be shown');
ok(provider.includes('LIMIT 500'), 'thread detail must be bounded');
ok(provider.includes("return $this->read_only();"), 'legacy PeepSo provider writes must fail closed');
ok(!/INSERT\s+INTO|UPDATE\s+\{|DELETE\s+FROM/i.test(provider), 'legacy PeepSo read adapter must not contain SQL mutations');

ok(service.includes('Internal_Message_Projector::thread_list'), 'internal message list must pass through privacy projector');
ok(service.includes('Internal_Message_Projector::thread_detail'), 'internal message detail must pass through privacy projector');
ok(controller.includes('WP_REST_Server::READABLE'), 'internal message routes must be GET-only');
ok(!/CREATABLE|EDITABLE|DELETABLE|POST|PUT|PATCH|DELETE/.test(controller), 'internal message read controller must expose no mutation verb');
ok(controller.includes("Capabilities::MESSAGES_ACCESS"), 'route permission must require message capability/admin');
ok(controller.includes("'permission_callback' => array( $this, 'can_read' )"), 'route must have an explicit permission callback');

ok(communicationsModule.includes('Internal_Message_Read_REST_Controller'), 'Communications module must register internal message reads');
ok(shellPreview.includes('Communications_Preview_Renderer::render'), 'gated shell preview must render Communications modules');
ok(preview.includes('Alleen gesprekken waarvan jij deelnemer bent'), 'preview must communicate participant scope');

// Check actual provider/storage fields rather than prose/comments mentioning privacy concepts.
[
  'post_content', 'mrec_', 'mpart_', 'user_email', 'user_login',
].forEach((field) => ok(!preview.includes(field), `preview must not reference provider/storage field ${field}`));
[
  'password', 'credential', 'secret', 'token', 'oauth', 'refresh_token',
].forEach((field) => {
  const access = new RegExp(`\\[['\"](?:[^'\"]*${field}[^'\"]*)['\"]\\]`, 'i');
  ok(!access.test(preview), `preview must not access credential-like field containing ${field}`);
});
ok(!/htmlBody|textBody|\['body'\]|\['content'\]/.test(preview), 'Communications list preview must not render message/mail bodies');

ok(bootstrap.includes('class-legacy-readonly-internal-message-provider.php'), 'bootstrap must load concrete PeepSo read adapter');
ok(bootstrap.includes('class-communications-preview-renderer.php'), 'bootstrap must load Communications preview renderer');

if (!process.exitCode) console.log('PASS: MvM Hub Communications read runtime/privacy contract');
