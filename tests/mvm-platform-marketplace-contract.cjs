'use strict';

const fs = require('fs');
const assert = require('assert');

const model = fs.readFileSync('plugins/mvm-platform/src/marketplace/class-marketplace.php', 'utf8');
const rest = fs.readFileSync('plugins/mvm-platform/src/marketplace/class-marketplace-rest.php', 'utf8');
const moderation = fs.readFileSync('plugins/mvm-platform/src/marketplace/class-marketplace-moderation.php', 'utf8');
const caps = fs.readFileSync('plugins/mvm-platform/src/class-capabilities.php', 'utf8');
const frontend = fs.readFileSync('plugins/mvm-platform/src/marketplace/class-marketplace-frontend.php', 'utf8');
const js = fs.readFileSync('plugins/mvm-platform/assets/marketplace.js', 'utf8');
const single = fs.readFileSync('plugins/mvm-platform/templates/marketplace/single.php', 'utf8');

assert.match(model, /public const POST_TYPE\s*= 'mvm_listing'/);
assert.match(model, /'show_in_rest'\s*=>\s*false/);
assert.match(model, /MvM_Platform_Capabilities::MARKETPLACE_MODERATE/);
assert.match(caps, /mvm_marketplace_moderate/);
assert.match(model, /validate_listing_text/);
assert.match(model, /vuurwapen/);
assert.match(model, /vuurwerk/);
assert.match(model, /cannabis/);
assert.match(model, /nicotine/);
assert.match(model, /receptplichtig/);
assert.match(model, /MAX_GALLERY\s*= 8/);
assert.match(model, /wp_clear_scheduled_hook/);
assert.ok(!model.includes("apply_filters( 'the_content'"), 'marketplace API descriptions must not run unrelated global content filters');
assert.ok(!model.includes("'id'          => $author_id"), 'public seller payload must not expose the numeric WordPress user ID');

for (const route of [
  "'/marketplace'",
  "'/marketplace/mine'",
  "'/marketplace/media'",
  "'/marketplace/(?P<id>\\d+)'",
  "'/marketplace/(?P<id>\\d+)/status'",
  "'/marketplace/(?P<id>\\d+)/report'",
  "'/marketplace/moderation'",
  "'/marketplace/(?P<id>\\d+)/moderate'",
]) {
  assert.ok(rest.includes(route), `missing REST route ${route}`);
}

assert.match(rest, /permission_callback/);
assert.match(rest, /MvM_Marketplace::is_owner/);
assert.match(rest, /MARKETPLACE_MODERATE/);
assert.match(rest, /post_author.*get_current_user_id/s);
assert.match(rest, /attachment->post_author/);
assert.match(rest, /wp_attachment_is_image/);
assert.match(rest, /8 \* MB_IN_BYTES/);
assert.match(rest, /wp_kses_post/);
assert.match(rest, /mvm_marketplace_duplicate_report/);
assert.match(rest, /mvm_marketplace_rate_limited/);
assert.match(moderation, /str_starts_with\( \$event, 'moderation_' \)/);
assert.match(moderation, /delete_post_meta\( \$post_id, MvM_Marketplace::meta_key\( 'reports' \) \)/);
assert.ok(!rest.includes('Access-Control-Allow-Origin'), 'marketplace must not add custom permissive CORS');

for (const forbiddenField of ['email', 'phone', 'telephone', 'street_address']) {
  assert.ok(!model.includes(`'${forbiddenField}'`), `public listing model must not expose ${forbiddenField}`);
}

assert.ok(!js.includes('.innerHTML'), 'REST listing data must not be injected using innerHTML');
assert.ok(!js.includes('insertAdjacentHTML'), 'REST listing data must not be injected as HTML');
assert.match(js, /textContent/);
assert.match(js, /X-WP-Nonce/);
assert.match(js, /credentials: 'same-origin'/);
assert.match(js, /\/media/);
assert.match(js, /\/report/);

assert.ok(!single.includes('comments_template'), 'marketplace must not alter/reuse the frozen PeepSo comment setup');
assert.ok(!single.toLowerCase().includes('mailto:'), 'seller email must not be exposed in listing template');
assert.match(frontend, /dark|marketplace/i);

console.log('MvM marketplace security/privacy contract: OK');
