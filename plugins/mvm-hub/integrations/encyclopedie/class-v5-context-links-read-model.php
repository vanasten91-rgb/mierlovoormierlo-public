<?php

namespace MVM\Hub\Integrations\Encyclopedie;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Dormant V5 read model for News <-> Encyclopedie ContextLinks.
 *
 * It consumes already-authorized Smart Links context and normalizes only bounded
 * metadata for preview/review UI. It never writes links, mutates posts or claims
 * ownership of the existing Encyclopedie Smart Links implementation.
 */
final class V5_Context_Links_Read_Model {
    private const MAX_LINKS = 20;

    /**
     * @param array<string,mixed> $context
     * @return array<string,mixed>
     */
    public static function build( int $news_post_id, array $context, int $limit = 12 ): array {
        $news_post_id = max( 0, $news_post_id );
        $limit        = max( 1, min( self::MAX_LINKS, $limit ) );

        if ( 0 === $news_post_id || true !== ( $context['available'] ?? false ) ) {
            return array(
                'newsPostId' => $news_post_id,
                'available'  => false,
                'links'      => array(),
                'conflicts'  => 0,
                'source'     => sanitize_key( (string) ( $context['source'] ?? 'unavailable' ) ),
                'readOnly'   => true,
            );
        }

        $links = array();
        $seen  = array();
        $items = is_array( $context['suggestions'] ?? null ) ? $context['suggestions'] : array();

        foreach ( $items as $item ) {
            if ( ! is_array( $item ) ) {
                continue;
            }

            $target_id = max( 0, (int) ( $item['targetId'] ?? $item['target_id'] ?? 0 ) );
            $label     = sanitize_text_field( (string) ( $item['label'] ?? '' ) );
            $title     = sanitize_text_field( (string) ( $item['targetTitle'] ?? $item['target_title'] ?? '' ) );
            $type      = sanitize_key( (string) ( $item['targetType'] ?? $item['target_type'] ?? '' ) );
            $url       = esc_url_raw( (string) ( $item['targetUrl'] ?? $item['target_url'] ?? '' ) );

            if ( $target_id <= 0 || '' === $url ) {
                continue;
            }

            $dedupe_key = $target_id . '|' . strtolower( $label );
            if ( isset( $seen[ $dedupe_key ] ) ) {
                continue;
            }
            $seen[ $dedupe_key ] = true;

            $links[] = array(
                'targetId'    => $target_id,
                'targetType'  => $type,
                'targetTitle' => $title,
                'label'       => $label,
                'targetUrl'   => $url,
                'relation'    => 'context_link_candidate',
                'origin'      => sanitize_key( (string) ( $context['source'] ?? 'smart-links' ) ),
                'reviewState' => 'proposed',
            );

            if ( count( $links ) >= $limit ) {
                break;
            }
        }

        return array(
            'newsPostId' => $news_post_id,
            'available'  => true,
            'links'      => $links,
            'conflicts'  => max( 0, (int) ( $context['conflicts'] ?? 0 ) ),
            'source'     => sanitize_key( (string) ( $context['source'] ?? 'smart-links' ) ),
            'readOnly'   => true,
            'owner'      => 'existing-smart-links-runtime',
        );
    }

    private function __construct() {}
}
