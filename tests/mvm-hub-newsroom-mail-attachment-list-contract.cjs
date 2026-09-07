'use strict';

const fs = require('node:fs');
const path = require('node:path');
const root = path.resolve(__dirname, '..');
const source = fs.readFileSync(path.join(root, 'plugins/mvm-hub/integrations/mail/class-folder-scoped-imap-mail-provider.php'), 'utf8');

function must(condition, message) {
  if (!condition) throw new Error(message);
}

const listStart = source.indexOf('public function list_messages');
const detailStart = source.indexOf('/** @return array<string,mixed>|\\WP_Error */', listStart);
const listBlock = source.slice(listStart, detailStart);

must(listBlock.includes('self::folder_from_id( $folder_id )'), 'message list must stay bound to the requested folder');
must(listBlock.includes("$this->connect( $mailbox_id, $remote )"), 'message list must use one folder-scoped IMAP connection for MIME metadata');
must(listBlock.includes('imap_fetchstructure'), 'message list must inspect MIME structure without fetching attachment bodies');
must(listBlock.includes("$message['hasAttachments']"), 'message list must explicitly project hasAttachments');
must(listBlock.includes('self::structure_has_attachment'), 'message list must use the bounded recursive MIME attachment detector');
must(source.includes('private static function structure_has_attachment'), 'recursive MIME attachment detector missing');
must(source.includes("self::part_filename( $structure )"), 'attachment detection must reuse the same filename/name parsing as message detail');
must(source.includes("'attachment' === strtolower"), 'explicit MIME attachment disposition must be recognized');
must(!listBlock.includes('imap_fetchbody') && !listBlock.includes('imap_body('), 'message list must not download message or attachment bodies merely to show the attachment indicator');

console.log('PASS: Mail message lists expose attachment indicators from folder-scoped MIME structure metadata');
