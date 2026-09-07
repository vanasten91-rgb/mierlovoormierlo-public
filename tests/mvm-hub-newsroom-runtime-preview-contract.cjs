'use strict';

const fs = require('node:fs');
const path = require('node:path');

const root = path.resolve(__dirname, '..');
const read = (relative) => fs.readFileSync(path.join(root, relative), 'utf8');

const gates = read('plugins/mvm-hub/core/class-runtime-gates.php');
const controller = read('plugins/mvm-hub/core/class-shell-preview-rest-controller.php');
const kernel = read('plugins/mvm-hub/core/class-kernel.php');
const router = read('plugins/mvm-hub/core/class-router.php');
const bootstrap = read('plugins/mvm-hub/mvm-hub.php');

function fail(message) {
  console.error(`FAIL: ${message}`);
  process.exitCode = 1;
}
function ok(condition, message) {
  if (!condition) fail(message);
}

const previewGateBlock = gates.slice(gates.indexOf('public static function shell_preview_enabled'), gates.indexOf('public static function communications_writes_enabled'));
ok(previewGateBlock.includes("'MVM_HUB_ENABLE_SHELL_PREVIEW'"), 'shell preview must pass an explicit enable constant to the generic environment gate');
ok(previewGateBlock.includes("'MVM_HUB_ALLOW_PRODUCTION_SHELL_PREVIEW'"), 'production preview must pass a second explicit acknowledgement to the generic environment gate');
ok(previewGateBlock.includes('self::environment_gate('), 'shell preview must use the shared fail-closed environment gate');
ok(gates.includes("'production' !== $environment"), 'generic environment gate must be environment-aware');
ok(gates.includes('if ( ! defined( $enable_constant ) || true !== constant( $enable_constant ) )'), 'generic environment gate must fail closed when enable constant is absent/false');
ok(gates.includes('return defined( $production_constant ) && true === constant( $production_constant )'), 'production must require the second acknowledgement constant');

// Preview and internal Communications writes remain independent gates. Mail is
// stricter: only the exact staging clone can use the double-gated signed Mail
// rehearsal; WP_ENVIRONMENT_TYPE may not broaden Mail eligibility.
ok(gates.includes("'MVM_HUB_ENABLE_COMMUNICATION_WRITES'"), 'communications writes must have their own explicit gate');
ok(gates.includes("'MVM_HUB_ALLOW_PRODUCTION_COMMUNICATION_WRITES'"), 'production internal-message writes must require their own second acknowledgement');
const mailGate = gates.slice(gates.indexOf('public static function mail_writes_enabled'), gates.indexOf('private static function environment_gate'));
ok(mailGate.includes('if ( ! self::verified_mail_staging_host() )') && mailGate.includes('return false;'), 'external Mail delivery must remain hard disabled unless the exact staging clone is verified');
ok(mailGate.includes("return 'staging.mierlovoormierlo.nl' === $host;"), 'Mail rehearsal exception must be pinned to the exact staging host');
ok(!mailGate.includes('wp_get_environment_type'), 'Mail rehearsal eligibility must not broaden to arbitrary non-production hosts');
ok(mailGate.includes('MVM_HUB_ENABLE_STAGING_MAIL_WRITES') && mailGate.includes("true !== constant( 'MVM_HUB_ENABLE_STAGING_MAIL_WRITES' )"), 'staging Mail rehearsal must require the explicit literal-true staging-only constant');
ok(mailGate.includes("Release_Control::flag_enabled( 'mail_writes' )"), 'staging Mail rehearsal must also require signed mail release scope');
ok(!mailGate.includes('MVM_HUB_ENABLE_EXTERNAL_MAIL_DELIVERY') && !mailGate.includes('MVM_HUB_ALLOW_PRODUCTION_MAIL_DELIVERY') && !mailGate.includes('MVM_HUB_ALLOW_PRODUCTION_MAIL_WRITES'), 'production/config-only constants must not be able to reopen Mail delivery');
ok(!/COMMUNICATION_WRITES|EXTERNAL_MAIL_DELIVERY|ALLOW_PRODUCTION_MAIL_DELIVERY|ENABLE_STAGING_MAIL_WRITES/.test(previewGateBlock), 'shell preview gate must not enable or depend on communication write/delivery state');

ok(kernel.includes('if ( Runtime_Gates::shell_preview_enabled() )'), 'kernel must not register preview hook unless the preview gate is open');
ok(kernel.includes("'rest_api_init'"), 'gated preview must register only as a REST diagnostic');
ok(controller.includes("private const ROUTE     = '/runtime/shell-preview'"), 'preview route must stay outside /hub/ ownership');
ok(controller.includes('\\WP_REST_Server::READABLE'), 'preview route must be GET-only');
ok(controller.includes("current_user_can( 'manage_options' )"), 'preview route must be admin-only');
ok(controller.includes("'routeOwned'      => false"), 'preview response must explicitly report no route ownership');
ok(controller.includes("'mailWrites'      => Runtime_Gates::mail_writes_enabled()"), 'preview response must report Mail gate state without mutating it');
ok(controller.includes('Hub_Security_Policy::response_headers()'), 'preview must expose the intended private security-header contract for smoke validation');

ok(!/add_rewrite_rule|flush_rewrite_rules|template_include|template_redirect/m.test(controller + kernel + router), 'preview runtime must not claim or rewrite /hub/');
ok(!/wp_enqueue_(?:script|style)/m.test(controller + kernel), 'preview runtime must not enqueue site-wide assets');
ok(!/define\s*\(\s*['"]MVM_HUB_ENABLE_(?:COMMUNICATION_WRITES|EXTERNAL_MAIL_DELIVERY|STAGING_MAIL_WRITES)['"]/m.test(controller + kernel), 'preview runtime must never set communications or Mail delivery constants');
ok(bootstrap.includes("core/class-runtime-gates.php"), 'bootstrap must load runtime gates');
ok(bootstrap.includes("core/class-shell-preview-rest-controller.php"), 'bootstrap must load preview controller');

if (!process.exitCode) {
  console.log('PASS: MvM Hub isolated fail-closed runtime preview with production-closed, exact-staging signed Mail contract');
}
