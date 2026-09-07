(function () {
    'use strict';

    if (!window.MVMEncyclopedieNext || !window.MVMEncyclopedieNext.restUrl) {
        return;
    }

    var config = window.MVMEncyclopedieNext;

    function createElement(tag, className, text) {
        var element = document.createElement(tag);
        if (className) {
            element.className = className;
        }
        if (typeof text === 'string') {
            element.textContent = text;
        }
        return element;
    }

    function createCard(item) {
        var article = createElement('article', 'mvm-e3-card');
        var mediaLink = createElement('a', 'mvm-e3-card-media');
        mediaLink.href = item.url;
        mediaLink.tabIndex = -1;
        mediaLink.setAttribute('aria-hidden', 'true');

        if (item.thumbnail) {
            var image = document.createElement('img');
            image.src = item.thumbnail;
            image.alt = '';
            image.loading = 'lazy';
            image.decoding = 'async';
            mediaLink.appendChild(image);
        } else {
            mediaLink.appendChild(createElement('span', 'mvm-e3-card-placeholder'));
        }

        var body = createElement('div', 'mvm-e3-card-body');
        body.appendChild(createElement('span', 'mvm-e3-type', item.type_label || 'Dossier'));

        var heading = createElement('h3');
        var titleLink = createElement('a', '', item.title || 'Dossier');
        titleLink.href = item.url;
        heading.appendChild(titleLink);
        body.appendChild(heading);

        if (item.excerpt) {
            body.appendChild(createElement('p', '', item.excerpt));
        }

        article.appendChild(mediaLink);
        article.appendChild(body);
        return article;
    }

    function initLiveSearch(form, formIndex) {
        var input = form.querySelector('[data-mvm-search-input]');
        var typeSelect = form.querySelector('[data-mvm-search-type]');
        var results = form.querySelector('[data-mvm-live-results]');
        var timer = null;
        var controller = null;
        var activeIndex = -1;

        if (!input || !results) {
            return;
        }

        if (!results.id) {
            results.id = 'mvm-e3-live-results-' + String(formIndex + 1);
        }
        results.setAttribute('role', 'listbox');
        results.setAttribute('aria-label', 'Zoeksuggesties');
        input.setAttribute('role', 'combobox');
        input.setAttribute('aria-autocomplete', 'list');
        input.setAttribute('aria-controls', results.id);
        input.setAttribute('aria-expanded', 'false');

        function optionLinks() {
            return Array.prototype.slice.call(results.querySelectorAll('[role="option"]'));
        }

        function clearActive() {
            optionLinks().forEach(function (option) {
                option.classList.remove('is-active');
                option.setAttribute('aria-selected', 'false');
                option.style.background = '';
            });
            activeIndex = -1;
            input.removeAttribute('aria-activedescendant');
        }

        function setActive(index) {
            var options = optionLinks();
            if (!options.length) {
                clearActive();
                return;
            }

            if (index < 0) {
                index = options.length - 1;
            }
            if (index >= options.length) {
                index = 0;
            }

            options.forEach(function (option, optionIndex) {
                var selected = optionIndex === index;
                option.classList.toggle('is-active', selected);
                option.setAttribute('aria-selected', selected ? 'true' : 'false');
                option.style.background = selected ? 'var(--mvm-e3-blue-soft)' : '';
            });

            activeIndex = index;
            input.setAttribute('aria-activedescendant', options[index].id);
            options[index].scrollIntoView({ block: 'nearest' });
        }

        function closeResults() {
            clearActive();
            results.hidden = true;
            results.replaceChildren();
            input.setAttribute('aria-expanded', 'false');
        }

        function renderMessage(message) {
            clearActive();
            var status = createElement('div', 'mvm-e3-empty', message);
            status.setAttribute('role', 'status');
            results.replaceChildren(status);
            results.hidden = false;
            input.setAttribute('aria-expanded', 'true');
        }

        function renderItems(items) {
            results.replaceChildren();
            activeIndex = -1;
            input.removeAttribute('aria-activedescendant');

            if (!Array.isArray(items) || items.length === 0) {
                renderMessage(config.labels && config.labels.empty ? config.labels.empty : 'Geen dossiers gevonden.');
                return;
            }

            items.forEach(function (item, itemIndex) {
                var link = createElement('a', 'mvm-e3-live-item');
                link.href = item.url;
                link.id = results.id + '-option-' + String(itemIndex + 1);
                link.setAttribute('role', 'option');
                link.setAttribute('aria-selected', 'false');
                link.tabIndex = -1;

                if (item.thumbnail) {
                    var image = document.createElement('img');
                    image.src = item.thumbnail;
                    image.alt = '';
                    image.loading = 'lazy';
                    image.decoding = 'async';
                    link.appendChild(image);
                } else {
                    link.appendChild(createElement('span', 'mvm-e3-live-thumb'));
                }

                var copy = createElement('span');
                copy.appendChild(createElement('strong', '', item.title || 'Dossier'));
                copy.appendChild(createElement('small', '', item.type_label || 'Dossier'));
                link.appendChild(copy);
                results.appendChild(link);
            });

            results.hidden = false;
            input.setAttribute('aria-expanded', 'true');
        }

        function searchNow() {
            var query = input.value.trim();
            if (query.length < 2) {
                closeResults();
                return;
            }

            if (controller) {
                controller.abort();
            }
            controller = new AbortController();

            renderMessage(config.labels && config.labels.loading ? config.labels.loading : 'Zoeken…');

            var url = new URL(config.restUrl + 'search', window.location.origin);
            url.searchParams.set('q', query);
            url.searchParams.set('per_page', '6');
            if (typeSelect && typeSelect.value) {
                url.searchParams.set('type', typeSelect.value);
            }

            fetch(url.toString(), {
                credentials: 'same-origin',
                signal: controller.signal,
                headers: { 'Accept': 'application/json' }
            })
                .then(function (response) {
                    if (!response.ok) {
                        throw new Error('search_failed');
                    }
                    return response.json();
                })
                .then(function (payload) {
                    renderItems(payload.items || []);
                })
                .catch(function (error) {
                    if (error && error.name === 'AbortError') {
                        return;
                    }
                    renderMessage(config.labels && config.labels.error ? config.labels.error : 'Zoeken lukt nu niet.');
                });
        }

        input.addEventListener('input', function () {
            window.clearTimeout(timer);
            clearActive();
            timer = window.setTimeout(searchNow, 220);
        });

        if (typeSelect) {
            typeSelect.addEventListener('change', function () {
                clearActive();
                if (input.value.trim().length >= 2) {
                    searchNow();
                }
            });
        }

        input.addEventListener('keydown', function (event) {
            var options = optionLinks();

            if (event.key === 'ArrowDown' && options.length) {
                event.preventDefault();
                setActive(activeIndex + 1);
                return;
            }

            if (event.key === 'ArrowUp' && options.length) {
                event.preventDefault();
                setActive(activeIndex - 1);
                return;
            }

            if (event.key === 'Enter' && activeIndex >= 0 && options[activeIndex]) {
                event.preventDefault();
                window.location.assign(options[activeIndex].href);
                return;
            }

            if (event.key === 'Escape') {
                event.preventDefault();
                closeResults();
            }
        });

        document.addEventListener('click', function (event) {
            if (!form.contains(event.target)) {
                closeResults();
            }
        });
    }

    function initRelationLoader(button) {
        var section = button.closest('.mvm-e3-relations');
        var grid = section && section.querySelector('[data-mvm-relations-grid]');
        var postId = parseInt(button.getAttribute('data-post-id') || '0', 10);
        var loading = false;

        if (!grid || !postId) {
            return;
        }

        if (!grid.id) {
            grid.id = 'mvm-e3-relations-' + String(postId);
        }
        button.setAttribute('aria-controls', grid.id);

        button.addEventListener('click', function () {
            if (loading) {
                return;
            }

            loading = true;
            button.disabled = true;
            button.setAttribute('aria-busy', 'true');
            var originalText = button.textContent;
            button.textContent = config.labels && config.labels.loading ? config.labels.loading : 'Laden…';

            var offset = parseInt(button.getAttribute('data-offset') || '0', 10);
            var url = new URL(config.restUrl + 'items/' + postId + '/relations', window.location.origin);
            url.searchParams.set('offset', String(offset));
            url.searchParams.set('per_page', '12');

            fetch(url.toString(), {
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json' }
            })
                .then(function (response) {
                    if (!response.ok) {
                        throw new Error('relations_failed');
                    }
                    return response.json();
                })
                .then(function (payload) {
                    var items = Array.isArray(payload.items) ? payload.items : [];
                    items.forEach(function (item) {
                        grid.appendChild(createCard(item));
                    });

                    var nextOffset = offset + items.length;
                    button.setAttribute('data-offset', String(nextOffset));
                    if (nextOffset >= parseInt(payload.total || '0', 10) || items.length === 0) {
                        button.remove();
                        return;
                    }

                    button.textContent = config.labels && config.labels.moreRelations ? config.labels.moreRelations : originalText;
                    button.disabled = false;
                    button.removeAttribute('aria-busy');
                    loading = false;
                })
                .catch(function () {
                    button.textContent = originalText;
                    button.disabled = false;
                    button.removeAttribute('aria-busy');
                    loading = false;
                });
        });
    }

    document.querySelectorAll('[data-mvm-live-search]').forEach(initLiveSearch);
    document.querySelectorAll('[data-mvm-relations-more]').forEach(initRelationLoader);
}());
