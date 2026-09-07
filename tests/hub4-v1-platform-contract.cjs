'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const root = path.resolve(__dirname, '..', 'plugins', 'mvm-hub4-rc-direct');
const read = (file) => fs.readFileSync(path.join(root, file), 'utf8');
let assertions = 0;
const check = (condition, message) => {
    assert.ok(condition, message);
    assertions += 1;
};

const main = read('mvm-hub4.php');
const schema = read('src/class-platform-schema.php');
const caps = read('src/class-capabilities.php');
const signals = read('src/class-signals.php');
const signalConversion = read('src/class-signal-conversion.php');
const dossiers = read('src/class-dossiers.php');
const calendar = read('src/class-editorial-calendar.php');
const media = read('src/class-media-desk.php');
const distribution = read('src/class-distribution.php');
const corrections = read('src/class-corrections.php');
const personalization = read('src/class-personalization.php');
const personalizedFeedUi = read('src/class-personalized-feed-ui.php');
const dashboard = read('src/class-dashboard.php');
const abilities = read('src/class-abilities.php');
const platformRest = read('src/class-platform-rest.php');
const publicRest = read('src/class-public-rest.php');
const publicUi = read('src/class-public-ui.php');
const app = read('src/class-app.php');
const template = read('templates/app.php');
const platformJs = read('assets/platform-v1.js');
const conversionJs = read('assets/signal-conversion-ui.js');

const headerVersion = main.match(/\* Version:\s*([^\s]+)/)?.[1] || '';
const constantVersion = main.match(/MVM_HUB4_VERSION',\s*'([^']+)'/)?.[1] || '';
check(/^\d+\.\d+\.\d+(?:-rc\d+)?$/.test(headerVersion) && headerVersion === constantVersion, 'Integrated Hub version must be explicit and consistent.');
check(main.includes('MvM_Hub4_Platform_Schema::activate();'), 'v1 schema must be installed on activation.');
check(main.includes("class-public-ui.php") && main.includes('MvM_Hub4_Public_UI::init();'), 'Public website integration must be explicitly bootstrapped.');
check(main.includes("class-signal-conversion.php") && main.includes('MvM_Hub4_Signal_Conversion::init();'), 'Signal conversion workflow must be explicitly bootstrapped.');
check(main.includes("class-personalized-feed-ui.php") && main.includes('MvM_Hub4_Personalized_Feed_UI::init();'), 'Personalized feed UI must be explicitly bootstrapped.');

for (const table of ['signals', 'dossiers', 'dossier_links', 'editorial_calendar', 'media_items', 'distribution_items', 'corrections']) {
    check(schema.includes(`'${table}'`), `Platform schema must include ${table}.`);
}
check((schema.match(/dbDelta\s*\(/g) || []).length >= 7, 'Platform schema must use dbDelta for every platform table.');
check(schema.includes("OPTION_SCHEMA  = 'mvm_hub4_platform_schema'") || schema.includes("OPTION_SCHEMA = 'mvm_hub4_platform_schema'"), 'Platform schema version must be tracked separately.');
check(schema.includes('SCHEMA_VERSION = 3'), 'Platform schema must include signal-to-assignment migration.');
for (const field of ['incident_at_utc', 'verification_status', 'incident_status', 'assignment_id']) {
    check(schema.includes(field), `Platform schema field ${field} must exist.`);
}
check(schema.includes('KEY assignment_id (assignment_id)'), 'Signal assignment relation must be indexed.');

for (const capability of [
    'NEWS_REVIEW',
    'SIGNAL_VIEW', 'SIGNAL_CREATE', 'SIGNAL_TRIAGE',
    'DOSSIER_VIEW', 'DOSSIER_MANAGE', 'DOSSIER_PUBLISH',
    'CALENDAR_VIEW', 'CALENDAR_MANAGE',
    'MEDIA_VIEW', 'MEDIA_MANAGE', 'MEDIA_REVIEW',
    'DISTRIBUTION_VIEW', 'DISTRIBUTION_PREPARE', 'DISTRIBUTION_APPROVE',
    'CORRECTION_VIEW', 'CORRECTION_MANAGE', 'DASHBOARD_VIEW'
]) {
    check(caps.includes(`const ${capability}`), `Capability ${capability} must exist.`);
}
check(caps.includes('SCHEMA_VERSION = 4'), 'Capability schema must include the guarded Nieuwsradar review grant.');

const internalRouteCount = (platformRest.match(/register_rest_route\s*\(/g) || []).length;
const internalPermissionCount = (platformRest.match(/['"]permission_callback['"]\s*=>/g) || []).length;
check(internalRouteCount > 0 && internalPermissionCount >= internalRouteCount, 'Every v1 internal route must have permission callbacks.');
check(!platformRest.includes('__return_true'), 'Internal v1 routes may not use __return_true.');
check(platformRest.includes("'mvm-hub4/v1'"), 'Internal v1 API must remain in the protected Hub namespace.');
check(platformRest.includes('MvM_Hub4_Security::require_capability'), 'Internal v1 writes must use Hub capability guards.');
check(platformRest.includes("'assignmentCreate'"), 'Platform status must expose assignment-create permission for conversion UI.');

check(signalConversion.includes("'/signals/(?P<id>\\d+)/convert'"), 'Signal conversion must use a dedicated internal route.');
check(signalConversion.includes('permission_callback') && signalConversion.includes('SIGNAL_TRIAGE'), 'Signal conversion route must require triage capability.');
check(signalConversion.includes('DOSSIER_MANAGE') && signalConversion.includes('ASSIGNMENT_CREATE'), 'Signal conversion must enforce target-specific capabilities.');
check(signalConversion.includes("'signal.convert'"), 'Signal conversions must be audited.');
check(!signalConversion.includes('__return_true'), 'Signal conversion may not bypass permissions.');

const publicRouteCount = (publicRest.match(/register_rest_route\s*\(/g) || []).length;
const publicPermissionCount = (publicRest.match(/['"]permission_callback['"]\s*=>/g) || []).length;
check(publicRouteCount > 0 && publicPermissionCount >= publicRouteCount, 'Every public route must have an explicit permission callback.');
check(!publicRest.includes('__return_true'), 'Public routes must use named policy callbacks rather than __return_true.');
check(publicRest.includes("'mvm-public/v1'"), 'Public API must use a separate namespace.');
check(publicRest.includes('RATE_LIMIT  = 5') && publicRest.includes('RATE_WINDOW = 900'), 'Public intake must be rate limited.');
check(publicRest.includes("get_param( 'website' )"), 'Public REST intake must retain the honeypot check.');
check(publicRest.includes("hash_hmac( 'sha256'"), 'Public REST rate limiting must avoid storing raw client IPs.');
check(!/['"]id['"]\s*=>\s*\$result/.test(publicRest), 'Public intake responses may not return internal record IDs.');

check(signals.includes('if ( $include_contact )'), 'Signal contact data must be conditionally exposed only to triage users.');
check(/['"]submitted_via['"]\s*=>\s*\$via/.test(signals), 'Signal intake origin must be recorded server-side.');
check(signals.includes("'safety' === $kind") && signals.includes('incidentAtUtc') && signals.includes('verificationStatus') && signals.includes('incidentStatus'), '112 workflow must enforce explicit safety fields.');
check(signals.includes('mvm_hub4_safety_location') && signals.includes('mvm_hub4_safety_time'), '112 workflow must reject missing location/time.');
check(signals.includes("'assignmentId'") && signals.includes('mvm_hub4_signal_convert_relation'), 'Signals must expose conversion relation and reject orphan converted status.');

check(dossiers.includes("visibility = %s AND status = %s") && dossiers.includes("$params[] = 'published'"), 'Dossier list must restrict public visibility to published dossiers.');
check(!dossiers.match(/public_row[\s\S]{0,1400}internalBrief/), 'Public dossier projection may not expose internalBrief.');
check(dossiers.includes("array( 'post', 'event', 'encyclopedia', 'url' )"), 'Public dossier links must use a restricted allowlist.');
check(dossiers.includes("status = %s LIMIT 1") && dossiers.includes("'public', 'published'"), 'Public dossier detail must require published status.');

check(calendar.includes('can_manage_all') && calendar.includes('owner_user_id'), 'Calendar writes must be owner-scoped for lower roles.');
check(media.includes("array( 'approved', 'rejected', 'used' )") && media.includes('can_review_all'), 'Media approval states must require reviewer authority.');
check(distribution.includes("array( 'approved', 'sent', 'cancelled' )") && distribution.includes('can_approve'), 'Distribution approval/sent states must require approver authority.');
check(!/wp_remote_(?:post|get|request)|curl_exec|fsockopen/.test(distribution), 'Distribution v1 must not silently send to external channels.');
check(corrections.includes("'_mvm_hub4_last_material_update_utc'"), 'Published correction must record material update metadata.');
check(corrections.includes('public_for_post') && !/public_for_post[\s\S]{0,1500}contact_email/.test(corrections), 'Public corrections may not expose submitter contact details.');

check(personalization.includes("META_TOPICS = '_mvm_hub4_topics'"), 'My Mierlo preferences must use dedicated usermeta.');
check(personalization.includes("get_term( $term_id, 'category' )"), 'My Mierlo topic IDs must be taxonomy validated.');
check(personalization.includes('public static function feed'), 'My Mierlo must provide a personalized published-news feed.');
check(personalizedFeedUi.includes("add_shortcode( 'mvm_mijn_mierlo_feed'"), 'My Mierlo feed shortcode must exist.');
check(personalizedFeedUi.includes("'mvm_mijn_mierlo' !== $tag") && personalizedFeedUi.includes('MvM_Hub4_Personalization::feed'), 'My Mierlo preferences view must append the visible feed for logged-in users.');
check(!/contact_(?:email|phone|name)|internalBrief|audit/i.test(personalizedFeedUi), 'Personalized feed UI may not expose internal/contact data.');

check(dashboard.includes('overdueAssignments') && dashboard.includes('correctionsWaiting') && dashboard.includes('distributionReview'), 'Operational dashboard must cover newsroom blockers.');
check(dashboard.includes("'radar'"), 'Role-home ordering must use the v1 radar navigation id.');

check(abilities.includes("add_action( 'wp_abilities_api_init'"), 'Native WordPress Abilities API registration must be used when available.');
check(abilities.includes('MvM_Hub4_Security::require_hub_access()'), 'Abilities must enforce the Hub session boundary.');
check(!abilities.includes('__return_true'), 'Abilities may not bypass permissions.');

check(publicUi.includes("add_shortcode( 'mvm_tip_de_redactie'"), 'Tip de redactie shortcode must exist.');
check(publicUi.includes("add_shortcode( 'mvm_mijn_mierlo'"), 'My Mierlo shortcode must exist.');
check(publicUi.includes("add_shortcode( 'mvm_dossiers'"), 'Public dossiers shortcode must exist.');
check(publicUi.includes('check_admin_referer') && publicUi.includes("hash_hmac( 'sha256'"), 'Server-rendered public forms must use nonce verification and rate limiting.');
check(publicUi.includes("add_filter( 'the_content'") && publicUi.includes('public_for_post'), 'Article correction/transparency block must be wired to published corrections.');

check(app.includes('platform_navigation') && template.includes('data-mvm-platform-view'), 'Hub v1 navigation must be capability-filtered and rendered.');
check(template.includes('platform-v1.css') && template.includes('platform-v1.js'), 'Hub v1 UI assets must be loaded by the app template.');
check(template.includes('signal-conversion-ui.js'), 'Signal conversion controller must be loaded by the app template.');
for (const source of [platformJs, conversionJs]) {
    check(source.includes("'X-WP-Nonce'") && source.includes("credentials: 'same-origin'"), 'Platform UI requests must send REST nonce and same-origin credentials.');
    check(!/\b(?:innerHTML|outerHTML|insertAdjacentHTML|eval)\b/.test(source), 'Platform UI may not use unsafe HTML/eval sinks.');
    check(!/(?:window\.)?(?:localStorage|sessionStorage)/.test(source), 'Platform UI may not persist newsroom data in browser storage.');
}

console.log(`Hub 4 v1 platform contract: ${assertions} assertions passed.`);
