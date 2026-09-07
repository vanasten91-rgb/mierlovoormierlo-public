'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');

const events = fs.readFileSync('plugins/mvm-platform/src/site/class-events-overview.php', 'utf8');
const css = fs.readFileSync('plugins/mvm-platform/assets/events-overview.css', 'utf8');
const bootstrap = fs.readFileSync('plugins/mvm-platform/bootstrap.php', 'utf8');
const platform = fs.readFileSync('plugins/mvm-platform/src/class-platform.php', 'utf8');

assert.match(events, /remove_shortcode\( 'mvm_events_overview' \)/, 'new renderer must replace the earlier RC shortcode');
assert.match(events, /add_shortcode\( 'mvm_events_overview'/);
assert.match(events, /DateTimeImmutable::createFromFormat\( '!Y-m-d H:i:s', \$value, \$timezone \)/, 'WP Event Manager local datetime strings must be parsed in the WordPress timezone');
assert.ok(!events.includes('strtotime('), 'event overview must not reinterpret local event meta through server/UTC strtotime semantics');
assert.match(events, /wp_timezone\(\)/);
assert.match(events, /current_time\( 'Y-m-d H:i:s' \)/, 'upcoming-event filter must use WordPress local time');
assert.match(events, /post_type'\s*=>\s*'event_listing'/);
assert.match(events, /get_post_meta\( \$post->ID, '_event_start_date'/);
assert.match(events, /get_the_post_thumbnail_url/);
assert.match(events, /Bekijk evenement →/);
assert.match(events, /wp_reset_postdata\(\)/);
assert.match(css, /#1966AE/i, 'event overview must retain MvM blue');
assert.match(css, /background:#fff/, 'cards must use white surfaces');
assert.match(css, /color:#17202a/, 'white cards must use dark text');
assert.match(css, /@media\(max-width:620px\)/, 'event grid must be responsive on mobile');
assert.ok(bootstrap.includes("src/site/class-events-overview.php"), 'integrated module must package the repaired event renderer');
assert.match(platform, /MvM_Platform_Events_Overview::boot\(\)/, 'repaired event renderer must boot after the legacy frontend layer');

console.log('MvM repaired events overview contract: OK');
