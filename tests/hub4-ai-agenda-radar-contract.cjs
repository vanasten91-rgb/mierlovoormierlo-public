#!/usr/bin/env node

const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');

const root = path.resolve(__dirname, '..');
const read = (file) => fs.readFileSync(path.join(root, file), 'utf8');
const plugin = read('plugins/mvm-hub4-rc-direct/mvm-hub4.php');
const radar = read('plugins/mvm-hub4-rc-direct/src/class-ai-agenda-radar.php');
const rest = read('plugins/mvm-hub4-rc-direct/src/class-ai-agenda-radar-rest.php');
const legacy = read('plugins/mvm-hub4-rc-direct/src/class-legacy-newsradar.php');
const newsroom = read('plugins/mvm-hub4-rc-direct/src/class-newsroom-rest.php');
const health = read('plugins/mvm-hub4-rc-direct/src/class-health-check.php');
const consolidation = read('plugins/mvm-hub4-rc-direct/src/consolidated-snippets/bootstrap.php');
const ui = read('plugins/mvm-hub4-rc-direct/assets/news-radar-v1.js');
const css = read('plugins/mvm-hub4-rc-direct/assets/news-radar-v1.css');

assert.match(plugin, /Version:\s*\d+\.\d+\.\d+(?:-rc\d+)?/);
assert.match(plugin, /class-ai-agenda-radar\.php/);
assert.match(plugin, /class-ai-agenda-radar-rest\.php/);
assert.match(plugin, /MvM_Hub4_AI_Agenda_Radar::init\(\)/);
assert.match(plugin, /MvM_Hub4_AI_Agenda_Radar_REST::init\(\)/);
assert.ok(plugin.indexOf('class-ai-agenda-radar.php') < plugin.indexOf('src/consolidated-snippets/bootstrap.php'), 'native module must load before guarded snippet consolidation');

assert.match(radar, /EXPECTED_ACTIVE\s*=\s*118/);
assert.match(radar, /EXPECTED_STOPPED\s*=\s*118/);
assert.match(radar, /MAX_BATCH\s*=\s*2/);
assert.match(radar, /wp_safe_remote_get/);
assert.match(radar, /'reject_unsafe_urls'\s*=>\s*true/);
assert.match(radar, /'limit_response_size'\s*=>\s*self::MAX_BODY_BYTES/);
assert.match(radar, /wp_http_validate_url/);
assert.match(radar, /legacy_owner_active/);
assert.match(radar, /'ownership'\s*=>\s*self::legacy_owner_active\(\)\s*\?\s*'compatibility'\s*:\s*'native'/);
assert.match(radar, /preg_match\(\s*'\/\\b\(20\\d\{2\}\)\\b\/'/);
assert.match(radar, /UPDATE \{\$wpdb->options\} SET option_value = %s WHERE option_name = %s AND option_value = %s/);
assert.match(radar, /'publishesContent'\s*=>\s*false/);
assert.doesNotMatch(radar, /wp_insert_post|wp_publish_post|post_status['"]\s*=>\s*['"]publish/);
assert.doesNotMatch(radar, /AIza|sk-[A-Za-z0-9]|api[_-]?key\s*=/i);
assert.match(consolidation, /mvm_hub12_native_replacement_snippet_ids/);
assert.match(consolidation, /return array\( 503, 504, 506, 509, 513, 514, 525 \)/);
assert.match(consolidation, /class_exists\( 'MvM_Hub4_AI_Agenda_Radar' \)/);

assert.match(rest, /\/agenda-crawl\/settings/);
assert.match(rest, /\/agenda-crawl\/run/);
assert.match(rest, /SOURCE_VIEW/);
assert.match(rest, /SOURCE_MANAGE/);
assert.match(rest, /'news\.agenda_crawl_run'/);
assert.match(newsroom, /MvM_Hub4_AI_Agenda_Radar::sort_groups/);
assert.match(legacy, /'agenda_start_ts'/);
assert.match(legacy, /'ai_checked_at'/);

assert.match(health, /'ai_agenda_crawl'/);
assert.match(health, /check_ai_agenda_crawl/);
assert.match(health, /118\/118-bronbaseline/);
assert.match(health, /publiceert niets/);

assert.match(ui, /AI-agendacrawl/);
assert.match(ui, /Proefrun: 2 bronnen/);
assert.match(ui, /er wordt niets gepubliceerd/);
assert.match(ui, /agenda-crawl\/settings/);
assert.match(ui, /agenda-crawl\/run/);
assert.match(ui, /aria-live/);
assert.match(css, /#1966ae/i);
assert.match(css, /data-theme="dark"/);
assert.match(css, /focus-visible/);

console.log('AI-agendacrawl security contract: passed.');
