<?php

namespace MVM\Hub\Modules\Newsroom;

use MVM\Hub\Core\Audit;
use MVM\Hub\Core\Capabilities;
use MVM\Hub\Core\Runtime_Gates;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Private staff collaboration for Newsroom 2.0.
 *
 * Storage uses non-public, non-queryable, non-REST post types. All interaction
 * happens through authenticated wp-admin AJAX actions with nonce + capability
 * checks. Audit records contain metadata only, never message content.
 */
final class Staff_Collaboration {
    private const BOARD_TYPE          = 'mvm_staff_notice';
    private const CHAT_TYPE           = 'mvm_staff_chat';
    private const NONCE               = 'mvm_newsroom2_staff';
    private const CHAT_RETENTION_DAYS = 30;

    private static bool $registered = false;

    public static function register(): void {
        if ( self::$registered ) {
            return;
        }
        self::$registered = true;

        add_action( 'init', array( self::class, 'register_post_types' ), 3 );

        add_action( 'wp_ajax_mvm_nr2_board_list', array( self::class, 'ajax_board_list' ) );
        add_action( 'wp_ajax_mvm_nr2_board_post', array( self::class, 'ajax_board_post' ) );
        add_action( 'wp_ajax_mvm_nr2_board_toggle_pin', array( self::class, 'ajax_board_toggle_pin' ) );
        add_action( 'wp_ajax_mvm_nr2_board_delete', array( self::class, 'ajax_board_delete' ) );
        add_action( 'wp_ajax_mvm_nr2_chat_list', array( self::class, 'ajax_chat_list' ) );
        add_action( 'wp_ajax_mvm_nr2_chat_send', array( self::class, 'ajax_chat_send' ) );
        add_action( 'wp_ajax_mvm_nr2_chat_delete', array( self::class, 'ajax_chat_delete' ) );
    }

    public static function nonce(): string {
        return wp_create_nonce( self::NONCE );
    }

    public static function register_post_types(): void {
        $base = array(
            'public'              => false,
            'publicly_queryable'  => false,
            'exclude_from_search' => true,
            'show_ui'             => false,
            'show_in_menu'        => false,
            'show_in_admin_bar'   => false,
            'show_in_nav_menus'   => false,
            'show_in_rest'        => false,
            'query_var'           => false,
            'rewrite'             => false,
            'supports'            => array( 'title', 'editor', 'author' ),
            'can_export'          => false,
            'delete_with_user'    => false,
        );

        register_post_type( self::BOARD_TYPE, array_merge( $base, array( 'label' => 'MvM stafprikbord' ) ) );
        register_post_type( self::CHAT_TYPE, array_merge( $base, array( 'label' => 'MvM teamchat' ) ) );
    }

    public static function ajax_board_list(): void {
        self::verify_request( Capabilities::STAFF_BOARD_VIEW );

        $query = new \WP_Query(
            array(
                'post_type'              => self::BOARD_TYPE,
                'post_status'            => 'private',
                'posts_per_page'         => 50,
                'orderby'                => array( 'date' => 'DESC' ),
                'no_found_rows'          => true,
                'ignore_sticky_posts'    => true,
                'update_post_term_cache' => false,
            )
        );

        $items = array();
        foreach ( (array) $query->posts as $post ) {
            if ( ! $post instanceof \WP_Post || self::notice_expired( $post->ID ) ) {
                continue;
            }
            $items[] = self::notice_payload( $post );
        }

        usort(
            $items,
            static fn( array $left, array $right ): int => (int) $right['pinned'] <=> (int) $left['pinned'] ?: strcmp( (string) $right['createdUtc'], (string) $left['createdUtc'] )
        );

        wp_send_json_success(
            array(
                'items'       => array_values( $items ),
                'canPost'     => Capabilities::can_post_staff_board() && Runtime_Gates::newsroom_writes_enabled(),
                'canModerate' => current_user_can( 'manage_options' ) || current_user_can( Capabilities::STAFF_BOARD_MODERATE ),
            )
        );
    }

    public static function ajax_board_post(): void {
        self::verify_request( Capabilities::STAFF_BOARD_POST, true );

        $title    = sanitize_text_field( self::post_string( 'title', 120 ) );
        $message  = sanitize_textarea_field( self::post_string( 'message', 4000 ) );
        $priority = sanitize_key( self::post_string( 'priority', 20 ) );
        $priority = in_array( $priority, array( 'normal', 'important' ), true ) ? $priority : 'normal';

        if ( '' === $title || '' === $message ) {
            wp_send_json_error( array( 'message' => 'Titel en bericht zijn verplicht.' ), 400 );
        }

        $post_id = wp_insert_post(
            array(
                'post_type'    => self::BOARD_TYPE,
                'post_status'  => 'private',
                'post_title'   => $title,
                'post_content' => $message,
                'post_author'  => get_current_user_id(),
            ),
            true
        );

        if ( is_wp_error( $post_id ) ) {
            wp_send_json_error( array( 'message' => 'Het prikbordbericht kon niet worden opgeslagen.' ), 500 );
        }

        update_post_meta( $post_id, '_mvm_notice_priority', $priority );
        update_post_meta( $post_id, '_mvm_notice_pinned', 0 );

        Audit::record( 'newsroom.staff_board.created', 'success', 'staff_notice', (int) $post_id, array( 'priority' => $priority ) );
        wp_send_json_success( array( 'item' => self::notice_payload( get_post( $post_id ) ) ) );
    }

    public static function ajax_board_toggle_pin(): void {
        self::verify_request( Capabilities::STAFF_BOARD_MODERATE, true );
        $post   = self::private_post_from_request( self::BOARD_TYPE );
        $pinned = 1 === (int) get_post_meta( $post->ID, '_mvm_notice_pinned', true ) ? 0 : 1;
        update_post_meta( $post->ID, '_mvm_notice_pinned', $pinned );
        Audit::record( 'newsroom.staff_board.pin_changed', 'success', 'staff_notice', $post->ID, array( 'pinned' => (bool) $pinned ) );
        wp_send_json_success( array( 'pinned' => (bool) $pinned ) );
    }

    public static function ajax_board_delete(): void {
        self::verify_request( Capabilities::STAFF_BOARD_VIEW, true );
        $post      = self::private_post_from_request( self::BOARD_TYPE );
        $moderator = current_user_can( 'manage_options' ) || current_user_can( Capabilities::STAFF_BOARD_MODERATE );
        $own       = (int) $post->post_author === get_current_user_id();
        $pinned    = 1 === (int) get_post_meta( $post->ID, '_mvm_notice_pinned', true );

        if ( ! $moderator && ( ! $own || $pinned ) ) {
            Audit::record( 'newsroom.staff_board.delete', 'denied', 'staff_notice', $post->ID );
            wp_send_json_error( array( 'message' => 'Je mag dit prikbordbericht niet verwijderen.' ), 403 );
        }

        wp_delete_post( $post->ID, true );
        Audit::record( 'newsroom.staff_board.deleted', 'success', 'staff_notice', $post->ID );
        wp_send_json_success();
    }

    public static function ajax_chat_list(): void {
        self::verify_request( Capabilities::TEAM_CHAT_ACCESS );
        self::prune_chat_if_due();
        $after_id = max( 0, (int) ( $_POST['afterId'] ?? 0 ) );

        $query = new \WP_Query(
            array(
                'post_type'              => self::CHAT_TYPE,
                'post_status'            => 'private',
                'posts_per_page'         => 60,
                'orderby'                => 'ID',
                'order'                  => 'DESC',
                'no_found_rows'          => true,
                'ignore_sticky_posts'    => true,
                'update_post_term_cache' => false,
                'date_query'             => array(
                    array(
                        'column'    => 'post_date_gmt',
                        'after'     => gmdate( 'Y-m-d H:i:s', time() - self::CHAT_RETENTION_DAYS * DAY_IN_SECONDS ),
                        'inclusive' => true,
                    ),
                ),
            )
        );

        $items = array();
        foreach ( array_reverse( (array) $query->posts ) as $post ) {
            if ( ! $post instanceof \WP_Post || ( $after_id > 0 && $post->ID <= $after_id ) ) {
                continue;
            }
            $items[] = self::chat_payload( $post );
        }

        wp_send_json_success(
            array(
                'items'         => $items,
                'canSend'       => Capabilities::can_send_team_chat() && Runtime_Gates::newsroom_writes_enabled(),
                'canModerate'   => current_user_can( 'manage_options' ) || current_user_can( Capabilities::TEAM_CHAT_MODERATE ),
                'retentionDays' => self::CHAT_RETENTION_DAYS,
            )
        );
    }

    public static function ajax_chat_send(): void {
        self::verify_request( Capabilities::TEAM_CHAT_SEND, true );
        self::prune_chat_if_due();
        $user_id  = get_current_user_id();
        $rate_key = 'mvm_nr2_chat_rate_' . $user_id;
        if ( get_transient( $rate_key ) ) {
            wp_send_json_error( array( 'message' => 'Wacht heel even voordat je nog een bericht stuurt.' ), 429 );
        }

        $message = sanitize_textarea_field( self::post_string( 'message', 1500 ) );
        if ( '' === $message ) {
            wp_send_json_error( array( 'message' => 'Schrijf eerst een bericht.' ), 400 );
        }

        set_transient( $rate_key, 1, 2 );
        $post_id = wp_insert_post(
            array(
                'post_type'    => self::CHAT_TYPE,
                'post_status'  => 'private',
                'post_title'   => 'Teamchat ' . gmdate( 'Y-m-d H:i:s' ),
                'post_content' => $message,
                'post_author'  => $user_id,
            ),
            true
        );

        if ( is_wp_error( $post_id ) ) {
            delete_transient( $rate_key );
            wp_send_json_error( array( 'message' => 'Het chatbericht kon niet worden opgeslagen.' ), 500 );
        }

        Audit::record( 'newsroom.team_chat.sent', 'success', 'team_chat', (int) $post_id, array( 'length' => mb_strlen( $message ) ) );
        wp_send_json_success( array( 'item' => self::chat_payload( get_post( $post_id ) ) ) );
    }

    public static function ajax_chat_delete(): void {
        self::verify_request( Capabilities::TEAM_CHAT_ACCESS, true );
        $post      = self::private_post_from_request( self::CHAT_TYPE );
        $moderator = current_user_can( 'manage_options' ) || current_user_can( Capabilities::TEAM_CHAT_MODERATE );
        $own       = (int) $post->post_author === get_current_user_id();
        $age       = max( 0, time() - (int) get_post_time( 'U', true, $post ) );

        if ( ! $moderator && ( ! $own || $age > 15 * MINUTE_IN_SECONDS ) ) {
            Audit::record( 'newsroom.team_chat.delete', 'denied', 'team_chat', $post->ID );
            wp_send_json_error( array( 'message' => 'Je mag dit chatbericht niet verwijderen.' ), 403 );
        }

        wp_delete_post( $post->ID, true );
        Audit::record( 'newsroom.team_chat.deleted', 'success', 'team_chat', $post->ID );
        wp_send_json_success();
    }

    private static function verify_request( string $capability, bool $write = false ): void {
        if ( ! is_user_logged_in() || ( ! current_user_can( $capability ) && ! current_user_can( 'manage_options' ) ) ) {
            wp_send_json_error( array( 'message' => 'Geen toegang.' ), 403 );
        }
        check_ajax_referer( self::NONCE, 'nonce' );
        if ( $write && ! Runtime_Gates::newsroom_writes_enabled() ) {
            wp_send_json_error( array( 'message' => 'Schrijven in de Newsroom staat veilig uit.' ), 403 );
        }
    }

    private static function private_post_from_request( string $type ): \WP_Post {
        $post_id = max( 0, (int) ( $_POST['id'] ?? 0 ) );
        $post    = get_post( $post_id );
        if ( ! $post instanceof \WP_Post || $type !== $post->post_type || 'private' !== $post->post_status ) {
            wp_send_json_error( array( 'message' => 'Item niet gevonden.' ), 404 );
        }
        return $post;
    }

    private static function notice_payload( ?\WP_Post $post ): array {
        if ( ! $post instanceof \WP_Post ) {
            return array();
        }
        $author = get_userdata( (int) $post->post_author );
        return array(
            'id'         => (int) $post->ID,
            'title'      => (string) $post->post_title,
            'message'    => (string) $post->post_content,
            'authorId'   => (int) $post->post_author,
            'authorName' => $author ? (string) $author->display_name : 'Staf',
            'createdUtc' => get_post_time( 'c', true, $post ),
            'pinned'     => 1 === (int) get_post_meta( $post->ID, '_mvm_notice_pinned', true ),
            'priority'   => sanitize_key( (string) get_post_meta( $post->ID, '_mvm_notice_priority', true ) ) ?: 'normal',
            'canDelete'  => current_user_can( 'manage_options' )
                || current_user_can( Capabilities::STAFF_BOARD_MODERATE )
                || (int) $post->post_author === get_current_user_id(),
        );
    }

    private static function chat_payload( ?\WP_Post $post ): array {
        if ( ! $post instanceof \WP_Post ) {
            return array();
        }
        $author = get_userdata( (int) $post->post_author );
        $age    = max( 0, time() - (int) get_post_time( 'U', true, $post ) );
        return array(
            'id'         => (int) $post->ID,
            'message'    => (string) $post->post_content,
            'authorId'   => (int) $post->post_author,
            'authorName' => $author ? (string) $author->display_name : 'Staf',
            'createdUtc' => get_post_time( 'c', true, $post ),
            'mine'       => (int) $post->post_author === get_current_user_id(),
            'canDelete'  => current_user_can( 'manage_options' )
                || current_user_can( Capabilities::TEAM_CHAT_MODERATE )
                || ( (int) $post->post_author === get_current_user_id() && $age <= 15 * MINUTE_IN_SECONDS ),
        );
    }

    private static function notice_expired( int $post_id ): bool {
        $expires = (string) get_post_meta( $post_id, '_mvm_notice_expires_utc', true );
        if ( '' === $expires ) {
            return false;
        }
        $timestamp = strtotime( $expires . ' UTC' );
        return false !== $timestamp && $timestamp < time();
    }

    private static function prune_chat_if_due(): void {
        $transient = 'mvm_nr2_chat_retention_prune_v1';
        if ( get_transient( $transient ) ) {
            return;
        }

        set_transient( $transient, 1, DAY_IN_SECONDS );
        $ids = get_posts(
            array(
                'post_type'      => self::CHAT_TYPE,
                'post_status'    => 'private',
                'fields'         => 'ids',
                'posts_per_page' => 100,
                'orderby'        => 'ID',
                'order'          => 'ASC',
                'date_query'     => array(
                    array(
                        'column'    => 'post_date_gmt',
                        'before'    => gmdate( 'Y-m-d H:i:s', time() - self::CHAT_RETENTION_DAYS * DAY_IN_SECONDS ),
                        'inclusive' => false,
                    ),
                ),
            )
        );

        $deleted = 0;
        foreach ( (array) $ids as $post_id ) {
            if ( wp_delete_post( (int) $post_id, true ) ) {
                ++$deleted;
            }
        }

        if ( $deleted > 0 ) {
            Audit::record( 'newsroom.team_chat.retention_cleanup', 'success', 'team_chat', 0, array( 'deleted' => $deleted, 'retention_days' => self::CHAT_RETENTION_DAYS ) );
        }
    }

    private static function post_string( string $key, int $max_length ): string {
        $value = isset( $_POST[ $key ] ) ? (string) wp_unslash( $_POST[ $key ] ) : '';
        return mb_substr( trim( $value ), 0, $max_length );
    }

    private function __construct() {}
}
