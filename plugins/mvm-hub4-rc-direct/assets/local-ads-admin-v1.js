(() => {
    'use strict';

    const config = window.MvMHub4Config || {};
    const root = config.platformRestRoot || '';
    const nonce = config.restNonce || '';
    const nav = document.querySelector('[data-mvm-service-view="local_ads"]');
    const container = document.querySelector('[data-mvm-service-container]');
    const message = document.querySelector('[data-mvm-platform-message]');
    if (!root || !nonce || !nav || !container) return;

    const el = (tag, attrs = {}, children = []) => {
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

    const setMessage = (text, type = '') => {
        if (!message) return;
        message.textContent = text || '';
        message.className = 'mvm-hub4__platform-message';
        if (type) message.classList.add(`is-${type}`);
    };

    const api = async (path, options = {}) => {
        const url = new URL(String(path).replace(/^\//, ''), root);
        const headers = new Headers(options.headers || {});
        headers.set('X-WP-Nonce', nonce);
        headers.set('Accept', 'application/json');
        if (options.body && !headers.has('Content-Type')) headers.set('Content-Type', 'application/json; charset=UTF-8');
        const response = await fetch(url.toString(), {
            ...options,
            headers,
            credentials: 'same-origin',
            cache: 'no-store'
        });
        let data = {};
        try { data = await response.json(); } catch (error) { throw new Error('Ongeldig antwoord van advertentiebeheer.'); }
        if (!response.ok) throw new Error(data.message || 'Advertentieactie mislukt.');
        return data;
    };

    const request = (path, method, payload) => api(path, {
        method,
        body: payload === undefined ? undefined : JSON.stringify(payload)
    });

    const field = (label, input, wide = false) => {
        const wrap = el('label', { class: wide ? 'is-wide' : '' });
        wrap.appendChild(document.createTextNode(label));
        wrap.appendChild(input);
        return wrap;
    };

    const dateValue = (timestamp) => {
        const value = Number(timestamp || 0);
        if (!value) return '';
        const date = new Date(value * 1000);
        const pad = (number) => String(number).padStart(2, '0');
        return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
    };

    const buildForm = (placements) => {
        const form = el('form', { class: 'mvm-hub4__platform-form', 'data-local-ad-admin-form': '1' });
        const id = el('input', { type: 'hidden', name: 'id' });
        const advertiser = el('input', { name: 'advertiser', maxlength: '120', required: 'required' });
        const headline = el('input', { name: 'headline', maxlength: '160', required: 'required' });
        const text = el('textarea', { name: 'text', maxlength: '600', rows: '4' });
        const cta = el('input', { name: 'cta', maxlength: '60', value: 'Bekijk ondernemer' });
        const url = el('input', { name: 'url', type: 'url', required: 'required', placeholder: 'https://...' });
        const owner = el('input', { name: 'owner_id', type: 'number', min: '0', step: '1', placeholder: 'optioneel: Ondernemer user-ID' });
        const image = el('input', { name: 'image_id', type: 'number', min: '0', step: '1', placeholder: 'optioneel: media-ID' });
        const start = el('input', { name: 'start_at', type: 'datetime-local' });
        const end = el('input', { name: 'end_at', type: 'datetime-local' });
        const status = el('select', { name: 'status' });
        [['draft', 'Concept'], ['active', 'Actief'], ['paused', 'Gepauzeerd'], ['ended', 'Afgelopen'], ['suspended', 'Geschorst']].forEach(([value, label]) => {
            status.appendChild(el('option', { value, text: label }));
        });

        form.appendChild(id);
        form.appendChild(field('Bedrijfsnaam', advertiser));
        form.appendChild(field('Kop', headline));
        form.appendChild(field('Advertentietekst', text, true));
        form.appendChild(field('Knoptekst', cta));
        form.appendChild(field('Doel-URL', url));
        form.appendChild(field('Ondernemer eigenaar-ID', owner));
        form.appendChild(field('Afbeelding media-ID', image));
        form.appendChild(field('Start', start));
        form.appendChild(field('Einde', end));
        form.appendChild(field('Status', status));

        const placementField = el('fieldset', { class: 'is-wide' });
        placementField.appendChild(el('legend', { text: 'Plaatsingen' }));
        const placementGrid = el('div', { class: 'mvm-hub4__platform-actions' });
        Object.entries(placements || {}).forEach(([value, label]) => {
            const checkbox = el('input', { type: 'checkbox', value, 'data-local-ad-placement': value });
            const item = el('label');
            item.appendChild(checkbox);
            item.appendChild(document.createTextNode(` ${label}`));
            placementGrid.appendChild(item);
        });
        placementField.appendChild(placementGrid);
        form.appendChild(placementField);

        const actions = el('div', { class: 'mvm-hub4__platform-actions is-wide' });
        const submit = el('button', { type: 'submit', class: 'mvm-hub4__platform-button', text: 'Advertentie aanmaken' });
        const cancel = el('button', { type: 'button', class: 'mvm-hub4__platform-button mvm-hub4__platform-button--secondary', text: 'Annuleren', hidden: 'hidden' });
        actions.appendChild(submit);
        actions.appendChild(cancel);
        form.appendChild(actions);

        const reset = () => {
            form.reset();
            id.value = '';
            cta.value = 'Bekijk ondernemer';
            status.value = 'draft';
            submit.textContent = 'Advertentie aanmaken';
            cancel.setAttribute('hidden', 'hidden');
        };

        const load = (item) => {
            id.value = String(item.id || '');
            advertiser.value = item.advertiser || '';
            headline.value = item.headline || '';
            text.value = item.text || '';
            cta.value = item.cta || 'Bekijk ondernemer';
            url.value = item.url || '';
            owner.value = Number(item.owner_id || 0) || '';
            image.value = Number(item.image_id || 0) || '';
            start.value = dateValue(item.start_at);
            end.value = dateValue(item.end_at);
            status.value = item.status || 'draft';
            form.querySelectorAll('[data-local-ad-placement]').forEach((checkbox) => {
                checkbox.checked = Array.isArray(item.placements) && item.placements.includes(checkbox.value);
            });
            submit.textContent = 'Wijzigingen opslaan';
            cancel.removeAttribute('hidden');
            form.scrollIntoView({ behavior: 'smooth', block: 'start' });
        };

        cancel.addEventListener('click', reset);
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            const selected = Array.from(form.querySelectorAll('[data-local-ad-placement]:checked')).map((checkbox) => checkbox.value);
            const payload = {
                advertiser: advertiser.value.trim(),
                headline: headline.value.trim(),
                text: text.value.trim(),
                cta: cta.value.trim(),
                url: url.value.trim(),
                owner_id: Number(owner.value || 0),
                image_id: Number(image.value || 0),
                start_at: start.value,
                end_at: end.value,
                status: status.value,
                placements: selected
            };
            try {
                if (id.value) {
                    await request(`local-ads/${id.value}`, 'PUT', payload);
                    setMessage('Advertentie bijgewerkt door SysOp/Admin.', 'success');
                } else {
                    await request('local-ads', 'POST', payload);
                    setMessage('Advertentie aangemaakt door SysOp/Admin.', 'success');
                }
                reset();
                await render();
            } catch (error) {
                setMessage(error.message, 'error');
            }
        });

        return { form, load };
    };

    const render = async () => {
        if (nav.getAttribute('aria-pressed') !== 'true') return;
        let data;
        try { data = await api('local-ads'); } catch (error) { return; }
        if (!data.can_admin) return;

        const existing = container.querySelector('[data-mvm-local-ads-admin]');
        if (existing) existing.remove();

        const panel = el('section', { class: 'mvm-hub4__platform-card', 'data-mvm-local-ads-admin': '1' });
        panel.appendChild(el('h2', { text: 'SysOp/Admin advertentiebeheer' }));
        panel.appendChild(el('p', { text: 'Administrator en SysOp kunnen hier advertenties aanmaken, wijzigen en verwijderen. Andere staffrollen blijven beperkt tot toezicht en moderatie.' }));

        const { form, load } = buildForm(data.placements || {});
        panel.appendChild(form);

        const list = el('div', { class: 'mvm-hub4__platform-list' });
        (Array.isArray(data.items) ? data.items : []).forEach((item) => {
            const row = el('article', { class: 'mvm-hub4__platform-row' });
            const copy = el('div');
            copy.appendChild(el('strong', { text: item.headline || `Advertentie #${item.id}` }));
            copy.appendChild(el('small', { text: `${item.advertiser || 'Ondernemer'} · ${item.status || 'draft'} · eigenaar #${Number(item.owner_id || 0)}` }));
            row.appendChild(copy);
            const actions = el('div', { class: 'mvm-hub4__platform-actions' });
            const edit = el('button', { type: 'button', class: 'mvm-hub4__platform-button mvm-hub4__platform-button--secondary', text: 'Bewerken' });
            edit.addEventListener('click', () => load(item));
            const remove = el('button', { type: 'button', class: 'mvm-hub4__platform-button', text: 'Verwijderen' });
            remove.addEventListener('click', async () => {
                if (!window.confirm(`Advertentie “${item.headline || item.id}” naar de prullenbak verplaatsen?`)) return;
                try {
                    await request(`local-ads/${item.id}`, 'DELETE');
                    setMessage('Advertentie verwijderd door SysOp/Admin.', 'success');
                    await render();
                } catch (error) { setMessage(error.message, 'error'); }
            });
            actions.appendChild(edit);
            actions.appendChild(remove);
            row.appendChild(actions);
            list.appendChild(row);
        });
        panel.appendChild(list);
        container.appendChild(panel);
    };

    const scheduleRender = () => {
        let tries = 0;
        const tick = () => {
            tries += 1;
            if (nav.getAttribute('aria-pressed') !== 'true') return;
            if (container.getAttribute('aria-busy') === 'false' || tries > 30) {
                render();
                return;
            }
            window.setTimeout(tick, 100);
        };
        window.setTimeout(tick, 50);
    };

    nav.addEventListener('click', scheduleRender);
})();
