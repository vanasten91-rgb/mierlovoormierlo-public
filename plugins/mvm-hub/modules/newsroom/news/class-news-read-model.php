<?php

namespace MVM\Hub\Modules\Newsroom\News;

use MVM\Hub\Core\Capabilities;
use MVM\Hub\Core\Router;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Privacy-minimized Newsroom news list.
 *
 * Non-team users are query-scoped to their own posts. Team/review users may
 * query the team stream, but every unpublished item still requires native
 * edit_post object permission before it is returned.
 */
final class News_Read_Model {
    /** @return array<string,mixed> */
    public function list( int $page = 1, int $per_page = 20, string $status = '' ): array {
        $page     = max( 1, min( 100, $page ) );
        $per_page = max( 1, min( 50, $per_page ) );
        $team     = $this->can_view_team_stream();

        $allowed_statuses = array( 'draft', 'pending', 'future', 'publish', 'private' );
        $status = sanitize_key( $status );
        $statuses = in_array( $status, $allowed_statuses, true ) ? array( $status ) : $allowed_statuses;

        $args = array(
            'post_type'              => 'post',
            'post_status'            => $statuses,
            'posts_per_page'         => $per_page + 1,
            'offset'                 => ( $page - 1 ) * $per_page,
            'orderby'                => 'modified',
            'order'                  => 'DESC',
            'no_found_rows'          => true,
            'ignore_sticky_posts'    => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        );

        if ( ! $team ) {
            $args['author'] = get_current_user_id();
        }

        $query = new \WP_Query( $args );
        $items = array();

        foreach ( (array) $query->posts as $post ) {
            if ( ! $post instanceof \WP_Post ) {
                continue;
            }

            $is_published = 'publish' === $post->post_status;
            if ( ! $is_published && ! current_user_can( 'edit_post', $post->ID ) ) {
                continue;
            }

            if ( $is_published && ! current_user_can( 'read_post', $post->ID ) ) {
                continue;
            }

            $workflow_state = self::workflow_state_from_wp_status( (string) $post->post_status );
            $items[] = array(
                'id'                => (int) $post->ID,
                'title'             => get_the_title( $post ) ?: '(Zonder titel)',
                'wpStatus'          => sanitize_key( (string) $post->post_status ),
                'workflowState'     => $workflow_state,
                'migrationNeedsMap' => null === $workflow_state,
                'authorUserId'      => (int) $post->post_author,
                'modifiedUtc'       => get_post_modified_time( 'Y-m-d H:i:s', true, $post ),
                'dateUtc'           => get_post_time( 'Y-m-d H:i:s', true, $post ),
                'hubUrl'            => Router::hub_url( 'nieuwsroom/nieuws/' . $post->ID . '/' ),
                'viewUrl'           => $is_published ? esc_url_raw( (string) get_permalink( $post ) ) : '',
                'canEdit'           => current_user_can( 'edit_post', $post->ID ),
            );
        }

        $has_more = count( $items ) > $per_page;
        $items = array_slice( $items, 0, $per_page );

        return array(
            'items'          => array_values( $items ),
            'page'           => $page,
            'perPage'        => $per_page,
            'hasMore'        => $has_more,
            'scope'          => $team ? 'team' : 'own',
            'generatedAtUtc' => gmdate( 'c' ),
        );
    }

    private function can_view_team_stream(): bool {
        return current_user_can( 'manage_options' )
            || current_user_can( Capabilities::NEWS_EDIT_TEAM )
            || current_user_can( Capabilities::NEWS_REVIEW )
            || current_user_can( 'mvm_hub4_news_review' );
    }

    public static function workflow_state_from_wp_status( string $status ): ?string {
        return match ( sanitize_key( $status ) ) {
            'draft'   => 'draft',
            'pending' => 'review',
            'future'  => 'scheduled',
            'publish' => 'published',
            default   => null,
        };
    }
}
