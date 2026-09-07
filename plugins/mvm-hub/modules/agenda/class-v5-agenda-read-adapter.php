<?php

namespace MVM\Hub\Modules\Agenda;

use MVM\Hub\Core\Data_Classification;
use MVM\Hub\Core\Work_Item_Schema;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Dormant read-only adapter from the current Newsroom editorial calendar model
 * to a V5 work-item candidate. The current calendar remains canonical owner.
 */
final class V5_Agenda_Read_Adapter {
    /** @param array<string,mixed> $item @return array<string,mixed>|null */
    public static function to_work_item( array $item ): ?array {
        $id     = max( 0, (int) ( $item['id'] ?? 0 ) );
        $title  = sanitize_text_field( (string) ( $item['title'] ?? '' ) );
        $status = sanitize_key( (string) ( $item['status'] ?? '' ) );
        $kind   = sanitize_key( (string) ( $item['kind'] ?? 'event' ) );
        $owner  = max( 0, (int) ( $item['ownerUserId'] ?? 0 ) );
        $start  = trim( (string) ( $item['startsAtUtc'] ?? '' ) );

        if ( 0 === $id || '' === $title || '' === $status || '' === $start ) {
            return null;
        }

        $candidate = array(
            'id'                  => $id,
            'type'                => 'agenda_item',
            'domain'              => 'agenda',
            'object_type'         => '' !== $kind ? $kind : 'event',
            'object_id'           => max( 0, (int) ( $item['postId'] ?? $id ) ),
            'title'               => $title,
            'owner_user_id'       => $owner,
            'owner_team'          => $owner > 0 ? '' : 'agenda',
            'priority'            => self::priority_from_status( $status ),
            'status'              => self::work_status( $status ),
            'deadline_utc'        => $start,
            'workflow_key'        => 'agenda_editorial',
            'workflow_version'    => 1,
            'workflow_state'      => self::workflow_state( $status ),
            'required_capability' => 'mvm_agenda_view',
            'dependency_ids'      => self::dependency_ids( $item ),
            'blocker_reason'      => 'blocked' === $status ? 'Agenda-item is geblokkeerd in de huidige planning.' : '',
            'checklist'           => array(),
            'classification'      => Data_Classification::INTERNAL,
        );

        $normalized = Work_Item_Schema::normalize( $candidate );
        if ( null === $normalized ) {
            return null;
        }

        $normalized['id']                    = $id;
        $normalized['starts_at_utc']         = $start;
        $normalized['ends_at_utc']           = trim( (string) ( $item['endsAtUtc'] ?? '' ) );
        $normalized['calendar_kind']         = $kind;
        $normalized['legacy_source']         = 'mvm_hub4_editorial_calendar';
        $normalized['canonical_owner']       = 'current-newsroom-editorial-calendar';
        $normalized['read_only_projection']  = true;

        return $normalized;
    }

    private static function work_status( string $status ): string {
        return match ( sanitize_key( $status ) ) {
            'cancelled', 'canceled' => Work_Item_Schema::STATUS_CANCELLED,
            'done', 'completed', 'published' => Work_Item_Schema::STATUS_DONE,
            'blocked' => Work_Item_Schema::STATUS_BLOCKED,
            'confirmed', 'scheduled', 'in_progress', 'ready' => Work_Item_Schema::STATUS_IN_PROGRESS,
            default => Work_Item_Schema::STATUS_OPEN,
        };
    }

    private static function workflow_state( string $status ): string {
        $state = sanitize_key( $status );
        return '' !== $state ? $state : 'planned';
    }

    private static function priority_from_status( string $status ): string {
        return match ( sanitize_key( $status ) ) {
            'blocked' => Work_Item_Schema::PRIORITY_HIGH,
            'confirmed', 'scheduled', 'ready' => Work_Item_Schema::PRIORITY_HIGH,
            default => Work_Item_Schema::PRIORITY_NORMAL,
        };
    }

    /** @param array<string,mixed> $item @return list<int> */
    private static function dependency_ids( array $item ): array {
        $ids = array(
            (int) ( $item['assignmentId'] ?? 0 ),
            (int) ( $item['dossierId'] ?? 0 ),
        );
        $ids = array_filter( $ids, static fn( int $id ): bool => $id > 0 );
        return array_values( array_unique( $ids ) );
    }

    private function __construct() {}
}
