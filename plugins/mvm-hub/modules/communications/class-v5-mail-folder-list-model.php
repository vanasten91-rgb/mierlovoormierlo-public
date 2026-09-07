<?php

namespace MVM\Hub\Modules\Communications;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Dormant folder navigation projection for one already-authorized Mail V2 mailbox.
 *
 * Folder metadata is bounded and opaque. The model does not query a provider,
 * claim sync ownership or expose mailbox credentials.
 */
final class V5_Mail_Folder_List_Model {
    private const MAX_FOLDERS = 100;

    /**
     * @param list<array<string,mixed>> $folders
     * @return array<string,mixed>
     */
    public static function build( string $mailbox_id, array $folders, bool $can_read, bool $can_manage_folders ): array {
        $mailbox_id = self::opaque_id( $mailbox_id, 128 );
        if ( '' === $mailbox_id || ! $can_read ) {
            return self::empty_projection();
        }

        $items = array();
        foreach ( array_slice( $folders, 0, self::MAX_FOLDERS ) as $candidate ) {
            if ( ! is_array( $candidate ) ) {
                continue;
            }

            $folder_id = self::opaque_id( (string) ( $candidate['folderId'] ?? $candidate['id'] ?? '' ), 190 );
            if ( '' === $folder_id ) {
                continue;
            }

            $role = strtolower( trim( (string) ( $candidate['role'] ?? 'custom' ) ) );
            if ( ! in_array( $role, array( 'inbox', 'sent', 'drafts', 'archive', 'trash', 'spam', 'custom' ), true ) ) {
                $role = 'custom';
            }

            $is_system = in_array( $role, array( 'inbox', 'sent', 'drafts', 'trash', 'spam' ), true );
            $target_mutable = $can_manage_folders && ! $is_system;

            $items[] = array(
                'folderId' => $folder_id,
                'label'    => self::text( (string) ( $candidate['label'] ?? $candidate['name'] ?? 'Map' ), 100 ),
                'role'     => $role,
                'unread'   => max( 0, min( 99999, (int) ( $candidate['unread'] ?? 0 ) ) ),
                'total'    => max( 0, min( 999999, (int) ( $candidate['total'] ?? 0 ) ) ),
                'currentActions' => array(
                    'open'   => true,
                    'rename' => false,
                    'delete' => false,
                ),
                'targetActions' => array(
                    'open'   => true,
                    'rename' => $target_mutable,
                    'delete' => $target_mutable,
                ),
            );
        }

        usort(
            $items,
            static function ( array $a, array $b ): int {
                $order = array( 'inbox' => 10, 'drafts' => 20, 'sent' => 30, 'archive' => 40, 'spam' => 90, 'trash' => 100, 'custom' => 50 );
                $left = $order[ $a['role'] ] ?? 50;
                $right = $order[ $b['role'] ] ?? 50;
                return $left <=> $right ?: strcasecmp( (string) $a['label'], (string) $b['label'] );
            }
        );

        return array(
            'mailboxId' => $mailbox_id,
            'folders'   => $items,
            'count'     => count( $items ),
            'currentProductionMode' => 'read_only',
            'productionWritesEnabled' => false,
            'security' => array(
                'searchVisibility' => 'none',
                'aiVisibility'     => 'none',
            ),
        );
    }

    /** @return array<string,mixed> */
    private static function empty_projection(): array {
        return array(
            'mailboxId' => '',
            'folders'   => array(),
            'count'     => 0,
            'currentProductionMode' => 'read_only',
            'productionWritesEnabled' => false,
            'security' => array(
                'searchVisibility' => 'none',
                'aiVisibility'     => 'none',
            ),
        );
    }

    private static function opaque_id( string $value, int $max_length ): string {
        $value = trim( $value );
        if ( '' === $value || strlen( $value ) > $max_length ) {
            return '';
        }
        return 1 === preg_match( '/^[A-Za-z0-9._:-]+$/', $value ) ? $value : '';
    }

    private static function text( string $value, int $max_length ): string {
        $value = strip_tags( $value );
        $value = preg_replace( '/[\x00-\x1F\x7F]+/u', ' ', $value ) ?? '';
        $value = trim( preg_replace( '/\s+/u', ' ', $value ) ?? '' );
        if ( '' === $value ) {
            return 'Map';
        }
        return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $max_length ) : substr( $value, 0, $max_length );
    }

    private function __construct() {}
}
