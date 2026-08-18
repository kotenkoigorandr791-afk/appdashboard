(function () {
  'use strict';

  const state = {  
    i18n: {},
    api: null,
    flashStatus: null,
    formatBytes: null,
    t: null,
    initialized: false,
    currentData: null,
    selectedPath: null,
    currentHoverFolder: null
  };

  const $ = (id) => document.getElementById(id);

  function defaultT(i18n, key, params = {}) {
    let str = (i18n && i18n[key]) || key;
    Object.keys(params).forEach((name) => {
      str = str.replaceAll(`{${name}}`, String(params[name]));
    });
    return str;
  }

  function t(key, params = {}) {
    const tr = state.t || defaultT;
    return tr(state.i18n || {}, key, params);
  }

  function formatBytes(bytes) {
    if (typeof state.formatBytes === 'function') return state.formatBytes(bytes);
    if (!Number.isFinite(bytes) || bytes <= 0) return '0 B';
    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
    let value = bytes;
    let i = 0;
    while (value >= 1024 && i < units.length - 1) {
      value /= 1024;
      i++;
    }
    return `${value.toFixed(i === 0 ? 0 : 1)} ${units[i]}`;
  }

  function flash(message) {
    if (typeof state.flashStatus === 'function') {
      state.flashStatus(message);
    }
  }

  function hashColor(input) {
    let hash = 0;
    for (let i = 0; i < input.length; i++) {
      hash = ((hash << 5) - hash) + input.charCodeAt(i);
      hash |= 0;
    }
    const hue = Math.abs(hash) % 360;
    return `hsl(${hue}deg 62% 44%)`;
  }

  function splitByWeight(items) {
    const total = items.reduce((sum, item) => sum + item.size_bytes, 0);
    const target = total / 2;
    let left = [];
    let right = [];
    let acc = 0;
    for (const item of items) {
      if (acc < target || left.length === 0) {
        left.push(item);
        acc += item.size_bytes;
      } else {
        right.push(item);
      }
    }
    if (right.length === 0 && left.length > 1) {
      right = [left.pop()];
    }
    return [left, right];
  }

  function buildRects(items, x, y, w, h, out) {
    if (!items.length) return;
    if (w <= 2 || h <= 2) {
      out.push({ item: items[0], x, y, w: Math.max(1, w), h: Math.max(1, h) });
      return;
    }
    if (items.length === 1) {
      out.push({ item: items[0], x, y, w, h });
      return;
    }
    const [first, second] = splitByWeight(items);
    const firstWeight = first.reduce((sum, item) => sum + item.size_bytes, 0);
    const secondWeight = Math.max(1, second.reduce((sum, item) => sum + item.size_bytes, 0));
    const ratio = firstWeight / (firstWeight + secondWeight);

    if (w >= h) {
      const w1 = Math.max(1, Math.round(w * ratio));
      buildRects(first, x, y, w1, h, out);
      buildRects(second, x + w1, y, Math.max(1, w - w1), h, out);
    } else {
      const h1 = Math.max(1, Math.round(h * ratio));
      buildRects(first, x, y, w, h1, out);
      buildRects(second, x, y + h1, w, Math.max(1, h - h1), out);
    }
  }

  function renderHover(folder, pinned = false) {
    const hoverEl = $('storage-usage-hover');
    if (!hoverEl) return;
    if (!folder) {
      hoverEl.innerHTML = `<div class="storage-usage-hover-title">${t('storageUsageHoverHint')}</div>`;
      return;
    }

    const details = [
      `${t('path')}: ${folder.path || '/'}`,
      `${t('size')}: ${formatBytes(folder.size_bytes || 0)}`,
      '',
      `${t('storageUsageFiles')}: ${folder.files_count || 0}`,
      `${t('storageUsageSubfolders')}: ${folder.subfolders_count || 0}`,
      `${t('storageUsageContains')}: ${(folder.children_preview || []).join(', ') || t('storageUsageUnknown')}`
    ].join('\n');

    const canGoToFolder = pinned && folder.path && folder.path !== '/';

    hoverEl.innerHTML = `
      <div class="storage-usage-hover-title">${folder.path || '/'}</div>
      <div class="storage-usage-hover-details">${details}</div>
      ${canGoToFolder ? `<button class="btn primary small storage-usage-go-btn" id="btn-storage-usage-go">${t('storageUsageGoToFolder')}</button>` : ''}
    `;
  }

  function renderTreemap(folders) {
    const map = $('storage-usage-treemap');
    const empty = $('storage-usage-empty');
    if (!map || !empty) return;
    map.innerHTML = '';

    const valid = (folders || [])
      .filter((f) => Number(f.size_bytes) > 0)
      .sort((a, b) => b.size_bytes - a.size_bytes)
      .slice(0, 300);

    if (!valid.length) {
      empty.classList.remove('hidden');
      renderHover(null);
      return;
    }

    empty.classList.add('hidden');

    const width = map.clientWidth || 900;
    const height = map.clientHeight || 420;
    const rects = [];
    buildRects(valid, 0, 0, width, height, rects);

    for (const rect of rects) {
      const tile = document.createElement('div');
      tile.className = 'storage-usage-tile';
      tile.dataset.path = rect.item.path || '/';
      tile.style.left = `${rect.x}px`;
      tile.style.top = `${rect.y}px`;
      tile.style.width = `${rect.w}px`;
      tile.style.height = `${rect.h}px`;
      tile.style.background = hashColor(rect.item.path || rect.item.name || '/');

      const tooltipText = [
        `${t('folderPath')}: ${rect.item.path || '/'}`,
        `${t('size')}: ${formatBytes(rect.item.size_bytes || 0)}`,
        `${t('storageUsageFiles')}: ${rect.item.files_count || 0}`,
        `${t('storageUsageSubfolders')}: ${rect.item.subfolders_count || 0}`,
        `${t('storageUsageContains')}: ${(rect.item.children_preview || []).join(', ') || t('storageUsageUnknown')}`
      ].join('\n');
      tile.title = tooltipText;

      if (rect.w >= 95 && rect.h >= 54) {
        const label = document.createElement('div');
        label.className = 'storage-usage-tile-label';
        label.innerHTML = `
          <div class="storage-usage-tile-name">${rect.item.path || '/'}</div>
          <div class="storage-usage-tile-size">${formatBytes(rect.item.size_bytes || 0)}</div>
        `;
        tile.appendChild(label);
      }

      if (state.selectedPath && state.selectedPath === (rect.item.path || '/')) {
        tile.classList.add('selected');
      }

      tile.addEventListener('mouseenter', () => {
        state.currentHoverFolder = rect.item;
        if (!state.selectedPath) {
          renderHover(rect.item, false);
        }
      });

      tile.addEventListener('click', () => {
        const tilePath = rect.item.path || '/';
        if (state.selectedPath === tilePath) {
          state.selectedPath = null;
          map.querySelectorAll('.storage-usage-tile.selected').forEach((el) => el.classList.remove('selected'));
          renderHover(state.currentHoverFolder || null, false);
        } else {
          state.selectedPath = tilePath;
          map.querySelectorAll('.storage-usage-tile.selected').forEach((el) => el.classList.remove('selected'));
          tile.classList.add('selected');
          renderHover(rect.item, true);
        }
      });
      map.appendChild(tile);
    }
  }

  function setMeta(data) {
    const sourceEl = $('storage-usage-source');
    const updatedEl = $('storage-usage-updated');
    if (!sourceEl || !updatedEl) return;

    sourceEl.textContent = data.from_cache
      ? t('storageUsageSourceDb')
      : t('storageUsageSourceScan');

    if (data.updated_at) {
      const date = new Date(Number(data.updated_at) * 1000);
      updatedEl.textContent = t('storageUsageUpdatedAt', { time: date.toLocaleString() });
    } else {
      updatedEl.textContent = '';
    }
  }

  async function loadUsage(forceScan) {
    const loading = $('storage-usage-loading');
    const refreshBtn = $('btn-refresh-storage-usage');
    if (!state.api || !loading) return;

    loading.classList.remove('hidden');
    if (refreshBtn) refreshBtn.disabled = true;

    try {
      const data = forceScan
        ? await state.api('refresh_storage_usage')
        : await state.api('get_storage_usage', {}, 'GET');

      if (!data || data.ok === false) {
        throw new Error((data && data.error) || 'failed');
      }

      state.currentData = data;
      setMeta(data);
      renderTreemap(data.folders || []);

      if (forceScan) {
        flash(t('storageUsageScanCompleted'));
      }
    } catch (error) {
      flash(t(forceScan ? 'storageUsageScanFailed' : 'storageUsageLoadFailed') + ': ' + error.message);
    } finally {
      loading.classList.add('hidden');
      if (refreshBtn) refreshBtn.disabled = false;
    }
  }

  function openModal() {
    const modal = $('storage-usage-modal');
    if (!modal) return;
    window.closeSettingsMenu?.();
    state.selectedPath = null;
    state.currentHoverFolder = null;
    modal.classList.remove('hidden');
    renderHover(null);
    loadUsage(false);
  }

  function closeModal() {
    const modal = $('storage-usage-modal');
    if (!modal) return;
    modal.classList.add('hidden');
  }

  function bindEvents() {
    const openBtn = $('btn-storage-usage');
    const closeBtn = $('btn-close-storage-usage');
    const modal = $('storage-usage-modal');
    const refreshBtn = $('btn-refresh-storage-usage');
    const map = $('storage-usage-treemap');
    const hover = $('storage-usage-hover');

    if (openBtn) openBtn.addEventListener('click', openModal);
    if (closeBtn) closeBtn.addEventListener('click', closeModal);
    if (refreshBtn) {
      refreshBtn.addEventListener('click', () => {
        flash(t('storageUsageScanInProgress'));
        loadUsage(true);
      });
    }
    if (modal) {
      modal.addEventListener('click', (event) => {
        if (event.target === modal) closeModal();
      });
    }
    if (hover) {
      hover.addEventListener('click', async (event) => {
        const goBtn = event.target.closest('#btn-storage-usage-go');
        if (!goBtn || !state.selectedPath || state.selectedPath === '/') return;
        try {
          if (typeof window.focusPathInTree === 'function') {
            await window.focusPathInTree(state.selectedPath);
          } else if (typeof window.expandFolderToPath === 'function') {
            await window.expandFolderToPath(state.selectedPath);
          }
          closeModal();
        } catch (error) {
          flash(t('storageUsageGoToFolderFailed') + ': ' + error.message);
        }
      });
    }
    if (map) {
      window.addEventListener('resize', () => {
        if (!modal || modal.classList.contains('hidden') || !state.currentData) return;
        renderTreemap(state.currentData.folders || []);
      });
    }
  }

  window.initStorageUsageModal = function initStorageUsageModal(i18n, deps = {}) {
    state.i18n = i18n || {};
    state.api = deps.api || window.api || null;
    state.flashStatus = deps.flashStatus || window.flashStatus || null;
    state.formatBytes = deps.formatBytes || window.formatBytes || null;
    state.t = deps.t || window.t || defaultT;

    if (state.initialized) return;
    state.initialized = true;
    bindEvents();
  };
})();
