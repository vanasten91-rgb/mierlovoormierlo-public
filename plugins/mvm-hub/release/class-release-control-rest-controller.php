<?php

namespace MVM\Hub\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Admin-only release control for the exact installed Hub4 decommission RC.
 *
 * This release may enable recurring Hub cron ownership only when the persistent
 * legacy service payload has independently passed its integrity checks. A
 * rollback owner must remain provably available through promotion: either the
 * Hub4 plugin itself, or the integrity-checked persistent Hub 3.8.2 payload
 * already adopted by MvM Hub. Communications writes and external mail delivery
 * can never be enabled by this controller.
 */
final class Release_Control_REST_Controller {
    private const NAMESPACE = 'mvm-hub/v1';
    private const HUB4_PLUGIN = 'mvm-hub4-rc-direct/mvm-hub4.php';

    public function register_routes(): void {
        register_rest_route(
            self::NAMESPACE,
            '/release/status',
            array(
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => array( $this, 'status' ),
                'permission_callback' => array( $this, 'can_release' ),
            )
        );
        register_rest_route(
            self::NAMESPACE,
            '/release/promote',
            array(
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'promote' ),
                'permission_callback' => array( $this, 'can_release' ),
                'args'                => array(
                    'releaseId' => array( 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ),
                    'artifactSha256' => array( 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ),
                    'innerSha256' => array( 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ),
                    'backupConfirmed' => array( 'required' => true, 'sanitize_callback' => 'rest_sanitize_boolean' ),
                ),
            )
        );
        register_rest_route(
            self::NAMESPACE,
            '/release/newsroom2-promote',
            array(
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'newsroom2_promote' ),
                'permission_callback' => array( $this, 'can_release' ),
                'args'                => array(
                    'releaseId' => array( 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ),
                    'artifactSha256' => array( 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ),
                    'innerSha256' => array( 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ),
                    'backupConfirmed' => array( 'required' => true, 'sanitize_callback' => 'rest_sanitize_boolean' ),
                ),
            )
        );
        register_rest_route(
            self::NAMESPACE,
            '/release/rollback',
            array(
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'rollback' ),
                'permission_callback' => array( $this, 'can_release' ),
                'args'                => array(
                    'releaseId' => array( 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ),
                ),
            )
        );
    }

    public function can_release(): bool {
        return is_user_logged_in() && current_user_can( 'manage_options' ) && current_user_can( 'activate_plugins' );
    }

    public function status(): \WP_REST_Response {
        return rest_ensure_response( $this->snapshot() );
    }

    public function promote( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
        if ( Release_Control::release_id() !== (string) $request->get_param( 'releaseId' ) ) {
            return new \WP_Error( 'mvm_release_id', 'Release-ID komt niet overeen met deze build.', array( 'status' => 409 ) );
        }
        if ( true !== rest_sanitize_boolean( $request->get_param( 'backupConfirmed' ) ) ) {
            return new \WP_Error( 'mvm_release_backup', 'Een bevestigde herstelbare backup is verplicht.', array( 'status' => 409 ) );
        }
        $preflight = $this->preflight();
        if ( is_wp_error( $preflight ) ) {
            Audit::record( 'release.promote', 'denied', 'hub_release', 0, array( 'reason' => $preflight->get_error_code() ) );
            return $preflight;
        }

        $saved = Release_Control::set_mode(
            'live',
            get_current_user_id(),
            (string) $request->get_param( 'artifactSha256' ),
            (string) $request->get_param( 'innerSha256' )
        );
        if ( is_wp_error( $saved ) ) {
            Audit::record( 'release.promote', 'error', 'hub_release', 0, array( 'reason' => $saved->get_error_code() ) );
            return $saved;
        }

        Role_Capability_Bundles::reconcile_if_enabled();
        $roles = Role_Capability_Bundles::status();
        foreach ( (array) ( $roles['roles'] ?? array() ) as $role_status ) {
            if ( ! empty( $role_status['exists'] ) && empty( $role_status['matches'] ) ) {
                Release_Control::set_mode( 'rollback', get_current_user_id() );
                Audit::record( 'release.promote', 'error', 'hub_release', 0, array( 'reason' => 'capability_reconciliation_failed' ) );
                return new \WP_Error( 'mvm_release_caps', 'Capability-reconciliatie is niet volledig groen; release is teruggezet naar rollback.', array( 'status' => 500 ) );
            }
        }

        if ( ! Cron_Cutover::activate() ) {
            Release_Control::set_mode( 'rollback', get_current_user_id() );
            Audit::record( 'release.promote', 'error', 'hub_release', 0, array( 'reason' => 'cron_cutover_failed' ) );
            return new \WP_Error( 'mvm_release_cron', 'Cronownership kon niet veilig worden overgenomen; release is teruggezet naar rollback.', array( 'status' => 500 ) );
        }

        $cron = Cron_Ownership::status();
        $newsradar_ready = ! empty( $cron['takeoverEnabled'] ) && ! empty( $cron['newsradar']['providerReady'] );
        $ai_ready = empty( $cron['aiAgenda']['enabled'] ) || ! empty( $cron['aiAgenda']['providerReady'] );
        if ( ! $newsradar_ready || ! $ai_ready || Runtime_Gates::communications_writes_enabled() || Runtime_Gates::mail_writes_enabled() ) {
            Release_Control::set_mode( 'rollback', get_current_user_id() );
            Audit::record( 'release.promote', 'error', 'hub_release', 0, array( 'reason' => 'post_promote_safety_check_failed' ) );
            return new \WP_Error( 'mvm_release_postcheck', 'De post-promote veiligheidscontrole is niet volledig groen; release is teruggezet naar rollback.', array( 'status' => 500 ) );
        }

        Audit::record(
            'release.promote',
            'success',
            'hub_release',
            0,
            array(
                'release_id' => Release_Control::release_id(),
                'hub4_active' => $this->hub4_active(),
                'rollback_owner' => $this->rollback_owner_status(),
                'cron_takeover' => true,
                'persistent_legacy_payload' => true,
                'communications_writes' => false,
                'mail_writes' => false,
            )
        );
        return rest_ensure_response( $this->snapshot() );
    }

    /**
     * Newsroom 2.0-only promotion. Route ownership, Newsroom writes and role
     * reconciliation are opened for the exact verified artifact; recurring
     * cron, Communications writes and mail delivery stay fail-closed.
     */
    public function newsroom2_promote( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
        if ( Release_Control::release_id() !== (string) $request->get_param( 'releaseId' ) ) {
            return new \WP_Error( 'mvm_release_id', 'Release-ID komt niet overeen met deze build.', array( 'status' => 409 ) );
        }
        if ( true !== rest_sanitize_boolean( $request->get_param( 'backupConfirmed' ) ) ) {
            return new \WP_Error( 'mvm_release_backup', 'Een bevestigde herstelbare backup is verplicht.', array( 'status' => 409 ) );
        }

        $preflight = $this->preflight();
        if ( is_wp_error( $preflight ) ) {
            Audit::record( 'release.newsroom2_promote', 'denied', 'hub_release', 0, array( 'reason' => $preflight->get_error_code() ) );
            return $preflight;
        }

        $saved = Release_Control::set_mode(
            'live',
            get_current_user_id(),
            (string) $request->get_param( 'artifactSha256' ),
            (string) $request->get_param( 'innerSha256' ),
            'newsroom2'
        );
        if ( is_wp_error( $saved ) ) {
            Audit::record( 'release.newsroom2_promote', 'error', 'hub_release', 0, array( 'reason' => $saved->get_error_code() ) );
            return $saved;
        }

        Role_Capability_Bundles::reconcile_if_enabled();
        $roles = Role_Capability_Bundles::status();
        foreach ( (array) ( $roles['roles'] ?? array() ) as $role_status ) {
            if ( ! empty( $role_status['exists'] ) && empty( $role_status['matches'] ) ) {
                Release_Control::set_mode( 'rollback', get_current_user_id() );
                Audit::record( 'release.newsroom2_promote', 'error', 'hub_release', 0, array( 'reason' => 'capability_reconciliation_failed' ) );
                return new \WP_Error( 'mvm_release_caps', 'Capability-reconciliatie is niet volledig groen; release is teruggezet naar rollback.', array( 'status' => 500 ) );
            }
        }

        if (
            ! Runtime_Gates::route_takeover_enabled()
            || ! Runtime_Gates::newsroom_writes_enabled()
            || ! Runtime_Gates::capability_reconciliation_enabled()
            || Runtime_Gates::cron_takeover_enabled()
            || Runtime_Gates::communications_writes_enabled()
            || Runtime_Gates::mail_writes_enabled()
            || ! Staff_Login_Recaptcha::dependencies_ready()
        ) {
            Release_Control::set_mode( 'rollback', get_current_user_id() );
            Audit::record( 'release.newsroom2_promote', 'error', 'hub_release', 0, array( 'reason' => 'newsroom2_postcheck_failed' ) );
            return new \WP_Error( 'mvm_release_newsroom2_postcheck', 'De Newsroom 2.0 post-promote veiligheidscontrole is niet volledig groen; release is teruggezet naar rollback.', array( 'status' => 500 ) );
        }

        Audit::record(
            'release.newsroom2_promote',
            'success',
            'hub_release',
            0,
            array(
                'release_id' => Release_Control::release_id(),
                'scope' => 'newsroom2',
                'hub4_active' => $this->hub4_active(),
                'rollback_owner' => $this->rollback_owner_status(),
                'route_takeover' => true,
                'newsroom_writes' => true,
                'capability_reconciliation' => true,
                'cron_takeover' => false,
                'communications_writes' => false,
                'mail_writes' => false,
            )
        );
        return rest_ensure_response( $this->snapshot() );
    }

    public function rollback( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
        if ( Release_Control::release_id() !== (string) $request->get_param( 'releaseId' ) ) {
            return new \WP_Error( 'mvm_release_id', 'Release-ID komt niet overeen met deze build.', array( 'status' => 409 ) );
        }
        $saved = Release_Control::set_mode( 'rollback', get_current_user_id() );
        if ( is_wp_error( $saved ) ) {
            return $saved;
        }
        Audit::record(
            'release.rollback',
            'success',
            'hub_release',
            0,
            array(
                'release_id' => Release_Control::release_id(),
                'hub4_active' => $this->hub4_active(),
                'rollback_owner' => $this->rollback_owner_status(),
            )
        );
        return rest_ensure_response( $this->snapshot() );
    }

    /**
     * Pure fail-closed rollback-owner evaluator used by CI and the live
     * preflight. Hub4 remains a valid rollback owner. Once Hub4 is retired, the
     * replacement is accepted only when the already-loaded legacy service
     * runtime was adopted specifically from the integrity-checked persistent
     * payload and explicitly reports that the Hub4 plugin is no longer needed.
     *
     * @param array<string,mixed> $legacy_status
     */
    public static function rollback_owner_status_for( bool $hub4_active, array $legacy_status ): string {
        if ( $hub4_active ) {
            return 'hub4';
        }

        if (
            'adopted' !== (string) ( $legacy_status['state'] ?? '' )
            || 'persistent' !== (string) ( $legacy_status['source'] ?? '' )
            || empty( $legacy_status['persistentReady'] )
            || (bool) ( $legacy_status['hub4PluginRequired'] ?? true )
        ) {
            return 'unavailable';
        }

        return 'persistent_legacy_services';
    }

    /** @return array<string,mixed>|\WP_Error */
    private function preflight(): array|\WP_Error {
        $rollback_owner = $this->rollback_owner_status();
        if ( 'unavailable' === $rollback_owner ) {
            return new \WP_Error( 'mvm_release_rollback_owner', 'Geen aantoonbaar herstelbare rollback-eigenaar is beschikbaar.', array( 'status' => 409 ) );
        }
        if ( Runtime_Gates::cron_takeover_enabled() ) {
            return new \WP_Error( 'mvm_release_cron_already_live', 'Cronownership staat al open; een schone gecontroleerde handoff is vereist.', array( 'status' => 409 ) );
        }
        if ( Runtime_Gates::communications_writes_enabled() || Runtime_Gates::mail_writes_enabled() ) {
            return new \WP_Error( 'mvm_release_unsafe_gates', 'Communications- of mailwrites staan al open; release afgebroken.', array( 'status' => 409 ) );
        }
        if ( ! Legacy_Services::persistent_ready() ) {
            return new \WP_Error( 'mvm_release_legacy_payload', 'De persistente Hub 3.8.2 servicepayload ontbreekt of wijkt af; Hub4 mag niet worden gedecommissioned.', array( 'status' => 409 ) );
        }
        foreach ( array( 'MVM\\Hub\\Modules\\Newsroom\\Newsroom_Runtime_Renderer', 'MVM\\Hub\\Modules\\Communications\\Communications_Runtime_Renderer', 'MVM\\Hub\\Core\\Hub_Runtime', 'MVM\\Hub\\Core\\Legacy_Services', 'MVM\\Hub\\Core\\Cron_Cutover', 'MVM\Hub\Core\Staff_Login_Recaptcha' ) as $class ) {
            if ( ! class_exists( $class ) ) {
                return new \WP_Error( 'mvm_release_class', 'Een vereiste Hub-runtimeklasse ontbreekt.', array( 'status' => 500 ) );
            }
        }
        if ( ! Staff_Login_Recaptcha::dependencies_ready() ) {
            return new \WP_Error( 'mvm_release_staff_gateway', 'De beveiligde stafgateway mist een vereiste legacy-authenticatiedependency.', array( 'status' => 409, 'dependencies' => Staff_Login_Recaptcha::dependency_status() ) );
        }
        $counts = $this->counts();
        if ( null === $counts['sources'] || $counts['sources'] < 238 || null === $counts['assignments'] || $counts['assignments'] < 3 || null === $counts['dossiers'] || $counts['dossiers'] < 1 || null === $counts['signals'] || $counts['signals'] < 1 ) {
            return new \WP_Error( 'mvm_release_data', 'Productie-inventaris is kleiner dan de gevalideerde baseline.', array( 'status' => 409 ) );
        }
        return array( 'counts' => $counts, 'legacyServices' => Legacy_Services::status(), 'rollbackOwner' => $rollback_owner );
    }

    /** @return array<string,mixed> */
    private function snapshot(): array {
        return array(
            'release' => Release_Control::status(),
            'gates' => array(
                'routeTakeover' => Runtime_Gates::route_takeover_enabled(),
                'newsroomWrites' => Runtime_Gates::newsroom_writes_enabled(),
                'capabilityReconciliation' => Runtime_Gates::capability_reconciliation_enabled(),
                'cronTakeover' => Runtime_Gates::cron_takeover_enabled(),
                'communicationsWrites' => Runtime_Gates::communications_writes_enabled(),
                'mailWrites' => Runtime_Gates::mail_writes_enabled(),
            ),
            'hub4Active' => $this->hub4_active(),
            'rollbackOwner' => $this->rollback_owner_status(),
            'legacyServices' => Legacy_Services::status(),
            'cron' => Cron_Ownership::status(),
            'counts' => $this->counts(),
            'capabilities' => Role_Capability_Bundles::status(),
        );
    }

    /** @return array{sources:?int,assignments:?int,dossiers:?int,signals:?int,audit:?int} */
    private function counts(): array {
        global $wpdb;
        $out = array();
        foreach ( array( 'sources' => 'sources', 'assignments' => 'assignments', 'dossiers' => 'dossiers', 'signals' => 'signals', 'audit' => 'audit' ) as $label => $suffix ) {
            $table = $wpdb->prefix . 'mvm_hub4_' . $suffix;
            $exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
            $out[ $label ] = $table === $exists ? (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ) : null; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        }
        return $out;
    }

    private function rollback_owner_status(): string {
        return self::rollback_owner_status_for( $this->hub4_active(), Legacy_Services::status() );
    }

    private function hub4_active(): bool {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        return is_plugin_active( self::HUB4_PLUGIN );
    }
}
