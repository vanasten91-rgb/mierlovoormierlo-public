<?php

namespace MVM\Hub\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Explicit runtime feature gates for parallel migration.
 *
 * Nothing is enabled by plugin activation alone. Staging/development require an
 * explicit enable constant; production additionally requires a second, separate
 * acknowledgement so non-production configuration cannot silently bleed live.
 *
 * Route ownership, Newsroom writes, role reconciliation and the final Hub4 cron
 * handoff may additionally be enabled by the signed, release-scoped
 * Release_Control record. Communications writes remain available for internal
 * staff messaging. MvM Mail remains hard disabled on the canonical production
 * host; the exact product-like staging host can opt into a signed Mail release
 * for controlled E2E rehearsal even when WP_ENVIRONMENT_TYPE is misconfigured.
 */
final class Runtime_Gates {
    public static function shell_preview_enabled(): bool {
        return self::environment_gate(
            'MVM_HUB_ENABLE_SHELL_PREVIEW',
            'MVM_HUB_ALLOW_PRODUCTION_SHELL_PREVIEW'
        );
    }

    /** Allows the central Hub to own /hub/* as a standalone private runtime. */
    public static function route_takeover_enabled(): bool {
        return self::environment_gate(
            'MVM_HUB_ENABLE_ROUTE_TAKEOVER',
            'MVM_HUB_ALLOW_PRODUCTION_ROUTE_TAKEOVER'
        ) || Release_Control::flag_enabled( 'route_takeover' );
    }

    /** Enables Newsroom mutations. Independent from route ownership. */
    public static function newsroom_writes_enabled(): bool {
        return self::environment_gate(
            'MVM_HUB_ENABLE_NEWSROOM_WRITES',
            'MVM_HUB_ALLOW_PRODUCTION_NEWSROOM_WRITES'
        ) || Release_Control::flag_enabled( 'newsroom_writes' );
    }

    /** Enables reconciliation of the explicit MvM capability bundles to roles. */
    public static function capability_reconciliation_enabled(): bool {
        return self::environment_gate(
            'MVM_HUB_ENABLE_CAPABILITY_RECONCILIATION',
            'MVM_HUB_ALLOW_PRODUCTION_CAPABILITY_RECONCILIATION'
        ) || Release_Control::flag_enabled( 'capability_reconciliation' );
    }

    /** Enables ownership of recurring Hub jobs. */
    public static function cron_takeover_enabled(): bool {
        return self::environment_gate(
            'MVM_HUB_ENABLE_CRON_TAKEOVER',
            'MVM_HUB_ALLOW_PRODUCTION_CRON_TAKEOVER'
        ) || (
            Release_Control::flag_enabled( 'cron_takeover' )
            && class_exists( Legacy_Services::class )
            && Legacy_Services::persistent_ready()
        );
    }

    /** Enables non-mail Communications mutations such as internal staff messaging. */
    public static function communications_writes_enabled(): bool {
        return self::environment_gate(
            'MVM_HUB_ENABLE_COMMUNICATION_WRITES',
            'MVM_HUB_ALLOW_PRODUCTION_COMMUNICATION_WRITES'
        ) || Release_Control::flag_enabled( 'communications_writes' );
    }

    /**
     * Enables Mail writes only for a controlled staging rehearsal.
     *
     * The canonical production host remains hard read-only in code. Only the
     * exact product-like staging host can open this gate, irrespective of the
     * copied WP_ENVIRONMENT_TYPE value. Staging must still opt in with
     * MVM_HUB_ENABLE_STAGING_MAIL_WRITES=true and must also hold a valid, signed
     * Release_Control record whose cumulative scope is `mail`. No single config
     * value or stale release record can open delivery.
     */
    public static function mail_writes_enabled(): bool {
        if ( ! self::verified_mail_staging_host() ) {
            return false;
        }

        if (
            ! defined( 'MVM_HUB_ENABLE_STAGING_MAIL_WRITES' )
            || true !== constant( 'MVM_HUB_ENABLE_STAGING_MAIL_WRITES' )
        ) {
            return false;
        }

        return Release_Control::flag_enabled( 'mail_writes' );
    }

    /** Exact host pin for the product-like Mail rehearsal clone. */
    private static function verified_mail_staging_host(): bool {
        if ( ! function_exists( 'home_url' ) || ! function_exists( 'wp_parse_url' ) ) {
            return false;
        }

        $host = strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) );

        return 'staging.mierlovoormierlo.nl' === $host;
    }

    private static function environment_gate( string $enable_constant, string $production_constant ): bool {
        if ( ! defined( $enable_constant ) || true !== constant( $enable_constant ) ) {
            return false;
        }

        $environment = function_exists( 'wp_get_environment_type' )
            ? wp_get_environment_type()
            : 'production';

        if ( 'production' !== $environment ) {
            return true;
        }

        return defined( $production_constant ) && true === constant( $production_constant );
    }

    private function __construct() {}
}
