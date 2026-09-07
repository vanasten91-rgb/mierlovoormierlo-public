<?php

namespace MVM\Hub\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Dormant WordPress database implementation of the V5 work-item repository.
 *
 * The class is intentionally not loaded by the current Hub bootstrap. It only
 * becomes eligible after the V5 schema migration has been approved and applied.
 */
final class WPDB_Work_Item_Repository implements Work_Item_Repository {
    private string $work_items;
    private string $dependencies_table;
    private string $checklist_table;
    private string $events_table;
    private int $transaction_depth = 0;

    public function __construct( private readonly \wpdb $wpdb ) {
        $prefix = preg_replace( '/[^A-Za-z0-9_]/', '', (string) $wpdb->prefix ) ?? '';
        $this->work_items = $prefix . 'mvm_work_items';
        $this->dependencies_table = $prefix . 'mvm_work_item_dependencies';
        $this->checklist_table = $prefix . 'mvm_work_item_checklist';
        $this->events_table = $prefix . 'mvm_workflow_events';
    }

    public function get( int $id ): ?array {
        if ( $id < 1 ) return null;
        $sql = $this->wpdb->prepare( "SELECT * FROM {$this->work_items} WHERE id = %d LIMIT 1", $id );
        $row = $this->wpdb->get_row( $sql, ARRAY_A );
        if ( ! is_array( $row ) ) return null;
        $row['dependency_ids'] = $this->dependencies( $id );
        $row['checklist'] = $this->checklist( $id );
        return $row;
    }

    public function create( array $record ): array|\WP_Error {
        $now = gmdate( 'Y-m-d H:i:s' );
        $sql = $this->wpdb->prepare(
            "INSERT INTO {$this->work_items} (type,domain,object_type,object_id,title,owner_user_id,owner_team,priority,status,deadline_utc,workflow_key,workflow_version,workflow_state,required_capability,blocker_reason,classification,created_by_user_id,created_at_utc,updated_at_utc,version) VALUES (%s,%s,%s,NULLIF(%d,0),%s,NULLIF(%d,0),NULLIF(%s,''),%s,%s,NULLIF(%s,''),%s,%d,%s,%s,NULLIF(%s,''),%s,%d,%s,%s,1)",
            sanitize_key( (string) ( $record['type'] ?? '' ) ),
            sanitize_key( (string) ( $record['domain'] ?? '' ) ),
            sanitize_key( (string) ( $record['object_type'] ?? '' ) ),
            max( 0, (int) ( $record['object_id'] ?? 0 ) ),
            sanitize_text_field( (string) ( $record['title'] ?? '' ) ),
            max( 0, (int) ( $record['owner_user_id'] ?? 0 ) ),
            sanitize_key( (string) ( $record['owner_team'] ?? '' ) ),
            sanitize_key( (string) ( $record['priority'] ?? '' ) ),
            sanitize_key( (string) ( $record['status'] ?? '' ) ),
            self::db_datetime( (string) ( $record['deadline_utc'] ?? '' ) ),
            sanitize_key( (string) ( $record['workflow_key'] ?? '' ) ),
            max( 1, (int) ( $record['workflow_version'] ?? 1 ) ),
            sanitize_key( (string) ( $record['workflow_state'] ?? '' ) ),
            sanitize_key( (string) ( $record['required_capability'] ?? '' ) ),
            sanitize_textarea_field( (string) ( $record['blocker_reason'] ?? '' ) ),
            Data_Classification::normalize( (string) ( $record['classification'] ?? '' ) ),
            max( 1, (int) ( $record['created_by_user_id'] ?? 0 ) ),
            $now,
            $now
        );

        $result = $this->wpdb->query( $sql );
        if ( false === $result ) {
            return new \WP_Error( 'mvm_work_storage_create_failed', 'Werkitem kon niet worden opgeslagen.', array( 'status' => 500 ) );
        }

        $id = (int) $this->wpdb->insert_id;
        $created = $this->get( $id );
        return null === $created
            ? new \WP_Error( 'mvm_work_storage_create_unreadable', 'Werkitem kon na opslag niet worden gelezen.', array( 'status' => 500 ) )
            : $created;
    }

    public function save( int $id, array $record, int $expected_version ): array|\WP_Error {
        if ( $id < 1 || $expected_version < 1 ) {
            return new \WP_Error( 'mvm_work_storage_invalid', 'Ongeldige opslagaanvraag.', array( 'status' => 400 ) );
        }

        $sql = $this->wpdb->prepare(
            "UPDATE {$this->work_items} SET type=%s,domain=%s,object_type=%s,object_id=NULLIF(%d,0),title=%s,owner_user_id=NULLIF(%d,0),owner_team=NULLIF(%s,''),priority=%s,status=%s,deadline_utc=NULLIF(%s,''),workflow_key=%s,workflow_version=%d,workflow_state=%s,required_capability=%s,blocker_reason=NULLIF(%s,''),classification=%s,updated_at_utc=%s,version=version+1 WHERE id=%d AND version=%d",
            sanitize_key( (string) ( $record['type'] ?? '' ) ),
            sanitize_key( (string) ( $record['domain'] ?? '' ) ),
            sanitize_key( (string) ( $record['object_type'] ?? '' ) ),
            max( 0, (int) ( $record['object_id'] ?? 0 ) ),
            sanitize_text_field( (string) ( $record['title'] ?? '' ) ),
            max( 0, (int) ( $record['owner_user_id'] ?? 0 ) ),
            sanitize_key( (string) ( $record['owner_team'] ?? '' ) ),
            sanitize_key( (string) ( $record['priority'] ?? '' ) ),
            sanitize_key( (string) ( $record['status'] ?? '' ) ),
            self::db_datetime( (string) ( $record['deadline_utc'] ?? '' ) ),
            sanitize_key( (string) ( $record['workflow_key'] ?? '' ) ),
            max( 1, (int) ( $record['workflow_version'] ?? 1 ) ),
            sanitize_key( (string) ( $record['workflow_state'] ?? '' ) ),
            sanitize_key( (string) ( $record['required_capability'] ?? '' ) ),
            sanitize_textarea_field( (string) ( $record['blocker_reason'] ?? '' ) ),
            Data_Classification::normalize( (string) ( $record['classification'] ?? '' ) ),
            gmdate( 'Y-m-d H:i:s' ),
            $id,
            $expected_version
        );

        $affected = $this->wpdb->query( $sql );
        if ( false === $affected ) {
            return new \WP_Error( 'mvm_work_storage_update_failed', 'Werkitem kon niet worden bijgewerkt.', array( 'status' => 500 ) );
        }
        if ( 1 !== (int) $affected ) {
            return new \WP_Error( 'mvm_work_version_conflict', 'Het werkitem is intussen gewijzigd.', array( 'status' => 409 ) );
        }

        $saved = $this->get( $id );
        return null === $saved
            ? new \WP_Error( 'mvm_work_storage_update_unreadable', 'Bijgewerkt werkitem kon niet worden gelezen.', array( 'status' => 500 ) )
            : $saved;
    }

    public function dependencies( int $id ): array {
        if ( $id < 1 ) return array();
        $sql = $this->wpdb->prepare( "SELECT depends_on_work_item_id FROM {$this->dependencies_table} WHERE work_item_id=%d ORDER BY depends_on_work_item_id ASC", $id );
        $values = $this->wpdb->get_col( $sql );
        return array_values( array_filter( array_map( 'intval', is_array( $values ) ? $values : array() ), static fn( int $v ): bool => $v > 0 ) );
    }

    public function checklist( int $id ): array {
        if ( $id < 1 ) return array();
        $sql = $this->wpdb->prepare( "SELECT item_key,label,is_done,completed_by,completed_at,position FROM {$this->checklist_table} WHERE work_item_id=%d ORDER BY position ASC,item_key ASC", $id );
        $rows = $this->wpdb->get_results( $sql, ARRAY_A );
        $out = array();
        foreach ( is_array( $rows ) ? $rows : array() as $row ) {
            $out[] = array(
                'id' => sanitize_key( (string) ( $row['item_key'] ?? '' ) ),
                'label' => sanitize_text_field( (string) ( $row['label'] ?? '' ) ),
                'done' => 1 === (int) ( $row['is_done'] ?? 0 ),
                'completed_by' => max( 0, (int) ( $row['completed_by'] ?? 0 ) ),
                'completed_at' => (string) ( $row['completed_at'] ?? '' ),
            );
        }
        return $out;
    }

    public function replace_dependencies( int $id, array $dependency_ids ): bool|\WP_Error {
        if ( $id < 1 ) return new \WP_Error( 'mvm_work_storage_invalid', 'Ongeldig werkitem.', array( 'status' => 400 ) );
        $delete = $this->wpdb->query( $this->wpdb->prepare( "DELETE FROM {$this->dependencies_table} WHERE work_item_id=%d", $id ) );
        if ( false === $delete ) return new \WP_Error( 'mvm_work_dependencies_failed', 'Afhankelijkheden konden niet worden bijgewerkt.', array( 'status' => 500 ) );
        foreach ( array_values( array_unique( array_filter( array_map( 'intval', $dependency_ids ), static fn( int $v ): bool => $v > 0 && $v !== $id ) ) ) as $dependency_id ) {
            $sql = $this->wpdb->prepare( "INSERT INTO {$this->dependencies_table} (work_item_id,depends_on_work_item_id,created_at_utc) VALUES (%d,%d,%s)", $id, $dependency_id, gmdate( 'Y-m-d H:i:s' ) );
            if ( false === $this->wpdb->query( $sql ) ) return new \WP_Error( 'mvm_work_dependencies_failed', 'Afhankelijkheden konden niet worden bijgewerkt.', array( 'status' => 500 ) );
        }
        return true;
    }

    public function replace_checklist( int $id, array $items ): bool|\WP_Error {
        if ( $id < 1 ) return new \WP_Error( 'mvm_work_storage_invalid', 'Ongeldig werkitem.', array( 'status' => 400 ) );
        $delete = $this->wpdb->query( $this->wpdb->prepare( "DELETE FROM {$this->checklist_table} WHERE work_item_id=%d", $id ) );
        if ( false === $delete ) return new \WP_Error( 'mvm_work_checklist_failed', 'Checklist kon niet worden bijgewerkt.', array( 'status' => 500 ) );

        foreach ( array_values( $items ) as $position => $item ) {
            if ( ! is_array( $item ) ) continue;
            $key = sanitize_key( (string) ( $item['id'] ?? '' ) );
            $label = sanitize_text_field( (string) ( $item['label'] ?? '' ) );
            if ( '' === $key || '' === $label ) continue;
            $done = ! empty( $item['done'] ) ? 1 : 0;
            $completed_by = $done ? max( 0, (int) ( $item['completed_by'] ?? 0 ) ) : 0;
            $completed_at = $done ? self::db_datetime( (string) ( $item['completed_at'] ?? '' ) ) : '';
            $sql = $this->wpdb->prepare(
                "INSERT INTO {$this->checklist_table} (work_item_id,item_key,label,is_done,completed_by,completed_at,position) VALUES (%d,%s,%s,%d,NULLIF(%d,0),NULLIF(%s,''),%d)",
                $id, $key, $label, $done, $completed_by, $completed_at, max( 0, (int) $position )
            );
            if ( false === $this->wpdb->query( $sql ) ) return new \WP_Error( 'mvm_work_checklist_failed', 'Checklist kon niet worden bijgewerkt.', array( 'status' => 500 ) );
        }
        return true;
    }

    public function append_transition( array $event ): bool|\WP_Error {
        $request_id = sanitize_key( (string) ( $event['request_id'] ?? '' ) );
        if ( '' === $request_id || strlen( $request_id ) > 96 ) {
            return new \WP_Error( 'mvm_work_event_invalid', 'Ongeldige transitie-identiteit.', array( 'status' => 400 ) );
        }

        $sql = $this->wpdb->prepare(
            "INSERT INTO {$this->events_table} (work_item_id,workflow_key,workflow_version,from_state,to_state,actor_user_id,reason_code,request_id,occurred_at_utc) VALUES (%d,%s,%d,NULLIF(%s,''),%s,%d,%s,%s,%s)",
            max( 1, (int) ( $event['work_item_id'] ?? 0 ) ),
            sanitize_key( (string) ( $event['workflow_key'] ?? '' ) ),
            max( 1, (int) ( $event['workflow_version'] ?? 1 ) ),
            sanitize_key( (string) ( $event['from_state'] ?? '' ) ),
            sanitize_key( (string) ( $event['to_state'] ?? '' ) ),
            max( 1, (int) ( $event['actor_user_id'] ?? 0 ) ),
            sanitize_key( (string) ( $event['reason_code'] ?? '' ) ),
            $request_id,
            gmdate( 'Y-m-d H:i:s' )
        );

        if ( false === $this->wpdb->query( $sql ) ) {
            return new \WP_Error( 'mvm_work_event_conflict', 'Transitie kon niet worden vastgelegd of is al verwerkt.', array( 'status' => 409 ) );
        }
        return true;
    }

    public function transaction( callable $callback ): mixed {
        if ( $this->transaction_depth > 0 ) {
            return $callback();
        }

        $this->transaction_depth++;
        $this->wpdb->query( 'START TRANSACTION' );
        try {
            $result = $callback();
            if ( is_wp_error( $result ) ) {
                $this->wpdb->query( 'ROLLBACK' );
                return $result;
            }
            $this->wpdb->query( 'COMMIT' );
            return $result;
        } catch ( \Throwable $error ) {
            $this->wpdb->query( 'ROLLBACK' );
            throw $error;
        } finally {
            $this->transaction_depth = max( 0, $this->transaction_depth - 1 );
        }
    }

    private static function db_datetime( string $value ): string {
        $value = trim( $value );
        if ( '' === $value ) return '';
        try {
            return ( new \DateTimeImmutable( $value, new \DateTimeZone( 'UTC' ) ) )
                ->setTimezone( new \DateTimeZone( 'UTC' ) )
                ->format( 'Y-m-d H:i:s' );
        } catch ( \Throwable ) {
            return '';
        }
    }
}
