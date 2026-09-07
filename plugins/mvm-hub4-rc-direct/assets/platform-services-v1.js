(() => {
    'use strict';

    const config = window.MvMHub4Config || {};
    const serviceRoot = config.platformRestRoot || '';
    const container = document.querySelector('[data-mvm-service-container]');
    const serviceNav = Array.from(document.querySelectorAll('[data-mvm-service-view]'));
    const classicNav = Array.from(document.querySelectorAll('[data-mvm-view]'));
    const platformNav = Array.from(document.querySelectorAll('[data-mvm-platform-view]'));
    const radarNav = document.querySelector('[data-mvm-news-radar-view]');
    const classicView = document.querySelector('[data-mvm-view-container]');
    const platformView = document.querySelector('[data-mvm-platform-container]');
    const workflow = document.querySelector('[data-mvm-workflow-guide]');
    const myWork = document.querySelector('[data-mvm-my-work]');
    const titleNode = document.querySelector('[data-mvm-view-title]');
    const messageNode = document.querySelector('[data-mvm-platform-message]');

    if (!container || !serviceNav.length || !serviceRoot || !config.restNonce) return;

    const labels = {
        marketplace: 'Marktplaats',
        local_ads: 'Ondernemersadvertenties',
        newsletter: 'Nieuwsbrief',
        pwa: 'PWA'
    };

    let activeService = '';

    const element = (tag, attrs = {}, children = []) => {
        const node = document.createElement(tag);
        Object.entries(attrs).forEach(([key, value]) => {
            if (value === null || value === undefined || value === false) return;
            if (key === 'class') node.className = String(value);
            else if (key === 'text') node.textContent = String(value);
            else if (key === 'checked') node.checked = Boolean(value);
            else if (key === 'disabled') node.disabled = Boolean(value);
            else node.setAttribute(key, String(value));
        });
        (Array.isArray(children) ? children : [children]).forEach((child) => {
            if (child === null || child === undefined) return;
            node.appendChild(child instanceof Node ? child : document.createTextNode(String(child)));
        });
        return node;
    };

    const clear = (node) => {
        while (node.firstChild) node.removeChild(node.firstChild);
    };

    const setMessage = (text = '', type = '') => {
        if (!messageNode) return;
        messageNode.textContent = text;
        messageNode.className = 'mvm-hub4__platform-message';
        if (type) messageNode.classList.add(`is-${type}`);
    };

    const api = async (path, options = {}) => {
        const url = new URL(String(path).replace(/^\//, ''), serviceRoot);
        const headers = new Headers(options.headers || {});
        headers.set('X-WP-Nonce', config.restNonce);
        headers.set('Accept', 'application/json');
        if (options.body && !headers.has('Content-Type')) headers.set('Content-Type', 'application/json; charset=UTF-8');
        const response = await fetch(url.toString(), {
            ...options,
            credentials: 'same-origin',
            cache: 'no-store',
            headers
        });
        let data = {};
        try { data = await response.json(); } catch (error) { throw new Error('De platformdienst gaf geen geldig antwoord.'); }
        if (!response.ok) throw new Error(data.message || 'De actie kon niet worden uitgevoerd.');
        return data;
    };

    const post = (path, payload = {}) => api(path, { method: 'POST', body: JSON.stringify(payload) });

    const toolbar = (title, text) => {
        const wrap = element('div', { class: 'mvm-hub4__platform-toolbar' });
        const copy = element('div');
        copy.appendChild(element('h2', { text: title }));
        if (text) copy.appendChild(element('p', { text }));
        wrap.appendChild(copy);
        return wrap;
    };

    const badge = (text, urgent = false) => element('span', {
        class: `mvm-hub4__platform-badge${urgent ? ' mvm-hub4__platform-badge--urgent' : ''}`,
        text
    });

    const metric = (label, value, note = '') => {
        const card = element('article', { class: 'mvm-hub4__platform-card' });
        card.appendChild(element('h3', { text: label }));
        card.appendChild(element('div', { class: 'mvm-hub4__platform-metric', text: String(value ?? 0) }));
        if (note) card.appendChild(element('p', { text: note }));
        return card;
    };

    const actionButton = (label, handler, secondary = false) => {
        const button = element('button', {
            type: 'button',
            class: secondary ? 'mvm-hub4__button mvm-hub4__button--secondary' : 'mvm-hub4__button',
            text: label
        });
        button.addEventListener('click', handler);
        return button;
    };

    const renderLoading = (label = 'Laden…') => {
        clear(container);
        container.setAttribute('aria-busy', 'true');
        container.appendChild(element('div', { class: 'mvm-hub4__platform-empty', text: label }));
    };

    const finish = () => container.setAttribute('aria-busy', 'false');

    const renderMarketplace = async () => {
        const items = await api('marketplace/moderation');
        clear(container);
        container.appendChild(toolbar('Marktplaatsmoderatie', 'Behandel gemelde of geblokkeerde particuliere advertenties. Ondernemersadvertenties staan in een aparte dienst.'));
        const grid = element('div', { class: 'mvm-hub4__platform-grid' });
        grid.appendChild(metric('In wachtrij', items.length));
        grid.appendChild(metric('Gemeld', items.filter((item) => Number(item.report_count || 0) > 0).length));
        grid.appendChild(metric('Geblokkeerd', items.filter((item) => item.status === 'moderated').length));
        container.appendChild(grid);

        if (!items.length) {
            container.appendChild(element('div', { class: 'mvm-hub4__platform-empty', text: 'Geen particuliere advertenties vragen nu om moderatie.' }));
            finish();
            return;
        }

        const list = element('div', { class: 'mvm-hub4__platform-list' });
        items.forEach((item) => {
            const row = element('article', { class: 'mvm-hub4__platform-row' });
            const copy = element('div');
            copy.appendChild(element('strong', { text: item.title || `Advertentie #${item.id}` }));
            const seller = item.seller && item.seller.display_name ? item.seller.display_name : 'MvM-inwoner';
            copy.appendChild(element('small', { text: `${seller} · ${item.status || 'onbekend'} · ${Number(item.report_count || 0)} melding(en)` }));
            if (item.location) copy.appendChild(element('p', { text: item.location }));
            row.appendChild(copy);
            const actions = element('div', { class: 'mvm-hub4__platform-badges' });
            actions.appendChild(badge(item.status || 'onbekend', item.status === 'moderated'));
            if (item.status === 'moderated') {
                actions.appendChild(actionButton('Herstellen', async () => {
                    const reason = window.prompt('Reden voor herstellen (minimaal 5 tekens):');
                    if (!reason) return;
                    try {
                        await post(`marketplace/${item.id}/moderate`, { moderation_action: 'restore', reason });
                        setMessage('Advertentie hersteld.', 'success');
                        await renderMarketplace();
                    } catch (error) { setMessage(error.message, 'error'); }
                }, true));
            } else {
                actions.appendChild(actionButton('Blokkeren', async () => {
                    const reason = window.prompt('Reden voor blokkeren (minimaal 5 tekens):');
                    if (!reason) return;
                    try {
                        await post(`marketplace/${item.id}/moderate`, { moderation_action: 'block', reason });
                        setMessage('Advertentie geblokkeerd.', 'success');
                        await renderMarketplace();
                    } catch (error) { setMessage(error.message, 'error'); }
                }));
            }
            row.appendChild(actions);
            list.appendChild(row);
        });
        container.appendChild(list);
        finish();
    };

    const renderLocalAds = async () => {
        const data = await api('local-ads');
        const items = Array.isArray(data.items) ? data.items : [];
        clear(container);
        container.appendChild(toolbar('Ondernemersadvertenties', 'Stafcontrole staat los van het ondernemersportaal. Ondernemers beheren inhoud en planning zelf; hier kan staf alleen toezicht houden, schorsen en herstellen.'));

        const grid = element('div', { class: 'mvm-hub4__platform-grid' });
        grid.appendChild(metric('Totaal', items.length));
        grid.appendChild(metric('Actief', items.filter((item) => item.status === 'active').length));
        grid.appendChild(metric('Gepauzeerd', items.filter((item) => item.status === 'paused').length));
        grid.appendChild(metric('Geschorst', items.filter((item) => item.status === 'suspended').length));
        container.appendChild(grid);

        const publicLink = element('a', {
            class: 'mvm-hub4__button mvm-hub4__button--secondary',
            href: `${window.location.origin}/aanbiedingen/`,
            target: '_blank',
            rel: 'noopener noreferrer',
            text: 'Bekijk Aanbiedingen'
        });
        container.appendChild(element('div', { class: 'mvm-hub4__platform-actions' }, publicLink));

        if (!items.length) {
            container.appendChild(element('div', { class: 'mvm-hub4__platform-empty', text: 'Nog geen ondernemersadvertenties.' }));
            finish();
            return;
        }

        const list = element('div', { class: 'mvm-hub4__platform-list' });
        items.forEach((item) => {
            const row = element('article', { class: 'mvm-hub4__platform-row' });
            const copy = element('div');
            copy.appendChild(element('strong', { text: item.headline || `Advertentie #${item.id}` }));
            copy.appendChild(element('small', { text: `${item.advertiser || 'Ondernemer'} · ${item.status || 'draft'} · eigenaar #${Number(item.owner_id || 0)}` }));
            if (item.text) copy.appendChild(element('p', { text: item.text }));
            row.appendChild(copy);

            const actions = element('div', { class: 'mvm-hub4__platform-badges' });
            actions.appendChild(badge(item.status || 'draft', item.status === 'suspended'));
            if (item.status === 'suspended') {
                actions.appendChild(actionButton('Schorsing opheffen', async () => {
                    const reason = window.prompt('Reden voor herstel (minimaal 5 tekens):');
                    if (!reason || reason.trim().length < 5) return;
                    try {
                        await post(`local-ads/${item.id}/moderate`, { moderation_action: 'restore', reason: reason.trim() });
                        setMessage('Schorsing opgeheven. De advertentie staat gepauzeerd; de ondernemer kan hem zelf weer activeren.', 'success');
                        await renderLocalAds();
                    } catch (error) { setMessage(error.message, 'error'); }
                }, true));
            } else {
                actions.appendChild(actionButton('Schorsen', async () => {
                    const reason = window.prompt('Reden voor schorsing (minimaal 5 tekens):');
                    if (!reason || reason.trim().length < 5) return;
                    try {
                        await post(`local-ads/${item.id}/moderate`, { moderation_action: 'suspend', reason: reason.trim() });
                        setMessage('Advertentie geschorst. De reden is in het auditlog vastgelegd.', 'success');
                        await renderLocalAds();
                    } catch (error) { setMessage(error.message, 'error'); }
                }));
            }
            row.appendChild(actions);
            list.appendChild(row);
        });
        container.appendChild(list);
        finish();
    };

    const topicOptions = [
        ['news', 'Laatste nieuws'],
        ['events', 'Evenementen'],
        ['sport', 'Sport'],
        ['associations', 'Verenigingen'],
        ['politics', 'Politiek'],
        ['culture', 'Cultuur'],
        ['marketplace', 'Marktplaats']
    ];

    const campaignForm = () => {
        const form = element('form', { class: 'mvm-hub4__platform-form' });
        const subject = element('label', { class: 'is-wide' }, [document.createTextNode('Onderwerp'), element('input', { name: 'subject', maxlength: '180', required: 'required' })]);
        const preheader = element('label', { class: 'is-wide' }, [document.createTextNode('Voorvertoning'), element('input', { name: 'preheader', maxlength: '220' })]);
        const body = element('label', { class: 'is-wide' }, [document.createTextNode('Inhoud'), element('textarea', { name: 'body', maxlength: '200000', required: 'required' })]);
        const topics = element('fieldset', { class: 'is-wide' });
        topics.appendChild(element('legend', { text: 'Doelgroep' }));
        const topicGrid = element('div', { class: 'mvm-hub4__platform-actions' });
        topicOptions.forEach(([key, label]) => {
            const wrap = element('label');
            const input = element('input', { type: 'checkbox', value: key, 'data-newsletter-topic': key });
            if (key === 'news') input.checked = true;
            wrap.appendChild(input);
            wrap.appendChild(document.createTextNode(` ${label}`));
            topicGrid.appendChild(wrap);
        });
        topics.appendChild(topicGrid);
        form.appendChild(subject);
        form.appendChild(preheader);
        form.appendChild(body);
        form.appendChild(topics);
        const actions = element('div', { class: 'mvm-hub4__platform-actions' });
        actions.appendChild(element('button', { type: 'submit', class: 'mvm-hub4__platform-button', text: 'Concept maken' }));
        form.appendChild(actions);
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            const selected = Array.from(form.querySelectorAll('[data-newsletter-topic]:checked')).map((input) => input.value);
            const data = new FormData(form);
            try {
                await post('newsletter/campaigns', {
                    subject: String(data.get('subject') || ''),
                    preheader: String(data.get('preheader') || ''),
                    body: String(data.get('body') || ''),
                    topics: selected
                });
                setMessage('Nieuwsbriefconcept opgeslagen.', 'success');
                await renderNewsletter();
            } catch (error) { setMessage(error.message, 'error'); }
        });
        return form;
    };

    const renderNewsletter = async () => {
        const items = await api('newsletter/campaigns');
        clear(container);
        container.appendChild(toolbar('Nieuwsbrief', 'Maak campagnes, stuur een testmail en plan verzending gecontroleerd in. De queue verstuurt in kleine idempotente batches.'));
        container.appendChild(campaignForm());

        const grid = element('div', { class: 'mvm-hub4__platform-grid' });
        grid.appendChild(metric('Campagnes', items.length));
        grid.appendChild(metric('Ingepland', items.filter((item) => item.status === 'scheduled').length));
        grid.appendChild(metric('Verzonden', items.reduce((sum, item) => sum + Number(item.total_sent || 0), 0)));
        grid.appendChild(metric('Definitief mislukt', items.reduce((sum, item) => sum + Number(item.total_failed || 0), 0)));
        container.appendChild(grid);

        if (!items.length) {
            container.appendChild(element('div', { class: 'mvm-hub4__platform-empty', text: 'Nog geen nieuwsbriefcampagnes.' }));
            finish();
            return;
        }

        const list = element('div', { class: 'mvm-hub4__platform-list' });
        items.forEach((item) => {
            const row = element('article', { class: 'mvm-hub4__platform-row' });
            const copy = element('div');
            copy.appendChild(element('strong', { text: item.subject || `Campagne #${item.id}` }));
            copy.appendChild(element('small', { text: `${item.status} · ${Number(item.total_sent || 0)}/${Number(item.total_queued || 0)} verzonden${Number(item.total_failed || 0) ? ` · ${item.total_failed} mislukt` : ''}` }));
            if (item.preheader) copy.appendChild(element('p', { text: item.preheader }));
            row.appendChild(copy);

            const actions = element('div', { class: 'mvm-hub4__platform-badges' });
            actions.appendChild(badge(item.status || 'draft', item.status === 'sending'));
            if (['draft', 'scheduled'].includes(item.status)) {
                actions.appendChild(actionButton('Testmail', async () => {
                    try {
                        const result = await post(`newsletter/campaigns/${item.id}/test`);
                        setMessage(result.message || 'Testmail verzonden.', 'success');
                    } catch (error) { setMessage(error.message, 'error'); }
                }, true));
                const schedule = element('input', { type: 'datetime-local', 'aria-label': `Verzendmoment ${item.subject || item.id}` });
                actions.appendChild(schedule);
                actions.appendChild(actionButton('Inplannen', async () => {
                    const date = schedule.value ? new Date(schedule.value) : new Date();
                    if (Number.isNaN(date.getTime())) {
                        setMessage('Kies een geldig verzendmoment.', 'error');
                        return;
                    }
                    try {
                        await post(`newsletter/campaigns/${item.id}/schedule`, { scheduled_at: date.toISOString() });
                        setMessage('Campagne ingepland.', 'success');
                        await renderNewsletter();
                    } catch (error) { setMessage(error.message, 'error'); }
                }));
                actions.appendChild(actionButton('Annuleren', async () => {
                    try {
                        await post(`newsletter/campaigns/${item.id}/cancel`);
                        setMessage('Campagne geannuleerd.', 'success');
                        await renderNewsletter();
                    } catch (error) { setMessage(error.message, 'error'); }
                }, true));
            }
            row.appendChild(actions);
            list.appendChild(row);
        });
        container.appendChild(list);
        finish();
    };

    const renderPwa = async () => {
        const status = await api('platform');
        clear(container);
        container.appendChild(toolbar('PWA & mobiele API', 'Read-only beheerstatus van de installeerbare MvM-webapp en de gedeelde mobiele REST-laag.'));
        const grid = element('div', { class: 'mvm-hub4__platform-grid' });
        grid.appendChild(metric('Platformversie', status.version || '—'));
        grid.appendChild(metric('REST namespace', status.api || 'mvm/v1'));
        grid.appendChild(metric('Marktplaats', status.marketplace ? 'actief' : 'niet geladen'));
        grid.appendChild(metric('Nieuwsbrief', status.newsletter ? 'actief' : 'niet geladen'));
        container.appendChild(grid);

        const card = element('article', { class: 'mvm-hub4__platform-card' });
        card.appendChild(element('h3', { text: 'Privacy- en cachegrenzen' }));
        card.appendChild(element('p', { text: 'De service worker cachet geen Hub-, wp-admin-, account- of REST-responses. Dynamische navigatie blijft netwerkgestuurd; alleen veilige statische MvM-assets en de offlinepagina worden gecachet.' }));
        const links = element('div', { class: 'mvm-hub4__platform-actions' });
        const origin = window.location.origin;
        [['Manifest', '/mvm.webmanifest'], ['Service worker', '/mvm-sw.js'], ['Offlinepagina', '/mvm-offline/']].forEach(([label, path]) => {
            const link = element('a', { class: 'mvm-hub4__button mvm-hub4__button--secondary', href: `${origin}${path}`, target: '_blank', rel: 'noopener noreferrer', text: label });
            links.appendChild(link);
        });
        card.appendChild(links);
        container.appendChild(card);
        finish();
    };

    const renderService = async (view) => {
        renderLoading(`${labels[view] || 'Platformdienst'} laden…`);
        setMessage('');
        try {
            if (view === 'marketplace') await renderMarketplace();
            else if (view === 'local_ads') await renderLocalAds();
            else if (view === 'newsletter') await renderNewsletter();
            else await renderPwa();
        } catch (error) {
            clear(container);
            container.appendChild(element('div', { class: 'mvm-hub4__platform-empty', text: error.message }));
            setMessage(error.message, 'error');
            finish();
        }
    };

    const deactivateOtherNavigation = () => {
        classicNav.forEach((button) => { button.classList.remove('is-active'); button.setAttribute('aria-pressed', 'false'); });
        platformNav.forEach((button) => { button.classList.remove('is-active'); button.setAttribute('aria-pressed', 'false'); });
        if (radarNav) { radarNav.classList.remove('is-active'); radarNav.setAttribute('aria-pressed', 'false'); }
    };

    const showService = async (view, updateHistory = true) => {
        if (!labels[view] || !serviceNav.some((button) => button.dataset.mvmServiceView === view)) return;
        activeService = view;
        if (classicView) classicView.hidden = true;
        if (platformView) platformView.hidden = true;
        if (workflow) workflow.hidden = true;
        if (myWork) myWork.hidden = true;
        container.hidden = false;
        if (titleNode) titleNode.textContent = labels[view];
        deactivateOtherNavigation();
        serviceNav.forEach((button) => {
            const active = button.dataset.mvmServiceView === view;
            button.classList.toggle('is-active', active);
            button.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
        if (updateHistory) {
            const url = new URL(window.location.href);
            url.searchParams.delete('view');
            url.searchParams.delete('platform');
            url.searchParams.set('service', view);
            window.history.pushState({ service: view }, '', url.toString());
        }
        await renderService(view);
    };

    const hideService = () => {
        activeService = '';
        container.hidden = true;
        serviceNav.forEach((button) => { button.classList.remove('is-active'); button.setAttribute('aria-pressed', 'false'); });
    };

    serviceNav.forEach((button) => button.addEventListener('click', () => showService(button.dataset.mvmServiceView || '')));
    [...classicNav, ...platformNav].forEach((button) => button.addEventListener('click', hideService));
    if (radarNav) radarNav.addEventListener('click', hideService);

    window.addEventListener('popstate', () => {
        const view = new URL(window.location.href).searchParams.get('service');
        if (view && labels[view]) showService(view, false);
        else if (activeService) hideService();
    });

    const initial = new URL(window.location.href).searchParams.get('service');
    if (initial && labels[initial]) showService(initial, false);
})();
