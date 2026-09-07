<?php

namespace MVM\Hub\Modules\Community;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Dormant ownership inventory for public community moderation.
 *
 * PeepSo remains canonical for social activity; wpForo remains canonical for
 * forum content. V5 may coordinate moderation work but must not become a second
 * storage owner or bypass either plugin's object permissions.
 */
final class V5_Community_Owner_Catalog {
    /** @return array<string,array<string,mixed>> */
    public static function all(): array {
        return array(
            'peepso_activity' => array(
                'canonical_owner' => 'peepso',
                'object_type' => 'peepso_activity',
                'classification' => 'internal',
                'v5_read_owner' => false,
                'v5_write_owner' => false,
                'requires_object_authorization' => true,
            ),
            'wpforo_content' => array(
                'canonical_owner' => 'wpforo',
                'object_type' => 'wpforo_content',
                'classification' => 'internal',
                'v5_read_owner' => false,
                'v5_write_owner' => false,
                'requires_object_authorization' => true,
            ),
        );
    }

    /** @return array<string,mixed>|null */
    public static function get( string $key ): ?array {
        $key = sanitize_key( $key );
        $all = self::all();
        return $all[ $key ] ?? null;
    }

    private function __construct() {}
}
