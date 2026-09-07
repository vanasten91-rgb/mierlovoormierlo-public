(() => {
    'use strict';

    const config = window.MvMHub4Config || {};
    const panel = document.querySelector('[data-mvm-my-work]');
    if (!panel || !config.restRoot || !config.restNonce) {
        return;
    }

    const list = panel.querySelector('[data-mvm-my-work-list]');
    const status = panel.querySelector('[data-mvm-my-work-status]');
    const form = panel.querySelector('[data-mvm-signal-form]');
    const titleInput = panel.querySelector('[data-mvm-signal-title]');
    const typeInput = panel.querySelector('[data-mvm-signal-type]');
    const submit = panel.querySelector('[data-mvm-signal-submit]');

    const api = async (path, options = {}) => {
        const url = new URL(path.replace(/^\//, ''), config.restRoot);
        const headers = new Headers(options.headers || {});
        headers.set('X-WP-Nonce', config.restNonce);
        headers.set('Accept', 'application/json');
        if (options.body) {
            headers.set('Content-Type', 'application/json; charset=UTF-8');
        }
        const response = await fetch(url.toString(), {
            ...options,
            credentials: 'same-origin',
            cache: 'no-store',
            headers
        });
        const json = await response.json().catch(() => null);
        if (!response.ok) {
            throw new Error(json && json.message ? json.message : 'De actie kon niet worden uitgevoerd.');
        }
        return json;
    };

    const text = (tag, className, value) => {
        const node = document.createElement(tag);
        if (className) node.className = className;
        node.textContent = value;
        return node;
    };

    const setStatus = (message = '', error = false) => {
        if (!status) return;
        status.textContent = message;
        status.classList.toggle('is-error', error);
    };

    const nextAction = (item) => {
        if (item.status === 'signal' || item.status === 'assigned') return { label: 'Starten', status: 'in_progress' };
        if (item.status === 'in_progress') return { label: 'Naar beoordeling', status: 'review' };
        if (item.status === 'review') return { label: 'Verder bewerken', status: 'in_progress' };
        return null;
    };

    const updateAssignment = async (item, next, button) => {
        button.disabled = true;
        setStatus('Opdracht bijwerken…');
        try {
            await api(`assignments/${item.id}`, {
                method: 'POST',
                body: JSON.stringify({ status: next.status })
            });
            setStatus('Opdracht bijgewerkt.');
            await load();
        } catch (error) {
            button.disabled = false;
            setStatus(error.message, true);
        }
    };

    const render = (items) => {
        while (list && list.firstChild) list.removeChild(list.firstChild);
        if (!list) return;

        if (!items.length) {
            list.appendChild(text('p', 'mvm-hub4__my-work-empty', 'Er staat nu niets open voor jou.'));
            return;
        }

        items.forEach((item) => {
            const row = document.createElement('article');
            row.className = 'mvm-hub4__my-work-item';

            const main = document.createElement('div');
            main.className = 'mvm-hub4__my-work-main';
            main.appendChild(text('strong', '', item.title || 'Naamloze opdracht'));

            const meta = document.createElement('div');
            meta.className = 'mvm-hub4__my-work-meta';
            meta.appendChild(text('span', 'mvm-hub4__status-chip', item.statusLabel || item.status));
            meta.appendChild(text('span', '', `Prioriteit ${item.priority || 2}`));
            if (item.dueAtUtc) meta.appendChild(text('span', '', `Deadline ${item.dueAtUtc}`));
            main.appendChild(meta);
            row.appendChild(main);

            const action = nextAction(item);
            if (action) {
                const button = text('button', 'mvm-hub4__button mvm-hub4__button--secondary', action.label);
                button.type = 'button';
                button.addEventListener('click', () => updateAssignment(item, action, button));
                row.appendChild(button);
            }

            list.appendChild(row);
        });
    };

    const load = async () => {
        setStatus('Mijn werk laden…');
        try {
            const data = await api('assignments?scope=mine&limit=8');
            render(Array.isArray(data.items) ? data.items : []);
            setStatus('');
        } catch (error) {
            setStatus(error.message, true);
        }
    };

    if (form) {
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            const title = String(titleInput?.value || '').trim();
            const type = String(typeInput?.value || 'news');
            if (!title) {
                setStatus('Vul eerst een korte titel voor het signaal in.', true);
                titleInput?.focus();
                return;
            }
            if (submit) submit.disabled = true;
            setStatus('Signaal opslaan…');
            try {
                await api('assignments', {
                    method: 'POST',
                    body: JSON.stringify({ title, type })
                });
                if (titleInput) titleInput.value = '';
                setStatus('Signaal toegevoegd.');
                await load();
            } catch (error) {
                setStatus(error.message, true);
            } finally {
                if (submit) submit.disabled = false;
            }
        });
    }

    load();
})();
