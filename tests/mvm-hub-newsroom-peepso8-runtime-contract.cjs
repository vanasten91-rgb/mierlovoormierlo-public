'use strict';

const fs = require('node:fs');
const path = require('node:path');
const root = path.resolve(__dirname, '..');
const source = fs.readFileSync(path.join(root, 'plugins/mvm-hub/integrations/peepso/class-peepso8-internal-message-provider.php'), 'utf8');

function fail(message) { console.error(`FAIL: ${message}`); process.exitCode = 1; }
function ok(condition, message) { if (!condition) fail(message); }

ok(source.includes("defined( '\\\\PeepSoMessages::PLUGIN_VERSION' )"), 'PeepSo Chat class constant remains the preferred version source');
ok(source.includes("get_plugins()"), 'PeepSo 8 detection must fall back to installed plugin metadata when the runtime class constant is absent');
ok(source.includes("'peepsomessages.php'"), 'fallback must identify the PeepSo Chat bootstrap, not a generic PeepSo plugin');
ok(source.includes("str_contains( $name, 'peepso' )") && source.includes("str_contains( $name, 'chat' )"), 'fallback must bind to PeepSo Chat metadata');
ok(source.includes("version_compare( $version, '8.0.0.0', '>=' )") && source.includes("version_compare( $version, '9.0.0.0', '>=' )"), 'provider must remain restricted to the validated PeepSo 8.x range');
ok(source.includes("method_exists( '\\\\PeepSoMessagesModel', 'create_new_conversation' )") && source.includes("method_exists( '\\\\PeepSoMessagesModel', 'add_to_conversation' )") && source.includes("method_exists( '\\\\PeepSoMessageParticipants', 'in_conversation' )"), 'provider still requires the exact validated PeepSo messaging APIs');

if (!process.exitCode) console.log('PASS: MvM Hub PeepSo 8 runtime version fallback contract');
