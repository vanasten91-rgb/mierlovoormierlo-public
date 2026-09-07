(function(){
  'use strict';

  var root = document.querySelector('[data-mvm-newsroom]');
  if (!root) return;

  var section = root.getAttribute('data-section') || 'today';
  var restBase = root.getAttribute('data-rest-base') || '';
  var nonce = root.getAttribute('data-rest-nonce') || '';
  var writeEnabled = root.getAttribute('data-write-enabled') === '1';
  var canCreate = root.getAttribute('data-can-create') === '1';
  var canUpdate = root.getAttribute('data-can-update') === '1';
  var content = root.querySelector('[data-newsroom-content]');
  var notice = root.querySelector('[data-newsroom-notice]');
  var dialog = root.querySelector('[data-newsroom-dialog]');
  var form = root.querySelector('[data-newsroom-form]');
  var fieldsBox = root.querySelector('[data-newsroom-fields]');
  var statusBox = root.querySelector('[data-newsroom-form-status]');
  var titleBox = root.querySelector('[data-newsroom-dialog-title]');
  var smartPanel = root.querySelector('[data-smartlinks-panel]');
  var smartQuery = root.querySelector('[data-smartlinks-query]');
  var smartResults = root.querySelector('[data-smartlinks-results]');
  var current = { section: section, id: null, detail: null };

  var endpoints = {
    news: 'newsroom/news',
    assignments: 'newsroom/assignments',
    radar: 'newsroom/radar',
    sources: 'newsroom/sources',
    agenda: 'newsroom/agenda',
    media: 'newsroom/media',
    dossiers: 'newsroom/dossiers',
    corrections: 'newsroom/corrections',
    distribution: 'newsroom/distribution'
  };

  var options = {
    newsState: ['idea','assigned','draft','review','changes_requested','ready','scheduled','published','correction'],
    assignmentState: ['new','assigned','in_progress','review','completed','cancelled'],
    type: ['news','photo','event','source','general'],
    radarKind: ['news','photo','event','source','safety','correction','general'],
    radarStatus: ['new','triage','assigned','in_progress','converted','closed','rejected'],
    verification: ['unverified','verified','conflicting'],
    incident: ['unknown','ongoing','resolved'],
    sourceCategory: ['sport','verenigingen','nieuwssites','politiek','officieel','onderwijs','cultuur','veiligheid','ondernemers','lokaal_sociaal','overig'],
    sourceFrequency: ['four_daily','twice_daily','daily','three_weekly','twice_weekly','weekly','biweekly','monthly','seasonal','on_demand'],
    sourceStatus: ['active','paused','stopped'],
    agendaKind: ['news','event','photo','review','deadline','distribution','safety'],
    agendaStatus: ['planned','confirmed','ready','done','cancelled'],
    mediaKind: ['photo','video','audio','document'],
    mediaStatus: ['requested','assigned','uploaded','review','approved','rejected','used'],
    consent: ['unknown','not_required','obtained','restricted'],
    dossierStatus: ['open','research','writing','review','ready','published','archived'],
    visibility: ['private','team','public'],
    correctionStatus: ['new','reviewing','accepted','rejected','published'],
    channel: ['facebook','whatsapp','email','peepso','other'],
    distributionStatus: ['draft','review','approved','sent','cancelled']
  };

  var defs = {
    news: [
      {name:'title',label:'Titel',required:true,wide:true},
      {name:'content',label:'Artikeltekst',type:'textarea',wide:true},
      {name:'excerpt',label:'Samenvatting',type:'textarea',wide:true},
      {name:'state',label:'Workflow',type:'select',options:'newsState'},
      {name:'publishAtUtc',label:'Publicatiemoment UTC',type:'datetime'},
      {name:'categoryIds',label:'Categorie-ID’s',help:'Komma-gescheiden'},
      {name:'featuredMediaId',label:'Uitgelichte media-ID',type:'number'},
      {name:'expectedModifiedGmt',type:'hidden'}
    ],
    assignments: [
      {name:'title',label:'Opdracht',required:true,wide:true},
      {name:'brief',label:'Briefing',type:'textarea',wide:true},
      {name:'type',label:'Type',type:'select',options:'type'},
      {name:'state',label:'Status',type:'select',options:'assignmentState'},
      {name:'priority',label:'Prioriteit',type:'number'},
      {name:'assigneeUserId',label:'Toegewezen user-ID',type:'number'},
      {name:'dueAtUtc',label:'Deadline UTC',type:'datetime'},
      {name:'sourcePostId',label:'Bronpost-ID',type:'number'},
      {name:'eventPostId',label:'Eventpost-ID',type:'number'},
      {name:'newsPostId',label:'Nieuws-ID',type:'number'}
    ],
    radar: [
      {name:'title',label:'Signaal',required:true,wide:true},
      {name:'summary',label:'Samenvatting',type:'textarea',wide:true},
      {name:'kind',label:'Type',type:'select',options:'radarKind'},
      {name:'status',label:'Status',type:'select',options:'radarStatus'},
      {name:'priority',label:'Prioriteit',type:'number'},
      {name:'sourceUrl',label:'Bron-URL'},
      {name:'location',label:'Locatie'},
      {name:'incidentAtUtc',label:'Incidenttijd UTC',type:'datetime'},
      {name:'verificationStatus',label:'Verificatie',type:'select',options:'verification'},
      {name:'incidentStatus',label:'Incidentstatus',type:'select',options:'incident'},
      {name:'assigneeUserId',label:'Toegewezen user-ID',type:'number'},
      {name:'dossierId',label:'Dossier-ID',type:'number'},
      {name:'assignmentId',label:'Opdracht-ID',type:'number'}
    ],
    sources: [
      {name:'category',label:'Categorie',type:'select',options:'sourceCategory'},
      {name:'frequency',label:'Frequentie',type:'select',options:'sourceFrequency'},
      {name:'status',label:'Status',type:'select',options:'sourceStatus'},
      {name:'monitorEnabled',label:'Monitor actief',type:'checkbox'},
      {name:'privateNote',label:'Interne notitie',type:'textarea',wide:true}
    ],
    agenda: [
      {name:'title',label:'Agenda-item',required:true,wide:true},
      {name:'kind',label:'Type',type:'select',options:'agendaKind'},
      {name:'status',label:'Status',type:'select',options:'agendaStatus'},
      {name:'startsAtUtc',label:'Start UTC',type:'datetime',required:true},
      {name:'endsAtUtc',label:'Einde UTC',type:'datetime'},
      {name:'location',label:'Locatie'},
      {name:'postId',label:'Post-ID',type:'number'},
      {name:'eventPostId',label:'Eventpost-ID',type:'number'},
      {name:'assignmentId',label:'Opdracht-ID',type:'number'},
      {name:'dossierId',label:'Dossier-ID',type:'number'},
      {name:'ownerUserId',label:'Eigenaar user-ID',type:'number'}
    ],
    media: [
      {name:'title',label:'Media-item',required:true,wide:true},
      {name:'kind',label:'Type',type:'select',options:'mediaKind'},
      {name:'status',label:'Status',type:'select',options:'mediaStatus'},
      {name:'attachmentId',label:'Attachment-ID',type:'number'},
      {name:'photographerUserId',label:'Fotograaf user-ID',type:'number'},
      {name:'reviewerUserId',label:'Reviewer user-ID',type:'number'},
      {name:'consentStatus',label:'Toestemming',type:'select',options:'consent'},
      {name:'location',label:'Locatie'},
      {name:'credit',label:'Credit'},
      {name:'relatedPostId',label:'Gerelateerde post-ID',type:'number'},
      {name:'assignmentId',label:'Opdracht-ID',type:'number'},
      {name:'dossierId',label:'Dossier-ID',type:'number'}
    ],
    dossiers: [
      {name:'title',label:'Dossier',required:true,wide:true},
      {name:'summary',label:'Samenvatting',type:'textarea',wide:true},
      {name:'internalBrief',label:'Interne briefing',type:'textarea',wide:true},
      {name:'status',label:'Status',type:'select',options:'dossierStatus'},
      {name:'visibility',label:'Zichtbaarheid',type:'select',options:'visibility'},
      {name:'leadUserId',label:'Lead user-ID',type:'number'}
    ],
    corrections: [
      {name:'postId',label:'Artikel-ID',type:'number',required:true},
      {name:'body',label:'Correctietekst',type:'textarea',wide:true},
      {name:'status',label:'Status',type:'select',options:'correctionStatus'},
      {name:'reviewerUserId',label:'Reviewer user-ID',type:'number'}
    ],
    distribution: [
      {name:'postId',label:'Artikel-ID',type:'number',required:true},
      {name:'channel',label:'Kanaal',type:'select',options:'channel'},
      {name:'status',label:'Status',type:'select',options:'distributionStatus'},
      {name:'copy',label:'Publicatietekst',type:'textarea',wide:true},
      {name:'targetUrl',label:'Doel-URL'},
      {name:'scheduledAtUtc',label:'Planning UTC',type:'datetime'},
      {name:'approvedByUserId',label:'Goedkeurder user-ID',type:'number'}
    ]
  };

  function api(path){ return new URL(String(path).replace(/^\/+/,''), restBase.replace(/\/?$/,'/')).toString(); }
  function esc(value){ return String(value == null ? '' : value).replace(/[&<>'"]/g,function(ch){return {'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[ch];}); }
  function setNotice(message, kind){ if(!notice) return; notice.hidden = !message; notice.className = 'mvm-alert' + (kind ? ' mvm-alert--' + kind : ''); notice.textContent = message || ''; }
  function idOf(item){ return item.id || item.sourcePostId || item.postId || 0; }
  function titleOf(item){ return item.title || item.name || item.subject || ('#' + idOf(item)); }
  function stateOf(item){ return item.workflowState || item.state || item.status || item.wpStatus || item.kind || ''; }
  function dateOf(item){ return item.modifiedUtc || item.modifiedGmt || item.updatedAtUtc || item.nextCheckUtc || item.startsAtUtc || ''; }
  function normalizeDetail(payload){ return payload && payload.id ? payload : (payload || {}); }

  function request(path, opts){
    opts = opts || {};
    var headers = {'Accept':'application/json','X-WP-Nonce':nonce};
    if (opts.body) headers['Content-Type'] = 'application/json';
    return fetch(api(path), {method:opts.method || 'GET', credentials:'same-origin', headers:headers, body:opts.body ? JSON.stringify(opts.body) : undefined})
      .then(function(res){ return res.json().catch(function(){ return {}; }).then(function(json){ if(!res.ok){ var msg = json.message || json.code || ('HTTP ' + res.status); throw new Error(msg); } return json; }); });
  }

  function renderList(payload){
    if (!content) return;
    var items = Array.isArray(payload.items) ? payload.items : [];
    var canEditRows = writeEnabled && canUpdate && section !== 'team' && section !== 'today';
    var html = '<div class="mvm-newsroom-preview"><header class="mvm-newsroom-preview__section-head"><div><p class="mvm-hub__eyebrow">Nieuwsroom</p><h2>'+esc(label(section))+'</h2><p class="mvm-newsroom-preview__muted">Live geladen via dezelfde REST-contracten als de editor.</p></div><span class="mvm-badge">'+esc(items.length)+' items</span></header>';
    if (!items.length) {
      html += '<div class="mvm-empty">Geen items beschikbaar binnen deze weergave.</div></div>';
      content.innerHTML = html;
      return;
    }
    html += '<div class="mvm-table-wrap"><table class="mvm-table"><thead><tr><th>Item</th><th>Status</th><th>Gewijzigd</th><th>Actie</th></tr></thead><tbody>';
    items.forEach(function(item){
      var id = idOf(item);
      html += '<tr data-newsroom-row="'+esc(id)+'"><td><strong>'+esc(titleOf(item))+'</strong></td><td><span class="mvm-badge">'+esc(stateOf(item))+'</span></td><td>'+esc(dateOf(item))+'</td><td class="mvm-newsroom-runtime__row-actions">';
      if (canEditRows && id) html += '<button type="button" class="mvm-button mvm-button--secondary" data-newsroom-edit="'+esc(id)+'">Bewerken</button>';
      html += '</td></tr>';
    });
    html += '</tbody></table></div></div>';
    content.innerHTML = html;
  }

  function label(key){ return {news:'Nieuws',assignments:'Opdrachten',radar:'Radar',sources:'Bronnen',agenda:'Agenda',media:'Media',dossiers:'Dossiers',corrections:'Correcties',distribution:'Distributie',team:'Team',today:'Vandaag'}[key] || 'Nieuwsroom'; }

  function load(){
    if (!endpoints[section]) return;
    setNotice('Laden…');
    request(endpoints[section] + '?page=1&per_page=20').then(function(payload){ renderList(payload); setNotice(''); }).catch(function(err){ setNotice(err.message, 'warning'); });
  }

  function fieldHtml(def, value){
    if (def.type === 'hidden') return '<input type="hidden" name="'+esc(def.name)+'" value="'+esc(value)+'">';
    var cls = 'mvm-newsroom-field' + (def.wide ? ' mvm-newsroom-field--wide' : '');
    var required = def.required ? ' required' : '';
    var html = '<div class="'+cls+'"><label>'+esc(def.label || def.name)+'</label>';
    if (def.type === 'textarea') {
      html += '<textarea name="'+esc(def.name)+'"'+required+'>'+esc(value)+'</textarea>';
    } else if (def.type === 'select') {
      html += '<select name="'+esc(def.name)+'"'+required+'>';
      (options[def.options] || []).forEach(function(opt){ html += '<option value="'+esc(opt)+'"'+(String(value)===opt?' selected':'')+'>'+esc(opt)+'</option>'; });
      html += '</select>';
    } else if (def.type === 'checkbox') {
      html += '<input type="checkbox" name="'+esc(def.name)+'" value="1"'+(value ? ' checked' : '')+'>';
    } else {
      var type = def.type === 'datetime' ? 'datetime-local' : (def.type || 'text');
      html += '<input type="'+esc(type)+'" name="'+esc(def.name)+'" value="'+esc(value)+'"'+required+'>';
    }
    if (def.help) html += '<small class="mvm-newsroom-preview__muted">'+esc(def.help)+'</small>';
    html += '</div>';
    return html;
  }

  function defaultsFor(kind){
    var now = new Date(Date.now() + 3600000).toISOString().slice(0,16);
    return {state:'idea',type:'news',kind:'news',status:'planned',priority:2,startsAtUtc:now,photographerUserId:root.getAttribute('data-current-user') || '',ownerUserId:root.getAttribute('data-current-user') || '',monitorEnabled:true,consentStatus:'unknown',visibility:'team',channel:'facebook'};
  }

  function openEditor(id){
    if (!dialog || !form || !fieldsBox || !defs[section]) return;
    current = {section:section,id:id || null,detail:null};
    var starter = Object.assign({}, defaultsFor(section));
    if (!id) return showEditor(starter);
    request(endpoints[section] + '/' + encodeURIComponent(id)).then(function(detail){ showEditor(normalizeDetail(detail)); }).catch(function(err){ setNotice(err.message,'warning'); });
  }

  function showEditor(detail){
    current.detail = detail;
    if (titleBox) titleBox.textContent = current.id ? 'Bewerken · ' + label(section) : 'Nieuw · ' + label(section);
    if (section === 'news' && detail.modifiedGmt) detail.expectedModifiedGmt = detail.modifiedGmt;
    fieldsBox.innerHTML = defs[section].map(function(def){ return fieldHtml(def, coerceForInput(detail[def.name], def)); }).join('');
    if (smartPanel) smartPanel.hidden = section !== 'news';
    setFormStatus('');
    if (dialog.showModal) dialog.showModal(); else dialog.setAttribute('open','open');
  }

  function coerceForInput(value, def){
    if (value == null) return '';
    if (Array.isArray(value)) return value.join(',');
    if (def.type === 'datetime' && typeof value === 'string') return value.replace(' ','T').slice(0,16);
    return value;
  }

  function collect(){
    var data = {};
    defs[section].forEach(function(def){
      var el = form.elements[def.name];
      if (!el) return;
      if (def.type === 'checkbox') data[def.name] = !!el.checked;
      else if (def.type === 'number') data[def.name] = el.value === '' ? 0 : Number(el.value);
      else if (def.name === 'categoryIds') data[def.name] = String(el.value || '').split(',').map(function(v){ return Number(v.trim()); }).filter(Boolean);
      else if (def.type === 'datetime') data[def.name] = el.value ? el.value.replace('T',' ') + ':00' : '';
      else data[def.name] = el.value;
    });
    return data;
  }

  function save(ev){
    ev.preventDefault();
    if (!writeEnabled) return setFormStatus('Schrijven staat uit.', true);
    var data = collect();
    var id = current.id;
    var path = endpoints[section] + (id ? '/' + encodeURIComponent(id) : '');
    var previousState = current.detail ? (current.detail.state || '') : '';
    setFormStatus('Opslaan…');
    request(path, {method:'POST', body:data}).then(function(saved){
      if (section === 'news' && id && data.state && data.state !== previousState) {
        return request(endpoints.news + '/' + encodeURIComponent(id) + '/transition', {method:'POST', body:{to:data.state,publishAtUtc:data.publishAtUtc || ''}}).then(function(){ return saved; });
      }
      return saved;
    }).then(function(){ setFormStatus('Opgeslagen.', false, true); closeDialog(); load(); }).catch(function(err){ setFormStatus(err.message, true); });
  }

  function setFormStatus(message, error, success){
    if (!statusBox) return;
    statusBox.className = error ? 'mvm-newsroom-editor__error' : (success ? 'mvm-newsroom-editor__success' : 'mvm-newsroom-preview__muted');
    statusBox.textContent = message || '';
  }

  function closeDialog(){ if(!dialog) return; if(dialog.close) dialog.close(); else dialog.removeAttribute('open'); }

  function smartSearch(){
    if (!smartQuery || !smartResults) return;
    var q = smartQuery.value.trim();
    if (q.length < 2) return;
    smartResults.innerHTML = '<div class="mvm-newsroom-preview__muted">Zoeken…</div>';
    request('newsroom/encyclopedia/targets?q=' + encodeURIComponent(q) + '&limit=8').then(function(results){
      var items = Array.isArray(results) ? results : (Array.isArray(results.items) ? results.items : []);
      smartResults.innerHTML = '<div class="mvm-newsroom-smartlink-results">' + items.map(function(item){
        return '<div class="mvm-newsroom-smartlink-result"><span><strong>'+esc(item.title || item.label || '')+'</strong><br><small>'+esc(item.url || item.permalink || '')+'</small></span><button type="button" class="mvm-button mvm-button--secondary" data-smartlink-insert data-title="'+esc(item.title || item.label || '')+'" data-url="'+esc(item.url || item.permalink || '')+'">Invoegen</button></div>';
      }).join('') + '</div>';
    }).catch(function(err){ smartResults.innerHTML = '<div class="mvm-newsroom-editor__error">'+esc(err.message)+'</div>'; });
  }

  root.addEventListener('click', function(ev){
    var target = ev.target;
    if (!(target instanceof Element)) return;
    var edit = target.closest('[data-newsroom-edit]');
    if (edit) openEditor(edit.getAttribute('data-newsroom-edit'));
    if (target.closest('[data-newsroom-new]')) openEditor(null);
    if (target.closest('[data-newsroom-refresh]')) load();
    if (target.closest('[data-newsroom-close]')) closeDialog();
    if (target.closest('[data-smartlinks-search]')) smartSearch();
    var insert = target.closest('[data-smartlink-insert]');
    if (insert && form) {
      var area = form.elements.content;
      if (area) area.value += ' <a href="' + insert.getAttribute('data-url') + '">' + insert.getAttribute('data-title') + '</a>';
    }
  });
  if (form) form.addEventListener('submit', save);
  load();
})();
