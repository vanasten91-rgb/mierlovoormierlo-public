(() => {
    'use strict';

    const config = window.MvMHub4Config || {};
    const container = document.querySelector('[data-mvm-view-container]');
    const titleNode = document.querySelector('[data-mvm-view-title]');
    const newsNav = document.querySelector('[data-mvm-view="news"]');
    if (!container || !titleNode || !newsNav || !config.restRoot || !config.restNonce) return;

    let categories = null;
    let activeSlug = '';
    let rendering = false;
    let observerQueued = false;

    const element = (tag, attrs = {}, children = []) => {
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

    const api = async (path, options = {}) => {
        const url = new URL(path.replace(/^\//, ''), config.restRoot);
        const headers = new Headers(options.headers || {});
        headers.set('X-WP-Nonce', config.restNonce);
        headers.set('Accept', 'application/json');
        if (options.body) headers.set('Content-Type', 'application/json; charset=UTF-8');
        const response = await fetch(url.toString(), {
            ...options,
            credentials: 'same-origin',
            cache: 'no-store',
            headers
        });
        let data = null;
        try { data = await response.json(); } catch (error) { throw new Error('De categoriepagina gaf geen geldig antwoord.'); }
        if (!response.ok) {
            const failure = new Error(data && data.message ? data.message : 'De categorieactie is mislukt.');
            failure.status = response.status;
            throw failure;
        }
        return data;
    };

    const isNewsView = () => newsNav.classList.contains('is-active') || titleNode.textContent.trim() === 'Nieuws';

    const readUrl = () => {
        const params = new URLSearchParams(window.location.search);
        activeSlug = /^[a-z0-9-]+$/.test(params.get('category') || '') ? params.get('category') : '';
        return params;
    };

    const writeUrl = (slug, replace = false) => {
        const url = new URL(window.location.href);
        url.searchParams.set('view', 'news');
        if (slug) url.searchParams.set('category', slug);
        else url.searchParams.delete('category');
        window.history[replace ? 'replaceState' : 'pushState']({}, '', url.pathname + '?' + url.searchParams.toString());
    };

    const loadCategories = async (force = false) => {
        if (categories && !force) return categories;
        categories = await api('news/categories');
        return categories;
    };

    const formatUtc = (value) => {
        if (!value) return '—';
        const date = new Date(`${String(value).replace(' ', 'T')}Z`);
        if (Number.isNaN(date.getTime())) return '—';
        return new Intl.DateTimeFormat('nl-NL', { dateStyle: 'medium', timeStyle: 'short' }).format(date);
    };

    const categoryButton = (item) => {
        const selected = item.slug === activeSlug;
        const node = element('button', {
            type: 'button',
            class: `mvm-news-category-v1__tab mvm-news-category-v1--${item.colorKey}${selected ? ' is-active' : ''}`,
            'aria-pressed': selected ? 'true' : 'false'
        }, [
            element('span', { text: item.name }),
            element('small', { text: String(item.count) })
        ]);
        node.addEventListener('click', () => {
            activeSlug = item.slug;
            writeUrl(activeSlug);
            render();
        });
        return node;
    };

    const renderCategoryPage = async (shell) => {
        const page = element('section', { class: 'mvm-news-category-v1__page', 'data-mvm-news-category-page': activeSlug });
        page.appendChild(element('div', { class: 'mvm-news-category-v1__loading', text: 'Categorie laden…' }));
        shell.appendChild(page);

        try {
            const data = await api(`news/category/${encodeURIComponent(activeSlug)}?limit=50`);
            while (page.firstChild) page.removeChild(page.firstChild);
            const category = data.category || {};
            page.classList.add(`mvm-news-category-v1--${category.colorKey || 'blue'}`);

            const head = element('div', { class: 'mvm-news-category-v1__page-head' });
            const text = element('div');
            text.appendChild(element('p', { class: 'mvm-news-category-v1__eyebrow', text: 'Nieuwscategorie' }));
            text.appendChild(element('h3', { text: category.name || activeSlug }));
            text.appendChild(element('p', { text: `${data.total || 0} nieuwsitem${Number(data.total) === 1 ? '' : 's'} in deze categorie.` }));
            head.appendChild(text);
            const copy = element('button', { type: 'button', class: 'mvm-hub4__button mvm-hub4__button--secondary', text: 'Kopieer deeplink' });
            copy.addEventListener('click', async () => {
                try {
                    await navigator.clipboard.writeText(window.location.href);
                    copy.textContent = 'Link gekopieerd';
                } catch (error) {
                    copy.textContent = 'Kopiëren niet gelukt';
                }
            });
            head.appendChild(copy);
            page.appendChild(head);

            const list = element('ul', { class: 'mvm-news-category-v1__list' });
            (data.items || []).forEach((item) => {
                const li = element('li', { class: 'mvm-news-category-v1__item' });
                const main = element('div');
                main.appendChild(element('strong', { text: item.title }));
                const meta = element('p', { class: 'mvm-news-category-v1__meta' });
                meta.appendChild(document.createTextNode(`${item.status || 'publish'} · gewijzigd ${formatUtc(item.modifiedUtc)}`));
                if (item.author) meta.appendChild(document.createTextNode(` · ${item.author}`));
                main.appendChild(meta);
                li.appendChild(main);
                const actions = element('div', { class: 'mvm-news-category-v1__actions' });
                if (item.viewUrl) actions.appendChild(element('a', { href: item.viewUrl, class: 'mvm-hub4__button mvm-hub4__button--secondary', text: 'Bekijken' }));
                if (item.editUrl) actions.appendChild(element('a', { href: item.editUrl, class: 'mvm-hub4__button', text: 'Bewerken' }));
                li.appendChild(actions);
                list.appendChild(li);
            });
            if (!(data.items || []).length) {
                list.appendChild(element('li', { class: 'mvm-news-category-v1__empty', text: 'Deze categorie is nog leeg. De pagina bestaat al en wordt automatisch gevuld zodra nieuws aan de categorie wordt gekoppeld.' }));
            }
            page.appendChild(list);
        } catch (error) {
            while (page.firstChild) page.removeChild(page.firstChild);
            page.appendChild(element('div', {
                class: 'mvm-news-category-v1__error',
                role: 'alert',
                text: error.status === 404 ? 'Deze nieuwscategorie bestaat niet (404).' : error.message
            }));
        }
    };

    const createForm = (data) => {
        if (!data.permissions || !data.permissions.canManage) return null;
        const card = element('details', { class: 'mvm-news-category-v1__create' });
        card.appendChild(element('summary', { text: 'Nieuwe categoriepagina maken' }));
        const form = element('form', { class: 'mvm-news-category-v1__form' });
        const nameLabel = element('label', {}, [element('span', { text: 'Categorienaam' })]);
        const name = element('input', { type: 'text', minlength: '2', maxlength: '80', required: 'required', autocomplete: 'off' });
        nameLabel.appendChild(name);
        form.appendChild(nameLabel);

        const colorLabel = element('label', {}, [element('span', { text: 'MvM-kleur' })]);
        const select = element('select', { required: 'required' });
        Object.entries(data.palette || {}).forEach(([key, option]) => {
            select.appendChild(element('option', { value: key, text: option.label || key }));
        });
        colorLabel.appendChild(select);
        form.appendChild(colorLabel);
        const status = element('p', { class: 'mvm-news-category-v1__form-status', role: 'status', 'aria-live': 'polite' });
        const submit = element('button', { type: 'submit', class: 'mvm-hub4__button', text: 'Categoriepagina maken' });
        form.appendChild(submit);
        form.appendChild(status);
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            submit.disabled = true;
            status.textContent = 'Categorie aanmaken…';
            try {
                const created = await api('news/categories', {
                    method: 'POST',
                    body: JSON.stringify({ name: name.value, color: select.value })
                });
                await loadCategories(true);
                activeSlug = created.slug;
                writeUrl(activeSlug);
                status.textContent = 'Categoriepagina aangemaakt.';
                await render();
            } catch (error) {
                status.textContent = error.message;
            } finally {
                submit.disabled = false;
            }
        });
        card.appendChild(form);
        return card;
    };

    const render = async () => {
        if (rendering || !isNewsView()) return;
        rendering = true;
        try {
            document.querySelectorAll('[data-mvm-news-categories-v1]').forEach((node) => node.remove());
            const data = await loadCategories();
            if (!isNewsView()) return;

            container.classList.toggle('mvm-news-category-v1--filtered', Boolean(activeSlug));
            const shell = element('section', { class: 'mvm-news-category-v1', 'data-mvm-news-categories-v1': '1', 'aria-label': 'Nieuwscategorieën' });
            const header = element('div', { class: 'mvm-news-category-v1__header' });
            const text = element('div');
            text.appendChild(element('h2', { text: 'Nieuws per categorie' }));
            text.appendChild(element('p', { text: 'Elke categorie heeft automatisch een eigen Hub-pagina in de vaste MvM-stijl, ook wanneer de categorie nog leeg is.' }));
            header.appendChild(text);
            const form = createForm(data);
            if (form) header.appendChild(form);
            shell.appendChild(header);

            const tabs = element('div', { class: 'mvm-news-category-v1__tabs', role: 'group', 'aria-label': 'Kies een nieuwscategorie' });
            const all = element('button', {
                type: 'button',
                class: `mvm-news-category-v1__tab mvm-news-category-v1--blue${activeSlug ? '' : ' is-active'}`,
                'aria-pressed': activeSlug ? 'false' : 'true'
            }, [element('span', { text: 'Alle categorieën' })]);
            all.addEventListener('click', () => {
                activeSlug = '';
                writeUrl('');
                render();
            });
            tabs.appendChild(all);
            (data.items || []).forEach((item) => tabs.appendChild(categoryButton(item)));
            shell.appendChild(tabs);

            if (activeSlug) await renderCategoryPage(shell);
            container.prepend(shell);
        } catch (error) {
            container.classList.remove('mvm-news-category-v1--filtered');
            const shell = element('section', { class: 'mvm-news-category-v1', 'data-mvm-news-categories-v1': '1' });
            shell.appendChild(element('div', { class: 'mvm-news-category-v1__error', role: 'alert', text: error.message }));
            container.prepend(shell);
        } finally {
            rendering = false;
        }
    };

    const clearCategoryUi = () => {
        document.querySelectorAll('[data-mvm-news-categories-v1]').forEach((node) => node.remove());
        container.classList.remove('mvm-news-category-v1--filtered');
    };

    const queueRender = () => {
        if (observerQueued) return;
        observerQueued = true;
        window.setTimeout(() => {
            observerQueued = false;
            if (isNewsView()) render();
            else clearCategoryUi();
        }, 30);
    };

    const isOwnShellNode = (node) => node instanceof Element && (
        node.matches('[data-mvm-news-categories-v1]') || Boolean(node.closest('[data-mvm-news-categories-v1]'))
    );

    const boot = () => {
        const params = readUrl();
        if (params.get('view') === 'news' && !isNewsView()) newsNav.click();
        const observer = new MutationObserver((mutations) => {
            const externalMutation = mutations.some((mutation) => {
                const nodes = [...mutation.addedNodes, ...mutation.removedNodes];
                return nodes.some((node) => !isOwnShellNode(node));
            });
            if (externalMutation) queueRender();
        });
        observer.observe(container, { childList: true });
        observer.observe(titleNode, { childList: true, characterData: true, subtree: true });
        newsNav.addEventListener('click', () => window.setTimeout(() => { readUrl(); render(); }, 0));
        window.addEventListener('popstate', () => {
            readUrl();
            if (!isNewsView()) newsNav.click();
            window.setTimeout(render, 0);
        });
        if (isNewsView()) render();
    };

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot, { once: true });
    else boot();
})();
