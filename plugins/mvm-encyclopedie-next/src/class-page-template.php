<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Keeps the canonical encyclopedia landing/theme pages independent from the
 * active theme's page-title/content wrappers while still using its header and
 * footer. This prevents duplicate H1 output and theme-specific heavy wrappers.
 */
final class MVM_Encyclopedie_Next_Page_Template {
    private const HOME_PAGE_ID = 1492;
    private const THEMES_PAGE_ID = 6905;

    public static function init(): void {
        add_filter( 'template_include', array( __CLASS__, 'template_include' ), 60 );
    }

    public static function is_managed_page(): bool {
        if ( ! is_page() ) {
            return false;
        }

        $post_id = get_queried_object_id();
        if ( in_array( $post_id, array( self::HOME_PAGE_ID, self::THEMES_PAGE_ID ), true ) ) {
            return true;
        }

        return in_array( self::THEMES_PAGE_ID, array_map( 'absint', get_post_ancestors( $post_id ) ), true );
    }

    public static function template_include( string $template ): string {
        if ( ! self::is_managed_page() ) {
            return $template;
        }

        $candidate = MVM_ENCYCLOPEDIE_NEXT_DIR . 'templates/page.php';
        return is_readable( $candidate ) ? $candidate : $template;
    }
}
