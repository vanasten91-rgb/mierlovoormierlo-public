<?php

declare(strict_types=1);

define('ABSPATH', __DIR__ . '/');

$GLOBALS['mvm_test_options'] = array();

function get_option(string $name, mixed $default = false): mixed {
    return array_key_exists($name, $GLOBALS['mvm_test_options'])
        ? $GLOBALS['mvm_test_options'][$name]
        : $default;
}

function wp_parse_url(string $url): array|false {
    return parse_url($url);
}

function wp_strip_all_tags(string $value): string {
    return strip_tags($value);
}

function remove_accents(string $value): string {
    return strtr($value, array('é' => 'e', 'ë' => 'e', 'ï' => 'i', 'ö' => 'o', 'ü' => 'u'));
}

require_once __DIR__ . '/../plugins/mvm-hub4-rc-direct/src/class-legacy-newsradar.php';

function assert_true(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$rows = array(
    array(
        'id' => 'r1',
        'track_id' => 't1',
        'source_id' => 110,
        'source' => 'Omroep Brabant',
        'title' => 'Zelfde onderwerp',
        'url' => 'https://www.omroepbrabant.nl/nieuws/6024055/eerste-slug',
        'status' => 'nieuw',
    ),
    array(
        'id' => 'r2',
        'track_id' => 't2',
        'source_id' => 110,
        'source' => 'Omroep Brabant',
        'title' => 'Zelfde onderwerp',
        'url' => 'https://www.omroepbrabant.nl/nieuws/6024055/gewijzigde-slug',
        'status' => 'update',
    ),
    array(
        'id' => 'r3',
        'track_id' => 't3',
        'source_id' => 1,
        'source' => 'Gemeente Geldrop-Mierlo',
        'title' => 'Ander onderwerp',
        'url' => 'https://www.geldrop-mierlo.nl/nieuw-bericht',
        'status' => 'nieuw',
    ),
);

$key1 = MvM_Hub4_Legacy_Newsradar::subject_key($rows[0]);
$key2 = MvM_Hub4_Legacy_Newsradar::subject_key($rows[1]);
assert_true('article:www.omroepbrabant.nl:6024055' === $key1, 'numeric article key must match legacy tombstone convention');
assert_true($key1 === $key2, 'slug changes for one article must remain one subject');

$groups = MvM_Hub4_Legacy_Newsradar::grouped_results($rows);
assert_true(2 === count($groups), 'three notifications should group into two subjects');
assert_true(2 === $groups[0]['notification_count'], 'first subject must report two notifications');
assert_true(1 === count($groups[0]['updates']), 'first subject must contain one expandable update');
assert_true('r1' === $groups[0]['primary']['id'], 'legacy ordering must preserve first/current card as primary');

$title_only_a = array('source' => 'Lokale Bron', 'title' => 'Nieuws uit Mierlo');
$title_only_b = array('source' => 'Lokale Bron', 'title' => 'Nieuws uit Mierlo');
assert_true(
    MvM_Hub4_Legacy_Newsradar::subject_key($title_only_a) === MvM_Hub4_Legacy_Newsradar::subject_key($title_only_b),
    'title/source fallback must be deterministic'
);

$GLOBALS['mvm_test_options'] = array(
    MvM_Hub4_Legacy_Newsradar::OPTION_SOURCES => array(
        array(
            'id' => 1,
            'name' => 'Bron A',
            'url' => 'https://example.test',
            'category' => 'nieuws',
            'priority' => 'A',
            'frequency' => 'Dagelijks',
            'active' => true,
            'last_checked' => '2026-08-25 08:00:00',
            'next_check' => '2026-08-26 20:00:00',
            'web_checked' => '2026-08-25',
            'note' => 'internal note must not leak',
            'secret' => 'not-exposed',
        ),
        array(
            'id' => 2,
            'name' => 'Bron B',
            'url' => 'https://example.org',
            'category' => 'agenda',
            'priority' => 'B',
            'frequency' => 'Wekelijks',
            'active' => true,
        ),
    ),
    MvM_Hub4_Legacy_Newsradar::OPTION_RESULTS => $rows,
    MvM_Hub4_Legacy_Newsradar::OPTION_TRACKS => array('t1' => array(), 't2' => array()),
    MvM_Hub4_Legacy_Newsradar::OPTION_SEEN => array('a' => 1),
    MvM_Hub4_Legacy_Newsradar::OPTION_DETAIL_QUEUE => array('jobs' => array('a' => array(), 'b' => array())),
    MvM_Hub4_Legacy_Newsradar::OPTION_TOMBSTONES => array('article:x:1' => array()),
    MvM_Hub4_Legacy_Newsradar::OPTION_SETTINGS => array('enabled' => 1, 'api_key' => 'must-not-leak'),
    MvM_Hub4_Legacy_Newsradar::OPTION_STATE => array('running' => 0),
);

$snapshot = MvM_Hub4_Legacy_Newsradar::snapshot();
assert_true(true === $snapshot['legacy_available'], 'legacy dataset should be detected');
assert_true(2 === $snapshot['sources'], 'snapshot must count sources');
assert_true(3 === $snapshot['results'], 'snapshot must count results');
assert_true(2 === $snapshot['queue_jobs'], 'snapshot must count detail queue jobs');
assert_true(true === $snapshot['enabled'], 'snapshot must expose only enabled state, not settings secrets');

$sources = MvM_Hub4_Legacy_Newsradar::sources();
assert_true(2 === count($sources), 'source adapter must preserve source count');
assert_true('A' === $sources[0]['priority'], 'source adapter must expose priority for deterministic sorting');
assert_true('Dagelijks' === $sources[0]['frequency'], 'source adapter must expose monitoring frequency');
assert_true('2026-08-25 08:00:00' === $sources[0]['last_checked'], 'source adapter must expose last checked time');
assert_true('2026-08-26 20:00:00' === $sources[0]['next_check'], 'source adapter must expose next check time');
assert_true(!array_key_exists('note', $sources[0]), 'source adapter must not expose internal notes');
assert_true(!array_key_exists('secret', $sources[0]), 'source adapter must enforce its allowlist');

$adapted_results = MvM_Hub4_Legacy_Newsradar::results();
assert_true(3 === count($adapted_results), 'result adapter must preserve result count');
assert_true(!array_key_exists('content_hash', $adapted_results[0]), 'result adapter must not expose non-UI hashes');

fwrite(STDOUT, "Hub 4 legacy Nieuwsradar runtime: all assertions passed.\n");
