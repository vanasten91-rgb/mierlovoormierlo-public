<?php

defined( 'ABSPATH' ) || exit;

if ( ! MvM_Entrepreneur_Portal::is_request() || ! current_user_can( MvM_Platform_Capabilities::LOCAL_ADS_SELF_MANAGE ) ) {
	exit;
}

MvM_Entrepreneur_Portal::enqueue_assets();
$user = wp_get_current_user();
get_header();
?>
<main class="mvm-entrepreneur" data-mvm-entrepreneur-ads>
	<section class="mvm-entrepreneur__hero">
		<div>
			<p class="mvm-entrepreneur__eyebrow">MvM Ondernemers</p>
			<h1>Advertentiecentrum</h1>
			<p>Dien je eigen lokale advertenties voor Mierlo voor Mierlo in en beheer bestaande plaatsingen. Nieuwe of inhoudelijk gewijzigde advertenties worden eerst door MvM beoordeeld.</p>
		</div>
		<div class="mvm-entrepreneur__account">
			<span>Ingelogd als</span>
			<strong><?php echo esc_html( $user->display_name ?: $user->user_login ); ?></strong>
		</div>
	</section>

	<section class="mvm-entrepreneur__layout">
		<article class="mvm-entrepreneur__panel">
			<div class="mvm-entrepreneur__panel-head">
				<div>
					<p class="mvm-entrepreneur__eyebrow">Nieuwe advertentie</p>
					<h2>Maak een MvM-advertentieblok</h2>
				</div>
				<button type="button" class="mvm-entrepreneur__secondary" data-mvm-entrepreneur-reset>Nieuw formulier</button>
			</div>

			<form class="mvm-entrepreneur__form" data-mvm-entrepreneur-form>
				<input type="hidden" name="id" value="">
				<label>
					<span>Bedrijfsnaam</span>
					<input type="text" name="advertiser" maxlength="120" autocomplete="organization" required>
				</label>
				<label>
					<span>Kop</span>
					<input type="text" name="headline" maxlength="160" required>
				</label>
				<label class="is-wide">
					<span>Korte advertentietekst</span>
					<textarea name="text" rows="5" maxlength="600"></textarea>
				</label>
				<label>
					<span>Knoptekst</span>
					<input type="text" name="cta" maxlength="60" value="Bekijk ondernemer">
				</label>
				<label>
					<span>Website-URL</span>
					<input type="url" name="url" maxlength="2048" placeholder="https://…" required>
				</label>
				<label>
					<span>Startdatum</span>
					<input type="datetime-local" name="start_at">
				</label>
				<label>
					<span>Einddatum</span>
					<input type="datetime-local" name="end_at">
				</label>
				<label data-mvm-entrepreneur-status-field hidden>
					<span>Status bestaande advertentie</span>
					<select name="status">
						<option value="draft">Ter beoordeling</option>
						<option value="paused">Gepauzeerd</option>
						<option value="ended">Beëindigd</option>
					</select>
				</label>
				<div class="mvm-entrepreneur__image-field">
					<span>Afbeelding</span>
					<input type="file" accept="image/jpeg,image/png,image/webp" data-mvm-entrepreneur-image>
					<input type="hidden" name="image_id" value="">
					<div class="mvm-entrepreneur__image-preview" data-mvm-entrepreneur-image-preview hidden></div>
				</div>
				<fieldset class="is-wide">
					<legend>Waar mag deze advertentie verschijnen?</legend>
					<div class="mvm-entrepreneur__placements" data-mvm-entrepreneur-placements></div>
					<p>Nieuwe advertenties gaan altijd eerst <strong>ter beoordeling</strong>. Je advertentie wordt pas na goedkeuring herkenbaar getoond als <strong>“Advertentie · Lokale ondernemer”</strong>. Alleen MvM-staf kan een advertentie activeren.</p>
				</fieldset>
				<div class="mvm-entrepreneur__actions is-wide">
					<p class="mvm-entrepreneur__status" data-mvm-entrepreneur-form-status role="status" aria-live="polite"></p>
					<button type="submit" class="mvm-entrepreneur__primary">Opslaan / indienen</button>
				</div>
			</form>
		</article>

		<aside class="mvm-entrepreneur__panel mvm-entrepreneur__help">
			<p class="mvm-entrepreneur__eyebrow">Zo werkt het</p>
			<h2>Eigen beheer, met controle</h2>
			<ul>
				<li>Je ziet en beheert uitsluitend advertenties van je eigen ondernemersaccount.</li>
				<li>Je kiest zelf waar op MvM een advertentie mag verschijnen.</li>
				<li>Een nieuwe advertentie heeft geen publicatiestatus om te kiezen: die gaat altijd eerst ter beoordeling.</li>
				<li>Wijzigingen aan actieve advertenties gaan opnieuw ter beoordeling.</li>
				<li>Je kunt een bestaande advertentie wel direct pauzeren of beëindigen; dat vermindert alleen de zichtbaarheid.</li>
				<li>De advertenties bevatten geen trackingpixel of persoonsprofilering.</li>
				<li>Alleen MvM-staf kan na controle een advertentie activeren of ingrijpen bij misbruik.</li>
			</ul>
		</aside>
	</section>

	<section class="mvm-entrepreneur__panel mvm-entrepreneur__mine">
		<div class="mvm-entrepreneur__panel-head">
			<div>
				<p class="mvm-entrepreneur__eyebrow">Mijn advertenties</p>
				<h2>Overzicht</h2>
			</div>
			<p class="mvm-entrepreneur__status" data-mvm-entrepreneur-list-status role="status" aria-live="polite">Advertenties laden…</p>
		</div>
		<div class="mvm-entrepreneur__list" data-mvm-entrepreneur-list></div>
	</section>
</main>
<?php
get_footer();
