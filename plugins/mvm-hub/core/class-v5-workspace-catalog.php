<?php

namespace MVM\Hub\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Dormant information architecture for the Hub V5 staff shell.
 *
 * This catalog deliberately performs no current_user_can checks and claims no
 * routes. A future policy resolver filters it after authorization. Keeping the
 * catalog separate from the current Workspaces class allows side-by-side design
 * and testing without changing the live /hub/ navigation.
 */
final class V5_Workspace_Catalog {
    /** @return array<string,array<string,string|int|bool>> */
    public static function all(): array {
        return array(
            'my-work' => array(
                'label'      => 'Mijn Werk',
                'path'       => 'werk/',
                'policy'     => 'staff',
                'staff_only' => true,
                'priority'   => 10,
            ),
            'newsroom' => array(
                'label'      => 'Nieuwsroom',
                'path'       => 'nieuwsroom/',
                'policy'     => 'newsroom',
                'staff_only' => true,
                'priority'   => 20,
            ),
            'agenda' => array(
                'label'      => 'Agenda',
                'path'       => 'agenda/',
                'policy'     => 'agenda',
                'staff_only' => true,
                'priority'   => 30,
            ),
            'encyclopedie' => array(
                'label'      => 'Encyclopedie',
                'path'       => 'encyclopedie/',
                'policy'     => 'encyclopedie',
                'staff_only' => true,
                'priority'   => 40,
            ),
            'community' => array(
                'label'      => 'Community',
                'path'       => 'community/',
                'policy'     => 'community',
                'staff_only' => true,
                'priority'   => 50,
            ),
            'mierlokaal' => array(
                'label'      => 'MierLokaal',
                'path'       => 'mierlokaal/',
                'policy'     => 'mierlokaal',
                'staff_only' => true,
                'priority'   => 60,
            ),
            'intake' => array(
                'label'      => 'Intake',
                'path'       => 'intake/',
                'policy'     => 'intake',
                'staff_only' => true,
                'priority'   => 70,
            ),
            'media' => array(
                'label'      => 'Media',
                'path'       => 'media/',
                'policy'     => 'media',
                'staff_only' => true,
                'priority'   => 80,
            ),
            'communications' => array(
                'label'      => 'Communicatie',
                'path'       => 'communicatie/',
                'policy'     => 'communications',
                'staff_only' => true,
                'priority'   => 90,
            ),
            'layout' => array(
                'label'      => 'Layout Studio',
                'path'       => 'layout/',
                'policy'     => 'layout',
                'staff_only' => true,
                'priority'   => 100,
            ),
            'technical' => array(
                'label'      => 'Techniek',
                'path'       => 'techniek/',
                'policy'     => 'technical',
                'staff_only' => true,
                'priority'   => 110,
            ),
        );
    }

    /** @return array<string,string|int|bool>|null */
    public static function get( string $workspace ): ?array {
        $workspace = sanitize_key( $workspace );
        return self::all()[ $workspace ] ?? null;
    }

    public static function default_workspace(): string {
        return 'my-work';
    }

    /** @return list<string> */
    public static function policy_keys(): array {
        $policies = array_map(
            static fn( array $config ): string => sanitize_key( (string) $config['policy'] ),
            self::all()
        );

        return array_values( array_unique( array_filter( $policies ) ) );
    }

    private function __construct() {}
}
