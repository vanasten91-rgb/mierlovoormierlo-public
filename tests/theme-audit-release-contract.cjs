'use strict';

const fs = require('fs');
const assert = require('assert');

const root = 'themes/newsup-pro-child';
const read = (name) => fs.readFileSync(`${root}/${name}`, 'utf8');

const engine = read('mvm-events-2.php');
const single = read('single-event_listing.php');
const overview = read('page-evenementen.php');
const organizers = read('mvm-organisatoren.php');
const entrepreneurs = read('mvm-ondernemers.php');
const today = read('mvm-theme-1-today-source.php');
const seo = read('mvm-seo-v2.php');
const css = read('mvm-events-2.css');

// Login/admin routing: staff must win over auxiliary portal roles.
assert(engine.includes("if ( mvm_e2_is_staff( $user ) )"), 'staff-first login redirect missing');
assert(engine.includes("! mvm_e2_is_staff( $user ) && in_array( 'mvm_organisator'"), 'PeepSo organizer target must exclude staff');
assert(engine.includes("! mvm_e2_is_staff( $user ) && in_array( 'mvm_ondernemer'"), 'PeepSo entrepreneur target must exclude staff');
assert(engine.includes("wp_doing_ajax() || mvm_e2_is_staff()"), 'admin guard must exempt staff and AJAX');
assert(engine.includes("return home_url( '/organisatoren/' )"), 'organizer target missing');
assert(engine.includes("return home_url( '/ondernemers/' )"), 'entrepreneur target missing');

// Ownership and write boundaries.
assert(engine.includes("check_admin_referer( 'mvm_e2_save_event', 'mvm_nonce' )"), 'event nonce missing');
assert(engine.includes("check_admin_referer( 'mvm_e2_save_ad', 'mvm_nonce' )"), 'ad nonce missing');
assert(engine.includes("mvm_e2_owned_post( $event_id, 'event_listing' )"), 'event ownership gate missing');
assert(engine.includes("mvm_e2_owned_post( $ad_id, 'mvm_advertentie' )"), 'ad ownership gate missing');
assert(engine.includes("$status = 'pending'"), 'new event must begin pending');
assert(engine.includes("? 'publish' : 'pending'"), 'new ad must begin pending');

// Canonical legacy routes and portal indexing policy.
for (const route of [
  '/een-evenement-posten/', '/evenement-dashboard/', '/organisator-dashboard/',
  '/locatie-dashboard/', '/evenement-organisatoren/', '/evenementlocaties/'
]) {
  assert(engine.includes(route), `legacy route contract missing: ${route}`);
}
for (const directive of ["$robots['noindex']", "$robots['nofollow']", "$robots['noarchive']"]) {
  assert(engine.includes(directive), `portal robots directive missing: ${directive}`);
}

// Public PeepSo aliases remain canonical even before the Hub router is promoted.
assert(seo.includes("'/community/' => '/activity/'"), 'community canonical fallback missing');
assert(seo.includes("'/groups/'    => '/groepen/'"), 'groups canonical fallback missing');
assert(seo.includes("'/pages/'     => '/paginas/'"), 'pages canonical fallback missing');
assert(seo.includes("add_action( 'template_redirect', 'mvm_seo_v2_redirect_legacy_public_routes', -80 )"), 'theme canonical fallback priority missing');
assert(seo.includes("wp_safe_redirect( home_url( $redirects[ $request_path ] ), 301, 'MvM canonical route' )"), 'canonical fallback must use safe permanent redirect');

// Event detail integration: official RSVP hook, one share block, PeepSo comments retained.
assert(single.includes("has_action( 'single_event_listing_button_start' )"), 'RSVP hook presence check missing');
assert(single.includes("do_action( 'single_event_listing_button_start' )"), 'official RSVP hook invocation missing');
assert.strictEqual((single.match(/mvm_e2_share_block\s*\(/g) || []).length, 1, 'event detail must render one share block');
assert(single.includes('comments_template();'), 'event comments integration missing');
assert(!single.includes('the_content();'), 'legacy WPEM single wrapper must not be reintroduced');

// Agenda and portal UI contract.
assert(overview.includes("isset( $_GET['archief'] )"), 'event archive toggle missing');
assert(overview.includes('mvm_e2_events_query( $past )'), 'event query integration missing');
assert(organizers.includes("$event_args['author'] = get_current_user_id()"), 'organizer own-content filter missing');
assert(organizers.includes("wp_nonce_field( 'mvm_e2_save_event', 'mvm_nonce' )"), 'organizer form nonce missing');
assert(entrepreneurs.includes("$args['author'] = get_current_user_id()"), 'entrepreneur own-content filter missing');
assert(entrepreneurs.includes('MvM-pagina maken'), 'MvM page wording missing');
assert(entrepreneurs.includes("selected( $ad_category, 'algemeen' )"), 'ad category persistence missing');

// Homepage and agenda must interpret all-day/legacy time metadata consistently.
assert(today.includes("$end_time = trim( (string) get_post_meta( $event_id, '_event_end_time', true ) )"), 'homepage end-time read missing');
assert(today.includes("preg_match( '/^\\d{2}:\\d{2}:\\d{2}$/', $time )"), 'homepage legacy HH:MM:SS normalization missing');
assert(today.includes("'_mvm_all_day'"), 'homepage all-day flag missing');
assert(today.includes("$time_label = $all_day ? 'Hele dag'"), 'homepage all-day label missing');

// Responsive, dark mode and explicit RSVP styling.
assert(css.includes('@media(max-width:900px)'), 'tablet responsive contract missing');
assert(css.includes('@media(max-width:620px)'), 'mobile responsive contract missing');
assert(css.includes('body.dark-mode .mvm-e2-shell'), 'body dark-mode contract missing');
assert(css.includes('html[data-theme="dark"] .mvm-e2-shell'), 'data-theme dark-mode contract missing');
assert(css.includes('.mvm-e2-rsvp{'), 'RSVP style contract missing');
assert(css.includes('-webkit-text-fill-color'), 'WebKit text-fill contrast guard missing');

console.log('Full-site audit theme release contract: OK');
