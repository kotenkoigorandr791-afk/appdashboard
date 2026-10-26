// assets/monaco-shared.js
// Single source of truth for everything the cloud application shares between its
// Monaco editors: the main file editor (assets/monaco-editor.js) and the project
// editors (utilities/projects/html-editor, markdown-editor, latex-editor, zip-viewer).
//
// Every editor must load this file before its own Monaco bootstrap and use
// cloudMonacoShared so that all of them run the same Monaco version, the same
// theme palette, the same editor options, the same localized context menu, the
// same transliteration rules and the same numbered-list behaviour.
(function () {
    'use strict';

    // Monaco version used everywhere. The NLS indexes below are tied to it: the
    // monaco-editor build ships its own English strings and we only override the
    // ones we translate, so these numbers must be bumped together with VERSION.
    const VERSION = '0.52.2';
    const CDN_BASE = 'https://cdn.jsdelivr.net/npm/monaco-editor@' + VERSION + '/min/vs';

    // Monaco localizes its built-in UI strings through globalThis._VSCODE_NLS_MESSAGES,
    // an array the bundle addresses by index (first argument of localize()/localize2()).
    // The build ships translated arrays only for de/es/fr/it/ja/ko/ru/zh-*, so for the
    // app's languages we fill the indexes we translate ourselves; anything left unset
    // falls back to the build's own English text.
    //
    // Indexes taken from monaco-editor@0.52.2/min/vs/editor/editor.main.js.
    const NLS_MESSAGE_INDEXES = {
        // Context menu
        monacoCtxCut: [726, 727, 728],
        monacoCtxCopy: [730, 731, 732],
        monacoCtxPaste: [734, 735, 736],
        monacoCtxSelectAll: [67],
        monacoCtxCommandPalette: [701],
        monacoCtxFormatDocument: [922],
        monacoCtxFormatSelection: [923],
        monacoCtxRenameSymbol: [1242],
        monacoCtxGoToSymbol: [703],
        monacoCtxChangeAllOccurrences: [1167],
        monacoCtxRefactor: [755],
        monacoCtxSourceAction: [760],
        monacoCtxGoToDefinition: [973],
        monacoCtxGoToDeclaration: [976],
        monacoCtxGoToTypeDefinition: [978],
        monacoCtxGoToImplementations: [980],
        monacoCtxGoToReferences: [982],
        // Code action window ("Quick fix" and the action kind titles it groups by)
        monacoQuickFix: [778],
        monacoCtxExtract: [779],
        monacoCtxInline: [780],
        monacoCtxRewrite: [781],
        monacoCtxMove: [782],
        monacoCtxSurroundWith: [783],
        monacoCtxCodeActionSource: [784],
        monacoNoCodeActions: [750, 754],
        monacoShowCodeActions: [793]
    };

    // Transliteration tables: every Latin key maps to what that key produces on the
    // Russian/Ukrainian layout - letters AND punctuation. That is the point of the
    // feature: with an English layout you press the same physical keys you would on a
    // Russian keyboard and get the same text, so
    //     ghbdtn? etj ntcn/ gjkexbkjcm gkj[j/   ->  "Привет, это тест. получилось плохо."
    //     ghbmeh ntrcnf kfk ,skj teghfdbkmtj/    ->  "пример текста как было неправильно."
    // ',' is the key that types б, '.' types ю, '/' types '.' and '?' (Shift+/) types
    // ',' - exactly like the real layout. Characters that arrive already as Cyrillic
    // (a real Russian/Ukrainian layout) are passed through untouched.
    const TRANSLIT_TABLES = (typeof window !== 'undefined' && window.CloudTransliteration && window.CloudTransliteration.TABLES)
        ? window.CloudTransliteration.TABLES
        : {
        ru: {
            '`': 'ё', '~': 'Ё',
            'q': 'й', 'w': 'ц', 'e': 'у', 'r': 'к', 't': 'е', 'y': 'н', 'u': 'г', 'i': 'ш', 'o': 'щ', 'p': 'з',
            '[': 'х', ']': 'ъ',
            'a': 'ф', 's': 'ы', 'd': 'в', 'f': 'а', 'g': 'п', 'h': 'р', 'j': 'о', 'k': 'л', 'l': 'д', ';': 'ж',
            "'": 'э',
            'z': 'я', 'x': 'ч', 'c': 'с', 'v': 'м', 'b': 'и', 'n': 'т', 'm': 'ь',
            ',': 'б', '.': 'ю', '/': '.', '?': ',', '<': 'Б', '>': 'Ю',
            'Q': 'Й', 'W': 'Ц', 'E': 'У', 'R': 'К', 'T': 'Е', 'Y': 'Н', 'U': 'Г', 'I': 'Ш', 'O': 'Щ', 'P': 'З',
            '{': 'Х', '}': 'Ъ',
            'A': 'Ф', 'S': 'Ы', 'D': 'В', 'F': 'А', 'G': 'П', 'H': 'Р', 'J': 'О', 'K': 'Л', 'L': 'Д', ':': 'Ж',
            '"': 'Э',
            'Z': 'Я', 'X': 'Ч', 'C': 'С', 'V': 'М', 'B': 'И', 'N': 'Т', 'M': 'Ь'
        },
        uk: {
            '`': 'ґ',
            'q': 'й', 'w': 'ц', 'e': 'у', 'r': 'к', 't': 'е', 'y': 'н', 'u': 'г', 'i': 'ш', 'o': 'щ', 'p': 'з',
            '[': 'х', ']': 'ї',
            'a': 'ф', 's': 'і', 'd': 'в', 'f': 'а', 'g': 'п', 'h': 'р', 'j': 'о', 'k': 'л', 'l': 'д', ';': 'ж',
            "'": 'є',
            'z': 'я', 'x': 'ч', 'c': 'с', 'v': 'м', 'b': 'и', 'n': 'т', 'm': 'ь',
            ',': 'б', '.': 'ю', '/': '.', '?': ',', '<': 'Б', '>': 'Ю',
            'Q': 'Й', 'W': 'Ц', 'E': 'У', 'R': 'К', 'T': 'Е', 'Y': 'Н', 'U': 'Г', 'I': 'Ш', 'O': 'Щ', 'P': 'З',
            '{': 'Х', '}': 'Ї',
            'A': 'Ф', 'S': 'І', 'D': 'В', 'F': 'А', 'G': 'П', 'H': 'Р', 'J': 'О', 'K': 'Л', 'L': 'Д', ':': 'Ж',
            '"': 'Є',
            'Z': 'Я', 'X': 'Ч', 'C': 'С', 'V': 'М', 'B': 'И', 'N': 'Т', 'M': 'Ь'
        }
    };

    // Fills Monaco's message array with the app's translations. Must run before
    // vs/editor/editor.main is loaded, because labels are resolved at load time.
    function applyNlsTranslations(i18n, lang) {
        const messages = Array.isArray(globalThis._VSCODE_NLS_MESSAGES)
            ? globalThis._VSCODE_NLS_MESSAGES
            : [];
        const source = i18n || {};
        let applied = 0;
        Object.keys(NLS_MESSAGE_INDEXES).forEach((key) => {
            const value = source[key];
            if (typeof value !== 'string' || value === '') return;
            NLS_MESSAGE_INDEXES[key].forEach((index) => {
                messages[index] = value;
                applied += 1;
            });
        });
        globalThis._VSCODE_NLS_MESSAGES = messages;
        globalThis._VSCODE_NLS_LANGUAGE = lang || 'en';
        return applied;
    }

    function configureLoader() {
        if (typeof require === 'undefined' || typeof require.config !== 'function') return false;
        require.config({ paths: { vs: CDN_BASE }, ignoreDuplicateModules: ['vs/editor/editor.main'] });
        return true;
    }

    let lastI18n = {};
    let lastLang = 'en';
    function tKey(key, fallback) {
        const value = lastI18n && lastI18n[key];
        return (typeof value === 'string' && value) ? value : (fallback || key);
    }

    // Loads Monaco once, translating its built-in UI first. Editors that already have
    // the locale in memory can pass `i18n`; otherwise `i18nUrl` is fetched for `lang`.
    function loadMonaco(options) {
        const opts = options || {};
        const lang = opts.lang || 'en';
        lastLang = lang;

        const applyAndLoad = (i18n) => {
            lastI18n = i18n || {};
            applyNlsTranslations(lastI18n, lang);
            configureLoader();
            return new Promise((resolve, reject) => {
                require(['vs/editor/editor.main'], function () {
                    resolve(typeof window !== 'undefined' ? window.monaco : undefined);
                }, function (error) {
                    reject(error);
                });
            });
        };

        if (opts.i18n) return applyAndLoad(opts.i18n);
        if (!opts.i18nUrl) return applyAndLoad({});

        const separator = opts.i18nUrl.indexOf('?') === -1 ? '?' : '&';
        const url = opts.i18nUrl + separator + 'lang=' + encodeURIComponent(lang);
        return fetch(url)
            .then((response) => (response.ok ? response.json() : {}))
            .catch(() => ({}))
            .then(applyAndLoad);
    }

    function resolveAccent(options) {
        let accent = (options && options.accent) || '';
        if (!accent) {
            try {
                accent = (window.APP_CONFIG && window.APP_CONFIG.color) || '';
            } catch (_) { accent = ''; }
        }
        if (!accent) accent = '#4a90e2';
        if (accent.charAt(0) !== '#') accent = '#' + accent;
        return accent;
    }

    // The exact palette assets/monaco-editor.js uses, so every Monaco editor in the
    // app looks the same. `rules` are optional language token colours (tag, keyword, ...).
    function defineAppThemes(monaco, options) {
        const opts = options || {};
        const accent = resolveAccent(opts);
        const rules = opts.rules || [];
        monaco.editor.defineTheme('custom-dark', {
            base: 'vs-dark',
            inherit: true,
            rules: rules,
            colors: {
                'editor.background': '#1a1a1a',
                'editor.foreground': '#eeeeee',
                'editor.lineHighlightBackground': '#2a2a2a',
                'editorLineNumber.foreground': '#666666',
                'editorLineNumber.activeForeground': '#eeeeee',
                'focusBorder': '#00000000',
                'editor.selectionBackground': accent + '40',
                'editor.inactiveSelectionBackground': accent + '20'
            }
        });
        monaco.editor.defineTheme('custom-light', {
            base: 'vs',
            inherit: true,
            rules: rules,
            colors: {
                'editor.background': '#ffffff',
                'editor.foreground': '#000000',
                'editor.lineHighlightBackground': '#f0f0f0',
                'editorLineNumber.foreground': '#999999',
                'editorLineNumber.activeForeground': '#333333',
                'focusBorder': '#00000000',
                'editor.selectionBackground': accent + '40',
                'editor.inactiveSelectionBackground': accent + '20'
            }
        });
        return accent;
    }

    // The editor options shared by every Monaco editor: a narrow line-number gutter
    // (folding arrows hidden, folding still available via Ctrl+Shift+[ / ] and F1)
    // and the same look and feel.
    function standardEditorOptions(overrides) {
        return Object.assign({
            lineNumbers: 'on',
            lineNumbersMinChars: 2,
            // 8px is the smallest decorations column that still fits the 7px
            // .ai-agent-edit-glyph strip used by AI-edit decorations.
            lineDecorationsWidth: 8,
            folding: true,
            foldingStrategy: 'auto',
            showFoldingControls: 'never',
            glyphMargin: false,
            contextmenu: true,
            roundedSelection: true,
            scrollBeyondLastLine: true,
            automaticLayout: true,
            fontFamily: 'Consolas, "Courier New", monospace'
        }, overrides || {});
    }

    // Monaco measures the character width when the editor is created and wraps
    // lines using that measurement. The editors render with the JetBrains Mono
    // webfont, which CSS loads with font-display: swap; when the font arrives
    // after the editor was created, every wrapped line renders slightly wider
    // than the width Monaco wrapped for. That leftover width becomes a phantom
    // horizontal scroll range, so the text drifts left and right while typing.
    // Waiting for the font before the editor is created keeps the measurement
    // and the rendered glyphs in sync (bounded by a timeout so an unreachable
    // font CDN can never block the editor).
    function waitForEditorFont(overrides) {
        const fonts = typeof document !== 'undefined' ? document.fonts : null;
        if (!fonts || typeof fonts.load !== 'function') return Promise.resolve();
        const options = overrides || {};
        const families = String(options.fontFamily || '')
            .split(',')
            .map(function (part) { return part.trim().replace(/^["']|["']$/g, ''); })
            .filter(function (name) { return name && !/^(monospace|serif|sans-serif|system-ui)$/i.test(name); });
        if (!families.length) return Promise.resolve();
        const size = options.fontSize || 14;
        const weight = options.fontWeight || '400';
        const loads = families.map(function (family) {
            try {
                return fonts.load(weight + ' ' + size + 'px "' + family + '"');
            } catch (_) {
                return Promise.resolve([]);
            }
        });
        const settled = Promise.all(loads)
            .then(function () { return fonts.ready; })
            .catch(function () { /* font unavailable: keep the fallback metrics */ });
        const timeout = new Promise(function (resolve) { setTimeout(resolve, 1500); });
        return Promise.race([settled, timeout]);
    }

    // Every editor here wraps its lines, so there is never a legitimate reason to
    // scroll horizontally. Monaco can still scroll by a few pixels when a wrapped
    // line ends up marginally wider than the content area (character width
    // rounding) and each such scroll makes the text jump left and right while
    // typing, so keep the horizontal offset pinned to zero.
    function installHorizontalScrollLock(editor) {
        let restoring = false;
        const disposable = editor.onDidScrollChange(function (event) {
            if (restoring || !event.scrollLeftChanged) return;
            if (editor.getScrollLeft() === 0) return;
            restoring = true;
            editor.setScrollLeft(0);
            restoring = false;
        });
        return {
            dispose: function () { disposable.dispose(); }
        };
    }

    function isTxtFile(filePath) {
        return /\.txt$/i.test(filePath || '');
    }

    // Reads the language currently selected in the cloud shell's switcher. Editor
    // iframes (html/markdown/latex/zip) are same origin and live inside the shell, so
    // they can read it across the iframe boundary instead of being reset when the
    // shell's switcher changes (which would drop unsaved content).
    function parentSwitcherLang() {
        try {
            if (!window.parent || window.parent === window) return null;
            const active = window.parent.document.querySelector('.lang-option.active');
            if (active && active.dataset && active.dataset.lang) return active.dataset.lang;
        } catch (_) { /* cross-origin parent or no document */ }
        return null;
    }

    // The live language for transliteration. The page's own switcher wins (the main
    // cloud editor); otherwise the shell's switcher is read across the iframe boundary
    // (project editors); only then the server-rendered fallback. Evaluated on every
    // key, so switching languages while any editor is open takes effect immediately.
    function resolveLang(fallback) {
        try {
            const active = document.querySelector('.lang-option.active');
            if (active && active.dataset && active.dataset.lang) return active.dataset.lang;
        } catch (_) { /* no document */ }
        const fromParent = parentSwitcherLang();
        if (fromParent) return fromParent;
        return fallback || 'en';
    }

    // Typing Russian/Ukrainian on an English keyboard layout: Latin letters are
    // rewritten to the Cyrillic letter at the same layout position, and punctuation
    // keys follow the same layout ('.' -> 'ю', ',' -> 'б', '/' -> '.', '?' -> ',').
    // When the keys arrive already Cyrillic (a real Russian/Ukrainian layout) the
    // preceding characters are Cyrillic too, so the next punctuation key must stay
    // literal ('.' stays '.') instead of being transliterated. Returns a disposable.
    function installTransliteration(options) {
        const editor = options.editor;
        const monacoRef = options.monaco;
        const container = options.container;
        const getLang = options.getLang;
        if (!editor || !monacoRef || typeof getLang !== 'function') {
            return { dispose: function () {} };
        }

        // Track IME composition so we never interfere with native input.
        let isComposing = false;
        const onCompositionStart = () => { isComposing = true; };
        const onCompositionEnd = () => { isComposing = false; };
        if (container) {
            container.addEventListener('compositionstart', onCompositionStart);
            container.addEventListener('compositionend', onCompositionEnd);
        }

        // True while the user is typing on a real Cyrillic layout. Punctuation then
        // stays literal, because the layout already produced the intended character.
        let lastTypedWasCyrillic = false;

        const keyDown = editor.onKeyDown((e) => {
            if (isComposing) return;

            const activeLang = getLang();
            if (!activeLang || activeLang === 'en' || !TRANSLIT_TABLES[activeLang]) return;

            const key = e.browserEvent ? e.browserEvent.key : null;
            if (!key) return;

            // Already Cyrillic (real Russian/Ukrainian layout) - leave it alone, but
            // remember the layout so the following punctuation is not transliterated.
            if (/[\u0400-\u04FF]/.test(key)) {
                lastTypedWasCyrillic = true;
                return;
            }
            // A Latin letter means the transliteration flow is active again: from now
            // on punctuation keys map to their Cyrillic layout positions.
            if (/^[a-zA-Z]$/.test(key)) {
                lastTypedWasCyrillic = false;
            }

            const isPairChar = ['(', ')', '{', '}'].includes(key);
            const isPunctuationKey = !/[a-zA-Z]/.test(key);
            const table = TRANSLIT_TABLES[activeLang];
            if (key.length !== 1 || !Object.prototype.hasOwnProperty.call(table, key)) return;
            if (e.ctrlKey || e.altKey || e.metaKey || isPairChar) return;
            if (isPunctuationKey && lastTypedWasCyrillic) return;

            e.preventDefault();
            const text = table[key];
            const selection = editor.getSelection();
            if (selection && !selection.isEmpty()) {
                editor.executeEdits('transliteration', [{ range: selection, text }]);
                const start = selection.getStartPosition();
                editor.setPosition({ lineNumber: start.lineNumber, column: start.column + text.length });
                return;
            }
            const position = editor.getPosition();
            if (!position) return;
            editor.executeEdits('transliteration', [{
                range: new monacoRef.Range(position.lineNumber, position.column, position.lineNumber, position.column),
                text
            }]);
            editor.setPosition({
                lineNumber: position.lineNumber,
                column: position.column + text.length
            });
        });

        return {
            dispose: function () {
                keyDown.dispose();
                if (container) {
                    container.removeEventListener('compositionstart', onCompositionStart);
                    container.removeEventListener('compositionend', onCompositionEnd);
                }
            }
        };
    }

    // Counter for the per-editor context keys used by the list continuation below.
    let txtListMarkerKeySeq = 0;

    // Markdown fenced code blocks hold literal text: a line that happens to start
    // with "1. " there is code, not a list, so Enter must not add the next number.
    function isInsideFencedCodeBlock(model, lineNumber) {
        let fence = null;
        for (let i = 1; i < lineNumber; i++) {
            const match = /^\s*(`{3,}|~{3,})/.exec(model.getLineContent(i));
            if (!match) continue;
            if (!fence) fence = match[1].charAt(0);
            else if (match[1].charAt(0) === fence) fence = null;
        }
        return fence !== null;
    }

    // Ordered-list continuation for prose editors (.txt and Markdown): Enter on
    // "1. foo" writes "2. " on the next line, keeping the indentation, the delimiter
    // and the spacing. Enter on a marker without text removes the marker so the list
    // can be ended. Backspace right after the auto inserted marker removes the whole
    // marker in one press, the way Word drops the automatic numbering. The caller
    // decides which editors get it (only editors that hold running text). Returns a
    // disposable.
    function installNumberedListContinuation(options) {
        const editor = options.editor;
        const monacoRef = options.monaco;
        if (!editor || !monacoRef) return { dispose: function () {} };

        const markerPattern = /^(\s*)(?:(\d+)([.)])|([-*+\u2022\u2013\u2014]))([ \t]+)(.*)$/;
        // The marker the previous Enter added ("2. "), so Backspace can tell it apart
        // from a number the user typed by hand.
        let autoMarker = null;
        // Backspace is bound through this context key instead of a keydown listener: a
        // keydown listener would still let Monaco delete one more character, while a
        // gated command replaces the default deletion whenever the caret sits right
        // after the auto inserted marker. Key names are per editor so that two open
        // editors cannot fight over the same key.
        const contextKeyName = 'cloudTxtAutoListMarker' + (++txtListMarkerKeySeq);
        const markerAtCaret = typeof editor.createContextKey === 'function'
            ? editor.createContextKey(contextKeyName, false)
            : null;

        function caretSitsAfterAutoMarker() {
            if (!autoMarker) return false;
            const selection = editor.getSelection();
            if (!selection || !selection.isEmpty()) return false;
            const model = editor.getModel();
            if (!model) return false;
            const position = selection.getStartPosition();
            if (position.lineNumber < 1 || position.lineNumber > model.getLineCount()) return false;
            return model.getValueInRange(new monacoRef.Range(
                position.lineNumber,
                1,
                position.lineNumber,
                position.column
            )) === autoMarker.marker;
        }

        function refreshMarkerContext() {
            if (!markerAtCaret) return;
            markerAtCaret.set(caretSitsAfterAutoMarker());
        }

        function removeAutoMarker() {
            if (!autoMarker) return;
            const model = editor.getModel();
            const selection = editor.getSelection();
            if (!model || !selection) return;
            const position = selection.getStartPosition();
            // The whole marker goes away in one press; the indentation and any text
            // that follows on the line stay untouched (Word-like).
            editor.executeEdits('txt-list-marker-backspace', [{
                range: new monacoRef.Range(position.lineNumber, 1, position.lineNumber, position.column),
                text: autoMarker.indent
            }]);
            editor.setPosition({ lineNumber: position.lineNumber, column: autoMarker.indent.length + 1 });
            autoMarker = null;
            refreshMarkerContext();
        }

        const backspaceCommand = markerAtCaret
            ? editor.addCommand(monacoRef.KeyCode.Backspace, removeAutoMarker, contextKeyName)
            : null;
        const contentWatcher = editor.onDidChangeModelContent(() => refreshMarkerContext());
        const selectionWatcher = editor.onDidChangeCursorSelection(() => refreshMarkerContext());

        const keyDown = editor.onKeyDown((e) => {
            if (e.keyCode !== monacoRef.KeyCode.Enter) return;
            if (e.ctrlKey || e.altKey || e.metaKey || e.shiftKey) return;
            // Enter also commits an IME composition; leave that to the IME.
            if (e.browserEvent && e.browserEvent.isComposing) return;

            const selection = editor.getSelection();
            if (!selection || !selection.isEmpty()) return;

            const model = editor.getModel();
            if (!model) return;

            const position = selection.getStartPosition();
            if (typeof model.getLanguageId === 'function' && model.getLanguageId() === 'markdown'
                && isInsideFencedCodeBlock(model, position.lineNumber)) return;
            const linePrefix = model.getValueInRange(
                new monacoRef.Range(position.lineNumber, 1, position.lineNumber, position.column)
            );
            const match = markerPattern.exec(linePrefix);
            if (!match) return;

            const indent = match[1];
            const digits = match[2];
            const delimiter = match[3];
            const bullet = match[4];
            const spacing = match[5];
            const itemText = match[6];
            const restOfLine = model.getValueInRange(new monacoRef.Range(
                position.lineNumber,
                position.column,
                position.lineNumber,
                model.getLineMaxColumn(position.lineNumber)
            ));

            e.preventDefault();

            if (itemText.trim() === '' && restOfLine.trim() === '') {
                // Ending the list: drop the empty item's own marker.
                autoMarker = null;
                editor.executeEdits('txt-list-continue', [{
                    range: new monacoRef.Range(position.lineNumber, 1, position.lineNumber, position.column),
                    text: indent
                }]);
                editor.setPosition({ lineNumber: position.lineNumber, column: indent.length + 1 });
                return;
            }

            const nextMarker = indent + (bullet || (Number(digits) + 1) + delimiter) + spacing;
            editor.executeEdits('txt-list-continue', [{
                range: new monacoRef.Range(position.lineNumber, position.column, position.lineNumber, position.column),
                text: '\n' + nextMarker
            }]);
            editor.setPosition({ lineNumber: position.lineNumber + 1, column: nextMarker.length + 1 });
            autoMarker = { marker: nextMarker, indent: indent };
            refreshMarkerContext();
        });

        return {
            dispose: function () {
                keyDown.dispose();
                contentWatcher.dispose();
                selectionWatcher.dispose();
                // addCommand returns an id on newer Monaco builds and a disposable on
                // older ones, so only dispose when there is something to dispose.
                if (backspaceCommand && typeof backspaceCommand.dispose === 'function') {
                    backspaceCommand.dispose();
                }
            }
        };
    }

    function getSelectedText(editor) {
        const model = editor && editor.getModel && editor.getModel();
        const sel = editor && editor.getSelection && editor.getSelection();
        if (!model || !sel || sel.isEmpty()) return '';
        return model.getValueInRange(sel);
    }

    function ensureAiOverlay(hostContainer, labels) {
        let overlay = hostContainer && hostContainer.querySelector('.monaco-ai-action-overlay');
        if (overlay) return overlay;
        overlay = document.createElement('div');
        overlay.className = 'monaco-ai-action-overlay hidden';
        overlay.style.cssText = 'position:absolute;z-index:20000;width:min(320px,calc(100% - 24px));padding:12px;border:1px solid rgba(255,255,255,.18);border-radius:14px;backdrop-filter:blur(14px);background:color-mix(in srgb,var(--panel,#252526) 78%,transparent);box-shadow:0 18px 48px rgba(0,0,0,.28);display:flex;flex-direction:column;gap:10px;touch-action:none';
        overlay.innerHTML = '<div data-role="drag-handle" style="display:flex;align-items:center;justify-content:space-between;gap:8px;cursor:move;user-select:none;"><strong data-role="title" style="font-size:13px;"></strong><button type="button" data-role="close" style="width:28px;height:28px;min-width:28px;cursor:pointer;border:none;background:transparent;color:inherit;font-size:18px;">×</button></div><textarea data-role="prompt" rows="2" style="width:100%;min-height:52px;max-height:120px;padding:8px 10px;border-radius:10px;border:1px solid var(--border,#444);background:var(--bg,#1e1e1e);color:var(--fg,#eee);resize:vertical;box-sizing:border-box;font:inherit;"></textarea><div data-role="status" style="min-height:18px;font-size:12px;color:var(--muted,#999);"></div><div style="display:flex;gap:8px;justify-content:flex-end;"><button type="button" data-role="undo" class="hidden" style="padding:6px 10px;border-radius:8px;border:1px solid var(--border,#444);background:transparent;color:inherit;cursor:pointer;"></button><button type="button" data-role="apply" style="padding:6px 10px;border-radius:8px;border:1px solid transparent;background:var(--accent,#569cd6);color:#fff;cursor:pointer;"></button></div>';
        const mount = hostContainer || document.body;
        if (getComputedStyle(mount).position === 'static') mount.style.position = 'relative';
        mount.appendChild(overlay);
        overlay.querySelector('[data-role="close"]').addEventListener('click', () => overlay.classList.add('hidden'));
        document.addEventListener('keydown', (ev) => { if (ev.key === 'Escape') overlay.classList.add('hidden'); });
        const handle = overlay.querySelector('[data-role="drag-handle"]');
        let drag = null;
        handle.addEventListener('pointerdown', (ev) => {
            if (ev.target.closest('[data-role="close"]')) return;
            const parentRect = overlay.parentElement.getBoundingClientRect();
            drag = { id: ev.pointerId, x: ev.clientX, y: ev.clientY, l: overlay.offsetLeft, t: overlay.offsetTop, maxL: Math.max(0, parentRect.width - overlay.offsetWidth), maxT: Math.max(0, parentRect.height - overlay.offsetHeight) };
            handle.setPointerCapture(ev.pointerId);
            ev.preventDefault();
        });
        handle.addEventListener('pointermove', (ev) => {
            if (!drag || drag.id !== ev.pointerId) return;
            overlay.style.left = Math.min(drag.maxL, Math.max(0, drag.l + (ev.clientX - drag.x))) + 'px';
            overlay.style.top = Math.min(drag.maxT, Math.max(0, drag.t + (ev.clientY - drag.y))) + 'px';
        });
        const endDrag = (ev) => { if (!drag || drag.id !== ev.pointerId) return; drag = null; try { handle.releasePointerCapture(ev.pointerId); } catch (_) {} };
        handle.addEventListener('pointerup', endDrag);
        handle.addEventListener('pointercancel', endDrag);
        refreshAiOverlayLabels(overlay, labels);
        return overlay;
    }

    function refreshAiOverlayLabels(overlay, labels) {
        if (!overlay) return;
        const L = labels || {};
        const title = overlay.querySelector('[data-role="title"]');
        if (title) title.textContent = L.askAi || tKey('monacoAskAi', 'Ask AI');
        const prompt = overlay.querySelector('[data-role="prompt"]');
        if (prompt) prompt.placeholder = L.prompt || tKey('monacoAiPromptPlaceholder', 'What should AI change?');
        const apply = overlay.querySelector('[data-role="apply"]');
        if (apply) apply.textContent = L.apply || tKey('apply', 'Apply');
        const undo = overlay.querySelector('[data-role="undo"]');
        if (undo) undo.textContent = L.undo || tKey('monacoAiUndo', 'Undo');
        if (!document.getElementById('cloud-monaco-ai-overlay-style')) {
            const st = document.createElement('style');
            st.id = 'cloud-monaco-ai-overlay-style';
            st.textContent = '.monaco-ai-action-overlay.hidden{display:none!important}.monaco-ai-action-overlay .hidden{display:none!important}';
            document.head.appendChild(st);
        }
    }

    async function requestAiReplacement(aiEndpoint, instruction, text) {
        const response = await fetch(aiEndpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            body: new URLSearchParams({
                action: 'chat',
                role: 'general',
                model: '',
                message: [
                    'You are a code editing assistant.',
                    'Rewrite only the provided selection according to the instruction.',
                    'Return only the replacement text, with no markdown fences and no explanations.',
                    'Instruction: ' + String(instruction || 'Improve this code.'),
                    'Selection:',
                    String(text || '')
                ].join('\n')
            })
        });
        const data = await response.json().catch(() => ({}));
        if (!response.ok || data?.error) throw new Error(data?.error || 'AI request failed');
        return String(data.response ?? '').trim();
    }

    function openAiActionMenu(editor, container, options) {
        const opts = options || {};
        const selectedText = getSelectedText(editor);
        if (!selectedText) return;
        const overlay = ensureAiOverlay(container, opts.labels);
        refreshAiOverlayLabels(overlay, opts.labels);
        const promptEl = overlay.querySelector('[data-role="prompt"]');
        const statusEl = overlay.querySelector('[data-role="status"]');
        const applyBtn = overlay.querySelector('[data-role="apply"]');
        const undoBtn = overlay.querySelector('[data-role="undo"]');
        const selection = editor.getSelection();
        if (!selection || selection.isEmpty()) return;
        const coords = editor.getScrolledVisiblePosition(selection.getStartPosition());
        const rect = container.getBoundingClientRect();
        overlay.classList.remove('hidden');
        overlay.style.left = '0px';
        overlay.style.top = '0px';
        const margin = 12;
        const ow = overlay.offsetWidth || 280;
        const oh = overlay.offsetHeight || 160;
        const maxLeft = Math.max(margin, container.clientWidth - ow - margin);
        const maxTop = Math.max(margin, container.clientHeight - oh - margin);
        overlay.style.left = Math.min(Math.max(margin, (coords?.left || 0) + 12), maxLeft) + 'px';
        overlay.style.top = Math.min(Math.max(margin, (coords?.top || 0) + 28), maxTop) + 'px';
        statusEl.textContent = '';
        undoBtn.classList.add('hidden');
        requestAnimationFrame(() => { try { promptEl.focus(); } catch (_) {} });
        let canUndo = false;
        undoBtn.onclick = () => {
            if (!canUndo) return;
            editor.trigger('ai-inline-undo', 'undo', null);
            canUndo = false;
            undoBtn.classList.add('hidden');
            statusEl.textContent = '';
            notifyUndoRedo(editor);
        };
        applyBtn.onclick = async () => {
            const latest = editor.getSelection();
            if (!latest || latest.isEmpty()) return;
            const original = getSelectedText(editor);
            const instruction = (promptEl?.value || '').trim();
            applyBtn.disabled = true;
            statusEl.textContent = tKey('monacoAiProcessing', 'Processing…');
            try {
                const replacement = await requestAiReplacement(opts.aiEndpoint, instruction, original);
                editor.pushUndoStop();
                editor.executeEdits('ai-inline-rewrite', [{ range: latest, text: replacement, forceMoveMarkers: true }]);
                editor.pushUndoStop();
                canUndo = true;
                statusEl.textContent = tKey('monacoAiReplaced', 'Replaced');
                undoBtn.classList.remove('hidden');
                notifyUndoRedo(editor);
            } catch (error) {
                statusEl.textContent = error.message || 'AI error';
            } finally {
                applyBtn.disabled = false;
            }
        };
    }

    function notifyUndoRedo(editor) {
        try {
            const model = editor && editor.getModel && editor.getModel();
            const canUndo = !!(model && model.canUndo && model.canUndo());
            const canRedo = !!(model && model.canRedo && model.canRedo());
            window.parent && window.parent !== window && window.parent.postMessage({
                source: 'cloudIframeMonacoAi',
                type: 'undoRedoState',
                canUndo: canUndo,
                canRedo: canRedo
            }, '*');
            window.dispatchEvent(new CustomEvent('cloudMonacoUndoRedoChanged', { detail: { canUndo, canRedo } }));
        } catch (_) {}
    }

    // Single entry point for context-menu actions shared by every Monaco editor:
    // translated Select All and Ask AI (selection rewrite).
    function installStandardActions(options) {
        const opts = options || {};
        const editor = opts.editor;
        const monacoRef = opts.monaco || (typeof monaco !== 'undefined' ? monaco : null);
        const container = opts.container;
        if (!editor || !monacoRef) return { dispose: function () {} };
        const selectAllLabel = opts.selectAllLabel || tKey('monacoCtxSelectAll', 'Select All');
        const askAiLabel = opts.askAiLabel || tKey('monacoAskAi', 'Ask AI');
        const disposables = [];

        const selectAll = editor.addAction({
            id: 'cloud-select-all',
            label: selectAllLabel,
            keybindings: [monacoRef.KeyMod.CtrlCmd | monacoRef.KeyCode.KeyA],
            contextMenuGroupId: '9_cutcopypaste',
            contextMenuOrder: 1.5,
            run: function (ed) {
                const model = ed.getModel();
                if (!model) return;
                const range = model.getFullModelRange();
                ed.setSelection(range);
                ed.revealRange(range);
                ed.focus();
            }
        });
        if (selectAll) disposables.push(selectAll);

        if (opts.aiEndpoint !== false) {
            const askAi = editor.addAction({
                id: 'cloud-ask-ai',
                label: askAiLabel,
                contextMenuGroupId: 'navigation',
                contextMenuOrder: 0,
                precondition: 'editorHasSelection',
                run: function () {
                    if (!opts.aiEndpoint) return;
                    openAiActionMenu(editor, container || editor.getDomNode()?.parentElement || document.body, {
                        aiEndpoint: opts.aiEndpoint,
                        labels: opts.labels
                    });
                }
            });
            if (askAi) disposables.push(askAi);
        }

        const contentWatch = editor.onDidChangeModelContent(() => notifyUndoRedo(editor));
        const focusWatch = editor.onDidFocusEditorText(() => notifyUndoRedo(editor));
        disposables.push(contentWatch, focusWatch);
        notifyUndoRedo(editor);

        return {
            dispose: function () {
                disposables.forEach((d) => { try { d && d.dispose && d.dispose(); } catch (_) {} });
            }
        };
    }

    window.cloudMonacoShared = {
        VERSION: VERSION,
        CDN_BASE: CDN_BASE,
        NLS_MESSAGE_INDEXES: NLS_MESSAGE_INDEXES,
        applyNlsTranslations: applyNlsTranslations,
        configureLoader: configureLoader,
        loadMonaco: loadMonaco,
        defineAppThemes: defineAppThemes,
        standardEditorOptions: standardEditorOptions,
        waitForEditorFont: waitForEditorFont,
        installHorizontalScrollLock: installHorizontalScrollLock,
        isTxtFile: isTxtFile,
        resolveLang: resolveLang,
        installTransliteration: installTransliteration,
        installNumberedListContinuation: installNumberedListContinuation,
        // Kept for pages that still cache the previous bundle version.
        installTxtNumberedListContinuation: installNumberedListContinuation,
        installStandardActions: installStandardActions,
        openAiActionMenu: openAiActionMenu,
        t: tKey,
        getI18n: function () { return lastI18n; },
        getLang: function () { return lastLang; },
        notifyUndoRedo: notifyUndoRedo
    };
})();
