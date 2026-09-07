const fs = require('fs');
const path = require('path');
const assert = require('assert');

const root = path.join(__dirname, '..');
const read = (file) => fs.readFileSync(path.join(root, file), 'utf8');
const account = read('plugins/mvm-platform/src/newsletter/class-newsletter-account.php');
const javascript = read('plugins/mvm-platform/assets/newsletter.js');
const template = read('plugins/mvm-platform/src/newsletter/class-newsletter-template.php');
const bootstrap = read('plugins/mvm-platform/bootstrap.php');
const platform = read('plugins/mvm-platform/src/class-platform.php');

assert(account.includes("'mvm_mijn_mierlo'"), 'Newsletter controls must be attached to the existing Mijn Mierlo account environment');
assert(account.includes("'mvm_newsletter_account'"), 'A reusable account shortcode must be registered');
assert(account.includes("'/newsletter/preferences'"), 'Account unsubscribe must reuse the existing preferences route');
assert(account.includes('WP_REST_Server::DELETABLE'), 'Full account unsubscribe must be an explicit DELETE action');
assert(account.includes("'MvM_Platform_REST', 'require_authenticated'"), 'Account unsubscribe must require authenticated REST access');
assert(account.includes('wp_get_current_user()'), 'Account unsubscribe must bind to the authenticated account');
assert(account.includes('subscriber_by_email'), 'Account unsubscribe must reuse the existing newsletter subscriber store');
assert(account.includes("'status'             => 'unsubscribed'"), 'Full unsubscribe must mark the existing subscriber as unsubscribed');
assert(account.includes("'topics'             => '[]'"), 'Full unsubscribe must clear newsletter topic delivery');
assert(account.includes("status IN ('pending','failed')"), 'Queued unsent deliveries must be cancelled on account unsubscribe');
assert(!account.includes('CREATE TABLE'), 'Account controls must not introduce a second newsletter data model');
assert(javascript.includes("method: 'DELETE'"), 'Account UI must invoke the authenticated full-unsubscribe action');
assert(javascript.includes('data-mvm-newsletter-account-unsubscribe-button'), 'Account UI must expose the full-unsubscribe control');
assert(javascript.includes('window.confirm'), 'Full unsubscribe must require explicit user confirmation');
assert(template.includes('altijd direct afmelden'), 'Every delivered newsletter must explicitly state that unsubscribe is always available');
assert(bootstrap.includes('class-newsletter-account.php'), 'Bootstrap must load the account integration');
assert(platform.includes('MvM_Newsletter_Account::boot()'), 'Platform boot must activate the account integration');

console.log('MvM newsletter account and unsubscribe contract: OK');
