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

// Добавьте в начало файла
let pendingUpdates = new Map();
let downloadRenderTimer = null;
const RENDER_INTERVAL = 50; // мс - частота обновления UI (20 раз в секунду)

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

// В функции updateDownloadProgress
function updateDownloadProgress(downloadId, loaded) {
    const item = downloadState.items.get(downloadId);
    if (!item || item.cancelled) return;
    
    const now = Date.now();
    const timeDiff = (now - item.lastTime) / 1000;
    
    // Обновляем скорость чаще, чтобы UI не "подвисал".
    if (timeDiff >= 0.25) {
        const loadedDiff = loaded - item.lastLoaded;
        item.speed = loadedDiff / timeDiff;
        item.lastLoaded = loaded;
        item.lastTime = now;
    }
    
    item.loaded = loaded;
    item.progress = (loaded / item.totalSize) * 100;
    
    // Обновляем круговой прогресс сразу (это важно для анимации)
    if (downloadState.items.size > 0) {
        const totalProgress = Array.from(downloadState.items.values())
            .reduce((sum, item) => sum + item.progress, 0) / downloadState.items.size;
        updateCircularProgress(totalProgress);
    }
    
    // Сохраняем обновление для отложенной отрисовки панели
    pendingUpdates.set(downloadId, {
        progress: item.progress,
        loaded: loaded,
        speed: item.speed,
        totalSize: item.totalSize
    });

    // Гарантируем обновления независимо от фокуса/кликов в UI.
    ensureDownloadRenderLoop();
    renderPendingUpdates();
}

// Функция для отрисовки накопленных обновлений
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
            // Если сервер не поддержал Range после паузы — отключаем resume для этого файла.
            pauseBtn.classList.add('hidden');
        }
        if (startByte > 0 && !supportsResume) {
            // Сервер не поддержал Range: перезапускаем с начала.
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
            // Защита на случай неточного Content-Length: пробуем догрузить.
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

// Функция для получения всех изображений в папке
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
            .map(item => item.name); // возвращаем только имена файлов
        
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
            
            // Проверяем, начинается ли с точки
            if (!extension.startsWith('.')) {
                extension = '.' + extension;
            }
            
            // Проверяем, есть ли такое расширение в выпадающем списке
            let optionFound = false;
            for (let i = 0; i < extensionSelect.options.length; i++) {
                if (extensionSelect.options[i].value === extension) {
                    extensionSelect.value = extension;
                    optionFound = true;
                    break;
                }
            }
            
            if (optionFound) {
                // Обновляем кастомный селект
                if (selectedDisplay) {
                    const arrow = selectedDisplay.querySelector('span:last-child');
                    selectedDisplay.textContent = extension;
                    if (arrow) selectedDisplay.appendChild(arrow);
                }
                
                // Скрываем кастомное поле ввода
                customExtensionInput.classList.add('hidden');
                customExtensionInput.value = '';
                
                // Подсвечиваем выбранный тег
                formatTags.forEach(t => t.style.background = '');
                this.style.background = 'var(--accent)';
                this.style.color = 'white';
                
                // Анимация выбора
                this.style.transform = 'scale(1.1)';
                setTimeout(() => {
                    this.style.transform = 'scale(1)';
                }, 200);
                
                // Если это специальный формат с цветом
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
                
                // Добавляем визуальную обратную связь для поля ввода имени файла
                const fileNameInput = document.getElementById('new-file-name');
                if (fileNameInput.value) {
                    // Если есть имя файла, показываем предварительный просмотр полного имени
                    const fullFileName = fileNameInput.value + extension;
                    flashStatus(`File will be created as: ${fullFileName}`);
                }
            } else {
                // Если расширение не найдено в списке, используем кастомное
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
        
        // Добавляем стили для курсора при наведении
        tag.style.cursor = 'pointer';
        tag.style.transition = 'all 0.2s ease';
        
        // Добавляем эффект при наведении
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
        // Создаем запрос к API для проверки роли
        const url = new URL(window.APP_CONFIG.apiBase, window.location.href);
        url.searchParams.set('action', 'get_user_role');
        url.searchParams.set('v', Date.now()); // Отключаем кэширование

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

    // Удаляем все классы ролей
    icon.classList.remove('role-guest', 'role-manager', 'role-admin');

    // Устанавливаем соответствующий класс и текст
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
    // Пытаемся получить session_id из разных источников
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
