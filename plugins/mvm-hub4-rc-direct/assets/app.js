(() => {
    'use strict';

    const config = window.MvMHub4Config || {};
    const container = document.querySelector('[data-mvm-view-container]');
    const titleNode = document.querySelector('[data-mvm-view-title]');
    const statusNode = document.querySelector('[data-mvm-status]');
    const navButtons = Array.from(document.querySelectorAll('[data-mvm-view]'));

    if (!container || !titleNode || !config.restRoot || !config.restNonce) {
        return;
    }

    const labels = {
        today: 'Vandaag',
        news: 'Nieuws',
        sources: 'Bronnen',
        agenda: 'Agenda',
        team: 'Team',
        more: 'Meer'
    };

    const state = {
        view: 'today',
        sourceOptions: null,
        sourceController: null,
        sourcePage: 1,
        selected: new Set(),
        sourceFilters: {
            search: '',
            due: '',
            category: '',
            frequency: '',
            status: '',
            configured: ''
        }
    };

    const element = (tag, attrs = {}, children = []) => {
        const node = document.createElement(tag);
        Object.entries(attrs).forEach(([key, value]) => {
            if (value === null || value === undefined || value === false) {
                return;
            }
            if (key === 'class') {
                node.className = String(value);
            } else if (key === 'text') {
                node.textContent = String(value);
            } else if (key === 'checked') {
                node.checked = Boolean(value);
            } else if (key === 'disabled') {
                node.disabled = Boolean(value);
            } else if (key.startsWith('data-')) {
                node.setAttribute(key, String(value));
            } else {
                node.setAttribute(key, String(value));
            }
        });
        const list = Array.isArray(children) ? children : [children];
        list.forEach((child) => {
            if (child === null || child === undefined) {
                return;
            }
            node.appendChild(child instanceof Node ? child : document.createTextNode(String(child)));
        });
        return node;
    };

    const clear = (node) => {
        while (node.firstChild) {
            node.removeChild(node.firstChild);
        }
    };

    const setStatus = (message = '', type = '') => {
        statusNode.textContent = message;
        statusNode.className = 'mvm-hub4__status';
        if (type) {
            statusNode.classList.add(`is-${type}`);
        }
    };

    const api = async (path, options = {}) => {
        const url = new URL(path.replace(/^\//, ''), config.restRoot);
        const headers = new Headers(options.headers || {});
        headers.set('X-WP-Nonce', config.restNonce);
        headers.set('Accept', 'application/json');
        if (options.body && !headers.has('Content-Type')) {
            headers.set('Content-Type', 'application/json; charset=UTF-8');
        }

        const response = await fetch(url.toString(), {
            ...options,
            credentials: 'same-origin',
            headers
        });

        let json = null;
        try {
            json = await response.json();
        } catch (error) {
            throw new Error('De Hub gaf geen geldig antwoord.');
        }

        if (!response.ok) {
            throw new Error(json && json.message ? json.message : 'De actie kon niet worden uitgevoerd.');
        }

        return json;
    };

    const button = (label, onClick, secondary = false) => {
        const node = element('button', {
            type: 'button',
            class: `mvm-hub4__button${secondary ? ' mvm-hub4__button--secondary' : ''}`,
            text: label
        });
        node.addEventListener('click', onClick);
        return node;
    };

    const formatUtc = (value) => {
        if (!value) {
            return '—';
        }
        const date = new Date(`${String(value).replace(' ', 'T')}Z`);
        if (Number.isNaN(date.getTime())) {
            return '—';
        }
        return new Intl.DateTimeFormat('nl-NL', {
            dateStyle: 'medium',
            timeStyle: 'short'
        }).format(date);
    };

    const renderLoading = (label = 'Laden…') => {
        clear(container);
        container.setAttribute('aria-busy', 'true');
        container.appendChild(element('div', { class: 'mvm-hub4__loading', text: label }));
    };

    const finishRender = () => {
        container.setAttribute('aria-busy', 'false');
    };

    const metricCard = (title, count, description, blue = false) => {
        const card = element('article', { class: `mvm-hub4__card${blue ? ' mvm-hub4__card--blue' : ''}` });
        card.appendChild(element('h2', { text: title }));
        const metric = element('div', { class: 'mvm-hub4__metric' }, [
            element('strong', { text: String(count) })
        ]);
        card.appendChild(metric);
        card.appendChild(element('p', { text: description }));
        return card;
    };

    const renderToday = async () => {
        renderLoading('Vandaag laden…');
        try {
            const data = await api('status');
            clear(container);

            const section = element('section', { class: 'mvm-hub4__section' });
            const head = element('div', { class: 'mvm-hub4__section-head' });
            const intro = element('div');
            intro.appendChild(element('h2', { text: 'Wat vraagt vandaag aandacht?' }));
            intro.appendChild(element('p', { text: 'Eén overzicht voor bronnen, nieuws en redactieprioriteiten.' }));
            head.appendChild(intro);
            head.appendChild(button('Open bronnen', () => setView('sources')));
            section.appendChild(head);

            const grid = element('div', { class: 'mvm-hub4__grid' }, [
                metricCard('Achterstallige bronnen', data.sources?.overdue || 0, 'Deze bronnen hadden al gecontroleerd moeten zijn.', true),
                metricCard('Vandaag controleren', data.sources?.today || 0, 'Bronnen die vandaag op de controlelijst staan.'),
                metricCard('Komende 7 dagen', data.sources?.thisWeek || 0, 'Bronnen die daarna deze week aandacht vragen.')
            ]);
            section.appendChild(grid);

            const quality = element('article', { class: 'mvm-hub4__card' });
            quality.appendChild(element('h2', { text: 'Veilige newsroom' }));
            quality.appendChild(element('p', { text: `Auditlog bewaart standaard ${data.auditRetentionDays || 30} dagen en nooit korter dan 14 dagen. Routine-acties werken zonder volledige paginaverversing.` }));
            section.appendChild(quality);
            container.appendChild(section);
            finishRender();
        } catch (error) {
            clear(container);
            container.appendChild(element('div', { class: 'mvm-hub4__empty', text: error.message }));
            finishRender();
        }
    };

    const placeholderView = (title, description, items) => {
        clear(container);
        const section = element('section', { class: 'mvm-hub4__placeholder' });
        const card = element('article', { class: 'mvm-hub4__card' });
        card.appendChild(element('h2', { text: title }));
        card.appendChild(element('p', { text: description }));
        const list = element('ul');
        items.forEach((item) => list.appendChild(element('li', { text: item })));
        card.appendChild(list);
        section.appendChild(card);
        container.appendChild(section);
        finishRender();
    };

    const ensureSourceOptions = async () => {
        if (!state.sourceOptions) {
            state.sourceOptions = await api('sources/options');
        }
        return state.sourceOptions;
    };

    const selectField = (label, key, options, firstLabel) => {
        const wrap = element('div', { class: 'mvm-hub4__field' });
        const id = `mvm-source-filter-${key}`;
        wrap.appendChild(element('label', { for: id, text: label }));
        const select = element('select', { id, 'data-source-filter': key });
        select.appendChild(element('option', { value: '', text: firstLabel }));
        options.forEach((option) => {
            const node = element('option', { value: option.value, text: option.label });
            if (state.sourceFilters[key] === option.value) {
                node.selected = true;
            }
            select.appendChild(node);
        });
        wrap.appendChild(select);
        return wrap;
    };

    const sourceDueBadge = (item) => {
        const map = {
            overdue: ['Achterstallig', 'mvm-hub4__badge mvm-hub4__badge--overdue'],
            today: ['Vandaag', 'mvm-hub4__badge mvm-hub4__badge--today'],
            week: ['Deze week', 'mvm-hub4__badge'],
            later: ['Later', 'mvm-hub4__badge mvm-hub4__badge--ok'],
            unscheduled: ['Handmatig', 'mvm-hub4__badge'],
            inactive: ['Niet actief', 'mvm-hub4__badge']
        };
        const value = map[item.dueState] || ['—', 'mvm-hub4__badge'];
        return element('span', { class: value[1], text: value[0] });
    };

    const renderSourceRow = (item, options) => {
        const row = element('tr');
        const checkbox = element('input', {
            type: 'checkbox',
            'aria-label': `Selecteer ${item.title}`,
            checked: state.selected.has(item.id)
        });
        checkbox.addEventListener('change', () => {
            if (checkbox.checked) {
                state.selected.add(item.id);
            } else {
                state.selected.delete(item.id);
            }
            updateBulkToolbar(options);
        });
        row.appendChild(element('td', {}, checkbox));

        const titleCell = element('td');
        const titleWrap = element('div', { class: 'mvm-hub4__source-title' });
        titleWrap.appendChild(element('strong', { text: item.title }));
        const links = element('div', { class: 'mvm-hub4__source-links' });
        if (item.externalUrl) {
            const external = element('a', { href: item.externalUrl, target: '_blank', rel: 'noopener noreferrer', text: 'Open bron ↗' });
            links.appendChild(external);
        }
        if (item.sourcePageUrl) {
            links.appendChild(element('a', { href: item.sourcePageUrl, target: '_blank', rel: 'noopener noreferrer', text: 'Bronrecord' }));
        }
        titleWrap.appendChild(links);
        titleCell.appendChild(titleWrap);
        row.appendChild(titleCell);

        row.appendChild(element('td', {}, item.configured ? element('span', { class: 'mvm-hub4__badge', text: item.categoryLabel }) : element('span', { class: 'mvm-hub4__muted', text: 'Nog instellen' })));
        row.appendChild(element('td', {}, item.configured ? element('span', { text: item.frequencyLabel }) : element('span', { class: 'mvm-hub4__muted', text: '—' })));
        row.appendChild(element('td', {}, sourceDueBadge(item)));

        const last = element('td');
        last.appendChild(element('div', { text: formatUtc(item.lastCheckedUtc) }));
        if (item.lastCheckedName) {
            last.appendChild(element('div', { class: 'mvm-hub4__muted', text: item.lastCheckedName }));
        }
        row.appendChild(last);
        row.appendChild(element('td', { text: formatUtc(item.nextCheckUtc) }));

        const actions = element('td');
        const actionWrap = element('div', { class: 'mvm-hub4__row-actions' });
        if (options.permissions?.canCheck) {
            const checkButton = button('Gecontroleerd', async () => {
                checkButton.disabled = true;
                setStatus('Bron afvinken…');
                try {
                    await api(`sources/${item.id}/check`, { method: 'POST', body: '{}' });
                    setStatus('Bron is als gecontroleerd gemarkeerd.', 'success');
                    await loadSourceTable(options);
                } catch (error) {
                    setStatus(error.message, 'error');
                    checkButton.disabled = false;
                }
            }, true);
            actionWrap.appendChild(checkButton);
        }
        actions.appendChild(actionWrap);
        row.appendChild(actions);
        return row;
    };

    const updateBulkToolbar = (options) => {
        const toolbar = container.querySelector('[data-source-bulk]');
        if (!toolbar) {
            return;
        }
        toolbar.hidden = state.selected.size < 1;
        const count = toolbar.querySelector('[data-source-selected-count]');
        if (count) {
            count.textContent = `${state.selected.size} geselecteerd`;
        }
    };

    const buildBulkToolbar = (options) => {
        const toolbar = element('div', { class: 'mvm-hub4__toolbar', 'data-source-bulk': '1' });
        toolbar.hidden = state.selected.size < 1;
        toolbar.appendChild(element('strong', { 'data-source-selected-count': '1', text: `${state.selected.size} geselecteerd` }));

        if (options.permissions?.canManage) {
            const addSelect = (name, choices, label) => {
                const select = element('select', { 'data-bulk-field': name, 'aria-label': label });
                select.appendChild(element('option', { value: '', text: label }));
                choices.forEach((choice) => select.appendChild(element('option', { value: choice.value, text: choice.label })));
                toolbar.appendChild(select);
            };
            addSelect('category', options.categories || [], 'Categorie wijzigen…');
            addSelect('frequency', options.frequencies || [], 'Frequentie wijzigen…');
            addSelect('status', options.statuses || [], 'Status wijzigen…');
            addSelect('monitorEnabled', [
                { value: '1', label: 'Controle inschakelen' },
                { value: '0', label: 'Controle uitschakelen' }
            ], 'Controleplanning…');

            toolbar.appendChild(button('Toepassen', async (event) => {
                const applyButton = event.currentTarget;
                const payload = { ids: Array.from(state.selected) };
                toolbar.querySelectorAll('[data-bulk-field]').forEach((select) => {
                    if (!select.value) {
                        return;
                    }
                    if (select.dataset.bulkField === 'monitorEnabled') {
                        payload.monitorEnabled = select.value === '1';
                    } else {
                        payload[select.dataset.bulkField] = select.value;
                    }
                });
                if (Object.keys(payload).length === 1) {
                    setStatus('Kies eerst wat je wilt wijzigen.', 'error');
                    return;
                }
                applyButton.disabled = true;
                setStatus('Bulk-wijziging opslaan…');
                try {
                    await api('sources/bulk-update', { method: 'POST', body: JSON.stringify(payload) });
                    state.selected.clear();
                    setStatus('De geselecteerde bronnen zijn bijgewerkt.', 'success');
                    await loadSourceTable(options);
                } catch (error) {
                    setStatus(error.message, 'error');
                    applyButton.disabled = false;
                }
            }));
        }

        if (options.permissions?.canCheck) {
            toolbar.appendChild(button('Markeer gecontroleerd', async (event) => {
                const checkButton = event.currentTarget;
                checkButton.disabled = true;
                setStatus('Geselecteerde bronnen afvinken…');
                try {
                    await api('sources/bulk-check', {
                        method: 'POST',
                        body: JSON.stringify({ ids: Array.from(state.selected) })
                    });
                    state.selected.clear();
                    setStatus('De geselecteerde bronnen zijn gecontroleerd.', 'success');
                    await loadSourceTable(options);
                } catch (error) {
                    setStatus(error.message, 'error');
                    checkButton.disabled = false;
                }
            }, true));
        }
        return toolbar;
    };

    const loadSourceTable = async (options) => {
        const target = container.querySelector('[data-source-results]');
        if (!target) {
            return;
        }

        if (state.sourceController) {
            state.sourceController.abort();
        }
        state.sourceController = new AbortController();
        target.setAttribute('aria-busy', 'true');
        clear(target);
        target.appendChild(element('div', { class: 'mvm-hub4__loading', text: 'Bronnen laden…' }));

        const params = new URLSearchParams({
            page: String(state.sourcePage),
            per_page: '50'
        });
        Object.entries(state.sourceFilters).forEach(([key, value]) => {
            if (value) {
                params.set(key, value);
            }
        });

        try {
            const data = await api(`sources?${params.toString()}`, { signal: state.sourceController.signal });
            clear(target);

            const tableWrap = element('div', { class: 'mvm-hub4__table-wrap' });
            const table = element('table', { class: 'mvm-hub4__table' });
            const thead = element('thead');
            const headRow = element('tr');
            const selectAll = element('input', { type: 'checkbox', 'aria-label': 'Selecteer alle bronnen op deze pagina' });
            headRow.appendChild(element('th', {}, selectAll));
            ['Bron', 'Categorie', 'Frequentie', 'Planning', 'Laatst gecontroleerd', 'Volgende controle', 'Actie'].forEach((label) => headRow.appendChild(element('th', { text: label })));
            thead.appendChild(headRow);
            table.appendChild(thead);

            const tbody = element('tbody');
            (data.items || []).forEach((item) => tbody.appendChild(renderSourceRow(item, options)));
            table.appendChild(tbody);
            tableWrap.appendChild(table);

            if (!data.items || !data.items.length) {
                target.appendChild(element('div', { class: 'mvm-hub4__empty', text: 'Geen bronnen gevonden voor deze filters.' }));
            } else {
                target.appendChild(tableWrap);
            }

            selectAll.addEventListener('change', () => {
                (data.items || []).forEach((item) => {
                    if (selectAll.checked) {
                        state.selected.add(item.id);
                    } else {
                        state.selected.delete(item.id);
                    }
                });
                target.querySelectorAll('tbody input[type="checkbox"]').forEach((checkbox) => {
                    checkbox.checked = selectAll.checked;
                });
                updateBulkToolbar(options);
            });

            const pager = element('div', { class: 'mvm-hub4__pager' });
            pager.appendChild(element('span', { text: `${data.total || 0} bronnen · pagina ${data.page || 1} van ${data.totalPages || 1}` }));
            const pagerButtons = element('div', { class: 'mvm-hub4__pager-buttons' });
            const previous = button('Vorige', async () => {
                if (state.sourcePage > 1) {
                    state.sourcePage -= 1;
                    state.selected.clear();
                    updateBulkToolbar(options);
                    await loadSourceTable(options);
                }
            }, true);
            previous.disabled = state.sourcePage <= 1;
            const next = button('Volgende', async () => {
                if (state.sourcePage < (data.totalPages || 1)) {
                    state.sourcePage += 1;
                    state.selected.clear();
                    updateBulkToolbar(options);
                    await loadSourceTable(options);
                }
            }, true);
            next.disabled = state.sourcePage >= (data.totalPages || 1);
            pagerButtons.appendChild(previous);
            pagerButtons.appendChild(next);
            pager.appendChild(pagerButtons);
            target.appendChild(pager);
            target.setAttribute('aria-busy', 'false');
        } catch (error) {
            if (error.name === 'AbortError') {
                return;
            }
            clear(target);
            target.appendChild(element('div', { class: 'mvm-hub4__empty', text: error.message }));
            target.setAttribute('aria-busy', 'false');
        }
    };

    const renderSources = async () => {
        renderLoading('Bronnenmodule laden…');
        try {
            const options = await ensureSourceOptions();
            clear(container);
            const section = element('section', { class: 'mvm-hub4__section' });
            const head = element('div', { class: 'mvm-hub4__section-head' });
            const intro = element('div');
            intro.appendChild(element('h2', { text: 'Handmatige bronnencontrole' }));
            intro.appendChild(element('p', { text: 'Geen scraping: open de bron, beoordeel hem en vink hem individueel of in bulk af.' }));
            head.appendChild(intro);
            section.appendChild(head);

            const filters = element('div', { class: 'mvm-hub4__filters' });
            const searchField = element('div', { class: 'mvm-hub4__field' });
            searchField.appendChild(element('label', { for: 'mvm-source-search', text: 'Zoeken' }));
            const search = element('input', { id: 'mvm-source-search', type: 'search', value: state.sourceFilters.search, placeholder: 'Zoek op bronnaam of URL…', autocomplete: 'off' });
            searchField.appendChild(search);
            filters.appendChild(searchField);
            filters.appendChild(selectField('Planning', 'due', [
                { value: 'overdue', label: 'Achterstallig' },
                { value: 'today', label: 'Vandaag' },
                { value: 'week', label: 'Deze week' },
                { value: 'later', label: 'Later' },
                { value: 'unscheduled', label: 'Handmatig / geen datum' }
            ], 'Alle planningen'));
            filters.appendChild(selectField('Categorie', 'category', options.categories || [], 'Alle categorieën'));
            filters.appendChild(selectField('Frequentie', 'frequency', options.frequencies || [], 'Alle frequenties'));
            filters.appendChild(selectField('Ingericht', 'configured', [
                { value: 'yes', label: 'Ingericht' },
                { value: 'no', label: 'Nog niet ingericht' }
            ], 'Alle bronnen'));
            section.appendChild(filters);
            section.appendChild(buildBulkToolbar(options));
            section.appendChild(element('div', { 'data-source-results': '1' }));
            container.appendChild(section);
            finishRender();

            let searchTimer = null;
            search.addEventListener('input', () => {
                window.clearTimeout(searchTimer);
                searchTimer = window.setTimeout(() => {
                    state.sourceFilters.search = search.value.trim();
                    state.sourcePage = 1;
                    state.selected.clear();
                    updateBulkToolbar(options);
                    loadSourceTable(options);
                }, 250);
            });

            filters.querySelectorAll('[data-source-filter]').forEach((select) => {
                select.addEventListener('change', () => {
                    state.sourceFilters[select.dataset.sourceFilter] = select.value;
                    state.sourcePage = 1;
                    state.selected.clear();
                    updateBulkToolbar(options);
                    loadSourceTable(options);
                });
            });

            await loadSourceTable(options);
        } catch (error) {
            clear(container);
            container.appendChild(element('div', { class: 'mvm-hub4__empty', text: error.message }));
            finishRender();
        }
    };

    const renderView = async (view) => {
        setStatus('');
        if (view === 'today') {
            await renderToday();
        } else if (view === 'sources') {
            await renderSources();
        } else if (view === 'news') {
            placeholderView('Nieuws', 'De nieuwe nieuwsredactie wordt hier modulair opgebouwd.', [
                'Nieuw artikel en concepten',
                'Review en goedkeuring',
                'Planning en publicatiestatus',
                'Nieuws-inzendingen en signalen',
                'Facebook-distributiecheck na publicatie'
            ]);
        } else if (view === 'agenda') {
            placeholderView('Agenda', 'Evenementen en redactionele planning komen samen in één overzicht.', [
                'Komende evenementen',
                'Publicatiekalender',
                'Deadlines en opdrachten',
                'Geen dubbele agenda-administratie'
            ]);
        } else if (view === 'team') {
            placeholderView('Team', 'Opdrachten, interne communicatie en rollen worden eenvoudiger samengebracht.', [
                'Mijn opdrachten',
                'Teamoverzicht',
                'Interne berichten',
                'Rolgerichte werkinstructies'
            ]);
        } else {
            placeholderView('Meer', 'Beheer en specialistische tools zonder het hoofdmenu te overladen.', [
                'Werkplaats met Elementor, PostX en andere toegestane tools',
                'MvM Mail',
                'Encyclopedie',
                'Forum',
                'Systeemcontrole',
                'Auditlog voor bevoegde rollen'
            ]);
        }
    };

    const setView = async (view, updateUrl = true) => {
        if (!labels[view]) {
            view = 'today';
        }
        state.view = view;
        titleNode.textContent = labels[view];
        navButtons.forEach((nav) => {
            const active = nav.dataset.mvmView === view;
            nav.classList.toggle('is-active', active);
            nav.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
        if (updateUrl && window.history && window.history.replaceState) {
            const url = new URL(config.baseUrl, window.location.origin);
            if (view !== 'today') {
                url.searchParams.set('view', view);
            }
            window.history.replaceState({ view }, '', url.toString());
        }
        await renderView(view);
        document.getElementById('mvm-hub4-main')?.focus({ preventScroll: true });
    };

    navButtons.forEach((nav) => {
        nav.addEventListener('click', () => setView(nav.dataset.mvmView || 'today'));
    });

    window.addEventListener('popstate', (event) => {
        const view = event.state?.view || new URL(window.location.href).searchParams.get('view') || 'today';
        setView(view, false);
    });

    const initialView = new URL(window.location.href).searchParams.get('view') || 'today';
    setView(initialView, false);
})();
