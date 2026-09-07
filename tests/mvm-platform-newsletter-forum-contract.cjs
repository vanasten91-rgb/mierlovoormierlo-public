const fs = require('fs');
const path = require('path');
const assert = require('assert');

const root = path.join(__dirname, '..');
const content = fs.readFileSync(path.join(root, 'plugins/mvm-platform/src/newsletter/class-newsletter-content.php'), 'utf8');

assert(content.includes("'Actueel op het forum'" ) || content.includes('Actueel op het forum'), 'Newsletter must render an MvM forum section');
assert(content.includes("$wpdb->prefix . 'wpforo_topics'"), 'Forum content must reuse the existing wpForo topics store');
assert(content.includes('t.private = 0'), 'Private wpForo topics must never be selected');
assert(content.includes('t.status = 0'), 'Only approved/public topic status may be selected');
assert(content.includes('forum_is_public_for_guests'), 'Selection must enforce guest readability at send time');
assert(content.includes("'no_access' !=="), 'No-access forums must be excluded');
assert(content.includes('FORUM_RECENCY_DAYS'), 'Only current forum activity may be included');
assert(content.includes('FORUM_SNAPSHOT_PREFIX'), 'Campaign forum topics must be snapshotted for stable delivery');
assert(content.includes('WHERE t.topicid IN ('), 'Forum cards must be fetched in one bounded query rather than N+1 queries');
const selects = content.match(/SELECT [^\"]+/g) || [];
assert(selects.every((query) => !/\b(body|email|userid)\b/i.test(query)), 'Forum snapshot queries must not load post bodies or member PII');
assert(content.includes("home_url( '/forum/' )"), 'Newsletter must link to the existing MvM forum');

console.log('MvM newsletter forum contract: OK');
