<?php
/** MvM Ondernemershub — los van staf en organisatoren. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
get_header();

if ( ! is_user_logged_in() ) :
    ?>
    <main id="content" class="mvm-e2-portal" role="main">
        <section class="mvm-e2-login">
            <span class="mvm-e2-kicker" style="color:#1966AE;-webkit-text-fill-color:#1966AE">Ondernemers</span>
            <h1>Ondernemershub</h1>
            <p>Log in om je eigen advertenties te beheren en je MvM-pagina te maken.</p>
            <a class="mvm-e2-button primary" href="<?php echo esc_url( wp_login_url( home_url( '/ondernemers/' ) ) ); ?>">Inloggen</a>
        </section>
    </main>
    <?php
    get_footer();
    return;
endif;

if ( ! mvm_e2_is_entrepreneur() ) :
    ?>
    <main id="content" class="mvm-e2-portal" role="main">
        <section class="mvm-e2-login">
            <h1>Geen ondernemerstoegang</h1>
            <p>Dit account heeft geen toegang tot de Ondernemershub.</p>
            <a class="mvm-e2-button" href="<?php echo esc_url( home_url( '/' ) ); ?>">Naar de startpagina</a>
        </section>
    </main>
    <?php
    get_footer();
    return;
endif;

$edit_id = isset( $_GET['bewerken'] ) ? absint( $_GET['bewerken'] ) : 0;
$editing = $edit_id && mvm_e2_owned_post( $edit_id, 'mvm_advertentie' ) ? get_post( $edit_id ) : null;
$args = array(
    'post_type' => 'mvm_advertentie',
    'post_status' => array( 'publish', 'pending', 'draft' ),
    'posts_per_page' => 100,
    'orderby' => 'date',
    'order' => 'DESC',
);
if ( ! mvm_e2_is_staff() ) {
    $args['author'] = get_current_user_id();
}
$ads = new WP_Query( $args );
$status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
?>
<main id="content" class="mvm-e2-portal" role="main">
    <header class="mvm-e2-portal-head">
        <div>
            <span class="mvm-e2-kicker">Lokale ondernemers</span>
            <h1>Ondernemershub</h1>
            <p>Beheer je eigen advertenties en je publieke MvM-pagina. Deze omgeving staat los van de redactie- en staf-Hub.</p>
        </div>
        <div class="mvm-e2-actions">
            <a class="mvm-e2-button" href="<?php echo esc_url( home_url( '/' ) ); ?>">Startpagina</a>
            <a class="mvm-e2-button" href="<?php echo esc_url( wp_logout_url( home_url( '/' ) ) ); ?>">Uitloggen</a>
        </div>
    </header>

    <?php if ( in_array( $status, array( 'ingediend', 'bijgewerkt' ), true ) ) : ?>
        <div class="mvm-e2-notice"><?php echo 'ingediend' === $status ? 'Je advertentie is ingediend en staat klaar voor controle.' : 'Je advertentie is bijgewerkt.'; ?></div>
    <?php endif; ?>

    <section class="mvm-e2-panel">
        <h2>Snel naar</h2>
        <div class="mvm-e2-quicklinks">
            <a class="mvm-e2-quicklink mvm-e2-page-create" href="<?php echo esc_url( home_url( '/paginas/' ) ); ?>">
                <strong>MvM-pagina maken</strong>
                <span>Maak of beheer de publieke pagina van je onderneming in de MvM-community.</span>
            </a>
            <a class="mvm-e2-quicklink" href="#advertentie-maken">
                <strong>Advertentie indienen</strong>
                <span>Maak een herkenbare lokale advertentie die eerst ter controle wordt aangeboden.</span>
            </a>
        </div>
    </section>

    <div class="mvm-e2-portal-grid">
        <section class="mvm-e2-panel">
            <h2>Mijn advertenties</h2>
            <div class="mvm-e2-list">
                <?php if ( $ads->have_posts() ) : ?>
                    <?php while ( $ads->have_posts() ) : $ads->the_post(); $id = get_the_ID(); ?>
                        <article class="mvm-e2-list-item">
                            <div>
                                <h3><?php the_title(); ?></h3>
                                <div class="mvm-e2-meta">
                                    <?php if ( get_post_meta( $id, '_mvm_business_name', true ) ) : ?><span><?php echo esc_html( get_post_meta( $id, '_mvm_business_name', true ) ); ?></span><?php endif; ?>
                                    <?php if ( get_post_meta( $id, '_mvm_ad_start', true ) || get_post_meta( $id, '_mvm_ad_end', true ) ) : ?><span>Looptijd: <?php echo esc_html( get_post_meta( $id, '_mvm_ad_start', true ) ?: 'direct' ); ?> – <?php echo esc_html( get_post_meta( $id, '_mvm_ad_end', true ) ?: 'open' ); ?></span><?php endif; ?>
                                </div>
                                <span class="mvm-e2-status <?php echo esc_attr( get_post_status( $id ) ); ?>"><?php echo esc_html( mvm_e2_status_label( get_post_status( $id ) ) ); ?></span>
                            </div>
                            <a class="mvm-e2-button" href="<?php echo esc_url( add_query_arg( 'bewerken', $id, home_url( '/ondernemers/' ) ) ); ?>">Bewerken</a>
                        </article>
                    <?php endwhile; wp_reset_postdata(); ?>
                <?php else : ?>
                    <div class="mvm-e2-empty"><p>Je hebt nog geen advertenties ingediend.</p></div>
                <?php endif; ?>
            </div>
        </section>

        <aside id="advertentie-maken" class="mvm-e2-panel">
            <h2><?php echo $editing ? 'Advertentie bewerken' : 'Advertentie indienen'; ?></h2>
            <form class="mvm-e2-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
                <input type="hidden" name="action" value="mvm_e2_save_ad">
                <input type="hidden" name="ad_id" value="<?php echo $editing ? (int) $editing->ID : 0; ?>">
                <?php wp_nonce_field( 'mvm_e2_save_ad', 'mvm_nonce' ); ?>
                <label>Naam onderneming <input required type="text" name="business_name" value="<?php echo esc_attr( $editing ? get_post_meta( $editing->ID, '_mvm_business_name', true ) : '' ); ?>"></label>
                <label>Titel advertentie <input required type="text" name="ad_title" value="<?php echo esc_attr( $editing ? $editing->post_title : '' ); ?>"></label>
                <label>Omschrijving <textarea name="ad_description"><?php echo esc_textarea( $editing ? $editing->post_content : '' ); ?></textarea></label>
                <?php $ad_category = $editing ? (string) get_post_meta( $editing->ID, '_mvm_ad_category', true ) : 'algemeen'; ?>
                <label>Categorie <select name="ad_category"><option value="algemeen" <?php selected( $ad_category, 'algemeen' ); ?>>Algemeen</option><option value="eten-drinken" <?php selected( $ad_category, 'eten-drinken' ); ?>>Eten & drinken</option><option value="winkels" <?php selected( $ad_category, 'winkels' ); ?>>Winkels</option><option value="diensten" <?php selected( $ad_category, 'diensten' ); ?>>Diensten</option><option value="zorg-welzijn" <?php selected( $ad_category, 'zorg-welzijn' ); ?>>Zorg & welzijn</option><option value="wonen-klussen" <?php selected( $ad_category, 'wonen-klussen' ); ?>>Wonen & klussen</option></select></label>
                <div class="mvm-e2-form-grid">
                    <label>Startdatum <input type="date" name="ad_start" value="<?php echo esc_attr( $editing ? get_post_meta( $editing->ID, '_mvm_ad_start', true ) : '' ); ?>"></label>
                    <label>Einddatum <input type="date" name="ad_end" value="<?php echo esc_attr( $editing ? get_post_meta( $editing->ID, '_mvm_ad_end', true ) : '' ); ?>"></label>
                </div>
                <label>Knoptekst <input type="text" name="ad_cta_label" placeholder="Bijv. Bekijk aanbieding" value="<?php echo esc_attr( $editing ? get_post_meta( $editing->ID, '_mvm_ad_cta_label', true ) : '' ); ?>"></label>
                <label>Link van de knop <input type="url" name="ad_cta_url" placeholder="https://" value="<?php echo esc_attr( $editing ? get_post_meta( $editing->ID, '_mvm_ad_cta_url', true ) : '' ); ?>"></label>
                <label>Afbeelding <input type="file" name="ad_image" accept="image/jpeg,image/png,image/webp"></label>
                <button class="mvm-e2-button primary" type="submit"><?php echo $editing ? 'Wijzigingen opslaan' : 'Advertentie indienen'; ?></button>
                <p class="mvm-e2-help">Je ziet en bewerkt alleen je eigen advertenties. Nieuwe advertenties worden eerst gecontroleerd.</p>
            </form>
        </aside>
    </div>
</main>
<?php get_footer();
