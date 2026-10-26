<script src="/project/sharepoint/assets/js/qr-code.js?v=5"></script>
<script src="/project/sharepoint/share_i18n.php?lang=<?php echo htmlspecialchars($language ?? 'en', ENT_QUOTES); ?>"></script>
<script src="/project/sharepoint/assets/js/share-modal.js?v=12"></script>
<script>
<?php $jsonFlags = JSON_HEX_TAG | JSON_INVALID_UTF8_SUBSTITUTE; ?>
const translations = <?= json_encode($translations, $jsonFlags) ?: '{}' ?>;
const currentLang = <?= json_encode($language, $jsonFlags) ?: '"en"' ?>;
const ocrI18n={en:{waiting:'Waiting for OCR start...',done:'OCR completed',recognized:'recognized',unresolved:'unresolved',warning:'Warning',autoAdded:'Auto-added to database',manualAdded:'Added after manual mapping',dbAdded:'OCR data added to database',jobCreated:'Job created',baseCurrency:'Accounting currency',statusError:'Status error',ocrError:'OCR error',startError:'Start error'},ru:{waiting:'Ожидание запуска OCR...',done:'OCR завершен',recognized:'распознано',unresolved:'нераспознано',warning:'Предупреждение',autoAdded:'Автоматически добавлено в базу',manualAdded:'Добавлено после ручного сопоставления',dbAdded:'OCR данные добавлены в базу',jobCreated:'Задача создана',baseCurrency:'Валюта учета',statusError:'Ошибка статуса',ocrError:'Ошибка OCR',startError:'Ошибка запуска'},uk:{waiting:'Очікування запуску OCR...',done:'OCR завершено',recognized:'розпізнано',unresolved:'нерозпізнано',warning:'Попередження',autoAdded:'Автоматично додано в базу',manualAdded:'Додано після ручного зіставлення',dbAdded:'OCR дані додано в базу',jobCreated:'Задачу створено',baseCurrency:'Валюта обліку',statusError:'Помилка статусу',ocrError:'Помилка OCR',startError:'Помилка запуску'}};
const ocrT=ocrI18n[currentLang]||ocrI18n.en;
let expensesData = <?= json_encode($allStoresExpenses ?? [], $jsonFlags) ?: '{}' ?>;
let incomesData  = <?= json_encode($allIncomes ?? [], $jsonFlags) ?: '{}' ?>;
let allDates     = <?= json_encode($allDates ?? [], $jsonFlags) ?: '[]' ?>;
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
let totalRowsDB   = <?= (int)$totalRows ?>;
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
function setupModalChrome(){document.querySelectorAll('.modal-content').forEach(content=>{if(content.querySelector(':scope > .modal-topbar'))return;const close=content.querySelector(':scope > .modal-close'),title=Array.from(content.children).find(el=>/H[23]/.test(el.tagName)),topbar=document.createElement('div'),label=title||document.createElement('div'),body=document.createElement('div');topbar.className='modal-topbar';label.classList.add('modal-topbar-title');body.className='modal-body';topbar.appendChild(label);if(close)topbar.appendChild(close);Array.from(content.childNodes).forEach(node=>{if(node!==topbar&&node!==close&&node!==label)body.appendChild(node);});content.appendChild(topbar);content.appendChild(body);});}
function showModal(id) { setupModalChrome(); document.getElementById(id).style.display='flex'; syncModalBodyState(); }
function closeModal(id) { document.getElementById(id).style.display='none'; syncModalBodyState(); }

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
function loadPage() {
    const monthRange = getMonthDateRange();
    const params = new URLSearchParams({
        get_expenses_page: 1,
        months_window: 0,
        sort: 'date_asc',
        start_date: monthRange.start_date,
        end_date: monthRange.end_date
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
        renderPagination();
        highlightCurrentDate();
        if (pendingScrollDate) { scrollToTableRow(pendingScrollDate); pendingScrollDate = null; }

        document.querySelectorAll('#data-table tbody input').forEach(inp=>{
            inp.defaultValue = inp.value;
        });
    })
    .catch(e=>{ console.error('loadPage error:', e); });
}

function renderPagination() {
    const p = document.getElementById('pagination');
    if (p) { p.innerHTML = ''; p.style.display = 'none'; }
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

    // Next month button
    const nextBtn = document.createElement('button');
    nextBtn.innerHTML = '▶';
    nextBtn.title = t('next_month','Next month');
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
}

let calReopen = false;
function buildMonthPickerPopup(month, year) {
    const pop = document.createElement('div');
    pop.className = 'table-cal-pop';

    const title = document.createElement('div');
    title.className = 'table-cal-title';
    title.textContent = t('select_date_label','Select Date:');
    pop.appendChild(title);

    const grid = document.createElement('div');
    grid.className = 'table-cal-grid';

    const yearSel = document.createElement('select');
    yearSel.setAttribute('data-cal-year','1');
    yearSel.setAttribute('aria-label', t('year_label','Year'));
    // Any past year must stay reachable: months without data still render empty cells.
    const minYear = Math.min(2000, tableYearMin);
    const maxYear = Math.max(tableYearMax, year, (new Date()).getFullYear());
    for (let y = minYear; y <= maxYear; y++) {
        const o = document.createElement('option');
        o.value = y; o.textContent = y;
        if (y === year) o.selected = true;
        yearSel.appendChild(o);
    }

    const monthSel = document.createElement('select');
    monthSel.setAttribute('data-cal-month','1');
    monthSel.setAttribute('aria-label', t('month_label','Month'));
    monthNames.forEach((m,i)=>{
        const o = document.createElement('option');
        o.value = i; o.textContent = m;
        if (i === month) o.selected = true;
        monthSel.appendChild(o);
    });

    const yearField = document.createElement('label');
    yearField.className = 'table-cal-field';
    const yearCap = document.createElement('span');
    yearCap.textContent = t('year_label','Year');
    yearField.appendChild(yearCap);
    yearField.appendChild(yearSel);

    const monthField = document.createElement('label');
    monthField.className = 'table-cal-field';
    const monthCap = document.createElement('span');
    monthCap.textContent = t('month_label','Month');
    monthField.appendChild(monthCap);
    monthField.appendChild(monthSel);

    grid.appendChild(yearField);
    grid.appendChild(monthField);
    pop.appendChild(grid);

    const apply = () => {
        const y = parseInt(yearSel.value,10);
        const m = parseInt(monthSel.value,10);
        if (isNaN(y) || isNaN(m)) return;
        currentYear = y;
        currentMonth = m;
        calReopen = true;
        loadPageWithMonth();
    };
    yearSel.onchange = apply;
    monthSel.onchange = apply;
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
    currentMonth = month + direction;
    currentYear = year;
    if (currentMonth > 11) { currentMonth = 0; currentYear++; }
    if (currentMonth < 0) { currentMonth = 11; currentYear--; }
    loadPageWithMonth();
}

function loadPageWithMonth() {
    renderMonthPagination();
    if (calReopen) {
        const pop = document.querySelector('.table-cal-pop');
        if (pop) pop.classList.add('open');
        calReopen = false;
    }
    loadPage();
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

function saveExpense(input) {
    expensesData = (expensesData && typeof expensesData === 'object') ? expensesData : {};
    incomesData  = (incomesData  && typeof incomesData  === 'object') ? incomesData  : {};
    const date  = input.getAttribute('data-date');
    const store = input.getAttribute('data-store');
    const isIncome = input.getAttribute('data-income')==='1';
    let   val   = input.value.trim().replace(/,/g,'.');
    let   amount= 0;
    try {
        if (/^[0-9.\+\-\*\/\(\)\s]+$/.test(val)) amount = eval(val)||0;
        else amount = parseFloat(val)||0;
    } catch(e) { amount=0; }
    amount = parseFloat(amount.toFixed(2));
    input.defaultValue = amount!==0?amount.toFixed(2):'';
    input.value = input.defaultValue;
    
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
    let amount=0;
    try{
        if(/^[0-9.\+\-\*\/\(\)\s]+$/.test(val))amount=eval(val)||0;
        else amount=parseFloat(val)||0;
    }catch(e){amount=0;}
    return parseFloat(amount.toFixed(6));
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
    input.addEventListener('blur',()=>setTimeout(hideAmountCurrencyPopover,220));
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
        const res = (new Function('return '+clean))();
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
        const r=[];
        r.push({...base,label:opts&&opts._label||'',data:solid,borderColor:color,backgroundColor:base.fill?color+'22':'transparent',fill:!!base.fill});
        r.push({...base,label:'',data:dashed,borderColor:color,backgroundColor:'transparent',borderDash:[6,4],fill:false});
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
    } else {
        const perStoreTotals=months.map(m=>{let t=0;userSettings.stores.forEach(s=>{if(s.visible!==false)t+=monthly[m][s.name]||0;});return t*conv;});
        userSettings.stores.forEach((s,i)=>{
            if(s.visible===false)return;
            datasets.push(...splitLine(months.map(m=>(monthly[m][s.name]||0)*conv),storeColors[i%storeColors.length],{_label:s.name}));
        });
        if(userSettings.show_trend){
            const trendData=linearRegression(perStoreTotals);
            datasets.push(...splitLine(trendData,'#f59e0b',{tension:0,_label:t('difference_line','Trend')}));
        }
    }
    chart=new Chart(ctx,{type:'line',data:{labels:months,datasets},options:{responsive:true,maintainAspectRatio:false,
        interaction:{mode:'index',intersect:false},
        scales:{y:{beginAtZero:!datasets.some(d=>d.data.some(v=>v!==null&&v<0)),title:{display:true,text:`${t('chart_amount','Amount')} (${chartCurrency})`,color:'<?= $theme==='dark'?'#b0b0b0':'#000' ?>'},ticks:{color:'<?= $theme==='dark'?'#b0b0b0':'#000' ?>'},grid:{color:'<?= $theme==='dark'?'#5a5a5a':'rgba(0,0,0,0.1)' ?>'},border:{color:'<?= $theme==='dark'?'#7a7a7a':'#666' ?>'}},
               x:{title:{display:true,text:t('chart_month','Month'),color:'<?= $theme==='dark'?'#b0b0b0':'#000' ?>'},ticks:{color:'<?= $theme==='dark'?'#b0b0b0':'#000' ?>'},grid:{color:'<?= $theme==='dark'?'#5a5a5a':'rgba(0,0,0,0.1)' ?>'},border:{color:'<?= $theme==='dark'?'#7a7a7a':'#666' ?>'}}},
        plugins:{legend:{labels:{color:'<?= $theme==='dark'?'#fff':'#000' ?>',filter:item=>item.text!==''}},
                 tooltip:{mode:'index',intersect:false,filter:item=>item.dataset.label!=='',callbacks:{label:c=>`${c.dataset.label}: ${c.parsed.y.toFixed(2)} ${chartCurrency}`}}}}});
    document.getElementById('chart-year').textContent=`${translations.year}: ${currentChartYear}`;
    document.querySelector('.chart-toggle-btn').textContent=chartMode==='total'?translations.per_store_expenses:translations.total_expenses;
    updateYearButtons();
}
function yearHasData(y){return availableYears.includes(Number(y));}
function updateYearButtons(){
    const prev=document.getElementById('chart-year-prev-btn');
    const next=document.getElementById('chart-year-next-btn');
    if(prev)prev.disabled=!yearHasData(currentChartYear-1);
    if(next)next.disabled=!yearHasData(currentChartYear+1);
    const ap=document.getElementById('analytics-year-prev-btn');
    const an=document.getElementById('analytics-year-next-btn');
    if(ap)ap.disabled=!yearHasData(analyticsYear-1);
    if(an)an.disabled=!yearHasData(analyticsYear+1);
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
    if(!yearHasData(target))return;
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
function toggleChartMode(){chartMode=chartMode==='total'?'stores':'total';updateChart();}

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
    wrap.innerHTML='';
    const dates=xrHistory&&xrHistory.dates?xrHistory.dates:[];
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

function xrRenderTabs(){
    document.querySelectorAll('#xr-base-tabs .xr-base-tab').forEach(b=>{
        b.classList.toggle('active',b.dataset.base===xrBase);
    });
}

function loadExchangeRates() {
    const loader=document.getElementById('rates-loading');
    const cached=xrHistoryCache[xrBase];
    if(loader)loader.style.display=cached?'none':'block';
    if(cached){ xrHistory=cached; xrRenderRows(); }
    // current snapshot (for other app features) + 14-day history for sparklines
    fetch(`?get_exchange_rates=1&base=${xrBase}`)
        .then(r=>r.json())
        .then(data=>{
            if(data.status==='success'){
                currentRates=data.rates;
                window.currentRates=data.rates;
            }
        })
        .catch(()=>{});
    fetch(`?get_exchange_history=1&base=${xrBase}&days=14`)
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
                showNotification(t('failed_load_exchange_rates','Failed to load exchange rates'),'error');
            }
        })
        .catch(e=>{
            if(loader)loader.style.display='none';
            if(!cached)showNotification(`${t('network_error','Network error')}: ${e}`,'error');
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

function showOCRAnalyticsModal(){showModal('ocr-analytics-modal');updateYearButtons();loadOCRAnalytics();}

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
function manualAddStorePhoto(name){
    const fn = String(name || '').replace(/[^a-zA-Z0-9]/g, '_');
    return `users/${currentUserFolder}/store_${fn}.png`;
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
function manualAddStoreAvatar(name){
    const wrap = document.createElement('span');
    wrap.className = 'manual-store-avatar';
    const initials = document.createElement('span');
    initials.className = 'manual-store-initials';
    initials.textContent = (String(name || '').trim().charAt(0) || '?').toUpperCase();
    const img = document.createElement('img');
    img.className = 'manual-store-thumb';
    img.alt = '';
    img.loading = 'lazy';
    img.src = manualAddStorePhoto(name);
    img.onload = () => { initials.style.display = 'none'; };
    img.onerror = () => {
        if(img.dataset.jpgTried){ img.remove(); return; }
        img.dataset.jpgTried = '1';
        img.src = manualAddStorePhoto(name).replace(/\.png$/, '.jpg');
    };
    wrap.append(img, initials);
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

        btn.append(manualAddStoreAvatar(s.name), label);
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
    const maxYear = Math.max(new Date().getFullYear() + 1, year);
    if(!yearSel.options.length || Number(yearSel.options[0].value) !== minYear || Number(yearSel.options[yearSel.options.length - 1].value) !== maxYear){
        yearSel.textContent = '';
        for(let y = minYear; y <= maxYear; y++){
            const o = document.createElement('option');
            o.value = String(y);
            o.textContent = String(y);
            yearSel.appendChild(o);
        }
    }
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
    if(section==='goals') loadGoalsList();
    if(section==='tags') loadTagsList();
}

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
    if(!tagsCache.length){host.innerHTML=`<div class="goals-empty">${t('tags_empty','No tags yet')}</div>`;return;}
    tagsCache.forEach(tag=>{
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
        del.onclick=()=>{if(!confirm(t('tags_delete_confirm','Delete this tag?')))return;tagsPost({delete_tag:tag.id}).then(loadTagsList);};
        row.append(chip,used,del);
        host.appendChild(row);
    });
}
function saveTagFromForm(){
    const name=(document.getElementById('tag-name').value||'').trim();
    if(!name){showNotification(t('tags_name_required','Name is required'),'error');return;}
    tagsPost({save_tag:'1',name,color:document.getElementById('tag-color').value}).then(res=>{
        if(res.status!=='success'){showNotification(t('tags_save_failed','Could not save tag'),'error');return;}
        document.getElementById('tag-name').value='';
        loadTagsList();
        showNotification(t('tags_saved','Tag saved'),'success');
    });
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
        return;
    }
    goalsCache.forEach(g=>{
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
            const raw=prompt(t('goals_contribute_prompt','Amount to add'),'10');
            if(raw==null)return;
            const amount=parseFloat(raw);
            if(!(amount>0))return;
            goalsPost({contribute_goal:g.id,id:g.id,amount}).then(res=>{
                if(res.status!=='success'){showNotification(res.message||t('error_generic','Error'),'error');return;}
                loadGoalsList();
            });
        }));
        actions.appendChild(mk(g.is_archived?t('goals_unarchive','Restore'):t('goals_archive','Archive'),()=>{
            goalsPost({archive_goal:g.id,is_archived:g.is_archived?0:1}).then(()=>loadGoalsList());
        }));
        actions.appendChild(mk(t('delete','Delete'),()=>{
            if(!confirm(t('goals_delete_confirm','Delete this goal?')))return;
            goalsPost({delete_goal:g.id}).then(()=>loadGoalsList());
        }));
        host.appendChild(card);
    });
}
function openGoalEditor(goal){
    const box=document.getElementById('goal-editor');
    if(!box)return;
    box.hidden=false;
    document.getElementById('goal-id').value=goal&&goal.id?goal.id:'';
    document.getElementById('goal-name').value=goal&&goal.name?goal.name:'';
    document.getElementById('goal-type').value=goal&&goal.goal_type?goal.goal_type:'savings';
    document.getElementById('goal-target').value=goal?Number(goal.target_amount||0):0;
    document.getElementById('goal-current').value=goal?Number(goal.current_amount||0):0;
    document.getElementById('goal-date').value=goal&&goal.target_date?goal.target_date:'';
    document.getElementById('goal-description').value=goal&&goal.description?goal.description:'';
}
function closeGoalEditor(){
    const box=document.getElementById('goal-editor');
    if(box)box.hidden=true;
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
const visEyeSvg='<svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/></svg>';
const visEyeOffSvg='<svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M12 7c2.76 0 5 2.24 5 5 0 .65-.13 1.26-.36 1.83l2.92 2.92c1.51-1.26 2.7-2.89 3.43-4.75-1.73-4.39-6-7.5-11-7.5-1.4 0-2.74.25-3.98.7l2.16 2.16C10.74 7.13 11.35 7 12 7zM2 4.27l2.28 2.28.46.46C3.08 8.3 1.78 10.02 1 12c1.73 4.39 6 7.5 11 7.5 1.55 0 3.03-.3 4.38-.84l.42.42L19.73 22 21 20.73 3.27 3 2 4.27z"/></svg>';
function storeCategoryIcon(key){return `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="${storeCategoryIcons[key]||storeCategoryIcons.other}"/></svg>`;}
function closeAddEditStoreModal(){pendingOCRStoreRaw='';closeModal('add-edit-store-modal');}
function selectedStoreCategories(){return new Set((userSettings.stores||[]).filter(s=>String(s.id)!==String(currentStoreId)).map(s=>s.category||'other'));}
function refreshCategoryPicker(){const native=document.getElementById('store-category-input'),picker=document.getElementById('store-category-picker');if(!native||!picker)return;const used=selectedStoreCategories();picker.innerHTML='';Array.from(native.options).forEach(option=>{const btn=document.createElement('button');btn.type='button';btn.className='store-category-option';btn.dataset.value=option.value;btn.dataset.used=used.has(option.value)?'1':'0';btn.innerHTML=`${storeCategoryIcon(option.value)}<span>${option.textContent}</span>`;btn.onclick=()=>{native.value=option.value;refreshCategoryPicker();};picker.appendChild(btn);});picker.querySelectorAll('.store-category-option').forEach(btn=>btn.classList.toggle('active',btn.dataset.value===native.value));}
function showAddEditStoreModal(id=null,name='',visible=true,ocrRaw='',category=null){currentStoreId=id;pendingOCRStoreRaw=ocrRaw||'';document.getElementById('add-edit-store-title').textContent=id?translations.edit_store:translations.add_store;document.getElementById('store-name-input').value=name;const cat=document.getElementById('store-category-input');const current=(userSettings.stores||[]).find(s=>String(s.id)===String(id));if(cat)cat.value=category||current?.category||'other';document.getElementById('store-photo-input').value='';const card=document.getElementById('store-photo-crop-card'),preview=document.getElementById('store-photo-preview'),btn=document.getElementById('crop-store-btn');if(card)card.style.display='none';if(preview){preview.removeAttribute('src');preview.style.display='block';}if(btn){btn.style.display='none';btn.innerHTML=cropIconSvg+'<span>'+(translations.crop_photo||'Crop Photo')+'</span>';}if(cropper){cropper.destroy();cropper=null;}if(storePhotoObjectUrl){URL.revokeObjectURL(storePhotoObjectUrl);storePhotoObjectUrl='';}storePhotoFile=null;refreshCategoryPicker();showModal('add-edit-store-modal');}
function previewStorePhoto(input){const file=input.files&&input.files[0];if(!file)return;if(cropper){cropper.destroy();cropper=null;}if(storePhotoObjectUrl)URL.revokeObjectURL(storePhotoObjectUrl);storePhotoFile=file;storePhotoObjectUrl=URL.createObjectURL(file);const p=document.getElementById('store-photo-preview'),card=document.getElementById('store-photo-crop-card'),btn=document.getElementById('crop-store-btn');p.src=storePhotoObjectUrl;p.style.display='block';if(card)card.style.display='flex';if(btn){btn.style.display='inline-flex';btn.innerHTML=cropIconSvg+'<span>'+(translations.crop_photo||'Crop Photo')+'</span>';}}
function cropStorePhoto(){const p=document.getElementById('store-photo-preview');if(!p||!p.getAttribute('src'))return;currentCropType='pending-store';currentCropId='';const ci=document.getElementById('crop-image');ci.src=p.src;showModal('crop-modal');requestAnimationFrame(()=>{if(cropper)cropper.destroy();cropper=new Cropper(ci,{aspectRatio:1,viewMode:2,autoCropArea:.95,background:false,responsive:true,restore:false,checkOrientation:true,guides:true,center:true,dragMode:'move',cropBoxMovable:true,cropBoxResizable:true,minCropBoxWidth:40,minCropBoxHeight:40});});}
function rekeyExpensesDataStore(oldN,newN){if(!oldN||oldN===newN)return;Object.keys(expensesData||{}).forEach(d=>{const o=expensesData[d];if(o&&Object.prototype.hasOwnProperty.call(o,oldN)){o[newN]=o[oldN];delete o[oldN];}});}
function storePhotoUrl(s,withPhoto){if(!withPhoto)return'';return s.photo_url?`${s.photo_url}${s.photo_url.includes('?')?'&':'?'}t=${Date.now()}`:`users/${currentUserFolder}/store_${String(s.name).replace(/[^a-zA-Z0-9]/g,'_')}.png?t=${Date.now()}`;}
function applyStoreToSettingsUI(s,withPhoto){if(!s)return;const idStr=String(s.id);const prevI=(userSettings.stores||[]).findIndex(x=>String(x.id)===idStr);if(prevI>=0){rekeyExpensesDataStore(userSettings.stores[prevI].name,s.name);userSettings.stores[prevI]={...userSettings.stores[prevI],...s};}else{userSettings.stores.push(s);Object.keys(expensesData||{}).forEach(d=>{if(expensesData[d]&&typeof expensesData[d][s.name]==='undefined')expensesData[d][s.name]=0;});}const list=document.getElementById('store-list');if(!list)return;let row=list.querySelector(`.store-item[data-store-id="${CSS.escape(idStr)}"]`);const photoU=storePhotoUrl(s,withPhoto);if(!row){row=document.createElement('div');row.className='store-item';row.dataset.storeId=idStr;row.innerHTML=`<span style="flex:1;"></span><button class="visibility-toggle" data-store-id="${idStr}" data-visible="${s.visible!==false?'true':'false'}" title="${translations.visible||'Visible'}"></button><button class="edit-store-btn" type="button">${translations.edit_store||'Edit'}</button><button class="delete-store-btn" type="button"><svg viewBox="0 0 24 24"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg> ${translations.delete||'Delete'}</button>`;list.appendChild(row);}row.dataset.storeName=s.name;row.querySelector('span').textContent=s.name;row.querySelector('.edit-store-btn').onclick=()=>showAddEditStoreModal(s.id,s.name,s.visible,'',s.category);row.querySelector('.delete-store-btn').onclick=()=>confirmDeleteStore(s.id,s.name);const vt=row.querySelector('.visibility-toggle');if(vt){vt.setAttribute('data-visible',s.visible!==false?'true':'false');vt.innerHTML=s.visible!==false?visEyeSvg:visEyeOffSvg;vt.onclick=()=>toggleStoreVisibility(vt);}let img=row.querySelector('img.store-icon');if(withPhoto){if(!img){img=document.createElement('img');img.className='store-icon';row.insertBefore(img,row.firstChild);}img.src=photoU;img.alt=s.name;}refreshCategoryPicker();loadAvailableYears().then(()=>updateChart());loadPage(1);}
function saveStore(){const name=document.getElementById('store-name-input').value.trim();if(!name){showNotification(t('store_name_required','Store name is required'),'error');return;}const withPhoto=!!storePhotoFile,pendingRaw=pendingOCRStoreRaw,fd=new FormData();fd.append('add_edit_store','1');fd.append('store_id',currentStoreId||Date.now());fd.append('store_name',name);fd.append('store_category',document.getElementById('store-category-input')?.value||'other');if(storePhotoFile)fd.append('store_photo',storePhotoFile,'store_photo.png');fetch('',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{if(d.status!=='success'){showNotification(d.message||t('error_generic','Error'),'error');return;}closeAddEditStoreModal();showNotification(translations.store_saved||'Store saved','success');if(d.store)applyStoreToSettingsUI(d.store,withPhoto);if(pendingRaw&&d.store){const selected=readUnresolvedSelections();selected[pendingRaw]=d.store.name;renderUnresolvedModal(selected);}storePhotoFile=null;}).catch(e=>showNotification(`${t('network_error','Network error')}: ${e}`,'error'));}
function confirmDeleteStore(id,name){pendingDelete={type:'store',id,name};document.getElementById('confirm-delete-message').textContent=translations.confirm_delete_store||`Delete store "${name}"?`;showModal('confirm-delete-modal');}
function executeDelete(){if(pendingDelete.type==='store')deleteStore(pendingDelete.id,pendingDelete.name);}
function removeDataTableStoreColumnByName(storeName){const ths=document.querySelectorAll('#data-table thead th');let col=-1;ths.forEach((th,idx)=>{if(idx>0&&th.textContent.trim()===storeName)col=idx;});if(col<0)return;document.querySelectorAll('#data-table tr').forEach(tr=>{const c=tr.children[col];if(c)c.remove();});if(typeof updateChart==='function')updateChart();}
function deleteStore(id,name){const fd=new FormData();fd.append('delete_store','1');fd.append('store_id',id);fd.append('store_name',name);fetch('',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{if(d.status!=='success'){showNotification(d.message||t('error_generic','Error'),'error');return;}const idS=String(id);userSettings.stores=userSettings.stores.filter(s=>String(s.id)!==idS);Object.keys(expensesData||{}).forEach(dt=>{if(expensesData[dt]&&Object.prototype.hasOwnProperty.call(expensesData[dt],name))delete expensesData[dt][name];});document.querySelector(`#store-list .store-item[data-store-id="${CSS.escape(idS)}"]`)?.remove();document.querySelector(`.store-visibility-table .visibility-toggle[data-store-id="${CSS.escape(idS)}"]`)?.closest('tr')?.remove();removeDataTableStoreColumnByName(name);refreshCategoryPicker();showNotification(translations.delete_store_ok||'Store deleted','success');closeModal('confirm-delete-modal');}).catch(e=>showNotification(`${t('network_error','Network error')}: ${e}`,'error'));}
function toggleStoreVisibility(btn){const nv=btn.getAttribute('data-visible')!=='true';btn.setAttribute('data-visible',nv?'true':'false');btn.innerHTML=nv?visEyeSvg:visEyeOffSvg;scheduleSaveTableSettings();}

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
    if(!confirm(t('confirm_delete_backup','Are you sure you want to delete this backup?')))return;
    fetch('',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams({backup_action:'delete',backup_file:fn})})
    .then(r=>r.json()).then(d=>{if(d.status==='success'){showNotification(t('backup_deleted','Backup deleted'),'success');setTimeout(()=>location.reload(),1000);}else showNotification(d.message||t('error_generic','Error'),'error');});
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
            defaultPermissions: ['view','interact'],
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
            defaultPermissions: ['view','interact'],
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
            await new Promise(function(resolve,reject){var s=document.createElement('script');s.src='/project/assets/qr-code-loader.js?v=1';s.onload=resolve;s.onerror=reject;document.head.appendChild(s);});
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
function renderAnalyticsSummaryTable(id,rows,nameKey){
    const el=document.getElementById(id);
    if(!el)return;
    if(!rows||!rows.length){el.textContent=t('no_data','No data');return;}
    el.innerHTML='';
    const totals=rows.map(r=>Number(r.total)||0).filter(v=>v>0);
    const min=totals.length?Math.min(...totals):0,max=totals.length?Math.max(...totals):0;
    const table=document.createElement('table');
    table.className='analytics-summary-table';
    table.innerHTML=`<thead><tr><th>${nameKey}</th><th>${t('today','Today')}</th><th>${t('average_day_year','Avg/day')}</th><th>${t('total_expenses','Total')}</th></tr></thead>`;
    const body=document.createElement('tbody');
    rows.slice(0,12).forEach(row=>{
        const tr=document.createElement('tr');
        tr.innerHTML=`<td>${row.label}</td><td class="expense-gradient-cell" style="${expenseGradientStyle(row.today,min,max)}">${money(row.today)}</td><td class="expense-gradient-cell" style="${expenseGradientStyle(row.year_day_avg,min,max)}">${money(row.year_day_avg)}</td><td class="expense-gradient-cell" style="${expenseGradientStyle(row.total,min,max)}">${money(row.total)}</td>`;
        body.appendChild(tr);
    });
    table.appendChild(body);
    el.appendChild(table);
}
function renderAnalyticsSummaries(payload){
    const stores=(payload.store_summary||[]).map(r=>({...r,label:r.store}));
    const categories=(payload.category_summary||[]).map(r=>({...r,label:categoryLabel(r.category)})).sort((a,b)=>(b.total||0)-(a.total||0));
    renderAnalyticsSummaryTable('analytics-store-summary',stores,t('store_name','Store'));
    renderAnalyticsSummaryTable('analytics-category-summary',categories,t('store_category','Category'));
}
function chartTextColor(){
    const html=document.documentElement;
    if(html.classList.contains('dark')||html.classList.contains('theme-dark'))return '#f3f4f6';
    if(html.classList.contains('light')||html.classList.contains('theme-light'))return '#111827';
    // Fallback: detect theme from CSS variable (--bg-color set server-side)
    const bg=getComputedStyle(html).getPropertyValue('--bg-color').trim().toLowerCase();
    if(bg){
        const m=bg.match(/^#([0-9a-f]{6})$/);
        if(m){
            const v=parseInt(m[1],16),r=(v>>16)&255,g=(v>>8)&255,b=v&255;
            return (r*0.299+g*0.587+b*0.114)<128?'#f3f4f6':'#111827';
        }
        if(bg.includes('rgba')||bg.includes('rgb')){
            const nums=bg.match(/[\d.]+/g);
            if(nums&&nums.length>=3){
                const lum=(+nums[0])*0.299+(+nums[1])*0.587+(+nums[2])*0.114;
                return lum<128?'#f3f4f6':'#111827';
            }
        }
    }
    return '#111827';
}
window.chartTextColor=chartTextColor;
function buildOCRAnalyticsChart(payload,selectedStore){
    renderAnalyticsSummaries(payload);
    const canvas=document.getElementById('ocr-analytics-chart');
    if(!canvas)return;
    const ctx=canvas.getContext('2d');
    if(ocrAnalyticsChart)ocrAnalyticsChart.destroy();
    const monthly=payload.monthly||[];
    const color=chartTextColor();
    if(selectedStore==='__all__'){
        const labels=[...new Set(monthly.map(r=>r.ym))].sort();
        const categories=[...new Set((payload.category_summary||[]).map(r=>r.category))];
        const datasets=categories.map((cat,i)=>({type:'bar',label:categoryLabel(cat),data:labels.map(ym=>monthly.filter(r=>r.ym===ym).reduce((sum,r)=>{const st=(payload.store_summary||[]).find(s=>s.store===r.store);return sum+(st&&st.category===cat?Number(r.amount_sum||0):0);},0)),backgroundColor:storeColors[i%storeColors.length]+'66',borderColor:storeColors[i%storeColors.length],borderWidth:1,stack:'amount'}));
        ocrAnalyticsChart=new Chart(ctx,{type:'bar',data:{labels,datasets},options:{responsive:true,maintainAspectRatio:false,scales:{x:{stacked:true,ticks:{color:chartTextColor()}},y:{stacked:true,beginAtZero:true,ticks:{color:chartTextColor()}}},plugins:{legend:{labels:{color:chartTextColor()}}}}});
        return;
    }
    const daily=payload.daily||[];
    const labels=[...new Set(daily.map(r=>r.date))].sort();
    const amounts=labels.map(d=>{const row=daily.find(r=>r.date===d);return row?Number(row.amount):0;});
    ocrAnalyticsChart=new Chart(ctx,{type:'bar',data:{labels,datasets:[{label:t('chart_amount','Amount'),data:amounts,backgroundColor:'rgba(239,68,68,.45)',borderColor:'rgba(239,68,68,1)',borderWidth:1}]},options:{responsive:true,maintainAspectRatio:false,scales:{x:{ticks:{color:chartTextColor()}},y:{beginAtZero:true,ticks:{color:chartTextColor()}}},plugins:{legend:{labels:{color:chartTextColor()}}}}});
}
function loadOCRAnalytics(){
    const select=document.getElementById('ocr-analytics-store');
    const store=select?select.value:'__all__';
    fetch(`?get_ocr_stats=1&store=${encodeURIComponent(store)}&year=${encodeURIComponent(analyticsYear)}`).then(r=>r.json()).then(d=>{
        if(d.status!=='success'){showNotification(t('failed_load_analytics','Failed to load analytics'),'error');return;}
        if(select&&select.options.length<=1)(d.stores||[]).forEach(s=>{const o=document.createElement('option');o.value=s;o.textContent=s;select.appendChild(o);});
        buildOCRAnalyticsChart(d,store);
    }).catch(()=>showNotification(t('failed_load_analytics','Failed to load analytics'),'error'));
}
function showOCRAnalyticsModal(){showModal('ocr-analytics-modal');updateYearButtons();loadOCRAnalytics();}

// ══ DOMContentLoaded ═══════════════════════════════════════════════════════
document.addEventListener('DOMContentLoaded',function(){
setupModalChrome();
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
            if(open){closeModal(open.id);}
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
