<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class MvM_Hub4_Platform_Schema {
    private const OPTION_SCHEMA  = 'mvm_hub4_platform_schema';
    private const SCHEMA_VERSION = 3;

    public static function init(): void {
        add_action( 'init', array( __CLASS__, 'maybe_upgrade' ), 6 );
    }

    public static function maybe_upgrade(): void {
        if ( (int) get_option( self::OPTION_SCHEMA, 0 ) >= self::SCHEMA_VERSION ) {
            return;
        }
        self::activate();
        if ( class_exists( 'MvM_Hub4_Audit' ) ) {
            MvM_Hub4_Audit::log(
                'system.platform_schema_upgraded',
                'success',
                array( 'object_type' => 'schema', 'context' => array( 'schema_version' => self::SCHEMA_VERSION ) )
            );
        }
    }

    public static function activate(): void {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();

        $signals = self::table( 'signals' );
        dbDelta( "CREATE TABLE {$signals} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            kind varchar(24) NOT NULL DEFAULT 'general',
            status varchar(24) NOT NULL DEFAULT 'new',
            priority tinyint(3) unsigned NOT NULL DEFAULT 2,
            title varchar(190) NOT NULL,
            summary text NULL,
            source_url text NULL,
            location varchar(190) NOT NULL DEFAULT '',
            incident_at_utc datetime NULL,
            verification_status varchar(24) NOT NULL DEFAULT 'unverified',
            incident_status varchar(24) NOT NULL DEFAULT 'unknown',
            submitted_via varchar(20) NOT NULL DEFAULT 'hub',
            submitter_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            contact_name varchar(190) NOT NULL DEFAULT '',
            contact_email varchar(190) NOT NULL DEFAULT '',
            contact_phone varchar(80) NOT NULL DEFAULT '',
            assignee_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            dossier_id bigint(20) unsigned NOT NULL DEFAULT 0,
            assignment_id bigint(20) unsigned NOT NULL DEFAULT 0,
            created_by_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            created_at_utc datetime NOT NULL,
            updated_at_utc datetime NOT NULL,
            closed_at_utc datetime NULL,
            PRIMARY KEY  (id),
            KEY status (status),
            KEY kind (kind),
            KEY priority (priority),
            KEY incident_at_utc (incident_at_utc),
            KEY assignee_user_id (assignee_user_id),
            KEY dossier_id (dossier_id),
            KEY assignment_id (assignment_id),
            KEY created_at_utc (created_at_utc)
        ) {$charset};" );

        $dossiers = self::table( 'dossiers' );
        dbDelta( "CREATE TABLE {$dossiers} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            status varchar(24) NOT NULL DEFAULT 'open',
            visibility varchar(16) NOT NULL DEFAULT 'internal',
            title varchar(190) NOT NULL,
            slug varchar(190) NOT NULL,
            summary text NULL,
            internal_brief longtext NULL,
            category_term_id bigint(20) unsigned NOT NULL DEFAULT 0,
            lead_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            created_by_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            created_at_utc datetime NOT NULL,
            updated_at_utc datetime NOT NULL,
            published_at_utc datetime NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY slug (slug),
            KEY status (status),
            KEY visibility (visibility),
            KEY lead_user_id (lead_user_id),
            KEY category_term_id (category_term_id)
        ) {$charset};" );

        $links = self::table( 'dossier_links' );
        dbDelta( "CREATE TABLE {$links} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            dossier_id bigint(20) unsigned NOT NULL,
            object_type varchar(32) NOT NULL,
            object_id bigint(20) unsigned NOT NULL DEFAULT 0,
            object_url text NULL,
            label varchar(190) NOT NULL DEFAULT '',
            created_by_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            created_at_utc datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY dossier_id (dossier_id),
            KEY object_lookup (object_type, object_id)
        ) {$charset};" );

        $calendar = self::table( 'editorial_calendar' );
        dbDelta( "CREATE TABLE {$calendar} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            kind varchar(24) NOT NULL DEFAULT 'news',
            status varchar(24) NOT NULL DEFAULT 'planned',
            title varchar(190) NOT NULL,
            starts_at_utc datetime NOT NULL,
            ends_at_utc datetime NULL,
            dossier_id bigint(20) unsigned NOT NULL DEFAULT 0,
            post_id bigint(20) unsigned NOT NULL DEFAULT 0,
            assignment_id bigint(20) unsigned NOT NULL DEFAULT 0,
            owner_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            created_by_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            created_at_utc datetime NOT NULL,
            updated_at_utc datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY starts_at_utc (starts_at_utc),
            KEY owner_user_id (owner_user_id),
            KEY dossier_id (dossier_id),
            KEY status (status)
        ) {$charset};" );

        $media = self::table( 'media_items' );
        dbDelta( "CREATE TABLE {$media} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            status varchar(24) NOT NULL DEFAULT 'requested',
            kind varchar(20) NOT NULL DEFAULT 'photo',
            title varchar(190) NOT NULL,
            attachment_id bigint(20) unsigned NOT NULL DEFAULT 0,
            assignment_id bigint(20) unsigned NOT NULL DEFAULT 0,
            dossier_id bigint(20) unsigned NOT NULL DEFAULT 0,
            photographer_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            credit varchar(190) NOT NULL DEFAULT '',
            location varchar(190) NOT NULL DEFAULT '',
            captured_at_utc datetime NULL,
            consent_status varchar(24) NOT NULL DEFAULT 'unknown',
            alt_text varchar(500) NOT NULL DEFAULT '',
            created_by_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            created_at_utc datetime NOT NULL,
            updated_at_utc datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY status (status),
            KEY photographer_user_id (photographer_user_id),
            KEY dossier_id (dossier_id),
            KEY attachment_id (attachment_id)
        ) {$charset};" );

        $distribution = self::table( 'distribution_items' );
        dbDelta( "CREATE TABLE {$distribution} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            post_id bigint(20) unsigned NOT NULL DEFAULT 0,
            dossier_id bigint(20) unsigned NOT NULL DEFAULT 0,
            channel varchar(24) NOT NULL DEFAULT 'other',
            status varchar(24) NOT NULL DEFAULT 'draft',
            copy_text text NULL,
            target_url text NULL,
            prepared_by_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            approved_by_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            created_at_utc datetime NOT NULL,
            updated_at_utc datetime NOT NULL,
            sent_at_utc datetime NULL,
            PRIMARY KEY  (id),
            KEY post_id (post_id),
            KEY dossier_id (dossier_id),
            KEY channel (channel),
            KEY status (status)
        ) {$charset};" );

        $corrections = self::table( 'corrections' );
        dbDelta( "CREATE TABLE {$corrections} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            post_id bigint(20) unsigned NOT NULL DEFAULT 0,
            status varchar(24) NOT NULL DEFAULT 'new',
            message text NOT NULL,
            public_note text NULL,
            submitted_via varchar(20) NOT NULL DEFAULT 'public',
            contact_name varchar(190) NOT NULL DEFAULT '',
            contact_email varchar(190) NOT NULL DEFAULT '',
            reviewer_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            created_at_utc datetime NOT NULL,
            updated_at_utc datetime NOT NULL,
            resolved_at_utc datetime NULL,
            PRIMARY KEY  (id),
            KEY post_id (post_id),
            KEY status (status),
            KEY reviewer_user_id (reviewer_user_id),
            KEY created_at_utc (created_at_utc)
        ) {$charset};" );

        update_option( self::OPTION_SCHEMA, self::SCHEMA_VERSION, false );
    }

    public static function table( string $name ): string {
        global $wpdb;
        $allowed = array( 'signals', 'dossiers', 'dossier_links', 'editorial_calendar', 'media_items', 'distribution_items', 'corrections' );
        if ( ! in_array( $name, $allowed, true ) ) {
            throw new InvalidArgumentException( 'Unknown Hub 4 platform table.' );
        }
        return $wpdb->prefix . 'mvm_hub4_' . $name;
    }

    public static function schema_version(): int {
        return (int) get_option( self::OPTION_SCHEMA, 0 );
    }
}