function initFloatingDownloadButton() {
    const btn = document.getElementById('floating-download-btn');
    const panel = document.getElementById('download-progress-panel');
    
    // Set theme color for progress circle
    const circle = btn.querySelector('.circular-progress circle');
    if (circle && window.APP_CONFIG && window.APP_CONFIG.color) {
        circle.style.stroke = window.APP_CONFIG.color;
        circle.style.transition = 'stroke-dashoffset 0.08s linear';
    }
    
    btn.addEventListener('click', (e) => {
        e.stopPropagation();
        panel.classList.toggle('hidden');
        downloadState.panelVisible = !panel.classList.contains('hidden');
    });
    
    // Close panel when clicking outside
    document.addEventListener('click', (e) => {
        if (!panel.contains(e.target) && !btn.contains(e.target) && !panel.classList.contains('hidden')) {
            panel.classList.add('hidden');
            downloadState.panelVisible = false;
        }
    });
}

// Show floating download button
function showFloatingDownloadButton() {
    const btn = document.getElementById('floating-download-btn');
    btn.classList.remove('hidden');
    downloadState.active = true;
}

// Hide floating download button
function hideFloatingDownloadButton() {
    const btn = document.getElementById('floating-download-btn');
    const panel = document.getElementById('download-progress-panel');
    btn.classList.add('hidden');
    panel.classList.add('hidden');
    downloadState.active = false;
    downloadState.panelVisible = false;
}

// Update circular progress
function updateCircularProgress(progress) {
    const circle = document.querySelector('#floating-download-btn .circular-progress circle');
    if (circle) {
        const circumference = 183; // 2 * π * 29.1
        const offset = circumference - (progress / 100) * circumference;
        circle.style.strokeDashoffset = offset;
    }
}

// Add download item to panel
function addDownloadItem(path, filename, totalSize) {
    const container = document.getElementById('download-items-container');
    const downloadId = downloadState.nextId++;
    
    const item = document.createElement('div');
    item.className = 'download-item';
    item.id = `download-item-${downloadId}`;
    item.innerHTML = `
        <div class="download-filename" title="${filename}">${filename}</div>
        <div class="download-progress-bar">
            <div class="download-progress-fill" style="width: 0%"></div>
        </div>
        <div class="download-info">
            <span class="download-size">0 / ${formatBytes(totalSize)}</span>
            <span class="download-percent">0%</span>
        </div>
        <div class="download-speed">0 KB/s</div>
        <div class="download-actions">
            <button class="btn secondary small pause-download" data-id="${downloadId}">
                <svg width="12" height="12" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none">
                    <rect x="6" y="4" width="4" height="16" fill="currentColor"/>
                    <rect x="14" y="4" width="4" height="16" fill="currentColor"/>
                </svg>
                <span>${t(i18n, 'pause')}</span>
            </button>
            <button class="btn danger small cancel-download" data-id="${downloadId}">
                <svg width="12" height="12" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
                <span>${t(i18n, 'cancel')}</span>
            </button>
        </div>
    `;
    
    container.appendChild(item);
    
    downloadState.items.set(downloadId, {
        path,
        filename,
        totalSize,
        loaded: 0,
        progress: 0,
        speed: 0,
        paused: false,
        cancelled: false,
        canResume: true,
        running: false,
        controller: null,
        chunks: [],
        objectUrl: null,
        startTime: Date.now(),
        lastLoaded: 0,
        lastTime: Date.now()
    });
    
    // Add event listeners
    item.querySelector('.pause-download').addEventListener('click', () => togglePauseDownload(downloadId));
    item.querySelector('.cancel-download').addEventListener('click', () => cancelDownload(downloadId));
    ensureDownloadRenderLoop();

    return downloadId;
}

// Add to the top of the file
let pendingUpdates = new Map();
let downloadRenderTimer = null;
const RENDER_INTERVAL = 50; // ms - UI refresh rate (20 times per second)

function ensureDownloadRenderLoop() {
    if (downloadRenderTimer) return;
    downloadRenderTimer = setInterval(() => {
        renderPendingUpdates();
        if (downloadState.items.size === 0 && pendingUpdates.size === 0) {
            clearInterval(downloadRenderTimer);
            downloadRenderTimer = null;
        }
    }, RENDER_INTERVAL);
}

// In the updateDownloadProgress function
function updateDownloadProgress(downloadId, loaded) {
    const item = downloadState.items.get(downloadId);
    if (!item || item.cancelled) return;
    
    const now = Date.now();
    const timeDiff = (now - item.lastTime) / 1000;
    
        // Update the speed more often so the UI does not 'freeze'.
    if (timeDiff >= 0.25) {
        const loadedDiff = loaded - item.lastLoaded;
        item.speed = loadedDiff / timeDiff;
        item.lastLoaded = loaded;
        item.lastTime = now;
    }
    
    item.loaded = loaded;
    item.progress = (loaded / item.totalSize) * 100;
    
        // Update the circular progress right away (important for animation)
    if (downloadState.items.size > 0) {
        const totalProgress = Array.from(downloadState.items.values())
            .reduce((sum, item) => sum + item.progress, 0) / downloadState.items.size;
        updateCircularProgress(totalProgress);
    }
    
        // Save the update for deferred panel rendering
    pendingUpdates.set(downloadId, {
        progress: item.progress,
        loaded: loaded,
        speed: item.speed,
        totalSize: item.totalSize
    });

        // Ensure updates regardless of focus/clicks in the UI.
    ensureDownloadRenderLoop();
    renderPendingUpdates();
}

// Function to render accumulated updates
function renderPendingUpdates() {
    if (pendingUpdates.size === 0) return;
    
    pendingUpdates.forEach((update, downloadId) => {
        const itemElement = document.getElementById(`download-item-${downloadId}`);
        if (itemElement) {
            const fill = itemElement.querySelector('.download-progress-fill');
            const sizeSpan = itemElement.querySelector('.download-size');
            const percentSpan = itemElement.querySelector('.download-percent');
            const speedSpan = itemElement.querySelector('.download-speed');
            
            if (fill) fill.style.width = `${update.progress}%`;
            if (sizeSpan) sizeSpan.textContent = `${formatBytes(update.loaded)} / ${formatBytes(update.totalSize)}`;
            if (percentSpan) percentSpan.textContent = `${Math.round(update.progress)}%`;
            if (speedSpan) speedSpan.textContent = `${formatBytes(update.speed)}/s`;
        }
    });
    
    pendingUpdates.clear();
}

// Toggle pause/resume download
function togglePauseDownload(downloadId) {
    const item = downloadState.items.get(downloadId);
    if (item) {
        if (item.canResume === false) return;
        item.paused = !item.paused;
        const btn = document.querySelector(`#download-item-${downloadId} .pause-download span`);
        if (btn) {
            btn.textContent = item.paused ? t(i18n, 'resume') : t(i18n, 'pause');
        }

        if (item.paused) {
            if (item.controller) {
                item.controller.abort();
            }
        } else {
            continueDownload(downloadId);
        }
    }
}

// Cancel download
function cancelDownload(downloadId) {
    const item = downloadState.items.get(downloadId);
    if (item) {
        item.cancelled = true;
        item.paused = false;
        if (item.controller) {
            item.controller.abort();
        }
        if (item.objectUrl) {
            URL.revokeObjectURL(item.objectUrl);
            item.objectUrl = null;
        }
        
        // Remove from UI
        const itemElement = document.getElementById(`download-item-${downloadId}`);
        if (itemElement) {
            itemElement.remove();
        }
        
        downloadState.items.delete(downloadId);
        
        // Hide floating button if no downloads left
        if (downloadState.items.size === 0) {
            hideFloatingDownloadButton();
            if (downloadRenderTimer) {
                clearInterval(downloadRenderTimer);
                downloadRenderTimer = null;
            }
        } else {
            // Update circular progress
            const totalProgress = Array.from(downloadState.items.values())
                .reduce((sum, item) => sum + item.progress, 0) / downloadState.items.size;
            updateCircularProgress(totalProgress);
        }
    }
}

// Override downloadPath function
async function downloadPath(path) {
    try {
        showFloatingDownloadButton();
        const fileInfo = await api('file_info', { path }, 'GET');
        const filename = path.split('/').pop();
        const downloadId = addDownloadItem(path, filename, fileInfo.size);
        await downloadWithProgress(downloadId);
        return true;
    } catch (err) {
        console.error('Download error:', err);
        flashStatus(t(i18n, 'downloadError') + ': ' + err.message);
        return false;
    }
}

function finalizeDownloadedFile(downloadId) {
    const item = downloadState.items.get(downloadId);
    if (!item || item.cancelled) return;

    const blob = new Blob(item.chunks);
    const downloadUrl = URL.createObjectURL(blob);
    item.objectUrl = downloadUrl;

    const a = document.createElement('a');
    a.href = downloadUrl;
    a.download = item.filename;
    document.body.appendChild(a);
    a.click();
    a.remove();

    item.progress = 100;
    updateDownloadProgress(downloadId, item.totalSize);

    setTimeout(() => {
        const targetItem = downloadState.items.get(downloadId);
        if (targetItem && targetItem.objectUrl) {
            URL.revokeObjectURL(targetItem.objectUrl);
            targetItem.objectUrl = null;
        }
        cancelDownload(downloadId);
    }, 2000);
}

// Download with progress tracking using fetch and Range resume
async function downloadWithProgress(downloadId) {
    const item = downloadState.items.get(downloadId);
    if (!item || item.cancelled || item.running) return;

    item.running = true;
    item.controller = new AbortController();
    const startByte = item.loaded;
    const url = `api.php?action=download&path=${encodeURIComponent(item.path)}`;
    const headers = {};
    if (startByte > 0) {
        headers.Range = `bytes=${startByte}-`;
    }

    try {
        const response = await fetch(url, {
            headers,
            signal: item.controller.signal
        });
        if (!response.ok) throw new Error('Download failed');

        const pauseBtn = document.querySelector(`#download-item-${downloadId} .pause-download`);
        const acceptsRanges = (response.headers.get('accept-ranges') || '').toLowerCase().includes('bytes');
        if (startByte === 0) {
            item.canResume = acceptsRanges || response.status === 206;
            if (pauseBtn && !item.canResume) {
                pauseBtn.classList.add('hidden');
            }
        }

        const supportsResume = response.status === 206;
        if (pauseBtn && !supportsResume && startByte > 0) {
                        // If the server did not support Range after a pause, disable resume for this file.
            pauseBtn.classList.add('hidden');
        }
        if (startByte > 0 && !supportsResume) {
                        // The server did not support Range: restart from the beginning.
            item.loaded = 0;
            item.lastLoaded = 0;
            item.lastTime = Date.now();
            item.chunks = [];
            updateDownloadProgress(downloadId, 0);
        }

        const reader = response.body.getReader();

        while (true) {
            const liveItem = downloadState.items.get(downloadId);
            if (!liveItem || liveItem.cancelled || liveItem.paused) {
                try { await reader.cancel(); } catch (_) {}
                return;
            }

            const { done, value } = await reader.read();
            if (done) break;
            if (!value) continue;

            liveItem.chunks.push(value);
            liveItem.loaded += value.length;
            updateDownloadProgress(downloadId, liveItem.loaded);
        }

        const completeItem = downloadState.items.get(downloadId);
        if (!completeItem || completeItem.cancelled || completeItem.paused) return;
        if (completeItem.loaded >= completeItem.totalSize) {
            finalizeDownloadedFile(downloadId);
        } else {
                        // Guard against an inaccurate Content-Length: try to reload.
            await continueDownload(downloadId);
        }
    } catch (err) {
        const liveItem = downloadState.items.get(downloadId);
        if (err?.name === 'AbortError') {
            return;
        }
        console.error('Download error:', err);
        flashStatus(t(i18n, 'downloadError') + ': ' + err.message);
        if (liveItem) {
            liveItem.cancelled = true;
        }
        cancelDownload(downloadId);
    } finally {
        const latestItem = downloadState.items.get(downloadId);
        if (latestItem) {
            latestItem.running = false;
            latestItem.controller = null;
        }
    }
}

// Continue paused download
async function continueDownload(downloadId) {
    const item = downloadState.items.get(downloadId);
    if (!item) return;
    if (item.cancelled || item.running || item.paused) return;
    if (item.loaded >= item.totalSize) {
        finalizeDownloadedFile(downloadId);
        return;
    }
    await downloadWithProgress(downloadId);
}

// Function to get all images in the folder
async function getImagesInFolder(folderPath, rootOverride) {
    try {
        const params = { path: folderPath };
        if (rootOverride) params.root = rootOverride;
        const response = await api('list', params, 'GET');
        const imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'tiff', 'tif'];
        
        const images = response.items
            .filter(item => {
                if (item.type === 'file') {
                    const ext = item.name.split('.').pop().toLowerCase();
                    return imageExtensions.includes(ext);
                }
                return false;
            })
            .map(item => item.name); // return only file names
        
        return images;
    } catch (err) {
        console.error('Failed to get images in folder:', err);
        return [];
    }
}

function initFormatTagsClickHandler() {
    const formatTags = document.querySelectorAll('#new-file-modal .format-tag');
    const extensionSelect = document.getElementById('file-extension-select');
    const customExtensionInput = document.getElementById('custom-extension-input');
    const selectedDisplay = document.querySelector('.custom-select-selected');
    
    formatTags.forEach(tag => {
        tag.addEventListener('click', function(e) {
            e.stopPropagation();
            
            let extension = this.textContent.trim();
            
                        // Check whether it starts with a dot
            if (!extension.startsWith('.')) {
                extension = '.' + extension;
            }
            
                        // Check whether the extension is in the dropdown
            let optionFound = false;
            for (let i = 0; i < extensionSelect.options.length; i++) {
                if (extensionSelect.options[i].value === extension) {
                    extensionSelect.value = extension;
                    optionFound = true;
                    break;
                }
            }
            
            if (optionFound) {
                                // Update the custom select
                if (selectedDisplay) {
                    const arrow = selectedDisplay.querySelector('span:last-child');
                    selectedDisplay.textContent = extension;
                    if (arrow) selectedDisplay.appendChild(arrow);
                }
                
                                // Hide the custom input field
                customExtensionInput.classList.add('hidden');
                customExtensionInput.value = '';
                
                                // Highlight the selected tag
                formatTags.forEach(t => t.style.background = '');
                this.style.background = 'var(--accent)';
                this.style.color = 'white';
                
                                // Selection animation
                this.style.transform = 'scale(1.1)';
                setTimeout(() => {
                    this.style.transform = 'scale(1)';
                }, 200);
                
                                // If this is a special format with a color
                const officeColors = {
                    '.docx': '#2b579a',
                    '.doc': '#2b579a',
                    '.xlsx': '#217346',
                    '.xls': '#217346',
                    '.csv': '#217346',
                    '.pptx': '#d24726',
                    '.ppt': '#d24726'
                };
                
                if (officeColors[extension]) {
                    if (selectedDisplay) {
                        selectedDisplay.style.borderColor = officeColors[extension];
                        selectedDisplay.style.boxShadow = `0 0 0 1px ${officeColors[extension]}33`;
                        selectedDisplay.style.color = officeColors[extension];
                        selectedDisplay.style.fontWeight = '600';
                    }
                } else {
                    if (selectedDisplay) {
                        selectedDisplay.style.borderColor = 'var(--border)';
                        selectedDisplay.style.boxShadow = '';
                        selectedDisplay.style.color = 'var(--fg)';
                        selectedDisplay.style.fontWeight = '400';
                    }
                }
                
                                // Add visual feedback for the file name input
                const fileNameInput = document.getElementById('new-file-name');
                if (fileNameInput.value) {
                                        // If there is a file name, show a preview of the full name
                    const fullFileName = fileNameInput.value + extension;
                    flashStatus(`File will be created as: ${fullFileName}`);
                }
            } else {
                                // If the extension is not in the list, use the custom one
                extensionSelect.value = 'custom';
                customExtensionInput.classList.remove('hidden');
                customExtensionInput.value = extension;
                customExtensionInput.focus();
                
                if (selectedDisplay) {
                    const arrow = selectedDisplay.querySelector('span:last-child');
                    selectedDisplay.textContent = 'Custom';
                    if (arrow) selectedDisplay.appendChild(arrow);
                }
            }
        });
        
                // Add cursor styles on hover
        tag.style.cursor = 'pointer';
        tag.style.transition = 'all 0.2s ease';
        
                // Add a hover effect
        tag.addEventListener('mouseenter', function() {
            if (this.style.background !== 'var(--accent)') {
                this.style.opacity = '0.8';
                this.style.transform = 'scale(1.05)';
            }
        });
        
        tag.addEventListener('mouseleave', function() {
            if (this.style.background !== 'var(--accent)') {
                this.style.opacity = '1';
                this.style.transform = 'scale(1)';
            }
        });
    });
}
async function getUserRole() {
    try {
                // Create an API request to check the role
        const url = new URL(window.APP_CONFIG.apiBase, window.location.href);
        url.searchParams.set('action', 'get_user_role');
        url.searchParams.set('v', Date.now()); // Disable caching

        const response = await fetch(url, {
            method: 'GET',
            headers: {
                'Cache-Control': 'no-cache',
                'Pragma': 'no-cache'
            }
        });


        if (!response.ok) {
            console.warn('Failed to get user role, status:', response.status);
            return 0;
        }

        const data = await response.json();

        return data.role || 0;
    } catch (err) {
        console.error('Failed to get user role:', err);
        return 0;
    }
}

function updateUserSettingsAvatar(photoUrl) {
    const img = document.getElementById('user-settings-avatar');
    if (!img) return;
    const url = (photoUrl && String(photoUrl).trim()) || '/default_avatar.png';
    img.onerror = () => { img.onerror = null; img.src = '/default_avatar.png'; };
    img.src = url;
}
function updateUserRoleIcon(role) {
    const icon = document.getElementById('user-role-icon');
    const label = document.getElementById('user-role-label');
    
    if (!icon || !label) return;

        // Remove all role classes
    icon.classList.remove('role-guest', 'role-manager', 'role-admin');

        // Set the corresponding class and text
    let roleClass, roleKey;
    switch(role) {
        case 0:
            roleClass = 'role-guest';
            roleKey = 'roleGuest';
            break;
        case 1:
            roleClass = 'role-manager';
            roleKey = 'roleManager';
            break;
        case 2:
            roleClass = 'role-admin';
            roleKey = 'roleAdmin';
            break;
        default:
            roleClass = 'role-guest';
            roleKey = 'roleGuest';
    }

    icon.classList.add(roleClass);
    label.textContent = t(window.i18n || {}, roleKey);
    label.setAttribute('data-i18n', roleKey);
}

function getSessionId() {
        // Try to get session_id from different sources
    if (typeof session_id !== 'undefined') {
        return session_id;
    }
    return getCookie('PHPSESSID');
}

function getCookie(name) {
    const value = `; ${document.cookie}`;
    const parts = value.split(`; ${name}=`);
    if (parts.length === 2) return parts.pop().split(';').shift();
    return null;
}


// ------------------------------------------------------------------
//  Desktop Sync Token Management (Modal)
// ------------------------------------------------------------------
function openTextToSpeechEditor(filePath) {
    // Check if there's an existing view tab for this file
    const existingViewTab = tabsState.tabs.find(tab => tab.path === filePath && !tab.ttsEditor);
    const inheritedRoot = existingViewTab ? existingViewTab.root : null;
    if (existingViewTab) {
        closeTab(existingViewTab.id);
    }

    // Check if TTS editor tab already exists
    const existingEditorTab = tabsState.tabs.find(tab => tab.path === filePath && tab.ttsEditor);
    if (existingEditorTab) {
        switchToTab(existingEditorTab.id);
        return;
    }

    // Check tab limit
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
        ttsEditor: true,
        root: inheritedRoot || getCurrentCloudRoot(),
        title: filePath.split('/').pop() + ' (TTS)',
        modified: false,
        content: null
    };

    // Insert tab after active tab
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
    restoreTabContent(newTab);
}
