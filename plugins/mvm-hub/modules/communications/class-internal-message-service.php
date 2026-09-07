<?php

namespace MVM\Hub\Modules\Communications;

use MVM\Hub\Core\Capabilities;
use MVM\Hub\Core\Communications_Policy;
use MVM\Hub\Integrations\Mail\Communications_Action_Security;
use MVM\Hub\Integrations\Mail\Communications_Audit;
use MVM\Hub\Integrations\PeepSo\Internal_Message_Provider;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Participant-scoped internal messaging service.
 *
 * Team/admin status never bypasses the provider's per-user thread scope. There
 * is no endpoint in the parallel migration; future controllers still require
 * CSRF/nonces in addition to these business/session checks.
 */
final class Internal_Message_Service {
    private const MAX_PARTICIPANTS = 25;
    private const MAX_BODY_BYTES   = 50000;

    public function __construct(
        private readonly Internal_Message_Provider $provider,
        private readonly Communications_Action_Security $security,
        private readonly Communications_Audit $audit
    ) {}

    /** @return array<string,mixed>|\WP_Error */
    public function list( int $page = 1, int $per_page = 20 ): array|\WP_Error {
        $user_id = $this->authorized_user();
        if ( is_wp_error( $user_id ) ) {
            return $user_id;
        }

        $page     = max( 1, min( 100, $page ) );
        $per_page = max( 1, min( 50, $per_page ) );
        $payload  = $this->provider->list_threads_for_user(
            $user_id,
            array(
                'page'    => $page,
                'perPage' => $per_page,
            )
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

    /** @param array<int,int|string> $participant_user_ids @return array<string,mixed>|\WP_Error */
    public function create( array $participant_user_ids, string $body ): array|\WP_Error {
        $user_id = $this->authorized_user( true, false, true );
        if ( is_wp_error( $user_id ) ) {
            return $user_id;
        }

        $participants = self::participants( $participant_user_ids, $user_id );
        if ( is_wp_error( $participants ) ) {
            return $participants;
        }

        $body = self::body( $body );
        if ( is_wp_error( $body ) ) {
            return $body;
        }

        $result = $this->provider->create_thread( $user_id, $participants, $body );
        $thread_id = is_wp_error( $result ) ? 0 : self::result_thread_id( $result );
        $this->audit->record(
            'messages.thread.create',
            is_wp_error( $result ) ? 'failed' : 'success',
            $user_id,
            self::safe_ref( $thread_id ),
            array( 'participant_count' => count( $participants ) )
        );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return $this->project_mutation_result( $user_id, $thread_id );
    }

    /** @return array<string,mixed>|\WP_Error */
    public function reply( int $thread_id, string $body ): array|\WP_Error {
        $user_id = $this->authorized_user( true, false, true );
        if ( is_wp_error( $user_id ) ) {
            return $user_id;
        }

        if ( $thread_id <= 0 ) {
            return new \WP_Error( 'mvm_messages_thread', 'Ongeldig gesprek.' );
        }

        // Participant check happens before the write even if the provider also
        // checks it internally, preventing a write method from becoming the first
        // horizontal-authorization boundary.
        $thread = $this->provider->get_thread_for_user( $user_id, $thread_id );
        if ( is_wp_error( $thread ) ) {
            return new \WP_Error( 'mvm_messages_thread_forbidden', 'Dit gesprek is niet beschikbaar.' );
        }

        $body = self::body( $body );
        if ( is_wp_error( $body ) ) {
            return $body;
        }

        $result = $this->provider->reply( $user_id, $thread_id, $body );
        $this->audit->record(
            'messages.thread.reply',
            is_wp_error( $result ) ? 'failed' : 'success',
            $user_id,
            self::safe_ref( $thread_id )
        );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return $this->project_mutation_result( $user_id, $thread_id );
    }

    public function mark_read( int $thread_id ): true|\WP_Error {
        return $this->own_mutation( 'messages.thread.read', $thread_id, 'read' );
    }

    public function archive( int $thread_id ): true|\WP_Error {
        return $this->own_mutation( 'messages.thread.archive', $thread_id, 'archive' );
    }

    public function delete( int $thread_id ): true|\WP_Error {
        return $this->own_mutation( 'messages.thread.delete', $thread_id, 'delete' );
    }

    /** @return true|\WP_Error */
    private function own_mutation( string $event, int $thread_id, string $action ): true|\WP_Error {
        $user_id = $this->authorized_user( false, true, true );
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

        $result = match ( $action ) {
            'read'    => $this->provider->mark_read( $user_id, $thread_id ),
            'archive' => $this->provider->archive_for_user( $user_id, $thread_id ),
            'delete'  => $this->provider->delete_for_user( $user_id, $thread_id ),
            default   => new \WP_Error( 'mvm_messages_action', 'Onbekende berichtactie.' ),
        };

        $this->audit->record(
            $event,
            is_wp_error( $result ) || false === $result ? 'failed' : 'success',
            $user_id,
            self::safe_ref( $thread_id )
        );

        return is_wp_error( $result ) ? $result : ( true === $result ? true : new \WP_Error( 'mvm_messages_mutation_failed', 'De actie kon niet worden uitgevoerd.' ) );
    }

    /** @return int|\WP_Error */
    private function authorized_user( bool $send = false, bool $manage_own = false, bool $mutation = false ): int|\WP_Error {
        if ( ! is_user_logged_in() || ! Capabilities::can_access_communications() ) {
            return new \WP_Error( 'mvm_messages_auth_required', 'Geen toegang tot interne berichten.' );
        }

        $allowed = current_user_can( 'manage_options' )
            || current_user_can( Capabilities::COMMUNICATIONS_ADMIN )
            || current_user_can( Capabilities::MESSAGES_ACCESS );

        if ( $send ) {
            $allowed = $allowed && (
                current_user_can( 'manage_options' )
                || current_user_can( Capabilities::COMMUNICATIONS_ADMIN )
                || current_user_can( Capabilities::MESSAGES_SEND )
            );
        }

        if ( $manage_own ) {
            $allowed = $allowed && (
                current_user_can( 'manage_options' )
                || current_user_can( Capabilities::COMMUNICATIONS_ADMIN )
                || current_user_can( Capabilities::MESSAGES_MANAGE_OWN )
            );
        }

        if ( ! $allowed ) {
            return new \WP_Error( 'mvm_messages_forbidden', 'Je hebt geen toestemming voor deze actie.' );
        }

        $user_id = get_current_user_id();
        if ( $mutation ) {
            $session = $this->security->authorize_session( $user_id );
            if ( is_wp_error( $session ) ) {
                return $session;
            }
        }

        return $user_id;
    }

    /** @param array<int,int|string> $ids @return array<int,int>|\WP_Error */
    private static function participants( array $ids, int $actor_user_id ): array|\WP_Error {
        $participants = array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );
        $participants = array_values( array_diff( $participants, array( $actor_user_id ) ) );

        if ( array() === $participants || count( $participants ) > self::MAX_PARTICIPANTS ) {
            return new \WP_Error( 'mvm_messages_participants', 'Kies een geldige groep ontvangers.' );
        }

        foreach ( $participants as $participant_id ) {
            $participant = get_userdata( $participant_id );
            if ( ! $participant instanceof \WP_User || ! user_can( $participant, 'read' ) ) {
                return new \WP_Error( 'mvm_messages_participants', 'Kies een geldige groep ontvangers.' );
            }
        }

        return $participants;
    }

    /** @return string|\WP_Error */
    private static function body( string $body ): string|\WP_Error {
        if ( strlen( $body ) > self::MAX_BODY_BYTES ) {
            return new \WP_Error( 'mvm_messages_body_too_large', 'Het bericht is te groot.' );
        }

        $body = trim( Communications_Policy::sanitize_composer_html( $body ) );
        return '' === trim( wp_strip_all_tags( $body ) ) ? new \WP_Error( 'mvm_messages_body_required', 'Schrijf eerst een bericht.' ) : $body;
    }

    /** @param array<string,mixed> $result */
    private static function result_thread_id( array $result ): int {
        return max( 0, absint( $result['id'] ?? $result['threadId'] ?? 0 ) );
    }

    /** @return array<string,mixed>|\WP_Error */
    private function project_mutation_result( int $user_id, int $thread_id ): array|\WP_Error {
        if ( $thread_id <= 0 ) {
            return new \WP_Error( 'mvm_messages_provider_payload', 'Het gesprek is opgeslagen maar kon niet veilig worden teruggelezen.' );
        }

        $thread = $this->provider->get_thread_for_user( $user_id, $thread_id );
        if ( is_wp_error( $thread ) ) {
            return new \WP_Error( 'mvm_messages_provider_payload', 'Het gesprek is opgeslagen maar kon niet veilig worden teruggelezen.' );
        }

        return Internal_Message_Projector::thread_detail( $thread, $user_id );
    }

    private static function safe_ref( int $thread_id ): string {
        return $thread_id > 0 ? substr( wp_hash( (string) $thread_id, 'mvm_hub_message_thread' ), 0, 24 ) : '';
    }
}
