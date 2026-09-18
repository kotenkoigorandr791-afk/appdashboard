(function () {
  function shellParam(name) {
    try {
      var v = new URLSearchParams(location.search || '').get(name);
      if (v) return v;
    } catch (e) {}
    try {
      if (window.parent && window.parent !== window) {
        var pv = new URLSearchParams(window.parent.location.search || '').get(name);
        if (pv) return pv;
      }
    } catch (e2) {}
    return '';
  }
  function readDeclaredTheme() {
    var fromUrl = shellParam('theme');
    if (fromUrl) return fromUrl.toLowerCase();
    var vars = (document.getElementById('appdashboard-kb-vars') || {}).textContent || '';
    var m = vars.match(/--kb-shell-theme:\s*([a-z]+)/i);
    if (m && m[1]) return m[1].toLowerCase();
    return 'light';
  }
  function normalize(theme) {
    theme = String(theme || 'light').toLowerCase();
    if (theme === 'device') return 'auto';
    return theme === 'dark' || theme === 'auto' ? theme : 'light';
  }
  function effectiveDark(theme) {
    if (theme === 'dark') return true;
    if (theme === 'auto') {
      try {
        return window.matchMedia('(prefers-color-scheme: dark)').matches;
      } catch (e) {
        return false;
      }
    }
    return false;
  }
  function applyTheme() {
    var theme = normalize(readDeclaredTheme());
    var dark = effectiveDark(theme);
    document.documentElement.classList.toggle('kb-dark', dark);
    document.documentElement.setAttribute('data-kb-theme', theme);
    if (document.body) document.body.classList.toggle('kb-dark', dark);
  }
  function normalizeColor(raw) {
    if (!raw) return '';
    try { raw = decodeURIComponent(String(raw).trim()); } catch (e) {}
    if (/^%23/i.test(raw)) raw = '#' + raw.slice(3);
    if (raw && raw.charAt(0) !== '#') raw = '#' + raw.replace(/^#/, '');
    return /^#[0-9A-Fa-f]{6}$/i.test(raw) ? raw : '';
  }
  function readDeclaredColor() {
    var fromUrl = normalizeColor(shellParam('color'));
    if (fromUrl) return fromUrl;
    var vars = (document.getElementById('appdashboard-kb-vars') || {}).textContent || '';
    var m = vars.match(/--primary-color:\s*(#[0-9A-Fa-f]{6})/i);
    if (m && m[1]) return m[1];
    try {
      var parentColor = getComputedStyle(window.parent.document.documentElement).getPropertyValue('--primary-color').trim();
      parentColor = normalizeColor(parentColor);
      if (parentColor) return parentColor;
    } catch (e) {}
    return '';
  }
  function applyAccentColor() {
    var color = readDeclaredColor();
    if (!color) return;
    var root = document.documentElement;
    var body = document.body;
    root.style.setProperty('--primary-color', color);
    body.style.setProperty('--primary-color', color);
    root.style.setProperty('--link-color-primary', color);
    root.style.setProperty('--link-color-hover', color);
    root.style.setProperty('--link-color-focus', color);
    root.style.setProperty('--button-primary-background-color', color);
    root.style.setProperty('--button-primary-border-color', color);
    root.style.setProperty('accent-color', color);
    body.style.setProperty('accent-color', color);
  }
  function mix(a, b, t) {
    return Math.round(a + (b - a) * t);
  }
  function stageColor(t, dark) {
    var stops = dark
      ? [[71, 85, 105], [37, 99, 235], [217, 119, 6], [22, 163, 74]]
      : [[100, 116, 139], [59, 130, 246], [245, 158, 11], [34, 197, 94]];
    var max = stops.length - 1;
    var x = Math.max(0, Math.min(1, t)) * max;
    var i = Math.floor(x);
    var f = x - i;
    var c0 = stops[i];
    var c1 = stops[Math.min(max, i + 1)];
    return [
      mix(c0[0], c1[0], f),
      mix(c0[1], c1[1], f),
      mix(c0[2], c1[2], f)
    ];
  }
  function isTodoColumn(header) {
    if (!header) return false;
    var titleEl = header.querySelector('.board-column-title a.dropdown-menu, .board-column-title');
    var title = titleEl ? (titleEl.textContent || '').replace(/\s+/g, ' ').trim().toLowerCase() : '';
    title = title.replace(/\s*▼\s*$/, '').trim();
    if (title === 'todo' || title === 'ready') return true;
    if (title.indexOf('todo ') === 0 || title.indexOf('ready ') === 0) return true;
    return false;
  }
  function renameTodoColumnLabel(header) {
    if (!header || header.getAttribute('data-kb-todo') === '1') return;
    var link = header.querySelector('.board-column-title a.dropdown-menu');
    if (link) {
      for (var n = 0; n < link.childNodes.length; n++) {
        var node = link.childNodes[n];
        if (node.nodeType === 3 && /ready|todo/i.test(node.textContent || '')) {
          node.textContent = node.textContent.replace(/ready|todo/i, 'todo');
        }
      }
    }
    header.setAttribute('data-kb-todo', '1');
  }
  function paintBoardColumns() {
    var board = document.getElementById('board');
    if (!board) return;
    var headers = board.querySelectorAll('tr:first-child th.board-column-header');
    var n = headers.length;
    if (!n) return;
    var dark = document.body.classList.contains('kb-dark');
    for (var i = 0; i < n; i++) {
      var todo = isTodoColumn(headers[i]);
      if (todo) renameTodoColumnLabel(headers[i]);
      var rgb = todo ? [220, 38, 38] : stageColor(n === 1 ? 1 : i / (n - 1), dark);
      var bg = 'rgba(' + rgb[0] + ',' + rgb[1] + ',' + rgb[2] + ',' + (dark ? (todo ? '0.34' : '0.28') : (todo ? '0.22' : '0.20')) + ')';
      var border = 'rgba(' + rgb[0] + ',' + rgb[1] + ',' + rgb[2] + ',' + (dark ? '0.62' : '0.48') + ')';
      var color = todo
        ? (dark ? '#fecaca' : '#b91c1c')
        : (dark
          ? 'rgb(' + Math.min(255, rgb[0] + 80) + ',' + Math.min(255, rgb[1] + 80) + ',' + Math.min(255, rgb[2] + 80) + ')'
          : 'rgb(' + Math.max(0, rgb[0] - 40) + ',' + Math.max(0, rgb[1] - 40) + ',' + Math.max(0, rgb[2] - 40) + ')');
      headers[i].style.setProperty('background', bg, 'important');
      headers[i].style.setProperty('border-color', border, 'important');
      headers[i].style.setProperty('color', color, 'important');
      if (todo) headers[i].classList.add('kb-column-todo');
      var links = headers[i].querySelectorAll('a, .board-column-title');
      for (var L = 0; L < links.length; L++) links[L].style.setProperty('color', color, 'important');
      var cells = board.querySelectorAll('tr.board-swimlane > td:nth-child(' + (i + 1) + ')');
      for (var c = 0; c < cells.length; c++) {
        cells[c].style.setProperty('background', bg, 'important');
        cells[c].style.setProperty('border-color', border, 'important');
        if (todo) cells[c].classList.add('kb-column-todo');
      }
    }
  }
  function fillBoardHeight() {
    var board = document.getElementById('board');
    var container = document.getElementById('board-container');
    if (!board || !container) return;
    var top = container.getBoundingClientRect().top;
    var h = Math.max(280, window.innerHeight - top - 16);
    container.style.minHeight = h + 'px';
    board.style.minHeight = h + 'px';
    var lists = board.querySelectorAll('.board-task-list.board-column-expanded');
    var headerRow = board.querySelector('tr:first-child');
    var headerH = headerRow ? headerRow.getBoundingClientRect().height : 48;
    var listH = Math.max(200, h - headerH - 8);
    var needsScroll = false;
    for (var i = 0; i < lists.length; i++) {
      lists[i].style.minHeight = listH + 'px';
      lists[i].style.maxHeight = listH + 'px';
      if (lists[i].scrollHeight > lists[i].clientHeight + 2) needsScroll = true;
    }
    var needsX = board.scrollWidth > container.clientWidth + 2;
    container.style.overflowX = needsX ? 'auto' : 'hidden';
    container.style.overflowY = 'hidden';
    container.classList.toggle('kb-board-fit', !needsX && !needsScroll);
    for (var j = 0; j < lists.length; j++) {
      lists[j].style.overflowY = needsScroll ? 'auto' : 'hidden';
    }
  }
  function paintColumnStats() {
    var rows = document.querySelectorAll('.kb-column-stats');
    if (!rows.length) return;
    var dark = document.documentElement.classList.contains('kb-dark')
      || (document.body && document.body.classList.contains('kb-dark'));
    for (var r = 0; r < rows.length; r++) {
      var stats = rows[r].querySelectorAll('.kb-column-stat');
      var n = stats.length;
      if (!n) continue;
      for (var i = 0; i < n; i++) {
        var t = n === 1 ? 1 : i / (n - 1);
        var rgb = stageColor(t, dark);
        var aBg = dark ? 0.28 : 0.16;
        var aBorder = dark ? 0.55 : 0.38;
        var bg = 'rgba(' + rgb[0] + ',' + rgb[1] + ',' + rgb[2] + ',' + aBg + ')';
        var border = 'rgba(' + rgb[0] + ',' + rgb[1] + ',' + rgb[2] + ',' + aBorder + ')';
        var color = dark
          ? 'rgb(' + Math.min(255, rgb[0] + 90) + ',' + Math.min(255, rgb[1] + 90) + ',' + Math.min(255, rgb[2] + 90) + ')'
          : 'rgb(' + Math.max(0, rgb[0] - 45) + ',' + Math.max(0, rgb[1] - 45) + ',' + Math.max(0, rgb[2] - 45) + ')';
        var el = stats[i];
        el.style.setProperty('background', bg, 'important');
        el.style.setProperty('border-color', border, 'important');
        el.style.setProperty('color', color, 'important');
        var strong = el.querySelector('strong');
        var small = el.querySelector('small');
        if (strong) strong.style.setProperty('color', color, 'important');
        if (small) small.style.setProperty('color', color, 'important');
      }
    }
  }
  function refreshBoardChrome() {
    fillBoardHeight();
    paintBoardColumns();
    paintColumnStats();
  }
  function polishModal() {
    var box = document.getElementById('modal-box');
    var content = document.getElementById('modal-content');
    if (!box || !content) return;
    var width = String(box.style.width || '');
    if (width.indexOf('1350') !== -1 || width.indexOf('98%') !== -1) {
      box.setAttribute('data-kb-size', 'large');
    } else if (width.indexOf('1024') !== -1 || width.indexOf('70%') !== -1) {
      box.setAttribute('data-kb-size', 'medium');
    } else {
      box.setAttribute('data-kb-size', 'small');
    }
    content.classList.add('kb-modal-ready');
    var requiredMarks = content.querySelectorAll('input[required] + .form-required, select[required] + .form-required, textarea[required] + .form-required');
    for (var i = 0; i < requiredMarks.length; i++) {
      var mark = requiredMarks[i];
      var field = mark.previousElementSibling;
      if (!field || !field.id) continue;
      var label = content.querySelector('label[for="' + field.id + '"]');
      if (label && !label.querySelector('.form-required')) {
        label.appendChild(mark);
      } else {
        mark.style.display = 'none';
      }
    }
    var actions = content.querySelectorAll('.form-actions');
    for (var a = 0; a < actions.length; a++) {
      var el = actions[a];
      if (el.getAttribute('data-kb-polished') === '1') continue;
      el.setAttribute('data-kb-polished', '1');
      el.classList.add('kb-form-actions');
      var cancel = el.querySelector('a');
      if (cancel) {
        cancel.classList.add('kb-btn-secondary');
        var nodes = Array.prototype.slice.call(el.childNodes);
        for (var n = 0; n < nodes.length; n++) {
          if (nodes[n].nodeType === 3 && nodes[n].textContent.trim()) {
            nodes[n].textContent = '';
          }
        }
      }
    }
    var labels = content.querySelectorAll('form > label, fieldset > label, .kb-field > label, .form-columns label, .form-inline > label');
    for (var L = 0; L < labels.length; L++) {
      var lb = labels[L];
      if (lb.querySelector('input[type="checkbox"], input[type="radio"]')) {
        lb.classList.add('kb-check-label');
      }
    }
    polishModalTitle(content);
    buildModalTopbar(box, content);
    polishUploadModal(box, content);
    mergeAttachZones(content);
  }
  function polishUploadModal(box, content) {
    var drop = content.querySelector('#file-dropzone');
    if (!drop) {
      box.classList.remove('kb-upload-modal');
      content.classList.remove('kb-upload-content');
      return;
    }
    box.classList.add('kb-upload-modal');
    content.classList.add('kb-upload-content');
    if (content.querySelector('.kb-upload-hint')) return;
    var host = content.querySelector('.js-file-upload');
    var oversize = '';
    if (host) {
      try {
        var params = JSON.parse(host.getAttribute('data-params') || '{}');
        oversize = params.labelOversize || '';
      } catch (err) {}
    }
    if (!oversize) return;
    var hint = document.createElement('p');
    hint.className = 'form-help kb-upload-hint';
    hint.textContent = oversize;
    if (drop.parentNode) {
      drop.parentNode.insertBefore(hint, drop.nextSibling);
    }
  }
  function escapeHtml(value) {
    return String(value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }
  function polishModalTitle(content) {
    var header = content.querySelector('.page-header');
    var h2 = header && header.querySelector('h2');
    if (!h2 || h2.getAttribute('data-kb-title') === '1') return;
    var raw = (h2.textContent || '').replace(/\s+/g, ' ').trim();
    if (!raw) return;
    var parts = raw.split(/\s*>\s*/);
    header.classList.add('kb-modal-title-wrap');
    if (parts.length >= 2) {
      h2.innerHTML =
        '<span class="kb-modal-eyebrow">' + escapeHtml(parts[0]) + '</span>' +
        '<span class="kb-modal-title">' + escapeHtml(parts.slice(1).join(' › ')) + '</span>';
    } else {
      h2.innerHTML = '<span class="kb-modal-title">' + escapeHtml(raw) + '</span>';
    }
    h2.setAttribute('data-kb-title', '1');
  }
  function buildModalTopbar(box, content) {
    var header = document.getElementById('modal-header');
    if (!header) return;
    var closeBtn = header.querySelector('#modal-close-button');
    var pageHeader = content.querySelector('.page-header');
    var eyebrow = '';
    var title = '';
    if (pageHeader) {
      var eyebrowEl = pageHeader.querySelector('.kb-modal-eyebrow');
      var titleEl = pageHeader.querySelector('.kb-modal-title');
      if (eyebrowEl) eyebrow = eyebrowEl.textContent || '';
      if (titleEl) title = titleEl.textContent || '';
      if (!title) {
        var h2 = pageHeader.querySelector('h2');
        title = ((h2 && h2.textContent) || '').replace(/\s+/g, ' ').trim();
      }
    }
    if (!title) {
      title = 'Dialog';
    }
    header.className = 'kb-modal-topbar';
    header.innerHTML = '';
    var main = document.createElement('div');
    main.className = 'kb-modal-topbar-main';
    if (eyebrow) {
      var eye = document.createElement('span');
      eye.className = 'kb-modal-eyebrow';
      eye.textContent = eyebrow;
      main.appendChild(eye);
    }
    var titleNode = document.createElement('span');
    titleNode.className = 'kb-modal-title';
    titleNode.textContent = title;
    main.appendChild(titleNode);
    header.appendChild(main);
    var actions = document.createElement('div');
    actions.className = 'kb-modal-topbar-actions';
    if (closeBtn) {
      actions.appendChild(closeBtn);
    } else {
      var fallbackClose = document.createElement('a');
      fallbackClose.href = '#';
      fallbackClose.id = 'modal-close-button';
      fallbackClose.innerHTML = '<i class="fa fa-times"></i>';
      fallbackClose.addEventListener('click', function (e) {
        e.preventDefault();
        try {
          if (window.KB && KB.modal) KB.modal.close();
        } catch (err) {}
      });
      actions.appendChild(fallbackClose);
    }
    header.appendChild(actions);
    box.classList.add('kb-has-topbar');
  }
  function mergeAttachZones(content) {
    var panels = content.querySelectorAll('.accordion-content');
    for (var i = 0; i < panels.length; i++) {
      var panel = panels[i];
      if (panel.getAttribute('data-kb-merged') === '1') continue;
      var shot = panel.querySelector('#screenshot-zone');
      var drop = panel.querySelector('#file-dropzone');
      if (!shot || !drop) continue;
      var wrap = document.createElement('div');
      wrap.className = 'kb-attach-merged';
      while (panel.firstChild) {
        wrap.appendChild(panel.firstChild);
      }
      panel.appendChild(wrap);
      panel.setAttribute('data-kb-merged', '1');
    }
  }
  var modalPolishTimer = null;
  function onModalRendered() {
    if (modalPolishTimer) clearTimeout(modalPolishTimer);
    modalPolishTimer = setTimeout(polishModal, 20);
  }
  applyTheme();
  applyAccentColor();
  refreshBoardChrome();
  window.addEventListener('resize', refreshBoardChrome);
  try {
    var mq = window.matchMedia('(prefers-color-scheme: dark)');
    var onChange = function () {
      if (normalize(readDeclaredTheme()) === 'auto') {
        applyTheme();
        applyAccentColor();
        refreshBoardChrome();
      }
    };
    if (typeof mq.addEventListener === 'function') mq.addEventListener('change', onChange);
    else if (typeof mq.addListener === 'function') mq.addListener(onChange);
  } catch (e) {}
  document.addEventListener('DOMContentLoaded', function () {
    applyAccentColor();
    refreshBoardChrome();
  });
  setTimeout(function () {
    applyAccentColor();
    refreshBoardChrome();
  }, 50);
  setTimeout(refreshBoardChrome, 300);
  function bindModalHooks() {
    try {
      if (window.KB && typeof KB.on === 'function') {
        KB.on('modal.afterRender', onModalRendered);
        KB.on('modal.open', onModalRendered);
        return true;
      }
    } catch (e2) {}
    return false;
  }
  if (!bindModalHooks()) {
    document.addEventListener('DOMContentLoaded', bindModalHooks);
    setTimeout(bindModalHooks, 500);
  }
  document.addEventListener('click', function (ev) {
    var t = ev.target;
    if (!t) return;
    if (t.closest && t.closest('.js-modal-medium, .js-modal-large, .js-modal-small, .js-modal-confirm, a[href*="ProjectCreation"], .btn-blue')) {
      setTimeout(onModalRendered, 120);
      setTimeout(onModalRendered, 400);
    }
  }, true);
})();
