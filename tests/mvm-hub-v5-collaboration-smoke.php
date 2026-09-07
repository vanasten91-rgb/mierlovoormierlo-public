<?php

declare(strict_types=1);

define( 'ABSPATH', __DIR__ . '/' );

if ( ! function_exists( 'sanitize_key' ) ) {
    function sanitize_key( mixed $value ): string {
        $value = strtolower( (string) $value );
        return (string) preg_replace( '/[^a-z0-9_\-]/', '', $value );
    }
}
if ( ! function_exists( 'wp_strip_all_tags' ) ) {
    function wp_strip_all_tags( mixed $value, bool $remove_breaks = false ): string {
        $value = strip_tags( (string) $value );
        return $remove_breaks ? preg_replace( '/[\r\n\t ]+/', ' ', $value ) ?? '' : $value;
    }
}

require_once __DIR__ . '/../plugins/mvm-hub/core/class-data-classification.php';
require_once __DIR__ . '/../plugins/mvm-hub/core/class-v5-search-document-policy.php';
require_once __DIR__ . '/../plugins/mvm-hub/modules/communications/class-v5-collaboration-owner-catalog.php';
require_once __DIR__ . '/../plugins/mvm-hub/modules/communications/class-v5-collaboration-read-adapter.php';

use MVM\Hub\Core\Data_Classification;
use MVM\Hub\Core\V5_Search_Document_Policy;
use MVM\Hub\Modules\Communications\V5_Collaboration_Owner_Catalog;
use MVM\Hub\Modules\Communications\V5_Collaboration_Read_Adapter;

function mvm_v5_collab_assert( bool $condition, string $message ): void {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
}

$board_owner = V5_Collaboration_Owner_Catalog::get( 'staff_board' );
$chat_owner  = V5_Collaboration_Owner_Catalog::get( 'team_chat' );

mvm_v5_collab_assert( is_array( $board_owner ), 'staff board owner must resolve' );
mvm_v5_collab_assert( is_array( $chat_owner ), 'team chat owner must resolve' );
mvm_v5_collab_assert( 'current-newsroom-staff-collaboration' === $board_owner['current_owner'], 'staff board owner must remain current Newsroom collaboration' );
mvm_v5_collab_assert( 'current-newsroom-staff-collaboration' === $chat_owner['current_owner'], 'team chat owner must remain current Newsroom collaboration' );
mvm_v5_collab_assert( 'mvm_staff_board_view' === $board_owner['read_capability'], 'staff board read capability must remain explicit' );
mvm_v5_collab_assert( 'mvm_team_chat_access' === $chat_owner['read_capability'], 'team chat read capability must remain explicit' );
mvm_v5_collab_assert( false === $board_owner['write_promotion_enabled'], 'staff board V5 write promotion must remain off' );
mvm_v5_collab_assert( false === $chat_owner['write_promotion_enabled'], 'team chat V5 write promotion must remain off' );
mvm_v5_collab_assert( 30 === $chat_owner['retention_days'], 'team chat retention parity must stay 30 days' );
mvm_v5_collab_assert( 1500 === $chat_owner['max_message_length'], 'team chat message parity must stay 1500 chars' );

$board = V5_Collaboration_Read_Adapter::board(
    array(
        array(
            'id' => 11,
            'title' => '<b>Redactievergadering</b>',
            'message' => "Afspraak\nvoor vrijdag",
            'authorId' => 7,
            'authorName' => 'Redactie',
            'createdUtc' => '2026-09-04T08:00:00Z',
            'pinned' => true,
            'priority' => 'important',
            'canDelete' => true,
            'raw_private_meta' => 'must not pass through',
        ),
    )
);

mvm_v5_collab_assert( 1 === count( $board ), 'valid board item must project' );
mvm_v5_collab_assert( 'Redactievergadering' === $board[0]['title'], 'board title must be stripped of markup' );
mvm_v5_collab_assert( 'none' === $board[0]['search_visibility'], 'staff board must opt out of shared search' );
mvm_v5_collab_assert( 'none' === $board[0]['ai_visibility'], 'staff board must opt out of general AI context' );
mvm_v5_collab_assert( false === $board[0]['audit_body_allowed'], 'staff board body must not enter generic audit' );
mvm_v5_collab_assert( true === $board[0]['read_only_projection'], 'staff board V5 projection must be read-only' );
mvm_v5_collab_assert( ! array_key_exists( 'canDelete', $board[0] ), 'current runtime mutation affordance must not pass into dormant V5 read model' );
mvm_v5_collab_assert( ! array_key_exists( 'raw_private_meta', $board[0] ), 'arbitrary private metadata must not pass through' );

$chat = V5_Collaboration_Read_Adapter::chat(
    array(
        array(
            'id' => 21,
            'message' => '<script>alert(1)</script>Start overleg',
            'authorId' => 8,
            'authorName' => 'Team',
            'createdUtc' => '2026-09-04T08:01:00Z',
            'mine' => false,
            'canDelete' => true,
        ),
    )
);

mvm_v5_collab_assert( 1 === count( $chat ), 'valid chat item must project' );
mvm_v5_collab_assert( 'alert(1)Start overleg' === $chat[0]['message'], 'chat projection must strip markup' );
mvm_v5_collab_assert( 30 === $chat[0]['retentionDays'], 'chat projection must preserve retention metadata' );
mvm_v5_collab_assert( 'none' === $chat[0]['search_visibility'], 'team chat must opt out of shared search' );
mvm_v5_collab_assert( false === $chat[0]['audit_body_allowed'], 'team chat body must not enter generic audit' );
mvm_v5_collab_assert( ! array_key_exists( 'canDelete', $chat[0] ), 'chat mutation affordance must not pass into dormant read model' );

$search_decision = V5_Search_Document_Policy::decision(
    array(
        'classification' => Data_Classification::INTERNAL,
        'search_visibility' => $chat[0]['search_visibility'],
    ),
    array( 'scope' => V5_Search_Document_Policy::SCOPE_STAFF )
);
mvm_v5_collab_assert( false === $search_decision['allowed'], 'team chat must remain absent from shared staff search' );
mvm_v5_collab_assert( 'search_visibility_denied' === $search_decision['reason'], 'collaboration search denial must be explicit' );

mvm_v5_collab_assert( array() === V5_Collaboration_Read_Adapter::board( array( array( 'id' => 0 ) ) ), 'invalid board item must fail closed' );
mvm_v5_collab_assert( array() === V5_Collaboration_Read_Adapter::chat( array( array( 'id' => 0 ) ) ), 'invalid chat item must fail closed' );

echo "PASS: MvM Hub V5 collaboration projections preserve current ownership and stay out of search, AI and generic audit\n";
