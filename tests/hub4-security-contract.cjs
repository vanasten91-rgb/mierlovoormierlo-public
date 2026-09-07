'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const repositoryRoot = path.resolve(__dirname, '..');
const pluginRoot = path.join(repositoryRoot, 'plugins', 'mvm-hub4-rc-direct');
const read = (file) => fs.readFileSync(path.join(pluginRoot, file), 'utf8').replace(/\r\n/g, '\n');

let assertions = 0;
const check = (condition, message) => {
    assert.ok(condition, message);
    assertions += 1;
};

const main = read('mvm-hub4.php');
const capabilities = read('src/class-capabilities.php');
const security = read('src/class-security.php');
const session = read('src/class-session.php');
const audit = read('src/class-audit.php');
const responseHardening = read('src/class-response-hardening.php');
const assignments = read('src/class-assignments.php');
const checklist = read('src/class-publication-checklist.php');
const rest = read('src/class-rest.php');
const launch = read('src/class-launch.php');
const newsroomRest = read('src/class-newsroom-rest.php');
const systemRest = read('src/class-system-stats-rest.php');
const healthRest = read('src/class-health-check-rest.php');
const app = read('src/class-app.php');
const template = read('templates/app.php');
const responsiveCss = read('assets/responsive-hardening.css');

const headerVersion = main.match(/^\s*\*\s+Version:\s+([^\s]+)\s*$/m)?.[1] || '';
const runtimeVersion = main.match(/define\(\s*'MVM_HUB4_VERSION'\s*,\s*'([^']+)'\s*\)/)?.[1] || '';
check(Boolean(headerVersion) && headerVersion === runtimeVersion, 'Plugin header and runtime version must match.');
check(main.includes('MvM_Hub4_Capabilities::init();'), 'Capability reconciliation must be initialized on upgrades.');

const routeSources = [rest, launch, newsroomRest, systemRest, healthRest].join('\n');
const routeCount = (routeSources.match(/register_rest_route\s*\(/g) || []).length;
const permissionCount = (routeSources.match(/['"]permission_callback['"]\s*=>/g) || []).length;
check(routeCount > 0 && permissionCount >= routeCount, 'Every REST route must have one or more permission callbacks.');
check(!routeSources.includes('__return_true'), 'Hub REST permissions may not use __return_true.');
check(routeSources.includes("'mvm-hub4/v1'"), 'Hub 4 must keep its dedicated REST namespace.');
check(security.includes('MvM_Hub4_Session::validate_and_touch()'), 'REST access must retain the additional Hub session boundary.');
check(session.includes('wp_get_session_token()') && session.includes('hash_hmac'), 'Hub sessions must remain bound to the WordPress session token.');
check(session.includes('wp_destroy_current_session()') && session.includes('wp_clear_auth_cookie()'), 'Expired Hub sessions must invalidate the current WordPress session.');

check(capabilities.includes('role->remove_cap( $capability )'), 'Role reconciliation must revoke stale Hub 4 grants.');
check(capabilities.includes('role_map_is_current()'), 'Role reconciliation must detect drift after activation.');

const roleBlock = (role, nextRole = null) => {
    const start = capabilities.indexOf(`'${role}' =>`);
    const end = nextRole
        ? capabilities.indexOf(`'${nextRole}' =>`, start + 1)
        : capabilities.indexOf('\n        );\n    }\n\n    public static function managed_capabilities', start + 1);
    check(start >= 0 && end > start, `Role block ${role} must be present.`);
    return capabilities.slice(start, end);
};

const photographer = roleBlock('mvm_fotograaf', 'mvm_moderator');
const moderator = roleBlock('mvm_moderator', 'mvm_vertaler');
const translator = roleBlock('mvm_vertaler');
const editor = roleBlock('mvm_editor', 'mvm_journalist');
const prohibitedLowRoleCaps = ['ASSIGNMENT_CREATE', 'ASSIGNMENT_MANAGE', 'CHECKLIST_USE', 'SOURCE_MANAGE', 'AUDIT_VIEW', 'SYSTEM_OVERVIEW'];

for (const [name, block] of [['Fotograaf', photographer], ['Moderator', moderator], ['Vertaler', translator]]) {
    for (const capability of prohibitedLowRoleCaps) {
        check(!block.includes(`self::${capability}`), `${name} may not receive ${capability}.`);
    }
    check(block.includes('self::ASSIGNMENT_VIEW'), `${name} must retain view access to assigned work.`);
    check(block.includes('self::TOOLS_USE'), `${name} must retain audited launches for its workflow tool.`);
}

check(photographer.includes('self::AGENDA_VIEW') && photographer.includes('self::NEWS_VIEW'), 'Fotograaf needs agenda/news context only.');
check(!moderator.includes('self::AGENDA_VIEW') && moderator.includes('self::NEWS_VIEW') && moderator.includes('self::TEAM_VIEW'), 'Moderator must be limited to moderation context.');
check(translator.includes('self::SOURCE_VIEW') && translator.includes('self::NEWS_VIEW'), 'Vertaler needs read-only source/news context.');
check(!editor.includes('self::SOURCE_MANAGE'), 'Editor may check sources but may not manage source configuration.');

check(checklist.includes('current_user_can( MvM_Hub4_Capabilities::CHECKLIST_USE )') && checklist.includes("current_user_can( 'edit_post', $post_id )"), 'Checklist writes need both global and object-level authorization.');
check(checklist.includes("'news.checklist_update', 'denied'"), 'Denied checklist writes must be audited.');
check(assignments.includes('validate_related_post_id(') && assignments.includes("current_user_can( 'read_post', $post_id )"), 'Assignment relationship IDs must be object-authorized.');
check(assignments.includes("'assignment.update', 'denied'"), 'Denied assignment updates must be audited.');
check(assignments.includes('validate_assignee(') && assignments.includes('ASSIGNMENT_VIEW'), 'Assignments may only target Hub users who can view assignments.');

check(audit.includes('MIN_RETENTION_DAYS    = 14'), 'Audit retention must be at least 14 days.');
check(audit.includes("wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily'"), 'Audit cleanup must remain scheduled daily.');
check(audit.includes('is_sensitive_context_key') && /email\|private/.test(audit), 'Audit context must redact email/private-key variants.');
check(responseHardening.includes('is_sensitive_key') && /email\|private/.test(responseHardening), 'REST responses must redact email/private-key variants.');
check(rest.includes('if ( $can_view_sources )') && rest.includes("$source_counts    = array( 'overdue' => 0"), 'Status must not disclose source counts without SOURCE_VIEW.');

check(app.includes("add_rewrite_rule( '^hub4/?$'") && !app.includes("add_rewrite_rule( '^hub/?$'"), 'Hub 4 routing must not replace the Hub 3 fallback route.');
check(template.includes('responsive-hardening.css'), 'The scoped responsive hardening stylesheet must be loaded last.');
check(responsiveCss.includes('min-inline-size: 260px') && responsiveCss.includes('@media (max-width: 900px)'), 'Desktop and tablet sidebar constraints must be explicit.');
check(responsiveCss.includes('word-break: normal') && responsiveCss.includes('overflow: visible'), 'Help/sidebar text may not letter-wrap or use a nested scrollbar.');
check(responsiveCss.includes('repeat(auto-fit, minmax(112px, 1fr))'), 'Tablet/mobile navigation must wrap instead of scrolling internally.');

const javascriptFiles = fs.readdirSync(path.join(pluginRoot, 'assets')).filter((name) => name.endsWith('.js'));
for (const file of javascriptFiles) {
    const source = read(path.join('assets', file));
    check(!/\b(?:innerHTML|outerHTML|insertAdjacentHTML|eval)\b/.test(source), `${file} may not use unsafe HTML/eval sinks.`);
    check(
        !/(?:window\.)?(?:localStorage|sessionStorage)\s*(?:\[|\.(?:getItem|setItem|removeItem|clear|key|length)\b)/.test(source),
        `${file} may not persist Hub data in browser storage.`
    );
    if (source.includes('fetch(')) {
        check(source.includes('X-WP-Nonce'), `${file} fetch calls must send the WordPress REST nonce.`);
        check(source.includes("credentials: 'same-origin'"), `${file} fetch calls must remain same-origin authenticated.`);
    }
}

const allPhp = fs.readdirSync(path.join(pluginRoot, 'src'))
    .filter((name) => name.endsWith('.php'))
    .map((name) => read(path.join('src', name)))
    .join('\n');
check(!/deactivate_plugins\s*\(/.test(allPhp), 'Hub 4 may not deactivate Hub 3.8.2 or another plugin.');
check(!/update_option\s*\(\s*['"][^'"]*(?:peepso|ultimate.member|elementor|postx)/i.test(allPhp), 'Protected plugin settings may not be changed.');

console.log(`Hub 4 security contract: ${assertions} assertions passed.`);
