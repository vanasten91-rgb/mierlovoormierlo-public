(() => {
  'use strict';

  const root = document.querySelector('[data-mvm-account-delete]');
  const config = window.MvMAccountDelete || {};
  if (!root || !config.apiUrl || !config.nonce) return;

  const form = root.querySelector('[data-mvm-account-delete-form]');
  const status = root.querySelector('[data-mvm-account-delete-status]');

  const messageFrom = (data, fallback) => (data && data.message ? data.message : fallback);
  const request = async (path, payload) => {
    const response = await fetch(`${config.apiUrl}${path}`, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': config.nonce,
      },
      body: JSON.stringify(payload),
    });
    const data = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(messageFrom(data, 'De actie kon niet worden uitgevoerd.'));
    return data;
  };

  if (form && status) {
    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      const button = form.querySelector('button[type="submit"]');
      const password = form.elements.password.value;
      const confirmed = form.elements.confirmed.checked;
      status.textContent = 'Bevestigingsmail wordt verstuurd…';
      button.disabled = true;
      try {
        const data = await request('request', { password, confirmed });
        form.elements.password.value = '';
        status.textContent = messageFrom(data, 'Controleer je e-mail.');
      } catch (error) {
        form.elements.password.value = '';
        status.textContent = error.message;
      } finally {
        button.disabled = false;
      }
    });
  }

  const match = window.location.hash.match(/^#mvm-account-delete=([A-Za-z0-9_-]{20,80})$/);
  if (!match || !status) return;

  const token = match[1];
  window.history.replaceState(null, document.title, window.location.pathname + window.location.search);
  if (!window.confirm('Je MvM-account en gekoppelde persoonsgegevens definitief verwijderen?')) {
    status.textContent = 'Account verwijderen is geannuleerd.';
    return;
  }

  status.textContent = 'Je account wordt veilig verwijderd…';
  request('confirm', { token })
    .then((data) => {
      status.textContent = messageFrom(data, 'Je account is verwijderd.');
      window.setTimeout(() => window.location.replace(data.redirect || '/'), 700);
    })
    .catch((error) => {
      status.textContent = error.message;
    });
})();
