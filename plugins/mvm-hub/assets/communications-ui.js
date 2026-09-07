'use strict';

(() => {
  const q = (root, selector) => root ? root.querySelector(selector) : null;
  const qa = (root, selector) => root ? Array.from(root.querySelectorAll(selector)) : [];

  const icon = (name) => {
    const paths = {
      info: '<circle cx="12" cy="12" r="9"/><path d="M12 10v6M12 7h.01"/>',
      folder: '<path d="M3.5 6.5h6l2 2h9v9.5a2 2 0 0 1-2 2h-15a2 2 0 0 1-2-2V8.5a2 2 0 0 1 2-2Z"/>',
      plus: '<path d="M12 5v14M5 12h14"/>',
      check: '<path d="m5 12 4 4 10-10"/>',
      unread: '<path d="M4 6h16v12H4z"/><path d="m4 7 8 6 8-6"/>',
      star: '<path d="m12 3 2.7 5.5 6 .9-4.3 4.2 1 6-5.4-2.9-5.4 2.9 1-6-4.3-4.2 6-.9Z"/>',
      move: '<path d="M4 5h7l2 2h7v12H4z"/><path d="m10 11 4 0m0 0-2-2m2 2-2 2"/>',
      inbox: '<path d="M4 5h16v14H4z"/><path d="M4 14h5l2 2h2l2-2h5"/>',
      shield: '<path d="M12 3 5 6v5c0 4.6 2.8 7.5 7 10 4.2-2.5 7-5.4 7-10V6Z"/><path d="m9 12 2 2 4-4"/>',
      archive: '<path d="M4 7h16v13H4zM3 3h18v4H3z"/><path d="M9 12h6"/>',
      spam: '<path d="m8 3 8 0 5 5v8l-5 5H8l-5-5V8Z"/><path d="M12 7v6m0 4h.01"/>',
      trash: '<path d="M4 7h16M9 7V4h6v3m3 0-1 14H7L6 7"/>',
      pin: '<path d="m9 3 6 0-1 6 3 3H7l3-3Z"/><path d="M12 12v9"/>'
    };
    return `<svg class="mvm-mail-ui-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">${paths[name] || paths.info}</svg>`;
  };

  const createApi = (root) => {
    const base = String(root.dataset.restBase || '').replace(/\/$/, '');
    const nonce = String(root.dataset.restNonce || '');
    return async (path, options = {}) => {
      const headers = new Headers(options.headers || {});
      headers.set('Accept', 'application/json');
      if (nonce) headers.set('X-WP-Nonce', nonce);
      let body = options.body;
      if (body && !(body instanceof FormData) && typeof body !== 'string') {
        headers.set('Content-Type', 'application/json');
        body = JSON.stringify(body);
      }
      const response = await fetch(`${base}${path}`, {
        method: options.method || 'GET', credentials: 'same-origin', headers, body,
      });
      let payload = null;
      try { payload = await response.json(); } catch (_) { payload = null; }
      if (!response.ok) throw new Error(payload?.message || `HTTP ${response.status}`);
      return payload;
    };
  };

  const setNotice = (root, text, error = false) => {
    const notice = q(root, '[data-mail-notice]');
    if (!notice) return;
    notice.hidden = !text;
    notice.className = `mvm-alert${error ? ' mvm-alert--warning' : ''}`;
    notice.textContent = text || '';
  };

  const addGuide = (root) => {
    const toolbar = q(root, '.mvm-mail-client__toolbar');
    if (!toolbar || q(root, '[data-mail-ui-guide]')) return;
    const guide = document.createElement('section');
    guide.className = 'mvm-mail-ui-guide';
    guide.dataset.mailUiGuide = '';
    guide.setAttribute('aria-label', 'Uitleg redactie-mail');
    guide.innerHTML = `
      <div>${icon('inbox')}<span><strong>Redactie-mail</strong><small>Lees, zoek en beantwoord berichten vanuit één werkplek.</small></span></div>
      <div>${icon('folder')}<span><strong>Mappen</strong><small>Maak eigen dossiermappen en verplaats berichten direct.</small></span></div>
      <div>${icon('shield')}<span><strong>Veilige bijlagen</strong><small>Uploads gaan door de ingestelde malwarescan voordat ze verzonden worden.</small></span></div>`;
    toolbar.insertAdjacentElement('afterend', guide);
  };

  const enhanceFolderCreate = (root, api) => {
    const button = q(root, '[data-folder-create]');
    if (!button || button.dataset.mailUiEnhanced === '1') return;
    button.dataset.mailUiEnhanced = '1';
    button.classList.add('mvm-mail-folder-create');
    button.innerHTML = `${icon('plus')}<span>Nieuwe map</span>`;
    button.setAttribute('aria-label', 'Nieuwe mailmap maken');
    button.title = 'Maak een eigen map voor een dossier, onderwerp of redactionele workflow';

    const head = button.closest('.mvm-mail-folders__head');
    if (head && !q(root, '.mvm-mail-folders__hint')) {
      const hint = document.createElement('p');
      hint.className = 'mvm-mail-folders__hint';
      hint.textContent = 'Orden de redactie-inbox in eigen dossiermappen.';
      head.insertAdjacentElement('afterend', hint);
    }

    let dialog = q(root, '[data-mail-folder-dialog]');
    if (!dialog) {
      dialog = document.createElement('dialog');
      dialog.className = 'mvm-mail-folder-dialog';
      dialog.dataset.mailFolderDialog = '';
      dialog.innerHTML = `
        <form method="dialog" class="mvm-mail-folder-dialog__frame">
          <header><div>${icon('folder')}<div><small>Redactie-mail</small><h3 data-folder-dialog-title>Nieuwe map</h3></div></div><button type="button" class="mvm-icon-button" data-folder-dialog-close aria-label="Sluiten">×</button></header>
          <label>Mapnaam<input type="text" maxlength="120" autocomplete="off" data-folder-dialog-name placeholder="Bijv. Gemeenteraad, Sport of Dossier centrum"></label>
          <p class="mvm-mail-folder-dialog__help">Gebruik korte, herkenbare namen. Systeemmappen zoals Inbox en Sent blijven apart.</p>
          <footer><button type="button" class="mvm-button mvm-button--secondary" data-folder-dialog-close>Annuleren</button><button type="submit" class="mvm-button mvm-button--primary" data-folder-dialog-submit>Map maken</button></footer>
        </form>`;
      root.append(dialog);
      qa(dialog, '[data-folder-dialog-close]').forEach((close) => close.addEventListener('click', () => dialog.close()));
      q(dialog, 'form')?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const input = q(dialog, '[data-folder-dialog-name]');
        const name = String(input?.value || '').trim();
        if (!name) { input?.focus(); return; }
        try {
          const mode = String(dialog.dataset.folderDialogMode || 'create');
          const id = String(dialog.dataset.folderDialogId || '');
          await api(mode === 'rename' ? `/communications/mail/folders/${encodeURIComponent(id)}` : '/communications/mail/folders', { method: mode === 'rename' ? 'PUT' : 'POST', body: { name } });
          dialog.close();
          if (input) input.value = '';
          setNotice(root, mode === 'rename' ? `Map is hernoemd naar “${name}”.` : `Map “${name}” is aangemaakt.`);
          q(root, '[data-mail-refresh]')?.click();
        } catch (error) { setNotice(root, error.message || 'Mapactie mislukt.', true); }
      });
    }

    const open = (mode, row = null) => {
      dialog.dataset.folderDialogMode = mode;
      dialog.dataset.folderDialogId = mode === 'rename' ? String(row?.dataset.folderId || '') : '';
      const input = q(dialog, '[data-folder-dialog-name]');
      if (input) input.value = mode === 'rename' ? String(row?.dataset.folderName || '') : '';
      const title = q(dialog, '[data-folder-dialog-title]'); if (title) title.textContent = mode === 'rename' ? 'Map hernoemen' : 'Nieuwe map';
      const submit = q(dialog, '[data-folder-dialog-submit]'); if (submit) submit.textContent = mode === 'rename' ? 'Naam opslaan' : 'Map maken';
      if (typeof dialog.showModal === 'function') {
        dialog.showModal();
        setTimeout(() => { input?.focus(); input?.select(); }, 0);
      }
    };

    root.addEventListener('click', (event) => {
      const target = event.target instanceof Element ? event.target : null;
      const create = target?.closest('[data-folder-create]');
      const rename = target?.closest('[data-folder-rename]');
      if (create || rename) {
        event.preventDefault();
        event.stopPropagation();
        open(rename ? 'rename' : 'create', rename?.closest('[data-folder-row]') || null);
      }
    }, true);
  };

  const setupBulk = (root, api) => {
    if (root.dataset.manageMessages !== '1') return;
    const panel = q(root, '.mvm-mail-list-panel');
    const list = q(root, '[data-mail-list]');
    const search = q(root, '[data-mail-search-form]');
    if (!panel || !list || !search || q(root, '[data-mail-bulk]')) return;

    const selected = new Set();
    const bulk = document.createElement('div');
    bulk.className = 'mvm-mail-bulk';
    bulk.dataset.mailBulk = '';
    bulk.innerHTML = `
      <label class="mvm-mail-bulk__select-all"><input type="checkbox" data-mail-select-all><span>Alles</span></label>
      <span class="mvm-mail-bulk__count" data-mail-selection-count>0 geselecteerd</span>
      <div class="mvm-mail-bulk__actions" aria-label="Bulkacties">
        <button type="button" class="mvm-button mvm-button--secondary" data-mail-bulk-action="read">${icon('check')}<span>Gelezen</span></button>
        <button type="button" class="mvm-button mvm-button--secondary" data-mail-bulk-action="unread">${icon('unread')}<span>Ongelezen</span></button>
        <button type="button" class="mvm-button mvm-button--secondary" data-mail-bulk-action="flag">${icon('star')}<span>Vlag</span></button>
        <button type="button" class="mvm-button mvm-button--secondary" data-mail-bulk-action="pin">${icon('pin')}<span>Vast</span></button>
        <button type="button" class="mvm-button mvm-button--secondary" data-mail-bulk-action="archive">${icon('archive')}<span>Archief</span></button>
        <button type="button" class="mvm-button mvm-button--secondary" data-mail-bulk-action="spam">${icon('spam')}<span>Spam</span></button>
        <button type="button" class="mvm-button mvm-button--danger" data-mail-bulk-action="trash">${icon('trash')}<span>Verwijderen</span></button>
        <select data-mail-bulk-folder aria-label="Doelmap voor geselecteerde berichten"><option value="">Verplaats naar…</option></select>
        <button type="button" class="mvm-button mvm-button--secondary" data-mail-bulk-action="move">${icon('move')}<span>Verplaats</span></button>
      </div>`;
    search.insertAdjacentElement('afterend', bulk);

    const countNode = q(bulk, '[data-mail-selection-count]');
    const selectAll = q(bulk, '[data-mail-select-all]');
    const folderSelect = q(bulk, '[data-mail-bulk-folder]');

    const updateState = () => {
      if (countNode) countNode.textContent = `${selected.size} geselecteerd`;
      bulk.classList.toggle('has-selection', selected.size > 0);
      qa(bulk, '[data-mail-bulk-action]').forEach((button) => { button.disabled = selected.size === 0; });
      if (folderSelect) folderSelect.disabled = selected.size === 0;
      const boxes = qa(list, '[data-mail-select]');
      if (selectAll) {
        selectAll.checked = boxes.length > 0 && boxes.every((box) => box.checked);
        selectAll.indeterminate = boxes.some((box) => box.checked) && !selectAll.checked;
      }
    };

    const refreshFolders = () => {
      if (!folderSelect) return;
      const current = folderSelect.value;
      folderSelect.replaceChildren();
      const placeholder = document.createElement('option'); placeholder.value = ''; placeholder.textContent = 'Verplaats naar…'; folderSelect.append(placeholder);
      qa(root, '[data-folder-row]').forEach((row) => {
        const id = String(row.dataset.folderId || '');
        const name = String(row.dataset.folderName || 'Map');
        if (!id || id === String(root.querySelector('[data-folder-row] .mvm-mail-folder.is-active')?.closest('[data-folder-row]')?.dataset.folderId || '')) return;
        const option = document.createElement('option'); option.value = id; option.textContent = name; folderSelect.append(option);
      });
      if (Array.from(folderSelect.options).some((option) => option.value === current)) folderSelect.value = current;
    };

    const decorateRows = () => {
      qa(list, 'button[data-mail-message-id]').forEach((button) => {
        if (button.closest('.mvm-mail-row-shell')) return;
        const id = String(button.dataset.mailMessageId || '');
        if (!id) return;
        const shell = document.createElement('div');
        shell.className = 'mvm-mail-row-shell';
        const label = document.createElement('label');
        label.className = 'mvm-mail-row-select';
        label.setAttribute('aria-label', 'Selecteer bericht');
        const checkbox = document.createElement('input'); checkbox.type = 'checkbox'; checkbox.dataset.mailSelect = id;
        checkbox.checked = selected.has(id);
        checkbox.addEventListener('click', (event) => event.stopPropagation());
        checkbox.addEventListener('change', () => {
          if (checkbox.checked) selected.add(id); else selected.delete(id);
          shell.classList.toggle('is-selected', checkbox.checked);
          updateState();
        });
        label.append(checkbox);
        button.parentNode?.insertBefore(shell, button);
        shell.append(label, button);
        shell.classList.toggle('is-selected', checkbox.checked);
      });
      Array.from(selected).forEach((id) => {
        if (!q(list, `[data-mail-select="${CSS.escape(id)}"]`)) selected.delete(id);
      });
      updateState();
    };

    selectAll?.addEventListener('change', () => {
      qa(list, '[data-mail-select]').forEach((box) => {
        box.checked = !!selectAll.checked;
        const id = String(box.dataset.mailSelect || '');
        if (box.checked) selected.add(id); else selected.delete(id);
        box.closest('.mvm-mail-row-shell')?.classList.toggle('is-selected', box.checked);
      });
      updateState();
    });

    const runBulk = async (action) => {
      const ids = Array.from(selected);
      if (!ids.length) return;
      let destination = '';
      if (action === 'move') {
        destination = String(folderSelect?.value || '');
        if (!destination) { setNotice(root, 'Kies eerst een doelmap.', true); folderSelect?.focus(); return; }
      }
      if (action === 'trash' && !window.confirm(`${ids.length} geselecteerde bericht${ids.length === 1 ? '' : 'en'} naar de prullenbak verplaatsen?`)) return;
      bulk.classList.add('is-busy');
      qa(bulk, 'button,select,input').forEach((node) => { node.disabled = true; });
      try {
        const result = await api('/communications/mail/messages/bulk', { method: 'POST', body: { ids, action, folderId: destination } });
        const ok = Math.max(0, Number(result?.processed) || 0);
        const failed = Math.max(0, Number(result?.failed) || 0);
        selected.clear();
        setNotice(root, failed ? `${ok} verwerkt; ${failed} actie(s) mislukt.` : `${ok} bericht${ok === 1 ? '' : 'en'} bijgewerkt.`, failed > 0);
        q(root, '[data-mail-refresh]')?.click();
      } catch (error) {
        setNotice(root, error.message || 'Bulkactie mislukt.', true);
      } finally {
        bulk.classList.remove('is-busy');
        if (selectAll) selectAll.disabled = false;
        updateState();
      }
    };

    qa(bulk, '[data-mail-bulk-action]').forEach((button) => button.addEventListener('click', () => runBulk(String(button.dataset.mailBulkAction || ''))));

    const listObserver = new MutationObserver(decorateRows);
    listObserver.observe(list, { childList: true, subtree: false });
    const folderNav = q(root, '[data-mail-folders]');
    if (folderNav) new MutationObserver(refreshFolders).observe(folderNav, { childList: true, subtree: true });
    decorateRows();
    refreshFolders();
  };

  const enhanceButtons = (root) => {
    const map = [
      ['[data-mail-new]', 'plus', 'Nieuw bericht'],
      ['[data-mail-refresh]', 'inbox', 'Vernieuwen']
    ];
    map.forEach(([selector, iconName, label]) => {
      const button = q(root, selector);
      if (!button || button.dataset.mailUiIcon === '1') return;
      button.dataset.mailUiIcon = '1';
      button.innerHTML = `${icon(iconName)}<span>${label}</span>`;
    });
  };

  const init = (root) => {
    if (root.dataset.mailUiReady === '1') return;
    root.dataset.mailUiReady = '1';
    const api = createApi(root);
    addGuide(root);
    enhanceButtons(root);
    enhanceFolderCreate(root, api);
    setupBulk(root, api);
  };

  document.addEventListener('DOMContentLoaded', () => qa(document, '[data-mvm-mail]').forEach(init));
})();
