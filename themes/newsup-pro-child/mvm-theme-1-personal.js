(function () {
    'use strict';

    const root = document.getElementById('mvm-personal-home-v1');
    const config = window.MvMPersonalHomeV1 || {};
    if (!root || !config.ajaxUrl || !config.nonce) return;

    const shell = root.querySelector('.mvm-personal-home-v1__shell');
    const loading = root.querySelector('.mvm-personal-home-v1__loading');
    const toggle = root.querySelector('.mvm-personal-home-v1__toggle');
    const toggleLabel = root.querySelector('.mvm-personal-home-v1__toggle-label');
    const storageKey = 'mvm-personal-home-expanded-v1';

    const text = (value) => String(value == null ? '' : value);

    const readExpanded = () => {
        try {
            return window.localStorage.getItem(storageKey) === '1';
        } catch (error) {
            return false;
        }
    };

    const rememberExpanded = (expanded) => {
        try {
            window.localStorage.setItem(storageKey, expanded ? '1' : '0');
        } catch (error) {
            // Local storage is optional. The component remains fully usable without it.
        }
    };

    const setExpanded = (expanded, persist) => {
        const panel = root.querySelector('#mvm-personal-home-panel-v1');
        if (!panel || !toggle) return;

        panel.hidden = !expanded;
        toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        root.classList.toggle('is-expanded', expanded);
        if (toggleLabel) {
            toggleLabel.textContent = expanded ? 'Inklappen' : 'Uitklappen';
        }
        if (persist) {
            rememberExpanded(expanded);
        }
    };

    const makeLink = (href, label, meta) => {
        const link = document.createElement('a');
        link.className = 'mvm-personal-link-v1';
        link.href = href;

        const strong = document.createElement('strong');
        strong.textContent = label;
        link.appendChild(strong);

        if (meta) {
            const span = document.createElement('span');
            span.textContent = meta;
            link.appendChild(span);
        }
        return link;
    };

    const render = (payload) => {
        const counts = payload.counts || {};
        const links = payload.links || {};
        const events = Array.isArray(payload.events) ? payload.events : [];
        const savedItems = Array.isArray(payload.savedItems) ? payload.savedItems.slice() : [];

        const stats = document.createElement('div');
        const statValues = {};
        stats.className = 'mvm-personal-home-v1__stats';
        [
            ['events', 'Evenementen'],
            ['saved', 'Opgeslagen'],
            ['forumBookmarks', 'Favorieten'],
            ['forumFollows', 'Gevolgd']
        ].forEach(([key, label]) => {
            const card = document.createElement('div');
            card.className = 'mvm-personal-stat-v1';
            const value = document.createElement('strong');
            value.textContent = text(Number(counts[key] || 0));
            statValues[key] = value;
            const caption = document.createElement('span');
            caption.textContent = label;
            card.append(value, caption);
            stats.appendChild(card);
        });

        const panel = document.createElement('div');
        panel.id = 'mvm-personal-home-panel-v1';
        panel.className = 'mvm-personal-home-v1__content';
        panel.hidden = true;

        const layout = document.createElement('div');
        layout.className = 'mvm-personal-home-v1__layout';

        const eventSection = document.createElement('section');
        eventSection.className = 'mvm-personal-events-v1';
        eventSection.setAttribute('aria-labelledby', 'mvm-personal-events-title-v1');
        const eventTitle = document.createElement('h3');
        eventTitle.id = 'mvm-personal-events-title-v1';
        eventTitle.textContent = 'Mijn agenda';
        eventSection.appendChild(eventTitle);

        if (events.length) {
            const list = document.createElement('div');
            list.className = 'mvm-personal-events-v1__list';
            events.forEach((event) => {
                const link = document.createElement('a');
                link.className = 'mvm-personal-event-v1';
                link.href = event.url;

                const badge = document.createElement('span');
                badge.className = 'mvm-personal-event-v1__status';
                badge.textContent = text(event.status);

                const title = document.createElement('strong');
                title.textContent = text(event.title);

                const date = document.createElement('span');
                date.className = 'mvm-personal-event-v1__date';
                date.textContent = text(event.date);

                link.append(badge, title, date);
                list.appendChild(link);
            });
            eventSection.appendChild(list);
        } else {
            const empty = document.createElement('p');
            empty.className = 'mvm-personal-home-v1__empty';
            empty.textContent = 'Nog geen aankomend evenement gemarkeerd als “Ik ga” of “Geïnteresseerd”.';
            eventSection.appendChild(empty);
        }

        if (links.events) {
            const allEvents = document.createElement('a');
            allEvents.className = 'mvm-personal-home-v1__more';
            allEvents.href = links.events;
            allEvents.textContent = 'Alle evenementen →';
            eventSection.appendChild(allEvents);
        }
        layout.appendChild(eventSection);

        const quick = document.createElement('nav');
        quick.className = 'mvm-personal-quick-v1';
        quick.setAttribute('aria-label', 'Mijn Mierlo voor Mierlo');
        const quickTitle = document.createElement('h3');
        quickTitle.textContent = 'Snel naar';
        quick.appendChild(quickTitle);

        const quickGrid = document.createElement('div');
        quickGrid.className = 'mvm-personal-quick-v1__grid';
        if (links.activity) quickGrid.appendChild(makeLink(links.activity, 'Tijdlijn'));
        if (links.messages) quickGrid.appendChild(makeLink(links.messages, 'Berichten'));
        if (links.groups) quickGrid.appendChild(makeLink(links.groups, 'Groepen'));
        if (links.forum) quickGrid.appendChild(makeLink(links.forum, 'Forum', `${Number(counts.forumBookmarks || 0)} favoriet · ${Number(counts.forumFollows || 0)} gevolgd`));
        if (links.profile) quickGrid.appendChild(makeLink(links.profile, 'Mijn profiel'));
        quick.appendChild(quickGrid);
        layout.appendChild(quick);

        const savedSection = document.createElement('section');
        savedSection.className = 'mvm-personal-saved-v1';
        savedSection.setAttribute('aria-labelledby', 'mvm-personal-saved-title-v1');

        const savedHead = document.createElement('div');
        savedHead.className = 'mvm-personal-saved-v1__head';
        const savedTitle = document.createElement('h3');
        savedTitle.id = 'mvm-personal-saved-title-v1';
        savedTitle.textContent = 'Opgeslagen';
        const savedMeta = document.createElement('span');
        savedMeta.textContent = `${Number(counts.saved || 0)} totaal`;
        savedHead.append(savedTitle, savedMeta);
        savedSection.appendChild(savedHead);

        const filterBar = document.createElement('div');
        filterBar.className = 'mvm-personal-saved-v1__filters';
        filterBar.setAttribute('role', 'group');
        filterBar.setAttribute('aria-label', 'Filter opgeslagen items');
        const filters = [
            ['all', 'Alles'],
            ['news', 'Nieuws'],
            ['encyclopedia', 'Encyclopedie'],
            ['events', 'Evenementen'],
            ['community', 'Community']
        ];
        filters.forEach(([key, label], index) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'mvm-personal-saved-filter-v1';
            button.dataset.savedFilter = key;
            button.setAttribute('aria-pressed', index === 0 ? 'true' : 'false');
            button.textContent = label;
            filterBar.appendChild(button);
        });
        savedSection.appendChild(filterBar);

        const savedList = document.createElement('div');
        savedList.className = 'mvm-personal-saved-v1__list';

        let activeSavedFilter = 'all';

        const renderSaved = (filter) => {
            activeSavedFilter = filter || 'all';
            savedList.replaceChildren();
            const visible = activeSavedFilter === 'all' ? savedItems : savedItems.filter((item) => item.type === activeSavedFilter);

            visible.forEach((item) => {
                const row = document.createElement('div');
                row.className = 'mvm-personal-saved-row-v1';

                const link = document.createElement('a');
                link.className = 'mvm-personal-saved-item-v1';
                link.href = item.url;
                link.dataset.savedType = item.type;

                const badge = document.createElement('span');
                badge.className = 'mvm-personal-saved-item-v1__type';
                badge.textContent = text(item.typeLabel);

                const title = document.createElement('strong');
                title.textContent = text(item.title);
                link.append(badge, title);

                if (item.eventStatus) {
                    const status = document.createElement('span');
                    status.className = 'mvm-personal-saved-item-v1__status';
                    status.textContent = text(item.eventStatus);
                    link.appendChild(status);
                }

                const remove = document.createElement('button');
                remove.type = 'button';
                remove.className = 'mvm-personal-saved-remove-v1';
                remove.textContent = 'Verwijderen';
                remove.setAttribute('aria-label', `Verwijder ${text(item.title)} uit opgeslagen`);

                remove.addEventListener('click', () => {
                    if (!config.bookmarkNonce || remove.disabled) return;

                    remove.disabled = true;
                    remove.textContent = 'Verwijderen…';

                    const body = new URLSearchParams();
                    body.set('action', 'mvm_bookmark_toggle_v1');
                    body.set('nonce', config.bookmarkNonce);
                    body.set('post_id', String(Number(item.id || 0)));
                    body.set('saved', '0');

                    fetch(config.ajaxUrl, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
                        body: body.toString()
                    })
                        .then((response) => response.json())
                        .then((response) => {
                            if (!response || !response.success || !response.data || response.data.saved !== false) {
                                throw new Error(response && response.data && response.data.message ? response.data.message : 'Verwijderen mislukt');
                            }

                            const index = savedItems.findIndex((savedItem) => Number(savedItem.id) === Number(item.id));
                            if (index !== -1) savedItems.splice(index, 1);

                            counts.savedMvm = Math.max(0, Number(counts.savedMvm || 0) - 1);
                            counts.saved = Math.max(0, Number(counts.savedCommunity || 0) + Number(counts.savedMvm || 0));
                            savedMeta.textContent = `${Number(counts.saved || 0)} totaal`;
                            if (statValues.saved) statValues.saved.textContent = text(Number(counts.saved || 0));
                            renderSaved(activeSavedFilter);
                        })
                        .catch(() => {
                            remove.disabled = false;
                            remove.textContent = 'Opnieuw proberen';
                        });
                });

                row.append(link, remove);
                savedList.appendChild(row);
            });

            const includeCommunity = activeSavedFilter === 'all' || activeSavedFilter === 'community';
            const communityCount = Number(counts.savedCommunity || 0);
            if (includeCommunity && communityCount > 0) {
                const community = document.createElement('a');
                community.className = 'mvm-personal-saved-item-v1 mvm-personal-saved-item-v1--community';
                community.href = links.activity || '#';
                const badge = document.createElement('span');
                badge.className = 'mvm-personal-saved-item-v1__type';
                badge.textContent = 'Community';
                const title = document.createElement('strong');
                title.textContent = `${communityCount} opgeslagen community${communityCount === 1 ? 'post' : 'posts'}`;
                const hint = document.createElement('span');
                hint.className = 'mvm-personal-saved-item-v1__status';
                hint.textContent = 'Open Tijdlijn';
                community.append(badge, title, hint);
                savedList.appendChild(community);
            }

            if (!savedList.children.length) {
                const empty = document.createElement('p');
                empty.className = 'mvm-personal-home-v1__empty';
                empty.textContent = activeSavedFilter === 'all' ? 'Je hebt nog niets opgeslagen.' : 'Nog niets opgeslagen in deze categorie.';
                savedList.appendChild(empty);
            }
        };

        filterBar.addEventListener('click', (event) => {
            const button = event.target.closest('[data-saved-filter]');
            if (!button) return;
            filterBar.querySelectorAll('[data-saved-filter]').forEach((item) => item.setAttribute('aria-pressed', item === button ? 'true' : 'false'));
            renderSaved(button.dataset.savedFilter || 'all');
        });

        renderSaved('all');
        savedSection.appendChild(savedList);
        panel.append(layout, savedSection);
        if (loading) loading.remove();
        shell.append(stats, panel);

        if (toggle) {
            toggle.disabled = false;
            toggle.addEventListener('click', () => {
                const expanded = toggle.getAttribute('aria-expanded') === 'true';
                setExpanded(!expanded, true);
            });
        }

        setExpanded(readExpanded(), false);
        root.setAttribute('aria-busy', 'false');
    };

    const fail = (message) => {
        if (loading) {
            loading.textContent = message || 'Je persoonlijke overzicht kon niet worden geladen.';
        }
        root.classList.add('mvm-personal-home-v1--error');
        root.setAttribute('aria-busy', 'false');
    };

    const body = new URLSearchParams();
    body.set('action', 'mvm_personal_home_v1');
    body.set('nonce', config.nonce);

    fetch(config.ajaxUrl, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
        body: body.toString()
    })
        .then((response) => response.json())
        .then((response) => {
            if (!response || !response.success || !response.data) {
                throw new Error(response && response.data && response.data.message ? response.data.message : 'Onbekende fout');
            }
            render(response.data);
        })
        .catch((error) => fail(error.message));
}());
