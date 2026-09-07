(() => {
  'use strict';

  const config = window.MvMAccountLogin || {};
  const form = document.querySelector('[data-mvm-login-form]');
  const status = document.querySelector('[data-mvm-login-status]');
  const hash = window.location.hash.match(/^#mvm-account-delete=[A-Za-z0-9_-]{20,80}$/) ? window.location.hash : '';

  const destination = () => `${config.redirect || config.accountUrl || '/mijn-mierlo/'}${hash}`;
  if (config.loggedIn) {
    window.location.replace(destination());
    return;
  }
  if (!form || !status || !config.ajaxUrl || !config.nonce || !config.siteKey) return;

  const toggle = form.querySelector('[data-mvm-password-toggle]');
  const password = form.elements.password;
  if (toggle && password) {
    toggle.addEventListener('click', () => {
      const visible = password.type === 'text';
      password.type = visible ? 'password' : 'text';
      toggle.textContent = visible ? 'Toon' : 'Verberg';
      toggle.setAttribute('aria-pressed', visible ? 'false' : 'true');
    });
  }

  let widgetId = null;
  let submitting = false;
  const button = form.querySelector('button[type="submit"]');

  const finish = () => {
    submitting = false;
    button.disabled = false;
    if (widgetId !== null && window.grecaptcha) window.grecaptcha.reset(widgetId);
  };

  const authenticate = async (token) => {
    const body = new URLSearchParams({
      action: 'mvm_account_login',
      nonce: config.nonce,
      login: form.elements.login.value,
      password: form.elements.password.value,
      remember: form.elements.remember.checked ? '1' : '',
      redirect_to: config.redirect || config.accountUrl || '/mijn-mierlo/',
      recaptcha_token: token,
    });
    try {
      const response = await fetch(config.ajaxUrl, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
        body: body.toString(),
      });
      const data = await response.json().catch(() => ({}));
      form.elements.password.value = '';
      if (!response.ok || !data.success) throw new Error(data.data && data.data.message ? data.data.message : 'Inloggen is niet gelukt. Probeer opnieuw.');
      status.textContent = 'Inloggen gelukt. Je wordt doorgestuurd…';
      window.location.assign(`${data.data.redirect || config.accountUrl}${hash}`);
    } catch (error) {
      status.textContent = error.message;
      finish();
    }
  };

  form.addEventListener('submit', (event) => {
    event.preventDefault();
    if (submitting || !form.reportValidity()) return;
    if (!window.grecaptcha || typeof window.grecaptcha.render !== 'function') {
      status.textContent = 'De beveiligingscontrole kon niet worden geladen. Controleer je verbinding en probeer opnieuw.';
      return;
    }
    submitting = true;
    button.disabled = true;
    status.textContent = 'Beveiligde controle wordt uitgevoerd…';

    const execute = () => {
      if (widgetId === null) {
        widgetId = window.grecaptcha.render(form.querySelector('[data-mvm-recaptcha]'), {
          sitekey: config.siteKey,
          size: 'invisible',
          callback: authenticate,
          'expired-callback': finish,
          'error-callback': () => {
            status.textContent = 'De beveiligingscontrole is mislukt. Probeer opnieuw.';
            finish();
          },
        });
      }
      window.grecaptcha.execute(widgetId);
    };
    if (typeof window.grecaptcha.ready === 'function') window.grecaptcha.ready(execute);
    else execute();
  });
})();
