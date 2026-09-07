<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class MvM_Hub4_System_Stats {
    private const CACHE_KEY   = 'system_overview_v1';
    private const CACHE_GROUP = 'mvm_hub4';
    private const CACHE_TTL   = 60;

    public static function overview( bool $fresh = false ): array {
        if ( $fresh ) {
            wp_cache_delete( self::CACHE_KEY, self::CACHE_GROUP );
        }

        $found  = false;
        $cached = wp_cache_get( self::CACHE_KEY, self::CACHE_GROUP, false, $found );
        if ( $found && is_array( $cached ) ) {
            return $cached;
        }

        $users          = count_users();
        $news_publish   = self::post_count( 'post', 'publish' );
        $news_draft     = self::post_count( 'post', 'draft' );
        $news_pending   = self::post_count( 'post', 'pending' );
        $news_scheduled = self::post_count( 'post', 'future' );

        $encyclopedia = array(
            'articles'     => self::post_count( 'mvm_encyclopedie', 'publish' ),
            'people'       => self::post_count( 'mvm_persoon', 'publish' ),
            'locations'    => self::post_count( 'mvm_locatie', 'publish' ),
            'buildings'    => self::post_count( 'mvm_gebouw', 'publish' ),
            'events'       => self::post_count( 'mvm_gebeurtenis', 'publish' ),
            'associations' => self::post_count( 'mvm_vereniging', 'publish' ),
            'businesses'   => self::post_count( 'mvm_bedrijf', 'publish' ),
            'images'       => self::post_count( 'mvm_beeld', 'publish' ),
        );
        $encyclopedia['total'] = array_sum( $encyclopedia );

        $hub_assignments = self::table_count( self::prefixed_table( 'mvm_hub4_assignments' ) );
        $legacy_assign   = self::post_count_many( 'mvm_assignment', array( 'private', 'publish', 'draft', 'pending' ) );

        $data = array(
            'generatedAtUtc' => gmdate( 'c' ),
            'cards' => array(
                array( 'id' => 'users', 'label' => 'Geregistreerde gebruikers', 'value' => (int) ( $users['total_users'] ?? 0 ), 'group' => 'community' ),
                array( 'id' => 'news', 'label' => 'Gepubliceerd nieuws', 'value' => $news_publish, 'group' => 'news' ),
                array( 'id' => 'community_posts', 'label' => 'Communityberichten', 'value' => self::post_count( 'peepso-post', 'publish' ), 'group' => 'community' ),
                array( 'id' => 'community_photos', 'label' => 'Communityfoto’s', 'value' => self::table_count( self::prefixed_table( 'peepso_photos' ) ), 'group' => 'community' ),
                array( 'id' => 'media_images', 'label' => 'Media-afbeeldingen', 'value' => self::image_attachment_count(), 'group' => 'media' ),
                array( 'id' => 'encyclopedia', 'label' => 'Encyclopedie-items', 'value' => (int) $encyclopedia['total'], 'group' => 'encyclopedia' ),
                array( 'id' => 'events', 'label' => 'Actieve evenementen', 'value' => self::post_count( 'event_listing', 'publish' ), 'group' => 'events' ),
                array( 'id' => 'sources', 'label' => 'Bronnen', 'value' => self::post_count( 'mvm_bron', 'publish' ), 'group' => 'sources' ),
                array( 'id' => 'forum_topics', 'label' => 'Forumonderwerpen', 'value' => self::table_count( self::prefixed_table( 'wpforo_topics' ) ), 'group' => 'community' ),
                array( 'id' => 'forum_posts', 'label' => 'Forumberichten', 'value' => self::table_count( self::prefixed_table( 'wpforo_posts' ) ), 'group' => 'community' ),
                array( 'id' => 'reactions', 'label' => 'PeepSo-reacties', 'value' => self::post_count( 'peepso-comment', 'publish' ), 'group' => 'community' ),
                array( 'id' => 'pages', 'label' => 'Gepubliceerde pagina’s', 'value' => self::post_count( 'page', 'publish' ), 'group' => 'site' ),
            ),
            'news' => array(
                'published' => $news_publish,
                'drafts'    => $news_draft,
                'pending'   => $news_pending,
                'scheduled' => $news_scheduled,
                'tips'      => self::post_count_many( 'mvm_news_tip', array( 'private', 'publish', 'draft', 'pending' ) ),
            ),
            'encyclopedia' => $encyclopedia,
            'events' => array(
                'active'  => self::post_count( 'event_listing', 'publish' ),
                'drafts'  => self::post_count( 'event_listing', 'draft' ),
                'expired' => self::post_count( 'event_listing', 'expired' ),
            ),
            'media' => array(
                'libraryImages'   => self::image_attachment_count(),
                'communityPhotos' => self::table_count( self::prefixed_table( 'peepso_photos' ) ),
                'imageBank'       => self::post_count( 'mvm_beeld', 'publish' ),
            ),
            'community' => array(
                'users'          => (int) ( $users['total_users'] ?? 0 ),
                'peepsoPosts'    => self::post_count( 'peepso-post', 'publish' ),
                'peepsoComments' => self::post_count( 'peepso-comment', 'publish' ),
                'peepsoLikes'    => self::table_count( self::prefixed_table( 'peepso_likes' ) ),
                'forumTopics'    => self::table_count( self::prefixed_table( 'wpforo_topics' ) ),
                'forumPosts'     => self::table_count( self::prefixed_table( 'wpforo_posts' ) ),
            ),
            'editorial' => array(
                'hub4Assignments'   => $hub_assignments,
                'legacyAssignments' => $legacy_assign,
                'openAssignments'   => self::table_count_where_not_in(
                    self::prefixed_table( 'mvm_hub4_assignments' ),
                    'status',
                    array( 'published', 'cancelled' )
                ),
                'sources' => self::post_count( 'mvm_bron', 'publish' ),
            ),
        );

        wp_cache_set( self::CACHE_KEY, $data, self::CACHE_GROUP, self::CACHE_TTL );
        return $data;
    }

    private static function post_count( string $post_type, string $status ): int {
        if ( ! post_type_exists( $post_type ) ) {
            return 0;
        }
        $counts = wp_count_posts( $post_type );
        return isset( $counts->{$status} ) ? (int) $counts->{$status} : 0;
    }

    private static function post_count_many( string $post_type, array $statuses ): int {
        $total = 0;
        foreach ( $statuses as $status ) {
            $total += self::post_count( $post_type, (string) $status );
        }
        return $total;
    }

    private static function image_attachment_count(): int {
        global $wpdb;
        return (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_status = 'inherit' AND post_mime_type LIKE 'image/%'"
        );
    }

    private static function prefixed_table( string $suffix ): string {
        global $wpdb;
        return $wpdb->prefix . $suffix;
    }

    private static function table_exists( string $table ): bool {
        global $wpdb;
        return $table === (string) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
    }

    private static function table_count( string $table ): int {
        global $wpdb;
        if ( ! self::table_exists( $table ) ) {
            return 0;
        }
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is generated internally from $wpdb->prefix.
        return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
    }

    private static function table_count_where_not_in( string $table, string $column, array $values ): int {
        global $wpdb;
        if ( ! self::table_exists( $table ) || ! preg_match( '/^[a-z0-9_]+$/i', $column ) || empty( $values ) ) {
            return 0;
        }
        $placeholders = implode( ',', array_fill( 0, count( $values ), '%s' ) );
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table/column are internal allowlisted identifiers.
        $sql = $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE {$column} NOT IN ({$placeholders})", $values );
        return (int) $wpdb->get_var( $sql );
    }
}
