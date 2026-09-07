'use strict';

const fs = require('fs');
const path = require('path');

const root = path.resolve(__dirname, '..');
const pluginRoot = path.join(root, 'plugins', 'mvm-encyclopedie-next');
const read = (relative) => fs.readFileSync(path.join(pluginRoot, relative), 'utf8');

const bootstrap = read('mvm-encyclopedie-next.php');
const model = read('src/class-content-model.php');
const query = read('src/class-query.php');
const visibility = read('src/class-visibility.php');
const hotlinks = read('src/class-hotlinks.php');
const frontend = read('src/class-frontend.php');
const themeGroups = read('src/class-theme-groups.php');
const pageTemplateClass = read('src/class-page-template.php');
const pageTemplate = read('templates/page.php');
const single = read('templates/single.php');
const archive = read('templates/archive.php');
const js = read('assets/frontend.js');
const css = read('assets/frontend.css');
const allPhp = [bootstrap, model, query, visibility, hotlinks, frontend, themeGroups, pageTemplateClass, pageTemplate, single, archive].join('\n');

function assert(condition, message) {
    if (!condition) {
        throw new Error(message);
    }
}

assert(/final class MVM_Encyclopedie\b/.test(bootstrap), 'Standalone runtime must expose canonical MVM_Encyclopedie marker class.');
assert(bootstrap.includes('MVM_Encyclopedie_Next_Content_Model::register_all()'), 'Activation must register the canonical content model before rewrite flush.');
assert(bootstrap.includes('MVM_Encyclopedie_Next_Visibility::init()'), 'Standalone runtime must initialize the public visibility boundary.');
assert(bootstrap.includes('MVM_Encyclopedie_Next_Hotlinks::init()'), 'Standalone runtime must initialize bounded dossier hotlinking.');
assert(bootstrap.includes('MVM_Encyclopedie_Next_Theme_Groups::init()'), 'Standalone runtime must initialize canonical theme grouping.');
assert(bootstrap.includes('MVM_Encyclopedie_Next_Page_Template::init()'), 'Standalone runtime must own lightweight canonical encyclopedia page templates.');
assert(model.includes("'show_in_menu' => 'mvm-encyclopedie'"), 'Standalone content types must preserve the Hub encyclopedia admin-menu placement.');
assert(pageTemplateClass.includes('self::HOME_PAGE_ID, self::THEMES_PAGE_ID'), 'Page-template ownership must include the canonical encyclopedia landing pages.');
assert(pageTemplateClass.includes('get_post_ancestors( $post_id )'), 'Page-template ownership must include canonical editorial theme descendants.');
assert(pageTemplate.includes('the_content();'), 'Canonical page template must render stored WordPress page content/shortcodes.');
assert(!pageTemplate.includes('the_title('), 'Canonical page template must not duplicate shortcode-owned H1 titles.');

const rewrites = [
    'encyclopedie/artikel',
    'encyclopedie/personen',
    'encyclopedie/locaties',
    'encyclopedie/gebouwen',
    'encyclopedie/gebeurtenissen',
    'encyclopedie/verenigingen',
    'encyclopedie/bedrijven',
    'encyclopedie/beeldbank',
    'encyclopedie/bronnen',
    'encyclopedie/thema',
    'encyclopedie/periode',
    'encyclopedie/status',
    'encyclopedie/gebied'
];
rewrites.forEach((rewrite) => assert(model.includes(`'${rewrite}'`), `Missing legacy-compatible rewrite: ${rewrite}`));

['mvm_public_home', 'mvm_themas', 'mvm_thema_page'].forEach((shortcode) => {
    assert(frontend.includes(`'${shortcode}'`), `Missing legacy page shortcode bridge: ${shortcode}`);
});

const canonicalThemePages = [
    'oorsprong-archeologie',
    'heren-heerlijkheid-kasteel',
    'bestuur-recht-gemeente',
    'kerk-geloof-religie',
    'oorlog-bevrijding-herdenken',
    'landbouw-kersen-platteland',
    'ambachten-textiel-economie',
    'onderwijs-jeugd',
    'zorg-sociaal-leven',
    'cultuur-tradities-identiteit',
    'verenigingen-sport-vrije-tijd',
    'natuur-landschap-water',
    'verkeer-infrastructuur-nutsvoorzieningen',
    'gebouwen-monumenten-erfgoed',
    'buurten-straten-geografie',
    'mierlo-hout-brandevoort',
    'personen-dorpsverhalen',
    'onderzoek-bronnen-publicaties'
];
canonicalThemePages.forEach((slug) => assert(themeGroups.includes(`'${slug}' => array(`), `Missing canonical theme-page group: ${slug}`));
const topLevelThemeGroups = themeGroups.match(/^            '[^']+' => array\(/gm) || [];
assert(topLevelThemeGroups.length === 18, `Theme-group bridge must expose exactly the 18 canonical editorial groups; found ${topLevelThemeGroups.length}.`);
assert(themeGroups.includes("'taxonomy' => 'mvm_thema'"), 'Theme groups must query the existing mvm_thema knowledge layer.');
assert(themeGroups.includes("'field' => 'slug'"), 'Theme groups must resolve stored taxonomy slugs without creating terms.');
assert(themeGroups.includes("'operator' => 'IN'"), 'Theme groups must combine their existing terms with OR/IN semantics.');
assert(themeGroups.includes("'per_page' => 24"), 'Theme-group dossier lists must remain paginated at 24 items.');

assert(/MAX_PER_PAGE\s*=\s*24/.test(query), 'Public search must remain bounded to at most 24 items per request.');
assert(query.includes("'_mvm_relations'"), 'Relations must use the existing pre-indexed _mvm_relations field.');
assert(query.includes("WP_REST_Server::READABLE"), 'REST endpoints must be read-only.');
assert(!query.includes("WP_REST_Server::CREATABLE"), 'Public encyclopedia REST must not expose create routes.');
assert(!query.includes("WP_REST_Server::EDITABLE"), 'Public encyclopedia REST must not expose update routes.');
assert(!query.includes("WP_REST_Server::DELETABLE"), 'Public encyclopedia REST must not expose delete routes.');
assert(query.includes("'1' === (string) get_post_meta( $post_id, '_mvm_public_hidden', true )"), 'Hidden dossier relation REST source must fail closed.');

assert(visibility.includes("'_mvm_public_hidden'"), 'Visibility guard must enforce the existing public-hidden marker.');
assert(visibility.includes("$query->is_post_type_archive"), 'Visibility guard must cover canonical post type archives.');
assert(visibility.includes("$query->is_tax"), 'Visibility guard must cover encyclopedia taxonomy archives.');
assert(visibility.includes("'relation' => 'AND'"), 'Archive visibility guard must preserve and combine any existing meta query.');
assert(visibility.includes("'template_redirect'"), 'Hidden singular dossiers must be blocked after WordPress resolves the canonical URL.');
assert(visibility.includes('is_singular( MVM_Encyclopedie_Next_Content_Model::post_type_slugs() )'), 'Hidden singular boundary must cover every encyclopedia content type.');
assert(visibility.includes('$wp_query->set_404()'), 'Hidden singular dossiers must set the WordPress query to 404.');
assert(visibility.includes('status_header( 404 )'), 'Hidden singular dossiers must return HTTP 404.');
assert(visibility.includes('nocache_headers()'), 'Hidden singular 404 responses must not be cached as public pages.');

assert(/MAX_RELATION_TARGETS\s*=\s*48/.test(hotlinks), 'Hotlinking must inspect at most 48 already-related dossiers.');
assert(/MAX_ALIASES\s*=\s*120/.test(hotlinks), 'Hotlinking alias expansion must remain bounded.');
assert(/MAX_LINKS\s*=\s*6/.test(hotlinks), 'A dossier may receive at most six automatic inline hotlinks.');
assert(hotlinks.includes("'_mvm_aliases'"), 'Hotlinking must reuse stored aliases instead of a global runtime scan.');
assert(hotlinks.includes('MVM_Encyclopedie_Next_Query::relation_ids'), 'Hotlink candidates must be constrained to stored dossier relations.');
assert(hotlinks.includes("'a', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6'"), 'Hotlinking must avoid existing links and headings.');

const forbiddenWrites = [
    'wp_insert_post(',
    'wp_update_post(',
    'wp_delete_post(',
    'update_post_meta(',
    'add_post_meta(',
    'delete_post_meta(',
    'wp_set_object_terms(',
    'wp_remote_get(',
    'wp_remote_post(',
    "'posts_per_page' => -1",
    '"posts_per_page" => -1'
];
forbiddenWrites.forEach((needle) => assert(!allPhp.includes(needle), `Forbidden public-runtime pattern found: ${needle}`));

assert(frontend.includes("'posts_per_page' => 8"), 'Homepage discovery list must stay compact.');
assert(single.includes("relations( (int) $post_object->ID, 0, 12 )"), 'Dossier pages must initially load only 12 relations.');
assert(archive.includes("number_format_i18n( $total )"), 'Archive template must expose bounded-query result count.');
assert(frontend.includes('loading="lazy"'), 'Card imagery must lazy-load.');
assert(frontend.includes('decoding="async"'), 'Card imagery must decode asynchronously.');
assert(js.includes("url.searchParams.set('per_page', '6')"), 'Live search must request only six suggestions.');
assert(js.includes("url.searchParams.set('per_page', '12')"), 'Relation expansion must request bounded 12-item batches.');
assert(js.includes("input.setAttribute('role', 'combobox')"), 'Live search must expose combobox semantics.');
assert(js.includes("results.setAttribute('role', 'listbox')"), 'Live search must expose listbox semantics.');
assert(js.includes("input.setAttribute('aria-activedescendant'"), 'Keyboard live search must expose its active option.');
assert(js.includes("event.key === 'ArrowDown'"), 'Live search must support ArrowDown keyboard navigation.');
assert(js.includes("event.key === 'Escape'"), 'Live search must support Escape dismissal.');
assert(js.includes("button.setAttribute('aria-busy', 'true')"), 'Lazy relation loading must expose busy state to assistive technology.');
assert(!js.includes('.innerHTML'), 'Client rendering must not inject REST payloads through innerHTML.');
assert(css.includes('--mvm-e3-blue: #1966AE'), 'MvM primary blue must remain #1966AE.');
assert(css.includes('body.dark-mode .mvm-encyclopedie-next'), 'Dark mode must remain compatible with the current MvM body state.');
assert(css.includes('[data-theme="dark"] .mvm-encyclopedie-next'), 'Dark mode must support the data-theme state.');
assert(css.includes('content-visibility: auto'), 'Heavy below-fold sections should use content-visibility optimization.');

console.log('Encyclopedie Next contract: OK');
