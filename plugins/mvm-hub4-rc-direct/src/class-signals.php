<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class MvM_Hub4_Signals {
    private const KINDS = array( 'news', 'photo', 'event', 'source', 'safety', 'correction', 'general' );
    private const STATUSES = array( 'new', 'triage', 'assigned', 'in_progress', 'converted', 'closed', 'rejected' );
    private const VERIFICATION = array( 'unverified', 'verified', 'conflicting' );
    private const INCIDENT_STATUS = array( 'unknown', 'ongoing', 'resolved' );

    public static function kinds(): array {
        return self::KINDS;
    }

    public static function statuses(): array {
        return self::STATUSES;
    }

    public static function list_for_current_user( array $args = array() ): array {
        global $wpdb;
        $table = MvM_Hub4_Platform_Schema::table( 'signals' );
        $where = array( '1=1' );
        $params = array();
        $status = sanitize_key( (string) ( $args['status'] ?? '' ) );
        $kind = sanitize_key( (string) ( $args['kind'] ?? '' ) );
        $assignee = absint( $args['assignee'] ?? 0 );
        $limit = min( 100, max( 1, absint( $args['limit'] ?? 50 ) ) );

        if ( in_array( $status, self::STATUSES, true ) ) {
            $where[] = 'status = %s';
            $params[] = $status;
        }
        if ( in_array( $kind, self::KINDS, true ) ) {
            $where[] = 'kind = %s';
            $params[] = $kind;
        }
        if ( $assignee > 0 ) {
            $where[] = 'assignee_user_id = %d';
            $params[] = $assignee;
        }

        $sql = "SELECT * FROM {$table} WHERE " . implode( ' AND ', $where ) . " ORDER BY CASE status WHEN 'new' THEN 0 WHEN 'triage' THEN 1 ELSE 2 END, priority ASC, id DESC LIMIT %d";
        $params[] = $limit;
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- fixed internal table and prepared values.
        $rows = $wpdb->get_results( $wpdb->prepare( $sql, ...$params ), ARRAY_A );
        $include_contact = current_user_can( MvM_Hub4_Capabilities::SIGNAL_TRIAGE ) || current_user_can( 'manage_options' );
        return array_values( array_map( static fn( array $row ): array => self::row( $row, $include_contact ), is_array( $rows ) ? $rows : array() ) );
    }

    public static function get( int $id ): array|WP_Error {
        $row = self::raw( $id );
        if ( ! $row ) {
            return new WP_Error( 'mvm_hub4_signal_missing', 'Dit signaal bestaat niet.', array( 'status' => 404 ) );
        }
        $include_contact = current_user_can( MvM_Hub4_Capabilities::SIGNAL_TRIAGE ) || current_user_can( 'manage_options' );
        return self::row( $row, $include_contact );
    }

    public static function create_internal( array $input ): array|WP_Error {
        if ( ! current_user_can( MvM_Hub4_Capabilities::SIGNAL_CREATE ) && ! current_user_can( 'manage_options' ) ) {
            return new WP_Error( 'mvm_hub4_signal_denied', 'Je mag geen signaal toevoegen.', array( 'status' => 403 ) );
        }
        return self::create( $input, 'hub', get_current_user_id() );
    }

    public static function create_public( array $input ): int|WP_Error {
        $result = self::create( $input, 'public', is_user_logged_in() ? get_current_user_id() : 0 );
        return is_wp_error( $result ) ? $result : (int) $result['id'];
    }

    private static function create( array $input, string $via, int $submitter_user_id ): array|WP_Error {
        global $wpdb;

        $kind = sanitize_key( (string) ( $input['kind'] ?? 'general' ) );
        if ( ! in_array( $kind, self::KINDS, true ) ) {
            $kind = 'general';
        }
        $title = trim( sanitize_text_field( (string) ( $input['title'] ?? '' ) ) );
        $summary = trim( sanitize_textarea_field( (string) ( $input['summary'] ?? '' ) ) );
        if ( mb_strlen( $title ) < 3 || mb_strlen( $title ) > 190 ) {
            return new WP_Error( 'mvm_hub4_signal_title', 'Vul een duidelijke titel in.', array( 'status' => 400 ) );
        }
        if ( mb_strlen( $summary ) > 8000 ) {
            return new WP_Error( 'mvm_hub4_signal_summary', 'De omschrijving is te lang.', array( 'status' => 400 ) );
        }

        $source_url = self::sanitize_url( $input['sourceUrl'] ?? '' );
        if ( is_wp_error( $source_url ) ) {
            return $source_url;
        }
        $location = mb_substr( sanitize_text_field( (string) ( $input['location'] ?? '' ) ), 0, 190 );
        $incident_at = self::sanitize_datetime( $input['incidentAtUtc'] ?? null );
        $verification = 'public' === $via ? 'unverified' : self::sanitize_verification( (string) ( $input['verificationStatus'] ?? 'unverified' ) );
        $incident_status = 'public' === $via
            ? 'unknown'
            : self::sanitize_incident_status( (string) ( $input['incidentStatus'] ?? ( 'safety' === $kind ? 'ongoing' : 'unknown' ) ) );

        if ( 'safety' === $kind && 'hub' === $via ) {
            if ( '' === $location ) {
                return new WP_Error( 'mvm_hub4_safety_location', 'Voor een 112/veiligheidssignaal is een locatie verplicht.', array( 'status' => 400 ) );
            }
            if ( ! $incident_at ) {
                return new WP_Error( 'mvm_hub4_safety_time', 'Voor een 112/veiligheidssignaal is het incidenttijdstip verplicht.', array( 'status' => 400 ) );
            }
            if ( ! in_array( $incident_status, array( 'ongoing', 'resolved' ), true ) ) {
                return new WP_Error( 'mvm_hub4_safety_status', 'Kies of het incident loopt of is afgerond.', array( 'status' => 400 ) );
            }
        }

        $priority = 'public' === $via ? 2 : min( 4, max( 1, absint( $input['priority'] ?? 2 ) ) );
        if ( 'safety' === $kind && 'hub' === $via ) {
            $priority = 1;
        }
        $now = current_time( 'mysql', true );
        $data = array(
            'kind'                => $kind,
            'status'              => 'new',
            'priority'            => $priority,
            'title'               => mb_substr( $title, 0, 190 ),
            'summary'             => mb_substr( $summary, 0, 8000 ),
            'source_url'          => $source_url,
            'location'            => $location,
            'incident_at_utc'     => $incident_at,
            'verification_status' => $verification,
            'incident_status'     => $incident_status,
            'submitted_via'       => $via,
            'submitter_user_id'   => $submitter_user_id,
            'contact_name'        => mb_substr( sanitize_text_field( (string) ( $input['contactName'] ?? '' ) ), 0, 190 ),
            'contact_email'       => sanitize_email( (string) ( $input['contactEmail'] ?? '' ) ),
            'contact_phone'       => mb_substr( preg_replace( '/[^0-9+() .-]/', '', (string) ( $input['contactPhone'] ?? '' ) ) ?? '', 0, 80 ),
            'assignee_user_id'    => 0,
            'dossier_id'          => 0,
            'assignment_id'       => 0,
            'created_by_user_id'  => 'hub' === $via ? get_current_user_id() : 0,
            'created_at_utc'      => $now,
            'updated_at_utc'      => $now,
            'closed_at_utc'       => null,
        );

        $ok = $wpdb->insert(
            MvM_Hub4_Platform_Schema::table( 'signals' ),
            $data,
            array( '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%s', '%s', '%s' )
        );
        if ( false === $ok ) {
            return new WP_Error( 'mvm_hub4_signal_save', 'Het signaal kon niet worden opgeslagen.', array( 'status' => 500 ) );
        }

        $id = (int) $wpdb->insert_id;
        MvM_Hub4_Audit::log(
            'signal.create',
            'success',
            array(
                'object_type' => 'signal',
                'object_id'   => $id,
                'context'     => array(
                    'kind'                => $kind,
                    'submitted_via'       => $via,
                    'priority'            => $priority,
                    'verification_status' => $verification,
                    'incident_status'     => $incident_status,
                ),
            )
        );
        $row = self::raw( $id );
        return self::row( $row ?: $data + array( 'id' => $id ), 'hub' === $via );
    }

    public static function update( int $id, array $input ): array|WP_Error {
        global $wpdb;
        if ( ! current_user_can( MvM_Hub4_Capabilities::SIGNAL_TRIAGE ) && ! current_user_can( 'manage_options' ) ) {
            return new WP_Error( 'mvm_hub4_signal_denied', 'Je mag signalen niet triëren.', array( 'status' => 403 ) );
        }
        $row = self::raw( $id );
        if ( ! $row ) {
            return new WP_Error( 'mvm_hub4_signal_missing', 'Dit signaal bestaat niet.', array( 'status' => 404 ) );
        }

        $changes = array( 'updated_at_utc' => current_time( 'mysql', true ) );
        $formats = array( '%s' );
        if ( array_key_exists( 'status', $input ) ) {
            $status = sanitize_key( (string) $input['status'] );
            if ( ! in_array( $status, self::STATUSES, true ) ) {
                return new WP_Error( 'mvm_hub4_signal_status', 'Ongeldige signaalstatus.', array( 'status' => 400 ) );
            }
            $changes['status'] = $status;
            $formats[] = '%s';
            if ( in_array( $status, array( 'closed', 'rejected', 'converted' ), true ) ) {
                $changes['closed_at_utc'] = current_time( 'mysql', true );
                $formats[] = '%s';
            }
        }
        if ( array_key_exists( 'priority', $input ) ) {
            $changes['priority'] = min( 4, max( 1, absint( $input['priority'] ) ) );
            $formats[] = '%d';
        }
        if ( array_key_exists( 'assigneeUserId', $input ) ) {
            $assignee = absint( $input['assigneeUserId'] );
            if ( $assignee > 0 ) {
                $user = get_user_by( 'id', $assignee );
                if ( ! $user instanceof WP_User || ! MvM_Hub4_Security::can_access_hub( $user ) ) {
                    return new WP_Error( 'mvm_hub4_signal_assignee', 'De gekozen medewerker heeft geen Hub-toegang.', array( 'status' => 400 ) );
                }
            }
            $changes['assignee_user_id'] = $assignee;
            $formats[] = '%d';
        }
        if ( array_key_exists( 'dossierId', $input ) ) {
            $dossier_id = absint( $input['dossierId'] );
            if ( $dossier_id > 0 && is_wp_error( MvM_Hub4_Dossiers::get( $dossier_id ) ) ) {
                return new WP_Error( 'mvm_hub4_signal_dossier', 'Het gekozen dossier bestaat niet of is niet toegankelijk.', array( 'status' => 400 ) );
            }
            $changes['dossier_id'] = $dossier_id;
            $formats[] = '%d';
        }
        if ( array_key_exists( 'verificationStatus', $input ) ) {
            $changes['verification_status'] = self::sanitize_verification( (string) $input['verificationStatus'] );
            $formats[] = '%s';
        }
        if ( array_key_exists( 'incidentStatus', $input ) ) {
            $changes['incident_status'] = self::sanitize_incident_status( (string) $input['incidentStatus'] );
            $formats[] = '%s';
        }
        if ( array_key_exists( 'incidentAtUtc', $input ) ) {
            $incident_at = self::sanitize_datetime( $input['incidentAtUtc'] );
            if ( 'safety' === (string) $row['kind'] && ! $incident_at ) {
                return new WP_Error( 'mvm_hub4_safety_time', 'Een geldig incidenttijdstip is verplicht.', array( 'status' => 400 ) );
            }
            $changes['incident_at_utc'] = $incident_at;
            $formats[] = '%s';
        }
        if ( array_key_exists( 'location', $input ) ) {
            $location = mb_substr( sanitize_text_field( (string) $input['location'] ), 0, 190 );
            if ( 'safety' === (string) $row['kind'] && '' === trim( $location ) ) {
                return new WP_Error( 'mvm_hub4_safety_location', 'Een locatie is verplicht voor 112/veiligheid.', array( 'status' => 400 ) );
            }
            $changes['location'] = $location;
            $formats[] = '%s';
        }
        if ( array_key_exists( 'sourceUrl', $input ) ) {
            $url = self::sanitize_url( $input['sourceUrl'] );
            if ( is_wp_error( $url ) ) {
                return $url;
            }
            $changes['source_url'] = $url;
            $formats[] = '%s';
        }

        if ( 1 === count( $changes ) ) {
            return new WP_Error( 'mvm_hub4_signal_no_changes', 'Geen geldige wijzigingen ontvangen.', array( 'status' => 400 ) );
        }

        $next_incident_status = (string) ( $changes['incident_status'] ?? $row['incident_status'] );
        if ( 'safety' === (string) $row['kind'] && ! in_array( $next_incident_status, array( 'ongoing', 'resolved' ), true ) ) {
            return new WP_Error( 'mvm_hub4_safety_status', 'Een 112/veiligheidssignaal moet lopend of afgerond zijn.', array( 'status' => 400 ) );
        }

        $next_status = (string) ( $changes['status'] ?? $row['status'] );
        $next_dossier_id = (int) ( $changes['dossier_id'] ?? $row['dossier_id'] ?? 0 );
        $assignment_id = (int) ( $row['assignment_id'] ?? 0 );
        if ( 'converted' === $next_status && $next_dossier_id < 1 && $assignment_id < 1 ) {
            return new WP_Error( 'mvm_hub4_signal_convert_relation', 'Een omgezet signaal moet aan een dossier of opdracht gekoppeld zijn.', array( 'status' => 400 ) );
        }

        $ok = $wpdb->update( MvM_Hub4_Platform_Schema::table( 'signals' ), $changes, array( 'id' => $id ), $formats, array( '%d' ) );
        if ( false === $ok ) {
            return new WP_Error( 'mvm_hub4_signal_update', 'Het signaal kon niet worden bijgewerkt.', array( 'status' => 500 ) );
        }

        MvM_Hub4_Audit::log(
            'signal.update',
            'success',
            array(
                'object_type' => 'signal',
                'object_id'   => $id,
                'context'     => array( 'changed_fields' => implode( ',', array_keys( $changes ) ) ),
            )
        );
        return self::get( $id );
    }

    public static function counts(): array {
        global $wpdb;
        $table = MvM_Hub4_Platform_Schema::table( 'signals' );
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed internal table.
        $rows = $wpdb->get_results( "SELECT status, COUNT(*) AS total FROM {$table} GROUP BY status", ARRAY_A );
        $counts = array_fill_keys( self::STATUSES, 0 );
        foreach ( is_array( $rows ) ? $rows : array() as $row ) {
            $key = sanitize_key( (string) $row['status'] );
            if ( isset( $counts[ $key ] ) ) {
                $counts[ $key ] = (int) $row['total'];
            }
        }
        return $counts;
    }

    private static function raw( int $id ): ?array {
        global $wpdb;
        $table = MvM_Hub4_Platform_Schema::table( 'signals' );
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed internal table.
        $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", absint( $id ) ), ARRAY_A );
        return is_array( $row ) ? $row : null;
    }

    private static function row( array $row, bool $include_contact ): array {
        $data = array(
            'id'                 => (int) ( $row['id'] ?? 0 ),
            'kind'               => sanitize_key( (string) ( $row['kind'] ?? 'general' ) ),
            'status'             => sanitize_key( (string) ( $row['status'] ?? 'new' ) ),
            'priority'           => (int) ( $row['priority'] ?? 2 ),
            'title'              => sanitize_text_field( (string) ( $row['title'] ?? '' ) ),
            'summary'            => sanitize_textarea_field( (string) ( $row['summary'] ?? '' ) ),
            'sourceUrl'          => esc_url_raw( (string) ( $row['source_url'] ?? '' ) ),
            'location'           => sanitize_text_field( (string) ( $row['location'] ?? '' ) ),
            'incidentAtUtc'      => $row['incident_at_utc'] ?? null,
            'verificationStatus' => sanitize_key( (string) ( $row['verification_status'] ?? 'unverified' ) ),
            'incidentStatus'     => sanitize_key( (string) ( $row['incident_status'] ?? 'unknown' ) ),
            'submittedVia'       => sanitize_key( (string) ( $row['submitted_via'] ?? 'hub' ) ),
            'assigneeUserId'     => (int) ( $row['assignee_user_id'] ?? 0 ),
            'dossierId'          => (int) ( $row['dossier_id'] ?? 0 ),
            'assignmentId'       => (int) ( $row['assignment_id'] ?? 0 ),
            'createdAtUtc'       => sanitize_text_field( (string) ( $row['created_at_utc'] ?? '' ) ),
            'updatedAtUtc'       => sanitize_text_field( (string) ( $row['updated_at_utc'] ?? '' ) ),
        );
        if ( $include_contact ) {
            $data['contact'] = array(
                'name'  => sanitize_text_field( (string) ( $row['contact_name'] ?? '' ) ),
                'email' => sanitize_email( (string) ( $row['contact_email'] ?? '' ) ),
                'phone' => sanitize_text_field( (string) ( $row['contact_phone'] ?? '' ) ),
            );
        }
        return $data;
    }

    private static function sanitize_url( mixed $value ): string|WP_Error {
        $raw = trim( (string) $value );
        if ( '' === $raw ) {
            return '';
        }
        $url = esc_url_raw( $raw, array( 'http', 'https' ) );
        if ( '' === $url || ! wp_http_validate_url( $url ) ) {
            return new WP_Error( 'mvm_hub4_signal_url', 'De bron-URL is niet geldig.', array( 'status' => 400 ) );
        }
        return $url;
    }

    private static function sanitize_datetime( mixed $value ): ?string {
        if ( null === $value || '' === trim( (string) $value ) ) {
            return null;
        }
        $timestamp = strtotime( (string) $value );
        return false === $timestamp ? null : gmdate( 'Y-m-d H:i:s', $timestamp );
    }

    private static function sanitize_verification( string $value ): string {
        $value = sanitize_key( $value );
        return in_array( $value, self::VERIFICATION, true ) ? $value : 'unverified';
    }

    private static function sanitize_incident_status( string $value ): string {
        $value = sanitize_key( $value );
        return in_array( $value, self::INCIDENT_STATUS, true ) ? $value : 'unknown';
    }
}