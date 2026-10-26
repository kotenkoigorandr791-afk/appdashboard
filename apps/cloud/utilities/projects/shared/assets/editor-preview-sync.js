(function () {
    if (window.cloudPreviewSync) return;
    function getEditor() {
        return window.monaco && monaco.editor.getEditors && monaco.editor.getEditors()[0];
    }
    function clearMarks(root) {
        if (!root) return;
        root.querySelectorAll('.pv-sync-mark').forEach(el => el.classList.remove('pv-sync-mark'));
    }
    function highlightEditorLine(editor, line) {
        editor.revealLineInCenter(line);
        editor.setPosition({ lineNumber: line, column: 1 });
        editor.focus();
        const deco = editor.createDecorationsCollection([{ range: new monaco.Range(line, 1, line, 1), options: { isWholeLine: true, className: 'pv-sync-line' } }]);
        setTimeout(() => deco.clear(), 1800);
    }
    function findLineByText(source, needle) {
        const n = String(needle || '').replace(/\s+/g, ' ').trim();
        if (n.length < 2) return null;
        const variants = [n, n.slice(0, 80), n.slice(0, 40)].filter((v, i, a) => v && a.indexOf(v) === i);
        for (const v of variants) {
            const idx = source.indexOf(v);
            if (idx >= 0) return source.slice(0, idx).split('\n').length;
            const soft = source.replace(/\s+/g, ' ');
            const softNeedle = v.replace(/\s+/g, ' ');
            const sidx = soft.indexOf(softNeedle);
            if (sidx >= 0) {
                let count = 0, seen = 0;
                for (let i = 0; i < source.length; i++) {
                    if (source[i] === '\n') count++;
                    if (!/\s/.test(source[i])) {
                        if (seen === sidx) return count + 1;
                        seen++;
                    }
                }
            }
        }
        return null;
    }
    function elementSearchText(el) {
        if (!el) return '';
        const tagged = el.getAttribute && el.getAttribute('data-source-line');
        if (tagged) return { line: parseInt(tagged, 10) };
        const text = (el.innerText || el.textContent || '').replace(/\s+/g, ' ').trim();
        if (text) return { text: text.slice(0, 120) };
        if (el.outerHTML) return { text: el.outerHTML.replace(/\s+/g, ' ').slice(0, 120) };
        return {};
    }
    function markAndScroll(el) {
        if (!el || !el.classList) return;
        clearMarks(el.ownerDocument);
        el.classList.add('pv-sync-mark');
        try { el.scrollIntoView({ block: 'center', behavior: 'smooth', inline: 'nearest' }); } catch (_) {}
        setTimeout(() => el.classList.remove('pv-sync-mark'), 1800);
    }
    function findPreviewNodeByText(root, needle) {
        if (!root || !needle) return null;
        const want = needle.replace(/\s+/g, ' ').trim();
        if (want.length < 2) return null;
        const walker = (root.ownerDocument || document).createTreeWalker(root, NodeFilter.SHOW_ELEMENT);
        let best = null, bestScore = 0;
        while (walker.nextNode()) {
            const el = walker.currentNode;
            if (!el.innerText) continue;
            const t = el.innerText.replace(/\s+/g, ' ').trim();
            if (!t) continue;
            if (t === want) return el;
            if (t.includes(want) || want.includes(t.slice(0, Math.min(t.length, want.length)))) {
                const score = Math.min(t.length, want.length) / Math.max(t.length, want.length);
                if (score > bestScore && t.length < want.length * 4) { best = el; bestScore = score; }
            }
        }
        return best;
    }
    function enableMarkdown(opts) {
        const preview = opts.previewRoot;
        let lastEl = null;
        if (!preview) return null;
        preview.addEventListener('click', ev => {
            const el = ev.target.closest ? ev.target.closest('h1,h2,h3,h4,h5,h6,p,li,pre,blockquote,table,tr,td,th,code,a,img,div,span') : null;
            if (!el || !preview.contains(el)) return;
            lastEl = el;
        });
        function toCode() {
            if (window.cloudEditorPanes && cloudEditorPanes.paneMode() !== 'split') return;
            const editor = getEditor();
            if (!editor) return;
            const el = lastEl || preview.querySelector('.pv-sync-mark');
            if (!el) return;
            const info = elementSearchText(el.closest('[data-source-line]') || el);
            if (info.line) { highlightEditorLine(editor, info.line); return; }
            const line = findLineByText(editor.getValue(), info.text);
            if (line) highlightEditorLine(editor, line);
        }
        function toPreview() {
            if (window.cloudEditorPanes && cloudEditorPanes.paneMode() !== 'split') return;
            const editor = getEditor();
            if (!editor) return;
            const sel = editor.getSelection();
            const text = editor.getModel().getValueInRange(sel).trim() || editor.getModel().getLineContent(sel.startLineNumber);
            const node = findPreviewNodeByText(preview, text);
            if (node) { lastEl = node; markAndScroll(node); }
        }
        return { toCode, toPreview };
    }
    function enableHtml(opts) {
        const frame = opts.iframe;
        let last = null;
        function doc() {
            try { return frame && frame.contentDocument; } catch (_) { return null; }
        }
        function bind() {
            const d = doc();
            if (!d || d.__pvSyncBound) return;
            d.__pvSyncBound = true;
            d.addEventListener('click', ev => {
                const el = ev.target;
                if (!el || el === d.documentElement || el === d.body) return;
                last = el;
            }, true);
            if (!d.getElementById('pv-sync-style')) {
                const st = d.createElement('style');
                st.id = 'pv-sync-style';
                st.textContent = '.pv-sync-mark{outline:2px solid rgba(255,170,0,.95)!important;background:rgba(255,200,0,.22)!important;}';
                (d.head || d.documentElement).appendChild(st);
            }
        }
        if (frame) frame.addEventListener('load', bind);
        function toCode() {
            if (window.cloudEditorPanes && cloudEditorPanes.paneMode() !== 'split') return;
            bind();
            const editor = getEditor();
            const d = doc();
            if (!editor || !d) return;
            const el = last || d.querySelector('.pv-sync-mark');
            if (!el) return;
            const info = elementSearchText(el);
            const line = info.line || findLineByText(editor.getValue(), info.text || (el.outerHTML || '').slice(0, 120));
            if (line) highlightEditorLine(editor, line);
        }
        function toPreview() {
            if (window.cloudEditorPanes && cloudEditorPanes.paneMode() !== 'split') return;
            bind();
            const editor = getEditor();
            const d = doc();
            if (!editor || !d || !d.body) return;
            const sel = editor.getSelection();
            const text = editor.getModel().getValueInRange(sel).trim() || editor.getModel().getLineContent(sel.startLineNumber);
            const node = findPreviewNodeByText(d.body, text);
            if (node) {
                last = node;
                clearMarks(d);
                node.classList.add('pv-sync-mark');
                try { node.scrollIntoView({ block: 'center', behavior: 'smooth' }); } catch (_) {}
                setTimeout(() => node.classList.remove('pv-sync-mark'), 1800);
            }
        }
        return { toCode, toPreview, rebind: bind };
    }
    function annotateMarkdownSourceLines(markdownText, html) {
        try {
            if (typeof marked === 'undefined' || !marked.lexer) return html;
            const tokens = marked.lexer(markdownText);
            let pos = 0;
            const parts = [];
            tokens.forEach(token => {
                if (!token || token.type === 'space') return;
                const raw = token.raw || '';
                let idx = markdownText.indexOf(raw, pos);
                if (idx < 0) idx = markdownText.indexOf(raw.trim(), pos);
                const line = idx >= 0 ? markdownText.slice(0, idx).split('\n').length : 1;
                if (idx >= 0) pos = idx + raw.length;
                let chunk = marked.parser([token]);
                chunk = chunk.replace(/^(\s*<[a-zA-Z0-9]+)/, '$1 data-source-line="' + line + '"');
                parts.push(chunk);
            });
            return parts.length ? parts.join('') : html;
        } catch (_) {
            return html;
        }
    }
    window.cloudPreviewSync = { enableMarkdown, enableHtml, annotateMarkdownSourceLines, findLineByText };
})();
