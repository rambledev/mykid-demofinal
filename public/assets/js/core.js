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
   * Thai date / time — always Asia/Bangkok, Buddhist year, 24 h (whatever the device settings)
   * --------------------------------------------------------------------- */
  const THAI_FMT = new Intl.DateTimeFormat('th-TH-u-ca-buddhist-nu-latn', {
    timeZone: 'Asia/Bangkok', day: '2-digit', month: '2-digit', year: 'numeric',
    hour: '2-digit', minute: '2-digit', second: '2-digit', hourCycle: 'h23',
  });
  /** Now in Thailand: { date: "10/10/2569", hm: "14:30", hms: "14:30:05" }. */
  function thaiNow() {
    const p = Object.fromEntries(THAI_FMT.formatToParts(new Date()).map((x) => [x.type, x.value]));
    return { date: `${p.day}/${p.month}/${p.year}`, hm: `${p.hour}:${p.minute}`, hms: `${p.hour}:${p.minute}:${p.second}` };
  }

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
    if (typeof data.chatUnread === 'number') Chat.setBadge(data.chatUnread);
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
      if (el.type === 'checkbox') { // multi-select (data-multi): the record holds an array of values
        el.checked = Array.isArray(values[el.name]) && values[el.name].map(String).includes(el.value);
        return;
      }
      const value = values[el.name] === null ? '' : String(values[el.name]);
      if (el.type === 'radio') el.checked = el.value === value;
      else el.value = value;
    });
    syncShowIf(form);
    $$('[data-upload-preview]', form).forEach((box) => { box.innerHTML = ''; });
    $$('[data-secret-hint]', form).forEach((hint) => {
      hint.textContent = record && record.rtsp_configured ? '🔐 ตั้งค่าไว้แล้ว — เว้นว่างเพื่อใช้ค่าเดิม (ไม่แสดงค่าจริงเพื่อความปลอดภัย)' : 'ยังไม่ได้ตั้งค่า';
    });
    $('.sheet__title', sheet).textContent = record ? form.dataset.titleEdit : form.dataset.titleAdd;
    $('[data-form-error]', form).hidden = true;
  }

  /** Show / hide fields marked data-show-if="<field>" data-show-value="<value>" (components.php showIf). */
  function syncShowIf(form) {
    $$('[data-show-if]', form).forEach((box) => {
      const control = form.elements[box.dataset.showIf];
      box.hidden = !control || control.value !== box.dataset.showValue;
    });
  }

  async function submitForm(form) {
    const table = form.dataset.formTable;
    const id = form.elements.id.value || ''; // secure id token ('' = new row)
    const data = {};
    new FormData(form).forEach((value, key) => { if (key !== 'id' && !(value instanceof File)) data[key] = value; });
    // Multi-select fields only: always an array (empty when nothing is ticked, so the server can clear it).
    // Hidden ones (showIf) are left out. Every other field keeps the single-value behaviour above.
    $$('[data-multi]', form).forEach((box) => {
      const key = box.dataset.multi;
      if (box.hidden) { delete data[key]; return; }
      data[key] = $$(`input[name="${CSS.escape(key)}"]:checked`, box).map((el) => el.value);
    });
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
    const action = form.dataset.formAction || 'save'; // e.g. save_camera_settings (render_form_sheet options)
    const result = await runAction(form.querySelector('[type="submit"]'), action, { table, id, data }, () => closeSheet(form.closest('.sheet')), files);
    if (!result) {
      errorEl.textContent = 'ยังบันทึกไม่ได้ กรุณาตรวจสอบข้อมูลแล้วลองใหม่';
      errorEl.hidden = false;
    }
  }

  async function deleteRecord(button) {
    const { table, id, label } = button.dataset;
    const confirmed = await confirmDialog({ title: 'ลบข้อมูลนี้?', message: `“${label}” จะถูกลบออกจากระบบ`, okLabel: 'ลบ' });
    if (confirmed) await runAction(button, 'delete', { table, id });
  }

  /** Preset chip in a form (components.php field type "presets"): fill reason + points of that form. */
  function fillPreset(button) {
    const form = button.form;
    if (!form) return;
    if (form.elements.reason) form.elements.reason.value = button.dataset.reason;
    if (form.elements.points) form.elements.points.value = button.dataset.points;
    Log.info('fillPreset', { reason: button.dataset.reason, points: button.dataset.points });
  }

  function quickSave(button) {
    const { table, id, field, value } = button.dataset;
    return runAction(button, 'save', { table, id, data: { [field]: value } });
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
    runAction(form.querySelector('[type="submit"]'), 'save_meal', { meal: card.dataset.meal, classroom_id: card.dataset.classroom, items }, null, files);
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
    Chat.pollSoon();
  }

  function scrollChats() {
    $$('[data-chat-scroll]').forEach((box) => { box.scrollTop = box.scrollHeight; });
  }

  /** Admin "จัดการระบบ": seed import (preview → confirm), system reset, PIN reset. */
  const System = {
    async seed(form, submitter) {
      const mode = submitter && submitter.dataset.seedMode === 'confirm' ? 'confirm' : 'preview';
      const files = Array.from(form.elements.files.files || []);
      const errorEl = $('[data-form-error]', form);
      const report = $('[data-seed-report]', form);
      const confirmBtn = form.querySelector('[data-seed-mode="confirm"]');
      if (!files.length) { errorEl.textContent = 'กรุณาเลือกไฟล์ Seed Data (.json)'; errorEl.hidden = false; return; }
      if (mode === 'confirm') {
        const ok = await confirmDialog({ title: 'นำเข้า Seed Data?', message: 'ข้อมูลห้องเรียน ครู และนักเรียนจากไฟล์จะถูกบันทึกลงระบบ', icon: '📥', okLabel: 'นำเข้า' });
        if (!ok) return;
      }
      errorEl.hidden = true;
      setBusy(submitter, true);
      try {
        const data = await api('import_seed', { mode }, { files });
        report.innerHTML = '';
        data.report.forEach((line) => { const li = document.createElement('li'); li.textContent = line; report.appendChild(li); });
        report.hidden = false;
        confirmBtn.disabled = data.written; // after a preview the import can be confirmed
        toast(data.message);
        if (data.written) { form.reset(); await refreshLive(); }
      } catch (error) {
        errorEl.textContent = error instanceof UserError ? error.message : 'กรุณาลองใหม่อีกครั้ง';
        errorEl.hidden = false;
        confirmBtn.disabled = true;
      } finally {
        setBusy(submitter, false);
      }
    },

    async reset(form) {
      const errorEl = $('[data-form-error]', form);
      const phrase = form.elements.phrase.value.trim();
      const pin = form.elements.pin.value.trim();
      if (phrase !== 'ล้างข้อมูล') { errorEl.textContent = 'กรุณาพิมพ์คำว่า “ล้างข้อมูล” เพื่อยืนยัน'; errorEl.hidden = false; return; }
      if (!/^\d{6}$/.test(pin)) { errorEl.textContent = 'กรุณากรอกรหัส PIN 6 หลักของคุณ'; errorEl.hidden = false; return; }
      const ok = await confirmDialog({ title: 'ล้างข้อมูลทั้งหมด?', message: 'ข้อมูลทั้งหมดยกเว้นโรงเรียนและบัญชีผู้ดูแลจะถูกลบ และย้อนกลับไม่ได้', icon: '⚠️', okLabel: 'ล้างข้อมูล' });
      if (!ok) return;
      errorEl.hidden = true;
      const button = form.querySelector('[type="submit"]');
      setBusy(button, true);
      try {
        const data = await api('system_reset', { phrase, pin });
        form.reset();
        toast(data.message);
        if (data.redirect) setTimeout(() => { window.location.href = data.redirect; }, 1500);
      } catch (error) {
        errorEl.textContent = error instanceof UserError ? error.message : 'กรุณาลองใหม่อีกครั้ง';
        errorEl.hidden = false;
      } finally {
        setBusy(button, false);
      }
    },

    async resetPin(button) {
      const ok = await confirmDialog({ title: 'รีเซ็ต PIN?', message: `PIN ของ “${button.dataset.label}” จะกลับเป็นรหัสเริ่มต้น และต้องตั้ง PIN ใหม่เมื่อเข้าสู่ระบบครั้งถัดไป`, icon: '🔑', okLabel: 'รีเซ็ต PIN' });
      if (ok) await runAction(button, 'reset_pin', { user: button.dataset.user });
    },
  };

  /** Account page: change the own PIN (current + new + confirm). */
  async function changePin(form) {
    const first = 'firstSetup' in form.dataset; // first login: no current PIN to type
    const values = Object.fromEntries(['current', 'new', 'confirm'].map((k) => [k, form.elements[k] ? form.elements[k].value.trim() : '']));
    const errorEl = $('[data-form-error]', form);
    const fail = (msg, field) => { errorEl.textContent = msg; errorEl.hidden = false; if (field) form.elements[field].focus(); };
    if (!first && !/^\d{6}$/.test(values.current)) return fail('กรุณากรอกรหัส PIN ปัจจุบัน 6 หลัก', 'current');
    if (!/^\d{6}$/.test(values.new)) return fail('รหัส PIN ใหม่ต้องเป็นตัวเลข 6 หลัก', 'new');
    if (values.new !== values.confirm) return fail('รหัส PIN ใหม่ทั้งสองช่องไม่ตรงกัน', 'confirm');
    errorEl.hidden = true;
    const button = form.querySelector('[type="submit"]');
    setBusy(button, true);
    try {
      const data = await api('change_pin', values);
      if (data.csrf) CFG.csrf = data.csrf; // session id was renewed
      form.reset();
      toast(data.message);
      if (data.redirect) setTimeout(() => { window.location.href = data.redirect; }, 900);
    } catch (error) {
      fail(error instanceof UserError ? error.message : 'กรุณาลองใหม่อีกครั้ง');
    } finally {
      setBusy(button, false);
    }
    return null;
  }

  function sendMessage(form) {
    return Chat.send(form);
  }

  /* ------------------------------------------------------------------------
   * Chat — near-realtime: polls "chat_poll" every 2.5 s (also while typing), appends new bubbles,
   * marks the visible thread read, keeps unread badges + "อ่านแล้ว" in sync.
   * --------------------------------------------------------------------- */
  const CHAT_POLL_MS = 2500;
  const CHAT_POLL_HIDDEN_MS = 15000;
  const Chat = {
    timer: null,
    busy: false,

    /** The thread on screen: parent page = the only one; teacher = the open panel. */
    visible() {
      const layout = $('[data-chat-layout]');
      if (!layout) return $('[data-chat]');
      const panel = $$('[data-thread-panel]', layout).find((p) => !p.hidden);
      const panelShown = layout.classList.contains('is-thread-open') || window.matchMedia('(min-width: 900px)').matches;
      return panel && panelShown ? $('[data-chat]', panel) : null;
    },

    setBadge(count) {
      $$('[data-chat-badge]').forEach((b) => {
        b.hidden = !count;
        b.textContent = count > 99 ? '99+' : String(count);
        b.setAttribute('aria-label', `ข้อความยังไม่ได้อ่าน ${count} ข้อความ`);
      });
    },

    bubble(m) {
      const li = document.createElement('li');
      li.className = `bubble${m.mine ? ' bubble--me' : ''}`;
      li.dataset.msg = m.id;
      li.innerHTML = '<span class="bubble__sender"></span><p class="bubble__text"></p><time class="bubble__time"></time>';
      $('.bubble__sender', li).textContent = m.sender;
      $('.bubble__text', li).textContent = m.text;
      $('.bubble__time', li).textContent = m.time;
      return li;
    },

    /** Append messages not shown yet; keep the view at the bottom when the user was there. */
    append(chat, messages) {
      if (!chat || !messages.length) return 0;
      const list = $('[data-chat-scroll]', chat);
      const atBottom = list.scrollHeight - list.scrollTop - list.clientHeight < 80;
      let added = 0;
      messages.forEach((m) => {
        if ($(`[data-msg="${m.id}"]`, list)) return;
        const empty = $('[data-chat-empty]', list);
        if (empty) empty.remove();
        list.appendChild(this.bubble(m));
        chat.dataset.chatLast = m.id;
        added += 1;
        if (m.mine) $('[data-chat-seen]', chat).hidden = true; // new message of mine: not read yet
      });
      if (added && (atBottom || messages[messages.length - 1].mine)) list.scrollTop = list.scrollHeight;
      const last = messages[messages.length - 1];
      const preview = $(`[data-thread="${chat.dataset.chatThread}"] [data-thread-preview]`);
      if (preview && last) preview.textContent = last.text;
      return added;
    },

    updateThreads(threads) {
      $$('[data-thread]').forEach((t) => {
        const n = threads[t.dataset.thread] || 0;
        t.classList.toggle('has-unread', n > 0);
        const badge = $('[data-thread-unread]', t);
        if (badge) { badge.hidden = !n; badge.textContent = String(n); }
      });
    },

    async poll() {
      const chat = this.visible() || $('[data-chat]'); // list view still refreshes the unread badges
      if (!chat || this.busy) return;
      this.busy = true;
      const read = Boolean(this.visible()) && !document.hidden;
      try {
        const data = await api('chat_poll', { student_id: chat.dataset.chatThread, after: chat.dataset.chatLast || '', read }, { quiet: true });
        const added = this.append(chat, data.messages || []);
        if (added) Log.info('Chat.poll', 'new messages', { added });
        $('[data-chat-seen]', chat).hidden = !data.seen;
        this.updateThreads(data.threads || {});
      } catch (error) {
        Log.error('Chat.poll', error, {});
      } finally {
        this.busy = false;
      }
    },

    schedule() {
      clearTimeout(this.timer);
      this.timer = setTimeout(async () => { await this.poll(); this.schedule(); }, document.hidden ? CHAT_POLL_HIDDEN_MS : CHAT_POLL_MS);
    },

    pollSoon() {
      if (!$('[data-chat]')) return;
      clearTimeout(this.timer);
      this.timer = setTimeout(async () => { await this.poll(); this.schedule(); }, 50);
    },

    async send(form) {
      const input = form.elements.text;
      const text = input.value.trim();
      if (!text) return;
      const button = form.querySelector('[type="submit"]');
      setBusy(button, true);
      try {
        const data = await api('send_message', { student_id: form.dataset.student, text });
        input.value = '';
        this.append(form.closest('[data-chat]'), [data.chat]);
      } catch (error) {
        toast(error instanceof UserError ? error.message : 'ส่งข้อความไม่สำเร็จ กรุณาลองใหม่อีกครั้ง', 'error');
      } finally {
        setBusy(button, false);
        input.focus();
      }
    },

    init() {
      if (!$('[data-chat]')) return;
      Log.info('Chat.init', 'polling', { intervalMs: CHAT_POLL_MS });
      document.addEventListener('visibilitychange', () => { if (!document.hidden) this.pollSoon(); });
      this.pollSoon();
    },
  };

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
    Log.end('initLogin');
  }

  /* ------------------------------------------------------------------------
   * Clocks, chart tooltips, polling
   * --------------------------------------------------------------------- */
  function initClocks() {
    const tick = () => {
      const t = thaiNow();
      $$('[data-clock]').forEach((c) => { c.textContent = `${t.hm} น.`; });
      $$('[data-clock-seconds]').forEach((c) => { c.textContent = `${t.hms} น.`; });
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
        const data = await api('notification_read', { id: button.dataset.id });
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
      if (await runAction(button, 'pickup_create', { student_id: button.dataset.student })) await this.sync(false);
    },

    async update(button) {
      const { id, status, name } = button.dataset;
      if (status === 'completed') {
        const ok = await confirmDialog({ title: `ส่งมอบ${name}แล้ว?`, message: 'ยืนยันว่าผู้ปกครองรับนักเรียนจากจุดรับ-ส่งแล้ว', icon: '🤝', okLabel: 'ส่งมอบนักเรียนแล้ว' });
        if (!ok) return;
      }
      if (await runAction(button, 'pickup_update', { id, status })) await this.sync(false);
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
   *   mock   — "not connected" placeholder (no stream URL configured yet) — never a fake picture
   *   hls    — native HLS (Safari) or hls.js, lazy-loaded only when a real .m3u8 is configured
   *   webrtc — WHEP: POST an SDP offer to the media server, play the answer
   * --------------------------------------------------------------------- */
  const CCTV = (() => {
    const players = new Map();
    const HLS_JS = 'https://cdn.jsdelivr.net/npm/hls.js@1.5.13/dist/hls.min.js'; // pinned version (CSP allows jsDelivr)
    let hlsLoading = null;

    const esc = (v) => String(v ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const stamp = () => { const t = thaiNow(); return `${t.date} ${t.hms} น.`; }; // e.g. 10/10/2569 14:30:05 น.

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
        mount(el) {
          signal(el, '📡', 'กล้องนี้ยังไม่ได้เชื่อมต่อสัญญาณ', 'ผู้ดูแลโรงเรียนต้องตั้งค่า Stream URL จาก Media Server ก่อน');
          return { destroy() { el.innerHTML = ''; }, setMuted() {}, hasAudio: false };
        },
      },
      hls: {
        async mount(el, cam, stream) {
          const video = videoShell(el, cam);
          let hls = null;
          // hls.js whenever Media Source Extensions exist: recent desktop Chrome answers canPlayType()
          // "maybe" for HLS, but its native player fails (DEMUXER_ERROR_COULD_NOT_PARSE → black video)
          // on MediaMTX's LL-HLS fMP4 while the playlist still has EXT-X-GAP parts (muxer just started).
          // Native HLS only where hls.js cannot run (iPhone without MSE) or its CDN script fails.
          const hasMse = Boolean(window.MediaSource || window.ManagedMediaSource);
          const canNative = Boolean(video.canPlayType('application/vnd.apple.mpegurl'));
          let useHlsJs = hasMse;
          if (useHlsJs) {
            try { await loadHlsJs(); } catch (error) { if (!canNative) throw error; Log.error('CCTV.hls', 'hls.js load failed, native fallback', { camera: cam.code }); }
            useHlsJs = Boolean(window.Hls && window.Hls.isSupported());
          }
          Log.info('CCTV.hls', useHlsJs ? 'hls.js' : 'native', { camera: cam.code, mse: hasMse, native: canNative });
          if (!useHlsJs) {
            if (!canNative) throw new UserError('เบราว์เซอร์นี้ไม่รองรับ HLS');
            video.addEventListener('error', () => { Log.error('CCTV.hls', 'native playback error', { camera: cam.code, code: video.error && video.error.code }); signal(el, '📡', 'การเชื่อมต่อสัญญาณขัดข้อง', 'กรุณาลองใหม่อีกครั้ง'); }, { once: true });
            video.src = stream.url; // Safari / iOS without MSE play HLS natively
          } else {
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
        // streaming switched off / not configured on the server (fail closed): no URL was handed out
        if (data.stream.type === 'unavailable') return signal(el, '🔒', data.stream.notice || 'ระบบกล้องยังไม่พร้อมใช้งาน', cam.code);
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
    if (player && !player.hasAudio) toast('กล้องนี้ไม่มีสัญญาณเสียง', 'info');
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
        { table: 'studentStatuses', id: '', data: { student_id: form.elements.student_id.value, status, note } }, () => closeSheet(sheet));
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
      message: `พบไฟล์ภาพที่หมดอายุ ${Number(button.dataset.files).toLocaleString('th-TH')} ไฟล์ รวม ${button.dataset.size} ต้องการลบไฟล์เหล่านี้หรือไม่? (ข้อมูลรายการจะยังอยู่ครบ)`,
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
    'fill-preset': fillPreset,
    'set-status': setStatus,
    'edit-meal': (b) => toggleMealEdit(b, true),
    'cancel-meal': (b) => toggleMealEdit(b, false),
    'open-thread': (b) => openThread(b.dataset.thread),
    'close-thread': () => { const l = $('[data-chat-layout]'); if (l) l.classList.remove('is-thread-open'); activeThread = null; },
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
    'reset-pin': (b) => System.resetPin(b),
    'cctv-open': openViewer,
    'cctv-close': closeViewer,
    'cctv-fullscreen': toggleFullscreen,
    'cctv-mute': toggleMute,
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
    const showIfForm = event.target.form;
    if (showIfForm && showIfForm.querySelector('[data-show-if]')) syncShowIf(showIfForm);
    const select = event.target.closest('[data-intake-id]');
    if (!select) return;
    runAction(select, 'save_intake', { id: select.dataset.intakeId, meal: select.dataset.meal, level: select.value });
  });

  document.addEventListener('submit', (event) => {
    const form = event.target;
    if (form.matches('[data-form-table]')) { event.preventDefault(); submitForm(form); }
    else if (form.matches('[data-meal-form]')) { event.preventDefault(); saveMeal(form); }
    else if (form.matches('[data-chat-form]')) { event.preventDefault(); sendMessage(form); }
    else if (form.matches('[data-pin-form]')) { event.preventDefault(); changePin(form); }
    else if (form.matches('[data-seed-form]')) { event.preventDefault(); System.seed(form, event.submitter); }
    else if (form.matches('[data-reset-form]')) { event.preventDefault(); System.reset(form); }
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
    const wanted = $('[data-chat-layout]') && $('[data-chat-layout]').dataset.chatOpen;
    if (wanted) {
      openThread(wanted);
    } else if (window.matchMedia('(min-width: 900px)').matches) {
      const first = $('[data-chat-layout] [data-thread]');
      if (first) openThread(first.dataset.thread);
    }
    Chat.init();
    if (CFG.toast) toast(CFG.toast.message || CFG.toast, CFG.toast.type || 'success');
    Log.end('init');
  });
})();
