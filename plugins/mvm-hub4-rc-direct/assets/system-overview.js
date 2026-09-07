(() => {
    'use strict';

    const config = window.MvMHub4SystemStats || {};
    const button = document.querySelector('[data-mvm-system-refresh]');
    const status = document.querySelector('[data-mvm-system-status]');
    const generated = document.querySelector('[data-mvm-system-generated]');
    const healthCard = document.querySelector('[data-mvm-health-card]');
    const healthOverall = document.querySelector('[data-mvm-health-overall]');
    const healthChecked = document.querySelector('[data-mvm-health-checked]');
    const healthList = document.querySelector('[data-mvm-health-list]');
    const healthTotal = document.querySelector('[data-health-total]');
    const healthLabels = {
        ok: 'In orde',
        warning: 'Waarschuwing',
        critical: 'Kritiek',
        unknown: 'Onbekend'
    };

    if (!button || !config.restRoot || !config.restNonce) {
        return;
    }

    const setStatus = (message = '', error = false) => {
        if (!status) return;
        status.textContent = message;
        status.classList.toggle('is-error', error);
    };

    const getPath = (object, path) => {
        return String(path || '').split('.').reduce((value, key) => {
            if (value && typeof value === 'object' && Object.prototype.hasOwnProperty.call(value, key)) {
                return value[key];
            }
            return 0;
        }, object);
    };

    const formatNumber = (value) => {
        const numeric = Number(value || 0);
        return new Intl.NumberFormat(document.documentElement.lang || 'nl-NL').format(Number.isFinite(numeric) ? numeric : 0);
    };

    const render = (data) => {
        const cardMap = new Map((Array.isArray(data.cards) ? data.cards : []).map((card) => [String(card.id), card]));
        document.querySelectorAll('[data-stat-card]').forEach((node) => {
            const card = cardMap.get(node.getAttribute('data-stat-card') || '');
            if (card) node.textContent = formatNumber(card.value);
        });

        document.querySelectorAll('[data-stat-path]').forEach((node) => {
            node.textContent = formatNumber(getPath(data, node.getAttribute('data-stat-path')));
        });

        if (generated && data.generatedAtUtc) {
            const date = new Date(data.generatedAtUtc);
            generated.textContent = Number.isNaN(date.getTime())
                ? 'Cijfers bijgewerkt.'
                : `Bijgewerkt: ${date.toLocaleString(document.documentElement.lang || 'nl-NL')} UTC-bron`;
        }
    };

    const renderHealth = (data) => {
        const overall = Object.prototype.hasOwnProperty.call(healthLabels, data.status) ? data.status : 'unknown';
        const summary = data && typeof data.summary === 'object' ? data.summary : {};
        const checks = Array.isArray(data.checks) ? data.checks : [];

        if (healthCard) {
            Object.keys(healthLabels).forEach((key) => healthCard.classList.remove(`mvm-system-health--${key}`));
            healthCard.classList.add(`mvm-system-health--${overall}`);
        }
        if (healthOverall) {
            healthOverall.setAttribute('data-status', overall);
            const label = healthOverall.querySelector('strong');
            if (label) label.textContent = healthLabels[overall];
        }

        document.querySelectorAll('[data-health-count]').forEach((node) => {
            const key = node.getAttribute('data-health-count') || 'unknown';
            node.textContent = formatNumber(summary[key]);
        });
        if (healthTotal) healthTotal.textContent = formatNumber(summary.total || checks.length);

        if (healthChecked && data.checked_at) {
            const date = new Date(data.checked_at);
            healthChecked.textContent = Number.isNaN(date.getTime())
                ? 'Laatste controle is bijgewerkt.'
                : `Laatste controle: ${date.toLocaleString(document.documentElement.lang || 'nl-NL')} UTC-bron`;
        }

        if (healthList) {
            healthList.replaceChildren();
            checks.forEach((check) => {
                const checkStatus = Object.prototype.hasOwnProperty.call(healthLabels, check.status) ? check.status : 'unknown';
                const item = document.createElement('li');
                item.className = `mvm-system-health__item mvm-system-health__item--${checkStatus}`;

                const statusLabel = document.createElement('span');
                statusLabel.className = 'mvm-system-health__item-status';
                statusLabel.textContent = healthLabels[checkStatus];

                const copy = document.createElement('div');
                const heading = document.createElement('strong');
                heading.textContent = String(check.label || 'Systeemcontrole');
                const summaryText = document.createElement('p');
                summaryText.textContent = String(check.summary || 'Geen aanvullende informatie.');

                copy.append(heading, summaryText);
                item.append(statusLabel, copy);
                healthList.appendChild(item);
            });
        }
    };

    const fetchJson = async (path) => {
        const url = new URL(path, config.restRoot);
        url.searchParams.set('fresh', 'true');
        const response = await fetch(url.toString(), {
            method: 'GET',
            credentials: 'same-origin',
            cache: 'no-store',
            headers: {
                'Accept': 'application/json',
                'X-WP-Nonce': config.restNonce
            }
        });
        const json = await response.json().catch(() => null);
        if (!response.ok) {
            throw new Error(json && json.message ? json.message : 'De systeemstatus kon niet worden vernieuwd.');
        }
        return json || {};
    };

    button.addEventListener('click', async () => {
        button.disabled = true;
        setStatus('Systeemstatus vernieuwen…');

        try {
            const [stats, health] = await Promise.all([
                fetchJson('system/stats'),
                fetchJson('system/health')
            ]);
            render(stats);
            renderHealth(health);
            setStatus('Systeemstatus en cijfers zijn bijgewerkt.');
        } catch (error) {
            setStatus(error instanceof Error ? error.message : 'De systeemstatus kon niet worden vernieuwd.', true);
        } finally {
            button.disabled = false;
        }
    });
})();
