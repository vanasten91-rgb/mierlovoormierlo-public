'use strict';

const fs = require('node:fs');
const path = require('node:path');
const root = path.resolve(__dirname, '..');
const read = (relative) => fs.readFileSync(path.join(root, relative), 'utf8');
const must = (condition, message) => { if (!condition) throw new Error(message); };

const assets = read('plugins/mvm-hub/core/class-assets.php');
const runtime = read('plugins/mvm-hub/core/class-hub-runtime.php');
const moduleSource = read('plugins/mvm-hub/modules/communications/class-communications-module.php');
const registrar = read('plugins/mvm-hub/modules/communications/class-mail-write-rest-controller.php');
const callbacks = read('plugins/mvm-hub/modules/communications/class-communications-write-rest-controller.php');
const readController = read('plugins/mvm-hub/modules/communications/class-mail-read-rest-controller.php');
const folderService = read('plugins/mvm-hub/modules/communications/class-mail-folder-service.php');
const incoming = read('plugins/mvm-hub/modules/communications/class-incoming-mail-attachment-service.php');
const preferences = read('plugins/mvm-hub/modules/communications/class-mail-message-preferences.php');
const blocklist = read('plugins/mvm-hub/modules/communications/class-mail-sender-blocklist.php');
const renderer = read('plugins/mvm-hub/modules/communications/class-communications-runtime-renderer.php');
const js = read('plugins/mvm-hub/assets/communications.js');
const ui = read('plugins/mvm-hub/assets/communications-ui.js');
const css = read('plugins/mvm-hub/assets/communications-ui.css');

for (const token of ['communications-ui.css', 'communications-ui.js', 'communicationsUiStyle', 'communicationsUiScript']) {
  must(assets.includes(token) || runtime.includes(token), `editorial UI asset is not runtime reachable: ${token}`);
}
must(runtime.indexOf("'communicationsStyle'") < runtime.indexOf("'communicationsUiStyle'"), 'Mail visual layer must load after the base stylesheet');
must(runtime.indexOf("'communicationsScript'") < runtime.indexOf("'communicationsUiScript'"), 'Mail enhancement script must load after the base client');

must(registrar.includes('Runtime_Gates::mail_writes_enabled()'), 'all Mail mutations must remain behind the fail-closed Mail gate');
must(registrar.includes("array( 'archive', 'trash', 'spam' )"), 'missing gated special Mail action routes');
for (const route of ['/pin-state', '/report', '/block-sender', '/communications/mail/messages/bulk']) {
  must(registrar.includes(route), `missing gated Mail action route ${route}`);
}
must((registrar.match(/can_manage_messages/g) || []).length >= 6, 'new message actions must reuse MAIL_MANAGE_MESSAGES authorization');
must(callbacks.includes("array_slice( $input['ids'], 0, 100 )"), 'bulk requests must be bounded to 100 messages');
must(callbacks.includes("array( 'read', 'unread', 'flag', 'unflag', 'pin', 'unpin', 'move', 'archive', 'trash', 'spam' )"), 'bulk action allowlist is incomplete');
must(folderService.includes('authorize_message_action'), 'message actions must retain session, capability and object authorization');
must(folderService.includes("array( 'archive', 'trash', 'junk' )"), 'special-folder actions must be allowlisted');
must(folderService.includes('mvm_mail_special_folder_unavailable'), 'missing/ambiguous special folders must fail closed');

must(readController.includes('/attachments/(?P<attachment>'), 'incoming attachment download route missing');
for (const token of ["requires_step_up( 'mail_attachment_download' )", 'Private_Storage_Root::directory', 'attachment_content_allowed', '$this->scanner->scan', "'clean' !==", "'contentBase64'", "@unlink( $temporary )"]) {
  must(incoming.includes(token), `incoming attachment security boundary missing ${token}`);
}
must(!/wp_get_attachment_url|wp_upload_dir|publicMediaPromotion.*true/.test(incoming), 'incoming downloads must never become public Media Library URLs');
must(moduleSource.includes('WordPress_Step_Up_Authenticator::register()'), 'read-sensitive attachment step-up must be runtime reachable');

must(preferences.includes('wp_hash') && preferences.includes('update_user_meta'), 'pin state must store only keyed per-user references');
must(blocklist.includes('wp_hash') && blocklist.includes('update_option'), 'sender blocking must store only keyed mailbox references');
must(!preferences.includes('$subject') && !blocklist.includes('$subject'), 'private preference stores must not persist mail subjects');

for (const token of ['data-mail-sort', 'Nieuwste eerst', 'Oudste eerst', 'data-mail-report-dialog', 'data-folder-special']) {
  must(renderer.includes(token), `runtime Mail UI missing ${token}`);
}
for (const token of ['togglePin', 'runSpecialAction', 'toggleSenderBlock', 'downloadAttachment', 'contentBase64', "sort: 'date'", 'sortDirection']) {
  must(js.includes(token), `Mail client behavior missing ${token}`);
}
for (const action of ['read', 'unread', 'flag', 'pin', 'archive', 'spam', 'trash', 'move']) {
  must(ui.includes(`data-mail-bulk-action="${action}"`), `bulk UI missing ${action}`);
}
must(ui.includes("api('/communications/mail/messages/bulk'"), 'bulk UI must use one bounded server route');
for (const token of ['#1966AE', 'color-scheme:light', 'color-scheme:dark', '@media(max-width:1180px)', '@media(max-width:900px)', '@media(max-width:720px)', '@media(max-width:520px)', '@media(prefers-contrast:more)']) {
  must(css.includes(token), `light/dark/responsive Mail visual contract missing ${token}`);
}

console.log('PASS: full MvM editorial Mail client remains gated, private, responsive and attachment-safe');
