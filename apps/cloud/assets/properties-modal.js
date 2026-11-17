// assets/properties-modal.js
// Module for displaying file and folder properties with cancel and timeout support

let currentAbortController = null;
let loadingModalElement = null;

/**
  * Shows a properties modal with upload cancellation support
  * @param {string} filePath - Path to the file or folder
  * @param {string} fileType - Type ('file' or 'dir')
 */
async function showProperties(filePath, fileType, root = null) {
    if (!filePath) {
        flashStatus(t(window.i18n, 'selectItemFirst'));
        return;
    }

        // Cancel the previous request, if any
    if (currentAbortController) {
        currentAbortController.abort();
    }

        // Create a new controller for cancellation
    currentAbortController = new AbortController();
    const signal = currentAbortController.signal;

        // Show the loading modal with a cancel button
    const loadingModal = showLoadingModalWithCancel(
        t(window.i18n, 'loadingProperties'),
        () => {
            if (currentAbortController) {
                currentAbortController.abort();
                currentAbortController = null;
            }
            if (loadingModalElement) {
                loadingModalElement.remove();
                loadingModalElement = null;
            }
        }
    );
    loadingModalElement = loadingModal;

    try {
                // Set a 60 second timeout
        const timeoutId = setTimeout(() => {
            if (currentAbortController) {
                currentAbortController.abort();
                flashStatus(t(window.i18n, 'propertiesLoadTimeout'));
            }
        }, 60000);

        let properties;
        
        // Pass the item's root (group / hidden folder): without it the server
        // looked the file up in "main" and answered 404.
        const propsRoot = root || (typeof getCurrentCloudRoot === 'function' ? getCurrentCloudRoot() : 'main');
        if (fileType === 'dir') {
            properties = await apiWithAbort(
                'get_folder_properties', 
                { path: filePath, root: propsRoot }, 
                'GET', 
                signal
            );
        } else {
            properties = await apiWithAbort(
                'get_properties', 
                { path: filePath, root: propsRoot }, 
                'GET', 
                signal
            );
        }

        clearTimeout(timeoutId);
        
        if (loadingModalElement) {
            loadingModalElement.remove();
            loadingModalElement = null;
        }

        if (fileType === 'dir') {
            showFolderPropertiesModal(properties, filePath);
        } else {
            showFilePropertiesModal(properties, filePath);
        }

    } catch (err) {
        if (loadingModalElement) {
            loadingModalElement.remove();
            loadingModalElement = null;
        }

                // Ignore errors from cancelled requests
        if (err.name === 'AbortError' || err.message === 'Запрос отменен') {
            console.log('Properties loading cancelled');
            return;
        }

        console.error('Failed to get properties:', err);
        
                // Show an error modal
        showPropertiesErrorModal(err.message);
        
    } finally {
        currentAbortController = null;
    }
}

/**
  * API request with cancellation support
 */
async function apiWithAbort(action, params = {}, method = 'GET', signal) {
    const url = new URL(window.APP_CONFIG.apiBase, window.location.href);
    
    if (method === 'GET') {
        url.searchParams.set('action', action);
        url.searchParams.set('v', '1');
        Object.entries(params || {}).forEach(([k, v]) => {
            if (v !== undefined && v !== null) {
                url.searchParams.set(k, v);
            }
        });
        
        const res = await fetch(url, { signal });
        if (!res.ok) throw new Error(`API error: ${res.status} ${res.statusText}`);
        return res.headers.get('content-type')?.includes('application/json') ? res.json() : res.text();
    } else {
        const body = new URLSearchParams({ action, ...params });
        const res = await fetch(url, { 
            method: 'POST', 
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, 
            body,
            signal 
        });
        const data = await res.json();
        if (!res.ok || data.error) throw new Error(data.error || `API error: ${res.status} ${res.statusText}`);
        return data;
    }
}

/**
  * Shows a loading modal with a cancel button
 */
function showLoadingModalWithCancel(message, onCancel) {
        // Remove the previous modal, if any
    const existingModal = document.querySelector('.modal[data-loading-modal="true"]');
    if (existingModal) {
        existingModal.remove();
    }

    const modal = document.createElement('div');
    modal.className = 'modal';
    modal.style.display = 'flex';
    modal.setAttribute('data-loading-modal', 'true');
    
    modal.innerHTML = `
        <div class="modal-content" style="max-width: 400px; text-align: center;">
            <div class="modal-body" style="padding: 30px;">
                <div class="spinner" style="
                    width: 50px;
                    height: 50px;
                    margin: 0 auto 20px;
                    animation: spin 1s linear infinite;
                ">
                    <svg width="50" height="50" viewBox="0 0 50 50" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <circle cx="25" cy="25" r="22" stroke="var(--border)" stroke-width="5" opacity="0.3"/>
                        <path d="M25 3a22 22 0 0 1 22 22" stroke="var(--accent)" stroke-width="5" stroke-linecap="round" opacity="0.8"/>
                    </svg>
                </div>
                <div style="color: var(--fg); margin-bottom: 20px; font-size: 16px;">${message}</div>
                <div style="color: var(--muted); font-size: 13px; margin-bottom: 20px;">
                    ${t(window.i18n, 'thisMayTakeSomeTime')}
                </div>
                <button id="btn-cancel-properties-loading" class="btn secondary" style="min-width: 120px;">
                    <svg width="16" height="16" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none" style="vertical-align: middle; margin-right: 6px;">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                    ${t(window.i18n, 'cancel')}
                </button>
            </div>
        </div>
    `;

    document.body.appendChild(modal);

    const cancelBtn = document.getElementById('btn-cancel-properties-loading');
    cancelBtn.addEventListener('click', () => {
        if (onCancel) onCancel();
        modal.remove();
    });

        // Close on Escape
    const escapeHandler = (e) => {
        if (e.key === 'Escape') {
            if (onCancel) onCancel();
            modal.remove();
            document.removeEventListener('keydown', escapeHandler);
        }
    };
    document.addEventListener('keydown', escapeHandler);

    return modal;
}

/**
  * Shows an error modal for loading properties
 */
function showPropertiesErrorModal(errorMessage) {
    const modal = document.createElement('div');
    modal.className = 'modal';
    modal.style.display = 'flex';
    
    modal.innerHTML = `
        <div class="modal-content" style="max-width: 450px;">
            <div class="modal-header" style="border-bottom-color: #e74c3c;">
                <h3 style="color: #e74c3c; display: flex; align-items: center; gap: 10px;">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="8" x2="12" y2="12"></line>
                        <line x1="12" y1="16" x2="12.01" y2="16"></line>
                    </svg>
                    ${t(window.i18n, 'propertiesLoadError')}
                </h3>
                <button class="icon-btn close-btn" aria-label="${t(window.i18n, 'close') || 'Close'}">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                </button>
            </div>
            <div class="modal-body" style="padding: 25px; text-align: center;">
                <div style="font-size: 48px; margin-bottom: 20px; color: #e74c3c;">⚠️</div>
                <p style="margin-bottom: 15px; color: var(--fg);">${t(window.i18n, 'failedToLoadProperties')}</p>
                <div style="
                    background: var(--panel); 
                    padding: 12px; 
                    border-radius: 6px; 
                    color: var(--muted);
                    font-family: monospace;
                    font-size: 13px;
                    word-break: break-word;
                    margin-bottom: 10px;
                ">
                    ${escapeHtml(errorMessage)}
                </div>
                <p style="color: var(--muted); font-size: 13px; margin-top: 10px;">
                    ${t(window.i18n, 'propertiesErrorHelp')}
                </p>
            </div>
            <div class="modal-footer" style="justify-content: center;">
                <button id="btn-retry-properties" class="btn primary" style="min-width: 120px;">
                    <svg width="16" height="16" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none" style="vertical-align: middle; margin-right: 6px;">
                        <path d="M23 4v6h-6"></path>
                        <path d="M1 20v-6h6"></path>
                        <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10"></path>
                        <path d="M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path>
                    </svg>
                    ${t(window.i18n, 'retry')}
                </button>
                <button id="btn-close-properties-error" class="btn secondary">
                    ${t(window.i18n, 'close')}
                </button>
            </div>
        </div>
    `;

    document.body.appendChild(modal);

    const closeModal = () => modal.remove();
    
    modal.querySelector('.close-btn').addEventListener('click', closeModal);
    modal.querySelector('#btn-close-properties-error').addEventListener('click', closeModal);
    
        // Retry button
    const retryBtn = modal.querySelector('#btn-retry-properties');
    if (retryBtn) {
        retryBtn.addEventListener('click', () => {
            closeModal();
                        // Get the currently selected item from the context menu
            const contextMenu = document.getElementById('context-menu');
            if (contextMenu) {
                const items = contextMenu.querySelectorAll('.context-menu-item');
                                // Simulate a click on the 'Properties' item
                for (const item of items) {
                    if (item.textContent.includes(t(window.i18n, 'properties'))) {
                        item.click();
                        break;
                    }
                }
            }
        });
    }

    modal.addEventListener('click', (e) => {
        if (e.target === modal) closeModal();
    });

        // Close on Escape
    const escapeHandler = (e) => {
        if (e.key === 'Escape') {
            closeModal();
            document.removeEventListener('keydown', escapeHandler);
        }
    };
    document.addEventListener('keydown', escapeHandler);
}

/**
  * Escapes HTML for safe display
 */
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

/**
  * Shows the file properties modal
 */
function showFilePropertiesModal(properties, filePath) {
    const modal = document.createElement('div');
    modal.className = 'modal';
    modal.style.display = 'flex';
    
    const fileName = filePath.split('/').pop();
    const fileExt = fileName.split('.').pop().toLowerCase();
    const isImage = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'tiff'].includes(fileExt);
    
    modal.innerHTML = `
        <div class="modal-content properties-modal">
            <div class="modal-header">
                <h3>
                    <svg width="20" height="20" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; margin-right: 8px;">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                        <polyline points="14 2 14 8 20 8"></polyline>
                        <line x1="16" y1="13" x2="8" y2="13"></line>
                        <line x1="16" y1="17" x2="8" y2="17"></line>
                        <polyline points="10 9 9 9 8 9"></polyline>
                    </svg>
                    ${t(window.i18n, 'fileProperties')}: ${fileName}
                </h3>
                <button class="icon-btn close-btn" aria-label="${t(window.i18n, 'close') || 'Close'}">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                </button>
            </div>
            <div class="modal-body properties-body">
                <div class="properties-block">
                    <h4 style="margin-top: 0; margin-bottom: 15px; color: var(--accent);">
                        <svg width="16" height="16" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; margin-right: 8px;">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                            <polyline points="14 2 14 8 20 8"></polyline>
                            <line x1="16" y1="13" x2="8" y2="13"></line>
                            <line x1="16" y1="17" x2="8" y2="17"></line>
                            <polyline points="10 9 9 9 8 9"></polyline>
                        </svg>
                        ${t(window.i18n, 'generalProperties')}
                    </h4>
                    ${renderFileGeneralProperties(properties, filePath)}
                </div>
                
                ${isImage && properties.details ? `
                <div class="properties-block">
                    <h4 style="margin-top: 0; margin-bottom: 15px; color: var(--accent);">
                        <svg width="16" height="16" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; margin-right: 8px;">
                            <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                            <circle cx="8.5" cy="8.5" r="1.5"></circle>
                            <polyline points="21 15 16 10 5 21"></polyline>
                        </svg>
                        ${t(window.i18n, 'imageProperties')}
                    </h4>
                    ${renderImageDetails(properties.details)}
                </div>
                ` : ''}
                
                ${properties.security ? `
                <div class="properties-block">
                    <h4 style="margin-top: 0; margin-bottom: 15px; color: var(--accent);">
                        <svg width="16" height="16" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; margin-right: 8px;">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                        </svg>
                        ${t(window.i18n, 'security')}
                    </h4>
                    ${renderSecurityProperties(properties)}
                </div>
                ` : ''}
            </div>
            <div class="modal-footer">
                <button class="btn primary" id="btn-close-properties">${t(window.i18n, 'close')}</button>
            </div>
        </div>
    `;

    document.body.appendChild(modal);

    const closeModal = () => modal.remove();
    
    modal.querySelector('.close-btn').addEventListener('click', closeModal);
    modal.querySelector('#btn-close-properties').addEventListener('click', closeModal);
    modal.addEventListener('click', (e) => {
        if (e.target === modal) closeModal();
    });

        // Close on Escape
    const escapeHandler = (e) => {
        if (e.key === 'Escape') {
            closeModal();
            document.removeEventListener('keydown', escapeHandler);
        }
    };
    document.addEventListener('keydown', escapeHandler);
}

/**
  * Shows the folder properties modal
 */
function showFolderPropertiesModal(properties, folderPath) {
    const modal = document.createElement('div');
    modal.className = 'modal';
    modal.style.display = 'flex';
    
    const folderName = folderPath.split('/').pop() || '/';
    
    modal.innerHTML = `
        <div class="modal-content properties-modal">
            <div class="modal-header">
                <h3>
                    <svg width="20" height="20" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; margin-right: 8px;">
                        <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path>
                    </svg>
                    ${t(window.i18n, 'folderProperties')}: ${folderName}
                </h3>
                <button class="icon-btn close-btn" aria-label="${t(window.i18n, 'close') || 'Close'}">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                </button>
            </div>
            <div class="modal-body properties-body">
                <div class="properties-block">
                    <h4 style="margin-top: 0; margin-bottom: 15px; color: var(--accent);">
                        <svg width="16" height="16" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; margin-right: 8px;">
                            <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path>
                        </svg>
                        ${t(window.i18n, 'folderInformation')}
                    </h4>
                    
                    <div class="property-row">
                        <span class="property-label">${t(window.i18n, 'folderPath')}:</span>
                        <span class="property-value" title="${folderPath}">${folderPath || '/'}</span>
                    </div>
                    
                    <div class="property-row">
                        <span class="property-label">${t(window.i18n, 'totalSize')}:</span>
                        <span class="property-value">${formatFileSize(properties.size || 0)} (${(properties.size || 0).toLocaleString('en-US').replace(/,/g, ' ')} ${t(window.i18n, 'bytes')})</span>
                    </div>
                    
                    <div class="property-row">
                        <span class="property-label">${t(window.i18n, 'folderContents')}:</span>
                        <span class="property-value">
                            ${properties.items || 0} ${t(window.i18n, 'items')}
                        </span>
                    </div>
                    
                    <div class="property-row" style="margin-left: 20px;">
                        <span class="property-label">${t(window.i18n, 'files')}:</span>
                        <span class="property-value">${properties.files || 0}</span>
                    </div>
                    
                    <div class="property-row" style="margin-left: 20px;">
                        <span class="property-label">${t(window.i18n, 'subfolders')}:</span>
                        <span class="property-value">${properties.folders || 0}</span>
                    </div>
                    
                    <div class="property-row">
                        <span class="property-label">${t(window.i18n, 'createdDate')}:</span>
                        <span class="property-value">${formatDate(properties.created)}</span>
                    </div>
                    
                    <div class="property-row">
                        <span class="property-label">${t(window.i18n, 'modifiedDate')}:</span>
                        <span class="property-value">${formatDate(properties.modified)}</span>
                    </div>
                    
                    ${properties.attributes ? `
                    <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid var(--border);">
                        <h5 style="margin: 0 0 10px 0; color: var(--muted);">${t(window.i18n, 'folderAttributes')}</h5>
                        ${renderFolderAttributes(properties.attributes)}
                    </div>
                    ` : ''}
                    
                    ${properties.items_preview && properties.items_preview.length > 0 ? `
                    <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid var(--border);">
                        <h5 style="margin: 0 0 10px 0; color: var(--muted);">${t(window.i18n, 'contentPreview')} (${Math.min(properties.items_preview.length, 100)}/${properties.items})</h5>
                        <div style="max-height: 200px; overflow-y: auto; border: 1px solid var(--border); border-radius: 4px; padding: 8px;">
                            ${properties.items_preview.map(item => `
                                <div style="padding: 4px; font-size: 12px; display: flex; align-items: center; gap: 8px;">
                                    ${getFileIconForPreview(item)}
                                    <span style="flex: 1; color: var(--fg);" title="${item.name}">${item.name}</span>
                                    <span style="color: var(--muted); font-size: 11px;">${item.type === 'file' ? formatFileSize(item.size) : ''}</span>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                    ` : ''}
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn primary" id="btn-close-properties">${t(window.i18n, 'close')}</button>
            </div>
        </div>
    `;

    document.body.appendChild(modal);

    const closeModal = () => modal.remove();
    
    modal.querySelector('.close-btn').addEventListener('click', closeModal);
    modal.querySelector('#btn-close-properties').addEventListener('click', closeModal);
    modal.addEventListener('click', (e) => {
        if (e.target === modal) closeModal();
    });

        // Close on Escape
    const escapeHandler = (e) => {
        if (e.key === 'Escape') {
            closeModal();
            document.removeEventListener('keydown', escapeHandler);
        }
    };
    document.addEventListener('keydown', escapeHandler);
}

/**
  * Formats the file size
 */
function formatFileSize(bytes) {
    if (bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    const num = (bytes / Math.pow(k, i)).toFixed(2);
    // Use spaces as thousand separators for readability
    const spaced = Number(num).toLocaleString('en-US').replace(/,/g, ' ');
    return spaced + ' ' + sizes[i];
}

/**
  * Formats the date
 */
function formatDate(dateString) {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return date.toLocaleString();
}

/**
  * Returns an icon for the preview
 */
function getFileIconForPreview(item) {
    if (typeof window.getItemIconMarkup === 'function') {
        return window.getItemIconMarkup(item);
    }
    return '';
}

/**
  * Renders common file properties
 */
function renderFileGeneralProperties(properties, filePath) {
    return `
        <div class="property-row">
            <span class="property-label">
                <svg width="14" height="14" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                    <polyline points="14 2 14 8 20 8"></polyline>
                </svg>
                ${t(window.i18n, 'fileType')}:
            </span>
            <span class="property-value">${properties.type || 'Unknown'}</span>
        </div>
        <div class="property-row">
            <span class="property-label">
                <svg width="14" height="14" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none">
                    <path d="M3 7v13a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-6l-2-2H5a2 2 0 0 0-2 2z"></path>
                </svg>
                ${t(window.i18n, 'filePath')}:
            </span>
            <span class="property-value" title="${filePath}">${filePath}</span>
        </div>
        <div class="property-row">
            <span class="property-label">
                <svg width="14" height="14" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none">
                    <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
                </svg>
                ${t(window.i18n, 'fileSize')}:
            </span>
            <span class="property-value">${formatFileSize(properties.size)} (${(properties.size || 0).toLocaleString('en-US').replace(/,/g, ' ')} ${t(window.i18n, 'bytes')})</span>
        </div>
        ${properties.created ? `
        <div class="property-row">
            <span class="property-label">
                <svg width="14" height="14" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none">
                    <circle cx="12" cy="12" r="10"></circle>
                    <polyline points="12 6 12 12 16 14"></polyline>
                </svg>
                ${t(window.i18n, 'createdDate')}:
            </span>
            <span class="property-value">${formatDate(properties.created)}</span>
        </div>
        ` : ''}
        ${properties.modified ? `
        <div class="property-row">
            <span class="property-label">
                <svg width="14" height="14" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none">
                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                </svg>
                ${t(window.i18n, 'modifiedDate')}:
            </span>
            <span class="property-value">${formatDate(properties.modified)}</span>
        </div>
        ` : ''}
        ${properties.accessed ? `
        <div class="property-row">
            <span class="property-label">
                <svg width="14" height="14" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none">
                    <circle cx="12" cy="12" r="10"></circle>
                    <polyline points="12 6 12 12 16 14"></polyline>
                </svg>
                ${t(window.i18n, 'accessedDate')}:
            </span>
            <span class="property-value">${formatDate(properties.accessed)}</span>
        </div>
        ` : ''}
        ${properties.attributes ? `
        <div class="property-row-section attributes-section">
            <div style="margin-top: 10px;border-top: 2px solid var(--border);padding-top:10px;"></div>
            <div class="property-section-header">
                <svg width="16" height="16" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none">
                    <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path>
                </svg>
                ${t(window.i18n, 'fileAttributes')}
            </div>
            <div style="display:flex;flex-direction:column;gap:4px;">
                ${renderAttributes(properties.attributes)}
            </div>
        </div>
        ` : ''}
    `;
}

/**
  * Renders image details
 */
function renderImageDetails(details) {
    let detailsHTML = '';

    // Dimensions
    if (details.width && details.height) {
        detailsHTML += `
            <div class="property-row">
                <span class="property-label">
                    <svg width="14" height="14" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none">
                        <rect x="3" y="3" width="18" height="18" rx="2"></rect>
                        <circle cx="8.5" cy="8.5" r="1.5"></circle>
                        <polyline points="21 15 16 10 5 21"></polyline>
                    </svg>
                    ${t(window.i18n, 'dimensions')}:
                </span>
                <span class="property-value">${details.width} × ${details.height} ${t(window.i18n, 'pixels')}</span>
            </div>
        `;
    }

    // Resolution
    if (details.resolution) {
        detailsHTML += `
            <div class="property-row">
                <span class="property-label">
                    <svg width="14" height="14" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none">
                        <rect x="2" y="2" width="20" height="20"></rect>
                        <path d="M4 20V10"></path>
                        <path d="M5 8h16"></path>
                    </svg>
                    ${t(window.i18n, 'resolution')}:
                </span>
                <span class="property-value">${details.resolution.horizontal} × ${details.resolution.vertical} DPI</span>
            </div>
        `;
    }

    // Bit Depth
    if (details.bitDepth) {
        detailsHTML += `
            <div class="property-row">
                <span class="property-label">
                    <svg width="14" height="14" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none">
                        <rect x="2" y="3" width="20" height="18" rx="2" ry="2"></rect>
                        <rect x="7" y="7" width="3" height="3"></rect>
                        <rect x="14" y="7" width="3" height="3"></rect>
                        <rect x="7" y="14" width="3" height="3"></rect>
                    </svg>
                    ${t(window.i18n, 'bitDepth')}:
                </span>
                <span class="property-value">${details.bitDepth} bit</span>
            </div>
        `;
    }

    // Compression
    if (details.compression) {
        detailsHTML += `
            <div class="property-row">
                <span class="property-label">
                    <svg width="14" height="14" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none">
                        <polyline points="4 14 10 14 10 20"></polyline>
                        <polyline points="20 10 14 10 14 4"></polyline>
                        <line x1="14" y1="10" x2="21" y2="3"></line>
                        <line x1="3" y1="21" x2="10" y2="14"></line>
                    </svg>
                    ${t(window.i18n, 'compression')}:
                </span>
                <span class="property-value">${details.compression}</span>
            </div>
        `;
    }

        // Camera properties
        const cameraProps = [];
        cameraProps.push(`<div class="property-row"><span class="property-label"><svg width="14" height="14" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none"><rect x="4" y="2" width="16" height="20" rx="2"/><circle cx="12" cy="14" r="3"/><line x1="12" y1="2" x2="12" y2="4"/></svg> ${t(window.i18n, 'cameraModel')}:</span><span class="property-value">${details.cameraModel}</span></div>`);
    if (details.exposure) cameraProps.push(`<div class="property-row"><span class="property-label">${t(window.i18n, 'exposure')}:</span><span class="property-value">${details.exposure}</span></div>`);
    if (details.focalLength) cameraProps.push(`<div class="property-row"><span class="property-label">${t(window.i18n, 'focalLength')}:</span><span class="property-value">${details.focalLength}</span></div>`);
    if (details.aperture) cameraProps.push(`<div class="property-row"><span class="property-label">${t(window.i18n, 'aperture')}:</span><span class="property-value">${details.aperture}</span></div>`);
    if (details.iso) cameraProps.push(`<div class="property-row"><span class="property-label">${t(window.i18n, 'iso')}:</span><span class="property-value">${details.iso}</span></div>`);
    
    if (cameraProps.length > 0) {
        detailsHTML += `
            <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid var(--border);">
                <h5 style="margin: 0 0 10px 0; color: var(--muted);">${t(window.i18n, 'cameraProperties')}</h5>
                ${cameraProps.join('')}
            </div>
        `;
    }
    
    if (details.allExifTags && typeof details.allExifTags === 'object') {
        const allTags = [];
        Object.entries(details.allExifTags).forEach(([key, value]) => {
            if (value && typeof value !== 'object') {
                allTags.push(`
                    <div class="property-row">
                        <span class="property-label" title="${key}">${formatExifKey(key)}:</span>
                        <span class="property-value">${String(value)}</span>
                    </div>
                `);
            }
        });
        
        if (allTags.length > 0) {
            detailsHTML += `
                <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid var(--border);">
                    <h5 style="margin: 0 0 10px 0; color: var(--muted);">${t(window.i18n, 'allMetadata')}</h5>
                    <div style="max-height: 200px; overflow-y: auto; border: 1px solid var(--border); border-radius: 4px; padding: 10px;">
                        ${allTags.join('')}
                    </div>
                </div>
            `;
        }
    }
    
    if (detailsHTML === '') {
        detailsHTML = `<div class="property-value" style="color: var(--muted);">${t(window.i18n, 'noImageMetadata')}</div>`;
    }
    
    return detailsHTML;
}

/**
  * Formats the EXIF key
 */
function formatExifKey(key) {
    const parts = key.split(':');
    const cleanKey = parts.length > 1 ? parts[1] : parts[0];
    return cleanKey
        .replace(/([A-Z])/g, ' $1')
        .replace(/^./, str => str.toUpperCase())
        .trim();
}

/**
  * Renders security properties
 */
function renderSecurityProperties(properties) {
    if (!properties.security) return '';
    
    return `
        ${properties.security.owner ? `
        <div class="property-row">
            <span class="property-label">${t(window.i18n, 'owner')}:</span>
            <span class="property-value">${properties.security.owner}</span>
        </div>
        ` : ''}
        ${properties.security.permissions ? `
        <div class="property-row">
            <span class="property-label">${t(window.i18n, 'permissions')}:</span>
            <span class="property-value">${properties.security.permissions}</span>
        </div>
        ` : ''}
    `;
}

/**
  * Renders attributes
 */
function renderAttributes(attributes) {
    const attrColors = {
        readOnly: '#e74c3c',
        hidden: '#f39c12',
        system: '#8e44ad',
        archive: '#3498db'
    };
    const attrIcons = {
        readOnly: '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 9.9-1"></path></svg>',
        hidden: '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>',
        system: '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect><line x1="8" y1="21" x2="16" y2="21"></line><line x1="12" y1="17" x2="12" y2="21"></line></svg>',
        archive: '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="21 8 21 21 3 21 3 8"></polyline><rect x="1" y="3" width="22" height="5"></rect><line x1="10" y1="12" x2="14" y2="12"></line></svg>'
    };
    
    const attrList = [];
    if (attributes.readOnly) attrList.push(`<div class="property-list-item" style="color:${attrColors.readOnly}">
        ${attrIcons.readOnly} ${t(window.i18n, 'readOnly')}
    </div>`);
    if (attributes.hidden) attrList.push(`<div class="property-list-item" style="color:${attrColors.hidden}">
        ${attrIcons.hidden} ${t(window.i18n, 'hidden')}
    </div>`);
    if (attributes.system) attrList.push(`<div class="property-list-item" style="color:${attrColors.system}">
        ${attrIcons.system} ${t(window.i18n, 'system')}
    </div>`);
    if (attributes.archive) attrList.push(`<div class="property-list-item" style="color:${attrColors.archive}">
        ${attrIcons.archive} ${t(window.i18n, 'archive')}
    </div>`);
    
    return attrList.length > 0 ? 
        `<div style="display:flex;flex-direction:column;gap:2px;">${attrList.join('')}</div>` :
        `<div class="property-value italic-gray">${t(window.i18n, 'noAttributes')}</div>`;
}

/**
  * Renders folder attributes
 */
function renderFolderAttributes(attributes) {
    return renderAttributes(attributes);
}

// Export the function for use in other files
window.showProperties = showProperties;
