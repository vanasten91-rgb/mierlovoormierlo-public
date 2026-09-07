<?php

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'MVM_PLATFORM_VERSION' ) ) {
	define( 'MVM_PLATFORM_VERSION', '0.8.1' );
}
if ( ! defined( 'MVM_PLATFORM_FILE' ) ) {
	define( 'MVM_PLATFORM_FILE', __FILE__ );
}
if ( ! defined( 'MVM_PLATFORM_DIR' ) ) {
	define( 'MVM_PLATFORM_DIR', trailingslashit( __DIR__ ) );
}
if ( ! defined( 'MVM_PLATFORM_URL' ) ) {
	define( 'MVM_PLATFORM_URL', plugin_dir_url( __FILE__ ) );
}

// Embedded Hub 1.1 local-ads source manifest:
// class-local-business-ads.php, class-local-business-ad-widget.php,
// class-local-business-ads-rest.php, class-entrepreneur-ads-rest.php,
// class-entrepreneur-portal.php, class-offers-page.php,
// class-homepage-ad-slots.php, class-homepage-ad-slots-rest.php,
// class-ad-examples-page.php, class-ad-settings-page.php.
require_once MVM_PLATFORM_DIR . 'src/class-capabilities.php';
require_once MVM_PLATFORM_DIR . 'src/class-rest.php';
require_once MVM_PLATFORM_DIR . 'src/class-hub-bridge.php';
require_once MVM_PLATFORM_DIR . 'src/marketplace/class-marketplace.php';
require_once MVM_PLATFORM_DIR . 'src/marketplace/class-marketplace-rest.php';
require_once MVM_PLATFORM_DIR . 'src/marketplace/class-marketplace-frontend.php';
require_once MVM_PLATFORM_DIR . 'src/marketplace/class-marketplace-moderation.php';
require_once MVM_PLATFORM_DIR . 'src/pwa/class-content-rest.php';
require_once MVM_PLATFORM_DIR . 'src/pwa/class-pwa.php';
require_once MVM_PLATFORM_DIR . 'src/newsletter/class-newsletter.php';
require_once MVM_PLATFORM_DIR . 'src/newsletter/class-newsletter-content.php';
require_once MVM_PLATFORM_DIR . 'src/newsletter/class-newsletter-template.php';
require_once MVM_PLATFORM_DIR . 'src/newsletter/class-newsletter-delivery.php';
require_once MVM_PLATFORM_DIR . 'src/newsletter/class-newsletter-rest.php';
require_once MVM_PLATFORM_DIR . 'src/newsletter/class-newsletter-frontend.php';
require_once MVM_PLATFORM_DIR . 'src/newsletter/class-newsletter-account.php';
require_once MVM_PLATFORM_DIR . 'src/newsletter/class-newsletter-legacy-compat.php';
require_once MVM_PLATFORM_DIR . 'src/account/class-account-deletion.php';
require_once MVM_PLATFORM_DIR . 'src/account/class-account-login.php';
require_once MVM_PLATFORM_DIR . 'src/local-ads/class-local-business-ads.php';
require_once MVM_PLATFORM_DIR . 'src/local-ads/class-local-business-ad-widget.php';
require_once MVM_PLATFORM_DIR . 'src/local-ads/class-local-business-ads-rest.php';
require_once MVM_PLATFORM_DIR . 'src/local-ads/class-entrepreneur-ads-rest.php';
require_once MVM_PLATFORM_DIR . 'src/local-ads/class-entrepreneur-portal.php';
require_once MVM_PLATFORM_DIR . 'src/local-ads/class-offers-page.php';
require_once MVM_PLATFORM_DIR . 'src/local-ads/class-homepage-ad-slots.php';
require_once MVM_PLATFORM_DIR . 'src/local-ads/class-homepage-ad-slots-rest.php';
require_once MVM_PLATFORM_DIR . 'src/local-ads/class-ad-examples-page.php';
require_once MVM_PLATFORM_DIR . 'src/local-ads/class-ad-settings-page.php';
require_once MVM_PLATFORM_DIR . 'src/site/class-events-overview.php';
require_once MVM_PLATFORM_DIR . 'src/site/class-share-media-compat.php';
require_once MVM_PLATFORM_DIR . 'src/site/class-event-meta-normalizer.php';
require_once MVM_PLATFORM_DIR . 'src/site/class-security-hardening.php';
require_once MVM_PLATFORM_DIR . 'src/site/class-featured-image-alt.php';
require_once MVM_PLATFORM_DIR . 'src/class-platform.php';

final class MvM_Platform_Module {
	private const PUBLIC_PAGES = array(
		'marktplaats' => array(
			'title'   => 'Marktplaats',
			'content' => '[mvm_marktplaats]',
		),
		'aanbiedingen' => array(
			'title'   => 'Aanbiedingen',
			'content' => '[mvm_aanbiedingen]',
		),
	);

	public static function activate(): void {
		self::ensure_public_pages();
		MvM_Platform::prepare_page_backed_routes();
		MvM_Platform_Capabilities::activate();
		MvM_Marketplace::activate();
		MvM_Newsletter::activate();
		MvM_Newsletter_Frontend::activate();
		MvM_Local_Business_Ads::activate();
		MvM_Entrepreneur_Portal::activate();
		MvM_Offers_Page::activate();
		MvM_Ad_Examples_Page::activate();
		MvM_Ad_Settings_Page::activate();
	}

	public static function deactivate(): void {
		MvM_Marketplace::deactivate();
		MvM_Newsletter::deactivate();
		MvM_Newsletter_Frontend::deactivate();
		MvM_Local_Business_Ads::deactivate();
		MvM_Entrepreneur_Portal::deactivate();
		MvM_Offers_Page::deactivate();
		MvM_Ad_Examples_Page::deactivate();
		MvM_Ad_Settings_Page::deactivate();
	}

	public static function boot(): void {
		MvM_Platform::instance()->boot();
	}

	private static function ensure_public_pages(): void {
		foreach ( self::PUBLIC_PAGES as $slug => $page_data ) {
			$existing = get_page_by_path( $slug, OBJECT, 'page' );
			if ( $existing instanceof WP_Post && 'trash' !== $existing->post_status ) {
				continue;
			}

			$result = wp_insert_post(
				array(
					'post_type'    => 'page',
					'post_status'  => 'publish',
					'post_title'   => $page_data['title'],
					'post_name'    => $slug,
					'post_content' => $page_data['content'],
					'comment_status' => 'closed',
					'ping_status'    => 'closed',
				),
				true
			);

			if ( is_wp_error( $result ) ) {
				error_log( sprintf( 'MvM Platform kon pagina %s niet aanmaken: %s', $slug, $result->get_error_message() ) );
			}
		}
	}
}
