'use strict';

const fs = require('fs');
const path = require('path');

const root = path.resolve(__dirname, '..');
const plugin = path.join(root, 'plugins', 'mvm-hubs-v3-secure');
const files = {
  bootstrap: path.join(plugin, 'mvm-hubs-v3-secure.php'),
  security: path.join(plugin, 'src', 'class-mvm-hubs-v3-security.php'),
  audit: path.join(plugin, 'src', 'class-mvm-hubs-v3-audit.php'),
  communications: path.join(plugin, 'src', 'class-mvm-hubs-v3-communications.php'),
  adminStatus: path.join(plugin, 'src', 'class-mvm-hubs-v3-admin-status.php'),
  moderation: path.join(plugin, 'src', 'class-mvm-hubs-v3-moderation.php'),
  homeSettings: path.join(plugin, 'src', 'class-mvm-hubs-v3-home-settings.php'),
  maintenance: path.join(plugin, 'src', 'class-mvm-hubs-v3-maintenance.php'),
  entrepreneurAds: path.join(root, 'plugins', 'mvm-platform', 'src', 'local-ads', 'class-entrepreneur-ads-rest.php'),
  staffAds: path.join(root, 'plugins', 'mvm-platform', 'src', 'local-ads', 'class-local-business-ads-rest.php'),
  localAdsCore: path.join(root, 'plugins', 'mvm-platform', 'src', 'local-ads', 'class-local-business-ads.php'),
  entrepreneurTemplate: path.join(root, 'plugins', 'mvm-platform', 'templates', 'entrepreneur', 'ads-center.php'),
  entrepreneurJs: path.join(root, 'plugins', 'mvm-platform', 'assets', 'entrepreneur-ads.js'),
};

function read(key) {
  if (!fs.existsSync(files[key])) throw new Error(`Missing ${key}: ${files[key]}`);
  return fs.readFileSync(files[key], 'utf8');
}

function requireAll(text, tokens, label) {
  for (const token of tokens) {
    if (!text.includes(token)) throw new Error(`${label}: missing ${token}`);
  }
}

function requireBefore(text, first, second, label) {
  const firstAt = text.indexOf(first);
  const secondAt = text.indexOf(second);
  if (firstAt < 0 || secondAt < 0 || firstAt >= secondAt) {
    throw new Error(`${label}: expected ${first} before ${second}`);
  }
}

const bootstrap = read('bootstrap');
const security = read('security');
const audit = read('audit');
const communications = read('communications');
const adminStatus = read('adminStatus');
const moderation = read('moderation');
const homeSettings = read('homeSettings');
const maintenance = read('maintenance');
const entrepreneurAds = read('entrepreneurAds');
const staffAds = read('staffAds');
const localAdsCore = read('localAdsCore');
const entrepreneurTemplate = read('entrepreneurTemplate');
const entrepreneurJs = read('entrepreneurJs');

requireAll(bootstrap, [
  'MVM_HUBS_V3_SECURE_VERSION',
  'MVM_HUBS_V3_CAPS_SCHEMA',
  "define( 'MVM_HUBS_V3_CAPS_SCHEMA', '2026.09.01-1' )",
  'class-mvm-hubs-v3-communications.php',
  'MvM_Hubs_V3_Security::boot()',
  'MvM_Hubs_V3_Audit::boot()',
  'MvM_Hubs_V3_Admin_Status::boot()',
  'MvM_Hubs_V3_Moderation::boot()',
  'MvM_Hubs_V3_Home_Settings::boot()',
  'MvM_Hubs_V3_Maintenance::boot()',
], 'bootstrap');
if (bootstrap.includes('class-mvm-hubs-v3-promo.php') || bootstrap.includes('MvM_Hubs_V3_Promo')) {
  throw new Error('bootstrap: duplicate promo storage must not be loaded');
}

requireAll(security, [
  'mvm_hub3_business_access',
  'mvm_hub3_editorial_access',
  'mvm_hub3_moderation_access',
  'mvm_hub3_team_planning_access',
  'mvm_hub3_admin_access',
  "'/mijn-hub/'",
  "'/ondernemers-hub/'",
  "'/redactie-hub/'",
  "'/beheer-hub/'",
  "CAPS_SCHEMA_OPTION = 'mvm_hubs_v3_caps_schema'",
  "SECURE_VERSION_OPTION = 'mvm_hubs_v3_secure_version'",
  'MVM_HUBS_V3_CAPS_SCHEMA',
  'public static function is_draft_preview(): bool',
  "get_option( 'wpvibe_draft_theme'",
  "strpos( $stylesheet, 'wpvibe-draft' )",
  "strpos( $origin, 'wpvibe_preview=' )",
  'maybe_sync_capabilities',
  'remove_cap(',
  'user_can(',
  'can_view_team_planning',
  'capability_health',
  "'roles_checked'",
  "'drift_count'",
  "return array( 'organisatieaccount', 'opgeslagen' )",
  "return array( 'layouts', 'homeblokken', 'veiligheid', 'integraties', 'onderhoud', 'herstel', 'organisaties' )",
  "'mijnwerk'",
  "'nieuwsradar'",
  "'bronnen'",
  "'mail'",
  "'communicatie'",
  'Cache-Control: private, no-store',
  'X-Robots-Tag: noindex, nofollow, noarchive',
  'X-Content-Type-Options: nosniff',
  "'permission_callback'",
], 'security');

const maybeSync = (security.split('public static function maybe_sync_capabilities(): void')[1] || '').split('public static function sync_capabilities(): bool')[0] || '';
requireAll(maybeSync, [
  'self::is_draft_preview()',
  'MVM_HUBS_V3_CAPS_SCHEMA',
  'self::CAPS_SCHEMA_OPTION',
  'self::SECURE_VERSION_OPTION',
], 'schema-driven capability sync');
requireBefore(maybeSync, 'self::is_draft_preview()', 'MVM_HUBS_V3_CAPS_SCHEMA !==', 'preview before schema sync');
const versionGate = (maybeSync.split('MVM_HUBS_V3_SECURE_VERSION !==')[1] || '');
if (versionGate.includes('self::sync_capabilities()')) {
  throw new Error('schema-driven capability sync: Secure Core version must not trigger a role rewrite');
}

const syncCaps = (security.split('public static function sync_capabilities(): bool')[1] || '').split('public static function capability_health')[0] || '';
requireAll(syncCaps, [
  'self::is_draft_preview()',
  'return false;',
  'remove_cap(',
  'add_cap(',
  'update_option( self::CAPS_SCHEMA_OPTION, MVM_HUBS_V3_CAPS_SCHEMA, false )',
  'update_option( self::SECURE_VERSION_OPTION, MVM_HUBS_V3_SECURE_VERSION, false )',
  'return true;',
], 'capability sync');
requireBefore(syncCaps, 'self::is_draft_preview()', 'remove_cap(', 'capability preview hard block');
requireBefore(syncCaps, 'self::is_draft_preview()', 'add_cap(', 'capability preview hard block add');

requireAll(audit, [
  "'public' => false",
  "'show_ui' => false",
  "'show_in_rest' => false",
  'ALLOWED_CONTEXT_KEYS',
  "'object_key'",
  "'reason_code'",
  "'post_status' => 'private'",
  "add_action( 'mvm_platform_audit_event'",
  'record_platform_event',
  "'source' => 'mvm_platform'",
  "'object_type' => $domain",
  "'object_id' => absint( $object_id )",
  "'status' => $status",
  '$actor_user_id !== $current_user_id',
  'public static function record( string $event, array $context = array() ): bool',
  'wp_insert_post(',
  'return ! is_wp_error( $post_id ) && $post_id > 0;',
], 'audit');
const platformBridge = (audit.split('public static function record_platform_event')[1] || '').split('public static function record(')[0] || '';
if (
  platformBridge.includes("'context' => $context") ||
  platformBridge.includes('self::record( $event, $context') ||
  platformBridge.includes('wp_json_encode( $context')
) {
  throw new Error('audit: platform bridge must not forward raw platform context');
}

requireAll(communications, [
  'EMERGENCY_REASON_CODES',
  'can_open_conversation',
  "MvM_Hubs_V3_Security::can_access( 'editorial', $user_id )",
  'in_array( $user_id, $member_ids, true )',
  'authorize_admin_emergency_view',
  "MvM_Hubs_V3_Security::can_access( 'admin', $user_id )",
  "'communication_emergency_view_authorized'",
  "'reason_code' => $reason_code",
  'MvM_Hubs_V3_Audit::record(',
  'return true === $audit_written;',
], 'communications');
if (communications.includes('register_rest_route') || communications.includes('post_content') || communications.includes('message_body')) {
  throw new Error('communications: authorization boundary must not expose or store conversation content');
}

requireAll(adminStatus, [
  "'/admin/status'",
  "MvM_Hubs_V3_Security::can_access( 'admin' )",
  "'wordpress'",
  "'php'",
  "'capabilities' => MvM_Hubs_V3_Security::capability_health()",
  "'integrations'",
  "'theme_policy_saved'",
  "'theme_snapshot_present'",
  "Cache-Control', 'private, no-store",
], 'admin status');
if (/password|secret|token|api[_-]?key/i.test(adminStatus)) {
  throw new Error('admin status: secret-like field name must not be exposed');
}

requireAll(moderation, [
  "'/moderation/sources'",
  'MvM_Hubs_V3_Security::can_moderate()',
  "'news_comments'",
  "'forum'",
  "'community'",
  "'detail_mode' => 'on_demand'",
  "'actions_enabled' => false",
  "'content_included' => false",
  "'personal_data_included' => false",
  "'destructive_actions_enabled' => false",
  "Cache-Control', 'private, no-store",
], 'moderation sources');

requireAll(homeSettings, [
  "'mvm_home_blocks_v3'",
  'MAX_SLOTS = 4',
  "'1x2', '2x2', '3x2', 'lead-4', 'compact-list'",
  "MvM_Hubs_V3_Security::can_access( 'admin' )",
  'MvM_Hubs_V3_Security::is_draft_preview()',
  'Previewmodus: deze wijziging wordt niet opgeslagen.',
  "check_admin_referer( 'mvm_hubs_v3_save_home_blocks'",
  'array_slice( $raw, 0, self::MAX_SLOTS',
  'max( 1, min( 12, absint(',
  "'home_blocks_update_authorized'",
  "'object_key' => self::OPTION",
  'if ( ! $audit_ready )',
  'update_option( self::OPTION, $clean, false )',
  "'/admin/home-blocks'",
  "Cache-Control', 'private, no-store",
], 'home settings');
requireBefore(homeSettings, 'MvM_Hubs_V3_Security::is_draft_preview()', "check_admin_referer( 'mvm_hubs_v3_save_home_blocks'", 'home settings preview before nonce');
requireBefore(homeSettings, 'MvM_Hubs_V3_Security::is_draft_preview()', "'home_blocks_update_authorized'", 'home settings preview before audit');
requireBefore(homeSettings, 'MvM_Hubs_V3_Security::is_draft_preview()', 'update_option( self::OPTION, $clean, false )', 'home settings preview before write');
requireBefore(homeSettings, "'home_blocks_update_authorized'", 'update_option( self::OPTION, $clean, false )', 'home settings audit gate');

requireAll(maintenance, [
  "'/admin/maintenance'",
  "'sync_hub_capabilities'",
  "'flush_rewrites'",
  "MvM_Hubs_V3_Security::can_access( 'admin' )",
  'MvM_Hubs_V3_Security::is_draft_preview()',
  "'mvm_hubs_v3_preview_write_blocked'",
  "'maintenance_authorized'",
  "'object_key' => $action",
  'if ( ! $audit_ready )',
  'MvM_Hubs_V3_Security::sync_capabilities()',
  'flush_rewrite_rules( false )',
  "'destructive' => false",
  "Cache-Control', 'private, no-store",
], 'maintenance');
requireBefore(maintenance, 'MvM_Hubs_V3_Security::is_draft_preview()', "'maintenance_authorized'", 'maintenance preview before audit');
requireBefore(maintenance, 'MvM_Hubs_V3_Security::is_draft_preview()', 'MvM_Hubs_V3_Security::sync_capabilities()', 'maintenance preview before capability sync');
requireBefore(maintenance, 'MvM_Hubs_V3_Security::is_draft_preview()', 'flush_rewrite_rules( false )', 'maintenance preview before rewrite');
requireBefore(maintenance, "'maintenance_authorized'", 'MvM_Hubs_V3_Security::sync_capabilities()', 'maintenance capability audit gate');
requireBefore(maintenance, "'maintenance_authorized'", 'flush_rewrite_rules( false )', 'maintenance rewrite audit gate');
const forbiddenMaintenance = ['plugin_update', 'theme_update', 'database_repair', 'delete_users', 'delete_posts'];
for (const token of forbiddenMaintenance) {
  if (maintenance.includes(token)) throw new Error(`maintenance: forbidden broad action ${token}`);
}

requireAll(entrepreneurAds, [
  "'/entrepreneur/ads'",
  'LOCAL_ADS_SELF_MANAGE',
  'can_edit_own',
  'get_own_ad',
  'no_store_private',
  'wp_check_filetype_and_ext',
  'MAX_OPEN_REVIEW_ITEMS = 5',
  'CREATE_RATE_LIMIT_SECONDS = 30',
  'mvm_entrepreneur_ad_rate_limited',
  'mvm_entrepreneur_ad_open_limit',
  'mvm_entrepreneur_ad_date_order',
  "$payload['status'] = 'draft';",
  "return in_array( $status, array( 'draft', 'paused', 'ended' ), true ) ? $status : 'draft';",
  'review_capacity_guard',
  "'posts_per_page' => self::MAX_OPEN_REVIEW_ITEMS",
  "'meta_key'       => '_mvm_local_ad_status'",
  "'meta_value'     => 'draft'",
  '$capacity = self::review_capacity_guard( $post->ID );',
  "audit_gate( 'create_for_review', 0, 'draft' )",
  "audit_gate( 'update', $post->ID, $payload['status'] )",
  "audit_gate( 'trash', $post->ID, 'trash' )",
  "audit_gate( 'media_upload', 0, 'authorized' )",
  "class_exists( 'MvM_Hubs_V3_Audit' )",
  'mvm_entrepreneur_ad_audit_unavailable',
  "'self_created_for_review'",
  "'self_updated_for_review'",
  "'self_trashed'",
  "'self_media_uploaded'",
  "do_action( 'mvm_platform_audit_event'",
], 'entrepreneur ads self-service');
requireBefore(entrepreneurAds, '$guard = self::create_guard();', 'wp_insert_post(', 'entrepreneur create guard');
requireBefore(entrepreneurAds, "self::audit_gate( 'create_for_review', 0, 'draft' )", 'wp_insert_post(', 'entrepreneur create audit gate');
requireBefore(entrepreneurAds, "$payload['status'] = 'draft';", 'MvM_Local_Business_Ads::set_ad_fields( (int) $post_id, $payload )', 'entrepreneur create review state');
requireBefore(entrepreneurAds, '$capacity = self::review_capacity_guard( $post->ID );', '$updated = wp_update_post(', 'entrepreneur resubmission capacity gate');
requireBefore(entrepreneurAds, "self::audit_gate( 'update', $post->ID, $payload['status'] )", '$updated = wp_update_post(', 'entrepreneur update audit gate');
requireBefore(entrepreneurAds, "self::audit_gate( 'trash', $post->ID, 'trash' )", 'wp_trash_post( $post->ID )', 'entrepreneur trash audit gate');
requireBefore(entrepreneurAds, 'wp_trash_post( $post->ID )', "self::audit( 'self_trashed'", 'entrepreneur trash completion audit');
requireBefore(entrepreneurAds, "self::audit_gate( 'media_upload', 0, 'authorized' )", 'media_handle_sideload(', 'entrepreneur media audit gate');
requireBefore(entrepreneurAds, 'media_handle_sideload(', "self::audit( 'self_media_uploaded'", 'entrepreneur media completion audit');

requireAll(localAdsCore, [
  "'public'              => false",
  "'publicly_queryable'  => false",
  "'show_in_rest'        => false",
  "'active' !== $data['status']",
], 'local ads public boundary');

requireAll(staffAds, [
  "'/local-ads'",
  'LOCAL_ADS_ADMIN',
  'validate_admin_payload',
  'MvM_Local_Business_Ads::statuses()',
], 'local ads staff approval');

requireAll(entrepreneurTemplate, [
  'data-mvm-entrepreneur-status-field hidden',
  '<option value="draft">Ter beoordeling</option>',
  '<option value="paused">Gepauzeerd</option>',
  '<option value="ended">Beëindigd</option>',
  'Nieuwe advertenties gaan altijd eerst',
  'Alleen MvM-staf kan een advertentie activeren.',
], 'entrepreneur ads template');
if (entrepreneurTemplate.includes('<option value="active">')) {
  throw new Error('entrepreneur ads template: self-service must not offer active status');
}

requireAll(entrepreneurJs, [
  "draft: 'Ter beoordeling'",
  "['draft', 'paused', 'ended'].includes(item.status)",
  'statusField.hidden = true',
  'statusField.hidden = false',
  "status: id ? form.elements.status.value : 'draft'",
  'Na opslaan gaat de wijziging opnieuw ter beoordeling.',
  'Opgeslagen en ter beoordeling bij MvM.',
], 'entrepreneur ads client');

const combined = [bootstrap, security, audit, communications, adminStatus, moderation, homeSettings, maintenance, entrepreneurAds, staffAds].join('\n');
const forbidden = [
  /api[_-]?key\s*[=:]\s*["'][^"']+/i,
  /client[_-]?secret\s*[=:]\s*["'][^"']+/i,
  /password\s*[=:]\s*["'][^"']+/i,
  /Authorization:\s*Bearer\s+[A-Za-z0-9._-]+/i,
];
for (const pattern of forbidden) {
  if (pattern.test(combined)) throw new Error(`Potential secret pattern detected: ${pattern}`);
}

console.log('MvM Hubs v3 security contract: OK');
