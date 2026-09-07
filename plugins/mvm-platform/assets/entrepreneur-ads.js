(() => {
    'use strict';

    const root = document.querySelector('[data-mvm-entrepreneur-ads]');
    const config = window.MvMEntrepreneurAds || {};
    if (!root || !config.api || !config.nonce) return;

    const form = root.querySelector('[data-mvm-entrepreneur-form]');
    const list = root.querySelector('[data-mvm-entrepreneur-list]');
    const listStatus = root.querySelector('[data-mvm-entrepreneur-list-status]');
    const formStatus = root.querySelector('[data-mvm-entrepreneur-form-status]');
    const placementWrap = root.querySelector('[data-mvm-entrepreneur-placements]');
    const imageInput = root.querySelector('[data-mvm-entrepreneur-image]');
    const imagePreview = root.querySelector('[data-mvm-entrepreneur-image-preview]');
    const resetButton = root.querySelector('[data-mvm-entrepreneur-reset]');
    const statusField = root.querySelector('[data-mvm-entrepreneur-status-field]');

    const el = (tag, className = '', text = '') => {
        const node = document.createElement(tag);
        if (className) node.className = className;
        if (text !== '') node.textContent = String(text);
        return node;
    };

    const setStatus = (node, message, error = false) => {
        if (!node) return;
        node.textContent = message || '';
        node.classList.toggle('is-error', Boolean(error));
    };

    const api = async (path, options = {}) => {
        const response = await fetch(`${config.api}${String(path).replace(/^\//, '')}`, {
            method: options.method || 'GET',
            credentials: 'same-origin',
            cache: 'no-store',
            headers: {
                'X-WP-Nonce': config.nonce,
                Accept: 'application/json',
                ...(options.body instanceof FormData ? {} : { 'Content-Type': 'application/json; charset=UTF-8' }),
                ...(options.headers || {})
            },
            body: options.body instanceof FormData ? options.body : (options.body ? JSON.stringify(options.body) : undefined)
        });
        let data = null;
        try { data = await response.json(); } catch (error) { data = null; }
        if (!response.ok) throw new Error(data?.message || 'De actie kon niet worden uitgevoerd.');
        return data;
    };

    const toLocalInput = (timestamp) => {
        const value = Number(timestamp || 0);
        if (!value) return '';
        const date = new Date(value * 1000);
        const local = new Date(date.getTime() - date.getTimezoneOffset() * 60000);
        return local.toISOString().slice(0, 16);
    };

    const formatDate = (timestamp) => {
        const value = Number(timestamp || 0);
        if (!value) return 'Geen einddatum';
        return new Intl.DateTimeFormat('nl-NL', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value * 1000));
    };

    const statusLabel = (status) => ({ draft: 'Ter beoordeling', active: 'Actief', paused: 'Gepauzeerd', ended: 'Beëindigd', suspended: 'Geschorst door MvM' }[status] || status);

    const renderPlacements = (placements) => {
        if (!placementWrap) return;
        placementWrap.replaceChildren();
        Object.entries(placements || {}).forEach(([key, label]) => {
            const item = el('label', 'mvm-entrepreneur__placement');
            const input = document.createElement('input');
            input.type = 'checkbox';
            input.name = 'placements[]';
            input.value = key;
            item.append(input, document.createTextNode(String(label)));
            placementWrap.appendChild(item);
        });
    };

    const selectedPlacements = () => Array.from(form?.querySelectorAll('input[name="placements[]"]:checked') || []).map((input) => input.value);

    const clearPreview = () => {
        if (!imagePreview) return;
        imagePreview.replaceChildren();
        imagePreview.hidden = true;
    };

    const showPreview = (url) => {
        clearPreview();
        if (!imagePreview || !url) return;
        const image = document.createElement('img');
        image.src = String(url);
        image.alt = 'Voorbeeld advertentieafbeelding';
        image.loading = 'lazy';
        imagePreview.appendChild(image);
        imagePreview.hidden = false;
    };

    const resetForm = () => {
        if (!form) return;
        form.reset();
        form.elements.id.value = '';
        form.elements.image_id.value = '';
        form.elements.cta.value = 'Bekijk ondernemer';
        form.elements.status.value = 'draft';
        if (statusField) statusField.hidden = true;
        clearPreview();
        setStatus(formStatus, 'Nieuwe advertenties gaan altijd eerst ter beoordeling.');
        root.querySelector('.mvm-entrepreneur__panel')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    };

    const fillForm = (item) => {
        if (!form || item.status === 'suspended') return;
        form.elements.id.value = String(item.id || '');
        form.elements.advertiser.value = item.advertiser || '';
        form.elements.headline.value = item.headline || '';
        form.elements.text.value = item.text || '';
        form.elements.cta.value = item.cta || 'Bekijk ondernemer';
        form.elements.url.value = item.url || '';
        form.elements.start_at.value = toLocalInput(item.start_at);
        form.elements.end_at.value = toLocalInput(item.end_at);
        form.elements.status.value = ['draft', 'paused', 'ended'].includes(item.status) ? item.status : 'draft';
        form.elements.image_id.value = String(item.image_id || '');
        if (statusField) statusField.hidden = false;
        Array.from(form.querySelectorAll('input[name="placements[]"]')).forEach((input) => {
            input.checked = Array.isArray(item.placements) && item.placements.includes(input.value);
        });
        showPreview(item.image_url || '');
        setStatus(
            formStatus,
            item.status === 'active'
                ? `Actieve advertentie #${item.id} bewerken. Na opslaan gaat de wijziging opnieuw ter beoordeling.`
                : `Advertentie #${item.id} bewerken.`
        );
        root.querySelector('.mvm-entrepreneur__panel')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    };

    const renderList = (items) => {
        if (!list) return;
        list.replaceChildren();
        if (!Array.isArray(items) || !items.length) {
            list.appendChild(el('p', 'mvm-entrepreneur__empty', 'Je hebt nog geen advertenties.'));
            return;
        }
        items.forEach((item) => {
            const article = el('article', 'mvm-entrepreneur__ad');
            article.dataset.id = String(item.id);
            if (item.image_url) {
                const media = el('div', 'mvm-entrepreneur__ad-media');
                const image = document.createElement('img');
                image.src = String(item.image_url);
                image.alt = '';
                image.loading = 'lazy';
                media.appendChild(image);
                article.appendChild(media);
            }
            const body = el('div', 'mvm-entrepreneur__ad-body');
            const top = el('div', 'mvm-entrepreneur__ad-top');
            top.append(el('strong', '', item.advertiser || 'Ondernemer'), el('span', `mvm-entrepreneur__badge is-${item.status || 'draft'}`, statusLabel(item.status)));
            body.appendChild(top);
            body.appendChild(el('h3', '', item.headline || 'Advertentie'));
            if (item.text) body.appendChild(el('p', '', item.text));
            const meta = el('p', 'mvm-entrepreneur__ad-meta', `${(item.placements || []).length} plaatsing(en) · ${formatDate(item.end_at)}`);
            body.appendChild(meta);
            const actions = el('div', 'mvm-entrepreneur__ad-actions');
            if (item.status !== 'suspended') {
                const edit = el('button', 'mvm-entrepreneur__secondary', 'Bewerken');
                edit.type = 'button';
                edit.addEventListener('click', () => fillForm(item));
                actions.appendChild(edit);
            } else {
                actions.appendChild(el('p', 'mvm-entrepreneur__suspension-note', 'Neem contact op met MvM-staf voor herstel.'));
            }
            const remove = el('button', 'mvm-entrepreneur__danger', 'Verwijderen');
            remove.type = 'button';
            remove.addEventListener('click', async () => {
                if (!window.confirm('Deze advertentie verwijderen?')) return;
                remove.disabled = true;
                try {
                    await api(`ads/${item.id}`, { method: 'DELETE' });
                    setStatus(listStatus, 'Advertentie verwijderd.');
                    await load();
                } catch (error) {
                    setStatus(listStatus, error.message, true);
                    remove.disabled = false;
                }
            });
            actions.appendChild(remove);
            body.appendChild(actions);
            article.appendChild(body);
            list.appendChild(article);
        });
    };

    const load = async () => {
        setStatus(listStatus, 'Advertenties laden…');
        try {
            const data = await api('ads');
            renderPlacements(data.placements || config.placements || {});
            renderList(data.items || []);
            setStatus(listStatus, `${(data.items || []).length} advertentie(s).`);
        } catch (error) {
            setStatus(listStatus, error.message, true);
        }
    };

    const uploadImage = async () => {
        const file = imageInput?.files?.[0];
        if (!file) return Number(form?.elements.image_id.value || 0);
        const body = new FormData();
        body.append('file', file, file.name);
        setStatus(formStatus, 'Afbeelding uploaden…');
        const result = await api('media', { method: 'POST', body });
        if (form) form.elements.image_id.value = String(result.id || '');
        showPreview(result.url || '');
        return Number(result.id || 0);
    };

    if (imageInput) {
        imageInput.addEventListener('change', () => {
            const file = imageInput.files?.[0];
            if (!file) return;
            const url = URL.createObjectURL(file);
            showPreview(url);
            window.setTimeout(() => URL.revokeObjectURL(url), 1000);
        });
    }

    if (resetButton) resetButton.addEventListener('click', resetForm);

    if (form) {
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            const submit = form.querySelector('button[type="submit"]');
            if (submit) submit.disabled = true;
            try {
                const imageId = await uploadImage();
                const id = Number(form.elements.id.value || 0);
                const payload = {
                    advertiser: form.elements.advertiser.value,
                    headline: form.elements.headline.value,
                    text: form.elements.text.value,
                    cta: form.elements.cta.value,
                    url: form.elements.url.value,
                    image_id: imageId,
                    placements: selectedPlacements(),
                    start_at: form.elements.start_at.value || 0,
                    end_at: form.elements.end_at.value || 0,
                    status: id ? form.elements.status.value : 'draft'
                };
                if (!payload.placements.length) throw new Error('Kies minimaal één plaatsing.');
                setStatus(formStatus, id ? 'Wijzigingen veilig opslaan…' : 'Advertentieaanvraag indienen…');
                const saved = await api(id ? `ads/${id}` : 'ads', { method: id ? 'PUT' : 'POST', body: payload });
                if (saved?.status === 'draft') {
                    setStatus(formStatus, 'Opgeslagen en ter beoordeling bij MvM.');
                } else if (saved?.status === 'paused') {
                    setStatus(formStatus, 'Advertentie is gepauzeerd.');
                } else if (saved?.status === 'ended') {
                    setStatus(formStatus, 'Advertentie is beëindigd.');
                } else {
                    setStatus(formStatus, 'Wijziging opgeslagen.');
                }
                resetForm();
                await load();
            } catch (error) {
                setStatus(formStatus, error.message, true);
            } finally {
                if (submit) submit.disabled = false;
            }
        });
    }

    resetForm();
    load();
})();
