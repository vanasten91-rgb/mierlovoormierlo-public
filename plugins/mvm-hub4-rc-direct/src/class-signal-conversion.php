<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class MvM_Hub4_Signal_Conversion {
    private const TARGETS = array( 'dossier', 'assignment' );

    public static function init(): void {
        add_action( 'rest_api_init', array( __CLASS__, 'register_route' ) );
    }

    public static function register_route(): void {
        register_rest_route(
            'mvm-hub4/v1',
            '/signals/(?P<id>\d+)/convert',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( __CLASS__, 'rest_convert' ),
                'permission_callback' => MvM_Hub4_Security::require_capability( MvM_Hub4_Capabilities::SIGNAL_TRIAGE ),
                'args'                => array(
                    'id' => array(
                        'required'          => true,
                        'sanitize_callback' => 'absint',
                        'validate_callback' => static fn( mixed $value ): bool => absint( $value ) > 0,
                    ),
                ),
            )
        );
    }

    public static function rest_convert( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $input = $request->get_json_params();
        $result = self::convert( absint( $request['id'] ), is_array( $input ) ? $input : array() );
        return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
    }

    public static function convert( int $signal_id, array $input ): array|WP_Error {
        $signal = MvM_Hub4_Signals::get( $signal_id );
        if ( is_wp_error( $signal ) ) {
            return $signal;
        }

        $target = sanitize_key( (string) ( $input['target'] ?? '' ) );
        if ( ! in_array( $target, self::TARGETS, true ) ) {
            return new WP_Error( 'mvm_hub4_signal_convert_target', 'Kies dossier of opdracht als doel.', array( 'status' => 400 ) );
        }

        if ( in_array( (string) $signal['status'], array( 'converted', 'closed', 'rejected' ), true ) ) {
            return new WP_Error( 'mvm_hub4_signal_convert_state', 'Dit signaal kan vanuit de huidige status niet worden omgezet.', array( 'status' => 409 ) );
        }
        if ( (int) ( $signal['dossierId'] ?? 0 ) > 0 || (int) ( $signal['assignmentId'] ?? 0 ) > 0 ) {
            return new WP_Error( 'mvm_hub4_signal_convert_exists', 'Dit signaal is al aan redactioneel werk gekoppeld.', array( 'status' => 409 ) );
        }

        return 'dossier' === $target
            ? self::to_dossier( $signal_id, $signal, $input )
            : self::to_assignment( $signal_id, $signal, $input );
    }

    private static function to_dossier( int $signal_id, array $signal, array $input ): array|WP_Error {
        if ( ! current_user_can( MvM_Hub4_Capabilities::DOSSIER_MANAGE ) && ! current_user_can( 'manage_options' ) ) {
            return new WP_Error( 'mvm_hub4_signal_convert_dossier_denied', 'Je mag geen dossier maken.', array( 'status' => 403 ) );
        }

        $dossier_input = array(
            'title'         => (string) $signal['title'],
            'summary'       => (string) ( $signal['summary'] ?? '' ),
            'internalBrief' => self::brief_from_signal( $signal_id, $signal ),
        );
        if ( absint( $input['leadUserId'] ?? 0 ) > 0 ) {
            $dossier_input['leadUserId'] = absint( $input['leadUserId'] );
        }
        if ( absint( $input['categoryTermId'] ?? 0 ) > 0 ) {
            $dossier_input['categoryTermId'] = absint( $input['categoryTermId'] );
        }

        $dossier = MvM_Hub4_Dossiers::create( $dossier_input );
        if ( is_wp_error( $dossier ) ) {
            return $dossier;
        }
        $dossier_id = absint( $dossier['id'] ?? 0 );
        if ( $dossier_id < 1 ) {
            return new WP_Error( 'mvm_hub4_signal_convert_dossier_missing', 'Het nieuwe dossier heeft geen geldig ID.', array( 'status' => 500 ) );
        }

        $linked = MvM_Hub4_Dossiers::add_link(
            $dossier_id,
            array(
                'objectType' => 'signal',
                'objectId'   => $signal_id,
                'label'      => 'Redactioneel signaal #' . $signal_id,
            )
        );
        if ( is_wp_error( $linked ) ) {
            MvM_Hub4_Audit::log( 'signal.convert', 'failed', array( 'object_type' => 'signal', 'object_id' => $signal_id, 'context' => array( 'target' => 'dossier', 'target_id' => $dossier_id ) ) );
            return $linked;
        }

        $updated = MvM_Hub4_Signals::update(
            $signal_id,
            array(
                'dossierId' => $dossier_id,
                'status'    => 'converted',
            )
        );
        if ( is_wp_error( $updated ) ) {
            return $updated;
        }

        MvM_Hub4_Audit::log(
            'signal.convert',
            'success',
            array(
                'object_type' => 'signal',
                'object_id'   => $signal_id,
                'context'     => array( 'target' => 'dossier', 'target_id' => $dossier_id ),
            )
        );

        return array( 'target' => 'dossier', 'targetId' => $dossier_id, 'signal' => $updated, 'dossier' => $linked );
    }

    private static function to_assignment( int $signal_id, array $signal, array $input ): array|WP_Error {
        global $wpdb;

        if ( ! current_user_can( MvM_Hub4_Capabilities::ASSIGNMENT_CREATE ) && ! current_user_can( MvM_Hub4_Capabilities::ASSIGNMENT_MANAGE ) && ! current_user_can( 'manage_options' ) ) {
            return new WP_Error( 'mvm_hub4_signal_convert_assignment_denied', 'Je mag geen opdracht maken.', array( 'status' => 403 ) );
        }

        $assignee = absint( $input['assigneeUserId'] ?? ( $signal['assigneeUserId'] ?? 0 ) );
        $assignment_input = array(
            'type'           => self::assignment_type( (string) $signal['kind'] ),
            'status'         => $assignee > 0 ? 'assigned' : 'signal',
            'priority'       => min( 4, max( 1, absint( $signal['priority'] ?? 2 ) ) ),
            'title'          => (string) $signal['title'],
            'brief'          => self::brief_from_signal( $signal_id, $signal ),
            'assigneeUserId' => $assignee,
        );
        if ( ! empty( $input['dueAtUtc'] ) ) {
            $assignment_input['dueAtUtc'] = sanitize_text_field( (string) $input['dueAtUtc'] );
        }

        $assignment = MvM_Hub4_Assignments::create( $assignment_input );
        if ( is_wp_error( $assignment ) ) {
            return $assignment;
        }
        $assignment_id = absint( $assignment['id'] ?? 0 );
        if ( $assignment_id < 1 ) {
            return new WP_Error( 'mvm_hub4_signal_convert_assignment_missing', 'De nieuwe opdracht heeft geen geldig ID.', array( 'status' => 500 ) );
        }

        $now = current_time( 'mysql', true );
        $ok = $wpdb->update(
            MvM_Hub4_Platform_Schema::table( 'signals' ),
            array(
                'status'           => 'converted',
                'assignment_id'    => $assignment_id,
                'assignee_user_id' => absint( $assignment['assigneeUserId'] ?? $assignee ),
                'updated_at_utc'   => $now,
                'closed_at_utc'    => $now,
            ),
            array( 'id' => $signal_id ),
            array( '%s', '%d', '%d', '%s', '%s' ),
            array( '%d' )
        );
        if ( false === $ok ) {
            MvM_Hub4_Audit::log( 'signal.convert', 'failed', array( 'object_type' => 'signal', 'object_id' => $signal_id, 'context' => array( 'target' => 'assignment', 'target_id' => $assignment_id ) ) );
            return new WP_Error( 'mvm_hub4_signal_convert_update', 'De opdracht is gemaakt, maar het signaal kon niet worden gekoppeld.', array( 'status' => 500 ) );
        }

        MvM_Hub4_Audit::log(
            'signal.convert',
            'success',
            array(
                'object_type' => 'signal',
                'object_id'   => $signal_id,
                'context'     => array( 'target' => 'assignment', 'target_id' => $assignment_id ),
            )
        );

        return array( 'target' => 'assignment', 'targetId' => $assignment_id, 'signal' => MvM_Hub4_Signals::get( $signal_id ), 'assignment' => $assignment );
    }

    private static function assignment_type( string $kind ): string {
        return match ( sanitize_key( $kind ) ) {
            'photo'  => 'photo',
            'event'  => 'event',
            'source' => 'source',
            'general'=> 'general',
            default  => 'news',
        };
    }

    private static function brief_from_signal( int $signal_id, array $signal ): string {
        $parts = array( 'Omgezet vanuit Hub-signaal #' . $signal_id . '.' );
        if ( ! empty( $signal['summary'] ) ) {
            $parts[] = (string) $signal['summary'];
        }
        if ( ! empty( $signal['location'] ) ) {
            $parts[] = 'Locatie: ' . (string) $signal['location'];
        }
        if ( ! empty( $signal['sourceUrl'] ) ) {
            $parts[] = 'Bron: ' . (string) $signal['sourceUrl'];
        }
        if ( 'safety' === (string) ( $signal['kind'] ?? '' ) ) {
            $parts[] = '112-status: ' . (string) ( $signal['incidentStatus'] ?? 'unknown' ) . '; broncontrole: ' . (string) ( $signal['verificationStatus'] ?? 'unverified' ) . '; incidenttijd: ' . (string) ( $signal['incidentAtUtc'] ?? 'onbekend' ) . '.';
        }
        return mb_substr( implode( "\n\n", $parts ), 0, 8000 );
    }
}
