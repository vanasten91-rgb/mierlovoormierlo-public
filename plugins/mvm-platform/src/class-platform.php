<?php

defined( 'ABSPATH' ) || exit;

final class MvM_Platform {
	private static ?self $instance = null;
	private bool $booted = false;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {}

	public static function prepare_page_backed_routes(): void {
		add_filter( 'register_post_type_args', array( __CLASS__, 'marketplace_post_type_args' ), 10, 2 );
		add_filter( 'post_type_archive_link', array( __CLASS__, 'marketplace_archive_link' ), 10, 2 );
	}

	public static function marketplace_post_type_args( array $args, string $post_type ): array {
		if ( class_exists( 'MvM_Marketplace' ) && MvM_Marketplace::POST_TYPE === $post_type ) {
			$page = get_page_by_path( 'marktplaats', OBJECT, 'page' );
			if ( $page instanceof WP_Post && 'trash' !== $page->post_status ) {
				$args['has_archive'] = 'marktplaats-archief';
			}
		}
		return $args;
	}

	public static function marketplace_archive_link( string $link, string $post_type ): string {
		if ( class_exists( 'MvM_Marketplace' ) && MvM_Marketplace::POST_TYPE === $post_type ) {
			$page = get_page_by_path( 'marktplaats', OBJECT, 'page' );
			if ( $page instanceof WP_Post && 'publish' === $page->post_status ) {
				return (string) get_permalink( $page );
			}
		}
		return $link;
	}

	public function boot(): void {
		if ( $this->booted ) {
			return;
		}
		$this->booted = true;

		self::prepare_page_backed_routes();
		MvM_Platform_Capabilities::maybe_sync();
		MvM_Platform_Hub_Bridge::boot();
		MvM_Marketplace::boot();
		MvM_Marketplace_Moderation::boot();
		MvM_Marketplace_Frontend::boot();
		MvM_Platform_PWA::boot();
		MvM_Newsletter::boot();
		MvM_Newsletter_Frontend::boot();
		MvM_Newsletter_Account::boot();
		MvM_Newsletter_Legacy_Compat::boot();
		MvM_Account_Deletion::boot();
		MvM_Account_Login::boot();
		MvM_Local_Business_Ads::boot();
		MvM_Entrepreneur_Portal::boot();
		MvM_Offers_Page::boot();
		MvM_Homepage_Ad_Slots::boot();
		MvM_Ad_Examples_Page::boot();
		MvM_Ad_Settings_Page::boot();
		MvM_Platform_Events_Overview::boot();
		MvM_Platform_Share_Media_Compat::boot();
		MvM_Platform_Event_Meta_Normalizer::boot();
		MvM_Platform_Security_Hardening::boot();
		MvM_Platform_Featured_Image_Alt::boot();

		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_design_system' ), PHP_INT_MAX );
		add_action( 'login_enqueue_scripts', array( __CLASS__, 'enqueue_design_system' ), PHP_INT_MAX );
		add_action( 'rest_api_init', array( 'MvM_Platform_REST', 'register_routes' ) );
		add_action( 'rest_api_init', array( 'MvM_Marketplace_REST', 'register_routes' ) );
		add_action( 'rest_api_init', array( 'MvM_Platform_Content_REST', 'register_routes' ) );
		add_action( 'rest_api_init', array( 'MvM_Newsletter_REST', 'register_routes' ) );
		add_action( 'rest_api_init', array( 'MvM_Local_Business_Ads_REST', 'register_routes' ) );
		add_action( 'rest_api_init', array( 'MvM_Entrepreneur_Ads_REST', 'register_routes' ) );
		add_action( 'rest_api_init', array( 'MvM_Homepage_Ad_Slots_REST', 'register_routes' ) );
	}

	public static function enqueue_design_system(): void {
		$path = MVM_PLATFORM_DIR . 'assets/design-system.css';
		wp_enqueue_style(
			'mvm-platform-design-system',
			MVM_PLATFORM_URL . 'assets/design-system.css',
			array(),
			is_readable( $path ) ? (string) filemtime( $path ) : MVM_PLATFORM_VERSION
		);
	}
}
