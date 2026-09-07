'use strict';

const fs = require('node:fs');
const path = require('node:path');

const root = path.resolve(__dirname, '..');
const read = (relative) => fs.readFileSync(path.join(root, relative), 'utf8');

const bootstrap = read('plugins/mvm-hub/mvm-hub.php');
const shell = read('plugins/mvm-hub/core/class-shell.php');
const renderer = read('plugins/mvm-hub/core/class-shell-renderer.php');
const assets = read('plugins/mvm-hub/core/class-assets.php');
const script = read('plugins/mvm-hub/assets/hub-shell.js');
const css = read('plugins/mvm-hub/assets/hub.css');

function fail(message) {
  console.error(`FAIL: ${message}`);
  process.exitCode = 1;
}
function ok(condition, message) {
  if (!condition) fail(message);
}

ok(bootstrap.includes("core/class-shell-renderer.php"), 'bootstrap must load shell renderer');
ok(bootstrap.includes("core/class-assets.php"), 'bootstrap must load inert asset manifest');
ok(renderer.includes('Shell::view_model'), 'renderer must consume capability-filtered shell view model');
ok(renderer.includes('esc_url') && renderer.includes('esc_html'), 'renderer must escape shell navigation and labels');
ok(renderer.includes('mvm-hub__skip-link'), 'renderer must provide a keyboard skip link');
ok(renderer.includes('aria-current="page"'), 'active workspace must be exposed with aria-current');
ok(renderer.includes('aria-live="polite"'), 'workspace surface must expose polite live-region semantics');
ok(renderer.includes('data-mvm-hub-nav-toggle'), 'renderer must expose an accessible mobile navigation control');
ok(renderer.includes('data-mvm-hub-theme-toggle'), 'renderer must expose the Hub theme control');
ok(!/<script\b/i.test(renderer), 'renderer must not emit inline script tags');
ok(!/add_action|add_filter|wp_enqueue_(?:script|style)|add_rewrite_rule|flush_rewrite_rules/m.test(renderer + assets + shell), 'inert shell code must not hook, enqueue or claim routes');

ok(assets.includes("hub.css") && assets.includes("hub-shell.js"), 'asset manifest must declare separated CSS and JS');
ok(!/wp_enqueue_(?:script|style)/m.test(assets), 'asset manifest must not enqueue itself');
ok(script.includes("root.setAttribute('data-js', 'true')"), 'mobile nav enhancement must only hide content after JS is active');
ok(script.includes("event.key === 'Escape'"), 'mobile navigation must support Escape dismissal');
ok(script.includes("aria-expanded"), 'mobile navigation must synchronize aria-expanded');
ok(!/\.innerHTML\s*=|insertAdjacentHTML|document\.write/m.test(script), 'shell JS must not inject HTML strings');
ok(css.includes('.mvm-hub[data-js]:not([data-nav-open]) .mvm-hub__nav'), 'mobile navigation collapse must be progressive enhancement');
ok(css.includes('.mvm-hub__skip-link:focus'), 'skip link must become visible on keyboard focus');

if (!process.exitCode) {
  console.log('PASS: MvM Hub inert accessible shell renderer contract');
}
