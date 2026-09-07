<?php

namespace MVM\Hub\Modules\Communications;

use MVM\Hub\Core\Capabilities;
use MVM\Hub\Integrations\PeepSo\Internal_Message_Provider;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Read-only internal-message facade for the central Hub.
 */
final class Internal_Message_Read_Service {
    public function __construct( private readonly Internal_Message_Provider $provider ) {}

    /** @return array<string,mixed>|\WP_Error */
    public function list( int $page = 1, int $per_page = 20 ): array|\WP_Error {
        $user_id = $this->authorized_user();
        if ( is_wp_error( $user_id ) ) {
            return $user_id;
        }
        $page = max( 1, min( 100, $page ) );
        $per_page = max( 1, min( 50, $per_page ) );
        $payload = $this->provider->list_threads_for_user(
            $user_id,
            array( 'page' => $page, 'perPage' => $per_page )
        );
        return Internal_Message_Projector::thread_list( $payload, $page, $per_page );
    }

    /** @return array<string,mixed>|\WP_Error */
    public function get( int $thread_id ): array|\WP_Error {
        $user_id = $this->authorized_user();
        if ( is_wp_error( $user_id ) ) {
            return $user_id;
        }
        if ( $thread_id <= 0 ) {
            return new \WP_Error( 'mvm_messages_thread', 'Ongeldig gesprek.' );
        }
        $thread = $this->provider->get_thread_for_user( $user_id, $thread_id );
        if ( is_wp_error( $thread ) ) {
            return new \WP_Error( 'mvm_messages_thread_forbidden', 'Dit gesprek is niet beschikbaar.' );
        }
        return Internal_Message_Projector::thread_detail( $thread, $user_id );
    }

    /** @return int|\WP_Error */
    private function authorized_user(): int|\WP_Error {
        if ( ! is_user_logged_in() || ! Capabilities::can_access_communications() ) {
            return new \WP_Error( 'mvm_messages_auth_required', 'Geen toegang tot interne berichten.' );
        }
        $allowed = current_user_can( 'manage_options' )
            || current_user_can( Capabilities::COMMUNICATIONS_ADMIN )
            || current_user_can( Capabilities::MESSAGES_ACCESS );
        if ( ! $allowed ) {
            return new \WP_Error( 'mvm_messages_forbidden', 'Geen toegang tot interne berichten.' );
        }
        $user_id = get_current_user_id();
        return $user_id > 0 ? $user_id : new \WP_Error( 'mvm_messages_auth_required', 'Geen toegang tot interne berichten.' );
    }
}
