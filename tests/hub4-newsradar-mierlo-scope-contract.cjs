const fs = require('fs');
const path = require('path');

const root = path.resolve(__dirname, '..');
const plugin = fs.readFileSync(path.join(root, 'plugins/mvm-hub4-rc-direct/mvm-hub4.php'), 'utf8');
const scope = fs.readFileSync(path.join(root, 'plugins/mvm-hub4-rc-direct/src/class-newsradar-local-scope.php'), 'utf8');
const actions = fs.readFileSync(path.join(root, 'plugins/mvm-hub4-rc-direct/src/class-newsradar-source-actions.php'), 'utf8');
const fallback = fs.readFileSync(path.join(root, 'plugins/mvm-hub4-rc-direct/src/consolidated-snippets/snippet-519.php'), 'utf8');
const runner = fs.readFileSync(path.join(root, 'plugins/mvm-hub4-rc-direct/src/consolidated-snippets/snippet-521.php'), 'utf8');

function must(haystack, needle, message) {
  if (!haystack.includes(needle)) throw new Error(message || `Missing contract: ${needle}`);
}

must(plugin, "class-newsradar-local-scope.php", 'Hub4 must load the deterministic Mierlo scope filter.');
must(plugin, "class-newsradar-source-actions.php", 'Hub4 must load the secure manual source action layer.');
must(scope, "'Mierlo-Hout is een wijk van Helmond", 'Mierlo-Hout must be explicitly excluded from village Mierlo scope.');
must(scope, "preg_match( '/(^|[^a-z0-9])mierlo", 'Exact village Mierlo references must be detected independently of substring noise.');
must(scope, "Gemeentebreed signaal", 'Geldrop-Mierlo municipality-only items must require a Mierlo impact check.');
must(scope, "'Luchen'", 'Verified Mierlo entity dictionary must include Luchen.');
must(scope, "'Mifano'", 'Verified Mierlo entity dictionary must include Mifano.');
must(scope, "'HC Mierlo'", 'Verified Mierlo entity dictionary must include HC Mierlo.');
must(scope, "mvm_newsradar_mierlo_entities", 'Local entity dictionary must be extensible without editing the core filter.');

must(actions, "'/sources/(?P<id>\\d+)/run'", 'A single source must have an explicit actual-crawler run endpoint.');
must(actions, "'/sources/bulk-run'", 'Selected sources must support a bounded bulk crawler queue.');
must(actions, 'SOURCE_CHECK', 'Manual runs must require the dedicated source-check capability.');
must(actions, 'queue_manual_sources', 'REST actions must queue crawler work rather than launch parallel fetches.');

for (const needle of ['http 429', 'no credits', 'quota', 'rate limit', 'insufficient_quota', 'billing']) {
  must(fallback.toLowerCase(), needle, `Provider fallback must recognize ${needle}.`);
}
must(fallback, "'gemini-3.7-flash'", 'Gemini 3.7 Flash must be the first confirmed fallback model.');
must(fallback, "'gemini-3.6-flash'", 'Gemini 3.6 Flash must be the second fallback model.');
must(fallback, "'gemini-3.5-flash-lite'", 'Gemini Flash Lite must remain a low-cost fallback.');
must(fallback, "($scope['decision']??'')==='reject'", 'Deterministic non-Mierlo rejection must override AI classification.');
must(runner, "MvM_Hub4_Newsradar_Local_Scope::assess", 'Mierlo scope must run before expensive fallback AI processing.');
must(runner, "$r['ai_pending']=0", 'Clear non-Mierlo candidates must leave the AI queue.');
must(runner, "Automatische AI-fallback hervat", 'A provider-failed cycle must be able to resume automatically.');

console.log('Nieuwsradar Mierlo-only/provider fallback contract: passed');
