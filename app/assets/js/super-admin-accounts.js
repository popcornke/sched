(() => {
  'use strict';
  const overlay = document.getElementById('saAccountConfirm');
  const accept = document.getElementById('saConfirmAccept');
  const cancel = document.getElementById('saConfirmCancel');
  if (!overlay || !accept || !cancel) return;
  let selectedForm = null;
  let formerFocus = null;
  const close = () => {
    overlay.hidden = true;
    selectedForm = null;
    document.body.classList.remove('sa-confirm-open');
    formerFocus?.focus();
  };
  // Delegate form confirmation so it also works for live-filtered table rows.
  document.addEventListener('submit', event => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || !form.matches('.sa-account-menu form[data-confirm-title]')) return;
    if (form.dataset.confirmed === 'yes') return;
    event.preventDefault();
    selectedForm = form;
    formerFocus = document.activeElement;
    document.getElementById('saConfirmTitle').textContent = form.dataset.confirmTitle || 'Confirm account action';
    document.getElementById('saConfirmMessage').textContent = form.dataset.confirmDetail || 'Continue with this action?';
    accept.classList.toggle('sa-btn--danger', form.dataset.danger === '1');
    accept.classList.toggle('sa-btn--primary', form.dataset.danger !== '1');
    overlay.hidden = false;
    document.body.classList.add('sa-confirm-open');
    cancel.focus();
  });
  accept.addEventListener('click', () => {
    if (!selectedForm) return;
    const form = selectedForm;
    form.dataset.confirmed = 'yes';
    form.requestSubmit();
  });
  cancel.addEventListener('click', close);
  overlay.addEventListener('click', e => { if (e.target === overlay) close(); });
  document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && !overlay.hidden) { e.preventDefault(); close(); }
  });
  document.addEventListener('click', e => {
    document.querySelectorAll('.sa-account-menu[open]').forEach(menu => {
      if (!menu.contains(e.target)) menu.open = false;
    });
  });
  // Custom account selects: original select remains the form's source of truth.
  // Menus render in document.body so card/table overflow cannot clip them.
  let openPicker = null;
  const closePicker = (returnFocus = false) => {
    if (!openPicker) return;
    const {menu, trigger} = openPicker;
    menu.remove();
    trigger.setAttribute('aria-expanded', 'false');
    trigger.classList.remove('sa-picker-open');
    if (returnFocus) trigger.focus();
    openPicker = null;
  };
  document.querySelectorAll('.sa-modern-select').forEach((select, index) => {
    const wrapper = document.createElement('div');
    wrapper.className = 'sa-picker';
    select.parentNode.insertBefore(wrapper, select);
    wrapper.appendChild(select);
    select.classList.add('sa-picker-native');
    select.tabIndex = -1;
    select.setAttribute('aria-hidden', 'true');

    const trigger = document.createElement('button');
    trigger.type = 'button';
    trigger.className = 'sa-picker-trigger';
    trigger.id = `sa-picker-trigger-${index}`;
    trigger.setAttribute('aria-haspopup', 'listbox');
    trigger.setAttribute('aria-expanded', 'false');
    trigger.disabled = select.disabled;
    const name = select.closest('.sa-field')?.querySelector('label')?.textContent?.trim() || 'Option';
    trigger.setAttribute('aria-label', `Choose ${name}`);
    const current = document.createElement('span');
    current.className = 'sa-picker-value';
    const arrow = document.createElement('i');
    arrow.className = 'fa-solid fa-chevron-down sa-picker-arrow';
    arrow.setAttribute('aria-hidden', 'true');
    trigger.append(current, arrow);
    wrapper.appendChild(trigger);
    const sync = () => {
      current.textContent = select.selectedOptions[0]?.textContent?.trim() || 'Select an option';
      trigger.disabled = select.disabled;
    };
    select.addEventListener('change', sync);
    sync();

    trigger.addEventListener('click', () => {
      if (trigger.disabled) return;
      if (openPicker?.trigger === trigger) {closePicker(); return;}
      closePicker();
      const menu = document.createElement('div');
      menu.className = 'sa-picker-menu';
      menu.id = `sa-picker-menu-${index}`;
      menu.setAttribute('role', 'listbox');
      menu.setAttribute('aria-label', name);
      trigger.setAttribute('aria-controls', menu.id);
      const buttons = [];
      Array.from(select.options).forEach((option, optIndex) => {
        const item = document.createElement('button');
        item.type = 'button';
        item.className = 'sa-picker-option';
        item.setAttribute('role', 'option');
        item.setAttribute('aria-selected', String(optIndex === select.selectedIndex));
        item.textContent = option.textContent.trim();
        item.disabled = option.disabled;
        if (optIndex === select.selectedIndex) item.classList.add('is-selected');
        item.addEventListener('click', () => {
          select.selectedIndex = optIndex;
          select.dispatchEvent(new Event('change', {bubbles:true}));
          closePicker(true);
        });
        menu.appendChild(item);
        buttons.push(item);
      });
      document.body.appendChild(menu);
      const bounds = trigger.getBoundingClientRect();
      const menuWidth = Math.max(bounds.width, 180);
      const height = Math.min(menu.scrollHeight, 292);
      const below = window.innerHeight - bounds.bottom;
      const flip = below < height + 12 && bounds.top > below;
      const top = flip ? Math.max(8, bounds.top - height - 6) : Math.min(window.innerHeight - 8 - height, bounds.bottom + 6);
      menu.style.width = `${menuWidth}px`;
      menu.style.left = `${Math.max(8, Math.min(bounds.left, window.innerWidth - menuWidth - 8))}px`;
      menu.style.top = `${Math.max(8, top)}px`;
      trigger.setAttribute('aria-expanded', 'true');
      trigger.classList.add('sa-picker-open');
      openPicker = {menu, trigger, buttons};
      (buttons[select.selectedIndex] || buttons[0])?.focus();
    });
  });
  document.addEventListener('pointerdown', e => {
    if (openPicker && !openPicker.menu.contains(e.target) && !openPicker.trigger.contains(e.target)) closePicker();
  });
  document.addEventListener('keydown', e => {
    if (!openPicker) return;
    const {menu, trigger, buttons} = openPicker;
    if (e.key === 'Escape') {e.preventDefault();closePicker(true);return;}
    const enabled = buttons.filter(b => !b.disabled);
    const pos = enabled.indexOf(document.activeElement);
    if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
      e.preventDefault();
      enabled[(pos + (e.key === 'ArrowDown' ? 1 : -1) + enabled.length) % enabled.length]?.focus();
    } else if (e.key === 'Home' || e.key === 'End') {
      e.preventDefault();enabled[e.key === 'Home' ? 0 : enabled.length - 1]?.focus();
    } else if (e.key === 'Tab') closePicker();
  });
  window.addEventListener('resize', () => closePicker());
  document.addEventListener('scroll', e => {if (openPicker) closePicker();}, true);

  // Live account filtering: update only the results; keep the search focused.
  const filters = document.getElementById('saAccountFilters');
  const tableWrap = document.getElementById('saAccountsTableWrap');
  const countLabel = document.getElementById('saAccountsCount');
  const feedback = document.getElementById('saAccountFilterFeedback');
  if (filters && tableWrap && countLabel && feedback) {
    const search = filters.querySelector('[name="q"]');
    const role = filters.querySelector('[name="role"]');
    const status = filters.querySelector('[name="status"]');
    let debounceTimer = null;
    let activeRequest = null;
    let requestSerial = 0;

    const buildUrl = (page = 1) => {
      const url = new URL(window.location.href);
      url.searchParams.delete('q');
      url.searchParams.delete('role');
      url.searchParams.delete('status');
      url.searchParams.delete('page');
      if (search.value.trim()) url.searchParams.set('q', search.value.trim());
      if (role.value) url.searchParams.set('role', role.value);
      if (status.value) url.searchParams.set('status', status.value);
      if (page > 1) url.searchParams.set('page', String(page));
      // Filter changes must not accidentally jump to an old edit form.
      url.searchParams.delete('edit');
      url.hash = '';
      return url;
    };
    const refresh = async (url, push = true) => {
      if (activeRequest) activeRequest.abort();
      const controller = new AbortController();
      activeRequest = controller;
      const serial = ++requestSerial;
      feedback.textContent = 'Filtering…';
      tableWrap.setAttribute('aria-busy', 'true');
      try {
        const response = await fetch(url.toString(), {
          method: 'GET', credentials: 'same-origin', signal: controller.signal,
          headers: {'X-Requested-With':'XMLHttpRequest'}
        });
        if (!response.ok) throw new Error(`HTTP ${response.status}`);
        const doc = new DOMParser().parseFromString(await response.text(), 'text/html');
        const newBody = doc.querySelector('#saAccountsTableWrap tbody');
        const newCount = doc.querySelector('#saAccountsCount');
        const newPagination = doc.querySelector('#saAccountsPagination');
        const oldBody = tableWrap.querySelector('tbody');
        if (!newBody || !newCount || !oldBody) throw new Error('Unexpected filter response');
        if (serial !== requestSerial) return;
        oldBody.replaceWith(document.importNode(newBody, true));
        countLabel.textContent = newCount.textContent;
        document.getElementById('saAccountsPagination')?.remove();
        if (newPagination) {
          tableWrap.insertAdjacentElement('afterend', document.importNode(newPagination, true));
        }
        if (push) history.replaceState(null, '', url.toString());
        feedback.textContent = '';
      } catch (error) {
        if (error.name !== 'AbortError' && serial === requestSerial) {
          feedback.textContent = 'Unable to filter right now. Please try again.';
          console.error('Account live filter:', error);
        }
      } finally {
        if (serial === requestSerial) {
          tableWrap.removeAttribute('aria-busy');
          activeRequest = null;
        }
      }
    };
    const scheduleRefresh = () => {
      clearTimeout(debounceTimer);
      debounceTimer = setTimeout(() => refresh(buildUrl()), 280);
    };
    search?.addEventListener('input', scheduleRefresh);
    role?.addEventListener('change', () => { clearTimeout(debounceTimer); refresh(buildUrl()); });
    status?.addEventListener('change', () => { clearTimeout(debounceTimer); refresh(buildUrl()); });
    filters.addEventListener('submit', event => {
      event.preventDefault();
      clearTimeout(debounceTimer);
      refresh(buildUrl());
    });
    document.addEventListener('click', event => {
      const link = event.target.closest('#saAccountsPagination a');
      if (!link) return;
      event.preventDefault();
      clearTimeout(debounceTimer);
      refresh(new URL(link.href));
    });
  }

})();
