/**
 * Mykid core client script (Package A & B).
 *
 * - One delegated click handler for every [data-action] element.
 * - After any change the page re-fetches itself and swaps the [data-live] regions,
 *   so the server-rendered (scope-filtered) markup is always the source of truth.
 * - Every page polls the state version, so changes made on another device appear live.
 */
(function () {
  'use strict';

  const CFG = window.MYKID || {};
  const DEBUG = Boolean(CFG.debug);
  const POLL_INTERVAL_MS = 8000;
  const PICKUP_POLL_MS = 4000; // รับ-ส่ง pages check their own status faster
  const TOAST_DURATION_MS = 2600;

  /* ------------------------------------------------------------------------
   * Logging (dev only)
   * --------------------------------------------------------------------- */
  const Log = {
    start(fn, data) { if (DEBUG) console.log(`[MykidCore][${fn}] START`, data ?? ''); },
    end(fn, data) { if (DEBUG) console.log(`[MykidCore][${fn}] END`, data ?? ''); },
    info(fn, msg, data) { if (DEBUG) console.log(`[MykidCore][${fn}] ${msg}`, data ?? ''); },
    error(fn, error, context) { console.error(`[MykidCore][${fn}] ERROR`, { error, ...context }); },
  };

  function devGroup(title, url, request, status, response) {
    if (!DEBUG) return;
    console.group(`[DEV] API Response — ${title}`);
    console.log('URL:', url);
    console.log('Request:', request);
    console.log('Status:', status);
    console.log('Response:', response);
    console.groupEnd();
  }

  /** Error whose message is safe to show to users. */
  class UserError extends Error {}

  const $ = (selector, root = document) => root.querySelector(selector);
  const $$ = (selector, root = document) => Array.from(root.querySelectorAll(selector));

  function setBusy(el, busy) {
    if (!el) return;
    el.disabled = busy;
    el.classList.toggle('is-busy', busy);
  }

  function toast(message, type = 'success') {
    const stack = $('[data-toast-stack]');
    if (!stack || !message) return;
    Log.info('toast', type, { message });
    const icons = { success: '✅', error: '😥', info: '🔔', lock: '🔒' };
    const el = document.createElement('div');
    el.className = `toast toast--${type}`;
    el.setAttribute('role', type === 'error' ? 'alert' : 'status');
    el.innerHTML = `<span class="toast__icon" aria-hidden="true">${icons[type] || icons.success}</span><span class="toast__text"></span>`;
    el.querySelector('.toast__text').textContent = message;
    stack.appendChild(el);
    requestAnimationFrame(() => el.classList.add('is-in'));
    setTimeout(() => { el.classList.remove('is-in'); setTimeout(() => el.remove(), 300); }, TOAST_DURATION_MS);
  }

  /* ------------------------------------------------------------------------
   * API + live refresh
   * --------------------------------------------------------------------- */
  /** POST to the API. With `files`, sends multipart/form-data (fields + images[]); otherwise JSON. */
  async function api(action, payload = {}, { quiet = false, files = null } = {}) {
    if (!quiet) Log.start('api', { action, payload, files: files ? files.length : 0 });
    const request = { action, ...payload };
    let body = JSON.stringify(request);
    const headers = { 'X-CSRF-Token': CFG.csrf };
    if (files) {
      body = new FormData();
      Object.entries(request).forEach(([k, v]) => body.append(k, typeof v === 'object' && v !== null ? JSON.stringify(v) : String(v)));
      files.forEach((f) => body.append('images[]', f, f.name));
    } else {
      headers['Content-Type'] = 'application/json';
    }
    let response;
    let data;
    try {
      response = await fetch(CFG.apiUrl, { method: 'POST', credentials: 'same-origin', headers, body });
      data = await response.json();
    } catch (error) {
      Log.error('api', error, { action, payload });
      throw new UserError('ไม่สามารถเชื่อมต่อได้ กรุณาลองใหม่อีกครั้ง');
    }
    if (!quiet) devGroup(action, CFG.apiUrl, request, response.status, data);
    if (!data.ok) {
      Log.error('api', data.message, { action, status: response.status });
      if (data.redirect) setTimeout(() => { window.location.href = data.redirect; }, 1200);
      throw new UserError(data.message || 'กรุณาลองใหม่อีกครั้ง');
    }
    if (data.version) CFG.version = data.version;
    if (typeof data.unread === 'number') Notifications.setBadges(data.unread);
    if (!quiet) Log.end('api', { action, version: data.version });
    return data;
  }

  /** Re-fetch this page and swap every [data-live] region (server stays the source of truth). */
  async function refreshLive() {
    const regions = $$('[data-live][id]');
    if (!regions.length) return;
    Log.start('refreshLive', { regions: regions.map((r) => r.id) });
    try {
      const html = await fetch(window.location.href, { credentials: 'same-origin' }).then((r) => r.text());
      const doc = new DOMParser().parseFromString(html, 'text/html');
      regions.forEach((region) => {
        const fresh = doc.getElementById(region.id);
        if (fresh) region.innerHTML = fresh.innerHTML;
      });
      restoreUiState();
      Log.end('refreshLive');
    } catch (error) {
      Log.error('refreshLive', error, {});
    }
  }

  async function runAction(el, action, payload, onSuccess, files = null) {
    setBusy(el, true);
    try {
      const data = await api(action, payload, { files });
      if (onSuccess) onSuccess(data);
      toast(data.message);
      await refreshLive();
      return data;
    } catch (error) {
      toast(error instanceof UserError ? error.message : 'กรุณาลองใหม่อีกครั้ง', 'error');
      if (!(error instanceof UserError)) Log.error('runAction', error, { action, payload });
      return null;
    } finally {
      setBusy(el, false);
    }
  }

  /* ------------------------------------------------------------------------
   * Sheets + confirm
   * --------------------------------------------------------------------- */
  let lastFocused = null;

  function openSheet(id) {
    Log.info('openSheet', id);
    const sheet = document.getElementById(id);
    if (!sheet) return null;
    lastFocused = document.activeElement;
    sheet.classList.add('is-open');
    sheet.setAttribute('aria-hidden', 'false');
    document.body.classList.add('has-sheet');
    const panel = $('.sheet__panel', sheet);
    panel.setAttribute('tabindex', '-1');
    setTimeout(() => panel.focus({ preventScroll: true }), 50);
    return sheet;
  }

  function closeSheet(sheet) {
    (sheet ? [sheet] : $$('.sheet.is-open')).forEach((s) => {
      s.classList.remove('is-open');
      s.setAttribute('aria-hidden', 'true');
      s.dispatchEvent(new CustomEvent('sheet:close'));
    });
    if (!$('.sheet.is-open')) document.body.classList.remove('has-sheet');
    if (lastFocused && document.contains(lastFocused)) lastFocused.focus({ preventScroll: true });
  }

  function confirmDialog({ title, message, icon = '🗑️', okLabel = 'ยืนยัน' }) {
    return new Promise((resolve) => {
      const sheet = openSheet('sheet-confirm');
      if (!sheet) return resolve(window.confirm(message));
      $('[data-confirm-title]', sheet).textContent = title;
      $('[data-confirm-message]', sheet).textContent = message;
      $('[data-confirm-icon]', sheet).textContent = icon;
      const ok = $('[data-confirm-ok]', sheet);
      ok.textContent = okLabel;
      const cleanup = (result) => { ok.removeEventListener('click', onOk); sheet.removeEventListener('sheet:close', onClose); resolve(result); };
      const onOk = () => { cleanup(true); closeSheet(sheet); };
      const onClose = () => cleanup(false);
      ok.addEventListener('click', onOk);
      sheet.addEventListener('sheet:close', onClose);
    });
  }

  /* ------------------------------------------------------------------------
   * Generic schema forms (add / edit) + delete + quick save
   * --------------------------------------------------------------------- */
  function openForm(button) {
    const table = button.dataset.table;
    const record = button.dataset.record ? JSON.parse(button.dataset.record) : null;
    const defaults = button.dataset.defaults ? JSON.parse(button.dataset.defaults) : {};
    Log.start('openForm', { table, record, defaults });

    const sheet = openSheet(`sheet-form-${table}`);
    if (!sheet) return;
    const form = $('form[data-form-table]', sheet);
    form.reset();
    const values = record || defaults;
    form.elements.id.value = record ? record.id : '';
    Array.from(form.elements).forEach((el) => {
      if (!el.name || el.name === 'id' || el.type === 'file' || !(el.name in values)) return;
      const value = values[el.name] === null ? '' : String(values[el.name]);
      if (el.type === 'radio') el.checked = el.value === value;
      else el.value = value;
    });
    $$('[data-upload-preview]', form).forEach((box) => { box.innerHTML = ''; });
    $$('[data-secret-hint]', form).forEach((hint) => {
      hint.textContent = record && record.rtsp_configured ? '🔐 ตั้งค่าไว้แล้ว — เว้นว่างเพื่อใช้ค่าเดิม (ไม่แสดงค่าจริงเพื่อความปลอดภัย)' : 'ยังไม่ได้ตั้งค่า';
    });
    $('.sheet__title', sheet).textContent = record ? form.dataset.titleEdit : form.dataset.titleAdd;
    $('[data-form-error]', form).hidden = true;
  }

  async function submitForm(form) {
    const table = form.dataset.formTable;
    const id = Number(form.elements.id.value || 0);
    const data = {};
    new FormData(form).forEach((value, key) => { if (key !== 'id' && !(value instanceof File)) data[key] = value; });
    const imageInput = form.querySelector('[data-image-input]');
    Log.start('submitForm', { table, id, data, images: imageInput ? imageInput.files.length : 0 });

    const missing = Array.from(form.elements).find((el) => el.required && el.type !== 'file' && !String(el.value).trim());
    const errorEl = $('[data-form-error]', form);
    if (missing) {
      errorEl.textContent = 'กรุณากรอกข้อมูลที่จำเป็นให้ครบ';
      errorEl.hidden = false;
      missing.focus();
      return;
    }
    errorEl.hidden = true;
    const files = imageInput && imageInput.files.length ? await prepareImages(imageInput.files, errorEl) : null;
    if (files === false) return;
    const result = await runAction(form.querySelector('[type="submit"]'), 'save', { table, id, data }, () => closeSheet(form.closest('.sheet')), files);
    if (!result) {
      errorEl.textContent = 'ยังบันทึกไม่ได้ กรุณาตรวจสอบข้อมูลแล้วลองใหม่';
      errorEl.hidden = false;
    }
  }

  async function deleteRecord(button) {
    const { table, id, label } = button.dataset;
    const confirmed = await confirmDialog({ title: 'ลบข้อมูลนี้?', message: `“${label}” จะถูกลบออกจากระบบ`, okLabel: 'ลบ' });
    if (confirmed) await runAction(button, 'delete', { table, id: Number(id) });
  }

  function quickSave(button) {
    const { table, id, field, value } = button.dataset;
    return runAction(button, 'save', { table, id: Number(id), data: { [field]: value } });
  }

  /* ------------------------------------------------------------------------
   * Status, meals, intake, chat
   * --------------------------------------------------------------------- */
  async function setStatus(button) {
    const scope = button.closest('[data-status-scope]');
    const noteInput = scope ? $('[data-status-note]', scope) : null;
    const sheet = button.closest('.sheet'); // resolve before the region refreshes
    await runAction(button, 'set_status', { code: button.dataset.code, note: noteInput ? noteInput.value.trim() : '' }, () => {
      if (noteInput) noteInput.value = '';
      if (sheet) closeSheet(sheet);
    });
  }

  function toggleMealEdit(button, editing) {
    const card = button.closest('[data-meal]');
    const form = $('[data-meal-form]', card);
    if (!editing) { form.reset(); Picker.reset(form); }
    card.classList.toggle('is-editing', editing);
    form.hidden = !editing;
    if (editing) form.elements.items.focus();
  }

  async function saveMeal(form) {
    const card = form.closest('[data-meal]');
    const items = form.elements.items.value.split('\n').map((s) => s.trim()).filter(Boolean);
    if (!items.length) { toast('กรุณากรอกรายการอาหารอย่างน้อย 1 รายการ', 'error'); return; }
    const picked = Picker.files(form);
    const files = picked.length ? await prepareImages(picked) : null; // food photos taken / picked in this meal
    if (picked.length && !files) return;
    runAction(form.querySelector('[type="submit"]'), 'save_meal', { meal: card.dataset.meal, classroom_id: Number(card.dataset.classroom), items }, null, files);
  }

  let activeThread = null;

  function openThread(id) {
    const layout = $('[data-chat-layout]');
    if (!layout || !id) return;
    activeThread = String(id);
    $$('[data-thread]', layout).forEach((t) => t.classList.toggle('is-active', t.dataset.thread === activeThread));
    $$('[data-thread-panel]', layout).forEach((p) => { p.hidden = p.dataset.threadPanel !== activeThread; });
    layout.classList.add('is-thread-open');
    scrollChats();
  }

  function scrollChats() {
    $$('[data-chat-scroll]').forEach((box) => { box.scrollTop = box.scrollHeight; });
  }

  async function sendMessage(form) {
    const input = form.elements.text;
    const text = input.value.trim();
    if (!text) return;
    const ok = await runAction(form.querySelector('[type="submit"]'), 'send_message', { student_id: Number(form.dataset.student), text });
    if (ok) {
      const fresh = $(`[data-chat-form][data-student="${form.dataset.student}"] input[name="text"]`);
      if (fresh) fresh.focus();
    }
  }

  /* ------------------------------------------------------------------------
   * Filters, tabs, school picker (client-side, within already-scoped data)
   * --------------------------------------------------------------------- */
  const filterState = {};
  const tabState = {};

  function applyFilter(target) {
    const bar = $(`[data-filter-bar="${target}"]`);
    const list = $(`[data-filter-list="${target}"]`);
    if (!bar || !list) return;
    const state = filterState[target] || { q: '', chip: '' };
    const items = $$('[data-filter-item]', list);
    let shown = 0;
    items.forEach((item) => {
      const okChip = !state.chip || item.dataset.group === state.chip;
      const okText = !state.q || (item.dataset.search || '').toLowerCase().includes(state.q);
      item.classList.toggle('is-filtered-out', !(okChip && okText));
      if (okChip && okText) shown++;
    });
    const count = $('[data-filter-count]', bar);
    if (count) count.textContent = `แสดง ${shown} จาก ${items.length} รายการ`;
    $$('[data-filter-chip]', bar).forEach((c) => c.classList.toggle('is-active', c.dataset.filterChip === state.chip));
    Log.info('applyFilter', target, { ...state, shown });
  }

  function switchTab(group, key) {
    tabState[group] = key;
    $$(`[data-tabs="${group}"] [data-tab]`).forEach((b) => {
      const on = b.dataset.tab === key;
      b.classList.toggle('is-active', on);
      b.setAttribute('aria-selected', on ? 'true' : 'false');
    });
    $$(`[data-tab-panel^="${group}:"]`).forEach((p) => { p.hidden = p.dataset.tabPanel !== `${group}:${key}`; });
  }

  function pickSchool(key) {
    $$('[data-school-pick]').forEach((b) => b.classList.toggle('is-active', b.dataset.schoolPick === key));
    $$('[data-school-kpi]').forEach((k) => { k.hidden = k.dataset.schoolKpi !== key; });
    $$('[data-school-row]').forEach((r) => { r.hidden = key !== 'all' && r.dataset.schoolRow !== key; });
  }

  /** Re-apply client UI state after live regions are swapped. */
  function restoreUiState() {
    Object.entries(tabState).forEach(([group, key]) => switchTab(group, key));
    $$('[data-filter-bar]').forEach((bar) => applyFilter(bar.dataset.filterBar));
    if (activeThread) openThread(activeThread);
    scrollChats();
    if (typeof CCTV !== 'undefined') CCTV.scan();
  }

  /* ------------------------------------------------------------------------
   * Login page
   * --------------------------------------------------------------------- */
  function formatPhone(value) {
    const d = value.replace(/\D/g, '').slice(0, 10);
    if (d.length <= 3) return d;
    if (d.length <= 6) return `${d.slice(0, 3)}-${d.slice(3)}`;
    return `${d.slice(0, 3)}-${d.slice(3, 6)}-${d.slice(6)}`;
  }

  function fillPin(boxes, digits) {
    boxes.forEach((box, i) => { box.value = digits[i] || ''; });
    (boxes.find((b) => !b.value) || boxes[boxes.length - 1]).focus();
  }

  function setTheme(theme) {
    document.body.className = document.body.className.replace(/\btheme-\S+/g, '').trim() + ` theme-${theme}`;
  }

  function initLogin() {
    const form = $('[data-login-form]');
    if (!form) return;
    Log.start('initLogin');
    const boxes = $$('.pin__box', form);
    const phone = $('[data-phone-input]', form);

    form.addEventListener('change', (event) => {
      if (event.target.name === 'role') setTheme(event.target.dataset.theme);
    });
    phone.addEventListener('input', () => { phone.value = formatPhone(phone.value); });
    boxes.forEach((box, index) => {
      box.addEventListener('input', () => {
        const digits = box.value.replace(/\D/g, '');
        if (digits.length > 1) { fillPin(boxes, (boxes.slice(0, index).map((b) => b.value).join('') + digits).slice(0, 6)); return; }
        box.value = digits;
        if (digits && boxes[index + 1]) boxes[index + 1].focus();
      });
      box.addEventListener('keydown', (event) => {
        if (event.key === 'Backspace' && !box.value && boxes[index - 1]) { boxes[index - 1].value = ''; boxes[index - 1].focus(); }
      });
      box.addEventListener('paste', (event) => {
        event.preventDefault();
        fillPin(boxes, (event.clipboardData.getData('text') || '').replace(/\D/g, '').slice(0, 6));
      });
    });
    form.addEventListener('submit', (event) => {
      const pin = boxes.map((b) => b.value).join('');
      if (phone.value.replace(/\D/g, '').length !== 10) {
        event.preventDefault(); toast('กรุณากรอกเบอร์โทรศัพท์ 10 หลัก', 'error'); phone.focus();
      } else if (pin.length !== 6) {
        event.preventDefault(); toast('กรุณากรอกรหัส PIN ให้ครบ 6 หลัก', 'error'); (boxes.find((b) => !b.value) || boxes[0]).focus();
      } else {
        setBusy(form.querySelector('[type="submit"]'), true);
      }
    });
    const auto = form.dataset.autofill && form.querySelector(`.demo-account-btn[data-phone="${form.dataset.autofill}"]`);
    if (auto) fillAccount(auto); // arrived from the role selection / demo guide
    if (auto && 'autosubmit' in form.dataset) form.requestSubmit(); // role card → straight into the demo
    Log.end('initLogin');
  }

  function fillAccount(button) {
    const form = button.closest('form');
    const { role, phone, pin } = button.dataset;
    const roleInput = form.querySelector(`input[name="role"][value="${role}"]`);
    if (roleInput && !roleInput.checked) {
      roleInput.checked = true;
      roleInput.dispatchEvent(new Event('change', { bubbles: true }));
    }
    form.querySelector('[data-phone-input]').value = formatPhone(phone);
    fillPin($$('.pin__box', form), pin);
    $$('.demo-account-btn', form).forEach((b) => b.classList.toggle('is-selected', b === button));
    form.querySelector('[type="submit"]').focus({ preventScroll: true });
    toast(`เลือกบัญชี “${button.querySelector('strong').textContent}” แล้ว กด “เข้าสู่ระบบ” ได้เลย`, 'info');
  }

  /* ------------------------------------------------------------------------
   * Clocks, chart tooltips, polling
   * --------------------------------------------------------------------- */
  function initClocks() {
    const pad = (n) => String(n).padStart(2, '0');
    const tick = () => {
      const now = new Date();
      const hm = `${pad(now.getHours())}:${pad(now.getMinutes())}`;
      $$('[data-clock]').forEach((c) => { c.textContent = hm; });
      $$('[data-clock-seconds]').forEach((c) => { c.textContent = `${hm}:${pad(now.getSeconds())}`; });
    };
    tick();
    setInterval(tick, 1000);
  }

  function initChartTips() {
    const tip = $('[data-chart-tip]');
    if (!tip) return;
    const show = (target, x, y) => {
      tip.textContent = target.dataset.tip;
      tip.hidden = false;
      const w = tip.offsetWidth;
      tip.style.left = `${Math.min(window.innerWidth - w - 8, Math.max(8, x - w / 2))}px`;
      tip.style.top = `${Math.max(8, y - 44)}px`;
    };
    document.addEventListener('pointermove', (e) => {
      const target = e.target.closest('[data-tip]');
      if (target) show(target, e.clientX, e.clientY); else tip.hidden = true;
    });
    document.addEventListener('focusin', (e) => {
      const target = e.target.closest('[data-tip]');
      if (!target) return;
      const r = target.getBoundingClientRect();
      show(target, r.left + r.width / 2, r.top);
    });
    document.addEventListener('focusout', () => { tip.hidden = true; });
  }

  function initPolling() {
    if (!CFG.poll) return; // also keeps the notification badge fresh on pages without live regions
    Log.info('initPolling', 'enabled', { intervalMs: POLL_INTERVAL_MS, version: CFG.version });
    setInterval(async () => {
      const typing = document.activeElement && document.activeElement.matches('input, textarea, select');
      if (document.hidden || typing || $('.sheet.is-open') || $('#cctv-viewer:not([hidden])')) return;
      try {
        const before = CFG.version;
        const data = await api('version', {}, { quiet: true });
        if (data.version !== before && $('[data-live]')) {
          Log.info('initPolling', 'changed', { before, after: data.version });
          await refreshLive();
          toast('มีข้อมูลอัปเดตใหม่', 'info');
        }
      } catch (error) {
        Log.error('initPolling', error, { version: CFG.version });
      }
    }, POLL_INTERVAL_MS);
  }

  /* ------------------------------------------------------------------------
   * รับ-ส่ง (pickup): parent notifies ETA, teacher moves the status, both pages poll
   * the dedicated pickup_status API (no WebSocket, no location of any kind).
   * --------------------------------------------------------------------- */
  /**
   * In-app notifications: red badge on every "แจ้งเตือน" menu + mark as read. No push of any kind —
   * the unread count comes back with polling ("version", "pickup_status") and every API response.
   */
  const Notifications = {
    setBadges(count) {
      $$('[data-notif-badge]').forEach((b) => {
        b.hidden = count <= 0;
        b.textContent = count > 99 ? '99+' : String(count);
        b.setAttribute('aria-label', `ยังไม่ได้อ่าน ${count} รายการ`);
      });
    },

    async open(button) {
      try {
        const data = await api('notification_read', { id: Number(button.dataset.id) });
        button.classList.remove('is-unread');
        const dot = $('.notif-item__dot', button);
        if (dot) { dot.textContent = '○'; dot.setAttribute('aria-label', 'อ่านแล้ว'); }
        if (data.link) window.location.href = new URL(data.link, new URL(CFG.apiUrl, window.location.href)).href;
      } catch (error) {
        toast(error instanceof UserError ? error.message : 'กรุณาลองใหม่อีกครั้ง', 'error');
      }
    },

    readAll(button) {
      return runAction(button, 'notification_read_all', {});
    },
  };

  const Pickup = {
    signature: null,
    statuses: {},

    remember(data) {
      this.signature = data.signature;
      this.statuses = Object.fromEntries(data.rows.map((r) => [r.id, r.status]));
    },

    /** Fetch status; refresh the page regions when something changed. `announce` = toast what changed. */
    async sync(announce) {
      const data = await api('pickup_status', {}, { quiet: true });
      if (data.signature === this.signature) return;
      const changed = data.rows.find((r) => this.statuses[r.id] !== r.status);
      Log.info('Pickup.sync', 'changed', { before: this.signature, after: data.signature, changed });
      this.remember(data);
      await refreshLive();
      if (announce && changed) toast(CFG.role === 'parent' ? `${changed.label} · ${changed.message}` : `${changed.label}`, 'info');
    },

    async init() {
      const region = $('[data-pickup-live]');
      if (!region) return;
      Log.start('Pickup.init', { signature: region.dataset.pickupSignature });
      try {
        this.remember(await api('pickup_status', {}, { quiet: true }));
      } catch (error) {
        Log.error('Pickup.init', error, {});
      }
      setInterval(async () => {
        if (document.hidden || $('.sheet.is-open')) return;
        try { await this.sync(true); } catch (error) { Log.error('Pickup.poll', error, {}); }
      }, PICKUP_POLL_MS);
      Log.end('Pickup.init', { intervalMs: PICKUP_POLL_MS });
    },

    /** Parent: one tap "กำลังไปรับลูก" for ONE child (no time / ETA). */
    async go(button) {
      const ok = await confirmDialog({ title: `กำลังไปรับ${button.dataset.name}?`, message: 'ครูจะได้รับแจ้งว่าคุณกำลังเดินทางมารับนักเรียน', icon: '🚗', okLabel: 'กำลังไปรับลูก' });
      if (!ok) return;
      if (await runAction(button, 'pickup_create', { student_id: Number(button.dataset.student) })) await this.sync(false);
    },

    async update(button) {
      const { id, status, name } = button.dataset;
      if (status === 'completed') {
        const ok = await confirmDialog({ title: `ส่งมอบ${name}แล้ว?`, message: 'ยืนยันว่าผู้ปกครองรับนักเรียนจากจุดรับ-ส่งแล้ว', icon: '🤝', okLabel: 'ส่งมอบนักเรียนแล้ว' });
        if (!ok) return;
      }
      if (await runAction(button, 'pickup_update', { id: Number(id), status })) await this.sync(false);
    },
  };

  /* ------------------------------------------------------------------------
   * CCTV player
   *
   * Browsers cannot play RTSP. In production a media server (e.g. MediaMTX / go2rtc)
   * pulls RTSP from the camera and serves HLS or WebRTC (WHEP); see docs/cctv-architecture.md.
   * The page never contains a stream URL: every mount asks the API ("camera_stream"), which
   * re-checks the user's permission for that camera and returns { type, url, token }.
   *
   * Drivers share one interface:  mount(el, camera, stream) → { destroy(), setMuted(bool) }
   *   mock   — animated demo camera (no network, used while stream_url is empty)
   *   hls    — native HLS (Safari) or hls.js, lazy-loaded only when a real .m3u8 is configured
   *   webrtc — WHEP: POST an SDP offer to the media server, play the answer
   * --------------------------------------------------------------------- */
  const CCTV = (() => {
    const players = new Map();
    const HLS_JS = 'https://cdn.jsdelivr.net/npm/hls.js@1/dist/hls.min.js';
    let hlsLoading = null;

    const SCENES = {
      classroom: `<rect width="320" height="180" fill="#FFF4CC"/><rect y="128" width="320" height="52" fill="#F1D9B5"/>
        <rect x="24" y="22" width="92" height="62" rx="6" fill="#BFE7FF" stroke="#fff" stroke-width="5"/>
        <g><ellipse cx="60" cy="44" rx="16" ry="7" fill="#fff"/><animateTransform attributeName="transform" type="translate" values="-20 0; 40 0; -20 0" dur="18s" repeatCount="indefinite"/></g>
        <rect x="140" y="26" width="120" height="58" rx="6" fill="#4C7A63"/><text x="152" y="62" font-size="20" fill="#fff" font-family="sans-serif">A B C 1 2 3</text>
        <circle cx="290" cy="40" r="14" fill="#fff" stroke="#FFB547" stroke-width="3"/><line x1="290" y1="40" x2="290" y2="31" stroke="#2F3A56" stroke-width="2"><animateTransform attributeName="transform" type="rotate" values="0 290 40; 360 290 40" dur="60s" repeatCount="indefinite"/></line>
        <rect x="40" y="112" width="80" height="12" rx="4" fill="#FF9F7A"/><rect x="48" y="124" width="6" height="22" fill="#C97A5A"/><rect x="106" y="124" width="6" height="22" fill="#C97A5A"/>
        <rect x="190" y="112" width="80" height="12" rx="4" fill="#7CC6F2"/><rect x="198" y="124" width="6" height="22" fill="#5A9CC4"/><rect x="256" y="124" width="6" height="22" fill="#5A9CC4"/>
        <rect x="140" y="140" width="14" height="14" fill="#FF9EC0"/><rect x="156" y="140" width="14" height="14" fill="#FFC94D"/><rect x="148" y="126" width="14" height="14" fill="#8FD9A8"/>
        <circle cx="60" cy="164" r="8" fill="#FF7AA8"><animate attributeName="cx" values="40;280;40" dur="9s" repeatCount="indefinite"/></circle>`,
      playground: `<defs><linearGradient id="sky" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#9FD6FF"/><stop offset="1" stop-color="#DFF4FF"/></linearGradient></defs>
        <rect width="320" height="180" fill="url(#sky)"/><circle cx="270" cy="34" r="16" fill="#FFD66B"/>
        <g fill="#fff"><ellipse cx="70" cy="34" rx="22" ry="9"/><animateTransform attributeName="transform" type="translate" values="0 0; 60 0; 0 0" dur="24s" repeatCount="indefinite"/></g>
        <rect y="118" width="320" height="62" fill="#8FD9A8"/><rect x="214" y="146" width="80" height="22" rx="6" fill="#FFE1A8"/>
        <path d="M30 70 h40 v50 h-6 v-44 h-28 v44 h-6z" fill="#FF9F7A"/><path d="M64 76 L120 124 h-10 L58 82z" fill="#FFC94D"/>
        <g><line x1="150" y1="52" x2="150" y2="120" stroke="#7A5600" stroke-width="5"/><line x1="230" y1="52" x2="230" y2="120" stroke="#7A5600" stroke-width="5"/><line x1="146" y1="52" x2="234" y2="52" stroke="#7A5600" stroke-width="6"/></g>
        <g><line x1="190" y1="54" x2="182" y2="104" stroke="#555" stroke-width="2"/><line x1="190" y1="54" x2="198" y2="104" stroke="#555" stroke-width="2"/><rect x="178" y="104" width="24" height="6" rx="2" fill="#B79CFF"/>
          <animateTransform attributeName="transform" type="rotate" values="-14 190 54; 14 190 54; -14 190 54" dur="3s" repeatCount="indefinite"/></g>
        <circle cx="290" cy="110" r="22" fill="#6CC08A"/><rect x="286" y="118" width="8" height="24" fill="#8A5A3B"/>`,
      gate: `<rect width="320" height="180" fill="#DFF4FF"/><g fill="#fff"><ellipse cx="240" cy="30" rx="24" ry="9"/><animateTransform attributeName="transform" type="translate" values="0 0; -70 0; 0 0" dur="26s" repeatCount="indefinite"/></g>
        <rect x="70" y="40" width="180" height="78" fill="#FFEAF2"/><path d="M60 44 L160 10 L260 44z" fill="#FF9EC0"/>
        <rect x="140" y="80" width="40" height="38" fill="#B79CFF"/><rect x="88" y="58" width="30" height="22" fill="#BFE7FF"/><rect x="202" y="58" width="30" height="22" fill="#BFE7FF"/>
        <line x1="160" y1="10" x2="160" y2="-6" stroke="#555" stroke-width="2"/><path d="M160 -6 h20 v10 h-20z" fill="#FF7AA8"><animateTransform attributeName="transform" type="scale" values="1 1; 0.8 1; 1 1" dur="1.6s" repeatCount="indefinite"/></path>
        <rect y="118" width="320" height="62" fill="#C9E7B8"/><path d="M130 180 L150 118 h20 L190 180z" fill="#EEE3CF"/>
        <g stroke="#7A8299" stroke-width="4"><line x1="40" y1="96" x2="40" y2="150"/><line x1="280" y1="96" x2="280" y2="150"/></g>
        <g stroke="#9AA3BA" stroke-width="3"><line x1="44" y1="104" x2="128" y2="104"/><line x1="44" y1="126" x2="128" y2="126"/><line x1="192" y1="104" x2="276" y2="104"/><line x1="192" y1="126" x2="276" y2="126"/></g>
        <g><rect x="-40" y="150" width="42" height="16" rx="5" fill="#FFC94D"/><circle cx="-30" cy="168" r="5" fill="#2F3A56"/><circle cx="-8" cy="168" r="5" fill="#2F3A56"/>
          <animateTransform attributeName="transform" type="translate" values="0 0; 380 0" dur="11s" repeatCount="indefinite"/></g>`,
    };

    const esc = (v) => String(v ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const pad = (n) => String(n).padStart(2, '0');
    const stamp = () => { const d = new Date(); return `${pad(d.getDate())}-${pad(d.getMonth() + 1)}-${d.getFullYear()} ${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}`; };

    function signal(el, icon, title, detail = '') {
      el.innerHTML = `<span class="cctv-signal"><span aria-hidden="true">${icon}</span>${esc(title)}${detail ? `<small>${esc(detail)}</small>` : ''}</span>`;
    }

    function osd(cam) {
      return `<span class="cctv-osd cctv-osd--top"><span><span class="osd-live">LIVE</span> ${esc(cam.code)}</span><span data-cctv-time>${stamp()}</span></span>
        <span class="cctv-osd cctv-osd--bottom"><span>${esc(cam.name)} · ${esc(cam.location)}</span><span>${esc(cam.school)}</span></span>`;
    }

    function videoShell(el, cam) {
      el.innerHTML = `<video playsinline autoplay muted></video>${osd(cam)}`;
      return el.querySelector('video');
    }

    function loadHlsJs() {
      if (window.Hls) return Promise.resolve();
      hlsLoading = hlsLoading || new Promise((resolve, reject) => {
        const script = document.createElement('script');
        script.src = HLS_JS;
        script.onload = resolve;
        script.onerror = () => reject(new UserError('โหลดตัวเล่น HLS ไม่สำเร็จ'));
        document.head.appendChild(script);
      });
      return hlsLoading;
    }

    const Drivers = {
      mock: {
        mount(el, cam) {
          el.innerHTML = `<span class="cctv-mock"><svg viewBox="0 0 320 180" preserveAspectRatio="xMidYMid slice" aria-hidden="true">${SCENES[cam.scene] || SCENES.classroom}</svg><span class="cctv-noise"></span></span>${osd(cam)}`;
          return { destroy() { el.innerHTML = ''; }, setMuted() {}, hasAudio: false };
        },
      },
      hls: {
        async mount(el, cam, stream) {
          const video = videoShell(el, cam);
          let hls = null;
          if (video.canPlayType('application/vnd.apple.mpegurl')) {
            video.src = stream.url; // Safari / iOS play HLS natively
          } else {
            await loadHlsJs();
            if (!window.Hls || !window.Hls.isSupported()) throw new UserError('เบราว์เซอร์นี้ไม่รองรับ HLS');
            hls = new window.Hls({ lowLatencyMode: true, liveSyncDurationCount: 2 });
            hls.on(window.Hls.Events.ERROR, (_e, data) => { if (data.fatal) { Log.error('CCTV.hls', data.type, { camera: cam.code }); signal(el, '📡', 'การเชื่อมต่อสัญญาณขัดข้อง', 'กรุณาลองใหม่อีกครั้ง'); } });
            hls.loadSource(stream.url);
            hls.attachMedia(video);
          }
          video.play().catch(() => {});
          return { destroy() { if (hls) hls.destroy(); video.removeAttribute('src'); video.load(); el.innerHTML = ''; }, setMuted(m) { video.muted = m; }, hasAudio: true };
        },
      },
      webrtc: {
        async mount(el, cam, stream) {
          const video = videoShell(el, cam);
          const pc = new RTCPeerConnection();
          pc.addTransceiver('video', { direction: 'recvonly' });
          pc.addTransceiver('audio', { direction: 'recvonly' });
          pc.ontrack = (event) => { video.srcObject = event.streams[0]; };
          const offer = await pc.createOffer();
          await pc.setLocalDescription(offer);
          const res = await fetch(stream.url, { method: 'POST', headers: { 'Content-Type': 'application/sdp', Authorization: `Bearer ${stream.token}` }, body: offer.sdp });
          if (!res.ok) { pc.close(); throw new UserError('Media Server ปฏิเสธการเชื่อมต่อ'); }
          await pc.setRemoteDescription({ type: 'answer', sdp: await res.text() });
          return { destroy() { pc.close(); video.srcObject = null; el.innerHTML = ''; }, setMuted(m) { video.muted = m; }, hasAudio: true };
        },
      },
    };

    function destroy(el) {
      const player = players.get(el);
      if (player) { player.destroy(); players.delete(el); }
    }

    /** Mount a camera into a screen element after a permission-checked stream request. */
    async function mount(el, cam) {
      destroy(el);
      if (!cam.active) return signal(el, '⏸️', 'กล้องปิดใช้งาน', `${cam.code} · ${cam.name}`);
      if (cam.status === 'offline') return signal(el, '📡', 'ไม่มีสัญญาณ (Offline)', `${cam.code} · ${cam.name}`);
      if (cam.status === 'maintenance') return signal(el, '🛠️', 'อยู่ระหว่างบำรุงรักษา', `${cam.code} · ${cam.name}`);
      signal(el, '⏳', 'กำลังเชื่อมต่อ...');
      el.querySelector('.cctv-signal').classList.add('cctv-signal--loading');
      try {
        const data = await api('camera_stream', { camera_id: cam.id }, { quiet: true });
        if (!data.stream) return signal(el, '📡', 'ไม่มีสัญญาณ', cam.code);
        const driver = Drivers[data.stream.type] || Drivers.mock;
        players.set(el, await driver.mount(el, data.camera, data.stream));
        Log.info('CCTV.mount', data.stream.type, { camera: cam.code });
        return players.get(el);
      } catch (error) {
        Log.error('CCTV.mount', error, { camera: cam.code });
        signal(el, '🔒', error instanceof UserError ? error.message : 'ไม่สามารถเปิดกล้องได้', 'กรุณาลองใหม่อีกครั้ง');
        return null;
      }
    }

    /** Lazily mount grid players when they scroll into view; clean up removed ones. */
    const observer = 'IntersectionObserver' in window ? new IntersectionObserver((entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) { observer.unobserve(entry.target); mount(entry.target, JSON.parse(entry.target.dataset.camera)); }
      });
    }, { rootMargin: '120px' }) : null;

    function scan() {
      players.forEach((_p, el) => { if (!el.isConnected) destroy(el); });
      $$('[data-cctv-player]').forEach((el) => {
        if (players.has(el) || el.dataset.observed) return;
        el.dataset.observed = '1';
        if (observer) observer.observe(el); else mount(el, JSON.parse(el.dataset.camera));
      });
    }

    setInterval(() => { const t = stamp(); $$('[data-cctv-time]').forEach((n) => { n.textContent = t; }); }, 1000);
    return { mount, destroy, scan, get: (el) => players.get(el) };
  })();

  /* ---------- CCTV viewer (modal + Fullscreen API) ---------- */
  const viewer = { cam: null, muted: true, lastFocus: null };

  async function openViewer(button) {
    const cam = JSON.parse(button.dataset.camera);
    const box = $('#cctv-viewer');
    if (!box) return;
    Log.start('openViewer', { camera: cam.code });
    viewer.cam = cam;
    viewer.lastFocus = document.activeElement;
    $$('[data-cctv-field]', box).forEach((f) => { f.textContent = cam[f.dataset.cctvField] ?? '-'; });
    const live = $('[data-cctv-live]', box);
    const online = cam.active && cam.status === 'online';
    live.textContent = online ? 'LIVE' : (cam.active ? cam.statusLabel : 'ปิดใช้งาน');
    live.classList.toggle('is-off', !online);
    viewer.muted = true;
    const mute = $('[data-action="cctv-mute"]', box);
    mute.textContent = '🔇';
    mute.setAttribute('aria-pressed', 'true');
    box.hidden = false;
    document.body.classList.add('has-sheet');
    $('.cctv-viewer__panel', box).focus({ preventScroll: true });
    await CCTV.mount($('[data-cctv-viewer-screen]', box), cam);
  }

  function closeViewer() {
    const box = $('#cctv-viewer');
    if (!box || box.hidden) return;
    if (document.fullscreenElement) document.exitFullscreen().catch(() => {});
    CCTV.destroy($('[data-cctv-viewer-screen]', box));
    box.hidden = true;
    viewer.cam = null;
    if (!$('.sheet.is-open')) document.body.classList.remove('has-sheet');
    if (viewer.lastFocus && document.contains(viewer.lastFocus)) viewer.lastFocus.focus({ preventScroll: true });
  }

  function toggleFullscreen() {
    const panel = $('[data-cctv-fullscreen]');
    if (!panel) return;
    if (document.fullscreenElement || document.webkitFullscreenElement) {
      (document.exitFullscreen || document.webkitExitFullscreen).call(document);
      return;
    }
    const request = panel.requestFullscreen || panel.webkitRequestFullscreen;
    if (request) {
      const result = request.call(panel); // Promise in modern browsers, undefined in old WebKit
      if (result && result.catch) result.catch(() => toast('เบราว์เซอร์นี้ไม่อนุญาตโหมดเต็มจอ', 'info'));
    }
    else toast('เบราว์เซอร์นี้ไม่รองรับโหมดเต็มจอ', 'info');
  }

  function toggleMute(button) {
    const player = CCTV.get($('[data-cctv-viewer-screen]'));
    viewer.muted = !viewer.muted;
    if (player) player.setMuted(viewer.muted);
    button.textContent = viewer.muted ? '🔇' : '🔊';
    button.setAttribute('aria-pressed', viewer.muted ? 'true' : 'false');
    if (player && !player.hasAudio) toast('กล้อง Demo ไม่มีสัญญาณเสียง', 'info');
  }

  /* ------------------------------------------------------------------------
   * Images (portfolio / activity / food)
   *
   * Phones produce multi-MB photos, so images are downscaled in the browser first
   * (longest side ≤ 1600px, JPEG) — the server validates and re-encodes again anyway.
   * --------------------------------------------------------------------- */
  const IMAGE_MAX_SIDE = 1600;
  const IMAGE_MAX_FILES = 6;
  const IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

  async function resizeImage(file) {
    try {
      const bitmap = 'createImageBitmap' in window ? await createImageBitmap(file, { imageOrientation: 'from-image' }) : null;
      if (!bitmap) return file;
      const scale = Math.min(1, IMAGE_MAX_SIDE / Math.max(bitmap.width, bitmap.height));
      const canvas = document.createElement('canvas');
      canvas.width = Math.round(bitmap.width * scale);
      canvas.height = Math.round(bitmap.height * scale);
      const ctx = canvas.getContext('2d');
      ctx.fillStyle = '#fff'; // transparent PNG → white background in JPEG
      ctx.fillRect(0, 0, canvas.width, canvas.height);
      ctx.drawImage(bitmap, 0, 0, canvas.width, canvas.height);
      const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', 0.85));
      return blob && blob.size < file.size ? new File([blob], file.name.replace(/\.[^.]+$/, '') + '.jpg', { type: 'image/jpeg' }) : file;
    } catch (error) {
      Log.error('resizeImage', error, { name: file.name });
      return file; // the server still validates and optimises
    }
  }

  /** Validate type/count on the client (friendly messages), then downscale. false = invalid. */
  async function prepareImages(fileList, errorEl) {
    const files = Array.from(fileList);
    const fail = (msg) => { if (errorEl) { errorEl.textContent = msg; errorEl.hidden = false; } else toast(msg, 'error'); return false; };
    if (files.length > IMAGE_MAX_FILES) return fail(`เลือกได้ไม่เกิน ${IMAGE_MAX_FILES} รูปต่อครั้ง`);
    if (files.some((f) => !IMAGE_TYPES.includes(f.type))) return fail('รองรับเฉพาะไฟล์รูปภาพ JPG, PNG หรือ WebP');
    Log.start('prepareImages', { count: files.length, bytes: files.reduce((n, f) => n + f.size, 0) });
    const out = await Promise.all(files.map(resizeImage));
    Log.end('prepareImages', { bytes: out.reduce((n, f) => n + f.size, 0) });
    return out;
  }

  function previewImages(input) {
    const box = input.closest('form').querySelector('[data-upload-preview]');
    if (!box) return;
    box.querySelectorAll('img').forEach((img) => URL.revokeObjectURL(img.src));
    box.innerHTML = '';
    Array.from(input.files).slice(0, IMAGE_MAX_FILES).forEach((f) => {
      if (!IMAGE_TYPES.includes(f.type)) return;
      const img = document.createElement('img');
      img.src = URL.createObjectURL(f);
      img.alt = f.name;
      box.appendChild(img);
    });
    if (input.files.length) {
      const note = document.createElement('small');
      note.textContent = `เลือกแล้ว ${input.files.length} รูป`;
      box.appendChild(note);
    }
  }

  /**
   * Image picker: "📸 ถ่ายภาพ" (camera) + "🖼️ เลือกรูป" inputs feed one list per picker,
   * so a teacher can shoot several photos one by one. Files stay in memory until the form is sent.
   */
  const Picker = {
    lists: new WeakMap(),

    files(form) {
      const picker = form && $('[data-image-picker]', form);
      return picker ? (this.lists.get(picker) || []) : [];
    },

    add(input) {
      const picker = input.closest('[data-image-picker]');
      const list = this.lists.get(picker) || [];
      const incoming = Array.from(input.files);
      input.value = ''; // the same camera input can be used again
      const bad = incoming.filter((f) => !IMAGE_TYPES.includes(f.type));
      if (bad.length) toast('รองรับเฉพาะไฟล์รูปภาพ JPG, PNG หรือ WebP', 'error');
      const merged = list.concat(incoming.filter((f) => IMAGE_TYPES.includes(f.type)));
      if (merged.length > IMAGE_MAX_FILES) toast(`แนบได้ไม่เกิน ${IMAGE_MAX_FILES} รูปต่อครั้ง`, 'error');
      this.lists.set(picker, merged.slice(0, IMAGE_MAX_FILES));
      Log.info('Picker.add', 'files', { added: incoming.length, total: this.lists.get(picker).length });
      this.render(picker);
    },

    remove(button) {
      const picker = button.closest('[data-image-picker]');
      const list = (this.lists.get(picker) || []).filter((_, i) => i !== Number(button.dataset.index));
      this.lists.set(picker, list);
      this.render(picker);
    },

    reset(form) {
      const picker = form && $('[data-image-picker]', form);
      if (!picker) return;
      this.lists.set(picker, []);
      this.render(picker);
    },

    render(picker) {
      const box = $('[data-upload-preview]', picker);
      box.querySelectorAll('img').forEach((img) => URL.revokeObjectURL(img.src));
      box.innerHTML = '';
      const list = this.lists.get(picker) || [];
      list.forEach((f, i) => {
        const tile = document.createElement('span');
        tile.className = 'upload-preview__item';
        const img = document.createElement('img');
        img.src = URL.createObjectURL(f);
        img.alt = `รูปที่ ${i + 1}`;
        const del = document.createElement('button');
        del.type = 'button';
        del.className = 'upload-preview__remove';
        del.dataset.action = 'image-remove';
        del.dataset.index = String(i);
        del.setAttribute('aria-label', `เอารูปที่ ${i + 1} ออก`);
        del.textContent = '×';
        tile.append(img, del);
        box.appendChild(tile);
      });
      if (list.length) {
        const note = document.createElement('small');
        note.textContent = `แนบแล้ว ${list.length} รูป`;
        box.appendChild(note);
      }
    },
  };

  /**
   * Individual student status (teacher home): tap a child → type a status + note → saved as a new
   * studentStatuses row (generic "save" API, scope enforced on the server).
   */
  const StudentStatus = {
    open(button) {
      const sheet = openSheet('sheet-student-status');
      if (!sheet) return;
      const form = $('[data-student-status-form]', sheet);
      form.reset();
      form.elements.student_id.value = button.dataset.student;
      form.elements.status.value = button.dataset.status || '';
      form.elements.note.value = button.dataset.note || '';
      $('.sheet__title', sheet).textContent = `สถานะของ${button.dataset.name}`;
      const current = $('[data-student-status-current]', form);
      current.hidden = !button.dataset.status;
      current.textContent = button.dataset.status ? `สถานะล่าสุดวันนี้: ${button.dataset.status} (${button.dataset.since} น.) — บันทึกใหม่จะแทนที่การแสดงผล ประวัติยังเก็บไว้` : '';
      $('[data-form-error]', form).hidden = true;
      setTimeout(() => form.elements.status.focus(), 80);
      Log.info('StudentStatus.open', 'student', { id: button.dataset.student });
    },

    async save(form) {
      const status = form.elements.status.value.trim();
      const note = form.elements.note.value.trim();
      const error = $('[data-form-error]', form);
      if (!status) { error.textContent = 'กรุณาพิมพ์สถานะ'; error.hidden = false; form.elements.status.focus(); return; }
      const sheet = form.closest('.sheet');
      await runAction($('[type="submit"]', form), 'save',
        { table: 'studentStatuses', id: 0, data: { student_id: form.elements.student_id.value, status, note } }, () => closeSheet(sheet));
    },
  };

  function openUpload(button) {
    const sheet = openSheet('sheet-upload');
    if (!sheet) return;
    const form = $('[data-upload-form]', sheet);
    form.reset();
    form.elements.owner_type.value = button.dataset.ownerType;
    form.elements.owner_id.value = button.dataset.ownerId;
    $('[data-upload-title]', form).textContent = button.dataset.title || '';
    Picker.reset(form);
    $('[data-form-error]', form).hidden = true;
  }

  async function submitUpload(form) {
    const picked = Picker.files(form);
    const errorEl = $('[data-form-error]', form);
    if (!picked.length) { errorEl.textContent = 'กรุณาถ่ายภาพหรือเลือกรูปภาพอย่างน้อย 1 รูป'; errorEl.hidden = false; return; }
    const files = await prepareImages(picked, errorEl);
    if (!files) return;
    errorEl.hidden = true;
    await runAction(form.querySelector('[type="submit"]'), 'upload_images',
      { owner_type: form.elements.owner_type.value, owner_id: form.elements.owner_id.value }, () => closeSheet(form.closest('.sheet')), files);
  }

  function openMedia(button) {
    const sheet = openSheet('sheet-media');
    if (!sheet) return;
    const img = $('[data-media-img]', sheet);
    img.src = button.dataset.src;
    img.alt = button.dataset.caption || '';
    $('[data-media-caption]', sheet).textContent = button.dataset.caption || '';
  }

  async function cleanupMedia(button) {
    const ok = await confirmDialog({
      title: 'ล้างไฟล์ภาพที่หมดอายุ?',
      message: `พบไฟล์ภาพที่หมดอายุ ${Number(button.dataset.files).toLocaleString()} ไฟล์ รวม ${button.dataset.size} ต้องการลบไฟล์เหล่านี้หรือไม่? (ข้อมูลรายการจะยังอยู่ครบ)`,
      icon: '🗑️',
      okLabel: 'ลบไฟล์',
    });
    if (ok) await runAction(button, 'cleanup_media', {});
  }

  /* ------------------------------------------------------------------------
   * Event wiring
   * --------------------------------------------------------------------- */
  const ACTIONS = {
    'open-sheet': (b) => openSheet(b.dataset.sheet),
    'close-sheet': (b) => closeSheet(b.closest('.sheet')),
    'open-form': openForm,
    delete: deleteRecord,
    'quick-save': quickSave,
    'set-status': setStatus,
    'edit-meal': (b) => toggleMealEdit(b, true),
    'cancel-meal': (b) => toggleMealEdit(b, false),
    'open-thread': (b) => openThread(b.dataset.thread),
    'close-thread': () => { const l = $('[data-chat-layout]'); if (l) l.classList.remove('is-thread-open'); activeThread = null; },
    'fill-account': fillAccount,
    print: () => window.print(),
    'open-camera': (b) => { const s = openSheet('sheet-camera'); if (s) $('.sheet__title', s).textContent = b.dataset.name; },
    'upload-open': openUpload,
    'image-remove': (b) => Picker.remove(b),
    'student-status-open': (b) => StudentStatus.open(b),
    'media-open': openMedia,
    'cleanup-media': cleanupMedia,
    'pickup-go': (b) => Pickup.go(b),
    'notification-open': (b) => Notifications.open(b),
    'notification-read-all': (b) => Notifications.readAll(b),
    'pickup-update': (b) => Pickup.update(b),
    'cctv-open': openViewer,
    'cctv-close': closeViewer,
    'cctv-fullscreen': toggleFullscreen,
    'cctv-mute': toggleMute,
    'reset-demo': async (b) => {
      const ok = await confirmDialog({ title: 'รีเซ็ตข้อมูล Demo?', message: 'ข้อมูลในขอบเขตของบัญชีนี้จะกลับเป็นค่าเริ่มต้น', icon: '🔄', okLabel: 'รีเซ็ต' });
      if (ok && await runAction(b, 'reset_demo')) setTimeout(() => window.location.reload(), 800);
    },
  };

  document.addEventListener('click', (event) => {
    const chip = event.target.closest('[data-filter-chip]');
    if (chip) {
      const target = chip.closest('[data-filter-bar]').dataset.filterBar;
      filterState[target] = { ...(filterState[target] || { q: '' }), chip: chip.dataset.filterChip };
      applyFilter(target);
      return;
    }
    const tab = event.target.closest('[data-tabs] [data-tab]');
    if (tab) { switchTab(tab.closest('[data-tabs]').dataset.tabs, tab.dataset.tab); return; }
    const school = event.target.closest('[data-school-pick]');
    if (school) { pickSchool(school.dataset.schoolPick); return; }

    const button = event.target.closest('[data-action]');
    if (!button || button.disabled) return;
    const handler = ACTIONS[button.dataset.action];
    if (!handler) return;
    event.preventDefault();
    Log.info('click', button.dataset.action, { ...button.dataset });
    handler(button);
  });

  document.addEventListener('input', (event) => {
    const statusForm = event.target.closest('[data-student-status-form]');
    if (statusForm) { $('[data-form-error]', statusForm).hidden = true; return; }
    const search = event.target.closest('[data-filter-search]');
    if (!search) return;
    const target = search.closest('[data-filter-bar]').dataset.filterBar;
    filterState[target] = { ...(filterState[target] || { chip: '' }), q: search.value.trim().toLowerCase() };
    applyFilter(target);
  });

  document.addEventListener('change', (event) => {
    if (event.target.matches('[data-image-input]')) { previewImages(event.target); return; }
    if (event.target.matches('[data-image-source]')) { Picker.add(event.target); return; }
    const select = event.target.closest('[data-intake-id]');
    if (!select) return;
    runAction(select, 'save_intake', { id: Number(select.dataset.intakeId), meal: select.dataset.meal, level: select.value });
  });

  document.addEventListener('submit', (event) => {
    const form = event.target;
    if (form.matches('[data-form-table]')) { event.preventDefault(); submitForm(form); }
    else if (form.matches('[data-meal-form]')) { event.preventDefault(); saveMeal(form); }
    else if (form.matches('[data-chat-form]')) { event.preventDefault(); sendMessage(form); }
    else if (form.matches('[data-upload-form]')) { event.preventDefault(); submitUpload(form); }
    else if (form.matches('[data-student-status-form]')) { event.preventDefault(); StudentStatus.save(form); }
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && $('#cctv-viewer:not([hidden])') && !document.fullscreenElement) { closeViewer(); return; }
    if (event.key === 'Escape' && $('.sheet.is-open')) closeSheet();
  });

  document.addEventListener('DOMContentLoaded', () => {
    Log.start('init', { page: CFG.page, role: CFG.role, version: CFG.version });
    initLogin();
    initClocks();
    initChartTips();
    initPolling();
    Pickup.init();
    $$('[data-filter-bar]').forEach((bar) => {
      if (bar.dataset.initialChip) filterState[bar.dataset.filterBar] = { q: '', chip: bar.dataset.initialChip };
      applyFilter(bar.dataset.filterBar);
    });
    scrollChats();
    CCTV.scan();
    if (window.matchMedia('(min-width: 900px)').matches) {
      const first = $('[data-chat-layout] [data-thread]');
      if (first) openThread(first.dataset.thread);
    }
    if (CFG.toast) toast(CFG.toast.message || CFG.toast, CFG.toast.type || 'success');
    Log.end('init');
  });
})();
