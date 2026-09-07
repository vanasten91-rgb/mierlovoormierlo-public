'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const root = path.resolve(__dirname, '..', 'plugins', 'mvm-hub4-rc-direct');
const main = fs.readFileSync(path.join(root, 'mvm-hub4.php'), 'utf8');
const source = fs.readFileSync(path.join(root, 'src', 'class-peepso-event-dedupe.php'), 'utf8');
let assertions = 0;
const check = (condition, message) => { assert.ok(condition, message); assertions += 1; };

check(main.includes("src/class-peepso-event-dedupe.php"), 'Hub must source-own the event dedupe class.');
check(main.includes('MvM_Hub4_PeepSo_Event_Dedupe::init();'), 'Hub must boot event dedupe.');
check(source.includes("is_singular( 'event_listing' )"), 'Dedupe must be scoped to event listings only.');
check(source.includes("add_action( 'wp_head'") && source.includes('render_legacy_suppression_marker'), 'Hub must publish the suppression marker before the legacy final-HTML injector runs.');
check(source.includes('data-mvm-peepso-event-comments="1"'), 'Suppression marker must use the exact legacy compatibility marker.');
check(source.includes('mvm-peepso-event-comments-owner') && source.includes('content="hub4"'), 'Suppression marker must identify Hub 1.1 as owner.');
check(!source.includes('mvm-native-peepso-comments'), 'Dedupe source must not target the proven upper comment component.');
check(!/update_option|update_post_meta|wp_insert_post|wp_update_post|wp_delete_post|delete_post_meta/.test(source), 'Dedupe must not mutate PeepSo or event data.');

console.log(`MvM event comment dedupe contract: ${assertions} assertions passed.`);
