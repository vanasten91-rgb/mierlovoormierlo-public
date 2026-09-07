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
        $value = preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', (string) $value ) ?? '';
        $value = strip_tags( $value );
        return $remove_breaks ? preg_replace( '/[\r\n\t ]+/', ' ', $value ) ?? '' : $value;
    }
}

require_once __DIR__ . '/../plugins/mvm-hub/core/class-data-classification.php';
require_once __DIR__ . '/../plugins/mvm-hub/modules/communications/class-v5-collaboration-owner-catalog.php';
require_once __DIR__ . '/../plugins/mvm-hub/modules/communications/class-v5-collaboration-read-adapter.php';
require_once __DIR__ . '/../plugins/mvm-hub/modules/communications/class-v5-communications-preview-model.php';

use MVM\Hub\Modules\Communications\V5_Communications_Preview_Model;

function mvm_v5_comms_preview_assert( bool $condition, string $message ): void {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
}

$model = V5_Communications_Preview_Model::build(
    array(
        array(
            'id' => 31,
            'title' => 'Belangrijk bericht',
            'message' => 'Controleer planning',
            'authorId' => 4,
            'authorName' => 'Teamleider',
            'createdUtc' => '2026-09-04T09:00:00Z',
            'pinned' => true,
            'priority' => 'important',
        ),
        array(
            'id' => 32,
            'title' => 'Gewoon bericht',
            'message' => 'Ter informatie',
            'authorId' => 5,
            'authorName' => 'Redactie',
            'createdUtc' => '2026-09-04T09:05:00Z',
            'pinned' => false,
            'priority' => 'normal',
        ),
    ),
    array(
        array(
            'id' => 41,
            'message' => '<script>bad()</script>Goedemorgen team',
            'authorId' => 6,
            'authorName' => 'Journalist',
            'createdUtc' => '2026-09-04T09:10:00Z',
            'mine' => false,
        ),
    )
);

mvm_v5_comms_preview_assert( 'communications' === $model['workspace'], 'preview must identify communications workspace' );
mvm_v5_comms_preview_assert( 'coexistence_read_only' === $model['ownerMode'], 'preview must stay coexistence read-only' );
mvm_v5_comms_preview_assert( 2 === $model['board']['total'], 'board preview must project both safe items' );
mvm_v5_comms_preview_assert( 1 === $model['board']['pinned'], 'board preview must count pinned items' );
mvm_v5_comms_preview_assert( 1 === $model['board']['important'], 'board preview must count important items' );
mvm_v5_comms_preview_assert( false === $model['board']['canWrite'], 'V5 board writes must remain disabled' );
mvm_v5_comms_preview_assert( 1 === $model['teamChat']['total'], 'chat preview must project safe item' );
mvm_v5_comms_preview_assert( 'Goedemorgen team' === $model['teamChat']['items'][0]['message'], 'chat preview must remove script blocks' );
mvm_v5_comms_preview_assert( 30 === $model['teamChat']['retentionDays'], 'chat preview must expose 30-day retention' );
mvm_v5_comms_preview_assert( false === $model['teamChat']['canSend'], 'V5 chat send must remain disabled' );
mvm_v5_comms_preview_assert( 'read_only' === $model['mail']['currentProductionMode'], 'Mail production mode must remain read-only' );
mvm_v5_comms_preview_assert( false === $model['mail']['v2PromotionEnabled'], 'Mail V2 promotion must remain disabled' );
mvm_v5_comms_preview_assert( false === $model['mail']['receiveSyncPromoted'], 'Mail receive sync must remain unpromoted' );
mvm_v5_comms_preview_assert( false === $model['mail']['sendRoutePromoted'], 'no mail send route may be promoted' );
mvm_v5_comms_preview_assert( 'bidirectional' === $model['mail']['targetMode'], 'Mail V2 target mode must remain bidirectional' );
mvm_v5_comms_preview_assert( true === $model['mail']['receiveRequired'], 'Mail V2 receive capability must remain required' );
mvm_v5_comms_preview_assert( true === $model['mail']['sendRequired'], 'Mail V2 send capability must remain required' );
mvm_v5_comms_preview_assert( 'none' === $model['security']['searchVisibility'], 'collaboration preview must remain outside general search' );
mvm_v5_comms_preview_assert( 'none' === $model['security']['aiVisibility'], 'collaboration preview must remain outside general AI context' );
mvm_v5_comms_preview_assert( false === $model['security']['auditBodyAllowed'], 'collaboration bodies must remain outside generic audit payloads' );

echo "PASS: MvM Hub V5 Communications preview is read-only and keeps Mail, search, AI and audit boundaries closed\n";
