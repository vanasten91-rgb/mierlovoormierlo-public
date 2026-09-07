'use strict';

const fs = require('fs');
const path = require('path');

const root = path.resolve(__dirname, '..');
const securityPath = path.join(root, 'plugins', 'mvm-hubs-v3-secure', 'src', 'class-mvm-hubs-v3-security.php');
const security = fs.readFileSync(securityPath, 'utf8');

const requireToken = (token, label = token) => {
  if (!security.includes(token)) throw new Error(`role contract: missing ${label}`);
};

[
  'mvm_hub3_business_access',
  'mvm_hub3_editorial_access',
  'mvm_hub3_review_access',
  'mvm_hub3_moderation_access',
  'mvm_hub3_team_planning_access',
  'mvm_hub3_admin_access',
  'can_submit_editorial',
  'can_review',
  'can_review_vacancies',
  'can_moderate',
  'can_view_team_planning',
  'allowed_sections',
  'primary_hub',
  "'primary_hub' => MvM_Hubs_V3_Security::primary_hub()",
  "'sections'    => $sections",
  "'permissions' => array(",
  "'vacancy_review'   => MvM_Hubs_V3_Security::can_review_vacancies()",
].forEach((token) => requireToken(token));

const capsMatch = security.match(/private const CAPS = array\(([\s\S]*?)\n\s*\);/);
if (!capsMatch) {
  throw new Error('role contract: unable to isolate Secure Core CAPS array');
}
const capsBlock = capsMatch[1];
if (capsBlock.includes('mvm_vacatures_manage_all')) {
  throw new Error('role contract: Secure Core must not manage the vacancy domain capability');
}

const roleCapsBlock = (security.split('private const ROLE_CAPS = array(')[1] || '').split('private const HUB_PATHS')[0] || '';
const roleLine = (role) => roleCapsBlock.split('\n').find((line) => line.includes(`'${role}'`)) || '';
const requireRole = (role, required, forbidden = []) => {
  const line = roleLine(role);
  if (!line) throw new Error(`role contract: missing role ${role}`);
  required.forEach((cap) => {
    if (!line.includes(cap)) throw new Error(`role contract: ${role} missing ${cap}`);
  });
  forbidden.forEach((cap) => {
    if (line.includes(cap)) throw new Error(`role contract: ${role} must not inherit ${cap}`);
  });
};

requireRole('mvm_ondernemer', ['mvm_hub3_business_access'], ['mvm_hub3_editorial_access', 'mvm_hub3_admin_access']);
requireRole('mvm_organisator', ['mvm_hub3_business_access'], ['mvm_hub3_editorial_access', 'mvm_hub3_admin_access']);

for (const role of ['mvm_redacteur', 'mvm_fotograaf', 'mvm_vertaler', 'mvm_journalist']) {
  requireRole(role, ['mvm_hub3_editorial_access'], ['mvm_hub3_review_access', 'mvm_hub3_moderation_access', 'mvm_hub3_team_planning_access', 'mvm_hub3_admin_access']);
}

requireRole(
  'mvm_editor',
  ['mvm_hub3_editorial_access', 'mvm_hub3_review_access'],
  ['mvm_hub3_moderation_access', 'mvm_hub3_team_planning_access', 'mvm_hub3_admin_access']
);
requireRole(
  'mvm_moderator',
  ['mvm_hub3_editorial_access', 'mvm_hub3_moderation_access'],
  ['mvm_hub3_review_access', 'mvm_hub3_team_planning_access', 'mvm_hub3_admin_access']
);
requireRole(
  'mvm_teamleider',
  ['mvm_hub3_editorial_access', 'mvm_hub3_review_access', 'mvm_hub3_team_planning_access'],
  ['mvm_hub3_moderation_access', 'mvm_hub3_admin_access']
);

for (const role of ['mvm_sysop', 'administrator']) {
  requireRole(role, [
    'mvm_hub3_business_access',
    'mvm_hub3_editorial_access',
    'mvm_hub3_review_access',
    'mvm_hub3_moderation_access',
    'mvm_hub3_team_planning_access',
    'mvm_hub3_admin_access',
  ]);
}

requireToken("return user_can( $user_id, 'edit_posts' ) || user_can( $user_id, 'mvm_submit_articles' );", 'native submit-right gate');
requireToken("if ( user_can( $user_id, 'manage_options' ) )", 'vacancy admin fallback');
requireToken("return self::can_review( $user_id ) && user_can( $user_id, 'mvm_vacatures_manage_all' );", 'vacancy review dual-cap gate');
requireToken("$sections[] = 'mijnwerk';", 'own-work section gate');
requireToken("$sections[] = 'review';", 'review section gate');
requireToken("$sections[] = 'nieuwsradar';", 'newsradar section gate');
requireToken("$sections[] = 'bronnen';", 'sources section gate');
requireToken("$sections[] = 'mail';", 'mail section');
requireToken("$sections[] = 'vacatures';", 'vacancy review section gate');
requireToken("$sections[] = 'planning';", 'planning section gate');
requireToken("$sections[] = 'team';", 'team section');
requireToken("$sections[] = 'communicatie';", 'communications section');
requireToken("$sections[] = 'moderatie';", 'moderation section gate');
requireToken("return array( 'organisatieaccount', 'opgeslagen' );", 'user section slugs');
requireToken("return array( 'layouts', 'homeblokken', 'veiligheid', 'integraties', 'onderhoud', 'herstel', 'organisaties' );", 'admin section slugs');
requireToken("foreach ( array( 'admin', 'editorial', 'business' ) as $hub )", 'primary hub priority');

console.log('MvM Hubs v3 role contract: OK');
