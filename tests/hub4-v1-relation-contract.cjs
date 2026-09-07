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
const access = read('src/class-platform-object-access.php');
const writes = read('src/class-platform-writes.php');
const rest = read('src/class-platform-rest.php');
const abilities = read('src/class-abilities.php');
const conversion = read('src/class-signal-conversion.php');
const srcDir = path.join(root, 'src');
const phpFiles = fs.readdirSync(srcDir).filter((name) => name.endsWith('.php'));

check(main.includes("class-platform-object-access.php") && main.includes("class-platform-writes.php"), 'Relation authorization classes must be loaded by the plugin bootstrap.');

for (const needle of [
    'MvM_Hub4_Dossiers::get( $id )',
    'MvM_Hub4_Assignments::get_public( $id )',
    "current_user_can( 'read_post', $id )",
    'MvM_Hub4_Media_Desk::get( $id )',
    'MvM_Hub4_Signals::get( $id )',
    "'security.relation_denied'"
]) {
    check(access.includes(needle), `Object access layer must enforce ${needle}.`);
}
check(access.includes('MvM_Hub4_Capabilities::ASSIGNMENT_VIEW'), 'Assignment relations must require assignment visibility.');
check(access.includes('MvM_Hub4_Capabilities::SIGNAL_VIEW'), 'Signal relations must require signal visibility.');
check(access.includes('MvM_Hub4_Capabilities::NEWS_VIEW') && access.includes('MvM_Hub4_Capabilities::AGENDA_VIEW') && access.includes('MvM_Hub4_Capabilities::SOURCE_VIEW'), 'Post relations must require workflow-specific Hub capabilities.');
check(access.includes("'post'") && access.includes("'event_listing'"), 'Editorial calendar post relations must be type-allowlisted.');

for (const needle of [
    'Platform_Object_Access::dossier',
    'Platform_Object_Access::assignment',
    'Platform_Object_Access::editorial_post',
    'Platform_Object_Access::dossier_link_target'
]) {
    check(writes.includes(needle), `Guarded write facade must call ${needle}.`);
}

for (const needle of [
    'MvM_Hub4_Platform_Writes::add_dossier_link',
    'MvM_Hub4_Platform_Writes::create_calendar',
    'MvM_Hub4_Platform_Writes::update_calendar',
    'MvM_Hub4_Platform_Writes::create_media',
    'MvM_Hub4_Platform_Writes::create_distribution'
]) {
    check(rest.includes(needle), `REST write surface must use guarded facade: ${needle}.`);
}

for (const needle of [
    'MvM_Hub4_Platform_Writes::create_calendar',
    'MvM_Hub4_Platform_Writes::create_media',
    'MvM_Hub4_Platform_Writes::create_distribution'
]) {
    check(abilities.includes(needle), `Abilities write surface must use guarded facade: ${needle}.`);
}

const occurrences = (needle) => phpFiles
    .filter((file) => read(path.join('src', file)).includes(needle))
    .sort();

check(
    JSON.stringify(occurrences('MvM_Hub4_Media_Desk::create(')) === JSON.stringify(['class-platform-writes.php']),
    'Media creation must only be reachable through the guarded write facade.'
);
check(
    JSON.stringify(occurrences('MvM_Hub4_Editorial_Calendar::create(')) === JSON.stringify(['class-platform-writes.php']),
    'Calendar creation must only be reachable through the guarded write facade.'
);
check(
    JSON.stringify(occurrences('MvM_Hub4_Distribution::create(')) === JSON.stringify(['class-platform-writes.php']),
    'Distribution creation must only be reachable through the guarded write facade.'
);
const dossierLinkCallers = occurrences('MvM_Hub4_Dossiers::add_link(');
check(
    JSON.stringify(dossierLinkCallers) === JSON.stringify(['class-platform-writes.php', 'class-signal-conversion.php']),
    'Dossier link creation must be guarded; only trusted signal conversion may call the service directly.'
);
check(conversion.includes("'objectType' => 'signal'") && conversion.includes("'objectId'   => $signal_id"), 'Trusted signal conversion may only add the already-authorized source signal relation.');

check(!access.includes('__return_true') && !writes.includes('__return_true'), 'Relation authorization may not bypass permissions.');

console.log(`Hub 4 v1 relation/IDOR contract: ${assertions} assertions passed.`);
