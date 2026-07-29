// assets/monaco-touch-selection.js
// Shared touch selection with draggable handles for Monaco Editor.
// Call window.monacoTouchSelection.enable(editor, container) after editor creation.

(function () {
    const isTouchDevice = ('ontouchstart' in window) || navigator.maxTouchPoints > 0;

    let cssInjected = false;
    function injectCSS() {
        if (cssInjected) return;
        const style = document.createElement('style');
        style.textContent = `.monaco-touch-selection-handles{pointer-events:none!important}.monaco-selection-handle{pointer-events:auto!important;touch-action:none!important;z-index:10000!important;width:12px!important;height:32px!important;background:#4A90D9!important;border:2px solid #fff!important;box-shadow:0 2px 6px rgba(0,0,0,0.35)!important;position:relative!important}.monaco-selection-handle-start{clip-path:path('M6 0 C6 0, 0 14, 0 20 C0 26.627, 2.686 32, 6 32 C9.314 32, 12 26.627, 12 20 C12 14, 6 0, 6 0 Z')!important;transform:translate(-50%,-30%)!important}.monaco-selection-handle-end{clip-path:path('M6 0 C6 0, 0 14, 0 20 C0 26.627, 2.686 32, 6 32 C9.314 32, 12 26.627, 12 20 C12 14, 6 0, 6 0 Z')!important;transform:translate(-50%,-20%)!important}.monaco-selection-handle::before{content:''!important;position:absolute!important;top:-15px!important;left:-15px!important;right:-15px!important;bottom:-15px!important;pointer-events:auto!important}.monaco-selection-handle:active{filter:brightness(1.2)}.monaco-selection-handle-start:active{transform:scale(1.25) translate(-50%,-30%)!important}.monaco-selection-handle-end:active{transform:scale(1.25) translate(-50%,-20%)!important}`;
        document.head.appendChild(style);
        cssInjected = true;
    }

    function enable(editor, container) {
        if (!isTouchDevice) return;
        injectCSS();

        let touchStart = null;
        let lastTapTime = 0;
        let selectionInProgress = false;
        let longPressTimer = null;
        let isScrolling = false;
        let isDraggingHandle = null;
        let selectionHandles = null;
        let autoScrollFrame = null;
        let autoScrollTouch = null;
        let autoScrollStartedAt = 0;
        const LONG_PRESS_DELAY = 400;
        const SCROLL_THRESHOLD = 15;
        const AUTO_SCROLL_ZONE = 72;
        const AUTO_SCROLL_MAX_PX = 16;
        const AUTO_SCROLL_ACCEL_MS = 1000;
        const AUTO_SCROLL_INITIAL_FACTOR = 1 / 3;

        function createSelectionHandles() {
            removeSelectionHandles();
            const editorDom = editor.getDomNode();
            if (!editorDom) return;
            if (!['relative','absolute','fixed'].includes(editorDom.style.position)) {
                editorDom.style.position = 'relative';
            }
            selectionHandles = document.createElement('div');
            selectionHandles.className = 'monaco-touch-selection-handles';
            selectionHandles.style.cssText = 'position:absolute;top:0;left:0;width:100%;height:100%;pointer-events:none;z-index:100;overflow:hidden;';

            const handleBase = `position:absolute;width:12px;height:32px;background:#4A90D9;border:2px solid #fff;box-shadow:0 2px 6px rgba(0,0,0,0.35);pointer-events:auto;touch-action:none;cursor:pointer;display:none;clip-path:path('M6 0 C6 0, 0 14, 0 20 C0 26.627, 2.686 32, 6 32 C9.314 32, 12 26.627, 12 20 C12 14, 6 0, 6 0 Z');position:relative;`;
            const startHandle = document.createElement('div');
            startHandle.className = 'monaco-selection-handle monaco-selection-handle-start';
            startHandle.style.cssText = handleBase + 'transform:translate(-50%,-30%);';
            startHandle.dataset.handle = 'start';
            const endHandle = document.createElement('div');
            endHandle.className = 'monaco-selection-handle monaco-selection-handle-end';
            endHandle.style.cssText = handleBase + 'transform:translate(-50%,-20%);';
            endHandle.dataset.handle = 'end';

            selectionHandles.appendChild(startHandle);
            selectionHandles.appendChild(endHandle);
            editorDom.appendChild(selectionHandles);

            [startHandle, endHandle].forEach(handle => {
                handle.addEventListener('touchstart', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    isDraggingHandle = handle.dataset.handle;
                    container.style.touchAction = 'none';
                }, { passive: false });

                handle.addEventListener('touchmove', (e) => {
                    if (isDraggingHandle !== handle.dataset.handle) return;
                    e.preventDefault();
                    e.stopPropagation();
                    const touch = e.touches[0];
                    try { setSelectionFromClientPoint(touch.clientX, touch.clientY); } catch (_) {}
                    updateTouchAutoScroll(touch);
                }, { passive: false });

                handle.addEventListener('touchend', (e) => {
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
            if (selectionHandles) { selectionHandles.remove(); selectionHandles = null; }
            isDraggingHandle = null;
            stopTouchAutoScroll();
        }

        function updateHandlePositions() {
            if (!selectionHandles) return;
            const sel = editor.getSelection();
            if (!sel || sel.isEmpty()) { hideHandles(); return; }
            const sh = selectionHandles.querySelector('.monaco-selection-handle-start');
            const eh = selectionHandles.querySelector('.monaco-selection-handle-end');
            if (!sh || !eh) return;
            const sc = editor.getScrolledVisiblePosition({ lineNumber: sel.startLineNumber, column: sel.startColumn });
            const ec = editor.getScrolledVisiblePosition({ lineNumber: sel.endLineNumber, column: sel.endColumn });
            if (!sc || !ec) { hideHandles(); return; }
            const lh = editor.getOption(monaco.editor.EditorOption.lineHeight);
            sh.style.left = sc.left + 'px';
            sh.style.top = (sc.top + (lh / 2)) + 'px';
            sh.style.display = 'block';
            eh.style.left = ec.left + 'px';
            eh.style.top = (ec.top + (lh / 2) - (lh / 2)) + 'px';
            eh.style.display = 'block';
        }

        function hideHandles() {
            if (!selectionHandles) return;
            selectionHandles.querySelectorAll('.monaco-selection-handle').forEach(h => h.style.display = 'none');
        }

        function setSelectionFromClientPoint(clientX, clientY) {
            const target = editor.getTargetAtClientPoint(clientX, clientY);
            if (!target || !target.position) return false;
            const model = editor.getModel();
            if (!model) return false;
            const lineNumber = Math.max(1, Math.min(target.position.lineNumber, model.getLineCount()));
            const column = Math.max(1, Math.min(target.position.column, model.getLineMaxColumn(lineNumber)));
            if (isDraggingHandle) {
                const sel = editor.getSelection();
                if (!sel) return false;
                const range = isDraggingHandle === 'start'
                    ? new monaco.Range(lineNumber, column, sel.endLineNumber, sel.endColumn)
                    : new monaco.Range(sel.startLineNumber, sel.startColumn, lineNumber, column);
                if (range.isEmpty()) return false;
                editor.setSelection(range);
            } else {
                if (!touchStart || !touchStart.position) return false;
                editor.setSelection(new monaco.Range(touchStart.position.lineNumber, touchStart.position.column, lineNumber, column));
            }
            updateHandlePositions();
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

        container.style.touchAction = 'pan-x pan-y';
        container.style.webkitUserSelect = 'text';
        container.style.userSelect = 'text';

        container.addEventListener('touchstart', (e) => {
            if (isDraggingHandle) return;
            const touch = e.touches[0];
            const hit = editor.getTargetAtClientPoint(touch.clientX, touch.clientY);
            touchStart = { x: touch.clientX, y: touch.clientY, time: Date.now(), position: hit ? hit.position : null };
            isScrolling = false;
            if (longPressTimer) { clearTimeout(longPressTimer); longPressTimer = null; }
            if (e.touches.length === 1) {
                const curSel = editor.getSelection();
                if ((!curSel || curSel.isEmpty()) && hit && hit.position) editor.setPosition(hit.position);
            }
            longPressTimer = setTimeout(() => {
                if (touchStart && !isScrolling && touchStart.position) {
                    selectionInProgress = true;
                    container.style.touchAction = 'none';
                    const word = editor.getModel().getWordAtPosition(touchStart.position);
                    if (word) {
                        const range = new monaco.Range(touchStart.position.lineNumber, word.startColumn, touchStart.position.lineNumber, word.endColumn);
                        try { editor.setSelection(range); } catch (_) {}
                        editor.revealRange(range);
                        createSelectionHandles();
                        setTimeout(updateHandlePositions, 10);
                    }
                }
                longPressTimer = null;
            }, LONG_PRESS_DELAY);
        }, { passive: true });

        container.addEventListener('touchmove', (e) => {
            if (!touchStart || e.touches.length !== 1) return;
            const touch = e.touches[0];
            const moveX = Math.abs(touch.clientX - touchStart.x);
            const moveY = Math.abs(touch.clientY - touchStart.y);
            if (!selectionInProgress && longPressTimer && (moveX > SCROLL_THRESHOLD || moveY > SCROLL_THRESHOLD)) {
                clearTimeout(longPressTimer); longPressTimer = null; isScrolling = true; return;
            }
            if (!selectionInProgress) return;
            e.preventDefault();
            try { setSelectionFromClientPoint(touch.clientX, touch.clientY); } catch (_) {}
            updateTouchAutoScroll(touch);
        }, { passive: false });

        container.addEventListener('touchend', (e) => {
            if (!touchStart) return;
            const now = Date.now();
            const duration = now - touchStart.time;
            if (longPressTimer) { clearTimeout(longPressTimer); longPressTimer = null; }

            if (!isScrolling && duration < 250 && (now - lastTapTime) < 350) {
                e.preventDefault();
                const touch = e.changedTouches[0];
                const hit = editor.getTargetAtClientPoint(touch.clientX, touch.clientY);
                if (hit && hit.position) {
                    const word = editor.getModel().getWordAtPosition(hit.position);
                    if (word) {
                        const range = new monaco.Range(hit.position.lineNumber, word.startColumn, hit.position.lineNumber, word.endColumn);
                        try { editor.setSelection(range); } catch (_) {}
                        editor.revealRange(range);
                        createSelectionHandles();
                        setTimeout(updateHandlePositions, 10);
                    }
                }
            } else if (!isScrolling && duration < 200 && !selectionInProgress) {
                const touch = e.changedTouches[0];
                const hit = editor.getTargetAtClientPoint(touch.clientX, touch.clientY);
                if (hit && hit.position) {
                    const curSel = editor.getSelection();
                    if (!curSel || curSel.isEmpty()) { editor.setPosition(hit.position); hideHandles(); }
                }
            }

            if (selectionInProgress) { selectionInProgress = false; container.style.touchAction = 'pan-x pan-y'; }
            lastTapTime = now;
            touchStart = null;
            isScrolling = false;
            stopTouchAutoScroll();
        }, { passive: false });

        editor.onDidScrollChange(updateHandlePositions);
        editor.onDidChangeCursorPosition(updateHandlePositions);
        editor.onDidChangeCursorSelection((ev) => {
            if (ev.selection && !ev.selection.isEmpty()) {
                if (!selectionHandles) createSelectionHandles();
                setTimeout(updateHandlePositions, 0);
            } else {
                hideHandles();
            }
        });
        document.addEventListener('touchstart', (e) => {
            if (!container.contains(e.target)) removeSelectionHandles();
        });
    }

    window.monacoTouchSelection = { enable, isTouch: () => isTouchDevice };
})();
