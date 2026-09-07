<?php

namespace MVM\Hub\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Object_Access {
    /** @param array<string,mixed> $row */
    public static function can_read_assignment( array $row, ?int $user_id = null ): bool {
        if ( Capabilities::can_manage_assignments() ) {
            return true;
        }

        $user_id ??= get_current_user_id();
        if ( $user_id <= 0 ) {
            return false;
        }

        return $user_id === (int) ( $row['assignee_user_id'] ?? 0 )
            || $user_id === (int) ( $row['created_by_user_id'] ?? 0 );
    }

    /** @param array<string,mixed> $row */
    public static function can_update_assignment( array $row, ?int $user_id = null ): bool {
        if ( current_user_can( 'manage_options' ) || current_user_can( Capabilities::ASSIGNMENTS_MANAGE ) ) {
            return true;
        }
        $user_id ??= get_current_user_id();
        return $user_id > 0 && $user_id === (int) ( $row['assignee_user_id'] ?? 0 );
    }

    public static function can_read_news_post( int $post_id ): bool {
        $post = get_post( $post_id );
        if ( ! $post instanceof \WP_Post || 'post' !== $post->post_type ) {
            return false;
        }
        if ( 'publish' === $post->post_status ) {
            return Capabilities::can_access_newsroom();
        }
        if ( current_user_can( 'manage_options' ) || current_user_can( Capabilities::NEWS_REVIEW ) ) {
            return true;
        }
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return false;
        }
        return (int) $post->post_author === get_current_user_id()
            || current_user_can( Capabilities::NEWS_EDIT_TEAM );
    }

    public static function can_edit_news_post( int $post_id ): bool {
        $post = get_post( $post_id );
        if ( ! $post instanceof \WP_Post || 'post' !== $post->post_type ) {
            return false;
        }
        if ( current_user_can( 'manage_options' ) ) {
            return true;
        }
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return false;
        }
        if ( (int) $post->post_author === get_current_user_id() ) {
            return current_user_can( Capabilities::NEWS_EDIT_OWN )
                || current_user_can( Capabilities::NEWS_EDIT_TEAM )
                || current_user_can( Capabilities::NEWS_REVIEW )
                || current_user_can( Capabilities::NEWS_PUBLISH );
        }
        return current_user_can( Capabilities::NEWS_EDIT_TEAM )
            || current_user_can( Capabilities::NEWS_REVIEW )
            || current_user_can( Capabilities::NEWS_PUBLISH );
    }

    private function __construct() {}
}
