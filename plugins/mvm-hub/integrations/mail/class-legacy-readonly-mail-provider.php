<?php

namespace MVM\Hub\Integrations\Mail;

use MVM\Hub\Core\Communications_Policy;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Transitional provider for the existing production read-only mailbox routes.
 *
 * No credentials are handled here. Reads execute through WordPress in-process
 * REST so the existing route permission callback remains an additional security
 * boundary. Every write/delivery method fails closed.
 */
final class Legacy_Readonly_Mail_Provider implements Mail_Provider {
    private const MAILBOX_ID = 'editorial';
    private const INBOX_ID   = 'inbox';

    public function list_folders( string $mailbox_id ): array {
        if ( ! $this->valid_mailbox( $mailbox_id ) ) {
            return array();
        }

        return array(
            array(
                'id'       => self::INBOX_ID,
                'name'     => 'Inbox',
                'type'     => 'inbox',
                'readOnly' => true,
            ),
        );
    }

    public function list_messages( string $mailbox_id, string $folder_id, array $query = array() ): array {
        if ( ! $this->valid_mailbox( $mailbox_id ) || self::INBOX_ID !== sanitize_key( $folder_id ) ) {
            return $this->empty_list( 'invalid_mailbox_or_folder' );
        }

        $limit  = max( 1, min( 50, (int) ( $query['limit'] ?? 20 ) ) );
        $offset = max( 0, min( 5000, (int) ( $query['offset'] ?? 0 ) ) );

        $request = new \WP_REST_Request( 'GET', '/mvm/v1/editorial/mail/messages' );
        $request->set_param( 'limit', $limit );
        $request->set_param( 'offset', $offset );
        $response = rest_do_request( $request );

        if ( $response->is_error() ) {
            return $this->empty_list( 'legacy_provider_unavailable' );
        }

        $data = $response->get_data();
        if ( ! is_array( $data ) ) {
            return $this->empty_list( 'invalid_provider_payload' );
        }

        $messages = isset( $data['messages'] ) && is_array( $data['messages'] ) ? $data['messages'] : array();
        $normalized = array();
        foreach ( $messages as $message ) {
            if ( ! is_array( $message ) ) {
                continue;
            }
            $normalized[] = $this->normalize_list_message( $message );
        }

        return array(
            'messages' => $normalized,
            'total'    => max( 0, (int) ( $data['total'] ?? count( $normalized ) ) ),
            'readonly' => true,
        );
    }

    public function get_message( string $mailbox_id, string $message_id ): array|\WP_Error {
        if ( ! $this->valid_mailbox( $mailbox_id ) || ! ctype_digit( $message_id ) ) {
            return new \WP_Error( 'mvm_mail_invalid_message', 'Ongeldig mailbox- of bericht-ID.' );
        }

        $request = new \WP_REST_Request( 'GET', '/mvm/v1/editorial/mail/messages/' . rawurlencode( $message_id ) );
        $response = rest_do_request( $request );
        if ( $response->is_error() ) {
            return new \WP_Error( 'mvm_mail_read_failed', 'Het bericht kon niet veilig worden gelezen.' );
        }

        $data = $response->get_data();
        if ( ! is_array( $data ) || array() === $data ) {
            return new \WP_Error( 'mvm_mail_not_found', 'Het bericht is niet beschikbaar.' );
        }

        return $this->normalize_detail_message( $data );
    }

    public function save_draft( string $mailbox_id, array $draft, ?string $draft_id = null ): array|\WP_Error {
        unset( $mailbox_id, $draft, $draft_id );
        return $this->read_only_error();
    }

    public function create_folder( string $mailbox_id, string $name, ?string $parent_id = null ): array|\WP_Error {
        unset( $mailbox_id, $name, $parent_id );
        return $this->read_only_error();
    }

    public function rename_folder( string $mailbox_id, string $folder_id, string $name ): bool|\WP_Error {
        unset( $mailbox_id, $folder_id, $name );
        return $this->read_only_error();
    }

    public function delete_folder( string $mailbox_id, string $folder_id ): bool|\WP_Error {
        unset( $mailbox_id, $folder_id );
        return $this->read_only_error();
    }

    public function move_message( string $mailbox_id, string $message_id, string $folder_id ): bool|\WP_Error {
        unset( $mailbox_id, $message_id, $folder_id );
        return $this->read_only_error();
    }

    public function set_read_state( string $mailbox_id, string $message_id, bool $is_read ): bool|\WP_Error {
        unset( $mailbox_id, $message_id, $is_read );
        return $this->read_only_error();
    }

    public function set_flagged_state( string $mailbox_id, string $message_id, bool $is_flagged ): bool|\WP_Error {
        unset( $mailbox_id, $message_id, $is_flagged );
        return $this->read_only_error();
    }

    public function deliver( string $mailbox_id, array $message, string $idempotency_key ): array|\WP_Error {
        unset( $mailbox_id, $message, $idempotency_key );
        return $this->read_only_error();
    }

    public function capabilities( string $mailbox_id ): array {
        if ( ! $this->valid_mailbox( $mailbox_id ) ) {
            return array();
        }

        return array(
            'read'         => true,
            'folders'      => false,
            'drafts'       => false,
            'move'         => false,
            'flags'        => false,
            'attachments'  => false,
            'send'         => false,
            'remoteImages' => Communications_Policy::remote_images_allowed_by_default(),
        );
    }

    /** @param array<string,mixed> $message */
    private function normalize_list_message( array $message ): array {
        return array(
            'id'      => (string) max( 0, (int) ( $message['uid'] ?? 0 ) ),
            'subject' => sanitize_text_field( (string) ( $message['subject'] ?? '' ) ),
            'from'    => sanitize_text_field( (string) ( $message['from'] ?? '' ) ),
            'date'    => sanitize_text_field( (string) ( $message['date'] ?? '' ) ),
            'seen'    => (bool) ( $message['seen'] ?? false ),
            'size'    => max( 0, (int) ( $message['size'] ?? 0 ) ),
        );
    }

    /** @param array<string,mixed> $message */
    private function normalize_detail_message( array $message ): array {
        $normalized = $this->normalize_list_message( $message );
        $plain = (string) ( $message['text'] ?? $message['body'] ?? '' );
        $html  = (string) ( $message['html'] ?? '' );

        $normalized['text'] = sanitize_textarea_field( wp_strip_all_tags( $plain ) );
        $normalized['html'] = '' !== $html ? Communications_Policy::sanitize_incoming_html( $html ) : '';
        $normalized['attachments'] = $this->normalize_attachments( $message['attachments'] ?? array() );
        $normalized['remoteImagesBlocked'] = ! Communications_Policy::remote_images_allowed_by_default();

        return $normalized;
    }

    private function normalize_attachments( mixed $attachments ): array {
        if ( ! is_array( $attachments ) ) {
            return array();
        }

        $safe = array();
        foreach ( $attachments as $attachment ) {
            if ( ! is_array( $attachment ) ) {
                continue;
            }
            $safe[] = array(
                'id'   => sanitize_text_field( (string) ( $attachment['id'] ?? $attachment['part'] ?? '' ) ),
                'name' => sanitize_file_name( (string) ( $attachment['name'] ?? $attachment['filename'] ?? '' ) ),
                'mime' => sanitize_mime_type( (string) ( $attachment['mime'] ?? $attachment['type'] ?? '' ) ),
                'size' => max( 0, (int) ( $attachment['size'] ?? 0 ) ),
            );
        }
        return $safe;
    }

    private function valid_mailbox( string $mailbox_id ): bool {
        return self::MAILBOX_ID === sanitize_key( $mailbox_id );
    }

    private function read_only_error(): \WP_Error {
        return new \WP_Error( 'mvm_mail_read_only', 'Deze mailboxprovider is tijdens de migratie alleen-lezen.' );
    }

    private function empty_list( string $error ): array {
        return array(
            'messages' => array(),
            'total'    => 0,
            'readonly' => true,
            'error'    => sanitize_key( $error ),
        );
    }
}
