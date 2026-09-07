<?php

defined( 'ABSPATH' ) || exit;

/**
 * GDPR-conscious self-service deletion for ordinary MvM members.
 *
 * Destructive execution requires all three controls: an authenticated REST
 * nonce, the current password, and a short-lived confirmation link delivered
 * to the account email address. Editorial and administrative accounts are
 * deliberately excluded from self-service deletion.
 */
final class MvM_Account_Deletion {
	private const TOKEN_HASH_META = '_mvm_account_delete_token_hash';
	private const TOKEN_EXPIRY_META = '_mvm_account_delete_token_expiry';
	private const TOKEN_LIFETIME = 30 * MINUTE_IN_SECONDS;
	private const REQUEST_WINDOW = HOUR_IN_SECONDS;
	private const REQUEST_LIMIT = 3;

	public static function boot(): void {
		add_shortcode( 'mvm_account_delete', array( __CLASS__, 'shortcode' ) );
		add_filter( 'do_shortcode_tag', array( __CLASS__, 'append_to_my_mierlo' ), 30, 4 );
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ), 30 );
	}

	public static function register_routes(): void {
		register_rest_route(
			MvM_Platform_REST::NAMESPACE,
			'/account/delete/request',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'permission_callback' => array( 'MvM_Platform_REST', 'require_authenticated' ),
				'callback'            => array( __CLASS__, 'request_deletion' ),
			)
		);
		register_rest_route(
			MvM_Platform_REST::NAMESPACE,
			'/account/delete/confirm',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'permission_callback' => array( 'MvM_Platform_REST', 'require_authenticated' ),
				'callback'            => array( __CLASS__, 'confirm_deletion' ),
			)
		);
	}

	/**
	 * @param string $output Shortcode output.
	 * @param string $tag    Shortcode name.
	 * @param array  $attr   Shortcode attributes.
	 * @param array  $match  Regex match data.
	 */
	public static function append_to_my_mierlo( string $output, string $tag, array $attr, array $match ): string {
		unset( $attr, $match );
		if ( 'mvm_mijn_mierlo' !== $tag || ! is_user_logged_in() || str_contains( $output, 'data-mvm-account-delete' ) ) {
			return $output;
		}

		return $output . self::shortcode();
	}

	public static function shortcode(): string {
		if ( ! is_user_logged_in() ) {
			return '';
		}

		$user = wp_get_current_user();
		self::enqueue_assets();

		ob_start();
		?>
		<section class="mvm-account-delete" data-mvm-account-delete aria-labelledby="mvm-account-delete-title">
			<div class="mvm-account-delete__card">
				<p class="mvm-account-delete__eyebrow">Privacy &amp; account</p>
				<h2 id="mvm-account-delete-title">MvM-account verwijderen</h2>
				<?php if ( self::is_protected_user( $user ) ) : ?>
					<p>Dit redactie- of beheerdersaccount kan niet via de website worden verwijderd. Neem hiervoor contact op met de hoofdbeheerder.</p>
				<?php else : ?>
					<p>Hiermee verwijder je je account en laat je gekoppelde persoonsgegevens wissen. Je nieuwsbrief wordt stopgezet en je openbare bijdragen worden verwijderd of geanonimiseerd waar de wet en de werking van het forum dat vereisen.</p>
					<div class="mvm-account-delete__warning" role="note"><strong>Let op:</strong> na de bevestiging per e-mail kan dit niet ongedaan worden gemaakt.</div>
					<form class="mvm-account-delete__form" data-mvm-account-delete-form>
						<label for="mvm-account-delete-password">Huidig wachtwoord</label>
						<input id="mvm-account-delete-password" name="password" type="password" autocomplete="current-password" required>
						<label class="mvm-account-delete__confirm"><input name="confirmed" type="checkbox" required> Ik begrijp dat mijn MvM-account definitief wordt verwijderd.</label>
						<p class="mvm-account-delete__status" data-mvm-account-delete-status role="status" aria-live="polite"></p>
						<button class="mvm-account-delete__button" type="submit">Stuur bevestigingsmail</button>
					</form>
				<?php endif; ?>
			</div>
		</section>
		<?php
		return (string) ob_get_clean();
	}

	public static function request_deletion( WP_REST_Request $request ): WP_REST_Response {
		$user = wp_get_current_user();
		if ( self::is_protected_user( $user ) ) {
			return self::error_response( 'mvm_account_delete_protected', 'Dit account kan niet via de website worden verwijderd.', 403 );
		}
		if ( ! self::consume_request_allowance( $user->ID ) ) {
			return self::error_response( 'mvm_account_delete_rate_limited', 'Er zijn te veel aanvragen gedaan. Probeer het over een uur opnieuw.', 429 );
		}

		$password  = (string) $request->get_param( 'password' );
		$confirmed = true === $request->get_param( 'confirmed' ) || 'true' === strtolower( (string) $request->get_param( 'confirmed' ) ) || '1' === (string) $request->get_param( 'confirmed' );
		if ( ! $confirmed || '' === $password || ! wp_check_password( $password, (string) $user->user_pass, $user->ID ) ) {
			$password = '';
			return self::error_response( 'mvm_account_delete_reauthentication_failed', 'Het wachtwoord klopt niet of de bevestiging ontbreekt.', 403 );
		}
		$password = '';

		$token = wp_generate_password( 48, false, false );
		update_user_meta( $user->ID, self::TOKEN_HASH_META, self::token_hash( $token ) );
		update_user_meta( $user->ID, self::TOKEN_EXPIRY_META, time() + self::TOKEN_LIFETIME );
		$link = home_url( '/inloggen/#mvm-account-delete=' . rawurlencode( $token ) );
		$sent = wp_mail(
			(string) $user->user_email,
			'Bevestig het verwijderen van je MvM-account',
			"Je hebt gevraagd om je MvM-account definitief te verwijderen.\n\nBevestig dit binnen 30 minuten via:\n{$link}\n\nHeb je dit niet aangevraagd? Dan hoef je niets te doen. Je account blijft bestaan."
		);
		$token = '';
		if ( ! $sent ) {
			delete_user_meta( $user->ID, self::TOKEN_HASH_META );
			delete_user_meta( $user->ID, self::TOKEN_EXPIRY_META );
			return self::error_response( 'mvm_account_delete_mail_failed', 'De bevestigingsmail kon niet worden verstuurd. Probeer het later opnieuw.', 503 );
		}

		do_action( 'mvm_platform_audit_event', 'account', 'deletion_requested', $user->ID, $user->ID );
		return MvM_Platform_REST::no_store_private(
			rest_ensure_response(
				array( 'message' => 'Controleer je e-mail. De bevestigingslink is 30 minuten geldig.' )
			)
		);
	}

	public static function confirm_deletion( WP_REST_Request $request ): WP_REST_Response {
		$user = wp_get_current_user();
		if ( self::is_protected_user( $user ) ) {
			return self::error_response( 'mvm_account_delete_protected', 'Dit account kan niet via de website worden verwijderd.', 403 );
		}

		$token   = trim( (string) $request->get_param( 'token' ) );
		$stored  = (string) get_user_meta( $user->ID, self::TOKEN_HASH_META, true );
		$expires = (int) get_user_meta( $user->ID, self::TOKEN_EXPIRY_META, true );
		$valid   = '' !== $token && '' !== $stored && $expires >= time() && hash_equals( $stored, self::token_hash( $token ) );
		$token   = '';
		if ( ! $valid ) {
			if ( $expires < time() ) {
				delete_user_meta( $user->ID, self::TOKEN_HASH_META );
				delete_user_meta( $user->ID, self::TOKEN_EXPIRY_META );
			}
			return self::error_response( 'mvm_account_delete_token_invalid', 'Deze bevestigingslink is ongeldig of verlopen.', 403 );
		}

		$user_id = (int) $user->ID;
		$email   = (string) $user->user_email;
		$erased  = self::run_privacy_erasers( $email );
		if ( is_wp_error( $erased ) ) {
			return self::error_response( (string) $erased->get_error_code(), (string) $erased->get_error_message(), 500 );
		}

		$newsletter_erased = self::anonymize_newsletter_subscription( $user_id, $email );
		if ( is_wp_error( $newsletter_erased ) ) {
			return self::error_response( (string) $newsletter_erased->get_error_code(), (string) $newsletter_erased->get_error_message(), 500 );
		}
		delete_user_meta( $user_id, self::TOKEN_HASH_META );
		delete_user_meta( $user_id, self::TOKEN_EXPIRY_META );
		do_action( 'mvm_platform_audit_event', 'account', 'deletion_confirmed', $user_id, $user_id );

		require_once ABSPATH . 'wp-admin/includes/user.php';
		if ( ! wp_delete_user( $user_id ) ) {
			return self::error_response( 'mvm_account_delete_failed', 'Het account kon niet volledig worden verwijderd. Neem contact op met de beheerder.', 500 );
		}

		wp_clear_auth_cookie();
		return MvM_Platform_REST::no_store_private(
			rest_ensure_response(
				array(
					'message'  => 'Je MvM-account is verwijderd.',
					'redirect' => home_url( '/' ),
				)
			)
		);
	}

	private static function enqueue_assets(): void {
		$style = MVM_PLATFORM_DIR . 'assets/account-delete.css';
		$script = MVM_PLATFORM_DIR . 'assets/account-delete.js';
		wp_enqueue_style( 'mvm-account-delete', MVM_PLATFORM_URL . 'assets/account-delete.css', array(), is_readable( $style ) ? (string) filemtime( $style ) : MVM_PLATFORM_VERSION );
		wp_enqueue_script( 'mvm-account-delete', MVM_PLATFORM_URL . 'assets/account-delete.js', array(), is_readable( $script ) ? (string) filemtime( $script ) : MVM_PLATFORM_VERSION, true );
		wp_localize_script(
			'mvm-account-delete',
			'MvMAccountDelete',
			array(
				'apiUrl' => esc_url_raw( rest_url( MvM_Platform_REST::NAMESPACE . '/account/delete/' ) ),
				'nonce'  => wp_create_nonce( 'wp_rest' ),
			)
		);
	}

	private static function is_protected_user( WP_User $user ): bool {
		return user_can( $user, 'manage_options' ) || user_can( $user, 'edit_others_posts' );
	}

	private static function consume_request_allowance( int $user_id ): bool {
		$key   = 'mvm_account_delete_' . $user_id;
		$count = (int) get_transient( $key );
		if ( $count >= self::REQUEST_LIMIT ) {
			return false;
		}
		set_transient( $key, $count + 1, self::REQUEST_WINDOW );
		return true;
	}

	private static function token_hash( string $token ): string {
		return hash_hmac( 'sha256', trim( $token ), wp_salt( 'auth' ) . '|mvm-account-delete' );
	}

	/**
	 * Run every registered WordPress/plugin eraser before the user record is
	 * removed, so PeepSo, wpForo and other integrations can erase their data.
	 *
	 * @return true|WP_Error
	 */
	private static function run_privacy_erasers( string $email ) {
		$erasers = apply_filters( 'wp_privacy_personal_data_erasers', array() );
		foreach ( (array) $erasers as $eraser ) {
			$callback = $eraser['callback'] ?? null;
			if ( ! is_callable( $callback ) ) {
				continue;
			}
			$done = false;
			for ( $page = 1; $page <= 100; $page++ ) {
				try {
					$result = call_user_func( $callback, $email, $page );
				} catch ( Throwable $exception ) {
					return new WP_Error( 'mvm_account_delete_eraser_failed', 'Persoonsgegevens konden niet veilig worden gewist.' );
				}
				if ( is_wp_error( $result ) || ! is_array( $result ) ) {
					return new WP_Error( 'mvm_account_delete_eraser_failed', 'Persoonsgegevens konden niet veilig worden gewist.' );
				}
				$done = ! empty( $result['done'] );
				if ( $done ) {
					break;
				}
			}
			if ( ! $done ) {
				return new WP_Error( 'mvm_account_delete_eraser_incomplete', 'Het wissen van persoonsgegevens is niet volledig afgerond.' );
			}
		}
		return true;
	}

	/**
	 * @return true|WP_Error
	 */
	private static function anonymize_newsletter_subscription( int $user_id, string $email ) {
		global $wpdb;

		$subscriber = MvM_Newsletter::subscriber_by_email( $email );
		if ( ! $subscriber ) {
			return true;
		}
		$anonymous_email = 'deleted-' . $user_id . '-' . strtolower( wp_generate_password( 20, false, false ) ) . '@invalid.example';
		$updated = $wpdb->update(
			MvM_Newsletter::table( 'subscribers' ),
			array(
				'email'              => $anonymous_email,
				'email_hash'         => MvM_Newsletter::email_hash( $anonymous_email ),
				'status'             => 'unsubscribed',
				'topics'             => '[]',
				'confirm_token_hash' => '',
				'confirm_expires'    => null,
				'updated_at'         => MvM_Newsletter::now_mysql(),
			),
			array( 'id' => absint( $subscriber->id ) )
		);
		if ( false === $updated ) {
			return new WP_Error( 'mvm_account_delete_newsletter_failed', 'De nieuwsbriefgegevens konden niet veilig worden geanonimiseerd.' );
		}
		$wpdb->query(
			$wpdb->prepare(
				'UPDATE ' . MvM_Newsletter::table( 'queue' ) . " SET status = 'cancelled', last_error = 'account_deleted' WHERE subscriber_id = %d AND status IN ('pending','failed')",
				absint( $subscriber->id )
			)
		);
		return true;
	}

	private static function error_response( string $code, string $message, int $status ): WP_REST_Response {
		return MvM_Platform_REST::no_store_private(
			new WP_REST_Response(
				array(
					'code'    => sanitize_key( $code ),
					'message' => $message,
				),
				$status
			)
		);
	}
}
