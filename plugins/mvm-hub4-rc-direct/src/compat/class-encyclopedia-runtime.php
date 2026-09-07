<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Read-only compatibility registration for the canonical Mierlo Encyclopedie
 * content model.
 *
 * Hub 3 used to load Mierlo Encyclopedie 2.94.0 internally. During the Hub 4
 * consolidation that runtime disappeared while the database content and public
 * links remained. This class restores only the WordPress registration layer
 * required to resolve those existing objects. It deliberately does not import,
 * reconcile, seed, rewrite content, or mutate encyclopedia post/meta data.
 *
 * If the canonical MVM_Encyclopedie runtime is loaded, this compatibility layer
 * becomes dormant and lets the canonical module own its registrations.
 */
final class MvM_Hub4_Encyclopedia_Runtime {
    private const REWRITE_VERSION = '2.94.0-hub4-compat1';
    private const REWRITE_OPTION  = 'mvm_hub4_encyclopedia_rewrite_version';

    /** @var array<string,array{0:string,1:string,2:string,3:string}> */
    private const POST_TYPES = array(
        'mvm_encyclopedie' => array( 'Encyclopedie', 'Encyclopedie-artikel', 'encyclopedie/artikel', 'dashicons-book-alt' ),
        'mvm_persoon'      => array( 'Personen', 'Persoon', 'encyclopedie/personen', 'dashicons-id' ),
        'mvm_locatie'      => array( 'Locaties', 'Locatie', 'encyclopedie/locaties', 'dashicons-location-alt' ),
        'mvm_gebouw'       => array( 'Gebouwen', 'Gebouw', 'encyclopedie/gebouwen', 'dashicons-building' ),
        'mvm_gebeurtenis'  => array( 'Gebeurtenissen', 'Gebeurtenis', 'encyclopedie/gebeurtenissen', 'dashicons-calendar-alt' ),
        'mvm_vereniging'   => array( 'Verenigingen', 'Vereniging', 'encyclopedie/verenigingen', 'dashicons-groups' ),
        'mvm_bedrijf'      => array( 'Bedrijven', 'Bedrijf', 'encyclopedie/bedrijven', 'dashicons-store' ),
        'mvm_beeld'        => array( 'Beeldbank', 'Beeldobject', 'encyclopedie/beeldbank', 'dashicons-format-image' ),
        'mvm_bron'         => array( 'Bronnen', 'Bron', 'encyclopedie/bronnen', 'dashicons-media-document' ),
    );

    /** @var array<string,array{0:string,1:string,2:bool,3:string}> */
    private const TAXONOMIES = array(
        'mvm_thema'   => array( 'Thema’s', 'Thema', true, 'encyclopedie/thema' ),
        'mvm_periode' => array( 'Perioden', 'Periode', true, 'encyclopedie/periode' ),
        'mvm_status'  => array( 'Historische status', 'Historische status', false, 'encyclopedie/status' ),
        'mvm_gebied'  => array( 'Geografische gebieden', 'Geografisch gebied', true, 'encyclopedie/gebied' ),
    );

    public static function init(): void {
        add_action( 'init', array( __CLASS__, 'register_content_types' ), 0 );
        add_action( 'init', array( __CLASS__, 'register_taxonomies' ), 0 );
        add_action( 'admin_init', array( __CLASS__, 'maybe_flush_rewrite_rules' ), 1 );
    }

    public static function register_content_types(): void {
        if ( class_exists( 'MVM_Encyclopedie', false ) ) {
            return;
        }

        foreach ( self::POST_TYPES as $slug => $data ) {
            if ( post_type_exists( $slug ) ) {
                continue;
            }

            list( $plural, $singular, $rewrite, $icon ) = $data;
            register_post_type(
                $slug,
                array(
                    'labels' => array(
                        'name'          => $plural,
                        'singular_name' => $singular,
                        'add_new_item'  => 'Nieuw ' . strtolower( $singular ) . ' toevoegen',
                        'edit_item'     => $singular . ' bewerken',
                        'new_item'      => 'Nieuw ' . strtolower( $singular ),
                        'view_item'     => $singular . ' bekijken',
                        'search_items'  => $plural . ' zoeken',
                        'not_found'     => 'Geen resultaten gevonden',
                    ),
                    'public'       => true,
                    'show_in_rest' => true,
                    'has_archive'  => true,
                    'rewrite'      => array(
                        'slug'       => $rewrite,
                        'with_front' => false,
                    ),
                    'menu_icon'    => $icon,
                    'supports'     => array( 'title', 'editor', 'excerpt', 'thumbnail', 'revisions', 'author' ),
                    'show_in_menu' => 'mvm-encyclopedie',
                )
            );
        }
    }

    public static function register_taxonomies(): void {
        if ( class_exists( 'MVM_Encyclopedie', false ) ) {
            return;
        }

        $objects = array_keys( self::POST_TYPES );
        foreach ( self::TAXONOMIES as $slug => $data ) {
            if ( taxonomy_exists( $slug ) ) {
                continue;
            }

            list( $plural, $singular, $hierarchical, $rewrite ) = $data;
            register_taxonomy(
                $slug,
                $objects,
                array(
                    'labels' => array(
                        'name'          => $plural,
                        'singular_name' => $singular,
                    ),
                    'public'       => true,
                    'show_in_rest' => true,
                    'hierarchical' => $hierarchical,
                    'rewrite'      => array(
                        'slug'       => $rewrite,
                        'with_front' => false,
                    ),
                )
            );
        }
    }

    public static function activate(): void {
        if ( class_exists( 'MVM_Encyclopedie', false ) ) {
            return;
        }

        self::register_content_types();
        self::register_taxonomies();
        flush_rewrite_rules( false );
        update_option( self::REWRITE_OPTION, self::REWRITE_VERSION, false );
    }

    public static function maybe_flush_rewrite_rules(): void {
        if ( class_exists( 'MVM_Encyclopedie', false ) ) {
            return;
        }

        if ( self::REWRITE_VERSION === (string) get_option( self::REWRITE_OPTION, '' ) ) {
            return;
        }

        // The init hooks have already registered the compatibility content model.
        // Flush only the rewrite table; encyclopedia posts/meta are never modified.
        flush_rewrite_rules( false );
        update_option( self::REWRITE_OPTION, self::REWRITE_VERSION, false );
    }
}
