<script src="/shell/sharepoint/assets/js/qr-code.js?v=5"></script>
<script src="/shell/sharepoint/share_i18n.php?lang=<?php echo htmlspecialchars($language ?? 'en', ENT_QUOTES); ?>"></script>
<?php if (empty($_SESSION['sharepoint_csrf'])) $_SESSION['sharepoint_csrf'] = bin2hex(random_bytes(24)); ?>
<script>window.SHAREPOINT_CSRF=<?= json_encode($_SESSION['sharepoint_csrf']) ?>;</script>
<script src="/shell/sharepoint/assets/js/share-modal.js?v=15"></script>
<script>
<?php $jsonFlags = JSON_HEX_TAG | JSON_INVALID_UTF8_SUBSTITUTE; ?>
const translations = <?= json_encode($translations, $jsonFlags) ?: '{}' ?>;
const currentLang = <?= json_encode($language, $jsonFlags) ?: '"en"' ?>;
const ocrI18n={en:{waiting:'Waiting for OCR start...',done:'OCR completed',recognized:'recognized',unresolved:'unresolved',warning:'Warning',autoAdded:'Auto-added to database',manualAdded:'Added after manual mapping',dbAdded:'OCR data added to database',jobCreated:'Job created',baseCurrency:'Accounting currency',statusError:'Status error',ocrError:'OCR error',startError:'Start error'},ru:{waiting:'Ожидание запуска OCR...',done:'OCR завершен',recognized:'распознано',unresolved:'нераспознано',warning:'Предупреждение',autoAdded:'Автоматически добавлено в базу',manualAdded:'Добавлено после ручного сопоставления',dbAdded:'OCR данные добавлены в базу',jobCreated:'Задача создана',baseCurrency:'Валюта учета',statusError:'Ошибка статуса',ocrError:'Ошибка OCR',startError:'Ошибка запуска'},uk:{waiting:'Очікування запуску OCR...',done:'OCR завершено',recognized:'розпізнано',unresolved:'нерозпізнано',warning:'Попередження',autoAdded:'Автоматично додано в базу',manualAdded:'Додано після ручного зіставлення',dbAdded:'OCR дані додано в базу',jobCreated:'Задачу створено',baseCurrency:'Валюта обліку',statusError:'Помилка статусу',ocrError:'Помилка OCR',startError:'Помилка запуску'}};
const ocrT=ocrI18n[currentLang]||ocrI18n.en;
let expensesData = <?= json_encode($allStoresExpenses ?? [], $jsonFlags) ?: '{}' ?>;
let incomesData  = <?= json_encode($allIncomes ?? [], $jsonFlags) ?: '{}' ?>;
let allDates     = <?= json_encode($allDates ?? [], $jsonFlags) ?: '[]' ?>;
// {date: {store: {source: 'manual'|'ocr'|..., note: 'raw notification'}}}
let expenseActivityMeta = <?= json_encode($expenseActivityMeta ?? [], $jsonFlags) ?: '{}' ?>;
let userSettings = <?= json_encode($userSettings, $jsonFlags) ?: '{}' ?>;
let currentUser  = <?= json_encode($currentUser, $jsonFlags) ?: '""' ?>;
const currentUserFolder = <?= json_encode($currentUserFolder, $jsonFlags) ?: '""' ?>;
let currentChartYear = <?= date('Y') ?>;
let chartMode    = 'total';
let chart;
let ocrAnalyticsChart=null;
let availableYears=[];
let analyticsYear=(new Date()).getFullYear();
let cropper;
let currentCropType = '';
let currentCropId   = '';
let saveTimeout;
let tableSettings = <?= json_encode($tableSettings, $jsonFlags) ?: '{}' ?>;
let pendingDelete = { type:'', id:'', name:'' };
let pendingConfirmAction=null;
let inputPromptResolver=null;
let totalRowsDB   = <?= (int)$totalRows ?>;
let tablePageInfo = { page: <?= (int)($tablePage ?? 1) ?>, perPage: <?= (int)($tablePerPage ?? 20) ?>, total: <?= (int)($totalRows ?? 0) ?>, totalPages: <?= (int)($tableTotalPages ?? 1) ?> };
const tableYearMin = <?= (int)($tableMinYear ?? date('Y')) ?>;
const tableYearMax = <?= (int)($tableMaxYear ?? date('Y')) ?>;
let pendingScrollDate = null;
let shareChart    = null;
let currentRates  = {};

const storeColors = ['#FF6384','#36A2EB','#FFCE56','#4BC0C0','#9966FF','#FF9F40','rgba(6,127,0,1)','#C9CBCF','#4BC0C0','#FFCD56','#C9CBCF','#FF6384'];
const monthNames  = <?= json_encode(($monthNames[$language] ?? $monthNames['en']), $jsonFlags) ?: '[]' ?>;
const t=(key,fallback='')=>translations[key]||fallback;
const categoryLabel=key=>t('store_category_'+key,key);
function money(v){return `${(Number(v)||0).toFixed(2)} ${userSettings.currency||''}`.trim();}
function updateFinancialSummary(){
    const income=Object.values(incomesData||{}).reduce((s,v)=>s+(Number(v)||0),0);
    const expenses=Object.values(expensesData||{}).reduce((s,row)=>s+Object.values(row||{}).reduce((a,v)=>a+(Number(v)||0),0),0);
    const values={income,expenses,balance:income-expenses};
    const map={income:'financial-income',expenses:'financial-expenses',balance:'financial-balance'};
    Object.keys(map).forEach(k=>{const el=document.getElementById(map[k]);if(el)el.textContent=money(values[k]);});
}

// ══ Modal helpers ═══════════════════════════════════════════════════════════
function syncModalBodyState(){document.body.classList.toggle('modal-open',!!document.querySelector('.modal[style*="display: flex"]'));}
function setupModalChrome(){document.querySelectorAll('.modal-content:not(.tx-sheet):not(.history-sheet)').forEach(content=>{if(content.querySelector(':scope > .modal-topbar'))return;const close=content.querySelector(':scope > .modal-close'),title=Array.from(content.children).find(el=>/H[23]/.test(el.tagName)),topbar=document.createElement('div'),label=title||document.createElement('div'),body=document.createElement('div');topbar.className='modal-topbar';label.classList.add('modal-topbar-title');body.className='modal-body';topbar.appendChild(label);if(close)topbar.appendChild(close);Array.from(content.childNodes).forEach(node=>{if(node!==topbar&&node!==close&&node!==label)body.appendChild(node);});content.appendChild(topbar);content.appendChild(body);});}
function showModal(id) { setupModalChrome(); document.getElementById(id).style.display='flex'; syncModalBodyState(); }
function closeModal(id) { document.getElementById(id).style.display='none'; syncModalBodyState(); }
function confirmWithModal(message,action,confirmLabel=t('delete','Delete')){
    pendingConfirmAction=action;
    document.getElementById('confirm-delete-title').textContent=t('confirm_action_title','Confirm action');
    document.getElementById('confirm-delete-message').textContent=message;
    document.getElementById('confirm-delete-action').textContent=confirmLabel;
    showModal('confirm-delete-modal');
}
function cancelConfirmation(){
    pendingConfirmAction=null;
    closeModal('confirm-delete-modal');
}
function requestNumberInput(title,label,value=''){
    return new Promise(resolve=>{
        inputPromptResolver=resolve;
        document.getElementById('input-prompt-title').textContent=title;
        document.getElementById('input-prompt-label').textContent=label;
        const input=document.getElementById('input-prompt-value');
        input.value=value;
        showModal('input-prompt-modal');
        input.focus();
    });
}
function resolveInputPrompt(value){
    closeModal('input-prompt-modal');
    const resolve=inputPromptResolver;
    inputPromptResolver=null;
    if(resolve)resolve(value==null?null:String(value));
}

// ══ Custom dropdowns (every native select) ═════════════════════════════════
// The concept renders dropdowns as a button plus a menu, so every <select> in
// the app is replaced by that component while the native element stays in the
// DOM as the single source of truth for form submits and existing listeners.
const CS_PAGE_SIZE = 8;
const CS_SVG_NS = 'http://www.w3.org/2000/svg';

function csShouldSkip(select) {
    return !select || select.multiple || select.hidden
        || select.getAttribute('aria-hidden') === 'true'
        || select.dataset.nativeSelect === '1';
}

function csOptions(select) {
    return Array.from(select.options);
}

function csSync(select) {
    const ui = select._cs;
    if (!ui) return;
    const selected = select.selectedOptions[0];
    ui.label.textContent = selected ? selected.textContent.trim() : '';
    ui.trigger.disabled = select.disabled;
    ui.trigger.setAttribute('aria-label', selected ? selected.textContent.trim() : '');
}

function csCloseOthers(keep) {
    document.querySelectorAll('.cs-select.is-open').forEach(wrap => {
        if (wrap === keep) return;
        wrap.classList.remove('is-open');
        const menu = wrap._csMenu;
        const trigger = wrap.querySelector('.cs-trigger');
        if (menu) menu.hidden = true;
        if (trigger) trigger.setAttribute('aria-expanded', 'false');
    });
}

function csChoose(select, value) {
    if (select.value === value) { select._cs.close(); return; }
    select.value = value;
    select.dispatchEvent(new Event('change', { bubbles: true }));
    csSync(select);
    select._cs.close();
    select._cs.trigger.focus();
}

function enhanceSelect(select) {
    if (!select || select.dataset.csReady === '1' || csShouldSkip(select)) return;
    select.dataset.csReady = '1';
    const wrap = document.createElement('span');
    wrap.className = 'cs-select' + (select.classList.contains('manual-cal-select') ? ' cs-inline' : '');
    select.parentNode.insertBefore(wrap, select);
    wrap.appendChild(select);
    select.classList.add('cs-native');

    const trigger = document.createElement('button');
    trigger.type = 'button';
    trigger.className = 'cs-trigger';
    trigger.setAttribute('aria-haspopup', 'listbox');
    trigger.setAttribute('aria-expanded', 'false');
    const label = document.createElement('span');
    label.className = 'cs-label';
    const chevron = document.createElementNS(CS_SVG_NS, 'svg');
    chevron.setAttribute('viewBox', '0 0 24 24');
    chevron.setAttribute('class', 'cs-chevron');
    chevron.setAttribute('aria-hidden', 'true');
    const polyline = document.createElementNS(CS_SVG_NS, 'polyline');
    polyline.setAttribute('points', '6 9 12 15 18 9');
    polyline.setAttribute('fill', 'none');
    polyline.setAttribute('stroke', 'currentColor');
    polyline.setAttribute('stroke-width', '2');
    polyline.setAttribute('stroke-linecap', 'round');
    polyline.setAttribute('stroke-linejoin', 'round');
    chevron.appendChild(polyline);
    trigger.append(label, chevron);

    const menu = document.createElement('div');
    menu.className = 'cs-menu';
    menu.hidden = true;
    const list = document.createElement('div');
    list.className = 'cs-list';
    list.setAttribute('role', 'listbox');
    const pager = document.createElement('div');
    pager.className = 'cs-pager';
    pager.hidden = true;
    menu.append(list, pager);
    wrap.append(trigger);
    // The menu lives on <body>: a modal with backdrop-filter becomes the
    // containing block for position:fixed, which would offset the menu.
    document.body.appendChild(menu);

    const ui = {
        wrap, trigger, label, menu, list, pager, page: 0,
        open() {
            csCloseOthers(wrap);
            const options = csOptions(select);
            const selectedIndex = options.findIndex(option => option.value === select.value);
            ui.page = selectedIndex >= 0 ? Math.floor(selectedIndex / CS_PAGE_SIZE) : 0;
            wrap.classList.add('is-open');
            trigger.setAttribute('aria-expanded', 'true');
            menu.hidden = false;
            ui.render(selectedIndex);
            ui.position();
            // Fonts and scrollbars settle one frame later; re-measure then.
            requestAnimationFrame(() => { if (wrap.classList.contains('is-open')) ui.position(); });
            const active = list.querySelector('.cs-option.is-active');
            if (active) active.focus({ preventScroll: true }); else trigger.focus();
        },
        position() {
            const rect = trigger.getBoundingClientRect();
            const margin = 8;
            const preferred = Math.min(280, window.innerHeight * 0.46);
            const spaceBelow = window.innerHeight - rect.bottom - margin;
            const spaceAbove = rect.top - margin;
            const openUp = spaceBelow < Math.min(180, preferred) && spaceAbove > spaceBelow;
            const available = Math.max(140, openUp ? spaceAbove : spaceBelow);
            menu.style.maxHeight = Math.min(preferred, available) + 'px';
            menu.style.width = rect.width + 'px';
            menu.style.left = Math.max(margin, Math.min(rect.left, window.innerWidth - rect.width - margin)) + 'px';
            menu.style.top = openUp
                ? Math.max(margin, rect.top - Math.min(menu.offsetHeight || preferred, available) - 6) + 'px'
                : (rect.bottom + 6) + 'px';
        },
        close() {
            wrap.classList.remove('is-open');
            trigger.setAttribute('aria-expanded', 'false');
            menu.hidden = true;
        },
        render(focusIndex) {
            const options = csOptions(select);
            const pageCount = Math.max(1, Math.ceil(options.length / CS_PAGE_SIZE));
            ui.page = Math.max(0, Math.min(ui.page, pageCount - 1));
            const start = ui.page * CS_PAGE_SIZE;
            list.replaceChildren();
            if (!options.length) {
                const empty = document.createElement('div');
                empty.className = 'cs-empty';
                empty.textContent = t('no_data', 'No data');
                list.appendChild(empty);
            }
            options.slice(start, start + CS_PAGE_SIZE).forEach((option, index) => {
                const item = document.createElement('button');
                item.type = 'button';
                item.className = 'cs-option';
                item.setAttribute('role', 'option');
                item.setAttribute('aria-selected', option.value === select.value ? 'true' : 'false');
                if (option.disabled) item.disabled = true;
                const text = document.createElement('span');
                text.className = 'cs-option-label';
                text.textContent = option.textContent.trim();
                item.appendChild(text);
                if (option.value === select.value) {
                    const dot = document.createElement('span');
                    dot.className = 'cs-option-dot';
                    item.appendChild(dot);
                }
                item.onclick = () => csChoose(select, option.value);
                if (focusIndex !== undefined && start + index === focusIndex) item.classList.add('is-active');
                list.appendChild(item);
            });
            pager.hidden = pageCount <= 1;
            if (pageCount > 1) {
                pager.replaceChildren();
                const previous = document.createElement('button');
                previous.type = 'button';
                previous.textContent = '‹';
                previous.setAttribute('aria-label', t('settings_previous', 'Previous page'));
                previous.disabled = ui.page === 0;
                previous.onclick = () => { ui.page--; ui.render(); };
                const status = document.createElement('span');
                status.className = 'cs-pager-status';
                status.textContent = t('settings_page', '%1$d of %2$d')
                    .replace('%1$d', String(ui.page + 1)).replace('%2$d', String(pageCount));
                const next = document.createElement('button');
                next.type = 'button';
                next.textContent = '›';
                next.setAttribute('aria-label', t('settings_next', 'Next page'));
                next.disabled = ui.page === pageCount - 1;
                next.onclick = () => { ui.page++; ui.render(); };
                pager.append(previous, status, next);
            }
        },
        move(delta) {
            const options = csOptions(select);
            if (!options.length) return;
            const items = Array.from(list.querySelectorAll('.cs-option:not(:disabled)'));
            if (!items.length) return;
            const current = items.indexOf(document.activeElement);
            const nextIndex = Math.max(0, Math.min(items.length - 1, current + delta));
            items[nextIndex].focus();
        }
    };
    select._cs = ui;
    wrap._csMenu = menu;
    csSync(select);

    trigger.addEventListener('click', event => {
        event.stopPropagation();
        if (wrap.classList.contains('is-open')) ui.close(); else ui.open();
    });
    trigger.addEventListener('keydown', event => {
        if (['ArrowDown', 'ArrowUp', 'Enter', ' '].includes(event.key)) {
            event.preventDefault();
            ui.open();
        }
    });
    menu.addEventListener('keydown', event => {
        if (event.key === 'Escape') {
            // Escape closes the list, not the modal behind it.
            event.preventDefault();
            event.stopPropagation();
            ui.close();
            trigger.focus();
        }
        else if (event.key === 'ArrowDown') { event.preventDefault(); ui.move(1); }
        else if (event.key === 'ArrowUp') { event.preventDefault(); ui.move(-1); }
        else if (event.key === 'PageDown') { event.preventDefault(); ui.page++; ui.render(); }
        else if (event.key === 'PageUp') { event.preventDefault(); ui.page--; ui.render(); }
        else if (event.key === 'Home' || event.key === 'End') {
            event.preventDefault();
            const items = Array.from(list.querySelectorAll('.cs-option:not(:disabled)'));
            if (items.length) items[event.key === 'Home' ? 0 : items.length - 1].focus();
        }
    });
    select.addEventListener('change', () => csSync(select));
    // Options are filled by other code (stores, currencies, analytics), so the
    // trigger label follows the native element instead of caching the text.
    new MutationObserver(() => csSync(select)).observe(select, { childList: true, subtree: true, characterData: true });
}

function enhanceAllSelects(root) {
    const scope = root || document;
    if (scope.tagName === 'SELECT') enhanceSelect(scope);
    (scope.querySelectorAll ? scope.querySelectorAll('select') : []).forEach(enhanceSelect);
}

window.syncCustomSelects = function () {
    enhanceAllSelects(document);
    document.querySelectorAll('select[data-cs-ready="1"]').forEach(csSync);
};

function initCustomSelects() {
    enhanceAllSelects(document);
    new MutationObserver(mutations => {
        mutations.forEach(mutation => {
            mutation.addedNodes.forEach(node => {
                if (node.nodeType === 1) enhanceAllSelects(node);
            });
        });
    }).observe(document.body, { childList: true, subtree: true });
    document.addEventListener('mousedown', event => {
        if (!event.target.closest('.cs-select') && !event.target.closest('.cs-menu')) csCloseOthers(null);
    }, true);
    // A fixed-position menu cannot follow a scroll, so any scroll closes it.
    window.addEventListener('scroll', event => {
        if (event.target instanceof Element && event.target.closest && event.target.closest('.cs-menu')) return;
        csCloseOthers(null);
    }, true);
    window.addEventListener('resize', () => csCloseOthers(null));
}

// ══ Custom calendar field ══════════════════════════════════════════════════
// Every <input type="date"> is replaced by a styled trigger plus a month grid,
// so the native browser popup (which cannot be themed) never shows. The native
// input stays in the DOM as the value holder for forms and existing handlers.
const CAL_SVG_NS = 'http://www.w3.org/2000/svg';
let openCalendar = null;

function calYmd(date) {
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
}
function calParse(value) {
    if (!/^\d{4}-\d{2}-\d{2}$/.test(value || '')) return null;
    const [y, m, d] = value.split('-').map(Number);
    const date = new Date(y, m - 1, d);
    return Number.isNaN(date.getTime()) ? null : date;
}
function calFormat(input, date) {
    if (!date) return '';
    try {
        return new Intl.DateTimeFormat(currentLang || 'en', { day: '2-digit', month: 'short', year: 'numeric' }).format(date);
    } catch (_) {
        return calYmd(date);
    }
}
function calBounds(input) {
    const min = calParse(input.min);
    const max = calParse(input.max);
    return { min, max };
}
function calLabelFor(input) {
    const date = calParse(input.value);
    return calFormat(input, date);
}

function closeCalendar() {
    if (!openCalendar) return;
    openCalendar.pop.hidden = true;
    openCalendar.input.closest('.cal-field-wrap')?.classList.remove('is-open');
    openCalendar = null;
}
function csCloseForCalendar() {
    if (typeof csCloseOthers === 'function') csCloseOthers(null);
}

function enhanceDateField(input) {
    if (!input || input.dataset.calReady === '1' || input.dataset.nativeDate === '1') return;
    if (input.closest('#data-table')) return;
    input.dataset.calReady = '1';

    const wrap = document.createElement('span');
    wrap.className = 'cal-field-wrap';
    input.parentNode.insertBefore(wrap, input);
    wrap.appendChild(input);
    input.classList.add('cal-native');
    if (!input.type) input.type = 'date';

    const trigger = document.createElement('button');
    trigger.type = 'button';
    trigger.className = 'cal-trigger';
    trigger.setAttribute('aria-haspopup', 'dialog');
    const label = document.createElement('span');
    label.className = 'cal-label';
    const icon = document.createElementNS(CAL_SVG_NS, 'svg');
    icon.setAttribute('viewBox', '0 0 24 24');
    icon.setAttribute('class', 'cal-icon');
    icon.setAttribute('aria-hidden', 'true');
    icon.setAttribute('fill', 'none');
    icon.setAttribute('stroke', 'currentColor');
    icon.setAttribute('stroke-width', '2');
    icon.setAttribute('stroke-linecap', 'round');
    icon.setAttribute('stroke-linejoin', 'round');
    [['rect', { x: 3, y: 4.5, width: 18, height: 17, rx: 2.5 }], ['line', { x1: 16, y1: 2.5, x2: 16, y2: 6.5 }], ['line', { x1: 8, y1: 2.5, x2: 8, y2: 6.5 }], ['line', { x1: 3, y1: 10, x2: 21, y2: 10 }]]
        .forEach(([tag, attrs]) => {
            const node = document.createElementNS(CAL_SVG_NS, tag);
            Object.entries(attrs).forEach(([name, value]) => node.setAttribute(name, String(value)));
            icon.appendChild(node);
        });
    trigger.append(label, icon);
    wrap.appendChild(trigger);

    const pop = document.createElement('div');
    pop.className = 'cal-pop';
    pop.hidden = true;
    pop.setAttribute('role', 'dialog');
    pop.innerHTML = `
        <div class="cal-pop-head">
            <button type="button" class="cal-nav" data-dir="-1" aria-label="${t('prev_month', 'Previous month')}">‹</button>
            <div class="cal-selects">
                <select class="cal-month" aria-label="${t('month_label', 'Month')}" data-native-select="0"></select>
                <select class="cal-year" aria-label="${t('year_label', 'Year')}" data-native-select="0"></select>
            </div>
            <button type="button" class="cal-nav" data-dir="1" aria-label="${t('next_month', 'Next month')}">›</button>
        </div>
        <div class="cal-dows"></div>
        <div class="cal-grid"></div>
        <div class="cal-foot">
            <button type="button" class="cal-today">${t('today', 'Today')}</button>
            <button type="button" class="cal-clear">${t('calendar_clear', 'Clear')}</button>
        </div>`;
    // Same containment problem as the dropdown: keep the popup on <body>.
    document.body.appendChild(pop);

    const monthSelect = pop.querySelector('.cal-month');
    const yearSelect = pop.querySelector('.cal-year');
    const grid = pop.querySelector('.cal-grid');
    const dows = pop.querySelector('.cal-dows');
    const navButtons = pop.querySelectorAll('.cal-nav');

    monthNames.forEach((name, index) => {
        const option = document.createElement('option');
        option.value = String(index);
        option.textContent = name;
        monthSelect.appendChild(option);
    });

    const dowsList = (() => {
        const monday = new Date(2024, 0, 1);
        return Array.from({ length: 7 }, (_, index) => {
            const day = new Date(monday);
            day.setDate(monday.getDate() + index);
            try { return new Intl.DateTimeFormat(currentLang || 'en', { weekday: 'short' }).format(day); }
            catch (_) { return ['Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa', 'Su'][index]; }
        });
    })();
    dows.replaceChildren();
    dowsList.forEach(name => {
        const cell = document.createElement('span');
        cell.className = 'cal-dow';
        cell.textContent = name;
        dows.appendChild(cell);
    });

    let viewYear = 0;
    let viewMonth = 0;

    function fillYears() {
        const { min, max } = calBounds(input);
        const nowYear = new Date().getFullYear();
        const first = min ? min.getFullYear() : Math.min(1900, nowYear);
        const last = max ? max.getFullYear() : Math.max(nowYear, viewYear);
        const previous = yearSelect.value;
        yearSelect.replaceChildren();
        for (let year = first; year <= last; year++) {
            const option = document.createElement('option');
            option.value = String(year);
            option.textContent = String(year);
            yearSelect.appendChild(option);
        }
        yearSelect.value = previous && Number(previous) >= first && Number(previous) <= last ? previous : String(viewYear);
        if (typeof csSync === 'function' && yearSelect._cs) csSync(yearSelect);
        if (typeof csSync === 'function' && monthSelect._cs) csSync(monthSelect);
    }

    function render() {
        const { min, max } = calBounds(input);
        const selected = calParse(input.value);
        const today = new Date();
        const todayYmd = calYmd(today);
        monthSelect.value = String(viewMonth);
        yearSelect.value = String(viewYear);
        if (monthSelect._cs) csSync(monthSelect);
        if (yearSelect._cs) csSync(yearSelect);

        grid.replaceChildren();
        const firstWeekday = (new Date(viewYear, viewMonth, 1).getDay() + 6) % 7;
        for (let i = 0; i < firstWeekday; i++) {
            const blank = document.createElement('span');
            blank.className = 'cal-blank';
            grid.appendChild(blank);
        }
        const days = new Date(viewYear, viewMonth + 1, 0).getDate();
        for (let day = 1; day <= days; day++) {
            const date = new Date(viewYear, viewMonth, day);
            const ymd = calYmd(date);
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'cal-day';
            button.textContent = String(day);
            button.dataset.date = ymd;
            button.disabled = (min && date < min) || (max && date > max);
            if (ymd === todayYmd) button.classList.add('is-today');
            if (selected && ymd === calYmd(selected)) button.setAttribute('aria-selected', 'true');
            button.onclick = () => pick(ymd);
            grid.appendChild(button);
        }
    }

    function position() {
        const rect = trigger.getBoundingClientRect();
        const margin = 8;
        const width = 300;
        const height = pop.offsetHeight || 340;
        const spaceBelow = window.innerHeight - rect.bottom - margin;
        const openUp = spaceBelow < height && rect.top - margin > spaceBelow;
        pop.style.width = Math.min(width, window.innerWidth - margin * 2) + 'px';
        pop.style.left = Math.max(margin, Math.min(rect.left, window.innerWidth - width - margin)) + 'px';
        pop.style.top = openUp ? Math.max(margin, rect.top - height - 6) + 'px' : rect.bottom + 6 + 'px';
    }

    function open() {
        closeCalendar();
        csCloseForCalendar();
        const selected = calParse(input.value) || new Date();
        viewYear = selected.getFullYear();
        viewMonth = selected.getMonth();
        const { max } = calBounds(input);
        if (max && new Date(viewYear, viewMonth, 1) > max) {
            viewYear = max.getFullYear();
            viewMonth = max.getMonth();
        }
        fillYears();
        render();
        pop.hidden = false;
        wrap.classList.add('is-open');
        trigger.setAttribute('aria-expanded', 'true');
        openCalendar = { input, pop };
        position();
        requestAnimationFrame(() => { if (openCalendar && openCalendar.input === input) position(); });
    }

    function pick(ymd) {
        input.value = ymd;
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.dispatchEvent(new Event('change', { bubbles: true }));
        syncLabel();
        close();
    }
    function close() {
        pop.hidden = true;
        wrap.classList.remove('is-open');
        trigger.setAttribute('aria-expanded', 'false');
        if (openCalendar && openCalendar.input === input) openCalendar = null;
    }
    function syncLabel() {
        const text = calLabelFor(input);
        label.textContent = text || t('calendar_placeholder', 'dd.mm.yyyy');
        label.classList.toggle('is-empty', !text);
        trigger.disabled = input.disabled;
        trigger.classList.toggle('is-disabled', input.disabled);
        trigger.setAttribute('aria-label', text || t('select_date_label', 'Select date'));
    }

    trigger.addEventListener('click', event => {
        event.stopPropagation();
        if (openCalendar && openCalendar.input === input) { close(); return; }
        open();
    });
    navButtons.forEach(button => {
        button.addEventListener('click', event => {
            event.stopPropagation();
            viewMonth += Number(button.dataset.dir);
            if (viewMonth > 11) { viewMonth = 0; viewYear++; }
            if (viewMonth < 0) { viewMonth = 11; viewYear--; }
            fillYears();
            render();
        });
    });
    monthSelect.addEventListener('change', () => { viewMonth = Number(monthSelect.value); render(); });
    yearSelect.addEventListener('change', () => { viewYear = Number(yearSelect.value); render(); });
    pop.querySelector('.cal-today').onclick = event => {
        event.stopPropagation();
        const now = new Date();
        const { min, max } = calBounds(input);
        if ((min && now < min) || (max && now > max)) return;
        pick(calYmd(now));
    };
    pop.querySelector('.cal-clear').onclick = event => {
        event.stopPropagation();
        input.value = '';
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.dispatchEvent(new Event('change', { bubbles: true }));
        syncLabel();
        close();
    };
    input.addEventListener('change', syncLabel);
    input.addEventListener('input', syncLabel);
    // Other code writes input.value directly (default ranges, resets), which
    // fires no event: trap the property so the label never goes stale.
    const valueDescriptor = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value');
    Object.defineProperty(input, 'value', {
        configurable: true,
        get() { return valueDescriptor.get.call(this); },
        set(value) { valueDescriptor.set.call(this, value); syncLabel(); }
    });
    new MutationObserver(syncLabel).observe(input, { attributes: true, attributeFilter: ['min', 'max', 'disabled', 'required'] });

    enhanceSelect(monthSelect);
    enhanceSelect(yearSelect);
    syncLabel();
    trigger.setAttribute('aria-expanded', 'false');
}

function enhanceAllDateFields(root) {
    const scope = root || document;
    if (scope.matches && scope.matches('input[type="date"]')) enhanceDateField(scope);
    (scope.querySelectorAll ? scope.querySelectorAll('input[type="date"]') : []).forEach(enhanceDateField);
}

function initDateFields() {
    enhanceAllDateFields(document);
    new MutationObserver(mutations => {
        mutations.forEach(mutation => mutation.addedNodes.forEach(node => {
            if (node.nodeType === 1) enhanceAllDateFields(node);
        }));
    }).observe(document.body, { childList: true, subtree: true });
    document.addEventListener('mousedown', event => {
        if (!event.target.closest) return;
        if (!event.target.closest('.cal-field-wrap') && !event.target.closest('.cal-pop') && !event.target.closest('.cs-menu')) closeCalendar();
    }, true);
    window.addEventListener('scroll', event => {
        if (event.target instanceof Element && event.target.closest && event.target.closest('.cal-pop')) return;
        closeCalendar();
    }, true);
    window.addEventListener('resize', () => closeCalendar());
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && openCalendar) {
            event.preventDefault();
            event.stopImmediatePropagation();
            closeCalendar();
        }
    }, true);
}

// ══ Notification ═══════════════════════════════════════════════════════════
function showNotification(message, type='info') {
    const c = document.getElementById('notification-container');
    const n = document.createElement('div');
    n.className = `notification ${type}`;
    const s = document.createElement('span');
    s.textContent = message;
    const b = document.createElement('button');
    b.className = 'notification-close';
    b.textContent = '×';
    b.onclick = () => n.remove();
    n.append(s, b);
    c.appendChild(n);
    setTimeout(()=>n.remove(), 5000);
}

// ══ Server-side table data ════════════════════════════════════════════════════
function loadPage(page) {
    const monthRange = getMonthDateRange();
    const params = new URLSearchParams({
        get_expenses_page: 1,
        months_window: 0,
        sort: 'date_asc',
        start_date: monthRange.start_date,
        end_date: monthRange.end_date,
        page: String(page || 1),
        per_page: String(tablePageInfo.perPage || 20)
    });

    fetch('?' + params.toString())
    .then(r=>r.json())
    .then(data=>{
        if (data.status !== 'success') return;
        const tbody = document.querySelector('#data-table tbody');
        tbody.innerHTML = '';
        const visibleStores = userSettings.stores.filter(s=>s.visible!==false);

        const byDate = {};
        const incomeByDate = {};
        (data.rows||[]).forEach(row=>{
            if (!byDate[row.date]) byDate[row.date] = {};
            byDate[row.date][row.store] = parseFloat(row.amount)||0;
        });
        (data.incomes||[]).forEach(row=>{incomeByDate[row.date]=parseFloat(row.amount)||0;});
        let dates = (data.dates && data.dates.length) ? data.dates : Object.keys(byDate);
        if (!data.dates || !data.dates.length) {
            dates.sort((a,b)=>tableSettings.sort_by==='date_desc'?b.localeCompare(a):a.localeCompare(b));
        }

        dates.forEach(date=>{
            const tr = document.createElement('tr');
            tr.setAttribute('data-date', date);
            const parts = date.split('-');
            const mo = parseInt(parts[1])-1;
            const dateStr = parts[2]+' '+monthNames[mo]+' '+parts[0];
            tr.innerHTML = `<td>${dateStr}</td>`;
            visibleStores.forEach(store=>{
                const amt = (byDate[date] && byDate[date][store.name]) || 0;
                const td = document.createElement('td');
                const input = document.createElement('input');
                input.type = 'text';
                input.setAttribute('data-date', date);
                input.setAttribute('data-store', store.name);
                input.value = amt!==0 ? parseFloat(amt).toFixed(2) : '';
                input.defaultValue = input.value;
                input.oninput = ()=>formatNumberInput(input);
                input.onchange = ()=>scheduleSave(input);
                input.onfocus = ()=>{ hidePreviewTooltip(); highlightRowAndColumn(input); formatNumberInput(input); };
                input.onblur  = ()=>{ hidePreviewTooltip(); removeHighlight(); if(input.value.trim()!==input.defaultValue) scheduleSave(input); };
                input.addEventListener('input',()=>previewExpression(input));
                attachCellContextMenu(input);
                attachAmountCurrencyPicker(input);
                td.appendChild(input);
                tr.appendChild(td);
            });
            if(userSettings.show_incomes){
                const td = document.createElement('td');
                td.className='income-separator';
                const input = document.createElement('input');
                input.type='text';
                input.setAttribute('data-date',date);
                input.setAttribute('data-income','1');
                const incAmt=incomeByDate[date]||0;
                input.value=incAmt!==0?parseFloat(incAmt).toFixed(2):'';
                input.defaultValue=input.value;
                input.oninput=()=>formatNumberInput(input);
                input.onchange=()=>scheduleSave(input);
                input.onfocus=()=>{ hidePreviewTooltip(); highlightRowAndColumn(input); formatNumberInput(input); };
                input.onblur=()=>{ hidePreviewTooltip(); removeHighlight(); if(input.value.trim()!==input.defaultValue) scheduleSave(input); };
                input.addEventListener('input',()=>previewExpression(input));
                attachCellContextMenu(input);
                attachAmountCurrencyPicker(input);
                td.appendChild(input);
                tr.appendChild(td);
            }
            tbody.appendChild(tr);
        });

        totalRowsDB = data.total;
        tablePageInfo = {
            page: data.page || 1,
            perPage: data.perPage || tablePageInfo.perPage || 20,
            total: data.total || 0,
            totalPages: data.totalPages || 1
        };
        renderPagination();
        highlightCurrentDate();
        if (pendingScrollDate) { scrollToTableRow(pendingScrollDate); pendingScrollDate = null; }

        document.querySelectorAll('#data-table tbody input').forEach(inp=>{
            inp.defaultValue = inp.value;
        });
        initTableInputSelection();
    })
    .catch(e=>{ console.error('loadPage error:', e); });
}

function renderPagination() {
    const p = document.getElementById('pagination');
    if (!p) return;
    p.innerHTML = '';
    const page = tablePageInfo.page || 1;
    const totalPages = tablePageInfo.totalPages || 1;
    if (totalPages <= 1) { p.style.display = 'none'; return; }
    p.style.display = 'flex';
    const addButton = (label, target, opts = {}) => {
        const b = document.createElement('button');
        b.type = 'button';
        b.textContent = label;
        if (opts.title) b.title = opts.title;
        if (target === page) {
            b.classList.add('active');
            b.setAttribute('aria-current', 'page');
        } else if (opts.disabled) {
            b.disabled = true;
        } else {
            b.onclick = () => loadPage(target);
        }
        p.appendChild(b);
    };
    const pushPage = n => addButton(String(n), n);
    addButton('‹', page - 1, { disabled: page <= 1, title: t('prev_page', 'Previous page') });
    const span = 2;
    if (totalPages > 1) pushPage(1);
    if (page - span > 2) p.appendChild(document.createTextNode('…'));
    for (let n = Math.max(2, page - span); n <= Math.min(totalPages - 1, page + span); n++) pushPage(n);
    if (page + span < totalPages - 1) p.appendChild(document.createTextNode('…'));
    if (totalPages > 1) pushPage(totalPages);
    addButton('›', page + 1, { disabled: page >= totalPages, title: t('next_page', 'Next page') });
}

// ══ Month pagination ═══════════════════════════════════════════════════════
let currentMonth = null;
let currentYear = null;

function localYmd(d){
    const p = n => String(n).padStart(2,'0');
    return d.getFullYear()+'-'+p(d.getMonth()+1)+'-'+p(d.getDate());
}
function scrollToTableRow(dateStr){
    const scroller = document.querySelector('.table-modal .data-table-scroll');
    const row = document.querySelector(`#data-table tbody tr[data-date="${dateStr}"]`);
    if(!scroller||!row) return;
    const r = row.getBoundingClientRect();
    const s = scroller.getBoundingClientRect();
    scroller.scrollTop += (r.top - s.top) - Math.max(0,(s.height - r.height)/2);
}

function getMonthData() {
    const now = new Date();
    if (!currentMonth) currentMonth = now.getMonth();
    if (!currentYear) currentYear = now.getFullYear();
    return { month: currentMonth, year: currentYear };
}

function renderMonthPagination() {
    const container = document.getElementById('month-pagination');
    if (!container) return;
    container.innerHTML = '';

    const { month, year } = getMonthData();
    const monthNamesShort = monthNames.map(n=>n.slice(0,3));

    // Prev month button
    const prevBtn = document.createElement('button');
    prevBtn.innerHTML = '◀';
    prevBtn.title = t('prev_month','Previous month');
    prevBtn.onclick = () => { navigateMonth(-1); };
    container.appendChild(prevBtn);

    // Current month label
    const label = document.createElement('span');
    label.className = 'month-pagination-label';
    label.textContent = monthNamesShort[month] + ' ' + year;
    container.appendChild(label);

    // Next month button: the future has no expenses, so it stops at this month.
    const nextBtn = document.createElement('button');
    nextBtn.innerHTML = '▶';
    nextBtn.title = t('next_month','Next month');
    const nowDate = new Date();
    nextBtn.disabled = year > nowDate.getFullYear()
        || (year === nowDate.getFullYear() && month >= nowDate.getMonth());
    nextBtn.onclick = () => { navigateMonth(1); };
    container.appendChild(nextBtn);

    // Today button: jumps to the current month and highlights today's whole row
    const todayBtn = document.createElement('button');
    todayBtn.type = 'button';
    todayBtn.textContent = t('today','Today');
    todayBtn.style.marginLeft = '8px';
    todayBtn.onclick = () => {
        const now = new Date();
        currentMonth = now.getMonth();
        currentYear = now.getFullYear();
        pendingScrollDate = localYmd(now);
        loadPageWithMonth();
    };
    container.appendChild(todayBtn);

    // Calendar: pick any year and month
    const calBtn = document.createElement('button');
    calBtn.type = 'button';
    calBtn.className = 'table-cal-trigger';
    calBtn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4.5" width="18" height="17" rx="2.5"/><line x1="16" y1="2.5" x2="16" y2="6.5"/><line x1="8" y1="2.5" x2="8" y2="6.5"/><line x1="3" y1="10" x2="21" y2="10"/></svg>';
    calBtn.title = t('select_date_label','Select Date:');
    calBtn.style.marginLeft = '8px';
    container.appendChild(calBtn);

    const pop = buildMonthPickerPopup(month, year);
    container.appendChild(pop);
    calBtn.onclick = (e) => { e.stopPropagation(); pop.classList.toggle('open'); };
    renderPagination();
}

let calReopen = false;
function buildMonthPickerPopup(month, year) {
    const pop = document.createElement('div');
    pop.className = 'table-cal-pop';

    const title = document.createElement('div');
    title.className = 'table-cal-title';
    title.textContent = t('select_date_label','Select Date:');
    pop.appendChild(title);

    // Two steps with a short slide: pick the year first (any year back to 1900),
    // then the month.
    const today = new Date();
    const newestYear = today.getFullYear();
    const OLDEST_YEAR = 1900;
    let pickedYear = year;

    const yearsPanel = document.createElement('div');
    yearsPanel.className = 'table-cal-panel table-cal-years';
    for (let y = newestYear; y >= OLDEST_YEAR; y--) {
        const cell = document.createElement('button');
        cell.type = 'button';
        cell.className = 'table-cal-cell';
        cell.textContent = String(y);
        if (y === pickedYear) cell.classList.add('is-active');
        cell.onclick = () => { pickedYear = y; showMonths(); };
        yearsPanel.appendChild(cell);
    }

    const monthsPanel = document.createElement('div');
    monthsPanel.className = 'table-cal-panel table-cal-months';
    monthsPanel.hidden = true;

    const monthsHead = document.createElement('div');
    monthsHead.className = 'table-cal-head';
    const backBtn = document.createElement('button');
    backBtn.type = 'button';
    backBtn.className = 'table-cal-back';
    backBtn.textContent = '‹ ' + t('year_label', 'Year');
    backBtn.onclick = () => showYears();
    const pickedLabel = document.createElement('strong');
    pickedLabel.textContent = String(pickedYear);
    monthsHead.appendChild(backBtn);
    monthsHead.appendChild(pickedLabel);
    monthsPanel.appendChild(monthsHead);

    monthNames.forEach((name, i) => {
        const cell = document.createElement('button');
        cell.type = 'button';
        cell.className = 'table-cal-cell';
        cell.textContent = name;
        // Months after the current one are disabled for the current year.
        cell.disabled = pickedYear === today.getFullYear() && i > today.getMonth();
        if (i === month) cell.classList.add('is-active');
        cell.onclick = () => {
            currentYear = pickedYear;
            currentMonth = i;
            calReopen = true;
            loadPageWithMonth();
        };
        monthsPanel.appendChild(cell);
    });

    function replayAnimation(el) {
        el.style.animation = 'none';
        void el.offsetWidth;
        el.style.animation = '';
    }
    function showMonths() {
        pickedLabel.textContent = String(pickedYear);
        yearsPanel.hidden = true;
        monthsPanel.hidden = false;
        replayAnimation(monthsPanel);
    }
    function showYears() {
        monthsPanel.hidden = true;
        yearsPanel.hidden = false;
        replayAnimation(yearsPanel);
        setTimeout(() => {
            const active = yearsPanel.querySelector('.is-active');
            if (active) yearsPanel.scrollTop = Math.max(0, active.offsetTop - yearsPanel.clientHeight / 2);
        }, 0);
    }

    pop.appendChild(yearsPanel);
    pop.appendChild(monthsPanel);
    setTimeout(showYears, 0);
    return pop;
}

document.addEventListener('click', e => {
    const openPop = document.querySelector('.table-cal-pop.open');
    if (!openPop) return;
    if (openPop.contains(e.target)) return;
    if (e.target.closest && e.target.closest('.table-cal-trigger')) return;
    openPop.classList.remove('open');
});

function navigateMonth(direction) {
    const { month, year } = getMonthData();
    let nextMonth = month + direction;
    let nextYear = year;
    if (nextMonth > 11) { nextMonth = 0; nextYear++; }
    if (nextMonth < 0) { nextMonth = 11; nextYear--; }
    const now = new Date();
    if (nextYear > now.getFullYear() || (nextYear === now.getFullYear() && nextMonth > now.getMonth())) return;
    currentMonth = nextMonth;
    currentYear = nextYear;
    loadPageWithMonth();
}

function loadPageWithMonth() {
    renderMonthPagination();
    if (calReopen) {
        const pop = document.querySelector('.table-cal-pop');
        if (pop) pop.classList.add('open');
        calReopen = false;
    }
    loadPage(1);
}

function getMonthDateRange() {
    const { month, year } = getMonthData();
    // Build the range in LOCAL time: toISOString() would shift to UTC and drop
    // the last day of the month for users ahead of UTC (e.g. June 30 missing).
    const start = new Date(year, month, 1);
    const end = new Date(year, month + 1, 0);
    return {
        start_date: localYmd(start),
        end_date: localYmd(end)
    };
}

// ══ Expense save ════════════════════════════════════════════════════════════
// Safe arithmetic evaluator. The page CSP forbids eval()/Function(), so we
// parse simple + - * / ( ) expressions ourselves instead of calling eval().
function safeCalc(expr) {
    const s = String(expr == null ? '' : expr).replace(/,/g, '.').replace(/\s+/g, '');
    if (!s) return 0;
    if (!/^[0-9.+\-*/()]+$/.test(s)) {
        const n = parseFloat(s);
        return isFinite(n) ? n : 0;
    }
    let i = 0;
    function parseExpr() {
        let v = parseTerm();
        while (i < s.length && (s[i] === '+' || s[i] === '-')) {
            const op = s[i++];
            const r = parseTerm();
            v = op === '+' ? v + r : v - r;
        }
        return v;
    }
    function parseTerm() {
        let v = parseFactor();
        while (i < s.length && (s[i] === '*' || s[i] === '/')) {
            const op = s[i++];
            const r = parseFactor();
            v = op === '*' ? v * r : v / r;
        }
        return v;
    }
    function parseFactor() {
        if (s[i] === '+') { i++; return parseFactor(); }
        if (s[i] === '-') { i++; return -parseFactor(); }
        if (s[i] === '(') {
            i++;
            const v = parseExpr();
            if (s[i] === ')') i++;
            return v;
        }
        const start = i;
        while (i < s.length && /[0-9.]/.test(s[i])) i++;
        const num = parseFloat(s.slice(start, i));
        return isFinite(num) ? num : 0;
    }
    try {
        const result = parseExpr();
        return isFinite(result) ? result : 0;
    } catch (e) { return 0; }
}

function formatNumberInput(input) {
    let v = input.value.replace(/,/g,'.').replace(/[^\d.\+\-\*\/]/g,'');
    v = v.replace(/[\+\-\*\/]{2,}/g,m=>m[0]);
    input.value = v;
}

function scheduleSave(input) {
    clearTimeout(saveTimeout);
    if (input.value.trim()===input.defaultValue) return;
    saveTimeout = setTimeout(()=>saveExpense(input), 800);
}

function saveExpense(input, options={}) {
    expensesData = (expensesData && typeof expensesData === 'object') ? expensesData : {};
    incomesData  = (incomesData  && typeof incomesData  === 'object') ? incomesData  : {};
    const date  = input.getAttribute('data-date');
    const store = input.getAttribute('data-store');
    const isIncome = input.getAttribute('data-income')==='1';
    const before = input.defaultValue;
    let   val   = input.value.trim().replace(/,/g,'.');
    let   amount= safeCalc(val);
    amount = parseFloat(amount.toFixed(2));
    input.defaultValue = amount!==0?amount.toFixed(2):'';
    input.value = input.defaultValue;
    if (options.history !== false) recordCellEdit(cellRef(input), before, input.defaultValue);
    
    if(isIncome){
        incomesData[date]=amount;
    }else{
        if (!expensesData[date]) expensesData[date]={};
        expensesData[date][store]=amount;
    }
    updateFinancialSummary();

    const payload=isIncome?{update_income:'1',date,amount}:{update_expense:'1',date,store,amount};
    fetch('', {method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:new URLSearchParams(payload)})
    .then(r=>r.json())
    .then(d=>{ if(d.status!=='success') showNotification(d.message||t('error_generic','Error'),'error'); })
    .catch(e=>showNotification(`${t('network_error','Network error')}: ${e}`,'error'));
}
function parseTableInputAmount(val){
    val=String(val||'').trim().replace(/,/g,'.');
    return parseFloat(safeCalc(val).toFixed(6));
}
const INPUT_CURRENCIES=['USD','EUR','PLN','UAH','BYN'];
let amountRatesCache=null;
const AMOUNT_RATE_TTL=300000;
let currencyPopoverInput=null;
async function fetchAmountRatesMap(){
    const base=(userSettings.currency||'USD').toUpperCase();
    const now=Date.now();
    if(amountRatesCache&&amountRatesCache.base===base&&now-amountRatesCache.ts<AMOUNT_RATE_TTL)return amountRatesCache.rates;
    const r=await fetch(`?get_exchange_rates=1&base=${encodeURIComponent(base)}`);
    const d=await r.json();
    if(!d||d.status!=='success'||!d.rates||typeof d.rates!=='object')throw new Error('bad');
    amountRatesCache={base,rates:d.rates,ts:now};
    return d.rates;
}
function foreignAmountToBase(amount,fromCur,rates,base){
    fromCur=(fromCur||'').toUpperCase();
    base=(base||'USD').toUpperCase();
    if(!amount||!isFinite(amount))return 0;
    if(fromCur===base)return amount;
    const x=rates[fromCur];
    if(!x||x<=0)return amount;
    return amount/x;
}
function positionAmountCurrencyPopover(input){
    const pop=document.getElementById('amount-currency-popover');
    if(!pop||pop.style.display==='none')return;
    const r=input.getBoundingClientRect();
    let left=r.left;
    const top=r.bottom+6;
    pop.style.minWidth='auto';
    const w=pop.offsetWidth||200;
    if(left+w>window.innerWidth-8)left=window.innerWidth-w-8;
    if(left<8)left=8;
    pop.style.left=left+'px';
    pop.style.top=top+'px';
}
function hideAmountCurrencyPopover(){
    const pop=document.getElementById('amount-currency-popover');
    if(pop){pop.style.display='none';pop.setAttribute('aria-hidden','true');}
    currencyPopoverInput=null;
}
function showAmountCurrencyPopover(input){
    const pop=document.getElementById('amount-currency-popover');
    if(!pop||document.activeElement!==input)return;
    const v=input.value.trim();
    if(!/\d/.test(v)){hideAmountCurrencyPopover();return;}
    currencyPopoverInput=input;
    const base=(userSettings.currency||'USD').toUpperCase();
    const title=document.createElement('div');
    title.className='amount-currency-popover-title';
    title.textContent=t('input_currency_title','Amount is in');
    const wrap=document.createElement('div');
    wrap.className='amount-currency-popover-btns';
    INPUT_CURRENCIES.forEach(cur=>{
        const b=document.createElement('button');
        b.type='button';
        b.textContent=cur;
        b.dataset.currency=cur;
        b.onmousedown=e=>e.preventDefault();
        b.onclick=async e=>{
            e.preventDefault();
            e.stopPropagation();
            const num=parseTableInputAmount(input.value);
            if(!num||!isFinite(num)){hideAmountCurrencyPopover();return;}
            try{
                const rates=await fetchAmountRatesMap();
                if(cur!==base){
                    const x=rates[cur];
                    if(x===undefined||x===null||!(x>0)){showNotification(t('rates_not_loaded','Could not load exchange rates'),'error');return;}
                }
                const conv=foreignAmountToBase(num,cur,rates,base);
                input.value=conv!==0?parseFloat(conv.toFixed(2)).toFixed(2):'';
                hideAmountCurrencyPopover();
                formatNumberInput(input);
                saveExpense(input);
            }catch(_){
                showNotification(t('rates_not_loaded','Could not load exchange rates'),'error');
            }
        };
        wrap.appendChild(b);
    });
    pop.innerHTML='';
    pop.appendChild(title);
    pop.appendChild(wrap);
    pop.setAttribute('aria-hidden','false');
    pop.style.display='block';
    positionAmountCurrencyPopover(input);
}
function attachCellContextMenu(input){
    if(input.dataset.cellContextBound)return;
    input.dataset.cellContextBound='1';
    input.oncontextmenu=async e=>{
        const date=input.getAttribute('data-date')||'';
        if(!date)return;
        e.preventDefault();
        if(input.getAttribute('data-income')==='1'){
            const num=parseTableInputAmount(input.value);
            const amtStr=num!==0?num.toFixed(2):'—';
            const lab=t('cell_income_label','Income');
            const msg=t('cell_info_income','{date} · {label} · {amount}').replace(/\{date\}/g,date).replace(/\{label\}/g,lab).replace(/\{amount\}/g,amtStr);
            showNotification(msg,'info');
            return;
        }
        const store=input.getAttribute('data-store')||'';
        if(!store)return;
        const num=parseTableInputAmount(input.value);
        const amtStr=num!==0?num.toFixed(2):'—';
        try{
            const r=await fetch('?'+new URLSearchParams({get_expense_meta:'1',date,store}));
            const d=await r.json();
            const meta=d&&d.meta?d.meta:{};
            const source=String(meta.entry_source||'manual').toLowerCase();
            const raw=String(meta.ocr_raw_store||'').trim();
            let msg;
            if(source==='ocr'){
                const rawDisp=raw||t('ocr_detected','OCR');
                msg=t('cell_info_expense_ocr','{date} · {store} · {amount} · OCR: {raw}').replace(/\{date\}/g,date).replace(/\{store\}/g,store).replace(/\{amount\}/g,amtStr).replace(/\{raw\}/g,rawDisp);
            }else{
                const lab=source==='subscription'
                    ? t('cell_entry_subscription','Subscription')
                    : t('cell_entry_manual','Manual entry');
                msg=t('cell_info_expense_manual','{date} · {store} · {amount} · {label}').replace(/\{date\}/g,date).replace(/\{store\}/g,store).replace(/\{amount\}/g,amtStr).replace(/\{label\}/g,lab);
            }
            showNotification(msg,'info');
        }catch(_){
            showNotification(t('error_generic','Error'),'error');
        }
    };
}
function attachAmountCurrencyPicker(input){
    if(input.dataset.amountPickerBound)return;
    input.dataset.amountPickerBound='1';
    const maybeShow=()=>{
        if(document.activeElement===input&&/\d/.test(input.value))showAmountCurrencyPopover(input);
        else if(document.activeElement!==input)hideAmountCurrencyPopover();
    };
    input.addEventListener('input',maybeShow);
    input.addEventListener('focus',maybeShow);
    // Clicking a cell that already holds a number used to open the picker and
    // close it again in the same gesture; a plain click always re-opens it now.
    input.addEventListener('click',maybeShow);
    input.addEventListener('mouseup',maybeShow);
    input.addEventListener('blur',()=>setTimeout(()=>{ if(document.activeElement!==input) hideAmountCurrencyPopover(); },220));
}

function previewExpression(input) {
    const tooltip = document.getElementById('preview-tooltip');
    const result  = document.getElementById('preview-tooltip-result');
    if (!tooltip||!result) return;
    const expr = input.value.trim();
    const rect = input.getBoundingClientRect();
    if (!expr || !/\d/.test(expr)) { hidePreviewTooltip(); return; }
    try {
        const clean = expr.replace(/,/g,'.');
        if (!/^[0-9.\+\-\*\/\(\)\s]+$/.test(clean)) throw new Error('invalid');
        const res = safeCalc(clean);
        if (typeof res==='number'&&!isNaN(res)&&isFinite(res)) {
            const txt = (translations.preview_result||'= {result}').replace('{result}',res.toFixed(2));
            result.textContent=txt; result.style.color='green';
        } else throw new Error('invalid');
    } catch(e) { result.textContent='❌ '+(translations.invalid_expression||'Invalid expression'); result.style.color='red'; }
    tooltip.style.display='block';
    tooltip.style.left=(rect.left+window.scrollX)+'px';
    tooltip.style.top=(rect.top+window.scrollY-tooltip.offsetHeight-15)+'px';
    if (parseInt(tooltip.style.top)<window.scrollY) tooltip.style.top=(rect.bottom+window.scrollY+15)+'px';
}

function hidePreviewTooltip() {
    const tooltip = document.getElementById('preview-tooltip');
    if (tooltip) tooltip.style.display='none';
}

// ══ Chart ══════════════════════════════════════════════════════════════════
let exchangeRatesToUSD = {USD:1,EUR:1.1696,PLN:0.275,UAH:0.02416,BYN:0.2942};
let chartCurrency = userSettings.currency;
let chartLegendEntries = [];
let hiddenChartLabels = new Set();

async function switchChartCurrency(cur) {
    chartCurrency = cur;
    document.querySelectorAll('.currency-btn').forEach(b=>b.classList.toggle('active',b.dataset.currency===cur));
    try {
        const [nbu,nbp] = await Promise.all([
            fetch('https://bank.gov.ua/NBUStatService/v1/statdirectory/exchange?json').then(r=>r.json()),
            fetch('https://api.nbp.pl/api/exchangerates/tables/a/?format=json').then(r=>r.json())
        ]);
        const nbuRates={};nbu.forEach(r=>{nbuRates[r.cc]=r.rate;});
        const nbpRates={};nbp[0].rates.forEach(r=>{nbpRates[r.code]=r.mid;});
        exchangeRatesToUSD={USD:1,EUR:nbuRates['EUR']/nbuRates['USD'],PLN:1/nbpRates['USD'],UAH:1/nbuRates['USD'],BYN:0.2942};
        showNotification(t('exchange_rates_updated','Exchange rates updated'),'success');
    } catch(e) { showNotification(`${t('error_fetch_rates','Error fetching rates')}: ${e.message}`,'error'); }
    updateChart();
}

function linearRegression(values){
    const n=values.length;
    if(n<2)return values.slice();
    let sx=0,sy=0,sxx=0,sxy=0;
    for(let i=0;i<n;i++){sx+=i;sy+=values[i];sxx+=i*i;sxy+=i*values[i];}
    const denom=n*sxx-sx*sx;
    if(denom===0)return values.slice();
    const m=(n*sxy-sx*sy)/denom;
    const b=(sy-m*sx)/n;
    return Array.from({length:n},(_,i)=>+(m*i+b).toFixed(2));
}

function updateChart() {
    const ctx = document.getElementById('expenses-chart').getContext('2d');
    // Guard against null/undefined data (fixes "can't convert null to object" on load)
    expensesData = (expensesData && typeof expensesData === 'object') ? expensesData : {};
    incomesData  = (incomesData  && typeof incomesData  === 'object') ? incomesData  : {};
    userSettings = (userSettings && typeof userSettings === 'object') ? userSettings : {};
    userSettings.stores = Array.isArray(userSettings.stores) ? userSettings.stores : [];
    const allMonths = Array.from({length:12},(_,i)=>String(i+1).padStart(2,'0'));
    const yearDates = Object.keys(expensesData).filter(d=>d.startsWith(currentChartYear.toString()));
    const monthly={};
    allMonths.forEach(m=>{ monthly[m]={}; userSettings.stores.forEach(s=>{monthly[m][s.name]=0;}); });
    const monthlyIncome={};allMonths.forEach(m=>{monthlyIncome[m]=0;});
    yearDates.forEach(date=>{
        const m=date.split('-')[1];
        if (m in monthly) userSettings.stores.forEach(s=>{monthly[m][s.name]+=(expensesData[date]&&expensesData[date][s.name])||0;});
        if (m in monthlyIncome) monthlyIncome[m]+=(incomesData[date]||0);
    });
    const base = userSettings.currency;
    const conv = (exchangeRatesToUSD[base]||1)/(exchangeRatesToUSD[chartCurrency]||1);
    if (chart) chart.destroy();
    const now=new Date();
    const isCurrentYear=Number(currentChartYear)===now.getFullYear();
    const isPastYear=Number(currentChartYear)<now.getFullYear();
    let months=allMonths;
    if(userSettings.trim_empty_months){
        months=allMonths.filter(m=>{
            let hasData=false;
            userSettings.stores.forEach(s=>{if((monthly[m][s.name]||0)!==0)hasData=true;});
            if((monthlyIncome[m]||0)!==0)hasData=true;
            return hasData;
        });
    }
    const cutMonth=isCurrentYear?String(now.getMonth()+1).padStart(2,'0'):(isPastYear?'99':'__none__');
    function splitLine(vals,color,opts){
        const n=vals.length;
        const solid=[],dashed=[];
        for(let i=0;i<n;i++){
            if(months[i]<cutMonth){solid.push(vals[i]);dashed.push(null);}
            else if(months[i]===cutMonth){solid.push(vals[i]);dashed.push(vals[i]);}
            else{solid.push(null);dashed.push(vals[i]);}
        }
        const base={tension:.4,pointRadius:0,borderWidth:2,...(opts||{})};
        const gLabel=(opts&&opts._label)||'';
        delete base._label;
        const r=[];
        r.push({...base,label:gLabel,data:solid,borderColor:color,backgroundColor:base.fill?color+'22':'transparent',fill:!!base.fill});
        // The dashed projection shares the group label so hover tooltips keep
        // working there; `_dash` keeps it out of the legend list.
        r.push({...base,label:gLabel,data:dashed,borderColor:color,backgroundColor:'transparent',borderDash:[6,4],fill:false,_dash:true});
        return r;
    }
    let datasets=[];
    if (chartMode==='total') {
        const td=months.map(m=>{let t=0;userSettings.stores.forEach(s=>{if(s.visible!==false)t+=monthly[m][s.name]||0;});return t*conv;});
        datasets=splitLine(td,'#ef4444',{fill:true,_label:translations.total_expenses});
        if(userSettings.show_incomes){
            const inc=months.map(m=>(monthlyIncome[m]||0)*conv);
            datasets.push(...splitLine(inc,'#22c55e',{_label:t('incomes','Incomes')}));
        }
        if(userSettings.show_trend){
            const inc=months.map(m=>(monthlyIncome[m]||0)*conv);
            const diff=months.map((_,i)=>+(inc[i]-td[i]).toFixed(2));
            datasets.push(...splitLine(diff,'#f59e0b',{_label:t('difference_line','Difference')}));
        }
    } else if (chartMode==='categories') {
        // Third mode: the same year curve split by store category.
        const catOf={};
        userSettings.stores.forEach(s=>{catOf[s.name]=s.category||'other';});
        const perStoreTotals=months.map(m=>{let t=0;userSettings.stores.forEach(s=>{if(s.visible!==false)t+=monthly[m][s.name]||0;});return t*conv;});
        const catTotals={};
        userSettings.stores.forEach(s=>{const c=catOf[s.name];if(s.visible===false)return;catTotals[c]=(catTotals[c]||0)+months.reduce((sum,m)=>sum+(monthly[m][s.name]||0),0);});
        const cats=Object.keys(catTotals).sort((a,b)=>catTotals[b]-catTotals[a]);
        cats.forEach((cat,i)=>{
            const vals=months.map(m=>{let t=0;userSettings.stores.forEach(s=>{if(s.visible!==false&&catOf[s.name]===cat)t+=monthly[m][s.name]||0;});return t*conv;});
            datasets.push(...splitLine(vals,storeColors[i%storeColors.length],{_label:categoryLabel(cat)}));
        });
        if(userSettings.show_trend){
            const trendData=linearRegression(perStoreTotals);
            datasets.push(...splitLine(trendData,'#f59e0b',{tension:0,_label:t('difference_line','Trend')}));
        }
    } else {
        const perStoreTotals=months.map(m=>{let t=0;userSettings.stores.forEach(s=>{if(s.visible!==false)t+=monthly[m][s.name]||0;});return t*conv;});
        userSettings.stores.forEach((s,i)=>{
            if(s.visible===false)return;
            datasets.push(...splitLine(months.map(m=>(monthly[m][s.name]||0)*conv),storeColors[i%storeColors.length],{_label:s.name}));
        });
        // Per store view: no difference/trend line, the store curves are enough.
    }
    // More than five legend entries clutter the canvas, so the legend moves
    // into a modal and only a button stays next to the chart.
    datasets.forEach(d=>{if(d&&d.label&&hiddenChartLabels.has(d.label))d.hidden=true;});
    chartLegendEntries=datasets.filter(d=>d&&d.label&&!d._dash).map(d=>({label:d.label,color:d.borderColor}));
    const hideChartLegend=chartLegendEntries.length>5;
    chart=new Chart(ctx,{type:'line',data:{labels:months,datasets},options:{responsive:true,maintainAspectRatio:false,
        interaction:{mode:'nearest',intersect:false},
        scales:{y:{beginAtZero:!datasets.some(d=>d.data.some(v=>v!==null&&v<0)),title:{display:true,text:`${t('chart_amount','Amount')} (${chartCurrency})`,color:'<?= $theme==='dark'?'#b0b0b0':'#000' ?>'},ticks:{color:'<?= $theme==='dark'?'#b0b0b0':'#000' ?>'},grid:{color:'<?= $theme==='dark'?'#5a5a5a':'rgba(0,0,0,0.1)' ?>'},border:{color:'<?= $theme==='dark'?'#7a7a7a':'#666' ?>'}},
               x:{title:{display:true,text:t('chart_month','Month'),color:'<?= $theme==='dark'?'#b0b0b0':'#000' ?>'},ticks:{color:'<?= $theme==='dark'?'#b0b0b0':'#000' ?>'},grid:{color:'<?= $theme==='dark'?'#5a5a5a':'rgba(0,0,0,0.1)' ?>'},border:{color:'<?= $theme==='dark'?'#7a7a7a':'#666' ?>'}}},
        plugins:{legend:{display:!hideChartLegend,labels:{color:'<?= $theme==='dark'?'#fff':'#000' ?>',filter:item=>{const ds=datasets[item.datasetIndex];return !!ds&&!ds._dash&&ds.label!=='';}}},
                 // A store with 0 in the hovered month has nothing to report, so it
                 // never appears in the tooltip list.
                 tooltip:{mode:'nearest',intersect:false,filter:item=>item.dataset.label!==''&&Number(item.parsed.y)!==0,callbacks:{label:c=>`${c.dataset.label}: ${c.parsed.y.toFixed(2)} ${chartCurrency}`}}}}});
    const chartLegendBtn=document.getElementById('chart-legend-btn');
    if(chartLegendBtn){
        chartLegendBtn.style.display=hideChartLegend?'':'none';
        chartLegendBtn.textContent=`${t('legend','Legend')} (${chartLegendEntries.length})`;
    }
    document.getElementById('chart-year').textContent=`${translations.year}: ${currentChartYear}`;
    document.querySelectorAll('.chart-toggle-btn:not(#chart-legend-btn)').forEach(btn=>{
        btn.textContent=chartMode==='total'?translations.per_store_expenses:(chartMode==='stores'?t('chart_by_category','By category'):translations.total_expenses);
    });
    updateYearButtons();
    renderActivityFeed();
}

// ══ Activity feed (transactions under the dashboard) ═══════════════════════
const ACTIVITY_PAGE_SIZE = 6;
let activityTransactions = [];
let activityPage = 1;
let activitySourceFilter = 'all';
let activeActivityId = null;

function activityEsc(value){
    return String(value == null ? '' : value).replace(/[&<>"']/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[ch]));
}
function activityCurrency(){ return (chartCurrency || userSettings.currency || 'USD').toUpperCase(); }
function activityConvert(amount){
    const base = (userSettings.currency || 'USD').toUpperCase();
    const target = activityCurrency();
    const conv = (exchangeRatesToUSD[base] || 1) / (exchangeRatesToUSD[target] || 1);
    return (Number(amount) || 0) * conv;
}
function activityAmountText(amount){
    return `${activityConvert(amount).toFixed(2)} ${activityCurrency()}`;
}
function activityDateLabel(date){
    const d = new Date(`${date}T00:00:00`);
    if (isNaN(d.getTime())) return String(date || '');
    try {
        return new Intl.DateTimeFormat(document.documentElement.lang || currentLang || 'en',
            {weekday:'long', day:'numeric', month:'short'}).format(d);
    } catch (e) { return String(date || ''); }
}
function activityFullDate(date){
    const d = new Date(`${date}T00:00:00`);
    if (isNaN(d.getTime())) return String(date || '');
    try {
        return new Intl.DateTimeFormat(document.documentElement.lang || currentLang || 'en',
            {day:'numeric', month:'long', year:'numeric'}).format(d);
    } catch (e) { return String(date || ''); }
}
function activityStoreCategory(store){
    const entry = (userSettings.stores || []).find(s => s && s.name === store);
    return entry && entry.category ? categoryLabel(entry.category) : t('store_category_other', 'Other');
}

// One card transaction per (date, store) pair — the expenses table stores sums.
function buildActivityTransactions(){
    const list = [];
    const metaAll = (expenseActivityMeta && typeof expenseActivityMeta === 'object') ? expenseActivityMeta : {};
    Object.keys(expensesData || {}).forEach(date => {
        const row = expensesData[date] || {};
        Object.keys(row).forEach(store => {
            const amount = Number(row[store]) || 0;
            if (!(amount > 0.0001)) return;
            const meta = (metaAll[date] && metaAll[date][store]) || {};
            const raw = String(meta.source || 'manual').toLowerCase();
            const source = (raw === 'manual' || raw === 'subscription') ? 'manual' : 'notification';
            list.push({
                id: `${date}|${store}`,
                date: date,
                store: store,
                amount: amount,
                source: source,
                raw: String(meta.note || ''),
                category: activityStoreCategory(store)
            });
        });
    });
    list.sort((a, b) => a.date === b.date ? a.store.localeCompare(b.store) : (a.date < b.date ? 1 : -1));
    return list;
}
function activityTransactionsForView(){
    const all = buildActivityTransactions();
    return activitySourceFilter === 'all' ? all : all.filter(tx => tx.source === activitySourceFilter);
}
function activityStoreIcon(store){
    const key = String(store || '').trim().toLowerCase();
    const badge = (bg, border, inner) => `<span class="activity-card__icon" style="background:${bg};border-color:${border}">${inner}</span>`;
    if (key === 'lidl') return badge('#0050AA', '#1E6BC4', '<svg viewBox="0 0 40 40" aria-hidden="true"><circle cx="20" cy="20" r="15" fill="#FFF000" stroke="#E60A14" stroke-width="2.4"/><text x="20" y="24" text-anchor="middle" fill="#0050AA" font-size="10" font-weight="900" font-family="sans-serif">LIDL</text></svg>');
    if (key === 'biedronka') return badge('#FFD600', '#E6C200', '<svg viewBox="0 0 40 40" aria-hidden="true"><ellipse cx="20" cy="23" rx="9" ry="10" fill="#E30613"/><circle cx="20" cy="12" r="4" fill="#1D1D1B"/><line x1="20" y1="13" x2="20" y2="33" stroke="#1D1D1B" stroke-width="1.8"/></svg>');
    if (key === 'kfc') return badge('#A3080C', '#C8102E', '<svg viewBox="0 0 40 40" aria-hidden="true"><rect x="7" y="7" width="26" height="26" rx="5" fill="#fff"/><text x="20" y="24" text-anchor="middle" fill="#181818" font-size="9" font-weight="900" font-family="sans-serif">KFC</text></svg>');
    if (key === 'auchan') return badge('#E2001A', '#FF334B', '<svg viewBox="0 0 40 40" aria-hidden="true"><circle cx="20" cy="20" r="13" fill="#fff"/><path d="M14 25 L20 13 L26 25 M16.5 21 H23.5" stroke="#E2001A" stroke-width="2.4" fill="none" stroke-linecap="round"/></svg>');
    if (key === 'stokrotka' || key === 'stocrotka') return badge('#1E3B18', '#3E7A30', '<svg viewBox="0 0 40 40" aria-hidden="true"><circle cx="20" cy="12" r="3.6" fill="#fff"/><circle cx="28" cy="20" r="3.6" fill="#fff"/><circle cx="20" cy="28" r="3.6" fill="#fff"/><circle cx="12" cy="20" r="3.6" fill="#fff"/><circle cx="20" cy="20" r="4" fill="#FFD600"/></svg>');
    const initial = (String(store || '?').trim().charAt(0) || '?').toUpperCase();
    return `<span class="activity-card__icon activity-card__icon--initial">${activityEsc(initial)}</span>`;
}
function activityCardHtml(tx, showDate){
    const sourceLabel = tx.source === 'notification'
        ? `🔔 ${t('activity_filter_notification', 'Notification')}`
        : `✏️ ${t('activity_filter_manual', 'Manual')}`;
    return `<button type="button" class="activity-card" data-tx-id="${activityEsc(tx.id)}" onclick="openTxDetails('${activityEsc(tx.id).replace(/'/g, "\\'")}')" title="${activityEsc(tx.store)}">
        ${activityStoreIcon(tx.store)}
        <span class="activity-card__main">
            <span class="activity-card__row">
                <span class="activity-card__store">${activityEsc(tx.store)}</span>
                ${showDate ? `<span class="activity-card__date">${activityEsc(activityDateLabel(tx.date))}</span>` : ''}
            </span>
            <span class="activity-card__meta">${sourceLabel}<span class="activity-card__dot">·</span><span class="activity-card__category">${activityEsc(tx.category)}</span></span>
        </span>
        <span class="activity-card__amount">-${activityAmountText(tx.amount)}</span>
    </button>`;
}
function activityGroupHtml(list, showDate){
    const groups = [];
    let current = null;
    list.forEach(tx => {
        if (!current || current.date !== tx.date) { current = {date: tx.date, items: []}; groups.push(current); }
        current.items.push(tx);
    });
    return groups.map(group => `<div class="activity-group">
        ${showDate ? `<div class="activity-group__label">${activityEsc(activityDateLabel(group.date))}</div>` : ''}
        <div class="activity-group__grid">${group.items.map(tx => activityCardHtml(tx, !showDate)).join('')}</div>
    </div>`).join('');
}
function activityPageList(){
    const n = Math.max(1, Math.ceil(activityTransactions.length / ACTIVITY_PAGE_SIZE));
    if (activityPage > n) activityPage = n;
    if (activityPage < 1) activityPage = 1;
    return {page: activityPage, totalPages: n};
}
function renderActivityFeed(){
    const listEl = document.getElementById('dashboard-activity-list');
    if (!listEl) return;
    activityTransactions = buildActivityTransactions();
    const badge = document.getElementById('tx-total-badge');
    if (badge) badge.textContent = String(activityTransactions.length);
    const pager = document.getElementById('dashboard-activity-pagination');
    if (!activityTransactions.length) {
        listEl.innerHTML = `<div class="activity-empty">${activityEsc(t('activity_empty', 'No transactions yet.'))}</div>`;
        if (pager) pager.innerHTML = '';
        return;
    }
    const {page, totalPages} = activityPageList();
    const start = (page - 1) * ACTIVITY_PAGE_SIZE;
    const slice = activityTransactions.slice(start, start + ACTIVITY_PAGE_SIZE);
    listEl.innerHTML = activityGroupHtml(slice, true);
    if (pager) pager.innerHTML = activityPaginationHtml(start, slice.length, activityTransactions.length, page, totalPages);
}
function activityPaginationHtml(start, count, total, page, totalPages){
    const summary = `${t('activity_shown', 'Showing')} ${start + 1}–${start + count} ${t('activity_of', 'of')} ${total}`;
    const btn = (label, target, disabled, active) => `<button type="button" class="activity-pager__btn${active ? ' is-active' : ''}" ${disabled ? 'disabled' : ''} onclick="goToActivityPage(${target})">${label}</button>`;
    let pages = '';
    const windowSize = 5;
    let from = Math.max(1, page - 2), to = Math.min(totalPages, from + windowSize - 1);
    from = Math.max(1, to - windowSize + 1);
    if (from > 1) pages += btn('1', 1, false, page === 1) + (from > 2 ? '<span class="activity-pager__gap">…</span>' : '');
    for (let p = from; p <= to; p++) pages += btn(String(p), p, false, p === page);
    if (to < totalPages) pages += (to < totalPages - 1 ? '<span class="activity-pager__gap">…</span>' : '') + btn(String(totalPages), totalPages, false, page === totalPages);
    return `<div class="activity-pager__summary">${activityEsc(summary)}</div>
        <div class="activity-pager__controls">
            ${btn(`← ${activityEsc(t('activity_prev', 'Back'))}`, page - 1, page === 1, false)}
            ${pages}
            ${btn(`${activityEsc(t('activity_next', 'Next'))} →`, page + 1, page === totalPages, false)}
        </div>`;
}
function goToActivityPage(page){
    activityPage = Number(page) || 1;
    renderActivityFeed();
    const section = document.getElementById('dashboard-activity-section');
    if (section && section.scrollIntoView) section.scrollIntoView({behavior: 'smooth', block: 'start'});
}
function activityFilteredList(){
    const query = String(document.getElementById('history-search-input')?.value || '').toLowerCase().trim();
    return activityTransactions.filter(tx => {
        if (activitySourceFilter !== 'all' && tx.source !== activitySourceFilter) return false;
        if (!query) return true;
        return tx.store.toLowerCase().includes(query)
            || tx.category.toLowerCase().includes(query)
            || activityConvert(tx.amount).toFixed(2).includes(query);
    });
}
function renderFullActivity(){
    const container = document.getElementById('full-activity-list');
    if (!container) return;
    const list = activityFilteredList();
    container.innerHTML = list.length
        ? activityGroupHtml(list, true)
        : `<div class="activity-empty">${activityEsc(t('activity_no_results', 'No transactions match the filter'))}</div>`;
}
function setActivitySourceFilter(source){
    activitySourceFilter = source || 'all';
    document.querySelectorAll('#history-source-filters .hist-filter').forEach(btn => {
        btn.classList.toggle('is-active', btn.dataset.source === activitySourceFilter);
    });
    renderFullActivity();
}
function openActivityHistory(){
    activityTransactions = buildActivityTransactions();
    setActivitySourceFilter('all');
    const search = document.getElementById('history-search-input');
    if (search) search.value = '';
    renderFullActivity();
    showModal('activity-history-modal');
}
function openTxDetails(id){
    const tx = activityTransactions.find(item => item.id === id) || buildActivityTransactions().find(item => item.id === id);
    if (!tx) return;
    activeActivityId = tx.id;
    const box = document.getElementById('tx-details-content');
    if (!box) return;
    const sourceText = tx.source === 'notification'
        ? t('activity_source_notification', 'Notification (Google Wallet / Bank)')
        : t('activity_source_manual', 'Manual entry');
    box.innerHTML = `
        <div class="tx-detail__hero">
            ${activityStoreIcon(tx.store)}
            <div class="tx-detail__hero-text">
                <h4>${activityEsc(tx.store)}</h4>
                <span class="tx-detail__badge">${tx.source === 'notification' ? '🔔' : '✏️'} ${activityEsc(tx.source === 'notification' ? t('activity_filter_notification', 'Notification') : t('activity_filter_manual', 'Manual'))}</span>
            </div>
            <div class="tx-detail__amount">-${activityAmountText(tx.amount)}</div>
        </div>
        <div class="tx-detail__table">
            <div><span>${activityEsc(t('activity_date', 'Date'))}</span><b>${activityEsc(activityFullDate(tx.date))}</b></div>
            <div><span>${activityEsc(t('activity_category', 'Category'))}</span><b>${activityEsc(tx.category)}</b></div>
            <div><span>${activityEsc(t('activity_source', 'Source'))}</span><b>${activityEsc(sourceText)}</b></div>
        </div>
        <div id="tx-detail-raw-slot"></div>
        <div class="tx-detail__actions">
            <button type="button" class="tx-detail__delete" onclick="deleteTransaction('${activityEsc(tx.id).replace(/'/g, "\\'")}')">${activityEsc(t('activity_delete', 'Delete transaction'))}</button>
            <button type="button" class="tx-detail__done" onclick="closeModal('tx-details-modal')">${activityEsc(t('activity_done', 'Done'))}</button>
        </div>`;
    showModal('tx-details-modal');
    // The page ships only source/amount for the year; the encrypted OCR note is
    // fetched on demand for the card the user actually opened.
    const renderRaw = (raw) => {
        const slot = document.getElementById('tx-detail-raw-slot');
        if (slot && raw) slot.innerHTML = `<div class="tx-detail__raw"><div class="tx-detail__raw-title">🔔 ${activityEsc(t('activity_raw_notification', 'Original notification'))}</div><p>${activityEsc(raw)}</p></div>`;
    };
    if (tx.raw) {
        renderRaw(tx.raw);
    } else {
        fetch(`?get_expense_meta=1&date=${encodeURIComponent(tx.date)}&store=${encodeURIComponent(tx.store)}`, {headers: {'Accept': 'application/json'}})
            .then(r => r.json())
            .then(d => {
                if (activeActivityId !== tx.id || !d || d.status !== 'success') return;
                tx.raw = String((d.meta && d.meta.ocr_raw_store) || '');
                renderRaw(tx.raw);
            })
            .catch(() => {});
    }
}
function deleteTransaction(id){
    const tx = activityTransactions.find(item => item.id === id);
    if (!tx) return;
    const doDelete = () => {
        fetch('', {method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: new URLSearchParams({update_expense: '1', date: tx.date, store: tx.store, amount: '0'})})
        .then(r => r.json())
        .then(d => {
            if (d.status !== 'success') { showNotification(d.message || t('error_generic', 'Error'), 'error'); return; }
            if (expensesData[tx.date]) { delete expensesData[tx.date][tx.store]; }
            if (expenseActivityMeta[tx.date]) { delete expenseActivityMeta[tx.date][tx.store]; }
            closeModal('tx-details-modal');
            updateFinancialSummary();
            updateChart();
            renderFullActivity();
            showNotification(`${t('activity_deleted', 'Transaction deleted')}: ${tx.store}`, 'success');
        })
        .catch(e => showNotification(`${t('network_error', 'Network error')}: ${e}`, 'error'));
    };
    if (typeof confirmWithModal === 'function') {
        confirmWithModal(`${t('activity_delete_confirm', 'Delete this transaction?')} ${tx.store} · ${activityAmountText(tx.amount)}`, doDelete, t('delete', 'Delete'));
    } else {
        doDelete();
    }
}
function renderChartLegend(){
    const list=document.getElementById('chart-legend-list');
    if(!list)return;
    const esc=v=>String(v).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    list.innerHTML=chartLegendEntries.map(e=>{
        const off=hiddenChartLabels.has(e.label);
        return `<button type="button" class="chart-legend-item${off?' is-off':''}" data-label="${esc(e.label)}" title="${esc(e.label)}">`
            +`<i class="chart-legend-swatch" style="background:${esc(e.color)}"></i>`
            +`<span class="chart-legend-label">${esc(e.label)}</span>`
            +`<span class="chart-legend-check" aria-hidden="true">${off?'':'✓'}</span></button>`;
    }).join('');
    list.querySelectorAll('.chart-legend-item').forEach(btn=>{
        btn.onclick=()=>{
            const label=btn.dataset.label;
            if(hiddenChartLabels.has(label))hiddenChartLabels.delete(label);
            else hiddenChartLabels.add(label);
            applyChartLegendVisibility();
            renderChartLegend();
        };
    });
}
function applyChartLegendVisibility(){
    if(!chart||!chart.data||!Array.isArray(chart.data.datasets))return;
    chart.data.datasets.forEach((d,i)=>{
        if(d&&d.label)chart.setDatasetVisibility(i,!hiddenChartLabels.has(d.label));
    });
    chart.update();
}
function openChartLegend(){
    renderChartLegend();
    showModal('chart-legend-modal');
}

function yearHasData(y){return availableYears.includes(Number(y));}
// A year that has not started yet can be neither scanned nor charted.
function highestSelectableYear(){return new Date().getFullYear();}
function updateYearButtons(){
    const maxYear=highestSelectableYear();
    const prev=document.getElementById('chart-year-prev-btn');
    const next=document.getElementById('chart-year-next-btn');
    if(prev)prev.disabled=!yearHasData(currentChartYear-1);
    if(next)next.disabled=currentChartYear+1>maxYear||!yearHasData(currentChartYear+1);
    const ap=document.getElementById('analytics-year-prev-btn');
    const an=document.getElementById('analytics-year-next-btn');
    if(ap)ap.disabled=!yearHasData(analyticsYear-1);
    if(an)an.disabled=analyticsYear+1>maxYear||!yearHasData(analyticsYear+1);
    const yl=document.getElementById('analytics-year-label');
    if(yl)yl.textContent=String(analyticsYear);
}
function loadAvailableYears(){
    return fetch('?get_available_years=1').then(r=>r.json()).then(d=>{
        availableYears=(d.status==='success'&&Array.isArray(d.years)?d.years:[]).map(Number).sort((a,b)=>a-b);
        if(!availableYears.length){availableYears=[new Date().getFullYear()];}
        if(!yearHasData(currentChartYear))currentChartYear=availableYears[availableYears.length-1];
        if(!yearHasData(analyticsYear))analyticsYear=currentChartYear;
        updateYearButtons();
    }).catch(()=>{availableYears=[currentChartYear];updateYearButtons();});
}

function changeChartYear(d) {
    const target=currentChartYear+d;
    if(target>highestSelectableYear()||!yearHasData(target))return;
    currentChartYear = target;
    // Load data for new year
    fetch(`?year_data=${currentChartYear}`)
        .then(r=>r.json())
        .then(data=>{
            if(data.status==='success') {
                expensesData = data.expenses || {};
                incomesData = data.incomes || {};
                allDates = Array.isArray(data.dates) ? data.dates : [];
                updateChart();
            }
        })
        .catch(()=>updateChart());
}
function changeAnalyticsYear(d){
    const target=analyticsYear+Number(d);
    if(target>highestSelectableYear()||!yearHasData(target))return;
    analyticsYear=target;
    updateYearButtons();
    loadOCRAnalytics();
}
function toggleChartMode(){chartMode=chartMode==='total'?'stores':(chartMode==='stores'?'categories':'total');updateChart();}

// ══ Exchange Rates ══════════════════════════════════════════════════════════
// ══ Exchange Rates (redesigned) ══════════════════════════════════════════════
let xrBase = 'EUR';
let xrHistory = null;      // {dates:[], history:{CUR:{date:value}}}
const xrHistoryCache = {};
const XR_CURRENCIES = ['EUR','USD','UAH','PLN','BYN'];
const XR_NAMES = {EUR:'Euro',USD:'US Dollar',UAH:'Ukrainian Hryvnia',PLN:'Polish Zloty',BYN:'Belarusian Ruble'};

function xrFmt(v){
    if(!v&&v!==0)return'—';
    const a=Math.abs(v);
    if(a>=1000)return v.toFixed(0);
    if(a>=100)return v.toFixed(2);
    if(a>=1)return v.toFixed(4);
    return v.toFixed(5);
}

function xrSparkPath(values,w,h){
    const pts=values.filter(v=>v!==null&&v!==undefined&&isFinite(v));
    if(pts.length<2)return'';
    const min=Math.min(...pts),max=Math.max(...pts),rng=(max-min)||1;
    const step=w/(values.length-1);
    return values.map((v,i)=>{
        if(v===null||v===undefined||!isFinite(v))return'';
        const x=(i*step).toFixed(1),y=(h-2-((v-min)/rng)*(h-4)).toFixed(1);
        return (i===0||values.slice(0,i).some(p=>p===null||p===undefined)?'M':'L')+x+' '+y;
    }).join(' ');
}

function xrSparkline(cur,color){
    const ns='http://www.w3.org/2000/svg';
    const svg=document.createElementNS(ns,'svg');
    svg.setAttribute('class','xr-spark');
    svg.setAttribute('viewBox','0 0 60 24');
    svg.setAttribute('preserveAspectRatio','none');
    const series=xrHistory&&xrHistory.history&&xrHistory.history[cur]?Object.values(xrHistory.history[cur]):[];
    if(series.length>=2){
        const p=document.createElementNS(ns,'path');
        p.setAttribute('d',xrSparkPath(series,60,24));
        p.setAttribute('stroke',color);
        svg.appendChild(p);
    }
    return svg;
}

function xrRenderRows(){
    const wrap=document.getElementById('xr-rows');
    if(!wrap)return;
    const dates=xrHistory&&xrHistory.dates?xrHistory.dates:[];
    const anyData=xrHistory&&xrHistory.history&&XR_CURRENCIES.some(c=>c!==xrBase&&xrHistory.history[c]&&Object.values(xrHistory.history[c]).some(v=>v!==null));
    if(!anyData){
        xrRenderEmpty();
        return;
    }
    wrap.innerHTML='';
    XR_CURRENCIES.filter(c=>c!==xrBase).forEach((cur,idx)=>{
        const series=xrHistory&&xrHistory.history&&xrHistory.history[cur]?xrHistory.history[cur]:null;
        const vals=series?Object.values(series).filter(v=>v!==null):[];
        const last=vals.length?vals[vals.length-1]:null;
        const prev=series&&dates.length>=2?(series[dates[dates.length-2]]??null):null;
        const diff=(last!==null&&prev!==null&&prev!==0)?((last-prev)/prev*100):null;
        const row=document.createElement('div');
        row.className='xr-row'+(diff!==null&&Math.abs(diff)<0.005?' active':'');
        row.innerHTML=`<div class="xr-flag">${cur.slice(0,2)}</div>`+
            `<div class="xr-info"><strong>${cur}</strong><span>${XR_NAMES[cur]||''}</span></div>`+
            `<div class="xr-rate">${xrFmt(last)}<small>${xrBase}</small></div>`;
        const ch=document.createElement('div');
        ch.className='xr-change '+(diff===null?'flat':(diff>=0?'up':'down'));
        ch.textContent=diff===null?'—':`${diff>=0?'▲':'▼'} ${Math.abs(diff).toFixed(2)}%`;
        row.appendChild(ch);
        row.appendChild(xrSparkline(cur,diff!==null&&diff<0?'#ff5656':'#2ecb71'));
        wrap.appendChild(row);
    });
}

function xrRenderEmpty(){
    const wrap=document.getElementById('xr-rows');
    if(!wrap)return;
    wrap.innerHTML='<div class="xr-empty"><span>'+t('rates_unavailable','Exchange rates are unavailable right now.')+'</span>'+
        '<button type="button" onclick="loadExchangeRates(true)">'+t('retry','Retry')+'</button></div>';
}

function xrRenderTabs(){
    document.querySelectorAll('#xr-base-tabs .xr-base-tab').forEach(b=>{
        b.classList.toggle('active',b.dataset.base===xrBase);
    });
}

function xrFetchJson(url, timeoutMs=12000){
    const ctrl=new AbortController();
    const timer=setTimeout(()=>ctrl.abort(),timeoutMs);
    return fetch(url,{signal:ctrl.signal}).finally(()=>clearTimeout(timer));
}
function loadExchangeRates(retry) {
    const loader=document.getElementById('rates-loading');
    const cached=retry?null:xrHistoryCache[xrBase];
    if(retry)delete xrHistoryCache[xrBase];
    if(loader)loader.style.display=cached?'none':'block';
    if(cached){ xrHistory=cached; xrRenderRows(); }
    const bust=retry?'&nocache=1':'';
    // current snapshot (for other app features) + 14-day history for sparklines
    xrFetchJson(`?get_exchange_rates=1&base=${xrBase}${bust}`)
        .then(r=>r.json())
        .then(data=>{
            if(data.status==='success'&&data.rates){
                currentRates=data.rates;
                window.currentRates=data.rates;
            }
        })
        .catch(()=>{});
    xrFetchJson(`?get_exchange_history=1&base=${xrBase}&days=14${bust}`)
        .then(r=>r.json())
        .then(data=>{
            if(loader)loader.style.display='none';
            if(data.status==='success'){
                xrHistoryCache[xrBase]=data;
                xrHistory=data;
                xrRenderRows();
                const up=document.getElementById('xr-updated');
                if(up)up.textContent=new Date().toLocaleTimeString();
            } else if(!cached) {
                xrRenderEmpty();
                showNotification(t('failed_load_exchange_rates','Failed to load exchange rates'),'error');
            }
        })
        .catch(e=>{
            if(loader)loader.style.display='none';
            // Keep whatever is already rendered instead of an endless spinner.
            if(!cached){
                if(!document.querySelector('#xr-rows .xr-row'))xrRenderEmpty();
                showNotification(`${t('network_error','Network error')}: ${e&&e.name==='AbortError'?'timeout':e}`,'error');
            }
        });
}

function xrSetBase(base){
    xrBase=base;
    xrRenderTabs();
    loadExchangeRates();
}

document.addEventListener('click',e=>{
    const tab=e.target.closest('#xr-base-tabs .xr-base-tab');
    if(tab)xrSetBase(tab.dataset.base);
});


function highlightCurrentDate() {
    document.querySelectorAll('#data-table .current-date-row').forEach(r=>r.classList.remove('current-date-row'));
    const today = localYmd(new Date());
    document.querySelectorAll('#data-table tbody tr').forEach(r=>{
        if (r.getAttribute('data-date')===today) {
            r.classList.add('current-date-row');
        }
    });
}

function highlightRowAndColumn(input) {
    document.querySelectorAll('.highlight-row,.highlight-column,.highlight-date').forEach(el=>{
        el.classList.remove('highlight-row','highlight-column','highlight-date');
    });
    const row=input.closest('tr');
    row.classList.add('highlight-row');
    const dc=row.querySelector('td:first-child');
    if(dc) dc.classList.add('highlight-date');
    const cell=input.parentElement;
    const ci=Array.from(row.cells).indexOf(cell);
    const hr=document.querySelector('#data-table thead tr');
    if(hr&&hr.cells[ci]) hr.cells[ci].classList.add('highlight-column');
    document.querySelectorAll(`#data-table tbody tr td:nth-child(${ci+1})`).forEach(c=>c.classList.add('highlight-column'));
}
function removeHighlight(){document.querySelectorAll('.highlight-row,.highlight-column').forEach(el=>{el.classList.remove('highlight-row','highlight-column');});}

// ══ Date add ══════════════════════════════════════════════════════════════
function changeDateValue(field,delta) {
    const y=document.getElementById('date-year'),m=document.getElementById('date-month'),d=document.getElementById('date-day');
    let yr=parseInt(y.value)||new Date().getFullYear(),mo=parseInt(m.value)||1,dy=parseInt(d.value)||1;
    if(field==='year'){yr+=delta;if(yr<1900)yr=1900;if(yr>2100)yr=2100;}
    else if(field==='month'){mo+=delta;if(mo<1)mo=12;if(mo>12)mo=1;}
    else{const dim=new Date(yr,mo,0).getDate();dy+=delta;if(dy<1)dy=dim;if(dy>dim)dy=1;}
    y.value=yr;m.value=String(mo).padStart(2,'0');d.value=String(dy).padStart(2,'0');
}

function addNewDate(date=null, colIdx=0) {
    expensesData = (expensesData && typeof expensesData === 'object') ? expensesData : {};
    incomesData  = (incomesData  && typeof incomesData  === 'object') ? incomesData  : {};
    if (!date) {
        const yr=document.getElementById('date-year').value;
        const mo=String(document.getElementById('date-month').value).padStart(2,'0');
        const dy=String(document.getElementById('date-day').value).padStart(2,'0');
        date=`${yr}-${mo}-${dy}`;
    }
    if (!isValidDate(date)) { showNotification(t('invalid_date_format','Invalid date format'),'error'); return; }
    fetch('',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:new URLSearchParams({add_date_ajax:'1',date})})
    .then(r=>r.json())
    .then(d=>{
        if(d.status==='success'){
            showNotification(d.message||t('date_added','Date added'),'success');
            closeModal('add-date-modal');
            if(!allDates.includes(date)){allDates.push(date);allDates.sort();}
            if(!expensesData[date]){expensesData[date]={};userSettings.stores.forEach(s=>{expensesData[date][s.name]=0;});}
            if(typeof incomesData[date]==='undefined') incomesData[date]=0;
            totalRowsDB++;
            loadPage();
        } else showNotification(d.message||t('error_generic','Error'),'error');
    }).catch(e=>showNotification(`${t('network_error','Network error')}: ${e}`,'error'));
}

function isValidDate(s){return /^\d{4}-\d{2}-\d{2}$/.test(s)&&!isNaN(new Date(s));}

// ══ Manual expense (store → date → amount, no table) ═════════════════════════
let manualAddState = { step:1, store:'', date:'' };

function manualAddYmd(offsetDays){
    const d = new Date();
    d.setDate(d.getDate() + Number(offsetDays || 0));
    return localYmd(d);
}
function manualAddStores(){
    const all = (Array.isArray(userSettings.stores) ? userSettings.stores : []).filter(s => s && s.name);
    const visible = all.filter(s => s.visible !== false);
    // Hidden stores still accept expenses, so fall back to them when nothing is visible.
    return visible.length ? visible : all;
}
// CSP on the page forbids eval(), so the amount is parsed without it.
function parseManualAmount(val){
    const s = String(val || '').replace(/\s/g, '').replace(/,/g, '.');
    if(!/^\d*\.?\d*$/.test(s)) return 0;
    const n = parseFloat(s);
    return isFinite(n) ? n : 0;
}
function formatManualAmount(input){
    let v = String(input.value || '').replace(/,/g, '.').replace(/[^\d.]/g, '');
    const dot = v.indexOf('.');
    if(dot >= 0) v = v.slice(0, dot + 1) + v.slice(dot + 1).replace(/\./g, '');
    input.value = v;
}
function manualAddFormatDate(ymd){
    if(!isValidDate(ymd)) return ymd || '';
    const p = ymd.split('-');
    const m = monthNames[parseInt(p[1], 10) - 1] || p[1];
    return `${p[2]} ${m} ${p[0]}`;
}
function manualAddFormatDateShort(ymd){
    if(!isValidDate(ymd)) return ymd || '';
    const d = new Date(ymd + 'T00:00:00');
    const mon = manualAddIntl({ month:'short' }, d, monthNames[d.getMonth()] || '');
    return `${String(d.getDate()).padStart(2,'0')} ${mon}`;
}
function manualAddWeekday(ymd){
    return manualAddIntl({ weekday:'short' }, new Date(ymd + 'T00:00:00'), '');
}
function manualAddStoreAvatar(name, photoUrl){
    const wrap = document.createElement('span');
    wrap.className = 'manual-store-avatar';
    const initials = document.createElement('span');
    initials.className = 'manual-store-initials';
    initials.textContent = (String(name || '').trim().charAt(0) || '?').toUpperCase();
    wrap.appendChild(initials);
    // Only request an image when the server told us a photo exists, otherwise
    // the browser logs a 404 for every store without one.
    if (photoUrl) {
        const img = document.createElement('img');
        img.className = 'manual-store-thumb';
        img.alt = '';
        img.loading = 'lazy';
        img.onload = () => { initials.style.display = 'none'; };
        img.onerror = () => { img.remove(); };
        img.src = photoUrl;
        wrap.insertBefore(img, initials);
    }
    return wrap;
}
function renderManualStoreGrid(){
    const grid = document.getElementById('manual-store-grid');
    if(!grid) return;
    const q = (document.getElementById('manual-store-search')?.value || '').trim().toLowerCase();
    const stores = manualAddStores().filter(s => !q || s.name.toLowerCase().includes(q));
    grid.innerHTML = '';
    if(!stores.length){
        const empty = document.createElement('div');
        empty.className = 'manual-empty';
        empty.textContent = t('manual_no_stores','No stores available');
        grid.appendChild(empty);
        return;
    }
    stores.forEach(s => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'manual-store-btn' + (manualAddState.store === s.name ? ' selected' : '');
        btn.setAttribute('data-store', s.name);
        btn.setAttribute('role','option');
        btn.setAttribute('aria-selected', manualAddState.store === s.name ? 'true' : 'false');

        const label = document.createElement('span');
        label.className = 'manual-store-name';
        label.textContent = s.name;

        btn.append(manualAddStoreAvatar(s.name, s.photo_url), label);
        btn.onclick = () => manualAddPickStore(s.name);
        grid.appendChild(btn);
    });
}
function renderManualDateQuick(){
    const wrap = document.getElementById('manual-date-quick');
    if(!wrap) return;
    wrap.innerHTML = '';
    // Last seven days, oldest → today, so the row reads left to right.
    for(let offset = -6; offset <= 0; offset++){
        const ymd = manualAddYmd(offset);
        const label = offset === 0
            ? t('today','Today')
            : (offset === -1 ? t('manual_yesterday','Yesterday') : manualAddWeekday(ymd));
        const b = document.createElement('button');
        b.type = 'button';
        b.className = 'manual-date-chip' + (manualAddState.date === ymd ? ' selected' : '');
        b.setAttribute('data-date', ymd);
        const main = document.createElement('span');
        main.textContent = label;
        const sub = document.createElement('small');
        sub.textContent = manualAddFormatDateShort(ymd);
        b.append(main, sub);
        b.onclick = () => manualAddPickDate(ymd);
        wrap.appendChild(b);
    }
}
let manualAddCalView = { year:0, month:0 };
function manualAddSyncCalendarView(){
    const d = new Date((manualAddState.date || manualAddYmd(0)) + 'T00:00:00');
    manualAddCalView = { year:d.getFullYear(), month:d.getMonth() };
}
function manualAddCalendarShift(delta){
    let year = manualAddCalView.year || new Date().getFullYear();
    let month = (manualAddCalView.month || 0) + Number(delta || 0);
    year += Math.floor(month / 12);
    month = ((month % 12) + 12) % 12;
    const now = new Date();
    if(year > now.getFullYear() || (year === now.getFullYear() && month > now.getMonth())) return;
    manualAddCalView = { year, month };
    renderManualCalendar();
}
function manualAddIntl(part, date, fallback){
    try { return new Intl.DateTimeFormat(currentLang || 'en', part).format(date); }
    catch(_) { return fallback; }
}
function manualAddFillCalendarSelects(year, month){
    const monthSel = document.getElementById('manual-cal-month');
    const yearSel  = document.getElementById('manual-cal-year');
    if(!monthSel || !yearSel) return;
    if(monthSel.options.length !== 12){
        monthSel.textContent = '';
        for(let m = 0; m < 12; m++){
            const o = document.createElement('option');
            o.value = String(m);
            o.textContent = manualAddIntl({ month:'long' }, new Date(2024, m, 1), monthNames[m] || String(m + 1));
            monthSel.appendChild(o);
        }
    }
    const minYear = 2000;
    const now = new Date();
    const maxYear = Math.max(now.getFullYear(), year);
    if(!yearSel.options.length || Number(yearSel.options[0].value) !== minYear || Number(yearSel.options[yearSel.options.length - 1].value) !== maxYear){
        yearSel.textContent = '';
        for(let y = minYear; y <= maxYear; y++){
            const o = document.createElement('option');
            o.value = String(y);
            o.textContent = String(y);
            yearSel.appendChild(o);
        }
    }
    // The current year stops at the current month, the future is unreachable.
    Array.from(monthSel.options).forEach((option, index) => {
        option.disabled = Number(year) === now.getFullYear() && index > now.getMonth();
    });
    monthSel.value = String(month);
    yearSel.value = String(year);
}
function manualAddCalendarJump(){
    const monthSel = document.getElementById('manual-cal-month');
    const yearSel  = document.getElementById('manual-cal-year');
    if(!monthSel || !yearSel) return;
    const month = Number(monthSel.value);
    const year  = Number(yearSel.value);
    if(!Number.isFinite(month) || !Number.isFinite(year)) return;
    const now = new Date();
    if(year > now.getFullYear() || (year === now.getFullYear() && month > now.getMonth())) return;
    manualAddCalView = { year, month };
    renderManualCalendar();
}
function renderManualCalendar(){
    const weekdays = document.getElementById('manual-cal-weekdays');
    const grid     = document.getElementById('manual-cal-grid');
    if(!weekdays || !grid) return;
    if(!manualAddCalView.year) manualAddSyncCalendarView();
    const { year, month } = manualAddCalView;
    manualAddFillCalendarSelects(year, month);

    weekdays.textContent = '';
    const monday = new Date(2024, 0, 1); // a Monday
    for(let i = 0; i < 7; i++){
        const day = new Date(monday);
        day.setDate(monday.getDate() + i);
        const span = document.createElement('span');
        span.textContent = manualAddIntl({ weekday:'short' }, day, ['Mo','Tu','We','Th','Fr','Sa','Su'][i]);
        weekdays.appendChild(span);
    }

    grid.textContent = '';
    const offset = (new Date(year, month, 1).getDay() + 6) % 7; // Monday first
    const daysInMonth = new Date(year, month + 1, 0).getDate();
    for(let i = 0; i < offset; i++){
        const blank = document.createElement('span');
        blank.className = 'manual-cal-blank';
        grid.appendChild(blank);
    }
    const today = manualAddYmd(0);
    for(let day = 1; day <= daysInMonth; day++){
        const ymd = `${year}-${String(month + 1).padStart(2,'0')}-${String(day).padStart(2,'0')}`;
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'manual-cal-day';
        btn.textContent = String(day);
        btn.setAttribute('data-date', ymd);
        btn.disabled = ymd > today;
        if(ymd === manualAddState.date) btn.classList.add('selected');
        if(ymd === today) btn.classList.add('today');
        btn.onclick = () => manualAddPickDate(ymd);
        grid.appendChild(btn);
    }
}
function renderManualSummary(){
    const box = document.getElementById('manual-summary');
    if(!box) return;
    box.innerHTML = '';
    if(manualAddState.store){
        const avatar = manualAddStoreAvatar(manualAddState.store);
        avatar.classList.add('manual-summary-avatar');
        box.appendChild(avatar);
    }
    const add = (label, value) => {
        const item = document.createElement('div');
        item.className = 'manual-summary-item';
        const lab = document.createElement('span');
        lab.className = 'manual-summary-label';
        lab.textContent = label;
        const val = document.createElement('strong');
        val.textContent = value;
        item.append(lab, val);
        box.appendChild(item);
    };
    add(t('manual_step_store','Store'), manualAddState.store || '—');
    add(t('manual_step_date','Date'), manualAddFormatDate(manualAddState.date || manualAddYmd(0)));
}
function manualAddBaseCurrency(){ return String(userSettings.currency || 'USD').toUpperCase(); }
// A native <select> popup takes system colors, so the currency list is custom.
let manualAddCurrency = '';
function manualCurrencyCheckIcon(){
    const ns = 'http://www.w3.org/2000/svg';
    const svg = document.createElementNS(ns, 'svg');
    svg.setAttribute('class','manual-currency-check');
    svg.setAttribute('viewBox','0 0 24 24');
    svg.setAttribute('fill','none');
    svg.setAttribute('stroke','currentColor');
    svg.setAttribute('stroke-width','2.6');
    svg.setAttribute('stroke-linecap','round');
    svg.setAttribute('stroke-linejoin','round');
    const poly = document.createElementNS(ns, 'polyline');
    poly.setAttribute('points','5 13 10 18 19 7');
    svg.appendChild(poly);
    return svg;
}
function renderManualCurrency(){
    const base = manualAddBaseCurrency();
    if(!INPUT_CURRENCIES.includes(String(manualAddCurrency).toUpperCase())) manualAddCurrency = base;
    const label = document.getElementById('manual-amount-currency');
    if(label) label.textContent = manualAddCurrency;
    const menu = document.getElementById('manual-amount-currency-menu');
    if(!menu) return;
    menu.textContent = '';
    INPUT_CURRENCIES.forEach(cur => {
        const b = document.createElement('button');
        b.type = 'button';
        b.className = 'manual-currency-opt';
        b.setAttribute('role','option');
        b.setAttribute('data-currency', cur);
        b.setAttribute('aria-selected', cur === manualAddCurrency ? 'true' : 'false');
        const name = document.createElement('span');
        name.textContent = cur;
        b.appendChild(name);
        if(cur === manualAddCurrency) b.appendChild(manualCurrencyCheckIcon());
        b.onclick = (e) => { e.stopPropagation(); manualAddSetCurrency(cur); };
        menu.appendChild(b);
    });
}
function toggleManualCurrencyMenu(e){
    if(e) e.stopPropagation();
    const menu = document.getElementById('manual-amount-currency-menu');
    const btn  = document.getElementById('manual-amount-currency-btn');
    if(!menu) return;
    renderManualCurrency();
    const open = !menu.classList.contains('open');
    menu.classList.toggle('open', open);
    menu.setAttribute('aria-hidden', open ? 'false' : 'true');
    if(btn) btn.setAttribute('aria-expanded', open ? 'true' : 'false');
}
function closeManualCurrencyMenu(){
    const menu = document.getElementById('manual-amount-currency-menu');
    const btn  = document.getElementById('manual-amount-currency-btn');
    if(menu){ menu.classList.remove('open'); menu.setAttribute('aria-hidden','true'); }
    if(btn) btn.setAttribute('aria-expanded','false');
}
function manualAddSetCurrency(cur){
    cur = String(cur || '').toUpperCase();
    if(!INPUT_CURRENCIES.includes(cur)) return;
    manualAddCurrency = cur;
    renderManualCurrency();
    closeManualCurrencyMenu();
    updateManualAmountHint();
}
let manualHintToken = 0;
async function updateManualAmountHint(){
    const hint  = document.getElementById('manual-amount-hint');
    const input = document.getElementById('manual-amount-input');
    if(!hint || !input) return;
    const base = manualAddBaseCurrency();
    const cur  = String(manualAddCurrency || base).toUpperCase();
    const val  = parseManualAmount(input.value);
    if(cur === base || !(val > 0)){ hint.textContent = ''; return; }
    const token = ++manualHintToken;
    try{
        const rates = await fetchAmountRatesMap();
        if(token !== manualHintToken) return;
        if(!(rates[cur] > 0)){ hint.textContent = ''; return; }
        hint.textContent = `≈ ${foreignAmountToBase(val, cur, rates, base).toFixed(2)} ${base}`;
    }catch(_){
        if(token === manualHintToken) hint.textContent = '';
    }
}
function manualAddPickStore(name){
    manualAddState.store = name;
    document.querySelectorAll('#manual-store-grid .manual-store-btn').forEach(b => {
        const on = b.getAttribute('data-store') === name;
        b.classList.toggle('selected', on);
        b.setAttribute('aria-selected', on ? 'true' : 'false');
    });
    manualAddGo(2);
}
function manualAddPickDate(ymd){
    if(!isValidDate(ymd)) return;
    manualAddState.date = ymd;
    document.querySelectorAll('#manual-date-quick .manual-date-chip').forEach(chip => {
        chip.classList.toggle('selected', chip.getAttribute('data-date') === ymd);
    });
    manualAddSyncCalendarView();
    renderManualCalendar();
    manualAddGo(3);
}
function manualAddGo(step){
    step = Number(step) || 1;
    closeManualCurrencyMenu();
    closeManualPeriodMenu();
    if(step >= 2 && !manualAddState.store){
        step = 1;
        showNotification(t('manual_pick_store','Pick a store first'),'info');
    }
    if(step >= 3 && !manualAddState.date) manualAddState.date = manualAddYmd(0);
    manualAddState.step = step;
    document.querySelectorAll('#manual-add-modal .manual-panel').forEach(p => {
        p.classList.toggle('active', Number(p.getAttribute('data-panel')) === step);
    });
    document.querySelectorAll('#manual-add-modal .manual-step').forEach(b => {
        const n = Number(b.getAttribute('data-step'));
        b.classList.toggle('active', n === step);
        b.classList.toggle('done', n < step);
        b.disabled = n > step;
        b.setAttribute('aria-selected', n === step ? 'true' : 'false');
    });
    if(step === 1){
        const s = document.getElementById('manual-store-search');
        if(s && window.innerWidth > 768) s.focus();
    }
    if(step === 2){ renderManualDateQuick(); renderManualCalendar(); }
    if(step === 3){
        renderManualCurrency();
        renderManualSummary();
        updateManualAmountHint();
        setTimeout(() => {
            const a = document.getElementById('manual-amount-input');
            if(a && window.innerWidth > 768) a.focus();
        }, 80);
    }
}
function openManualAddModal(opts){
    manualAddState = { step:1, store:'', date:manualAddYmd(0) };
    manualAddCalView = { year:0, month:0 };
    manualSubPeriod = 'monthly';
    manualSubItems = [];
    const search = document.getElementById('manual-store-search');
    if(search) search.value = '';
    const amount = document.getElementById('manual-amount-input');
    if(amount) amount.value = '';
    const hint = document.getElementById('manual-amount-hint');
    if(hint) hint.textContent = '';
    const subDay = document.getElementById('manual-sub-day');
    if(subDay) subDay.value = String((new Date()).getDate());
    const subCount = document.getElementById('manual-sub-count');
    if(subCount){ subCount.value = '3'; delete subCount.dataset.touched; }
    const subEnd = document.getElementById('manual-sub-end');
    if(subEnd) subEnd.value = '';
    const subEndless = document.getElementById('manual-sub-endless');
    if(subEndless) subEndless.checked = true;
    manualAddCurrency = manualAddBaseCurrency();
    closeManualCurrencyMenu();
    renderManualCurrency();
    renderManualStoreGrid();
    renderManualPeriodMenu();
    renderManualSubscriptions();
    setManualSubscriptionEnabled(!!(opts && opts.subscription));
    showModal('manual-add-modal');
    manualAddGo(1);
}
function openManualAddFromTable(){
    closeModal('expenses-table-modal');
    openManualAddModal({ subscription:true });
}
function closeManualAddModal(){
    closeManualCurrencyMenu();
    closeManualPeriodMenu();
    closeModal('manual-add-modal');
}

// ══ Recurring expenses (subscriptions) ═══════════════════════════════════════
let manualSubEnabled = false;
let manualSubPeriod = 'monthly';
let manualSubItems = [];

function manualSubPeriods(){
    return [
        { key:'monthly', label:t('manual_sub_monthly','Once a month') },
        { key:'weekly',  label:t('manual_sub_weekly','Once a week') },
        { key:'weeks',   label:t('manual_sub_weeks','Every N weeks') },
        { key:'days',    label:t('manual_sub_days','Every N days') }
    ];
}
function renderManualPeriodMenu(){
    const items = manualSubPeriods();
    const label = document.getElementById('manual-sub-period-label');
    if(label){
        const current = items.find(item => item.key === manualSubPeriod) || items[0];
        label.textContent = current.label;
    }
    const menu = document.getElementById('manual-sub-period-menu');
    if(!menu) return;
    menu.textContent = '';
    items.forEach(item => {
        const b = document.createElement('button');
        b.type = 'button';
        b.className = 'manual-sub-period-opt';
        b.setAttribute('role','option');
        b.setAttribute('data-period', item.key);
        b.setAttribute('aria-selected', item.key === manualSubPeriod ? 'true' : 'false');
        const name = document.createElement('span');
        name.textContent = item.label;
        b.appendChild(name);
        if(item.key === manualSubPeriod) b.appendChild(manualCurrencyCheckIcon());
        b.onclick = (e) => { e.stopPropagation(); manualAddSetPeriod(item.key); };
        menu.appendChild(b);
    });
}
function toggleManualPeriodMenu(e){
    if(e) e.stopPropagation();
    const menu = document.getElementById('manual-sub-period-menu');
    const btn  = document.getElementById('manual-sub-period-btn');
    if(!menu) return;
    renderManualPeriodMenu();
    const open = !menu.classList.contains('open');
    menu.classList.toggle('open', open);
    menu.setAttribute('aria-hidden', open ? 'false' : 'true');
    if(btn) btn.setAttribute('aria-expanded', open ? 'true' : 'false');
}
function closeManualPeriodMenu(){
    const menu = document.getElementById('manual-sub-period-menu');
    const btn  = document.getElementById('manual-sub-period-btn');
    if(menu){ menu.classList.remove('open'); menu.setAttribute('aria-hidden','true'); }
    if(btn) btn.setAttribute('aria-expanded','false');
}
function manualAddSetPeriod(key){
    if(!manualSubPeriods().some(item => item.key === key)) return;
    manualSubPeriod = key;
    closeManualPeriodMenu();
    renderManualPeriodMenu();
    syncManualSubFields();
}
function syncManualSubFields(){
    const dayField   = document.getElementById('manual-sub-day-field');
    const countField = document.getElementById('manual-sub-count-field');
    const unit       = document.getElementById('manual-sub-count-unit');
    const countInput = document.getElementById('manual-sub-count');
    const isMonthly  = manualSubPeriod === 'monthly';
    if(dayField) dayField.hidden = !isMonthly;
    if(countField) countField.hidden = isMonthly;
    if(unit){
        unit.textContent = manualSubPeriod === 'weeks'
            ? t('manual_sub_unit_weeks','wk')
            : t('manual_sub_unit_days','days');
    }
    if(countInput && manualSubPeriod === 'weekly' && !countInput.dataset.touched) countInput.value = '1';
    renderManualSubHint();
}
function renderManualSubHint(){
    const hint = document.getElementById('manual-sub-hint');
    if(!hint) return;
    if(manualSubPeriod !== 'monthly'){ hint.textContent = ''; return; }
    const raw = parseInt(document.getElementById('manual-sub-day')?.value || '1', 10) || 1;
    const day = Math.max(1, Math.min(31, raw));
    hint.textContent = day >= 29 ? t('manual_sub_day_hint','A day that does not exist in a month moves to its last day.') : '';
}
function syncManualSubEnd(){
    const endless = document.getElementById('manual-sub-endless');
    const end = document.getElementById('manual-sub-end');
    if(!end) return;
    const isEndless = !endless || endless.checked;
    end.disabled = isEndless;
    if(isEndless) end.value = '';
}
function syncManualSubBody(){
    const cb = document.getElementById('manual-sub-enabled');
    manualSubEnabled = !!(cb && cb.checked);
    const body = document.getElementById('manual-sub-body');
    if(body) body.hidden = !manualSubEnabled;
    if(!manualSubEnabled){
        closeManualPeriodMenu();
        return;
    }
    renderManualPeriodMenu();
    syncManualSubFields();
    syncManualSubEnd();
    loadManualSubscriptions();
}
function setManualSubscriptionEnabled(on){
    manualSubEnabled = !!on;
    const cb = document.getElementById('manual-sub-enabled');
    if(cb) cb.checked = manualSubEnabled;
    syncManualSubBody();
}
function manualSubPayload(store, amount, date){
    let frequency = 'monthly';
    let dayOfMonth = 0;
    let intervalDays = 0;
    if(manualSubPeriod === 'monthly'){
        const raw = parseInt(document.getElementById('manual-sub-day')?.value || '1', 10) || 1;
        dayOfMonth = Math.max(1, Math.min(31, raw));
    }else{
        frequency = 'interval';
        const raw = parseInt(document.getElementById('manual-sub-count')?.value || '1', 10) || 1;
        const n = Math.max(1, Math.min(365, raw));
        intervalDays = manualSubPeriod === 'weeks' ? n * 7 : (manualSubPeriod === 'weekly' ? 7 : n);
    }
    const endless = document.getElementById('manual-sub-endless');
    const endInput = document.getElementById('manual-sub-end');
    const endDate = (!endless || endless.checked) ? '' : (endInput && endInput.value ? endInput.value : '');
    return {
        store,
        amount,
        frequency,
        day_of_month: dayOfMonth,
        interval_days: intervalDays,
        start_date: date,
        end_date: endDate
    };
}
function manualSubPeriodText(sub){
    if(sub.frequency === 'monthly'){
        return `${t('manual_sub_monthly','Once a month')} · ${sub.day_of_month}`;
    }
    const days = Number(sub.interval_days) || 1;
    if(days === 7) return t('manual_sub_weekly','Once a week');
    if(days % 7 === 0) return `${t('manual_sub_every','Every')} ${days / 7} ${t('manual_sub_unit_weeks','wk')}`;
    return `${t('manual_sub_every','Every')} ${days} ${t('manual_sub_unit_days','days')}`;
}
function renderManualSubscriptions(){
    const list = document.getElementById('manual-sub-list');
    if(!list) return;
    list.textContent = '';
    if(!manualSubItems.length){
        const empty = document.createElement('div');
        empty.className = 'manual-sub-empty';
        empty.textContent = t('manual_sub_none','No active subscriptions');
        list.appendChild(empty);
        return;
    }
    manualSubItems.forEach(sub => {
        const row = document.createElement('div');
        row.className = 'manual-sub-item';
        row.setAttribute('data-sub-id', sub.id);
        row.appendChild(manualAddStoreAvatar(sub.store));
        const main = document.createElement('div');
        main.className = 'manual-sub-item-main';
        const title = document.createElement('strong');
        title.textContent = `${sub.store} · ${money(sub.amount)}`;
        const meta = document.createElement('span');
        meta.textContent = manualSubPeriodText(sub) + (sub.end_date ? ` · ${t('manual_sub_until','Valid until')} ${sub.end_date}` : '');
        main.append(title, meta);
        row.appendChild(main);
        const del = document.createElement('button');
        del.type = 'button';
        del.className = 'manual-sub-item-del';
        del.textContent = t('delete','Delete');
        del.onclick = () => deleteManualSubscription(sub.id);
        row.appendChild(del);
        list.appendChild(row);
    });
}
function loadManualSubscriptions(){
    const list = document.getElementById('manual-sub-list');
    if(!list) return;
    fetch('?get_subscriptions=1').then(r => r.json()).then(d => {
        if(!d || d.status !== 'success') return;
        manualSubItems = Array.isArray(d.subscriptions) ? d.subscriptions : [];
        renderManualSubscriptions();
    }).catch(() => {});
}
function deleteManualSubscription(id){
    fetch('', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:new URLSearchParams({delete_subscription:id})})
    .then(r => r.json())
    .then(d => {
        if(d.status !== 'success'){ showNotification(d.message || t('error_generic','Error'),'error'); return; }
        manualSubItems = manualSubItems.filter(item => String(item.id) !== String(id));
        renderManualSubscriptions();
        showNotification(t('manual_sub_deleted','Subscription deleted'),'success');
    })
    .catch(e => showNotification(`${t('network_error','Network error')}: ${e}`,'error'));
}
function saveManualSubscription(store, amount, date){
    const payload = manualSubPayload(store, amount, date);
    if(!payload.start_date) payload.start_date = date;
    if(payload.end_date && payload.end_date < payload.start_date){
        showNotification(t('invalid_date_format','Invalid date format'),'error');
        return;
    }
    const btn = document.getElementById('manual-save-btn');
    if(btn) btn.disabled = true;
    fetch('', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:new URLSearchParams({add_subscription:'1', ...payload})})
    .then(r => r.json())
    .then(d => {
        if(d.status !== 'success'){ showNotification(d.message || t('error_generic','Error'),'error'); return; }
        showNotification(t('manual_sub_created','Subscription created'),'success');
        closeManualAddModal();
        location.reload();
    })
    .catch(e => showNotification(`${t('network_error','Network error')}: ${e}`,'error'))
    .finally(() => { if(btn) btn.disabled = false; });
}
async function saveManualExpense(){
    const store = manualAddState.store;
    const date  = manualAddState.date || manualAddYmd(0);
    const input = document.getElementById('manual-amount-input');
    const base  = manualAddBaseCurrency();
    const cur   = String(manualAddCurrency || base).toUpperCase();
    let   amount = parseManualAmount(input ? input.value : '');
    if(!store){ manualAddGo(1); showNotification(t('manual_pick_store','Pick a store first'),'info'); return; }
    if(!isValidDate(date)){ showNotification(t('invalid_date_format','Invalid date format'),'error'); return; }
    if(!(amount > 0)){ showNotification(t('manual_amount_required','Enter the amount'),'error'); if(input) input.focus(); return; }
    if(cur !== base){
        try{
            const rates = await fetchAmountRatesMap();
            if(!(rates[cur] > 0)) throw new Error('rates');
            amount = foreignAmountToBase(amount, cur, rates, base);
        }catch(_){
            showNotification(t('rates_not_loaded','Could not load exchange rates'),'error');
            return;
        }
    }
    amount = Math.round(amount * 100) / 100;
    if(!(amount > 0)){ showNotification(t('manual_amount_required','Enter the amount'),'error'); if(input) input.focus(); return; }
    if(manualSubEnabled){ saveManualSubscription(store, amount, date); return; }
    const btn = document.getElementById('manual-save-btn');
    if(btn) btn.disabled = true;
    fetch('', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:new URLSearchParams({update_expense:'1', date, store, amount})})
    .then(r => r.json())
    .then(d => {
        if(d.status !== 'success'){ showNotification(d.message || t('error_generic','Error'),'error'); return; }
        expensesData = (expensesData && typeof expensesData === 'object') ? expensesData : {};
        if(date.startsWith(String(currentChartYear))){
            if(!expensesData[date]) expensesData[date] = {};
            expensesData[date][store] = amount;
        }
        if(!allDates.includes(date)){ allDates.push(date); allDates.sort(); }
        updateFinancialSummary();
        activityPage = 1;
        loadAvailableYears().then(() => updateChart());
        // Show the new row in the table right away.
        const dd = new Date(date + 'T00:00:00');
        currentMonth = dd.getMonth();
        currentYear  = dd.getFullYear();
        pendingScrollDate = date;
        renderMonthPagination();
        loadPage();
        showNotification(t('manual_saved','Expense saved'),'success');
        closeManualAddModal();
    })
    .catch(e => showNotification(`${t('network_error','Network error')}: ${e}`,'error'))
    .finally(() => { if(btn) btn.disabled = false; });
}

// ══ Settings ══════════════════════════════════════════════════════════════
let debounceBudgetTimer=null;
let debounceTableTimer=null;
function scheduleSaveBudgetSettings(){
    clearTimeout(debounceBudgetTimer);
    debounceBudgetTimer=setTimeout(saveBudgetSettingsNow,950);
}
function saveBudgetSettingsNow(){
    const f=new FormData();
    f.append('settings','1');
    f.append('monthly_budget',document.getElementById('monthly-budget').value);
    f.append('currency',document.getElementById('currency').value);
    const showIncomesEl=document.getElementById('show-incomes-toggle');
    if(showIncomesEl&&showIncomesEl.checked)f.append('show_incomes','1');
    const showTrendEl=document.getElementById('show-trend-toggle');
    if(showTrendEl&&showTrendEl.checked)f.append('show_trend','1');
    const trimEmptyEl=document.getElementById('trim-empty-months-toggle');
    if(trimEmptyEl&&trimEmptyEl.checked)f.append('trim_empty_months','1');
    fetch('',{method:'POST',body:f}).then(r=>r.json()).then(d=>{
        if(d.status!=='success'){showNotification(d.message||t('error_generic','Error'),'error');return;}
        userSettings.currency=document.getElementById('currency').value;
        userSettings.monthly_budget=parseFloat(document.getElementById('monthly-budget').value)||0;
        userSettings.show_incomes=!!(showIncomesEl&&showIncomesEl.checked);
        userSettings.show_trend=!!(showTrendEl&&showTrendEl.checked);
        userSettings.trim_empty_months=!!(trimEmptyEl&&trimEmptyEl.checked);
        chartCurrency=userSettings.currency;
        document.querySelectorAll('.currency-btn').forEach(b=>b.classList.toggle('active',b.dataset.currency===chartCurrency));
        loadAvailableYears().then(()=>updateChart());
        loadPage();
    }).catch(e=>showNotification(`${t('network_error','Network error')}: ${e}`,'error'));
}
function scheduleSaveTableSettings(){
    clearTimeout(debounceTableTimer);
    debounceTableTimer=setTimeout(saveTableSettingsNow,950);
}
function saveTableSettingsNow(){
    const f=new FormData();
    f.append('save_table_settings','1');
    const sb=document.getElementById('sort-by');
    if(sb)f.append('sort_by',sb.value);
    const mw=document.getElementById('months-window');
    if(mw)f.append('months_window',mw.value);
    const sd=document.getElementById('start-date');
    if(sd)f.append('start_date',sd.value);
    document.querySelectorAll('.visibility-toggle').forEach(btn=>{
        const sid=btn.getAttribute('data-store-id');
        const vis=btn.getAttribute('data-visible')==='true';
        f.append(vis?'store_visible[]':'store_hidden[]',sid);
    });
    fetch('',{method:'POST',body:f}).then(r=>r.json()).then(d=>{
        if(d.status!=='success'){showNotification(d.message||t('error_generic','Error'),'error');return;}
        document.querySelectorAll('.visibility-toggle').forEach(btn=>{
            const id=btn.getAttribute('data-store-id');
            const v=btn.getAttribute('data-visible')==='true';
            const st=(userSettings.stores||[]).find(s=>String(s.id)===String(id));
            if(st)st.visible=v;
        });
        loadPage(1);
    }).catch(e=>showNotification(`${t('network_error','Network error')}: ${e}`,'error'));
}
function submitBulkDelete(){
    const mode=document.getElementById('bulk-delete-mode')?.value||'range';
    const fd=new FormData();
    fd.append('bulk_delete_data','1');
    fd.append('bd_mode',mode);
    if(mode==='all')fd.append('bd_confirm_all',(document.getElementById('bulk-delete-confirm-all')?.value||'').trim());
    else{
        fd.append('bd_date_from',document.getElementById('bulk-delete-from')?.value||'');
        fd.append('bd_date_to',document.getElementById('bulk-delete-to')?.value||'');
        fd.append('bd_store_id',document.getElementById('bulk-delete-store')?.value||'__all__');
        fd.append('bd_scope',document.getElementById('bulk-delete-scope')?.value||'expenses');
    }
    fetch('',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
        if(d.status!=='success'){
            let m=d.message||t('error_generic','Error');
            if(m==='confirm_delete')m=t('bulk_delete_err_confirm','Type DELETE');
            else if(m==='invalid_dates')m=t('bulk_delete_err_dates','Invalid period');
            else if(m==='invalid_store')m=t('bulk_delete_err_store','Invalid store');
            else if(m==='invalid_mode')m=t('bulk_delete_err_mode','Invalid request');
            showNotification(m,'error');
            return;
        }
        showNotification(t('bulk_delete_ok','Done'),'success');
        loadAvailableYears().then(()=>updateChart());
        loadPage(1);
    }).catch(e=>showNotification(`${t('network_error','Network error')}: ${e}`,'error'));
}
function switchSettingsSection(section){
    document.querySelectorAll('.settings-section').forEach(el=>el.classList.remove('active'));
    document.querySelectorAll('.settings-menu-btn').forEach(btn=>btn.classList.remove('active'));
    const target=document.getElementById(`settings-section-${section}`);
    const activeBtn=document.querySelector(`.settings-menu-btn[data-settings-section="${section}"]`);
    if(target)target.classList.add('active');
    if(activeBtn)activeBtn.classList.add('active');
    updateStoresPagination();
    if(section==='goals') loadGoalsList();
    if(section==='tags') loadTagsList();
}
const settingsListPages={stores:0,tags:0,goals:0};
function settingsItemsPerPage(kind){
    const bodyHeight=document.querySelector('#settings-modal .modal-body')?.clientHeight||Math.round(window.innerHeight*.75);
    const reserve={stores:220,tags:230,goals:150}[kind];
    const rowHeight={stores:64,tags:58,goals:184}[kind];
    return Math.max(1,Math.floor(Math.max(rowHeight,bodyHeight-reserve)/rowHeight));
}
function renderListPagination(kind,totalItems,onPage){
    const host=document.getElementById(`${kind}-pagination`);
    if(!host)return 1;
    const pageSize=settingsItemsPerPage(kind);
    const pageCount=Math.ceil(totalItems/pageSize);
    settingsListPages[kind]=Math.max(0,Math.min(settingsListPages[kind],pageCount-1));
    host.replaceChildren();
    host.hidden=pageCount<=1;
    if(pageCount<=1)return pageSize;
    const previous=document.createElement('button');
    previous.type='button';previous.className='settings-page-btn';previous.textContent='‹';
    previous.setAttribute('aria-label',t('settings_previous','Previous page'));
    previous.disabled=settingsListPages[kind]===0;
    previous.onclick=()=>{settingsListPages[kind]--;onPage();};
    const status=document.createElement('span');
    status.className='settings-page-status';status.setAttribute('aria-live','polite');
    status.textContent=t('settings_page','%1$d of %2$d')
        .replace('%1$d',String(settingsListPages[kind]+1)).replace('%2$d',String(pageCount));
    const next=document.createElement('button');
    next.type='button';next.className='settings-page-btn';next.textContent='›';
    next.setAttribute('aria-label',t('settings_next','Next page'));
    next.disabled=settingsListPages[kind]===pageCount-1;
    next.onclick=()=>{settingsListPages[kind]++;onPage();};
    host.append(previous,status,next);
    return pageSize;
}
function updateStoresPagination(){
    const list=document.getElementById('store-list');
    if(!list)return;
    const items=[...list.querySelectorAll('.store-item')];
    const pageSize=renderListPagination('stores',items.length,updateStoresPagination);
    const start=settingsListPages.stores*pageSize;
    items.forEach((item,index)=>item.style.display=index>=start&&index<start+pageSize?'':'none');
}
function initSettingsListPagination(){
    const list=document.getElementById('store-list');
    if(list&&!list.dataset.paginationReady){
        list.dataset.paginationReady='1';
        new MutationObserver(updateStoresPagination).observe(list,{childList:true});
    }
    updateStoresPagination();
    renderTagsList();renderGoalsList();
}
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',initSettingsListPagination,{once:true});
else setTimeout(initSettingsListPagination,0);
window.addEventListener('resize',()=>{updateStoresPagination();renderTagsList();renderGoalsList();if(lastAnalyticsPayload){buildOCRAnalyticsChart(lastAnalyticsPayload,document.getElementById('ocr-analytics-store')?.value||'__all__');}});

// ══ Tags
let tagsCache=[];
function tagsPost(body){return fetch('',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams(body)}).then(r=>r.json());}
function loadTagsList(){
    fetch('?get_tags=1').then(r=>r.json()).then(d=>{tagsCache=Array.isArray(d.tags)?d.tags:[];renderTagsList();}).catch(()=>{tagsCache=[];renderTagsList();});
}
function renderTagsList(){
    const host=document.getElementById('tags-list');
    if(!host)return;
    host.innerHTML='';
    if(!tagsCache.length){host.innerHTML=`<div class="goals-empty">${t('tags_empty','No tags yet')}</div>`;renderListPagination('tags',0,renderTagsList);return;}
    const pageSize=renderListPagination('tags',tagsCache.length,renderTagsList);
    const start=settingsListPages.tags*pageSize;
    tagsCache.slice(start,start+pageSize).forEach(tag=>{
        const row=document.createElement('div');
        row.className='tag-row';
        row.dataset.tagId=tag.id;
        const chip=document.createElement('span');
        chip.className='tag-chip';
        chip.style.background=tag.color||'#607d8b';
        chip.textContent=tag.name;
        const used=document.createElement('span');
        used.className='tag-usage';
        used.textContent=tag.usage_count?String(tag.usage_count):'';
        const del=document.createElement('button');
        del.type='button';
        del.className='cancel-btn tag-delete';
        del.textContent=t('delete','Delete');
        del.onclick=()=>confirmWithModal(t('tags_delete_confirm','Delete this tag?'),()=>tagsPost({delete_tag:tag.id}).then(loadTagsList));
        row.append(chip,used,del);
        host.appendChild(row);
    });
}
let activeTagPicker='store';
function tagPickerItems(){
    if(activeTagPicker==='store')return (userSettings.stores||[]).map(store=>({
        value:store.name,
        title:store.name,
        meta:store.category?categoryLabel(store.category):t('tags_store_item','Store'),
        icon:store.name.trim().slice(0,1).toUpperCase()||'S',
    }));
    const store=document.getElementById('tag-store').value;
    if(!store)return [];
    return Object.keys(expensesData||{})
        .filter(date=>Number(expensesData[date]?.[store])>0)
        .sort((left,right)=>right.localeCompare(left))
        .map(date=>({
            value:date,
            title:date,
            meta:`${store} · ${money(expensesData[date][store])}`,
            icon:'T',
        }));
}
function renderTagPicker(){
    const host=document.getElementById('tag-picker-list');
    const query=(document.getElementById('tag-picker-search').value||'').trim().toLocaleLowerCase();
    const matches=tagPickerItems().filter(item=>`${item.title} ${item.meta}`.toLocaleLowerCase().includes(query));
    host.replaceChildren();
    if(!matches.length){
        const empty=document.createElement('div');
        empty.className='tag-picker-empty';
        empty.textContent=t('tags_no_matches','No matching results');
        host.appendChild(empty);
        return;
    }
    matches.forEach(item=>{
        const option=document.createElement('button');
        option.type='button';option.className='tag-picker-option';option.setAttribute('role','option');
        const icon=document.createElement('span');icon.className='tag-picker-option-icon';icon.textContent=item.icon;
        const copy=document.createElement('span');copy.className='tag-picker-option-copy';
        const title=document.createElement('span');title.className='tag-picker-option-title';title.textContent=item.title;
        const meta=document.createElement('span');meta.className='tag-picker-option-meta';meta.textContent=item.meta;
        copy.append(title,meta);option.append(icon,copy);
        option.onclick=()=>{
            if(activeTagPicker==='store'){
                document.getElementById('tag-store').value=item.value;
                document.getElementById('tag-store-label').textContent=item.title;
                document.getElementById('tag-payment').value='';
                document.getElementById('tag-payment-label').textContent=t('tags_choose_payment','Choose a payment');
                document.getElementById('tag-payment-picker').disabled=false;
            }else{
                document.getElementById('tag-payment').value=item.value;
                document.getElementById('tag-payment-label').textContent=`${item.title} · ${item.meta.split(' · ').slice(-1)[0]}`;
            }
            closeModal('tag-picker-modal');
        };
        host.appendChild(option);
    });
}
function openTagPicker(kind){
    activeTagPicker=kind;
    document.getElementById('tag-picker-title').textContent=kind==='store'?t('tags_choose_store','Choose a store'):t('tags_choose_payment','Choose a payment');
    const search=document.getElementById('tag-picker-search');
    search.value='';
    renderTagPicker();
    showModal('tag-picker-modal');
    search.focus();
}
document.getElementById('tag-picker-search')?.addEventListener('input',renderTagPicker);
async function saveTagFromForm(){
    const name=(document.getElementById('tag-name').value||'').trim();
    if(!name){showNotification(t('tags_name_required','Name is required'),'error');return;}
    const store=document.getElementById('tag-store').value;
    const date=document.getElementById('tag-payment').value;
    if(!store){showNotification(t('tags_choose_store_error','Choose a store first'),'error');return;}
    if(!date){showNotification(t('tags_choose_payment_error','Choose a payment first'),'error');return;}
    try{
        const saved=await tagsPost({save_tag:'1',name,color:document.getElementById('tag-color').value});
        if(saved.status!=='success'||!saved.tag?.id){showNotification(saved.message||t('tags_save_failed','Could not save tag'),'error');return;}
        const query=new URLSearchParams({get_expense_tags:'1',from:date,to:date});
        const tagged=await fetch('?'+query.toString()).then(response=>response.json());
        const key=`${date}\t${store}`;
        const tagIds=[...new Set([...(tagged.expense_tags?.[key]||[]),saved.tag.id])];
        const assigned=await tagsPost({set_expense_tags:'1',date,store,tag_ids:JSON.stringify(tagIds)});
        if(assigned.status!=='success'){showNotification(assigned.message||t('tags_assign_failed','Could not assign tag'),'error');return;}
        document.getElementById('tag-name').value='';
        loadTagsList();
        showNotification(t('tags_saved','Tag saved'),'success');
    }catch(error){showNotification(`${t('network_error','Network error')}: ${error}`,'error');}
}
// ══ Goals ═══════════════════════════════════════════════════════════════════
let goalsCache=[];
function goalsPost(body){
    return fetch('',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams(body)}).then(r=>r.json());
}
function loadGoalsList(){
    fetch('?get_goals=1&include_archived=1').then(r=>r.json()).then(d=>{
        goalsCache=Array.isArray(d.goals)?d.goals:[];
        renderGoalsList();
    }).catch(()=>{goalsCache=[];renderGoalsList();});
}
function renderGoalsList(){
    const host=document.getElementById('goals-list');
    if(!host)return;
    host.innerHTML='';
    if(!goalsCache.length){
        host.innerHTML=`<div class="goals-empty">${t('goals_empty','No goals yet')}</div>`;
        renderListPagination('goals',0,renderGoalsList);
        return;
    }
    const pageSize=renderListPagination('goals',goalsCache.length,renderGoalsList);
    const start=settingsListPages.goals*pageSize;
    goalsCache.slice(start,start+pageSize).forEach(g=>{
        const card=document.createElement('div');
        card.className='goal-card'+(g.is_archived?' is-archived':'');
        const pct=Math.max(0,Math.min(100,Number(g.progress_percent)||0));
        const typeLabel=g.goal_type==='expense'?t('goals_type_expense','Spending limit'):t('goals_type_savings','Savings');
        card.innerHTML=`<div class="goal-card-head"><strong></strong><span class="goal-type"></span></div>
            <div class="goal-amounts"><span class="goal-current"></span><span class="goal-target"></span></div>
            <div class="goal-bar"><div class="goal-bar-fill" style="width:${pct}%"></div></div>
            <div class="goal-meta"></div>
            <div class="goal-actions"></div>`;
        card.querySelector('strong').textContent=g.name||'';
        card.querySelector('.goal-type').textContent=typeLabel+(g.is_archived?' · '+t('goals_archived','Archived'):'');
        card.querySelector('.goal-current').textContent=`${Number(g.current_amount||0).toFixed(2)} ${g.currency||''}`;
        card.querySelector('.goal-target').textContent=`/ ${Number(g.target_amount||0).toFixed(2)} (${pct}%)`;
        card.querySelector('.goal-meta').textContent=g.target_date?`${t('goals_date','Target date')}: ${g.target_date}`:(g.description||'');
        const actions=card.querySelector('.goal-actions');
        const mk=(label,fn)=>{const b=document.createElement('button');b.type='button';b.className='cancel-btn';b.textContent=label;b.onclick=fn;return b;};
        actions.appendChild(mk(t('edit','Edit'),()=>openGoalEditor(g)));
        actions.appendChild(mk(t('goals_contribute','+ Progress'),()=>{
            requestNumberInput(t('goals_contribute','Add progress'),t('goals_contribute_prompt','Amount to add'),'10').then(raw=>{
                if(raw==null)return;
                const amount=parseFloat(raw);
                if(!(amount>0))return;
                goalsPost({contribute_goal:g.id,id:g.id,amount}).then(res=>{
                    if(res.status!=='success'){showNotification(res.message||t('error_generic','Error'),'error');return;}
                    loadGoalsList();
                });
            });
        }));
        actions.appendChild(mk(g.is_archived?t('goals_unarchive','Restore'):t('goals_archive','Archive'),()=>{
            goalsPost({archive_goal:g.id,is_archived:g.is_archived?0:1}).then(()=>loadGoalsList());
        }));
        actions.appendChild(mk(t('delete','Delete'),()=>{
            confirmWithModal(t('goals_delete_confirm','Delete this goal?'),()=>goalsPost({delete_goal:g.id}).then(()=>loadGoalsList()));
        }));
        host.appendChild(card);
    });
}
function openGoalEditor(goal){
    const title=document.getElementById('goal-editor-title');
    if(title)title.textContent=goal&&goal.id?t('edit','Edit'):t('goals_add','Add goal');
    document.getElementById('goal-id').value=goal&&goal.id?goal.id:'';
    document.getElementById('goal-name').value=goal&&goal.name?goal.name:'';
    document.getElementById('goal-type').value=goal&&goal.goal_type?goal.goal_type:'savings';
    document.getElementById('goal-target').value=goal?Number(goal.target_amount||0):0;
    document.getElementById('goal-current').value=goal?Number(goal.current_amount||0):0;
    document.getElementById('goal-date').value=goal&&goal.target_date?goal.target_date:'';
    document.getElementById('goal-description').value=goal&&goal.description?goal.description:'';
    showModal('goal-editor-modal');
    document.getElementById('goal-name').focus();
}
function closeGoalEditor(){
    closeModal('goal-editor-modal');
}
function saveGoalEditor(){
    const payload={
        save_goal:'1',
        id:document.getElementById('goal-id').value||'',
        name:document.getElementById('goal-name').value.trim(),
        goal_type:document.getElementById('goal-type').value,
        target_amount:document.getElementById('goal-target').value||0,
        current_amount:document.getElementById('goal-current').value||0,
        target_date:document.getElementById('goal-date').value||'',
        description:document.getElementById('goal-description').value||'',
        currency:(userSettings&&userSettings.currency)||'USD'
    };
    if(!payload.name){showNotification(t('goals_name_required','Name is required'),'error');return;}
    goalsPost(payload).then(res=>{
        if(res.status!=='success'){showNotification(res.message||t('error_generic','Error'),'error');return;}
        closeGoalEditor();
        loadGoalsList();
        showNotification(t('goals_saved','Goal saved'),'success');
    }).catch(e=>showNotification(`${t('network_error','Network error')}: ${e}`,'error'));
}

// ══ Stores ════════════════════════════════════════════════════════════════
let currentStoreId=null,storePhotoFile=null,pendingOCRStoreRaw='',storePhotoObjectUrl='';
const cropIconSvg='<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="6" cy="6" r="3"/><circle cx="6" cy="18" r="3"/><line x1="20" y1="4" x2="8.12" y2="15.88"/><line x1="14.47" y1="14.48" x2="20" y2="20"/><line x1="8.12" y1="8.12" x2="12" y2="12"/></svg>';
const storeCategoryIcons={food:'M4 10h16M6 10l1 10h10l1-10M8 10V6a4 4 0 0 1 8 0v4',groceries:'M4 10h16M6 10l1 10h10l1-10M9 10V7a3 3 0 0 1 6 0v3',cafes:'M5 8h12v5a5 5 0 0 1-5 5H10a5 5 0 0 1-5-5V8Zm12 2h2a2 2 0 0 1 0 4h-2M6 4h10',clothes:'M16 4l4 4-3 3v9H7v-9L4 8l4-4 2 2h4l2-2Z',shoes:'M4 15c4 0 7-1 10-5l6 5v3H4v-3Z',home:'M3 11l9-7 9 7v9H5v-9Zm6 9v-6h6v6',apartment:'M6 21V3h12v18M9 7h2m2 0h2M9 11h2m2 0h2M9 15h2m2 0h2',utilities:'M13 2 5 14h6l-1 8 8-12h-6l1-8Z',transport:'M6 17h12l1-6-2-5H7l-2 5 1 6Zm1 0a2 2 0 1 0 0 4 2 2 0 0 0 0-4Zm10 0a2 2 0 1 0 0 4 2 2 0 0 0 0-4Z',car:'M5 16h14l-1-5-2-4H8l-2 4-1 5Zm2 0a2 2 0 1 0 0 4 2 2 0 0 0 0-4Zm10 0a2 2 0 1 0 0 4 2 2 0 0 0 0-4Z',travel:'M3 11l18-8-8 18-2-8-8-2Z',health:'M12 21s-7-4.4-7-10a4 4 0 0 1 7-2.6A4 4 0 0 1 19 11c0 5.6-7 10-7 10Zm0-9v4m-2-2h4',pharmacy:'M10 3h4v6h6v4h-6v8h-4v-8H4V9h6V3Z',beauty:'M12 3c4 4 4 8 0 12-4-4-4-8 0-12Zm-6 18h12',children:'M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm-7 9a7 7 0 0 1 14 0',pets:'M5 10a2 2 0 1 0 0-4 2 2 0 0 0 0 4Zm14 0a2 2 0 1 0 0-4 2 2 0 0 0 0 4Zm-7 2c3 0 6 3 6 6H6c0-3 3-6 6-6Z',build:'M4 20h16M7 20V9l5-5 5 5v11M9 12h6M9 16h6',education:'M3 7l9-4 9 4-9 4-9-4Zm4 4v5c3 2 7 2 10 0v-5',sports:'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Zm-6-9h12M12 3c3 3 3 15 0 18M12 3c-3 3-3 15 0 18',electronics:'M4 5h16v11H4V5Zm6 15h4',subscriptions:'M7 7h10M7 12h10M7 17h6M5 3h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z',entertainment:'M7 4v16l10-8L7 4Z',gifts:'M20 12v8H4v-8M2 7h20v5H2V7Zm10 0v13M12 7C9 7 8 3 10 3s2 4 2 4Zm0 0c3 0 4-4 2-4s-2 4-2 4Z',services:'M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Zm0-13v3m0 14v3M4.2 4.2l2.1 2.1m11.4 11.4 2.1 2.1M2 12h3m14 0h3M4.2 19.8l2.1-2.1M17.7 6.3l2.1-2.1',finance:'M4 7h16M6 7a6 6 0 0 1 12 0M6 7v13h12V7M9 12h6M9 16h6',taxes:'M6 2h9l5 5v15H6V2Zm8 0v6h6M9 13h6M9 17h4',insurance:'M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z',charity:'M12 21s-7-4.4-7-10a4 4 0 0 1 7-2.6A4 4 0 0 1 19 11c0 5.6-7 10-7 10Z',other:'M12 3l9 5-9 5-9-5 9-5Zm-7 9 7 4 7-4M5 16l7 4 7-4'};
// Material 3 outlined visibility / visibility_off.
const visEyeSvg='<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2.5 12S6 5.75 12 5.75 21.5 12 21.5 12 18 18.25 12 18.25 2.5 12 2.5 12Z"/><circle cx="12" cy="12" r="3.25"/></svg>';
const visEyeOffSvg='<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2.5 12S6 5.75 12 5.75 21.5 12 21.5 12 18 18.25 12 18.25 2.5 12 2.5 12Z"/><circle cx="12" cy="12" r="3.25"/><path d="M3.5 3.5 20.5 20.5"/></svg>';
function storeCategoryIcon(key){return `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="${storeCategoryIcons[key]||storeCategoryIcons.other}"/></svg>`;}
function closeAddEditStoreModal(){pendingOCRStoreRaw='';closeModal('add-edit-store-modal');}
function selectedStoreCategories(){return new Set((userSettings.stores||[]).filter(s=>String(s.id)!==String(currentStoreId)).map(s=>s.category||'other'));}
function refreshCategoryPicker(){const native=document.getElementById('store-category-input'),picker=document.getElementById('store-category-picker');if(!native||!picker)return;const used=selectedStoreCategories();picker.innerHTML='';Array.from(native.options).forEach(option=>{const btn=document.createElement('button');btn.type='button';btn.className='store-category-option';btn.dataset.value=option.value;btn.dataset.used=used.has(option.value)?'1':'0';btn.innerHTML=`${storeCategoryIcon(option.value)}<span>${option.textContent}</span>`;btn.onclick=()=>{native.value=option.value;refreshCategoryPicker();};picker.appendChild(btn);});picker.querySelectorAll('.store-category-option').forEach(btn=>btn.classList.toggle('active',btn.dataset.value===native.value));}
function showAddEditStoreModal(id=null,name='',visible=true,ocrRaw='',category=null){currentStoreId=id;pendingOCRStoreRaw=ocrRaw||'';document.getElementById('add-edit-store-title').textContent=id?translations.edit_store:translations.add_store;document.getElementById('store-name-input').value=name;const cat=document.getElementById('store-category-input');const current=(userSettings.stores||[]).find(s=>String(s.id)===String(id));if(cat)cat.value=category||current?.category||'other';document.getElementById('store-photo-input').value='';const card=document.getElementById('store-photo-crop-card'),preview=document.getElementById('store-photo-preview'),btn=document.getElementById('crop-store-btn');if(card)card.style.display='none';if(preview){preview.removeAttribute('src');preview.style.display='block';}if(btn){btn.style.display='none';btn.innerHTML=cropIconSvg+'<span>'+(translations.crop_photo||'Crop Photo')+'</span>';}if(cropper){cropper.destroy();cropper=null;}if(storePhotoObjectUrl){URL.revokeObjectURL(storePhotoObjectUrl);storePhotoObjectUrl='';}storePhotoFile=null;refreshCategoryPicker();showModal('add-edit-store-modal');}
function previewStorePhoto(input){const file=input.files&&input.files[0];if(!file)return;if(cropper){cropper.destroy();cropper=null;}if(storePhotoObjectUrl)URL.revokeObjectURL(storePhotoObjectUrl);storePhotoFile=file;storePhotoObjectUrl=URL.createObjectURL(file);const p=document.getElementById('store-photo-preview'),card=document.getElementById('store-photo-crop-card'),btn=document.getElementById('crop-store-btn');p.src=storePhotoObjectUrl;p.style.display='block';if(card)card.style.display='flex';if(btn){btn.style.display='inline-flex';btn.innerHTML=cropIconSvg+'<span>'+(translations.crop_photo||'Crop Photo')+'</span>';}}
function cropStorePhoto(){const p=document.getElementById('store-photo-preview');if(!p||!p.getAttribute('src'))return;currentCropType='pending-store';currentCropId='';const ci=document.getElementById('crop-image');ci.src=p.src;showModal('crop-modal');requestAnimationFrame(()=>{if(cropper)cropper.destroy();cropper=new Cropper(ci,{aspectRatio:1,viewMode:2,autoCropArea:.95,background:false,responsive:true,restore:false,checkOrientation:true,guides:true,center:true,dragMode:'move',cropBoxMovable:true,cropBoxResizable:true,minCropBoxWidth:40,minCropBoxHeight:40});});}
function rekeyExpensesDataStore(oldN,newN){if(!oldN||oldN===newN)return;Object.keys(expensesData||{}).forEach(d=>{const o=expensesData[d];if(o&&Object.prototype.hasOwnProperty.call(o,oldN)){o[newN]=o[oldN];delete o[oldN];}});}
function storePhotoUrl(s,withPhoto){if(!withPhoto||!s||!s.photo_url)return'';return `${s.photo_url}${s.photo_url.includes('?')?'&':'?'}t=${Date.now()}`;}
function applyStoreToSettingsUI(s,withPhoto){if(!s)return;const idStr=String(s.id);const prevI=(userSettings.stores||[]).findIndex(x=>String(x.id)===idStr);if(prevI>=0){rekeyExpensesDataStore(userSettings.stores[prevI].name,s.name);userSettings.stores[prevI]={...userSettings.stores[prevI],...s};}else{userSettings.stores.push(s);Object.keys(expensesData||{}).forEach(d=>{if(expensesData[d]&&typeof expensesData[d][s.name]==='undefined')expensesData[d][s.name]=0;});}const list=document.getElementById('store-list');if(!list)return;let row=list.querySelector(`.store-item[data-store-id="${CSS.escape(idStr)}"]`);const photoU=storePhotoUrl(s,withPhoto);if(!row){row=document.createElement('div');row.className='store-item';row.dataset.storeId=idStr;row.innerHTML=`<span style="flex:1;"></span><button class="visibility-toggle" data-store-id="${idStr}" data-visible="${s.visible!==false?'true':'false'}" title="${translations.visible||'Visible'}"></button><button class="edit-store-btn" type="button">${translations.edit_store||'Edit'}</button><button class="delete-store-btn" type="button"><svg viewBox="0 0 24 24"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg> ${translations.delete||'Delete'}</button>`;list.appendChild(row);}row.dataset.storeName=s.name;row.setAttribute('data-visible',s.visible!==false?'true':'false');row.querySelector('span').textContent=s.name;row.querySelector('.edit-store-btn').onclick=()=>showAddEditStoreModal(s.id,s.name,s.visible,'',s.category);row.querySelector('.delete-store-btn').onclick=()=>confirmDeleteStore(s.id,s.name);const vt=row.querySelector('.visibility-toggle');if(vt){vt.setAttribute('data-visible',s.visible!==false?'true':'false');vt.innerHTML=s.visible!==false?visEyeSvg:visEyeOffSvg;vt.onclick=()=>toggleStoreVisibility(vt);}let img=row.querySelector('img.store-icon');if(photoU){if(!img){img=document.createElement('img');img.className='store-icon';row.insertBefore(img,row.firstChild);}img.src=photoU;img.alt=s.name;}refreshCategoryPicker();loadAvailableYears().then(()=>updateChart());loadPage(1);}
function saveStore(){const name=document.getElementById('store-name-input').value.trim();if(!name){showNotification(t('store_name_required','Store name is required'),'error');return;}const withPhoto=!!storePhotoFile,pendingRaw=pendingOCRStoreRaw,fd=new FormData();fd.append('add_edit_store','1');fd.append('store_id',currentStoreId||Date.now());fd.append('store_name',name);fd.append('store_category',document.getElementById('store-category-input')?.value||'other');if(storePhotoFile)fd.append('store_photo',storePhotoFile,'store_photo.png');fetch('',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{if(d.status!=='success'){showNotification(d.message||t('error_generic','Error'),'error');return;}closeAddEditStoreModal();showNotification(translations.store_saved||'Store saved','success');if(d.store)applyStoreToSettingsUI(d.store,withPhoto);if(pendingRaw&&d.store){const selected=readUnresolvedSelections();selected[pendingRaw]=d.store.name;renderUnresolvedModal(selected);}storePhotoFile=null;}).catch(e=>showNotification(`${t('network_error','Network error')}: ${e}`,'error'));}
function confirmDeleteStore(id,name){pendingConfirmAction=null;pendingDelete={type:'store',id,name};document.getElementById('confirm-delete-title').textContent=translations.confirm_delete_store||t('confirm_action_title','Confirm action');document.getElementById('confirm-delete-message').textContent=translations.confirm_delete_store_message||`Delete store "${name}"?`;document.getElementById('confirm-delete-action').textContent=t('delete','Delete');showModal('confirm-delete-modal');}
function executeDelete(){const action=pendingConfirmAction;pendingConfirmAction=null;closeModal('confirm-delete-modal');if(action){action();return;}if(pendingDelete.type==='store')deleteStore(pendingDelete.id,pendingDelete.name);}
function removeDataTableStoreColumnByName(storeName){const ths=document.querySelectorAll('#data-table thead th');let col=-1;ths.forEach((th,idx)=>{if(idx>0&&th.textContent.trim()===storeName)col=idx;});if(col<0)return;document.querySelectorAll('#data-table tr').forEach(tr=>{const c=tr.children[col];if(c)c.remove();});if(typeof updateChart==='function')updateChart();}
function deleteStore(id,name){const fd=new FormData();fd.append('delete_store','1');fd.append('store_id',id);fd.append('store_name',name);fetch('',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{if(d.status!=='success'){showNotification(d.message||t('error_generic','Error'),'error');return;}const idS=String(id);userSettings.stores=userSettings.stores.filter(s=>String(s.id)!==idS);Object.keys(expensesData||{}).forEach(dt=>{if(expensesData[dt]&&Object.prototype.hasOwnProperty.call(expensesData[dt],name))delete expensesData[dt][name];});document.querySelector(`#store-list .store-item[data-store-id="${CSS.escape(idS)}"]`)?.remove();document.querySelector(`.store-visibility-table .visibility-toggle[data-store-id="${CSS.escape(idS)}"]`)?.closest('tr')?.remove();removeDataTableStoreColumnByName(name);refreshCategoryPicker();showNotification(translations.delete_store_ok||'Store deleted','success');closeModal('confirm-delete-modal');}).catch(e=>showNotification(`${t('network_error','Network error')}: ${e}`,'error'));}
function toggleStoreVisibility(btn){const nv=btn.getAttribute('data-visible')!=='true';btn.setAttribute('data-visible',nv?'true':'false');btn.innerHTML=nv?visEyeSvg:visEyeOffSvg;const row=btn.closest('.store-item');if(row)row.setAttribute('data-visible',nv?'true':'false');scheduleSaveTableSettings();}

// ══ Crop modal ═════════════════════════════════════════════════════════════
function closeCropModal(){closeModal('crop-modal');if(cropper){cropper.destroy();cropper=null;}}
function cropAndSave(){if(!cropper)return;const canvas=cropper.getCroppedCanvas({width:512,height:512,imageSmoothingEnabled:true,imageSmoothingQuality:'high'});if(!canvas){showNotification(translations.crop_failed||'Could not crop image, try again','error');return;}canvas.toBlob(blob=>{if(!blob){showNotification(translations.crop_failed||'Could not crop image, try again','error');return;}if(currentCropType==='pending-store'){storePhotoFile=blob;if(storePhotoObjectUrl)URL.revokeObjectURL(storePhotoObjectUrl);storePhotoObjectUrl=URL.createObjectURL(blob);const p=document.getElementById('store-photo-preview');p.src=storePhotoObjectUrl;document.getElementById('store-photo-crop-card').style.display='flex';closeCropModal();return;}if(currentCropType!=='store'||!currentCropId){closeCropModal();return;}const st=(userSettings.stores||[]).find(x=>String(x.id)===String(currentCropId));const n=(st&&st.name)||'';if(!n){showNotification(t('store_not_found','Store not found'),'error');return;}const fd=new FormData();fd.append('add_edit_store','1');fd.append('store_id',currentCropId);fd.append('store_name',n);fd.append('store_category',st.category||'other');fd.append('store_photo',blob,'store_photo.png');fetch('',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{if(d.status!=='success'){showNotification(d.message||t('error_generic','Error'),'error');return;}closeCropModal();if(d.store)applyStoreToSettingsUI(d.store,true);else showNotification(translations.store_saved||'Store saved','success');});},'image/png');}

// ══ Backup ═════════════════════════════════════════════════════════════════
function downloadBackup(fn){window.location.href='?download_backup='+encodeURIComponent(fn);}
function saveBackup(){
    const name=document.getElementById('backup-name').value;
    if(!name){showNotification(t('enter_backup_name','Please enter backup name first'),'error');return;}
    // First organize data
    fetch('',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams({backup_action:'organize'})})
    .then(r=>r.json()).then(d=>{
        if(d.status!=='success'){showNotification(d.message||t('organize_failed','Organize failed'),'error');return;}
        // Then save backup
        return fetch('',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams({backup_action:'save',backup_name:name})});
    })
    .then(r=>r.json()).then(d=>{
        if(d.status==='success'){showNotification(d.message,'success');setTimeout(()=>location.reload(),1000);}
        else showNotification(d.message||t('error_generic','Error'),'error');
    });
}
let pendingRestoreFile=null;
function restoreBackup(fn){
    pendingRestoreFile=fn;
    const t=translations.restore_backup_title||'Restore backup?';
    const p=translations.confirm_restore_backup||'';
    const ht=document.getElementById('restore-backup-title');
    const htx=document.getElementById('restore-backup-text');
    if(ht)ht.textContent=t;
    if(htx)htx.innerHTML=p?p.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/\n/g,'<br>'):'';
    showModal('confirm-restore-backup-modal');
}
function executeRestoreBackup(){
    if(!pendingRestoreFile)return;
    const fn=pendingRestoreFile;
    pendingRestoreFile=null;
    closeModal('confirm-restore-backup-modal');
    fetch('',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams({backup_action:'restore',backup_file:fn})})
    .then(r=>r.json()).then(d=>{if(d.status==='success'){showNotification(t('backup_restored','Backup restored'),'success');setTimeout(()=>location.reload(),1000);}else showNotification(d.message||t('error_generic','Error'),'error');});
}
function deleteBackup(fn){
    confirmWithModal(t('confirm_delete_backup','Are you sure you want to delete this backup?'),()=>{
        fetch('',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams({backup_action:'delete',backup_file:fn})})
        .then(r=>r.json()).then(d=>{if(d.status==='success'){showNotification(t('backup_deleted','Backup deleted'),'success');setTimeout(()=>location.reload(),1000);}else showNotification(d.message||t('error_generic','Error'),'error');});
    });
}
function uploadBackup(){
    const fi=document.getElementById('upload-backup-file');
    if(!fi.files[0]){showNotification(t('select_file_first','Please select a file'),'error');return;}
    const fd=new FormData();fd.append('backup_action','upload');fd.append('backup_file',fi.files[0]);
    fetch('',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{if(d.status==='success'){showNotification(t('backup_uploaded','Backup uploaded'),'success');fi.value='';setTimeout(()=>location.reload(),1000);}else showNotification(d.message||t('error_generic','Error'),'error');}).catch(e=>showNotification(`${t('upload_error','Upload error')}: ${e}`,'error'));
}
function viewBackup(fn) {
    fetch(`?download_backup=${encodeURIComponent(fn)}`).then(r=>r.text()).then(data=>{
        const preview=document.getElementById('backup-preview');
        const lines=data.trim().split('\n');
        if(!lines.length){preview.innerHTML=`<div style="text-align:center;padding:20px;color:#666;">${t('empty_backup','Empty backup')}</div>`;showModal('view-backup-modal');return;}
        const headers=lines[0].split(',').map(h=>h.trim());
        let html=`<div style="margin-bottom:20px;"><h4 style="margin:0 0 5px;color:var(--text-color);">${fn}</h4><div style="font-size:12px;color:#666;">${t('record_count','{count} records').replace('{count}',lines.length-1)}</div></div><div style="overflow:auto;max-height:65vh;"><table style="width:100%;border-collapse:collapse;color:var(--text-color);font-size:14px;"><thead><tr style="background:var(--primary-color);color:white;">`;
        headers.forEach(h=>html+=`<th style="padding:12px;text-align:left;border:1px solid var(--border-color);font-weight:bold;">${h}</th>`);
        html+='</tr></thead><tbody>';
        let total=0;
        for(let i=1;i<lines.length;i++){
            if(!lines[i].trim())continue;
            const cells=lines[i].split(',').map(c=>c.trim());
            html+='<tr>';let rt=0;
            cells.forEach((cell,idx)=>{
                let cc=cell,st='padding:10px;border:1px solid var(--border-color);';
                if(idx===0){cc=formatDateWithMonthName(cell);st+='font-weight:500;';}
                else{const n=parseFloat(cell);if(!isNaN(n)){cc=n.toFixed(2);rt+=n;st+='text-align:right;font-family:monospace;';if(n>0)st+='color:green;font-weight:500;';}}
                html+=`<td style="${st}">${cc}</td>`;
            });
            if(rt>0){total+=rt;html+=`<td style="padding:10px;border:1px solid var(--border-color);text-align:right;font-family:monospace;font-weight:bold;background:var(--group-block-bg);">${rt.toFixed(2)}</td>`;}
            html+='</tr>';
        }
        html+='</tbody>';
        if(total>0)html+=`<tfoot><tr style="background:var(--primary-color);color:white;"><td colspan="${headers.length}" style="padding:12px;text-align:right;font-weight:bold;">${t('total_label','Total:')}</td><td style="padding:12px;text-align:right;font-family:monospace;font-weight:bold;">${total.toFixed(2)}</td></tr></tfoot>`;
        html+='</table></div>';
        preview.innerHTML=html;showModal('view-backup-modal');
    }).catch(e=>showNotification(`${t('error_loading_backup','Error loading backup')}: ${e}`,'error'));
}
function formatDateWithMonthName(s){
    s=s.replace(/['"]/g,'');
    let d;
    if(s.match(/^\d{4}-\d{2}-\d{2}$/)){const p=s.split('-');d=new Date(p[0],p[1]-1,p[2]);}
    else if(s.match(/^\d{2}-\d{2}-\d{4}$/)){const p=s.split('-');d=new Date(p[2],p[1]-1,p[0]);}
    else if(s.match(/^\d{2}-\d{2}-\d{2}$/)){const p=s.split('-');d=new Date('20'+p[2],p[1]-1,p[0]);}
    else d=new Date(s);
    if(isNaN(d)) return s;
    return `${d.getDate()} ${monthNames[d.getMonth()]} ${d.getFullYear()}`;
}

function openImportModal(){
    const im=document.getElementById('import-modal');
    im.style.display='flex';
    syncModalBodyState();
    if(location.hash!=='#ocr'){location.hash='ocr';}
    setOCRLog([ocrT.waiting]);
    if(window.embeddedOcrWizard&&typeof window.embeddedOcrWizard.restore==='function'){window.embeddedOcrWizard.restore();}
}
function closeImportModal(){
    document.getElementById('import-modal').style.display='none';
    syncModalBodyState();
    if(location.hash==='#ocr'){history.replaceState(null,'',`${location.pathname}${location.search}`);}
}
let bankCsvState=null;
function closeImportMenu(){const m=document.getElementById('import-menu');if(m)m.style.display='none';}
function toggleImportMenu(e){e.stopPropagation();const m=document.getElementById('import-menu');if(!m)return;m.style.display=m.style.display==='block'?'none':'block';}
function closeBankCsvModal(){closeModal('bank-csv-import-modal');bankCsvState=null;const f=document.getElementById('bank-csv-input');if(f)f.value='';}
function openBankCsvModal(){showModal('bank-csv-import-modal');}
function renderBankCsvAnalysis(d){
    bankCsvState={token:d.token,groups:d.groups||[],conflict_dates:d.conflict_dates||[]};
    const meta=document.getElementById('bank-csv-meta');
    const dup=d.duplicate_count||0;
    const rc=d.row_count||0;
    const cf=(d.confidence!=null?d.confidence:'—');
    meta.textContent=`${t('bank_csv_rows','Rows')}: ${rc} · ${t('bank_csv_duplicates','Duplicates')}: ${dup} · ${t('bank_csv_confidence','Confidence')}: ${cf}`;
    const cb=document.getElementById('bank-csv-conflict-box');
    const ct=document.getElementById('bank-csv-conflict-text');
    if((d.conflict_dates||[]).length){cb.style.display='block';ct.textContent=t('bank_csv_conflicts','');}else{cb.style.display='none';}
    const wrap=document.getElementById('bank-csv-groups');
    const stores=(userSettings.stores||[]).map(s=>s.name).filter(Boolean);
    const thInc=String(t('bank_csv_include','')).replace(/</g,'&lt;');
    const thLab=String(t('bank_csv_col_label','Category')).replace(/</g,'&lt;');
    const thDates=String(t('bank_csv_col_dates','Dates')).replace(/</g,'&lt;');
    const thStore=String(t('bank_csv_store_map','Store')).replace(/</g,'&lt;');
    const thAmt=String(t('amount','Amount')).replace(/</g,'&lt;');
    const rows=(d.groups||[]).map(g=>{
        const lab=String(g.label||'').replace(/</g,'&lt;');
        const inc=g.income?` <span class="bank-csv-inc-tag">(${String(t('bank_csv_income_group','Income')).replace(/</g,'&lt;')})</span>`:'';
        const opts=stores.map(sn=>`<option value="${String(sn).replace(/"/g,'&quot;')}">${String(sn).replace(/</g,'&lt;')}</option>`).join('');
        const ds=(g.dates||[]).join(', ')||'—';
        const gk=String(g.key||'').replace(/&/g,'&amp;').replace(/"/g,'&quot;');
        return `<tr data-gk="${gk}"><td class="bank-csv-td-inc"><input type="checkbox" checked data-gk="${gk}" class="bank-csv-inc bank-csv-ch" aria-label="${thInc}"></td><td class="bank-csv-td-label"><strong>${lab}</strong>${inc}</td><td class="bank-csv-td-dates"><span class="bank-csv-n">n=${g.count}</span><div class="bank-csv-dates">${String(ds).replace(/</g,'&lt;')}</div></td><td class="bank-csv-td-sel"><select class="bank-csv-store bank-csv-sel" data-gk="${gk}">${opts}</select></td><td class="bank-csv-td-amt">${g.total.toFixed(2)}</td></tr>`;
    }).join('');
    wrap.innerHTML=`<table class="bank-csv-table"><colgroup><col class="bank-csv-col-inc"><col><col class="bank-csv-col-dates"><col class="bank-csv-col-store"><col class="bank-csv-col-amt"></colgroup><thead><tr><th>${thInc}</th><th>${thLab}</th><th>${thDates}</th><th>${thStore}</th><th class="bank-csv-th-num">${thAmt}</th></tr></thead><tbody>${rows}</tbody></table>`;
    wrap.querySelectorAll('select.bank-csv-store').forEach(sel=>{
        const gk=sel.getAttribute('data-gk');
        const g=(d.groups||[]).find(x=>String(x.key)===gk);
        const want=g&&(g.income?(stores[0]||''):pickStoreForMerchant(String(g.label||'')));
        Array.from(sel.options).forEach(o=>{if(o.value===want)sel.value=o.value;});
        if(!Array.from(sel.options).some(o=>o.value===sel.value)&&sel.options.length){sel.selectedIndex=0;}
    });
    const sels=Array.from(wrap.querySelectorAll('select.bank-csv-store'));
    sels.forEach((sel,idx)=>{
        sel.addEventListener('change',()=>{
            const nx=sels[idx+1];
            if(!nx)return;
            setTimeout(()=>{
                nx.focus();
                try{if(typeof nx.showPicker==='function')nx.showPicker();}catch(_){}
            },0);
        });
    });
}
function pickStoreForMerchant(label){
    const stores=(userSettings.stores||[]).map(s=>s.name).filter(Boolean);
    const nk=String(label).toLowerCase().replace(/\s+/g,' ').trim();
    for(let i=0;i<stores.length;i++){if(stores[i].toLowerCase()===nk)return stores[i];}
    let best=stores[0]||label,sc=0;
    for(let i=0;i<stores.length;i++){
        const sn=stores[i],b=sn.toLowerCase();
        const L=Math.min(nk.length,b.length)||1;
        let m=0;
        for(let j=0;j<L;j++){if(nk[j]===b[j])m++;}
        const pct=200*m/(nk.length+b.length+0.01);
        if(pct>sc){sc=pct;best=sn;}
    }
    return sc>=35?best:(stores[0]||label);
}
function submitBankCsvImport(){
    if(!bankCsvState||!bankCsvState.token){showNotification(t('error_generic','Error'),'error');return;}
    const ex=[];
    document.querySelectorAll('.bank-csv-inc').forEach(ch=>{if(!ch.checked)ex.push(ch.getAttribute('data-gk'));});
    const sm={};
    document.querySelectorAll('select.bank-csv-store').forEach(sel=>{sm[sel.getAttribute('data-gk')]=sel.value;});
    let cm='skip';
    document.querySelectorAll('input[name="bank-csv-conflict"]').forEach(r=>{if(r.checked)cm=r.value;});
    const fd=new FormData();
    fd.append('bank_csv_apply',JSON.stringify({token:bankCsvState.token,exclude_group_keys:ex,store_map:sm,conflict_mode:cm,conflict_dates:bankCsvState.conflict_dates||[]}));
    fetch('',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
        if(d.status==='success'){showNotification(t('bank_csv_apply_ok','OK'),'success');closeBankCsvModal();location.reload();}
        else showNotification(d.message||t('error_generic','Error'),'error');
    }).catch(e=>showNotification(`${t('network_error','Network error')}: ${e}`,'error'));
}
function onBankCsvFileChange(ev){
    const f=ev.target.files&&ev.target.files[0];
    if(!f)return;
    const fd=new FormData();
    fd.append('bank_csv_analyze','1');
    fd.append('bank_csv_file',f);
    fetch('',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
        ev.target.value='';
        if(d.status!=='success'){showNotification(d.message||t('bank_csv_analyze_fail','Fail'),'error');return;}
        openBankCsvModal();
        renderBankCsvAnalysis(d);
    }).catch(e=>{ev.target.value='';showNotification(`${t('network_error','Network error')}: ${e}`,'error');});
}
window.handleEmbeddedOCRProgress=function(lines){setOCRLog((lines||[]).map(localizeOCRLine));};
window.handleEmbeddedOCRFinished=function(message){closeImportModal();showNotification(message||ocrT.done,'success');loadAvailableYears().then(()=>updateChart());loadPage(1);updateFinancialSummary();};
let unresolvedJobId='';
let unresolvedRows=[];
function setOCRLog(lines){
    const box=document.getElementById('ocr-log');
    box.innerHTML=(lines||[]).map(x=>`<div>${String(x).replace(/</g,'&lt;')}</div>`).join('');
}
function appendOCRLog(line){
    const box=document.getElementById('ocr-log');
    const div=document.createElement('div');
    div.textContent=line;
    box.appendChild(div);
    box.scrollTop=box.scrollHeight;
}
function localizeOCRLine(line){
    let s=String(line||'');
    s=s.replace(/^Processed\s+/i,`${ocrT.recognized} `);
    s=s.replace(/^Job created:\s*/i,`${ocrT.jobCreated}:`);
    s=s.replace(/^Accounting currency:\s*/i,`${ocrT.baseCurrency}:`);
    s=s.replace(/^Задача создана:\s*/i,`${ocrT.jobCreated}:`);
    s=s.replace(/^Валюта учета:\s*/i,`${ocrT.baseCurrency}:`);
    s=s.replace(/^Завдання створено:\s*/i,`${ocrT.jobCreated}:`);
    s=s.replace(/^Валюта обліку:\s*/i,`${ocrT.baseCurrency}:`);
    s=s.replace(/^Ошибка статуса:/i,`${ocrT.statusError}:`);
    s=s.replace(/^Помилка статусу:/i,`${ocrT.statusError}:`);
    s=s.replace(/^Ошибка OCR:/i,`${ocrT.ocrError}:`);
    s=s.replace(/^Помилка OCR:/i,`${ocrT.ocrError}:`);
    s=s.replace(/^Ошибка запуска:/i,`${ocrT.startError}:`);
    s=s.replace(/^Помилка запуску:/i,`${ocrT.startError}:`);
    s=s.replace(/^Start error:\s*/i,`${ocrT.startError}:`);
    return s;
}
function escHtml(s){return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');}
function uniqueRawMerchants(rows){return [...new Set(rows.map(r=>r.merchant_raw).filter(Boolean))];}
function readUnresolvedSelections(){const map={};document.querySelectorAll('#unresolved-list select').forEach(s=>{if(s.value){map[s.dataset.raw]=s.value;}});return map;}
function suggestedStoreName(raw){const row=(unresolvedRows||[]).find(item=>item&&item.merchant_raw===raw&&item.suggested_store);return row&&row.suggested_store?row.suggested_store:'';}
function renderUnresolvedModal(selectedMap=null){
    const stores=(userSettings.stores||[]).map(s=>s.name);
    const list=document.getElementById('unresolved-list');
    const selected=selectedMap||readUnresolvedSelections();
    list.innerHTML=uniqueRawMerchants(unresolvedRows).map(raw=>{const nextValue=selected[raw]||'';const nextName=suggestedStoreName(raw)||raw;return `<div style="display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1.4fr);gap:10px;align-items:center;margin-bottom:8px;"><div>${escHtml(raw)}</div><div style="display:grid;grid-template-columns:minmax(0,1fr) auto;gap:10px;align-items:center;"><select data-raw="${escHtml(raw)}" style="padding:8px;background:var(--group-block-bg);border:1px solid var(--border-color);color:var(--text-color);"><option value="">${translations.ocr_choose_store||''}</option>${stores.map(s=>`<option value="${escHtml(s)}"${s===nextValue?' selected':''}>${escHtml(s)}</option>`).join('')}</select><button type="button" class="ocr-create-store-btn" data-raw="${escHtml(raw)}" data-name="${escHtml(nextName)}" style="padding:8px 10px;background:var(--primary-color);color:#fff;border:none;border-radius:6px;cursor:pointer;white-space:nowrap;">+ ${escHtml(translations.add_store||'Add Store')}</button></div></div>`;}).join('');
    list.querySelectorAll('.ocr-create-store-btn').forEach(btn=>btn.onclick=()=>showAddEditStoreModal(null,btn.dataset.name||btn.dataset.raw,true,btn.dataset.raw));
    showModal('unresolved-modal');
}
function submitUnresolvedMappings(){
    const map=readUnresolvedSelections();
    fetch('utils/expense_ocr_app.php?api=resolve',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({job_id:unresolvedJobId,map})})
    .then(r=>r.json()).then(async d=>{if(d.status==='ok'){appendOCRLog(`${ocrT.manualAdded}: ${d.added}`);const sr=await fetch(`utils/expense_ocr_app.php?api=status&job_id=${encodeURIComponent(unresolvedJobId)}`);const sd=await sr.json();if(sd.status==='ok'){unresolvedRows=sd.job&&Array.isArray(sd.job.unresolved)?sd.job.unresolved:[];}if(unresolvedRows.length){showNotification(`${ocrT.manualAdded}: ${d.added}`,'success');renderUnresolvedModal();return;}await fetch('utils/expense_ocr_app.php?api=finalize',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({job_id:unresolvedJobId})});showNotification(`${ocrT.manualAdded}: ${d.added}`,'success');closeModal('unresolved-modal');}else{showNotification(d.message||translations.ocr_mapping_error,'error');}})
    .catch(e=>showNotification(`${t('network_error','Network error')}: ${e.message}`,'error'));
}

// ══ Share chart ═══════════════════════════════════════════════════════════
function getShareChartData(){
    const cd=JSON.parse(JSON.stringify(chart.data));
    cd.datasets=cd.datasets.filter(d=>d.label!=='');
    cd.datasets.forEach((d,i)=>{d.borderColor=storeColors[i%storeColors.length];d.backgroundColor=storeColors[i%storeColors.length]+'80';d.borderWidth=3;d.pointRadius=5;d.tension=0.4;delete d.borderDash;});
    return cd;
}
function shareChartOptions(isMobile,titleSize){
    // Fixed light palette: the export is a standalone PNG shown in the
    // messenger, where a transparent background makes axis labels unreadable.
    // Chart.js clears the canvas on every redraw, so the opaque backdrop is
    // repainted by the shareOpaqueBg plugin registered below.
    const grid='rgba(0,0,0,.12)',tick='#4a4a4a',text='#1f1f1f';
    return {responsive:false,maintainAspectRatio:false,animation:false,scales:{y:{beginAtZero:true,grid:{color:grid,drawBorder:true,borderColor:grid},title:{display:true,text:`${t('chart_amount','Amount')} (${chartCurrency})`,color:tick,font:{size:titleSize||(isMobile?13:16)}},ticks:{color:tick,font:{size:titleSize||(isMobile?11:14)}}},x:{grid:{color:grid,drawBorder:true,borderColor:grid},title:{display:true,text:t('chart_month','Month'),color:tick,font:{size:titleSize||(isMobile?13:16)}},ticks:{color:tick,font:{size:titleSize||(isMobile?11:14)}}}},plugins:{legend:{labels:{color:text,font:{size:titleSize||(isMobile?12:15)},usePointStyle:true,boxWidth:8}},shareOpaqueBg:{enabled:true},title:{display:true,text:`${t('expense_tracker_title','Expense Tracker')} - ${currentChartYear}`,color:text,font:{size:titleSize||(isMobile?14:18),weight:'bold'}}}};
}
if(window.Chart&&!Chart.__shareOpaqueBgRegistered){
    Chart.__shareOpaqueBgRegistered=true;
    Chart.register({id:'shareOpaqueBg',beforeDraw(c){if(!c.options?.plugins?.shareOpaqueBg?.enabled)return;const ctx=c.ctx,w=c.width,h=c.height;ctx.save();ctx.globalCompositeOperation='destination-over';ctx.fillStyle='#ffffff';ctx.fillRect(0,0,w,h);ctx.restore();}});
}
function renderShareChartCanvas(width,height,isMobile,titleSize){
    const canvas=document.createElement('canvas');
    canvas.width=width;canvas.height=height;
    const ctx=canvas.getContext('2d');
    const inst=new Chart(ctx,{type:'line',data:getShareChartData(),options:shareChartOptions(isMobile,titleSize)});
    inst.update('none');
    return {canvas,chart:inst};
}
function showShareModal(){
    if(window.openShareModal){
        window.openShareModal('spends','app','expense_chart',{
            title: (translations.share_chart || document.title || 'Expenses'),
            defaultPermissions: [],
            requirePassword: true,
            apiUrl: '/apps/spends/api/share.php'
        });
        return;
    }
    if(!chart){showNotification(t('chart_not_available','Chart not available'),'error');return;}
    showModal('share-modal');
    requestAnimationFrame(()=>{
        try{
            const sc=document.getElementById('share-chart');
            if(!sc)return;
            const isMobile=window.innerWidth<=768;
            const w=isMobile?Math.min(window.innerWidth*0.9,500):500;
            const h=isMobile?Math.min(window.innerHeight*0.4,300):300;
            if(shareChart)shareChart.destroy();
            sc.width=w;sc.height=h;
            const ctx=sc.getContext('2d');
            shareChart=new Chart(ctx,{type:'line',data:getShareChartData(),options:shareChartOptions(isMobile)});
            shareChart.update('none');
        }catch(e){showNotification(t('chart_render_failed','Failed to render chart'),'error');}
    });
}
function downloadChartImage(){
    if(!chart){showNotification(t('chart_not_available','Chart not available'),'error');return;}
    try{
        const {canvas,chart:dc}=renderShareChartCanvas(1000,600,false,24);
        const l=document.createElement('a');
        l.download=`expenses-chart-${currentChartYear}.png`;
        l.href=canvas.toDataURL('image/png');
        l.click();
        dc.destroy();
        showNotification(translations.chart_downloaded||'Chart downloaded','success');
    }catch(e){showNotification(translations.copy_failed||'Failed to copy','error');}
}
function chartShareDataUrl(){
    const {canvas,chart:dc}=renderShareChartCanvas(1000,600,false,24);
    const dataUrl=canvas.toDataURL('image/png');
    dc.destroy();
    return dataUrl;
}
function dataUrlToPngFile(dataUrl){
    const bin=atob(dataUrl.split(',')[1]||'');
    const bytes=new Uint8Array(bin.length);
    for(let i=0;i<bin.length;i++)bytes[i]=bin.charCodeAt(i);
    return new File([bytes],`expenses-chart-${currentChartYear}.png`,{type:'image/png'});
}
function shareChartToCloud(){
    // The app share dialog creates a live/snapshot link directly. It must not
    // upload a rendered PNG (expenses-chart-*.png) into the user's Cloud storage.
    if(window.openShareModal){
        window.openShareModal('spends','app','expense_chart',{
            title: (translations.share_chart || document.title || 'Expenses'),
            defaultPermissions: [],
            requirePassword: true,
            apiUrl: '/apps/spends/api/share.php'
        });
        return;
    }
    showNotification(t('error','Error')+': '+t('share_chart','Share'),'error');
}
async function showChartShareLinkModal(link){
    closeModal('share-modal');
    showModal('chart-share-link-modal');
    const linkEl=document.getElementById('csl-link');
    linkEl.href=link;linkEl.textContent=link;
    const qrHost=document.getElementById('csl-qr');
    qrHost.innerHTML='';
    try{
        if(typeof window.apEnsureQrCodeStyling!=='function'){
            await new Promise(function(resolve,reject){var s=document.createElement('script');s.src='/shell/assets/qr-code-loader.js?v=1';s.onload=resolve;s.onerror=reject;document.head.appendChild(s);});
        }
        const Ctor=await window.apEnsureQrCodeStyling();
        const wrap=document.createElement('div');wrap.className='csl-qr-wrap';
        const qr=new Ctor(window.apQrStyleOptions?window.apQrStyleOptions(link,200):{width:200,height:200,type:'svg',data:link,margin:4,qrOptions:{errorCorrectionLevel:'H'},dotsOptions:{type:'rounded',color:'#2563eb'},cornersSquareOptions:{type:'extra-rounded',color:'#2563eb'},cornersDotOptions:{type:'dot',color:'#7c3aed'},backgroundOptions:{color:'rgba(255,255,255,0)'}});
        qr.append(wrap);qrHost.appendChild(wrap);
    }catch(e){
        qrHost.innerHTML='<div class="ccm-empty">QR unavailable</div>';
    }
    const copyBtn=document.getElementById('csl-copy-btn');
    copyBtn.onclick=async()=>{
        try{await navigator.clipboard.writeText(link);showNotification(t('link_copied','Link copied'),'success');}
        catch(e){
            const ta=document.createElement('textarea');ta.value=link;document.body.appendChild(ta);ta.select();
            try{document.execCommand('copy');showNotification(t('link_copied','Link copied'),'success');}catch(_){showNotification(t('copy_failed','Copy failed'),'error');}
            ta.remove();
        }
    };
}
let ccmRecipient=null;
let ccmPngDataUrl=null;
function openChartChatSend(){
    if(!chart){showNotification(t('chart_not_available','Chart not available'),'error');return;}
    ccmPngDataUrl=chartShareDataUrl();
    closeModal('share-modal');
    closeModal('chart-share-link-modal');
    showModal('chart-chat-modal');
    ccmBackToUsers();
}
function ccmBackToUsers(){
    ccmRecipient=null;
    document.getElementById('ccm-step-user').style.display='flex';
    document.getElementById('ccm-step-compose').style.display='none';
    const inp=document.getElementById('ccm-user-search');
    inp.value='';
    document.getElementById('ccm-user-results').innerHTML=`<div class="ccm-empty">${t('share_search_hint','Type a name or email to search')}</div>`;
    setTimeout(()=>inp.focus(),100);
}
let ccmSearchSeq=0;
async function ccmSearchUsers(){
    const q=document.getElementById('ccm-user-search').value.trim();
    const box=document.getElementById('ccm-user-results');
    const seq=++ccmSearchSeq;
    if(!q){box.innerHTML=`<div class="ccm-empty">${t('share_search_hint','Type a name or email to search')}</div>`;return;}
    box.innerHTML='<div class="ccm-empty">…</div>';
    try{
        const res=await fetch(`?chat_search_users=1&q=${encodeURIComponent(q)}`);
        const data=await res.json();
        if(seq!==ccmSearchSeq)return;
        const users=(data.users||[]);
        if(!users.length){
            box.innerHTML=`<div class="ccm-empty">${t('no_users_found','No users found')}</div>`;
            return;
        }
        box.innerHTML='';
        users.forEach(u=>{
            const b=document.createElement('button');b.type='button';b.className='ccm-user-row';
            const initials=(u.name||u.email||'?').slice(0,2).toUpperCase();
            if(u.photo){
                const img=document.createElement('img');
                img.src=u.photo;img.alt='';
                img.onerror=()=>{const s=document.createElement('span');s.className='ccm-user-avatar';s.textContent=initials;img.replaceWith(s);};
                b.appendChild(img);
            }else{
                const s=document.createElement('span');s.className='ccm-user-avatar';s.textContent=initials;b.appendChild(s);
            }
            const info=document.createElement('span');info.className='ccm-user-info';
            const strong=document.createElement('strong');strong.textContent=u.name||u.email;
            const mail=document.createElement('span');mail.textContent=u.email;
            info.append(strong,mail);b.appendChild(info);
            b.onclick=()=>ccmPickUser(u);
            box.appendChild(b);
        });
    }catch(e){
        if(seq!==ccmSearchSeq)return;
        box.innerHTML=`<div class="ccm-empty">${t('network_error','Network error')}</div>`;
    }
}
function ccmPickUser(u){
    ccmRecipient=u;
    document.getElementById('ccm-step-user').style.display='none';
    document.getElementById('ccm-step-compose').style.display='flex';
    document.getElementById('ccm-recipient-name').textContent=u.name||u.email;
    document.getElementById('ccm-recipient-email').textContent=u.email;
    document.getElementById('ccm-recipient-flag').textContent=(u.name||u.email||'?').slice(0,2).toUpperCase();
    document.getElementById('ccm-preview').src=ccmPngDataUrl;
    document.getElementById('ccm-caption').value='';
}
async function ccmSend(){
    if(!ccmRecipient)return;
    const btn=document.getElementById('ccm-send-btn');
    if(btn)btn.disabled=true;
    try{
        const fd=new FormData();
        fd.append('chat_send_message','1');
        fd.append('peer_email',ccmRecipient.email);
        fd.append('message',document.getElementById('ccm-caption').value.trim());
        fd.append('media',dataUrlToPngFile(ccmPngDataUrl));
        const res=await fetch('?',{method:'POST',body:fd});
        const data=await res.json();
        if(data.status!=='success')throw new Error(data.message||'failed');
        showNotification(t('message_sent','Message sent'),'success');
        closeModal('chart-chat-modal');
    }catch(e){
        showNotification(`${t('error','Error')}: ${e.message}`,'error');
    }finally{
        if(btn)btn.disabled=false;
    }
}
document.addEventListener('input',e=>{
    if(e.target&&e.target.id==='ccm-user-search'){
        clearTimeout(e.target._t);
        e.target._t=setTimeout(ccmSearchUsers,350);
    }
});
// ══ Table keyboard navigation ══════════════════════════════════════════════
function handleTableNavigation(e){
    if(e.key==='Enter'){
        const el=document.activeElement;
        if(!el||el.tagName!=='INPUT'||!el.closest('#data-table tbody'))return;
        e.preventDefault();
        hideAmountCurrencyPopover();
        const inputs=Array.from(document.querySelectorAll('#data-table tbody input'));
        const ci=inputs.indexOf(el);
        if(ci===-1)return;
        const cols=userSettings.stores.filter(s=>s.visible!==false).length+(userSettings.show_incomes?1:0);
        if(cols<=0)return;
        const row=Math.floor(ci/cols),col=ci%cols;
        const lastRow=Math.ceil(inputs.length/cols)-1;
        if(row<lastRow){
            const ni=ci+cols;
            el.blur();
            setTimeout(()=>{const list=Array.from(document.querySelectorAll('#data-table tbody input'));const next=list[ni];if(next){next.focus();next.select();}},0);
        }else{el.blur();addNewDate(null,col);}
        return;
    }
    if(!['ArrowUp','ArrowDown','ArrowLeft','ArrowRight','Tab'].includes(e.key))return;
    e.preventDefault();
    const inputs=Array.from(document.querySelectorAll('#data-table tbody input'));
    const ci=inputs.indexOf(document.activeElement);
    if(ci===-1)return;
    const cols=userSettings.stores.filter(s=>s.visible!==false).length+(userSettings.show_incomes?1:0);
    const row=Math.floor(ci/cols),col=ci%cols;
    let ni=ci;
    if(e.key==='ArrowUp'&&row>0)ni=ci-cols;
    else if(e.key==='ArrowDown'){if(row<Math.ceil(inputs.length/cols)-1)ni=ci+cols;else{addNewDate(null,col);return;}}
    else if(e.key==='ArrowLeft'&&col>0)ni=ci-1;
    else if(e.key==='ArrowRight'||e.key==='Tab'){if(col<cols-1)ni=ci+1;else if(row<Math.ceil(inputs.length/cols)-1)ni=ci+1;else{addNewDate(null,0);return;}}
if(ni!==ci&&inputs[ni]){inputs[ni].focus();inputs[ni].select();}
}
function appThemeIsDark(){
    const theme=(document.documentElement.dataset.theme||'').toLowerCase();
    if(theme)return theme==='dark';
    const bg=getComputedStyle(document.documentElement).getPropertyValue('--bg-color').trim().toLowerCase();
    const m=bg.match(/^#([0-9a-f]{6})$/);
    if(m){const v=parseInt(m[1],16);const l=((v>>16)&255)*0.299+((v>>8)&255)*0.587+(v&255)*0.114;return l<128;}
    return false;
}
// The fill is painted on the text (background-clip:text), so both gradient stops
// must stay readable: WCAG AA 4.5:1 against the panel in each theme.
function expenseGradientStyle(value,min,max){
    const n=Number(value)||0;
    const dark=appThemeIsDark();
    const lightness=dark?72:27;
    let hue=120;
    if(max>min){
        const pct=Math.max(0,Math.min(1,(n-min)/(max-min)));
        hue=120-(pct*120);
    }
    const hue2=Math.max(0,hue-16);
    const sat1=dark?78:72,sat2=dark?84:78;
    return `background-image:linear-gradient(90deg,hsl(${hue} ${sat1}% ${lightness}%),hsl(${hue2} ${sat2}% ${lightness}%));-webkit-background-clip:text;background-clip:text;color:transparent;-webkit-text-fill-color:transparent;`;
}
// ══ Statistics window ══════════════════════════════════════════════════════
const analyticsState = { store: '__all__', payload: null, hidden: new Set() };
let lastAnalyticsPayload = null;

const analyticsPalette = [
    { fill: '#7B3B46', stroke: '#B25565' },
    { fill: '#2B536E', stroke: '#447FA6' },
    { fill: '#8A6D1F', stroke: '#C9A227' },
    { fill: '#4A6B33', stroke: '#6B8E4E' },
    { fill: '#5F3F78', stroke: '#8A5FA8' }
];
const analyticsChartBox = { width: 1040, height: 315, left: 46, right: 24, top: 14, bottom: 34 };

function analyticsStoreRows() {
    const payload = analyticsState.payload || {};
    return (payload.store_summary || [])
        .map(row => ({ ...row, label: row.store }))
        .filter(row => Number(row.total) > 0);
}
function analyticsCategoryRows() {
    const payload = analyticsState.payload || {};
    const selected = analyticsState.store;
    const categories = (payload.category_summary || [])
        .map(row => ({ ...row, label: categoryLabel(row.category) }))
        .filter(row => Number(row.total) > 0)
        .sort((a, b) => (Number(b.total) || 0) - (Number(a.total) || 0));
    if (selected === '__all__') return categories;
    const store = analyticsStoreRows().find(row => row.store === selected);
    if (!store) return categories;
    return [{
        label: categoryLabel(store.category || 'other'),
        today: store.today,
        year_day_avg: store.year_day_avg,
        total: store.total
    }];
}

// Biggest totals read as red, mid ones amber, the rest green.
function analyticsTotalClass(values, value) {
    const max = Math.max(0, ...values.map(v => Number(v) || 0));
    const n = Number(value) || 0;
    if (!max || n <= 0) return 'is-ok';
    const share = n / max;
    return share >= .6 ? 'is-hot' : share >= .3 ? 'is-warm' : 'is-ok';
}

function renderAnalyticsSummaryTable(id, rows, nameKey, kind) {
    const host = document.getElementById(id);
    if (!host) return;
    host.replaceChildren();
    if (!rows.length) {
        const empty = document.createElement('div');
        empty.className = 'analytics-empty';
        empty.textContent = t('no_data', 'No data');
        host.appendChild(empty);
        return;
    }
    const totals = rows.map(row => row.total);
    const selected = analyticsState.store;
    const table = document.createElement('table');
    table.className = 'analytics-table';
    const head = document.createElement('thead');
    const headRow = document.createElement('tr');
    [nameKey, t('today', 'Today'), t('average_day_year', 'Avg/day'), t('total_expenses', 'Total')].forEach((label, index) => {
        const th = document.createElement('th');
        th.textContent = label;
        if (index === 0) th.scope = 'col';
        headRow.appendChild(th);
    });
    head.appendChild(headRow);
    table.appendChild(head);
    const body = document.createElement('tbody');
    rows.forEach(row => {
        const tr = document.createElement('tr');
        if (kind === 'store') {
            if (selected !== '__all__' && selected === row.store) tr.classList.add('is-selected');
            else if (selected !== '__all__') tr.classList.add('is-dimmed');
            tr.tabIndex = 0;
            tr.setAttribute('role', 'button');
            tr.setAttribute('aria-pressed', selected === row.store ? 'true' : 'false');
            tr.title = t('analytics_filter_hint', 'Click a row to filter');
            tr.onclick = () => selectAnalyticsStore(row.store);
            tr.onkeydown = event => {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    selectAnalyticsStore(row.store);
                }
            };
        }
        const nameCell = document.createElement('td');
        nameCell.className = 'analytics-name';
        if (kind === 'store' && selected === row.store) {
            const dot = document.createElement('span');
            dot.className = 'analytics-store-dot';
            nameCell.appendChild(dot);
        }
        nameCell.appendChild(document.createTextNode(row.label));
        const today = document.createElement('td');
        today.className = 'analytics-num is-ok';
        today.textContent = money(row.today);
        const avg = document.createElement('td');
        avg.className = 'analytics-num is-ok';
        avg.textContent = money(row.year_day_avg);
        const total = document.createElement('td');
        total.className = 'analytics-num ' + analyticsTotalClass(totals, row.total);
        total.textContent = money(row.total);
        tr.append(nameCell, today, avg, total);
        body.appendChild(tr);
    });
    table.appendChild(body);
    host.appendChild(table);
}

function renderAnalyticsSummaries(payload) {
    analyticsState.payload = payload;
    if (analyticsState.store !== '__all__'
        && !(payload.store_summary || []).some(row => row.store === analyticsState.store)) {
        analyticsState.store = '__all__';
    }
    const badge = document.getElementById('analytics-category-badge');
    const reset = document.getElementById('analytics-reset-filter');
    const selected = analyticsState.store;
    if (badge) {
        badge.hidden = selected === '__all__';
        badge.textContent = selected === '__all__' ? '' : selected;
    }
    if (reset) reset.hidden = selected === '__all__';
    const select = document.getElementById('ocr-analytics-store');
    if (select && select.value !== selected) select.value = selected;
    if (window.syncCustomSelects) syncCustomSelects();
    renderAnalyticsSummaryTable('analytics-store-summary', analyticsStoreRows(), t('store_name', 'Store'), 'store');
    renderAnalyticsSummaryTable('analytics-category-summary', analyticsCategoryRows(), t('store_category', 'Category'), 'category');
    ['analytics-store-pagination', 'analytics-category-pagination'].forEach(id => {
        const el = document.getElementById(id);
        if (el) { el.hidden = true; el.replaceChildren(); }
    });
}

// Rows per month for the chart: all visible categories, or the picked store.
function analyticsChartSeries() {
    const payload = analyticsState.payload || {};
    const monthly = payload.monthly || [];
    const selected = analyticsState.store;
    if (selected === '__all__') {
        const stores = analyticsStoreRows();
        const labels = [...new Set(monthly.map(row => row.ym))].sort();
        const grouped = new Map();
        stores.forEach(store => {
            const key = store.category || 'other';
            if (!grouped.has(key)) grouped.set(key, { label: categoryLabel(key), members: [], total: 0 });
            const entry = grouped.get(key);
            entry.members.push(store.store);
            entry.total += Number(store.total) || 0;
        });
        const series = [...grouped.entries()]
            .sort((a, b) => b[1].total - a[1].total)
            .map(([key, entry], index) => {
                const palette = analyticsPalette[index % analyticsPalette.length];
                return {
                    key: categoryLabel(key),
                    label: entry.label,
                    fill: palette.fill,
                    stroke: palette.stroke,
                    values: labels.map(ym => monthly
                        .filter(row => row.ym === ym && entry.members.includes(row.store))
                        .reduce((sum, row) => sum + (Number(row.amount_sum) || 0), 0))
                };
            });
        return { labels, series };
    }
    const rows = monthly.filter(row => row.store === selected);
    const labels = [...new Set(rows.map(row => row.ym))].sort();
    const store = analyticsStoreRows().find(row => row.store === selected);
    const palette = analyticsPalette[0];
    return {
        labels,
        series: [{
            key: 'amount',
            label: categoryLabel(store?.category || 'other'),
            fill: palette.fill,
            stroke: palette.stroke,
            values: labels.map(ym => rows
                .filter(row => row.ym === ym)
                .reduce((sum, row) => sum + (Number(row.amount_sum) || 0), 0))
        }]
    };
}

function analyticsScaleMax(maxValue) {
    if (!(maxValue > 0)) return 10;
    const magnitude = Math.pow(10, Math.floor(Math.log10(maxValue)));
    const normalized = maxValue / magnitude;
    const factor = normalized <= 1 ? 1 : normalized <= 2 ? 2 : normalized <= 5 ? 5 : 10;
    return Math.max(1, factor * magnitude);
}

function renderAnalyticsLegend(series) {
    const legend = document.getElementById('analytics-bars-legend');
    if (!legend) return;
    legend.replaceChildren();
    series.forEach(item => {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'analytics-legend-btn' + (analyticsState.hidden.has(item.key) ? ' is-off' : '');
        button.setAttribute('aria-pressed', analyticsState.hidden.has(item.key) ? 'false' : 'true');
        const swatch = document.createElement('span');
        swatch.className = 'analytics-legend-swatch';
        swatch.style.background = item.fill;
        swatch.style.borderColor = item.stroke;
        const label = document.createElement('span');
        label.textContent = item.label;
        button.append(swatch, label);
        button.onclick = () => {
            const visibleKeys = series.filter(entry => !analyticsState.hidden.has(entry.key));
            if (analyticsState.hidden.has(item.key)) analyticsState.hidden.delete(item.key);
            else if (visibleKeys.length > 1) analyticsState.hidden.add(item.key);
            renderAnalyticsChart();
        };
        legend.appendChild(button);
    });
}

function positionAnalyticsTooltip(event, tooltip) {
    const host = tooltip.parentElement;
    if (!host) return;
    const rect = host.getBoundingClientRect();
    const tipWidth = tooltip.offsetWidth || 190;
    const tipHeight = tooltip.offsetHeight || 120;
    let left = event.clientX - rect.left + 14;
    let top = event.clientY - rect.top - 24;
    if (left + tipWidth > rect.width - 6) left = event.clientX - rect.left - tipWidth - 14;
    if (left < 6) left = 6;
    if (top + tipHeight > rect.height - 6) top = rect.height - tipHeight - 6;
    if (top < 6) top = 6;
    tooltip.style.left = left + 'px';
    tooltip.style.top = top + 'px';
}

function showAnalyticsTooltip(event, label, items) {
    const tooltip = document.getElementById('analytics-tooltip');
    const rowsHost = document.getElementById('analytics-tooltip-rows');
    const month = document.getElementById('analytics-tooltip-month');
    const totalEl = document.getElementById('analytics-tooltip-total');
    if (!tooltip || !rowsHost || !month || !totalEl) return;
    // Only series that actually spent money in this period are listed; a month
    // full of zero-value stores used to flood the tooltip.
    const visible = items.filter(item => Number(item.value) > 0);
    month.textContent = label;
    rowsHost.replaceChildren();
    let total = 0;
    visible.forEach(item => {
        total += Number(item.value) || 0;
        const row = document.createElement('div');
        row.className = 'analytics-tooltip-row';
        const key = document.createElement('span');
        key.className = 'analytics-tooltip-key';
        const swatch = document.createElement('span');
        swatch.className = 'analytics-tooltip-swatch';
        swatch.style.background = item.fill;
        swatch.style.borderColor = item.stroke;
        const name = document.createElement('span');
        name.textContent = item.label + ':';
        key.append(swatch, name);
        const value = document.createElement('span');
        value.className = 'analytics-tooltip-value';
        value.textContent = money(item.value);
        row.append(key, value);
        rowsHost.appendChild(row);
    });
    if (!visible.length) {
        const empty = document.createElement('div');
        empty.className = 'analytics-tooltip-row';
        empty.textContent = t('no_data', 'No data');
        rowsHost.appendChild(empty);
    }
    totalEl.textContent = money(total);
    tooltip.classList.add('is-visible');
    tooltip.setAttribute('aria-hidden', 'false');
    positionAnalyticsTooltip(event, tooltip);
}

function hideAnalyticsTooltip() {
    const tooltip = document.getElementById('analytics-tooltip');
    if (!tooltip) return;
    tooltip.classList.remove('is-visible');
    tooltip.setAttribute('aria-hidden', 'true');
}

function analyticsTextColor() {
    return getComputedStyle(document.documentElement).getPropertyValue('--text-color').trim() || '#f3f4f6';
}

function renderAnalyticsChart() {
    const gridLayer = document.getElementById('analytics-grid-layer');
    const barsLayer = document.getElementById('analytics-bars-layer');
    const labelsLayer = document.getElementById('analytics-labels-layer');
    if (!gridLayer || !barsLayer || !labelsLayer) return;
    if (!analyticsState.payload) return;

    const all = analyticsChartSeries();
    renderAnalyticsLegend(all.series);
    const visibleSeries = all.series.filter(item => !analyticsState.hidden.has(item.key));
    const series = all.labels.map((label, index) => ({
        label,
        values: visibleSeries.map(item => Number(item.values[index]) || 0)
    }));
    gridLayer.replaceChildren();
    barsLayer.replaceChildren();
    labelsLayer.replaceChildren();
    if (!all.labels.length) return;

    const { width, height, left, right, top, bottom } = analyticsChartBox;
    const chartW = width - left - right;
    const chartH = height - top - bottom;
    const maxValue = Math.max(0, ...series.map(point => point.values.reduce((sum, value) => sum + value, 0)));
    const scaleMax = analyticsScaleMax(maxValue);
    const step = scaleMax / 5;
    const svgNS = 'http://www.w3.org/2000/svg';
    const axisColor = analyticsTextColor();

    const make = (tag, attrs) => {
        const node = document.createElementNS(svgNS, tag);
        Object.entries(attrs).forEach(([name, value]) => node.setAttribute(name, String(value)));
        return node;
    };

    for (let value = 0; value <= scaleMax + 0.0001; value += step) {
        const y = top + chartH - (value / scaleMax) * chartH;
        gridLayer.appendChild(make('line', {
            x1: left, y1: y.toFixed(1), x2: left + chartW, y2: y.toFixed(1),
            stroke: value === 0 ? '#323532' : '#262826', 'stroke-width': 1
        }));
        const tick = make('text', {
            x: left - 10, y: (y + 3.5).toFixed(1), fill: '#8e928e',
            'font-size': 10.5, 'text-anchor': 'end'
        });
        tick.textContent = (Math.round(value * 100) / 100).toString();
        labelsLayer.appendChild(tick);
    }

    const columnWidth = chartW / series.length;
    for (let index = 0; index <= series.length; index++) {
        const x = left + index * columnWidth;
        gridLayer.appendChild(make('line', {
            x1: x.toFixed(1), y1: top, x2: x.toFixed(1), y2: top + chartH,
            stroke: '#242624', 'stroke-width': 1
        }));
    }

    const barWidth = Math.min(168, columnWidth * 0.72);
    series.forEach((point, index) => {
        const centerX = left + index * columnWidth + columnWidth / 2;
        const barX = centerX - barWidth / 2;
        const baseY = top + chartH;
        const group = make('g', { class: 'analytics-bar-group' });
        group.appendChild(make('rect', {
            x: barX.toFixed(1), y: top, width: barWidth.toFixed(1), height: chartH, fill: 'transparent'
        }));
        let cursorY = baseY;
        visibleSeries.forEach((item, seriesIndex) => {
            const value = point.values[seriesIndex];
            if (!(value > 0)) return;
            const segmentHeight = (value / scaleMax) * chartH;
            const segmentY = cursorY - segmentHeight;
            group.appendChild(make('rect', {
                class: 'analytics-bar-rect',
                x: barX.toFixed(1), y: segmentY.toFixed(1),
                width: barWidth.toFixed(1), height: segmentHeight.toFixed(1),
                fill: item.fill, stroke: item.stroke, 'stroke-width': 1
            }));
            cursorY = segmentY;
        });
        const items = visibleSeries.map((item, seriesIndex) => ({
            label: item.label, fill: item.fill, stroke: item.stroke, value: point.values[seriesIndex]
        }));
        group.addEventListener('mousemove', event => showAnalyticsTooltip(event, point.label, items));
        group.addEventListener('mouseleave', hideAnalyticsTooltip);
        barsLayer.appendChild(group);

        const caption = make('text', {
            x: centerX.toFixed(1), y: top + chartH + 20, fill: axisColor,
            'font-size': 11, 'text-anchor': 'middle'
        });
        caption.textContent = point.label.length > 7 ? point.label.slice(5) : point.label;
        labelsLayer.appendChild(caption);
    });
}

function buildOCRAnalyticsChart(payload, selectedStore) {
    lastAnalyticsPayload = payload;
    if (typeof selectedStore === 'string') analyticsState.store = selectedStore;
    renderAnalyticsSummaries(payload);
    renderAnalyticsChart();
}

// The picker filters the already loaded year payload, so every store stays in
// the table (dimmed) and no extra request is needed per click.
function renderAnalyticsSelection() {
    renderAnalyticsSummaries(analyticsState.payload || {});
    renderAnalyticsChart();
}
function selectAnalyticsStore(store) {
    analyticsState.store = store === analyticsState.store && store !== '__all__' ? '__all__' : store;
    const select = document.getElementById('ocr-analytics-store');
    if (select) select.value = analyticsState.store;
    renderAnalyticsSelection();
}

function onAnalyticsStoreChange() {
    const select = document.getElementById('ocr-analytics-store');
    analyticsState.store = select ? select.value : '__all__';
    renderAnalyticsSelection();
}

// Long option lists are paged in chunks of 10 so a picker with hundreds of
// stores stays light: only the current page's <option> nodes are in the DOM.
function applyChunkedSelect(select, pageSize) {
    if (!select) return;
    pageSize = pageSize || 10;
    const all = Array.from(select.options).map(option => ({ value: option.value, label: option.textContent }));
    const previousNav = select.parentElement && select.parentElement.querySelector(':scope > .select-pager');
    if (previousNav) previousNav.remove();
    select.__chunkAll = all;
    if (all.length <= pageSize) return;
    const nav = document.createElement('div');
    nav.className = 'select-pager';
    nav.innerHTML = '<button type="button" data-chunk="prev" aria-label="Previous page">‹</button><span data-chunk-label></span><button type="button" data-chunk="next" aria-label="Next page">›</button>';
    select.parentNode.insertBefore(nav, select.nextSibling);
    const label = nav.querySelector('[data-chunk-label]');
    const prev = nav.querySelector('[data-chunk="prev"]');
    const next = nav.querySelector('[data-chunk="next"]');
    const render = page => {
        const pages = Math.max(1, Math.ceil(all.length / pageSize));
        page = Math.max(0, Math.min(pages - 1, page));
        select.__chunkPage = page;
        const current = select.value;
        select.replaceChildren();
        all.slice(page * pageSize, page * pageSize + pageSize).forEach(item => {
            const node = document.createElement('option');
            node.value = item.value;
            node.textContent = item.label;
            select.appendChild(node);
        });
        if (all.some(item => item.value === current)) select.value = current;
        label.textContent = (page + 1) + ' / ' + pages;
        prev.disabled = page === 0;
        next.disabled = page >= pages - 1;
        if (window.syncCustomSelects) syncCustomSelects();
    };
    prev.addEventListener('click', () => render((select.__chunkPage || 0) - 1));
    next.addEventListener('click', () => render((select.__chunkPage || 0) + 1));
    const selectedIndex = all.findIndex(item => item.value === select.value);
    render(selectedIndex > -1 ? Math.floor(selectedIndex / pageSize) : 0);
}

function loadOCRAnalytics() {
    const select = document.getElementById('ocr-analytics-store');
    fetch(`?get_ocr_stats=1&store=__all__&year=${encodeURIComponent(analyticsYear)}`)
        .then(r => r.json())
        .then(d => {
            if (d.status !== 'success') { showNotification(t('failed_load_analytics', 'Failed to load analytics'), 'error'); return; }
            const store = analyticsState.store || '__all__';
            if (select) {
                const options = [{ value: '__all__', label: t('all_stores', 'All stores') }]
                    .concat((d.stores || []).map(name => ({ value: name, label: name })));
                select.replaceChildren();
                options.forEach(option => {
                    const node = document.createElement('option');
                    node.value = option.value;
                    node.textContent = option.label;
                    select.appendChild(node);
                });
                select.value = (d.stores || []).includes(store) ? store : '__all__';
                applyChunkedSelect(select, 10);
            }
            buildOCRAnalyticsChart(d, select ? select.value : store);
        })
        .catch(() => showNotification(t('failed_load_analytics', 'Failed to load analytics'), 'error'));
}

function showOCRAnalyticsModal() {
    showModal('ocr-analytics-modal');
    updateYearButtons();
    loadOCRAnalytics();
}

// ══ Excel-like cell selection ══════════════════════════════════════════════
const tableSelection = { anchor: null, dragging: false, inputs: [] };
const undoStack = [];
const redoStack = [];

function tableGridInputs() {
    return Array.from(document.querySelectorAll('#data-table tbody input'));
}
function tableGridCols() {
    const stores = (userSettings.stores || []).filter(store => store.visible !== false).length;
    return stores + (userSettings.show_incomes ? 1 : 0);
}
function isCellInput(el) {
    return !!el && el.tagName === 'INPUT' && !!el.closest && !!el.closest('#data-table tbody');
}
function markCell(input, on) {
    const td = input.closest('td');
    if (td) td.classList.toggle('cell-selected', on);
}
function clearCellSelection() {
    document.querySelectorAll('#data-table td.cell-selected').forEach(td => td.classList.remove('cell-selected'));
    tableSelection.inputs = [];
    tableSelection.anchor = null;
    tableSelection.dragging = false;
    tableSelection.lastHover = null;
}
function selectCellRange(from, to, additive) {
    const inputs = tableGridInputs();
    const cols = tableGridCols();
    const start = inputs.indexOf(from);
    const end = inputs.indexOf(to);
    if (start < 0 || end < 0 || cols <= 0) return;
    if (!additive) {
        document.querySelectorAll('#data-table td.cell-selected').forEach(td => td.classList.remove('cell-selected'));
        tableSelection.inputs = [];
    }
    const rowA = Math.floor(start / cols), colA = start % cols;
    const rowB = Math.floor(end / cols), colB = end % cols;
    const firstRow = Math.min(rowA, rowB), lastRow = Math.max(rowA, rowB);
    const firstCol = Math.min(colA, colB), lastCol = Math.max(colA, colB);
    const picked = new Set(tableSelection.inputs);
    for (let row = firstRow; row <= lastRow; row++) {
        for (let col = firstCol; col <= lastCol; col++) {
            const input = inputs[row * cols + col];
            if (!input) continue;
            picked.add(input);
            markCell(input, true);
        }
    }
    tableSelection.inputs = [...picked];
}
function selectAllTableCells() {
    const inputs = tableGridInputs();
    if (!inputs.length) return;
    clearCellSelection();
    tableSelection.inputs = inputs.slice();
    tableSelection.anchor = inputs[0];
    inputs.forEach(input => markCell(input, true));
}
function selectionBounds() {
    const inputs = tableGridInputs();
    const cols = tableGridCols();
    const selected = tableSelection.inputs.filter(input => inputs.includes(input));
    if (!selected.length || cols <= 0) return null;
    const indexes = selected.map(input => inputs.indexOf(input));
    const rows = indexes.map(index => Math.floor(index / cols));
    const columns = indexes.map(index => index % cols);
    return {
        inputs, cols,
        firstRow: Math.min(...rows), lastRow: Math.max(...rows),
        firstCol: Math.min(...columns), lastCol: Math.max(...columns)
    };
}
function selectionTsv() {
    const bounds = selectionBounds();
    if (!bounds) return '';
    const lines = [];
    for (let row = bounds.firstRow; row <= bounds.lastRow; row++) {
        const cells = [];
        for (let col = bounds.firstCol; col <= bounds.lastCol; col++) {
            const input = bounds.inputs[row * bounds.cols + col];
            cells.push(input ? String(input.value || '').replace(/\t/g, ' ') : '');
        }
        lines.push(cells.join('\t'));
    }
    return lines.join('\n');
}
function writeClipboard(text) {
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).catch(() => copyViaTextarea(text));
        return;
    }
    copyViaTextarea(text);
}
function copyViaTextarea(text) {
    const area = document.createElement('textarea');
    area.value = text;
    area.setAttribute('readonly', '');
    area.style.position = 'fixed';
    area.style.opacity = '0';
    document.body.appendChild(area);
    area.select();
    try { document.execCommand('copy'); } catch (_) { /* clipboard unavailable */ }
    area.remove();
}
function copySelectedCells() {
    const text = selectionTsv();
    if (!text) return false;
    writeClipboard(text);
    showNotification(t('cells_copied', 'Selected cells copied'), 'success');
    return true;
}
function attachTableCellSelection(input) {
    if (!input || input.dataset.cellSelectionReady === '1') return;
    input.dataset.cellSelectionReady = '1';
    input.addEventListener('mousedown', event => {
        if (event.button !== 0) return;
        // Suppress the browser's own text-selection drag: it scrolls the table
        // under the pointer and would fight the spreadsheet-style range.
        event.preventDefault();
        if (event.shiftKey && tableSelection.anchor) {
            selectCellRange(tableSelection.anchor, input, event.ctrlKey || event.metaKey);
            input.focus({ preventScroll: true });
            return;
        }
        tableSelection.anchor = input;
        tableSelection.dragging = true;
        tableSelection.lastHover = input;
        selectCellRange(input, input, event.ctrlKey || event.metaKey);
        input.focus({ preventScroll: true });
        input.select();
        // The currency hint floats over the cells below and would break a drag.
        hideAmountCurrencyPopover();
    });
    input.addEventListener('focus', () => {
        if (tableSelection.dragging) return;
        if (tableSelection.inputs.includes(input)) return;
        tableSelection.anchor = input;
        selectCellRange(input, input, false);
    });
}
function initTableInputSelection() {
    tableGridInputs().forEach(attachTableCellSelection);
    clearCellSelection();
}
document.addEventListener('mouseup', () => {
    tableSelection.dragging = false;
    tableSelection.lastHover = null;
});
// First Escape drops the cell selection; only the next one closes the table.
document.addEventListener('keydown', event => {
    if (event.key !== 'Escape' || !tableSelection.inputs.length) return;
    clearCellSelection();
    event.stopImmediatePropagation();
}, true);
// A drag follows the pointer across cells; elementFromPoint keeps it working
// even when the pointer jumps over a cell border between two mousemove events.
document.addEventListener('mousemove', event => {
    if (!tableSelection.dragging || !tableSelection.anchor) return;
    if (event.buttons === 0) { tableSelection.dragging = false; return; }
    let el = document.elementFromPoint(event.clientX, event.clientY);
    if (!isCellInput(el) && el && el.closest) el = el.closest('#data-table tbody td')?.querySelector('input') || null;
    if (!isCellInput(el) || el === tableSelection.lastHover) return;
    tableSelection.lastHover = el;
    selectCellRange(tableSelection.anchor, el, false);
});
document.addEventListener('mousedown', event => {
    if (event.target.closest && event.target.closest('#data-table')) return;
    clearCellSelection();
});

// ══ Undo / redo for table edits ════════════════════════════════════════════
function cellRef(input) {
    return {
        date: input.getAttribute('data-date'),
        store: input.getAttribute('data-store'),
        income: input.getAttribute('data-income') === '1'
    };
}
function findCellInput(ref) {
    return tableGridInputs().find(input => {
        if (input.getAttribute('data-date') !== ref.date) return false;
        if (ref.income) return input.getAttribute('data-income') === '1';
        return input.getAttribute('data-store') === ref.store;
    }) || null;
}
function recordCellEdit(ref, before, after) {
    if (before === after) return;
    undoStack.push({ ref, before, after });
    if (undoStack.length > 200) undoStack.shift();
    redoStack.length = 0;
}
function applyCellHistory(ref, value) {
    const input = findCellInput(ref);
    if (!input) {
        showNotification(t('undo_not_on_page', 'Open the table on that month to undo this edit'), 'warning');
        return false;
    }
    input.value = value;
    saveExpense(input, { history: false });
    return true;
}
function undoCellEdit() {
    const entry = undoStack.pop();
    if (!entry) { showNotification(t('undo_nothing', 'Nothing to undo'), 'info'); return; }
    if (applyCellHistory(entry.ref, entry.before)) redoStack.push(entry);
}
function redoCellEdit() {
    const entry = redoStack.pop();
    if (!entry) { showNotification(t('redo_nothing', 'Nothing to redo'), 'info'); return; }
    if (applyCellHistory(entry.ref, entry.after)) undoStack.push(entry);
}

// ══ Keyboard shortcuts ═════════════════════════════════════════════════════
function hotkeyTypingTarget(el) {
    if (!el) return false;
    if (el.isContentEditable) return true;
    if (el.tagName === 'TEXTAREA' || el.tagName === 'SELECT') return true;
    if (el.tagName !== 'INPUT') return false;
    const type = (el.getAttribute('type') || 'text').toLowerCase();
    return !['checkbox', 'radio', 'button', 'submit', 'reset', 'range', 'color', 'file'].includes(type);
}
function openModalElement() {
    return document.querySelector('.modal[style*="display: flex"]');
}
function flushPendingSaves() {
    clearTimeout(saveTimeout);
    const dirty = tableGridInputs().filter(input => input.value.trim() !== input.defaultValue);
    dirty.forEach(input => saveExpense(input));
    if (dirty.length) showNotification(t('shortcuts_saved', 'Edits saved'), 'success');
}
function setupWizardEnterKeys() {
    const modal = document.getElementById('manual-add-modal');
    if (!modal) return;
    modal.addEventListener('keydown', event => {
        if (event.key !== 'Enter' || event.isComposing) return;
        const el = document.activeElement;
        if (!el || el.tagName === 'TEXTAREA' || el.tagName === 'BUTTON') return;
        event.preventDefault();
        if (manualAddState.step < 3) manualAddGo(manualAddState.step + 1);
        else saveManualExpense();
    });
}
function handleHotkeys(event) {
    const key = event.key;
    const mod = event.ctrlKey || event.metaKey;
    if (mod) {
        const lower = key.toLowerCase();
        if (lower === 'z' && !event.shiftKey) {
            if (isCellInput(document.activeElement) || undoStack.length) {
                event.preventDefault();
                undoCellEdit();
            }
            return;
        }
        if ((lower === 'z' && event.shiftKey) || (lower === 'y' && !event.shiftKey)) {
            event.preventDefault();
            redoCellEdit();
            return;
        }
        if (lower === 'c' && tableSelection.inputs.length && !window.getSelection()?.toString()) {
            if (copySelectedCells()) event.preventDefault();
            return;
        }
        if (lower === 'a' && isCellInput(document.activeElement)) {
            event.preventDefault();
            selectAllTableCells();
            return;
        }
        if (lower === 's') {
            event.preventDefault();
            flushPendingSaves();
            return;
        }
        return;
    }
    if (event.altKey) return;
    if (key === '?' || (key === '/' && event.shiftKey)) {
        event.preventDefault();
        showModal('shortcuts-modal');
        return;
    }
    if (hotkeyTypingTarget(document.activeElement)) return;
    const open = openModalElement();
    if (open) {
        if (open.id === 'ocr-analytics-modal' && (key === 'ArrowLeft' || key === 'ArrowRight')) {
            event.preventDefault();
            changeAnalyticsYear(key === 'ArrowLeft' ? -1 : 1);
        }
        return;
    }
    if (key === 'PageUp') { event.preventDefault(); navigateMonth(-1); }
    else if (key === 'PageDown') { event.preventDefault(); navigateMonth(1); }
    else if (key === 'n' || key === 'N') { event.preventDefault(); openManualAddModal(); }
    else if (key === 't' || key === 'T') { event.preventDefault(); showModal('expenses-table-modal'); }
    else if (key === 'a' || key === 'A') { event.preventDefault(); showOCRAnalyticsModal(); }
    else if (key === ',') { event.preventDefault(); showModal('settings-modal'); }
}
function initHotkeys() {
    document.addEventListener('keydown', handleHotkeys);
    setupWizardEnterKeys();
}

// ══ DOMContentLoaded ═══════════════════════════════════════════════════════
document.addEventListener('DOMContentLoaded',function(){
setupModalChrome();
initCustomSelects();
initDateFields();
initHotkeys();
updateFinancialSummary();
loadAvailableYears().then(()=>{updateChart();});
    highlightCurrentDate();
    setInterval(highlightCurrentDate,60000);
    document.addEventListener('keydown',handleTableNavigation);

    document.querySelectorAll('#data-table tbody input').forEach(input=>{
        input.defaultValue=input.value;
        input.onfocus=()=>{hidePreviewTooltip();highlightRowAndColumn(input);formatNumberInput(input);};
        input.onblur=()=>{hidePreviewTooltip();removeHighlight();if(input.value.trim()!==input.defaultValue)scheduleSave(input);};
        input.addEventListener('input',()=>previewExpression(input));
        attachCellContextMenu(input);
        attachAmountCurrencyPicker(input);
    });
    initTableInputSelection();
    const acp=document.getElementById('amount-currency-popover');
    if(acp)acp.addEventListener('mousedown',e=>e.preventDefault());
    window.addEventListener('scroll',()=>{if(currencyPopoverInput)positionAmountCurrencyPopover(currencyPopoverInput);},true);
    window.addEventListener('resize',()=>{if(currencyPopoverInput)positionAmountCurrencyPopover(currencyPopoverInput);});

    document.addEventListener('keydown',function(e){
        if(e.key==='Escape'){
            hideAmountCurrencyPopover();
            closeImportMenu();
            const im=document.getElementById('import-modal');
            if(im&&im.style.display==='flex'){closeImportModal();return;}
            const open=document.querySelector('.modal[style*="display: flex"]');
            if(open){if(open.id==='input-prompt-modal')resolveInputPrompt(null);else if(open.id==='confirm-delete-modal')cancelConfirmation();else closeModal(open.id);}
            else{const ai=document.activeElement;if(ai.tagName==='INPUT'&&ai.closest('#data-table'))ai.blur();}
        }
    });

    const ib=document.getElementById('import-btn');
    const im=document.getElementById('import-modal');
    ib.addEventListener('click',e=>{e.stopPropagation();toggleImportMenu(e);});
    const iw=document.getElementById('import-opt-wallet');
    const ic=document.getElementById('import-opt-csv');
    const iman=document.getElementById('import-opt-manual');
    const itab=document.getElementById('import-opt-table');
    if(iw)iw.addEventListener('click',e=>{e.stopPropagation();closeImportMenu();openImportModal();});
    if(ic)ic.addEventListener('click',e=>{e.stopPropagation();closeImportMenu();document.getElementById('bank-csv-input').click();});
    if(iman)iman.addEventListener('click',e=>{e.stopPropagation();closeImportMenu();openManualAddModal();});
    if(itab)itab.addEventListener('click',e=>{e.stopPropagation();closeImportMenu();showModal('expenses-table-modal');});
    const bci=document.getElementById('bank-csv-input');
    if(bci)bci.addEventListener('change',onBankCsvFileChange);
    const mb=document.getElementById('monthly-budget');
    if(mb)mb.addEventListener('input',scheduleSaveBudgetSettings);
    const curEl=document.getElementById('currency');
    if(curEl)curEl.addEventListener('change',scheduleSaveBudgetSettings);
    const sit=document.getElementById('show-incomes-toggle');
    if(sit)sit.addEventListener('change',function(){
        scheduleSaveBudgetSettings();
        toggleIncomeColumnVisibility();
    });
    const stt=document.getElementById('show-trend-toggle');
    if(stt)stt.addEventListener('change',function(){
        scheduleSaveBudgetSettings();
        updateChart();
    });
    const tem=document.getElementById('trim-empty-months-toggle');
    if(tem)tem.addEventListener('change',function(){
        scheduleSaveBudgetSettings();
        updateChart();
    });
    const bdm=document.getElementById('bulk-delete-mode');
    const syncBulkDeletePanels=()=>{
        const r=document.getElementById('bulk-delete-range-fields');
        const a=document.getElementById('bulk-delete-all-fields');
        if(!bdm||!r||!a)return;
        if(bdm.value==='all'){r.style.display='none';a.style.display='block';}
        else{r.style.display='block';a.style.display='none';}
    };
    if(bdm){bdm.addEventListener('change',syncBulkDeletePanels);syncBulkDeletePanels();}
    const bf=document.getElementById('bulk-delete-from');
    const bt2=document.getElementById('bulk-delete-to');
    if(bf&&bt2&&!bf.value&&!bt2.value){
        const n=new Date();
        bt2.value=n.toISOString().slice(0,10);
        bf.value=new Date(n.getFullYear(),n.getMonth(),1).toISOString().slice(0,10);
    }
    document.addEventListener('click',e=>{if(!e.target.closest('.add-menu-wrap'))closeImportMenu();});
    document.addEventListener('click',e=>{if(!e.target.closest('#manual-currency'))closeManualCurrencyMenu();});
    document.addEventListener('click',e=>{if(!e.target.closest('#manual-sub-period'))closeManualPeriodMenu();});
    im.addEventListener('click',e=>{if(e.target===im)closeImportModal();});
    if(location.hash==='#ocr'){openImportModal();}
    window.addEventListener('hashchange',()=>{
        if(location.hash==='#ocr'){openImportModal();return;}
        const im=document.getElementById('import-modal');
        if(im&&im.style.display==='flex')closeImportModal();
    });
    window.addEventListener('message',e=>{
        if(e.data&&e.data.type==='ocr_progress'){setOCRLog((e.data.lines||[]).map(localizeOCRLine));return;}
        if(e.data&&e.data.type==='ocr_finished'){closeImportModal();showNotification(e.data.message||ocrT.done,'success');loadAvailableYears().then(()=>updateChart());loadPage(1);updateFinancialSummary();return;}
        if(e.data&&e.data.type==='ocr_close'){closeImportModal();return;}
        if(!e.data||e.data.type!=='ocr_done'){return;}
        if(e.data.ocr_text){console.log('[OCR text]',e.data.ocr_text);}
        unresolvedJobId=e.data.jobId||'';
        unresolvedRows=e.data.unresolved||[];
        const recognized=(e.data.records||[]).length;
        const unresolved=unresolvedRows.length;
        const warnings=e.data.warnings||[];
        const logs=[`${ocrT.done}: ${ocrT.recognized} ${recognized}, ${ocrT.unresolved} ${unresolved}`,...warnings.map(w=>`${ocrT.warning}: ${w}`),`${ocrT.autoAdded}: ${recognized}`];
        setOCRLog(logs);
        if(unresolvedRows.length){renderUnresolvedModal();return;}
        fetch('utils/expense_ocr_app.php?api=finalize',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({job_id:unresolvedJobId})}).catch(()=>{});
        showNotification(`${ocrT.dbAdded}: ${recognized}`,'success');
    });

    document.querySelector(`.currency-btn[data-currency="${chartCurrency}"]`)?.classList.add('active');

    ['date-year','date-month','date-day'].forEach(id=>{
        const el=document.getElementById(id);
        el.addEventListener('wheel',e=>{e.preventDefault();changeDateValue(id.replace('date-',''),e.deltaY>0?-1:1);});
    });

    // Toolbar overflow: add and statistics stay visible, everything else moves
    // into the profile menu so the bar always fits on one line.
    (function initProfileMenu(){
        const menu=document.getElementById('profile-menu');
        const btn=document.getElementById('profile-menu-btn');
        if(!menu||!btn)return;
        const close=function(){
            menu.classList.remove('open');
            menu.hidden=true;
            btn.setAttribute('aria-expanded','false');
        };
        // Account-level settings stay in the avatar menu; chart actions
        // (share, rates) remain in the top bar as their own buttons.
        ['button[onclick*="settings-modal"]','button[onclick*="backup-modal"]','#mobile-app-btn'].forEach(function(sel){
            document.querySelectorAll('.header-buttons '+sel).forEach(function(el){
                if(el.closest('#profile-menu'))return;
                const label=el.getAttribute('title')||el.textContent.trim();
                el.classList.remove('header-btn');
                el.classList.add('profile-menu-item');
                el.removeAttribute('title');
                el.setAttribute('role','menuitem');
                el.setAttribute('type','button');
                if(!el.querySelector('.profile-menu-label')){
                    const span=document.createElement('span');
                    span.className='profile-menu-label';
                    span.textContent=label;
                    el.appendChild(span);
                }
                el.addEventListener('click',close);
                menu.appendChild(el);
            });
        });
        btn.addEventListener('click',function(e){
            e.stopPropagation();
            const open=menu.hidden;
            menu.hidden=!open;
            menu.classList.toggle('open',open);
            btn.setAttribute('aria-expanded',open?'true':'false');
        });
        document.addEventListener('click',function(e){
            if(menu.hidden)return;
            if(menu.contains(e.target)||btn.contains(e.target))return;
            close();
        });
        document.addEventListener('keydown',function(e){
            if(e.key==='Escape'&&!menu.hidden)close();
        });
    })();

    renderMonthPagination();
    loadPage();

    function toggleIncomeColumnVisibility() {
        const showIncomes = document.getElementById('show-incomes-toggle').checked;
        const incomeElements = document.querySelectorAll('.income-header, .income-separator');
        incomeElements.forEach(el => {
            if (showIncomes) {
                el.classList.remove('hidden');
            } else {
                el.classList.add('hidden');
            }
        });
    }
});
</script>
