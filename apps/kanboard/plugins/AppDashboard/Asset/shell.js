(function () {
  function readDeclaredTheme() {
    var vars = (document.getElementById('appdashboard-kb-vars') || {}).textContent || '';
    var m = vars.match(/--kb-shell-theme:\s*([a-z]+)/i);
    if (m && m[1]) return m[1].toLowerCase();
    var q = /[?&]theme=([a-z]+)/i.exec(location.search || '');
    return q ? q[1].toLowerCase() : 'light';
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
    document.body.classList.toggle('kb-dark', effectiveDark(theme));
    document.documentElement.setAttribute('data-kb-theme', theme);
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
  function paintBoardColumns() {
    var board = document.getElementById('board');
    if (!board) return;
    var headers = board.querySelectorAll('tr:first-child th.board-column-header');
    var n = headers.length;
    if (!n) return;
    var dark = document.body.classList.contains('kb-dark');
    for (var i = 0; i < n; i++) {
      var t = n === 1 ? 1 : i / (n - 1);
      var rgb = stageColor(t, dark);
      var bg = 'rgba(' + rgb[0] + ',' + rgb[1] + ',' + rgb[2] + ',' + (dark ? '0.28' : '0.20') + ')';
      var border = 'rgba(' + rgb[0] + ',' + rgb[1] + ',' + rgb[2] + ',' + (dark ? '0.55' : '0.42') + ')';
      var color = dark
        ? 'rgb(' + Math.min(255, rgb[0] + 80) + ',' + Math.min(255, rgb[1] + 80) + ',' + Math.min(255, rgb[2] + 80) + ')'
        : 'rgb(' + Math.max(0, rgb[0] - 40) + ',' + Math.max(0, rgb[1] - 40) + ',' + Math.max(0, rgb[2] - 40) + ')';
      headers[i].style.setProperty('background', bg, 'important');
      headers[i].style.setProperty('border-color', border, 'important');
      headers[i].style.setProperty('color', color, 'important');
      var links = headers[i].querySelectorAll('a, .board-column-title');
      for (var L = 0; L < links.length; L++) links[L].style.setProperty('color', color, 'important');
      var cells = board.querySelectorAll('tr.board-swimlane > td:nth-child(' + (i + 1) + ')');
      for (var c = 0; c < cells.length; c++) {
        cells[c].style.setProperty('background', bg, 'important');
        cells[c].style.setProperty('border-color', border, 'important');
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
    for (var i = 0; i < lists.length; i++) {
      lists[i].style.minHeight = listH + 'px';
    }
  }
  function refreshBoardChrome() {
    fillBoardHeight();
    paintBoardColumns();
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
    var labels = content.querySelectorAll('form > label, .kb-field > label, .form-columns label');
    for (var L = 0; L < labels.length; L++) {
      var lb = labels[L];
      if (lb.querySelector('input[type="checkbox"], input[type="radio"]')) {
        lb.classList.add('kb-check-label');
      }
    }
  }
  var modalPolishTimer = null;
  function onModalRendered() {
    if (modalPolishTimer) clearTimeout(modalPolishTimer);
    modalPolishTimer = setTimeout(polishModal, 20);
  }
  applyTheme();
  refreshBoardChrome();
  window.addEventListener('resize', refreshBoardChrome);
  try {
    var mq = window.matchMedia('(prefers-color-scheme: dark)');
    var onChange = function () {
      if (normalize(readDeclaredTheme()) === 'auto') {
        applyTheme();
        refreshBoardChrome();
      }
    };
    if (typeof mq.addEventListener === 'function') mq.addEventListener('change', onChange);
    else if (typeof mq.addListener === 'function') mq.addListener(onChange);
  } catch (e) {}
  document.addEventListener('DOMContentLoaded', refreshBoardChrome);
  setTimeout(refreshBoardChrome, 50);
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
