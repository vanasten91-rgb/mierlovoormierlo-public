(function () {
  'use strict';

  const config = window.MvMMarketplace || {};
  const app = document.querySelector('[data-mvm-marketplace-app]');
  const single = document.querySelector('[data-mvm-marketplace-single]');

  async function request(path, options) {
    const opts = Object.assign({ method: 'GET', credentials: 'same-origin' }, options || {});
    opts.headers = Object.assign({}, opts.headers || {});
    if (config.nonce) {
      opts.headers['X-WP-Nonce'] = config.nonce;
    }
    if (opts.body && !(opts.body instanceof FormData) && typeof opts.body !== 'string') {
      opts.headers['Content-Type'] = 'application/json';
      opts.body = JSON.stringify(opts.body);
    }
    const response = await fetch(String(config.api || '') + path, opts);
    let payload = null;
    try {
      payload = await response.json();
    } catch (error) {
      payload = null;
    }
    if (!response.ok) {
      const message = payload && payload.message ? payload.message : 'De actie kon niet worden uitgevoerd.';
      throw new Error(message);
    }
    return { data: payload, response: response };
  }

  function formatPrice(item) {
    if (item.price_type === 'free') return 'Gratis';
    if (item.price_type === 'swap') return 'Ruilen';
    const euros = Number(item.price_cents || 0) / 100;
    return new Intl.NumberFormat('nl-NL', { style: 'currency', currency: 'EUR' }).format(euros);
  }

  function statusLabel(status) {
    return ({ active: 'Beschikbaar', reserved: 'Gereserveerd', sold: 'Verkocht', expired: 'Verlopen', moderated: 'Geblokkeerd' })[status] || status;
  }

  function el(name, className, text) {
    const node = document.createElement(name);
    if (className) node.className = className;
    if (text !== undefined && text !== null) node.textContent = String(text);
    return node;
  }

  function card(item, mine) {
    const article = el('article', 'mvm-marketplace-card');
    article.dataset.mvmListingId = String(item.id);
    const link = el('a', 'mvm-marketplace-card__image');
    link.href = String(item.url || '#');
    link.setAttribute('aria-label', String(item.title || 'Advertentie'));
    const imageUrl = item.featured_image || (Array.isArray(item.gallery) ? item.gallery[0] : '');
    if (imageUrl) {
      const image = document.createElement('img');
      image.src = String(imageUrl);
      image.alt = '';
      image.loading = 'lazy';
      link.appendChild(image);
    } else {
      link.appendChild(el('span', '', 'MvM'));
    }
    article.appendChild(link);

    const body = el('div', 'mvm-marketplace-card__body');
    const meta = el('div', 'mvm-marketplace-card__meta');
    meta.appendChild(el('strong', '', formatPrice(item)));
    if (item.status && item.status !== 'active') meta.appendChild(el('span', '', statusLabel(item.status)));
    body.appendChild(meta);

    const heading = el('h2');
    const titleLink = el('a', '', item.title || 'Advertentie');
    titleLink.href = String(item.url || '#');
    heading.appendChild(titleLink);
    body.appendChild(heading);
    if (item.location) body.appendChild(el('p', 'mvm-marketplace-card__location', item.location));

    if (mine) {
      const actions = el('div', 'mvm-marketplace-owner__actions');
      ['active', 'reserved', 'sold'].forEach(function (status) {
        const button = el('button', '', statusLabel(status));
        button.type = 'button';
        button.dataset.mvmMineStatus = status;
        actions.appendChild(button);
      });
      const remove = el('button', '', 'Verwijderen');
      remove.type = 'button';
      remove.dataset.mvmMineDelete = '1';
      actions.appendChild(remove);
      body.appendChild(actions);
    }

    article.appendChild(body);
    return article;
  }

  function setStatus(node, message, isError) {
    if (!node) return;
    node.textContent = message || '';
    node.style.color = isError ? 'var(--mvm-marketplace-danger)' : '';
  }

  function debounce(fn, wait) {
    let timer = 0;
    return function () {
      const args = arguments;
      window.clearTimeout(timer);
      timer = window.setTimeout(function () { fn.apply(null, args); }, wait);
    };
  }

  if (app) {
    const grid = app.querySelector('[data-mvm-marketplace-grid]');
    const statusNode = app.querySelector('[data-mvm-marketplace-status]');
    const search = app.querySelector('[data-mvm-marketplace-search]');
    const category = app.querySelector('[data-mvm-marketplace-category]');
    const filters = app.querySelector('[data-mvm-marketplace-filters]');
    const pager = app.querySelector('[data-mvm-marketplace-pager]');
    const tabs = Array.from(app.querySelectorAll('[data-mvm-marketplace-tab]'));
    let mode = 'browse';
    let page = 1;

    function renderItems(items, mine) {
      grid.replaceChildren();
      if (!Array.isArray(items) || !items.length) {
        grid.appendChild(el('p', 'mvm-marketplace__empty', mine ? 'Je hebt nog geen advertenties.' : 'Geen advertenties gevonden.'));
        return;
      }
      items.forEach(function (item) { grid.appendChild(card(item, mine)); });
    }

    function renderPager(totalPages) {
      pager.replaceChildren();
      if (mode !== 'browse' || totalPages <= 1) return;
      const prev = el('button', '', 'Vorige');
      prev.type = 'button';
      prev.disabled = page <= 1;
      prev.addEventListener('click', function () { if (page > 1) { page -= 1; loadBrowse(); } });
      const label = el('span', '', 'Pagina ' + page + ' van ' + totalPages);
      const next = el('button', '', 'Volgende');
      next.type = 'button';
      next.disabled = page >= totalPages;
      next.addEventListener('click', function () { if (page < totalPages) { page += 1; loadBrowse(); } });
      pager.append(prev, label, next);
    }

    async function loadBrowse() {
      mode = 'browse';
      setStatus(statusNode, 'Aanbod laden…', false);
      const params = new URLSearchParams({ page: String(page), per_page: '12' });
      if (search && search.value.trim()) params.set('search', search.value.trim());
      if (category && Number(category.value)) params.set('category', category.value);
      try {
        const result = await request('?' + params.toString());
        renderItems(result.data, false);
        renderPager(Number(result.response.headers.get('X-WP-TotalPages') || 1));
        setStatus(statusNode, '', false);
      } catch (error) {
        setStatus(statusNode, error.message, true);
      }
    }

    async function loadMine() {
      mode = 'mine';
      pager.replaceChildren();
      setStatus(statusNode, 'Je advertenties laden…', false);
      try {
        const result = await request('/mine');
        renderItems(result.data, true);
        setStatus(statusNode, '', false);
      } catch (error) {
        setStatus(statusNode, error.message, true);
      }
    }

    const refreshBrowse = debounce(function () { page = 1; loadBrowse(); }, 250);
    if (search) search.addEventListener('input', refreshBrowse);
    if (category) category.addEventListener('change', refreshBrowse);

    tabs.forEach(function (tab) {
      tab.addEventListener('click', function () {
        tabs.forEach(function (candidate) { candidate.classList.toggle('is-active', candidate === tab); });
        const nextMode = tab.dataset.mvmMarketplaceTab;
        if (filters) filters.hidden = nextMode === 'mine';
        if (nextMode === 'mine') loadMine(); else { page = 1; loadBrowse(); }
      });
    });

    grid.addEventListener('click', async function (event) {
      const statusButton = event.target.closest('[data-mvm-mine-status]');
      const deleteButton = event.target.closest('[data-mvm-mine-delete]');
      const cardNode = event.target.closest('[data-mvm-listing-id]');
      if (!cardNode || (!statusButton && !deleteButton)) return;
      const id = Number(cardNode.dataset.mvmListingId || 0);
      if (!id) return;
      event.preventDefault();
      try {
        if (statusButton) {
          statusButton.disabled = true;
          await request('/' + id + '/status', { method: 'POST', body: { status: statusButton.dataset.mvmMineStatus } });
        } else if (deleteButton) {
          if (!window.confirm('Deze advertentie verwijderen?')) return;
          deleteButton.disabled = true;
          await request('/' + id, { method: 'DELETE' });
        }
        await loadMine();
      } catch (error) {
        setStatus(statusNode, error.message, true);
        if (statusButton) statusButton.disabled = false;
        if (deleteButton) deleteButton.disabled = false;
      }
    });

    const dialog = app.querySelector('[data-mvm-marketplace-dialog]');
    const form = app.querySelector('[data-mvm-marketplace-form]');
    const formStatus = app.querySelector('[data-mvm-marketplace-form-status]');
    app.querySelectorAll('[data-mvm-marketplace-open-create]').forEach(function (button) {
      button.addEventListener('click', function () {
        if (dialog && typeof dialog.showModal === 'function') dialog.showModal();
        else if (dialog) dialog.setAttribute('open', 'open');
      });
    });
    app.querySelectorAll('[data-mvm-marketplace-close]').forEach(function (button) {
      button.addEventListener('click', function () { if (dialog && typeof dialog.close === 'function') dialog.close(); else if (dialog) dialog.removeAttribute('open'); });
    });

    async function uploadPhotos(files) {
      const ids = [];
      const maximum = Math.min(Number(config.maxGallery || 8), files.length);
      for (let index = 0; index < maximum; index += 1) {
        const data = new FormData();
        data.append('file', files[index], files[index].name);
        setStatus(formStatus, 'Foto ' + (index + 1) + ' van ' + maximum + ' uploaden…', false);
        const uploaded = await request('/media', { method: 'POST', body: data });
        ids.push(Number(uploaded.data.id));
      }
      return ids.filter(Boolean);
    }

    if (form) {
      const priceType = form.elements.price_type;
      const price = form.elements.price;
      function syncPrice() {
        const enabled = priceType.value === 'fixed';
        price.disabled = !enabled;
        if (!enabled) price.value = '0';
      }
      priceType.addEventListener('change', syncPrice);
      syncPrice();

      form.addEventListener('submit', async function (event) {
        event.preventDefault();
        const submit = form.querySelector('button[type="submit"]');
        submit.disabled = true;
        try {
          const files = Array.from(form.elements.photos.files || []);
          if (files.length > Number(config.maxGallery || 8)) throw new Error('Kies maximaal ' + String(config.maxGallery || 8) + ' foto’s.');
          const gallery = await uploadPhotos(files);
          setStatus(formStatus, 'Advertentie plaatsen…', false);
          const euros = Number.parseFloat(form.elements.price.value || '0');
          const payload = {
            title: form.elements.title.value,
            description: form.elements.description.value,
            price_type: form.elements.price_type.value,
            price_cents: Number.isFinite(euros) ? Math.round(Math.max(0, euros) * 100) : 0,
            condition: form.elements.condition.value,
            categories: [Number(form.elements.category.value)],
            location: form.elements.location.value,
            gallery: gallery
          };
          const created = await request('', { method: 'POST', body: payload });
          form.reset();
          syncPrice();
          setStatus(formStatus, 'Advertentie geplaatst.', false);
          window.location.assign(created.data.url || config.archiveUrl || window.location.href);
        } catch (error) {
          setStatus(formStatus, error.message, true);
        } finally {
          submit.disabled = false;
        }
      });
    }
  }

  if (single) {
    const id = Number(single.dataset.mvmMarketplaceSingle || 0);
    const ownerStatus = single.querySelector('[data-mvm-marketplace-owner-status]');
    single.querySelectorAll('[data-mvm-marketplace-set-status]').forEach(function (button) {
      button.addEventListener('click', async function () {
        button.disabled = true;
        try {
          await request('/' + id + '/status', { method: 'POST', body: { status: button.dataset.mvmMarketplaceSetStatus } });
          setStatus(ownerStatus, 'Status aangepast.', false);
          window.location.reload();
        } catch (error) {
          setStatus(ownerStatus, error.message, true);
          button.disabled = false;
        }
      });
    });

    const reportForm = single.querySelector('[data-mvm-marketplace-report-form]');
    if (reportForm) {
      const reportStatus = single.querySelector('[data-mvm-marketplace-report-status]');
      reportForm.addEventListener('submit', async function (event) {
        event.preventDefault();
        const button = reportForm.querySelector('button[type="submit"]');
        button.disabled = true;
        try {
          await request('/' + id + '/report', { method: 'POST', body: { reason: reportForm.elements.reason.value } });
          reportForm.reset();
          setStatus(reportStatus, 'Bedankt. De melding is naar de moderatie gestuurd.', false);
        } catch (error) {
          setStatus(reportStatus, error.message, true);
          button.disabled = false;
        }
      });
    }
  }
}());
