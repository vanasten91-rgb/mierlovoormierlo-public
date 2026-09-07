<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit( 1 );
}

/**
 * Integration-only fixtures for the isolated GitHub Actions WordPress install.
 * Never load this file on production.
 */
function mvm_e3_fixture_post( int $id, string $post_type, string $title, string $slug, string $content = '', int $parent = 0, int $menu_order = 0 ): void {
    global $wpdb;

    $now = current_time( 'mysql' );
    $gmt = get_gmt_from_date( $now );

    $wpdb->delete( $wpdb->posts, array( 'ID' => $id ), array( '%d' ) );
    $inserted = $wpdb->insert(
        $wpdb->posts,
        array(
            'ID'                    => $id,
            'post_author'           => 1,
            'post_date'             => $now,
            'post_date_gmt'         => $gmt,
            'post_content'          => $content,
            'post_title'            => $title,
            'post_excerpt'          => '',
            'post_status'           => 'publish',
            'comment_status'        => 'closed',
            'ping_status'           => 'closed',
            'post_password'         => '',
            'post_name'             => $slug,
            'to_ping'               => '',
            'pinged'                => '',
            'post_modified'         => $now,
            'post_modified_gmt'     => $gmt,
            'post_content_filtered' => '',
            'post_parent'           => $parent,
            'guid'                  => home_url( '/?p=' . $id ),
            'menu_order'            => $menu_order,
            'post_type'             => $post_type,
            'post_mime_type'        => '',
            'comment_count'         => 0,
        ),
        array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%s', '%s', '%d' )
    );

    if ( false === $inserted ) {
        throw new RuntimeException( 'Could not insert fixture post ' . $id . ': ' . $wpdb->last_error );
    }

    clean_post_cache( $id );
}

mvm_e3_fixture_post( 1492, 'page', 'Digitale Encyclopedie van Mierlo', 'encyclopedie', '[mvm_public_home]' );
mvm_e3_fixture_post( 6905, 'page', 'Thema’s in de Encyclopedie van Mierlo', 'themas', '[mvm_themas]', 1492 );

$themes = array(
    6906 => array( '01. Oorsprong & archeologie', 'oorsprong-archeologie' ),
    6907 => array( '02. Heren, heerlijkheid & kasteel', 'heren-heerlijkheid-kasteel' ),
    6908 => array( '03. Bestuur, recht & gemeente', 'bestuur-recht-gemeente' ),
    6909 => array( '04. Kerk, geloof & religie', 'kerk-geloof-religie' ),
    6910 => array( '05. Oorlog, bevrijding & herdenken', 'oorlog-bevrijding-herdenken' ),
    6911 => array( '06. Landbouw, kersen & platteland', 'landbouw-kersen-platteland' ),
    6912 => array( '07. Ambachten, textiel & economie', 'ambachten-textiel-economie' ),
    6913 => array( '08. Onderwijs & jeugd', 'onderwijs-jeugd' ),
    6914 => array( '09. Zorg & sociaal leven', 'zorg-sociaal-leven' ),
    6915 => array( '10. Cultuur, tradities & identiteit', 'cultuur-tradities-identiteit' ),
    6916 => array( '11. Verenigingen, sport & vrije tijd', 'verenigingen-sport-vrije-tijd' ),
    6917 => array( '12. Natuur, landschap & water', 'natuur-landschap-water' ),
    6918 => array( '13. Verkeer, infrastructuur & nutsvoorzieningen', 'verkeer-infrastructuur-nutsvoorzieningen' ),
    6919 => array( '14. Gebouwen, monumenten & erfgoed', 'gebouwen-monumenten-erfgoed' ),
    6920 => array( '15. Buurten, straten & historische geografie', 'buurten-straten-geografie' ),
    6921 => array( '16. Mierlo-Hout, Brandevoort & grensontwikkeling', 'mierlo-hout-brandevoort' ),
    6922 => array( '17. Personen & dorpsverhalen', 'personen-dorpsverhalen' ),
    6923 => array( '18. Onderzoek, bronnen & publicaties', 'onderzoek-bronnen-publicaties' ),
);

foreach ( $themes as $id => $definition ) {
    mvm_e3_fixture_post(
        $id,
        'page',
        $definition[0],
        $definition[1],
        '[mvm_thema_page slug="' . $definition[1] . '"]',
        6905,
        $id - 6905
    );
    update_post_meta( $id, '_mvm_theme_page_slug', $definition[1] );
}

// Production's 18 page slugs are editorial groupings, not taxonomy term slugs.
// Create only representative granular terms so the integration suite cannot
// accidentally depend on a non-existent one-to-one term/page mapping.
$granular_terms = array(
    'onderwijs' => 'Onderwijs',
    'straten'   => 'Straten',
);
foreach ( $granular_terms as $term_slug => $term_name ) {
    if ( ! term_exists( $term_slug, 'mvm_thema' ) ) {
        $created = wp_insert_term( $term_name, 'mvm_thema', array( 'slug' => $term_slug ) );
        if ( is_wp_error( $created ) ) {
            throw new RuntimeException( $created->get_error_message() );
        }
    }
}

mvm_e3_fixture_post(
    1977,
    'mvm_encyclopedie',
    'Moderne basisscholen',
    'moderne-basisscholen',
    '<p>De Arkweg en Puur Sang horen bij dit verhaal over modern onderwijs in Mierlo.</p>'
);
mvm_e3_fixture_post(
    2054,
    'mvm_locatie',
    'Arkweg',
    'arkweg',
    '<p>De Arkweg is een historische locatie in Mierlo.</p>'
);
mvm_e3_fixture_post(
    2055,
    'mvm_locatie',
    'Verborgen locatie',
    'verborgen-locatie',
    '<p>Dit dossier mag niet op een publieke encyclopedie-oppervlakte verschijnen.</p>'
);

update_post_meta( 1977, '_mvm_relations', array( 2054, 2055 ) );
update_post_meta( 2054, '_mvm_aliases', array( 'Arkweg', 'de Arkweg' ) );
update_post_meta( 2055, '_mvm_public_hidden', '1' );
wp_set_object_terms( 1977, 'onderwijs', 'mvm_thema', false );
wp_set_object_terms( 2054, 'straten', 'mvm_thema', false );

for ( $i = 0; $i < 30; ++$i ) {
    $id = 7100 + $i;
    mvm_e3_fixture_post(
        $id,
        'mvm_persoon',
        sprintf( 'Persoon %02d', $i + 1 ),
        sprintf( 'persoon-%02d', $i + 1 ),
        '<p>Testpersoon uit de geïsoleerde encyclopedie-integratietest.</p>'
    );
}

mvm_e3_fixture_post( 7130, 'mvm_persoon', 'Verborgen persoon', 'verborgen-persoon', '<p>Niet publiek.</p>' );
update_post_meta( 7130, '_mvm_public_hidden', '1' );

mvm_e3_fixture_post( 7200, 'mvm_gebeurtenis', 'Bevrijding van Mierlo', 'bevrijding-van-mierlo', '<p>Een gebeurtenis uit de testtijdlijn.</p>' );
update_post_meta( 7200, '_mvm_start_date', '1944-09-22' );
mvm_e3_fixture_post( 7201, 'mvm_gebouw', 'Testgebouw', 'testgebouw', '<p>Gebouwfixture.</p>' );
mvm_e3_fixture_post( 7202, 'mvm_vereniging', 'Testvereniging', 'testvereniging', '<p>Verenigingsfixture.</p>' );
mvm_e3_fixture_post( 7203, 'mvm_bedrijf', 'Testbedrijf', 'testbedrijf', '<p>Bedrijfsfixture.</p>' );
mvm_e3_fixture_post( 7204, 'mvm_beeld', 'Testbeeld', 'testbeeld', '<p>Beeldfixture.</p>' );
mvm_e3_fixture_post( 7205, 'mvm_bron', 'Testbron', 'testbron', '<p>Bronfixture.</p>' );

flush_rewrite_rules( false );

echo "Encyclopedie Next fixtures: OK\n";
