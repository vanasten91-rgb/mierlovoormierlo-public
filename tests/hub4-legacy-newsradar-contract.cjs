'use strict';

const fs = require('fs');
const path = require('path');

const root = path.resolve(__dirname, '..');
const restPath = path.join(root, 'plugins/mvm-hub4-rc-direct/src/class-newsroom-rest.php');
const adapterPath = path.join(root, 'plugins/mvm-hub4-rc-direct/src/class-legacy-newsradar.php');
const writesPath = path.join(root, 'plugins/mvm-hub4-rc-direct/src/class-legacy-newsradar-writes.php');
const capsPath = path.join(root, 'plugins/mvm-hub4-rc-direct/src/class-capabilities.php');
const appPath = path.join(root, 'plugins/mvm-hub4-rc-direct/templates/app.php');
const uiPath = path.join(root, 'plugins/mvm-hub4-rc-direct/assets/news-radar-v1.js');
const cssPath = path.join(root, 'plugins/mvm-hub4-rc-direct/assets/news-radar-v1.css');

const rest = fs.readFileSync(restPath, 'utf8');
const adapter = fs.readFileSync(adapterPath, 'utf8');
const writes = fs.readFileSync(writesPath, 'utf8');
const caps = fs.readFileSync(capsPath, 'utf8');
const app = fs.readFileSync(appPath, 'utf8');
const ui = fs.readFileSync(uiPath, 'utf8');
const css = fs.readFileSync(cssPath, 'utf8');

function assert(condition, message) {
  if (!condition) {
    console.error(`FAIL: ${message}`);
    process.exit(1);
  }
}

const getRouteStart = rest.indexOf("'/news-radar'");
assert(getRouteStart >= 0, 'Hub4 must register /news-radar');
const getRouteSlice = rest.slice(getRouteStart, rest.indexOf(');', getRouteStart) + 2);
assert(getRouteSlice.includes('WP_REST_Server::READABLE'), '/news-radar GET must remain read-only');
assert(!getRouteSlice.includes('WP_REST_Server::CREATABLE'), '/news-radar collection must not accept writes');
assert(!getRouteSlice.includes('WP_REST_Server::EDITABLE'), '/news-radar collection must not accept edits');
assert(getRouteSlice.includes('MvM_Hub4_Capabilities::NEWS_VIEW'), '/news-radar GET must require NEWS_VIEW');
assert(getRouteSlice.includes('MvM_Hub4_Security::require_capability'), '/news-radar GET must use Hub4 session/capability security');

const reviewRouteStart = rest.indexOf("'/news-radar/(?P<id>[a-f0-9]{64})/reviewed'");
assert(reviewRouteStart >= 0, 'Hub4 must register the narrow /reviewed write route');
const reviewRouteSlice = rest.slice(reviewRouteStart, rest.indexOf(');', reviewRouteStart) + 2);
assert(reviewRouteSlice.includes('WP_REST_Server::CREATABLE'), '/reviewed must be POST/creatable');
assert(!reviewRouteSlice.includes('WP_REST_Server::DELETABLE'), '/reviewed must never delete');
assert(reviewRouteSlice.includes('MvM_Hub4_Capabilities::NEWS_REVIEW'), '/reviewed must require NEWS_REVIEW');
assert(reviewRouteSlice.includes('MvM_Hub4_Security::require_capability'), '/reviewed must use Hub4 browser-session security');
assert(reviewRouteSlice.includes("preg_match( '/^[a-f0-9]{64}$/'"), '/reviewed must validate exact 64-char hex IDs');

assert(rest.includes("'readOnly'    => true"), 'GET response must explicitly advertise its read-only collection semantics');
assert(rest.includes("'canReview' => $can_review"), 'GET response must expose capability-derived review permission');
assert(rest.includes("'news.radar_view'"), 'reads must be audit logged');
assert(rest.includes("'news.radar_review'"), 'review writes must be audit logged');
assert(rest.includes('MvM_Hub4_Legacy_Newsradar_Writes::mark_reviewed'), 'review route must use the isolated legacy-write service');
assert(rest.includes('MvM_Hub4_Legacy_Newsradar::grouped_results()'), 'route must use grouped legacy adapter');
assert(rest.includes('MvM_Hub4_Legacy_Newsradar::snapshot()'), 'route must expose non-sensitive legacy snapshot');
assert(rest.includes('MvM_Hub4_Legacy_Newsradar::sources()'), 'route must expose allow-listed monitoring sources');

assert(!/\bupdate_option\s*\(/.test(adapter), 'read adapter must never mutate options');
assert(!/\bdelete_option\s*\(/.test(adapter), 'read adapter must never delete options');
assert(!/\badd_option\s*\(/.test(adapter), 'read adapter must never create options');
assert(!/\bwp_remote_(get|post|request)\s*\(/.test(adapter), 'read adapter must never crawl external sources');
assert(!/api\.openai\.com/i.test(adapter), 'read adapter must never call OpenAI');
assert(!/wp_schedule_(event|single_event)\s*\(/.test(adapter), 'read adapter must never schedule crawler work');
assert(adapter.includes("'article:' . $host . ':' . $match[1]"), 'numeric article identity must align with legacy tombstones');
assert(adapter.includes("'last_checked'"), 'source allowlist must include last checked time');
assert(adapter.includes("'next_check'"), 'source allowlist must include next check time');
assert(!adapter.match(/array\([^)]*'note'[^)]*\)/s), 'source allowlist must not expose internal notes');

assert(writes.includes("private const OPTION_LOCK = 'mvm_bh_news_radar_lock'"), 'review service must inspect the authoritative crawler lock');
assert(writes.includes('public static function crawler_busy()'), 'review service must fail closed against a live crawler lock');
assert(writes.includes("preg_match( '/^[a-f0-9]{64}$/'"), 'write service must revalidate the result ID');
assert(/\$row\['reviewed'\]\s*=\s*1/.test(writes), 'review service may set only the legacy reviewed state');
assert(/\$row\['reviewed_at'\]\s*=\s*wp_date\( DATE_ATOM \)/.test(writes), 'review service must use WordPress-local timestamp semantics');
assert(/\$row\['reviewed_by'\]\s*=\s*get_current_user_id\(\)/.test(writes), 'review service must attribute the current user');
assert(writes.includes("if ( ! empty( $row['reviewed'] ) )"), 'review action must be idempotent');
assert(writes.includes('option_value = %s WHERE option_name = %s AND option_value = %s'), 'production review write must use optimistic compare-and-swap');
assert(writes.includes("'mvm_news_radar_conflict'"), 'concurrent legacy changes must fail closed with conflict');
assert(!/NEWS_TOMBSTONES_OPTION|mvm_bh_news_radar_tombstones/.test(writes), 'review service must not touch tombstones');
assert(!/NEWS_DETAIL_QUEUE_OPTION|mvm_bh_news_radar_detail_queue/.test(writes), 'review service must not touch detail queue');
assert(!/mvm_bh_news_radar_tracks/.test(writes), 'review service must not touch tracks');
assert(!/\bdelete_option\s*\(/.test(writes), 'review service must not delete legacy options');
assert(!/\bwp_remote_(get|post|request)\s*\(/.test(writes), 'review service must not make network requests');
assert(!/wp_schedule_(event|single_event)\s*\(/.test(writes), 'review service must not schedule crawler work');
assert(!/api\.openai\.com/i.test(writes), 'review service must not call OpenAI');

assert(caps.includes("public const NEWS_REVIEW          = 'mvm_hub4_news_review'"), 'NEWS_REVIEW capability must exist');
assert(caps.includes('private const SCHEMA_VERSION = 4;'), 'capability schema must be bumped for NEWS_REVIEW');

assert(app.includes('data-mvm-news-radar-view'), 'Hub4 template must expose a dedicated Nieuwsradar navigation button');
assert(app.includes('MvM_Hub4_Capabilities::NEWS_VIEW'), 'Nieuwsradar navigation must be gated by NEWS_VIEW');
assert(app.includes('assets/news-radar-v1.js'), 'Hub4 template must load Nieuwsradar JS');
assert(app.includes('assets/news-radar-v1.css'), 'Hub4 template must load Nieuwsradar CSS');
assert(app.includes('Nieuwsradar'), 'Nieuwsradar must have its own visible label separate from Mierlo-radar');

assert(ui.includes("api('news-radar?limit=200')"), 'UI must read from the GET Nieuwsradar endpoint');
assert(ui.includes("options.method || 'GET'"), 'read helper must default explicitly to GET');
assert(ui.includes("method: 'POST'"), 'UI must expose the guarded review POST');
assert(ui.includes("news-radar/${encodeURIComponent(normalized)}/reviewed"), 'review write target must remain the reviewed subroute');
assert(ui.includes("api('agenda-crawl/settings', { method: 'POST'"), 'AI-agendacrawl settings must use its allow-listed POST route');
assert(ui.includes("api('agenda-crawl/run', { method: 'POST'"), 'AI-agendacrawl proefrun must use its allow-listed POST route');
assert(!/method\s*:\s*['\"](?:PUT|PATCH|DELETE)['\"]/i.test(ui), 'Nieuwsradar UI must never issue PUT/PATCH/DELETE');
const postCount = (ui.match(/method:\s*'POST'/g) || []).length;
assert(postCount === 3, `Nieuwsradar UI must contain exactly three allow-listed POST paths, found ${postCount}`);
assert(ui.includes("const validResultId = (value) => /^[a-f0-9]{64}$/"), 'UI must validate result IDs before POST');
assert(ui.includes('canReview = Boolean(data?.permissions?.canReview)'), 'UI must derive review controls from server permission');
assert(ui.includes("text: 'Markeer nagekeken'"), 'UI must label the narrow review action clearly');
assert(ui.includes("badge(text, 'reviewed')"), 'reviewed rows must become status-only UI');
assert(!/\.innerHTML\s*=|insertAdjacentHTML|outerHTML\s*=/.test(ui), 'Nieuwsradar UI must not inject server strings as HTML');
assert(ui.includes("element('details'"), 'repeated detections must render in a collapsible details history');
assert(ui.includes('Updates (${updates.length})'), 'update history must clearly report grouped update count');
assert(ui.includes("['http:', 'https:'].includes(parsed.protocol)"), 'external source links must be protocol allow-listed');
assert(ui.includes("rel: 'noopener noreferrer'"), 'external source links must be isolated from the Hub window');
assert(ui.includes('notification_count'), 'UI must surface grouped notification counts');

assert(ui.includes("['priority-asc', 'Prioriteit: hoog → laag']"), 'source sorter must default to highest priority first');
assert(ui.includes("['priority-desc', 'Prioriteit: laag → hoog']"), 'source sorter must support inverse priority');
assert(ui.includes("['name-asc', 'Naam: A → Z']"), 'source sorter must support A-Z');
assert(ui.includes("['name-desc', 'Naam: Z → A']"), 'source sorter must support Z-A');
assert(ui.includes("['frequency', 'Controlefrequentie: vaakst eerst']"), 'source sorter must support frequency');
assert(ui.includes("['checked-desc', 'Laatste controle: nieuw → oud']"), 'source sorter must support latest check date/time');
assert(ui.includes("['checked-asc', 'Laatste controle: oud → nieuw']"), 'source sorter must support oldest check date/time');
assert(ui.includes("['next-asc', 'Volgende controle: eerstvolgende eerst']"), 'source sorter must support next check');
assert(ui.includes("['arrival-desc', 'Binnenkomst: nieuwste eerst']"), 'source sorter must support newest arrival');
assert(ui.includes("['arrival-asc', 'Binnenkomst: oudste eerst']"), 'source sorter must support oldest arrival');
assert(ui.includes("Crawler verwerkt A → B → C"), 'UI must communicate crawler priority order');

assert(css.includes('.mvm-news-radar__history'), 'Nieuwsradar CSS must style update history');
assert(css.includes('.mvm-news-radar__metrics'), 'Nieuwsradar CSS must style summary metrics');
assert(css.includes('.mvm-news-radar__source-list'), 'Nieuwsradar CSS must style monitoring source list');
assert(css.includes('.mvm-news-radar__sort'), 'Nieuwsradar CSS must style source sort control');
assert(css.includes('.mvm-news-radar__review-button'), 'Nieuwsradar CSS must style review controls');
assert(css.includes('.mvm-news-radar__badge--reviewed'), 'Nieuwsradar CSS must style reviewed state');
assert(css.includes(':focus-visible'), 'review control must have keyboard focus styling');
assert(css.includes('@media(max-width:640px)'), 'Nieuwsradar UI must include mobile hardening');

console.log('Hub 4 legacy Nieuwsradar read/review contract: all assertions passed.');
