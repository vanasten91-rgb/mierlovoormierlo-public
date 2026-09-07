<?php
// Consolidated from production Code Snippet #387.
defined( 'ABSPATH' ) || exit;

function mvm_global_contrast_css_v1() {
    return <<<'MVM_CONTRAST_CSS'
<style id="mvm-global-contrast-v1">
:root {
    --mvm-contrast-blue: #1966AE;
    --mvm-contrast-blue-dark: #124f88;
    --mvm-contrast-bg: #eef3f8;
    --mvm-contrast-surface: #ffffff;
    --mvm-contrast-surface-alt: #f6f9fc;
    --mvm-contrast-text: #17222d;
    --mvm-contrast-muted: #46596b;
    --mvm-contrast-border: #c5d3df;
    --mvm-contrast-link: #1966AE;
    --mvm-contrast-focus: #ffbf47;
}

/* Hub 4: één bron voor alle kleurvariabelen, inclusief oudere modulenaamgeving. */
body.mvm-hub4, .mvm-hub4 {
    --blue: var(--mvm-contrast-blue);
    --blue-dark: var(--mvm-contrast-blue-dark);
    --surface: var(--mvm-contrast-surface);
    --surface-alt: var(--mvm-contrast-surface-alt);
    --border: var(--mvm-contrast-border);
    --text: var(--mvm-contrast-text);
    --muted: var(--mvm-contrast-muted);
    --mvm-hub4-blue: var(--blue);
    --mvm-hub4-blue-dark: var(--blue-dark);
    --mvm-hub4-surface: var(--surface);
    --mvm-hub4-soft: var(--surface-alt);
    --mvm-hub4-border: var(--border);
    --mvm-hub4-text: var(--text);
    --mvm-hub4-muted: var(--muted);
    --mvm-hub4-link: var(--mvm-contrast-link);
    color: var(--text);
    color-scheme: light;
}
.mvm-hub4 a:not(.mvm-hub4__button):not(.mvm-hub4__nav-item):not(.mvm-hub4__platform-button):not(.mvm-news-radar__review-button) {
    color: var(--mvm-hub4-link);
}
.mvm-hub4 input::placeholder,
.mvm-hub4 textarea::placeholder {
    color: #607284;
    opacity: 1;
}
.mvm-hub4 button:disabled,
.mvm-hub4 input:disabled,
.mvm-hub4 select:disabled,
.mvm-hub4 textarea:disabled {
    opacity: .72;
}

/* Platform v1: verwijder vaste lichtmoduskleuren zodat dark mode niet kan lekken. */
.mvm-hub4 .mvm-hub4__platform-toolbar p,
.mvm-hub4 .mvm-hub4__platform-card p,
.mvm-hub4 .mvm-hub4__platform-row small,
.mvm-hub4 .mvm-hub4__platform-row p,
.mvm-hub4 .mvm-hub4__platform-empty,
.mvm-hub4 .mvm-hub4__platform-message { color: var(--muted) !important; }
.mvm-hub4 .mvm-hub4__platform-card,
.mvm-hub4 .mvm-hub4__platform-row,
.mvm-hub4 .mvm-hub4__platform-table { background: var(--surface) !important; color: var(--text) !important; border-color: var(--border) !important; }
.mvm-hub4 .mvm-hub4__platform-form { background: var(--surface-alt) !important; border-color: var(--border) !important; color: var(--text) !important; }
.mvm-hub4 .mvm-hub4__platform-form label,
.mvm-hub4 .mvm-hub4__platform-card h3,
.mvm-hub4 .mvm-hub4__platform-row strong,
.mvm-hub4 .mvm-hub4__platform-table td { color: var(--text) !important; }
.mvm-hub4 .mvm-hub4__platform-form input,
.mvm-hub4 .mvm-hub4__platform-form select,
.mvm-hub4 .mvm-hub4__platform-form textarea { background: var(--surface) !important; color: var(--text) !important; border-color: #8299ad !important; }
.mvm-hub4 .mvm-hub4__platform-button--secondary { background: var(--surface) !important; color: var(--mvm-hub4-link) !important; }
.mvm-hub4 .mvm-hub4__platform-row,
.mvm-hub4 .mvm-hub4__platform-table th,
.mvm-hub4 .mvm-hub4__platform-table td { border-color: var(--border) !important; }

/* Nieuwsradar: semantische tekst erft nu echte Hub-tokens in plaats van fallback-grijs. */
.mvm-hub4 .mvm-news-radar__toolbar p,
.mvm-hub4 .mvm-news-radar__metric span,
.mvm-hub4 .mvm-news-radar__metric small,
.mvm-hub4 .mvm-news-radar__sources-head p,
.mvm-hub4 .mvm-news-radar__section-title p,
.mvm-hub4 .mvm-news-radar__sort span,
.mvm-hub4 .mvm-news-radar__source-main small,
.mvm-hub4 .mvm-news-radar__source-dates dt,
.mvm-hub4 .mvm-news-radar__card small,
.mvm-hub4 .mvm-news-radar__update small,
.mvm-hub4 .mvm-news-radar__loading,
.mvm-hub4 .mvm-news-radar__empty { color: var(--muted) !important; }
.mvm-hub4 .mvm-news-radar__metric,
.mvm-hub4 .mvm-news-radar__card,
.mvm-hub4 .mvm-news-radar__sources,
.mvm-hub4 .mvm-news-radar__sort select { background: var(--surface) !important; color: var(--text) !important; border-color: var(--border) !important; }
.mvm-hub4 .mvm-news-radar__source-card,
.mvm-hub4 .mvm-news-radar__update,
.mvm-hub4 .mvm-news-radar__badge { background: var(--surface-alt); color: var(--text); border-color: var(--border); }
.mvm-hub4 .mvm-news-radar__source-link,
.mvm-hub4 .mvm-news-radar__history summary { color: var(--mvm-hub4-link) !important; }

/* Publieke MvM-componenten: voorkom light-text-on-white in dark mode. */
@media (prefers-color-scheme: dark) {
    :root {
        --mvm-contrast-bg: #111923;
        --mvm-contrast-surface: #18222d;
        --mvm-contrast-surface-alt: #111923;
        --mvm-contrast-text: #f5f8fb;
        --mvm-contrast-muted: #c9d5e1;
        --mvm-contrast-border: #405467;
        --mvm-contrast-link: #6DB8F2;
    }
    body.mvm-hub4, .mvm-hub4 {
        --blue-soft: #173858;
        color-scheme: dark;
    }
    .mvm-hub4 input::placeholder,
    .mvm-hub4 textarea::placeholder { color: #aebdca; }
    .mvm-hub4 .mvm-news-radar__badge--priority-a { background:#153756 !important; color:#dceeff !important; border-color:#4b83ad !important; }
    .mvm-hub4 .mvm-news-radar__badge--priority-b { background:#4a3612 !important; color:#ffe2a8 !important; border-color:#8f6a20 !important; }
    .mvm-hub4 .mvm-news-radar__badge--priority-c,
    .mvm-hub4 .mvm-news-radar__badge--inactive { background:#2b3946 !important; color:#d7e1ea !important; border-color:#607284 !important; }
    .mvm-hub4 .mvm-news-radar__badge--active,
    .mvm-hub4 .mvm-news-radar__badge--reviewed { background:#183c28 !important; color:#c9f0d4 !important; border-color:#4e8a61 !important; }

    body.mvm-dark .mvm-public-v1,
    html[data-theme="dark"] .mvm-public-v1,
    body.mvm-dark .mvm-share-v1,
    html[data-theme="dark"] .mvm-share-v1 {
        --mvm-bg:#111923;
        --mvm-text:#f5f8fb;
        --mvm-muted:#c9d5e1;
        --mvm-border:#405467;
        --mvm-share-bg:#18222d;
        --mvm-share-text:#f5f8fb;
        --mvm-share-muted:#c9d5e1;
        --mvm-share-border:#405467;
    }
    body.mvm-dark .mvm-public-v1__box,
    body.mvm-dark .mvm-public-v1__card,
    body.mvm-dark .mvm-public-v1__notice,
    body.mvm-dark .mvm-public-v1__correction,
    body.mvm-dark .mvm-public-v1__topic,
    html[data-theme="dark"] .mvm-public-v1__box,
    html[data-theme="dark"] .mvm-public-v1__card,
    html[data-theme="dark"] .mvm-public-v1__notice,
    html[data-theme="dark"] .mvm-public-v1__correction,
    html[data-theme="dark"] .mvm-public-v1__topic { background:#18222d !important; color:#f5f8fb !important; border-color:#405467 !important; }
    body.mvm-dark .mvm-public-v1__form input,
    body.mvm-dark .mvm-public-v1__form select,
    body.mvm-dark .mvm-public-v1__form textarea,
    html[data-theme="dark"] .mvm-public-v1__form input,
    html[data-theme="dark"] .mvm-public-v1__form select,
    html[data-theme="dark"] .mvm-public-v1__form textarea { background:#0f1821 !important; color:#f5f8fb !important; border-color:#52697d !important; }
}

/* Expliciete Hub-themeswitch wint van het OS-thema. */
body.mvm-hub4[data-theme="dark"],
html[data-theme="dark"] body.mvm-hub4 {
    --surface:#18222d;
    --surface-alt:#111923;
    --border:#405467;
    --text:#f5f8fb;
    --muted:#c9d5e1;
    --blue-soft:#173858;
    --mvm-hub4-link:#6DB8F2;
    background:#111923 !important;
    color:#f5f8fb !important;
    color-scheme:dark;
}
body.mvm-hub4[data-theme="light"],
html[data-theme="light"] body.mvm-hub4 {
    --surface:#fff;
    --surface-alt:#f6f9fc;
    --border:#c5d3df;
    --text:#17222d;
    --muted:#46596b;
    --blue-soft:#eaf3fb;
    --mvm-hub4-link:#1966AE;
    background:#eef3f8 !important;
    color:#17222d !important;
    color-scheme:light;
}
</style>
MVM_CONTRAST_CSS;
}

add_action( 'wp_head', static function () {
    echo mvm_global_contrast_css_v1(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}, 9999 );

add_action( 'admin_head', static function () {
    echo mvm_global_contrast_css_v1(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    echo <<<'MVM_ADMIN_CONTRAST'
<style id="mvm-global-admin-contrast-v1">
body.mvm-hub4-admin-skin {
    --mvm-text:#17222d;
    --mvm-muted:#46596b;
    --mvm-surface:#fff;
    --mvm-surface-alt:#f5f8fb;
    --mvm-border:#c5d3df;
    color:#17222d;
}
body.mvm-hub4-admin-skin:not(.mvm-hub4-admin-skin--chrome-only) #wpbody-content,
body.mvm-hub4-admin-skin:not(.mvm-hub4-admin-skin--chrome-only) #wpbody-content .wrap,
body.mvm-hub4-admin-skin:not(.mvm-hub4-admin-skin--chrome-only) #wpbody-content .postbox,
body.mvm-hub4-admin-skin:not(.mvm-hub4-admin-skin--chrome-only) #wpbody-content .card,
body.mvm-hub4-admin-skin:not(.mvm-hub4-admin-skin--chrome-only) #wpbody-content .notice,
body.mvm-hub4-admin-skin:not(.mvm-hub4-admin-skin--chrome-only) #wpbody-content .wp-list-table {
    color:var(--mvm-text) !important;
}
body.mvm-hub4-admin-skin:not(.mvm-hub4-admin-skin--chrome-only) #wpbody-content .postbox,
body.mvm-hub4-admin-skin:not(.mvm-hub4-admin-skin--chrome-only) #wpbody-content .card,
body.mvm-hub4-admin-skin:not(.mvm-hub4-admin-skin--chrome-only) #wpbody-content .notice,
body.mvm-hub4-admin-skin:not(.mvm-hub4-admin-skin--chrome-only) #wpbody-content .wp-list-table {
    background:var(--mvm-surface) !important;
    border-color:var(--mvm-border) !important;
}
body.mvm-hub4-admin-skin:not(.mvm-hub4-admin-skin--chrome-only) #wpbody-content p,
body.mvm-hub4-admin-skin:not(.mvm-hub4-admin-skin--chrome-only) #wpbody-content .description,
body.mvm-hub4-admin-skin:not(.mvm-hub4-admin-skin--chrome-only) #wpbody-content .howto,
body.mvm-hub4-admin-skin:not(.mvm-hub4-admin-skin--chrome-only) #wpbody-content .tablenav .displaying-num {
    color:var(--mvm-muted) !important;
}
body.mvm-hub4-admin-skin:not(.mvm-hub4-admin-skin--chrome-only) #wpbody-content h1,
body.mvm-hub4-admin-skin:not(.mvm-hub4-admin-skin--chrome-only) #wpbody-content h2,
body.mvm-hub4-admin-skin:not(.mvm-hub4-admin-skin--chrome-only) #wpbody-content h3,
body.mvm-hub4-admin-skin:not(.mvm-hub4-admin-skin--chrome-only) #wpbody-content label,
body.mvm-hub4-admin-skin:not(.mvm-hub4-admin-skin--chrome-only) #wpbody-content strong,
body.mvm-hub4-admin-skin:not(.mvm-hub4-admin-skin--chrome-only) #wpbody-content td {
    color:var(--mvm-text);
}
body.mvm-hub4-admin-skin:not(.mvm-hub4-admin-skin--chrome-only) #wpbody-content input::placeholder,
body.mvm-hub4-admin-skin:not(.mvm-hub4-admin-skin--chrome-only) #wpbody-content textarea::placeholder {
    color:#607284;
    opacity:1;
}
</style>
MVM_ADMIN_CONTRAST;
}, 9999 );
