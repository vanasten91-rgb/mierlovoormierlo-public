<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class MvM_Hub4_Publication_Checklist {
    public static function activate(): void {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $table   = self::table_name();
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            news_post_id bigint(20) unsigned NOT NULL,
            source_verified tinyint(1) unsigned NOT NULL DEFAULT 0,
            facts_verified tinyint(1) unsigned NOT NULL DEFAULT 0,
            facebook_ready tinyint(1) unsigned NOT NULL DEFAULT 0,
            second_review tinyint(1) unsigned NOT NULL DEFAULT 0,
            updated_by_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            updated_at_utc datetime NOT NULL,
            PRIMARY KEY  (news_post_id),
            KEY updated_by_user_id (updated_by_user_id),
            KEY updated_at_utc (updated_at_utc)
        ) {$charset};";

        dbDelta( $sql );
    }

    public static function table_name(): string {
        global $wpdb;
        return $wpdb->prefix . 'mvm_hub4_news_checklists';
    }

    public static function summary( int $post_id ): array|WP_Error {
        $post = self::get_news_post( $post_id );
        if ( is_wp_error( $post ) ) {
            return $post;
        }

        $can_view = current_user_can( 'manage_options' )
            || current_user_can( 'edit_post', $post_id )
            || (
                current_user_can( MvM_Hub4_Capabilities::NEWS_VIEW )
                && current_user_can( 'read_post', $post_id )
            );

        if ( ! $can_view ) {
            MvM_Hub4_Audit::log( 'news.checklist_view', 'denied', array( 'object_type' => 'post', 'object_id' => $post_id ) );
            return new WP_Error( 'mvm_hub4_checklist_denied', 'Je mag deze checklist niet bekijken.', array( 'status' => 403 ) );
        }

        $can_update = current_user_can( 'manage_options' )
            || (
                current_user_can( MvM_Hub4_Capabilities::CHECKLIST_USE )
                && current_user_can( 'edit_post', $post_id )
            );

        $manual = self::get_manual( $post_id );
        $auto   = self::automatic_checks( $post );
        $items  = array_merge( $auto, array(
            array( 'id' => 'source_verified', 'label' => 'Bron gecontroleerd', 'done' => (bool) $manual['source_verified'], 'mode' => 'manual' ),
            array( 'id' => 'facts_verified', 'label' => 'Feiten gecontroleerd', 'done' => (bool) $manual['facts_verified'], 'mode' => 'manual' ),
            array( 'id' => 'facebook_ready', 'label' => 'Geschikt om op Facebook te delen', 'done' => (bool) $manual['facebook_ready'], 'mode' => 'manual' ),
            array( 'id' => 'second_review', 'label' => 'Tweede redactionele controle', 'done' => (bool) $manual['second_review'], 'mode' => 'manual' ),
        ) );

        $required = count( $items );
        $done     = count( array_filter( $items, static fn( array $item ): bool => ! empty( $item['done'] ) ) );

        return array(
            'newsPostId' => $post_id,
            'items'      => $items,
            'done'       => $done,
            'required'   => $required,
            'complete'   => $required > 0 && $done === $required,
            'canUpdate'   => $can_update,
            'updatedAtUtc' => $manual['updated_at_utc'] ?: null,
            'updatedByUserId' => (int) $manual['updated_by_user_id'],
        );
    }

    public static function update( int $post_id, array $input ): array|WP_Error {
        global $wpdb;

        $post = self::get_news_post( $post_id );
        if ( is_wp_error( $post ) ) {
            return $post;
        }

        $can_update = current_user_can( 'manage_options' )
            || (
                current_user_can( MvM_Hub4_Capabilities::CHECKLIST_USE )
                && current_user_can( 'edit_post', $post_id )
            );

        if ( ! $can_update ) {
            MvM_Hub4_Audit::log( 'news.checklist_update', 'denied', array( 'object_type' => 'post', 'object_id' => $post_id ) );
            return new WP_Error( 'mvm_hub4_checklist_denied', 'Je mag deze checklist niet wijzigen.', array( 'status' => 403 ) );
        }

        $current = self::get_manual( $post_id );
        $data    = array(
            'news_post_id'       => $post_id,
            'source_verified'    => self::bool_input( $input, 'sourceVerified', $current['source_verified'] ),
            'facts_verified'     => self::bool_input( $input, 'factsVerified', $current['facts_verified'] ),
            'facebook_ready'     => self::bool_input( $input, 'facebookReady', $current['facebook_ready'] ),
            'second_review'      => self::bool_input( $input, 'secondReview', $current['second_review'] ),
            'updated_by_user_id' => get_current_user_id(),
            'updated_at_utc'     => current_time( 'mysql', true ),
        );

        $ok = $wpdb->replace(
            self::table_name(),
            $data,
            array( '%d', '%d', '%d', '%d', '%d', '%d', '%s' )
        );
        if ( false === $ok ) {
            return new WP_Error( 'mvm_hub4_checklist_save_failed', 'De publicatiechecklist kon niet worden opgeslagen.', array( 'status' => 500 ) );
        }

        MvM_Hub4_Audit::log(
            'news.checklist_update',
            'success',
            array(
                'object_type' => 'post',
                'object_id'   => $post_id,
                'context'     => array(
                    'source_verified' => (int) $data['source_verified'],
                    'facts_verified'  => (int) $data['facts_verified'],
                    'facebook_ready'  => (int) $data['facebook_ready'],
                    'second_review'   => (int) $data['second_review'],
                ),
            )
        );

        return self::summary( $post_id );
    }

    private static function get_manual( int $post_id ): array {
        global $wpdb;
        $table = self::table_name();
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed internal table name.
        $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE news_post_id = %d", $post_id ), ARRAY_A );
        if ( ! is_array( $row ) ) {
            return array(
                'source_verified'    => 0,
                'facts_verified'     => 0,
                'facebook_ready'     => 0,
                'second_review'      => 0,
                'updated_by_user_id' => 0,
                'updated_at_utc'     => null,
            );
        }
        return $row;
    }

    private static function automatic_checks( WP_Post $post ): array {
        $post_id     = (int) $post->ID;
        $categories  = wp_get_post_categories( $post_id, array( 'fields' => 'ids' ) );
        $has_category= is_array( $categories ) && count( $categories ) > 0;
        $thumb_id    = get_post_thumbnail_id( $post_id );
        $has_image   = $thumb_id > 0;
        $has_alt     = $has_image && '' !== trim( (string) get_post_meta( $thumb_id, '_wp_attachment_image_alt', true ) );
        $plain       = trim( wp_strip_all_tags( strip_shortcodes( (string) $post->post_content ) ) );
        $has_intro   = '' !== trim( (string) $post->post_excerpt ) || mb_strlen( $plain ) >= 120;

        return array(
            array( 'id' => 'category', 'label' => 'Categorie gekozen', 'done' => $has_category, 'mode' => 'automatic' ),
            array( 'id' => 'featured_image', 'label' => 'Uitgelichte afbeelding aanwezig', 'done' => $has_image, 'mode' => 'automatic' ),
            array( 'id' => 'image_alt', 'label' => 'Alt-tekst bij afbeelding', 'done' => $has_alt, 'mode' => 'automatic' ),
            array( 'id' => 'intro', 'label' => 'Korte intro aanwezig', 'done' => $has_intro, 'mode' => 'automatic' ),
        );
    }

    private static function get_news_post( int $post_id ): WP_Post|WP_Error {
        $post = get_post( absint( $post_id ) );
        if ( ! $post instanceof WP_Post || 'post' !== $post->post_type ) {
            return new WP_Error( 'mvm_hub4_checklist_post', 'Dit nieuwsartikel bestaat niet.', array( 'status' => 404 ) );
        }
        return $post;
    }

    private static function bool_input( array $input, string $key, mixed $fallback ): int {
        if ( ! array_key_exists( $key, $input ) ) {
            return (int) (bool) $fallback;
        }
        return (int) filter_var( $input[ $key ], FILTER_VALIDATE_BOOLEAN );
    }
}
