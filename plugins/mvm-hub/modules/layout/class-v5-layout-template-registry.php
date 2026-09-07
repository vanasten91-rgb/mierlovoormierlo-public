<?php

namespace MVM\Hub\Modules\Layout;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Code-owned allowlist of Layout Studio renderers.
 *
 * Layout editors select a reviewed template ID and safe schema settings; they
 * never submit PHP, JavaScript or arbitrary HTML through Layout Studio.
 */
final class V5_Layout_Template_Registry {
    /** @return array<string,array<string,mixed>> */
    public static function all(): array {
        return array(
            'news-grid-b' => array(
                'slot' => 'news-main',
                'label' => 'Nieuws raster',
                'settings' => array(
                    'items' => array( 'type' => 'int', 'min' => 1, 'max' => 12, 'default' => 6 ),
                    'category' => array( 'type' => 'key', 'default' => 'all' ),
                    'show_excerpt' => array( 'type' => 'bool', 'default' => true ),
                ),
            ),
            'agenda-list-a' => array(
                'slot' => 'agenda-main',
                'label' => 'Agenda lijst',
                'settings' => array(
                    'items' => array( 'type' => 'int', 'min' => 1, 'max' => 20, 'default' => 8 ),
                    'show_location' => array( 'type' => 'bool', 'default' => true ),
                ),
            ),
            'encyclopedie-feature-a' => array(
                'slot' => 'encyclopedie-feature',
                'label' => 'Encyclopedie uitgelicht',
                'settings' => array(
                    'items' => array( 'type' => 'int', 'min' => 1, 'max' => 8, 'default' => 4 ),
                    'theme' => array( 'type' => 'key', 'default' => 'all' ),
                ),
            ),
        );
    }

    /** @return array<string,mixed>|null */
    public static function get( string $template_id ): ?array {
        $template_id = sanitize_key( $template_id );
        $all = self::all();
        return $all[ $template_id ] ?? null;
    }

    /** @return array<string,mixed>|null */
    public static function normalize( string $slot, string $template_id, array $settings ): ?array {
        $slot = sanitize_key( $slot );
        $template_id = sanitize_key( $template_id );
        $template = self::get( $template_id );
        if ( null === $template || $slot !== (string) $template['slot'] ) {
            return null;
        }

        $normalized = array();
        foreach ( (array) $template['settings'] as $name => $rule ) {
            $value = $settings[ $name ] ?? ( $rule['default'] ?? null );
            $type = (string) ( $rule['type'] ?? '' );
            if ( 'bool' === $type ) {
                $normalized[ $name ] = true === $value || 1 === $value || '1' === $value || 'true' === $value;
            } elseif ( 'int' === $type ) {
                $min = (int) ( $rule['min'] ?? 0 );
                $max = (int) ( $rule['max'] ?? PHP_INT_MAX );
                $normalized[ $name ] = max( $min, min( $max, (int) $value ) );
            } elseif ( 'key' === $type ) {
                $normalized[ $name ] = sanitize_key( (string) $value );
                if ( '' === $normalized[ $name ] ) {
                    $normalized[ $name ] = sanitize_key( (string) ( $rule['default'] ?? '' ) );
                }
            }
        }

        return array(
            'slot' => $slot,
            'template' => $template_id,
            'settings' => $normalized,
            'executableContentAllowed' => false,
            'arbitraryHtmlAllowed' => false,
        );
    }

    private function __construct() {}
}
