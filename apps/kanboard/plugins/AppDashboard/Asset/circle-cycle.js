(function () {
  function parseBurnup() {
    var node = document.getElementById('kb-burnup-data');
    if (!node) return [];
    try {
      return JSON.parse(node.textContent || '[]') || [];
    } catch (e) {
      return [];
    }
  }
  function drawBurnup(canvas) {
    if (!canvas || !canvas.getContext) return;
    var data = parseBurnup();
    if (!data.length) return;
    var dpr = window.devicePixelRatio || 1;
    var cssW = canvas.clientWidth || 360;
    var cssH = canvas.clientHeight || 160;
    canvas.width = Math.floor(cssW * dpr);
    canvas.height = Math.floor(cssH * dpr);
    var ctx = canvas.getContext('2d');
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    var pad = { t: 12, r: 8, b: 24, l: 28 };
    var w = cssW - pad.l - pad.r;
    var h = cssH - pad.t - pad.b;
    var maxY = 1;
    for (var i = 0; i < data.length; i++) {
      maxY = Math.max(maxY, data[i].scope || 0, data[i].started || 0, data[i].completed || 0, data[i].ideal || 0);
    }
    maxY = maxY + 2;
    function xAt(i) {
      return pad.l + (data.length === 1 ? w / 2 : (i / (data.length - 1)) * w);
    }
    function yAt(v) {
      return pad.t + h - (v / maxY) * h;
    }
    ctx.clearRect(0, 0, cssW, cssH);
    ctx.strokeStyle = 'rgba(236,236,239,0.22)';
    ctx.lineWidth = 1;
    for (var g = 0; g < 4; g++) {
      var gy = pad.t + (h * g) / 3;
      ctx.beginPath();
      ctx.moveTo(pad.l, gy);
      ctx.lineTo(pad.l + w, gy);
      ctx.stroke();
    }
    ctx.fillStyle = 'rgba(236,236,239,0.65)';
    ctx.font = '10px ui-sans-serif, system-ui, sans-serif';
    ctx.textAlign = 'right';
    for (var gyi = 0; gyi < 4; gyi++) {
      var yv = Math.round(maxY - (maxY * gyi) / 3);
      ctx.fillText(String(yv), pad.l - 2, pad.t + (h * gyi) / 3 + 3);
    }
    ctx.textAlign = 'left';
    function line(key, color, width, dash) {
      ctx.beginPath();
      ctx.strokeStyle = color;
      ctx.lineWidth = width;
      ctx.setLineDash(dash || []);
      for (var j = 0; j < data.length; j++) {
        var x = xAt(j);
        var y = yAt(data[j][key] || 0);
        if (j === 0) ctx.moveTo(x, y);
        else ctx.lineTo(x, y);
      }
      ctx.stroke();
      ctx.setLineDash([]);
    }
    var grad = ctx.createLinearGradient(0, pad.t, 0, pad.t + h);
    grad.addColorStop(0, 'rgba(103,113,197,0.45)');
    grad.addColorStop(1, 'rgba(103,113,197,0.02)');
    ctx.beginPath();
    for (var k = 0; k < data.length; k++) {
      var x2 = xAt(k);
      var y2 = yAt(data[k].completed || 0);
      if (k === 0) ctx.moveTo(x2, y2);
      else ctx.lineTo(x2, y2);
    }
    ctx.lineTo(xAt(data.length - 1), pad.t + h);
    ctx.lineTo(xAt(0), pad.t + h);
    ctx.closePath();
    ctx.fillStyle = grad;
    ctx.fill();
    line('ideal', 'rgba(103,113,197,0.55)', 1.25, [4, 4]);
    line('scope', '#8f9299', 1.5);
    line('started', '#facc15', 1.5);
    line('completed', '#6771c5', 1.85);
    ctx.fillStyle = 'rgba(236,236,239,0.72)';
    ctx.font = '11px ui-sans-serif, system-ui, sans-serif';
    var ticks = [0, Math.floor((data.length - 1) / 2), data.length - 1];
    for (var t = 0; t < ticks.length; t++) {
      var idx = ticks[t];
      if (idx < 0 || idx >= data.length) continue;
      var label = data[idx].label || data[idx].date || '';
      ctx.fillText(label, xAt(idx) - 14, cssH - 8);
    }
    ctx.strokeStyle = 'rgba(236,236,239,0.14)';
    ctx.beginPath();
    ctx.moveTo(pad.l, pad.t + h);
    ctx.lineTo(pad.l + w, pad.t + h);
    ctx.stroke();
  }

  var STATUS_ICONS = {
    backlog: '<svg class="kb-st-icon kb-st-backlog" viewBox="0 0 16 16" aria-hidden="true"><circle cx="8" cy="8" r="5.25" fill="none" stroke="currentColor" stroke-width="1.5" stroke-dasharray="2.2 2"/></svg>',
    todo: '<svg class="kb-st-icon kb-st-todo" viewBox="0 0 16 16" aria-hidden="true"><circle cx="8" cy="8" r="5.25" fill="none" stroke="currentColor" stroke-width="1.5"/></svg>',
    progress: '<svg class="kb-st-icon kb-st-progress" viewBox="0 0 16 16" aria-hidden="true"><circle cx="8" cy="8" r="5.25" fill="none" stroke="currentColor" stroke-width="1.5"/><path d="M8 2.75a5.25 5.25 0 0 1 0 10.5Z" fill="currentColor"/></svg>',
    review: '<svg class="kb-st-icon kb-st-review" viewBox="0 0 16 16" aria-hidden="true"><circle cx="8" cy="8" r="5.25" fill="none" stroke="currentColor" stroke-width="1.5"/><path d="m5.6 8.2 1.7 1.7 3.2-3.4" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>',
    done: '<svg class="kb-st-icon kb-st-done" viewBox="0 0 16 16" aria-hidden="true"><circle cx="8" cy="8" r="6" fill="currentColor"/><path d="m5.4 8.1 1.8 1.8 3.5-3.7" fill="none" stroke="#0f0f12" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>'
  };

  function prioIconSvg(priority, min, max) {
    var span = Math.max(1, max - min);
    var level = Math.round(((priority - min) / span) * 3);
    level = Math.max(0, Math.min(3, level));
    var colors = ['#94a3b8', '#3b82f6', '#f59e0b', '#ef4444'];
    var color = colors[level];
    var bars = '';
    for (var i = 0; i < 3; i++) {
      var h = 4 + i * 3;
      var y = 14 - h;
      var x = 3 + i * 4;
      var fill = level === 0 ? 'rgba(148,163,184,0.35)' : (i < level ? color : 'rgba(148,163,184,0.28)');
      bars += '<rect x="' + x + '" y="' + y + '" width="2.5" height="' + h + '" rx="0.6" fill="' + fill + '"/>';
    }
    return '<svg class="kb-prio-icon kb-prio-' + level + '" viewBox="0 0 16 16" width="14" height="14" aria-hidden="true">' + bars + '</svg>';
  }

  function applyBoardTints() {
    var mapNode = document.getElementById('kb-board-status-map');
    var board = document.getElementById('kb-view-board');
    if (!mapNode || !board) return;
    var map = {};
    try {
      map = JSON.parse(mapNode.textContent || '{}') || {};
    } catch (e) {
      return;
    }
    var headers = board.querySelectorAll('.board-column-header[data-column-id]');
    for (var i = 0; i < headers.length; i++) {
      var id = String(headers[i].getAttribute('data-column-id') || '');
      var key = map[id] || map[Number(id)] || '';
      if (!key) continue;
      headers[i].classList.add('kb-tint-' + key);
      var cells = board.querySelectorAll('.board-column-' + id);
      for (var c = 0; c < cells.length; c++) {
        cells[c].classList.add('kb-tint-' + key);
      }
      var lists = board.querySelectorAll('.board-task-list[data-column-id="' + id + '"]');
      for (var l = 0; l < lists.length; l++) {
        lists[l].classList.add('kb-tint-' + key);
      }
    }
  }

  function sortTable(table, key, dir) {
    var tbody = table.tBodies[0];
    if (!tbody) return;
    var rows = Array.prototype.slice.call(tbody.querySelectorAll('tr.kb-table-row'));
    rows.sort(function (a, b) {
      var av = a.getAttribute('data-' + key) || '';
      var bv = b.getAttribute('data-' + key) || '';
      var an = Number(av);
      var bn = Number(bv);
      var cmp;
      if (!isNaN(an) && !isNaN(bn) && String(av).trim() !== '' && String(bv).trim() !== '' && /^-?\d+(\.\d+)?$/.test(String(av)) && /^-?\d+(\.\d+)?$/.test(String(bv))) {
        cmp = an - bn;
      } else {
        cmp = String(av).toLowerCase().localeCompare(String(bv).toLowerCase(), undefined, { numeric: true, sensitivity: 'base' });
      }
      return dir === 'desc' ? -cmp : cmp;
    });
    for (var i = 0; i < rows.length; i++) tbody.appendChild(rows[i]);
  }

  var tableSortState = { key: null, dir: 'asc' };
  var tableSortBound = false;
  function initTableSort() {
    if (!tableSortBound) {
      tableSortBound = true;
      document.addEventListener('click', function (e) {
        var th = e.target && e.target.closest ? e.target.closest('#kb-view-table th.is-sortable') : null;
        if (!th) return;
        var table = document.getElementById('kb-view-table');
        if (!table) return;
        var key = th.getAttribute('data-sort');
        if (!key) return;
        if (tableSortState.key === key) tableSortState.dir = tableSortState.dir === 'asc' ? 'desc' : 'asc';
        else {
          tableSortState.key = key;
          tableSortState.dir = 'asc';
        }
        var heads = table.querySelectorAll('th.is-sortable');
        for (var j = 0; j < heads.length; j++) {
          heads[j].classList.remove('sort-asc', 'sort-desc');
        }
        th.classList.add(tableSortState.dir === 'asc' ? 'sort-asc' : 'sort-desc');
        sortTable(table, key, tableSortState.dir);
      });
    }
  }

  function quickUrl() {
    var main = document.querySelector('.kb-cycle-main[data-quick-url]');
    return main ? main.getAttribute('data-quick-url') : '';
  }

  function updateRowStatus(row, statusKey, statusTitle, columnId) {
    row.className = row.className.replace(/\bstatus-\w+/g, '').replace(/\bkb-tint-\w+/g, '');
    row.classList.add('kb-table-row', 'status-' + statusKey, 'kb-tint-' + statusKey);
    row.setAttribute('data-status-key', statusKey);
    row.setAttribute('data-status', statusTitle || statusKey);
    row.setAttribute('data-column-id', String(columnId));
    var iconWrap = row.querySelector('.kb-table-status .kb-inline-icon');
    if (iconWrap) iconWrap.innerHTML = STATUS_ICONS[statusKey] || STATUS_ICONS.todo;
    var statusCell = row.querySelector('.kb-table-status');
    if (statusCell) statusCell.setAttribute('data-value', String(statusTitle || statusKey).toLowerCase());
  }

  function updateRowPriority(row, priority) {
    row.setAttribute('data-priority', String(priority));
    var cell = row.querySelector('.kb-table-priority');
    if (cell) cell.setAttribute('data-value', String(priority));
    var iconWrap = row.querySelector('.kb-table-priority .kb-inline-icon');
    if (iconWrap) {
      var min = 0;
      var max = 3;
      var opts = row.querySelectorAll('.kb-table-priority select option');
      if (opts.length) {
        min = Number(opts[0].value);
        max = Number(opts[opts.length - 1].value);
      }
      iconWrap.innerHTML = prioIconSvg(Number(priority), min, max);
    }
  }

  function postQuickUpdate(taskId, field, value) {
    var url = quickUrl();
    if (!url) {
      var sum = document.querySelector('.kb-task-summary[data-quick-url]');
      if (sum) url = sum.getAttribute('data-quick-url') || '';
    }
    if (!url) return Promise.reject(new Error('no_url'));
    return fetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify({ task_id: taskId, field: field, value: Number(value) })
    }).then(function (r) {
      return r.json().then(function (j) {
        return { ok: r.ok && j && j.ok, json: j };
      });
    });
  }

  function enhanceIconSelect(select) {
    if (!select || select.getAttribute('data-kb-icon-select') === '1') return;
    select.setAttribute('data-kb-icon-select', '1');
    var wrap = select.closest('.kb-inline-select') || select.parentNode;
    if (!wrap) return;
    select.classList.add('kb-select-native', 'kb-quick-select-native');
    var btn = wrap.querySelector('.kb-icon-select-trigger');
    if (!btn) {
      btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'kb-icon-select-trigger';
      btn.setAttribute('aria-haspopup', 'listbox');
      wrap.insertBefore(btn, select);
    }
    function currentIcon() {
      var opt = select.options[select.selectedIndex];
      var sk = opt ? opt.getAttribute('data-status-key') : '';
      if (select.getAttribute('data-field') === 'column_id') return STATUS_ICONS[sk] || STATUS_ICONS.todo;
      if (select.getAttribute('data-field') === 'priority') {
        var min = select.options.length ? Number(select.options[0].value) : 0;
        var max = select.options.length ? Number(select.options[select.options.length - 1].value) : 3;
        return prioIconSvg(Number(select.value || 0), min, max);
      }
      return '';
    }
    function sync() {
      var opt = select.options[select.selectedIndex];
      var label = opt ? opt.textContent.replace(/\s+/g, ' ').trim() : '';
      btn.innerHTML = '<span class="kb-icon-select-ico">' + currentIcon() + '</span><span class="kb-icon-select-label">' + label + '</span><span class="kb-select-caret"></span>';
      var iconWrap = wrap.querySelector('.kb-inline-icon');
      if (iconWrap && select.getAttribute('data-field') === 'column_id') {
        var sk = opt ? opt.getAttribute('data-status-key') || 'todo' : 'todo';
        iconWrap.innerHTML = STATUS_ICONS[sk] || STATUS_ICONS.todo;
      }
    }
    var menu = null;
    function close() {
      if (menu) {
        menu.remove();
        menu = null;
      }
      wrap.classList.remove('open');
    }
    function open() {
      if (menu) {
        close();
        return;
      }
      menu = document.createElement('ul');
      menu.className = 'kb-menu kb-menu-select kb-icon-select-menu';
      menu.setAttribute('role', 'listbox');
      for (var i = 0; i < select.options.length; i++) {
        (function (opt) {
          var li = document.createElement('li');
          li.className = 'kb-menu-item' + (opt.selected ? ' kb-menu-selected' : '');
          li.setAttribute('role', 'option');
          var sk = opt.getAttribute('data-status-key') || '';
          var ico =
            select.getAttribute('data-field') === 'column_id'
              ? STATUS_ICONS[sk] || STATUS_ICONS.todo
              : select.getAttribute('data-field') === 'priority'
                ? prioIconSvg(Number(opt.value), Number(select.options[0].value), Number(select.options[select.options.length - 1].value))
                : '';
          li.innerHTML = '<span class="kb-icon-select-ico">' + ico + '</span><span>' + opt.textContent.replace(/\s+/g, ' ').trim() + '</span>';
          li.addEventListener('mousedown', function (e) {
            e.preventDefault();
          });
          li.addEventListener('click', function (e) {
            e.preventDefault();
            select.value = opt.value;
            select.dispatchEvent(new Event('change', { bubbles: true }));
            sync();
            close();
          });
          menu.appendChild(li);
        })(select.options[i]);
      }
      document.body.appendChild(menu);
      wrap.classList.add('open');
      var r = btn.getBoundingClientRect();
      menu.style.left = Math.round(r.left) + 'px';
      menu.style.top = Math.round(r.bottom + 6) + 'px';
      menu.style.minWidth = Math.max(160, Math.round(r.width)) + 'px';
      setTimeout(function () {
        document.addEventListener('mousedown', onDoc, true);
      }, 0);
      function onDoc(ev) {
        if (!menu.contains(ev.target) && ev.target !== btn && !btn.contains(ev.target)) {
          close();
          document.removeEventListener('mousedown', onDoc, true);
        }
      }
    }
    btn.addEventListener('click', function (e) {
      e.preventDefault();
      e.stopPropagation();
      open();
    });
    sync();
  }

  var quickSelectBound = false;
  function initQuickSelects() {
    var selects = document.querySelectorAll('.kb-quick-select');
    for (var i = 0; i < selects.length; i++) {
      if (!selects[i].getAttribute('data-prev')) {
        selects[i].setAttribute('data-prev', selects[i].value);
      }
      try {
        enhanceIconSelect(selects[i]);
      } catch (e0) {}
    }
    if (quickSelectBound) return;
    quickSelectBound = true;
    document.addEventListener('change', function (e) {
      var t = e.target;
      if (!t || !t.classList || !t.classList.contains('kb-quick-select')) return;
      var field = t.getAttribute('data-field');
      var taskId = Number(t.getAttribute('data-task-id') || 0);
      var value = t.value;
      if (!field || !taskId) return;
      var row = t.closest('tr.kb-table-row');
      var prev = t.getAttribute('data-prev') || t.defaultValue;
      t.setAttribute('data-prev', value);
      t.disabled = true;
      postQuickUpdate(taskId, field, value)
        .then(function (res) {
          t.disabled = false;
          if (!res.ok) {
            t.value = prev;
            try {
              enhanceIconSelect(t);
            } catch (e1) {}
            return;
          }
          if (field === 'column_id' && row) {
            var opt = t.options[t.selectedIndex];
            var sk = opt ? opt.getAttribute('data-status-key') || 'todo' : 'todo';
            var title = opt ? opt.textContent.trim() : sk;
            updateRowStatus(row, sk, title, Number(value));
          }
          if (field === 'priority' && row) {
            updateRowPriority(row, Number(value));
          }
          if (field === 'column_id' && !row) {
            var iconWrap = t.closest('.kb-inline-select');
            if (iconWrap) {
              var ic = iconWrap.querySelector('.kb-inline-icon');
              var o2 = t.options[t.selectedIndex];
              var sk2 = o2 ? o2.getAttribute('data-status-key') || 'todo' : 'todo';
              if (ic) ic.innerHTML = STATUS_ICONS[sk2] || STATUS_ICONS.todo;
            }
          }
          try {
            t.removeAttribute('data-kb-icon-select');
            enhanceIconSelect(t);
          } catch (e2) {}
        })
        .catch(function () {
          t.disabled = false;
          t.value = prev;
        });
    });
  }

  var listDnDBound = false;
  function moveTaskDomToGroup(row, targetRows, statusKey, columnId) {
    var empty = targetRows.querySelector('.kb-issue-empty');
    if (empty) empty.remove();
    targetRows.appendChild(row);
    row.setAttribute('data-column-id', String(columnId));
    row.classList.toggle('is-done', statusKey === 'done');
    var st = row.querySelector('.kb-issue-status');
    if (st) st.innerHTML = STATUS_ICONS[statusKey] || STATUS_ICONS.todo;
    var group = targetRows.closest('.kb-issue-group');
    if (group) {
      var countEl = group.querySelector('.kb-issue-group-count');
      if (countEl) countEl.textContent = String(targetRows.querySelectorAll('.kb-issue-row').length);
    }
    document.querySelectorAll('#kb-cycle-issues .kb-issue-group').forEach(function (g) {
      var rows = g.querySelector('.kb-issue-rows');
      var c = g.querySelector('.kb-issue-group-count');
      if (rows && c) c.textContent = String(rows.querySelectorAll('.kb-issue-row').length);
      if (rows && !rows.querySelector('.kb-issue-row') && !rows.querySelector('.kb-issue-empty')) {
        var hint = document.createElement('div');
        hint.className = 'kb-issue-empty kb-drop-hint';
        hint.textContent = 'Drop tasks here';
        rows.appendChild(hint);
      }
    });
  }

  function initListDnD() {
    var root = document.getElementById('kb-cycle-issues');
    if (!root || root.getAttribute('data-can-edit') !== '1') return;
    if (!listDnDBound) {
      listDnDBound = true;
      var dragRow = null;
      document.addEventListener('dragstart', function (e) {
        var row = e.target && e.target.closest ? e.target.closest('#kb-cycle-issues .kb-issue-row[draggable="true"]') : null;
        if (!row) return;
        dragRow = row;
        row.classList.add('is-dragging');
        try {
          e.dataTransfer.setData('text/plain', row.getAttribute('data-task-id') || '');
          e.dataTransfer.effectAllowed = 'move';
        } catch (err) {}
      });
      document.addEventListener('dragend', function () {
        if (dragRow) dragRow.classList.remove('is-dragging');
        dragRow = null;
        document.querySelectorAll('.kb-issue-rows.is-drop-target').forEach(function (el) {
          el.classList.remove('is-drop-target');
        });
      });
      document.addEventListener('dragover', function (e) {
        var zone = e.target && e.target.closest ? e.target.closest('#kb-cycle-issues .kb-issue-rows') : null;
        if (!zone || !dragRow) return;
        e.preventDefault();
        zone.classList.add('is-drop-target');
      });
      document.addEventListener('dragleave', function (e) {
        var zone = e.target && e.target.closest ? e.target.closest('#kb-cycle-issues .kb-issue-rows') : null;
        if (zone) zone.classList.remove('is-drop-target');
      });
      document.addEventListener('drop', function (e) {
        var zone = e.target && e.target.closest ? e.target.closest('#kb-cycle-issues .kb-issue-rows') : null;
        if (!zone || !dragRow) return;
        e.preventDefault();
        zone.classList.remove('is-drop-target');
        var columnId = Number(zone.getAttribute('data-column-id') || 0);
        var statusKey = zone.getAttribute('data-status-key') || 'todo';
        var taskId = Number(dragRow.getAttribute('data-task-id') || 0);
        var fromCol = Number(dragRow.getAttribute('data-column-id') || 0);
        if (!taskId || !columnId || columnId === fromCol) return;
        var row = dragRow;
        moveTaskDomToGroup(row, zone, statusKey, columnId);
        postQuickUpdate(taskId, 'column_id', columnId).then(function (res) {
          if (!res.ok) location.reload();
        }).catch(function () {
          location.reload();
        });
      });
    }
  }

  var tableDnDBound = false;
  function initTableDnD() {
    var table = document.getElementById('kb-view-table');
    if (!table) return;
    if (!tableDnDBound) {
      tableDnDBound = true;
      var dragRow = null;
      document.addEventListener('dragstart', function (e) {
        var row = e.target && e.target.closest ? e.target.closest('#kb-view-table tr.kb-table-row[draggable="true"]') : null;
        if (!row) return;
        dragRow = row;
        row.classList.add('is-dragging');
        try {
          e.dataTransfer.setData('text/plain', row.getAttribute('data-task-id') || '');
          e.dataTransfer.effectAllowed = 'move';
        } catch (err) {}
      });
      document.addEventListener('dragend', function () {
        if (dragRow) dragRow.classList.remove('is-dragging');
        dragRow = null;
        document.querySelectorAll('#kb-view-table tr.is-drop-target').forEach(function (el) {
          el.classList.remove('is-drop-target');
        });
      });
      document.addEventListener('dragover', function (e) {
        var row = e.target && e.target.closest ? e.target.closest('#kb-view-table tr.kb-table-row') : null;
        if (!row || !dragRow || row === dragRow) return;
        e.preventDefault();
        row.classList.add('is-drop-target');
      });
      document.addEventListener('dragleave', function (e) {
        var row = e.target && e.target.closest ? e.target.closest('#kb-view-table tr.kb-table-row') : null;
        if (row) row.classList.remove('is-drop-target');
      });
      document.addEventListener('drop', function (e) {
        var row = e.target && e.target.closest ? e.target.closest('#kb-view-table tr.kb-table-row') : null;
        if (!row || !dragRow || row === dragRow) return;
        e.preventDefault();
        row.classList.remove('is-drop-target');
        var columnId = Number(row.getAttribute('data-column-id') || 0);
        var statusKey = row.getAttribute('data-status-key') || 'todo';
        var statusTitle = row.getAttribute('data-status') || statusKey;
        var taskId = Number(dragRow.getAttribute('data-task-id') || 0);
        var fromCol = Number(dragRow.getAttribute('data-column-id') || 0);
        if (!taskId || !columnId || columnId === fromCol) return;
        updateRowStatus(dragRow, statusKey, statusTitle, columnId);
        var sel = dragRow.querySelector('select.kb-quick-select[data-field="column_id"]');
        if (sel) {
          sel.value = String(columnId);
          sel.setAttribute('data-prev', String(columnId));
          sel.removeAttribute('data-kb-icon-select');
          try {
            enhanceIconSelect(sel);
          } catch (e3) {}
        }
        postQuickUpdate(taskId, 'column_id', columnId).then(function (res) {
          if (!res.ok) location.reload();
        }).catch(function () {
          location.reload();
        });
      });
    }
  }

  function initProjectTiles() {
    var root = document.getElementById('kb-project-tiles');
    if (!root || root.getAttribute('data-bound') === '1') return;
    root.setAttribute('data-bound', '1');
    root.addEventListener('click', function (e) {
      var btn = e.target && e.target.closest ? e.target.closest('.kb-project-tile-head') : null;
      if (!btn) return;
      var tile = btn.closest('.kb-project-tile');
      if (!tile) return;
      var body = tile.querySelector('.kb-project-tile-body');
      var open = btn.getAttribute('aria-expanded') === 'true';
      btn.setAttribute('aria-expanded', open ? 'false' : 'true');
      tile.classList.toggle('is-open', !open);
      if (body) body.hidden = open;
    });
  }

  function hideBoardSelectorChrome() {
    var box = document.querySelector('header .board-selector-container');
    if (box) box.style.display = 'none';
  }

  function reinitBoardSortable() {
    try {
      if (window.jQuery && jQuery.fn && jQuery.fn.sortable) {
        var lists = jQuery('#kb-view-board .board-task-list');
        if (lists.length) {
          lists.each(function () {
            var $el = jQuery(this);
            if ($el.hasClass('ui-sortable')) {
              try {
                $el.sortable('refresh');
              } catch (e0) {}
            }
          });
        }
      }
    } catch (e) {}
  }

  function boot() {
    if (document.querySelector('.kb-cycle-workspace')) {
      document.body.classList.add('kb-cycle-page');
    } else {
      document.body.classList.remove('kb-cycle-page');
    }
    var canvas = document.getElementById('kb-burnup-chart');
    if (canvas) drawBurnup(canvas);
    applyBoardTints();
    initTableSort();
    initQuickSelects();
    initListDnD();
    initTableDnD();
    initProjectTiles();
    hideBoardSelectorChrome();
    reinitBoardSortable();
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
  document.addEventListener('kb:softnav', boot);
  window.addEventListener('resize', function () {
    var canvas = document.getElementById('kb-burnup-chart');
    if (canvas) drawBurnup(canvas);
  });
})();
