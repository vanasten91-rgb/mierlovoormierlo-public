<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Source-owned compatibility layer for the existing PeepSo/WP Event Manager
 * comment integration. Behaviour is intentionally kept equivalent to the
 * proven production snippets; no PeepSo plugin code or settings are changed.
 */
final class MvM_Hub4_PeepSo_Event_Comments {
    private const STRUCTURAL_OPTION = 'mvm_peepso_structural_companions_v1';
    private const ALLOWED_TYPES = array( 'event_listing', 'event_organizer', 'event_venue' );

    public static function init(): void {
        add_action( 'init', array( __CLASS__, 'ensure_structural_companions' ), 100 );
        add_action( 'save_post_event_listing', array( __CLASS__, 'on_save_event' ), PHP_INT_MAX, 2 );
        add_action( 'save_post_event_organizer', array( __CLASS__, 'on_save_structural' ), 20, 2 );
        add_action( 'save_post_event_venue', array( __CLASS__, 'on_save_structural' ), 20, 2 );
        add_action( 'wp', array( __CLASS__, 'normalize_current_event' ), 1 );
        add_action( 'wp_head', array( __CLASS__, 'dedupe_css' ), PHP_INT_MAX );
        add_action( 'wp_footer', array( __CLASS__, 'render_native_comments' ), 5 );
        add_action( 'wp_footer', array( __CLASS__, 'dedupe_script' ), PHP_INT_MAX );
        add_filter( 'comments_open', array( __CLASS__, 'close_wp_comments' ), PHP_INT_MAX, 2 );
    }

    public static function ensure_structural_companions(): void {
        if ( '1' === (string) get_option( self::STRUCTURAL_OPTION, '' ) ) {
            return;
        }
        if ( ! class_exists( 'PeepSo' ) || ! self::bridge_class() ) {
            return;
        }

        $ids = get_posts(
            array(
                'post_type'      => array( 'event_organizer', 'event_venue' ),
                'post_status'    => array( 'publish', 'future', 'draft', 'pending', 'private' ),
                'posts_per_page' => -1,
                'fields'         => 'ids',
            )
        );

        $ok = true;
        foreach ( $ids as $id ) {
            if ( ! self::ensure_companion( (int) $id ) ) {
                $ok = false;
            }
        }
        if ( $ok ) {
            update_option( self::STRUCTURAL_OPTION, '1', false );
        }
    }

    public static function on_save_structural( int $post_id, $post ): void {
        if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
            return;
        }
        if ( $post instanceof WP_Post ) {
            self::ensure_companion( $post_id );
        }
    }

    public static function on_save_event( int $post_id, $post ): void {
        if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
            return;
        }
        if ( $post instanceof WP_Post ) {
            self::ensure_companion( $post_id );
            self::normalize_activity( $post_id );
        }
    }

    public static function normalize_current_event(): void {
        if ( is_singular( 'event_listing' ) ) {
            self::normalize_activity( (int) get_queried_object_id() );
        }
    }

    public static function close_wp_comments( bool $open, int $post_id ): bool {
        if ( in_array( get_post_type( $post_id ), array( 'event_listing', 'event_organizer', 'event_venue', 'tribe_events' ), true ) ) {
            return false;
        }
        return $open;
    }

    public static function ensure_companion( int $content_id ): int {
        if ( ! $content_id || ! class_exists( 'PeepSo' ) ) {
            return 0;
        }

        $content = get_post( $content_id );
        if ( ! $content instanceof WP_Post || ! in_array( $content->post_type, self::ALLOWED_TYPES, true ) ) {
            return 0;
        }

        $existing = absint( get_post_meta( $content_id, 'peepso_postnotify', true ) );
        if ( $existing && 'peepso-post' === get_post_type( $existing ) ) {
            return $existing;
        }

        $class = self::bridge_class();
        if ( ! $class ) {
            return 0;
        }

        try {
            $ref = new ReflectionMethod( $class, 'create_peepso_companion' );
            $ref->setAccessible( true );
            $created = $ref->invoke( null, $content );
        } catch ( Throwable $e ) {
            return 0;
        }

        if ( ! is_array( $created ) || empty( $created['success'] ) || empty( $created['id'] ) ) {
            return 0;
        }

        $notify_id = absint( $created['id'] );
        if ( ! $notify_id || 'peepso-post' !== get_post_type( $notify_id ) ) {
            return 0;
        }

        update_post_meta( $content_id, 'peepso_postnotify', $notify_id );
        return $notify_id;
    }

    public static function normalize_activity( int $event_id ): bool {
        global $wpdb;

        if ( ! $event_id || 'event_listing' !== get_post_type( $event_id ) ) {
            return false;
        }

        $notify_id = self::ensure_companion( $event_id );
        if ( ! $notify_id || 'peepso-post' !== get_post_type( $notify_id ) ) {
            return false;
        }

        $table = self::activity_table();
        if ( ! preg_match( '/^[A-Za-z0-9_]+$/', $table ) ) {
            return false;
        }

        $module_id = self::blogposts_module_id( $table );
        if ( ! $module_id ) {
            return false;
        }

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT act_id, act_module_id FROM `{$table}` WHERE act_external_id = %d ORDER BY act_id DESC LIMIT 1",
                $notify_id
            ),
            ARRAY_A
        );
        if ( ! is_array( $row ) || empty( $row['act_id'] ) ) {
            return false;
        }
        if ( absint( $row['act_module_id'] ) === $module_id ) {
            return true;
        }

        return false !== $wpdb->update(
            $table,
            array( 'act_module_id' => $module_id ),
            array( 'act_id' => absint( $row['act_id'] ) ),
            array( '%d' ),
            array( '%d' )
        );
    }

    public static function render_native_comments(): void {
        if ( is_admin() || wp_doing_ajax() || ! is_singular( self::ALLOWED_TYPES ) ) {
            return;
        }

        $content_id = (int) get_queried_object_id();
        $html = self::native_html( $content_id );
        if ( '' === trim( $html ) ) {
            return;
        }

        $type = get_post_type( $content_id );
        $title = 'Reageren';
        if ( 'event_listing' === $type ) {
            $title = 'Reageren op dit evenement';
        } elseif ( 'event_organizer' === $type ) {
            $title = 'Reageren op deze organisator';
        } elseif ( 'event_venue' === $type ) {
            $title = 'Reageren op deze locatie';
        }

        echo '<section id="mvm-native-peepso-comments" data-mvm-peepso-native="1">';
        echo '<h2 class="mvm-native-peepso-comments-title">' . esc_html( $title ) . '</h2>';
        // PeepSo owns and escapes its generated component markup.
        echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        ?>
        <style id="mvm-native-peepso-comments-style">
            #mvm-native-peepso-comments{display:block!important;visibility:visible!important;opacity:1!important;width:100%!important;max-width:none!important;height:auto!important;overflow:visible!important;clear:both!important;margin:28px 0!important}
            #mvm-native-peepso-comments .mvm-native-peepso-comments-title{margin:0 0 16px!important;color:#1966AE!important;font-size:24px!important;line-height:1.25!important;text-transform:none!important}
            body.mvm-native-peepso-ready #comments,body.mvm-native-peepso-ready .comments-area,body.mvm-native-peepso-ready .comment-respond,body.mvm-native-peepso-ready form#commentform{display:none!important}
            @media(max-width:767px){#mvm-native-peepso-comments .mvm-native-peepso-comments-title{font-size:21px!important}}
        </style>
        <script id="mvm-native-peepso-comments-script">
        (function(){
            function place(){
                var box=document.getElementById('mvm-native-peepso-comments');
                if(!box||box.getAttribute('data-mvm-peepso-native')!=='1')return;
                document.body.classList.add('mvm-native-peepso-ready');
                var wpComments=document.getElementById('comments')||document.querySelector('.comments-area')||document.querySelector('.comment-respond');
                if(wpComments&&wpComments.parentNode){wpComments.parentNode.insertBefore(box,wpComments);wpComments.style.setProperty('display','none','important');return;}
                var target=document.querySelector('.wpem-single-event-body')||document.querySelector('.single_event_listing')||document.querySelector('main article')||document.querySelector('main');
                if(target&&box.parentNode!==target){target.appendChild(box);}
            }
            if(document.readyState==='loading'){document.addEventListener('DOMContentLoaded',place,{once:true});}else{place();}
            window.addEventListener('load',place,{once:true});setTimeout(place,250);setTimeout(place,900);
        }());
        </script>
        <?php
        echo '</section>';
    }

    public static function dedupe_css(): void {
        if ( is_singular( 'event_listing' ) ) {
            echo '<style id="mvm-event-peepso-deduplicate-v1">body.single-event_listing #mvm-native-peepso-event-comments{display:none!important}</style>';
        }
    }

    public static function dedupe_script(): void {
        if ( ! is_singular( 'event_listing' ) ) {
            return;
        }
        ?>
        <script id="mvm-event-peepso-deduplicate-v1-js">
        (function(){'use strict';function removeDuplicate(){var keep=document.getElementById('mvm-native-peepso-comments');var duplicate=document.getElementById('mvm-native-peepso-event-comments');if(keep&&duplicate&&duplicate.parentNode){duplicate.parentNode.removeChild(duplicate);}}removeDuplicate();if(document.readyState==='loading'){document.addEventListener('DOMContentLoaded',removeDuplicate,{once:true});}window.addEventListener('load',removeDuplicate,{once:true});window.setTimeout(removeDuplicate,250);window.setTimeout(removeDuplicate,900);}());
        </script>
        <?php
    }

    private static function native_html( int $content_id ): string {
        if ( ! $content_id || ! class_exists( 'PeepSo' ) ) {
            return '';
        }
        $content = get_post( $content_id );
        if ( ! $content instanceof WP_Post || ! in_array( $content->post_type, self::ALLOWED_TYPES, true ) ) {
            return '';
        }
        $notify_id = self::ensure_companion( $content_id );
        if ( ! $notify_id || 'peepso-post' !== get_post_type( $notify_id ) ) {
            return '';
        }

        global $wp_filter, $post, $wp_query;
        $callback = null;
        $hook = 'comment_form_comments_closed';
        if ( ! empty( $wp_filter[ $hook ] ) && is_object( $wp_filter[ $hook ] ) && ! empty( $wp_filter[ $hook ]->callbacks ) ) {
            foreach ( $wp_filter[ $hook ]->callbacks as $items ) {
                foreach ( (array) $items as $item ) {
                    $cb = isset( $item['function'] ) ? $item['function'] : null;
                    if ( is_array( $cb ) && count( $cb ) >= 2 && is_object( $cb[0] ) && 'PeepSo' === get_class( $cb[0] ) && 'blogposts_filter_the_content_blogpost' === (string) $cb[1] && is_callable( $cb ) ) {
                        $callback = $cb;
                        break 2;
                    }
                }
            }
        }
        if ( ! $callback ) {
            return '';
        }

        $original_post = $post;
        $original_query_object = isset( $wp_query ) && is_object( $wp_query ) && isset( $wp_query->queried_object ) && $wp_query->queried_object instanceof WP_Post ? $wp_query->queried_object : null;
        $shadow = clone $content;
        $shadow->post_type = 'post';
        $shadow->comment_status = 'closed';
        $post = $shadow;
        if ( isset( $wp_query ) && is_object( $wp_query ) ) {
            $wp_query->queried_object = $shadow;
        }

        $buffer_level = ob_get_level();
        $html = '';
        try {
            setup_postdata( $post );
            ob_start();
            $returned = call_user_func( $callback, $content_id );
            $html = (string) ob_get_clean();
            if ( is_string( $returned ) && '' !== trim( $returned ) ) {
                $html .= $returned;
            }
        } catch ( Throwable $e ) {
            while ( ob_get_level() > $buffer_level ) {
                ob_end_clean();
            }
            $html = '';
        }

        $post = $original_post;
        if ( isset( $wp_query ) && is_object( $wp_query ) ) {
            $wp_query->queried_object = $original_query_object;
        }
        if ( $original_post instanceof WP_Post ) {
            setup_postdata( $original_post );
        } else {
            wp_reset_postdata();
        }

        return '' !== trim( $html ) && false !== strpos( $html, 'ps-comments--blogpost' ) ? $html : '';
    }

    private static function bridge_class(): string {
        foreach ( get_declared_classes() as $class ) {
            if ( 0 !== strpos( $class, 'MVM_Event_Migrator_Bridge_' ) ) {
                continue;
            }
            try {
                $ref = new ReflectionClass( $class );
                if ( $ref->hasMethod( 'create_peepso_companion' ) ) {
                    return $class;
                }
            } catch ( Throwable $e ) {
                continue;
            }
        }
        return '';
    }

    private static function activity_table(): string {
        global $wpdb;
        foreach ( get_declared_classes() as $class ) {
            if ( 0 !== strpos( $class, 'MVM_Event_Migrator_Bridge_' ) ) {
                continue;
            }
            try {
                $method = new ReflectionMethod( $class, 'activity_table_name' );
                $method->setAccessible( true );
                $candidate = (string) $method->invoke( null );
                if ( $candidate && preg_match( '/^[A-Za-z0-9_]+$/', $candidate ) ) {
                    return $candidate;
                }
            } catch ( Throwable $e ) {
                continue;
            }
        }
        return $wpdb->prefix . 'peepso_activities';
    }

    private static function blogposts_module_id( string $table ): int {
        global $wpdb;
        foreach ( get_declared_classes() as $class ) {
            if ( 0 !== strpos( $class, 'MVM_Event_Migrator_Bridge_' ) ) {
                continue;
            }
            try {
                $method = new ReflectionMethod( $class, 'blogposts_module_id' );
                $method->setAccessible( true );
                $module_id = absint( $method->invoke( null ) );
                if ( $module_id ) {
                    return $module_id;
                }
            } catch ( Throwable $e ) {
                continue;
            }
        }

        $sql = "SELECT activity.act_module_id FROM `{$table}` AS activity INNER JOIN {$wpdb->postmeta} AS linkage ON linkage.meta_key = 'peepso_postnotify' AND CAST(linkage.meta_value AS UNSIGNED) = activity.act_external_id INNER JOIN {$wpdb->posts} AS event_post ON event_post.ID = linkage.post_id AND event_post.post_type = 'event_listing' WHERE activity.act_module_id > 1 GROUP BY activity.act_module_id ORDER BY COUNT(*) DESC, activity.act_module_id DESC LIMIT 1";
        return absint( $wpdb->get_var( $sql ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table names are validated/WordPress-owned.
    }
}
