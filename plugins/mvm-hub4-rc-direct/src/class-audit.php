<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class MvM_Hub4_Audit {
    public const OPTION_RETENTION_DAYS = 'mvm_hub4_audit_retention_days';
    public const CRON_HOOK             = 'mvm_hub4_audit_cleanup';
    public const MIN_RETENTION_DAYS    = 14;
    public const DEFAULT_RETENTION     = 30;

    private static bool $writing = false;

    public static function init(): void {
        add_filter( 'pre_update_option_' . self::OPTION_RETENTION_DAYS, array( __CLASS__, 'clamp_retention' ), 10, 2 );
        add_action( self::CRON_HOOK, array( __CLASS__, 'cleanup' ) );

        if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
            wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK );
        }
    }

    public static function activate(): void {
        global $wpdb;

        $table_name      = self::table_name();
        $charset_collate = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            occurred_at_utc datetime NOT NULL,
            actor_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            actor_role varchar(100) NOT NULL DEFAULT '',
            event_code varchar(190) NOT NULL,
            object_type varchar(100) NOT NULL DEFAULT '',
            object_id bigint(20) unsigned NOT NULL DEFAULT 0,
            result varchar(32) NOT NULL DEFAULT 'success',
            request_id char(36) NOT NULL,
            transition_from varchar(100) NOT NULL DEFAULT '',
            transition_to varchar(100) NOT NULL DEFAULT '',
            context_json longtext NULL,
            PRIMARY KEY  (id),
            KEY occurred_at_utc (occurred_at_utc),
            KEY actor_user_id (actor_user_id),
            KEY event_code (event_code),
            KEY object_lookup (object_type, object_id),
            KEY request_id (request_id)
        ) {$charset_collate};";

        dbDelta( $sql );

        if ( false === get_option( self::OPTION_RETENTION_DAYS, false ) ) {
            add_option( self::OPTION_RETENTION_DAYS, self::DEFAULT_RETENTION, '', false );
        }

        self::log( 'system.audit_activated', 'success', array( 'object_type' => 'audit' ) );
    }

    public static function table_name(): string {
        global $wpdb;
        return $wpdb->prefix . 'mvm_hub4_audit';
    }

    public static function clamp_retention( mixed $new_value, mixed $old_value ): int {
        unset( $old_value );
        $days = absint( $new_value );
        return max( self::MIN_RETENTION_DAYS, $days ?: self::DEFAULT_RETENTION );
    }

    public static function retention_days(): int {
        return max(
            self::MIN_RETENTION_DAYS,
            absint( get_option( self::OPTION_RETENTION_DAYS, self::DEFAULT_RETENTION ) )
        );
    }

    /**
     * Write a sanitized audit event.
     *
     * Do not pass passwords, tokens, message/article bodies, internal notes,
     * attachment contents, cookies, nonces or raw request payloads in $data.
     */
    public static function log( string $event_code, string $result = 'success', array $data = array() ): void {
        if ( self::$writing ) {
            return;
        }

        self::$writing = true;

        try {
            global $wpdb;

            $user       = wp_get_current_user();
            $roles      = $user instanceof WP_User ? (array) $user->roles : array();
            $actor_role = $roles ? sanitize_key( (string) reset( $roles ) ) : '';
            $context    = self::sanitize_context( isset( $data['context'] ) && is_array( $data['context'] ) ? $data['context'] : array() );

            $wpdb->insert(
                self::table_name(),
                array(
                    'occurred_at_utc' => current_time( 'mysql', true ),
                    'actor_user_id'   => $user instanceof WP_User ? (int) $user->ID : 0,
                    'actor_role'      => $actor_role,
                    'event_code'      => self::sanitize_event_code( $event_code ),
                    'object_type'     => sanitize_key( (string) ( $data['object_type'] ?? '' ) ),
                    'object_id'       => absint( $data['object_id'] ?? 0 ),
                    'result'          => self::sanitize_result( $result ),
                    'request_id'      => class_exists( 'MvM_Hub4_Security' ) ? MvM_Hub4_Security::request_id() : wp_generate_uuid4(),
                    'transition_from' => sanitize_key( (string) ( $data['transition_from'] ?? '' ) ),
                    'transition_to'   => sanitize_key( (string) ( $data['transition_to'] ?? '' ) ),
                    'context_json'    => $context ? wp_json_encode( $context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) : null,
                ),
                array( '%s', '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s' )
            );
        } finally {
            self::$writing = false;
        }
    }

    public static function cleanup(): void {
        global $wpdb;

        $cutoff = gmdate( 'Y-m-d H:i:s', time() - ( self::retention_days() * DAY_IN_SECONDS ) );
        $table  = self::table_name();

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is internal and fixed.
        $deleted = $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE occurred_at_utc < %s", $cutoff ) );

        self::log(
            'system.audit_cleanup',
            false === $deleted ? 'error' : 'success',
            array(
                'object_type' => 'audit',
                'context'     => array(
                    'deleted_count' => false === $deleted ? 0 : (int) $deleted,
                    'retention_days' => self::retention_days(),
                ),
            )
        );
    }

    private static function sanitize_event_code( string $event_code ): string {
        $event_code = strtolower( trim( $event_code ) );
        $event_code = preg_replace( '/[^a-z0-9._-]/', '', $event_code );
        return substr( (string) $event_code, 0, 190 );
    }

    private static function sanitize_result( string $result ): string {
        $allowed = array( 'success', 'denied', 'error', 'cancelled' );
        $result  = sanitize_key( $result );
        return in_array( $result, $allowed, true ) ? $result : 'success';
    }

    private static function sanitize_context( array $context ): array {
        $clean = array();

        foreach ( array_slice( $context, 0, 20, true ) as $key => $value ) {
            $key = sanitize_key( (string) $key );
            if ( '' === $key || self::is_sensitive_context_key( $key ) ) {
                continue;
            }

            if ( is_bool( $value ) || is_int( $value ) || is_float( $value ) ) {
                $clean[ $key ] = $value;
                continue;
            }

            if ( is_string( $value ) ) {
                $clean[ $key ] = mb_substr( sanitize_text_field( $value ), 0, 250 );
            }
        }

        return $clean;
    }

    private static function is_sensitive_context_key( string $key ): bool {
        return 1 === preg_match(
            '/password|passwd|passphrase|(^|[_-])(pass|pwd)([_-]|$)|token|nonce|cookie|authorization|secret|api[_-]?key|(^|[_-])session($|[_-](id|key|token|cookie|secret)([_-]|$))|e[_-]?mail|email|private[_-]?note|(^|[_-])(body|content|message|note)([_-]|$)/i',
            $key
        );
    }
}
