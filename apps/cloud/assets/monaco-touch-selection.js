// assets/monaco-touch-selection.js
// Shared touch selection with draggable handles for Monaco Editor.
// Call window.monacoTouchSelection.enable(editor, container) after editor creation.

(function () {
    // Only coarse pointers count so touch-capable laptops with a mouse do not
    // get the mobile-only selection handles on desktop.
    function detectTouchDevice() {
        try {
            if (window.matchMedia && window.matchMedia('(pointer: coarse)').matches) return true;
        } catch (_) {}
        if (!('ontouchstart' in window) && !(navigator.maxTouchPoints > 0)) return false;
        return window.innerWidth <= 768;
    }
    const isTouchDevice = detectTouchDevice();

    // Word-style handles: thin vertical line with a dot on top (start) and a
    // horizontal bar with a dot underneath (end), matching the reference design.
    let cssInjected = false;
    function injectCSS() {
        if (cssInjected) return;
        const style = document.createElement('style');
        style.textContent = `.monaco-touch-selection-handles{pointer-events:none!important}.word-handle{position:absolute!important;pointer-events:auto!important;touch-action:none!important;z-index:10000!important;opacity:0;transition:opacity .08s linear;will-change:top,left;cursor:pointer!important}.word-handle.top-line{width:1.5px!important;height:18px!important;background:#3b82f6!important;border-radius:1px!important;box-shadow:0 0 3px rgba(59,130,246,.6)!important}.word-handle.bottom-line{height:1.5px!important;width:6px!important;background:#3b82f6!important;border-radius:1px!important;box-shadow:0 0 3px rgba(59,130,246,.6)!important}.word-handle.top-line::before{content:''!important;position:absolute!important;top:-3px!important;left:50%!important;transform:translateX(-50%)!important;width:5px!important;height:5px!important;background:#3b82f6!important;border-radius:50%!important;box-shadow:0 0 4px rgba(59,130,246,.8)!important}.word-handle.bottom-line::after{content:''!important;position:absolute!important;bottom:-3px!important;left:50%!important;transform:translateX(-50%)!important;width:5px!important;height:5px!important;background:#3b82f6!important;border-radius:50%!important;box-shadow:0 0 4px rgba(59,130,246,.8)!important}.word-handle:active{filter:brightness(1.2)!important}`;
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

            const mk = (cls, key) => {
                const el = document.createElement('div');
                el.className = `word-handle ${cls}`;
                el.dataset.handle = key;
                el.style.display = 'none';
                selectionHandles.appendChild(el);
                return el;
            };
            // start = top line above selection start, end = bottom bar below selection end
            const startHandle = mk('top-line', 'start');
            const endHandle = mk('bottom-line', 'end');

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
            const sh = selectionHandles.querySelector('.word-handle.top-line');
            const eh = selectionHandles.querySelector('.word-handle.bottom-line');
            if (!sh || !eh) return;
            const sc = editor.getScrolledVisiblePosition({ lineNumber: sel.startLineNumber, column: sel.startColumn });
            const ec = editor.getScrolledVisiblePosition({ lineNumber: sel.endLineNumber, column: sel.endColumn });
            if (!sc || !ec) { hideHandles(); return; }
            const lh = editor.getOption(monaco.editor.EditorOption.lineHeight);
            // Top line sits above the selection start; bottom bar below the end.
            sh.style.left = (sc.left - 0.75) + 'px';
            sh.style.top = (sc.top - 2) + 'px';
            sh.style.display = 'block';
            sh.style.opacity = '0.95';
            eh.style.left = (ec.left - 3) + 'px';
            eh.style.top = (ec.top + lh - 1) + 'px';
            eh.style.display = 'block';
            eh.style.opacity = '0.95';
            // Very short single-line selection: spread the markers apart
            if (sel.startLineNumber === sel.endLineNumber && (ec.left - sc.left) < 4) {
                eh.style.left = (ec.left + 1) + 'px';
            }
        }

        function hideHandles() {
            if (!selectionHandles) return;
            selectionHandles.querySelectorAll('.word-handle').forEach(h => h.style.display = 'none');
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
