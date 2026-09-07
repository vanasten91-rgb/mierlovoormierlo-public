'use strict';

const fs = require('fs');
const path = require('path');

const root = path.resolve(__dirname, '..');
const pluginPath = path.join(root, 'plugins/mvm-hub4-rc-direct/mvm-hub4.php');
const routerPath = path.join(root, 'plugins/mvm-hub4-rc-direct/src/class-canonical-hub.php');
const appPath = path.join(root, 'plugins/mvm-hub4-rc-direct/src/class-app.php');
const sessionPath = path.join(root, 'plugins/mvm-hub4-rc-direct/src/class-session.php');

const plugin = fs.readFileSync(pluginPath, 'utf8');
const router = fs.readFileSync(routerPath, 'utf8');
const app = fs.readFileSync(appPath, 'utf8');
const session = fs.readFileSync(sessionPath, 'utf8');

function assert(condition, message) {
  if (!condition) {
    console.error(`FAIL: ${message}`);
    process.exit(1);
  }
}

const headerVersion = plugin.match(/\* Version:\s*([^\s]+)/)?.[1] || '';
const runtimeVersion = plugin.match(/define\( 'MVM_HUB4_VERSION', '([^']+)' \)/)?.[1] || '';
assert(/^\d+\.\d+\.\d+(?:-rc\d+)?$/.test(headerVersion), 'Integrated Hub plugin header must be explicit');
assert(runtimeVersion === headerVersion, 'Integrated Hub runtime version must match header');
assert(plugin.includes("require_once MVM_HUB4_DIR . 'src/class-canonical-hub.php'"), 'canonical router must be loaded');
assert(plugin.includes('MvM_Hub4_Canonical_Hub::init();'), 'canonical router must be initialized');

assert(router.includes("add_action( 'template_redirect', array( __CLASS__, 'route' ), -100 )"), 'canonical route must run before legacy template rendering');
assert(router.includes("wp_parse_url( $uri, PHP_URL_PATH )"), 'routing must match URL path rather than raw query string');
assert(router.includes("home_url( '/hub/' )"), 'canonical route must derive /hub from WordPress home URL');
assert(router.includes("home_url( '/hub4/' )"), 'compatibility route must derive /hub4 from WordPress home URL');
assert(router.includes("wp_safe_redirect( home_url( '/hub/' ), 302, 'MvM Hub 4' )"), '/hub4 must redirect safely to canonical /hub');
assert(router.includes("if ( ! is_user_logged_in() )"), 'unauthenticated /hub must be left to the existing gateway');
assert(router.includes("set_query_var( 'mvm_hub4', '1' )"), 'authenticated /hub must enter the hardened Hub4 request pipeline');
assert(router.includes('MvM_Hub4_App::render_if_requested();'), 'canonical route must reuse Hub4 app security/render pipeline');

assert(!/\bwp_signon\s*\(/.test(router), 'canonical router must not implement password authentication');
assert(!/\bwp_set_auth_cookie\s*\(/.test(router), 'canonical router must not mint auth cookies');
assert(!/\$_(?:POST|REQUEST|COOKIE)\s*\[/.test(router), 'canonical router must not process credentials or mutable request payloads');
assert(!/mvm_bh_news_radar_|mvm_mail_|mvm_today_/.test(router), 'canonical router must not take ownership of legacy service data');

const loginCheck = app.indexOf('if ( ! is_user_logged_in() )');
const capCheck = app.indexOf('if ( ! MvM_Hub4_Security::can_access_hub() )');
const sessionCheck = app.indexOf('MvM_Hub4_Session::validate_and_touch()');
const headersCheck = app.indexOf('self::send_security_headers()');
assert(loginCheck >= 0 && capCheck > loginCheck, 'Hub4 app must keep login then capability boundary');
assert(sessionCheck > capCheck, 'Hub4 app must validate browser-bound Hub session after capability check');
assert(headersCheck > sessionCheck, 'security headers must be sent only after successful session validation');
assert(app.includes("MvM_Hub4_Audit::log( 'hub.session_open'"), 'canonical Hub render must remain audit logged');
assert(app.includes("'Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0'"), 'Hub response must remain no-store');
assert(app.includes("Content-Security-Policy: default-src 'self'"), 'Hub response must retain strict CSP');

assert(session.includes('wp_get_session_token()'), 'Hub4 session must remain bound to WordPress browser session token');
assert(session.includes('DEFAULT_IDLE_SECONDS     = 30 * MINUTE_IN_SECONDS'), 'Hub4 idle timeout must remain 30 minutes');
assert(session.includes('DEFAULT_ABSOLUTE_SECONDS = 8 * HOUR_IN_SECONDS'), 'Hub4 absolute timeout must remain 8 hours');

console.log(`Hub ${headerVersion} canonical /hub route contract: all assertions passed.`);
