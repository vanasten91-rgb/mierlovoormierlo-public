<?php

namespace MVM\Hub\Modules\Communications;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Normalizes receive-sync batches without persisting or transporting mail.
 *
 * The sync plane deliberately carries only identifiers/state required to
 * invalidate/update mailbox projections. Message bodies, subjects, addresses,
 * filenames and credentials are excluded from this generic contract.
 */
final class V5_Mail_Sync_Contract {
    private const MAX_BATCH         = 200;
    private const MAX_CURSOR_LENGTH = 512;

    /**
     * @param array<string,mixed> $batch
     * @return array<string,mixed>
     */
    public static function normalize_batch( array $batch ): array {
        $cursor = self::cursor( (string) ( $batch['cursor'] ?? '' ) );
        $upserts = array();
        foreach ( array_slice( (array) ( $batch['upserts'] ?? array() ), 0, self::MAX_BATCH ) as $candidate ) {
            if ( ! is_array( $candidate ) ) {
                continue;
            }

            $ref = V5_Mail_Message_Reference::normalize(
                (string) ( $candidate['mailboxId'] ?? '' ),
                (string) ( $candidate['folderId'] ?? '' ),
                (string) ( $candidate['messageId'] ?? '' )
            );
            if ( null === $ref ) {
                continue;
            }

            $upserts[] = array(
                'reference' => $ref,
                'revision'  => self::opaque_revision( (string) ( $candidate['revision'] ?? '' ) ),
                'seen'      => true === ( $candidate['seen'] ?? false ),
                'flagged'   => true === ( $candidate['flagged'] ?? false ),
                'size'      => max( 0, min( 100000000, (int) ( $candidate['size'] ?? 0 ) ) ),
            );
        }

        $deleted = array();
        foreach ( array_slice( (array) ( $batch['deleted'] ?? $batch['deletedIds'] ?? array() ), 0, self::MAX_BATCH ) as $candidate ) {
            if ( ! is_array( $candidate ) ) {
                continue;
            }
            $ref = V5_Mail_Message_Reference::normalize(
                (string) ( $candidate['mailboxId'] ?? '' ),
                (string) ( $candidate['folderId'] ?? '' ),
                (string) ( $candidate['messageId'] ?? '' )
            );
            if ( null !== $ref ) {
                $deleted[] = $ref;
            }
        }

        return array(
            'cursor'           => $cursor,
            'upserts'          => $upserts,
            'deleted'          => $deleted,
            'hasMore'          => true === ( $batch['hasMore'] ?? false ),
            'resetRequired'    => true === ( $batch['resetRequired'] ?? false ),
            'searchVisibility' => 'none',
            'aiVisibility'     => 'none',
            'auditBodyAllowed' => false,
        );
    }

    public static function valid_cursor( string $cursor ): bool {
        return '' !== self::cursor( $cursor );
    }

    private static function cursor( string $cursor ): string {
        $cursor = trim( $cursor );
        if ( '' === $cursor || strlen( $cursor ) > self::MAX_CURSOR_LENGTH ) {
            return '';
        }
        return 1 === preg_match( '/^[A-Za-z0-9._:+=\/-]+$/', $cursor ) ? $cursor : '';
    }

    private static function opaque_revision( string $revision ): string {
        $revision = trim( $revision );
        if ( '' === $revision || strlen( $revision ) > 128 ) {
            return '';
        }
        return 1 === preg_match( '/^[A-Za-z0-9._:-]+$/', $revision ) ? $revision : '';
    }

    private function __construct() {}
}
