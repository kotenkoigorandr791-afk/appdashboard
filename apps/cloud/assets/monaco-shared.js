// assets/monaco-shared.js
// Single source of truth for everything the cloud application shares between its
// Monaco editors: the main file editor (assets/monaco-editor.js) and the project
// editors (utilities/projects/html-editor, markdown-editor, latex-editor, zip-viewer).
//
// Every editor must load this file before its own Monaco bootstrap and use
// cloudMonacoShared so that all of them run the same Monaco version, the same
// theme palette, the same editor options, the same localized context menu, the
// same transliteration rules and the same .txt numbered-list behaviour.
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
    const TRANSLIT_TABLES = {
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

    // Loads Monaco once, translating its built-in UI first. Editors that already have
    // the locale in memory can pass `i18n`; otherwise `i18nUrl` is fetched for `lang`.
    function loadMonaco(options) {
        const opts = options || {};
        const lang = opts.lang || 'en';

        const applyAndLoad = (i18n) => {
            applyNlsTranslations(i18n || {}, lang);
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

    function isTxtFile(filePath) {
        return /\.txt$/i.test(filePath || '');
    }

    // Typing Russian/Ukrainian on an English keyboard layout: Latin letters are
    // rewritten to the Cyrillic letter at the same layout position, punctuation is
    // left untouched. Returns a disposable.
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

        const keyDown = editor.onKeyDown((e) => {
            if (isComposing) return;

            const activeLang = getLang();
            if (!activeLang || activeLang === 'en' || !TRANSLIT_TABLES[activeLang]) return;

            const key = e.browserEvent ? e.browserEvent.key : null;
            if (!key) return;

            // Already Cyrillic (real Russian/Ukrainian layout) - leave it alone.
            if (/[\u0400-\u04FF]/.test(key)) return;

            const isPairChar = ['(', ')', '{', '}'].includes(key);
            const table = TRANSLIT_TABLES[activeLang];
            if (key.length !== 1 || !Object.prototype.hasOwnProperty.call(table, key)) return;
            if (e.ctrlKey || e.altKey || e.metaKey || isPairChar) return;

            e.preventDefault();
            const text = table[key];
            const selection = editor.getSelection();
            if (selection && !selection.isEmpty()) {
                editor.executeEdits('transliteration', [{ range: selection, text }]);
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

    // Ordered-list continuation for .txt files: Enter on "1. foo" writes "2. " on the
    // next line, keeping the indentation, the delimiter and the spacing. Enter on a
    // marker without text removes the marker so the list can be ended. Returns a disposable.
    function installTxtNumberedListContinuation(options) {
        const editor = options.editor;
        const monacoRef = options.monaco;
        const filePath = options.filePath;
        if (!editor || !monacoRef || !isTxtFile(filePath)) return { dispose: function () {} };

        const markerPattern = /^(\s*)(\d+)([.)])([ \t]+)(.*)$/;
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
            const linePrefix = model.getValueInRange(
                new monacoRef.Range(position.lineNumber, 1, position.lineNumber, position.column)
            );
            const match = markerPattern.exec(linePrefix);
            if (!match) return;

            const indent = match[1];
            const digits = match[2];
            const delimiter = match[3];
            const spacing = match[4];
            const itemText = match[5];
            const restOfLine = model.getValueInRange(new monacoRef.Range(
                position.lineNumber,
                position.column,
                position.lineNumber,
                model.getLineMaxColumn(position.lineNumber)
            ));

            e.preventDefault();

            if (itemText.trim() === '' && restOfLine.trim() === '') {
                editor.executeEdits('txt-list-continue', [{
                    range: new monacoRef.Range(position.lineNumber, 1, position.lineNumber, position.column),
                    text: indent
                }]);
                editor.setPosition({ lineNumber: position.lineNumber, column: indent.length + 1 });
                return;
            }

            const nextMarker = indent + (Number(digits) + 1) + delimiter + spacing;
            editor.executeEdits('txt-list-continue', [{
                range: new monacoRef.Range(position.lineNumber, position.column, position.lineNumber, position.column),
                text: '\n' + nextMarker
            }]);
            editor.setPosition({ lineNumber: position.lineNumber + 1, column: nextMarker.length + 1 });
        });

        return {
            dispose: function () { keyDown.dispose(); }
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
        isTxtFile: isTxtFile,
        installTransliteration: installTransliteration,
        installTxtNumberedListContinuation: installTxtNumberedListContinuation
    };
})();
