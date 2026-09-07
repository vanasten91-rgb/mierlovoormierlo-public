<?php
/**
 * Plugin Name: MvM Hub
 * Description: Centrale MvM werkruimte met modulair geladen Mijn Mierlo, Organisatie, Nieuwsroom, Communicatie en Techniek.
 * Version: 0.1.0-alpha5
 * Author: Mierlo voor Mierlo
 * Requires at least: 7.1
 * Requires PHP: 8.4
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'MVM_HUB_VERSION', '0.1.0-alpha5' );
define( 'MVM_HUB_FILE', __FILE__ );
define( 'MVM_HUB_DIR', plugin_dir_path( __FILE__ ) );

require_once MVM_HUB_DIR . 'core/class-capabilities.php';
require_once MVM_HUB_DIR . 'core/class-privacy.php';
require_once MVM_HUB_DIR . 'core/class-audit.php';
require_once MVM_HUB_DIR . 'core/class-object-access.php';
require_once MVM_HUB_DIR . 'core/class-communications-policy.php';
require_once MVM_HUB_DIR . 'core/class-hub-security-policy.php';
require_once MVM_HUB_DIR . 'core/class-release-control.php';
require_once MVM_HUB_DIR . 'core/class-runtime-gates.php';
require_once MVM_HUB_DIR . 'core/class-role-capability-bundles.php';
require_once MVM_HUB_DIR . 'core/class-cron-ownership.php';
require_once MVM_HUB_DIR . 'core/class-cron-cutover.php';
require_once MVM_HUB_DIR . 'core/class-workspaces.php';
require_once MVM_HUB_DIR . 'core/class-workspace-sections.php';
require_once MVM_HUB_DIR . 'core/class-module-descriptors.php';
require_once MVM_HUB_DIR . 'core/class-router.php';
require_once MVM_HUB_DIR . 'core/class-assets.php';
require_once MVM_HUB_DIR . 'core/class-shell.php';
require_once MVM_HUB_DIR . 'core/class-shell-renderer.php';
require_once MVM_HUB_DIR . 'core/class-shell-preview-rest-controller.php';
require_once MVM_HUB_DIR . 'core/class-release-control-rest-controller.php';
require_once MVM_HUB_DIR . 'core/class-release-control-abilities.php';

require_once MVM_HUB_DIR . 'integrations/encyclopedie/class-smart-links-context.php';
require_once MVM_HUB_DIR . 'integrations/encyclopedie/class-smart-links-target-search.php';

require_once MVM_HUB_DIR . 'integrations/mail/interface-mail-provider.php';
require_once MVM_HUB_DIR . 'integrations/mail/interface-private-attachment-store.php';
require_once MVM_HUB_DIR . 'integrations/mail/interface-attachment-normalizer.php';
require_once MVM_HUB_DIR . 'integrations/mail/interface-attachment-scanner.php';
require_once MVM_HUB_DIR . 'integrations/mail/interface-draft-store.php';
require_once MVM_HUB_DIR . 'integrations/mail/interface-communications-audit.php';
require_once MVM_HUB_DIR . 'integrations/mail/interface-mailbox-access.php';
require_once MVM_HUB_DIR . 'integrations/mail/interface-communications-action-security.php';
require_once MVM_HUB_DIR . 'integrations/mail/interface-mail-delivery-security.php';
require_once MVM_HUB_DIR . 'integrations/mail/class-private-storage-root.php';
require_once MVM_HUB_DIR . 'integrations/mail/class-legacy-readonly-mail-provider.php';
require_once MVM_HUB_DIR . 'integrations/mail/class-legacy-editorial-mailbox-access.php';
require_once MVM_HUB_DIR . 'integrations/mail/class-configured-mailbox-access.php';
require_once MVM_HUB_DIR . 'integrations/mail/class-dedicated-smtp-mail-provider.php';
require_once MVM_HUB_DIR . 'integrations/mail/class-dedicated-imap-smtp-mail-provider.php';
require_once MVM_HUB_DIR . 'integrations/mail/class-folder-scoped-imap-mail-provider.php';
require_once MVM_HUB_DIR . 'integrations/mail/class-sent-archiving-mail-provider.php';
require_once MVM_HUB_DIR . 'integrations/mail/class-v5-imap-attachment-reader.php';
require_once MVM_HUB_DIR . 'integrations/mail/class-encrypted-filesystem-draft-store.php';
require_once MVM_HUB_DIR . 'integrations/mail/class-filesystem-private-attachment-store.php';
require_once MVM_HUB_DIR . 'integrations/mail/class-default-attachment-normalizer.php';
require_once MVM_HUB_DIR . 'integrations/mail/class-filter-attachment-scanner.php';
require_once MVM_HUB_DIR . 'integrations/mail/class-private-http-attachment-scanner.php';
require_once MVM_HUB_DIR . 'integrations/mail/class-clamav-attachment-scanner.php';
require_once MVM_HUB_DIR . 'integrations/mail/class-hub4-communications-audit.php';
require_once MVM_HUB_DIR . 'integrations/mail/class-wordpress-step-up-authenticator.php';
require_once MVM_HUB_DIR . 'integrations/mail/class-default-communications-action-security.php';
require_once MVM_HUB_DIR . 'integrations/mail/class-default-mail-delivery-security.php';

require_once MVM_HUB_DIR . 'integrations/peepso/interface-internal-message-provider.php';
require_once MVM_HUB_DIR . 'integrations/peepso/class-legacy-readonly-internal-message-provider.php';
require_once MVM_HUB_DIR . 'integrations/peepso/class-peepso8-internal-message-provider.php';

require_once MVM_HUB_DIR . 'modules/newsroom/news/class-news-workflow.php';
require_once MVM_HUB_DIR . 'modules/newsroom/news/class-news-read-model.php';
require_once MVM_HUB_DIR . 'modules/newsroom/news/class-news-write-service.php';
require_once MVM_HUB_DIR . 'modules/newsroom/news/class-smart-links-rest-controller.php';
require_once MVM_HUB_DIR . 'modules/newsroom/news/class-smart-links-target-search-rest-controller.php';
require_once MVM_HUB_DIR . 'modules/newsroom/assignments/class-assignment-workflow.php';
require_once MVM_HUB_DIR . 'modules/newsroom/assignments/class-assignments-read-model.php';
require_once MVM_HUB_DIR . 'modules/newsroom/assignments/class-assignment-write-service.php';
require_once MVM_HUB_DIR . 'modules/newsroom/class-news-and-assignments-read-rest-controller.php';
require_once MVM_HUB_DIR . 'modules/newsroom/class-newsroom-write-rest-controller.php';
require_once MVM_HUB_DIR . 'modules/newsroom/read/class-newsroom-read-model.php';
require_once MVM_HUB_DIR . 'modules/newsroom/read/class-newsroom-read-rest-controller.php';
require_once MVM_HUB_DIR . 'modules/newsroom/read/class-newsroom-secondary-read-model.php';
require_once MVM_HUB_DIR . 'modules/newsroom/read/class-newsroom-secondary-read-rest-controller.php';
require_once MVM_HUB_DIR . 'modules/newsroom/read/class-newsroom-platform-write-service.php';
require_once MVM_HUB_DIR . 'modules/newsroom/read/class-newsroom-editor-detail-service.php';
require_once MVM_HUB_DIR . 'modules/newsroom/read/class-newsroom-platform-write-rest-controller.php';
require_once MVM_HUB_DIR . 'modules/newsroom/team/class-team-read-model.php';
require_once MVM_HUB_DIR . 'modules/newsroom/team/class-team-read-rest-controller.php';
require_once MVM_HUB_DIR . 'modules/newsroom/dashboard/class-next-actions.php';
require_once MVM_HUB_DIR . 'modules/newsroom/dashboard/class-today-read-model.php';
require_once MVM_HUB_DIR . 'modules/newsroom/dashboard/class-today-rest-controller.php';
require_once MVM_HUB_DIR . 'modules/newsroom/class-newsroom-preview-renderer.php';
require_once MVM_HUB_DIR . 'modules/newsroom/class-newsroom-runtime-renderer.php';
require_once MVM_HUB_DIR . 'modules/newsroom/class-newsroom-module.php';

require_once MVM_HUB_DIR . 'modules/communications/class-mail-reply-builder.php';
require_once MVM_HUB_DIR . 'modules/communications/class-mail-compose-validator.php';
require_once MVM_HUB_DIR . 'modules/communications/class-mail-query.php';
require_once MVM_HUB_DIR . 'modules/communications/class-mail-draft-service.php';
require_once MVM_HUB_DIR . 'modules/communications/class-mail-attachment-service.php';
require_once MVM_HUB_DIR . 'modules/communications/class-incoming-mail-attachment-service.php';
require_once MVM_HUB_DIR . 'modules/communications/class-mail-message-preferences.php';
require_once MVM_HUB_DIR . 'modules/communications/class-mail-sender-blocklist.php';
require_once MVM_HUB_DIR . 'modules/communications/class-mail-folder-service.php';
require_once MVM_HUB_DIR . 'modules/communications/class-mail-delivery-guard.php';
require_once MVM_HUB_DIR . 'modules/communications/class-mail-delivery-service.php';
require_once MVM_HUB_DIR . 'modules/communications/class-mail-read-projector.php';
require_once MVM_HUB_DIR . 'modules/communications/class-mail-read-service.php';
require_once MVM_HUB_DIR . 'modules/communications/class-internal-message-projector.php';
require_once MVM_HUB_DIR . 'modules/communications/class-internal-message-read-service.php';
require_once MVM_HUB_DIR . 'modules/communications/class-internal-message-service.php';
require_once MVM_HUB_DIR . 'modules/communications/class-communications-service-factory.php';
require_once MVM_HUB_DIR . 'modules/communications/class-mail-read-rest-controller.php';
require_once MVM_HUB_DIR . 'modules/communications/class-internal-message-read-rest-controller.php';
require_once MVM_HUB_DIR . 'modules/communications/class-internal-message-write-rest-controller.php';
require_once MVM_HUB_DIR . 'modules/communications/class-communications-preview-renderer.php';
require_once MVM_HUB_DIR . 'modules/communications/class-communications-runtime-renderer.php';
require_once MVM_HUB_DIR . 'modules/communications/class-communications-module.php';

require_once MVM_HUB_DIR . 'core/class-staff-login-recaptcha.php';
require_once MVM_HUB_DIR . 'core/class-hub-runtime.php';
require_once MVM_HUB_DIR . 'core/class-kernel.php';

add_action( 'plugins_loaded', array( 'MVM\\Hub\\Core\\Kernel', 'boot' ), 30 );
