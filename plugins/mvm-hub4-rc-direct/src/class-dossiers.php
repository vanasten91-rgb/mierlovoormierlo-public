<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class MvM_Hub4_Dossiers {
    private const STATUSES     = array( 'open', 'research', 'writing', 'review', 'ready', 'published', 'archived' );
    private const VISIBILITIES = array( 'internal', 'public' );
    private const LINK_TYPES   = array( 'post', 'event', 'source', 'media', 'signal', 'assignment', 'encyclopedia', 'url' );

    public static function statuses(): array {
        return self::STATUSES;
    }

    public static function list_for_current_user( array $args = array() ): array {
        global $wpdb;

        if ( ! current_user_can( MvM_Hub4_Capabilities::DOSSIER_VIEW ) && ! current_user_can( 'manage_options' ) ) {
            return array();
        }

        $table  = MvM_Hub4_Platform_Schema::table( 'dossiers' );
        $where  = array( '1=1' );
        $params = array();
        $status = sanitize_key( (string) ( $args['status'] ?? '' ) );
        $limit  = min( 100, max( 1, absint( $args['limit'] ?? 50 ) ) );

        if ( in_array( $status, self::STATUSES, true ) ) {
            $where[]  = 'status = %s';
            $params[] = $status;
        }

        if ( ! self::can_manage_all() ) {
            $user_id = get_current_user_id();
            if ( current_user_can( MvM_Hub4_Capabilities::DOSSIER_MANAGE ) ) {
                $where[]  = '(lead_user_id = %d OR created_by_user_id = %d OR (visibility = %s AND status = %s))';
                $params[] = $user_id;
                $params[] = $user_id;
                $params[] = 'public';
                $params[] = 'published';
            } else {
                $where[]  = '(visibility = %s AND status = %s)';
                $params[] = 'public';
                $params[] = 'published';
            }
        }

        $sql      = "SELECT * FROM {$table} WHERE " . implode( ' AND ', $where ) . ' ORDER BY updated_at_utc DESC, id DESC LIMIT %d';
        $params[] = $limit;
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- fixed internal table and prepared values.
        $rows = $wpdb->get_results( $wpdb->prepare( $sql, ...$params ), ARRAY_A );

        return array_values( array_map( array( __CLASS__, 'internal_row' ), is_array( $rows ) ? $rows : array() ) );
    }

    public static function get( int $id ): array|WP_Error {
        $row = self::raw( $id );
        if ( ! $row ) {
            return new WP_Error( 'mvm_hub4_dossier_missing', 'Dit dossier bestaat niet.', array( 'status' => 404 ) );
        }
        if ( ! self::can_view_row( $row ) ) {
            MvM_Hub4_Audit::log( 'dossier.view', 'denied', array( 'object_type' => 'dossier', 'object_id' => $id ) );
            return new WP_Error( 'mvm_hub4_dossier_denied', 'Je mag dit dossier niet bekijken.', array( 'status' => 403 ) );
        }

        $data               = self::internal_row( $row );
        $data['links']      = self::links( $id, false );
        $data['canManage']  = self::can_edit_row( $row );
        $data['canPublish'] = current_user_can( MvM_Hub4_Capabilities::DOSSIER_PUBLISH ) || current_user_can( 'manage_options' );
        return $data;
    }

    public static function create( array $input ): array|WP_Error {
        global $wpdb;

        if ( ! current_user_can( MvM_Hub4_Capabilities::DOSSIER_MANAGE ) && ! current_user_can( 'manage_options' ) ) {
            return new WP_Error( 'mvm_hub4_dossier_denied', 'Je mag geen dossier maken.', array( 'status' => 403 ) );
        }

        $title = trim( sanitize_text_field( (string) ( $input['title'] ?? '' ) ) );
        if ( mb_strlen( $title ) < 3 || mb_strlen( $title ) > 190 ) {
            return new WP_Error( 'mvm_hub4_dossier_title', 'Vul een duidelijke dossiertitel in.', array( 'status' => 400 ) );
        }

        $lead = absint( $input['leadUserId'] ?? get_current_user_id() );
        $lead_check = self::validate_lead( $lead );
        if ( is_wp_error( $lead_check ) ) {
            return $lead_check;
        }
        if ( ! self::can_manage_all() && $lead !== get_current_user_id() ) {
            return new WP_Error( 'mvm_hub4_dossier_lead_denied', 'Je mag alleen jezelf als dossierhouder instellen.', array( 'status' => 403 ) );
        }

        $category = self::validate_category( absint( $input['categoryTermId'] ?? 0 ) );
        if ( is_wp_error( $category ) ) {
            return $category;
        }

        $now  = current_time( 'mysql', true );
        $data = array(
            'status'             => 'open',
            'visibility'         => 'internal',
            'title'              => $title,
            'slug'               => self::unique_slug( sanitize_title( (string) ( $input['slug'] ?? $title ) ) ),
            'summary'            => mb_substr( sanitize_textarea_field( (string) ( $input['summary'] ?? '' ) ), 0, 8000 ),
            'internal_brief'     => mb_substr( sanitize_textarea_field( (string) ( $input['internalBrief'] ?? '' ) ), 0, 20000 ),
            'category_term_id'   => $category,
            'lead_user_id'       => $lead,
            'created_by_user_id' => get_current_user_id(),
            'created_at_utc'     => $now,
            'updated_at_utc'     => $now,
            'published_at_utc'   => null,
        );

        $ok = $wpdb->insert(
            MvM_Hub4_Platform_Schema::table( 'dossiers' ),
            $data,
            array( '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s', '%s' )
        );
        if ( false === $ok ) {
            return new WP_Error( 'mvm_hub4_dossier_save', 'Het dossier kon niet worden opgeslagen.', array( 'status' => 500 ) );
        }

        $id = (int) $wpdb->insert_id;
        MvM_Hub4_Audit::log( 'dossier.create', 'success', array( 'object_type' => 'dossier', 'object_id' => $id ) );
        return self::get( $id );
    }

    public static function update( int $id, array $input ): array|WP_Error {
        global $wpdb;

        $row = self::raw( $id );
        if ( ! $row ) {
            return new WP_Error( 'mvm_hub4_dossier_missing', 'Dit dossier bestaat niet.', array( 'status' => 404 ) );
        }
        if ( ! self::can_edit_row( $row ) ) {
            MvM_Hub4_Audit::log( 'dossier.update', 'denied', array( 'object_type' => 'dossier', 'object_id' => $id ) );
            return new WP_Error( 'mvm_hub4_dossier_denied', 'Je mag dit dossier niet wijzigen.', array( 'status' => 403 ) );
        }

        $changes = array( 'updated_at_utc' => current_time( 'mysql', true ) );
        $formats = array( '%s' );

        if ( array_key_exists( 'title', $input ) ) {
            $title = trim( sanitize_text_field( (string) $input['title'] ) );
            if ( mb_strlen( $title ) < 3 || mb_strlen( $title ) > 190 ) {
                return new WP_Error( 'mvm_hub4_dossier_title', 'De dossiertitel is ongeldig.', array( 'status' => 400 ) );
            }
            $changes['title'] = $title;
            $formats[]        = '%s';
        }
        if ( array_key_exists( 'summary', $input ) ) {
            $changes['summary'] = mb_substr( sanitize_textarea_field( (string) $input['summary'] ), 0, 8000 );
            $formats[]           = '%s';
        }
        if ( array_key_exists( 'internalBrief', $input ) ) {
            $changes['internal_brief'] = mb_substr( sanitize_textarea_field( (string) $input['internalBrief'] ), 0, 20000 );
            $formats[]                  = '%s';
        }
        if ( array_key_exists( 'status', $input ) ) {
            $status = sanitize_key( (string) $input['status'] );
            if ( ! in_array( $status, self::STATUSES, true ) ) {
                return new WP_Error( 'mvm_hub4_dossier_status', 'Ongeldige dossierstatus.', array( 'status' => 400 ) );
            }
            if ( 'published' === $status && ! current_user_can( MvM_Hub4_Capabilities::DOSSIER_PUBLISH ) && ! current_user_can( 'manage_options' ) ) {
                return new WP_Error( 'mvm_hub4_dossier_publish_denied', 'Je mag dossiers niet publiceren.', array( 'status' => 403 ) );
            }
            $changes['status'] = $status;
            $formats[]         = '%s';
            if ( 'published' === $status && empty( $row['published_at_utc'] ) ) {
                $changes['published_at_utc'] = current_time( 'mysql', true );
                $formats[]                   = '%s';
            }
        }
        if ( array_key_exists( 'leadUserId', $input ) ) {
            $lead = absint( $input['leadUserId'] );
            $check = self::validate_lead( $lead );
            if ( is_wp_error( $check ) ) {
                return $check;
            }
            if ( ! self::can_manage_all() && $lead !== get_current_user_id() ) {
                return new WP_Error( 'mvm_hub4_dossier_lead_denied', 'Je mag alleen jezelf als dossierhouder instellen.', array( 'status' => 403 ) );
            }
            $changes['lead_user_id'] = $lead;
            $formats[]               = '%d';
        }
        if ( array_key_exists( 'categoryTermId', $input ) ) {
            $category = self::validate_category( absint( $input['categoryTermId'] ) );
            if ( is_wp_error( $category ) ) {
                return $category;
            }
            $changes['category_term_id'] = $category;
            $formats[]                   = '%d';
        }
        if ( array_key_exists( 'visibility', $input ) ) {
            $visibility = sanitize_key( (string) $input['visibility'] );
            if ( ! in_array( $visibility, self::VISIBILITIES, true ) ) {
                return new WP_Error( 'mvm_hub4_dossier_visibility', 'Ongeldige zichtbaarheid.', array( 'status' => 400 ) );
            }
            if ( 'public' === $visibility && ! current_user_can( MvM_Hub4_Capabilities::DOSSIER_PUBLISH ) && ! current_user_can( 'manage_options' ) ) {
                return new WP_Error( 'mvm_hub4_dossier_publish_denied', 'Je mag dossiers niet publiek maken.', array( 'status' => 403 ) );
            }
            $changes['visibility'] = $visibility;
            $formats[]             = '%s';
        }

        if ( 1 === count( $changes ) ) {
            return new WP_Error( 'mvm_hub4_dossier_no_changes', 'Geen geldige wijzigingen ontvangen.', array( 'status' => 400 ) );
        }

        $ok = $wpdb->update( MvM_Hub4_Platform_Schema::table( 'dossiers' ), $changes, array( 'id' => $id ), $formats, array( '%d' ) );
        if ( false === $ok ) {
            return new WP_Error( 'mvm_hub4_dossier_update', 'Het dossier kon niet worden bijgewerkt.', array( 'status' => 500 ) );
        }

        MvM_Hub4_Audit::log(
            'dossier.update',
            'success',
            array(
                'object_type' => 'dossier',
                'object_id'   => $id,
                'context'     => array( 'changed_fields' => implode( ',', array_keys( $changes ) ) ),
            )
        );
        return self::get( $id );
    }

    public static function add_link( int $dossier_id, array $input ): array|WP_Error {
        global $wpdb;

        $dossier = self::raw( $dossier_id );
        if ( ! $dossier ) {
            return new WP_Error( 'mvm_hub4_dossier_missing', 'Dit dossier bestaat niet.', array( 'status' => 404 ) );
        }
        if ( ! self::can_edit_row( $dossier ) ) {
            return new WP_Error( 'mvm_hub4_dossier_denied', 'Je mag dit dossier niet wijzigen.', array( 'status' => 403 ) );
        }

        $type = sanitize_key( (string) ( $input['objectType'] ?? '' ) );
        if ( ! in_array( $type, self::LINK_TYPES, true ) ) {
            return new WP_Error( 'mvm_hub4_dossier_link_type', 'Ongeldig linktype.', array( 'status' => 400 ) );
        }

        $object_id  = absint( $input['objectId'] ?? 0 );
        $object_url = '';
        if ( 'url' === $type ) {
            $object_url = esc_url_raw( (string) ( $input['objectUrl'] ?? '' ), array( 'http', 'https' ) );
            if ( '' === $object_url || ! wp_http_validate_url( $object_url ) ) {
                return new WP_Error( 'mvm_hub4_dossier_link_url', 'De URL is niet geldig.', array( 'status' => 400 ) );
            }
        } elseif ( $object_id < 1 ) {
            return new WP_Error( 'mvm_hub4_dossier_link_id', 'Een object-ID is verplicht.', array( 'status' => 400 ) );
        }

        $ok = $wpdb->insert(
            MvM_Hub4_Platform_Schema::table( 'dossier_links' ),
            array(
                'dossier_id'         => $dossier_id,
                'object_type'        => $type,
                'object_id'          => $object_id,
                'object_url'         => $object_url,
                'label'              => mb_substr( sanitize_text_field( (string) ( $input['label'] ?? '' ) ), 0, 190 ),
                'created_by_user_id' => get_current_user_id(),
                'created_at_utc'     => current_time( 'mysql', true ),
            ),
            array( '%d', '%s', '%d', '%s', '%s', '%d', '%s' )
        );
        if ( false === $ok ) {
            return new WP_Error( 'mvm_hub4_dossier_link_save', 'De koppeling kon niet worden opgeslagen.', array( 'status' => 500 ) );
        }

        MvM_Hub4_Audit::log( 'dossier.link_add', 'success', array( 'object_type' => 'dossier', 'object_id' => $dossier_id, 'context' => array( 'link_type' => $type ) ) );
        return self::get( $dossier_id );
    }

    public static function public_list( int $limit = 20 ): array {
        global $wpdb;
        $table = MvM_Hub4_Platform_Schema::table( 'dossiers' );
        $limit = min( 50, max( 1, $limit ) );
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed internal table.
        $rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE visibility = %s AND status = %s ORDER BY published_at_utc DESC, updated_at_utc DESC LIMIT %d", 'public', 'published', $limit ), ARRAY_A );
        return array_values( array_map( array( __CLASS__, 'public_row' ), is_array( $rows ) ? $rows : array() ) );
    }

    public static function public_by_slug( string $slug ): array|WP_Error {
        global $wpdb;
        $table = MvM_Hub4_Platform_Schema::table( 'dossiers' );
        $slug  = sanitize_title( $slug );
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed internal table.
        $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE slug = %s AND visibility = %s AND status = %s LIMIT 1", $slug, 'public', 'published' ), ARRAY_A );
        if ( ! is_array( $row ) ) {
            return new WP_Error( 'mvm_public_dossier_missing', 'Dit dossier is niet beschikbaar.', array( 'status' => 404 ) );
        }

        $data          = self::public_row( $row );
        $data['links'] = self::links( (int) $row['id'], true );
        return $data;
    }

    public static function counts(): array {
        global $wpdb;
        $table = MvM_Hub4_Platform_Schema::table( 'dossiers' );
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed internal table.
        return array(
            'open'   => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status NOT IN ('published','archived')" ),
            'review' => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE status = %s", 'review' ) ),
            'public' => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE visibility = %s AND status = %s", 'public', 'published' ) ),
        );
    }

    private static function raw( int $id ): ?array {
        global $wpdb;
        $table = MvM_Hub4_Platform_Schema::table( 'dossiers' );
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed internal table.
        $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", absint( $id ) ), ARRAY_A );
        return is_array( $row ) ? $row : null;
    }

    private static function links( int $dossier_id, bool $public_only ): array {
        global $wpdb;
        $table = MvM_Hub4_Platform_Schema::table( 'dossier_links' );
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed internal table.
        $rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE dossier_id = %d ORDER BY id ASC", $dossier_id ), ARRAY_A );
        $result = array();

        foreach ( is_array( $rows ) ? $rows : array() as $row ) {
            $type = sanitize_key( (string) $row['object_type'] );
            if ( $public_only && ! in_array( $type, array( 'post', 'event', 'encyclopedia', 'url' ), true ) ) {
                continue;
            }

            $object_id = (int) $row['object_id'];
            $url       = esc_url_raw( (string) $row['object_url'] );
            if ( $public_only && 'url' !== $type ) {
                $post = get_post( $object_id );
                if ( ! $post instanceof WP_Post || 'publish' !== $post->post_status ) {
                    continue;
                }
                $url = get_permalink( $post ) ?: '';
            }

            $result[] = array(
                'id'         => (int) $row['id'],
                'objectType' => $type,
                'objectId'   => $object_id,
                'url'        => $url,
                'label'      => sanitize_text_field( (string) $row['label'] ),
            );
        }
        return $result;
    }

    private static function internal_row( array $row ): array {
        return array(
            'id'              => (int) $row['id'],
            'status'          => sanitize_key( (string) $row['status'] ),
            'visibility'      => sanitize_key( (string) $row['visibility'] ),
            'title'           => sanitize_text_field( (string) $row['title'] ),
            'slug'            => sanitize_title( (string) $row['slug'] ),
            'summary'         => sanitize_textarea_field( (string) $row['summary'] ),
            'internalBrief'   => sanitize_textarea_field( (string) $row['internal_brief'] ),
            'categoryTermId'  => (int) $row['category_term_id'],
            'leadUserId'      => (int) $row['lead_user_id'],
            'createdByUserId' => (int) $row['created_by_user_id'],
            'createdAtUtc'    => sanitize_text_field( (string) $row['created_at_utc'] ),
            'updatedAtUtc'    => sanitize_text_field( (string) $row['updated_at_utc'] ),
            'publishedAtUtc'  => $row['published_at_utc'] ?: null,
        );
    }

    private static function public_row( array $row ): array {
        return array(
            'id'             => (int) $row['id'],
            'title'          => sanitize_text_field( (string) $row['title'] ),
            'slug'           => sanitize_title( (string) $row['slug'] ),
            'summary'        => sanitize_textarea_field( (string) $row['summary'] ),
            'categoryTermId' => (int) $row['category_term_id'],
            'updatedAtUtc'   => sanitize_text_field( (string) $row['updated_at_utc'] ),
            'publishedAtUtc' => $row['published_at_utc'] ?: null,
        );
    }

    private static function can_manage_all(): bool {
        return current_user_can( 'manage_options' )
            || current_user_can( MvM_Hub4_Capabilities::DOSSIER_PUBLISH )
            || current_user_can( MvM_Hub4_Capabilities::ASSIGNMENT_MANAGE );
    }

    private static function can_view_row( array $row ): bool {
        if ( ! current_user_can( MvM_Hub4_Capabilities::DOSSIER_VIEW ) && ! current_user_can( 'manage_options' ) ) {
            return false;
        }
        if ( self::can_manage_all() ) {
            return true;
        }
        if ( 'public' === (string) $row['visibility'] && 'published' === (string) $row['status'] ) {
            return true;
        }
        if ( ! current_user_can( MvM_Hub4_Capabilities::DOSSIER_MANAGE ) ) {
            return false;
        }
        $user_id = get_current_user_id();
        return $user_id === (int) $row['lead_user_id'] || $user_id === (int) $row['created_by_user_id'];
    }

    private static function can_edit_row( array $row ): bool {
        if ( ! current_user_can( MvM_Hub4_Capabilities::DOSSIER_MANAGE ) && ! current_user_can( 'manage_options' ) ) {
            return false;
        }
        if ( self::can_manage_all() ) {
            return true;
        }
        $user_id = get_current_user_id();
        return $user_id === (int) $row['lead_user_id'] || $user_id === (int) $row['created_by_user_id'];
    }

    private static function validate_lead( int $user_id ): true|WP_Error {
        if ( 0 === $user_id ) {
            return true;
        }
        $user = get_user_by( 'id', $user_id );
        if ( ! $user instanceof WP_User || ! MvM_Hub4_Security::can_access_hub( $user ) ) {
            return new WP_Error( 'mvm_hub4_dossier_lead', 'De gekozen dossierhouder heeft geen Hub-toegang.', array( 'status' => 400 ) );
        }
        return true;
    }

    private static function validate_category( int $term_id ): int|WP_Error {
        if ( 0 === $term_id ) {
            return 0;
        }
        $term = get_term( $term_id, 'category' );
        if ( ! $term instanceof WP_Term ) {
            return new WP_Error( 'mvm_hub4_dossier_category', 'De gekozen nieuwscategorie bestaat niet.', array( 'status' => 400 ) );
        }
        return $term_id;
    }

    private static function unique_slug( string $slug ): string {
        global $wpdb;
        $base      = $slug ?: 'dossier';
        $candidate = $base;
        $i         = 2;
        $table     = MvM_Hub4_Platform_Schema::table( 'dossiers' );

        while ( true ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed internal table.
            $exists = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE slug = %s", $candidate ) );
            if ( 0 === $exists ) {
                return mb_substr( $candidate, 0, 190 );
            }
            $candidate = mb_substr( $base, 0, 180 ) . '-' . $i;
            ++$i;
        }
    }
}
