(() => {
  'use strict';
  const $ = id => document.getElementById(id);
  const delay = ms => new Promise(resolve => setTimeout(resolve, ms));
  const stats = { formRequests: 0, emailRequests: 0, couponRequests: 0, dialogRequests: 0, copyRequests: 0, comboRequests: 0 };
  window.__labStats = stats;
  function icon(name) { return window.LAB_ICONS[name] || ''; }
  function injectIcons(root = document) { root.querySelectorAll('[data-icon]').forEach(el => { el.innerHTML = icon(el.dataset.icon); }); }
  injectIcons();
  const events = [];
  function emit(name, data) { events.unshift({ event: name, data }); $('event-log').textContent = events.slice(0, 8).map(e => e.event + ' ' + JSON.stringify(e.data)).join('\n'); }
  function helper(id, message, error = false) { const el = $(id); if (el.textContent !== message) el.textContent = message; el.classList.toggle('it-validation-error', error); }
  function invalid(el, state) { el.setAttribute('aria-invalid', String(state)); }
  function busyButton(el, busy, text) { el.disabled = busy; el.setAttribute('aria-busy', String(busy)); el.classList.toggle('is-loading', busy); el.innerHTML = busy ? icon('loader-circle').replace('class="it-icon"', 'class="it-icon it-spinner"') + '<span>Загрузка…</span>' : text; }

  // Progressive validation, async validation cache and stale-response guard.
  const email = $('email'), repeat = $('repeat'), comment = $('comment');
  const touched = new Set();
  let submitted = false, saving = false, emailVersion = 0, emailCache = null, emailCheck = null;
  function formatValid(value) { const el = document.createElement('input'); el.type = 'email'; el.required = true; el.value = value; return el.validity.valid; }
  function errors() {
    const a = email.value.trim(), b = repeat.value.trim();
    const err = {};
    if (!a) err.email = 'Укажите e-mail.'; else if (!formatValid(a)) err.email = 'Введите корректный e-mail.';
    if (emailCache?.value === a && !emailCache.ok) err.email = 'Этот адрес уже используется.';
    if (!b) err.repeat = 'Повторите e-mail.'; else if (a !== b) err.repeat = 'Адреса должны совпадать.';
    if (Array.from(comment.value).length > 500) err.comment = 'Сократите комментарий до 500 символов.';
    return err;
  }
  function renderErrors() {
    const err = errors();
    for (const [id, hint] of [['email', 'Введите адрес электронной почты.'], ['repeat', 'Адреса должны совпадать.'], ['comment', 'До 500 символов.']]) {
      const show = submitted || touched.has(id);
      invalid($(id), !!(show && err[id]));
      if (id !== 'email' || !emailCheck) helper(id + '-helper', show && err[id] ? err[id] : hint, !!(show && err[id]));
    }
    const count = Array.from(comment.value).length;
    $('comment-count').textContent = count + ' / 500'; $('comment-count').classList.toggle('is-error', count > 500);
    const visibleErrors = submitted ? Object.entries(err) : [];
    const summary = $('form-summary');
    const signature = JSON.stringify(visibleErrors);
    if (summary.dataset.signature !== signature) {
      summary.dataset.signature = signature; summary.replaceChildren();
      if (visibleErrors.length) {
        const heading = document.createElement('strong'); heading.textContent = 'Исправьте ' + visibleErrors.length + ' ' + ({one:'поле',few:'поля',many:'полей',other:'поля'}[new Intl.PluralRules('ru').select(visibleErrors.length)]); summary.append(heading);
        const ul = document.createElement('ul');
        visibleErrors.forEach(([id, msg]) => { const li = document.createElement('li'), a = document.createElement('a'); a.href = '#' + id; a.textContent = msg; a.onclick = ev => { ev.preventDefault(); $(id).focus(); }; li.append(a); ul.append(li); }); summary.append(ul);
      }
    }
    summary.hidden = !visibleErrors.length;
    return err;
  }
  async function checkEmail() {
    const value = email.value.trim();
    if (!value || !formatValid(value)) return false;
    if (emailCache?.value === value) return emailCache.ok;
    if (emailCheck?.value === value) return emailCheck.promise;
    const version = ++emailVersion;
    const promise = (async () => {
      stats.emailRequests++;
      helper('email-helper', 'Проверяем значение…');
      email.setAttribute('aria-busy', 'true');
      await delay(value.toLowerCase() === 'taken@example.com' ? 650 : 250);
      if (version !== emailVersion || email.value.trim() !== value) return false;
      const ok = value.toLowerCase() !== 'taken@example.com';
      emailCache = { value, ok }; emailCheck = null;
      email.setAttribute('aria-busy', 'false');
      renderErrors(); if (ok) helper('email-helper', 'Адрес доступен. Проверка демонстрационная.');
      emit('onValidate', { field: 'email', value, valid: ok });
      return ok;
    })();
    emailCheck = { value, promise }; return promise;
  }
  for (const input of [email, repeat, comment]) {
    input.addEventListener('input', () => {
      if (input === email) { emailVersion++; emailCache = null; emailCheck = null; email.setAttribute('aria-busy', 'false'); }
      if (submitted || touched.has(input.id) || input === comment) renderErrors();
      helper('form-status', 'Изменения не сохранены.');
    });
    input.addEventListener('blur', () => { touched.add(input.id); renderErrors(); if (input === email && !errors().email) void checkEmail(); });
  }
  $('demo-form').addEventListener('submit', async ev => {
    ev.preventDefault(); if (saving) return;
    submitted = true;
    let err = renderErrors(); if (Object.keys(err).length) { $(Object.keys(err)[0]).focus(); emit('onSubmitInvalid', { fields: Object.keys(err) }); return; }
    const submitTrigger = document.activeElement; saving = true; busyButton($('form-save'), true, 'Сохранить'); [email, repeat, comment].forEach(e => { e.readOnly = true; });
    const payload = { email: email.value.trim(), comment: comment.value };
    const fail = $('form-fail').checked;
    try {
      const available = await checkEmail();
      if (!available) { err = renderErrors(); email.focus(); return; }
      helper('form-status', 'Сохраняем…'); stats.formRequests++; emit('onSubmit', payload);
      await delay(700);
      if (fail) { helper('form-status', 'Не удалось сохранить. Введённые данные сохранены.', true); emit('onSubmitError', { code: 'simulated_service_error' }); }
      else { helper('form-status', 'Данные сохранены.'); emit('onSubmitSuccess', payload); }
    } finally { saving = false; busyButton($('form-save'), false, 'Сохранить'); [email, repeat, comment].forEach(e => { e.readOnly = false; }); if(submitTrigger===$('form-save') && document.activeElement===document.body) $('form-save').focus(); }
  });

  // Strict combobox: query is draft, only Enter/click commits a value.
  const combo = $('combo'), list = $('combo-options');
  const options = ['Windows 11', 'Windows 10', 'Windows Server', 'Microsoft Office', 'Microsoft Visio', 'Microsoft Project'].map((label, i) => ({ id: 'line-' + i, label }));
  let comboSeq = 0, comboTimer, matches = [], active = -1, committed = null, comboLoading = false;
  function closeCombo(restore = false) { comboLoading = false; combo.setAttribute('aria-busy', 'false'); list.hidden = true; combo.setAttribute('aria-expanded', 'false'); combo.removeAttribute('aria-activedescendant'); active = -1; if (restore) combo.value = committed?.label || ''; }
  function drawOptions() {
    list.replaceChildren();
    if (!matches.length) { const el = document.createElement('div'); el.className = 'it-listbox-status'; el.textContent = 'Нет подходящих вариантов.'; list.append(el); combo.removeAttribute('aria-activedescendant'); return; }
    matches.forEach((option, i) => {
      const el = document.createElement('div'); el.id = 'option-' + option.id; el.setAttribute('role','option'); el.className = 'it-option'; el.textContent = option.label; el.setAttribute('aria-selected', String(i === active));
      el.onpointerdown = ev => { ev.preventDefault(); commit(i); }; list.append(el);
    });
    if (active >= 0) { combo.setAttribute('aria-activedescendant', 'option-' + matches[active].id); $('option-' + matches[active].id).scrollIntoView({ block: 'nearest' }); }
    else combo.removeAttribute('aria-activedescendant');
  }
  function commit(i) { if (!matches[i]) return; comboSeq++; clearTimeout(comboTimer); committed = matches[i]; combo.value = committed.label; closeCombo(); helper('combo-helper', 'Выбрано: ' + committed.label); emit('onValueChange', { component: 'combobox', value: committed.id }); combo.focus(); }
  async function loadOptions(query, sequence) {
    stats.comboRequests++; matches = []; active = -1; comboLoading = true; combo.setAttribute('aria-busy','true'); list.hidden = false; combo.setAttribute('aria-expanded', 'true'); list.innerHTML = '<div class="it-listbox-status">Загружаем варианты…</div>'; helper('combo-helper', 'Загрузка вариантов…');
    await delay(query.toLowerCase() === 'win' ? 600 : 120);
    if (sequence !== comboSeq || document.activeElement !== combo) return;
    comboLoading = false; combo.setAttribute('aria-busy','false'); matches = options.filter(o => o.label.toLocaleLowerCase('ru-RU').includes(query.toLocaleLowerCase('ru-RU'))); active = -1; drawOptions(); helper('combo-helper', matches.length ? 'Найдено вариантов: ' + matches.length + '. Выберите и подтвердите.' : 'Нет подходящих вариантов.');
  }
  combo.addEventListener('input', () => { const sequence = ++comboSeq; clearTimeout(comboTimer); combo.removeAttribute('aria-activedescendant'); active = -1; matches = []; closeCombo(); comboTimer = setTimeout(() => void loadOptions(combo.value, sequence), 300); });
  combo.addEventListener('keydown', ev => {
    if (ev.key === 'Escape') { ev.preventDefault(); comboSeq++; clearTimeout(comboTimer); closeCombo(true); helper('combo-helper', committed ? 'Выбрано: ' + committed.label : 'Выбор ещё не сделан.'); return; }
    if (ev.key === 'Tab') { comboSeq++; clearTimeout(comboTimer); closeCombo(true); return; }
    if (ev.key === 'Enter' && !list.hidden) { ev.preventDefault(); if (active >= 0) commit(active); return; }
    if (ev.key === 'ArrowDown' || ev.key === 'ArrowUp') {
      ev.preventDefault(); clearTimeout(comboTimer);
      if (comboLoading) return;
      if (list.hidden) { comboSeq++; matches = options.filter(o => o.label.toLocaleLowerCase('ru-RU').includes(combo.value.toLocaleLowerCase('ru-RU'))); list.hidden = false; combo.setAttribute('aria-expanded', 'true'); active = ev.key === 'ArrowDown' ? 0 : matches.length - 1; }
      else if (matches.length) { active = Math.max(0, Math.min(matches.length - 1, active + (ev.key === 'ArrowDown' ? 1 : -1))); if (active < 0) active = 0; }
      drawOptions();
    }
  });
  combo.addEventListener('blur', () => { comboSeq++; clearTimeout(comboTimer); closeCombo(true); helper('combo-helper', committed ? 'Выбрано: '+committed.label : 'Выбор ещё не сделан.'); });
  $('combo-clear').onclick = () => { comboSeq++; clearTimeout(comboTimer); committed = null; combo.value = ''; closeCombo(); helper('combo-helper', 'Выбор сброшен.'); emit('onValueChange', { component: 'combobox', value: null }); combo.focus(); };

  // Data table: native semantics, stable ID selection and a single responsive data source.
  const data = [
    { id: '001024', date: '2026-10-14', product: 'Windows 11 Professional', status: 'paid', minor: 169000 },
    { id: '001023', date: '2026-10-13', product: 'Windows 11 Enterprise 2024 LTSC — лицензия для одного устройства', status: 'processing', minor: 219000 },
    { id: '001022', date: '2026-10-12', product: 'Microsoft Office Professional Plus', status: 'paid', minor: 459000 },
    { id: '001021', date: '2026-10-11', product: 'Windows Server Standard', status: 'awaiting_payment', minor: 249000 },
    { id: '001020', date: '2026-10-10', product: 'Windows 11 Home', status: 'cancelled', minor: 159000 }
  ];
  let selected = new Set(), sort = { field: 'date', dir: 'desc' }, applied = { min: null, max: null, from: '', to: '' }, visible = [];
  const statusInfo = { paid: ['Оплачен', 'success'], processing: ['В обработке', ''], awaiting_payment: ['Ожидает оплаты', 'warning'], cancelled: ['Отменён', 'error'] };
  const money = minor => new Intl.NumberFormat('ru-RU', { style: 'currency', currency: 'RUB', minimumFractionDigits: minor % 100 ? 2 : 0, maximumFractionDigits: 2 }).format(minor / 100);
  const dateText = date => date.split('-').reverse().join('.');
  function statusNode(row) { const s = document.createElement('span'); const [label, kind] = statusInfo[row.status] || ['Статус уточняется', 'neutral']; s.className = 'it-badge' + (kind ? ' it-badge--' + kind : ''); s.textContent = label; return s; }
  function rowCheck(row) { const label = document.createElement('label'); label.className = 'it-check-hit'; const input = document.createElement('input'); input.type = 'checkbox'; input.className = 'it-checkbox'; input.dataset.rowId = row.id; input.checked = selected.has(row.id); input.setAttribute('aria-label', 'Выбрать заказ ' + row.id); label.append(input); return label; }
  function openAction(row) { const b = document.createElement('button'); b.type = 'button'; b.className = 'it-button it-button--ghost'; b.style.setProperty('--button-min-width', '112px'); b.textContent = 'Открыть'; b.setAttribute('aria-label', 'Открыть заказ ' + row.id); b.onclick = () => { helper('order-action', 'Демонстрационное событие: открыть заказ #' + row.id); emit('onOpen', { orderId: row.id }); }; return b; }
  function updateSelection() { const all = visible.length && visible.every(r => selected.has(r.id)); const some = visible.some(r => selected.has(r.id)); for(const id of ['select-all','mobile-select-all']) { $(id).checked=!!all; $(id).indeterminate=!!some&&!all; $(id).disabled=!visible.length; } $('selected-count').textContent = 'Выбрано: ' + selected.size; }
  function renderTable() {
    visible = data.filter(r => (applied.min === null || r.minor >= applied.min) && (applied.max === null || r.minor <= applied.max) && (!applied.from || r.date >= applied.from) && (!applied.to || r.date <= applied.to)).sort((a, b) => { const comp = sort.field === 'amount' ? a.minor - b.minor : a.date.localeCompare(b.date); return (sort.dir === 'asc' ? comp : -comp) || a.id.localeCompare(b.id); });
    $('order-body').replaceChildren(); $('mobile-rows').replaceChildren();
    visible.forEach(r => {
      const tr = document.createElement('tr'); tr.dataset.id = r.id; tr.dataset.selected = String(selected.has(r.id));
      const td = () => document.createElement('td');
      let c = td(); c.append(rowCheck(r)); tr.append(c);
      c = td(); const id = document.createElement('strong'); id.style.color = 'var(--color-primary)'; id.textContent = '#' + r.id; c.append(id); tr.append(c);
      c = td(); c.textContent = dateText(r.date); tr.append(c);
      c = td(); c.textContent = r.product; tr.append(c);
      c = td(); c.append(statusNode(r)); tr.append(c);
      c = td(); c.className = 'it-amount'; c.textContent = money(r.minor); tr.append(c);
      c = td(); const wrap = document.createElement('span'); wrap.className = 'it-button-slot'; wrap.append(openAction(r)); c.append(wrap); tr.append(c); $('order-body').append(tr);
      const mobile = document.createElement('article'); mobile.className = 'it-mobile-row'; mobile.dataset.id = r.id;
      const head = document.createElement('header'); const number = document.createElement('strong'); number.textContent = '#' + r.id; head.append(rowCheck(r), number, statusNode(r)); mobile.append(head);
      const dl = document.createElement('dl'); for (const [name, value] of [['Товар', r.product], ['Дата', dateText(r.date)], ['Сумма', money(r.minor)]]) { const div = document.createElement('div'), dt = document.createElement('dt'), dd = document.createElement('dd'); dt.textContent = name; dd.textContent = value; div.append(dt, dd); dl.append(div); } mobile.append(dl, openAction(r)); $('mobile-rows').append(mobile);
    });
    $('data-empty').hidden = !!visible.length; $('orders').closest('.it-table-scroll').hidden = !visible.length;
    $('mobile-rows').hidden = !visible.length; $('result-count').textContent = 'Показано: ' + visible.length;
    updateSelection();
  }
  function syncChecks() { document.querySelectorAll('[data-row-id]').forEach(e => { e.checked = selected.has(e.dataset.rowId); }); document.querySelectorAll('#order-body tr').forEach(e => { e.dataset.selected = String(selected.has(e.dataset.id)); }); updateSelection(); }
  document.addEventListener('change', ev => { if (ev.target.dataset.rowId) { const id = ev.target.dataset.rowId; if (ev.target.checked) selected.add(id); else selected.delete(id); syncChecks(); emit('onSelectionChange', [...selected]); } });
  for(const id of ['select-all','mobile-select-all']) $(id).onchange = () => { visible.forEach(r=>{if($(id).checked) selected.add(r.id); else selected.delete(r.id);});syncChecks();emit('onSelectionChange',[...selected]); };
  function sortBy(field) { sort = { field, dir: sort.field === field && sort.dir === 'asc' ? 'desc' : 'asc' }; $('th-date').removeAttribute('aria-sort'); $('th-amount').removeAttribute('aria-sort'); $('th-' + field).setAttribute('aria-sort', sort.dir === 'asc' ? 'ascending' : 'descending'); $('date-arrow').innerHTML = icon(sort.field === 'date' ? sort.dir === 'asc' ? 'arrow-up' : 'arrow-down' : 'chevrons-up-down'); $('amount-arrow').innerHTML = icon(sort.field === 'amount' ? sort.dir === 'asc' ? 'arrow-up' : 'arrow-down' : 'chevrons-up-down'); renderTable(); emit('onSort', sort); }
  $('sort-date').onclick = () => sortBy('date'); $('sort-amount').onclick = () => sortBy('amount');
  function parseMoney(value) { const s = value.trim().replace(/[\s\u00a0₽]/g, '').replace(',', '.'); if (!s) return null; if (!/^\d+(\.\d{1,2})?$/.test(s)) return NaN; const [whole, fraction = ''] = s.split('.'); const minor = Number(whole + fraction.padEnd(2, '0')); return Number.isSafeInteger(minor) ? minor : NaN; }
  $('filters').onsubmit = ev => {
    ev.preventDefault(); const min = parseMoney($('min-price').value), max = parseMoney($('max-price').value), from = $('date-from').value, to = $('date-to').value;
    const badMoney = !Number.isNaN(min) && !Number.isNaN(max) && (min === null || max === null || min <= max);
    const badDates = !from || !to || from <= to;
    for (const id of ['min-price', 'max-price']) invalid($(id), !badMoney);
    for (const id of ['date-from', 'date-to']) invalid($(id), !badDates);
    if (!badMoney || !badDates) { helper('filter-error', !badMoney ? 'Введите корректный диапазон цен: «От» не больше «До».' : 'Дата начала должна быть не позже даты окончания.', true); $(!badMoney ? 'min-price' : 'date-from').focus(); return; }
    applied = { min, max, from, to }; selected.clear(); helper('filter-error', ''); renderTable(); emit('onFilterApply', applied);
  };
  function resetFilters() { for (const id of ['min-price', 'max-price', 'date-from', 'date-to']) { $(id).value = ''; invalid($(id), false); } applied = { min: null, max: null, from: '', to: '' }; selected.clear(); helper('filter-error', ''); renderTable(); emit('onFilterReset', applied); }
  $('reset-filter').onclick = resetFilters; $('empty-reset').onclick = () => { resetFilters(); $('min-price').focus(); };
  renderTable();

  // Native dialog provides modal focus containment and an inert background.
  const dialog = $('confirm-dialog'); let dialogBusy = false, dialogTrigger = null, previousOverflow = ''; 
  $('open-dialog').setAttribute('aria-haspopup', 'dialog'); $('open-dialog').setAttribute('aria-controls', 'confirm-dialog');
  $('open-dialog').onclick = () => { if(dialog.open) return; previousOverflow=document.body.style.overflow; document.body.style.overflow='hidden'; dialogTrigger = document.activeElement; if (!dialogBusy) $('dialog-error').hidden = true; dialog.showModal(); $('dialog-cancel').focus(); emit('onDialogOpen', {}); };
  for (const id of ['dialog-cancel', 'dialog-close']) $(id).onclick = () => dialog.close('cancel');
  dialog.addEventListener('keydown', ev => { if(ev.key!=='Tab') return; const nodes=[...dialog.querySelectorAll('button:not([disabled]),input:not([disabled]),[href],[tabindex]:not([tabindex="-1"])')].filter(el=>el.getClientRects().length); const first=nodes[0],last=nodes[nodes.length-1]; if(ev.shiftKey&&document.activeElement===first){ev.preventDefault();last.focus();}else if(!ev.shiftKey&&document.activeElement===last){ev.preventDefault();first.focus();} });
  dialog.addEventListener('close', () => { document.body.style.overflow=previousOverflow; if (dialogTrigger?.isConnected) dialogTrigger.focus(); else $('open-dialog').focus(); emit('onDialogClose', { result: dialog.returnValue }); });
  $('dialog-confirm').onclick = async () => {
    if (dialogBusy) return; dialogBusy = true; stats.dialogRequests++; const fail = $('dialog-fail').checked;
    busyButton($('dialog-confirm'), true, 'Удалить'); dialog.setAttribute('aria-busy', 'true'); $('dialog-error').hidden = true; emit('onConfirm', {});
    await delay(700); dialogBusy = false; busyButton($('dialog-confirm'), false, 'Удалить'); dialog.setAttribute('aria-busy', 'false');
    if (fail) { $('dialog-error').textContent = 'Не удалось удалить. Данные сохранены. Повторите попытку.'; $('dialog-error').hidden = false; helper('dialog-result', 'Демонстрационная операция не выполнена.', true); if (dialog.open) $('dialog-confirm').focus(); emit('onConfirmError', {}); }
    else { if (dialog.open) dialog.close('success'); helper('dialog-result', 'Демонстрационная операция выполнена.'); emit('onConfirmSuccess', {}); }
  };

  // Coupon: preserve draft, freeze during request, success only from the response.
  let couponBusy = false, couponApplied = false;
  $('coupon-form').onsubmit = async ev => {
    ev.preventDefault(); if (couponBusy) return;
    if (couponApplied) { couponBusy=true;stats.couponRequests++;busyButton($('coupon-apply'),true,'Убрать');helper('coupon-helper','Удаляем купон…');await delay(450);couponBusy=false;couponApplied=false;$('coupon').readOnly=false;busyButton($('coupon-apply'),false,'Применить');$('coupon-apply').classList.remove('it-button--secondary');$('coupon-apply').classList.add('it-button--primary');helper('coupon-helper','Купон удалён. Демонстрационная сумма пересчитана.');emit('onCouponRemove',{});return; }
    const code = $('coupon').value.trim();
    if (!code) { invalid($('coupon'), true); helper('coupon-helper', 'Введите код купона.', true); $('coupon').focus(); return; }
    couponBusy = true; $('coupon').readOnly = true; busyButton($('coupon-apply'), true, 'Применить'); helper('coupon-helper', 'Проверяем купон…'); stats.couponRequests++; emit('onCouponSubmit', { code });
    await delay(650); couponBusy = false; busyButton($('coupon-apply'), false, 'Применить');
    if (code === 'SOFT10') { couponApplied = true; invalid($('coupon'), false); $('coupon-apply').textContent = 'Убрать'; $('coupon-apply').classList.remove('it-button--primary');$('coupon-apply').classList.add('it-button--secondary'); helper('coupon-helper', 'Купон применён. Это демонстрационный ответ.'); emit('onCouponSuccess', { code }); }
    else { $('coupon').readOnly = false; invalid($('coupon'), true); helper('coupon-helper', 'Код не найден или больше не действует.', true); $('coupon').focus(); emit('onCouponError', { code }); }
  };
  $('coupon').oninput = () => { invalid($('coupon'), false); if (!couponBusy && !couponApplied) helper('coupon-helper', 'Купон не применён.'); };

  // Mask and clipboard are independent actions; unavailable keys cannot be copied.
  const rawKey = 'XXXXX-XXXXX-XXXXX-XXXXX-XXXXX'; let revealed = false, copying = false, keyVersion = 0, copyTimer;
  function renderKey() { const available = $('key-available').checked; $('license').value = available ? revealed ? rawKey : rawKey.replace(/X/g, '•') : 'Ключ ещё недоступен'; $('reveal-key').disabled = !available; $('copy-key').disabled = !available || copying; $('reveal-key').setAttribute('aria-pressed', String(revealed)); $('reveal-key').setAttribute('aria-label', revealed ? 'Скрыть ключ' : 'Показать ключ'); $('reveal-key').innerHTML = icon(revealed ? 'eye-off' : 'eye'); }
  $('key-available').onchange = () => { keyVersion++; clearTimeout(copyTimer); revealed = false; renderKey(); helper('license-helper', $('key-available').checked ? 'Ключ скрыт. Копирование использует исходное значение.' : 'Дождитесь подтверждения оплаты.'); };
  $('reveal-key').onclick = () => { if (!$('key-available').checked) return; revealed = !revealed; renderKey(); helper('license-helper', revealed ? 'Ключ показан. Его можно выделить и скопировать вручную.' : 'Ключ скрыт.'); emit('onReveal', { revealed }); };
  $('copy-key').onclick = async () => {
    if (!$('key-available').checked || copying) return; copying = true; stats.copyRequests++; const version = keyVersion; clearTimeout(copyTimer); $('copy-key').innerHTML=icon('loader-circle').replace('class="it-icon"','class="it-icon it-spinner"'); $('copy-key').setAttribute('aria-busy','true'); renderKey();
    try {
      if ($('copy-fail').checked) throw Error('simulated_clipboard_denial');
      if (!navigator.clipboard?.writeText) throw Error('clipboard_unavailable');
      await navigator.clipboard.writeText(rawKey);
      if (version !== keyVersion) return;
      helper('license-helper', 'Ключ скопирован.'); $('copy-key').innerHTML = icon('check'); emit('onCopySuccess', {});
      copyTimer = setTimeout(() => { $('copy-key').innerHTML = icon('copy'); }, 1500);
    } catch { if (version === keyVersion) { $('copy-key').innerHTML=icon('copy'); helper('license-helper', 'Не удалось скопировать. Нажмите «Показать», затем выделите ключ и скопируйте вручную.', true); emit('onCopyError', {}); } }
    finally { copying = false; $('copy-key').setAttribute('aria-busy','false'); if(version!==keyVersion) $('copy-key').innerHTML=icon('copy'); renderKey(); }
  };
  renderKey(); emit('onReady', { components: ['form', 'combobox', 'table', 'dialog', 'coupon', 'license'] });
})();
