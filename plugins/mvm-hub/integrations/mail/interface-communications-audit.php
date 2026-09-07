<?php

namespace MVM\Hub\Integrations\Mail;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Confidential audit boundary for mail/message mutations.
 * Callers must pass metadata-only context; implementations must apply the
 * central privacy redaction again before persistence.
 */
interface Communications_Audit {
    /** @param array<string,mixed> $context */
    public function record( string $event, string $result, int $user_id, string $object_ref = '', array $context = array() ): void;
}
