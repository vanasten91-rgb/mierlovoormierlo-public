'use strict';

const fs = require('node:fs');
const path = require('node:path');
const root = path.resolve(__dirname, '..');
const read = (relative) => fs.readFileSync(path.join(root, relative), 'utf8');
const assert = (condition, message) => { if (!condition) throw new Error(message); };

const workspacePolicy = read('plugins/mvm-hub/core/class-v5-workspace-policy.php');
const workspaceCatalog = read('plugins/mvm-hub/core/class-v5-workspace-catalog.php');
const shellModel = read('plugins/mvm-hub/core/class-v5-shell-preview-model.php');
const myWorkPreview = read('plugins/mvm-hub/core/class-v5-my-work-preview-model.php');
const shellRenderer = read('plugins/mvm-hub/core/class-v5-shell-preview-renderer.php');
const newsroomQueue = read('plugins/mvm-hub/modules/newsroom/class-v5-newsroom-queue-model.php');
const newsroomAdapter = read('plugins/mvm-hub/modules/newsroom/class-v5-newsroom-read-adapter.php');
const contextLinks = read('plugins/mvm-hub/integrations/encyclopedie/class-v5-context-links-read-model.php');
const bootstrap = read('plugins/mvm-hub/mvm-hub.php');

// Capability-aware navigation stays callback-driven and fail-closed.
assert(workspacePolicy.includes('allowed_workspaces'), 'workspace policy must expose authorized workspace projection');
assert(workspacePolicy.includes("'policy_inventory_required'"), 'unresolved future workspace policy must still be able to fail closed');
assert(workspacePolicy.includes("'mierlokaal'"), 'MierLokaal workspace policy must remain explicit');
assert(workspacePolicy.includes("'mvm_technical_access'"), 'technical workspace must require technical capability');
assert(!workspacePolicy.includes('current_user_can(') && !workspacePolicy.includes('get_userdata('), 'workspace navigation policy must not bind to hidden WP role/runtime state');
assert(!workspacePolicy.includes('manage_options'), 'V5 navigation policy must not use manage_options as universal staff authorization');

const mierlokaalStart = workspacePolicy.indexOf("'mierlokaal' => array(");
const intakeStart = workspacePolicy.indexOf("'intake' => array(", mierlokaalStart);
assert(mierlokaalStart >= 0 && intakeStart > mierlokaalStart, 'MierLokaal policy section must be structurally bounded');
const mierlokaalPolicy = workspacePolicy.slice(mierlokaalStart, intakeStart);
for (const cap of [
  'mvm_marketplace_moderate',
  'mvm_local_ads_manage',
  'mvm_local_ads_admin',
  'mvm_vacatures_manage_all',
]) {
  assert(mierlokaalPolicy.includes(`'${cap}'`), `MierLokaal staff policy must preserve live internal capability ${cap}`);
}
assert(mierlokaalPolicy.includes("'inventory_required' => false"), 'MierLokaal staff capability inventory must be resolved');
assert(!mierlokaalPolicy.includes('mvm_local_ads_self_manage'), 'partner self-service must not grant the staff MierLokaal workspace');
assert(!mierlokaalPolicy.includes('mvm_hub3_business_access'), 'legacy Hub3 business access must not become a new V5 dependency');
assert(!mierlokaalPolicy.includes('edit_mvm_vacatures'), 'own-content partner vacancy capability must not be used as staff workspace authorization');

// Preview models and renderer are deliberately inert side-by-side components.
assert(workspaceCatalog.includes("return 'my-work';"), 'My Work remains the default V5 workspace');
assert(shellModel.includes("'routeOwnership'      => false"), 'shell preview must remain route-dormant');
assert(myWorkPreview.includes("'routeOwnership'      => false"), 'My Work preview must remain route-dormant');
assert(myWorkPreview.includes("'blocked'  => array()") && myWorkPreview.includes("'today'    => array()"), 'My Work preview must provide blocked/today lanes');
assert(myWorkPreview.includes("'protected'"), 'My Work preview summary must account for protected work after authorization');
assert(shellRenderer.includes('data-route-ownership="0"'), 'rendered preview must visibly remain non-routing');
assert(shellRenderer.includes('Waarom staat dit bovenaan?'), 'preview must explain task priority to staff');
assert(!shellRenderer.includes('href='), 'preview renderer must not create live V5 route links before cutover');
assert(!shellRenderer.includes('add_action(') && !shellRenderer.includes('register_rest_route('), 'preview renderer must not register runtime hooks/routes');

// Newsroom coexistence keeps legacy/current owners canonical and projects read-only queues.
assert(newsroomQueue.includes('V5_Newsroom_Read_Adapter::assignment_to_work_item'), 'queue model must reuse current assignment adapter');
assert(newsroomQueue.includes('V5_Newsroom_Read_Adapter::news_to_work_item'), 'queue model must reuse current news adapter');
assert(newsroomQueue.includes('My_Work_Read_Model::build'), 'queue model must authorize before queue output');
assert(newsroomQueue.includes("'readOnly'  => true"), 'Newsroom V5 queue must be explicitly read-only');
assert(newsroomQueue.includes("'legacyOwnershipPreserved' => true"), 'Newsroom queue must preserve canonical current ownership');
assert(!newsroomQueue.includes('$wpdb') && !newsroomQueue.includes('wp_update_post(') && !newsroomQueue.includes('update_option('), 'Newsroom queue must not perform writes');
assert(newsroomAdapter.includes('legacy_source'), 'Newsroom adapter must mark coexistence source');

// ContextLinks is a normalized review model over the existing Smart Links owner.
assert(contextLinks.includes("'relation'    => 'context_link_candidate'"), 'ContextLinks model must emit explicit relation candidates');
assert(contextLinks.includes("'reviewState' => 'proposed'"), 'ContextLinks candidates must start in proposed state');
assert(contextLinks.includes("'owner'      => 'existing-smart-links-runtime'"), 'existing Smart Links runtime remains owner in preview phase');
assert(contextLinks.includes("'readOnly'   => true"), 'ContextLinks preview must be explicitly read-only');
assert(!contextLinks.includes('wp_update_post(') && !contextLinks.includes('update_post_meta(') && !contextLinks.includes('$wpdb'), 'ContextLinks preview must not mutate content/database');

// New V5 preview files remain intentionally absent from the live plugin bootstrap.
for (const dormant of [
  'class-v5-workspace-policy.php',
  'class-v5-my-work-preview-model.php',
  'class-v5-shell-preview-renderer.php',
  'class-v5-newsroom-queue-model.php',
  'class-v5-context-links-read-model.php',
]) {
  assert(!bootstrap.includes(dormant), `${dormant} must remain dormant and absent from live bootstrap`);
}

console.log('PASS: MvM Hub V5 side-by-side preview/coexistence and MierLokaal staff boundary contract');
