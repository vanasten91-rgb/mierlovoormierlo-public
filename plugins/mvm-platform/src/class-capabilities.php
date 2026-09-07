<?php

defined( 'ABSPATH' ) || exit;

final class MvM_Platform_Capabilities {
	public const VERSION_OPTION = 'mvm_platform_capability_version';
	public const VERSION        = '5';

	public const ENTREPRENEUR_ROLE      = 'mvm_ondernemer';
	public const LOCAL_ADS_SELF_MANAGE  = 'mvm_local_ads_self_manage';
	public const MARKETPLACE_MODERATE   = 'mvm_marketplace_moderate';
	public const NEWSLETTER_MANAGE      = 'mvm_newsletter_manage';
	public const PWA_MANAGE             = 'mvm_pwa_manage';
	public const LOCAL_ADS_MANAGE       = 'mvm_local_ads_manage';
	public const LOCAL_ADS_ADMIN        = 'mvm_local_ads_admin';

	private const ORGANIZATION_SELF_SERVICE_ROLES = array(
		'mvm_vereniging',
		'mvm_club',
		'mvm_organisator',
		'mvm_ondernemer',
		'mvm_bedrijf',
		'mvm_winkelier',
	);

	private const ORGANIZATION_ROLE_LABELS = array(
		'mvm_vereniging' => 'Vereniging',
		'mvm_club'       => 'Club',
		'mvm_organisator' => 'Organisator',
		'mvm_ondernemer' => 'Ondernemer',
		'mvm_bedrijf'    => 'Bedrijf',
		'mvm_winkelier'  => 'Winkelier',
	);

	/**
	 * Narrow capability matrix. Never add broad wp-admin capabilities here.
	 * Organization self-service roles can manage only their own local-business
	 * offers/promotions through the frontend portal. They never receive staff
	 * moderation/admin capabilities from this matrix.
	 */
	private static function role_matrix(): array {
		$matrix = array(
			'administrator' => array(
				self::MARKETPLACE_MODERATE,
				self::NEWSLETTER_MANAGE,
				self::PWA_MANAGE,
				self::LOCAL_ADS_MANAGE,
				self::LOCAL_ADS_ADMIN,
			),
			'mvm_sysop' => array(
				self::MARKETPLACE_MODERATE,
				self::NEWSLETTER_MANAGE,
				self::PWA_MANAGE,
				self::LOCAL_ADS_MANAGE,
				self::LOCAL_ADS_ADMIN,
			),
			'mvm_teamleider' => array(
				self::MARKETPLACE_MODERATE,
				self::NEWSLETTER_MANAGE,
				self::PWA_MANAGE,
				self::LOCAL_ADS_MANAGE,
			),
			'mvm_editor' => array(
				self::MARKETPLACE_MODERATE,
				self::NEWSLETTER_MANAGE,
				self::PWA_MANAGE,
				self::LOCAL_ADS_MANAGE,
			),
			'mvm_moderator' => array(
				self::MARKETPLACE_MODERATE,
			),
		);

		foreach ( self::ORGANIZATION_SELF_SERVICE_ROLES as $role_name ) {
			$matrix[ $role_name ] = array( self::LOCAL_ADS_SELF_MANAGE );
		}

		return $matrix;
	}

	public static function activate(): void {
		self::sync();
	}

	public static function maybe_sync(): void {
		if ( self::VERSION !== (string) get_option( self::VERSION_OPTION, '' ) ) {
			self::sync();
		}
	}

	public static function sync(): void {
		self::ensure_organization_roles();

		$managed_caps = array(
			self::LOCAL_ADS_SELF_MANAGE,
			self::MARKETPLACE_MODERATE,
			self::NEWSLETTER_MANAGE,
			self::PWA_MANAGE,
			self::LOCAL_ADS_MANAGE,
			self::LOCAL_ADS_ADMIN,
		);
		$matrix = self::role_matrix();

		foreach ( wp_roles()->roles as $role_name => $role_data ) {
			unset( $role_data );
			$role = get_role( (string) $role_name );
			if ( ! $role ) {
				continue;
			}

			$allowed = $matrix[ $role_name ] ?? array();
			foreach ( $managed_caps as $capability ) {
				if ( in_array( $capability, $allowed, true ) ) {
					$role->add_cap( $capability );
				} else {
					$role->remove_cap( $capability );
				}
			}
		}

		update_option( self::VERSION_OPTION, self::VERSION, false );
	}

	private static function ensure_organization_roles(): void {
		foreach ( self::ORGANIZATION_ROLE_LABELS as $role_name => $label ) {
			$role = get_role( $role_name );
			if ( ! $role ) {
				$role = add_role(
					$role_name,
					$label,
					array(
						'read'                       => true,
						self::LOCAL_ADS_SELF_MANAGE => true,
					)
				);
			}

			if ( $role instanceof WP_Role ) {
				$role->add_cap( 'read' );
				$role->add_cap( self::LOCAL_ADS_SELF_MANAGE );
			}
		}
	}
}
