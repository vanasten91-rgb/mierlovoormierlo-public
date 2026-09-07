<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

get_header();

while ( have_posts() ) :
    the_post();
    $post_object = get_post();
    if ( ! $post_object instanceof WP_Post ) {
        continue;
    }

    $summary = MVM_Encyclopedie_Next_Query::summary( $post_object );
    $facts = MVM_Encyclopedie_Next_Frontend::facts( $post_object );
    $taxonomy_groups = MVM_Encyclopedie_Next_Frontend::taxonomy_groups( $post_object );
    $relation_ids = MVM_Encyclopedie_Next_Query::relation_ids( (int) $post_object->ID );
    $relations = MVM_Encyclopedie_Next_Query::relations( (int) $post_object->ID, 0, 12 );
    $relation_total = count( $relation_ids );
    $thumbnail = get_the_post_thumbnail_url( $post_object, 'large' );
    $license_url = 'mvm_beeld' === $post_object->post_type ? trim( (string) get_post_meta( $post_object->ID, '_mvm_media_license_url', true ) ) : '';
    ?>
    <main id="primary" class="mvm-encyclopedie-next mvm-e3-page mvm-e3-dossier">
        <?php echo MVM_Encyclopedie_Next_Frontend::render_breadcrumbs( $post_object ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

        <header class="mvm-e3-dossier-hero">
            <div class="mvm-e3-dossier-heading">
                <span class="mvm-e3-type"><?php echo esc_html( (string) $summary['type_label'] ); ?></span>
                <h1><?php the_title(); ?></h1>
                <?php if ( ! empty( $summary['excerpt'] ) ) : ?>
                    <p class="mvm-e3-lede"><?php echo esc_html( (string) $summary['excerpt'] ); ?></p>
                <?php endif; ?>
            </div>
            <?php if ( $thumbnail ) : ?>
                <figure class="mvm-e3-dossier-media">
                    <img src="<?php echo esc_url( $thumbnail ); ?>" alt="<?php echo esc_attr( get_the_title() ); ?>" loading="eager" decoding="async" fetchpriority="high">
                </figure>
            <?php endif; ?>
        </header>

        <div class="mvm-e3-dossier-layout">
            <article class="mvm-e3-article">
                <div class="mvm-e3-prose">
                    <?php the_content(); ?>
                </div>

                <?php if ( 'mvm_beeld' === $post_object->post_type && '' !== $license_url ) : ?>
                    <p class="mvm-e3-license"><a href="<?php echo esc_url( $license_url ); ?>" rel="noopener noreferrer">Bekijk licentie- of rechteninformatie</a></p>
                <?php endif; ?>
            </article>

            <?php if ( $facts || $taxonomy_groups ) : ?>
                <aside class="mvm-e3-facts" aria-label="Dossierinformatie">
                    <?php if ( $facts ) : ?>
                        <section>
                            <h2>In het kort</h2>
                            <dl>
                                <?php foreach ( $facts as $label => $value ) : ?>
                                    <div><dt><?php echo esc_html( $label ); ?></dt><dd><?php echo esc_html( $value ); ?></dd></div>
                                <?php endforeach; ?>
                            </dl>
                        </section>
                    <?php endif; ?>

                    <?php foreach ( $taxonomy_groups as $taxonomy => $terms ) : ?>
                        <?php $tax_object = get_taxonomy( $taxonomy ); ?>
                        <section>
                            <h2><?php echo esc_html( $tax_object && isset( $tax_object->labels->name ) ? $tax_object->labels->name : $taxonomy ); ?></h2>
                            <div class="mvm-e3-chips">
                                <?php foreach ( $terms as $term ) : ?>
                                    <?php $term_link = get_term_link( $term ); ?>
                                    <?php if ( ! is_wp_error( $term_link ) ) : ?><a href="<?php echo esc_url( $term_link ); ?>"><?php echo esc_html( $term->name ); ?></a><?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        </section>
                    <?php endforeach; ?>
                </aside>
            <?php endif; ?>
        </div>

        <?php if ( $relations ) : ?>
            <section class="mvm-e3-section mvm-e3-relations" aria-labelledby="mvm-e3-relations-title">
                <div class="mvm-e3-section-head">
                    <div>
                        <p class="mvm-e3-kicker">Verbanden</p>
                        <h2 id="mvm-e3-relations-title">Verder ontdekken</h2>
                    </div>
                    <span class="mvm-e3-count"><?php echo esc_html( number_format_i18n( $relation_total ) ); ?> gekoppeld</span>
                </div>
                <div class="mvm-e3-card-grid" data-mvm-relations-grid>
                    <?php foreach ( $relations as $item ) : ?>
                        <?php echo MVM_Encyclopedie_Next_Frontend::render_card( $item ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <?php endforeach; ?>
                </div>
                <?php if ( $relation_total > count( $relations ) ) : ?>
                    <button class="mvm-e3-load-more" type="button" data-mvm-relations-more data-post-id="<?php echo esc_attr( (string) $post_object->ID ); ?>" data-offset="<?php echo esc_attr( (string) count( $relations ) ); ?>">Meer verbanden laden</button>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <nav class="mvm-e3-dossier-footer" aria-label="Encyclopedienavigatie">
            <a href="<?php echo esc_url( MVM_Encyclopedie_Next_Content_Model::archive_url_for( (string) $post_object->post_type ) ); ?>">Terug naar <?php echo esc_html( strtolower( MVM_Encyclopedie_Next_Content_Model::label_for( (string) $post_object->post_type, true ) ) ); ?></a>
            <a href="<?php echo esc_url( home_url( '/encyclopedie/' ) ); ?>">Encyclopedie-overzicht</a>
        </nav>
    </main>
    <?php
endwhile;

get_footer();
