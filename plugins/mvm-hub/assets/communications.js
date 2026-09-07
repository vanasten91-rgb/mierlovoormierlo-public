'use strict';

(() => {
  const qs = (root, selector) => root ? root.querySelector(selector) : null;
  const qsa = (root, selector) => root ? Array.from(root.querySelectorAll(selector)) : [];
  const unique = (items) => Array.from(new Set(items.filter(Boolean)));
  const emails = (value) => unique(String(value || '').split(/[\s,;]+/).map((part) => part.trim().toLowerCase()).filter(Boolean));
  const escapeHtml = (value) => String(value || '').replace(/[&<>"']/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[char]));
  const bytes = (value) => {
    const n = Math.max(0, Number(value) || 0);
    if (n < 1024) return `${n} B`;
    if (n < 1024 * 1024) return `${(n / 1024).toFixed(1)} KB`;
    return `${(n / (1024 * 1024)).toFixed(1)} MB`;
  };
  const mailIcon = (name) => {
    const paths = {
      reply: '<path d="m10 8-5 4 5 4"/><path d="M5 12h8a6 6 0 0 1 6 6"/>',
      replyAll: '<path d="m9 8-5 4 5 4"/><path d="M4 12h9a6 6 0 0 1 6 6"/><path d="m13 8-3 4 3 4"/>',
      forward: '<path d="m14 8 5 4-5 4"/><path d="M19 12h-8a6 6 0 0 0-6 6"/>',
      read: '<path d="M4 6h16v12H4z"/><path d="m4 7 8 6 8-6"/>',
      flag: '<path d="M6 21V4m0 1h11l-2 4 2 4H6"/>',
      pin: '<path d="m9 3 6 0-1 6 3 3H7l3-3Z"/><path d="M12 12v9"/>',
      archive: '<path d="M4 7h16v13H4zM3 3h18v4H3z"/><path d="M9 12h6"/>',
      spam: '<path d="m8 3 8 0 5 5v8l-5 5H8l-5-5V8Z"/><path d="M12 7v6m0 4h.01"/>',
      trash: '<path d="M4 7h16M9 7V4h6v3m3 0-1 14H7L6 7"/><path d="M10 11v6m4-6v6"/>',
      report: '<path d="M5 21V4m0 1h12l-2 4 2 4H5"/>',
      block: '<circle cx="12" cy="12" r="9"/><path d="m6 6 12 12"/>',
      move: '<path d="M4 5h7l2 2h7v12H4z"/><path d="m10 12 4 0m0 0-2-2m2 2-2 2"/>',
      download: '<path d="M12 3v12m0 0 4-4m-4 4-4-4"/><path d="M5 20h14"/>',
      attachment: '<path d="m9 17 7-7a3 3 0 0 0-4-4l-8 8a5 5 0 0 0 7 7l8-8"/>',
    };
    return `<svg class="mvm-mail-ui-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">${paths[name] || paths.flag}</svg>`;
  };
  const decorateButton = (button, icon, label, title = '') => {
    button.innerHTML = `${mailIcon(icon)}<span>${label}</span>`;
    button.title = title || label;
    return button;
  };
  const idempotencyKey = () => globalThis.crypto && typeof globalThis.crypto.randomUUID === 'function'
    ? globalThis.crypto.randomUUID()
    : `mvm-${Date.now()}-${Math.random().toString(36).slice(2, 14)}`;

  class RestClient {
    constructor(root) {
      this.base = String(root.dataset.restBase || '').replace(/\/$/, '');
      this.nonce = String(root.dataset.restNonce || '');
    }

    async request(path, options = {}) {
      const headers = new Headers(options.headers || {});
      headers.set('Accept', 'application/json');
      if (this.nonce) headers.set('X-WP-Nonce', this.nonce);
      let body = options.body;
      if (body && !(body instanceof FormData) && typeof body !== 'string') {
        headers.set('Content-Type', 'application/json');
        body = JSON.stringify(body);
      }
      const response = await fetch(`${this.base}${path}`, {
        method: options.method || 'GET',
        credentials: 'same-origin',
        headers,
        body,
      });
      let payload = null;
      try { payload = await response.json(); } catch (_) { payload = null; }
      if (!response.ok) {
        const error = new Error(payload && payload.message ? payload.message : `HTTP ${response.status}`);
        error.code = payload && payload.code ? payload.code : 'mvm_http_error';
        error.status = response.status;
        error.data = payload && payload.data ? payload.data : null;
        throw error;
      }
      return payload;
    }
  }

  class MailClient {
    constructor(root) {
      this.root = root;
      this.rest = new RestClient(root);
      this.writeEnabled = root.dataset.writeEnabled === '1';
      this.deliveryEnabled = root.dataset.deliveryEnabled === '1';
      this.manageFolders = root.dataset.manageFolders === '1';
      this.manageMessages = root.dataset.manageMessages === '1';
      this.mailboxAddress = String(root.dataset.mailboxAddress || '').toLowerCase();
      this.page = 1;
      this.perPage = 30;
      this.hasMore = false;
      this.search = '';
      this.stateFilter = 'all';
      this.sortDirection = String(qs(root, '[data-mail-sort]')?.value || 'desc') === 'asc' ? 'asc' : 'desc';
      this.currentFolder = this.activeFolderId();
      this.currentMessage = null;
      this.folders = this.folderSnapshot();
      this.draft = this.blankDraft();
      this.autosaveTimer = null;
      this.saving = false;
      this.stepUpResolve = null;
      this.bind();
    }

    blankDraft() {
      return {
        id: '', version: 0, mode: 'compose', to: [], cc: [], bcc: [], subject: '', htmlBody: '',
        originalMessageId: '', inReplyTo: '', references: [], attachments: [],
      };
    }

    folderSnapshot() {
      return qsa(this.root, '[data-folder-row]').map((row) => ({
        id: String(row.dataset.folderId || ''),
        name: String(row.dataset.folderName || ''),
        system: row.dataset.folderSystem === '1',
      })).filter((item) => item.id);
    }

    activeFolderId() {
      const active = qs(this.root, '[data-folder-row] .mvm-mail-folder.is-active');
      const row = active ? active.closest('[data-folder-row]') : null;
      return row ? String(row.dataset.folderId || '') : '';
    }

    bind() {
      this.root.addEventListener('click', (event) => this.onClick(event));
      const searchForm = qs(this.root, '[data-mail-search-form]');
      if (searchForm) searchForm.addEventListener('submit', (event) => {
        event.preventDefault();
        this.search = String(qs(this.root, '[data-mail-search]')?.value || '').trim();
        this.stateFilter = String(qs(this.root, '[data-mail-state]')?.value || 'all');
        this.page = 1;
        this.loadMessages();
      });
      const state = qs(this.root, '[data-mail-state]');
      if (state) state.addEventListener('change', () => {
        this.stateFilter = String(state.value || 'all');
        this.page = 1;
        this.loadMessages();
      });
      const sort = qs(this.root, '[data-mail-sort]');
      if (sort) sort.addEventListener('change', () => {
        this.sortDirection = String(sort.value || 'desc') === 'asc' ? 'asc' : 'desc';
        this.page = 1;
        this.loadMessages();
      });
      const composerForm = qs(this.root, '[data-mail-compose-form]');
      if (composerForm) composerForm.addEventListener('submit', (event) => event.preventDefault());
      const editor = qs(this.root, '[data-compose-editor]');
      if (editor) editor.addEventListener('input', () => this.scheduleAutosave());
      ['to', 'cc', 'bcc', 'subject'].forEach((field) => {
        const node = qs(this.root, `[data-compose-${field}]`);
        if (node) node.addEventListener('input', () => this.scheduleAutosave());
      });
      const files = qs(this.root, '[data-compose-files]');
      if (files) files.addEventListener('change', () => this.uploadFiles(files.files));
      const drafts = qs(this.root, '[data-compose-drafts]');
      if (drafts) drafts.addEventListener('change', () => {
        if (drafts.value) this.loadDraft(String(drafts.value));
        else this.newDraft();
      });
      qsa(this.root, '[data-format]').forEach((button) => button.addEventListener('click', () => this.formatEditor(button.dataset.format || '')));
      const link = qs(this.root, '[data-format-link]');
      if (link) link.addEventListener('click', () => this.addEditorLink());
      const reportForm = qs(this.root, '[data-mail-report-form]');
      if (reportForm) reportForm.addEventListener('submit', (event) => { event.preventDefault(); this.submitReport(); });
      qsa(this.root, '[data-mail-report-close]').forEach((button) => button.addEventListener('click', () => qs(this.root, '[data-mail-report-dialog]')?.close()));
      this.bindStepUp();
    }

    async onClick(event) {
      const target = event.target instanceof Element ? event.target : null;
      if (!target) return;
      const folderSelect = target.closest('[data-folder-select]');
      if (folderSelect) {
        const row = folderSelect.closest('[data-folder-row]');
        if (row) this.selectFolder(String(row.dataset.folderId || ''));
        return;
      }
      const message = target.closest('[data-mail-message-id]');
      if (message) { await this.openMessage(String(message.dataset.mailMessageId || '')); return; }
      if (target.closest('[data-mail-refresh]')) { await this.refresh(); return; }
      if (target.closest('[data-mail-new]')) { await this.openComposer('compose'); return; }
      if (target.closest('[data-mail-prev]')) { if (this.page > 1) { this.page -= 1; await this.loadMessages(); } return; }
      if (target.closest('[data-mail-next]')) { if (this.hasMore) { this.page += 1; await this.loadMessages(); } return; }
      if (target.closest('[data-folder-create]')) { await this.createFolder(); return; }
      const rename = target.closest('[data-folder-rename]');
      if (rename) { await this.renameFolder(rename.closest('[data-folder-row]')); return; }
      const remove = target.closest('[data-folder-delete]');
      if (remove) { await this.deleteFolder(remove.closest('[data-folder-row]')); return; }
      if (target.closest('[data-compose-close]')) { this.closeComposer(); return; }
      if (target.closest('[data-compose-save]')) { await this.saveDraft(true); return; }
      if (target.closest('[data-compose-delete]')) { await this.deleteDraft(); return; }
      if (target.closest('[data-compose-send]')) { await this.sendDraft(); return; }
      const removeAttachment = target.closest('[data-attachment-delete]');
      if (removeAttachment) { await this.deleteAttachment(String(removeAttachment.dataset.attachmentDelete || '')); return; }
      if (target.closest('[data-mail-reply]')) { await this.openComposer('reply', this.currentMessage); return; }
      if (target.closest('[data-mail-reply-all]')) { await this.openComposer('reply_all', this.currentMessage); return; }
      if (target.closest('[data-mail-forward]')) { await this.openComposer('forward', this.currentMessage); return; }
      if (target.closest('[data-mail-toggle-read]')) { await this.toggleRead(); return; }
      if (target.closest('[data-mail-toggle-flag]')) { await this.toggleFlag(); return; }
      if (target.closest('[data-mail-toggle-pin]')) { await this.togglePin(); return; }
      const special = target.closest('[data-mail-special-action]');
      if (special) { await this.runSpecialAction(String(special.dataset.mailSpecialAction || '')); return; }
      if (target.closest('[data-mail-report]')) { this.openReportDialog(); return; }
      if (target.closest('[data-mail-block-sender]')) { await this.toggleSenderBlock(); return; }
      const attachment = target.closest('[data-mail-attachment-download]');
      if (attachment) { await this.downloadAttachment(String(attachment.dataset.mailAttachmentDownload || '')); return; }
      const move = target.closest('[data-mail-move]');
      if (move) { await this.moveCurrent(String(move.value || '')); return; }
    }

    notice(message, type = 'info') {
      const node = qs(this.root, '[data-mail-notice]');
      if (!node) return;
      node.hidden = !message;
      node.className = `mvm-alert${type === 'error' ? ' mvm-alert--warning' : ''}`;
      node.textContent = message || '';
    }

    async refresh() {
      await this.loadFolders();
      await this.loadMessages();
      if (this.currentMessage && this.currentMessage.id) await this.openMessage(this.currentMessage.id);
    }

    selectFolder(id) {
      if (!id || id === this.currentFolder) return;
      this.currentFolder = id;
      this.page = 1;
      this.currentMessage = null;
      qsa(this.root, '[data-folder-row]').forEach((row) => {
        const button = qs(row, '[data-folder-select]');
        if (button) button.classList.toggle('is-active', String(row.dataset.folderId || '') === id);
      });
      const detail = qs(this.root, '[data-mail-detail]');
      if (detail) detail.innerHTML = '<div class="mvm-empty">Selecteer een bericht om het te lezen.</div>';
      this.loadMessages();
    }

    async loadFolders() {
      try {
        const payload = await this.rest.request('/communications/mail/folders');
        this.folders = Array.isArray(payload?.folders) ? payload.folders : [];
        if (!this.folders.some((folder) => String(folder.id) === this.currentFolder)) {
          this.currentFolder = String(this.folders.find((folder) => folder.specialUse === 'inbox')?.id || this.folders[0]?.id || '');
        }
        this.renderFolders();
      } catch (error) { this.notice(error.message, 'error'); }
    }

    renderFolders() {
      const nav = qs(this.root, '[data-mail-folders]');
      if (!nav) return;
      nav.replaceChildren();
      this.folders.forEach((folder) => {
        const row = document.createElement('div');
        row.className = 'mvm-mail-folder-row';
        row.dataset.folderRow = '';
        row.dataset.folderId = String(folder.id || '');
        row.dataset.folderName = String(folder.name || 'Map');
        row.dataset.folderSpecial = String(folder.specialUse || '');
        row.dataset.folderSystem = folder.system ? '1' : '0';
        const button = document.createElement('button');
        button.type = 'button';
        button.className = `mvm-mail-folder${String(folder.id) === this.currentFolder ? ' is-active' : ''}`;
        button.dataset.folderSelect = '';
        const label = document.createElement('span'); label.textContent = String(folder.name || 'Map');
        const badge = document.createElement('span'); badge.className = 'mvm-badge'; badge.textContent = String(Math.max(0, Number(folder.unreadCount) || 0));
        button.append(label, badge); row.append(button);
        if (this.manageFolders && !folder.system) {
          const rename = document.createElement('button'); rename.type = 'button'; rename.className = 'mvm-icon-button'; rename.dataset.folderRename = ''; rename.setAttribute('aria-label', 'Map hernoemen'); rename.textContent = '✎';
          const remove = document.createElement('button'); remove.type = 'button'; remove.className = 'mvm-icon-button mvm-icon-button--danger'; remove.dataset.folderDelete = ''; remove.setAttribute('aria-label', 'Map verwijderen'); remove.textContent = '×';
          row.append(rename, remove);
        }
        nav.append(row);
      });
    }

    async loadMessages() {
      if (!this.currentFolder) return;
      const list = qs(this.root, '[data-mail-list]');
      if (list) list.innerHTML = '<div class="mvm-empty">Berichten laden…</div>';
      const query = new URLSearchParams({ folder: this.currentFolder, page: String(this.page), per_page: String(this.perPage), state: this.stateFilter, sort: 'date', direction: this.sortDirection });
      if (this.search) query.set('search', this.search);
      try {
        const payload = await this.rest.request(`/communications/mail/messages?${query.toString()}`);
        const messages = Array.isArray(payload?.messages) ? payload.messages : [];
        this.hasMore = payload?.hasMore === true;
        this.renderMessages(messages);
        if (Number(payload?.blockedHidden) > 0) this.notice(`${Number(payload.blockedHidden)} bericht${Number(payload.blockedHidden) === 1 ? '' : 'en'} van geblokkeerde afzenders verborgen.`);
        const page = qs(this.root, '[data-mail-page]'); if (page) page.textContent = String(this.page);
        const prev = qs(this.root, '[data-mail-prev]'); if (prev) prev.disabled = this.page <= 1;
        const next = qs(this.root, '[data-mail-next]'); if (next) next.disabled = !this.hasMore;
      } catch (error) {
        if (list) { list.replaceChildren(); const empty = document.createElement('div'); empty.className = 'mvm-empty'; empty.textContent = error.message; list.append(empty); }
        this.notice(error.message, 'error');
      }
    }

    renderMessages(messages) {
      const list = qs(this.root, '[data-mail-list]');
      if (!list) return;
      list.replaceChildren();
      if (!messages.length) { const empty = document.createElement('div'); empty.className = 'mvm-empty'; empty.textContent = 'Geen berichten in deze map.'; list.append(empty); return; }
      messages.forEach((message) => {
        const button = document.createElement('button');
        button.type = 'button'; button.className = `mvm-mail-row${message.seen ? '' : ' is-unread'}`; button.dataset.mailMessageId = String(message.id || '');
        const from = document.createElement('span'); from.className = 'mvm-mail-row__from'; from.textContent = String(message.fromLabel || '');
        const subject = document.createElement('strong'); subject.className = 'mvm-mail-row__subject'; subject.textContent = String(message.subject || '(Geen onderwerp)');
        const date = document.createElement('time'); date.textContent = String(message.date || '');
        const status = document.createElement('span'); status.className = 'mvm-mail-row__status';
        if (message.pinned) { const pin = document.createElement('span'); pin.innerHTML = mailIcon('pin'); pin.setAttribute('aria-label', 'Vastgezet'); pin.title = 'Vastgezet'; status.append(pin); }
        if (message.hasAttachments) { const attachment = document.createElement('span'); attachment.innerHTML = mailIcon('attachment'); attachment.setAttribute('aria-label', 'Met bijlage'); attachment.title = 'Met bijlage'; status.append(attachment); }
        if (message.senderBlocked) { const blocked = document.createElement('span'); blocked.className = 'mvm-mail-row__blocked'; blocked.textContent = 'Geblokkeerd'; status.append(blocked); }
        const flag = document.createElement('span'); flag.textContent = message.flagged ? '★' : '☆'; flag.setAttribute('aria-label', message.flagged ? 'Met vlag' : 'Geen vlag'); status.append(flag);
        button.classList.toggle('is-pinned', !!message.pinned);
        button.append(from, subject, date, status); list.append(button);
      });
    }

    async openMessage(id) {
      if (!id) return;
      const detail = qs(this.root, '[data-mail-detail]');
      if (detail) detail.innerHTML = '<div class="mvm-empty">Bericht laden…</div>';
      try {
        const message = await this.rest.request(`/communications/mail/messages/${encodeURIComponent(id)}`);
        this.currentMessage = message;
        this.renderMessage(message);
        if (this.manageMessages && message && !message.seen) {
          this.rest.request(`/communications/mail/messages/${encodeURIComponent(id)}/read-state`, { method: 'POST', body: { read: true } })
            .then(() => this.loadMessages()).catch(() => {});
        }
      } catch (error) {
        if (detail) { detail.replaceChildren(); const empty = document.createElement('div'); empty.className = 'mvm-empty'; empty.textContent = error.message; detail.append(empty); }
      }
    }

    renderMessage(message) {
      const detail = qs(this.root, '[data-mail-detail]');
      if (!detail) return;
      detail.replaceChildren();
      const header = document.createElement('header'); header.className = 'mvm-mail-detail__header';
      const heading = document.createElement('h3'); heading.textContent = String(message.subject || '(Geen onderwerp)');
      const meta = document.createElement('p'); meta.className = 'mvm-newsroom-preview__muted'; meta.textContent = `${message.fromLabel || message.fromAddress || ''}${message.date ? ` · ${message.date}` : ''}`;
      header.append(heading, meta);
      if (message.senderBlocked) {
        const blocked = document.createElement('span'); blocked.className = 'mvm-mail-security-badge'; blocked.innerHTML = `${mailIcon('block')} Afzender geblokkeerd`; header.append(blocked);
      }
      if (this.writeEnabled || this.manageMessages) {
        const actions = document.createElement('div'); actions.className = 'mvm-mail-detail__actions';
        if (this.writeEnabled) {
          [['Antwoorden', 'mailReply', 'reply'], ['Allen antwoorden', 'mailReplyAll', 'replyAll'], ['Doorsturen', 'mailForward', 'forward']].forEach(([label, key, icon]) => {
            const button = document.createElement('button'); button.type = 'button'; button.className = 'mvm-button mvm-button--secondary'; button.dataset[key] = ''; decorateButton(button, icon, label); actions.append(button);
          });
        }
        if (this.manageMessages) {
          const read = document.createElement('button'); read.type = 'button'; read.className = 'mvm-button mvm-button--secondary'; read.dataset.mailToggleRead = ''; decorateButton(read, 'read', message.seen ? 'Ongelezen' : 'Gelezen', message.seen ? 'Markeer als ongelezen' : 'Markeer als gelezen');
          const flag = document.createElement('button'); flag.type = 'button'; flag.className = 'mvm-button mvm-button--secondary'; flag.dataset.mailToggleFlag = ''; decorateButton(flag, 'flag', message.flagged ? 'Vlag weg' : 'Vlag', message.flagged ? 'Vlag verwijderen' : 'Vlag toevoegen');
          const pin = document.createElement('button'); pin.type = 'button'; pin.className = 'mvm-button mvm-button--secondary'; pin.dataset.mailTogglePin = ''; decorateButton(pin, 'pin', message.pinned ? 'Losmaken' : 'Vastzetten', message.pinned ? 'Bericht niet langer bovenaan houden' : 'Bericht bovenaan houden');
          const archive = document.createElement('button'); archive.type = 'button'; archive.className = 'mvm-button mvm-button--secondary'; archive.dataset.mailSpecialAction = 'archive'; decorateButton(archive, 'archive', 'Archiveren', 'Verplaats naar de systeemmap Archief');
          const spam = document.createElement('button'); spam.type = 'button'; spam.className = 'mvm-button mvm-button--secondary'; spam.dataset.mailSpecialAction = 'spam'; decorateButton(spam, 'spam', 'Spam', 'Meld als spam en verplaats naar de spammap');
          const remove = document.createElement('button'); remove.type = 'button'; remove.className = 'mvm-button mvm-button--danger'; remove.dataset.mailSpecialAction = 'trash'; decorateButton(remove, 'trash', 'Verwijderen', 'Verplaats naar de prullenbak');
          const report = document.createElement('button'); report.type = 'button'; report.className = 'mvm-button mvm-button--secondary'; report.dataset.mailReport = ''; decorateButton(report, 'report', 'Rapporteren', 'Leg een privacyveilige veiligheidsmelding vast');
          const block = document.createElement('button'); block.type = 'button'; block.className = 'mvm-button mvm-button--secondary'; block.dataset.mailBlockSender = ''; decorateButton(block, 'block', message.senderBlocked ? 'Deblokkeren' : 'Blokkeren', message.senderBlocked ? 'Hef de blokkering van deze afzender op' : 'Blokkeer deze afzender en verplaats het bericht naar spam');
          const move = document.createElement('select'); move.dataset.mailMove = ''; move.setAttribute('aria-label', 'Verplaats naar map');
          const placeholder = document.createElement('option'); placeholder.value = ''; placeholder.textContent = 'Verplaats naar…'; move.append(placeholder);
          this.folders.filter((folder) => String(folder.id) !== String(message.folderId || this.currentFolder)).forEach((folder) => { const option = document.createElement('option'); option.value = String(folder.id); option.textContent = String(folder.name || 'Map'); move.append(option); });
          actions.append(read, flag, pin, archive, spam, remove, report, block, move);
        }
        header.append(actions);
      }
      detail.append(header);
      const recipients = document.createElement('div'); recipients.className = 'mvm-mail-detail__recipients';
      const to = Array.isArray(message.to) ? message.to.join(', ') : ''; const cc = Array.isArray(message.cc) ? message.cc.join(', ') : '';
      recipients.textContent = `Aan: ${to || '—'}${cc ? ` · CC: ${cc}` : ''}`; detail.append(recipients);
      const body = document.createElement('article'); body.className = 'mvm-mail-detail__body';
      if (message.htmlBody) body.innerHTML = String(message.htmlBody); else { const pre = document.createElement('div'); pre.className = 'mvm-mail-plain'; pre.textContent = String(message.textBody || ''); body.append(pre); }
      detail.append(body);
      const attachments = Array.isArray(message.attachments) ? message.attachments : [];
      if (attachments.length) {
        const box = document.createElement('section'); box.className = 'mvm-mail-detail__attachments';
        const title = document.createElement('strong'); title.textContent = `Bijlagen (${attachments.length})`; box.append(title);
        const help = document.createElement('p'); help.className = 'mvm-newsroom-preview__muted'; help.textContent = 'Downloaden vereist identiteitsbevestiging; elk bestand wordt opnieuw gecontroleerd door de ingestelde malwarescanner.'; box.append(help);
        const list = document.createElement('ul');
        attachments.forEach((attachment) => {
          const li = document.createElement('li');
          const label = document.createElement('span'); label.textContent = `${attachment.name || 'Bijlage'} · ${bytes(attachment.sizeBytes)}`;
          const download = document.createElement('button'); download.type = 'button'; download.className = 'mvm-button mvm-button--secondary'; download.dataset.mailAttachmentDownload = String(attachment.id || ''); decorateButton(download, 'download', 'Downloaden', `Scan en download ${attachment.name || 'bijlage'}`);
          li.append(label, download); list.append(li);
        });
        box.append(list); detail.append(box);
      }
      if (message.remoteImagesBlocked) { const note = document.createElement('p'); note.className = 'mvm-newsroom-preview__muted'; note.textContent = 'Externe afbeeldingen zijn geblokkeerd om tracking te beperken.'; detail.append(note); }
    }

    async createFolder() {
      if (!this.manageFolders) return;
      const name = window.prompt('Naam van de nieuwe map:');
      if (!name) return;
      try { await this.rest.request('/communications/mail/folders', { method: 'POST', body: { name } }); this.notice('Map aangemaakt.'); await this.loadFolders(); }
      catch (error) { this.notice(error.message, 'error'); }
    }

    async renameFolder(row) {
      if (!row || !this.manageFolders || row.dataset.folderSystem === '1') return;
      const id = String(row.dataset.folderId || '');
      const name = window.prompt('Nieuwe mapnaam:', String(row.dataset.folderName || ''));
      if (!id || !name) return;
      try { await this.rest.request(`/communications/mail/folders/${encodeURIComponent(id)}`, { method: 'PUT', body: { name } }); this.notice('Map hernoemd.'); await this.loadFolders(); }
      catch (error) { this.notice(error.message, 'error'); }
    }

    async deleteFolder(row) {
      if (!row || !this.manageFolders || row.dataset.folderSystem === '1') return;
      const id = String(row.dataset.folderId || '');
      const name = String(row.dataset.folderName || 'deze map');
      if (!id || !window.confirm(`Map “${name}” verwijderen?`)) return;
      try {
        await this.stepUp('mail_folder_delete');
        await this.rest.request(`/communications/mail/folders/${encodeURIComponent(id)}`, { method: 'DELETE' });
        this.notice('Map verwijderd.'); await this.loadFolders(); await this.loadMessages();
      } catch (error) { if (error) this.notice(error.message || 'Map verwijderen geannuleerd.', 'error'); }
    }

    async toggleRead() {
      if (!this.currentMessage || !this.manageMessages) return;
      const read = !this.currentMessage.seen;
      try { await this.rest.request(`/communications/mail/messages/${encodeURIComponent(this.currentMessage.id)}/read-state`, { method: 'POST', body: { read } }); this.currentMessage.seen = read; this.renderMessage(this.currentMessage); await this.loadMessages(); }
      catch (error) { this.notice(error.message, 'error'); }
    }

    async toggleFlag() {
      if (!this.currentMessage || !this.manageMessages) return;
      const flagged = !this.currentMessage.flagged;
      try { await this.rest.request(`/communications/mail/messages/${encodeURIComponent(this.currentMessage.id)}/flag-state`, { method: 'POST', body: { flagged } }); this.currentMessage.flagged = flagged; this.renderMessage(this.currentMessage); await this.loadMessages(); }
      catch (error) { this.notice(error.message, 'error'); }
    }

    async togglePin() {
      if (!this.currentMessage || !this.manageMessages) return;
      const pinned = !this.currentMessage.pinned;
      try {
        await this.rest.request(`/communications/mail/messages/${encodeURIComponent(this.currentMessage.id)}/pin-state`, { method: 'POST', body: { pinned } });
        this.currentMessage.pinned = pinned;
        this.notice(pinned ? 'Bericht is bovenaan vastgezet.' : 'Bericht is losgemaakt.');
        this.renderMessage(this.currentMessage);
        await this.loadMessages();
      } catch (error) { this.notice(error.message, 'error'); }
    }

    async runSpecialAction(action) {
      if (!this.currentMessage || !this.manageMessages || !['archive', 'spam', 'trash'].includes(action)) return;
      if (action === 'trash' && !window.confirm('Dit bericht naar de prullenbak verplaatsen?')) return;
      try {
        await this.rest.request(`/communications/mail/messages/${encodeURIComponent(this.currentMessage.id)}/${action}`, { method: 'POST', body: {} });
        const labels = { archive: 'Bericht gearchiveerd.', spam: 'Bericht als spam gemeld.', trash: 'Bericht naar de prullenbak verplaatst.' };
        this.notice(labels[action] || 'Bericht bijgewerkt.');
        this.currentMessage = null;
        const detail = qs(this.root, '[data-mail-detail]');
        if (detail) detail.innerHTML = '<div class="mvm-empty">Selecteer een bericht om het te lezen.</div>';
        await this.loadFolders();
        await this.loadMessages();
      } catch (error) { this.notice(error.message, 'error'); }
    }

    openReportDialog() {
      if (!this.currentMessage || !this.manageMessages) return;
      const dialog = qs(this.root, '[data-mail-report-dialog]');
      if (dialog && typeof dialog.showModal === 'function') dialog.showModal();
    }

    async submitReport() {
      if (!this.currentMessage || !this.manageMessages) return;
      const reason = String(qs(this.root, '[data-mail-report-reason]')?.value || '');
      try {
        await this.rest.request(`/communications/mail/messages/${encodeURIComponent(this.currentMessage.id)}/report`, { method: 'POST', body: { reason } });
        qs(this.root, '[data-mail-report-dialog]')?.close();
        this.notice('Veiligheidsmelding vastgelegd zonder mailinhoud te kopiëren.');
      } catch (error) { this.notice(error.message, 'error'); }
    }

    async toggleSenderBlock() {
      if (!this.currentMessage || !this.manageMessages) return;
      const blocked = !this.currentMessage.senderBlocked;
      if (blocked && !window.confirm('Deze afzender blokkeren? Het huidige bericht wordt zo mogelijk naar Spam verplaatst.')) return;
      try {
        const result = await this.rest.request(`/communications/mail/messages/${encodeURIComponent(this.currentMessage.id)}/block-sender`, { method: 'POST', body: { blocked } });
        this.notice(result?.warning || (blocked ? 'Afzender geblokkeerd.' : 'Afzender gedeblokkeerd.'), !!result?.warning ? 'error' : 'info');
        if (blocked && result?.movedToSpam) {
          this.currentMessage = null;
          const detail = qs(this.root, '[data-mail-detail]');
          if (detail) detail.innerHTML = '<div class="mvm-empty">Selecteer een bericht om het te lezen.</div>';
          await this.loadFolders();
          await this.loadMessages();
        } else {
          this.currentMessage.senderBlocked = blocked;
          this.renderMessage(this.currentMessage);
          await this.loadMessages();
        }
      } catch (error) { this.notice(error.message, 'error'); }
    }

    async downloadAttachment(attachmentId) {
      if (!this.currentMessage || !attachmentId) return;
      const path = `/communications/mail/messages/${encodeURIComponent(this.currentMessage.id)}/attachments/${encodeURIComponent(attachmentId)}`;
      try {
        let payload;
        try {
          payload = await this.rest.request(path);
        } catch (error) {
          if (error.code !== 'mvm_communications_step_up_required') throw error;
          await this.stepUp('mail_attachment_download');
          payload = await this.rest.request(path);
        }
        const encoded = String(payload?.contentBase64 || '');
        if (!encoded) throw new Error('De bijlage bevat geen vrijgegeven downloadinhoud.');
        const raw = atob(encoded);
        const chunks = [];
        for (let offset = 0; offset < raw.length; offset += 65536) {
          const slice = raw.slice(offset, offset + 65536);
          const bytesChunk = new Uint8Array(slice.length);
          for (let index = 0; index < slice.length; index += 1) bytesChunk[index] = slice.charCodeAt(index);
          chunks.push(bytesChunk);
        }
        const blob = new Blob(chunks, { type: String(payload?.mimeType || 'application/octet-stream') });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url; link.download = String(payload?.name || 'bijlage'); link.hidden = true;
        document.body.append(link); link.click(); link.remove();
        setTimeout(() => URL.revokeObjectURL(url), 30000);
        this.notice(`Bijlage “${payload?.name || 'bijlage'}” is gescand en vrijgegeven.`);
      } catch (error) { if (error) this.notice(error.message || 'Bijlage downloaden geannuleerd.', 'error'); }
    }

    async moveCurrent(folderId) {
      if (!this.currentMessage || !folderId || !this.manageMessages) return;
      try { await this.rest.request(`/communications/mail/messages/${encodeURIComponent(this.currentMessage.id)}/move`, { method: 'POST', body: { folderId } }); this.notice('Bericht verplaatst.'); this.currentMessage = null; await this.loadMessages(); }
      catch (error) { this.notice(error.message, 'error'); }
    }

    async openComposer(mode = 'compose', original = null) {
      if (!this.writeEnabled) return;
      await this.loadDrafts();
      this.newDraft(false);
      this.draft.mode = mode;
      if (original && mode !== 'compose') this.prefillFromMessage(mode, original);
      this.syncComposerFromDraft();
      const dialog = qs(this.root, '[data-mail-composer]');
      if (dialog && typeof dialog.showModal === 'function') dialog.showModal();
    }

    closeComposer() {
      if (this.autosaveTimer) clearTimeout(this.autosaveTimer);
      const dialog = qs(this.root, '[data-mail-composer]');
      if (dialog && dialog.open) dialog.close();
    }

    newDraft(sync = true) {
      this.draft = this.blankDraft();
      if (sync) this.syncComposerFromDraft();
    }

    prefillFromMessage(mode, message) {
      const prefix = (value, wanted) => new RegExp(`^\\s*${wanted}\\s*:`, 'i').test(value) ? value : `${wanted}: ${value}`;
      const originalText = String(message.textBody || '').trim();
      const quote = originalText ? `<p><br></p><hr><p><strong>Oorspronkelijk bericht</strong></p><blockquote>${escapeHtml(originalText).replace(/\n/g, '<br>')}</blockquote>` : '';
      this.draft.originalMessageId = String(message.id || '');
      if (mode === 'reply' || mode === 'reply_all') {
        const own = this.mailboxAddress;
        const to = mode === 'reply_all'
          ? unique([message.fromAddress, ...(Array.isArray(message.to) ? message.to : [])].map((item) => String(item || '').toLowerCase()).filter((item) => item && item !== own))
          : unique([String(message.fromAddress || '').toLowerCase()].filter(Boolean));
        const cc = mode === 'reply_all'
          ? unique((Array.isArray(message.cc) ? message.cc : []).map((item) => String(item || '').toLowerCase()).filter((item) => item && item !== own && !to.includes(item)))
          : [];
        this.draft.to = to; this.draft.cc = cc; this.draft.subject = prefix(String(message.subject || ''), 'Re');
        this.draft.inReplyTo = String(message.messageId || '');
        this.draft.references = unique([...(Array.isArray(message.references) ? message.references : []), String(message.messageId || '')]);
        this.draft.htmlBody = quote;
      } else if (mode === 'forward') {
        this.draft.subject = prefix(String(message.subject || ''), 'Fwd');
        this.draft.htmlBody = quote;
      }
    }

    composerPayload() {
      const editor = qs(this.root, '[data-compose-editor]');
      const payload = {
        mode: this.draft.mode || 'compose',
        to: emails(qs(this.root, '[data-compose-to]')?.value),
        cc: emails(qs(this.root, '[data-compose-cc]')?.value),
        bcc: emails(qs(this.root, '[data-compose-bcc]')?.value),
        subject: String(qs(this.root, '[data-compose-subject]')?.value || ''),
        htmlBody: String(editor?.innerHTML || ''),
        attachmentIds: this.draft.attachments.map((item) => String(item.id || '')).filter(Boolean),
        originalMessageId: this.draft.originalMessageId || '',
        inReplyTo: this.draft.inReplyTo || '',
        references: Array.isArray(this.draft.references) ? this.draft.references : [],
      };
      if (this.draft.id) payload.draftId = this.draft.id;
      return payload;
    }

    syncComposerFromDraft() {
      const set = (selector, value) => { const node = qs(this.root, selector); if (node) node.value = value; };
      set('[data-compose-to]', (this.draft.to || []).join(', '));
      set('[data-compose-cc]', (this.draft.cc || []).join(', '));
      set('[data-compose-bcc]', (this.draft.bcc || []).join(', '));
      set('[data-compose-subject]', this.draft.subject || '');
      const editor = qs(this.root, '[data-compose-editor]'); if (editor) editor.innerHTML = this.draft.htmlBody || '';
      const heading = qs(this.root, '[data-compose-heading]'); if (heading) heading.textContent = ({ reply: 'Antwoorden', reply_all: 'Allen antwoorden', forward: 'Doorsturen' }[this.draft.mode] || 'Nieuw bericht');
      const status = qs(this.root, '[data-compose-status]'); if (status) status.textContent = this.draft.id ? `Concept opgeslagen · versie ${this.draft.version}` : 'Concept nog niet opgeslagen.';
      this.renderDraftAttachments();
    }

    scheduleAutosave() {
      if (!this.writeEnabled) return;
      if (this.autosaveTimer) clearTimeout(this.autosaveTimer);
      this.autosaveTimer = setTimeout(() => this.saveDraft(false), 1200);
    }

    async saveDraft(manual = false) {
      if (!this.writeEnabled || this.saving) return this.draft;
      this.saving = true;
      const status = qs(this.root, '[data-compose-status]'); if (status) status.textContent = 'Concept opslaan…';
      try {
        const payload = this.composerPayload();
        let result;
        if (!this.draft.id) {
          result = await this.rest.request('/communications/mail/drafts', { method: 'POST', body: payload });
        } else {
          result = await this.rest.request(`/communications/mail/drafts/${encodeURIComponent(this.draft.id)}`, { method: 'PUT', body: { ...payload, expectedVersion: this.draft.version } });
        }
        this.draft.id = String(result?.id || this.draft.id || '');
        this.draft.version = Math.max(1, Number(result?.version) || this.draft.version || 1);
        if (status) status.textContent = manual ? `Concept opgeslagen · versie ${this.draft.version}` : `Automatisch opgeslagen · versie ${this.draft.version}`;
        await this.loadDrafts(this.draft.id);
        return this.draft;
      } catch (error) {
        if (status) status.textContent = error.code === 'mvm_mail_draft_conflict' ? 'Concept gewijzigd in een andere sessie. Herlaad het concept.' : `Opslaan mislukt: ${error.message}`;
        throw error;
      } finally { this.saving = false; }
    }

    async ensureDraft() {
      if (!this.draft.id) await this.saveDraft(true);
      return this.draft.id;
    }

    async loadDrafts(selected = '') {
      const select = qs(this.root, '[data-compose-drafts]');
      if (!select || !this.writeEnabled) return;
      try {
        const payload = await this.rest.request('/communications/mail/drafts?page=1&per_page=50');
        const items = Array.isArray(payload?.items) ? payload.items : [];
        select.replaceChildren();
        const blank = document.createElement('option'); blank.value = ''; blank.textContent = 'Nieuw concept'; select.append(blank);
        items.forEach((item) => { const option = document.createElement('option'); option.value = String(item.id || ''); option.textContent = `${item.subject || '(Geen onderwerp)'} · v${item.version || 1}`; select.append(option); });
        select.value = selected || this.draft.id || '';
      } catch (_) {}
    }

    async loadDraft(id) {
      try {
        const payload = await this.rest.request(`/communications/mail/drafts/${encodeURIComponent(id)}`);
        this.draft = {
          ...this.blankDraft(), ...payload,
          id: String(payload?.id || id), version: Math.max(1, Number(payload?.version) || 1),
          to: Array.isArray(payload?.to) ? payload.to : [], cc: Array.isArray(payload?.cc) ? payload.cc : [], bcc: Array.isArray(payload?.bcc) ? payload.bcc : [],
          references: Array.isArray(payload?.references) ? payload.references : [], attachments: Array.isArray(payload?.attachments) ? payload.attachments : [],
        };
        this.syncComposerFromDraft();
      } catch (error) { this.notice(error.message, 'error'); }
    }

    async deleteDraft() {
      if (!this.draft.id) { this.newDraft(); return; }
      if (!window.confirm('Dit concept verwijderen?')) return;
      try { await this.rest.request(`/communications/mail/drafts/${encodeURIComponent(this.draft.id)}`, { method: 'DELETE' }); this.newDraft(); await this.loadDrafts(); this.notice('Concept verwijderd.'); }
      catch (error) { this.notice(error.message, 'error'); }
    }

    async uploadFiles(fileList) {
      if (!this.writeEnabled || !fileList || !fileList.length) return;
      try {
        const draftId = await this.ensureDraft();
        for (const file of Array.from(fileList).slice(0, 20)) {
          const form = new FormData(); form.append('file', file, file.name);
          const item = await this.rest.request(`/communications/mail/drafts/${encodeURIComponent(draftId)}/attachments`, { method: 'POST', body: form });
          if (item && item.id) this.draft.attachments.push(item);
        }
        this.renderDraftAttachments();
        await this.saveDraft(false);
      } catch (error) { this.notice(error.message, 'error'); }
      const input = qs(this.root, '[data-compose-files]'); if (input) input.value = '';
    }

    renderDraftAttachments() {
      const list = qs(this.root, '[data-compose-attachments]');
      if (!list) return;
      list.replaceChildren();
      this.draft.attachments.forEach((item) => {
        const li = document.createElement('li');
        const label = document.createElement('span'); label.textContent = `${item.name || 'Bijlage'} · ${bytes(item.sizeBytes)} · ${item.scanStatus || 'scan'}`;
        const remove = document.createElement('button'); remove.type = 'button'; remove.className = 'mvm-icon-button'; remove.dataset.attachmentDelete = String(item.id || ''); remove.setAttribute('aria-label', 'Bijlage verwijderen'); remove.textContent = '×';
        li.append(label, remove); list.append(li);
      });
    }

    async deleteAttachment(id) {
      if (!this.draft.id || !id) return;
      try {
        await this.rest.request(`/communications/mail/drafts/${encodeURIComponent(this.draft.id)}/attachments/${encodeURIComponent(id)}`, { method: 'DELETE' });
        this.draft.attachments = this.draft.attachments.filter((item) => String(item.id) !== id);
        this.renderDraftAttachments(); await this.saveDraft(false);
      } catch (error) { this.notice(error.message, 'error'); }
    }

    formatEditor(command) {
      const allowed = ['bold', 'italic', 'insertUnorderedList'];
      if (!allowed.includes(command)) return;
      const editor = qs(this.root, '[data-compose-editor]'); if (editor) editor.focus();
      document.execCommand(command, false);
      this.scheduleAutosave();
    }

    addEditorLink() {
      const url = String(window.prompt('Link (https://… of mailto:…):') || '').trim();
      if (!/^(https?:\/\/|mailto:)/i.test(url)) return;
      const editor = qs(this.root, '[data-compose-editor]'); if (editor) editor.focus();
      document.execCommand('createLink', false, url);
      this.scheduleAutosave();
    }

    async sendDraft() {
      if (!this.deliveryEnabled || !this.writeEnabled) return;
      try {
        await this.saveDraft(true);
        await this.stepUp('mail_send');
        const payload = this.composerPayload();
        const result = await this.rest.request('/communications/mail/send', { method: 'POST', headers: { 'Idempotency-Key': idempotencyKey() }, body: payload });
        if (!result?.delivered) throw new Error('De provider heeft verzending niet bevestigd.');
        const sentDraftId = this.draft.id;
        this.notice('E-mail veilig verzonden.');
        this.closeComposer();
        this.newDraft();
        if (sentDraftId) this.rest.request(`/communications/mail/drafts/${encodeURIComponent(sentDraftId)}`, { method: 'DELETE' }).catch(() => {});
        await this.loadFolders(); await this.loadMessages();
      } catch (error) { if (error) this.notice(error.message || 'Verzending geannuleerd.', 'error'); }
    }

    bindStepUp() {
      const dialog = qs(this.root, '[data-step-up-dialog]');
      const form = qs(this.root, '[data-step-up-form]');
      if (!dialog || !form) return;
      qsa(this.root, '[data-step-up-cancel]').forEach((button) => button.addEventListener('click', () => {
        if (dialog.open) dialog.close();
        if (this.stepUpResolve) { this.stepUpResolve.reject(new Error('Identiteitsbevestiging geannuleerd.')); this.stepUpResolve = null; }
      }));
      form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!this.stepUpResolve) return;
        const password = String(qs(this.root, '[data-step-up-password]')?.value || '');
        const errorNode = qs(this.root, '[data-step-up-error]');
        try {
          await this.rest.request('/communications/step-up', { method: 'POST', body: { action: this.stepUpResolve.action, password } });
          if (dialog.open) dialog.close();
          const resolver = this.stepUpResolve; this.stepUpResolve = null;
          const field = qs(this.root, '[data-step-up-password]'); if (field) field.value = '';
          if (errorNode) { errorNode.hidden = true; errorNode.textContent = ''; }
          resolver.resolve(true);
        } catch (error) {
          if (errorNode) { errorNode.hidden = false; errorNode.textContent = error.message; }
          const field = qs(this.root, '[data-step-up-password]'); if (field) { field.value = ''; field.focus(); }
        }
      });
    }

    stepUp(action) {
      return new Promise((resolve, reject) => {
        const dialog = qs(this.root, '[data-step-up-dialog]');
        if (!dialog || typeof dialog.showModal !== 'function') { reject(new Error('Identiteitsbevestiging is niet beschikbaar.')); return; }
        if (this.stepUpResolve) this.stepUpResolve.reject(new Error('Eerdere identiteitsbevestiging vervangen.'));
        this.stepUpResolve = { action, resolve, reject };
        const errorNode = qs(this.root, '[data-step-up-error]'); if (errorNode) { errorNode.hidden = true; errorNode.textContent = ''; }
        const field = qs(this.root, '[data-step-up-password]'); if (field) field.value = '';
        dialog.showModal();
        setTimeout(() => field?.focus(), 0);
      });
    }
  }

  class MessagesClient {
    constructor(root) {
      this.root = root;
      this.rest = new RestClient(root);
      this.writeEnabled = root.dataset.writeEnabled === '1';
      this.manageOwn = root.dataset.manageOwn === '1';
      this.currentThread = 0;
      this.selectedParticipants = new Map();
      this.searchTimer = null;
      this.bind();
    }

    bind() {
      this.root.addEventListener('click', (event) => this.onClick(event));
      const form = qs(this.root, '[data-message-compose-form]');
      if (form) form.addEventListener('submit', (event) => { event.preventDefault(); this.createThread(); });
      const search = qs(this.root, '[data-participant-search]');
      if (search) search.addEventListener('input', () => {
        if (this.searchTimer) clearTimeout(this.searchTimer);
        this.searchTimer = setTimeout(() => this.searchParticipants(String(search.value || '').trim()), 250);
      });
    }

    async onClick(event) {
      const target = event.target instanceof Element ? event.target : null;
      if (!target) return;
      const thread = target.closest('[data-thread-id]');
      if (thread) { await this.openThread(Number(thread.dataset.threadId || 0)); return; }
      if (target.closest('[data-message-new]')) { this.openNew(); return; }
      if (target.closest('[data-message-compose-close]')) { this.closeNew(); return; }
      const participant = target.closest('[data-participant-id]');
      if (participant) { this.toggleParticipant(Number(participant.dataset.participantId || 0), String(participant.dataset.participantName || '')); return; }
      const selected = target.closest('[data-selected-participant]');
      if (selected) { this.selectedParticipants.delete(Number(selected.dataset.selectedParticipant || 0)); this.renderSelection(); return; }
      if (target.closest('[data-message-reply-submit]')) { await this.reply(); return; }
      if (target.closest('[data-message-archive]')) { await this.archive(); return; }
      if (target.closest('[data-message-delete]')) { await this.deleteThread(); return; }
    }

    notice(message, error = false) {
      const node = qs(this.root, '[data-message-notice]'); if (!node) return;
      node.hidden = !message; node.className = `mvm-alert${error ? ' mvm-alert--warning' : ''}`; node.textContent = message || '';
    }

    async openThread(id) {
      if (!id) return;
      const detail = qs(this.root, '[data-message-detail]'); if (detail) detail.innerHTML = '<div class="mvm-empty">Gesprek laden…</div>';
      try {
        const thread = await this.rest.request(`/communications/messages/${id}`);
        this.currentThread = id; this.renderThread(thread);
        if (this.manageOwn && Number(thread?.unreadCount || 0) > 0) this.rest.request(`/communications/messages/${id}/read`, { method: 'POST', body: {} }).catch(() => {});
      } catch (error) { if (detail) { detail.replaceChildren(); const empty = document.createElement('div'); empty.className = 'mvm-empty'; empty.textContent = error.message; detail.append(empty); } }
    }

    renderThread(thread) {
      const detail = qs(this.root, '[data-message-detail]'); if (!detail) return; detail.replaceChildren();
      const header = document.createElement('header'); header.className = 'mvm-message-detail__header';
      const title = document.createElement('h3'); title.textContent = String(thread?.title || 'Gesprek');
      const names = Array.isArray(thread?.participants) ? thread.participants.map((p) => p.displayName).filter(Boolean).join(', ') : '';
      const meta = document.createElement('p'); meta.className = 'mvm-newsroom-preview__muted'; meta.textContent = names;
      header.append(title, meta);
      if (this.manageOwn) {
        const actions = document.createElement('div'); actions.className = 'mvm-mail-detail__actions';
        const archive = document.createElement('button'); archive.type = 'button'; archive.className = 'mvm-button mvm-button--secondary'; archive.dataset.messageArchive = ''; archive.textContent = thread?.archived ? 'Gearchiveerd' : 'Archiveren'; archive.disabled = !!thread?.archived;
        const remove = document.createElement('button'); remove.type = 'button'; remove.className = 'mvm-button mvm-button--danger'; remove.dataset.messageDelete = ''; remove.textContent = 'Voor mij verwijderen';
        actions.append(archive, remove); header.append(actions);
      }
      detail.append(header);
      const stream = document.createElement('div'); stream.className = 'mvm-message-stream';
      (Array.isArray(thread?.messages) ? thread.messages : []).forEach((message) => {
        const item = document.createElement('article'); item.className = `mvm-message-bubble${message.isOwn ? ' is-own' : ''}`;
        const head = document.createElement('div'); head.className = 'mvm-message-bubble__meta'; head.textContent = `${message.authorName || 'Gebruiker'}${message.createdAtUtc ? ` · ${message.createdAtUtc}` : ''}`;
        const body = document.createElement('div'); body.className = 'mvm-message-bubble__body'; body.innerHTML = String(message.htmlBody || '');
        item.append(head, body); stream.append(item);
      });
      detail.append(stream);
      if (this.writeEnabled) {
        const reply = document.createElement('div'); reply.className = 'mvm-message-reply';
        const textarea = document.createElement('textarea'); textarea.rows = 4; textarea.maxLength = 10000; textarea.dataset.messageReplyBody = ''; textarea.placeholder = 'Schrijf een antwoord…';
        const button = document.createElement('button'); button.type = 'button'; button.className = 'mvm-button mvm-button--primary'; button.dataset.messageReplySubmit = ''; button.textContent = 'Verzenden';
        reply.append(textarea, button); detail.append(reply);
      }
    }

    async reply() {
      const body = String(qs(this.root, '[data-message-reply-body]')?.value || '').trim();
      if (!this.currentThread || !body) return;
      try { await this.rest.request(`/communications/messages/${this.currentThread}/reply`, { method: 'POST', body: { body } }); await this.openThread(this.currentThread); this.notice('Antwoord verzonden.'); }
      catch (error) { this.notice(error.message, true); }
    }

    openNew() {
      if (!this.writeEnabled) return;
      this.selectedParticipants.clear(); this.renderSelection();
      const search = qs(this.root, '[data-participant-search]'); if (search) search.value = '';
      const results = qs(this.root, '[data-participant-results]'); if (results) results.replaceChildren();
      const body = qs(this.root, '[data-message-compose-body]'); if (body) body.value = '';
      const dialog = qs(this.root, '[data-message-composer]'); if (dialog && typeof dialog.showModal === 'function') dialog.showModal();
    }

    closeNew() { const dialog = qs(this.root, '[data-message-composer]'); if (dialog?.open) dialog.close(); }

    async searchParticipants(search) {
      const results = qs(this.root, '[data-participant-results]'); if (!results) return;
      results.replaceChildren();
      if (search.length < 2) return;
      try {
        const payload = await this.rest.request(`/communications/participants?search=${encodeURIComponent(search)}`);
        (Array.isArray(payload?.items) ? payload.items : []).forEach((item) => {
          const button = document.createElement('button'); button.type = 'button'; button.className = 'mvm-participant-result'; button.dataset.participantId = String(item.id || 0); button.dataset.participantName = String(item.displayName || ''); button.textContent = String(item.displayName || ''); results.append(button);
        });
      } catch (error) { this.notice(error.message, true); }
    }

    toggleParticipant(id, name) {
      if (!id) return;
      if (this.selectedParticipants.has(id)) this.selectedParticipants.delete(id); else this.selectedParticipants.set(id, name || `Gebruiker ${id}`);
      this.renderSelection();
    }

    renderSelection() {
      const box = qs(this.root, '[data-participant-selection]'); if (!box) return; box.replaceChildren();
      this.selectedParticipants.forEach((name, id) => { const chip = document.createElement('button'); chip.type = 'button'; chip.className = 'mvm-badge'; chip.dataset.selectedParticipant = String(id); chip.textContent = `${name} ×`; box.append(chip); });
    }

    async createThread() {
      const body = String(qs(this.root, '[data-message-compose-body]')?.value || '').trim();
      const participants = Array.from(this.selectedParticipants.keys());
      if (!participants.length || !body) { this.notice('Kies minimaal één deelnemer en schrijf een bericht.', true); return; }
      try {
        const result = await this.rest.request('/communications/messages', { method: 'POST', body: { participants, body } });
        this.closeNew(); this.notice('Gesprek gestart.');
        const id = Number(result?.id || result?.threadId || 0); if (id) await this.openThread(id); else window.location.reload();
      } catch (error) { this.notice(error.message, true); }
    }

    async archive() {
      if (!this.currentThread || !this.manageOwn) return;
      try { await this.rest.request(`/communications/messages/${this.currentThread}/archive`, { method: 'POST', body: {} }); this.notice('Gesprek gearchiveerd.'); await this.openThread(this.currentThread); }
      catch (error) { this.notice(error.message, true); }
    }

    async deleteThread() {
      if (!this.currentThread || !this.manageOwn || !window.confirm('Dit gesprek alleen voor jezelf verwijderen?')) return;
      try { await this.rest.request(`/communications/messages/${this.currentThread}`, { method: 'DELETE' }); this.notice('Gesprek voor jou verwijderd.'); window.location.reload(); }
      catch (error) { this.notice(error.message, true); }
    }
  }

  document.addEventListener('DOMContentLoaded', () => {
    qsa(document, '[data-mvm-mail]').forEach((root) => new MailClient(root));
    qsa(document, '[data-mvm-messages]').forEach((root) => new MessagesClient(root));
  });
})();
