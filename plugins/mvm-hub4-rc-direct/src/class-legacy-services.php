<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Transitional owner for the remaining Hub 3.8.2 services.
 *
 * Hub 1.1 prefers a persistent, integrity-checked payload under wp-content so
 * normal Hub plugin upgrades cannot remove it. Older embedded and inactive
 * plugin copies remain read-only fallback sources during the migration window.
 */
final class MvM_Hub4_Legacy_Services {
    public const LEGACY_PLUGIN_BASENAME = 'mierlo-voor-mierlo-hub/mierlo-voor-mierlo-hub.php';
    public const LEGACY_VERSION         = '3.8.2';
    public const EXPECTED_FILE_COUNT    = 400;
    public const EXPECTED_TREE_SHA256   = '70673d5bdbe9c107103adb5eeccd4b55754b766729a0f21630b3ab6bb346fc39';

    private static string $state = 'not_bootstrapped';
    private static string $source = '';
    private static string $root = '';

    public static function bootstrap(): void {
        if ( defined( 'MVM_HUB4_DISABLE_LEGACY_SERVICES' ) && MVM_HUB4_DISABLE_LEGACY_SERVICES ) {
            self::$state = 'disabled';
            return;
        }

        if ( defined( 'MVM_SUITE_VERSION' ) || class_exists( 'MVM_Suite', false ) ) {
            self::$state  = 'external_loaded';
            self::$source = 'hub3-active';
            return;
        }

        $active_plugins = (array) get_option( 'active_plugins', array() );
        if ( in_array( self::LEGACY_PLUGIN_BASENAME, $active_plugins, true ) ) {
            self::$state  = 'external_active';
            self::$source = 'hub3-active';
            return;
        }

        foreach ( self::candidate_roots() as $source => $root ) {
            if ( ! self::root_is_valid( $root ) ) {
                continue;
            }

            $bootstrap = trailingslashit( $root ) . 'mierlo-voor-mierlo-hub.php';
            require_once $bootstrap;

            if ( ! defined( 'MVM_SUITE_VERSION' ) || self::LEGACY_VERSION !== (string) MVM_SUITE_VERSION ) {
                self::$state  = 'load_failed';
                self::$source = $source;
                self::$root   = $root;
                self::register_admin_notice();
                return;
            }

            self::$state  = 'adopted';
            self::$source = $source;
            self::$root   = $root;

            if ( ! defined( 'MVM_HUB4_LEGACY_SERVICES_ADOPTED' ) ) {
                define( 'MVM_HUB4_LEGACY_SERVICES_ADOPTED', true );
            }

            return;
        }

        self::$state = 'payload_missing_or_changed';
        self::register_admin_notice();
    }

    /**
     * Runtime status for release smoke tests and system diagnostics.
     *
     * @return array{state:string,source:string,root:string,legacyVersion:string,expectedTreeSha256:string}
     */
    public static function status(): array {
        return array(
            'state'                 => self::$state,
            'source'                => self::$source,
            'root'                  => self::$root,
            'legacyVersion'         => self::LEGACY_VERSION,
            'expectedTreeSha256'    => self::EXPECTED_TREE_SHA256,
        );
    }

    /**
     * @return array<string,string>
     */
    private static function candidate_roots(): array {
        return array(
            'persistent'      => trailingslashit( WP_CONTENT_DIR ) . 'mvm-hub4-legacy/hub3',
            'embedded'        => trailingslashit( MVM_HUB4_DIR ) . 'legacy/hub3',
            'legacy-fallback' => trailingslashit( WP_PLUGIN_DIR ) . 'mierlo-voor-mierlo-hub',
        );
    }

    private static function root_is_valid( string $root ): bool {
        if ( ! is_dir( $root ) ) {
            return false;
        }

        foreach ( self::critical_hashes() as $relative => $expected_hash ) {
            $path = trailingslashit( $root ) . $relative;
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

    /**
     * Current production hashes after the approved Hub3 reCAPTCHA hotfix.
     * These are deliberately limited to the critical loaders and primary
     * service modules so normal requests do not hash the full payload.
     * The full 400-file tree digest is checked during migration/preflight.
     *
     * @return array<string,string>
     */
    private static function critical_hashes(): array {
        return array(
            'mierlo-voor-mierlo-hub.php'                                      => '00c00c29eaded52683e65bf7fc00dce0c0a09dd9e2aee9a9ea03bf015b48db3b',
            'includes/class-mvm-suite.php'                                    => 'aa823a58f977e6c771a1fe04fbe974fa2c0bc0914c537b4b6e7d5834830f315f',
            'modules/hub/mvm-beheerhub.php'                                   => '6681ad1475bb7d1aee680fcc9fd83dfd33b77030cf7bbcfb3b45318efd943eb1',
            'modules/redactie/mvm-redactiehub.php'                            => '7b629f56fc92a9c435dfffd40cb874652fb6e730bdc965db36cf3e5e18a3e6ea',
            'modules/encyclopedie/mierlo-encyclopedie.php'                    => '7c0d8a25a23c182a0e2eeb197aded493765f4cf0d065112d6061969e51a06a20',
            'modules/encyclopedie/includes/class-mvm-encyclopedie-v2940.php'  => 'e702454fd9906f04c31a047183c7feed20b6df16a8901524434bc5264f31da7f',
            'modules/event-bridge/mierlo-event-migrator-bridge.php'            => '98fc932df1bcb55f95a2f9d4cdc053b35cd1b63b1d3fbe23b4d8f50bbc322a15',
            'modules/forum/mvm-forum.php'                                      => 'b17de2ea928b7fd6f02538b58519d14045bdb68298721de40fe1f730bf122637',
            'modules/mail/mvm-mail.php'                                        => '7a7ed2a196ded3cbd848ba2dbe8856d259bf90658ea4082cabbd1283316456b5',
            'modules/mierlo-vandaag/mvm-mierlo-vandaag.php'                    => 'e7785f043c012eb86ecf7a51316731ecc2cb0c7d927b2e190f90b64beff1d385',
            'modules/site-integrations/mvm-site-integrations.php'              => 'b7defdc234295a1713c3348b30bee91e90f7a67b43df7eb044057f76a015c10a',
            'modules/staff-docs/mvm-staff-docs.php'                            => '457f3130525ae670efc99fc65af247eca0b542238eddfddb227c7aa9a3f29767',
            'modules/runtime-patches/mvm-runtime-patches.php'                  => 'e7a7e405a96e4e6df799bfa32edde58991f2fe656689b150c6e9f4e1d0eb2cb4',
            'modules/runtime-patches/auth/010-258.php'                         => 'ed68345c6d2309bcfae43575a3295887a372aa4bcdf00d8611efa2c093f1aab6',
            'modules/runtime-patches/auth/010-288.php'                         => '994e69c61c7d8f63e301b9d7724028c3100a057238556f706020a7c41b6b2713',
        );
    }

    private static function register_admin_notice(): void {
        add_action(
            'admin_notices',
            static function (): void {
                if ( ! current_user_can( 'manage_options' ) ) {
                    return;
                }

                echo '<div class="notice notice-error"><p>'
                    . esc_html__( 'MvM Hub 4 kon de gemigreerde legacy-services niet veilig laden. Hub3 niet verwijderen; gebruik het rollbackpad.', 'mvm-hub4' )
                    . '</p></div>';
            }
        );
    }
}
