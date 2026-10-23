// assets/monaco-editor.js
// Monaco Editor integration for editing mode only

let monacoInitialized = false;
let monacoEditors = new Map(); // tabId -> editor instance
let monacoModels = new Map(); // path -> model
let monacoModelRefs = new Map(); // path -> active editor count
let monacoTabModelPaths = new Map(); // tabId -> path
let monacoIsTouchDevice = false;
let monacoSelectionHandlesCSSInjected = false;
let monacoAiEditDecorations = new Map(); // tabId -> decoration ids
let monacoAiEditZones = new Map(); // tabId -> view zone ids
let monacoRemoteCursorWidgets = new Map(); // tabId -> Map(clientId -> widget)
let monacoCollabInsertDecorations = new Map(); // tabId -> decoration id[]
const monacoCollabInsertStyleIds = new Set();
let monacoSpellcheckCodeActionsRegistered = false;
let monacoCompletionsRegistered = false;
const monacoSpellcheckStates = new Map();
// All spellcheck requests are multiplexed through a single shared worker so the
// dictionaries (typo.js plus the .aff/.dic files) are fetched, parsed and kept in
// memory only once, no matter how many text editors are open at the same time.
// Every request gets a unique id and is routed back to the editor that sent it.
const monacoSpellcheckRoutes = new Map(); // requestId -> { state, kind }
let monacoSpellcheckSharedWorker = null;
let monacoSpellcheckRequestSeq = 0;
const monacoFooterStatusEls = new Map();
const monacoEditorExtraDisposables = new Map();
const monacoSpellcheckDebounceMs = 650;
const monacoSpellcheckMaxErrors = 200;
const monacoCompletionCache = new Map();
const monacoSpellcheckLanguages = {
    auto: {
        label: 'monacoSpellAuto'
    },
    off: {
        label: 'monacoSpellcheckOff'
    },
    en_US: {
        label: 'English',
        aff: 'assets/dictionaries/en_US.aff',
        dic: 'assets/dictionaries/en_US.dic'
    },
    ru_RU: {
        label: 'Русский',
        aff: 'assets/dictionaries/ru_RU.aff',
        dic: 'assets/dictionaries/ru_RU.dic'
    },
    uk_UA: {
        label: 'Українська',
        aff: 'assets/dictionaries/uk_UA.aff',
        dic: 'assets/dictionaries/uk_UA.dic'
    },
    be_BY: {
        label: 'Беларуская',
        aff: 'assets/dictionaries/be_BY.aff',
        dic: 'assets/dictionaries/be_BY.dic'
    },
    de_DE: {
        label: 'Deutsch',
        aff: 'assets/dictionaries/de_DE.aff',
        dic: 'assets/dictionaries/de_DE.dic'
    },
    fr_FR: {
        label: 'Français',
        aff: 'assets/dictionaries/fr_FR.aff',
        dic: 'assets/dictionaries/fr_FR.dic'
    },
    es_ES: {
        label: 'Español',
        aff: 'assets/dictionaries/es_ES.aff',
        dic: 'assets/dictionaries/es_ES.dic'
    },
    it_IT: {
        label: 'Italiano',
        aff: 'assets/dictionaries/it_IT.aff',
        dic: 'assets/dictionaries/it_IT.dic'
    },
    pt_BR: {
        label: 'Português (Brasil)',
        aff: 'assets/dictionaries/pt_BR.aff',
        dic: 'assets/dictionaries/pt_BR.dic'
    },
    nl_NL: {
        label: 'Nederlands',
        aff: 'assets/dictionaries/nl_NL.aff',
        dic: 'assets/dictionaries/nl_NL.dic'
    },
    pl_PL: {
        label: 'Polski',
        aff: 'assets/dictionaries/pl_PL.aff',
        dic: 'assets/dictionaries/pl_PL.dic'
    },
    cs_CZ: {
        label: 'Čeština',
        aff: 'assets/dictionaries/cs_CZ.aff',
        dic: 'assets/dictionaries/cs_CZ.dic'
    },
    da_DK: {
        label: 'Dansk',
        aff: 'assets/dictionaries/da_DK.aff',
        dic: 'assets/dictionaries/da_DK.dic'
    },
    sv_SE: {
        label: 'Svenska',
        aff: 'assets/dictionaries/sv_SE.aff',
        dic: 'assets/dictionaries/sv_SE.dic'
    }
};

const monacoCollabRemoteColors = ['#ef4444', '#f59e0b', '#10b981', '#3b82f6', '#ec4899', '#14b8a6', '#a855f7', '#f97316'];

// Punctuation that sticks to the word in front of it: when a suggestion put a
// trailing space at the end of the text, typing one of these removes that space
// again instead of leaving "word ," behind.
const monacoStickyPunctuation = /^[.,!?;:)\]}>%°…»”’"'–—-]$/u;
// Command id used by completion items and code actions to request the trailing
// space after the word the user just accepted.
const monacoSuggestionSpaceCommandId = 'cloudMonacoSuggestionTrailingSpace';

function monacoT(key, params) {
    if (typeof t === 'function' && window.i18n) return t(window.i18n, key, params || {});
    return key;
}

function monacoSpellcheckLanguagesPayload() {
    const payload = { auto: { label: monacoSpellcheckLanguages.auto.label } };
    Object.entries(monacoSpellcheckLanguages).forEach(([id, lang]) => {
        if (id !== 'auto') payload[id] = { aff: lang.aff, dic: lang.dic };
    });
    return payload;
}

function monacoCollabColorForClient(clientId) {
    const id = String(clientId || '');
    let hash = 0;
    for (let i = 0; i < id.length; i++) hash = ((hash << 5) - hash) + id.charCodeAt(i);
    return monacoCollabRemoteColors[Math.abs(hash) % monacoCollabRemoteColors.length];
}

function monacoAddEditorDisposable(editor, disposable) {
    if (!editor || !disposable || typeof disposable.dispose !== 'function') return;
    const list = monacoEditorExtraDisposables.get(editor) || [];
    list.push(disposable);
    monacoEditorExtraDisposables.set(editor, list);
}

function monacoDisposeExtraDisposables(editor) {
    const list = monacoEditorExtraDisposables.get(editor) || [];
    list.forEach((disposable) => {
        try {
            disposable.dispose();
        } catch (_) {}
    });
    monacoEditorExtraDisposables.delete(editor);
}

function monacoAcquireModel(filePath, content, language) {
    const normalizedPath = String(filePath || '');
    let model = monacoModels.get(normalizedPath);
    if (!model || model.isDisposed()) {
        const modelUri = monaco.Uri.parse(`file:///${normalizedPath.replace(/^\/+/, '')}`);
        model = monaco.editor.createModel(content, language, modelUri);
        monacoModels.set(normalizedPath, model);
    } else {
        monaco.editor.setModelLanguage(model, language);
        if (model.getValue() !== content) {
            model.setValue(content);
        }
    }
    monacoModelRefs.set(normalizedPath, (monacoModelRefs.get(normalizedPath) || 0) + 1);
    return model;
}

function monacoReleaseModelByTab(tabId) {
    const filePath = monacoTabModelPaths.get(tabId);
    if (!filePath) return;
    monacoTabModelPaths.delete(tabId);
    const nextRefCount = Math.max(0, (monacoModelRefs.get(filePath) || 1) - 1);
    if (nextRefCount > 0) {
        monacoModelRefs.set(filePath, nextRefCount);
        return;
    }
    monacoModelRefs.delete(filePath);
    const model = monacoModels.get(filePath);
    if (model && !model.isDisposed()) {
        model.dispose();
    }
    monacoModels.delete(filePath);
}


// Inject CSS for selection handles
function monacoInjectSelectionHandlesCSS() {
    if (monacoSelectionHandlesCSSInjected) return;

    const style = document.createElement('style');
    // Word-style handles: thin vertical line with a dot on top (start) and a
    // horizontal bar with a dot underneath (end), matching the reference design.
    style.textContent = `
        .monaco-touch-selection-handles {
            pointer-events: none !important;
        }
        .word-handle {
            position: absolute !important;
            pointer-events: auto !important;
            touch-action: none !important;
            z-index: 10000 !important;
            opacity: 0;
            transition: opacity .08s linear;
            will-change: top, left;
            cursor: pointer !important;
        }
        .word-handle.top-line {
            width: 1.5px !important;
            height: 18px !important;
            background: #3b82f6 !important;
            border-radius: 1px !important;
            box-shadow: 0 0 3px rgba(59,130,246,.6) !important;
        }
        .word-handle.bottom-line {
            height: 1.5px !important;
            width: 6px !important;
            background: #3b82f6 !important;
            border-radius: 1px !important;
            box-shadow: 0 0 3px rgba(59,130,246,.6) !important;
        }
        .word-handle.top-line::before {
            content: '' !important;
            position: absolute !important;
            top: -3px !important;
            left: 50% !important;
            transform: translateX(-50%) !important;
            width: 5px !important;
            height: 5px !important;
            background: #3b82f6 !important;
            border-radius: 50% !important;
            box-shadow: 0 0 4px rgba(59,130,246,.8) !important;
        }
        .word-handle.bottom-line::after {
            content: '' !important;
            position: absolute !important;
            bottom: -3px !important;
            left: 50% !important;
            transform: translateX(-50%) !important;
            width: 5px !important;
            height: 5px !important;
            background: #3b82f6 !important;
            border-radius: 50% !important;
            box-shadow: 0 0 4px rgba(59,130,246,.8) !important;
        }
        /* Enlarged click area for both handles */
        .word-handle:active {
            filter: brightness(1.2) !important;
        }
        .monaco-spellcheck-error {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='4' viewBox='0 0 12 4'%3E%3Cpath d='M0 3 Q3 0 6 3 T12 3' fill='none' stroke='%23e53935' stroke-width='1.4'/%3E%3C/svg%3E") !important;
            background-repeat: repeat-x !important;
            background-position: 0 100% !important;
            background-size: 12px 4px !important;
            text-decoration: none !important;
            background-color: transparent !important;
        }
        .monaco-editor-container,
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
        .monaco-status-bar {
            min-height: 24px !important;
            height: auto !important;
            gap: 8px !important;
            box-sizing: border-box !important;
            overflow: hidden !important;
        }
        .monaco-status-left,
        .monaco-status-right {
            min-width: 0 !important;
        }
        .monaco-app-footer-status {
            width: 100% !important;
        }
        .monaco-app-footer-status .monaco-status-left {
            flex: 1 1 auto !important;
        }
        .monaco-app-footer-status .monaco-status-right {
            flex: 0 0 auto !important;
            margin-left: auto !important;
        }
        .monaco-status-item {
            white-space: nowrap !important;
        }
        /* Spellcheck language picker: the trigger keeps the app's input look (the
           native select chrome is gone because the picker is now a modal, but the
           arrow still hints that a list opens). */
        .monaco-status-spell-select {
            appearance: none !important;
            -webkit-appearance: none !important;
            display: inline-flex !important;
            align-items: center !important;
            gap: 6px !important;
            text-align: left !important;
            background-color: var(--panel, #2a2a2a) !important;
            color: var(--fg, #e6e6e6) !important;
            border: 1px solid color-mix(in srgb, var(--accent, #4a90e2) 35%, var(--border, rgba(255,255,255,.2))) !important;
            border-radius: 6px !important;
            padding: 2px 22px 2px 8px !important;
            font-family: inherit !important;
            line-height: 1.5 !important;
            max-width: 100% !important;
            cursor: pointer !important;
            outline: none !important;
            transition: border-color .15s ease, box-shadow .15s ease !important;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6' viewBox='0 0 10 6'%3E%3Cpath d='M1 1l4 4 4-4' fill='none' stroke='%23888888' stroke-width='1.5' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E") !important;
            background-repeat: no-repeat !important;
            background-position: right 6px center !important;
            background-size: 10px 6px !important;
        }
        .monaco-status-spell-select:hover {
            border-color: color-mix(in srgb, var(--accent, #4a90e2) 60%, var(--border, rgba(255,255,255,.2))) !important;
        }
        .monaco-status-spell-select:focus {
            border-color: var(--accent, #4a90e2) !important;
            box-shadow: 0 0 0 2px color-mix(in srgb, var(--accent, #4a90e2) 30%, transparent) !important;
        }
        /* Flag/icon shown inside the trigger */
        .monaco-spell-icon {
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            width: 18px !important;
            height: 12px !important;
            flex: 0 0 auto !important;
            border-radius: 2px !important;
            overflow: hidden !important;
        }
        .monaco-spell-icon svg,
        .monaco-spell-option-icon svg {
            width: 100% !important;
            height: 100% !important;
            display: block !important;
        }
        .monaco-spell-icon-label {
            min-width: 0 !important;
            white-space: nowrap !important;
            overflow: hidden !important;
            text-overflow: ellipsis !important;
        }
        /* Language picker modal, centered on screen */
        .monaco-spell-modal-overlay {
            position: fixed !important;
            inset: 0 !important;
            z-index: 20000 !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            padding: 16px !important;
            background: rgba(0, 0, 0, .45) !important;
            backdrop-filter: blur(2px) !important;
            -webkit-backdrop-filter: blur(2px) !important;
            animation: monacoSpellModalFade .12s ease-out !important;
        }
        @keyframes monacoSpellModalFade {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        .monaco-spell-modal {
            width: min(360px, 100%) !important;
            max-height: min(70vh, 520px) !important;
            display: flex !important;
            flex-direction: column !important;
            background: var(--panel, #2a2a2a) !important;
            color: var(--fg, #e6e6e6) !important;
            border: 1px solid color-mix(in srgb, var(--accent, #4a90e2) 35%, var(--border, rgba(255,255,255,.2))) !important;
            border-radius: 12px !important;
            box-shadow: 0 18px 48px rgba(0, 0, 0, .45) !important;
            overflow: hidden !important;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif !important;
            font-size: 13px !important;
        }
        .monaco-spell-modal-head {
            display: flex !important;
            align-items: center !important;
            justify-content: space-between !important;
            gap: 8px !important;
            padding: 12px 14px !important;
            font-weight: 600 !important;
            border-bottom: 1px solid color-mix(in srgb, var(--border, rgba(255,255,255,.2)) 70%, transparent) !important;
        }
        .monaco-spell-modal-close {
            appearance: none !important;
            -webkit-appearance: none !important;
            background: transparent !important;
            border: 0 !important;
            color: inherit !important;
            font: inherit !important;
            font-size: 18px !important;
            line-height: 1 !important;
            padding: 2px 8px !important;
            border-radius: 6px !important;
            cursor: pointer !important;
        }
        .monaco-spell-modal-close:hover {
            background: color-mix(in srgb, var(--fg, #ffffff) 12%, transparent) !important;
        }
        .monaco-spell-modal-list {
            display: flex !important;
            flex-direction: column !important;
            gap: 2px !important;
            padding: 8px !important;
            overflow-y: auto !important;
            /* Must be allowed to shrink inside the capped panel, otherwise the
               tail of the list would be clipped by the panel with no way to
               scroll to it. */
            flex: 1 1 auto !important;
            min-height: 0 !important;
            overscroll-behavior: contain !important;
            /* Follow the app theme: dark track/thumb in the dark theme instead of
               the browser's default light scrollbar. */
            scrollbar-width: thin !important;
            scrollbar-color: var(--scrollbar-thumb, var(--muted, #888888)) var(--scrollbar-track, var(--panel, #2a2a2a)) !important;
        }
        .monaco-spell-modal-list::-webkit-scrollbar {
            width: 10px !important;
        }
        .monaco-spell-modal-list::-webkit-scrollbar-track {
            background: var(--scrollbar-track, var(--panel, #2a2a2a)) !important;
            border-radius: 3px !important;
        }
        .monaco-spell-modal-list::-webkit-scrollbar-thumb {
            background: var(--scrollbar-thumb, var(--muted, #888888)) !important;
            border: 2px solid transparent !important;
            background-clip: padding-box !important;
            border-radius: 999px !important;
        }
        .monaco-spell-modal-list::-webkit-scrollbar-thumb:hover {
            background: var(--scrollbar-thumb-hover, var(--muted, #888888)) !important;
            background-clip: padding-box !important;
        }
        .monaco-spell-option {
            display: flex !important;
            align-items: center !important;
            gap: 10px !important;
            width: 100% !important;
            padding: 8px 10px !important;
            background: transparent !important;
            border: 1px solid transparent !important;
            border-radius: 8px !important;
            color: inherit !important;
            font: inherit !important;
            text-align: left !important;
            cursor: pointer !important;
        }
        .monaco-spell-option:hover,
        .monaco-spell-option:focus-visible {
            background: color-mix(in srgb, var(--accent, #4a90e2) 12%, transparent) !important;
            outline: none !important;
        }
        .monaco-spell-option.is-active {
            background: color-mix(in srgb, var(--accent, #4a90e2) 18%, transparent) !important;
            border-color: color-mix(in srgb, var(--accent, #4a90e2) 45%, transparent) !important;
        }
        .monaco-spell-option-icon {
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            width: 26px !important;
            height: 18px !important;
            flex: 0 0 auto !important;
            border-radius: 3px !important;
            overflow: hidden !important;
        }
        .monaco-spell-option-label {
            flex: 1 1 auto !important;
            min-width: 0 !important;
            white-space: nowrap !important;
            overflow: hidden !important;
            text-overflow: ellipsis !important;
        }
        .monaco-spell-option-check {
            flex: 0 0 auto !important;
            opacity: 0 !important;
            color: var(--accent, #4a90e2) !important;
        }
        .monaco-spell-option.is-active .monaco-spell-option-check {
            opacity: 1 !important;
        }
        @media (max-width: 768px) {
            .monaco-status-bar {
                align-items: stretch !important;
                flex-direction: column !important;
                padding: 5px 8px !important;
                min-height: 52px !important;
            }
            .monaco-status-left,
            .monaco-status-right {
                width: 100% !important;
                gap: 6px !important;
                overflow-x: auto !important;
                scrollbar-width: none !important;
            }
            .monaco-app-footer-status {
                width: 100% !important;
                flex-wrap: nowrap !important;
            }
            .monaco-app-footer-status .monaco-status-left {
                flex: 1 1 auto !important;
            }
            .monaco-app-footer-status .monaco-status-right {
                flex: 0 0 auto !important;
                width: auto !important;
                margin-left: auto !important;
            }
            .monaco-status-left::-webkit-scrollbar,
            .monaco-status-right::-webkit-scrollbar {
                display: none !important;
            }
            .monaco-status-spell-label {
                display: none !important;
            }
            .monaco-status-spell-select {
                flex: 0 0 auto !important;
                max-width: 42vw !important;
            }
            .monaco-status-spell-status {
                flex: 0 0 auto !important;
            }
            .monaco-status-item {
                padding: 2px 6px !important;
            }
        }
    `;
    document.head.appendChild(style);
    monacoSelectionHandlesCSSInjected = true;
}

// Detect touch device. Only coarse pointers count so touch-capable laptops
// with a mouse do not get mobile-only UI (touch selection handles etc.).
function monacoDetectTouchDevice() {
    try {
        if (window.matchMedia && window.matchMedia('(pointer: coarse)').matches) return true;
    } catch (_) {}
    // Fallback for browsers without matchMedia support
    if (!('ontouchstart' in window) && !(navigator.maxTouchPoints > 0)) return false;
    return window.innerWidth <= 768;
}

// Detect if device is in portrait orientation (vertical screen)
function monacoIsPortraitOrientation() {
    // Check if screen height is greater than width (portrait)
    return window.innerHeight > window.innerWidth;
}

// Check if minimap should be hidden (touch device in portrait orientation)
function monacoShouldHideMinimap() {
    return monacoIsTouchDevice && monacoIsPortraitOrientation();
}

// Monaco's built-in UI strings (context menu, quick fix window, command palette, ...)
// come from assets/monaco-shared.js so that every Monaco editor in the app shares one
// map. It has to run before vs/editor/editor.main resolves the labels.
function monacoApplyNlsTranslations() {
    if (!window.cloudMonacoShared) {
        console.error('[monaco] assets/monaco-shared.js must be loaded before assets/monaco-editor.js');
        return 0;
    }
    return window.cloudMonacoShared.applyNlsTranslations(
        window.i18n || {},
        (window.APP_CONFIG && window.APP_CONFIG.lang) || 'en'
    );
}

// Initialize Monaco editor
function initMonaco() {
    return new Promise((resolve, reject) => {
        if (monacoInitialized) {
            resolve();
            return;
        }
        
        monacoIsTouchDevice = monacoDetectTouchDevice();
        
        if (typeof require === 'undefined') {
            console.error('Require.js is not loaded');
            reject(new Error('Require.js is not loaded'));
            return;
        }

        // Translate Monaco's own UI strings before the editor bundle resolves them,
        // then point the loader at the shared, pinned Monaco build.
        monacoApplyNlsTranslations();

        if (!window.cloudMonacoShared || !window.cloudMonacoShared.configureLoader()) {
            reject(new Error('assets/monaco-shared.js is missing or require.js is not loaded'));
            return;
        }
        
        require(['vs/editor/editor.main'], function() {
            monacoInitialized = true;
            if (typeof monaco !== 'undefined') window.monaco = monaco;

            // Inject CSS for touch selection handles
            monacoInjectSelectionHandlesCSS();

            monacoUpdateTheme();
            
            if (monaco.languages && monaco.languages.typescript) {
                monaco.languages.typescript.javascriptDefaults.setCompilerOptions({
                    target: monaco.languages.typescript.ScriptTarget.ES2020,
                    allowNonTsExtensions: true,
                });
            }
            
            monacoSetupLanguages();
            monacoAddCustomEditorActions();
            monacoRegisterSuggestionTrailingSpace();
            monacoRegisterSpellcheckCodeActions();
            
            resolve();
        }, function(error) {
            console.error('Failed to load Monaco editor:', error);
            reject(error);
        });
    });
}

// Marks the completion items that are plain words, so accepting one can append the
// trailing space (see monacoAppendSuggestionTrailingSpace). Snippets that place the
// caret inside brackets must keep their own layout.
function monacoWithSuggestionSpaceCommand(items) {
    (items || []).forEach((item) => {
        if (typeof item.insertText === 'string' && /^[A-Za-z_][A-Za-z0-9_]*$/.test(item.insertText)) {
            item.command = { id: monacoSuggestionSpaceCommandId, title: '' };
        }
    });
    return items;
}

// Register hints from completions.js
function monacoRegisterCompletions() {
    if (monacoCompletionsRegistered) return;
    monacoCompletionsRegistered = true;
    if (!window.completions) {
        console.warn('Completions database not found');
        return;
    }

    const completions = window.completions;

    // Register providers for each language
    Object.keys(completions).forEach(langId => {
        const langCompletions = completions[langId];
        
        monaco.languages.registerCompletionItemProvider(langId, {
            provideCompletionItems: (model, position) => {
                if (monacoCompletionCache.has(langId)) {
                    return { suggestions: monacoCompletionCache.get(langId) };
                }
                const suggestions = [];
                
                // Add keywords
                if (langCompletions.keywords) {
                    langCompletions.keywords.forEach(keyword => {
                        suggestions.push({
                            label: keyword,
                            kind: monaco.languages.CompletionItemKind.Keyword,
                            insertText: keyword,
                            detail: 'Keyword',
                            documentation: `${langId} keyword`
                        });
                    });
                }
                
                // Add built-in functions/objects
                if (langCompletions.builtins) {
                    langCompletions.builtins.forEach(builtin => {
                        suggestions.push({
                            label: builtin,
                            kind: monaco.languages.CompletionItemKind.Function,
                            insertText: builtin,
                            detail: 'Built-in function',
                            documentation: `Built-in ${langId} function`
                        });
                    });
                }
                
                // Add methods (for JavaScript, Python, etc.)
                if (langCompletions.methods) {
                    langCompletions.methods.forEach(method => {
                        suggestions.push({
                            label: method,
                            kind: monaco.languages.CompletionItemKind.Method,
                            insertText: method + '()',
                            insertTextRules: monaco.languages.CompletionItemInsertTextRule.InsertAsSnippet,
                            detail: 'Method',
                            documentation: `${langId} method`
                        });
                    });
                }
                
                // Add operators
                if (langCompletions.operators) {
                    langCompletions.operators.forEach(operator => {
                        suggestions.push({
                            label: operator,
                            kind: monaco.languages.CompletionItemKind.Operator,
                            insertText: operator,
                            detail: 'Operator',
                            documentation: `${langId} operator`
                        });
                    });
                }
                
                // HTML-specific tags and attributes
                if (langId === 'html') {
                    if (langCompletions.tags) {
                        langCompletions.tags.forEach(tag => {
                            suggestions.push({
                                label: tag,
                                kind: monaco.languages.CompletionItemKind.Property,
                                insertText: `<${tag}>$1</${tag}>`,
                                insertTextRules: monaco.languages.CompletionItemInsertTextRule.InsertAsSnippet,
                                detail: 'HTML tag',
                                documentation: `HTML ${tag} tag`
                            });
                        });
                    }
                    if (langCompletions.attributes) {
                        langCompletions.attributes.forEach(attr => {
                            suggestions.push({
                                label: attr,
                                kind: monaco.languages.CompletionItemKind.Field,
                                insertText: attr + '="$1"',
                                insertTextRules: monaco.languages.CompletionItemInsertTextRule.InsertAsSnippet,
                                detail: 'HTML attribute',
                                documentation: `HTML ${attr} attribute`
                            });
                        });
                    }
                }
                
                // CSS-specific
                if (langId === 'css') {
                    if (langCompletions.properties) {
                        langCompletions.properties.forEach(prop => {
                            suggestions.push({
                                label: prop,
                                kind: monaco.languages.CompletionItemKind.Property,
                                insertText: prop + ': $1;',
                                insertTextRules: monaco.languages.CompletionItemInsertTextRule.InsertAsSnippet,
                                detail: 'CSS property',
                                documentation: `CSS ${prop} property`
                            });
                        });
                    }
                    if (langCompletions.values) {
                        langCompletions.values.forEach(value => {
                            suggestions.push({
                                label: value,
                                kind: monaco.languages.CompletionItemKind.Value,
                                insertText: value,
                                detail: 'CSS value',
                                documentation: `CSS ${value} value`
                            });
                        });
                    }
                    if (langCompletions.units) {
                        langCompletions.units.forEach(unit => {
                            suggestions.push({
                                label: unit,
                                kind: monaco.languages.CompletionItemKind.Unit,
                                insertText: unit,
                                detail: 'CSS unit',
                                documentation: `CSS ${unit} unit`
                            });
                        });
                    }
                }
                
                // PHP-specific
                if (langId === 'php') {
                    if (langCompletions.functions) {
                        langCompletions.functions.forEach(func => {
                            suggestions.push({
                                label: func,
                                kind: monaco.languages.CompletionItemKind.Function,
                                insertText: func + '($1)',
                                insertTextRules: monaco.languages.CompletionItemInsertTextRule.InsertAsSnippet,
                                detail: 'PHP function',
                                documentation: `PHP ${func} function`
                            });
                        });
                    }
                    if (langCompletions.superglobals) {
                        langCompletions.superglobals.forEach(superglobal => {
                            suggestions.push({
                                label: superglobal,
                                kind: monaco.languages.CompletionItemKind.Variable,
                                insertText: superglobal,
                                detail: 'PHP superglobal',
                                documentation: `PHP ${superglobal} superglobal`
                            });
                        });
                    }
                    if (langCompletions.constants) {
                        langCompletions.constants.forEach(constant => {
                            suggestions.push({
                                label: constant,
                                kind: monaco.languages.CompletionItemKind.Constant,
                                insertText: constant,
                                detail: 'PHP constant',
                                documentation: `PHP ${constant} constant`
                            });
                        });
                    }
                }
                
                // SQL-specific
                if (langId === 'sql') {
                    if (langCompletions.functions) {
                        langCompletions.functions.forEach(func => {
                            suggestions.push({
                                label: func,
                                kind: monaco.languages.CompletionItemKind.Function,
                                insertText: func + '()',
                                detail: 'SQL function',
                                documentation: `SQL ${func} function`
                            });
                        });
                    }
                    if (langCompletions.types) {
                        langCompletions.types.forEach(type => {
                            suggestions.push({
                                label: type,
                                kind: monaco.languages.CompletionItemKind.TypeParameter,
                                insertText: type,
                                detail: 'SQL data type',
                                documentation: `SQL ${type} data type`
                            });
                        });
                    }
                }
                
                // Python-specific modules
                if (langId === 'python' && langCompletions.modules) {
                    langCompletions.modules.forEach(module => {
                        suggestions.push({
                            label: module,
                            kind: monaco.languages.CompletionItemKind.Module,
                            insertText: module,
                            detail: 'Python module',
                            documentation: `Python ${module} module`
                        });
                    });
                }
                
                monacoWithSuggestionSpaceCommand(suggestions);
                monacoCompletionCache.set(langId, suggestions);
                return { suggestions };
            }
        });
    });
}

// Add custom editor actions including Select All
function monacoAddCustomEditorActions() {
    if (!monaco.editor) return;
    
    monaco.editor.addEditorAction({
        id: 'select-all-touch',
        label: monacoT('monacoCtxSelectAll'),
        contextMenuGroupId: '9_cutcopypaste',
        contextMenuOrder: 2,
        run: function(editor) {
            const model = editor.getModel();
            if (model) {
                const fullRange = model.getFullModelRange();
                editor.setSelection(fullRange);
                editor.revealRange(fullRange);
            }
        }
    });

    if (monacoIsTouchDevice) {
        monaco.editor.addEditorAction({
            id: 'touch-select-word',
            label: monacoT('monacoCtxSelectWord'),
            contextMenuGroupId: '9_cutcopypaste',
            contextMenuOrder: 3,
            run: function(editor) {
                const position = editor.getPosition();
                const word = editor.getModel().getWordAtPosition(position);
                if (word) {
                    const range = new monaco.Range(
                        position.lineNumber, word.startColumn,
                        position.lineNumber, word.endColumn
                    );
                    editor.setSelection(range);
                    editor.revealRange(range);
                }
            }
        });
    }
}

function monacoGetAccentHex() {
    let accent = window.APP_CONFIG?.color || getComputedStyle(document.documentElement).getPropertyValue('--accent').trim() || '#4a90e2';
    if (!accent.startsWith('#')) accent = '#' + accent;
    return accent;
}

function monacoGetSelectionThemeColors() {
    const accent = monacoGetAccentHex();
    return {
        'editor.selectionBackground': accent + '40',
        'editor.inactiveSelectionBackground': accent + '20',
    };
}

// Update Monaco theme based on current app theme. The palette itself lives in
// assets/monaco-shared.js so every Monaco editor in the app looks the same.
function monacoUpdateTheme() {
    const theme = document.body.getAttribute('data-theme');
    if (window.cloudMonacoShared) {
        window.cloudMonacoShared.defineAppThemes(monaco, { accent: monacoGetAccentHex() });
    }
    monaco.editor.setTheme(theme === 'dark' ? 'custom-dark' : 'custom-light');
}

// Configure language-specific settings
function monacoSetupLanguages() {
    if (!monaco.languages) return;
    
    if (monaco.languages.typescript && monaco.languages.typescript.javascriptDefaults) {
        monaco.languages.typescript.javascriptDefaults.setDiagnosticsOptions({
            noSemanticValidation: true,
            noSyntaxValidation: false,
        });
    }
    
    try { monaco.languages.register({ id: 'python' }); } catch (e) {}
    try { monaco.languages.register({ id: 'php' }); } catch (e) {}
    try { monaco.languages.register({ id: 'sql' }); } catch (e) {}
    try { monaco.languages.register({ id: 'java' }); } catch (e) {}
    try { monaco.languages.register({ id: 'cpp' }); } catch (e) {}
    try { monaco.languages.register({ id: 'c' }); } catch (e) {}
    try { monaco.languages.register({ id: 'matlab' }); } catch (e) {}

    monacoSetupMatlabLanguage();
    monacoSetupLatexLanguage();
    
    if (monaco.languages.html && monaco.languages.html.htmlDefaults) {
        monaco.languages.html.htmlDefaults.setOptions({
            format: {
                tabSize: 2,
                insertSpaces: true,
                wrapLineLength: 120,
                unformatted: 'wbr',
                contentUnformatted: 'pre,code,textarea',
                indentInnerHtml: false,
                preserveNewLines: true,
                maxPreserveNewLines: null,
                indentHandlebars: false,
                endWithNewline: false,
                extraLiners: 'head, body, /html'
            }
        });
    }
    
    if (monaco.languages.css && monaco.languages.css.cssDefaults) {
        monaco.languages.css.cssDefaults.setOptions({
            validate: true,
            lint: {
                compatibleVendorPrefixes: 'ignore',
                vendorPrefix: 'warning',
                duplicateProperties: 'warning',
                emptyRules: 'warning',
                importStatement: 'ignore',
                boxModel: 'ignore',
                universalSelector: 'ignore',
                zeroUnits: 'ignore',
                fontFaceProperties: 'warning',
                hexColorLength: 'error',
                argumentsInColorFunction: 'error',
                unknownProperties: 'warning',
                ieHack: 'ignore',
                unknownVendorSpecificProperties: 'ignore',
                propertyIgnoredDueToDisplay: 'warning',
                important: 'ignore',
                float: 'ignore',
                idSelector: 'ignore'
            }
        });
    }

    // Enable JSON validation
    if (monaco.languages.json && monaco.languages.json.jsonDefaults) {
        monaco.languages.json.jsonDefaults.setDiagnosticsOptions({
            validate: true,
            allowComments: false,
            enableSchemaRequest: true
        });
    }
}

// MATLAB language configuration and syntax highlighting
function monacoSetupMatlabLanguage() {
    if (!monaco.languages) return;

    monaco.languages.setLanguageConfiguration('matlab', {
        comments: {
            lineComment: '%',
            blockComment: ['%{', '}%']
        },
        brackets: [
            ['{', '}'],
            ['[', ']'],
            ['(', ')']
        ],
        autoClosingPairs: [
            { open: '{', close: '}' },
            { open: '[', close: ']' },
            { open: '(', close: ')' },
            { open: '\'', close: '\'', notIn: ['string', 'comment'] },
            { open: '"', close: '"', notIn: ['string', 'comment'] }
        ],
        surroundingPairs: [
            { open: '{', close: '}' },
            { open: '[', close: ']' },
            { open: '(', close: ')' },
            { open: '\'', close: '\'' },
            { open: '"', close: '"' }
        ],
        wordPattern: /(-?\d*\.\d\w*)|([^\`\~\!\@\#\$\%\^\&\*\(\)\-\=\+\[\{\]\}\\\|\;\:\'\"\,\.\<\>\/\?\s]+)/g
    });

    monaco.languages.setMonarchTokensProvider('matlab', {
        defaultToken: '',
        tokenPostfix: '.matlab',

        keywords: [
            'break', 'case', 'catch', 'continue', 'else', 'elseif', 'end',
            'for', 'function', 'global', 'if', 'otherwise', 'parfor',
            'persistent', 'return', 'spmd', 'switch', 'try', 'while',
            'classdef', 'properties', 'methods', 'events'
        ],

        builtins: [
            // Math
            'abs', 'acos', 'acosh', 'asin', 'asinh', 'atan', 'atan2', 'atanh',
            'ceil', 'cos', 'cosh', 'cot', 'exp', 'fix', 'floor', 'log', 'log10',
            'log2', 'max', 'min', 'mod', 'round', 'sign', 'sin', 'sinh', 'sqrt',
            'sum', 'tan', 'tanh', 'mean', 'median', 'std', 'var',
            // Linear algebra
            'det', 'eig', 'inv', 'norm', 'rank', 'trace', 'transpose', 'ctranspose',
            'eye', 'zeros', 'ones', 'diag', 'tril', 'triu', 'linspace', 'logspace',
            // I/O
            'disp', 'display', 'fprintf', 'fscanf', 'fopen', 'fclose', 'fgetl',
            'fgets', 'fprintf', 'fread', 'fwrite', 'load', 'save', 'input',
            // String
            'sprintf', 'sscanf', 'strcat', 'strcmp', 'strcmpi', 'strfind',
            'strlen', 'strrep', 'strstr', 'strtok', 'num2str', 'int2str',
            'mat2str', 'sprintfc', 'cellstr',
            // Array/matrix
            'size', 'length', 'numel', 'reshape', 'reshape', 'repmat', 'flip',
            'rot90', 'sort', 'find', 'cumsum', 'cumprod', 'diff', 'unique',
            'ismember', 'intersect', 'union', 'setdiff', 'setxor',
            // Plotting
            'plot', 'plot3', 'scatter', 'bar', 'hist', 'pie', 'contour',
            'surf', 'mesh', 'imagesc', 'imshow', 'figure', 'subplot',
            'title', 'xlabel', 'ylabel', 'zlabel', 'legend', 'grid',
            'axis', 'hold', 'cla', 'clf', 'close', 'colormap', 'colorbar',
            // General
            'exist', 'isnumeric', 'ischar', 'islogical', 'iscell', 'isstruct',
            'isempty', 'isinf', 'isnan', 'isprime', 'isa', 'class', 'whos',
            'who', 'clear', 'clc', 'help', 'doc', 'tic', 'toc', 'pause',
            'clock', 'date', 'now', 'datenum', 'datestr', 'datevec',
            // FFT / signal processing
            'fft', 'ifft', 'fft2', 'ifft2', 'fftn', 'ifftn', 'filter',
            'conv', 'deconv', 'xcorr', 'xcov',
            // Optimization / solvers
            'fzero', 'fsolve', 'fminsearch', 'fminbnd', 'fmincon', 'ode45',
            'ode23', 'ode113', 'ode15s', 'bvp4c',
            // Cell/struct
            'cell', 'cell2mat', 'cellfun', 'struct', 'structfun', 'fieldnames',
            'isfield', 'getfield', 'setfield', 'rmfield',
            // Random
            'rand', 'randi', 'randn', 'randperm', 'rng', 'randstream'
        ],

        operators: [
            '+', '-', '*', '/', '\\', '^', "'",
            '.+', '.-', '.*', './', '.\\', '.^', ".'",
            '==', '~=', '>', '<', '>=', '<=',
            '&&', '||', '~', '&', '|',
            ':', '...', ';', ',', '@'
        ],

        symbols: /[=><!~&|+\-*\/\^@]+/,

        escapes: /\\(?:[abfnrtv\\"']|x\h{2}|[0-7]{3}|u\{[0-9a-fA-F]+\})/,

        tokenizer: {
            root: [
                // Block comments %{ ... }%
                [/%\{/, { token: 'comment.block.open', next: '@blockComment' }],

                // Line comments
                [/%.*$/, 'comment'],

                // Strings (single quotes)
                [/'/, { token: 'string', next: '@stringSq' }],

                // Strings (double quotes)
                [/"/, { token: 'string', next: '@stringDq' }],

                // Numbers
                [/\b\d+(\.\d+)?([eE][+-]?\d+)?[ij]?\b/, 'number'],
                [/\b(pi|eps|inf|Inf|NaN|nan|i|j|true|false)\b/, 'number'],

                // Keywords
                [/\b(function|end|if|else|elseif|for|while|switch|case|otherwise|try|catch|return|break|continue|global|persistent|parfor|spmd|classdef|properties|methods|events)\b/, 'keyword'],

                // Built-in functions
                [/\b(abs|acos|acosh|asin|asinh|atan|atan2|atanh|ceil|cos|cosh|cot|exp|fix|floor|log|log10|log2|max|min|mod|round|sign|sin|sinh|sqrt|sum|tan|tanh|mean|median|std|var|det|eig|inv|norm|rank|trace|transpose|ctranspose|eye|zeros|ones|diag|tril|triu|linspace|logspace|disp|display|fprintf|fscanf|fopen|fclose|fgetl|fgets|fread|fwrite|load|save|input|sprintf|sscanf|strcat|strcmp|strcmpi|strfind|strlen|strrep|strstr|num2str|int2str|mat2str|size|length|numel|reshape|repmat|flip|rot90|sort|find|cumsum|cumprod|diff|unique|ismember|intersect|union|setdiff|setxor|plot|plot3|scatter|bar|hist|pie|contour|surf|mesh|imagesc|imshow|figure|subplot|title|xlabel|ylabel|zlabel|legend|grid|axis|hold|cla|clf|close|colormap|colorbar|exist|isnumeric|ischar|islogical|iscell|isstruct|isempty|isinf|isnan|isprime|isa|class|whos|who|clear|clc|help|doc|tic|toc|pause|clock|date|now|datenum|datestr|datevec|fft|ifft|fft2|ifft2|fftn|ifftn|filter|conv|deconv|xcorr|xcov|fzero|fsolve|fminsearch|fminbnd|fmincon|ode45|ode23|ode113|ode15s|bvp4c|cell|cell2mat|cellfun|struct|structfun|fieldnames|isfield|getfield|setfield|rmfield|rand|randi|randn|randperm|rng|randstream)\b/, 'function'],

                // Identifiers
                [/[a-zA-Z_]\w*/, {
                    cases: {
                        '@keywords': 'keyword',
                        '@builtins': 'function',
                        '@default': 'identifier'
                    }
                }],

                // Operators
                [/@symbols/, {
                    cases: {
                        '@operators': 'operator',
                        '@default': ''
                    }
                }],

                // Whitespace
                { include: '@whitespace' }
            ],

            whitespace: [
                [/[ \t\r\n]+/, 'white']
            ],

            blockComment: [
                [/[^%]+/, 'comment'],
                [/}%/, { token: 'comment.block.close', next: '@pop' }],
                [/%/, 'comment']
            ],

            stringSq: [
                [/[^']+/, 'string'],
                [/''/, 'string.escape'],
                [/'/, { token: 'string', next: '@pop' }]
            ],

            stringDq: [
                [/[^"]+/, 'string'],
                [/""/, 'string.escape'],
                [/"/, { token: 'string', next: '@pop' }]
            ]
        }
    });
}

// LaTeX language configuration and syntax highlighting
function monacoSetupLatexLanguage() {
    if (!monaco.languages) return;
    try { monaco.languages.register({ id: 'latex' }); } catch (e) {}
    monaco.languages.setMonarchTokensProvider('latex', {
        tokenizer: {
            root: [
                [/(\\documentclass|\\usepackage|\\begin|\\end|\\title|\\author|\\date|\\maketitle)/, { token: 'keyword' }],
                [/\\(section|subsection|subsubsection|chapter|part|label|ref|cite|pageref)/, { token: 'type' }],
                [/\\(textbf|textit|underline|emph|footnote|item)/, { token: 'function' }],
                [/\\(table|figure|caption|centering|includegraphics|includepdf|addcontentsline)/, { token: 'class' }],
                [/\\(hline|multicolumn|multirow|hfill|vfill|hspace|vspace|bigskip|medskip|smallskip)/, { token: 'type' }],
                [/\\(begin|end)\s*\{[^}]*\}/, { token: 'class' }],
                [/\\(itemize|enumerate|description|quote|quotation|verse|center|flushleft|flushright|minipage|tabular)/, { token: 'type' }],
                [/\\(equation|align|gather|multline|split|cases|matrix|pmatrix|bmatrix|vmatrix|Vmatrix)/, { token: 'class' }],
                [/\\(theorem|lemma|proof|definition|example|remark)/, { token: 'class' }],
                [/\\(frac|sqrt|sum|int|prod|lim|log|ln|exp|sin|cos|tan|cot|sec|csc|arcsin|arccos|arctan|sinh|cosh|tanh|coth|max|min|inf|sup|det|dim|hom|ker|Pr)/, { token: 'function' }],
                [/\\([a-zA-Z]+)/, { token: 'keyword' }],
                [/\\%/, { token: 'constant.character.escape' }],
                [/(?<!\\)%.*$/, { token: 'comment' }],
                [/\d+(\.\d+)?/, { token: 'number' }],
                [/\{|\}|\[|\]|\(|\)/, { token: 'delimiter.bracket' }],
                [/[$&]/, { token: 'operator' }],
                [/[a-zA-Z]+/, { token: 'text' }],
            ],
        }
    });
}

// Touch toolbar with Copy / Paste / Select All for edit-mode editors
function monacoCreateTouchToolbar(editor, container) {
    return null;
}

function monacoIsTxtFile(filePath) {
    return /\.txt$/i.test(filePath || '');
}

// Ordered-list continuation for prose files. The behaviour lives in
// assets/monaco-shared.js so that every Monaco editor shares it.
function monacoSetupTxtNumberedListContinuation(editor, filePath) {
    if (!window.cloudMonacoShared?.installNumberedListContinuation) return;
    monacoAddEditorDisposable(
        editor,
        window.cloudMonacoShared.installNumberedListContinuation({ editor, monaco, filePath })
    );
}

function monacoGetDefaultSpellcheckLanguage() {
    const saved = localStorage.getItem('monacoSpellcheckLanguage');
    if (saved === 'off') return 'off';
    if (saved && monacoSpellcheckLanguages[saved]) return saved;
    return 'auto';
}

function monacoIsSpellcheckDisabled(languageId) {
    return languageId === 'off';
}

function monacoHexToRgb(hex) {
    const normalized = (hex || '').replace('#', '').trim();
    if (!/^[0-9a-f]{6}$/i.test(normalized)) return null;
    const value = parseInt(normalized, 16);
    return {
        r: (value >> 16) & 255,
        g: (value >> 8) & 255,
        b: value & 255
    };
}

function monacoGetStatusBarColors(isDark) {
    const accent = window.APP_CONFIG?.color || getComputedStyle(document.documentElement).getPropertyValue('--accent').trim() || '#4a90e2';
    const rgb = monacoHexToRgb(accent);
    if (isDark) {
        return {
            background: '#3a3a3a',
            foreground: '#f1f1f1',
            itemBackground: 'rgba(255,255,255,0.08)',
            itemBorder: 'rgba(255,255,255,0.18)'
        };
    }

    return {
        background: rgb ? `rgba(${rgb.r},${rgb.g},${rgb.b},0.16)` : 'color-mix(in srgb, var(--accent) 16%, transparent)',
        foreground: accent,
        itemBackground: rgb ? `rgba(${rgb.r},${rgb.g},${rgb.b},0.12)` : 'color-mix(in srgb, var(--accent) 12%, transparent)',
        itemBorder: rgb ? `rgba(${rgb.r},${rgb.g},${rgb.b},0.32)` : 'color-mix(in srgb, var(--accent) 32%, transparent)'
    };
}

function monacoCreateSpellcheckWorker() {
    const typoUrl = window.location.origin + window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/') + 1) + 'assets/dictionaries/typo.js';
    const dictBaseUrl = window.location.origin + window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/') + 1);
    const workerSource = `
        let TypoCtor = null;
        const dictionaries = {};
        let supplementalWords = null;
        const typoUrl = '${typoUrl}';
        const dictBaseUrl = '${dictBaseUrl}';
        function dictUrl(relative) { return dictBaseUrl + relative; }
        function ensureTypo() {
            if (!TypoCtor) {
                importScripts(typoUrl);
                TypoCtor = self.Typo;
            }
            if (!TypoCtor) throw new Error('Typo.js failed to load');
        }
        function ensureSupplemental() {
            if (supplementalWords) return Promise.resolve(supplementalWords);
            return fetch(dictUrl('assets/dictionaries/supplemental.txt'), { cache: 'force-cache' })
                .then((response) => response.ok ? response.text() : '')
                .then((text) => {
                    supplementalWords = new Set(
                        String(text || '').split(/\\r?\\n/)
                            .map((line) => line.replace(/#.*/, '').trim().toLowerCase())
                            .filter((word) => word.length > 1)
                    );
                    return supplementalWords;
                })
                .catch(() => {
                    supplementalWords = new Set();
                    return supplementalWords;
                });
        }
        function isSupplementalWord(word) {
            return supplementalWords && supplementalWords.has(String(word || '').toLowerCase());
        }
        function skipWord(word) {
            return word.length < 2 ||
                /^\\d/.test(word) ||
                /^[\\p{Lu}]$/u.test(word) ||
                /^[A-Z0-9_]+$/.test(word) ||
                /[_/@.]/.test(word) ||
                /^https?:/i.test(word);
        }
        function wordLanguageIds(word, selectedLanguageId) {
            if (selectedLanguageId !== 'auto') return [selectedLanguageId];
            if (/[A-Za-z]/.test(word)) return ['en_US'];
            if (/[ёъыэ]/i.test(word)) return ['ru_RU'];
            if (/[ґєії]/i.test(word)) return ['uk_UA', 'ru_RU', 'be_BY'];
            if (/[ў]/i.test(word)) return ['be_BY', 'ru_RU', 'uk_UA'];
            if (/[\\u0400-\\u04FF]/.test(word)) return ['ru_RU', 'uk_UA', 'be_BY'];
            return ['en_US'];
        }
        function ensureDictionary(languageId, language) {
            if (dictionaries[languageId]) return Promise.resolve(dictionaries[languageId]);
            ensureTypo();
            return Promise.all([
                fetch(dictUrl(language.aff), { cache: 'force-cache' }),
                fetch(dictUrl(language.dic), { cache: 'force-cache' })
            ]).then(([affResponse, dicResponse]) => {
                if (!affResponse.ok || !dicResponse.ok) throw new Error('Failed to load dictionary ' + languageId);
                return Promise.all([affResponse.text(), dicResponse.text()]);
            }).then(([affData, dicData]) => {
                dictionaries[languageId] = new TypoCtor(languageId, affData, dicData);
                return dictionaries[languageId];
            });
        }
        self.onmessage = async (event) => {
            const data = event.data || {};
            try {
                if (data.type === 'load') {
                    ensureTypo();
                    await ensureSupplemental();
                    if (data.languageId !== 'auto') {
                        await ensureDictionary(data.languageId, data.languages[data.languageId]);
                    }
                    self.postMessage({ type: 'ready', requestId: data.requestId, languageId: data.languageId });
                    return;
                }
                if (data.type === 'preload') {
                    ensureTypo();
                    await ensureSupplemental();
                    for (const languageId of data.languageIds || []) {
                        if (data.languages[languageId]) await ensureDictionary(languageId, data.languages[languageId]);
                    }
                    return;
                }
                if (data.type === 'check') {
                    const lines = String(data.text || '').split(/\\r\\n|\\r|\\n/);
                    const wordRegex = /[\\p{L}\\p{M}]+(?:['-][\\p{L}\\p{M}]+)*/gu;
                    const errors = [];
                    const suggestionsMap = {};
                    const maxErrors = data.maxErrors || 200;
                    await ensureSupplemental();
                    const loadedLanguageIds = new Set();
                    for (let i = 0; i < lines.length && errors.length < maxErrors; i++) {
                        const line = lines[i];
                        let match;
                        while ((match = wordRegex.exec(line)) !== null && errors.length < maxErrors) {
                            const word = match[0];
                            if (match.index > 0 && line[match.index - 1] === '.') continue;
                            // Inside this template literal the backslash must be doubled, otherwise the
                            // template unescape turns /^\.[a-z]{2,}$/ into /^.[a-z]{2,}$/ — a pattern that
                            // matches every all-letter word and silently skips the whole dictionary.
                            if (/^\\.[a-z]{2,}$/i.test(word)) continue;
                            if (skipWord(word)) continue;
                            if (isSupplementalWord(word)) continue;
                            const languageIds = wordLanguageIds(word, data.languageId);
                            if (languageIds.length === 0) continue;
                            let isCorrect = false;
                            for (let languageIndex = 0; languageIndex < languageIds.length; languageIndex++) {
                                const languageId = languageIds[languageIndex];
                                if (languageIndex > 0 && !dictionaries[languageId]) continue;
                                if (!loadedLanguageIds.has(languageId)) {
                                    await ensureDictionary(languageId, data.languages[languageId]);
                                    loadedLanguageIds.add(languageId);
                                }
                                const dictionary = dictionaries[languageId];
                                if (dictionary && dictionary.check(word)) {
                                    isCorrect = true;
                                    break;
                                }
                            }
                            if (!isCorrect) {
                                errors.push({
                                    word,
                                    startLineNumber: i + 1,
                                    startColumn: match.index + 1,
                                    endLineNumber: i + 1,
                                    endColumn: match.index + word.length + 1
                                });
                            }
                        }
                    }
                    self.postMessage({ type: 'checked', requestId: data.requestId, versionId: data.versionId, errors, suggestions: suggestionsMap });
                    return;
                }
                if (data.type === 'suggest') {
                    const languageIds = wordLanguageIds(data.word, data.languageId);
                    const suggestions = [];
                    for (const languageId of languageIds) {
                        const dictionary = await ensureDictionary(languageId, data.languages[languageId]);
                        suggestions.push(...dictionary.suggest(data.word).slice(0, 5));
                        if (suggestions.length >= 5) break;
                    }
                    self.postMessage({
                        type: 'suggestions',
                        requestId: data.requestId,
                        word: data.word,
                        suggestions: Array.from(new Set(suggestions)).slice(0, 5)
                    });
                }
            } catch (error) {
                self.postMessage({ type: 'error', requestId: data.requestId, message: error.message || String(error) });
            }
        };
    `;
    const workerUrl = URL.createObjectURL(new Blob([workerSource], { type: 'application/javascript' }));
    const worker = new Worker(workerUrl);
    URL.revokeObjectURL(workerUrl);
    return worker;
}

// Returns the single shared spellcheck worker, creating it on first use. Keeping
// one worker means its dictionary cache is shared by every open editor.
function monacoGetSpellcheckWorker() {
    if (monacoSpellcheckSharedWorker) return monacoSpellcheckSharedWorker;
    const worker = monacoCreateSpellcheckWorker();
    worker.onmessage = monacoHandleSpellcheckMessage;
    monacoSpellcheckSharedWorker = worker;
    return worker;
}

function monacoPreloadSpellcheckDictionaries() {
    const saved = monacoGetDefaultSpellcheckLanguage();
    if (saved === 'off') return;
    const appLang = new URLSearchParams(window.location.search).get('lang') || document.documentElement.lang || 'en';
    const byAppLang = { ru: 'ru_RU', uk: 'uk_UA', be: 'be_BY', pl: 'pl_PL', cs: 'cs_CZ' };
    const preferred = saved === 'auto' ? ['en_US', byAppLang[String(appLang).slice(0, 2)]] : [saved];
    const languageIds = Array.from(new Set(preferred.filter((id) => id && monacoSpellcheckLanguages[id])));
    if (!languageIds.length) return;
    monacoGetSpellcheckWorker().postMessage({ type: 'preload', languageIds, languages: monacoSpellcheckLanguagesPayload() });
}
(window.requestIdleCallback || ((callback) => setTimeout(callback, 800)))(() => {
    try { monacoPreloadSpellcheckDictionaries(); } catch (_) {}
}, { timeout: 4000 });

function monacoNextSpellcheckRequestId() {
    return ++monacoSpellcheckRequestSeq;
}

// Routes a worker response to the editor that issued the request.
function monacoHandleSpellcheckMessage(event) {
    const data = event.data || {};
    if (data.requestId == null) return;
    const route = monacoSpellcheckRoutes.get(data.requestId);
    if (!route) return;
    const state = route.state;

    if (data.type === 'ready') {
        monacoSpellcheckRoutes.delete(data.requestId);
        state.ready = true;
        const label = state.languageId === 'auto'
            ? monacoT('monacoSpellAuto')
            : (monacoSpellcheckLanguages[state.languageId]?.label || state.languageId);
        if (state.statusElement) state.statusElement.textContent = `${monacoT('monacoSpellLanguage')} ${label}`;
        if (state.editor) monacoRunSpellcheck(state.editor);
        return;
    }
    if (data.type === 'checked') {
        monacoSpellcheckRoutes.delete(data.requestId);
        const model = state.model;
        if (!model) return;
        if (data.requestId !== state.latestCheckRequestId || data.versionId !== model.getVersionId()) return;
        Object.keys(data.suggestions || {}).forEach((word) => {
            monacoRememberSuggestion(state, word, data.suggestions[word]);
        });
        if (state.editor) monacoApplySpellcheckDecorations(state.editor, data.errors || []);
        return;
    }
    if (data.type === 'suggestions') {
        monacoSpellcheckRoutes.delete(data.requestId);
        monacoRememberSuggestion(state, data.word, data.suggestions || []);
        const pending = state.pendingSuggestions.get(data.requestId);
        if (pending) {
            state.pendingSuggestions.delete(data.requestId);
            pending.resolve(data.suggestions || []);
        }
        return;
    }
    if (data.type === 'error') {
        monacoSpellcheckRoutes.delete(data.requestId);
        console.error('Spellcheck failed:', data.message);
        if (state.statusElement) state.statusElement.textContent = monacoT('monacoSpellcheckUnavailable');
        const pending = state.pendingSuggestions.get(data.requestId);
        if (pending) {
            state.pendingSuggestions.delete(data.requestId);
            pending.resolve([]);
        }
    }
}

function monacoFindSpellcheckStateByModel(model) {
    return Array.from(monacoSpellcheckStates.values()).find(state => state.model === model);
}

function monacoRangesIntersect(a, b) {
    return a.startLineNumber <= b.endLineNumber &&
           a.endLineNumber >= b.startLineNumber &&
           a.startColumn <= b.endColumn &&
           a.endColumn >= b.startColumn;
}

function monacoGetSpellcheckErrorAtRange(state, range) {
    if (!state || !state.errors) return null;
    return state.errors.find(error => monacoRangesIntersect(error, range));
}

function monacoRequestSpellcheckSuggestions(state, word) {
    if (!state || !state.worker || !word) return Promise.resolve([]);
    if (state.suggestionCache.has(word)) return Promise.resolve(state.suggestionCache.get(word));

    const requestId = monacoNextSpellcheckRequestId();
    return new Promise((resolve) => {
        state.pendingSuggestions.set(requestId, { resolve, word });
        monacoSpellcheckRoutes.set(requestId, { state, kind: 'suggest' });
        monacoGetSpellcheckWorker().postMessage({
            type: 'suggest',
            requestId,
            word,
            languageId: state.languageId,
            languages: monacoSpellcheckLanguagesPayload()
        });
    });
}

function monacoRememberSuggestion(state, word, suggestions) {
    if (!state || !word) return;
    state.suggestionCache.set(word, suggestions || []);
    if (state.suggestionCache.size > 300) {
        const oldestKey = state.suggestionCache.keys().next().value;
        if (oldestKey) state.suggestionCache.delete(oldestKey);
    }
}

function monacoApplySpellcheckDecorations(editor, errors) {
    const state = monacoSpellcheckStates.get(editor);
    if (!state) return;
    state.errors = errors || [];
    const model = editor.getModel();

    const decorations = state.errors.map(error => ({
        range: new monaco.Range(
            error.startLineNumber,
            error.startColumn,
            error.endLineNumber,
            error.endColumn
        ),
        options: {
            stickiness: monaco.editor.TrackedRangeStickiness.NeverGrowsWhenTypingAtEdges
        }
    }));
    state.decorationIds = editor.deltaDecorations(state.decorationIds || [], decorations);

    // Register markers so Monaco knows about issues at cursor position.
    // This enables the lightbulb (💡) and auto-triggers provideCodeActions
    // when the cursor lands on a misspelled word.
    if (model) {
        const markers = state.errors.map(error => ({
            severity: monaco.MarkerSeverity.Warning,
            message: `Spelling suggestion available for "${error.word}"`,
            startLineNumber: error.startLineNumber,
            startColumn: error.startColumn,
            endLineNumber: error.endLineNumber,
            endColumn: error.endColumn
        }));
        monaco.editor.setModelMarkers(model, 'spellcheck', markers);
    }
}

function monacoRunSpellcheck(editor) {
    const model = editor.getModel();
    const state = monacoSpellcheckStates.get(editor);
    if (!model || !state || !state.enabled || !state.ready || !state.worker) return;

    const requestId = monacoNextSpellcheckRequestId();
    state.latestCheckRequestId = requestId;
    state.latestCheckVersionId = model.getVersionId();
    monacoSpellcheckRoutes.set(requestId, { state, kind: 'check' });
    monacoGetSpellcheckWorker().postMessage({
        type: 'check',
        requestId,
        versionId: state.latestCheckVersionId,
        text: model.getValue(),
        languageId: state.languageId,
        languages: monacoSpellcheckLanguagesPayload(),
        maxErrors: monacoSpellcheckMaxErrors
    });
}

function monacoScheduleSpellcheck(editor) {
    const state = monacoSpellcheckStates.get(editor);
    if (!state) return;
    clearTimeout(state.timer);
    state.timer = setTimeout(() => monacoRunSpellcheck(editor), monacoSpellcheckDebounceMs);
}
function monacoRefreshFooterStatusBar() {
    const slot = document.getElementById('status-editor-slot');
    if (!slot) return;
    const active = window.tabsState && window.tabsState.activeTabId;
    slot.replaceChildren();
    if (active != null && monacoFooterStatusEls.has(active)) {
        slot.appendChild(monacoFooterStatusEls.get(active));
        slot.classList.remove('hidden');
    } else {
        slot.classList.add('hidden');
    }
}

// ── Trailing space after an accepted suggestion ────────────────────────────
// Suggestion lists (the .txt spellcheck quick fix and the completion list) insert
// the chosen word under the caret. When that word ends up at the end of the text
// there is nothing to separate it from whatever the user types next, so a space is
// appended — but only there: in the middle of a sentence the following text already
// provides the separation. That appended space is remembered with a tracked
// decoration, so a punctuation mark typed right after the suggestion glues itself
// to the word (the decoration's space is dropped again).
let monacoSuggestionSpaceRegistered = false;
let monacoAutoSpaceState = null; // { editor, collection, listener }

function monacoRegisterSuggestionTrailingSpace() {
    if (monacoSuggestionSpaceRegistered) return;
    if (!monaco.editor || typeof monaco.editor.registerCommand !== 'function') return;
    monacoSuggestionSpaceRegistered = true;
    monaco.editor.registerCommand(monacoSuggestionSpaceCommandId, () => {
        monacoAppendSuggestionTrailingSpace();
    });
}

function monacoEditorWithTextFocus() {
    const editors = typeof monaco.editor.getEditors === 'function' ? monaco.editor.getEditors() : [];
    return editors.find((editor) => typeof editor.hasTextFocus === 'function' && editor.hasTextFocus())
        || monacoEditors.get(window.tabsState && window.tabsState.activeTabId)
        || editors[0]
        || null;
}

function monacoClearAutoSpace() {
    const state = monacoAutoSpaceState;
    monacoAutoSpaceState = null;
    if (!state) return;
    try { state.collection.clear(); } catch (_) {}
    try { state.listener.dispose(); } catch (_) {}
}

function monacoTrackAutoSpace(editor, model, spaceOffset) {
    monacoClearAutoSpace();
    if (typeof editor.createDecorationsCollection !== 'function') return;
    const start = model.getPositionAt(spaceOffset);
    const end = model.getPositionAt(spaceOffset + 1);
    const collection = editor.createDecorationsCollection([{
        range: new monaco.Range(start.lineNumber, start.column, end.lineNumber, end.column),
        options: {
            description: 'cloudSuggestionAutoSpace',
            stickiness: monaco.editor.TrackedRangeStickiness.NeverGrowsWhenTypingAtEdges
        }
    }]);
    const listener = editor.onDidChangeModelContent((event) => {
        monacoAbsorbAutoSpacePunctuation(editor, event);
    });
    monacoAutoSpaceState = { editor, collection, listener };
}

// Drops the auto inserted space when the character typed right after it is
// punctuation, so the mark sits against the word ("word," not "word ,").
function monacoAbsorbAutoSpacePunctuation(editor, event) {
    const state = monacoAutoSpaceState;
    if (!state || state.editor !== editor) return;
    if (event.isFlush || !event.changes || event.changes.length !== 1) return;
    const change = event.changes[0];
    if (change.rangeLength !== 0 || change.text.length !== 1) return;
    if (!monacoStickyPunctuation.test(change.text)) return;
    const model = editor.getModel();
    if (!model) return;
    const range = state.collection.getRange(0);
    if (!range) {
        monacoClearAutoSpace();
        return;
    }
    const spaceEndOffset = model.getOffsetAt({ lineNumber: range.endLineNumber, column: range.endColumn });
    if (spaceEndOffset !== change.rangeOffset) return;
    // Only while the punctuation is the last thing on the line: otherwise the
    // auto space separates two words that must stay apart.
    const punctuationPosition = model.getPositionAt(change.rangeOffset);
    if (model.getLineContent(punctuationPosition.lineNumber).length !== punctuationPosition.column) return;
    try {
        editor.executeEdits('suggestion-auto-space-punctuation', [{
            range: monaco.Range.fromPositions(
                { lineNumber: range.startLineNumber, column: range.startColumn },
                { lineNumber: range.endLineNumber, column: range.endColumn }
            ),
            text: ''
        }]);
    } catch (_) {
        // Editing inside a content change event is not allowed everywhere; leaving
        // the space in place is an acceptable fallback.
    } finally {
        monacoClearAutoSpace();
    }
}

function monacoAppendSuggestionTrailingSpace(editorOverride) {
    const editor = editorOverride || monacoEditorWithTextFocus();
    if (!editor || typeof editor.executeEdits !== 'function') return;
    const model = editor.getModel();
    const selection = editor.getSelection();
    if (!model || !selection || !selection.isEmpty()) return;
    const position = selection.getPosition();
    if (!position || position.column <= 1) return;
    // Not in the middle of a sentence: only when no text follows the caret —
    // neither on this line nor anywhere below it.
    const tail = model.getValue().slice(model.getOffsetAt(position));
    if (/\S/.test(tail)) return;
    const lineContent = model.getLineContent(position.lineNumber);
    const beforeCaret = lineContent.charAt(position.column - 2);
    if (!beforeCaret || /\s/.test(beforeCaret)) return;
    const offset = model.getOffsetAt(position);
    editor.executeEdits('suggestion-trailing-space', [{
        range: new monaco.Range(position.lineNumber, position.column, position.lineNumber, position.column),
        text: ' '
    }]);
    const afterSpace = model.getPositionAt(offset + 1);
    editor.setPosition(afterSpace);
    monacoTrackAutoSpace(editor, model, offset);
}

// Accepting a word from a suggestion list — the completion popup or the spelling
// quick fix — inserts a whole word in a single edit, while typing always arrives
// one character at a time. So a word shaped insert that ends right under the caret
// is treated as an accepted suggestion and gets the trailing space when the text
// ends there. This covers every provider (our own completions, the built-in word
// and type suggestions, the spellcheck quick fix), not just the ones we register.
function monacoNoteSuggestionAcceptance(editor, event) {
    if (!event || event.isFlush || event.isUndoing || event.isRedoing) return;
    if (!event.changes || event.changes.length !== 1) return;
    const change = event.changes[0];
    const inserted = change.text;
    // One line only, and a real word: snippets, list markers and newlines are out.
    if (!inserted || inserted.length < 2 || /\r|\n/.test(inserted)) return;
    if (!/^[\p{L}][\p{L}\p{M}\d_']*$/u.test(inserted)) return;
    const model = editor.getModel();
    if (!model) return;
    // The word starts at a word boundary: it replaced the typed prefix instead of
    // extending a word that was already there.
    const before = change.rangeOffset > 0 ? model.getValue().charAt(change.rangeOffset - 1) : '';
    if (before && /[\p{L}\p{M}\d_']/u.test(before)) return;
    // The caret lands right after the inserted word; check that once Monaco has
    // finished applying the edit (the selection is only updated afterwards).
    const endPosition = model.getPositionAt(change.rangeOffset + inserted.length);
    setTimeout(() => {
        if (editor.getModel() !== model) return;
        const position = editor.getPosition();
        if (!position || position.lineNumber !== endPosition.lineNumber || position.column !== endPosition.column) return;
        monacoAppendSuggestionTrailingSpace(editor);
    }, 0);
}

function monacoRegisterSpellcheckCodeActions() {
    if (monacoSpellcheckCodeActionsRegistered || !monaco.languages) return;
    monacoSpellcheckCodeActionsRegistered = true;

    const quickFixKind = monaco.languages.CodeActionKind ? monaco.languages.CodeActionKind.QuickFix : 'quickfix';
    monaco.languages.registerCodeActionProvider('plaintext', {
        provideCodeActions(model, range, context, token) {
            const state = monacoFindSpellcheckStateByModel(model);
            if (!state || !state.errors) return { actions: [], dispose: () => {} };

            // Find error at cursor/range position
            let error = monacoGetSpellcheckErrorAtRange(state, range);

            // Fallback: if range intersection fails, find error by word at cursor position
            if (!error) {
                const pos = { lineNumber: range.startLineNumber, column: range.startColumn };
                const word = model.getWordAtPosition(pos);
                if (word) {
                    error = state.errors.find(e =>
                        e.startLineNumber === pos.lineNumber &&
                        e.startColumn === word.startColumn &&
                        e.endColumn === word.endColumn
                    );
                }
            }

            if (!error) return { actions: [], dispose: () => {} };

            // Helper to build actions from suggestions
        const buildActions = (suggestions) => ({
            actions: suggestions.map((suggestion, index) => ({
                    title: suggestion,
                    kind: quickFixKind,
                    edit: {
                        edits: [{
                            resource: model.uri,
                            textEdit: {
                                range: new monaco.Range(
                                    error.startLineNumber,
                                    error.startColumn,
                                    error.endLineNumber,
                                    error.endColumn
                                ),
                                text: suggestion
                            }
                        }]
                    },
                    isPreferred: index === 0,
                    // Runs after the edit: adds a trailing space when the corrected
                    // word ends up at the end of the text.
                    command: { id: monacoSuggestionSpaceCommandId, title: '' }
                })),
                dispose: () => {}
            });

            // If suggestions are cached, return synchronously to avoid Canceled error
            if (state.suggestionCache.has(error.word)) {
                return buildActions(state.suggestionCache.get(error.word));
            }

            // Otherwise request from worker with CancellationToken support
            return new Promise(resolve => {
                let cancelled = false;
                if (token && typeof token.onCancellationRequested === 'function') {
                    token.onCancellationRequested(() => { cancelled = true; });
                }
                monacoRequestSpellcheckSuggestions(state, error.word)
                    .then(suggestions => {
                        if (!cancelled) resolve(buildActions(suggestions));
                    })
                    .catch(() => {
                        if (!cancelled) resolve({ actions: [], dispose: () => {} });
                    });
            });
        }
    });

    monaco.languages.registerHoverProvider('plaintext', {
        provideHover(model, position) {
            const state = monacoFindSpellcheckStateByModel(model);
            if (!state || !state.errors) return null;

            const hoverRange = new monaco.Range(position.lineNumber, position.column, position.lineNumber, position.column);
            let error = monacoGetSpellcheckErrorAtRange(state, hoverRange);

            // Fallback: find error by word at hover position
            if (!error) {
                const word = model.getWordAtPosition(position);
                if (word) {
                    error = state.errors.find(e =>
                        e.startLineNumber === position.lineNumber &&
                        e.startColumn === word.startColumn &&
                        e.endColumn === word.endColumn
                    );
                }
            }

            if (!error) return null;

            const showSuggestions = (suggestions) => ({
                range: new monaco.Range(error.startLineNumber, error.startColumn, error.endLineNumber, error.endColumn),
                contents: [{
                    value: suggestions.length
                        ? `**Spelling mistake:** "${error.word}"\n\n${suggestions.map((s, i) => `${i + 1}. ${s}`).join('\n')}`
                        : `**Spelling mistake:** "${error.word}"`
                }]
            });

            // Return cached suggestions synchronously if available
            const cached = state.suggestionCache.get(error.word);
            if (cached) {
                return showSuggestions(cached);
            }
            return monacoRequestSpellcheckSuggestions(state, error.word)
                .then(showSuggestions)
                .catch(() => null);
        }
    });
}

function monacoDisposeSpellcheck(editor) {
    const state = monacoSpellcheckStates.get(editor);
    if (!state) return;
    clearTimeout(state.timer);
    if (state.contentDisposable) state.contentDisposable.dispose();
    state.pendingSuggestions?.forEach(pending => pending.resolve([]));
    state.pendingSuggestions?.clear();
    // Drop this editor's in-flight requests but keep the shared worker (and its
    // loaded dictionaries) alive for the other open editors.
    monacoSpellcheckRoutes.forEach((route, requestId) => {
        if (route.state === state) monacoSpellcheckRoutes.delete(requestId);
    });
    if (state.decorationIds?.length) editor.deltaDecorations(state.decorationIds, []);
    // Clear spellcheck markers
    const model = editor.getModel();
    if (model) monaco.editor.setModelMarkers(model, 'spellcheck', []);
    monacoSpellcheckStates.delete(editor);
}

async function monacoEnableSpellcheck(editor, container, statusElement, languageId) {
    const model = editor.getModel();
    if (!model) return;

    monacoDisposeSpellcheck(editor);

    if (monacoIsSpellcheckDisabled(languageId)) {
        statusElement.textContent = monacoT('monacoSpellcheckOff');
        return;
    }

    statusElement.textContent = monacoT('monacoSpellcheckLoading');

    const worker = monacoGetSpellcheckWorker();
    const state = {
        enabled: true,
        ready: false,
        languageId,
        model,
        editor,
        statusElement,
        worker,
        timer: null,
        contentDisposable: null,
        latestCheckRequestId: 0,
        latestCheckVersionId: 0,
        decorationIds: [],
        errors: [],
        suggestionCache: new Map(),
        pendingSuggestions: new Map()
    };
    monacoSpellcheckStates.set(editor, state);

    const loadRequestId = monacoNextSpellcheckRequestId();
    monacoSpellcheckRoutes.set(loadRequestId, { state, kind: 'load' });
    worker.postMessage({
        type: 'load',
        requestId: loadRequestId,
        languageId,
        languages: monacoSpellcheckLanguagesPayload()
    });
    state.contentDisposable = editor.onDidChangeModelContent(() => monacoScheduleSpellcheck(editor));
}

// Icons for the spellcheck language picker: the two non-language options get an
// icon (a globe for automatic detection, a crossed-out circle for off) and every
// real language gets its country flag. All of them share the 24x16 viewBox so the
// picker can size them with one CSS box; the icons are drawn inside it, which
// keeps their square shape.
const monacoSpellcheckIcons = {
    // Automatic detection: two sparkles (the usual "magic/auto" metaphor).
    auto: '<svg viewBox="4 0 16 16" aria-hidden="true"><path d="M11 2q.8 5 5.5 5.7-4.7.8-5.5 5.8-.8-5-5.5-5.8Q10.2 7 11 2z" fill="currentColor"/><path d="M16.6 11.1q.35 2.1 2.3 2.3-1.95.2-2.3 2.3-.35-2.1-2.3-2.3 1.95-.2 2.3-2.3z" fill="currentColor" opacity=".75"/></svg>',
    off: '<svg viewBox="4 0 16 16" aria-hidden="true"><circle cx="12" cy="8" r="6.4" fill="none" stroke="currentColor" stroke-width="1.5"/><path d="M7.5 12.5 16.5 3.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>',
    en_US: '<svg viewBox="0 0 24 16" aria-hidden="true"><rect width="24" height="16" fill="#ffffff"/><g fill="#b22234"><rect width="24" height="1.23"/><rect y="2.46" width="24" height="1.23"/><rect y="4.92" width="24" height="1.23"/><rect y="7.38" width="24" height="1.23"/><rect y="9.84" width="24" height="1.23"/><rect y="12.3" width="24" height="1.23"/><rect y="14.76" width="24" height="1.24"/></g><rect width="10" height="8.61" fill="#3c3b6e"/><g fill="#ffffff"><circle cx="2" cy="1.8" r=".55"/><circle cx="5.2" cy="1.8" r=".55"/><circle cx="8.4" cy="1.8" r=".55"/><circle cx="3.6" cy="3.9" r=".55"/><circle cx="6.8" cy="3.9" r=".55"/><circle cx="2" cy="6" r=".55"/><circle cx="5.2" cy="6" r=".55"/><circle cx="8.4" cy="6" r=".55"/></g></svg>',
    ru_RU: '<svg viewBox="0 0 24 16" aria-hidden="true"><rect width="24" height="16" fill="#ffffff"/><rect y="5.33" width="24" height="5.34" fill="#0039a6"/><rect y="10.67" width="24" height="5.33" fill="#d52b1e"/></svg>',
    uk_UA: '<svg viewBox="0 0 24 16" aria-hidden="true"><rect width="24" height="8" fill="#0057b7"/><rect y="8" width="24" height="8" fill="#ffd700"/></svg>',
    be_BY: '<svg viewBox="0 0 24 16" aria-hidden="true"><rect width="24" height="16" fill="#c8313e"/><rect y="10.67" width="24" height="5.33" fill="#4aa657"/><rect width="5.5" height="16" fill="#ffffff"/><path d="M2.75 1 5.5 3.6 2.75 6.2 0 3.6zM2.75 9.8 5.5 12.4 2.75 15 0 12.4z" fill="#c8313e"/></svg>',
    de_DE: '<svg viewBox="0 0 24 16" aria-hidden="true"><rect width="24" height="5.33" fill="#111111"/><rect y="5.33" width="24" height="5.34" fill="#dd0000"/><rect y="10.67" width="24" height="5.33" fill="#ffce00"/></svg>',
    fr_FR: '<svg viewBox="0 0 24 16" aria-hidden="true"><rect width="8" height="16" fill="#0055a4"/><rect x="8" width="8" height="16" fill="#ffffff"/><rect x="16" width="8" height="16" fill="#ef4135"/></svg>',
    es_ES: '<svg viewBox="0 0 24 16" aria-hidden="true"><rect width="24" height="16" fill="#aa151b"/><rect y="4" width="24" height="8" fill="#f1bf00"/></svg>',
    it_IT: '<svg viewBox="0 0 24 16" aria-hidden="true"><rect width="8" height="16" fill="#009246"/><rect x="8" width="8" height="16" fill="#ffffff"/><rect x="16" width="8" height="16" fill="#ce2b37"/></svg>',
    pt_BR: '<svg viewBox="0 0 24 16" aria-hidden="true"><rect width="24" height="16" fill="#009c3b"/><path d="M12 1.8 22.2 8 12 14.2 1.8 8z" fill="#ffdf00"/><circle cx="12" cy="8" r="3.1" fill="#002776"/></svg>',
    nl_NL: '<svg viewBox="0 0 24 16" aria-hidden="true"><rect width="24" height="5.33" fill="#ae1c28"/><rect y="5.33" width="24" height="5.34" fill="#ffffff"/><rect y="10.67" width="24" height="5.33" fill="#21468b"/></svg>',
    pl_PL: '<svg viewBox="0 0 24 16" aria-hidden="true"><rect width="24" height="8" fill="#ffffff"/><rect y="8" width="24" height="8" fill="#dc143c"/></svg>',
    cs_CZ: '<svg viewBox="0 0 24 16" aria-hidden="true"><rect width="24" height="8" fill="#ffffff"/><rect y="8" width="24" height="8" fill="#d7141a"/><path d="M0 0 10 8 0 16z" fill="#11457e"/></svg>',
    da_DK: '<svg viewBox="0 0 24 16" aria-hidden="true"><rect width="24" height="16" fill="#c60c30"/><rect x="7" width="3.2" height="16" fill="#ffffff"/><rect y="6.4" width="24" height="3.2" fill="#ffffff"/></svg>',
    sv_SE: '<svg viewBox="0 0 24 16" aria-hidden="true"><rect width="24" height="16" fill="#006aa7"/><rect x="7" width="3.2" height="16" fill="#fecc00"/><rect y="6.4" width="24" height="3.2" fill="#fecc00"/></svg>'
};

function monacoSpellcheckIcon(languageId) {
    return monacoSpellcheckIcons[languageId] || monacoSpellcheckIcons.auto;
}

function monacoSpellcheckLanguageLabel(languageId) {
    if (languageId === 'auto') return monacoT('monacoSpellAuto');
    if (languageId === 'off') return monacoT('monacoSpellOff');
    return monacoSpellcheckLanguages[languageId]?.label || languageId;
}

// Options shown in the picker: off first, then auto detection, then languages.
function monacoSpellcheckLanguageOptions() {
    const options = [{ value: 'off', label: monacoT('monacoSpellOff') }];
    Object.keys(monacoSpellcheckLanguages).forEach((languageId) => {
        if (languageId === 'off') return;
        options.push({ value: languageId, label: monacoSpellcheckLanguageLabel(languageId) });
    });
    return options;
}

let monacoSpellcheckModalCleanup = null;
let monacoSpellcheckModalOwner = null;

function monacoCloseSpellcheckLanguageModal() {
    const cleanup = monacoSpellcheckModalCleanup;
    monacoSpellcheckModalCleanup = null;
    monacoSpellcheckModalOwner = null;
    if (cleanup) cleanup();
}

// The language picker lives in a centered modal instead of a select element: a
// native dropdown cannot render the flags next to the language names.
function monacoOpenSpellcheckLanguageModal(options) {
    monacoCloseSpellcheckLanguageModal();

    const overlay = document.createElement('div');
    overlay.className = 'monaco-spell-modal-overlay';
    overlay.setAttribute('role', 'dialog');
    overlay.setAttribute('aria-modal', 'true');
    overlay.setAttribute('aria-label', monacoT('monacoSpellSelectTitle'));

    const panel = document.createElement('div');
    panel.className = 'monaco-spell-modal';

    const head = document.createElement('div');
    head.className = 'monaco-spell-modal-head';
    const title = document.createElement('span');
    title.textContent = monacoT('monacoSpellSelectTitle');
    const closeButton = document.createElement('button');
    closeButton.type = 'button';
    closeButton.className = 'monaco-spell-modal-close';
    const closeLabel = monacoT('close');
    closeButton.setAttribute('aria-label', closeLabel && closeLabel !== 'close' ? closeLabel : 'Close');
    closeButton.textContent = '\u00d7';
    head.append(title, closeButton);

    const list = document.createElement('div');
    list.className = 'monaco-spell-modal-list';
    list.setAttribute('role', 'listbox');

    monacoSpellcheckLanguageOptions().forEach((option) => {
        const item = document.createElement('button');
        item.type = 'button';
        item.className = 'monaco-spell-option';
        item.dataset.value = option.value;
        item.setAttribute('role', 'option');
        const isActive = option.value === options.current;
        if (isActive) item.classList.add('is-active');
        item.setAttribute('aria-selected', isActive ? 'true' : 'false');

        const icon = document.createElement('span');
        icon.className = 'monaco-spell-option-icon';
        icon.innerHTML = monacoSpellcheckIcon(option.value);
        const label = document.createElement('span');
        label.className = 'monaco-spell-option-label';
        label.textContent = option.label;
        const check = document.createElement('span');
        check.className = 'monaco-spell-option-check';
        check.textContent = '\u2713';
        item.append(icon, label, check);

        item.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();
            const value = option.value;
            monacoCloseSpellcheckLanguageModal();
            options.onSelect(value);
            options.trigger?.focus?.();
        });
        list.appendChild(item);
    });

    panel.append(head, list);
    overlay.appendChild(panel);

    const onKeyDown = (event) => {
        if (event.key === 'Escape') {
            event.preventDefault();
            event.stopPropagation();
            monacoCloseSpellcheckLanguageModal();
            options.trigger?.focus?.();
        }
    };
    const onOverlayClick = (event) => {
        if (event.target === overlay) monacoCloseSpellcheckLanguageModal();
    };

    closeButton.addEventListener('click', (event) => {
        event.preventDefault();
        event.stopPropagation();
        monacoCloseSpellcheckLanguageModal();
        options.trigger?.focus?.();
    });
    overlay.addEventListener('mousedown', onOverlayClick);
    document.addEventListener('keydown', onKeyDown, true);
    document.body.appendChild(overlay);

    monacoSpellcheckModalOwner = options.owner || null;
    monacoSpellcheckModalCleanup = () => {
        document.removeEventListener('keydown', onKeyDown, true);
        overlay.remove();
    };

    (list.querySelector('.monaco-spell-option.is-active') || list.firstElementChild)?.focus?.();
}

function monacoCreateStatusBar(editor, container, filePath, tabId, readOnly) {
    const isDark = document.body.getAttribute('data-theme') !== 'light' &&
                   !document.body.classList.contains('light-theme');
    const statusColors = monacoGetStatusBarColors(isDark);
    const bg = statusColors.background;
    const color = statusColors.foreground;
    const slot = document.getElementById('status-editor-slot');
    const embedInFooter = !!(slot && tabId !== 'code-modal');
    const statusBar = document.createElement('div');
    if (embedInFooter) {
        statusBar.className = 'monaco-app-footer-status';
        statusBar.style.cssText = 'color:var(--muted);';
    } else {
        statusBar.className = 'monaco-status-bar';
        statusBar.style.cssText = `position:absolute;bottom:0;left:0;right:0;display:flex;align-items:center;justify-content:space-between;padding:0 10px;height:24px;background:${bg};color:${color};border-top:1px solid ${statusColors.itemBorder};font-size:12px;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;z-index:100;user-select:none;`;
    }
    const leftItems = document.createElement('div');
    leftItems.className = 'monaco-status-left';
    leftItems.style.cssText = embedInFooter ? 'display:flex;align-items:center;gap:8px;' : 'display:flex;align-items:center;gap:12px;';
    if (monacoIsTxtFile(filePath) && !readOnly) {
        const spellLabel = document.createElement('span');
        spellLabel.className = 'monaco-status-spell-label';
        spellLabel.textContent = monacoT('monacoSpellLanguage');
        if (!embedInFooter) spellLabel.style.cssText = 'opacity:0.8;';
        // Trigger for the language modal: shows the flag/icon of the active choice.
        const spellSelector = document.createElement('button');
        spellSelector.type = 'button';
        spellSelector.className = 'monaco-status-spell-select';
        spellSelector.title = monacoT('monacoSpellSelectTitle');
        spellSelector.setAttribute('aria-haspopup', 'dialog');
        const spellSelectorIcon = document.createElement('span');
        spellSelectorIcon.className = 'monaco-spell-icon';
        const spellSelectorLabel = document.createElement('span');
        spellSelectorLabel.className = 'monaco-spell-icon-label';
        spellSelector.append(spellSelectorIcon, spellSelectorLabel);

        const currentSpellLanguage = monacoGetDefaultSpellcheckLanguage();
        let activeSpellLanguage = currentSpellLanguage;
        const spellStatus = document.createElement('span');
        spellStatus.className = 'monaco-status-spell-status';
        if (!embedInFooter) spellStatus.style.cssText = 'opacity:0.7;';
        spellStatus.textContent = currentSpellLanguage === 'off' ? monacoT('monacoSpellcheckOff') : monacoT('monacoSpellcheckLoading');

        function paintSpellcheckTrigger(languageId) {
            spellSelectorIcon.innerHTML = monacoSpellcheckIcon(languageId);
            spellSelectorLabel.textContent = monacoSpellcheckLanguageLabel(languageId);
            spellSelector.setAttribute('aria-label', `${monacoT('monacoSpellLanguage')} ${monacoSpellcheckLanguageLabel(languageId)}`);
        }
        function applySpellcheckLanguage(languageId) {
            activeSpellLanguage = languageId;
            localStorage.setItem('monacoSpellcheckLanguage', languageId);
            paintSpellcheckTrigger(languageId);
            monacoEnableSpellcheck(editor, container, spellStatus, languageId);
        }
        paintSpellcheckTrigger(currentSpellLanguage);
        spellSelector.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();
            monacoOpenSpellcheckLanguageModal({
                current: activeSpellLanguage,
                trigger: spellSelector,
                owner: editor,
                onSelect: applySpellcheckLanguage
            });
        });
        monacoAddEditorDisposable(editor, {
            dispose() {
                if (monacoSpellcheckModalOwner === editor) monacoCloseSpellcheckLanguageModal();
            }
        });
        leftItems.appendChild(spellLabel);
        leftItems.appendChild(spellSelector);
        monacoEnableSpellcheck(editor, container, spellStatus, activeSpellLanguage);
    }
    const rightItems = document.createElement('div');
    rightItems.className = 'monaco-status-right';
    rightItems.style.cssText = embedInFooter ? 'display:flex;align-items:center;gap:8px;flex-shrink:0;' : 'display:flex;align-items:center;gap:12px;';
    const pillStyle = embedInFooter ? '' : `opacity:0.9;background:${statusColors.itemBackground};border:1px solid ${statusColors.itemBorder};padding:2px 8px;border-radius:3px;`;
    const wordCounter = document.createElement('span');
    wordCounter.className = 'monaco-status-item';
    if (pillStyle) wordCounter.style.cssText = pillStyle;
    wordCounter.textContent = '0 words';
    const charCounter = document.createElement('span');
    charCounter.className = 'monaco-status-item';
    if (pillStyle) charCounter.style.cssText = pillStyle;
    charCounter.textContent = '0 chars';
    const positionInfo = document.createElement('span');
    positionInfo.className = 'monaco-status-item';
    if (pillStyle) positionInfo.style.cssText = pillStyle;
    positionInfo.textContent = 'Ln 1, Col 1';
    rightItems.appendChild(wordCounter);
    rightItems.appendChild(charCounter);
    rightItems.appendChild(positionInfo);
    statusBar.appendChild(leftItems);
    statusBar.appendChild(rightItems);
    function updateCounters() {
        const selection = editor.getSelection();
        const content = selection && !selection.isEmpty()
            ? editor.getModel().getValueInRange(selection)
            : editor.getValue();
        const chars = content.length;
        const words = content.trim() === '' ? 0 : content.trim().split(/\s+/).length;
        wordCounter.textContent = `${words} word${words !== 1 ? 's' : ''}`;
        charCounter.textContent = `${chars} char${chars !== 1 ? 's' : ''}`;
        const position = editor.getPosition();
        if (position) {
            positionInfo.textContent = `Ln ${position.lineNumber}, Col ${position.column}`;
        }
    }
    editor.onDidChangeModelContent(() => { updateCounters(); });
    editor.onDidChangeCursorPosition(() => { updateCounters(); });
    editor.onDidChangeCursorSelection(() => { updateCounters(); });
    updateCounters();
    if (embedInFooter) {
        monacoFooterStatusEls.set(tabId, statusBar);
        monacoRefreshFooterStatusBar();
    } else {
        container.appendChild(statusBar);
    }
    function updateStatusBarLayout() {
        const statusHeight = embedInFooter ? 0 : Math.ceil(statusBar.getBoundingClientRect().height || 24);
        const touchToolbar = container.querySelector('.monaco-touch-toolbar');
        let touchToolbarHeight = 0;
        if (touchToolbar) {
            touchToolbar.style.bottom = `${statusHeight}px`;
            touchToolbarHeight = Math.ceil(touchToolbar.getBoundingClientRect().height || 0);
        }
        editor.updateOptions({ padding: { bottom: statusHeight + touchToolbarHeight + 8 } });
    }
    requestAnimationFrame(updateStatusBarLayout);
    window.addEventListener('resize', updateStatusBarLayout);
    const resizeObserver = !embedInFooter && typeof ResizeObserver !== 'undefined'
        ? new ResizeObserver(updateStatusBarLayout)
        : null;
    resizeObserver?.observe(statusBar);
    const statusLayoutDisposable = {
        dispose() {
            window.removeEventListener('resize', updateStatusBarLayout);
            resizeObserver?.disconnect();
            if (embedInFooter) {
                monacoFooterStatusEls.delete(tabId);
                if (statusBar.parentNode) statusBar.remove();
                monacoRefreshFooterStatusBar();
            } else if (statusBar.parentNode) {
                statusBar.remove();
            }
        }
    };
    editor.onDidDispose(() => statusLayoutDisposable.dispose());
    return statusBar;
}

// Register all programming languages
// Get language ID from file extension
function monacoGetLanguage(ext) {
    const languageMap = {
        'js': 'javascript', 'jsx': 'javascript', 'ts': 'typescript', 'tsx': 'typescript',
        'html': 'html', 'htm': 'html', 'css': 'css', 'scss': 'scss', 'less': 'less',
        'vue': 'html', 'svelte': 'html', 'php': 'php', 'phtml': 'php',
        'py': 'python', 'py3': 'python', 'pyw': 'python', 'rb': 'ruby', 'go': 'go',
        'java': 'java', 'c': 'c', 'cpp': 'cpp', 'h': 'c', 'hpp': 'cpp', 'cs': 'csharp', 'rs': 'rust',
        'swift': 'swift', 'kt': 'kotlin', 'scala': 'scala', 'dart': 'dart',
        'lua': 'lua', 'pl': 'perl', 'r': 'r', 'd': 'd', 'nim': 'nim',
        'cr': 'crystal', 'zig': 'zig', 'v': 'v', 'gleam': 'gleam',
        'sql': 'sql', 'json': 'json', 'yaml': 'yaml', 'yml': 'yaml', 'xml': 'xml',
        'sh': 'shell', 'bash': 'shell', 'ps1': 'powershell', 'bat': 'bat',
        'ini': 'ini', 'conf': 'ini', 'dockerfile': 'dockerfile',
        'md': 'markdown', 'markdown': 'markdown', 'rst': 'restructuredtext',
        'tex': 'latex', 'latex': 'latex', 'sty': 'latex', 'cls': 'latex', 'bib': 'latex',
        'hs': 'haskell', 'lisp': 'lisp', 'lsp': 'lisp', 'el': 'elisp',
        'scm': 'scheme', 'rkt': 'racket', 'ml': 'ocaml', 'pro': 'prolog',
        'm': 'matlab', 'f': 'fortran', 'f90': 'fortran', 'cbl': 'cobol',
        'ada': 'ada', 'adb': 'ada', 'v': 'verilog', 'vhd': 'vhdl',
        'sol': 'solidity', 'graphql': 'graphql', 'txt': 'plaintext',
        'ino': 'cpp'
    };
    
    return languageMap[ext] || 'plaintext';
}

// Handle editor hotkeys
// Delete current line
function monacoDeleteLine(editor) {
    const position = editor.getPosition();
    const lineNumber = position.lineNumber;
    
    const range = new monaco.Range(
        lineNumber, 1,
        lineNumber, editor.getModel().getLineMaxColumn(lineNumber)
    );
    
    editor.executeEdits('delete-line', [{
        range: range,
        text: null
    }]);
    
    const model = editor.getModel();
    if (model.getLineContent(lineNumber) === '' && model.getLineCount() > 1) {
        const deleteEmptyRange = new monaco.Range(
            lineNumber, 1,
            lineNumber + 1, 1
        );
        editor.executeEdits('delete-empty-line', [{
            range: deleteEmptyRange,
            text: null
        }]);
    }
}

// Move line up or down
function monacoMoveLine(editor, up) {
    const position = editor.getPosition();
    const lineNumber = position.lineNumber;
    const model = editor.getModel();
    const lineCount = model.getLineCount();
    
    if ((up && lineNumber === 1) || (!up && lineNumber === lineCount)) {
        return;
    }
    
    const targetLine = up ? lineNumber - 1 : lineNumber + 1;
    
    const lineText = model.getLineContent(lineNumber);
    const targetLineText = model.getLineContent(targetLine);
    
    const selection = editor.getSelection();
    const isLineSelected = selection && 
                          selection.startLineNumber === lineNumber && 
                          selection.endLineNumber === lineNumber && 
                          selection.startColumn === 1 && 
                          selection.endColumn === model.getLineMaxColumn(lineNumber);
    
    editor.executeEdits('move-line', [
        {
            range: new monaco.Range(lineNumber, 1, lineNumber, model.getLineMaxColumn(lineNumber)),
            text: targetLineText
        },
        {
            range: new monaco.Range(targetLine, 1, targetLine, model.getLineMaxColumn(targetLine)),
            text: lineText
        }
    ]);
    
    if (isLineSelected) {
        const newRange = new monaco.Range(
            targetLine, 1,
            targetLine, model.getLineMaxColumn(targetLine)
        );
        editor.setSelection(newRange);
    } else {
        editor.setPosition({ lineNumber: targetLine, column: position.column });
    }
    
    editor.revealLine(targetLine);
}

// Toggle block comment
function monacoToggleBlockComment(editor) {
    editor.trigger('keyboard', 'editor.action.blockComment', {});
}

// Duplicate line
function monacoDuplicateLine(editor) {
    editor.trigger('keyboard', 'editor.action.duplicateSelection', {});
}

// Improved touch screen support with mobile selection
function monacoEnableTouchSelection(editor, container) {
    if (!monacoIsTouchDevice) return;

    let touchStart = null;
    let lastTapTime = 0;
    let selectionInProgress = false;
    let initialPosition = null;
    let longPressTimer = null;
    let pendingLongPress = false;
    let isScrolling = false;
    let isDraggingHandle = null; // 'start' or 'end'
    let selectionHandles = null;
    let autoScrollFrame = null;
    let autoScrollTouch = null;
    let autoScrollStartedAt = 0;
    const LONG_PRESS_DELAY = 400; // ms required to activate selection
    const SCROLL_THRESHOLD = 15; // px movement before long press triggers = scroll
    const AUTO_SCROLL_ZONE = 72;
    const AUTO_SCROLL_MAX_PX = 16;
    const AUTO_SCROLL_ACCEL_MS = 1000;
    const AUTO_SCROLL_INITIAL_FACTOR = 1 / 3;
    const domListeners = [];
    const addListener = (target, eventName, handler, options) => {
        target.addEventListener(eventName, handler, options);
        domListeners.push(() => target.removeEventListener(eventName, handler, options));
    };

    // Create visual selection handles
    function createSelectionHandles() {
        removeSelectionHandles();

        const editorDom = editor.getDomNode();
        if (!editorDom) return;

        // Make sure the editor has position: relative for absolute positioning of children
        if (editorDom.style.position !== 'relative' && editorDom.style.position !== 'absolute' && editorDom.style.position !== 'fixed') {
            editorDom.style.position = 'relative';
        }

        selectionHandles = document.createElement('div');
        selectionHandles.className = 'monaco-touch-selection-handles';
        selectionHandles.style.cssText = 'position:absolute;top:0;left:0;width:100%;height:100%;pointer-events:none;z-index:100;overflow:hidden;';

        // Start handle: thin vertical Word-style line above selection start
        const startHandle = document.createElement('div');
        startHandle.className = 'word-handle top-line';
        startHandle.dataset.handle = 'start';

        // End handle: horizontal Word-style bar below selection end
        const endHandle = document.createElement('div');
        endHandle.className = 'word-handle bottom-line';
        endHandle.dataset.handle = 'end';

        selectionHandles.appendChild(startHandle);
        selectionHandles.appendChild(endHandle);
        editorDom.appendChild(selectionHandles);

        [startHandle, endHandle].forEach(handle => {
            addListener(handle, 'touchstart', (e) => {
                e.preventDefault();
                e.stopPropagation();
                isDraggingHandle = handle.dataset.handle;
                container.style.touchAction = 'none';
            }, { passive: false });

            addListener(handle, 'touchmove', (e) => {
                if (isDraggingHandle !== handle.dataset.handle) return;
                e.preventDefault();
                e.stopPropagation();
                const touch = e.touches[0];
                try { setSelectionFromClientPoint(touch.clientX, touch.clientY); } catch (_) {}
                updateTouchAutoScroll(touch);
            }, { passive: false });

            addListener(handle, 'touchend', (e) => {
                if (isDraggingHandle === handle.dataset.handle) {
                    e.stopPropagation();
                    isDraggingHandle = null;
                    stopTouchAutoScroll();
                    container.style.touchAction = 'pan-x pan-y';
                }
            });
        });
    }

    function removeSelectionHandles() {
        if (selectionHandles) {
            selectionHandles.remove();
            selectionHandles = null;
        }
        isDraggingHandle = null;
        stopTouchAutoScroll();
    }

    function updateSelectionHandlesPosition() {
        if (!selectionHandles) return;

        const selection = editor.getSelection();
        if (!selection || selection.isEmpty()) {
            hideSelectionHandles();
            return;
        }

        const startHandle = selectionHandles.querySelector('.word-handle.top-line');
        const endHandle = selectionHandles.querySelector('.word-handle.bottom-line');
        if (!startHandle || !endHandle) return;

        const editorDom = editor.getDomNode();
        if (!editorDom) return;
        
        // Get the selection start and end coordinates
        const startCoords = editor.getScrolledVisiblePosition({
            lineNumber: selection.startLineNumber,
            column: selection.startColumn
        });
        
        const endCoords = editor.getScrolledVisiblePosition({
            lineNumber: selection.endLineNumber,
            column: selection.endColumn
        });

        if (!startCoords || !endCoords) {
            hideSelectionHandles();
            return;
        }

        const lineHeight = editor.getOption(monaco.editor.EditorOption.lineHeight);

        // Top line sits above the selection start; bottom bar below the end
        const startX = startCoords.left;
        const startY = startCoords.top - 2;
        const endX = endCoords.left;
        const endY = endCoords.top + lineHeight - 1;

        startHandle.style.left = (startX - 0.75) + 'px';
        startHandle.style.top = startY + 'px';
        startHandle.style.display = 'block';
        startHandle.style.opacity = '0.95';

        endHandle.style.left = (endX - 3) + 'px';
        endHandle.style.top = endY + 'px';
        endHandle.style.display = 'block';
        endHandle.style.opacity = '0.95';

        // Very short single-line selection: spread the markers apart
        if (selection.startLineNumber === selection.endLineNumber && (endX - startX) < 4) {
            endHandle.style.left = (endX + 1) + 'px';
        }
    }

    function hideSelectionHandles() {
        if (!selectionHandles) return;
        const startHandle = selectionHandles.querySelector('.word-handle.top-line');
        const endHandle = selectionHandles.querySelector('.word-handle.bottom-line');
        if (startHandle) startHandle.style.display = 'none';
        if (endHandle) endHandle.style.display = 'none';
    }

    function setSelectionFromClientPoint(clientX, clientY) {
        const currentPosition = editor.getTargetAtClientPoint(clientX, clientY);
        if (!currentPosition || !currentPosition.position) return false;
        const model = editor.getModel();
        if (!model) return false;
        const lineNumber = Math.max(1, Math.min(currentPosition.position.lineNumber, model.getLineCount()));
        const column = Math.max(1, Math.min(currentPosition.position.column, model.getLineMaxColumn(lineNumber)));
        if (isDraggingHandle) {
            const selection = editor.getSelection();
            if (!selection) return false;
            const range = isDraggingHandle === 'start'
                ? new monaco.Range(lineNumber, column, selection.endLineNumber, selection.endColumn)
                : new monaco.Range(selection.startLineNumber, selection.startColumn, lineNumber, column);
            if (range.isEmpty()) return false;
            editor.setSelection(range);
        } else {
            if (!touchStart || !touchStart.position) return false;
            editor.setSelection(new monaco.Range(
                touchStart.position.lineNumber,
                touchStart.position.column,
                lineNumber,
                column
            ));
        }
        updateSelectionHandlesPosition();
        return true;
    }

    function stopTouchAutoScroll() {
        if (autoScrollFrame) cancelAnimationFrame(autoScrollFrame);
        autoScrollFrame = null;
        autoScrollTouch = null;
        autoScrollStartedAt = 0;
    }

    function autoScrollDirection(clientY) {
        const rect = container.getBoundingClientRect();
        const topDistance = clientY - rect.top;
        const bottomDistance = rect.bottom - clientY;
        if (topDistance < AUTO_SCROLL_ZONE) return { direction: -1, distance: Math.max(0, topDistance) };
        if (bottomDistance < AUTO_SCROLL_ZONE) return { direction: 1, distance: Math.max(0, bottomDistance) };
        return { direction: 0, distance: AUTO_SCROLL_ZONE };
    }

    function runTouchAutoScroll() {
        if (!autoScrollTouch) {
            autoScrollFrame = null;
            return;
        }
        const edge = autoScrollDirection(autoScrollTouch.clientY);
        if (!edge.direction) {
            stopTouchAutoScroll();
            return;
        }
        const elapsed = Date.now() - autoScrollStartedAt;
        const accel = Math.min(1, elapsed / AUTO_SCROLL_ACCEL_MS);
        const factor = AUTO_SCROLL_INITIAL_FACTOR + ((1 - AUTO_SCROLL_INITIAL_FACTOR) * accel);
        const intensity = 1 - (edge.distance / AUTO_SCROLL_ZONE);
        const delta = edge.direction * Math.max(1, AUTO_SCROLL_MAX_PX * intensity * intensity * factor);
        editor.setScrollTop(editor.getScrollTop() + delta);
        setSelectionFromClientPoint(autoScrollTouch.clientX, autoScrollTouch.clientY);
        autoScrollFrame = requestAnimationFrame(runTouchAutoScroll);
    }

    function updateTouchAutoScroll(touch) {
        const edge = autoScrollDirection(touch.clientY);
        if (!edge.direction) {
            stopTouchAutoScroll();
            return;
        }
        autoScrollTouch = { clientX: touch.clientX, clientY: touch.clientY };
        if (!autoScrollFrame) {
            autoScrollStartedAt = Date.now();
            autoScrollFrame = requestAnimationFrame(runTouchAutoScroll);
        }
    }

    // Allow text selection but prevent accidental selection during scroll
    container.style.touchAction = 'pan-x pan-y';
    container.style.webkitUserSelect = 'text';
    container.style.userSelect = 'text';

    // Intercept contextmenu before Monaco: if our long-press selected a word,
    // suppress native menu so our handles show. Otherwise let native menu through
    // by stopping Monaco from seeing the event but NOT preventing default.
    addListener(container, 'contextmenu', (e) => {
        if (selectionInProgress) {
            // We successfully selected a word via our long-press: suppress native
            // menu so our custom handles appear.
            e.preventDefault();
            e.stopPropagation();
            return;
        }
        // Not our selection — stop Monaco's handler from running (which would
        // prevent default and show its own menu), but DON'T prevent default,
        // so the browser's native context menu appears.
        e.stopImmediatePropagation();
    }, { capture: true });

    addListener(container, 'touchstart', (e) => {
        // If dragging a handle - ignore normal handlers
        if (isDraggingHandle) return;

        const touch = e.touches[0];
        const position = editor.getTargetAtClientPoint(touch.clientX, touch.clientY);

        touchStart = {
            x: touch.clientX,
            y: touch.clientY,
            time: Date.now(),
            position: position ? position.position : null
        };

        initialPosition = position ? position.position : null;
        isScrolling = false;

        // Clear any existing timer
        if (longPressTimer) {
            clearTimeout(longPressTimer);
            longPressTimer = null;
        }

        // Only set cursor position if there's no active selection
        if (e.touches.length === 1) {
            const currentSelection = editor.getSelection();
            if (!currentSelection || currentSelection.isEmpty()) {
                if (position && position.position) {
                    editor.setPosition(position.position);
                }
            }
        }

        // A real finger starts a native long press / text drag the moment the
        // touch is held, and that native selection replaces whatever we set.
        // Claim the gesture before the browser does: if the finger stays put,
        // we own it; if it moves, touchmove restores scrolling and the
        // platform keeps its own behaviour (including the menu).
        pendingLongPress = false;
        container.style.touchAction = 'none';

        // Start long press timer - only activate selection after holding
        longPressTimer = setTimeout(() => {
            // Decide before committing: only a word under the finger, and only
            // when nothing is selected yet. Otherwise the platform must be left
            // alone so it can show its own menu.
            if (!touchStart || isScrolling || !touchStart.position) {
                longPressTimer = null;
                return;
            }
            const currentSelection = editor.getSelection();
            if (currentSelection && !currentSelection.isEmpty()) {
                longPressTimer = null;
                return;
            }
            const word = editor.getModel().getWordAtPosition(touchStart.position);
            if (!word) {
                longPressTimer = null;
                return;
            }

            // Long press detected - select the word
            pendingLongPress = true;
            selectionInProgress = true;

            const range = new monaco.Range(
                touchStart.position.lineNumber,
                word.startColumn,
                touchStart.position.lineNumber,
                word.endColumn
            );
            editor.setSelection(range);
            editor.revealRange(range);

            // Create selection handles
            createSelectionHandles();
            setTimeout(updateSelectionHandlesPosition, 10);

            longPressTimer = null;
        }, LONG_PRESS_DELAY);

    }, { passive: true });

    addListener(container, 'touchmove', (e) => {
        const touch = e.touches[0];

        // Handle selection handle dragging (duplicated for reliability)
        if (isDraggingHandle && selectionHandles) {
            e.preventDefault();
            e.stopPropagation();
            return; // The global handler will handle this
        }

        if (!touchStart || e.touches.length !== 1) return;

        const moveX = Math.abs(touch.clientX - touchStart.x);
        const moveY = Math.abs(touch.clientY - touchStart.y);

        // If finger moved beyond threshold before long press, it's a scroll
        if (!selectionInProgress && longPressTimer) {
            if (moveX > SCROLL_THRESHOLD || moveY > SCROLL_THRESHOLD) {
                // Cancel long press - user is scrolling
                clearTimeout(longPressTimer);
                longPressTimer = null;
                isScrolling = true;
                // Hand the gesture back to the platform: it must be allowed to
                // scroll from the current touchstart, which only works when
                // touch-action is restored to a scrolling value.
                container.style.touchAction = 'pan-x pan-y';
                return;
            }
        }

        // If selection is active (long press fired), allow expanding the selection
        if (!selectionInProgress) return;

        e.preventDefault();
        try { setSelectionFromClientPoint(touch.clientX, touch.clientY); } catch (_) {}
        updateTouchAutoScroll(touch);

    }, { passive: false });

    addListener(container, 'touchend', (e) => {
        // Finish handle dragging
        if (isDraggingHandle) {
            isDraggingHandle = null;
            stopTouchAutoScroll();
            container.style.touchAction = 'pan-x pan-y';
            return;
        }

        if (!touchStart) return;

        const touchEnd = Date.now();
        const touchDuration = touchEnd - touchStart.time;

        // Clear long press timer
        if (longPressTimer) {
            clearTimeout(longPressTimer);
            longPressTimer = null;
        }
// Double tap - select word (only if not scrolling and the finger is on a word)
        if (touchDuration < 200 && (touchEnd - lastTapTime) < 300 && !isScrolling) {
            const touch = e.changedTouches[0];
            const position = editor.getTargetAtClientPoint(touch.clientX, touch.clientY);

            if (position && position.position) {
                const word = editor.getModel().getWordAtPosition(position.position);
                if (word) {
                    // Only claim the tap when we actually select something, so a
                    // tap elsewhere still reaches the platform menu.
                    e.preventDefault();
                    const range = new monaco.Range(
                        position.position.lineNumber,
                        word.startColumn,
                        position.position.lineNumber,
                        word.endColumn
                    );
                    editor.setSelection(range);
                    editor.revealRange(range);

                    // Create handles for double tap
                    createSelectionHandles();
                    setTimeout(updateSelectionHandlesPosition, 10);
                }
            }
        }

        // Triple tap - select line (only if not scrolling)
        else if (touchDuration < 200 && (touchEnd - lastTapTime) < 600 && !isScrolling) {
            const touch = e.changedTouches[0];
            const position = editor.getTargetAtClientPoint(touch.clientX, touch.clientY);

            if (position && position.position) {
                e.preventDefault();
                const lineNumber = position.position.lineNumber;
                const lineContent = editor.getModel().getLineContent(lineNumber);

                const range = new monaco.Range(
                    lineNumber,
                    1,
                    lineNumber,
                    lineContent.length + 1
                );
                editor.setSelection(range);
                editor.revealLine(lineNumber);

                // Create handles for triple tap
                createSelectionHandles();
                setTimeout(updateSelectionHandlesPosition, 10);
            }
        }
        // Long press - the word is already selected in touchstart
        else if (pendingLongPress && !isScrolling) {
            e.preventDefault();
            // Selection was already made in touchstart
        }

        // Reset selection mode after touch ends
        if (selectionInProgress) {
            selectionInProgress = false;
            container.style.touchAction = 'pan-x pan-y';
        }
        pendingLongPress = false;

        if (!selectionInProgress && touchDuration < 200 && !isScrolling) {
            const touch = e.changedTouches[0];
            const position = editor.getTargetAtClientPoint(touch.clientX, touch.clientY);

            if (position && position.position) {
                const currentSelection = editor.getSelection();
                if (!currentSelection || currentSelection.isEmpty()) {
                    editor.setPosition(position.position);
                    // Hide handles if there is no selection
                    hideSelectionHandles();
                }
            }
        }

        lastTapTime = touchEnd;
        touchStart = null;
        selectionInProgress = false;
        initialPosition = null;
        isScrolling = false;
        stopTouchAutoScroll();

    }, { passive: false });

    // Update handle positions on scroll and selection change
    editor.onDidScrollChange(() => {
        updateSelectionHandlesPosition();
    });

    editor.onDidChangeCursorPosition(() => {
        updateSelectionHandlesPosition();
    });

    editor.onDidChangeCursorSelection((e) => {
        if (e.selection && !e.selection.isEmpty()) {
            if (!selectionHandles) {
                createSelectionHandles();
            }
            setTimeout(updateSelectionHandlesPosition, 0);
        } else {
            hideSelectionHandles();
        }
    });

    addListener(document, 'touchstart', (e) => {
        if (!container.contains(e.target)) {
            removeSelectionHandles();
        }
    });
    const cleanup = {
        dispose() {
            if (longPressTimer) {
                clearTimeout(longPressTimer);
                longPressTimer = null;
            }
            removeSelectionHandles();
            domListeners.splice(0).forEach((dispose) => {
                try { dispose(); } catch (_) {}
            });
        }
    };
    monacoAddEditorDisposable(editor, cleanup);
    return cleanup;
}

// Create Monaco editor for a tab
async function monacoCreateEditor(container, content, filePath, tabId, readOnly = false) {
    try {
        await initMonaco();
        monacoRegisterCompletions();

        if (!container) {
            throw new Error('Container element is null');
        }

        const ext = filePath.split('.').pop().toLowerCase();
        const language = monacoGetLanguage(ext);

        const previousPath = monacoTabModelPaths.get(tabId);
        if (previousPath && previousPath !== filePath) {
            monacoReleaseModelByTab(tabId);
        }
        let model = monacoAcquireModel(filePath, content, language);
        monacoTabModelPaths.set(tabId, filePath);

        // Settings for the editor with an improved scrollbar and minimap
        const editorOptions = {
            model: model,
            language: language,
            theme: document.body.getAttribute('data-theme') === 'dark' ? 'custom-dark' : 'custom-light',
            readOnly: readOnly,

            // Minimap settings - hidden on mobile devices in portrait orientation
            minimap: {
                enabled: !monacoShouldHideMinimap(), // Disabled on touch devices in portrait orientation
                maxColumn: 80,
                renderCharacters: true,
                scale: 1,
                showSlider: 'always',
                side: 'right',
                size: 'proportional'
            },
            
            // Scrollbar settings - hide vertical and horizontal
            scrollbar: {
                vertical: 'hidden', // Completely hide the vertical scrollbar
                horizontal: 'hidden', // Completely hide the horizontal scrollbar
                verticalScrollbarSize: 0,
                horizontalScrollbarSize: 0,
                useShadows: false,
                alwaysConsumeMouseWheel: true
            },
            
            automaticLayout: true,
            fontSize: monacoIsTouchDevice ? 18 : 14,
            fontFamily: '"JetBrains Mono", Consolas, "Courier New", monospace',
            lineNumbers: 'on',
            lineNumbersMinChars: 2,
            // Keep the line-numbers gutter narrow. Monaco reserves a 16px
            // folding gutter plus 10/20px of line decorations on top of the
            // numbers, which squeezed the code area on phones. The decorations
            // column is trimmed to 8px (the minimum that still fits the 7px
            // .ai-agent-edit-glyph strip) and the folding arrows are hidden;
            // folding itself still works via Ctrl+Shift+[ / ] and the
            // command palette (F1).
            lineDecorationsWidth: 8,
            roundedSelection: true,
            scrollBeyondLastLine: true,
            
            // Word wrap settings
            wordWrap: 'on',
            wrappingIndent: 'same',
            wrappingStrategy: 'advanced',
            wordWrapBreakAfterCharacters: '\t})]?|&,;',
            wordWrapBreakBeforeCharacters: '{([+',
            wordWrapBreakObtrusiveCharacters: '.',
            
            // Indentation settings
            tabSize: 2,
            insertSpaces: true,
            detectIndentation: true,
            
            // Highlighting and visual effects
            bracketPairColorization: { enabled: true },
            guides: { bracketPairs: true, indentation: true },
            autoClosingBrackets: 'always',
            autoClosingQuotes: 'always',
            autoIndent: 'full',
            formatOnPaste: true,
            formatOnType: true,
            renderWhitespace: 'selection',
            smoothScrolling: true,
            mouseWheelZoom: true,
            
            // Folding settings
            folding: true,
            foldingStrategy: 'auto',
            showFoldingControls: 'never',
            
            // Context menu
            contextmenu: true,
            multiCursorModifier: 'alt',
            
            // Mobile keyboard/input support
            accessibilitySupport: 'off',
            disableLayerHinting: true,
            
            // Hints and autocomplete
            quickSuggestions: true,
            suggestOnTriggerCharacters: true,
            acceptSuggestionOnEnter: 'on',
            tabCompletion: 'on',
            wordBasedSuggestions: true,
            parameterHints: { enabled: true },
            unicodeHighlight: {
                ambiguousCharacters: false,
                invisibleCharacters: false,
                nonBasicASCII: false
            },
            
            // Special settings for touch
            dragAndDrop: false,
            mouseWheelScrollSensitivity: monacoIsTouchDevice ? 3 : 1,
            fastScrollSensitivity: monacoIsTouchDevice ? 4 : 2,
            multiCursorMergeOverlapping: true,
            selectionClipboard: true,
            
            overviewRulerLanes: 0,
            overviewRulerBorder: false,
            hideCursorInOverviewRuler: true,
            
            // Scroll settings via the minimap
            scrollByPage: true,
            scrollBeyondLastColumn: 5
        };
        
        // The editor renders with the JetBrains Mono webfont. Monaco measures the
        // character width at creation time and wraps lines with that measurement,
        // so the font has to be there first — otherwise wrapped lines end up wider
        // than the width Monaco wrapped for and the leftover range lets the text
        // scroll horizontally (the view jitters left and right while typing).
        if (window.cloudMonacoShared && window.cloudMonacoShared.waitForEditorFont) {
            await window.cloudMonacoShared.waitForEditorFont({
                fontFamily: editorOptions.fontFamily,
                fontSize: editorOptions.fontSize
            });
        }

        const editor = monaco.editor.create(container, editorOptions);

        // Word wrap is on, so the text must never scroll sideways: pin the
        // horizontal offset to zero (see installHorizontalScrollLock).
        if (window.cloudMonacoShared && window.cloudMonacoShared.installHorizontalScrollLock) {
            monacoAddEditorDisposable(editor, window.cloudMonacoShared.installHorizontalScrollLock(editor));
        }

        const notifyUndoRedoState = () => {
            window.dispatchEvent(new CustomEvent('cloudMonacoUndoRedoChanged', { detail: { tabId } }));
        };

        monacoEnableTouchSelection(editor, container);
        if (!readOnly) monacoCreateTouchToolbar(editor, container);
        
        monacoCreateStatusBar(editor, container, filePath, tabId, readOnly);

        // Transliteration lives in assets/monaco-shared.js so that the project
        // editors (html, markdown, latex, zip) follow exactly the same rules.
        monacoAddEditorDisposable(
            editor,
            window.cloudMonacoShared.installTransliteration({
                editor,
                container,
                monaco,
                getLang: () => window.cloudMonacoShared.resolveLang()
            })
        );

        // ── App global hotkeys ───────────────────────────────────────
        // Monaco absorbs all key presses inside itself and does not let them bubble up
        // to document. So we explicitly intercept every global hotkey
        // via addCommand and call the corresponding app function directly
        // (they are available since monaco-editor.js loads in the same window as app.js).

        // Ctrl+S - save the file (instead of the browser 'Save page')
        editor.addCommand(monaco.KeyMod.CtrlCmd | monaco.KeyCode.KeyS, function() {
            if (typeof saveFile === 'function') {
                saveFile().then((ok) => {
                    if (ok && typeof showSuccessCheck === 'function') showSuccessCheck(document.getElementById('btn-save'));
                });
            }
        });

        // F9 - switch input language
        editor.addCommand(monaco.KeyCode.F9, function() {
            if (typeof switchInputLanguage === 'function') switchInputLanguage();
        });

        // F8 - compile/run
        editor.addCommand(monaco.KeyCode.F8, function() {
            const viewer = document.getElementById('viewer');
            const path = viewer && viewer.dataset.path;
            if (path) {
                const ext = path.split('.').pop().toLowerCase();
                const supportedLangs = ['java', 'c', 'cpp', 'py', 'm'];
                if (supportedLangs.includes(ext)) {
                    if (typeof compileAndRun === 'function') compileAndRun();
                } else {
                    if (typeof flashStatus === 'function' && window.i18n)
                        flashStatus(window.i18n['fileTypeNotCompilable'] || 'File type not compilable');
                }
            } else {
                if (typeof flashStatus === 'function' && window.i18n)
                    flashStatus(window.i18n['noFileOpen'] || 'No file open');
            }
        });

        // F5 - refresh the file tree
        editor.addCommand(monaco.KeyCode.F5, function() {
            document.getElementById('btn-refresh-tree')?.click();
        });

        // Ctrl+Shift+A - open/close the AI assistant
        editor.addCommand(monaco.KeyMod.CtrlCmd | monaco.KeyMod.Shift | monaco.KeyCode.KeyA, function() {
            if (typeof toggleAIModal === 'function') toggleAIModal();
        });

        // Ctrl+Shift+→ - next tab
        editor.addCommand(monaco.KeyMod.CtrlCmd | monaco.KeyMod.Shift | monaco.KeyCode.RightArrow, function() {
            if (typeof switchToNextTab === 'function') switchToNextTab();
        });

        // Ctrl+Shift+← - previous tab
        editor.addCommand(monaco.KeyMod.CtrlCmd | monaco.KeyMod.Shift | monaco.KeyCode.LeftArrow, function() {
            if (typeof switchToPrevTab === 'function') switchToPrevTab();
        });
        // ───────────────────────────────────────────────────────────────────────

        // Add commands for hotkeys
        editor.addCommand(monaco.KeyMod.CtrlCmd | monaco.KeyCode.KeyD, function() {
            monacoDeleteLine(editor);
        });

        editor.addCommand(monaco.KeyMod.CtrlCmd | monaco.KeyMod.Shift | monaco.KeyCode.UpArrow, function() {
            monacoMoveLine(editor, true);
        });
        
        editor.addCommand(monaco.KeyMod.CtrlCmd | monaco.KeyMod.Shift | monaco.KeyCode.DownArrow, function() {
            monacoMoveLine(editor, false);
        });
        
        editor.addCommand(monaco.KeyMod.CtrlCmd | monaco.KeyMod.Shift | monaco.KeyCode.Slash, function() {
            monacoToggleBlockComment(editor);
        });
        
        editor.addCommand(monaco.KeyMod.CtrlCmd | monaco.KeyMod.Shift | monaco.KeyCode.KeyD, function() {
            monacoDuplicateLine(editor);
        });
        editor.addAction({
            id: `spellcheck-quickfix-${tabId}`,
            label: monacoT('monacoQuickFix'),
            keybindings: [
                monaco.KeyMod.CtrlCmd | monaco.KeyCode.KeyI,
                monaco.KeyMod.CtrlCmd | monaco.KeyCode.Space
            ],
            contextMenuGroupId: 'navigation',
            contextMenuOrder: 1.4,
            run(ed) {
                const action = ed.getAction('editor.action.quickFix');
                if (action) action.run();
                else ed.trigger('keyboard', 'editor.action.quickFix', {});
            }
        });

        editor.addAction({
            id: `ask-ai-${tabId}`,
            label: monacoT('monacoAskAi'),
            contextMenuGroupId: 'navigation',
            contextMenuOrder: 0,
            precondition: 'editorHasSelection',
            run: async function() {
                await monacoOpenAiActionMenu(editor, container);
            }
        });

        // Add a Select All command for all devices so it always wins over app-level handlers
        editor.addCommand(monaco.KeyMod.CtrlCmd | monaco.KeyCode.KeyA, function() {
            const model = editor.getModel();
            if (model) {
                const fullRange = model.getFullModelRange();
                editor.setSelection(fullRange);
                editor.revealRange(fullRange);
            }
        });

        if (!readOnly && (language === 'plaintext' || language === 'markdown')) {
            monacoSetupTxtNumberedListContinuation(editor, filePath);
        }

        if (!readOnly && language === 'plaintext') {

            let isApplyingAutoCase = false;
            const autoSentenceCaseDisposable = editor.onDidChangeModelContent((event) => {
                if (isApplyingAutoCase || editor.__collabApplyingRemote || event.isFlush || event.changes.length !== 1) return;
                const change = event.changes[0];
                if (change.rangeLength !== 0 || change.text.length !== 1) return;
                if (!/^\p{Ll}$/u.test(change.text)) return;
                const prefix = model.getValue().slice(0, change.rangeOffset);
                if (!/(^|[.!?]\s*|\n\s*)$/u.test(prefix)) return;
                const upper = change.text.toLocaleUpperCase();
                if (upper === change.text) return;
                const startOffset = change.rangeOffset;
                const startPos = model.getPositionAt(startOffset);
                const endPos = model.getPositionAt(startOffset + 1);
                isApplyingAutoCase = true;
                editor.executeEdits('sentence-autocase', [{
                    range: new monaco.Range(startPos.lineNumber, startPos.column, endPos.lineNumber, endPos.column),
                    text: upper
                }]);
                editor.setPosition(endPos);
                isApplyingAutoCase = false;
            });
            monacoAddEditorDisposable(editor, autoSentenceCaseDisposable);
        }

        monacoEditors.set(tabId, editor);
        monacoAddEditorDisposable(editor, editor.onDidChangeModelContent((event) => {
            monacoNoteSuggestionAcceptance(editor, event);
        }));
        monacoAddEditorDisposable(editor, editor.onDidChangeModelContent(() => { notifyUndoRedoState(); }));
        monacoAddEditorDisposable(editor, editor.onDidFocusEditorText(() => { notifyUndoRedoState(); }));
        notifyUndoRedoState();
        editor.onDidDispose(() => monacoDisposeExtraDisposables(editor));
        
        return editor;
        
    } catch (error) {
        console.error('Failed to create Monaco editor:', error);
        throw error;
    }
}

// Get editor content
function monacoGetContent(tabId) {
    const editor = monacoEditors.get(tabId);
    return editor ? editor.getValue() : '';
}

// Set editor content
function monacoSetContent(tabId, content) {
    const editor = monacoEditors.get(tabId);
    if (editor) {
        editor.setValue(content);
    }
}

function monacoGetEditor(tabId) {
    return monacoEditors.get(tabId) || null;
}

function monacoGetActiveEditor() {
    const activeTabId = window.tabsState?.activeTabId;
    if (activeTabId && monacoEditors.has(activeTabId)) {
        return { tabId: activeTabId, editor: monacoEditors.get(activeTabId) };
    }

    const viewer = document.getElementById('viewer');
    const container = viewer?.querySelector('.monaco-editor-container[data-tab-id]');
    const domTabId = container?.getAttribute('data-tab-id');
    if (domTabId && monacoEditors.has(domTabId)) {
        return { tabId: domTabId, editor: monacoEditors.get(domTabId) };
    }
    if (domTabId && monacoEditors.has(Number(domTabId))) {
        return { tabId: Number(domTabId), editor: monacoEditors.get(Number(domTabId)) };
    }

    if (viewer?.monacoEditor) {
        for (const [tabId, editor] of monacoEditors.entries()) {
            if (editor === viewer.monacoEditor) return { tabId, editor };
        }
    }

    return null;
}

let monacoAiRuntimeConfigPromise = null;
let monacoAiActionOverlay = null;

function monacoGetSelectedText(editor) {
    const selection = editor?.getSelection();
    const model = editor?.getModel();
    if (!selection || selection.isEmpty() || !model) return '';
    return model.getValueInRange(selection);
}

function monacoCaptureEditorViewState(editor) {
    if (!editor) return null;
    const model = editor.getModel();
    const selection = editor.getSelection();
    const position = editor.getPosition();
    let selectionOffsets = null;
    if (model && selection) {
        selectionOffsets = {
            start: model.getOffsetAt(selection.getStartPosition()),
            end: model.getOffsetAt(selection.getEndPosition()),
            isEmpty: selection.isEmpty()
        };
    } else if (model && position) {
        const offset = model.getOffsetAt(position);
        selectionOffsets = { start: offset, end: offset, isEmpty: true };
    }
    return {
        selectionOffsets,
        scrollTop: editor.getScrollTop(),
        scrollLeft: editor.getScrollLeft()
    };
}

function monacoRestoreEditorViewState(editor, state) {
    if (!editor || !state) return;
    const model = editor.getModel();
    if (model && state.selectionOffsets) {
        try {
            const maxOffset = model.getValueLength();
            const start = model.getPositionAt(Math.min(state.selectionOffsets.start, maxOffset));
            const end = model.getPositionAt(Math.min(state.selectionOffsets.end, maxOffset));
            if (state.selectionOffsets.isEmpty) {
                editor.setPosition(start);
            } else {
                editor.setSelection(new monaco.Range(start.lineNumber, start.column, end.lineNumber, end.column));
            }
        } catch (_) {}
    }
    if (typeof state.scrollTop === 'number') editor.setScrollTop(state.scrollTop);
    if (typeof state.scrollLeft === 'number') editor.setScrollLeft(state.scrollLeft);
}

function monacoCollabClientStyleSlug(clientId) {
    return String(clientId || '').replace(/[^a-z0-9_-]/gi, '-');
}

function monacoHexToRgba(hex, alpha) {
    const normalized = (hex || '').replace('#', '').trim();
    if (!/^[0-9a-f]{6}$/i.test(normalized)) return `rgba(59,130,246,${alpha})`;
    const value = parseInt(normalized, 16);
    const r = (value >> 16) & 255;
    const g = (value >> 8) & 255;
    const b = value & 255;
    return `rgba(${r},${g},${b},${alpha})`;
}

function monacoEnsureCollabCharDelayClass(clientId, delayMs) {
    const slug = monacoCollabClientStyleSlug(clientId);
    const rounded = Math.round(delayMs);
    const className = `monaco-collab-insert-char-${slug}-d${rounded}`;
    const styleId = `monaco-collab-delay-${slug}-${rounded}`;
    if (document.getElementById(styleId)) return className;
    const style = document.createElement('style');
    style.id = styleId;
    style.textContent = `.${className}{animation-delay:${rounded}ms!important;animation-duration:400ms!important;}`;
    document.head.appendChild(style);
    return className;
}

function monacoEnsureCollabInsertStyle(clientId, color) {
    const slug = monacoCollabClientStyleSlug(clientId);
    const styleId = `monaco-collab-insert-style-${slug}`;
    if (monacoCollabInsertStyleIds.has(styleId)) return `monaco-collab-insert-${slug}`;
    const hit = monacoHexToRgba(color, 0.42);
    const style = document.createElement('style');
    style.id = styleId;
    style.textContent = `
        @keyframes monaco-collab-insert-sweep-${slug} {
            0% {
                background-image: linear-gradient(90deg, ${hit} 0%, ${hit} 55%, transparent 55%);
                background-size: 220% 100%;
                background-position: 0% 0;
                opacity: 1;
            }
            100% {
                background-image: linear-gradient(90deg, ${hit} 0%, ${hit} 55%, transparent 55%);
                background-size: 220% 100%;
                background-position: 100% 0;
                opacity: 0;
            }
        }
        .monaco-collab-insert-${slug} {
            background-repeat: no-repeat;
            box-decoration-break: clone;
            -webkit-box-decoration-break: clone;
            animation: monaco-collab-insert-sweep-${slug} 1.5s linear forwards;
        }
        .monaco-collab-insert-char-${slug} {
            background-color: ${hit};
            box-decoration-break: clone;
            -webkit-box-decoration-break: clone;
            animation: monaco-collab-insert-char-${slug} 0.4s ease-out forwards;
        }
        @keyframes monaco-collab-insert-char-${slug} {
            from { background-color: ${hit}; }
            to { background-color: transparent; }
        }
    `;
    document.head.appendChild(style);
    monacoCollabInsertStyleIds.add(styleId);
    return `monaco-collab-insert-${slug}`;
}

function monacoRangeForRemoteInsert(model, change) {
    if (!model || !change?.text) return null;
    const text = String(change.text);
    if (!text.length) return null;
    const startPos = {
        lineNumber: change.range.startLineNumber,
        column: change.range.startColumn
    };
    const startOffset = model.getOffsetAt(startPos);
    const endOffset = startOffset + text.length;
    const endPos = model.getPositionAt(Math.min(endOffset, model.getValueLength()));
    return new monaco.Range(startPos.lineNumber, startPos.column, endPos.lineNumber, endPos.column);
}

function monacoScheduleCollabDecorationRemoval(editor, tabId, decorationIds, delayMs) {
    if (!decorationIds.length) return;
    setTimeout(() => {
        try {
            const current = monacoCollabInsertDecorations.get(tabId) || [];
            const remaining = current.filter((id) => !decorationIds.includes(id));
            editor.deltaDecorations(decorationIds, []);
            monacoCollabInsertDecorations.set(tabId, remaining);
        } catch (_) {}
    }, delayMs);
}

function monacoBuildCollabInsertDecorations(model, clientId, change, color) {
    const text = String(change?.text ?? '');
    if (!text.length || !model) return [];
    const slug = monacoCollabClientStyleSlug(clientId);
    monacoEnsureCollabInsertStyle(clientId, color || '#3b82f6');
    const charClass = `monaco-collab-insert-char-${slug}`;
    const sweepClass = `monaco-collab-insert-${slug}`;
    const totalMs = 1500;
    const fadeMs = 400;
    const range = monacoRangeForRemoteInsert(model, change);
    if (!range) return [];
    if (text.length > 400) {
        return [{
            range,
            options: {
                inlineClassName: sweepClass,
                stickiness: monaco.editor.TrackedRangeStickiness.NeverGrowsWhenTypingAtEdges
            }
        }];
    }
    const decorations = [];
    const step = text.length > 1 ? (totalMs - fadeMs) / (text.length - 1) : 0;
    let offset = model.getOffsetAt(range.getStartPosition());
    let charIndex = 0;
    for (let i = 0; i < text.length; i++) {
        const ch = text[i];
        if (ch === '\r') continue;
        const charStart = model.getPositionAt(offset);
        offset += 1;
        const charEnd = model.getPositionAt(Math.min(offset, model.getValueLength()));
        const delayClass = monacoEnsureCollabCharDelayClass(clientId, charIndex * step);
        charIndex += 1;
        decorations.push({
            range: new monaco.Range(charStart.lineNumber, charStart.column, charEnd.lineNumber, charEnd.column),
            options: {
                inlineClassName: `${charClass} ${delayClass}`,
                stickiness: monaco.editor.TrackedRangeStickiness.NeverGrowsWhenTypingAtEdges
            }
        });
    }
    return decorations;
}

function monacoAnimateRemoteCollabInserts(editor, tabId, clientId, changes, color) {
    const model = editor?.getModel();
    if (!editor || !model || !Array.isArray(changes) || typeof monaco === 'undefined') return;
    const decorations = [];
    changes.forEach((change) => {
        decorations.push(...monacoBuildCollabInsertDecorations(model, clientId, change, color));
    });
    if (!decorations.length) return;
    const addedIds = editor.deltaDecorations([], decorations);
    const allIds = (monacoCollabInsertDecorations.get(tabId) || []).concat(addedIds);
    monacoCollabInsertDecorations.set(tabId, allIds);
    monacoScheduleCollabDecorationRemoval(editor, tabId, addedIds, 1650);
}

function monacoRunCollabRemoteApply(editor, fn) {
    if (!editor || typeof fn !== 'function') return;
    const owned = !editor.__collabApplyingRemote;
    if (owned) editor.__collabApplyingRemote = true;
    try {
        fn();
    } finally {
        if (owned) {
            queueMicrotask(() => {
                editor.__collabApplyingRemote = false;
            });
        }
    }
}
function monacoApplyRemoteCollabEdits(editor, edits) {
    if (!editor || !edits?.length) return;
    monacoRunCollabRemoteApply(editor, () => {
        const scrollTop = editor.getScrollTop();
        const scrollLeft = editor.getScrollLeft();
        const savedSelections = editor.getSelections();
        editor.pushUndoStop();
        editor.executeEdits('collab-ws', edits, () => savedSelections);
        editor.setScrollTop(scrollTop);
        editor.setScrollLeft(scrollLeft);
    });
}

function monacoRemoteCursorWidgetMap(tabId) {
    if (!monacoRemoteCursorWidgets.has(tabId)) {
        monacoRemoteCursorWidgets.set(tabId, new Map());
    }
    return monacoRemoteCursorWidgets.get(tabId);
}

function monacoCreateRemoteCursorWidget(editor, clientId, color, name) {
    const domNode = document.createElement('div');
    domNode.className = 'monaco-remote-cursor-widget';
    domNode.style.pointerEvents = 'none';
    const caret = document.createElement('div');
    caret.className = 'monaco-remote-cursor-widget-caret';
    caret.style.cssText = `width:2px;height:1.2em;background:${color};margin-left:-1px;`;
    domNode.appendChild(caret);
    let labelNode = null;
    if (name) {
        // On the first line place the label below the caret so it is not clipped
        const initialLabelTop = '0.2em';
        labelNode = document.createElement('div');
        labelNode.className = 'monaco-remote-cursor-widget-label';
        labelNode.textContent = name;
        labelNode.style.cssText = `color:#fff;background:${color};border-radius:999px;padding:1px 6px;margin-left:4px;font-size:11px;line-height:16px;white-space:nowrap;position:absolute;top:${initialLabelTop};left:0;`;
        domNode.appendChild(labelNode);
    }
    const widget = {
        editor,
        clientId,
        color,
        name: name || '',
        domNode,
        caretNode: caret,
        labelNode,
        position: { lineNumber: 1, column: 1 },
        getId() {
            return `monaco-remote-cursor-${monacoCollabClientStyleSlug(clientId)}`;
        },
        getDomNode() {
            return domNode;
        },
        getPosition() {
            return {
                position: this.position,
                preference: [monaco.editor.ContentWidgetPositionPreference.EXACT]
            };
        },
        update(position, nextColor, nextName) {
            this.position = position;
            this.color = nextColor;
            this.name = nextName || '';
            this.caretNode.style.background = nextColor;
            // On the first line the label must go BELOW the caret, otherwise it gets
            // clipped by the top edge of the editor / viewport.
            const labelTop = ((position && position.lineNumber) || 1) <= 1 ? '0.2em' : '-1.4em';
            if (this.labelNode) {
                this.labelNode.textContent = this.name;
                this.labelNode.style.background = nextColor;
                this.labelNode.style.display = this.name ? '' : 'none';
                this.labelNode.style.top = labelTop;
            } else if (this.name) {
                this.labelNode = document.createElement('div');
                this.labelNode.className = 'monaco-remote-cursor-widget-label';
                this.labelNode.textContent = this.name;
                this.labelNode.style.cssText = `color:#fff;background:${nextColor};border-radius:999px;padding:1px 6px;margin-left:4px;font-size:11px;line-height:16px;white-space:nowrap;position:absolute;top:${labelTop};left:0;`;
                this.domNode.appendChild(this.labelNode);
            }
            this.editor.layoutContentWidget(this);
        },
        dispose() {
            try { this.editor.removeContentWidget(this); } catch (_) {}
        }
    };
    editor.addContentWidget(widget);
    return widget;
}

function monacoSetRemoteCursor(tabId, clientId, cursor) {
    const editor = monacoEditors.get(tabId);
    const model = editor?.getModel();
    if (!editor || !model || !cursor?.position) return;
    const perTab = monacoRemoteCursorWidgetMap(tabId);
    const color = cursor.color || monacoCollabColorForClient(clientId);
    const lineNumber = Math.max(1, Math.min(Number(cursor.position.lineNumber) || 1, model.getLineCount()));
    const column = Math.max(1, Math.min(Number(cursor.position.column) || 1, model.getLineMaxColumn(lineNumber)));
    const position = { lineNumber, column };
    let widget = perTab.get(clientId);
    if (!widget) {
        widget = monacoCreateRemoteCursorWidget(editor, clientId, color, cursor.name || '');
        perTab.set(clientId, widget);
    }
    widget.update(position, color, cursor.name || '');
}

function monacoClearRemoteCursor(tabId, clientId) {
    const perTab = monacoRemoteCursorWidgets.get(tabId);
    if (!perTab || !perTab.has(clientId)) return;
    perTab.get(clientId).dispose();
    perTab.delete(clientId);
    if (perTab.size === 0) monacoRemoteCursorWidgets.delete(tabId);
}

function monacoClearAllRemoteCursors(tabId) {
    const perTab = monacoRemoteCursorWidgets.get(tabId);
    if (!perTab || perTab.size === 0) return;
    perTab.forEach((widget) => widget.dispose());
    monacoRemoteCursorWidgets.delete(tabId);
}

function monacoGetUndoRedoState(editor) {
    const model = editor?.getModel();
    return {
        canUndo: !!(model && typeof model.canUndo === 'function' && model.canUndo()),
        canRedo: !!(model && typeof model.canRedo === 'function' && model.canRedo())
    };
}

function monacoUndo(tabId) {
    const editor = monacoEditors.get(tabId);
    if (!editor) return false;
    editor.trigger('toolbar', 'undo', null);
    return true;
}

function monacoRedo(tabId) {
    const editor = monacoEditors.get(tabId);
    if (!editor) return false;
    editor.trigger('toolbar', 'redo', null);
    return true;
}

async function monacoGetAiRuntimeConfig() {
    if (!monacoAiRuntimeConfigPromise) {
        monacoAiRuntimeConfigPromise = fetch('ai.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            body: new URLSearchParams({ action: 'ai_runtime_config' })
        }).then(async (response) => {
            const data = await response.json();
            if (!response.ok || !data?.success) {
                throw new Error(data?.error || 'AI runtime config is unavailable');
            }
            // Do not memoize forever: drop the cached promise so a later call
            // re-fetches fresh config (admin may have changed the model list).
            setTimeout(() => { monacoAiRuntimeConfigPromise = null; }, 30000);
            return data;
        }).catch((error) => {
            monacoAiRuntimeConfigPromise = null;
            throw error;
        });
    }
    return monacoAiRuntimeConfigPromise;
}

function monacoBuildAiPrompt(instruction, text) {
    const safeText = String(text ?? '');
    const safeInstruction = String(instruction ?? '').trim() || 'Fix spelling, punctuation, and obvious grammar errors.';
    return [
        safeInstruction,
        'Верни только готовый текст для прямой замены выделения.',
        'Нельзя добавлять пояснения, кавычки, markdown, списки, вводные фразы или комментарии.',
        'Сохраняй переносы строк, если это не противоречит задаче.',
        '',
        safeText
    ].join('\n');
}

async function monacoRequestAiReplacement(instruction, text) {
    const runtime = await monacoGetAiRuntimeConfig();
    const response = await fetch('ai.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
        body: new URLSearchParams({
            action: 'chat',
            role: 'general',
            model: runtime.default_model || '',
            message: monacoBuildAiPrompt(instruction, text)
        })
    });
    const data = await response.json();
    if (!response.ok || data?.error) {
        throw new Error(data?.error || 'AI request failed');
    }
    return String(data.response ?? '').trim();
}

function monacoRefreshAiActionOverlayLabels(overlay) {
    if (!overlay) return;
    const title = overlay.querySelector('[data-role="title"]');
    if (title) title.textContent = monacoT('monacoAskAi');
    const promptEl = overlay.querySelector('[data-role="prompt"]');
    if (promptEl) {
        const placeholder = monacoT('monacoAiPromptPlaceholder');
        promptEl.placeholder = placeholder === 'monacoAiPromptPlaceholder'
            ? 'What should AI change?'
            : placeholder;
    }
    const applyBtn = overlay.querySelector('[data-role="apply"]');
    if (applyBtn) applyBtn.textContent = monacoT('apply') || 'Apply';
    const undoBtn = overlay.querySelector('[data-role="undo"]');
    if (undoBtn) undoBtn.textContent = monacoT('monacoAiUndo');
}

function monacoEnsureAiActionOverlay(hostContainer) {
    if (monacoAiActionOverlay && monacoAiActionOverlay.parentElement) {
        if (!monacoAiActionOverlay.querySelector('[data-role="prompt"]')) {
            monacoAiActionOverlay.remove();
            monacoAiActionOverlay = null;
        } else {
            if (hostContainer && monacoAiActionOverlay.parentElement !== hostContainer) {
                hostContainer.appendChild(monacoAiActionOverlay);
            }
            monacoRefreshAiActionOverlayLabels(monacoAiActionOverlay);
            return monacoAiActionOverlay;
        }
    }
    const overlay = document.createElement('div');
    overlay.className = 'monaco-ai-action-overlay hidden';
    overlay.style.cssText = [
        'position:absolute',
        'z-index:20000',
        'width:min(320px,calc(100% - 24px))',
        'padding:12px',
        'border:1px solid rgba(255,255,255,0.18)',
        'border-radius:14px',
        'backdrop-filter:blur(14px)',
        'background:color-mix(in srgb,var(--panel) 78%,transparent)',
        'box-shadow:0 18px 48px rgba(0,0,0,0.28)',
        'display:flex',
        'flex-direction:column',
        'gap:10px',
        'touch-action:none'
    ].join(';');
    overlay.innerHTML = `
        <div data-role="drag-handle" style="display:flex;align-items:center;justify-content:space-between;gap:8px;cursor:move;user-select:none;">
            <strong data-role="title" style="font-size:13px;"></strong>
            <button type="button" data-role="close" class="icon-btn" style="width:28px;height:28px;min-width:28px;cursor:pointer;">×</button>
        </div>
        <textarea data-role="prompt" rows="2" style="width:100%;min-height:52px;max-height:120px;padding:8px 10px;border-radius:10px;border:1px solid var(--border);background:var(--bg);color:var(--fg);resize:vertical;box-sizing:border-box;font:inherit;"></textarea>
        <div data-role="status" style="min-height:18px;font-size:12px;color:var(--muted);"></div>
        <div style="display:flex;gap:8px;justify-content:flex-end;">
            <button type="button" data-role="undo" class="btn secondary small hidden"></button>
            <button type="button" data-role="apply" class="btn primary small"></button>
        </div>
    `;
    const mountTarget = hostContainer || document.body;
    if (getComputedStyle(mountTarget).position === 'static') {
        mountTarget.style.position = 'relative';
    }
    mountTarget.appendChild(overlay);
    monacoRefreshAiActionOverlayLabels(overlay);
    const closeOverlay = () => overlay.classList.add('hidden');
    overlay.querySelector('[data-role="close"]').addEventListener('click', closeOverlay);
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') closeOverlay();
    });
    const dragHandle = overlay.querySelector('[data-role="drag-handle"]');
    let dragState = null;
    dragHandle.addEventListener('pointerdown', (event) => {
        if (event.target.closest('[data-role="close"]')) return;
        const parentRect = overlay.parentElement.getBoundingClientRect();
        dragState = {
            pointerId: event.pointerId,
            startX: event.clientX,
            startY: event.clientY,
            originLeft: overlay.offsetLeft,
            originTop: overlay.offsetTop,
            maxLeft: Math.max(0, parentRect.width - overlay.offsetWidth),
            maxTop: Math.max(0, parentRect.height - overlay.offsetHeight)
        };
        dragHandle.setPointerCapture(event.pointerId);
        event.preventDefault();
    });
    dragHandle.addEventListener('pointermove', (event) => {
        if (!dragState || dragState.pointerId !== event.pointerId) return;
        const nextLeft = Math.min(dragState.maxLeft, Math.max(0, dragState.originLeft + (event.clientX - dragState.startX)));
        const nextTop = Math.min(dragState.maxTop, Math.max(0, dragState.originTop + (event.clientY - dragState.startY)));
        overlay.style.left = `${nextLeft}px`;
        overlay.style.top = `${nextTop}px`;
    });
    const endDrag = (event) => {
        if (!dragState || dragState.pointerId !== event.pointerId) return;
        dragState = null;
        try { dragHandle.releasePointerCapture(event.pointerId); } catch (_) {}
    };
    dragHandle.addEventListener('pointerup', endDrag);
    dragHandle.addEventListener('pointercancel', endDrag);
    monacoAiActionOverlay = overlay;
    return overlay;
}

function monacoPositionAiOverlay(overlay, container, x, y) {
    overlay.classList.remove('hidden');
    const parentRect = container.getBoundingClientRect();
    const localX = x - parentRect.left;
    const localY = y - parentRect.top;
    overlay.style.left = '0px';
    overlay.style.top = '0px';
    const margin = 12;
    const rect = overlay.getBoundingClientRect();
    const maxLeft = Math.max(margin, container.clientWidth - rect.width - margin);
    const maxTop = Math.max(margin, container.clientHeight - rect.height - margin);
    overlay.style.left = `${Math.min(Math.max(margin, localX), maxLeft)}px`;
    overlay.style.top = `${Math.min(Math.max(margin, localY), maxTop)}px`;
}

async function monacoOpenAiActionMenu(editor, container) {
    const selectedText = monacoGetSelectedText(editor);
    if (!selectedText) return;
    const overlay = monacoEnsureAiActionOverlay(container);
    monacoRefreshAiActionOverlayLabels(overlay);
    const promptEl = overlay.querySelector('[data-role="prompt"]');
    const statusEl = overlay.querySelector('[data-role="status"]');
    const applyBtn = overlay.querySelector('[data-role="apply"]');
    const undoBtn = overlay.querySelector('[data-role="undo"]');
    const selection = editor.getSelection();
    const selectedRange = selection && !selection.isEmpty() ? selection : null;
    if (!selectedRange) return;

    const cursorCoords = editor.getScrolledVisiblePosition(selectedRange.getStartPosition());
    const containerRect = container.getBoundingClientRect();
    const anchorX = containerRect.left + ((cursorCoords?.left ?? 0) + 12);
    const anchorY = containerRect.top + ((cursorCoords?.top ?? 0) + 28);
    monacoPositionAiOverlay(overlay, container, anchorX, anchorY);
    statusEl.textContent = '';
    undoBtn.classList.add('hidden');
    if (promptEl && !promptEl.value) {
        requestAnimationFrame(() => promptEl.focus());
    }

    let canUndoAiEdit = false;
    undoBtn.onclick = () => {
        if (!canUndoAiEdit) return;
        editor.trigger('ai-inline-undo', 'undo', null);
        canUndoAiEdit = false;
        undoBtn.classList.add('hidden');
        statusEl.textContent = '';
    };

    applyBtn.onclick = async () => {
        const latestSelection = editor.getSelection();
        if (!latestSelection || latestSelection.isEmpty()) return;
        const originalText = monacoGetSelectedText(editor);
        const instruction = (promptEl?.value || '').trim();
        applyBtn.disabled = true;
        statusEl.textContent = monacoT('monacoAiProcessing');
        try {
            const replacement = await monacoRequestAiReplacement(instruction, originalText);
            editor.pushUndoStop();
            editor.executeEdits('ai-inline-rewrite', [{
                range: latestSelection,
                text: replacement,
                forceMoveMarkers: true
            }]);
            editor.pushUndoStop();
            canUndoAiEdit = true;
            statusEl.textContent = monacoT('monacoAiReplaced');
            undoBtn.classList.remove('hidden');
        } catch (error) {
            statusEl.textContent = error.message || 'Ошибка AI';
        } finally {
            applyBtn.disabled = false;
        }
    };
}

function monacoReplaceContentAsUndoable(tabId, content, source = 'ai-agent-edit') {
    const editor = monacoEditors.get(tabId);
    const model = editor?.getModel();
    if (!editor || !model) return false;

    editor.pushUndoStop();
    editor.executeEdits(source, [{
        range: model.getFullModelRange(),
        text: String(content ?? ''),
        forceMoveMarkers: true
    }]);
    editor.pushUndoStop();
    editor.setPosition({ lineNumber: 1, column: 1 });
    editor.revealLineInCenter(1);
    return true;
}

function monacoReplaceContentPreservingView(tabId, content, source = 'remote-sync') {
    const editor = monacoEditors.get(tabId);
    const model = editor?.getModel();
    if (!editor || !model) return false;
    const apply = () => {
        const scrollTop = editor.getScrollTop();
        const scrollLeft = editor.getScrollLeft();
        const savedSelections = editor.getSelections();
        editor.pushUndoStop();
        editor.executeEdits(source, [{
            range: model.getFullModelRange(),
            text: String(content ?? ''),
            forceMoveMarkers: true
        }], () => savedSelections);
        editor.setScrollTop(scrollTop);
        editor.setScrollLeft(scrollLeft);
    };
    if (source === 'collab-sync' || source === 'remote-sync') {
        monacoRunCollabRemoteApply(editor, apply);
    } else {
        apply();
    }
    return true;
}

function monacoComputeChangedLineRanges(oldText, newText) {
    const oldLines = String(oldText ?? '').split(/\r\n|\r|\n/);
    const newLines = String(newText ?? '').split(/\r\n|\r|\n/);
    const m = oldLines.length;
    const n = newLines.length;
    const dp = Array.from({ length: m + 1 }, () => new Array(n + 1).fill(0));

    for (let i = m - 1; i >= 0; i--) {
        for (let j = n - 1; j >= 0; j--) {
            dp[i][j] = oldLines[i] === newLines[j]
                ? dp[i + 1][j + 1] + 1
                : Math.max(dp[i + 1][j], dp[i][j + 1]);
        }
    }

    const ranges = [];
    let i = 0;
    let j = 0;
    let pendingNewStart = null;
    let pendingNewEnd = null;
    let pendingDeleteAt = null;

    const flush = () => {
        if (pendingNewStart !== null) {
            ranges.push({
                startLineNumber: Math.max(1, pendingNewStart + 1),
                endLineNumber: Math.max(1, pendingNewEnd + 1)
            });
        } else if (pendingDeleteAt !== null && n > 0) {
            const line = Math.min(n, Math.max(1, pendingDeleteAt + 1));
            ranges.push({ startLineNumber: line, endLineNumber: line });
        }
        pendingNewStart = null;
        pendingNewEnd = null;
        pendingDeleteAt = null;
    };

    while (i < m || j < n) {
        if (i < m && j < n && oldLines[i] === newLines[j]) {
            flush();
            i++;
            j++;
        } else if (j < n && (i === m || dp[i][j + 1] >= dp[i + 1][j])) {
            if (pendingNewStart === null) pendingNewStart = j;
            pendingNewEnd = j;
            j++;
        } else if (i < m) {
            if (pendingNewStart === null) pendingDeleteAt = Math.max(0, j - 1);
            i++;
        }
    }
    flush();

    return ranges;
}

function monacoBuildLineDiff(oldText, newText) {
    const oldLines = String(oldText ?? '').split(/\r\n|\r|\n/);
    const newLines = String(newText ?? '').split(/\r\n|\r|\n/);
    const m = oldLines.length;
    const n = newLines.length;
    const dp = Array.from({ length: m + 1 }, () => new Array(n + 1).fill(0));

    for (let i = m - 1; i >= 0; i--) {
        for (let j = n - 1; j >= 0; j--) {
            dp[i][j] = oldLines[i] === newLines[j]
                ? dp[i + 1][j + 1] + 1
                : Math.max(dp[i + 1][j], dp[i][j + 1]);
        }
    }

    const rows = [];
    let i = 0;
    let j = 0;
    while (i < m || j < n) {
        if (i < m && j < n && oldLines[i] === newLines[j]) {
            rows.push({ type: 'context', text: oldLines[i], oldLineNumber: i + 1, newLineNumber: j + 1 });
            i++;
            j++;
        } else if (j < n && (i === m || dp[i][j + 1] >= dp[i + 1][j])) {
            rows.push({ type: 'added', text: newLines[j], newLineNumber: j + 1 });
            j++;
        } else if (i < m) {
            rows.push({ type: 'removed', text: oldLines[i], oldLineNumber: i + 1, afterLineNumber: j });
            i++;
        }
    }

    return rows;
}

function monacoEscapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text == null ? '' : String(text);
    return div.innerHTML;
}

function monacoCreateRemovedZoneNode(lines) {
    const node = document.createElement('div');
    node.className = 'monaco-ai-removed-zone';
    node.style.cssText = [
        'box-sizing:border-box',
        'width:100%',
        'font-family:"JetBrains Mono", Consolas, "Courier New", monospace',
        'font-size:12px',
        'line-height:1.35',
        'border-top:1px solid rgba(220,38,38,0.32)',
        'border-bottom:1px solid rgba(220,38,38,0.32)',
        'background:rgba(220,38,38,0.16)',
        'color:#ffb4b4',
        'overflow:hidden'
    ].join(';');
    node.innerHTML = lines.map((line) => (
        `<div style="display:grid;grid-template-columns:24px 1fr;gap:8px;padding:2px 10px;white-space:normal;overflow-wrap:anywhere;"><span style="opacity:.85;user-select:none;">-</span><span style="white-space:pre-wrap;">${monacoEscapeHtml(line)}</span></div>`
    )).join('');
    return node;
}

function monacoShowRemovedLineZones(tabId, editor, diffRows) {
    monacoClearAiEditZones(tabId);
    const removedGroups = [];
    let current = null;

    diffRows.forEach((row) => {
        if (row.type !== 'removed') {
            current = null;
            return;
        }
        const afterLineNumber = Math.max(0, Number(row.afterLineNumber) || 0);
        if (!current || current.afterLineNumber !== afterLineNumber) {
            current = { afterLineNumber, lines: [] };
            removedGroups.push(current);
        }
        current.lines.push(row.text);
    });

    if (removedGroups.length === 0) return;

    const zoneIds = [];
    editor.changeViewZones((accessor) => {
        removedGroups.forEach((group) => {
            zoneIds.push(accessor.addZone({
                afterLineNumber: group.afterLineNumber,
                heightInLines: Math.max(1, group.lines.length),
                domNode: monacoCreateRemovedZoneNode(group.lines)
            }));
        });
    });
    monacoAiEditZones.set(tabId, zoneIds);
}

function monacoClearAiEditZones(tabId) {
    const editor = monacoEditors.get(tabId);
    const zoneIds = monacoAiEditZones.get(tabId) || [];
    if (editor && zoneIds.length) {
        editor.changeViewZones((accessor) => {
            zoneIds.forEach((id) => accessor.removeZone(id));
        });
    }
    monacoAiEditZones.delete(tabId);
}

function monacoHighlightAiEdit(tabId, oldText, newText) {
    const editor = monacoEditors.get(tabId);
    const model = editor?.getModel();
    if (!editor || !model || typeof monaco === 'undefined') return [];

    const diffRows = monacoBuildLineDiff(oldText, newText);
    const ranges = monacoComputeChangedLineRanges(oldText, newText);
    const addedLineNumbers = new Set(diffRows.filter(row => row.type === 'added').map(row => row.newLineNumber));
    const decorationRanges = addedLineNumbers.size > 0
        ? Array.from(addedLineNumbers).sort((a, b) => a - b).map((lineNumber) => ({ startLineNumber: lineNumber, endLineNumber: lineNumber }))
        : ranges;
    const decorations = decorationRanges.map((range) => ({
        range: new monaco.Range(
            range.startLineNumber,
            1,
            range.endLineNumber,
            model.getLineMaxColumn(Math.min(range.endLineNumber, model.getLineCount()))
        ),
        options: {
            isWholeLine: true,
            className: 'ai-agent-edit-line',
            linesDecorationsClassName: 'ai-agent-edit-glyph',
            overviewRuler: {
                color: '#85b503',
                position: monaco.editor.OverviewRulerLane.Right
            },
            minimap: {
                color: '#85b503',
                position: monaco.editor.MinimapPosition.Inline
            }
        }
    }));

    const previous = monacoAiEditDecorations.get(tabId) || [];
    const next = editor.deltaDecorations(previous, decorations);
    monacoAiEditDecorations.set(tabId, next);
    monacoShowRemovedLineZones(tabId, editor, diffRows);

    if (ranges.length > 0) {
        editor.revealLineInCenter(ranges[0].startLineNumber);
    }

    return ranges;
}

function monacoClearAiEditHighlight(tabId) {
    const editor = monacoEditors.get(tabId);
    monacoClearAiEditZones(tabId);
    if (!editor) return;
    const previous = monacoAiEditDecorations.get(tabId) || [];
    if (previous.length) editor.deltaDecorations(previous, []);
    monacoAiEditDecorations.delete(tabId);
}

// Dispose editor
function monacoDisposeEditor(tabId) {
    const editor = monacoEditors.get(tabId);
    if (editor) {
        monacoClearAiEditHighlight(tabId);
        monacoClearAllRemoteCursors(tabId);
        const collabDecos = monacoCollabInsertDecorations.get(tabId);
        if (collabDecos?.length) editor.deltaDecorations(collabDecos, []);
        monacoCollabInsertDecorations.delete(tabId);
        monacoDisposeSpellcheck(editor);
        monacoDisposeExtraDisposables(editor);
        editor.dispose();
        monacoEditors.delete(tabId);
    }
    monacoReleaseModelByTab(tabId);
}

// Update theme
function monacoUpdateThemeFromApp() {
    if (monacoInitialized) {
        monacoUpdateTheme();
        monacoEditors.forEach(editor => {
            editor.updateOptions({
                theme: document.body.getAttribute('data-theme') === 'dark' ? 'custom-dark' : 'custom-light'
            });
        });
    }
}

// Update minimap visibility based on orientation
function monacoUpdateMinimapVisibility() {
    if (monacoInitialized) {
        const hideMinimap = monacoShouldHideMinimap();
        monacoEditors.forEach(editor => {
            editor.updateOptions({
                minimap: {
                    enabled: !hideMinimap,
                    maxColumn: 80,
                    renderCharacters: true,
                    scale: 1,
                    showSlider: 'always',
                    side: 'right',
                    size: 'proportional'
                }
            });
        });
    }
}

// Resize editors
function monacoResizeEditors() {
    monacoEditors.forEach(editor => {
        editor.layout();
    });
}

// Listen for orientation changes
window.addEventListener('orientationchange', function() {
    setTimeout(monacoUpdateMinimapVisibility, 300);
});

// Also listen for resize events (covers desktop window resizing)
window.addEventListener('resize', function() {
    monacoUpdateMinimapVisibility();
});

// UMD libs (e.g. KaTeX auto-render, loaded with defer) check `define.amd` before
// calling define() anonymously. Removing that marker makes them fall back to global
// export without breaking Monaco's loader, which uses the function itself internally.
if (typeof window.define !== 'undefined') delete window.define.amd;

(function cloudTreeUiEnhancements() {
    const MOBILE_MQ = '(max-width: 768px)';
    const FILE_DRAG_THRESHOLD = 6;
    const MOVE_ICON_SVG = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 5v14"/><path d="M21 12H7"/><path d="m15 18 6-6-6-6"/></svg>';
    let fileDragState = null;
    let dragGhost = null;
    let dragArrow = null;
    let dropTargetNode = null;
    let rootToolbarDropBtn = null;
    let rootSwitchToken = 0;
    let folderExpandHoverTimer = null;
    let folderExpandHoverPath = '';

    function isAiModalOpen() {
        const modal = document.getElementById('ai-modal');
        return !!(modal && !modal.classList.contains('hidden'));
    }

    function isPointerOverAiAssistantPanel(x, y) {
        if (!isAiModalOpen()) return false;
        const surface = document.querySelector('#ai-modal .ai-modal-content');
        if (!surface) return false;
        const r = surface.getBoundingClientRect();
        return x >= r.left && x <= r.right && y >= r.top && y <= r.bottom;
    }

    function tryAttachTreeFileToAiAssistant(path, root) {
        const normalized = String(path || '').replace(/\\/g, '/').replace(/^\/+/, '').trim();
        if (!normalized || normalized.includes('..')) return false;
        if (typeof window.aiAssistant?.attachMentionFromTree === 'function') {
            return window.aiAssistant.attachMentionFromTree(normalized, root);
        }
        return false;
    }

    function injectTreeUiStyles() {
        if (document.getElementById('cloud-tree-ui-styles')) return;
        const style = document.createElement('style');
        style.id = 'cloud-tree-ui-styles';
        style.textContent = `
@media (max-width:768px){
  .layout.cloud-tree-sidebar-open .root-toolbar-top-separator{height:0;min-height:0;margin:0;padding:0;overflow:hidden}
  .layout.cloud-tree-sidebar-open .root-toolbar-top-separator::before{display:none}
  .layout.cloud-tree-sidebar-open .section-title{padding-left:12px}
}
.tree .node.tree-drop-target{outline:2px dashed var(--accent);outline-offset:-2px;background:color-mix(in srgb,var(--accent) 12%,transparent)!important}
.tree .node.tree-drag-source{opacity:.55}
#tree.tree-external-drop-active,#tree.tree-root-drop-active{outline:2px dashed var(--accent);outline-offset:-4px;background:color-mix(in srgb,var(--accent) 6%,transparent)}

#tree.cloud-tree-root-switching{opacity:0;transform:translateX(10px);transition:opacity .18s ease,transform .18s ease}
.cloud-tree-drag-ghost{position:fixed;z-index:40000;pointer-events:none;padding:6px 10px;border-radius:8px;background:var(--panel);border:1px solid var(--accent);box-shadow:0 8px 24px rgba(0,0,0,.22);font-size:13px;max-width:240px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.cloud-tree-move-icon{position:fixed;z-index:40001;pointer-events:none;width:24px;height:24px;color:var(--accent);filter:drop-shadow(0 1px 2px rgba(0,0,0,.25))}
.root-toolbar-btn.tree-root-drop-target{border-color:var(--accent)!important;box-shadow:0 0 0 3px color-mix(in srgb,var(--accent) 22%,transparent)!important;background:color-mix(in srgb,var(--accent) 10%,transparent)!important}
`;
        document.head.appendChild(style);
    }

    function isPlainTextTreeFile(path) {
        if (typeof window.isMonacoEditFile === 'function') return window.isMonacoEditFile(path);
        if (!path || typeof isTextFile !== 'function' || !isTextFile(path)) return false;
        if (typeof isMarkdownFile === 'function' && isMarkdownFile(path)) return false;
        const ext = String(path).split('.').pop().toLowerCase();
        if (ext === 'tex' || ext === 'latex') return false;
        if (typeof isOfficeFile === 'function' && isOfficeFile(path)) return false;
        return true;
    }

    function shouldKeepViewOption(path) {
        if (!path) return false;
        if (typeof opensDirectInEditor === 'function' && opensDirectInEditor(path)) return false;
        if (typeof isEditableImage === 'function' && isEditableImage(path)) return true;
        if (typeof isEditableAudio === 'function' && isEditableAudio(path)) return true;
        if (typeof isOfficeFile === 'function' && isOfficeFile(path)) return true;
        return false;
    }

    function openTreeFile(path) {
        if (!path) return;
        if (typeof openFileFromTree === 'function') {
            openFileFromTree(path);
            return;
        }
        if (isPlainTextTreeFile(path)) {
            if (typeof openFileInTab === 'function') openFileInTab(path, true);
            return;
        }
        if (typeof openFileInTab === 'function') openFileInTab(path, false);
    }

    function patchOpenFileInTab() {
        if (typeof openFileInTab !== 'function' || openFileInTab.__cloudTreePatched) return;
        const original = openFileInTab;
        openFileInTab = function(path, editMode = false, root = null) {
            if (editMode === false && typeof opensDirectInEditor === 'function' && opensDirectInEditor(path)) {
                if (typeof openFileFromTree === 'function') {
                    openFileFromTree(path, root);
                    return;
                }
            }
            if (editMode === false && isPlainTextTreeFile(path)) return original(path, true, root);
            return original(path, editMode, root);
        };
        openFileInTab.__cloudTreePatched = true;
        window.openFileInTab = openFileInTab;
    }

    function patchContextMenu() {
        if (typeof showContextMenu !== 'function' || showContextMenu.__cloudTreePatched) return;
        const original = showContextMenu;
        showContextMenu = function(e, path, type, name) {
            original(e, path, type, name);
            const menu = document.getElementById('context-menu');
            if (menu) menu.style.zIndex = '300000';
            if (!menu || type !== 'file' || !path) return;
            const labels = new Set([
                typeof t === 'function' && window.i18n ? t(window.i18n, 'viewFile') : 'View file',
                typeof t === 'function' && window.i18n ? t(window.i18n, 'openFile') : 'Open file',
                typeof t === 'function' && window.i18n ? t(window.i18n, 'preview') : 'Preview'
            ]);
            menu.querySelectorAll('.context-menu-item').forEach((item) => {
                const label = item.querySelector('.context-menu-item-label')?.textContent?.trim() || '';
                if (isPlainTextTreeFile(path) && labels.has(label)) item.remove();
            });
            const editLabel = typeof t === 'function' && window.i18n ? t(window.i18n, 'edit') : 'Edit';
            const hideEdit = typeof shouldHideEditMenuOption === 'function' && shouldHideEditMenuOption(path);
            menu.querySelectorAll('.context-menu-item').forEach((item) => {
                const label = item.querySelector('.context-menu-item-label')?.textContent?.trim() || '';
                if (hideEdit && label === editLabel && !item.classList.contains('context-menu-group-trigger')) item.remove();
            });
        };
        showContextMenu.__cloudTreePatched = true;
        window.showContextMenu = showContextMenu;
    }

    function patchTreeAutoShift() {
        if (typeof updateTreeAutoShift === 'function' && !updateTreeAutoShift.__cloudTreePatched) {
            updateTreeAutoShift = function() {
                const tree = document.getElementById('tree');
                if (!tree) return;
                const rect = tree.getBoundingClientRect();
                const nodes = tree.querySelectorAll('.node');
                let minPad = Infinity;
                let foundVisible = false;
                for (const node of nodes) {
                    const nr = node.getBoundingClientRect();
                    if (nr.bottom < rect.top || nr.top > rect.bottom) continue;
                    foundVisible = true;
                    const pad = parseInt(node.style.paddingLeft || '0', 10) || 0;
                    if (pad < minPad) minPad = pad;
                }
                if (!foundVisible || !Number.isFinite(minPad)) minPad = 0;
                let shift = Math.max(0, minPad - 8);
                for (const node of nodes) {
                    const nr = node.getBoundingClientRect();
                    if (nr.bottom < rect.top || nr.top > rect.bottom) continue;
                    const clip = rect.left + 6 - nr.left;
                    if (clip > 0) shift = Math.max(0, shift - clip);
                }
                if (typeof treeShiftTarget !== 'undefined') treeShiftTarget = shift;
                if (!treeShiftAnimRafId && typeof requestAnimationFrame === 'function' && typeof animateTreeAutoShift === 'function') {
                    treeShiftAnimRafId = requestAnimationFrame(animateTreeAutoShift);
                }
            };
            updateTreeAutoShift.__cloudTreePatched = true;
            window.updateTreeAutoShift = updateTreeAutoShift;
        }
        if (typeof updateTreeLabelWidths === 'function' && !updateTreeLabelWidths.__cloudTreePatched) {
            const originalWidths = updateTreeLabelWidths;
            updateTreeLabelWidths = function() {
                const tree = document.getElementById('tree');
                if (!tree) return;
                const treeRect = tree.getBoundingClientRect();
                const rightEdge = treeRect.right - 6;
                tree.querySelectorAll('.node .label').forEach((label) => {
                    const node = label.closest('.node');
                    if (!node) return;
                    const nodeRect = node.getBoundingClientRect();
                    const labelRect = label.getBoundingClientRect();
                    const iconReserve = Math.max(18, labelRect.left - nodeRect.left);
                    const available = Math.floor(rightEdge - nodeRect.left - iconReserve - 4);
                    if (available > 56) {
                        label.style.maxWidth = `${available}px`;
                    } else {
                        label.style.maxWidth = '';
                    }
                });
            };
            updateTreeLabelWidths.__cloudTreePatched = true;
            window.updateTreeLabelWidths = updateTreeLabelWidths;
        }
    }

    function clearRootToolbarDropTarget() {
        if (rootToolbarDropBtn) rootToolbarDropBtn.classList.remove('tree-root-drop-target');
        rootToolbarDropBtn = null;
    }

    function clearDropTarget() {
        if (dropTargetNode) dropTargetNode.classList.remove('tree-drop-target');
        dropTargetNode = null;
    }

    function clearFolderExpandHoverTimer() {
        if (folderExpandHoverTimer) clearTimeout(folderExpandHoverTimer);
        folderExpandHoverTimer = null;
        folderExpandHoverPath = '';
    }

    function clearTreeRootDropHighlight() {
        const tree = document.getElementById('tree');
        if (tree) tree.classList.remove('tree-root-drop-active');
    }

    function clearExternalDropHighlight() {
        const tree = document.getElementById('tree');
        if (tree) tree.classList.remove('tree-external-drop-active');
    }

    function clearDragUi() {
        if (dragGhost) { dragGhost.remove(); dragGhost = null; }
        if (dragArrow) { dragArrow.remove(); dragArrow = null; }
        clearDropTarget();
        clearRootToolbarDropTarget();
        clearFolderExpandHoverTimer();
        clearTreeRootDropHighlight();
        clearExternalDropHighlight();
        document.querySelectorAll('#tree .node.tree-drag-source').forEach((n) => n.classList.remove('tree-drag-source'));
    }

    function ensureDragGhost(label) {
        if (!dragGhost) {
            dragGhost = document.createElement('div');
            dragGhost.className = 'cloud-tree-drag-ghost';
            document.body.appendChild(dragGhost);
        }
        dragGhost.textContent = label;
    }

    function hideMoveIcon() {
        if (dragArrow) { dragArrow.remove(); dragArrow = null; }
    }

    function ensureMoveIcon() {
        if (dragArrow) return;
        dragArrow = document.createElement('div');
        dragArrow.className = 'cloud-tree-move-icon';
        dragArrow.innerHTML = MOVE_ICON_SVG;
        document.body.appendChild(dragArrow);
    }

    function updateMoveIcon(x, y) {
        ensureMoveIcon();
        dragArrow.style.left = `${x + 16}px`;
        dragArrow.style.top = `${y - 12}px`;
    }

    function isValidMoveTarget(folderNode, sourcePath, sourceRoot, viewRoot, sourceType = 'file') {
        if (!folderNode || !sourcePath) return false;
        const targetDir = folderNode.dataset.path || '';
        if (targetDir === sourcePath) return false;
        // Only folders can contain their own descendants; a file may always move up into an ancestor.
        if (sourceType !== 'file' && targetDir && sourcePath.startsWith(targetDir + '/')) return false;
        const parentDir = sourcePath.includes('/') ? sourcePath.substring(0, sourcePath.lastIndexOf('/')) : '';
        if ((viewRoot || sourceRoot) === sourceRoot && targetDir === parentDir) return false;
        return true;
    }

    function folderDropTargetAt(x, y) {
        const el = document.elementFromPoint(x, y);
        const node = el?.closest?.('#tree .node[data-type="dir"]');
        if (!node || node.classList.contains('tree-drag-source')) return null;
        return node;
    }

    function isPointerOverTree(x, y) {
        const tree = document.getElementById('tree');
        if (!tree) return false;
        const rect = tree.getBoundingClientRect();
        return x >= rect.left && x <= rect.right && y >= rect.top && y <= rect.bottom;
    }

    function canDropOnTreeRoot(sourcePath, sourceRoot, viewRoot) {
        if (!sourcePath) return false;
        const parentDir = sourcePath.includes('/') ? sourcePath.substring(0, sourcePath.lastIndexOf('/')) : '';
        if (parentDir === '' && (viewRoot || sourceRoot) === sourceRoot) return false;
        return true;
    }

    function scheduleFolderExpandDuringDrag(folderNode) {
        if (!folderNode) return;
        const folderPath = folderNode.dataset.path || '';
        if (folderExpandHoverPath === folderPath && folderExpandHoverTimer) return;
        clearFolderExpandHoverTimer();
        folderExpandHoverPath = folderPath;
        folderExpandHoverTimer = setTimeout(() => {
            folderExpandHoverTimer = null;
            if (!fileDragState?.active) return;
            const children = folderNode.nextElementSibling;
            if (!children?.classList.contains('children')) return;
            if (children.style.display !== 'none') return;
            if (children.dataset.loaded === 'false') {
                const toggle = folderNode.querySelector('.toggle');
                if (toggle) toggle.click();
                return;
            }
            const toggle = folderNode.querySelector('.toggle');
            if (typeof setTreeDirToggleOpen === 'function' && toggle) setTreeDirToggleOpen(toggle, true);
            children.style.display = 'block';
            if (typeof syncTreeFolderEndLine === 'function') syncTreeFolderEndLine(children, true);
            if (typeof expandedFolders !== 'undefined' && folderPath) expandedFolders.add(folderPath);
            if (typeof saveFolderState === 'function') saveFolderState();
        }, 500);
    }

    function rootToolbarTargetAt(x, y) {
        const el = document.elementFromPoint(x, y);
        const btn = el?.closest?.('#root-toolbar .root-toolbar-btn[data-root]');
        if (btn) {
            const root = btn.dataset.root || '';
            if (root !== 'trash') return btn;
            return null;
        }
        const toolbar = document.getElementById('root-toolbar');
        if (!toolbar) return null;
        for (const b of toolbar.querySelectorAll('.root-toolbar-btn[data-root]')) {
            if ((b.dataset.root || '') === 'trash') continue;
            const r = b.getBoundingClientRect();
            if (x >= r.left && x <= r.right && y >= r.top && y <= r.bottom) return b;
        }
        return null;
    }

    async function switchTreeRootDuringDrag(rootKey) {
        if (!fileDragState || fileDragState.viewRoot === rootKey || rootKey === 'trash') return;
        const token = ++rootSwitchToken;
        fileDragState.switchingRoot = true;
        clearDropTarget();
        hideMoveIcon();
        fileDragState.hadValidDropTarget = false;
        fileDragState.dropTargetDir = '';
        const tree = document.getElementById('tree');
        if (tree) tree.classList.add('cloud-tree-root-switching');
        await new Promise((r) => setTimeout(r, 140));
        if (!fileDragState || token !== rootSwitchToken) return;
        if (typeof setCurrentCloudRoot === 'function') setCurrentCloudRoot(rootKey);
        const toolbar = document.getElementById('root-toolbar');
        if (toolbar) {
            toolbar.querySelectorAll('.root-toolbar-btn').forEach((b) => {
                b.classList.toggle('active', (b.dataset.root || 'main') === rootKey);
            });
        }
        if (typeof cachedFileTree !== 'undefined') cachedFileTree = null;
        try {
            if (typeof buildTree === 'function') await buildTree('');
            if (typeof syncRootModeView === 'function') await syncRootModeView();
        } catch (_) {}
        fileDragState.viewRoot = rootKey;
        if (tree) {
            requestAnimationFrame(() => tree.classList.remove('cloud-tree-root-switching'));
        }
        fileDragState.switchingRoot = false;
        if (fileDragState.lastX != null && fileDragState.lastY != null) {
            requestAnimationFrame(() => {
                if (!fileDragState) return;
                clearDropTarget();
                hideMoveIcon();
                const target = folderDropTargetAt(fileDragState.lastX, fileDragState.lastY);
                if (target && isValidMoveTarget(target, fileDragState.path, fileDragState.sourceRoot, fileDragState.viewRoot, fileDragState.sourceType)) {
                    dropTargetNode = target;
                    fileDragState.dropTargetDir = target.dataset.path || '';
                    fileDragState.dropTargetRoot = fileDragState.viewRoot;
                    fileDragState.hadValidDropTarget = true;
                    fileDragState.dropOnRootToolbar = false;
                    target.classList.add('tree-drop-target');
                    updateMoveIcon(fileDragState.lastX, fileDragState.lastY);
                }
            });
        }
    }

    async function moveTreeFile(sourcePath, targetDir, sourceRoot, targetRoot) {
        if (!sourcePath || sourcePath === targetDir) return;
        if (targetDir && (targetDir === sourcePath || targetDir.startsWith(sourcePath + '/'))) {
            if (typeof flashStatus === 'function' && window.i18n) flashStatus(t(window.i18n, 'cannotMoveToSubfolder'));
            return;
        }
        sourceRoot = sourceRoot || (typeof getCurrentCloudRoot === 'function' ? getCurrentCloudRoot() : 'main');
        targetRoot = targetRoot || sourceRoot;
        try {
            await api('move', {
                source_path: sourcePath,
                target_dir: targetDir || '',
                target_root: targetRoot,
                root: sourceRoot
            });
            const newName = sourcePath.split('/').pop();
            const newPath = targetDir ? `${targetDir}/${newName}` : newName;
            if (typeof patchTreeCache === 'function') {
                const oldCachePath = typeof toMainTreePath === 'function' ? toMainTreePath(sourcePath, sourceRoot) : sourcePath;
                const newCachePath = typeof toMainTreePath === 'function' ? toMainTreePath(newPath, targetRoot) : newPath;
                await patchTreeCache([{ op: 'move', old_path: oldCachePath, new_path: newCachePath, new_name: newName }]);
            }
            if (typeof treeNodeRename === 'function' && sourceRoot === 'main' && targetRoot === 'main') {
                treeNodeRename(sourcePath, newPath, newName);
            } else if (typeof buildTree === 'function') {
                await buildTree('');
            }
            if (typeof flashStatus === 'function' && window.i18n) flashStatus(t(window.i18n, 'moveSuccess'));
            const viewer = document.getElementById('viewer');
            if (viewer?.dataset?.path === sourcePath && typeof closeViewer === 'function') closeViewer();
            if (typeof clearSelection === 'function') clearSelection();
        } catch (err) {
            if (typeof flashStatus === 'function' && window.i18n) flashStatus(t(window.i18n, 'moveError') + err.message);
        }
    }

    function onFileDragMove(e) {
        if (!fileDragState || fileDragState.pointerId !== e.pointerId) return;
        const dx = e.clientX - fileDragState.startX;
        const dy = e.clientY - fileDragState.startY;
        if (!fileDragState.active) {
            if (Math.hypot(dx, dy) < FILE_DRAG_THRESHOLD) return;
            fileDragState.active = true;
            fileDragState.node.classList.add('tree-drag-source');
            ensureDragGhost(fileDragState.label);
            document.body.style.userSelect = 'none';
        }
        if (dragGhost) {
            dragGhost.style.left = `${e.clientX + 12}px`;
            dragGhost.style.top = `${e.clientY + 12}px`;
        }
        fileDragState.lastX = e.clientX;
        fileDragState.lastY = e.clientY;
        const rootBtn = rootToolbarTargetAt(e.clientX, e.clientY);
        clearRootToolbarDropTarget();
        if (rootBtn) {
            rootToolbarDropBtn = rootBtn;
            rootBtn.classList.add('tree-root-drop-target');
            const nextRoot = rootBtn.dataset.root || 'main';
            if (!fileDragState.switchingRoot && nextRoot !== fileDragState.viewRoot) {
                switchTreeRootDuringDrag(nextRoot);
            }
        }
        const target = folderDropTargetAt(e.clientX, e.clientY);
        clearDropTarget();
        clearTreeRootDropHighlight();
        clearFolderExpandHoverTimer();
        hideMoveIcon();
        if (target && isValidMoveTarget(target, fileDragState.path, fileDragState.sourceRoot, fileDragState.viewRoot, fileDragState.sourceType)) {
            dropTargetNode = target;
            fileDragState.dropTargetDir = target.dataset.path || '';
            fileDragState.dropTargetRoot = fileDragState.viewRoot || fileDragState.sourceRoot;
            fileDragState.hadValidDropTarget = true;
            fileDragState.dropOnRootToolbar = false;
            target.classList.add('tree-drop-target');
            scheduleFolderExpandDuringDrag(target);
            updateMoveIcon(e.clientX, e.clientY);
        } else if (rootBtn && rootBtn.dataset.root) {
            fileDragState.dropTargetDir = '';
            fileDragState.dropTargetRoot = rootBtn.dataset.root || 'main';
            fileDragState.hadValidDropTarget = true;
            fileDragState.dropOnRootToolbar = true;
        } else if (isPointerOverTree(e.clientX, e.clientY) && canDropOnTreeRoot(fileDragState.path, fileDragState.sourceRoot, fileDragState.viewRoot)) {
            const tree = document.getElementById('tree');
            if (tree) tree.classList.add('tree-root-drop-active');
            fileDragState.dropTargetDir = '';
            fileDragState.dropTargetRoot = fileDragState.viewRoot || fileDragState.sourceRoot;
            fileDragState.hadValidDropTarget = true;
            fileDragState.dropOnRootToolbar = false;
            updateMoveIcon(e.clientX, e.clientY);
        } else {
            fileDragState.dropTargetDir = '';
            fileDragState.dropTargetRoot = '';
            fileDragState.hadValidDropTarget = false;
            fileDragState.dropOnRootToolbar = false;
        }
    }

    async function onFileDragEnd(e) {
        if (!fileDragState || fileDragState.pointerId !== e.pointerId) return;
        const { active, path, node, sourceRoot, sourceType, viewRoot, dropTargetDir, dropTargetRoot, hadValidDropTarget, dropOnRootToolbar } = fileDragState;
        if (active && isPointerOverAiAssistantPanel(e.clientX, e.clientY) && tryAttachTreeFileToAiAssistant(path, sourceRoot)) {
            fileDragState = null;
            document.body.style.userSelect = '';
            document.removeEventListener('pointermove', onFileDragMove);
            document.removeEventListener('pointerup', onFileDragEnd);
            document.removeEventListener('pointercancel', onFileDragEnd);
            clearDragUi();
            if (node?.isConnected) node.classList.remove('tree-drag-source');
            return;
        }
        let targetNode = dropTargetNode;
        if (!targetNode || !targetNode.isConnected) {
            targetNode = folderDropTargetAt(e.clientX, e.clientY);
        }
        const defaultRoot = viewRoot || (typeof getCurrentCloudRoot === 'function' ? getCurrentCloudRoot() : 'main');
        let finalTargetDir;
        let finalTargetRoot = dropTargetRoot || defaultRoot;
        const rootBtn = rootToolbarTargetAt(e.clientX, e.clientY);
        if (rootBtn && rootBtn.dataset.root && rootBtn.dataset.root !== 'trash') {
            finalTargetDir = '';
            finalTargetRoot = rootBtn.dataset.root;
        } else if (dropOnRootToolbar && hadValidDropTarget) {
            finalTargetDir = '';
            finalTargetRoot = dropTargetRoot || defaultRoot;
        } else if (targetNode && isValidMoveTarget(targetNode, path, sourceRoot, finalTargetRoot, sourceType)) {
            finalTargetDir = targetNode.dataset.path || '';
        } else if (hadValidDropTarget) {
            finalTargetDir = dropTargetDir ?? '';
        } else if (isPointerOverTree(e.clientX, e.clientY) && canDropOnTreeRoot(path, sourceRoot, viewRoot)) {
            finalTargetDir = '';
            finalTargetRoot = defaultRoot;
        } else {
            finalTargetDir = undefined;
        }
        fileDragState = null;
        document.body.style.userSelect = '';
        document.removeEventListener('pointermove', onFileDragMove);
        document.removeEventListener('pointerup', onFileDragEnd);
        document.removeEventListener('pointercancel', onFileDragEnd);
        clearDragUi();
        if (!active || finalTargetDir === undefined) return;
        await moveTreeFile(path, finalTargetDir, sourceRoot, finalTargetRoot);
        if (node?.isConnected) node.classList.remove('tree-drag-source');
    }

    function hasExternalFileTransfer(e) {
        const types = e.dataTransfer?.types;
        if (!types) return false;
        const list = Array.from(types);
        return list.includes('Files') || list.includes('application/x-moz-file') || (e.dataTransfer?.files?.length > 0);
    }

    function getExternalDropTargets() {
        const tree = document.getElementById('tree');
        const sidebar = document.querySelector('.sidebar');
        return [tree, sidebar].filter(Boolean);
    }

    function uploadExternalFiles(files, basePath) {
        const list = Array.from(files || []).filter((f) => f && f.size >= 0);
        if (!list.length) return;
        const uploadFn = window.uploadFilesToPath || (window.uploadModule && window.uploadModule.uploadFilesToPath);
        if (typeof uploadFn === 'function') uploadFn(list, basePath || '');
    }

    async function resolveDropItems(items) {
        const allFiles = [];
        async function processEntry(entry, path) {
            if (entry.isFile) {
                return new Promise((resolve) => {
                    entry.file((file) => {
                        file.relativePath = path + file.name;
                        allFiles.push(file);
                        resolve();
                    }, resolve);
                });
            }
            if (entry.isDirectory) {
                const reader = entry.createReader();
                return new Promise((resolve) => {
                    const readBatch = () => reader.readEntries(async (entries) => {
                        if (!entries.length) return resolve();
                        await Promise.all(entries.map((e) => processEntry(e, path + entry.name + '/')));
                        readBatch();
                    });
                    readBatch();
                });
            }
        }
        for (const item of items) {
            if (item.kind === 'file') {
                const entry = item.webkitGetAsEntry?.() || item.getAsEntry?.();
                if (entry) await processEntry(entry, '');
            }
        }
        return allFiles;
    }

    function initTreeExternalFileDrop() {
        const targets = getExternalDropTargets();
        if (!targets.length) return;
        const zone = targets[targets.length - 1];
        if (zone.dataset.cloudExternalDropBound === '1') return;
        zone.dataset.cloudExternalDropBound = '1';
        let extDragViewRoot = '';
        let extDragSwitching = false;
        let extDragRootSwitchToken = 0;
        let extDragRootBtn = null;
        const onDragEnter = (e) => {
            if (window.matchMedia(MOBILE_MQ).matches) return;
            if (!hasExternalFileTransfer(e)) return;
            if (typeof isTrashRoot === 'function' && isTrashRoot()) return;
            e.preventDefault();
            e.stopPropagation();
            extDragViewRoot = (typeof getCurrentCloudRoot === 'function' ? getCurrentCloudRoot() : 'main');
            const tree = document.getElementById('tree');
            if (tree) tree.classList.add('tree-external-drop-active');
        };
        const onDragOver = async (e) => {
            if (window.matchMedia(MOBILE_MQ).matches) return;
            if (!hasExternalFileTransfer(e)) return;
            if (typeof isTrashRoot === 'function' && isTrashRoot()) return;
            e.preventDefault();
            e.stopPropagation();
            e.dataTransfer.dropEffect = 'copy';
            const tree = document.getElementById('tree');
            const rootBtn = rootToolbarTargetAt(e.clientX, e.clientY);
            if (extDragRootBtn) { extDragRootBtn.classList.remove('tree-root-drop-target'); extDragRootBtn = null; }
            if (rootBtn) {
                extDragRootBtn = rootBtn;
                rootBtn.classList.add('tree-root-drop-target');
                const nextRoot = rootBtn.dataset.root || 'main';
                if (!extDragSwitching && nextRoot !== extDragViewRoot) {
                    extDragSwitching = true;
                    const token = ++extDragRootSwitchToken;
                    clearDropTarget();
                    if (tree) tree.classList.remove('tree-external-drop-active');
                    if (tree) tree.classList.add('cloud-tree-root-switching');
                    await new Promise((r) => setTimeout(r, 140));
                    if (token !== extDragRootSwitchToken) return;
                    if (typeof setCurrentCloudRoot === 'function') setCurrentCloudRoot(nextRoot);
                    const toolbar = document.getElementById('root-toolbar');
                    if (toolbar) toolbar.querySelectorAll('.root-toolbar-btn').forEach((b) => b.classList.toggle('active', (b.dataset.root || 'main') === nextRoot));
                    if (typeof cachedFileTree !== 'undefined') cachedFileTree = null;
                    try { if (typeof buildTree === 'function') await buildTree(''); if (typeof syncRootModeView === 'function') await syncRootModeView(); } catch (_) {}
                    extDragViewRoot = nextRoot;
                    if (tree) requestAnimationFrame(() => tree.classList.remove('cloud-tree-root-switching'));
                    extDragSwitching = false;
                }
                clearDropTarget();
                if (tree) tree.classList.remove('tree-external-drop-active');
                return;
            }
            const folderTarget = folderDropTargetAt(e.clientX, e.clientY);
            clearDropTarget();
            if (folderTarget) {
                if (tree) tree.classList.remove('tree-external-drop-active');
                dropTargetNode = folderTarget;
                folderTarget.classList.add('tree-drop-target');
            } else if (tree) {
                tree.classList.add('tree-external-drop-active');
            }
        };
        const onDragLeave = (e) => {
            if (window.matchMedia(MOBILE_MQ).matches) return;
            const tree = document.getElementById('tree');
            const sidebar = document.querySelector('.sidebar');
            const related = e.relatedTarget;
            if (related && sidebar && sidebar.contains(related)) return;
            clearDropTarget();
            clearExternalDropHighlight();
            if (extDragRootBtn) { extDragRootBtn.classList.remove('tree-root-drop-target'); extDragRootBtn = null; }
        };
        const onDrop = async (e) => {
            if (window.matchMedia(MOBILE_MQ).matches) return;
            if (!hasExternalFileTransfer(e)) return;
            if (typeof isTrashRoot === 'function' && isTrashRoot()) return;
            e.preventDefault();
            e.stopPropagation();
            const rootBtn = rootToolbarTargetAt(e.clientX, e.clientY);
            let targetRoot = (typeof getCurrentCloudRoot === 'function' ? getCurrentCloudRoot() : 'main');
            if (rootBtn && rootBtn.dataset.root && rootBtn.dataset.root !== 'trash') {
                targetRoot = rootBtn.dataset.root;
                if (targetRoot !== extDragViewRoot && typeof setCurrentCloudRoot === 'function') {
                    setCurrentCloudRoot(targetRoot);
                    if (typeof cachedFileTree !== 'undefined') cachedFileTree = null;
                    try { if (typeof buildTree === 'function') await buildTree(''); if (typeof syncRootModeView === 'function') await syncRootModeView(); } catch (_) {}
                }
            }
            const targetNode = dropTargetNode || folderDropTargetAt(e.clientX, e.clientY);
            const targetDir = targetNode?.dataset?.path || '';
            clearDropTarget();
            clearExternalDropHighlight();
            if (extDragRootBtn) { extDragRootBtn.classList.remove('tree-root-drop-target'); extDragRootBtn = null; }
            const files = await (async () => {
                const items = e.dataTransfer.items;
                if (items && items.length > 0) {
                    const resolved = await resolveDropItems(items);
                    if (resolved.length) return resolved;
                }
                return Array.from(e.dataTransfer.files || []);
            })();
            if (files.length) uploadExternalFiles(files, targetDir);
        };
        zone.addEventListener('dragenter', onDragEnter);
        zone.addEventListener('dragover', onDragOver);
        zone.addEventListener('dragleave', onDragLeave);
        zone.addEventListener('drop', onDrop);
    }

    function initTreeFileDragDrop() {
        document.addEventListener('mousedown', (e) => {
            if (e.button !== 0 || window.matchMedia(MOBILE_MQ).matches) return;
            if (!e.target.closest('#tree')) return;
            if (e.target.closest('.selection-checkbox') || e.target.closest('button') || e.target.closest('input')) return;
            const fileNode = e.target.closest('#tree .node[data-type="file"]');
            if (fileNode) e.stopPropagation();
        }, true);

        document.addEventListener('pointerdown', (e) => {
            if (e.button !== 0 || window.matchMedia(MOBILE_MQ).matches) return;
            if (typeof isTrashRoot === 'function' && isTrashRoot()) return;
            if (isAiModalOpen()) return;
            const fileNode = e.target.closest('#tree .node[data-type="file"]');
            if (!fileNode || e.target.closest('.selection-checkbox')) return;
            if (e.target.closest('.tree-rename-input')) return;
            if (typeof selectionState !== 'undefined' && selectionState.active) return;
            if (!e.altKey) e.preventDefault();
            const label = fileNode.querySelector('.label')?.textContent || fileNode.dataset.path || '';
            const currentRoot = typeof getCurrentCloudRoot === 'function' ? getCurrentCloudRoot() : 'main';
            fileDragState = {
                pointerId: e.pointerId,
                node: fileNode,
                path: fileNode.dataset.path || '',
                label,
                sourceRoot: currentRoot,
                sourceType: fileNode.dataset.type || 'file',
                viewRoot: currentRoot,
                switchingRoot: false,
                dropTargetDir: '',
                dropTargetRoot: currentRoot,
                hadValidDropTarget: false,
                dropOnRootToolbar: false,
                lastX: e.clientX,
                lastY: e.clientY,
                active: false,
                startX: e.clientX,
                startY: e.clientY
            };
            document.addEventListener('pointermove', onFileDragMove);
            document.addEventListener('pointerup', onFileDragEnd);
            document.addEventListener('pointercancel', onFileDragEnd);
        }, true);
    }

    function syncMobileToolbarMenuSlot() {
        const sidebar = document.querySelector('.sidebar');
        const layout = document.querySelector('.layout');
        if (!sidebar || !layout) return;
        layout.classList.toggle('cloud-tree-sidebar-open', window.matchMedia(MOBILE_MQ).matches && !sidebar.classList.contains('active'));
    }

    function initCloudTreeUi() {
        injectTreeUiStyles();
        patchOpenFileInTab();
        patchContextMenu();
        patchTreeAutoShift();
        initTreeFileDragDrop();
        initTreeExternalFileDrop();
        syncMobileToolbarMenuSlot();
        const sidebar = document.querySelector('.sidebar');
        if (sidebar) {
            new MutationObserver(syncMobileToolbarMenuSlot).observe(sidebar, { attributes: true, attributeFilter: ['class'] });
        }
        window.addEventListener('resize', syncMobileToolbarMenuSlot);
        if (typeof initTreeAutoShift === 'function') initTreeAutoShift();
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initCloudTreeUi);
    else initCloudTreeUi();
})();

// Export functions
window.monacoManager = {
    createEditor: monacoCreateEditor,
    getEditor: monacoGetEditor,
    getActiveEditor: monacoGetActiveEditor,
    getUndoRedoState: monacoGetUndoRedoState,
    getContent: monacoGetContent,
    setContent: monacoSetContent,
    undo: monacoUndo,
    redo: monacoRedo,
    replaceContentAsUndoable: monacoReplaceContentAsUndoable,
    replaceContentPreservingView: monacoReplaceContentPreservingView,
    highlightAiEdit: monacoHighlightAiEdit,
    computeAiEditChangedRanges: monacoComputeChangedLineRanges,
    clearAiEditHighlight: monacoClearAiEditHighlight,
    setRemoteCursor: monacoSetRemoteCursor,
    clearRemoteCursor: monacoClearRemoteCursor,
    clearAllRemoteCursors: monacoClearAllRemoteCursors,
    applyRemoteCollabEdits: monacoApplyRemoteCollabEdits,
    animateRemoteCollabInserts: monacoAnimateRemoteCollabInserts,
    captureEditorViewState: monacoCaptureEditorViewState,
    restoreEditorViewState: monacoRestoreEditorViewState,
    dispose: monacoDisposeEditor,
    updateTheme: monacoUpdateThemeFromApp,
    resize: monacoResizeEditors,
    deleteLine: monacoDeleteLine,
    moveLine: monacoMoveLine,
    toggleBlockComment: monacoToggleBlockComment,
    duplicateLine: monacoDuplicateLine,
    createTouchToolbar: monacoCreateTouchToolbar,
    createStatusBar: monacoCreateStatusBar,
    refreshFooterStatusBar: monacoRefreshFooterStatusBar,
    isTouchDevice: () => monacoIsTouchDevice
};
if (typeof window !== 'undefined') {
    window.addEventListener('cloudActiveTabChanged', () => { monacoRefreshFooterStatusBar(); });
}
