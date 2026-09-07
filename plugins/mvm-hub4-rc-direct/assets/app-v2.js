(() => {
    'use strict';

    const config = window.MvMHub4Config || {};
    const container = document.querySelector('[data-mvm-view-container]');
    const titleNode = document.querySelector('[data-mvm-view-title]');
    const statusNode = document.querySelector('[data-mvm-status]');
    const navButtons = Array.from(document.querySelectorAll('[data-mvm-view]'));
    const mainNode = document.getElementById('mvm-hub4-main');

    if (!container || !titleNode || !statusNode || !config.restRoot || !config.restNonce) {
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
    const allowedViews = new Set(navButtons.map((item) => item.dataset.mvmView).filter(Boolean));

    const state = {
        view: 'today',
        statusData: null,
        sourceOptions: null,
        sourceController: null,
        sourcePage: 1,
        selectedSources: new Set(),
        sourceFilters: {
            search: '',
            due: '',
            category: '',
            frequency: '',
            status: '',
            configured: ''
        },
        auditPage: 1,
        auditFilters: { event: '', result: '' },
        sessionRedirecting: false,
        lastUserActivity: Date.now(),
        lastSessionTouch: Date.now(),
        loadedAt: Date.now()
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

    const redirectToLogin = (message = 'Je Hub-sessie is verlopen. Meld opnieuw aan.') => {
        if (state.sessionRedirecting) {
            return;
        }
        state.sessionRedirecting = true;
        setStatus(message, 'error');
        window.setTimeout(() => {
            window.location.assign(config.loginUrl || '/hub/');
        }, 900);
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
            cache: 'no-store',
            headers
        });

        let json = null;
        try {
            json = await response.json();
        } catch (error) {
            if (response.status === 401) {
                redirectToLogin();
            }
            throw new Error('De Hub gaf geen geldig antwoord.');
        }

        if (!response.ok) {
            const message = json && json.message ? json.message : 'De actie kon niet worden uitgevoerd.';
            if (response.status === 401) {
                redirectToLogin(message);
            }
            const error = new Error(message);
            error.status = response.status;
            throw error;
        }

        return json;
    };

    const button = (label, onClick, secondary = false, extraClass = '') => {
        const node = element('button', {
            type: 'button',
            class: `mvm-hub4__button${secondary ? ' mvm-hub4__button--secondary' : ''}${extraClass ? ` ${extraClass}` : ''}`,
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
        return new Intl.DateTimeFormat('nl-NL', { dateStyle: 'medium', timeStyle: 'short' }).format(date);
    };

    const formatEventDate = (dateValue, timeValue = '') => {
        if (!dateValue) {
            return 'Datum nog niet ingevuld';
        }
        const rawDate = String(dateValue).slice(0, 10);
        const date = new Date(`${rawDate}T00:00:00`);
        if (Number.isNaN(date.getTime())) {
            return String(dateValue);
        }
        const formatted = new Intl.DateTimeFormat('nl-NL', { dateStyle: 'medium' }).format(date);
        const time = String(timeValue || '').slice(0, 5);
        return time && time !== '00:00' ? `${formatted} · ${time}` : formatted;
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
        card.appendChild(element('div', { class: 'mvm-hub4__metric' }, [element('strong', { text: String(count) })]));
        card.appendChild(element('p', { text: description }));
        return card;
    };

    const statusChip = (label, status = '') => element('span', {
        class: `mvm-hub4__status-chip${status ? ` mvm-hub4__status-chip--${status}` : ''}`,
        text: label
    });

    const auditedLaunch = async (path, trigger, existingWindow = null) => {
        if (trigger) {
            trigger.disabled = true;
        }
        try {
            const data = await api(path, { method: 'POST', body: '{}' });
            if (!data.url) {
                throw new Error('De beheerlink ontbreekt.');
            }
            if (existingWindow && !existingWindow.closed) {
                existingWindow.location.replace(data.url);
            } else {
                window.location.assign(data.url);
            }
        } catch (error) {
            if (existingWindow && !existingWindow.closed) {
                existingWindow.close();
            }
            setStatus(error.message, 'error');
            if (trigger) {
                trigger.disabled = false;
            }
        }
    };

    const renderToday = async () => {
        renderLoading('Vandaag laden…');
        try {
            const data = await api('status');
            state.statusData = data;
            clear(container);

            const section = element('section', { class: 'mvm-hub4__section' });
            const head = element('div', { class: 'mvm-hub4__section-head' });
            const intro = element('div');
            intro.appendChild(element('h2', { text: 'Wat vraagt vandaag aandacht?' }));
            intro.appendChild(element('p', { text: 'Eén rustig startpunt voor de redactie. Geen automatische nieuws-scraping en geen onnodige paginaverversingen.' }));
            head.appendChild(intro);
            const actions = element('div', { class: 'mvm-hub4__quick-actions' });
            if (allowedViews.has('news')) actions.appendChild(button('Nieuws', () => setView('news')));
            if (allowedViews.has('sources')) actions.appendChild(button('Bronnen', () => setView('sources'), true));
            if (allowedViews.has('agenda')) actions.appendChild(button('Agenda', () => setView('agenda'), true));
            head.appendChild(actions);
            section.appendChild(head);

            section.appendChild(element('div', { class: 'mvm-hub4__grid' }, [
                metricCard('Achterstallige bronnen', data.sources?.overdue || 0, 'Deze bronnen hadden al gecontroleerd moeten zijn.', true),
                metricCard('Vandaag controleren', data.sources?.today || 0, 'Handmatig controleren en daarna afvinken.'),
                metricCard('Komende 7 dagen', data.sources?.thisWeek || 0, 'Bronnen die later deze week aandacht vragen.')
            ]));

            const security = element('article', { class: 'mvm-hub4__card' });
            security.appendChild(element('h2', { text: 'Veilige newsroom' }));
            security.appendChild(element('p', { text: `Auditretentie: ${data.auditRetentionDays || 30} dagen (minimum 14). De Hub gebruikt een server-side idle timeout en slaat geen gevoelige werkdata op in localStorage.` }));
            section.appendChild(security);

            container.appendChild(section);
            finishRender();
            updateSessionIndicator(data.sessionStatus || null);
        } catch (error) {
            renderError(error);
        }
    };

    const renderError = (error) => {
        clear(container);
        container.appendChild(element('div', { class: 'mvm-hub4__empty', text: error.message || 'Er ging iets mis.' }));
        finishRender();
    };

    const checklistInputKeys = {
        source_verified: 'sourceVerified',
        facts_verified: 'factsVerified',
        facebook_ready: 'facebookReady',
        second_review: 'secondReview'
    };

    const renderNewsChecklist = async (target, article) => {
        target.hidden = false;
        target.setAttribute('aria-busy', 'true');
        clear(target);
        target.appendChild(element('div', { class: 'mvm-hub4__loading', text: 'Publicatiecontrole laden…' }));
        target.scrollIntoView({ block: 'nearest' });

        const paint = (summary) => {
            clear(target);
            const header = element('div', { class: 'mvm-hub4__checklist-head' });
            const intro = element('div');
            intro.appendChild(element('p', { class: 'mvm-hub4__eyebrow', text: 'Publicatiecontrole' }));
            intro.appendChild(element('h2', { text: article.title }));
            intro.appendChild(element('p', {
                text: (summary.done || 0) + ' van ' + (summary.required || 0) + ' controles afgerond'
            }));
            header.appendChild(intro);
            header.appendChild(button('Sluiten', () => {
                target.hidden = true;
                clear(target);
            }, true));
            target.appendChild(header);

            target.appendChild(element('progress', {
                class: 'mvm-hub4__checklist-progress',
                max: Math.max(1, summary.required || 0),
                value: summary.done || 0,
                'aria-label': 'Voortgang publicatiecontrole'
            }));

            const list = element('div', { class: 'mvm-hub4__checklist-items' });
            (summary.items || []).forEach((check) => {
                const row = element('label', { class: 'mvm-hub4__checklist-item' });
                const editable = check.mode === 'manual' && Boolean(summary.canUpdate);
                const checkbox = element('input', {
                    type: 'checkbox',
                    checked: Boolean(check.done),
                    disabled: !editable,
                    'aria-label': check.label
                });
                row.appendChild(checkbox);
                const copy = element('span');
                copy.appendChild(element('strong', { text: check.label }));
                copy.appendChild(element('small', {
                    text: check.mode === 'automatic'
                        ? 'Automatisch gecontroleerd vanuit het artikel'
                        : (editable ? 'Handmatige redactiecontrole' : 'Alleen-lezen voor jouw rol')
                }));
                row.appendChild(copy);
                row.appendChild(element('span', {
                    class: 'mvm-hub4__checklist-result' + (check.done ? ' is-done' : ''),
                    text: check.done ? 'Gereed' : 'Open'
                }));

                if (editable && checklistInputKeys[check.id]) {
                    checkbox.addEventListener('change', async () => {
                        checkbox.disabled = true;
                        setStatus('Controlelijst opslaan…');
                        try {
                            const payload = {};
                            payload[checklistInputKeys[check.id]] = checkbox.checked;
                            const updated = await api('news/' + article.id + '/checklist', {
                                method: 'POST',
                                body: JSON.stringify(payload)
                            });
                            paint(updated);
                            setStatus('Controlelijst opgeslagen.', 'success');
                        } catch (error) {
                            setStatus(error.message, 'error');
                            try {
                                paint(await api('news/' + article.id + '/checklist'));
                            } catch (refreshError) {
                                clear(target);
                                target.appendChild(element('div', { class: 'mvm-hub4__empty-small', text: refreshError.message }));
                            }
                        }
                    });
                }
                list.appendChild(row);
            });
            target.appendChild(list);

            if (!summary.canUpdate) {
                target.appendChild(element('p', {
                    class: 'mvm-hub4__muted',
                    text: 'Je kunt de controles bekijken. Alleen bevoegde redacteuren kunnen handmatige controles wijzigen.'
                }));
            }
            target.setAttribute('aria-busy', 'false');
            target.focus({ preventScroll: true });
        };

        try {
            paint(await api('news/' + article.id + '/checklist'));
        } catch (error) {
            clear(target);
            const header = element('div', { class: 'mvm-hub4__checklist-head' });
            header.appendChild(element('h2', { text: 'Publicatiecontrole' }));
            header.appendChild(button('Sluiten', () => {
                target.hidden = true;
                clear(target);
            }, true));
            target.appendChild(header);
            target.appendChild(element('div', { class: 'mvm-hub4__empty-small', text: error.message }));
            target.setAttribute('aria-busy', 'false');
        }
    };

    const renderNews = async () => {
        renderLoading('Nieuwsredactie laden…');
        try {
            const data = await api('news?limit=50');
            const articles = Array.isArray(data.items) ? data.items : [];
            clear(container);

            const section = element('section', { class: 'mvm-hub4__section' });
            const head = element('div', { class: 'mvm-hub4__section-head' });
            const intro = element('div');
            intro.appendChild(element('h2', { text: 'Nieuwsredactie' }));
            intro.appendChild(element('p', { text: 'Zoek, controleer en open artikelen vanuit één veilige redactieflow. Artikelinhoud wordt pas geladen in de editor.' }));
            head.appendChild(intro);
            if (data.permissions?.canCreate) {
                let newButton = null;
                newButton = button('Nieuw artikel', () => auditedLaunch('tools/news_new/open', newButton));
                head.appendChild(newButton);
            }
            section.appendChild(head);

            const counts = data.counts || {};
            section.appendChild(element('div', { class: 'mvm-hub4__metrics-5' }, [
                metricCard('Concept', counts.draft || 0, 'Nog in bewerking.'),
                metricCard('Te beoordelen', counts.pending || 0, 'Wacht op redactionele controle.', true),
                metricCard('Ingepland', counts.scheduled || 0, 'Staat klaar voor automatische publicatie.'),
                metricCard('Gepubliceerd', counts.published || 0, 'Live nieuwsberichten.'),
                metricCard('Nieuws-inzendingen', counts.tips || 0, 'Binnengekomen signalen en tips.')
            ]));

            const filters = element('div', { class: 'mvm-hub4__news-filters', role: 'search' });
            const searchField = element('div', { class: 'mvm-hub4__field' });
            searchField.appendChild(element('label', { for: 'mvm-news-search', text: 'Zoek in recente artikelen' }));
            const search = element('input', {
                id: 'mvm-news-search',
                type: 'search',
                placeholder: 'Titel, auteur of status…',
                autocomplete: 'off'
            });
            searchField.appendChild(search);
            filters.appendChild(searchField);

            const statusField = element('div', { class: 'mvm-hub4__field' });
            statusField.appendChild(element('label', { for: 'mvm-news-status', text: 'Status' }));
            const status = element('select', { id: 'mvm-news-status' });
            [
                ['', 'Alle statussen'],
                ['draft', 'Concept'],
                ['pending', 'Te beoordelen'],
                ['future', 'Ingepland'],
                ['publish', 'Gepubliceerd'],
                ['private', 'Privé']
            ].forEach((option) => status.appendChild(element('option', { value: option[0], text: option[1] })));
            statusField.appendChild(status);
            filters.appendChild(statusField);
            section.appendChild(filters);

            const card = element('article', { class: 'mvm-hub4__card' });
            const resultHead = element('div', { class: 'mvm-hub4__news-results-head' });
            resultHead.appendChild(element('h2', { text: 'Artikelen' }));
            const resultCount = element('p', { class: 'mvm-hub4__muted', 'aria-live': 'polite' });
            resultHead.appendChild(resultCount);
            card.appendChild(resultHead);
            const list = element('ul', { class: 'mvm-hub4__list' });
            card.appendChild(list);
            section.appendChild(card);

            const checklist = element('article', {
                class: 'mvm-hub4__card mvm-hub4__checklist',
                'data-news-checklist': '1',
                tabindex: '-1',
                'aria-live': 'polite'
            });
            checklist.hidden = true;
            section.appendChild(checklist);
            container.appendChild(section);

            const renderItems = () => {
                const query = search.value.trim().toLocaleLowerCase('nl-NL');
                const selectedStatus = status.value;
                const visible = articles.filter((item) => {
                    if (selectedStatus && item.status !== selectedStatus) {
                        return false;
                    }
                    if (!query) {
                        return true;
                    }
                    return [item.title, item.author, item.statusLabel]
                        .filter(Boolean)
                        .some((value) => String(value).toLocaleLowerCase('nl-NL').includes(query));
                });

                clear(list);
                visible.forEach((item) => {
                    const li = element('li', { class: 'mvm-hub4__list-item' });
                    const main = element('div', { class: 'mvm-hub4__list-main' });
                    main.appendChild(element('strong', { text: item.title }));
                    main.appendChild(element('div', { class: 'mvm-hub4__list-meta' }, [
                        statusChip(item.statusLabel || item.status, item.status),
                        element('span', { text: 'Gewijzigd ' + formatUtc(item.modifiedUtc) }),
                        item.author ? element('span', { text: 'Door ' + item.author }) : null
                    ]));
                    li.appendChild(main);

                    const actions = element('div', { class: 'mvm-hub4__row-actions mvm-hub4__news-actions' });
                    if (data.permissions?.canViewChecklist) {
                        actions.appendChild(button('Controlelijst', () => renderNewsChecklist(checklist, item), true));
                    }
                    if (item.editUrl) {
                        let editButton = null;
                        editButton = button('Bewerken', () => auditedLaunch('news/' + item.id + '/open', editButton), true);
                        actions.appendChild(editButton);
                    }
                    if (item.viewUrl) {
                        actions.appendChild(element('a', {
                            class: 'mvm-hub4__button mvm-hub4__button--secondary',
                            href: item.viewUrl,
                            target: '_blank',
                            rel: 'noopener noreferrer',
                            text: 'Bekijken ↗'
                        }));
                    }
                    li.appendChild(actions);
                    list.appendChild(li);
                });

                if (!visible.length) {
                    list.appendChild(element('li', {
                        class: 'mvm-hub4__empty-small',
                        text: articles.length
                            ? 'Geen artikelen gevonden met deze filters.'
                            : 'Er zijn nog geen nieuwsitems in de actieve redactieflow.'
                    }));
                }
                resultCount.textContent = visible.length + (visible.length === 1 ? ' artikel' : ' artikelen') + ' getoond';
            };

            search.addEventListener('input', renderItems);
            status.addEventListener('change', renderItems);
            renderItems();
            finishRender();
        } catch (error) {
            renderError(error);
        }
    };

    const renderAgenda = async () => {
        renderLoading('Agenda laden…');
        try {
            const data = await api('agenda?limit=30');
            clear(container);
            const section = element('section', { class: 'mvm-hub4__section' });
            const intro = element('div', { class: 'mvm-hub4__section-head' });
            const text = element('div');
            text.appendChild(element('h2', { text: 'Agenda & planning' }));
            text.appendChild(element('p', { text: 'Aankomende evenementen en ingeplande nieuwsberichten, zonder dubbele agenda-administratie.' }));
            intro.appendChild(text);
            section.appendChild(intro);

            const columns = element('div', { class: 'mvm-hub4__two-column' });
            const eventsCard = element('article', { class: 'mvm-hub4__card' });
            eventsCard.appendChild(element('h2', { text: 'Aankomende evenementen' }));
            const events = element('ul', { class: 'mvm-hub4__list' });
            (data.events || []).forEach((item) => {
                const li = element('li', { class: 'mvm-hub4__list-item' });
                const main = element('div', { class: 'mvm-hub4__list-main' });
                main.appendChild(element('strong', { text: item.title }));
                main.appendChild(element('div', { class: 'mvm-hub4__list-meta' }, [
                    element('span', { text: formatEventDate(item.startDate, item.startTime) }),
                    item.location ? element('span', { text: item.location }) : null,
                    statusChip(item.statusLabel || item.status, item.status)
                ]));
                li.appendChild(main);
                if (item.editUrl) {
                    let editButton = null;
                    editButton = button('Beheren', () => auditedLaunch(`agenda/event/${item.id}/open`, editButton), true);
                    li.appendChild(editButton);
                }
                events.appendChild(li);
            });
            if (!(data.events || []).length) events.appendChild(element('li', { class: 'mvm-hub4__empty-small', text: 'Geen aankomende evenementen gevonden.' }));
            eventsCard.appendChild(events);
            columns.appendChild(eventsCard);

            const scheduleCard = element('article', { class: 'mvm-hub4__card' });
            scheduleCard.appendChild(element('h2', { text: 'Ingepland nieuws' }));
            const scheduled = element('ul', { class: 'mvm-hub4__list' });
            (data.scheduledNews || []).forEach((item) => {
                const li = element('li', { class: 'mvm-hub4__list-item' });
                const main = element('div', { class: 'mvm-hub4__list-main' });
                main.appendChild(element('strong', { text: item.title }));
                main.appendChild(element('div', { class: 'mvm-hub4__list-meta' }, [element('span', { text: formatUtc(item.dateUtc) })]));
                li.appendChild(main);
                let editButton = null;
                editButton = button('Bewerken', () => auditedLaunch(`news/${item.id}/open`, editButton), true);
                li.appendChild(editButton);
                scheduled.appendChild(li);
            });
            if (!(data.scheduledNews || []).length) scheduled.appendChild(element('li', { class: 'mvm-hub4__empty-small', text: 'Er staat momenteel geen nieuwsbericht gepland.' }));
            scheduleCard.appendChild(scheduled);
            columns.appendChild(scheduleCard);
            section.appendChild(columns);
            container.appendChild(section);
            finishRender();
        } catch (error) {
            renderError(error);
        }
    };

    const renderTeam = async () => {
        renderLoading('Team laden…');
        try {
            const data = await api('team');
            clear(container);
            const section = element('section', { class: 'mvm-hub4__section' });
            const head = element('div', { class: 'mvm-hub4__section-head' });
            const intro = element('div');
            intro.appendChild(element('h2', { text: 'Staf & rollen' }));
            intro.appendChild(element('p', { text: 'Alleen functionele gegevens: naam en MvM-rol. E-mailadressen en profielgegevens worden hier niet onnodig verspreid.' }));
            head.appendChild(intro);
            section.appendChild(head);

            const counts = element('div', { class: 'mvm-hub4__toolbar' });
            counts.appendChild(element('strong', { text: `${data.total || 0} stafleden` }));
            Object.entries(data.counts || {}).forEach(([role, count]) => counts.appendChild(statusChip(`${role}: ${count}`)));
            section.appendChild(counts);

            const grid = element('div', { class: 'mvm-hub4__team-grid' });
            (data.items || []).forEach((item) => {
                const card = element('article', { class: 'mvm-hub4__person' });
                const initial = String(item.displayName || '?').trim().charAt(0).toUpperCase() || '?';
                card.appendChild(element('span', { class: 'mvm-hub4__person-avatar', text: initial }));
                const info = element('div', { class: 'mvm-hub4__person-info' });
                info.appendChild(element('strong', { text: `${item.displayName}${item.isCurrent ? ' (jij)' : ''}` }));
                info.appendChild(element('span', { text: (item.roles || []).join(' · ') }));
                card.appendChild(info);
                grid.appendChild(card);
            });
            section.appendChild(grid);
            container.appendChild(section);
            finishRender();
        } catch (error) {
            renderError(error);
        }
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
            if (state.sourceFilters[key] === option.value) node.selected = true;
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

    const updateBulkToolbar = (options) => {
        const toolbar = container.querySelector('[data-source-bulk]');
        if (!toolbar) return;
        toolbar.hidden = state.selectedSources.size < 1;
        const count = toolbar.querySelector('[data-source-selected-count]');
        if (count) count.textContent = `${state.selectedSources.size} geselecteerd`;
    };

    const renderSourceRow = (item, options) => {
        const row = element('tr');
        const checkbox = element('input', { type: 'checkbox', 'aria-label': `Selecteer ${item.title}`, checked: state.selectedSources.has(item.id) });
        checkbox.addEventListener('change', () => {
            if (checkbox.checked) state.selectedSources.add(item.id); else state.selectedSources.delete(item.id);
            updateBulkToolbar(options);
        });
        row.appendChild(element('td', {}, checkbox));

        const titleCell = element('td');
        const titleWrap = element('div', { class: 'mvm-hub4__source-title' });
        titleWrap.appendChild(element('strong', { text: item.title }));
        const links = element('div', { class: 'mvm-hub4__source-links' });
        if (item.externalUrl) links.appendChild(element('a', { href: item.externalUrl, target: '_blank', rel: 'noopener noreferrer', text: 'Open bron ↗' }));
        if (item.sourcePageUrl) links.appendChild(element('a', { href: item.sourcePageUrl, target: '_blank', rel: 'noopener noreferrer', text: 'Bronrecord' }));
        titleWrap.appendChild(links);
        titleCell.appendChild(titleWrap);
        row.appendChild(titleCell);
        row.appendChild(element('td', {}, item.configured ? element('span', { class: 'mvm-hub4__badge', text: item.categoryLabel }) : element('span', { class: 'mvm-hub4__muted', text: 'Nog instellen' })));
        row.appendChild(element('td', { text: item.configured ? item.frequencyLabel : '—' }));
        row.appendChild(element('td', {}, sourceDueBadge(item)));
        const last = element('td');
        last.appendChild(element('div', { text: formatUtc(item.lastCheckedUtc) }));
        if (item.lastCheckedName) last.appendChild(element('div', { class: 'mvm-hub4__muted', text: item.lastCheckedName }));
        row.appendChild(last);
        row.appendChild(element('td', { text: formatUtc(item.nextCheckUtc) }));
        const actions = element('td');
        if (options.permissions?.canCheck) {
            let checkButton = null;
            checkButton = button('Gecontroleerd', async () => {
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
            actions.appendChild(checkButton);
        }
        row.appendChild(actions);
        return row;
    };

    const buildBulkToolbar = (options) => {
        const toolbar = element('div', { class: 'mvm-hub4__toolbar', 'data-source-bulk': '1' });
        toolbar.hidden = state.selectedSources.size < 1;
        toolbar.appendChild(element('strong', { 'data-source-selected-count': '1', text: `${state.selectedSources.size} geselecteerd` }));

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
            addSelect('monitorEnabled', [{ value: '1', label: 'Controle inschakelen' }, { value: '0', label: 'Controle uitschakelen' }], 'Controleplanning…');

            let applyButton = null;
            applyButton = button('Toepassen', async () => {
                const payload = { ids: Array.from(state.selectedSources) };
                toolbar.querySelectorAll('[data-bulk-field]').forEach((select) => {
                    if (!select.value) return;
                    if (select.dataset.bulkField === 'monitorEnabled') payload.monitorEnabled = select.value === '1';
                    else payload[select.dataset.bulkField] = select.value;
                });
                if (Object.keys(payload).length === 1) {
                    setStatus('Kies eerst wat je wilt wijzigen.', 'error');
                    return;
                }
                applyButton.disabled = true;
                try {
                    await api('sources/bulk-update', { method: 'POST', body: JSON.stringify(payload) });
                    state.selectedSources.clear();
                    setStatus('De geselecteerde bronnen zijn bijgewerkt.', 'success');
                    await loadSourceTable(options);
                } catch (error) {
                    setStatus(error.message, 'error');
                    applyButton.disabled = false;
                }
            });
            toolbar.appendChild(applyButton);
        }

        if (options.permissions?.canCheck) {
            let checkButton = null;
            checkButton = button('Markeer gecontroleerd', async () => {
                checkButton.disabled = true;
                try {
                    await api('sources/bulk-check', { method: 'POST', body: JSON.stringify({ ids: Array.from(state.selectedSources) }) });
                    state.selectedSources.clear();
                    setStatus('De geselecteerde bronnen zijn gecontroleerd.', 'success');
                    await loadSourceTable(options);
                } catch (error) {
                    setStatus(error.message, 'error');
                    checkButton.disabled = false;
                }
            }, true);
            toolbar.appendChild(checkButton);
        }
        return toolbar;
    };

    const loadSourceTable = async (options) => {
        const target = container.querySelector('[data-source-results]');
        if (!target) return;
        if (state.sourceController) state.sourceController.abort();
        state.sourceController = new AbortController();
        target.setAttribute('aria-busy', 'true');
        clear(target);
        target.appendChild(element('div', { class: 'mvm-hub4__loading', text: 'Bronnen laden…' }));

        const params = new URLSearchParams({ page: String(state.sourcePage), per_page: '50' });
        Object.entries(state.sourceFilters).forEach(([key, value]) => { if (value) params.set(key, value); });

        try {
            const data = await api(`sources?${params.toString()}`, { signal: state.sourceController.signal });
            clear(target);
            if (!data.items || !data.items.length) {
                target.appendChild(element('div', { class: 'mvm-hub4__empty', text: 'Geen bronnen gevonden voor deze filters.' }));
            } else {
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
                data.items.forEach((item) => tbody.appendChild(renderSourceRow(item, options)));
                table.appendChild(tbody);
                tableWrap.appendChild(table);
                target.appendChild(tableWrap);

                selectAll.addEventListener('change', () => {
                    data.items.forEach((item) => { if (selectAll.checked) state.selectedSources.add(item.id); else state.selectedSources.delete(item.id); });
                    target.querySelectorAll('tbody input[type="checkbox"]').forEach((checkbox) => { checkbox.checked = selectAll.checked; });
                    updateBulkToolbar(options);
                });
            }

            const pager = element('div', { class: 'mvm-hub4__pager' });
            pager.appendChild(element('span', { text: `${data.total || 0} bronnen · pagina ${data.page || 1} van ${data.totalPages || 1}` }));
            const pagerButtons = element('div', { class: 'mvm-hub4__pager-buttons' });
            const previous = button('Vorige', async () => {
                if (state.sourcePage > 1) {
                    state.sourcePage -= 1;
                    state.selectedSources.clear();
                    updateBulkToolbar(options);
                    await loadSourceTable(options);
                }
            }, true);
            previous.disabled = state.sourcePage <= 1;
            const next = button('Volgende', async () => {
                if (state.sourcePage < (data.totalPages || 1)) {
                    state.sourcePage += 1;
                    state.selectedSources.clear();
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
            if (error.name === 'AbortError') return;
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
            intro.appendChild(element('p', { text: 'Geen scraping: open de bron, beoordeel hem en vink hem individueel of in bulk af. De lijst blijft op zijn plek.' }));
            head.appendChild(intro);
            section.appendChild(head);

            const filters = element('div', { class: 'mvm-hub4__filters' });
            const searchField = element('div', { class: 'mvm-hub4__field' });
            searchField.appendChild(element('label', { for: 'mvm-source-search', text: 'Zoeken' }));
            const search = element('input', { id: 'mvm-source-search', type: 'search', value: state.sourceFilters.search, placeholder: 'Zoek op bronnaam of URL…', autocomplete: 'off' });
            searchField.appendChild(search);
            filters.appendChild(searchField);
            filters.appendChild(selectField('Planning', 'due', [
                { value: 'overdue', label: 'Achterstallig' }, { value: 'today', label: 'Vandaag' }, { value: 'week', label: 'Deze week' }, { value: 'later', label: 'Later' }, { value: 'unscheduled', label: 'Handmatig / geen datum' }
            ], 'Alle planningen'));
            filters.appendChild(selectField('Categorie', 'category', options.categories || [], 'Alle categorieën'));
            filters.appendChild(selectField('Frequentie', 'frequency', options.frequencies || [], 'Alle frequenties'));
            filters.appendChild(selectField('Status', 'status', options.statuses || [], 'Alle statussen'));
            filters.appendChild(selectField('Ingericht', 'configured', [{ value: 'yes', label: 'Ingericht' }, { value: 'no', label: 'Nog niet ingericht' }], 'Alle bronnen'));
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
                    state.selectedSources.clear();
                    updateBulkToolbar(options);
                    loadSourceTable(options);
                }, 300);
            });
            filters.querySelectorAll('[data-source-filter]').forEach((select) => {
                select.addEventListener('change', () => {
                    state.sourceFilters[select.dataset.sourceFilter] = select.value;
                    state.sourcePage = 1;
                    state.selectedSources.clear();
                    updateBulkToolbar(options);
                    loadSourceTable(options);
                });
            });
            await loadSourceTable(options);
        } catch (error) {
            renderError(error);
        }
    };

    const openTool = async (tool) => {
        let popup = null;
        if (tool.target === 'new') {
            popup = window.open('about:blank', '_blank');
            if (popup) popup.opener = null;
        }
        try {
            const resolved = await api(`tools/${encodeURIComponent(tool.id)}/open`, { method: 'POST', body: '{}' });
            if (!resolved.url) throw new Error('De werkplaatslink ontbreekt.');
            if (popup && !popup.closed) popup.location.replace(resolved.url);
            else window.location.assign(resolved.url);
        } catch (error) {
            if (popup && !popup.closed) popup.close();
            setStatus(error.message, 'error');
        }
    };

    const renderAudit = async (target) => {
        target.setAttribute('aria-busy', 'true');
        clear(target);
        target.appendChild(element('div', { class: 'mvm-hub4__loading', text: 'Auditlog laden…' }));
        const params = new URLSearchParams({ page: String(state.auditPage), per_page: '50' });
        if (state.auditFilters.event) params.set('event', state.auditFilters.event);
        if (state.auditFilters.result) params.set('result', state.auditFilters.result);
        try {
            const data = await api(`audit?${params.toString()}`);
            clear(target);
            const filters = element('div', { class: 'mvm-hub4__audit-filters' });
            const eventInput = element('input', { type: 'search', value: state.auditFilters.event, placeholder: 'Event-code, exact…', 'aria-label': 'Filter audit event-code' });
            const resultSelect = element('select', { 'aria-label': 'Filter audit resultaat' });
            [['', 'Alle resultaten'], ['success', 'Geslaagd'], ['denied', 'Geweigerd'], ['error', 'Fout'], ['cancelled', 'Geannuleerd']].forEach(([value, label]) => {
                const option = element('option', { value, text: label });
                if (state.auditFilters.result === value) option.selected = true;
                resultSelect.appendChild(option);
            });
            const apply = button('Filter', () => {
                state.auditFilters.event = eventInput.value.trim();
                state.auditFilters.result = resultSelect.value;
                state.auditPage = 1;
                renderAudit(target);
            });
            filters.appendChild(eventInput);
            filters.appendChild(resultSelect);
            filters.appendChild(apply);
            target.appendChild(filters);

            const tableWrap = element('div', { class: 'mvm-hub4__table-wrap' });
            const table = element('table', { class: 'mvm-hub4__table mvm-hub4__table--compact' });
            const thead = element('thead');
            const tr = element('tr');
            ['Tijd', 'Actor', 'Gebeurtenis', 'Object', 'Resultaat', 'Context'].forEach((label) => tr.appendChild(element('th', { text: label })));
            thead.appendChild(tr);
            table.appendChild(thead);
            const tbody = element('tbody');
            (data.items || []).forEach((item) => {
                const row = element('tr');
                row.appendChild(element('td', { text: formatUtc(item.occurred_at_utc) }));
                row.appendChild(element('td', { text: `${item.actor_role || 'onbekend'} · #${item.actor_user_id || 0}` }));
                row.appendChild(element('td', { text: item.event_code || '—' }));
                row.appendChild(element('td', { text: item.object_type ? `${item.object_type}${Number(item.object_id) ? ` #${item.object_id}` : ''}` : '—' }));
                row.appendChild(element('td', {}, statusChip(item.result || '—')));
                const context = item.context && typeof item.context === 'object' ? Object.entries(item.context).map(([k, v]) => `${k}: ${String(v)}`).join(' · ') : '';
                row.appendChild(element('td', { class: 'mvm-hub4__audit-context', text: context || '—' }));
                tbody.appendChild(row);
            });
            table.appendChild(tbody);
            tableWrap.appendChild(table);
            target.appendChild(tableWrap);

            const pager = element('div', { class: 'mvm-hub4__pager' });
            pager.appendChild(element('span', { text: `${data.total || 0} gebeurtenissen · pagina ${data.page || 1} van ${data.totalPages || 1}` }));
            const pagerButtons = element('div', { class: 'mvm-hub4__pager-buttons' });
            const previous = button('Vorige', () => { if (state.auditPage > 1) { state.auditPage -= 1; renderAudit(target); } }, true);
            previous.disabled = state.auditPage <= 1;
            const next = button('Volgende', () => { if (state.auditPage < (data.totalPages || 1)) { state.auditPage += 1; renderAudit(target); } }, true);
            next.disabled = state.auditPage >= (data.totalPages || 1);
            pagerButtons.appendChild(previous);
            pagerButtons.appendChild(next);
            pager.appendChild(pagerButtons);
            target.appendChild(pager);
            target.setAttribute('aria-busy', 'false');
        } catch (error) {
            clear(target);
            target.appendChild(element('div', { class: 'mvm-hub4__empty-small', text: error.message }));
            target.setAttribute('aria-busy', 'false');
        }
    };

    const renderMore = async () => {
        renderLoading('Werkplaats laden…');
        try {
            const status = state.statusData || await api('status');
            state.statusData = status;
            clear(container);
            const section = element('section', { class: 'mvm-hub4__section' });
            const head = element('div', { class: 'mvm-hub4__section-head' });
            const intro = element('div');
            intro.appendChild(element('h2', { text: 'Werkplaats & beheer' }));
            intro.appendChild(element('p', { text: 'Specialistische functies staan hier, zodat het hoofdmenu rustig blijft. Elke beheerlaunch wordt server-side gecontroleerd en gelogd.' }));
            head.appendChild(intro);
            section.appendChild(head);

            if (status.permissions?.tools) {
                const data = await api('tools');
                const groups = new Map();
                (data.items || []).forEach((tool) => {
                    const key = tool.category || 'Overig';
                    if (!groups.has(key)) groups.set(key, []);
                    groups.get(key).push(tool);
                });
                const groupsNode = element('div', { class: 'mvm-hub4__tool-groups' });
                groups.forEach((tools, category) => {
                    const group = element('section', { class: 'mvm-hub4__tool-group' });
                    group.appendChild(element('h3', { text: category }));
                    const grid = element('div', { class: 'mvm-hub4__tool-grid' });
                    tools.forEach((tool) => {
                        const toolButton = element('button', { type: 'button', class: 'mvm-hub4__tool' });
                        toolButton.appendChild(element('strong', { text: tool.title }));
                        toolButton.appendChild(element('span', { text: tool.description }));
                        toolButton.appendChild(element('em', { text: tool.target === 'new' ? 'Open in nieuw venster →' : 'Open beheer →' }));
                        toolButton.addEventListener('click', () => openTool(tool));
                        grid.appendChild(toolButton);
                    });
                    group.appendChild(grid);
                    groupsNode.appendChild(group);
                });
                section.appendChild(groupsNode);
            } else {
                section.appendChild(element('div', { class: 'mvm-hub4__empty-small', text: 'Voor jouw rol zijn geen specialistische beheertools nodig.' }));
            }

            if (status.permissions?.audit) {
                section.appendChild(element('hr', { class: 'mvm-hub4__section-divider' }));
                const auditHead = element('div', { class: 'mvm-hub4__section-head' });
                const auditIntro = element('div');
                auditIntro.appendChild(element('h2', { text: 'Auditlog' }));
                auditIntro.appendChild(element('p', { text: `Alle relevante Hub-handelingen worden minimaal 14 dagen bewaard; huidige instelling: ${status.auditRetentionDays || 30} dagen.` }));
                auditHead.appendChild(auditIntro);
                section.appendChild(auditHead);
                const auditTarget = element('div', { 'data-audit-results': '1' });
                section.appendChild(auditTarget);
                container.appendChild(section);
                finishRender();
                await renderAudit(auditTarget);
                return;
            }

            container.appendChild(section);
            finishRender();
        } catch (error) {
            renderError(error);
        }
    };

    const renderView = async (view) => {
        setStatus('');
        if (view === 'today') await renderToday();
        else if (view === 'news') await renderNews();
        else if (view === 'sources') await renderSources();
        else if (view === 'agenda') await renderAgenda();
        else if (view === 'team') await renderTeam();
        else await renderMore();
    };

    const setView = async (view, updateUrl = true, replace = false) => {
        if (!labels[view] || !allowedViews.has(view)) view = allowedViews.has('today') ? 'today' : Array.from(allowedViews)[0];
        if (!view) return;
        state.view = view;
        titleNode.textContent = labels[view] || 'Hub';
        navButtons.forEach((nav) => {
            const active = nav.dataset.mvmView === view;
            nav.classList.toggle('is-active', active);
            nav.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
        if (updateUrl && window.history) {
            const url = new URL(config.baseUrl, window.location.origin);
            if (view !== 'today') url.searchParams.set('view', view);
            const method = replace ? 'replaceState' : 'pushState';
            window.history[method]({ view }, '', url.toString());
        }
        await renderView(view);
        mainNode?.focus({ preventScroll: true });
    };

    const installSessionIndicator = () => {
        const topbar = document.querySelector('.mvm-hub4__topbar');
        const user = document.querySelector('.mvm-hub4__user');
        if (!topbar || !user || topbar.querySelector('[data-mvm-session-indicator]')) return;
        const indicator = element('div', { class: 'mvm-hub4__session', 'data-mvm-session-indicator': '1', title: 'Beveiligde Hub-sessie' });
        indicator.appendChild(element('span', { class: 'mvm-hub4__session-dot', 'aria-hidden': 'true' }));
        indicator.appendChild(element('span', { 'data-mvm-session-text': '1', text: 'Sessie beveiligd' }));
        topbar.insertBefore(indicator, user);
    };

    const updateSessionIndicator = (serverStatus = null) => {
        installSessionIndicator();
        const indicator = document.querySelector('[data-mvm-session-indicator]');
        const text = indicator?.querySelector('[data-mvm-session-text]');
        if (!indicator || !text) return;
        const policy = serverStatus || config.session || {};
        const idleSeconds = Number(policy.idleSeconds || 1800);
        const absoluteRemainingAtLoad = Number((config.session || {}).absoluteRemaining || policy.absoluteRemaining || 28800);
        const idleRemaining = Math.max(0, idleSeconds - Math.floor((Date.now() - state.lastUserActivity) / 1000));
        const absoluteRemaining = Math.max(0, absoluteRemainingAtLoad - Math.floor((Date.now() - state.loadedAt) / 1000));
        const remaining = Math.min(idleRemaining, absoluteRemaining);
        indicator.classList.toggle('is-warning', remaining > 0 && remaining <= 300);
        indicator.classList.toggle('is-expired', remaining <= 0);
        if (remaining <= 0) text.textContent = 'Sessie verlopen';
        else if (remaining <= 300) text.textContent = `Sessie verloopt binnen ${Math.max(1, Math.ceil(remaining / 60))} min`;
        else text.textContent = 'Sessie beveiligd';
    };

    const touchSession = async () => {
        if (state.sessionRedirecting) return;
        try {
            const data = await api('session/touch', { method: 'POST', body: '{}' });
            state.lastSessionTouch = Date.now();
            state.lastUserActivity = Date.now();
            updateSessionIndicator(data.sessionStatus || null);
        } catch (error) {
            if (error.status !== 401) setStatus(error.message, 'error');
        }
    };

    const recordActivity = () => {
        state.lastUserActivity = Date.now();
        updateSessionIndicator();
        if (Date.now() - state.lastSessionTouch >= 4 * 60 * 1000) touchSession();
    };

    ['pointerdown', 'keydown', 'touchstart'].forEach((name) => document.addEventListener(name, recordActivity, { passive: true }));
    window.addEventListener('scroll', () => {
        if (Date.now() - state.lastUserActivity > 15000) recordActivity();
    }, { passive: true });

    window.setInterval(() => {
        updateSessionIndicator();
        const idleSeconds = Number((config.session || {}).idleSeconds || 1800);
        const locallyIdle = Date.now() - state.lastUserActivity;
        if (locallyIdle > (idleSeconds + 5) * 1000) {
            touchSession();
        } else if (locallyIdle < 5 * 60 * 1000 && Date.now() - state.lastSessionTouch >= 4 * 60 * 1000) {
            touchSession();
        }
    }, 60000);

    navButtons.forEach((nav) => nav.addEventListener('click', () => setView(nav.dataset.mvmView || 'today')));
    window.addEventListener('popstate', (event) => {
        const view = event.state?.view || new URL(window.location.href).searchParams.get('view') || 'today';
        setView(view, false);
    });

    installSessionIndicator();
    updateSessionIndicator();
    const initialView = new URL(window.location.href).searchParams.get('view') || 'today';
    setView(initialView, true, true);
})();
