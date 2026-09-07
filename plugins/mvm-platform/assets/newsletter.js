(() => {
    'use strict';

    const config = window.MvMNewsletter || {};
    const root = document.querySelector('[data-mvm-newsletter-app]');
    if (!root || !config.api) return;

    const request = async (path, options = {}) => {
        const base = String(config.api).replace(/\/$/, '') + '/';
        const url = new URL(String(path).replace(/^\//, ''), base);
        const headers = new Headers(options.headers || {});
        headers.set('Accept', 'application/json');
        if (options.body && !headers.has('Content-Type')) headers.set('Content-Type', 'application/json; charset=UTF-8');
        if (config.nonce) headers.set('X-WP-Nonce', config.nonce);
        const response = await fetch(url.toString(), {
            ...options,
            credentials: 'same-origin',
            cache: 'no-store',
            headers
        });
        let data = {};
        try { data = await response.json(); } catch (error) { throw new Error('De nieuwsbriefdienst gaf geen geldig antwoord.'); }
        if (!response.ok) throw new Error(data.message || 'De actie kon niet worden uitgevoerd.');
        return data;
    };

    const post = (path, payload) => request(path, { method: 'POST', body: JSON.stringify(payload) });

    const selectedTopics = (form) => Array.from(form.querySelectorAll('input[name="topics[]"]:checked')).map((input) => input.value);

    const setBusy = (form, busy) => {
        Array.from(form.querySelectorAll('button,input,select,textarea')).forEach((control) => {
            if (control.type !== 'hidden') control.disabled = Boolean(busy);
        });
        form.setAttribute('aria-busy', busy ? 'true' : 'false');
    };

    const subscribeForm = root.querySelector('[data-mvm-newsletter-subscribe]');
    if (subscribeForm) {
        subscribeForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            const status = subscribeForm.querySelector('[data-mvm-newsletter-subscribe-status]');
            const email = String(new FormData(subscribeForm).get('email') || '').trim();
            const topics = selectedTopics(subscribeForm);
            const consent = Boolean(subscribeForm.querySelector('input[name="consent"]:checked'));
            if (!consent || !email || !topics.length) {
                if (status) status.textContent = 'Vul je e-mailadres in, kies minimaal één onderwerp en geef toestemming.';
                return;
            }
            if (status) status.textContent = 'Bevestigingsmail aanvragen…';
            setBusy(subscribeForm, true);
            try {
                const data = await post('subscribe', { email, topics, consent: true, source: 'website' });
                if (status) status.textContent = data.message || 'Controleer je e-mail om de inschrijving te bevestigen.';
                subscribeForm.reset();
            } catch (error) {
                if (status) status.textContent = error.message;
            } finally {
                setBusy(subscribeForm, false);
            }
        });
    }

    const createTopicCheckbox = (key, label, checked) => {
        const wrap = document.createElement('label');
        wrap.className = 'mvm-newsletter__topic';
        const input = document.createElement('input');
        input.type = 'checkbox';
        input.name = 'topics[]';
        input.value = key;
        input.checked = Boolean(checked);
        const text = document.createElement('span');
        text.textContent = label;
        wrap.appendChild(input);
        wrap.appendChild(text);
        return wrap;
    };

    const preferencesCard = root.querySelector('[data-mvm-newsletter-preferences]');
    const preferencesForm = root.querySelector('[data-mvm-newsletter-preferences-form]');
    const preferencesTopics = root.querySelector('[data-mvm-newsletter-preference-topics]');
    const preferencesIntro = root.querySelector('[data-mvm-newsletter-preferences-intro]');
    const preferencesStatus = root.querySelector('[data-mvm-newsletter-preferences-status]');
    const unsubscribeBlock = root.querySelector('[data-mvm-newsletter-account-unsubscribe]');
    const unsubscribeButton = root.querySelector('[data-mvm-newsletter-account-unsubscribe-button]');
    const unsubscribeStatus = root.querySelector('[data-mvm-newsletter-unsubscribe-status]');

    const renderPreferences = (data) => {
        if (!preferencesCard || !preferencesForm || !preferencesTopics) return;
        preferencesCard.hidden = false;
        preferencesTopics.replaceChildren();
        const status = data && data.status ? String(data.status) : 'none';
        const active = status === 'active';
        const canUnsubscribe = status === 'active' || status === 'pending';
        Object.entries(config.topics || {}).forEach(([key, label]) => {
            preferencesTopics.appendChild(createTopicCheckbox(key, String(label), active && (data.topics || []).includes(key)));
        });
        preferencesForm.hidden = !active;
        if (unsubscribeBlock) unsubscribeBlock.hidden = !canUnsubscribe;
        if (preferencesIntro) {
            if (active) preferencesIntro.textContent = 'Je nieuwsbrief is actief. Pas hieronder je onderwerpen aan of schrijf je volledig uit.';
            else if (status === 'pending') preferencesIntro.textContent = 'Je inschrijving wacht nog op bevestiging via e-mail. Je kunt de aanvraag hieronder ook volledig stopzetten.';
            else if (status === 'unsubscribed') preferencesIntro.textContent = 'Je bent volledig afgemeld voor de nieuwsbrief.';
            else preferencesIntro.textContent = 'Er is voor je account-e-mailadres nog geen actieve nieuwsbriefinschrijving.';
        }
    };

    const loadPreferences = async () => {
        if (!config.loggedIn || !preferencesCard) return;
        try {
            renderPreferences(await request('preferences'));
        } catch (error) {
            preferencesCard.hidden = false;
            if (preferencesIntro) preferencesIntro.textContent = error.message;
            if (preferencesForm) preferencesForm.hidden = true;
            if (unsubscribeBlock) unsubscribeBlock.hidden = true;
        }
    };

    if (preferencesForm) {
        preferencesForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            const topics = selectedTopics(preferencesForm);
            if (!topics.length) {
                if (preferencesStatus) preferencesStatus.textContent = 'Kies minimaal één onderwerp. Gebruik “Volledig uitschrijven” om de nieuwsbrief stop te zetten.';
                return;
            }
            if (preferencesStatus) preferencesStatus.textContent = 'Voorkeuren opslaan…';
            setBusy(preferencesForm, true);
            try {
                const data = await request('preferences', { method: 'PUT', body: JSON.stringify({ topics }) });
                if (preferencesStatus) preferencesStatus.textContent = 'Je voorkeuren zijn opgeslagen.';
                renderPreferences(data);
            } catch (error) {
                if (preferencesStatus) preferencesStatus.textContent = error.message;
            } finally {
                setBusy(preferencesForm, false);
            }
        });
        loadPreferences();
    }

    if (unsubscribeButton) {
        unsubscribeButton.addEventListener('click', async () => {
            const confirmed = window.confirm('Weet je zeker dat je de nieuwsbrief volledig wilt stopzetten?');
            if (!confirmed) return;
            unsubscribeButton.disabled = true;
            if (unsubscribeBlock) unsubscribeBlock.setAttribute('aria-busy', 'true');
            if (unsubscribeStatus) unsubscribeStatus.textContent = 'Afmelding verwerken…';
            try {
                const data = await request('preferences', { method: 'DELETE' });
                renderPreferences(data);
                if (unsubscribeStatus) unsubscribeStatus.textContent = data.message || 'Je bent volledig afgemeld voor de nieuwsbrief.';
                if (preferencesIntro) preferencesIntro.textContent = data.message || 'Je bent volledig afgemeld voor de nieuwsbrief.';
            } catch (error) {
                if (unsubscribeStatus) unsubscribeStatus.textContent = error.message;
            } finally {
                unsubscribeButton.disabled = false;
                if (unsubscribeBlock) unsubscribeBlock.setAttribute('aria-busy', 'false');
            }
        });
    }

    const tokenAction = root.querySelector('[data-mvm-newsletter-token-action]');
    if (tokenAction && (config.route === 'confirm' || config.route === 'unsubscribe')) {
        const title = tokenAction.querySelector('[data-mvm-newsletter-action-title]');
        const status = tokenAction.querySelector('[data-mvm-newsletter-action-status]');
        const params = new URLSearchParams(window.location.hash.replace(/^#/, ''));
        const token = String(params.get('token') || '').trim();
        if (window.history && window.location.hash) {
            window.history.replaceState({}, document.title, window.location.pathname + window.location.search);
        }
        if (!token) {
            if (title) title.textContent = 'Link ongeldig';
            if (status) status.textContent = 'De beveiligde token ontbreekt. Open de volledige link uit de nieuwsbriefmail.';
        } else {
            const action = config.route === 'confirm' ? 'confirm' : 'unsubscribe';
            post(action, { token }).then((data) => {
                if (title) title.textContent = config.route === 'confirm' ? 'Inschrijving bevestigd' : 'Je bent afgemeld';
                if (status) status.textContent = data.message || (config.route === 'confirm' ? 'Je inschrijving is bevestigd.' : 'Je afmelding is verwerkt.');
                tokenAction.classList.add('is-success');
            }).catch((error) => {
                if (title) title.textContent = config.route === 'confirm' ? 'Bevestigen niet gelukt' : 'Afmelden niet gelukt';
                if (status) status.textContent = error.message;
                tokenAction.classList.add('is-error');
            });
        }
    }
})();
