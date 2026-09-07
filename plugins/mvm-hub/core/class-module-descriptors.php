<?php

namespace MVM\Hub\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Shared module status contract between shell and backend.
 *
 * Routes listed here are informational only. Endpoint permission callbacks and
 * object authorization remain authoritative. Writes stay blocked during the
 * parallel migration even when a service implementation already exists.
 */
final class Module_Descriptors {
    /** @return array<string,mixed>|null */
    public static function for( string $workspace, string $section ): ?array {
        $workspace = sanitize_key( $workspace );
        $section   = sanitize_key( $section );

        if ( ! Workspace_Sections::can_access( $workspace, $section ) ) {
            return null;
        }

        $config = Workspace_Sections::all()[ $workspace ][ $section ] ?? null;
        if ( ! is_array( $config ) ) {
            return null;
        }

        $descriptor = match ( $workspace . ':' . $section ) {
            'newsroom:today' => self::read_ready( array( '/mvm-hub/v1/newsroom/today' ) ),
            'newsroom:news' => self::read_ready(
                array( '/mvm-hub/v1/newsroom/news' ),
                array(
                    '/mvm-hub/v1/newsroom/news/{id}/smart-links',
                    '/mvm-hub/v1/newsroom/encyclopedia/targets',
                )
            ),
            'newsroom:assignments' => self::read_ready( array( '/mvm-hub/v1/newsroom/assignments' ) ),
            'newsroom:radar' => self::read_ready( array( '/mvm-hub/v1/newsroom/radar' ) ),
            'newsroom:sources' => self::read_ready( array( '/mvm-hub/v1/newsroom/sources' ) ),
            'newsroom:agenda' => self::read_ready( array( '/mvm-hub/v1/newsroom/agenda' ) ),
            'newsroom:media' => self::read_ready( array( '/mvm-hub/v1/newsroom/media' ) ),
            'newsroom:dossiers' => self::read_ready( array( '/mvm-hub/v1/newsroom/dossiers' ) ),
            'newsroom:corrections' => self::read_ready( array( '/mvm-hub/v1/newsroom/corrections' ) ),
            'newsroom:distribution' => self::read_ready( array( '/mvm-hub/v1/newsroom/distribution' ) ),
            'newsroom:team' => self::read_ready( array( '/mvm-hub/v1/newsroom/team' ) ),
            'communications:messages' => array(
                'stage'           => 'read-ready',
                'readRoutes'      => array(
                    '/mvm-hub/v1/communications/messages',
                    '/mvm-hub/v1/communications/messages/{id}',
                ),
                'auxiliaryRoutes' => array(),
                'writesEnabled'   => false,
                'writeState'      => 'provider-write-adapter-not-enabled',
            ),
            'communications:mail' => array(
                'stage'           => 'read-ready',
                'readRoutes'      => array(
                    '/mvm-hub/v1/communications/mail/folders',
                    '/mvm-hub/v1/communications/mail/messages',
                    '/mvm-hub/v1/communications/mail/messages/{id}',
                ),
                'auxiliaryRoutes' => array(),
                'writesEnabled'   => false,
                'writeState'      => 'transport-security-gated',
            ),
            default => array(
                'stage'           => 'planned',
                'readRoutes'      => array(),
                'auxiliaryRoutes' => array(),
                'writesEnabled'   => false,
                'writeState'      => 'not-implemented',
            ),
        };

        return array_merge(
            array(
                'workspace' => $workspace,
                'section'   => $section,
                'label'     => sanitize_text_field( (string) ( $config['label'] ?? $section ) ),
            ),
            $descriptor
        );
    }

    /** @param array<int,string> $routes @param array<int,string> $auxiliary @return array<string,mixed> */
    private static function read_ready( array $routes, array $auxiliary = array() ): array {
        return array(
            'stage'           => 'read-ready',
            'readRoutes'      => array_values( $routes ),
            'auxiliaryRoutes' => array_values( $auxiliary ),
            'writesEnabled'   => false,
            'writeState'      => 'parallel-migration-blocked',
        );
    }

    private function __construct() {}
}
