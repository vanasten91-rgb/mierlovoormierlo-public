'use strict';

const fs = require('fs');
const path = require('path');

const root = path.resolve(__dirname, '..');
const platformCaps = fs.readFileSync(path.join(root, 'plugins', 'mvm-platform', 'src', 'class-capabilities.php'), 'utf8');
const hubSecurity = fs.readFileSync(path.join(root, 'plugins', 'mvm-hubs-v3-secure', 'src', 'class-mvm-hubs-v3-security.php'), 'utf8');

const organizationRoles = [
  'mvm_vereniging',
  'mvm_club',
  'mvm_organisator',
  'mvm_ondernemer',
  'mvm_bedrijf',
  'mvm_winkelier',
];

for (const role of organizationRoles) {
  if (!platformCaps.includes(`'${role}'`)) throw new Error(`platform capabilities missing organization role ${role}`);
  if (!hubSecurity.includes(`'${role}'`)) throw new Error(`Hubs security missing organization role ${role}`);
}

if (!platformCaps.includes('ORGANIZATION_SELF_SERVICE_ROLES')) throw new Error('platform capabilities missing organization self-service matrix');
if (!platformCaps.includes('LOCAL_ADS_SELF_MANAGE')) throw new Error('platform capabilities missing promotion self-service capability');

const businessSections = (hubSecurity.split('private static function business_sections')[1] || '').split('private static function editorial_sections')[0] || '';
if (!businessSections.includes("'mvm_ondernemer', 'mvm_bedrijf', 'mvm_winkelier'")) {
  throw new Error('Hubs security must keep vacancies limited to commercial organization roles');
}
if (!businessSections.includes("return array( 'profiel', 'vacature', 'evenement' );")) {
  throw new Error('commercial organization accounts must receive profile, vacancy and event sections');
}
if (!businessSections.includes("'mvm_vereniging', 'mvm_club', 'mvm_organisator'")) {
  throw new Error('Hubs security missing community organization section group');
}
if (!businessSections.includes("return array( 'profiel', 'evenement' );")) {
  throw new Error('community organization accounts must not receive vacancies');
}
if (!businessSections.includes("if ( in_array( 'organizer', $roles, true ) )")) {
  throw new Error('generic organizer compatibility role must be handled explicitly');
}
if (!businessSections.includes("return array( 'evenement' );")) {
  throw new Error('generic organizer compatibility role must remain event-only');
}

console.log('MvM organization account contract OK');
