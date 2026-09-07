<?php

defined( 'ABSPATH' ) || exit;

/**
 * Connects the existing newsletter subscription to the logged-in Mijn Mierlo
 * account environment without introducing a second subscriber store.
 */
final class MvM_Newsletter_Account {
	public static function boot(): void {
		add_shortcode( 'mvm_newsletter_account', array( __CLASS__, 'shortcode' ) );
		add_filter( 'do_shortcode_tag', array( __CLASS__, 'append_to_my_mierlo' ), 20, 4 );
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ), 20 );
	}

	public static function register_routes(): void {
		register_rest_route(
			MvM_Platform_REST::NAMESPACE,
			'/newsletter/preferences',
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'permission_callback' => array( 'MvM_Platform_REST', 'require_authenticated' ),
				'callback'            => array( __CLASS__, 'unsubscribe' ),
			)
		);
	}

	/**
	 * Add newsletter controls to the existing Mijn Mierlo shortcode output.
	 *
	 * @param string $output Shortcode output.
	 * @param string $tag    Shortcode name.
	 * @param array  $attr   Shortcode attributes.
	 * @param array  $match  Regex match data.
	 */
	public static function append_to_my_mierlo( string $output, string $tag, array $attr, array $match ): string {
		unset( $attr, $match );
		if ( 'mvm_mijn_mierlo' !== $tag || ! is_user_logged_in() || str_contains( $output, 'data-mvm-newsletter-account' ) ) {
			return $output;
		}

		return $output . self::shortcode();
	}

	public static function shortcode(): string {
		if ( ! is_user_logged_in() ) {
			return '';
		}

		MvM_Newsletter_Frontend::enqueue_assets();

		ob_start();
		?>
		<section class="mvm-newsletter mvm-newsletter--account" data-mvm-newsletter-app data-mvm-newsletter-account aria-labelledby="mvm-newsletter-account-title">
			<article class="mvm-newsletter__card mvm-newsletter__preferences" data-mvm-newsletter-preferences>
				<p class="mvm-newsletter__eyebrow mvm-newsletter__eyebrow--blue">Account</p>
				<h2 id="mvm-newsletter-account-title">Mijn nieuwsbrief</h2>
				<p data-mvm-newsletter-preferences-intro>Je nieuwsbriefstatus en voorkeuren worden geladen.</p>
				<form class="mvm-newsletter__form" data-mvm-newsletter-preferences-form hidden>
					<fieldset>
						<legend>Onderwerpen</legend>
						<div class="mvm-newsletter__topics" data-mvm-newsletter-preference-topics></div>
					</fieldset>
					<p class="mvm-newsletter__status" data-mvm-newsletter-preferences-status role="status" aria-live="polite"></p>
					<button type="submit" class="mvm-newsletter__button">Voorkeuren opslaan</button>
				</form>
				<div class="mvm-newsletter__unsubscribe" data-mvm-newsletter-account-unsubscribe hidden>
					<h3>Volledig uitschrijven</h3>
					<p>Je kunt de nieuwsbrief op ieder moment volledig stopzetten. Je ontvangt daarna geen nieuwe campagnes meer.</p>
					<p class="mvm-newsletter__status" data-mvm-newsletter-unsubscribe-status role="status" aria-live="polite"></p>
					<button type="button" class="mvm-newsletter__button mvm-newsletter__button--danger" data-mvm-newsletter-account-unsubscribe-button>Nieuwsbrief volledig stopzetten</button>
				</div>
				<p class="mvm-newsletter__account-link"><a href="<?php echo esc_url( home_url( '/nieuwsbrief/' ) ); ?>">Meer over de nieuwsbrief en privacy</a></p>
			</article>
		</section>
		<?php
		return (string) ob_get_clean();
	}

	public static function unsubscribe(): WP_REST_Response {
		global $wpdb;

		$user       = wp_get_current_user();
		$email      = MvM_Newsletter::normalize_email( (string) $user->user_email );
		$subscriber = is_email( $email ) ? MvM_Newsletter::subscriber_by_email( $email ) : null;

		if ( $subscriber && 'unsubscribed' !== (string) $subscriber->status ) {
			$campaign_ids = $wpdb->get_col(
				$wpdb->prepare(
					'SELECT DISTINCT campaign_id FROM ' . MvM_Newsletter::table( 'queue' ) . " WHERE subscriber_id = %d AND status IN ('pending','failed')",
					absint( $subscriber->id )
				)
			);
			$updated = $wpdb->update(
				MvM_Newsletter::table( 'subscribers' ),
				array(
					'status'             => 'unsubscribed',
					'topics'             => '[]',
					'confirm_token_hash' => '',
					'confirm_expires'    => null,
					'updated_at'         => MvM_Newsletter::now_mysql(),
				),
				array( 'id' => absint( $subscriber->id ) ),
				null,
				array( '%d' )
			);

			if ( false === $updated ) {
				return MvM_Platform_REST::no_store_private(
					new WP_REST_Response(
						array( 'code' => 'mvm_newsletter_unsubscribe_failed', 'message' => 'Afmelden is niet gelukt. Probeer het later opnieuw.' ),
						500
					)
				);
			}

			$wpdb->query(
				$wpdb->prepare(
					'UPDATE ' . MvM_Newsletter::table( 'queue' ) . " SET status = 'cancelled', last_error = 'subscriber_unsubscribed' WHERE subscriber_id = %d AND status IN ('pending','failed')",
					absint( $subscriber->id )
				)
			);

			foreach ( array_map( 'absint', (array) $campaign_ids ) as $campaign_id ) {
				$remaining = (int) $wpdb->get_var(
					$wpdb->prepare(
						'SELECT COUNT(*) FROM ' . MvM_Newsletter::table( 'queue' ) . " WHERE campaign_id = %d AND (status IN ('pending','sending') OR (status = 'failed' AND attempts < %d))",
						$campaign_id,
						3
					)
				);
				if ( 0 === $remaining ) {
					$wpdb->update(
						MvM_Newsletter::table( 'campaigns' ),
						array( 'status' => 'sent', 'sent_at' => MvM_Newsletter::now_mysql(), 'updated_at' => MvM_Newsletter::now_mysql() ),
						array( 'id' => $campaign_id, 'status' => 'sending' ),
						array( '%s', '%s', '%s' ),
						array( '%d', '%s' )
					);
				}
			}

			do_action( 'mvm_platform_audit_event', 'newsletter', 'account_unsubscribed', absint( $subscriber->id ), get_current_user_id() );
		}

		return MvM_Platform_REST::no_store_private(
			rest_ensure_response(
				array(
					'status'  => 'unsubscribed',
					'topics'  => array(),
					'message' => 'Je bent volledig afgemeld voor de nieuwsbrief.',
				)
			)
		);
	}
}
