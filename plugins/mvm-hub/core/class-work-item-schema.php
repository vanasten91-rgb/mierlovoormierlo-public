<?php

namespace MVM\Hub\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Normalization contract for Hub V5 operational work items.
 *
 * A work item coordinates work around a canonical content/domain object; it is
 * not itself the source of truth for the article, event, encyclopedia entity,
 * community object or mailbox message.
 */
final class Work_Item_Schema {
    public const STATUS_OPEN        = 'open';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_BLOCKED     = 'blocked';
    public const STATUS_DONE        = 'done';
    public const STATUS_CANCELLED   = 'cancelled';

    public const PRIORITY_LOW    = 'low';
    public const PRIORITY_NORMAL = 'normal';
    public const PRIORITY_HIGH   = 'high';
    public const PRIORITY_URGENT = 'urgent';

    /** @return list<string> */
    public static function statuses(): array {
        return array(
            self::STATUS_OPEN,
            self::STATUS_IN_PROGRESS,
            self::STATUS_BLOCKED,
            self::STATUS_DONE,
            self::STATUS_CANCELLED,
        );
    }

    /** @return list<string> */
    public static function priorities(): array {
        return array(
            self::PRIORITY_LOW,
            self::PRIORITY_NORMAL,
            self::PRIORITY_HIGH,
            self::PRIORITY_URGENT,
        );
    }

    /**
     * Normalize a candidate record. Returns null when required identity or
     * workflow fields are missing. This does not persist anything.
     *
     * @param array<string,mixed> $record
     * @return array<string,mixed>|null
     */
    public static function normalize( array $record ): ?array {
        $type         = isset( $record['type'] ) ? sanitize_key( (string) $record['type'] ) : '';
        $domain       = isset( $record['domain'] ) ? sanitize_key( (string) $record['domain'] ) : '';
        $title        = isset( $record['title'] ) ? sanitize_text_field( (string) $record['title'] ) : '';
        $workflow     = isset( $record['workflow_key'] ) ? sanitize_key( (string) $record['workflow_key'] ) : '';
        $workflow_ver = isset( $record['workflow_version'] ) ? (int) $record['workflow_version'] : 0;
        $workflow_state = isset( $record['workflow_state'] ) ? sanitize_key( (string) $record['workflow_state'] ) : '';

        if ( '' === $type || '' === $domain || '' === $title || '' === $workflow || $workflow_ver < 1 || '' === $workflow_state ) {
            return null;
        }

        $status = isset( $record['status'] ) ? sanitize_key( (string) $record['status'] ) : self::STATUS_OPEN;
        if ( ! in_array( $status, self::statuses(), true ) ) {
            return null;
        }

        $priority = isset( $record['priority'] ) ? sanitize_key( (string) $record['priority'] ) : self::PRIORITY_NORMAL;
        if ( ! in_array( $priority, self::priorities(), true ) ) {
            return null;
        }

        $classification = Data_Classification::normalize(
            isset( $record['classification'] ) ? (string) $record['classification'] : ''
        );

        $owner_user_id = isset( $record['owner_user_id'] ) ? max( 0, (int) $record['owner_user_id'] ) : 0;
        $owner_team     = isset( $record['owner_team'] ) ? sanitize_key( (string) $record['owner_team'] ) : '';
        if ( 0 === $owner_user_id && '' === $owner_team ) {
            return null;
        }

        $dependency_ids = self::positive_int_list( $record['dependency_ids'] ?? array() );
        $checklist      = self::normalize_checklist( $record['checklist'] ?? array() );
        if ( null === $checklist ) {
            return null;
        }

        return array(
            'type'               => $type,
            'domain'             => $domain,
            'object_type'        => isset( $record['object_type'] ) ? sanitize_key( (string) $record['object_type'] ) : '',
            'object_id'          => isset( $record['object_id'] ) ? max( 0, (int) $record['object_id'] ) : 0,
            'title'              => $title,
            'owner_user_id'      => $owner_user_id,
            'owner_team'         => $owner_team,
            'priority'           => $priority,
            'status'             => $status,
            'deadline_utc'       => self::normalize_datetime( $record['deadline_utc'] ?? '' ),
            'workflow_key'       => $workflow,
            'workflow_version'   => $workflow_ver,
            'workflow_state'     => $workflow_state,
            'required_capability'=> isset( $record['required_capability'] ) ? sanitize_key( (string) $record['required_capability'] ) : '',
            'dependency_ids'     => $dependency_ids,
            'blocker_reason'     => isset( $record['blocker_reason'] ) ? sanitize_textarea_field( (string) $record['blocker_reason'] ) : '',
            'checklist'          => $checklist,
            'classification'     => $classification,
        );
    }

    /** @param mixed $value @return list<int> */
    private static function positive_int_list( mixed $value ): array {
        if ( ! is_array( $value ) ) {
            return array();
        }

        $ids = array_map( 'intval', $value );
        $ids = array_filter( $ids, static fn( int $id ): bool => $id > 0 );
        return array_values( array_unique( $ids ) );
    }

    /**
     * @param mixed $value
     * @return list<array{id:string,label:string,done:bool}>|null
     */
    private static function normalize_checklist( mixed $value ): ?array {
        if ( ! is_array( $value ) ) {
            return null;
        }

        $normalized = array();
        $seen       = array();
        foreach ( $value as $item ) {
            if ( ! is_array( $item ) ) {
                return null;
            }

            $id    = isset( $item['id'] ) ? sanitize_key( (string) $item['id'] ) : '';
            $label = isset( $item['label'] ) ? sanitize_text_field( (string) $item['label'] ) : '';
            if ( '' === $id || '' === $label || isset( $seen[ $id ] ) ) {
                return null;
            }

            $seen[ $id ] = true;
            $normalized[] = array(
                'id'    => $id,
                'label' => $label,
                'done'  => ! empty( $item['done'] ),
            );
        }

        return $normalized;
    }

    /** @param mixed $value */
    private static function normalize_datetime( mixed $value ): string {
        $value = is_string( $value ) ? trim( $value ) : '';
        if ( '' === $value ) {
            return '';
        }

        try {
            $date = new \DateTimeImmutable( $value, new \DateTimeZone( 'UTC' ) );
            return $date->setTimezone( new \DateTimeZone( 'UTC' ) )->format( 'Y-m-d\TH:i:s\Z' );
        } catch ( \Throwable ) {
            return '';
        }
    }

    private function __construct() {}
}
