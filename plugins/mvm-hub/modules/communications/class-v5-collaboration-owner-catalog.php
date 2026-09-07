<?php

namespace MVM\Hub\Modules\Communications;

use MVM\Hub\Core\Data_Classification;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Dormant owner inventory for staff collaboration during V5 coexistence.
 *
 * Current Newsroom Staff_Collaboration remains authoritative. This catalog
 * grants no capability and does not register storage, hooks, routes or writes.
 */
final class V5_Collaboration_Owner_Catalog {
    /** @return array<string,array<string,mixed>> */
    public static function all(): array {
        return array(
            'staff_board' => array(
                'source_key'             => 'staff_board',
                'domain'                 => 'communications',
                'current_owner'          => 'current-newsroom-staff-collaboration',
                'owner_resolution'       => 'resolved',
                'implementation'          => 'MVM\\Hub\\Modules\\Newsroom\\Staff_Collaboration',
                'storage_type'            => 'mvm_staff_notice',
                'read_capability'         => 'mvm_staff_board_view',
                'write_capability'        => 'mvm_staff_board_post',
                'moderate_capability'     => 'mvm_staff_board_moderate',
                'classification'          => Data_Classification::INTERNAL,
                'search_visibility'       => 'none',
                'write_promotion_enabled' => false,
                'max_items'               => 50,
            ),
            'team_chat' => array(
                'source_key'             => 'team_chat',
                'domain'                 => 'communications',
                'current_owner'          => 'current-newsroom-staff-collaboration',
                'owner_resolution'       => 'resolved',
                'implementation'          => 'MVM\\Hub\\Modules\\Newsroom\\Staff_Collaboration',
                'storage_type'            => 'mvm_staff_chat',
                'read_capability'         => 'mvm_team_chat_access',
                'write_capability'        => 'mvm_team_chat_send',
                'moderate_capability'     => 'mvm_team_chat_moderate',
                'classification'          => Data_Classification::INTERNAL,
                'search_visibility'       => 'none',
                'write_promotion_enabled' => false,
                'max_items'               => 60,
                'retention_days'          => 30,
                'max_message_length'      => 1500,
            ),
        );
    }

    /** @return array<string,mixed>|null */
    public static function get( string $source_key ): ?array {
        $source_key = sanitize_key( $source_key );
        $all        = self::all();
        return $all[ $source_key ] ?? null;
    }

    public static function can_promote_writes( string $source_key ): bool {
        $record = self::get( $source_key );
        return is_array( $record ) && true === ( $record['write_promotion_enabled'] ?? false );
    }

    private function __construct() {}
}
