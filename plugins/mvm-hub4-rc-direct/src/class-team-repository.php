<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class MvM_Hub4_Team_Repository {
    public static function overview(): array {
        $role_labels = self::role_labels();
        $roles       = array_keys( $role_labels );

        $query = new WP_User_Query(
            array(
                'role__in' => $roles,
                'orderby'  => 'display_name',
                'order'    => 'ASC',
                'number'   => 100,
                'fields'   => 'all',
            )
        );

        $items  = array();
        $counts = array_fill_keys( array_values( $role_labels ), 0 );

        foreach ( (array) $query->get_results() as $user ) {
            if ( ! $user instanceof WP_User ) {
                continue;
            }

            $labels = array();
            foreach ( (array) $user->roles as $role ) {
                if ( isset( $role_labels[ $role ] ) ) {
                    $label    = $role_labels[ $role ];
                    $labels[] = $label;
                    $counts[ $label ] = (int) ( $counts[ $label ] ?? 0 ) + 1;
                }
            }

            if ( ! $labels ) {
                continue;
            }

            $items[] = array(
                'id'          => (int) $user->ID,
                'displayName' => sanitize_text_field( (string) $user->display_name ),
                'roles'       => array_values( $labels ),
                'isCurrent'   => (int) $user->ID === get_current_user_id(),
            );
        }

        $counts = array_filter( $counts, static fn ( int $count ): bool => $count > 0 );

        return array(
            'items'  => $items,
            'counts' => $counts,
            'total'  => count( $items ),
        );
    }

    public static function role_labels(): array {
        return array(
            'mvm_sysop'      => 'SysOp',
            'mvm_teamleider' => 'Teamleider',
            'mvm_editor'     => 'Editor',
            'mvm_journalist' => 'Journalist',
            'mvm_redacteur'  => 'Redacteur',
            'mvm_fotograaf'  => 'Fotograaf',
            'mvm_moderator'  => 'Moderator',
            'mvm_vertaler'   => 'Vertaler',
        );
    }
}
