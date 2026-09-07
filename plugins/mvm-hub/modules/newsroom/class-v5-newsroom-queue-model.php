<?php

namespace MVM\Hub\Modules\Newsroom;

use MVM\Hub\Core\My_Work_Read_Model;
use MVM\Hub\Core\Work_Item_Schema;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Read-only V5 queue projector for the current Newsroom coexistence phase.
 *
 * Existing Newsroom read models remain the data owners. This class translates
 * their already scoped output to V5 work-item candidates, authorizes each item
 * through My_Work_Read_Model, and groups the allowed result for preview UI.
 */
final class V5_Newsroom_Queue_Model {
    /**
     * @param list<array<string,mixed>> $assignments
     * @param list<array<string,mixed>> $news
     * @param callable(array<string,mixed>):(bool|array<string,mixed>) $authorize
     * @return array<string,mixed>
     */
    public static function build(
        array $assignments,
        array $news,
        callable $authorize,
        ?\DateTimeImmutable $now = null,
        int $limit = 100
    ): array {
        $candidates = array();

        foreach ( $assignments as $assignment ) {
            if ( ! is_array( $assignment ) ) {
                continue;
            }
            $candidate = V5_Newsroom_Read_Adapter::assignment_to_work_item( $assignment );
            if ( null !== $candidate ) {
                $candidates[] = $candidate;
            }
        }

        foreach ( $news as $article ) {
            if ( ! is_array( $article ) ) {
                continue;
            }
            $candidate = V5_Newsroom_Read_Adapter::news_to_work_item( $article );
            if ( null !== $candidate ) {
                $candidates[] = $candidate;
            }
        }

        $items = My_Work_Read_Model::build( $candidates, $authorize, $now, $limit );
        $queues = array(
            'assignments' => array(),
            'articles'    => array(),
            'review'      => array(),
            'blocked'     => array(),
            'scheduled'   => array(),
        );

        foreach ( $items as $item ) {
            $type  = sanitize_key( (string) ( $item['type'] ?? '' ) );
            $state = sanitize_key( (string) ( $item['workflow_state'] ?? '' ) );
            $status = sanitize_key( (string) ( $item['status'] ?? '' ) );

            if ( 'newsroom_assignment' === $type ) {
                $queues['assignments'][] = $item;
            }
            if ( 'news_article' === $type ) {
                $queues['articles'][] = $item;
            }
            if ( in_array( $state, array( 'review', 'source_check', 'media', 'editor_review', 'changes_requested' ), true ) ) {
                $queues['review'][] = $item;
            }
            if ( Work_Item_Schema::STATUS_BLOCKED === $status ) {
                $queues['blocked'][] = $item;
            }
            if ( 'scheduled' === $state ) {
                $queues['scheduled'][] = $item;
            }
        }

        return array(
            'workspace' => 'newsroom',
            'counts'    => array_map( 'count', $queues ),
            'queues'    => $queues,
            'items'     => $items,
            'readOnly'  => true,
            'legacyOwnershipPreserved' => true,
        );
    }

    private function __construct() {}
}
