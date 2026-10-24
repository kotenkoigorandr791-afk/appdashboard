function guardFilenameInput(input) {
  if (window.CloudFilenameGuard) window.CloudFilenameGuard.attach(input);
}
function validateCloudFilename(name, input) {
  const result = window.CloudFilenameGuard ? window.CloudFilenameGuard.validate(name) : { ok: !/[\\/:*?"<>|]/.test(String(name || '')) };
  if (result.ok) return true;
  flashStatus(t(i18n, result.code === 'empty' ? 'enterNewName' : 'invalidFolderName'));
  input?.focus();
  input?.select();
  return false;
}
function newFolder(basePath) {
  // Guard: ensure basePath is always a string
  if (basePath !== null && typeof basePath === 'object') basePath = basePath.path || '';
let targetPath = (typeof basePath === 'string') ? basePath : '';
let targetRoot = getCurrentCloudRoot();
const pathRoot = typeof getRootFromPath === 'function' ? getRootFromPath(targetPath) : 'main';
if (pathRoot !== 'main') {
targetRoot = pathRoot;
targetPath = typeof fromMainTreePath === 'function' ? fromMainTreePath(targetPath, targetRoot) : targetPath;
}
newFolderState.basePath = targetPath;
newFolderState.baseRoot = targetRoot;
  const modal = document.getElementById('new-folder-modal');
  const folderNameInput = document.getElementById('new-folder-name');
  guardFilenameInput(folderNameInput);
  folderNameInput.value = '';
  translateModalElements(modal);
  modal.classList.remove('hidden');
  folderNameInput.focus();
}
function createNewFolder() {
  const folderNameInput = document.getElementById('new-folder-name');
  const name = folderNameInput.value.trim();
  if (!name) {
    flashStatus(t(i18n, 'enterFolderName'));
    folderNameInput.focus();
    return;
  }
  if (!validateCloudFilename(name, folderNameInput)) return;
  const path = newFolderState.basePath ? `${String(newFolderState.basePath)}/${name}` : name;
  document.getElementById('new-folder-modal').classList.add('hidden');
const mkdirRoot = newFolderState.baseRoot || getCurrentCloudRoot();
  api('mkdir', { path, root: mkdirRoot }).then(async () => {
    const now = Math.floor(Date.now() / 1000);
    const newItem = { name, path, type: 'dir', size: null, mtime: now };
    await insertTreeItem(newItem, mkdirRoot);
    flashStatus(t(i18n, 'folderCreated', {name}));
  }).catch(err => {
    if (err.message.includes('exists')) {
      flashStatus(t(i18n, 'folderExists'));
    } else {
      flashStatus(t(i18n, 'errorPrefix') + err.message);
    }
  });
}
function initCreateFolderModal() {
  const newFolderModal = document.getElementById('new-folder-modal');
  const newFolderNameInput = document.getElementById('new-folder-name');
  const btnCancelNewFolder = document.getElementById('btn-cancel-new-folder');
  const btnCreateFolder = document.getElementById('btn-create-folder');
  guardFilenameInput(newFolderNameInput);
  btnCancelNewFolder.addEventListener('click', () => {
    newFolderModal.classList.add('hidden');
  });
  btnCreateFolder.addEventListener('click', createNewFolder);
  newFolderNameInput.addEventListener('keypress', (e) => {
    if (e.key === 'Enter') {
      createNewFolder();
    }
  });
  newFolderModal.addEventListener('click', (e) => {
    if (e.target === newFolderModal) {
      newFolderModal.classList.add('hidden');
    }
  });
  newFolderModal.querySelector('.close-btn').addEventListener('click', () => {
    newFolderModal.classList.add('hidden');
  });
}
function newFile(basePath) {
  // Guard: ensure basePath is always a string
  if (basePath !== null && typeof basePath === 'object') basePath = basePath.path || '';
  let targetPath = (typeof basePath === 'string') ? basePath : '';
  let targetRoot = getCurrentCloudRoot();
  const pathRoot = typeof getRootFromPath === 'function' ? getRootFromPath(targetPath) : 'main';
  if (pathRoot !== 'main') {
    targetRoot = pathRoot;
    targetPath = typeof fromMainTreePath === 'function' ? fromMainTreePath(targetPath, targetRoot) : targetPath;
  }
  newFileState.basePath = targetPath;
  newFileState.baseRoot = targetRoot;
  const modal = document.getElementById('new-file-modal');
  const fileNameInput = document.getElementById('new-file-name');
  const extensionSelect = document.getElementById('file-extension-select');
  const customExtensionInput = document.getElementById('custom-extension-input');
  guardFilenameInput(fileNameInput);
  fileNameInput.value = '';
  customExtensionInput.value = '';
  customExtensionInput.classList.add('hidden');
  translateModalElements(modal);
  modal.classList.remove('hidden');
  fileNameInput.focus();
}
function newVideo(basePath) {
  if (basePath !== null && typeof basePath === 'object') basePath = basePath.path || '';
  const modal = ensureVideoModal();
  modal.dataset.basePath = (typeof basePath === 'string') ? basePath : '';
  modal.querySelector('#video-prompt').value = '';
  modal.querySelector('#video-duration').value = '15';
  modal.querySelector('#video-preset').value = '720x1280';
  modal.querySelector('#video-fps').value = '24';
  modal.querySelector('#video-status').textContent = '';
  modal.querySelector('#video-progress-fill').style.width = '0%';
  updateVideoPreviewMeta(modal);
  renderVideoAssetSelection(modal);
  hydrateVideoModelOptions(modal);
  modal.classList.remove('hidden');
  modal.querySelector('#video-prompt').focus();
}
function ensureVideoModal() {
  let modal = document.getElementById('video-generation-modal');
  if (modal) return modal;
  modal = document.createElement('div');
  modal.id = 'video-generation-modal';
  modal.className = 'modal hidden';
  modal.innerHTML = `
    <div class="modal-content video-modal-content">
      <style>
        #video-generation-modal .video-modal-content{max-width:820px;width:92vw;max-height:90dvh;border-radius:12px;overflow:hidden;display:flex;flex-direction:column}
        #video-generation-modal .video-modal-scroll{flex:1 1 auto;overflow-y:auto;min-height:0;scrollbar-width:thin;scrollbar-color:var(--scrollbar-thumb) var(--scrollbar-track);padding:0 4px}
        #video-generation-modal .video-modal-scroll::-webkit-scrollbar{width:6px}
        #video-generation-modal .video-modal-scroll::-webkit-scrollbar-track{background:var(--scrollbar-track);border-radius:3px}
        #video-generation-modal .video-modal-scroll::-webkit-scrollbar-thumb{background:var(--scrollbar-thumb);border-radius:3px}
        #video-generation-modal .video-modal-head{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:18px 20px;border-bottom:1px solid var(--border);flex-shrink:0}
        #video-generation-modal .video-title{display:flex;align-items:center;gap:10px;min-width:0}
        #video-generation-modal .video-title-icon{width:38px;height:38px;display:grid;place-items:center;border-radius:8px;background:color-mix(in srgb,var(--accent) 14%,var(--panel));color:var(--accent);border:1px solid color-mix(in srgb,var(--accent) 28%,var(--border))}
        #video-generation-modal .video-title h3{margin:0;font-size:18px;line-height:1.2}
        #video-generation-modal .video-title p{margin:2px 0 0;color:var(--muted);font-size:12px}
        #video-generation-modal .video-grid{display:grid;grid-template-columns:minmax(0,1.15fr) 260px;gap:16px;padding:18px 20px}
        #video-generation-modal .video-panel{border:1px solid var(--border);border-radius:8px;background:color-mix(in srgb,var(--panel) 62%,transparent);padding:14px}
        #video-generation-modal .video-prompt-label{display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;font-size:13px;font-weight:700}
        #video-generation-modal #video-prompt{width:100%;min-height:170px;resize:vertical;border:1px solid var(--border);border-radius:8px;background:var(--bg);color:var(--fg);padding:12px;line-height:1.45}
        #video-generation-modal .video-controls{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;margin-top:12px}
        #video-generation-modal .video-field{display:grid;gap:6px;font-size:12px;color:var(--muted);font-weight:700}
        #video-generation-modal .video-field select{width:100%;border:1px solid var(--border);border-radius:8px;background:var(--bg);color:var(--fg);padding:9px}
        #video-generation-modal .video-preview{min-height:230px;display:grid;align-content:space-between;background:linear-gradient(135deg,#111827,#115e59);color:#fff;position:relative;overflow:hidden}
        #video-generation-modal .video-preview:before{content:"";position:absolute;inset:0;background:linear-gradient(120deg,rgba(255,255,255,.16),transparent 44%);pointer-events:none}
        #video-generation-modal .video-preview strong{font-size:24px;line-height:1.05;position:relative}
        #video-generation-modal .video-preview span{font-size:11px;text-transform:uppercase;letter-spacing:.12em;color:#67e8f9;position:relative}
        #video-generation-modal .video-assets-box{max-height:132px;overflow:auto;border:1px solid var(--border);border-radius:8px;padding:8px;background:var(--bg)}
        #video-generation-modal .video-progress-wrap{height:9px;background:var(--bg);border:1px solid var(--border);border-radius:999px;overflow:hidden;margin-top:14px}
        #video-generation-modal #video-progress-fill{height:100%;width:0%;background:linear-gradient(90deg,var(--accent),#22c55e);transition:width .25s ease}
        #video-generation-modal #video-status{margin-top:10px;color:var(--muted);font-size:13px;min-height:18px}
        @media (max-width:760px){#video-generation-modal .video-grid{grid-template-columns:1fr}#video-generation-modal .video-controls{grid-template-columns:1fr}}
      </style>
      <div class="video-modal-head">
        <div class="video-title">
          <div class="video-title-icon">
            <svg width="20" height="20" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round">
              <rect x="3" y="5" width="18" height="14" rx="2"></rect>
              <path d="m10 9 5 3-5 3z"></path>
            </svg>
          </div>
          <div>
            <h3>${t(window.i18n, 'createVideo') || 'Create Video'}</h3>
            <p>AI composition → HyperFrames → MP4</p>
          </div>
        </div>
        <button class="icon-btn close-btn" aria-label="${escapeHtml(t(window.i18n, 'close') || 'Close')}">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                    </button>
      </div>
      <div class="video-modal-scroll">
      <div class="video-grid">
        <div>
          <div class="video-panel">
            <label class="video-prompt-label">
              <span>${t(window.i18n, 'videoPrompt') || 'Prompt'}</span>
              <span>MP4</span>
            </label>
            <textarea id="video-prompt" rows="6" placeholder="${t(window.i18n, 'videoPromptPlaceholder') || 'Describe the video you want to create...'}"></textarea>
            <div class="video-controls">
              <label class="video-field">Format
                <select id="video-preset">
                  <option value="720x1280">Vertical 9:16</option>
                  <option value="1280x720">Landscape 16:9</option>
                  <option value="720x720">Square 1:1</option>
                </select>
              </label>
              <label class="video-field">Duration
                <select id="video-duration">
                  <option value="10">10s</option>
                  <option value="15">15s</option>
                  <option value="30">30s</option>
                  <option value="45">45s</option>
                  <option value="60">60s</option>
                </select>
              </label>
              <label class="video-field">FPS
                <select id="video-fps">
                  <option value="24">24</option>
                </select>
              </label>
              <label class="video-field hidden" id="video-model-field">Model
                <select id="video-model"></select>
              </label>
            </div>
          </div>
          <div class="video-panel" style="margin-top:12px;">
            <label class="video-prompt-label">
              <span>${t(window.i18n, 'videoAssets') || 'Assets'}</span>
            </label>
            <div id="video-assets" class="video-assets-box"></div>
          </div>
        </div>
        <div>
          <div class="video-panel video-preview">
            <span>Preview</span>
            <strong>${t(window.i18n, 'createVideo') || 'Create Video'}</strong>
            <span id="video-preview-meta">720 x 1280 · 15s · 24fps</span>
          </div>
          <div class="video-progress-wrap">
            <div id="video-progress-fill"></div>
          </div>
          <div id="video-status"></div>
        </div>
      </div>
      <div class="modal-footer">
        <button id="btn-cancel-video" class="btn secondary">${t(window.i18n, 'cancel') || 'Cancel'}</button>
        <button id="btn-generate-video" class="btn primary">${t(window.i18n, 'generateVideo') || 'Generate Video'}</button>
      </div>
      </div>
    </div>
  `;
  document.body.appendChild(modal);
  modal.querySelector('.close-btn').addEventListener('click', () => modal.classList.add('hidden'));
  modal.querySelector('#btn-cancel-video').addEventListener('click', () => modal.classList.add('hidden'));
  modal.addEventListener('click', (e) => { if (e.target === modal) modal.classList.add('hidden'); });
  modal.querySelector('#btn-generate-video').addEventListener('click', () => startVideoGeneration(modal));
  ['#video-preset', '#video-duration', '#video-fps', '#video-model'].forEach(selector => modal.querySelector(selector)?.addEventListener('change', () => updateVideoPreviewMeta(modal)));
  return modal;
}
async function hydrateVideoModelOptions(modal) {
  try {
    const options = await api('video_generation_options', {}, 'GET');
    const field = modal.querySelector('#video-model-field');
    const select = modal.querySelector('#video-model');
    if (!options.can_choose_model || !Array.isArray(options.models) || !options.models.length) {
      field.classList.add('hidden');
      select.innerHTML = '';
      return;
    }
    select.innerHTML = options.models.map(model => `<option value="${escapeHtml(model)}"${model === options.default_model ? ' selected' : ''}>${escapeHtml(model)}</option>`).join('');
    field.classList.remove('hidden');
  } catch (_) {}
}
function updateVideoPreviewMeta(modal) {
  const preset = modal.querySelector('#video-preset').value.replace('x', ' x ');
  const duration = modal.querySelector('#video-duration').value + 's';
  const fps = modal.querySelector('#video-fps').value + 'fps';
  modal.querySelector('#video-preview-meta').textContent = `${preset} · ${duration} · ${fps}`;
}
function renderVideoAssetSelection(modal) {
  const wrap = modal.querySelector('#video-assets');
  const items = Array.from(selectionState?.selectedItems || []);
  const fileItems = items.filter(path => {
    const ext = String(path).split('.').pop().toLowerCase();
    return ['png', 'jpg', 'jpeg', 'webp', 'gif', 'svg', 'mp3', 'wav', 'ogg', 'mp4', 'mov', 'webm'].includes(ext);
  });
  if (!fileItems.length) {
    wrap.innerHTML = `<div style="color:var(--muted); font-size:13px;">${t(window.i18n, 'videoNoAssets') || 'Select media files before opening this dialog to use them as assets.'}</div>`;
    return;
  }
  wrap.innerHTML = fileItems.map(path => `
    <label style="display:flex; align-items:center; gap:8px; padding:4px 0;">
      <input type="checkbox" value="${escapeHtml(path)}" checked>
      <span>${escapeHtml(path.split('/').pop())}</span>
    </label>
  `).join('');
}
async function startVideoGeneration(modal) {
  const prompt = modal.querySelector('#video-prompt').value.trim();
  if (!prompt) {
    flashStatus(t(window.i18n, 'videoPromptRequired') || 'Enter a video prompt');
    modal.querySelector('#video-prompt').focus();
    return;
  }
  const [width, height] = modal.querySelector('#video-preset').value.split('x').map(v => parseInt(v, 10));
  const assetIds = Array.from(modal.querySelectorAll('#video-assets input:checked')).map(input => input.value);
  const button = modal.querySelector('#btn-generate-video');
  button.disabled = true;
  try {
    const data = await api('video_generation', {
      folderId: modal.dataset.basePath || '',
      prompt,
      duration: modal.querySelector('#video-duration').value,
      width,
      height,
      fps: modal.querySelector('#video-fps').value,
      model: modal.querySelector('#video-model-field')?.classList.contains('hidden') ? '' : modal.querySelector('#video-model')?.value || '',
      assetIds: JSON.stringify(assetIds)
    });
    pollVideoGeneration(modal, data.jobId);
  } catch (err) {
    button.disabled = false;
    flashStatus(t(window.i18n, 'errorPrefix') + err.message);
  }
}
function pollVideoGeneration(modal, jobId) {
  const statusEl = modal.querySelector('#video-status');
  const fill = modal.querySelector('#video-progress-fill');
  const button = modal.querySelector('#btn-generate-video');
  const labels = {
    queued: 'Queued',
    preparing: 'Preparing assets',
    generating_composition: 'Generating composition',
    rendering: 'Rendering video',
    uploading: 'Uploading to cloud storage',
    completed: 'Completed',
    failed: 'Failed'
  };
  const tick = async () => {
    try {
      const job = await api('video_generation_status', { jobId }, 'GET');
      const progress = Math.max(0, Math.min(100, Number(job.progress || 0)));
      fill.style.width = progress + '%';
      statusEl.textContent = labels[job.status] || job.status || '';
      if (job.status === 'completed') {
        button.disabled = false;
        const path = job.file_path || job.fileId;
        if (path) {
          const now = Math.floor(Date.now() / 1000);
          await insertTreeItem({ name: job.file_name || path.split('/').pop(), path, type: 'file', size: job.size || 0, mtime: now }, getCurrentCloudRoot());
          openFileInTab(path, true);
        }
        flashStatus(t(window.i18n, 'videoCreated') || 'Video created');
        return;
      }
      if (job.status === 'failed') {
        button.disabled = false;
        statusEl.textContent = job.error || labels.failed;
        flashStatus(t(window.i18n, 'errorPrefix') + (job.error || labels.failed));
        return;
      }
      setTimeout(tick, 1500);
    } catch (err) {
      button.disabled = false;
      flashStatus(t(window.i18n, 'errorPrefix') + err.message);
    }
  };
  tick();
}
async function deletePath(path, type = 'file', name = '', root = null) {
    if (path !== null && typeof path === 'object') { root = path.root || root; type = path.type || type; name = path.name || name; path = path.path || ''; }
    path = String(path || '');
    if (!path) return;
    if (!name) {
        name = path.split('/').pop();
    }
    showDeleteModal(path, type, name, root || getCurrentCloudRoot());
    clearSelection(); 
}
let inlineRenameState = null;
function cancelInlineTreeRename() {
  if (!inlineRenameState) return;
  const { node, label, input, originalName } = inlineRenameState;
  if (input && input.parentNode) input.remove();
  if (label) {
    label.textContent = originalName;
    label.classList.remove('hidden');
  }
  if (node) node.classList.remove('tree-renaming');
  inlineRenameState = null;
}
async function commitInlineTreeRename() {
  if (!inlineRenameState) return;
  const { path, input, originalName, node, label } = inlineRenameState;
  const newFullFileName = (input?.value || '').trim();
  if (!newFullFileName) {
    flashStatus(t(i18n, 'enterNewName'));
    input?.focus();
    return;
  }
  if (newFullFileName === originalName) {
    cancelInlineTreeRename();
    return;
  }
  if (!validateCloudFilename(newFullFileName, input)) return;
  const oldPath = path;
  const directory = oldPath.includes('/') ? oldPath.substring(0, oldPath.lastIndexOf('/') + 1) : '';
  const newPath = directory + newFullFileName;
  const cleanupInput = () => {
    if (node) node.classList.remove('tree-renaming');
    if (input?.parentNode) input.remove();
    if (label) label.classList.remove('hidden');
  };
  inlineRenameState = null;
  try {
    await api('rename', { old_path: oldPath, new_name: newFullFileName });
    const currentRoot = getCurrentCloudRoot();
    if (typeof patchTreeCacheForRoot === 'function') {
      await patchTreeCacheForRoot([{ op: 'rename', old_path: oldPath, new_path: newPath, new_name: newFullFileName }], currentRoot);
    } else if (currentRoot === 'main') {
      await patchTreeCache([{ op: 'rename', old_path: oldPath, new_path: newPath, new_name: newFullFileName }]);
    }
    if (currentRoot === 'main') {
      treeNodeRename(oldPath, newPath, newFullFileName);
    } else {
      await buildTree('');
    }
    const viewer = document.getElementById('viewer');
    if (viewer?.dataset?.path === oldPath) {
      const activeTab = tabsState.tabs.find(tab => tab.path === oldPath);
      if (activeTab) {
        activeTab.path = newPath;
        activeTab.title = newFullFileName;
        renderTabs();
        saveTabsState();
      }
    }
    cleanupInput();
    flashStatus(t(i18n, 'fileRenamed'));
  } catch (err) {
    flashStatus(t(i18n, 'errorPrefix') + err.message);
    startInlineTreeRename(oldPath);
  }
}
function startInlineTreeRename(path) {
  if (!path) return;
  if (typeof path === 'object') path = path.path || '';
  if (!path || typeof path !== 'string') return;
  if (inlineRenameState?.path === path) return;
  cancelInlineTreeRename();
  const esc = CSS.escape(path);
  const node = document.querySelector(`#tree .node[data-path="${esc}"]`);
  if (!node) {
    renamePathModal(path);
    return;
  }
  const label = node.querySelector('.label');
  if (!label || label.classList.contains('hidden')) return;
  const originalName = label.textContent || path.split('/').pop();
  const input = document.createElement('input');
  input.type = 'text';
  input.className = 'tree-rename-input';
  input.value = originalName;
  input.setAttribute('aria-label', t(i18n, 'rename') || 'Rename');
  guardFilenameInput(input);
  if (window.CloudTransliteration?.attachToInput) window.CloudTransliteration.attachToInput(input);
  label.classList.add('hidden');
  node.classList.add('tree-renaming');
  node.insertBefore(input, label.nextSibling);
  inlineRenameState = { path, node, label, input, originalName };
  const finish = (commit) => {
    if (!inlineRenameState || inlineRenameState.input !== input) return;
    if (commit) commitInlineTreeRename();
    else cancelInlineTreeRename();
  };
  input.addEventListener('keydown', (e) => {
    e.stopPropagation();
    if (e.key === 'Enter') {
      e.preventDefault();
      finish(true);
    } else if (e.key === 'Escape') {
      e.preventDefault();
      finish(false);
    }
  });
  input.addEventListener('blur', () => finish(true));
  input.addEventListener('click', (e) => e.stopPropagation());
  input.addEventListener('dblclick', (e) => e.stopPropagation());
  requestAnimationFrame(() => {
    input.focus();
    const dot = originalName.lastIndexOf('.');
    if (dot > 0) input.setSelectionRange(0, dot);
    else input.select();
  });
}
function renamePathModal(path) {
  if (!path) return;
  if (typeof path === 'object') path = path.path || '';
  if (!path || typeof path !== 'string') return;
  renameState.oldPath = path;
  const fileName = path.split('/').pop();
  const lastDotIndex = fileName.lastIndexOf('.');
  let nameWithoutExt = fileName;
  let currentExt = '';
  if (lastDotIndex > 0) {
    nameWithoutExt = fileName.substring(0, lastDotIndex);
    currentExt = fileName.substring(lastDotIndex);
    renameState.currentExt = currentExt;
  }
  const modal = document.getElementById('rename-modal');
  const fileNameInput = document.getElementById('rename-file-name');
  const extensionSelect = document.getElementById('rename-extension-select');
  const customExtensionInput = document.getElementById('rename-custom-extension-input');
  const currentFileName = document.getElementById('current-file-name');
  fileNameInput.value = nameWithoutExt;
  extensionSelect.value = 'keep';
  customExtensionInput.value = '';
  customExtensionInput.classList.add('hidden');
  currentFileName.textContent = fileName;
  translateModalElements(modal);
  modal.classList.remove('hidden');
  fileNameInput.focus();
  fileNameInput.select();
}
function renamePath(path) {
  startInlineTreeRename(path);
}
window.startInlineTreeRename = startInlineTreeRename;
window.cancelInlineTreeRename = cancelInlineTreeRename;
function translateModalElements(modal) {
  const i18n = window.i18n;
  modal.querySelectorAll('[data-i18n]').forEach(el => {
    const key = el.getAttribute('data-i18n');
    if (i18n[key]) {
      if (el.tagName === 'INPUT' || el.tagName === 'TEXTAREA') {
        el.placeholder = i18n[key];
      } else {
        el.textContent = i18n[key];
      }
    }
  });
  modal.querySelectorAll('[data-i18n-placeholder]').forEach(el => {
    el.placeholder = t(i18n, el.dataset.i18nPlaceholder);
  });
}
function initFileModals() {
  const newFileModal = document.getElementById('new-file-modal');
  const newFileNameInput = document.getElementById('new-file-name');
  const newFileExtensionSelect = document.getElementById('file-extension-select');
  const newFileCustomExtension = document.getElementById('custom-extension-input');
  const btnCancelNewFile = document.getElementById('btn-cancel-new-file');
  const btnCreateFile = document.getElementById('btn-create-file');
  const renameModal = document.getElementById('rename-modal');
  const renameFileNameInput = document.getElementById('rename-file-name');
  const renameExtensionSelect = document.getElementById('rename-extension-select');
  const renameCustomExtension = document.getElementById('rename-custom-extension-input');
  const btnCancelRename = document.getElementById('btn-cancel-rename');
  const btnConfirmRename = document.getElementById('btn-confirm-rename');
  guardFilenameInput(newFileNameInput);
  guardFilenameInput(newFileCustomExtension);
  guardFilenameInput(renameFileNameInput);
  guardFilenameInput(renameCustomExtension);
  newFileExtensionSelect.addEventListener('change', function() {
    if (this.value === 'custom') {
      newFileCustomExtension.classList.remove('hidden');
      newFileCustomExtension.focus();
    } else {
      newFileCustomExtension.classList.add('hidden');
      newFileCustomExtension.value = '';
    }
  });
  btnCancelNewFile.addEventListener('click', () => {
    newFileModal.classList.add('hidden');
  });
  btnCreateFile.addEventListener('click', createNewFile);
  newFileNameInput.addEventListener('keypress', (e) => {
    if (e.key === 'Enter') {
      createNewFile();
    }
  });
  renameExtensionSelect.addEventListener('change', function() {
    if (this.value === 'custom') {
      renameCustomExtension.classList.remove('hidden');
      renameCustomExtension.focus();
    } else {
      renameCustomExtension.classList.add('hidden');
      renameCustomExtension.value = '';
    }
  });
  btnCancelRename.addEventListener('click', () => {
    renameModal.classList.add('hidden');
  });
  btnConfirmRename.addEventListener('click', confirmRenameFile);
  renameFileNameInput.addEventListener('keypress', (e) => {
    if (e.key === 'Enter') {
      confirmRenameFile();
    }
  });
  [newFileModal, renameModal].forEach(modal => {
    modal.addEventListener('click', (e) => {
      if (e.target === modal) {
        modal.classList.add('hidden');
      }
    });
    modal.querySelector('.close-btn').addEventListener('click', () => {
      modal.classList.add('hidden');
    });
  });
  initFileExtensionHighlighting();
}
function confirmRenameFile() {
  const fileNameInput = document.getElementById('rename-file-name');
  const extensionSelect = document.getElementById('rename-extension-select');
  const customExtensionInput = document.getElementById('rename-custom-extension-input');
  const newName = fileNameInput.value.trim();
  if (!newName) {
    flashStatus(t(i18n, 'enterNewName'));
    fileNameInput.focus();
    return;
  }
  let newExtension = '';
  if (extensionSelect.value === 'custom') {
    newExtension = customExtensionInput.value.trim();
    if (newExtension && !newExtension.startsWith('.')) {
      newExtension = '.' + newExtension;
    }
  } else if (extensionSelect.value === 'keep') {
    newExtension = renameState.currentExt;
  } else {
    newExtension = extensionSelect.value;
  }
  const newFullFileName = newName + newExtension;
  if (!validateCloudFilename(newFullFileName, fileNameInput)) return;
  const oldPath = renameState.oldPath;
  const directory = oldPath.substring(0, oldPath.lastIndexOf('/') + 1);
  const newPath = directory + newFullFileName;
  document.getElementById('rename-modal').classList.add('hidden');
  api('rename', { old_path: oldPath, new_name: newFullFileName }).then(async () => {
    const currentRoot = getCurrentCloudRoot();
    if (typeof patchTreeCacheForRoot === 'function') {
      await patchTreeCacheForRoot([{ op: 'rename', old_path: oldPath, new_path: newPath, new_name: newFullFileName }], currentRoot);
    } else if (currentRoot === 'main') {
      await patchTreeCache([{ op: 'rename', old_path: oldPath, new_path: newPath, new_name: newFullFileName }]);
    }
    if (currentRoot === 'main') {
      treeNodeRename(oldPath, newPath, newFullFileName);
    } else {
      await buildTree('');
    }
    const viewer = document.getElementById('viewer');
    if (viewer.dataset.path === oldPath) {
      const activeTab = tabsState.tabs.find(tab => tab.path === oldPath);
      if (activeTab) {
        activeTab.path = newPath;
        activeTab.title = newFullFileName;
        renderTabs();
        saveTabsState();
      }
    }
    flashStatus(t(i18n, 'fileRenamed'));
  }).catch(err => {
    flashStatus(t(i18n, 'errorPrefix') + err.message);
  });
}
function createNewFile() {
  const fileNameInput = document.getElementById('new-file-name');
  const extensionSelect = document.getElementById('file-extension-select');
  const customExtensionInput = document.getElementById('custom-extension-input');
  const name = fileNameInput.value.trim();
  if (!name) {
    flashStatus(t(i18n, 'enterFileName'));
    fileNameInput.focus();
    return;
  }
  let extension = '.txt'; // default value
  if (extensionSelect.value === 'custom') {
    extension = customExtensionInput.value.trim();
    if (extension && !extension.startsWith('.')) {
      extension = '.' + extension;
    }
    if (!extension) {
      extension = '.txt';
    }
  } else if (extensionSelect.value && extensionSelect.value !== 'custom') {
    extension = extensionSelect.value;
  }
  if (!extension) {
    extension = '.txt';
  }
  const fullFileName = name + extension;
  if (!validateCloudFilename(fullFileName, fileNameInput)) return;
  const path = newFileState.basePath ? `${String(newFileState.basePath)}/${fullFileName}` : fullFileName;
  document.getElementById('new-file-modal').classList.add('hidden');
  const createRoot = newFileState.baseRoot || getCurrentCloudRoot();
  api('create_file', { path, root: createRoot }).then(async () => {
    const now = Math.floor(Date.now() / 1000);
    const newItem = { name: fullFileName, path, type: 'file', size: 0, mtime: now };
    await insertTreeItem(newItem, createRoot);
    const fileExt = fullFileName.split('.').pop().toLowerCase();
    if (fileExt === 'tex' || fileExt === 'latex') {
      openLatexEditor(path, createRoot);
    } else if (isMarkdownFile(fullFileName)) {
      openMarkdownEditor(path, createRoot);
    } else if (typeof isHtmlFile === 'function' && isHtmlFile(fullFileName)) {
      if (typeof openHtmlEditor === 'function') openHtmlEditor(path, createRoot);
    } else if (isOfficeFile(fullFileName)) {
      openOnlyOfficeEditor(path, createRoot);
    } else if (isTextFile(fullFileName)) {
      openFileInTab(path, true, createRoot);
    }
    flashStatus(t(i18n, 'fileCreated', {name: fullFileName}));
  }).catch(err => {
    if (err.message.includes('exists')) {
      flashStatus(t(i18n, 'fileExists'));
    } else {
      flashStatus(t(i18n, 'errorPrefix') + err.message);
    }
  });
}
function downloadPath(path) {
  const root = getCurrentCloudRoot();
  const url = `api.php?action=download&path=${encodeURIComponent(path)}&root=${root}`;
  const a = document.createElement('a');
  a.href = url; a.download = '';
  document.body.appendChild(a);
  a.click();
  a.remove();
}
function closeAllContextMenus() {
    document.querySelectorAll('.context-menu-submenu').forEach((el) => el.remove());
    document.getElementById('context-menu')?.remove();
}
window.closeAllContextMenus = closeAllContextMenus;
function isContextMenuUiElement(el) {
    return !!(el && (el.closest?.('.context-menu') || el.closest?.('.context-menu-submenu')));
}
function attachContextMenuDismiss(removeMenu) {
    const ac = new AbortController();
    const { signal } = ac;
    const dismiss = () => {
        if (dismiss._done) return;
        dismiss._done = true;
        ac.abort();
        removeMenu();
    };
    window.addEventListener('blur', dismiss, { signal });
    document.addEventListener('pointerdown', (ev) => {
        if (!isContextMenuUiElement(ev.target)) dismiss();
    }, { signal, capture: true });
    document.addEventListener('pointerdown', (ev) => {
        if (!document.getElementById('context-menu')) return;
        const hit = document.elementFromPoint(ev.clientX, ev.clientY);
        if (!hit || isContextMenuUiElement(hit)) return;
        if (hit.tagName === 'IFRAME' || hit.closest?.('#viewer')) {
            // Tap inside an editor iframe should close open dropdown menus
            dismiss();
        }
    }, { signal, capture: true });
    return dismiss;
}
function renderContextMenuOptions(menu, options, hooks = {}) {
    const isTouchMenu = !window.matchMedia('(hover: hover)').matches;
    const submenuClickGuardMs = 400;
    const submenus = [];
    let hideSubmenuTimer = null;
    const cancelHideSubmenu = () => {
        if (hideSubmenuTimer) clearTimeout(hideSubmenuTimer);
        hideSubmenuTimer = null;
    };
    const hideSubmenuEl = (submenu) => {
        submenu.classList.remove('visible');
        submenu.style.visibility = 'hidden';
        submenu.style.opacity = '0';
        submenu.style.pointerEvents = 'none';
        if (submenu.parentNode) submenu.parentNode.removeChild(submenu);
    };
    const hideAllSubmenus = () => {
        cancelHideSubmenu();
        submenus.forEach(hideSubmenuEl);
    };
    const positionSubmenu = (submenu, trigger) => {
        hideAllSubmenus();
        document.body.appendChild(submenu);
        submenu.style.visibility = 'hidden';
        submenu.style.opacity = '0';
        submenu.style.pointerEvents = 'none';
        submenu.classList.add('visible');
        const tr = trigger.getBoundingClientRect();
        const sr = submenu.getBoundingClientRect();
        const vw = window.innerWidth;
        const vh = window.innerHeight;
        let left;
        let top;
        if (isTouchMenu) {
            left = tr.left;
            top = tr.bottom + 2;
            if (top + sr.height > vh - 5) top = Math.max(5, tr.top - sr.height - 2);
            if (left + sr.width > vw - 5) left = Math.max(5, vw - sr.width - 5);
        } else {
            left = tr.right + 2;
            top = tr.top;
            if (left + sr.width > vw - 5) left = Math.max(5, tr.left - sr.width - 2);
            if (top + sr.height > vh - 5) top = Math.max(5, vh - sr.height - 5);
        }
        submenu.style.left = `${left}px`;
        submenu.style.top = `${top}px`;
        submenu.style.visibility = 'visible';
        submenu.style.opacity = '1';
        if (isTouchMenu) {
            submenu.dataset.openedAt = String(Date.now());
            setTimeout(() => {
                if (submenu.parentNode) submenu.style.pointerEvents = 'auto';
            }, submenuClickGuardMs);
        } else {
            submenu.style.pointerEvents = 'auto';
        }
    };
    const scheduleHideSubmenu = (submenu, trigger) => {
        cancelHideSubmenu();
        hideSubmenuTimer = setTimeout(() => {
            if (!submenu.matches(':hover') && !trigger.matches(':hover')) hideSubmenuEl(submenu);
        }, 160);
    };
    const bindItem = (item, opt) => {
        if (hooks.isItemDisabled?.(opt, item)) {
            item.style.opacity = '0.5';
            item.style.cursor = 'not-allowed';
            return;
        }
        if (!opt.action) {
            item.style.cursor = 'default';
            item.style.color = 'var(--muted)';
            return;
        }
        item.addEventListener('click', (e) => {
            e.stopPropagation();
            const parentSubmenu = item.closest('.context-menu-submenu');
            if (parentSubmenu?.dataset.openedAt) {
                const elapsed = Date.now() - parseInt(parentSubmenu.dataset.openedAt, 10);
                if (elapsed < submenuClickGuardMs) return;
            }
            opt.action();
            hooks.onItemClick?.(opt);
            document.querySelectorAll('.context-menu, .context-menu-submenu').forEach((m) => m.remove());
        });
    };
    const appendOption = (container, opt) => {
        if (opt.label === '---' || opt.type === 'separator') {
            const separator = document.createElement('div');
            separator.className = 'context-menu-separator';
            container.appendChild(separator);
            return;
        }
        if (opt.type === 'group' && opt.items?.length) {
            const group = document.createElement('div');
            group.className = 'context-menu-group';
            const trigger = document.createElement('div');
            trigger.className = 'context-menu-item context-menu-group-trigger';
            trigger.innerHTML = `${getContextMenuIconSvg(opt.icon)}<span class="context-menu-item-label">${escapeHtml(opt.label || '')}</span><svg class="context-menu-aim-triangle" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>`;
            const submenu = document.createElement('div');
            submenu.className = 'context-menu-submenu';
            opt.items.forEach((sub) => appendOption(submenu, sub));
            submenus.push(submenu);
            menu.appendChild(group);
            group.appendChild(trigger);
            const openSubmenu = () => {
                positionSubmenu(submenu, trigger);
            };
            if (!isTouchMenu) trigger.addEventListener('mouseenter', openSubmenu);
            trigger.addEventListener('click', (e) => {
                e.stopPropagation();
                if (isTouchMenu) e.preventDefault();
                if (submenu.classList.contains('visible') && submenu.parentNode) hideSubmenuEl(submenu);
                else openSubmenu();
            });
            trigger.addEventListener('mouseleave', () => scheduleHideSubmenu(submenu, trigger));
            submenu.addEventListener('mouseenter', cancelHideSubmenu);
            submenu.addEventListener('mouseleave', () => scheduleHideSubmenu(submenu, trigger));
            return;
        }
        const item = document.createElement('div');
        item.className = 'context-menu-item';
        item.innerHTML = `${getContextMenuIconSvg(opt.icon)}<span class="context-menu-item-label">${escapeHtml(opt.label || '')}</span>`;
        bindItem(item, opt);
        container.appendChild(item);
    };
    options.forEach((opt) => appendOption(menu, opt));
    menu._hideContextSubmenus = hideAllSubmenus;
}
window.renderContextMenuOptions = renderContextMenuOptions;

function getShareActionPath(path, root) {
    return typeof toMainTreePath === 'function' ? toMainTreePath(path, root || getCurrentCloudRoot()) : path;
}
function buildFileActionGroupItems(path, type, opts = {}) {
    const actionItems = [];
    const includeSelect = opts.includeSelect === true && type === 'file';
    const hideEdit = opts.hideEdit || (typeof shouldHideEditMenuOption === 'function' && shouldHideEditMenuOption(path));
    if (includeSelect) {
        actionItems.push({
            label: selectionState.active ? t(window.i18n, 'deselect') : t(window.i18n, 'select'),
            icon: selectionState.active ? 'deselect' : 'select',
            action: () => toggleSelectionMode()
        });
    }
    if (path) actionItems.push({ label: t(window.i18n, 'rename'), icon: 'rename', action: () => renamePath(path) });
    if (path) actionItems.push({ label: t(window.i18n, 'move'), icon: 'move', action: () => showMoveDialog(path, type) });
    if (path) actionItems.push({ label: t(window.i18n, 'copy'), icon: 'copy', action: () => showCopyDialog(path, type) });
    if (type === 'file' && path && !hideEdit && (isMonacoEditFile(path) || isEditableImage(path) || isEditableAudio(path) || (typeof isOfficeFile === 'function' && isOfficeFile(path)))) {
        actionItems.push({
            label: t(window.i18n, 'edit'),
            icon: 'edit',
            action: () => {
                if (isMonacoEditFile(path)) openFileInTab(path, true);
                else if (isEditableImage(path)) openPhotoEditor(path);
                else if (isEditableAudio(path)) openTranscriptionEditor(path);
                else if (typeof openOnlyOfficeEditor === 'function') openOnlyOfficeEditor(path);
            }
        });
    }
    if (type === 'file' && path && isConvertibleFile(path)) {
        actionItems.push({ label: t(window.i18n, 'convert'), icon: 'convert', action: () => showConvertDialog(path) });
    }
    if (path && type === 'dir') {
        actionItems.push({ label: t(window.i18n, 'archiveFolder'), icon: 'archive-folder', action: () => archiveFolder(path) });
    }
    return actionItems;
}

function buildFileContextMenuOptions(path, type, opts = {}) {
    const options = [];
    const actionItems = buildFileActionGroupItems(path, type, opts);
    if (actionItems.length) {
        options.push({ type: 'group', label: t(window.i18n, 'actions'), icon: 'layout-panel-top', items: actionItems });
    }
    if (type === 'file' && path.toLowerCase().endsWith('.txt')) {
        options.push({ label: 'Text to Speech', icon: 'speech', action: () => openTextToSpeechEditor(path) });
    }
    if (type === 'file' && typeof isEditableAudio === 'function' && isEditableAudio(path)) {
        options.push({
            label: (window.i18n && window.i18n.transcribeAudio) || 'Transcribe audio',
            icon: 'speech',
            action: () => openTranscriptionEditor(path)
        });
    }
    if (type === 'file' && isZipFile(path)) {
        options.push({ label: t(window.i18n, 'extractArchive'), icon: 'extract', action: () => showExtractDialog(path), title: t(window.i18n, 'extractArchiveTooltip') });
    }
    if (opts.tabId != null) {
        options.push({ label: t(window.i18n, 'refresh'), icon: 'refresh', action: () => refreshTabContent(opts.tabId) });
        if (type === 'file' && typeof isOfficeFile === 'function' && isOfficeFile(path)) {
            options.push({
                label: t(window.i18n, 'editWithOnlyOffice'),
                icon: 'office',
                action: () => openOnlyOfficeEditor(path),
                title: t(window.i18n, 'editWithOnlyOfficeTooltip')
            });
        }
    }
    if (path) {
        options.push({
            type: 'group',
            label: t(window.i18n, 'share'),
            icon: 'share',
            items: [
                { label: cloudActionText('shareGetLink', 'Get link'), icon: 'link', action: () => shareItem(path, type, opts.root) },
                { label: cloudActionText('shareSendToChat', 'Send to chat'), icon: 'message', action: () => sendCloudShareToChat(path, type, opts.root) }
            ]
        });
    }
    if (path) options.push({ label: t(window.i18n, 'properties'), icon: 'properties', action: () => showProperties(path, type) });
    if (opts.tabId != null) {
        if (tabsState.tabs.length <= 1) {
            options.push({ label: t(window.i18n, 'close'), icon: 'close', action: () => closeTab(opts.tabId) });
        } else {
            options.push({
                type: 'group',
                label: t(window.i18n, 'close'),
                icon: 'close',
                items: [
                    {
                        label: t(window.i18n, 'closeCurrent'),
                        icon: 'close',
                        action: () => closeTab(opts.tabId)
                    },
                    {
                        label: t(window.i18n, 'closeOtherTabs'),
                        icon: 'close-others',
                        action: () => {
                            tabsState.tabs = tabsState.tabs.filter((entry) => entry.id === opts.tabId);
                            tabsState.activeTabId = opts.tabId;
                            renderTabs();
                            saveTabsState();
                        }
                    }
                ]
            });
        }
    }
    if (path) {
        options.push({
            label: t(window.i18n, 'delete'),
            icon: 'delete',
            action: () => deletePath(path, 'file', opts.tabId != null ? opts.tabTitle : '', opts.root || getCurrentCloudRoot())
        });
    }
    return options;
}
window.buildFileContextMenuOptions = buildFileContextMenuOptions;

function showContextMenu(e, path, type, name, root) {
    // Guard: if path is an object (item was passed instead of item.path), extract fields
    if (path !== null && typeof path === 'object') {
        name = path.name || '';
        type = path.type || 'file';
        path = path.path || '';
    }
    // Ensure path is always a string
    path = (path === null || path === undefined) ? '' : String(path);
    type = type || 'file';
    name = name || path.split('/').pop() || '';

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
    const basePath = path || '';
    const hasMultipleSelection = selectionState.selectedItems.size > 1;
    const options = [];
    if (isTrashRoot()) {
        const targetPaths = hasMultipleSelection
            ? Array.from(selectionState.selectedItems)
            : (path ? [path] : []);
        options.push(
            {
                label: t(window.i18n, 'restoreSelected', { count: targetPaths.length || 1 }),
                icon: 'move',
                action: () => restoreTrashItems(targetPaths)
            },
            {
                label: t(window.i18n, 'deleteSelected', { count: targetPaths.length || 1 }),
                icon: 'delete',
                action: () => showDeleteConfirmationModal(targetPaths, { permanent: true, fromTrash: true })
            }
        );
    } else if (hasMultipleSelection) {
        options.push(
            { 
                label: t(window.i18n, 'deselect'), 
                icon: 'deselect',
                action: () => {
                    toggleSelectionMode();
                    menu.remove(); // Close the menu after selection
                }
            },
            { 
                label: t(window.i18n, 'moveSelected', { count: selectionState.selectedItems.size }), 
                icon: 'move',
                action: () => {
                    moveSelectedItems();
                    menu.remove();
                }
            },
            { 
                label: t(window.i18n, 'deleteSelected', { count: selectionState.selectedItems.size }), 
                icon: 'delete',
                action: () => {
                    deleteSelectedItems();
                    menu.remove();
                }
            }
        );
    } else {
        options.push(
            ...(type !== 'file' ? [{
                label: selectionState.active ? t(window.i18n, 'deselect') : t(window.i18n, 'select'),
                icon: selectionState.active ? 'deselect' : 'select',
                action: () => {
                    toggleSelectionMode();
                    menu.remove();
                }
            }] : []),
            ...(selectionState.active && selectionState.selectedItems.size > 0 ? [
                { label: '---', action: null }
            ] : []),
            ...(selectionState.active && selectionState.selectedItems.size > 0 ? [
                { 
                    label: t(window.i18n, 'deleteSelected', { count: selectionState.selectedItems.size }), 
                    icon: 'delete',
                    action: () => deleteSelectedItems() 
                },
                { 
                    label: t(window.i18n, 'moveSelected', { count: selectionState.selectedItems.size }), 
                    icon: 'move',
                    action: () => moveSelectedItems() 
                },
                { label: '---', action: null }
            ] : []),
            ...(type !== 'file' ? (() => {
                const createItems = [
                    { label: t(window.i18n, 'newFolder'), icon: 'new-folder', action: () => newFolder(basePath) },
                    { label: t(window.i18n, 'newFile'), icon: 'new-file', action: () => newFile(basePath) },
                    { label: t(window.i18n, 'createVideo') || 'Create Video', icon: 'video', action: () => newVideo(basePath) }
                ];
                return [{ type: 'group', label: t(window.i18n, 'create'), icon: 'circle-plus', items: createItems }];
            })() : []),
            ...(type !== 'file' ? [
                { label: t(window.i18n, 'upload'), icon: 'upload', action: () => openUploadDialog(basePath) },
            ] : []),
            ...buildFileContextMenuOptions(path, type, { includeSelect: type === 'file', root: root || getCurrentCloudRoot() })
        );
    }
    if (options.length === 0) {
        options.push({ label: t(window.i18n, 'noActions'), action: () => {} });
    }
    renderContextMenuOptions(menu, options);
    document.body.appendChild(menu);
    adjustContextMenuPosition(menu, e.clientX, e.clientY);
    menu.style.visibility = 'visible';
    menu.style.opacity = '1';
    const removeMenu = () => {
        if (removeMenu._done) return;
        removeMenu._done = true;
        menu._hideContextSubmenus?.();
        closeAllContextMenus();
        document.removeEventListener('click', closeMenu);
        document.removeEventListener('keydown', closeOnEscape);
        document.removeEventListener('mousemove', closeOnDistance);
    };
    attachContextMenuDismiss(removeMenu);
    const closeMenu = (event) => {
        if (!isContextMenuUiElement(event.target)) removeMenu();
    };
    const closeOnEscape = (event) => {
        if (event.key === 'Escape') removeMenu();
    };
    const closeOnDistance = (() => {
        const samples = [];
        return (event) => {
            // Never dismiss while the cursor is over any context menu or submenu
            const hit = document.elementFromPoint(event.clientX, event.clientY);
            if (hit && isContextMenuUiElement(hit)) return;
            const now = performance.now();
            samples.push({ x: event.clientX, y: event.clientY, t: now });
            while (samples.length > 0 && now - samples[0].t > 100) samples.shift();
            if (samples.length < 2) return;
            // Use the nearest open menu (main menu or any visible submenu) as anchor
            const rects = [menu, ...document.querySelectorAll('.context-menu-submenu.visible')]
                .filter((el) => el && el.parentNode)
                .map((el) => el.getBoundingClientRect());
            const dist = rects.reduce((min, r) => {
                const dx = Math.max(r.left - event.clientX, 0, event.clientX - r.right);
                const dy = Math.max(r.top - event.clientY, 0, event.clientY - r.bottom);
                return Math.min(min, Math.sqrt(dx * dx + dy * dy));
            }, Infinity);
            if (dist < 20) return;
            const head = samples[0];
            const dt = (now - head.t) / 1000;
            if (dt < 0.01) return;
            const speed = Math.hypot(event.clientX - head.x, event.clientY - head.y) / dt;
            if (speed > 600) removeMenu();
        };
    })();
    setTimeout(() => {
        document.addEventListener('click', closeMenu);
        document.addEventListener('keydown', closeOnEscape);
        if (window.matchMedia('(hover: hover)').matches) {
            document.addEventListener('mousemove', closeOnDistance);
        }
    }, 10);
}

function getContextMenuIconSvg(iconName) {
    const icons = {
        edit: '<svg class="context-menu-item-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 1 1 3 3L7 19l-4 1 1-4z"/></svg>',
        refresh: '<svg class="context-menu-item-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12a9 9 0 1 1-2.64-6.36"/><polyline points="21 3 21 9 15 9"/></svg>',
        extract: '<svg class="context-menu-item-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 8v13H3V8"/><path d="M1 3h22v5H1z"/><path d="m12 11 0 6"/><path d="m9 14 3 3 3-3"/></svg>',
        convert: '<svg class="context-menu-item-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="7 7 3 7 3 3"/><polyline points="17 17 21 17 21 21"/><path d="M21 3l-7 7"/><path d="M3 21l7-7"/></svg>',
        office: '<svg class="context-menu-item-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M9 8h6"/><path d="M9 12h6"/><path d="M9 16h6"/></svg>',
        speech: '<svg class="context-menu-item-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M8.8 20v-4.1l1.9.2a2.3 2.3 0 0 0 2.164-2.1V8.3A5.37 5.37 0 0 0 2 8.25c0 2.8.656 3.054 1 4.55a5.77 5.77 0 0 1 .029 2.758L2 20"/><path d="M19.8 17.8a7.5 7.5 0 0 0 .003-10.603"/><path d="M17 15a3.5 3.5 0 0 0-.025-4.975"/></svg>',
        properties: '<svg class="context-menu-item-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9.671 4.136a2.34 2.34 0 0 1 4.659 0 2.34 2.34 0 0 0 3.319 1.915 2.34 2.34 0 0 1 2.33 4.033 2.34 2.34 0 0 0 0 3.831 2.34 2.34 0 0 1-2.33 4.033 2.34 2.34 0 0 0-3.319 1.915 2.34 2.34 0 0 1-4.659 0 2.34 2.34 0 0 0-3.32-1.915 2.34 2.34 0 0 1-2.33-4.033 2.34 2.34 0 0 0 0-3.831A2.34 2.34 0 0 1 6.35 6.051a2.34 2.34 0 0 0 3.319-1.915"/><circle cx="12" cy="12" r="3"/></svg>',
        rename: '<svg class="context-menu-item-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 1 1 3 3L7 19l-4 1 1-4z"/></svg>',
        move: '<svg class="context-menu-item-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="6" cy="19" r="3"/><path d="M9 19h8.5a3.5 3.5 0 0 0 0-7h-11a3.5 3.5 0 0 1 0-7H15"/><circle cx="18" cy="5" r="3"/></svg>',
        copy: '<svg class="context-menu-item-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="14" height="14" x="8" y="8" rx="2" ry="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/></svg>',
        delete: '<svg class="context-menu-item-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M8 6V4h8v2"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/></svg>',
        close: '<svg class="context-menu-item-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>',
        'close-others': '<svg class="context-menu-item-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" x2="18" y1="12" y2="18"/><line x1="12" x2="18" y1="18" y2="12"/><rect width="14" height="14" x="8" y="8" rx="2" ry="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/></svg>',
        'close-all': '<svg class="context-menu-item-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="5" y1="5" x2="19" y2="19"/><line x1="19" y1="5" x2="5" y2="19"/></svg>',
        select: '<svg class="context-menu-item-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="4" width="16" height="16" rx="2"/><polyline points="8 12 11 15 16 9"/></svg>',
        deselect: '<svg class="context-menu-item-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="4" width="16" height="16" rx="2"/><line x1="8" y1="8" x2="16" y2="16"/><line x1="16" y1="8" x2="8" y2="16"/></svg>',
        upload: '<svg class="context-menu-item-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 16V6"/><polyline points="8 10 12 6 16 10"/><path d="M4 18h16"/></svg>',
        link: '<svg class="context-menu-item-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>',
        message: '<svg class="context-menu-item-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a4 4 0 0 1-4 4H7l-4 4V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4z"/></svg>',
        video: '<svg class="context-menu-item-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m10 9 5 3-5 3z"/></svg>',
        'new-folder': '<svg class="context-menu-item-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/><line x1="12" y1="11" x2="12" y2="17"/><line x1="9" y1="14" x2="15" y2="14"/></svg>',
        'new-file': '<svg class="context-menu-item-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="11" x2="12" y2="17"/><line x1="9" y1="14" x2="15" y2="14"/></svg>',
        'archive-folder': '<svg class="context-menu-item-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 7h18v13a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><rect x="2" y="2" width="20" height="5"/><path d="M10 12h4"/></svg>',
        share: '<svg class="context-menu-item-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>',
        'circle-plus': '<svg class="context-menu-item-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M8 12h8"/><path d="M12 8v8"/></svg>',
        'layout-panel-top': '<svg class="context-menu-item-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="7" x="3" y="3" rx="1"/><rect width="7" height="7" x="3" y="14" rx="1"/><rect width="7" height="7" x="14" y="14" rx="1"/></svg>',
        'chevron-right': '<svg class="context-menu-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>'
    };
    return icons[iconName] || '<svg class="context-menu-item-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="2"/></svg>';
}
function isZipFile(path) {
    const name = String(path || '').toLowerCase();
    if (name.endsWith('.tar.gz') || name.endsWith('.tgz')) return true;
    const ext = name.split('.').pop();
    return ['zip', '7z', 'rar', 'tar'].includes(ext);
}
async function archivePasswordRequired(filePath) {
    try {
        const result = await api('archive_password_required', {
            archive_path: filePath,
            format: typeof getArchiveFormatFromName === 'function' ? getArchiveFormatFromName(filePath) : ''
        }, 'GET');
        return !!result.password_required;
    } catch (_) {
        return false;
    }
}
async function extractArchive(filePath, extractToPath = null, password = '') {
    if (!isZipFile(filePath)) {
        flashStatus(t(i18n, 'notAZipFile'));
        return;
    }
    try {
        flashStatus(t(i18n, 'extractingArchive'));
        const response = await api('extract_archive', {
            archive_path: filePath,
            extract_to: extractToPath || '',
            format: typeof getArchiveFormatFromName === 'function' ? getArchiveFormatFromName(filePath) : '',
            password: password || '',
            async: '1'
        }, 'POST');
        const result = response.job_id ? await waitForArchiveJob(response.job_id, t(i18n, 'extractingArchive')) : response;
        if (result.cancelled) {
            flashStatus(t(i18n, 'cancelled') || 'Cancelled');
        } else if (result.success !== false) {
            flashStatus(t(i18n, 'archiveExtracted'));
            // Unknown number of extracted files — full rebuild needed here
            cachedFileTree = null;
            try { await api('invalidate_file_tree', {}, 'POST'); } catch(_) {}
            await buildTree('');
        } else {
            flashStatus(t(i18n, 'extractError') + ': ' + result.error);
        }
    } catch (err) {
        console.error('Extraction error:', err);
        flashStatus(t(i18n, 'extractError') + ': ' + err.message);
    }
}
function showExtractDialog(filePath) {
    const modal = document.createElement('div');
    modal.className = 'modal';
    modal.style.display = 'flex';
    modal.innerHTML = `
        <div class="modal-content" style="max-width: 500px;">
            <div class="modal-header">
                <h3>${t(i18n, 'selectExtractDestination')}</h3>
                <button class="icon-btn close-btn" aria-label="${escapeHtml(t(i18n, 'close') || 'Close')}">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                </button>
            </div>
            <div class="modal-body">
                <p>${t(i18n, 'extractingArchive')} <strong>${filePath}</strong></p>
                <div class="folder-selector">
                    <label>${t(i18n, 'selectTargetFolder')}</label>
                    <div id="extract-folder-tree" class="folder-tree" style="
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
                <div id="extract-password-group" class="form-group" style="margin-top: 12px;display:none;">
                    <label>${t(i18n, 'password') || 'Password'}</label>
                    <div style="display:flex;gap:6px;">
                        <input id="extract-archive-password" type="password" autocomplete="current-password" placeholder="${t(i18n, 'password') || 'Password'}" style="width:100%;min-width:0;">
                        <button id="extract-password-toggle" class="icon-btn" type="button" title="${t(i18n, 'showPassword') || 'Show password'}">
                            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button id="btn-extract-here" class="btn secondary">${t(i18n, 'extractHere')}</button>
                <button id="btn-cancel-extract" class="btn secondary">${t(i18n, 'cancel')}</button>
                <button id="btn-confirm-extract" class="btn primary">${t(i18n, 'extractToFolder')}</button>
            </div>
        </div>
    `;
    document.body.appendChild(modal);
    loadFolderTreeForExtract();
    const passwordGroup = document.getElementById('extract-password-group');
    const passwordInput = document.getElementById('extract-archive-password');
    const passwordToggle = document.getElementById('extract-password-toggle');
    archivePasswordRequired(filePath).then((required) => {
        if (required && passwordGroup) passwordGroup.style.display = '';
    });
    passwordToggle?.addEventListener('click', () => {
        const visible = passwordInput.type === 'text';
        passwordInput.type = visible ? 'password' : 'text';
        passwordToggle.title = visible ? (t(i18n, 'showPassword') || 'Show password') : (t(i18n, 'hidePassword') || 'Hide password');
        passwordInput.focus();
    });
    document.getElementById('btn-cancel-extract').addEventListener('click', () => modal.remove());
    document.getElementById('btn-extract-here').addEventListener('click', () => {
        const password = document.getElementById('extract-archive-password')?.value || '';
        modal.remove();
        extractArchive(filePath, '', password);
    });
    document.getElementById('btn-confirm-extract').addEventListener('click', () => {
        const targetDir = window.selectedExtractFolder || '';
        const password = document.getElementById('extract-archive-password')?.value || '';
        modal.remove();
        extractArchive(filePath, targetDir, password);
    });
    modal.querySelector('.close-btn').addEventListener('click', () => modal.remove());
    modal.addEventListener('click', (e) => {
        if (e.target === modal) {
            modal.remove();
        }
    });
}
async function loadFolderTreeForExtract() {
    try {
        const treeContainer = document.getElementById('extract-folder-tree');
        treeContainer.innerHTML = `<div style="padding: 10px; text-align: center; color: var(--muted);">${t(i18n, 'loading')}</div>`;
        
        const rootItems = await api('list', { path: '' }, 'GET');
        const folders = rootItems.items.filter(item => item.type === 'dir');
        
        treeContainer.innerHTML = '';
        
        if (folders.length === 0) {
            treeContainer.innerHTML = `<div style="padding: 10px; text-align: center; color: var(--muted);">${t(i18n, 'foldersNotFound')}</div>`;
            return;
        }
        
        const container = renderExtractFolderNodes(folders, '', 0);
        treeContainer.appendChild(container);
    } catch (err) {
        console.error('Failed to load folder tree:', err);
        const treeContainer = document.getElementById('extract-folder-tree');
        treeContainer.innerHTML = `<div style="padding: 10px; text-align: center; color: #e74c3c;">${t(i18n, 'loadError', { message: err.message })}</div>`;
    }
}
function renderExtractFolderNodes(items, basePath = '', depth = 0) {
    const container = document.createElement('div');
    container.className = 'extract-tree-container';
    items.forEach(item => {
        const node = document.createElement('div');
        node.className = 'extract-tree-node';
        node.style.paddingLeft = `${depth * 20}px`;
        node.dataset.path = item.path;
        node.dataset.type = item.type;
        const toggle = document.createElement('span');
        toggle.className = 'extract-tree-toggle';
        toggle.innerHTML = '<svg width="12" height="12" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>';
        toggle.style.cursor = 'pointer';
        toggle.style.marginRight = '5px';
        toggle.style.display = 'inline-block';
        toggle.style.verticalAlign = 'middle';
        const label = document.createElement('span');
        label.className = 'extract-tree-label';
        label.innerHTML = `
            <span style="margin-right: 6px; display: inline-flex;">${getFolderIconMarkup()}</span>
            <span>${item.name}</span>
        `;
        label.style.cursor = 'pointer';
        label.style.padding = '4px 8px';
        label.style.borderRadius = '3px';
        label.style.display = 'inline-block';
        label.style.verticalAlign = 'middle';
        label.style.flex = '1';
        const nodeContent = document.createElement('div');
        nodeContent.style.display = 'flex';
        nodeContent.style.alignItems = 'center';
        nodeContent.style.margin = '2px 0';
        nodeContent.appendChild(toggle);
        nodeContent.appendChild(label);
        node.appendChild(nodeContent);
        const children = document.createElement('div');
        children.className = 'extract-tree-children';
        children.style.display = 'none';
        children.dataset.loaded = 'false';
        label.addEventListener('click', (e) => {
            e.stopPropagation();
            selectFolderForExtract(item.path);
        });
        toggle.addEventListener('click', async (e) => {
            e.stopPropagation();
            const isOpen = children.style.display !== 'none';
            toggle.style.transform = isOpen ? 'rotate(0deg)' : 'rotate(90deg)';
            toggle.style.transition = 'transform 0.2s ease';
            children.style.display = isOpen ? 'none' : 'block';
            if (!isOpen && children.dataset.loaded === 'false') {
                try {
                    const res = await api('list', { path: item.path }, 'GET');
                    const childFolders = res.items.filter(child => child.type === 'dir');
                    if (childFolders.length > 0) {
                        const childNodes = renderExtractFolderNodes(childFolders, item.path, depth + 1);
                        children.appendChild(childNodes);
                    } else {
                        const emptyMsg = document.createElement('div');
                        emptyMsg.style.padding = '8px 20px';
                        emptyMsg.style.color = 'var(--muted)';
                        emptyMsg.style.fontSize = '12px';
                        emptyMsg.textContent = t(i18n, 'noSubfolders');
                        children.appendChild(emptyMsg);
                    }
                    children.dataset.loaded = 'true';
                } catch (err) {
                    console.error('Failed to load folder contents:', err);
                }
            }
        });
        container.appendChild(node);
        container.appendChild(children);
    });
    return container;
}
function selectFolderForExtract(folderPath) {
    document.querySelectorAll('.extract-tree-label').forEach(label => {
        label.style.background = 'transparent';
        label.style.color = 'var(--fg)';
    });
    const selectedLabel = document.querySelector(`.extract-tree-node[data-path="${folderPath}"] .extract-tree-label`);
    if (selectedLabel) {
        selectedLabel.style.background = 'var(--accent)';
        selectedLabel.style.color = 'white';
    }
    const pathDisplay = document.getElementById('current-folder-path');
    pathDisplay.textContent = folderPath || '/';
    window.selectedExtractFolder = folderPath;
}
async function archiveFolder(folderPath) {
  if (!folderPath) {
    flashStatus(t(i18n, 'selectFolderToArchive'));
    return;
  }
  const timestamp = formatTimestampForFilename();
  const options = typeof window.showArchiveOptionsModal === 'function'
    ? await window.showArchiveOptionsModal(`${folderPath.split('/').pop()}_${timestamp}.zip`)
    : { archiveName: '', format: 'zip', password: '' };
  if (!options) return;
  try {
    const result = await api('archive_folder', { 
      folder_path: folderPath,
      archive_name: options.archiveName,
      format: options.format,
      password: options.password,
      async: '1'
    }, 'POST');
    const completed = result.job_id ? await waitForArchiveJob(result.job_id, t(i18n, 'archivingFolder')) : result;
    if (completed.cancelled) {
      flashStatus(t(i18n, 'cancelled') || 'Cancelled');
    } else if (completed.success !== false) {
      // Add created archive to tree incrementally
      const archiveName = completed.archive_name || (completed.archive_path && completed.archive_path.split('/').pop()) || (folderPath.split('/').pop() + '.zip');
      const archiveDir = folderPath.includes('/') ? folderPath.substring(0, folderPath.lastIndexOf('/')) : '';
      const archivePath = archiveDir ? archiveDir + '/' + archiveName : archiveName;
      const now = Math.floor(Date.now() / 1000);
      const newItem = { name: archiveName, path: archivePath, type: 'file', size: completed.size || 0, mtime: now };
      await insertTreeItem(newItem, getCurrentCloudRoot());
      showArchiveResultDialog(completed, folderPath);
    } else {
      flashStatus(t(i18n, 'archiveError') + ': ' + completed.error);
    }
  } catch (err) {
    flashStatus(t(i18n, 'archiveError') + ': ' + err.message);
  }
}
function showArchiveResultDialog(result, originalFolderPath) {
  const modal = document.createElement('div');
  modal.className = 'modal';
  modal.style.display = 'flex';
  const archiveName = result.archive_name || result.archive_path.split('/').pop();
  modal.innerHTML = `
    <div class="modal-content" style="max-width: 500px;">
      <div class="modal-header">
        <h3 style="color: #27ae60;">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 8px;">
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
            <polyline points="14 2 14 8 20 8"></polyline>
            <path d="M16 13H8"></path>
            <path d="M16 17H8"></path>
            <path d="M10 9H9H8"></path>
          </svg>
          ${t(i18n, 'archiveCreated')}
        </h3>
        <button class="icon-btn close-btn" aria-label="${escapeHtml(t(i18n, 'close') || 'Close')}">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
        </button>
      </div>
      <div class="modal-body">
        <div style="text-align: center; margin-bottom: 20px;">
          <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#27ae60" stroke-width="2" style="margin-bottom: 10px;">
            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
            <polyline points="22 4 12 14.01 9 11.01"></polyline>
          </svg>
          <p><strong>${t(i18n, 'archiveReady')}</strong></p>
        </div>
        <div style="background: var(--panel); padding: 15px; border-radius: 4px; margin-bottom: 15px;">
          <div class="property-row">
            <span class="property-label">${t(i18n, 'originalFolder')}:</span>
            <span class="property-value">${originalFolderPath}</span>
          </div>
          <div class="property-row">
            <span class="property-label">${t(i18n, 'archiveFile')}:</span>
            <span class="property-value">${archiveName}</span>
          </div>
          <div class="property-row">
            <span class="property-label">${t(i18n, 'fileSize')}:</span>
            <span class="property-value">${result.size ? formatBytes(result.size) : t(i18n, 'unknown')}</span>
          </div>
          <div class="property-row">
            <span class="property-label">${t(i18n, 'location')}:</span>
            <span class="property-value">${t(i18n, 'currentFolder')}</span>
          </div>
        </div>
        <div style="background: #d4edda; border: 1px solid #c3e6cb; border-radius: 4px; padding: 12px; color: #155724; margin-top: 15px;">
          <strong>${t(i18n, 'success')}:</strong> 
          ${t(i18n, 'archiveCreatedInCurrentFolder')}
        </div>
      </div>
      <div class="modal-footer">
        <button id="btn-ok-archive" class="btn primary">
          ${t(i18n, 'ok')}
        </button>
      </div>
    </div>
  `;
  document.body.appendChild(modal);
  const closeModal = () => modal.remove();
  modal.querySelector('.close-btn').addEventListener('click', closeModal);
  modal.querySelector('#btn-ok-archive').addEventListener('click', closeModal);
  modal.addEventListener('click', (e) => {
    if (e.target === modal) closeModal();
  });
  refreshTreeAfterOperation();
}
function cloudActionText(key, fallback) {
    const value = t(window.i18n, key);
    return value && value !== key ? value : fallback;
}

async function shareItem(path, type, root) {
    if (!path) {
        flashStatus(t(i18n, 'noPathSelected'));
        return;
    }

    try {
        await flushPendingAutoSaves();
        const displayName = path.split('/').pop();
        const normalizedPath = getShareActionPath(path, root);
        shareState.currentPath = normalizedPath;
        if (typeof renderShareModalForPath === 'function') {
            await renderShareModalForPath(normalizedPath, displayName, shareState.currentExpiresOption || 'never');
        } else {
            const result = await api('share_file', { path: normalizedPath, root: root || getCurrentCloudRoot(), expires_option: shareState.currentExpiresOption || 'never' }, 'POST');
            if (!result.key) flashStatus(t(i18n, 'shareError'));
        }
    } catch (err) {
        flashStatus(t(i18n, 'shareError') + ': ' + err.message);
    }
}

function getCloudShareChatModal() {
    let modal = document.getElementById('cloud-share-chat-modal');
    if (modal) return modal;
    modal = document.createElement('div');
    modal.id = 'cloud-share-chat-modal';
    modal.style.cssText = 'display:none;position:fixed;inset:0;z-index:200010;align-items:center;justify-content:center;background:rgba(0,0,0,.52);backdrop-filter:blur(10px);-webkit-backdrop-filter:blur(10px);padding:16px;box-sizing:border-box;';
    modal.innerHTML = `
        <div style="width:100%;max-width:420px;border-radius:16px;border:1px solid var(--border);background:var(--panel);box-shadow:0 24px 70px rgba(0,0,0,.38);overflow:hidden;">
            <div style="padding:18px 20px;border-bottom:1px solid var(--border);font-size:17px;font-weight:700;color:var(--fg);">${cloudActionText('shareSendToChat', 'Send to chat')}</div>
            <div style="padding:16px 20px;display:flex;flex-direction:column;gap:12px;">
                <input id="cloud-share-chat-email" type="email" autocomplete="off" placeholder="email@example.com" style="width:100%;box-sizing:border-box;padding:12px 14px;border:1px solid var(--border);border-radius:10px;background:var(--bg);color:var(--fg);font-size:15px;">
                <div id="cloud-share-chat-results" style="display:none;max-height:180px;overflow-y:auto;border:1px solid var(--border);border-radius:10px;background:var(--bg);padding:4px;"></div>
                <div id="cloud-share-chat-status" style="min-height:20px;color:var(--muted);font-size:14px;"></div>
            </div>
            <div style="display:flex;gap:10px;justify-content:flex-end;padding:14px 20px 18px;border-top:1px solid var(--border);">
                <button type="button" id="cloud-share-chat-cancel" class="btn secondary">${cloudActionText('cancel', 'Cancel')}</button>
                <button type="button" id="cloud-share-chat-send" class="btn primary">${cloudActionText('send', 'Send')}</button>
            </div>
        </div>
    `;
    document.body.appendChild(modal);
    return modal;
}

function sendCloudShareToChat(path, type, root) {
    if (!path) {
        flashStatus(t(i18n, 'noPathSelected'));
        return;
    }
    const normalizedPath = getShareActionPath(path, root);
    const modal = getCloudShareChatModal();
    const emailInput = modal.querySelector('#cloud-share-chat-email');
    const results = modal.querySelector('#cloud-share-chat-results');
    const status = modal.querySelector('#cloud-share-chat-status');
    const cancel = modal.querySelector('#cloud-share-chat-cancel');
    const send = modal.querySelector('#cloud-share-chat-send');
    let searchTimer = null;
    const close = () => {
        modal.style.display = 'none';
        send.disabled = false;
        cancel.disabled = false;
        if (results) {
            results.style.display = 'none';
            results.innerHTML = '';
        }
    };
    const renderUserResults = (users) => {
        if (!results) return;
        if (!users.length) {
            results.style.display = 'none';
            results.innerHTML = '';
            return;
        }
        results.innerHTML = '';
        users.forEach((user) => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.style.cssText = 'display:flex;align-items:center;gap:10px;width:100%;padding:9px 10px;border:0;border-radius:8px;background:transparent;color:var(--fg);cursor:pointer;text-align:left;';
            let photo = user.photo || '/default_avatar.png';
            if (photo && !/^https?:\/\//i.test(photo) && !photo.startsWith('/')) {
                photo = '/' + photo.replace(/^\/+/, '');
            }
            btn.innerHTML = `<img src="${escapeHtml(photo)}" alt="" style="width:28px;height:28px;border-radius:50%;object-fit:cover;flex-shrink:0;"><span style="min-width:0;display:flex;flex-direction:column;"><span style="font-size:13px;font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${escapeHtml(user.name || user.email)}</span><span style="font-size:12px;opacity:.7;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${escapeHtml(user.email)}</span></span>`;
            btn.addEventListener('click', () => {
                emailInput.value = user.email;
                results.style.display = 'none';
                results.innerHTML = '';
                status.textContent = '';
            });
            results.appendChild(btn);
        });
        results.style.display = 'block';
    };
    emailInput.oninput = () => {
        const q = emailInput.value.trim();
        if (searchTimer) clearTimeout(searchTimer);
        if (q.length < 2) {
            renderUserResults([]);
            return;
        }
        searchTimer = setTimeout(async () => {
            try {
                const url = new URL('/main', window.location.origin);
                url.searchParams.set('api', 'support_chat');
                url.searchParams.set('action', 'search_users');
                url.searchParams.set('q', q);
                const response = await fetch(url, { credentials: 'same-origin' });
                const data = await response.json();
                renderUserResults(data.ok && Array.isArray(data.users) ? data.users : []);
            } catch (_) {
                renderUserResults([]);
            }
        }, 200);
    };
    emailInput.value = '';
    emailInput.type = 'text';
    status.textContent = '';
    modal.style.display = 'flex';
    emailInput.focus();
    cancel.onclick = close;
    modal.onclick = (e) => {
        if (e.target === modal) close();
    };
    send.onclick = async () => {
        const email = emailInput.value.trim().toLowerCase();
        if (!email || !email.includes('@')) {
            status.textContent = cloudActionText('enterFullEmail', 'Enter full email');
            return;
        }
        send.disabled = true;
        cancel.disabled = true;
        status.textContent = cloudActionText('generatingLink', 'Generating link...');
        try {
            const shareData = typeof window.generateShareKey === 'function'
                ? await window.generateShareKey(normalizedPath, shareState.currentExpiresOption || 'never', root || getCurrentCloudRoot())
                : await api('share_file', { path: normalizedPath, root: root || getCurrentCloudRoot(), expires_option: shareState.currentExpiresOption || 'never' }, 'POST');
            if (!shareData?.key) throw new Error('Failed to generate share key');
            let metadata = {};
            try {
                metadata = await api('internal_share_metadata', { path: normalizedPath, root: root || getCurrentCloudRoot() }, 'GET');
            } catch (_) {}
            const sessionUrl = new URL('/main', window.location.origin);
            sessionUrl.searchParams.set('api', 'support_chat');
            sessionUrl.searchParams.set('action', 'session_info');
            const sessionResponse = await fetch(sessionUrl, { credentials: 'same-origin' });
            const sessionData = await sessionResponse.json();
            const isAdmin = !!sessionData?.is_admin;
            const formData = new FormData();
            formData.append('action', 'send_message');
            if (isAdmin) {
                formData.append('chat_type', 'support');
                formData.append('user_email', email);
                formData.append('send_as', 'support');
            } else {
                formData.append('chat_type', 'peer');
                formData.append('peer_email', email);
            }
            formData.append('message', JSON.stringify({
                type: 'cloud_attachment',
                share_id: shareData.key,
                name: metadata.name || path.split('/').pop(),
                ext: metadata.ext || String(path.split('.').pop() || '').toLowerCase(),
                size: Number(metadata.size || 0),
                mime: metadata.mime || '',
                preview_type: metadata.preview_type || 'file',
                preview: metadata.preview || ''
            }));
            const response = await fetch('/main?api=support_chat', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            });
            const data = await response.json();
            if (!data.ok) throw new Error(data.error || 'Send failed');
            close();
            flashStatus(cloudActionText('sentToChat', 'Sent to chat'));
        } catch (err) {
            status.textContent = err.message;
            send.disabled = false;
            cancel.disabled = false;
        }
    };
}
window.sendCloudShareToChat = sendCloudShareToChat;



// Add this function to get icons in the preview
function getFileIconForPreview(item) {
    return getItemIconMarkup(item);
}

// Also add a function to get the icon color by extension (optional)
function getDetailsTabTitle(properties) {
    if (properties.type && properties.type.includes('image')) return 'Image';
    if (properties.type && properties.type.includes('audio')) return 'Audio';
    if (properties.type && properties.type.includes('video')) return 'Video';
    if (properties.type && properties.type.includes('document')) return 'Document';
    return 'Details';
}
function renderGeneralProperties(properties, filePath) {
    return `
        <div class="property-row">
            <span class="property-label">${t(i18n, 'fileType')}:</span>
            <span class="property-value">${properties.type || 'Unknown'}</span>
        </div>
        <div class="property-row">
            <span class="property-label">${t(i18n, 'filePath')}:</span>
            <span class="property-value" title="${filePath}">${filePath}</span>
        </div>
        <div class="property-row">
            <span class="property-label">${t(i18n, 'fileSize')}:</span>
            <span class="property-value">${formatFileSize(properties.size)} (${properties.size} ${t(i18n, 'bytes')})</span>
        </div>
        ${properties.created ? `
        <div class="property-row">
            <span class="property-label">${t(i18n, 'createdDate')}:</span>
            <span class="property-value">${formatDate(properties.created)}</span>
        </div>
        ` : ''}
        ${properties.modified ? `
        <div class="property-row">
            <span class="property-label">${t(i18n, 'modifiedDate')}:</span>
            <span class="property-value">${formatDate(properties.modified)}</span>
        </div>
        ` : ''}
        ${properties.accessed ? `
        <div class="property-row">
            <span class="property-label">${t(i18n, 'accessedDate')}:</span>
            <span class="property-value">${formatDate(properties.accessed)}</span>
        </div>
        ` : ''}
        ${properties.attributes ? `
        <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid var(--border);">
            <h5 style="margin: 0 0 10px 0; color: var(--muted);">${t(i18n, 'fileAttributes')}</h5>
            ${renderAttributes(properties.attributes)}
        </div>
        ` : ''}
    `;
}
function formatExifKey(key) {
    const parts = key.split(':');
    const cleanKey = parts.length > 1 ? parts[1] : parts[0];
    return cleanKey
        .replace(/([A-Z])/g, ' $1')
        .replace(/^./, str => str.toUpperCase())
        .trim();
}

function formatFileSize(bytes) {
    if (bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}
function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleString();
}
function formatTimestampForFilename() {
    const d = new Date();
    const yyyy = d.getFullYear();
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    const dd = String(d.getDate()).padStart(2, '0');
    const hh = String(d.getHours()).padStart(2, '0');
    const mi = String(d.getMinutes()).padStart(2, '0');
    const ss = String(d.getSeconds()).padStart(2, '0');
    return `${yyyy}-${mm}-${dd}_${hh}-${mi}-${ss}`;
}
function formatDuration(seconds) {
    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    const secs = Math.floor(seconds % 60);
    if (hours > 0) {
        return `${hours}:${minutes.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
    } else {
        return `${minutes}:${secs.toString().padStart(2, '0')}`;
    }
}
function isLatexFile(path) {
  const ext = path.split('.').pop().toLowerCase();
  return ext === 'tex' || ext === 'latex';
}
function isMonacoEditFile(path) {
  if (!path || !isTextFile(path)) return false;
  if (isMarkdownFile(path) || isLatexFile(path) || (typeof isHtmlFile === 'function' && isHtmlFile(path))) return false;
  if (typeof isOfficeFile === 'function' && isOfficeFile(path)) return false;
  return true;
}
function opensDirectInEditor(path) {
  if (!path) return false;
  if (isMarkdownFile(path) || isLatexFile(path)) return true;
  if (typeof isHtmlFile === 'function' && isHtmlFile(path)) return true;
  return isMonacoEditFile(path);
}
function shouldHideEditMenuOption(path) {
  return opensDirectInEditor(path);
}
function shouldRenameOnTreeDblClick(path) {
  if (!path) return false;
  if (typeof isEditableImage === 'function' && isEditableImage(path)) return false;
  if (typeof isEditableAudio === 'function' && isEditableAudio(path)) return false;
  return true;
}
function openFileFromTree(path, root = null) {
  if (!path) return;
  if (isMarkdownFile(path)) {
    if (typeof openMarkdownEditor === 'function') openMarkdownEditor(path, root);
    return;
  }
  if (typeof isHtmlFile === 'function' && isHtmlFile(path)) {
    if (typeof openHtmlEditor === 'function') openHtmlEditor(path, root);
    return;
  }
  if (isLatexFile(path)) {
    openLatexEditor(path, root);
    return;
  }
  if (isMonacoEditFile(path)) {
    openFileInTab(path, true, root);
    return;
  }
  openFileInTab(path, false, root);
}
window.isLatexFile = isLatexFile;
window.isMonacoEditFile = isMonacoEditFile;
window.opensDirectInEditor = opensDirectInEditor;
window.shouldHideEditMenuOption = shouldHideEditMenuOption;
window.shouldRenameOnTreeDblClick = shouldRenameOnTreeDblClick;
window.openFileFromTree = openFileFromTree;
function isTextFile(path) {
  const ext = path.split('.').pop().toLowerCase();
  const textExts = ['txt', 'md', 'js', 'ts', 'json', 'html', 'css', 'php', 'py', 'rb', 'go', 'java', 'cs', 'cpp', 'c', 'h', 'hpp', 'sh', 'yml', 'yaml', 'sql', 'kt', 'swift', 'rs', 'lua', 'pl', 'r', 'toml', 'ini', 'dockerfile', 'vue', 'm','tex', 'latex'];
  const binaryExts = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 
                     'mp4', 'webm', 'ogg', 'mov', 'avi', 'mkv',  // video
                     'mp3', 'wav', 'ogg', 'm4a', 'flac'];        // audio
  if (binaryExts.includes(ext)) {
    return false;
  }
  return textExts.includes(ext);
}
function isEditableImage(path) {
    const ext = path.split('.').pop().toLowerCase();
    const imageExts = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'tiff'];
    return imageExts.includes(ext);
}
function flashStatus(text) {
  const footer = document.querySelector('.statusbar');
  const flashEl = document.getElementById('status-flash');
  const isMobileFooterFlash = window.matchMedia('(max-width: 768px)').matches;
  const getStatusKind = (value) => {
    const raw = String(value || '').trim();
    const lower = raw.toLowerCase();
    if (/^(✓|✔)/.test(raw) || /(success|successful|completed|ready|saved|created|updated|copied|renamed|uploaded|downloaded|restored|extracted|converted|applied|cleared|успеш|готов|готово|сохран|создан|создано|обнов|скопирован|переимен|загружен|скачив|восстанов|извлеч|примен|очищ|заверш|завершено|створ|оновл|збереж|завантаж|адноў|выкан)/i.test(raw)) return 'success';
    if (/^(✗|✕|×)/.test(raw) || /(error|failed|failure|cannot|could not|invalid|denied|blocked|not found|ошиб|не удалось|нельзя|невозможно|некоррект|заблок|відмов|помил|не вдалося|неможливо|няправ|памыл)/i.test(raw)) return 'error';
    if (/(warning|warn|confirm|attention|quota|limit|skipped|предупреж|вниман|подтверд|лимит|квот|пропущ|уваг|поперед|підтверд|ліміт|квота|прапушч|увага)/i.test(raw)) return 'warning';
    if (/(creating|loading|processing|searching|extracting|uploading|downloading|moving|deleting|recognizing|scan|tool|step|создан|создание|загрузка|обработка|поиск|перемещ|удален|распозна|скан|инструмент|шаг|створення|оброб|пошук|переміщ|видален|інструмент|крок)/i.test(raw)) return 'info';
    return 'neutral';
  };
  const renderStatusText = (el, value) => {
    if (!el) return;
    const raw = String(value || '');
    const escape = (s) => s.replace(/[&<>"']/g, (ch) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[ch]));
    const content = /^\s*(?:Tool|Step|Инструмент|Шаг)\s*:/i.test(raw)
      ? `<span class="status-tool-icon" aria-hidden="true"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.8-3.8a6 6 0 0 1-7.9 7.9l-6.8 6.8a2.1 2.1 0 0 1-3-3l6.8-6.8a6 6 0 0 1 7.9-7.9l-3.8 3.8z"/></svg></span><span class="status-tool-text">${escape(raw)}</span>`
      : escape(raw);
    if (/^\s*(?:Tool|Step|Инструмент|Шаг)\s*:/i.test(raw)) {
      el.innerHTML = content;
    } else {
      el.textContent = raw;
    }
  };
  if (!footer || !flashEl) {
    const fallbackEl = document.getElementById('status-right-content') || document.getElementById('status-right');
    if (!fallbackEl) return;
    renderStatusText(fallbackEl, text);
    setTimeout(() => {
      if (fallbackEl.textContent === String(text || '')) fallbackEl.textContent = '';
    }, 7000);
    return;
  }
  if (window.flashStatusTimer) {
    clearTimeout(window.flashStatusTimer);
    window.flashStatusTimer = null;
  }
  if (window.flashStatusCleanupTimer) {
    clearTimeout(window.flashStatusCleanupTimer);
    window.flashStatusCleanupTimer = null;
  }
  const statusKind = getStatusKind(text);
  flashEl.className = `status-kind-${statusKind}`;
  const raw = String(text || '');
  const escape = (s) => s.replace(/[&<>"']/g, (ch) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[ch]));
  const toolContent = /^\s*(?:Tool|Step|Инструмент|Шаг)\s*:/i.test(raw)
    ? `<span class="status-tool-icon" aria-hidden="true"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.8-3.8a6 6 0 0 1-7.9 7.9l-6.8 6.8a2.1 2.1 0 0 1-3-3l6.8-6.8a6 6 0 0 1 7.9-7.9l-3.8 3.8z"/></svg></span><span class="status-tool-text">${escape(raw)}</span>`
    : escape(raw);
  flashEl.innerHTML = `<span class="status-flash-text">${toolContent}</span><span class="status-flash-led" aria-hidden="true"></span>`;
  footer.classList.remove('is-flash-visible', 'is-flash-inline-visible');
  footer.classList.add(isMobileFooterFlash ? 'is-flash-visible' : 'is-flash-inline-visible');
  window.flashStatusTimer = setTimeout(() => {
    footer.classList.remove('is-flash-visible', 'is-flash-inline-visible');
    window.flashStatusCleanupTimer = setTimeout(() => {
      if (!footer.classList.contains('is-flash-visible') && !footer.classList.contains('is-flash-inline-visible')) {
        flashEl.textContent = '';
      }
      window.flashStatusCleanupTimer = null;
    }, 240);
    window.flashStatusTimer = null;
  }, 7000);
}
function showSuccessCheck(button,text='') {
  if (!button) return;
  if (button.__successTimer) clearTimeout(button.__successTimer);
  if (!button.dataset.successOriginalHtml) button.dataset.successOriginalHtml = button.innerHTML;
  const textPart = text ? `<span>${text}</span>` : '';
  button.innerHTML = `<svg class="success-check-icon" width="16" height="16" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.6" fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"></path></svg>${textPart}`;
  button.classList.add('success-check-active');
  button.disabled = true;
  button.__successTimer = setTimeout(() => {
    button.innerHTML = button.dataset.successOriginalHtml;
    button.classList.remove('success-check-active');
    button.disabled = false;
    button.__successTimer = null;
  }, 1300);
}
function isConvertibleFile(path) {
    const ext = path.split('.').pop().toLowerCase();
    const convertibleExts = [
        'docx', 'doc', 'pdf', 'odt', 'txt', 'html',
        'jpg', 'jpeg', 'png', 'webp', 'bmp', 'gif'
    ];
    return convertibleExts.includes(ext);
}
function showConvertDialog(filePath) {
    const modal = document.createElement('div');
    modal.className = 'modal';
    modal.style.display = 'flex';
    modal.innerHTML = `
        <div class="modal-content" style="max-width: 600px;">
            <div class="modal-header">
                <h3>
                    <svg width="20" height="20" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; margin-right: 8px;">
                        <path d="M8 17l4 4 4-4"></path>
                        <path d="M12 12v9"></path>
                        <path d="M20.88 18.09A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.29"></path>
                    </svg>
                    ${t(i18n, 'convertFile')}: ${filePath.split('/').pop()}
                </h3>
                <button class="icon-btn close-btn" aria-label="${escapeHtml(t(i18n, 'close') || 'Close')}">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>
                        <span style="vertical-align: middle; margin-right: 6px; display: inline-flex;">${getFileIcon(filePath)}</span>
                        ${t(i18n, 'selectTargetFormat')}
                    </label>
                    <select id="target-format-select" style="width: 100%; padding: 8px; margin: 10px 0;">
                        <option value="">-- ${t(i18n, 'selectTargetFormat')} --</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>
                        <svg width="16" height="16" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; margin-right: 6px;">
                            <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                            <polyline points="9 22 9 12 15 12 15 22"></polyline>
                        </svg>
                        ${t(i18n, 'outputPath')}
                    </label>
                    <select id="output-folder-select" style="width: 100%; padding: 8px; margin: 10px 0;">
                        <option value="">${t(i18n, 'sameFolder')}</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="animated-check-label">
                        <input type="checkbox" id="delete-original-checkbox" class="animated-checkbox"/>
                        <span class="animated-checkbox-box">
                          <svg width="24" height="18" viewBox="0 0 24 18" fill="none"><path d="M2 9L8.5 15.5L22 2" stroke="#fff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </span>
                        ${t(i18n, 'deleteOriginal')}
                    </label>
                </div>
                <div id="convert-supported-formats" class="supported-formats" style="margin-top: 15px; padding: 10px; background: var(--panel); border-radius: 4px;">
                    <strong>
                        <svg width="16" height="16" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; margin-right: 6px;">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                            <polyline points="14 2 14 8 20 8"></polyline>
                            <line x1="16" y1="13" x2="8" y2="13"></line>
                            <line x1="16" y1="17" x2="8" y2="17"></line>
                            <polyline points="10 9 9 9 8 9"></polyline>
                        </svg>
                        ${t(i18n, 'supportedFormats')}
                    </strong>
                    <div id="format-list" style="margin-top: 8px;"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button id="btn-cancel-convert" class="btn secondary">
                    <svg width="16" height="16" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; margin-right: 6px;">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                    ${t(i18n, 'cancel')}
                </button>
                <button id="btn-confirm-convert" class="btn primary">
                    <svg width="16" height="16" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; margin-right: 6px;">
                        <path d="M8 17l4 4 4-4"></path>
                        <path d="M12 12v9"></path>
                        <path d="M20.88 18.09A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.29"></path>
                    </svg>
                    ${t(i18n, 'convert')}
                </button>
            </div>
        </div>
    `;
    document.body.appendChild(modal);
    loadSupportedFormats(filePath, modal);
    loadFoldersForConversion(filePath, modal);
    document.getElementById('btn-cancel-convert').addEventListener('click', () => modal.remove());
    document.getElementById('btn-confirm-convert').addEventListener('click', () => confirmConversion(filePath, modal));
    modal.querySelector('.close-btn').addEventListener('click', () => modal.remove());
    modal.addEventListener('click', (e) => {
        if (e.target === modal) {
            modal.remove();
        }
    });
}
async function loadFoldersForConversion(filePath, modal) {
    try {
        const allFolders = await getAllFoldersRecursive('');
        const select = document.getElementById('output-folder-select');
        const sourceDir = dirname(filePath);
        while (select.children.length > 1) {
            select.removeChild(select.lastChild);
        }
        const rootOption = document.createElement('option');
        rootOption.value = '';
        rootOption.textContent = '/ (root)';
        if (sourceDir === '') {
            rootOption.selected = true;
        }
        select.appendChild(rootOption);
        allFolders.forEach(folder => {
            const option = document.createElement('option');
            option.value = folder.path;
            option.textContent = '/' + folder.path;
            if (folder.path === sourceDir) {
                option.selected = true;
            }
            select.appendChild(option);
        });
    } catch (err) {
        console.error('Failed to load folders:', err);
        flashStatus(t(i18n, 'folderListError'));
    }
}
function openLatexEditor(filePath, root = null) {
    const requestedRoot = root || null;
    const existingViewTab = tabsState.tabs.find(tab => tab.path === filePath && !tab.latexEditor && (!requestedRoot || getTabRoot(tab) === requestedRoot));
    const inheritedRoot = requestedRoot || (existingViewTab ? existingViewTab.root : null);
    if (existingViewTab) {
        closeTab(existingViewTab.id);
    }
    const existingEditorTab = tabsState.tabs.find(tab => tab.path === filePath && tab.latexEditor && (!requestedRoot || getTabRoot(tab) === requestedRoot));
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
        latexEditor: true,
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
    loadLatexEditorToTab(newTab);
    saveTabsState();
}
function loadLatexEditorToTab(tab) {
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
    document.getElementById('file-view').classList.remove('hidden');
    fileName.textContent = tab.title;
    viewer.dataset.path = tab.path;
    viewer.dataset.editMode = 'true';
    viewer.dataset.latexEditor = 'true';
    updateToolbarForLatexEditor();
    let iframe = viewer.querySelector('iframe[data-tab-id="' + tab.id + '"]');
    if (!iframe) {
        iframe = document.createElement('iframe');
        iframe.setAttribute('data-tab-id', tab.id);
        const urlParams = new URLSearchParams(window.location.search);
        const lang = urlParams.get('lang') || 'en';
        const theme = urlParams.get('theme') || 'dark';
        const color = urlParams.get('color') || '#85b503';
        
                // CHANGE HERE - use the saved fullPath if available
        let currentUser;
        let filePath;
        
        if (tab.fullPath) {
                        // Extract the user and path from fullPath
            const parts = tab.fullPath.split('/');
            if (parts.length >= 3 && parts[0] === 'storage') {
                currentUser = parts[1];
                filePath = parts.slice(2).join('/');
            } else {
                                // Fallback to the standard method
                currentUser = currentUserState.user || 'default';
                currentUser = currentUser.replace(/\s+/g, '_');
                filePath = tab.path;
            }
        } else {
            currentUser = currentUserState.user || 'default';
            currentUser = currentUser.replace(/\s+/g, '_');
            filePath = tab.path;
        }
        
        const latexEditorUrl = `utilities/projects/latex-editor/public/index.php?path=${encodeURIComponent(filePath)}&user=${encodeURIComponent(currentUser)}&lang=${lang}&theme=${theme}&color=${encodeURIComponent(color)}&root=${getResolvedTabRoot(tab)}`;
        iframe.src = latexEditorUrl;
        applyFullScreenStyles(iframe);
        viewer.appendChild(iframe);
        
                // Save fullPath for future use
        tab.fullPath = buildEditorStoragePath(currentUser, filePath, getResolvedTabRoot(tab));
    } else {
        applyFullScreenStyles(iframe);
        iframe.style.display = 'block';
    }
}
function updateToolbarForLatexEditor() {
    const saveBtn = document.getElementById('btn-save');
    const codeActionsBtn = document.getElementById('btn-code-actions');
    const copyBtn = document.getElementById('btn-copy');
    saveBtn.classList.add('hidden');
    codeActionsBtn.classList.add('hidden');
    if (copyBtn) copyBtn.classList.add('hidden');
}
function updateToolbarForZipViewer() {
    const saveBtn = document.getElementById('btn-save');
    const codeActionsBtn = document.getElementById('btn-code-actions');
    const copyBtn = document.getElementById('btn-copy');
    saveBtn.classList.add('hidden');
    codeActionsBtn.classList.add('hidden');
    if (copyBtn) copyBtn.classList.add('hidden');
}
function initDeleteModal() {
    const modal = document.getElementById('delete-modal');
    const cancelBtn = document.getElementById('btn-cancel-delete');
    const confirmBtn = document.getElementById('btn-confirm-delete');
    const closeBtn = document.getElementById('btn-close-delete-modal');
    const permanentCheckbox = document.getElementById('delete-permanently-checkbox');
    document.getElementById('btn-delete-selected')?.addEventListener('click', deleteSelectedItems);
    cancelBtn.addEventListener('click', closeDeleteModal);
    closeBtn.addEventListener('click', closeDeleteModal);
    confirmBtn.addEventListener('click', confirmDelete);
    permanentCheckbox?.addEventListener('change', () => {
        deleteState.permanent = !!permanentCheckbox.checked;
    });
    modal.addEventListener('click', (e) => {
        if (e.target === modal) {
            closeDeleteModal();
        }
    });
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && !modal.classList.contains('hidden')) {
            closeDeleteModal();
        }
    });
}
function showDeleteModal(path, type, name, root = null) {
    const inTrash = isTrashRoot();
    const permanentCheckbox = document.getElementById('delete-permanently-checkbox');
    const permanentOption = document.getElementById('delete-permanent-option');
    const warningText = document.getElementById('delete-warning-text');
    const permanent = inTrash;
    deleteState = { path, type, name, root: root || getCurrentCloudRoot(), permanent };
    const modal = document.getElementById('delete-modal');
    const title = document.getElementById('delete-modal-title');
    const text = document.getElementById('delete-confirmation-text');
    if (title) {
        title.textContent = inTrash ? t(i18n, 'deletePermanently') : t(i18n, 'deleteConfirmation');
    }
    if (type === 'file') {
        text.textContent = inTrash ? t(i18n, 'deleteFilePermanentlyConfirmation', { name }) : t(i18n, 'deleteFileConfirmation', { name });
    } else if (type === 'dir') {
        text.textContent = inTrash ? t(i18n, 'deleteFolderPermanentlyConfirmation', { name }) : t(i18n, 'deleteFolderConfirmation', { name });
    } else {
        text.textContent = t(i18n, 'deleteItemConfirmation', { name });
    }
    if (permanentCheckbox) permanentCheckbox.checked = permanent;
    if (permanentOption) permanentOption.classList.toggle('hidden', inTrash);
    if (warningText) {
        warningText.textContent = inTrash ? t(i18n, 'trashPermanentDeleteWarning') : t(i18n, 'trashMoveWarning');
    }
    modal.classList.remove('hidden');
    setTimeout(() => {
        document.getElementById('btn-cancel-delete').focus();
    }, 100);
}
function closeDeleteModal() {
    document.getElementById('delete-modal').classList.add('hidden');
    deleteState = { path: '', type: '', name: '', root: '', permanent: false };
}
async function confirmDelete() {
    const { path, type, root, permanent } = deleteState;
    if (!path) {
        closeDeleteModal();
        return;
    }
    const confirmBtn = document.getElementById('btn-confirm-delete');
    const originalText = confirmBtn.textContent;
    try {
        confirmBtn.textContent = t(i18n, 'deleting');
        confirmBtn.disabled = true;
        if (isTrashRoot() || permanent) {
            await api('delete', { path, root });
            await cleanupDeletedAudioTranscriptions([path]);
        } else {
            await api('move_to_trash', { path, root });
        }
        closeTabsByPath(path);
        if (type === 'file') {
            flashStatus(isTrashRoot() || permanent ? t(i18n, 'fileDeleted') : t(i18n, 'fileMovedToTrash'));
        } else {
            flashStatus(isTrashRoot() || permanent ? t(i18n, 'folderDeleted') : t(i18n, 'folderMovedToTrash'));
        }
        confirmBtn.textContent = originalText;
        confirmBtn.disabled = false;
        closeDeleteModal();
        if (isTrashRoot()) {
          await renderTrashView();
        } else {
          const currentRoot = getCurrentCloudRoot();
          if (typeof patchTreeCacheForRoot === 'function') {
            await patchTreeCacheForRoot([{ op: 'remove', path }], currentRoot);
          } else if (currentRoot === 'main') {
            await patchTreeCache([{ op: 'remove', path }]);
          }
          if (currentRoot === 'main') {
            treeNodeRemove(path);
          } else {
            await buildTree('');
          }
        }
        removeAdvancedSearchResult(path);
        updateSelectionUI();
        if (document.getElementById('viewer').dataset.path === path) {
            closeViewer();
        }
    } catch (err) {
        flashStatus(t(i18n, 'errorPrefix') + err.message);
        confirmBtn.textContent = originalText;
        confirmBtn.disabled = false;
    }
}
function dirname(path) {
    const lastSlash = path.lastIndexOf('/');
    return lastSlash === -1 ? '' : path.substring(0, lastSlash);
}
function loadSupportedFormats(filePath, modal) {
    const fileExt = filePath.split('.').pop().toLowerCase();
    const formatSelect = document.getElementById('target-format-select');
    const formatList = document.getElementById('format-list');
    const supportedConversions = {
        'docx': ['pdf', 'txt', 'odt', 'html'],
        'doc': ['pdf', 'txt', 'odt', 'html'],
        'pdf': ['docx', 'txt', 'html'],
        'odt': ['pdf', 'docx', 'txt', 'html'],
        'jpg': ['png', 'webp', 'bmp', 'gif'],
        'jpeg': ['png', 'webp', 'bmp', 'gif'],
        'png': ['jpg', 'webp', 'bmp', 'gif'],
        'webp': ['jpg', 'png', 'bmp', 'gif'],
        'bmp': ['jpg', 'png', 'webp', 'gif'],
        'gif': ['jpg', 'png', 'webp', 'bmp']
    };
    const formats = supportedConversions[fileExt] || [];
    formatSelect.innerHTML = '<option value="">-- ' + t(i18n, 'selectTargetFormat') + ' --</option>';
    formats.forEach(format => {
        const option = document.createElement('option');
        option.value = format;
        option.textContent = '.' + format;
        formatSelect.appendChild(option);
    });
    if (formats.length > 0) {
        formatList.innerHTML = formats.map(format => 
            `<span class="format-tag">.${format}</span>`
        ).join('');
    } else {
        formatList.innerHTML = `<span style="color: var(--muted);">${t(i18n, 'unsupportedConversion')}</span>`;
        document.getElementById('btn-confirm-convert').disabled = true;
    }
}
async function confirmConversion(filePath, modal) {
    const formatSelect = document.getElementById('target-format-select');
    const folderSelect = document.getElementById('output-folder-select');
    const deleteOriginal = document.getElementById('delete-original-checkbox').checked;
    const targetFormat = formatSelect.value;
    if (!targetFormat) {
        flashStatus(t(i18n, 'selectTargetFormat'));
        return;
    }
    const confirmBtn = document.getElementById('btn-confirm-convert');
    const originalText = confirmBtn.textContent;
    try {
        confirmBtn.textContent = t(i18n, 'convertingFile');
        confirmBtn.disabled = true;
        const sourceDir = dirname(filePath);
        const sourceFilename = pathinfo(filePath, 'filename');
        const selectedFolder = folderSelect.value;
        let outputPath = '';
        if (selectedFolder === '') {
            outputPath = sourceFilename + '.' + targetFormat;
        } else {
            outputPath = selectedFolder + '/' + sourceFilename + '.' + targetFormat;
        }
        if (!deleteOriginal) {
            let counter = 1;
            let testPath = outputPath;
            const pathParts = outputPath.split('/');
            const fileName = pathParts.pop();
            const fileExt = fileName.split('.').pop();
            const baseName = fileName.substring(0, fileName.lastIndexOf('.'));
            const folderPath = pathParts.join('/');
            while (await fileExists(testPath)) {
                const newFileName = baseName + '_' + counter + '.' + fileExt;
                testPath = folderPath ? folderPath + '/' + newFileName : newFileName;
                counter++;
            }
            outputPath = testPath;
        }
        const result = await api('convert_file', {
            source_path: filePath,
            target_format: targetFormat,
            output_path: outputPath,
            delete_original: deleteOriginal
        });
        flashStatus(t(i18n, 'conversionSuccess'));
        modal.remove();
        // Incremental update: add output file, optionally remove original
        const now = Math.floor(Date.now() / 1000);
        const ops = [{ op: 'add', item: { name: outputPath.split('/').pop(), path: outputPath, type: 'file', size: 0, mtime: now } }];
        if (deleteOriginal) ops.push({ op: 'remove', path: filePath });
        await patchTreeCache(ops);
        ops.forEach(op => { if (op.op === 'add') treeNodeAdd(op.item); else treeNodeRemove(op.path); });
        if (deleteOriginal && document.getElementById('viewer').dataset.path === filePath) {
            closeViewer();
        }
    } catch (err) {
        flashStatus(t(i18n, 'conversionFailed') + ': ' + err.message);
        confirmBtn.textContent = originalText;
        confirmBtn.disabled = false;
    }
}
function pathinfo(path, options) {
    const lastDot = path.lastIndexOf('.');
    const lastSlash = path.lastIndexOf('/');
    const filename = lastSlash === -1 ? path : path.substring(lastSlash + 1);
    if (lastDot === -1 || lastDot < lastSlash) {
        if (options === 'filename') {
            return filename;
        }
        return { filename: filename, extension: '', basename: filename };
    }
    const basename = filename.substring(0, filename.lastIndexOf('.'));
    const extension = path.substring(lastDot + 1);
    if (options === 'filename') {
        return basename;
    }
    return {
        filename: filename,
        extension: extension,
        basename: basename
    };
}
async function fileExists(filePath) {
    try {
        await api('open', { path: filePath }, 'GET');
        return true;
    } catch (err) {
        return false;
    }
}
function switchInputLanguage() {
    const langOptions = document.querySelectorAll('.lang-option');
    const currentActive = document.querySelector('.lang-option.active');
    const currentIndex = Array.from(langOptions).indexOf(currentActive);
    const nextIndex = (currentIndex + 1) % langOptions.length;
    langOptions.forEach(opt => opt.classList.remove('active'));
    langOptions[nextIndex].classList.add('active');
    const newLang = langOptions[nextIndex].dataset.lang;
    localStorage.setItem('preferredLang', newLang);
}
function switchToNextTab() {
    if (tabsState.tabs.length === 0) return;
    const currentIndex = tabsState.tabs.findIndex(tab => tab.id === tabsState.activeTabId);
    if (currentIndex === -1) {
        const firstTab = tabsState.tabs[0];
        if (firstTab) {
            switchToTab(firstTab.id);
        }
        return;
    }
    const nextIndex = (currentIndex + 1) % tabsState.tabs.length;
    const nextTab = tabsState.tabs[nextIndex];
    switchToTab(nextTab.id);
    flashStatus(t(i18n, 'switchedToTab', { title: nextTab.title }));
}
function switchToPrevTab() {
    if (tabsState.tabs.length === 0) return;
    const currentIndex = tabsState.tabs.findIndex(tab => tab.id === tabsState.activeTabId);
    if (currentIndex === -1) {
        const lastTab = tabsState.tabs[tabsState.tabs.length - 1];
        if (lastTab) {
            switchToTab(lastTab.id);
        }
        return;
    }
    const prevIndex = (currentIndex - 1 + tabsState.tabs.length) % tabsState.tabs.length;
    const prevTab = tabsState.tabs[prevIndex];
    switchToTab(prevTab.id);
    flashStatus(t(i18n, 'switchedToTab', { title: prevTab.title }));
}
function isAssistantOverlayOpen() {
  const modal = document.getElementById('ai-modal');
  return !!(modal && !modal.classList.contains('hidden'));
}

function handleHotkeys(e, i18n) {
  const mac = navigator.platform.toUpperCase().includes('MAC');
  const mod = mac ? e.metaKey : e.ctrlKey;
  const isInput = e.target.matches('input, textarea, [contenteditable="true"]');
  const isCodeMirror = e.target.closest('.CodeMirror');

    // Function keys and global combos ALWAYS work,
    // regardless of where the focus is (input, textarea, editor, etc.)
  const isGlobalHotkey = (
    e.key === 'F9' ||
    e.key === 'F8' ||
    e.key === 'F5' ||
    (mod && e.key.toLowerCase() === 's') ||
    (mod && e.shiftKey && e.key.toLowerCase() === 'a') ||
    (e.shiftKey && mod && e.key === 'ArrowRight') ||
    (e.shiftKey && mod && e.key === 'ArrowLeft')
  );

    // Block early exit only for NON-global hotkeys in regular input fields
  if (isInput && !isCodeMirror && !isGlobalHotkey) {
    return;
  }

  // The assistant modal is the topmost layer: Escape must close it first, even
  // when a tree selection or lasso is active underneath.
  if (e.key === 'Escape' && isAssistantOverlayOpen()) return;

  if (isTrashRoot()) {
    if (mod && e.key.toLowerCase() === 'a') {
      e.preventDefault();
      selectionState.active = true;
      setTrashSelection(trashState.items.map((item) => item.path), trashState.items.length ? 0 : -1);
      focusTrashItem(0);
      return;
    }
    if (mod && e.key.toLowerCase() === 'r') {
      e.preventDefault();
      restoreSelectedTrashItems();
      return;
    }
    if (e.key === 'Delete' && selectionState.selectedItems.size > 0) {
      e.preventDefault();
      deleteSelectedItems();
      return;
    }
    if (e.key === 'Escape') {
      e.preventDefault();
      clearSelection();
      return;
    }
    if (e.key === ' ' && trashState.focusedIndex !== -1) {
      e.preventDefault();
      toggleTrashItemSelection(trashState.focusedIndex, true, false);
      return;
    }
    if (e.key === 'Enter' && trashState.focusedIndex !== -1) {
      e.preventDefault();
      const item = trashState.items[trashState.focusedIndex];
      if (item) restoreTrashItems([item.path]);
      return;
    }
    const navigationKeys = ['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown', 'Home', 'End'];
    if (navigationKeys.includes(e.key) && trashState.items.length) {
      e.preventDefault();
      const cols = getTrashGridColumnCount();
      const current = trashState.focusedIndex === -1 ? 0 : trashState.focusedIndex;
      let next = current;
      if (e.key === 'ArrowLeft') next = current - 1;
      if (e.key === 'ArrowRight') next = current + 1;
      if (e.key === 'ArrowUp') next = current - cols;
      if (e.key === 'ArrowDown') next = current + cols;
      if (e.key === 'Home') next = 0;
      if (e.key === 'End') next = trashState.items.length - 1;
      focusTrashItem(next, e.shiftKey);
      if (!e.shiftKey && !mod) {
        toggleTrashItemSelection(Math.max(0, Math.min(next, trashState.items.length - 1)), false, false);
      }
      return;
    }
  }

    // Global hotkeys for selection mode (always work)
  if (selectionState.active && selectionState.selectedItems.size > 0) {
        // Escape - cancel selection
    if (e.key === 'Escape') {
      e.preventDefault();
      e.stopPropagation();
      clearSelection();
      flashStatus(t(i18n, 'selectionCleared'));
      return;
    }

        // Delete - delete the selected files
    if (e.key === 'Delete') {
      e.preventDefault();
      e.stopPropagation();
      deleteSelectedItems();
      return;
    }

        // D - download the selected files
    if (e.key.toLowerCase() === 'd' && !mod && !e.shiftKey && !e.altKey) {
      e.preventDefault();
      e.stopPropagation();
      downloadSelectedItems();
      return;
    }
  }

    // Escape - close the lasso if active
  if (e.key === 'Escape' && lassoState.active) {
    e.preventDefault();
    e.stopPropagation();
    lassoState.active = false;
    lassoState.element.style.display = 'none';
    document.body.style.userSelect = '';
    return;
  }

  if (e.shiftKey && mod && e.key === 'ArrowRight') {
    e.preventDefault();
    e.stopPropagation();
    switchToNextTab();
    return;
  }
  if (e.shiftKey && mod && e.key === 'ArrowLeft') {
    e.preventDefault();
    e.stopPropagation();
    switchToPrevTab();
    return;
  }

  if (e.key === 'F9') {
    e.preventDefault();
    e.stopPropagation();
    switchInputLanguage();
    return;
  }
  if (e.key === 'F8') {
    e.preventDefault();
    e.stopPropagation();
    const viewer = document.getElementById('viewer');
    const path = viewer.dataset.path;
    if (path) {
      const ext = path.split('.').pop().toLowerCase();
      const supportedLangs = ['java', 'c', 'cpp', 'py', 'm'];
      if (supportedLangs.includes(ext)) {
        compileAndRun();
      } else {
        flashStatus(t(i18n, 'fileTypeNotCompilable'));
      }
    } else {
      flashStatus(t(i18n, 'noFileOpen'));
    }
    return;
  }
  if (e.key === 'F5') {
    e.preventDefault();
    e.stopPropagation();
    document.getElementById('btn-refresh-tree')?.click();
    return;
  }
  if (mod && e.key.toLowerCase() === 'k') {
    e.preventDefault();
    flashStatus(t(i18n, 'searchHint'));
  }
  if (mod && e.key.toLowerCase() === 's') {
    e.preventDefault();
    e.stopPropagation();
    saveFile().then((ok) => {
      if (ok) showSuccessCheck(document.getElementById('btn-save'));
    });
    return;
  }
  if (e.key === 'Escape' && searchState.active) {
    e.preventDefault();
    closeSearchPanel();
  }
  if (mod && e.shiftKey && e.key.toLowerCase() === 'a') {
    e.preventDefault();
    e.stopPropagation();
    toggleAIModal();
    return;
  }
  if (mod && e.key === ' ') {
    e.preventDefault();
    editor.showHint({
      hint: getHintFunction(fileExt),
      completeSingle: false
    });
    return;
  }
}
function loadZipViewerToTab(tab) {
    const viewer = document.getElementById('viewer');
    const fileName = document.getElementById('file-name');
    
    document.getElementById('file-view').classList.remove('hidden');
    fileName.textContent = tab.title;
    viewer.dataset.path = tab.path;
    viewer.dataset.editMode = 'false';
    viewer.dataset.zipViewer = 'true';
    
    updateToolbarForZipViewer();
    
        // First hide all iframes
    const allIframes = viewer.querySelectorAll('iframe');
    allIframes.forEach(iframe => {
        iframe.style.display = 'none';
    });
    
        // Check if there is already a cached iframe for this tab
    let iframe = tabsState.iframeCache.get(tab.id);
    
    if (!iframe || !iframe.parentNode) {
                // If not in the cache or it was removed, create a new one
        iframe = document.createElement('iframe');
        iframe.setAttribute('data-tab-id', tab.id);
        
        const urlParams = new URLSearchParams(window.location.search);
        const lang = urlParams.get('lang') || 'en';
        const theme = document.body.getAttribute('data-theme') || 'dark';
        const folderColor = window.APP_CONFIG.color || '#85b503';
        
        let currentUser = currentUserState.user || 'default';
        currentUser = currentUser.replace(/\s+/g, '_');
        const root = getResolvedTabRoot(tab);
        const fullPath = buildEditorStoragePath(currentUser, tab.path, root);
        const zipPath = encodeURIComponent(fullPath);
        tab.fullPath = fullPath;
        
        const zipViewerUrl = `utilities/projects/zip-viewer/public/index.php?archive=${zipPath}&path=${encodeURIComponent(tab.path)}&user=${encodeURIComponent(currentUser)}&root=${encodeURIComponent(root)}&lang=${lang}&theme=${theme}&folderColor=${encodeURIComponent(folderColor)}`;
        iframe.src = zipViewerUrl;
        tab.iframeSrc = zipViewerUrl;
        
        iframe.onerror = function() {
            console.error('Failed to load ZIP viewer');
            const errorDiv = document.createElement('div');
            errorDiv.style.cssText = `
                position: absolute;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                color: var(--muted);
                text-align: center;
                z-index: 100;
            `;
            errorDiv.innerHTML = `
                <i class="fa-solid fa-circle-exclamation" style="font-size: 48px; margin-bottom: 16px; color: #e74c3c;"></i>
                <p>Failed to load archive. Please try again.</p>
                <button onclick="location.reload()" class="btn primary" style="margin-top: 16px;">Reload</button>
            `;
            iframe.parentNode.appendChild(errorDiv);
        };
        
        applyFullScreenStyles(iframe);
        viewer.appendChild(iframe);
        
                // Save to the cache
        tabsState.iframeCache.set(tab.id, iframe);
    } else {
                // If in the cache, just show it
        applyFullScreenStyles(iframe);
        iframe.style.display = 'block';
    }
    
        // Make sure all other iframes are hidden
    viewer.querySelectorAll('iframe').forEach(f => {
        if (parseInt(f.getAttribute('data-tab-id')) !== tab.id) {
            f.style.display = 'none';
        }
    });
}




// Download progress state
let downloadState = {
    active: false,
    items: new Map(), // downloadId -> { filename, path, progress, speed, totalSize, loaded, chunks, ... }
    nextId: 1,
    panelVisible: false
};

// Initialize floating download button
