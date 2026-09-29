// advanced-search.js
// Module for advanced file and folder search with a visual tree

// Advanced search state
let advancedSearchState = {
    active: false,
    query: '',
    results: [],
    isSearching: false,
    abortController: null,
    processedItems: 0,
    totalItems: 0,
    searchFolder: '',
    searchRoot: '',
    searchTargets: [{ folder: '', root: 'all' }],
    textMode: 'name',
    filters: {
        name: '',
        extension: '',
        type: 'all',
        size: {
            operator: 'any',
            value: null,
            min: null,
            max: null,
            unit: 'MB'
        },
        date: {
            operator: 'any',
            value: null,
            start: null,
            end: null
        }
    }
};

// Cache for the folder tree
let folderTreeCache = null;
let extensionSuggestionsInitialized = false;

const COMMON_FILE_EXTENSIONS = [
    'txt', 'md', 'rtf', 'pdf', 'doc', 'docx', 'odt',
    'xls', 'xlsx', 'csv', 'ppt', 'pptx',
    'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp', 'ico',
    'mp3', 'wav', 'ogg', 'flac', 'm4a', 'aac',
    'mp4', 'avi', 'mkv', 'mov', 'wmv', 'webm',
    'zip', 'rar', '7z', 'tar', 'gz',
    'js', 'ts', 'jsx', 'tsx', 'css', 'scss', 'html', 'php', 'py', 'java', 'c', 'cpp', 'h', 'cs', 'go', 'rb', 'rs', 'swift', 'kt', 'sql', 'json', 'xml', 'yml', 'yaml', 'sh', 'ps1',
    'tex', 'latex', 'log', 'ini', 'env'
];

const extensionSuggestState = {
    activeIndex: -1,
    items: []
};

function normalizeExtension(input) {
    return String(input || '').trim().toLowerCase().replace(/^\.+/, '');
}

function hideExtensionSuggestions() {
    const list = document.getElementById('search-extension-suggestions');
    if (!list) return;
    list.classList.add('hidden');
    list.classList.remove('visible');
    list.innerHTML = '';
    extensionSuggestState.activeIndex = -1;
    extensionSuggestState.items = [];
}

function applyExtensionSuggestion(ext) {
    const input = document.getElementById('search-extension');
    if (!input) return;
    input.value = `.${ext}`;
    input.focus();
    hideExtensionSuggestions();
}

function renderExtensionSuggestions(rawValue) {
    const list = document.getElementById('search-extension-suggestions');
    if (!list) return;

    const normalized = normalizeExtension(rawValue);
    const filtered = COMMON_FILE_EXTENSIONS
        .filter((ext) => !normalized || ext.includes(normalized))
        .slice(0, 10);

    extensionSuggestState.items = filtered;
    extensionSuggestState.activeIndex = filtered.length ? 0 : -1;

    if (!filtered.length) {
        hideExtensionSuggestions();
        return;
    }

    list.innerHTML = filtered.map((ext, index) => {
        const icon = typeof window.getItemIconMarkup === 'function'
            ? window.getItemIconMarkup({ name: `file.${ext}`, path: `file.${ext}`, type: 'file' })
            : '';
        return `
        <div class="search-extension-suggestion${index === extensionSuggestState.activeIndex ? ' active' : ''}" data-ext="${ext}" role="option" aria-selected="${index === extensionSuggestState.activeIndex ? 'true' : 'false'}">
            ${icon}
            <span class="search-extension-dot">.${ext}</span>
        </div>
    `;
    }).join('');

    positionExtensionSuggestions();

    list.classList.remove('hidden');
    list.classList.add('visible');
    list.style.zIndex = '1000001';
}

function positionExtensionSuggestions() {
    const input = document.getElementById('search-extension');
    const list = document.getElementById('search-extension-suggestions');
    if (!input || !list) return;

    list.style.left = '0';
    list.style.top = 'calc(100% + 6px)';
    list.style.width = '100%';
}

let scrollHandlerAttached = false;

function attachScrollHandler() {
    if (scrollHandlerAttached) return;
    const modalBody = document.querySelector('#advanced-search-modal .modal-body');
    if (modalBody) {
        modalBody.addEventListener('scroll', positionExtensionSuggestions, { passive: true });
        scrollHandlerAttached = true;
    }
}

function moveExtensionSuggestionSelection(delta) {
    const list = document.getElementById('search-extension-suggestions');
    if (!list || list.classList.contains('hidden') || extensionSuggestState.items.length === 0) return;

    const total = extensionSuggestState.items.length;
    extensionSuggestState.activeIndex = (extensionSuggestState.activeIndex + delta + total) % total;

    const options = list.querySelectorAll('.search-extension-suggestion');
    options.forEach((el, index) => {
        const active = index === extensionSuggestState.activeIndex;
        el.classList.toggle('active', active);
        el.setAttribute('aria-selected', active ? 'true' : 'false');
    });
}

function initExtensionSuggestions() {
    if (extensionSuggestionsInitialized) return;

    const input = document.getElementById('search-extension');
    const list = document.getElementById('search-extension-suggestions');
    if (!input || !list) return;

    // Attach scroll handler for modal body to reposition dropdown on scroll
    attachScrollHandler();

    // Also handle window resize
    window.addEventListener('resize', positionExtensionSuggestions, { passive: true });

    input.addEventListener('input', () => {
        renderExtensionSuggestions(input.value);
    });

    input.addEventListener('focus', () => {
        renderExtensionSuggestions(input.value);
    });

    input.addEventListener('keydown', (e) => {
        if (list.classList.contains('hidden')) return;
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            moveExtensionSuggestionSelection(1);
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            moveExtensionSuggestionSelection(-1);
        } else if (e.key === 'Enter') {
            e.preventDefault();
            const ext = extensionSuggestState.items[extensionSuggestState.activeIndex];
            if (ext) applyExtensionSuggestion(ext);
        } else if (e.key === 'Escape') {
            e.preventDefault();
            hideExtensionSuggestions();
        }
    });

    list.addEventListener('mousedown', (e) => {
        const option = e.target.closest('.search-extension-suggestion');
        if (!option) return;
        e.preventDefault();
        const ext = option.dataset.ext;
        if (ext) applyExtensionSuggestion(ext);
    });

    document.addEventListener('click', (e) => {
        if (!e.target.closest('.search-extension-autocomplete')) {
            hideExtensionSuggestions();
        }
    });

    extensionSuggestionsInitialized = true;
}

// Initialize the advanced search
window.initAdvancedSearch = function() {
    const btnAdvancedSearch = document.getElementById('btn-advanced-search');
    const modal = document.getElementById('advanced-search-modal');
    const closeBtn = modal.querySelector('.close-btn');
    const cancelBtn = document.getElementById('btn-cancel-advanced-search');
    const resetBtn = document.getElementById('btn-reset-advanced-search');
    const startBtn = document.getElementById('btn-start-advanced-search');
    const sizeOperator = document.getElementById('search-size-operator');
    const dateOperator = document.getElementById('search-date-operator');
    initExtensionSuggestions();
    initAdvancedSearchModeControls();

    // Open the modal
    btnAdvancedSearch.addEventListener('click', async () => {
        modal.classList.remove('hidden');
        await loadFoldersForAdvancedSearch();
        resetAdvancedSearchForm();
    });

    // Close the modal
    const closeModal = () => {
        if (advancedSearchState.isSearching) {
            advancedSearchState.abortController?.abort();
        }
        hideExtensionSuggestions();
        modal.classList.add('hidden');
        resetAdvancedSearchForm();
    };

    closeBtn.addEventListener('click', closeModal);
    cancelBtn.addEventListener('click', closeModal);
    modal.addEventListener('click', (e) => {
        if (e.target === modal) closeModal();
    });

    // Reset the form
    resetBtn.addEventListener('click', resetAdvancedSearchForm);

    // Handlers for size conditions
    sizeOperator.addEventListener('change', toggleSizeInputs);
    dateOperator.addEventListener('change', toggleDateInputs);

    // Run the search
    startBtn.addEventListener('click', startAdvancedSearch);
}

function initAdvancedSearchModeControls() {
    document.querySelectorAll('#search-text-mode button').forEach((btn) => {
        btn.addEventListener('click', () => setAdvancedTextMode(btn.dataset.mode || 'name'));
    });
    document.querySelectorAll('#search-type-mode button').forEach((btn) => {
        btn.addEventListener('click', () => {
            const value = btn.dataset.type || 'all';
            document.getElementById('search-type').value = value;
            document.querySelectorAll('#search-type-mode button').forEach((el) => el.classList.toggle('active', el === btn));
        });
    });
}

function setAdvancedTextMode(mode) {
    advancedSearchState.textMode = mode === 'extension' ? 'extension' : 'name';
    document.querySelectorAll('#search-text-mode button').forEach((btn) => {
        btn.classList.toggle('active', btn.dataset.mode === advancedSearchState.textMode);
    });
    document.querySelectorAll('[data-search-panel]').forEach((panel) => {
        panel.classList.toggle('hidden', panel.dataset.searchPanel !== advancedSearchState.textMode);
    });
    if (advancedSearchState.textMode === 'name') {
        document.getElementById('search-extension').value = '';
        hideExtensionSuggestions();
    } else {
        document.getElementById('search-name').value = '';
    }
}

// Load folders with a visual tree
async function loadFoldersForAdvancedSearch() {
    const treeContainer = document.getElementById('search-folder-tree-container');
    if (!treeContainer) return;

    // Show loading
    treeContainer.innerHTML = `
        <div class="folder-tree-loading" style="padding: 20px; text-align: center; color: var(--muted);">
            <div class="spinner" style="
                width: 30px;
                height: 30px;
                margin: 0 auto 10px;
                animation: spin 1s linear infinite;
            ">
                <svg width="30" height="30" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="20" cy="20" r="18" stroke="var(--border)" stroke-width="4" opacity="0.3"/>
                    <path d="M20 2a18 18 0 0 1 18 18" stroke="var(--accent)" stroke-width="4" stroke-linecap="round" opacity="0.8"/>
                </svg>
            </div>
            <div>${t(i18n, 'loadingFolders') || 'Loading folders...'}</div>
        </div>
    `;

    try {
        // Get the root elements
        const rootItems = await api('list', { path: '' }, 'GET');
        const folders = rootItems.items.filter(item => item.type === 'dir');
        
        treeContainer.innerHTML = '';
        
        if (folders.length === 0) {
            treeContainer.innerHTML = `
                <div style="padding: 20px; text-align: center; color: var(--muted);">
                    ${t(i18n, 'noFoldersFound') || 'No folders found'}
                </div>
            `;
            return;
        }

        const rootNode = createFolderNode({ path: '', name: t(i18n, 'allTypes') || 'All locations', type: 'dir', root: 'all' }, 0, true);
        treeContainer.appendChild(rootNode);
        const foldersContainer = renderSearchFolderNodes(folders, '', 0);
        treeContainer.appendChild(foldersContainer);
        const specialRootDefs = [
            { root: 'archive', labelKey: 'archiveRoot', svgPath: '<rect width="20" height="5" x="2" y="3" rx="1"/><path d="M4 8v11a2 2 0 0 0 2 2h2"/><path d="M20 8v11a2 2 0 0 1-2 2h-2"/><path d="m9 15 3-3 3 3"/><path d="M12 12v9"/>' },
            { root: 'temp', labelKey: 'tempFilesRoot', svgPath: '<path d="M21 5H3"/><path d="M7 12H3"/><path d="M7 19H3"/><path d="M12 18a5 5 0 0 0 9-3 4.5 4.5 0 0 0-4.5-4.5c-1.33 0-2.54.54-3.41 1.41L11 14"/><path d="M11 10v4h4"/>' },
            { root: 'documents', labelKey: 'documentsRoot', svgPath: '<path d="M10 2v8l3-3 3 3V2"/><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H19a1 1 0 0 1 1 1v18a1 1 0 0 1-1 1H6.5a1 1 0 0 1 0-5H20"/>' },
            { root: 'photos', labelKey: 'photosRoot', svgPath: '<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/>' },
            { root: 'pdf', labelKey: 'pdfRoot', svgPath: '<path d="M12 7v14"/><path d="M16 12h2"/><path d="M16 8h2"/><path d="M3 18a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1h5a4 4 0 0 1 4 4 4 4 0 0 1 4-4h5a1 1 0 0 1 1 1v13a1 1 0 0 1-1 1h-6a3 3 0 0 0-3 3 3 3 0 0 0-3-3z"/><path d="M6 12h2"/><path d="M6 8h2"/>' },
            { root: 'trash', labelKey: 'trashRoot', svgPath: '<path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M10 11v6"/><path d="M14 11v6"/>' },
        ];
        const sep = document.createElement('div');
        sep.style.cssText = 'margin: 8px 0 4px; padding: 0 8px; font-size: 11px; text-transform: uppercase; color: var(--muted); letter-spacing: 0.05em;';
        sep.textContent = t(i18n, 'specialFolders') || 'Special folders';
        treeContainer.appendChild(sep);
        specialRootDefs.forEach(def => {
            const node = document.createElement('div');
            node.className = 'search-folder-node';
            node.dataset.path = '';
            node.dataset.root = def.root;
            node.dataset.type = 'dir';
            const nodeContent = document.createElement('div');
            nodeContent.className = 'search-folder-content';
            nodeContent.style.cssText = 'display:flex;align-items:center;padding:6px 8px;border-radius:4px;cursor:pointer;transition:background 0.2s;';
            const icon = document.createElement('span');
            icon.style.cssText = 'margin-right:8px;display:inline-flex;align-items:center;flex-shrink:0;';
            icon.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">${def.svgPath}</svg>`;
            const label = document.createElement('span');
            label.className = 'folder-label';
            label.textContent = t(i18n, def.labelKey) || def.root;
            label.style.cssText = 'flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;';
            const checkmark = document.createElement('span');
            checkmark.className = 'folder-checkmark';
            checkmark.style.cssText = 'width:20px;height:20px;border-radius:50%;background:transparent;border:2px solid var(--border);margin-left:8px;display:flex;align-items:center;justify-content:center;transition:all 0.2s;';
            checkmark.innerHTML = '<svg width="12" height="12" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3" fill="none" style="display:none;"><polyline points="20 6 9 17 4 12"></polyline></svg>';
            nodeContent.appendChild(icon);
            nodeContent.appendChild(label);
            nodeContent.appendChild(checkmark);
            nodeContent.addEventListener('click', (e) => {
                e.stopPropagation();
                selectFolderForSearch('', def.root);
            });
            node.appendChild(nodeContent);
            treeContainer.appendChild(node);
        });
        selectFolderForSearch('', 'all', true);

    } catch (err) {
        console.error('Failed to load folders:', err);
        treeContainer.innerHTML = `
            <div style="padding: 20px; text-align: center; color: #e74c3c;">
                ${t(i18n, 'loadError', { message: err.message }) || 'Error loading folders'}
            </div>
        `;
    }
}

// Create a folder node for the tree
function createFolderNode(item, depth = 0, isRoot = false) {
    const node = document.createElement('div');
    node.className = 'search-folder-node';
    if (isRoot) {
        node.dataset.path = '';
        node.dataset.root = item.root || 'all';
        node.dataset.type = 'dir';
    } else {
        node.dataset.path = item.path;
        node.dataset.type = item.type;
    }
    node.style.marginLeft = isRoot ? '0' : `${depth * 20}px`;

    const nodeContent = document.createElement('div');
    nodeContent.className = 'search-folder-content';
    nodeContent.style.cssText = `
        display: flex;
        align-items: center;
        padding: 6px 8px;
        border-radius: 4px;
        cursor: pointer;
        transition: background 0.2s;
    `;

    // Folder icon
    const icon = document.createElement('span');
    icon.className = 'folder-icon';
    icon.innerHTML = typeof window.getFolderIconMarkup === 'function' ? window.getFolderIconMarkup() : '';
    icon.style.marginRight = '8px';

    // Folder name
    const label = document.createElement('span');
    label.className = 'folder-label';
    label.textContent = isRoot ? item.name : item.name;
    label.style.flex = '1';
    label.style.overflow = 'hidden';
    label.style.textOverflow = 'ellipsis';
    label.style.whiteSpace = 'nowrap';

    // Selection indicator
    const checkmark = document.createElement('span');
    checkmark.className = 'folder-checkmark';
    checkmark.style.cssText = `
        width: 20px;
        height: 20px;
        border-radius: 50%;
        background: transparent;
        border: 2px solid var(--border);
        margin-left: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s;
    `;
    checkmark.innerHTML = `
        <svg width="12" height="12" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3" fill="none" style="display: none;">
            <polyline points="20 6 9 17 4 12"></polyline>
        </svg>
    `;

    nodeContent.appendChild(icon);
    nodeContent.appendChild(label);
    nodeContent.appendChild(checkmark);

    // Folder selection handler
    nodeContent.addEventListener('click', (e) => {
        e.stopPropagation();
        selectFolderForSearch(node.dataset.path, node.dataset.root || '', false);
    });

    // Double click handler to expand/collapse
    nodeContent.addEventListener('dblclick', async (e) => {
        e.stopPropagation();
        if (node.dataset.type === 'dir') {
            await toggleFolder(node, item);
        }
    });

    node.appendChild(nodeContent);

    // Container for child elements
    const childrenContainer = document.createElement('div');
    childrenContainer.className = 'search-folder-children';
    childrenContainer.style.display = 'none';
    childrenContainer.dataset.loaded = 'false';
    node.appendChild(childrenContainer);

    return node;
}

// Render folder nodes for search
function renderSearchFolderNodes(items, basePath = '', depth = 0) {
    const container = document.createElement('div');
    container.className = 'search-folder-tree-container';
    
    items.forEach(item => {
        const node = createFolderNode(item, depth);
        
        // Add an expand arrow if there is a folder
        const toggle = document.createElement('span');
        toggle.className = 'folder-toggle';
        toggle.innerHTML = `
            <svg width="12" height="12" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none">
                <polyline points="6 9 12 15 18 9"></polyline>
            </svg>
        `;
        toggle.style.cssText = `
            cursor: pointer;
            margin-right: 4px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 20px;
            height: 20px;
            transition: transform 0.2s;
        `;
        
        const nodeContent = node.querySelector('.search-folder-content');
        nodeContent.insertBefore(toggle, nodeContent.firstChild);
        
        // Arrow handler
        toggle.addEventListener('click', async (e) => {
            e.stopPropagation();
            await toggleFolder(node, item);
        });
        
        container.appendChild(node);
    });
    
    return container;
}

// Toggle folder expansion
async function toggleFolder(node, item) {
    const childrenContainer = node.querySelector('.search-folder-children');
    const toggle = node.querySelector('.folder-toggle');
    const isOpen = childrenContainer.style.display !== 'none';
    
    // Animate the arrow
    toggle.style.transform = isOpen ? 'rotate(0deg)' : 'rotate(90deg)';
    
    if (!isOpen) {
        childrenContainer.style.display = 'block';
        
        if (childrenContainer.dataset.loaded === 'false') {
            try {
                const res = await api('list', { path: item.path }, 'GET');
                const childFolders = res.items.filter(child => child.type === 'dir');
                
                if (childFolders.length > 0) {
                    const childNodes = renderSearchFolderNodes(childFolders, item.path, 
                        parseInt(node.style.marginLeft || '0') / 20 + 1);
                    childrenContainer.appendChild(childNodes);
                } else {
                    const emptyMsg = document.createElement('div');
                    emptyMsg.style.cssText = `
                        padding: 8px 20px;
                        color: var(--muted);
                        font-size: 12px;
                        font-style: italic;
                    `;
                    emptyMsg.textContent = t(i18n, 'noSubfolders') || 'No subfolders';
                    childrenContainer.appendChild(emptyMsg);
                }
                
                childrenContainer.dataset.loaded = 'true';
            } catch (err) {
                console.error('Failed to load folder contents:', err);
                childrenContainer.innerHTML = `
                    <div style="padding: 8px 20px; color: #e74c3c; font-size: 12px;">
                        ${t(i18n, 'loadError') || 'Error loading'}
                    </div>
                `;
            }
        }
    } else {
        childrenContainer.style.display = 'none';
    }
}

// Select a folder for search
function getSearchTargetKey(folderPath, root = '') {
    return `${root || 'main'}:${folderPath || ''}`;
}

function formatSearchTarget(target) {
    if (target.root === 'all') return t(i18n, 'allTypes') || 'All locations';
    const rootLabel = typeof getCloudRootDisplayName === 'function'
        ? getCloudRootDisplayName(target.root || 'main')
        : (target.root || 'main');
    return target.folder ? `${rootLabel}/${target.folder}` : rootLabel;
}

function updateSearchFolderSelectionUI() {
    const selected = new Set(advancedSearchState.searchTargets.map((target) => getSearchTargetKey(target.folder, target.root)));
    document.querySelectorAll('.search-folder-node').forEach((node) => {
        const key = getSearchTargetKey(node.dataset.path || '', node.dataset.root || '');
        const isSelected = selected.has(key);
        const content = node.querySelector('.search-folder-content');
        const checkmark = node.querySelector('.folder-checkmark');
        if (content) content.style.background = isSelected ? 'rgba(var(--accent-rgb), 0.1)' : 'transparent';
        if (checkmark) {
            checkmark.style.background = isSelected ? 'var(--accent)' : 'transparent';
            checkmark.style.borderColor = isSelected ? 'var(--accent)' : 'var(--border)';
            const svg = checkmark.querySelector('svg');
            if (svg) {
                svg.style.display = isSelected ? 'block' : 'none';
                svg.style.color = 'white';
            }
        }
    });
    const selectedDisplay = document.getElementById('selected-folder-display');
    if (selectedDisplay) {
        const label = selected.size === 1
            ? formatSearchTarget(advancedSearchState.searchTargets[0])
            : `${selected.size} ${t(i18n, 'folders') || 'folders'}`;
        const span = selectedDisplay.querySelector('span');
        if (span) span.textContent = label;
        else selectedDisplay.textContent = label;
    }
    advancedSearchState.searchFolder = advancedSearchState.searchTargets[0]?.folder || '';
    advancedSearchState.searchRoot = advancedSearchState.searchTargets[0]?.root || '';
}

function selectFolderForSearch(folderPath, root = '', forceSelected = false) {
    const target = { folder: folderPath || '', root: root || '' };
    const key = getSearchTargetKey(target.folder, target.root);
    const current = advancedSearchState.searchTargets || [];
    const exists = current.some((item) => getSearchTargetKey(item.folder, item.root) === key);

    if (target.root === 'all') {
        advancedSearchState.searchTargets = [target];
    } else {
        let next = current.filter((item) => item.root !== 'all');
        if (exists && !forceSelected) {
            next = next.filter((item) => getSearchTargetKey(item.folder, item.root) !== key);
        } else if (!exists) {
            next.push(target);
        }
        advancedSearchState.searchTargets = next.length ? next : [{ folder: '', root: 'all' }];
    }
    updateSearchFolderSelectionUI();
}

// Toggle size fields
function toggleSizeInputs() {
    const operator = document.getElementById('search-size-operator').value;
    const sizeValue = document.getElementById('search-size-value');
    const sizeBetween = document.getElementById('search-size-between');
    
    sizeValue.classList.toggle('hidden', operator === 'between' || operator === 'any');
    sizeBetween.classList.toggle('hidden', operator !== 'between');
}

// Toggle date fields
function toggleDateInputs() {
    const operator = document.getElementById('search-date-operator').value;
    const dateValue = document.getElementById('search-date-value');
    const dateBetween = document.getElementById('search-date-between');
    
    dateValue.classList.toggle('hidden', operator === 'between' || operator === 'any');
    dateBetween.classList.toggle('hidden', operator !== 'between');
}

// Reset the form
function resetAdvancedSearchForm() {
    document.getElementById('search-name').value = '';
    document.getElementById('search-extension').value = '';
    document.getElementById('search-type').value = 'all';
    setAdvancedTextMode('name');
    document.querySelectorAll('#search-type-mode button').forEach((btn) => btn.classList.toggle('active', btn.dataset.type === 'all'));
    document.getElementById('search-size-operator').value = 'any';
    document.getElementById('search-size-value').value = '';
    document.getElementById('search-size-unit').value = 'MB';
    document.getElementById('search-size-min').value = '';
    document.getElementById('search-size-max').value = '';
    document.getElementById('search-size-between-unit').value = 'MB';
    document.getElementById('search-date-operator').value = 'any';
    document.getElementById('search-date-value').value = '';
    document.getElementById('search-date-start').value = '';
    document.getElementById('search-date-end').value = '';
    hideExtensionSuggestions();
    
    // Reset the folder selection to the root
    advancedSearchState.searchTargets = [];
    selectFolderForSearch('', 'all', true);
    
    toggleSizeInputs();
    toggleDateInputs();
}

// Run the advanced search
async function startAdvancedSearch() {
    const textMode = advancedSearchState.textMode || 'name';
    // Collect the filters
    const filters = {
        name: textMode === 'name' ? document.getElementById('search-name').value.trim() : '',
        extension: textMode === 'extension' ? document.getElementById('search-extension').value.trim() : '',
        type: document.getElementById('search-type').value,
        folder: advancedSearchState.searchFolder || '',
        root: advancedSearchState.searchRoot || '',
        targets: normalizeAdvancedSearchTargets(advancedSearchState.searchTargets),
        size: {
            operator: document.getElementById('search-size-operator').value,
            value: parseFloat(document.getElementById('search-size-value').value),
            min: parseFloat(document.getElementById('search-size-min').value),
            max: parseFloat(document.getElementById('search-size-max').value),
            unit: document.getElementById('search-size-unit').value,
            betweenUnit: document.getElementById('search-size-between-unit').value
        },
        date: {
            operator: document.getElementById('search-date-operator').value,
            value: document.getElementById('search-date-value').value,
            start: document.getElementById('search-date-start').value,
            end: document.getElementById('search-date-end').value
        }
    };

    // Close the modal
    document.getElementById('advanced-search-modal').classList.add('hidden');
    
    // Run the search with progress
    await performAdvancedSearchWithProgress(filters);
}

function normalizeAdvancedSearchTargets(targets) {
    const selected = Array.isArray(targets) && targets.length ? targets : [{ folder: '', root: 'all' }];
    if (selected.some((target) => target.root === 'all')) {
        return [
            { folder: '', root: '' },
            { folder: '', root: 'archive' },
            { folder: '', root: 'temp' },
            { folder: '', root: 'documents' },
            { folder: '', root: 'photos' },
            { folder: '', root: 'pdf' }
        ];
    }
    return selected.map((target) => ({ folder: target.folder || '', root: target.root || '' }));
}

// Execute the advanced search with a progress bar
async function performAdvancedSearchWithProgress(filters) {
    const progressContainer = document.getElementById('search-progress-container');
    const progressPercent = document.getElementById('search-progress-percent');
    const progressFill = document.getElementById('search-progress-fill');
    const processedSpan = document.getElementById('search-processed-items');
    const totalSpan = document.getElementById('search-total-items');
    
    // Show the progress bar
    progressContainer.classList.remove('hidden');
    
    advancedSearchState.isSearching = true;
    advancedSearchState.results = [];
    advancedSearchState.processedItems = 0;
    advancedSearchState.filters = filters;
    
    const searchTargets = normalizeAdvancedSearchTargets(filters.targets || [{ folder: filters.folder || '', root: filters.root || '' }]);
    const allItems = [];
    const seenItems = new Set();
    for (const target of searchTargets) {
        const targetItems = await getAllItemsForSearch(target.folder || '', target.root || '');
        targetItems.forEach((item) => {
            const root = target.root || '';
            const key = `${root}:${item.path}`;
            if (seenItems.has(key)) return;
            seenItems.add(key);
            allItems.push({ ...item, root });
        });
    }
    advancedSearchState.totalItems = allItems.length;
    totalSpan.textContent = allItems.length;
    
    // Create an AbortController for cancellation
    advancedSearchState.abortController = new AbortController();
    
    // Run the search with progress
    const results = [];
    
    for (let i = 0; i < allItems.length; i++) {
        if (advancedSearchState.abortController.signal.aborted) {
            break;
        }
        
        const item = allItems[i];
        
        // Check filter matching
        if (matchesFilters(item, filters)) {
            results.push(item);
        }
        
        // Update the progress
        advancedSearchState.processedItems = i + 1;
        const percent = Math.round(((i + 1) / allItems.length) * 100);
        progressPercent.textContent = percent + '%';
        progressFill.style.width = percent + '%';
        processedSpan.textContent = i + 1;
        
        // Give the browser time to update the UI
        if (i % 100 === 0) {
            await new Promise(resolve => setTimeout(resolve, 0));
        }
    }
    
    // Hide the progress bar
    progressContainer.classList.add('hidden');
    
    // Display the results
    advancedSearchState.results = results;
    displayAdvancedSearchResults(results, filters);
    
    advancedSearchState.isSearching = false;
    advancedSearchState.abortController = null;
}

// Get all elements for search
async function getAllItemsForSearch(path, root = '') {
    const params = { path };
    if (root) params.root = root;
    const items = await api('list', params, 'GET');
    let allItems = [...items.items];
    for (const item of items.items) {
        if (item.type === 'dir') {
            const subItems = await getAllItemsForSearch(item.path, root);
            allItems = allItems.concat(subItems);
        }
    }
    return allItems;
}

// Check filter matching
function matchesFilters(item, filters) {
    // Filter by name
    if (filters.name) {
        const pattern = filters.name.replace(/\*/g, '.*').toLowerCase();
        const regex = new RegExp(pattern);
        if (!regex.test(item.name.toLowerCase())) {
            return false;
        }
    }
    
    // Filter by extension
    if (filters.extension) {
        const ext = '.' + item.name.split('.').pop().toLowerCase();
        const searchExt = filters.extension.toLowerCase();
        if (!searchExt.includes(ext) && !ext.includes(searchExt.replace('.', ''))) {
            return false;
        }
    }
    
    // Filter by type
    if (filters.type !== 'all' && !matchesType(item, filters.type)) {
        return false;
    }
    
    // Filter by size (files only)
    if (item.type === 'file' && filters.size.operator !== 'any') {
        if (!matchesSize(item.size || 0, filters.size)) {
            return false;
        }
    }
    
    // Filter by date
    if (filters.date.operator !== 'any' && item.mtime) {
        if (!matchesDate(item.mtime, filters.date)) {
            return false;
        }
    }
    
    return true;
}

// File type check
function matchesType(item, type) {
    if (type === 'file') return item.type === 'file';
    if (type === 'folder') return item.type === 'dir';
    if (item.type === 'dir') return type === 'folder';
    
    const ext = item.name.split('.').pop().toLowerCase();
    
    const typeMap = {
        'document': ['txt', 'doc', 'docx', 'pdf', 'odt', 'rtf'],
        'image': ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'tiff'],
        'audio': ['mp3', 'wav', 'ogg', 'm4a', 'flac', 'aac'],
        'video': ['mp4', 'webm', 'ogg', 'mov', 'avi', 'mkv'],
        'archive': ['zip', 'rar', '7z', 'tar', 'gz'],
        'code': ['js', 'ts', 'html', 'css', 'php', 'py', 'java', 'cpp', 'c', 'm'],
        '3d': ['obj', 'stl', '3mf', 'fbx']
    };
    
    return typeMap[type]?.includes(ext) || false;
}

// Size check
function matchesSize(size, sizeFilter) {
    const value = convertToBytes(sizeFilter.value, sizeFilter.unit);
    const min = convertToBytes(sizeFilter.min, sizeFilter.betweenUnit);
    const max = convertToBytes(sizeFilter.max, sizeFilter.betweenUnit);
    
    switch (sizeFilter.operator) {
        case 'greater':
            return size > value;
        case 'less':
            return size < value;
        case 'between':
            return size >= min && size <= max;
        case 'exact':
            return Math.abs(size - value) < 1024; // 1KB tolerance
        default:
            return true;
    }
}

// Date check
function matchesDate(timestamp, dateFilter) {
    const itemDate = new Date(timestamp * 1000);
    
    switch (dateFilter.operator) {
        case 'after':
            return itemDate > new Date(dateFilter.value);
        case 'before':
            return itemDate < new Date(dateFilter.value);
        case 'between':
            return itemDate >= new Date(dateFilter.start) && 
                   itemDate <= new Date(dateFilter.end);
        default:
            return true;
    }
}

// Convert to bytes
function convertToBytes(value, unit) {
    if (!value) return 0;
    
    const multipliers = {
        'B': 1,
        'KB': 1024,
        'MB': 1024 * 1024,
        'GB': 1024 * 1024 * 1024
    };
    
    return value * (multipliers[unit] || 1);
}

// Display advanced search results
function displayAdvancedSearchResults(results, filters) {
    const tree = document.getElementById('tree');
    tree.innerHTML = '';
    
    if (results.length === 0) {
        tree.innerHTML = `<div class="no-results">${t(i18n, 'noResultsFound') || 'No results found'}</div>`;
        return;
    }
    
    const container = document.createElement('div');
    container.className = 'search-results-table';
    
    // Title with the result count
    const header = document.createElement('div');
    header.className = 'search-results-header';
    header.innerHTML = `
        <h3>${t(i18n, 'searchResults') || 'Search Results'} (${results.length})</h3>
        <button class="btn secondary small" onclick="window.clearSearch ? window.clearSearch() : location.reload()">
            ${t(i18n, 'clear') || 'Clear'}
        </button>
    `;
    container.appendChild(header);
    
    // Results table
    results.forEach((item, index) => {
        const resolved = typeof getSearchResultDisplayPath === 'function'
            ? getSearchResultDisplayPath(item)
            : { root: item.root || '', localPath: item.path, displayPath: item.path };
        const resultItem = document.createElement('div');
        resultItem.className = 'search-result-item advanced-search-result search-result-node';
        resultItem.dataset.path = resolved.localPath;
        resultItem.dataset.root = resolved.root || 'main';
        resultItem.dataset.type = item.type;
        
        const icon = getFileIconForPreview({ ...item, path: resolved.localPath });
        const size = item.type === 'file' ? formatBytes(item.size || 0) : '—';
        const date = item.mtime ? new Date(item.mtime * 1000).toLocaleString() : '—';
        
        resultItem.innerHTML = `
            <div class="result-icon">${icon}</div>
            <div class="result-info">
                <div class="result-name">${escapeHtml(item.name || resolved.localPath.split('/').pop() || resolved.displayPath)}</div>
                <div class="result-path">${escapeHtml(resolved.displayPath)}</div>
                <div class="result-meta">
                    <span class="result-size">
                        <svg width="12" height="12" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none">
                            <path d="M20 12H4M12 4v16"></path>
                        </svg>
                        ${size}
                    </span>
                    <span class="result-date">
                        <svg width="12" height="12" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none">
                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                            <line x1="16" y1="2" x2="16" y2="6"></line>
                            <line x1="8" y1="2" x2="8" y2="6"></line>
                            <line x1="3" y1="10" x2="21" y2="10"></line>
                        </svg>
                        ${date}
                    </span>
                </div>
            </div>
        `;

        if (typeof selectionState !== 'undefined' && selectionState.active && typeof addCheckboxToNode === 'function') {
            addCheckboxToNode(resultItem);
        }
        if (typeof syncNodeDragHandle === 'function') syncNodeDragHandle(resultItem);
        
        resultItem.addEventListener('click', (e) => {
            if (typeof selectionState !== 'undefined' && selectionState.active) {
                e.preventDefault();
                e.stopPropagation();
                const checkbox = resultItem.querySelector('.selection-checkbox');
                if (checkbox) {
                    checkbox.checked = !checkbox.checked;
                    if (typeof handleItemSelection === 'function') {
                        handleItemSelection(resolved.localPath, item.type, checkbox.checked, resolved.root);
                    }
                }
                return;
            }
            if (item.type === 'dir') {
                expandToFolder(resolved.localPath, resolved.root);
            } else {
                if (typeof openFileFromTree === 'function') openFileFromTree(resolved.localPath, resolved.root);
                else if (typeof openFileInTab === 'function') openFileInTab(resolved.localPath, false, resolved.root);
            }
        });
        
        resultItem.addEventListener('contextmenu', (e) => {
            e.preventDefault();
            e.stopPropagation();
            if (typeof showContextMenu === 'function') {
                showContextMenu(e, resolved.localPath, item.type, item.name);
            }
        });
        
        container.appendChild(resultItem);
    });
    
    tree.appendChild(container);
}

// Helper function to get the file icon
function getFileIconForPreview(item) {
    if (typeof window.getItemIconMarkup === 'function') {
        return window.getItemIconMarkup(item);
    }
    return '';
}

// Function to expand to the folder
async function expandToFolder(path, root = null) {
    if (typeof buildTree === 'function') {
        if (root && root !== getCurrentCloudRoot() && typeof switchCloudRoot === 'function') {
            await switchCloudRoot(root, { silent: true });
        }
        await buildTree('');
        if (typeof expandFolderToPath === 'function') {
            await expandFolderToPath(path);
        }
    }
}

window.advancedSearch = {
    init: window.initAdvancedSearch,
    reset: resetAdvancedSearchForm,
    start: startAdvancedSearch
}
