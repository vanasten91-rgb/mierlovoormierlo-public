<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Legacy compatibility shim.
 *
 * The generic MvM "Delen / Opslaan" block is intentionally disabled
 * sitewide. Keep the class and init method so existing boot code and older
 * integrations do not fatal when upgrading from an earlier Hub 4 build.
 */
final class MvM_Hub4_Public_Sharing {
    public static function init(): void {
        // Intentionally no-op: do not register a the_content share-block filter.
    }

    public static function append_share_block( string $content ): string {
        return $content;
    }
}
