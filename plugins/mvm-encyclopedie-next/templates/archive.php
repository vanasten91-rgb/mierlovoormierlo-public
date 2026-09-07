<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

get_header();

global $wp_query;

$is_taxonomy = is_tax( array_keys( MVM_Encyclopedie_Next_Content_Model::taxonomies() ) );
$title = $is_taxonomy ? single_term_title( '', false ) : post_type_archive_title( '', false );
$description = $is_taxonomy ? trim( wp_strip_all_tags( term_description() ) ) : '';
$current = max( 1, (int) get_query_var( 'paged', 1 ) );
$total = isset( $wp_query->found_posts ) ? (int) $wp_query->found_posts : 0;
$max_pages = isset( $wp_query->max_num_pages ) ? (int) $wp_query->max_num_pages : 1;
?>
<main id="primary" class="mvm-encyclopedie-next mvm-e3-page">
    <?php echo MVM_Encyclopedie_Next_Frontend::render_breadcrumbs(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

    <section class="mvm-e3-hero mvm-e3-hero-compact">
        <div class="mvm-e3-hero-copy">
            <p class="mvm-e3-kicker">Digitale Encyclopedie van Mierlo</p>
            <h1><?php echo esc_html( $title ); ?></h1>
            <?php if ( '' !== $description ) : ?>
                <p><?php echo esc_html( $description ); ?></p>
            <?php else : ?>
                <p>Verken deze collectie in compacte pagina’s. Alleen de dossiers op deze pagina worden geladen.</p>
            <?php endif; ?>
        </div>
    </section>

    <nav class="mvm-e3-subnav" aria-label="Encyclopediecollecties">
        <a href="<?php echo esc_url( home_url( '/encyclopedie/' ) ); ?>">Overzicht</a>
        <?php foreach ( MVM_Encyclopedie_Next_Content_Model::post_types() as $slug => $definition ) : ?>
            <a href="<?php echo esc_url( MVM_Encyclopedie_Next_Content_Model::archive_url_for( $slug ) ); ?>" <?php echo is_post_type_archive( $slug ) ? 'aria-current="page"' : ''; ?>><?php echo esc_html( (string) $definition['plural'] ); ?></a>
        <?php endforeach; ?>
    </nav>

    <section class="mvm-e3-section" aria-labelledby="mvm-e3-archive-results">
        <div class="mvm-e3-section-head">
            <div>
                <p class="mvm-e3-kicker">Collectie</p>
                <h2 id="mvm-e3-archive-results">Dossiers</h2>
            </div>
            <span class="mvm-e3-count"><?php echo esc_html( number_format_i18n( $total ) ); ?> gevonden</span>
        </div>

        <?php echo MVM_Encyclopedie_Next_Frontend::render_query_grid( $wp_query ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

        <?php if ( $max_pages > 1 ) : ?>
            <?php
            $big = 999999999;
            $links = paginate_links(
                array(
                    'base' => str_replace( (string) $big, '%#%', esc_url_raw( get_pagenum_link( $big ) ) ),
                    'format' => '?paged=%#%',
                    'current' => $current,
                    'total' => $max_pages,
                    'type' => 'list',
                    'prev_text' => 'Vorige',
                    'next_text' => 'Volgende',
                )
            );
            ?>
            <?php if ( $links ) : ?><nav class="mvm-e3-pagination" aria-label="Paginering"><?php echo wp_kses_post( $links ); ?></nav><?php endif; ?>
        <?php endif; ?>
    </section>
</main>
<?php
get_footer();
