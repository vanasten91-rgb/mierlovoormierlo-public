'use strict';

const fs = require('fs');
const path = require('path');

const root = path.resolve(__dirname, '..');
const pluginPath = path.join(root, 'plugins/mvm-hub4-rc-direct/mvm-hub4.php');
const routerPath = path.join(root, 'plugins/mvm-hub4-rc-direct/src/class-site-canonical.php');

const plugin = fs.readFileSync(pluginPath, 'utf8');
const router = fs.readFileSync(routerPath, 'utf8');

function assert(condition, message) {
  if (!condition) {
    console.error(`FAIL: ${message}`);
    process.exit(1);
  }
}

assert(plugin.includes("require_once MVM_HUB4_DIR . 'src/class-site-canonical.php'"), 'site canonical router must be loaded');
assert(plugin.includes('MvM_Hub4_Site_Canonical::init();'), 'site canonical router must be initialized');
assert(router.includes("add_action( 'template_redirect', array( __CLASS__, 'redirect_legacy_routes' ), -90 )"), 'legacy redirects must run before normal rendering');
assert(router.includes("'/een-evenement-posten/'          => '/organisatoren/'"), 'old event submission route must redirect to organizer portal');
assert(router.includes("'/organisator-dashboard/'         => '/organisatoren/'"), 'old organizer dashboard must redirect to organizer portal');
assert(router.includes("'/evenement-organisatoren/'       => '/evenementen/'"), 'old organizer listing must redirect to agenda');
assert(router.includes("'/community/'                     => '/activity/'"), 'old community route must redirect to current activity route');
assert(router.includes("'/groups/'                        => '/groepen/'"), 'old groups route must redirect to current Dutch route');
assert(router.includes("'/pages/'                         => '/paginas/'"), 'old pages route must redirect to current Dutch route');
assert(router.includes("wp_safe_redirect( home_url( $redirects[ $normalized ] ), 301, 'MvM canonical route' )"), 'canonical redirects must be permanent and safe');
assert(router.includes("array( '/organisatoren/', '/ondernemers/' )"), 'private frontend portals must have explicit robots protection');
assert(router.includes("$robots['noindex']"), 'private frontend portals must be noindex');
assert(!router.includes("'/participant/'"), 'wpForo participant namespace must not be hijacked');
assert(!/\bwp_delete_post\s*\(/.test(router), 'canonical router must not delete content');
assert(!/\bupdate_option\s*\(/.test(router), 'canonical router must not mutate settings');

console.log('Hub 1.1 site canonical route contract: all assertions passed.');
