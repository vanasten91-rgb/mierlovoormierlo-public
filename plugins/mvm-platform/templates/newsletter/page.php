<?php

defined( 'ABSPATH' ) || exit;

$route = MvM_Newsletter_Frontend::route();
if ( '' === $route ) {
	status_header( 404 );
	exit;
}

if ( in_array( $route, array( 'confirm', 'unsubscribe' ), true ) ) {
	nocache_headers();
}

MvM_Newsletter_Frontend::enqueue_assets();
get_header();
?>
<main class="mvm-newsletter" data-mvm-newsletter-app data-route="<?php echo esc_attr( $route ); ?>">
	<section class="mvm-newsletter__hero">
		<div>
			<p class="mvm-newsletter__eyebrow">Mierlo voor Mierlo</p>
			<h1><?php echo 'signup' === $route ? esc_html__( 'Nieuwsbrief', 'mvm-platform' ) : ( 'confirm' === $route ? esc_html__( 'Inschrijving bevestigen', 'mvm-platform' ) : esc_html__( 'Nieuwsbrief afmelden', 'mvm-platform' ) ); ?></h1>
			<?php if ( 'signup' === $route ) : ?>
				<p>Ontvang lokaal nieuws en activiteiten die jij belangrijk vindt. Kies zelf de onderwerpen en wijzig je voorkeuren wanneer je wilt.</p>
			<?php elseif ( 'confirm' === $route ) : ?>
				<p>We controleren de beveiligde bevestigingslink uit je e-mail.</p>
			<?php else : ?>
				<p>Met de beveiligde afmeldlink uit je e-mail kun je de nieuwsbrief direct stopzetten.</p>
			<?php endif; ?>
		</div>
	</section>

	<section class="mvm-newsletter__content">
		<?php if ( 'signup' === $route ) : ?>
			<div class="mvm-newsletter__grid">
				<article class="mvm-newsletter__card">
					<h2>Schrijf je in</h2>
					<p>Na inschrijving ontvang je eerst een bevestigingsmail. Pas na die bevestiging wordt je abonnement actief.</p>
					<form class="mvm-newsletter__form" data-mvm-newsletter-subscribe>
						<label>
							<span>E-mailadres</span>
							<input type="email" name="email" autocomplete="email" maxlength="320" required>
						</label>
						<fieldset>
							<legend>Kies je onderwerpen</legend>
							<div class="mvm-newsletter__topics">
								<?php MvM_Newsletter_Frontend::render_topic_checkboxes(); ?>
							</div>
						</fieldset>
						<label class="mvm-newsletter__consent">
							<input type="checkbox" name="consent" value="1" required>
							<span>Ik wil de Mierlo voor Mierlo-nieuwsbrief ontvangen en begrijp dat ik mij op ieder moment kan afmelden.</span>
						</label>
						<p class="mvm-newsletter__privacy">We gebruiken je e-mailadres alleen voor de gekozen nieuwsbriefonderwerpen. We publiceren het niet en gebruiken geen trackingpixel in de nieuwsbrief.</p>
						<p class="mvm-newsletter__status" data-mvm-newsletter-subscribe-status role="status" aria-live="polite"></p>
						<button type="submit" class="mvm-newsletter__button">Bevestigingsmail aanvragen</button>
					</form>
				</article>

				<article class="mvm-newsletter__card mvm-newsletter__card--accent">
					<h2>Jij houdt de regie</h2>
					<ul>
						<li>Alleen onderwerpen die je zelf kiest.</li>
						<li>Bevestiging via double opt-in.</li>
						<li>Direct afmelden via elke nieuwsbrief.</li>
						<li>Geen openbare abonneelijst.</li>
						<li>Geen trackingpixel voor leesgedrag.</li>
					</ul>
				</article>
			</div>

			<?php if ( is_user_logged_in() ) : ?>
				<article class="mvm-newsletter__card mvm-newsletter__preferences" data-mvm-newsletter-preferences hidden>
					<h2>Mijn nieuwsbriefvoorkeuren</h2>
					<p data-mvm-newsletter-preferences-intro>Je huidige voorkeuren worden geladen.</p>
					<form class="mvm-newsletter__form" data-mvm-newsletter-preferences-form>
						<fieldset>
							<legend>Onderwerpen</legend>
							<div class="mvm-newsletter__topics" data-mvm-newsletter-preference-topics></div>
						</fieldset>
						<p class="mvm-newsletter__status" data-mvm-newsletter-preferences-status role="status" aria-live="polite"></p>
						<button type="submit" class="mvm-newsletter__button">Voorkeuren opslaan</button>
					</form>
				</article>
			<?php endif; ?>
		<?php else : ?>
			<article class="mvm-newsletter__card mvm-newsletter__action-card" data-mvm-newsletter-token-action>
				<div class="mvm-newsletter__spinner" aria-hidden="true"></div>
				<h2 data-mvm-newsletter-action-title><?php echo 'confirm' === $route ? esc_html__( 'Bevestiging controleren…', 'mvm-platform' ) : esc_html__( 'Afmelding verwerken…', 'mvm-platform' ); ?></h2>
				<p class="mvm-newsletter__status" data-mvm-newsletter-action-status role="status" aria-live="polite"></p>
				<a href="<?php echo esc_url( home_url( '/nieuwsbrief/' ) ); ?>" class="mvm-newsletter__button mvm-newsletter__button--secondary">Terug naar Nieuwsbrief</a>
			</article>
		<?php endif; ?>

		<noscript><p class="mvm-newsletter__noscript">Voor inschrijven, bevestigen en voorkeuren beheren is JavaScript op deze pagina nodig.</p></noscript>
	</section>
</main>
<?php
get_footer();
