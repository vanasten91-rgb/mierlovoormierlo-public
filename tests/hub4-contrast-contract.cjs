'use strict';

const fs = require('fs');
const path = require('path');
const assert = require('assert');

const root = path.resolve(__dirname, '..');
const read = (file) => fs.readFileSync(path.join(root, file), 'utf8');

const css = read('plugins/mvm-hub4-rc-direct/assets/contrast-v1.css');
const sitewide = read('plugins/mvm-hub4-rc-direct/assets/mvm-sitewide-v1.css');
const encyclopedia = read('plugins/mvm-hub4-rc-direct/src/class-encyclopedia-theme.php');
const encyclopediaDetail = read('plugins/mvm-hub4-rc-direct/assets/encyclopedia-hub12-detail.css');
const app = read('plugins/mvm-hub4-rc-direct/templates/app.php');
const main = read('plugins/mvm-hub4-rc-direct/mvm-hub4.php');
const loader = read('plugins/mvm-hub4-rc-direct/src/class-contrast.php');

function rgb(hex) {
    const value = hex.replace('#', '');
    return [0, 2, 4].map((offset) => parseInt(value.slice(offset, offset + 2), 16) / 255);
}

function luminance(hex) {
    return rgb(hex)
        .map((channel) => channel <= 0.03928 ? channel / 12.92 : Math.pow((channel + 0.055) / 1.055, 2.4))
        .reduce((sum, channel, index) => sum + channel * [0.2126, 0.7152, 0.0722][index], 0);
}

function ratio(foreground, background) {
    const a = luminance(foreground);
    const b = luminance(background);
    const lighter = Math.max(a, b);
    const darker = Math.min(a, b);
    return (lighter + 0.05) / (darker + 0.05);
}

const pairs = [
    ['#17222d', '#ffffff', 'light primary text'],
    ['#46596b', '#ffffff', 'light muted text'],
    ['#1966AE', '#ffffff', 'light MvM link'],
    ['#f5f8fb', '#111923', 'dark primary text'],
    ['#c9d5e1', '#111923', 'dark muted text'],
    ['#6DB8F2', '#111923', 'dark link'],
    ['#ffffff', '#1966AE', 'MvM blue hero text'],
];

for (const [foreground, background, label] of pairs) {
    assert(ratio(foreground, background) >= 4.5, `${label} must meet WCAG AA`);
}

for (const token of [
    '--mvm-hub4-surface: var(--surface)',
    '--mvm-hub4-soft: var(--surface-alt)',
    '--mvm-hub4-border: var(--border)',
    '--mvm-hub4-text: var(--text)',
    '--mvm-hub4-muted: var(--muted)',
]) {
    assert(css.includes(token), `missing compatibility token: ${token}`);
}

assert(css.includes('.mvm-hub4__platform-form'), 'platform form contrast override missing');
assert(css.includes('.mvm-news-radar__source-card'), 'Nieuwsradar contrast override missing');
assert(css.includes('.mvm-public-v1__card'), 'public MvM dark-surface override missing');
assert(css.includes('mvm-hub4-admin-skin--chrome-only'), 'third-party admin isolation guard missing');
assert(css.includes('body.mvm-hub4[data-theme="dark"]'), 'explicit Hub dark theme override missing');
assert(css.includes('body.mvm-hub4[data-theme="light"]'), 'explicit Hub light theme override missing');

assert(main.includes("require_once MVM_HUB4_DIR . 'src/class-contrast.php';"), 'contrast class not bootstrapped');
assert(main.includes('MvM_Hub4_Contrast::init();'), 'contrast class not initialized');
assert(loader.includes("add_action( 'wp_enqueue_scripts'"), 'frontend contrast enqueue missing');
assert(loader.includes("add_action( 'admin_enqueue_scripts'"), 'admin contrast enqueue missing');
assert(loader.includes("MVM_HUB4_URL . 'assets/contrast-v1.css'"), 'contrast asset path missing');
assert(loader.includes("MVM_HUB4_URL . 'assets/mvm-sitewide-v1.css'"), 'sitewide MvM visual contract must be enqueued');
assert(loader.includes("MVM_HUB4_URL . 'assets/encyclopedia-hub12-detail.css'"), 'encyclopedia Hub 1/2 detail stylesheet must be enqueued');
assert(loader.includes("self::is_encyclopedia_request()"), 'encyclopedia detail stylesheet must remain route-scoped');
assert(loader.includes("'wp_enqueue_scripts' === current_filter()"), 'sitewide MvM contract must remain frontend-only');

for (const surface of [
    '.mvm-marketplace',
    '.mvm-offers',
    '.mvm-ad-examples',
    '.mvm-events-v1',
    '.mvm-public-home',
    '.mvm-newsletter',
    '.mvm-entrepreneur-portal',
]) {
    assert(sitewide.includes(surface), `shared MvM style missing surface ${surface}`);
}
assert(sitewide.includes('--mvm-brand:#1966AE'), 'sitewide MvM brand token missing');
assert(sitewide.includes('-webkit-text-fill-color:#fff!important'), 'blue/dark surfaces must explicitly guard WebKit text fill');
assert(sitewide.includes('.mvm-public-home .mvm-scroll-hint'), 'encyclopedia stale scroll hint guard missing');
assert(sitewide.includes('display:none!important'), 'encyclopedia scroll hint must be hidden when nested scrolling is disabled');
assert(sitewide.includes('body.single-mvm_encyclopedie'), 'encyclopedia article body scope missing');
assert(sitewide.includes('body.single-mvm_persoon'), 'encyclopedia person body scope missing');
assert(sitewide.includes('body.single-mvm_bron'), 'encyclopedia source body scope missing');
assert(sitewide.includes('.mg-blog-post-box.single>.mg-header'), 'actual Newsup encyclopedia header must receive MvM hero treatment');
assert(sitewide.includes('main.single-class>.container-fluid'), 'encyclopedia detail width contract missing');
assert(sitewide.includes('width:min(100% - 28px,1152px)!important'), 'encyclopedia detail must align to MvM 1152px content width');
assert(sitewide.includes('linear-gradient(135deg,#1966AE 0%,#124f88 100%)'), 'encyclopedia/detail MvM blue hero gradient missing');
assert(!sitewide.includes('#wpforo'), 'sitewide contract must not style wpForo internals');
assert(!sitewide.includes('.elementor'), 'sitewide contract must not style Elementor internals');
assert(!sitewide.includes('.ultp'), 'sitewide contract must not style PostX internals');

// Hub 1/2 inspired encyclopedia dashboard contract.
for (const selector of [
    '.mvm-public-home .mvm-entry-grid',
    '.mvm-public-home .mvm-card-grid',
    '.mvm-public-home .mvm-az-nav',
    '.mvm-public-home .mvm-az-letter',
    '.mvm-public-home .mvm-timeline-toolbar',
    '.mvm-public-home .mvm-timeline-era-nav',
    '.mvm-public-home .mvm-timeline-track',
    '.mvm-public-home .mvm-timeline-entry',
    '.mvm-themes-index .mvm-theme-jump',
    '.mvm-themes-index .mvm-theme-bundle',
]) {
    assert(encyclopedia.includes(selector), `Hub 1/2 encyclopedia presentation missing ${selector}`);
}
assert(encyclopedia.includes('content:"Hoofdstukken"'), 'overview must visibly label the chapter menu');
assert(encyclopedia.includes('grid-template-columns:repeat(6,minmax(0,1fr))'), 'desktop chapter menu must be a six-card dashboard');
assert(encyclopedia.includes('grid-template-columns:repeat(13,minmax(36px,1fr))'), 'A-Z desktop navigation must use a clear letter grid');
assert(encyclopedia.includes('position:sticky!important'), 'A-Z letter navigation must remain accessible while browsing');
assert(encyclopedia.includes('grid-template-columns:repeat(3,minmax(0,1fr))'), 'overview cards and timeline era menu must retain multi-column desktop layout');
assert(encyclopedia.includes('border-left:3px solid #c8ddf0!important'), 'timeline must retain its visible chronological spine');
assert(encyclopedia.includes('.mvm-scroll-hint{display:none!important}'), 'obsolete nested-scroll hint must stay hidden');
assert(encyclopedia.includes('@media(max-width:640px)'), 'encyclopedia dashboard must have a dedicated mobile contract');
assert(encyclopedia.includes('html[data-theme="dark"] .mvm-public-home'), 'encyclopedia dashboard must retain dark-mode support');

// The same dashboard language must continue through all dossier types.
for (const detailType of [
    'single-mvm_encyclopedie',
    'single-mvm_persoon',
    'single-mvm_locatie',
    'single-mvm_gebouw',
    'single-mvm_gebeurtenis',
    'single-mvm_vereniging',
    'single-mvm_bedrijf',
    'single-mvm_beeld',
    'single-mvm_bron',
]) {
    assert(encyclopediaDetail.includes(detailType), `Hub 1/2 detail styling missing ${detailType}`);
}
for (const detailSelector of [
    '.mvm-dossier-breadcrumb',
    '.mvm-secondary-themes',
    '.mvm-facts-card',
    '.mvm-dossier-neighbors',
    '.mvm-anchor-toc',
    '.mvm-dossier-content',
    '.mvm-wiki-network',
]) {
    assert(encyclopediaDetail.includes(detailSelector), `Hub 1/2 detail card missing ${detailSelector}`);
}
assert(encyclopediaDetail.includes('--mvm-ency-detail-blue:#1966AE'), 'detail cards must use the MvM blue token');
assert(encyclopediaDetail.includes('border-radius:var(--mvm-ency-detail-radius)!important'), 'detail cards must remain rounded');
assert(encyclopediaDetail.includes('grid-template-columns:repeat(2,minmax(0,1fr))!important'), 'facts, neighbours and TOC must use readable desktop grids');
assert(encyclopediaDetail.includes('border-left:5px solid var(--mvm-ency-detail-blue)!important'), 'dossier section headings must keep a clear MvM blue chapter marker');
assert(encyclopediaDetail.includes('html[data-theme="dark"]'), 'detail cards must retain dark-mode support');
assert(encyclopediaDetail.includes('@media(max-width:767px)'), 'detail cards must collapse safely on mobile');
assert(!encyclopediaDetail.includes('#wpforo'), 'encyclopedia detail CSS must not style wpForo');
assert(!encyclopediaDetail.includes('.elementor'), 'encyclopedia detail CSS must not style Elementor internals');
assert(!encyclopediaDetail.includes('.ps-'), 'encyclopedia detail CSS must not style PeepSo internals');

const responsivePosition = app.indexOf("assets/responsive-hardening.css");
const contrastPosition = app.indexOf("assets/contrast-v1.css");
assert(responsivePosition >= 0 && contrastPosition > responsivePosition, 'contrast asset must be defined after responsive CSS');
assert(app.includes("<link rel=\"stylesheet\" href=\"<?php echo esc_url( $contrast_css ); ?>\">"), 'standalone Hub contrast link missing');

console.log(`Hub4 contrast contract passed (${pairs.length} WCAG colour pairs + Hub 1/2 encyclopedia dashboard/detail checks).`);
