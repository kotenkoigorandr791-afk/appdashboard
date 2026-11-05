(function () {
  'use strict';

  // Board cards render only a lightweight "#id" trigger.
  // The actions list (edit, comment, close, ...) is fetched the first time the menu is used.
  function load(el) {
    var state = el.getAttribute('data-kb-menu-state');
    if (state === 'loading' || state === 'ready') return;
    var url = el.getAttribute('data-kb-menu-url');
    if (!url) return;
    el.setAttribute('data-kb-menu-state', 'loading');
    fetch(url, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function (r) { return r.ok ? r.text() : Promise.reject(new Error('HTTP ' + r.status)); })
      .then(function (html) {
        var tpl = document.createElement('template');
        tpl.innerHTML = html.trim();
        var content = tpl.content.querySelector('ul') || tpl.content;
        var list = el.querySelector('ul');
        if (list) list.parentNode.replaceChild(content, list);
        else el.appendChild(content);
        el.setAttribute('data-kb-menu-state', 'ready');
      })
      .catch(function () { el.setAttribute('data-kb-menu-state', 'error'); });
  }

  document.addEventListener('mouseover', function (e) {
    var el = e.target && e.target.closest ? e.target.closest('.kb-task-menu') : null;
    if (el) load(el);
  }, true);

  document.addEventListener('focusin', function (e) {
    var el = e.target && e.target.closest ? e.target.closest('.kb-task-menu') : null;
    if (el) load(el);
  }, true);

  document.addEventListener('touchstart', function (e) {
    var el = e.target && e.target.closest ? e.target.closest('.kb-task-menu') : null;
    if (el) load(el);
  }, { capture: true, passive: true });
})();
