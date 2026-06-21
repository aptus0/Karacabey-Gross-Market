/* ─────────────────────────────────────────────────────────────
   KGM Mail · Frontend
   ───────────────────────────────────────────────────────────── */
(() => {
  'use strict';

  // ─── State ──────────────────────────────────────────────────
  const state = {
    view: 'dashboard',
    inbox: [],
    tickets: [],
    sent: [],
    templates: [],
    mailboxes: [],
    selectedInboxUid: null,
    selectedTicketUid: null,
    selectedTemplateUid: null,
    ticketStatusFilter: 'all',
    inboxFilter: '',
    online: false,
    polling: null,
    adminToken: '',
  };

  // ─── DOM helpers ────────────────────────────────────────────
  const $ = (sel, root = document) => root.querySelector(sel);
  const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));

  function esc(s) {
    return String(s ?? '').replace(/[&<>'"]/g, c => ({
      '&': '&amp;', '<': '&lt;', '>': '&gt;', '\'': '&#039;', '"': '&quot;'
    }[c]));
  }

  function fmtTime(d) {
    if (!d) return '';
    try {
      const date = new Date(d);
      const now = new Date();
      const diff = (now - date) / 1000;
      if (diff < 60) return 'şimdi';
      if (diff < 3600) return `${Math.floor(diff / 60)}dk`;
      if (diff < 86400) return `${Math.floor(diff / 3600)}sa`;
      if (diff < 604800) return `${Math.floor(diff / 86400)}g`;
      return date.toLocaleDateString('tr-TR', { day: '2-digit', month: 'short' });
    } catch { return ''; }
  }

  function fmtFull(d) {
    if (!d) return '';
    try { return new Date(d).toLocaleString('tr-TR'); } catch { return ''; }
  }

  // ─── API ────────────────────────────────────────────────────
  async function api(path, options = {}) {
    const headers = {
      'Accept': 'application/json',
      'Content-Type': 'application/json',
      ...(options.headers || {}),
    };
    if (state.adminToken) {
      headers['X-Mail-Admin-Token'] = state.adminToken;
    }

    const res = await fetch(path, {
      ...options,
      headers,
    });
    let data = null;
    try { data = await res.json(); } catch { /* empty body */ }
    if (!res.ok) {
      const msg = (data && data.message) || `HTTP ${res.status}`;
      throw new Error(msg);
    }
    return data || {};
  }

  // ─── Toast ──────────────────────────────────────────────────
  function toast(message, type = 'info') {
    const stack = $('#toastStack');
    if (!stack) return;
    const el = document.createElement('div');
    el.className = `toast toast--${type}`;
    el.textContent = message;
    stack.appendChild(el);
    setTimeout(() => {
      el.style.opacity = '0';
      el.style.transform = 'translateY(8px)';
      el.style.transition = 'opacity 0.2s, transform 0.2s';
      setTimeout(() => el.remove(), 220);
    }, 3200);
  }

  // ─── View switching ─────────────────────────────────────────
  function setView(viewName) {
    state.view = viewName;
    $$('.view').forEach(v => v.classList.toggle('is-active', v.dataset.viewContent === viewName));
    $$('.nav-item').forEach(b => b.classList.toggle('is-active', b.dataset.view === viewName));
    if (viewName === 'sent') renderSent();
    if (viewName === 'templates') loadTemplates();
    if (viewName === 'mailboxes') renderMailboxes();
    if (viewName === 'system') renderSystem();
  }

  // ─── Status indicator ───────────────────────────────────────
  function setOnline(ok) {
    state.online = ok;
    const dot = $('#statusDot');
    const text = $('#statusText');
    if (dot) dot.className = `status-dot ${ok ? 'is-online' : 'is-offline'}`;
    if (text) text.textContent = ok ? 'Bağlı' : 'Bağlantı yok';
  }

  function setTokenStatus(mode = 'session') {
    const el = $('#adminTokenStatus');
    if (!el) return;
    el.textContent = state.adminToken
      ? 'Token ile bağlı'
      : (mode === 'missing' ? 'Panel oturumu veya token gerekli' : 'Panel oturumu');
  }

  function openTokenModal() {
    const modal = $('#tokenModal');
    if (!modal) return;
    modal.hidden = false;
    const input = $('#adminTokenInput');
    if (input) {
      input.value = state.adminToken || '';
      setTimeout(() => input.focus(), 80);
    }
  }

  function closeTokenModal() {
    const modal = $('#tokenModal');
    if (modal) modal.hidden = true;
  }

  function loadStoredToken() {
    try {
      state.adminToken = sessionStorage.getItem('kgm_mail_admin_token') || '';
    } catch {
      state.adminToken = '';
    }
    setTokenStatus();
  }

  function saveToken(token) {
    state.adminToken = token.trim();
    try {
      if (state.adminToken) {
        sessionStorage.setItem('kgm_mail_admin_token', state.adminToken);
      } else {
        sessionStorage.removeItem('kgm_mail_admin_token');
      }
    } catch { /* storage unavailable */ }
    setTokenStatus();
  }

  // ─── Renderers ──────────────────────────────────────────────
  function renderStats(stats) {
    const s = stats.stats || {};
    $('#statsGrid').innerHTML = `
      <div class="stat stat--orange"><div class="stat__value">${stats.inbound_count ?? 0}</div><div class="stat__label">Gelen Mail</div></div>
      <div class="stat stat--blue"><div class="stat__value">${stats.ticket_count ?? 0}</div><div class="stat__label">Destek Ticket</div></div>
      <div class="stat stat--green"><div class="stat__value">${s.sent ?? 0}</div><div class="stat__label">Gönderilen</div></div>
      <div class="stat stat--gray"><div class="stat__value">${stats.queue_depth ?? 0}</div><div class="stat__label">Kuyrukta</div></div>
      <div class="stat ${stats.maildir_poll ? 'stat--green' : 'stat--gray'}"><div class="stat__value">${stats.maildir_poll ? 'Aktif' : 'Kapalı'}</div><div class="stat__label">Maildir Poller</div></div>
    `;
    $('#inboxBadge').textContent = stats.inbound_count ?? 0;
    const openTickets = state.tickets.filter(t => t.status === 'open' || t.status === 'pending').length;
    $('#ticketBadge').textContent = openTickets;
  }

  function renderInboxList(target = '#inboxList', items = null, limit = null) {
    const list = items ?? state.inbox;
    const filter = state.inboxFilter.toLowerCase();
    const filtered = filter
      ? list.filter(m => (m.subject || '').toLowerCase().includes(filter)
                      || (m.from_email || '').toLowerCase().includes(filter))
      : list;
    const sliced = limit ? filtered.slice(0, limit) : filtered;
    const el = $(target);
    if (!el) return;
    if (sliced.length === 0) {
      el.innerHTML = `<div class="empty"><p>Gelen mail yok</p></div>`;
      return;
    }
    el.innerHTML = sliced.map(m => `
      <div class="list-item ${state.selectedInboxUid === m.uid ? 'is-active' : ''}" data-inbox="${esc(m.uid)}">
        <div class="list-item__top">
          <span class="list-item__title">${esc(m.subject || '(Konu yok)')}</span>
          <span class="list-item__time">${fmtTime(m.received_at)}</span>
        </div>
        <div class="list-item__meta">${esc(m.from_email)}</div>
        <div class="list-item__preview">${esc((m.text_body || '').slice(0, 100))}</div>
      </div>
    `).join('');
  }

  function renderTicketList() {
    const filter = state.ticketStatusFilter;
    const filtered = filter === 'all' ? state.tickets : state.tickets.filter(t => t.status === filter);
    const el = $('#ticketList');
    if (filtered.length === 0) {
      el.innerHTML = `<div class="empty"><p>Bu filtrede ticket yok</p></div>`;
      return;
    }
    el.innerHTML = filtered.map(t => `
      <div class="list-item ${state.selectedTicketUid === t.uid ? 'is-active' : ''}" data-ticket="${esc(t.uid)}">
        <div class="list-item__top">
          <span class="list-item__title">#${esc(t.number)} — ${esc(t.subject)}</span>
          <span class="list-item__time">${fmtTime(t.updated_at)}</span>
        </div>
        <div class="list-item__meta">${esc(t.customer_email)}</div>
        <div class="list-item__top">
          <span class="list-item__preview">${esc(t.last_message || '')}</span>
          <span class="tag tag--${esc(t.status)}">${esc(t.status)}</span>
        </div>
      </div>
    `).join('');
    $('#ticketCount').textContent = filtered.length;
  }

  function renderTicketPreview() {
    const open = state.tickets.filter(t => t.status === 'open' || t.status === 'pending').slice(0, 6);
    if (open.length === 0) {
      $('#ticketPreview').innerHTML = `<div class="empty"><p>Açık ticket yok</p></div>`;
      return;
    }
    $('#ticketPreview').innerHTML = open.map(t => `
      <div class="list-item" data-ticket="${esc(t.uid)}">
        <div class="list-item__top">
          <span class="list-item__title">#${esc(t.number)} — ${esc(t.subject)}</span>
          <span class="tag tag--${esc(t.status)}">${esc(t.status)}</span>
        </div>
        <div class="list-item__meta">${esc(t.customer_email)}</div>
      </div>
    `).join('');
  }

  async function renderInboxDetail(uid) {
    try {
      const m = await api('/api/v1/inbox/messages/' + encodeURIComponent(uid));
      state.selectedInboxUid = uid;
      renderInboxList();
      $('#inboxDetail').innerHTML = `
        <div class="detail-header">
          <h2>${esc(m.subject || '(Konu yok)')}</h2>
          <div class="detail-meta">
            <div class="detail-meta__row"><span class="detail-meta__label">Gönderen:</span> ${esc(m.from_name ? m.from_name + ' <' + m.from_email + '>' : m.from_email)}</div>
            <div class="detail-meta__row"><span class="detail-meta__label">Alıcı:</span> ${(m.to || []).map(esc).join(', ')}</div>
            ${m.cc && m.cc.length ? `<div class="detail-meta__row"><span class="detail-meta__label">CC:</span> ${m.cc.map(esc).join(', ')}</div>` : ''}
            <div class="detail-meta__row"><span class="detail-meta__label">Tarih:</span> ${fmtFull(m.received_at)}</div>
            ${m.ticket_uid ? `<div class="detail-meta__row"><span class="detail-meta__label">Ticket:</span> <a class="link" data-open-ticket="${esc(m.ticket_uid)}" href="#">Aç →</a></div>` : ''}
          </div>
        </div>
        <div class="detail-body">${esc(m.text_body || '(boş)')}</div>
        <div class="detail-actions">
          <button type="button" class="btn btn--danger" data-delete-inbox="${esc(m.uid)}">Maili Sil</button>
        </div>
      `;
    } catch (e) {
      $('#inboxDetail').innerHTML = `<div class="empty"><p>Yüklenemedi: ${esc(e.message)}</p></div>`;
    }
  }

  async function renderTicketDetail(uid) {
    try {
      const t = await api('/api/v1/tickets/' + encodeURIComponent(uid));
      state.selectedTicketUid = uid;
      renderTicketList();
      const thread = (t.messages || []).map(m => `
        <div class="thread-msg thread-msg--${m.direction === 'inbound' ? 'inbound' : 'outbound'}">
          <div class="thread-msg__head">
            <div class="thread-msg__from">
              <span class="who">${esc(m.from_email)}</span>
              <span class="role">${m.direction === 'inbound' ? 'Müşteri' : 'Destek'}</span>
            </div>
            <div class="thread-msg__time">${fmtFull(m.created_at)}</div>
          </div>
          <div class="thread-msg__body">${esc(m.text_body || '')}</div>
        </div>
      `).join('');

      $('#ticketDetail').innerHTML = `
        <div class="detail-header">
          <h2>#${esc(t.number)} — ${esc(t.subject)}</h2>
          <div class="detail-meta">
            <div class="detail-meta__row"><span class="detail-meta__label">Müşteri:</span> ${esc(t.customer_name || '')} &lt;${esc(t.customer_email)}&gt;</div>
            <div class="detail-meta__row"><span class="detail-meta__label">Mailbox:</span> ${esc(t.mailbox)}</div>
            <div class="detail-meta__row"><span class="detail-meta__label">Durum:</span> <span class="tag tag--${esc(t.status)}">${esc(t.status)}</span></div>
            <div class="detail-meta__row"><span class="detail-meta__label">Açıldı:</span> ${fmtFull(t.created_at)}</div>
          </div>
        </div>
        <div class="thread">${thread}</div>
        <form class="reply-form" id="ticketReplyForm" data-uid="${esc(t.uid)}">
          <textarea name="text_body" placeholder="Müşteriye yanıt yazın..." required></textarea>
          <div class="reply-form__actions">
            <div class="filter-chips">
              <button type="button" class="chip ${t.status === 'open' ? 'is-active' : ''}" data-set-status="open">Açık</button>
              <button type="button" class="chip ${t.status === 'pending' ? 'is-active' : ''}" data-set-status="pending">Beklemede</button>
              <button type="button" class="chip ${t.status === 'resolved' ? 'is-active' : ''}" data-set-status="resolved">Çözüldü</button>
              <button type="button" class="chip ${t.status === 'closed' ? 'is-active' : ''}" data-set-status="closed">Kapalı</button>
            </div>
            <button type="submit" class="btn btn--primary">Yanıtı Gönder</button>
          </div>
        </form>
      `;

      // Reply form bind
      $('#ticketReplyForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const ta = e.target.querySelector('[name=text_body]');
        const text = ta.value.trim();
        if (!text) return;
        try {
          await api('/api/v1/tickets/' + encodeURIComponent(uid) + '/reply', {
            method: 'POST',
            body: JSON.stringify({ text_body: text }),
          });
          toast('Yanıt kuyruğa alındı', 'success');
          ta.value = '';
          await loadTickets();
          await renderTicketDetail(uid);
        } catch (err) {
          toast(err.message, 'error');
        }
      });

      // Status chips
      $$('[data-set-status]', $('#ticketDetail')).forEach(btn => {
        btn.addEventListener('click', async () => {
          const status = btn.dataset.setStatus;
          try {
            await api('/api/v1/tickets/' + encodeURIComponent(uid) + '/status', {
              method: 'PATCH',
              body: JSON.stringify({ status }),
            });
            toast(`Durum güncellendi: ${status}`, 'success');
            await loadTickets();
            await renderTicketDetail(uid);
          } catch (err) {
            toast(err.message, 'error');
          }
        });
      });
    } catch (e) {
      $('#ticketDetail').innerHTML = `<div class="empty"><p>Yüklenemedi: ${esc(e.message)}</p></div>`;
    }
  }

  function renderSent() {
    const tbody = $('#sentTableBody');
    if (state.sent.length === 0) {
      tbody.innerHTML = `<tr><td colspan="5" style="text-align:center;color:var(--text-muted);padding:30px">Henüz gönderim yok</td></tr>`;
      return;
    }
    tbody.innerHTML = state.sent.map(m => `
      <tr>
        <td><span class="tag tag--${esc(m.status)}">${esc(m.status)}</span></td>
        <td>${esc((m.to || []).join(', '))}</td>
        <td>${esc(m.subject || '')}</td>
        <td>${fmtFull(m.created_at)}</td>
        <td>
          ${esc(m.last_error || '—')}
          <button type="button" class="btn btn--danger btn--sm" data-delete-sent="${esc(m.uid)}">Sil</button>
        </td>
      </tr>
    `).join('');
  }

  function renderTemplateList() {
    const el = $('#templateList');
    if (state.templates.length === 0) {
      el.innerHTML = `<div class="empty"><p>Henüz şablon yok</p></div>`;
      return;
    }
    el.innerHTML = state.templates.map(t => `
      <div class="list-item ${state.selectedTemplateUid === t.uid ? 'is-active' : ''}" data-template="${esc(t.uid)}">
        <div class="list-item__top">
          <span class="list-item__title">${esc(t.name || t.key)}</span>
          <span class="list-item__time">${fmtTime(t.updated_at)}</span>
        </div>
        <div class="list-item__meta">${esc(t.key)}</div>
      </div>
    `).join('');
  }

  function renderTemplateEditor(t = null) {
    const isNew = !t;
    const tpl = t || { key: '', name: '', subject: '', text_body: '', html_body: '' };
    $('#templateEditor').innerHTML = `
      <form class="template-form" id="templateForm">
        <div class="form-row">
          <label>Anahtar (key) *<input name="key" value="${esc(tpl.key)}" placeholder="order_shipped" ${isNew ? '' : 'readonly'} required></label>
          <label>Görünür Ad<input name="name" value="${esc(tpl.name)}" placeholder="Sipariş Kargolandı"></label>
        </div>
        <label>Konu *<input name="subject" value="${esc(tpl.subject)}" required></label>
        <div class="tabs-mini">
          <button type="button" class="tab is-active" data-pane="text-pane">Metin</button>
          <button type="button" class="tab" data-pane="html-pane">HTML</button>
        </div>
        <div class="pane is-active" data-pane="text-pane">
          <textarea name="text_body" rows="10" placeholder="Şablon metni... {{customer_name}} gibi değişkenler kullanabilirsiniz">${esc(tpl.text_body)}</textarea>
        </div>
        <div class="pane" data-pane="html-pane">
          <textarea name="html_body" rows="10" class="mono" placeholder="<p>HTML şablon...</p>">${esc(tpl.html_body)}</textarea>
        </div>
        <div class="template-actions">
          ${isNew ? '' : '<button type="button" class="btn btn--danger" id="deleteTemplateBtn">Sil</button>'}
          <div style="margin-left:auto;display:flex;gap:8px">
            <button type="submit" class="btn btn--primary">${isNew ? 'Oluştur' : 'Güncelle'}</button>
          </div>
        </div>
      </form>
    `;

    $$('.tab', $('#templateEditor')).forEach(tab => {
      tab.addEventListener('click', () => {
        $$('.tab', $('#templateEditor')).forEach(t => t.classList.toggle('is-active', t === tab));
        $$('.pane', $('#templateEditor')).forEach(p => p.classList.toggle('is-active', p.dataset.pane === tab.dataset.pane));
      });
    });

    $('#templateForm').addEventListener('submit', async (e) => {
      e.preventDefault();
      const fd = new FormData(e.target);
      const payload = {
        key: fd.get('key'),
        name: fd.get('name'),
        subject: fd.get('subject'),
        text_body: fd.get('text_body'),
        html_body: fd.get('html_body'),
      };
      try {
        if (isNew) {
          await api('/api/v1/mail/templates', { method: 'POST', body: JSON.stringify(payload) });
          toast('Şablon oluşturuldu', 'success');
        } else {
          await api('/api/v1/mail/templates/' + encodeURIComponent(tpl.uid), { method: 'PUT', body: JSON.stringify(payload) });
          toast('Şablon güncellendi', 'success');
        }
        await loadTemplates();
      } catch (err) {
        toast(err.message, 'error');
      }
    });

    const delBtn = $('#deleteTemplateBtn');
    if (delBtn) {
      delBtn.addEventListener('click', async () => {
        if (!confirm('Bu şablonu sil?')) return;
        try {
          await api('/api/v1/mail/templates/' + encodeURIComponent(tpl.uid), { method: 'DELETE' });
          toast('Şablon silindi', 'success');
          state.selectedTemplateUid = null;
          await loadTemplates();
          $('#templateEditor').innerHTML = `<div class="empty"><p>Soldan bir şablon seçin</p></div>`;
        } catch (err) {
          toast(err.message, 'error');
        }
      });
    }
  }

  function renderMailboxes() {
    const grid = $('#mailboxGrid');
    if (state.mailboxes.length === 0) {
      grid.innerHTML = `<div class="empty"><p>Mailbox yok</p></div>`;
      return;
    }
    grid.innerHTML = state.mailboxes.map(m => `
      <div class="mbx-card">
        <div class="mbx-card__addr">${esc(m.address)}</div>
        <div class="mbx-card__name">${esc(m.name)}</div>
        <div class="mbx-card__purpose">${esc(m.purpose)}</div>
        <div class="mbx-card__foot">
          <span class="tag ${m.enabled ? 'tag--sent' : 'tag--closed'}">${m.enabled ? 'aktif' : 'pasif'}</span>
          <span>mode: ${esc(m.inbound_mode)}</span>
        </div>
      </div>
    `).join('');
  }

  async function renderSystem() {
    try {
      const sum = await api('/api/v1/system/summary');
      $('#systemSummary').innerHTML = `
        <div class="kv__row"><span class="kv__key">Outbound (mail kuyruğu)</span><span class="kv__val">${sum.outbound ?? 0}</span></div>
        <div class="kv__row"><span class="kv__key">Inbound (gelen)</span><span class="kv__val">${sum.inbound ?? 0}</span></div>
        <div class="kv__row"><span class="kv__key">Toplam ticket</span><span class="kv__val">${sum.tickets ?? 0}</span></div>
        <div class="kv__row"><span class="kv__key">Açık ticket</span><span class="kv__val">${sum.open_tickets ?? 0}</span></div>
        <div class="kv__row"><span class="kv__key">SMTP</span><span class="kv__val">${sum.smtp_disabled ? 'devre dışı' : 'aktif'}</span></div>
        <div class="kv__row"><span class="kv__key">Maildir polling</span><span class="kv__val">${sum.maildir_poll ? 'aktif' : 'kapalı'}</span></div>
        <div class="kv__row"><span class="kv__key">Maildir root</span><span class="kv__val">${esc(sum.maildir_root || '—')}</span></div>
      `;
    } catch (e) {
      $('#systemSummary').innerHTML = `<div class="empty"><p>${esc(e.message)}</p></div>`;
    }
  }

  // ─── Loaders ────────────────────────────────────────────────
  async function loadStats() {
    const stats = await api('/api/v1/mail/queue/stats');
    renderStats(stats);
  }

  async function loadInbox() {
    const data = await api('/api/v1/inbox/messages?limit=100');
    state.inbox = data.items || [];
    renderInboxList('#inboxList');
    renderInboxList('#inboxPreview', state.inbox, 5);
    $('#inboxCount').textContent = state.inbox.length;
  }

  async function loadTickets() {
    const data = await api('/api/v1/tickets?limit=100');
    state.tickets = data.items || [];
    renderTicketList();
    renderTicketPreview();
  }

  async function loadSent() {
    const data = await api('/api/v1/mail/messages?limit=100');
    state.sent = data.items || [];
    if (state.view === 'sent') renderSent();
  }

  async function loadTemplates() {
    const data = await api('/api/v1/mail/templates');
    state.templates = data.items || [];
    renderTemplateList();
    // Compose modal select
    const select = $('#composeTemplate');
    if (select) {
      select.innerHTML = '<option value="">— Boş —</option>' + state.templates.map(t =>
        `<option value="${esc(t.uid)}">${esc(t.name || t.key)}</option>`
      ).join('');
    }
  }

  async function loadMailboxes() {
    const data = await api('/api/v1/mailboxes');
    state.mailboxes = data.items || [];
    // Populate compose from-select
    const fromSel = $('#composeFrom');
    if (fromSel) {
      fromSel.innerHTML = state.mailboxes
        .filter(m => m.enabled)
        .map(m => `<option value="${esc(m.address)}">${esc(m.address)}</option>`)
        .join('');
    }
    if (state.view === 'mailboxes') renderMailboxes();
  }

  async function loadAll() {
    try {
      await Promise.all([loadStats(), loadInbox(), loadTickets(), loadSent(), loadMailboxes()]);
      setOnline(true);
      setTokenStatus();
    } catch (e) {
      setOnline(false);
      if (String(e.message).includes('401') || String(e.message).toLowerCase().includes('yetkisiz') || String(e.message).toLowerCase().includes('oturumu gerekli')) {
        setTokenStatus('missing');
        openTokenModal();
      }
      toast('Bağlantı hatası: ' + e.message, 'error');
    }
  }

  // ─── Compose modal ──────────────────────────────────────────
  function openCompose(prefill = {}) {
    const modal = $('#composeModal');
    modal.hidden = false;
    if (prefill.to) $('input[name="to"]', modal).value = prefill.to;
    if (prefill.subject) $('input[name="subject"]', modal).value = prefill.subject;
    setTimeout(() => $('input[name="to"]', modal).focus(), 100);
  }
  function closeCompose() {
    const modal = $('#composeModal');
    modal.hidden = true;
    $('#composeForm').reset();
  }

  // ─── Wire-up: top-level event handlers ──────────────────────
  function wireEvents() {
    // Nav buttons
    $$('[data-view]').forEach(b => b.addEventListener('click', () => setView(b.dataset.view)));

    // Refresh
    $('#refreshBtn').addEventListener('click', () => loadAll().then(() => toast('Güncellendi', 'success')));

    const tokenBtn = $('#adminTokenBtn');
    if (tokenBtn) tokenBtn.addEventListener('click', openTokenModal);
    const saveTokenBtn = $('#saveTokenBtn');
    if (saveTokenBtn) {
      saveTokenBtn.addEventListener('click', async () => {
        saveToken($('#adminTokenInput')?.value || '');
        closeTokenModal();
        await loadAll();
        await loadTemplates().catch(() => {});
      });
    }
    const tokenInput = $('#adminTokenInput');
    if (tokenInput) {
      tokenInput.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
          e.preventDefault();
          $('#saveTokenBtn')?.click();
        }
      });
    }

    // Compose
    $('#composeBtn').addEventListener('click', () => openCompose());
    $('#composeBtnSidebar')?.addEventListener('click', () => openCompose());
    $$('[data-close]').forEach(b => b.addEventListener('click', () => {
      closeCompose();
      closeTokenModal();
    }));

    // Compose form
    $('#composeForm').addEventListener('submit', async (e) => {
      e.preventDefault();
      const fd = new FormData(e.target);
      const payload = {
        from_email: fd.get('from_email'),
        to: [fd.get('to')],
        subject: fd.get('subject'),
        text_body: fd.get('text_body'),
        html_body: fd.get('html_body') || undefined,
      };
      const cc = (fd.get('cc') || '').split(',').map(s => s.trim()).filter(Boolean);
      if (cc.length) payload.cc = cc;
      try {
        const d = await api('/api/v1/mail/send', { method: 'POST', body: JSON.stringify(payload) });
        toast('Mail kuyruğa alındı: ' + d.message_uid, 'success');
        closeCompose();
        await Promise.all([loadStats(), loadSent()]);
      } catch (err) {
        toast(err.message, 'error');
      }
    });

    // Compose template selector
    $('#composeTemplate').addEventListener('change', (e) => {
      const uid = e.target.value;
      if (!uid) return;
      const tpl = state.templates.find(t => t.uid === uid);
      if (!tpl) return;
      const form = $('#composeForm');
      form.querySelector('[name=subject]').value = tpl.subject || '';
      form.querySelector('[name=text_body]').value = tpl.text_body || '';
      form.querySelector('[name=html_body]').value = tpl.html_body || '';
    });

    // Compose tabs (text/html)
    $$('.tab', $('#composeModal')).forEach(t => {
      t.addEventListener('click', () => {
        $$('.tab', $('#composeModal')).forEach(x => x.classList.toggle('is-active', x === t));
        $$('.pane', $('#composeModal')).forEach(p => p.classList.toggle('is-active', p.dataset.pane === t.dataset.pane));
      });
    });

    // Inbox click delegation
    $('#inboxList').addEventListener('click', (e) => {
      const item = e.target.closest('[data-inbox]');
      if (item) renderInboxDetail(item.dataset.inbox);
    });
    $('#inboxPreview').addEventListener('click', (e) => {
      const item = e.target.closest('[data-inbox]');
      if (item) { setView('inbox'); renderInboxDetail(item.dataset.inbox); }
    });
    $('#inboxDetail').addEventListener('click', (e) => {
      const link = e.target.closest('[data-open-ticket]');
      if (link) { e.preventDefault(); setView('tickets'); renderTicketDetail(link.dataset.openTicket); }
      const del = e.target.closest('[data-delete-inbox]');
      if (del && confirm('Bu gelen mail silinsin mi?')) {
        api('/api/v1/inbox/messages/' + encodeURIComponent(del.dataset.deleteInbox), { method: 'DELETE' })
          .then(async () => { state.selectedInboxUid = null; $('#inboxDetail').innerHTML = '<div class="empty"><p>Mail silindi</p></div>'; await loadInbox(); toast('Mail silindi', 'success'); })
          .catch(err => toast(err.message, 'error'));
      }
    });

    $('#sentTableBody').addEventListener('click', (e) => {
      const del = e.target.closest('[data-delete-sent]');
      if (!del || !confirm('Bu gönderim kaydı silinsin mi?')) return;
      api('/api/v1/mail/messages/' + encodeURIComponent(del.dataset.deleteSent), { method: 'DELETE' })
        .then(async () => { await Promise.all([loadSent(), loadStats()]); toast('Gönderim kaydı silindi', 'success'); })
        .catch(err => toast(err.message, 'error'));
    });

    // Inbox filter
    $('#inboxFilter').addEventListener('input', (e) => {
      state.inboxFilter = e.target.value;
      renderInboxList('#inboxList');
    });

    // Ticket list click
    $('#ticketList').addEventListener('click', (e) => {
      const item = e.target.closest('[data-ticket]');
      if (item) renderTicketDetail(item.dataset.ticket);
    });
    $('#ticketPreview').addEventListener('click', (e) => {
      const item = e.target.closest('[data-ticket]');
      if (item) { setView('tickets'); renderTicketDetail(item.dataset.ticket); }
    });

    // Ticket status chips (top filter)
    $$('[data-status]', $('#ticketStatusFilter')).forEach(b => {
      b.addEventListener('click', () => {
        state.ticketStatusFilter = b.dataset.status;
        $$('[data-status]', $('#ticketStatusFilter')).forEach(x => x.classList.toggle('is-active', x === b));
        renderTicketList();
      });
    });

    // Templates
    $('#newTemplateBtn').addEventListener('click', () => {
      state.selectedTemplateUid = null;
      renderTemplateList();
      renderTemplateEditor(null);
    });
    $('#templateList').addEventListener('click', (e) => {
      const item = e.target.closest('[data-template]');
      if (!item) return;
      const tpl = state.templates.find(t => t.uid === item.dataset.template);
      if (tpl) {
        state.selectedTemplateUid = tpl.uid;
        renderTemplateList();
        renderTemplateEditor(tpl);
      }
    });

    // Global search → filter inbox
    $('#globalSearch').addEventListener('input', (e) => {
      state.inboxFilter = e.target.value;
      if (state.view !== 'inbox') {
        setView('inbox');
        $('#inboxFilter').value = e.target.value;
      }
      renderInboxList('#inboxList');
    });

    // Keyboard shortcuts
    document.addEventListener('keydown', (e) => {
      if (e.target.matches('input, textarea, select')) return;
      if (e.key === '/') { e.preventDefault(); $('#globalSearch').focus(); }
      if (e.key === 'c') { e.preventDefault(); openCompose(); }
      if (e.key === 'r') { e.preventDefault(); loadAll(); }
      if (e.key === 'Escape') closeCompose();
    });
  }

  // ─── Boot ───────────────────────────────────────────────────
  function startPolling() {
    if (state.polling) clearInterval(state.polling);
    state.polling = setInterval(() => loadAll().catch(() => {}), 30000);
  }

  document.addEventListener('DOMContentLoaded', () => {
    loadStoredToken();
    wireEvents();
    loadAll().then(() => loadTemplates());
    startPolling();
  });
})();
