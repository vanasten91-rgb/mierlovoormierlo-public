const fs = require('fs');
const path = require('path');
const crypto = require('crypto');
const assert = require('assert');

const root = path.join(__dirname, '..');
const read = (file) => fs.readFileSync(path.join(root, file), 'utf8');
const php = read('plugins/mvm-platform/src/account/class-account-login.php');
const template = read('plugins/mvm-platform/templates/account/login.php');
const js = read('plugins/mvm-platform/assets/account-login.js');
const css = read('plugins/mvm-platform/assets/account-login.css');
const bootstrap = read('plugins/mvm-platform/bootstrap.php');
const platform = read('plugins/mvm-platform/src/class-platform.php');
const logo = fs.readFileSync(path.join(root, 'plugins/mvm-platform/assets/mvm-login-logo.jpg'));

assert(php.includes("private const PATH = '/inloggen/'"), 'A dedicated MvM login route must be used');
assert(php.includes("add_filter( 'login_url'"), 'WordPress login links must point to the MvM screen');
assert(php.includes("add_action( 'login_init'"), 'Direct standard WordPress login visits must be redirected');
assert(php.includes('wp_signon'), 'Authentication must remain on the WordPress core backend');
assert(php.includes('check_ajax_referer'), 'Login must enforce a same-origin nonce');
assert(php.includes('mvm_hub_password_recaptcha_verify_v12'), 'Login must reuse the existing server-side MvM reCAPTCHA verifier');
assert(php.includes("! function_exists( 'mvm_hub_password_recaptcha_verify_v12' ) ||"), 'reCAPTCHA must fail closed when the verifier is unavailable');
assert(php.includes('consume_login_allowance'), 'Login must be rate limited');
assert(php.includes('wp_validate_redirect'), 'Post-login redirects must be same-host validated');
assert(php.includes('RESPONSE_FLOOR_SECONDS'), 'Login errors must use a timing floor');
assert(!/do_action\([^;]*\$password/s.test(php), 'Password must not be added to audit/log payloads');
assert(template.includes('assets/mvm-login-logo.jpg'), 'The supplied MvM logo must be shown');
assert(template.includes("home_url( '/privacy/' )"), 'Privacy policy must be linked');
assert(template.includes("home_url( '/algemene-voorwaarden/' )"), 'Terms and conditions must be linked');
assert(template.includes('data-mvm-recaptcha'), 'The custom form must contain an invisible reCAPTCHA mount');
assert(js.includes("action: 'mvm_account_login'"), 'Custom form must submit only to the MvM auth handler');
assert(js.includes('recaptcha_token'), 'Client must send a fresh reCAPTCHA token');
assert(js.includes('window.location.hash') && js.includes('mvm-account-delete'), 'Login must preserve account-deletion confirmation fragments');
assert(css.includes('background: #1966ae'), 'Login background must match the supplied logo blue');
assert(crypto.createHash('sha256').update(logo).digest('hex').toUpperCase() === 'FD653ADFD1BB89A0677268460E3A5FDB5E58E1A14313261BC70ABFD2738FA758', 'Login must use the exact supplied logo asset');
assert(bootstrap.includes('class-account-login.php'), 'Bootstrap must load the login module');
assert(platform.includes('MvM_Account_Login::boot()'), 'Platform must boot the MvM login module');

console.log('MvM account login contract: OK');
