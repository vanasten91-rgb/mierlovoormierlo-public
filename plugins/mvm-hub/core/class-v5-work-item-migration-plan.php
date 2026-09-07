<?php

namespace MVM\Hub\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Declarative, non-executing migration plan for V5 work-item persistence.
 *
 * This class intentionally emits metadata and SQL templates only. It does not
 * invoke WordPress schema migration helpers, query the database, register hooks
 * or mutate production.
 */
final class V5_Work_Item_Migration_Plan {
    public const TARGET_SCHEMA = Work_Item_Storage_Contract::SCHEMA_VERSION;

    /** @return array<string,mixed> */
    public static function plan( string $prefix ): array {
        if ( '' === $prefix || 1 !== preg_match( '/^[A-Za-z0-9_]+$/', $prefix ) ) {
            return array(
                'valid' => false,
                'reason' => 'invalid_prefix',
                'schemaVersion' => self::TARGET_SCHEMA,
                'operations' => array(),
            );
        }

        return array(
            'valid' => true,
            'schemaVersion' => self::TARGET_SCHEMA,
            'strategy' => 'expand_contract',
            'productionWrites' => false,
            'requiresBackup' => true,
            'requiresDryRun' => true,
            'requiresRollbackEvidence' => true,
            'requiresSeparateApproval' => true,
            'operations' => array(
                self::work_items_sql( $prefix ),
                self::dependencies_sql( $prefix ),
                self::checklist_sql( $prefix ),
                self::workflow_events_sql( $prefix ),
            ),
            'postConditions' => array(
                'allTablesExist',
                'allIndexesExist',
                'schemaVersionRecorded',
                'legacyAssignmentOwnerUnchangedUntilCutover',
                'noSourceBodyCopied',
                'noMailBodyCopied',
                'noChatBodyCopied',
            ),
        );
    }

    /** @return array<string,string> */
    private static function work_items_sql( string $prefix ): array {
        $table = $prefix . 'mvm_work_items';
        return array(
            'name' => 'create_work_items',
            'table' => $table,
            // WordPress schema-delta parsing is line-oriented and compares
            // MariaDB canonical display widths. Keep declarations isolated and
            // width-stable so a second rehearsal pass requests no changes.
            'sql' => "CREATE TABLE {$table} (\n"
                . "  id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,\n"
                . "  type VARCHAR(64) NOT NULL,\n"
                . "  domain VARCHAR(64) NOT NULL,\n"
                . "  object_type VARCHAR(64) NOT NULL DEFAULT '',\n"
                . "  object_id BIGINT(20) UNSIGNED NULL,\n"
                . "  title VARCHAR(255) NOT NULL,\n"
                . "  owner_user_id BIGINT(20) UNSIGNED NULL,\n"
                . "  owner_team VARCHAR(64) NULL,\n"
                . "  priority VARCHAR(16) NOT NULL,\n"
                . "  status VARCHAR(24) NOT NULL,\n"
                . "  deadline_utc DATETIME NULL,\n"
                . "  workflow_key VARCHAR(96) NOT NULL,\n"
                . "  workflow_version INT(10) UNSIGNED NOT NULL,\n"
                . "  workflow_state VARCHAR(64) NOT NULL,\n"
                . "  required_capability VARCHAR(96) NOT NULL DEFAULT '',\n"
                . "  blocker_reason TEXT NULL,\n"
                . "  classification VARCHAR(32) NOT NULL,\n"
                . "  created_by_user_id BIGINT(20) UNSIGNED NOT NULL,\n"
                . "  created_at_utc DATETIME NOT NULL,\n"
                . "  updated_at_utc DATETIME NOT NULL,\n"
                . "  version INT(10) UNSIGNED NOT NULL DEFAULT 1,\n"
                . "  PRIMARY KEY  (id),\n"
                . "  KEY status_owner_deadline (status, owner_user_id, deadline_utc),\n"
                . "  KEY status_team_deadline (status, owner_team, deadline_utc),\n"
                . "  KEY domain_object (domain, object_type, object_id),\n"
                . "  KEY workflow_state (workflow_key, workflow_state),\n"
                . "  KEY classification_status (classification, status),\n"
                . "  KEY updated_at_utc (updated_at_utc)\n"
                . ")",
        );
    }

    /** @return array<string,string> */
    private static function dependencies_sql( string $prefix ): array {
        $table = $prefix . 'mvm_work_item_dependencies';
        return array(
            'name' => 'create_dependencies',
            'table' => $table,
            'sql' => "CREATE TABLE {$table} (\n"
                . "  work_item_id BIGINT(20) UNSIGNED NOT NULL,\n"
                . "  depends_on_work_item_id BIGINT(20) UNSIGNED NOT NULL,\n"
                . "  created_at_utc DATETIME NOT NULL,\n"
                . "  PRIMARY KEY  (work_item_id, depends_on_work_item_id),\n"
                . "  KEY depends_on (depends_on_work_item_id)\n"
                . ")",
        );
    }

    /** @return array<string,string> */
    private static function checklist_sql( string $prefix ): array {
        $table = $prefix . 'mvm_work_item_checklist';
        return array(
            'name' => 'create_checklist',
            'table' => $table,
            'sql' => "CREATE TABLE {$table} (\n"
                . "  work_item_id BIGINT(20) UNSIGNED NOT NULL,\n"
                . "  item_key VARCHAR(96) NOT NULL,\n"
                . "  label VARCHAR(255) NOT NULL,\n"
                . "  is_done TINYINT(1) NOT NULL DEFAULT 0,\n"
                . "  completed_by BIGINT(20) UNSIGNED NULL,\n"
                . "  completed_at DATETIME NULL,\n"
                . "  position INT(10) UNSIGNED NOT NULL DEFAULT 0,\n"
                . "  PRIMARY KEY  (work_item_id, item_key),\n"
                . "  KEY work_item_position (work_item_id, position)\n"
                . ")",
        );
    }

    /** @return array<string,string> */
    private static function workflow_events_sql( string $prefix ): array {
        $table = $prefix . 'mvm_workflow_events';
        return array(
            'name' => 'create_workflow_events',
            'table' => $table,
            'sql' => "CREATE TABLE {$table} (\n"
                . "  id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,\n"
                . "  work_item_id BIGINT(20) UNSIGNED NOT NULL,\n"
                . "  workflow_key VARCHAR(96) NOT NULL,\n"
                . "  workflow_version INT(10) UNSIGNED NOT NULL,\n"
                . "  from_state VARCHAR(64) NULL,\n"
                . "  to_state VARCHAR(64) NOT NULL,\n"
                . "  actor_user_id BIGINT(20) UNSIGNED NOT NULL,\n"
                . "  reason_code VARCHAR(96) NOT NULL DEFAULT '',\n"
                . "  request_id VARCHAR(96) NOT NULL,\n"
                . "  occurred_at_utc DATETIME NOT NULL,\n"
                . "  PRIMARY KEY  (id),\n"
                . "  KEY work_item_time (work_item_id, occurred_at_utc),\n"
                . "  KEY workflow_to_state (workflow_key, to_state),\n"
                . "  KEY actor_time (actor_user_id, occurred_at_utc),\n"
                . "  UNIQUE KEY request_id (request_id)\n"
                . ")",
        );
    }

    private function __construct() {}
}
