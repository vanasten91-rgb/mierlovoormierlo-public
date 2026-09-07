<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class MvM_Hub4_Corrections {
    private const STATUSES = array( 'new', 'reviewing', 'accepted', 'rejected', 'published' );

    public static function list_for_current_user( array $args = array() ): array {
        global $wpdb;
        $table = MvM_Hub4_Platform_Schema::table( 'corrections' );
        $where = array( '1=1' );
        $params = array();
        $status = sanitize_key( (string) ( $args['status'] ?? '' ) );
        $post_id = absint( $args['postId'] ?? 0 );
        $limit = min( 100, max( 1, absint( $args['limit'] ?? 50 ) ) );

        if ( in_array( $status, self::STATUSES, true ) ) {
            $where[] = 'status = %s';
            $params[] = $status;
        }
        if ( $post_id > 0 ) {
            $where[] = 'post_id = %d';
            $params[] = $post_id;
        }

        $sql = "SELECT * FROM {$table} WHERE " . implode( ' AND ', $where ) . ' ORDER BY id DESC LIMIT %d';
        $params[] = $limit;
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- fixed table and prepared values.
        $rows = $wpdb->get_results( $wpdb->prepare( $sql, ...$params ), ARRAY_A );
        $include_contact = self::can_manage();
        return array_values(
            array_map(
                static fn( array $row ): array => self::internal_row( $row, $include_contact ),
                is_array( $rows ) ? $rows : array()
            )
        );
    }

    public static function create_internal( array $input ): array|WP_Error {
        if ( ! self::can_manage() ) {
            return new WP_Error( 'mvm_hub4_correction_denied', 'Je mag geen correctieverzoek toevoegen.', array( 'status' => 403 ) );
        }
        $id = self::create( $input, 'hub' );
        return is_wp_error( $id ) ? $id : self::get( $id );
    }

    public static function create_public( array $input ): int|WP_Error {
        return self::create( $input, 'public' );
    }

    private static function create( array $input, string $via ): int|WP_Error {
        global $wpdb;
        $post_id = absint( $input['postId'] ?? 0 );
        $post = get_post( $post_id );
        if ( ! $post instanceof WP_Post || 'post' !== $post->post_type || 'publish' !== $post->post_status ) {
            return new WP_Error( 'mvm_hub4_correction_post', 'Het artikel is niet beschikbaar.', array( 'status' => 400 ) );
        }
        $message = trim( sanitize_textarea_field( (string) ( $input['message'] ?? '' ) ) );
        if ( mb_strlen( $message ) < 10 || mb_strlen( $message ) > 8000 ) {
            return new WP_Error( 'mvm_hub4_correction_message', 'Beschrijf de mogelijke fout duidelijk.', array( 'status' => 400 ) );
        }
        $now = current_time( 'mysql', true );
        $data = array(
            'post_id'          => $post_id,
            'status'           => 'new',
            'message'          => $message,
            'public_note'      => '',
            'submitted_via'    => $via,
            'contact_name'     => mb_substr( sanitize_text_field( (string) ( $input['contactName'] ?? '' ) ), 0, 190 ),
            'contact_email'    => sanitize_email( (string) ( $input['contactEmail'] ?? '' ) ),
            'reviewer_user_id' => 0,
            'created_at_utc'   => $now,
            'updated_at_utc'   => $now,
            'resolved_at_utc'  => null,
        );
        $ok = $wpdb->insert(
            MvM_Hub4_Platform_Schema::table( 'corrections' ),
            $data,
            array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s' )
        );
        if ( false === $ok ) {
            return new WP_Error( 'mvm_hub4_correction_save', 'Het correctieverzoek kon niet worden opgeslagen.', array( 'status' => 500 ) );
        }
        $id = (int) $wpdb->insert_id;
        MvM_Hub4_Audit::log(
            'correction.create',
            'success',
            array(
                'object_type' => 'correction',
                'object_id'   => $id,
                'context'     => array( 'post_id' => $post_id, 'submitted_via' => $via ),
            )
        );
        return $id;
    }

    public static function update( int $id, array $input ): array|WP_Error {
        global $wpdb;
        if ( ! self::can_manage() ) {
            return new WP_Error( 'mvm_hub4_correction_denied', 'Je mag correctieverzoeken niet afhandelen.', array( 'status' => 403 ) );
        }
        $raw = self::raw( $id );
        if ( ! $raw ) {
            return new WP_Error( 'mvm_hub4_correction_missing', 'Dit correctieverzoek bestaat niet.', array( 'status' => 404 ) );
        }

        $changes = array(
            'reviewer_user_id' => get_current_user_id(),
            'updated_at_utc'   => current_time( 'mysql', true ),
        );
        $formats = array( '%d', '%s' );
        if ( array_key_exists( 'status', $input ) ) {
            $status = sanitize_key( (string) $input['status'] );
            if ( ! in_array( $status, self::STATUSES, true ) ) {
                return new WP_Error( 'mvm_hub4_correction_status', 'Ongeldige correctiestatus.', array( 'status' => 400 ) );
            }
            $changes['status'] = $status;
            $formats[] = '%s';
            if ( in_array( $status, array( 'accepted', 'rejected', 'published' ), true ) ) {
                $changes['resolved_at_utc'] = current_time( 'mysql', true );
                $formats[] = '%s';
            }
        }
        if ( array_key_exists( 'publicNote', $input ) ) {
            $changes['public_note'] = mb_substr( sanitize_textarea_field( (string) $input['publicNote'] ), 0, 4000 );
            $formats[] = '%s';
        }

        $status_after = (string) ( $changes['status'] ?? $raw['status'] );
        $note_after = trim( (string) ( $changes['public_note'] ?? $raw['public_note'] ) );
        if ( 'published' === $status_after && mb_strlen( $note_after ) < 3 ) {
            return new WP_Error( 'mvm_hub4_correction_public_note', 'Een gepubliceerde correctie heeft een korte openbare toelichting nodig.', array( 'status' => 400 ) );
        }

        $ok = $wpdb->update( MvM_Hub4_Platform_Schema::table( 'corrections' ), $changes, array( 'id' => $id ), $formats, array( '%d' ) );
        if ( false === $ok ) {
            return new WP_Error( 'mvm_hub4_correction_update', 'Het correctieverzoek kon niet worden bijgewerkt.', array( 'status' => 500 ) );
        }
        if ( 'published' === $status_after ) {
            update_post_meta( (int) $raw['post_id'], '_mvm_hub4_last_material_update_utc', current_time( 'mysql', true ) );
        }
        MvM_Hub4_Audit::log(
            'correction.update',
            'success',
            array(
                'object_type' => 'correction',
                'object_id'   => $id,
                'context'     => array( 'post_id' => (int) $raw['post_id'], 'status' => $status_after ),
            )
        );
        return self::get( $id );
    }

    public static function get( int $id ): array|WP_Error {
        if ( ! current_user_can( MvM_Hub4_Capabilities::CORRECTION_VIEW ) && ! current_user_can( 'manage_options' ) ) {
            return new WP_Error( 'mvm_hub4_correction_denied', 'Je mag dit correctieverzoek niet bekijken.', array( 'status' => 403 ) );
        }
        $raw = self::raw( $id );
        if ( ! $raw ) {
            return new WP_Error( 'mvm_hub4_correction_missing', 'Dit correctieverzoek bestaat niet.', array( 'status' => 404 ) );
        }
        return self::internal_row( $raw, self::can_manage() );
    }

    public static function public_for_post( int $post_id ): array {
        global $wpdb;
        $table = MvM_Hub4_Platform_Schema::table( 'corrections' );
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed table.
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, public_note, resolved_at_utc FROM {$table} WHERE post_id = %d AND status = %s AND public_note <> '' ORDER BY resolved_at_utc ASC, id ASC",
                $post_id,
                'published'
            ),
            ARRAY_A
        );
        return array_values(
            array_map(
                static fn( array $row ): array => array(
                    'id'            => (int) $row['id'],
                    'publicNote'    => sanitize_textarea_field( (string) $row['public_note'] ),
                    'resolvedAtUtc' => sanitize_text_field( (string) $row['resolved_at_utc'] ),
                ),
                is_array( $rows ) ? $rows : array()
            )
        );
    }

    public static function waiting_count(): int {
        global $wpdb;
        $table = MvM_Hub4_Platform_Schema::table( 'corrections' );
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed table.
        return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status IN ('new','reviewing')" );
    }

    private static function raw( int $id ): ?array {
        global $wpdb;
        $table = MvM_Hub4_Platform_Schema::table( 'corrections' );
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed table.
        $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", absint( $id ) ), ARRAY_A );
        return is_array( $row ) ? $row : null;
    }

    private static function internal_row( array $row, bool $include_contact ): array {
        $data = array(
            'id'             => (int) $row['id'],
            'postId'         => (int) $row['post_id'],
            'status'         => sanitize_key( (string) $row['status'] ),
            'message'        => sanitize_textarea_field( (string) $row['message'] ),
            'publicNote'     => sanitize_textarea_field( (string) $row['public_note'] ),
            'submittedVia'   => sanitize_key( (string) $row['submitted_via'] ),
            'reviewerUserId' => (int) $row['reviewer_user_id'],
            'createdAtUtc'   => sanitize_text_field( (string) $row['created_at_utc'] ),
            'updatedAtUtc'   => sanitize_text_field( (string) $row['updated_at_utc'] ),
            'resolvedAtUtc'  => $row['resolved_at_utc'] ?: null,
        );
        if ( $include_contact ) {
            $data['contact'] = array(
                'name'  => sanitize_text_field( (string) $row['contact_name'] ),
                'email' => sanitize_email( (string) $row['contact_email'] ),
            );
        }
        return $data;
    }

    private static function can_manage(): bool {
        return current_user_can( MvM_Hub4_Capabilities::CORRECTION_MANAGE ) || current_user_can( 'manage_options' );
    }
}
