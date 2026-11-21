(() => {
  'use strict';
  const search = document.getElementById('ahSearch');
  const decision = document.getElementById('ahDecision');
  const table = document.getElementById('ahTable');
  if (!search || !decision || !table) return;
  // Mirrors the regular Admin custom dropdown while preserving native select/change behavior.
  function enhanceDecisionSelect(select) {
    if (select.dataset.enhanced === '1') return;
    select.dataset.enhanced = '1';
    const wrapper = document.createElement('div');
    wrapper.className = 'ah-select-control';
    select.parentNode.insertBefore(wrapper, select);
    wrapper.appendChild(select);
    select.classList.add('ah-native-select');
    const trigger = document.createElement('button');
    trigger.type = 'button';
    trigger.className = 'ah-select-trigger';
    trigger.setAttribute('aria-haspopup', 'listbox');
    trigger.setAttribute('aria-expanded', 'false');
    trigger.setAttribute('aria-label', 'Filter by decision');
    const label = document.createElement('span');
    const chevron = document.createElement('span');
    chevron.className = 'ah-select-chevron';
    chevron.setAttribute('aria-hidden', 'true');
    trigger.append(label, chevron);
    const options = document.createElement('div');
    options.className = 'ah-select-options';
    options.id = 'ahDecisionOptions';
    options.setAttribute('role', 'listbox');
    options.setAttribute('aria-label', 'Decision options');
    options.hidden = true;
    trigger.setAttribute('aria-controls', options.id);
    wrapper.append(trigger, options);
    const allOptions = [...select.options];
    let activeIndex = Math.max(0, select.selectedIndex);
    const rendered = allOptions.map((opt, index) => {
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'ah-select-option';
      btn.textContent = opt.text;
      btn.setAttribute('role', 'option');
      btn.dataset.value = opt.value;
      btn.addEventListener('click', () => choose(index));
      options.append(btn);
      return btn;
    });
    function sync() {
      const index = Math.max(0, select.selectedIndex);
      label.textContent = allOptions[index]?.text || 'Select decision';
      rendered.forEach((btn, i) => btn.setAttribute('aria-selected', String(i === index)));
    }
    function close(returnFocus = false) {
      options.hidden = true;
      trigger.setAttribute('aria-expanded','false');
      if (returnFocus) trigger.focus();
    }
    function open() {
      options.hidden = false;
      trigger.setAttribute('aria-expanded','true');
      activeIndex = Math.max(0, select.selectedIndex);
      rendered[activeIndex]?.focus();
    }
    function choose(index) {
      select.selectedIndex = index;
      select.dispatchEvent(new Event('change', {bubbles:true}));
      close(true);
    }
    trigger.addEventListener('click', () => options.hidden ? open() : close());
    trigger.addEventListener('keydown', e => {
      if (['ArrowDown','ArrowUp','Enter',' '].includes(e.key)) {e.preventDefault(); open();}
    });
    options.addEventListener('keydown', e => {
      if (e.key === 'Escape' || e.key === 'Tab') {close(e.key === 'Escape');return;}
      if (['ArrowDown','ArrowUp','Home','End'].includes(e.key)) {
        e.preventDefault();
        if (e.key === 'Home') activeIndex = 0;
        else if (e.key === 'End') activeIndex = rendered.length - 1;
        else activeIndex = (activeIndex + (e.key === 'ArrowDown' ? 1 : -1) + rendered.length) % rendered.length;
        rendered[activeIndex]?.focus();
      }
      if (e.key === 'Enter' || e.key === ' ') {e.preventDefault();choose(activeIndex);}
    });
    rendered.forEach((btn, i) => btn.addEventListener('focus', () => {activeIndex = i;}));
    select.addEventListener('change', sync);
    document.addEventListener('click', e => { if (!wrapper.contains(e.target)) close(); });
    sync();
  }
  enhanceDecisionSelect(decision);
  const rows = [...table.querySelectorAll('tr.ah-data-row')];
  const results = document.getElementById('ahResultCount');
  const pageInfo = document.getElementById('ahPageInfo');
  const pageNumber = document.getElementById('ahPageNumber');
  const prev = document.getElementById('ahPrevious');
  const next = document.getElementById('ahNext');
  const empty = document.getElementById('ahEmpty');
  const pageSize = 10;
  let page = 1;
  let filtered = rows;
  const closeMenus = except => {
    document.querySelectorAll('.ah-more[open]').forEach(menu => {
      if (menu !== except) menu.open = false;
    });
  };
  function render() {
    const totalPages = Math.max(1, Math.ceil(filtered.length / pageSize));
    page = Math.min(page, totalPages);
    const start = (page - 1) * pageSize;
    const visible = new Set(filtered.slice(start, start + pageSize));
    rows.forEach(row => { row.hidden = !visible.has(row); });
    results.textContent = `${filtered.length} matching record${filtered.length === 1 ? '' : 's'}`;
    pageInfo.textContent = filtered.length ? `Showing ${start + 1}–${Math.min(start + pageSize, filtered.length)} of ${filtered.length}` : '0 records';
    pageNumber.textContent = `Page ${page} of ${totalPages}`;
    prev.disabled = page <= 1;
    next.disabled = page >= totalPages;
    empty.hidden = filtered.length !== 0;
    closeMenus();
  }
  function apply() {
    const query = search.value.trim().toLocaleLowerCase();
    const selected = decision.value;
    filtered = rows.filter(row => (!query || row.dataset.search.includes(query)) && (selected === 'all' || row.dataset.decision === selected));
    page = 1;
    render();
  }
  search.addEventListener('input', apply);
  decision.addEventListener('change', apply);
  prev.addEventListener('click', () => { if (page > 1) { page--; render(); }});
  next.addEventListener('click', () => { if (page * pageSize < filtered.length) { page++; render(); }});
  function positionMenu(details) {
    const trigger = details.querySelector('summary');
    const menu = details.querySelector('.ah-more-menu');
    if (!trigger || !menu) return;
    const rect = trigger.getBoundingClientRect();
    const width = Math.max(menu.offsetWidth, 190);
    menu.style.left = Math.max(8, Math.min(rect.right - width, innerWidth - width - 8)) + 'px';
    const height = menu.offsetHeight;
    menu.style.top = (rect.bottom + height + 8 > innerHeight && rect.top > height ? rect.top - height - 5 : rect.bottom + 5) + 'px';
  }
  document.addEventListener('toggle', event => {
    if (event.target.matches?.('.ah-more') && event.target.open) {
      closeMenus(event.target);
      positionMenu(event.target);
    }
  }, true);
  document.addEventListener('scroll', () => closeMenus(), true);
  window.addEventListener('resize', () => closeMenus());
  document.addEventListener('click', event => {
    if (!event.target.closest('.ah-more')) closeMenus();
  });
  document.addEventListener('keydown', event => { if (event.key === 'Escape') closeMenus(); });
  render();
})();
