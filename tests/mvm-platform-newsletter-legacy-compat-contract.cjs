const fs = require('fs');
const assert = require('assert');

const compat = fs.readFileSync('plugins/mvm-platform/src/newsletter/class-newsletter-legacy-compat.php', 'utf8');
const bootstrap = fs.readFileSync('plugins/mvm-platform/bootstrap.php', 'utf8');
const platform = fs.readFileSync('plugins/mvm-platform/src/class-platform.php', 'utf8');

assert(compat.includes("function_exists( 'mvm_newsletter_table_v1' )"), 'legacy coexistence guard is required');
assert(compat.includes("add_shortcode( 'mvm_newsletter_signup_v1'"), 'legacy signup shortcode must remain compatible');
assert(compat.includes("add_shortcode( 'mvm_newsletter_unsubscribe_v1'"), 'legacy unsubscribe shortcode must remain compatible');
assert(compat.includes('MvM_Newsletter::email_hash'), 'legacy subscriber identities must migrate to canonical HMAC hashing');
assert(compat.includes("hash( 'sha256', $token )"), 'in-flight legacy token links must remain consumable during cutover');
assert(compat.includes("'unsubscribe_check_email'"), 'unsubscribe request response must remain non-enumerating');
assert(compat.includes('MvM_Newsletter::unsubscribe_token'), 'new unsubscribe links must use the canonical signed token');
assert(bootstrap.includes("class-newsletter-legacy-compat.php"), 'compat adapter must be loaded by the platform bootstrap');
assert(platform.includes('MvM_Newsletter_Legacy_Compat::boot();'), 'compat adapter must be booted by MvM Platform');

console.log('MvM Platform newsletter legacy compatibility contract: OK');
