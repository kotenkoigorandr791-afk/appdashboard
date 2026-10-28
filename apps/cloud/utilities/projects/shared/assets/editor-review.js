/*
 * Cloud editor review bridge.
 *
 * Runs inside the Monaco-based editor iframes (LaTeX / Markdown / HTML).
 * The review UI itself lives in the parent cloud app (assets/app/reviews.js);
 * this bridge only exposes the editor selection and paints inline comment
 * markers over the code.
 */
(function () {
    'use strict';

    if (window.__cloudReviewBridgeLoaded) return;
    window.__cloudReviewBridgeLoaded = true;

    const q = new URLSearchParams(location.search);
    const light = q.get('theme') === 'light';

    const style = document.createElement('style');
    style.textContent = [
        '.rv-line{background:color-mix(in srgb, #f0b429 14%, transparent)!important;box-shadow:inset 3px 0 0 #f0b429;}',
        '.rv-annot{display:inline-flex;align-items:center;gap:6px;max-width:min(340px,42vw);margin-left:10px;padding:3px 9px 3px 7px;border-radius:999px;border:1px solid color-mix(in srgb, #f0b429 55%, transparent);background:' + (light ? 'rgba(255,255,255,.94)' : 'rgba(28,34,42,.94)') + ';color:' + (light ? '#243044' : '#eef2f8') + ';font:500 11.5px/1.35 "Segoe UI",system-ui,sans-serif;box-shadow:0 6px 18px rgba(0,0,0,.28);cursor:pointer;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;vertical-align:middle;backdrop-filter:blur(6px);-webkit-backdrop-filter:blur(6px);}',
        '.rv-annot:hover{border-color:#f0b429;box-shadow:0 8px 22px rgba(240,180,41,.28);}',
        '.rv-annot-dot{width:16px;height:16px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;background:#f0b429;color:#1d232c;font:700 10px/1 "Segoe UI",sans-serif;flex:0 0 auto;}',
        '.rv-annot-text{overflow:hidden;text-overflow:ellipsis;max-width:250px;}'
    ].join('');
    document.head.appendChild(style);

    let editor = null;
    let deco = null;
    let widgets = [];
    let lastItems = [];

    function clearMarkers() {
        widgets.forEach((w) => { try { editor.removeContentWidget(w); } catch (_) {} });
        widgets = [];
        if (deco) { try { deco.clear(); } catch (_) {} deco = null; }
    }

    function commentLabel(item) {
        return (item.author ? item.author + ': ' : '') + (item.comment || item.suggestion || '');
    }

    function createWidget(line, items) {
        const node = document.createElement('div');
        node.className = 'rv-annot';
        const dot = document.createElement('span');
        dot.className = 'rv-annot-dot';
        dot.textContent = items.length > 1 ? String(items.length) : '💬';
        const text = document.createElement('span');
        text.className = 'rv-annot-text';
        text.textContent = commentLabel(items[0]).slice(0, 90) || 'Review';
        node.append(dot, text);
        node.title = items.map(commentLabel).join('\n');
        node.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            try {
                window.parent.postMessage({
                    source: 'cloudReviewIframe',
                    type: 'focus',
                    line,
                    id: items[0].id
                }, '*');
            } catch (_) {}
        });
        return {
            getId: () => 'rv-annot-' + line,
            getDomNode: () => node,
            getPosition: () => ({
                position: { lineNumber: line, column: editor.getModel().getLineMaxColumn(line) },
                preference: [window.monaco.editor.ContentWidgetPositionPreference.EXACT]
            })
        };
    }

    function applyMarkers(items) {
        lastItems = Array.isArray(items) ? items : [];
        if (!editor || !window.monaco) return;
        clearMarkers();
        const open = lastItems.filter((i) => i && i.status === 'open');
        if (!open.length) return;
        const byLine = new Map();
        open.forEach((item) => {
            const line = Math.max(1, Math.min(editor.getModel().getLineCount(), Number(item.start_line) || 1));
            if (!byLine.has(line)) byLine.set(line, []);
            byLine.get(line).push(item);
        });
        const decorations = [];
        byLine.forEach((list, line) => {
            const endLine = Math.max(line, Math.min(editor.getModel().getLineCount(), Number(list[0].end_line) || line));
            decorations.push({
                range: new window.monaco.Range(line, 1, endLine, 1),
                options: { isWholeLine: true, className: 'rv-line' }
            });
            try {
                const widget = createWidget(line, list);
                editor.addContentWidget(widget);
                widgets.push(widget);
            } catch (_) {}
        });
        if (deco) deco.set(decorations);
        else deco = editor.createDecorationsCollection(decorations);
    }

    function selectionInfo() {
        if (!editor) return null;
        const model = editor.getModel();
        const sel = editor.getSelection();
        if (!model || !sel) return null;
        let endLine = sel.endLineNumber;
        if (sel.endColumn === 1 && endLine > sel.startLineNumber) endLine--;
        const text = sel.isEmpty() ? model.getLineContent(sel.startLineNumber) : model.getValueInRange(sel);
        return { start: sel.startLineNumber, end: endLine, quote: text };
    }

    function revealLine(line) {
        if (!editor) return;
        const ln = Math.max(1, Math.min(editor.getModel().getLineCount(), Number(line) || 1));
        editor.revealLineInCenter(ln);
        editor.setPosition({ lineNumber: ln, column: 1 });
        editor.focus();
    }

    function applySuggestion(item) {
        if (!editor || !item) return false;
        const model = editor.getModel();
        const quote = String(item.quote || '');
        if (!quote) return false;
        const matches = model.findMatches(quote, false, false, true, null, false);
        if (!matches.length) return false;
        matches.sort((a, b) => Math.abs(a.range.startLineNumber - (Number(item.start_line) || 1)) - Math.abs(b.range.startLineNumber - (Number(item.start_line) || 1)));
        editor.executeEdits('review', [{ range: matches[0].range, text: String(item.suggestion || '') }]);
        editor.pushUndoStop();
        return true;
    }

    function reply(reqId, ok, result) {
        try {
            window.parent.postMessage({
                source: 'cloudReviewIframe',
                type: 'result',
                reqId,
                ok,
                result: result || null
            }, '*');
        } catch (_) {}
    }

    window.addEventListener('message', (ev) => {
        const d = ev.data;
        if (!d || d.source !== 'cloudParent' || d.type !== 'cloudReview') return;
        if (d.action === 'ping') { reply(d.reqId, true, { ready: !!editor }); return; }
        if (d.action === 'getSelection') { reply(d.reqId, !!editor, selectionInfo()); return; }
        if (d.action === 'apply') { applyMarkers(d.items); reply(d.reqId, true); return; }
        if (d.action === 'reveal') { revealLine(d.line); reply(d.reqId, true); return; }
        if (d.action === 'applySuggestion') { reply(d.reqId, applySuggestion(d.item)); return; }
        reply(d.reqId, false);
    });

    function notifyReady() {
        try {
            window.parent.postMessage({ source: 'cloudReviewIframe', type: 'ready' }, '*');
        } catch (_) {}
    }

    const wait = setInterval(() => {
        const e = window.monaco && window.monaco.editor.getEditors && window.monaco.editor.getEditors()[0];
        if (!e) return;
        clearInterval(wait);
        editor = e;
        notifyReady();
        if (lastItems.length) applyMarkers(lastItems);
    }, 300);
    setTimeout(() => clearInterval(wait), 60000);

    window.__cloudReviewBridge = { applyMarkers, getSelection: selectionInfo, revealLine, applySuggestion };
})();
