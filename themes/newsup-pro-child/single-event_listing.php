<?php
/** Nieuwe MvM eventdetailweergave. */
get_header();
while ( have_posts() ) :
    the_post();
    $event_id = get_the_ID();
    $organizer = mvm_e2_organizer_name( $event_id );
    $price = trim( (string) mvm_e2_meta( $event_id, '_event_ticket_price' ) );
    $registration = trim( (string) mvm_e2_meta( $event_id, '_registration' ) );
    ?>
    <main id="content" class="mvm-e2-event" role="main">
        <article <?php post_class( 'mvm-e2-event-article' ); ?>>
            <?php if ( has_post_thumbnail() ) : ?>
                <figure class="mvm-e2-event-hero">
                    <img src="<?php echo esc_url( get_the_post_thumbnail_url( $event_id, 'full' ) ); ?>" alt="<?php echo esc_attr( get_the_title() ); ?>" decoding="async" fetchpriority="high">
                </figure>
            <?php endif; ?>

            <header class="mvm-e2-event-head">
                <span class="mvm-e2-kicker" style="color:#1966AE;-webkit-text-fill-color:#1966AE">Evenement</span>
                <h1><?php the_title(); ?></h1>
                <div class="mvm-e2-facts" aria-label="Praktische informatie">
                    <div class="mvm-e2-fact"><span>Datum</span><strong><?php echo esc_html( mvm_e2_date_label( $event_id ) ); ?></strong></div>
                    <div class="mvm-e2-fact"><span>Tijd</span><strong><?php echo esc_html( mvm_e2_time_label( $event_id ) ); ?></strong></div>
                    <div class="mvm-e2-fact"><span>Locatie</span><strong><?php echo esc_html( mvm_e2_location( $event_id ) ); ?></strong></div>
                    <div class="mvm-e2-fact"><span>Organisator</span><strong><?php echo esc_html( $organizer ?: 'Niet vermeld' ); ?></strong></div>
                    <?php if ( $price ) : ?><div class="mvm-e2-fact"><span>Prijs</span><strong><?php echo esc_html( $price ); ?></strong></div><?php endif; ?>
                    <?php if ( $registration ) : ?><div class="mvm-e2-fact"><span>Meer informatie</span><strong><a href="<?php echo esc_url( $registration ); ?>" target="_blank" rel="noopener noreferrer">Website / aanmelden</a></strong></div><?php endif; ?>
                </div>
            </header>

            <div class="mvm-e2-content entry-content">
                <?php
                // Render alleen de opgeslagen evenementinhoud. WP Event Manager's
                // legacy single-wrapper (met o.a. een tweede deelblok) wordt bewust
                // niet via the_content() opnieuw ingeladen.
                $raw_content = get_post_field( 'post_content', $event_id );
                $clean_content = do_blocks( $raw_content );
                $clean_content = shortcode_unautop( $clean_content );
                $clean_content = do_shortcode( $clean_content );
                if ( isset( $GLOBALS['wp_embed'] ) && is_object( $GLOBALS['wp_embed'] ) ) {
                    $clean_content = $GLOBALS['wp_embed']->autoembed( $clean_content );
                }
                echo $clean_content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core blocks/oEmbed + portal input is sanitized on save.
                ?>
            </div>

            <?php if ( has_action( 'single_event_listing_button_start' ) ) : ?>
                <section class="mvm-e2-rsvp" aria-label="Aanwezigheid">
                    <?php do_action( 'single_event_listing_button_start' ); ?>
                </section>
            <?php endif; ?>

            <?php echo mvm_e2_share_block( $event_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

            <section class="mvm-e2-comments" aria-label="Reacties">
                <?php
                // PeepSo BlogPosts/WP Event Manager integratie neemt hier de reactie-interface over.
                comments_template();
                ?>
            </section>

            <div class="mvm-e2-toolbar" style="margin-top:34px">
                <a class="mvm-e2-button" href="<?php echo esc_url( home_url( '/evenementen/' ) ); ?>">← Terug naar evenementen</a>
            </div>
        </article>
    </main>
<?php endwhile; ?>
<?php get_footer();
