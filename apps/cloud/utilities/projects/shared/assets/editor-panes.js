(function () {
    if (window.cloudEditorPanes) return;
    const lang = new URLSearchParams(location.search).get('lang') || 'en';
    const L = {
        en: { expandEditor: 'Expand editor', expandPreview: 'Expand preview', showEditor: 'Show editor', showPreview: 'Show preview', toPreview: 'To preview', toCode: 'To code', toPreviewTip: 'Jump from code selection to preview', toCodeTip: 'Jump from preview selection to code' },
        ru: { expandEditor: 'Развернуть редактор', expandPreview: 'Развернуть просмотр', showEditor: 'Показать редактор', showPreview: 'Показать просмотр', toPreview: 'В просмотр', toCode: 'В код', toPreviewTip: 'Перейти из выделения в коде к просмотру', toCodeTip: 'Перейти из выделения в просмотре к коду' },
        uk: { expandEditor: 'Розгорнути редактор', expandPreview: 'Розгорнути перегляд', showEditor: 'Показати редактор', showPreview: 'Показати перегляд', toPreview: 'У перегляд', toCode: 'У код', toPreviewTip: 'Перейти з виділення в коді до перегляду', toCodeTip: 'Перейти з виділення в перегляді до коду' },
        be: { expandEditor: 'Разгарнуць рэдактар', expandPreview: 'Разгарнуць прагляд', showEditor: 'Паказаць рэдактар', showPreview: 'Паказаць прагляд', toPreview: 'У прагляд', toCode: 'У код', toPreviewTip: 'Перайсці з вылучэння ў кодзе да прагляду', toCodeTip: 'Перайсці з вылучэння ў праглядзе да кода' }
    };
    const t = L[lang] || L.en;
    const ICONS = {
        right: '<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>',
        left: '<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 19-7-7 7-7"/><path d="M19 12H5"/></svg>',
        chevRight: '<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 17 5-5-5-5"/><path d="m13 17 5-5-5-5"/></svg>',
        chevLeft: '<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m11 17-5-5 5-5"/><path d="m18 17-5-5 5-5"/></svg>'
    };
    let state = null;
    function ensureStyles() {
        if (document.getElementById('cloud-editor-panes-style')) return;
        const st = document.createElement('style');
        st.id = 'cloud-editor-panes-style';
        st.textContent = [
            '#resizer,.resizer{position:relative;overflow:visible}',
            '.rz-tools{position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);display:flex;flex-direction:column;align-items:center;gap:28px;z-index:30;cursor:default;pointer-events:auto}',
            '.rz-group{display:flex;flex-direction:column;gap:1px;padding:3px;border-radius:10px;background:var(--panel-bg,#252526);border:1px solid var(--border-color,#3c3c3c);box-shadow:0 1px 5px rgba(0,0,0,.3)}',
            '.rz-group.rz-panes{min-height:42px;justify-content:center}',
            '.rz-tools button{display:flex;align-items:center;justify-content:center;width:18px;height:18px;max-height:18px;padding:0;border:none!important;border-radius:9px!important;background:transparent;color:var(--text-color,#ccc);cursor:pointer;pointer-events:auto;overflow:hidden;transition:opacity .22s ease,max-height .22s ease,max-width .22s ease,transform .22s ease,margin .22s ease}',
            '.rz-tools button:hover{background:var(--btn-bg,#3a3d41)}',
            '.rz-tools button.rz-collapsed{opacity:0;max-height:0;height:0;margin:0;pointer-events:none;transform:scale(.85)}',
            'body.pane-editor .rz-tools{transform:translate(-100%,-50%);left:100%}',
            'body.pane-preview .rz-tools,body.pane-pdf .rz-tools{transform:translate(0,-50%);left:0}',
            'body.pane-editor .rz-sync,body.pane-preview .rz-sync,body.pane-pdf .rz-sync{display:none!important}',
            'body.pane-editor #pdf-panel,body.pane-editor .preview-panel{display:none!important}',
            'body.pane-preview #editor-panel,body.pane-preview .editor-panel,body.pane-pdf #editor-panel{display:none!important}',
            'body.pane-editor #editor-panel,body.pane-editor .editor-panel,body.pane-preview #pdf-panel,body.pane-preview .preview-panel,body.pane-pdf #pdf-panel{flex:1 1 auto!important;width:auto!important;min-width:0!important;height:auto!important}',
            'body.rz-stack #resizer,body.rz-stack .resizer{overflow:visible!important;z-index:60;flex-shrink:0}',
            'body.rz-stack #main,body.rz-stack .editor-container{overflow:visible!important}',
            'body.rz-stack .rz-tools{display:flex!important;flex-direction:row;align-items:center;justify-content:center;gap:12px;top:50%;left:50%;transform:translate(-50%,-50%);max-width:calc(100% - 8px);z-index:61;pointer-events:auto}',
            'body.rz-stack .rz-group{flex-direction:row;pointer-events:auto}',
            'body.rz-stack .rz-group.rz-panes{min-height:0;min-width:42px;justify-content:center}',
            'body.rz-stack .rz-tools button svg{transform:rotate(90deg)}',
            'body.rz-stack .rz-tools button.rz-collapsed{max-width:0;width:0;max-height:18px;height:18px;opacity:0;pointer-events:none}',
            'body.rz-stack.pane-editor .rz-tools{top:0!important;left:50%!important;right:auto!important;bottom:auto!important;transform:translate(-50%,calc(-100% - 6px))!important}',
            'body.rz-stack.pane-preview .rz-tools,body.rz-stack.pane-pdf .rz-tools{top:100%!important;left:50%!important;right:auto!important;bottom:auto!important;transform:translate(-50%,6px)!important}',
            'body.rz-stack.pane-editor #resizer,body.rz-stack.pane-editor .resizer,body.rz-stack.pane-preview #resizer,body.rz-stack.pane-preview .resizer,body.rz-stack.pane-pdf #resizer,body.rz-stack.pane-pdf .resizer{position:relative;z-index:60}',
            '.pv-sync-mark{outline:2px solid rgba(255,170,0,.95)!important;background:rgba(255,200,0,.22)!important;border-radius:3px}',
            '.pv-sync-line{background:rgba(255,200,0,.25)}'
        ].join('');
        document.head.appendChild(st);
    }
    function stopDrag(ev) {
        ev.preventDefault();
        ev.stopPropagation();
    }
    function btn(icon, tip, fn) {
        const b = document.createElement('button');
        b.type = 'button';
        b.title = tip;
        b.innerHTML = icon;
        b.addEventListener('pointerdown', stopDrag);
        b.addEventListener('mousedown', stopDrag);
        b.addEventListener('touchstart', ev => ev.stopPropagation(), { passive: true });
        b.addEventListener('click', ev => { ev.preventDefault(); ev.stopPropagation(); fn(ev); });
        return b;
    }
    function getEditor() {
        if (state && typeof state.getEditor === 'function') return state.getEditor();
        return window.monaco && monaco.editor.getEditors && monaco.editor.getEditors()[0];
    }
    function paneMode() {
        if (document.body.classList.contains('pane-editor')) return 'editor';
        if (document.body.classList.contains('pane-preview') || document.body.classList.contains('pane-pdf')) return 'preview';
        return 'split';
    }
    function updateStackMode() {
        const resizer = state && state.tools && state.tools.parentElement;
        let stacked = false;
        if (resizer) {
            const w = resizer.offsetWidth || 0;
            const h = resizer.offsetHeight || 0;
            if (w > 0 && h > 0) stacked = w >= Math.max(16, h) * 2;
        }
        if (document.body.classList.contains('mobile-layout')) stacked = true;
        document.body.classList.toggle('rz-stack', !!stacked);
    }
    function updatePaneButtons() {
        if (!state) return;
        updateStackMode();
        const mode = paneMode();
        if (state.syncGroup) state.syncGroup.style.display = mode === 'split' ? '' : 'none';
        if (state.btnExpandEditor) {
            state.btnExpandEditor.classList.toggle('rz-collapsed', mode === 'editor');
            state.btnExpandEditor.title = mode === 'preview'
                ? ((state.opts && state.opts.showEditorTip) || t.showEditor)
                : ((state.opts && state.opts.expandEditorTip) || t.expandEditor);
        }
        if (state.btnExpandPreview) {
            state.btnExpandPreview.classList.toggle('rz-collapsed', mode === 'preview');
            state.btnExpandPreview.title = mode === 'editor'
                ? ((state.opts && state.opts.showPreviewTip) || t.showPreview)
                : ((state.opts && state.opts.expandPreviewTip) || t.expandPreview);
        }
    }
    function setPane(mode) {
        if (mode === 'pdf') mode = 'preview';
        document.body.classList.toggle('pane-editor', mode === 'editor');
        document.body.classList.toggle('pane-preview', mode === 'preview');
        document.body.classList.remove('pane-pdf');
        updatePaneButtons();
        window.dispatchEvent(new Event('resize'));
        const e = getEditor();
        if (e) [60, 250, 600].forEach(ms => setTimeout(() => e.layout(), ms));
        if (state && typeof state.onPaneChange === 'function') state.onPaneChange(mode);
    }
    function restoreSplitForDrag(ev) {
        if (ev.target && ev.target.closest && ev.target.closest('.rz-tools')) return;
        if (paneMode() !== 'split') setPane('split');
    }
    function enable(opts) {
        ensureStyles();
        const resizer = opts && opts.resizer || document.getElementById('resizer') || document.querySelector('.resizer');
        if (!resizer || resizer.__rzBound) return state;
        resizer.__rzBound = true;
        state = {
            opts: opts || {},
            getEditor: opts && opts.getEditor,
            onToPreview: opts && opts.onToPreview,
            onToCode: opts && opts.onToCode,
            onPaneChange: opts && opts.onPaneChange,
            hasSync: !!(opts && (opts.onToPreview || opts.onToCode || opts.sync !== false))
        };
        const tools = document.createElement('div');
        tools.className = 'rz-tools';
        const sync = document.createElement('div');
        sync.className = 'rz-group rz-sync';
        if (state.hasSync) {
            sync.append(
                btn(ICONS.right, (opts && opts.toPreviewTip) || t.toPreviewTip, () => { if (paneMode() !== 'split') return; if (state.onToPreview) state.onToPreview(); }),
                btn(ICONS.left, (opts && opts.toCodeTip) || t.toCodeTip, () => { if (paneMode() !== 'split') return; if (state.onToCode) state.onToCode(); })
            );
            tools.appendChild(sync);
        }
        const panes = document.createElement('div');
        panes.className = 'rz-group rz-panes';
        const btnExpandEditor = btn(ICONS.chevRight, (opts && opts.expandEditorTip) || t.expandEditor, () => setPane(paneMode() === 'preview' ? 'split' : 'editor'));
        const btnExpandPreview = btn(ICONS.chevLeft, (opts && opts.expandPreviewTip) || t.expandPreview, () => setPane(paneMode() === 'editor' ? 'split' : 'preview'));
        panes.append(btnExpandEditor, btnExpandPreview);
        tools.appendChild(panes);
        resizer.appendChild(tools);
        state.syncGroup = sync;
        state.tools = tools;
        state.btnExpandEditor = btnExpandEditor;
        state.btnExpandPreview = btnExpandPreview;
        resizer.addEventListener('pointerdown', restoreSplitForDrag, true);
        resizer.addEventListener('mousedown', restoreSplitForDrag, true);
        resizer.addEventListener('touchstart', restoreSplitForDrag, { capture: true, passive: true });
        window.addEventListener('resize', updatePaneButtons);
        if (typeof ResizeObserver !== 'undefined') {
            try { new ResizeObserver(updatePaneButtons).observe(resizer); } catch (_) {}
        }
        updatePaneButtons();
        return state;
    }
    window.cloudEditorPanes = { enable, setPane, paneMode, getEditor, t, ICONS, updateStackMode };
})();
