<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class MvM_Hub4_Distribution {
    private const CHANNELS = array( 'facebook', 'whatsapp', 'email', 'peepso', 'other' );
    private const STATUSES = array( 'draft', 'review', 'approved', 'sent', 'cancelled' );

    public static function list_for_current_user( array $args = array() ): array {
        global $wpdb;
        $table = MvM_Hub4_Platform_Schema::table( 'distribution_items' );
        $where = array( '1=1' );
        $params = array();
        $status = sanitize_key( (string) ( $args['status'] ?? '' ) );
        $channel = sanitize_key( (string) ( $args['channel'] ?? '' ) );
        $limit = min( 100, max( 1, absint( $args['limit'] ?? 50 ) ) );
        if ( in_array( $status, self::STATUSES, true ) ) {
            $where[] = 'status = %s';
            $params[] = $status;
        }
        if ( in_array( $channel, self::CHANNELS, true ) ) {
            $where[] = 'channel = %s';
            $params[] = $channel;
        }
        if ( ! self::can_approve() ) {
            $where[] = 'prepared_by_user_id = %d';
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
        if ( ! current_user_can( MvM_Hub4_Capabilities::DISTRIBUTION_PREPARE ) && ! current_user_can( 'manage_options' ) ) {
            return new WP_Error( 'mvm_hub4_distribution_denied', 'Je mag geen distributietekst voorbereiden.', array( 'status' => 403 ) );
        }
        $post_id = absint( $input['postId'] ?? 0 );
        $post = get_post( $post_id );
        if ( ! $post instanceof WP_Post || 'publish' !== $post->post_status || ! current_user_can( 'read_post', $post_id ) ) {
            return new WP_Error( 'mvm_hub4_distribution_post', 'Kies een gepubliceerd bericht dat je mag bekijken.', array( 'status' => 400 ) );
        }
        $channel = sanitize_key( (string) ( $input['channel'] ?? 'other' ) );
        if ( ! in_array( $channel, self::CHANNELS, true ) ) {
            return new WP_Error( 'mvm_hub4_distribution_channel', 'Ongeldig distributiekanaal.', array( 'status' => 400 ) );
        }
        $copy = trim( sanitize_textarea_field( (string) ( $input['copyText'] ?? '' ) ) );
        if ( mb_strlen( $copy ) < 3 || mb_strlen( $copy ) > 10000 ) {
            return new WP_Error( 'mvm_hub4_distribution_copy', 'Vul een geldige distributietekst in.', array( 'status' => 400 ) );
        }
        $target_url = self::sanitize_url( $input['targetUrl'] ?? get_permalink( $post_id ) );
        if ( is_wp_error( $target_url ) ) {
            return $target_url;
        }
        $now = current_time( 'mysql', true );
        $data = array(
            'post_id'              => $post_id,
            'dossier_id'           => absint( $input['dossierId'] ?? 0 ),
            'channel'              => $channel,
            'status'               => 'draft',
            'copy_text'            => $copy,
            'target_url'           => $target_url,
            'prepared_by_user_id'  => get_current_user_id(),
            'approved_by_user_id'  => 0,
            'created_at_utc'       => $now,
            'updated_at_utc'       => $now,
            'sent_at_utc'          => null,
        );
        $ok = $wpdb->insert(
            MvM_Hub4_Platform_Schema::table( 'distribution_items' ),
            $data,
            array( '%d', '%d', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s' )
        );
        if ( false === $ok ) {
            return new WP_Error( 'mvm_hub4_distribution_save', 'De distributietekst kon niet worden opgeslagen.', array( 'status' => 500 ) );
        }
        $id = (int) $wpdb->insert_id;
        MvM_Hub4_Audit::log( 'distribution.create', 'success', array( 'object_type' => 'distribution_item', 'object_id' => $id, 'context' => array( 'channel' => $channel, 'post_id' => $post_id ) ) );
        return self::get( $id );
    }

    public static function update( int $id, array $input ): array|WP_Error {
        global $wpdb;
        $raw = self::raw( $id );
        if ( ! $raw ) {
            return new WP_Error( 'mvm_hub4_distribution_missing', 'Dit distributie-item bestaat niet.', array( 'status' => 404 ) );
        }
        if ( ! self::can_edit( $raw ) ) {
            MvM_Hub4_Audit::log( 'distribution.update', 'denied', array( 'object_type' => 'distribution_item', 'object_id' => $id ) );
            return new WP_Error( 'mvm_hub4_distribution_denied', 'Je mag dit distributie-item niet wijzigen.', array( 'status' => 403 ) );
        }
        $changes = array( 'updated_at_utc' => current_time( 'mysql', true ) );
        $formats = array( '%s' );

        if ( array_key_exists( 'copyText', $input ) ) {
            if ( in_array( (string) $raw['status'], array( 'approved', 'sent' ), true ) && ! self::can_approve() ) {
                return new WP_Error( 'mvm_hub4_distribution_locked', 'Een goedgekeurde tekst mag alleen door een reviewer worden gewijzigd.', array( 'status' => 403 ) );
            }
            $copy = trim( sanitize_textarea_field( (string) $input['copyText'] ) );
            if ( mb_strlen( $copy ) < 3 || mb_strlen( $copy ) > 10000 ) {
                return new WP_Error( 'mvm_hub4_distribution_copy', 'De distributietekst is ongeldig.', array( 'status' => 400 ) );
            }
            $changes['copy_text'] = $copy;
            $formats[] = '%s';
        }
        if ( array_key_exists( 'targetUrl', $input ) ) {
            $url = self::sanitize_url( $input['targetUrl'] );
            if ( is_wp_error( $url ) ) {
                return $url;
            }
            $changes['target_url'] = $url;
            $formats[] = '%s';
        }
        if ( array_key_exists( 'status', $input ) ) {
            $status = sanitize_key( (string) $input['status'] );
            if ( ! in_array( $status, self::STATUSES, true ) ) {
                return new WP_Error( 'mvm_hub4_distribution_status', 'Ongeldige distributiestatus.', array( 'status' => 400 ) );
            }
            if ( in_array( $status, array( 'approved', 'sent', 'cancelled' ), true ) && ! self::can_approve() ) {
                return new WP_Error( 'mvm_hub4_distribution_approve_denied', 'Alleen een bevoegde reviewer mag deze status instellen.', array( 'status' => 403 ) );
            }
            if ( ! self::can_approve() && ! in_array( $status, array( 'draft', 'review' ), true ) ) {
                return new WP_Error( 'mvm_hub4_distribution_transition', 'Deze statusstap is niet toegestaan.', array( 'status' => 403 ) );
            }
            $changes['status'] = $status;
            $formats[] = '%s';
            if ( 'approved' === $status ) {
                $changes['approved_by_user_id'] = get_current_user_id();
                $formats[] = '%d';
            }
            if ( 'sent' === $status ) {
                $changes['approved_by_user_id'] = get_current_user_id();
                $formats[] = '%d';
                $changes['sent_at_utc'] = current_time( 'mysql', true );
                $formats[] = '%s';
            }
        }

        if ( 1 === count( $changes ) ) {
            return new WP_Error( 'mvm_hub4_distribution_no_changes', 'Geen geldige wijzigingen ontvangen.', array( 'status' => 400 ) );
        }
        $ok = $wpdb->update( MvM_Hub4_Platform_Schema::table( 'distribution_items' ), $changes, array( 'id' => $id ), $formats, array( '%d' ) );
        if ( false === $ok ) {
            return new WP_Error( 'mvm_hub4_distribution_update', 'Het distributie-item kon niet worden bijgewerkt.', array( 'status' => 500 ) );
        }
        MvM_Hub4_Audit::log( 'distribution.update', 'success', array( 'object_type' => 'distribution_item', 'object_id' => $id, 'context' => array( 'changed_fields' => implode( ',', array_keys( $changes ) ) ) ) );
        return self::get( $id );
    }

    public static function get( int $id ): array|WP_Error {
        $raw = self::raw( $id );
        if ( ! $raw ) {
            return new WP_Error( 'mvm_hub4_distribution_missing', 'Dit distributie-item bestaat niet.', array( 'status' => 404 ) );
        }
        if ( ! self::can_view( $raw ) ) {
            return new WP_Error( 'mvm_hub4_distribution_denied', 'Je mag dit distributie-item niet bekijken.', array( 'status' => 403 ) );
        }
        $data = self::row( $raw );
        $data['canApprove'] = self::can_approve();
        return $data;
    }

    public static function review_count(): int {
        global $wpdb;
        $table = MvM_Hub4_Platform_Schema::table( 'distribution_items' );
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed table.
        return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE status = %s", 'review' ) );
    }

    private static function raw( int $id ): ?array {
        global $wpdb;
        $table = MvM_Hub4_Platform_Schema::table( 'distribution_items' );
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed table.
        $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", absint( $id ) ), ARRAY_A );
        return is_array( $row ) ? $row : null;
    }

    private static function row( array $row ): array {
        return array(
            'id'               => (int) $row['id'],
            'postId'           => (int) $row['post_id'],
            'dossierId'        => (int) $row['dossier_id'],
            'channel'          => sanitize_key( (string) $row['channel'] ),
            'status'           => sanitize_key( (string) $row['status'] ),
            'copyText'         => sanitize_textarea_field( (string) $row['copy_text'] ),
            'targetUrl'        => esc_url_raw( (string) $row['target_url'] ),
            'preparedByUserId' => (int) $row['prepared_by_user_id'],
            'approvedByUserId' => (int) $row['approved_by_user_id'],
            'updatedAtUtc'     => sanitize_text_field( (string) $row['updated_at_utc'] ),
            'sentAtUtc'        => $row['sent_at_utc'] ?: null,
        );
    }

    private static function can_approve(): bool {
        return current_user_can( 'manage_options' ) || current_user_can( MvM_Hub4_Capabilities::DISTRIBUTION_APPROVE );
    }

    private static function can_view( array $row ): bool {
        if ( ! current_user_can( MvM_Hub4_Capabilities::DISTRIBUTION_VIEW ) && ! current_user_can( 'manage_options' ) ) {
            return false;
        }
        return self::can_approve() || get_current_user_id() === (int) $row['prepared_by_user_id'];
    }

    private static function can_edit( array $row ): bool {
        if ( ! current_user_can( MvM_Hub4_Capabilities::DISTRIBUTION_PREPARE ) && ! current_user_can( 'manage_options' ) ) {
            return false;
        }
        return self::can_approve() || get_current_user_id() === (int) $row['prepared_by_user_id'];
    }

    private static function sanitize_url( mixed $value ): string|WP_Error {
        $url = esc_url_raw( trim( (string) $value ), array( 'http', 'https' ) );
        if ( '' === $url || ! wp_http_validate_url( $url ) ) {
            return new WP_Error( 'mvm_hub4_distribution_url', 'De doel-URL is niet geldig.', array( 'status' => 400 ) );
        }
        return $url;
    }
}
