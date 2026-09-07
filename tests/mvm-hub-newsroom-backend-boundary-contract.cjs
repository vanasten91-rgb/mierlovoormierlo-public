'use strict';

const fs = require('fs');
const assert = require('assert');

const read = (path) => fs.readFileSync(path, 'utf8');
const admin = read('plugins/mvm-hub/modules/newsroom/class-newsroom-admin.php');
const kernel = read('plugins/mvm-hub/core/class-kernel.php');
const workspaces = read('plugins/mvm-hub/core/class-workspaces.php');
const runtime = read('plugins/mvm-hub/core/class-hub-runtime.php');
const js = read('plugins/mvm-hub/assets/newsroom2-admin.js');
const css = read('plugins/mvm-hub/assets/newsroom2-admin.css');

assert(admin.includes('final class Newsroom_Admin'), 'Backend Newsroom admin class missing');
assert(admin.includes("add_action( 'admin_menu'"), 'Newsroom must register through wp-admin');
assert(admin.includes('add_menu_page('), 'Newsroom wp-admin menu missing');
assert(admin.includes('is_admin()'), 'Newsroom access must remain scoped to wp-admin');
assert(admin.includes('is_user_logged_in()'), 'Newsroom must require authentication');
assert(admin.includes('Hub_Security_Policy::response_headers()'), 'Private response headers missing');
assert(kernel.includes('Newsroom_Admin::register();'), 'Kernel must boot backend Newsroom');
assert(kernel.includes('Staff_Collaboration::register();'), 'Kernel must boot staff collaboration service');

assert(workspaces.includes("'newsroom' => array("), 'Legacy Newsroom route descriptor must remain resolvable for compatibility');
assert(workspaces.includes("if ( 'newsroom' === $key )"), 'Newsroom must be filtered from standalone /hub/ navigation');
assert(runtime.includes("if ( 'newsroom' === $requested_workspace )"), 'Legacy /hub/ Newsroom requests must have an explicit backend handoff');
assert(runtime.includes("admin_url( 'admin.php?page=mvm-newsroom' )"), 'Legacy Newsroom requests must redirect to backend Newsroom');
assert(runtime.includes('wp_safe_redirect('), 'Backend handoff must use a safe redirect');

assert(js.includes('document.body.dataset.mvmNewsroomTheme = safeTheme'), 'Theme state must reach the wp-admin body');
assert(css.includes('body[data-mvm-newsroom-theme="light"] #wpcontent'), 'Light admin canvas selector missing');
assert(css.includes('body[data-mvm-newsroom-theme="dark"] #wpcontent'), 'Dark admin canvas selector missing');
assert(css.includes('background: #0E1721;'), 'Dark admin canvas background missing');
assert(css.includes('background: #F5F7FA;'), 'Canonical MvM light admin canvas background missing');

assert(!fs.existsSync('plugins/mvm-hub/modules/newsroom/class-newsroom-2-renderer.php'), 'Deprecated standalone Newsroom 2 renderer must not return');

console.log('MvM Newsroom backend boundary contract: OK');
