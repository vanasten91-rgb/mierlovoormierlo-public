<?php
/**
 * Runtime regression tests for the fail-closed transitional theme guard.
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}

require_once __DIR__ . '/../plugins/mvm-hub4-rc-direct/src/compat/class-theme-guard.php';

function mvm_theme_guard_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$rc3FalsePositive = <<<'PHP'
<?php
if ( function_exists( 'some_unrelated_helper' ) ) {
    do_something();
}

// The route token exists elsewhere in the file, but the include is unguarded.
$legacy_route = 'mvm_hubs_v3_routes';
require_once get_stylesheet_directory() . '/mvm-hubs-v3.php';
PHP;

mvm_theme_guard_assert(
    false === MvM_Hub4_Theme_Guard::source_has_hub_guard($rc3FalsePositive),
    'An unrelated function_exists token plus the route token must not satisfy the guard.'
);

$correctGuard = <<<'PHP'
<?php
if ( ! function_exists( 'mvm_hubs_v3_routes' ) ) {
    require_once get_stylesheet_directory() . '/mvm-hubs-v3.php';
}
PHP;

mvm_theme_guard_assert(
    true === MvM_Hub4_Theme_Guard::source_has_hub_guard($correctGuard),
    'The exact route guard around the legacy Hub include must be accepted.'
);

$unguardedIncludeAfterGuard = <<<'PHP'
<?php
if ( ! function_exists( 'mvm_hubs_v3_routes' ) ) {
    $compatibility_note = true;
}

require_once get_stylesheet_directory() . '/mvm-hubs-v3.php';
PHP;

mvm_theme_guard_assert(
    false === MvM_Hub4_Theme_Guard::source_has_hub_guard($unguardedIncludeAfterGuard),
    'A matching guard elsewhere must not protect an include outside its block.'
);

$nearMatch = <<<'PHP'
<?php
if (!function_exists('mvm_hubs_v3_routes')) {
    require_once get_stylesheet_directory() . '/mvm-hubs-v3.php';
}
PHP;

mvm_theme_guard_assert(
    false === MvM_Hub4_Theme_Guard::source_has_hub_guard($nearMatch),
    'Formatting uncertainty must fail closed rather than risk a redeclaration.'
);

fwrite(STDOUT, "Hub 4 theme guard runtime: all regression cases passed.\n");
