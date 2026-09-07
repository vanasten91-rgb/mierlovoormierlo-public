<?php

namespace MVM\Hub\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Cron-only continuation of an already-live Newsroom 2.0 release.
 *
 * This handoff upgrades the signed release scope from newsroom2 to full so the
 * rebuilt Hub becomes owner of recurring jobs. Communications and Mail remain
 * outside signed release control. Any failed cron postcheck restores the same
 * installed artifact to the newsroom2 scope instead of disabling Newsroom 2.0.
 */
final class Cron_Release {
    private const HUB4_PLUGIN = 'mvm-hub4-rc-direct/mvm-hub4.php';

    /** @param array<string,mixed> $input
     *  @return array<string,mixed>|\WP_Error
     */
    public static function promote( array $input ): array|\WP_Error {
        $release_id = sanitize_text_field( (string) ( $input['releaseId'] ?? '' ) );
        $artifact   = self::sha256( $input['artifactSha256'] ?? '' );
        $inner      = self::sha256( $input['innerSha256'] ?? '' );
        $backup     = rest_sanitize_boolean( $input['backupConfirmed'] ?? false );

        if ( Release_Control::release_id() !== $release_id ) {
            return new \WP_Error( 'mvm_cron_release_id', 'Release-ID komt niet overeen met deze build.', array( 'status' => 409 ) );
        }
        if ( true !== $backup ) {
            return new \WP_Error( 'mvm_cron_release_backup', 'Een bevestigde herstelbare backup is verplicht.', array( 'status' => 409 ) );
        }
        if ( '' === $artifact || '' === $inner ) {
            return new \WP_Error( 'mvm_cron_release_hashes', 'De geverifieerde artifact- en install-ZIP hashes zijn verplicht.', array( 'status' => 400 ) );
        }

        $preflight = self::preflight();
        if ( is_wp_error( $preflight ) ) {
            Audit::record( 'release.cron_promote', 'denied', 'hub_release', 0, array( 'reason' => $preflight->get_error_code() ) );
            return $preflight;
        }
        $rollback_owner = (string) ( $preflight['rollbackOwner'] ?? 'unavailable' );

        $saved = Release_Control::set_mode( 'live', get_current_user_id(), $artifact, $inner, 'full' );
        if ( is_wp_error( $saved ) ) {
            Audit::record( 'release.cron_promote', 'error', 'hub_release', 0, array( 'reason' => $saved->get_error_code() ) );
            return $saved;
        }

        if ( ! Cron_Cutover::activate() ) {
            self::restore_newsroom2( $artifact, $inner, 'cron_cutover_failed' );
            return new \WP_Error( 'mvm_cron_release_cutover', 'Cronownership kon niet veilig worden overgenomen; Newsroom 2.0 is behouden.', array( 'status' => 500 ) );
        }

        $cron = Cron_Ownership::status();
        $roles = Role_Capability_Bundles::status();

        $newsradar_ok = (
            ! empty( $cron['takeoverEnabled'] )
            && ! empty( $cron['newsradar']['providerReady'] )
            && ! empty( $cron['newsradar']['nextRun'] )
        );
        $ai_schedule_ok = (
            empty( $cron['aiAgenda']['enabled'] )
            || ! empty( $cron['aiAgenda']['nextRun'] )
        );
        $audit_ok = ! empty( $cron['audit']['nextRun'] );
        $roles_ok = self::roles_match( $roles );

        $gates_ok = (
            Runtime_Gates::route_takeover_enabled()
            && Runtime_Gates::newsroom_writes_enabled()
            && Runtime_Gates::capability_reconciliation_enabled()
            && Runtime_Gates::cron_takeover_enabled()
            && ! Runtime_Gates::communications_writes_enabled()
            && ! Runtime_Gates::mail_writes_enabled()
        );

        if (
            ! $newsradar_ok
            || ! $ai_schedule_ok
            || ! $audit_ok
            || ! $roles_ok
            || ! $gates_ok
            || ! Staff_Login_Recaptcha::dependencies_ready()
            || ! Legacy_Services::persistent_ready()
        ) {
            self::restore_newsroom2( $artifact, $inner, 'cron_postcheck_failed' );
            return new \WP_Error(
                'mvm_cron_release_postcheck',
                'De cron-handoff postcheck is niet volledig groen; Newsroom 2.0 is behouden en cron takeover is teruggedraaid.',
                array( 'status' => 500, 'cron' => $cron )
            );
        }

        Audit::record(
            'release.cron_promote',
            'success',
            'hub_release',
            0,
            array(
                'release_id'               => Release_Control::release_id(),
                'scope'                    => 'full',
                'hub4_active'              => 'hub4' === $rollback_owner,
                'rollback_owner'           => $rollback_owner,
                'cron_takeover'            => true,
                'newsradar_next_run'       => (string) ( $cron['newsradar']['nextRun'] ?? '' ),
                'ai_agenda_enabled'        => ! empty( $cron['aiAgenda']['enabled'] ),
                'ai_agenda_provider_ready' => ! empty( $cron['aiAgenda']['providerReady'] ),
                'ai_agenda_next_run'       => (string) ( $cron['aiAgenda']['nextRun'] ?? '' ),
                'audit_next_run'           => (string) ( $cron['audit']['nextRun'] ?? '' ),
                'communications_writes'    => false,
                'mail_writes'              => false,
            )
        );

        return self::snapshot();
    }

    /** @return array<string,mixed>|\WP_Error */
    private static function preflight(): array|\WP_Error {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';

        $release = Release_Control::status();
        if (
            empty( $release['valid'] )
            || 'live' !== (string) ( $release['mode'] ?? '' )
            || 'newsroom2' !== (string) ( $release['scope'] ?? '' )
        ) {
            return new \WP_Error( 'mvm_cron_release_scope', 'Cronhandoff vereist een geldige live Newsroom 2.0-release.', array( 'status' => 409 ) );
        }

        $legacy_status = Legacy_Services::status();
        $rollback_owner = Release_Control_REST_Controller::rollback_owner_status_for(
            is_plugin_active( self::HUB4_PLUGIN ),
            $legacy_status
        );
        if ( 'unavailable' === $rollback_owner ) {
            return new \WP_Error( 'mvm_cron_release_rollback_owner', 'Geen aantoonbaar herstelbare rollback-eigenaar is beschikbaar voor de cronhandoff.', array( 'status' => 409 ) );
        }
        if (
            ! Runtime_Gates::route_takeover_enabled()
            || ! Runtime_Gates::newsroom_writes_enabled()
            || ! Runtime_Gates::capability_reconciliation_enabled()
            || Runtime_Gates::cron_takeover_enabled()
            || Runtime_Gates::communications_writes_enabled()
            || Runtime_Gates::mail_writes_enabled()
        ) {
            return new \WP_Error( 'mvm_cron_release_gates', 'De bestaande Newsroom 2.0 gate-state wijkt af; cronhandoff afgebroken.', array( 'status' => 409 ) );
        }
        if ( ! Legacy_Services::persistent_ready() ) {
            return new \WP_Error( 'mvm_cron_release_legacy', 'De persistente legacy-servicepayload is niet integer.', array( 'status' => 409 ) );
        }
        if ( ! Staff_Login_Recaptcha::dependencies_ready() ) {
            return new \WP_Error( 'mvm_cron_release_gateway', 'De beveiligde stafgateway is niet volledig beschikbaar.', array( 'status' => 409 ) );
        }

        $roles = Role_Capability_Bundles::status();
        if ( ! self::roles_match( $roles ) ) {
            return new \WP_Error( 'mvm_cron_release_caps', 'Het actuele capabilityschema is niet volledig groen.', array( 'status' => 409 ) );
        }

        $counts = self::counts();
        if (
            null === $counts['sources'] || $counts['sources'] < 238
            || null === $counts['assignments'] || $counts['assignments'] < 3
            || null === $counts['dossiers'] || $counts['dossiers'] < 1
            || null === $counts['signals'] || $counts['signals'] < 1
        ) {
            return new \WP_Error( 'mvm_cron_release_data', 'Productie-inventaris is kleiner dan de gevalideerde baseline.', array( 'status' => 409 ) );
        }

        return array(
            'release' => $release,
            'counts' => $counts,
            'rollbackOwner' => $rollback_owner,
        );
    }

    private static function restore_newsroom2( string $artifact, string $inner, string $reason ): void {
        $restored = Release_Control::set_mode( 'live', get_current_user_id(), $artifact, $inner, 'newsroom2' );
        Audit::record(
            'release.cron_promote',
            is_wp_error( $restored ) ? 'error' : 'rollback',
            'hub_release',
            0,
            array(
                'reason'                => $reason,
                'restored_scope'        => is_wp_error( $restored ) ? 'unknown' : 'newsroom2',
                'communications_writes' => false,
                'mail_writes'           => false,
            )
        );
    }

    /** @param array<string,mixed> $roles */
    private static function roles_match( array $roles ): bool {
        foreach ( (array) ( $roles['roles'] ?? array() ) as $role_status ) {
            if ( ! empty( $role_status['exists'] ) && empty( $role_status['matches'] ) ) {
                return false;
            }
        }
        $schema_version = (int) ( $roles['schemaVersion'] ?? 0 );
        $stored_version = (int) ( $roles['storedVersion'] ?? 0 );

        return 0 < $schema_version && $schema_version === $stored_version;
    }

    /** @return array{sources:?int,assignments:?int,dossiers:?int,signals:?int,audit:?int} */
    private static function counts(): array {
        global $wpdb;
        $out = array();
        foreach ( array( 'sources' => 'sources', 'assignments' => 'assignments', 'dossiers' => 'dossiers', 'signals' => 'signals', 'audit' => 'audit' ) as $label => $suffix ) {
            $table = $wpdb->prefix . 'mvm_hub4_' . $suffix;
            $exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
            $out[ $label ] = $table === $exists ? (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ) : null; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        }
        return $out;
    }

    /** @return array<string,mixed> */
    private static function snapshot(): array {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        $hub4_active = is_plugin_active( self::HUB4_PLUGIN );
        $legacy_status = Legacy_Services::status();
        return array(
            'ok' => true,
            'release' => Release_Control::status(),
            'gates' => array(
                'routeTakeover' => Runtime_Gates::route_takeover_enabled(),
                'newsroomWrites' => Runtime_Gates::newsroom_writes_enabled(),
                'capabilityReconciliation' => Runtime_Gates::capability_reconciliation_enabled(),
                'cronTakeover' => Runtime_Gates::cron_takeover_enabled(),
                'communicationsWrites' => Runtime_Gates::communications_writes_enabled(),
                'mailWrites' => Runtime_Gates::mail_writes_enabled(),
            ),
            'cron' => Cron_Ownership::status(),
            'hub4Active' => $hub4_active,
            'rollbackOwner' => Release_Control_REST_Controller::rollback_owner_status_for( $hub4_active, $legacy_status ),
            'legacyServices' => $legacy_status,
            'capabilities' => Role_Capability_Bundles::status(),
            'counts' => self::counts(),
        );
    }

    private static function sha256( mixed $value ): string {
        $hash = strtolower( trim( (string) $value ) );
        return 64 === strlen( $hash ) && ctype_xdigit( $hash ) ? $hash : '';
    }

    private function __construct() {}
}
