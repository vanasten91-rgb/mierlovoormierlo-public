<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit( 1 );
}

function mvm_e3_assert( bool $condition, string $message ): void {
    if ( ! $condition ) {
        throw new RuntimeException( $message );
    }
}

mvm_e3_assert( class_exists( 'MVM_Encyclopedie', false ), 'Canonical MVM_Encyclopedie runtime marker missing.' );
mvm_e3_assert( class_exists( 'MVM_Encyclopedie_Next_Visibility', false ), 'Archive visibility guard missing.' );

$expected_post_types = array(
    'mvm_encyclopedie' => 'encyclopedie/artikel',
    'mvm_persoon'      => 'encyclopedie/personen',
    'mvm_locatie'      => 'encyclopedie/locaties',
    'mvm_gebouw'       => 'encyclopedie/gebouwen',
    'mvm_gebeurtenis'  => 'encyclopedie/gebeurtenissen',
    'mvm_vereniging'   => 'encyclopedie/verenigingen',
    'mvm_bedrijf'      => 'encyclopedie/bedrijven',
    'mvm_beeld'        => 'encyclopedie/beeldbank',
    'mvm_bron'         => 'encyclopedie/bronnen',
);

foreach ( $expected_post_types as $post_type => $rewrite ) {
    $object = get_post_type_object( $post_type );
    mvm_e3_assert( $object instanceof WP_Post_Type, 'Missing post type: ' . $post_type );
    mvm_e3_assert( isset( $object->rewrite['slug'] ) && $rewrite === $object->rewrite['slug'], 'Rewrite drift for ' . $post_type );
    mvm_e3_assert( true === $object->show_in_rest, 'REST registration missing for ' . $post_type );
    mvm_e3_assert( 'mvm-encyclopedie' === $object->show_in_menu, 'Admin menu placement drift for ' . $post_type );
}

$expected_taxonomies = array(
    'mvm_thema'   => 'encyclopedie/thema',
    'mvm_periode' => 'encyclopedie/periode',
    'mvm_status'  => 'encyclopedie/status',
    'mvm_gebied'  => 'encyclopedie/gebied',
);

foreach ( $expected_taxonomies as $taxonomy => $rewrite ) {
    $object = get_taxonomy( $taxonomy );
    mvm_e3_assert( $object instanceof WP_Taxonomy, 'Missing taxonomy: ' . $taxonomy );
    mvm_e3_assert( isset( $object->rewrite['slug'] ) && $rewrite === $object->rewrite['slug'], 'Rewrite drift for ' . $taxonomy );
}

mvm_e3_assert( 1492 === (int) get_page_by_path( 'encyclopedie', OBJECT, 'page' )->ID, 'Canonical encyclopedia page ID changed in fixture.' );
mvm_e3_assert( 6905 === (int) get_page_by_path( 'encyclopedie/themas', OBJECT, 'page' )->ID, 'Canonical themes page ID changed in fixture.' );

$arkweg = get_post( 2054 );
$schools = get_post( 1977 );
mvm_e3_assert( $arkweg instanceof WP_Post && $schools instanceof WP_Post, 'Canonical Smart Link fixtures missing.' );
mvm_e3_assert( home_url( '/encyclopedie/locaties/arkweg/' ) === get_permalink( $arkweg ), 'Arkweg canonical permalink changed.' );
mvm_e3_assert( home_url( '/encyclopedie/artikel/moderne-basisscholen/' ) === get_permalink( $schools ), 'Moderne basisscholen canonical permalink changed.' );

$people = MVM_Encyclopedie_Next_Query::search(
    array(
        'post_type' => 'mvm_persoon',
        'per_page'  => 100,
        'page'      => 1,
    )
);
mvm_e3_assert( 24 === $people->post_count, 'Public query did not enforce the 24-item upper bound.' );
mvm_e3_assert( 30 === (int) $people->found_posts, 'Hidden person leaked into public person result count.' );
mvm_e3_assert( 2 === (int) $people->max_num_pages, 'Person pagination did not split 30 public records into two pages.' );
mvm_e3_assert( ! in_array( 7130, wp_list_pluck( $people->posts, 'ID' ), true ), 'Hidden person leaked into bounded public search.' );

$public_counts = MVM_Encyclopedie_Next_Query::counts();
mvm_e3_assert( 30 === (int) $public_counts['mvm_persoon'], 'Homepage collection count included a hidden person.' );
mvm_e3_assert( 1 === (int) $public_counts['mvm_locatie'], 'Homepage collection count included a hidden location.' );

$home = do_shortcode( '[mvm_public_home]' );
mvm_e3_assert( false !== strpos( $home, 'data-mvm-encyclopedie-root' ), 'Homepage shortcode did not render the new root.' );
mvm_e3_assert( 9 === substr_count( $home, 'mvm-e3-collection-card' ), 'Homepage did not expose exactly nine canonical collections.' );
mvm_e3_assert( false !== strpos( $home, 'Momenten uit de geschiedenis' ), 'Compact timeline did not render.' );
mvm_e3_assert( false === strpos( $home, 'Verborgen locatie' ), 'Hidden location leaked into homepage discovery.' );

$themes = do_shortcode( '[mvm_themas]' );
mvm_e3_assert( 18 === substr_count( $themes, 'mvm-e3-theme-card' ), 'Themes overview must keep the 18 existing main theme pages.' );

$_GET['q'] = 'Arkweg';
$filtered_home = do_shortcode( '[mvm_public_home]' );
unset( $_GET['q'] );
mvm_e3_assert( false !== strpos( $filtered_home, 'Arkweg' ), 'Server-side encyclopedia search did not find Arkweg.' );
mvm_e3_assert( false === strpos( $filtered_home, 'Verborgen locatie' ), 'Hidden location leaked into server-side search.' );

$search_request = new WP_REST_Request( 'GET', '/mvm-encyclopedie/v1/search' );
$search_request->set_param( 'q', 'Persoon' );
$search_request->set_param( 'type', 'mvm_persoon' );
$search_request->set_param( 'per_page', 100 );
$search_response = rest_do_request( $search_request );
mvm_e3_assert( 200 === $search_response->get_status(), 'REST search failed.' );
$search_data = $search_response->get_data();
mvm_e3_assert( isset( $search_data['items'] ) && 24 === count( $search_data['items'] ), 'REST search exceeded or missed the 24-item bound.' );
mvm_e3_assert( isset( $search_data['total'] ) && 30 === (int) $search_data['total'], 'REST search leaked hidden records into total.' );

$relations_request = new WP_REST_Request( 'GET', '/mvm-encyclopedie/v1/items/1977/relations' );
$relations_request->set_param( 'per_page', 24 );
$relations_response = rest_do_request( $relations_request );
mvm_e3_assert( 200 === $relations_response->get_status(), 'Relations REST endpoint failed.' );
$relations_data = $relations_response->get_data();
mvm_e3_assert( 1 === (int) $relations_data['total'], 'Hidden relation target was not filtered.' );
mvm_e3_assert( 1 === count( $relations_data['items'] ) && 'Arkweg' === $relations_data['items'][0]['title'], 'Visible relation target was not preserved.' );

$hidden_relations_request = new WP_REST_Request( 'GET', '/mvm-encyclopedie/v1/items/2055/relations' );
$hidden_relations_response = rest_do_request( $hidden_relations_request );
mvm_e3_assert( 404 === $hidden_relations_response->get_status(), 'Hidden dossier exposed its relation REST surface.' );

// Exercise the hotlink filter under a real main singular query context.
global $wp_query, $wp_the_query, $post;
$old_wp_query = $wp_query;
$old_wp_the_query = $wp_the_query;
$old_post = $post;
$singular_query = new WP_Query(
    array(
        'post_type' => 'mvm_encyclopedie',
        'p'         => 1977,
    )
);
$wp_query = $singular_query;
$wp_the_query = $singular_query;
$hotlinked = '';
if ( $singular_query->have_posts() ) {
    $singular_query->the_post();
    $hotlinked = apply_filters( 'the_content', (string) get_the_content() );
}
wp_reset_postdata();
$wp_query = $old_wp_query;
$wp_the_query = $old_wp_the_query;
$post = $old_post;

mvm_e3_assert( false !== strpos( $hotlinked, 'class="mvm-e3-hotlink"' ), 'Bounded hotlinking did not create an Arkweg link.' );
mvm_e3_assert( false !== strpos( $hotlinked, '/encyclopedie/locaties/arkweg/' ), 'Hotlink did not use the canonical Arkweg permalink.' );
mvm_e3_assert( false === strpos( $hotlinked, 'verborgen-locatie' ), 'Hidden relation became a hotlink.' );

echo "Encyclopedie Next WordPress smoke: OK\n";
