(() => {
    'use strict';

    const config = window.MvMHub4Config || {};
    const container = document.querySelector('[data-mvm-platform-container]');
    const platformNav = Array.from(document.querySelectorAll('[data-mvm-platform-view]'));
    const classicNav = Array.from(document.querySelectorAll('[data-mvm-view]'));
    const classicView = document.querySelector('[data-mvm-view-container]');
    const workflow = document.querySelector('[data-mvm-workflow-guide]');
    const myWork = document.querySelector('[data-mvm-my-work]');
    const titleNode = document.querySelector('[data-mvm-view-title]');
    const messageNode = document.querySelector('[data-mvm-platform-message]');

    if (!container || !platformNav.length || !config.restRoot || !config.restNonce) {
        return;
    }

    const labels = {
        dashboard: 'Dashboard',
        signals: 'Signalen',
        dossiers: 'Dossiers',
        calendar: 'Kalender',
        media: 'Media',
        distribution: 'Distributie',
        corrections: 'Correcties',
        radar: 'Mierlo-radar'
    };

    let platform = null;
    let activeView = '';

    const element = (tag, attrs = {}, children = []) => {
        const node = document.createElement(tag);
        Object.entries(attrs).forEach(([key, value]) => {
            if (value === null || value === undefined || value === false) return;
            if (key === 'class') node.className = String(value);
            else if (key === 'text') node.textContent = String(value);
            else if (key === 'checked') node.checked = Boolean(value);
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
        const url = new URL(String(path).replace(/^\//, ''), config.restRoot);
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
        try { data = await response.json(); } catch (error) { throw new Error('De Hub gaf geen geldig antwoord.'); }
        if (!response.ok) throw new Error(data.message || 'De actie kon niet worden uitgevoerd.');
        return data;
    };

    const jsonPost = (path, data) => api(path, { method: 'POST', body: JSON.stringify(data) });

    const formatDate = (value) => {
        if (!value) return '—';
        const raw = String(value).replace(' ', 'T');
        const date = new Date(raw.endsWith('Z') ? raw : `${raw}Z`);
        return Number.isNaN(date.getTime()) ? String(value) : new Intl.DateTimeFormat('nl-NL', { dateStyle: 'medium', timeStyle: 'short' }).format(date);
    };

    const toolbar = (title, text) => {
        const wrap = element('div', { class: 'mvm-hub4__platform-toolbar' });
        const copy = element('div');
        copy.appendChild(element('h2', { text: title }));
        if (text) copy.appendChild(element('p', { text }));
        wrap.appendChild(copy);
        return wrap;
    };

    const badge = (text, urgent = false) => element('span', { class: `mvm-hub4__platform-badge${urgent ? ' mvm-hub4__platform-badge--urgent' : ''}`, text });

    const field = (label, name, type = 'text', options = []) => {
        const wrap = element('label', { class: name === 'summary' || name === 'copyText' || name === 'message' ? 'is-wide' : '' });
        wrap.appendChild(document.createTextNode(label));
        let control;
        if (type === 'textarea') {
            control = element('textarea', { name, maxlength: '10000' });
        } else if (type === 'select') {
            control = element('select', { name });
            options.forEach(([value, text]) => control.appendChild(element('option', { value, text })));
        } else {
            control = element('input', { name, type, autocomplete: 'off' });
        }
        wrap.appendChild(control);
        return wrap;
    };

    const formData = (form) => Object.fromEntries(new FormData(form).entries());

    const submitButton = (label) => {
        const actions = element('div', { class: 'mvm-hub4__platform-actions' });
        actions.appendChild(element('button', { type: 'submit', class: 'mvm-hub4__platform-button', text: label }));
        return actions;
    };

    const renderLoading = () => {
        clear(container);
        container.appendChild(element('div', { class: 'mvm-hub4__platform-empty', text: 'Laden…' }));
    };

    const renderRows = (items, mapper) => {
        if (!items || !items.length) return element('div', { class: 'mvm-hub4__platform-empty', text: 'Nog geen items.' });
        const list = element('div', { class: 'mvm-hub4__platform-list' });
        items.forEach((item) => list.appendChild(mapper(item)));
        return list;
    };

    const metric = (label, value, note = '') => {
        const card = element('article', { class: 'mvm-hub4__platform-card' });
        card.appendChild(element('h3', { text: label }));
        card.appendChild(element('div', { class: 'mvm-hub4__platform-metric', text: String(value ?? 0) }));
        if (note) card.appendChild(element('p', { text: note }));
        return card;
    };

    const renderDashboard = async () => {
        const data = await api('dashboard');
        clear(container);
        container.appendChild(toolbar('Operationeel dashboard', 'Wat vraagt vandaag aandacht in de MvM-newsroom?'));
        const grid = element('div', { class: 'mvm-hub4__platform-grid' });
        const metrics = data.metrics || {};
        grid.appendChild(metric('Nieuwe signalen', data.signals?.new || 0, 'Nog niet getrieerd'));
        grid.appendChild(metric('Opdrachten te laat', metrics.overdueAssignments || 0));
        grid.appendChild(metric('Wacht op review', metrics.pendingNews || 0, 'Nieuws met status pending'));
        grid.appendChild(metric('Checklists incompleet', metrics.incompleteChecklists || 0));
        grid.appendChild(metric('Komende 7 dagen', metrics.upcomingCalendar || 0, 'Redactiekalender'));
        grid.appendChild(metric('Media review', metrics.mediaForReview || 0));
        grid.appendChild(metric('Correcties', metrics.correctionsWaiting || 0, 'Nog te beoordelen'));
        grid.appendChild(metric('Distributie review', metrics.distributionReview || 0));
        if (data.sourceRadar) grid.appendChild(metric('Bronnen te laat', data.sourceRadar.overdue || 0, 'Mierlo-radar'));
        container.appendChild(grid);
    };

    const signalForm = () => {
        if (!platform?.writes?.signalCreate) return null;
        const form = element('form', { class: 'mvm-hub4__platform-form' });
        form.appendChild(field('Type', 'kind', 'select', [['news','Nieuws'],['photo','Fotografie'],['event','Evenement'],['source','Bron'],['safety','112 / veiligheid'],['general','Algemeen']]));
        form.appendChild(field('Titel', 'title'));
        form.appendChild(field('Omschrijving', 'summary', 'textarea'));
        form.appendChild(field('Locatie', 'location'));
        form.appendChild(field('Incidenttijd (voor 112)', 'incidentAtUtc', 'datetime-local'));
        form.appendChild(field('Broncontrole', 'verificationStatus', 'select', [['unverified','Nog niet gecontroleerd'],['verified','Gecontroleerd'],['conflicting','Tegenstrijdig']]));
        form.appendChild(field('Incidentstatus', 'incidentStatus', 'select', [['ongoing','Lopend'],['resolved','Afgerond'],['unknown','Onbekend']]));
        form.appendChild(field('Bron-URL', 'sourceUrl', 'url'));
        form.appendChild(submitButton('Signaal toevoegen'));
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            setMessage('Signaal opslaan…');
            try {
                const data = formData(form);
                if (data.incidentAtUtc) data.incidentAtUtc = new Date(data.incidentAtUtc).toISOString();
                await jsonPost('signals', data);
                setMessage('Signaal toegevoegd.', 'success');
                await renderSignals();
            } catch (error) { setMessage(error.message, 'error'); }
        });
        return form;
    };

    const renderSignals = async () => {
        const data = await api('signals?limit=75');
        clear(container);
        container.appendChild(toolbar('Signalen-inbox', 'Tips, nieuwsaanleidingen, foto-opdrachten en 112-signalen op één plek.'));
        const form = signalForm();
        if (form) container.appendChild(form);
        container.appendChild(renderRows(data.items, (item) => {
            const row = element('article', { class: `mvm-hub4__platform-row${item.kind === 'safety' ? ' mvm-hub4__platform-safety' : ''}` });
            const copy = element('div');
            copy.appendChild(element('strong', { text: item.title }));
            copy.appendChild(element('small', { text: `${item.kind} · ${item.status} · ${formatDate(item.createdAtUtc)}` }));
            if (item.summary) copy.appendChild(element('p', { text: item.summary }));
            if (item.kind === 'safety') copy.appendChild(element('small', { text: `${item.location || 'Locatie ontbreekt'} · ${item.verificationStatus} · ${item.incidentStatus}` }));
            row.appendChild(copy);
            const badges = element('div', { class: 'mvm-hub4__platform-badges' });
            badges.appendChild(badge(`P${item.priority}`, item.priority === 1));
            badges.appendChild(badge(item.status));
            if (platform?.writes?.signalTriage) {
                const select = element('select', { 'aria-label': `Status ${item.title}` });
                ['new','triage','assigned','in_progress','converted','closed','rejected'].forEach((value) => {
                    const option = element('option', { value, text: value });
                    if (value === item.status) option.selected = true;
                    select.appendChild(option);
                });
                select.addEventListener('change', async () => {
                    try { await jsonPost(`signals/${item.id}`, { status: select.value }); setMessage('Signaalstatus bijgewerkt.', 'success'); await renderSignals(); }
                    catch (error) { setMessage(error.message, 'error'); }
                });
                badges.appendChild(select);
            }
            row.appendChild(badges);
            return row;
        }));
    };

    const renderDossiers = async () => {
        const data = await api('dossiers?limit=75');
        clear(container);
        container.appendChild(toolbar('Redactionele dossiers', 'Verbind bronnen, signalen, nieuws, agenda en media rond één verhaal.'));
        if (platform?.writes?.dossierManage) {
            const form = element('form', { class: 'mvm-hub4__platform-form' });
            form.appendChild(field('Titel', 'title'));
            form.appendChild(field('Samenvatting', 'summary', 'textarea'));
            form.appendChild(field('Interne briefing', 'internalBrief', 'textarea'));
            form.appendChild(submitButton('Dossier maken'));
            form.addEventListener('submit', async (event) => {
                event.preventDefault();
                try { await jsonPost('dossiers', formData(form)); setMessage('Dossier gemaakt.', 'success'); await renderDossiers(); }
                catch (error) { setMessage(error.message, 'error'); }
            });
            container.appendChild(form);
        }
        container.appendChild(renderRows(data.items, (item) => {
            const row = element('article', { class: 'mvm-hub4__platform-row' });
            const copy = element('div');
            copy.appendChild(element('strong', { text: item.title }));
            copy.appendChild(element('small', { text: `${item.status} · ${item.visibility} · bijgewerkt ${formatDate(item.updatedAtUtc)}` }));
            if (item.summary) copy.appendChild(element('p', { text: item.summary }));
            row.appendChild(copy);
            const badges = element('div', { class: 'mvm-hub4__platform-badges' });
            badges.appendChild(badge(item.status));
            badges.appendChild(badge(item.visibility));
            row.appendChild(badges);
            return row;
        }));
    };

    const renderCalendar = async () => {
        const data = await api('calendar?limit=150');
        clear(container);
        container.appendChild(toolbar('Redactiekalender', 'Planning, deadlines, reviews, fotografie en veiligheidsupdates.'));
        if (platform?.writes?.calendarManage) {
            const form = element('form', { class: 'mvm-hub4__platform-form' });
            form.appendChild(field('Type', 'kind', 'select', [['news','Nieuws'],['event','Evenement'],['photo','Foto'],['review','Review'],['deadline','Deadline'],['distribution','Distributie'],['safety','112 / veiligheid']]));
            form.appendChild(field('Titel', 'title'));
            form.appendChild(field('Start', 'startsAtUtc', 'datetime-local'));
            form.appendChild(submitButton('Inplannen'));
            form.addEventListener('submit', async (event) => {
                event.preventDefault();
                try {
                    const data = formData(form);
                    if (data.startsAtUtc) data.startsAtUtc = new Date(data.startsAtUtc).toISOString();
                    await jsonPost('calendar', data);
                    setMessage('Planning toegevoegd.', 'success');
                    await renderCalendar();
                } catch (error) { setMessage(error.message, 'error'); }
            });
            container.appendChild(form);
        }
        container.appendChild(renderRows(data.items, (item) => {
            const row = element('article', { class: 'mvm-hub4__platform-row' });
            const copy = element('div');
            copy.appendChild(element('strong', { text: item.title }));
            copy.appendChild(element('small', { text: `${formatDate(item.startsAtUtc)} · ${item.kind}` }));
            row.appendChild(copy);
            row.appendChild(element('div', { class: 'mvm-hub4__platform-badges' }, badge(item.status)));
            return row;
        }));
    };

    const renderMedia = async () => {
        const data = await api('media?limit=75');
        clear(container);
        container.appendChild(toolbar('Mediadesk', 'Foto- en mediaopdrachten met credits, alt-tekst en toestemmingsstatus.'));
        if (platform?.writes?.mediaManage) {
            const form = element('form', { class: 'mvm-hub4__platform-form' });
            form.appendChild(field('Type', 'kind', 'select', [['photo','Foto'],['video','Video'],['audio','Audio'],['document','Document']]));
            form.appendChild(field('Titel', 'title'));
            form.appendChild(field('Credit', 'credit'));
            form.appendChild(field('Locatie', 'location'));
            form.appendChild(field('Alt-tekst', 'altText'));
            form.appendChild(field('Toestemming', 'consentStatus', 'select', [['unknown','Onbekend'],['not_required','Niet nodig'],['obtained','Verkregen'],['restricted','Beperkt']]));
            form.appendChild(submitButton('Media-item maken'));
            form.addEventListener('submit', async (event) => {
                event.preventDefault();
                try { await jsonPost('media', formData(form)); setMessage('Media-item gemaakt.', 'success'); await renderMedia(); }
                catch (error) { setMessage(error.message, 'error'); }
            });
            container.appendChild(form);
        }
        container.appendChild(renderRows(data.items, (item) => {
            const row = element('article', { class: 'mvm-hub4__platform-row' });
            const copy = element('div');
            copy.appendChild(element('strong', { text: item.title }));
            copy.appendChild(element('small', { text: `${item.kind} · ${item.credit || 'credit nog niet ingevuld'} · ${item.consentStatus}` }));
            row.appendChild(copy);
            row.appendChild(element('div', { class: 'mvm-hub4__platform-badges' }, badge(item.status)));
            return row;
        }));
    };

    const renderDistribution = async () => {
        const data = await api('distribution?limit=75');
        clear(container);
        container.appendChild(toolbar('Distributiecentrum', 'Maak kanaalteksten voor gepubliceerde berichten; verzenden blijft altijd een bewuste menselijke stap.'));
        if (platform?.writes?.distributionPrepare) {
            const form = element('form', { class: 'mvm-hub4__platform-form' });
            form.appendChild(field('Bericht-ID', 'postId', 'number'));
            form.appendChild(field('Kanaal', 'channel', 'select', [['facebook','Facebook'],['whatsapp','WhatsApp'],['email','E-mail'],['peepso','PeepSo'],['other','Overig']]));
            form.appendChild(field('Tekst', 'copyText', 'textarea'));
            form.appendChild(field('Doel-URL', 'targetUrl', 'url'));
            form.appendChild(submitButton('Concept opslaan'));
            form.addEventListener('submit', async (event) => {
                event.preventDefault();
                try { const input = formData(form); input.postId = Number(input.postId); await jsonPost('distribution', input); setMessage('Distributieconcept opgeslagen.', 'success'); await renderDistribution(); }
                catch (error) { setMessage(error.message, 'error'); }
            });
            container.appendChild(form);
        }
        container.appendChild(renderRows(data.items, (item) => {
            const row = element('article', { class: 'mvm-hub4__platform-row' });
            const copy = element('div');
            copy.appendChild(element('strong', { text: `${item.channel} · bericht #${item.postId}` }));
            copy.appendChild(element('p', { text: item.copyText }));
            row.appendChild(copy);
            row.appendChild(element('div', { class: 'mvm-hub4__platform-badges' }, badge(item.status)));
            return row;
        }));
    };

    const renderCorrections = async () => {
        const data = await api('corrections?limit=75');
        clear(container);
        container.appendChild(toolbar('Correcties & transparantie', 'Beoordeel foutmeldingen en publiceer alleen een gecontroleerde openbare toelichting.'));
        container.appendChild(renderRows(data.items, (item) => {
            const row = element('article', { class: 'mvm-hub4__platform-row' });
            const copy = element('div');
            copy.appendChild(element('strong', { text: `Artikel #${item.postId}` }));
            copy.appendChild(element('p', { text: item.message }));
            if (item.publicNote) copy.appendChild(element('small', { text: `Openbaar: ${item.publicNote}` }));
            row.appendChild(copy);
            const side = element('div', { class: 'mvm-hub4__platform-badges' });
            side.appendChild(badge(item.status));
            if (platform?.writes?.correctionManage) {
                const publish = element('button', { type: 'button', class: 'mvm-hub4__platform-button mvm-hub4__platform-button--secondary', text: 'Afhandelen' });
                publish.addEventListener('click', async () => {
                    const note = window.prompt('Korte openbare toelichting bij de correctie:');
                    if (!note) return;
                    try { await jsonPost(`corrections/${item.id}`, { status: 'published', publicNote: note }); setMessage('Correctie gepubliceerd.', 'success'); await renderCorrections(); }
                    catch (error) { setMessage(error.message, 'error'); }
                });
                side.appendChild(publish);
            }
            row.appendChild(side);
            return row;
        }));
    };

    const renderRadar = async () => {
        const data = await api('source-radar?limit=30');
        clear(container);
        container.appendChild(toolbar('Mierlo-radar', 'Bronnen die volgens de bestaande controleplanning aandacht vragen. Geen scraping of automatische publicatie.'));
        const grid = element('div', { class: 'mvm-hub4__platform-grid' });
        grid.appendChild(metric('Te laat', data.summary?.overdue || 0));
        grid.appendChild(metric('Vandaag', data.summary?.today || 0));
        grid.appendChild(metric('Deze week', data.summary?.thisWeek || 0));
        grid.appendChild(metric('Totaal', data.summary?.total || 0));
        container.appendChild(grid);
        const combined = [...(data.overdue || []), ...(data.today || [])];
        container.appendChild(renderRows(combined, (item) => {
            const row = element('article', { class: 'mvm-hub4__platform-row' });
            const copy = element('div');
            copy.appendChild(element('strong', { text: item.title || item.name || `Bron #${item.id || item.sourcePostId || 0}` }));
            copy.appendChild(element('small', { text: item.next_check_utc || item.nextCheckUtc || 'Controle nodig' }));
            row.appendChild(copy);
            return row;
        }));
    };

    const renderView = async (view) => {
        renderLoading();
        setMessage('');
        try {
            if (!platform) platform = await api('platform');
            if (view === 'dashboard') await renderDashboard();
            else if (view === 'signals') await renderSignals();
            else if (view === 'dossiers') await renderDossiers();
            else if (view === 'calendar') await renderCalendar();
            else if (view === 'media') await renderMedia();
            else if (view === 'distribution') await renderDistribution();
            else if (view === 'corrections') await renderCorrections();
            else await renderRadar();
        } catch (error) {
            clear(container);
            container.appendChild(element('div', { class: 'mvm-hub4__platform-empty', text: error.message }));
            setMessage(error.message, 'error');
        }
    };

    const showPlatform = async (view) => {
        activeView = view;
        if (classicView) classicView.hidden = true;
        if (workflow) workflow.hidden = true;
        if (myWork) myWork.hidden = true;
        container.hidden = false;
        if (titleNode) titleNode.textContent = labels[view] || 'Newsroom 1.0';
        classicNav.forEach((button) => { button.classList.remove('is-active'); button.setAttribute('aria-pressed', 'false'); });
        platformNav.forEach((button) => {
            const active = button.dataset.mvmPlatformView === view;
            button.classList.toggle('is-active', active);
            button.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
        const url = new URL(window.location.href);
        url.searchParams.delete('view');
        url.searchParams.set('platform', view);
        window.history.pushState({ platform: view }, '', url.toString());
        await renderView(view);
    };

    const showClassic = () => {
        activeView = '';
        container.hidden = true;
        if (classicView) classicView.hidden = false;
        if (workflow) workflow.hidden = false;
        if (myWork) myWork.hidden = false;
        platformNav.forEach((button) => { button.classList.remove('is-active'); button.setAttribute('aria-pressed', 'false'); });
    };

    platformNav.forEach((button) => button.addEventListener('click', () => showPlatform(button.dataset.mvmPlatformView || 'dashboard')));
    classicNav.forEach((button) => button.addEventListener('click', showClassic));
    window.addEventListener('popstate', () => {
        const view = new URL(window.location.href).searchParams.get('platform');
        if (view && labels[view]) showPlatform(view);
        else if (activeView) showClassic();
    });

    const initial = new URL(window.location.href).searchParams.get('platform');
    if (initial && labels[initial] && platformNav.some((button) => button.dataset.mvmPlatformView === initial)) showPlatform(initial);
})();
