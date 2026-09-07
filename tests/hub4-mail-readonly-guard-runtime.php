<?php
/**
 * Runtime regression tests for the fail-closed legacy MvM Mail send boundary.
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}

$GLOBALS['mvm_mail_guard_hooks'] = [
    'wp_ajax_mvm_hub_mail_send' => ['legacy_send_handler'],
    'wp_ajax_nopriv_mvm_hub_mail_send' => ['legacy_public_send_handler'],
    'wp_ajax_mvm_unrelated_action' => ['keep_me'],
];
$GLOBALS['mvm_mail_guard_scheduled'] = [];

function remove_all_actions(string $hook): bool
{
    unset($GLOBALS['mvm_mail_guard_hooks'][$hook]);
    return true;
}

function add_action(string $hook, $callback, int $priority = 10, int $accepted_args = 1): bool
{
    $GLOBALS['mvm_mail_guard_scheduled'][] = [
        'hook' => $hook,
        'callback' => $callback,
        'priority' => $priority,
        'accepted_args' => $accepted_args,
    ];
    return true;
}

function mvm_mail_guard_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

require_once __DIR__ . '/../plugins/mvm-hub4-rc-direct/src/compat/class-mail-readonly-guard.php';

MvM_Hub4_Mail_Readonly_Guard::boot();

mvm_mail_guard_assert(
    !isset($GLOBALS['mvm_mail_guard_hooks']['wp_ajax_mvm_hub_mail_send']),
    'The authenticated legacy MvM Mail send action must be removed.'
);
mvm_mail_guard_assert(
    !isset($GLOBALS['mvm_mail_guard_hooks']['wp_ajax_nopriv_mvm_hub_mail_send']),
    'The public legacy MvM Mail send action must be removed fail-closed.'
);
mvm_mail_guard_assert(
    isset($GLOBALS['mvm_mail_guard_hooks']['wp_ajax_mvm_unrelated_action']),
    'Unrelated AJAX actions must remain untouched.'
);

$scheduled = $GLOBALS['mvm_mail_guard_scheduled'];
foreach (['plugins_loaded', 'init'] as $expectedHook) {
    $matches = array_values(array_filter(
        $scheduled,
        static fn(array $item): bool => $item['hook'] === $expectedHook
            && $item['priority'] === PHP_INT_MAX
            && is_array($item['callback'])
            && $item['callback'][0] === MvM_Hub4_Mail_Readonly_Guard::class
            && $item['callback'][1] === 'disable_legacy_send_route'
    ));
    mvm_mail_guard_assert(count($matches) === 1, "The guard must re-assert at {$expectedHook} with final priority.");
}

$GLOBALS['mvm_mail_guard_hooks']['wp_ajax_mvm_hub_mail_send'] = ['late_legacy_send_handler'];
MvM_Hub4_Mail_Readonly_Guard::disable_legacy_send_route();
mvm_mail_guard_assert(
    !isset($GLOBALS['mvm_mail_guard_hooks']['wp_ajax_mvm_hub_mail_send']),
    'A late legacy send registration must be removed again.'
);

fwrite(STDOUT, "Hub 4 MvM Mail read-only guard runtime: all regression cases passed.\n");
