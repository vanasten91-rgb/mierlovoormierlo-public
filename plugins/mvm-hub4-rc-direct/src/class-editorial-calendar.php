<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class MvM_Hub4_Editorial_Calendar {
    private const KINDS = array( 'news', 'event', 'photo', 'review', 'deadline', 'distribution', 'safety' );
    private const STATUSES = array( 'planned', 'confirmed', 'ready', 'done', 'cancelled' );

    public static function list_for_current_user( array $args = array() ): array {
        global $wpdb;
        $table = MvM_Hub4_Platform_Schema::table( 'editorial_calendar' );
        $where = array( '1=1' );
        $params = array();
        $from = self::sanitize_datetime( $args['from'] ?? gmdate( 'Y-m-d 00:00:00', time() - WEEK_IN_SECONDS ) );
        $to = self::sanitize_datetime( $args['to'] ?? gmdate( 'Y-m-d 23:59:59', time() + ( 60 * DAY_IN_SECONDS ) ) );
        $owner = absint( $args['owner'] ?? 0 );
        $kind = sanitize_key( (string) ( $args['kind'] ?? '' ) );
        $status = sanitize_key( (string) ( $args['status'] ?? '' ) );
        $limit = min( 250, max( 1, absint( $args['limit'] ?? 100 ) ) );

        if ( $from ) {
            $where[] = 'starts_at_utc >= %s';
            $params[] = $from;
        }
        if ( $to ) {
            $where[] = 'starts_at_utc <= %s';
            $params[] = $to;
        }
        if ( $owner > 0 ) {
            $where[] = 'owner_user_id = %d';
            $params[] = $owner;
        }
        if ( in_array( $kind, self::KINDS, true ) ) {
            $where[] = 'kind = %s';
            $params[] = $kind;
        }
        if ( in_array( $status, self::STATUSES, true ) ) {
            $where[] = 'status = %s';
            $params[] = $status;
        }
        if ( ! self::can_manage_all() ) {
            $where[] = '(owner_user_id = %d OR created_by_user_id = %d)';
            $params[] = get_current_user_id();
            $params[] = get_current_user_id();
        }

        $sql = "SELECT * FROM {$table} WHERE " . implode( ' AND ', $where ) . ' ORDER BY starts_at_utc ASC, id ASC LIMIT %d';
        $params[] = $limit;
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- fixed table and prepared values.
        $rows = $wpdb->get_results( $wpdb->prepare( $sql, ...$params ), ARRAY_A );
        return array_values( array_map( array( __CLASS__, 'row' ), is_array( $rows ) ? $rows : array() ) );
    }

    public static function create( array $input ): array|WP_Error {
        global $wpdb;
        if ( ! current_user_can( MvM_Hub4_Capabilities::CALENDAR_MANAGE ) && ! current_user_can( 'manage_options' ) ) {
            return new WP_Error( 'mvm_hub4_calendar_denied', 'Je mag de redactiekalender niet wijzigen.', array( 'status' => 403 ) );
        }
        $validated = self::validate_input( $input, false );
        if ( is_wp_error( $validated ) ) {
            return $validated;
        }
        $now = current_time( 'mysql', true );
        $data = $validated + array(
            'created_by_user_id' => get_current_user_id(),
            'created_at_utc'     => $now,
            'updated_at_utc'     => $now,
        );
        $ok = $wpdb->insert(
            MvM_Hub4_Platform_Schema::table( 'editorial_calendar' ),
            $data,
            array( '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%d', '%s', '%s' )
        );
        if ( false === $ok ) {
            return new WP_Error( 'mvm_hub4_calendar_save', 'Het kalenderitem kon niet worden opgeslagen.', array( 'status' => 500 ) );
        }
        $id = (int) $wpdb->insert_id;
        MvM_Hub4_Audit::log( 'calendar.create', 'success', array( 'object_type' => 'calendar_item', 'object_id' => $id, 'context' => array( 'kind' => $data['kind'] ) ) );
        return self::get( $id );
    }

    public static function update( int $id, array $input ): array|WP_Error {
        global $wpdb;
        $raw = self::raw( $id );
        if ( ! $raw ) {
            return new WP_Error( 'mvm_hub4_calendar_missing', 'Dit kalenderitem bestaat niet.', array( 'status' => 404 ) );
        }
        if ( ! self::can_edit( $raw ) ) {
            MvM_Hub4_Audit::log( 'calendar.update', 'denied', array( 'object_type' => 'calendar_item', 'object_id' => $id ) );
            return new WP_Error( 'mvm_hub4_calendar_denied', 'Je mag dit kalenderitem niet wijzigen.', array( 'status' => 403 ) );
        }
        $changes = self::validate_input( $input, true );
        if ( is_wp_error( $changes ) ) {
            return $changes;
        }
        if ( ! $changes ) {
            return new WP_Error( 'mvm_hub4_calendar_no_changes', 'Geen geldige wijzigingen ontvangen.', array( 'status' => 400 ) );
        }
        $changes['updated_at_utc'] = current_time( 'mysql', true );
        $formats = array();
        foreach ( array_keys( $changes ) as $key ) {
            $formats[] = in_array( $key, array( 'dossier_id', 'post_id', 'assignment_id', 'owner_user_id' ), true ) ? '%d' : '%s';
        }
        $ok = $wpdb->update( MvM_Hub4_Platform_Schema::table( 'editorial_calendar' ), $changes, array( 'id' => $id ), $formats, array( '%d' ) );
        if ( false === $ok ) {
            return new WP_Error( 'mvm_hub4_calendar_update', 'Het kalenderitem kon niet worden bijgewerkt.', array( 'status' => 500 ) );
        }
        MvM_Hub4_Audit::log( 'calendar.update', 'success', array( 'object_type' => 'calendar_item', 'object_id' => $id, 'context' => array( 'changed_fields' => implode( ',', array_keys( $changes ) ) ) ) );
        return self::get( $id );
    }

    public static function get( int $id ): array|WP_Error {
        $raw = self::raw( $id );
        if ( ! $raw ) {
            return new WP_Error( 'mvm_hub4_calendar_missing', 'Dit kalenderitem bestaat niet.', array( 'status' => 404 ) );
        }
        if ( ! self::can_view( $raw ) ) {
            return new WP_Error( 'mvm_hub4_calendar_denied', 'Je mag dit kalenderitem niet bekijken.', array( 'status' => 403 ) );
        }
        $data = self::row( $raw );
        $data['canManage'] = self::can_edit( $raw );
        return $data;
    }

    public static function upcoming_count( int $days = 7 ): int {
        global $wpdb;
        $table = MvM_Hub4_Platform_Schema::table( 'editorial_calendar' );
        $from = current_time( 'mysql', true );
        $to = gmdate( 'Y-m-d H:i:s', time() + ( max( 1, $days ) * DAY_IN_SECONDS ) );
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed internal table.
        return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE starts_at_utc BETWEEN %s AND %s AND status NOT IN ('done','cancelled')", $from, $to ) );
    }

    private static function validate_input( array $input, bool $partial ): array|WP_Error {
        $out = array();
        if ( ! $partial || array_key_exists( 'kind', $input ) ) {
            $kind = sanitize_key( (string) ( $input['kind'] ?? 'news' ) );
            if ( ! in_array( $kind, self::KINDS, true ) ) {
                return new WP_Error( 'mvm_hub4_calendar_kind', 'Ongeldig kalendertype.', array( 'status' => 400 ) );
            }
            $out['kind'] = $kind;
        }
        if ( ! $partial || array_key_exists( 'status', $input ) ) {
            $status = sanitize_key( (string) ( $input['status'] ?? 'planned' ) );
            if ( ! in_array( $status, self::STATUSES, true ) ) {
                return new WP_Error( 'mvm_hub4_calendar_status', 'Ongeldige kalenderstatus.', array( 'status' => 400 ) );
            }
            $out['status'] = $status;
        }
        if ( ! $partial || array_key_exists( 'title', $input ) ) {
            $title = trim( sanitize_text_field( (string) ( $input['title'] ?? '' ) ) );
            if ( mb_strlen( $title ) < 3 || mb_strlen( $title ) > 190 ) {
                return new WP_Error( 'mvm_hub4_calendar_title', 'Vul een geldige titel in.', array( 'status' => 400 ) );
            }
            $out['title'] = $title;
        }
        if ( ! $partial || array_key_exists( 'startsAtUtc', $input ) ) {
            $start = self::sanitize_datetime( $input['startsAtUtc'] ?? null );
            if ( ! $start ) {
                return new WP_Error( 'mvm_hub4_calendar_start', 'Een geldige startdatum is verplicht.', array( 'status' => 400 ) );
            }
            $out['starts_at_utc'] = $start;
        }
        if ( array_key_exists( 'endsAtUtc', $input ) || ! $partial ) {
            $end = self::sanitize_datetime( $input['endsAtUtc'] ?? null );
            $out['ends_at_utc'] = $end;
        }
        foreach ( array( 'dossierId' => 'dossier_id', 'postId' => 'post_id', 'assignmentId' => 'assignment_id' ) as $input_key => $db_key ) {
            if ( array_key_exists( $input_key, $input ) || ! $partial ) {
                $out[ $db_key ] = absint( $input[ $input_key ] ?? 0 );
            }
        }
        if ( array_key_exists( 'ownerUserId', $input ) || ! $partial ) {
            $owner = absint( $input['ownerUserId'] ?? get_current_user_id() );
            if ( $owner > 0 ) {
                $user = get_user_by( 'id', $owner );
                if ( ! $user instanceof WP_User || ! MvM_Hub4_Security::can_access_hub( $user ) ) {
                    return new WP_Error( 'mvm_hub4_calendar_owner', 'De gekozen medewerker heeft geen Hub-toegang.', array( 'status' => 400 ) );
                }
            }
            if ( ! self::can_manage_all() && $owner !== get_current_user_id() ) {
                return new WP_Error( 'mvm_hub4_calendar_owner_denied', 'Je mag alleen voor jezelf plannen.', array( 'status' => 403 ) );
            }
            $out['owner_user_id'] = $owner;
        }
        return $out;
    }

    private static function sanitize_datetime( mixed $value ): ?string {
        if ( null === $value || '' === trim( (string) $value ) ) {
            return null;
        }
        $timestamp = strtotime( (string) $value );
        return false === $timestamp ? null : gmdate( 'Y-m-d H:i:s', $timestamp );
    }

    private static function raw( int $id ): ?array {
        global $wpdb;
        $table = MvM_Hub4_Platform_Schema::table( 'editorial_calendar' );
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed internal table.
        $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", absint( $id ) ), ARRAY_A );
        return is_array( $row ) ? $row : null;
    }

    private static function row( array $row ): array {
        return array(
            'id'              => (int) $row['id'],
            'kind'            => sanitize_key( (string) $row['kind'] ),
            'status'          => sanitize_key( (string) $row['status'] ),
            'title'           => sanitize_text_field( (string) $row['title'] ),
            'startsAtUtc'     => sanitize_text_field( (string) $row['starts_at_utc'] ),
            'endsAtUtc'       => $row['ends_at_utc'] ?: null,
            'dossierId'       => (int) $row['dossier_id'],
            'postId'          => (int) $row['post_id'],
            'assignmentId'    => (int) $row['assignment_id'],
            'ownerUserId'     => (int) $row['owner_user_id'],
            'createdByUserId' => (int) $row['created_by_user_id'],
            'updatedAtUtc'    => sanitize_text_field( (string) $row['updated_at_utc'] ),
        );
    }

    private static function can_manage_all(): bool {
        return current_user_can( 'manage_options' )
            || current_user_can( MvM_Hub4_Capabilities::ASSIGNMENT_MANAGE )
            || current_user_can( MvM_Hub4_Capabilities::DOSSIER_PUBLISH );
    }

    private static function can_view( array $row ): bool {
        if ( ! current_user_can( MvM_Hub4_Capabilities::CALENDAR_VIEW ) && ! current_user_can( 'manage_options' ) ) {
            return false;
        }
        return self::can_manage_all()
            || get_current_user_id() === (int) $row['owner_user_id']
            || get_current_user_id() === (int) $row['created_by_user_id'];
    }

    private static function can_edit( array $row ): bool {
        if ( ! current_user_can( MvM_Hub4_Capabilities::CALENDAR_MANAGE ) && ! current_user_can( 'manage_options' ) ) {
            return false;
        }
        return self::can_manage_all()
            || get_current_user_id() === (int) $row['owner_user_id']
            || get_current_user_id() === (int) $row['created_by_user_id'];
    }
}
