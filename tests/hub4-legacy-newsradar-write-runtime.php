<?php

declare(strict_types=1);

define('ABSPATH', __DIR__ . '/');

final class WP_Error {
    public function __construct(
        private string $code,
        private string $message = '',
        private mixed $data = null
    ) {}
    public function get_error_code(): string { return $this->code; }
    public function get_error_data(): mixed { return $this->data; }
}

function is_wp_error(mixed $thing): bool { return $thing instanceof WP_Error; }

$GLOBALS['mvm_test_options'] = array();
$GLOBALS['mvm_test_update_count'] = 0;

function get_option(string $name, mixed $default = false): mixed {
    return array_key_exists($name, $GLOBALS['mvm_test_options'])
        ? $GLOBALS['mvm_test_options'][$name]
        : $default;
}
function update_option(string $name, mixed $value, mixed $autoload = null): bool {
    unset($autoload);
    $old = $GLOBALS['mvm_test_options'][$name] ?? null;
    $GLOBALS['mvm_test_options'][$name] = $value;
    $GLOBALS['mvm_test_update_count']++;
    return $old !== $value;
}
function wp_date(string $format): string {
    unset($format);
    return '2026-08-26T14:05:00+02:00';
}
function get_current_user_id(): int { return 99; }
function sanitize_text_field(string $value): string { return trim(strip_tags($value)); }
function absint(mixed $value): int { return abs((int) $value); }

require_once __DIR__ . '/../plugins/mvm-hub4-rc-direct/src/class-legacy-newsradar.php';
require_once __DIR__ . '/../plugins/mvm-hub4-rc-direct/src/class-legacy-newsradar-writes.php';

function assert_true(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$id = str_repeat('a', 64);
$GLOBALS['mvm_test_options'] = array(
    MvM_Hub4_Legacy_Newsradar::OPTION_RESULTS => array(
        array('id' => $id, 'title' => 'Test item', 'reviewed' => 0),
    ),
    'mvm_bh_news_radar_lock' => array('cycle' => 'legacy-stale', 'created' => time() - 5000, 'expires' => 0),
);

assert_true(false === MvM_Hub4_Legacy_Newsradar_Writes::crawler_busy(), 'expires=0 must not count as an active crawler lock');

$result = MvM_Hub4_Legacy_Newsradar_Writes::mark_reviewed($id);
assert_true(is_array($result), 'valid review must succeed');
assert_true(true === $result['reviewed'], 'review result must report reviewed=true');
assert_true(true === $result['changed'], 'first review must report changed=true');
assert_true('2026-08-26T14:05:00+02:00' === $result['reviewedAt'], 'review timestamp must use WordPress local time');
assert_true(99 === $result['reviewedBy'], 'review user must be current WordPress user');
assert_true(1 === $GLOBALS['mvm_test_update_count'], 'first review must perform exactly one option write');

$stored = $GLOBALS['mvm_test_options'][MvM_Hub4_Legacy_Newsradar::OPTION_RESULTS][0];
assert_true(1 === $stored['reviewed'], 'legacy reviewed flag must mirror Hub3 semantics');
assert_true('2026-08-26T14:05:00+02:00' === $stored['reviewed_at'], 'legacy reviewed_at must be preserved');
assert_true(99 === $stored['reviewed_by'], 'legacy reviewed_by must be preserved');

$again = MvM_Hub4_Legacy_Newsradar_Writes::mark_reviewed($id);
assert_true(is_array($again), 'repeated review must remain a success');
assert_true(false === $again['changed'], 'repeated review must be idempotent');
assert_true(1 === $GLOBALS['mvm_test_update_count'], 'idempotent review must not perform another option write');
assert_true('2026-08-26T14:05:00+02:00' === $again['reviewedAt'], 'idempotent review must preserve original timestamp');
assert_true(99 === $again['reviewedBy'], 'idempotent review must preserve original reviewer');

$invalid = MvM_Hub4_Legacy_Newsradar_Writes::mark_reviewed('not-a-valid-id');
assert_true($invalid instanceof WP_Error && 'mvm_news_radar_invalid_id' === $invalid->get_error_code(), 'invalid IDs must fail closed');

$missing = MvM_Hub4_Legacy_Newsradar_Writes::mark_reviewed(str_repeat('b', 64));
assert_true($missing instanceof WP_Error && 'mvm_news_radar_not_found' === $missing->get_error_code(), 'unknown IDs must return not found');

$busy_id = str_repeat('c', 64);
$GLOBALS['mvm_test_options'][MvM_Hub4_Legacy_Newsradar::OPTION_RESULTS][] = array('id' => $busy_id, 'reviewed' => 0);
$GLOBALS['mvm_test_options']['mvm_bh_news_radar_lock'] = array(
    'cycle' => 'active-cycle',
    'created' => time(),
    'expires' => time() + 600,
);
$before_updates = $GLOBALS['mvm_test_update_count'];
$busy = MvM_Hub4_Legacy_Newsradar_Writes::mark_reviewed($busy_id);
assert_true($busy instanceof WP_Error && 'mvm_news_radar_busy' === $busy->get_error_code(), 'active crawler lock must block review writes');
assert_true($before_updates === $GLOBALS['mvm_test_update_count'], 'blocked review must not write legacy options');

$GLOBALS['mvm_test_options']['mvm_bh_news_radar_lock']['expires'] = time() - 1;
assert_true(false === MvM_Hub4_Legacy_Newsradar_Writes::crawler_busy(), 'expired crawler lock must no longer block reviews');

fwrite(STDOUT, "Hub 4 legacy Nieuwsradar review runtime: all assertions passed.\n");
