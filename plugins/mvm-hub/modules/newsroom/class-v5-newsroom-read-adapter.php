<?php

namespace MVM\Hub\Modules\Newsroom;

use MVM\Hub\Core\Data_Classification;
use MVM\Hub\Core\Work_Item_Schema;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Read-only compatibility adapter from current Newsroom read models to V5 work
 * item candidates. It never queries or writes legacy tables and never changes
 * existing workflow state; callers remain responsible for authorization.
 */
final class V5_Newsroom_Read_Adapter {
    /** @param array<string,mixed> $item @return array<string,mixed>|null */
    public static function assignment_to_work_item( array $item ): ?array {
        $state = sanitize_key( (string) ( $item['workflowState'] ?? $item['state'] ?? '' ) );
        $owner = max( 0, (int) ( $item['assigneeUserId'] ?? 0 ) );
        $id    = max( 0, (int) ( $item['id'] ?? 0 ) );
        $title = sanitize_text_field( (string) ( $item['title'] ?? '' ) );

        if ( '' === $state || 0 === $id || 0 === $owner || '' === $title ) {
            return null;
        }

        $candidate = array(
            'id'                  => $id,
            'type'                => 'newsroom_assignment',
            'domain'              => 'newsroom',
            'object_type'         => 'legacy_assignment',
            'object_id'           => $id,
            'title'               => $title,
            'owner_user_id'       => $owner,
            'owner_team'          => '',
            'priority'            => self::priority_from_legacy( $item['priority'] ?? 2 ),
            'status'              => self::work_status_from_assignment_state( $state ),
            'deadline_utc'        => (string) ( $item['dueAtUtc'] ?? '' ),
            'workflow_key'        => 'newsroom_assignment_bridge',
            'workflow_version'    => 1,
            'workflow_state'      => $state,
            'required_capability' => 'mvm_assignments_view',
            'dependency_ids'      => array(),
            'blocker_reason'      => '',
            'checklist'           => array(),
            'classification'      => Data_Classification::INTERNAL,
        );

        $normalized = Work_Item_Schema::normalize( $candidate );
        if ( null === $normalized ) {
            return null;
        }

        $normalized['id'] = $id;
        $normalized['legacy_source'] = 'mvm_hub4_assignments';
        return $normalized;
    }

    /** @param array<string,mixed> $item @return array<string,mixed>|null */
    public static function news_to_work_item( array $item ): ?array {
        $state  = sanitize_key( (string) ( $item['workflowState'] ?? '' ) );
        $id     = max( 0, (int) ( $item['id'] ?? 0 ) );
        $author = max( 0, (int) ( $item['authorUserId'] ?? 0 ) );
        $title  = sanitize_text_field( (string) ( $item['title'] ?? '' ) );

        if ( '' === $state || 0 === $id || 0 === $author || '' === $title ) {
            return null;
        }

        $candidate = array(
            'id'                  => $id,
            'type'                => 'news_article',
            'domain'              => 'newsroom',
            'object_type'         => 'post',
            'object_id'           => $id,
            'title'               => $title,
            'owner_user_id'       => $author,
            'owner_team'          => '',
            'priority'            => Work_Item_Schema::PRIORITY_NORMAL,
            'status'              => self::work_status_from_news_state( $state ),
            'deadline_utc'        => '',
            'workflow_key'        => 'news_regular',
            'workflow_version'    => 1,
            'workflow_state'      => $state,
            'required_capability' => 'mvm_newsroom_access',
            'dependency_ids'      => array(),
            'blocker_reason'      => '',
            'checklist'           => array(),
            'classification'      => Data_Classification::INTERNAL,
        );

        $normalized = Work_Item_Schema::normalize( $candidate );
        if ( null === $normalized ) {
            return null;
        }

        $normalized['id'] = $id;
        $normalized['legacy_source'] = 'wordpress_post';
        return $normalized;
    }

    /** @param mixed $priority */
    private static function priority_from_legacy( mixed $priority ): string {
        $priority = max( 1, min( 4, (int) $priority ) );
        return match ( $priority ) {
            1       => Work_Item_Schema::PRIORITY_URGENT,
            2       => Work_Item_Schema::PRIORITY_HIGH,
            3       => Work_Item_Schema::PRIORITY_NORMAL,
            default => Work_Item_Schema::PRIORITY_LOW,
        };
    }

    private static function work_status_from_assignment_state( string $state ): string {
        return match ( sanitize_key( $state ) ) {
            'new', 'assigned' => Work_Item_Schema::STATUS_OPEN,
            'in_progress', 'review' => Work_Item_Schema::STATUS_IN_PROGRESS,
            'completed' => Work_Item_Schema::STATUS_DONE,
            'cancelled' => Work_Item_Schema::STATUS_CANCELLED,
            default => Work_Item_Schema::STATUS_BLOCKED,
        };
    }

    private static function work_status_from_news_state( string $state ): string {
        return match ( sanitize_key( $state ) ) {
            'draft', 'review', 'scheduled' => Work_Item_Schema::STATUS_IN_PROGRESS,
            'published' => Work_Item_Schema::STATUS_DONE,
            default => Work_Item_Schema::STATUS_BLOCKED,
        };
    }

    private function __construct() {}
}
