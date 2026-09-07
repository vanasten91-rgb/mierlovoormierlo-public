'use strict';

const fs = require('fs');
const path = require('path');

const root = path.resolve(__dirname, '..', 'plugins', 'mvm-hub4-rc-direct');
const main = fs.readFileSync(path.join(root, 'mvm-hub4.php'), 'utf8');
const service = fs.readFileSync(path.join(root, 'src/class-news-categories.php'), 'utf8');
const js = fs.readFileSync(path.join(root, 'assets/news-categories-v1.js'), 'utf8');
const css = fs.readFileSync(path.join(root, 'assets/news-categories-v1.css'), 'utf8');

function assert(condition, message) {
  if (!condition) {
    console.error(`FAIL: ${message}`);
    process.exit(1);
  }
}

function luminance(hex) {
  const rgb = hex.match(/[0-9a-f]{2}/gi).map(v => parseInt(v, 16) / 255).map(v =>
    v <= 0.04045 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4)
  );
  return 0.2126 * rgb[0] + 0.7152 * rgb[1] + 0.0722 * rgb[2];
}

function contrast(a, b) {
  const x = luminance(a);
  const y = luminance(b);
  return (Math.max(x, y) + 0.05) / (Math.min(x, y) + 0.05);
}

assert(main.includes("src/class-news-categories.php"), 'category service must be bootstrapped');
assert(main.includes('MvM_Hub4_News_Categories::init();'), 'category service must initialize');
assert(service.includes("public const MANAGE_CAPABILITY = 'mvm_hub4_news_category_manage'"), 'category writes need a narrow custom capability');
assert(!service.includes("current_user_can( 'manage_categories' )") && !service.includes("current_user_can('manage_categories')"), 'category flow must not require broad native manage_categories');
assert(service.includes("'hide_empty' => false"), 'empty categories must get Hub pages');
assert(service.includes("'/news/categories'"), 'category list/create route must exist');
assert(service.includes("'/news/category/(?P<slug>[a-z0-9-]+)'"), 'deep-linked category news route must exist');
assert(service.includes("MvM_Hub4_Capabilities::NEWS_VIEW"), 'category reads must require news view capability');
assert(service.includes('MvM_Hub4_Security::require_hub_access()'), 'category writes must enforce Hub session boundary');
assert(service.includes('current_user_can( MvM_Hub4_Capabilities::NEWS_REVIEW )'), 'category writes must also require news review authority');
assert((service.match(/self::can_manage_categories\(\)/g) || []).length >= 1, 'write callback must repeat authorization gate');
assert(service.includes('wp_insert_term('), 'new category must use canonical WordPress taxonomy');
assert(service.includes('update_term_meta('), 'validated color key must be persisted as term metadata');
assert(service.includes('isset( $palette[ $color ] )'), 'category color must be allow-listed');
assert(service.includes("array( 'status' => 404 )"), 'unknown category must return 404');
assert(service.includes("'category_name'           => $term->slug"), 'news filtering must happen server-side by category');
assert(service.includes("'news.category_created'"), 'category creation must be audit logged');
assert(service.includes("'news.category_view'"), 'category page reads must be audit logged');
assert(service.includes("home_url( '/hub/' )"), 'deep links must target canonical /hub route');
assert(service.includes("'view' => 'news'") && service.includes("'category' => $term->slug"), 'deep links must include view and category');
assert(service.includes("str_contains( $html, 'class=\"mvm-hub4\"' )"), 'assets must only inject into final Hub4 document');
assert(service.includes("assets/news-categories-v1.css") && service.includes("assets/news-categories-v1.js"), 'category assets must be same-origin plugin files');
assert(service.includes("'veiligheid'          => 'red'"), 'live veiligheid slug must default to the red MvM accent');
assert(service.includes("'agenda'              => 'orange'"), 'live agenda slug must default to the orange MvM accent');

for (const role of ['administrator', 'mvm_sysop', 'mvm_teamleider', 'mvm_editor']) {
  assert(service.includes(`'${role}'`), `write role ${role} must be explicitly represented`);
}
for (const role of ['mvm_journalist', 'mvm_redacteur', 'mvm_moderator', 'mvm_vertaler', 'mvm_fotograaf']) {
  assert(service.includes(`'${role}'`), `excluded role ${role} must be explicitly reconciled`);
}

for (const [key, hex] of Object.entries({
  blue: '#1966AE', red: '#B42318', orange: '#A15C00', green: '#1F7A3F',
  purple: '#6D3AAE', teal: '#0F766E', pink: '#9D174D', slate: '#475569'
})) {
  assert(service.includes(`'${hex}'`), `${key} must be in the fixed PHP palette`);
  assert(css.includes(`mvm-news-category-v1--${key}`) && css.includes(hex), `${key} must have a fixed CSS accent`);
  assert(contrast(hex, '#FFFFFF') >= 4.5, `${key} accent must meet WCAG AA with white active-tab text`);
}

assert(js.includes('new URLSearchParams(window.location.search)'), 'UI must read deep-link query parameters');
assert(js.includes("url.searchParams.set('view', 'news')"), 'UI must preserve news view in deep links');
assert(js.includes("url.searchParams.set('category', slug)"), 'UI must write category deep links');
assert(js.includes("api('news/categories'"), 'UI must load/create categories through Hub REST');
assert(js.includes("method: 'POST'"), 'UI must support category creation');
assert(js.includes('MutationObserver'), 'companion UI must survive base app rerenders');
assert(js.includes('externalMutation'), 'observer must ignore its own category-shell mutations');
assert(js.includes('mvm-news-category-v1--filtered'), 'selected category must suppress the unfiltered base list');
assert(!/\binnerHTML\b|\bouterHTML\b|insertAdjacentHTML|\beval\b/.test(js), 'category UI must avoid unsafe HTML/eval sinks');
assert(!/(?:localStorage|sessionStorage)/.test(js), 'category UI must not persist sensitive state in browser storage');
assert(css.includes('@media(max-width:760px)'), 'category UI must have a mobile layout');
assert(css.includes('var(--text') && css.includes('var(--muted') && css.includes('var(--surface'), 'category UI must use Hub contrast tokens');

console.log('Hub 4 news category pages/security contract: all assertions passed.');
