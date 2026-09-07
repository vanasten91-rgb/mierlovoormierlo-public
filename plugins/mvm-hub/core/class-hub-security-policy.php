<?php

namespace MVM\Hub\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Security/privacy defaults for the future central /hub/ shell.
 *
 * This class is intentionally inert during parallel migration: it registers no
 * hooks and sends no headers until the central Hub takes route ownership.
 */
final class Hub_Security_Policy {
    public const IDLE_TIMEOUT_SECONDS       = 30 * MINUTE_IN_SECONDS;
    public const STEP_UP_WINDOW_SECONDS     = 15 * MINUTE_IN_SECONDS;
    public const MAX_SESSION_AGE_SECONDS    = 12 * HOUR_IN_SECONDS;

    /** @return array<string,string> */
    public static function response_headers(): array {
        return array(
            'Cache-Control'       => 'private, no-store, max-age=0, must-revalidate',
            'Pragma'              => 'no-cache',
            'X-Robots-Tag'        => 'noindex, nofollow, noarchive, nosnippet',
            'Referrer-Policy'     => 'same-origin',
            'X-Content-Type-Options' => 'nosniff',
            'Permissions-Policy'  => 'camera=(), microphone=(), geolocation=(), payment=()',
            'Content-Security-Policy' => "default-src 'self'; object-src 'none'; base-uri 'self'; frame-ancestors 'self'; form-action 'self'; img-src 'self' data: blob:; connect-src 'self'",
        );
    }

    public static function requires_step_up( string $action ): bool {
        return in_array(
            sanitize_key( $action ),
            array(
                'mail_send',
                'mail_folder_delete',
                'mail_attachment_download',
                'source_credentials_change',
                'capability_change',
                'security_setting_change',
                'integration_secret_change',
            ),
            true
        );
    }

    /** @return array<string,int> */
    public static function session_limits(): array {
        return array(
            'idleTimeoutSeconds'   => self::IDLE_TIMEOUT_SECONDS,
            'stepUpWindowSeconds'  => self::STEP_UP_WINDOW_SECONDS,
            'maxSessionAgeSeconds' => self::MAX_SESSION_AGE_SECONDS,
        );
    }

    private function __construct() {}
}
