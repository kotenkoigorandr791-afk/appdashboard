(function () {
  const WIDE = '.form-group select,.sp-share__select,.props-table select';
  let current = null;
  function labelOf(sel) {
    const o = sel.options[sel.selectedIndex];
    return o ? o.textContent : '';
  }
  function sync(sel) {
    const cs = sel._cs;
    cs.text.textContent = labelOf(sel);
    cs.btn.disabled = sel.disabled;
    cs.wrap.classList.toggle('cs--disabled', sel.disabled);
  }
  function closeList() {
    if (!current) return;
    current.wrap.classList.remove('cs--open');
    current.list.remove();
    current = null;
  }
  function place(cs) {
    const r = cs.btn.getBoundingClientRect();
    const l = cs.list;
    l.style.minWidth = r.width + 'px';
    l.style.left = Math.min(r.left, Math.max(8, window.innerWidth - l.offsetWidth - 8)) + 'px';
    const below = window.innerHeight - r.bottom - 12;
    const above = r.top - 12;
    const up = below < Math.min(l.scrollHeight, 220) && above > below;
    l.style.maxHeight = Math.max(120, Math.min(280, up ? above : below)) + 'px';
    l.style.top = up ? Math.max(8, r.top - l.offsetHeight - 6) + 'px' : r.bottom + 6 + 'px';
  }
  function choose(sel, index) {
    if (sel.selectedIndex !== index) {
      sel.selectedIndex = index;
      sel.dispatchEvent(new Event('input', { bubbles: true }));
      sel.dispatchEvent(new Event('change', { bubbles: true }));
    }
    sync(sel);
    closeList();
  }
  function openList(sel) {
    const cs = sel._cs;
    if (current === cs) return closeList();
    closeList();
    const list = document.createElement('div');
    list.className = 'cs__list' + (document.body.classList.contains('light') ? ' cs__list--light' : '');
    list.setAttribute('role', 'listbox');
    Array.from(sel.options).forEach((o, i) => {
      const item = document.createElement('div');
      item.className = 'cs__opt' + (i === sel.selectedIndex ? ' cs__opt--on' : '') + (o.disabled ? ' cs__opt--off' : '');
      item.setAttribute('role', 'option');
      item.textContent = o.textContent;
      if (!o.disabled) item.addEventListener('mousedown', (e) => { e.preventDefault(); choose(sel, i); });
      list.appendChild(item);
    });
    cs.list = list;
    document.body.appendChild(list);
    cs.wrap.classList.add('cs--open');
    current = cs;
    place(cs);
    const on = list.querySelector('.cs__opt--on');
    if (on) on.scrollIntoView({ block: 'nearest' });
  }
  function step(sel, dir) {
    let i = sel.selectedIndex;
    do { i += dir; } while (sel.options[i] && sel.options[i].disabled);
    if (sel.options[i]) choose(sel, i);
  }
  function enhance(sel) {
    if (sel._cs || sel.multiple || sel.size > 1 || sel.hasAttribute('data-native-select')) return;
    const wrap = document.createElement('div');
    wrap.className = 'cs';
    if (sel.style.width) wrap.style.width = sel.style.width;
    else if (sel.matches(WIDE)) wrap.style.width = '100%';
    if (sel.style.flex) wrap.style.flex = sel.style.flex;
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'cs__btn';
    btn.setAttribute('aria-haspopup', 'listbox');
    const text = document.createElement('span');
    text.className = 'cs__text';
    const arrow = document.createElement('span');
    arrow.className = 'cs__arrow';
    btn.append(text, arrow);
    sel.parentNode.insertBefore(wrap, sel);
    wrap.append(btn, sel);
    sel.classList.add('cs__native');
    sel.tabIndex = -1;
    sel.setAttribute('aria-hidden', 'true');
    sel._cs = { wrap, btn, text, list: null };
    btn.addEventListener('click', () => openList(sel));
    btn.addEventListener('keydown', (e) => {
      if (e.key === 'ArrowDown') { e.preventDefault(); step(sel, 1); }
      else if (e.key === 'ArrowUp') { e.preventDefault(); step(sel, -1); }
      else if (e.key === 'Escape') closeList();
    });
    ['value', 'selectedIndex'].forEach((prop) => {
      const d = Object.getOwnPropertyDescriptor(HTMLSelectElement.prototype, prop);
      Object.defineProperty(sel, prop, {
        configurable: true,
        get() { return d.get.call(this); },
        set(v) { d.set.call(this, v); sync(this); }
      });
    });
    new MutationObserver(() => sync(sel)).observe(sel, { childList: true, subtree: true, attributes: true, characterData: true });
    sel.addEventListener('change', () => sync(sel));
    sync(sel);
  }
  function scan(root) {
    if (root.nodeType !== 1) return;
    if (root.tagName === 'SELECT') enhance(root);
    root.querySelectorAll && root.querySelectorAll('select').forEach(enhance);
  }
  function init() {
    scan(document.body);
    new MutationObserver((list) => list.forEach((m) => m.addedNodes.forEach(scan))).observe(document.body, { childList: true, subtree: true });
    document.addEventListener('mousedown', (e) => {
      if (current && !current.list.contains(e.target) && !current.wrap.contains(e.target)) closeList();
    }, true);
    window.addEventListener('resize', closeList);
    window.addEventListener('scroll', (e) => { if (current && !current.list.contains(e.target)) closeList(); }, true);
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeList(); });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
