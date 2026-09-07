<?php
/** Nieuwe MvM evenementenagenda. */
get_header();
$past = isset( $_GET['archief'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['archief'] ) );
$query = mvm_e2_events_query( $past );
?>
<main id="content" class="mvm-e2-shell" role="main">
    <section class="mvm-e2-hero">
        <div>
            <span class="mvm-e2-kicker">Agenda van Mierlo</span>
            <h1>Evenementen</h1>
            <p>Wat is er te doen in Mierlo? Bekijk activiteiten op datum, met tijd, locatie en organisator direct zichtbaar.</p>
        </div>
        <div class="mvm-e2-actions">
            <a class="mvm-e2-button primary" href="<?php echo esc_url( home_url( '/organisatoren/' ) ); ?>">Voor organisatoren</a>
        </div>
    </section>

    <div class="mvm-e2-toolbar">
        <nav class="mvm-e2-tabs" aria-label="Agendaweergave">
            <a class="<?php echo $past ? '' : 'is-active'; ?>" href="<?php echo esc_url( home_url( '/evenementen/' ) ); ?>">Aankomend</a>
            <a class="<?php echo $past ? 'is-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'archief', '1', home_url( '/evenementen/' ) ) ); ?>">Voorbij</a>
        </nav>
        <span class="mvm-e2-help">Gesorteerd op startdatum en starttijd.</span>
    </div>

    <?php if ( $query->have_posts() ) : ?>
        <div class="mvm-e2-grid">
            <?php while ( $query->have_posts() ) : $query->the_post(); $event_id = get_the_ID(); ?>
                <article class="mvm-e2-card">
                    <div class="mvm-e2-card-media">
                        <?php if ( has_post_thumbnail() ) : ?>
                            <?php echo get_the_post_thumbnail( $event_id, 'large', array( 'decoding' => 'async', 'alt' => get_the_title() ) ); ?>
                        <?php else : ?>
                            <div aria-hidden="true" style="height:100%;background:linear-gradient(135deg,#dceaf7,#f5f8fb)"></div>
                        <?php endif; ?>
                    </div>
                    <div class="mvm-e2-card-body">
                        <div class="mvm-e2-datebadge"><?php echo esc_html( mvm_e2_date_label( $event_id, true ) ); ?></div>
                        <h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
                        <div class="mvm-e2-meta">
                            <span><strong>Tijd:</strong> <?php echo esc_html( mvm_e2_time_label( $event_id ) ); ?></span>
                            <span><strong>Locatie:</strong> <?php echo esc_html( mvm_e2_location( $event_id ) ); ?></span>
                            <?php if ( mvm_e2_organizer_name( $event_id ) ) : ?>
                                <span><strong>Organisator:</strong> <?php echo esc_html( mvm_e2_organizer_name( $event_id ) ); ?></span>
                            <?php endif; ?>
                        </div>
                        <?php if ( has_excerpt() ) : ?><p><?php echo esc_html( wp_trim_words( get_the_excerpt(), 22 ) ); ?></p><?php endif; ?>
                        <a class="mvm-e2-button" href="<?php the_permalink(); ?>">Bekijk evenement</a>
                    </div>
                </article>
            <?php endwhile; ?>
        </div>
    <?php else : ?>
        <div class="mvm-e2-empty">
            <h2><?php echo $past ? 'Nog geen evenementen in het archief' : 'Geen aankomende evenementen gevonden'; ?></h2>
            <p><?php echo $past ? 'Zodra evenementen voorbij zijn, verschijnen ze hier.' : 'Nieuwe activiteiten verschijnen hier zodra ze zijn gepubliceerd.'; ?></p>
        </div>
    <?php endif; wp_reset_postdata(); ?>
</main>
<?php get_footer();
