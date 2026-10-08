        let editor;
        const previewFrame = document.getElementById('previewFrame');
        const preview = document.getElementById('preview');
        const clearBtn = document.getElementById('clearBtn');
        const saveBtn = document.getElementById('saveBtn');
        const fullscreenBtn = document.getElementById('fullscreenBtn');
        const refreshPreviewBtn = document.getElementById('refreshPreviewBtn');
        const exitFullscreenBtn = document.getElementById('exitFullscreenBtn');
        const fullscreenBtnMobile = document.getElementById('fullscreenBtnMobile');
        const refreshPreviewBtnMobile = document.getElementById('refreshPreviewBtnMobile');
        const exitFullscreenBtnMobile = document.getElementById('exitFullscreenBtnMobile');
        const mobilePreviewActions = document.getElementById('mobilePreviewActions');
        const fullscreenHeader = document.querySelector('.fullscreen-header');
        const fullscreenExitBtn = document.getElementById('fullscreenExitBtn');
        const fullscreenRefreshBtn = document.getElementById('fullscreenRefreshBtn');
        const confirmClearModal = document.getElementById('confirmClearModal');
        const cancelClearBtn = document.getElementById('cancelClearBtn');
        const confirmClearBtn = document.getElementById('confirmClearBtn');
        const undoBtn = document.getElementById('undoBtn');
        const redoBtn = document.getElementById('redoBtn');
        const urlParams = new URLSearchParams(window.location.search);
        const filePath = urlParams.get('path') || '';
        const user = urlParams.get('user') || 'default';
        const lang = urlParams.get('lang') || 'en';
        const root = urlParams.get('root') || 'main';
        const theme = urlParams.get('theme') || 'dark';
        const color = urlParams.get('color') || '#569cd6';
        let autoSaveTimer = null;
        const AUTO_SAVE_DELAY = 10000;
        let hasUnsavedChanges = false;
        let isInitialLoad = true;
        let isFullscreen = false;
        let previewTimer = null;
        const translations = {
            en: { saveTooltip: "Save (Ctrl+S)", clearTooltip: "Clear all content", fullscreenTooltip: "Fullscreen preview", exitFullscreenTooltip: "Exit fullscreen", refreshPreviewTooltip: "Refresh preview", preview: "Preview", undoTooltip: "Undo (Ctrl+Z)", redoTooltip: "Redo (Ctrl+Y)", confirmClearTitle: "Confirm Clear", confirmClearText: "Are you sure you want to clear all content? This action cannot be undone.", cancel: "Cancel", clear: "Clear", saving: "Saving...", saved: "Saved successfully", saveError: "Error saving file", fileSaved: "File saved successfully", noChanges: "No changes to save", unsavedChanges: "Unsaved changes", previewRefreshed: "Preview refreshed" },
            ru: { saveTooltip: "Сохранить (Ctrl+S)", clearTooltip: "Очистить всё содержимое", fullscreenTooltip: "Полноэкранный предпросмотр", exitFullscreenTooltip: "Выйти из полноэкранного режима", refreshPreviewTooltip: "Обновить предпросмотр", preview: "Предпросмотр", undoTooltip: "Отменить (Ctrl+Z)", redoTooltip: "Повторить (Ctrl+Y)", confirmClearTitle: "Подтверждение очистки", confirmClearText: "Вы уверены, что хотите очистить всё содержимое? Это действие нельзя отменить.", cancel: "Отмена", clear: "Очистить", saving: "Сохранение...", saved: "Успешно сохранено", saveError: "Ошибка сохранения файла", fileSaved: "Файл успешно сохранён", noChanges: "Нет изменений для сохранения", unsavedChanges: "Несохранённые изменения", previewRefreshed: "Предпросмотр обновлён" },
            uk: { saveTooltip: "Зберегти (Ctrl+S)", clearTooltip: "Очистити весь вміст", fullscreenTooltip: "Повноекранний перегляд", exitFullscreenTooltip: "Вийти з повноекранного режиму", refreshPreviewTooltip: "Оновити перегляд", preview: "Перегляд", undoTooltip: "Скасувати (Ctrl+Z)", redoTooltip: "Повторити (Ctrl+Y)", confirmClearTitle: "Підтвердження очищення", confirmClearText: "Ви впевнені, що хочете очистити весь вміст? Цю дію не можна скасувати.", cancel: "Скасувати", clear: "Очистити", saving: "Збереження...", saved: "Успішно збережено", saveError: "Помилка збереження файлу", fileSaved: "Файл успішно збережено", noChanges: "Немає змін для збереження", unsavedChanges: "Незбережені зміни", previewRefreshed: "Перегляд оновлено" },
            be: { saveTooltip: "Захаваць (Ctrl+S)", clearTooltip: "Ачысціць увесь змест", fullscreenTooltip: "Поўнаэкранны прагляд", exitFullscreenTooltip: "Выйсці з поўнаэкраннага рэжыму", refreshPreviewTooltip: "Абнавіць прагляд", preview: "Прагляд", undoTooltip: "Адмяніць (Ctrl+Z)", redoTooltip: "Паўтарыць (Ctrl+Y)", confirmClearTitle: "Пацверджанне ачысткі", confirmClearText: "Вы ўпэўнены, што хочаце ачысціць увесь змест? Гэта дзеянне нельга адмяніць.", cancel: "Адмяніць", clear: "Ачысціць", saving: "Захаванне...", saved: "Паспяхова захавана", saveError: "Памылка захавання файла", fileSaved: "Файл паспяхова захаваны", noChanges: "Няма зменаў для захавання", unsavedChanges: "Незахаваныя змены", previewRefreshed: "Прагляд абноўлены" }
        };
        function translatePage() {
            const currentLang = translations[lang] || translations.en;
            document.querySelectorAll('[data-i18n]').forEach(el => {
                const key = el.getAttribute('data-i18n');
                if (currentLang[key]) el.textContent = currentLang[key];
            });
            document.querySelectorAll('[data-i18n-title]').forEach(el => {
                const key = el.getAttribute('data-i18n-title');
                if (currentLang[key]) el.setAttribute('title', currentLang[key]);
            });
        }
        function showStatus(message, isError = false, options = {}) {
            const { localOnly = false } = options;
            const canNotifyParent = window.parent && window.parent !== window;
            if (!localOnly && canNotifyParent) {
                window.parent.postMessage({ type: 'htmlEditorStatus', message, isError, fromShowStatus: true }, '*');
                return;
            }
            const statusEl = document.getElementById('statusMessage');
            statusEl.textContent = message;
            statusEl.style.background = isError ? '#c42b1c' : color;
            statusEl.style.display = 'block';
            setTimeout(() => { statusEl.style.display = 'none'; }, 4000);
        }
        function normalizeHtmlForPreview(html) {
            const trimmed = (html || '').trim();
            if (!trimmed) return '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body></body></html>';
            if (/<html[\s>]/i.test(trimmed)) return trimmed;
            return '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body>' + trimmed + '</body></html>';
        }
        function updatePreview() {
            if (!editor || !previewFrame) return;
            previewFrame.srcdoc = normalizeHtmlForPreview(editor.getValue());
        }
        function schedulePreviewUpdate() {
            if (isResizing) return;
            if (previewTimer) clearTimeout(previewTimer);
            previewTimer = setTimeout(updatePreview, 120);
        }
        function refreshPreview() {
            updatePreview();
            showStatus((translations[lang] || translations.en).previewRefreshed, false, { localOnly: true });
        }
        async function saveFile() {
            if (!filePath || !editor) { showStatus('No file selected', true); return; }
            const content = editor.getValue();
            if (window.originalContent === content) {
                showStatus((translations[lang] || translations.en).noChanges, false);
                if (window.parent) window.parent.postMessage({ type: 'htmlEditorSaved', relativePath: filePath, success: true, message: 'No changes to save' }, '*');
                return;
            }
            try {
                showStatus((translations[lang] || translations.en).saving, false);
                const response = await fetch('<?= htmlspecialchars($apiUrl) ?>', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({ action: 'save_file', path: filePath, content: content, root: root })
                });
                if (!response.ok) throw new Error('HTTP error! status: ' + response.status);
                const data = await response.json();
                if (data.error) throw new Error(data.error);
                window.originalContent = content;
                hasUnsavedChanges = false;
                if (autoSaveTimer) { clearTimeout(autoSaveTimer); autoSaveTimer = null; }
                if (window.parent) {
                    window.parent.postMessage({ type: 'HTML_SAVED', path: filePath, timestamp: Date.now() }, '*');
                    window.parent.postMessage({ type: 'htmlEditorSaved', relativePath: filePath, success: true, message: 'File saved successfully' }, '*');
                }
            } catch (error) {
                showStatus(((translations[lang] || translations.en).saveError) + ': ' + error.message, true);
                throw error;
            }
        }
        function startAutoSaveTimer() {
            if (autoSaveTimer) clearTimeout(autoSaveTimer);
            autoSaveTimer = setTimeout(() => { if (hasUnsavedChanges) saveFile(); }, AUTO_SAVE_DELAY);
            if (window.parent && !hasUnsavedChanges) window.parent.postMessage({ type: 'htmlEditorStatus', message: (translations[lang] || translations.en).unsavedChanges, hasUnsavedChanges: true }, '*');
            hasUnsavedChanges = true;
        }
        function updateButtonStates() {
            if (!editor) return;
            const undoState = editor.getModel().canUndo();
            const redoState = editor.getModel().canRedo();
            undoBtn.classList.toggle('disabled', !undoState);
            undoBtn.disabled = !undoState;
            redoBtn.classList.toggle('disabled', !redoState);
            redoBtn.disabled = !redoState;
        }
        function toggleFullscreen() {
            isFullscreen = !isFullscreen;
            if (isFullscreen) {
                preview.classList.add('fullscreen');
                fullscreenHeader.style.display = 'flex';
                fullscreenBtn.style.display = 'none';
                exitFullscreenBtn.style.display = 'flex';
                fullscreenBtnMobile.style.display = 'none';
                exitFullscreenBtnMobile.style.display = 'flex';
            } else {
                preview.classList.remove('fullscreen');
                fullscreenHeader.style.display = 'none';
                fullscreenBtn.style.display = 'flex';
                exitFullscreenBtn.style.display = 'none';
                fullscreenBtnMobile.style.display = 'flex';
                exitFullscreenBtnMobile.style.display = 'none';
            }
        }
        undoBtn.addEventListener('click', () => { editor.trigger('keyboard', 'undo', null); setTimeout(() => { schedulePreviewUpdate(); updateButtonStates(); }, 10); });
        redoBtn.addEventListener('click', () => { editor.trigger('keyboard', 'redo', null); setTimeout(() => { schedulePreviewUpdate(); updateButtonStates(); }, 10); });
        fullscreenBtn.addEventListener('click', toggleFullscreen);
        exitFullscreenBtn.addEventListener('click', toggleFullscreen);
        refreshPreviewBtn.addEventListener('click', refreshPreview);
        fullscreenBtnMobile.addEventListener('click', toggleFullscreen);
        exitFullscreenBtnMobile.addEventListener('click', toggleFullscreen);
        refreshPreviewBtnMobile.addEventListener('click', refreshPreview);
        fullscreenExitBtn.addEventListener('click', toggleFullscreen);
        fullscreenRefreshBtn.addEventListener('click', refreshPreview);
        clearBtn.addEventListener('click', () => { confirmClearModal.style.display = 'flex'; });
        cancelClearBtn.addEventListener('click', () => { confirmClearModal.style.display = 'none'; });
        confirmClearBtn.addEventListener('click', () => { if (editor) { editor.setValue(''); updatePreview(); editor.focus(); } confirmClearModal.style.display = 'none'; updateButtonStates(); });
        saveBtn.addEventListener('click', saveFile);
        window.addEventListener('click', (e) => { if (e.target === confirmClearModal) confirmClearModal.style.display = 'none'; });
        const resizer = document.querySelector('.resizer');
        const editorPanel = document.querySelector('.editor-panel');
        const previewPanel = document.querySelector('.preview-panel');
        let isResizing = false;
        function expandPreviewPanel() {
            if (!editorPanel || !previewPanel) return;
            const containerWidth = editorPanel.parentElement?.offsetWidth || 0;
            if (containerWidth < 640) return;
            const minWidth = 300;
            const editorWidth = Math.max(minWidth, Math.min(380, Math.floor(containerWidth * 0.32)));
            if (containerWidth - editorWidth - (resizer?.offsetWidth || 0) >= minWidth) {
                editorPanel.style.flex = '0 0 ' + editorWidth + 'px';
                previewPanel.style.flex = '1';
                if (editor) editor.layout();
            }
        }
        function startPanelResize(clientX) {
            if (isResizing) return;
            isResizing = true;
            document.body.classList.add('is-resizing');
            const shield = document.createElement('div');
            shield.className = 'resize-shield';
            shield.setAttribute('aria-hidden', 'true');
            document.body.appendChild(shield);
            const startX = clientX;
            const startEditorWidth = editorPanel.offsetWidth;
            const minWidth = 300;
            function applyResize(ev) {
                const dx = ev.clientX - startX;
                const containerWidth = editorPanel.parentElement.offsetWidth;
                const newEditorWidth = startEditorWidth + dx;
                if (newEditorWidth >= minWidth && containerWidth - newEditorWidth - resizer.offsetWidth >= minWidth) {
                    editorPanel.style.flex = '0 0 ' + newEditorWidth + 'px';
                    previewPanel.style.flex = '1';
                }
            }
            function endResize() {
                if (!isResizing) return;
                isResizing = false;
                document.body.classList.remove('is-resizing');
                shield.remove();
                window.removeEventListener('mousemove', applyResize);
                window.removeEventListener('mouseup', endResize);
                window.removeEventListener('pointermove', applyResize);
                window.removeEventListener('pointerup', endResize);
                window.removeEventListener('pointercancel', endResize);
                if (editor) {
                    editor.layout();
                    editor.focus();
                }
            }
            window.addEventListener('mousemove', applyResize);
            window.addEventListener('mouseup', endResize);
            window.addEventListener('pointermove', applyResize);
            window.addEventListener('pointerup', endResize);
            window.addEventListener('pointercancel', endResize);
        }
        resizer.addEventListener('pointerdown', (e) => {
            if (e.button !== 0) return;
            e.preventDefault();
            e.stopPropagation();
            if (resizer.setPointerCapture) {
                try { resizer.setPointerCapture(e.pointerId); } catch (_) {}
            }
            startPanelResize(e.clientX);
        });
        function initMonaco() {
            // The Monaco loader, its localized built-in UI, the palette and the
            // editor options all come from assets/monaco-shared.js, the single module
            // shared with the main cloud editor.
            const shared = window.cloudMonacoShared;
            shared.loadMonaco({
                lang: lang,
                i18nUrl: '<?= htmlspecialchars(cloud_url('i18n/load.php')) ?>'
            }).then(function () {
                shared.defineAppThemes(monaco, { accent: color });
                editor = monaco.editor.create(document.getElementById('editor-container'), Object.assign({}, shared.standardEditorOptions(), {
                    value: <?php echo $fileContentForJS; ?>,
                    language: 'html',
                    theme: theme === 'light' ? 'custom-light' : 'custom-dark',
                    fontSize: 14,
                    fontFamily: 'Consolas, "Courier New", monospace',
                    lineHeight: 1.6,
                    minimap: { enabled: true, side: 'right', size: 'fit', showSlider: 'always', renderCharacters: false },
                    scrollbar: { vertical: 'hidden', horizontal: 'visible', verticalScrollbarSize: 0, horizontalScrollbarSize: 8 },
                    scrollBeyondLastLine: true,
                    wordWrap: 'on',
                    automaticLayout: true,
                    formatOnPaste: true,
                    formatOnType: true,
                    lineNumbers: 'on'
                }));
                // Same transliteration rules as the main editor.
                shared.installTransliteration({
                    editor: editor,
                    container: document.getElementById('editor-container'),
                    monaco: monaco,
                    getLang: function () { return lang; }
                });
                if (window.monacoTouchSelection) window.monacoTouchSelection.enable(editor, document.getElementById('editor-container'));
                // Realtime collaboration via the shared cloud-collab-ws tunnel
                if (window.cloudIframeCollab) {
                    window.cloudIframeCollab.enable({
                        editor: editor,
                        container: document.getElementById('editor-container'),
                        filePath: filePath,
                        root: root,
                        user: user,
                        userName: user,
                        getLocalContent: () => editor.getValue(),
                        onRemoteContent: (content) => {
                            if (typeof content !== 'string' || editor.getValue() === content) return;
                            const pos = editor.getPosition();
                            editor.setValue(content);
                            if (pos) { try { editor.setPosition(pos); } catch (_) {} }
                            schedulePreviewUpdate();
                            updateButtonStates();
                        }
                    });
                }
                editor.onDidChangeModelContent(() => {
                    schedulePreviewUpdate();
                    if (!isInitialLoad) startAutoSaveTimer();
                    updateButtonStates();
                });
                editor.onDidChangeCursorSelection(() => updateButtonStates());
                editor.onDidChangeModel(() => updateButtonStates());
                updatePreview();
                expandPreviewPanel();
                isInitialLoad = false;
                window.originalContent = editor.getValue();
                if (window.attachCloudIframeMonacoAi) window.attachCloudIframeMonacoAi(editor);
                window.cloudIframeMonacoAiSave = saveFile;
                editor.addCommand(monaco.KeyMod.CtrlCmd | monaco.KeyCode.KeyS, saveFile);
                updateButtonStates();
            }).catch(function (e) {
                console.error('Monaco failed to load:', e);
            });
        }
        document.addEventListener('visibilitychange', () => {
            if (document.hidden && hasUnsavedChanges && autoSaveTimer) { clearTimeout(autoSaveTimer); saveFile(); }
        });
        window.addEventListener('message', function(event) {
            if (event.data.type === 'loadHtmlContent' && event.data.content && editor) {
                editor.setValue(event.data.content);
                window.originalContent = event.data.content;
                updatePreview();
            }
        });
        window.addEventListener('load', () => {
            translatePage();
            initMonaco();
            const isMobile = window.innerWidth <= 768;
            if (isMobile) {
                document.querySelector('.preview-header').style.display = 'none';
                mobilePreviewActions.style.display = 'flex';
            }
        });
        window.addEventListener('resize', function() {
            if (editor) editor.layout();
            if (!isResizing && window.innerWidth > 768) expandPreviewPanel();
            const isMobile = window.innerWidth <= 768;
            if (isMobile) {
                document.querySelector('.preview-header').style.display = 'none';
                mobilePreviewActions.style.display = 'flex';
            } else {
                document.querySelector('.preview-header').style.display = 'flex';
                mobilePreviewActions.style.display = 'none';
            }
        });
        document.addEventListener('keydown', function(e) { if (e.key === 'Escape' && isFullscreen) toggleFullscreen(); });
