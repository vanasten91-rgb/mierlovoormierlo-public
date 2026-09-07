'use strict';

const { chromium } = require('playwright');

const baseUrl = (process.argv[2] || 'http://127.0.0.1:8080').replace(/\/$/, '');

function assert(condition, message) {
    if (!condition) {
        throw new Error(message);
    }
}

async function noHorizontalOverflow(page, label) {
    const dimensions = await page.evaluate(() => ({
        viewport: window.innerWidth,
        document: document.documentElement.scrollWidth,
        body: document.body.scrollWidth,
    }));
    assert(dimensions.document <= dimensions.viewport + 2, `${label}: document horizontal overflow ${dimensions.document}px > ${dimensions.viewport}px`);
    assert(dimensions.body <= dimensions.viewport + 2, `${label}: body horizontal overflow ${dimensions.body}px > ${dimensions.viewport}px`);
}

(async () => {
    const browser = await chromium.launch({ headless: true });
    try {
        const page = await browser.newPage({ viewport: { width: 1440, height: 1000 } });
        const browserErrors = [];
        page.on('pageerror', (error) => browserErrors.push(error.message));
        page.on('console', (message) => {
            if (message.type() === 'error') {
                browserErrors.push(`console: ${message.text()}`);
            }
        });

        await page.goto(`${baseUrl}/encyclopedie/`, { waitUntil: 'networkidle' });
        const encyclopediaRoot = page.locator('[data-mvm-encyclopedie-root]');
        assert(await encyclopediaRoot.count() === 1, 'Desktop homepage root missing.');
        assert(await encyclopediaRoot.locator('h1').count() === 1, 'Canonical encyclopedia runtime must render exactly one H1 inside its own root.');
        assert(await page.locator('.mvm-e3-collection-card').count() === 9, 'Desktop homepage must show nine collection cards.');
        await noHorizontalOverflow(page, 'desktop home');

        const styleState = await page.evaluate(() => {
            const root = document.querySelector('.mvm-encyclopedie-next');
            return {
                hrefs: Array.from(document.styleSheets).map((sheet) => sheet.href || '').filter(Boolean),
                lightSurface: root ? getComputedStyle(root).getPropertyValue('--mvm-e3-surface').trim() : '',
                rootColor: root ? getComputedStyle(root).color : '',
            };
        });
        console.log(`Stylesheets: ${styleState.hrefs.join(' | ')}`);
        console.log(`Light surface token: ${styleState.lightSurface || '(empty)'}`);
        assert(styleState.hrefs.some((href) => href.includes('/mvm-encyclopedie-next/assets/frontend.css')), 'WordPress did not load the Encyclopedie Next stylesheet on the homepage.');
        assert(styleState.lightSurface !== '', 'Encyclopedie Next stylesheet loaded but its surface token was not applied to the root.');

        await page.evaluate(() => document.body.classList.add('dark-mode'));
        const darkSurface = await page.locator('.mvm-encyclopedie-next').evaluate((element) => getComputedStyle(element).getPropertyValue('--mvm-e3-surface').trim());
        assert(styleState.lightSurface !== darkSurface, `Dark mode did not change surface token (${styleState.lightSurface}).`);
        assert(darkSurface.toLowerCase() === '#13283a', `Unexpected dark surface token: ${darkSurface}`);
        await page.evaluate(() => document.body.classList.remove('dark-mode'));

        const searchInput = page.locator('[data-mvm-search-input]');
        const liveResults = page.locator('[data-mvm-live-results]');
        assert(await searchInput.getAttribute('role') === 'combobox', 'Live-search input must expose combobox semantics.');
        assert(await searchInput.getAttribute('aria-autocomplete') === 'list', 'Live-search combobox must declare list autocomplete.');
        const controlledResultsId = await searchInput.getAttribute('aria-controls');
        assert(controlledResultsId && controlledResultsId === await liveResults.getAttribute('id'), 'Live-search combobox must control the results listbox.');
        assert(await liveResults.getAttribute('role') === 'listbox', 'Live-search results must expose listbox semantics.');
        assert(await searchInput.getAttribute('aria-expanded') === 'false', 'Live-search combobox should begin collapsed.');

        await searchInput.fill('Arkweg');
        const liveItem = liveResults.locator('.mvm-e3-live-item').filter({ hasText: 'Arkweg' }).first();
        await liveItem.waitFor({ state: 'visible', timeout: 5000 });
        const liveHref = await liveItem.getAttribute('href');
        assert(liveHref && liveHref.endsWith('/encyclopedie/locaties/arkweg/'), `Live search returned wrong Arkweg URL: ${liveHref}`);
        assert(await searchInput.getAttribute('aria-expanded') === 'true', 'Live-search combobox did not expand after suggestions loaded.');
        assert(await liveItem.getAttribute('role') === 'option', 'Live-search suggestion must expose option semantics.');

        await searchInput.press('ArrowDown');
        const activeDescendant = await searchInput.getAttribute('aria-activedescendant');
        assert(activeDescendant && activeDescendant === await liveItem.getAttribute('id'), 'ArrowDown did not activate the first live-search option.');
        assert(await liveItem.getAttribute('aria-selected') === 'true', 'Active live-search option must expose aria-selected=true.');
        await searchInput.press('Escape');
        assert(await searchInput.getAttribute('aria-expanded') === 'false', 'Escape did not collapse live-search suggestions.');
        assert(await liveResults.isHidden(), 'Escape did not hide the live-search listbox.');

        await page.goto(`${baseUrl}/encyclopedie/themas/onderwijs-jeugd/`, { waitUntil: 'networkidle' });
        const themeGroupRoot = page.locator('[data-mvm-theme-group="onderwijs-jeugd"]');
        assert(await themeGroupRoot.count() === 1, 'Editorial theme group root missing.');
        assert(await themeGroupRoot.locator('h1').count() === 1, 'Canonical editorial theme runtime must render exactly one H1 inside its own root.');
        assert(await themeGroupRoot.locator('h1').filter({ hasText: 'Onderwijs & jeugd' }).count() === 1, 'Editorial theme page H1 changed unexpectedly.');
        await noHorizontalOverflow(page, 'desktop editorial theme');

        await page.goto(`${baseUrl}/encyclopedie/artikel/moderne-basisscholen/`, { waitUntil: 'networkidle' });
        assert(await page.locator('.mvm-e3-hotlink[href$="/encyclopedie/locaties/arkweg/"]').count() === 1, 'Rendered dossier is missing the Arkweg hotlink.');
        const initialRelations = await page.locator('[data-mvm-relations-grid] .mvm-e3-card').count();
        assert(initialRelations === 12, `Dossier must initially render 12 relations, got ${initialRelations}.`);
        const moreButton = page.locator('[data-mvm-relations-more]');
        assert(await moreButton.count() === 1, 'More-relations button missing for a dossier with more than 12 relations.');
        const relationGridId = await page.locator('[data-mvm-relations-grid]').getAttribute('id');
        assert(relationGridId && await moreButton.getAttribute('aria-controls') === relationGridId, 'Relation loader must expose aria-controls for its grid.');
        await moreButton.click();
        await page.waitForFunction(() => document.querySelectorAll('[data-mvm-relations-grid] .mvm-e3-card').length > 12, null, { timeout: 5000 });
        const expandedRelations = await page.locator('[data-mvm-relations-grid] .mvm-e3-card').count();
        assert(expandedRelations === 15, `Lazy relation expansion expected 15 visible relations, got ${expandedRelations}.`);
        assert(await moreButton.count() === 0, 'More-relations button should disappear after all relations are loaded.');
        await noHorizontalOverflow(page, 'desktop dossier');

        await page.setViewportSize({ width: 390, height: 844 });
        await page.goto(`${baseUrl}/encyclopedie/`, { waitUntil: 'networkidle' });
        await noHorizontalOverflow(page, 'mobile home');
        const mobileHero = await page.locator('.mvm-e3-hero').boundingBox();
        assert(mobileHero && mobileHero.width <= 390, `Mobile hero exceeds viewport: ${mobileHero && mobileHero.width}px.`);
        assert(await page.locator('.mvm-e3-collection-card').count() === 9, 'Mobile homepage lost collection cards.');

        await page.goto(`${baseUrl}/encyclopedie/locaties/arkweg/`, { waitUntil: 'networkidle' });
        await noHorizontalOverflow(page, 'mobile Arkweg');
        assert(await page.locator('h1').filter({ hasText: 'Arkweg' }).count() >= 1, 'Arkweg heading missing on mobile.');

        // Use a separate browser page for the deliberately hidden URL. Chromium
        // emits a console resource error for a main-document 404; that is the
        // expected security result here and must not pollute the ordinary UI
        // console-error gate above.
        const hiddenPage = await browser.newPage({ viewport: { width: 390, height: 844 } });
        const hiddenResponse = await hiddenPage.goto(`${baseUrl}/encyclopedie/locaties/verborgen-locatie/`, { waitUntil: 'domcontentloaded' });
        assert(hiddenResponse && hiddenResponse.status() === 404, `Hidden dossier browser request must be 404, got ${hiddenResponse && hiddenResponse.status()}.`);
        await hiddenPage.close();

        assert(browserErrors.length === 0, `Browser errors detected: ${browserErrors.join(' | ')}`);
        console.log('Encyclopedie Next browser smoke: OK');
    } finally {
        await browser.close();
    }
})().catch((error) => {
    console.error(error.stack || error.message || error);
    process.exit(1);
});
