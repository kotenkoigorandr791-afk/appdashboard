(function () {
    const lang = new URLSearchParams(location.search).get('lang') || 'en';
    const L = {
        en: { expandEditor: 'Expand editor', expandPdf: 'Expand PDF', toPdf: 'To PDF', toCode: 'To code', toPdfTip: 'Jump from code selection to PDF', toCodeTip: 'Jump from PDF selection (or double-click) to code' },
        ru: { expandEditor: 'Развернуть редактор', expandPdf: 'Развернуть PDF', toPdf: 'В PDF', toCode: 'В код', toPdfTip: 'Перейти из выделения в коде к месту в PDF', toCodeTip: 'Перейти из выделения в PDF (или двойной клик) к месту в коде' },
        uk: { expandEditor: 'Розгорнути редактор', expandPdf: 'Розгорнути PDF', toPdf: 'У PDF', toCode: 'У код', toPdfTip: 'Перейти з виділення в коді до місця в PDF', toCodeTip: 'Перейти з виділення в PDF (або подвійний клік) до місця в коді' },
        be: { expandEditor: 'Разгарнуць рэдактар', expandPdf: 'Разгарнуць PDF', toPdf: 'У PDF', toCode: 'У код', toPdfTip: 'Перайсці з вылучэння ў кодзе да месца ў PDF', toCodeTip: 'Перайсці з вылучэння ў PDF (або двайны клік) да месца ў кодзе' }
    };
    const t = L[lang] || L.en;
    const pdfView = () => document.getElementById('pdf-view');
    const getEditor = () => window.monaco && monaco.editor.getEditors && monaco.editor.getEditors()[0];
    let lastClick = null;
    async function query(data) {
        if (!window.__synctexId) return null;
        const f = new FormData();
        Object.entries({ ...data, synctex_query: '1', synctex_id: window.__synctexId }).forEach(([k, v]) => f.append(k, v));
        try {
            const r = await (await fetch('', { method: 'POST', body: f })).json();
            return r && r.ok ? r : null;
        } catch (_) { return null; }
    }
    function pageWrapper(n) {
        const c = pdfView().querySelector('canvas[data-page-number="' + n + '"]');
        return c ? c.closest('.pdf-page-wrapper') : null;
    }
    function mark(wrapper, left, top, width, height) {
        wrapper.querySelectorAll('.synctex-mark').forEach(m => m.remove());
        const m = document.createElement('div');
        m.className = 'synctex-mark';
        m.style.cssText = 'position:absolute;pointer-events:none;border-radius:3px;background:rgba(255,200,0,.35);outline:2px solid rgba(255,170,0,.9);transition:opacity .6s;left:' + left + 'px;top:' + top + 'px;width:' + width + 'px;height:' + height + 'px';
        wrapper.appendChild(m);
        m.scrollIntoView({ block: 'center', behavior: 'smooth' });
        setTimeout(() => { m.style.opacity = '0'; }, 1800);
        setTimeout(() => m.remove(), 2500);
    }
    async function toPdf() {
        const e = getEditor();
        if (!e) return;
        const p = e.getSelection().getStartPosition();
        const r = await query({ mode: 'view', line: p.lineNumber, col: p.column });
        if (!r) return;
        const w = pageWrapper(r.page);
        if (!w) return;
        const s = parseFloat(w.dataset.scale) || 1;
        const h = Math.max(r.hh, 10);
        mark(w, Math.max(0, r.h * s - 4), Math.max(0, (r.v - h) * s), Math.max(r.w * s, 40) + 8, h * s + 6);
    }
    async function toCode(wrapper, cx, cy) {
        const e = getEditor();
        if (!e || !wrapper) return;
        const s = parseFloat(wrapper.dataset.scale) || 1;
        const canvas = wrapper.querySelector('canvas');
        const r = await query({ mode: 'edit', page: canvas.getAttribute('data-page-number'), x: cx / s, y: cy / s });
        if (!r) return;
        e.revealLineInCenter(r.line);
        e.setPosition({ lineNumber: r.line, column: Math.max(1, r.column) });
        e.focus();
        const d = e.createDecorationsCollection([{ range: new monaco.Range(r.line, 1, r.line, 1), options: { isWholeLine: true, className: 'synctex-line' } }]);
        setTimeout(() => d.clear(), 1800);
    }
    function pointFromSelection() {
        const sel = window.getSelection();
        if (!sel || !sel.rangeCount || sel.isCollapsed) return null;
        const node = sel.anchorNode && (sel.anchorNode.nodeType === 1 ? sel.anchorNode : sel.anchorNode.parentElement);
        const wrapper = node && node.closest && node.closest('.pdf-page-wrapper');
        if (!wrapper || !pdfView().contains(wrapper)) return null;
        const rects = sel.getRangeAt(0).getClientRects();
        const rect = rects.length ? rects[0] : sel.getRangeAt(0).getBoundingClientRect();
        const wr = wrapper.getBoundingClientRect();
        return { wrapper, x: rect.left - wr.left + Math.min(rect.width, 20) / 2, y: rect.top - wr.top + rect.height / 2 };
    }
    function pdfToCode() {
        const p = pointFromSelection() || lastClick;
        if (p) toCode(p.wrapper, p.x, p.y);
    }
    const style = document.createElement('style');
    style.textContent = '.synctex-line{background:rgba(255,200,0,.25)}.pdf-page-wrapper{position:relative}';
    document.head.appendChild(style);
    const ICONS = {
        right: '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-arrow-right preview-icon"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>',
        left: '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-arrow-left preview-icon"><path d="m12 19-7-7 7-7"/><path d="M19 12H5"/></svg>',
        chevRight: '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 17 5-5-5-5"/><path d="m13 17 5-5-5-5"/></svg>',
        chevLeft: '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m11 17-5-5 5-5"/><path d="m18 17-5-5 5-5"/></svg>'
    };
    style.textContent += '#resizer{position:relative;overflow:visible}.rz-tools{position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);display:flex;flex-direction:column;gap:2px;padding:3px;border-radius:6px;background:var(--panel-bg,#252526);border:1px solid var(--border-color,#3c3c3c);box-shadow:0 2px 8px rgba(0,0,0,.35);z-index:30;cursor:default}.rz-tools button{display:flex;align-items:center;justify-content:center;width:22px;height:22px;padding:0;border:none;border-radius:4px;background:transparent;color:var(--text-color,#ccc);cursor:pointer}.rz-tools button:hover{background:var(--btn-bg,#3a3d41)}body.pane-editor .rz-tools{transform:translate(-100%,-50%);left:100%}body.pane-pdf .rz-tools{transform:translate(0,-50%);left:0}body.pane-editor #pdf-panel{display:none!important}body.pane-editor #editor-panel,body.pane-pdf #pdf-panel{flex:1 1 auto!important;width:auto!important;min-width:0!important}body.pane-pdf #editor-panel{display:none!important}.mobile-layout .rz-tools{display:none}@media (max-width:768px){.rz-tools{display:none}}';
    function rzButton(icon, tip, fn) {
        const b = document.createElement('button');
        b.type = 'button';
        b.title = tip;
        b.innerHTML = icon;
        b.addEventListener('mousedown', ev => { ev.preventDefault(); ev.stopPropagation(); });
        b.addEventListener('touchstart', ev => ev.stopPropagation(), { passive: true });
        b.addEventListener('click', fn);
        return b;
    }
    function setPane(mode) {
        document.body.classList.toggle('pane-editor', mode === 'editor');
        document.body.classList.toggle('pane-pdf', mode === 'pdf');
        window.dispatchEvent(new Event('resize'));
        const e = getEditor();
        if (e) [60, 250, 600].forEach(ms => setTimeout(() => e.layout(), ms));
    }
    const paneMode = () => document.body.classList.contains('pane-editor') ? 'editor' : document.body.classList.contains('pane-pdf') ? 'pdf' : 'split';
    const resizerEl = document.getElementById('resizer');
    if (resizerEl) {
        const tools = document.createElement('div');
        tools.className = 'rz-tools';
        tools.append(
            rzButton(ICONS.right, t.toPdfTip, toPdf),
            rzButton(ICONS.left, t.toCodeTip, pdfToCode),
            rzButton(ICONS.chevRight, t.expandEditor, () => setPane(paneMode() === 'pdf' ? 'split' : 'editor')),
            rzButton(ICONS.chevLeft, t.expandPdf, () => setPane(paneMode() === 'editor' ? 'split' : 'pdf'))
        );
        resizerEl.appendChild(tools);
        resizerEl.addEventListener('mousedown', ev => { if (paneMode() !== 'split') ev.stopImmediatePropagation(); }, true);
        resizerEl.addEventListener('touchstart', ev => { if (paneMode() !== 'split') ev.stopImmediatePropagation(); }, { capture: true, passive: true });
    }
    pdfView().addEventListener('click', ev => {
        const w = ev.target.closest('.pdf-page-wrapper');
        if (!w) return;
        const r = w.getBoundingClientRect();
        lastClick = { wrapper: w, x: ev.clientX - r.left, y: ev.clientY - r.top };
    });
    pdfView().addEventListener('dblclick', ev => {
        const w = ev.target.closest('.pdf-page-wrapper');
        if (!w) return;
        const r = w.getBoundingClientRect();
        toCode(w, ev.clientX - r.left, ev.clientY - r.top);
    });
    const wait = setInterval(() => {
        const e = getEditor();
        if (!e) return;
        clearInterval(wait);
        e.addAction({ id: 'synctex-to-pdf', label: t.toPdf, keybindings: [monaco.KeyMod.CtrlCmd | monaco.KeyMod.Alt | monaco.KeyCode.KeyJ], run: toPdf });
    }, 400);
})();
