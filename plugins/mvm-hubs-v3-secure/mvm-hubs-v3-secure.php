<?php
/**
 * Plugin Name: MvM Hubs v3 Secure Core
 * Description: Beveiligings- en autorisatiekern voor de vier gescheiden Mierlo voor Mierlo hubs.
 * Version: 0.3.0-rc1
 * Requires at least: 7.1
 * Requires PHP: 8.4
 * Author: Mierlo voor Mierlo
 * Text Domain: mvm-hubs-v3
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'MVM_HUBS_V3_SECURE_VERSION' ) ) {
	define( 'MVM_HUBS_V3_SECURE_VERSION', '0.3.0-rc1' );
}
if ( ! defined( 'MVM_HUBS_V3_CAPS_SCHEMA' ) ) {
	define( 'MVM_HUBS_V3_CAPS_SCHEMA', '2026.09.01-1' );
}

require_once __DIR__ . '/src/class-mvm-hubs-v3-security.php';
require_once __DIR__ . '/src/class-mvm-hubs-v3-audit.php';
require_once __DIR__ . '/src/class-mvm-hubs-v3-communications.php';
require_once __DIR__ . '/src/class-mvm-hubs-v3-admin-status.php';
require_once __DIR__ . '/src/class-mvm-hubs-v3-moderation.php';
require_once __DIR__ . '/src/class-mvm-hubs-v3-home-settings.php';
require_once __DIR__ . '/src/class-mvm-hubs-v3-maintenance.php';

register_activation_hook( __FILE__, array( 'MvM_Hubs_V3_Security', 'activate' ) );

add_action(
	'plugins_loaded',
	static function (): void {
		MvM_Hubs_V3_Security::boot();
		MvM_Hubs_V3_Audit::boot();
		MvM_Hubs_V3_Admin_Status::boot();
		MvM_Hubs_V3_Moderation::boot();
		MvM_Hubs_V3_Home_Settings::boot();
		MvM_Hubs_V3_Maintenance::boot();
	},
	20
);
