const HIDE_LANG_SWITCHER_CACHE_KEY = 'hideLangSwitcher';
const CLOUD_DEVICE_ID_COOKIE = 'ap_device_id';
function cloudPointerDragStart(cursor, onMove, onEnd) {
    document.getElementById('cloud-pointer-shield')?.remove();
    const shield = document.createElement('div');
    shield.id = 'cloud-pointer-shield';
    shield.style.cssText = 'position:fixed;inset:0;z-index:2147483000;background:transparent;touch-action:none;';
    if (cursor) shield.style.cursor = cursor;
    document.body.appendChild(shield);
    document.body.style.userSelect = 'none';
    if (cursor) document.body.style.cursor = cursor;
    const finish = (ev) => {
        shield.remove();
        document.body.style.userSelect = '';
        document.body.style.cursor = '';
        onEnd?.(ev);
    };
    const move = (ev) => {
        ev.preventDefault();
        onMove?.(ev);
    };
    shield.addEventListener('pointermove', move);
    shield.addEventListener('pointerup', finish);
    shield.addEventListener('pointercancel', finish);
}
window.cloudPointerDragStart = cloudPointerDragStart;
function getCachedHideLangSwitcherPreference() {
  const value = localStorage.getItem(HIDE_LANG_SWITCHER_CACHE_KEY);
  return value === '1';
}
function getCloudCookieValue(name) {
  const prefix = name + '=';
  const parts = document.cookie ? document.cookie.split('; ') : [];
  for (let i = 0; i < parts.length; i++) {
    if (parts[i].indexOf(prefix) === 0) return decodeURIComponent(parts[i].slice(prefix.length));
  }
  return '';
}
function generateCloudDeviceId() {
  if (window.crypto && typeof window.crypto.randomUUID === 'function') {
    return window.crypto.randomUUID().replace(/-/g, '').toLowerCase();
  }
  const bytes = new Uint8Array(16);
  if (window.crypto && typeof window.crypto.getRandomValues === 'function') {
    window.crypto.getRandomValues(bytes);
    return Array.from(bytes).map(b => b.toString(16).padStart(2, '0')).join('');
  }
  return (Date.now().toString(16) + Math.random().toString(16).slice(2)).padEnd(32, '0').slice(0, 32);
}
function writeCloudCookie(name, value, maxAgeSeconds) {
  document.cookie = name + '=' + encodeURIComponent(value) + '; path=/; max-age=' + maxAgeSeconds + '; samesite=Lax';
}
function buildOnlyOfficeEditorUrl(tab, currentUser, lang, theme, color, root) {
  const params = new URLSearchParams({
    path: tab.path,
    user: currentUser,
    lang,
    theme,
    color: color || '#85b503',
    root
  });
  return `onlyoffice_editor.php?${params.toString()}`;
}
function buildCloudProjectUrl(projectPath, params = {}) {
    const base = (window.APP_CONFIG && window.APP_CONFIG.appBase)
        ? window.APP_CONFIG.appBase
        : new URL(window.APP_CONFIG?.apiBase || '/apps/cloud/api.php', window.location.href).pathname.replace(/\/api\.php$/, '');
    const url = new URL(`${base.replace(/\/+$/, '')}/${String(projectPath).replace(/^\/+/, '')}`, window.location.origin);
    Object.entries(params).forEach(([key, value]) => {
        if (value !== undefined && value !== null) url.searchParams.set(key, value);
    });
    return url.pathname + url.search;
}
window.buildCloudProjectUrl = buildCloudProjectUrl;
function dedupeTreeItemsByPath(items) {
    const map = new Map();
    (Array.isArray(items) ? items : []).forEach((item) => {
        const path = String(item?.path || '').replace(/^\/+/, '').replace(/\/+/g, '/');
        if (!path) return;
        map.set(path, { ...item, path });
    });
    return Array.from(map.values());
}
window.dedupeTreeItemsByPath = dedupeTreeItemsByPath;
function dedupeRenderedTreeNodesByPath(root = document.getElementById('tree')) {
    if (!root) return;
    const seen = new Set();
    root.querySelectorAll('.node[data-path]').forEach((node) => {
        const path = String(node.dataset.path || '').replace(/^\/+/, '').replace(/\/+/g, '/');
        if (!path) return;
        if (!seen.has(path)) {
            seen.add(path);
            return;
        }
        const children = node.nextElementSibling;
        if (children && children.classList.contains('children')) children.remove();
        node.remove();
    });
}
window.dedupeRenderedTreeNodesByPath = dedupeRenderedTreeNodesByPath;
function waitForStylesheetsReady(timeoutMs = 2000) {
  const links = Array.from(document.querySelectorAll('link[rel="stylesheet"]'));
  const pending = links.filter((link) => !link.sheet).map((link) => new Promise((resolve) => {
    link.addEventListener('load', resolve, { once: true });
    link.addEventListener('error', resolve, { once: true });
  }));
  if (!pending.length) return Promise.resolve();
  return Promise.race([
    Promise.all(pending),
    new Promise((resolve) => setTimeout(resolve, timeoutMs))
  ]);
}
function ensureCloudDeviceId() {
  let value = getCloudCookieValue(CLOUD_DEVICE_ID_COOKIE);
  if (!/^[a-f0-9]{32,128}$/i.test(value || '')) {
    value = generateCloudDeviceId();
    writeCloudCookie(CLOUD_DEVICE_ID_COOKIE, value, 31536000);
  }
  return value;
}
document.addEventListener('DOMContentLoaded', async function() {
    //comment
  await waitForAPP_CONFIG();
  await waitForStylesheetsReady();
  if (!window.APP_CONFIG) {
    console.warn('APP_CONFIG still not defined, using defaults');
    window.APP_CONFIG = {
      lang: 'en',
      theme: 'dark',
      color: '#85b503'
    };
  }
  
  const cfg = window.APP_CONFIG;
  cfg.lang = cfg.lang || 'en';
  cfg.theme = cfg.theme || 'dark';
  cfg.color = cfg.color || '#85b503';
  applyLangSwitcherVisibility(getCachedHideLangSwitcherPreference());
  try {
    window.i18n = await loadLocale(cfg.lang);
  } catch (err) {
    console.error('Failed to load locale:', err);
    window.i18n = {};
  }
  
  // IMPORTANT: First update users and load their state
  await refreshUsers(window.i18n);
  
  // Now load the folders state (user is already determined)
  await loadFolderState();
  
  applyTheme(cfg.theme, cfg.color);
  translateUI(window.i18n);
  await waitForInitialWindowLoad();
  bindUI(window.i18n);
  initFloatingDownloadButton();
  initFloatingCompileUI();
  initTreeDragMoveHandlers();
  await buildTree('');
  await syncRootModeView();
  
  // Restore state after the tree loads
  await restoreFolderState();
  await maybeOpenCloudAttachmentFromChat();
  initTreeAutoRefresh();
  ensureCloudDeviceId();
  setTimeout(() => {
    maybeShowCloudEncryptionNotice(window.i18n);
  }, 900);
  
  document.addEventListener('keydown', (e) => handleHotkeys(e, window.i18n), true);
  setTimeout(() => {
    refreshAllEditorIframes();
  }, 500);

  // Initialize lasso selection on page load for Windows-style drag selection
  initLassoSelection();
});

async function maybeOpenCloudAttachmentFromChat() {
  let payload = null;
  try {
    payload = JSON.parse(sessionStorage.getItem('cloudOpenAfterLoad') || 'null');
    sessionStorage.removeItem('cloudOpenAfterLoad');
  } catch (_) {
    sessionStorage.removeItem('cloudOpenAfterLoad');
  }
  if (!payload || !payload.path) return;
  const root = payload.root || 'temp';
  if (root !== getCurrentCloudRoot()) {
    setCurrentCloudRoot(root);
    await buildTree('');
    await syncRootModeView();
  }
  openFileInTab(payload.path, false, root);
}

function waitForAPP_CONFIG() {
  return new Promise((resolve) => {
    const maxWaitTime = 5000; // 5 seconds max
    const startTime = Date.now();
    function check() {
      if (window.APP_CONFIG) {
        resolve();
      } else if (Date.now() - startTime > maxWaitTime) {
        console.warn('APP_CONFIG not found after waiting, continuing with defaults');
        resolve();
      } else {
        setTimeout(check, 100);
      }
    }
    check();
  });
}

function waitForInitialWindowLoad() {
  return new Promise((resolve) => {
    if (document.readyState === 'complete') {
      requestAnimationFrame(() => requestAnimationFrame(resolve));
      return;
    }
    window.addEventListener('load', () => {
      requestAnimationFrame(() => requestAnimationFrame(resolve));
    }, { once: true });
  });
}
window.addEventListener('beforeunload', () => {
    if (searchState.searchTimeout) {
        clearTimeout(searchState.searchTimeout);
    }
    if (searchTimeout) {
        clearTimeout(searchTimeout);
    }
});
let autoSaveTimers = new Map();
const AUTO_SAVE_DELAY = 10000; // 10 seconds
const MONACO_COLLAB_CURSOR_MS = 80;
const MONACO_COLLAB_SNAPSHOT_MS = 500;
const MONACO_COLLAB_SYNC_REQUEST_MS = 600;
const monacoCollabSessions = new Map();
const monacoCollabSocketState = {
    remoteClientColors: new Map(),
    socket: null,
    reconnectTimer: null,
    pendingMessages: [],
    clientId: 'client-' + Math.random().toString(36).slice(2, 10),
    wasConnected: false
};
function t(i18n, key, params = {}) {
  let str = i18n[key];
  if (str === undefined) {
    console.warn(`Translation missing for key: "${key}"`);
    str = key;
  }
  Object.keys(params).forEach(p => {
    str = str.replaceAll(`{${p}}`, params[p]);
  });
  return str;
}
const cloudRootState={current:localStorage.getItem('cloudRoot')||'main',allowed:new Set(['main','trash','archive','temp','documents','photos','pdf'])};
let currentUserState={user:null};
function isCloudGroupRoot(root){return String(root||'').startsWith('group:');}
function getCurrentCloudRoot(){const c=cloudRootState.current;return(cloudRootState.allowed.has(c)||isCloudGroupRoot(c))?c:'main';}
function setCurrentCloudRoot(root){cloudRootState.current=(cloudRootState.allowed.has(root)||isCloudGroupRoot(root))?root:'main';localStorage.setItem('cloudRoot',cloudRootState.current);}
function getTabRoot(tab){const r=tab&&tab.root;if(r&&(cloudRootState.allowed.has(r)||isCloudGroupRoot(r)))return r;return getCurrentCloudRoot();}
function getActiveTabRoot(){const t=tabsState.tabs.find(t=>t.id===tabsState.activeTabId);return getTabRoot(t);}
async function getCurrentUser(){if(!currentUserState.user){const state=await api('get_state');setCurrentUser(state.active);}return currentUserState.user;}
function setCurrentUser(user){
    currentUserState.user=user;
    window.cloudCurrentUser = user || '';
}
function getRootFolderPrefix(root) {
    const map = {'archive': '.archive', 'temp': '.temp_files', 'documents': '.documents', 'photos': '.photos', 'pdf': '.pdf', 'trash': '.trash'};
    return map[root] || '';
}
function pathStartsWithRootPrefix(path, root) {
    const prefix = getRootFolderPrefix(root);
    const normalized = String(path || '').replace(/^\/+/, '');
    return !!prefix && (normalized === prefix || normalized.startsWith(prefix + '/'));
}
function buildUserStoragePath(user, path, root = 'main') {
    const safeUser = String(user || 'default').replace(/\s+/g, '_');
    const normalized = String(path || '').replace(/^\/+/, '');
    const prefix = getRootFolderPrefix(root);
    const rootedPath = prefix && !pathStartsWithRootPrefix(normalized, root) ? `${prefix}/${normalized}` : normalized;
    return `storage/${safeUser}/${rootedPath}`;
}
function buildEditorStoragePath(user, path, root = 'main') {
    if (isCloudGroupRoot(root)) return String(path || '').replace(/^\/+/, '');
    return buildUserStoragePath(user, path, root);
}

/**
 * Determine which root folder a path belongs to based on its prefix
 * @param {string} path - File or folder path
 * @returns {string} - Root key ('main', 'archive', 'documents', etc.)
 */
function getRootFromPath(path) {
    const rootMap = {
        '.trash': 'trash',
        '.archive': 'archive',
        '.temp_files': 'temp',
        '.documents': 'documents',
        '.photos': 'photos',
        '.pdf': 'pdf'
    };
    
    // Check if path starts with any of the hidden root folders
    for (const [prefix, rootKey] of Object.entries(rootMap)) {
        if (path === prefix || path.startsWith(prefix + '/')) {
            return rootKey;
        }
    }
    
    return 'main';
}
const CLOUD_ROOT_DISPLAY_KEYS = {
    main: 'folders',
    archive: 'archiveRoot',
    temp: 'tempFilesRoot',
    documents: 'documentsRoot',
    photos: 'photosRoot',
    pdf: 'pdfRoot',
    trash: 'trashRoot'
};
const CLOUD_ROOT_PREFIXES = {
    archive: '.archive',
    temp: '.temp_files',
    documents: '.documents',
    photos: '.photos',
    pdf: '.pdf',
    trash: '.trash'
};
function stripSearchRootPrefix(path, root = 'main') {
    const normalized = String(path || '').replace(/^\/+/, '');
    if (!root || root === 'main') return normalized;
    const prefix = CLOUD_ROOT_PREFIXES[root] || '';
    if (!prefix) return normalized;
    if (normalized === prefix) return '';
    return normalized.startsWith(prefix + '/') ? normalized.slice(prefix.length + 1) : normalized;
}
function getCloudRootDisplayName(root = 'main') {
    if (isCloudGroupRoot(root) && window.cloudGroupsState) {
        const gid = root.slice(6);
        const g = window.cloudGroupsState.groups?.find((x) => x.id === gid);
        if (g) return g.name;
    }
    const key = CLOUD_ROOT_DISPLAY_KEYS[root] || 'folders';
    if (window.i18n && window.i18n[key]) return t(window.i18n, key);
    return root || 'Folders';
}
function buildMoveRootToolbarHtml(activeRoot = 'main', options = {}) {
    const i18nRef = window.i18n || {};
    if (!window.i18n) return '';
    const excludeGroups = !!options.excludeGroups;
    const btnStyle = 'width:40px;height:40px;border:1px solid var(--border);background:var(--bg);color:var(--fg);border-radius:10px;display:flex;align-items:center;justify-content:center;cursor:pointer;transition:all 0.2s;flex-shrink:0';
    const mkBtn = (key, title, icon, extraClass = '') => {
        const active = activeRoot === key ? ' active' : '';
        const safeTitle = String(title || '').replace(/"/g, '&quot;').replace(/</g, '&lt;');
        return `<button type="button" class="move-root-btn${active}${extraClass}" data-root="${key}" title="${safeTitle}" style="${btnStyle}">${icon}</button>`;
    };
    const icons = {
        main: '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>',
        archive: '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="5" x="2" y="3" rx="1"/><path d="M4 8v11a2 2 0 0 0 2 2h2"/><path d="M20 8v11a2 2 0 0 1-2 2h-2"/><path d="m9 15 3-3 3 3"/><path d="M12 12v9"/></svg>',
        temp: '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 5H3"/><path d="M7 12H3"/><path d="M7 19H3"/><path d="M12 18a5 5 0 0 0 9-3 4.5 4.5 0 0 0-4.5-4.5c-1.33 0-2.54.54-3.41 1.41L11 14"/><path d="M11 10v4h4"/></svg>',
        documents: '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 2v8l3-3 3 3V2"/><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H19a1 1 0 0 1 1 1v18a1 1 0 0 1-1 1H6.5a1 1 0 0 1 0-5H20"/></svg>',
        photos: '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>',
        pdf: '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 7v14"/><path d="M16 12h2"/><path d="M16 8h2"/><path d="M3 18a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1h5a4 4 0 0 1 4 4 4 4 0 0 1 4-4h5a1 1 0 0 1 1 1v13a1 1 0 0 1-1 1h-6a3 3 0 0 0-3 3 3 3 0 0 0-3-3z"/><path d="M6 12h2"/><path d="M6 8h2"/></svg>',
        group: '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>'
    };
    const roots = [
        ['main', 'folders'],
        ['archive', 'archiveRoot'],
        ['temp', 'tempFilesRoot'],
        ['documents', 'documentsRoot'],
        ['photos', 'photosRoot'],
        ['pdf', 'pdfRoot']
    ];
    let html = roots.map(([key, titleKey]) => mkBtn(key, t(i18nRef, titleKey), icons[key])).join('');
    if (!excludeGroups) {
        (window.cloudGroupsState?.groups || []).forEach((g) => {
            const rootKey = 'group:' + g.id;
            html += mkBtn(rootKey, g.name, icons.group, ' move-root-btn--group');
        });
    }
    return html;
}
window.buildMoveRootToolbarHtml = buildMoveRootToolbarHtml;
function getSearchResultDisplayPath(item) {
    const sourcePath = String(item?.path || '');
    const root = item?.root || getRootFromPath(sourcePath);
    const localPath = item?.localPath || stripSearchRootPrefix(sourcePath, root);
    const rootLabel = getCloudRootDisplayName(root);
    return {
        root,
        localPath,
        fullPath: sourcePath,
        displayPath: localPath ? `${rootLabel}/${localPath}` : rootLabel
    };
}
window.getSearchResultDisplayPath = getSearchResultDisplayPath;
function getResolvedTabRoot(tab) {
    const pathRoot = getRootFromPath(String((tab && tab.path) || ''));
    return pathRoot !== 'main' ? pathRoot : getTabRoot(tab);
}
let contentCache = new Map();
let sortState = {
    active: false,
    currentSort: 'name-asc',
    items: []
};
let searchState = {
    active: false,
    query: '',
    matches: [],
    currentIndex: -1,
    editor: null,
    searchTimeout: null
};
let editorsMap = new Map(); 
window.editorsMap = editorsMap;
let dragState = {
    isDragging: false,
    startX: 0,
    startY: 0,
    initialX: 0,
    initialY: 0
};
let newFileState = {
  basePath: ''
};
let renameState = {
  oldPath: '',
  currentExt: ''
};
let newFolderState = {
  basePath: ''
};
let deleteState = {
    path: '',
    type: '',
    name: '',
    permanent: false
};
let selectionState = {
    active: false,
    selectedItems: new Set(), // paths of selected items
    selectedRoots: new Map(),
    mode: 'select' // 'select' or 'deselect'
};
let usageStatusRefreshTimerId = null;
let currentTreeDragPaths = [];
let currentTreeDragSourceRoot = 'main';
const USER_USAGE_REFRESH_ACTIONS = new Set([
    'mkdir',
    'create_file',
    'video_generation',
    'rename',
    'move',
    'copy',
    'move_to_trash',
    'delete',
    'save_file',
    'upload',
    'extract_archive',
    'create_archive',
    'archive_folder',
    'convert_file',
    'restore_trash_item'
]);
let trashState = {
    items: [],
    focusedIndex: -1,
    anchorIndex: -1
};

function isTrashRoot(root = getCurrentCloudRoot()) {
    return root === 'trash';
}

function scheduleUserUsageStatusRefresh(delayMs = 450) {
    if (usageStatusRefreshTimerId) {
        clearTimeout(usageStatusRefreshTimerId);
    }
    usageStatusRefreshTimerId = setTimeout(async () => {
        usageStatusRefreshTimerId = null;
        try {
            if (typeof refreshUsers === 'function') {
                await refreshUsers(window.i18n || {});
            }
        } catch (err) {
            console.warn('Failed to refresh user storage status:', err);
        }
    }, delayMs);
}
window.scheduleUserUsageStatusRefresh = scheduleUserUsageStatusRefresh;

function getDragTreePaths(path) {
    const normalized = String(path || '').trim();
    if (!normalized) return [];
    if (selectionState.active && selectionState.selectedItems.has(normalized) && selectionState.selectedItems.size > 1) {
        const root = selectionState.selectedRoots?.get(normalized) || getCurrentCloudRoot();
        return Array.from(selectionState.selectedItems).filter((itemPath) => {
            return itemPath && ((selectionState.selectedRoots?.get(itemPath) || getCurrentCloudRoot()) === root);
        });
    }
    return [normalized];
}

function setTreeDragPayload(event, path, type, root = getCurrentCloudRoot()) {
    if (!event?.dataTransfer) return;
    const paths = getDragTreePaths(path);
    if (!paths.length) return;
    currentTreeDragPaths = paths;
    currentTreeDragSourceRoot = root || getCurrentCloudRoot();
    event.dataTransfer.effectAllowed = 'copyMove';
    event.dataTransfer.setData('application/x-cloud-file-path', path);
    event.dataTransfer.setData('application/x-cloud-file-paths', JSON.stringify(paths));
    event.dataTransfer.setData('application/x-cloud-file-type', type);
    event.dataTransfer.setData('application/x-cloud-source-root', currentTreeDragSourceRoot);
    event.dataTransfer.setData('text/plain', paths.join('\n'));
}

window.setTreeDragPayload = setTreeDragPayload;

function getTreeDragPayload(event) {
    const dt = event?.dataTransfer;
    if (!dt) return [];
    const rawMany = dt.getData('application/x-cloud-file-paths');
    if (rawMany) {
        try {
            const parsed = JSON.parse(rawMany);
            if (Array.isArray(parsed)) return parsed.map((p) => String(p || '').trim()).filter(Boolean);
        } catch (_) {}
    }
    const single = dt.getData('application/x-cloud-file-path') || dt.getData('text/plain');
    return single ? [String(single).split('\n')[0].trim()].filter(Boolean) : currentTreeDragPaths.slice();
}
function hasExternalFilesDrag(event) {
    const types = event?.dataTransfer?.types;
    return !!types && Array.from(types).includes('Files');
}

function canDropTreePaths(paths, targetDir) {
    const target = String(targetDir || '').trim();
    return Array.isArray(paths) && paths.length > 0 && paths.every((source) => {
        const src = String(source || '').trim();
        return src && src !== target && !target.startsWith(src + '/');
    });
}

async function moveTreeDragPaths(paths, targetDir, targetRoot = getCurrentCloudRoot(), sourceRoot = currentTreeDragSourceRoot || getCurrentCloudRoot()) {
    const uniquePaths = [...new Set((paths || []).map((p) => String(p || '').trim()).filter(Boolean))];
    if (!canDropTreePaths(uniquePaths, targetDir)) return;
    const movedPairs = [];
    let errorCount = 0;
    for (const sourcePath of uniquePaths) {
        try {
            await api('move', {
                source_path: sourcePath,
                target_dir: targetDir,
                target_root: targetRoot,
                root: sourceRoot
            });
            const newName = sourcePath.split('/').pop();
            const newPath = targetDir ? `${targetDir}/${newName}` : newName;
            movedPairs.push([sourcePath, newPath, newName]);
        } catch (err) {
            console.error(`Failed to move ${sourcePath}:`, err);
            errorCount++;
        }
    }
    if (movedPairs.length) {
        const ops = movedPairs.map(([oldP, newP, newN]) => ({ op: 'move', old_path: oldP, new_path: newP, new_name: newN }));
        if (typeof patchTreeCacheForRoot === 'function') await patchTreeCacheForRoot(ops, sourceRoot);
        if (sourceRoot === targetRoot) {
            movedPairs.forEach(([oldP, newP, newN]) => treeNodeRename(oldP, newP, newN));
        } else {
            cachedFileTree = null;
            await buildTree('');
        }
        flashStatus(t(i18n, movedPairs.length > 1 ? 'moveSuccessMultiple' : 'moveSuccess', { count: movedPairs.length }));
    }
    if (errorCount) {
        flashStatus(t(i18n, 'moveErrorMultiple', { count: errorCount }));
    }
    clearSelection();
}

function initTreeDragMoveHandlers() {
    document.addEventListener('dragstart', (event) => {
        const node = event.target?.closest?.('#tree .node[data-path], #tree .advanced-search-result[data-path]');
        if (!node) return;
        const type = node.dataset.type || 'file';
        const path = node.dataset.path || '';
        const root = node.dataset.root || getCurrentCloudRoot();
        if (selectionState.active && !isLassoDragHandle(event.target)) {
            if (type !== 'file') {
                event.preventDefault();
                return;
            }
            setTreeDragPayload(event, path, type, root);
            return;
        }
        setTreeDragPayload(event, path, type, root);
    });

    document.addEventListener('dragover', (event) => {
        if (hasExternalFilesDrag(event)) {
            const node = event.target?.closest?.('#tree .node[data-type="dir"]');
            if (!node) return;
            event.preventDefault();
            event.dataTransfer.dropEffect = 'copy';
            node.classList.add('tree-drop-target');
            return;
        }
        const node = event.target?.closest?.('#tree .node[data-type="dir"]');
        if (!node) return;
        const paths = getTreeDragPayload(event);
        if (!canDropTreePaths(paths, node.dataset.path || '')) return;
        event.preventDefault();
        event.dataTransfer.dropEffect = 'move';
        node.classList.add('tree-drop-target');
    }, true);

    document.addEventListener('dragleave', (event) => {
        const node = event.target?.closest?.('#tree .node.tree-drop-target');
        if (node && !node.contains(event.relatedTarget)) node.classList.remove('tree-drop-target');
    }, true);

    document.addEventListener('drop', async (event) => {
        if (hasExternalFilesDrag(event)) {
            const node = event.target?.closest?.('#tree .node[data-type="dir"]');
            if (!node) return;
            const files = event.dataTransfer?.files;
            if (!files || !files.length || typeof window.uploadFilesToPath !== 'function') return;
            event.preventDefault();
            event.stopPropagation();
            document.querySelectorAll('#tree .node.tree-drop-target').forEach((el) => el.classList.remove('tree-drop-target'));
            window.uploadFilesToPath(Array.from(files), node.dataset.path || '');
            return;
        }
        const node = event.target?.closest?.('#tree .node[data-type="dir"]');
        if (!node) return;
        const paths = getTreeDragPayload(event);
        const targetDir = node.dataset.path || '';
        if (!canDropTreePaths(paths, targetDir)) return;
        event.preventDefault();
        event.stopPropagation();
        currentTreeDragPaths = [];
        document.querySelectorAll('#tree .node.tree-drop-target').forEach((el) => el.classList.remove('tree-drop-target'));
        await moveTreeDragPaths(paths, targetDir, getCurrentCloudRoot());
    }, true);

    document.addEventListener('dragend', () => {
        currentTreeDragPaths = [];
        currentTreeDragSourceRoot = getCurrentCloudRoot();
        document.querySelectorAll('#tree .node.tree-drop-target').forEach((el) => el.classList.remove('tree-drop-target'));
    }, true);
}

let shareState = {
    currentKey: null,
    currentPath: '',
    currentExpiresOption: 'never',
    currentRestrictions: {
        password: '',
        view_only: 0,
        disable_download: 0,
        disable_copy: 0,
        max_views: 0
    }
};


let lassoState = {
    active: false,
    startX: 0,
    startY: 0,
    currentX: 0,
    currentY: 0,
    container: null,
    element: null
};
let compileTimerState = {
    startTime: null,
    timerInterval: null,
    elapsedSeconds: 0,
    isRunning: false
};
let compileFloatingState = {
    active: false,
    minimized: false
};

// Format time for display (MM:SS.t)
function formatCompileTime(seconds) {
    const mins = Math.floor(seconds / 60);
    const secs = Math.floor(seconds % 60);
    const tenths = Math.floor((seconds - Math.floor(seconds)) * 10);
    return `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}.${tenths}`;
}

// Start compile timer
function startCompileTimer() {
    // Stop any existing timer
    stopCompileTimer();
    
    const timerElement = document.getElementById('compile-timer');
    const displayElement = document.getElementById('timer-display');
    
    if (!timerElement || !displayElement) return;
    
    compileTimerState.startTime = Date.now();
    compileTimerState.isRunning = true;
    compileTimerState.elapsedSeconds = 0;
    
    // Update UI
    timerElement.classList.remove('finished');
    timerElement.classList.add('running');
    displayElement.textContent = formatCompileTime(0);
    updateCompileFloatingButtonState();
    
    // Start interval
    compileTimerState.timerInterval = setInterval(() => {
        if (!compileTimerState.isRunning) return;
        
        const now = Date.now();
        const elapsed = (now - compileTimerState.startTime) / 1000;
        compileTimerState.elapsedSeconds = elapsed;
        displayElement.textContent = formatCompileTime(elapsed);
    }, 100); // Update every 100ms for smooth display
}

// Stop compile timer
function stopCompileTimer() {
    if (compileTimerState.timerInterval) {
        clearInterval(compileTimerState.timerInterval);
        compileTimerState.timerInterval = null;
    }
    
    const timerElement = document.getElementById('compile-timer');
    if (timerElement) {
        timerElement.classList.remove('running');
    }
    
    compileTimerState.isRunning = false;
    updateCompileFloatingButtonState();
}

// Mark compilation as finished (stop timer and show finished state)
function finishCompileTimer() {
    stopCompileTimer();
    
    const timerElement = document.getElementById('compile-timer');
    if (timerElement) {
        timerElement.classList.add('finished');
        timerElement.classList.remove('running');
    }
    updateCompileFloatingButtonState();
}

// Reset compile timer
function resetCompileTimer() {
    stopCompileTimer();
    
    const timerElement = document.getElementById('compile-timer');
    const displayElement = document.getElementById('timer-display');
    
    if (timerElement) {
        timerElement.classList.remove('running', 'finished');
    }
    
    if (displayElement) {
        displayElement.textContent = '00:00.0';
    }
    
    compileTimerState.startTime = null;
    compileTimerState.elapsedSeconds = 0;
    compileTimerState.isRunning = false;
    updateCompileFloatingButtonState();
}

function positionFloatingCompileButton() {
    const button = document.getElementById('floating-compile-btn');
    if (!button) return;
    let bottom = 40;
    const downloadBtn = document.getElementById('floating-download-btn');
    const uploadBtn = document.getElementById('floating-upload-btn');
    if (downloadBtn && !downloadBtn.classList.contains('hidden')) bottom += 70;
    if (uploadBtn && !uploadBtn.classList.contains('hidden')) bottom += 70;
    button.style.bottom = `${bottom}px`;
}

function updateCompileFloatingButtonState() {
    const button = document.getElementById('floating-compile-btn');
    if (!button) return;
    button.classList.toggle('is-running', !!compileTimerState.isRunning);
    button.classList.toggle('is-finished', !compileTimerState.isRunning && compileFloatingState.active);
    const timerLabel = button.querySelector('.floating-compile-time');
    if (timerLabel) {
        timerLabel.textContent = compileTimerState.isRunning
            ? formatCompileTime(compileTimerState.elapsedSeconds)
            : (compileFloatingState.active ? formatCompileTime(compileTimerState.elapsedSeconds) : '');
    }
}

function showFloatingCompileButton() {
    const button = document.getElementById('floating-compile-btn');
    if (!button) return;
    compileFloatingState.active = true;
    button.classList.remove('hidden');
    positionFloatingCompileButton();
    updateCompileFloatingButtonState();
}

function hideFloatingCompileButton() {
    const button = document.getElementById('floating-compile-btn');
    if (!button) return;
    button.classList.add('hidden');
    compileFloatingState.active = false;
    compileFloatingState.minimized = false;
    updateCompileFloatingButtonState();
}

function minimizeCompileModal() {
    const modal = document.getElementById('compile-modal');
    if (!modal || modal.classList.contains('hidden')) return;
    compileFloatingState.minimized = true;
    modal.classList.add('hidden');
    showFloatingCompileButton();
    flashStatus(t(i18n, 'compileOutput') || 'Compile output');
}

function expandCompileModal() {
    const modal = document.getElementById('compile-modal');
    if (!modal) return;
    compileFloatingState.minimized = false;
    modal.classList.remove('hidden');
    hideFloatingCompileButton();
    const input = document.getElementById('compile-input-text');
    if (input) setTimeout(() => input.focus(), 60);
}

function initFloatingCompileUI() {
    const button = document.getElementById('floating-compile-btn');
    if (!button || button.dataset.bound === '1') return;
    button.dataset.bound = '1';
    button.addEventListener('click', (e) => {
        e.stopPropagation();
        expandCompileModal();
    });
    const observer = new MutationObserver(() => {
        if (!button.classList.contains('hidden')) positionFloatingCompileButton();
    });
    observer.observe(document.body, { childList: true, subtree: true, attributes: true, attributeFilter: ['class'] });
    window.addEventListener('resize', positionFloatingCompileButton);
    updateCompileFloatingButtonState();
}
// Function to create a selection rectangle element
function createLassoElement() {
    const lasso = document.createElement('div');
    lasso.className = 'lasso-selection';
    lasso.style.cssText = `
        position: fixed;
        background: rgba(${getComputedStyle(document.documentElement).getPropertyValue('--accent-rgb')}, 0.15);
        border: 2px solid var(--accent);
        border-radius: 4px;
        pointer-events: none;
        z-index: 9999;
        display: none;
        box-shadow: 0 0 0 1px rgba(255, 255, 255, 0.1) inset;
        backdrop-filter: blur(2px);
        transition: background 0.1s ease;
    `;
    document.body.appendChild(lasso);
    return lasso;
}

// Function to get all tree elements in a given rectangle
function getTreeElementsInRect(rect) {
    const tree = document.getElementById('tree');
    const nodes = tree.querySelectorAll('.node');
    const elementsInRect = [];
    
    nodes.forEach(node => {
        const nodeRect = node.getBoundingClientRect();
        
        // Check rectangle intersection
        if (nodeRect.right > rect.left && 
            nodeRect.left < rect.right && 
            nodeRect.bottom > rect.top && 
            nodeRect.top < rect.bottom) {
            
            // Check element visibility (not hidden inside a collapsed folder)
            let isVisible = true;
            let parent = node.parentElement;
            
            // Check whether the element is inside a collapsed folder
            while (parent && parent !== tree) {
                if (parent.classList.contains('children') && parent.style.display === 'none') {
                    isVisible = false;
                    break;
                }
                parent = parent.parentElement;
            }
            
            if (isVisible) {
                elementsInRect.push(node);
            }
        }
    });
    
    return elementsInRect;
}

function getTrashElementsInRect(rect) {
    const grid = document.getElementById('trash-grid');
    if (!grid) return [];
    const cards = grid.querySelectorAll('.trash-item');
    const elementsInRect = [];

    cards.forEach((card) => {
        const cardRect = card.getBoundingClientRect();
        if (cardRect.right > rect.left &&
            cardRect.left < rect.right &&
            cardRect.bottom > rect.top &&
            cardRect.top < rect.bottom) {
            elementsInRect.push(card);
        }
    });

    return elementsInRect;
}

// Function to select elements with a rectangle
function selectElementsInLasso(lassoRect) {
    if (!selectionState.active) return;
    
    const elementsInRect = getTreeElementsInRect(lassoRect);
    
    elementsInRect.forEach(node => {
        const path = node.dataset.path;
        const type = node.dataset.type;
        
        if (!selectionState.selectedItems.has(path)) {
            selectionState.selectedItems.add(path);
            
            // Add a checkbox if missing
            if (!node.querySelector('.selection-checkbox')) {
                addCheckboxToNode(node);
            }
            
            // Check the checkbox
            const checkbox = node.querySelector('.selection-checkbox');
            if (checkbox) {
                checkbox.checked = true;
            }
            
            node.classList.add('selected');
        }
    });
    
    updateSelectionUI();
}

function getMouseEventElement(e) {
    return e.target instanceof Element ? e.target : e.target?.parentElement || null;
}
function isLassoDragHandle(el) {
    return !!(el && (el.closest('.toggle') || el.closest('.result-icon')));
}
function isLassoBlockedTarget(el) {
    return !!(el && (el.closest('.selection-checkbox') || el.closest('button') || el.closest('input')));
}
function canStartTreeLasso(e) {
    const el = getMouseEventElement(e);
    if (!el) return false;
    const treeTarget = el.closest('#tree');
    const trashTarget = isTrashRoot() ? el.closest('#trash-grid') : null;
    if (!treeTarget && !trashTarget) return false;
    if (isLassoBlockedTarget(el)) return false;
    if (isLassoDragHandle(el)) return false;
    return true;
}
function initLassoSelection() {
    const tree = document.getElementById('tree');
    lassoState.element = createLassoElement();
    let dragStarted = false;
    let lassoMouseDown = false;
    let mouseDownPos = { x: 0, y: 0 };
    const DRAG_THRESHOLD = 5;
    const lassoCapture = true;
    document.addEventListener('mousedown', (e) => {
        if (e.button !== 0) return;
        if (window.matchMedia('(max-width: 768px)').matches) return;
        if (!canStartTreeLasso(e)) return;
        const el = getMouseEventElement(e);
        const trashTarget = isTrashRoot() ? el.closest('#trash-grid') : null;
        lassoState.container = trashTarget ? 'trash' : 'tree';
        dragStarted = false;
        lassoMouseDown = true;
        mouseDownPos.x = e.clientX;
        mouseDownPos.y = e.clientY;
        if (selectionState.active || el?.closest('.node, .advanced-search-result')) {
            e.preventDefault();
        }
    }, lassoCapture);
    document.addEventListener('mousemove', (e) => {
        if (!(e.buttons & 1)) {
            lassoMouseDown = false;
            mouseDownPos.x = 0;
            mouseDownPos.y = 0;
            return;
        }
        if (lassoMouseDown && !dragStarted && !lassoState.active) {
            const dx = e.clientX - mouseDownPos.x;
            const dy = e.clientY - mouseDownPos.y;
            const distance = Math.sqrt(dx * dx + dy * dy);
            if (distance > DRAG_THRESHOLD) {
                if (!selectionState.active && lassoState.container !== 'trash') {
                    selectionState.active = true;
                    selectionState.mode = 'select';
                    document.querySelectorAll('#tree .node, #tree .advanced-search-result').forEach(node => {
                        addCheckboxToNode(node);
                    });
                    tree?.classList.add('selection-mode');
                    syncAllTreeNodeDragHandles();
                }
                if (lassoState.container === 'trash') {
                    selectionState.active = true;
                    selectionState.mode = 'select';
                }
                dragStarted = true;
                lassoState.active = true;
                lassoState.startX = mouseDownPos.x;
                lassoState.startY = mouseDownPos.y;
                lassoState.currentX = mouseDownPos.x;
                lassoState.currentY = mouseDownPos.y;
                lassoState.mode = 'select';
                document.body.style.userSelect = 'none';
                e.preventDefault();
                const lasso = lassoState.element;
                lasso.style.display = 'block';
                lasso.style.left = lassoState.startX + 'px';
                lasso.style.top = lassoState.startY + 'px';
                lasso.style.width = '1px';
                lasso.style.height = '1px';
                updateSelectionUI();
            }
        }
        if (!lassoState.active) return;
        lassoState.currentX = e.clientX;
        lassoState.currentY = e.clientY;
        const lasso = lassoState.element;
        const left = Math.min(lassoState.startX, lassoState.currentX);
        const top = Math.min(lassoState.startY, lassoState.currentY);
        const width = Math.abs(lassoState.currentX - lassoState.startX);
        const height = Math.abs(lassoState.currentY - lassoState.startY);
        lasso.style.left = left + 'px';
        lasso.style.top = top + 'px';
        lasso.style.width = width + 'px';
        lasso.style.height = height + 'px';
        if (selectionState.active) {
            const lassoRect = {
                left: left,
                top: top,
                right: left + width,
                bottom: top + height
            };
            if (lassoState.container === 'trash') {
                processTrashElementsInLasso(lassoRect, lassoState.mode);
            } else {
                processElementsInLasso(lassoRect, lassoState.mode);
            }
        }
        e.preventDefault();
    }, lassoCapture);
    document.addEventListener('mouseup', (e) => {
        lassoMouseDown = false;
        mouseDownPos.x = 0;
        mouseDownPos.y = 0;
        if (!lassoState.active) {
            document.body.style.userSelect = '';
            return;
        }
        const lasso = lassoState.element;
        lasso.style.display = 'none';
        lassoState.active = false;
        lassoState.mode = null;
        lassoState.container = null;
        dragStarted = false;
        document.body.style.userSelect = '';
        updateSelectionUI();
        e.preventDefault();
    }, lassoCapture);

    // Handler to clear selection on Escape
    document.addEventListener('keydown', (e) => {
        if (e.key !== 'Escape') return;
        if (lassoState.active) {
            lassoState.active = false;
            lassoState.element.style.display = 'none';
            lassoState.container = null;
            document.body.style.userSelect = '';
            mouseDownPos.x = 0;
            mouseDownPos.y = 0;
            lassoMouseDown = false;
            e.preventDefault();
            return;
        }
        if (typeof getTopModal === 'function' && getTopModal()) return;
        if (document.getElementById('context-menu')) return;
        if (!selectionState.active) return;
        clearSelection();
        updateSelectionMenuItem();
        e.preventDefault();
    });
}
function processElementsInLasso(lassoRect, mode) {
    if (!selectionState.active) return;
    
    const elementsInRect = getTreeElementsInRect(lassoRect);
    
    elementsInRect.forEach(node => {
        const path = node.dataset.path;
        const type = node.dataset.type;
        
        if (mode === 'toggle') {
            // Toggle mode: if selected - deselect, if not - select
            if (selectionState.selectedItems.has(path)) {
                selectionState.selectedItems.delete(path);
                node.classList.remove('selected');
                const checkbox = node.querySelector('.selection-checkbox');
                if (checkbox) checkbox.checked = false;
            } else {
                selectionState.selectedItems.add(path);
                node.classList.add('selected');
                
                // Add a checkbox if missing
                if (!node.querySelector('.selection-checkbox')) {
                    addCheckboxToNode(node);
                }
                
                const checkbox = node.querySelector('.selection-checkbox');
                if (checkbox) checkbox.checked = true;
            }
        } else {
            // Normal mode: select all in the rectangle
            if (!selectionState.selectedItems.has(path)) {
                selectionState.selectedItems.add(path);
                
                // Add a checkbox if missing
                if (!node.querySelector('.selection-checkbox')) {
                    addCheckboxToNode(node);
                }
                
                const checkbox = node.querySelector('.selection-checkbox');
                if (checkbox) checkbox.checked = true;
                
                node.classList.add('selected');
            }
        }
    });
}

function formatShareExpiryText(expiresAt, expiresOption) {
    if (!expiresAt || expiresOption === 'never') return t(window.i18n, 'shareLinkNeverExpires');
    const d = new Date(Number(expiresAt) * 1000);
    if (Number.isNaN(d.getTime())) return t(window.i18n, 'shareLinkExpiryUnknown');
    return `${t(window.i18n, 'shareLinkExpiresAt')}: ${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')} ${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`;
}
async function generateShareKey(filePath, expiresOption = 'never') {
    const response = await api('share_file', {
        path: filePath,
        expires_option: expiresOption,
        password: shareState.currentRestrictions.password || '',
        view_only: shareState.currentRestrictions.view_only ? 1 : 0,
        disable_download: shareState.currentRestrictions.disable_download ? 1 : 0,
        disable_copy: shareState.currentRestrictions.disable_copy ? 1 : 0,
        max_views: Number(shareState.currentRestrictions.max_views || 0)
    }, 'POST');
    if (response && response.key) {
        return response;
    }
    throw new Error('Failed to generate share key');
}
window.generateShareKey = generateShareKey;
function buildCloudShareUrl(shareData) {
    const currentUrl = new URL(window.location.href);
    const currentParams = currentUrl.searchParams;
    let color = currentParams.get('color');
    if (!color) {
        color = window.APP_CONFIG.color.replace('#', '%23');
    }
    const shareUrl = new URL(window.location.href);
    shareUrl.pathname = '/';
    shareUrl.search = '';
    shareUrl.searchParams.set('sharedFile', shareData.key);
    shareUrl.searchParams.set('theme', currentParams.get('theme') || window.APP_CONFIG.theme);
    shareUrl.searchParams.set('lang', currentParams.get('lang') || window.APP_CONFIG.lang);
    shareUrl.searchParams.set('color', color);
    return shareUrl.toString();
}
window.getCloudShareUrlForPath = async function(filePath, expiresOption = 'never') {
    const shareData = await generateShareKey(filePath, expiresOption);
    return buildCloudShareUrl(shareData);
};
async function ensureQrCodeStyling() {
    if (typeof QRCodeStyling !== 'undefined') return QRCodeStyling;
    const url = 'https://cdn.jsdelivr.net/npm/qr-code-styling@1.5.0/lib/qr-code-styling.js';
    const res = await fetch(url, { cache: 'no-store' });
    if (!res.ok) throw new Error('HTTP ' + res.status);
    const code = await res.text();
    const prevDefine = (typeof define !== 'undefined') ? define : undefined;
    try {
        if (typeof window !== 'undefined') window.define = undefined;
        if (typeof self !== 'undefined') self.define = undefined;
        (0, eval)(code);
    } finally {
        if (typeof window !== 'undefined' && typeof prevDefine !== 'undefined') window.define = prevDefine;
        if (typeof self !== 'undefined' && typeof prevDefine !== 'undefined') self.define = prevDefine;
    }
    if (typeof window !== 'undefined' && typeof window.QRCodeStyling !== 'undefined') return window.QRCodeStyling;
    if (typeof self !== 'undefined' && typeof self.QRCodeStyling !== 'undefined') return self.QRCodeStyling;
    return QRCodeStyling;
}
async function renderShareModalForPath(filePath, displayName, expiresOption = 'never') {
    const modal = document.getElementById('share-modal');
    const select = document.getElementById('share-expires-select');
    const expiresInfo = document.getElementById('share-expires-at');
    const modeNoneInput = document.getElementById('share-access-none');
    const modeViewOnlyInput = document.getElementById('share-access-view-only');
    const modeDisableDownloadInput = document.getElementById('share-access-disable-download');
    const modeDisableCopyInput = document.getElementById('share-access-disable-copy');
    const passwordInput = document.getElementById('share-password');
    const openLimitInput = document.getElementById('share-open-limit');
    if (!modal || !select) throw new Error('Share modal is unavailable');

    shareState.currentPath = filePath;
    shareState.currentExpiresOption = expiresOption || 'never';
    select.value = shareState.currentExpiresOption;
    const currentPasswordValue = shareState.currentRestrictions?.password || '';

    flashStatus(t(i18n, 'generatingLink'));
    const shareData = await generateShareKey(filePath, shareState.currentExpiresOption);
    shareState.currentKey = shareData.key;
    shareState.currentRestrictions = {
        password: currentPasswordValue,
        view_only: Number(shareData.view_only || 0),
        disable_download: Number(shareData.disable_download || 0),
        disable_copy: Number(shareData.disable_copy || 0),
        max_views: Number(shareData.max_views || 0)
    };
    const modeValue = shareState.currentRestrictions.view_only ? 'view_only' : (shareState.currentRestrictions.disable_download ? 'disable_download' : (shareState.currentRestrictions.disable_copy ? 'disable_copy' : 'none'));
    if (modeNoneInput) modeNoneInput.checked = modeValue === 'none';
    if (modeViewOnlyInput) modeViewOnlyInput.checked = modeValue === 'view_only';
    if (modeDisableDownloadInput) modeDisableDownloadInput.checked = modeValue === 'disable_download';
    if (modeDisableCopyInput) modeDisableCopyInput.checked = modeValue === 'disable_copy';
    if (passwordInput) passwordInput.value = currentPasswordValue;
    if (openLimitInput) openLimitInput.value = String(shareState.currentRestrictions.max_views || 0);

    const finalUrl = buildCloudShareUrl(shareData);

    const linkEl = document.getElementById('share-link');
    linkEl.href = finalUrl;
    linkEl.textContent = finalUrl;
    if (expiresInfo) {
        expiresInfo.textContent = formatShareExpiryText(shareData.expires ?? null, shareData.expires_option || shareState.currentExpiresOption);
    }

    const qrContainer = document.getElementById('qrcode-container');
    qrContainer.innerHTML = '';
    const wrapper = document.createElement('div');
    wrapper.style.cssText = `
        padding: 20px;
        border-radius: 22px;
        background: #ffffff;
        display: grid;
        place-items: center;
        max-width: 360px;
        margin: 16px auto;
    `;
    try {
        const QRCodeStylingCtor = await ensureQrCodeStyling();
        if (typeof QRCodeStylingCtor === 'undefined') {
            wrapper.innerHTML = '<p style="color: var(--muted); padding: 12px;">QR code unavailable</p>';
        } else {
            const qr = new QRCodeStylingCtor({
                width: 256,
                height: 256,
                type: 'svg',
                data: finalUrl,
                margin: 8,
                qrOptions: { errorCorrectionLevel: 'H' },
                dotsOptions: {
                    type: 'rounded',
                    gradient: {
                        type: 'linear',
                        rotation: Math.PI / 4,
                        colorStops: [
                            { offset: 0, color: '#7c3aed' },
                            { offset: 0.5, color: '#2563eb' },
                            { offset: 1, color: '#06b6d4' }
                        ]
                    }
                },
                cornersSquareOptions: { type: 'extra-rounded', color: '#2563eb' },
                cornersDotOptions: { type: 'dot', color: '#7c3aed' },
                backgroundOptions: { color: 'rgba(255,255,255,0)' }
            });
            qr.append(wrapper);
        }
    } catch (error) {
        console.error('QR Code error:', error);
        wrapper.innerHTML = '<p style="color: var(--muted); padding: 12px;">QR code unavailable</p>';
    }
    qrContainer.appendChild(wrapper);
    document.querySelector('#share-modal .modal-header h3').textContent =
        t(i18n, 'shareModalTitle') + ': ' + displayName;

    if (!document.head.querySelector('style[data-share-modal]')) {
        const style = document.createElement('style');
        style.setAttribute('data-share-modal', '1');
        style.textContent = `
            @keyframes fadeIn {
                from { opacity: 0; transform: scale(0.9); }
                to { opacity: 1; transform: scale(1); }
            }
            @media (max-width: 640px) {
                #share-modal-actions {
                    flex-direction: row !important;
                    align-items: center !important;
                    justify-content: center !important;
                    width: 100% !important;
                }
                #share-modal-actions .btn {
                    width: auto !important;
                    min-width: 0 !important;
                    flex: 0 1 auto !important;
                }
            }
        `;
        document.head.appendChild(style);
    }

    modal.classList.remove('hidden');
}
window.renderShareModalForPath = renderShareModalForPath;
async function openShareModal() {
    const viewer = document.getElementById('viewer');
    const filePath = viewer.dataset.path;
    if (!filePath) {
        flashStatus(t(i18n, 'noFileOpen'));
        return false;
    }
    try {
        viewer.dataset.lastSharedPath = filePath;
        await renderShareModalForPath(filePath, viewer.dataset.path.split('/').pop(), shareState.currentExpiresOption || 'never');
        return true;
    } catch (err) {
        flashStatus(t(i18n, 'shareError') + ': ' + err.message);
        document.getElementById('share-modal').classList.add('hidden');
        return false;
    }
}
function cloudShareText(key, fallback) {
    const value = t(window.i18n, key);
    return value && value !== key ? value : fallback;
}
function openShareChoiceMenu(anchor, getLinkAction, sendAction) {
    document.querySelectorAll('.context-menu, .context-menu-submenu').forEach((el) => el.remove());
    const menu = document.createElement('div');
    menu.className = 'context-menu';
    menu.style.position = 'fixed';
    menu.style.zIndex = '200000';
    menu.style.visibility = 'hidden';
    menu.style.opacity = '0';
    const options = [
        { label: cloudShareText('shareGetLink', 'Get link'), icon: 'link', action: getLinkAction },
        { label: cloudShareText('shareSendToChat', 'Send to chat'), icon: 'message', action: sendAction }
    ];
    if (typeof renderContextMenuOptions === 'function') {
        renderContextMenuOptions(menu, options);
    } else {
        options.forEach((opt) => {
            const item = document.createElement('button');
            item.type = 'button';
            item.className = 'context-menu-item';
            item.textContent = opt.label;
            item.addEventListener('click', () => {
                menu.remove();
                opt.action();
            });
            menu.appendChild(item);
        });
    }
    document.body.appendChild(menu);
    const rect = anchor.getBoundingClientRect();
    const menuRect = menu.getBoundingClientRect();
    menu.style.left = `${Math.max(8, Math.min(rect.left, window.innerWidth - menuRect.width - 8))}px`;
    menu.style.top = `${Math.max(8, Math.min(rect.bottom + 6, window.innerHeight - menuRect.height - 8))}px`;
    menu.style.visibility = 'visible';
    menu.style.opacity = '1';
    const close = (e) => {
        if (menu.contains(e.target) || e.target === anchor) return;
        menu.remove();
        document.removeEventListener('pointerdown', close, true);
    };
    setTimeout(() => document.addEventListener('pointerdown', close, true), 0);
}
function initShareFeature() {
    document.getElementById('btn-share').addEventListener('click', () => {
        const button = document.getElementById('btn-share');
        openShareChoiceMenu(button, async () => {
            const ok = await openShareModal();
            if (ok) showSuccessCheck(button);
        }, () => {
            const viewer = document.getElementById('viewer');
            const filePath = viewer?.dataset?.path;
            if (!filePath) {
                flashStatus(t(i18n, 'noFileOpen'));
                return;
            }
            if (typeof window.sendCloudShareToChat === 'function') {
                window.sendCloudShareToChat(filePath, 'file');
            }
        });
    });
    document.getElementById('btn-close-share').addEventListener('click', () => {
        document.getElementById('share-modal').classList.add('hidden');
    });
    document.getElementById('btn-copy-share-link').addEventListener('click', async () => {
        const link = document.getElementById('share-link').href;
        const button = document.getElementById('btn-copy-share-link');
        try {
            await navigator.clipboard.writeText(link);
            flashStatus(t(i18n, 'shareLinkCopied'));
            showSuccessCheck(button, t(i18n, 'shareCopied'));
        } catch (err) {
            console.warn('Clipboard API недоступен, используем fallback');
            const textarea = document.createElement('textarea');
            textarea.value = link;
            textarea.style.position = 'fixed';
            textarea.style.opacity = '0';
            textarea.style.left = '-9999px';
            document.body.appendChild(textarea);
            textarea.focus();
            textarea.select();
            try {
                const successful = document.execCommand('copy');
                if (successful) {
                    flashStatus(t(i18n, 'shareLinkCopiedFallback'));
                    showSuccessCheck(button, t(i18n, 'shareCopied'));
                } else {
                    throw new Error('execCommand failed');
                }
            } catch (fallbackErr) {
                flashStatus(t(i18n, 'shareCopyFailed')); 
                console.error('Copy failed:', fallbackErr);
            } finally {
                document.body.removeChild(textarea);
            }
        }
    });
    document.getElementById('share-modal').addEventListener('click', (e) => {
        if (e.target === document.getElementById('share-modal')) {
            e.target.classList.add('hidden');
        }
    });
    document.getElementById('btn-share-settings')?.addEventListener('click', () => {
        document.getElementById('share-settings-modal')?.classList.remove('hidden');
    });
    document.getElementById('btn-close-share-settings')?.addEventListener('click', () => {
        document.getElementById('share-settings-modal')?.classList.add('hidden');
    });
    document.getElementById('share-settings-modal')?.addEventListener('click', (e) => {
        if (e.target === document.getElementById('share-settings-modal')) e.target.classList.add('hidden');
    });
    document.getElementById('btn-toggle-share-password')?.addEventListener('click', () => {
        const input = document.getElementById('share-password');
        const eyeOpen = document.getElementById('share-password-eye-open');
        const eyeClosed = document.getElementById('share-password-eye-closed');
        if (!input) return;
        const isPassword = input.type === 'password';
        input.type = isPassword ? 'text' : 'password';
        if (eyeOpen) eyeOpen.style.display = isPassword ? 'none' : '';
        if (eyeClosed) eyeClosed.style.display = isPassword ? '' : 'none';
    });
    document.getElementById('share-expires-select')?.addEventListener('change', async (e) => {
        const nextOption = e.target.value || 'never';
        shareState.currentExpiresOption = nextOption;
        if (!shareState.currentPath) return;
        try {
            await renderShareModalForPath(shareState.currentPath, shareState.currentPath.split('/').pop(), nextOption);
        } catch (err) {
            flashStatus(t(i18n, 'shareError') + ': ' + err.message);
        }
    });
    const applyShareAccessMode = async (mode) => {
        shareState.currentRestrictions.view_only = mode === 'view_only' ? 1 : 0;
        shareState.currentRestrictions.disable_download = mode === 'disable_download' ? 1 : 0;
        shareState.currentRestrictions.disable_copy = mode === 'disable_copy' ? 1 : 0;
        if (!shareState.currentPath) return;
        try {
            await renderShareModalForPath(shareState.currentPath, shareState.currentPath.split('/').pop(), shareState.currentExpiresOption);
        } catch (err) {
            flashStatus(t(i18n, 'shareError') + ': ' + err.message);
        }
    };
    document.querySelectorAll('input[name="share-access-mode"]').forEach((el) => {
        el.addEventListener('change', async (e) => {
            await applyShareAccessMode(e.target.value || 'none');
        });
    });
    document.getElementById('share-password')?.addEventListener('change', async (e) => {
        shareState.currentRestrictions.password = String(e.target.value || '');
        if (!shareState.currentPath) return;
        try {
            await renderShareModalForPath(shareState.currentPath, shareState.currentPath.split('/').pop(), shareState.currentExpiresOption);
        } catch (err) {
            flashStatus(t(i18n, 'shareError') + ': ' + err.message);
        }
    });
    document.getElementById('share-open-limit')?.addEventListener('change', async (e) => {
        shareState.currentRestrictions.max_views = Math.max(0, Number(e.target.value || 0));
        if (!shareState.currentPath) return;
        try {
            await renderShareModalForPath(shareState.currentPath, shareState.currentPath.split('/').pop(), shareState.currentExpiresOption);
        } catch (err) {
            flashStatus(t(i18n, 'shareError') + ': ' + err.message);
        }
    });
}


/* Moved to assets/app/editors-and-tree.js */

function getFormattedCodeText(codeElement) {
    const codeId = codeElement.id;
    const messageElement = codeElement.closest('.ai-message');
    if (!messageElement || !codeId) {
        return codeElement.textContent || '';
    }
    const messageId = messageElement.id;
    const message = aiState.chatHistory.find(msg => msg.id === messageId);
    if (!message || !message.content) {
        return codeElement.textContent || '';
    }
    const htmlRegex = new RegExp(`<code[^>]*id="${codeId}"[^>]*>([\\s\\S]*?)</code>`, 'i');
    const htmlMatch = message.content.match(htmlRegex);
    if (htmlMatch && htmlMatch[1]) {
        return decodeHtmlEntities(htmlMatch[1].trim());
    }
    const anyCodeRegex = /```(?:\w+)?\n([\s\S]*?)```/;
    const codeMatch = message.content.match(anyCodeRegex);
    if (codeMatch && codeMatch[1]) {
        return codeMatch[1].trim();
    }
    const markdownBlocks = message.content.match(/```[\s\S]*?```/g);
    if (markdownBlocks && markdownBlocks.length > 0) {
        const firstBlock = markdownBlocks[0];
        const contentMatch = firstBlock.match(/```(?:\w+)?\n([\s\S]*?)```/);
        if (contentMatch && contentMatch[1]) {
            return contentMatch[1].trim();
        }
    }
    if (message.content.length < 1000 && 
        (message.content.includes('\\begin') || 
         message.content.includes('function') || 
         message.content.includes('class ') || 
         message.content.includes('def '))) {
        return message.content.trim();
    }
    return codeElement.textContent || '';
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
async function loadLocale(lang) {
  const map = { en:'en', ru:'ru', uk:'uk', be:'be', pl:'pl', cs:'cs' };
  const code = map[lang] || 'en';
  try {
    const res = await fetch(`i18n/load.php?lang=${encodeURIComponent(code)}&v=3`);
    if (res.ok) return res.json();
  } catch (e) {}
  const res = await fetch(`i18n/${code}.json?v=2`);
  return res.ok ? res.json() : {};
}
function applyTheme(theme, color) {
    const savedWidth = localStorage.getItem('sidebarWidth');
    if (savedWidth) {
        const sidebar = document.querySelector('.sidebar');
        if (sidebar) {
            sidebar.style.width = savedWidth + 'px';
        }
    }
  document.body.setAttribute('data-theme', theme);

  const style = document.createElement('style');
  const r = parseInt(color.slice(1, 3), 16);
    const g = parseInt(color.slice(3, 5), 16);
    const b = parseInt(color.slice(5, 7), 16);

  style.innerHTML = `
    :root { 
      --accent: ${color}; 
      --accent-rgb: ${r}, ${g}, ${b};
      --accent-transparent: rgba(${r}, ${g}, ${b}, 0.2);
      --panel-hover: rgba(${r}, ${g}, ${b}, 0.1); 
      --bg: ${theme === 'dark' ? '#1a1a1a' : '#ffffff'};
      --fg: ${theme === 'dark' ? '#ffffff' : '#000000'};
      --panel: ${theme === 'dark' ? '#2a2a2a' : '#f5f5f5'};
      --border: ${theme === 'dark' ? '#444' : '#ddd'};
      --muted: ${theme === 'dark' ? '#888' : '#666'};
      --scrollbar-track: ${theme === 'dark' ? '#2a2a2a' : '#f1f1f1'};
      --scrollbar-thumb: ${theme === 'dark' ? '#555' : '#c1c1c1'};
      --scrollbar-thumb-hover: ${theme === 'dark' ? '#666' : '#a8a8a8'};
    }
    .btn.primary, .btn.danger { box-shadow: none; }
    .btn.primary { background: var(--accent); border-color: var(--accent); color: white; }
    a, .icon-btn.active { color: var(--accent); }
    select {
      background: var(--panel);
      color: var(--fg);
      border: 1px solid var(--border);
      scrollbar-width: thin;
      scrollbar-color: var(--scrollbar-thumb) var(--scrollbar-track);
    }
      .tab-icon {
        color: var(--accent);
        opacity: 0.9;
        transition: opacity 0.2s;
    }
    .tab:hover .tab-icon {
        opacity: 1;
    }
    .tab.active .tab-icon {
        color: var(--accent);
        opacity: 1;
    }
    .tab.active {
      position: relative;
      border-bottom-color: transparent;
    }
    .tab.active::after {
      content: '';
      position: absolute;
      left: 8px;
      right: 8px;
      bottom: -1px;
      height: 4px;
      background: linear-gradient(to bottom, color-mix(in srgb, var(--accent) 72%, #000 28%) 0%, color-mix(in srgb, var(--accent) 45%, var(--bg) 55%) 55%, color-mix(in srgb, var(--accent) 10%, var(--bg) 90%) 100%);
      border-radius: 2px;
      pointer-events: none;
    }
    #viewer {
      position: relative !important;
      width: 100% !important;
      height: 100% !important;
      overflow: hidden !important;
    }
      .api-key-notification.success {
    background: rgba(40, 167, 69, 0.1);
    border: 1px solid #28a745;
    color: #28a745;
    }

    .api-key-notification.error {
    background: rgba(220, 53, 69, 0.1);
    border: 1px solid #dc3545;
    color: #dc3545;
    }

    .api-key-notification.warning {
    background: rgba(255, 193, 7, 0.1);
    border: 1px solid #ffc107;
    color: #ffc107;
    }

    .api-key-input:focus {
    outline: none;
    border-color: var(--accent);
    box-shadow: 0 0 0 2px rgba(var(--accent-rgb), 0.2);
    }
    .api-key-notification.success {
    background: rgba(40, 167, 69, 0.1);
    border: 1px solid #28a745;
    color: #28a745;
    }

    .api-key-notification.error {
    background: rgba(220, 53, 69, 0.1);
    border: 1px solid #dc3545;
    color: #dc3545;
    }

    .api-key-notification.warning {
    background: rgba(255, 193, 7, 0.1);
    border: 1px solid #ffc107;
    color: #ffc107;
    }

    .api-key-input:focus {
    outline: none;
    border-color: var(--accent);
    box-shadow: 0 0 0 2px rgba(var(--accent-rgb), 0.2);
    }

    #toggle-current-key-visibility:hover {
    color: var(--accent);
    transform: translateY(-50%) scale(1.1);
    }

    #toggle-current-key-visibility {
    transition: all 0.2s ease;
    }
    #viewer iframe {
      position: absolute !important;
      top: 0 !important;
      left: 0 !important;
      width: 100% !important;
      height: 100% !important;
      border: none !important;
      background: var(--bg) !important;
      z-index: 10;
    }
    .file-content-container {
      position: absolute !important;
      top: 0 !important;
      left: 0 !important;
      width: 100% !important;
      height: 100% !important;
      z-index: 5;
    }
    #file-view {
      position: relative;
      width: 100%;
      height: 100%;
      overflow: hidden;
    }
    select::-webkit-scrollbar {
      width: 8px;
    }
    select::-webkit-scrollbar-track {
      background: var(--scrollbar-track);
      border-radius: 4px;
    }
    select::-webkit-scrollbar-thumb {
      background: var(--scrollbar-thumb);
      border-radius: 4px;
    }
    select::-webkit-scrollbar-thumb:hover {
      background: var(--scrollbar-thumb-hover);
    }
    select option {
      background: var(--panel);
      color: var(--fg);
    }
    .search-highlight {
      background-color: ${theme === 'dark' ? '#ffeb3b76' : '#fff59dd7'};
      color: ${theme === 'dark' ? '#000' : '#000'};
      font: #eeeeee;
    }
    .search-current {
      background-color: ${theme === 'dark' ? '#09ff00a3' : '#53ff4da3'} !important;
      color: ${theme === 'dark' ? '#001484ff' : '#001484ff'} !important;
      font: #eeeeee;
    }
    .code-block-header {
        padding: 2px 8px !important;
        min-height: 24px !important;
        font-size: 11px !important;
    }
    .code-block-header .code-language {
        font-size: 11px !important;
    }
    .ai-message-header {
        min-height: 20px !important;
        margin-bottom: 4px !important;
    }
    .ai-regenerate-btn, .ai-copy-btn {
        width: 20px !important;
        height: 18px !important;
        padding: 2px !important;
    }
        .monaco-editor-container {
            position: absolute !important;
            top: 0 !important;
            left: 0 !important;
            width: 100% !important;
            height: 100% !important;
            overflow: hidden !important;
            background: ${theme === 'dark' ? '#1a1a1a' : '#ffffff'} !important;
            border: none !important;
            outline: none !important;
            box-shadow: none !important;
        }

        .monaco-editor-container:focus,
        .monaco-editor-container:focus-within,
        .monaco-editor,
        .monaco-editor.focused,
        .monaco-editor .overflow-guard,
        .monaco-editor .inputarea,
        .monaco-editor textarea {
            border: none !important;
            outline: none !important;
            box-shadow: none !important;
        }

        .monaco-editor .margin {
            background-color: ${theme === 'dark' ? '#1a1a1a' : '#f5f5f5'} !important;
        }

        .monaco-editor .minimap {
            background-color: ${theme === 'dark' ? '#1a1a1a' : '#f5f5f5'} !important;
        }

    /* ── Light‑theme transparency for solid‑filled elements ── */
    body[data-theme="light"] .btn.primary {
      background: color-mix(in srgb, var(--accent) 82%, transparent);
      border-color: color-mix(in srgb, var(--accent) 82%, transparent);
    }
    body[data-theme="light"] .btn.danger {
      background: color-mix(in srgb, var(--danger) 82%, transparent);
      border-color: color-mix(in srgb, var(--danger) 82%, transparent);
    }
    body[data-theme="light"] .settings-toggle input:checked + .settings-toggle-slider {
      background: color-mix(in srgb, var(--accent) 75%, transparent);
    }
    body[data-theme="light"] .lang-switcher .lang-option.active {
      background: color-mix(in srgb, var(--accent) 70%, transparent);
      color: var(--fg);
    }
    body[data-theme="light"] .progress-fill {
      background: color-mix(in srgb, var(--accent) 75%, transparent);
    }
    body[data-theme="light"] .compile-timer.running {
      background: color-mix(in srgb, var(--accent) 78%, transparent);
    }
    body[data-theme="light"] #floating-upload-btn,
    body[data-theme="light"] .floating-download-btn {
      background: color-mix(in srgb, var(--accent) 80%, transparent);
    }
    body[data-theme="light"] #btn-ai-send {
      background: color-mix(in srgb, var(--accent) 82%, transparent);
      border-color: color-mix(in srgb, var(--accent) 82%, transparent);
    }
    body[data-theme="light"] #btn-clear-ai-history {
      background: color-mix(in srgb, var(--danger) 82%, transparent);
      border-color: color-mix(in srgb, var(--danger) 82%, transparent);
    }

    /* ── Light‑theme transparency for selection / highlight elements ── */
    body[data-theme="light"] .tree .node:hover {
      background: color-mix(in srgb, rgb(131,131,131) 45%, transparent);
    }
    body[data-theme="light"] .dropdown-item:hover {
      background: color-mix(in srgb, var(--accent) 82%, transparent);
      color: white;
    }
    body[data-theme="light"] .move-tree-label:hover {
      background: color-mix(in srgb, var(--border) 55%, transparent) !important;
    }
    body[data-theme="light"] .node:focus {
      background: color-mix(in srgb, var(--accent) 82%, transparent);
      color: white;
    }

  `;
  document.head.appendChild(style);

  if (window.monacoManager) {
        window.monacoManager.updateTheme();
    }

}
function translateUI(i18n) {
  const dict = {
    title: 'appTitle'
  };
  Object.entries(dict).forEach(([id, key])=>{
    const el = document.getElementById(id);
    if (el) el.textContent = i18n[key] || key;
  });
  document.querySelectorAll('[data-i18n]').forEach(el=>{
    const key = el.getAttribute('data-i18n');
    const translation = i18n[key] || key;
    // Use innerHTML for translations that contain HTML tags
    if (key === 'aboutText' || key === 'encryptedFolderWarningText' || translation.includes('<strong>') || translation.includes('<em>') || translation.includes('<br>')) {
      el.innerHTML = translation;
    } else if (el.closest('#code-actions-menu')) {
      // Preserve the SVG icon and update only the text node
      const svg = el.querySelector('svg');
      if (svg) {
        el.innerHTML = svg.outerHTML + '<span class="menu-item-label">' + translation + '</span>';
      } else {
        el.textContent = translation;
      }
    } else {
      el.textContent = translation;
    }
  });
  document.querySelectorAll('[data-i18n-placeholder]').forEach(el => {
    el.placeholder = t(i18n, el.dataset.i18nPlaceholder);
  });
  document.querySelectorAll('[data-i18n-title]').forEach(el => {
    el.title = t(i18n, el.dataset.i18nTitle);
  });
  document.querySelectorAll('#sort-filter-menu [data-i18n]').forEach(el => {
    const key = el.getAttribute('data-i18n');
    if (i18n[key]) {
      el.textContent = i18n[key];
    }
  });
  document.querySelectorAll('.lang-option[data-i18n-title]').forEach(el => {
    el.title = t(i18n, el.dataset.i18nTitle);
  });
  const roleSelect = document.getElementById('ai-role-select');
  if (roleSelect) {
    roleSelect.options[0].text = t(i18n, 'generalAssistant');
    roleSelect.options[1].text = t(i18n, 'programmer');
    roleSelect.options[2].text = t(i18n, 'searchAssistant');
  }
  document.getElementById('btn-save').title = i18n.save || 'Save';
  document.getElementById('btn-monaco-undo').title = i18n.undo || 'Undo';
  document.getElementById('btn-monaco-redo').title = i18n.redo || 'Redo';
  document.getElementById('btn-download').title = i18n.download;
  document.getElementById('btn-copy').title = i18n.copy;
  const groupChatBtn = document.getElementById('btn-group-chat');
  if (groupChatBtn) groupChatBtn.title = t(i18n, 'groupChatTitle');
  document.getElementById('search-input').placeholder = i18n.search || 'Search...';
  updateTrashToolbarSelectionState();
  
  const settingsButton = document.getElementById('btn-settings');
  if (settingsButton) {
    settingsButton.title = t(i18n, 'settings');
  }
  
  // Also update the compile button text if the window is open
  const compileSendBtn = document.getElementById('btn-compile-send');
  if (compileSendBtn && !document.getElementById('compile-modal').classList.contains('hidden')) {
    compileSendBtn.textContent = t(i18n, 'compileAndRun');
  }
  
  const compileInput = document.getElementById('compile-input-text');
  if (compileInput && !document.getElementById('compile-modal').classList.contains('hidden')) {
    compileInput.placeholder = t(i18n, 'enterProgramInput');
  }

  if (window.aiAssistant && typeof window.aiAssistant.refreshI18n === 'function') {
    window.aiAssistant.refreshI18n();
  }
}
let tabsState = {
    tabs: [],
    activeTabId: null,
    maxTabs: window.innerWidth <= 768 ? 4 : 10,
    nextTabId: 1,
    contentCache: new Map(),
    iframeCache: new Map(),
    openFileTokens: new Map()
};
window.tabsState = tabsState;
let tabPreviewHoverTimer = null;
const RECENT_FILES_KEY = 'cloudRecentFiles';
let recentFilesSelection = 0;
let recentFilesPruning = false;
function getViewerElement() {
    return document.getElementById('viewer');
}
function loadRecentFiles() {
    try {
        const rows = JSON.parse(localStorage.getItem(RECENT_FILES_KEY) || '[]');
        return Array.isArray(rows) ? rows.filter((row) => row && row.path && row.type !== 'dir').slice(0, 12) : [];
    } catch (_) {
        return [];
    }
}
async function pruneNonExistentRecentFiles() {
    if (recentFilesPruning) return;
    recentFilesPruning = true;
    try {
        const rows = loadRecentFiles();
        if (!rows.length) return;
        const checks = await Promise.all(rows.map(async (row) => {
            try {
                const res = await fetch(`api.php?action=file_info&path=${encodeURIComponent(row.path)}&root=${encodeURIComponent(row.root || 'main')}&v=${Date.now()}`, { cache: 'no-store' });
                return { row, exists: res.ok };
            } catch (_) {
                return { row, exists: false };
            }
        }));
        const existing = checks.filter((c) => c.exists).map((c) => c.row);
        const removed = checks.length - existing.length;
        if (removed > 0) {
            localStorage.setItem(RECENT_FILES_KEY, JSON.stringify(existing));
            recentFilesSelection = Math.max(0, Math.min(recentFilesSelection, existing.length - 1));
            if (tabsState.tabs.length === 0) {
                renderViewerEmptyState();
            }
        }
    } finally {
        recentFilesPruning = false;
    }
}
function saveRecentFile(path, root = null) {
    if (!path) return;
    const file = { path, root: root || getCurrentCloudRoot(), title: String(path).split('/').pop(), ts: Date.now() };
    const rows = loadRecentFiles().filter((row) => !(row.path === file.path && row.root === file.root));
    rows.unshift(file);
    localStorage.setItem(RECENT_FILES_KEY, JSON.stringify(rows.slice(0, 12)));
    if (tabsState.tabs.length === 0) renderViewerEmptyState();
}
function recentFileEmptyIcon() {
    return '<svg class="viewer-empty-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><path d="M14 2v6h6"></path><path d="M9 15h6"></path><path d="M9 18h3"></path></svg>';
}
function renderViewerEmptyState() {
    const viewer = getViewerElement();
    const fileView = document.getElementById('file-view');
    if (!viewer || !fileView || tabsState.tabs.length > 0) return;
    if ((typeof isTrashRoot === 'function' && isTrashRoot()) || document.body.classList.contains('trash-mode')) return;
    fileView.classList.remove('hidden');
    viewer.innerHTML = '';
    viewer.dataset.path = '';
    viewer.dataset.editor = '';
    viewer.dataset.empty = 'true';
    viewer.classList.add('viewer-empty-state');
    const rows = loadRecentFiles();
    const wrap = document.createElement('div');
    wrap.className = 'viewer-empty';
    if (!rows.length) {
        wrap.innerHTML = `${recentFileEmptyIcon()}<div class="viewer-empty-title">${escapeHtml(t(i18n, 'noRecentFiles'))}</div><div class="viewer-empty-subtitle">${escapeHtml(t(i18n, 'noRecentFilesHint'))}</div>`;
        viewer.appendChild(wrap);
        return;
    }
    recentFilesSelection = Math.max(0, Math.min(recentFilesSelection, rows.length - 1));
    wrap.innerHTML = `<div class="viewer-empty-title">${escapeHtml(t(i18n, 'recentFiles'))}</div><div class="viewer-recent-list" role="listbox" aria-label="${escapeHtml(t(i18n, 'recentFiles'))}"></div>`;
    const list = wrap.querySelector('.viewer-recent-list');
    rows.forEach((row, index) => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'viewer-recent-item';
        btn.dataset.index = String(index);
        btn.dataset.path = row.path;
        btn.dataset.root = row.root || 'main';
        btn.setAttribute('role', 'option');
        btn.setAttribute('aria-selected', index === recentFilesSelection ? 'true' : 'false');
        btn.innerHTML = `${getFileIconMarkup(row.title || row.path, 'viewer-recent-icon')}<span class="viewer-recent-text"><span class="viewer-recent-name">${escapeHtml(row.title || row.path)}</span><span class="viewer-recent-path">${escapeHtml(formatDisplayPath(row.path))}</span></span>`;
        btn.addEventListener('click', () => openRecentFile(index));
        list.appendChild(btn);
    });
    viewer.appendChild(wrap);
    updateRecentFilesSelection();
    if (!recentFilesPruning && rows.length > 0) {
        pruneNonExistentRecentFiles();
    }
}
function updateRecentFilesSelection() {
    document.querySelectorAll('.viewer-recent-item').forEach((item) => {
        item.classList.toggle('selected', Number(item.dataset.index) === recentFilesSelection);
        item.setAttribute('aria-selected', Number(item.dataset.index) === recentFilesSelection ? 'true' : 'false');
    });
}
function openRecentFile(index = recentFilesSelection) {
    const rows = loadRecentFiles();
    const row = rows[index];
    if (!row) return;
    openFileInTab(row.path, false, row.root || 'main');
}
function initRecentFilesKeyboard() {
    if (window.recentFilesKeyboardInitialized) return;
    window.recentFilesKeyboardInitialized = true;
    document.addEventListener('keydown', (event) => {
        if (tabsState.tabs.length > 0) return;
        const tag = (document.activeElement?.tagName || '').toUpperCase();
        if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT' || document.activeElement?.isContentEditable) return;
        const rows = loadRecentFiles();
        if (!rows.length || !['ArrowUp', 'ArrowDown', 'Enter'].includes(event.key)) return;
        event.preventDefault();
        if (event.key === 'ArrowUp') recentFilesSelection = (recentFilesSelection - 1 + rows.length) % rows.length;
        if (event.key === 'ArrowDown') recentFilesSelection = (recentFilesSelection + 1) % rows.length;
        if (event.key === 'Enter') openRecentFile();
        updateRecentFilesSelection();
    });
}
function getOrCreateFileContainer(tabId) {
    const viewer = getViewerElement();
    if (!viewer) return null;
    let fileContainer = viewer.querySelector('.file-content-container[data-tab-id="' + tabId + '"]');
    if (!fileContainer) {
        fileContainer = document.createElement('div');
        fileContainer.className = 'file-content-container';
        fileContainer.setAttribute('data-tab-id', String(tabId));
        fileContainer.style.cssText = 'position:absolute;top:0;left:0;width:100%;height:100%;display:none;z-index:5;';
        viewer.appendChild(fileContainer);
    }
    return fileContainer;
}
function hideAllTabPanels(exceptTabId) {
    const viewer = getViewerElement();
    if (!viewer) return;
    const exceptId = exceptTabId == null ? null : String(exceptTabId);
    viewer.querySelectorAll('.file-content-container').forEach((container) => {
        const tabId = container.getAttribute('data-tab-id');
        container.style.display = tabId === exceptId ? 'block' : 'none';
    });
    viewer.querySelectorAll('iframe[data-tab-id]').forEach((iframe) => {
        const tabId = iframe.getAttribute('data-tab-id');
        if (tabId === exceptId) {
            applyFullScreenStyles(iframe);
            iframe.style.display = 'block';
        } else {
            iframe.style.display = 'none';
        }
    });
    Array.from(viewer.children).forEach((child) => {
        if (child.classList && child.classList.contains('file-content-container')) return;
        if (child.matches && child.matches('iframe[data-tab-id]')) return;
        if (child.hasAttribute && child.hasAttribute('data-tab-id')) {
            child.style.display = child.getAttribute('data-tab-id') === exceptId ? 'block' : 'none';
        }
    });
}
function tabHasLiveView(tab) {
    if (!tab) return false;
    const viewer = getViewerElement();
    if (!viewer) return false;
    const container = viewer.querySelector('.file-content-container[data-tab-id="' + tab.id + '"]');
    if (container && container.childElementCount > 0) return true;
    if (viewer.querySelector('iframe[data-tab-id="' + tab.id + '"]')) return true;
    if (tabsState.iframeCache.has(tab.id)) return true;
    return false;
}
function stashTabView(tab) {
    if (!tab) return;
    const viewer = getViewerElement();
    if (!viewer) return;
    const cacheData = {
        timestamp: Date.now(),
        path: tab.path,
        photoEditor: !!tab.photoEditor,
        latexEditor: !!tab.latexEditor,
        transcriptionEditor: !!tab.transcriptionEditor,
        ttsEditor: !!tab.ttsEditor,
        onlyOfficeEditor: !!tab.onlyOfficeEditor,
        zipViewer: !!tab.zipViewer,
        fullPath: tab.fullPath || null,
        html: null,
        type: 'file'
    };
    const editorIframe = viewer.querySelector('iframe[data-tab-id="' + tab.id + '"]');
    if (tab.photoEditor || tab.latexEditor || tab.transcriptionEditor || tab.ttsEditor || tab.onlyOfficeEditor || tab.zipViewer || tab.markdownEditor || tab.htmlEditor) {
        if (editorIframe) {
            cacheData.type = 'iframe';
            cacheData.src = editorIframe.src;
            tabsState.iframeCache.set(tab.id, editorIframe);
        }
    } else {
        const container = viewer.querySelector('.file-content-container[data-tab-id="' + tab.id + '"]');
        if (container && container.childElementCount > 0) {
            cacheData.type = 'image';
            cacheData.html = container.innerHTML;
            cacheData.scrollLeft = container.scrollLeft || 0;
            cacheData.scrollTop = container.scrollTop || 0;
            const iframe = container.querySelector('iframe');
            if (iframe) {
                cacheData.iframeSrc = iframe.src;
                tabsState.iframeCache.set(tab.id, iframe);
            }
        } else if (editorIframe) {
            cacheData.type = 'iframe';
            cacheData.src = editorIframe.src;
            tabsState.iframeCache.set(tab.id, editorIframe);
        }
    }
    tabsState.contentCache.set(tab.id, cacheData);
}
function revealTabView(tab) {
    const viewer = getViewerElement();
    if (!viewer || !tab) return false;
    hideAllTabPanels(tab.id);
    const cachedIframe = tabsState.iframeCache.get(tab.id);
    if (cachedIframe) {
        if (cachedIframe.parentNode !== viewer) {
            viewer.appendChild(cachedIframe);
        }
        applyFullScreenStyles(cachedIframe);
        cachedIframe.style.display = 'block';
    }
    const container = viewer.querySelector('.file-content-container[data-tab-id="' + tab.id + '"]');
    if (container && container.childElementCount > 0) {
        container.style.display = 'block';
        return true;
    }
    if (cachedIframe && cachedIframe.parentNode === viewer) {
        return true;
    }
    return false;
}
function clearCachedContent(path) {
    contentCache.delete(path);
}
function handleSearch(e) {
  const query = e.target.value.trim();
  const clearBtn = document.getElementById('btn-clear-search');
  clearBtn.classList.toggle('hidden', query === '');
  if (searchTimeout) {
    clearTimeout(searchTimeout);
  }
  if (query.length < 2) {
    buildTree('');
    return;
  }
  searchTimeout = setTimeout(() => {
    searchFiles(query);
  }, 500);
}
function clearSearch() {
  const searchInput = document.getElementById('search-input');
  const clearBtn = document.getElementById('btn-clear-search');
  searchInput.value = '';
  clearBtn.classList.add('hidden');
  buildTree('');
}
function isBinaryFile(path) {
    const ext = path.split('.').pop().toLowerCase();
    const binaryExts = [
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 
        'mp4', 'webm', 'ogg', 'mov', 'avi', 'mkv',
        'mp3', 'wav', 'ogg', 'm4a', 'flac',
        'obj', 'stl', '3mf',
        'jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'tiff',
        'zip', 'rar', '7z', 'tar', 'gz'
    ];
    return binaryExts.includes(ext);
}
function initTabs() {
    const savedTabs = localStorage.getItem('fileTabs');
    if (savedTabs) {
        try {
            const parsed = JSON.parse(savedTabs);
            tabsState.tabs = parsed.tabs || [];
            tabsState.activeTabId = parsed.activeTabId;
            tabsState.nextTabId = parsed.nextTabId || 1;
            
            // ADD THIS CODE - restore the user for LaTeX and other editors
            
            
            tabsState.tabs.forEach(tab => {
                if (!tab.root) {
                    const p = getRootFromPath(String(tab.path || ''));
                    tab.root = p !== 'main' ? p : (localStorage.getItem('cloudRoot') || 'main');
                }
                if (tab.photoEditor || tab.latexEditor || tab.transcriptionEditor || tab.onlyOfficeEditor || tab.markdownEditor || tab.htmlEditor || tab.zipViewer || tab.ttsEditor) {
                    tab.needsIframeRefresh = true;
                }
                if (tab.zipViewer && !tab.iframeSrc) {
                    tab.needsIframeRefresh = true;
                }
            });
            
            if (tabsState.activeTabId && tabsState.tabs.length > 0) {
                const activeTab = tabsState.tabs.find(tab => tab.id === tabsState.activeTabId);
                if (activeTab) {
                    restoreTabContent(activeTab);
                }
            }
            renderTabs();
        } catch (e) {
            console.error('Failed to restore tabs:', e);
        }
    }
}
function saveTabsState() {
    const stateToSave = {
        tabs: tabsState.tabs.map(({ content, ...rest }) => {
            // ADD THIS CHECK - save fullPath for LaTeX and other editors
            if (rest.zipViewer && rest.fullPath) {
                return { ...rest, fullPath: rest.fullPath };
            }
            return rest;
        }), 
        activeTabId: tabsState.activeTabId,
        nextTabId: tabsState.nextTabId,
    };
    localStorage.setItem('fileTabs', JSON.stringify(stateToSave));
}
function getEffectiveCloudTheme(urlParams) {
    if (window.APP_CONFIG && (window.APP_CONFIG.theme === 'light' || window.APP_CONFIG.theme === 'dark')) {
        return window.APP_CONFIG.theme;
    }
    const t = (urlParams ? urlParams.get('theme') : null) || 'dark';
    return t === 'light' ? 'light' : 'dark';
}
function refreshAllEditorIframes() {
    tabsState.tabs.forEach(tab => {
        if (tab.photoEditor || tab.latexEditor || tab.transcriptionEditor ||
            tab.onlyOfficeEditor || tab.markdownEditor || tab.htmlEditor || tab.zipViewer || tab.ttsEditor) {
            
            const iframe = tabsState.iframeCache.get(tab.id);
            if (iframe) {
                const currentSrc = iframe.src;
                const urlParams = new URLSearchParams(window.location.search);
                const currentLang = urlParams.get('lang') || 'en';
                const currentTheme = getEffectiveCloudTheme(urlParams);
                const currentColor = urlParams.get('color') || '#85b503';
                
                if (!currentSrc.includes('lang=' + currentLang) || 
                    !currentSrc.includes('theme=' + currentTheme) ||
                    !currentSrc.includes('color=' + currentColor)) {
                    
                    
                    let currentUser = currentUserState.user || 'default';
                    currentUser = currentUser.replace(/\s+/g, '_');
                    const root = getResolvedTabRoot(tab);
                    let newUrl;

                    if (tab.photoEditor) {
                        const fullPath = buildEditorStoragePath(currentUser, tab.path, root);
                        newUrl = `utilities/projects/photo-editor/public/index.php?path=${encodeURIComponent(fullPath)}&user=${encodeURIComponent(currentUser)}&lang=${currentLang}&theme=${currentTheme}&color=${encodeURIComponent(currentColor)}&root=${root}`;
                    } else if (tab.latexEditor) {
                        newUrl = `utilities/projects/latex-editor/public/index.php?path=${encodeURIComponent(tab.path)}&user=${encodeURIComponent(currentUser)}&lang=${currentLang}&theme=${currentTheme}&color=${encodeURIComponent(currentColor)}&root=${root}`;
                    } else if (tab.transcriptionEditor) {
                        newUrl = buildCloudProjectUrl('utilities/projects/transcription-editor/public/index.php', {
                            path: tab.path,
                            user: currentUser,
                            lang: currentLang,
                            theme: currentTheme,
                            color: currentColor,
                            root
                        });
                    } else if (tab.ttsEditor) {
                        newUrl = buildCloudProjectUrl('utilities/projects/tts-editor/public/index.php', {
                            path: tab.path,
                            user: currentUser,
                            lang: currentLang,
                            theme: currentTheme,
                            color: currentColor,
                            root
                        });
                    } else if (tab.onlyOfficeEditor) {
                        newUrl = buildOnlyOfficeEditorUrl(tab, currentUser, currentLang, currentTheme, currentColor, root);
                    } else if (tab.zipViewer) {
                        const fullZipPath = buildEditorStoragePath(currentUser, tab.path, root);
                        tab.fullPath = fullZipPath;
                        newUrl = `utilities/projects/zip-viewer/public/index.php?archive=${encodeURIComponent(fullZipPath)}&path=${encodeURIComponent(tab.path)}&user=${encodeURIComponent(currentUser)}&root=${encodeURIComponent(root)}&lang=${currentLang}&theme=${currentTheme}&folderColor=${encodeURIComponent(currentColor)}`;
                    } else if (tab.markdownEditor) {
                        newUrl = `utilities/projects/markdown-editor/public/index.php?path=${encodeURIComponent(tab.path)}&user=${encodeURIComponent(currentUser)}&lang=${currentLang}&theme=${currentTheme}&color=${encodeURIComponent(currentColor)}&root=${root}`;
                    } else if (tab.htmlEditor) {
                        newUrl = `utilities/projects/html-editor/public/index.php?path=${encodeURIComponent(tab.path)}&user=${encodeURIComponent(currentUser)}&lang=${currentLang}&theme=${currentTheme}&color=${encodeURIComponent(currentColor)}&root=${root}`;
                    }
                    
                    if (newUrl) {
                        iframe.src = newUrl;
                        tab.iframeSrc = newUrl;
                    }
                }
            }
        }
    });
}
// Find the openFileInTab function in app.js (around line 550)
function openFileInTab(path, editMode = false, root = null) {
    // Check whether the device is mobile
    const isMobile = isMobileDevice();
    
    // Only on mobile, collapse the sidebar when opening in edit mode
    if (isMobile && editMode) {
        const sidebar = document.querySelector('.sidebar');
        if (sidebar && sidebar.classList.contains('active')) {
            sidebar.classList.remove('active');
            localStorage.setItem('sidebarVisible', 'false');
        }
    }
    
    const isLatexEditor = editMode && (path.toLowerCase().endsWith('.tex') || path.toLowerCase().endsWith('.latex'));
    const requestedRoot = root || null;
    const existingTab = tabsState.tabs.find(tab => tab.path === path && (!requestedRoot || getTabRoot(tab) === requestedRoot));
    let inheritedRoot = requestedRoot || (existingTab ? existingTab.root : null);
    if (existingTab) {
        if (editMode && !existingTab.editMode) {
            closeTab(existingTab.id);
        } else {
            switchToTab(existingTab.id);
            return;
        }
    }
    if (tabsState.tabs.length >= tabsState.maxTabs) {
        const lastTab = tabsState.tabs[tabsState.tabs.length - 1];
        closeTab(lastTab.id);
        flashStatus(t(i18n, 'tabLimitReached', { max: tabsState.maxTabs }));
    }
    const tabId = tabsState.nextTabId++;
    const newTab = {
        id: tabId,
        path: path,
        editMode: editMode,
        latexEditor: isLatexEditor,
        photoEditor: false,
        root: inheritedRoot || getCurrentCloudRoot(),
        title: path.split('/').pop(),
        modified: false,
        content: null
    };
    
    if (tabsState.activeTabId) {
        const prevTab = tabsState.tabs.find((t) => t.id === tabsState.activeTabId);
        if (prevTab) stashTabView(prevTab);
        const activeIndex = tabsState.tabs.findIndex(tab => tab.id === tabsState.activeTabId);
        if (activeIndex !== -1) {
            tabsState.tabs.splice(activeIndex + 1, 0, newTab);
        } else {
            tabsState.tabs.push(newTab);
        }
    } else {
        tabsState.tabs.push(newTab);
    }
    
    tabsState.activeTabId = tabId;
    renderTabs();
    saveRecentFile(path, newTab.root);
    
    if (isLatexEditor) {
        loadLatexEditorToTab(newTab);
    } else {
        loadFileContentToTab(newTab);
    }
    
    saveTabsState();
}
async function loadFileContentToTab(tab) {
    const viewer = document.getElementById('viewer');
    const fileName = document.getElementById('file-name');
    document.getElementById('file-view').classList.remove('hidden');
    fileName.textContent = tab.title;
    viewer.dataset.path = tab.path;
    viewer.dataset.editMode = tab.editMode.toString();
    viewer.dataset.tabRoot = getResolvedTabRoot(tab);
    updateToolbarForTab(tab);
        await openFile(tab.path, tab.editMode, null, getResolvedTabRoot(tab));
}
function isMarkdownFile(path) {
    const ext = path.split('.').pop().toLowerCase();
    return ext === 'md' || ext === 'markdown';
}
function openMarkdownEditor(filePath, root = null) {
    const requestedRoot = root || null;
    const existingViewTab = tabsState.tabs.find(tab => tab.path === filePath && !tab.markdownEditor && (!requestedRoot || getTabRoot(tab) === requestedRoot));
    const inheritedRoot = requestedRoot || (existingViewTab ? existingViewTab.root : null);
    if (existingViewTab) {
        closeTab(existingViewTab.id);
    }
    const existingEditorTab = tabsState.tabs.find(tab => tab.path === filePath && tab.markdownEditor && (!requestedRoot || getTabRoot(tab) === requestedRoot));
    if (existingEditorTab) {
        switchToTab(existingEditorTab.id);
        return;
    }
    if (tabsState.tabs.length >= tabsState.maxTabs) {
        const lastTab = tabsState.tabs[tabsState.tabs.length - 1];
        closeTab(lastTab.id);
        flashStatus(t(i18n, 'tabLimitReached', { max: tabsState.maxTabs }));
    }
    const tabId = tabsState.nextTabId++;
    const newTab = {
        id: tabId,
        path: filePath,
        editMode: true,
        markdownEditor: true,
        root: inheritedRoot || getCurrentCloudRoot(),
        title: filePath.split('/').pop(),
        modified: false,
        content: null
    };
    if (tabsState.activeTabId) {
        const activeIndex = tabsState.tabs.findIndex(tab => tab.id === tabsState.activeTabId);
        if (activeIndex !== -1) {
            tabsState.tabs.splice(activeIndex + 1, 0, newTab);
        } else {
            tabsState.tabs.push(newTab);
        }
    } else {
        tabsState.tabs.push(newTab);
    }
    tabsState.activeTabId = tabId;
    renderTabs();
    saveRecentFile(filePath, newTab.root);
    loadMarkdownEditorToTab(newTab);
    saveTabsState();
}
function loadMarkdownEditorToTab(tab) {
    const viewer = document.getElementById('viewer');
    const fileName = document.getElementById('file-name');
    const fileContainers = viewer.querySelectorAll('.file-content-container');
    fileContainers.forEach(container => {
        container.style.display = 'none';
    });
    viewer.style.cssText = `
        position: relative;
        width: 100%;
        height: 100%;
        overflow: hidden;
    `;
    const fileExt = tab.path.split('.').pop().toLowerCase();
    const supportedMarkdownFormats = ['md', 'markdown'];
    if (!supportedMarkdownFormats.includes(fileExt)) {
        flashStatus(t(i18n, 'unsupportedMarkdownFormat'));
        return;
    }
    document.getElementById('file-view').classList.remove('hidden');
    fileName.textContent = tab.title;
    viewer.dataset.path = tab.path;
    viewer.dataset.editMode = 'true';
    viewer.dataset.markdownEditor = 'true';
    updateToolbarForMarkdownEditor();
    let iframe = viewer.querySelector('iframe[data-tab-id="' + tab.id + '"]');
    if (!iframe) {
        iframe = document.createElement('iframe');
        iframe.setAttribute('data-tab-id', tab.id);
        const urlParams = new URLSearchParams(window.location.search);
        const lang = urlParams.get('lang') || 'en';
        const theme = getEffectiveCloudTheme(urlParams);
        const color = urlParams.get('color') || '#85b503';
        
        let currentUser = currentUserState.user || 'default';
        currentUser = currentUser.replace(/\s+/g, '_');
        const markdownEditorUrl = `utilities/projects/markdown-editor/public/index.php?path=${encodeURIComponent(tab.path)}&user=${encodeURIComponent(currentUser)}&lang=${lang}&theme=${theme}&color=${encodeURIComponent(color)}&root=${getResolvedTabRoot(tab)}`;
        iframe.src = markdownEditorUrl;
        applyFullScreenStyles(iframe);
        viewer.appendChild(iframe);
    } else {
        applyFullScreenStyles(iframe);
        iframe.style.display = 'block';
    }
}
window.addEventListener('message', function(event) {
    if (event.data.type === 'closeOnlyOfficeEditor') {
        const activeTab = tabsState.tabs.find(tab => tab.id === tabsState.activeTabId);
        if (activeTab && activeTab.onlyOfficeEditor) {
            closeTab(activeTab.id);
        }
    } else if (event.data.type === 'onlyOfficeLoaded') {
        if (event.data.success) {
            flashStatus(t(i18n, 'onlyOfficeLoaded'));
        } else {
            flashStatus(t(i18n, 'onlyOfficeLoadError') + ': ' + event.data.error);
        }
    }

    if (event.data.type === 'UPDATE_LATEX_USER') {
        const activeTab = tabsState.tabs.find(tab => tab.id === tabsState.activeTabId);
        if (activeTab && activeTab.latexEditor && event.data.user) {
            activeTab.fullPath = buildUserStoragePath(event.data.user, activeTab.path, getTabRoot(activeTab));
            saveTabsState();
        }
    }
    
    if (event.data.type === 'photoEditorSaved') {
        const activeTab = tabsState.tabs.find(tab => tab.id === tabsState.activeTabId);
        if (activeTab && activeTab.photoEditor && event.data.relativePath) {
            activeTab.path = event.data.relativePath;
            activeTab.title = event.data.relativePath.split('/').pop();
            renderTabs();
            saveTabsState();
            const viewer = document.getElementById('viewer');
            viewer.dataset.path = event.data.relativePath;
            document.getElementById('file-name').textContent = activeTab.title;
            flashStatus(t(i18n, 'fileSaved'));
        }
    }
    if (event.data.type === 'UPDATE_TAB_TITLE') {
        const activeTab = tabsState.tabs.find(tab => tab.id === tabsState.activeTabId);
        if (activeTab && event.data.title) {
            activeTab.title = event.data.title;
            activeTab.isImage = true; // Add the flag
            renderTabs();
            document.getElementById('file-name').textContent = event.data.title;
        }
    }
    
    if (event.data.type === 'transcriptionSaved') {
        flashStatus(t(i18n, 'transcriptionSaved'));
    }
    if (event.data.type === 'closeTranscriptionEditor') {
        const activeTab = tabsState.tabs.find(tab => tab.id === tabsState.activeTabId);
        if (activeTab && activeTab.transcriptionEditor) {
            closeTab(activeTab.id);
        }
    }
    if (event.data.type === 'fileCreated') {
        // Refresh file list when a new file is created (from TTS or transcription editor)
        const filePath = event.data.path;

        // Invalidate cache and rebuild tree
        cachedFileTree = null;

        if (filePath) {
            // Rebuild tree from server (which now has the new file)
            if (getCurrentCloudRoot() === 'main') {
                buildTree('').then(() => {
                    // After tree is rebuilt, highlight the new file
                    pinTreePaths([filePath], { scrollToLast: true });
                });
            } else {
                buildTree('');
            }
        } else {
            buildTree('');
        }

        flashStatus(t(i18n, 'fileSaved'));
    }
    if (event.data.type === 'PDF_SAVED') {
        const pdfPath = event.data.path;
        if (pdfPath) {
            if (getCurrentCloudRoot() === 'main' && typeof window.highlightNewFilesInTree === 'function') {
                void window.highlightNewFilesInTree([pdfPath], { openLastPdf: true });
            } else if (getCurrentCloudRoot() === 'main') {
                const pdfName = pdfPath.split('/').pop();
                const now = Math.floor(Date.now() / 1000);
                const newItem = { name: pdfName, path: pdfPath, type: 'file', size: 0, mtime: now };
                patchTreeCache([{ op: 'add', item: newItem }]);
                treeNodeAdd(newItem);
                pinTreePaths([pdfPath], { scrollToLast: true });
            } else {
                void (async () => {
                    const pdfName = pdfPath.split('/').pop();
                    const now = Math.floor(Date.now() / 1000);
                    await insertTreeItem({ name: pdfName, path: pdfPath, type: 'file', size: 0, mtime: now }, getCurrentCloudRoot());
                    try { await openFileInTab(pdfPath, false); } catch (_) {}
                })();
            }
        } else {
            cachedFileTree = null;
            try { api('invalidate_file_tree', {}, 'POST'); } catch(_) {}
        }
        flashStatus(t(i18n, 'pdfCreated'));
    }
    if (event.data.type === 'MARKDOWN_SAVED') {
        const mdPath = event.data.path;
        if (mdPath) {
            cachedFileTree = null;
            try { api('invalidate_file_tree', {}, 'POST'); } catch(_) {}
        }
    }
    if (event.data.type === 'markdownEditorStatus') {
        if (event.data.fromShowStatus && event.data.message) {
            flashStatus(event.data.message);
        }
    }
    if (event.data.type === 'markdownEditorSaved') {
        const activeTab = tabsState.tabs.find(tab => tab.id === tabsState.activeTabId);
        if (activeTab && activeTab.markdownEditor) {
            flashStatus(t(i18n, 'fileSaved'));
        }
    }
    if (event.data.type === 'closeMarkdownEditor') {
        const activeTab = tabsState.tabs.find(tab => tab.id === tabsState.activeTabId);
        if (activeTab && activeTab.markdownEditor) {
            closeTab(activeTab.id);
        }
    }
    if (event.data.type === 'loadMarkdownContent' && event.data.content) {
        const activeTab = tabsState.tabs.find(tab => tab.id === tabsState.activeTabId);
        if (activeTab && activeTab.markdownEditor) {
            const iframe = document.querySelector(`iframe[data-tab-id="${activeTab.id}"]`);
            if (iframe && iframe.contentWindow) {
                iframe.contentWindow.postMessage({
                    type: 'loadMarkdownContent',
                    content: event.data.content
                }, '*');
            }
        }
    }
    if (event.data.type === 'HTML_SAVED') {
        const htmlPath = event.data.path;
        if (htmlPath) {
            cachedFileTree = null;
            try { api('invalidate_file_tree', {}, 'POST'); } catch(_) {}
        }
    }
    if (event.data.type === 'htmlEditorStatus') {
        if (event.data.fromShowStatus && event.data.message) {
            flashStatus(event.data.message);
        }
    }
    if (event.data.type === 'htmlEditorSaved') {
        const activeTab = tabsState.tabs.find(tab => tab.id === tabsState.activeTabId);
        if (activeTab && activeTab.htmlEditor) {
            flashStatus(t(i18n, 'fileSaved'));
        }
    }
    if (event.data.type === 'closeHtmlEditor') {
        const activeTab = tabsState.tabs.find(tab => tab.id === tabsState.activeTabId);
        if (activeTab && activeTab.htmlEditor) {
            closeTab(activeTab.id);
        }
    }
    if (event.data.type === 'loadHtmlContent' && event.data.content) {
        const activeTab = tabsState.tabs.find(tab => tab.id === tabsState.activeTabId);
        if (activeTab && activeTab.htmlEditor) {
            const iframe = document.querySelector(`iframe[data-tab-id="${activeTab.id}"]`);
            if (iframe && iframe.contentWindow) {
                iframe.contentWindow.postMessage({
                    type: 'loadHtmlContent',
                    content: event.data.content
                }, '*');
            }
        }
    }
});
function updateToolbarForMarkdownEditor() {
    const saveBtn = document.getElementById('btn-save');
    const codeActionsBtn = document.getElementById('btn-code-actions');
    saveBtn.classList.add('hidden');
    codeActionsBtn.classList.add('hidden');
}
function openHtmlEditor(filePath, root = null) {
    const requestedRoot = root || null;
    const existingViewTab = tabsState.tabs.find(tab => tab.path === filePath && !tab.htmlEditor && (!requestedRoot || getTabRoot(tab) === requestedRoot));
    const inheritedRoot = requestedRoot || (existingViewTab ? existingViewTab.root : null);
    if (existingViewTab) {
        closeTab(existingViewTab.id);
    }
    const existingEditorTab = tabsState.tabs.find(tab => tab.path === filePath && tab.htmlEditor && (!requestedRoot || getTabRoot(tab) === requestedRoot));
    if (existingEditorTab) {
        switchToTab(existingEditorTab.id);
        return;
    }
    if (tabsState.tabs.length >= tabsState.maxTabs) {
        const lastTab = tabsState.tabs[tabsState.tabs.length - 1];
        closeTab(lastTab.id);
        flashStatus(t(i18n, 'tabLimitReached', { max: tabsState.maxTabs }));
    }
    const tabId = tabsState.nextTabId++;
    const newTab = {
        id: tabId,
        path: filePath,
        editMode: true,
        htmlEditor: true,
        root: inheritedRoot || getCurrentCloudRoot(),
        title: filePath.split('/').pop(),
        modified: false,
        content: null
    };
    if (tabsState.activeTabId) {
        const activeIndex = tabsState.tabs.findIndex(tab => tab.id === tabsState.activeTabId);
        if (activeIndex !== -1) {
            tabsState.tabs.splice(activeIndex + 1, 0, newTab);
        } else {
            tabsState.tabs.push(newTab);
        }
    } else {
        tabsState.tabs.push(newTab);
    }
    tabsState.activeTabId = tabId;
    renderTabs();
    saveRecentFile(filePath, newTab.root);
    loadHtmlEditorToTab(newTab);
    saveTabsState();
}
function loadHtmlEditorToTab(tab) {
    const viewer = document.getElementById('viewer');
    const fileName = document.getElementById('file-name');
    const fileContainers = viewer.querySelectorAll('.file-content-container');
    fileContainers.forEach(container => {
        container.style.display = 'none';
    });
    viewer.style.cssText = `
        position: relative;
        width: 100%;
        height: 100%;
        overflow: hidden;
    `;
    if (!isHtmlFile(tab.path)) {
        flashStatus(t(i18n, 'unsupportedHtmlFormat') || 'Unsupported HTML format');
        return;
    }
    document.getElementById('file-view').classList.remove('hidden');
    fileName.textContent = tab.title;
    viewer.dataset.path = tab.path;
    viewer.dataset.editMode = 'true';
    viewer.dataset.htmlEditor = 'true';
    updateToolbarForHtmlEditor();
    let iframe = viewer.querySelector('iframe[data-tab-id="' + tab.id + '"]');
    if (!iframe) {
        iframe = document.createElement('iframe');
        iframe.setAttribute('data-tab-id', tab.id);
        const urlParams = new URLSearchParams(window.location.search);
        const lang = urlParams.get('lang') || 'en';
        const theme = getEffectiveCloudTheme(urlParams);
        const color = urlParams.get('color') || '#85b503';
        let currentUser = currentUserState.user || 'default';
        currentUser = currentUser.replace(/\s+/g, '_');
        const htmlEditorUrl = buildCloudProjectUrl('utilities/projects/html-editor/public/index.php', {
            path: tab.path,
            user: currentUser,
            lang,
            theme,
            color,
            root: getResolvedTabRoot(tab)
        });
        iframe.src = htmlEditorUrl;
        applyFullScreenStyles(iframe);
        viewer.appendChild(iframe);
    } else {
        applyFullScreenStyles(iframe);
        iframe.style.display = 'block';
    }
}
function updateToolbarForHtmlEditor() {
    updateToolbarForMarkdownEditor();
}
window.openHtmlEditor = openHtmlEditor;
function switchToTab(tabId) {
    const currentActiveTab = tabsState.tabs.find(t => t.id === tabsState.activeTabId);
    if (currentActiveTab) {
        stashTabView(currentActiveTab);
        cacheTabContent(currentActiveTab);
    }

    const tab = tabsState.tabs.find(t => t.id === tabId);
    if (tab) {
        tabsState.activeTabId = tabId;
        const viewer = document.getElementById('viewer');

        // Update editor references - only Monaco now
        if (editorsMap.has(tabId)) {
            const editor = editorsMap.get(tabId);
            if (editor) {
                viewer.monacoEditor = editor;
                viewer.dataset.editor = 'monaco';
            }
        } else {
            viewer.monacoEditor = null;
            viewer.dataset.editor = '';
        }
        
        restoreTabContent(tab);
        
        updateActiveTabVisualState();
        saveTabsState();
        window.dispatchEvent(new CustomEvent('cloudActiveTabChanged'));
        
        setTimeout(() => {
            const iframe = tabsState.iframeCache.get(tabId) || viewer.querySelector('iframe[data-tab-id="' + tabId + '"]');
            if (iframe) {
                applyFullScreenStyles(iframe);
            }
        }, 100);
    }
}
function updateActiveTabVisualState() {
    const tabsList = document.getElementById('tabs-list');
    if (!tabsList) return;

    tabsList.querySelectorAll('.tab').forEach((tabEl) => {
        const isActive = String(tabEl.getAttribute('data-tab-id')) === String(tabsState.activeTabId);
        tabEl.classList.toggle('active', isActive);
    });

    const activeTab = tabsList.querySelector('.tab.active');
    if (activeTab) {
        requestAnimationFrame(() => {
            activeTab.scrollIntoView({
                behavior: 'smooth',
                block: 'nearest',
                inline: 'center'
            });
            if (typeof window.updateScrollButtons === 'function') {
                window.updateScrollButtons();
            }
        });
    }
}
function saveTabContent(tab) {
    const viewer = document.getElementById('viewer');
    if (tab.editMode && !tab.photoEditor && !tab.latexEditor) {
        let content = '';
        
        // Try Monaco first
        if (window.monacoManager) {
            const monacoContent = window.monacoManager.getContent(tab.id);
            if (monacoContent !== '') {
                content = monacoContent;
            }
        }
        
        // Try editorsMap
        if (!content && editorsMap.has(tab.id)) {
            const editor = editorsMap.get(tab.id);
            content = editor.getValue();
        }
        
        // Fallback to textarea
        if (!content) {
            const textarea = viewer.querySelector('textarea');
            if (textarea) {
                content = textarea.value;
            }
        }
        
        tab.content = content;
    }
}
async function closeTab(tabId) {
    hideTabPreview();
    
    const tabsBeforeClose = tabsState.tabs.length;
    const tabIndex = tabsState.tabs.findIndex(t => t.id === tabId);
    if (tabIndex === -1) return;
    
    const tab = tabsState.tabs[tabIndex];
    if (tab.modified) {
        const result = await showConfirmModal(
            t(i18n, 'unsavedChanges'),
            t(i18n, 'closeModifiedTab')
        );
        if (!result.confirmed) {
            return;
        }
    }
    teardownTabResources(tabId);
    tabsState.tabs.splice(tabIndex, 1);
    tabsState.contentCache.delete(tabId);
    const viewer = document.getElementById('viewer');
    
    if (tabsState.activeTabId === tabId) {
        if (tabsState.tabs.length > 0) {
            let newActiveIndex;
            
            if (tabIndex < tabsState.tabs.length) {
                newActiveIndex = tabIndex;
            } else {
                newActiveIndex = tabsState.tabs.length - 1;
            }
            
            newActiveIndex = Math.max(0, Math.min(newActiveIndex, tabsState.tabs.length - 1));
            
            const newActiveTab = tabsState.tabs[newActiveIndex];
            tabsState.activeTabId = newActiveTab.id;
            
            restoreTabContent(newActiveTab);
            
            const cachedIframe = tabsState.iframeCache.get(newActiveTab.id);
            if (cachedIframe && viewer) {
                if (cachedIframe.parentNode !== viewer) {
                    viewer.appendChild(cachedIframe);
                }
                cachedIframe.style.display = 'block';
            }
        } else {
            tabsState.activeTabId = null;
            closeViewer();
        }
    }
    
    renderTabs();
    saveTabsState();
    
    if (tabsBeforeClose === 1 && tabsState.tabs.length === 0) {
        exitFullscreenMode();
    }
}
async function closeAllTabs() {
    if (tabsState.tabs.length === 0) return;
    
    const hasModifiedTabs = tabsState.tabs.some(tab => tab.modified);
    if (hasModifiedTabs) {
        const result = await showConfirmModal(
            t(i18n, 'unsavedChanges'),
            t(i18n, 'closeAllModifiedTabs')
        );
        if (!result.confirmed) {
            return;
        }
    }
    
    monacoCollabSessions.forEach((_, tabId) => stopMonacoCollabSession(tabId));
    // Clear all editors
    editorsMap.clear();
    
    // Clear the iframe cache
    tabsState.iframeCache.forEach((iframe, tabId) => {
        if (iframe) iframe.remove();
    });
    tabsState.iframeCache.clear();
    
    // Remove all iframes and containers
    const viewer = document.getElementById('viewer');
    const allIframes = viewer.querySelectorAll('iframe');
    allIframes.forEach(iframe => iframe.remove());
    
    const allContainers = viewer.querySelectorAll('.file-content-container');
    allContainers.forEach(container => container.remove());
    
    // Clear state
    tabsState.tabs = [];
    tabsState.activeTabId = null;
    tabsState.contentCache.clear();
    
    closeViewer();
    
    renderTabs();
    
    // Save state
    saveTabsState();
    
    document.body.classList.toggle('tabs-open', false);
    flashStatus(t(i18n, 'allTabsClosed'));
    exitFullscreenMode();
}
function exitFullscreenMode() {
    const fileView = document.getElementById('file-view');
    if (fileView && fileView.classList.contains('fullscreen-mode')) {
        fileView.classList.remove('fullscreen-mode');
        handleFullscreenWithModals(false);
        const btnFullscreen = document.getElementById('btn-fullscreen');
        if (btnFullscreen) {
            btnFullscreen.title = 'Fullscreen';
            const icon = btnFullscreen.querySelector('svg');
            if (icon) {
                icon.innerHTML = '<path d="M8 3H5a2 2 0 0 0-2 2v3m18 0V5a2 2 0 0 0-2-2h-3m0 18h3a2 2 0 0 0 2-2v-3M3 16v3a2 2 0 0 0 2 2h3"/>';
            }
        }
    }
}
function restoreTabContent(tab) {
    const viewer = document.getElementById('viewer');
    const fileName = document.getElementById('file-name');
    document.getElementById('file-view').classList.remove('hidden');
    fileName.textContent = tab.title;
    viewer.dataset.path = tab.path;
    viewer.dataset.editMode = tab.editMode.toString();
    viewer.dataset.tabRoot = getResolvedTabRoot(tab);
    viewer.dataset.photoEditor = tab.photoEditor ? 'true' : 'false';
    viewer.dataset.latexEditor = tab.latexEditor ? 'true' : 'false';
    viewer.dataset.transcriptionEditor = tab.transcriptionEditor ? 'true' : 'false';
    viewer.dataset.ttsEditor = tab.ttsEditor ? 'true' : 'false';
    viewer.dataset.onlyOfficeEditor = tab.onlyOfficeEditor ? 'true' : 'false';
    viewer.dataset.zipViewer = tab.zipViewer ? 'true' : 'false';
    
    updateToolbarForTab(tab);

    if (revealTabView(tab)) {
        requestAnimationFrame(() => {
            const activeIframe = viewer.querySelector('iframe[data-tab-id="' + tab.id + '"]');
            if (activeIframe) applyFullScreenStyles(activeIframe);
        });
        return;
    }
    
    const isEditorTab = tab.photoEditor || tab.latexEditor || tab.transcriptionEditor ||
                       tab.onlyOfficeEditor || tab.markdownEditor || tab.htmlEditor || tab.zipViewer || tab.ttsEditor;
    
    if (isEditorTab) {
        // First check the iframe cache
        let iframe = tabsState.iframeCache.get(tab.id);
        
        if (iframe && iframe.parentNode !== viewer) {
            // If cached but not in the DOM - add it
            viewer.appendChild(iframe);
            applyFullScreenStyles(iframe);
            iframe.style.display = 'block';
        } else if (iframe && iframe.parentNode === viewer) {
            // If already in the DOM - just show it
            applyFullScreenStyles(iframe);
            iframe.style.display = 'block';
        } else {
            // If not cached - create a new one
            if (tab.photoEditor) {
                loadPhotoEditorToTab(tab);
            } else if (tab.latexEditor) {
                loadLatexEditorToTab(tab);
            } else if (tab.transcriptionEditor) {
                loadTranscriptionEditorToTab(tab);
            } else if (tab.ttsEditor) {
                loadTTSEditorToTab(tab);
            } else if (tab.onlyOfficeEditor) {
                loadOnlyOfficeEditorToTab(tab);
            } else if (tab.markdownEditor) {
                loadMarkdownEditorToTab(tab);
            } else if (tab.htmlEditor) {
                loadHtmlEditorToTab(tab);
            } else if (tab.zipViewer) {
                loadZipViewerToTab(tab);
            }
        }
    } else {
        // Regular files
        let contentElement = null;
        const editorMarkers = viewer.querySelectorAll('[data-tab-id="' + tab.id + '"]');
        if (editorMarkers.length > 0) {
            contentElement = editorMarkers[0];
        }
        
        const cachedContent = tabsState.contentCache.get(tab.id);
        
        if (contentElement) {
            contentElement.style.display = 'block';
            if (contentElement.classList.contains('CodeMirror')) {
                setTimeout(() => {
                    if (contentElement.CodeMirror) {
                        contentElement.CodeMirror.refresh();
                        contentElement.CodeMirror.focus();
                    }
                }, 10);
            }
        } else if (cachedContent) {
            restoreCachedContent(tab, cachedContent);
        } else {
            const memoryCache = buildMemoryCacheForTab(tab);
            if (memoryCache) {
                restoreCachedContent(tab, memoryCache);
            } else if (!tabHasLiveView(tab)) {
                openFile(tab.path, tab.editMode, null, getResolvedTabRoot(tab));
            }
        }
    }
    
    if (!isEditorTab && isEditableImage(tab.path) && !tab.photoEditor && !tabHasLiveView(tab)) {
        const cachedContent = tabsState.contentCache.get(tab.id);
        if (cachedContent && cachedContent.type === 'image' && cachedContent.html) {
            restoreCachedContent(tab, cachedContent);
        } else if (!tabHasLiveView(tab)) {
            openFile(tab.path, false, null, getResolvedTabRoot(tab));
        }
    }
    
    requestAnimationFrame(() => {
        if (isEditorTab) {
            const activeIframe = viewer.querySelector('iframe[data-tab-id="' + tab.id + '"]');
            if (activeIframe) {
                applyFullScreenStyles(activeIframe);
            }
        }
    });
}
function applyFullScreenStyles(element) {
    element.style.cssText = `
        position: absolute !important;
        top: 0 !important;
        left: 0 !important;
        width: 100% !important;
        height: 100% !important;
        border: none !important;
        background: var(--bg) !important;
        display: block !important;
        z-index: 10 !important;
    `;
}
function focusActiveEditorFrame() {
    const activeTabId = window.tabsState?.activeTabId;
    const viewer = document.getElementById('viewer');
    if (!activeTabId || !viewer) return;
    const iframe = viewer.querySelector('iframe[data-tab-id="' + activeTabId + '"]');
    if (!iframe || iframe.style.display === 'none') return;
    if (!iframe.hasAttribute('tabindex')) iframe.setAttribute('tabindex', '0');
    requestAnimationFrame(() => {
        try { iframe.focus({ preventScroll: true }); }
        catch (_) { try { iframe.focus(); } catch (__) {} }
        try { iframe.contentWindow?.focus(); } catch (_) {}
    });
}
function buildMemoryCacheForTab(tab) {
    if (!tab || typeof tab.content !== 'string') return null;
    if (!tab.editMode && !isTextFile(tab.path) && !isHtmlFile(tab.path)) return null;

    return {
        type: 'monaco',
        content: tab.content,
        cursor: null
    };
}
function cacheTabContent(tab) {
    const viewer = document.getElementById('viewer');
    if (tab.id !== tabsState.activeTabId) {
        stashTabView(tab);
        return;
    }
    
    const cacheData = {
        timestamp: Date.now(),
        type: tab.type || 'file',
        content: null,
        html: null,
        editorState: null,
        src: null, 
        path: tab.path, 
        photoEditor: tab.photoEditor,
        latexEditor: tab.latexEditor,
        transcriptionEditor: tab.transcriptionEditor,
        ttsEditor: tab.ttsEditor,
        onlyOfficeEditor: tab.onlyOfficeEditor,
        zipViewer: tab.zipViewer,
        fullPath: tab.fullPath
    };
    
    // Check the Monaco editor
    if (window.monacoManager) {
        const monacoContent = window.monacoManager.getContent(tab.id);
        if (monacoContent !== '') {
            cacheData.type = 'monaco';
            cacheData.content = monacoContent;
            const editor = window.monacoManager?.getEditor?.(tab.id);
            if (editor) {
                const position = editor.getPosition();
                cacheData.cursor = position ? { lineNumber: position.lineNumber, column: position.column } : null;
            }
        }
    }
    
    if (tab.photoEditor || tab.latexEditor || tab.transcriptionEditor || tab.ttsEditor || tab.onlyOfficeEditor || tab.zipViewer) {
        const iframe = viewer.querySelector('iframe[data-tab-id="' + tab.id + '"]');
        if (iframe) {
            cacheData.type = 'iframe';
            cacheData.src = iframe.src; // SAVE THE FULL iframe URL
            cacheData.editorType = tab.photoEditor ? 'photo' :
                                  tab.latexEditor ? 'latex' :
                                  tab.transcriptionEditor ? 'transcription' :
                                  tab.ttsEditor ? 'tts' :
                                  tab.onlyOfficeEditor ? 'office' :
                                  tab.zipViewer ? 'zip' : 'unknown';
            
            // Keep the live iframe to restore it later without reloading.
            tabsState.iframeCache.set(tab.id, iframe);
        }
    } 
    // ===== ADD THIS BLOCK FOR IMAGES =====
    else if (viewer.querySelector('img')) {
        const imgContainer = viewer.querySelector('.image-viewer-container');
        if (imgContainer) {
            cacheData.type = 'image';
            cacheData.html = imgContainer.innerHTML;
            cacheData.scrollLeft = imgContainer.scrollLeft;
            cacheData.scrollTop = imgContainer.scrollTop;
            
            // For the photo viewer, keep the iframe separately
            const iframe = imgContainer.querySelector('iframe');
            if (iframe) {
                cacheData.iframeSrc = iframe.src;
                tabsState.iframeCache.set(tab.id, iframe);
            }
        }
    } 
    // ===== END OF BLOCK =====
    else if (viewer.querySelector('audio, video')) {
        cacheData.type = 'media';
        cacheData.html = viewer.innerHTML;
    }
    else {
        cacheData.type = 'html';
        cacheData.html = viewer.innerHTML;
    }
    
    tabsState.contentCache.set(tab.id, cacheData);
}
function manageIframeVisibility(tabId, show = true) {
    const viewer = document.getElementById('viewer');
    viewer.style.cssText = `
        position: relative;
        width: 100%;
        height: 100%;
        overflow: hidden;
    `;
    const allIframes = viewer.querySelectorAll('iframe');
    allIframes.forEach(iframe => {
        const iframeTabId = iframe.getAttribute('data-tab-id');
        if (iframeTabId) {
            if (parseInt(iframeTabId) === tabId) {
                applyFullScreenStyles(iframe);
                iframe.style.display = 'block';
                iframe.style.zIndex = '2';
            } else {
                iframe.style.display = 'none';
                iframe.style.zIndex = '1';
            }
        }
    });
}
function restoreCachedContent(tab, cacheData) {
    const viewer = document.getElementById('viewer');
    hideAllTabPanels(tab.id);
    switch(cacheData.type) {
        case 'iframe':
            // Restore editor flags from the cache
            if (cacheData.photoEditor) tab.photoEditor = true;
            if (cacheData.latexEditor) tab.latexEditor = true;
            if (cacheData.transcriptionEditor) tab.transcriptionEditor = true;
            if (cacheData.ttsEditor) tab.ttsEditor = true;
            if (cacheData.onlyOfficeEditor) tab.onlyOfficeEditor = true;
            if (cacheData.zipViewer) tab.zipViewer = true;

            if (cacheData.path !== tab.path) {
                createEditorIframe(tab);
            } else {
                createEditorIframe(tab);
            }
            break;
        case 'monaco':
        case 'codemirror':
            if (!tab.editMode && isHtmlFile(tab.path)) {
                renderHtmlFilePreview(viewer, tab.path, tab.id);
                break;
            }
            const editorContainer = document.createElement('div');
            editorContainer.style.cssText = `
                width: 100%;
                height: 100%;
                border: none;
                outline: none;
                box-shadow: none;
            `;
            editorContainer.setAttribute('data-tab-id', tab.id);
            viewer.appendChild(editorContainer);
            const content = cacheData.content || '';
            const readOnly = !tab.editMode;
            
            // Use Monaco editor (read-only if not in edit mode)
            if (window.monacoManager) {
                window.monacoManager.createEditor(editorContainer, content, tab.path, tab.id, readOnly)
                    .then(editor => {
                        editorsMap.set(tab.id, editor);
                        viewer.dataset.editor = 'monaco';
                        if (tab.editMode) {
                            initMonacoAutoSave(editor, tab.path, tab.id);
                            if (isMonacoCollabEligiblePath(tab.path)) {
                                startMonacoCollabSession(tab.id, tab.path, getResolvedTabRoot(tab), content, '');
                            }
                        }
                        
                        // Restore cursor position if available
                        if (cacheData.cursor && editor) {
                            editor.setPosition({
                                lineNumber: cacheData.cursor.lineNumber || 1,
                                column: cacheData.cursor.column || 1
                            });
                        }
                        
                        editor.focus();
                    })
                    .catch(err => {
                        console.error('Failed to restore Monaco editor:', err);
                        flashStatus(t(i18n, 'editorRestoreError'));
                    });
            } else {
                console.error('Monaco manager not available');
                flashStatus(t(i18n, 'editorLoadError'));
            }
            break;
        case 'image': {
            const fileContainer = getOrCreateFileContainer(tab.id);
            fileContainer.innerHTML = cacheData.html || '';
            fileContainer.style.display = 'block';
            const imgContainer = fileContainer.querySelector('.image-viewer-container');
            if (imgContainer && cacheData.scrollLeft !== undefined) {
                setTimeout(() => {
                    imgContainer.scrollLeft = cacheData.scrollLeft;
                    imgContainer.scrollTop = cacheData.scrollTop;
                }, 150);
            }
            const iframe = fileContainer.querySelector('iframe');
            if (iframe) tabsState.iframeCache.set(tab.id, iframe);
            break;
        }
        case 'media':
        case 'html': {
            const fileContainer = getOrCreateFileContainer(tab.id);
            fileContainer.innerHTML = cacheData.html || '';
            fileContainer.style.display = 'block';
            break;
        }
        default:
            openFile(tab.path, tab.editMode, null);
    }
}
function createEditorIframe(tab) {
    const viewer = document.getElementById('viewer');
    const oldIframe = viewer.querySelector('iframe[data-tab-id="' + tab.id + '"]') || tabsState.iframeCache.get(tab.id);
    if (oldIframe) {
        applyFullScreenStyles(oldIframe);
        oldIframe.style.display = 'block';
        hideAllTabPanels(tab.id);
        return;
    }
    if (tab.photoEditor) {
        loadPhotoEditorToTab(tab);
    } else if (tab.latexEditor) {
        loadLatexEditorToTab(tab);
    } else if (tab.transcriptionEditor) {
        loadTranscriptionEditorToTab(tab);
    } else if (tab.ttsEditor) {
        loadTTSEditorToTab(tab);
    } else if (tab.onlyOfficeEditor) {
        loadOnlyOfficeEditorToTab(tab);
    } else if (tab.markdownEditor) {
        loadMarkdownEditorToTab(tab);
    } else if (tab.htmlEditor) {
        loadHtmlEditorToTab(tab);
    }
}
function updateToolbarForTab(tab) {
    const saveBtn = document.getElementById('btn-save');
    const codeActionsBtn = document.getElementById('btn-code-actions');
    const copyBtn = document.getElementById('btn-copy');
    const undoBtn = document.getElementById('btn-monaco-undo');
    const redoBtn = document.getElementById('btn-monaco-redo');
    if (tab.photoEditor || tab.latexEditor || tab.transcriptionEditor || 
        tab.onlyOfficeEditor || tab.markdownEditor || tab.htmlEditor || tab.zipViewer) {
        saveBtn.classList.add('hidden');
        codeActionsBtn.classList.add('hidden');
        if (copyBtn) copyBtn.classList.add('hidden');
        undoBtn?.classList.add('hidden');
        redoBtn?.classList.add('hidden');
    } else {
        saveBtn.classList.toggle('hidden', !tab.editMode);
        codeActionsBtn.classList.toggle('hidden', !tab.editMode);
        if (copyBtn) {
            const isBinary = isBinaryFile(tab.path);
            copyBtn.classList.toggle('hidden', isBinary);
        }
        const showMonacoHistory = !!tab.editMode && !isBinaryFile(tab.path);
        undoBtn?.classList.toggle('hidden', !showMonacoHistory);
        redoBtn?.classList.toggle('hidden', !showMonacoHistory);
        updateMonacoUndoRedoToolbar();
    }
    
    // Show/hide compile button based on file type
    const compileMenuItem = document.getElementById('code-action-compile');
    if (compileMenuItem) {
        const fileExt = tab.path.split('.').pop().toLowerCase();
        const supportedLangs = ['java', 'c', 'cpp', 'py', 'm'];
        compileMenuItem.style.display = (tab.editMode && supportedLangs.includes(fileExt)) ? 'flex' : 'none';
    }
}

function updateMonacoUndoRedoToolbar() {
    const undoBtn = document.getElementById('btn-monaco-undo');
    const redoBtn = document.getElementById('btn-monaco-redo');
    if (!undoBtn || !redoBtn) return;
    const active = window.monacoManager?.getActiveEditor?.();
    if (!active) {
        undoBtn.disabled = true;
        redoBtn.disabled = true;
        return;
    }
    const state = window.monacoManager?.getUndoRedoState?.(active.editor) || { canUndo: false, canRedo: false };
    undoBtn.disabled = !state.canUndo;
    redoBtn.disabled = !state.canRedo;
}
function markTabAsModified(tabId, modified = true) {
    const tab = tabsState.tabs.find(t => t.id === tabId);
    if (tab) {
        const stateChanged = !!tab.modified !== !!modified;
        tab.modified = modified;
        if (modified) {
            tab.content = getCurrentEditorContent(tabId);
        }
        // Avoid a full re-render + localStorage write on every content change
        // (including realtime collab updates): update the dot in place instead.
        if (!stateChanged) {
            const tabsList = document.getElementById('tabs-list');
            const tabEl = tabsList?.querySelector('.tab[data-tab-id="' + tabId + '"]');
            if (tabEl) tabEl.classList.toggle('modified', !!tab.modified);
            return;
        }
        renderTabs();
        saveTabsState();
    }
}
function initFullscreen() {
    const btnFullscreen = document.getElementById('btn-fullscreen');
    if (!btnFullscreen) return;
    
    btnFullscreen.addEventListener('click', () => {
        const fileView = document.getElementById('file-view');
        if (!fileView) return;
        
        const isFull = fileView.classList.toggle('fullscreen-mode');
        const icon = btnFullscreen.querySelector('svg');
        
        if (isFull) {
            btnFullscreen.title = 'Exit Fullscreen';
            if (icon) {
                icon.innerHTML = '<path d="M8 3v3a2 2 0 0 1-2 2H3m18 0h-3a2 2 0 0 1-2-2V3m0 18v-3a2 2 0 0 1 2-2h3M3 16h3a2 2 0 0 1 2 2v3"/>';
            }
            // Add modal window handling
            handleFullscreenWithModals(true);
            focusActiveEditorFrame();
        } else {
            btnFullscreen.title = 'Fullscreen';
            if (icon) {
                icon.innerHTML = '<path d="M8 3H5a2 2 0 0 0-2 2v3m18 0V5a2 2 0 0 0-2-2h-3m0 18h3a2 2 0 0 0 2-2v-3M3 16v3a2 2 0 0 0 2 2h3"/>';
            }
            // Restore normal behavior
            handleFullscreenWithModals(false);
        }
    });
}
function handleFullscreenWithModals(isFullscreen) {
  const modals = document.querySelectorAll('.modal:not(.hidden)');
  const searchPanel = document.querySelector('.search-container');
  const selectionPanel = document.getElementById('selection-action-panel');
  
  if (isFullscreen) {
    // In fullscreen mode
    document.body.style.overflow = 'hidden';
    
    // If there are open modals, make sure they stay on top
    modals.forEach(modal => {
      modal.style.zIndex = '30000';
    });
    
    if (searchPanel) {
      searchPanel.classList.add('hidden');
    }
    
    if (selectionPanel && !selectionPanel.classList.contains('hidden')) {
      selectionPanel.style.zIndex = '32000';
    }
  } else {
    // Exit fullscreen mode
    document.body.style.overflow = '';
    
    // Restore normal z-index values
    modals.forEach(modal => {
      modal.style.zIndex = '';
    });
    
    if (searchPanel) {
      searchPanel.classList.remove('hidden');
    }
    
    if (selectionPanel) {
      selectionPanel.style.zIndex = '';
    }
  }
}
function handleFullscreenChange() {
    const btnFullscreen = document.getElementById('btn-fullscreen');
    const icon = btnFullscreen.querySelector('svg');
    const fileView = document.getElementById('file-view');
    if (document.fullscreenElement) {
        icon.innerHTML = `
            <path d="M8 3v3a2 2 0 0 1-2 2H3m18 0h-3a2 2 0 0 1-2-2V3m0 18v-3a2 2 0 0 1 2-2h3M3 16h3a2 2 0 0 1 2 2v3"></path>
        `;
        btnFullscreen.title = 'Exit Fullscreen';
        fileView.classList.add('fullscreen-mode');
        handleFullscreenWithModals(true);
        focusActiveEditorFrame();
    } else {
        icon.innerHTML = `
            <path d="M8 3H5a2 2 0 0 0-2 2v3m18 0V5a2 2 0 0 0-2-2h-3m0 18h3a2 2 0 0 0 2-2v-3M3 16v3a2 2 0 0 0 2 2h3"></path>
        `;
        btnFullscreen.title = 'Fullscreen';
        fileView.classList.remove('fullscreen-mode');
        handleFullscreenWithModals(false);
    }
}
let tabScrollingInitialized = false;

function initTabScrolling() {
    const tabsList = document.getElementById('tabs-list');
    const scrollLeftBtn = document.getElementById('tab-scroll-left');
    const scrollRightBtn = document.getElementById('tab-scroll-right');
    
    if (!tabsList || !scrollLeftBtn || !scrollRightBtn) return;
    
    const SCROLL_AMOUNT = 200;
    
    window.updateScrollButtons = function() {
        // Get fresh element references each time
        const tabsList = document.getElementById('tabs-list');
        const scrollLeftBtn = document.getElementById('tab-scroll-left');
        const scrollRightBtn = document.getElementById('tab-scroll-right');
        
        if (!tabsList || !scrollLeftBtn || !scrollRightBtn) return;
        
        if (tabsState.tabs.length === 0) {
            scrollLeftBtn.style.display = 'none';
            scrollRightBtn.style.display = 'none';
            return;
        }
        
        const needsScrolling = tabsList.scrollWidth > tabsList.clientWidth;
        
        if (!needsScrolling) {
            scrollLeftBtn.style.display = 'none';
            scrollRightBtn.style.display = 'none';
            return;
        }
        
        scrollLeftBtn.style.display = 'flex';
        scrollRightBtn.style.display = 'flex';
        
        const isAtStart = tabsList.scrollLeft <= 5;
        const isAtEnd = tabsList.scrollLeft + tabsList.clientWidth >= tabsList.scrollWidth - 5;
        
        scrollLeftBtn.classList.toggle('hidden', isAtStart);
        scrollRightBtn.classList.toggle('hidden', isAtEnd);
    };
    
    // Remove old handlers before adding new ones
    scrollLeftBtn.replaceWith(scrollLeftBtn.cloneNode(true));
    scrollRightBtn.replaceWith(scrollRightBtn.cloneNode(true));
    
    // Get new references after cloning
    const newScrollLeft = document.getElementById('tab-scroll-left');
    const newScrollRight = document.getElementById('tab-scroll-right');
    const newTabsList = document.getElementById('tabs-list');
    
    newScrollLeft.addEventListener('click', () => {
        newTabsList.scrollBy({ left: -SCROLL_AMOUNT, behavior: 'smooth' });
    });
    
    newScrollRight.addEventListener('click', () => {
        newTabsList.scrollBy({ left: SCROLL_AMOUNT, behavior: 'smooth' });
    });
    
    newTabsList.addEventListener('scroll', window.updateScrollButtons);
    window.addEventListener('resize', window.updateScrollButtons);
    
    // Observe changes in the tab list
    const observer = new MutationObserver(window.updateScrollButtons);
    observer.observe(newTabsList, { childList: true, subtree: true, attributes: true });
    
    // Initial update with a longer delay to ensure the DOM loads
    setTimeout(window.updateScrollButtons, 150);
    
    // Wheel scrolling
    newTabsList.addEventListener('wheel', (e) => {
        if (Math.abs(e.deltaY) > Math.abs(e.deltaX)) {
            e.preventDefault();
            newTabsList.scrollLeft += e.deltaY;
            window.updateScrollButtons();
        }
    }, { passive: false });
}
function renderTabs() {
    const hasTabs = tabsState.tabs.length > 0;
    document.body.classList.toggle('tabs-open', hasTabs);
    document.body.classList.toggle('tabs-empty', !hasTabs);
    const viewer = getViewerElement();
    if (hasTabs && viewer) {
        viewer.classList.remove('viewer-empty-state');
        delete viewer.dataset.empty;
    }

    let tabsContainer = document.querySelector('.tabs-container');
    let tabsList = document.getElementById('tabs-list');
    let closeAllBtn = document.getElementById('btn-close-all-tabs');
    
    // Check if a button container already exists
    if (!tabsContainer || !document.getElementById('tab-scroll-left')) {
        // If the container does not exist, create it
        if (!tabsContainer) {
            tabsContainer = document.createElement('div');
            tabsContainer.className = 'tabs-container';
            const fileView = document.querySelector('.file-view');
            if (fileView) {
                fileView.insertBefore(tabsContainer, fileView.firstChild);
            }
        }
        
            tabsContainer.innerHTML = `
            	    <button class="tab-scroll-btn" id="tab-scroll-left" title="Scroll left">
                <svg viewBox="0 0 24 24">
                    <polyline points="15 18 9 12 15 6"></polyline>
                </svg>
            </button>
            <div class="tabs-list" id="tabs-list"></div>
            <button class="tab-scroll-btn" id="tab-scroll-right" title="Scroll right">
                <svg viewBox="0 0 24 24">
                    <polyline points="9 18 15 12 9 6"></polyline>
                </svg>
            </button>
	            <button class="icon-btn tab-close-all" id="btn-close-all-tabs" title="" data-i18n-title="closeAllTabs">
	                <svg width="16" height="16" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none">
	                    <line x1="18" y1="6" x2="6" y2="18"></line>
	                    <line x1="6" y1="6" x2="18" y2="18"></line>
	                </svg>
	            </button>
            `;
        
        // Re-initialize references
        tabsList = document.getElementById('tabs-list');
        closeAllBtn = document.getElementById('btn-close-all-tabs');
        
	        // Add a handler for the close-all-tabs button
	        closeAllBtn.addEventListener('click', closeAllTabs);
	        // Ensure translated title for dynamically-created button
	        if (window.i18n) closeAllBtn.title = t(window.i18n, 'closeAllTabs');
        
        // Initialize scrolling
        if (typeof initTabScrolling === 'function' && !window.tabScrollingInitialized) {
            initTabScrolling();
            window.tabScrollingInitialized = true;
        }
    }
    
    // Handle empty state
    if (tabsState.tabs.length === 0) {
        tabsList.innerHTML = '';
        if (closeAllBtn) closeAllBtn.style.display = 'none';
        
        const scrollLeft = document.getElementById('tab-scroll-left');
        const scrollRight = document.getElementById('tab-scroll-right');
        if (scrollLeft) scrollLeft.style.display = 'none';
        if (scrollRight) scrollRight.style.display = 'none';
        renderViewerEmptyState();
        return;
    }
    
    // Show the close-all-tabs button
    if (closeAllBtn) closeAllBtn.style.display = 'flex';

    const existingTabs = new Map(
        Array.from(tabsList.querySelectorAll('.tab')).map((tabEl) => [String(tabEl.getAttribute('data-tab-id')), tabEl])
    );

    tabsState.tabs.forEach((tab) => {
        const key = String(tab.id);
        let tabElement = existingTabs.get(key);

        if (!tabElement) {
            tabElement = document.createElement('div');
            tabElement.className = 'tab';
            tabElement.setAttribute('data-tab-id', tab.id);
            tabElement.innerHTML = `
                <div class="tab-content" title="">
                    <span class="tab-icon-slot"></span>
                    <span class="tab-title-text"></span>
                </div>
                <button class="tab-close" data-tab-id="${tab.id}" title="Close tab" aria-label="Close tab"></button>
            `;

            tabElement.addEventListener('mouseenter', (e) => {
                clearTimeout(tabPreviewHoverTimer);
                tabPreviewHoverTimer = setTimeout(() => {
                    const currentTab = tabsState.tabs.find((entry) => String(entry.id) === String(tabElement.getAttribute('data-tab-id')));
                    if (currentTab) showTabPreview(currentTab, e);
                }, 220);
            });

            tabElement.addEventListener('mouseleave', () => {
                clearTimeout(tabPreviewHoverTimer);
                hideTabPreview();
            });

            tabElement.addEventListener('click', (e) => {
                if (e.target.closest('.tab-close')) return;
                if (shouldSuppressMobileActivation()) {
                    e.preventDefault();
                    e.stopPropagation();
                    return;
                }

                const currentTabId = Number(tabElement.getAttribute('data-tab-id'));
                const isActiveTab = currentTabId === tabsState.activeTabId;
                if (isActiveTab) {
                    const rect = tabElement.getBoundingClientRect();
                    const clickX = typeof e.clientX === 'number' && e.clientX > 0
                        ? e.clientX
                        : rect.left + rect.width / 2;
                    const clickY = typeof e.clientY === 'number' && e.clientY > 0
                        ? e.clientY
                        : rect.bottom;

                    showTabContextMenu({
                        preventDefault: () => e.preventDefault(),
                        stopPropagation: () => e.stopPropagation(),
                        clientX: clickX,
                        clientY: clickY
                    }, currentTabId);
                    return;
                }

                switchToTab(currentTabId);
            });

            attachMobileContextMenuTrigger(tabElement, (menuEvent) => {
                const currentTabId = Number(tabElement.getAttribute('data-tab-id'));
                showTabContextMenu(menuEvent, currentTabId);
            });

            tabElement.addEventListener('contextmenu', (e) => {
                if (shouldSuppressMobileActivation()) {
                    e.preventDefault();
                    e.stopPropagation();
                    return;
                }
                const currentTabId = Number(tabElement.getAttribute('data-tab-id'));
                showTabContextMenu(e, currentTabId);
            });

            const closeBtn = tabElement.querySelector('.tab-close');
            closeBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                const currentTabId = Number(tabElement.getAttribute('data-tab-id'));
                closeTab(currentTabId);
            });

            tabElement.addEventListener('dragstart', (e) => {
                const currentTabId = Number(tabElement.getAttribute('data-tab-id'));
                const currentTab = tabsState.tabs.find((entry) => Number(entry.id) === currentTabId);
                const path = String(currentTab?.path || '').trim();
                if (!path || e.target.closest('.tab-close')) {
                    e.preventDefault();
                    return;
                }
                const root = typeof getResolvedTabRoot === 'function' ? getResolvedTabRoot(currentTab) : (currentTab?.root || getCurrentCloudRoot());
                e.dataTransfer.setData('application/x-cloud-file-path', path);
                e.dataTransfer.setData('application/x-cloud-file-paths', JSON.stringify([path]));
                e.dataTransfer.setData('application/x-cloud-file-type', 'file');
                e.dataTransfer.setData('application/x-cloud-source-root', root || 'main');
                e.dataTransfer.setData('text/plain', path);
                e.dataTransfer.effectAllowed = 'copy';
            });
        }

        tabElement.classList.toggle('active', tab.id === tabsState.activeTabId);
        tabElement.classList.toggle('modified', !!tab.modified);
        tabElement.setAttribute('data-tab-id', tab.id);
        tabElement.draggable = !!tab.path;

        const tabContent = tabElement.querySelector('.tab-content');
        if (tabContent) {
            tabContent.title = tab.path;
        }

        const titleEl = tabElement.querySelector('.tab-title-text');
        if (titleEl && titleEl.textContent !== tab.title) {
            titleEl.textContent = tab.title;
        }

        const iconSlot = tabElement.querySelector('.tab-icon-slot');
        const iconHtml = getFileIcon(tab.path);
        if (iconSlot && iconSlot.innerHTML !== iconHtml) {
            iconSlot.innerHTML = iconHtml;
        }

        const closeBtn = tabElement.querySelector('.tab-close');
        if (closeBtn) {
            closeBtn.setAttribute('data-tab-id', tab.id);
        }

        // Only append new tabs: re-appending an existing node would move it to the
        // end of the DOM and cause the tab bar to visibly jump/flicker on every
        // content update (e.g. realtime collab sync).
        if (tabElement.parentElement !== tabsList) {
            tabsList.appendChild(tabElement);
        } else {
            const lastAppended = tabsList.querySelector('.tab:last-of-type');
            if (lastAppended !== tabElement &&
                Array.from(tabsList.children).indexOf(tabElement) !== tabsState.tabs.indexOf(tab)) {
                tabsList.insertBefore(tabElement, tabsList.children[tabsState.tabs.indexOf(tab)] || null);
            }
        }
        existingTabs.delete(key);
    });

    existingTabs.forEach((tabEl) => tabEl.remove());
    
    setTimeout(() => {
        const activeTab = tabsList.querySelector('.tab.active');
        if (activeTab) {
            // Only scroll when the active tab is actually out of view, otherwise
            // repeated renders (e.g. realtime collab updates) make the bar jiggle.
            const listRect = tabsList.getBoundingClientRect();
            const tabRect = activeTab.getBoundingClientRect();
            const isVisible = tabRect.left >= listRect.left - 1 && tabRect.right <= listRect.right + 1;
            if (!isVisible) {
                activeTab.scrollIntoView({
                    behavior: 'smooth',
                    block: 'nearest',
                    inline: 'center'
                });
            }
        }
        
        // Update scroll button state
        if (typeof window.updateScrollButtons === 'function') {
            window.updateScrollButtons();
        }
    }, 150);
}
const TAB_PREVIEW_IMAGE_EXT = new Set(['jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp', 'tif', 'tiff']);
function getTabPreviewKindLabel(tab) {
    if (!tab) return 'File';
    if (tab.photoEditor) return 'Photo editor';
    if (tab.latexEditor) return 'LaTeX';
    if (tab.transcriptionEditor) return 'Transcription';
    if (tab.ttsEditor) return 'TTS';
    if (tab.onlyOfficeEditor) return 'Office';
    if (tab.markdownEditor) return 'Markdown';
    if (tab.htmlEditor) return 'HTML';
    if (tab.zipViewer) return 'Archive';
    const ext = getFileExtension(tab.path);
    if (ext === 'pdf') return 'PDF';
    if (TAB_PREVIEW_IMAGE_EXT.has(ext)) return 'Image';
    if (['mp4', 'webm', 'mov', 'avi', 'mkv'].includes(ext)) return 'Video';
    if (['mp3', 'wav', 'm4a', 'flac', 'aac'].includes(ext)) return 'Audio';
    if (['doc', 'docx'].includes(ext)) return 'Word';
    if (['xls', 'xlsx'].includes(ext)) return 'Excel';
    if (['ppt', 'pptx'].includes(ext)) return 'Presentation';
    return ext ? ext.toUpperCase() : 'File';
}
function getTabPreviewImageSrc(tab) {
    const viewer = getViewerElement();
    if (viewer) {
        const container = viewer.querySelector('.file-content-container[data-tab-id="' + tab.id + '"]');
        const img = container?.querySelector('img');
        const src = img && (img.currentSrc || img.src);
        if (src && !src.endsWith('#')) return src;
    }
    const apiBase = (window.APP_CONFIG && window.APP_CONFIG.apiBase) ? window.APP_CONFIG.apiBase : 'api.php';
    return apiBase + '?action=raw&path=' + encodeURIComponent(tab.path) + '&root=' + encodeURIComponent(getResolvedTabRoot(tab));
}
function createTabPreviewHeader(tab) {
    const header = document.createElement('div');
    header.className = 'tab-preview-header';
    header.innerHTML = `
        <div class="tab-preview-title">
            ${getFileIcon(tab.path)}
            <strong>${escapeHtml(tab.title || '')}</strong>
        </div>
        <div class="tab-preview-path">${escapeHtml(formatDisplayPath(tab.path))}</div>
    `;
    return header;
}
function createTabPreviewImageNode(tab) {
    const wrap = document.createElement('div');
    wrap.className = 'tab-preview-shot tab-preview-shot-image';
    const img = document.createElement('img');
    img.src = getTabPreviewImageSrc(tab);
    img.alt = '';
    img.decoding = 'async';
    img.loading = 'lazy';
    img.onerror = () => {
        wrap.classList.remove('tab-preview-shot-image');
        wrap.classList.add('tab-preview-kind');
        wrap.innerHTML = '<div class="binary-icon">' + getFileIcon(tab.path) + '</div><div>' + escapeHtml(getTabPreviewKindLabel(tab)) + '</div>';
    };
    wrap.appendChild(img);
    return wrap;
}
function createTabPreviewKindNode(tab) {
    const wrap = document.createElement('div');
    wrap.className = 'tab-preview-kind';
    wrap.innerHTML = '<div class="binary-icon">' + getFileIcon(tab.path) + '</div><div class="tab-preview-kind-label">' + escapeHtml(getTabPreviewKindLabel(tab)) + '</div>';
    return wrap;
}
function createTextPreviewNode(tab, content) {
    const wrap = document.createElement('div');
    wrap.className = 'tab-preview-content-inner file-preview';
    const inner = document.createElement('div');
    inner.className = 'file-content-preview';
    inner.innerHTML = formatFilePreview(content, tab.path);
    wrap.appendChild(inner);
    return wrap;
}
function getMonacoPreviewText(tab) {
    if (window.monacoManager) {
        const content = window.monacoManager.getContent(tab.id);
        if (content) return content;
    }
    if (typeof monacoEditors !== 'undefined' && monacoEditors.has(tab.id)) {
        try {
            return monacoEditors.get(tab.id).getValue();
        } catch (e) {}
    }
    return tab.content != null ? String(tab.content) : '';
}
function buildTabPreviewVisual(tab) {
    const ext = getFileExtension(tab.path);
    if (TAB_PREVIEW_IMAGE_EXT.has(ext) || tab.photoEditor) {
        return createTabPreviewImageNode(tab);
    }
    const textContent = getMonacoPreviewText(tab);
    if (textContent && textContent.trim()) {
        return createTextPreviewNode(tab, textContent);
    }
    return createTabPreviewKindNode(tab);
}
function positionTabPreview(preview, event) {
    const anchor = event.currentTarget || event.target;
    const rect = anchor.getBoundingClientRect();
    const margin = 8;
    preview.style.visibility = 'hidden';
    preview.style.left = '0px';
    preview.style.top = '0px';
    const box = preview.getBoundingClientRect();
    let left = rect.left;
    let top = rect.bottom + margin;
    if (left + box.width > window.innerWidth - margin) {
        left = Math.max(margin, window.innerWidth - box.width - margin);
    }
    if (top + box.height > window.innerHeight - margin) {
        top = Math.max(margin, rect.top - box.height - margin);
    }
    preview.style.left = left + 'px';
    preview.style.top = top + 'px';
    preview.style.visibility = '';
}
function showTabPreview(tab, event) {
    if (isMobileDevice() || !tab) return;
    hideTabPreview();
    const preview = document.createElement('div');
    preview.id = 'tab-preview';
    preview.className = 'tab-preview';
    preview.appendChild(createTabPreviewHeader(tab));
    const body = document.createElement('div');
    body.className = 'tab-preview-content tab-preview-visual';
    body.appendChild(buildTabPreviewVisual(tab));
    preview.appendChild(body);
    document.body.appendChild(preview);
    positionTabPreview(preview, event);
    setTimeout(() => {
        const contextMenu = document.getElementById('context-menu');
        if (contextMenu) contextMenu.addEventListener('mouseenter', hideTabPreview);
    }, 50);
}
const FILE_ICON_BASE_PATH = 'icons';
const FILE_ICON_FALLBACK = 'txt';

function getFileExtension(value) {
    const name = String(value || '').split('/').pop() || '';
    const dotIndex = name.lastIndexOf('.');
    if (dotIndex <= 0 || dotIndex === name.length - 1) {
        return '';
    }
    return name.slice(dotIndex + 1).toLowerCase();
}

function getFileIconAssetName(value) {
    const ext = getFileExtension(value);
    return ext || FILE_ICON_FALLBACK;
}

function getFileIconAssetPath(value) {
    return `${FILE_ICON_BASE_PATH}/${encodeURIComponent(getFileIconAssetName(value))}.svg`;
}

function formatDisplayPath(path) {
    const normalized = String(path || '').replace(/^\/+/, '');
    return `/${normalized}`;
}

const TREE_FOLDER_ICON_COLOR = '#eb9a05';
function getTreeDirToggleMarkup(isOpen = false) {
    return `<span class="tree-folder-icon${isOpen ? ' is-open' : ''}" aria-hidden="true"><svg class="tree-folder-icon-svg tree-folder-icon-closed" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 20a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.9a2 2 0 0 1-1.69-.9L9.6 3.9A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2Z"/></svg><svg class="tree-folder-icon-svg tree-folder-icon-open" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 5a2 2 0 0 1 2 2v7a2 2 0 0 1-2 2H9a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h2.5a1.5 1.5 0 0 1 1.2.6l.6.8a1.5 1.5 0 0 0 1.2.6z"/><path d="M3 8.268a2 2 0 0 0-1 1.738V19a2 2 0 0 0 2 2h11a2 2 0 0 0 1.732-1"/></svg></span>`;
}
function setTreeDirToggleOpen(toggleEl, isOpen) {
    if (!toggleEl) return;
    const icon = toggleEl.querySelector('.tree-folder-icon');
    if (icon) icon.classList.toggle('is-open', !!isOpen);
    toggleEl.closest('.node')?.classList.toggle('tree-dir-open', !!isOpen);
}
function syncTreeGuideLastMarkers(container, items) {
    if (!container) return;
    const nodes = container.querySelectorAll(':scope > .node');
    const lastIndex = Array.isArray(items) && items.length ? items.length - 1 : nodes.length - 1;
    nodes.forEach((node, i) => node.classList.toggle('tree-guide-last', i === lastIndex));
}
function syncTreeFolderEndLine(childrenEl, visible) {
    if (!childrenEl) return;
    childrenEl.querySelector(':scope > .tree-folder-end-line')?.remove();
    if (visible && typeof syncTreeChildrenGuide === 'function') syncTreeChildrenGuide(childrenEl);
    else if (!visible) childrenEl.style.removeProperty('--tree-guide-stop');
}
function syncTreeChildrenGuide(childrenEl) {
    if (!childrenEl || !childrenEl.classList.contains('children')) return;
    if (childrenEl.style.display === 'none') {
        childrenEl.style.removeProperty('--tree-guide-stop');
        return;
    }
    const container = childrenEl.querySelector(':scope > .tree-container');
    if (!container) return;
    const nodes = container.querySelectorAll(':scope > .node');
    if (!nodes.length) {
        childrenEl.style.removeProperty('--tree-guide-stop');
        return;
    }
    const lastNode = nodes[nodes.length - 1];
    const cornerH = parseFloat(getComputedStyle(childrenEl).getPropertyValue('--tree-guide-corner')) || 10;
    const childrenTop = childrenEl.getBoundingClientRect().top;
    const lastRect = lastNode.getBoundingClientRect();
    const stop = lastRect.top - childrenTop + lastRect.height / 2 - cornerH;
    childrenEl.style.setProperty('--tree-guide-stop', `${Math.max(0, stop)}px`);
}
function syncAllTreeChildrenGuides(root = document.getElementById('tree')) {
    if (!root) return;
    root.querySelectorAll('.children').forEach((ch) => {
        if (ch.style.display !== 'none') syncTreeChildrenGuide(ch);
    });
}
window.getTreeDirToggleMarkup = getTreeDirToggleMarkup;
window.setTreeDirToggleOpen = setTreeDirToggleOpen;
window.syncTreeFolderEndLine = syncTreeFolderEndLine;
window.syncTreeChildrenGuide = syncTreeChildrenGuide;
window.syncAllTreeChildrenGuides = syncAllTreeChildrenGuides;
window.syncTreeGuideLastMarkers = syncTreeGuideLastMarkers;
function getFolderIconMarkup(extraClass = '') {
    const className = ['app-file-icon', 'app-file-icon-folder', extraClass].filter(Boolean).join(' ');
    return `<span class="${className}" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="${TREE_FOLDER_ICON_COLOR}" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 20a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.9a2 2 0 0 1-1.69-.9L9.6 3.9A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2Z"/></svg></span>`;
}

function getFileIconMarkup(value, extraClass = '') {
    const className = ['app-file-icon', 'app-file-icon-file', extraClass].filter(Boolean).join(' ');
    const src = getFileIconAssetPath(value);
    const fallbackSrc = `${FILE_ICON_BASE_PATH}/${FILE_ICON_FALLBACK}.svg`;
    const safeSrc = escapeHtml(src);
    const safeFallbackSrc = escapeHtml(fallbackSrc);
    return `
        <span class="${className}" aria-hidden="true">
            <img src="${safeSrc}" alt="" loading="lazy" draggable="false" onerror="this.onerror=null;this.src='${safeFallbackSrc}';">
        </span>
    `.trim();
}

function getItemIconMarkup(item, extraClass = '') {
    if (item && item.type === 'dir') {
        return getFolderIconMarkup(extraClass);
    }
    // Use name first to determine the icon (important for trash, where path is an ID)
    const iconTarget = typeof item === 'string' ? item : (item && (item.name || item.path)) || '';
    return getFileIconMarkup(iconTarget, extraClass);
}

window.getFileIconMarkup = getFileIconMarkup;
window.getFolderIconMarkup = getFolderIconMarkup;
window.getItemIconMarkup = getItemIconMarkup;

function getFileIcon(filePath) {
    return getFileIconMarkup(filePath);
}
function formatFilePreview(content, filePath) {
    if (!content) return '<div class="empty-file">Empty file</div>';
    const maxLength = 300;
    let preview = content.substring(0, maxLength);
    if (content.length > maxLength) {
        preview += '...';
    }
    preview = preview.replace(/</g, '&lt;').replace(/>/g, '&gt;');
    const ext = filePath.split('.').pop().toLowerCase();
    if (['js', 'ts', 'html', 'css', 'php', 'py', 'java', 'json', 'm'].includes(ext)) {
        return `<pre class="code-preview language-${ext}"><code>${preview}</code></pre>`;
    }
    return `<pre class="text-preview">${preview}</pre>`;
}
function hideTabPreview() {
    const preview = document.getElementById('tab-preview');
    if (preview) {
        const contextMenu = document.getElementById('context-menu');
        if (contextMenu) contextMenu.removeEventListener('mouseenter', hideTabPreview);
        preview.remove();
    }
}
const MOBILE_CONTEXT_MENU_LONG_PRESS_MS = 450;
const MOBILE_CONTEXT_MENU_MOVE_TOLERANCE = 10;
let mobileContextMenuSuppressUntil = 0;

function shouldSuppressMobileActivation() {
    return isMobileDevice() && Date.now() < mobileContextMenuSuppressUntil;
}

function attachMobileContextMenuTrigger(element, openMenu) {
    let longPressTimer = null;
    let longPressTriggered = false;
    let startX = 0;
    let startY = 0;

    const clearLongPress = () => {
        if (longPressTimer) {
            clearTimeout(longPressTimer);
            longPressTimer = null;
        }
    };

    element.addEventListener('selectstart', (e) => {
        if (isMobileDevice()) {
            e.preventDefault();
        }
    });

    element.addEventListener('touchstart', (e) => {
        if (!isMobileDevice() || e.touches.length !== 1) return;

        const touch = e.touches[0];
        startX = touch.clientX;
        startY = touch.clientY;
        longPressTriggered = false;
        clearLongPress();

        longPressTimer = setTimeout(() => {
            longPressTriggered = true;
            mobileContextMenuSuppressUntil = Date.now() + 700;
            openMenu({
                preventDefault: () => {},
                stopPropagation: () => {},
                clientX: startX,
                clientY: startY
            });
        }, MOBILE_CONTEXT_MENU_LONG_PRESS_MS);
    }, { passive: true });

    element.addEventListener('touchmove', (e) => {
        if (!isMobileDevice() || !longPressTimer || e.touches.length !== 1) return;

        const touch = e.touches[0];
        const movedX = Math.abs(touch.clientX - startX);
        const movedY = Math.abs(touch.clientY - startY);
        if (movedX > MOBILE_CONTEXT_MENU_MOVE_TOLERANCE || movedY > MOBILE_CONTEXT_MENU_MOVE_TOLERANCE) {
            clearLongPress();
        }
    }, { passive: true });

    element.addEventListener('touchend', (e) => {
        clearLongPress();
        if (longPressTriggered) {
            e.preventDefault();
            e.stopPropagation();
        }
    }, { passive: false });

    element.addEventListener('touchcancel', clearLongPress, { passive: true });
}

function initTabEventHandling() {
    document.addEventListener('mousedown', function(e) {
        if (e.target.closest('.tab') || e.target.closest('#tabs-list')) {
            e.stopPropagation();
        }
    });
}
let searchTimeout = null;
async function searchFiles(query) {
  try {
    // Use cached flat tree — much faster than recursive list calls
    let allFiles = cachedFileTree;
    if (!allFiles) {
      try {
        const data = await api('get_file_tree', {}, 'GET');
        if (data && data.ok && Array.isArray(data.items)) {
          cachedFileTree = data.items;
          allFiles = data.items;
        }
      } catch (_) {}
    }
    if (!allFiles) allFiles = await getAllFilesRecursive('');
    const q = query.toLowerCase();
    const results = allFiles.filter(item =>
      item.name.toLowerCase().includes(q) ||
      item.path.toLowerCase().includes(q)
    );
    displaySearchResults(results, query);
  } catch (err) {
    console.error('Search error:', err);
  }
}


function displaySearchResults(results, query) {
  const tree = document.getElementById('tree');
  tree.innerHTML = '';
  if (results.length === 0) {
    tree.innerHTML = `<div class="no-results">${t(i18n, 'noResultsFor')} "${query}"</div>`;
    return;
  }
  const container = document.createElement('div');
  container.className = 'search-results-table';
  const header = document.createElement('div');
  header.className = 'search-results-header';
  header.innerHTML = `
    <h3>${t(i18n, 'searchResults') || 'Search Results'} (${results.length})</h3>
    <button class="btn secondary small" onclick="window.clearSearch ? window.clearSearch() : location.reload()">
      ${t(i18n, 'clear') || 'Clear'}
    </button>
  `;
  container.appendChild(header);
  results.forEach(item => {
    const resolved = getSearchResultDisplayPath(item);
    const node = document.createElement('div');
    node.className = 'search-result-item advanced-search-result search-result-node';
    node.dataset.path = resolved.localPath;
    node.dataset.root = resolved.root;
    node.dataset.type = item.type;
    const icon = getItemIconMarkup({ ...item, path: resolved.localPath });
    const size = item.type === 'file' ? formatBytes(item.size || 0) : '-';
    const date = item.mtime ? new Date(item.mtime * 1000).toLocaleString() : '-';
    node.innerHTML = `
      <div class="result-icon">${icon}</div>
      <div class="result-info">
        <div class="result-name">${highlightText(item.name || resolved.localPath.split('/').pop() || resolved.displayPath, query)}</div>
        <div class="result-path">${highlightText(resolved.displayPath, query)}</div>
        <div class="result-meta">
          <span class="result-size">${size}</span>
          <span class="result-date">${date}</span>
        </div>
      </div>
    `;
    if (selectionState.active && typeof addCheckboxToNode === 'function') addCheckboxToNode(node);
    syncNodeDragHandle(node);
    node.addEventListener('click', (e) => {
      if (selectionState.active) {
        e.preventDefault();
        e.stopPropagation();
        const checkbox = node.querySelector('.selection-checkbox');
        if (checkbox) {
          checkbox.checked = !checkbox.checked;
          handleItemSelection(resolved.localPath, item.type, checkbox.checked, resolved.root);
        }
        return;
      }
      if (item.type === 'dir') {
        if (resolved.root !== getCurrentCloudRoot()) {
          switchCloudRoot(resolved.root, { silent: true }).then(() => expandFolderToPath?.(resolved.localPath));
        } else {
          buildTree('').then(() => expandFolderToPath?.(resolved.localPath));
        }
      } else {
        if (typeof openFileFromTree === 'function') openFileFromTree(resolved.localPath, resolved.root);
        else openFileInTab(resolved.localPath, false, resolved.root);
      }
    });
    node.addEventListener('contextmenu', (e) => {
      e.preventDefault();
      e.stopPropagation();
      if (typeof showContextMenu === 'function') showContextMenu(e, resolved.localPath, item.type, item.name);
    });
    container.appendChild(node);
  });
  tree.appendChild(container);
}
function highlightText(text, query) {
  const safeText = String(text || '').replace(/[&<>"']/g, (ch) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[ch]));
  const safeQuery = String(query || '').replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  if (!safeQuery) return safeText;
  const regex = new RegExp(`(${safeQuery})`, 'gi');
  return safeText.replace(regex, '<mark>$1</mark>');
}
async function getAllFilesRecursive(path) {
  const items = await api('list', { path }, 'GET');
  let allItems = [...items.items];
  for (const item of items.items) {
    if (item.type === 'dir') {
      const subItems = await getAllFilesRecursive(item.path);
      allItems = allItems.concat(subItems);
    }
  }
  return allItems;
}
function initF2Rename() {
    document.addEventListener('keydown', (e) => {
        if (e.key === 'F2') {
            e.preventDefault();
            const focusedElement = document.activeElement;
            if (focusedElement) {
                const treeNode = focusedElement.closest('.node');
                if (treeNode) {
                    const path = treeNode.dataset.path;
                    const type = treeNode.dataset.type;
                    if (path) {
                        renamePath(path);
                        return;
                    }
                }
                const tabElement = focusedElement.closest('.tab');
                if (tabElement) {
                    const tabId = tabElement.dataset.tabId;
                    if (tabId) {
                        const tab = tabsState.tabs.find(t => t.id == tabId);
                        if (tab) {
                            renamePath(tab.path);
                            return;
                        }
                    }
                }
                const searchResult = focusedElement.closest('.search-result-node');
                if (searchResult) {
                    const path = searchResult.dataset.path;
                    const type = searchResult.dataset.type;
                    if (path && type === 'file') {
                        renamePath(path);
                        return;
                    }
                }
            }
            const hoveredElement = document.querySelector(':hover');
            if (hoveredElement && hoveredElement !== focusedElement) {
                const treeNode = hoveredElement.closest('.node');
                if (treeNode) {
                    const path = treeNode.dataset.path;
                    const type = treeNode.dataset.type;
                    if (path) {
                        renamePath(path);
                        return;
                    }
                }
                const tabElement = hoveredElement.closest('.tab');
                if (tabElement) {
                    const tabId = tabElement.dataset.tabId;
                    if (tabId) {
                        const tab = tabsState.tabs.find(t => t.id == tabId);
                        if (tab) {
                            renamePath(tab.path);
                            return;
                        }
                    }
                }
                const searchResult = hoveredElement.closest('.search-result-node');
                if (searchResult) {
                    const path = searchResult.dataset.path;
                    const type = searchResult.dataset.type;
                    if (path && type === 'file') {
                        renamePath(path);
                        return;
                    }
                }
            }
            // Update the tab title with an SVG icon
            const activeTab = tabsState.tabs.find(tab => tab.id === tabsState.activeTabId);
            if (activeTab) {
                // Keep the original title but add a flag that this is a photo
                activeTab.isImage = true;
                activeTab.title = path.split('/').pop();
                renderTabs();
            }
            flashStatus(t(i18n, 'selectItemToRename'));
        }
    });
}

function bindUI(i18n) {

    if (window.initAdvancedSearch) {
        window.initAdvancedSearch();
    }
    document.getElementById('btn-compile-send').addEventListener('click', sendInputToProgram);
    document.getElementById('btn-close-compile-modal').addEventListener('click', closeCompileModal);
    document.getElementById('btn-minimize-compile-modal')?.addEventListener('click', (e) => {
        e.stopPropagation();
        minimizeCompileModal();
    });

    initTabs();
    initRecentFilesKeyboard();
    renderViewerEmptyState();
    initCodeActionsMenuIcons();
    document.getElementById('btn-close-all-tabs').addEventListener('click', closeAllTabs);
    window.addEventListener('resize', () => {
        tabsState.maxTabs = window.innerWidth <= 768 ? 4 : 10;
    });
    const mainSidebar = document.querySelector('.sidebar');
    const syncSidebarForViewport = () => {
        if (!mainSidebar) return;
        if (window.innerWidth >= 769) {
            mainSidebar.classList.remove('active');
        } else {
            mainSidebar.classList.toggle('active', localStorage.getItem('sidebarVisible') === 'true');
        }
        syncToolbarVisibility();
    };
    document.getElementById('btn-toggle-sidebar').addEventListener('click', () => {
    if (window.innerWidth >= 769 || !mainSidebar) return;
    mainSidebar.classList.toggle('active');
    localStorage.setItem('sidebarVisible', mainSidebar.classList.contains('active') ? 'true' : 'false');
    syncToolbarVisibility();
    });
    syncSidebarForViewport();
    window.addEventListener('resize', syncSidebarForViewport);
    document.getElementById('btn-refresh-tree').addEventListener('click', async function() {
    const btn = this;
    btn.classList.add('rotating');
    await invalidateTreeCache();
    await buildTree('');
    await syncRootModeView();
    btn.classList.remove('rotating');
    flashStatus(t(i18n, 'foldersRefreshed') || 'Folder list updated');
    });
    document.getElementById('btn-tree-back')?.addEventListener('click', () => {
        if (typeof navigateTreeBack === 'function') navigateTreeBack();
    });
    initDeleteModal();
    initModalKeyboardNav();
    document.getElementById('btn-trash-delete-selected')?.addEventListener('click', () => deleteSelectedItems());
    document.getElementById('btn-trash-restore-selected')?.addEventListener('click', () => restoreSelectedTrashItems());
    document.getElementById('btn-trash-restore-all')?.addEventListener('click', () => restoreAllTrashItems());
    document.getElementById('btn-trash-delete-all')?.addEventListener('click', () => emptyTrashWithConfirmation());
    initFileModals();
    initLangSwitcher();
    initCreateFolderModal();
    initTabEventHandling();
    initF2Rename();
    initTabScrolling();
    initFullscreen();
    initSortFilter();
    initTreeAutoShift();
    initShareFeature();
    if (!currentUserState.user) {
        syncSettingsControls();
    }
    initSelectionPanelHandlers();
    if (window.initStorageUsageModal) {
        window.initStorageUsageModal(i18n, {
            api,
            flashStatus,
            formatBytes,
            t
        });
    }

function syncToolbarVisibility() {
    const sidebar = document.querySelector('.sidebar');
    const toolbar = document.getElementById('root-toolbar');
    if (!sidebar || !toolbar) return;
    // When sidebar is NOT active (visible), toolbar should be visible
    // When sidebar IS active (hidden), toolbar should also be hidden
    toolbar.classList.toggle('hidden-by-sidebar', sidebar.classList.contains('active'));
}
function syncRootToolbarActive(root = getCurrentCloudRoot()) {
    const toolbar = document.getElementById('root-toolbar');
    if (!toolbar) return;
    toolbar.querySelectorAll('.root-toolbar-btn').forEach((btn) => {
        if (btn.id === 'btn-root-groups') {
            btn.classList.toggle('active', isCloudGroupRoot(root));
            return;
        }
        btn.classList.toggle('active', btn.dataset.root === root);
    });
}
async function switchCloudRoot(root, options = {}) {
    const nextRoot = (cloudRootState.allowed.has(root) || isCloudGroupRoot(root)) ? root : 'main';
    if (isCloudGroupRoot(nextRoot)) cloudRootState.allowed.add(nextRoot);
    const previousRoot = getCurrentCloudRoot();
    if (!options.keepSelection && nextRoot !== previousRoot) clearSelection();
    if (typeof persistTreeBrowsePath === 'function') persistTreeBrowsePath(previousRoot);
    setCurrentCloudRoot(nextRoot);
    syncRootToolbarActive(nextRoot);
    cachedFileTree = null;
    if (typeof loadTreeBrowsePath === 'function') loadTreeBrowsePath(nextRoot);
    await buildTree('');
    await syncRootModeView();
    await restoreFolderState();
    if (!options.silent && nextRoot !== previousRoot) {
        const rootLabel = getCloudRootDisplayName(nextRoot);
        flashStatus(t(window.i18n || {}, 'rootSwitched', { root: rootLabel }));
    }
    if (window.cloudGroups?.updateSectionTitle) window.cloudGroups.updateSectionTitle();
    if (window.cloudGroups?.renderGroupSwitcher) window.cloudGroups.renderGroupSwitcher();
    if (window.cloudGroups?.updateToolbarButton) window.cloudGroups.updateToolbarButton();
}
window.switchCloudRoot = switchCloudRoot;
function initRootToolbar(i18n) {
    const toolbar = document.getElementById('root-toolbar');
    if (!toolbar) return;
    let dragSwitchTimer = null;
    const clearRootDragState = () => {
        if (dragSwitchTimer) {
            clearTimeout(dragSwitchTimer);
            dragSwitchTimer = null;
        }
        toolbar.querySelectorAll('.root-toolbar-btn.tree-drop-target').forEach((el) => el.classList.remove('tree-drop-target'));
    };
    const root = getCurrentCloudRoot();
    toolbar.querySelectorAll('.root-toolbar-btn').forEach((btn) => {
        const isGroupsBtn = btn.id === 'btn-root-groups';
        if (isGroupsBtn) {
            btn.classList.toggle('active', isCloudGroupRoot(root));
            return;
        }
        btn.classList.toggle('active', btn.dataset.root === root);
        btn.addEventListener('click', async () => {
            const nextRoot = btn.dataset.root || 'main';
            if (nextRoot === getCurrentCloudRoot()) return;
            try {
                await switchCloudRoot(nextRoot);
            } catch (err) {
                flashStatus(t(i18n, 'errorPrefix') + err.message);
            }
        }, { once: false });
        btn.addEventListener('dragover', (event) => {
            const isExternalFiles = hasExternalFilesDrag(event);
            const paths = getTreeDragPayload(event);
            if (!paths.length && !isExternalFiles) return;
            event.preventDefault();
            event.dataTransfer.dropEffect = isExternalFiles ? 'copy' : 'move';
            btn.classList.add('tree-drop-target');
            const nextRoot = btn.dataset.root || 'main';
            if (nextRoot !== getCurrentCloudRoot() && !dragSwitchTimer) {
                dragSwitchTimer = setTimeout(async () => {
                    dragSwitchTimer = null;
                    try {
                        await switchCloudRoot(nextRoot, { silent: true, keepSelection: true });
                    } catch (err) {
                        flashStatus(t(i18n, 'errorPrefix') + err.message);
                    }
                }, 550);
            }
        });
        btn.addEventListener('dragleave', (event) => {
            if (btn.contains(event.relatedTarget)) return;
            btn.classList.remove('tree-drop-target');
            if (dragSwitchTimer) {
                clearTimeout(dragSwitchTimer);
                dragSwitchTimer = null;
            }
        });
        btn.addEventListener('drop', async (event) => {
            const isExternalFiles = hasExternalFilesDrag(event);
            const paths = getTreeDragPayload(event);
            if (!paths.length && !isExternalFiles) return;
            event.preventDefault();
            event.stopPropagation();
            const targetRoot = btn.dataset.root || 'main';
            clearRootDragState();
            if (targetRoot !== getCurrentCloudRoot()) {
                await switchCloudRoot(targetRoot, { silent: true, keepSelection: true });
            }
            if (isExternalFiles) {
                const files = event.dataTransfer?.files;
                if (files?.length && typeof window.uploadFilesToPath === 'function') {
                    window.uploadFilesToPath(Array.from(files), '');
                }
                return;
            }
            await moveTreeDragPaths(paths, '', targetRoot, currentTreeDragSourceRoot || getCurrentCloudRoot());
            currentTreeDragPaths = [];
            currentTreeDragSourceRoot = getCurrentCloudRoot();
        });
    });
    document.addEventListener('dragend', clearRootDragState);
}
    document.getElementById('btn-code-actions').classList.add('hidden');
    const tree = document.getElementById('tree');
    const sidebar = document.querySelector('.sidebar');
    tree.addEventListener('contextmenu', (e) => {
    if (!e.target.closest('.node') && !e.target.closest('.search-result-node')) {
        e.preventDefault();
        showContextMenu(e, '', 'dir', '');
    }
    });
    
    // Left-click on empty tree also opens context menu
    tree.addEventListener('click', (e) => {
        if (tree.classList.contains('is-empty') && !e.target.closest('.node') && !e.target.closest('.search-result-node')) {
            showContextMenu(e, '', 'dir', '');
        }
    });
    window.addEventListener('beforeunload', () => {
        flushPendingAutoSavesSync();
        clearAutoSaveTimer();
    });
    window.addEventListener('resize', () => {
        if (window.monacoManager) {
            window.monacoManager.resize();
        }
    });
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            flushPendingAutoSaves();
        }
    });
    document.addEventListener('click', (e) => {
        if (!e.target.closest('.dropdown-menu') && 
            !e.target.closest('#btn-code-actions') &&
            !e.target.closest('.settings') &&
            !e.target.closest('#btn-settings')) {
            closeAllDropdowns();
        }
    });
    sidebar.addEventListener('click', (e) => {
        if (!e.target.closest('.node') && 
            !e.target.closest('.search-result-node') && 
            !e.target.closest('.section-title')) {
        showContextMenu(e, '', 'dir', '');
        }
    });
    initSidebarResizer();
    initRootToolbar(i18n);
    if (window.initTopbarUI) {
        window.initTopbarUI(i18n);
    }
    document.getElementById('btn-code-actions').addEventListener('click', toggleCodeActionsMenu);
    document.getElementById('btn-apply-code-changes').addEventListener('click', applyCodeChanges);
    document.getElementById('btn-close-code-modal').addEventListener('click', closeCodeModal);
    document.getElementById('quota-mb').addEventListener('change', updateQuota);
    document.getElementById('quota-mb').addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.target.blur(); } });
    document.getElementById('session-limit-mb').addEventListener('change', updateSessionLimit);
    document.getElementById('session-limit-mb').addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.target.blur(); } });
    document.getElementById('btn-save').addEventListener('click', async () => {
    const ok = await saveFile();
    if (ok) showSuccessCheck(document.getElementById('btn-save'));
    });
    document.getElementById('btn-monaco-undo')?.addEventListener('click', () => {
    const active = window.monacoManager?.getActiveEditor?.();
    if (!active) return;
    window.monacoManager?.undo?.(active.tabId);
    setTimeout(updateMonacoUndoRedoToolbar, 0);
    });
    document.getElementById('btn-monaco-redo')?.addEventListener('click', () => {
    const active = window.monacoManager?.getActiveEditor?.();
    if (!active) return;
    window.monacoManager?.redo?.(active.tabId);
    setTimeout(updateMonacoUndoRedoToolbar, 0);
    });
    document.getElementById('btn-copy').addEventListener('click', async () => {
    const path = document.getElementById('viewer').dataset.path;
    if (path) {
        const ok = await copyViewer(path);
        if (ok) showSuccessCheck(document.getElementById('btn-copy'));
    }
    });
    document.getElementById('btn-download').addEventListener('click', async () => {
    const ok = await downloadCurrent();
    if (ok) showSuccessCheck(document.getElementById('btn-download'));
    });
    document.addEventListener('click', (e) => {
    const contextMenu = document.getElementById('context-menu');
    if (contextMenu && !contextMenu.contains(e.target)) {
        contextMenu.remove();
    }
    });
    window.addEventListener('cloudMonacoUndoRedoChanged', () => {
    updateMonacoUndoRedoToolbar();
    });

    const usersTable = document.getElementById('users-table');
    if (usersTable) {
        usersTable.addEventListener('change', async function(e) {
            const selectedUser = e.target.value;
            
            // Load folder state for the new user
            await loadFolderState();
            
            // Reload the tree
            await buildTree('');
            
            // Restore state
            await restoreFolderState();
            
            flashStatus(t(i18n, 'userSwitched', { user: selectedUser }));
        });
    }


    // Add to the end of the bindUI() function
    const observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            if (mutation.type === 'attributes' && mutation.attributeName === 'class') {
                const modal = mutation.target;
                if (modal.classList && !modal.classList.contains('hidden')) {
                    // The modal became visible
                    const fileView = document.getElementById('file-view');
                    if (fileView && fileView.classList.contains('fullscreen-mode')) {
                        modal.style.zIndex = '30000';
                    }
                }
            }
        });
    });

    // Track all modals
    document.querySelectorAll('.modal').forEach(modal => {
        observer.observe(modal, { attributes: true, attributeFilter: ['class'] });
    });

    setTimeout(() => {
        initFormatTagsClickHandler();
    }, 1000);
}
function initSortFilter() {
    const sortBtn = document.getElementById('btn-sort-filter');
    const sortMenu = document.getElementById('sort-filter-menu');
    if (!sortBtn || !sortMenu) return;
    if (sortMenu.parentElement !== document.body) document.body.appendChild(sortMenu);
    sortBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        const isHidden = sortMenu.classList.contains('hidden');
        closeAllDropdowns();
        if (isHidden) {
            sortMenu.classList.remove('hidden');
            positionSortMenu(sortBtn, sortMenu);
            document.querySelectorAll('.sort-option').forEach(option => {
                const svg = option.querySelector('svg');
                if (option.dataset.sort === sortState.currentSort) {
                    svg.style.display = 'block';
                    option.style.fontWeight = '600';
                } else {
                    svg.style.display = 'none';
                    option.style.fontWeight = '400';
                }
            });
        }
    });
    document.addEventListener('click', (e) => {
        if (!sortMenu.contains(e.target) && !sortBtn.contains(e.target)) {
            sortMenu.classList.add('hidden');
        }
    });
    document.querySelectorAll('.sort-option').forEach(option => {
        option.addEventListener('click', async () => {
            const sortType = option.dataset.sort;
            sortState.currentSort = sortType;
            localStorage.setItem('treeSortType', sortType);
            await buildTree('');
            sortMenu.classList.add('hidden');
        });
    });
}
function positionSortMenu(button, menu) {
    const buttonRect = button.getBoundingClientRect();
    menu.style.visibility = 'hidden';
    menu.style.display = 'block';
    menu.style.position = 'fixed';
    menu.style.left = '0';
    menu.style.top = '0';
    const menuWidth = menu.offsetWidth;
    const menuHeight = menu.offsetHeight;
    menu.style.visibility = '';
    menu.style.display = '';
    const padding = 10;
    const viewportWidth = window.innerWidth;
    const viewportHeight = window.innerHeight;
    const minTop = viewportHeight * 0.1;
    let left = buttonRect.left;
    if (left + menuWidth > viewportWidth - padding) {
        left = viewportWidth - menuWidth - padding;
    }
    if (left < padding) {
        left = padding;
    }
    let top = Math.max(buttonRect.bottom + 5, minTop);
    if (top + menuHeight > viewportHeight - padding) {
        top = Math.max(buttonRect.top - menuHeight - 5, minTop);
        if (top + menuHeight > viewportHeight - padding) {
            top = minTop;
            const maxMenuHeight = viewportHeight - minTop - padding;
            menu.style.maxHeight = `${maxMenuHeight}px`;
            menu.style.overflowY = 'auto';
        }
    }
    menu.style.position = 'fixed';
    menu.style.left = `${left}px`;
    menu.style.top = `${top}px`;
    menu.style.maxWidth = `${viewportWidth - left - padding}px`;
    menu.style.zIndex = '35000';
}
function toggleSelectionMode() {
    selectionState.active = !selectionState.active;
    const contextMenu = document.getElementById('context-menu');
    if (contextMenu) {
        contextMenu.remove();
    }
    
    if (selectionState.active) {
        selectionState.mode = 'select';
        document.querySelectorAll('#tree .node, #tree .advanced-search-result').forEach(node => {
            addCheckboxToNode(node);
        });

        // Add a class to change the cursor
        document.getElementById('tree').classList.add('selection-mode');
        syncAllTreeNodeDragHandles();
        flashStatus(t(i18n, 'selectionModeActive'));
    } else {
        clearSelection();
        
        // Remove the cursor class
        const tree = document.getElementById('tree');
        if (tree) {
            tree.classList.remove('selection-mode');
            // Force reset the cursor
            tree.style.cursor = '';
        }
        
        // Hide the lasso if active
        if (lassoState.element) {
            lassoState.element.style.display = 'none';
        }
        
        // Reset lasso state
        lassoState.active = false;
        lassoState.mode = null;
        
        // Restore text selection
        document.body.style.userSelect = '';
        
        // Remove all checkboxes
        document.querySelectorAll('.selection-checkbox').forEach(checkbox => {
            checkbox.remove();
        });
        syncAllTreeNodeDragHandles();
        flashStatus(t(i18n, 'selectionModeDeactivated'));
    }
    
    updateSelectionMenuItem();
    updateSelectionUI();
}

function processTrashElementsInLasso(lassoRect, mode) {
    if (!selectionState.active || !isTrashRoot()) return;

    const elementsInRect = getTrashElementsInRect(lassoRect);
    const next = mode === 'toggle' ? new Set(selectionState.selectedItems) : new Set(selectionState.selectedItems);

    elementsInRect.forEach((card) => {
        const path = card.dataset.path;
        if (!path) return;
        if (mode === 'toggle') {
            if (next.has(path)) next.delete(path);
            else next.add(path);
            return;
        }
        next.add(path);
    });

    selectionState.selectedItems = next;
    updateSelectionUI();
}
async function removeCommentsFromCode() {
    const activeTabId = tabsState.activeTabId;
    if (!activeTabId) {
        flashStatus(t(i18n, 'noFileToProcess'));
        return;
    }
    const activeTab = tabsState.tabs.find(tab => tab.id === activeTabId);
    if (!activeTab) {
        flashStatus(t(i18n, 'noFileToProcess'));
        return;
    }
    const path = activeTab.path;
    if (!path) {
        flashStatus(t(i18n, 'noFileToProcess'));
        return;
    }
    const content = getCurrentEditorContent(activeTabId);
    if (!content) {
        flashStatus(t(i18n, 'noContentToProcess'));
        return;
    }
    const ext = path.split('.').pop().toLowerCase();
    const languageMap = {
        'js': 'javascript', 'ts': 'javascript', 'php': 'php', 'html': 'html', 'htm': 'html',
        'c': 'c', 'cpp': 'c', 'h': 'c', 'py': 'python', 'css': 'css', 'sql': 'sql',
        'java': 'java', 'rb': 'ruby', 'sh': 'bash', 'ps1': 'powershell',
        'm': 'matlab'
    };
    const language = languageMap[ext] || 'auto';
    try {
        const response = await fetch('remove_comments.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: new URLSearchParams({
                action: 'remove_comments',
                code: content,
                language: language
            })
        });
        const data = await response.json();
        if (data.success) {
            const stats = {
                removed_chars: data.removed_chars || (content.length - data.cleaned_code.length),
                original_length: data.original_length || content.length,
                cleaned_length: data.cleaned_length || data.cleaned_code.length
            };
            showCodeModal(
                t(i18n, 'codeWithoutComments'), 
                data.cleaned_code, 
                content,
                stats
            );
        } else {
            flashStatus(t(i18n, 'removeCommentsFailed') + ': ' + (data.error || 'Unknown error'));
        }
    } catch (err) {
        flashStatus(t(i18n, 'removeCommentsError') + ': ' + err.message);
    }
}
function updateSelectionMenuItem() {
    const selectionMenuItem = document.querySelector('[data-i18n="select"]');
    const selectionCounter = document.getElementById('selection-counter');
    const selectionActionPanel = document.getElementById('selection-action-panel');
    if (selectionMenuItem) {
        selectionMenuItem.textContent = selectionState.active ? 
            t(i18n, 'deselect') : 
            t(i18n, 'select');
        selectionMenuItem.title = selectionState.active ? 
            t(i18n, 'deselectTooltip') : 
            t(i18n, 'selectTooltip');
    }
    if (selectionCounter) {
        const count = selectionState.selectedItems.size;
        selectionCounter.textContent = count > 0 ? 
            t(i18n, 'selectedCount', { count }) : 
            '';
        selectionCounter.style.display = count > 0 ? 'inline' : 'none';
    }
    if (selectionActionPanel) {
        const count = selectionState.selectedItems.size;
        if (count > 0 && selectionState.active) {
            selectionActionPanel.classList.remove('hidden');
            const moveBtn = selectionActionPanel.querySelector('#btn-move-selected');
            const deleteBtn = selectionActionPanel.querySelector('#btn-delete-selected');
            const downloadBtn = selectionActionPanel.querySelector('#btn-download-selected');
            const clearBtn = selectionActionPanel.querySelector('#btn-clear-selection');
            if (moveBtn) {
                const label = moveBtn.querySelector('span');
                if (label) label.textContent = t(i18n, 'moveSelected', { count });
                moveBtn.title = t(i18n, 'moveSelectedTooltip', { count });
            }
            if (downloadBtn) {
                const label = downloadBtn.querySelector('span');
                if (label) label.textContent = t(i18n, 'downloadSelected', { count });
                downloadBtn.title = t(i18n, 'downloadSelectedTooltip', { count });
            }
            if (deleteBtn) {
                const label = deleteBtn.querySelector('span');
                if (label) label.textContent = t(i18n, 'deleteSelected', { count });
                deleteBtn.title = t(i18n, 'deleteSelectedTooltip', { count });
            }
            if (clearBtn) {
                clearBtn.title = t(i18n, 'clearSelectionTooltip');
                clearBtn.setAttribute('aria-label', t(i18n, 'clearSelectionTooltip'));
            }
        } else {
            selectionActionPanel.classList.add('hidden');
        }
    }
    document.querySelectorAll('#tree .selection-checkbox').forEach(checkbox => {
        const node = checkbox.closest('.node, .advanced-search-result');
        if (node) {
            const itemPath = node.dataset.path;
            checkbox.checked = selectionState.selectedItems.has(itemPath);
        }
    });
    const checkboxes = document.querySelectorAll('.selection-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.style.display = selectionState.active ? 'inline-block' : 'none';
    });
    const sidebar = document.querySelector('.sidebar');
    if (sidebar) {
        if (selectionState.active) {
            sidebar.classList.add('selection-mode-active');
        } else {
            sidebar.classList.remove('selection-mode-active');
        }
    }
    updateSelectionStatus();
}
function initSelectionPanelHandlers() {
    const moveBtn = document.getElementById('btn-move-selected');
    const deleteBtn = document.getElementById('btn-delete-selected');
    const downloadBtn = document.getElementById('btn-download-selected'); // New button
    const clearBtn = document.getElementById('btn-clear-selection');

    if (moveBtn) {
        moveBtn.addEventListener('click', () => {
            if (selectionState.selectedItems.size > 0) {
                moveSelectedItems();
            }
        });
    }

    if (deleteBtn) {
        deleteBtn.addEventListener('click', () => {
            if (selectionState.selectedItems.size > 0) {
                deleteSelectedItems();
            }
        });
    }

    // New handler for the download button
    if (downloadBtn) {
        downloadBtn.addEventListener('click', () => {
            if (selectionState.selectedItems.size > 0) {
                downloadSelectedItems();
            }
        });
    }

    if (clearBtn) {
        clearBtn.addEventListener('click', () => {
            clearSelection();
        });
    }
}
function updateSelectionStatus() {
    const statusRight = document.getElementById('status-right-content') || document.getElementById('status-right');
    const count = selectionState.selectedItems.size;
    if (selectionState.active && count > 0) {
        statusRight.textContent = t(i18n, 'selectionStatus', { 
            count: count,
            mode: selectionState.mode
        });
        statusRight.style.color = 'var(--accent)';
    } else if (selectionState.active) {
        statusRight.textContent = t(i18n, 'selectionModeActive');
        statusRight.style.color = 'var(--accent)';
    } else {
        statusRight.textContent = '';
        statusRight.style.color = '';
    }
}
function syncNodeDragHandle(node) {
    if (!node?.dataset?.path) return;
    const type = node.dataset.type || 'file';
    const path = node.dataset.path;
    const isSearchResult = node.classList.contains('advanced-search-result');
    const toggle = node.querySelector(':scope > .toggle');
    const resultIcon = node.querySelector('.result-icon');
    const iconHandle = toggle || resultIcon;
    node.draggable = false;
    if (iconHandle) {
        iconHandle.draggable = false;
        iconHandle.classList.remove('selection-drag-handle');
    }
    if (selectionState.active) {
        if (iconHandle && selectionState.selectedItems.has(path)) {
            iconHandle.draggable = true;
            iconHandle.classList.add('selection-drag-handle');
        }
        return;
    }
    if (isSearchResult || type === 'file') node.draggable = true;
}
function syncAllTreeNodeDragHandles() {
    document.querySelectorAll('#tree .node[data-path], #tree .advanced-search-result[data-path]').forEach(syncNodeDragHandle);
}
function addCheckboxToNode(node) {
    if (node.querySelector('.selection-checkbox')) return;
    
    const checkbox = document.createElement('input');
    checkbox.type = 'checkbox';
    checkbox.className = 'selection-checkbox';
    
    const path = node.dataset.path;
    const type = node.dataset.type;
    
    checkbox.checked = selectionState.selectedItems.has(path);
    
    checkbox.addEventListener('click', (e) => {
        e.stopPropagation();
        handleItemSelection(path, type, checkbox.checked);
        if (checkbox.checked) {
            node.classList.add('selected');
        } else {
            node.classList.remove('selected');
        }
    });
    
    const label = node.querySelector(':scope > .label');
    if (label) {
        node.insertBefore(checkbox, label);
        return;
    }

    if (node.classList.contains('advanced-search-result')) {
        const info = node.querySelector('.result-info');
        const name = node.querySelector('.result-name');
        if (info && name && name.parentNode === info) {
            info.insertBefore(checkbox, name);
        } else if (info) {
            info.prepend(checkbox);
        } else {
            node.prepend(checkbox);
        }
        return;
    }

    node.prepend(checkbox);
}
function handleItemSelection(path, type, selected, root = null) {
    if (isTrashRoot()) {
        if (selected) selectionState.selectedItems.add(String(path || ''));
        else selectionState.selectedItems.delete(String(path || ''));
        updateSelectionUI();
        return;
    }
    const p = String(path || '');
    const esc = CSS.escape(p);
    if (selected) {
        selectionState.selectedItems.add(p);
        const nodeForRoot = document.querySelector(`#tree .advanced-search-result[data-path="${esc}"], #tree .node[data-path="${esc}"]`);
        selectionState.selectedRoots.set(p, root || nodeForRoot?.dataset.root || getCurrentCloudRoot());
        
        // Animate the addition
        const node = document.querySelector(`#tree .node[data-path="${esc}"], #tree .advanced-search-result[data-path="${esc}"]`);
        if (node) {
            node.classList.add('selected');
            node.style.animation = 'none';
            node.offsetHeight; // Trigger reflow
            node.style.animation = 'nodeHighlight 0.3s ease-out';
        }
    } else {
        selectionState.selectedItems.delete(p);
        selectionState.selectedRoots.delete(p);
        const node = document.querySelector(`#tree .node[data-path="${esc}"], #tree .advanced-search-result[data-path="${esc}"]`);
        if (node) {
            node.classList.remove('selected');
        }
    }
    updateSelectionUI();
}
function updateSelectionUI() {
    const count = selectionState.selectedItems.size;
    if (isTrashRoot()) {
        document.querySelectorAll('.trash-item').forEach((node) => {
            const path = node.dataset.path;
            const selected = selectionState.selectedItems.has(path);
            node.classList.toggle('selected', selected);
            const checkbox = node.querySelector('.trash-item-checkbox');
            if (checkbox) checkbox.checked = selected;
        });
        const actionPanel = document.getElementById('selection-action-panel');
        if (actionPanel) actionPanel.classList.add('hidden');
        const statusRight = document.getElementById('status-right-content') || document.getElementById('status-right');
        statusRight.textContent = count > 0
            ? t(i18n, 'selectionStatus', { count, mode: 'select' })
            : '';
        statusRight.style.color = count > 0 ? 'var(--accent)' : '';
        updateTrashToolbarSelectionState();
        return;
    }
    
    // Update node selection
    document.querySelectorAll('#tree .node, #tree .advanced-search-result').forEach(node => {
        const path = node.dataset.path;
        if (selectionState.selectedItems.has(path)) {
            node.classList.add('selected');
        } else {
            node.classList.remove('selected');
        }
    });
    
    // Update checkboxes
    document.querySelectorAll('#tree .selection-checkbox').forEach(checkbox => {
        const node = checkbox.closest('.node, .advanced-search-result');
        if (node) {
            const path = node.dataset.path;
            checkbox.checked = selectionState.selectedItems.has(path);
        }
    });
    
    // Update the actions panel
    const actionPanel = document.getElementById('selection-action-panel');
    if (actionPanel) {
        if (count > 0 && selectionState.active) {
            actionPanel.classList.remove('hidden');
            
            // Update the text of all buttons
            const moveBtn = document.getElementById('btn-move-selected');
            const deleteBtn = document.getElementById('btn-delete-selected');
            const downloadBtn = document.getElementById('btn-download-selected');
            const clearBtn = document.getElementById('btn-clear-selection');
            
            if (moveBtn) {
                const moveSpan = moveBtn.querySelector('span');
                if (moveSpan) {
                    moveSpan.textContent = t(i18n, 'moveSelected', { count: count });
                }
                moveBtn.title = t(i18n, 'moveSelectedTooltip', { count: count });
            }
            
            if (deleteBtn) {
                const deleteSpan = deleteBtn.querySelector('span');
                if (deleteSpan) {
                    deleteSpan.textContent = t(i18n, 'deleteSelected', { count: count });
                }
                deleteBtn.title = t(i18n, 'deleteSelectedTooltip', { count: count });
            }
            
            if (downloadBtn) {
                const downloadSpan = downloadBtn.querySelector('span');
                if (downloadSpan) {
                    downloadSpan.textContent = t(i18n, 'downloadSelected', { count: count });
                }
                downloadBtn.title = t(i18n, 'downloadSelectedTooltip', { count: count });
            }
            
            if (clearBtn) {
                const clearSpan = clearBtn.querySelector('span');
                if (clearSpan) {
                    clearSpan.textContent = t(i18n, 'clearSelection');
                }
                clearBtn.title = t(i18n, 'clearSelectionTooltip');
            }
        } else {
            actionPanel.classList.add('hidden');
        }
    }
    
    // Update the status bar
    const statusRight = document.getElementById('status-right-content') || document.getElementById('status-right');
    if (selectionState.active && count > 0) {
        statusRight.textContent = t(i18n, 'selectionStatus', { 
            count: count,
            mode: selectionState.mode || 'select'
        });
        statusRight.style.color = 'var(--accent)';
    } else if (selectionState.active) {
        statusRight.textContent = t(i18n, 'selectionModeActive');
        statusRight.style.color = 'var(--accent)';
    } else {
        statusRight.textContent = '';
        statusRight.style.color = '';
    }
    syncAllTreeNodeDragHandles();
}

// Add a handler for the Ctrl key (toggle mode)
document.addEventListener('keydown', (e) => {
    if (e.key === 'Control' && lassoState.active) {
        lassoState.mode = 'toggle';
    }
});

document.addEventListener('keyup', (e) => {
    if (e.key === 'Control' && lassoState.active) {
        lassoState.mode = 'select';
    }
});


function clearSelection() {
    selectionState.selectedItems.clear();
    selectionState.selectedRoots.clear();
    selectionState.active = isTrashRoot();
    if (isTrashRoot()) {
        trashState.anchorIndex = -1;
        updateSelectionUI();
        return;
    }
    
    // Remove the selection class from all nodes
    document.querySelectorAll('#tree .node.selected, #tree .advanced-search-result.selected').forEach(node => {
        node.classList.remove('selected');
    });
    
    // Remove checkboxes
    document.querySelectorAll('#tree .selection-checkbox').forEach(checkbox => {
        checkbox.remove();
    });
    
    // Hide the actions panel
    const actionPanel = document.getElementById('selection-action-panel');
    if (actionPanel) {
        actionPanel.classList.add('hidden');
    }
    
    // Reset the tree cursor
    const tree = document.getElementById('tree');
    if (tree) {
        tree.classList.remove('selection-mode');
        tree.style.cursor = '';
    }
    
    // Hide the lasso if active
    if (lassoState.element) {
        lassoState.element.style.display = 'none';
    }
    
    // Reset lasso state
    lassoState.active = false;
    lassoState.mode = null;
    
    // Restore text selection
    document.body.style.userSelect = '';
    
    updateSelectionUI();
}
function initLangSwitcher() {
  const langOptions = document.querySelectorAll('.lang-option');
  let currentLang = 'en';
  langOptions.forEach(option => {
    option.addEventListener('click', function() {
      const lang = this.dataset.lang;
      langOptions.forEach(opt => opt.classList.remove('active'));
      this.classList.add('active');
      currentLang = lang;
      localStorage.setItem('preferredLang', lang);
      applyTransliterationToAll(currentLang);
    });
  });
  const savedLang = localStorage.getItem('preferredLang');
  if (savedLang) {
    const savedOption = document.querySelector(`.lang-option[data-lang="${savedLang}"]`);
    if (savedOption) {
      langOptions.forEach(opt => opt.classList.remove('active'));
      savedOption.classList.add('active');
      currentLang = savedLang;
    }
  }
  document.addEventListener('keydown', handleAutoTransliteration);
  document.addEventListener('keyup', handleWordCompletion);
}
function handleAutoTransliteration(e) {
  if (e.ctrlKey || e.altKey || e.metaKey) return;
  const activeLang = document.querySelector('.lang-option.active')?.dataset.lang;
  const focusedElement = document.activeElement;
  const isInEditor = focusedElement?.tagName === 'TEXTAREA' || 
                    focusedElement?.classList.contains('CodeMirror') ||
                    (focusedElement?.closest('.CodeMirror') && focusedElement?.closest('.cm-content'));
  if (isInEditor && activeLang && activeLang !== 'en') {
    setTimeout(() => {
      transliterateLastChar(activeLang, focusedElement);
    }, 0);
  }
}
function handleWordCompletion(e) {
  const activeLang = document.querySelector('.lang-option.active')?.dataset.lang;
  const focusedElement = document.activeElement;
  const isInEditor = focusedElement?.tagName === 'TEXTAREA' || 
                    focusedElement?.classList.contains('CodeMirror') ||
                    (focusedElement?.closest('.CodeMirror') && focusedElement?.closest('.cm-content'));
  const isWordEnd = e.key === ' ' || e.key === '.' || e.key === ',' || e.key === ';' || 
                   e.key === ':' || e.key === '!' || e.key === '?' || e.key === 'Enter';
  if (isInEditor && activeLang && activeLang !== 'en' && isWordEnd) {
    setTimeout(() => {
      checkAndFixLastWord(activeLang, focusedElement);
    }, 10);
  }
}
function checkAndFixLastWord(lang, element) {
  let currentText, selectionStart, selectionEnd, setTextFunction;
  if (element.tagName === 'TEXTAREA') {
    currentText = element.value;
    selectionStart = element.selectionStart;
    selectionEnd = element.selectionEnd;
    setTextFunction = (text) => { element.value = text; };
  } else {
    const cmEditor = element.CodeMirror || window.cmEditor || element.closest('.CodeMirror')?.CodeMirror;
    if (cmEditor) {
      currentText = cmEditor.getValue();
      const cursor = cmEditor.getCursor();
      selectionStart = cmEditor.indexFromPos(cursor);
      selectionEnd = selectionStart;
      setTextFunction = (text) => { 
        cmEditor.setValue(text); 
        cmEditor.setCursor(cursor); 
      };
    } else {
      return;
    }
  }
  if (selectionStart > 0) {
    let wordStart = selectionStart - 1;
    const wordDelimiters = [' ', '.', ',', ';', ':', '!', '?', '\n', '\t', '(', ')', '[', ']', '{', '}'];
    while (wordStart > 0 && !wordDelimiters.includes(currentText[wordStart - 1])) {
      wordStart--;
    }
    const lastWord = currentText.substring(wordStart, selectionStart);
    if (lastWord.length > 0) {
      const needsTransliteration = /^[a-zA-Z]+$/.test(lastWord) && lastWord.length > 1;
      if (needsTransliteration) {
        const transliteratedWord = transliterateWord(lastWord, lang);
        if (transliteratedWord !== lastWord) {
          const newText = currentText.substring(0, wordStart) + 
                         transliteratedWord + 
                         currentText.substring(selectionStart);
          setTextFunction(newText);
          const newCursorPos = wordStart + transliteratedWord.length;
          if (element.tagName === 'TEXTAREA') {
            element.selectionStart = element.selectionEnd = newCursorPos;
          } else {
            const cmEditor = element.CodeMirror || window.cmEditor;
            if (cmEditor) {
              const newPos = cmEditor.posFromIndex(newCursorPos);
              cmEditor.setCursor(newPos);
            }
          }
        }
      }
    }
  }
}
function transliterateWord(word, lang) {
  const translitTables = {
    'ru': {
      'q': 'й', 'w': 'ц', 'e': 'у', 'r': 'к', 't': 'е', 'y': 'н', 'u': 'г', 'i': 'ш', 'o': 'щ', 'p': 'з', 
      '[': 'х', ']': 'ъ',
      'a': 'ф', 's': 'ы', 'd': 'в', 'f': 'а', 'g': 'п', 'h': 'р', 'j': 'о', 'k': 'л', 'l': 'д', ';': 'ж', 
      "'": 'э', "''": 'э',
      'z': 'я', 'x': 'ч', 'c': 'с', 'v': 'м', 'b': 'и', 'n': 'т', 'm': 'ь', ',': 'б', '.': 'ю', '/': '.',
      'Q': 'Й', 'W': 'Ц', 'E': 'У', 'R': 'К', 'T': 'Е', 'Y': 'Н', 'U': 'Г', 'I': 'Ш', 'O': 'Щ', 'P': 'З',
      '{': 'Х', '}': 'Ъ',
      'A': 'Ф', 'S': 'Ы', 'D': 'В', 'F': 'А', 'G': 'П', 'H': 'Р', 'J': 'О', 'K': 'Л', 'L': 'Д', ':': 'Ж',
      '"': 'Э','""': 'Э',
      'Z': 'Я', 'X': 'Ч', 'C': 'С', 'V': 'М', 'B': 'И', 'N': 'Т', 'M': 'Ь', '<': 'Б', '>': 'Ю', '?': ','
    },
    'uk': {
      'q': 'й', 'w': 'ц', 'e': 'у', 'r': 'к', 't': 'е', 'y': 'н', 'u': 'г', 'i': 'ш', 'o': 'щ', 'p': 'з', 
      '[': 'х', ']': 'ї',
      'a': 'ф', 's': 'і', 'd': 'в', 'f': 'а', 'g': 'п', 'h': 'р', 'j': 'о', 'k': 'л', 'l': 'д', ';': 'ж', 
      "'": 'є',"''": 'є',
      'z': 'я', 'x': 'ч', 'c': 'с', 'v': 'м', 'b': 'и', 'n': 'т', 'm': 'ь', ',': 'б', '.': 'ю', '/': '.',
      'Q': 'Й', 'W': 'Ц', 'E': 'У', 'R': 'К', 'T': 'Е', 'Y': 'Н', 'U': 'Г', 'I': 'Ш', 'O': 'Щ', 'P': 'З',
      '{': 'Х', '}': 'Ї',
      'A': 'Ф', 'S': 'І', 'D': 'В', 'F': 'А', 'G': 'П', 'H': 'Р', 'J': 'О', 'K': 'Л', 'L': 'Д', ':': 'Ж',
      '"': 'Є','""': 'Є',
      'Z': 'Я', 'X': 'Ч', 'C': 'С', 'V': 'М', 'B': 'И', 'N': 'Т', 'M': 'Ь', '<': 'Б', '>': 'Ю', '?': ','
    }
  };
  const table = translitTables[lang];
  if (!table) return word;
  let result = '';
  for (let i = 0; i < word.length; i++) {
    const char = word[i];
    result += table[char] || char;
  }
  return result;
}
function applyTransliterationToAll(lang) {
  const focusedElement = document.activeElement;
  const isInEditor = focusedElement?.tagName === 'TEXTAREA' || 
                    focusedElement?.classList.contains('CodeMirror') ||
                    (focusedElement?.closest('.CodeMirror') && focusedElement?.closest('.cm-content'));
  if (isInEditor && lang && lang !== 'en') {
    let currentText, setTextFunction;
    if (focusedElement.tagName === 'TEXTAREA') {
      currentText = focusedElement.value;
      setTextFunction = (text) => { focusedElement.value = text; };
    } else {
      const cmEditor = focusedElement.CodeMirror || window.cmEditor || focusedElement.closest('.CodeMirror')?.CodeMirror;
      if (cmEditor) {
        currentText = cmEditor.getValue();
        setTextFunction = (text) => { cmEditor.setValue(text); };
      } else {
        return;
      }
    }
    const words = currentText.split(/(\s+)/);
    const transliteratedWords = words.map(word => {
      if (/^[a-zA-Z]+$/.test(word) && word.length > 1) {
        return transliterateWord(word, lang);
      }
      return word;
    });
    const newText = transliteratedWords.join('');
    if (newText !== currentText) {
      setTextFunction(newText);
    }
  }
}
function transliterateLastChar(lang, element) {
  const translitTables = {
    'ru': {
      'q': 'й', 'w': 'ц', 'e': 'у', 'r': 'к', 't': 'е', 'y': 'н', 'u': 'г', 'i': 'ш', 'o': 'щ', 'p': 'з', 
      '[': 'х', ']': 'ъ',
      'a': 'ф', 's': 'ы', 'd': 'в', 'f': 'а', 'g': 'п', 'h': 'р', 'j': 'о', 'k': 'л', 'l': 'д', ';': 'ж', 
      "'": 'э', "''": 'э',
      'z': 'я', 'x': 'ч', 'c': 'с', 'v': 'м', 'b': 'и', 'n': 'т', 'm': 'ь', ',': 'б', '.': 'ю', '/': '.',
      'Q': 'Й', 'W': 'Ц', 'E': 'У', 'R': 'К', 'T': 'Е', 'Y': 'Н', 'U': 'Г', 'I': 'Ш', 'O': 'Щ', 'P': 'З',
      '{': 'Х', '}': 'Ъ',
      'A': 'Ф', 'S': 'Ы', 'D': 'В', 'F': 'А', 'G': 'П', 'H': 'Р', 'J': 'О', 'K': 'Л', 'L': 'Д', ':': 'Ж',
      '"': 'Э', '""': 'Э',
      'Z': 'Я', 'X': 'Ч', 'C': 'С', 'V': 'М', 'B': 'И', 'N': 'Т', 'M': 'Ь', '<': 'Б', '>': 'Ю', '?': ','
    },
    'uk': {
      'q': 'й', 'w': 'ц', 'e': 'у', 'r': 'к', 't': 'е', 'y': 'н', 'u': 'г', 'i': 'ш', 'o': 'щ', 'p': 'з', 
      '[': 'х', ']': 'ї',
      'a': 'ф', 's': 'і', 'd': 'в', 'f': 'а', 'g': 'п', 'h': 'р', 'j': 'о', 'k': 'л', 'l': 'д', ';': 'ж', 
      "'": 'є', "''": 'є',
      'z': 'я', 'x': 'ч', 'c': 'с', 'v': 'м', 'b': 'и', 'n': 'т', 'm': 'ь', ',': 'б', '.': 'ю', '/': '.',
      'Q': 'Й', 'W': 'Ц', 'E': 'У', 'R': 'К', 'T': 'Е', 'Y': 'Н', 'U': 'Г', 'I': 'Ш', 'O': 'Щ', 'P': 'З',
      '{': 'Х', '}': 'Ї',
      'A': 'Ф', 'S': 'І', 'D': 'В', 'F': 'А', 'G': 'П', 'H': 'Р', 'J': 'О', 'K': 'Л', 'L': 'Д', ':': 'Ж',
      '"': 'Є', '""': 'Є',
      'Z': 'Я', 'X': 'Ч', 'C': 'С', 'V': 'М', 'B': 'И', 'N': 'Т', 'M': 'Ь', '<': 'Б', '>': 'Ю', '?': ','
    }
  };
  const table = translitTables[lang];
  if (!table) return;
  let currentText, selectionStart, selectionEnd, setTextFunction;
  if (element.tagName === 'TEXTAREA') {
    currentText = element.value;
    selectionStart = element.selectionStart;
    selectionEnd = element.selectionEnd;
    setTextFunction = (text) => { element.value = text; };
  } else {
    const cmEditor = element.CodeMirror || window.cmEditor || element.closest('.CodeMirror')?.CodeMirror;
    if (cmEditor) {
      currentText = cmEditor.getValue();
      const cursor = cmEditor.getCursor();
      selectionStart = cmEditor.indexFromPos(cursor);
      selectionEnd = selectionStart;
      setTextFunction = (text) => { cmEditor.setValue(text); cmEditor.setCursor(cursor); };
    } else {
      return;
    }
  }
  if (selectionStart > 0) {
    const lastChar = currentText[selectionStart - 1];
    const transliterated = table[lastChar];
    if (transliterated && transliterated !== lastChar) {
      const newText = currentText.substring(0, selectionStart - 1) + 
                     transliterated + 
                     currentText.substring(selectionStart);
      setTextFunction(newText);
      if (element.tagName === 'TEXTAREA') {
        element.selectionStart = element.selectionEnd = selectionStart - 1 + transliterated.length;
      } else {
        const cmEditor = element.CodeMirror || window.cmEditor;
        if (cmEditor) {
          const newPos = cmEditor.posFromIndex(selectionStart - 1 + transliterated.length);
          cmEditor.setCursor(newPos);
        }
      }
    }
  }
}
function toggleCodeActionsMenu(e) {
    e.stopPropagation();
    const menu = document.getElementById('code-actions-menu');
    if (!menu) return;
    
    // Check if current file supports compilation
    const viewer = document.getElementById('viewer');
    const path = viewer?.dataset?.path;
    const compileMenuItem = document.getElementById('code-action-compile');
    if (compileMenuItem && path) {
        const ext = path.split('.').pop().toLowerCase();
        const supportedLangs = ['java', 'c', 'cpp', 'py', 'm'];
        compileMenuItem.style.display = supportedLangs.includes(ext) ? 'flex' : 'none';
    }
    
    const isHidden = menu.classList.contains('hidden');
    closeAllDropdowns(); // First close all
    if (isHidden) {
        if (menu.parentElement !== document.body) {
            document.body.appendChild(menu);
        }
        menu.classList.remove('hidden');
        adjustDropdownPosition();
    }
}
document.querySelectorAll('#code-actions-menu .dropdown-item').forEach(item => {
    item.addEventListener('click', () => {
        closeAllDropdowns();
    });
});

const CODE_ACTION_ICONS = {
    compactCurrentCode: `<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0"><polyline points="4 14 10 14 10 20"/><polyline points="20 10 14 10 14 4"/><line x1="14" y1="10" x2="21" y2="3"/><line x1="3" y1="21" x2="10" y2="14"/></svg>`,
    formatCurrentCode: `<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0"><polyline points="4 10 10 10 10 4"/><polyline points="20 14 14 14 14 20"/><line x1="10" y1="10" x2="3" y2="3"/><line x1="21" y1="21" x2="14" y2="14"/></svg>`,
    compileAndRun: `<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0"><polygon points="5 3 19 12 5 21 5 3"/></svg>`,
    removeCommentsFromCode: `<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>`
};

function initCodeActionsMenuIcons() {
    const menu = document.getElementById('code-actions-menu');
    if (!menu) return;

    menu.querySelectorAll('.dropdown-item').forEach(item => {
        // Determine which action this item triggers
        const onclickAttr = item.getAttribute('onclick') || '';
        let iconSvg = null;

        if (onclickAttr.includes('compactCurrentCode')) {
            iconSvg = CODE_ACTION_ICONS.compactCurrentCode;
        } else if (onclickAttr.includes('formatCurrentCode')) {
            iconSvg = CODE_ACTION_ICONS.formatCurrentCode;
        } else if (onclickAttr.includes('compileAndRun')) {
            iconSvg = CODE_ACTION_ICONS.compileAndRun;
        } else if (onclickAttr.includes('removeCommentsFromCode')) {
            iconSvg = CODE_ACTION_ICONS.removeCommentsFromCode;
        }

        if (iconSvg && !item.querySelector('svg')) {
            const existingText = item.textContent.trim();
            item.innerHTML = iconSvg + '<span class="menu-item-label">' + existingText + '</span>';
            item.style.display = 'flex';
            item.style.alignItems = 'center';
            item.style.gap = '8px';
        }
    });
}
function adjustDropdownPosition() {
    const dropdown = document.getElementById('code-actions-menu');
    const btn = document.getElementById('btn-code-actions');
    if (!dropdown || !btn || dropdown.classList.contains('hidden')) return;
    const rect = btn.getBoundingClientRect();
    const dropdownHeight = dropdown.offsetHeight;
    const dropdownWidth = dropdown.offsetWidth;
    const viewportHeight = window.innerHeight;
    const viewportWidth = window.innerWidth;
    const gap = 4;
    const padding = 8;
    const sideToolbar = !!btn.closest('#file-view.fullscreen-mode .file-toolbar') && window.matchMedia('(min-width: 769px)').matches;
    let left = sideToolbar && rect.right + gap + dropdownWidth <= viewportWidth - padding
        ? rect.right + gap
        : rect.right - dropdownWidth;
    left = Math.max(padding, Math.min(left, viewportWidth - dropdownWidth - padding));
    let top = sideToolbar
        ? rect.top
        : rect.bottom + gap;
    if (!sideToolbar && top + dropdownHeight > viewportHeight - padding) {
        top = rect.top - dropdownHeight - gap;
    }
    top = Math.max(padding, Math.min(top, viewportHeight - dropdownHeight - padding));
    dropdown.style.position = 'fixed';
    dropdown.style.left = `${left}px`;
    dropdown.style.right = 'auto';
    dropdown.style.top = `${top}px`;
    dropdown.style.bottom = 'auto';
    dropdown.style.marginTop = '0';
    dropdown.style.marginBottom = '0';
}
window.addEventListener('resize', () => {
    const dropdown = document.getElementById('code-actions-menu');
    if (dropdown && !dropdown.classList.contains('hidden')) {
        adjustDropdownPosition();
    }
});
window.addEventListener('scroll', () => {
    const dropdown = document.getElementById('code-actions-menu');
    if (dropdown && !dropdown.classList.contains('hidden')) {
        adjustDropdownPosition();
    }
}, true);
async function compactCurrentCode() {
    const activeTabId = tabsState.activeTabId;
    if (!activeTabId) {
        flashStatus('No file to compact');
        return;
    }
    const activeTab = tabsState.tabs.find(tab => tab.id === activeTabId);
    if (!activeTab) {
        flashStatus('No file to compact');
        return;
    }
    const path = activeTab.path;
    if (!path) {
        flashStatus('No file to compact');
        return;
    }
    const content = getCurrentEditorContent(activeTabId);
    if (!content) {
        flashStatus('No content to compact');
        return;
    }
    const ext = path.split('.').pop().toLowerCase();
    const languageMap = {
        'js': 'php', 'php': 'php', 'html': 'html', 'htm': 'html',
        'c': 'c', 'cpp': 'c', 'h': 'c', 'py': 'python',
        'ps1': 'powershell', 'css': 'css', 'sql': 'sql'
    };
    const language = languageMap[ext] || 'php';
    try {
        const response = await fetch('de_format_code.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: new URLSearchParams({
                action: 'compact',
                code: content,
                language: language
            })
        });
        const data = await response.json();
        if (data.success) {
            showCodeModal('Compact Code', data.compacted_code || data.formatted_code, content, null);
        } else {
            flashStatus('Compaction failed: ' + data.error);
        }
    } catch (err) {
        flashStatus('Compaction error: ' + err.message);
    }
}
async function formatCurrentCode() {
    const activeTabId = tabsState.activeTabId;
    if (!activeTabId) {
        flashStatus('No file to format');
        return;
    }
    const activeTab = tabsState.tabs.find(tab => tab.id === activeTabId);
    if (!activeTab) {
        flashStatus('No file to format');
        return;
    }
    const path = activeTab.path;
    if (!path) {
        flashStatus('No file to format');
        return;
    }
    const content = getCurrentEditorContent(activeTabId);
    if (!content) {
        flashStatus('No content to format');
        return;
    }
    const ext = path.split('.').pop().toLowerCase();
    const languageMap = {
        'js': 'php', 'php': 'php', 'html': 'html', 'htm': 'html',
        'c': 'c', 'cpp': 'c', 'h': 'c', 'py': 'python',
        'ps1': 'powershell', 'css': 'css', 'sql': 'sql'
    };
    const language = languageMap[ext] || 'php';
    try {
        const response = await fetch('format_code.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: new URLSearchParams({
                action: 'format',
                code: content,
                language: language
            })
        });
        const data = await response.json();
        if (data.success) {
            showCodeModal('Compact Code', data.compacted_code || data.formatted_code, content);
        } else {
            flashStatus('Formatting failed: ' + data.error);
        }
    } catch (err) {
        flashStatus('Formatting error: ' + err.message);
    }
}
function getCurrentEditorContent(tabId) {
    const viewer = document.getElementById('viewer');

    if (editorsMap.has(tabId)) {
        return editorsMap.get(tabId).getValue();
    }

    if (window.monacoManager) {
        const monacoContent = window.monacoManager.getContent(tabId);
        if (monacoContent !== '') {
            return monacoContent;
        }
    }

    const textarea = viewer.querySelector(`.file-content-container[data-tab-id="${tabId}"] textarea`);
    if (textarea) {
        return textarea.value;
    }

    return '';
}
function getEditorContent() {
    const activeTabId = tabsState.activeTabId;
    if (!activeTabId) return '';
    const activeTab = tabsState.tabs.find(tab => tab.id === activeTabId);
    if (!activeTab) return '';
    
    // Check Monaco editor first
    if (editorsMap.has(activeTabId)) {
        const editor = editorsMap.get(activeTabId);
        return editor.getValue();
    }
    
    const textarea = viewer.querySelector(`.file-content-container[data-tab-id="${activeTabId}"] textarea`);
    if (textarea) {
        return textarea.value;
    }
    
    return activeTab.content || '';
}

/**
 * Show a confirmation modal dialog (replacement for browser confirm())
 * @param {string} title - Modal title
 * @param {string} message - Confirmation message
 * @returns {Promise<{confirmed: boolean}>} - Promise resolving with confirmed status
 */
function showConfirmModal(title, message) {
    return new Promise((resolve) => {
        const modal = document.createElement('div');
        modal.className = 'modal';
        modal.style.display = 'flex';
        modal.innerHTML = `
            <div class="modal-content" style="max-width: 450px;">
                <div class="modal-header">
                    <h3>${title}</h3>
                    <button class="icon-btn close-btn" aria-label="${escapeHtml(t(i18n, 'close') || 'Close')}">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                    </button>
                </div>
                <div class="modal-body">
                    <p style="margin: 20px 0;">${message}</p>
                </div>
                <div class="modal-footer">
                    <button class="btn secondary btn-cancel-confirm">${t(i18n, 'cancel')}</button>
                    <button class="btn primary btn-confirm-action">${t(i18n, 'yes')}</button>
                </div>
            </div>
        `;
        document.body.appendChild(modal);

        const confirmBtn = modal.querySelector('.btn-confirm-action');
        const cancelBtn = modal.querySelector('.btn-cancel-confirm');
        const closeBtn = modal.querySelector('.close-btn');

        const cleanup = () => {
            modal.remove();
        };

        confirmBtn.addEventListener('click', () => {
            cleanup();
            resolve({ confirmed: true });
        });

        cancelBtn.addEventListener('click', () => {
            cleanup();
            resolve({ confirmed: false });
        });

        closeBtn.addEventListener('click', () => {
            cleanup();
            resolve({ confirmed: false });
        });

        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                cleanup();
                resolve({ confirmed: false });
            }
        });
    });
}

function showCodeModal(title, newContent, originalContent, stats = null) {
    const modal = document.getElementById('code-modal');
    const titleElement = document.getElementById('code-modal-title');
    const modalBody = modal.querySelector('.modal-body');
    titleElement.textContent = title;
    const existingStats = modalBody.querySelector('.code-stats');
    if (existingStats) {
        existingStats.remove();
    }
    if (stats && title.includes(t(i18n, 'codeWithoutComments'))) {
        const statsElement = document.createElement('div');
        statsElement.className = 'code-stats';
        statsElement.style.cssText = `
            margin-bottom: 10px;
            padding: 8px;
            background: var(--panel);
            border-radius: 4px;
            font-size: 12px;
            color: var(--muted);
        `;
        const removedChars = stats.removed_chars !== undefined ? stats.removed_chars : 0;
        const originalLength = stats.original_length !== undefined ? stats.original_length : originalContent.length;
        const cleanedLength = stats.cleaned_length !== undefined ? stats.cleaned_length : newContent.length;
        statsElement.innerHTML = `
            Removed ${removedChars} characters 
            (${originalLength} → ${cleanedLength})
        `;
        modalBody.insertBefore(statsElement, modalBody.firstChild);
    }
    const editorContainer = document.getElementById('code-modal-editor');
    const activeTabId = tabsState.activeTabId;
    
    // Use Monaco editor for code modal
    editorContainer.innerHTML = '';
    const editorDiv = document.createElement('div');
    editorDiv.style.cssText = `
        width: 100%;
        min-height: 400px;
        max-height: 70vh;
    `;
    editorContainer.appendChild(editorDiv);
    
    if (window.monacoManager) {
        window.monacoManager.createEditor(editorDiv, newContent, activeTabId ? (tabsState.tabs.find(t => t.id === activeTabId)?.path || 'code.txt') : 'code.txt', 'code-modal', false)
            .then(editor => {
                window.codeModalEditor = editor;
                editor.focus();
            })
            .catch(err => {
                console.error('Failed to create Monaco editor:', err);
                editorDiv.innerHTML = '<p style="color: var(--muted);">Failed to load editor</p>';
            });
    } else {
        editorContainer.innerHTML = '<p style="color: var(--muted);">Monaco editor not available</p>';
    }
    
    modal.dataset.originalContent = originalContent;
    modal.dataset.newContent = newContent;
    modal.classList.remove('hidden');
    setTimeout(() => {
        if (window.codeModalEditor) {
            window.codeModalEditor.layout();
            window.codeModalEditor.focus();
        }
    }, 200);
}
function closeCodeModal() {
  const modal = document.getElementById('code-modal');
  const modalBody = modal.querySelector('.modal-body');
  const existingStats = modalBody.querySelector('.code-stats');
  if (existingStats) {
    existingStats.remove();
  }
  modal.classList.add('hidden');
}
function applyCodeChanges() {
    const modal = document.getElementById('code-modal');
    const newContent = window.codeModalEditor ? window.codeModalEditor.getValue() : modal.dataset.newContent;
    if (!newContent) {
        flashStatus('No content to apply');
        return;
    }
    const activeTabId = tabsState.activeTabId;
    if (!activeTabId) {
        flashStatus('No active tab');
        return;
    }
    const activeTab = tabsState.tabs.find(tab => tab.id === activeTabId);
    if (!activeTab) {
        flashStatus('Active tab not found');
        return;
    }
    const viewer = document.getElementById('viewer');
    let editorUpdated = false;
    if (typeof editorsMap !== 'undefined' && editorsMap.has(activeTabId)) {
        const editor = editorsMap.get(activeTabId);
        editor.setValue(newContent);
        editorUpdated = true;
    }
    else {
        const editorContainer = viewer.querySelector(`.file-content-container[data-tab-id="${activeTabId}"] .CodeMirror`);
        if (editorContainer && editorContainer.CodeMirror) {
            editorContainer.CodeMirror.setValue(newContent);
            editorUpdated = true;
        }
        else {
            const textarea = viewer.querySelector(`.file-content-container[data-tab-id="${activeTabId}"] textarea`);
            if (textarea) {
                textarea.value = newContent;
                textarea.dispatchEvent(new Event('input'));
                editorUpdated = true;
            }
            else if (viewer.cmEditor && viewer.dataset.path === activeTab.path) {
                viewer.cmEditor.setValue(newContent);
                editorUpdated = true;
            }
        }
    }
    if (editorUpdated) {
        markTabAsModified(activeTabId, true);
        flashStatus('Content updated successfully');
        closeCodeModal();
    } else {
        flashStatus('Could not find editor for current tab');
    }
}
function initSidebarResizer() {
    const sidebar = document.querySelector('.sidebar');
    if (!sidebar) return;
    let resizer = sidebar.querySelector('.sidebar-resizer');
    if (!resizer) {
        resizer = document.createElement('div');
        resizer.className = 'sidebar-resizer';
        sidebar.appendChild(resizer);
    }
    resizer.addEventListener('dblclick', resetSidebarWidth);
    function resetSidebarWidth() {
        sidebar.style.width = '';
        localStorage.removeItem('sidebarWidth');
    }
    let isResizing = false;
    let startX = 0;
    let startWidth = 0;
    function duringResize(e) {
        if (!isResizing) return;
        const deltaX = e.clientX - startX;
        const newWidth = startWidth + deltaX;
        const minWidth = 200;
        const maxWidth = window.innerWidth * 0.7;
        if (newWidth >= minWidth && newWidth <= maxWidth) sidebar.style.width = newWidth + 'px';
    }
    function stopResize() {
        if (!isResizing) return;
        isResizing = false;
        resizer.classList.remove('active');
        const currentWidth = parseInt(document.defaultView.getComputedStyle(sidebar).width, 10);
        localStorage.setItem('sidebarWidth', currentWidth);
    }
    function startResize(e) {
        if (e.button !== 0) return;
        isResizing = true;
        startX = e.clientX;
        startWidth = parseInt(document.defaultView.getComputedStyle(sidebar).width, 10);
        resizer.classList.add('active');
        document.body.style.userSelect = 'none';
        cloudPointerDragStart('col-resize', duringResize, stopResize);
        e.preventDefault();
        e.stopPropagation();
    }
    resizer.addEventListener('pointerdown', startResize);
}

async function compileAndRun() {
  // Block compilation for level 0 users
  const _compileRole = await getUserRole();
  if (_compileRole === 0) {
    flashStatus(t(i18n, 'noPermission') || 'No permission to compile');
    return;
  }
  const viewer = document.getElementById('viewer');
  const path = viewer.dataset.path;
  if (!path) {
    flashStatus(t(i18n, 'noFileToCompile'));
    return;
  }
  const content = getEditorContent();
  if (!content) {
    flashStatus(t(i18n, 'noContentToCompile'));
    return;
  }
  const ext = path.split('.').pop().toLowerCase();
  const supportedLangs = ['java', 'c', 'cpp', 'py', 'm'];
  if (!supportedLangs.includes(ext)) {
    flashStatus(t(i18n, 'languageNotSupported', {ext}));
    return;
  }
  showCompileTerminal(path, content, ext);
}
async function refreshTreeAfterOperation() {
    await invalidateTreeCache();
    await buildTree('');
}
function showCompileTerminal(filename, content, language) {
  const modal = document.getElementById('compile-modal');
  const terminal = document.getElementById('compile-terminal');
  
  // Reset timer
  resetCompileTimer();
  compileFloatingState.minimized = false;
  hideFloatingCompileButton();
  
  terminal._copyButton = null;
  terminal.innerHTML = '';
  terminal.style.cssText = `
    width: 100%;
    height: 400px;
    background: ${document.body.getAttribute('data-theme') === 'dark' ? '#4b4b4bff' : '#ccccccff'};
    color: var(--fg);
    font-family: 'Courier New', monospace;
    padding: 15px;
    overflow-x: hidden;
    overflow-y: auto;
    white-space: pre-wrap;
    word-wrap: break-word;
    word-break: break-word;
    border-radius: 4px;
    margin-bottom: 10px;
    line-height: 1.4;
    font-size: 14px;
    position: relative;
  `;
  
  addCopyButtonToTerminal(terminal);
  addTerminalLine(terminal, `$ ${t(i18n, 'compilingFile', {filename})}`);
  addTerminalLine(terminal, `$ ${t(i18n, 'language')}: ${language}`);
  addTerminalLine(terminal, `$ ${t(i18n, 'enterProgramInput')}`);
  addTerminalLine(terminal, '');
  
  const inputContainer = document.querySelector('.compile-input');
  inputContainer.classList.remove('hidden');
  
  const input = document.getElementById('compile-input-text');
  input.value = '';
  
  // Update the placeholder with the translation
  if (window.i18n && window.i18n.enterProgramInput) {
    input.placeholder = window.i18n.enterProgramInput;
  }
  
  const sendButton = document.getElementById('btn-compile-send');
  
  // Update the button text with the translation
  if (window.i18n && window.i18n.compileAndRun) {
    sendButton.textContent = window.i18n.compileAndRun;
  }
  
  // Remove the old handler, if any, and add a new one
  sendButton.replaceWith(sendButton.cloneNode(true));
  const newSendButton = document.getElementById('btn-compile-send');
  
  newSendButton.onclick = () => {
    startCompileTimer();
    performCompilation(filename, content, language, terminal, input);
  };
  
  modal.classList.remove('hidden');
  updateCompileFloatingButtonState();
  
  setTimeout(() => {
    input.focus();
  }, 100);
}
function addCopyButtonToTerminal(terminal) {
  if (terminal._copyButton && terminal._copyButton.parentNode) {
    terminal._copyButton.remove();
  }
  const copyButton = document.createElement('button');
  copyButton.className = 'btn secondary small';
  copyButton.style.cssText = `
    position: absolute;
    top: 10px;
    right: 10px;
    z-index: 10;
    padding: 4px 8px;
    font-size: 12px;
    display: flex;
    align-items: center;
    gap: 4px;
    background: var(--panel);
    border: 1px solid var(--border);
    border-radius: 4px;
    cursor: pointer;
  `;
  copyButton.innerHTML = `
    <svg width="14" height="14" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round">
      <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
      <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
    </svg>
    <span>${t(i18n, 'copy') || 'Copy'}</span>
  `;
  copyButton.addEventListener('click', async () => {
    const allLines = Array.from(terminal.querySelectorAll('div'));
    let lastReadyIndex = -1;
    for (let i = allLines.length - 1; i >= 0; i--) {
      const lineText = allLines[i].textContent || allLines[i].innerText;
      if (lineText.includes('🔄') && lineText.includes('Ready for next compilation')) {
        lastReadyIndex = i;
        break;
      }
    }
    // - If there is a "🔄 Ready", copy all lines up to it (excluding)
    let linesToCopy;
    if (lastReadyIndex > 0) {
      linesToCopy = allLines.slice(4, lastReadyIndex);
    } else {
      linesToCopy = allLines.slice(4);
    }
    const terminalContent = linesToCopy
      .map(line => line.textContent || line.innerText)
      .filter(text => text.trim() !== '')
      .join('\n');
    const originalHTML = copyButton.innerHTML;
    try {
      if (navigator.clipboard && window.isSecureContext) {
        await navigator.clipboard.writeText(terminalContent);
      } else {
        const textArea = document.createElement('textarea');
        textArea.value = terminalContent;
        textArea.style.position = 'fixed';
        textArea.style.top = '0';
        textArea.style.left = '0';
        textArea.style.width = '2em';
        textArea.style.height = '2em';
        textArea.style.padding = '0';
        textArea.style.border = 'none';
        textArea.style.outline = 'none';
        textArea.style.boxShadow = 'none';
        textArea.style.background = 'transparent';
        textArea.style.opacity = '0';
        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();
        const successful = document.execCommand('copy');
        document.body.removeChild(textArea);
        if (!successful) {
          throw new Error('Fallback copy method failed');
        }
      }
      copyButton.innerHTML = `
        <svg width="14" height="14" viewBox="0 0 24 24" stroke="#00ff00" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round">
          <path d="M20 6L9 17l-5-5"></path>
        </svg>
        <span>${t(i18n, 'copied') || 'Copied!'}</span>
      `;
      copyButton.style.color = '#00ff00';
      setTimeout(() => {
        copyButton.innerHTML = originalHTML;
        copyButton.style.color = '';
      }, 2000);
    } catch (err) {
      console.error('Copy failed:', err);
      flashStatus(t(i18n, 'copyFailed') || 'Failed to copy');
    }
  });
  terminal.style.position = 'relative';
  terminal.appendChild(copyButton);
  terminal._copyButton = copyButton;
}
function addTerminalLine(terminal, text, className = '') {
  const line = document.createElement('div');
  line.textContent = text;
  if (className) {
    line.className = className;
  }
  terminal.appendChild(line);
  terminal.scrollTop = terminal.scrollHeight;
}
async function performCompilation(filename, content, language, terminal, input) {
  const programInput = input.value.trim();
  const copyButton = terminal._copyButton;
  const lines = terminal.querySelectorAll('div');
  
  for (let i = 4; i < lines.length; i++) {
    lines[i].remove();
  }
  
  if (copyButton && !terminal.contains(copyButton)) {
    terminal.appendChild(copyButton);
  }
  
  addTerminalLine(terminal, `$ ${t(i18n, 'compilingFile', {filename})}`);
  
  const loadingLine = document.createElement('div');
  loadingLine.textContent = `⏳ ${t(i18n, 'compiling')}`;
  loadingLine.className = 'compile-warning';
  terminal.appendChild(loadingLine);
  terminal.scrollTop = terminal.scrollHeight;
  
  try {
    const activeTab = tabsState.tabs.find(tab => tab.id === tabsState.activeTabId) || null;
    const compileRoot = activeTab ? getResolvedTabRoot(activeTab) : getActiveTabRoot();
    const sourcePath = String(activeTab?.path || filename || '').replace(/^\/+/, '');
    const sourceDir = sourcePath.includes('/') ? sourcePath.slice(0, sourcePath.lastIndexOf('/')) : '';
    let currentUser = currentUserState.user || 'default';
    currentUser = currentUser.replace(/\s+/g, '_');
    const fullServerPath = buildUserStoragePath(currentUser, sourcePath || filename, compileRoot);
    
    const response = await fetch('compile.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/x-www-form-urlencoded'},
      body: new URLSearchParams({
        action: 'compile_run',
        file_path: fullServerPath,
        root: compileRoot,
        file_content: content,
        language: language,
        program_input: programInput
      })
    });
    
    loadingLine.remove();
    
    // Stop the timer when the response arrives
    stopCompileTimer();
    
    const data = await response.json();
    
    if (data.fileChanges && data.fileChanges.length > 0) {
      await applyCompileFileChangesToTree(data.fileChanges, { root: compileRoot, sourcePath });
      
      // Split changes into files and folders
      const createdFiles = data.fileChanges.filter(change => change.action === 'created' && change.type === 'file');
      const createdFolders = data.fileChanges.filter(change => change.action === 'created' && change.type === 'dir');
      const deletedFiles = data.fileChanges.filter(change => change.action === 'deleted' && change.type === 'file');
      const deletedFolders = data.fileChanges.filter(change => change.action === 'deleted' && change.type === 'dir');
      
      // Display created folders
      if (createdFolders.length > 0) {
        const folderNames = createdFolders.map(f => f.file).join(', ');
        addTerminalLine(terminal, `[DIR] ${t(i18n, 'foldersCreated')}: ${folderNames}`, 'compile-success');
        
        // Detailed output for each folder
        createdFolders.forEach(folder => {
          addTerminalLine(terminal, `   [DIR] Created folder: ${folder.fullPath}`, 'compile-success');
        });
      }
      
      // Display created files
      if (createdFiles.length > 0) {
        const fileNames = createdFiles.map(f => f.file).join(', ');
        addTerminalLine(terminal, `[FILE] ${t(i18n, 'filesCreated')}: ${fileNames}`, 'compile-success');
        
        // Detailed output for each file
        createdFiles.forEach(file => {
          addTerminalLine(terminal, `   [FILE] Created file: ${file.fullPath}`, 'compile-success');
        });
      }
      
      // Display deleted folders
      if (deletedFolders.length > 0) {
        const folderNames = deletedFolders.map(f => f.file).join(', ');
        addTerminalLine(terminal, `🗑️ ${t(i18n, 'foldersDeleted')}: ${folderNames}`, 'compile-warning');
        
        // Detailed output for each deleted folder
        deletedFolders.forEach(folder => {
          addTerminalLine(terminal, `   🗑️ Deleted folder: ${folder.fullPath}`, 'compile-warning');
        });
      }
      
      // Display deleted files
      if (deletedFiles.length > 0) {
        const fileNames = deletedFiles.map(f => f.file).join(', ');
        addTerminalLine(terminal, `🗑️ ${t(i18n, 'filesDeleted')}: ${fileNames}`, 'compile-warning');
        
        // Detailed output for each deleted file
        deletedFiles.forEach(file => {
          addTerminalLine(terminal, `   🗑️ Deleted file: ${file.fullPath}`, 'compile-warning');
        });
      }
      
      // If there were changes, show overall statistics
      if (createdFiles.length > 0 || createdFolders.length > 0 || 
          deletedFiles.length > 0 || deletedFolders.length > 0) {
        addTerminalLine(terminal, `[SUMMARY] ${t(i18n, 'changesSummary')}:`, 'compile-success');
        addTerminalLine(terminal, `   +${createdFolders.length} ${t(i18n, 'folders')}, +${createdFiles.length} ${t(i18n, 'files')}, -${deletedFolders.length} ${t(i18n, 'folders')}, -${deletedFiles.length} ${t(i18n, 'files')}`, 'compile-success');
      }
    }

    if (data.success) {
      addTerminalLine(terminal, `✅ ${t(i18n, 'compilationSuccess')}`, 'compile-success');
      
      // Add execution time info
      const elapsedTime = compileTimerState.elapsedSeconds.toFixed(1);
      addTerminalLine(terminal, `⏱️ Execution time: ${elapsedTime}s`, 'compile-success');
      
      if (data.output && data.output.trim()) {
        addTerminalLine(terminal, `📤 ${t(i18n, 'programOutput')}`);
        const outputLines = data.output.split('\n');
        outputLines.forEach(line => {
          if (line.trim()) {
            addTerminalLine(terminal, line);
          }
        });
      } else {
        addTerminalLine(terminal, `ℹ️ ${t(i18n, 'noOutput')}`);
      }
      
      if (data.error && data.error.trim()) {
        addTerminalLine(terminal, `[INFO] ${t(i18n, 'additionalInfo')}`, 'compile-warning');
        addTerminalLine(terminal, data.error);
      }
      
      // Mark the timer as completed successfully
      finishCompileTimer();
    } else {
      addTerminalLine(terminal, `❌ ${t(i18n, 'compilationFailed')}`, 'compile-error');
      
      // Add time info even on error
      const elapsedTime = compileTimerState.elapsedSeconds.toFixed(1);
      addTerminalLine(terminal, `⏱️ Failed after: ${elapsedTime}s`, 'compile-warning');
      
      if (data.output && data.output.trim()) {
        addTerminalLine(terminal, `🔧 ${t(i18n, 'compilerOutput')}`);
        addTerminalLine(terminal, data.output);
      }
      
      if (data.error && data.error.trim()) {
        addTerminalLine(terminal, `🚨 ${t(i18n, 'errorDetails')}`);
        addTerminalLine(terminal, data.error, 'compile-error');
      } else {
        addTerminalLine(terminal, `🚨 ${t(i18n, 'noErrorInfo')}`);
      }
    }
  } catch (err) {
    loadingLine.remove();
    
    // Stop the timer on network error
    stopCompileTimer();
    
    const elapsedTime = compileTimerState.elapsedSeconds.toFixed(1);
    addTerminalLine(terminal, `💥 ${t(i18n, 'networkError')}${err.message}`, 'compile-error');
    addTerminalLine(terminal, `⏱️ Error after: ${elapsedTime}s`, 'compile-warning');
  }
  
  addTerminalLine(terminal, '');
  
  // Use the translation for the ready message
  const readyMessage = t(i18n, 'readyForCompilation') || 'Ready for next compilation';
  addTerminalLine(terminal, `🔄 ${readyMessage}`);
  
  if (copyButton && !terminal.contains(copyButton)) {
    terminal.appendChild(copyButton);
  }
  
  input.value = '';
  input.focus();
}
function closeCompileModal() {
  const modal = document.getElementById('compile-modal');
  if (modal) {
    modal.classList.add('hidden');
    hideFloatingCompileButton();
    resetCompileTimer(); // Reset the timer on close
  }
}

function closeAllDropdowns() {
    document.querySelectorAll('.dropdown-menu:not(.hidden)').forEach(menu => {
        menu.classList.add('hidden');
    });
    window.closeSettingsMenu?.();
    const sortFilterMenu = document.getElementById('sort-filter-menu');
    if (sortFilterMenu) {
        sortFilterMenu.classList.add('hidden');
    }
    const contextMenu = document.getElementById('context-menu');
    if (contextMenu) {
        contextMenu.remove();
    }
    const codeActionsMenu = document.getElementById('code-actions-menu');
    if (codeActionsMenu) {
        codeActionsMenu.classList.add('hidden');
    }
}
function showCompileOutput(output, error, filename) {
  const modal = document.getElementById('code-modal');
  const titleElement = document.getElementById('code-modal-title');
  titleElement.textContent = `Compile & Run: ${filename}`;
  const editorContainer = document.getElementById('code-modal-editor');
  editorContainer.innerHTML = '';
  const terminal = document.createElement('div');
  terminal.className = 'compile-terminal';
  terminal.style.cssText = `
    width: 100%;
    height: 300px;
    background: #1e1e1e;
    color: #00ff00;
    font-family: 'Courier New', monospace;
    padding: 15px;
    overflow-y: auto;
    white-space: pre-wrap;
    border-radius: 4px;
    margin-bottom: 10px;
  `;
  let terminalContent = `$ Compiling and running: ${filename}\n\n`;
  if (error) {
    terminalContent += `ERROR:\n${error}\n\n`;
  }
  if (output) {
    terminalContent += `OUTPUT:\n${output}\n`;
  }
  if (!error && !output) {
    terminalContent += `Program executed successfully (no output)\n`;
  }
  terminalContent += `\n$ Process completed.`;
  terminal.textContent = terminalContent;
  const inputContainer = document.createElement('div');
  inputContainer.className = 'compile-input';
  inputContainer.style.cssText = `
    display: flex;
    gap: 10px;
    margin-top: 10px;
  `;
  const input = document.createElement('input');
  input.type = 'text';
  input.id = 'compile-input-text';
  input.placeholder = 'Enter input for program...';
  input.style.cssText = `
    flex: 1;
    padding: 8px;
    background: #2d2d2d;
    color: #ffffff;
    border: 1px solid #555;
    border-radius: 4px;
    font-family: 'Courier New', monospace;
  `;
  const sendButton = document.createElement('button');
  sendButton.textContent = 'Send';
  sendButton.className = 'btn primary small';
  sendButton.onclick = sendInputToProgram;
  inputContainer.appendChild(input);
  inputContainer.appendChild(sendButton);
  editorContainer.appendChild(terminal);
  editorContainer.appendChild(inputContainer);
  const applyBtn = document.getElementById('btn-apply-code-changes');
  applyBtn.textContent = 'Close Terminal';
  applyBtn.onclick = closeCodeModal;
  modal.classList.remove('hidden');
  setTimeout(() => {
    input.focus();
  }, 100);
}
async function sendInputToProgram() {
  const terminal = document.getElementById('compile-terminal');
  const inputEl = document.getElementById('compile-input-text');
  const path = document.getElementById('viewer').dataset.path;
  if (!terminal || !inputEl || !path) {
    flashStatus(t(i18n, 'compileError'));
    return;
  }
  const input = inputEl.value.trim();
  terminal.textContent = ''; // Clear terminal
  const resp = await api('compile', { path, input }, 'POST');
  terminal.textContent = resp.output || 'No output';
}

function initFileExtensionHighlighting() {
    const extensionSelect = document.getElementById('file-extension-select');
    if (!extensionSelect) return;
    
    const originalContainer = extensionSelect.parentNode;
    const customSelect = document.createElement('div');
    customSelect.className = 'custom-select';
    customSelect.style.cssText = `
        position: relative;
        width: 80%;
        z-index: 1000;
        min-width: 60px;
    `;
    
    const selectedDisplay = document.createElement('div');
    selectedDisplay.className = 'custom-select-selected';
    selectedDisplay.textContent = '.txt';
    selectedDisplay.style.cssText = `
        padding: 8px 12px;
        border: 1px solid var(--border);
        border-radius: 4px;
        background: var(--panel);
        color: var(--fg);
        cursor: pointer;
        font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
        display: flex;
        justify-content: space-between;
        align-items: center;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        position: relative;
        z-index: 2;
    `;
    
    const arrow = document.createElement('span');
    arrow.textContent = '▼';
    arrow.style.fontSize = '10px';
    arrow.style.marginLeft = '8px';
    arrow.style.flexShrink = '0';
    selectedDisplay.appendChild(arrow);
    
    const optionsContainer = document.createElement('div');
    optionsContainer.className = 'custom-select-options hidden';
    optionsContainer.style.cssText = `
        position: fixed;
        max-height: 400px;
        overflow-y: auto;
        background: var(--panel);
        border: 1px solid var(--border);
        border-radius: 8px;
        margin: 0;
        box-shadow: 0 8px 30px rgba(0,0,0,0.3);
        z-index: 1000002;
        min-width: 150px;
        width: auto;
        opacity: 0;
        transform: scale(0.95);
        transition: opacity 0.15s ease, transform 0.15s ease;
        pointer-events: auto;
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        border: 1px solid rgba(var(--accent-rgb), 0.3);
    `;
    
    // Add scroll styles
    optionsContainer.style.scrollbarWidth = 'thin';
    optionsContainer.style.scrollbarColor = 'var(--scrollbar-thumb) var(--scrollbar-track)';
    
    const officeColors = {
        '.docx': '#2b579a',
        '.doc': '#2b579a',
        '.xlsx': '#217346',
        '.xls': '#217346',
        '.csv': '#217346',
        '.pptx': '#d24726',
        '.ppt': '#d24726'
    };
    
    // Collect options
    for (let i = 0; i < extensionSelect.options.length; i++) {
        const option = extensionSelect.options[i];
        const customOption = document.createElement('div');
        customOption.className = 'custom-option';
        customOption.dataset.value = option.value;
        
        const optionText = document.createElement('span');
        optionText.textContent = option.textContent;
        optionText.style.cssText = `
            display: block;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            width: 100%;
        `;
        
        customOption.appendChild(optionText);
        
        if (officeColors[option.value]) {
            customOption.style.cssText = `
                padding: 10px 16px;
                cursor: pointer;
                color: ${officeColors[option.value]};
                font-weight: 600;
                transition: all 0.15s ease;
                display: flex;
                align-items: center;
                white-space: nowrap;
                border-bottom: 1px solid var(--border);
            `;
        } else {
            customOption.style.cssText = `
                padding: 10px 16px;
                cursor: pointer;
                color: var(--fg);
                transition: all 0.15s ease;
                display: flex;
                align-items: center;
                white-space: nowrap;
                border-bottom: 1px solid var(--border);
            `;
        }
        
        if (i === extensionSelect.options.length - 1) {
            customOption.style.borderBottom = 'none';
        }
        
        customOption.addEventListener('click', () => {
            extensionSelect.value = option.value;
            selectedDisplay.textContent = option.textContent;
            selectedDisplay.appendChild(arrow);
            
            if (officeColors[option.value]) {
                selectedDisplay.style.borderColor = officeColors[option.value];
                selectedDisplay.style.boxShadow = `0 0 0 1px ${officeColors[option.value]}33`;
                selectedDisplay.style.color = officeColors[option.value];
                selectedDisplay.style.fontWeight = '600';
            } else {
                selectedDisplay.style.borderColor = 'var(--border)';
                selectedDisplay.style.boxShadow = '';
                selectedDisplay.style.color = 'var(--fg)';
                selectedDisplay.style.fontWeight = '400';
            }
            
            hideOptions();
            extensionSelect.dispatchEvent(new Event('change'));
        });
        
        customOption.addEventListener('mouseenter', () => {
            customOption.style.background = 'var(--accent)';
            customOption.style.color = 'white';
        });
        
        customOption.addEventListener('mouseleave', () => {
            if (officeColors[option.value]) {
                customOption.style.background = '';
                customOption.style.color = officeColors[option.value];
            } else {
                customOption.style.background = '';
                customOption.style.color = 'var(--fg)';
            }
        });
        
        optionsContainer.appendChild(customOption);
    }
    
    function showOptions() {
        const rect = selectedDisplay.getBoundingClientRect();
        
        // Calculate the position
        const scrollbarWidth = window.innerWidth - document.documentElement.clientWidth;
        const viewportWidth = window.innerWidth;
        const viewportHeight = window.innerHeight;
        
        // Determine list width (min 200px, max 300px)
        let listWidth = Math.min(300, Math.max(200, rect.width));
        
        // Horizontal position (center relative to the select)
        let left = rect.left + (rect.width / 2) - (listWidth / 2);
        
        // Adjust so it does not overflow the edges (10px margin)
        left = Math.max(10, Math.min(left, viewportWidth - listWidth - scrollbarWidth - 10));
        
        // Reset previous positioning styles
        optionsContainer.style.top = '';
        optionsContainer.style.bottom = '';
        
        // Vertical position
        let top = rect.bottom + 5; // 5px offset from the bottom
        
        // Check if the list fits below (10px from the bottom edge)
        const spaceBelow = viewportHeight - top - 10;
        const spaceAbove = rect.top - 10;
        
        if (spaceBelow >= 200) {
            // Fits below - show downward
            top = rect.bottom + 5;
            optionsContainer.style.top = `${top}px`;
            optionsContainer.style.maxHeight = `${Math.min(400, spaceBelow - 10)}px`;
            optionsContainer.style.transformOrigin = 'top';
        } else if (spaceAbove >= 200) {
            // Does not fit below but fits above
            const bottom = viewportHeight - rect.top + 5;
            optionsContainer.style.bottom = `${bottom}px`;
            optionsContainer.style.maxHeight = `${Math.min(400, spaceAbove - 10)}px`;
            optionsContainer.style.transformOrigin = 'bottom';
        } else {
            // Fits neither above nor below - use available space
            if (spaceBelow > spaceAbove) {
                top = rect.bottom + 5;
                optionsContainer.style.top = `${top}px`;
                optionsContainer.style.maxHeight = `${Math.max(100, spaceBelow - 10)}px`;
                optionsContainer.style.transformOrigin = 'top';
            } else {
                const bottom = viewportHeight - rect.top + 5;
                optionsContainer.style.bottom = `${bottom}px`;
                optionsContainer.style.maxHeight = `${Math.max(100, spaceAbove - 10)}px`;
                optionsContainer.style.transformOrigin = 'bottom';
            }
        }
        
        optionsContainer.style.left = `${left}px`;
        optionsContainer.style.width = `${listWidth}px`;
        
        // Show with animation
        optionsContainer.classList.remove('hidden');
        setTimeout(() => {
            optionsContainer.style.opacity = '1';
            optionsContainer.style.transform = 'scale(1)';
        }, 10);
    }
    
    function hideOptions() {
        optionsContainer.style.opacity = '0';
        optionsContainer.style.transform = 'scale(0.95)';
        setTimeout(() => {
            optionsContainer.classList.add('hidden');
        }, 150);
    }
    
    selectedDisplay.addEventListener('click', (e) => {
        e.stopPropagation();
        e.preventDefault();
        
        if (optionsContainer.classList.contains('hidden')) {
            // Hide all other open selects
            document.querySelectorAll('.custom-select-options:not(.hidden)').forEach(el => {
                el.classList.add('hidden');
                el.style.opacity = '0';
                el.style.transform = 'scale(0.95)';
            });
            
            showOptions();
        } else {
            hideOptions();
        }
    });
    
    // Close on outside click
    document.addEventListener('click', (e) => {
        if (!customSelect.contains(e.target) && !optionsContainer.contains(e.target)) {
            hideOptions();
        }
    });
    
    // Close on scroll ONLY if the scroll was NOT inside the list
    window.addEventListener('scroll', () => {
        if (!optionsContainer.classList.contains('hidden')) {
            // Check whether the focus is inside the list
            const activeElement = document.activeElement;
            const isScrollingInside = optionsContainer.contains(activeElement) || 
                                      optionsContainer.matches(':hover') ||
                                      optionsContainer.contains(event?.target);
            
            if (!isScrollingInside) {
                hideOptions();
            }
        }
    }, true);
    
    // Close on window resize
    window.addEventListener('resize', () => {
        if (!optionsContainer.classList.contains('hidden')) {
            hideOptions();
        }
    });
    
    // Close on Escape
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && !optionsContainer.classList.contains('hidden')) {
            hideOptions();
        }
    });
    
    // Prevent the scroll event from bubbling inside the list
    optionsContainer.addEventListener('wheel', (e) => {
        e.stopPropagation();
    });
    
    optionsContainer.addEventListener('touchmove', (e) => {
        e.stopPropagation();
    });
    
    // Close when a modal opens
    const modal = document.getElementById('new-file-modal');
    if (modal) {
        const observer = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                if (mutation.attributeName === 'class') {
                    const isHidden = modal.classList.contains('hidden');
                    if (!isHidden) {
                        // Reset when a modal opens
                        selectedDisplay.style.borderColor = 'var(--border)';
                        selectedDisplay.style.boxShadow = '';
                        selectedDisplay.style.color = 'var(--fg)';
                        selectedDisplay.style.fontWeight = '400';
                        selectedDisplay.textContent = '.txt';
                        selectedDisplay.appendChild(arrow);
                        extensionSelect.value = '';
                        hideOptions();
                    } else {
                        // Close the list when the modal closes
                        hideOptions();
                    }
                }
            });
        });
        observer.observe(modal, { attributes: true });
    }
    
    customSelect.appendChild(selectedDisplay);
    document.body.appendChild(optionsContainer);
    originalContainer.insertBefore(customSelect, extensionSelect.nextSibling);
    extensionSelect.style.display = 'none';
    
    // Add styles
    const style = document.createElement('style');
    style.textContent = `
        .custom-select-options {
            scrollbar-width: thin;
            scrollbar-color: var(--scrollbar-thumb) var(--scrollbar-track);
        }
        
        .custom-select-options::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }
        
        .custom-select-options::-webkit-scrollbar-track {
            background: var(--scrollbar-track);
            border-radius: 3px;
        }
        
        .custom-select-options::-webkit-scrollbar-thumb {
            background: var(--scrollbar-thumb);
            border-radius: 3px;
        }
        
        .custom-select-options::-webkit-scrollbar-thumb:hover {
            background: var(--scrollbar-thumb-hover);
        }
        
        .custom-select-options.hidden {
            display: none !important;
        }
        
        .custom-option:last-child {
            border-bottom: none !important;
        }
        
        .custom-option:hover {
            background: var(--accent) !important;
            color: white !important;
        }
        
        /* Animation for smooth appearance */
        .custom-select-options {
            transition: opacity 0.15s ease, transform 0.15s ease;
        }
        
        /* Remove pointer-events for scrolling */
        .custom-select-options {
            pointer-events: auto !important;
        }
    `;
    document.head.appendChild(style);
}


function escapeRegex(string) {
    return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}
async function refreshUsers(i18n) {
    const state = await api('get_state');
    setCurrentUser(state.active);
    const tableBody = document.getElementById('users-table-body');
    tableBody.innerHTML = '';
    
    state.users.forEach(u => {
        const tr = document.createElement('tr');
        if (u.name === state.active) {
            tr.classList.add('active');
        }
        
        const displayEmail = u.email || u.name;
        const quotaHuman = formatBytes((u.quota_mb || 0) * 1024 * 1024);
        const usedBytes = formatBytes(u.used_bytes || 0);
        const sessionLimit = (u.session_limit_mb || 0) + 'MB';
        
        tr.innerHTML = `
            <td><span class="user-name">${escapeHtml(displayEmail)}</span></td>
            <td><span class="user-quota-used">${usedBytes}</span></td>
            <td><span class="user-quota-used">${quotaHuman}</span></td>
            <td><span class="user-quota-used">${sessionLimit}</span></td>
        `;
        
        tableBody.appendChild(tr);
    });
    
    const active = state.users.find(u => u.name === state.active);
    updateUserSettingsAvatar(state.photo || active?.photo);
    if (!active) {
        return;
    }
    const activeDisplayEmail = (active && active.email) || state.active_email || state.active;
    window.cloudCurrentUserDisplayEmail = activeDisplayEmail || '';
    const statusText = isMobileDevice() ?
        `${t(i18n, 'activeUser')}: ${activeDisplayEmail} • ${t(i18n, 'used')}: ${formatBytes(active.used_bytes)}` :
        `${t(i18n, 'activeUser')}: ${activeDisplayEmail} • ${t(i18n, 'used')}: ${formatBytes(active.used_bytes)}`;
    
    const quotaEl = document.getElementById('status-quota');
    if (quotaEl) quotaEl.textContent = statusText;
    else document.getElementById('status-left').textContent = statusText;
    syncSettingsControls(state);
}
async function syncSettingsControls(state = null) {
    if (!state) {
        state = await api('get_state');
        setCurrentUser(state.active);
    }
    const active = state.users.find(u => u.name === state.active);
    if (!active) return;
    const activeDisplayEmail = (active && active.email) || state.active_email || state.active;
    window.cloudCurrentUserDisplayEmail = activeDisplayEmail || '';
    document.getElementById('quota-mb').value = active.quota_mb;
    document.getElementById('session-limit-mb').value = active.session_limit_mb || 0;
    const hideLangToggle = document.getElementById('hide-lang-switcher-toggle');
    if (hideLangToggle) {
        hideLangToggle.checked = !!active.hide_lang_switcher;
    }
    localStorage.setItem(HIDE_LANG_SWITCHER_CACHE_KEY, active.hide_lang_switcher ? '1' : '0');
    applyLangSwitcherVisibility(!!active.hide_lang_switcher);

    // Check the user level and show the key change button
    const userRole = await getUserRole();

    // Update the user role icon
    updateUserRoleIcon(userRole);

    // Hide the compile button for level 0 users
    const compileMenuItem = document.getElementById('code-action-compile');
    if (compileMenuItem) {
        compileMenuItem.style.display = (userRole === 0) ? 'none' : 'flex';
    }
}

function applyLangSwitcherVisibility(hidden) {
    const switcher = document.querySelector('.lang-switcher');
    document.documentElement.classList.toggle('hide-lang-switcher', !!hidden);
    if (!switcher) return;
    switcher.classList.toggle('hidden', !!hidden);
}
let buildTreeRequestId = 0;
let treeBuildInFlightRequestId = 0;
async function buildTree(path) {
    const requestId = ++buildTreeRequestId;
    treeBuildInFlightRequestId = requestId;
    const requestRoot = getCurrentCloudRoot();
    const tree = document.getElementById('tree');
    tree.innerHTML = '';
    try {
        if (isTrashRoot()) {
            tree.classList.remove('is-empty');
            await renderTrashView();
            if (requestId !== buildTreeRequestId || requestRoot !== getCurrentCloudRoot()) return;
            return;
        }
        const useFlatCache = requestRoot === 'main' && !isCloudGroupRoot(requestRoot);
        const browsePath = typeof getTreeBrowsePath === 'function' ? getTreeBrowsePath() : '';

    // Only use full-tree cache for root-level calls (path === '' or undefined).
    // Sub-path calls (rare) fall back to the old list-based renderNodes path.
        if (!path && useFlatCache && !browsePath) {
        // Try cache first
        let items = cachedFileTree;
        if (items === null) {
            items = await fetchFileTree();
        }
        if (requestId !== buildTreeRequestId || requestRoot !== getCurrentCloudRoot()) return;

        // If cache returned something (even an empty array), render from it
        if (items !== null) {
            const hiddenRoots = ['.archive', '.temp_files', '.documents', '.photos', '.pdf', '.trash', '.cloud_groups'];
            let mergedItems = items;
            try {
                const freshRoot = await api('list', { path: '' }, 'GET');
                if (Array.isArray(freshRoot?.items) && freshRoot.items.length) {
                    const known = new Set(mergedItems.map((item) => item.path));
                    mergedItems = mergedItems.concat(freshRoot.items.filter((item) => item?.path && !known.has(item.path)));
                }
            } catch (_) {}
            const visibleItems = dedupeTreeItemsByPath(mergedItems).filter((item) => {
                if (hiddenRoots.some((root) => item.path === root || item.path.startsWith(root + '/'))) {
                    return false;
                }
                return true;
            });
            const scopedItems = browsePath
                ? visibleItems.filter((item) => {
                    const p = String(item?.path || '');
                    return p === browsePath || p.startsWith(browsePath + '/');
                })
                : visibleItems;
            const container = buildTreeFromFlatItems(scopedItems);
            tree.appendChild(container);
            dedupeRenderedTreeNodesByPath(tree);
            tree.classList.toggle('is-empty', countDirectTreeChildren(scopedItems, browsePath) === 0);
            scheduleTreeAutoShift();
            if (typeof updateTreeBrowseHeader === 'function') updateTreeBrowseHeader();
            if (typeof syncAllTreeChildrenGuides === 'function') syncAllTreeChildrenGuides(tree);
                return;
            }
        }

        const listPath = browsePath || path || '';
        const root = await api('list', { path: listPath }, 'GET');
        if (requestId !== buildTreeRequestId || requestRoot !== getCurrentCloudRoot()) return;
        const savedSort = localStorage.getItem('treeSortType');
        if (savedSort) sortState.currentSort = savedSort;
        root.items = sortItems(dedupeTreeItemsByPath(root.items), sortState.currentSort);
        if (browsePath) root.items = root.items.filter((item) => String(item?.path || '') !== browsePath);
        let renderItems = root.items;
        let renderBase = listPath;
        const container = renderNodes(renderItems, renderBase, 0);
        tree.appendChild(container);
        dedupeRenderedTreeNodesByPath(tree);
        tree.classList.toggle('is-empty', renderItems.length === 0);
        await restoreFolderState();
        scheduleTreeAutoShift();
        if (typeof updateTreeBrowseHeader === 'function') updateTreeBrowseHeader();
        if (typeof syncAllTreeChildrenGuides === 'function') syncAllTreeChildrenGuides(tree);
    } finally {
        if (treeBuildInFlightRequestId === requestId) treeBuildInFlightRequestId = 0;
    }
}
window.buildTree = buildTree;

const TREE_AUTO_REFRESH_INTERVAL_MS = 1000;
let treeAutoRefreshTimerId = null;
let treeAutoRefreshInFlight = false;

function getTreeNodeParentPath(path) {
    const normalized = String(path || '').replace(/^\/+/, '');
    return normalized.includes('/') ? normalized.slice(0, normalized.lastIndexOf('/')) : '';
}

function getOpenTreeFolderPaths() {
    const paths = [''];
    document.querySelectorAll('#tree .node[data-type="dir"]').forEach((node) => {
        const children = node.nextElementSibling;
        if (!children || !children.classList.contains('children')) return;
        if (children.style.display === 'none') return;
        const path = String(node.dataset.path || '').trim();
        if (path) paths.push(path);
    });
    return [...new Set(paths)];
}

function isVisibleMainTreeItem(item) {
    const hiddenRoots = ['.archive', '.temp_files', '.documents', '.photos', '.pdf', '.trash', '.cloud_groups'];
    if (!item || !item.path) return false;
    if (hiddenRoots.some((root) => item.path === root || item.path.startsWith(root + '/'))) return false;
    return true;
}

function isTreeAutoRefreshIdle() {
    if (document.hidden) return false;
    if (window.uploadState?.isUploading) return false;
    if (window.aiState?.isGenerating) return false;
    return true;
}

async function refreshMainTreeIncrementally() {
    if (getCurrentCloudRoot() !== 'main') return;
    const browsePath = typeof getTreeBrowsePath === 'function' ? getTreeBrowsePath() : '';
    const data = await api('get_file_tree', {}, 'GET');
    if (getCurrentCloudRoot() !== 'main') return;
    if (!data?.ok || !Array.isArray(data.items)) return;

    const previousItems = Array.isArray(cachedFileTree) ? cachedFileTree : [];
    const previousMap = new Map(previousItems.map((item) => [item.path, item]));
    const nextItems = dedupeTreeItemsByPath(data.items);
    const nextMap = new Map(nextItems.map((item) => [item.path, item]));
    cachedFileTree = nextItems;

    if (!previousItems.length) return;

    const isInCurrentTreeScope = (item) => {
        const itemPath = String(item?.path || '');
        if (!browsePath) return true;
        return itemPath !== browsePath && itemPath.startsWith(browsePath + '/');
    };
    const addedItems = nextItems.filter((item) => !previousMap.has(item.path) && isVisibleMainTreeItem(item) && isInCurrentTreeScope(item));
    const removedItems = previousItems.filter((item) => !nextMap.has(item.path) && isVisibleMainTreeItem(item) && isInCurrentTreeScope(item));

    if (!addedItems.length && !removedItems.length) return;

    removedItems
        .sort((a, b) => b.path.length - a.path.length)
        .forEach((item) => treeNodeRemove(item.path, { animate: false }));

    addedItems
        .sort((a, b) => a.path.localeCompare(b.path, undefined, { numeric: true, sensitivity: 'base' }))
        .forEach((item) => {
            if (areAllParentsOpen(item.path)) {
                treeNodeAdd(item, { openParents: false, highlight: false });
            }
        });
    dedupeRenderedTreeNodesByPath();

    const tree = document.getElementById('tree');
    if (tree) {
        const visibleItems = nextItems.filter((item) => isVisibleMainTreeItem(item) && isInCurrentTreeScope(item));
        tree.classList.toggle('is-empty', countDirectTreeChildren(visibleItems, browsePath) === 0);
    }
}

function normalizeCompileChangePathForRoot(change, root = 'main', sourcePath = '') {
    const candidatePath = String(change?.path || change?.relativePath || change?.file || '')
        .replace(/\\/g, '/')
        .replace(/^\/+/, '')
        .trim();
    const rawFullPath = String(change?.fullPath || '').replace(/\\/g, '/').replace(/^\/+/, '').trim();
    const rawPath = /^(?:[A-Za-z]:)?\//.test(String(change?.path || '').replace(/\\/g, '/').trim()) && rawFullPath
        ? rawFullPath
        : candidatePath;
    if (!rawPath) return '';
    const sourceDir = String(sourcePath || '').includes('/')
        ? String(sourcePath).slice(0, String(sourcePath).lastIndexOf('/'))
        : '';
    let currentUser = currentUserState.user || 'default';
    currentUser = String(currentUser).replace(/\s+/g, '_');
    const storagePrefix = `storage/${currentUser}/`;
    let normalizedPath = rawPath.startsWith(storagePrefix) ? rawPath.slice(storagePrefix.length) : rawPath;
    const rootPrefix = getRootFolderPrefix(root);
    if (root !== 'main' && rootPrefix) {
        if (normalizedPath === rootPrefix) normalizedPath = '';
        else if (normalizedPath.startsWith(rootPrefix + '/')) normalizedPath = normalizedPath.slice(rootPrefix.length + 1);
    }
    if (change?.path && !/^(?:[A-Za-z]:)?\//.test(String(change.path).replace(/\\/g, '/').trim())) return normalizedPath;
    return sourceDir && normalizedPath ? `${sourceDir}/${normalizedPath}` : normalizedPath;
}

async function applyCompileFileChangesToTree(fileChanges, { root = getCurrentCloudRoot(), sourcePath = '' } = {}) {
    const r = root || getCurrentCloudRoot();
    const now = Math.floor(Date.now() / 1000);
    const normalizedChanges = (Array.isArray(fileChanges) ? fileChanges : [])
        .map((change) => {
            const relPath = normalizeCompileChangePathForRoot(change, r, sourcePath);
            if (!relPath) return null;
            return { change, relPath };
        })
        .filter(Boolean);
    if (!normalizedChanges.length) return { createdPaths: [], normalizedChanges: [] };

    const ops = normalizedChanges.map(({ change, relPath }) => {
        if (change.action === 'created') {
            return {
                op: 'add',
                item: {
                    name: change.file || relPath.split('/').pop(),
                    path: relPath,
                    type: change.type || 'file',
                    size: 0,
                    mtime: now
                }
            };
        }
        if (change.action === 'deleted') {
            return { op: 'remove', path: relPath };
        }
        return null;
    }).filter(Boolean);
    if (!ops.length) return { createdPaths: [], normalizedChanges };

    if (typeof patchTreeCacheForRoot === 'function') {
        await patchTreeCacheForRoot(ops, r);
    } else if (typeof patchTreeCache === 'function') {
        await patchTreeCache(ops);
    }

    const createdPaths = [];
    if (getCurrentCloudRoot() === r) {
        ops.filter((op) => op.op === 'remove')
            .sort((a, b) => String(b.path || '').length - String(a.path || '').length)
            .forEach((op) => treeNodeRemove(op.path, { animate: false }));

        ops.filter((op) => op.op === 'add')
            .sort((a, b) => {
                const aIsDir = a.item?.type === 'dir';
                const bIsDir = b.item?.type === 'dir';
                if (aIsDir !== bIsDir) return aIsDir ? -1 : 1;
                const aDepth = (String(a.item?.path || '').match(/\//g) || []).length;
                const bDepth = (String(b.item?.path || '').match(/\//g) || []).length;
                if (aDepth !== bDepth) return aDepth - bDepth;
                return String(a.item?.path || '').localeCompare(String(b.item?.path || ''), undefined, { numeric: true, sensitivity: 'base' });
            })
            .forEach((op) => {
                const itemPath = String(op.item?.path || '');
                if (!itemPath) return;
                const inserted = treeNodeAdd(op.item, { openParents: false, allowHiddenParent: true, highlight: false });
                if (inserted) {
                    if (areAllParentsOpen(itemPath)) {
                        createdPaths.push(itemPath);
                        if (typeof highlightNewTreeNode === 'function') highlightNewTreeNode(itemPath);
                    }
                }
            });

        if (createdPaths.length && typeof pinTreePaths === 'function') {
            pinTreePaths(createdPaths, { scrollToLast: true });
        }
        const tree = document.getElementById('tree');
        if (tree) tree.classList.remove('is-empty');
    }

    return { createdPaths, normalizedChanges };
}
window.applyCompileFileChangesToTree = applyCompileFileChangesToTree;

async function refreshScopedRootIncrementally(root) {
    if (root !== getCurrentCloudRoot()) return;
    const scopeParents = getOpenTreeFolderPaths();
    const listResults = await Promise.all(scopeParents.map(async (parentPath) => {
        try {
            const response = await api('list', { path: parentPath, root }, 'GET');
            return { parentPath, items: Array.isArray(response?.items) ? response.items : [] };
        } catch (_) {
            return null;
        }
    }));
    if (root !== getCurrentCloudRoot()) return;

    const fetchedPaths = new Set();
    const fetchedItems = [];
    for (const result of listResults) {
        if (!result) continue;
        for (const item of result.items) {
            if (!item?.path) continue;
            fetchedPaths.add(item.path);
            fetchedItems.push(item);
        }
    }

    fetchedItems
        .sort((a, b) => a.path.localeCompare(b.path, undefined, { numeric: true, sensitivity: 'base' }))
        .forEach((item) => {
            if (!getTreeNodeByPath(item.path)) {
                treeNodeAdd(item, { openParents: false, highlight: false });
            }
        });
    dedupeRenderedTreeNodesByPath();

    const scopedParents = new Set(scopeParents);
    document.querySelectorAll('#tree .node[data-path]').forEach((node) => {
        const path = String(node.dataset.path || '');
        if (!path) return;
        const parentPath = getTreeNodeParentPath(path);
        if (!scopedParents.has(parentPath)) return;
        if (!fetchedPaths.has(path)) {
            treeNodeRemove(path, { animate: false });
        }
    });

    const tree = document.getElementById('tree');
    if (tree) tree.classList.toggle('is-empty', fetchedPaths.size === 0);
}

async function runTreeAutoRefresh() {
    if (treeAutoRefreshInFlight || !isTreeAutoRefreshIdle()) return;
    if (treeBuildInFlightRequestId) return;
    const root = getCurrentCloudRoot();
    if (root === 'trash') return;

    treeAutoRefreshInFlight = true;
    try {
        if (root === 'main') {
            await refreshMainTreeIncrementally();
        } else {
            await refreshScopedRootIncrementally(root);
        }
    } catch (err) {
        console.warn('Tree auto refresh failed:', err);
    } finally {
        treeAutoRefreshInFlight = false;
    }
}

function initTreeAutoRefresh() {
    if (treeAutoRefreshTimerId !== null) return;
    treeAutoRefreshTimerId = window.setInterval(() => {
        void runTreeAutoRefresh();
    }, TREE_AUTO_REFRESH_INTERVAL_MS);
    void runTreeAutoRefresh();
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) void runTreeAutoRefresh();
    });
    window.addEventListener('focus', () => {
        void runTreeAutoRefresh();
    });
}

function updateTrashToolbarSelectionState() {
    const count = selectionState.selectedItems.size;
    const deleteSelectedBtn = document.getElementById('btn-trash-delete-selected');
    const restoreSelectedBtn = document.getElementById('btn-trash-restore-selected');
    const restoreAllBtn = document.getElementById('btn-trash-restore-all');
    const deleteAllBtn = document.getElementById('btn-trash-delete-all');
    if (restoreAllBtn) restoreAllBtn.textContent = t(i18n, 'restoreAll');
    if (deleteAllBtn) deleteAllBtn.textContent = t(i18n, 'deleteAll');
    if (deleteSelectedBtn) deleteSelectedBtn.textContent = t(i18n, 'deleteSelected', { count });
    if (restoreSelectedBtn) restoreSelectedBtn.textContent = t(i18n, 'restoreSelected', { count });
    if (deleteSelectedBtn) deleteSelectedBtn.disabled = count === 0;
    if (restoreSelectedBtn) restoreSelectedBtn.disabled = count === 0;
}

function formatTrashDeletedAt(timestamp) {
    if (!timestamp) return '';
    try {
        return new Date(timestamp * 1000).toLocaleString();
    } catch (_) {
        return '';
    }
}

function updateToolbarForTrashView() {
    const normalIds = [
        'btn-ai', 'btn-save', 'btn-monaco-undo', 'btn-monaco-redo', 'btn-download', 'btn-copy', 'btn-share', 'btn-fullscreen'
    ];
    normalIds.forEach((id) => document.getElementById(id)?.classList.add('hidden'));
    document.querySelector('.code-actions')?.classList.add('hidden');
    document.getElementById('tabs-container')?.classList.add('hidden');
    document.getElementById('btn-trash-restore-all')?.classList.remove('hidden');
    document.getElementById('btn-trash-delete-selected')?.classList.remove('hidden');
    document.getElementById('btn-trash-delete-all')?.classList.remove('hidden');
    document.getElementById('btn-trash-restore-selected')?.classList.remove('hidden');
    updateTrashToolbarSelectionState();
}

function restoreDefaultToolbarAfterTrash() {
    const activeTab = tabsState.tabs.find((tab) => tab.id === tabsState.activeTabId);
    ['btn-ai', 'btn-download', 'btn-share', 'btn-fullscreen'].forEach((id) => {
        document.getElementById(id)?.classList.remove('hidden');
    });
    document.querySelector('.code-actions')?.classList.remove('hidden');
    document.getElementById('tabs-container')?.classList.remove('hidden');
    document.getElementById('btn-trash-restore-all')?.classList.add('hidden');
    document.getElementById('btn-trash-delete-selected')?.classList.add('hidden');
    document.getElementById('btn-trash-delete-all')?.classList.add('hidden');
    document.getElementById('btn-trash-restore-selected')?.classList.add('hidden');
    if (activeTab) {
        updateToolbarForTab(activeTab);
    } else {
        document.getElementById('btn-save')?.classList.add('hidden');
        document.getElementById('btn-monaco-undo')?.classList.add('hidden');
        document.getElementById('btn-monaco-redo')?.classList.add('hidden');
        document.getElementById('btn-copy')?.classList.add('hidden');
    }
}

function clearRegularTreeSelectionUI() {
    document.querySelectorAll('#tree .node.selected, #tree .advanced-search-result.selected').forEach((node) => {
        node.classList.remove('selected');
    });
    document.querySelectorAll('#tree .selection-checkbox').forEach((checkbox) => checkbox.remove());
    const tree = document.getElementById('tree');
    if (tree) {
        tree.classList.remove('selection-mode');
        tree.style.cursor = '';
    }
    const actionPanel = document.getElementById('selection-action-panel');
    if (actionPanel) actionPanel.classList.add('hidden');
}

function setTrashSelection(paths, anchorIndex = null) {
    selectionState.selectedItems = new Set(paths);
    if (anchorIndex !== null) {
        trashState.anchorIndex = anchorIndex;
    }
    updateSelectionUI();
}

function toggleTrashItemSelection(index, additive = false, range = false) {
    const item = trashState.items[index];
    if (!item) return;

    if (!additive && !range) {
        const next = new Set(selectionState.selectedItems);
        if (next.has(item.path)) {
            next.delete(item.path);
            selectionState.selectedItems = next;
            trashState.focusedIndex = index;
            trashState.anchorIndex = next.size ? index : -1;
            updateSelectionUI();
            return;
        }
        setTrashSelection([item.path], index);
        trashState.focusedIndex = index;
        return;
    }

    if (range && trashState.anchorIndex !== -1) {
        const start = Math.min(trashState.anchorIndex, index);
        const end = Math.max(trashState.anchorIndex, index);
        const paths = trashState.items.slice(start, end + 1).map((entry) => entry.path);
        setTrashSelection(paths, trashState.anchorIndex);
        trashState.focusedIndex = index;
        return;
    }

    const next = new Set(selectionState.selectedItems);
    if (next.has(item.path)) next.delete(item.path);
    else next.add(item.path);
    selectionState.selectedItems = next;
    trashState.anchorIndex = index;
    trashState.focusedIndex = index;
    updateSelectionUI();
}

function focusTrashItem(index, extendSelection = false) {
    if (!trashState.items.length) return;
    const boundedIndex = Math.max(0, Math.min(index, trashState.items.length - 1));
    trashState.focusedIndex = boundedIndex;
    const node = document.querySelector(`.trash-item[data-index="${boundedIndex}"]`);
    if (node) {
        node.focus({ preventScroll: false });
        node.scrollIntoView({ block: 'nearest', inline: 'nearest' });
    }
    if (extendSelection) {
        toggleTrashItemSelection(boundedIndex, false, true);
    }
}

function getTrashGridColumnCount() {
    const grid = document.getElementById('trash-grid');
    if (!grid) return 1;
    const style = getComputedStyle(grid);
    const cols = style.gridTemplateColumns.split(' ').filter(Boolean).length;
    return cols || 1;
}

function renderTrashItems(items) {
    const viewer = document.getElementById('viewer');
    if (!viewer) return;
    if (!items.length) {
        viewer.innerHTML = `<div class="trash-empty"><span class="trash-empty-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18" /><path d="M8 6V4h8v2" /><path d="M6 6l1 16h10l1-16" /><path d="M10 11v6" /><path d="M14 11v6" /></svg></span>${escapeHtml(t(i18n, 'trashEmpty') || 'Trash is empty')}</div>`;
        return;
    }

    const grid = document.createElement('div');
    grid.id = 'trash-grid';
    grid.className = 'trash-grid';

    items.forEach((item, index) => {
        const card = document.createElement('div');
        card.className = 'trash-item';
        card.dataset.path = item.path;
        card.dataset.index = String(index);
        card.tabIndex = 0;

        const isSelected = selectionState.selectedItems.has(item.path);
        if (isSelected) card.classList.add('selected');

        const checkbox = document.createElement('input');
        checkbox.type = 'checkbox';
        checkbox.className = 'selection-checkbox trash-item-checkbox';
        checkbox.checked = isSelected;
        checkbox.addEventListener('click', (e) => {
            e.stopPropagation();
            toggleTrashItemSelection(index, true, false);
        });

        const icon = document.createElement('div');
        icon.className = 'trash-item-icon';
        icon.innerHTML = getItemIconMarkup(item);

        const name = document.createElement('div');
        name.className = 'trash-item-name';
        name.textContent = item.name;
        name.title = item.name;

        const details = document.createElement('div');
        details.className = 'trash-item-details';

        const meta = document.createElement('div');
        meta.className = 'trash-item-meta';
        meta.innerHTML = `
            <div class="trash-item-meta-row">${escapeHtml(item.type === 'dir' ? t(i18n, 'folders') : formatBytes(item.size || 0))}</div>
            <div class="trash-item-meta-row">${escapeHtml(formatTrashDeletedAt(item.deleted_at))}</div>
            <div class="trash-item-path">${escapeHtml(item.original_path || '')}</div>
        `;
        const pathNode = meta.querySelector('.trash-item-path');
        if (pathNode) {
            pathNode.title = item.original_path || '';
        }

        details.append(name, meta);
        card.append(checkbox, icon, details);

        card.addEventListener('click', (e) => {
            const multi = e.ctrlKey || e.metaKey;
            if (e.shiftKey) {
                toggleTrashItemSelection(index, false, true);
            } else if (multi) {
                toggleTrashItemSelection(index, true, false);
            } else {
                toggleTrashItemSelection(index, false, false);
            }
        });
        card.addEventListener('dblclick', (e) => {
            e.preventDefault();
            e.stopPropagation();
            restoreTrashItems([item.path]);
        });
        card.addEventListener('contextmenu', (e) => {
            e.preventDefault();
            e.stopPropagation();
            showContextMenu(e, item.path, item.type, item.name);
        });

        grid.appendChild(card);
    });

    viewer.innerHTML = '';
    viewer.appendChild(grid);
}

function preserveActiveTabViewState() {
    const activeTab = tabsState.tabs.find((tab) => tab.id === tabsState.activeTabId);
    if (!activeTab) return;

    saveTabContent(activeTab);
    stashTabView(activeTab);
    cacheTabContent(activeTab);
    hideAllTabPanels(null);
}

async function renderTrashView() {
    const fileView = document.getElementById('file-view');
    const fileName = document.getElementById('file-name');
    const viewer = document.getElementById('viewer');
    if (!fileView) return;

    fileView.classList.remove('hidden');
    document.body.classList.add('trash-mode');
    fileName.textContent = t(i18n, 'trashRoot');
    if (viewer) {
        viewer.dataset.path = '';
        viewer.dataset.editMode = 'false';
        viewer.dataset.tabRoot = 'trash';
    }
    updateToolbarForTrashView();

    selectionState.active = true;
    selectionState.mode = 'select';
    selectionState.selectedItems.clear();
    selectionState.selectedRoots.clear();
    trashState.focusedIndex = -1;
    trashState.anchorIndex = -1;

    const resp = await api('list', { path: '', root: 'trash' }, 'GET');
    trashState.items = Array.isArray(resp.items) ? resp.items : [];
    renderTrashItems(trashState.items);
    updateSelectionUI();
}

async function syncRootModeView() {
    if (isTrashRoot()) {
        preserveActiveTabViewState();
        await renderTrashView();
        return;
    }

    if (!document.body.classList.contains('trash-mode')) {
        return;
    }

    const viewer = document.getElementById('viewer');
    document.body.classList.remove('trash-mode');
    trashState.items = [];
    trashState.focusedIndex = -1;
    trashState.anchorIndex = -1;
    selectionState.selectedItems.clear();
    selectionState.selectedRoots.clear();
    selectionState.active = false;
    if (viewer) {
        viewer.innerHTML = '';
        viewer.dataset.path = '';
        viewer.dataset.editMode = 'false';
        viewer.dataset.tabRoot = getCurrentCloudRoot();
    }
    clearRegularTreeSelectionUI();
    restoreDefaultToolbarAfterTrash();
    if (tabsState.activeTabId) {
        const activeTab = tabsState.tabs.find((tab) => tab.id === tabsState.activeTabId);
        if (activeTab) {
            restoreTabContent(activeTab);
            updateToolbarForTab(activeTab);
        }
    }
}

function getTrashItemByPath(path) {
    return trashState.items.find((item) => item.path === path) || null;
}

async function restoreTrashItems(paths) {
    const items = Array.from(new Set((paths || []).filter(Boolean)));
    if (!items.length) return;
    let restored = 0;
    let failed = 0;
    for (const path of items) {
        try {
            await api('restore_from_trash', { path, root: 'trash' });
            restored++;
        } catch (err) {
            console.error('Failed to restore from trash:', path, err);
            failed++;
        }
    }
    if (restored > 0) flashStatus(t(i18n, 'restoreSuccessMultiple', { count: restored }));
    if (failed > 0) flashStatus(t(i18n, 'restoreErrorMultiple', { count: failed }));
    await renderTrashView();
}

async function restoreSelectedTrashItems() {
    await restoreTrashItems(Array.from(selectionState.selectedItems));
}

async function restoreAllTrashItems() {
    await restoreTrashItems(trashState.items.map((item) => item.path));
}

async function emptyTrashWithConfirmation() {
    if (!trashState.items.length) {
        flashStatus(t(i18n, 'trashEmpty'));
        return;
    }
    const { confirmed } = await showConfirmModal(t(i18n, 'deleteAll'), t(i18n, 'emptyTrashConfirm'));
    if (!confirmed) return;
    await api('empty_trash', { root: 'trash' });
    await renderTrashView();
    flashStatus(t(i18n, 'trashEmptied'));
}
function deleteSelectedItems() {
    const selectedItems = Array.from(selectionState.selectedItems);
    if (selectedItems.length === 0) return;
    showDeleteConfirmationModal(selectedItems, { permanent: isTrashRoot(), fromTrash: isTrashRoot() });
}
function showDeleteConfirmationModal(selectedItems, options = {}) {
    let permanent = !!options.permanent;
    const fromTrash = !!options.fromTrash;
    // Check if a modal is already open
    const existingModal = document.querySelector('.modal[data-delete-modal="true"]');
    if (existingModal) {
        existingModal.remove();
    }
    
    const modal = document.createElement('div');
    modal.className = 'modal';
    modal.style.display = 'flex';
    modal.setAttribute('data-delete-modal', 'true');
    
    // Show loading
    modal.innerHTML = `
        <div class="modal-content" style="max-width: 500px;">
            <div class="modal-header">
                <h3>${permanent ? t(i18n, 'deletePermanently') : t(i18n, 'confirmDelete')}</h3>
                <button class="icon-btn close-btn" aria-label="${escapeHtml(t(i18n, 'close') || 'Close')}">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                </button>
            </div>
            <div class="modal-body">
                <div style="text-align: center; padding: 20px;">
                    <div class="spinner" style="
                        width: 40px;
                        height: 40px;
                        margin: 0 auto 15px;
                        animation: spin 1s linear infinite;
                    ">
                        <svg width="40" height="40" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <circle cx="20" cy="20" r="18" stroke="var(--border)" stroke-width="4" opacity="0.3"/>
                            <path d="M20 2a18 18 0 0 1 18 18" stroke="var(--accent)" stroke-width="4" stroke-linecap="round" opacity="0.8"/>
                        </svg>
                    </div>
                    <div>${t(i18n, 'loadingFileInfo')}</div>
                </div>
            </div>
            <div class="modal-footer">
                <button id="btn-cancel-delete-selected" class="btn secondary">${t(i18n, 'cancel')}</button>
                <button id="btn-confirm-delete-selected" class="btn danger" disabled>${t(i18n, 'delete')}</button>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    
    // Add handlers right away
    const cancelBtn = document.getElementById('btn-cancel-delete-selected');
    const confirmBtn = document.getElementById('btn-confirm-delete-selected');
    const closeBtn = modal.querySelector('.close-btn');
    
    const closeModal = () => {
        modal.remove();
    };
    
    cancelBtn.addEventListener('click', closeModal);
    confirmBtn.addEventListener('click', () => {
        if (!fromTrash) {
            permanent = !!modal.querySelector('#delete-selected-permanently-checkbox')?.checked;
        }
        closeModal();
        performMultipleDelete(selectedItems, { permanent, fromTrash });
    });
    closeBtn.addEventListener('click', closeModal);
    
    modal.addEventListener('click', (e) => {
        if (e.target === modal) {
            closeModal();
        }
    });
    
    // Close on Escape
    const escapeHandler = (e) => {
        if (e.key === 'Escape') {
            closeModal();
            document.removeEventListener('keydown', escapeHandler);
        }
    };
    document.addEventListener('keydown', escapeHandler);
    
    // Load file size info
    Promise.all(selectedItems.map(async (path) => {
        try {
            // Get the file name from the path
            const fileName = path.split('/').pop();
            
            // Use the correct API endpoint to get file info
            const info = await api('file_info', { path }, 'GET');
            
            // Determine the type (folder or file)
            const isDir = info.type === 'dir' || path.endsWith('/');
            
            return { 
                path, 
                name: fileName,
                size: info.size || 0,
                type: isDir ? 'dir' : 'file'
            };
        } catch (err) {
            console.warn(`Failed to get size for ${path}:`, err);
            // Try an alternative method to get the size
            try {
                const fileInfo = await api('open', { path }, 'GET');
                const fileName = path.split('/').pop();
                return { 
                    path, 
                    name: fileName,
                    size: fileInfo.size || 0,
                    type: fileInfo.type || 'file'
                };
            } catch {
                const fileName = path.split('/').pop();
                return { 
                    path, 
                    name: fileName,
                    size: 0, 
                    type: path.includes('.') ? 'file' : 'dir' 
                };
            }
        }
    })).then(fileInfos => {
        const totalSize = fileInfos.reduce((sum, item) => sum + item.size, 0);
        const foldersCount = fileInfos.filter(item => item.type === 'dir').length;
        const filesCount = fileInfos.filter(item => item.type === 'file').length;
        
        // Update the modal content
        const modalBody = modal.querySelector('.modal-body');
        modalBody.innerHTML = `
            <p>${permanent ? t(i18n, 'deleteSelectedPermanentlyConfirm', { count: selectedItems.length }) : t(i18n, 'deleteSelectedConfirm', { count: selectedItems.length })}</p>
            ${!fromTrash ? `<label class="animated-check-label" style="margin:12px 0;"><input type="checkbox" id="delete-selected-permanently-checkbox" class="animated-checkbox"/><span class="animated-checkbox-box"><svg width="24" height="18" viewBox="0 0 24 18" fill="none"><path d="M2 9L8.5 15.5L22 2" stroke="#fff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg></span><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg><span>${t(i18n, 'deletePermanently')}</span></label>` : ''}
            ${foldersCount > 0 ? `<p style="font-size: 12px; color: var(--muted);">${t(i18n, 'folders')}: ${foldersCount}, ${t(i18n, 'files')}: ${filesCount}</p>` : ''}
            <div style="max-height: 300px; overflow-y: auto; margin-top: 10px; border: 1px solid var(--border); border-radius: 4px;">
                <div style="padding: 8px; background: var(--panel); border-bottom: 1px solid var(--border); font-weight: bold; display: flex; justify-content: space-between;">
                    <span>${t(i18n, 'fileName')}</span>
                    <span>${t(i18n, 'fileSize')}</span>
                </div>
                ${fileInfos.map(item => {
                    const icon = getItemIconMarkup(item);
                    
                    return `
                        <div style="padding: 6px 8px; display: flex; justify-content: space-between; border-bottom: 1px solid var(--border);">
                            <span style="color: var(--muted); display: flex; align-items: center; gap: 4px;">
                                ${icon}
                                <span title="${item.path}">${item.name}</span>
                            </span>
                            <span style="font-family: monospace;">${formatBytes(item.size)}</span>
                        </div>
                    `;
                }).join('')}
                <div style="padding: 8px; background: var(--panel); font-weight: bold; display: flex; justify-content: space-between;">
                    <span>${t(i18n, 'total')}:</span>
                    <span>${formatBytes(totalSize)}</span>
                </div>
            </div>
            <p style="margin-top: 15px; color: #e74c3c; background: rgba(231, 76, 60, 0.1); padding: 10px; border-radius: 4px;">
                <strong>${t(i18n, 'warning')}:</strong> ${permanent ? t(i18n, 'trashPermanentDeleteWarning') : t(i18n, 'trashMoveWarning')}
            </p>
        `;
        
        // Enable the delete button
        confirmBtn.disabled = false;
        
    }).catch(err => {
        console.error('Failed to load file sizes:', err);
        // If sizes could not be loaded, show a simple window
        const modalBody = modal.querySelector('.modal-body');
        modalBody.innerHTML = `
            <p>${permanent ? t(i18n, 'deleteSelectedPermanentlyConfirm', { count: selectedItems.length }) : t(i18n, 'deleteSelectedConfirm', { count: selectedItems.length })}</p>
            ${!fromTrash ? `<label class="animated-check-label" style="margin:12px 0;"><input type="checkbox" id="delete-selected-permanently-checkbox" class="animated-checkbox"/><span class="animated-checkbox-box"><svg width="24" height="18" viewBox="0 0 24 18" fill="none"><path d="M2 9L8.5 15.5L22 2" stroke="#fff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg></span><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg><span>${t(i18n, 'deletePermanently')}</span></label>` : ''}
            <div style="max-height: 200px; overflow-y: auto; margin-top: 10px; border: 1px solid var(--border); border-radius: 4px; padding: 8px;">
                ${selectedItems.map(item => {
                    const fileName = item.split('/').pop();
                    const icon = getItemIconMarkup({ name: fileName, type: 'file' });
                    return `
                        <div style="padding: 4px; font-size: 12px; color: var(--muted); display: flex; align-items: center; gap: 4px;">
                            ${icon} ${fileName}
                        </div>
                    `;
                }).join('')}
            </div>
            <p style="margin-top: 15px; color: #e74c3c; background: rgba(231, 76, 60, 0.1); padding: 10px; border-radius: 4px;">
                <strong>${t(i18n, 'warning')}:</strong> ${permanent ? t(i18n, 'trashPermanentDeleteWarning') : t(i18n, 'trashMoveWarning')}
            </p>
        `;
        
        confirmBtn.disabled = false;
    });
}
async function performMultipleDelete(selectedItems, options = {}) {
    const permanent = !!options.permanent;
    const fromTrash = !!options.fromTrash;
    let successCount = 0;
    let errorCount = 0;
    const deletedPaths = [];
    for (const path of selectedItems) {
        try {
            if (permanent || fromTrash) {
                await api('delete', { path });
                await cleanupDeletedAudioTranscriptions([path]);
            } else {
                await api('move_to_trash', { path });
            }
            successCount++;
            deletedPaths.push(path);
            closeTabsByPath(path);
        } catch (err) {
            console.error(`Failed to delete ${path}:`, err);
            errorCount++;
        }
    }
    if (successCount > 0) {
        flashStatus(permanent || fromTrash
            ? t(i18n, 'deleteSuccessMultiple', { count: successCount })
            : t(i18n, 'moveToTrashSuccessMultiple', { count: successCount }));
        if (isTrashRoot()) {
            await renderTrashView();
        } else if (getCurrentCloudRoot() !== 'main') {
            await buildTree('');
        } else {
            const ops = deletedPaths.map(p => ({ op: 'remove', path: p }));
            await patchTreeCache(ops);
            deletedPaths.forEach((p) => {
                treeNodeRemove(p);
                removeAdvancedSearchResult(p);
            });
        }
        updateSelectionUI();
    }
    if (errorCount > 0) {
        flashStatus(t(i18n, 'deleteErrorMultiple', { count: errorCount }));
    }
    clearSelection();
}
async function cleanupDeletedAudioTranscriptions(paths) {
    try {
        let user = currentUserState.user || 'default';
        user = user.replace(/\s+/g, '_');
        const formData = new FormData();
        formData.append('action', 'delete_transcription_for_audio');
        formData.append('user', user);
        formData.append('paths', JSON.stringify(paths || []));
        await fetch('transcription_api.php', { method: 'POST', body: formData });
    } catch (error) {
        console.warn(t(window.i18n || {}, 'transcriptionCleanupFailed'), error);
    }
}
const archiveJobState = { items: new Map(), nextId: 1, panelVisible: false };
function updateFloatingActionPositions() {
    const ids = ['floating-download-btn', 'floating-upload-btn', 'floating-compile-btn', 'floating-archive-btn'];
    let offset = 30;
    ids.forEach((id) => {
        const btn = document.getElementById(id);
        if (!btn || btn.classList.contains('hidden')) return;
        btn.style.bottom = `${offset}px`;
        btn.style.right = '30px';
        offset += 58;
    });
}
document.addEventListener('DOMContentLoaded', () => {
    ['floating-download-btn', 'floating-upload-btn', 'floating-compile-btn', 'floating-archive-btn'].forEach((id) => {
        const btn = document.getElementById(id);
        if (!btn) return;
        new MutationObserver(updateFloatingActionPositions).observe(btn, { attributes: true, attributeFilter: ['class'] });
    });
    updateFloatingActionPositions();
});
function initArchiveJobsPanel() {
    const btn = document.getElementById('floating-archive-btn');
    const panel = document.getElementById('archive-progress-panel');
    if (!btn || !panel || btn.dataset.bound === '1') return;
    btn.dataset.bound = '1';
    btn.addEventListener('click', (e) => {
        e.stopPropagation();
        panel.classList.toggle('hidden');
        archiveJobState.panelVisible = !panel.classList.contains('hidden');
    });
    document.addEventListener('click', (e) => {
        if (!panel.contains(e.target) && !btn.contains(e.target) && !panel.classList.contains('hidden')) {
            panel.classList.add('hidden');
            archiveJobState.panelVisible = false;
        }
    });
}
function showArchiveJobsButton() {
    initArchiveJobsPanel();
    document.getElementById('floating-archive-btn')?.classList.remove('hidden');
    updateFloatingActionPositions();
}
function hideArchiveJobsButtonIfIdle() {
    if (archiveJobState.items.size > 0) return;
    document.getElementById('floating-archive-btn')?.classList.add('hidden');
    document.getElementById('archive-progress-panel')?.classList.add('hidden');
    updateFloatingActionPositions();
}
function updateArchiveCircularProgress() {
    const circle = document.querySelector('#floating-archive-btn .circular-progress circle');
    if (!circle || archiveJobState.items.size === 0) return;
    const progress = Array.from(archiveJobState.items.values()).reduce((sum, item) => sum + item.progress, 0) / archiveJobState.items.size;
    circle.style.strokeDashoffset = 183 - (progress / 100) * 183;
}
function addArchiveJobItem(label) {
    showArchiveJobsButton();
    const container = document.getElementById('archive-items-container');
    const localId = archiveJobState.nextId++;
    const item = document.createElement('div');
    item.className = 'download-item';
    item.id = `archive-job-item-${localId}`;
    item.innerHTML = `
        <div class="download-filename" title="${escapeHtml(label)}">${escapeHtml(label)}</div>
        <div class="download-progress-bar"><div class="download-progress-fill" style="width: 0%"></div></div>
        <div class="download-info"><span class="archive-job-status">0%</span><span class="download-percent">0%</span></div>
        <div class="download-actions"><button class="btn danger small archive-cancel-job" type="button">${escapeHtml(t(window.i18n || {}, 'cancel') || 'Cancel')}</button></div>
    `;
    container?.appendChild(item);
    archiveJobState.items.set(localId, { progress: 0, cancelled: false });
    return localId;
}
function updateArchiveJobItem(localId, job) {
    const progress = Math.max(0, Math.min(100, Number(job.progress || 0)));
    const state = archiveJobState.items.get(localId);
    if (state) state.progress = progress;
    const item = document.getElementById(`archive-job-item-${localId}`);
    if (item) {
        const fill = item.querySelector('.download-progress-fill');
        const percent = item.querySelector('.download-percent');
        const status = item.querySelector('.archive-job-status');
        if (fill) fill.style.width = `${progress}%`;
        if (percent) percent.textContent = `${Math.round(progress)}%`;
        if (status) status.textContent = job.status === 'error' ? (job.error || 'Error') : `${job.processed || 0}/${job.total || 0}`;
    }
    updateArchiveCircularProgress();
}
async function waitForArchiveJob(jobId, label) {
    const localId = addArchiveJobItem(label);
    const cancelBtn = document.querySelector(`#archive-job-item-${localId} .archive-cancel-job`);
    if (cancelBtn) {
        cancelBtn.addEventListener('click', async () => {
            const state = archiveJobState.items.get(localId);
            if (state) state.cancelled = true;
            cancelBtn.disabled = true;
            try { await api('archive_job_cancel', { job_id: jobId }, 'POST'); } catch (_) {}
        });
    }
    while (true) {
        const job = await api('archive_job_status', { job_id: jobId }, 'GET');
        updateArchiveJobItem(localId, job);
        if (job.status === 'cancelled') {
            setTimeout(() => {
                document.getElementById(`archive-job-item-${localId}`)?.remove();
                archiveJobState.items.delete(localId);
                hideArchiveJobsButtonIfIdle();
            }, 1200);
            return { success: false, cancelled: true };
        }
        if (job.status === 'done') {
            setTimeout(() => {
                document.getElementById(`archive-job-item-${localId}`)?.remove();
                archiveJobState.items.delete(localId);
                hideArchiveJobsButtonIfIdle();
            }, 2500);
            return job;
        }
        if (job.status === 'error') throw new Error(job.error || 'Archive job failed');
        await new Promise(resolve => setTimeout(resolve, 900));
    }
}
function getArchiveFormatFromName(path) {
    const name = String(path || '').toLowerCase();
    if (name.endsWith('.tar.gz') || name.endsWith('.tgz')) return 'tar.gz';
    const ext = name.split('.').pop();
    return ['zip', '7z', 'rar', 'tar'].includes(ext) ? ext : 'zip';
}
function replaceArchiveNameExtension(name, format) {
    const ext = format === 'tar.gz' ? 'tar.gz' : format;
    const base = String(name || '').replace(/(\.tar\.gz|\.tgz|\.zip|\.7z|\.tar)$/i, '');
    return `${base || 'archive'}.${ext}`;
}
function generateStrongArchivePassword(length = 18) {
    const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%^&*_-+=';
    const bytes = new Uint32Array(length);
    crypto.getRandomValues(bytes);
    return Array.from(bytes, (value) => chars[value % chars.length]).join('');
}
function downloadTemporaryArchive(url, name) {
    const a = document.createElement('a');
    a.href = url;
    a.download = name || '';
    document.body.appendChild(a);
    a.click();
    a.remove();
}
function showArchiveOptionsModal(defaultName = '') {
    return new Promise((resolve) => {
        const modal = document.createElement('div');
        modal.className = 'modal';
        modal.style.display = 'flex';
        const label = (key, fallback) => {
            const value = typeof t === 'function' ? t(window.i18n || {}, key) : '';
            return value && value !== key ? value : fallback;
        };
        const timestamp = new Date().toISOString().replace(/[:.]/g, '-').slice(0, 19);
        const safeName = escapeHtml(defaultName || `selected_${timestamp}.zip`);
        modal.innerHTML = `
            <div class="modal-content" style="max-width: 460px;">
                <div class="modal-header">
                    <h3>${escapeHtml(label('createArchive', 'Create archive'))}</h3>
                    <button class="icon-btn close-btn" aria-label="${escapeHtml(label('close', 'Close'))}">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                    </button>
                </div>
                <div class="modal-body">
                    <style>
                        .archive-checkbox{display:flex;align-items:center;gap:10px;margin-top:12px;cursor:pointer;user-select:none}
                        .archive-checkbox input{position:absolute;opacity:0;pointer-events:none}
                        .archive-checkbox-box{width:18px;height:18px;border:1px solid var(--border);border-radius:5px;background:var(--panel);display:inline-flex;align-items:center;justify-content:center;color:#fff;transition:all .16s ease}
                        .archive-checkbox input:checked + .archive-checkbox-box{background:var(--accent);border-color:var(--accent)}
                        .archive-checkbox input:checked + .archive-checkbox-box::after{content:"";width:8px;height:4px;border-left:2px solid currentColor;border-bottom:2px solid currentColor;transform:rotate(-45deg);margin-top:-2px}
                        .archive-password-row{display:flex;gap:6px;margin-top:10px}
                        .archive-password-row.hidden{display:none}
                        .archive-password-row input{flex:1;min-width:0}
                        .archive-password-row .icon-btn{width:34px;height:34px;border:1px solid var(--border);border-radius:6px;background:var(--panel);color:var(--fg);display:flex;align-items:center;justify-content:center}
                    </style>
                    <div class="form-group">
                        <label>${escapeHtml(label('fileName', 'File name'))}</label>
                        <input id="archive-options-name" type="text" value="${safeName}" style="width:100%;">
                    </div>
                    <div class="form-group">
                        <label>${escapeHtml(label('archiveType', 'Archive type'))}</label>
                        <select id="archive-options-format" style="width:100%;">
                            <option value="zip">ZIP</option>
                            <option value="7z">7Z</option>
                            <option value="tar">TAR</option>
                            <option value="tar.gz">TAR.GZ</option>
                        </select>
                    </div>
                    <label class="animated-check-label" style="margin-top:12px;">
                        <input id="archive-options-encrypt" type="checkbox" class="animated-checkbox" style="width:32px;height:32px;"/>
                        <span class="animated-checkbox-box" style="width:20px;height:20px;">
                          <svg width="20" height="15" viewBox="0 0 24 18" fill="none"><path d="M2 9L8.5 15.5L22 2" stroke="#fff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </span>
                        <span>${escapeHtml(label('encryptArchive', 'Encrypt archive'))}</span>
                    </label>
                    <div id="archive-password-row" class="archive-password-row hidden">
                        <input id="archive-options-password" type="password" autocomplete="new-password" placeholder="${escapeHtml(label('password', 'Password'))}">
                        <button id="archive-password-toggle" class="icon-btn" type="button" title="${escapeHtml(label('showPassword', 'Show password'))}">
                            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                        <button id="archive-password-generate" class="icon-btn" type="button" title="${escapeHtml(label('suggestPassword', 'Suggest strong password'))}">
                            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 4V2"/><path d="M15 16v-2"/><path d="M8 9h2"/><path d="M20 9h2"/><path d="m17.8 6.2 1.4-1.4"/><path d="m10.8 13.2-1.4 1.4"/><path d="m17.8 11.8 1.4 1.4"/><path d="m10.8 4.8-1.4-1.4"/><path d="M4 20 14 10"/><path d="m5 15 4 4"/></svg>
                        </button>
                    </div>
                </div>
                <div class="modal-footer">
                    <button id="archive-options-cancel" class="btn secondary">${escapeHtml(label('cancel', 'Cancel'))}</button>
                    <button id="archive-options-create" class="btn primary">${escapeHtml(label('create', 'Create'))}</button>
                </div>
            </div>
        `;
        document.body.appendChild(modal);
        const nameInput = modal.querySelector('#archive-options-name');
        const formatSelect = modal.querySelector('#archive-options-format');
        const encryptInput = modal.querySelector('#archive-options-encrypt');
        const passwordInput = modal.querySelector('#archive-options-password');
        const passwordRow = modal.querySelector('#archive-password-row');
        const passwordToggle = modal.querySelector('#archive-password-toggle');
        const passwordGenerate = modal.querySelector('#archive-password-generate');
        if (window.CloudFilenameGuard) window.CloudFilenameGuard.attach(nameInput);
        const syncPassword = () => {
            const canEncrypt = ['zip', '7z'].includes(formatSelect.value);
            encryptInput.disabled = !canEncrypt;
            if (!canEncrypt) encryptInput.checked = false;
            passwordRow.classList.toggle('hidden', !encryptInput.checked || !canEncrypt);
        };
        formatSelect.addEventListener('change', () => {
            nameInput.value = replaceArchiveNameExtension(nameInput.value, formatSelect.value);
            syncPassword();
        });
        encryptInput.addEventListener('change', syncPassword);
        passwordToggle.addEventListener('click', () => {
            const visible = passwordInput.type === 'text';
            passwordInput.type = visible ? 'password' : 'text';
            passwordToggle.title = visible ? label('showPassword', 'Show password') : label('hidePassword', 'Hide password');
            passwordInput.focus();
        });
        passwordGenerate.addEventListener('click', () => {
            passwordInput.value = generateStrongArchivePassword();
            passwordInput.type = 'text';
            passwordInput.focus();
            passwordInput.select();
        });
        const close = (value = null) => {
            modal.remove();
            resolve(value);
        };
        modal.querySelector('.close-btn').addEventListener('click', () => close());
        modal.querySelector('#archive-options-cancel').addEventListener('click', () => close());
        modal.addEventListener('click', (event) => { if (event.target === modal) close(); });
        modal.querySelector('#archive-options-create').addEventListener('click', () => {
            const archiveName = nameInput.value.trim();
            const format = formatSelect.value;
            const password = encryptInput.checked ? passwordInput.value : '';
            if (!archiveName) {
                flashStatus(label('enterFileName', 'Enter file name'));
                nameInput.focus();
                return;
            }
            const nameCheck = window.CloudFilenameGuard ? window.CloudFilenameGuard.validate(archiveName) : { ok: !/[\\/:*?"<>|]/.test(archiveName) };
            if (!nameCheck.ok) {
                flashStatus(label('invalidFolderName', 'Invalid name'));
                nameInput.focus();
                nameInput.select();
                return;
            }
            if (encryptInput.checked && !password) {
                flashStatus(label('passwordRequired', 'Password required'));
                passwordInput.focus();
                return;
            }
            close({ archiveName, format, password });
        });
        syncPassword();
        nameInput.focus();
    });
}
window.showArchiveOptionsModal = showArchiveOptionsModal;
async function downloadSelectedItems() {
    const selectedItems = Array.from(selectionState.selectedItems);
    if (selectedItems.length === 0) {
        flashStatus(t(window.i18n, 'noItemsSelected'));
        return;
    }
    const timestamp = new Date().toISOString().replace(/[:.]/g, '-').slice(0, 19);
    const options = await showArchiveOptionsModal(`selected_${timestamp}.zip`);
    if (!options) return;

    try {
        const response = await api('create_archive', {
            items: JSON.stringify(selectedItems),
            archive_name: options.archiveName,
            format: options.format,
            password: options.password,
            temporary: '1',
            async: '1'
        }, 'POST');
        const result = response.job_id ? await waitForArchiveJob(response.job_id, t(window.i18n, 'creatingArchive')) : response;
        if (result.cancelled) {
            flashStatus(t(window.i18n, 'cancelled') || 'Cancelled');
        } else if (result.success !== false && result.download_url) {
            downloadTemporaryArchive(result.download_url, result.archive_name);
            flashStatus(t(window.i18n, 'archiveCreatedAndDownloading'));
        } else if (result.success !== false && result.archive_path) {
            const archivePath = result.archive_path;
            downloadPath(archivePath);
            flashStatus(t(window.i18n, 'archiveCreatedAndDownloading'));
        } else {
            flashStatus(t(window.i18n, 'archiveCreationFailed') + ': ' + (result.error || 'Unknown error'));
        }
    } catch (err) {
        console.error('Archive creation error:', err);
        flashStatus(t(window.i18n, 'archiveCreationError') + ': ' + err.message);
    }
}
function moveSelectedItems() {
    const selectedItems = Array.from(selectionState.selectedItems);
    if (selectedItems.length === 0) return;
    showMoveDialogForMultiple(selectedItems);
}
function showMoveDialogForMultiple(selectedPaths) {
    const modal = document.createElement('div');
    modal.className = 'modal';
    modal.style.display = 'flex';
    const sourceRoot = getCurrentCloudRoot();
    window.moveDialogSourceRoot = sourceRoot;
    window.selectedMoveFolder = '';
    window.selectedMoveRoot = sourceRoot;
    modal.innerHTML = `
        <div class="modal-content" style="max-width: 500px;">
            <div class="modal-header">
                <h3>${t(i18n, 'moveSelectedItems')}</h3>
                <button class="icon-btn close-btn" aria-label="${escapeHtml(t(i18n, 'close') || 'Close')}">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                </button>
            </div>
            <div class="modal-body">
                <p>${t(i18n, 'movingItemsCount', { count: selectedPaths.length })}</p>
                <div class="folder-selector">
                    <label>${t(i18n, 'selectTargetFolder')}</label>
                    <div class="move-root-toolbar" style="
                        display: flex;
                        flex-wrap: wrap;
                        gap: 8px;
                        padding: 10px;
                        margin-bottom: 10px;
                        background: var(--panel);
                        border-radius: 8px;
                        border: 1px solid var(--border);
                    ">
                        ${buildMoveRootToolbarHtml(sourceRoot)}
                    </div>
                    <div id="move-folder-tree" class="folder-tree" style="
                        border: 1px solid var(--border);
                        border-radius: 4px;
                        padding: 10px;
                        margin: 10px 0;
                        max-height: 300px;
                        overflow-y: auto;
                        background: var(--panel);
                    "></div>
                </div>
                <div class="current-folder" style="margin: 10px 0; padding: 10px; background: var(--panel); border-radius: 4px;">
                    ${t(i18n, 'selectedFolder')} <span id="current-folder-path">/</span>
                </div>
            </div>
            <div class="modal-footer">
                <button id="btn-cancel-move" class="btn secondary">${t(i18n, 'cancel')}</button>
                <button id="btn-confirm-move" class="btn primary">${t(i18n, 'move')}</button>
            </div>
        </div>
    `;
    document.body.appendChild(modal);
    loadFolderTreeForMove(sourceRoot);
    initMoveRootToolbar();
    document.getElementById('btn-cancel-move').addEventListener('click', () => modal.remove());
    document.getElementById('btn-confirm-move').addEventListener('click', () => confirmMultipleMove(selectedPaths, modal));
    modal.querySelector('.close-btn').addEventListener('click', () => modal.remove());
    modal.addEventListener('click', (e) => {
        if (e.target === modal) {
            modal.remove();
        }
    });
}
async function confirmMultipleMove(selectedPaths, modal) {
    const targetDir = window.selectedMoveFolder !== undefined ? window.selectedMoveFolder : '';
    
    // Check if moving to root - show modal confirmation instead of alert
    if (targetDir === '') {
        const confirmModal = await showConfirmModal(
            t(i18n, 'moveToRootConfirmTitleMultiple', { count: selectedPaths.length }),
            t(i18n, 'moveToRootConfirmMultiple', { count: selectedPaths.length })
        );
        if (!confirmModal.confirmed) {
            return;
        }
    }
    
    let successCount = 0;
    let errorCount = 0;
    const movedPairs = []; // [oldPath, newPath]
    for (const sourcePath of selectedPaths) {
        if (sourcePath === targetDir) { errorCount++; continue; }
        if (targetDir.startsWith(sourcePath + '/')) { errorCount++; continue; }
        try {
            await api('move', { source_path: sourcePath, target_dir: targetDir, target_root: window.selectedMoveRoot || 'main', root: window.moveDialogSourceRoot || getCurrentCloudRoot() });
            const newName = sourcePath.split('/').pop();
            const newPath = targetDir ? targetDir + '/' + newName : newName;
            movedPairs.push([sourcePath, newPath, newName]);
            successCount++;
        } catch (err) {
            console.error(`Failed to move ${sourcePath}:`, err);
            errorCount++;
        }
    }
    if (successCount > 0) {
        flashStatus(t(i18n, 'moveSuccessMultiple', { count: successCount }));
        const ops = movedPairs.map(([oldP, newP, newN]) => ({ op: 'move', old_path: oldP, new_path: newP, new_name: newN }));
        await patchTreeCache(ops);
        movedPairs.forEach(([oldP, newP, newN]) => treeNodeRename(oldP, newP, newN));
    }
    if (errorCount > 0) {
        flashStatus(t(i18n, 'moveErrorMultiple', { count: errorCount }));
    }
    modal.remove();
    clearSelection();
    const viewerPath = document.getElementById('viewer').dataset.path;
    if (viewerPath && selectedPaths.includes(viewerPath)) {
        closeViewer();
    }
}
function removeChildPathsFromState(parentPath) {
    const pathsToRemove = [];
    expandedFolders.forEach(path => {
        if (path !== parentPath && path.startsWith(parentPath + '/')) {
            pathsToRemove.push(path);
        }
    });
    pathsToRemove.forEach(path => {
        expandedFolders.delete(path);
    });
}
function hasChildPathsInState(parentPath) {
    for (const path of expandedFolders) {
        if (path !== parentPath && path.startsWith(parentPath + '/')) {
            return true;
        }
    }
    return false;
}
function sortItems(items, sortType) {
    const sorted = [...items];
    const collator = new Intl.Collator(undefined, { 
        sensitivity: 'base',
        numeric: true 
    });
    switch(sortType) {
        case 'name-asc':
            return sorted.sort((a, b) => collator.compare(a.name, b.name));
        case 'name-desc':
            return sorted.sort((a, b) => collator.compare(b.name, a.name));
        case 'date-asc':
            return sorted.sort((a, b) => {
                const dateA = a.mtime || a.modified || a.created || 0;
                const dateB = b.mtime || b.modified || b.created || 0;
                return dateA - dateB;
            });
        case 'date-desc':
            return sorted.sort((a, b) => {
                const dateA = a.mtime || a.modified || a.created || 0;
                const dateB = b.mtime || b.modified || b.created || 0;
                return dateB - dateA;
            });
        case 'size-asc':
            return sorted.sort((a, b) => {
                const sizeA = a.size || (a.type === 'dir' ? 0 : -1);
                const sizeB = b.size || (b.type === 'dir' ? 0 : -1);
                return sizeA - sizeB;
            });
        case 'size-desc':
            return sorted.sort((a, b) => {
                const sizeA = a.size || (a.type === 'dir' ? 0 : -1);
                const sizeB = b.size || (b.type === 'dir' ? 0 : -1);
                return sizeB - sizeA;
            });
        default:
            return sorted;
    }
}
function renderNodes(items, basePath = '', depth = 0) {
    const container = document.createElement('div');
    container.className = 'tree-container';
    
    const filteredItems = items;
    
    let sortedItems = [...filteredItems];
    if (sortState.currentSort) {
        sortedItems = sortItems(filteredItems, sortState.currentSort);
    }

    sortedItems.forEach((item, siblingIndex) => {
        const node = document.createElement('div');
        node.className = 'node';
        if (selectionState.selectedItems.has(item.path)) {
            node.classList.add('selected');
        }
        node.style.paddingLeft = `${depth * 16}px`;
        if (depth > 0) node.style.setProperty('--tree-branch-width', `${depth * 16}px`);
        node.dataset.path = item.path;
        node.dataset.type = item.type;
        node.addEventListener('dragstart', (e) => {
            if (item.type !== 'file' && !(selectionState.active && selectionState.selectedItems.has(item.path))) {
                e.preventDefault();
                return;
            }
            setTreeDragPayload(e, item.path, item.type);
        });
        const toggle = document.createElement('span');
        toggle.className = 'toggle';
        toggle.innerHTML = item.type === 'dir' ? getTreeDirToggleMarkup(false) : getFileIcon(item.path || item.name);
        const label = document.createElement('span');
        label.className = 'label';
        label.textContent = item.name;
        label.title = item.name;
        node.append(toggle, label);
        if (selectionState.active) addCheckboxToNode(node);
        syncNodeDragHandle(node);
        node.setAttribute('tabindex', '0');
        
        const children = document.createElement('div');
        children.className = 'children';
        children.style.display = 'none';
        children.dataset.loaded = 'false';
        
        let clickTimer = null;
        let isDoubleClick = false;
        
        // Modified click handler
        node.addEventListener('click', async (e) => {
            e.stopPropagation();
            if (shouldSuppressMobileActivation()) {
                e.preventDefault();
                return;
            }
            
            // If a checkbox was clicked, do not process further
            if (e.target.classList.contains('selection-checkbox')) {
                return;
            }
            
            // In selection mode - select the item instead of opening
            if (selectionState.active) {
                const checkbox = node.querySelector('.selection-checkbox');
                if (checkbox) {
                    checkbox.checked = !checkbox.checked;
                    handleItemSelection(item.path, item.type, checkbox.checked);
                    if (checkbox.checked) {
                        node.classList.add('selected');
                    } else {
                        node.classList.remove('selected');
                    }
                }
                return;
            }
            
            // If not in selection mode - normal behavior
            if (isDoubleClick) {
                isDoubleClick = false;
                return;
            }
            
            if (item.type === 'file') {
                clickTimer = setTimeout(async () => {
                    if (!isDoubleClick) {
                        if (typeof openFileFromTree === 'function') openFileFromTree(item.path);
                        else openFileInTab(item.path, false);
                    }
                }, 300);
        } else if (item.type === 'dir') {
            if (clickTimer) clearTimeout(clickTimer);
            clickTimer = setTimeout(async () => {
                if (isDoubleClick) { isDoubleClick = false; return; }
                const isOpen = children.style.display !== 'none';
                if (isOpen) {
                    setTreeDirToggleOpen(toggle, false);
                    children.style.display = 'none';
                    syncTreeFolderEndLine(children, false);
                    removeChildPathsFromState(item.path);
                    expandedFolders.delete(item.path);
                } else {
                    setTreeDirToggleOpen(toggle, true);
                    children.style.display = 'block';
                    if (children.dataset.loaded === 'false') {
                        try {
                            const res = await api('list', { path: item.path }, 'GET');
                            children.innerHTML = '';
                            const depth = parseInt(node.style.paddingLeft || '0') / 16 + 1;
                            const childNodes = renderNodes(res.items, item.path, depth);
                            children.appendChild(childNodes);
                            children.dataset.loaded = 'true';
                        } catch (err) {
                            console.error('Failed to load folder:', err);
                            children.innerHTML = `<div style="padding: 8px; color: var(--muted);">Error loading folder</div>`;
                        }
                    }
                    syncTreeFolderEndLine(children, true);
                    expandedFolders.add(item.path);
                }
                clickTimer = null;
                saveFolderState();
            }, 250);
        }
    });

        attachMobileContextMenuTrigger(node, (menuEvent) => {
            showContextMenu(menuEvent, item.path, item.type, item.name);
        });
        
        // Double click handler remains unchanged
        // Find the double click handler in renderNodes (around line 1135)
        node.addEventListener('dblclick', async (e) => {
            e.stopPropagation();
            
            // In selection mode - cancel the double click
            if (selectionState.active) {
                e.preventDefault();
                return;
            }
            
            isDoubleClick = true;
            if (clickTimer) {
                clearTimeout(clickTimer);
                clickTimer = null;
            }
            
            if (item.type === 'file') {
                if (typeof shouldRenameOnTreeDblClick === 'function' && shouldRenameOnTreeDblClick(item.path)) {
                    if (typeof startInlineTreeRename === 'function') startInlineTreeRename(item.path);
                    return;
                }
                if (typeof openFileFromTree === 'function') openFileFromTree(item.path);
                else openFileInTab(item.path, false);
            } else if (item.type === 'dir') {
                if (typeof navigateTreeTo === 'function') navigateTreeTo(item.path);
            }
        });
        
        node.addEventListener('contextmenu', (e) => {
            if (shouldSuppressMobileActivation()) {
                e.preventDefault();
                e.stopPropagation();
                return;
            }
            e.preventDefault();
            showContextMenu(e, item.path, item.type, item.name);
        });
        
        container.appendChild(node);
        container.appendChild(children);
    });
    syncTreeGuideLastMarkers(container, sortedItems);
    return container;
}
async function logPath(path) {
  try {
    await api('log_path', { path: path });
  } catch (err) {
    await logDebug(`Failed to log path ${path}: ${err.message}`);
  }
}
async function logDebug(message) {
  try {
    await api('log_debug', { message: message });
  } catch (err) {
    console.error(`Failed to log debug message: ${err.message}`);
  }
}
function resolveAutoSaveTabId(filePath, tabId = null, editor = null) {
    if (tabId) return tabId;
    if (editor && editor.__tabId) return editor.__tabId;
    if (!filePath) return null;
    const tab = tabsState.tabs.find(t => t.path === filePath);
    return tab ? tab.id : null;
}
function scheduleAutoSave(tabId, filePath, editor) {
    if (!tabId || !filePath || !editor) return;
    clearAutoSaveTimer(tabId);
    const timerId = setTimeout(() => {
        autoSaveFile(editor, filePath, tabId);
    }, AUTO_SAVE_DELAY);
    autoSaveTimers.set(tabId, timerId);
}
function initAutoSave(editor, filePath, tabId = null) {
    if (!editor || !filePath) return;
    const resolvedTabId = resolveAutoSaveTabId(filePath, tabId, editor);
    if (!resolvedTabId) return;
    editor.__tabId = resolvedTabId;
    clearAutoSaveTimer(resolvedTabId);
    editor.on('change', (cm, change) => {
        if (change.origin === 'setValue') return;
        markTabAsModified(resolvedTabId, true);
        const tab = tabsState.tabs.find(t => t.id === resolvedTabId);
        if (tab) {
            cacheTabContent(tab);
        }
        scheduleAutoSave(resolvedTabId, filePath, editor);
    });
    editor.on('blur', () => {
        flushAutoSave(resolvedTabId, editor, filePath);
    });
}
function initMonacoAutoSave(editor, filePath, tabId) {
    if (!editor || !filePath || !tabId) return;
    editor.__tabId = tabId;
    if (editor.__autoSaveChangeDisposable) {
        editor.__autoSaveChangeDisposable.dispose();
    }
    if (editor.__autoSaveBlurDisposable) {
        editor.__autoSaveBlurDisposable.dispose();
    }
    editor.__autoSaveChangeDisposable = editor.onDidChangeModelContent((event) => {
        if (editor.__collabApplyingRemote) return;
        if (event.isFlush) return;
        markTabAsModified(tabId, true);
        const tab = tabsState.tabs.find(t => t.id === tabId);
        if (tab) {
            cacheTabContent(tab);
        }
        if (editor.__aiAgentPreviewActive) {
            clearAutoSaveTimer(tabId);
            return;
        }
        broadcastMonacoCollabChanges(tabId, event.changes || []);
        scheduleMonacoCollabSnapshot(tabId);
        scheduleAutoSave(tabId, filePath, editor);
    });
    editor.__autoSaveBlurDisposable = editor.onDidBlurEditorText(() => {
        flushAutoSave(tabId, editor, filePath);
    });
}
function monacoCollabColorFromClientId(clientId) {
    const palette = ['#ef4444', '#f59e0b', '#10b981', '#3b82f6', '#ec4899', '#14b8a6', '#a855f7', '#f97316'];
    const id = String(clientId || '');
    let hash = 0;
    for (let i = 0; i < id.length; i++) hash = ((hash << 5) - hash) + id.charCodeAt(i);
    return palette[Math.abs(hash) % palette.length];
}
function getMonacoCollabSession(tabId) {
    return monacoCollabSessions.get(tabId) || null;
}
function monacoCollabComputeEditScript(oldText, newText) {
    const oldStr = String(oldText ?? '');
    const newStr = String(newText ?? '');
    let start = 0;
    const minLen = Math.min(oldStr.length, newStr.length);
    while (start < minLen && oldStr[start] === newStr[start]) start++;
    let endOld = oldStr.length;
    let endNew = newStr.length;
    while (endOld > start && endNew > start && oldStr[endOld - 1] === newStr[endNew - 1]) {
        endOld--;
        endNew--;
    }
    return {
        start,
        remove: oldStr.slice(start, endOld),
        insert: newStr.slice(start, endNew)
    };
}
function monacoCollabApplyEditScript(text, script) {
    const src = String(text ?? '');
    return src.slice(0, script.start) + script.insert + src.slice(script.start + script.remove.length);
}
function monacoCollabMergeDocuments(base, local, remote) {
    const baseStr = String(base ?? '');
    const localStr = String(local ?? '');
    const remoteStr = String(remote ?? '');
    if (localStr === remoteStr) return localStr;
    if (localStr === baseStr) return remoteStr;
    if (remoteStr === baseStr) return localStr;
    const localEdit = monacoCollabComputeEditScript(baseStr, localStr);
    const remoteEdit = monacoCollabComputeEditScript(baseStr, remoteStr);
    if (localEdit.start === remoteEdit.start && localEdit.remove === remoteEdit.remove) {
        const insert = localEdit.insert.length >= remoteEdit.insert.length ? localEdit.insert : remoteEdit.insert;
        return monacoCollabApplyEditScript(baseStr, { start: localEdit.start, remove: localEdit.remove, insert });
    }
    const ordered = localEdit.start <= remoteEdit.start
        ? [localEdit, remoteEdit]
        : [remoteEdit, localEdit];
    let merged = baseStr;
    let offset = 0;
    ordered.forEach((edit) => {
        const start = edit.start + offset;
        merged = merged.slice(0, start) + edit.insert + merged.slice(start + edit.remove.length);
        offset += edit.insert.length - edit.remove.length;
    });
    return merged;
}
function pushMonacoCollabSnapshot(tabId, content, force = false) {
    const session = getMonacoCollabSession(tabId);
    if (!session) return;
    const editor = editorsMap.get(tabId) || window.monacoManager?.getEditor?.(tabId);
    if (editor?.__collabApplyingRemote) return;
    const nextContent = typeof content === 'string' ? content : (editor?.getValue?.() ?? session.lastSyncedContent ?? '');
    if (!force && nextContent === session.lastPushedContent) return;
    session.docRevision = (session.docRevision || 0) + 1;
    session.lastPushedContent = nextContent;
    if (!editor?.__collabApplyingRemote) {
        session.lastSyncedContent = nextContent;
    }
    sendMonacoCollabSocketMessage({
        type: 'content_snapshot',
        roomKey: session.roomKey,
        path: session.filePath,
        root: session.rootKey,
        clientId: monacoCollabSocketState.clientId,
        content: nextContent,
        revision: session.docRevision,
        updatedAt: Date.now()
    });
}
function isMonacoCollabEligiblePath(filePath) {
    if (!filePath || typeof isTextFile !== 'function') return false;
    return isTextFile(filePath);
}
function getMonacoCollabRoomKey(filePath, rootKey) {
    return `${rootKey || getCurrentCloudRoot()}::${filePath || ''}`;
}
function getMonacoCollabSocketUrl() {
    const protocol = window.location.protocol === 'https:' ? 'wss:' : 'ws:';
    const configured = window.APP_CONFIG?.collabWsUrl;
    if (configured) {
        try {
            const parsed = new URL(configured, window.location.origin);
            parsed.protocol = protocol;
            return parsed.href.replace(/\/$/, '');
        } catch (_) {}
    }
    return `${protocol}//${window.location.host}/cloud-collab-ws`;
}
function flushMonacoCollabSocketQueue() {
    const socket = monacoCollabSocketState.socket;
    if (!socket || socket.readyState !== WebSocket.OPEN) return;
    while (monacoCollabSocketState.pendingMessages.length) {
        socket.send(JSON.stringify(monacoCollabSocketState.pendingMessages.shift()));
    }
}
function sendMonacoCollabSocketMessage(message) {
    monacoCollabSocketState.pendingMessages.push(message);
    flushMonacoCollabSocketQueue();
}
function monacoCollabApplyMergedContent(tabId, editor, session, mergedContent) {
    const current = editor.getValue();
    if (mergedContent === current) {
        session.lastSyncedContent = current;
        return;
    }
    if (window.monacoManager?.replaceContentPreservingView) {
        window.monacoManager.replaceContentPreservingView(tabId, mergedContent, 'collab-sync');
    } else {
        const viewState = window.monacoManager?.captureEditorViewState?.(editor);
        editor.setValue(mergedContent);
        window.monacoManager?.restoreEditorViewState?.(editor, viewState);
    }
    session.lastSyncedContent = mergedContent;
    const tab = tabsState.tabs.find((item) => item.id === tabId);
    if (tab) {
        tab.content = mergedContent;
        cacheTabContent(tab);
    }
}
function applyMonacoCollabRemoteContent(tabId, session, payload) {
    const editor = editorsMap.get(tabId) || window.monacoManager?.getEditor?.(tabId);
    if (!editor || editor.__collabApplyingRemote) return;
    const remoteColor = payload.color || monacoCollabColorFromClientId(payload.clientId);
    if (payload.clientId) monacoCollabSocketState.remoteClientColors.set(payload.clientId, remoteColor);
    const incomingRevision = Number(payload.revision) || 0;
    try {
        editor.__collabApplyingRemote = true;
        const localContent = editor.getValue();
        const baseContent = session.lastSyncedContent ?? '';
        if (typeof payload.content === 'string') {
            const remoteContent = payload.content;
            if (incomingRevision > 0 &&
                incomingRevision === session.lastServerRevision &&
                remoteContent === session.lastRemoteContent &&
                remoteContent === localContent) {
                return;
            }
            if (remoteContent !== localContent) {
                const merged = monacoCollabMergeDocuments(baseContent, localContent, remoteContent);
                monacoCollabApplyMergedContent(tabId, editor, session, merged);
            } else {
                session.lastSyncedContent = localContent;
            }
            if (incomingRevision > 0) {
                session.lastServerRevision = incomingRevision;
                session.lastRemoteRevision = incomingRevision;
                session.lastRemoteContent = remoteContent;
            }
        } else if (Array.isArray(payload.changes) && payload.changes.length > 0) {
            const monacoApi = window.monaco;
            if (!monacoApi?.Range) {
                requestMonacoCollabSync(tabId);
                return;
            }
            const edits = payload.changes.map((change) => ({
                range: new monacoApi.Range(
                    change.range.startLineNumber,
                    change.range.startColumn,
                    change.range.endLineNumber,
                    change.range.endColumn
                ),
                text: change.text,
                forceMoveMarkers: true
            }));
            const hadMutation = payload.changes.some((change) => {
                const text = String(change.text ?? '');
                const range = change.range || {};
                return text.length > 0 ||
                    range.startLineNumber !== range.endLineNumber ||
                    range.startColumn !== range.endColumn;
            });
            if (window.monacoManager?.applyRemoteCollabEdits) {
                window.monacoManager.applyRemoteCollabEdits(editor, edits);
            } else {
                const scrollTop = editor.getScrollTop();
                const scrollLeft = editor.getScrollLeft();
                const savedSelections = editor.getSelections();
                editor.pushUndoStop();
                editor.executeEdits('collab-ws', edits, () => savedSelections);
                editor.setScrollTop(scrollTop);
                editor.setScrollLeft(scrollLeft);
            }
            const afterContent = editor.getValue();
            if (hadMutation && afterContent === localContent) {
                requestMonacoCollabSync(tabId);
            } else {
                session.lastSyncedContent = afterContent;
                const tab = tabsState.tabs.find((item) => item.id === tabId);
                if (tab) tab.content = afterContent;
            }
            const insertChanges = payload.changes.filter((change) => change.text);
            if (insertChanges.length) {
                window.monacoManager?.animateRemoteCollabInserts?.(editor, tabId, payload.clientId, insertChanges, remoteColor);
            }
        }
        markTabAsModified(tabId, false);
    } catch (error) {
        console.error('Failed to apply realtime Monaco update:', error);
        requestMonacoCollabSync(tabId);
    } finally {
        queueMicrotask(() => {
            editor.__collabApplyingRemote = false;
        });
    }
}
function scheduleMonacoCollabSnapshot(tabId) {
    const session = getMonacoCollabSession(tabId);
    if (!session) return;
    if (session.snapshotTimer) clearTimeout(session.snapshotTimer);
    session.snapshotTimer = setTimeout(() => {
        session.snapshotTimer = null;
        pushMonacoCollabSnapshot(tabId);
    }, MONACO_COLLAB_SNAPSHOT_MS);
}
function requestMonacoCollabSync(tabId) {
    const session = getMonacoCollabSession(tabId);
    if (!session) return;
    sendMonacoCollabSocketMessage({
        type: 'sync_request',
        roomKey: session.roomKey,
        path: session.filePath,
        root: session.rootKey,
        clientId: monacoCollabSocketState.clientId
    });
}
function handleMonacoCollabSocketMessage(payload) {
    if (!payload || !payload.type) return;
    if (payload.type === 'sync' || payload.type === 'content_snapshot') {
        if (payload.clientId === monacoCollabSocketState.clientId) return;
        monacoCollabSessions.forEach((session, tabId) => {
            if (session.roomKey !== payload.roomKey) return;
            applyMonacoCollabRemoteContent(tabId, session, payload);
        });
        return;
    }
    if (payload.clientId === monacoCollabSocketState.clientId) return;
    if (payload.type === 'content') {
        monacoCollabSessions.forEach((session, tabId) => {
            if (session.roomKey !== payload.roomKey) return;
            applyMonacoCollabRemoteContent(tabId, session, payload);
        });
        return;
    }
    if (payload.type === 'cursor') {
        const remoteColor = payload.color || monacoCollabColorFromClientId(payload.clientId);
        if (payload.clientId) monacoCollabSocketState.remoteClientColors.set(payload.clientId, remoteColor);
        monacoCollabSessions.forEach((session, tabId) => {
            if (session.roomKey !== payload.roomKey) return;
            window.monacoManager?.setRemoteCursor?.(tabId, payload.clientId, {
                position: payload.position,
                color: remoteColor,
                name: payload.name
            });
        });
        return;
    }
    if (payload.type === 'leave') {
        monacoCollabSessions.forEach((session, tabId) => {
            if (session.roomKey !== payload.roomKey) return;
            window.monacoManager?.clearRemoteCursor?.(tabId, payload.clientId);
        });
    }
}
function ensureMonacoCollabSocket() {
    const existing = monacoCollabSocketState.socket;
    if (existing && (existing.readyState === WebSocket.OPEN || existing.readyState === WebSocket.CONNECTING)) {
        return;
    }
    if (existing && existing.readyState !== WebSocket.CLOSED) {
        try { existing.close(); } catch (_) {}
        monacoCollabSocketState.socket = null;
    }
    try {
        const socket = new WebSocket(getMonacoCollabSocketUrl());
        monacoCollabSocketState.socket = socket;
        socket.addEventListener('open', () => {
            const isReconnect = monacoCollabSocketState.wasConnected;
            monacoCollabSocketState.wasConnected = true;
            monacoCollabSessions.forEach((session, tabId) => {
                session.lastServerRevision = 0;
                session.lastRemoteRevision = 0;
                session.lastRemoteContent = '';
                const editor = editorsMap.get(tabId);
                const joinContent = editor?.getValue?.() ?? session.lastSyncedContent ?? '';
                sendMonacoCollabSocketMessage({
                    type: 'join',
                    roomKey: session.roomKey,
                    path: session.filePath,
                    root: session.rootKey,
                    clientId: monacoCollabSocketState.clientId,
                    name: currentUserState.user || 'user',
                    color: session.color,
                    content: joinContent,
                    revision: session.docRevision || 0,
                    updatedAt: Date.now()
                });
                if (isReconnect) {
                    if (session.syncRequestTimer) clearTimeout(session.syncRequestTimer);
                    session.syncRequestTimer = setTimeout(() => {
                        session.syncRequestTimer = null;
                        requestMonacoCollabSync(tabId);
                    }, MONACO_COLLAB_SYNC_REQUEST_MS);
                }
            });
            flushMonacoCollabSocketQueue();
        });
        socket.addEventListener('message', (event) => {
            try {
                handleMonacoCollabSocketMessage(JSON.parse(event.data));
            } catch (error) {
                console.error('Invalid Monaco collab socket payload:', error);
            }
        });
        socket.addEventListener('close', () => {
            if (monacoCollabSocketState.socket !== socket) return;
            monacoCollabSocketState.socket = null;
            if (monacoCollabSocketState.reconnectTimer) clearTimeout(monacoCollabSocketState.reconnectTimer);
            monacoCollabSocketState.reconnectTimer = setTimeout(() => ensureMonacoCollabSocket(), 1500);
        });
        socket.addEventListener('error', () => {
            if (monacoCollabSocketState.socket !== socket) return;
            try { socket.close(); } catch (_) {}
        });
    } catch (error) {
        console.error('Failed to connect Monaco collab socket:', error);
    }
}
function broadcastMonacoCollabChanges(tabId, changes) {
    const session = getMonacoCollabSession(tabId);
    if (!session || !Array.isArray(changes) || changes.length === 0) return;
    ensureMonacoCollabSocket();
    sendMonacoCollabSocketMessage({
        type: 'content',
        roomKey: session.roomKey,
        path: session.filePath,
        root: session.rootKey,
        clientId: monacoCollabSocketState.clientId,
        changes: changes.map((change) => ({
            text: change.text,
            range: {
                startLineNumber: change.range.startLineNumber,
                startColumn: change.range.startColumn,
                endLineNumber: change.range.endLineNumber,
                endColumn: change.range.endColumn
            }
        }))
    });
}
function broadcastMonacoCollabCursor(tabId, position) {
    const session = getMonacoCollabSession(tabId);
    if (!session || !position) return;
    const now = Date.now();
    if (session.lastCursorSentAt && (now - session.lastCursorSentAt) < MONACO_COLLAB_CURSOR_MS) return;
    session.lastCursorSentAt = now;
    ensureMonacoCollabSocket();
    sendMonacoCollabSocketMessage({
        type: 'cursor',
        roomKey: session.roomKey,
        path: session.filePath,
        root: session.rootKey,
        clientId: monacoCollabSocketState.clientId,
        name: currentUserState.user || 'user',
        color: session.color,
        position: {
            lineNumber: position.lineNumber,
            column: position.column
        }
    });
}
function stopMonacoCollabSession(tabId) {
    const session = monacoCollabSessions.get(tabId);
    if (!session) return;
    if (session.cursorDisposable?.dispose) session.cursorDisposable.dispose();
    if (session.snapshotTimer) clearTimeout(session.snapshotTimer);
    if (session.syncRequestTimer) clearTimeout(session.syncRequestTimer);
    window.monacoManager?.clearAllRemoteCursors?.(tabId);
    sendMonacoCollabSocketMessage({
        type: 'leave',
        roomKey: session.roomKey,
        clientId: monacoCollabSocketState.clientId
    });
    monacoCollabSessions.delete(tabId);
}
function startMonacoCollabSession(tabId, filePath, rootKey, initialContent = '', initialRevision = '') {
    if (!tabId || !filePath || !isMonacoCollabEligiblePath(filePath)) return;
    stopMonacoCollabSession(tabId);
    const colorPalette = ['#ef4444', '#f59e0b', '#10b981', '#3b82f6', '#ec4899', '#14b8a6'];
    const session = {
        tabId,
        filePath,
        rootKey: rootKey || getCurrentCloudRoot(),
        roomKey: getMonacoCollabRoomKey(filePath, rootKey || getCurrentCloudRoot()),
        revision: initialRevision || '',
        lastSyncedContent: String(initialContent ?? ''),
        isPushing: false,
        isPolling: false,
        pendingPush: false,
        pollTimer: null,
        pushTimer: null,
        lastCursorSentAt: 0,
        snapshotTimer: null,
        syncRequestTimer: null,
        docRevision: 0,
        lastServerRevision: 0,
        lastRemoteRevision: 0,
        lastRemoteContent: String(initialContent ?? ''),
        lastPushedContent: String(initialContent ?? ''),
        color: colorPalette[(tabId - 1) % colorPalette.length]
    };
    monacoCollabSessions.set(tabId, session);
    if (window.cloudGroups?.logGroupFileOpen) window.cloudGroups.logGroupFileOpen(filePath);
    const editor = editorsMap.get(tabId);
    if (editor?.onDidChangeCursorPosition) {
        session.cursorDisposable = editor.onDidChangeCursorPosition((event) => {
            if (!event?.position || editor.__collabApplyingRemote) return;
            const recoverReason = window.monaco?.editor?.CursorChangeReason?.RecoverFromMarkers;
            if (recoverReason !== undefined && event.reason === recoverReason) return;
            broadcastMonacoCollabCursor(tabId, event.position);
        });
    }
    ensureMonacoCollabSocket();
    const joinContent = editor?.getValue?.() ?? initialContent ?? '';
    sendMonacoCollabSocketMessage({
        type: 'join',
        roomKey: session.roomKey,
        path: session.filePath,
        root: session.rootKey,
        clientId: monacoCollabSocketState.clientId,
        name: currentUserState.user || 'user',
        color: session.color,
        content: joinContent,
        revision: session.docRevision || 0,
        updatedAt: Date.now()
    });
    session.lastSyncedContent = joinContent;
    session.lastPushedContent = joinContent;
    pushMonacoCollabSnapshot(tabId, joinContent, true);
    if (session.syncRequestTimer) clearTimeout(session.syncRequestTimer);
    session.syncRequestTimer = setTimeout(() => {
        session.syncRequestTimer = null;
        requestMonacoCollabSync(tabId);
    }, MONACO_COLLAB_SYNC_REQUEST_MS);
}
async function autoSaveFile(editor, filePath, tabId = null) {
    if (!filePath) return;
    const resolvedTabId = resolveAutoSaveTabId(filePath, tabId, editor);
    let content = null;
    if (resolvedTabId) {
        content = getCurrentEditorContent(resolvedTabId);
    }
    if ((content === null || content === undefined || content === '') && editor && editor.getValue) {
        content = editor.getValue();
    }
    if ((content === null || content === undefined) && resolvedTabId) {
        const tab = tabsState.tabs.find(t => t.id === resolvedTabId);
        if (tab) {
            content = tab.content || '';
        }
    }
    if (content === null || content === undefined) return;
    const resolvedTab = resolvedTabId ? tabsState.tabs.find(t => t.id === resolvedTabId) : null;
    try {
        await api('save_file', { path: filePath, content, root: getTabRoot(resolvedTab) });
        const collabSession = resolvedTabId ? getMonacoCollabSession(resolvedTabId) : null;
        if (collabSession) {
            collabSession.lastSyncedContent = content;
            collabSession.lastPushedContent = content;
            pushMonacoCollabSnapshot(resolvedTabId, content, true);
        }
        if (resolvedTabId) {
            markTabAsModified(resolvedTabId, false);
            const tab = tabsState.tabs.find(t => t.id === resolvedTabId);
            if (tab) {
                tab.content = content;
            }
            saveTabsState();
        }
        flashStatus(t(i18n, 'autoSaved'));
    } catch (err) {
        flashStatus(t(i18n, 'autoSaveFailed') + err.message);
    } finally {
        if (resolvedTabId) {
            clearAutoSaveTimer(resolvedTabId);
        }
    }
}
function isMobileDevice() {
    return /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent) || 
           window.innerWidth <= 768;
}
function clearAutoSaveTimer(tabId = null) {
    if (tabId === null || tabId === undefined) {
        autoSaveTimers.forEach((timerId) => clearTimeout(timerId));
        autoSaveTimers.clear();
        return;
    }
    const timerId = autoSaveTimers.get(tabId);
    if (timerId) {
        clearTimeout(timerId);
        autoSaveTimers.delete(tabId);
    }
}
function flushAutoSave(tabId, editor = null, filePath = null) {
    const timerId = autoSaveTimers.get(tabId);
    if (!timerId) return;
    clearTimeout(timerId);
    autoSaveTimers.delete(tabId);
    const tab = tabsState.tabs.find(t => t.id === tabId);
    const resolvedPath = filePath || (tab ? tab.path : null);
    if (!resolvedPath) return;
    autoSaveFile(editor, resolvedPath, tabId);
}
async function flushPendingAutoSaves() {
    const pendingTabIds = Array.from(autoSaveTimers.keys());
    pendingTabIds.forEach(tabId => clearAutoSaveTimer(tabId));
    await Promise.allSettled(
        pendingTabIds.map(tabId => {
            const tab = tabsState.tabs.find(t => t.id === tabId);
            if (!tab) return Promise.resolve();
            return autoSaveFile(editorsMap.get(tabId) || null, tab.path, tabId);
        })
    );
}
function flushPendingAutoSavesSync() {
    const pendingTabIds = Array.from(autoSaveTimers.keys());
    pendingTabIds.forEach(tabId => {
        const tab = tabsState.tabs.find(t => t.id === tabId);
        if (!tab) {
            clearAutoSaveTimer(tabId);
            return;
        }
        const content = getCurrentEditorContent(tabId);
        try {
            const xhr = new XMLHttpRequest();
            xhr.open('POST', 'api.php', false);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.send(new URLSearchParams({
                action: 'save_file',
                path: tab.path,
                content: content,
                root: getResolvedTabRoot(tab)
            }));
            markTabAsModified(tabId, false);
        } catch (err) {
            console.error('Synchronous auto-save failed:', err);
        } finally {
            clearAutoSaveTimer(tabId);
        }
    });
}

/* Moved to assets/app/file-viewers.js, media-player.js, model-viewer-3d.js */

function handleTabKey(textarea, shiftPressed) {
  const start = textarea.selectionStart;
  const end = textarea.selectionEnd;
  const value = textarea.value;
  if (shiftPressed) {
    const beforeSelection = value.substring(0, start);
    const afterSelection = value.substring(end);
    const selection = value.substring(start, end);
    const unindentedSelection = selection.split('\n').map(line => {
      if (line.startsWith('  ')) {
        return line.substring(2);
      } else if (line.startsWith('\t')) {
        return line.substring(1);
      }
      return line;
    }).join('\n');
    const newValue = beforeSelection + unindentedSelection + afterSelection;
    const newCursorPos = start;
    textarea.value = newValue;
    textarea.selectionStart = newCursorPos;
    textarea.selectionEnd = newCursorPos + unindentedSelection.length;
  } else {
    if (start === end) {
      const newValue = value.substring(0, start) + '  ' + value.substring(end);
      textarea.value = newValue;
      textarea.selectionStart = textarea.selectionEnd = start + 2;
    } else {
      const beforeSelection = value.substring(0, start);
      const afterSelection = value.substring(end);
      const selection = value.substring(start, end);
      const indentedSelection = selection.split('\n').map(line => '  ' + line).join('\n');
      const newValue = beforeSelection + indentedSelection + afterSelection;
      const newCursorPos = start;
      textarea.value = newValue;
      textarea.selectionStart = newCursorPos;
      textarea.selectionEnd = newCursorPos + indentedSelection.length;
    }
  }
  textarea.dispatchEvent(new Event('input'));
}
function toggleComment(textarea, fileExt) {
  const start = textarea.selectionStart;
  const end = textarea.selectionEnd;
  const value = textarea.value;
  const commentStyle = getCommentStyle(fileExt);
  const beforeSelection = value.substring(0, start);
  const afterSelection = value.substring(end);
  const selection = value.substring(start, end);
  const lines = selection.split('\n');
  const allLinesCommented = lines.every(line => {
    const trimmed = line.trim();
    return trimmed.startsWith(commentStyle.line) || trimmed === '';
  });
  let newSelection;
  let newCursorAdjustment = 0;
  if (allLinesCommented) {
    newSelection = lines.map(line => {
      const trimmed = line.trim();
      if (trimmed.startsWith(commentStyle.line)) {
        return line.replace(commentStyle.line, '');
      }
      return line;
    }).join('\n');
    newCursorAdjustment = -commentStyle.line.length;
  } else {
    newSelection = lines.map(line => {
      if (line.trim() === '') return line;
      return commentStyle.line + line;
    }).join('\n');
    newCursorAdjustment = commentStyle.line.length;
  }
  const newValue = beforeSelection + newSelection + afterSelection;
  textarea.value = newValue;
  if (selection.includes('\n')) {
    textarea.selectionStart = start;
    textarea.selectionEnd = end + (newCursorAdjustment * lines.length);
  } else {
    textarea.selectionStart = start;
    textarea.selectionEnd = end + newCursorAdjustment;
  }
  textarea.dispatchEvent(new Event('input'));
}
function getCommentStyle(fileExt) {
    const styles = {
        js: { 
            line: '// ', 
            block: { start: '/*', end: '*/' } 
        },
        ts: { 
            line: '// ', 
            block: { start: '/*', end: '*/' } 
        },
        java: { 
            line: '// ', 
            block: { start: '/*', end: '*/' } 
        },
        c: { 
            line: '// ', 
            block: { start: '/*', end: '*/' } 
        },
        cpp: { 
            line: '// ', 
            block: { start: '/*', end: '*/' } 
        },
        cs: { 
            line: '// ', 
            block: { start: '/*', end: '*/' } 
        },
        php: { 
            line: '// ', 
            block: { start: '/*', end: '*/' } 
        },
        css: { 
            line: '/* ', 
            block: { start: '/*', end: '*/' } 
        },
        scss: { 
            line: '// ', 
            block: { start: '/*', end: '*/' } 
        },
        less: { 
            line: '// ', 
            block: { start: '/*', end: '*/' } 
        },
        py: { 
            line: '# ', 
            block: { start: "'''", end: "'''" } 
        },
        rb: { 
            line: '# ', 
            block: { start: '=begin', end: '=end' } 
        },
        html: { 
            line: '<!-- ', 
            block: { start: '<!--', end: '-->' } 
        },
        xml: { 
            line: '<!-- ', 
            block: { start: '<!--', end: '-->' } 
        },
        sql: { 
            line: '-- ', 
            block: { start: '/*', end: '*/' } 
        },
        sh: { 
            line: '# ', 
            block: { start: ': <<\'EOF\'', end: 'EOF' } 
        },
        yml: { 
            line: '# ', 
            block: null 
        },
        yaml: { 
            line: '# ', 
            block: null 
        },
        md: { 
            line: '<!-- ', 
            block: { start: '<!--', end: '-->' } 
        },
    };
    return styles[fileExt] || { line: '// ', block: { start: '/*', end: '*/' } };
}
function deletePreviousWord(textarea) {
  const start = textarea.selectionStart;
  const value = textarea.value;
  if (start === 0) return;
  let wordStart = start - 1;
  while (wordStart > 0 && /\s/.test(value[wordStart - 1])) {
    wordStart--;
  }
  while (wordStart > 0 && !/\s/.test(value[wordStart - 1])) {
    wordStart--;
  }
  const newValue = value.substring(0, wordStart) + value.substring(start);
  textarea.value = newValue;
  textarea.selectionStart = textarea.selectionEnd = wordStart;
  textarea.dispatchEvent(new Event('input'));
}
function deleteLine(textarea) {
  const start = textarea.selectionStart;
  const value = textarea.value;
  const lines = value.split('\n');
  let lineStart = 0;
  let lineIndex = 0;
  for (let i = 0; i < lines.length; i++) {
    const lineEnd = lineStart + lines[i].length;
    if (start >= lineStart && start <= lineEnd) {
      lineIndex = i;
      break;
    }
    lineStart = lineEnd + 1;
  }
  lines.splice(lineIndex, 1);
  const newValue = lines.join('\n');
  textarea.value = newValue;
  lineStart = 0;
  for (let i = 0; i < Math.min(lineIndex, lines.length); i++) {
    lineStart += lines[i].length + 1;
  }
  textarea.selectionStart = textarea.selectionEnd = Math.min(lineStart, newValue.length);
  textarea.dispatchEvent(new Event('input'));
}
function selectLine(textarea) {
  const start = textarea.selectionStart;
  const value = textarea.value;
  const lines = value.split('\n');
  let lineStart = 0;
  let lineIndex = 0;
  for (let i = 0; i < lines.length; i++) {
    const lineEnd = lineStart + lines[i].length;
    if (start >= lineStart && start <= lineEnd) {
      lineIndex = i;
      break;
    }
    lineStart = lineEnd + 1;
  }
  const lineEnd = lineStart + lines[lineIndex].length;
  textarea.selectionStart = lineStart;
  textarea.selectionEnd = lineEnd;
}
function selectAllOccurrences(textarea) {
  const selectedText = textarea.value.substring(textarea.selectionStart, textarea.selectionEnd);
  if (!selectedText) return;
  const searchStart = textarea.selectionEnd;
  const nextIndex = textarea.value.indexOf(selectedText, searchStart);
  if (nextIndex !== -1) {
    textarea.selectionStart = nextIndex;
    textarea.selectionEnd = nextIndex + selectedText.length;
  }
}
function indentSelection(textarea, increase) {
  handleTabKey(textarea, !increase);
}
function insertLineBelow(textarea) {
  const start = textarea.selectionStart;
  const value = textarea.value;
  const afterCursor = value.substring(start);
  const nextNewline = afterCursor.indexOf('\n');
  const insertPos = nextNewline === -1 ? value.length : start + nextNewline + 1;
  const newValue = value.substring(0, insertPos) + '\n' + value.substring(insertPos);
  textarea.value = newValue;
  textarea.selectionStart = textarea.selectionEnd = insertPos + 1;
  textarea.dispatchEvent(new Event('input'));
}
function insertLineAbove(textarea) {
  const start = textarea.selectionStart;
  const value = textarea.value;
  const beforeCursor = value.substring(0, start);
  const lastNewline = beforeCursor.lastIndexOf('\n');
  const insertPos = lastNewline === -1 ? 0 : lastNewline + 1;
  const newValue = value.substring(0, insertPos) + '\n' + value.substring(insertPos);
  textarea.value = newValue;
  textarea.selectionStart = textarea.selectionEnd = insertPos;
  textarea.dispatchEvent(new Event('input'));
}
function handleAutoClosing(textarea, e) {
  if (!e.data || e.data.length !== 1) return;
  const pairs = {
    '(': ')',
    '[': ']',
    '{': '}',
    '"': '"',
    "'": "'",
    '`': '`'
  };
  const char = e.data;
  if (pairs[char]) {
    const start = textarea.selectionStart;
    const value = textarea.value;
    const newValue = value.substring(0, start) + pairs[char] + value.substring(start);
    textarea.value = newValue;
    textarea.selectionStart = textarea.selectionEnd = start;
  }
}
async function saveFile() {
    const activeTab = tabsState.tabs.find(tab => tab.id === tabsState.activeTabId);
    if (!activeTab) {
        flashStatus(t(i18n, 'noFileToSave'));
        return false;
    }

    const path = activeTab.path;
    let content = '';
    const viewer = document.getElementById('viewer');
    const collabSession = getMonacoCollabSession(activeTab.id);

    if (editorsMap.has(activeTab.id)) {
        content = editorsMap.get(activeTab.id).getValue();
    } else if (window.monacoManager) {
        content = window.monacoManager.getContent(activeTab.id);
    } else {
        const textarea = viewer.querySelector(`.file-content-container[data-tab-id="${activeTab.id}"] textarea`);
        if (textarea) content = textarea.value;
    }

    if (content === '' && activeTab.content) {
        content = activeTab.content;
    }

    if (!editorsMap.has(activeTab.id) && content === '' && !activeTab.content) {
        flashStatus(t(i18n, 'noContentToSave'));
        return false;
    }

    try {
        await api('save_file', { path, content, root: getTabRoot(activeTab) });
        if (collabSession) {
            collabSession.lastSyncedContent = content;
            collabSession.lastPushedContent = content;
            pushMonacoCollabSnapshot(activeTab.id, content, true);
        }
        flashStatus(t(i18n, 'fileSaved'));
        markTabAsModified(activeTab.id, false);
        activeTab.content = content;
        saveTabsState();
        return true;
    } catch (err) {
        flashStatus(t(i18n, 'saveFailed') + err.message);
        console.error('Save error:', err);
        return false;
    }
}
function clearAllCache() {
    contentCache.clear();
}
function getCacheStats() {
    return {
        size: contentCache.size,
        keys: Array.from(contentCache.keys())
    };
}
async function reloadFileInTab(tab, editMode = null) {
    const finalEditMode = editMode !== null ? editMode : tab.editMode;
    tab.editMode = finalEditMode;
    await openFile(tab.path, finalEditMode, tab.content);
    renderTabs();
    saveTabsState();
}
function guessLangClass(ext) {
  const map = {
    js:'language-javascript', ts:'language-typescript', json:'language-json',
    html:'language-xml', htm:'language-xml', xml:'language-xml',
    css:'language-css', scss:'language-scss', less:'language-less',
    php:'language-php', py:'language-python', rb:'language-ruby', go:'language-go',
    java:'language-java', cs:'language-cs', cpp:'language-cpp', c:'language-c',
    sh:'language-bash', md:'language-markdown', yml:'language-yaml', yaml:'language-yaml',
    sql:'language-sql', kt:'language-kotlin', swift:'language-swift', rs:'language-rust',
    lua:'language-lua', pl:'language-perl', r:'language-r', toml:'language-toml',
    ini:'language-ini', dockerfile:'language-dockerfile', vue:'language-javascript'
  };
  return map[ext] || '';
}
function closeViewer() {
    clearAutoSaveTimer();
    document.getElementById('file-view').classList.remove('hidden');
    const viewer = document.getElementById('viewer');
    const allIframes = viewer.querySelectorAll('iframe');
    allIframes.forEach(frame => {
        frame.style.display = 'none';
    });
    viewer.dataset.path = '';
    viewer.dataset.editor = '';
    viewer.dataset.latexEditor = 'false';
    viewer.dataset.photoEditor = 'false';
    viewer.cmEditor = null;
    viewer.monacoEditor = null;
    document.getElementById('file-name').textContent = '';
    document.getElementById('btn-save').classList.add('hidden');
    document.getElementById('btn-monaco-undo').classList.add('hidden');
    document.getElementById('btn-monaco-redo').classList.add('hidden');

    const hasTabs = tabsState.tabs.length > 0;
    document.body.classList.toggle('tabs-open', hasTabs);
    document.body.classList.toggle('tabs-empty', !hasTabs);
    renderViewerEmptyState();
}
async function copyViewer(path) {
  try {
    const resp = await api('open', { path }, 'GET');
    if (!resp.binary && resp.content) {
      if (navigator.clipboard && window.isSecureContext) {
        await navigator.clipboard.writeText(resp.content);
      } else {
        fallbackCopyTextToClipboard(resp.content);
      }
      flashStatus(t(i18n, 'fileCopiedToClipboard'));
      return true;
    } else {
      flashStatus(t(i18n, 'cannotCopyBinaryFile'));
      return false;
    }
  } catch (err) {
    flashStatus(t(i18n, 'copyFailed') + ': ' + err.message);
    return false;
  }
}
function fallbackCopyTextToClipboard(text) {
  const textArea = document.createElement('textarea');
  textArea.value = text;
  textArea.style.position = 'fixed';
  textArea.style.top = '0';
  textArea.style.left = '0';
  textArea.style.opacity = '0';
  textArea.style.pointerEvents = 'none';
  document.body.appendChild(textArea);
  textArea.focus();
  textArea.select();
  try {
    const successful = document.execCommand('copy');
    if (!successful) {
      throw new Error('Fallback copy method failed');
    }
  } catch (err) {
    throw new Error('Copy to clipboard failed: ' + err);
  } finally {
    document.body.removeChild(textArea);
  }
}
async function downloadCurrent() {
  const path = document.getElementById('viewer').dataset.path;
  if (!path) return false;
  return await downloadPath(path);
}
function isHtmlFile(path) {
  return /\.(html|htm)$/i.test(path || '');
}
function renderHtmlFilePreview(container, path, tabId) {
  if (window.location.protocol !== 'https:') {
    const warning = document.createElement('div');
    warning.setAttribute('data-tab-id', tabId);
    warning.style.cssText = `
      position: absolute;
      inset: 0;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 24px;
      text-align: center;
      background: var(--panel);
      color: var(--fg);
    `;
    warning.innerHTML = `
      <div style="max-width: 560px;">
        <h3 style="margin:0 0 12px;">HTML preview disabled on HTTP</h3>
        <p style="margin:0;color:var(--muted);line-height:1.5;">
          Live HTML iframe preview is blocked on insecure HTTP pages to avoid password-field and embedded-auth security risks.
          Open this app over HTTPS to re-enable live preview.
        </p>
      </div>
    `;
    container.style.position = 'relative';
    container.appendChild(warning);
    return;
  }
  const root = getActiveTabRoot();
  const iframe = document.createElement('iframe');
  iframe.setAttribute('data-tab-id', tabId);
  iframe.className = 'html-file-preview';
  iframe.src = `api.php?action=raw&path=${encodeURIComponent(path)}&root=${root}`;
  iframe.sandbox = 'allow-forms allow-modals allow-popups allow-scripts';
  iframe.style.cssText = `
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    border: none;
    background: #fff;
  `;
  container.style.position = 'relative';
  container.appendChild(iframe);
}
function formatBytes(bytes) {
  if (bytes < 1024) return bytes + ' B';
  const units = ['KB','MB','GB','TB'];
  let i= -1;
  do { bytes = bytes/1024; i++; } while (bytes >=1024 && i<units.length-1);
  return bytes.toFixed(1) + ' ' + units[i];
}
async function api(action, params={}, method='POST') {
  const scopeExcluded = new Set(['ai_chat','get_state','set_quota','set_session_limit','set_hide_lang_switcher','get_user_role','get_storage_usage','refresh_storage_usage','get_sync_log','get_file_tree','invalidate_file_tree','patch_file_tree','get_folder_state','set_folder_state','get_encryption_notice_status','set_encryption_notice_status','groups_list','groups_members','groups_history','groups_leave','groups_lookup_user','group_chat_messages','group_chat_unread','group_chat_mark_read','group_chat_send','group_chat_delete','group_chat_edit','group_chat_media']);
  const scopedParams = { ...(params || {}) };
  const activeRoot = getCurrentCloudRoot();
  if (!scopeExcluded.has(action) && !('root' in scopedParams)) scopedParams.root = activeRoot;
  const url = new URL(window.APP_CONFIG.apiBase, window.location.href);
  if (action === 'ai_chat') {
        const url = window.APP_CONFIG.aiApiBase || 'ai.php';
        const body = new URLSearchParams({ action: 'chat', ...scopedParams });
        const res = await fetch(url, { 
            method: 'POST', 
            headers: {'Content-Type': 'application/x-www-form-urlencoded'}, 
            body 
        });
        const data = await res.json();
        if (!res.ok || data.error) throw new Error(data.error || `AI API error: ${res.status}`);
        return data;
    }
  if (method === 'GET') {
    url.searchParams.set('action', action);
    url.searchParams.set('v', String(Date.now()));
    Object.entries(scopedParams||{}).forEach(([k,v]) => {
      if (v !== undefined && v !== null) {
        url.searchParams.set(k, v);
      }
    });
    const res = await fetch(url, { cache: 'no-store' });
    if (!res.ok) throw new Error(`API error: ${res.status} ${res.statusText}`);
    const text = await res.text();
    if (!res.headers.get('content-type')?.includes('application/json')) return text;
    try {
      return text ? JSON.parse(text) : {};
    } catch (_) {
      throw new Error(`API error: ${res.status} ${res.statusText}`);
    }
  } else {
    const body = new URLSearchParams({ action, ...scopedParams });
    const res = await fetch(url, { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded','X-Device-Id': ensureCloudDeviceId()}, body });
    const text = await res.text();
    let data;
    try {
      data = text ? JSON.parse(text) : {};
    } catch (_) {
      const preview = text.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim().slice(0, 180);
      throw new Error(preview || `API error: ${res.status} ${res.statusText}`);
    }
    if (!res.ok || data.error) throw new Error(data.error || `API error: ${res.status} ${res.statusText}`);
    if (USER_USAGE_REFRESH_ACTIONS.has(action)) {
      scheduleUserUsageStatusRefresh();
    }
    return data;
  }
}
async function maybeShowCloudEncryptionNotice(i18n) {
  try {
    const data = await api('get_encryption_notice_status');
    if (!data || !data.ok || !data.should_prompt) return;
    showCloudEncryptionNoticeModal(i18n);
  } catch (err) {
    console.warn('Cloud encryption notice check failed:', err);
  }
}
function showCloudEncryptionNoticeModal(i18n) {
  if (document.getElementById('cloud-encryption-notice-modal')) return;
  const title = (i18n && i18n.cloudEncryptionNoticeTitle) || 'Encryption notice';
  const message = (i18n && i18n.cloudEncryptionNoticeMessage) || 'Encryption data is stored in cloud storage inside the Documents folder. Please confirm that you understand this before continuing.';
  const buttonLabel = (i18n && i18n.cloudEncryptionNoticeConfirm) || 'Understood';
  const warningLabel = (i18n && i18n.warning) || 'Warning';

  const modal = document.createElement('div');
  modal.id = 'cloud-encryption-notice-modal';
  modal.className = 'modal';
  modal.style.display = 'flex';
  modal.innerHTML = `
    <div class="modal-content" style="
      max-width: 580px;
      width: min(580px, calc(100vw - 32px));
      padding: 0;
      overflow: hidden;
      border: 1px solid rgba(245, 158, 11, 0.28);
      background:
        linear-gradient(180deg, rgba(255,255,255,0.10), rgba(255,255,255,0.03)),
        color-mix(in srgb, var(--panel, #111827) 86%, transparent);
      backdrop-filter: blur(18px) saturate(140%);
      box-shadow:
        0 24px 80px rgba(0,0,0,0.38),
        0 0 0 1px rgba(245, 158, 11, 0.08) inset;
      border-radius: 22px;
    ">
      <div class="modal-header" style="
        border-bottom: 1px solid rgba(245, 158, 11, 0.16);
        background:
          radial-gradient(circle at top left, rgba(245,158,11,0.22), transparent 48%),
          linear-gradient(180deg, rgba(245,158,11,0.10), rgba(245,158,11,0.04));
        padding: 22px 24px 18px;
      ">
        <div style="display:flex;align-items:flex-start;gap:14px;">
          <div style="
            width:44px;height:44px;flex:0 0 44px;
            display:flex;align-items:center;justify-content:center;
            border-radius:14px;
            background:rgba(245, 158, 11, 0.16);
            color:#f59e0b;
            box-shadow:0 0 0 1px rgba(245,158,11,0.16) inset;
          ">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
              <line x1="12" y1="9" x2="12" y2="13"></line>
              <line x1="12" y1="17" x2="12.01" y2="17"></line>
            </svg>
          </div>
          <div style="min-width:0;">
            <div style="
              display:inline-flex;align-items:center;
              font-size:11px;font-weight:700;letter-spacing:.08em;
              text-transform:uppercase;
              color:#f59e0b;
              background:rgba(245, 158, 11, 0.12);
              border:1px solid rgba(245, 158, 11, 0.18);
              border-radius:999px;
              padding:5px 10px;
              margin-bottom:10px;
            ">${escapeHtml(warningLabel)}</div>
            <h3 style="margin:0;font-size:22px;line-height:1.2;font-weight:700;">${escapeHtml(title)}</h3>
          </div>
        </div>
      </div>
      <div class="modal-body" style="padding:22px 24px 18px;">
        <p style="margin:0 0 14px;line-height:1.7;font-size:14px;opacity:.94;">${escapeHtml(message)}</p>
        <div style="
          display:flex;
          gap:12px;
          align-items:flex-start;
          padding:14px 15px;
          border-radius:14px;
          background:rgba(245, 158, 11, 0.09);
          border:1px solid rgba(245, 158, 11, 0.15);
        ">
          <div style="color:#f59e0b;flex:0 0 auto;margin-top:1px;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <circle cx="12" cy="12" r="10"></circle>
              <line x1="12" y1="8" x2="12" y2="12"></line>
              <line x1="12" y1="16" x2="12.01" y2="16"></line>
            </svg>
          </div>
          <div style="font-size:13px;line-height:1.65;opacity:.92;">
            Cloud encryption-related data is stored in the <strong>Documents</strong> folder of your cloud space.
            Consent is recorded only after explicit confirmation.
          </div>
        </div>
      </div>
      <div class="modal-footer" style="
        justify-content:flex-end;
        padding:0 24px 24px;
        border-top:none;
      ">
        <button type="button" class="btn btn-primary" id="cloud-encryption-notice-confirm" style="
          min-width: 170px;
          border: none;
          border-radius: 12px;
          padding: 12px 18px;
          font-weight: 700;
          background: linear-gradient(180deg, #f59e0b, #d97706);
          color: #fff;
          box-shadow: 0 12px 24px rgba(217, 119, 6, 0.26);
        ">${escapeHtml(buttonLabel)}</button>
      </div>
    </div>
  `;
  document.body.appendChild(modal);

  const finalize = async () => {
    try {
      await api('set_encryption_notice_status');
      modal.remove();
    } catch (err) {
      flashStatus((((i18n && i18n.errorPrefix) || 'Error: ')) + err.message);
    }
  };
  modal.querySelector('#cloud-encryption-notice-confirm')?.addEventListener('click', finalize);
}
async function updateQuota() {
  const mb = parseInt(document.getElementById('quota-mb').value || '0', 10);
  if (mb <= 0) {
    flashStatus(t(i18n, 'invalidQuota'));
    return;
  }
  try {
    await api('set_quota', { quota_mb: mb }); // without name - the server will figure out the current user
    flashStatus(t(i18n, 'quotaUpdated'));
    await refreshUsers(i18n);
  } catch (err) {
    flashStatus(t(i18n, 'errorPrefix') + err.message);
  }
}
async function updateSessionLimit() {
  const mb = parseInt(document.getElementById('session-limit-mb').value || '0', 10);
  try {
    await api('set_session_limit', { session_limit_mb: mb }); // without name
    flashStatus(t(i18n, 'sessionLimitUpdated'));
    await refreshUsers(i18n);
  } catch (err) {
    flashStatus(t(i18n, 'errorPrefix') + err.message);
  }
}
async function updateLangSwitcherVisibilitySetting() {
  const hideLangToggle = document.getElementById('hide-lang-switcher-toggle');
  if (!hideLangToggle) return;
  const hideLangSwitcher = hideLangToggle.checked ? 1 : 0;
  localStorage.setItem(HIDE_LANG_SWITCHER_CACHE_KEY, hideLangSwitcher ? '1' : '0');
  applyLangSwitcherVisibility(!!hideLangSwitcher);
  try {
    await api('set_hide_lang_switcher', { hide_lang_switcher: hideLangSwitcher });
  } catch (err) {
    hideLangToggle.checked = !hideLangToggle.checked;
    localStorage.setItem(HIDE_LANG_SWITCHER_CACHE_KEY, hideLangToggle.checked ? '1' : '0');
    applyLangSwitcherVisibility(!!hideLangToggle.checked);
    flashStatus(t(i18n, 'errorPrefix') + err.message);
  }
}
function showTabContextMenu(e, tabId) {
    hideTabPreview();
    e.preventDefault();
    e.stopPropagation();
    const tab = tabsState.tabs.find(t => t.id === tabId);
    if (!tab) return;
    const existing = document.getElementById('context-menu');
    if (existing) existing.remove();
    document.querySelectorAll('.context-menu-submenu').forEach((el) => el.remove());
    const menu = document.createElement('div');
    menu.id = 'context-menu';
    menu.className = 'context-menu';
    menu.style.position = 'fixed';
    menu.style.zIndex = '1000';
    menu.style.visibility = 'hidden';
    menu.style.opacity = '0';
    const path = tab.path;
    const type = 'file';
    const hideEdit = tab.markdownEditor || tab.htmlEditor || tab.latexEditor || tab.photoEditor ||
        tab.transcriptionEditor || tab.onlyOfficeEditor || tab.ttsEditor || tab.zipViewer ||
        (typeof shouldHideEditMenuOption === 'function' && shouldHideEditMenuOption(path));
    const options = typeof buildFileContextMenuOptions === 'function'
        ? buildFileContextMenuOptions(path, type, { includeSelect: false, hideEdit, tabId, tabTitle: tab.title })
        : [];
    if (options.length === 0) {
        options.push({ label: t(window.i18n, 'noActions'), action: () => {} });
    }
    const renderMenu = typeof renderContextMenuOptions === 'function' ? renderContextMenuOptions : null;
    if (renderMenu) {
        renderMenu(menu, options);
    } else {
        options.forEach(opt => {
            const item = document.createElement('div');
            item.className = 'context-menu-item';
            item.innerHTML = `${getContextMenuIconSvg(opt.icon)}<span class="context-menu-item-label">${escapeHtml(opt.label || '')}</span>`;
            item.addEventListener('click', (e) => { e.stopPropagation(); opt.action(); menu.remove(); });
            menu.appendChild(item);
        });
    }
    document.body.appendChild(menu);
    adjustContextMenuPosition(menu, e.clientX, e.clientY);
    menu.style.visibility = 'visible';
    menu.style.opacity = '1';
    const removeMenu = () => {
        if (removeMenu._done) return;
        removeMenu._done = true;
        menu._hideContextSubmenus?.();
        if (typeof closeAllContextMenus === 'function') closeAllContextMenus();
        else {
            document.querySelectorAll('.context-menu-submenu').forEach((el) => el.remove());
            menu.remove();
        }
        document.removeEventListener('click', closeMenu);
        document.removeEventListener('keydown', closeOnEscape);
        document.removeEventListener('mousemove', closeOnDistance);
    };
    if (typeof attachContextMenuDismiss === 'function') attachContextMenuDismiss(removeMenu);
    const closeMenu = (event) => {
        const isUi = typeof isContextMenuUiElement === 'function'
            ? isContextMenuUiElement(event.target)
            : (menu.contains(event.target) || event.target.closest?.('.context-menu-submenu'));
        if (!isUi) removeMenu();
    };
    const closeOnEscape = (event) => {
        if (event.key === 'Escape') removeMenu();
    };
    const closeOnDistance = (event) => {
        const r = menu.getBoundingClientRect();
        const dx = Math.max(r.left - event.clientX, 0, event.clientX - r.right);
        const dy = Math.max(r.top - event.clientY, 0, event.clientY - r.bottom);
        if (Math.sqrt(dx * dx + dy * dy) > 150) removeMenu();
    };
    setTimeout(() => {
        document.addEventListener('click', closeMenu);
        document.addEventListener('keydown', closeOnEscape);
        if (window.matchMedia('(hover: hover)').matches) {
            document.addEventListener('mousemove', closeOnDistance);
        }
    }, 10);
}
function refreshTabContent(tabId) {
    const tab = tabsState.tabs.find(t => t.id === tabId);
    if (!tab) return;
    const savedRoot = getTabRoot(tab);
    const prevRoot = getCurrentCloudRoot();
    setCurrentCloudRoot(savedRoot);
    closeTab(tabId);
    if (tab.photoEditor) {
        openPhotoEditor(tab.path);
    } else if (tab.latexEditor) {
        openLatexEditor(tab.path);
    } else if (tab.transcriptionEditor) {
        openTranscriptionEditor(tab.path);
    } else if (tab.ttsEditor) {
        openTextToSpeechEditor(tab.path);
    } else if (tab.onlyOfficeEditor) {
        openOnlyOfficeEditor(tab.path);
    } else if (tab.markdownEditor) {
        openMarkdownEditor(tab.path);
    } else if (tab.htmlEditor) {
        openHtmlEditor(tab.path);
    } else if (tab.zipViewer) {
        const newTab = {
            id: tabsState.nextTabId++,
            path: tab.path,
            editMode: false,
            zipViewer: true,
            root: savedRoot,
            title: tab.title,
            modified: false,
            content: null,
            fullPath: tab.fullPath
        };
        if (tabsState.activeTabId) {
            const activeIndex = tabsState.tabs.findIndex(t => t.id === tabsState.activeTabId);
            if (activeIndex !== -1) {
                tabsState.tabs.splice(activeIndex + 1, 0, newTab);
            } else {
                tabsState.tabs.push(newTab);
            }
        } else {
            tabsState.tabs.push(newTab);
        }
        tabsState.activeTabId = newTab.id;
        renderTabs();
        loadZipViewerToTab(newTab);
        saveTabsState();
    } else {
        openFileInTab(tab.path, tab.editMode);
    }
    const freshTab = tabsState.tabs.find(t => t.id === tabsState.activeTabId);
    if (freshTab && !freshTab.root) freshTab.root = savedRoot;
    setCurrentCloudRoot(prevRoot);
    flashStatus(t(i18n, 'tabRefreshed', { name: tab.title }));
}
function closeOnEscape(e) {
    if (e.key !== 'Escape') return;
    const viewer = document.getElementById('viewer');
    const filePath = viewer.dataset.path;
    const isAudio = filePath && isEditableAudio(filePath);
    const activeNode = document.querySelector('.node.active');
    const isTreeAudio = activeNode && activeNode.dataset.type === 'file' && isEditableAudio(activeNode.dataset.path);
    if (isAudio || isTreeAudio) {
        document.querySelectorAll('select').forEach(select => {
            if (select === document.activeElement) {
                select.blur();
            }
        });
        document.querySelectorAll('.context-menu').forEach(menu => {
            menu.remove();
        });
        document.querySelectorAll('.context-menu').forEach(menu => {
            document.removeEventListener('click', menu.closeMenu);
            document.removeEventListener('keydown', menu.closeOnEscape);
        });
    }
}
async function closeOtherTabs(keepTabId) {
    const tabsToClose = tabsState.tabs.filter(tab => tab.id !== keepTabId);
    const hasModifiedTabs = tabsToClose.some(tab => tab.modified);
    if (hasModifiedTabs) {
        const result = await showConfirmModal(
            t(i18n, 'unsavedChanges'),
            t(i18n, 'closeOtherModifiedTabs')
        );
        if (!result.confirmed) {
            return;
        }
    }
    tabsState.tabs = tabsState.tabs.filter(tab => tab.id === keepTabId);
    if (!tabsState.tabs.some(tab => tab.id === tabsState.activeTabId)) {
        if (tabsState.tabs.length > 0) {
            tabsState.activeTabId = tabsState.tabs[0].id;
            restoreTabContent(tabsState.tabs[0]);
        } else {
            tabsState.activeTabId = null;
            closeViewer();
        }
    }
    renderTabs();
    saveTabsState();
    flashStatus(t(i18n, 'otherTabsClosed'));
}


async function uploadFile(path, file) {
  // Guard: path must be a string
  if (typeof path === 'object' && path !== null) path = path.path || '';
  path = String(path || '');
  const fileName = file.name.toLowerCase();
  const forbiddenExtensions = [
    '.exe', '.bat', '.cmd', '.sh', '.php', '.phtml', '.html', '.htm',
    '.js', '.jar', '.war', '.apk', '.dmg', '.app', '.scr', '.com'
  ];
  const hasForbiddenExtension = forbiddenExtensions.some(ext =>
    fileName.endsWith(ext)
  );
  if (hasForbiddenExtension) {
    showUploadWarningDialog(file.name, forbiddenExtensions);
    return;
  }
  const uploadRoot = getCurrentCloudRoot();
  const fd = new FormData();
  fd.append('action', 'upload');
  fd.append('path', path);
  fd.append('root', uploadRoot);
  fd.append('file', file, file.name);
  try {
    const res = await fetch('api.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (!res.ok || data.error) {
      flashStatus(t(i18n, 'uploadError') + (data.error ? ': ' + data.error : ''));
    } else {
      flashStatus(t(i18n, 'uploaded', { name: data.name || file.name }));
      const now = Math.floor(Date.now() / 1000);
      const uploadedPath = path;
      const uploadedName = uploadedPath.split('/').pop();
      const newItem = {
        name: uploadedName,
        path: uploadedPath,
        type: 'file',
        size: file.size,
        mtime: now
      };
      await insertTreeItem(newItem, uploadRoot);
      scheduleUserUsageStatusRefresh();

      // 3. If the parent folder is collapsed - show a notification
      const parentPath = uploadedPath.includes('/')
        ? uploadedPath.substring(0, uploadedPath.lastIndexOf('/'))
        : '';

      if (parentPath && !expandedFolders.has(parentPath)) {
        flashStatus(t(i18n, 'fileUploadedHidden', { name: uploadedName, folder: parentPath }));
      }

      // 4. Update the selection state (if active)
      if (selectionState.active) {
        const node = document.querySelector(`.node[data-path="${uploadedPath}"]`);
        if (node && !node.querySelector('.selection-checkbox')) {
          addCheckboxToNode(node);
        }
      }
    }
  } catch (err) {
    flashStatus(t(i18n, 'uploadError'));
  }
}
function showUploadWarningDialog(fileName, forbiddenExtensions) {
  const modal = document.createElement('div');
  modal.className = 'modal';
  modal.style.display = 'flex';
  const extensionsList = forbiddenExtensions.map(ext => 
    `<span class="forbidden-ext">${ext}</span>`
  ).join(', ');
  modal.innerHTML = `
    <div class="modal-content" style="max-width: 500px;">
      <div class="modal-header">
        <h3 style="color: #e74c3c;">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 8px;">
            <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
            <line x1="12" y1="9" x2="12" y2="13"></line>
            <line x1="12" y1="17" x2="12.01" y2="17"></line>
          </svg>
          ${t(i18n, 'securityWarning')}
        </h3>
        <button class="icon-btn close-btn" aria-label="${escapeHtml(t(i18n, 'close') || 'Close')}">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                </button>
      </div>
      <div class="modal-body">
        <div style="margin-bottom: 15px;">
          <p><strong>${t(i18n, 'fileBlocked')}: ${fileName}</strong></p>
          <p>${t(i18n, 'securityReason')}</p>
        </div>
        <div style="background: var(--panel); padding: 15px; border-radius: 4px; margin-bottom: 15px;">
          <h4 style="margin-top: 0; margin-bottom: 10px; color: var(--muted);">
            ${t(i18n, 'blockedExtensions')}:
          </h4>
          <div style="display: flex; flex-wrap: wrap; gap: 5px;">
            ${extensionsList}
          </div>
        </div>
        <div style="background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 4px; padding: 12px; color: #856404;">
          <strong>${t(i18n, 'recommendation')}:</strong> 
          ${t(i18n, 'uploadRecommendation')}
        </div>
      </div>
      <div class="modal-footer">
        <button id="btn-understand-upload" class="btn primary">
          ${t(i18n, 'understand')}
        </button>
      </div>
    </div>
  `;
  document.body.appendChild(modal);
  const style = document.createElement('style');
  style.textContent = `
    .forbidden-ext {
      background: #e74c3c;
      color: white;
      padding: 2px 6px;
      border-radius: 3px;
      font-size: 12px;
      font-family: monospace;
    }
  `;
  document.head.appendChild(style);
  modal.querySelector('.close-btn').addEventListener('click', () => modal.remove());
  modal.querySelector('#btn-understand-upload').addEventListener('click', () => modal.remove());
  modal.addEventListener('click', (e) => {
    if (e.target === modal) modal.remove();
  });
}


/* Moved to assets/app/file-actions.js */


/* Moved to assets/app/downloads-and-account.js */

function getTopModal() {
    const modals = Array.from(document.querySelectorAll('.modal'));
    for (let i = modals.length - 1; i >= 0; i--) {
        const m = modals[i];
        if (m.id === 'ai-modal' || m.id === 'upload-modal') continue;
        if (getComputedStyle(m).display !== 'none') return m;
    }
    return null;
}
function initModalKeyboardNav() {
    document.addEventListener('keydown', (e) => {
        if (e.key !== 'Escape' && e.key !== 'ArrowLeft' && e.key !== 'ArrowRight' && e.key !== 'Enter') return;
        const modal = getTopModal();
        if (!modal) return;
        if (e.key === 'Escape') {
            const btn =
                modal.querySelector('[id*="cancel"]:not([disabled])') ||
                modal.querySelector('[class*="btn-cancel"]:not([disabled])') ||
                modal.querySelector('[id^="btn-close-"]:not([disabled])') ||
                modal.querySelector('.close-btn');
            if (btn) { e.preventDefault(); btn.click(); }
            return;
        }
        const tag = (document.activeElement?.tagName || '').toUpperCase();
        const itype = (document.activeElement?.type || '').toLowerCase();
        if ((tag === 'INPUT' && itype !== 'button' && itype !== 'checkbox' && itype !== 'radio') || tag === 'TEXTAREA' || tag === 'SELECT') return;
        const footer = modal.querySelector('.modal-footer');
        const btns = Array.from((footer || modal).querySelectorAll('button')).filter(b => !b.classList.contains('close-btn') && !b.classList.contains('icon-btn') && getComputedStyle(b).display !== 'none');
        if (!btns.length) return;
        if (e.key === 'ArrowLeft' || e.key === 'ArrowRight') {
            e.preventDefault();
            const idx = btns.findIndex(b => b === document.activeElement);
            const next = idx === -1
                ? (e.key === 'ArrowRight' ? 0 : btns.length - 1)
                : (e.key === 'ArrowRight' ? (idx + 1) % btns.length : (idx - 1 + btns.length) % btns.length);
            btns[next].focus();
            return;
        }
        if (e.key === 'Enter' && !btns.includes(document.activeElement)) {
            const primary = (footer || modal).querySelector('.btn.primary:not([disabled]), .btn-ok-confirm:not([disabled])');
            if (primary) { e.preventDefault(); primary.click(); }
        }
    });
}
