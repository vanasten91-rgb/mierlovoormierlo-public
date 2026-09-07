<?php

namespace MVM\Hub\Integrations\PeepSo;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * PeepSo Chat 8.x adapter.
 *
 * Conversation creation/replies use PeepSo's own domain model. Per-user read
 * and delete state uses the existing recipient table only after proving thread
 * membership. Hub-only archive state is stored as private user metadata rather
 * than guessing at undocumented PeepSo chat-state semantics.
 */
final class PeepSo8_Internal_Message_Provider implements Internal_Message_Provider {
    private const ARCHIVE_META = '_mvm_hub_archived_peepso_threads_v1';
    private const MAX_ARCHIVED = 500;

    private \wpdb $wpdb;
    private Legacy_Readonly_Internal_Message_Provider $reader;

    public function __construct( ?\wpdb $database = null ) {
        global $wpdb;
        $this->wpdb   = $database ?? $wpdb;
        $this->reader = new Legacy_Readonly_Internal_Message_Provider( $this->wpdb );
    }

    public static function supported(): bool {
        if ( ! class_exists( '\\PeepSoMessages' ) ) {
            return false;
        }

        $version = self::runtime_version();
        if ( '' === $version || ! version_compare( $version, '8.0.0.0', '>=' ) || version_compare( $version, '9.0.0.0', '>=' ) ) {
            return false;
        }

        return class_exists( '\\PeepSoMessagesModel' )
            && method_exists( '\\PeepSoMessagesModel', 'create_new_conversation' )
            && method_exists( '\\PeepSoMessagesModel', 'add_to_conversation' )
            && class_exists( '\\PeepSoMessageParticipants' )
            && method_exists( '\\PeepSoMessageParticipants', 'in_conversation' );
    }

    private static function runtime_version(): string {
        if ( defined( '\\PeepSoMessages::PLUGIN_VERSION' ) ) {
            return trim( (string) constant( '\\PeepSoMessages::PLUGIN_VERSION' ) );
        }

        if ( ! function_exists( 'get_plugins' ) ) {
            $plugin_api = ABSPATH . 'wp-admin/includes/plugin.php';
            if ( is_readable( $plugin_api ) ) {
                require_once $plugin_api;
            }
        }

        if ( ! function_exists( 'get_plugins' ) ) {
            return '';
        }

        foreach ( get_plugins() as $plugin_file => $data ) {
            if ( 'peepsomessages.php' !== strtolower( basename( (string) $plugin_file ) ) ) {
                continue;
            }
            $name = strtolower( trim( (string) ( $data['Name'] ?? '' ) ) );
            if ( ! str_contains( $name, 'peepso' ) || ! str_contains( $name, 'chat' ) ) {
                continue;
            }
            $version = trim( (string) ( $data['Version'] ?? '' ) );
            if ( '' !== $version ) {
                return $version;
            }
        }

        return '';
    }

    /** @return array<string,mixed> */
    public function list_threads_for_user( int $user_id, array $query = array() ): array {
        $payload = $this->reader->list_threads_for_user( $user_id, $query );
        $items   = is_array( $payload['items'] ?? null ) ? $payload['items'] : array();
        $archive = array_fill_keys( $this->archived_ids( $user_id ), true );
        $visible = array();

        foreach ( $items as $item ) {
            if ( ! is_array( $item ) ) {
                continue;
            }
            $thread_id = absint( $item['id'] ?? 0 );
            if ( $thread_id <= 0 || ! $this->has_visible_message( $user_id, $thread_id ) ) {
                continue;
            }
            $item['archived'] = isset( $archive[ $thread_id ] );
            $visible[] = $item;
        }

        $payload['items'] = array_values( $visible );
        return $payload;
    }

    /** @return array<string,mixed>|\WP_Error */
    public function get_thread_for_user( int $user_id, int $thread_id ): array|\WP_Error {
        $thread = $this->reader->get_thread_for_user( $user_id, $thread_id );
        if ( is_wp_error( $thread ) ) {
            return $thread;
        }
        $thread['archived'] = in_array( $thread_id, $this->archived_ids( $user_id ), true );
        return $thread;
    }

    /** @return array<string,mixed>|\WP_Error */
    public function create_thread( int $actor_user_id, array $participant_user_ids, string $body ): array|\WP_Error {
        if ( ! self::supported() || ! $this->actor_valid( $actor_user_id ) ) {
            return $this->unavailable();
        }

        $participants = array_values( array_unique( array_filter( array_map( 'absint', $participant_user_ids ) ) ) );
        $participants = array_values( array_diff( $participants, array( $actor_user_id ) ) );
        if ( array() === $participants || count( $participants ) > 25 ) {
            return new \WP_Error( 'mvm_peepso_participants', 'Kies geldige deelnemers.' );
        }
        foreach ( $participants as $participant_id ) {
            if ( ! get_userdata( $participant_id ) instanceof \WP_User ) {
                return new \WP_Error( 'mvm_peepso_participants', 'Kies geldige deelnemers.' );
            }
        }

        $message = self::plain_message( $body );
        if ( '' === $message ) {
            return new \WP_Error( 'mvm_peepso_message_empty', 'Schrijf eerst een bericht.' );
        }

        $model  = new \PeepSoMessagesModel();
        $result = $model->create_new_conversation( $actor_user_id, $message, '', $participants );
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        $thread_id = absint( $result );
        if ( $thread_id <= 0 ) {
            return new \WP_Error( 'mvm_peepso_create_failed', 'Het PeepSo-gesprek kon niet worden aangemaakt.' );
        }

        do_action( 'peepso_messages_new_message', $thread_id );
        return array( 'id' => $thread_id, 'threadId' => $thread_id );
    }

    /** @return array<string,mixed>|\WP_Error */
    public function reply( int $actor_user_id, int $thread_id, string $body ): array|\WP_Error {
        if ( ! self::supported() || ! $this->actor_valid( $actor_user_id ) || ! $this->is_participant( $actor_user_id, $thread_id ) ) {
            return new \WP_Error( 'mvm_peepso_thread_forbidden', 'Dit gesprek is niet beschikbaar.' );
        }

        $message = self::plain_message( $body );
        if ( '' === $message ) {
            return new \WP_Error( 'mvm_peepso_message_empty', 'Schrijf eerst een bericht.' );
        }

        $model  = new \PeepSoMessagesModel();
        $msg_id = $model->add_to_conversation( $actor_user_id, $thread_id, $message );
        if ( is_wp_error( $msg_id ) ) {
            return $msg_id;
        }
        if ( ! $msg_id ) {
            return new \WP_Error( 'mvm_peepso_reply_failed', 'Het antwoord kon niet worden opgeslagen.' );
        }

        do_action( 'peepso_messages_new_message', absint( $msg_id ) );
        return array( 'id' => $thread_id, 'threadId' => $thread_id, 'messageId' => absint( $msg_id ) );
    }

    public function mark_read( int $actor_user_id, int $thread_id ): bool|\WP_Error {
        if ( ! $this->actor_valid( $actor_user_id ) || ! $this->is_participant( $actor_user_id, $thread_id ) ) {
            return new \WP_Error( 'mvm_peepso_thread_forbidden', 'Dit gesprek is niet beschikbaar.' );
        }

        $table = $this->recipients_table();
        $sql   = $this->wpdb->prepare(
            "UPDATE {$table} SET mrec_viewed=1 WHERE mrec_user_id=%d AND (mrec_parent_id=%d OR mrec_msg_id=%d)",
            $actor_user_id,
            $thread_id,
            $thread_id
        );
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- internal table; values are prepared.
        return false === $this->wpdb->query( $sql )
            ? new \WP_Error( 'mvm_peepso_read_failed', 'De leesstatus kon niet worden bijgewerkt.' )
            : true;
    }

    public function archive_for_user( int $actor_user_id, int $thread_id ): bool|\WP_Error {
        if ( ! $this->actor_valid( $actor_user_id ) || ! $this->is_participant( $actor_user_id, $thread_id ) ) {
            return new \WP_Error( 'mvm_peepso_thread_forbidden', 'Dit gesprek is niet beschikbaar.' );
        }

        $ids = $this->archived_ids( $actor_user_id );
        if ( ! in_array( $thread_id, $ids, true ) ) {
            $ids[] = $thread_id;
        }
        $ids = array_slice( array_values( array_unique( array_map( 'absint', $ids ) ) ), -self::MAX_ARCHIVED );
        return false === update_user_meta( $actor_user_id, self::ARCHIVE_META, $ids )
            ? new \WP_Error( 'mvm_peepso_archive_failed', 'Het gesprek kon niet worden gearchiveerd.' )
            : true;
    }

    public function delete_for_user( int $actor_user_id, int $thread_id ): bool|\WP_Error {
        if ( ! $this->actor_valid( $actor_user_id ) || ! $this->is_participant( $actor_user_id, $thread_id ) ) {
            return new \WP_Error( 'mvm_peepso_thread_forbidden', 'Dit gesprek is niet beschikbaar.' );
        }

        $table = $this->recipients_table();
        $sql   = $this->wpdb->prepare(
            "UPDATE {$table} SET mrec_deleted=1,mrec_viewed=1 WHERE mrec_user_id=%d AND (mrec_parent_id=%d OR mrec_msg_id=%d)",
            $actor_user_id,
            $thread_id,
            $thread_id
        );
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- internal table; values are prepared.
        $result = $this->wpdb->query( $sql );
        if ( false === $result ) {
            return new \WP_Error( 'mvm_peepso_delete_failed', 'Het gesprek kon niet voor jou worden verwijderd.' );
        }

        $ids = array_values( array_diff( $this->archived_ids( $actor_user_id ), array( $thread_id ) ) );
        update_user_meta( $actor_user_id, self::ARCHIVE_META, $ids );
        return true;
    }

    private function actor_valid( int $user_id ): bool {
        return $user_id > 0 && is_user_logged_in() && $user_id === get_current_user_id();
    }

    private function is_participant( int $user_id, int $thread_id ): bool {
        if ( ! $this->actor_valid( $user_id ) || $thread_id <= 0 ) {
            return false;
        }

        if ( class_exists( '\\PeepSoMessageParticipants' ) && method_exists( '\\PeepSoMessageParticipants', 'in_conversation' ) ) {
            $participants = new \PeepSoMessageParticipants();
            return true === $participants->in_conversation( $user_id, $thread_id );
        }

        return false;
    }

    private function has_visible_message( int $user_id, int $thread_id ): bool {
        $table = $this->recipients_table();
        $sql   = $this->wpdb->prepare(
            "SELECT 1 FROM {$table} WHERE mrec_user_id=%d AND (mrec_parent_id=%d OR mrec_msg_id=%d) AND COALESCE(mrec_deleted,0)=0 LIMIT 1",
            $user_id,
            $thread_id,
            $thread_id
        );
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- internal table; values are prepared.
        return '1' === (string) $this->wpdb->get_var( $sql );
    }

    /** @return array<int,int> */
    private function archived_ids( int $user_id ): array {
        if ( $user_id <= 0 || $user_id !== get_current_user_id() ) {
            return array();
        }
        $stored = get_user_meta( $user_id, self::ARCHIVE_META, true );
        return array_slice( array_values( array_unique( array_filter( array_map( 'absint', is_array( $stored ) ? $stored : array() ) ) ) ), -self::MAX_ARCHIVED );
    }

    private static function plain_message( string $body ): string {
        $text = trim( wp_strip_all_tags( str_replace( array( '<br>', '<br/>', '<br />', '</p>' ), "\n", $body ) ) );
        $text = mb_substr( $text, 0, 50000 );
        return '' === $text ? '' : htmlspecialchars( $text, ENT_QUOTES | ENT_SUBSTITUTE, get_bloginfo( 'charset' ) ?: 'UTF-8' );
    }

    private function recipients_table(): string {
        return $this->wpdb->prefix . 'peepso_message_recipients';
    }

    private function unavailable(): \WP_Error {
        return new \WP_Error( 'mvm_peepso8_unavailable', 'De geïnstalleerde PeepSo Chat-versie ondersteunt de gevalideerde Hub-provider niet.' );
    }
}
