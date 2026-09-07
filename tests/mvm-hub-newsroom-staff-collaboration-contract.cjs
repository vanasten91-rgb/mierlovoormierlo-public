'use strict';

const fs = require('fs');
const assert = require('assert');

const read = (path) => fs.readFileSync(path, 'utf8');
const collaboration = read('plugins/mvm-hub/modules/newsroom/class-staff-collaboration.php');
const capabilities = read('plugins/mvm-hub/core/class-capabilities.php');
const bundles = read('plugins/mvm-hub/core/class-role-capability-bundles.php');
const admin = read('plugins/mvm-hub/modules/newsroom/class-newsroom-admin.php');
const js = read('plugins/mvm-hub/assets/newsroom2-admin.js');
const css = read('plugins/mvm-hub/assets/newsroom2-admin.css');

for (const [pattern, label] of [
  [/private\s+const\s+BOARD_TYPE\s*=\s*'mvm_staff_notice'\s*;/, 'private board post type'],
  [/private\s+const\s+CHAT_TYPE\s*=\s*'mvm_staff_chat'\s*;/, 'private chat post type'],
  [/private\s+const\s+CHAT_RETENTION_DAYS\s*=\s*30\s*;/, '30-day chat retention'],
]) assert(pattern.test(collaboration), `Missing collaboration invariant: ${label}`);

for (const needle of [
  "'public'              => false",
  "'publicly_queryable'  => false",
  "'show_in_rest'        => false",
  "'query_var'           => false",
  "'rewrite'             => false",
  "check_ajax_referer( self::NONCE, 'nonce' )",
  'Runtime_Gates::newsroom_writes_enabled()',
  "set_transient( $rate_key, 1, 2 )",
  "'after'     => gmdate( 'Y-m-d H:i:s', time() - self::CHAT_RETENTION_DAYS * DAY_IN_SECONDS )",
]) assert(collaboration.includes(needle), `Missing collaboration invariant: ${needle}`);

for (const action of [
  'mvm_nr2_board_list', 'mvm_nr2_board_post', 'mvm_nr2_board_toggle_pin', 'mvm_nr2_board_delete',
  'mvm_nr2_chat_list', 'mvm_nr2_chat_send', 'mvm_nr2_chat_delete',
]) assert(collaboration.includes(`wp_ajax_${action}`), `Missing authenticated AJAX action ${action}`);

assert(!collaboration.includes('wp_ajax_nopriv_'), 'Staff collaboration must never expose nopriv AJAX');
assert(!collaboration.includes('wp_mail('), 'Staff collaboration must not send mail');
assert(!collaboration.includes('phpmailer_init'), 'Staff collaboration must not own PHPMailer');
assert(!collaboration.includes("Audit::record( 'newsroom.team_chat.sent', 'success', 'team_chat', (int) $post_id, array( 'message'"), 'Chat content must never be copied to audit metadata');
assert(collaboration.includes("array( 'length' => mb_strlen( $message ) )"), 'Chat audit must use message length metadata only');

for (const cap of [
  'STAFF_BOARD_VIEW', 'STAFF_BOARD_POST', 'STAFF_BOARD_MODERATE',
  'TEAM_CHAT_ACCESS', 'TEAM_CHAT_SEND', 'TEAM_CHAT_MODERATE',
]) {
  assert(capabilities.includes(`public const ${cap}`), `Missing capability ${cap}`);
  assert(bundles.includes(`Capabilities::${cap}`), `Capability ${cap} is not assigned in managed role bundles`);
}
assert(/private\s+const\s+SCHEMA_VERSION\s*=\s*3\s*;/.test(bundles), 'Staff collaboration capabilities must remain present in capability schema version 3');

assert(admin.includes("'mvm-newsroom-board'      => 'Prikbord'"), 'Prikbord admin page missing');
assert(admin.includes("'mvm-newsroom-chat'       => 'Teamchat'"), 'Teamchat admin page missing');
assert(admin.includes("'ajaxUrl'       => admin_url( 'admin-ajax.php' )"), 'Admin AJAX URL must be localized server-side');
assert(admin.includes("'nonce'         => Staff_Collaboration::nonce()"), 'Collaboration nonce must be localized server-side');

assert(!js.includes('innerHTML'), 'Newsroom JS must not render staff content through innerHTML');
assert(js.includes('textContent'), 'Newsroom JS must use textContent for staff content');
assert(js.includes("window.setInterval(loadChat, 12000)"), 'Teamchat refresh interval changed unexpectedly');
assert(css.startsWith('@import url("./newsroom2-collaboration.css");'), 'Collaboration stylesheet must be imported by Newsroom admin CSS');

console.log('MvM Newsroom staff collaboration security contract: OK');
