<?php

namespace MVM\Hub\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Durable boundary for semantic V5 domain events.
 *
 * Event payloads must be metadata-only and may never contain protected source,
 * mail, chat or private attachment bodies. Delivery is at-least-once; consumers
 * must therefore be idempotent by event id/correlation id.
 */
interface Domain_Event_Outbox {
    /** @return array<string,mixed>|\WP_Error */
    public function enqueue( array $event ): array|\WP_Error;

    /** @return list<array<string,mixed>> */
    public function pending( int $limit = 50 ): array;

    public function mark_delivered( int $id, string $consumer, string $delivered_at_utc ): bool|\WP_Error;

    public function mark_failed( int $id, string $consumer, string $reason_code, string $retry_at_utc ): bool|\WP_Error;
}
