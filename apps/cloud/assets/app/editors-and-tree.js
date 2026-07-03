function openPhotoEditor(filePath) {
    const existingViewTab = tabsState.tabs.find(tab => tab.path === filePath && !tab.photoEditor);
    const inheritedRoot = existingViewTab ? existingViewTab.root : null;
    if (existingViewTab) {
        closeTab(existingViewTab.id);
    }
    const existingEditorTab = tabsState.tabs.find(tab => tab.path === filePath && tab.photoEditor);
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
        photoEditor: true,
        root: inheritedRoot || getCurrentCloudRoot(),
        title: filePath.split('/').pop(),
        modified: false,
        content: null
    };
    if (tabsState.activeTabId) {
        const prevTab = tabsState.tabs.find((t) => t.id === tabsState.activeTabId);
        if (prevTab && typeof stashTabView === 'function') stashTabView(prevTab);
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
    loadPhotoEditorToTab(newTab);
    saveTabsState();
}
function loadPhotoEditorToTab(tab) {
    const viewer = document.getElementById('viewer');
    const fileName = document.getElementById('file-name');
    if (typeof hideAllTabPanels === 'function') {
        hideAllTabPanels(tab.id);
    } else {
    const fileContainers = viewer.querySelectorAll('.file-content-container');
    fileContainers.forEach(container => {
        container.style.display = 'none';
    });
    }
    viewer.style.cssText = `
        position: relative;
        width: 100%;
        height: 100%;
        overflow: hidden;
    `;
    const fileExt = tab.path.split('.').pop().toLowerCase();
    const supportedImageFormats = ['jpg', 'jpeg', 'png', 'webp', 'bmp', 'gif'];
    if (!supportedImageFormats.includes(fileExt)) {
        flashStatus(t(i18n, 'unsupportedImageFormat'));
        return;
    }
    document.getElementById('file-view').classList.remove('hidden');
    fileName.textContent = tab.title;
    viewer.dataset.path = tab.path;
    viewer.dataset.editMode = 'true';
    viewer.dataset.photoEditor = 'true';
    updateToolbarForPhotoEditor();
    let iframe = viewer.querySelector('iframe[data-tab-id="' + tab.id + '"]');
    if (!iframe) {
        iframe = document.createElement('iframe');
        iframe.setAttribute('data-tab-id', tab.id);
        iframe.setAttribute('tabindex', '0');
        const urlParams = new URLSearchParams(window.location.search);
        const lang = urlParams.get('lang') || 'en';
        const theme = urlParams.get('theme') || 'dark';
        const color = urlParams.get('color') || '#85b503';
        
        let currentUser = currentUserState.user || 'default';
        currentUser = currentUser.replace(/\s+/g, '_');
        const root = getResolvedTabRoot(tab);
        const fullPath = buildEditorStoragePath(currentUser, tab.path, root);
        const folderPath = tab.path.substring(0, tab.path.lastIndexOf('/'));
        getImagesInFolder(folderPath, root).then(imagesInFolder => {
            const photoEditorUrl = `utilities/projects/photo-editor/public/index.php?path=${encodeURIComponent(fullPath)}&user=${encodeURIComponent(currentUser)}&lang=${lang}&theme=${theme}&color=${encodeURIComponent(color)}&root=${root}&folder=${encodeURIComponent(folderPath)}&images=${encodeURIComponent(JSON.stringify(imagesInFolder))}`;
            iframe.src = photoEditorUrl;
        });
        applyFullScreenStyles(iframe);
        viewer.appendChild(iframe);
    } else {
        applyFullScreenStyles(iframe);
        iframe.style.display = 'block';
    }
}
window.addEventListener('resize', handleIframeResize);
function handleIframeResize() {
    const viewer = document.getElementById('viewer');
    if (!viewer) return;
    const state = window.tabsState;
    if (!state?.tabs?.length) return;
    const activeTab = state.tabs.find(tab => tab.id === state.activeTabId);
    if (!activeTab) return;
    const iframe = viewer.querySelector('iframe[data-tab-id="' + activeTab.id + '"]');
    if (iframe) {
        iframe.style.width = '100%';
        iframe.style.height = '100%';
        setTimeout(() => {
            applyFullScreenStyles(iframe);
        }, 150);
    }
}
function updateTabPathAfterSave(tabId, newRelativePath) {
    const tab = tabsState.tabs.find(t => t.id === tabId);
    if (tab) {
        tab.path = newRelativePath;
        tab.title = newRelativePath.split('/').pop();
        renderTabs();
        saveTabsState();
    }
}
function updateToolbarForPhotoEditor() {
    const saveBtn = document.getElementById('btn-save');
    const codeActionsBtn = document.getElementById('btn-code-actions');
    saveBtn.classList.add('hidden');
    codeActionsBtn.classList.add('hidden');
}

function closeTabsByPath(filePath) {
    const tabsToClose = tabsState.tabs.filter(tab => tab.path === filePath);
    let closedCount = 0;
    tabsToClose.forEach(tab => {
        const tabIndex = tabsState.tabs.findIndex(t => t.id === tab.id);
        if (tabIndex !== -1) {
            teardownTabResources(tab.id);
            tabsState.tabs.splice(tabIndex, 1);
            tabsState.contentCache.delete(tab.id);
            closedCount++;
            if (tabsState.activeTabId === tab.id) {
                if (tabsState.tabs.length > 0) {
                    const newActiveIndex = Math.min(tabIndex, tabsState.tabs.length - 1);
                    tabsState.activeTabId = tabsState.tabs[newActiveIndex].id;
                    restoreTabContent(tabsState.tabs[newActiveIndex]);
                } else {
                    tabsState.activeTabId = null;
                    closeViewer();
                }
            }
        }
    });
    if (closedCount > 0) {
        renderTabs();
        saveTabsState();
    }
    return closedCount;
}
function teardownTabResources(tabId) {
    clearAutoSaveTimer(tabId);
    stopMonacoCollabSession(tabId);
    if (window.monacoManager) {
        window.monacoManager.dispose(tabId);
    }
    if (editorsMap.has(tabId)) {
        editorsMap.delete(tabId);
    }
    const viewer = document.getElementById('viewer');
    if (!viewer) return;
    const iframe = viewer.querySelector('iframe[data-tab-id="' + tabId + '"]');
    if (iframe) {
        const iframeClone = iframe.cloneNode(true);
        iframeClone.style.display = 'none';
        tabsState.iframeCache.set(tabId, iframeClone);
        iframe.remove();
    } else {
        tabsState.iframeCache.delete(tabId);
    }
    const fileContainer = viewer.querySelector('.file-content-container[data-tab-id="' + tabId + '"]');
    if (fileContainer) {
        fileContainer.remove();
    }
}
function openTranscriptionEditor(filePath) {
    const existingViewTab = tabsState.tabs.find(tab => tab.path === filePath && !tab.transcriptionEditor);
    const inheritedRoot = existingViewTab ? existingViewTab.root : null;
    if (existingViewTab) {
        closeTab(existingViewTab.id);
    }
    const existingEditorTab = tabsState.tabs.find(tab => tab.path === filePath && tab.transcriptionEditor);
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
        transcriptionEditor: true,
        root: inheritedRoot || getCurrentCloudRoot(),
        title: filePath.split('/').pop() + ' (Transcription)',
        modified: false,
        content: null
    };
    if (tabsState.activeTabId) {
        const prevTab = tabsState.tabs.find((t) => t.id === tabsState.activeTabId);
        if (prevTab && typeof stashTabView === 'function') stashTabView(prevTab);
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
    loadTranscriptionEditorToTab(newTab);
    saveTabsState();
}
function loadTranscriptionEditorToTab(tab) {
    const viewer = document.getElementById('viewer');
    const fileName = document.getElementById('file-name');
    const fileExt = tab.path.split('.').pop().toLowerCase();
    const supportedAudioFormats = ['mp3', 'wav', 'ogg', 'm4a', 'flac', 'aac'];
    if (!supportedAudioFormats.includes(fileExt)) {
        flashStatus(t(i18n, 'unsupportedAudioFormat'));
        return;
    }
    document.getElementById('file-view').classList.remove('hidden');
    fileName.textContent = tab.title;
    viewer.dataset.path = tab.path;
    viewer.dataset.editMode = 'true';
    viewer.dataset.transcriptionEditor = 'true';
    updateToolbarForTranscriptionEditor();
    let iframe = viewer.querySelector('iframe[data-tab-id="' + tab.id + '"]');
    if (!iframe) {
        iframe = document.createElement('iframe');
        iframe.setAttribute('data-tab-id', tab.id);
        iframe.setAttribute('tabindex', '0');
        const urlParams = new URLSearchParams(window.location.search);
        const lang = urlParams.get('lang') || 'en';
        const theme = urlParams.get('theme') || 'dark';
        const color = urlParams.get('color') || '#85b503';
        
        let currentUser = currentUserState.user || 'default';
        currentUser = currentUser.replace(/\s+/g, '_');
        const transcriptionEditorUrl = `utilities/projects/transcription-editor/public/index.php?path=${encodeURIComponent(tab.path)}&user=${encodeURIComponent(currentUser)}&lang=${lang}&theme=${theme}&color=${encodeURIComponent(color)}&root=${getResolvedTabRoot(tab)}`;
        iframe.src = transcriptionEditorUrl;
        iframe.style.width = '100%';
        iframe.style.height = '100%';
        iframe.style.border = 'none';
        iframe.style.background = 'var(--bg)';
        viewer.appendChild(iframe);
    } else {
        iframe.style.display = 'block';
    }
}
function updateToolbarForTranscriptionEditor() {
    const saveBtn = document.getElementById('btn-save');
    const codeActionsBtn = document.getElementById('btn-code-actions');
    saveBtn.classList.add('hidden');
    codeActionsBtn.classList.add('hidden');
}

function loadTTSEditorToTab(tab) {
    const viewer = document.getElementById('viewer');
    const fileName = document.getElementById('file-name');
    const fileExt = tab.path.split('.').pop().toLowerCase();

    if (fileExt !== 'txt') {
        flashStatus('Only .txt files are supported for TTS');
        return;
    }

    document.getElementById('file-view').classList.remove('hidden');
    fileName.textContent = tab.title;
    viewer.dataset.path = tab.path;
    viewer.dataset.editMode = 'true';
    viewer.dataset.ttsEditor = 'true';
    updateToolbarForTTSEditor();

    let iframe = viewer.querySelector('iframe[data-tab-id="' + tab.id + '"]');
    if (!iframe) {
        iframe = document.createElement('iframe');
        iframe.setAttribute('data-tab-id', tab.id);
        const urlParams = new URLSearchParams(window.location.search);
        const lang = urlParams.get('lang') || 'en';
        const theme = urlParams.get('theme') || 'dark';
        const color = urlParams.get('color') || '#85b503';

        let currentUser = currentUserState.user || 'default';
        currentUser = currentUser.replace(/\s+/g, '_');
        const ttsEditorUrl = `utilities/projects/tts-editor/public/index.php?path=${encodeURIComponent(tab.path)}&user=${encodeURIComponent(currentUser)}&lang=${lang}&theme=${theme}&color=${encodeURIComponent(color)}&root=${getResolvedTabRoot(tab)}`;
        iframe.src = ttsEditorUrl;
        iframe.style.width = '100%';
        iframe.style.height = '100%';
        iframe.style.border = 'none';
        iframe.style.background = 'var(--bg)';
        viewer.appendChild(iframe);
    } else {
        iframe.style.display = 'block';
    }
}

function updateToolbarForTTSEditor() {
    const saveBtn = document.getElementById('btn-save');
    const codeActionsBtn = document.getElementById('btn-code-actions');
    saveBtn.classList.add('hidden');
    codeActionsBtn.classList.add('hidden');
}

function handleEmbeddedEditorMessage(event) {
    const data = event && typeof event.data === 'object' ? event.data : null;
    if (!data || data.type !== 'fileCreated') return;
    const createdPath = normalizeTreePathForDom(data.path || '');
    if (!createdPath) return;
    const root = typeof data.root === 'string' && data.root ? data.root : getCurrentCloudRoot();
    const name = data.name || createdPath.split('/').pop();
    insertTreeItem({
        path: createdPath,
        name,
        type: 'file'
    }, root).catch((err) => {
        console.warn('Embedded editor fileCreated sync failed', err);
    });
}

window.addEventListener('message', handleEmbeddedEditorMessage);

function isEditableAudio(path) {
    const ext = path.split('.').pop().toLowerCase();
    const audioExts = ['mp3', 'wav', 'ogg', 'm4a', 'flac', 'aac'];
    return audioExts.includes(ext);
}



function adjustContextMenuPosition(menu, clickX, clickY) {
    const menuRect = menu.getBoundingClientRect();
    const vh = window.innerHeight;
    const vw = window.innerWidth;
    let finalX = clickX;
    let finalY = clickY;
    if (finalY + menuRect.height > vh) {
        finalY = clickY - menuRect.height;
    }
    if (finalX + menuRect.width > vw) {
        finalX = clickX - menuRect.width;
    }
    finalY = Math.max(5, Math.min(vh - menuRect.height - 5, finalY));
    finalX = Math.max(5, Math.min(vw - menuRect.width - 5, finalX));
    menu.style.left = `${finalX}px`;
    menu.style.top = `${finalY}px`;
}







function isOfficeFile(path) {
    const ext = path.split('.').pop().toLowerCase();
    const officeExts = ['doc', 'docx', 'odt', 'rtf', 'xls', 'xlsx', 'ods', 'csv', 'ppt', 'pptx', 'odp'];
    return officeExts.includes(ext);
}
function openOnlyOfficeEditor(filePath) {
    const existingViewTab = tabsState.tabs.find(tab => tab.path === filePath && !tab.onlyOfficeEditor);
    const inheritedRoot = existingViewTab ? existingViewTab.root : null;
    if (existingViewTab) {
        closeTab(existingViewTab.id);
    }
    const existingEditorTab = tabsState.tabs.find(tab => tab.path === filePath && tab.onlyOfficeEditor);
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
        onlyOfficeEditor: true,
        root: inheritedRoot || getCurrentCloudRoot(),
        title: filePath.split('/').pop(),
        modified: false,
        content: null
    };
    if (tabsState.activeTabId) {
        const prevTab = tabsState.tabs.find((t) => t.id === tabsState.activeTabId);
        if (prevTab && typeof stashTabView === 'function') stashTabView(prevTab);
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
    if (window.cloudGroups?.logGroupFileOpen) window.cloudGroups.logGroupFileOpen(filePath);
    loadOnlyOfficeEditorToTab(newTab);
    saveTabsState();
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
function loadOnlyOfficeEditorToTab(tab) {
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
    const supportedOfficeFormats = ['doc', 'docx', 'odt', 'rtf', 'txt', 'xls', 'xlsx', 'ods', 'csv', 'ppt', 'pptx', 'odp'];
    if (!supportedOfficeFormats.includes(fileExt)) {
        flashStatus(t(i18n, 'unsupportedOfficeFormat'));
        return;
    }
    document.getElementById('file-view').classList.remove('hidden');
    fileName.textContent = tab.title;
    viewer.dataset.path = tab.path;
    viewer.dataset.editMode = 'true';
    viewer.dataset.onlyOfficeEditor = 'true';
    updateToolbarForOnlyOfficeEditor();
    let iframe = viewer.querySelector('iframe[data-tab-id="' + tab.id + '"]');
    if (!iframe) {
        iframe = document.createElement('iframe');
        iframe.setAttribute('data-tab-id', tab.id);
        iframe.setAttribute('tabindex', '0');
        const urlParams = new URLSearchParams(window.location.search);
        const lang = urlParams.get('lang') || 'en';
        const theme = urlParams.get('theme') || 'dark';
        const color = urlParams.get('color') || '#85b503';
        
        let currentUser = currentUserState.user || 'default';
        currentUser = currentUser.replace(/\s+/g, '_');
        const root = getResolvedTabRoot(tab);
        const onlyOfficeEditorUrl = buildOnlyOfficeEditorUrl(tab, currentUser, lang, theme, color, root);
        iframe.src = onlyOfficeEditorUrl;
        applyFullScreenStyles(iframe);
        viewer.appendChild(iframe);
    } else {
        iframe.setAttribute('tabindex', '0');
        applyFullScreenStyles(iframe);
        iframe.style.display = 'block';
    }
    requestAnimationFrame(() => {
        try { iframe.focus({ preventScroll: true }); }
        catch (_) { try { iframe.focus(); } catch (__) {} }
        try { iframe.contentWindow?.focus(); } catch (_) {}
    });
}
function updateToolbarForOnlyOfficeEditor() {
    const saveBtn = document.getElementById('btn-save');
    const codeActionsBtn = document.getElementById('btn-code-actions');
    saveBtn.classList.add('hidden');
    codeActionsBtn.classList.add('hidden');
}
function showMoveDialog(sourcePath, sourceType) {
    showTransferDialog(sourcePath, sourceType, 'move');
}
function showCopyDialog(sourcePath, sourceType) {
    showTransferDialog(sourcePath, sourceType, 'copy');
}
function showTransferDialog(sourcePath, sourceType, mode = 'move') {
    const isCopy = mode === 'copy';
    const modal = document.createElement('div');
    modal.className = 'modal';
    modal.style.display = 'flex';
    window.transferDialogMode = mode;
    window.transferDialogSourceType = sourceType;
    window.moveDialogSourceRoot = getCurrentCloudRoot();
    window.selectedMoveFolder = '';
    window.selectedMoveRoot = 'main';
    modal.innerHTML = `
        <div class="modal-content" style="max-width: 500px;">
            <div class="modal-header">
                <h3>${sourceType === 'dir' ? t(i18n, isCopy ? 'copyFolder' : 'moveFolder') : t(i18n, isCopy ? 'copyFileItem' : 'moveFile')}</h3>
                <button class="icon-btn close-btn">&times;</button>
            </div>
            <div class="modal-body">
                <p>${t(i18n, isCopy ? 'copying' : 'moving')} <strong>${sourcePath}</strong></p>
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
                        ${buildMoveRootToolbarHtml('main')}
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
                <button id="btn-confirm-move" class="btn primary">${t(i18n, isCopy ? 'copy' : 'move')}</button>
            </div>
        </div>
    `;
    document.body.appendChild(modal);
    loadFolderTreeForMove();
    initMoveRootToolbar();
    document.getElementById('btn-cancel-move').addEventListener('click', () => modal.remove());
    document.getElementById('btn-confirm-move').addEventListener('click', () => confirmMove(sourcePath, modal));
    modal.querySelector('.close-btn').addEventListener('click', () => modal.remove());
    modal.addEventListener('click', (e) => {
        if (e.target === modal) {
            modal.remove();
        }
    });
}
function showDestinationFolderDialog(options = {}) {
    return new Promise((resolve) => {
        const i18nRef = window.i18n || {};
        const title = options.title || t(i18nRef, 'selectTargetFolder');
        const confirmLabel = options.confirmLabel || t(i18nRef, 'select') || 'Select';
        const initialRoot = options.initialRoot || 'main';
        const initialPath = options.initialPath || '';
        const excludeGroups = !!options.excludeGroups;
        const modal = document.createElement('div');
        modal.className = 'modal';
        modal.style.display = 'flex';
        window.selectedMoveFolder = initialPath;
        window.selectedMoveRoot = initialRoot;
        modal.innerHTML = `
        <div class="modal-content" style="max-width: 500px;">
            <div class="modal-header">
                <h3>${title}</h3>
                <button class="icon-btn close-btn">&times;</button>
            </div>
            <div class="modal-body">
                <div class="folder-selector">
                    <label>${t(i18nRef, 'selectTargetFolder')}</label>
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
                        ${typeof buildMoveRootToolbarHtml === 'function' ? buildMoveRootToolbarHtml(initialRoot, { excludeGroups }) : ''}
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
                    ${t(i18nRef, 'selectedFolder')} <span id="current-folder-path">/</span>
                </div>
            </div>
            <div class="modal-footer">
                <button id="btn-cancel-move" class="btn secondary">${t(i18nRef, 'cancel')}</button>
                <button id="btn-confirm-move" class="btn primary">${confirmLabel}</button>
            </div>
        </div>
    `;
        const finish = (result) => {
            modal.remove();
            resolve(result);
        };
        document.body.appendChild(modal);
        loadFolderTreeForMove(initialRoot);
        initMoveRootToolbar();
        const pathDisplay = document.getElementById('current-folder-path');
        if (pathDisplay) {
            const rootPrefix = typeof getRootFolderPrefix === 'function' ? getRootFolderPrefix(initialRoot) : '';
            const rootLabel = typeof getCloudRootDisplayName === 'function' ? getCloudRootDisplayName(initialRoot) : initialRoot;
            const folder = initialPath ? (initialPath + '/') : '';
            pathDisplay.textContent = folder || (typeof isCloudGroupRoot === 'function' && isCloudGroupRoot(initialRoot) ? rootLabel + '/' : (rootPrefix || '/'));
        }
        document.getElementById('btn-cancel-move')?.addEventListener('click', () => finish({ confirmed: false }));
        modal.querySelector('.close-btn')?.addEventListener('click', () => finish({ confirmed: false }));
        modal.addEventListener('click', (e) => {
            if (e.target === modal) finish({ confirmed: false });
        });
        document.getElementById('btn-confirm-move')?.addEventListener('click', async () => {
            const targetDir = window.selectedMoveFolder !== undefined ? window.selectedMoveFolder : '';
            if (targetDir === '' && options.confirmRoot !== false) {
                const confirmModal = await showConfirmModal(
                    t(i18nRef, 'moveToRootConfirmTitle'),
                    t(i18nRef, 'moveToRootConfirm')
                );
                if (!confirmModal.confirmed) return;
            }
            finish({
                confirmed: true,
                root: window.selectedMoveRoot || 'main',
                path: targetDir
            });
        });
    });
}
window.showDestinationFolderDialog = showDestinationFolderDialog;

// Initialize root folder toolbar in move modal
function initMoveRootToolbar() {
    const buttons = document.querySelectorAll('.move-root-btn');
    buttons.forEach(btn => {
        btn.addEventListener('click', async () => {
            // Update active state
            buttons.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');

            // Load folder tree for selected root
            const rootKey = btn.dataset.root;
            const rootPrefix = getRootFolderPrefix(rootKey);
            window.selectedMoveRoot = rootKey;
            window.selectedMoveFolder = '';

            const pathDisplay = document.getElementById('current-folder-path');
            if (pathDisplay) {
                pathDisplay.textContent = isCloudGroupRoot(rootKey) ? (getCloudRootDisplayName(rootKey) + '/') : (rootPrefix || '/');
            }

            // Reload tree with new root - update content without recreating modal
            await loadFolderTreeForMove(rootKey);
        });
    });
}

async function loadFolderTreeForMove(rootKey = 'main') {
    try {
        const treeContainer = document.getElementById('move-folder-tree');
        if (!treeContainer) return;

        // Show loading state with fade transition
        treeContainer.style.opacity = '0.5';
        treeContainer.style.pointerEvents = 'none';

        // Get folder list with correct root parameter
        const rootItems = await api('list', { path: '', root: rootKey }, 'GET');
        const folders = rootItems.items.filter(item => item.type === 'dir');

        // Clear and rebuild tree
        treeContainer.innerHTML = '';

        // Add root folder option at the top
        const rootPrefix = getRootFolderPrefix(rootKey);
        const selectedFolder = window.selectedMoveFolder !== undefined ? window.selectedMoveFolder : (rootPrefix || '');
        const rootLabelText = isCloudGroupRoot(rootKey) ? getCloudRootDisplayName(rootKey) : t(i18n, 'rootFolder');
        const rootNode = document.createElement('div');
        rootNode.className = 'move-tree-node';
        rootNode.dataset.path = '';
        rootNode.dataset.type = 'dir';
        const rootLabel = document.createElement('span');
        rootLabel.className = 'move-tree-label';
        rootLabel.innerHTML = `<span style="margin-right: 6px;">${isCloudGroupRoot(rootKey) ? '👥' : '🏠'}</span><span>${rootLabelText}</span>`;
        rootLabel.style.cursor = 'pointer';
        rootLabel.style.padding = '4px 8px';
        rootLabel.style.borderRadius = '3px';
        rootLabel.style.display = 'inline-block';
        rootLabel.style.width = '100%';
        // Highlight if this root is selected
        if (selectedFolder === rootPrefix) {
            rootLabel.style.background = 'var(--accent)';
            rootLabel.style.color = 'white';
        }
        rootLabel.addEventListener('click', (e) => {
            e.stopPropagation();
            selectFolderForMove('');
        });
        rootNode.appendChild(rootLabel);
        treeContainer.appendChild(rootNode);

        if (folders.length === 0) {
            treeContainer.innerHTML += `<div style="padding: 10px; text-align: center; color: var(--muted);">${t(i18n, 'noSubfolders')}</div>`;
        } else {
            const container = renderMoveFolderNodes(folders, '', 0, rootKey);
            treeContainer.appendChild(container);
        }

        // Restore interactivity
        treeContainer.style.opacity = '1';
        treeContainer.style.pointerEvents = 'auto';
        
        // Update the selection highlight after tree is loaded
        selectFolderForMove(window.selectedMoveFolder);
    } catch (err) {
        console.error('Failed to load folder tree:', err);
        const treeContainer = document.getElementById('move-folder-tree');
        if (treeContainer) {
            treeContainer.style.opacity = '1';
            treeContainer.style.pointerEvents = 'auto';
            treeContainer.innerHTML = `<div style="padding: 10px; text-align: center; color: #e74c3c;">${t(i18n, 'loadError', { message: err.message })}</div>`;
        }
    }
}
function renderMoveFolderNodes(items, basePath = '', depth = 0, rootKey = 'main') {
    const container = document.createElement('div');
    container.className = 'move-tree-container';
    items.forEach(item => {
        const node = document.createElement('div');
        node.className = 'move-tree-node';
        node.style.paddingLeft = `${depth * 20}px`;
        node.dataset.path = item.path;
        node.dataset.type = item.type;
        const toggle = document.createElement('span');
        toggle.className = 'move-tree-toggle';
        toggle.innerHTML = '<svg width="12" height="12" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>';
        toggle.style.cursor = 'pointer';
        toggle.style.marginRight = '5px';
        toggle.style.display = 'inline-block';
        toggle.style.verticalAlign = 'middle';
        const label = document.createElement('span');
        label.className = 'move-tree-label';
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
        children.className = 'move-tree-children';
        children.style.display = 'none';
        children.dataset.loaded = 'false';
        label.addEventListener('click', (e) => {
            e.stopPropagation();
            selectFolderForMove(item.path);
        });
        toggle.addEventListener('click', async (e) => {
            e.stopPropagation();
            const isOpen = children.style.display !== 'none';
            toggle.style.transform = isOpen ? 'rotate(0deg)' : 'rotate(90deg)';
            toggle.style.transition = 'transform 0.2s ease';
            children.style.display = isOpen ? 'none' : 'block';
            if (!isOpen && children.dataset.loaded === 'false') {
                try {
                    const res = await api('list', { path: item.path, root: rootKey }, 'GET');
                    const childFolders = res.items.filter(child => child.type === 'dir');
                    if (childFolders.length > 0) {
                        const childNodes = renderMoveFolderNodes(childFolders, item.path, depth + 1, rootKey);
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
        nodeContent.addEventListener('dblclick', (e) => {
            e.stopPropagation();
            selectFolderForMove(item.path);
            if (children.style.display === 'none') {
                toggle.click();
            }
        });
        container.appendChild(node);
        container.appendChild(children);
    });
    return container;
}
function selectFolderForMove(folderPath) {
    document.querySelectorAll('.move-tree-label').forEach(label => {
        label.style.background = 'transparent';
        label.style.color = 'var(--fg)';
    });

    // Find the selected node - handle empty string for root
    let selectedLabel;
    let selectedNode;
    if (folderPath === '' || folderPath === null) {
        // Select root node (data-path="")
        selectedNode = document.querySelector(`.move-tree-node[data-path=""]`);
        selectedLabel = selectedNode ? selectedNode.querySelector('.move-tree-label') : null;
    } else {
        // First try to find by exact path
        selectedNode = document.querySelector(`.move-tree-node[data-path="${folderPath}"]`);
        selectedLabel = selectedNode ? selectedNode.querySelector('.move-tree-label') : null;

        // If not found and folderPath looks like a root prefix (e.g., .archive), select root node
        if (!selectedLabel) {
            const rootPrefixes = ['.archive', '.temp_files', '.documents', '.photos', '.pdf'];
            if (rootPrefixes.includes(folderPath)) {
                selectedNode = document.querySelector(`.move-tree-node[data-path=""]`);
                selectedLabel = selectedNode ? selectedNode.querySelector('.move-tree-label') : null;
            }
        }
    }

    if (selectedLabel) {
        selectedLabel.style.background = 'var(--accent)';
        selectedLabel.style.color = 'white';

        // Expand the tree node to show contents if it has children
        if (selectedNode) {
            const nodeContent = selectedNode.querySelector(':scope > div');
            if (nodeContent) {
                const toggle = nodeContent.querySelector('.move-tree-toggle');
                const children = selectedNode.querySelector(':scope > .move-tree-children');
                if (toggle && children && children.dataset.loaded === 'false') {
                    // Trigger the toggle click to load children
                    toggle.click();
                } else if (children) {
                    // Already loaded, just make sure it's visible
                    children.style.display = 'block';
                    if (toggle) {
                        toggle.style.transform = 'rotate(90deg)';
                        toggle.style.transition = 'transform 0.2s ease';
                    }
                }
            }
        }
    }
    const pathDisplay = document.getElementById('current-folder-path');
    if (pathDisplay) {
        const rootKey = window.selectedMoveRoot || 'main';
        pathDisplay.textContent = folderPath || (isCloudGroupRoot(rootKey) ? (getCloudRootDisplayName(rootKey) + '/') : '/');
    }
    window.selectedMoveFolder = folderPath;
}
async function loadFoldersForMove() {
    try {
        const allFolders = await getAllFoldersRecursive('');
        const select = document.getElementById('target-folder-select');
        while (select.children.length > 1) {
            select.removeChild(select.lastChild);
        }
        allFolders.forEach(folder => {
            const option = document.createElement('option');
            option.value = folder.path;
            option.textContent = folder.path || '/';
            select.appendChild(option);
        });
    } catch (err) {
        console.error('Failed to load folders:', err);
        flashStatus(t(i18n, 'folderListError'));
    }
}
let expandedFolders = new Set();
let treeBrowsePath = '';
const treeBrowsePathsByRoot = {};
function countDirectTreeChildren(items, parentPath = '') {
    const normalizedParent = String(parentPath || '').replace(/^\/+/, '').replace(/\/+$/, '');
    return (Array.isArray(items) ? items : []).filter((item) => {
        const itemPath = String(item?.path || '').replace(/^\/+/, '');
        if (!itemPath) return false;
        if (!normalizedParent) return !itemPath.includes('/');
        const prefix = `${normalizedParent}/`;
        if (!itemPath.startsWith(prefix)) return false;
        return !itemPath.slice(prefix.length).includes('/');
    }).length;
}
window.countDirectTreeChildren = countDirectTreeChildren;
function getTreeBrowsePath() { return treeBrowsePath; }
function persistTreeBrowsePath(root) {
    const key = root || (typeof getCurrentCloudRoot === 'function' ? getCurrentCloudRoot() : 'main');
    if (treeBrowsePath) treeBrowsePathsByRoot[key] = treeBrowsePath;
    else delete treeBrowsePathsByRoot[key];
}
function loadTreeBrowsePath(root) {
    const key = root || (typeof getCurrentCloudRoot === 'function' ? getCurrentCloudRoot() : 'main');
    treeBrowsePath = treeBrowsePathsByRoot[key] || '';
    updateTreeBrowseHeader();
}
function getTreeBrowseParentPath(path = treeBrowsePath) {
    const normalized = String(path || '').replace(/^\/+/, '').replace(/\/+$/, '');
    if (!normalized) return '';
    const idx = normalized.lastIndexOf('/');
    return idx === -1 ? '' : normalized.slice(0, idx);
}
function getTreeBrowseFolderName(path = treeBrowsePath) {
    const normalized = String(path || '').replace(/^\/+/, '').replace(/\/+$/, '');
    if (!normalized) return '';
    const parts = normalized.split('/');
    return parts[parts.length - 1] || normalized;
}
function updateTreeBrowseHeader() {
    const bar = document.getElementById('tree-browse-bar');
    const label = document.getElementById('tree-browse-label');
    const sectionTitle = document.getElementById('section-title-label');
    const groupSwitcher = document.getElementById('group-section-switcher');
    if (!bar || !label) return;
    const active = !!treeBrowsePath;
    bar.classList.toggle('hidden', !active);
    if (sectionTitle) sectionTitle.classList.toggle('hidden', active);
    if (groupSwitcher && active) groupSwitcher.classList.add('hidden');
    if (!active && typeof window.cloudGroupsRefreshSection === 'function') window.cloudGroupsRefreshSection();
    if (!active) {
        label.textContent = '';
        return;
    }
    label.textContent = getTreeBrowseFolderName();
    label.title = treeBrowsePath;
}
async function navigateTreeTo(path) {
    if (!path || path === treeBrowsePath) return;
    treeBrowsePath = String(path).replace(/^\/+/, '').replace(/\/+$/, '');
    persistTreeBrowsePath();
    updateTreeBrowseHeader();
    if (typeof buildTree === 'function') await buildTree('');
}
async function navigateTreeBack() {
    if (!treeBrowsePath) return;
    treeBrowsePath = getTreeBrowseParentPath();
    persistTreeBrowsePath();
    updateTreeBrowseHeader();
    if (typeof buildTree === 'function') await buildTree('');
}
function resetTreeBrowsePath() {
    treeBrowsePath = '';
    persistTreeBrowsePath();
    updateTreeBrowseHeader();
}
window.getTreeBrowsePath = getTreeBrowsePath;
window.persistTreeBrowsePath = persistTreeBrowsePath;
window.loadTreeBrowsePath = loadTreeBrowsePath;
window.navigateTreeTo = navigateTreeTo;
window.navigateTreeBack = navigateTreeBack;
window.resetTreeBrowsePath = resetTreeBrowsePath;
window.updateTreeBrowseHeader = updateTreeBrowseHeader;

// ─── Full file-tree cache helpers ─────────────────────────────────────────────
// cachedFileTree: flat array from api('get_file_tree'), null = not loaded yet.
let cachedFileTree = null;

/** Mark server-side tree cache invalid (call after every write op). */
async function invalidateTreeCache() {
    cachedFileTree = null;
    try {
        await api('invalidate_file_tree', {}, 'POST');
    } catch (err) {
        console.warn('invalidateTreeCache: server call failed', err);
    }
}

/** Fetch full flat tree from DB (rescans disk when cache is cold). */
async function fetchFileTree() {
    try {
        const data = await api('get_file_tree', {}, 'GET');
        if (data && data.ok && Array.isArray(data.items)) {
            cachedFileTree = data.items;
            return data.items;
        }
    } catch (err) {
        console.warn('fetchFileTree: failed', err);
    }
    cachedFileTree = [];
    return [];
}

// ------------------------------------------------------------------
//  Incremental tree helpers
//  patchTreeCache   — update in-memory cache + server DB JSON
//  treeNodeAdd      — insert one DOM node without full rebuild
//  treeNodeRemove   — remove one DOM node
//  treeNodeRename   — rename/move a DOM node in place
// ------------------------------------------------------------------

async function patchTreeCache(ops) {
    if (!ops || ops.length === 0) return;
    if (cachedFileTree !== null) {
        for (const op of ops) {
            if (op.op === 'add' && op.item) {
                const p = String(op.item.path || '');
                if (!cachedFileTree.some(i => i.path === p)) {
                    cachedFileTree.push({ ...op.item, path: p });
                }
            } else if (op.op === 'remove' && op.path) {
                const p = String(op.path);
                cachedFileTree = cachedFileTree.filter(i =>
                    i.path !== p && !i.path.startsWith(p + '/'));
            } else if ((op.op === 'rename' || op.op === 'move') && op.old_path && op.new_path) {
                const oldP = String(op.old_path);
                const newP = String(op.new_path);
                const newN = op.new_name || newP.split('/').pop();
                cachedFileTree = cachedFileTree.map(i => {
                    if (i.path === oldP) return { ...i, path: newP, name: newN };
                    if (i.path.startsWith(oldP + '/')) return { ...i, path: newP + '/' + i.path.slice(oldP.length + 1) };
                    return i;
                });
            }
        }
    }
    try {
        await api('patch_file_tree', { ops: JSON.stringify(ops) }, 'POST');
    } catch (err) {
        console.warn('patchTreeCache: server patch failed, invalidating', err);
        cachedFileTree = null;
        try { await api('invalidate_file_tree', {}, 'POST'); } catch (_) {}
    }
}

// Explicitly expose incremental tree helpers for other scripts (e.g. upload.js).
window.patchTreeCache = patchTreeCache;
function normalizeTreePathForDom(path) {
    return String(path || '').replace(/\\/g, '/').replace(/^\/+/, '').replace(/\/+/g, '/').trim();
}
function stripStoragePrefixFromTreePath(path) {
    const normalized = normalizeTreePathForDom(path);
    const match = normalized.match(/^storage\/[^/]+\/(.+)$/);
    return match ? normalizeTreePathForDom(match[1]) : normalized;
}
function toMainTreePath(path, root = 'main') {
    const normalized = stripStoragePrefixFromTreePath(path);
    if (!normalized) return '';
    if (!root || root === 'main') return normalized;
    const prefix = typeof getRootFolderPrefix === 'function' ? getRootFolderPrefix(root) : '';
    if (!prefix) return normalized;
    return (typeof pathStartsWithRootPrefix === 'function' && pathStartsWithRootPrefix(normalized, root))
        ? normalized
        : `${prefix}/${normalized}`;
}
function fromMainTreePath(path, root = 'main') {
    const normalized = stripStoragePrefixFromTreePath(path);
    if (!normalized || !root || root === 'main') return normalized;
    const prefix = typeof getRootFolderPrefix === 'function' ? getRootFolderPrefix(root) : '';
    if (!prefix) return normalized;
    if (normalized === prefix) return '';
    return normalized.startsWith(prefix + '/') ? normalized.slice(prefix.length + 1) : normalized;
}
function normalizeItemForRoot(item, root = 'main') {
    if (!item) return null;
    const normalizedPath = fromMainTreePath(item.path || '', root);
    return {
        ...item,
        path: normalizedPath,
        name: item.name || (normalizedPath ? normalizedPath.split('/').pop() : ''),
        type: item.type === 'dir' ? 'dir' : 'file'
    };
}
function mapTreeOpsToMainRoot(ops, root = 'main') {
    return (Array.isArray(ops) ? ops : []).map((op) => {
        if (!op || !op.op) return null;
        if (op.op === 'add' && op.item) {
            const item = normalizeItemForRoot(op.item, root);
            if (!item || !item.path) return null;
            return { ...op, item: { ...item, path: toMainTreePath(item.path, root) } };
        }
        if (op.op === 'remove' && op.path) {
            return { ...op, path: toMainTreePath(op.path, root) };
        }
        if ((op.op === 'rename' || op.op === 'move') && op.old_path && op.new_path) {
            return {
                ...op,
                old_path: toMainTreePath(op.old_path, root),
                new_path: toMainTreePath(op.new_path, root)
            };
        }
        return op;
    }).filter(Boolean);
}
async function patchTreeCacheForRoot(ops, root = 'main') {
    if (typeof isCloudGroupRoot === 'function' && isCloudGroupRoot(root)) return;
    const mappedOps = mapTreeOpsToMainRoot(ops, root);
    if (!mappedOps.length) return;
    await patchTreeCache(mappedOps);
}
window.patchTreeCacheForRoot = patchTreeCacheForRoot;
function getTreeInsertContainer(parentPath, options = {}) {
    const tree = document.getElementById('tree');
    if (!tree) return null;
    if (treeBrowsePath && parentPath === treeBrowsePath) {
        let root = tree.querySelector(':scope > .tree-container');
        if (!root) {
            root = document.createElement('div');
            root.className = 'tree-container';
            tree.appendChild(root);
            tree.classList.remove('is-empty');
        }
        return root;
    }
    if (!parentPath) {
        let root = tree.querySelector(':scope > .tree-container');
        if (!root) {
            root = document.createElement('div');
            root.className = 'tree-container';
            tree.appendChild(root);
            tree.classList.remove('is-empty');
        }
        return root;
    }
    const parentNode = tree.querySelector(`.node[data-path="${CSS.escape(String(parentPath))}"]`);
    if (!parentNode) return null;
    const childrenWrap = parentNode.nextElementSibling;
    if (!childrenWrap || !childrenWrap.classList.contains('children')) return null;
    if (childrenWrap.style.display === 'none') {
        if (options.allowHiddenParent) {
            // Keep subtree collapsed, but allow hidden DOM updates so new items
            // appear immediately when the user expands the folder later.
        } else {
            if (options.openParents === false) return null;
            childrenWrap.style.display = 'block';
            const toggle = parentNode.querySelector('.toggle');
            if (typeof setTreeDirToggleOpen === 'function') setTreeDirToggleOpen(toggle, true);
            if (typeof syncTreeFolderEndLine === 'function') syncTreeFolderEndLine(childrenWrap, true);
            expandedFolders.add(String(parentPath));
            saveFolderState();
        }
    }
    let container = childrenWrap.querySelector(':scope > .tree-container');
    if (!container) {
        container = document.createElement('div');
        container.className = 'tree-container';
        childrenWrap.appendChild(container);
    }
    return container;
}
function highlightNewTreeNode(path) {
    const apply = () => {
        const node = getTreeNodeByPath(path);
        if (!node) return;
        node.classList.remove('tree-new-file');
        void node.offsetWidth;
        node.classList.add('tree-new-file');
        if (node._newFileHighlightTimer) clearTimeout(node._newFileHighlightTimer);
        node._newFileHighlightTimer = setTimeout(() => {
            node.classList.remove('tree-new-file');
            node._newFileHighlightTimer = null;
        }, 2800);
        node.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
    };
    requestAnimationFrame(() => requestAnimationFrame(apply));
}
window.highlightNewTreeNode = highlightNewTreeNode;
function treeNodeAdd(item, options = {}) {
  const tree = document.getElementById('tree');
  if (!tree) return;

  const normalizedItem = {
    ...item,
    path: normalizeTreePathForDom(item.path || ''),
    name: item.name || String(item.path || '').split('/').pop()
  };
  const itemPath = normalizedItem.path;
  if (!itemPath) return false;
  const parentPath = itemPath.includes('/')
    ? itemPath.substring(0, itemPath.lastIndexOf('/'))
    : '';

  const parentChildren = getTreeInsertContainer(parentPath, options);
  if (!parentChildren) return false;

  const esc = CSS.escape(itemPath);
  if (tree.querySelector(`.node[data-path="${esc}"]`)) return false;

  const sibling = parentChildren.querySelector('.node');
  const depth = sibling
    ? Math.round(parseInt(sibling.style.paddingLeft || '0') / 16)
    : (parentPath === '' ? 0 : parentPath.split('/').length);

  const fragment = renderNodes([normalizedItem], parentPath, depth);
  const newNode = fragment.querySelector('.node');
  if (!newNode) return false;

  const existingNodes = Array.from(parentChildren.querySelectorAll(':scope > .node'));
  let inserted = false;
  for (const en of existingNodes) {
    const eType = en.dataset.type || 'file';
    const eName = (en.querySelector('.label') || {}).textContent || '';
    const before = (item.type === 'dir' && eType !== 'dir')
      || (item.type === eType && item.name.localeCompare(eName, undefined, { sensitivity: 'base', numeric: true }) < 0);
    if (before) {
      parentChildren.insertBefore(newNode, en);
      const childrenBlock = fragment.querySelector('.children');
      if (childrenBlock) parentChildren.insertBefore(childrenBlock, en);
      inserted = true;
      break;
    }
  }
  if (!inserted) {
    parentChildren.appendChild(newNode);
    const childrenBlock = fragment.querySelector('.children');
    if (childrenBlock) parentChildren.appendChild(childrenBlock);
  }

  if (options.highlight !== false) highlightNewTreeNode(itemPath);

  // Если в режиме выделения - добавляем чекбокс
  if (selectionState.active) {
    const node = tree.querySelector(`.node[data-path="${esc}"]`);
    if (node && !node.querySelector('.selection-checkbox')) {
      addCheckboxToNode(node);
    }
  }
  tree.classList.remove('is-empty');
  const guidesParent = parentChildren.closest('.children');
  if (guidesParent) {
    const siblingContainer = guidesParent.querySelector(':scope > .tree-container');
    if (typeof syncTreeGuideLastMarkers === 'function') syncTreeGuideLastMarkers(siblingContainer);
    if (typeof syncTreeChildrenGuide === 'function') syncTreeChildrenGuide(guidesParent);
  }
  return true;
}

window.treeNodeAdd = treeNodeAdd;

async function insertTreeItem(item, root) {
    const r = root ?? getCurrentCloudRoot();
    const normalizedItem = normalizeItemForRoot(item, r);
    if (!normalizedItem?.path) return;
    await patchTreeCacheForRoot([{ op: 'add', item: normalizedItem }], r);
    if (getCurrentCloudRoot() === r) {
        treeNodeAdd(normalizedItem);
        highlightNewTreeNode(normalizedItem.path);
    }
    if (getCurrentCloudRoot() === r) {
        pinTreePaths([normalizedItem.path], { scrollToLast: true });
    }
}
async function insertTreeItems(items, root) {
    const r = root ?? getCurrentCloudRoot();
    const list = (Array.isArray(items) ? items : [])
        .map((item) => normalizeItemForRoot(item, r))
        .filter((i) => i?.path);
    if (!list.length) return;
    await patchTreeCacheForRoot(list.map((item) => ({ op: 'add', item })), r);
    if (getCurrentCloudRoot() === r) {
        for (const item of list) {
            treeNodeAdd(item);
            highlightNewTreeNode(item.path);
        }
        pinTreePaths(list.map((i) => i.path), { scrollToLast: true });
    }
}
window.insertTreeItem = insertTreeItem;
window.insertTreeItems = insertTreeItems;

function treeNodeRemove(path, options = {}) {
    const tree = document.getElementById('tree');
    if (!tree || !path) return;
    const p = String(path);
    const esc = CSS.escape(p);
    const node = tree.querySelector(`.node[data-path="${esc}"]`);
    if (!node) return;
    const animate = options.animate !== false;
    const next = node.nextElementSibling;
    if (!animate) {
        node.remove();
        if (next && next.classList.contains('children')) next.remove();
        return;
    }
    if (node.dataset.removing === '1') return;

    const parent = node.parentElement;
    const movingSiblings = [];
    if (parent) {
        let movingCursor = next && next.classList.contains('children') ? next.nextElementSibling : node.nextElementSibling;
        while (movingCursor) {
            if (movingCursor.nodeType === Node.ELEMENT_NODE) movingSiblings.push(movingCursor);
            movingCursor = movingCursor.nextElementSibling;
        }
    }
    const firstRects = new Map(movingSiblings.map((el) => [el, el.getBoundingClientRect()]));

    node.dataset.removing = '1';
    node.style.maxHeight = `${node.offsetHeight}px`;
    if (next && next.classList.contains('children')) {
        next.style.maxHeight = `${next.scrollHeight}px`;
        next.classList.add('tree-removing-children');
    }
    void node.offsetHeight;
    node.classList.add('tree-removing');
    if (next && next.classList.contains('children')) {
        next.style.maxHeight = '0px';
    }
    if (movingSiblings.length) {
        requestAnimationFrame(() => {
            movingSiblings.forEach((el) => {
                if (!el.isConnected) return;
                const first = firstRects.get(el);
                if (!first) return;
                const last = el.getBoundingClientRect();
                const deltaY = first.top - last.top;
                if (Math.abs(deltaY) < 0.5) return;
                el.style.transition = 'none';
                el.style.transform = `translateY(${deltaY}px)`;
                void el.offsetHeight;
                el.style.transition = 'transform .32s cubic-bezier(0.22, 1, 0.36, 1)';
                el.style.transform = 'translateY(0)';
                const clearFlip = () => {
                    el.style.transition = '';
                    el.style.transform = '';
                    el.removeEventListener('transitionend', clearFlip);
                };
                el.addEventListener('transitionend', clearFlip);
                window.setTimeout(clearFlip, 360);
            });
        });
    }

    const cleanup = () => {
        node.remove();
        if (next && next.classList.contains('children')) next.remove();
        scheduleTreeAutoShift();
    };
    window.setTimeout(cleanup, 320);
}

function removeAdvancedSearchResult(path) {
    if (!path) return;
    const p = String(path);
    const esc = CSS.escape(p);
    const rows = document.querySelectorAll(`#tree .advanced-search-result[data-path="${esc}"]`);
    if (!rows.length) return;

    rows.forEach((row) => row.remove());
    selectionState.selectedItems.delete(p);

    const headerTitle = document.querySelector('#tree .search-results-header h3');
    if (!headerTitle) return;
    const remaining = document.querySelectorAll('#tree .advanced-search-result').length;
    headerTitle.textContent = `${t(i18n, 'searchResults') || 'Search Results'} (${remaining})`;
    if (remaining === 0) {
        const tree = document.getElementById('tree');
        if (tree) {
            tree.innerHTML = `<div class="no-results">${t(i18n, 'noResultsFound') || 'No results found'}</div>`;
        }
    }
}

function treeNodeRename(oldPath, newPath, newName) {
    const tree = document.getElementById('tree');
    if (!tree) return;
    const oldP = String(oldPath);
    const newP = String(newPath);
    // Update all nodes whose path starts with oldP
    tree.querySelectorAll('.node').forEach(n => {
        const np = n.dataset.path || '';
        if (np === oldP) {
            n.dataset.path = newP;
            const lbl = n.querySelector('.label');
            if (lbl) lbl.textContent = newName;
        } else if (np.startsWith(oldP + '/')) {
            n.dataset.path = newP + '/' + np.slice(oldP.length + 1);
        }
    });
    // If parent changed (move), relocate the node
    const oldParent = oldP.includes('/') ? oldP.substring(0, oldP.lastIndexOf('/')) : '';
    const newParent = newP.includes('/') ? newP.substring(0, newP.lastIndexOf('/')) : '';
    if (oldParent !== newParent) {
        treeNodeRemove(newP, { animate: false });
        const item = cachedFileTree ? cachedFileTree.find(i => i.path === newP) : null;
        if (item) treeNodeAdd(item);
    }
}

/**
 * Build full nested DOM from a flat items array (all files & folders).
 * Folders in expandedFolders are rendered open immediately — no extra API calls.
 */
function buildTreeFromFlatItems(flatItems) {
    const savedSort = localStorage.getItem('treeSortType');
    if (savedSort) sortState.currentSort = savedSort;

    // Group by parent path
    const childrenOf = {};
    for (const item of flatItems) {
        const parts = item.path.split('/');
        const parentPath = parts.length > 1 ? parts.slice(0, -1).join('/') : '';
        if (!childrenOf[parentPath]) childrenOf[parentPath] = [];
        childrenOf[parentPath].push(item);
    }
    for (const key of Object.keys(childrenOf)) {
        childrenOf[key] = sortItems(childrenOf[key], sortState.currentSort);
    }

    function renderLevel(parentPath, depth) {
        const container = document.createElement('div');
        container.className = 'tree-container';
        const siblings = childrenOf[parentPath] || [];
        siblings.forEach((item, siblingIndex) => {
            const node = document.createElement('div');
            node.className = 'node';
            if (selectionState.selectedItems.has(item.path)) node.classList.add('selected');
            node.style.paddingLeft = `${depth * 16}px`;
            if (depth > 0) node.style.setProperty('--tree-branch-width', `${depth * 16}px`);
            node.dataset.path = item.path;
            node.dataset.type = item.type;
            node.addEventListener('dragstart', (e) => {
                if (getNodeType() !== 'file' && !(selectionState.active && selectionState.selectedItems.has(getNodePath()))) {
                    e.preventDefault();
                    return;
                }
                const path = getNodePath();
                if (typeof setTreeDragPayload === 'function') {
                    setTreeDragPayload(e, path, getNodeType());
                } else {
                    e.dataTransfer.effectAllowed = 'move';
                    e.dataTransfer.setData('application/x-cloud-file-path', path);
                    e.dataTransfer.setData('application/x-cloud-file-type', getNodeType());
                    e.dataTransfer.setData('text/plain', path);
                }
            });
            const getNodePath = () => node.dataset.path || item.path;
            const getNodeType = () => node.dataset.type || item.type;
            const getNodeName = () => {
                const lbl = node.querySelector('.label');
                return lbl ? (lbl.textContent || item.name) : item.name;
            };
            const toggle = document.createElement('span');
            toggle.className = 'toggle';
            const dirMarkup = typeof getTreeDirToggleMarkup === 'function' ? getTreeDirToggleMarkup : () => '';
            toggle.innerHTML = item.type === 'dir' ? dirMarkup(false) : getFileIcon(item.path || item.name);
            const label = document.createElement('span');
            label.className = 'label';
            label.textContent = item.name;
            label.title = item.name;
            node.append(toggle, label);
            if (selectionState.active) addCheckboxToNode(node);
            if (typeof syncNodeDragHandle === 'function') syncNodeDragHandle(node);
            node.setAttribute('tabindex', '0');

            const childContainer = document.createElement('div');
            childContainer.className = 'children';
            childContainer.dataset.loaded = 'true'; // built from cache, no lazy-load needed

            if (item.type === 'dir') {
                const isOpen = expandedFolders.has(item.path);
                childContainer.style.display = isOpen ? 'block' : 'none';
                if (typeof setTreeDirToggleOpen === 'function') setTreeDirToggleOpen(toggle, isOpen);

                const inner = renderLevel(item.path, depth + 1);
                childContainer.appendChild(inner);
                if (typeof syncTreeFolderEndLine === 'function') syncTreeFolderEndLine(childContainer, isOpen);

                let clickTimer = null;
                let isDoubleClick = false;

                node.addEventListener('click', async (e) => {
                    e.stopPropagation();
                    if (e.target.classList.contains('selection-checkbox')) return;

                    if (selectionState.active) {
                        const cb = node.querySelector('.selection-checkbox');
                        if (cb) {
                            cb.checked = !cb.checked;
                            handleItemSelection(item.path, item.type, cb.checked);
                            node.classList.toggle('selected', cb.checked);
                        }
                        return;
                    }

                    if (isDoubleClick) { isDoubleClick = false; return; }

                    const open = childContainer.style.display !== 'none';
                    if (open) {
                        if (typeof setTreeDirToggleOpen === 'function') setTreeDirToggleOpen(toggle, false);
                        childContainer.style.display = 'none';
                        if (typeof syncTreeFolderEndLine === 'function') syncTreeFolderEndLine(childContainer, false);
                        removeChildPathsFromState(getNodePath());
                        expandedFolders.delete(getNodePath());
                    } else {
                        if (typeof setTreeDirToggleOpen === 'function') setTreeDirToggleOpen(toggle, true);
                        childContainer.style.display = 'block';
                        if (typeof syncTreeFolderEndLine === 'function') syncTreeFolderEndLine(childContainer, true);
                        expandedFolders.add(getNodePath());
                    }
                    saveFolderState();
                });

                node.addEventListener('dblclick', (e) => {
                    e.stopPropagation();
                    if (selectionState.active) { e.preventDefault(); return; }
                    isDoubleClick = true;
                    if (clickTimer) { clearTimeout(clickTimer); clickTimer = null; }
                    navigateTreeTo(getNodePath());
                });

            } else {
                childContainer.style.display = 'none';
                let clickTimer = null;
                let isDoubleClick = false;

                node.addEventListener('click', (e) => {
                    e.stopPropagation();
                    if (e.target.classList.contains('selection-checkbox')) return;

                    if (selectionState.active) {
                        const cb = node.querySelector('.selection-checkbox');
                        if (cb) {
                            cb.checked = !cb.checked;
                            handleItemSelection(getNodePath(), getNodeType(), cb.checked);
                            node.classList.toggle('selected', cb.checked);
                        }
                        return;
                    }

                    if (isDoubleClick) { isDoubleClick = false; return; }
                    clickTimer = setTimeout(() => {
                        if (!isDoubleClick) {
                            if (typeof openFileFromTree === 'function') openFileFromTree(getNodePath());
                            else openFileInTab(getNodePath(), false);
                        }
                    }, 300);
                });

                node.addEventListener('dblclick', (e) => {
                    e.stopPropagation();
                    if (selectionState.active) { e.preventDefault(); return; }
                    isDoubleClick = true;
                    if (clickTimer) { clearTimeout(clickTimer); clickTimer = null; }
                    const currentPath = getNodePath();
                    if (typeof shouldRenameOnTreeDblClick === 'function' && shouldRenameOnTreeDblClick(currentPath)) {
                        if (typeof startInlineTreeRename === 'function') startInlineTreeRename(currentPath);
                        return;
                    }
                    if (typeof openFileFromTree === 'function') openFileFromTree(currentPath);
                    else openFileInTab(currentPath, false);
                });
            }

            node.addEventListener('contextmenu', (e) => {
                e.preventDefault();
                e.stopPropagation();
                showContextMenu(e, getNodePath(), getNodeType(), getNodeName());
            });

            node.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); node.click(); }
            });

            container.appendChild(node);
            container.appendChild(childContainer);
        });
        if (typeof syncTreeGuideLastMarkers === 'function') syncTreeGuideLastMarkers(container, siblings);
        return container;
    }

    return renderLevel(treeBrowsePath || '', 0);
}

// Persists expanded folder paths to the server DB for this user.
async function saveFolderState() {
    const paths = Array.from(expandedFolders);
    try {
        await api('set_folder_state', { paths: JSON.stringify(paths) }, 'POST');
    } catch (err) {
        console.warn('saveFolderState: could not persist to server', err);
    }
}

// Loads expanded folder paths from the server DB for this user.
async function loadFolderState() {
    try {
        const data = await api('get_folder_state', {}, 'GET');
        expandedFolders = new Set(Array.isArray(data.paths) ? data.paths : []);
    } catch (err) {
        console.warn('loadFolderState: could not load from server', err);
        expandedFolders = new Set();
    }
}

// Stub kept so legacy call-sites don't throw.
async function restoreFolderState() { /* handled inside buildTree */ }

function areAllParentsOpen(path) {
    if (!path) return true;
    const normalized = String(path).replace(/^\/+/, '');
    const browsePath = treeBrowsePath;
    if (browsePath) {
        if (normalized === browsePath) return true;
        if (!normalized.startsWith(browsePath + '/')) return false;
        const relative = normalized.slice(browsePath.length + 1);
        const parts = relative.split('/').filter(Boolean);
        if (parts.length <= 1) return true;
        let cur = browsePath;
        for (let i = 0; i < parts.length - 1; i++) {
            cur = `${cur}/${parts[i]}`;
            if (!expandedFolders.has(cur)) return false;
        }
        return true;
    }
    const parts = normalized.split('/').filter(p => p !== '');
    let cur = '';
    for (let i = 0; i < parts.length - 1; i++) {
        cur = cur ? `${cur}/${parts[i]}` : parts[i];
        if (!expandedFolders.has(cur)) return false;
    }
    return true;
}

async function expandFolderToPath(targetPath) {
    const parts = targetPath.split('/').filter(p => p !== '');
    let cur = '';
    for (let i = 0; i < parts.length; i++) {
        cur = cur ? `${cur}/${parts[i]}` : parts[i];
        const node = document.querySelector(`.node[data-path="${cur}"]`);
        if (node && node.dataset.type === 'dir') {
            const ch = node.nextElementSibling;
            if (ch && ch.classList.contains('children') && ch.style.display === 'none') {
                const tog = node.querySelector('.toggle');
                if (typeof setTreeDirToggleOpen === 'function') setTreeDirToggleOpen(tog, true);
                ch.style.display = 'block';
                if (ch.dataset.loaded === 'false') {
                    try {
                        const res = await api('list', { path: cur }, 'GET');
                        ch.innerHTML = '';
                        const depth = parseInt(node.style.paddingLeft || '0') / 16 + 1;
                        ch.appendChild(renderNodes(res.items, cur, depth));
                        ch.dataset.loaded = 'true';
                    } catch (err) {
                        console.error('expandFolderToPath: list failed', err);
                    }
                }
                if (typeof syncTreeFolderEndLine === 'function') syncTreeFolderEndLine(ch, true);
            }
        }
    }
}
window.expandFolderToPath = expandFolderToPath;

let pinnedTreePath = null;
let pinnedTreePaths = new Set();
let pinnedClearAbortController = null;
let treeAutoShiftInitialized = false;
let treeShiftMeasureRafId = null;
let treeShiftAnimRafId = null;
let treeShiftCurrent = 0;
let treeShiftTarget = 0;

function getTreeNodeByPath(path) {
    if (!path) return null;
    const nodes = document.querySelectorAll('#tree .node[data-path]');
    for (const node of nodes) {
        if (node.dataset.path === path) return node;
    }
    return null;
}

function clearPinnedTreeSelection() {
    document.querySelectorAll('#tree .node.pinned-focus').forEach((el) => el.classList.remove('pinned-focus'));
    pinnedTreePaths.clear();
    pinnedTreePath = null;
    if (pinnedClearAbortController) {
        pinnedClearAbortController.abort();
        pinnedClearAbortController = null;
    }
}

function armPinnedClearOnFirstInteraction() {
    if (pinnedClearAbortController || pinnedTreePaths.size === 0) return;
    const controller = new AbortController();
    pinnedClearAbortController = controller;
    const clearOnInteraction = () => clearPinnedTreeSelection();
    const options = { capture: true, signal: controller.signal };
    ['click', 'contextmenu', 'mousedown', 'keydown', 'touchstart'].forEach((eventName) => {
        document.addEventListener(eventName, clearOnInteraction, options);
    });
}

function applyPinnedTreeSelection(path) {
    if (!path) return;
    const node = getTreeNodeByPath(path);
    if (!node) return;
    node.classList.add('pinned-focus');
    pinnedTreePaths.add(String(path));
    pinnedTreePath = String(path);
    armPinnedClearOnFirstInteraction();
}

function pinTreePaths(paths, { scrollToLast = false } = {}) {
    const list = (Array.isArray(paths) ? paths : [paths])
        .map((p) => String(p || '').trim())
        .filter(Boolean);
    if (list.length === 0) return;
    clearPinnedTreeSelection();
    list.forEach((path) => applyPinnedTreeSelection(path));
    if (scrollToLast) {
        const lastNode = getTreeNodeByPath(list[list.length - 1]);
        if (lastNode) {
            lastNode.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }
}

window.clearPinnedTreeSelection = clearPinnedTreeSelection;

function updateTreeAutoShift() {
    const tree = document.getElementById('tree');
    if (!tree) return;
    const rect = tree.getBoundingClientRect();
    const nodes = tree.querySelectorAll('.node');
    let minPad = Infinity;
    let foundVisible = false;

    for (const node of nodes) {
        const nr = node.getBoundingClientRect();
        if (nr.bottom >= rect.top && nr.top <= rect.bottom) {
            foundVisible = true;
            const pad = parseInt(node.style.paddingLeft || '0', 10) || 0;
            if (pad < minPad) minPad = pad;
        }
    }

    if (!foundVisible || !Number.isFinite(minPad)) minPad = 0;

    // Shift left only when visible nodes are deeply nested.
    const shift = Math.max(0, minPad - 8);
    treeShiftTarget = shift;

    if (!treeShiftAnimRafId) {
        treeShiftAnimRafId = requestAnimationFrame(animateTreeAutoShift);
    }
}

function updateTreeLabelWidths() {
    const tree = document.getElementById('tree');
    if (!tree) return;
    const treeRect = tree.getBoundingClientRect();
    const treeRight = treeRect.right - 8;
    const labels = tree.querySelectorAll('.node .label');
    for (const label of labels) {
        const labelRect = label.getBoundingClientRect();
        const available = Math.floor(treeRight - labelRect.left);
        if (available > 40) {
            label.style.maxWidth = `${available}px`;
        } else {
            label.style.maxWidth = '40px';
        }
    }
}

function scheduleTreeAutoShift() {
    if (treeShiftMeasureRafId) return;
    treeShiftMeasureRafId = requestAnimationFrame(() => {
        treeShiftMeasureRafId = null;
        updateTreeAutoShift();
        if (typeof syncAllTreeChildrenGuides === 'function') syncAllTreeChildrenGuides();
    });
}

function animateTreeAutoShift() {
    const tree = document.getElementById('tree');
    if (!tree) {
        treeShiftAnimRafId = null;
        return;
    }

    // Smoothly approach target shift to avoid abrupt jumps while scrolling.
    const delta = treeShiftTarget - treeShiftCurrent;
    treeShiftCurrent += delta * 0.18;
    tree.style.setProperty('--tree-auto-shift', `${-treeShiftCurrent.toFixed(2)}px`);
    updateTreeLabelWidths();

    if (Math.abs(delta) < 0.35) {
        treeShiftCurrent = treeShiftTarget;
        tree.style.setProperty('--tree-auto-shift', `${-treeShiftCurrent.toFixed(2)}px`);
        updateTreeLabelWidths();
        treeShiftAnimRafId = null;
        return;
    }

    treeShiftAnimRafId = requestAnimationFrame(animateTreeAutoShift);
}

function initTreeAutoShift() {
    if (treeAutoShiftInitialized) return;
    treeAutoShiftInitialized = true;
    const tree = document.getElementById('tree');
    if (!tree) return;
    tree.addEventListener('scroll', scheduleTreeAutoShift, { passive: true });
    window.addEventListener('resize', scheduleTreeAutoShift);
    scheduleTreeAutoShift();
    setTimeout(updateTreeLabelWidths, 0);
}

window.focusPathInTree = async function(path) {
    if (!path) return;
    let targetPath = String(path || '').replace(/^\/+/, '');
    const targetRoot = typeof getRootFromPath === 'function' ? getRootFromPath(targetPath) : getCurrentCloudRoot();
    if (targetRoot !== getCurrentCloudRoot() && typeof window.switchCloudRoot === 'function') {
        if (typeof fromMainTreePath === 'function') targetPath = fromMainTreePath(targetPath, targetRoot);
        await window.switchCloudRoot(targetRoot, { silent: true });
    } else if (typeof fromMainTreePath === 'function') {
        targetPath = fromMainTreePath(targetPath, targetRoot);
    }
    const parts = targetPath.split('/').filter(Boolean);
    let cur = '';
    for (const part of parts) {
        cur = cur ? `${cur}/${part}` : part;
        expandedFolders.add(cur);
    }
    await saveFolderState();
    await buildTree('');
    await restoreFolderState();
    await expandFolderToPath(targetPath);
    pinTreePaths(targetPath, { scrollToLast: true });
};

async function getAllFoldersRecursive(path) {
    const items = await api('list', { path }, 'GET');
    let folders = items.items.filter(i => i.type === 'dir').map(i => ({ name: i.name, path: i.path }));
    for (const folder of [...folders]) {
        folders = folders.concat(await getAllFoldersRecursive(folder.path));
    }
    return folders;
}
function updateCurrentFolderPath() {
    const select = document.getElementById('target-folder-select');
    const pathDisplay = document.getElementById('current-folder-path');
    pathDisplay.textContent = select.value || '/';
}
async function confirmMove(sourcePath, modal) {
    const isCopy = window.transferDialogMode === 'copy';
    const targetDir = window.selectedMoveFolder !== undefined ? window.selectedMoveFolder : '';
    if (targetDir === '' && !isCopy) {
        const confirmModal = await showConfirmModal(t(i18n, 'moveToRootConfirmTitle'), t(i18n, 'moveToRootConfirm'));
        if (!confirmModal.confirmed) return;
    }
    if (sourcePath === targetDir) {
        flashStatus(t(i18n, 'cannotMoveToSameFolder'));
        return;
    }
    if (targetDir.startsWith(sourcePath + '/')) {
        flashStatus(t(i18n, 'cannotMoveToSubfolder'));
        return;
    }
    try {
        const sourceRoot = window.moveDialogSourceRoot || getCurrentCloudRoot();
        const targetRoot = window.selectedMoveRoot || 'main';
        const payload = {
            source_path: sourcePath,
            target_dir: targetDir,
            target_root: targetRoot,
            root: sourceRoot
        };
        if (isCopy) await api('copy', payload);
        else await api('move', payload);
        const newName = sourcePath.split('/').pop();
        const newPath = targetDir ? targetDir + '/' + newName : newName;
        const currentRoot = getCurrentCloudRoot();
        if (typeof patchTreeCacheForRoot === 'function') {
            if (isCopy) {
                await patchTreeCacheForRoot([{ op: 'add', item: { path: newPath, name: newName, type: window.transferDialogSourceType || 'file' } }], targetRoot);
            } else {
                await patchTreeCacheForRoot([{ op: 'move', old_path: sourcePath, new_path: newPath, new_name: newName }], sourceRoot);
            }
        } else if (typeof patchTreeCache === 'function') {
            const newCachePath = toMainTreePath(newPath, targetRoot);
            if (isCopy) {
                await patchTreeCache([{ op: 'add', path: newCachePath, name: newName, type: window.transferDialogSourceType || 'file' }]);
            } else {
                const oldCachePath = toMainTreePath(sourcePath, sourceRoot);
                await patchTreeCache([{ op: 'move', old_path: oldCachePath, new_path: newCachePath, new_name: newName }]);
            }
        }
        if (isCopy) {
            await buildTree('');
        } else if (currentRoot === sourceRoot && currentRoot === targetRoot && sourceRoot === 'main' && targetRoot === 'main') {
            treeNodeRename(sourcePath, newPath, newName);
        } else {
            await buildTree('');
        }
        flashStatus(t(i18n, isCopy ? 'copySuccess' : 'moveSuccess'));
        modal.remove();
        if (!isCopy && document.getElementById('viewer').dataset.path === sourcePath) closeViewer();
    } catch (err) {
        flashStatus(t(i18n, isCopy ? 'copyError' : 'moveError') + err.message);
    }
    clearSelection();
}
