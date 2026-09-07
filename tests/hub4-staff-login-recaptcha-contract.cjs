'use strict';

const fs = require('fs');
const path = require('path');

const root = path.resolve(__dirname, '..', 'plugins', 'mvm-hub4-rc-direct');
const main = fs.readFileSync(path.join(root, 'mvm-hub4.php'), 'utf8');
const gate = fs.readFileSync(path.join(root, 'src/class-staff-login-recaptcha.php'), 'utf8');

function assert(condition, message) {
  if (!condition) {
    console.error(`FAIL: ${message}`);
    process.exit(1);
  }
}

assert(main.includes("src/class-staff-login-recaptcha.php"), 'reCAPTCHA hardening class must be bootstrapped');
assert(main.includes('MvM_Hub4_Staff_Login_Recaptcha::init();'), 'reCAPTCHA hardening class must initialize');
assert(gate.includes("mvm_hub_password_staff_login_v2"), 'current adopted staff-login action must be gated');
assert(gate.includes("mvm_hub_password_reset_request_v2', 'mvm_hub_password_staff_login_v2"), 'client allow-list must request reCAPTCHA for v2 staff login');
assert(gate.includes("mvm_hub_password_reset_request_v1', 'mvm_hub_password_staff_login_v1"), 'legacy v1 fallback must remain protected');
assert(gate.includes("$_POST['recaptcha_token']"), 'server gate must read the submitted reCAPTCHA token');
assert(gate.includes("mvm_hub_password_recaptcha_verify_v12( $token )"), 'server gate must call the existing hostname-validating verifier');
assert(gate.includes("! function_exists( 'mvm_hub_password_recaptcha_verify_v12' )"), 'missing verifier must fail closed');
assert(gate.includes("mvm_hub_password_gateway_proof_valid_v1()"), 'gateway proof must be checked before delegation');
assert(gate.includes("check_ajax_referer( 'mvm_hub_password_staff_login_v1', 'nonce', false )"), 'staff-login nonce must be checked before delegation');
assert(gate.includes("remove_action( 'wp_ajax_nopriv_' . self::LOGIN_ACTION"), 'legacy unauthenticated handler must be replaced');
assert(gate.includes("remove_action( 'wp_ajax_' . self::LOGIN_ACTION"), 'legacy authenticated handler must be replaced');
assert(gate.includes("mvm_hub_auth_v4_login();"), 'gate must delegate only after verification to the current credential handler');
assert(gate.includes("is_user_logged_in() || ! self::is_logged_out_hub_request()"), 'client patch must be scoped to logged-out /hub only');
assert(gate.includes("auth.staff_login_gate_denied"), 'denied gateway attempts must be auditable without credentials');
assert(!/password\s*=>|identifier\s*=>|user_pass|recaptcha_secret/i.test(gate), 'hardening layer must not log or embed credentials/secrets');

console.log('Hub 4 staff-login reCAPTCHA contract: all assertions passed.');
