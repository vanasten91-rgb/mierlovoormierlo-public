<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class MVM_Encyclopedie_Next_Content_Model {
    /** @var array<string,array<string,mixed>> */
    private const POST_TYPES = array(
        'mvm_encyclopedie' => array(
            'plural' => 'Encyclopedie',
            'singular' => 'Encyclopedie-artikel',
            'rewrite' => 'encyclopedie/artikel',
            'icon' => 'dashicons-book-alt',
        ),
        'mvm_persoon' => array(
            'plural' => 'Personen',
            'singular' => 'Persoon',
            'rewrite' => 'encyclopedie/personen',
            'icon' => 'dashicons-id',
        ),
        'mvm_locatie' => array(
            'plural' => 'Locaties',
            'singular' => 'Locatie',
            'rewrite' => 'encyclopedie/locaties',
            'icon' => 'dashicons-location-alt',
        ),
        'mvm_gebouw' => array(
            'plural' => 'Gebouwen',
            'singular' => 'Gebouw',
            'rewrite' => 'encyclopedie/gebouwen',
            'icon' => 'dashicons-building',
        ),
        'mvm_gebeurtenis' => array(
            'plural' => 'Gebeurtenissen',
            'singular' => 'Gebeurtenis',
            'rewrite' => 'encyclopedie/gebeurtenissen',
            'icon' => 'dashicons-calendar-alt',
        ),
        'mvm_vereniging' => array(
            'plural' => 'Verenigingen',
            'singular' => 'Vereniging',
            'rewrite' => 'encyclopedie/verenigingen',
            'icon' => 'dashicons-groups',
        ),
        'mvm_bedrijf' => array(
            'plural' => 'Bedrijven',
            'singular' => 'Bedrijf',
            'rewrite' => 'encyclopedie/bedrijven',
            'icon' => 'dashicons-store',
        ),
        'mvm_beeld' => array(
            'plural' => 'Beeldbank',
            'singular' => 'Beeldobject',
            'rewrite' => 'encyclopedie/beeldbank',
            'icon' => 'dashicons-format-image',
        ),
        'mvm_bron' => array(
            'plural' => 'Bronnen',
            'singular' => 'Bron',
            'rewrite' => 'encyclopedie/bronnen',
            'icon' => 'dashicons-media-document',
        ),
    );

    /** @var array<string,array<string,mixed>> */
    private const TAXONOMIES = array(
        'mvm_thema' => array(
            'plural' => 'Thema’s',
            'singular' => 'Thema',
            'hierarchical' => true,
            'rewrite' => 'encyclopedie/thema',
        ),
        'mvm_periode' => array(
            'plural' => 'Perioden',
            'singular' => 'Periode',
            'hierarchical' => true,
            'rewrite' => 'encyclopedie/periode',
        ),
        'mvm_status' => array(
            'plural' => 'Historische status',
            'singular' => 'Historische status',
            'hierarchical' => false,
            'rewrite' => 'encyclopedie/status',
        ),
        'mvm_gebied' => array(
            'plural' => 'Geografische gebieden',
            'singular' => 'Geografisch gebied',
            'hierarchical' => true,
            'rewrite' => 'encyclopedie/gebied',
        ),
    );

    public static function init(): void {
        add_action( 'init', array( __CLASS__, 'register_all' ), 0 );
        add_action( 'admin_init', array( __CLASS__, 'maybe_flush_rewrites' ), 1 );
    }

    /** @return array<string,array<string,mixed>> */
    public static function post_types(): array {
        return self::POST_TYPES;
    }

    /** @return array<string,array<string,mixed>> */
    public static function taxonomies(): array {
        return self::TAXONOMIES;
    }

    /** @return string[] */
    public static function post_type_slugs(): array {
        return array_keys( self::POST_TYPES );
    }

    public static function label_for( string $post_type, bool $plural = false ): string {
        if ( ! isset( self::POST_TYPES[ $post_type ] ) ) {
            return $post_type;
        }

        return (string) self::POST_TYPES[ $post_type ][ $plural ? 'plural' : 'singular' ];
    }

    public static function archive_url_for( string $post_type ): string {
        if ( ! isset( self::POST_TYPES[ $post_type ] ) ) {
            return home_url( '/encyclopedie/' );
        }

        return home_url( '/' . trailingslashit( (string) self::POST_TYPES[ $post_type ]['rewrite'] ) );
    }

    public static function register_all(): void {
        self::register_post_types();
        self::register_taxonomies();
    }

    public static function register_post_types(): void {
        foreach ( self::POST_TYPES as $slug => $definition ) {
            if ( post_type_exists( $slug ) ) {
                continue;
            }

            $plural   = (string) $definition['plural'];
            $singular = (string) $definition['singular'];

            register_post_type(
                $slug,
                array(
                    'labels' => array(
                        'name' => $plural,
                        'singular_name' => $singular,
                        'add_new_item' => 'Nieuw ' . strtolower( $singular ) . ' toevoegen',
                        'edit_item' => $singular . ' bewerken',
                        'new_item' => 'Nieuw ' . strtolower( $singular ),
                        'view_item' => $singular . ' bekijken',
                        'search_items' => $plural . ' zoeken',
                        'not_found' => 'Geen resultaten gevonden',
                    ),
                    'public' => true,
                    'publicly_queryable' => true,
                    'show_ui' => true,
                    'show_in_rest' => true,
                    'has_archive' => true,
                    'rewrite' => array(
                        'slug' => (string) $definition['rewrite'],
                        'with_front' => false,
                    ),
                    'menu_icon' => (string) $definition['icon'],
                    'supports' => array( 'title', 'editor', 'excerpt', 'thumbnail', 'revisions', 'author' ),
                    'show_in_nav_menus' => true,
                    'show_in_menu' => 'mvm-encyclopedie',
                    'query_var' => true,
                )
            );
        }
    }

    public static function register_taxonomies(): void {
        $objects = self::post_type_slugs();

        foreach ( self::TAXONOMIES as $slug => $definition ) {
            if ( taxonomy_exists( $slug ) ) {
                continue;
            }

            register_taxonomy(
                $slug,
                $objects,
                array(
                    'labels' => array(
                        'name' => (string) $definition['plural'],
                        'singular_name' => (string) $definition['singular'],
                    ),
                    'public' => true,
                    'publicly_queryable' => true,
                    'show_ui' => true,
                    'show_in_rest' => true,
                    'hierarchical' => (bool) $definition['hierarchical'],
                    'rewrite' => array(
                        'slug' => (string) $definition['rewrite'],
                        'with_front' => false,
                    ),
                    'query_var' => true,
                )
            );
        }
    }

    public static function maybe_flush_rewrites(): void {
        $stored = (string) get_option( 'mvm_encyclopedie_next_rewrite_version', '' );
        if ( MVM_ENCYCLOPEDIE_NEXT_VERSION === $stored ) {
            return;
        }

        self::register_all();
        flush_rewrite_rules( false );
        update_option( 'mvm_encyclopedie_next_rewrite_version', MVM_ENCYCLOPEDIE_NEXT_VERSION, false );
    }
}
