'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const root = path.resolve(__dirname, '..');
const read = (relative) => fs.readFileSync(path.join(root, relative), 'utf8');

const bootstrap = read('plugins/mvm-platform/bootstrap.php');
const capabilities = read('plugins/mvm-platform/src/class-capabilities.php');
const marketplace = read('plugins/mvm-platform/src/marketplace/class-marketplace.php');
const newsletter = read('plugins/mvm-platform/src/newsletter/class-newsletter.php');
const newsletterFrontend = read('plugins/mvm-platform/src/newsletter/class-newsletter-frontend.php');
const newsletterLegacyCompat = read('plugins/mvm-platform/src/newsletter/class-newsletter-legacy-compat.php');
const localAds = read('plugins/mvm-platform/src/local-ads/class-local-business-ads.php');
const entrepreneurPortal = read('plugins/mvm-platform/src/local-ads/class-entrepreneur-portal.php');
const offers = read('plugins/mvm-platform/src/local-ads/class-offers-page.php');
const examples = read('plugins/mvm-platform/src/local-ads/class-ad-examples-page.php');
const settings = read('plugins/mvm-platform/src/local-ads/class-ad-settings-page.php');

let assertions = 0;
const check = (condition, message) => {
  assert.ok(condition, message);
  assertions += 1;
};

for (const owner of [
  'MvM_Platform_Capabilities::activate();',
  'MvM_Marketplace::activate();',
  'MvM_Newsletter::activate();',
  'MvM_Newsletter_Frontend::activate();',
  'MvM_Local_Business_Ads::activate();',
  'MvM_Entrepreneur_Portal::activate();',
  'MvM_Offers_Page::activate();',
  'MvM_Ad_Examples_Page::activate();',
  'MvM_Ad_Settings_Page::activate();',
]) {
  check(bootstrap.includes(owner), `Platform activation must keep explicit owner ${owner}`);
}

check(
  !/\b(?:wp_delete_post|wp_delete_term|remove_role|DROP\s+TABLE|TRUNCATE\s+TABLE)\b/i.test(bootstrap),
  'top-level activation/deactivation orchestrator must not delete persistent data'
);

for (const cap of [
  'mvm_local_ads_self_manage',
  'mvm_marketplace_moderate',
  'mvm_newsletter_manage',
  'mvm_pwa_manage',
  'mvm_local_ads_manage',
  'mvm_local_ads_admin',
]) {
  check(capabilities.includes(cap), `managed capability ${cap} must remain explicit`);
}

check(
  /public\s+const\s+VERSION\s*=\s*'5'\s*;/.test(capabilities),
  'capability schema version must remain pinned to 5 for this cutover baseline'
);

const managedCapsMatch = capabilities.match(/\$managed_caps\s*=\s*array\s*\(([\s\S]*?)\n\s*\);/);
check(Boolean(managedCapsMatch), 'capability sync must keep an explicit local managed-cap list');
const managedCapRefs = managedCapsMatch
  ? [...managedCapsMatch[1].matchAll(/self::([A-Z0-9_]+)/g)].map((match) => match[1]).sort()
  : [];
const expectedManagedCapRefs = [
  'LOCAL_ADS_ADMIN',
  'LOCAL_ADS_MANAGE',
  'LOCAL_ADS_SELF_MANAGE',
  'MARKETPLACE_MODERATE',
  'NEWSLETTER_MANAGE',
  'PWA_MANAGE',
].sort();
check(
  JSON.stringify(managedCapRefs) === JSON.stringify(expectedManagedCapRefs),
  'capability sync must remain bounded to exactly the six Platform-managed capabilities'
);
check(
  /foreach\s*\(\s*\$managed_caps\s+as\s+\$capability\s*\)/.test(capabilities),
  'capability reconciliation must iterate only the explicit managed-cap list'
);
check(capabilities.includes("$role->add_cap( 'read' )") && capabilities.includes('$role->add_cap( self::LOCAL_ADS_SELF_MANAGE )'), 'existing organization roles must be augmented, not replaced');
check(!capabilities.includes('remove_role('), 'capability sync must not delete roles');

check(marketplace.includes("if ( ! term_exists( $category, self::TAXONOMY ) )"), 'marketplace defaults must be inserted only when missing');
check(marketplace.includes("if ( ! wp_next_scheduled( self::EXPIRE_HOOK ) )"), 'marketplace expiry cron must be idempotently scheduled');
check(marketplace.includes("wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', self::EXPIRE_HOOK )"), 'marketplace expiry cadence must remain hourly');
check(marketplace.includes('wp_clear_scheduled_hook( self::EXPIRE_HOOK )'), 'marketplace deactivation must clear only its own scheduler');

check(newsletter.includes("DB_VERSION        = '1'"), 'newsletter DB schema baseline must remain version 1 for this cutover');
for (const table of ['mvm_newsletter_subscribers', 'mvm_newsletter_campaigns', 'mvm_newsletter_queue']) {
  check(newsletter.includes(table), `newsletter table ${table} must remain explicitly owned`);
}
check(newsletter.includes('dbDelta('), 'newsletter table installation must remain schema-safe via dbDelta');
check(newsletter.includes("if ( ! wp_next_scheduled( self::CRON_HOOK ) )"), 'newsletter dispatch cron must be idempotently scheduled');
check(newsletter.includes("wp_schedule_event( time() + MINUTE_IN_SECONDS, 'mvm_five_minutes', self::CRON_HOOK )"), 'newsletter dispatch cadence must remain five minutes');
check(newsletter.includes('wp_clear_scheduled_hook( self::CRON_HOOK )'), 'newsletter deactivation must clear only its own scheduler');

for (const source of [newsletterFrontend, localAds, entrepreneurPortal, offers, examples, settings]) {
  check(!/\bwp_insert_post\s*\(/.test(source), 'route/presentation activators must not create WordPress pages/posts during activation');
  check(!/\b(?:wp_delete_post|delete_option|remove_role|DROP\s+TABLE|TRUNCATE\s+TABLE)\b/i.test(source), 'route/presentation activators must not delete persistent data');
}

check(newsletterFrontend.includes("add_rewrite_rule( '^nieuwsbrief/?$'"), 'newsletter route owner must remain rewrite-based rather than page-creation based');
check(newsletterFrontend.includes("if ( function_exists( 'mvm_newsletter_table_v1' ) )"), 'newsletter rewrites must remain dormant while the legacy production runtime is active');
check(
  newsletterFrontend.indexOf("if ( function_exists( 'mvm_newsletter_table_v1' ) )") < newsletterFrontend.indexOf("add_rewrite_rule( '^nieuwsbrief/?$'"),
  'legacy newsletter coexistence guard must execute before Platform claims /nieuwsbrief/'
);
check(newsletterLegacyCompat.includes("if ( function_exists( 'mvm_newsletter_table_v1' ) )"), 'legacy compatibility adapter and route owner must share the same production-runtime sentinel');
check(entrepreneurPortal.includes("add_rewrite_rule( '^ondernemers/advertentiecentrum/?$'"), 'entrepreneur portal must remain rewrite-based');
check(offers.includes("add_rewrite_rule( '^aanbiedingen/?$'"), 'offers page must remain rewrite-based');
check(examples.includes('add_rewrite_rule(') && settings.includes('add_rewrite_rule('), 'ad example/settings pages must remain rewrite-based');

console.log(`Platform activation contract: ${assertions} assertions passed.`);
