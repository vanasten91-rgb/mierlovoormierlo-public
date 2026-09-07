<?php

namespace MVM\Hub\Modules\Newsroom\Read;

use MVM\Hub\Core\Capabilities;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/** Privacy-minimal object-scoped detail projections used to prefill editors. */
final class Newsroom_Editor_Detail_Service {
    /** @return array<string,mixed>|\WP_Error */
    public function agenda( int $id ): array|\WP_Error {
        $row = self::row( 'editorial_calendar', $id );
        if ( ! is_array( $row ) ) {
            return new \WP_Error( 'mvm_agenda_missing', 'Dit kalenderitem bestaat niet.', array( 'status' => 404 ) );
        }
        if ( ! self::can_view( Capabilities::AGENDA_VIEW ) ) {
            return new \WP_Error( 'mvm_agenda_view_forbidden', 'Je mag dit kalenderitem niet bekijken.', array( 'status' => 403 ) );
        }
        if ( ! self::can_edit_owned( $row, 'owner_user_id', Capabilities::AGENDA_MANAGE ) && ! self::can_team_edit() ) {
            return new \WP_Error( 'mvm_agenda_view_forbidden', 'Je mag dit kalenderitem niet bekijken.', array( 'status' => 403 ) );
        }

        return self::project( $row, array(
            'id','kind','status','title','starts_at_utc','ends_at_utc','location',
            'post_id','event_post_id','assignment_id','dossier_id','owner_user_id',
            'created_by_user_id','updated_at_utc',
        ) );
    }

    /** @return array<string,mixed>|\WP_Error */
    public function media( int $id ): array|\WP_Error {
        $row = self::row( 'media_items', $id );
        if ( ! is_array( $row ) ) {
            return new \WP_Error( 'mvm_media_missing', 'Dit media-item bestaat niet.', array( 'status' => 404 ) );
        }
        if ( ! self::can_view( Capabilities::MEDIA_VIEW ) ) {
            return new \WP_Error( 'mvm_media_view_forbidden', 'Je mag dit media-item niet bekijken.', array( 'status' => 403 ) );
        }
        if ( ! self::can_edit_owned( $row, 'photographer_user_id', Capabilities::MEDIA_MANAGE ) && ! self::can_team_edit() ) {
            return new \WP_Error( 'mvm_media_view_forbidden', 'Je mag dit media-item niet bekijken.', array( 'status' => 403 ) );
        }

        return self::project( $row, array(
            'id','kind','status','title','attachment_id','photographer_user_id',
            'reviewer_user_id','consent_status','location','credit','related_post_id',
            'assignment_id','dossier_id','created_by_user_id','updated_at_utc',
        ) );
    }

    private static function can_view( string $capability ): bool {
        return is_user_logged_in()
            && Capabilities::can_access_newsroom()
            && ( current_user_can( 'manage_options' ) || current_user_can( $capability ) );
    }

    /** @param array<string,mixed> $row */
    private static function can_edit_owned( array $row, string $owner_key, string $capability ): bool {
        if ( ! current_user_can( 'manage_options' ) && ! current_user_can( $capability ) ) {
            return false;
        }
        $current = get_current_user_id();
        return self::can_team_edit()
            || 0 === (int) ( $row[ $owner_key ] ?? 0 )
            || $current === (int) ( $row[ $owner_key ] ?? 0 )
            || $current === (int) ( $row['created_by_user_id'] ?? 0 );
    }

    private static function can_team_edit(): bool {
        return current_user_can( 'manage_options' )
            || current_user_can( Capabilities::NEWS_EDIT_TEAM )
            || current_user_can( Capabilities::NEWS_PUBLISH );
    }

    /** @return array<string,mixed>|null */
    private static function row( string $suffix, int $id ): ?array {
        global $wpdb;
        $allowed = array( 'editorial_calendar', 'media_items' );
        if ( ! in_array( $suffix, $allowed, true ) ) {
            return null;
        }
        $table = $wpdb->prefix . 'mvm_hub4_' . $suffix;
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- suffix is strict allowlist.
        $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", absint( $id ) ), ARRAY_A );
        return is_array( $row ) ? $row : null;
    }

    /** @param array<string,mixed> $row @param array<int,string> $keys @return array<string,mixed> */
    private static function project( array $row, array $keys ): array {
        $out = array();
        $ints = array(
            'id','post_id','event_post_id','assignment_id','dossier_id','owner_user_id',
            'attachment_id','photographer_user_id','reviewer_user_id','related_post_id','created_by_user_id',
        );
        foreach ( $keys as $key ) {
            if ( ! array_key_exists( $key, $row ) ) {
                continue;
            }
            $value = $row[ $key ];
            if ( in_array( $key, $ints, true ) ) {
                $value = (int) $value;
            } elseif ( in_array( $key, array( 'title','location','credit' ), true ) ) {
                $value = sanitize_text_field( (string) $value );
            } elseif ( in_array( $key, array( 'kind','status','consent_status' ), true ) ) {
                $value = sanitize_key( (string) $value );
            } elseif ( is_string( $value ) ) {
                $value = sanitize_text_field( $value );
            }
            $camel = preg_replace_callback( '/_([a-z])/', static fn( array $m ): string => strtoupper( $m[1] ), $key );
            $out[ (string) $camel ] = $value;
        }
        return $out;
    }
}
