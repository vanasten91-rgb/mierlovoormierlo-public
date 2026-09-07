<?php

defined( 'ABSPATH' ) || exit;

MvM_Marketplace_Frontend::enqueue_assets();
get_header();

while ( have_posts() ) :
	the_post();
	$post = get_post();
	if ( ! ( $post instanceof WP_Post ) ) {
		continue;
	}
	$data = MvM_Marketplace::listing_data( $post, MvM_Marketplace::is_owner( $post->ID ) || current_user_can( MvM_Platform_Capabilities::MARKETPLACE_MODERATE ) );
	$is_owner = MvM_Marketplace::is_owner( $post->ID );
	$images = array_values( array_filter( array_unique( array_merge( array( $data['featured_image'] ), $data['gallery'] ) ) ) );
	?>
	<main id="primary" class="site-main mvm-marketplace-page">
		<div class="mvm-marketplace-page__inner">
			<article class="mvm-marketplace-single" data-mvm-marketplace-single="<?php echo esc_attr( (string) $post->ID ); ?>">
				<a class="mvm-marketplace-single__back" href="<?php echo esc_url( get_post_type_archive_link( MvM_Marketplace::POST_TYPE ) ); ?>">← Terug naar Marktplaats</a>
				<div class="mvm-marketplace-single__layout">
					<section class="mvm-marketplace-single__media" aria-label="Foto's van de advertentie">
						<?php if ( $images ) : ?>
							<div class="mvm-marketplace-single__gallery">
								<?php foreach ( $images as $index => $image ) : ?>
									<img src="<?php echo esc_url( $image ); ?>" alt="<?php echo esc_attr( 0 === $index ? $data['title'] : '' ); ?>" loading="<?php echo 0 === $index ? 'eager' : 'lazy'; ?>">
								<?php endforeach; ?>
							</div>
						<?php else : ?>
							<div class="mvm-marketplace-single__placeholder" aria-hidden="true">MvM</div>
						<?php endif; ?>
					</section>
					<section class="mvm-marketplace-single__content">
						<div class="mvm-marketplace-single__topline">
							<span class="mvm-marketplace-badge"><?php echo esc_html( MvM_Marketplace_Frontend::status_label( (string) $data['status'] ) ); ?></span>
							<span><?php echo esc_html( MvM_Marketplace_Frontend::condition_label( (string) $data['condition'] ) ); ?></span>
						</div>
						<h1><?php echo esc_html( $data['title'] ); ?></h1>
						<p class="mvm-marketplace-single__price"><?php echo esc_html( MvM_Marketplace_Frontend::price_label( (string) $data['price_type'], (int) $data['price_cents'] ) ); ?></p>
						<?php if ( $data['location'] ) : ?><p class="mvm-marketplace-single__location">📍 <?php echo esc_html( $data['location'] ); ?></p><?php endif; ?>
						<div class="mvm-marketplace-single__description"><?php echo wp_kses_post( wpautop( $post->post_content ) ); ?></div>

						<?php if ( ! empty( $data['categories'] ) && is_array( $data['categories'] ) ) : ?>
							<div class="mvm-marketplace-single__categories">
								<?php foreach ( $data['categories'] as $term_id => $term_name ) : ?>
									<?php $term_link = get_term_link( (int) $term_id, MvM_Marketplace::TAXONOMY ); ?>
									<?php if ( ! is_wp_error( $term_link ) ) : ?><a href="<?php echo esc_url( $term_link ); ?>"><?php echo esc_html( $term_name ); ?></a><?php endif; ?>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>

						<section class="mvm-marketplace-seller" aria-labelledby="mvm-marketplace-seller-title">
							<h2 id="mvm-marketplace-seller-title">Aangeboden door</h2>
							<p><strong><?php echo esc_html( $data['seller']['display_name'] ); ?></strong></p>
							<a class="mvm-marketplace__secondary" href="<?php echo esc_url( $data['seller']['profile_url'] ); ?>">Bekijk profiel</a>
							<p class="mvm-marketplace-seller__privacy">Contactgegevens worden niet automatisch openbaar gemaakt. Spreek overdracht en betaling rechtstreeks en veilig met elkaar af.</p>
						</section>

						<?php if ( $is_owner ) : ?>
							<section class="mvm-marketplace-owner" aria-labelledby="mvm-marketplace-owner-title">
								<h2 id="mvm-marketplace-owner-title">Mijn advertentie</h2>
								<div class="mvm-marketplace-owner__actions">
									<button type="button" data-mvm-marketplace-set-status="active">Beschikbaar</button>
									<button type="button" data-mvm-marketplace-set-status="reserved">Gereserveerd</button>
									<button type="button" data-mvm-marketplace-set-status="sold">Verkocht</button>
								</div>
								<p data-mvm-marketplace-owner-status role="status" aria-live="polite"></p>
							</section>
						<?php elseif ( is_user_logged_in() ) : ?>
							<details class="mvm-marketplace-report">
								<summary>Advertentie melden</summary>
								<form data-mvm-marketplace-report-form>
									<label>Waarom meld je deze advertentie?<textarea name="reason" rows="3" minlength="5" maxlength="500" required></textarea></label>
									<button type="submit">Melding versturen</button>
									<p data-mvm-marketplace-report-status role="status" aria-live="polite"></p>
								</form>
							</details>
						<?php else : ?>
							<p><a href="<?php echo esc_url( wp_login_url( get_permalink( $post ) ) ); ?>">Log in</a> om deze advertentie te melden of met de aanbieder in contact te komen.</p>
						<?php endif; ?>
					</section>
				</div>
			</article>
		</div>
	</main>
	<?php
endwhile;

get_footer();
