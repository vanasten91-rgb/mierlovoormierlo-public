<?php

namespace MVM\Hub\Modules\Communications;

use MVM\Hub\Core\Capabilities;
use MVM\Hub\Core\Communications_Policy;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Central delivery authorization gate for the future send/reply endpoint.
 *
 * This does not deliver mail. It makes the required checks explicit before a
 * transport implementation may ever be called.
 */
final class Mail_Delivery_Guard {
    public static function authorize( string $idempotency_key ): true|\WP_Error {
        if ( ! is_user_logged_in() ) {
            return new \WP_Error( 'mvm_mail_auth_required', 'Inloggen is vereist.' );
        }

        if ( ! Capabilities::can_send_mail() ) {
            return new \WP_Error( 'mvm_mail_send_forbidden', 'Je hebt geen toestemming om e-mail te verzenden.' );
        }

        if ( ! Communications_Policy::external_mail_delivery_enabled() ) {
            return new \WP_Error( 'mvm_mail_delivery_disabled', 'E-mailverzending is nog niet vrijgegeven voor deze release.' );
        }

        if ( ! self::valid_idempotency_key( $idempotency_key ) ) {
            return new \WP_Error( 'mvm_mail_idempotency_required', 'Een geldige unieke verzendsleutel is vereist.' );
        }

        return true;
    }

    public static function valid_idempotency_key( string $key ): bool {
        $key = trim( $key );
        $length = strlen( $key );
        return $length >= 32
            && $length <= 128
            && 1 === preg_match( '/^[A-Za-z0-9._:-]+$/', $key );
    }

    /**
     * The transport implementation must enforce these requirements as well.
     * Kept machine-readable so tests/UI can expose why delivery is unavailable.
     *
     * @return array<string,bool|int>
     */
    public static function requirements(): array {
        return array(
            'authenticated'         => true,
            'mailSendCapability'    => true,
            'objectAccess'          => true,
            'validSessionOrNonce'   => true,
            'validatedRecipients'   => true,
            'sanitizedBody'         => true,
            'privateAttachments'    => true,
            'attachmentPolicy'      => true,
            'idempotencyKey'        => true,
            'rateLimit'             => true,
            'auditEvent'            => true,
            'transportEnabled'      => Communications_Policy::external_mail_delivery_enabled(),
        );
    }

    private function __construct() {}
}
