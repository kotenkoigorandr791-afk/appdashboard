        let editor;
        const previewContent = document.querySelector('.preview-content');
        const preview = document.getElementById('preview');
        const clearBtn = document.getElementById('clearBtn');
        const saveBtn = document.getElementById('saveBtn');
        const exportPdfBtn = document.getElementById('exportPdfBtn');
        const fullscreenBtn = document.getElementById('fullscreenBtn');
        const refreshPreviewBtn = document.getElementById('refreshPreviewBtn');
        const exitFullscreenBtn = document.getElementById('exitFullscreenBtn');
        const fullscreenBtnMobile = document.getElementById('fullscreenBtnMobile');
        const refreshPreviewBtnMobile = document.getElementById('refreshPreviewBtnMobile');
        const exitFullscreenBtnMobile = document.getElementById('exitFullscreenBtnMobile');
        const mobilePreviewActions = document.getElementById('mobilePreviewActions');
        const desktopPreviewActions = document.getElementById('desktopPreviewActions');
        const fullscreenHeader = document.querySelector('.fullscreen-header');
        const fullscreenExitBtn = document.getElementById('fullscreenExitBtn');
        const fullscreenRefreshBtn = document.getElementById('fullscreenRefreshBtn');
        const translateBtnToolbar = document.getElementById('translateBtnToolbar');
        const translationModal = document.getElementById('translationModal');
        const translationSettingsModal = document.getElementById('translationSettingsModal');
        const sourceLang = document.getElementById('sourceLang');
        const targetLang = document.getElementById('targetLang');
        const sourceText = document.getElementById('sourceText');
        const targetText = document.getElementById('targetText');
        const translateBtn = document.getElementById('translateBtn');
        const translateAgainBtn = document.getElementById('translateAgainBtn');
        const replaceTextBtn = document.getElementById('replaceTextBtn');
        const urlParams = new URLSearchParams(window.location.search);
        const filePath = urlParams.get('path') || '';
        const user = urlParams.get('user') || 'default';
        const lang = (urlParams.get('lang') || 'en').toLowerCase().split(/[-_]/)[0];
        const root = urlParams.get('root') || 'main';
        const theme = <?php echo json_encode($theme, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE); ?>;
        const color = urlParams.get('color') || '#569cd6';
        let autoSaveTimer = null;
        const AUTO_SAVE_DELAY = 10000;
        let hasUnsavedChanges = false;
        let isInitialLoad = true;
        let isFullscreen = false;
        let currentSelectedText = '';
        let lastTranslationResult = '';
        let lastTranslationParams = { sourceLang: 'auto', targetLang: 'ru' };
        let formattingButtons = [];
        const translations = {
            en: {
                saveTooltip: "Save (Ctrl+S)",clearTooltip: "Clear all content",exportPdfTooltip: "Export to PDF",fullscreenTooltip: "Fullscreen preview",exitFullscreenTooltip: "Exit fullscreen",refreshPreviewTooltip: "Refresh preview",preview: "Preview",translateTooltip: "Translate selected text",translationWindow: "Translation",sourceLang: "Source language",targetLang: "Target language",autoDetect: "Auto-detect",translate: "Text to translate",translation: "Translation",translatePlaceholder: "Enter text to translate...",translationPlaceholder: "Translation will appear here...",translateAgain: "Translate again",translateButton: "Translate",replaceText: "Replace text",translationSettings: "Translation Settings",apiUrl: "API URL",apiUrlHelp: "Enter your OpenAI-compatible API URL",model: "Model",loadModels: "Load models",enterApiUrl: "http://localhost:1234/v1",saveSettings: "Save settings",cancel: "Cancel",translating: "Translating...",boldTooltip: "Bold (Ctrl+B)",italicTooltip: "Italic (Ctrl+I)",underlineTooltip: "Underline (Ctrl+U)",strikeTooltip: "Strikethrough",headingTooltip: "Heading",normalText: "Normal text",heading1: "Heading 1",heading2: "Heading 2",heading3: "Heading 3",heading4: "Heading 4",bulletListTooltip: "Bulleted list",numberedListTooltip: "Numbered list",taskListTooltip: "Task list",linkTooltip: "Insert link",codeTooltip: "Insert code",quoteTooltip: "Insert quote",tableTooltip: "Insert table",hrTooltip: "Horizontal line",mathTooltip: "Insert math formula",undoTooltip: "Undo (Ctrl+Z)",redoTooltip: "Redo (Ctrl+Y)",confirmClearTitle: "Confirm Clear",confirmClearText: "Are you sure you want to clear all content? This action cannot be undone.",clear: "Clear",insert: "Insert",insertTableTitle: "Insert Table",rows: "Rows",columns: "Columns",insertMathTitle: "Insert Math Formula",mathExamples: "Examples: \\(E = mc^2\\), $$\\int_a^b f(x)dx$$",mathPlaceholder: "Enter LaTeX formula here...",exportPdfTitle: "Export to PDF",exportPdfText: "The PDF will be generated using pandoc and saved in the same folder.",fontFamily: "Font family",paperSize: "Paper size",generatePdf: "Generate PDF",saving: "Saving...",saved: "Saved successfully",saveError: "Error saving file",fileSaved: "File saved successfully",generatingPdf: "Generating PDF...",pdfSuccess: "PDF generated successfully!",pdfError: "Error generating PDF",pdfNoPath: "No file selected",pdfCommand: "Executing command...",pdfNotFound: "pandoc not found. Please install pandoc on the server.",pdfFontError: "Font error. Trying default font...",noChanges: "No changes to save",unsavedChanges: "Unsaved changes"
            },
            ru: {
                saveTooltip: "Сохранить (Ctrl+S)",clearTooltip: "Очистить всё содержимое",exportPdfTooltip: "Экспорт в PDF",fullscreenTooltip: "Полноэкранный предпросмотр",exitFullscreenTooltip: "Выйти из полноэкранного режима",refreshPreviewTooltip: "Обновить предпросмотр",preview: "Предпросмотр",translateTooltip: "Перевести выделенный текст",translationWindow: "Перевод",sourceLang: "Исходный язык",targetLang: "Язык перевода",autoDetect: "Автоопределение",translate: "Текст для перевода",translation: "Перевод",translatePlaceholder: "Введите текст для перевода...",translationPlaceholder: "Перевод появится здесь...",translateAgain: "Перевести снова",translateButton: "Перевести",replaceText: "Заменить текст",translationSettings: "Настройки перевода",apiUrl: "URL API",apiUrlHelp: "Введите URL API, совместимого с OpenAI",model: "Модель",loadModels: "Загрузить модели",enterApiUrl: "http://localhost:1234/v1",saveSettings: "Сохранить настройки",cancel: "Отмена",translating: "Перевод...",boldTooltip: "Жирный (Ctrl+B)",italicTooltip: "Курсив (Ctrl+I)",underlineTooltip: "Подчёркнутый (Ctrl+U)",strikeTooltip: "Зачёркнутый",headingTooltip: "Заголовок",normalText: "Обычный текст",heading1: "Заголовок 1",heading2: "Заголовок 2",heading3: "Заголовок 3",heading4: "Заголовок 4",bulletListTooltip: "Маркированный список",numberedListTooltip: "Нумерованный список",taskListTooltip: "Список задач",linkTooltip: "Вставить ссылку",codeTooltip: "Вставить код",quoteTooltip: "Вставить цитату",tableTooltip: "Вставить таблицу",hrTooltip: "Горизонтальная линия",mathTooltip: "Вставить математическую формулу",undoTooltip: "Отменить (Ctrl+Z)",redoTooltip: "Повторить (Ctrl+Y)",confirmClearTitle: "Подтверждение очистки",confirmClearText: "Вы уверены, что хотите очистить всё содержимое? Это действие нельзя отменить.",clear: "Очистить",insert: "Вставить",insertTableTitle: "Вставить таблицу",rows: "Строки",columns: "Колонки",insertMathTitle: "Вставить математическую формулу",mathExamples: "Примеры: \\(E = mc^2\\), $$\\int_a^b f(x)dx$$",mathPlaceholder: "Введите формулу LaTeX здесь...",exportPdfTitle: "Экспорт в PDF",exportPdfText: "PDF будет сгенерирован с помощью pandoc и сохранён в той же папке.",fontFamily: "Семейство шрифтов",paperSize: "Размер бумаги",generatePdf: "Сгенерировать PDF",saving: "Сохранение...",saved: "Успешно сохранено",saveError: "Ошибка сохранения файла",fileSaved: "Файл успешно сохранён",generatingPdf: "Генерация PDF...",pdfSuccess: "PDF успешно сгенерирован!",pdfError: "Ошибка генерации PDF",pdfNoPath: "Файл не выбран",pdfCommand: "Выполнение команды...",pdfNotFound: "pandoc не найден. Установите pandoc на сервере.",pdfFontError: "Ошибка шрифта. Пробуем шрифт по умолчанию...",noChanges: "Нет изменений для сохранения",unsavedChanges: "Несохранённые изменения"
            },
            uk: {
                saveTooltip: "Зберегти (Ctrl+S)",clearTooltip: "Очистити весь вміст",exportPdfTooltip: "Експорт в PDF",fullscreenTooltip: "Повноекранний перегляд",exitFullscreenTooltip: "Вийти з повноекранного режиму",refreshPreviewTooltip: "Оновити перегляд",preview: "Перегляд",translateTooltip: "Перекласти виділений текст",translationWindow: "Переклад",sourceLang: "Мова оригіналу",targetLang: "Мова перекладу",autoDetect: "Автовизначення",translate: "Текст для перекладу",translation: "Переклад",translatePlaceholder: "Введіть текст для перекладу...",translationPlaceholder: "Переклад з'явиться тут...",translateAgain: "Перекласти знову",translateButton: "Перекласти",replaceText: "Замінити текст",translationSettings: "Налаштування перекладу",apiUrl: "URL API",apiUrlHelp: "Введіть URL API, сумісного з OpenAI",model: "Модель",loadModels: "Завантажити моделі",enterApiUrl: "http://localhost:1234/v1",saveSettings: "Зберегти налаштування",cancel: "Скасувати",translating: "Переклад...",boldTooltip: "Жирний (Ctrl+B)",italicTooltip: "Курсив (Ctrl+I)",underlineTooltip: "Підкреслений (Ctrl+U)",strikeTooltip: "Закреслений",headingTooltip: "Заголовок",normalText: "Звичайний текст",heading1: "Заголовок 1",heading2: "Заголовок 2",heading3: "Заголовок 3",heading4: "Заголовок 4",bulletListTooltip: "Маркований список",numberedListTooltip: "Нумерований список",taskListTooltip: "Список завдань",linkTooltip: "Вставити посилання",codeTooltip: "Вставити код",quoteTooltip: "Вставити цитату",tableTooltip: "Вставити таблицю",hrTooltip: "Горизонтальна лінія",mathTooltip: "Вставити математичну формулу",undoTooltip: "Скасувати (Ctrl+Z)",redoTooltip: "Повторити (Ctrl+Y)",confirmClearTitle: "Підтвердження очищення",confirmClearText: "Ви впевнені, що хочете очистити весь вміст? Цю дію не можна скасувати.",clear: "Очистити",insert: "Вставити",insertTableTitle: "Вставити таблицю",rows: "Рядки",columns: "Стовпці",insertMathTitle: "Вставити математичну формулу",mathExamples: "Приклади: \\(E = mc^2\\), $$\\int_a^b f(x)dx$$",mathPlaceholder: "Введіть формулу LaTeX тут...",exportPdfTitle: "Експорт в PDF",exportPdfText: "PDF буде згенеровано за допомогою pandoc та збережено в тій же папці.",fontFamily: "Сімейство шрифтів",paperSize: "Розмір паперу",generatePdf: "Згенерувати PDF",saving: "Збереження...",saved: "Успішно збережено",saveError: "Помилка збереження файлу",fileSaved: "Файл успішно збережено",generatingPdf: "Генерація PDF...",pdfSuccess: "PDF успішно згенеровано!",pdfError: "Помилка генерації PDF",pdfNoPath: "Файл не вибрано",pdfCommand: "Виконання команди...",pdfNotFound: "pandoc не знайдено. Встановіть pandoc на сервері.",pdfFontError: "Помилка шрифту. Пробуємо шрифт за замовчуванням...",noChanges: "Немає змін для збереження",unsavedChanges: "Незбережені зміни"
            },
            be: {
                saveTooltip: "Захаваць (Ctrl+S)",clearTooltip: "Ачысціць увесь змест",exportPdfTooltip: "Экспарт у PDF",fullscreenTooltip: "Поўнаэкранны прагляд",exitFullscreenTooltip: "Выйсці з поўнаэкраннага рэжыму",refreshPreviewTooltip: "Абнавіць прагляд",preview: "Прагляд",translateTooltip: "Перакласці вылучаны тэкст",translationWindow: "Пераклад",sourceLang: "Мова арыгіналу",targetLang: "Мова перакладу",autoDetect: "Аўтавызначэнне",translate: "Тэкст для перакладу",translation: "Пераклад",translatePlaceholder: "Увядзіце тэкст для перакладу...",translationPlaceholder: "Пераклад з'явіцца тут...",translateAgain: "Перакласці зноў",translateButton: "Перакласці",replaceText: "Замяніць тэкст",translationSettings: "Налады перакладу",apiUrl: "URL API",apiUrlHelp: "Увядзіце URL API, сумяшчальнага з OpenAI",model: "Мадэль",loadModels: "Загрузіць мадэлі",enterApiUrl: "http://localhost:1234/v1",saveSettings: "Захаваць налады",cancel: "Адмяніць",translating: "Пераклад...",boldTooltip: "Тоўсты (Ctrl+B)",italicTooltip: "Курсіў (Ctrl+I)",underlineTooltip: "Падкрэслены (Ctrl+U)",strikeTooltip: "Закрэслены",headingTooltip: "Загаловак",normalText: "Звычайны тэкст",heading1: "Загаловак 1",heading2: "Загаловак 2",heading3: "Загаловак 3",heading4: "Загаловак 4",bulletListTooltip: "Маркіраваны спіс",numberedListTooltip: "Нумараваны спіс",taskListTooltip: "Спіс задач",linkTooltip: "Уставіць спасылку",codeTooltip: "Уставіць код",quoteTooltip: "Уставіць цытату",tableTooltip: "Уставіць табліцу",hrTooltip: "Гарызантальная лінія",mathTooltip: "Уставіць матэматычную формулу",undoTooltip: "Адмяніць (Ctrl+Z)",redoTooltip: "Паўтарыць (Ctrl+Y)",confirmClearTitle: "Пацверджанне ачысткі",confirmClearText: "Вы ўпэўнены, што хочаце ачысціць увесь змест? Гэта дзеянне нельга адмяніць.",clear: "Ачысціць",insert: "Уставіць",insertTableTitle: "Уставіць табліцу",rows: "Радкі",columns: "Слупкі",insertMathTitle: "Уставіць матэматычную формулу",mathExamples: "Прыклады: \\(E = mc^2\\), $$\\int_a^b f(x)dx$$",mathPlaceholder: "Увядзіце формулу LaTeX тут...",exportPdfTitle: "Экспарт у PDF",exportPdfText: "PDF будзе згенераваны з дапамогай pandoc і захаваны ў той жа папцы.",fontFamily: "Сямейства шрыфтоў",paperSize: "Памер паперы",generatePdf: "Згенераваць PDF",saving: "Захаванне...",saved: "Паспяхова захавана",saveError: "Памылка захавання файла",fileSaved: "Файл паспяхова захаваны",generatingPdf: "Генерацыя PDF...",pdfSuccess: "PDF паспяхова згенераваны!",pdfError: "Памылка генерацыі PDF",pdfNoPath: "Файл не абраны",pdfCommand: "Выкананне каманды...",pdfNotFound: "pandoc не знойдзены. Усталюйце pandoc на серверы.",pdfFontError: "Памылка шрыфту. Прабуем шрыфт па змаўчанні...",noChanges: "Няма зменаў для захавання",unsavedChanges: "Незахаваныя змены"
            }
        };
        function translatePage() {
            const currentLang = Object.assign({}, translations['en'], translations[lang] || {});
            if (window.parent && window.parent !== window && window.parent.i18n && window.parent.i18n.normal) {
                currentLang.normalText = window.parent.i18n.normal;
            }
            document.querySelectorAll('[data-i18n]').forEach(element => {
                const key = element.getAttribute('data-i18n');
                if (currentLang[key]) element.textContent = currentLang[key];
            });
            document.querySelectorAll('[data-i18n-title]').forEach(element => {
                const key = element.getAttribute('data-i18n-title');
                if (currentLang[key]) element.setAttribute('title', currentLang[key]);
            });
            document.querySelectorAll('[data-i18n-placeholder]').forEach(element => {
                const key = element.getAttribute('data-i18n-placeholder');
                if (currentLang[key]) element.setAttribute('placeholder', currentLang[key]);
            });
            document.querySelectorAll('option[data-i18n]').forEach(option => {
                const key = option.getAttribute('data-i18n');
                if (currentLang[key]) option.textContent = currentLang[key];
            });
            if (window.currentStatus) showStatus(currentLang[window.currentStatus.key] || window.currentStatus.message, window.currentStatus.isError);
        }
        function showStatus(message, isError = false, options = {}) {
            const { localOnly = false } = options;
            const currentLang = translations[lang] || translations['en'];
            window.currentStatus = {
                message: message,
                isError: isError,
                key: Object.keys(currentLang).find(key => currentLang[key] === message)
            };

            const canNotifyParent = window.parent && window.parent !== window;
            if (!localOnly && canNotifyParent) {
                window.parent.postMessage({
                    type: 'markdownEditorStatus',
                    message,
                    isError,
                    fromShowStatus: true
                }, '*');
                return;
            }

            const statusEl = document.getElementById('statusMessage');
            statusEl.textContent = message;
            statusEl.style.background = isError ? '#c42b1c' : color;
            statusEl.style.display = 'block';
            setTimeout(() => { statusEl.style.display = 'none'; }, 4000);
        }
        async function saveFile(notifyParent = true) {
            if (!filePath || !editor) { showStatus(translations[lang]?.pdfNoPath || 'No file selected', true); return; }
            const content = editor.getValue();
            if (window.originalContent === content) {
                showStatus(translations[lang]?.noChanges || 'No changes to save', false);
                if (window.parent) window.parent.postMessage({ type: 'markdownEditorSaved', relativePath: filePath, success: true, message: 'No changes to save' }, '*');
                if (window.parent && notifyParent) window.parent.postMessage({ type: 'editorSaveResult', ok: true }, '*');
                return;
            }
            try {
                showStatus(translations[lang]?.saving || 'Saving...', false);
                const response = await fetch('<?= htmlspecialchars($apiUrl) ?>', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({ action: 'save_file', path: filePath, content: content, root: root })
                });
                if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                const data = await response.json();
                if (data.error) throw new Error(data.error);
                window.originalContent = content;
                hasUnsavedChanges = false;
                if (autoSaveTimer) { clearTimeout(autoSaveTimer); autoSaveTimer = null; }
                
                if (window.parent) {
                    window.parent.postMessage({ 
                        type: 'MARKDOWN_SAVED', 
                        path: filePath,
                        timestamp: Date.now()
                    }, '*');
                }
                
                if (window.parent) window.parent.postMessage({ type: 'markdownEditorSaved', relativePath: filePath, success: true, message: 'File saved successfully' }, '*');
                if (window.parent && notifyParent) window.parent.postMessage({ type: 'editorSaveResult', ok: true }, '*');
            } catch (error) {
                showStatus((translations[lang]?.saveError || 'Save error') + ': ' + error.message, true);
                if (window.parent && notifyParent) window.parent.postMessage({ type: 'editorSaveResult', ok: false, message: error.message }, '*');
                throw error;
            }
        }
        async function exportToPDF() {
            if (!filePath) { showStatus(translations[lang]?.pdfNoPath || 'No file selected', true, { localOnly: true }); return; }
            const fontFamily = document.getElementById('pdfFont').value;
            const paperSize = document.getElementById('paperSize').value;
            const pdfStatus = document.getElementById('pdfStatus');
            await saveFile();
            pdfStatus.textContent = translations[lang]?.generatingPdf || 'Generating PDF...';
            pdfStatus.style.color = color;
            try {
                const response = await fetch('<?= htmlspecialchars($apiUrl) ?>', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({ action: 'export_pdf', path: filePath, user: user, root: root, font: fontFamily, paper: paperSize })
                });
                
                if (!response.ok) {
                    const errorText = await response.text();
                    throw new Error(`Server error (${response.status}): ${errorText.substring(0, 100)}`);
                }
                
                // Validate response is JSON
                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    const text = await response.text();
                    throw new Error(`Invalid server response. Expected JSON, got: ${text.substring(0, 100)}`);
                }
                
                const data = await response.json();
                
                if (data.error) {
                    throw new Error(data.error);
                }
                
                if (data.success) {
                    pdfStatus.textContent = translations[lang]?.pdfSuccess || 'PDF generated successfully!';
                    pdfStatus.style.color = '#4CAF50';

                    if (window.parent) {
                        const generatedPdfPath = data.pdf_path || filePath.replace(/\.md$/i, '.pdf');
                        window.parent.postMessage({
                            type: 'PDF_SAVED',
                            path: generatedPdfPath,
                            timestamp: Date.now()
                        }, '*');
                    }

                    setTimeout(() => { pdfModal.style.display = 'none'; }, 2000);
                } else {
                    throw new Error(data.error || 'Unknown error');
                }
            } catch (error) {
                pdfStatus.textContent = (translations[lang]?.pdfError || 'PDF error') + ': ' + error.message;
                pdfStatus.style.color = '#c42b1c';
                showStatus((translations[lang]?.pdfError || 'PDF error') + ': ' + error.message, true, { localOnly: true });
            }
        }
        function startAutoSaveTimer() {
            if (autoSaveTimer) clearTimeout(autoSaveTimer);
            autoSaveTimer = setTimeout(() => { if (hasUnsavedChanges) saveFile(false); }, AUTO_SAVE_DELAY);
            if (window.parent && !hasUnsavedChanges) window.parent.postMessage({ type: 'markdownEditorStatus', message: translations[lang]?.unsavedChanges || 'Unsaved changes', hasUnsavedChanges: true }, '*');
            hasUnsavedChanges = true;
        }
        function updateButtonStates() {
            if (!editor) return;
            
            // Get the undo/redo state
            const undoState = editor.getModel().canUndo();
            const redoState = editor.getModel().canRedo();
            
            // Update the undo/redo buttons
            const undoBtn = document.getElementById('undoBtn');
            const redoBtn = document.getElementById('redoBtn');
            
            if (undoState) {
                undoBtn.classList.remove('disabled');
                undoBtn.disabled = false;
            } else {
                undoBtn.classList.add('disabled');
                undoBtn.disabled = true;
            }
            
            if (redoState) {
                redoBtn.classList.remove('disabled');
                redoBtn.disabled = false;
            } else {
                redoBtn.classList.add('disabled');
                redoBtn.disabled = true;
            }
            
            // Update formatting button state (requires selection)
            const selection = editor.getSelection();
            const hasSelection = selection && !selection.isEmpty();
            
            // Buttons that require a selection
            const selectionButtons = [
                document.getElementById('boldBtn'),
                document.getElementById('italicBtn'),
                document.getElementById('underlineBtn'),
                document.getElementById('strikeBtn'),
                document.getElementById('ulistBtn'),
                document.getElementById('olistBtn'),
                document.getElementById('checklistBtn'),
                document.getElementById('linkBtn'),
                document.getElementById('codeBtn'),
                document.getElementById('quoteBtn')
            ];
            
            selectionButtons.forEach(btn => {
                if (hasSelection) {
                    btn.classList.remove('disabled');
                    btn.disabled = false;
                } else {
                    btn.classList.add('disabled');
                    btn.disabled = true;
                }
            });

            // Heading dropdown
            const headingSelect = document.getElementById('headingSelect');
            if (hasSelection) {
                headingSelect.classList.remove('disabled');
                headingSelect.disabled = false;
            } else {
                headingSelect.classList.add('disabled');
                headingSelect.disabled = true;
            }
            
            // Translator button - always active but changes style
            if (hasSelection) {
                translateBtnToolbar.classList.remove('default');
                translateBtnToolbar.classList.add('primary');
            } else {
                translateBtnToolbar.classList.remove('primary');
                translateBtnToolbar.classList.add('default');
            }
        }
        const boldBtn = document.getElementById('boldBtn');
        const italicBtn = document.getElementById('italicBtn');
        const underlineBtn = document.getElementById('underlineBtn');
        const strikeBtn = document.getElementById('strikeBtn');
        const headingSelect = document.getElementById('headingSelect');
        const ulistBtn = document.getElementById('ulistBtn');
        const olistBtn = document.getElementById('olistBtn');
        const checklistBtn = document.getElementById('checklistBtn');
        const linkBtn = document.getElementById('linkBtn');
        const codeBtn = document.getElementById('codeBtn');
        const quoteBtn = document.getElementById('quoteBtn');
        const tableBtn = document.getElementById('tableBtn');
        const hrBtn = document.getElementById('hrBtn');
        const mathBtn = document.getElementById('mathBtn');
        const undoBtn = document.getElementById('undoBtn');
        const redoBtn = document.getElementById('redoBtn');
        // Buttons that are always active
        const alwaysActiveButtons = [tableBtn, hrBtn, mathBtn];
        const confirmClearModal = document.getElementById('confirmClearModal');
        const tableModal = document.getElementById('tableModal');
        const mathModal = document.getElementById('mathModal');
        const pdfModal = document.getElementById('pdfModal');
        const cancelClearBtn = document.getElementById('cancelClearBtn');
        const confirmClearBtn = document.getElementById('confirmClearBtn');
        const cancelTableBtn = document.getElementById('cancelTableBtn');
        const insertTableBtn = document.getElementById('insertTableBtn');
        const cancelMathBtn = document.getElementById('cancelMathBtn');
        const insertMathBtn = document.getElementById('insertMathBtn');
        const cancelPdfBtn = document.getElementById('cancelPdfBtn');
        const generatePdfBtn = document.getElementById('generatePdfBtn');
        const tableRows = document.getElementById('tableRows');
        const tableCols = document.getElementById('tableCols');
        const mathFormula = document.getElementById('mathFormula');

        // Configure marked for correct LaTeX handling
        marked.setOptions({
            breaks: true,
            gfm: true,
            headerIds: false,
            mangle: false
        });

        // Function to process LaTeX before Markdown
        function processLatex(text) {
            const blocks = [];
            let processed = text;
            processed = processed.replace(/\$\$([\s\S]*?)\$\$/g, function(match) {
                blocks.push(match);
                return `%%LATEX_BLOCK_${blocks.length-1}%%`;
            });
            processed = processed.replace(/\\\[([\s\S]*?)\\\]/g, function(match) {
                blocks.push(match);
                return `%%LATEX_BLOCK_${blocks.length-1}%%`;
            });
            processed = processed.replace(/\\\(([\s\S]*?)\\\)/g, function(match) {
                blocks.push(match);
                return `%%LATEX_INLINE_${blocks.length-1}%%`;
            });
            processed = processed.replace(/\$([^$\n]+?)\$/g, function(match, inner) {
                blocks.push('\\(' + inner + '\\)');
                return `%%LATEX_INLINE_${blocks.length-1}%%`;
            });
            return { processed, blocks };
        }

        // Function to restore LaTeX after Markdown
        function restoreLatex(html, blocks) {
            let restored = html;
            blocks.forEach((block, i) => {
                // Replace markers back to LaTeX, but escape for HTML
                restored = restored.replace(`%%LATEX_BLOCK_${i}%%`, block);
                restored = restored.replace(`%%LATEX_INLINE_${i}%%`, block);
            });
            return restored;
        }

        function updatePreview() {
            if (!editor) return;
            const markdownText = editor.getValue();
            const fallbackImageDataUri = 'data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%22150%22 height=%22150%22 viewBox=%220 0 150 150%22%3E%3Crect width=%22150%22 height=%22150%22 fill=%22%232d2d2d%22/%3E%3Ctext x=%2250%25%22 y=%2250%25%22 dominant-baseline=%22middle%22 text-anchor=%22middle%22 font-family=%22Arial%2Csans-serif%22 font-size=%2212%22 fill=%22%23bbbbbb%22%3Eimage%3C/text%3E%3C/svg%3E';
            const safeMarkdownText = markdownText.replace(/https?:\/\/via\.placeholder\.com\/150(?:[^\s)]*)?/gi, fallbackImageDataUri);

            // Process LaTeX
            const { processed, blocks } = processLatex(safeMarkdownText);

            // Convert Markdown to HTML
            let html = marked.parse(processed);

            // Restore LaTeX
            html = restoreLatex(html, blocks);

            // Insert into the preview
            previewContent.innerHTML = html;

            // Render math via MathJax
            if (window.MathJax && typeof MathJax.typesetPromise === 'function') {
                MathJax.typesetPromise([previewContent]).catch(err => {
                    // MathJax errors are non-critical
                });
            }
        }

        function refreshPreview() { 
            updatePreview(); 
            showStatus("Preview refreshed", false); 
        }
        
        function insertTextAtCursor(before, after = '') {
            if (!editor) return;
            const selection = editor.getSelection();
            const selectedText = editor.getModel().getValueInRange(selection);
            const newText = before + selectedText + after;
            editor.executeEdits("", [{ range: selection, text: newText, forceMoveMarkers: true }]);
            editor.focus();
            updatePreview();
            updateButtonStates();
        }
        function applyToList(prefix) {
            if (!editor) return;
            const selection = editor.getSelection();
            const model = editor.getModel();
            const startLine = selection.startLineNumber;
            const endLine = selection.endLineNumber;
            let edits = [];
            for (let lineNum = startLine; lineNum <= endLine; lineNum++) {
                const lineContent = model.getLineContent(lineNum);
                const trimmedLine = lineContent.trim();
                if (trimmedLine.length > 0) edits.push({ range: new monaco.Range(lineNum, 1, lineNum, lineContent.length + 1), text: prefix + lineContent, forceMoveMarkers: true });
            }
            if (edits.length > 0) { editor.executeEdits("", edits); editor.focus(); updatePreview(); updateButtonStates(); }
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
                preview.scrollTop = 0;
            } else {
                preview.classList.remove('fullscreen');
                fullscreenHeader.style.display = 'none';
                fullscreenBtn.style.display = 'flex';
                exitFullscreenBtn.style.display = 'none';
                fullscreenBtnMobile.style.display = 'flex';
                exitFullscreenBtnMobile.style.display = 'none';
            }
        }
        boldBtn.addEventListener('click', () => insertTextAtCursor('**', '**'));
        italicBtn.addEventListener('click', () => insertTextAtCursor('*', '*'));
        underlineBtn.addEventListener('click', () => insertTextAtCursor('<u>', '</u>'));
        strikeBtn.addEventListener('click', () => insertTextAtCursor('~~', '~~'));
        headingSelect.addEventListener('change', function() {
            const value = this.value;
            if (!value) return;
            let prefix = '';
            switch(value) { case 'h1': prefix = '# '; break; case 'h2': prefix = '## '; break; case 'h3': prefix = '### '; break; case 'h4': prefix = '#### '; break; }
            applyToList(prefix);
            this.value = '';
        });
        ulistBtn.addEventListener('click', () => applyToList('- '));
        olistBtn.addEventListener('click', () => {
            if (!editor) return;
            const selection = editor.getSelection();
            const startLine = selection.startLineNumber;
            const endLine = selection.endLineNumber;
            const model = editor.getModel();
            let edits = [];
            let counter = 1;
            for (let lineNum = startLine; lineNum <= endLine; lineNum++) {
                const lineContent = model.getLineContent(lineNum);
                const trimmedLine = lineContent.trim();
                if (trimmedLine.length > 0) { const newLineContent = counter + '. ' + lineContent; counter++; edits.push({ range: new monaco.Range(lineNum, 1, lineNum, lineContent.length + 1), text: newLineContent, forceMoveMarkers: true }); }
            }
            if (edits.length > 0) { editor.executeEdits("", edits); editor.focus(); updatePreview(); updateButtonStates(); }
        });
        checklistBtn.addEventListener('click', () => applyToList('- [ ] '));
        linkBtn.addEventListener('click', () => insertTextAtCursor('[text](https://example.com)'));
        codeBtn.addEventListener('click', () => insertTextAtCursor('```\n', '\n```'));
        quoteBtn.addEventListener('click', () => applyToList('> '));
        tableBtn.addEventListener('click', () => { tableRows.value = 3; tableCols.value = 3; tableModal.style.display = 'flex'; });
        hrBtn.addEventListener('click', () => {
            if (!editor) return;
            const selection = editor.getSelection();
            const position = selection.getStartPosition();
            const hrText = '\n\n---\n\n';
            editor.executeEdits("", [{ range: new monaco.Range(position.lineNumber, position.column, position.lineNumber, position.column), text: hrText, forceMoveMarkers: true }]);
            editor.focus();
            updatePreview();
            updateButtonStates();
        });
        mathBtn.addEventListener('click', () => { mathFormula.value = ''; mathModal.style.display = 'flex'; });
        undoBtn.addEventListener('click', () => { 
            editor.trigger('keyboard', 'undo', null); 
            setTimeout(() => {
                updatePreview(); 
                updateButtonStates();
            }, 10);
        });
        redoBtn.addEventListener('click', () => { 
            editor.trigger('keyboard', 'redo', null); 
            setTimeout(() => {
                updatePreview(); 
                updateButtonStates();
            }, 10);
        });
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
        cancelTableBtn.addEventListener('click', () => { tableModal.style.display = 'none'; });
        insertTableBtn.addEventListener('click', () => {
            const rows = parseInt(tableRows.value) || 3;
            const cols = parseInt(tableCols.value) || 3;
            if (rows < 1 || cols < 1) return;
            let tableText = '\n|'; for (let i = 0; i < cols; i++) tableText += ` Header ${i+1} |`; tableText += '\n|'; for (let i = 0; i < cols; i++) tableText += ' --- |'; tableText += '\n';
            for (let r = 0; r < rows; r++) { tableText += '|'; for (let c = 0; c < cols; c++) tableText += ` Cell ${r+1},${c+1} |`; tableText += '\n'; }
            tableText += '\n';
            const selection = editor.getSelection();
            editor.executeEdits("", [{ range: selection, text: tableText, forceMoveMarkers: true }]);
            tableModal.style.display = 'none';
            editor.focus();
            updatePreview();
            updateButtonStates();
        });
        cancelMathBtn.addEventListener('click', () => { mathModal.style.display = 'none'; });
        insertMathBtn.addEventListener('click', () => {
            const formula = mathFormula.value.trim();
            if (!formula) return;
            const mathType = document.querySelector('input[name="mathType"]:checked').value;
            let mathText = mathType === 'inline' ? `\\(${formula}\\)` : `\n$$\n${formula}\n$$\n`;
            const selection = editor.getSelection();
            editor.executeEdits("", [{ range: selection, text: mathText, forceMoveMarkers: true }]);
            mathModal.style.display = 'none';
            editor.focus();
            updatePreview();
            updateButtonStates();
        });
        exportPdfBtn.addEventListener('click', () => { pdfModal.style.display = 'flex'; document.getElementById('pdfStatus').textContent = ''; });
        cancelPdfBtn.addEventListener('click', () => { pdfModal.style.display = 'none'; });
        generatePdfBtn.addEventListener('click', exportToPDF);
        saveBtn.addEventListener('click', saveFile);
        window.addEventListener('message', (event) => {
            if (event.data && event.data.type === 'editor:save') saveFile();
        });
        function openTranslationModal() {
            if (!editor) return;
            
            const selection = editor.getSelection();
            const selectedText = editor.getModel().getValueInRange(selection);
            
            // Always show the window, even without a selection
            if (selectedText && selectedText.trim().length > 0) {
                currentSelectedText = selectedText;
                sourceText.value = selectedText;
            } else {
                // If there is no selection, clear the fields
                sourceText.value = '';
                targetText.value = '';
                currentSelectedText = '';
            }
            
            // If there is a previous result with the same languages, show it
            if (lastTranslationResult && 
                lastTranslationParams.sourceLang === sourceLang.value && 
                lastTranslationParams.targetLang === targetLang.value &&
                sourceText.value === currentSelectedText) {
                targetText.value = lastTranslationResult;
            } else {
                targetText.value = '';
            }
            
            translationModal.style.display = 'flex';
            
            // Focus the input field for convenience
            setTimeout(() => sourceText.focus(), 100);
        }
        async function performTranslation() {
            const text = sourceText.value;
            const source = sourceLang.value;
            const target = targetLang.value;
            if (!text.trim()) { showStatus('No text to translate', true); return; }
            const originalText = translateBtn.innerHTML;
            translateBtn.innerHTML = '<span class="translation-loading"></span> ' + (translations[lang]?.translating || 'Translating...');
            translateBtn.disabled = true;
            targetText.style.opacity = '0.5';
            targetText.style.transition = 'opacity 0.3s ease';
            const formData = new FormData();
            formData.append('translate', 'true');
            formData.append('text', text);
            formData.append('source_lang', source);
            formData.append('target_lang', target);
            try {
                const response = await fetch(window.location.href, { method: 'POST', body: formData });
                const data = await response.json();
                if (data.success) {
                    smoothTextReplace(targetText, data.translated_text);
                    lastTranslationResult = data.translated_text;
                    lastTranslationParams = { sourceLang: source, targetLang: target };
                } else showStatus(data.error || 'Translation failed', true);
            } catch (error) {
                showStatus('Translation error: ' + error.message, true);
                targetText.style.opacity = '1';
            } finally {
                translateBtn.innerHTML = originalText;
                translateBtn.disabled = false;
            }
        }
        function smoothTextReplace(textarea, newText) {
            textarea.style.transition = 'opacity 0.2s ease';
            textarea.style.opacity = '0';
            setTimeout(() => {
                textarea.value = newText;
                textarea.style.opacity = '1';
            }, 200);
        }
        function replaceWithTranslation() {
            const translated = targetText.value;
            if (!translated) { showStatus('No translation to replace', true); return; }
            const selection = editor.getSelection();
            if (selection) { editor.executeEdits("", [{ range: selection, text: translated, forceMoveMarkers: true }]); editor.focus(); updatePreview(); }
            translationModal.style.display = 'none';
        }
        translateBtnToolbar.addEventListener('click', openTranslationModal);
        translateBtn.addEventListener('click', performTranslation);
        translateAgainBtn.addEventListener('click', performTranslation);
        replaceTextBtn.addEventListener('click', replaceWithTranslation);
        window.addEventListener('click', (e) => {
            if (e.target === confirmClearModal) confirmClearModal.style.display = 'none';
            if (e.target === tableModal) tableModal.style.display = 'none';
            if (e.target === mathModal) mathModal.style.display = 'none';
            if (e.target === pdfModal) pdfModal.style.display = 'none';
            if (e.target === translationModal) translationModal.style.display = 'none';
        });
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
                editorPanel.style.flex = `0 0 ${editorWidth}px`;
                previewPanel.style.flex = '1';
                if (editor) editor.layout();
            }
        }
        resizer.addEventListener('mousedown', (e) => {
            isResizing = true;
            document.body.style.cursor = 'col-resize';
            document.body.style.userSelect = 'none';
            const startX = e.clientX;
            const editorWidth = editorPanel.offsetWidth;
            function onMouseMove(e) {
                if (!isResizing) return;
                const dx = e.clientX - startX;
                const containerWidth = editorPanel.parentElement.offsetWidth;
                const newEditorWidth = editorWidth + dx;
                const minWidth = 300;
                if (newEditorWidth >= minWidth && containerWidth - newEditorWidth - resizer.offsetWidth >= minWidth) { editorPanel.style.flex = `0 0 ${newEditorWidth}px`; previewPanel.style.flex = '1'; }
            }
            function onMouseUp() {
                isResizing = false;
                document.body.style.cursor = '';
                document.body.style.userSelect = '';
                document.removeEventListener('mousemove', onMouseMove);
                document.removeEventListener('mouseup', onMouseUp);
                if (editor) editor.layout();
            }
            document.addEventListener('mousemove', onMouseMove);
            document.addEventListener('mouseup', onMouseUp);
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
                    language: 'markdown',
                    theme: theme === 'light' ? 'custom-light' : 'custom-dark',
                    fontSize: 14,
                    fontFamily: 'Consolas, "Courier New", monospace',
                    lineHeight: 1.6,
                    // Minimap instead of scrollbar - hidden on touch devices in portrait orientation
                    minimap: (function() {
                        var isTouch = ('ontouchstart' in window) || (navigator.maxTouchPoints > 0);
                        var isPortrait = window.innerHeight > window.innerWidth;
                        var shouldHide = isTouch && isPortrait;
                        return {
                            enabled: !shouldHide,
                            side: 'right',
                            size: 'fit',
                            showSlider: 'always',
                            renderCharacters: false
                        };
                    })(),
                    // Disable the vertical scrollbar
                    scrollbar: {
                        vertical: 'hidden',
                        horizontal: 'visible',
                        useShadows: false,
                        verticalHasArrows: false,
                        horizontalHasArrows: true,
                        verticalScrollbarSize: 0,
                        horizontalScrollbarSize: 8,
                        alwaysConsumeMouseWheel: false
                    },
                    // Additional settings for convenience
                    scrollBeyondLastLine: true,
                    wordWrap: 'on',
                    automaticLayout: true,
                    formatOnPaste: true,
                    formatOnType: true,
                    suggestOnTriggerCharacters: true,
                    fixedOverflowWidgets: true,
                    renderLineHighlight: 'all',
                    quickSuggestions: true,
                    links: true,
                    contextmenu: true,
                    folding: true,
                    lineNumbers: 'on',
                    glyphMargin: false
                }));
                // Same transliteration rules as the main editor.
                shared.installTransliteration({
                    editor: editor,
                    container: document.getElementById('editor-container'),
                    monaco: monaco,
                    getLang: function () { return shared.resolveLang(lang); }
                });
                // Enter on "1. item" continues the list with "2. ", like the .txt editor.
                if (shared.installNumberedListContinuation) {
                    shared.installNumberedListContinuation({ editor: editor, monaco: monaco, filePath: filePath });
                }
                
                if (window.monacoTouchSelection) {
                    window.monacoTouchSelection.enable(editor, document.getElementById('editor-container'));
                }
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
                            updatePreview();
                            updateButtonStates();
                        }
                    });
                }
                editor.onDidChangeModelContent(() => {
                    updatePreview();
                    if (!isInitialLoad) startAutoSaveTimer();
                    updateButtonStates();
                });
                editor.onDidChangeCursorSelection(() => { updateButtonStates(); });

                // Add listeners to update undo/redo state when the stack changes
                editor.onDidChangeModel(() => {
                    updateButtonStates();
                });

                // Update minimap visibility on orientation change
                function updateMinimapVisibility() {
                    var isTouch = ('ontouchstart' in window) || (navigator.maxTouchPoints > 0);
                    var isPortrait = window.innerHeight > window.innerWidth;
                    var shouldHide = isTouch && isPortrait;
                    editor.updateOptions({
                        minimap: {
                            enabled: !shouldHide,
                            side: 'right',
                            size: 'fit',
                            showSlider: 'always',
                            renderCharacters: false
                        }
                    });
                }

                window.addEventListener('orientationchange', function() {
                    setTimeout(updateMinimapVisibility, 300);
                });

                window.addEventListener('resize', function() {
                    updateMinimapVisibility();
                });
                
                updatePreview();
                expandPreviewPanel();
                isInitialLoad = false;
                window.originalContent = editor.getValue();
                if (window.attachCloudIframeMonacoAi) window.attachCloudIframeMonacoAi(editor);
                window.cloudIframeMonacoAiSave = saveFile;
                editor.addCommand(monaco.KeyMod.CtrlCmd | monaco.KeyCode.KeyB, () => insertTextAtCursor('**', '**'));
                editor.addCommand(monaco.KeyMod.CtrlCmd | monaco.KeyCode.KeyI, () => insertTextAtCursor('*', '*'));
                editor.addCommand(monaco.KeyMod.CtrlCmd | monaco.KeyCode.KeyU, () => insertTextAtCursor('<u>', '</u>'));
                editor.addCommand(monaco.KeyMod.CtrlCmd | monaco.KeyCode.KeyS, saveFile);
                
                // Set the initial button state
                updateButtonStates();
            }).catch(function (e) {
                console.error('Monaco failed to load:', e);
            });
        }
        document.addEventListener('visibilitychange', () => {
            if (document.hidden && hasUnsavedChanges && autoSaveTimer) { clearTimeout(autoSaveTimer); saveFile(false); }
        });
        window.addEventListener('message', function(event) {
            if (event.data.type === 'loadMarkdownContent' && event.data.content) {
                if (editor) { editor.setValue(event.data.content); window.originalContent = event.data.content; updatePreview(); }
            }
        });
        window.addEventListener('load', () => {
            translatePage();
            initMonaco();
            
            const isMobile = window.innerWidth <= 768;
            if (isMobile) { document.querySelector('.preview-header').style.display = 'none'; mobilePreviewActions.style.display = 'flex'; }
        });
        window.addEventListener('resize', function() {
            if (editor) editor.layout();
            if (!isResizing && window.innerWidth > 768) expandPreviewPanel();
            const isMobile = window.innerWidth <= 768;
            if (isMobile) { document.querySelector('.preview-header').style.display = 'none'; mobilePreviewActions.style.display = 'flex'; }
            else { document.querySelector('.preview-header').style.display = 'flex'; mobilePreviewActions.style.display = 'none'; }
        });
        document.addEventListener('keydown', function(e) { if (e.key === 'Escape' && isFullscreen) toggleFullscreen(); });
        previewContent.addEventListener('click', function(e) {
            const link = e.target.closest('a[href]');
            if (!link) return;
            const href = link.getAttribute('href');
            if (!href || href.startsWith('#')) return;
            if (e.defaultPrevented || e.button !== 0) return;
            if (e.ctrlKey || e.metaKey || e.shiftKey || e.altKey) return;
            e.preventDefault();
            window.open(href, '_blank', 'noopener,noreferrer');
        });
    
