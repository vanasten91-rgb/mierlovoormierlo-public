'use strict';

const fs = require('node:fs');
const path = require('node:path');

const root = path.resolve(__dirname, '..');
const hubRoot = path.join(root, 'plugins', 'mvm-hub');
const runtimeExtensions = new Set(['.php', '.js']);

function walk(dir) {
  return fs.readdirSync(dir, { withFileTypes: true }).flatMap((entry) => {
    const full = path.join(dir, entry.name);
    if (entry.isDirectory()) return walk(full);
    return runtimeExtensions.has(path.extname(entry.name)) ? [full] : [];
  });
}

function fail(message) {
  console.error(`FAIL: ${message}`);
  process.exitCode = 1;
}

const forbiddenNamespaces = [
  /\/mvm-hub4\/v1(?:\/|['"`])/,
  /\/mvm-hubs\/v3(?:\/|['"`])/,
];

for (const file of walk(hubRoot)) {
  const source = fs.readFileSync(file, 'utf8');
  for (const pattern of forbiddenNamespaces) {
    if (pattern.test(source)) {
      fail(`current MvM Hub runtime must not consume legacy REST namespace ${pattern}: ${path.relative(root, file)}`);
    }
  }
}

const targetAdapter = fs.readFileSync(
  path.join(hubRoot, 'integrations', 'encyclopedie', 'class-smart-links-target-search.php'),
  'utf8'
);
const smartLinksController = fs.readFileSync(
  path.join(hubRoot, 'modules', 'newsroom', 'news', 'class-smart-links-rest-controller.php'),
  'utf8'
);
const descriptors = fs.readFileSync(
  path.join(hubRoot, 'core', 'class-module-descriptors.php'),
  'utf8'
);
const legacyServices = fs.readFileSync(
  path.join(hubRoot, 'core', 'class-legacy-services.php'),
  'utf8'
);

if (!targetAdapter.includes("'/mvm/v1/admin/smart-links/targets'")) {
  fail('Newsroom Smart Links target search must stay on the current mvm/v1 target owner.');
}
if (!targetAdapter.includes('rest_do_request')) {
  fail('Newsroom Smart Links target search must use in-process REST rather than legacy/self HTTP.');
}
if (!smartLinksController.includes("private const NAMESPACE = 'mvm-hub/v1'")) {
  fail('Newsroom Smart Links context must stay in the current mvm-hub/v1 namespace.');
}
if (!smartLinksController.includes("'/newsroom/news/(?P<id>\\d+)/smart-links'")) {
  fail('Current per-news Smart Links context route is missing.');
}
if (!descriptors.includes("'/mvm-hub/v1/newsroom/news/{id}/smart-links'")) {
  fail('Newsroom descriptor must advertise current Smart Links context route.');
}
if (!descriptors.includes("'/mvm-hub/v1/newsroom/encyclopedia/targets'")) {
  fail('Newsroom descriptor must advertise current Smart Links target-search route.');
}
if (!/['"]hub4PluginRequired['"]\s*=>\s*false/.test(legacyServices)) {
  fail('Legacy-services status must continue to declare the deprecated Hub4 plugin unnecessary.');
}

if (!process.exitCode) {
  console.log('PASS: current MvM Hub has no direct legacy REST consumers; compatibility stays isolated in adopted services');
}
