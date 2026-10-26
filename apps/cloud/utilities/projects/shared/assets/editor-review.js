(function () {
    const q = new URLSearchParams(location.search);
    const path = q.get('path') || '';
    const root = q.get('root') || 'main';
    const lang = q.get('lang') || 'en';
    const light = q.get('theme') === 'light';
    const seg = location.pathname.split('/');
    const idx = seg.indexOf('utilities');
    const api = (idx > 0 ? seg.slice(0, idx).join('/') : '') + '/api.php';
    const T = {
        en: { review: 'Review', add: 'Add comment', placeholder: 'Comment...', suggest: 'Suggest replacement', save: 'Save', cancel: 'Cancel', accept: 'Accept', reject: 'Reject', del: 'Delete', empty: 'No reviews yet', lines: 'Lines', accepted: 'Accepted', rejected: 'Rejected', stale: 'Text has changed, cannot apply' },
        ru: { review: 'Ревью', add: 'Добавить комментарий', placeholder: 'Комментарий...', suggest: 'Предложить замену', save: 'Сохранить', cancel: 'Отмена', accept: 'Принять', reject: 'Отклонить', del: 'Удалить', empty: 'Пока нет ревью', lines: 'Строки', accepted: 'Принято', rejected: 'Отклонено', stale: 'Текст изменился, применить нельзя' },
        uk: { review: 'Ревʼю', add: 'Додати коментар', placeholder: 'Коментар...', suggest: 'Запропонувати заміну', save: 'Зберегти', cancel: 'Скасувати', accept: 'Прийняти', reject: 'Відхилити', del: 'Видалити', empty: 'Поки немає ревʼю', lines: 'Рядки', accepted: 'Прийнято', rejected: 'Відхилено', stale: 'Текст змінився, застосувати не можна' },
        be: { review: 'Рэцэнзія', add: 'Дадаць каментарый', placeholder: 'Каментарый...', suggest: 'Прапанаваць замену', save: 'Захаваць', cancel: 'Скасаваць', accept: 'Прыняць', reject: 'Адхіліць', del: 'Выдаліць', empty: 'Пакуль няма рэцэнзій', lines: 'Радкі', accepted: 'Прынята', rejected: 'Адхілена', stale: 'Тэкст змяніўся, ужыць нельга' }
    };
    const t = k => (T[lang] || T.en)[k];
    const el = (tag, cls, text, kids) => {
        const e = document.createElement(tag);
        if (cls) e.className = cls;
        if (text) e.textContent = text;
        (kids || []).forEach(k => e.appendChild(k));
        return e;
    };
    const btn = (cls, html, fn, title) => {
        const b = el('button', cls);
        b.type = 'button';
        b.innerHTML = html;
        if (title) b.title = title;
        b.addEventListener('click', fn);
        return b;
    };
    async function call(action, data) {
        const headers = { 'Content-Type': 'application/x-www-form-urlencoded' };
        try {
            const csrf = (window.parent && window.parent.APP_CONFIG && window.parent.APP_CONFIG.csrfToken) || (window.APP_CONFIG && window.APP_CONFIG.csrfToken) || '';
            if (csrf) headers['X-CSRF-TOKEN'] = csrf;
        } catch (_) {}
        const r = await fetch(api + '?action=' + action + '&root=' + encodeURIComponent(root), {
            method: 'POST',
            credentials: 'same-origin',
            headers,
            body: new URLSearchParams({ action, path, root, ...(data || {}) })
        });
        if (!r.ok) throw new Error('http ' + r.status);
        return r.json();
    }
    const style = el('style');
    style.textContent = '.rv-panel{position:fixed;top:49px;right:0;bottom:0;width:min(340px,100vw);z-index:2500;display:none;flex-direction:column;background:' + (light ? '#fff' : '#252526') + ';color:' + (light ? '#333' : '#e0e0e0') + ';border-left:1px solid ' + (light ? '#ddd' : '#444') + ';box-shadow:-4px 0 16px rgba(0,0,0,.35);font:13px/1.4 "Segoe UI",sans-serif}.rv-panel.show{display:flex}.rv-head{display:flex;align-items:center;justify-content:space-between;padding:8px 10px;border-bottom:1px solid ' + (light ? '#ddd' : '#444') + ';font-weight:600}.rv-list{flex:1;overflow:auto;padding:8px;display:flex;flex-direction:column;gap:8px}.rv-card{border:1px solid ' + (light ? '#ddd' : '#444') + ';border-radius:6px;padding:8px;display:flex;flex-direction:column;gap:6px}.rv-card.done{opacity:.6}.rv-meta{display:flex;justify-content:space-between;gap:6px;cursor:pointer;font-size:12px;opacity:.8}.rv-quote{border-left:3px solid #e0a800;padding-left:6px;font-style:italic;white-space:pre-wrap;max-height:80px;overflow:auto}.rv-sugg{background:rgba(46,125,50,.2);border:1px solid #2e7d32;border-radius:4px;padding:4px 6px;white-space:pre-wrap;max-height:100px;overflow:auto;font-family:monospace}.rv-actions{display:flex;gap:4px;flex-wrap:wrap}.rv-form{display:none;flex-direction:column;gap:6px;padding:8px;border-top:1px solid ' + (light ? '#ddd' : '#444') + '}.rv-form.show{display:flex}.rv-form textarea{width:100%;min-height:60px;resize:vertical;background:' + (light ? '#fff' : '#1e1e1e') + ';color:inherit;border:1px solid ' + (light ? '#ccc' : '#444') + ';border-radius:4px;padding:6px;font:inherit;box-sizing:border-box}.rv-badge{margin-left:4px;font-size:11px}.rv-line{background:rgba(224,168,0,.14)}.rv-empty{opacity:.6;text-align:center;padding:16px}';
    document.head.appendChild(style);
    const list = el('div', 'rv-list');
    const comment = el('textarea');
    comment.placeholder = t('placeholder');
    const suggestToggle = el('input');
    suggestToggle.type = 'checkbox';
    const suggestText = el('textarea');
    suggestText.style.display = 'none';
    const form = el('div', 'rv-form');
    const panel = el('div', 'rv-panel');
    const badge = el('span', 'rv-badge');
    const toolbarBtn = btn('toolbar-btn formatting-btn', '<i class="fas fa-comment-dots"></i>', () => toggle(), t('review'));
    toolbarBtn.id = 'reviewBtn';
    toolbarBtn.appendChild(badge);
    let editor = null;
    let deco = null;
    let items = [];
    let pending = null;
    let timer = null;
    function toast(msg) {
        window.parent && window.parent !== window && window.parent.postMessage({ type: 'htmlEditorStatus', message: msg, isError: true }, '*');
    }
    function selectionInfo() {
        const model = editor.getModel();
        let sel = editor.getSelection();
        let endLine = sel.endLineNumber;
        if (sel.endColumn === 1 && endLine > sel.startLineNumber) endLine--;
        const text = sel.isEmpty() ? model.getLineContent(sel.startLineNumber) : model.getValueInRange(sel);
        return { start: sel.startLineNumber, end: endLine, quote: text };
    }
    function openForm() {
        if (!editor) return;
        pending = selectionInfo();
        comment.value = '';
        suggestToggle.checked = false;
        suggestText.style.display = 'none';
        suggestText.value = pending.quote;
        form.classList.add('show');
        comment.focus();
    }
    function closeForm() {
        form.classList.remove('show');
        pending = null;
    }
    async function submit() {
        if (!pending) return;
        const data = { start_line: pending.start, end_line: pending.end, quote: pending.quote, comment: comment.value };
        if (suggestToggle.checked) data.suggestion = suggestText.value;
        await call('review_add', data);
        closeForm();
        refresh();
    }
    async function resolve(item, status) {
        if (status === 'accepted' && item.suggestion !== null && editor) {
            const model = editor.getModel();
            const matches = model.findMatches(item.quote, false, false, true, null, false);
            matches.sort((a, b) => Math.abs(a.range.startLineNumber - item.start_line) - Math.abs(b.range.startLineNumber - item.start_line));
            if (!matches.length) { toast(t('stale')); return; }
            editor.executeEdits('review', [{ range: matches[0].range, text: item.suggestion }]);
        }
        await call('review_resolve', { id: item.id, status });
        refresh();
    }
    async function remove(item) {
        await call('review_delete', { id: item.id });
        refresh();
    }
    function card(item) {
        const done = item.status !== 'open';
        const meta = el('div', 'rv-meta', null, [el('span', '', item.author), el('span', '', t('lines') + ' ' + item.start_line + (item.end_line > item.start_line ? '-' + item.end_line : ''))]);
        meta.addEventListener('click', () => { if (editor) { editor.revealLineInCenter(item.start_line); editor.setPosition({ lineNumber: item.start_line, column: 1 }); } });
        const c = el('div', 'rv-card' + (done ? ' done' : ''), null, [meta]);
        if (item.quote) c.appendChild(el('div', 'rv-quote', item.quote));
        if (item.comment) c.appendChild(el('div', '', item.comment));
        if (item.suggestion !== null) c.appendChild(el('div', 'rv-sugg', item.suggestion));
        const actions = el('div', 'rv-actions');
        if (done) actions.appendChild(el('span', '', t(item.status) + (item.resolved_by ? ' · ' + item.resolved_by : '')));
        else {
            actions.appendChild(btn('toolbar-btn primary', t('accept'), () => resolve(item, 'accepted')));
            actions.appendChild(btn('toolbar-btn danger', t('reject'), () => resolve(item, 'rejected')));
        }
        if (item.mine) actions.appendChild(btn('toolbar-btn', '<i class="fas fa-trash"></i>', () => remove(item), t('del')));
        c.appendChild(actions);
        return c;
    }
    function render() {
        list.replaceChildren(...(items.length ? items.map(card) : [el('div', 'rv-empty', t('empty'))]));
        const open = items.filter(i => i.status === 'open');
        badge.textContent = open.length ? open.length : '';
        if (editor && window.monaco) {
            const decos = open.map(i => ({ range: new monaco.Range(i.start_line, 1, i.end_line, 1), options: { isWholeLine: true, className: 'rv-line' } }));
            if (deco) deco.set(decos);
            else deco = editor.createDecorationsCollection(decos);
        }
    }
    async function refresh() {
        try {
            const r = await call('review_list');
            if (r && r.ok) { items = r.items; render(); }
        } catch (_) {}
    }
    function toggle() {
        const show = !panel.classList.contains('show');
        panel.classList.toggle('show', show);
        clearInterval(timer);
        if (show) { refresh(); timer = setInterval(refresh, 15000); }
    }
    form.append(comment, el('label', '', null, [suggestToggle, el('span', '', ' ' + t('suggest'))]), suggestText, el('div', 'rv-actions', null, [btn('toolbar-btn primary', t('save'), submit), btn('toolbar-btn', t('cancel'), closeForm)]));
    suggestToggle.addEventListener('change', () => { suggestText.style.display = suggestToggle.checked ? '' : 'none'; });
    panel.append(el('div', 'rv-head', null, [el('span', '', t('review')), btn('toolbar-btn', '<i class="fas fa-plus"></i> ' + t('add'), openForm)]), list, form);
    document.body.appendChild(panel);
    const tb = document.querySelector('.editor-toolbar');
    if (tb) tb.appendChild(el('div', 'toolbar-group', null, [toolbarBtn]));
    const wait = setInterval(() => {
        const e = window.monaco && monaco.editor.getEditors && monaco.editor.getEditors()[0];
        if (!e) return;
        clearInterval(wait);
        editor = e;
        refresh();
    }, 400);
    setTimeout(() => clearInterval(wait), 60000);
})();
