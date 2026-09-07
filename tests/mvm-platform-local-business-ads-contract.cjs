'use strict';

const fs = require('fs');
const assert = require('assert');

const ads = fs.readFileSync('plugins/mvm-platform/src/local-ads/class-local-business-ads.php', 'utf8');
const rest = fs.readFileSync('plugins/mvm-platform/src/local-ads/class-local-business-ads-rest.php', 'utf8');
const entrepreneurRest = fs.readFileSync('plugins/mvm-platform/src/local-ads/class-entrepreneur-ads-rest.php', 'utf8');
const portal = fs.readFileSync('plugins/mvm-platform/src/local-ads/class-entrepreneur-portal.php', 'utf8');
const offers = fs.readFileSync('plugins/mvm-platform/src/local-ads/class-offers-page.php', 'utf8');
const widget = fs.readFileSync('plugins/mvm-platform/src/local-ads/class-local-business-ad-widget.php', 'utf8');
const css = fs.readFileSync('plugins/mvm-platform/assets/local-business-ads.css', 'utf8');
const entrepreneurCss = fs.readFileSync('plugins/mvm-platform/assets/entrepreneur-ads.css', 'utf8');
const entrepreneurJs = fs.readFileSync('plugins/mvm-platform/assets/entrepreneur-ads.js', 'utf8');
const bootstrap = fs.readFileSync('plugins/mvm-platform/bootstrap.php', 'utf8');
const platform = fs.readFileSync('plugins/mvm-platform/src/class-platform.php', 'utf8');
const caps = fs.readFileSync('plugins/mvm-platform/src/class-capabilities.php', 'utf8');
const hubApp = fs.readFileSync('plugins/mvm-hub4-rc-direct/src/class-app.php', 'utf8');
const hubServices = fs.readFileSync('plugins/mvm-hub4-rc-direct/assets/platform-services-v1.js', 'utf8');

assert.match(ads, /POST_TYPE\s*=\s*'mvm_local_ad'/, 'business ads must use a dedicated post type');
assert.ok(!ads.includes('mvm_listing'), 'business ads must remain separate from Marktplaats listings');
assert.match(ads, /Advertentie[^<]*<span[^>]*>·<\/span> Lokale ondernemer/, 'every rendered block must be visibly labelled as an advertisement');
assert.ok(ads.includes('rel="sponsored nofollow noopener noreferrer"'), 'outbound advertiser links must be sponsored/nofollow and opener-safe');
assert.match(ads, /return array\( 'draft', 'active', 'paused', 'ended', 'suspended' \)/, 'staff suspension must be a first-class status');
for (const placement of ['home_banner', 'home_stream', 'sidebar', 'article_inline', 'event_inline', 'encyclopedia_inline', 'before_footer']) {
    assert.ok(ads.includes(placement), `missing placement ${placement}`);
}
assert.match(ads, /'post' === \$post_type/);
assert.match(ads, /'event_listing' === \$post_type/);
assert.match(ads, /mvm_encyclopedie/);
assert.match(ads, /add_shortcode\(\s*'mvm_local_ad'/);
assert.match(widget, /MvM · Lokale ondernemer advertentie/);
assert.match(widget, /MvM_Local_Business_Ads::render/);

assert.match(caps, /LOCAL_ADS_ADMIN\s*=\s*'mvm_local_ads_admin'/, 'full local-ad CRUD needs its own narrow capability');
assert.match(rest, /LOCAL_ADS_MANAGE/, 'staff oversight must retain the moderation capability');
assert.match(rest, /LOCAL_ADS_ADMIN/, 'SysOp/Admin CRUD must use the admin-only capability');
assert.match(rest, /function can_admin\(/);
assert.match(rest, /'callback'\s*=>\s*array\( __CLASS__, 'create' \)/, 'admin route must support creating ads');
assert.match(rest, /'callback'\s*=>\s*array\( __CLASS__, 'update' \)/, 'admin route must support editing ads');
assert.match(rest, /'callback'\s*=>\s*array\( __CLASS__, 'trash' \)/, 'admin route must support deleting ads');
assert.match(rest, /permission_callback'\s*=>\s*array\( __CLASS__, 'can_admin' \)/, 'write routes must be admin-capability gated');
assert.match(rest, /\/local-ads\/\(\?P<id>\\d\+\)\/moderate/, 'staff must keep a dedicated moderation route');
assert.match(rest, /moderation_action/);
assert.match(rest, /array\( 'suspend', 'restore' \)/);
assert.match(rest, /from_status/);
assert.match(rest, /to_status/);
assert.match(rest, /'reason'\s*=>\s*\$reason/, 'moderation reason must reach the audit context');
assert.match(rest, /admin_created/);
assert.match(rest, /admin_updated/);
assert.match(rest, /admin_trashed/);
assert.match(rest, /resolve_owner/);
assert.match(rest, /LOCAL_ADS_SELF_MANAGE/, 'admin may assign an ad only to a valid entrepreneur owner');
assert.match(rest, /no_store_private/);

assert.match(entrepreneurRest, /LOCAL_ADS_SELF_MANAGE/, 'entrepreneur self service must use its own narrow capability');
assert.match(entrepreneurRest, /post_author'\s*=>\s*get_current_user_id\(\)/, 'entrepreneur list must be owner-scoped');
assert.match(entrepreneurRest, /\(int\) \$post->post_author === get_current_user_id\(\)/, 'entrepreneur mutations must verify ownership');
assert.match(entrepreneurRest, /'suspended' === \(string\) \$current\['status'\]/, 'entrepreneur update must fail closed on staff suspension');
assert.match(entrepreneurRest, /array\( 'draft', 'paused', 'ended' \)/, 'organization self service must be review-first and may never self-select active or suspended');
assert.ok(!/array\( 'draft', 'active', 'paused', 'ended' \)/.test(entrepreneurRest), 'organization self service must not expose active as a self-selectable status');
assert.match(entrepreneurRest, /mvm_entrepreneur_ad_suspended/);
assert.match(portal, /ondernemers\/advertentiecentrum/);
assert.match(portal, /LOCAL_ADS_SELF_MANAGE/);
assert.match(offers, /\^aanbiedingen\/\?\$/);
assert.match(offers, /'active' !== \$data\['status'\]/);

for (const required of [
    'class-local-business-ads.php',
    'class-local-business-ad-widget.php',
    'class-local-business-ads-rest.php',
    'class-entrepreneur-ads-rest.php',
    'class-entrepreneur-portal.php',
    'class-offers-page.php'
]) assert.ok(bootstrap.includes(required), `bootstrap missing ${required}`);
assert.match(platform, /MvM_Entrepreneur_Portal::boot\(\)/);
assert.match(platform, /MvM_Offers_Page::boot\(\)/);
assert.match(platform, /MvM_Entrepreneur_Ads_REST/);

assert.match(hubApp, /'local_ads'\s*,\s*'label'\s*=>\s*'Ondernemersadvertenties'/, 'Hub must expose a separate local ads service');
assert.match(hubApp, /MvM_Platform_Capabilities::LOCAL_ADS_MANAGE/, 'Hub local ads service must require staff oversight capability');
assert.ok(!hubApp.includes('LOCAL_ADS_SELF_MANAGE'), 'Hub must not use entrepreneur self-service capability');
assert.match(hubServices, /local_ads:\s*'Ondernemersadvertenties'/);
assert.match(hubServices, /renderLocalAds/);
assert.match(hubServices, /api\('local-ads'\)/);
assert.match(hubServices, /local-ads\/\$\{item\.id\}\/moderate/);
assert.match(hubServices, /moderation_action:\s*'suspend'/, 'Hub staff UI must suspend through moderation API');
assert.match(hubServices, /moderation_action:\s*'restore'/, 'Hub staff UI must restore through moderation API');
assert.match(hubServices, /reason:\s*reason\.trim\(\)/, 'Hub moderation reason must be submitted for auditing');
assert.ok(!hubServices.includes("api('entrepreneur/"), 'Hub staff UI must never call entrepreneur self-service routes');

assert.match(css, /--mvm-ad-blue:\s*#1966AE/i, 'MvM brand blue must anchor the component');
assert.match(css, /border-radius:\s*16px/);
assert.match(css, /@media \(max-width: 700px\)/);
assert.match(css, /data-theme=\\?"dark\\?"/);
assert.match(css, /mvm-local-ad--compact/);
assert.match(css, /mvm-local-ad--inline/);
assert.match(entrepreneurCss, /--mvm-entrepreneur-blue:\s*#1966AE/i);
assert.match(entrepreneurCss, /@media \(max-width: 700px\)/);
assert.ok(!entrepreneurJs.includes('innerHTML'), 'entrepreneur API data must not be rendered with innerHTML');
assert.match(entrepreneurJs, /X-WP-Nonce/);
assert.match(entrepreneurJs, /credentials:\s*'same-origin'/);
assert.match(entrepreneurJs, /cache:\s*'no-store'/);
assert.match(entrepreneurJs, /Geschorst door MvM/);

const combined = [ads, rest, entrepreneurRest, portal, offers, widget, entrepreneurJs, hubServices].join('\n').toLowerCase();
for (const forbidden of ['google analytics', 'gtag(', 'facebook pixel', 'fbq(', 'doubleclick', 'trackingpixel', 'click_id', 'utm_source=']) {
    assert.ok(!combined.includes(forbidden), `local ads must not contain tracking primitive: ${forbidden}`);
}

console.log('MvM local business ads contract: OK');
