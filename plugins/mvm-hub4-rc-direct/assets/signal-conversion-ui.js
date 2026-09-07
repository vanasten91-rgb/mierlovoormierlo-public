(() => {
    'use strict';

    const config = window.MvMHub4Config || {};
    const container = document.querySelector('[data-mvm-platform-container]');
    const messageNode = document.querySelector('[data-mvm-platform-message]');
    let rendering = false;

    if (!container || !config.restRoot || !config.restNonce) return;

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

    const setMessage = (text, type = '') => {
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

    const currentViewIsSignals = () => new URL(window.location.href).searchParams.get('platform') === 'signals' && !container.hidden;

    const convert = async (signalId, target, buttons) => {
        buttons.forEach((button) => { button.disabled = true; });
        setMessage(target === 'dossier' ? 'Dossier maken vanuit signaal…' : 'Opdracht maken vanuit signaal…');
        try {
            const result = await api(`signals/${signalId}/convert`, {
                method: 'POST',
                body: JSON.stringify({ target })
            });
            const label = target === 'dossier' ? 'dossier' : 'opdracht';
            setMessage(`Signaal omgezet naar ${label} #${result.targetId}.`, 'success');
            window.setTimeout(() => window.location.reload(), 350);
        } catch (error) {
            setMessage(error.message, 'error');
            buttons.forEach((button) => { button.disabled = false; });
        }
    };

    const render = async () => {
        if (rendering || !currentViewIsSignals() || container.querySelector('[data-mvm-signal-conversion]')) return;
        rendering = true;
        try {
            const platform = await api('platform');
            if (!platform?.writes?.signalTriage || (!platform?.writes?.dossierManage && !platform?.writes?.assignmentCreate)) return;

            const data = await api('signals?limit=75');
            const candidates = (data.items || []).filter((item) =>
                !['converted', 'closed', 'rejected'].includes(item.status)
                && !Number(item.dossierId || 0)
                && !Number(item.assignmentId || 0)
            );
            if (!candidates.length) return;

            const panel = element('section', { class: 'mvm-hub4__platform-card', 'data-mvm-signal-conversion': '1' });
            panel.appendChild(element('h3', { text: 'Signaal omzetten naar werk' }));
            panel.appendChild(element('p', { text: 'Maak vanuit een getrieerd signaal direct een redactioneel dossier of een opdracht. De koppeling wordt gelogd en het signaal wordt daarna gesloten als omgezet.' }));

            const select = element('select', { 'aria-label': 'Kies signaal om om te zetten' });
            candidates.forEach((item) => select.appendChild(element('option', { value: String(item.id), text: `#${item.id} · ${item.title}` })));
            panel.appendChild(select);

            const actions = element('div', { class: 'mvm-hub4__platform-actions' });
            const buttons = [];
            if (platform.writes.dossierManage) {
                const dossierButton = element('button', { type: 'button', class: 'mvm-hub4__platform-button', text: 'Maak dossier' });
                dossierButton.addEventListener('click', () => convert(Number(select.value), 'dossier', buttons));
                buttons.push(dossierButton);
                actions.appendChild(dossierButton);
            }
            if (platform.writes.assignmentCreate) {
                const assignmentButton = element('button', { type: 'button', class: 'mvm-hub4__platform-button mvm-hub4__platform-button--secondary', text: 'Maak opdracht' });
                assignmentButton.addEventListener('click', () => convert(Number(select.value), 'assignment', buttons));
                buttons.push(assignmentButton);
                actions.appendChild(assignmentButton);
            }
            panel.appendChild(actions);
            container.appendChild(panel);
        } catch (error) {
            setMessage(error.message, 'error');
        } finally {
            rendering = false;
        }
    };

    const observer = new MutationObserver(() => {
        if (currentViewIsSignals()) window.setTimeout(render, 0);
    });
    observer.observe(container, { childList: true });

    document.querySelectorAll('[data-mvm-platform-view="signals"]').forEach((button) => {
        button.addEventListener('click', () => window.setTimeout(render, 0));
    });
    window.addEventListener('popstate', () => window.setTimeout(render, 0));
    window.setTimeout(render, 0);
})();
