'use strict';

const fs = require('node:fs');
const path = require('node:path');
const root = path.resolve(__dirname, '..');
const pluginDir = path.join(root, 'plugins', 'mvm-hub');
const read = (relative) => fs.readFileSync(path.join(root, relative), 'utf8');
const walk = (dir) => fs.readdirSync(dir, { withFileTypes: true }).flatMap((entry) => {
  const full = path.join(dir, entry.name);
  return entry.isDirectory() ? walk(full) : [full];
});
function fail(message) { console.error(`FAIL: ${message}`); process.exitCode = 1; }
function ok(condition, message) { if (!condition) fail(message); }

ok(fs.existsSync(pluginDir), 'plugins/mvm-hub must exist');
const phpFiles = walk(pluginDir).filter((file) => file.endsWith('.php'));
const allPhp = phpFiles.map((file) => fs.readFileSync(file, 'utf8')).join('\n');
const explicitMutationNames = /class-(?:news-write-service|assignment-write-service|newsroom-write-rest-controller|newsroom-platform-write-service|newsroom-platform-write-rest-controller|staff-collaboration|audit|role-capability-bundles|cron-ownership)\.php$/;
const mutationFiles = phpFiles.filter((file) => explicitMutationNames.test(file));
const readOnlyFiles = phpFiles.filter((file) => {
  const inBaselineArea = /[\\/](?:core|modules[\\/]newsroom|integrations[\\/]encyclopedie)[\\/]/.test(file);
  return inBaselineArea && !mutationFiles.includes(file);
});
const readOnlyPhp = readOnlyFiles.map((file) => fs.readFileSync(file, 'utf8')).join('\n');
const roleMigrationFiles = phpFiles.filter((file) => /class-(?:role-capability-bundles|v5-role-uat-role-map)\.php$/.test(file));
const businessPhp = phpFiles.filter((file) => !roleMigrationFiles.includes(file)).map((file) => fs.readFileSync(file, 'utf8')).join('\n');
const dbWriteOffenders = readOnlyFiles.filter((file) => /\$wpdb\s*->\s*(?:insert|update|delete|replace)\s*\(/m.test(fs.readFileSync(file, 'utf8')));
const communicationsPhp = [
  ...walk(path.join(pluginDir, 'modules', 'communications')),
  ...walk(path.join(pluginDir, 'integrations', 'mail')),
].filter((file) => file.endsWith('.php')).map((file) => fs.readFileSync(file, 'utf8')).join('\n');

const bootstrap = read('plugins/mvm-hub/mvm-hub.php');
const capabilities = read('plugins/mvm-hub/core/class-capabilities.php');
const privacy = read('plugins/mvm-hub/core/class-privacy.php');
const communications = read('plugins/mvm-hub/core/class-communications-policy.php');
const runtimeGates = read('plugins/mvm-hub/core/class-runtime-gates.php');
const roleBundles = read('plugins/mvm-hub/core/class-role-capability-bundles.php');
const cronOwnership = read('plugins/mvm-hub/core/class-cron-ownership.php');
const kernel = read('plugins/mvm-hub/core/class-kernel.php');
const router = read('plugins/mvm-hub/core/class-router.php');
const moduleFile = read('plugins/mvm-hub/modules/newsroom/class-newsroom-module.php');
const newsroomAdmin = read('plugins/mvm-hub/modules/newsroom/class-newsroom-admin.php');
const controller = read('plugins/mvm-hub/modules/newsroom/dashboard/class-today-rest-controller.php');
const model = read('plugins/mvm-hub/modules/newsroom/dashboard/class-today-read-model.php');
const guidance = read('plugins/mvm-hub/modules/newsroom/dashboard/class-next-actions.php');
const workflow = read('plugins/mvm-hub/modules/newsroom/news/class-news-workflow.php');
const smartController = read('plugins/mvm-hub/modules/newsroom/news/class-smart-links-rest-controller.php');
const smartContext = read('plugins/mvm-hub/integrations/encyclopedie/class-smart-links-context.php');
const mailProvider = read('plugins/mvm-hub/integrations/mail/interface-mail-provider.php');
const messageProvider = read('plugins/mvm-hub/integrations/peepso/interface-internal-message-provider.php');
const communicationsModule = read('plugins/mvm-hub/modules/communications/class-communications-module.php');
const writeController = read('plugins/mvm-hub/modules/communications/class-communications-write-rest-controller.php');
const mailWriteController = read('plugins/mvm-hub/modules/communications/class-mail-write-rest-controller.php');
const internalWriteController = read('plugins/mvm-hub/modules/communications/class-internal-message-write-rest-controller.php');

ok(/namespace MVM\\Hub\\/m.test(allPhp), 'new Hub code must use the MVM\\Hub namespace');
ok(!/final\s+class\s+MvM_Hub4_/m.test(allPhp), 'new Hub must not declare Hub4 global classes');
ok(!/mvm-hub4-rc-direct|plugins\/mvm-hub4/m.test(bootstrap), 'bootstrap must not require Hub4 implementation files');
ok(!/require_once[^;]+compat\//m.test(bootstrap), 'new Hub bootstrap must not load legacy compat files');
ok(!/add_rewrite_rule|flush_rewrite_rules/m.test(allPhp), 'rebuild must not add or flush rewrite rules');
ok(!/wp_enqueue_scripts/m.test(allPhp), 'new Hub must not load site-wide frontend UI assets');
const adminEnqueueFiles = phpFiles.filter((file) => /admin_enqueue_scripts/m.test(fs.readFileSync(file, 'utf8')));
const normalizePath = (file) => path.relative(pluginDir, file).split(path.sep).join('/');
ok(adminEnqueueFiles.every((file) => normalizePath(file) === 'modules/newsroom/class-newsroom-admin.php'), `admin UI assets must stay owned by the private Newsroom screen; offenders: ${adminEnqueueFiles.map((f) => normalizePath(f)).join(', ')}`);
ok(newsroomAdmin.includes("if ( ! self::is_newsroom_screen() || ! self::can_enter() )"), 'Newsroom admin assets must be screen- and capability-scoped before enqueue');

ok(!/register_activation_hook|register_deactivation_hook/m.test(allPhp), 'new Hub must not register automatic activation/deactivation migrations');
ok(!/\bdbDelta\s*\(/m.test(allPhp), 'new Hub must not auto-create or mutate schemas');
const roleCreationOffenders = phpFiles.filter((file) => !file.endsWith('class-role-capability-bundles.php') && /\badd_role\s*\(/m.test(fs.readFileSync(file, 'utf8')));
ok(!/\bremove_role\s*\(/m.test(allPhp), 'new Hub must not remove roles');
ok(roleCreationOffenders.length === 0, `role creation must stay isolated to the gated migration bundle; offenders: ${roleCreationOffenders.map((f) => normalizePath(f)).join(', ')}`);

ok(roleBundles.includes('Runtime_Gates::capability_reconciliation_enabled()'), 'role reconciliation must check its dedicated gate');
ok(runtimeGates.includes("'MVM_HUB_ENABLE_CAPABILITY_RECONCILIATION'"), 'cap reconciliation needs explicit enable constant');
ok(runtimeGates.includes("'MVM_HUB_ALLOW_PRODUCTION_CAPABILITY_RECONCILIATION'"), 'production reconciliation needs second override');
ok(kernel.includes('if ( Runtime_Gates::capability_reconciliation_enabled() )'), 'kernel must register reconciliation only when gated');
ok(/->\s*(?:add_cap|remove_cap)\s*\(/m.test(roleBundles), 'explicit role migration bundle must own capability writes');
ok(!/->\s*(?:add_cap|remove_cap)\s*\(/m.test(businessPhp), 'business logic outside role migration must not mutate role capabilities');

ok(dbWriteOffenders.length === 0, `read-side Newsroom/Core/Smart Links must not write DB rows; offenders: ${dbWriteOffenders.map((f) => path.relative(pluginDir, f)).join(', ')}`);
ok(!/\b(?:update_option|add_option|delete_option)\s*\(/m.test(readOnlyPhp), 'read-side Newsroom/Core/Smart Links must not mutate options');

ok(runtimeGates.includes("'MVM_HUB_ENABLE_CRON_TAKEOVER'"), 'cron ownership needs explicit enable constant');
ok(runtimeGates.includes("'MVM_HUB_ALLOW_PRODUCTION_CRON_TAKEOVER'"), 'production cron ownership needs second override');
ok(cronOwnership.includes('Runtime_Gates::cron_takeover_enabled()'), 'cron mutation owner must fail closed behind the dedicated takeover gate');
ok(cronOwnership.includes('remove_all_actions( self::NEWSRADAR_HOOK )') && cronOwnership.includes('remove_all_actions( self::AI_AGENDA_HOOK )'), 'cron takeover must remove legacy callbacks before attaching central owners');

ok(runtimeGates.includes("'MVM_HUB_ENABLE_NEWSROOM_WRITES'"), 'Newsroom writes need explicit enable constant');
ok(runtimeGates.includes("'MVM_HUB_ALLOW_PRODUCTION_NEWSROOM_WRITES'"), 'production Newsroom writes need second override');
ok(moduleFile.includes('if ( Runtime_Gates::newsroom_writes_enabled() )'), 'Newsroom mutation route registration must be gated');
const mutationPhp = mutationFiles.map((f) => fs.readFileSync(f, 'utf8')).join('\n');
ok(!/mvm_hub4_(?:assignment_manage|news_review|dossier_manage|calendar_manage|media_manage|distribution_prepare|correction_manage)/m.test(mutationPhp), 'new mutation services must not authorize with legacy Hub4 write caps');

ok(!/\bwp_mail\s*\(/m.test(allPhp), 'new Hub must not call wp_mail');
ok(!/phpmailer_init/m.test(allPhp), 'new Hub must not configure SMTP through phpmailer_init');
ok(!/wp_ajax_/mi.test(communicationsPhp), 'Communications/Mail must not expose hidden AJAX paths; writes use REST controllers');
ok(!/mvm_hub_mail_ajax_send|wp_ajax_mvm_hub_mail_send/mi.test(allPhp), 'legacy hidden mail send AJAX must never return');
ok(communications.includes('external_mail_delivery_enabled(): bool') && communications.includes('return Runtime_Gates::mail_writes_enabled();'), 'communications policy must use the central Mail runtime gate');
const mailGate = runtimeGates.slice(runtimeGates.indexOf('public static function mail_writes_enabled'), runtimeGates.indexOf('private static function environment_gate'));
ok(mailGate.includes('if ( ! self::verified_mail_staging_host() )') && mailGate.includes('return false;'), 'alpha5 Mail runtime must remain hard read-only unless the exact staging clone is verified');
ok(mailGate.includes("return 'staging.mierlovoormierlo.nl' === $host;"), 'alpha5 Mail rehearsal must be pinned to the exact staging hostname');
ok(!mailGate.includes('wp_get_environment_type'), 'alpha5 Mail rehearsal eligibility must not broaden to arbitrary non-production hosts');
ok(mailGate.includes('MVM_HUB_ENABLE_STAGING_MAIL_WRITES') && mailGate.includes("true !== constant( 'MVM_HUB_ENABLE_STAGING_MAIL_WRITES' )"), 'Mail rehearsal must require explicit literal-true staging-only opt-in');
ok(mailGate.includes("Release_Control::flag_enabled( 'mail_writes' )"), 'Mail rehearsal must additionally require signed mail scope');
ok(!mailGate.includes('MVM_HUB_ALLOW_PRODUCTION_MAIL_WRITES') && !mailGate.includes('MVM_HUB_ENABLE_EXTERNAL_MAIL_DELIVERY'), 'Mail must not be reopenable by a production/config-only override');
ok(communicationsModule.includes('Runtime_Gates::communications_writes_enabled()'), 'internal Communications writes must remain gated');
ok(communicationsModule.includes('new Internal_Message_Write_REST_Controller()'), 'internal staff messaging must use the isolated write controller');
ok(communicationsModule.includes('Runtime_Gates::mail_writes_enabled()') && communicationsModule.includes('new Mail_Write_REST_Controller()'), 'Mail mutation routes must use the dedicated registrar behind the Mail runtime gate');
ok(!communicationsModule.includes('new Communications_Write_REST_Controller()'), 'combined Mail write controller must never be directly registered by the runtime module');
ok(communicationsModule.includes('class-communications-write-rest-controller.php'), 'combined controller may be loaded only as the hardened implementation delegate');
ok(internalWriteController.includes('if ( ! Runtime_Gates::communications_writes_enabled() )') || internalWriteController.includes('Runtime_Gates::communications_writes_enabled()'), 'internal message controller must enforce Communications write gate');
ok(!internalWriteController.includes('/communications/mail/'), 'internal message controller must not expose Mail mutation routes');
ok(mailWriteController.includes('Runtime_Gates::mail_writes_enabled()'), 'dedicated Mail registrar must re-check the Mail runtime gate');
ok(mailWriteController.includes('new Communications_Write_REST_Controller()'), 'dedicated Mail registrar must reuse the hardened delivery implementation');
ok(mailWriteController.includes("'/communications/mail/send'") && mailWriteController.includes("'can_send_mail'"), 'dedicated Mail registrar must expose only capability-guarded send routing');
ok(writeController.includes('if ( Runtime_Gates::mail_writes_enabled() )'), 'delegated legacy controller must retain Mail gate checks for auditability');
ok(/remote_images_allowed_by_default\(\): bool\s*\{\s*return false;/m.test(communications), 'remote mail images must be blocked by default');
ok(communications.includes('BLOCKED_EXTENSIONS'), 'dangerous attachment extensions must be denied');
ok(mailProvider.includes('interface Mail_Provider') && mailProvider.includes('deliver(') && mailProvider.includes('save_draft') && mailProvider.includes('create_folder'), 'mail provider boundary may retain dormant full-client primitives for a future explicit code release');
ok(messageProvider.includes('interface Internal_Message_Provider') && messageProvider.includes('list_threads_for_user'), 'internal message provider must remain user scoped');

ok(moduleFile.includes("'rest_api_init'"), 'Newsroom REST must register on rest_api_init');
ok(controller.includes("private const NAMESPACE = 'mvm-hub/v1'") && controller.includes("private const ROUTE     = '/newsroom/today'"), 'Today endpoint must use central route');
ok(controller.includes('\\WP_REST_Server::READABLE') && controller.includes("'permission_callback'"), 'Today endpoint must be protected GET');
ok(controller.includes('is_user_logged_in()') && controller.includes('Capabilities::can_access_newsroom()'), 'Today must require authenticated Newsroom access');

[
  'mvm_newsroom_access','mvm_news_create','mvm_news_edit_own','mvm_news_edit_team','mvm_news_review','mvm_news_publish','mvm_assignments_view','mvm_assignments_manage',
  'mvm_radar_view','mvm_radar_triage','mvm_sources_view','mvm_sources_manage','mvm_agenda_view','mvm_agenda_manage','mvm_media_view','mvm_media_manage',
  'mvm_dossiers_view','mvm_dossiers_manage','mvm_corrections_view','mvm_corrections_manage','mvm_distribution_view','mvm_distribution_manage','mvm_team_view',
  'mvm_messages_access','mvm_messages_send','mvm_messages_manage_own','mvm_mail_access','mvm_mail_read','mvm_mail_compose','mvm_mail_send','mvm_mail_manage_messages',
  'mvm_mail_manage_folders','mvm_mail_manage_own_attachments','mvm_communications_admin','mvm_communications_audit',
].forEach((cap) => ok(capabilities.includes(`'${cap}'`), `capability registry missing ${cap}`));
ok(capabilities.includes('mvm_hub4_dashboard_view'), 'temporary read bridge must preserve legacy Hub4 access');
ok(!/mvm_(?:journalist|editor|moderator|teamleider|fotograaf|vertaler)/m.test(businessPhp), 'role names must exist only in migration/UAT mapping files');

['contact_email','contact_phone','message_body','html_body','attachment_path','oauth_token','refresh_token'].forEach((field) => ok(privacy.includes(`'${field}'`), `privacy classification missing ${field}`));
ok(privacy.includes("'[redacted]'"), 'audit layer must redact confidential/secret values');

ok(model.includes("'mvm_hub4_' . $suffix"), 'Today must adopt existing Hub4 tables');
ok(model.includes('$item_params[] = 8;'), 'Today assignments must stay bounded');
ok(model.includes('Object_Access::can_read_assignment') && model.includes('Privacy::project_today_assignment'), 'Today requires object/privacy projection');
ok(!/contact_(?:name|email|phone)|private_note|\['brief'\]/m.test(model), 'Today must not leak private details');
ok(model.includes("status IN ('new','triage')"), 'Today must respect signal triage states');
ok(model.includes('Next_Actions::from_metrics') && guidance.includes('Smart Links-context'), 'Today must include workflow guidance');

['idea','assigned','draft','review','changes_requested','ready','scheduled','published','correction'].forEach((state) => ok(workflow.includes(`'${state}'`), `news workflow missing ${state}`));
ok(workflow.includes('required_capability') && workflow.includes('can_transition'), 'workflow must have capability guards');
ok(!/mvm_hub4_news_review/m.test(workflow), 'new workflow writes must not use legacy caps');

ok(smartController.includes("'/newsroom/news/(?P<id>\\d+)/smart-links'") && smartController.includes('\\WP_REST_Server::READABLE'), 'Smart Links route must be article-scoped GET');
ok(smartController.includes("'post' !== $post->post_type") && smartController.includes("current_user_can( 'edit_post', $post_id )"), 'Smart Links needs native object access');
ok(smartContext.includes("'/mvm/v1/admin/smart-links'") && smartContext.includes('rest_do_request') && smartContext.includes('MAX_RESULTS'), 'Smart Links must reuse bounded in-process owner');
ok(!/wp_update_post|update_post_meta|wp_insert_post/m.test(smartContext), 'Smart Links adapter must not mutate content');

ok(router.includes("public const HUB_PATH = '/hub/'"), 'central Hub path must be /hub/');
ok(!/add_action\s*\(/m.test(router), 'router helper must remain hook-free');

if (!process.exitCode) console.log(`PASS: MvM Hub migration baseline (${phpFiles.length} PHP files; ${readOnlyFiles.length} protected read-only files; ${mutationFiles.length} explicit mutation/migration files)`);