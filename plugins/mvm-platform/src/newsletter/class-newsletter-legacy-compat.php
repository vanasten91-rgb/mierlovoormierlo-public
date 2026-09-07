<?php

defined( 'ABSPATH' ) || exit;

/**
 * Transitional compatibility for the two production newsletter snippets.
 *
 * This class deliberately stays dormant while the legacy snippet runtime is
 * present. After staged snippet deactivation it preserves the existing page
 * shortcodes and old in-flight confirmation URLs while routing new work into
 * the canonical MvM Platform newsletter data model.
 */
final class MvM_Newsletter_Legacy_Compat {
	private const IDENTITY_SCHEMA_OPTION = 'mvm_newsletter_identity_schema';
	private const IDENTITY_SCHEMA        = 'hmac-v1';

	public static function boot(): void {
		// Snippet 582 defines this function. Do not register duplicate handlers
		// during the coexistence phase.
		if ( function_exists( 'mvm_newsletter_table_v1' ) ) {
			return;
		}

		self::migrate_legacy_email_hashes();

		add_shortcode( 'mvm_newsletter_signup_v1', array( __CLASS__, 'signup_shortcode' ) );
		add_shortcode( 'mvm_newsletter_unsubscribe_v1', array( __CLASS__, 'unsubscribe_shortcode' ) );
		add_action( 'admin_post_nopriv_mvm_newsletter_unsubscribe_request_v2', array( __CLASS__, 'request_unsubscribe_link' ) );
		add_action( 'admin_post_mvm_newsletter_unsubscribe_request_v2', array( __CLASS__, 'request_unsubscribe_link' ) );
		add_action( 'template_redirect', array( __CLASS__, 'handle_legacy_token_urls' ), 1 );
	}

	public static function signup_shortcode( array $atts = array() ): string {
		return self::status_notice() . MvM_Newsletter_Frontend::signup_shortcode( $atts );
	}

	public static function unsubscribe_shortcode(): string {
		MvM_Newsletter_Frontend::enqueue_assets();

		ob_start();
		?>
		<section class="mvm-newsletter mvm-newsletter--embed" aria-labelledby="mvm-newsletter-unsubscribe-title">
			<?php echo self::status_notice(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<article class="mvm-newsletter__card">
				<p class="mvm-newsletter__eyebrow mvm-newsletter__eyebrow--blue">Zelf beheren</p>
				<h2 id="mvm-newsletter-unsubscribe-title">Afmelden voor de nieuwsbrief</h2>
				<p>Vul je e-mailadres in. Als dit adres actief is, sturen we een beveiligde afmeldlink. Zo kan niemand anders je zomaar afmelden.</p>
				<form class="mvm-newsletter__form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="mvm_newsletter_unsubscribe_request_v2">
					<?php wp_nonce_field( 'mvm_newsletter_unsubscribe_request_v2', 'mvm_newsletter_unsubscribe_nonce' ); ?>
					<div class="mvm-newsletter__honeypot" aria-hidden="true">
						<label>Laat dit veld leeg<input type="text" name="company_website" tabindex="-1" autocomplete="off"></label>
					</div>
					<label>
						<span>E-mailadres</span>
						<input type="email" name="email" autocomplete="email" inputmode="email" maxlength="320" required>
					</label>
					<button type="submit" class="mvm-newsletter__button">Stuur afmeldlink</button>
				</form>
			</article>
		</section>
		<?php
		return (string) ob_get_clean();
	}

	public static function request_unsubscribe_link(): void {
		$valid_nonce = isset( $_POST['mvm_newsletter_unsubscribe_nonce'] )
			&& wp_verify_nonce(
				sanitize_text_field( wp_unslash( $_POST['mvm_newsletter_unsubscribe_nonce'] ) ),
				'mvm_newsletter_unsubscribe_request_v2'
			);

		if ( ! $valid_nonce || ! empty( $_POST['company_website'] ) ) {
			self::redirect( 'unsubscribe_check_email' );
		}

		$email = isset( $_POST['email'] )
			? MvM_Newsletter::normalize_email( (string) wp_unslash( $_POST['email'] ) )
			: '';

		if ( ! is_email( $email ) || ! self::rate_allowed() ) {
			self::redirect( 'unsubscribe_check_email' );
		}

		$subscriber = MvM_Newsletter::subscriber_by_email( $email );
		if ( $subscriber && 'active' === (string) $subscriber->status ) {
			$token = MvM_Newsletter::unsubscribe_token( $subscriber );
			$link  = home_url( '/nieuwsbrief/afmelden/#token=' . rawurlencode( $token ) );
			$body  = "Hallo,\n\nKlik op onderstaande link om je afmelding voor de nieuwsbrief van Mierlo voor Mierlo te bevestigen:\n{$link}\n\nHeb jij dit niet aangevraagd? Dan hoef je niets te doen.\n\nMierlo voor Mierlo";
			wp_mail(
				$email,
				'Bevestig je afmelding – Mierlo voor Mierlo',
				$body,
				array( 'Content-Type: text/plain; charset=UTF-8' )
			);
		}

		// Deliberately non-enumerating, regardless of account state or mail result.
		self::redirect( 'unsubscribe_check_email' );
	}

	/**
	 * Preserve confirmation/afmeld links that may have been issued by snippets
	 * immediately before cutover. New Platform links use the canonical routes.
	 */
	public static function handle_legacy_token_urls(): void {
		if ( isset( $_GET['mvm_newsletter_confirm'] ) ) {
			self::handle_legacy_confirmation( sanitize_text_field( wp_unslash( $_GET['mvm_newsletter_confirm'] ) ) );
		}
		if ( isset( $_GET['mvm_newsletter_unsubscribe_v2'] ) ) {
			self::handle_legacy_unsubscribe( sanitize_text_field( wp_unslash( $_GET['mvm_newsletter_unsubscribe_v2'] ) ) );
		}
	}

	private static function handle_legacy_confirmation( string $token ): void {
		global $wpdb;
		if ( strlen( $token ) < 40 || strlen( $token ) > 128 ) {
			self::redirect( 'expired' );
		}

		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . MvM_Newsletter::table( 'subscribers' ) . " WHERE status = 'pending' AND confirm_token_hash = %s LIMIT 1",
				hash( 'sha256', $token )
			)
		);
		if ( ! $row || ! self::legacy_token_not_expired( $row ) ) {
			self::redirect( 'expired' );
		}

		$now = MvM_Newsletter::now_mysql();
		$wpdb->update(
			MvM_Newsletter::table( 'subscribers' ),
			array(
				'email_hash'         => MvM_Newsletter::email_hash( (string) $row->email ),
				'status'             => 'active',
				'consent_at'         => $now,
				'confirm_token_hash' => '',
				'confirm_expires'    => null,
				'updated_at'         => $now,
			),
			array( 'id' => absint( $row->id ) )
		);
		self::redirect( 'confirmed' );
	}

	private static function handle_legacy_unsubscribe( string $token ): void {
		global $wpdb;
		if ( strlen( $token ) < 40 || strlen( $token ) > 128 ) {
			self::redirect( 'expired' );
		}

		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . MvM_Newsletter::table( 'subscribers' ) . " WHERE status = 'active' AND confirm_token_hash = %s LIMIT 1",
				hash( 'sha256', $token )
			)
		);
		if ( ! $row || ! self::legacy_token_not_expired( $row ) ) {
			self::redirect( 'expired' );
		}

		$wpdb->update(
			MvM_Newsletter::table( 'subscribers' ),
			array(
				'email_hash'         => MvM_Newsletter::email_hash( (string) $row->email ),
				'status'             => 'unsubscribed',
				'topics'             => '[]',
				'confirm_token_hash' => '',
				'confirm_expires'    => null,
				'updated_at'         => MvM_Newsletter::now_mysql(),
			),
			array( 'id' => absint( $row->id ) )
		);
		self::redirect( 'unsubscribed' );
	}

	private static function legacy_token_not_expired( object $row ): bool {
		if ( empty( $row->confirm_expires ) ) {
			return false;
		}
		$expires = strtotime( (string) $row->confirm_expires . ' UTC' );
		return false !== $expires && $expires >= time();
	}

	private static function migrate_legacy_email_hashes(): void {
		if ( self::IDENTITY_SCHEMA === (string) get_option( self::IDENTITY_SCHEMA_OPTION, '' ) ) {
			return;
		}

		global $wpdb;
		$table = MvM_Newsletter::table( 'subscribers' );
		$rows  = $wpdb->get_results( "SELECT id,email,email_hash FROM {$table} WHERE email <> '' ORDER BY id ASC LIMIT 5000" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$conflict = false;

		foreach ( (array) $rows as $row ) {
			$canonical = MvM_Newsletter::email_hash( (string) $row->email );
			if ( hash_equals( $canonical, (string) $row->email_hash ) ) {
				continue;
			}
			$other = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$table} WHERE email_hash = %s AND id <> %d LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$canonical,
					absint( $row->id )
				)
			);
			if ( $other ) {
				$conflict = true;
				continue;
			}
			$wpdb->update(
				$table,
				array( 'email_hash' => $canonical ),
				array( 'id' => absint( $row->id ) ),
				array( '%s' ),
				array( '%d' )
			);
		}

		if ( ! $conflict ) {
			update_option( self::IDENTITY_SCHEMA_OPTION, self::IDENTITY_SCHEMA, false );
		}
	}

	private static function rate_allowed(): bool {
		$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : 'unknown';
		$key = 'mvm_nl_unsub_' . substr( hash_hmac( 'sha256', $ip, wp_salt( 'nonce' ) ), 0, 24 );
		$hits = (int) get_transient( $key );
		if ( $hits >= 5 ) {
			return false;
		}
		set_transient( $key, $hits + 1, HOUR_IN_SECONDS );
		return true;
	}

	private static function status_notice(): string {
		$status = isset( $_GET['mvm_newsletter'] ) ? sanitize_key( wp_unslash( $_GET['mvm_newsletter'] ) ) : '';
		$messages = array(
			'check_email'              => array( 'success', 'Bijna klaar. Controleer je e-mail en klik op de bevestigingslink.' ),
			'confirmed'                => array( 'success', 'Je inschrijving is bevestigd.' ),
			'already_active'           => array( 'success', 'Dit e-mailadres is al aangemeld.' ),
			'unsubscribe_check_email'  => array( 'success', 'Als dit adres actief is ingeschreven, ontvang je een e-mail waarmee je de afmelding bevestigt.' ),
			'unsubscribed'             => array( 'success', 'Je bent afgemeld voor de MvM-nieuwsbrief.' ),
			'expired'                  => array( 'error', 'Deze link is ongeldig of verlopen.' ),
			'invalid'                  => array( 'error', 'Controleer de gegevens en probeer het opnieuw.' ),
			'consent'                  => array( 'error', 'Geef expliciet toestemming om je in te schrijven.' ),
			'mail_error'               => array( 'error', 'De e-mail kon niet worden verzonden. Probeer het later opnieuw.' ),
			'unsubscribe_mail_error'   => array( 'error', 'De afmeldmail kon niet worden verzonden. Probeer het later opnieuw.' ),
			'rate'                     => array( 'error', 'Er zijn te veel pogingen. Probeer het later opnieuw.' ),
		);
		if ( ! isset( $messages[ $status ] ) ) {
			return '';
		}

		return '<div class="mvm-newsletter__notice is-' . esc_attr( $messages[ $status ][0] ) . '" role="status">' . esc_html( $messages[ $status ][1] ) . '</div>';
	}

	private static function redirect( string $status ): void {
		wp_safe_redirect(
			add_query_arg(
				'mvm_newsletter',
				sanitize_key( $status ),
				home_url( '/nieuwsbrief/' )
			)
		);
		exit;
	}
}
