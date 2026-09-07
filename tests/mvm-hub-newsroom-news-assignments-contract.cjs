'use strict';

const fs = require('node:fs');
const path = require('node:path');

const root = path.resolve(__dirname, '..');
const news = fs.readFileSync(path.join(root, 'plugins/mvm-hub/modules/newsroom/news/class-news-read-model.php'), 'utf8');
const assignments = fs.readFileSync(path.join(root, 'plugins/mvm-hub/modules/newsroom/assignments/class-assignments-read-model.php'), 'utf8');
const assignmentWorkflow = fs.readFileSync(path.join(root, 'plugins/mvm-hub/modules/newsroom/assignments/class-assignment-workflow.php'), 'utf8');
const controller = fs.readFileSync(path.join(root, 'plugins/mvm-hub/modules/newsroom/class-news-and-assignments-read-rest-controller.php'), 'utf8');
const moduleFile = fs.readFileSync(path.join(root, 'plugins/mvm-hub/modules/newsroom/class-newsroom-module.php'), 'utf8');

function fail(message) {
  console.error(`FAIL: ${message}`);
  process.exitCode = 1;
}
function ok(condition, message) {
  if (!condition) fail(message);
}

// News query is pre-scoped, bounded and still checks individual objects.
ok(news.includes("$args['author'] = get_current_user_id()"), 'non-team News query must be scoped to current author before execution');
ok(news.includes("current_user_can( 'edit_post', $post->ID )"), 'unpublished News items must enforce native edit_post object access');
ok(news.includes("current_user_can( 'read_post', $post->ID )"), 'published News items must enforce native read_post object access');
ok(news.includes("'posts_per_page'         => $per_page + 1"), 'News list must use bounded lookahead pagination');
ok(news.includes("'offset'                 => ( $page - 1 ) * $per_page"), 'News lookahead pagination must not skip records');
ok(news.includes("'no_found_rows'          => true"), 'News list must avoid total-count scans');
ok(!/post_content|post_excerpt/m.test(news), 'generic News list must not expose article bodies/excerpts');
ok(!/get_edit_post_link/m.test(news), 'new Hub News list must not route users through wp-admin edit URLs');
ok(news.includes("Router::hub_url( 'nieuwsroom/nieuws/'"), 'News items must point to the future central Hub detail route');
ok(news.includes("'private'"), 'legacy private posts must be visible only through object checks during mapping');
ok(news.includes('migrationNeedsMap'), 'unmapped legacy WordPress statuses must be explicit migration cases');

// Assignment list reuses data without leaking the brief and retains object checks.
ok(assignments.includes("'mvm_hub4_assignments'"), 'Assignments read must adopt existing production table');
ok(assignments.includes('Object_Access::can_read_assignment'), 'every assignment row must pass object access');
ok(!/\bbrief\b/m.test(assignments), 'generic assignment list must not expose assignment brief');
ok(assignments.includes('(assignee_user_id = %d OR created_by_user_id = %d)'), 'non-managers must be query-scoped to own/created assignments');
ok(assignments.includes('$per_page + 1'), 'Assignments must use bounded lookahead pagination');
ok(!/SELECT\s+COUNT\s*\(/mi.test(assignments), 'Assignments must avoid total-count scans');
ok(assignments.includes('Assignment_Workflow::from_legacy_status'), 'Assignments must expose explicit legacy-to-new workflow mapping');

// New assignment workflow is an actual state machine.
['new', 'assigned', 'in_progress', 'review', 'completed', 'cancelled'].forEach((state) => {
  ok(assignmentWorkflow.includes(`'${state}'`), `assignment workflow missing ${state}`);
});
ok(assignmentWorkflow.includes('required_capability'), 'assignment transitions must declare required capability');
ok(assignmentWorkflow.includes('can_transition'), 'assignment workflow must expose transition guard');
ok(assignmentWorkflow.includes("'signal'      => 'new'"), 'legacy signal status must map to new');
ok(assignmentWorkflow.includes("'ready', 'scheduled', 'published' => 'completed'"), 'legacy completion states must map explicitly');

// Routes are read-only and capability protected.
ok(controller.includes("'/newsroom/news'"), 'News read route missing');
ok(controller.includes("'/newsroom/assignments'"), 'Assignments read route missing');
ok(controller.includes('\\WP_REST_Server::READABLE'), 'News/Assignments routes must be GET/read-only');
ok(controller.includes('Capabilities::can_access_newsroom()'), 'routes must require Newsroom workspace access');
ok(controller.includes('Capabilities::ASSIGNMENTS_VIEW'), 'Assignments route must require assignment view capability');
ok(moduleFile.includes('News_And_Assignments_Read_REST_Controller'), 'Newsroom module must register News/Assignments controller');

if (!process.exitCode) {
  console.log('PASS: MvM Hub News/Assignments scope workflow privacy contract');
}
