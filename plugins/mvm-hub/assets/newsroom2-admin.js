(() => {
  'use strict';

  const root = document.querySelector('[data-mvm-newsroom2]');
  if (!root) return;

  const cfg = window.MVMNewsroom2 || {};
  const storageKey = 'mvm_newsroom2_theme';
  const media = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;
  let chatTimer = null;

  const systemTheme = () => (media && media.matches ? 'dark' : 'light');
  const storedTheme = () => {
    try {
      const value = window.localStorage.getItem(storageKey);
      return value === 'light' || value === 'dark' ? value : '';
    } catch (_) {
      return '';
    }
  };
  const preferredTheme = () => {
    const htmlTheme = document.documentElement.dataset.theme;
    if (htmlTheme === 'light' || htmlTheme === 'dark') return htmlTheme;
    if (document.body.classList.contains('dark-mode')) return 'dark';
    return storedTheme() || systemTheme();
  };
  const applyTheme = (theme) => {
    const safeTheme = theme === 'dark' ? 'dark' : 'light';
    root.dataset.theme = safeTheme;
    document.body.dataset.mvmNewsroomTheme = safeTheme;
    const button = root.querySelector('[data-nr2-theme-toggle]');
    if (button) {
      button.setAttribute('aria-pressed', safeTheme === 'dark' ? 'true' : 'false');
      const label = button.querySelector('[data-nr2-theme-label]');
      if (label) label.textContent = safeTheme === 'dark' ? 'Donkere modus' : 'Lichte modus';
    }
  };
  const saveTheme = (theme) => {
    try { window.localStorage.setItem(storageKey, theme); } catch (_) {}
  };

  const ajax = async (action, data = {}) => {
    if (!cfg.ajaxUrl || !cfg.nonce) throw new Error('De beveiligde Hub-verbinding ontbreekt.');
    const body = new URLSearchParams({ action, nonce: cfg.nonce, ...data });
    const response = await fetch(cfg.ajaxUrl, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
      body: body.toString(),
    });
    let payload;
    try { payload = await response.json(); } catch (_) { throw new Error('Onverwacht antwoord van de Hub.'); }
    if (!response.ok || !payload || payload.success !== true) {
      const message = payload && payload.data && payload.data.message ? payload.data.message : 'De actie kon niet worden uitgevoerd.';
      throw new Error(message);
    }
    return payload.data || {};
  };

  const dateLabel = (value) => {
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '';
    return new Intl.DateTimeFormat('nl-NL', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' }).format(date);
  };

  const el = (tag, className, text) => {
    const node = document.createElement(tag);
    if (className) node.className = className;
    if (typeof text === 'string') node.textContent = text;
    return node;
  };

  const setStatus = (selector, message, isError = false) => {
    const node = root.querySelector(selector);
    if (!node) return;
    node.textContent = message || '';
    node.classList.toggle('is-error', Boolean(isError));
  };

  const renderBoard = (data) => {
    const list = root.querySelector('[data-nr2-board-list]');
    if (!list) return;
    list.replaceChildren();
    const items = Array.isArray(data.items) ? data.items : [];
    if (!items.length) {
      list.append(el('p', 'mvm-nr2-empty', 'Er staan nog geen mededelingen op het prikbord.'));
      return;
    }

    items.forEach((item) => {
      const card = el('article', `mvm-nr2-notice${item.pinned ? ' is-pinned' : ''}${item.priority === 'important' ? ' is-important' : ''}`);
      const head = el('div', 'mvm-nr2-notice__head');
      const titleWrap = el('div');
      const meta = el('span', 'mvm-nr2-notice__meta', `${item.authorName || 'Staf'} · ${dateLabel(item.createdUtc)}`);
      const title = el('h3', '', item.title || 'Mededeling');
      titleWrap.append(meta, title);
      head.append(titleWrap);
      if (item.pinned) head.append(el('span', 'mvm-nr2-pinbadge', 'Vastgepind'));
      card.append(head, el('p', 'mvm-nr2-notice__body', item.message || ''));

      if (data.canModerate || item.canDelete) {
        const actions = el('div', 'mvm-nr2-itemactions');
        if (data.canModerate) {
          const pin = el('button', 'button button-small', item.pinned ? 'Losmaken' : 'Vastpinnen');
          pin.type = 'button';
          pin.dataset.boardPin = String(item.id);
          actions.append(pin);
        }
        if (item.canDelete) {
          const remove = el('button', 'button button-small', 'Verwijderen');
          remove.type = 'button';
          remove.dataset.boardDelete = String(item.id);
          actions.append(remove);
        }
        card.append(actions);
      }
      list.append(card);
    });
  };

  const loadBoard = async () => {
    if (!root.querySelector('[data-nr2-board]')) return;
    try {
      renderBoard(await ajax('mvm_nr2_board_list'));
    } catch (error) {
      const list = root.querySelector('[data-nr2-board-list]');
      if (list) list.replaceChildren(el('p', 'mvm-nr2-empty is-error', error.message));
    }
  };

  const renderChat = (data) => {
    const list = root.querySelector('[data-nr2-chat-list]');
    if (!list) return;
    const wasNearBottom = list.scrollHeight - list.scrollTop - list.clientHeight < 100;
    list.replaceChildren();
    const items = Array.isArray(data.items) ? data.items : [];
    if (!items.length) {
      list.append(el('p', 'mvm-nr2-empty', 'Nog geen berichten. Gebruik de chat voor korte interne afstemming.'));
      return;
    }

    items.forEach((item) => {
      const row = el('article', `mvm-nr2-chatmsg${item.mine ? ' is-mine' : ''}`);
      const meta = el('div', 'mvm-nr2-chatmsg__meta');
      meta.append(el('strong', '', item.authorName || 'Staf'), el('span', '', dateLabel(item.createdUtc)));
      row.append(meta, el('p', '', item.message || ''));
      if (item.canDelete) {
        const remove = el('button', 'mvm-nr2-chatmsg__delete', 'Verwijderen');
        remove.type = 'button';
        remove.dataset.chatDelete = String(item.id);
        row.append(remove);
      }
      list.append(row);
    });
    if (wasNearBottom || !list.dataset.loaded) list.scrollTop = list.scrollHeight;
    list.dataset.loaded = '1';
  };

  const loadChat = async () => {
    if (!root.querySelector('[data-nr2-chat]') || document.hidden) return;
    try {
      renderChat(await ajax('mvm_nr2_chat_list'));
    } catch (error) {
      setStatus('[data-nr2-chat-status]', error.message, true);
    }
  };

  applyTheme(preferredTheme());
  loadBoard();
  loadChat();
  if (root.querySelector('[data-nr2-chat]')) chatTimer = window.setInterval(loadChat, 12000);

  root.addEventListener('click', async (event) => {
    const themeButton = event.target.closest('[data-nr2-theme-toggle]');
    if (themeButton) {
      const next = root.dataset.theme === 'dark' ? 'light' : 'dark';
      saveTheme(next);
      applyTheme(next);
      return;
    }

    if (event.target.closest('[data-nr2-board-refresh]')) {
      loadBoard();
      return;
    }

    const pin = event.target.closest('[data-board-pin]');
    if (pin) {
      pin.disabled = true;
      try { await ajax('mvm_nr2_board_toggle_pin', { id: pin.dataset.boardPin }); await loadBoard(); }
      catch (error) { setStatus('[data-nr2-board-status]', error.message, true); }
      finally { pin.disabled = false; }
      return;
    }

    const boardDelete = event.target.closest('[data-board-delete]');
    if (boardDelete) {
      if (!window.confirm('Dit prikbordbericht verwijderen?')) return;
      boardDelete.disabled = true;
      try { await ajax('mvm_nr2_board_delete', { id: boardDelete.dataset.boardDelete }); await loadBoard(); }
      catch (error) { setStatus('[data-nr2-board-status]', error.message, true); }
      return;
    }

    const chatDelete = event.target.closest('[data-chat-delete]');
    if (chatDelete) {
      chatDelete.disabled = true;
      try { await ajax('mvm_nr2_chat_delete', { id: chatDelete.dataset.chatDelete }); await loadChat(); }
      catch (error) { setStatus('[data-nr2-chat-status]', error.message, true); }
    }
  });

  root.addEventListener('submit', async (event) => {
    const boardForm = event.target.closest('[data-nr2-board-form]');
    if (boardForm) {
      event.preventDefault();
      const submit = boardForm.querySelector('button[type="submit"]');
      const form = new FormData(boardForm);
      if (submit) submit.disabled = true;
      setStatus('[data-nr2-board-status]', 'Bezig met plaatsen…');
      try {
        await ajax('mvm_nr2_board_post', {
          title: String(form.get('title') || ''),
          message: String(form.get('message') || ''),
          priority: String(form.get('priority') || 'normal'),
        });
        boardForm.reset();
        setStatus('[data-nr2-board-status]', 'Mededeling geplaatst.');
        await loadBoard();
      } catch (error) {
        setStatus('[data-nr2-board-status]', error.message, true);
      } finally {
        if (submit) submit.disabled = false;
      }
      return;
    }

    const chatForm = event.target.closest('[data-nr2-chat-form]');
    if (chatForm) {
      event.preventDefault();
      const field = chatForm.querySelector('textarea[name="message"]');
      const submit = chatForm.querySelector('button[type="submit"]');
      const message = field ? field.value.trim() : '';
      if (!message) return;
      if (submit) submit.disabled = true;
      setStatus('[data-nr2-chat-status]', 'Versturen…');
      try {
        await ajax('mvm_nr2_chat_send', { message });
        if (field) field.value = '';
        setStatus('[data-nr2-chat-status]', '');
        await loadChat();
        if (field) field.focus();
      } catch (error) {
        setStatus('[data-nr2-chat-status]', error.message, true);
      } finally {
        if (submit) submit.disabled = false;
      }
    }
  });

  document.addEventListener('visibilitychange', () => {
    if (!document.hidden) loadChat();
  });

  window.addEventListener('beforeunload', () => {
    if (chatTimer) window.clearInterval(chatTimer);
  });

  if (media && typeof media.addEventListener === 'function') {
    media.addEventListener('change', () => {
      if (!storedTheme()) applyTheme(systemTheme());
    });
  }
})();
