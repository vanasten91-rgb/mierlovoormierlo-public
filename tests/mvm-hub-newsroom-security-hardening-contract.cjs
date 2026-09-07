'use strict';

const fs = require('node:fs');
const path = require('node:path');
const root = path.resolve(__dirname, '..');

const security = fs.readFileSync(path.join(root, 'plugins/mvm-hub/core/class-hub-security-policy.php'), 'utf8');
const communications = fs.readFileSync(path.join(root, 'plugins/mvm-hub/core/class-communications-policy.php'), 'utf8');
const privacy = fs.readFileSync(path.join(root, 'plugins/mvm-hub/core/class-privacy.php'), 'utf8');
const scanner = fs.readFileSync(path.join(root, 'plugins/mvm-hub/integrations/mail/interface-attachment-scanner.php'), 'utf8');

function fail(message) {
  console.error(`FAIL: ${message}`);
  process.exitCode = 1;
}
function ok(condition, message) {
  if (!condition) fail(message);
}

// Future /hub/ must be private/non-indexable and protected against common browser leaks.
ok(security.includes("'Cache-Control'" ) && security.includes('no-store'), 'Hub response policy must disable shared/browser storage');
ok(security.includes("'X-Robots-Tag'" ) && security.includes('noindex'), 'Hub must not be indexed');
ok(security.includes("'Referrer-Policy'" ) && security.includes('same-origin'), 'Hub must limit referrer leakage');
ok(security.includes("'X-Content-Type-Options'" ) && security.includes('nosniff'), 'Hub must prevent MIME sniffing');
ok(security.includes("'Content-Security-Policy'"), 'Hub must define a CSP policy');
ok(security.includes("object-src 'none'"), 'Hub CSP must block plugin/object content');
ok(security.includes("frame-ancestors 'self'"), 'Hub CSP must protect against framing/clickjacking');
ok(security.includes('IDLE_TIMEOUT_SECONDS'), 'Hub must define an idle session timeout');
ok(security.includes('STEP_UP_WINDOW_SECONDS'), 'Hub must define a sensitive-action reauthentication window');
ok(security.includes("'mail_send'"), 'mail send must require sensitive-action step-up');
ok(!/add_action|add_filter|header\s*\(/m.test(security), 'security policy must remain inert until central Hub route takeover');

// Generic notifications should not leak mail subject lines by default.
ok(/notification_subjects_allowed_by_default\(\): bool\s*\{\s*return false;/m.test(communications), 'mail subjects must be hidden in generic notifications by default');
ok(communications.includes("return 'Nieuw beveiligd bericht';"), 'notification fallback must be generic');
ok(privacy.includes("'subject'           => self::CONFIDENTIAL_DATA"), 'mail subjects must be confidential in audit/privacy classification');
ok(privacy.includes("'internal_brief'    => self::CONFIDENTIAL_DATA"), 'internal dossier briefs must be confidential');
ok(privacy.includes("'message'           => self::CONFIDENTIAL_DATA"), 'correction/free-form messages must be confidential in audit logs');

// Attachments use a strict allow-list, not merely a deny-list.
ok(communications.includes('ALLOWED_ATTACHMENT_TYPES'), 'attachment allow-list must exist');
ok(communications.includes('attachment_type_allowed'), 'attachment filename+MIME+size must be checked together');
ok(communications.includes('attachment_content_allowed'), 'attachment bytes must be checked against signature/container policy');
ok(communications.includes('zip_is_safe_office_document'), 'Office ZIP containers must be structurally validated');
ok(communications.includes('cfb_is_safe_office_document'), 'legacy Office containers must reject macro markers');
ok(communications.includes('pdf_is_passive'), 'PDF active content must be rejected before scanning');
ok(communications.includes('max_attachment_bytes()'), 'attachment size must be enforced');
['php', 'phtml', 'phar', 'html', 'svg', 'zip', 'docm', 'xlsm', 'js', 'exe'].forEach((extension) => {
  ok(communications.includes(`'${extension}'`), `dangerous attachment extension must be blocked: ${extension}`);
});
['jpg', 'jpeg', 'jpe', 'jfif', 'png', 'gif', 'webp', 'avif', 'heic', 'heif', 'tif', 'tiff', 'bmp',
 'mp4', 'm4v', 'mov', 'webm', 'ogv', 'mkv', 'avi', 'mpeg', 'mpg', 'm2v', '3gp', '3g2',
 'pdf', 'doc', 'docx', 'odt', 'rtf', 'txt', 'md', 'csv'].forEach((extension) => {
  ok(communications.includes(`'${extension}'`), `required newsroom attachment type missing: ${extension}`);
});
ok(scanner.includes('interface Attachment_Scanner'), 'private attachment scanner boundary must exist');
ok(scanner.includes("status:string"), 'scanner must return an explicit status');

if (!process.exitCode) {
  console.log('PASS: MvM Hub security/privacy hardening contract');
}
