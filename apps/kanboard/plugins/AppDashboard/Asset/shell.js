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
    var dark = document.body.classList.contains('kb-dark') || document.documentElement.classList.contains('kb-dark');
    var circle = document.body.classList.contains('kb-circle');
    for (var i = 0; i < n; i++) {
      var todo = isTodoColumn(headers[i]);
      if (todo) renameTodoColumnLabel(headers[i]);
      if (todo) headers[i].classList.add('kb-column-todo');
      var bg, border, color;
      if (circle) {
        bg = dark ? '#101011' : '#ffffff';
        border = dark ? '#2c2c30' : '#ececee';
        color = dark ? '#fafafa' : '#18181b';
      } else {
        var rgb = todo ? [220, 38, 38] : stageColor(n === 1 ? 1 : i / (n - 1), dark);
        bg = 'rgba(' + rgb[0] + ',' + rgb[1] + ',' + rgb[2] + ',' + (dark ? (todo ? '0.34' : '0.28') : (todo ? '0.22' : '0.20')) + ')';
        border = 'rgba(' + rgb[0] + ',' + rgb[1] + ',' + rgb[2] + ',' + (dark ? '0.62' : '0.48') + ')';
        color = todo
          ? (dark ? '#fecaca' : '#b91c1c')
          : (dark
            ? 'rgb(' + Math.min(255, rgb[0] + 80) + ',' + Math.min(255, rgb[1] + 80) + ',' + Math.min(255, rgb[2] + 80) + ')'
            : 'rgb(' + Math.max(0, rgb[0] - 40) + ',' + Math.max(0, rgb[1] - 40) + ',' + Math.max(0, rgb[2] - 40) + ')');
      }
      headers[i].style.setProperty('background', bg, 'important');
      headers[i].style.setProperty('border-color', border, 'important');
      headers[i].style.setProperty('color', color, 'important');
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
  function setBoardHeight(board, container, lists, h) {
    container.style.minHeight = h + 'px';
    board.style.minHeight = h + 'px';
    var headerRow = board.querySelector('tr:first-child');
    var headerH = headerRow ? headerRow.getBoundingClientRect().height : 48;
    var listH = Math.max(200, h - headerH - 8);
    for (var i = 0; i < lists.length; i++) {
      lists[i].style.minHeight = listH + 'px';
      lists[i].style.maxHeight = listH + 'px';
    }
  }
  function fillBoardHeight() {
    var board = document.getElementById('board');
    var container = document.getElementById('board-container');
    if (!board || !container) return;
    var view = document.getElementById('kb-view-board') || container.parentElement;
    var lists = board.querySelectorAll('.board-task-list.board-column-expanded');
    var top = container.getBoundingClientRect().top + window.scrollY;
    // Account for the view paddings so the board never spills past the viewport.
    var bottomPad = 0;
    if (view) {
      var vs = window.getComputedStyle(view);
      bottomPad = (parseFloat(vs.paddingBottom) || 0) + (parseFloat(vs.borderBottomWidth) || 0);
    }
    var h = Math.max(280, window.innerHeight - top - bottomPad);
    setBoardHeight(board, container, lists, h);
    for (var pass = 0; pass < 3; pass++) {
      var over = document.documentElement.scrollHeight - window.innerHeight;
      if (over <= 0) break;
      h = Math.max(280, h - over);
      setBoardHeight(board, container, lists, h);
    }
    var needsScroll = false;
    for (var i = 0; i < lists.length; i++) {
      if (lists[i].scrollHeight > lists[i].clientHeight + 2) needsScroll = true;
    }
    var needsX = board.scrollWidth > container.clientWidth + 2;
    container.style.overflowX = needsX ? 'auto' : 'hidden';
    container.style.overflowY = 'hidden';
    container.classList.toggle('kb-board-fit', !needsX && !needsScroll);
    for (var j = 0; j < lists.length; j++) {
      lists[j].style.overflowY = needsScroll ? 'auto' : 'hidden';
    }
    // Hide the outer scrollbar as well when the whole board already fits.
    if (view) {
      var needsViewY = view.scrollHeight > view.clientHeight + 2;
      view.style.overflowY = needsViewY ? 'auto' : 'hidden';
      view.style.overflowX = view.scrollWidth > view.clientWidth + 2 ? 'auto' : 'hidden';
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
    polishActivityDates(content);
    buildModalTopbar(box, content);
    polishUploadModal(box, content);
    mergeAttachZones(content);
  }
  function polishActivityDates(content) {
    var events = content.querySelectorAll('.activity-event');
    for (var i = 0; i < events.length; i++) {
      var event = events[i];
      var date = event.querySelector('.activity-title .activity-date');
      var description = event.querySelector('.activity-description');
      if (!date || !description) continue;
      var row = description.querySelector('.kb-activity-meta-row');
      if (!row) {
        row = document.createElement('div');
        row.className = 'kb-activity-meta-row';
        description.insertBefore(row, description.firstChild);
        var taskTitle = description.querySelector('.activity-task-title');
        if (taskTitle) row.appendChild(taskTitle);
      }
      row.appendChild(date);
    }
  }
  function polishUploadModal(box, content) {
    var drop = content.querySelector('#file-dropzone');
    if (!drop || content.querySelector('.task-form-container')) {
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
  /* ===== Shared custom dropdown for every <select> ===== */
  function getSelectLabel(select) {
    var opt = select.options[select.selectedIndex];
    return opt ? opt.textContent.replace(/\s+/g, ' ').trim() : '';
  }
  function openMenuAt($menu, trigger, dirPref) {
    var r = trigger.getBoundingClientRect();
    var vw = document.documentElement.clientWidth || window.innerWidth;
    var vh = document.documentElement.clientHeight || window.innerHeight;
    $menu.css({ visibility: 'hidden', display: 'block', left: 0, top: 0 });
    var mw = $menu.outerWidth();
    var mh = $menu.outerHeight();
    var left = dirPref === 'left' ? r.left : r.right - mw;
    if (left < 8) left = 8;
    if (left + mw > vw - 8) left = vw - 8 - mw;
    var up = r.bottom + mh + 12 > vh && r.top - mh - 12 > 0;
    var top = up ? r.top - mh - 6 : r.bottom + 6;
    if (top < 8) top = 8;
    $menu.toggleClass('kb-menu-up', !!up)
      .css({ visibility: 'visible', display: 'block', left: Math.round(left), top: Math.round(top) });
  }
  function enhanceSelect(select) {
    if (!select || select.getAttribute('data-kb-select') === '1') return;
    if (select.classList.contains('ui-timepicker-select')) return;
    if (select.classList.contains('kb-quick-select') || select.classList.contains('kb-quick-select-native')) return;
    if (select.multiple || select.size > 1) return;
    var rect = select.getBoundingClientRect();
    if (rect.width === 0 && rect.height === 0) return;
    select.setAttribute('data-kb-select', '1');
    var wrap = document.createElement('span');
    wrap.className = 'kb-select';
    var trigger = document.createElement('button');
    trigger.type = 'button';
    trigger.className = 'kb-select-trigger';
    trigger.setAttribute('aria-haspopup', 'listbox');
    trigger.setAttribute('aria-expanded', 'false');
    var value = document.createElement('span');
    value.className = 'kb-select-value';
    value.textContent = getSelectLabel(select);
    trigger.appendChild(value);
    var caret = document.createElement('span');
    caret.className = 'kb-select-caret';
    trigger.appendChild(caret);
    select.parentNode.insertBefore(wrap, select);
    wrap.appendChild(trigger);
    wrap.appendChild(select);
    select.classList.add('kb-select-native');
    function sync() {
      value.textContent = getSelectLabel(select);
      if ($menu) {
        $menu.find('.kb-menu-item').each(function () {
          $(this).toggleClass('kb-menu-selected', String($(this).data('value')) === String(select.value));
        });
      }
    }
    var $menu = null;
    var menuUid = 0;
    function close() {
      if ($menu) {
        $menu.remove();
        $menu = null;
      }
      wrap.classList.remove('open');
      trigger.setAttribute('aria-expanded', 'false');
      $(document).off('mousedown.kb-select-' + menuUid);
      $(window).off('resize.kb-select-' + menuUid + ' scroll.kb-select-' + menuUid);
    }
    function open() {
      if ($menu) { close(); return; }
      $menu = $('<ul class="kb-menu kb-menu-select" role="listbox"></ul>');
      var dirPref = 'right';
      if (wrap.closest('.kb-modal-form, form, #modal-content').length) dirPref = 'left';
      $(select.options).each(function () {
        var opt = this;
        var item = $('<li class="kb-menu-item" role="option"></li>')
          .text(opt.textContent.replace(/\s+/g, ' ').trim())
          .attr('data-value', opt.value);
        if (opt.selected) item.addClass('kb-menu-selected');
        item.on('mousedown', function (e) { e.preventDefault(); })
          .on('click', function (e) {
            e.preventDefault();
            select.value = opt.value;
            $(select).trigger('change');
            sync();
            close();
            trigger.focus();
          });
        $menu.append(item);
      });
      $('body').append($menu);
      wrap.classList.add('open');
      trigger.setAttribute('aria-expanded', 'true');
      menuUid = ++kbSelectUid;
      openMenuAt($menu, trigger, dirPref);
      $(document).on('mousedown.kb-select-' + menuUid, function (e) {
        if (!$(e.target).closest('.kb-menu, .kb-select').length) close();
      });
      $(window).on('resize.kb-select-' + menuUid + ' scroll.kb-select-' + menuUid, close);
    }
    trigger.addEventListener('click', function (e) {
      e.preventDefault();
      e.stopPropagation();
      open();
    });
    trigger.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        open();
      } else if (e.key === 'Escape') {
        close();
      }
    });
    sync();
  }
  var kbSelectUid = 0;
  function enhanceAllSelects(root) {
    var scope = root && root.querySelectorAll ? root : document;
    var selects = scope.querySelectorAll('select');
    for (var i = 0; i < selects.length; i++) {
      try { enhanceSelect(selects[i]); } catch (e) {}
    }
  }
  /* ===== Project list frame (no scrollbars) ===== */
  function frameProjectLists(root) {
    var scope = root && root.querySelectorAll ? root : document;
    var lists = scope.querySelectorAll('section.page .table-list');
    for (var i = 0; i < lists.length; i++) {
      lists[i].classList.add('kb-frame');
    }
  }
  var kbMoScheduled = false;
  function kbMoPolish() {
    if (kbMoScheduled) return;
    kbMoScheduled = true;
    setTimeout(function () {
      kbMoScheduled = false;
      enhanceAllSelects(document);
      frameProjectLists(document);
    }, 60);
  }
  var kbEnhanceTimer = null;
  function scheduleEnhance() {
    if (kbEnhanceTimer) clearTimeout(kbEnhanceTimer);
    kbEnhanceTimer = setTimeout(function () {
      kbEnhanceTimer = null;
      enhanceAllSelects(document);
      frameProjectLists(document);
    }, 50);
  }
  document.addEventListener('click', function (ev) {
    var t = ev.target;
    if (!t) return;
    var modalLink = t.closest && t.closest('a.js-modal-medium, a.js-modal-large, a.js-modal-small, a.js-modal-confirm');
    if (modalLink && t.closest('svg') && window.KB && KB.modal) {
      ev.preventDefault();
      ev.stopImmediatePropagation();
      var modalUrl = modalLink.getAttribute('href');
      var modalSize = modalLink.classList.contains('js-modal-large') ? 'large' : (modalLink.classList.contains('js-modal-small') ? 'small' : 'medium');
      if (modalLink.classList.contains('js-modal-confirm')) modalSize = 'small';
      if (modalLink.classList.contains('js-modal-medium') && KB.modal.isOpen()) KB.modal.replace(modalUrl);
      else KB.modal.open(modalUrl, modalSize, modalLink.classList.contains('js-modal-confirm'));
      return;
    }
    if (t.closest && t.closest('.js-modal-medium, .js-modal-large, .js-modal-small, .js-modal-confirm, a[href*="ProjectCreation"], .btn-blue')) {
      setTimeout(onModalRendered, 120);
      setTimeout(onModalRendered, 400);
      scheduleEnhance();
    }
  }, true);
  try {
    var kbMo = new MutationObserver(kbMoPolish);
    kbMo.observe(document.documentElement, { childList: true, subtree: true, attributes: true, attributeFilter: ['style', 'class', 'hidden'] });
  } catch (e) {}
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', scheduleEnhance);
  } else {
    scheduleEnhance();
  }
  function setSidebarCollapsed(collapsed) {
    document.body.classList.toggle('kb-sidebar-collapsed', !!collapsed);
    try {
      localStorage.setItem('kb-sidebar-collapsed', collapsed ? '1' : '0');
    } catch (e) {}
  }
  function isPhoneSidebar() {
    try {
      return window.matchMedia('(max-width: 640px)').matches;
    } catch (e) {
      return false;
    }
  }
  function initSidebarToggle() {
    var collapse = document.getElementById('kb-sidebar-collapse');
    var fab = document.getElementById('kb-sidebar-fab');
    var backdrop = document.getElementById('kb-sidebar-backdrop');
    function openSidebar() { setSidebarCollapsed(false); }
    function closeSidebar() {
      if (!isPhoneSidebar()) return;
      setSidebarCollapsed(true);
    }
    if (collapse) collapse.addEventListener('click', closeSidebar);
    if (fab) fab.addEventListener('click', openSidebar);
    if (backdrop) backdrop.addEventListener('click', closeSidebar);
    document.addEventListener('keydown', function (e) {
      if ((e.metaKey || e.ctrlKey) && String(e.key || '').toLowerCase() === 'b') {
        if (!isPhoneSidebar()) return;
        e.preventDefault();
        setSidebarCollapsed(!document.body.classList.contains('kb-sidebar-collapsed'));
      }
    });
    document.addEventListener('click', function (e) {
      var t = e.target;
      if (!t || !t.closest) return;
      var discard = t.closest('[data-kb-plane-discard]');
      if (discard) {
        e.preventDefault();
        try { if (window.KB && KB.modal) KB.modal.close(); } catch (err) {}
        var c = document.getElementById('modal-close-button');
        if (c) c.click();
      }
    });
    // Phone only: auto-collapse. Tablet/desktop keep sidebar visible.
    if (isPhoneSidebar()) {
      setSidebarCollapsed(true);
    } else {
      try {
        if (localStorage.getItem('kb-sidebar-collapsed') !== '1') {
          setSidebarCollapsed(false);
        }
      } catch (e2) {
        setSidebarCollapsed(false);
      }
    }
    try {
      var mq = window.matchMedia('(max-width: 640px)');
      var onMq = function () {
        if (isPhoneSidebar()) setSidebarCollapsed(true);
        else setSidebarCollapsed(false);
      };
      if (typeof mq.addEventListener === 'function') mq.addEventListener('change', onMq);
      else if (typeof mq.addListener === 'function') mq.addListener(onMq);
    } catch (e3) {}
  }
  function tightenMemberAutocomplete() {
    try {
      if (!window.jQuery) return;
      jQuery('.kb-member-email, .kb-assignee-search').each(function () {
        var $el = jQuery(this);
        if ($el.data('ui-autocomplete')) $el.autocomplete('option', 'minLength', 2);
      });
    } catch (e) {}
  }
  function bootShellUi() {
    initSidebarToggle();
    setTimeout(tightenMemberAutocomplete, 0);
    setTimeout(tightenMemberAutocomplete, 400);
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootShellUi);
  } else {
    bootShellUi();
  }

  /* Soft navigation: keep sidebar mounted when switching projects / views */
  var softNavBusy = false;
  function softNavShouldHandle(a) {
    if (!a || softNavBusy) return false;
    if (a.target === '_blank' || a.hasAttribute('download')) return false;
    if (a.classList.contains('js-modal-medium') || a.classList.contains('js-modal-large') || a.classList.contains('js-modal-small') || a.classList.contains('js-modal-confirm')) return false;
    var href = a.getAttribute('href') || '';
    if (!href || href.charAt(0) === '#' || href.indexOf('javascript:') === 0) return false;
    if (href.indexOf('/apps/kanboard') === -1 && href.indexOf('controller=') === -1) return false;
    if (/controller=(AuthController|UserAjaxController|TaskCreationController|ProjectCreationController|TaskFileController|CommentController)/i.test(href)) return false;
    return /controller=(BoardViewController|DashboardController|ProjectViewController|ProjectOverviewController|TaskListController|TaskViewController|ProjectPermissionController|AnalyticController|SearchController)/i.test(href)
      || a.classList.contains('kb-cycle-view-link')
      || (a.closest && a.closest('#kb-app-sidebar') && !a.classList.contains('js-modal-medium') && !a.classList.contains('js-modal-large'));
  }
  function softNavMarkActive(url) {
    var side = document.getElementById('kb-app-sidebar');
    if (!side) return;
    var links = side.querySelectorAll('a[href]');
    var u;
    try { u = new URL(url, location.origin); } catch (e) { return; }
    var pid = u.searchParams.get('project_id') || '';
    for (var i = 0; i < links.length; i++) {
      var li = links[i].closest('li');
      if (!li) continue;
      var hu;
      try { hu = new URL(links[i].href, location.origin); } catch (e2) { continue; }
      var match = false;
      if (pid && hu.searchParams.get('project_id') === pid && /BoardViewController/i.test(hu.search)) match = true;
      else if (!pid && hu.pathname + hu.search === u.pathname + u.search) match = true;
      li.classList.toggle('active', match);
    }
  }
  function softNavReboot(root) {
    try {
      if (window.jQuery) {
        jQuery('.autocomplete, .kb-member-email, .kb-assignee-search', root || document).each(function () {
          var $el = jQuery(this);
          if ($el.data('ui-autocomplete')) {
            try { $el.autocomplete('destroy'); } catch (eD) {}
          }
        });
      }
    } catch (eDestroy) {}
    try {
      if (window.KB && typeof KB.trigger === 'function') KB.trigger('dom.ready');
    } catch (e0) {}
    try {
      if (window._KB) {
        if (typeof _KB.datePicker === 'function') _KB.datePicker();
        if (typeof _KB.autoComplete === 'function') _KB.autoComplete();
        if (typeof _KB.tagAutoComplete === 'function') _KB.tagAutoComplete();
        tightenMemberAutocomplete();
        if (typeof _KB.execute === 'function') {
          // refresh board controllers without rebinding global listeners
          for (var key in _KB.controllers || {}) {
            var c = _KB.controllers[key];
            if (c && typeof c.onBoardRendered === 'function') {
              try { c.onBoardRendered(); } catch (eB) {}
            }
          }
        }
      }
    } catch (e) {}
    try {
      var ev = document.createEvent('Event');
      ev.initEvent('kb:softnav', true, true);
      document.dispatchEvent(ev);
    } catch (e3) {}
    try {
      if (document.querySelector('.kb-cycle-workspace')) document.body.classList.add('kb-cycle-page');
      else document.body.classList.remove('kb-cycle-page');
      var canvas = document.getElementById('kb-burnup-chart');
      if (canvas && window.Event) window.dispatchEvent(new Event('resize'));
    } catch (e4) {}
    try {
      if (root && window.KB && typeof KB.render === 'function') KB.render();
    } catch (e5) {}
  }
  function softNavigate(url, push) {
    if (softNavBusy) return;
    softNavBusy = true;
    document.body.classList.add('kb-softnav-loading');
    // Do not send X-Requested-With: Kanboard AJAX responses omit the layout
    // (.kb-app-main / sidebar). Full HTML lets us swap main and keep the sidebar.
    fetch(url, { credentials: 'same-origin', headers: { Accept: 'text/html' } })
      .then(function (r) { return r.text().then(function (t) { return { ok: r.ok, url: r.url, text: t }; }); })
      .then(function (res) {
        if (!res.ok) throw new Error('nav failed');
        var doc = new DOMParser().parseFromString(res.text, 'text/html');
        var nextMain = doc.querySelector('.kb-app-main');
        var curMain = document.querySelector('.kb-app-main');
        if (!nextMain || !curMain) {
          location.href = url;
          return;
        }
        var side = document.getElementById('kb-app-sidebar');
        curMain.innerHTML = nextMain.innerHTML;
        softNavMarkActive(res.url || url);
        if (push !== false) {
          try { history.pushState({ kbSoft: 1 }, '', res.url || url); } catch (e) {}
        }
        document.title = doc.title || document.title;
        softNavReboot(curMain);
        try {
          if (side && document.getElementById('kb-app-sidebar') === side) {
            side.setAttribute('data-kb-softnav', '1');
          }
        } catch (e2) {}
      })
      .catch(function () { location.href = url; })
      .then(function () {
        softNavBusy = false;
        document.body.classList.remove('kb-softnav-loading');
      });
  }
  document.addEventListener('click', function (e) {
    var t = e.target;
    if (!t || !t.closest) return;
    var a = t.closest('a');
    if (!softNavShouldHandle(a)) return;
    if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
    e.preventDefault();
    softNavigate(a.href, true);
  }, true);
  window.addEventListener('popstate', function () {
    softNavigate(location.href, false);
  });
})();
