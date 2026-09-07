<?php
/**
 * Hub 1.2.0-RC1 guarded Code Snippets consolidation loader.
 *
 * First-party copies are loaded only when the matching production snippet ID
 * is no longer active. This makes the migration reversible and prevents
 * duplicate functions, hooks, markup and CSS while Code Snippets remains the
 * active source for a given feature.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'mvm_hub12_active_code_snippet_ids' ) ) {
    /**
     * Return the active Code Snippets IDs once per request.
     *
     * If the Code Snippets table is unavailable, the first-party plugin is the
     * only available source and all consolidated modules are therefore allowed
     * to load.
     *
     * @return int[]
     */
    function mvm_hub12_active_code_snippet_ids(): array {
        static $active_ids = null;

        if ( null !== $active_ids ) {
            return $active_ids;
        }

        global $wpdb;

        $active_ids = array();
        if ( ! isset( $wpdb ) || ! $wpdb instanceof wpdb ) {
            return $active_ids;
        }

        $table = $wpdb->prefix . 'snippets';
        $like  = $wpdb->esc_like( $table );
        $found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $like ) );

        if ( ! is_string( $found ) || $found !== $table ) {
            return $active_ids;
        }

        $rows = $wpdb->get_col( "SELECT id FROM `{$table}` WHERE active = 1" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        if ( ! is_array( $rows ) ) {
            return $active_ids;
        }

        $active_ids = array_values(
            array_unique(
                array_filter(
                    array_map( 'absint', $rows )
                )
            )
        );

        return $active_ids;
    }
}

if ( ! function_exists( 'mvm_hub12_native_replacement_snippet_ids' ) ) {
    /**
     * Snippets replaced by the source-native AI-agendacrawl module. Their
     * original files stay in the immutable manifest for audit and rollback,
     * but are not loaded after the live copies are disabled.
     *
     * @return int[]
     */
    function mvm_hub12_native_replacement_snippet_ids(): array {
        return array( 503, 504, 506, 509, 513, 514, 525 );
    }
}

if ( ! function_exists( 'mvm_hub12_should_load_consolidated_snippet' ) ) {
    function mvm_hub12_should_load_consolidated_snippet( int $snippet_id ): bool {
        if ( class_exists( 'MvM_Hub4_AI_Agenda_Radar' ) && in_array( $snippet_id, mvm_hub12_native_replacement_snippet_ids(), true ) ) {
            return false;
        }
        return ! in_array( $snippet_id, mvm_hub12_active_code_snippet_ids(), true );
    }
}

if ( ! function_exists( 'mvm_hub12_consolidated_snippet_manifest' ) ) {
    /**
     * Immutable production manifest for the 1.2.0-RC1 consolidation.
     *
     * @return array<int,array{file:string,sha256:string}>
     */
    function mvm_hub12_consolidated_snippet_manifest(): array {
        return array(
            387 => array( 'file' => 'snippet-387.php', 'sha256' => '45da79a417d1685558b9fbe97a2411b3efd4bbc7eb1907b79dac69ba1dd69a1b' ),
            388 => array( 'file' => 'snippet-388.php', 'sha256' => '86425d24ea259bbfd0d474e30621c837192dd0a0dd47bc055afc1cd28705910f' ),
            438 => array( 'file' => 'snippet-438.php', 'sha256' => '11ec7a88abd8884dd6e92a1ed2470f7b0d134bdd0d9e26f3f3884507e4524a53' ),
            454 => array( 'file' => 'snippet-454.php', 'sha256' => '259a3d4bdeea77046c921fe67f05ed63ae8c69b4c88151b2c904c01d6991b14c' ),
            465 => array( 'file' => 'snippet-465.php', 'sha256' => '6809dae10bc0e28d5f27e3d0f72032c3d5a9616d4bfb4ca284fd0182f0777e02' ),
            469 => array( 'file' => 'snippet-469.php', 'sha256' => 'f5673ed3e54111eb0f638d326a775d1e6806f143d5c08378f3621b242257a54b' ),
            470 => array( 'file' => 'snippet-470.php', 'sha256' => '656c1b64483feac0e4acf4095921dd5b040f10033af800b4eec0e3ff328c1764' ),
            471 => array( 'file' => 'snippet-471.php', 'sha256' => 'df84aade8742589c1a1354c45c7f56d0127a4d1b40d6a561f1e80ecb21ce1df8' ),
            472 => array( 'file' => 'snippet-472.php', 'sha256' => 'b67d991687db3d8bb89b3baead7463ca5d324e423c92d43c0bf75453b1ee2536' ),
            473 => array( 'file' => 'snippet-473.php', 'sha256' => '4566b2ab198fe530986d6dde2ebbfe236ecae94f5a5e2f2e57ae40556fb40f85' ),
            474 => array( 'file' => 'snippet-474.php', 'sha256' => '43116263f0e2e88932f6eaec4e972bcaca92767228dacbb7048698d2b8333690' ),
            475 => array( 'file' => 'snippet-475.php', 'sha256' => 'b7a36c76c701df8ea7386f0889d012ee79851070c1f63f78291f4c2967023858' ),
            476 => array( 'file' => 'snippet-476.php', 'sha256' => '83d9e636bae16c5209e7de117558603397fc5e9250e578e43eda6bb9d0041e3a' ),
            477 => array( 'file' => 'snippet-477.php', 'sha256' => 'ab301d32186eea169715cc75bb301c4a118e66b61cccacdf55df175d5e84eae0' ),
            478 => array( 'file' => 'snippet-478.php', 'sha256' => '8aab3d17e12e8f5cbec3d5a59618d104e2415472210b2f2f8b185ab98608ab7d' ),
            479 => array( 'file' => 'snippet-479.php', 'sha256' => 'f80a6a79e4b5cbbd18e80b78703dfe0ff20a6a1f7f907f2a499ae6f25b8675b9' ),
            480 => array( 'file' => 'snippet-480.php', 'sha256' => 'dbcd70c006d40c492a02736aba7bb2a776ff7776cfcc263f394598cd1400bc08' ),
            481 => array( 'file' => 'snippet-481.php', 'sha256' => 'cc0a2ba732ffb1835d0c01613386f6350a6825a666b8f363bd6ba73f012a001d' ),
            482 => array( 'file' => 'snippet-482.php', 'sha256' => '9fa7b9c7393061b58f3393ac6ba0caa5d641aa6d42c324fc23bfee0e3b8b0314' ),
            483 => array( 'file' => 'snippet-483.php', 'sha256' => 'f7d97d7f45d0ce93cfeed1f4c643baaeac649ecd87b421f0d473302c4e665e8e' ),
            484 => array( 'file' => 'snippet-484.php', 'sha256' => '3124c144a14b6d83b07963798d7eb4ad8e8b885e12fe9a691907ce178e376d3c' ),
            489 => array( 'file' => 'snippet-489.php', 'sha256' => '2957311bf0dc868ef4856ceb928f3fc85c24a84c0ace326ac141407004b00b9f' ),
            491 => array( 'file' => 'snippet-491.php', 'sha256' => '1ad47dcec0dec4c671a846d8ae18950087a52ed6efbc5686c7b51528e665bda1' ),
            492 => array( 'file' => 'snippet-492.php', 'sha256' => '568e6639e8c66667c6ffe4ff7661f6cde75098abfbe338d5e009b93b9aeca385' ),
            493 => array( 'file' => 'snippet-493.php', 'sha256' => 'a823b49a7aaf5142be7f957d08eac900e9b91eabecbfd019e43063df0b65ff45' ),
            496 => array( 'file' => 'snippet-496.php', 'sha256' => '77b3bfe4a68356cba85e9837a465fc5e9841656807a296627b5dea73fbc4a8b5' ),
            497 => array( 'file' => 'snippet-497.php', 'sha256' => 'b6fb03b02c39daa3c526f6db743b2d44095e584669d133da8a1f5df9249b5b49' ),
            498 => array( 'file' => 'snippet-498.php', 'sha256' => 'b1adbadcf0d3f08cde4da5134fddbddc2dc2ae0edb42df3db33b8330539efbe0' ),
            499 => array( 'file' => 'snippet-499.php', 'sha256' => '1df262488ba744baddb30236536769f23db8dfbc9825de0a8bb4c97e762a5a02' ),
            501 => array( 'file' => 'snippet-501.php', 'sha256' => '573e6eadfa22562ec21e177b31a0fcbb0a6bc44bc28bc595a1da1b0b5c3ce13f' ),
            502 => array( 'file' => 'snippet-502.php', 'sha256' => '23e74d89e9da7bd543b1cc8f6d904bf0b1fba7300fee6d056e11fba031faee93' ),
            503 => array( 'file' => 'snippet-503.php', 'sha256' => '690d04d0c83cea5f72dff45d85fb78360a9a4827523500d14bbbb5ba37e2f24e' ),
            504 => array( 'file' => 'snippet-504.php', 'sha256' => 'b018627c34eff57fe42876487499ff8b2fe485ec960870479b68c9d2cbe7768d' ),
            505 => array( 'file' => 'snippet-505.php', 'sha256' => 'fa13669bb90dce319941479c7069fccf6d0c3494fe8a40a9e0f625e8090447fb' ),
            506 => array( 'file' => 'snippet-506.php', 'sha256' => '328190f86fd41281ac826b43069cd8af3aa1029cbe1c835c4fba4f6f65d18a6f' ),
            509 => array( 'file' => 'snippet-509.php', 'sha256' => '0c3e86c394012b75cd40181db9376aab8acf4784f05c6819f5904d23c211e4a1' ),
            513 => array( 'file' => 'snippet-513.php', 'sha256' => 'da71e4ce755c4f44d45bf90ab63f6ef9b0262fa3c5d2c8e2e9ece7f11e54f349' ),
            514 => array( 'file' => 'snippet-514.php', 'sha256' => '399432304829ff1f5f1ecbd0ab8cd94cb2035dc3e60809978f45ca97b1cfbc0a' ),
            519 => array( 'file' => 'snippet-519.php', 'sha256' => 'c7fa9ea9973dfe77063956c6c301af29ea0342c5b4179d3c34260f018c44c3fd' ),
            521 => array( 'file' => 'snippet-521.php', 'sha256' => 'ca9c5e93ac1d6b67cf6dc74037c03d8d9dfb1aa8e960cf40dd24c9b1ccec09ed' ),
            522 => array( 'file' => 'snippet-522.php', 'sha256' => 'dd3474e559f8ad04415a1a7196fa6b462e0a241a288fff05be770b779518e2c6' ),
            525 => array( 'file' => 'snippet-525.php', 'sha256' => '8031544c9c7b20fcff082c50848d47e02399acb242910cad4544a7aa53e9cd59' ),
            529 => array( 'file' => 'snippet-529.php', 'sha256' => 'dd18faaa575f202c9f6336b9425f5c24df439e89ffe7512cbf87d10a0d99c605' ),
        );
    }
}

$manifest = mvm_hub12_consolidated_snippet_manifest();
ksort( $manifest, SORT_NUMERIC );

foreach ( $manifest as $snippet_id => $entry ) {
    if ( ! mvm_hub12_should_load_consolidated_snippet( (int) $snippet_id ) ) {
        continue;
    }

    $path = __DIR__ . '/' . $entry['file'];
    if ( ! is_readable( $path ) ) {
        do_action( 'mvm_hub12_consolidated_snippet_missing', (int) $snippet_id, $path );
        continue;
    }

    require $path;
}
