<?php

defined( 'ABSPATH' ) || exit;

/**
 * Canonical Smart Links asset ownership for the consolidated Hub.
 *
 * The legacy Hub UI still contains a rollback loader that points at the theme
 * copy. Registering the shared handle here at priority 1 makes the plugin copy
 * authoritative: later wp_enqueue_script() calls for the same handle enqueue
 * this registered source and cannot replace it with the rollback source.
 *
 * A second late hook guarantees that the canonical plugin asset and its runtime
 * config remain available after the retained theme fallback is eventually
 * removed. While the old loader still exists, duplicate localization is avoided
 * by detecting its already-attached data first.
 */
final class MvM_Hub4_Smart_Links_Assets {
    public static function boot(): void {
        add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register' ), 1 );
        add_action( 'wp_enqueue_scripts', array( __CLASS__, 'ensure_hub_asset' ), 2501 );
    }

    public static function register(): void {
        $asset = MVM_HUB4_DIR . 'assets/mvm-smart-links-hub.js';
        if ( ! is_readable( $asset ) ) {
            return;
        }

        wp_register_script(
            'mvm-smart-links-v3',
            MVM_HUB4_URL . 'assets/mvm-smart-links-hub.js',
            array( 'mvm-hubs-v3' ),
            (string) filemtime( $asset ),
            true
        );
    }

    public static function ensure_hub_asset(): void {
        if (
            ! function_exists( 'mvm_hubs_v3_current_hub' )
            || ! function_exists( 'mvm_hubs_v3_can_access' )
            || ! function_exists( 'mvm_hubs_v3_is_draft_preview' )
        ) {
            return;
        }

        $deel = isset( $_GET['deel'] ) ? sanitize_key( wp_unslash( (string) $_GET['deel'] ) ) : '';
        if ( 'admin' !== mvm_hubs_v3_current_hub() || 'integraties' !== $deel || ! mvm_hubs_v3_can_access( 'admin' ) ) {
            return;
        }
        if ( ! wp_script_is( 'mvm-smart-links-v3', 'registered' ) ) {
            return;
        }

        wp_enqueue_script( 'mvm-smart-links-v3' );

        $scripts       = wp_scripts();
        $existing_data = is_object( $scripts ) ? (string) $scripts->get_data( 'mvm-smart-links-v3', 'data' ) : '';
        if ( false !== strpos( $existing_data, 'MvMHubs3SmartLinksConfig' ) ) {
            return;
        }

        wp_localize_script(
            'mvm-smart-links-v3',
            'MvMHubs3SmartLinksConfig',
            array(
                'restRoot'  => esc_url_raw( rest_url( 'mvm/v1/admin/' ) ),
                'restNonce' => wp_create_nonce( 'wp_rest' ),
                'readOnly'  => mvm_hubs_v3_is_draft_preview(),
            )
        );
    }
}
