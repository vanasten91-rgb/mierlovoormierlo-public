<?php

defined( 'ABSPATH' ) || exit;

if ( ! MvM_Offers_Page::is_request() ) {
	exit;
}

MvM_Offers_Page::enqueue_assets();
$offers = MvM_Offers_Page::active_offers();
get_header();
?>
<main class="mvm-offers">
	<section class="mvm-offers__hero">
		<p class="mvm-offers__eyebrow">Lokale ondernemers</p>
		<h1>Aanbiedingen</h1>
		<p>Actuele aanbiedingen en acties van ondernemers uit Mierlo. Dit is commerciële inhoud en staat volledig los van de particuliere Marktplaats.</p>
	</section>

	<section class="mvm-offers__content" aria-labelledby="mvm-offers-list-title">
		<div class="mvm-offers__head">
			<div>
				<p class="mvm-offers__eyebrow">In Mierlo</p>
				<h2 id="mvm-offers-list-title">Actuele aanbiedingen</h2>
			</div>
			<p><?php echo esc_html( count( $offers ) ); ?> actieve aanbieding<?php echo 1 === count( $offers ) ? '' : 'en'; ?></p>
		</div>

		<?php if ( $offers ) : ?>
			<div class="mvm-offers__grid">
				<?php foreach ( $offers as $offer ) : ?>
					<?php
					$data = MvM_Local_Business_Ads::ad_data( $offer );
					$cta  = $data['cta'] ?: 'Bekijk ondernemer';
					?>
					<article class="mvm-offers__card">
						<div class="mvm-offers__label">Advertentie <span aria-hidden="true">·</span> Lokale ondernemer</div>
						<?php if ( $data['image_url'] ) : ?>
							<div class="mvm-offers__media">
								<img src="<?php echo esc_url( $data['image_url'] ); ?>" alt="" loading="lazy" decoding="async">
							</div>
						<?php endif; ?>
						<div class="mvm-offers__body">
							<p class="mvm-offers__advertiser"><?php echo esc_html( $data['advertiser'] ); ?></p>
							<h3><?php echo esc_html( $data['headline'] ); ?></h3>
							<?php if ( $data['text'] ) : ?>
								<p class="mvm-offers__text"><?php echo esc_html( $data['text'] ); ?></p>
							<?php endif; ?>
							<?php if ( $data['end_at'] > time() ) : ?>
								<p class="mvm-offers__until">Geldig t/m <?php echo esc_html( wp_date( 'j F Y', $data['end_at'] ) ); ?></p>
							<?php endif; ?>
							<a class="mvm-offers__cta" href="<?php echo esc_url( $data['url'] ); ?>" target="_blank" rel="sponsored nofollow noopener noreferrer">
								<?php echo esc_html( $cta ); ?> <span aria-hidden="true">→</span>
							</a>
						</div>
					</article>
				<?php endforeach; ?>
			</div>
		<?php else : ?>
			<div class="mvm-offers__empty">
				<h3>Er zijn nu geen actieve aanbiedingen.</h3>
				<p>Zodra lokale ondernemers een actie activeren, verschijnt die hier automatisch.</p>
			</div>
		<?php endif; ?>
	</section>
</main>
<?php
get_footer();
