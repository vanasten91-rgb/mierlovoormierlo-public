const fs = require('fs');
const path = require('path');

const root = path.resolve(__dirname, '..');
const adoption = fs.readFileSync(path.join(root, 'plugins/mvm-hub4-rc-direct/src/class-newsradar-source-adoption.php'), 'utf8');
const schedule = fs.readFileSync(path.join(root, 'plugins/mvm-hub4-rc-direct/src/class-newsradar-schedule.php'), 'utf8');
const sources = fs.readFileSync(path.join(root, 'plugins/mvm-hub4-rc-direct/src/class-sources.php'), 'utf8');

function must(haystack, needle, message) {
  if (!haystack.includes(needle)) throw new Error(message || `Missing contract: ${needle}`);
}

must(adoption, "MIGRATION_OPTION = 'mvm_newsradar_source_adoption_v1'", 'Migration must be idempotently versioned.');
must(adoption, "LEGACY_ID_META   = '_mvm_newsradar_legacy_source_id'", 'Legacy source identity must be preserved.');
must(adoption, "'frequency'        => 'daily'", 'Historical adoption must remain reproducible for rollback/audit.');
must(adoption, 'DAY_IN_SECONDS / count( $active )', 'Initial adopted schedule must remain reproducible for audit/rollback.');
must(adoption, 'time() + ( 5 * MINUTE_IN_SECONDS )', 'First staggered round must start promptly instead of waiting another day.');
must(adoption, "'_mvm_newsradar_priority'", 'Priority metadata must be preserved.');
must(adoption, "'_mvm_newsradar_usage'", 'Usage metadata must be preserved.');
must(adoption, "'_mvm_newsradar_original_frequency'", 'Original frequency must be retained for audit/rollback.');
must(adoption, "'_mvm_newsradar_social'", 'Social source metadata must be preserved.');
must(adoption, "'_mvm_newsradar_web_checked'", 'Web verification date must be preserved.');
must(adoption, "'_mvm_newsradar_excluded'", 'Excluded state must be preserved.');
must(adoption, "'_yoast_wpseo_meta-robots-noindex'", 'Operational monitoring sources must be noindex.');
must(adoption, 'hide_monitoring_sources_from_public_queries', 'Operational monitoring sources must be hidden from public mvm_bron queries.');
must(adoption, "'compare' => 'NOT EXISTS'", 'Public source queries must exclude records marked as Nieuwsradar monitoring sources.');
if (/update_option\s*\(\s*MvM_Hub4_Legacy_Newsradar::OPTION_SOURCES/.test(adoption)) {
  throw new Error('Adoption must never rewrite the legacy mvm_bh_sources rollback option.');
}

must(schedule, "public const HOOK = 'mvm_newsradar_staggered_v2'", 'Scheduler must use the staggered hook.');
must(schedule, "public const MANUAL_HOOK = 'mvm_newsradar_manual_source_v1'", 'Manual source runs must use their own bounded queue hook.');
must(schedule, "'interval' => 5 * MINUTE_IN_SECONDS", 'Scheduler must poll due slots in small bounded ticks.');
must(schedule, "return array( $source );", 'Managed crawler run must expose exactly one source.');
must(schedule, "$settings['source_batch'] = 1", 'Legacy crawler source batch must be forced to one.');
must(schedule, "$settings['enabled'] = 0", 'Unmanaged legacy bulk cron must fail closed.');
if (schedule.includes("AND s.frequency = 'daily'")) {
  throw new Error('Scheduler must not hard-code daily frequency; canonical source frequency owns the next due time.');
}
must(schedule, "s.status = 'active'", 'Automatic scheduling must only process active sources.');
must(schedule, 's.next_check_utc <= %s', 'Automatic scheduling must be driven by the canonical due time.');
must(schedule, "WHEN 'A' THEN 0 WHEN 'B' THEN 1 WHEN 'C' THEN 2", 'Priority A/B/C must affect which due source is handled first.');
must(schedule, 'LIMIT 1', 'Each scheduler run may select only one due source.');
must(schedule, 'MvM_Hub4_Legacy_Newsradar_Writes::crawler_busy()', 'Existing crawler lock must be honored.');
must(schedule, 'add_option( self::LOCK_OPTION', 'Scheduler must take an atomic per-run lock.');
must(schedule, 'MvM_Hub4_Sources::next_check_for_frequency', 'Successful checks must advance according to the selected source frequency.');
must(schedule, 'queue_manual_sources', 'Manual source runs must be queued instead of launched in parallel.');
must(schedule, 'array_slice(', 'Manual bulk queue must be bounded.');
must(schedule, "'stopped' ===", 'Stopped sources must be rejected from manual crawler runs until resumed.');
must(schedule, "wp_clear_scheduled_hook( self::OLD_HOOK", 'Old 08:00/20:00 bulk slots must be removed.');

must(sources, "'paused'  => 'Tijdelijk pauze'", 'Paused state must stay distinct from stopped.');
must(sources, "'stopped' => 'Gestopt'", 'Stopped state must remain explicit.');
must(sources, "'active' !== $data['status']", 'Paused/stopped sources must not keep an automatic due time.');
must(sources, "gmdate( 'Y-m-d H:i:s', time() )", 'Resuming a source must place it back into the queue promptly.');

console.log('Nieuwsradar source adoption/frequency contract: passed');
