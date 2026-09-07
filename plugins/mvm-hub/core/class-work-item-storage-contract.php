<?php

namespace MVM\Hub\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Declarative V5 storage contract for operational work items.
 *
 * This file performs no schema creation, SQL execution or persistence.
 * A later migration release must translate this contract into an idempotent,
 * separately approved database migration with rollback evidence.
 */
final class Work_Item_Storage_Contract {
    public const SCHEMA_VERSION = '2026.09.04-1';

    /** @return array<string,mixed> */
    public static function work_items(): array {
        return array(
            'table_suffix' => 'mvm_work_items',
            'primary_key'  => 'id',
            'columns'      => array(
                'id'                  => 'bigint_unsigned',
                'type'                => 'varchar_64',
                'domain'              => 'varchar_64',
                'object_type'         => 'varchar_64',
                'object_id'           => 'bigint_unsigned_nullable',
                'title'               => 'varchar_255',
                'owner_user_id'       => 'bigint_unsigned_nullable',
                'owner_team'          => 'varchar_64_nullable',
                'priority'            => 'varchar_16',
                'status'              => 'varchar_24',
                'deadline_utc'        => 'datetime_nullable',
                'workflow_key'        => 'varchar_96',
                'workflow_version'    => 'int_unsigned',
                'workflow_state'      => 'varchar_64',
                'required_capability' => 'varchar_96',
                'blocker_reason'      => 'text_nullable',
                'classification'      => 'varchar_32',
                'created_by_user_id'  => 'bigint_unsigned',
                'created_at_utc'      => 'datetime',
                'updated_at_utc'      => 'datetime',
                'version'             => 'int_unsigned',
            ),
            'indexes' => array(
                array( 'status', 'owner_user_id', 'deadline_utc' ),
                array( 'status', 'owner_team', 'deadline_utc' ),
                array( 'domain', 'object_type', 'object_id' ),
                array( 'workflow_key', 'workflow_state' ),
                array( 'classification', 'status' ),
                array( 'updated_at_utc' ),
            ),
        );
    }

    /** @return array<string,mixed> */
    public static function dependencies(): array {
        return array(
            'table_suffix' => 'mvm_work_item_dependencies',
            'primary_key'  => array( 'work_item_id', 'depends_on_work_item_id' ),
            'columns'      => array(
                'work_item_id'            => 'bigint_unsigned',
                'depends_on_work_item_id' => 'bigint_unsigned',
                'created_at_utc'          => 'datetime',
            ),
            'indexes' => array(
                array( 'depends_on_work_item_id' ),
            ),
        );
    }

    /** @return array<string,mixed> */
    public static function checklist(): array {
        return array(
            'table_suffix' => 'mvm_work_item_checklist',
            'primary_key'  => array( 'work_item_id', 'item_key' ),
            'columns'      => array(
                'work_item_id'   => 'bigint_unsigned',
                'item_key'       => 'varchar_96',
                'label'          => 'varchar_255',
                'is_done'        => 'tinyint_bool',
                'completed_by'   => 'bigint_unsigned_nullable',
                'completed_at'   => 'datetime_nullable',
                'position'       => 'int_unsigned',
            ),
            'indexes' => array(
                array( 'work_item_id', 'position' ),
            ),
        );
    }

    /** @return array<string,mixed> */
    public static function transitions(): array {
        return array(
            'table_suffix' => 'mvm_workflow_events',
            'primary_key'  => 'id',
            'columns'      => array(
                'id'                => 'bigint_unsigned',
                'work_item_id'      => 'bigint_unsigned',
                'workflow_key'      => 'varchar_96',
                'workflow_version'  => 'int_unsigned',
                'from_state'        => 'varchar_64_nullable',
                'to_state'          => 'varchar_64',
                'actor_user_id'     => 'bigint_unsigned',
                'reason_code'       => 'varchar_96',
                'request_id'        => 'varchar_96',
                'occurred_at_utc'   => 'datetime',
            ),
            'indexes' => array(
                array( 'work_item_id', 'occurred_at_utc' ),
                array( 'workflow_key', 'to_state' ),
                array( 'actor_user_id', 'occurred_at_utc' ),
            ),
        );
    }

    /** @return array<string,array<string,mixed>> */
    public static function all(): array {
        return array(
            'work_items'   => self::work_items(),
            'dependencies' => self::dependencies(),
            'checklist'    => self::checklist(),
            'transitions'  => self::transitions(),
        );
    }

    private function __construct() {}
}
