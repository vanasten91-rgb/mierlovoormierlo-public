'use strict';

const fs = require('node:fs');
const path = require('node:path');
const root = path.resolve(__dirname, '..');
const read = (relative) => fs.readFileSync(path.join(root, relative), 'utf8');
const assert = (condition, message) => { if (!condition) throw new Error(message); };

const agendaAdapter = read('plugins/mvm-hub/modules/agenda/class-v5-agenda-read-adapter.php');
const agendaPreview = read('plugins/mvm-hub/modules/agenda/class-v5-agenda-preview-model.php');
const encyclopedie = read('plugins/mvm-hub/integrations/encyclopedie/class-v5-encyclopedie-workspace-model.php');
const intake = read('plugins/mvm-hub/modules/intake/class-v5-intake-preview-model.php');
const crossDomain = read('plugins/mvm-hub/core/class-v5-cross-domain-my-work-model.php');
const bootstrap = read('plugins/mvm-hub/mvm-hub.php');

// Agenda coexistence must keep the current editorial calendar as canonical owner.
assert(agendaAdapter.includes("'legacy_source']         = 'mvm_hub4_editorial_calendar'"), 'Agenda adapter must identify current editorial calendar source');
assert(agendaAdapter.includes("'canonical_owner']       = 'current-newsroom-editorial-calendar'"), 'Agenda adapter must preserve canonical current owner');
assert(agendaPreview.includes("'legacyOwnershipPreserved' => true"), 'Agenda preview must declare coexistence ownership preservation');
assert(agendaPreview.includes('My_Work_Read_Model::build'), 'Agenda rows must be authorized before preview output');
assert(!agendaAdapter.includes('$wpdb') && !agendaAdapter.includes('wp_update_post(') && !agendaAdapter.includes('update_option('), 'Agenda adapter must not query or mutate storage');
assert(!agendaPreview.includes('$wpdb') && !agendaPreview.includes('register_rest_route(') && !agendaPreview.includes('add_action('), 'Agenda preview must not claim live runtime ownership');

// Encyclopedie parity remains a hard dependency gate.
assert(encyclopedie.includes("'hardDependencyAllowed' => $parity_matched"), 'Encyclopedie parity must gate hard V5 dependency');
assert(encyclopedie.includes("'owner'     => 'mvm-encyclopedie-next'"), 'Encyclopedie plugin must remain canonical owner');
assert(encyclopedie.includes('My_Work_Read_Model::build'), 'Encyclopedie maintenance must authorize before output');
assert(!encyclopedie.includes('$wpdb') && !encyclopedie.includes('wp_update_post(') && !encyclopedie.includes('register_rest_route('), 'Encyclopedie preview must remain read-only and route-dormant');

// Protected Intake must never become a metadata leak.
assert(intake.includes('explicitAclGranted'), 'protected Intake must require explicit ACL evidence before projection');
assert(intake.includes("return 'Beschermde tip #' . $id;"), 'protected Intake must redact case titles');
assert(intake.includes("'sensitivePayloadIncluded' => false"), 'Intake projection must explicitly exclude sensitive payloads');
assert(intake.includes("'notificationPayloadPolicy' => 'case-id-and-hub-link-only'"), 'Intake notifications must stay metadata-only');
for (const forbidden of ['contactEmail', 'attachmentPath', 'messageBody', 'sourceName']) {
  assert(!intake.includes(`'${forbidden}' =>`), `Intake preview must not return ${forbidden}`);
}
assert(!intake.includes('$wpdb') && !intake.includes('wp_insert_post(') && !intake.includes('register_rest_route('), 'Intake preview must not persist or claim routes');

// One global My Work ranking model must authorize before priority sorting.
assert(crossDomain.includes('My_Work_Read_Model::build'), 'cross-domain My Work must use shared authorization-first read model');
assert(crossDomain.includes("'authorizationBeforeRanking' => true"), 'cross-domain My Work must make authorization ordering explicit');
assert(crossDomain.includes('V5_My_Work_Preview_Model::build'), 'cross-domain My Work must reuse canonical preview lanes');
assert(!crossDomain.includes('$wpdb') && !crossDomain.includes('register_rest_route(') && !crossDomain.includes('add_action('), 'cross-domain aggregator must stay pure and dormant');

// None of the new preview classes may be loaded by production bootstrap yet.
for (const dormant of [
  'class-v5-cross-domain-my-work-model.php',
  'class-v5-agenda-read-adapter.php',
  'class-v5-agenda-preview-model.php',
  'class-v5-encyclopedie-workspace-model.php',
  'class-v5-intake-preview-model.php',
]) {
  assert(!bootstrap.includes(dormant), `${dormant} must remain absent from live bootstrap`);
}

console.log('PASS: MvM Hub V5 Agenda/Encyclopedie/Intake/cross-domain contract');
