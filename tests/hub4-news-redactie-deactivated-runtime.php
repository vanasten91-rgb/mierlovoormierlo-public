<?php

declare(strict_types=1);

define( 'ABSPATH', __DIR__ . '/' );
define( 'MVM_HUB4_VERSION', '1.3.0-rc4' );
define( 'MVM_HUB4_HUB_CAPS_SCHEMA', '2026.09.01-2' );

final class Test_Hub_Role {
    public array $caps = array();
    public function add_cap( string $cap ): void { $this->caps[ $cap ] = true; }
    public function remove_cap( string $cap ): void { unset( $this->caps[ $cap ] ); }
    public function has_cap( string $cap ): bool { return ! empty( $this->caps[ $cap ] ); }
}

final class WP_REST_Server {
    public const READABLE = 'GET';
}

$GLOBALS['hub_roles'] = array(
    'administrator'  => new Test_Hub_Role(),
    'mvm_sysop'      => new Test_Hub_Role(),
    'mvm_journalist' => new Test_Hub_Role(),
);
$GLOBALS['hub_options'] = array();
$GLOBALS['hub_stylesheet'] = 'newsup-pro-child';
$GLOBALS['hub_routes'] = array();
$GLOBALS['hub_actions'] = array();

function wp_roles(): object { return (object) array( 'roles' => array_fill_keys( array_keys( $GLOBALS['hub_roles'] ), array() ) ); }
function get_role( string $name ): ?Test_Hub_Role { return $GLOBALS['hub_roles'][ $name ] ?? null; }
function get_stylesheet(): string { return $GLOBALS['hub_stylesheet']; }
function get_option( string $key, mixed $default = false ): mixed { return $GLOBALS['hub_options'][ $key ] ?? $default; }
function update_option( string $key, mixed $value, bool $autoload = false ): bool { $GLOBALS['hub_options'][ $key ] = $value; return true; }
function sanitize_text_field( string $value ): string { return trim( $value ); }
function wp_unslash( string $value ): string { return $value; }
function add_action( string $hook, mixed $callback, int $priority = 10 ): void { $GLOBALS['hub_actions'][] = array( $hook, $callback, $priority ); }
function register_rest_route( string $namespace, string $route, array $args ): void { $GLOBALS['hub_routes'][] = $namespace . $route; }
function is_user_logged_in(): bool { return true; }
function content_url( string $path = '' ): string { return 'https://www.example.test/wp-content' . ( '' === $path ? '' : '/' . ltrim( $path, '/' ) ); }

require dirname( __DIR__ ) . '/plugins/mvm-hub4-rc-direct/src/compat/class-hub-security.php';
require dirname( __DIR__ ) . '/plugins/mvm-hub4-rc-direct/src/compat/class-theme-guard.php';
require dirname( __DIR__ ) . '/plugins/mvm-hub4-rc-direct/src/class-frontend-repairs.php';

function expect_true( bool $condition, string $message ): void {
    if ( ! $condition ) {
        fwrite( STDERR, $message . PHP_EOL );
        exit( 1 );
    }
}

$guarded_theme = <<<'PHP'
<?php
if ( ! function_exists( 'mvm_hubs_v3_routes' ) ) {
    require_once get_stylesheet_directory() . '/mvm-hubs-v3.php';
    require_once get_stylesheet_directory() . '/mvm-hubs-v3-help.php';
}
PHP;
expect_true(
    true === MvM_Hub4_Theme_Guard::source_has_hub_guard( $guarded_theme ),
    'Expliciet bewaakte legacy Hub-include moet als veilig worden herkend.'
);

$false_positive_theme = <<<'PHP'
<?php
if ( function_exists( 'some_other_helper' ) ) {
    do_something();
}
$sentinel = 'mvm_hubs_v3_routes';
require_once get_stylesheet_directory() . '/mvm-hubs-v3.php';
PHP;
expect_true(
    false === MvM_Hub4_Theme_Guard::source_has_hub_guard( $false_positive_theme ),
    'Losse function_exists + route-token mag een onbewaakte Hub-include nooit veilig verklaren.'
);

$include_outside_guard = <<<'PHP'
<?php
if ( ! function_exists( 'mvm_hubs_v3_routes' ) ) {
    require_once get_stylesheet_directory() . '/mvm-hubs-v3-help.php';
}
require_once get_stylesheet_directory() . '/mvm-hubs-v3.php';
PHP;
expect_true(
    false === MvM_Hub4_Theme_Guard::source_has_hub_guard( $include_outside_guard ),
    'Hub-include buiten de expliciete guard moet fail-closed blijven.'
);

expect_true(
    false === MvM_Hub4_Theme_Guard::source_has_hub_guard( "<?php\nrequire_once get_stylesheet_directory() . '/mvm-hubs-v3.php';\n" ),
    'Onbewaakte legacy Hub-include moet fail-closed blijven.'
);

MvM_Hub4_Hub_Security::activate();

$full_caps = array(
    'mvm_hub3_business_access',
    'mvm_hub3_editorial_access',
    'mvm_hub3_review_access',
    'mvm_hub3_moderation_access',
    'mvm_hub3_team_planning_access',
    'mvm_hub3_admin_access',
);
foreach ( array( 'administrator', 'mvm_sysop' ) as $role_name ) {
    foreach ( $full_caps as $cap ) {
        expect_true( $GLOBALS['hub_roles'][ $role_name ]->has_cap( $cap ), $role_name . ' mist ' . $cap );
    }
}
expect_true( $GLOBALS['hub_roles']['mvm_journalist']->has_cap( 'mvm_hub3_editorial_access' ), 'Journalist mist editorial access.' );
expect_true( ! $GLOBALS['hub_roles']['mvm_journalist']->has_cap( 'mvm_hub3_admin_access' ), 'Journalist kreeg te brede adminrechten.' );

$GLOBALS['hub_stylesheet'] = 'newsup-pro-child-wpvibe-draft';
$GLOBALS['hub_roles']['mvm_journalist']->remove_cap( 'mvm_hub3_editorial_access' );
expect_true( false === MvM_Hub4_Hub_Security::sync_capabilities(), 'Draft preview moet capability-writes blokkeren.' );
expect_true( ! $GLOBALS['hub_roles']['mvm_journalist']->has_cap( 'mvm_hub3_editorial_access' ), 'Draft preview wijzigde toch rechten.' );

$GLOBALS['hub_stylesheet'] = 'newsup-pro-child';
MvM_Hub4_Hub_Security::register_rest_routes();
expect_true( in_array( 'mvm-hub4/v1/context', $GLOBALS['hub_routes'], true ), 'Canonieke context-route ontbreekt.' );
expect_true( in_array( 'mvm-hubs/v3/context', $GLOBALS['hub_routes'], true ), 'Legacy route ontbreekt wanneer Secure Core gedeactiveerd is.' );

$broken_css = 'https://www.example.test/wp-content/plugins/home/vx127634/domains/mierlovoormierlo.nl/public_html/wp-content/mvm-hub4-legacy/hub3/modules/encyclopedie/assets/css/frontend.css?ver=2.94.0';
$broken_js  = 'https://www.example.test/wp-content/plugins/home/vx127634/domains/mierlovoormierlo.nl/public_html/wp-content/mvm-hub4-legacy/hub3/modules/encyclopedie/assets/js/frontend.js?ver=2.94.0';
$fixed_css  = 'https://www.example.test/wp-content/mvm-hub4-legacy/hub3/modules/encyclopedie/assets/css/frontend.css?ver=2.94.0';
$fixed_js   = 'https://www.example.test/wp-content/mvm-hub4-legacy/hub3/modules/encyclopedie/assets/js/frontend.js?ver=2.94.0';
expect_true( $fixed_css === MvM_Hub4_Frontend_Repairs::normalize_legacy_asset_url( $broken_css, 'mvm-encyclopedie-frontend' ), 'Kapotte Encyclopedie CSS-URL wordt niet veilig genormaliseerd.' );
expect_true( $fixed_js === MvM_Hub4_Frontend_Repairs::normalize_legacy_asset_url( $broken_js, 'mvm-encyclopedie-frontend' ), 'Kapotte Encyclopedie JS-URL wordt niet veilig genormaliseerd.' );
expect_true( $fixed_css === MvM_Hub4_Frontend_Repairs::normalize_legacy_asset_url( $fixed_css, 'mvm-encyclopedie-frontend' ), 'Reeds correcte persistente Encyclopedie-URL mag niet wijzigen.' );
$unrelated = 'https://www.example.test/wp-content/plugins/example/assets/frontend.css?ver=1';
expect_true( $unrelated === MvM_Hub4_Frontend_Repairs::normalize_legacy_asset_url( $unrelated, 'example' ), 'Onverwante plugin-assets mogen niet worden herschreven.' );
$legacy_other = 'https://www.example.test/wp-content/plugins/home/vx127634/public_html/wp-content/mvm-hub4-legacy/hub3/modules/encyclopedie/assets/css/admin.css?ver=1';
expect_true( $legacy_other === MvM_Hub4_Frontend_Repairs::normalize_legacy_asset_url( $legacy_other, 'mvm-encyclopedie-admin' ), 'Alleen de bewezen frontend.css/frontend.js assets mogen worden herschreven.' );

echo "MvM Nieuws/Redactie Hub gedeactiveerde Secure-Core runtime: OK\n";
