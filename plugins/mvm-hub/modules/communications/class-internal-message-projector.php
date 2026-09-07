<?php

namespace MVM\Hub\Modules\Communications;

use MVM\Hub\Core\Communications_Policy;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Explicit privacy projection for participant-scoped internal messages.
 *
 * PeepSo/provider payloads may contain implementation metadata, e-mail addresses,
 * login names or storage internals. Only the fields below may cross the Hub
 * module boundary.
 */
final class Internal_Message_Projector {
    /** @param array<string,mixed> $payload @return array<string,mixed> */
    public static function thread_list( array $payload, int $page, int $per_page ): array {
        $source = isset( $payload['items'] ) && is_array( $payload['items'] )
            ? $payload['items']
            : ( isset( $payload['threads'] ) && is_array( $payload['threads'] ) ? $payload['threads'] : array() );

        $items = array();
        foreach ( $source as $thread ) {
            if ( ! is_array( $thread ) ) {
                continue;
            }

            $id = self::positive_id( $thread['id'] ?? $thread['threadId'] ?? 0 );
            if ( 0 === $id ) {
                continue;
            }

            $items[] = array(
                'id'           => $id,
                'title'        => mb_substr( sanitize_text_field( (string) ( $thread['title'] ?? $thread['subject'] ?? 'Gesprek' ) ), 0, 190 ),
                'participants' => self::participants( $thread['participants'] ?? array() ),
                'unreadCount'  => max( 0, (int) ( $thread['unreadCount'] ?? $thread['unread_count'] ?? 0 ) ),
                'updatedAtUtc' => self::datetime( $thread['updatedAtUtc'] ?? $thread['updated_at_utc'] ?? $thread['date'] ?? '' ),
                'archived'     => (bool) ( $thread['archived'] ?? false ),
            );
        }

        return array(
            'items'   => array_values( $items ),
            'page'    => max( 1, min( 100, $page ) ),
            'perPage' => max( 1, min( 50, $per_page ) ),
            'hasMore' => true === ( $payload['hasMore'] ?? $payload['has_more'] ?? false ),
        );
    }

    /** @param array<string,mixed> $thread @return array<string,mixed>|\WP_Error */
    public static function thread_detail( array $thread, int $current_user_id ): array|\WP_Error {
        $id = self::positive_id( $thread['id'] ?? $thread['threadId'] ?? 0 );
        if ( 0 === $id ) {
            return new \WP_Error( 'mvm_messages_provider_payload', 'Het gesprek kon niet veilig worden weergegeven.' );
        }

        $source_messages = isset( $thread['messages'] ) && is_array( $thread['messages'] )
            ? $thread['messages']
            : ( isset( $thread['items'] ) && is_array( $thread['items'] ) ? $thread['items'] : array() );

        $messages = array();
        foreach ( $source_messages as $message ) {
            if ( ! is_array( $message ) ) {
                continue;
            }

            $message_id = self::positive_id( $message['id'] ?? $message['messageId'] ?? 0 );
            $author_id  = self::positive_id( $message['authorUserId'] ?? $message['author_user_id'] ?? $message['userId'] ?? 0 );
            $body       = (string) ( $message['body'] ?? $message['content'] ?? '' );

            $messages[] = array(
                'id'           => $message_id,
                'authorUserId' => $author_id,
                'authorName'   => mb_substr( sanitize_text_field( (string) ( $message['authorName'] ?? $message['displayName'] ?? '' ) ), 0, 120 ),
                'htmlBody'     => Communications_Policy::sanitize_composer_html( $body ),
                'createdAtUtc' => self::datetime( $message['createdAtUtc'] ?? $message['created_at_utc'] ?? $message['date'] ?? '' ),
                'isOwn'        => $author_id > 0 && $author_id === $current_user_id,
            );
        }

        return array(
            'id'           => $id,
            'title'        => mb_substr( sanitize_text_field( (string) ( $thread['title'] ?? $thread['subject'] ?? 'Gesprek' ) ), 0, 190 ),
            'participants' => self::participants( $thread['participants'] ?? array() ),
            'messages'     => array_values( $messages ),
            'unreadCount'  => max( 0, (int) ( $thread['unreadCount'] ?? $thread['unread_count'] ?? 0 ) ),
            'updatedAtUtc' => self::datetime( $thread['updatedAtUtc'] ?? $thread['updated_at_utc'] ?? $thread['date'] ?? '' ),
            'archived'     => (bool) ( $thread['archived'] ?? false ),
        );
    }

    /** @param mixed $participants @return array<int,array{id:int,displayName:string}> */
    private static function participants( mixed $participants ): array {
        if ( ! is_array( $participants ) ) {
            return array();
        }

        $projected = array();
        foreach ( array_slice( $participants, 0, 25 ) as $participant ) {
            if ( is_numeric( $participant ) ) {
                $id = self::positive_id( $participant );
                if ( $id > 0 ) {
                    $user = get_userdata( $id );
                    $projected[] = array(
                        'id'          => $id,
                        'displayName' => $user instanceof \WP_User ? mb_substr( sanitize_text_field( (string) $user->display_name ), 0, 120 ) : '',
                    );
                }
                continue;
            }

            if ( ! is_array( $participant ) ) {
                continue;
            }

            $id = self::positive_id( $participant['id'] ?? $participant['userId'] ?? $participant['user_id'] ?? 0 );
            if ( 0 === $id ) {
                continue;
            }

            $projected[] = array(
                'id'          => $id,
                'displayName' => mb_substr( sanitize_text_field( (string) ( $participant['displayName'] ?? $participant['name'] ?? '' ) ), 0, 120 ),
            );
        }

        return array_values( $projected );
    }

    private static function positive_id( mixed $value ): int {
        return max( 0, absint( $value ) );
    }

    private static function datetime( mixed $value ): string {
        $value = sanitize_text_field( (string) $value );
        return mb_substr( $value, 0, 40 );
    }

    private function __construct() {}
}
