'use strict';

const fs = require('node:fs');
const path = require('node:path');

const root = path.resolve(__dirname, '..');
const read = (relative) => fs.readFileSync(path.join(root, relative), 'utf8');

const adapter = read('plugins/mvm-hub/integrations/encyclopedie/class-smart-links-target-search.php');
const controller = read('plugins/mvm-hub/modules/newsroom/news/class-smart-links-target-search-rest-controller.php');
const moduleFile = read('plugins/mvm-hub/modules/newsroom/class-newsroom-module.php');
const descriptors = read('plugins/mvm-hub/core/class-module-descriptors.php');
const bootstrap = read('plugins/mvm-hub/mvm-hub.php');

function fail(message) {
  console.error(`FAIL: ${message}`);
  process.exitCode = 1;
}
function ok(condition, message) {
  if (!condition) fail(message);
}

ok(adapter.includes("'/mvm/v1/admin/smart-links/targets'"), 'target search must reuse existing Smart Links target owner');
ok(adapter.includes('rest_do_request'), 'target search must use in-process REST, never self-HTTP');
ok(adapter.includes("$request->set_param( 'search', $query )"), 'target search must use provider-side query filtering');
ok(adapter.includes('MAX_QUERY_CHARS = 120'), 'target search must bound query length');
ok(adapter.includes('MAX_RESULTS     = 12'), 'target search must bound result count');
ok(adapter.includes("'kind'        => 'related-search'"), 'manual target results must be explicitly distinguished from automatic aliases');
ok(!/wp_update_post|wp_insert_post|update_post_meta|update_option|->\s*(?:insert|update|delete|replace)\s*\(/m.test(adapter), 'target search adapter must not mutate WordPress or Smart Links state');

ok(controller.includes("private const ROUTE     = '/newsroom/encyclopedia/targets'"), 'editorial target search route missing');
ok(controller.includes('\\WP_REST_Server::READABLE'), 'target search route must be GET-only');
ok(controller.includes('Capabilities::can_access_newsroom()'), 'target search must require Newsroom workspace access');
ok(controller.includes('Capabilities::NEWS_EDIT_OWN') && controller.includes('Capabilities::NEWS_REVIEW'), 'target search must require a news-related capability');
ok(controller.includes("$length >= 2 && $length <= 120"), 'target search REST query must be length-bounded');
ok(controller.includes('(int) $value <= 12'), 'target search REST result limit must be capped at 12');
ok(moduleFile.includes('Smart_Links_Target_Search_REST_Controller'), 'Newsroom module must register target search controller');
ok(descriptors.includes("'/mvm-hub/v1/newsroom/encyclopedia/targets'"), 'News module descriptor must expose target search as auxiliary read route');
ok(bootstrap.includes("integrations/encyclopedie/class-smart-links-target-search.php"), 'bootstrap must load target search adapter');
ok(bootstrap.includes("modules/newsroom/news/class-smart-links-target-search-rest-controller.php"), 'bootstrap must load target search controller');

if (!process.exitCode) {
  console.log('PASS: MvM Hub bounded Smart Links target search contract');
}
