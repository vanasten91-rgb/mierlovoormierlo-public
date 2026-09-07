(function () {
  'use strict';

  function initQuickPanel() {
    var root = document.querySelector('[data-mvmh3-quick]');
    if (!root) return;

    var button = root.querySelector('[data-mvmh3-toggle]');
    var panel = root.querySelector('#mvmh3-quick-panel');
    if (!button || !panel) return;

    button.addEventListener('click', function () {
      var open = button.getAttribute('aria-expanded') === 'true';
      button.setAttribute('aria-expanded', open ? 'false' : 'true');
      panel.hidden = open;
    });

    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape' && button.getAttribute('aria-expanded') === 'true') {
        button.setAttribute('aria-expanded', 'false');
        panel.hidden = true;
        button.focus();
      }
    });
  }

  function secureConfig() {
    var config = window.MvMHubsV3Bridge;
    return config && config.secure && config.restRoot && config.nonce ? config : null;
  }

  function secureRequest(path, options) {
    var config = secureConfig();
    if (!config) {
      return Promise.reject(new Error('Secure Core is niet actief.'));
    }

    var requestOptions = Object.assign({
      method: 'GET',
      credentials: 'same-origin',
      headers: {
        'X-WP-Nonce': config.nonce,
        'Accept': 'application/json'
      }
    }, options || {});

    if (requestOptions.body && typeof requestOptions.body !== 'string') {
      requestOptions.headers['Content-Type'] = 'application/json';
      requestOptions.body = JSON.stringify(requestOptions.body);
    }

    return fetch(config.restRoot + path.replace(/^\//, ''), requestOptions).then(function (response) {
      return response.json().catch(function () { return {}; }).then(function (payload) {
        if (!response.ok) {
          throw new Error(payload && payload.message ? payload.message : 'De beveiligde actie is geweigerd.');
        }
        return payload;
      });
    });
  }

  function initAdminStatus() {
    var root = document.querySelector('[data-mvmh3-admin-status]');
    if (!root || !secureConfig()) return;

    secureRequest('admin/status').then(function (payload) {
      var wp = root.querySelector('[data-mvmh3-status="wordpress"]');
      var theme = root.querySelector('[data-mvmh3-status="theme"]');
      var secure = root.querySelector('[data-mvmh3-status="secure"]');
      var capabilities = root.querySelector('[data-mvmh3-status="capabilities"]');
      if (wp && payload.wordpress) wp.textContent = payload.wordpress;
      if (theme && payload.theme) {
        theme.textContent = [payload.theme.name, payload.theme.version].filter(Boolean).join(' ');
      }
      if (secure && payload.hubs_secure) secure.textContent = payload.hubs_secure;
      if (capabilities && payload.capabilities) {
        var drift = Number(payload.capabilities.drift_count || 0);
        var checked = Number(payload.capabilities.roles_checked || 0);
        capabilities.textContent = payload.capabilities.ok
          ? 'In orde · ' + checked + ' rollen gecontroleerd'
          : drift + ' afwijking' + (drift === 1 ? '' : 'en') + ' gevonden';
        capabilities.setAttribute('data-state', payload.capabilities.ok ? 'ok' : 'warning');
      }
    }).catch(function () {
      var secure = root.querySelector('[data-mvmh3-status="secure"]');
      var capabilities = root.querySelector('[data-mvmh3-status="capabilities"]');
      if (secure) secure.textContent = 'Status niet beschikbaar';
      if (capabilities) capabilities.textContent = 'Controle niet beschikbaar';
    });
  }

  function initModerationSources() {
    var root = document.querySelector('[data-mvmh3-moderation-sources]');
    if (!root || !secureConfig()) return;

    secureRequest('moderation/sources').then(function (payload) {
      var sources = payload && Array.isArray(payload.sources) ? payload.sources : [];
      sources.forEach(function (source) {
        if (!source || !source.key) return;
        var row = root.querySelector('[data-mvmh3-source="' + source.key + '"]');
        if (!row) return;
        var text = row.querySelector('span');
        row.setAttribute('data-state', source.available ? 'available' : 'unavailable');
        if (text) {
          text.textContent = source.available
            ? 'Beschikbaar via ' + source.provider + ' · details alleen op aanvraag'
            : 'Bron is momenteel niet beschikbaar';
        }
      });
    }).catch(function () {
      Array.prototype.forEach.call(root.querySelectorAll('[data-mvmh3-source] span'), function (node) {
        node.textContent = 'Bronstatus kon niet veilig worden opgehaald';
      });
    });
  }

  function initMaintenance() {
    var root = document.querySelector('[data-mvmh3-maintenance]');
    if (!root) return;

    var status = root.querySelector('[data-mvmh3-maintenance-status]');
    var buttons = Array.prototype.slice.call(root.querySelectorAll('[data-mvmh3-maintenance-action]'));
    if (!buttons.length) return;

    buttons.forEach(function (button) {
      button.addEventListener('click', function () {
        var action = button.getAttribute('data-mvmh3-maintenance-action');
        if (!action || button.disabled) return;

        buttons.forEach(function (item) { item.disabled = true; });
        if (status) status.textContent = 'Beveiligde onderhoudsactie wordt gecontroleerd…';

        secureRequest('admin/maintenance', {
          method: 'POST',
          body: { action: action }
        }).then(function (payload) {
          if (status) status.textContent = payload && payload.message ? payload.message : 'Onderhoudsactie uitgevoerd.';
        }).catch(function (error) {
          if (status) status.textContent = error && error.message ? error.message : 'Onderhoudsactie kon niet worden uitgevoerd.';
        }).finally(function () {
          buttons.forEach(function (item) { item.disabled = false; });
        });
      });
    });
  }

  function businessRequest(path, options) {
    var config = window.MvMHubsV3Business;
    if (!config || !config.restRoot || !config.nonce) {
      return Promise.reject(new Error('Vacaturebeheer is niet beschikbaar.'));
    }
    var opts = Object.assign({ method: 'GET', credentials: 'same-origin' }, options || {});
    opts.headers = Object.assign({ 'X-WP-Nonce': config.nonce, 'Accept': 'application/json' }, opts.headers || {});
    if (opts.body && !(opts.body instanceof FormData) && typeof opts.body !== 'string') {
      opts.headers['Content-Type'] = 'application/json; charset=UTF-8';
      opts.body = JSON.stringify(opts.body);
    }
    return fetch(config.restRoot + path.replace(/^\//, ''), opts).then(function (response) {
      return response.json().catch(function () { return {}; }).then(function (payload) {
        if (!response.ok) throw new Error(payload && payload.message ? payload.message : 'De vacatureactie kon niet worden uitgevoerd.');
        return payload;
      });
    });
  }

  function initBusinessVacancies() {
    var root = document.querySelector('[data-mvmh3-vacancies]');
    if (!root) return;
    var preview = root.getAttribute('data-mvmh3-preview') === '1';
    var form = root.querySelector('[data-mvmh3-vacancy-form]');
    var list = root.querySelector('[data-mvmh3-vacancy-list]');
    var formStatus = root.querySelector('[data-mvmh3-vacancy-status]');
    var listStatus = root.querySelector('[data-mvmh3-vacancy-list-status]');
    var statusField = root.querySelector('[data-mvmh3-vacancy-status-field]');
    var submit = form ? form.querySelector('button[type="submit"]') : null;
    if (!form || !list || !submit) return;

    function setStatus(node, text, error) {
      if (!node) return;
      node.textContent = text || '';
      node.setAttribute('data-state', error ? 'error' : 'ok');
    }

    function resetForm() {
      form.reset();
      form.elements.id.value = '';
      form.elements.location.value = 'Mierlo';
      form.elements.post_status.value = 'pending';
      form.elements.image_url.value = '';
      if (statusField) statusField.hidden = true;
      submit.textContent = 'Vacature indienen';
      setStatus(formStatus, 'Nieuwe vacatures gaan eerst ter beoordeling bij MvM.', false);
    }

    function fillForm(item) {
      var fields = ['title','company','location','employment','hours','salary_min','salary_max','salary_unit','closing_date','application_url','contact_name','contact_email','image_url','excerpt','content'];
      fields.forEach(function (name) {
        if (form.elements[name]) form.elements[name].value = item[name] || '';
      });
      form.elements.id.value = String(item.id || '');
      form.elements.post_status.value = ['draft', 'pending'].indexOf(item.post_status) >= 0 ? item.post_status : 'pending';
      if (statusField) statusField.hidden = false;
      submit.textContent = 'Wijzigingen indienen';
      setStatus(formStatus, 'Bewerk de vacature en dien de wijziging opnieuw in.', false);
      root.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function uploadImage() {
      var input = form.elements.image_file;
      var file = input && input.files ? input.files[0] : null;
      if (!file) return Promise.resolve(form.elements.image_url.value || '');
      var data = new FormData();
      data.append('file', file, file.name);
      setStatus(formStatus, 'Afbeelding uploaden…', false);
      return businessRequest('media', { method: 'POST', body: data }).then(function (payload) {
        form.elements.image_url.value = payload.url || '';
        return payload.url || '';
      });
    }

    function statusLabel(item) {
      var status = item && item.status ? item.status : item.post_status;
      return ({ published: 'Gepubliceerd', publish: 'Gepubliceerd', pending: 'Ter beoordeling', draft: 'Concept', expired: 'Verlopen', scheduled: 'Ingepland', future: 'Ingepland', private: 'Privé' })[status] || 'Onbekend';
    }

    function renderList(items) {
      list.replaceChildren();
      if (!items.length) {
        var empty = document.createElement('div');
        empty.className = 'mvmh3-empty';
        var strong = document.createElement('strong'); strong.textContent = 'Nog geen vacatures.';
        var span = document.createElement('span'); span.textContent = 'Je ingediende vacatures verschijnen hier.';
        empty.append(strong, span); list.appendChild(empty); return;
      }
      items.forEach(function (item) {
        var row = document.createElement('article'); row.className = 'mvmh3-vacancy-item';
        var copy = document.createElement('div');
        var title = document.createElement('strong'); title.textContent = item.title || 'Vacature';
        var meta = document.createElement('span'); meta.textContent = [item.company, statusLabel(item)].filter(Boolean).join(' · ');
        copy.append(title, meta);
        var actions = document.createElement('div'); actions.className = 'mvmh3-vacancy-item__actions';
        if (!preview) {
          var edit = document.createElement('button'); edit.type = 'button'; edit.className = 'mvmh3-primary'; edit.textContent = 'Bewerken'; edit.addEventListener('click', function () { fillForm(item); });
          actions.appendChild(edit);
        }
        if (item.permalink) {
          var view = document.createElement('a'); view.className = 'mvmh3-vacancy-link'; view.href = item.permalink; view.textContent = 'Bekijken'; actions.appendChild(view);
        }
        if (!preview) {
          var remove = document.createElement('button'); remove.type = 'button'; remove.className = 'mvmh3-vacancy-remove'; remove.textContent = 'Verwijderen';
          remove.addEventListener('click', function () {
            if (!window.confirm('Deze vacature verwijderen?')) return;
            remove.disabled = true;
            businessRequest('vacancies/' + Number(item.id), { method: 'DELETE' }).then(load).catch(function (error) {
              setStatus(listStatus, error.message, true); remove.disabled = false;
            });
          });
          actions.appendChild(remove);
        }
        row.append(copy, actions); list.appendChild(row);
      });
    }

    function load() {
      setStatus(listStatus, 'Vacatures laden…', false);
      return businessRequest('vacancies').then(function (payload) {
        var items = payload && Array.isArray(payload.items) ? payload.items : [];
        renderList(items);
        setStatus(listStatus, items.length + ' vacature' + (items.length === 1 ? '' : 's') + '.', false);
      }).catch(function (error) { setStatus(listStatus, error.message, true); });
    }

    if (!preview) {
      form.addEventListener('submit', function (event) {
        event.preventDefault(); submit.disabled = true;
        uploadImage().then(function (imageUrl) {
          var id = Number(form.elements.id.value || 0);
          var payload = {
            title: form.elements.title.value.trim(), company: form.elements.company.value.trim(), location: form.elements.location.value.trim(),
            employment: form.elements.employment.value, hours: form.elements.hours.value.trim(), salary_min: form.elements.salary_min.value,
            salary_max: form.elements.salary_max.value, salary_unit: form.elements.salary_unit.value, closing_date: form.elements.closing_date.value,
            application_url: form.elements.application_url.value.trim(), contact_name: form.elements.contact_name.value.trim(),
            contact_email: form.elements.contact_email.value.trim(), image_url: imageUrl, excerpt: form.elements.excerpt.value.trim(),
            content: form.elements.content.value.trim(), post_status: id ? form.elements.post_status.value : 'pending'
          };
          setStatus(formStatus, id ? 'Wijzigingen indienen…' : 'Vacature indienen…', false);
          return businessRequest(id ? 'vacancies/' + id : 'vacancies', { method: id ? 'PUT' : 'POST', body: payload });
        }).then(function () {
          setStatus(formStatus, 'Opgeslagen en ter beoordeling bij MvM.', false);
          resetForm(); return load();
        }).catch(function (error) {
          setStatus(formStatus, error.message, true);
        }).finally(function () { submit.disabled = false; });
      });
    }

    resetForm();
    load();
  }

  function vacancyReviewRequest(path, options) {
    var config = window.MvMHubsV3VacancyReview;
    if (!config || !config.restRoot || !config.nonce) {
      return Promise.reject(new Error('Vacaturebeoordeling is niet beschikbaar.'));
    }
    var opts = Object.assign({ method: 'GET', credentials: 'same-origin', cache: 'no-store' }, options || {});
    opts.headers = Object.assign({ 'X-WP-Nonce': config.nonce, 'Accept': 'application/json' }, opts.headers || {});
    if (opts.body && typeof opts.body !== 'string') {
      opts.headers['Content-Type'] = 'application/json; charset=UTF-8';
      opts.body = JSON.stringify(opts.body);
    }
    return fetch(config.restRoot + path.replace(/^\//, ''), opts).then(function (response) {
      return response.json().catch(function () { return {}; }).then(function (payload) {
        if (!response.ok) throw new Error(payload && payload.message ? payload.message : 'De vacatureactie kon niet worden uitgevoerd.');
        return payload;
      });
    });
  }

  function initVacancyReview() {
    var root = document.querySelector('[data-mvmh3-vacancy-review]');
    if (!root) return;
    var preview = root.getAttribute('data-mvmh3-preview') === '1';
    var list = root.querySelector('[data-mvmh3-vacancy-review-list]');
    var status = root.querySelector('[data-mvmh3-vacancy-review-status] span') || root.querySelector('[data-mvmh3-vacancy-review-status]');
    var form = root.querySelector('[data-mvmh3-vacancy-review-form]');
    var formStatus = root.querySelector('[data-mvmh3-vacancy-review-form-status]');
    var cancel = root.querySelector('[data-mvmh3-vacancy-review-cancel]');
    if (!list || !form) return;

    function label(item) {
      var value = item && (item.status || item.post_status);
      return ({ published: 'Gepubliceerd', publish: 'Gepubliceerd', pending: 'Ter beoordeling', draft: 'Concept', expired: 'Verlopen', future: 'Ingepland', scheduled: 'Ingepland', private: 'Privé' })[value] || 'Onbekend';
    }
    function message(text, error) {
      if (!status) return;
      status.textContent = text || '';
      status.setAttribute('data-state', error ? 'error' : 'ok');
    }
    function formMessage(text, error) {
      if (!formStatus) return;
      formStatus.textContent = text || '';
      formStatus.setAttribute('data-state', error ? 'error' : 'ok');
    }
    function setMetric(name, value) {
      var node = root.querySelector('[data-mvmh3-vacancy-metric="' + name + '"]');
      if (node) node.textContent = String(value);
    }
    function closeForm() {
      form.hidden = true;
      form.reset();
      if (form.elements.id) form.elements.id.value = '';
      formMessage('Controleer inhoud, contactgegevens en status voordat je opslaat.', false);
    }
    function fill(item) {
      ['title','company','location','employment','hours','closing_date','salary_min','salary_max','salary_unit','contact_name','contact_email','application_url','image_url','excerpt','content'].forEach(function (name) {
        if (form.elements[name]) form.elements[name].value = item[name] || '';
      });
      form.elements.id.value = String(item.id || '');
      var sourceStatus = item.post_status || '';
      form.elements.post_status.value = ['publish','pending','draft'].indexOf(sourceStatus) >= 0 ? sourceStatus : 'pending';
      form.hidden = false;
      formMessage('Je beoordeelt nu "' + (item.title || 'Vacature') + '".', false);
      form.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
    function payload() {
      return {
        title: form.elements.title.value.trim(),
        company: form.elements.company.value.trim(),
        location: form.elements.location.value.trim(),
        employment: form.elements.employment.value.trim(),
        hours: form.elements.hours.value.trim(),
        closing_date: form.elements.closing_date.value,
        salary_min: form.elements.salary_min.value,
        salary_max: form.elements.salary_max.value,
        salary_unit: form.elements.salary_unit.value,
        contact_name: form.elements.contact_name.value.trim(),
        contact_email: form.elements.contact_email.value.trim(),
        application_url: form.elements.application_url.value.trim(),
        image_url: form.elements.image_url.value.trim(),
        excerpt: form.elements.excerpt.value.trim(),
        content: form.elements.content.value.trim(),
        post_status: form.elements.post_status.value
      };
    }
    function render(items) {
      list.replaceChildren();
      setMetric('pending', items.filter(function (item) { return item.post_status === 'pending'; }).length);
      setMetric('published', items.filter(function (item) { return item.post_status === 'publish'; }).length);
      setMetric('draft', items.filter(function (item) { return item.post_status === 'draft'; }).length);
      if (!items.length) {
        var empty = document.createElement('div');
        empty.className = 'mvmh3-empty';
        var emptyTitle = document.createElement('strong');
        emptyTitle.textContent = 'Geen vacatures gevonden.';
        var emptyCopy = document.createElement('span');
        emptyCopy.textContent = 'Nieuwe organisatievacatures verschijnen hier zodra ze zijn ingediend.';
        empty.appendChild(emptyTitle);
        empty.appendChild(emptyCopy);
        list.appendChild(empty);
        return;
      }
      items.forEach(function (item) {
        var row = document.createElement('article'); row.className = 'mvmh3-vacancy-item';
        var copy = document.createElement('div');
        var title = document.createElement('strong'); title.textContent = item.title || 'Vacature';
        var meta = document.createElement('span'); meta.textContent = [item.company || 'Organisatie', item.location || 'Mierlo', label(item)].filter(Boolean).join(' · ');
        copy.append(title, meta);
        if (item.excerpt) { var excerpt = document.createElement('p'); excerpt.textContent = item.excerpt; copy.appendChild(excerpt); }
        var actions = document.createElement('div'); actions.className = 'mvmh3-vacancy-item__actions';
        if (!preview) {
          var edit = document.createElement('button'); edit.type = 'button'; edit.className = 'mvmh3-primary'; edit.textContent = item.post_status === 'pending' ? 'Beoordelen' : 'Bewerken'; edit.addEventListener('click', function () { fill(item); }); actions.appendChild(edit);
          var remove = document.createElement('button'); remove.type = 'button'; remove.className = 'mvmh3-vacancy-remove'; remove.textContent = 'Verwijderen';
          remove.addEventListener('click', function () {
            if (!window.confirm('Vacature "' + (item.title || item.id) + '" naar de prullenbak verplaatsen?')) return;
            remove.disabled = true;
            vacancyReviewRequest('vacancies/' + Number(item.id), { method: 'DELETE' }).then(load).catch(function (error) { message(error.message, true); remove.disabled = false; });
          });
          actions.appendChild(remove);
        }
        if (item.permalink) { var view = document.createElement('a'); view.className = 'mvmh3-vacancy-link'; view.href = item.permalink; view.target = '_blank'; view.rel = 'noopener noreferrer'; view.textContent = 'Bekijken'; actions.appendChild(view); }
        row.append(copy, actions); list.appendChild(row);
      });
    }
    function load() {
      message('Vacatures laden…', false);
      return vacancyReviewRequest('vacancies').then(function (data) {
        var items = data && Array.isArray(data.items) ? data.items : [];
        render(items);
        message(items.length + ' vacature' + (items.length === 1 ? '' : 's') + ' geladen.', false);
      }).catch(function (error) { message(error.message, true); });
    }
    if (!preview) {
      form.addEventListener('submit', function (event) {
        event.preventDefault();
        var id = Number(form.elements.id.value || 0);
        if (!id) return;
        var submit = form.querySelector('button[type="submit"]');
        if (submit) submit.disabled = true;
        formMessage('Besluit opslaan…', false);
        vacancyReviewRequest('vacancies/' + id, { method: 'PUT', body: payload() }).then(function () {
          formMessage('Vacature bijgewerkt.', false); closeForm(); return load();
        }).catch(function (error) { formMessage(error.message, true); }).finally(function () { if (submit) submit.disabled = false; });
      });
      if (cancel) cancel.addEventListener('click', closeForm);
    }
    closeForm();
    if (preview) return;
    load();
  }

  function mailReadOnlyConfig() {
    var config = window.MvMHubsV3MailReadOnly;
    return config && config.restRoot && config.nonce ? config : null;
  }

  function mailReadOnlyRequest(path) {
    var config = mailReadOnlyConfig();
    if (!config) return Promise.reject(new Error('De alleen-lezen mailadapter is niet beschikbaar.'));
    return fetch(config.restRoot + path.replace(/^\//, ''), {
      method: 'GET',
      credentials: 'same-origin',
      headers: {
        'X-WP-Nonce': config.nonce,
        'Accept': 'application/json'
      }
    }).then(function (response) {
      return response.json().catch(function () { return {}; }).then(function (payload) {
        if (!response.ok) {
          var message = payload && payload.message ? payload.message : 'De mailbox kon niet veilig worden gelezen.';
          throw new Error(message);
        }
        return payload;
      });
    });
  }

  function initMailReadOnly() {
    var root = document.querySelector('[data-mvmh3-mail-readonly]');
    var config = mailReadOnlyConfig();
    if (!root || !config) return;

    var loadButton = root.querySelector('[data-mvmh3-mail-load]');
    var status = root.querySelector('[data-mvmh3-mail-status]');
    var list = root.querySelector('[data-mvmh3-mail-list]');
    var message = root.querySelector('[data-mvmh3-mail-message]');
    var subject = root.querySelector('[data-mvmh3-mail-subject]');
    var meta = root.querySelector('[data-mvmh3-mail-meta]');
    var body = root.querySelector('[data-mvmh3-mail-body]');
    if (!loadButton || !status || !list || !message || !subject || !meta || !body) return;

    function setStatus(text, isError) {
      status.textContent = text;
      status.setAttribute('data-state', isError ? 'error' : 'ok');
    }

    function showMessage(uid) {
      setStatus('Bericht wordt alleen-lezen opgehaald…', false);
      mailReadOnlyRequest('messages/' + encodeURIComponent(String(uid))).then(function (payload) {
        var item = payload && payload.message ? payload.message : null;
        if (!item) throw new Error('Het bericht bevat geen leesbare inhoud.');
        subject.textContent = item.subject || '(geen onderwerp)';
        meta.textContent = [item.from ? 'Van: ' + item.from : '', item.to ? 'Aan: ' + item.to : '', item.date || ''].filter(Boolean).join(' · ');
        body.textContent = item.body || '(geen tekstinhoud gevonden)';
        message.hidden = false;
        setStatus('Bericht veilig gelezen zonder Seen-flag of mailboxwrite.', false);
      }).catch(function (error) {
        message.hidden = true;
        setStatus(error.message, true);
      });
    }

    function renderMessages(items) {
      list.replaceChildren();
      if (!items.length) {
        var empty = document.createElement('p');
        empty.className = 'mvmh3-muted';
        empty.textContent = 'Geen berichten in de INBOX.';
        list.appendChild(empty);
        return;
      }
      items.forEach(function (item) {
        if (!item || !item.uid) return;
        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'mvmh3-mail-readonly__item';
        button.setAttribute('data-seen', item.seen ? '1' : '0');

        var title = document.createElement('strong');
        title.textContent = item.subject || '(geen onderwerp)';
        var sender = document.createElement('span');
        sender.textContent = item.from || 'Afzender onbekend';
        var date = document.createElement('small');
        date.textContent = item.date || '';
        button.appendChild(title);
        button.appendChild(sender);
        button.appendChild(date);
        button.addEventListener('click', function () { showMessage(item.uid); });
        list.appendChild(button);
      });
    }

    loadButton.addEventListener('click', function () {
      loadButton.disabled = true;
      message.hidden = true;
      setStatus('INBOX wordt alleen-lezen opgehaald…', false);
      mailReadOnlyRequest('messages?limit=30&offset=0').then(function (payload) {
        var items = payload && Array.isArray(payload.messages) ? payload.messages : [];
        renderMessages(items);
        setStatus(items.length + ' bericht' + (items.length === 1 ? '' : 'en') + ' geladen · alleen-lezen.', false);
      }).catch(function (error) {
        list.replaceChildren();
        setStatus(error.message, true);
      }).finally(function () {
        loadButton.disabled = false;
      });
    });
  }

  function initSourceBulk() {
    var root = document.querySelector('[data-mvmh3-sources]');
    if (!root) return;

    var form = root.querySelector('.mvmh3-source-bulk');
    var all = root.querySelector('[data-mvmh3-source-all]');
    var boxes = Array.prototype.slice.call(root.querySelectorAll('[data-mvmh3-source-box]'));
    var count = root.querySelector('[data-mvmh3-source-count]');
    if (!form || !all || !boxes.length) return;

    function sync() {
      var enabled = boxes.filter(function (box) { return !box.disabled; });
      var selected = enabled.filter(function (box) { return box.checked; });
      all.checked = enabled.length > 0 && selected.length === enabled.length;
      all.indeterminate = selected.length > 0 && selected.length < enabled.length;
      if (count) count.textContent = selected.length + ' geselecteerd';
      root.classList.toggle('has-source-selection', selected.length > 0);
    }

    all.addEventListener('change', function () {
      boxes.forEach(function (box) {
        if (!box.disabled) box.checked = all.checked;
      });
      sync();
    });

    boxes.forEach(function (box) {
      box.addEventListener('change', sync);
    });

    form.addEventListener('submit', function (event) {
      var submitter = event.submitter || document.activeElement;
      if (submitter && submitter.name === 'single_action') return;
      if (!boxes.some(function (box) { return !box.disabled && box.checked; })) {
        event.preventDefault();
        if (count) {
          count.textContent = 'Selecteer eerst minimaal één bron';
          count.setAttribute('data-state', 'error');
          window.setTimeout(function () {
            count.removeAttribute('data-state');
            sync();
          }, 2200);
        }
      }
    });

    sync();
  }

  function init() {
    initQuickPanel();
    initAdminStatus();
    initModerationSources();
    initMaintenance();
    initBusinessVacancies();
    initVacancyReview();
    initMailReadOnly();
    initSourceBulk();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
}());

