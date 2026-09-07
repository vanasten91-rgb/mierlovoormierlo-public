<?php
/**
 * Plugin Name: MvM Platform
 * Description: Gedeelde MvM-platformmodule voor sitewide account-, content-, nieuwsbrief-, PWA- en frontendservices.
 * Version: 0.8.1
 * Requires at least: 7.1
 * Requires PHP: 8.4
 * Author: Mierlo voor Mierlo
 * Text Domain: mvm-platform
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/bootstrap.php';

register_activation_hook( __FILE__, array( 'MvM_Platform_Module', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'MvM_Platform_Module', 'deactivate' ) );

add_action(
	'plugins_loaded',
	static function (): void {
		MvM_Platform_Module::boot();
	},
	20
);
