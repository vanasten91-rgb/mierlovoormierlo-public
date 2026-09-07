'use strict';

const fs = require('fs');
const path = require('path');

const root = path.resolve(__dirname, '..', 'plugins', 'mvm-platform');
const extensions = new Set(['.php', '.js']);
const forbidden = [
  /ultimate_member/i,
  /ultimate-member/i,
  /\bUM\(\)\s*->/,
  /\bum_user\s*\(/i,
  /\bum_fetch_user\b/i,
  /\bum_get_core_page\b/i,
  /\bum_account\b/i,
  /\bum_profile\b/i,
];

function walk(dir) {
  return fs.readdirSync(dir, { withFileTypes: true }).flatMap((entry) => {
    const full = path.join(dir, entry.name);
    if (entry.isDirectory()) return walk(full);
    return extensions.has(path.extname(entry.name)) ? [full] : [];
  });
}

const violations = [];
for (const file of walk(root)) {
  const source = fs.readFileSync(file, 'utf8');
  for (const pattern of forbidden) {
    if (pattern.test(source)) {
      violations.push(`${path.relative(root, file)} matches ${pattern}`);
    }
  }
}

if (violations.length) {
  throw new Error(`MvM Platform must not depend on Ultimate Member runtime APIs:\n${violations.join('\n')}`);
}

console.log('MvM platform account-provider contract: no Ultimate Member runtime dependency');
