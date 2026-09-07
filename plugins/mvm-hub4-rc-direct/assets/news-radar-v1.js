(() => {
    'use strict';

    const config = window.MvMHub4Config || {};
    const button = document.querySelector('[data-mvm-news-radar-view]');
    const container = document.querySelector('[data-mvm-platform-container]');
    const classicView = document.querySelector('[data-mvm-view-container]');
    const classicNav = Array.from(document.querySelectorAll('[data-mvm-view]'));
    const platformNav = Array.from(document.querySelectorAll('[data-mvm-platform-view]'));
    const workflow = document.querySelector('[data-mvm-workflow-guide]');
    const myWork = document.querySelector('[data-mvm-my-work]');
    const titleNode = document.querySelector('[data-mvm-view-title]');
    const messageNode = document.querySelector('[data-mvm-platform-message]');

    if (!button || !container || !config.restRoot || !config.restNonce) {
        return;
    }

    let active = false;
    let requestToken = 0;
    let canReview = false;

    const element = (tag, attrs = {}, children = []) => {
        const node = document.createElement(tag);
        Object.entries(attrs).forEach(([key, value]) => {
            if (value === null || value === undefined || value === false) return;
            if (key === 'class') node.className = String(value);
            else if (key === 'text') node.textContent = String(value);
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

    const validResultId = (value) => /^[a-f0-9]{64}$/.test(String(value || '').toLowerCase());

    const safeUrl = (value) => {
        if (!value) return '';
        try {
            const parsed = new URL(String(value), window.location.origin);
            return ['http:', 'https:'].includes(parsed.protocol) ? parsed.toString() : '';
        } catch (error) {
            return '';
        }
    };

    const formatDate = (value) => {
        if (!value) return '—';
        const raw = String(value).trim();
        const date = /^\d+$/.test(raw)
            ? new Date(Number(raw) * 1000)
            : new Date(raw.replace(' ', 'T'));
        return Number.isNaN(date.getTime())
            ? raw
            : new Intl.DateTimeFormat('nl-NL', { dateStyle: 'medium', timeStyle: 'short' }).format(date);
    };

    const formatSourceDate = (value) => {
        if (!value) return '—';
        const raw = String(value).trim();
        const date = new Date(raw.replace(' ', 'T'));
        return Number.isNaN(date.getTime())
            ? raw
            : new Intl.DateTimeFormat('nl-NL', { dateStyle: 'medium' }).format(date);
    };

    const api = async (path, options = {}) => {
        const method = options.method || 'GET';
        const url = new URL(String(path).replace(/^\//, ''), config.restRoot);
        const response = await fetch(url.toString(), {
            method,
            credentials: 'same-origin',
            cache: 'no-store',
            headers: {
                'X-WP-Nonce': config.restNonce,
                'Accept': 'application/json',
                ...(options.body ? { 'Content-Type': 'application/json' } : {})
            },
            ...(options.body ? { body: JSON.stringify(options.body) } : {})
        });
        let data = {};
        try {
            data = await response.json();
        } catch (error) {
            throw new Error('De Nieuwsradar gaf geen geldig antwoord.');
        }
        if (!response.ok) {
            throw new Error(data.message || 'De Nieuwsradar kon niet worden geladen.');
        }
        return data;
    };

    const renderAgendaCrawl = (status = {}) => {
        if (status.ownership === 'compatibility' && document.getElementById('mvm-hub-ai-agenda-ui-v1')) {
            return null;
        }
        const section = element('section', { class: 'mvm-news-radar__agenda', 'aria-labelledby': 'mvm-agenda-crawl-title' });
        const heading = element('div', { class: 'mvm-news-radar__agenda-head' });
        const copy = element('div');
        copy.appendChild(element('h3', { id: 'mvm-agenda-crawl-title', text: 'AI-agendacrawl' }));
        copy.appendChild(element('p', { text: 'Controleert per uitvoering maximaal twee van de 118 actieve bronnen. Nieuwe vondsten komen alleen in de redactionele wachtrij; er wordt niets gepubliceerd.' }));
        heading.appendChild(copy);
        heading.appendChild(badge(status.ownership === 'compatibility' ? 'Compatibiliteitsmodus' : 'Hub 4-module', status.providerBlocked ? 'warning' : 'active'));
        section.appendChild(heading);

        const form = element('div', { class: 'mvm-news-radar__agenda-controls' });
        const enabledLabel = element('label', { class: 'mvm-news-radar__agenda-toggle' });
        const enabled = element('input', { type: 'checkbox' });
        enabled.checked = Boolean(status.enabled);
        enabled.disabled = !status?.permissions?.canManage;
        enabledLabel.appendChild(enabled);
        enabledLabel.appendChild(element('span', { text: 'AI-agendacrawl ingeschakeld' }));
        form.appendChild(enabledLabel);

        const sortLabel = element('label', { class: 'mvm-news-radar__agenda-field' });
        sortLabel.appendChild(element('span', { text: 'Radarvolgorde' }));
        const sort = element('select', { disabled: !status?.permissions?.canManage, 'aria-label': 'Nieuwsradar sorteren' });
        [
            ['recent', 'Meest recent'],
            ['relevance', 'Lokale relevantie'],
            ['radar', 'Bestaande Radarvolgorde']
        ].forEach(([value, text]) => sort.appendChild(element('option', { value, text })));
        sort.value = ['recent', 'relevance', 'radar'].includes(status.radarSort) ? status.radarSort : 'recent';
        sortLabel.appendChild(sort);
        form.appendChild(sortLabel);

        const batch = element('div', { class: 'mvm-news-radar__agenda-field' });
        batch.appendChild(element('span', { text: 'Bronnen per uitvoering' }));
        batch.appendChild(element('strong', { text: `${Math.min(Number(status.batch || 2), Number(status.maxBatch || 2))} van maximaal ${status.maxBatch || 2}` }));
        form.appendChild(batch);

        if (status?.permissions?.canManage) {
            const actions = element('div', { class: 'mvm-news-radar__agenda-actions' });
            const save = element('button', { type: 'button', class: 'mvm-news-radar__review-button', text: 'Instellingen opslaan' });
            const run = element('button', { type: 'button', class: 'mvm-news-radar__secondary-button', text: 'Proefrun: 2 bronnen' });
            save.addEventListener('click', async () => {
                save.disabled = true;
                run.disabled = true;
                setMessage('Instellingen van de AI-agendacrawl opslaan…');
                try {
                    await api('agenda-crawl/settings', { method: 'POST', body: { enabled: enabled.checked, batch: 2, radarSort: sort.value } });
                    setMessage('Instellingen zijn opgeslagen.', 'success');
                    await render();
                } catch (error) {
                    save.disabled = false;
                    run.disabled = false;
                    setMessage(error.message, 'error');
                }
            });
            run.addEventListener('click', async () => {
                save.disabled = true;
                run.disabled = true;
                setMessage('Veilige proefrun over maximaal twee bronnen uitvoeren…');
                try {
                    const result = await api('agenda-crawl/run', { method: 'POST' });
                    setMessage(result.message || 'De proefrun is afgerond.', result.paused ? 'warning' : 'success');
                    await render();
                } catch (error) {
                    save.disabled = false;
                    run.disabled = false;
                    setMessage(error.message, 'error');
                }
            });
            actions.appendChild(save);
            actions.appendChild(run);
            form.appendChild(actions);
        }
        section.appendChild(form);

        const facts = element('dl', { class: 'mvm-news-radar__agenda-status', 'aria-live': 'polite' });
        [
            ['Bronbaseline', `${status.activeSources ?? 0} actief / ${status.stoppedDuplicates ?? 0} gestopt`],
            ['Laatste uitvoering', formatDate(status.lastRun)],
            ['Volgende uitvoering', formatDate(status.nextRun)],
            ['AI-wachtrij', `${status.pendingAi ?? 0} item(s) · ${status.providerStatus || 'onbekend'}`]
        ].forEach(([label, value]) => {
            const group = element('div');
            group.appendChild(element('dt', { text: label }));
            group.appendChild(element('dd', { text: value }));
            facts.appendChild(group);
        });
        section.appendChild(facts);
        section.appendChild(element('p', { class: 'mvm-news-radar__agenda-message', text: status.lastMessage || status.providerMessage || 'Nog geen statusbericht.' }));
        return section;
    };

    const markReviewed = async (id) => {
        const normalized = String(id || '').toLowerCase();
        if (!validResultId(normalized)) {
            throw new Error('Dit radaritem heeft geen geldig review-ID.');
        }

        const url = new URL(`news-radar/${encodeURIComponent(normalized)}/reviewed`, config.restRoot);
        const response = await fetch(url.toString(), {
            method: 'POST',
            credentials: 'same-origin',
            cache: 'no-store',
            headers: {
                'X-WP-Nonce': config.restNonce,
                'Accept': 'application/json'
            }
        });
        let data = {};
        try {
            data = await response.json();
        } catch (error) {
            throw new Error('De reviewactie gaf geen geldig antwoord.');
        }
        if (!response.ok) {
            throw new Error(data.message || 'De reviewactie kon niet worden uitgevoerd.');
        }
        return data;
    };

    const badge = (text, modifier = '') => element('span', {
        class: `mvm-news-radar__badge${modifier ? ` mvm-news-radar__badge--${modifier}` : ''}`,
        text
    });

    const sourceLink = (row, label = 'Open bron') => {
        const url = safeUrl(row?.url);
        if (!url) return null;
        return element('a', {
            href: url,
            target: '_blank',
            rel: 'noopener noreferrer',
            class: 'mvm-news-radar__source-link',
            text: label
        });
    };

    const reviewControl = (row) => {
        if (row?.reviewed) {
            const text = row?.reviewed_at ? `Nagekeken · ${formatDate(row.reviewed_at)}` : 'Nagekeken';
            return badge(text, 'reviewed');
        }
        if (!canReview || !validResultId(row?.id)) return null;

        const control = element('button', {
            type: 'button',
            class: 'mvm-news-radar__review-button',
            text: 'Markeer nagekeken'
        });
        control.addEventListener('click', async () => {
            control.disabled = true;
            control.textContent = 'Opslaan…';
            setMessage('Reviewstatus opslaan…');
            try {
                await markReviewed(row.id);
                setMessage('Radaritem is als nagekeken gemarkeerd.', 'success');
                await render();
            } catch (error) {
                control.disabled = false;
                control.textContent = 'Markeer nagekeken';
                setMessage(error.message, 'error');
            }
        });
        return control;
    };

    const rowActions = (row) => {
        const actions = element('div', { class: 'mvm-news-radar__row-actions' });
        const link = sourceLink(row);
        const review = reviewControl(row);
        if (link) actions.appendChild(link);
        if (review) actions.appendChild(review);
        return actions.childElementCount ? actions : null;
    };

    const metaText = (row) => {
        const parts = [];
        if (row?.source) parts.push(String(row.source));
        if (row?.category) parts.push(String(row.category));
        const when = row?.found_at || row?.found_ts || row?.published || row?.published_ts;
        if (when) parts.push(formatDate(when));
        return parts.join(' · ');
    };

    const updateRow = (row) => {
        const item = element('article', { class: 'mvm-news-radar__update' });
        const head = element('div', { class: 'mvm-news-radar__update-head' });
        head.appendChild(element('strong', { text: row?.title || 'Update' }));
        if (row?.event_type) head.appendChild(badge(String(row.event_type)));
        item.appendChild(head);
        const meta = metaText(row);
        if (meta) item.appendChild(element('small', { text: meta }));
        if (row?.change_summary) item.appendChild(element('p', { text: String(row.change_summary) }));
        else if (row?.summary) item.appendChild(element('p', { text: String(row.summary) }));
        const actions = rowActions(row);
        if (actions) item.appendChild(actions);
        return item;
    };

    const subjectCard = (group) => {
        const primary = group?.primary || {};
        const updates = Array.isArray(group?.updates) ? group.updates : [];
        const count = Number(group?.notification_count || (updates.length + 1));
        const card = element('article', { class: 'mvm-news-radar__card' });

        const head = element('div', { class: 'mvm-news-radar__card-head' });
        const copy = element('div', { class: 'mvm-news-radar__card-copy' });
        copy.appendChild(element('h3', { text: primary.title || 'Nieuwsradar-onderwerp' }));
        const meta = metaText(primary);
        if (meta) copy.appendChild(element('small', { text: meta }));
        head.appendChild(copy);

        const badges = element('div', { class: 'mvm-news-radar__badges' });
        if (count > 1) badges.appendChild(badge(`${count} meldingen`, 'count'));
        if (primary.status) badges.appendChild(badge(String(primary.status)));
        if (primary.local_score !== undefined && primary.local_score !== '') {
            badges.appendChild(badge(`Score ${primary.local_score}`));
        }
        head.appendChild(badges);
        card.appendChild(head);

        if (primary.summary) card.appendChild(element('p', { text: String(primary.summary) }));
        else if (primary.excerpt) card.appendChild(element('p', { text: String(primary.excerpt) }));

        const actions = rowActions(primary);
        if (actions) card.appendChild(actions);

        if (updates.length) {
            const details = element('details', { class: 'mvm-news-radar__history' });
            details.appendChild(element('summary', { text: `Updates (${updates.length})` }));
            const history = element('div', { class: 'mvm-news-radar__history-list' });
            updates.forEach((row) => history.appendChild(updateRow(row)));
            details.appendChild(history);
            card.appendChild(details);
        }

        return card;
    };

    const metric = (label, value, note = '') => {
        const card = element('article', { class: 'mvm-news-radar__metric' });
        card.appendChild(element('span', { text: label }));
        card.appendChild(element('strong', { text: String(value ?? 0) }));
        if (note) card.appendChild(element('small', { text: note }));
        return card;
    };

    const priorityRank = (value) => ({ A: 0, B: 1, C: 2 }[String(value || '').toUpperCase()] ?? 99);
    const frequencyRank = (value) => {
        const text = String(value || '').toLowerCase();
        if (text.includes('dag')) return 0;
        if (text.includes('2-3')) return 1;
        if (text.includes('week')) return 2;
        if (text.includes('maand')) return 3;
        return 9;
    };
    const dateRank = (value, emptyValue = 0) => {
        if (!value) return emptyValue;
        const stamp = Date.parse(String(value).replace(' ', 'T'));
        return Number.isNaN(stamp) ? emptyValue : stamp;
    };
    const compareText = (a, b) => String(a || '').localeCompare(String(b || ''), 'nl', { sensitivity: 'base', numeric: true });

    const sortSources = (sources, mode) => {
        const rows = Array.isArray(sources) ? [...sources] : [];
        rows.sort((a, b) => {
            if (mode === 'priority-desc') return priorityRank(b.priority) - priorityRank(a.priority) || compareText(a.name, b.name);
            if (mode === 'name-asc') return compareText(a.name, b.name);
            if (mode === 'name-desc') return compareText(b.name, a.name);
            if (mode === 'frequency') return frequencyRank(a.frequency) - frequencyRank(b.frequency) || priorityRank(a.priority) - priorityRank(b.priority) || compareText(a.name, b.name);
            if (mode === 'checked-desc') return dateRank(b.last_checked) - dateRank(a.last_checked) || priorityRank(a.priority) - priorityRank(b.priority) || compareText(a.name, b.name);
            if (mode === 'checked-asc') return dateRank(a.last_checked, Number.MAX_SAFE_INTEGER) - dateRank(b.last_checked, Number.MAX_SAFE_INTEGER) || priorityRank(a.priority) - priorityRank(b.priority) || compareText(a.name, b.name);
            if (mode === 'next-asc') return dateRank(a.next_check, Number.MAX_SAFE_INTEGER) - dateRank(b.next_check, Number.MAX_SAFE_INTEGER) || priorityRank(a.priority) - priorityRank(b.priority) || compareText(a.name, b.name);
            if (mode === 'next-desc') return dateRank(b.next_check) - dateRank(a.next_check) || priorityRank(a.priority) - priorityRank(b.priority) || compareText(a.name, b.name);
            if (mode === 'arrival-desc') return Number(b.id || 0) - Number(a.id || 0);
            if (mode === 'arrival-asc') return Number(a.id || 0) - Number(b.id || 0);
            return priorityRank(a.priority) - priorityRank(b.priority) || compareText(a.name, b.name);
        });
        return rows;
    };

    const sourceCard = (source) => {
        const row = element('article', { class: 'mvm-news-radar__source-card' });
        const main = element('div', { class: 'mvm-news-radar__source-main' });
        const title = element('div', { class: 'mvm-news-radar__source-title' });
        title.appendChild(element('strong', { text: source?.name || 'Bron' }));
        const labels = element('div', { class: 'mvm-news-radar__badges' });
        if (source?.priority) labels.appendChild(badge(`Prioriteit ${String(source.priority).toUpperCase()}`, `priority-${String(source.priority).toLowerCase()}`));
        labels.appendChild(badge(source?.active === false ? 'Uit' : 'Actief', source?.active === false ? 'inactive' : 'active'));
        title.appendChild(labels);
        main.appendChild(title);
        const meta = [];
        if (source?.category) meta.push(String(source.category));
        if (source?.frequency) meta.push(String(source.frequency));
        main.appendChild(element('small', { text: meta.join(' · ') || 'Geen categorie/frequentie' }));
        const link = sourceLink(source, 'Open bron');
        if (link) main.appendChild(link);
        row.appendChild(main);

        const dates = element('dl', { class: 'mvm-news-radar__source-dates' });
        [
            ['Laatst gecontroleerd', formatSourceDate(source?.last_checked)],
            ['Volgende controle', formatSourceDate(source?.next_check)],
            ['Binnenkomst', source?.id ? `#${source.id}` : '—']
        ].forEach(([label, value]) => {
            const group = element('div');
            group.appendChild(element('dt', { text: label }));
            group.appendChild(element('dd', { text: value }));
            dates.appendChild(group);
        });
        row.appendChild(dates);
        return row;
    };

    const renderSources = (sources) => {
        const section = element('section', { class: 'mvm-news-radar__sources', 'aria-labelledby': 'mvm-news-radar-sources-title' });
        const head = element('div', { class: 'mvm-news-radar__sources-head' });
        const copy = element('div');
        copy.appendChild(element('h3', { id: 'mvm-news-radar-sources-title', text: 'Monitoringbronnen' }));
        copy.appendChild(element('p', { text: 'De crawler verwerkt standaard prioriteit A → B → C. Sorteer de lijst hieronder zonder de brondata te wijzigen.' }));
        head.appendChild(copy);

        const sortWrap = element('label', { class: 'mvm-news-radar__sort' });
        sortWrap.appendChild(element('span', { text: 'Sorteren' }));
        const select = element('select', { 'aria-label': 'Monitoringbronnen sorteren' });
        [
            ['priority-asc', 'Prioriteit: hoog → laag'],
            ['priority-desc', 'Prioriteit: laag → hoog'],
            ['name-asc', 'Naam: A → Z'],
            ['name-desc', 'Naam: Z → A'],
            ['frequency', 'Controlefrequentie: vaakst eerst'],
            ['checked-desc', 'Laatste controle: nieuw → oud'],
            ['checked-asc', 'Laatste controle: oud → nieuw'],
            ['next-asc', 'Volgende controle: eerstvolgende eerst'],
            ['next-desc', 'Volgende controle: laatste eerst'],
            ['arrival-desc', 'Binnenkomst: nieuwste eerst'],
            ['arrival-asc', 'Binnenkomst: oudste eerst']
        ].forEach(([value, text]) => select.appendChild(element('option', { value, text })));
        sortWrap.appendChild(select);
        head.appendChild(sortWrap);
        section.appendChild(head);

        const list = element('div', { class: 'mvm-news-radar__source-list', 'aria-live': 'polite' });
        const paint = () => {
            clear(list);
            const sorted = sortSources(sources, select.value);
            if (!sorted.length) {
                list.appendChild(element('div', { class: 'mvm-news-radar__empty', text: 'Er zijn geen monitoringbronnen beschikbaar.' }));
                return;
            }
            sorted.forEach((source) => list.appendChild(sourceCard(source)));
        };
        select.addEventListener('change', paint);
        paint();
        section.appendChild(list);
        return section;
    };

    const render = async () => {
        const token = ++requestToken;
        clear(container);
        container.setAttribute('aria-busy', 'true');
        container.appendChild(element('div', { class: 'mvm-news-radar__loading', text: 'Nieuwsradar laden…' }));
        setMessage('');

        try {
            const [data, agendaStatus] = await Promise.all([
                api('news-radar?limit=200'),
                api('agenda-crawl/settings')
            ]);
            if (!active || token !== requestToken) return;

            canReview = Boolean(data?.permissions?.canReview);
            clear(container);
            const header = element('div', { class: 'mvm-news-radar__toolbar' });
            const heading = element('div');
            heading.appendChild(element('h2', { text: 'Nieuwsradar' }));
            heading.appendChild(element('p', { text: 'Gegroepeerde meldingen uit de bestaande Radarqueue, aangevuld met bronvaste agenda-items. De overgang bewaart dezelfde brondata en reviewworkflow.' }));
            header.appendChild(heading);
            header.appendChild(canReview ? badge('Reviewen toegestaan', 'review') : badge('Alleen-lezen', 'readonly'));
            container.appendChild(header);

            const snapshot = data.snapshot || {};
            const metrics = element('div', { class: 'mvm-news-radar__metrics' });
            metrics.appendChild(metric('Monitoringbronnen', snapshot.sources || 0, 'Crawler verwerkt A → B → C'));
            metrics.appendChild(metric('Radarregels', snapshot.results || 0));
            metrics.appendChild(metric('Onderwerpen', Array.isArray(data.items) ? data.items.length : 0, 'Updates zijn onder hun onderwerp gegroepeerd'));
            metrics.appendChild(metric('Detailqueue', snapshot.queue_jobs || 0));
            container.appendChild(metrics);

            const agendaCrawl = renderAgendaCrawl(agendaStatus);
            if (agendaCrawl) container.appendChild(agendaCrawl);

            container.appendChild(renderSources(Array.isArray(data.sources) ? data.sources : []));

            const topicHeading = element('div', { class: 'mvm-news-radar__section-title' });
            topicHeading.appendChild(element('h3', { text: 'Gevonden onderwerpen' }));
            topicHeading.appendChild(element('p', { text: canReview ? 'Markeer afzonderlijke meldingen als nagekeken. Negeren/verwijderen blijft nog uitsluitend in de legacy Hub.' : 'Nieuwe meldingen blijven één onderwerp; latere wijzigingen staan uitklapbaar onder Updates.' }));
            container.appendChild(topicHeading);

            const items = Array.isArray(data.items) ? data.items : [];
            if (!items.length) {
                container.appendChild(element('div', { class: 'mvm-news-radar__empty', text: 'Er staan momenteel geen Nieuwsradar-meldingen klaar.' }));
            } else {
                const list = element('div', { class: 'mvm-news-radar__list' });
                items.forEach((group) => list.appendChild(subjectCard(group)));
                container.appendChild(list);
            }
        } catch (error) {
            if (!active || token !== requestToken) return;
            clear(container);
            container.appendChild(element('div', { class: 'mvm-news-radar__empty', text: error.message }));
            setMessage(error.message, 'error');
        } finally {
            if (active && token === requestToken) container.setAttribute('aria-busy', 'false');
        }
    };

    const setActive = (enabled) => {
        active = Boolean(enabled);
        button.classList.toggle('is-active', active);
        button.setAttribute('aria-pressed', active ? 'true' : 'false');
        if (!active) return;

        classicNav.forEach((item) => {
            item.classList.remove('is-active');
            item.setAttribute('aria-pressed', 'false');
        });
        platformNav.forEach((item) => {
            item.classList.remove('is-active');
            item.setAttribute('aria-pressed', 'false');
        });
        if (classicView) classicView.hidden = true;
        if (workflow) workflow.hidden = true;
        if (myWork) myWork.hidden = true;
        container.hidden = false;
        if (titleNode) titleNode.textContent = 'Nieuwsradar';
    };

    button.addEventListener('click', async () => {
        setActive(true);
        await render();
    });

    [...classicNav, ...platformNav].forEach((item) => {
        item.addEventListener('click', () => {
            active = false;
            canReview = false;
            requestToken += 1;
            button.classList.remove('is-active');
            button.setAttribute('aria-pressed', 'false');
        });
    });
})();
