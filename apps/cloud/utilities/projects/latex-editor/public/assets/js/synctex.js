(function () {
    const lang = new URLSearchParams(location.search).get('lang') || 'en';
    const L = {
        en: { expandEditor: 'Expand editor', expandPdf: 'Expand PDF', showEditor: 'Show editor', showPdf: 'Show PDF', toPdf: 'To PDF', toCode: 'To code', toPdfTip: 'Jump from code selection to PDF', toCodeTip: 'Jump from PDF selection (or double-click) to code' },
        ru: { expandEditor: 'Развернуть редактор', expandPdf: 'Развернуть PDF', showEditor: 'Показать редактор', showPdf: 'Показать PDF', toPdf: 'В PDF', toCode: 'В код', toPdfTip: 'Перейти из выделения в коде к месту в PDF', toCodeTip: 'Перейти из выделения в PDF (или двойной клик) к месту в коде' },
        uk: { expandEditor: 'Розгорнути редактор', expandPdf: 'Розгорнути PDF', showEditor: 'Показати редактор', showPdf: 'Показати PDF', toPdf: 'У PDF', toCode: 'У код', toPdfTip: 'Перейти з виділення в коді до місця в PDF', toCodeTip: 'Перейти з виділення в PDF (або подвійний клік) до місця в коді' },
        be: { expandEditor: 'Разгарнуць рэдактар', expandPdf: 'Разгарнуць PDF', showEditor: 'Паказаць рэдактар', showPdf: 'Паказаць PDF', toPdf: 'У PDF', toCode: 'У код', toPdfTip: 'Перайсці з вылучэння ў кодзе да месца ў PDF', toCodeTip: 'Перайсці з вылучэння ў PDF (або двайны клік) да месца ў кодзе' }
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
        const view = pdfView();
        return view.querySelector('.pdf-page-wrapper[data-page-number="' + n + '"]')
            || (() => { const c = view.querySelector('canvas[data-page-number="' + n + '"]'); return c ? c.closest('.pdf-page-wrapper') : null; })();
    }
    function scrollPdfTo(wrapper, markTop, markHeight) {
        const view = pdfView();
        if (!view || !wrapper) return;
        const y = wrapper.offsetTop + markTop + (markHeight || 0) / 2 - view.clientHeight / 2;
        const max = Math.max(0, view.scrollHeight - view.clientHeight);
        view.scrollTo({ top: Math.max(0, Math.min(max, y)), left: view.scrollLeft, behavior: 'smooth' });
    }
    function mark(wrapper, left, top, width, height) {
        wrapper.querySelectorAll('.synctex-mark').forEach(m => m.remove());
        const m = document.createElement('div');
        m.className = 'synctex-mark';
        m.style.cssText = 'position:absolute;pointer-events:none;border-radius:3px;background:rgba(255,200,0,.35);outline:2px solid rgba(255,170,0,.9);transition:opacity .6s;left:' + left + 'px;top:' + top + 'px;width:' + width + 'px;height:' + height + 'px';
        wrapper.appendChild(m);
        scrollPdfTo(wrapper, top, height);
        setTimeout(() => { m.style.opacity = '0'; }, 1800);
        setTimeout(() => m.remove(), 2500);
    }
    function isSplit() {
        return !window.cloudEditorPanes || cloudEditorPanes.paneMode() === 'split';
    }
    async function toPdf() {
        if (!isSplit()) return;
        const e = getEditor();
        if (!e) return;
        const p = e.getSelection().getStartPosition();
        const r = await query({ mode: 'view', line: p.lineNumber, col: p.column });
        if (!r) return;
        const w = pageWrapper(r.page);
        if (!w) return;
        w.scrollIntoView({ block: 'nearest' });
        const s = parseFloat(w.dataset.scale) || 1;
        const h = Math.max(r.hh, 10);
        mark(w, Math.max(0, r.h * s - 4), Math.max(0, (r.v - h) * s), Math.max(r.w * s, 40) + 8, h * s + 6);
    }
    async function toCode(wrapper, cx, cy) {
        if (!isSplit()) return;
        const e = getEditor();
        if (!e || !wrapper) return;
        const s = parseFloat(wrapper.dataset.scale) || 1;
        const page = wrapper.dataset.pageNumber || (wrapper.querySelector('canvas') && wrapper.querySelector('canvas').getAttribute('data-page-number'));
        const r = await query({ mode: 'edit', page, x: cx / s, y: cy / s });
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
        if (!isSplit()) return;
        const p = pointFromSelection() || lastClick;
        if (p) toCode(p.wrapper, p.x, p.y);
    }
    const style = document.createElement('style');
    style.textContent = '.synctex-line{background:rgba(255,200,0,.25)}.pdf-page-wrapper{position:relative}';
    document.head.appendChild(style);
    if (window.cloudEditorPanes) {
        cloudEditorPanes.enable({
            onToPreview: toPdf,
            onToCode: pdfToCode,
            toPreviewTip: t.toPdfTip,
            toCodeTip: t.toCodeTip,
            expandEditorTip: t.expandEditor,
            expandPreviewTip: t.expandPdf,
            showEditorTip: t.showEditor,
            showPreviewTip: t.showPdf
        });
    }
    pdfView().addEventListener('click', ev => {
        const w = ev.target.closest('.pdf-page-wrapper');
        if (!w) return;
        const r = w.getBoundingClientRect();
        lastClick = { wrapper: w, x: ev.clientX - r.left, y: ev.clientY - r.top };
    });
    pdfView().addEventListener('dblclick', ev => {
        if (!isSplit()) return;
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
