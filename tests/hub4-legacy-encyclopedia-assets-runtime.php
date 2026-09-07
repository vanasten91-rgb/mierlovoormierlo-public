<?php

declare(strict_types=1);

define( 'ABSPATH', __DIR__ . '/' );

function content_url( string $path = '' ): string {
    return 'https://www.mierlovoormierlo.nl/wp-content/' . ltrim( $path, '/' );
}

require dirname( __DIR__ ) . '/plugins/mvm-hub4-rc-direct/src/class-frontend-repairs.php';

function expect_legacy_asset( bool $condition, string $message ): void {
    if ( ! $condition ) {
        fwrite( STDERR, $message . PHP_EOL );
        exit( 1 );
    }
}

$broken_prefix = 'https://www.mierlovoormierlo.nl/wp-content/plugins/C:/legacy-absolute-path/wp-content/mvm-hub4-legacy/hub3/modules/encyclopedie/assets/';
$fixed_prefix  = 'https://www.mierlovoormierlo.nl/wp-content/mvm-hub4-legacy/hub3/modules/encyclopedie/assets/';

$css = $broken_prefix . 'css/frontend.css?ver=2.94.0';
expect_legacy_asset(
    $fixed_prefix . 'css/frontend.css?ver=2.94.0' === MvM_Hub4_Frontend_Repairs::normalize_legacy_asset_url( $css, 'mvm-encyclopedie-frontend' ),
    'Legacy Encyclopedie CSS URL met querystring is niet correct genormaliseerd.'
);

$js = $broken_prefix . 'js/frontend.js?ver=2.94.0#runtime';
expect_legacy_asset(
    $fixed_prefix . 'js/frontend.js?ver=2.94.0#runtime' === MvM_Hub4_Frontend_Repairs::normalize_legacy_asset_url( $js, 'mvm-encyclopedie-frontend' ),
    'Legacy Encyclopedie JavaScript URL met query/hash is niet correct genormaliseerd.'
);

$already_correct = $fixed_prefix . 'css/frontend.css?ver=2.94.0';
expect_legacy_asset(
    $already_correct === MvM_Hub4_Frontend_Repairs::normalize_legacy_asset_url( $already_correct ),
    'Een al correcte Encyclopedie asset-URL mag niet worden herschreven.'
);

$other_asset = $broken_prefix . 'css/admin.css?ver=2.94.0';
expect_legacy_asset(
    $other_asset === MvM_Hub4_Frontend_Repairs::normalize_legacy_asset_url( $other_asset ),
    'De reparatie mag geen andere legacy assets dan frontend.css/frontend.js herschrijven.'
);

$unrelated_plugin_asset = 'https://www.mierlovoormierlo.nl/wp-content/plugins/example/assets/frontend.css?ver=1';
expect_legacy_asset(
    $unrelated_plugin_asset === MvM_Hub4_Frontend_Repairs::normalize_legacy_asset_url( $unrelated_plugin_asset ),
    'De reparatie mag geen niet-Encyclopedie plugin-assets herschrijven.'
);

echo "MvM legacy Encyclopedie asset URL runtime: OK\n";

$route_test = __DIR__ . '/hub4-encyclopedia-runtime.php';
$command    = escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( $route_test );
passthru( $command, $route_exit );
expect_legacy_asset( 0 === $route_exit, 'De Encyclopedie CPT/rewrite-regressietest is mislukt.' );
