const fs = require('fs');
const path = require('path');
const assert = require('assert');

const root = path.join(__dirname, '..');
const read = (file) => fs.readFileSync(path.join(root, file), 'utf8');
const php = read('plugins/mvm-platform/src/account/class-account-deletion.php');
const js = read('plugins/mvm-platform/assets/account-delete.js');
const bootstrap = read('plugins/mvm-platform/bootstrap.php');
const platform = read('plugins/mvm-platform/src/class-platform.php');

assert(php.includes("'mvm_mijn_mierlo'"), 'Account deletion must be attached to Mijn Mierlo');
assert(php.includes("'/account/delete/request'"), 'Deletion must start through an authenticated request route');
assert(php.includes("'/account/delete/confirm'"), 'Deletion must finish through a separate confirmation route');
assert((php.match(/require_authenticated/g) || []).length >= 2, 'Both deletion routes must require WordPress authentication and REST nonce validation');
assert(php.includes('wp_check_password'), 'Deletion request must reauthenticate with the current password');
assert(php.includes('TOKEN_LIFETIME = 30 * MINUTE_IN_SECONDS'), 'Email confirmation must expire quickly');
assert(php.includes('hash_hmac') && php.includes('hash_equals'), 'Confirmation token must be stored/compared as a keyed hash');
assert(php.includes("home_url( '/inloggen/#mvm-account-delete='"), 'Email token must remain in a URL fragment rather than server logs');
assert(php.includes('wp_privacy_personal_data_erasers'), 'Registered WordPress and plugin GDPR erasers must run');
assert(php.includes('wp_delete_user'), 'The WordPress account must be deleted through the core API');
assert(php.includes('is_protected_user') && php.includes('edit_others_posts'), 'Editorial and administrative accounts must be protected');
assert(php.includes("'status'             => 'unsubscribed'"), 'Deleting an account must stop its existing newsletter');
assert(php.includes("'email'              => $anonymous_email"), 'Newsletter PII must be anonymized before account deletion');
assert(php.includes("last_error = 'account_deleted'"), 'Unsent newsletter queue rows must be cancelled');
assert(js.includes('window.confirm'), 'Final browser action must require an explicit destructive confirmation');
assert(js.includes('window.history.replaceState'), 'Confirmation tokens must be removed from the address bar before deletion');
assert(bootstrap.includes('class-account-deletion.php'), 'Bootstrap must load the deletion module');
assert(platform.includes('MvM_Account_Deletion::boot()'), 'Platform must boot the deletion module');

console.log('MvM account deletion contract: OK');
