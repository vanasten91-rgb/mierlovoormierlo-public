'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const root = path.resolve(__dirname, '..', 'plugins', 'mvm-hub4-rc-direct');
const read = (file) => fs.readFileSync(path.join(root, file), 'utf8');
const main = read('mvm-hub4.php');
const site = read('src/class-site-integrations-v1.php');
const repairs = read('src/class-frontend-repairs.php');
const forum = read('src/class-forum-layout.php');
const encyclopedia = read('src/class-encyclopedia-theme.php');
const contact = read('src/class-contact-theme.php');
const homepageContrast = read('src/class-homepage-contrast.php');
const personalization = read('src/class-personalization.php');
const personalizedFeed = read('src/class-personalized-feed-ui.php');
const publicCss = read('assets/public-v1.css');
const radar = read('src/class-newsradar-schedule.php');
const peepso = read('src/class-peepso-event-comments.php');
let assertions = 0;
const check = (condition, message) => { assert.ok(condition, message); assertions += 1; };

for (const file of ['class-site-integrations-v1.php', 'class-frontend-repairs.php', 'class-forum-layout.php', 'class-encyclopedia-theme.php', 'class-contact-theme.php', 'class-homepage-contrast.php', 'class-newsradar-schedule.php', 'class-peepso-event-comments.php']) {
    check(main.includes(`src/${file}`), `${file} must be source-owned by Hub 1.1.`);
}
for (const init of ['MvM_Hub4_Site_Integrations_V1::init();', 'MvM_Hub4_Frontend_Repairs::init();', 'MvM_Hub4_Forum_Layout::init();', 'MvM_Hub4_Encyclopedia_Theme::init();', 'MvM_Hub4_Contact_Theme::init();', 'MvM_Hub4_Homepage_Contrast::init();', 'MvM_Hub4_Newsradar_Schedule::init();', 'MvM_Hub4_PeepSo_Event_Comments::init();']) {
    check(main.includes(init), `${init} must boot with Hub 1.1.`);
}

check(site.includes('mvm-share-v1') && site.includes('display:none!important'), 'Generic MvM Delen/Opslaan block must remain disabled sitewide.');
check(site.includes('mvm-event-practical') && site.includes("is_singular( 'event_listing' )"), 'Only the duplicate compact event practical block may be removed.');
for (const ratio of ['21/9', '18/9', '16/9', '4/3', '1/1', '2/3', '9/16']) {
    check(site.includes(`aspect-ratio:${ratio}`), `Event video ratio ${ratio} must be retained.`);
}
check(site.includes('mvm-peepso-userbar-strip') && site.includes('COMMUNITY_PAGE_IDS'), 'Community-only header userbar suppression must be source-owned.');
check(site.includes('mvm-today-hub-panel-v1') && site.includes('mvm_today_hub_panel_v1') && site.includes("home_url( '/hub/' )"), 'Mierlo Vandaag compatibility output must remain excluded from canonical Hub routes.');
check(site.includes("'mvm-bh-bronnen'") && site.includes('mvm-source-sort'), 'Legacy Bronnenregister sorting must be source-owned.');
for (const sortMode of ['priority-asc', 'priority-desc', 'name-asc', 'name-desc', 'frequency', 'checked-desc', 'checked-asc', 'arrival-desc', 'arrival-asc']) {
    check(site.includes(sortMode), `Source register sort mode ${sortMode} must remain available.`);
}
check(site.includes('document.createElement') && site.includes('textContent'), 'Source sorting UI must construct labels/options through safe DOM APIs.');
check(!/\binnerHTML\b|\bouterHTML\b|insertAdjacentHTML|\beval\b/.test(site), 'Consolidated site UI must avoid unsafe HTML/eval sinks.');

check(repairs.includes("add_shortcode( 'mvm_events_overview'"), 'The repaired events overview must be source-owned.');
check(repairs.includes("'post_type'              => 'event_listing'"), 'Events overview must read existing WP Event Manager events.');
check(repairs.includes("'_event_start_date'") && repairs.includes("'_event_end_date'") && repairs.includes("'_event_location'"), 'Events overview must use existing event metadata without migration.');
check(repairs.includes("get_the_post_thumbnail_url( $post, 'medium_large' )"), 'Events overview must retain event images.');
check(!/wp_insert_post|wp_update_post|wp_delete_post|update_post_meta/.test(repairs), 'Frontend repair layer must remain read-only for event data.');
check(repairs.includes('.mvm-public-home') && repairs.includes('.mvm-az-scroll{max-height:none!important;overflow:visible!important'), 'Encyclopedia repair must remove the broken internal A-Z scroller and restore the page layout.');
check(repairs.includes('mvm-theme1-discover-photo-copy') && repairs.includes('-webkit-text-fill-color:#fff!important'), 'Homepage image-bank overlay must force visible white copy.');

check(forum.includes('.mvm-forum-actions') && forum.includes('display:none!important'), 'Custom forum New topic action must be fully hidden.');
check(forum.includes('max-width:1152px!important'), 'Forum must follow the 1152px MvM homepage content width.');
check(forum.includes('#wpforo') && forum.includes('#wpforo-wrap'), 'Forum width guard must constrain both wpForo containers without editing wpForo.');
check(!/update_option|wp_insert_post|wp_update_post|wp_delete_post/.test(forum), 'Forum layout module must be presentation-only.');

check(encyclopedia.includes("home_url( '/encyclopedie/' )"), 'Encyclopedia theme must be route-scoped.');
check(encyclopedia.includes('max-width:1152px!important'), 'Encyclopedia must align to the MvM page width.');
check(encyclopedia.includes('--mvm-ency-blue:#1966AE') && encyclopedia.includes('linear-gradient(135deg,var(--mvm-ency-blue)'), 'Encyclopedia masthead must use the MvM blue design token with white text.');
check(encyclopedia.includes('--mvm-ency-ink:#17202a') && encyclopedia.includes('background:#fff!important'), 'White encyclopedia cards must use the dark MvM ink token.');
check(encyclopedia.includes('--mvm-ency-soft:#f4f7fa') && encyclopedia.includes('--mvm-ency-ink:#17202a'), 'Light encyclopedia navigation cards must use a light surface with dark text.');
check(encyclopedia.includes('.mvm-az-scroll') && encyclopedia.includes('max-height:none!important') && encyclopedia.includes('overflow:visible!important'), 'Encyclopedia A-Z must not use a broken internal scroll container.');
check(encyclopedia.includes('html[data-theme="dark"]') && encyclopedia.includes('background:#17222d!important'), 'Encyclopedia must preserve a readable dedicated dark mode.');
check(encyclopedia.includes('@media(max-width:640px)') && encyclopedia.includes('grid-template-columns:1fr!important'), 'Encyclopedia search/layout must remain mobile responsive.');
check(encyclopedia.includes('content:"Hoofdstukken"') && encyclopedia.includes('.mvm-timeline-era-nav'), 'Encyclopedia must expose clear chapter and timeline navigation.');
check(encyclopedia.includes('.mvm-az-nav') && encyclopedia.includes('position:sticky!important'), 'Encyclopedia A-Z must provide persistent letter navigation.');

check(contact.includes('CONTACT_PAGE_ID = 140'), 'Contact contrast guard must remain scoped to the contact page.');
check(contact.includes('.elementor-element-c0a1b2c3'), 'Contact contrast guard must target the blue hero strip only.');
check(contact.includes('color:#fff!important') && contact.includes('-webkit-text-fill-color:#fff!important'), 'Contact hero label, title and intro must be white across browsers.');
check(!/wp_update_post|update_post_meta|update_option|innerHTML|eval\s*\(/.test(contact), 'Contact contrast guard must remain presentation-only.');

check(homepageContrast.includes('.mvm-theme1-action-card--primary'), 'Homepage community contrast guard must target the broken primary card only.');
check(homepageContrast.includes('background:#fff!important') && homepageContrast.includes('color:#17202a!important'), 'Light primary community card must use white background and dark text.');
check(homepageContrast.includes('color:#1966AE!important') && homepageContrast.includes('.mvm-theme1-action-icon'), 'Homepage card accent/link and icon rules must use MvM blue.');
check(homepageContrast.includes('html[data-theme="dark"]') && homepageContrast.includes('background:#17222d!important'), 'Homepage contrast guard must preserve a dedicated dark-mode treatment.');

check(personalization.includes("'imageUrl'") && personalization.includes("'imageAlt'") && personalization.includes("get_post_thumbnail_id( $post )"), 'Mijn Mierlo feed data must expose safe featured-image metadata.');
check(personalizedFeed.includes('mvm-public-v1__thumb') && personalizedFeed.includes("$item['imageUrl']"), 'Mijn Mierlo cards must render the small news thumbnail when available.');
check(publicCss.includes('.mvm-public-v1__thumb') && publicCss.includes('object-fit:cover'), 'Mijn Mierlo thumbnail must have a stable responsive image treatment.');

check(radar.includes("HOOK = 'mvm_newsradar_staggered_v2'"), 'Nieuwsradar must use the staggered v2 hook.');
check(radar.includes("'interval' => 5 * MINUTE_IN_SECONDS"), 'Nieuwsradar must poll due sources in bounded five-minute ticks.');
check(radar.includes("return array( $source );") && radar.includes("$settings['source_batch'] = 1"), 'Each managed Nieuwsradar run must expose and process at most one source.');
check(!radar.includes("AND s.frequency = 'daily'") && radar.includes('LIMIT 1') && radar.includes('next_check_for_frequency'), 'Nieuwsradar must select one due source independent of frequency and reschedule by the configured frequency.');
check(radar.includes("array( 'A' => 0, 'B' => 1, 'C' => 2 )"), 'Monitoring sources must preserve A-B-C priority ordering.');
check(radar.includes('add_option( self::LOCK_OPTION') && radar.includes('crawler_busy()'), 'Staggered runs must honor both Hub and legacy crawler locks.');
check(radar.includes("wp_clear_scheduled_hook( self::OLD_HOOK") && radar.includes('wp_schedule_event'), 'Old fixed slots must be removed and replaced by the recurring staggered scheduler.');
check(main.includes('MvM_Hub4_Newsradar_Schedule::deactivate();'), 'Hub deactivation must remove Nieuwsradar scheduler state.');

check(peepso.includes("private const ALLOWED_TYPES = array( 'event_listing', 'event_organizer', 'event_venue' )"), 'PeepSo bridge must stay limited to event content.');
check(peepso.includes("'create_peepso_companion'"), 'PeepSo companion creation must continue through the existing MvM event bridge.');
check(peepso.includes("'peepso_postnotify'"), 'PeepSo event linkage must continue using the established companion relation.');
check(peepso.includes('activity_table_name') && peepso.includes('blogposts_module_id'), 'Activity normalization must use the existing event bridge when available.');
check(peepso.includes('ps-comments--blogpost'), 'Only a valid PeepSo BlogPosts comment component may be rendered.');
check(peepso.includes('mvm-native-peepso-comments') && peepso.includes('mvm-native-peepso-event-comments'), 'The proven upper comment field must remain and the lower duplicate must be removed.');
check(peepso.includes("add_filter( 'comments_open'"), 'Native WordPress comments must remain closed for event content while PeepSo is used.');
check(!/deactivate_plugins|delete_plugins|wp_delete_plugin|update_option\s*\(\s*['\"]peepso/.test(peepso), 'Hub integration must not reconfigure or remove PeepSo itself.');

console.log(`Hub 1.1 site consolidation contract: ${assertions} assertions passed.`);
