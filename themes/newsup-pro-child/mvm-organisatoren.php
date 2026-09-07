<?php
/** MvM Organisatorenhub — los van de staf-Hub. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
get_header();

if ( ! is_user_logged_in() ) :
    ?>
    <main id="content" class="mvm-e2-portal" role="main">
        <section class="mvm-e2-login">
            <span class="mvm-e2-kicker" style="color:#1966AE;-webkit-text-fill-color:#1966AE">Organisatoren</span>
            <h1>Organisatorenhub</h1>
            <p>Log in om je eigen evenementen in te dienen en bij te houden.</p>
            <a class="mvm-e2-button primary" href="<?php echo esc_url( wp_login_url( home_url( '/organisatoren/' ) ) ); ?>">Inloggen</a>
        </section>
    </main>
    <?php
    get_footer();
    return;
endif;

if ( ! mvm_e2_is_organizer() ) :
    ?>
    <main id="content" class="mvm-e2-portal" role="main">
        <section class="mvm-e2-login">
            <h1>Geen organisatorentoegang</h1>
            <p>Dit account heeft geen toegang tot de Organisatorenhub.</p>
            <a class="mvm-e2-button" href="<?php echo esc_url( home_url( '/' ) ); ?>">Naar de startpagina</a>
        </section>
    </main>
    <?php
    get_footer();
    return;
endif;

$edit_id = isset( $_GET['bewerken'] ) ? absint( $_GET['bewerken'] ) : 0;
$editing = $edit_id && mvm_e2_owned_post( $edit_id, 'event_listing' ) ? get_post( $edit_id ) : null;
$event_args = array(
    'post_type' => 'event_listing',
    'post_status' => array( 'publish', 'pending', 'draft', 'expired' ),
    'posts_per_page' => 100,
    'meta_key' => '_event_start_date',
    'orderby' => array( 'meta_value' => 'ASC', 'title' => 'ASC' ),
    'order' => 'ASC',
);
if ( ! mvm_e2_is_staff() ) {
    $event_args['author'] = get_current_user_id();
}
$event_query = new WP_Query( $event_args );
$status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
?>
<main id="content" class="mvm-e2-portal" role="main">
    <header class="mvm-e2-portal-head">
        <div>
            <span class="mvm-e2-kicker">Eigen omgeving</span>
            <h1>Organisatorenhub</h1>
            <p>Plaats en onderhoud je eigen activiteiten. Nieuwe evenementen gaan eerst ter controle; bestaande gepubliceerde evenementen kun je zelf actualiseren.</p>
        </div>
        <div class="mvm-e2-actions">
            <a class="mvm-e2-button" href="<?php echo esc_url( home_url( '/evenementen/' ) ); ?>">Bekijk agenda</a>
            <a class="mvm-e2-button" href="<?php echo esc_url( wp_logout_url( home_url( '/' ) ) ); ?>">Uitloggen</a>
        </div>
    </header>

    <?php if ( in_array( $status, array( 'ingediend', 'bijgewerkt' ), true ) ) : ?>
        <div class="mvm-e2-notice"><?php echo 'ingediend' === $status ? 'Je evenement is ingediend en staat klaar voor controle.' : 'Je evenement is bijgewerkt.'; ?></div>
    <?php endif; ?>

    <div class="mvm-e2-portal-grid">
        <section class="mvm-e2-panel">
            <h2>Mijn evenementen</h2>
            <div class="mvm-e2-list">
                <?php if ( $event_query->have_posts() ) : ?>
                    <?php while ( $event_query->have_posts() ) : $event_query->the_post(); $id = get_the_ID(); ?>
                        <article class="mvm-e2-list-item">
                            <div>
                                <h3><?php the_title(); ?></h3>
                                <div class="mvm-e2-meta"><span><?php echo esc_html( mvm_e2_date_label( $id ) ); ?> · <?php echo esc_html( mvm_e2_time_label( $id ) ); ?></span><span><?php echo esc_html( mvm_e2_location( $id ) ); ?></span></div>
                                <span class="mvm-e2-status <?php echo esc_attr( get_post_status( $id ) ); ?>"><?php echo esc_html( mvm_e2_status_label( get_post_status( $id ) ) ); ?></span>
                            </div>
                            <div class="mvm-e2-actions">
                                <a class="mvm-e2-button" href="<?php echo esc_url( add_query_arg( 'bewerken', $id, home_url( '/organisatoren/' ) ) ); ?>">Bewerken</a>
                                <?php if ( 'publish' === get_post_status( $id ) ) : ?><a class="mvm-e2-button" href="<?php echo esc_url( get_permalink( $id ) ); ?>">Bekijken</a><?php endif; ?>
                            </div>
                        </article>
                    <?php endwhile; wp_reset_postdata(); ?>
                <?php else : ?>
                    <div class="mvm-e2-empty"><p>Je hebt nog geen evenementen.</p></div>
                <?php endif; ?>
            </div>
        </section>

        <aside class="mvm-e2-panel">
            <h2><?php echo $editing ? 'Evenement bewerken' : 'Evenement toevoegen'; ?></h2>
            <form class="mvm-e2-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
                <input type="hidden" name="action" value="mvm_e2_save_event">
                <input type="hidden" name="event_id" value="<?php echo $editing ? (int) $editing->ID : 0; ?>">
                <?php wp_nonce_field( 'mvm_e2_save_event', 'mvm_nonce' ); ?>

                <label>Titel <input required type="text" name="event_title" value="<?php echo esc_attr( $editing ? $editing->post_title : '' ); ?>"></label>
                <label>Omschrijving <textarea name="event_description"><?php echo esc_textarea( $editing ? $editing->post_content : '' ); ?></textarea></label>
                <label>Organisator <input type="text" name="organizer_name" value="<?php echo esc_attr( $editing ? get_post_meta( $editing->ID, '_mvm_organizer_name', true ) : '' ); ?>"></label>
                <div class="mvm-e2-form-grid">
                    <label>Startdatum <input required type="date" name="start_date" value="<?php echo esc_attr( $editing ? substr( (string) get_post_meta( $editing->ID, '_event_start_date', true ), 0, 10 ) : '' ); ?>"></label>
                    <label>Starttijd <input type="time" name="start_time" value="<?php echo esc_attr( $editing ? (string) get_post_meta( $editing->ID, '_event_start_time', true ) : '' ); ?>"></label>
                    <label>Einddatum <input type="date" name="end_date" value="<?php echo esc_attr( $editing ? substr( (string) get_post_meta( $editing->ID, '_event_end_date', true ), 0, 10 ) : '' ); ?>"></label>
                    <label>Eindtijd <input type="time" name="end_time" value="<?php echo esc_attr( $editing ? (string) get_post_meta( $editing->ID, '_event_end_time', true ) : '' ); ?>"></label>
                </div>
                <label style="display:flex;grid-template-columns:auto 1fr;align-items:center;gap:9px"><input style="width:auto" type="checkbox" name="all_day" value="1" <?php checked( $editing ? get_post_meta( $editing->ID, '_mvm_all_day', true ) : '', '1' ); ?>> Hele dag</label>
                <label>Locatienaam <input type="text" name="event_location" value="<?php echo esc_attr( $editing ? get_post_meta( $editing->ID, '_event_location', true ) : '' ); ?>"></label>
                <label>Adres <input type="text" name="event_address" value="<?php echo esc_attr( $editing ? get_post_meta( $editing->ID, '_event_address', true ) : '' ); ?>"></label>
                <div class="mvm-e2-form-grid"><label>Postcode <input type="text" name="event_postcode" value="<?php echo esc_attr( $editing ? get_post_meta( $editing->ID, '_event_pincode', true ) : '' ); ?>"></label><label>Prijs <input type="text" name="event_price" placeholder="Bijv. Gratis of 12,50" value="<?php echo esc_attr( $editing ? get_post_meta( $editing->ID, '_event_ticket_price', true ) : '' ); ?>"></label></div>
                <label>Website / aanmelden <input type="url" name="event_website" value="<?php echo esc_attr( $editing ? get_post_meta( $editing->ID, '_registration', true ) : '' ); ?>"></label>
                <label>Afbeelding <input type="file" name="event_image" accept="image/jpeg,image/png,image/webp"></label>
                <button class="mvm-e2-button primary" type="submit"><?php echo $editing ? 'Wijzigingen opslaan' : 'Evenement indienen'; ?></button>
                <p class="mvm-e2-help">Je ziet alleen je eigen evenementen. Nieuwe inzendingen worden niet automatisch gepubliceerd.</p>
            </form>
        </aside>
    </div>
</main>
<?php get_footer();
