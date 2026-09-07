<?php

defined( 'ABSPATH' ) || exit;

final class MvM_Marketplace_Frontend {
	private static bool $assets_enqueued = false;

	public static function boot(): void {
		add_filter( 'template_include', array( __CLASS__, 'template_include' ), 50 );
		add_shortcode( 'mvm_marktplaats', array( __CLASS__, 'shortcode_archive' ) );
	}

	public static function template_include( string $template ): string {
		if ( is_post_type_archive( MvM_Marketplace::POST_TYPE ) || is_tax( MvM_Marketplace::TAXONOMY ) ) {
			$plugin_template = MVM_PLATFORM_DIR . 'templates/marketplace/archive.php';
			return is_readable( $plugin_template ) ? $plugin_template : $template;
		}
		if ( is_singular( MvM_Marketplace::POST_TYPE ) ) {
			$post = get_queried_object();
			if ( $post instanceof WP_Post ) {
				$status = (string) get_post_meta( $post->ID, MvM_Marketplace::meta_key( 'status' ), true );
				if ( ! in_array( $status, MvM_Marketplace::public_statuses(), true ) && ! MvM_Marketplace::is_owner( $post->ID ) && ! current_user_can( MvM_Platform_Capabilities::MARKETPLACE_MODERATE ) ) {
					global $wp_query;
					$wp_query->set_404();
					status_header( 404 );
					return get_404_template();
				}
			}
			$plugin_template = MVM_PLATFORM_DIR . 'templates/marketplace/single.php';
			return is_readable( $plugin_template ) ? $plugin_template : $template;
		}
		return $template;
	}

	public static function enqueue_assets(): void {
		if ( self::$assets_enqueued ) {
			return;
		}
		self::$assets_enqueued = true;
		$css = MVM_PLATFORM_DIR . 'assets/marketplace.css';
		$js  = MVM_PLATFORM_DIR . 'assets/marketplace.js';
		wp_enqueue_style(
			'mvm-marketplace',
			MVM_PLATFORM_URL . 'assets/marketplace.css',
			array(),
			is_readable( $css ) ? (string) filemtime( $css ) : MVM_PLATFORM_VERSION
		);
		wp_enqueue_script(
			'mvm-marketplace',
			MVM_PLATFORM_URL . 'assets/marketplace.js',
			array(),
			is_readable( $js ) ? (string) filemtime( $js ) : MVM_PLATFORM_VERSION,
			true
		);
		wp_localize_script(
			'mvm-marketplace',
			'MvMMarketplace',
			array(
				'api'        => esc_url_raw( rest_url( MvM_Platform_REST::NAMESPACE . '/marketplace' ) ),
				'nonce'      => is_user_logged_in() ? wp_create_nonce( 'wp_rest' ) : '',
				'loggedIn'   => is_user_logged_in(),
				'loginUrl'   => wp_login_url( get_post_type_archive_link( MvM_Marketplace::POST_TYPE ) ),
				'archiveUrl' => get_post_type_archive_link( MvM_Marketplace::POST_TYPE ),
				'maxGallery' => MvM_Marketplace::MAX_GALLERY,
				'categories' => self::category_options(),
			)
		);
	}

	public static function shortcode_archive(): string {
		self::enqueue_assets();
		ob_start();
		self::render_archive_shell();
		return (string) ob_get_clean();
	}

	public static function render_archive_shell(): void {
		?>
		<section class="mvm-marketplace" data-mvm-marketplace-app>
			<header class="mvm-marketplace__hero">
				<div>
					<p class="mvm-marketplace__eyebrow">Mierlo voor Mierlo</p>
					<h1>Marktplaats</h1>
					<p>Geef spullen lokaal een tweede leven. Koop, verkoop, ruil of geef gratis weg aan inwoners uit de omgeving.</p>
				</div>
				<?php if ( is_user_logged_in() ) : ?>
					<button type="button" class="mvm-marketplace__primary" data-mvm-marketplace-open-create>Advertentie plaatsen</button>
				<?php else : ?>
					<a class="mvm-marketplace__primary" href="<?php echo esc_url( wp_login_url( get_post_type_archive_link( MvM_Marketplace::POST_TYPE ) ) ); ?>">Inloggen om te verkopen</a>
				<?php endif; ?>
			</header>

			<nav class="mvm-marketplace__tabs" aria-label="Marktplaatsweergave">
				<button type="button" class="is-active" data-mvm-marketplace-tab="browse">Aanbod</button>
				<?php if ( is_user_logged_in() ) : ?>
					<button type="button" data-mvm-marketplace-tab="mine">Mijn advertenties</button>
				<?php endif; ?>
			</nav>

			<div class="mvm-marketplace__filters" data-mvm-marketplace-filters>
				<label>
					<span>Zoeken</span>
					<input type="search" data-mvm-marketplace-search placeholder="Zoek in lokaal aanbod">
				</label>
				<label>
					<span>Categorie</span>
					<select data-mvm-marketplace-category>
						<option value="0">Alle categorieën</option>
						<?php foreach ( self::category_options() as $category ) : ?>
							<option value="<?php echo esc_attr( (string) $category['id'] ); ?>"><?php echo esc_html( $category['name'] ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
			</div>

			<p class="mvm-marketplace__status" data-mvm-marketplace-status role="status" aria-live="polite"></p>
			<div class="mvm-marketplace__grid" data-mvm-marketplace-grid>
				<?php self::render_server_cards(); ?>
			</div>
			<div class="mvm-marketplace__pager" data-mvm-marketplace-pager></div>
			<?php if ( is_user_logged_in() ) : ?>
				<?php self::render_create_dialog(); ?>
			<?php endif; ?>
		</section>
		<?php
	}

	private static function render_server_cards(): void {
		$query = new WP_Query(
			array(
				'post_type'      => MvM_Marketplace::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 12,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'meta_query'     => array(
					array(
						'key'     => MvM_Marketplace::meta_key( 'status' ),
						'value'   => MvM_Marketplace::public_statuses(),
						'compare' => 'IN',
					),
				),
			)
		);
		if ( ! $query->have_posts() ) {
			echo '<p class="mvm-marketplace__empty">Er staan nog geen advertenties. Plaats de eerste lokale advertentie.</p>';
			return;
		}
		foreach ( $query->posts as $post ) {
			self::render_card( $post );
		}
		wp_reset_postdata();
	}

	public static function render_card( WP_Post $post ): void {
		$data = MvM_Marketplace::listing_data( $post, false );
		$image = $data['featured_image'] ?: ( $data['gallery'][0] ?? '' );
		?>
		<article class="mvm-marketplace-card">
			<a class="mvm-marketplace-card__image" href="<?php echo esc_url( $data['url'] ); ?>" aria-label="<?php echo esc_attr( $data['title'] ); ?>">
				<?php if ( $image ) : ?>
					<img src="<?php echo esc_url( $image ); ?>" alt="" loading="lazy">
				<?php else : ?>
					<span aria-hidden="true">MvM</span>
				<?php endif; ?>
			</a>
			<div class="mvm-marketplace-card__body">
				<div class="mvm-marketplace-card__meta">
					<strong><?php echo esc_html( self::price_label( (string) $data['price_type'], (int) $data['price_cents'] ) ); ?></strong>
					<?php if ( 'active' !== $data['status'] ) : ?><span><?php echo esc_html( self::status_label( (string) $data['status'] ) ); ?></span><?php endif; ?>
				</div>
				<h2><a href="<?php echo esc_url( $data['url'] ); ?>"><?php echo esc_html( $data['title'] ); ?></a></h2>
				<?php if ( $data['location'] ) : ?><p class="mvm-marketplace-card__location"><?php echo esc_html( $data['location'] ); ?></p><?php endif; ?>
			</div>
		</article>
		<?php
	}

	private static function render_create_dialog(): void {
		?>
		<dialog class="mvm-marketplace-dialog" data-mvm-marketplace-dialog>
			<form class="mvm-marketplace-form" data-mvm-marketplace-form>
				<div class="mvm-marketplace-form__head">
					<div><p class="mvm-marketplace__eyebrow">Nieuw aanbod</p><h2>Advertentie plaatsen</h2></div>
					<button type="button" class="mvm-marketplace__icon-button" data-mvm-marketplace-close aria-label="Sluiten">×</button>
				</div>
				<label>Titel<input name="title" type="text" maxlength="140" required></label>
				<label>Omschrijving<textarea name="description" rows="7" maxlength="10000" required></textarea></label>
				<div class="mvm-marketplace-form__columns">
					<label>Type prijs<select name="price_type"><option value="fixed">Vaste prijs</option><option value="free">Gratis</option><option value="swap">Ruilen</option></select></label>
					<label>Prijs in euro's<input name="price" type="number" min="0" max="100000" step="0.01" value="0"></label>
				</div>
				<div class="mvm-marketplace-form__columns">
					<label>Staat<select name="condition"><option value="new">Nieuw</option><option value="like_new">Zo goed als nieuw</option><option value="good">Goed</option><option value="fair">Redelijk</option><option value="used" selected>Gebruikt</option></select></label>
					<label>Categorie<select name="category" required><option value="">Kies een categorie</option><?php foreach ( self::category_options() as $category ) : ?><option value="<?php echo esc_attr( (string) $category['id'] ); ?>"><?php echo esc_html( $category['name'] ); ?></option><?php endforeach; ?></select></label>
				</div>
				<label>Omgeving <span class="mvm-marketplace-form__hint">geen woonadres</span><input name="location" type="text" maxlength="80" placeholder="Bijv. Mierlo centrum"></label>
				<label>Foto's <span class="mvm-marketplace-form__hint">maximaal <?php echo esc_html( (string) MvM_Marketplace::MAX_GALLERY ); ?>, JPEG/PNG/WebP</span><input name="photos" type="file" accept="image/jpeg,image/png,image/webp" multiple></label>
				<p class="mvm-marketplace-form__policy">Geen wapens, vuurwerk, drugs, tabak/nicotine, alcohol of receptplichtige geneesmiddelen. Deel geen privé-adres in de advertentie.</p>
				<p data-mvm-marketplace-form-status role="status" aria-live="polite"></p>
				<div class="mvm-marketplace-form__actions">
					<button type="button" data-mvm-marketplace-close>Annuleren</button>
					<button class="mvm-marketplace__primary" type="submit">Plaatsen</button>
				</div>
			</form>
		</dialog>
		<?php
	}

	public static function category_options(): array {
		$terms = get_terms(
			array(
				'taxonomy'   => MvM_Marketplace::TAXONOMY,
				'hide_empty' => false,
				'orderby'    => 'name',
			)
		);
		if ( is_wp_error( $terms ) ) {
			return array();
		}
		return array_map(
			static fn( WP_Term $term ): array => array( 'id' => (int) $term->term_id, 'name' => $term->name ),
			$terms
		);
	}

	public static function price_label( string $type, int $cents ): string {
		if ( 'free' === $type ) {
			return 'Gratis';
		}
		if ( 'swap' === $type ) {
			return 'Ruilen';
		}
		return '€ ' . number_format( max( 0, $cents ) / 100, 2, ',', '.' );
	}

	public static function condition_label( string $condition ): string {
		$labels = array(
			'new'      => 'Nieuw',
			'like_new' => 'Zo goed als nieuw',
			'good'     => 'Goed',
			'fair'     => 'Redelijk',
			'used'     => 'Gebruikt',
		);
		return $labels[ $condition ] ?? 'Gebruikt';
	}

	public static function status_label( string $status ): string {
		$labels = array(
			'active'    => 'Beschikbaar',
			'reserved'  => 'Gereserveerd',
			'sold'      => 'Verkocht',
			'expired'   => 'Verlopen',
			'moderated' => 'Geblokkeerd',
		);
		return $labels[ $status ] ?? ucfirst( $status );
	}
}
