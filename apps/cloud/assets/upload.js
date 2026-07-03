let uploadState = {
  isUploading: false,
  currentUploadController: null,
  animationFrame: null,

  // Статистика
  speedHistory: [],
  speedHistoryValues: [],
  MAX_SPEED_POINTS: 60,
  peakSpeed: 0,
  lastUpdateTime: 0,
  lastUploadedBytes: 0,
  totalBytes: 0,
  uploadedBytes: 0,
  startTime: 0,

  // Для сглаживания скорости
  speedBuffer: [],
  SMOOTHING_FACTOR: 8
};

const SPEED_GRAPH_THRESHOLD_BYTES = 25 * 1024 * 1024;
const UPLOAD_TIME_UNKNOWN = '∞';

// upload.js - Добавить после существующего uploadState

// Состояние для плавающей кнопки загрузки
let uploadFloatingState = {
  active: false,
  items: new Map(), // uploadId -> { filename, progress, speed, total, loaded, status }
  nextId: 1,
  panelVisible: false,
  minimized: false // новое состояние для свернутого режима
};

let uploadFloatingUIInitialized = false;
let uploadFloatingObserver = null;

// Функция для показа плавающей кнопки загрузки
function showFloatingUploadButton() {
  initFloatingUploadUI();
  const btn = document.getElementById('floating-upload-btn');
  if (btn) {
    btn.classList.remove('hidden');
  }
  uploadFloatingState.active = true;
  positionFloatingUploadButton();
}

// Функция для скрытия плавающей кнопки загрузки
function hideFloatingUploadButton() {
  initFloatingUploadUI();
  const btn = document.getElementById('floating-upload-btn');
  const panel = document.getElementById('upload-progress-panel');
  if (btn) btn.classList.add('hidden');
  if (panel) panel.classList.add('hidden');
  uploadFloatingState.active = false;
  uploadFloatingState.panelVisible = false;
}

function positionFloatingUploadButton() {
  const button = document.getElementById('floating-upload-btn');
  if (!button) return;

  const downloadBtn = document.getElementById('floating-download-btn');
  if (downloadBtn && !downloadBtn.classList.contains('hidden')) {
    button.style.bottom = '110px';
  } else {
    button.style.bottom = '40px';
  }
}

function initFloatingUploadUI() {
  if (uploadFloatingUIInitialized) return;

  const button = document.getElementById('floating-upload-btn');
  const panel = document.getElementById('upload-progress-panel');
  if (!button || !panel) return;

  const circle = button.querySelector('.circular-progress circle');
  if (circle) {
    if (window.APP_CONFIG && window.APP_CONFIG.color) {
      circle.style.stroke = window.APP_CONFIG.color;
    }
    const circumference = 2 * Math.PI * 29.1;
    circle.style.strokeDasharray = circumference;
    circle.style.strokeDashoffset = circumference;
    circle.style.transition = 'stroke-dashoffset 0.3s linear';
  }

  const minimizeBtn = document.getElementById('upload-progress-minimize-btn');
  if (minimizeBtn) {
    minimizeBtn.addEventListener('click', (e) => {
      e.stopPropagation();
      minimizeUploadModal();
    });
  }

  button.addEventListener('click', (e) => {
    e.stopPropagation();
    if (uploadFloatingState.minimized) {
      expandUploadModal();
    } else {
      toggleUploadProgressPanel();
    }
  });

  document.addEventListener('click', (e) => {
    if (!panel.contains(e.target) &&
      !button.contains(e.target) &&
      !panel.classList.contains('hidden')) {
      panel.classList.add('hidden');
      uploadFloatingState.panelVisible = false;
    }
  });

  uploadFloatingObserver = new MutationObserver(positionFloatingUploadButton);
  uploadFloatingObserver.observe(document.body, { childList: true, subtree: true });
  positionFloatingUploadButton();
  uploadFloatingUIInitialized = true;
}

// Функция для сворачивания модального окна в плавающую кнопку
function minimizeUploadModal() {
  const modal = document.getElementById('upload-modal');
  const panel = document.getElementById('upload-progress-panel');
  const btn = document.getElementById('floating-upload-btn');

  if (modal && !modal.classList.contains('hidden')) {
    // Сохраняем состояние перед сворачиванием
    uploadFloatingState.minimized = true;

    // Скрываем модальное окно
    modal.classList.add('hidden');

    // Показываем плавающую кнопку
    showFloatingUploadButton();

    // Скрываем панель
    if (panel) panel.classList.add('hidden');

    // Обновляем круговой прогресс
    if (uploadFloatingState.items.size > 0) {
      const totalProgress = Array.from(uploadFloatingState.items.values())
        .reduce((sum, item) => sum + item.progress, 0) / uploadFloatingState.items.size;
      updateUploadCircularProgress(totalProgress);
    } else {
      updateUploadCircularProgress(0);
    }

    if (window.flashStatus) {
      window.flashStatus(window.i18n?.uploadMinimized || 'Upload minimized');
    }
  }
}

// Функция для разворачивания модального окна из свернутого состояния
function expandUploadModal() {
  const modal = document.getElementById('upload-modal');
  const btn = document.getElementById('floating-upload-btn');

  if (modal) {
    uploadFloatingState.minimized = false;
    modal.classList.remove('hidden');

    // Обновляем позицию модального окна
    modal.style.display = 'flex';

    // Скрываем плавающую кнопку
    if (btn) btn.classList.add('hidden');
  }
}

// Функция для переключения панели прогресса
function toggleUploadProgressPanel() {
  const panel = document.getElementById('upload-progress-panel');
  if (panel) {
    panel.classList.toggle('hidden');
    uploadFloatingState.panelVisible = !panel.classList.contains('hidden');
  }
}

// Обновление кругового прогресса загрузки
function updateUploadCircularProgress(progress) {
  const circle = document.querySelector('#floating-upload-btn .circular-progress circle');
  if (circle) {
    const circumference = 2 * Math.PI * 29.1; // 2 * π * 29.1 ≈ 183
    const offset = circumference - (progress / 100) * circumference;
    circle.style.strokeDashoffset = offset;
  }
}

// Добавление элемента загрузки в панель
function addUploadItem(filename, totalSize) {
  initFloatingUploadUI();
  const container = document.getElementById('upload-items-container');
  if (!container) return null;

  const uploadId = uploadFloatingState.nextId++;

  const item = document.createElement('div');
  item.className = 'download-item';
  item.id = `upload-item-${uploadId}`;
  item.innerHTML = `
        <div class="download-filename" title="${filename}">${filename}</div>
        <div class="download-progress-bar">
            <div class="download-progress-fill" style="width: 0%"></div>
        </div>
        <div class="download-info">
            <span class="download-size">0 / ${formatBytes(totalSize)}</span>
            <span class="download-percent">0%</span>
        </div>
        <div class="download-speed">0 B/s</div>
        <div class="download-status">${window.i18n?.uploading || 'Uploading...'}</div>
        <div class="download-actions">
            <button class="btn danger small cancel-upload" data-id="${uploadId}">
                <svg width="12" height="12" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
                <span>${window.i18n?.cancel || 'Cancel'}</span>
            </button>
        </div>
    `;

  container.appendChild(item);

  uploadFloatingState.items.set(uploadId, {
    filename,
    totalSize,
    loaded: 0,
    progress: 0,
    speed: 0,
    status: 'uploading',
    cancelled: false,
    startTime: Date.now(),
    lastLoaded: 0,
    lastTime: Date.now()
  });

  // Добавляем обработчик отмены
  item.querySelector('.cancel-upload').addEventListener('click', () => cancelUpload(uploadId));

  return uploadId;
}

// Обновление прогресса загрузки
function updateUploadProgress(uploadId, loaded, speed) {
  const item = uploadFloatingState.items.get(uploadId);
  if (!item || item.cancelled) return;

  const now = Date.now();
  const timeDiff = (now - item.lastTime) / 1000;

  item.loaded = loaded;
  item.progress = (loaded / item.totalSize) * 100;

  if (speed !== undefined) {
    item.speed = speed;
  } else if (timeDiff >= 0.5) {
    const loadedDiff = loaded - item.lastLoaded;
    item.speed = loadedDiff / timeDiff;
    item.lastLoaded = loaded;
    item.lastTime = now;
  }

  // Обновляем круговой прогресс
  if (uploadFloatingState.items.size > 0) {
    const totalProgress = Array.from(uploadFloatingState.items.values())
      .reduce((sum, item) => sum + item.progress, 0) / uploadFloatingState.items.size;
    updateUploadCircularProgress(totalProgress);
  }

  // Обновляем UI элемента
  const itemElement = document.getElementById(`upload-item-${uploadId}`);
  if (itemElement) {
    const fill = itemElement.querySelector('.download-progress-fill');
    const sizeSpan = itemElement.querySelector('.download-size');
    const percentSpan = itemElement.querySelector('.download-percent');
    const speedSpan = itemElement.querySelector('.download-speed');

    if (fill) fill.style.width = `${item.progress}%`;
    if (sizeSpan) sizeSpan.textContent = `${formatBytes(loaded)} / ${formatBytes(item.totalSize)}`;
    if (percentSpan) percentSpan.textContent = `${Math.round(item.progress)}%`;
    if (speedSpan) speedSpan.textContent = `${formatBytes(Number.isFinite(item.speed) ? item.speed : 0)}/s`;
  }
}

// Завершение загрузки
function completeUpload(uploadId) {
  const item = uploadFloatingState.items.get(uploadId);
  if (item) {
    item.status = 'completed';
    item.progress = 100;

    updateUploadProgress(uploadId, item.totalSize, 0);

    // Обновляем статус
    const itemElement = document.getElementById(`upload-item-${uploadId}`);
    if (itemElement) {
      const statusEl = itemElement.querySelector('.download-status');
      if (statusEl) statusEl.textContent = window.i18n?.uploadCompletedMark || 'Completed ✓';

      // Добавляем кнопку закрытия
      const actions = itemElement.querySelector('.download-actions');
      if (actions) {
        actions.innerHTML = `
                    <button class="btn secondary small close-upload" data-id="${uploadId}">
                        <svg width="12" height="12" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none">
                            <line x1="18" y1="6" x2="6" y2="18"></line>
                            <line x1="6" y1="6" x2="18" y2="18"></line>
                        </svg>
                        <span>${window.i18n?.close || 'Close'}</span>
                    </button>
                `;
        actions.querySelector('.close-upload').addEventListener('click', () => removeUploadItem(uploadId));
      }
    }

    // Через 2 секунды убираем элемент
    setTimeout(() => {
      removeUploadItem(uploadId);
    }, 2000);
  }
}

// Отмена загрузки
function cancelUpload(uploadId) {
  const item = uploadFloatingState.items.get(uploadId);
  if (item) {
    item.cancelled = true;
    item.status = 'cancelled';

    // Отменяем текущую загрузку, если она активна
    if (uploadState.currentUploadController && uploadState.isUploading) {
      uploadState.currentUploadController.abort();
    }

    removeUploadItem(uploadId);
  }
}

// Удаление элемента загрузки
function removeUploadItem(uploadId) {
  const itemElement = document.getElementById(`upload-item-${uploadId}`);
  if (itemElement) {
    itemElement.remove();
  }

  uploadFloatingState.items.delete(uploadId);

  // Скрываем кнопку если нет активных загрузок
  if (uploadFloatingState.items.size === 0 && !uploadFloatingState.minimized) {
    hideFloatingUploadButton();
  } else {
    // Обновляем круговой прогресс
    const totalProgress = Array.from(uploadFloatingState.items.values())
      .reduce((sum, item) => sum + item.progress, 0) / uploadFloatingState.items.size || 0;
    updateUploadCircularProgress(totalProgress);
  }
}

// Очистка устаревших сессий загрузки (вызывается при загрузке страницы)
function cleanupOldUploadSessions() {
  const keysToRemove = [];
  for (let i = 0; i < localStorage.length; i++) {
    const key = localStorage.key(i);
    if (key && key.startsWith('upload_session_')) {
      keysToRemove.push(key);
    }
  }
  keysToRemove.forEach(key => localStorage.removeItem(key));
}

/**
 * Открыть диалог загрузки
 * @param {string} basePath - Путь, куда загружать файлы
 */
function openUploadDialog(basePath) {
  showUploadModal(basePath);
}

/**
 * Показать модальное окно загрузки
 * @param {string} basePath - Путь, куда загружать файлы
 */
function showUploadModal(basePath) {
  // Проверяем, существует ли уже модальное окно
  let modal = document.getElementById('upload-modal');

  if (modal) {
    // Если существует, удаляем и создаем заново (чтобы сбросить состояние)
    modal.remove();
  }

  // Создаем модальное окно
  modal = document.createElement('div');
  modal.id = 'upload-modal';
  modal.className = 'modal';
  modal.innerHTML = getUploadModalHTML();

  document.body.appendChild(modal);

  const accentColor = getColorFromURL();
  const progressFill = modal.querySelector('#progress-fill');
  if (progressFill) {
    progressFill.style.background = accentColor;
  }

  initUploadHandlers(modal, basePath);
}

/**
 * HTML шаблон для модального окна загрузки
 */
function getUploadModalHTML() {
  const i18n = window.i18n || {};

  return `
        <div class="modal-content" style="max-width: 550px;">
            <div class="modal-header">
                <h3>
                    <svg width="20" height="20" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none" style="vertical-align: middle; margin-right: 8px;">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                        <polyline points="17 8 12 3 7 8"></polyline>
                        <line x1="12" y1="3" x2="12" y2="15"></line>
                    </svg>
                    ${i18n.uploadFiles || 'Upload Files'}
                </h3>
                <div style="display: flex; gap: 5px;">
                    <button class="icon-btn minimize-btn" title="Minimize">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-minus-icon lucide-minus"><path d="M5 12h14"/></svg>
                    </button>
                    <button class="icon-btn close-btn"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-x-icon lucide-x"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg></button>
                </div>
            </div>
            <div class="modal-body">
                <!-- Зона для выбора файлов -->
                <div class="upload-browse-panel" id="upload-browse-panel">
                    <div class="upload-zone upload-drop-zone" id="upload-drop-zone">
                        <svg class="upload-icon" width="48" height="48" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" fill="none">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                            <polyline points="17 8 12 3 7 8"></polyline>
                            <line x1="12" y1="3" x2="12" y2="15"></line>
                        </svg>
                        <div class="upload-title">${i18n.dragAndDrop || 'Drag and drop files or folders here'}</div>
                        <div class="upload-text">
                            <button class="btn primary" id="browse-files-btn">${i18n.browseFiles || 'Browse Files'}</button>
                            <input type="file" id="file-input" multiple style="display: none;" />
                            <button class="btn secondary" id="browse-folder-btn">${i18n.browseFolder || 'Browse Folder'}</button>
                            <input type="file" id="folder-input" webkitdirectory multiple style="display: none;" />
                        </div>
                    </div>
                </div>
                
                <!-- Прогресс загрузки -->
                <div id="upload-progress" class="upload-progress hidden">
                    <div class="progress-header">
                        <span id="progress-status">${i18n.preparingUpload || 'Preparing upload...'}</span>
                        <span id="progress-percent">0%</span>
                    </div>
                    
                    <!-- Прогресс-бар -->
                    <div class="progress-bar-container">
                        <div class="progress-bar">
                            <div class="progress-fill" id="progress-fill"></div>
                        </div>
                    </div>
                    
                    <!-- Статистика загрузки -->
                    <div class="upload-stats-grid" id="upload-stats">
                        <div class="stat-item">
                            <span class="stat-label">${i18n.uploadTotalSize || 'Total size'}</span>
                            <span class="stat-value" id="upload-total-size">0 B</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">${i18n.uploaded || 'Uploaded'}</span>
                            <span class="stat-value" id="upload-uploaded">0 B</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">${i18n.uploadSpeed || 'Speed'}</span>
                            <span class="stat-value" id="upload-speed">0 B/s</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">${i18n.uploadAverageSpeed || 'Avg speed'}</span>
                            <span class="stat-value" id="upload-avg-speed">0 B/s</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">${i18n.uploadPeakSpeed || 'Peak speed'}</span>
                            <span class="stat-value" id="upload-peak-speed">0 B/s</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">${i18n.uploadTimeRemaining || 'Time remaining'}</span>
                            <span class="stat-value" id="upload-eta">∞</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">${i18n.uploadTimeElapsed || 'Elapsed'}</span>
                            <span class="stat-value" id="upload-elapsed">0:00</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">${i18n.files || 'Files'}</span>
                            <span class="stat-value" id="file-count">0/0</span>
                        </div>
                    </div>
                    
                    <!-- График скорости -->
                    <div class="speed-graph-container">
                        <canvas id="speed-graph" width="500" height="100"></canvas>
                    </div>
                    
                    <!-- Информация о текущем файле -->
                    <div id="current-file" class="current-file"></div>
                </div>
                
                <!-- Результаты загрузки -->
                <div id="upload-results" class="upload-results hidden"></div>
            </div>
            
            <div class="modal-footer">
                <button id="btn-cancel-upload" class="btn secondary">${i18n.cancel || 'Cancel'}</button>
                <button id="btn-close-upload" class="btn primary hidden">${i18n.close || 'Close'}</button>
            </div>
        </div>
    `;
}

/**
 * Инициализация обработчиков для модального окна загрузки
 * @param {HTMLElement} modal - Модальное окно
 * @param {string} basePath - Путь для загрузки
 */
async function initUploadHandlers(modal, basePath) {
  const uploadZone = modal.querySelector('#upload-browse-panel');
  const fileInput = modal.querySelector('#file-input');
  const folderInput = modal.querySelector('#folder-input');
  const browseFilesBtn = modal.querySelector('#browse-files-btn');
  const browseFolderBtn = modal.querySelector('#browse-folder-btn');
  const cancelBtn = modal.querySelector('#btn-cancel-upload');
  const closeBtn = modal.querySelector('#btn-close-upload');
  const progressSection = modal.querySelector('#upload-progress');
  const resultsSection = modal.querySelector('#upload-results');

  // После получения ссылок на элементы
  const minimizeBtn = modal.querySelector('.minimize-btn');

  // Добавьте обработчик для кнопки сворачивания
  if (minimizeBtn) {
    minimizeBtn.addEventListener('click', (e) => {
      e.stopPropagation();
      minimizeUploadModal();
    });
  }
  // ============= ВСПОМОГАТЕЛЬНЫЕ ФУНКЦИИ =============

  function formatTimeElapsed(ms) {
    if (!Number.isFinite(ms) || ms < 0) return '0:00';
    const seconds = Math.floor(ms / 1000);
    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    const secs = seconds % 60;
    if (hours > 0) {
      return `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
    }
    return `${minutes.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
  }
  function formatTimeRemaining(seconds) {
    if (!Number.isFinite(seconds) || seconds < 0 || seconds === Infinity) return UPLOAD_TIME_UNKNOWN;
    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    const secs = Math.floor(seconds % 60);
    if (hours > 0) {
      return `${hours}h ${minutes}m`;
    } else if (minutes > 0) {
      return `${minutes}m ${secs}s`;
    } else if (secs > 5) {
      return `${secs}s`;
    } else if (secs > 0) {
      return '< 5s';
    }
    return UPLOAD_TIME_UNKNOWN;
  }
  function formatUploadEta(eta, speed) {
    if (!Number.isFinite(speed) || speed <= 0) return UPLOAD_TIME_UNKNOWN;
    if (!Number.isFinite(eta) || eta <= 0 || eta >= 86400) return UPLOAD_TIME_UNKNOWN;
    return formatTimeRemaining(eta);
  }

  function formatBytes(bytes, decimals = 1) {
    if (!Number.isFinite(bytes) || bytes < 0) return '0 B';
    if (bytes === 0) return '0 B';
    if (bytes < 1024) return bytes + ' B';

    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
    const k = 1024;
    const i = Math.floor(Math.log(bytes) / Math.log(k));

    const dec = i >= 3 ? 2 : decimals;

    return parseFloat((bytes / Math.pow(k, i)).toFixed(dec)) + ' ' + units[i];
  }

  function updateStatValue(container, selector, newValue) {
    const element = container.querySelector(selector);
    if (!element || element.textContent === newValue) return;

    element.classList.add('updating');
    element.textContent = newValue;

    setTimeout(() => {
      element.classList.remove('updating');
    }, 200);
  }

  function calculateSmoothedSpeed(rawSpeed) {
    uploadState.speedBuffer.push(rawSpeed);
    if (uploadState.speedBuffer.length > uploadState.SMOOTHING_FACTOR) {
      uploadState.speedBuffer.shift();
    }

    if (uploadState.speedBuffer.length === 0) return 0;

    const sorted = [...uploadState.speedBuffer].sort((a, b) => a - b);
    const mid = Math.floor(sorted.length / 2);

    return sorted.length % 2 === 0
      ? (sorted[mid - 1] + sorted[mid]) / 2
      : sorted[mid];
  }

  function updateAllStats(progressSection, current, total, currentFileName, speed, avgSpeed, peakSpeed, eta, totalBytes, uploadedBytes, elapsed) {
    const safeCurrent = Number.isFinite(current) ? current : 0;
    const percent = total > 0 ? Math.round((safeCurrent / total) * 100) : 0;

    const progressFill = progressSection.querySelector('#progress-fill');
    if (progressFill) {
      const currentWidth = parseFloat(progressFill.style.width) || 0;
      if (Math.abs(percent - currentWidth) > 0.5) {
        progressFill.style.width = percent + '%';
      }
    }

    updateStatValue(progressSection, '#progress-percent', percent + '%');
    updateStatValue(progressSection, '#file-count', `${Math.floor(current)}/${total}`);
    updateFileCountColor(progressSection, Math.floor(current), total, false, 0);

    const statusEl = progressSection.querySelector('#progress-status');
    if (statusEl) {
      if (current === total) {
        statusEl.textContent = window.i18n?.uploadCompleted || 'Upload completed';
      } else if (current === 0) {
        statusEl.textContent = window.i18n?.preparingUpload || 'Preparing upload...';
      } else {
        statusEl.textContent = window.i18n?.uploadingFiles || 'Uploading files...';
      }
    }

    updateStatValue(progressSection, '#upload-total-size', formatBytes(totalBytes, 2));
    updateStatValue(progressSection, '#upload-uploaded', formatBytes(uploadedBytes, 2));
    updateStatValue(progressSection, '#upload-speed', formatBytes(Math.round(speed), 1) + '/s');
    updateStatValue(progressSection, '#upload-avg-speed', formatBytes(Math.round(avgSpeed), 1) + '/s');
    updateStatValue(progressSection, '#upload-peak-speed', formatBytes(Math.round(peakSpeed), 1) + '/s');

    const etaEl = progressSection.querySelector('#upload-eta');
    if (etaEl) {
      const etaText = formatUploadEta(eta, speed);
      if (etaEl.textContent !== etaText) {
        etaEl.textContent = etaText;
        etaEl.classList.add('updating');
        setTimeout(() => etaEl.classList.remove('updating'), 200);
      }
    }
    updateStatValue(progressSection, '#upload-elapsed', formatTimeElapsed(elapsed));

    const currentFileEl = progressSection.querySelector('#current-file');
    if (currentFileEl && currentFileName) {
      const maxLength = 40;
      let displayName = currentFileName;
      if (displayName.length > maxLength) {
        const ext = displayName.split('.').pop();
        const name = displayName.substring(0, displayName.lastIndexOf('.'));
        displayName = name.substring(0, maxLength - 3 - ext.length) + '….' + ext;
      }
      currentFileEl.textContent = displayName;
      currentFileEl.title = currentFileName;
    }
  }

  function updateFileCountColor(progressSection, uploadedCount, totalCount, isFinished = false, errorCount = 0) {
    const fileCountEl = progressSection.querySelector('#file-count');
    if (!fileCountEl) return;

    fileCountEl.style.fontWeight = '600';
    const applyColor = (color) => fileCountEl.style.setProperty('color', color, 'important');

    if (isFinished && uploadedCount === totalCount && errorCount === 0 && totalCount > 0) {
      applyColor('#27ae60'); // green: all files uploaded
      return;
    }

    if (isFinished && uploadedCount === 0 && totalCount > 0) {
      applyColor('#e74c3c'); // red: nothing uploaded
      return;
    }

    if (isFinished && uploadedCount < totalCount) {
      applyColor('#f1c40f'); // yellow: finished but partially uploaded
      return;
    }

    applyColor('#f1c40f'); // yellow: in progress
  }

  function drawSpeedGraph(progressSection, speedHistory, peakSpeed, currentProgress) {
    const canvas = progressSection.querySelector('#speed-graph');
    if (!canvas) return;

    const ctx = canvas.getContext('2d');
    const width = canvas.width;
    const height = canvas.height;

    ctx.clearRect(0, 0, width, height);

    if (speedHistory.length < 2) return;

    const padding = { top: 20, right: 45, bottom: 25, left: 45 };
    const graphWidth = width - padding.left - padding.right;
    const graphHeight = height - padding.top - padding.bottom;

    const maxSpeedValue = Math.max(peakSpeed * 1.15, 1024, ...speedHistory);

    let maxSpeed;
    if (maxSpeedValue < 1024) {
      maxSpeed = 1024;
    } else if (maxSpeedValue < 10240) {
      maxSpeed = Math.ceil(maxSpeedValue / 1024) * 1024;
    } else if (maxSpeedValue < 102400) {
      maxSpeed = Math.ceil(maxSpeedValue / 10240) * 10240;
    } else {
      maxSpeed = Math.ceil(maxSpeedValue / 102400) * 102400;
    }

    ctx.fillStyle = 'rgba(0, 0, 0, 0.02)';
    ctx.fillRect(padding.left, padding.top, graphWidth, graphHeight);

    ctx.strokeStyle = 'rgba(128, 128, 128, 0.2)';
    ctx.lineWidth = 0.5;

    const speedLevels = [0, 0.25, 0.5, 0.75, 1];
    speedLevels.forEach(level => {
      const y = padding.top + graphHeight * (1 - level);

      ctx.beginPath();
      ctx.strokeStyle = 'rgba(128, 128, 128, 0.15)';
      ctx.lineWidth = 0.5;
      ctx.moveTo(padding.left, y);
      ctx.lineTo(padding.left + graphWidth, y);
      ctx.stroke();

      const speedValue = maxSpeed * level;
      ctx.font = '9px monospace';
      ctx.fillStyle = 'var(--muted)';
      ctx.textAlign = 'right';
      ctx.fillText(formatBytes(speedValue, 0) + '/s', padding.left - 5, y - 2);
    });

    const progressLevels = [0, 25, 50, 75, 100];
    progressLevels.forEach(progress => {
      const x = padding.left + (graphWidth * progress / 100);

      ctx.beginPath();
      ctx.strokeStyle = 'rgba(128, 128, 128, 0.15)';
      ctx.lineWidth = 0.5;
      ctx.moveTo(x, padding.top);
      ctx.lineTo(x, padding.top + graphHeight);
      ctx.stroke();

      ctx.font = '9px monospace';
      ctx.fillStyle = 'var(--muted)';
      ctx.textAlign = 'center';
      ctx.fillText(progress + '%', x, padding.top + graphHeight + 15);
    });

    if (currentProgress > 0 && currentProgress < 100) {
      const progressX = padding.left + (graphWidth * currentProgress / 100);

      ctx.beginPath();
      ctx.strokeStyle = 'var(--accent)';
      ctx.lineWidth = 1;
      ctx.setLineDash([5, 3]);
      ctx.moveTo(progressX, padding.top);
      ctx.lineTo(progressX, padding.top + graphHeight);
      ctx.stroke();
      ctx.setLineDash([]);
    }

    ctx.beginPath();
    ctx.strokeStyle = 'var(--accent)';
    ctx.lineWidth = 2;
    ctx.setLineDash([]);

    const points = [];
    for (let i = 0; i < speedHistory.length; i++) {
      const progress = (i / (speedHistory.length - 1)) * 100;
      const x = padding.left + (graphWidth * progress / 100);

      const normalizedSpeed = Math.min(speedHistory[i] / maxSpeed, 1);
      const y = padding.top + graphHeight - (normalizedSpeed * graphHeight);

      points.push({
        x: Math.max(padding.left, Math.min(padding.left + graphWidth, x)),
        y: Math.max(padding.top, Math.min(padding.top + graphHeight, y))
      });
    }

    if (points.length > 1) {
      ctx.beginPath();
      ctx.moveTo(points[0].x, points[0].y);

      for (let i = 1; i < points.length; i++) {
        ctx.lineTo(points[i].x, points[i].y);
      }

      ctx.stroke();

      ctx.beginPath();
      ctx.moveTo(points[0].x, points[0].y);

      for (let i = 1; i < points.length; i++) {
        ctx.lineTo(points[i].x, points[i].y);
      }

      ctx.lineTo(points[points.length - 1].x, padding.top + graphHeight);
      ctx.lineTo(points[0].x, padding.top + graphHeight);
      ctx.closePath();

      const gradient = ctx.createLinearGradient(0, padding.top, 0, padding.top + graphHeight);
      gradient.addColorStop(0, 'rgba(133, 181, 3, 0.25)');
      gradient.addColorStop(0.7, 'rgba(133, 181, 3, 0.05)');
      gradient.addColorStop(1, 'rgba(133, 181, 3, 0)');

      ctx.fillStyle = gradient;
      ctx.fill();
    }

    ctx.fillStyle = 'var(--accent)';
    points.forEach((point, index) => {
      if (index % 3 === 0 || index === points.length - 1) {
        ctx.beginPath();
        ctx.arc(point.x, point.y, 2.5, 0, Math.PI * 2);
        ctx.fill();

        ctx.strokeStyle = 'var(--bg)';
        ctx.lineWidth = 1.5;
        ctx.stroke();
      }
    });

    ctx.font = 'bold 9px sans-serif';
    ctx.fillStyle = 'var(--muted)';
    ctx.textAlign = 'left';
    ctx.fillText('Speed', 5, padding.top - 5);
    ctx.textAlign = 'right';
    ctx.fillText('Progress →', width - 10, height - 5);
  }

  function addUploadResult(resultsSection, fileName, success, message = '') {
    const resultItem = document.createElement('div');
    resultItem.className = `upload-result ${success ? 'success' : 'error'}`;

    const displayName = fileName.length > 50
      ? fileName.substring(0, 45) + '…'
      : fileName;

    resultItem.innerHTML = `
            <span class="result-icon">${success ? '✓' : '✗'}</span>
            <span class="result-filename" title="${fileName.replace(/"/g, '&quot;')}">${displayName}</span>
            ${message ? `<span class="result-message">${message}</span>` : ''}
        `;

    resultsSection.appendChild(resultItem);
  }

  function showUploadResults(resultsSection, successCount, errorCount, totalCount, skippedCount = 0) {
    resultsSection.classList.remove('hidden');

    const existingSummary = resultsSection.querySelector('.upload-summary');
    if (existingSummary) existingSummary.remove();

    const i18n = window.i18n || {};
    const summary = document.createElement('div');
    summary.className = 'upload-summary';
    summary.innerHTML = `
            <h4>${i18n.uploadSummary || 'Upload Summary'}</h4>
            <div class="summary-stats">
                <div class="stat-item">
                    <span class="stat-label">${i18n.totalFiles || 'Total files'}:</span>
                    <span class="stat-value">${totalCount}</span>
                </div>
                <div class="stat-item" style="color: #27ae60;">
                    <span class="stat-label">${i18n.successful || 'Successful'}:</span>
                    <span class="stat-value">${successCount}</span>
                </div>
                ${skippedCount > 0 ? `
                <div class="stat-item" style="color: #f39c12;">
                    <span class="stat-label">${i18n.skipped || 'Skipped'}:</span>
                    <span class="stat-value">${skippedCount}</span>
                </div>
                ` : ''}
                ${errorCount > 0 ? `
                <div class="stat-item" style="color: #e74c3c;">
                    <span class="stat-label">${i18n.errors || 'Errors'}:</span>
                    <span class="stat-value">${errorCount}</span>
                </div>
                ` : ''}
            </div>
        `;

    resultsSection.appendChild(summary);
  }

  // ============= ОСНОВНЫЕ ФУНКЦИИ =============

  async function processDroppedItems(items, basePath) {
    const allFiles = [];

    async function processEntry(entry, path = '') {
      if (entry.isFile) {
        return new Promise((resolve) => {
          entry.file(file => {
            file.relativePath = path + file.name;
            allFiles.push(file);
            resolve();
          });
        });
      } else if (entry.isDirectory) {
        const dirReader = entry.createReader();

        return new Promise((resolve) => {
          const readEntries = () => {
            dirReader.readEntries(async (entries) => {
              if (entries.length === 0) {
                resolve();
              } else {
                await Promise.all(entries.map(childEntry =>
                  processEntry(childEntry, path + entry.name + '/')
                ));
                readEntries();
              }
            });
          };
          readEntries();
        });
      }
    }

    for (const item of items) {
      if (item.kind === 'file') {
        const entry = item.webkitGetAsEntry();
        if (entry) {
          await processEntry(entry);
        }
      }
    }

    if (allFiles.length > 0) {
      startUpload(allFiles, true);
    }
  }

  async function startUpload(files, isFolderUpload) {
    if (uploadState.isUploading) return;

    uploadState.speedBuffer = [];
    uploadState.speedHistory = [];
    uploadState.speedHistoryValues = [];
    uploadState.peakSpeed = 0;
    uploadState.lastUpdateTime = Date.now();
    uploadState.lastUploadedBytes = 0;
    uploadState.totalBytes = files.reduce((sum, file) => sum + file.size, 0);
    uploadState.uploadedBytes = 0;
    uploadState.startTime = Date.now();

    const totalFiles = files.length;

    updateProgress(progressSection, 0, totalFiles, window.i18n?.checkingSpace || 'Checking space...', 0, 0, uploadState.totalBytes, 0);

    try {
      const state = await api('get_state');
      const activeUser = state.users.find(u => u.name === state.active);

      if (!activeUser) {
        throw new Error('User not found');
      }

      const quotaBytes = activeUser.quota_mb * 1024 * 1024;
      const freeSpace = quotaBytes - activeUser.used_bytes;

      if (uploadState.totalBytes > freeSpace) {
        const requiredSize = formatBytes(uploadState.totalBytes);
        const availableSize = formatBytes(freeSpace);

        const warningModal = document.createElement('div');
        warningModal.className = 'modal';
        warningModal.style.display = 'flex';
        warningModal.innerHTML = `
                    <div class="modal-content" style="max-width: 500px;">
                        <div class="modal-header">
                            <h3 style="color: #e74c3c;">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="10"></circle>
                                    <line x1="12" y1="8" x2="12" y2="12"></line>
                                    <line x1="12" y1="16" x2="12.01" y2="16"></line>
                                </svg>
                                ${window.i18n?.insufficientSpace || 'Insufficient Space'}
                            </h3>
                            <button class="icon-btn close-btn">&times;</button>
                        </div>
                        <div class="modal-body">
                            <p>${(window.i18n?.insufficientSpaceMessage || 'Required {required}, available {available}').replace('{required}', requiredSize).replace('{available}', availableSize)}</p>
                            <div style="background: var(--panel); padding: 15px; border-radius: 4px; margin: 15px 0;">
                                <div class="property-row">
                                    <span class="property-label">${(window.i18n?.filesSelected || 'Files selected: {count}').replace('{count}', files.length)}:</span>
                                    <span class="property-value">${files.length}</span>
                                </div>
                                <div class="property-row">
                                    <span class="property-label">${window.i18n?.totalUploadSize || 'Total upload size'}:</span>
                                    <span class="property-value">${requiredSize}</span>
                                </div>
                                <div class="property-row">
                                    <span class="property-label">${window.i18n?.availableSpace || 'Available space'}:</span>
                                    <span class="property-value">${availableSize}</span>
                                </div>
                            </div>
                            <p>${window.i18n?.freeUpSpace || 'Free up space or adjust quota'}</p>
                        </div>
                        <div class="modal-footer">
                            <button id="btn-cancel-upload-warning" class="btn secondary">${window.i18n?.cancel || 'Cancel'}</button>
                            <button id="btn-ignore-warning" class="btn danger">${window.i18n?.uploadAnyway || 'Upload Anyway'}</button>
                        </div>
                    </div>
                `;

        document.body.appendChild(warningModal);

        return new Promise((resolve) => {
          warningModal.querySelector('.close-btn').addEventListener('click', () => {
            warningModal.remove();
            resolve();
          });

          warningModal.querySelector('#btn-cancel-upload-warning').addEventListener('click', () => {
            warningModal.remove();
            resolve();
          });

          warningModal.querySelector('#btn-ignore-warning').addEventListener('click', () => {
            warningModal.remove();
            performUpload(files, isFolderUpload);
            resolve();
          });

          warningModal.addEventListener('click', (e) => {
            if (e.target === warningModal) {
              warningModal.remove();
              resolve();
            }
          });
        });
      } else {
        performUpload(files, isFolderUpload);
      }
    } catch (err) {
      console.error('Failed to check quota:', err);
      if (window.flashStatus) {
        window.flashStatus((window.i18n?.quotaCheckFailed || 'Quota check failed') + ': ' + err.message);
      }
      performUpload(files, isFolderUpload);
    }
  }

  function updateProgress(progressSection, current, total, currentFileName, speed = 0, etaSeconds = 0, totalBytes = 0, uploadedBytes = 0) {
    const percent = total > 0 ? Math.round((current / total) * 100) : 0;

    const progressFill = progressSection.querySelector('#progress-fill');
    if (progressFill) {
      progressFill.style.width = percent + '%';
    }

    const percentEl = progressSection.querySelector('#progress-percent');
    if (percentEl) {
      percentEl.textContent = percent + '%';
    }

    const fileCountEl = progressSection.querySelector('#file-count');
    if (fileCountEl) {
      fileCountEl.textContent = `${Math.floor(current)}/${total}`;
    }
    updateFileCountColor(progressSection, Math.floor(current), total, false, 0);

    const statusEl = progressSection.querySelector('#progress-status');
    if (statusEl) {
      if (current === total) {
        statusEl.textContent = window.i18n?.uploadCompleted || 'Upload completed';
      } else if (current === 0) {
        statusEl.textContent = window.i18n?.preparingUpload || 'Preparing upload...';
      } else {
        statusEl.textContent = window.i18n?.uploadingFiles || 'Uploading files...';
      }
    }

    const totalSizeEl = progressSection.querySelector('#upload-total-size');
    if (totalSizeEl && totalBytes > 0) {
      totalSizeEl.textContent = formatBytes(totalBytes);
    }

    const uploadedEl = progressSection.querySelector('#upload-uploaded');
    if (uploadedEl) {
      uploadedEl.textContent = formatBytes(uploadedBytes);
    }

    const speedEl = progressSection.querySelector('#upload-speed');
    if (speedEl) {
      if (speed > 0) {
        speedEl.textContent = formatBytes(Math.round(speed)) + '/s';
      } else {
        speedEl.textContent = '0 B/s';
      }
    }

    const etaEl = progressSection.querySelector('#upload-eta');
    if (etaEl) {
      etaEl.textContent = formatUploadEta(etaSeconds, speed);
    }

    const elapsedEl = progressSection.querySelector('#upload-elapsed');
    if (elapsedEl && uploadState.startTime) {
      elapsedEl.textContent = formatTimeElapsed(Date.now() - uploadState.startTime);
    }

    const currentFileEl = progressSection.querySelector('#current-file');
    if (currentFileEl && currentFileName) {
      const maxLength = 40;
      let displayName = currentFileName;
      if (displayName.length > maxLength) {
        const ext = displayName.split('.').pop();
        const name = displayName.substring(0, displayName.lastIndexOf('.'));
        displayName = name.substring(0, maxLength - 3 - ext.length) + '….' + ext;
      }
      currentFileEl.textContent = displayName;
      currentFileEl.title = currentFileName;
    }
  }

  function resolveUploadPath(file, isFolderUpload, targetBasePath) {
    const relativePath = file.relativePath || file.webkitRelativePath || file.name;
    if (isFolderUpload && relativePath.includes('/')) {
      return targetBasePath ? `${targetBasePath}/${relativePath}` : relativePath;
    }
    return targetBasePath ? `${targetBasePath}/${file.name}` : file.name;
  }

  function splitPath(filePath) {
    const idx = filePath.lastIndexOf('/');
    if (idx === -1) return { dir: '', name: filePath };
    return {
      dir: filePath.substring(0, idx),
      name: filePath.substring(idx + 1)
    };
  }

  async function collectExistingUploads(files, isFolderUpload, targetBasePath) {
    const filesByDir = new Map();

    for (const file of files) {
      const fullPath = resolveUploadPath(file, isFolderUpload, targetBasePath);
      const { dir, name } = splitPath(fullPath);
      if (!filesByDir.has(dir)) {
        filesByDir.set(dir, new Set());
      }
      filesByDir.get(dir).add(name);
    }

    const conflicts = new Set();
    for (const [dir, names] of filesByDir.entries()) {
      try {
        const response = await api('list', { path: dir }, 'GET');
        const existingFiles = new Set(
          (response.items || [])
            .filter(item => item.type === 'file')
            .map(item => item.name)
        );

        names.forEach((name) => {
          if (existingFiles.has(name)) {
            conflicts.add(dir ? `${dir}/${name}` : name);
          }
        });
      } catch (err) {
        if (!String(err?.message || '').includes('not found')) {
          throw err;
        }
      }
    }

    return conflicts;
  }

  /**
   * Показать модальное окно подтверждения
   * @param {string} message - Сообщение для подтверждения
   * @param {string} title - Заголовок окна (опционально)
   * @returns {Promise<boolean>} - true если подтверждено, false если отменено
   */
  function showModalConfirm(message, title) {
    return new Promise((resolve) => {
      const modal = document.createElement('div');
      modal.className = 'modal';
      modal.style.display = 'flex';
      modal.innerHTML = `
        <div class="modal-content" style="max-width: 450px;">
          <div class="modal-header">
            <h3>
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="12" y1="8" x2="12" y2="12"></line>
                <line x1="12" y1="16" x2="12.01" y2="16"></line>
              </svg>
              ${title || (window.i18n?.confirm || 'Confirm')}
            </h3>
            <button class="icon-btn close-btn">
              <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-x-icon lucide-x"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
            </button>
          </div>
          <div class="modal-body">
            <p>${message}</p>
          </div>
          <div class="modal-footer">
            <button class="btn-cancel-confirm btn secondary">${window.i18n?.cancel || 'Cancel'}</button>
            <button class="btn-ok-confirm btn primary">${window.i18n?.ok || 'OK'}</button>
          </div>
        </div>
      `;

      document.body.appendChild(modal);

      modal.querySelector('.close-btn').addEventListener('click', () => {
        modal.remove();
        resolve(false);
      });

      modal.querySelector('.btn-cancel-confirm').addEventListener('click', () => {
        modal.remove();
        resolve(false);
      });

      modal.querySelector('.btn-ok-confirm').addEventListener('click', () => {
        modal.remove();
        resolve(true);
      });

      modal.addEventListener('click', (e) => {
        if (e.target === modal) {
          modal.remove();
          resolve(false);
        }
      });
    });
  }
  function showFolderConflictModal(conflicts) {
    return new Promise((resolve) => {
      const modal = document.createElement('div');
      const conflictList = Array.from(conflicts);
      const previewItems = conflictList.slice(0, 6)
        .map((path) => `<li style="margin-bottom:4px;word-break:break-word;">${path}</li>`)
        .join('');
      const hiddenCount = conflictList.length - 6;
      const hiddenText = hiddenCount > 0 ? `<div style="margin-top:8px;color:var(--muted);">+${hiddenCount} ${window.i18n?.moreFiles || 'more files'}</div>` : '';
      modal.className = 'modal';
      modal.style.display = 'flex';
      modal.innerHTML = `
        <div class="modal-content" style="max-width:560px;">
          <div class="modal-header">
            <h3>
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="12" y1="8" x2="12" y2="12"></line>
                <line x1="12" y1="16" x2="12.01" y2="16"></line>
              </svg>
              ${window.i18n?.fileConflictsFound || 'File conflicts found'}
            </h3>
            <button class="icon-btn close-btn">
              <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
            </button>
          </div>
          <div class="modal-body">
            <p>${(window.i18n?.folderUploadConflictMessage || 'Found {count} existing files in this folder upload. Choose action for all conflicts.').replace('{count}', String(conflictList.length))}</p>
            <div style="max-height:180px;overflow:auto;background:var(--panel);border:1px solid var(--border);border-radius:8px;padding:10px 12px;margin-top:10px;">
              <ul style="padding-left:18px;margin:0;">${previewItems}</ul>
              ${hiddenText}
            </div>
          </div>
          <div class="modal-footer">
            <button class="btn-cancel-folder-conflict btn secondary">${window.i18n?.cancel || 'Cancel'}</button>
            <button class="btn-skip-folder-conflict btn secondary">${window.i18n?.skipAll || 'Skip all'}</button>
            <button class="btn-overwrite-folder-conflict btn primary">${window.i18n?.overwriteAll || 'Overwrite all'}</button>
          </div>
        </div>
      `;
      document.body.appendChild(modal);
      const finalize = (action) => {
        modal.remove();
        resolve(action);
      };
      modal.querySelector('.close-btn').addEventListener('click', () => finalize('cancel'));
      modal.querySelector('.btn-cancel-folder-conflict').addEventListener('click', () => finalize('cancel'));
      modal.querySelector('.btn-skip-folder-conflict').addEventListener('click', () => finalize('skip_all'));
      modal.querySelector('.btn-overwrite-folder-conflict').addEventListener('click', () => finalize('overwrite_all'));
      modal.addEventListener('click', (e) => {
        if (e.target === modal) finalize('cancel');
      });
    });
  }

  async function askOverwriteDecisions(conflicts) {
    const decisions = new Map();
    for (const fullPath of conflicts) {
      const fileName = fullPath.split('/').pop() || fullPath;
      const messageTemplate = window.i18n?.uploadFileExistsPrompt ||
        'File "{name}" already exists. Overwrite it? Click Cancel to skip this file.';
      const message = messageTemplate
        .replace('{name}', fileName)
        .replace('{path}', fullPath);
      const overwrite = await showModalConfirm(message, window.i18n?.fileExists || 'File Exists');
      decisions.set(fullPath, overwrite ? 'overwrite' : 'skip');
    }
    return decisions;
  }

  async function performUpload(files, isFolderUpload) {
    uploadState.isUploading = true;
    uploadState.currentUploadController = new AbortController();

    // Показываем плавающую кнопку загрузки
    showFloatingUploadButton();

    // Добавляем элемент в панель прогресса
    const totalUploadId = addUploadItem(window.i18n?.uploadMultipleFiles || 'Multiple files', uploadState.totalBytes);

    uploadZone.classList.add('hidden');
    progressSection.classList.remove('hidden');
    cancelBtn.classList.remove('hidden');
    closeBtn.classList.add('hidden');

    let processedCount = 0;
    let successCount = 0;
    let skippedCount = 0;
    let errorCount = 0;
    const totalFiles = files.length;
    const treeOps = [];
    const pendingDirAdds = new Set();
    const pendingFileAdds = new Set();
    const showSpeedGraph = uploadState.totalBytes > SPEED_GRAPH_THRESHOLD_BYTES;

    const speedGraphContainer = progressSection.querySelector('.speed-graph-container');
    if (speedGraphContainer) {
      speedGraphContainer.classList.toggle('hidden', !showSpeedGraph);
    }

    let lastGraphUpdate = 0;
    let lastStatsUpdate = 0;
    let overwriteDecisions = new Map();

    try {
      const conflicts = await collectExistingUploads(files, isFolderUpload, basePath);
      if (conflicts.size > 0) {
        if (isFolderUpload) {
          const folderDecision = await showFolderConflictModal(conflicts);
          if (folderDecision === 'cancel') {
            uploadState.isUploading = false;
            if (uploadState.animationFrame) {
              cancelAnimationFrame(uploadState.animationFrame);
              uploadState.animationFrame = null;
            }
            cancelBtn.classList.add('hidden');
            closeBtn.classList.remove('hidden');
            return;
          }
          overwriteDecisions = new Map(Array.from(conflicts).map((path) => [path, folderDecision === 'overwrite_all' ? 'overwrite' : 'skip']));
        } else {
          overwriteDecisions = await askOverwriteDecisions(conflicts);
        }
      }
    } catch (err) {
      console.warn('Failed to pre-check existing files before upload:', err);
    }

    function animate() {
      if (!uploadState.isUploading) return;

      const now = Date.now();

      if (showSpeedGraph && now - lastGraphUpdate > 50) {
        const currentProgress = uploadState.totalBytes > 0 ? (uploadState.uploadedBytes / uploadState.totalBytes) * 100 : 0;
        drawSpeedGraph(progressSection, uploadState.speedHistoryValues, uploadState.peakSpeed, currentProgress);
        lastGraphUpdate = now;
      }

      if (now - lastStatsUpdate > 250) {
        const elapsedSeconds = (now - uploadState.startTime) / 1000;
        const currentUploaded = uploadState.uploadedBytes;

        const avgSpeed = elapsedSeconds > 0 ? currentUploaded / elapsedSeconds : 0;

        const remainingBytes = uploadState.totalBytes - currentUploaded;
        let eta = 0;
        let currentSpeed = 0;

        if (uploadState.speedBuffer.length > 0) {
          const smoothedSpeed = calculateSmoothedSpeed(uploadState.speedBuffer[uploadState.speedBuffer.length - 1]);
          currentSpeed = smoothedSpeed;
          eta = smoothedSpeed > 0 ? remainingBytes / smoothedSpeed : 0;
        }

        updateAllStats(
          progressSection,
          processedCount + (files[processedCount] ? (uploadState.uploadedBytes - files.slice(0, processedCount).reduce((sum, f) => sum + f.size, 0)) / files[processedCount].size : 0),
          totalFiles,
          files[processedCount]?.name || '',
          currentSpeed,
          avgSpeed,
          uploadState.peakSpeed,
          eta,
          uploadState.totalBytes,
          currentUploaded,
          now - uploadState.startTime
        );

        lastStatsUpdate = now;
      }

      uploadState.animationFrame = requestAnimationFrame(animate);
    }
    animate();

    for (let i = 0; i < files.length; i++) {
      if (uploadState.currentUploadController.signal.aborted) break;

      const file = files[i];
      const relativePath = file.relativePath || file.webkitRelativePath || file.name;

      try {
        let filePath = resolveUploadPath(file, isFolderUpload, basePath);
        if (isFolderUpload && relativePath.includes('/')) {
          const dirs = relativePath.split('/');
          dirs.pop();

          if (dirs.length > 0) {
            let currentPath = basePath || '';
            for (const dir of dirs) {
              currentPath = currentPath ? `${currentPath}/${dir}` : dir;
              try {
                await api('mkdir', { path: currentPath });
              } catch (err) {
                if (!err.message.includes('exists')) {
                  throw err;
                }
              }

              if (!pendingDirAdds.has(currentPath)) {
                treeOps.push({
                  op: 'add',
                  item: {
                    path: currentPath,
                    name: dir,
                    type: 'dir'
                  }
                });
                pendingDirAdds.add(currentPath);
              }
            }
          }
        }

        const fileDecision = overwriteDecisions.get(filePath);
        if (fileDecision === 'skip') {
          skippedCount++;
          processedCount++;
          uploadState.uploadedBytes = files.slice(0, i + 1).reduce((sum, f) => sum + f.size, 0);
          addUploadResult(
            resultsSection,
            file.name,
            true,
            window.i18n?.uploadSkippedDuplicate || 'Skipped (file already exists)'
          );
          continue;
        }

        await uploadSingleFile(
          filePath,
          file,
          uploadState.currentUploadController.signal,
          fileDecision === 'overwrite',
          (percent, filename, chunkUploadedBytes) => {
            const now = Date.now();

            const fileBytes = (file.size * percent / 100);
            uploadState.uploadedBytes = files.slice(0, i).reduce((sum, f) => sum + f.size, 0) + fileBytes;

            const timeDiff = (now - uploadState.lastUpdateTime) / 1000;
            const byteDiff = uploadState.uploadedBytes - uploadState.lastUploadedBytes;

            let currentSpeed = 0;
            if (timeDiff > 0.1 && byteDiff > 0) {
              currentSpeed = byteDiff / timeDiff;

              const smoothedSpeed = calculateSmoothedSpeed(currentSpeed);

              if (smoothedSpeed > uploadState.peakSpeed) {
                uploadState.peakSpeed = smoothedSpeed;
              }

              uploadState.speedHistoryValues.push(smoothedSpeed);
              if (uploadState.speedHistoryValues.length > uploadState.MAX_SPEED_POINTS) {
                uploadState.speedHistoryValues.shift();
              }
            }

            uploadState.lastUpdateTime = now;
            uploadState.lastUploadedBytes = uploadState.uploadedBytes;

            if (totalUploadId) {
              updateUploadProgress(
                totalUploadId,
                uploadState.uploadedBytes,
                currentSpeed > 0 ? currentSpeed : undefined
              );
            }
          }
        );

        if (!pendingFileAdds.has(filePath)) {
          treeOps.push({
            op: 'add',
            item: {
              path: filePath,
              name: file.name,
              type: 'file'
            }
          });
          pendingFileAdds.add(filePath);
        }

        successCount++;
        processedCount++;

      } catch (err) {
        if (err.name === 'AbortError') break;

        console.error(`Failed to upload ${file.name}:`, err);
        errorCount++;
        addUploadResult(resultsSection, file.name, false, err.message || 'Upload failed');
        processedCount++;
      }
    }

    if (uploadState.animationFrame) {
      cancelAnimationFrame(uploadState.animationFrame);
    }

    uploadState.isUploading = false;
    cancelBtn.classList.add('hidden');
    closeBtn.classList.remove('hidden');

    const now = Date.now();
    const elapsedSeconds = (now - uploadState.startTime) / 1000;
    const avgSpeed = elapsedSeconds > 0 ? uploadState.uploadedBytes / elapsedSeconds : 0;

    updateAllStats(
      progressSection,
      processedCount,
      totalFiles,
      '',
      0,
      avgSpeed,
      uploadState.peakSpeed,
      0,
      uploadState.totalBytes,
      uploadState.uploadedBytes,
      now - uploadState.startTime
    );

    const currentProgress = uploadState.totalBytes > 0 ? (uploadState.uploadedBytes / uploadState.totalBytes) * 100 : 100;
    if (showSpeedGraph) {
      drawSpeedGraph(progressSection, uploadState.speedHistoryValues, uploadState.peakSpeed, currentProgress);
    }

    updateFileCountColor(progressSection, processedCount, totalFiles, true, errorCount);

    if (treeOps.length > 0) {
      try {
        const curRoot = (typeof getCurrentCloudRoot === 'function') ? getCurrentCloudRoot() : 'main';
        if (curRoot === 'main') {
          if (typeof window.patchTreeCache === 'function') {
            await window.patchTreeCache(treeOps);
          } else {
            await api('patch_file_tree', { ops: JSON.stringify(treeOps) }, 'POST');
          }
          if (typeof window.treeNodeAdd === 'function') {
            for (const op of treeOps) {
              if (op && op.op === 'add' && op.item) {
                window.treeNodeAdd(op.item);
              }
            }
          }
        } else if (typeof window.insertTreeItems === 'function') {
          await window.insertTreeItems(treeOps.filter((op) => op?.op === 'add' && op.item).map((op) => op.item), curRoot);
        } else if (typeof window.buildTree === 'function') {
          await window.buildTree('');
        }
      } catch (err) {
        console.warn('Failed to patch file tree after upload:', err);
      }
    }

    showUploadResults(resultsSection, successCount, errorCount, totalFiles, skippedCount);

    // После завершения всех загрузок
    if (processedCount === totalFiles) {
      const uploadId = Array.from(uploadFloatingState.items.keys())[0];
      if (uploadId) {
        completeUpload(uploadId);
      }
    }

    // Если были ошибки, показываем статус
    if (errorCount > 0) {
      const uploadId = Array.from(uploadFloatingState.items.keys())[0];
      if (uploadId) {
        const item = uploadFloatingState.items.get(uploadId);
        if (item) {
          item.status = 'completed_with_errors';
          const itemElement = document.getElementById(`upload-item-${uploadId}`);
          if (itemElement) {
            const statusEl = itemElement.querySelector('.download-status');
            if (statusEl) {
              const template = window.i18n?.uploadCompletedWithErrors || 'Completed with {count} errors';
              statusEl.textContent = `${template.replace('{count}', String(errorCount))} ⚠️`;
            }
          }
        }
      }
    }

    if (!uploadState.currentUploadController.signal.aborted) {
      if (window.flashStatus) {
        window.flashStatus(window.i18n?.uploadCompleted || 'Upload completed');
      }
    }
  }

  // ============= ОБРАБОТЧИКИ СОБЫТИЙ =============

  const dropZone = modal.querySelector('#upload-drop-zone');
  if (dropZone) {
    dropZone.addEventListener('dragenter', (e) => {
      e.preventDefault();
      e.stopPropagation();
      dropZone.classList.add('drag-over');
    });
    dropZone.addEventListener('dragover', (e) => {
      e.preventDefault();
      e.stopPropagation();
      e.dataTransfer.dropEffect = 'copy';
      dropZone.classList.add('drag-over');
    });
    dropZone.addEventListener('dragleave', (e) => {
      if (!dropZone.contains(e.relatedTarget)) {
        dropZone.classList.remove('drag-over');
      }
    });
    dropZone.addEventListener('drop', async (e) => {
      e.preventDefault();
      e.stopPropagation();
      dropZone.classList.remove('drag-over');
      const items = e.dataTransfer.items;
      if (items && items.length > 0) {
        await processDroppedItems(items, basePath);
      } else if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
        startUpload(Array.from(e.dataTransfer.files), false);
      }
    });
  }

  browseFilesBtn.addEventListener('click', () => fileInput.click());
  browseFolderBtn.addEventListener('click', () => folderInput.click());

  fileInput.addEventListener('change', (e) => {
    if (e.target.files.length > 0) {
      startUpload(Array.from(e.target.files), false);
    }
    fileInput.value = '';
  });

  folderInput.addEventListener('change', (e) => {
    if (e.target.files.length > 0) {
      startUpload(Array.from(e.target.files), true);
    }
    folderInput.value = '';
  });

  modal.__cloudStartUpload = startUpload;

  cancelBtn.addEventListener('click', () => {
    if (uploadState.currentUploadController && uploadState.isUploading) {
      uploadState.currentUploadController.abort();

      // Отменяем в плавающей кнопке
      const uploadId = Array.from(uploadFloatingState.items.keys())[0];
      if (uploadId) {
        cancelUpload(uploadId);
      }
    }
    if (uploadState.animationFrame) {
      cancelAnimationFrame(uploadState.animationFrame);
    }
    modal.remove();
  });

  closeBtn.addEventListener('click', () => {
    if (uploadState.animationFrame) {
      cancelAnimationFrame(uploadState.animationFrame);
    }
    modal.remove();
  });

  const closeModalBtn = modal.querySelector('.close-btn');
  if (closeModalBtn) {
    closeModalBtn.addEventListener('click', () => {
      if (uploadState.isUploading && uploadState.currentUploadController) {
        uploadState.currentUploadController.abort();
      }
      if (uploadState.animationFrame) {
        cancelAnimationFrame(uploadState.animationFrame);
      }
      modal.remove();
    });
  }

  modal.addEventListener('click', async (e) => {
    if (e.target === modal) {
      if (uploadState.isUploading && uploadState.currentUploadController) {
        // Просто минимизируем окно вместо запроса подтверждения
        minimizeUploadModal();
      } else {
        // Если загрузка не идет, просто закрываем
        if (uploadState.animationFrame) {
          cancelAnimationFrame(uploadState.animationFrame);
        }
        modal.remove();
      }
    }
  });
}

/**
 * Загрузить один файл
 * @param {string} filePath - Путь для сохранения
 * @param {File} file - Файл
 * @param {AbortSignal} signal - Сигнал для отмены
 * @param {boolean} overwrite - Перезаписать существующий файл
 * @param {Function} onProgress - Колбэк прогресса
 */
async function uploadSingleFile(filePath, file, signal, overwrite, onProgress) {
  let uploadId = '';
  const apiUrl = new URL(window.APP_CONFIG.apiBase, window.location.origin).href;
  try {
    const initResponse = await fetch(apiUrl, {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
      body: new URLSearchParams({
        action: 'upload',
        init_upload: 'true',
        path: filePath,
        root: (typeof getCurrentCloudRoot === 'function') ? getCurrentCloudRoot() : 'main',
        filename: file.name,
        total_size: String(file.size)
      })
    });
    const initData = await initResponse.json();
    if (initResponse.ok && initData.success && initData.upload_id) {
      uploadId = initData.upload_id;
    } else {
      throw new Error(initData.error || 'Failed to initialize upload session');
    }
  } catch (err) {
    console.error('Upload initialization failed:', err);
    uploadId = '';
  }

  const CHUNK_SIZE = 1024 * 1024;
  const chunks = Math.ceil(file.size / CHUNK_SIZE);
  const extIdx = file.name.lastIndexOf('.');
  const transportName = extIdx > -1 && extIdx < file.name.length - 1 ? `upload.${file.name.slice(extIdx + 1).replace(/[^a-zA-Z0-9]/g, '').toLowerCase() || 'bin'}` : 'upload.bin';

  const uploadRootKey = (typeof getCurrentCloudRoot === 'function') ? getCurrentCloudRoot() : 'main';
  const parseUploadError = (xhr, fallbackStatus) => {
    const text = String(xhr.responseText || '').trim();
    if (!text) return `Upload failed: ${fallbackStatus}`;
    try {
      const data = JSON.parse(text);
      if (data && data.error) return `Upload failed: ${data.error}`;
    } catch (_) {}
    return `Upload failed: ${text}`;
  };
  if (chunks <= 1) {
    return new Promise((resolve, reject) => {
      const xhr = new XMLHttpRequest();
      const fd = new FormData();

      fd.append('action', 'upload');
      fd.append('path', filePath);
      fd.append('root', uploadRootKey);
      fd.append('file', file, transportName);
      if (uploadId) fd.append('upload_id', uploadId);
      if (overwrite) {
        fd.append('overwrite', '1');
      }

      xhr.upload.addEventListener('progress', (e) => {
        if (e.lengthComputable) {
          const percent = Math.round((e.loaded / e.total) * 100);
          onProgress(percent, file.name);
        }
      });

      xhr.addEventListener('load', () => {
        if (xhr.status >= 200 && xhr.status < 300) {
          try {
            const data = JSON.parse(xhr.responseText);
            if (data.error) {
              reject(new Error(data.error));
            } else {
              window.scheduleUserUsageStatusRefresh?.();
              resolve(data);
            }
          } catch (e) {
            reject(new Error('Invalid response'));
          }
        } else {
          reject(new Error(parseUploadError(xhr, xhr.status)));
        }
      });

      xhr.addEventListener('error', () => reject(new Error('Network error')));
      xhr.addEventListener('abort', () => reject(new Error('Upload aborted')));

      xhr.open('POST', apiUrl);
      xhr.withCredentials = true;
      if (signal) {
        signal.addEventListener('abort', () => xhr.abort());
      }
      xhr.send(fd);
    });
  }

  return new Promise((resolve, reject) => {
    let currentChunk = 0;

    const uploadNextChunk = () => {
      const start = currentChunk * CHUNK_SIZE;
      const end = Math.min(start + CHUNK_SIZE, file.size);
      const chunk = file.slice(start, end);

      const xhr = new XMLHttpRequest();
      const fd = new FormData();

      fd.append('action', 'upload');
      fd.append('path', filePath);
      fd.append('root', uploadRootKey);
      fd.append('file', chunk, transportName);
      if (uploadId) fd.append('upload_id', uploadId);
      fd.append('chunk', currentChunk);
      fd.append('chunks', chunks);
      if (overwrite) {
        fd.append('overwrite', '1');
      }

      xhr.upload.addEventListener('progress', (e) => {
        if (e.lengthComputable) {
          const loadedBytes = currentChunk * CHUNK_SIZE + e.loaded;
          const percent = Math.round((loadedBytes / file.size) * 100);
          onProgress(percent, file.name);
        }
      });

      xhr.addEventListener('load', () => {
        if (xhr.status >= 200 && xhr.status < 300) {
          try {
            const data = JSON.parse(xhr.responseText);
            if (data.error) {
              reject(new Error(data.error));
              return;
            }

            currentChunk++;

            if (currentChunk < chunks) {
              uploadNextChunk();
            } else {
              window.scheduleUserUsageStatusRefresh?.();
              resolve(data);
            }
          } catch (e) {
            reject(new Error('Invalid response'));
          }
        } else {
          reject(new Error(parseUploadError(xhr, xhr.status)));
        }
      });

      xhr.addEventListener('error', () => reject(new Error('Network error')));
      xhr.addEventListener('abort', () => reject(new Error('Upload aborted')));

      xhr.open('POST', apiUrl);
      xhr.withCredentials = true;
      if (signal) {
        signal.addEventListener('abort', () => xhr.abort());
      }
      xhr.send(fd);
    };

    uploadNextChunk();
  });
}

/**
 * Получить цвет из URL
 */
function getColorFromURL() {
  const urlParams = new URLSearchParams(window.location.search);
  const colorParam = urlParams.get('color');
  if (colorParam) {
    return colorParam.startsWith('#') ? colorParam : '#' + colorParam;
  }
  return window.APP_CONFIG?.color || '#85b503';
}

function uploadFilesToPath(files, basePath = '') {
  const list = Array.from(files || []).filter((f) => f && f.size >= 0);
  if (!list.length) return;
  showUploadModal(basePath || '');
  const modal = document.getElementById('upload-modal');
  const start = modal && modal.__cloudStartUpload;
  if (typeof start === 'function') start(list, false);
}

// Экспортируем функции в глобальную область
window.uploadModule = {
  openUploadDialog,
  showUploadModal,
  uploadFilesToPath,
  cleanupOldUploadSessions,
  uploadSingleFile,
  minimizeUploadModal,
  expandUploadModal,
  showFloatingUploadButton,
  hideFloatingUploadButton
};

// Для обратной совместимости
window.openUploadDialog = openUploadDialog;
window.uploadFilesToPath = uploadFilesToPath;
window.cleanupOldUploadSessions = cleanupOldUploadSessions;
