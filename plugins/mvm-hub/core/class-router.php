<?php

namespace MVM\Hub\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Router {
    public const HUB_PATH = '/hub/';

    /**
     * Phase 1 does not claim or rewrite /hub/. This helper only gives modules a
     * canonical route contract while the existing production Hub remains owner.
     */
    public static function hub_url( string $relative = '' ): string {
        $relative = ltrim( $relative, '/' );
        return home_url( self::HUB_PATH . $relative );
    }

    public static function is_hub_request(): bool {
        $request_uri = isset( $_SERVER['REQUEST_URI'] )
            ? (string) wp_unslash( $_SERVER['REQUEST_URI'] )
            : '';
        $path = (string) wp_parse_url( $request_uri, PHP_URL_PATH );

        return self::HUB_PATH === trailingslashit( $path )
            || str_starts_with( trailingslashit( $path ), self::HUB_PATH );
    }

    private function __construct() {}
}
