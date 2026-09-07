<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class MvM_Hub4_Assignments {
    private const STATUSES = array( 'signal', 'assigned', 'in_progress', 'review', 'ready', 'scheduled', 'published', 'cancelled' );
    private const TYPES    = array( 'news', 'photo', 'event', 'source', 'general' );

    public static function activate(): void {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $table   = self::table_name();
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            type varchar(20) NOT NULL DEFAULT 'news',
            status varchar(20) NOT NULL DEFAULT 'signal',
            priority tinyint(3) unsigned NOT NULL DEFAULT 2,
            title varchar(190) NOT NULL,
            brief text NULL,
            source_post_id bigint(20) unsigned NOT NULL DEFAULT 0,
            event_post_id bigint(20) unsigned NOT NULL DEFAULT 0,
            news_post_id bigint(20) unsigned NOT NULL DEFAULT 0,
            assignee_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            created_by_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            due_at_utc datetime NULL,
            created_at_utc datetime NOT NULL,
            updated_at_utc datetime NOT NULL,
            completed_at_utc datetime NULL,
            PRIMARY KEY  (id),
            KEY assignee_user_id (assignee_user_id),
            KEY status (status),
            KEY priority (priority),
            KEY due_at_utc (due_at_utc),
            KEY news_post_id (news_post_id),
            KEY source_post_id (source_post_id),
            KEY event_post_id (event_post_id)
        ) {$charset};";

        dbDelta( $sql );
    }

    public static function table_name(): string {
        global $wpdb;
        return $wpdb->prefix . 'mvm_hub4_assignments';
    }

    public static function statuses(): array {
        return array(
            'signal'      => 'Signaal',
            'assigned'    => 'Toegewezen',
            'in_progress' => 'In bewerking',
            'review'      => 'Beoordelen',
            'ready'       => 'Klaar',
            'scheduled'   => 'Ingepland',
            'published'   => 'Gepubliceerd',
            'cancelled'   => 'Geannuleerd',
        );
    }

    public static function list_for_current_user( array $args = array() ): array {
        global $wpdb;

        $user_id = get_current_user_id();
        $can_all = self::can_manage();
        $scope   = sanitize_key( (string) ( $args['scope'] ?? 'mine' ) );
        $status  = sanitize_key( (string) ( $args['status'] ?? '' ) );
        $limit   = min( 50, max( 1, absint( $args['limit'] ?? 20 ) ) );
        $table   = self::table_name();
        $where   = array( '1=1' );
        $params  = array();

        if ( ! $can_all || 'mine' === $scope ) {
            $where[]  = '(assignee_user_id = %d OR created_by_user_id = %d)';
            $params[] = $user_id;
            $params[] = $user_id;
        }

        if ( in_array( $status, self::STATUSES, true ) ) {
            $where[]  = 'status = %s';
            $params[] = $status;
        }

        $sql = "SELECT id,type,status,priority,title,source_post_id,event_post_id,news_post_id,assignee_user_id,created_by_user_id,due_at_utc,created_at_utc,updated_at_utc,completed_at_utc FROM {$table} WHERE " . implode( ' AND ', $where ) . ' ORDER BY CASE WHEN due_at_utc IS NULL THEN 1 ELSE 0 END, due_at_utc ASC, priority ASC, id DESC LIMIT %d';
        $params[] = $limit;

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- fixed internal table name and prepared placeholders.
        $rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );

        return array_values( array_map( array( __CLASS__, 'public_row' ), is_array( $rows ) ? $rows : array() ) );
    }

    public static function create( array $input ): array|WP_Error {
        global $wpdb;

        $can_manage = self::can_manage();
        if ( ! current_user_can( MvM_Hub4_Capabilities::ASSIGNMENT_CREATE ) && ! $can_manage ) {
            return new WP_Error( 'mvm_hub4_assignment_denied', 'Je mag geen redactioneel signaal aanmaken.', array( 'status' => 403 ) );
        }

        $title = trim( sanitize_text_field( (string) ( $input['title'] ?? '' ) ) );
        if ( '' === $title ) {
            return new WP_Error( 'mvm_hub4_assignment_title', 'Vul een duidelijke opdrachttitel in.', array( 'status' => 400 ) );
        }

        $type     = self::sanitize_type( (string) ( $input['type'] ?? 'news' ) );
        $status   = $can_manage ? self::sanitize_status( (string) ( $input['status'] ?? 'signal' ) ) : 'signal';
        $assignee = $can_manage ? absint( $input['assigneeUserId'] ?? 0 ) : get_current_user_id();
        $assignee_check = self::validate_assignee( $assignee );
        if ( is_wp_error( $assignee_check ) ) {
            return $assignee_check;
        }

        $source_post_id = self::validate_related_post_id(
            $input['sourcePostId'] ?? 0,
            'mvm_bron',
            MvM_Hub4_Capabilities::SOURCE_VIEW,
            'bron'
        );
        if ( is_wp_error( $source_post_id ) ) {
            return $source_post_id;
        }

        $event_post_id = self::validate_related_post_id(
            $input['eventPostId'] ?? 0,
            'event_listing',
            MvM_Hub4_Capabilities::AGENDA_VIEW,
            'evenement'
        );
        if ( is_wp_error( $event_post_id ) ) {
            return $event_post_id;
        }

        $news_post_id = self::validate_related_post_id(
            $input['newsPostId'] ?? 0,
            'post',
            MvM_Hub4_Capabilities::NEWS_VIEW,
            'nieuwsartikel'
        );
        if ( is_wp_error( $news_post_id ) ) {
            return $news_post_id;
        }

        $now  = current_time( 'mysql', true );
        $data = array(
            'type'               => $type,
            'status'             => $status,
            'priority'           => $can_manage ? min( 4, max( 1, absint( $input['priority'] ?? 2 ) ) ) : 2,
            'title'              => mb_substr( $title, 0, 190 ),
            'brief'              => mb_substr( sanitize_textarea_field( (string) ( $input['brief'] ?? '' ) ), 0, 8000 ),
            'source_post_id'     => $source_post_id,
            'event_post_id'      => $event_post_id,
            'news_post_id'       => $news_post_id,
            'assignee_user_id'   => $assignee,
            'created_by_user_id' => get_current_user_id(),
            'due_at_utc'         => $can_manage ? self::sanitize_datetime( $input['dueAtUtc'] ?? null ) : null,
            'created_at_utc'     => $now,
            'updated_at_utc'     => $now,
            'completed_at_utc'   => in_array( $status, array( 'published', 'cancelled' ), true ) ? $now : null,
        );

        $ok = $wpdb->insert(
            self::table_name(),
            $data,
            array( '%s', '%s', '%d', '%s', '%s', '%d', '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s' )
        );
        if ( false === $ok ) {
            return new WP_Error( 'mvm_hub4_assignment_create_failed', 'De opdracht kon niet worden opgeslagen.', array( 'status' => 500 ) );
        }

        $id = (int) $wpdb->insert_id;
        MvM_Hub4_Audit::log(
            'assignment.create',
            'success',
            array(
                'object_type' => 'assignment',
                'object_id'   => $id,
                'context'     => array( 'type' => $type, 'status' => $status, 'assignee_user_id' => $assignee ),
            )
        );

        return self::get_public( $id );
    }

    public static function update( int $id, array $input ): array|WP_Error {
        global $wpdb;

        $row = self::get_raw( $id );
        if ( ! $row ) {
            return new WP_Error( 'mvm_hub4_assignment_missing', 'Deze opdracht bestaat niet.', array( 'status' => 404 ) );
        }

        $user_id = get_current_user_id();
        $can_all = self::can_manage();
        $is_own  = $user_id && $user_id === (int) $row['assignee_user_id'];

        if ( ! $can_all && ! $is_own ) {
            MvM_Hub4_Audit::log( 'assignment.update', 'denied', array( 'object_type' => 'assignment', 'object_id' => $id ) );
            return new WP_Error( 'mvm_hub4_assignment_denied', 'Je mag deze opdracht niet wijzigen.', array( 'status' => 403 ) );
        }

        $changes = array( 'updated_at_utc' => current_time( 'mysql', true ) );
        $formats = array( '%s' );

        if ( $can_all ) {
            if ( array_key_exists( 'title', $input ) ) {
                $title = trim( sanitize_text_field( (string) $input['title'] ) );
                if ( '' === $title ) {
                    return new WP_Error( 'mvm_hub4_assignment_title', 'De opdrachttitel mag niet leeg zijn.', array( 'status' => 400 ) );
                }
                $changes['title'] = mb_substr( $title, 0, 190 );
                $formats[] = '%s';
            }
            if ( array_key_exists( 'brief', $input ) ) {
                $changes['brief'] = mb_substr( sanitize_textarea_field( (string) $input['brief'] ), 0, 8000 );
                $formats[] = '%s';
            }
            if ( array_key_exists( 'assigneeUserId', $input ) ) {
                $next_assignee = absint( $input['assigneeUserId'] );
                $assignee_check = self::validate_assignee( $next_assignee );
                if ( is_wp_error( $assignee_check ) ) {
                    return $assignee_check;
                }
                $changes['assignee_user_id'] = $next_assignee;
                $formats[] = '%d';
            }
            if ( array_key_exists( 'priority', $input ) ) {
                $changes['priority'] = min( 4, max( 1, absint( $input['priority'] ) ) );
                $formats[] = '%d';
            }
            if ( array_key_exists( 'dueAtUtc', $input ) ) {
                $changes['due_at_utc'] = self::sanitize_datetime( $input['dueAtUtc'] );
                $formats[] = '%s';
            }
        }

        if ( array_key_exists( 'status', $input ) ) {
            $next = self::sanitize_status( (string) $input['status'] );
            if ( ! $can_all && ! self::own_transition_allowed( (string) $row['status'], $next ) ) {
                return new WP_Error( 'mvm_hub4_assignment_transition', 'Deze statusstap is niet toegestaan voor jouw rol.', array( 'status' => 403 ) );
            }
            $changes['status'] = $next;
            $formats[] = '%s';
            if ( in_array( $next, array( 'published', 'cancelled' ), true ) ) {
                $changes['completed_at_utc'] = current_time( 'mysql', true );
                $formats[] = '%s';
            }
        }

        $ok = $wpdb->update( self::table_name(), $changes, array( 'id' => $id ), $formats, array( '%d' ) );
        if ( false === $ok ) {
            return new WP_Error( 'mvm_hub4_assignment_update_failed', 'De opdracht kon niet worden bijgewerkt.', array( 'status' => 500 ) );
        }

        MvM_Hub4_Audit::log(
            'assignment.update',
            'success',
            array(
                'object_type' => 'assignment',
                'object_id'   => $id,
                'context'     => array(
                    'from_status' => (string) $row['status'],
                    'to_status'   => (string) ( $changes['status'] ?? $row['status'] ),
                ),
            )
        );

        return self::get_public( $id );
    }

    public static function get_public( int $id ): array|WP_Error {
        $row = self::get_raw( $id );
        if ( ! $row ) {
            return new WP_Error( 'mvm_hub4_assignment_missing', 'Deze opdracht bestaat niet.', array( 'status' => 404 ) );
        }

        $user_id = get_current_user_id();
        $can_all = self::can_manage();
        if ( ! $can_all && $user_id !== (int) $row['assignee_user_id'] && $user_id !== (int) $row['created_by_user_id'] ) {
            return new WP_Error( 'mvm_hub4_assignment_denied', 'Je mag deze opdracht niet bekijken.', array( 'status' => 403 ) );
        }

        return self::public_row( $row );
    }

    private static function get_raw( int $id ): ?array {
        global $wpdb;
        $table = self::table_name();
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed internal table name.
        $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", absint( $id ) ), ARRAY_A );
        return is_array( $row ) ? $row : null;
    }

    private static function public_row( array $row ): array {
        return array(
            'id'              => (int) $row['id'],
            'type'            => sanitize_key( (string) $row['type'] ),
            'status'          => sanitize_key( (string) $row['status'] ),
            'statusLabel'     => self::statuses()[ (string) $row['status'] ] ?? (string) $row['status'],
            'priority'        => (int) $row['priority'],
            'title'           => sanitize_text_field( (string) $row['title'] ),
            'sourcePostId'    => (int) $row['source_post_id'],
            'eventPostId'     => (int) $row['event_post_id'],
            'newsPostId'      => (int) $row['news_post_id'],
            'assigneeUserId'  => (int) $row['assignee_user_id'],
            'createdByUserId' => (int) $row['created_by_user_id'],
            'dueAtUtc'        => $row['due_at_utc'] ?: null,
            'updatedAtUtc'    => $row['updated_at_utc'],
        );
    }

    private static function sanitize_type( string $type ): string {
        $type = sanitize_key( $type );
        return in_array( $type, self::TYPES, true ) ? $type : 'news';
    }

    private static function sanitize_status( string $status ): string {
        $status = sanitize_key( $status );
        return in_array( $status, self::STATUSES, true ) ? $status : 'signal';
    }

    private static function sanitize_datetime( mixed $value ): ?string {
        if ( null === $value || '' === $value ) {
            return null;
        }
        $timestamp = strtotime( (string) $value );
        return false === $timestamp ? null : gmdate( 'Y-m-d H:i:s', $timestamp );
    }

    private static function own_transition_allowed( string $from, string $to ): bool {
        $allowed = array(
            'signal'      => array( 'in_progress' ),
            'assigned'    => array( 'in_progress' ),
            'in_progress' => array( 'review' ),
            'review'      => array( 'in_progress' ),
        );
        return isset( $allowed[ $from ] ) && in_array( $to, $allowed[ $from ], true );
    }

    private static function validate_assignee( int $user_id ): true|WP_Error {
        if ( 0 === $user_id ) {
            return true;
        }

        $user = get_user_by( 'id', $user_id );
        if ( ! $user instanceof WP_User ) {
            return new WP_Error( 'mvm_hub4_assignment_user', 'De gekozen medewerker bestaat niet.', array( 'status' => 400 ) );
        }

        if (
            ! MvM_Hub4_Security::can_access_hub( $user )
            || ( ! user_can( $user, MvM_Hub4_Capabilities::ASSIGNMENT_VIEW ) && ! user_can( $user, 'manage_options' ) )
        ) {
            return new WP_Error( 'mvm_hub4_assignment_user_access', 'De gekozen medewerker heeft geen toegang tot Hub-opdrachten.', array( 'status' => 400 ) );
        }

        return true;
    }

    private static function validate_related_post_id( mixed $value, string $expected_type, string $capability, string $label ): int|WP_Error {
        $post_id = absint( $value );
        if ( 0 === $post_id ) {
            return 0;
        }

        $post = get_post( $post_id );
        if ( ! $post instanceof WP_Post || $expected_type !== $post->post_type ) {
            return new WP_Error(
                'mvm_hub4_assignment_related_object',
                sprintf( 'Het gekoppelde %s bestaat niet.', sanitize_text_field( $label ) ),
                array( 'status' => 400 )
            );
        }

        $can_read = current_user_can( 'manage_options' )
            || current_user_can( 'edit_post', $post_id )
            || ( current_user_can( $capability ) && current_user_can( 'read_post', $post_id ) );

        if ( ! $can_read ) {
            MvM_Hub4_Audit::log(
                'assignment.related_object',
                'denied',
                array( 'object_type' => $expected_type, 'object_id' => $post_id )
            );
            return new WP_Error( 'mvm_hub4_assignment_related_denied', 'Je mag dit gekoppelde item niet gebruiken.', array( 'status' => 403 ) );
        }

        return $post_id;
    }

    private static function can_manage(): bool {
        return current_user_can( MvM_Hub4_Capabilities::ASSIGNMENT_MANAGE ) || current_user_can( 'manage_options' );
    }
}
