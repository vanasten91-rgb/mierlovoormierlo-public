<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class MvM_Hub4_Sources {
    public static function activate(): void {
        global $wpdb;

        $table_name      = self::table_name();
        $charset_collate = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $sql = "CREATE TABLE {$table_name} (
            source_post_id bigint(20) unsigned NOT NULL,
            monitor_enabled tinyint(1) unsigned NOT NULL DEFAULT 0,
            category varchar(50) NOT NULL DEFAULT 'overig',
            frequency varchar(32) NOT NULL DEFAULT 'weekly',
            status varchar(20) NOT NULL DEFAULT 'active',
            last_checked_utc datetime NULL,
            last_checked_by bigint(20) unsigned NOT NULL DEFAULT 0,
            next_check_utc datetime NULL,
            private_note text NULL,
            updated_at_utc datetime NOT NULL,
            PRIMARY KEY  (source_post_id),
            KEY monitor_enabled (monitor_enabled),
            KEY category (category),
            KEY frequency (frequency),
            KEY status (status),
            KEY next_check_utc (next_check_utc)
        ) {$charset_collate};";

        dbDelta( $sql );
    }

    public static function table_name(): string {
        global $wpdb;
        return $wpdb->prefix . 'mvm_hub4_sources';
    }

    public static function categories(): array {
        return array(
            'sport'          => 'Sport',
            'verenigingen'   => 'Verenigingen',
            'nieuwssites'    => 'Andere nieuwssites',
            'politiek'       => 'Politiek',
            'officieel'      => 'Officieel / gemeente',
            'onderwijs'      => 'Onderwijs',
            'cultuur'        => 'Cultuur',
            'veiligheid'     => 'Hulpdiensten & veiligheid',
            'ondernemers'    => 'Ondernemers',
            'lokaal_sociaal' => 'Lokale organisaties / sociale kanalen',
            'overig'         => 'Overig',
        );
    }

    public static function frequencies(): array {
        return array(
            'four_daily'    => array( 'label' => '4× per dag', 'hours' => 6, 'days' => 0 ),
            'twice_daily'   => array( 'label' => '2× per dag', 'hours' => 12, 'days' => 0 ),
            'daily'         => array( 'label' => 'Dagelijks', 'hours' => 24, 'days' => 1 ),
            'three_weekly'  => array( 'label' => '2–3× per week', 'hours' => 48, 'days' => 2 ),
            'twice_weekly'  => array( 'label' => '1–2× per week', 'hours' => 72, 'days' => 3 ),
            'weekly'        => array( 'label' => 'Wekelijks', 'hours' => 168, 'days' => 7 ),
            'biweekly'      => array( 'label' => 'Tweewekelijks', 'hours' => 336, 'days' => 14 ),
            'monthly'       => array( 'label' => 'Maandelijks', 'hours' => 720, 'days' => 30 ),
            'seasonal'      => array( 'label' => 'Seizoensgebonden', 'hours' => 0, 'days' => 0 ),
            'on_demand'     => array( 'label' => 'Op verzoek', 'hours' => 0, 'days' => 0 ),
        );
    }

    public static function statuses(): array {
        return array(
            'active'  => 'Actief',
            'paused'  => 'Tijdelijk pauze',
            'stopped' => 'Gestopt',
        );
    }

    public static function sanitize_category( string $category ): string {
        $category = sanitize_key( $category );
        return array_key_exists( $category, self::categories() ) ? $category : 'overig';
    }

    public static function sanitize_frequency( string $frequency ): string {
        $frequency = sanitize_key( $frequency );
        return array_key_exists( $frequency, self::frequencies() ) ? $frequency : 'weekly';
    }

    public static function sanitize_status( string $status ): string {
        $status = sanitize_key( $status );
        return array_key_exists( $status, self::statuses() ) ? $status : 'active';
    }

    public static function next_check_for_frequency( string $frequency, ?int $base_timestamp = null ): ?string {
        $frequency = self::sanitize_frequency( $frequency );
        $config    = self::frequencies()[ $frequency ];
        $hours     = (int) ( $config['hours'] ?? 0 );
        if ( $hours < 1 ) {
            return null;
        }

        $base_timestamp = $base_timestamp ?: time();
        return gmdate( 'Y-m-d H:i:s', $base_timestamp + ( $hours * HOUR_IN_SECONDS ) );
    }

    public static function get_state( int $source_post_id ): ?array {
        global $wpdb;

        $source_post_id = absint( $source_post_id );
        if ( ! $source_post_id ) {
            return null;
        }

        $table = self::table_name();
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed internal table name.
        $row = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$table} WHERE source_post_id = %d", $source_post_id ),
            ARRAY_A
        );

        return is_array( $row ) ? $row : null;
    }

    public static function save_state( int $source_post_id, array $changes ): bool|WP_Error {
        global $wpdb;

        $post = get_post( $source_post_id );
        if ( ! $post instanceof WP_Post || 'mvm_bron' !== $post->post_type || 'publish' !== $post->post_status ) {
            return new WP_Error( 'mvm_hub4_invalid_source', 'Deze bron bestaat niet of is niet gepubliceerd.' );
        }

        $current = self::get_state( $source_post_id );
        $old_enabled = (int) ( $current['monitor_enabled'] ?? 0 );
        $old_status  = (string) ( $current['status'] ?? 'active' );

        $data = array(
            'source_post_id'   => $source_post_id,
            'monitor_enabled'  => isset( $changes['monitor_enabled'] ) ? (int) (bool) $changes['monitor_enabled'] : $old_enabled,
            'category'         => isset( $changes['category'] ) ? self::sanitize_category( (string) $changes['category'] ) : (string) ( $current['category'] ?? 'overig' ),
            'frequency'        => isset( $changes['frequency'] ) ? self::sanitize_frequency( (string) $changes['frequency'] ) : (string) ( $current['frequency'] ?? 'weekly' ),
            'status'           => isset( $changes['status'] ) ? self::sanitize_status( (string) $changes['status'] ) : $old_status,
            'last_checked_utc' => $current['last_checked_utc'] ?? null,
            'last_checked_by'  => (int) ( $current['last_checked_by'] ?? 0 ),
            'next_check_utc'   => $current['next_check_utc'] ?? null,
            'private_note'     => $current['private_note'] ?? null,
            'updated_at_utc'   => current_time( 'mysql', true ),
        );

        if ( array_key_exists( 'private_note', $changes ) ) {
            $data['private_note'] = mb_substr( sanitize_textarea_field( (string) $changes['private_note'] ), 0, 4000 );
        }

        // Pause/stop is explicit: no automatic due date remains in the queue.
        if ( ! $data['monitor_enabled'] || 'active' !== $data['status'] ) {
            $data['next_check_utc'] = null;
        } else {
            $resumed = 0 === $old_enabled || 'active' !== $old_status;
            $schedule_changed = isset( $changes['frequency'] ) || isset( $changes['monitor_enabled'] ) || isset( $changes['status'] );
            if ( $resumed ) {
                // Resume catches up on the next scheduler tick instead of waiting a full interval.
                $data['next_check_utc'] = gmdate( 'Y-m-d H:i:s', time() );
            } elseif ( $schedule_changed || ! $data['next_check_utc'] ) {
                $data['next_check_utc'] = self::next_check_for_frequency( $data['frequency'] );
            }
        }

        $result = $wpdb->replace(
            self::table_name(),
            $data,
            array( '%d', '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s' )
        );

        if ( false === $result ) {
            return new WP_Error( 'mvm_hub4_source_save_failed', 'De bronstatus kon niet worden opgeslagen.' );
        }

        return true;
    }

    public static function mark_checked( int $source_post_id, int $user_id ): bool|WP_Error {
        global $wpdb;

        $state = self::get_state( $source_post_id );
        if ( ! $state ) {
            $created = self::save_state(
                $source_post_id,
                array(
                    'monitor_enabled' => true,
                    'status'          => 'active',
                )
            );
            if ( is_wp_error( $created ) ) {
                return $created;
            }
            $state = self::get_state( $source_post_id );
        }

        $frequency = self::sanitize_frequency( (string) ( $state['frequency'] ?? 'weekly' ) );
        $now       = time();
        $next      = self::next_check_for_frequency( $frequency, $now );
        if ( empty( $state['monitor_enabled'] ) || 'active' !== (string) ( $state['status'] ?? '' ) ) {
            $next = null;
        }

        $updated = $wpdb->update(
            self::table_name(),
            array(
                'last_checked_utc' => gmdate( 'Y-m-d H:i:s', $now ),
                'last_checked_by'  => absint( $user_id ),
                'next_check_utc'   => $next,
                'updated_at_utc'   => gmdate( 'Y-m-d H:i:s', $now ),
            ),
            array( 'source_post_id' => absint( $source_post_id ) ),
            array( '%s', '%d', '%s', '%s' ),
            array( '%d' )
        );

        if ( false === $updated ) {
            return new WP_Error( 'mvm_hub4_source_check_failed', 'De bron kon niet als gecontroleerd worden gemarkeerd.' );
        }

        return true;
    }
}
