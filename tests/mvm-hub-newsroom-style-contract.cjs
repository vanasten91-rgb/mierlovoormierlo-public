'use strict';

const fs = require('fs');
const path = require('path');

const root = path.resolve(__dirname, '..');
const read = (relative) => fs.readFileSync(path.join(root, relative), 'utf8');
const hub = read('plugins/mvm-hub/assets/hub.css');
const admin = read('plugins/mvm-hub/assets/newsroom2-admin.css');
const collaboration = read('plugins/mvm-hub/assets/newsroom2-collaboration.css');
const runtime = read('plugins/mvm-hub/assets/newsroom-runtime.css');

function requireText(source, needle, label) {
  if (!source.includes(needle)) {
    throw new Error(`Missing MvM style contract: ${label}`);
  }
}

function requireMatch(source, pattern, label) {
  if (!pattern.test(source)) {
    throw new Error(`Missing MvM style contract: ${label}`);
  }
}

requireText(hub, '--mvm-blue: #1966AE;', 'canonical MvM blue');
requireText(hub, '--mvm-bg: #F5F7FA;', 'canonical light background');
requireText(hub, '--mvm-border: #DCE3EA;', 'canonical border');
requireText(hub, '--mvm-radius: 12px;', 'canonical radius');
requireText(hub, '--mvm-radius-lg: 18px;', 'canonical large radius');

requireText(admin, '--nr-blue: #1966AE;', 'Newsroom MvM blue');
requireText(admin, '--nr-bg: #F5F7FA;', 'Newsroom background alignment');
requireText(admin, '--nr-muted: #627083;', 'Newsroom muted text alignment');
requireText(admin, '--nr-border: #DCE3EA;', 'Newsroom border alignment');
requireText(admin, '--nr-radius-sm: 8px;', 'Newsroom small radius alignment');
requireText(admin, '--nr-radius: 12px;', 'Newsroom radius alignment');
requireText(admin, '--nr-radius-lg: 18px;', 'Newsroom large radius alignment');
requireText(admin, 'color-scheme: dark;', 'Newsroom dark mode');
requireText(admin, '.mvm-nr2 .button-primary', 'primary button normalization');
requireText(admin, '.mvm-nr2 .button-secondary', 'secondary button normalization');
requireText(admin, '.mvm-nr2 .button:disabled', 'disabled state');
requireText(admin, '@media (prefers-reduced-motion: reduce)', 'reduced-motion support');
requireMatch(admin, /\.mvm-nr2 th\s*\{[^}]*text-transform:\s*none;/s, 'normal-case table headings');

if (/\.mvm-nr2 th\s*\{[^}]*text-transform:\s*uppercase;/s.test(admin)) {
  throw new Error('Newsroom table headings must not be forced uppercase');
}

requireText(collaboration, 'border-radius:var(--nr-radius-sm,8px)', 'collaboration compact radius token');
requireText(collaboration, 'border-radius:var(--nr-radius,12px)', 'collaboration standard radius token');
requireText(collaboration, 'border-radius:var(--nr-radius-lg,18px)', 'collaboration large radius token');
requireText(collaboration, 'box-shadow:var(--nr-shadow,0 8px 28px rgba(21,34,49,.08))', 'collaboration surface shadow token');

if (/border-radius:(?:10|11|14|16|18)px/.test(collaboration)) {
  throw new Error('Newsroom collaboration surfaces must use shared radius tokens instead of legacy fixed radii');
}

requireText(runtime, 'var(--mvm-radius-lg,18px)', 'runtime large radius token');
requireText(runtime, 'var(--mvm-radius,12px)', 'runtime radius token');
requireText(runtime, 'var(--mvm-border,#DCE3EA)', 'runtime border token');
requireText(runtime, 'var(--mvm-focus,#1966AE)', 'runtime focus token');
requireText(runtime, 'var(--mvm-danger,#A02B2B)', 'runtime danger token');
requireText(runtime, 'var(--mvm-success,#177447)', 'runtime success token');

console.log('PASS: MvM Hub Newsroom backend style contract');
