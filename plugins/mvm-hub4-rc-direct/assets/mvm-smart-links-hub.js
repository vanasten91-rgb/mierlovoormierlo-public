(() => {
    'use strict';

    const v3Config = window.MvMHubs3SmartLinksConfig || null;
    const config = v3Config || window.MvMHub4Config || {};
    const isV3 = !!v3Config;
    const readOnly = !!config.readOnly;
    const nav = isV3 ? null : document.querySelector('[data-mvm-smart-links-view]');
    const container = isV3 ? document.querySelector('[data-mvmh3-smart-links]') : document.querySelector('[data-mvm-service-container]');
    const classicView = isV3 ? null : document.querySelector('[data-mvm-view-container]');
    const platformView = isV3 ? null : document.querySelector('[data-mvm-platform-container]');
    const workflow = isV3 ? null : document.querySelector('[data-mvm-workflow-guide]');
    const myWork = isV3 ? null : document.querySelector('[data-mvm-my-work]');
    const titleNode = isV3 ? null : document.querySelector('[data-mvm-view-title]');
    const messageNode = isV3 ? document.querySelector('[data-mvmh3-smart-links-message]') : document.querySelector('[data-mvm-platform-message]');

    if (!container || !config.restRoot || !config.restNonce || (!isV3 && !nav)) return;

    let state = null;
    let targetTimer = null;

    const el = (tag, attrs = {}, children = []) => {
        const node = document.createElement(tag);
        Object.entries(attrs).forEach(([key, value]) => {
            if (value === null || value === undefined || value === false) return;
            if (key === 'class') node.className = String(value);
            else if (key === 'text') node.textContent = String(value);
            else node.setAttribute(key, String(value));
        });
        (Array.isArray(children) ? children : [children]).forEach((child) => {
            if (child === null || child === undefined) return;
            node.appendChild(child instanceof Node ? child : document.createTextNode(String(child)));
        });
        return node;
    };

    const clear = (node) => { while (node.firstChild) node.removeChild(node.firstChild); };
    const lower = (value) => String(value || '').trim().toLocaleLowerCase('nl-NL');
    const setMessage = (text = '', type = '') => {
        if (!messageNode) return;
        messageNode.textContent = text;
        messageNode.className = isV3 ? 'mvmh3-smartlinks__status' : 'mvm-hub4__platform-message';
        if (type) messageNode.classList.add(`is-${type}`);
    };

    const api = async (path, options = {}) => {
        const url = new URL(String(path).replace(/^\//, ''), config.restRoot);
        const headers = new Headers(options.headers || {});
        headers.set('X-WP-Nonce', config.restNonce);
        headers.set('Accept', 'application/json');
        if (options.body) headers.set('Content-Type', 'application/json; charset=UTF-8');
        const response = await fetch(url.toString(), { ...options, headers, credentials: 'same-origin', cache: 'no-store' });
        let data = {};
        try { data = await response.json(); } catch (error) { throw new Error('Ongeldig antwoord van Smart Links-beheer.'); }
        if (!response.ok) throw new Error(data.message || 'Smart Links-actie mislukt.');
        return data;
    };

    const request = (path, method, payload) => api(path, {
        method,
        body: payload === undefined ? undefined : JSON.stringify(payload),
    });

    const syncTermDrafts = () => {
        if (!state || !state.settings) return;
        const blacklist = container.querySelector('[data-mvm-smart-blacklist]');
        const whitelist = container.querySelector('[data-mvm-smart-whitelist]');
        if (blacklist) state.settings.blacklist = blacklist.value.split(/\r?\n/).map((item) => item.trim()).filter(Boolean);
        if (whitelist) state.settings.whitelist = whitelist.value.split(/\r?\n/).map((item) => item.trim()).filter(Boolean);
    };

    const metric = (label, value, detail = '') => {
        const card = el('article', { class: isV3 ? 'mvmh3-card mvm-smartlinks__metric' : 'mvm-hub4__platform-card mvm-smartlinks__metric' });
        card.appendChild(el('h3', { text: label }));
        card.appendChild(el('div', { class: 'mvm-hub4__platform-metric', text: String(value) }));
        if (detail) card.appendChild(el('small', { text: detail }));
        return card;
    };

    const section = (title, intro = '') => {
        const node = el('section', { class: 'mvm-smartlinks__section' });
        node.appendChild(el('h3', { text: title }));
        if (intro) node.appendChild(el('p', { class: 'mvm-smartlinks__intro', text: intro }));
        return node;
    };

    const renderDiagnostics = (root) => {
        const diagnostics = state && state.diagnostics ? state.diagnostics : {};
        const conflicts = Array.isArray(diagnostics.conflicts) ? diagnostics.conflicts : [];
        const broken = Array.isArray(diagnostics.broken_targets) ? diagnostics.broken_targets : [];
        const warnings = Array.isArray(diagnostics.warnings) ? diagnostics.warnings : [];
        if (!conflicts.length && !broken.length && !warnings.length) {
            root.appendChild(el('div', { class: 'mvm-smartlinks__notice is-good', text: 'Geen conflicten of kapotte handmatige targets gevonden.' }));
            return;
        }

        const box = el('div', { class: 'mvm-smartlinks__notice is-warning' });
        box.appendChild(el('strong', { text: 'Aandachtspunten' }));
        const list = el('ul');
        conflicts.forEach((item) => list.appendChild(el('li', { text: item })));
        broken.forEach((item) => list.appendChild(el('li', { text: `Kapotte target: “${item.label}” → dossier #${item.target_id}` })));
        warnings.forEach((item) => list.appendChild(el('li', { text: item })));
        box.appendChild(list);
        root.appendChild(box);
    };

    const buildAliasManager = () => {
        const node = section('Handmatige aliases', 'Voeg lokale termen toe die naar een bestaand encyclopediedossier moeten verwijzen.');
        const form = el('form', { class: 'mvm-smartlinks__alias-form' });
        const label = el('input', { type: 'text', maxlength: '180', placeholder: 'Alias, bijvoorbeeld lokale roepnaam', required: 'required' });
        const targetSearch = el('input', { type: 'search', maxlength: '180', placeholder: 'Zoek dossier op titel', autocomplete: 'off', required: 'required' });
        const selected = el('div', { class: 'mvm-smartlinks__selected-target', text: 'Nog geen target gekozen.' });
        const results = el('div', { class: 'mvm-smartlinks__target-results', role: 'listbox' });
        const add = el('button', { type: 'submit', class: 'mvm-hub4__platform-button', text: 'Alias toevoegen' });
        let selectedTarget = null;

        const searchTargets = async () => {
            const query = targetSearch.value.trim();
            clear(results);
            selectedTarget = null;
            selected.textContent = 'Nog geen target gekozen.';
            if (query.length < 2) return;
            results.appendChild(el('div', { class: 'mvm-smartlinks__muted', text: 'Zoeken…' }));
            try {
                const data = await api(`smart-links/targets?search=${encodeURIComponent(query)}`);
                clear(results);
                const items = Array.isArray(data.items) ? data.items : [];
                if (!items.length) {
                    results.appendChild(el('div', { class: 'mvm-smartlinks__muted', text: 'Geen dossiers gevonden.' }));
                    return;
                }
                items.forEach((item) => {
                    const button = el('button', {
                        type: 'button',
                        class: 'mvm-smartlinks__target-option',
                        role: 'option',
                        text: `${item.title} · #${item.id}`,
                    });
                    button.addEventListener('click', () => {
                        selectedTarget = item;
                        selected.textContent = `Target: ${item.title} (#${item.id})`;
                        clear(results);
                    });
                    results.appendChild(button);
                });
            } catch (error) {
                clear(results);
                results.appendChild(el('div', { class: 'mvm-smartlinks__muted', text: error.message }));
            }
        };

        targetSearch.addEventListener('input', () => {
            window.clearTimeout(targetTimer);
            targetTimer = window.setTimeout(searchTargets, 300);
        });

        form.appendChild(el('label', { text: 'Alias' }));
        form.appendChild(label);
        form.appendChild(el('label', { text: 'Targetdossier' }));
        form.appendChild(targetSearch);
        form.appendChild(results);
        form.appendChild(selected);
        form.appendChild(add);

        form.addEventListener('submit', (event) => {
            event.preventDefault();
            const aliasLabel = label.value.trim();
            if (!aliasLabel || !selectedTarget) {
                setMessage('Vul een alias in en kies een targetdossier.', 'error');
                return;
            }
            const exists = state.settings.aliases.some((item) => lower(item.label) === lower(aliasLabel));
            if (exists) {
                setMessage('Deze alias staat al in de handmatige lijst.', 'error');
                return;
            }
            syncTermDrafts();
            state.settings.aliases.push({ label: aliasLabel, target_id: Number(selectedTarget.id) });
            state.manual_aliases.push({
                label: aliasLabel,
                target_id: Number(selectedTarget.id),
                target_title: selectedTarget.title,
                target_type: selectedTarget.post_type,
                target_status: 'publish',
                target_url: selectedTarget.url,
            });
            label.value = '';
            targetSearch.value = '';
            selectedTarget = null;
            selected.textContent = 'Nog geen target gekozen.';
            render();
            setMessage('Alias toegevoegd aan de conceptinstellingen. Klik “Instellingen opslaan” om hem definitief vast te leggen.', 'success');
        });

        node.appendChild(form);

        const list = el('div', { class: 'mvm-smartlinks__rows' });
        const aliases = Array.isArray(state.manual_aliases) ? state.manual_aliases : [];
        if (!aliases.length) {
            list.appendChild(el('div', { class: 'mvm-smartlinks__empty', text: 'Geen handmatige aliases.' }));
        }
        aliases.forEach((item, index) => {
            const row = el('article', { class: 'mvm-smartlinks__row' });
            const copy = el('div');
            copy.appendChild(el('strong', { text: item.label }));
            copy.appendChild(el('small', { text: item.target_title ? `→ ${item.target_title} (#${item.target_id})` : `→ ontbrekend dossier #${item.target_id}` }));
            row.appendChild(copy);
            const actions = el('div', { class: 'mvm-smartlinks__row-actions' });
            if (item.target_url) actions.appendChild(el('a', { href: item.target_url, target: '_blank', rel: 'noopener noreferrer', text: 'Bekijken' }));
            const remove = el('button', { type: 'button', text: 'Verwijderen' });
            remove.addEventListener('click', () => {
                syncTermDrafts();
                state.settings.aliases.splice(index, 1);
                state.manual_aliases.splice(index, 1);
                render();
                setMessage('Alias uit de conceptinstellingen verwijderd. Sla de instellingen op om dit definitief te maken.', 'success');
            });
            actions.appendChild(remove);
            row.appendChild(actions);
            list.appendChild(row);
        });
        node.appendChild(list);
        return node;
    };

    const buildTermLists = () => {
        const node = section('Blacklist & whitelist', 'Blacklist-termen worden nooit automatisch gelinkt. Whitelist staat bewust korte maar veilige termen toe.');
        const grid = el('div', { class: 'mvm-smartlinks__term-grid' });

        const blacklistWrap = el('label');
        blacklistWrap.appendChild(el('strong', { text: 'Blacklist' }));
        blacklistWrap.appendChild(el('small', { text: 'Eén term per regel' }));
        const blacklist = el('textarea', { rows: '12', 'data-mvm-smart-blacklist': '1' });
        blacklist.value = (state.settings.blacklist || []).join('\n');
        blacklistWrap.appendChild(blacklist);

        const whitelistWrap = el('label');
        whitelistWrap.appendChild(el('strong', { text: 'Whitelist' }));
        whitelistWrap.appendChild(el('small', { text: 'Eén korte, veilige term per regel' }));
        const whitelist = el('textarea', { rows: '12', 'data-mvm-smart-whitelist': '1' });
        whitelist.value = (state.settings.whitelist || []).join('\n');
        whitelistWrap.appendChild(whitelist);

        grid.appendChild(blacklistWrap);
        grid.appendChild(whitelistWrap);
        node.appendChild(grid);
        return node;
    };

    const buildAutomaticList = () => {
        const aliases = Array.isArray(state.automatic_aliases) ? state.automatic_aliases : [];
        const node = section('Automatische aliases', 'Deze varianten worden automatisch afgeleid uit formele namen van personen, verenigingen, bedrijven en gebouwen.');
        const search = el('input', { type: 'search', class: 'mvm-smartlinks__filter', placeholder: 'Filter automatische aliases…' });
        const list = el('div', { class: 'mvm-smartlinks__rows is-scrollable' });

        const draw = () => {
            clear(list);
            const query = lower(search.value);
            const filtered = aliases.filter((item) => !query || lower(item.label).includes(query) || lower(item.target_title).includes(query));
            if (!filtered.length) list.appendChild(el('div', { class: 'mvm-smartlinks__empty', text: 'Geen automatische aliases gevonden.' }));
            filtered.forEach((item) => {
                const row = el('article', { class: 'mvm-smartlinks__row' });
                const copy = el('div');
                copy.appendChild(el('strong', { text: item.label }));
                copy.appendChild(el('small', { text: `→ ${item.target_title || `dossier #${item.target_id}`}` }));
                row.appendChild(copy);
                if (item.target_url) row.appendChild(el('a', { href: item.target_url, target: '_blank', rel: 'noopener noreferrer', text: 'Bekijken' }));
                list.appendChild(row);
            });
        };
        search.addEventListener('input', draw);
        node.appendChild(search);
        node.appendChild(list);
        draw();
        return node;
    };

    const buildTester = () => {
        const node = section('Test tekst', 'Plak een tekst om te zien welke Smart Links de huidige index zou herkennen. Er wordt niets gepubliceerd of opgeslagen.');
        const textarea = el('textarea', { rows: '8', maxlength: '15000', placeholder: 'Plak hier een nieuws- of concepttekst…' });
        const button = el('button', { type: 'button', class: isV3 ? 'mvmh3-primary' : 'mvm-hub4__platform-button', text: 'Test tekst' });
        const result = el('div', { class: 'mvm-smartlinks__test-result', 'aria-live': 'polite' });

        button.addEventListener('click', async () => {
            clear(result);
            if (!textarea.value.trim()) {
                result.appendChild(el('div', { class: 'mvm-smartlinks__empty', text: 'Voer eerst tekst in.' }));
                return;
            }
            button.disabled = true;
            result.appendChild(el('div', { class: 'mvm-smartlinks__muted', text: 'Test uitvoeren…' }));
            try {
                const data = await request('smart-links/test', 'POST', { text: textarea.value });
                clear(result);
                const matches = Array.isArray(data.matches) ? data.matches : [];
                result.appendChild(el('strong', { text: `${matches.length} link${matches.length === 1 ? '' : 's'} gevonden` }));
                if (!matches.length) return;
                const list = el('div', { class: 'mvm-smartlinks__rows' });
                matches.forEach((item) => {
                    const row = el('article', { class: 'mvm-smartlinks__row' });
                    const copy = el('div');
                    copy.appendChild(el('strong', { text: item.label }));
                    copy.appendChild(el('small', { text: `→ ${item.target_title || `dossier #${item.target_id}`} · ${item.source}` }));
                    row.appendChild(copy);
                    if (item.target_url) row.appendChild(el('a', { href: item.target_url, target: '_blank', rel: 'noopener noreferrer', text: 'Target' }));
                    list.appendChild(row);
                });
                result.appendChild(list);
            } catch (error) {
                clear(result);
                result.appendChild(el('div', { class: 'mvm-smartlinks__notice is-warning', text: error.message }));
            } finally {
                button.disabled = false;
            }
        });

        node.appendChild(textarea);
        node.appendChild(button);
        node.appendChild(result);
        return node;
    };

    const saveSettings = async () => {
        if (readOnly) {
            setMessage('Previewmodus: Smart Links-instellingen zijn alleen-lezen en worden niet opgeslagen.', 'warning');
            return;
        }
        syncTermDrafts();
        setMessage('Smart Links-instellingen opslaan…');
        try {
            state = await request('smart-links', 'POST', state.settings);
            setMessage('Smart Links-instellingen opgeslagen en de linkindex is vernieuwd.', 'success');
            render();
        } catch (error) {
            setMessage(error.message, 'error');
        }
    };

    const render = () => {
        clear(container);
        if (!state) return;

        const toolbar = el('div', { class: isV3 ? 'mvmh3-section__head mvm-smartlinks__toolbar' : 'mvm-hub4__platform-toolbar' });
        const copy = el('div');
        copy.appendChild(el('h2', { text: 'Smart Links' }));
        copy.appendChild(el('p', { text: 'Beheer automatische koppelingen van nieuws naar de Mierlose encyclopedie.' }));
        toolbar.appendChild(copy);
        const save = el('button', { type: 'button', class: isV3 ? 'mvmh3-primary' : 'mvm-hub4__button', text: readOnly ? 'Preview · alleen-lezen' : 'Instellingen opslaan' });
        if (readOnly) save.disabled = true;
        save.addEventListener('click', saveSettings);
        toolbar.appendChild(save);
        container.appendChild(toolbar);

        if (!state.stored) {
            container.appendChild(el('div', {
                class: 'mvm-smartlinks__notice',
                text: 'De huidige productieregels worden als veilige standaard getoond. Pas bij “Instellingen opslaan” worden ze naar beheerbare WordPress-instellingen gemigreerd.',
            }));
        }

        const counts = state.counts || {};
        const diagnostics = state.diagnostics || {};
        const issueCount = (diagnostics.conflicts || []).length + (diagnostics.broken_targets || []).length + (diagnostics.warnings || []).length;
        const metrics = el('div', { class: isV3 ? 'mvmh3-grid mvm-smartlinks__metrics' : 'mvm-hub4__platform-grid' });
        metrics.appendChild(metric('Actieve index', counts.total || 0, 'Alle linktermen'));
        metrics.appendChild(metric('Automatisch', counts.derived || 0, 'Afgeleide aliases'));
        metrics.appendChild(metric('Handmatig', (state.settings.aliases || []).length, 'Beheerbare aliases'));
        metrics.appendChild(metric('Aandachtspunten', issueCount, issueCount ? 'Controle nodig' : 'Alles groen'));
        container.appendChild(metrics);

        renderDiagnostics(container);

        const layout = el('div', { class: 'mvm-smartlinks__layout' });
        layout.appendChild(buildAliasManager());
        layout.appendChild(buildTermLists());
        container.appendChild(layout);
        container.appendChild(buildAutomaticList());
        container.appendChild(buildTester());
    };

    const load = async () => {
        clear(container);
        container.setAttribute('aria-busy', 'true');
        setMessage('');
        container.appendChild(el('div', { class: isV3 ? 'mvmh3-empty' : 'mvm-hub4__platform-empty', text: 'Smart Links laden…' }));
        try {
            state = await api('smart-links');
            render();
        } catch (error) {
            clear(container);
            container.appendChild(el('div', { class: isV3 ? 'mvmh3-empty' : 'mvm-hub4__platform-empty', text: error.message }));
        } finally {
            container.setAttribute('aria-busy', 'false');
        }
    };

    const deactivate = () => document.querySelectorAll('[data-mvm-view],[data-mvm-platform-view],[data-mvm-service-view],[data-mvm-news-radar-view]').forEach((button) => {
        button.classList.remove('is-active');
        button.setAttribute('aria-pressed', 'false');
    });

    const show = (updateHistory = true) => {
        if (classicView) classicView.hidden = true;
        if (platformView) platformView.hidden = true;
        if (workflow) workflow.hidden = true;
        if (myWork) myWork.hidden = true;
        container.hidden = false;
        deactivate();
        nav.classList.add('is-active');
        nav.setAttribute('aria-pressed', 'true');
        if (titleNode) titleNode.textContent = 'Smart Links';
        if (updateHistory) {
            const url = new URL(window.location.href);
            url.searchParams.delete('view');
            url.searchParams.delete('platform');
            url.searchParams.set('service', 'smart-links');
            window.history.pushState({ service: 'smart-links' }, '', url.toString());
        }
        load();
    };

    const hide = () => {
        nav.classList.remove('is-active');
        nav.setAttribute('aria-pressed', 'false');
    };

    if (isV3) {
        load();
        return;
    }

    nav.addEventListener('click', (event) => { event.preventDefault(); show(true); });
    document.querySelectorAll('[data-mvm-view],[data-mvm-platform-view],[data-mvm-service-view]:not([data-mvm-smart-links-view]),[data-mvm-news-radar-view]').forEach((button) => button.addEventListener('click', hide));
    window.addEventListener('popstate', () => new URL(window.location.href).searchParams.get('service') === 'smart-links' ? show(false) : hide());
    if (new URL(window.location.href).searchParams.get('service') === 'smart-links') show(false);
})();
