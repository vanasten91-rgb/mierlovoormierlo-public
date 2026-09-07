'use strict';

const fs = require('fs');
const path = require('path');

const root = path.resolve(__dirname, '..');
const cssPath = path.join(root, 'plugins/mvm-platform/assets/design-system.css');
const oldCssPath = path.join(root, 'plugins/mvm-platform/assets/ui-contract.css');
const platformPath = path.join(root, 'plugins/mvm-platform/src/class-platform.php');

function assert(condition, message) {
  if (!condition) {
    throw new Error(message);
  }
}

assert(fs.existsSync(cssPath), 'Canonical design-system.css must exist.');
assert(!fs.existsSync(oldCssPath), 'Legacy ui-contract.css must be removed.');

const css = fs.readFileSync(cssPath, 'utf8');
const platform = fs.readFileSync(platformPath, 'utf8');

assert(css.includes('--mvm-color-brand-600: #1966ae;'), 'MvM brand blue token must remain canonical #1966AE.');
assert(css.includes('--mvm-color-brand-700: #0b4d96;'), 'MvM dark brand token must remain present.');
assert(css.includes('--mvm-color-text: #112b50;'), 'Light theme text token must remain present.');
assert(css.includes('html[data-theme="dark"]'), 'Dark-theme selector contract must remain present.');
assert(css.includes('--mvm-color-canvas: #0f1e2e;'), 'Dark theme must provide a true dark canvas.');
assert(css.includes('--mvm-color-surface: #172a3e;'), 'Dark theme must provide a true dark surface.');
assert(css.includes('color-scheme: dark;'), 'Dark theme must advertise dark color-scheme.');
assert(css.includes('@media (prefers-reduced-motion: reduce)'), 'Reduced-motion accessibility contract must remain present.');
assert(css.includes(':focus-visible'), 'Keyboard focus-visible contract must remain present.');
assert(css.includes('.mvm-ui-button'), 'Reusable button primitive must remain present.');
assert(css.includes('.mvm-ui-card'), 'Reusable card primitive must remain present.');
assert(css.includes('.mvm-ui-input'), 'Reusable input primitive must remain present.');
assert(!css.includes('--mvm-ui-white: #ffffff'), 'Dark mode must not be neutralized by forcing the legacy white token.');
assert(!css.includes('color-scheme: light;\n    --mvm-ui-white: #ffffff'), 'Legacy dark-mode-as-light override must not return.');

assert(platform.includes("assets/design-system.css"), 'Platform must enqueue the canonical design-system.css asset.');
assert(!platform.includes("assets/ui-contract.css"), 'Platform must not enqueue the removed ui-contract.css asset.');
assert(platform.includes("login_enqueue_scripts"), 'Design system must also load on the public WordPress login context.');

console.log('MvM platform design-system contract: OK');
