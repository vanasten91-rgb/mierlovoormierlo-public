<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class MvM_Hub4_News_Repository {
    public static function overview( int $limit = 20 ): array {
        $limit = min( 50, max( 1, $limit ) );
        $counts = wp_count_posts( 'post' );

        $query = new WP_Query(
            array(
                'post_type'              => 'post',
                'post_status'            => array( 'draft', 'pending', 'future', 'publish', 'private' ),
                'posts_per_page'         => $limit,
                'orderby'                => 'modified',
                'order'                  => 'DESC',
                'no_found_rows'          => true,
                'ignore_sticky_posts'    => true,
                'update_post_meta_cache' => false,
                'update_post_term_cache' => false,
            )
        );

        $items = array();
        foreach ( (array) $query->posts as $post ) {
            if ( ! $post instanceof WP_Post || ! current_user_can( 'edit_post', $post->ID ) ) {
                continue;
            }

            $author = get_userdata( (int) $post->post_author );
            $items[] = array(
                'id'          => (int) $post->ID,
                'title'       => get_the_title( $post ) ?: '(Zonder titel)',
                'status'      => (string) $post->post_status,
                'statusLabel' => self::status_label( (string) $post->post_status ),
                'modifiedUtc' => get_post_modified_time( 'Y-m-d H:i:s', true, $post ),
                'dateUtc'     => get_post_time( 'Y-m-d H:i:s', true, $post ),
                'author'      => $author instanceof WP_User ? $author->display_name : '',
                'editUrl'     => esc_url_raw( (string) get_edit_post_link( $post->ID, 'raw' ) ),
                'viewUrl'     => 'publish' === $post->post_status ? esc_url_raw( (string) get_permalink( $post ) ) : '',
            );
        }

        return array(
            'permissions' => array(
                'canCreate'        => current_user_can( 'edit_posts' ) || current_user_can( 'manage_options' ),
                'canViewChecklist' => current_user_can( MvM_Hub4_Capabilities::NEWS_VIEW ) || current_user_can( 'manage_options' ),
                'canUseChecklist'  => current_user_can( MvM_Hub4_Capabilities::CHECKLIST_USE ) || current_user_can( 'manage_options' ),
            ),
            'counts' => array(
                'draft'     => (int) ( $counts->draft ?? 0 ),
                'pending'   => (int) ( $counts->pending ?? 0 ),
                'scheduled' => (int) ( $counts->future ?? 0 ),
                'published' => (int) ( $counts->publish ?? 0 ),
                'private'   => (int) ( $counts->private ?? 0 ),
                'tips'      => self::news_tip_count(),
            ),
            'items' => $items,
        );
    }

    private static function news_tip_count(): int {
        if ( ! post_type_exists( 'mvm_news_tip' ) ) {
            return 0;
        }

        $counts = wp_count_posts( 'mvm_news_tip' );
        $total  = 0;
        foreach ( get_post_stati( array( 'internal' => false ) ) as $status ) {
            if ( 'trash' === $status || 'auto-draft' === $status ) {
                continue;
            }
            $total += (int) ( $counts->{$status} ?? 0 );
        }
        return $total;
    }

    private static function status_label( string $status ): string {
        return match ( $status ) {
            'draft'   => 'Concept',
            'pending' => 'Te beoordelen',
            'future'  => 'Ingepland',
            'publish' => 'Gepubliceerd',
            'private' => 'Privé',
            default   => ucfirst( sanitize_key( $status ) ),
        };
    }
}
