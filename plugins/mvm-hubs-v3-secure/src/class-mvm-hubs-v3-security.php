<?php

defined( 'ABSPATH' ) || exit;

final class MvM_Hubs_V3_Security {
	private const CAPS_SCHEMA_OPTION = 'mvm_hubs_v3_caps_schema';
	private const SECURE_VERSION_OPTION = 'mvm_hubs_v3_secure_version';

	private const CAPS = array(
		'business'      => 'mvm_hub3_business_access',
		'editorial'     => 'mvm_hub3_editorial_access',
		'review'        => 'mvm_hub3_review_access',
		'moderation'    => 'mvm_hub3_moderation_access',
		'team_planning' => 'mvm_hub3_team_planning_access',
		'admin'         => 'mvm_hub3_admin_access',
	);

	/**
	 * Eén rol = één duidelijke verantwoordelijkheid.
	 *
	 * - Vereniging/club/MvM-organisator: organisatiepagina, promotie en evenementen.
	 * - Ondernemer/bedrijf/winkelier: idem + vacatures.
	 * - Generic WP Event Manager organizer: event-only compatibility.
	 * - Redacteur/journalist/fotograaf/vertaler: redactionele werkruimte.
	 * - Editor: redactie + beoordelen; vacature-review alleen met de bestaande domeincapability.
	 * - Moderator: redactie + modereren.
	 * - Teamleider: redactie + beoordelen + plannen; vacature-review alleen met de bestaande domeincapability.
	 * - SysOp/Administrator: technische nood- en beheerlaag, inclusief alle werkhubs.
	 *
	 * Domeincapabilities zoals mvm_vacatures_manage_all worden hier bewust niet
	 * beheerd: Secure Core combineert ze alleen met zijn eigen reviewgrens.
	 */
	private const ROLE_CAPS = array(
		'mvm_vereniging'  => array( 'mvm_hub3_business_access' ),
		'mvm_club'        => array( 'mvm_hub3_business_access' ),
		'mvm_organisator' => array( 'mvm_hub3_business_access' ),
		'mvm_ondernemer'  => array( 'mvm_hub3_business_access' ),
		'mvm_bedrijf'     => array( 'mvm_hub3_business_access' ),
		'mvm_winkelier'   => array( 'mvm_hub3_business_access' ),
		'organizer'       => array( 'mvm_hub3_business_access' ),
		'mvm_redacteur'   => array( 'mvm_hub3_editorial_access' ),
		'mvm_fotograaf'   => array( 'mvm_hub3_editorial_access' ),
		'mvm_vertaler'    => array( 'mvm_hub3_editorial_access' ),
		'mvm_journalist'  => array( 'mvm_hub3_editorial_access' ),
		'mvm_editor'      => array( 'mvm_hub3_editorial_access', 'mvm_hub3_review_access' ),
		'mvm_moderator'   => array( 'mvm_hub3_editorial_access', 'mvm_hub3_moderation_access' ),
		'mvm_teamleider'  => array( 'mvm_hub3_editorial_access', 'mvm_hub3_review_access', 'mvm_hub3_team_planning_access' ),
		'mvm_sysop'       => array( 'mvm_hub3_business_access', 'mvm_hub3_editorial_access', 'mvm_hub3_review_access', 'mvm_hub3_moderation_access', 'mvm_hub3_team_planning_access', 'mvm_hub3_admin_access' ),
		'administrator'   => array( 'mvm_hub3_business_access', 'mvm_hub3_editorial_access', 'mvm_hub3_review_access', 'mvm_hub3_moderation_access', 'mvm_hub3_team_planning_access', 'mvm_hub3_admin_access' ),
	);

	private const HUB_PATHS = array(
		'/mijn-hub/'        => 'user',
		'/ondernemers-hub/' => 'business',
		'/redactie-hub/'    => 'editorial',
		'/beheer-hub/'      => 'admin',
	);

	public static function boot(): void {
		add_action( 'init', array( __CLASS__, 'maybe_sync_capabilities' ), 1 );
		add_action( 'send_headers', array( __CLASS__, 'send_private_headers' ), 20 );
		add_action( 'rest_api_init', array( __CLASS__, 'register_rest_routes' ) );
	}

	public static function activate(): void {
		self::sync_capabilities();
	}

	public static function is_draft_preview(): bool {
		$stylesheet = (string) get_stylesheet();
		$draft      = (string) get_option( 'wpvibe_draft_theme', '' );

		if ( '' !== $draft && $draft === $stylesheet ) {
			return true;
		}
		if ( false !== strpos( $stylesheet, 'wpvibe-draft' ) ) {
			return true;
		}
		if ( isset( $_GET['wpvibe_preview'] ) && '' !== sanitize_text_field( wp_unslash( (string) $_GET['wpvibe_preview'] ) ) ) {
			return true;
		}

		$preview_origins = array(
			isset( $_REQUEST['_wp_http_referer'] ) ? (string) wp_unslash( $_REQUEST['_wp_http_referer'] ) : '',
			isset( $_SERVER['HTTP_REFERER'] ) ? (string) wp_unslash( $_SERVER['HTTP_REFERER'] ) : '',
		);
		foreach ( $preview_origins as $origin ) {
			if ( '' !== $origin && false !== strpos( $origin, 'wpvibe_preview=' ) ) {
				return true;
			}
		}
		return false;
	}

	public static function maybe_sync_capabilities(): void {
		if ( self::is_draft_preview() ) {
			return;
		}
		if ( MVM_HUBS_V3_CAPS_SCHEMA !== (string) get_option( self::CAPS_SCHEMA_OPTION, '' ) ) {
			self::sync_capabilities();
			return;
		}
		if ( MVM_HUBS_V3_SECURE_VERSION !== (string) get_option( self::SECURE_VERSION_OPTION, '' ) ) {
			update_option( self::SECURE_VERSION_OPTION, MVM_HUBS_V3_SECURE_VERSION, false );
		}
	}

	public static function sync_capabilities(): bool {
		if ( self::is_draft_preview() ) {
			return false;
		}

		$managed_caps = array_values( self::CAPS );
		foreach ( array_keys( wp_roles()->roles ) as $role_name ) {
			$role = get_role( (string) $role_name );
			if ( ! $role ) {
				continue;
			}
			$allowed = self::ROLE_CAPS[ $role_name ] ?? array();
			foreach ( $managed_caps as $cap ) {
				if ( in_array( $cap, $allowed, true ) ) {
					$role->add_cap( $cap );
				} else {
					$role->remove_cap( $cap );
				}
			}
		}
		update_option( self::CAPS_SCHEMA_OPTION, MVM_HUBS_V3_CAPS_SCHEMA, false );
		update_option( self::SECURE_VERSION_OPTION, MVM_HUBS_V3_SECURE_VERSION, false );
		return true;
	}

	public static function capability_health(): array {
		$managed_caps  = array_values( self::CAPS );
		$roles_checked = 0;
		$drift_count   = 0;

		foreach ( array_keys( wp_roles()->roles ) as $role_name ) {
			$role = get_role( (string) $role_name );
			if ( ! $role ) {
				continue;
			}
			++$roles_checked;
			$allowed = self::ROLE_CAPS[ $role_name ] ?? array();
			foreach ( $managed_caps as $cap ) {
				$should_have = in_array( $cap, $allowed, true );
				if ( $role->has_cap( $cap ) !== $should_have ) {
					++$drift_count;
				}
			}
		}

		return array(
			'ok'            => 0 === $drift_count,
			'roles_checked' => $roles_checked,
			'drift_count'   => $drift_count,
		);
	}

	public static function can_access( string $hub, int $user_id = 0 ): bool {
		$user_id = $user_id ?: get_current_user_id();
		if ( ! $user_id ) {
			return false;
		}
		if ( 'user' === $hub ) {
			return true;
		}
		$cap = self::CAPS[ $hub ] ?? '';
		return $cap ? user_can( $user_id, $cap ) : false;
	}

	public static function can_submit_editorial( int $user_id = 0 ): bool {
		$user_id = $user_id ?: get_current_user_id();
		if ( ! $user_id || ! self::can_access( 'editorial', $user_id ) ) {
			return false;
		}
		return user_can( $user_id, 'edit_posts' ) || user_can( $user_id, 'mvm_submit_articles' );
	}

	public static function can_review( int $user_id = 0 ): bool {
		$user_id = $user_id ?: get_current_user_id();
		return $user_id > 0 && user_can( $user_id, self::CAPS['review'] );
	}

	public static function can_review_vacancies( int $user_id = 0 ): bool {
		$user_id = $user_id ?: get_current_user_id();
		if ( ! $user_id || ! self::can_access( 'editorial', $user_id ) ) {
			return false;
		}
		if ( user_can( $user_id, 'manage_options' ) ) {
			return true;
		}
		return self::can_review( $user_id ) && user_can( $user_id, 'mvm_vacatures_manage_all' );
	}

	public static function can_moderate( int $user_id = 0 ): bool {
		$user_id = $user_id ?: get_current_user_id();
		return $user_id > 0 && user_can( $user_id, self::CAPS['moderation'] );
	}

	public static function can_view_team_planning( int $user_id = 0 ): bool {
		$user_id = $user_id ?: get_current_user_id();
		return $user_id > 0 && user_can( $user_id, self::CAPS['team_planning'] );
	}

	private static function business_sections( int $user_id ): array {
		$user  = get_userdata( $user_id );
		$roles = $user instanceof WP_User ? array_values( (array) $user->roles ) : array();

		if ( array_intersect( $roles, array( 'administrator', 'mvm_sysop', 'mvm_ondernemer', 'mvm_bedrijf', 'mvm_winkelier' ) ) ) {
			return array( 'profiel', 'vacature', 'evenement' );
		}
		if ( array_intersect( $roles, array( 'mvm_vereniging', 'mvm_club', 'mvm_organisator' ) ) ) {
			return array( 'profiel', 'evenement' );
		}
		if ( in_array( 'organizer', $roles, true ) ) {
			return array( 'evenement' );
		}
		return array();
	}

	private static function editorial_sections( int $user_id ): array {
		$sections = array();
		if ( self::can_submit_editorial( $user_id ) ) {
			$sections[] = 'mijnwerk';
		}
		if ( self::can_review( $user_id ) ) {
			$sections[] = 'review';
		}
		if ( user_can( $user_id, 'mvm_hub4_news_view' ) ) {
			$sections[] = 'nieuwsradar';
		}
		if ( user_can( $user_id, 'mvm_hub4_sources_view' ) ) {
			$sections[] = 'bronnen';
		}
		$sections[] = 'mail';
		if ( self::can_review_vacancies( $user_id ) ) {
			$sections[] = 'vacatures';
		}
		if ( self::can_view_team_planning( $user_id ) ) {
			$sections[] = 'planning';
		}
		$sections[] = 'team';
		$sections[] = 'communicatie';
		if ( self::can_moderate( $user_id ) ) {
			$sections[] = 'moderatie';
		}
		return $sections;
	}

	public static function allowed_sections( string $hub, int $user_id = 0 ): array {
		$user_id = $user_id ?: get_current_user_id();
		if ( ! $user_id || ! self::can_access( $hub, $user_id ) ) {
			return array();
		}

		if ( 'user' === $hub ) {
			return array( 'organisatieaccount', 'opgeslagen' );
		}
		if ( 'business' === $hub ) {
			return self::business_sections( $user_id );
		}
		if ( 'admin' === $hub ) {
			return array( 'layouts', 'homeblokken', 'veiligheid', 'integraties', 'onderhoud', 'herstel', 'organisaties' );
		}
		if ( 'editorial' === $hub ) {
			return self::editorial_sections( $user_id );
		}
		return array();
	}

	public static function primary_hub( int $user_id = 0 ): string {
		$user_id = $user_id ?: get_current_user_id();
		if ( ! $user_id ) {
			return '';
		}
		foreach ( array( 'admin', 'editorial', 'business' ) as $hub ) {
			if ( self::can_access( $hub, $user_id ) ) {
				return $hub;
			}
		}
		return 'user';
	}

	public static function request_hub(): string {
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/';
		$path        = wp_parse_url( $request_uri, PHP_URL_PATH );
		$path        = '/' . trim( is_string( $path ) ? $path : '/', '/' ) . '/';
		return self::HUB_PATHS[ $path ] ?? '';
	}

	public static function send_private_headers(): void {
		if ( ! self::request_hub() ) {
			return;
		}
		nocache_headers();
		header( 'Cache-Control: private, no-store, max-age=0, must-revalidate', true );
		header( 'Pragma: no-cache', true );
		header( 'X-Robots-Tag: noindex, nofollow, noarchive', true );
		header( 'Referrer-Policy: same-origin', true );
		header( 'X-Frame-Options: SAMEORIGIN', true );
		header( 'X-Content-Type-Options: nosniff', true );
	}

	public static function register_rest_routes(): void {
		register_rest_route(
			'mvm-hubs/v3',
			'/context',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'permission_callback' => static function (): bool {
					return is_user_logged_in();
				},
				'callback'            => static function (): WP_REST_Response {
					$allowed = array( 'user' );
					foreach ( array( 'business', 'editorial', 'admin' ) as $hub ) {
						if ( MvM_Hubs_V3_Security::can_access( $hub ) ) {
							$allowed[] = $hub;
						}
					}
					$sections = array();
					foreach ( $allowed as $hub ) {
						$sections[ $hub ] = MvM_Hubs_V3_Security::allowed_sections( $hub );
					}
					$response = new WP_REST_Response(
						array(
							'version'     => MVM_HUBS_V3_SECURE_VERSION,
							'primary_hub' => MvM_Hubs_V3_Security::primary_hub(),
							'hubs'        => $allowed,
							'sections'    => $sections,
							'permissions' => array(
								'submit_editorial' => MvM_Hubs_V3_Security::can_submit_editorial(),
								'review'           => MvM_Hubs_V3_Security::can_review(),
								'vacancy_review'   => MvM_Hubs_V3_Security::can_review_vacancies(),
								'moderate'         => MvM_Hubs_V3_Security::can_moderate(),
								'team_planning'    => MvM_Hubs_V3_Security::can_view_team_planning(),
							),
						),
						200
					);
					$response->header( 'Cache-Control', 'private, no-store, max-age=0, must-revalidate' );
					return $response;
				},
			)
		);
	}
}
