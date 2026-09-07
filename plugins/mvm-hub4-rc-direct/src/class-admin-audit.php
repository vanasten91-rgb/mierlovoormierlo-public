<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class MvM_Hub4_Admin_Audit {
    private static array $status_events = array();

    public static function init(): void {
        add_action( 'post_updated', array( __CLASS__, 'post_updated' ), 20, 3 );
        add_action( 'transition_post_status', array( __CLASS__, 'status_changed' ), 20, 3 );
        add_action( 'trashed_post', array( __CLASS__, 'post_trashed' ), 20, 2 );
        add_action( 'untrashed_post', array( __CLASS__, 'post_untrashed' ), 20, 2 );
        add_action( 'before_delete_post', array( __CLASS__, 'post_deleted' ), 20, 2 );
        add_action( 'add_attachment', array( __CLASS__, 'attachment_added' ), 20 );
        add_action( 'delete_attachment', array( __CLASS__, 'attachment_deleted' ), 20 );
        add_action( 'wp_set_comment_status', array( __CLASS__, 'comment_status_changed' ), 20, 2 );
        add_action( 'set_user_role', array( __CLASS__, 'user_role_changed' ), 20, 3 );
        add_action( 'profile_update', array( __CLASS__, 'profile_updated' ), 20, 2 );
        add_action( 'user_register', array( __CLASS__, 'user_created' ), 20, 2 );
        add_action( 'activated_plugin', array( __CLASS__, 'plugin_activated' ), 20, 2 );
        add_action( 'deactivated_plugin', array( __CLASS__, 'plugin_deactivated' ), 20, 2 );
        add_action( 'upgrader_process_complete', array( __CLASS__, 'upgrade_completed' ), 20, 2 );
    }

    public static function post_updated( int $post_id, WP_Post $post_after, WP_Post $post_before ): void {
        if ( ! self::should_log_content( $post_after ) ) {
            return;
        }

        $changed = array();
        if ( $post_before->post_title !== $post_after->post_title ) {
            $changed[] = 'title';
        }
        if ( $post_before->post_content !== $post_after->post_content ) {
            $changed[] = 'content';
        }
        if ( $post_before->post_excerpt !== $post_after->post_excerpt ) {
            $changed[] = 'excerpt';
        }
        if ( $post_before->post_author !== $post_after->post_author ) {
            $changed[] = 'author';
        }

        if ( ! $changed ) {
            return;
        }

        MvM_Hub4_Audit::log(
            'admin.content_updated',
            'success',
            array(
                'object_type' => sanitize_key( $post_after->post_type ),
                'object_id'   => $post_id,
                'context'     => array( 'changed_fields' => implode( ',', $changed ) ),
            )
        );
    }

    public static function status_changed( string $new_status, string $old_status, WP_Post $post ): void {
        if ( $new_status === $old_status || ! self::should_log_content( $post ) ) {
            return;
        }

        $key = $post->ID . ':' . $old_status . ':' . $new_status;
        if ( isset( self::$status_events[ $key ] ) ) {
            return;
        }
        self::$status_events[ $key ] = true;

        MvM_Hub4_Audit::log(
            'admin.content_status_changed',
            'success',
            array(
                'object_type'     => sanitize_key( $post->post_type ),
                'object_id'       => (int) $post->ID,
                'transition_from' => sanitize_key( $old_status ),
                'transition_to'   => sanitize_key( $new_status ),
            )
        );
    }

    public static function post_trashed( int $post_id, string $previous_status ): void {
        $post = get_post( $post_id );
        if ( ! $post instanceof WP_Post || ! self::should_log_content( $post ) ) {
            return;
        }
        MvM_Hub4_Audit::log(
            'admin.content_trashed',
            'success',
            array(
                'object_type'     => sanitize_key( $post->post_type ),
                'object_id'       => $post_id,
                'transition_from' => sanitize_key( $previous_status ),
                'transition_to'   => 'trash',
            )
        );
    }

    public static function post_untrashed( int $post_id, string $previous_status ): void {
        $post = get_post( $post_id );
        if ( ! $post instanceof WP_Post || ! self::should_log_content( $post ) ) {
            return;
        }
        MvM_Hub4_Audit::log(
            'admin.content_restored',
            'success',
            array(
                'object_type'     => sanitize_key( $post->post_type ),
                'object_id'       => $post_id,
                'transition_from' => 'trash',
                'transition_to'   => sanitize_key( $previous_status ),
            )
        );
    }

    public static function post_deleted( int $post_id, WP_Post $post ): void {
        if ( ! self::should_log_content( $post ) ) {
            return;
        }
        MvM_Hub4_Audit::log( 'admin.content_deleted', 'success', array( 'object_type' => sanitize_key( $post->post_type ), 'object_id' => $post_id ) );
    }

    public static function attachment_added( int $attachment_id ): void {
        if ( ! self::is_staff_action() ) {
            return;
        }
        MvM_Hub4_Audit::log( 'admin.media_uploaded', 'success', array( 'object_type' => 'attachment', 'object_id' => $attachment_id ) );
    }

    public static function attachment_deleted( int $attachment_id ): void {
        if ( ! self::is_staff_action() ) {
            return;
        }
        MvM_Hub4_Audit::log( 'admin.media_deleted', 'success', array( 'object_type' => 'attachment', 'object_id' => $attachment_id ) );
    }

    public static function comment_status_changed( int $comment_id, string $comment_status ): void {
        if ( ! self::is_staff_action() ) {
            return;
        }
        MvM_Hub4_Audit::log(
            'admin.comment_status_changed',
            'success',
            array(
                'object_type' => 'comment',
                'object_id'   => $comment_id,
                'transition_to' => sanitize_key( $comment_status ),
            )
        );
    }

    public static function user_role_changed( int $user_id, string $role, array $old_roles ): void {
        if ( ! self::is_staff_action() ) {
            return;
        }
        MvM_Hub4_Audit::log(
            'admin.user_role_changed',
            'success',
            array(
                'object_type' => 'user',
                'object_id'   => $user_id,
                'context'     => array(
                    'old_roles' => implode( ',', array_map( 'sanitize_key', $old_roles ) ),
                    'new_role'  => sanitize_key( $role ),
                ),
            )
        );
    }

    public static function profile_updated( int $user_id, WP_User $old_user_data ): void {
        unset( $old_user_data );
        if ( ! self::is_staff_action() ) {
            return;
        }
        MvM_Hub4_Audit::log( 'admin.user_profile_updated', 'success', array( 'object_type' => 'user', 'object_id' => $user_id ) );
    }

    public static function user_created( int $user_id, array $userdata ): void {
        unset( $userdata );
        if ( ! self::is_staff_action() ) {
            return;
        }
        MvM_Hub4_Audit::log( 'admin.user_created', 'success', array( 'object_type' => 'user', 'object_id' => $user_id ) );
    }

    public static function plugin_activated( string $plugin, bool $network_wide ): void {
        self::log_plugin_event( 'admin.plugin_activated', $plugin, $network_wide );
    }

    public static function plugin_deactivated( string $plugin, bool $network_wide ): void {
        self::log_plugin_event( 'admin.plugin_deactivated', $plugin, $network_wide );
    }

    public static function upgrade_completed( WP_Upgrader $upgrader, array $hook_extra ): void {
        unset( $upgrader );
        if ( ! self::is_staff_action() ) {
            return;
        }

        $type   = sanitize_key( (string) ( $hook_extra['type'] ?? 'unknown' ) );
        $action = sanitize_key( (string) ( $hook_extra['action'] ?? 'update' ) );
        MvM_Hub4_Audit::log(
            'admin.upgrade_completed',
            'success',
            array(
                'object_type' => $type,
                'context'     => array( 'action' => $action ),
            )
        );
    }

    private static function log_plugin_event( string $event_code, string $plugin, bool $network_wide ): void {
        if ( ! self::is_staff_action() ) {
            return;
        }
        MvM_Hub4_Audit::log(
            $event_code,
            'success',
            array(
                'object_type' => 'plugin',
                'context'     => array(
                    'plugin'       => sanitize_text_field( plugin_basename( $plugin ) ),
                    'network_wide' => $network_wide ? 1 : 0,
                ),
            )
        );
    }

    private static function should_log_content( WP_Post $post ): bool {
        if ( ! self::is_staff_action() || wp_is_post_revision( $post->ID ) || wp_is_post_autosave( $post->ID ) ) {
            return false;
        }

        return in_array( $post->post_type, self::audited_post_types(), true );
    }

    private static function audited_post_types(): array {
        return array(
            'post',
            'event_listing',
            'mvm_encyclopedie',
            'mvm_persoon',
            'mvm_locatie',
            'mvm_gebouw',
            'mvm_gebeurtenis',
            'mvm_vereniging',
            'mvm_bedrijf',
            'mvm_beeld',
            'mvm_bron',
            'mvm_assignment',
            'mvm_media_idea',
            'mvm_staff_message',
            'mvm_news_tip',
        );
    }

    private static function is_staff_action(): bool {
        return is_user_logged_in()
            && ( current_user_can( MvM_Hub4_Security::HUB_CAPABILITY ) || current_user_can( 'manage_options' ) );
    }
}
