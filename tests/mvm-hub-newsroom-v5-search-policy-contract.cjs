'use strict';

const fs = require('fs');
const path = require('path');

const root = path.resolve(__dirname, '..');
const policyPath = 'plugins/mvm-hub/core/class-v5-search-document-policy.php';
const readModelPath = 'plugins/mvm-hub/core/class-v5-search-shared-read-model.php';
const bootstrapPath = 'plugins/mvm-hub/mvm-hub.php';
const policy = fs.readFileSync(path.join(root, policyPath), 'utf8');
const readModel = fs.readFileSync(path.join(root, readModelPath), 'utf8');
const bootstrap = fs.readFileSync(path.join(root, bootstrapPath), 'utf8');

const assert = (condition, message) => {
  if (!condition) throw new Error(message);
};

for (const token of [
  'search_visibility_denied',
  'classification_not_public',
  'secret_never_indexed',
  'source_protected_shared_index_denied',
  'confidential_shared_index_denied',
  'object_access_denied',
  'step_up_required',
  'ok_direct_object_lookup',
]) {
  assert(policy.includes(token), `search policy must preserve ${token}`);
}

assert(policy.includes("public const SCOPE_PUBLIC = 'public'"), 'public index scope must stay explicit');
assert(policy.includes("public const SCOPE_STAFF  = 'staff'"), 'staff index scope must stay explicit');
assert(policy.includes("$document['search_visibility'] ?? 'inherit'"), 'documents must support an explicit discoverability boundary');
assert(policy.includes("if ( 'none' === $search_visibility )"), 'search_visibility=none must fail before classification-based allow');
assert(policy.includes('Data_Classification::normalize'), 'unknown classifications must flow through fail-closed normalization');
assert(policy.includes('filter_results'), 'result rendering must re-apply policy independently from index construction');
assert(policy.includes("true !== ( $context['step_up_satisfied'] ?? false )"), 'source-protected direct lookup must require step-up');
assert(policy.includes("true !== ( $context['object_access_allowed'] ?? false )"), 'confidential/protected direct lookup must require object policy');

assert(readModel.includes('private const MAX_RESULTS = 100'), 'shared search must stay bounded');
assert(readModel.includes("'direct_object_lookup' => false"), 'shared read model must not accept direct-sensitive-lookup escalation');
assert(readModel.includes('V5_Search_Document_Policy::filter_results'), 'shared read model must reapply search policy');
assert(readModel.includes('wp_strip_all_tags'), 'shared search text must be stripped of markup');
assert(!readModel.includes("provider_payload'"), 'shared read model must not explicitly project arbitrary provider payloads');
assert(!readModel.includes("source_notes'"), 'shared read model must not explicitly project source notes');

const forbiddenPatterns = [
  /add_action\s*\(/,
  /add_filter\s*\(/,
  /register_rest_route\s*\(/,
  /wp_insert_post\s*\(/,
  /wp_update_post\s*\(/,
  /update_post_meta\s*\(/,
  /delete_post_meta\s*\(/,
  /\$wpdb\s*->/,
];

for (const [label, source] of [['policy', policy], ['read-model', readModel]]) {
  for (const pattern of forbiddenPatterns) {
    assert(!pattern.test(source), `${label} must remain dormant/read-only: ${pattern}`);
  }
}

assert(!bootstrap.includes('class-v5-search-document-policy.php'), 'search policy must not be loaded by live bootstrap');
assert(!bootstrap.includes('class-v5-search-shared-read-model.php'), 'shared search read model must not be loaded by live bootstrap');

console.log('PASS: MvM Hub V5 search policy and shared read model remain dormant and fail-closed against classification/discoverability leakage');
