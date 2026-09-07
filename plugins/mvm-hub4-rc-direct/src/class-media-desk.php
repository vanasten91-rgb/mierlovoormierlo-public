<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class MvM_Hub4_Media_Desk {
    private const STATUSES = array( 'requested', 'assigned', 'uploaded', 'review', 'approved', 'rejected', 'used' );
    private const KINDS = array( 'photo', 'video', 'audio', 'document' );
    private const CONSENT = array( 'unknown', 'not_required', 'obtained', 'restricted' );

    public static function list_for_current_user( array $args = array() ): array {
        global $wpdb;
        $table = MvM_Hub4_Platform_Schema::table( 'media_items' );
        $where = array( '1=1' );
        $params = array();
        $status = sanitize_key( (string) ( $args['status'] ?? '' ) );
        $limit = min( 100, max( 1, absint( $args['limit'] ?? 50 ) ) );

        if ( in_array( $status, self::STATUSES, true ) ) {
            $where[] = 'status = %s';
            $params[] = $status;
        }
        if ( ! self::can_review_all() ) {
            $where[] = '(photographer_user_id = %d OR created_by_user_id = %d)';
            $params[] = get_current_user_id();
            $params[] = get_current_user_id();
        }

        $sql = "SELECT * FROM {$table} WHERE " . implode( ' AND ', $where ) . ' ORDER BY updated_at_utc DESC, id DESC LIMIT %d';
        $params[] = $limit;
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- fixed table and prepared values.
        $rows = $wpdb->get_results( $wpdb->prepare( $sql, ...$params ), ARRAY_A );
        return array_values( array_map( array( __CLASS__, 'row' ), is_array( $rows ) ? $rows : array() ) );
    }

    public static function create( array $input ): array|WP_Error {
        global $wpdb;
        if ( ! current_user_can( MvM_Hub4_Capabilities::MEDIA_MANAGE ) && ! current_user_can( 'manage_options' ) ) {
            return new WP_Error( 'mvm_hub4_media_denied', 'Je mag geen media-item maken.', array( 'status' => 403 ) );
        }
        $title = trim( sanitize_text_field( (string) ( $input['title'] ?? '' ) ) );
        if ( mb_strlen( $title ) < 3 || mb_strlen( $title ) > 190 ) {
            return new WP_Error( 'mvm_hub4_media_title', 'Vul een duidelijke mediatitel in.', array( 'status' => 400 ) );
        }
        $kind = sanitize_key( (string) ( $input['kind'] ?? 'photo' ) );
        if ( ! in_array( $kind, self::KINDS, true ) ) {
            return new WP_Error( 'mvm_hub4_media_kind', 'Ongeldig mediatype.', array( 'status' => 400 ) );
        }
        $photographer = absint( $input['photographerUserId'] ?? get_current_user_id() );
        if ( ! self::can_review_all() && $photographer !== get_current_user_id() ) {
            return new WP_Error( 'mvm_hub4_media_owner_denied', 'Je mag alleen media voor jezelf beheren.', array( 'status' => 403 ) );
        }
        $check = self::validate_user( $photographer );
        if ( is_wp_error( $check ) ) {
            return $check;
        }
        $attachment = absint( $input['attachmentId'] ?? 0 );
        $attachment_check = self::validate_attachment( $attachment );
        if ( is_wp_error( $attachment_check ) ) {
            return $attachment_check;
        }
        $now = current_time( 'mysql', true );
        $status = $attachment > 0 ? 'uploaded' : 'requested';
        $data = array(
            'status'               => $status,
            'kind'                 => $kind,
            'title'                => $title,
            'attachment_id'        => $attachment,
            'assignment_id'        => absint( $input['assignmentId'] ?? 0 ),
            'dossier_id'           => absint( $input['dossierId'] ?? 0 ),
            'photographer_user_id' => $photographer,
            'credit'               => mb_substr( sanitize_text_field( (string) ( $input['credit'] ?? '' ) ), 0, 190 ),
            'location'             => mb_substr( sanitize_text_field( (string) ( $input['location'] ?? '' ) ), 0, 190 ),
            'captured_at_utc'      => self::sanitize_datetime( $input['capturedAtUtc'] ?? null ),
            'consent_status'       => self::sanitize_consent( (string) ( $input['consentStatus'] ?? 'unknown' ) ),
            'alt_text'             => mb_substr( sanitize_text_field( (string) ( $input['altText'] ?? '' ) ), 0, 500 ),
            'created_by_user_id'   => get_current_user_id(),
            'created_at_utc'       => $now,
            'updated_at_utc'       => $now,
        );
        $ok = $wpdb->insert(
            MvM_Hub4_Platform_Schema::table( 'media_items' ),
            $data,
            array( '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
        );
        if ( false === $ok ) {
            return new WP_Error( 'mvm_hub4_media_save', 'Het media-item kon niet worden opgeslagen.', array( 'status' => 500 ) );
        }
        $id = (int) $wpdb->insert_id;
        MvM_Hub4_Audit::log( 'media.create', 'success', array( 'object_type' => 'media_item', 'object_id' => $id, 'context' => array( 'kind' => $kind, 'status' => $status ) ) );
        return self::get( $id );
    }

    public static function update( int $id, array $input ): array|WP_Error {
        global $wpdb;
        $raw = self::raw( $id );
        if ( ! $raw ) {
            return new WP_Error( 'mvm_hub4_media_missing', 'Dit media-item bestaat niet.', array( 'status' => 404 ) );
        }
        if ( ! self::can_edit( $raw ) ) {
            MvM_Hub4_Audit::log( 'media.update', 'denied', array( 'object_type' => 'media_item', 'object_id' => $id ) );
            return new WP_Error( 'mvm_hub4_media_denied', 'Je mag dit media-item niet wijzigen.', array( 'status' => 403 ) );
        }

        $changes = array( 'updated_at_utc' => current_time( 'mysql', true ) );
        $formats = array( '%s' );
        if ( array_key_exists( 'status', $input ) ) {
            $status = sanitize_key( (string) $input['status'] );
            if ( ! in_array( $status, self::STATUSES, true ) ) {
                return new WP_Error( 'mvm_hub4_media_status', 'Ongeldige mediastatus.', array( 'status' => 400 ) );
            }
            if ( in_array( $status, array( 'approved', 'rejected', 'used' ), true ) && ! self::can_review_all() ) {
                return new WP_Error( 'mvm_hub4_media_review_denied', 'Alleen een reviewer mag deze status instellen.', array( 'status' => 403 ) );
            }
            $changes['status'] = $status;
            $formats[] = '%s';
        }
        if ( array_key_exists( 'attachmentId', $input ) ) {
            $attachment = absint( $input['attachmentId'] );
            $check = self::validate_attachment( $attachment );
            if ( is_wp_error( $check ) ) {
                return $check;
            }
            $changes['attachment_id'] = $attachment;
            $formats[] = '%d';
        }
        foreach ( array( 'credit' => 190, 'location' => 190, 'altText' => 500 ) as $key => $max ) {
            if ( array_key_exists( $key, $input ) ) {
                $db = 'altText' === $key ? 'alt_text' : $key;
                $changes[ $db ] = mb_substr( sanitize_text_field( (string) $input[ $key ] ), 0, $max );
                $formats[] = '%s';
            }
        }
        if ( array_key_exists( 'consentStatus', $input ) ) {
            $changes['consent_status'] = self::sanitize_consent( (string) $input['consentStatus'] );
            $formats[] = '%s';
        }
        if ( array_key_exists( 'capturedAtUtc', $input ) ) {
            $changes['captured_at_utc'] = self::sanitize_datetime( $input['capturedAtUtc'] );
            $formats[] = '%s';
        }
        if ( array_key_exists( 'photographerUserId', $input ) && self::can_review_all() ) {
            $user_id = absint( $input['photographerUserId'] );
            $check = self::validate_user( $user_id );
            if ( is_wp_error( $check ) ) {
                return $check;
            }
            $changes['photographer_user_id'] = $user_id;
            $formats[] = '%d';
        }

        if ( 1 === count( $changes ) ) {
            return new WP_Error( 'mvm_hub4_media_no_changes', 'Geen geldige wijzigingen ontvangen.', array( 'status' => 400 ) );
        }
        $ok = $wpdb->update( MvM_Hub4_Platform_Schema::table( 'media_items' ), $changes, array( 'id' => $id ), $formats, array( '%d' ) );
        if ( false === $ok ) {
            return new WP_Error( 'mvm_hub4_media_update', 'Het media-item kon niet worden bijgewerkt.', array( 'status' => 500 ) );
        }
        MvM_Hub4_Audit::log( 'media.update', 'success', array( 'object_type' => 'media_item', 'object_id' => $id, 'context' => array( 'changed_fields' => implode( ',', array_keys( $changes ) ) ) ) );
        return self::get( $id );
    }

    public static function get( int $id ): array|WP_Error {
        $raw = self::raw( $id );
        if ( ! $raw ) {
            return new WP_Error( 'mvm_hub4_media_missing', 'Dit media-item bestaat niet.', array( 'status' => 404 ) );
        }
        if ( ! self::can_view( $raw ) ) {
            return new WP_Error( 'mvm_hub4_media_denied', 'Je mag dit media-item niet bekijken.', array( 'status' => 403 ) );
        }
        $data = self::row( $raw );
        $data['canManage'] = self::can_edit( $raw );
        $data['canReview'] = self::can_review_all();
        return $data;
    }

    public static function review_count(): int {
        global $wpdb;
        $table = MvM_Hub4_Platform_Schema::table( 'media_items' );
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed table.
        return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE status = %s", 'review' ) );
    }

    private static function raw( int $id ): ?array {
        global $wpdb;
        $table = MvM_Hub4_Platform_Schema::table( 'media_items' );
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed table.
        $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", absint( $id ) ), ARRAY_A );
        return is_array( $row ) ? $row : null;
    }

    private static function row( array $row ): array {
        return array(
            'id'                 => (int) $row['id'],
            'status'             => sanitize_key( (string) $row['status'] ),
            'kind'               => sanitize_key( (string) $row['kind'] ),
            'title'              => sanitize_text_field( (string) $row['title'] ),
            'attachmentId'       => (int) $row['attachment_id'],
            'assignmentId'       => (int) $row['assignment_id'],
            'dossierId'          => (int) $row['dossier_id'],
            'photographerUserId' => (int) $row['photographer_user_id'],
            'credit'             => sanitize_text_field( (string) $row['credit'] ),
            'location'           => sanitize_text_field( (string) $row['location'] ),
            'capturedAtUtc'      => $row['captured_at_utc'] ?: null,
            'consentStatus'      => sanitize_key( (string) $row['consent_status'] ),
            'altText'            => sanitize_text_field( (string) $row['alt_text'] ),
            'updatedAtUtc'       => sanitize_text_field( (string) $row['updated_at_utc'] ),
        );
    }

    private static function can_review_all(): bool {
        return current_user_can( 'manage_options' ) || current_user_can( MvM_Hub4_Capabilities::MEDIA_REVIEW );
    }

    private static function can_view( array $row ): bool {
        if ( ! current_user_can( MvM_Hub4_Capabilities::MEDIA_VIEW ) && ! current_user_can( 'manage_options' ) ) {
            return false;
        }
        return self::can_review_all()
            || get_current_user_id() === (int) $row['photographer_user_id']
            || get_current_user_id() === (int) $row['created_by_user_id'];
    }

    private static function can_edit( array $row ): bool {
        if ( ! current_user_can( MvM_Hub4_Capabilities::MEDIA_MANAGE ) && ! current_user_can( 'manage_options' ) ) {
            return false;
        }
        return self::can_review_all()
            || get_current_user_id() === (int) $row['photographer_user_id']
            || get_current_user_id() === (int) $row['created_by_user_id'];
    }

    private static function validate_attachment( int $attachment_id ): true|WP_Error {
        if ( 0 === $attachment_id ) {
            return true;
        }
        $post = get_post( $attachment_id );
        if ( ! $post instanceof WP_Post || 'attachment' !== $post->post_type ) {
            return new WP_Error( 'mvm_hub4_media_attachment', 'De gekozen bijlage bestaat niet.', array( 'status' => 400 ) );
        }
        if ( ! current_user_can( 'read_post', $attachment_id ) && ! current_user_can( 'manage_options' ) ) {
            return new WP_Error( 'mvm_hub4_media_attachment_denied', 'Je mag deze bijlage niet gebruiken.', array( 'status' => 403 ) );
        }
        return true;
    }

    private static function validate_user( int $user_id ): true|WP_Error {
        if ( 0 === $user_id ) {
            return true;
        }
        $user = get_user_by( 'id', $user_id );
        if ( ! $user instanceof WP_User || ! MvM_Hub4_Security::can_access_hub( $user ) ) {
            return new WP_Error( 'mvm_hub4_media_user', 'De gekozen medewerker heeft geen Hub-toegang.', array( 'status' => 400 ) );
        }
        return true;
    }

    private static function sanitize_consent( string $value ): string {
        $value = sanitize_key( $value );
        return in_array( $value, self::CONSENT, true ) ? $value : 'unknown';
    }

    private static function sanitize_datetime( mixed $value ): ?string {
        if ( null === $value || '' === trim( (string) $value ) ) {
            return null;
        }
        $timestamp = strtotime( (string) $value );
        return false === $timestamp ? null : gmdate( 'Y-m-d H:i:s', $timestamp );
    }
}
