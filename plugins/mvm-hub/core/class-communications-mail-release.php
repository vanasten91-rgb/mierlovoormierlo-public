<?php

namespace MVM\Hub\Core;

use MVM\Hub\Integrations\Mail\Private_Storage_Root;
use MVM\Hub\Integrations\PeepSo\PeepSo8_Internal_Message_Provider;
use MVM\Hub\Modules\Communications\Communications_Service_Factory;

if ( ! defined( 'ABSPATH' ) ) exit;

/** Cumulative production handoff: full -> communications -> mail. */
final class Communications_Mail_Release {
    /** @param array<string,mixed> $input */
    public static function promote_communications( array $input ): array|\WP_Error {
        $v = self::input( $input );
        if ( is_wp_error( $v ) ) return $v;
        $pre = self::preflight( 'full', false );
        if ( is_wp_error( $pre ) ) return $pre;

        $saved = Release_Control::set_mode( 'live', get_current_user_id(), $v['artifact'], $v['inner'], 'communications' );
        if ( is_wp_error( $saved ) ) return $saved;
        $health = self::health();
        if ( ! self::core_ok() || ! Runtime_Gates::communications_writes_enabled() || Runtime_Gates::mail_writes_enabled() || empty( $health['privateStorageReady'] ) || empty( $health['mailReadReady'] ) || empty( $health['internalMessagesReady'] ) ) {
            self::restore( 'full', $v, 'communications_postcheck_failed' );
            return new \WP_Error( 'mvm_communications_release_postcheck', 'Communications kon niet veilig worden vrijgegeven; de bestaande Hub-release is behouden.', array( 'status' => 500, 'health' => $health ) );
        }
        Audit::record( 'release.communications_promote', 'success', 'hub_release', 0, array( 'scope' => 'communications', 'mail_writes' => false ) );
        return self::snapshot();
    }

    /** @param array<string,mixed> $input */
    public static function promote_mail( array $input ): array|\WP_Error {
        $v = self::input( $input );
        if ( is_wp_error( $v ) ) return $v;
        $pre = self::preflight( 'communications', true );
        if ( is_wp_error( $pre ) ) return $pre;

        $saved = Release_Control::set_mode( 'live', get_current_user_id(), $v['artifact'], $v['inner'], 'mail' );
        if ( is_wp_error( $saved ) ) return $saved;
        $health = self::health();
        if ( ! self::core_ok() || ! Runtime_Gates::communications_writes_enabled() || ! Runtime_Gates::mail_writes_enabled() || empty( $health['privateStorageReady'] ) || empty( $health['mailReadReady'] ) || empty( $health['smtpConfigured'] ) ) {
            self::restore( 'communications', $v, 'mail_postcheck_failed' );
            return new \WP_Error( 'mvm_mail_release_postcheck', 'Externe mail kon niet veilig worden vrijgegeven; Communications is behouden.', array( 'status' => 500, 'health' => $health ) );
        }
        Audit::record( 'release.mail_promote', 'success', 'hub_release', 0, array( 'scope' => 'mail', 'communications_writes' => true, 'mail_writes' => true ) );
        return self::snapshot();
    }

    /** Non-secret provider/runtime readiness. @return array<string,mixed> */
    public static function health(): array {
        $mailbox = self::mailbox_id();
        $provider = Communications_Service_Factory::mail_provider();
        $caps = $provider->capabilities( $mailbox );
        $storage = Private_Storage_Root::path( true );
        $cron = Cron_Ownership::status();
        return array(
            'mailboxId' => $mailbox,
            'providerClass' => sanitize_text_field( get_class( $provider ) ),
            'mailReadReady' => true === ( $caps['read'] ?? false ),
            'foldersReady' => true === ( $caps['folders'] ?? false ),
            'draftsReady' => true === ( $caps['drafts'] ?? false ),
            'attachmentsReady' => true === ( $caps['attachments'] ?? false ),
            'smtpConfigured' => true === ( $caps['send'] ?? false ),
            'remoteImagesDefault' => true === ( $caps['remoteImages'] ?? false ),
            'privateStorageReady' => ! is_wp_error( $storage ),
            'internalMessagesReady' => PeepSo8_Internal_Message_Provider::supported(),
            'cronHealthy' => ! empty( $cron['takeoverEnabled'] ) && ! empty( $cron['newsradar']['nextRun'] ) && ( empty( $cron['aiAgenda']['enabled'] ) || ! empty( $cron['aiAgenda']['nextRun'] ) ) && ! empty( $cron['audit']['nextRun'] ),
        );
    }

    /** @return array{artifact:string,inner:string}|\WP_Error */
    private static function input( array $input ): array|\WP_Error {
        if ( Release_Control::release_id() !== sanitize_text_field( (string) ( $input['releaseId'] ?? '' ) ) ) return new \WP_Error( 'mvm_communications_release_id', 'Release-ID komt niet overeen.', array( 'status' => 409 ) );
        if ( true !== rest_sanitize_boolean( $input['backupConfirmed'] ?? false ) ) return new \WP_Error( 'mvm_communications_release_backup', 'Een bevestigde herstelbare backup is verplicht.', array( 'status' => 409 ) );
        $artifact = self::sha( $input['artifactSha256'] ?? '' );
        $inner = self::sha( $input['innerSha256'] ?? '' );
        if ( '' === $artifact || '' === $inner ) return new \WP_Error( 'mvm_communications_release_hashes', 'Geverifieerde artifact-hashes zijn verplicht.', array( 'status' => 400 ) );
        return array( 'artifact' => $artifact, 'inner' => $inner );
    }

    /** @return array<string,mixed>|\WP_Error */
    private static function preflight( string $scope, bool $smtp ): array|\WP_Error {
        $release = Release_Control::status();
        if ( empty( $release['valid'] ) || 'live' !== (string) ( $release['mode'] ?? '' ) || $scope !== (string) ( $release['scope'] ?? '' ) ) return new \WP_Error( 'mvm_communications_release_scope', 'De huidige release-scope is niet geschikt.', array( 'status' => 409 ) );
        if ( ! self::core_ok() ) return new \WP_Error( 'mvm_communications_release_core', 'De bestaande Hub-runtime is niet volledig groen.', array( 'status' => 409 ) );
        if ( 'full' === $scope && ( Runtime_Gates::communications_writes_enabled() || Runtime_Gates::mail_writes_enabled() ) ) return new \WP_Error( 'mvm_communications_release_gates', 'Communications/Mail gate-state wijkt af.', array( 'status' => 409 ) );
        if ( 'communications' === $scope && ( ! Runtime_Gates::communications_writes_enabled() || Runtime_Gates::mail_writes_enabled() ) ) return new \WP_Error( 'mvm_mail_release_gates', 'Communications moet eerst zelfstandig groen live staan.', array( 'status' => 409 ) );

        $health = self::health();
        if ( empty( $health['privateStorageReady'] ) || empty( $health['mailReadReady'] ) || empty( $health['internalMessagesReady'] ) || empty( $health['cronHealthy'] ) ) return new \WP_Error( 'mvm_communications_release_health', 'Communications-preflight is niet volledig groen.', array( 'status' => 409, 'health' => $health ) );
        if ( $smtp && empty( $health['smtpConfigured'] ) ) return new \WP_Error( 'mvm_mail_release_smtp', 'Dedicated SMTP is niet volledig geconfigureerd.', array( 'status' => 409, 'health' => $health ) );

        $roles = Role_Capability_Bundles::status();
        $schema_version = (int) ( $roles['schemaVersion'] ?? 0 );
        $stored_version = (int) ( $roles['storedVersion'] ?? 0 );
        if ( $schema_version < 1 || $stored_version !== $schema_version ) return new \WP_Error( 'mvm_communications_release_caps', 'Actieve capabilityschema komt niet overeen met de gedeclareerde Hub-schema.', array( 'status' => 409 ) );
        foreach ( (array) ( $roles['roles'] ?? array() ) as $r ) if ( ! empty( $r['exists'] ) && empty( $r['matches'] ) ) return new \WP_Error( 'mvm_communications_release_caps', 'Capabilityschema bevat drift.', array( 'status' => 409 ) );
        return array( 'release' => $release, 'health' => $health );
    }

    private static function core_ok(): bool {
        return Runtime_Gates::route_takeover_enabled() && Runtime_Gates::newsroom_writes_enabled() && Runtime_Gates::capability_reconciliation_enabled() && Runtime_Gates::cron_takeover_enabled() && Legacy_Services::persistent_ready() && Staff_Login_Recaptcha::dependencies_ready();
    }

    /** @param array{artifact:string,inner:string} $v */
    private static function restore( string $scope, array $v, string $reason ): void {
        $r = Release_Control::set_mode( 'live', get_current_user_id(), $v['artifact'], $v['inner'], $scope );
        Audit::record( 'release.communications_rollback', is_wp_error( $r ) ? 'error' : 'rollback', 'hub_release', 0, array( 'reason' => $reason, 'restored_scope' => is_wp_error( $r ) ? 'unknown' : $scope ) );
    }

    /** @return array<string,mixed> */
    private static function snapshot(): array {
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
            'health' => self::health(),
            'capabilities' => Role_Capability_Bundles::status(),
        );
    }

    private static function mailbox_id(): string { return defined( 'MVM_HUB_MAILBOX_ID' ) ? sanitize_key( (string) MVM_HUB_MAILBOX_ID ) : 'editorial'; }
    private static function sha( mixed $v ): string { $h = strtolower( trim( (string) $v ) ); return 64 === strlen( $h ) && ctype_xdigit( $h ) ? $h : ''; }
    private function __construct() {}
}
