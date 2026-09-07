<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Guarded write facade for v1 cross-module relations.
 *
 * REST and Abilities must use this facade whenever user-controlled IDs create
 * a relation between Hub records or WordPress objects.
 */
final class MvM_Hub4_Platform_Writes {
    public static function create_calendar( array $input ): array|WP_Error {
        $check = self::calendar_relations( $input, false );
        return is_wp_error( $check ) ? $check : MvM_Hub4_Editorial_Calendar::create( $input );
    }

    public static function update_calendar( int $id, array $input ): array|WP_Error {
        $check = self::calendar_relations( $input, true );
        return is_wp_error( $check ) ? $check : MvM_Hub4_Editorial_Calendar::update( $id, $input );
    }

    public static function create_media( array $input ): array|WP_Error {
        $dossier = MvM_Hub4_Platform_Object_Access::dossier( absint( $input['dossierId'] ?? 0 ), 'media dossier' );
        if ( is_wp_error( $dossier ) ) {
            return $dossier;
        }
        $assignment = MvM_Hub4_Platform_Object_Access::assignment( absint( $input['assignmentId'] ?? 0 ), 'media opdracht' );
        if ( is_wp_error( $assignment ) ) {
            return $assignment;
        }
        return MvM_Hub4_Media_Desk::create( $input );
    }

    public static function create_distribution( array $input ): array|WP_Error {
        $dossier = MvM_Hub4_Platform_Object_Access::dossier( absint( $input['dossierId'] ?? 0 ), 'distributie dossier' );
        if ( is_wp_error( $dossier ) ) {
            return $dossier;
        }
        return MvM_Hub4_Distribution::create( $input );
    }

    public static function add_dossier_link( int $dossier_id, array $input ): array|WP_Error {
        $type = sanitize_key( (string) ( $input['objectType'] ?? '' ) );
        if ( 'url' !== $type ) {
            $target = MvM_Hub4_Platform_Object_Access::dossier_link_target( $type, absint( $input['objectId'] ?? 0 ) );
            if ( is_wp_error( $target ) ) {
                return $target;
            }
        }
        return MvM_Hub4_Dossiers::add_link( $dossier_id, $input );
    }

    private static function calendar_relations( array $input, bool $partial ): true|WP_Error {
        if ( ! $partial || array_key_exists( 'dossierId', $input ) ) {
            $dossier = MvM_Hub4_Platform_Object_Access::dossier( absint( $input['dossierId'] ?? 0 ), 'kalender dossier' );
            if ( is_wp_error( $dossier ) ) {
                return $dossier;
            }
        }
        if ( ! $partial || array_key_exists( 'assignmentId', $input ) ) {
            $assignment = MvM_Hub4_Platform_Object_Access::assignment( absint( $input['assignmentId'] ?? 0 ), 'kalender opdracht' );
            if ( is_wp_error( $assignment ) ) {
                return $assignment;
            }
        }
        if ( ! $partial || array_key_exists( 'postId', $input ) ) {
            $post = MvM_Hub4_Platform_Object_Access::editorial_post( absint( $input['postId'] ?? 0 ), 'kalender content' );
            if ( is_wp_error( $post ) ) {
                return $post;
            }
        }
        return true;
    }
}
