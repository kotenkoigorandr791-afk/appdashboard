(function() {
    var aiDecoIds = [];
    var aiZoneIds = [];
    var editorRef = null;
    function ensureStyles() {
        if (document.getElementById('cloud-iframe-monaco-ai-styles')) return;
        var s = document.createElement('style');
        s.id = 'cloud-iframe-monaco-ai-styles';
        s.textContent = '.ai-agent-edit-line{background:rgba(133,181,3,.16)!important;border-left:3px solid #85b503!important;}.ai-agent-edit-glyph{background:#85b503!important;width:4px!important;margin-left:3px!important;}.monaco-ai-removed-zone{box-sizing:border-box;width:100%;font-family:Consolas,"Courier New",monospace;font-size:12px;line-height:1.35;border-top:1px solid rgba(220,38,38,.32);border-bottom:1px solid rgba(220,38,38,.32);background:rgba(220,38,38,.16);color:#ffb4b4;overflow:hidden;}';
        document.head.appendChild(s);
    }
    function esc(t) {
        var d = document.createElement('div');
        d.textContent = t == null ? '' : String(t);
        return d.innerHTML;
    }
    function computeChangedLineRanges(oldText, newText) {
        var oldLines = String(oldText ?? '').split(/\r\n|\r|\n/);
        var newLines = String(newText ?? '').split(/\r\n|\r|\n/);
        var m = oldLines.length;
        var n = newLines.length;
        var dp = Array.from({ length: m + 1 }, function() { return new Array(n + 1).fill(0); });
        for (var i = m - 1; i >= 0; i--) {
            for (var j = n - 1; j >= 0; j--) {
                dp[i][j] = oldLines[i] === newLines[j] ? dp[i + 1][j + 1] + 1 : Math.max(dp[i + 1][j], dp[i][j + 1]);
            }
        }
        var ranges = [];
        var ii = 0;
        var jj = 0;
        var pendingNewStart = null;
        var pendingNewEnd = null;
        var pendingDeleteAt = null;
        function flush() {
            if (pendingNewStart !== null) {
                ranges.push({ startLineNumber: Math.max(1, pendingNewStart + 1), endLineNumber: Math.max(1, pendingNewEnd + 1) });
            } else if (pendingDeleteAt !== null && n > 0) {
                var line = Math.min(n, Math.max(1, pendingDeleteAt + 1));
                ranges.push({ startLineNumber: line, endLineNumber: line });
            }
            pendingNewStart = null;
            pendingNewEnd = null;
            pendingDeleteAt = null;
        }
        while (ii < m || jj < n) {
            if (ii < m && jj < n && oldLines[ii] === newLines[jj]) {
                flush();
                ii++;
                jj++;
            } else if (jj < n && (ii === m || dp[ii][jj + 1] >= dp[ii + 1][jj])) {
                if (pendingNewStart === null) pendingNewStart = jj;
                pendingNewEnd = jj;
                jj++;
            } else if (ii < m) {
                if (pendingNewStart === null) pendingDeleteAt = Math.max(0, jj - 1);
                ii++;
            }
        }
        flush();
        return ranges;
    }
    function buildLineDiff(oldText, newText) {
        var oldLines = String(oldText ?? '').split(/\r\n|\r|\n/);
        var newLines = String(newText ?? '').split(/\r\n|\r|\n/);
        var m = oldLines.length;
        var n = newLines.length;
        var dp = Array.from({ length: m + 1 }, function() { return new Array(n + 1).fill(0); });
        for (var i = m - 1; i >= 0; i--) {
            for (var j = n - 1; j >= 0; j--) {
                dp[i][j] = oldLines[i] === newLines[j] ? dp[i + 1][j + 1] + 1 : Math.max(dp[i + 1][j], dp[i][j + 1]);
            }
        }
        var rows = [];
        var i = 0;
        var j = 0;
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
    function clearZones(ed) {
        if (!ed || !aiZoneIds.length) return;
        ed.changeViewZones(function(accessor) {
            aiZoneIds.forEach(function(id) { accessor.removeZone(id); });
        });
        aiZoneIds = [];
    }
    function clearHighlight(ed) {
        if (!ed) return;
        if (aiDecoIds.length) ed.deltaDecorations(aiDecoIds, []);
        aiDecoIds = [];
        clearZones(ed);
    }
    function createRemovedZoneNode(lines) {
        var node = document.createElement('div');
        node.className = 'monaco-ai-removed-zone';
        node.innerHTML = lines.map(function(line) {
            return '<div style="display:grid;grid-template-columns:24px 1fr;gap:8px;padding:2px 10px;white-space:normal;overflow-wrap:anywhere;"><span style="opacity:.85;user-select:none;">-</span><span style="white-space:pre-wrap;">' + esc(line) + '</span></div>';
        }).join('');
        return node;
    }
    function highlightPreview(ed, oldText, newText) {
        var model = ed.getModel();
        if (!ed || !model || typeof monaco === 'undefined') return [];
        var diffRows = buildLineDiff(oldText, newText);
        var ranges = computeChangedLineRanges(oldText, newText);
        var addedLineNumbers = new Set(diffRows.filter(function(row) { return row.type === 'added'; }).map(function(row) { return row.newLineNumber; }));
        var decorationRanges = addedLineNumbers.size > 0
            ? Array.from(addedLineNumbers).sort(function(a, b) { return a - b; }).map(function(lineNumber) { return { startLineNumber: lineNumber, endLineNumber: lineNumber }; })
            : ranges;
        var decorations = decorationRanges.map(function(range) {
            return {
                range: new monaco.Range(range.startLineNumber, 1, range.endLineNumber, model.getLineMaxColumn(Math.min(range.endLineNumber, model.getLineCount()))),
                options: {
                    isWholeLine: true,
                    className: 'ai-agent-edit-line',
                    linesDecorationsClassName: 'ai-agent-edit-glyph',
                    overviewRuler: { color: '#85b503', position: monaco.editor.OverviewRulerLane.Right },
                    minimap: { color: '#85b503', position: monaco.editor.MinimapPosition.Inline }
                }
            };
        });
        aiDecoIds = ed.deltaDecorations(aiDecoIds, decorations);
        clearZones(ed);
        var removedGroups = [];
        var cur = null;
        diffRows.forEach(function(row) {
            if (row.type !== 'removed') {
                cur = null;
                return;
            }
            var afterLineNumber = Math.max(0, Number(row.afterLineNumber) || 0);
            if (!cur || cur.afterLineNumber !== afterLineNumber) {
                cur = { afterLineNumber: afterLineNumber, lines: [] };
                removedGroups.push(cur);
            }
            cur.lines.push(row.text);
        });
        if (removedGroups.length) {
            ed.changeViewZones(function(accessor) {
                removedGroups.forEach(function(group) {
                    aiZoneIds.push(accessor.addZone({
                        afterLineNumber: group.afterLineNumber,
                        heightInLines: Math.max(1, group.lines.length),
                        domNode: createRemovedZoneNode(group.lines)
                    }));
                });
            });
        }
        if (ranges.length > 0) ed.revealLineInCenter(ranges[0].startLineNumber);
        return ranges;
    }
    function reply(ev, msg) {
        try {
            if (ev.source) ev.source.postMessage(msg, '*');
        } catch (e) {}
    }
    function undoRedoState(ed) {
        var model = ed && ed.getModel && ed.getModel();
        return {
            canUndo: !!(model && model.canUndo && model.canUndo()),
            canRedo: !!(model && model.canRedo && model.canRedo())
        };
    }
    function notifyParentUndoRedo(ed) {
        var state = undoRedoState(ed);
        try {
            if (window.parent && window.parent !== window) {
                window.parent.postMessage({ source: 'cloudIframeMonacoAi', type: 'undoRedoState', canUndo: state.canUndo, canRedo: state.canRedo }, '*');
            }
        } catch (e) {}
        if (window.cloudMonacoShared && typeof window.cloudMonacoShared.notifyUndoRedo === 'function') {
            window.cloudMonacoShared.notifyUndoRedo(ed);
        }
    }
    window.attachCloudIframeMonacoAi = function(editor) {
        ensureStyles();
        editorRef = editor;
        if (editor && !editor.__cloudAiUndoWatch) {
            editor.__cloudAiUndoWatch = true;
            editor.onDidChangeModelContent(function() { notifyParentUndoRedo(editor); });
            editor.onDidFocusEditorText(function() { notifyParentUndoRedo(editor); });
            notifyParentUndoRedo(editor);
        }
    };
    if (!window.__cloudIframeMonacoAiListener) {
        window.__cloudIframeMonacoAiListener = true;
        window.addEventListener('message', function(ev) {
            var d = ev.data;
            if (!d || d.source !== 'cloudParent' || d.type !== 'cloudIframeMonacoAi') return;
            var reqId = d.reqId;
            var ed = editorRef;
            function ok(r) { reply(ev, { source: 'cloudIframeMonacoAi', reqId: reqId, ok: true, result: r || {} }); }
            function fail(m) { reply(ev, { source: 'cloudIframeMonacoAi', reqId: reqId, ok: false, error: String(m || 'error') }); }
            if (!ed || typeof monaco === 'undefined') {
                fail('editor not ready');
                return;
            }
            try {
                if (d.action === 'getContent') {
                    ok({ content: ed.getValue() });
                    return;
                }
                if (d.action === 'undo') {
                    ed.trigger('parent-toolbar', 'undo', null);
                    notifyParentUndoRedo(ed);
                    ok(undoRedoState(ed));
                    return;
                }
                if (d.action === 'redo') {
                    ed.trigger('parent-toolbar', 'redo', null);
                    notifyParentUndoRedo(ed);
                    ok(undoRedoState(ed));
                    return;
                }
                if (d.action === 'getUndoRedoState') {
                    ok(undoRedoState(ed));
                    return;
                }
                if (d.action === 'applyPreview') {
                    var model = ed.getModel();
                    if (!model) {
                        fail('no model');
                        return;
                    }
                    ed.pushUndoStop();
                    ed.executeEdits('ai-agent-preview', [{ range: model.getFullModelRange(), text: String(d.newContent ?? ''), forceMoveMarkers: true }]);
                    ed.pushUndoStop();
                    ed.setPosition({ lineNumber: 1, column: 1 });
                    ed.revealLineInCenter(1);
                    notifyParentUndoRedo(ed);
                    ok({ applied: true });
                    return;
                }
                if (d.action === 'highlightPreview') {
                    ok({ ranges: highlightPreview(ed, d.oldText, d.newText) });
                    return;
                }
                if (d.action === 'clearHighlight') {
                    clearHighlight(ed);
                    ok({});
                    return;
                }
                if (d.action === 'cancelPreview') {
                    var model2 = ed.getModel();
                    if (model2) {
                        ed.pushUndoStop();
                        ed.executeEdits('ai-agent-cancel', [{ range: model2.getFullModelRange(), text: String(d.originalContent ?? ''), forceMoveMarkers: true }]);
                        ed.pushUndoStop();
                    }
                    clearHighlight(ed);
                    notifyParentUndoRedo(ed);
                    ok({});
                    return;
                }
                if (d.action === 'save') {
                    var fn = window.cloudIframeMonacoAiSave;
                    if (typeof fn !== 'function') {
                        fail('no save handler');
                        return;
                    }
                    var ret = fn();
                    (ret && typeof ret.then === 'function' ? ret : Promise.resolve()).then(function() { ok({ saved: true }); }).catch(function(e) { fail(e && e.message ? e.message : e); });
                    return;
                }
                if (d.action === 'compileLatex') {
                    var compileFn = window.cloudIframeMonacoAiCompile;
                    if (typeof compileFn !== 'function') {
                        fail('no compile handler');
                        return;
                    }
                    var compileRet = compileFn(!!d.savePdf);
                    (compileRet && typeof compileRet.then === 'function' ? compileRet : Promise.resolve(compileRet || {})).then(function(r) { ok(r || { compiled: true }); }).catch(function(e) { fail(e && e.message ? e.message : e); });
                    return;
                }
                fail('unknown action');
            } catch (e) {
                fail(e && e.message ? e.message : e);
            }
        });
    }
})();
