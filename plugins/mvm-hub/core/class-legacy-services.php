<?php

namespace MVM\Hub\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Temporary owner for the remaining Hub 3.8.2 platform services while Hub4 is
 * decommissioned. Only an integrity-checked payload outside the Hub4 plugin is
 * accepted, so removing Hub4 cannot silently remove a runtime dependency.
 */
final class Legacy_Services {
    public const LEGACY_PLUGIN_BASENAME = 'mierlo-voor-mierlo-hub/mierlo-voor-mierlo-hub.php';
    public const LEGACY_VERSION = '3.8.2';
    public const EXPECTED_TREE_SHA256 = '70673d5bdbe9c107103adb5eeccd4b55754b766729a0f21630b3ab6bb346fc39';

    private static string $state = 'not_bootstrapped';
    private static string $source = '';
    private static string $root = '';

    public static function bootstrap(): void {
        if ( function_exists( 'add_action' ) ) {
            add_action( 'rest_api_init', array( self::class, 'register_status_route' ) );
        }

        if ( defined( 'MVM_SUITE_VERSION' ) || class_exists( 'MVM_Suite', false ) ) {
            self::$state = 'external_loaded';
            self::$source = 'already-loaded';
            return;
        }

        // Minimal test/bootstrap contexts deliberately do not define WordPress
        // content/plugin paths. Fail closed there instead of assuming production.
        if ( ! defined( 'WP_CONTENT_DIR' ) || ! defined( 'WP_PLUGIN_DIR' ) ) {
            self::$state = 'wordpress_paths_unavailable';
            return;
        }

        $active_plugins = function_exists( 'get_option' ) ? (array) get_option( 'active_plugins', array() ) : array();
        if ( in_array( self::LEGACY_PLUGIN_BASENAME, $active_plugins, true ) ) {
            self::$state = 'external_active';
            self::$source = 'legacy-plugin';
            return;
        }

        foreach ( self::candidate_roots() as $source => $root ) {
            if ( ! self::root_is_valid( $root ) ) {
                continue;
            }

            $bootstrap = self::slash( $root ) . 'mierlo-voor-mierlo-hub.php';
            require_once $bootstrap;

            if ( ! defined( 'MVM_SUITE_VERSION' ) || self::LEGACY_VERSION !== (string) MVM_SUITE_VERSION ) {
                self::$state = 'load_failed';
                self::$source = $source;
                self::$root = $root;
                return;
            }

            self::$state = 'adopted';
            self::$source = $source;
            self::$root = $root;
            if ( ! defined( 'MVM_HUB_LEGACY_SERVICES_ADOPTED' ) ) {
                define( 'MVM_HUB_LEGACY_SERVICES_ADOPTED', true );
            }
            // Compatibility signal for the adopted Hub 3 payload during the
            // Hub4 retirement window. No Hub4 code is required by this flag.
            if ( ! defined( 'MVM_HUB4_LEGACY_SERVICES_ADOPTED' ) ) {
                define( 'MVM_HUB4_LEGACY_SERVICES_ADOPTED', true );
            }
            return;
        }

        self::$state = 'persistent_payload_missing_or_changed';
    }

    public static function persistent_ready(): bool {
        if ( ! defined( 'WP_CONTENT_DIR' ) ) {
            return false;
        }
        return self::root_is_valid( self::persistent_root() );
    }

    /** @return array<string,mixed> */
    public static function status(): array {
        return array(
            'state' => self::$state,
            'source' => self::$source,
            'root' => self::$root,
            'legacyVersion' => self::LEGACY_VERSION,
            'expectedTreeSha256' => self::EXPECTED_TREE_SHA256,
            'persistentReady' => self::persistent_ready(),
            'hub4PluginRequired' => false,
        );
    }

    public static function register_status_route(): void {
        if ( ! function_exists( 'register_rest_route' ) || ! class_exists( '\\WP_REST_Server' ) ) {
            return;
        }
        register_rest_route( 'mvm-hub/v1', '/technical/legacy-services-status', array(
            'methods' => \WP_REST_Server::READABLE,
            'callback' => static fn(): \WP_REST_Response => rest_ensure_response( self::status() ),
            'permission_callback' => static fn(): bool => is_user_logged_in() && ( current_user_can( 'manage_options' ) || Capabilities::can_access_technical() ),
        ) );
    }

    /** @return array<string,string> */
    private static function candidate_roots(): array {
        if ( ! defined( 'WP_CONTENT_DIR' ) || ! defined( 'WP_PLUGIN_DIR' ) ) {
            return array();
        }
        return array(
            'persistent' => self::persistent_root(),
            'legacy-fallback' => self::slash( (string) WP_PLUGIN_DIR ) . 'mierlo-voor-mierlo-hub',
        );
    }

    private static function persistent_root(): string {
        return defined( 'WP_CONTENT_DIR' )
            ? self::slash( (string) WP_CONTENT_DIR ) . 'mvm-hub4-legacy/hub3'
            : '';
    }

    private static function root_is_valid( string $root ): bool {
        if ( '' === $root || ! is_dir( $root ) ) {
            return false;
        }
        foreach ( self::critical_hashes() as $relative => $expected_hash ) {
            $path = self::slash( $root ) . $relative;
            if ( ! is_readable( $path ) ) {
                return false;
            }
            $actual_hash = hash_file( 'sha256', $path );
            if ( ! is_string( $actual_hash ) || ! hash_equals( $expected_hash, $actual_hash ) ) {
                return false;
            }
        }
        return true;
    }

    private static function slash( string $path ): string {
        return rtrim( $path, "/\\" ) . '/';
    }

    /** @return array<string,string> */
    private static function critical_hashes(): array {
        return array(
            'mierlo-voor-mierlo-hub.php' => '00c00c29eaded52683e65bf7fc00dce0c0a09dd9e2aee9a9ea03bf015b48db3b',
            'includes/class-mvm-suite.php' => 'aa823a58f977e6c771a1fe04fbe974fa2c0bc0914c537b4b6e7d5834830f315f',
            'modules/hub/mvm-beheerhub.php' => '6681ad1475bb7d1aee680fcc9fd83dfd33b77030cf7bbcfb3b45318efd943eb1',
            'modules/redactie/mvm-redactiehub.php' => '7b629f56fc92a9c435dfffd40cb874652fb6e730bdc965db36cf3e5e18a3e6ea',
            'modules/encyclopedie/mierlo-encyclopedie.php' => '7c0d8a25a23c182a0e2eeb197aded493765f4cf0d065112d6061969e51a06a20',
            'modules/encyclopedie/includes/class-mvm-encyclopedie-v2940.php' => 'e702454fd9906f04c31a047183c7feed20b6df16a8901524434bc5264f31da7f',
            'modules/event-bridge/mierlo-event-migrator-bridge.php' => '98fc932df1bcb55f95a2f9d4cdc053b35cd1b63b1d3fbe23b4d8f50bbc322a15',
            'modules/forum/mvm-forum.php' => 'b17de2ea928b7fd6f02538b58519d14045bdb68298721de40fe1f730bf122637',
            'modules/mail/mvm-mail.php' => '7a7ed2a196ded3cbd848ba2dbe8856d259bf90658ea4082cabbd1283316456b5',
            'modules/mierlo-vandaag/mvm-mierlo-vandaag.php' => 'e7785f043c012eb86ecf7a51316731ecc2cb0c7d927b2e190f90b64beff1d385',
            'modules/site-integrations/mvm-site-integrations.php' => 'b7defdc234295a1713c3348b30bee91e90f7a67b43df7eb044057f76a015c10a',
            'modules/staff-docs/mvm-staff-docs.php' => '457f3130525ae670efc99fc65af247eca0b542238eddfddb227c7aa9a3f29767',
            'modules/runtime-patches/mvm-runtime-patches.php' => 'e7a7e405a96e4e6df799bfa32edde58991f2fe656689b150c6e9f4e1d0eb2cb4',
            'modules/runtime-patches/auth/010-258.php' => 'ed68345c6d2309bcfae43575a3295887a372aa4bcdf00d8611efa2c093f1aab6',
            'modules/runtime-patches/auth/010-288.php' => '994e69c61c7d8f63e301b9d7724028c3100a057238556f706020a7c41b6b2713',
        );
    }

    private function __construct() {}
}
