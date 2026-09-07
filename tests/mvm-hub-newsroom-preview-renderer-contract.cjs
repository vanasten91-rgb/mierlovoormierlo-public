'use strict';

const fs = require('node:fs');
const path = require('node:path');

const root = path.resolve(__dirname, '..');
const read = (relative) => fs.readFileSync(path.join(root, relative), 'utf8');

const renderer = read('plugins/mvm-hub/modules/newsroom/class-newsroom-preview-renderer.php');
const communicationsRenderer = read('plugins/mvm-hub/modules/communications/class-communications-preview-renderer.php');
const controller = read('plugins/mvm-hub/core/class-shell-preview-rest-controller.php');
const assets = read('plugins/mvm-hub/core/class-assets.php');
const css = read('plugins/mvm-hub/assets/newsroom-preview.css');
const bootstrap = read('plugins/mvm-hub/mvm-hub.php');

function fail(message) {
  console.error(`FAIL: ${message}`);
  process.exitCode = 1;
}
function ok(condition, message) {
  if (!condition) fail(message);
}

ok(renderer.includes("'today'       => self::today()"), 'preview renderer must support Today');
ok(renderer.includes("'news'        => self::news()"), 'preview renderer must support News');
ok(renderer.includes("'assignments' => self::assignments()"), 'preview renderer must support Assignments');
ok(renderer.includes('new Today_Read_Model()'), 'Today preview must use the existing privacy-aware Today read model');
ok(renderer.includes('new News_Read_Model()'), 'News preview must use the existing object-scoped News read model');
ok(renderer.includes('new Assignments_Read_Model()'), 'Assignments preview must use the existing object-scoped assignment read model');
ok(renderer.includes('esc_html(') && renderer.includes('esc_attr('), 'preview renderer must escape rendered values');
ok(renderer.includes('array_slice( $actions, 0, 6 )'), 'Today guidance preview must stay bounded');
ok(renderer.includes('array_slice( $items, 0, 20 )'), 'assignment preview must stay bounded');

ok(!/<form\b|<input\b|<textarea\b|<select\b|type="submit"|method="post"/i.test(renderer), 'read-only Newsroom preview must not render mutation forms');
ok(!/wp-admin|edit_post_link|get_edit_post_link/i.test(renderer), 'preview must not leak wp-admin edit links');
['contact_email','contact_phone','private_note','internal_brief',"['brief']",'htmlBody','message_body'].forEach((needle) => {
  ok(!renderer.includes(needle), `preview renderer must not reference sensitive field ${needle}`);
});

ok(controller.includes("'section' => array("), 'gated preview must accept an explicit section');
ok(controller.includes('Newsroom_Preview_Renderer::render( $active_section )'), 'gated preview must obtain Newsroom module HTML from the server-side Newsroom renderer');
ok(controller.includes('Communications_Preview_Renderer::render( $active_section )'), 'gated preview must obtain Communications module HTML from the server-side Communications renderer');
ok(controller.includes("'moduleHtml'      => $module_html"), 'preview response must separate module HTML from shell HTML');

ok(/\$module_html\s*=\s*match\s*\(\s*\$active_workspace\s*\)/m.test(controller), 'preview renderer dispatch must be an explicit workspace match');
ok(/['"]newsroom['"]\s*=>\s*Newsroom_Preview_Renderer::render\(\s*\$active_section\s*\)/m.test(controller), 'Newsroom renderer must be selected only for Newsroom');
ok(/['"]communications['"]\s*=>\s*Communications_Preview_Renderer::render\(\s*\$active_section\s*\)/m.test(controller), 'Communications renderer must be selected only for Communications');
ok(/default\s*=>\s*['"]['"]/m.test(controller), 'unrelated workspaces must return no module preview HTML');
ok(!controller.includes('eval(') && !controller.includes('call_user_func'), 'preview renderer dispatch must stay explicit rather than dynamic');

ok(communicationsRenderer.includes("'messages' => self::messages()"), 'Communications preview must support internal messages');
ok(communicationsRenderer.includes("'mail'     => self::mail()"), 'Communications preview must support mail');
ok(!/<form\b|type="submit"|method="post"/i.test(communicationsRenderer), 'Communications preview must remain read-only');

ok(css.includes('.mvm-hub .mvm-newsroom-preview'), 'Newsroom preview CSS must remain scoped under .mvm-hub');
ok(!/(^|\n)\s*(?:html|body|table|ol|li|article)\s*\{/m.test(css), 'Newsroom preview CSS must not contain unscoped global selectors');
ok(assets.includes("'newsroom-preview.css'"), 'inert asset manifest must include preview stylesheet');
ok(bootstrap.includes("modules/newsroom/class-newsroom-preview-renderer.php"), 'bootstrap must load Newsroom preview renderer');
ok(bootstrap.includes("modules/communications/class-communications-preview-renderer.php"), 'bootstrap must load Communications preview renderer');

if (!process.exitCode) {
  console.log('PASS: MvM Hub isolated read-only preview renderer contract');
}
