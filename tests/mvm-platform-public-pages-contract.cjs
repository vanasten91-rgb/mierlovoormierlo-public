'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const root = path.resolve(__dirname, '..');
const read = (relative) => fs.readFileSync(path.join(root, relative), 'utf8');

const bootstrap = read('plugins/mvm-platform/bootstrap.php');
const platform = read('plugins/mvm-platform/src/class-platform.php');
const offers = read('plugins/mvm-platform/src/local-ads/class-offers-page.php');
const marketplaceFrontend = read('plugins/mvm-platform/src/marketplace/class-marketplace-frontend.php');

let assertions = 0;
const check = (condition, message) => {
  assert.ok(condition, message);
  assertions += 1;
};

check(bootstrap.includes("'marktplaats' => array("), 'activation must own a Marktplaats page definition');
check(bootstrap.includes("'content' => '[mvm_marktplaats]'"), 'Marktplaats page must render the marketplace shortcode');
check(bootstrap.includes("'aanbiedingen' => array("), 'activation must own an Aanbiedingen page definition');
check(bootstrap.includes("'content' => '[mvm_aanbiedingen]'"), 'Aanbiedingen page must render the offers shortcode');
check(bootstrap.includes('self::ensure_public_pages();'), 'page creation must run before feature activation');
check(bootstrap.includes("'post_status'  => 'publish'"), 'missing public pages must be created published');
check(bootstrap.includes("get_page_by_path( $slug, OBJECT, 'page' )"), 'page creation must be idempotent by slug');
check(bootstrap.includes('wp_insert_post('), 'activation must be able to create only missing page records');

for (const owner of [
  'MvM_Marketplace::activate();',
  'MvM_Offers_Page::activate();',
  'MvM_Marketplace::boot();',
  'MvM_Marketplace_Moderation::boot();',
  'MvM_Marketplace_Frontend::boot();',
  "array( 'MvM_Marketplace_REST', 'register_routes' )",
]) {
  check(bootstrap.includes(owner) || platform.includes(owner), `feature owner must stay enabled: ${owner}`);
}

check(marketplaceFrontend.includes("add_shortcode( 'mvm_marktplaats'"), 'Marktplaats shortcode must remain registered');
check(platform.includes("$args['has_archive'] = 'marktplaats-archief';"), 'technical marketplace archive must move away from /marktplaats/ when the real page exists');
check(platform.includes("get_page_by_path( 'marktplaats', OBJECT, 'page' )"), 'marketplace routing must resolve the real page');
check(platform.includes("return (string) get_permalink( $page );"), 'marketplace archive links must point to the real Marktplaats page');

check(offers.includes("add_shortcode( 'mvm_aanbiedingen'"), 'Aanbiedingen shortcode must be registered');
check(offers.includes("get_page_by_path( 'aanbiedingen', OBJECT, 'page' )"), 'Aanbiedingen route must detect the real page');
check(
  offers.indexOf("get_page_by_path( 'aanbiedingen', OBJECT, 'page' )") < offers.indexOf("add_rewrite_rule( '^aanbiedingen/?$'"),
  'virtual Aanbiedingen rewrite may only be a fallback after checking for the real page'
);
check(offers.includes('public static function shortcode(): string'), 'Aanbiedingen must render through its page shortcode');

console.log(`Platform public pages contract: ${assertions} assertions passed.`);
