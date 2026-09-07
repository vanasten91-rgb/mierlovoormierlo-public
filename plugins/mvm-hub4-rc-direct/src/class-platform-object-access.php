<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Central object-level authorization for cross-module v1 relations.
 *
 * Capabilities decide whether a workflow may be used; these checks decide
 * whether the current user may reference the concrete object supplied by ID.
 */
final class MvM_Hub4_Platform_Object_Access {
    public static function dossier( int $id, string $label = 'dossier' ): int|WP_Error {
        if ( 0 === $id ) {
            return 0;
        }
        $result = MvM_Hub4_Dossiers::get( $id );
        if ( is_wp_error( $result ) ) {
            self::audit_denied( $label, $id, 'dossier' );
            return new WP_Error( 'mvm_hub4_relation_dossier_denied', 'Het gekozen dossier bestaat niet of is niet toegankelijk.', array( 'status' => 403 ) );
        }
        return $id;
    }

    public static function assignment( int $id, string $label = 'opdracht' ): int|WP_Error {
        if ( 0 === $id ) {
            return 0;
        }
        if ( ! current_user_can( MvM_Hub4_Capabilities::ASSIGNMENT_VIEW ) && ! current_user_can( 'manage_options' ) ) {
            self::audit_denied( $label, $id, 'assignment' );
            return new WP_Error( 'mvm_hub4_relation_assignment_denied', 'De gekozen opdracht bestaat niet of is niet toegankelijk.', array( 'status' => 403 ) );
        }
        $result = MvM_Hub4_Assignments::get_public( $id );
        if ( is_wp_error( $result ) ) {
            self::audit_denied( $label, $id, 'assignment' );
            return new WP_Error( 'mvm_hub4_relation_assignment_denied', 'De gekozen opdracht bestaat niet of is niet toegankelijk.', array( 'status' => 403 ) );
        }
        return $id;
    }

    public static function post( int $id, array $allowed_post_types = array(), ?string $required_capability = null, string $label = 'bericht' ): int|WP_Error {
        if ( 0 === $id ) {
            return 0;
        }
        if ( $required_capability && ! current_user_can( $required_capability ) && ! current_user_can( 'manage_options' ) ) {
            self::audit_denied( $label, $id, 'post' );
            return new WP_Error( 'mvm_hub4_relation_post_denied', 'Het gekozen item bestaat niet of is niet toegankelijk.', array( 'status' => 403 ) );
        }
        $post = get_post( $id );
        if ( ! $post instanceof WP_Post ) {
            return new WP_Error( 'mvm_hub4_relation_post_missing', 'Het gekozen item bestaat niet.', array( 'status' => 400 ) );
        }
        if ( $allowed_post_types && ! in_array( (string) $post->post_type, $allowed_post_types, true ) ) {
            return new WP_Error( 'mvm_hub4_relation_post_type', 'Het gekozen item heeft niet het verwachte type.', array( 'status' => 400 ) );
        }
        if ( ! current_user_can( 'read_post', $id ) && ! current_user_can( 'manage_options' ) ) {
            self::audit_denied( $label, $id, (string) $post->post_type );
            return new WP_Error( 'mvm_hub4_relation_post_denied', 'Het gekozen item bestaat niet of is niet toegankelijk.', array( 'status' => 403 ) );
        }
        return $id;
    }

    public static function editorial_post( int $id, string $label = 'redactionele content' ): int|WP_Error {
        if ( 0 === $id ) {
            return 0;
        }
        $post = get_post( $id );
        if ( ! $post instanceof WP_Post ) {
            return new WP_Error( 'mvm_hub4_relation_post_missing', 'Het gekozen item bestaat niet.', array( 'status' => 400 ) );
        }
        return match ( (string) $post->post_type ) {
            'post'          => self::post( $id, array( 'post' ), MvM_Hub4_Capabilities::NEWS_VIEW, $label ),
            'event_listing' => self::post( $id, array( 'event_listing' ), MvM_Hub4_Capabilities::AGENDA_VIEW, $label ),
            default         => new WP_Error( 'mvm_hub4_relation_post_type', 'Alleen nieuws of evenementen kunnen aan de redactiekalender worden gekoppeld.', array( 'status' => 400 ) ),
        };
    }

    public static function signal( int $id, string $label = 'signaal' ): int|WP_Error {
        if ( 0 === $id ) {
            return 0;
        }
        if ( ! current_user_can( MvM_Hub4_Capabilities::SIGNAL_VIEW ) && ! current_user_can( 'manage_options' ) ) {
            self::audit_denied( $label, $id, 'signal' );
            return new WP_Error( 'mvm_hub4_relation_signal_denied', 'Het gekozen signaal bestaat niet of is niet toegankelijk.', array( 'status' => 403 ) );
        }
        $result = MvM_Hub4_Signals::get( $id );
        if ( is_wp_error( $result ) ) {
            return new WP_Error( 'mvm_hub4_relation_signal_missing', 'Het gekozen signaal bestaat niet.', array( 'status' => 400 ) );
        }
        return $id;
    }

    public static function media( int $id, string $label = 'media-item' ): int|WP_Error {
        if ( 0 === $id ) {
            return 0;
        }
        $result = MvM_Hub4_Media_Desk::get( $id );
        if ( is_wp_error( $result ) ) {
            self::audit_denied( $label, $id, 'media' );
            return new WP_Error( 'mvm_hub4_relation_media_denied', 'Het gekozen media-item bestaat niet of is niet toegankelijk.', array( 'status' => 403 ) );
        }
        return $id;
    }

    public static function dossier_link_target( string $type, int $object_id ): int|WP_Error {
        return match ( $type ) {
            'post'         => self::post( $object_id, array( 'post' ), MvM_Hub4_Capabilities::NEWS_VIEW, 'dossierlink nieuwsartikel' ),
            'event'        => self::post( $object_id, array( 'event_listing' ), MvM_Hub4_Capabilities::AGENDA_VIEW, 'dossierlink evenement' ),
            'source'       => self::post( $object_id, array( 'mvm_bron' ), MvM_Hub4_Capabilities::SOURCE_VIEW, 'dossierlink bron' ),
            'encyclopedia' => self::post( $object_id, array(), null, 'dossierlink encyclopedie' ),
            'assignment'   => self::assignment( $object_id, 'dossierlink opdracht' ),
            'signal'       => self::signal( $object_id, 'dossierlink signaal' ),
            'media'        => self::media( $object_id, 'dossierlink media' ),
            default        => new WP_Error( 'mvm_hub4_relation_type', 'Dit relationele type kan niet op objectniveau worden gevalideerd.', array( 'status' => 400 ) ),
        };
    }

    private static function audit_denied( string $label, int $id, string $object_type ): void {
        if ( ! class_exists( 'MvM_Hub4_Audit' ) ) {
            return;
        }
        MvM_Hub4_Audit::log(
            'security.relation_denied',
            'denied',
            array(
                'object_type' => $object_type,
                'object_id'   => $id,
                'context'     => array( 'relation' => sanitize_key( $label ) ),
            )
        );
    }
}
