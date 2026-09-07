<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Keeps the existing _mvm_public_hidden publication boundary consistent on
 * every public encyclopedia surface.
 */
final class MVM_Encyclopedie_Next_Visibility {
    public static function init(): void {
        add_action( 'pre_get_posts', array( __CLASS__, 'filter_public_collections' ), 20 );
        add_action( 'template_redirect', array( __CLASS__, 'block_hidden_singular' ), 1 );
    }

    /** @return array<int,array<string,mixed>> */
    public static function public_meta_query(): array {
        return array(
            'relation' => 'OR',
            array(
                'key' => '_mvm_public_hidden',
                'compare' => 'NOT EXISTS',
            ),
            array(
                'key' => '_mvm_public_hidden',
                'value' => '1',
                'compare' => '!=',
            ),
        );
    }

    public static function filter_public_collections( WP_Query $query ): void {
        if ( is_admin() || ! $query->is_main_query() ) {
            return;
        }

        $post_types = MVM_Encyclopedie_Next_Content_Model::post_type_slugs();
        $taxonomies = array_keys( MVM_Encyclopedie_Next_Content_Model::taxonomies() );

        if ( ! $query->is_post_type_archive( $post_types ) && ! $query->is_tax( $taxonomies ) ) {
            return;
        }

        $existing = $query->get( 'meta_query' );
        $visibility = self::public_meta_query();

        if ( empty( $existing ) || ! is_array( $existing ) ) {
            $query->set( 'meta_query', $visibility );
            return;
        }

        $query->set(
            'meta_query',
            array(
                'relation' => 'AND',
                $existing,
                $visibility,
            )
        );
    }

    public static function block_hidden_singular(): void {
        if ( is_admin() || ! is_singular( MVM_Encyclopedie_Next_Content_Model::post_type_slugs() ) ) {
            return;
        }

        $post_id = get_queried_object_id();
        if ( $post_id <= 0 || '1' !== (string) get_post_meta( $post_id, '_mvm_public_hidden', true ) ) {
            return;
        }

        global $wp_query;
        if ( $wp_query instanceof WP_Query ) {
            $wp_query->set_404();
        }

        status_header( 404 );
        nocache_headers();

        $template = get_404_template();
        if ( is_string( $template ) && '' !== $template && is_readable( $template ) ) {
            include $template;
        }

        exit;
    }
}
