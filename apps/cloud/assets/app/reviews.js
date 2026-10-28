/*
 * Cloud file reviews — parent-side UI.
 *
 * The toolbar button and the floating review panel live here so every editor
 * (LaTeX, Markdown, HTML and the plain Monaco editor) gets the same UI. Inline
 * comment chips are painted by the editor bridge (editor-review.js) inside the
 * iframes, or directly on the parent Monaco instance.
 */
(function () {
    'use strict';
    if (window.__cloudReviewsLoaded) return;
    window.__cloudReviewsLoaded = true;

    const lang = (window.APP_CONFIG && window.APP_CONFIG.lang) || 'en';
    const DICT = {
        en: { title: 'Review', add: 'Add comment', placeholder: 'Write a comment...', suggest: 'Suggest replacement', save: 'Save', cancel: 'Cancel', accept: 'Accept', reject: 'Reject', del: 'Delete', empty: 'No reviews yet', emptyHint: 'Select a line or some text and add the first comment.', lines: 'Line', linesPlural: 'Lines', accepted: 'Accepted', rejected: 'Rejected', stale: 'Text has changed, cannot apply', commentRequired: 'Write a comment or a suggestion', error: 'Could not load reviews', openFirst: 'Open review panel' },
        ru: { title: 'Ревью', add: 'Добавить комментарий', placeholder: 'Напишите комментарий...', suggest: 'Предложить замену', save: 'Сохранить', cancel: 'Отмена', accept: 'Принять', reject: 'Отклонить', del: 'Удалить', empty: 'Пока нет ревью', emptyHint: 'Выделите строку или текст и добавьте первый комментарий.', lines: 'Строка', linesPlural: 'Строки', accepted: 'Принято', rejected: 'Отклонено', stale: 'Текст изменился, применить нельзя', commentRequired: 'Напишите комментарий или замену', error: 'Не удалось загрузить ревью', openFirst: 'Открыть панель ревью' },
        uk: { title: 'Ревʼю', add: 'Додати коментар', placeholder: 'Напишіть коментар...', suggest: 'Запропонувати заміну', save: 'Зберегти', cancel: 'Скасувати', accept: 'Прийняти', reject: 'Відхилити', del: 'Видалити', empty: 'Поки немає ревʼю', emptyHint: 'Виділіть рядок або текст і додайте перший коментар.', lines: 'Рядок', linesPlural: 'Рядки', accepted: 'Прийнято', rejected: 'Відхилено', stale: 'Текст змінився, застосувати не можна', commentRequired: 'Напишіть коментар або заміну', error: 'Не вдалося завантажити ревʼю', openFirst: 'Відкрити панель ревʼю' },
        be: { title: 'Рэцэнзія', add: 'Дадаць каментарый', placeholder: 'Напішыце каментарый...', suggest: 'Прапанаваць замену', save: 'Захаваць', cancel: 'Скасаваць', accept: 'Прыняць', reject: 'Адхіліць', del: 'Выдаліць', empty: 'Пакуль няма рэцэнзій', emptyHint: 'Выдзеліце радок або тэкст і дадайце першы каментарый.', lines: 'Радок', linesPlural: 'Радкі', accepted: 'Прынята', rejected: 'Адхілена', stale: 'Тэкст змяніўся, ужыць нельга', commentRequired: 'Напішыце каментарый або замену', error: 'Не ўдалося загрузіць рэцэнзіі', openFirst: 'Адкрыць панэль рэцэнзій' }
    };
    const t = (k) => (DICT[lang] || DICT.en)[k];

    const state = {
        tabId: null,
        tab: null,
        path: '',
        root: 'main',
        items: [],
        open: false,
        autoOpened: new Set(),
        lastKey: ''
    };

    const el = (tag, cls, text) => {
        const n = document.createElement(tag);
        if (cls) n.className = cls;
        if (text !== undefined && text !== null) n.textContent = text;
        return n;
    };
    const icon = (paths) => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + paths + '</svg>';

    /* ------------------------------------------------------------------ UI */

    const panel = el('aside', 'crv-panel');
    panel.id = 'cloud-review-panel';
    panel.hidden = true;

    const head = el('header', 'crv-head');
    const titleWrap = el('div', 'crv-title');
    titleWrap.innerHTML = '<span class="crv-title-icon">' + icon('<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>') + '</span><span>' + t('title') + '</span><span class="crv-count" id="crv-count" hidden></span>';
    const headActions = el('div', 'crv-head-actions');
    const addBtn = el('button', 'crv-icon-btn crv-add');
    addBtn.type = 'button';
    addBtn.title = t('add');
    addBtn.innerHTML = icon('<line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>');
    const closeBtn = el('button', 'crv-icon-btn');
    closeBtn.type = 'button';
    closeBtn.title = 'Close';
    closeBtn.innerHTML = icon('<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>');
    headActions.append(addBtn, closeBtn);
    head.append(titleWrap, headActions);

    const list = el('div', 'crv-list');
    list.id = 'crv-list';

    const form = el('form', 'crv-form');
    form.hidden = true;
    const commentInput = el('textarea', 'crv-textarea');
    commentInput.placeholder = t('placeholder');
    commentInput.rows = 3;
    const suggestRow = el('label', 'crv-suggest-toggle');
    const suggestCheck = document.createElement('input');
    suggestCheck.type = 'checkbox';
    const suggestLabel = el('span', '', t('suggest'));
    suggestRow.append(suggestCheck, suggestLabel);
    const suggestInput = el('textarea', 'crv-textarea crv-textarea-suggestion');
    suggestInput.rows = 3;
    suggestInput.hidden = true;
    const formActions = el('div', 'crv-form-actions');
    const saveBtn = el('button', 'crv-btn crv-btn-primary', t('save'));
    saveBtn.type = 'submit';
    const cancelBtn = el('button', 'crv-btn', t('cancel'));
    cancelBtn.type = 'button';
    formActions.append(saveBtn, cancelBtn);
    form.append(commentInput, suggestRow, suggestInput, formActions);

    panel.append(head, list, form);
    document.body.appendChild(panel);

    const toast = el('div', 'crv-toast');
    toast.hidden = true;
    panel.appendChild(toast);
    let toastTimer = null;
    function showToast(msg, isError) {
        toast.textContent = msg;
        toast.classList.toggle('is-error', !!isError);
        toast.hidden = false;
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => { toast.hidden = true; }, 3200);
    }

    function setOpen(open) {
        state.open = !!open;
        panel.hidden = !state.open;
        document.getElementById('btn-review')?.classList.toggle('active', state.open);
        document.body.classList.toggle('crv-panel-open', state.open);
        if (!state.open) closeForm();
    }

    function closeForm() {
        form.hidden = true;
        commentInput.value = '';
        suggestCheck.checked = false;
        suggestInput.hidden = true;
        suggestInput.value = '';
    }

    /* --------------------------------------------------------------- target */

    function activeTab() {
        try {
            if (typeof tabsState === 'undefined' || !tabsState || !tabsState.tabs) return null;
            return tabsState.tabs.find((tab) => tab.id === tabsState.activeTabId) || null;
        } catch (_) { return null; }
    }

    function resolvedRoot(tab) {
        try {
            if (typeof getResolvedTabRoot === 'function') return getResolvedTabRoot(tab) || 'main';
        } catch (_) {}
        return (tab && tab.root) || 'main';
    }

    function isSupported(tab) {
        if (!tab || !tab.path) return false;
        if (document.body.classList.contains('trash-mode')) return false;
        if (tab.photoEditor || tab.onlyOfficeEditor || tab.zipViewer || tab.transcriptionEditor || tab.ttsEditor) return false;
        if (tab.latexEditor || tab.markdownEditor || tab.htmlEditor) return true;
        return !!tab.editMode;
    }

    function monacoEditor(tab) {
        try { return window.editorsMap && window.editorsMap.get(tab.id) || null; } catch (_) { return null; }
    }

    function iframeOf(tab) {
        return document.querySelector('#viewer iframe[data-tab-id="' + String(tab.id) + '"]');
    }

    function iframeCall(win, action, payload, timeoutMs) {
        return new Promise((resolve) => {
            if (!win) { resolve(null); return; }
            const reqId = 'crv_' + Date.now() + '_' + Math.random().toString(36).slice(2);
            let done = false;
            const finish = (value) => {
                if (done) return;
                done = true;
                clearTimeout(timer);
                window.removeEventListener('message', onMessage);
                resolve(value);
            };
            const timer = setTimeout(() => finish(null), timeoutMs || 2500);
            function onMessage(ev) {
                const d = ev.data;
                if (!d || d.source !== 'cloudReviewIframe' || d.type !== 'result' || d.reqId !== reqId) return;
                if (ev.source !== win) return;
                finish(d.ok ? (d.result || {}) : null);
            }
            window.addEventListener('message', onMessage);
            try {
                win.postMessage({ source: 'cloudParent', type: 'cloudReview', action, reqId, ...(payload || {}) }, '*');
            } catch (_) { finish(null); }
        });
    }

    function getSelection() {
        const tab = state.tab;
        if (!tab) return Promise.resolve(null);
        if (tab.latexEditor || tab.markdownEditor || tab.htmlEditor) {
            const iframe = iframeOf(tab);
            return iframeCall(iframe && iframe.contentWindow, 'getSelection');
        }
        const ed = monacoEditor(tab);
        if (!ed) return Promise.resolve(null);
        try {
            const model = ed.getModel();
            const sel = ed.getSelection();
            if (!model || !sel) return Promise.resolve(null);
            let endLine = sel.endLineNumber;
            if (sel.endColumn === 1 && endLine > sel.startLineNumber) endLine--;
            const text = sel.isEmpty() ? model.getLineContent(sel.startLineNumber) : model.getValueInRange(sel);
            return Promise.resolve({ start: sel.startLineNumber, end: endLine, quote: text });
        } catch (_) { return Promise.resolve(null); }
    }

    function revealLine(line) {
        const tab = state.tab;
        if (!tab) return;
        if (tab.latexEditor || tab.markdownEditor || tab.htmlEditor) {
            const iframe = iframeOf(tab);
            iframeCall(iframe && iframe.contentWindow, 'reveal', { line }, 1200);
            return;
        }
        const ed = monacoEditor(tab);
        if (!ed) return;
        try {
            const ln = Math.max(1, Math.min(ed.getModel().getLineCount(), Number(line) || 1));
            ed.revealLineInCenter(ln);
            ed.setPosition({ lineNumber: ln, column: 1 });
            ed.focus();
        } catch (_) {}
    }

    /* ----------------------------------------------- parent Monaco markers */

    const parentMarkers = new WeakMap();

    function clearParentMarkers(ed) {
        const entry = parentMarkers.get(ed);
        if (!entry) return;
        entry.widgets.forEach((w) => { try { ed.removeContentWidget(w); } catch (_) {} });
        if (entry.deco) { try { entry.deco.clear(); } catch (_) {} }
        parentMarkers.delete(ed);
    }

    function applyParentMarkers(ed, items) {
        if (!ed || !window.monaco) return;
        clearParentMarkers(ed);
        const open = (items || []).filter((i) => i && i.status === 'open');
        if (!open.length) return;
        const byLine = new Map();
        const model = ed.getModel();
        if (!model) return;
        open.forEach((item) => {
            const line = Math.max(1, Math.min(model.getLineCount(), Number(item.start_line) || 1));
            if (!byLine.has(line)) byLine.set(line, []);
            byLine.get(line).push(item);
        });
        const widgets = [];
        const decorations = [];
        byLine.forEach((group, line) => {
            const endLine = Math.max(line, Math.min(model.getLineCount(), Number(group[0].end_line) || line));
            decorations.push({
                range: new window.monaco.Range(line, 1, endLine, 1),
                options: { isWholeLine: true, className: 'rv-line' }
            });
            const node = el('div', 'rv-annot');
            const dot = el('span', 'rv-annot-dot', group.length > 1 ? String(group.length) : '💬');
            const text = el('span', 'rv-annot-text', ((group[0].author ? group[0].author + ': ' : '') + (group[0].comment || group[0].suggestion || '')).slice(0, 90) || 'Review');
            node.title = group.map((i) => (i.author ? i.author + ': ' : '') + (i.comment || i.suggestion || '')).join('\n');
            node.append(dot, text);
            node.addEventListener('click', () => {
                setOpen(true);
                focusCard(group[0].id);
                revealLine(line);
            });
            const widget = {
                getId: () => 'rv-annot-' + state.tabId + '-' + line,
                getDomNode: () => node,
                getPosition: () => ({
                    position: { lineNumber: line, column: model.getLineMaxColumn(line) },
                    preference: [window.monaco.editor.ContentWidgetPositionPreference.EXACT]
                })
            };
            ed.addContentWidget(widget);
            widgets.push(widget);
        });
        const deco = ed.createDecorationsCollection(decorations);
        parentMarkers.set(ed, { widgets, deco });
    }

    function applyMarkers(items) {
        const tab = state.tab;
        if (!tab) return;
        if (tab.latexEditor || tab.markdownEditor || tab.htmlEditor) {
            const iframe = iframeOf(tab);
            if (iframe && iframe.contentWindow) {
                iframeCall(iframe.contentWindow, 'apply', { items }, 1200);
            }
            return;
        }
        const ed = monacoEditor(tab);
        if (ed) applyParentMarkers(ed, items);
        else setTimeout(() => {
            if (state.tab === tab) applyMarkers(items);
        }, 500);
    }

    /* --------------------------------------------------------------- panel */

    function lineLabel(item) {
        const start = Number(item.start_line) || 1;
        const end = Number(item.end_line) || start;
        return (end > start ? t('linesPlural') : t('lines')) + ' ' + start + (end > start ? '–' + end : '');
    }

    function renderList() {
        const count = state.items.filter((i) => i.status === 'open').length;
        const countEl = panel.querySelector('#crv-count');
        if (countEl) {
            countEl.textContent = count ? String(count) : '';
            countEl.hidden = !count;
        }
        const toolbarBadge = document.getElementById('btn-review-badge');
        if (toolbarBadge) {
            toolbarBadge.textContent = count ? String(count) : '';
            toolbarBadge.hidden = !count;
        }
        list.replaceChildren();
        if (!state.items.length) {
            const empty = el('div', 'crv-empty');
            empty.innerHTML = '<span class="crv-empty-icon">' + icon('<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>') + '</span>';
            empty.append(el('div', 'crv-empty-title', t('empty')), el('div', 'crv-empty-hint', t('emptyHint')));
            const emptyAdd = el('button', 'crv-btn crv-btn-primary', t('add'));
            emptyAdd.type = 'button';
            emptyAdd.addEventListener('click', openForm);
            empty.appendChild(emptyAdd);
            list.appendChild(empty);
            return;
        }
        state.items.forEach((item) => list.appendChild(renderCard(item)));
    }

    function renderCard(item) {
        const done = item.status !== 'open';
        const card = el('article', 'crv-card' + (done ? ' is-done' : ''));
        card.dataset.reviewId = String(item.id);

        const cardHead = el('div', 'crv-card-head');
        const avatar = el('span', 'crv-avatar', (String(item.author || '?').trim()[0] || '?').toUpperCase());
        const meta = el('div', 'crv-card-meta');
        meta.append(el('div', 'crv-author', item.author || ''));
        const lineBtn = el('button', 'crv-line', lineLabel(item));
        lineBtn.type = 'button';
        lineBtn.addEventListener('click', () => { revealLine(item.start_line); flashCard(item.id); });
        meta.appendChild(lineBtn);
        cardHead.append(avatar, meta);
        if (done) cardHead.appendChild(el('span', 'crv-status crv-status-' + item.status, t(item.status === 'accepted' ? 'accepted' : 'rejected')));
        card.appendChild(cardHead);

        if (item.quote) {
            const quote = el('blockquote', 'crv-quote', item.quote);
            card.appendChild(quote);
        }
        if (item.comment) card.appendChild(el('div', 'crv-comment', item.comment));
        if (item.suggestion !== null && item.suggestion !== undefined) {
            const sugg = el('div', 'crv-suggestion');
            sugg.append(el('div', 'crv-suggestion-label', t('suggest')), el('pre', 'crv-suggestion-text', item.suggestion));
            card.appendChild(sugg);
        }
        const actions = el('div', 'crv-card-actions');
        if (!done) {
            const accept = el('button', 'crv-btn crv-btn-ok', t('accept'));
            accept.type = 'button';
            accept.addEventListener('click', () => resolveItem(item, 'accepted'));
            const reject = el('button', 'crv-btn', t('reject'));
            reject.type = 'button';
            reject.addEventListener('click', () => resolveItem(item, 'rejected'));
            actions.append(accept, reject);
        }
        if (item.mine) {
            const del = el('button', 'crv-btn crv-btn-danger', t('del'));
            del.type = 'button';
            del.addEventListener('click', () => deleteItem(item));
            actions.appendChild(del);
        }
        if (actions.childElementCount) card.appendChild(actions);
        card.addEventListener('click', (e) => {
            if (e.target.closest('button')) return;
            revealLine(item.start_line);
        });
        return card;
    }

    function flashCard(id) {
        const card = list.querySelector('[data-review-id="' + String(id) + '"]');
        if (!card) return;
        card.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
        card.classList.remove('is-flash');
        void card.offsetWidth;
        card.classList.add('is-flash');
    }

    function focusCard(id) {
        setOpen(true);
        renderList();
        requestAnimationFrame(() => flashCard(id));
    }

    /* ----------------------------------------------------------------- api */

    function apiBase() {
        return (window.APP_CONFIG && window.APP_CONFIG.apiBase) || 'api.php';
    }

    async function ensureCsrf() {
        if (window.__cloudCsrf) return window.__cloudCsrf;
        try {
            const url = new URL(apiBase(), location.href);
            url.searchParams.set('action', 'get_state');
            const res = await fetch(url.toString(), { credentials: 'same-origin', cache: 'no-store' });
            if (res.ok) {
                const data = await res.json();
                if (data && data.csrf_token) window.__cloudCsrf = data.csrf_token;
            }
        } catch (_) {}
        return window.__cloudCsrf || '';
    }

    async function api(action, data) {
        const url = new URL(apiBase(), location.href);
        url.searchParams.set('action', action);
        const params = { action, path: state.path, root: state.root };
        Object.entries(data || {}).forEach(([k, v]) => {
            if (v !== undefined && v !== null) params[k] = String(v);
        });
        // Read-only actions use GET so they never depend on the CSRF check.
        if (action === 'review_list') {
            Object.entries(params).forEach(([k, v]) => url.searchParams.set(k, v));
            const res = await fetch(url.toString(), { credentials: 'same-origin', cache: 'no-store' });
            if (!res.ok) throw new Error('http ' + res.status);
            return res.json();
        }
        // Mutations must carry the same session CSRF token the rest of the app sends.
        const token = await ensureCsrf();
        const res = await fetch(url.toString(), {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                ...(token ? { 'X-CSRF-Token': token } : {})
            },
            body: new URLSearchParams(params)
        });
        if (!res.ok) throw new Error('http ' + res.status);
        return res.json();
    }

    async function load() {
        if (!state.path) return;
        try {
            const data = await api('review_list');
            if (data && data.ok) {
                state.items = Array.isArray(data.items) ? data.items : [];
                renderList();
                applyMarkers(state.items);
                if (state.items.length) {
                    const key = state.tabId + '|' + state.path;
                    if (!state.autoOpened.has(key)) {
                        state.autoOpened.add(key);
                        setOpen(true);
                    }
                }
            }
        } catch (_) {
            showToast(t('error'), true);
        }
    }

    async function resolveItem(item, status) {
        try {
            if (status === 'accepted' && item.suggestion !== null && item.suggestion !== undefined) {
                const tab = state.tab;
                let applied = true;
                if (tab && (tab.latexEditor || tab.markdownEditor || tab.htmlEditor)) {
                    const iframe = iframeOf(tab);
                    const result = await iframeCall(iframe && iframe.contentWindow, 'applySuggestion', { item }, 2500);
                    applied = !!(result && result.ok);
                } else if (tab) {
                    const ed = monacoEditor(tab);
                    applied = applySuggestionToEditor(ed, item);
                }
                if (!applied) { showToast(t('stale'), true); return; }
            }
            await api('review_resolve', { id: item.id, status });
            await load();
        } catch (_) { showToast(t('error'), true); }
    }

    function applySuggestionToEditor(ed, item) {
        if (!ed || !window.monaco) return false;
        try {
            const model = ed.getModel();
            const quote = String(item.quote || '');
            if (!quote) return false;
            const matches = model.findMatches(quote, false, false, true, null, false);
            if (!matches.length) return false;
            matches.sort((a, b) => Math.abs(a.range.startLineNumber - (Number(item.start_line) || 1)) - Math.abs(b.range.startLineNumber - (Number(item.start_line) || 1)));
            ed.executeEdits('review', [{ range: matches[0].range, text: String(item.suggestion || '') }]);
            ed.pushUndoStop();
            return true;
        } catch (_) { return false; }
    }

    async function deleteItem(item) {
        try {
            await api('review_delete', { id: item.id });
            await load();
        } catch (_) { showToast(t('error'), true); }
    }

    async function openForm() {
        setOpen(true);
        const sel = await getSelection();
        commentInput.value = '';
        suggestCheck.checked = false;
        suggestInput.hidden = true;
        suggestInput.value = sel ? (sel.quote || '') : '';
        form.dataset.start = sel ? String(sel.start) : '1';
        form.dataset.end = sel ? String(sel.end) : '1';
        form.dataset.quote = sel ? (sel.quote || '') : '';
        form.hidden = false;
        commentInput.focus();
    }

    async function submitForm() {
        const comment = commentInput.value.trim();
        const suggestion = suggestCheck.checked ? suggestInput.value : '';
        if (!comment && !suggestion.trim()) {
            showToast(t('commentRequired'), true);
            return;
        }
        try {
            await api('review_add', {
                start_line: form.dataset.start || 1,
                end_line: form.dataset.end || form.dataset.start || 1,
                quote: form.dataset.quote || '',
                comment,
                ...(suggestCheck.checked ? { suggestion } : {})
            });
            closeForm();
            await load();
            setOpen(true);
        } catch (_) { showToast(t('error'), true); }
    }

    form.addEventListener('submit', (e) => { e.preventDefault(); submitForm(); });
    cancelBtn.addEventListener('click', closeForm);
    suggestCheck.addEventListener('change', () => {
        suggestInput.hidden = !suggestCheck.checked;
        if (suggestCheck.checked) suggestInput.focus();
    });
    addBtn.addEventListener('click', openForm);
    closeBtn.addEventListener('click', () => setOpen(false));
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && state.open && form.hidden) setOpen(false);
    });

    /* ------------------------------------------------------------- lifecycle */

    function tabKey(tab) {
        return tab ? tab.id + '|' + tab.path + '|' + resolvedRoot(tab) : '';
    }

    function onTabChange(tab) {
        state.tab = tab;
        state.tabId = tab ? tab.id : null;
        state.items = [];
        state.path = tab ? String(tab.path || '') : '';
        state.root = tab ? resolvedRoot(tab) : 'main';
        closeForm();
        setOpen(false);
        renderList();
        if (tab && state.path) {
            load();
            setTimeout(() => applyMarkers(state.items), 800);
            setTimeout(() => applyMarkers(state.items), 1800);
        }
    }

    function reviewButton() {
        let btn = document.getElementById('btn-review');
        if (!btn) {
            btn = el('button', 'icon-btn hidden');
            btn.id = 'btn-review';
            btn.type = 'button';
            btn.title = t('title');
            btn.innerHTML = icon('<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/><line x1="8" y1="9" x2="16" y2="9"/><line x1="8" y1="13" x2="13" y2="13"/>');
            const badge = el('span', 'crv-badge');
            badge.id = 'btn-review-badge';
            badge.hidden = true;
            btn.appendChild(badge);
            const toolbar = document.querySelector('.file-toolbar');
            const ai = document.getElementById('btn-ai');
            if (toolbar) toolbar.insertBefore(btn, ai || null);
        }
        btn.title = t('title');
        btn.setAttribute('aria-label', t('title'));
        if (!btn.dataset.crvBound) {
            btn.dataset.crvBound = '1';
            btn.addEventListener('click', () => {
                if (!state.tab) return;
                setOpen(!state.open);
                if (state.open) load();
            });
        }
        return btn;
    }

    function syncActive() {
        const tab = activeTab();
        const key = tabKey(tab);
        const supported = isSupported(tab);
        const btn = reviewButton();
        btn.classList.toggle('hidden', !supported);
        if (!supported) {
            if (state.open) setOpen(false);
            if (state.lastKey !== key) {
                state.lastKey = key;
                state.tab = null;
                state.tabId = null;
                state.path = '';
            }
            return;
        }
        if (key !== state.lastKey) {
            state.lastKey = key;
            onTabChange(tab);
        }
    }

    function boot() {
        reviewButton();
        syncActive();
        setInterval(syncActive, 600);
        setInterval(() => {
            if (state.open && state.path) load();
        }, 15000);
        window.addEventListener('message', (ev) => {
            const d = ev.data;
            if (!d || d.source !== 'cloudReviewIframe') return;
            if (d.type === 'ready') {
                const tab = state.tab;
                if (!tab) return;
                const iframe = iframeOf(tab);
                if (iframe && iframe.contentWindow === ev.source) applyMarkers(state.items);
                return;
            }
            if (d.type === 'focus') {
                focusCard(d.id);
                if (d.line) revealLine(d.line);
            }
        });
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
    else boot();
})();
