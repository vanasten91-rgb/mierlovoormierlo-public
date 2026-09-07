<?php

namespace MVM\Hub\Modules\Communications;

use MVM\Hub\Core\Capabilities;
use MVM\Hub\Integrations\Mail\Mailbox_Access;
use MVM\Hub\Integrations\Mail\Mail_Provider;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Mail_Read_Service {
    public function __construct(
        private readonly Mail_Provider $provider,
        private readonly Mailbox_Access $access
    ) {}

    /** @return array<string,mixed>|\WP_Error */
    public function folders( string $mailbox_id ): array|\WP_Error {
        $context = $this->read_context( $mailbox_id );
        if ( is_wp_error( $context ) ) {
            return $context;
        }
        return array(
            'folders'      => Mail_Read_Projector::folders( $this->provider->list_folders( $context['mailboxId'] ) ),
            'capabilities' => self::project_capabilities( $this->provider->capabilities( $context['mailboxId'] ) ),
        );
    }

    /** @param array<string,mixed> $input @return array<string,mixed>|\WP_Error */
    public function messages( string $mailbox_id, string $folder_id, array $input = array() ): array|\WP_Error {
        $context = $this->read_context( $mailbox_id );
        if ( is_wp_error( $context ) ) {
            return $context;
        }
        $folder_id = self::opaque_id( $folder_id, 128 );
        $folder = '' === $folder_id ? null : $this->folder( $context['mailboxId'], $folder_id );
        if ( null === $folder ) {
            return new \WP_Error( 'mvm_mail_folder_missing', 'Deze map is niet beschikbaar.' );
        }

        $query = Mail_Query::normalize( $input );
        $provider_query = array(
            'limit'     => (int) $query['perPage'] + 1,
            'offset'    => ( (int) $query['page'] - 1 ) * (int) $query['perPage'],
            'sort'      => $query['sort'],
            'direction' => $query['direction'],
            'state'     => $query['state'],
            'search'    => $query['search'],
            'cursor'    => $query['cursor'],
        );
        $provider_payload = $this->provider->list_messages( $context['mailboxId'], $folder_id, $provider_query );
        $blocked_hidden = 0;
        if ( is_array( $provider_payload['messages'] ?? null ) ) {
            foreach ( $provider_payload['messages'] as &$message ) {
                if ( is_array( $message ) ) {
                    $message['senderBlocked'] = Mail_Sender_Blocklist::is_blocked( $context['mailboxId'], (string) ( $message['fromAddress'] ?? '' ) );
                }
            }
            unset( $message );
            if ( 'inbox' === sanitize_key( (string) ( $folder['specialUse'] ?? '' ) ) ) {
                $before = count( $provider_payload['messages'] );
                $provider_payload['messages'] = array_values( array_filter(
                    $provider_payload['messages'],
                    static fn( mixed $message ): bool => is_array( $message ) && empty( $message['senderBlocked'] )
                ) );
                $blocked_hidden = $before - count( $provider_payload['messages'] );
            }
        }

        $projected = Mail_Read_Projector::message_list( $provider_payload, $query );
        foreach ( $projected['messages'] as &$message ) {
            if ( is_array( $message ) ) {
                $message['pinned'] = Mail_Message_Preferences::is_pinned( $context['userId'], $context['mailboxId'], (string) ( $message['id'] ?? '' ) );
            }
        }
        unset( $message );
        usort(
            $projected['messages'],
            static fn( array $left, array $right ): int => (int) ( $right['pinned'] ?? false ) <=> (int) ( $left['pinned'] ?? false )
        );
        $projected['blockedHidden'] = max( 0, $blocked_hidden );
        return $projected;
    }

    /** @return array<string,mixed>|\WP_Error */
    public function message( string $mailbox_id, string $message_id ): array|\WP_Error {
        $context = $this->read_context( $mailbox_id );
        if ( is_wp_error( $context ) ) {
            return $context;
        }
        $message_id = self::opaque_id( $message_id, 260 );
        if ( '' === $message_id ) {
            return new \WP_Error( 'mvm_mail_message_invalid', 'Dit bericht is niet beschikbaar.' );
        }
        $access = $this->access->authorize_message( $context['userId'], $context['mailboxId'], $message_id );
        if ( is_wp_error( $access ) ) {
            return new \WP_Error( 'mvm_mail_message_forbidden', 'Dit bericht is niet beschikbaar.' );
        }
        $message = $this->provider->get_message( $context['mailboxId'], $message_id );
        if ( is_wp_error( $message ) ) {
            return $message;
        }
        $projected = Mail_Read_Projector::message_detail( $message );
        if ( is_wp_error( $projected ) ) {
            return $projected;
        }
        $projected['pinned'] = Mail_Message_Preferences::is_pinned( $context['userId'], $context['mailboxId'], $message_id );
        $projected['senderBlocked'] = Mail_Sender_Blocklist::is_blocked( $context['mailboxId'], (string) ( $projected['fromAddress'] ?? '' ) );
        return $projected;
    }

    /** @return array{userId:int,mailboxId:string}|\WP_Error */
    private function read_context( string $mailbox_id ): array|\WP_Error {
        if ( ! is_user_logged_in() || ! Capabilities::can_read_mail() ) {
            return new \WP_Error( 'mvm_mail_read_forbidden', 'Geen toegang tot deze mailbox.' );
        }
        $mailbox_id = self::opaque_id( $mailbox_id, 128 );
        $user_id    = get_current_user_id();
        if ( '' === $mailbox_id || $user_id <= 0 ) {
            return new \WP_Error( 'mvm_mail_read_forbidden', 'Geen toegang tot deze mailbox.' );
        }
        $access = $this->access->authorize_read( $user_id, $mailbox_id );
        return is_wp_error( $access ) ? $access : array( 'userId' => $user_id, 'mailboxId' => $mailbox_id );
    }

    /** @return array<string,mixed>|null */
    private function folder( string $mailbox_id, string $folder_id ): ?array {
        foreach ( Mail_Read_Projector::folders( $this->provider->list_folders( $mailbox_id ) ) as $folder ) {
            if ( (string) ( $folder['id'] ?? '' ) === $folder_id ) {
                return $folder;
            }
        }
        return null;
    }

    /** @param array<string,bool|int|string> $capabilities @return array<string,bool> */
    private static function project_capabilities( array $capabilities ): array {
        $safe = array();
        foreach ( array( 'read', 'folders', 'drafts', 'move', 'flags', 'attachments', 'send', 'remoteImages' ) as $key ) {
            $safe[ $key ] = true === ( $capabilities[ $key ] ?? false );
        }
        return $safe;
    }

    private static function opaque_id( string $value, int $max_length ): string {
        $value = trim( $value );
        return strlen( $value ) <= $max_length && 1 === preg_match( '/^[A-Za-z0-9._:-]+$/', $value ) ? $value : '';
    }
}
