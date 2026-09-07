'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const root = path.resolve(__dirname, '..');
const read = (file) => fs.readFileSync(path.join(root, file), 'utf8');
const sources = read('plugins/mvm-hub4-rc-direct/src/class-sources.php');
const policy = read('plugins/mvm-hub4-rc-direct/src/class-newsradar-source-policy.php');
const curated = read('plugins/mvm-hub4-rc-direct/src/class-newsradar-curated-sources.php');
const reconcile = read('plugins/mvm-hub4-rc-direct/src/class-newsradar-source-policy-reconcile.php');
const repository = read('plugins/mvm-hub4-rc-direct/src/class-source-repository.php');
const main = read('plugins/mvm-hub4-rc-direct/mvm-hub4.php');

function must(haystack, needle, message) {
  assert.ok(haystack.includes(needle), message || `Missing contract: ${needle}`);
}

must(sources, "'four_daily'", 'Source frequencies must support four checks per day.');
must(sources, "'twice_daily'", 'Source frequencies must support two checks per day.');
must(sources, "'hours' => 6", '4x/day cadence must be six hours.');
must(sources, 'HOUR_IN_SECONDS', 'Next-check calculation must support subdaily intervals.');
must(sources, 'next_check_for_frequency', 'All source scheduling must remain centralized.');

must(policy, "'frequency' => 'four_daily'", 'High-speed official/safety sources must be eligible for 4x/day checks.');
must(policy, "'frequency' => 'monthly'", 'Low-signal local service providers must be throttled to monthly checks.');
must(policy, 'https://luchen.geldrop-mierlo.nl/nieuws', 'Luchen must be a curated high-value Mierlo source.');
must(policy, 'https://www.geldrop-mierlo.nl/besluitenlijst', 'Public B&W decisions must use the stable year-independent overview source.');
assert.ok(!policy.includes('https://www.geldrop-mierlo.nl/besluitenlijst-2026'), 'Curated B&W source must not hardcode the current year.');
must(policy, 'Brede regionale nieuwsbron', 'Regional news sources must stay explicitly filtered rather than being trusted wholesale.');

must(curated, "OPTION_CREATED = 'mvm_newsradar_curated_sources_created_v1'", 'Created curated source IDs must be retained for rollback/audit.');
must(curated, "'_mvm_newsradar_curated_key'", 'Curated source seeding must be idempotently keyed.');
must(curated, 'find_by_url', 'Curated source seeding must detect a manually existing URL.');
must(curated, "'post_status'  => 'publish'", 'Only validated curated configurations may become active sources.');
must(curated, 'wp_http_validate_url', 'Curated URLs must be validated before creation.');
assert.ok(!/wp_delete_post|delete_plugins|deactivate_plugins/.test(curated), 'Curated source seeding must not hard-delete or reconfigure plugins.');

must(reconcile, "OPTION_BACKUP = 'mvm_newsradar_source_policy_backup_v1'", 'Policy migration must preserve a rollback snapshot.');
must(reconcile, "'_mvm_newsradar_superseded_by'", 'Historical duplicate generations must be marked, not deleted.');
must(reconcile, "s.monitor_enabled = 1", 'Only active monitoring sources may be policy-normalized.');
must(reconcile, "s.status = 'active'", 'Stopped historical sources must not be rescheduled.');
must(reconcile, 'get_option( self::OPTION_BACKUP', 'Rollback snapshot must be write-once.');
must(reconcile, "'mvm_newsradar_policy_backup_failed'", 'A failed rollback snapshot must fail closed before policy writes.');
const snapshotWrite = reconcile.indexOf('update_option( self::OPTION_BACKUP');
const phaseTwo = reconcile.indexOf('// Phase 2:');
const firstPolicyStateWrite = reconcile.indexOf('MvM_Hub4_Sources::save_state', phaseTwo);
const firstPriorityWrite = reconcile.indexOf('update_post_meta( absint( $source_id ), $priority_key', phaseTwo);
assert.ok(snapshotWrite >= 0 && phaseTwo > snapshotWrite, 'Rollback snapshot must be persisted before phase 2 starts.');
assert.ok(firstPolicyStateWrite > snapshotWrite, 'Frequency mutation must occur after rollback persistence.');
assert.ok(firstPriorityWrite > snapshotWrite, 'Priority mutation must occur after rollback persistence.');
assert.ok(!/wp_delete_post|delete_post_meta\s*\(\s*\$duplicate_id/.test(reconcile), 'Policy reconciliation must not hard-delete source history.');

must(repository, "'_mvm_newsradar_superseded_by'", 'Canonical source repository must recognize superseded historical generations.');
must(repository, "'priority'", 'Canonical source repository must expose source priority to Hubs.');
must(repository, "'policyReason'", 'Canonical source repository must expose the reason for its monitoring cadence.');
must(repository, 'NOT EXISTS (', 'Superseded-source filtering must not rely on duplicate-producing meta joins.');
must(repository, 'ORDER BY pm.meta_id DESC LIMIT 1', 'Meta projections must select one deterministic value even if duplicate postmeta exists.');
assert.ok(!/GROUP BY\s+p\.ID/i.test(repository), 'Canonical source query must be valid under ONLY_FULL_GROUP_BY without permissive grouping.');

must(main, 'class-newsradar-source-policy.php', 'Hub bootstrap must load the Mierlo-first source policy.');
must(main, 'class-newsradar-curated-sources.php', 'Hub bootstrap must load curated source seeding.');
must(main, 'class-newsradar-source-policy-reconcile.php', 'Hub bootstrap must load policy reconciliation.');
must(main, 'MvM_Hub4_Newsradar_Curated_Sources::init();', 'Curated source seeding must initialize before policy reconciliation.');
must(main, 'MvM_Hub4_Newsradar_Source_Policy_Reconcile::init();', 'Policy reconciliation must initialize with Hub4.');

console.log('Nieuwsradar Mierlo-first source policy contract: passed');
