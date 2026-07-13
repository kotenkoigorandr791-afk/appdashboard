<script>
const translations = <?= json_encode($translations) ?>;
const currentLang = <?= json_encode($language) ?>;
const ocrI18n={en:{waiting:'Waiting for OCR start...',done:'OCR completed',recognized:'recognized',unresolved:'unresolved',warning:'Warning',autoAdded:'Auto-added to database',manualAdded:'Added after manual mapping',dbAdded:'OCR data added to database',jobCreated:'Job created',baseCurrency:'Accounting currency',statusError:'Status error',ocrError:'OCR error',startError:'Start error'},ru:{waiting:'Ожидание запуска OCR...',done:'OCR завершен',recognized:'распознано',unresolved:'нераспознано',warning:'Предупреждение',autoAdded:'Автоматически добавлено в базу',manualAdded:'Добавлено после ручного сопоставления',dbAdded:'OCR данные добавлены в базу',jobCreated:'Задача создана',baseCurrency:'Валюта учета',statusError:'Ошибка статуса',ocrError:'Ошибка OCR',startError:'Ошибка запуска'},uk:{waiting:'Очікування запуску OCR...',done:'OCR завершено',recognized:'розпізнано',unresolved:'нерозпізнано',warning:'Попередження',autoAdded:'Автоматично додано в базу',manualAdded:'Додано після ручного зіставлення',dbAdded:'OCR дані додано в базу',jobCreated:'Задачу створено',baseCurrency:'Валюта обліку',statusError:'Помилка статусу',ocrError:'Помилка OCR',startError:'Помилка запуску'}};
const ocrT=ocrI18n[currentLang]||ocrI18n.en;
let expensesData = <?= json_encode($allStoresExpenses) ?>;
let incomesData  = <?= json_encode($allIncomes ?? []) ?>;
let allDates     = <?= json_encode($allDates) ?>;
let userSettings = <?= json_encode($userSettings) ?>;
let currentUser  = <?= json_encode($currentUser) ?>;
const currentUserFolder = <?= json_encode($currentUserFolder) ?>;
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
let tableSettings = <?= json_encode($tableSettings) ?>;
let currentPage   = 1;
let searchTerm    = '';
let pendingDelete = { type:'', id:'', name:'' };
let totalRowsDB   = <?= (int)$totalRows ?>;
let shareChart    = null;
let rateChart     = null;
let currentRates  = {};

const storeColors = ['#FF6384','#36A2EB','#FFCE56','#4BC0C0','#9966FF','#FF9F40','rgba(6,127,0,1)','#C9CBCF','#4BC0C0','#FFCD56','#C9CBCF','#FF6384'];
const monthNames  = <?= json_encode(($monthNames[$language] ?? $monthNames['en'])) ?>;
const t=(key,fallback='')=>translations[key]||fallback;

// ══ Modal helpers ═══════════════════════════════════════════════════════════
function syncModalBodyState(){document.body.classList.toggle('modal-open',!!document.querySelector('.modal[style*="display: flex"]'));}
function showModal(id) { document.getElementById(id).style.display='flex'; syncModalBodyState(); }
function closeModal(id) { document.getElementById(id).style.display='none'; syncModalBodyState(); }

// ══ Notification ═══════════════════════════════════════════════════════════
function showNotification(message, type='info') {
    const c = document.getElementById('notification-container');
    const n = document.createElement('div');
    n.className = `notification ${type}`;
    n.innerHTML = `<span>${message}</span><button class="notification-close" onclick="this.parentElement.remove()">×</button>`;
    c.appendChild(n);
    setTimeout(()=>n.remove(), 5000);
}

// ══ Server-side pagination (SQL) ════════════════════════════════════════════
function filterTable() {
    searchTerm = document.getElementById('search-input').value.trim();
    currentPage = 1;
    loadPage(1);
}

function loadPage(page) {
    const params = new URLSearchParams({
        get_expenses_page: 1,
        page: page,
        search: searchTerm,
        per_page: tableSettings.rows_per_page,
        sort: tableSettings.sort_by,
        start_date: tableSettings.start_date || ''
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
        data.rows.forEach(row=>{
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
        currentPage = data.page;
        renderPagination(data.totalPages);
        highlightCurrentDate();

        document.querySelectorAll('#data-table tbody input').forEach(inp=>{
            inp.defaultValue = inp.value;
        });
    })
    .catch(e=>{ console.error('loadPage error:', e); });
}

function renderPagination(totalPages) {
    if (!totalPages) {
        totalPages = Math.ceil(totalRowsDB / tableSettings.rows_per_page) || 1;
    }
    let html = '';
    html += `<button onclick="goToPage(${currentPage-1})" ${currentPage===1?'disabled':''}>←</button>`;
    const maxButtons = 10;
    let startPage, endPage;
    if (totalPages<=maxButtons) { startPage=1; endPage=totalPages; }
    else {
        const half = Math.floor(maxButtons/2);
        startPage = Math.max(1,currentPage-half);
        endPage   = Math.min(totalPages,startPage+maxButtons-1);
        if (endPage-startPage+1<maxButtons) {
            if (currentPage<=half) endPage=maxButtons;
            else startPage=totalPages-maxButtons+1;
        }
    }
    if (startPage>1) { html+=`<button onclick="goToPage(1)">1</button>`; if(startPage>2) html+=`<span>…</span>`; }
    for (let i=startPage;i<=endPage;i++) html+=`<button onclick="goToPage(${i})" class="${i===currentPage?'active':''}">${i}</button>`;
    if (endPage<totalPages) { if(endPage<totalPages-1) html+=`<span>…</span>`; html+=`<button onclick="goToPage(${totalPages})">${totalPages}</button>`; }
    html += `<button onclick="goToPage(${currentPage+1})" ${currentPage===totalPages?'disabled':''}>→</button>`;
    document.getElementById('pagination').innerHTML = html;
}

function goToPage(page) {
    const totalPages = Math.ceil(totalRowsDB/tableSettings.rows_per_page)||1;
    if (page<1||page>totalPages) return;
    currentPage=page;
    loadPage(page);
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
                const lab=t('cell_entry_manual','Manual entry');
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
    const months = Array.from({length:12},(_,i)=>String(i+1).padStart(2,'0'));
    const yearDates = Object.keys(expensesData).filter(d=>d.startsWith(currentChartYear.toString()));
    const monthly={};
    months.forEach(m=>{ monthly[m]={}; userSettings.stores.forEach(s=>{monthly[m][s.name]=0;}); });
    const monthlyIncome={};months.forEach(m=>{monthlyIncome[m]=0;});
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
    const currentMonthIdx=isCurrentYear?now.getMonth():-1;
    const dashOn={borderDash:[6,4]};
    const dashOff={borderDash:[]};
    const segFn=p0Idx=>p0Idx<currentMonthIdx?dashOff:dashOn;
    let datasets=[];
    if (chartMode==='total') {
        const td=months.map(m=>{let t=0;userSettings.stores.forEach(s=>{if(s.visible!==false)t+=monthly[m][s.name]||0;});return t*conv;});
        datasets=[{label:translations.total_expenses,data:td,borderColor:'#ef4444',backgroundColor:'rgba(239,68,68,.14)',tension:.4,fill:true,segment:ctx=>segFn(ctx.p0DataIndex)}];
        if(userSettings.show_incomes){
            const inc=months.map(m=>(monthlyIncome[m]||0)*conv);
            datasets.push({label:t('incomes','Incomes'),data:inc,borderColor:'#22c55e',backgroundColor:'#22c55e',tension:.4,fill:false,segment:ctx=>segFn(ctx.p0DataIndex)});
        }
        if(userSettings.show_trend){
            const inc=months.map(m=>(monthlyIncome[m]||0)*conv);
            const trendData=months.map((_,i)=>+Math.abs(inc[i]-td[i]).toFixed(2));
            datasets.push({label:t('trend_line','Trend'),data:trendData,borderColor:'#f59e0b',backgroundColor:'transparent',borderWidth:2,tension:0,fill:false,pointRadius:0,segment:ctx=>segFn(ctx.p0DataIndex)});
        }
    } else {
        const perStoreTotals=months.map(m=>{let t=0;userSettings.stores.forEach(s=>{if(s.visible!==false)t+=monthly[m][s.name]||0;});return t*conv;});
        userSettings.stores.forEach((s,i)=>{
            if(s.visible===false)return;
            datasets.push({label:s.name,data:months.map(m=>(monthly[m][s.name]||0)*conv),borderColor:storeColors[i%storeColors.length],backgroundColor:storeColors[i%storeColors.length]+'20',tension:.4,fill:false,segment:ctx=>segFn(ctx.p0DataIndex)});
        });
        if(userSettings.show_trend){
            const trendData=linearRegression(perStoreTotals);
            datasets.push({label:t('trend_line','Trend'),data:trendData,borderColor:'#f59e0b',backgroundColor:'transparent',borderWidth:2,tension:0,fill:false,pointRadius:0,segment:ctx=>segFn(ctx.p0DataIndex)});
        }
    }
    chart=new Chart(ctx,{type:'line',data:{labels:months,datasets},options:{responsive:true,maintainAspectRatio:false,
        interaction:{mode:'index',intersect:false},
        scales:{y:{beginAtZero:true,title:{display:true,text:`Amount (${chartCurrency})`,color:'<?= $theme==='dark'?'#b0b0b0':'#000' ?>'},ticks:{color:'<?= $theme==='dark'?'#b0b0b0':'#000' ?>'},grid:{color:'<?= $theme==='dark'?'#5a5a5a':'rgba(0,0,0,0.1)' ?>'},border:{color:'<?= $theme==='dark'?'#7a7a7a':'#666' ?>'}},
               x:{title:{display:true,text:'Month',color:'<?= $theme==='dark'?'#b0b0b0':'#000' ?>'},ticks:{color:'<?= $theme==='dark'?'#b0b0b0':'#000' ?>'},grid:{color:'<?= $theme==='dark'?'#5a5a5a':'rgba(0,0,0,0.1)' ?>'},border:{color:'<?= $theme==='dark'?'#7a7a7a':'#666' ?>'}}},
        plugins:{legend:{labels:{color:'<?= $theme==='dark'?'#fff':'#000' ?>'}},
                 tooltip:{mode:'index',intersect:false,callbacks:{label:c=>`${c.dataset.label}: ${c.parsed.y.toFixed(2)} ${chartCurrency}`}}}}});
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
                expensesData = data.expenses;
                incomesData = data.incomes||{};
                allDates = data.dates;
                updateChart();
            }
        })
        .catch(()=>updateChart());
}
function toggleChartMode(){chartMode=chartMode==='total'?'stores':'total';updateChart();}

// ══ Exchange Rates ══════════════════════════════════════════════════════════
function loadExchangeRates() {
    const base = document.getElementById('base-currency-select').value;
    fetch(`?get_exchange_rates=1&base=${base}`)
        .then(r=>r.json())
        .then(data=>{
            if(data.status==='success') {
                currentRates = data.rates;
                displayRatesTable(base, data.rates);
                updateRateChart();
            } else {
                showNotification(t('failed_load_exchange_rates','Failed to load exchange rates'), 'error');
            }
        })
        .catch(e=>showNotification(`${t('network_error','Network error')}: ${e}`, 'error'));
}

function displayRatesTable(base, rates) {
    const tbody = document.getElementById('rates-tbody');
    const currencies = ['USD', 'EUR', 'UAH', 'PLN', 'BYN'];
    let html = '';
    currencies.forEach(cur => {
        if (cur === base) return;
        const rate = rates[cur] ? rates[cur].toFixed(4) : 'N/A';
        const usdRate = rates['USD'] ? (1 / rates['USD']).toFixed(4) : 'N/A';
        html += `<tr>
            <td><strong>${cur}</strong></td>
            <td>${rate} ${base}</td>
            <td class="${rates['USD'] && rates['USD'] > 1 ? 'rate-positive' : 'rate-negative'}">${usdRate} USD</td>
        </tr>`;
    });
    tbody.innerHTML = html;
}

function updateRateChart() {
    const currency = document.getElementById('chart-currency-select').value;
    if (!currentRates[currency]) return;
    
    const ctx = document.getElementById('rate-chart').getContext('2d');
    const baseRate = currentRates[currency];
    const historicalRates = [];
    const dateLabels = [];
    const today = new Date();
    for (let i = 30; i >= 0; i--) {
        const d = new Date(today);
        d.setDate(today.getDate() - i);
        const mm = String(d.getMonth()+1).padStart(2,'0');
        const dd = String(d.getDate()).padStart(2,'0');
        dateLabels.push(`${dd}.${mm}`);
        const variation = 1 + (Math.sin(i / 5) * 0.05);
        historicalRates.push(parseFloat((baseRate * variation).toFixed(4)));
    }
    
    if (rateChart) rateChart.destroy();
    rateChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: dateLabels,
            datasets: [{
                label: `${currency} vs ${document.getElementById('base-currency-select').value}`,
                data: historicalRates,
                borderColor: storeColors[0],
                backgroundColor: storeColors[0] + '20',
                tension: 0.4,
                fill: true,
                pointRadius: 2,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: { labels: { color: '<?= $theme==='dark'?'#fff':'#000' ?>' } }
            },
            scales: {
                y: { ticks: { color: '<?= $theme==='dark'?'#fff':'#000' ?>' } },
                x: { ticks: { color: '<?= $theme==='dark'?'#fff':'#000' ?>', maxTicksLimit: 10 } }
            }
        }
    });
}
function buildOCRAnalyticsChart(payload,selectedStore){
    const canvas=document.getElementById('ocr-analytics-chart');
    if(!canvas)return;
    const ctx=canvas.getContext('2d');
    if(ocrAnalyticsChart)ocrAnalyticsChart.destroy();
    const monthly=payload.monthly||[];
    const daily=payload.daily||[];
    if(selectedStore==='__all__'){
        const labels=[...new Set(monthly.map(r=>r.ym))].sort();
        const stores=[...new Set(monthly.map(r=>r.store))];
        const datasets=stores.map((store,i)=>({type:'bar',label:store,data:labels.map(ym=>{const row=monthly.find(r=>r.ym===ym&&r.store===store);return row?Number(row.amount_sum):0;}),backgroundColor:storeColors[i%storeColors.length]+'55',borderColor:storeColors[i%storeColors.length],borderWidth:1,stack:'amount'}));
        const intensity=labels.map(ym=>monthly.filter(r=>r.ym===ym).reduce((s,r)=>s+Number(r.intensity||0),0));
        datasets.push({type:'line',label:'Intensity',data:intensity,yAxisID:'y1',borderColor:'rgb(255,0,0)',backgroundColor:'rgb(255,0,0)',tension:.3,pointRadius:3});
        ocrAnalyticsChart=new Chart(ctx,{data:{labels,datasets},options:{responsive:true,maintainAspectRatio:false,scales:{y:{beginAtZero:true,title:{display:true,text:'Amount',color:'<?= $theme==='dark'?'#d1d5db':'#111827' ?>'},ticks:{color:'<?= $theme==='dark'?'#e5e7eb':'#111827' ?>'},grid:{color:'<?= $theme==='dark'?'rgba(148,163,184,.35)':'rgba(0,0,0,.1)' ?>'}},y1:{beginAtZero:true,position:'right',grid:{drawOnChartArea:false,color:'<?= $theme==='dark'?'rgba(148,163,184,.25)':'rgba(0,0,0,.06)' ?>'},ticks:{color:'<?= $theme==='dark'?'#e5e7eb':'#111827' ?>'},title:{display:true,text:'Intensity',color:'<?= $theme==='dark'?'#d1d5db':'#111827' ?>'}},x:{ticks:{color:'<?= $theme==='dark'?'#e5e7eb':'#111827' ?>'},grid:{color:'<?= $theme==='dark'?'rgba(148,163,184,.2)':'rgba(0,0,0,.06)' ?>'}}},plugins:{legend:{labels:{color:'<?= $theme==='dark'?'#f3f4f6':'#111827' ?>'}}}}});
        return;
    }
    const labels=[...new Set(daily.map(r=>r.date))].sort();
    const amounts=labels.map(d=>{const row=daily.find(r=>r.date===d);return row?Number(row.amount):0;});
    const monthIntensity={};
    monthly.forEach(r=>{monthIntensity[r.ym]=Number(r.intensity||0);});
    const intensity=labels.map(d=>monthIntensity[d.slice(0,7)]||0);
    ocrAnalyticsChart=new Chart(ctx,{data:{labels,datasets:[{type:'bar',label:`${selectedStore} amount`,data:amounts,backgroundColor:'rgba(54,162,235,.38)',borderColor:'rgba(54,162,235,1)',borderWidth:1},{type:'line',label:'Intensity',data:intensity,yAxisID:'y1',borderColor:'rgb(255,0,0)',backgroundColor:'rgb(255,0,0)',tension:.25,pointRadius:2}]},options:{responsive:true,maintainAspectRatio:false,scales:{y:{beginAtZero:true,title:{display:true,text:'Amount',color:'<?= $theme==='dark'?'#d1d5db':'#111827' ?>'},ticks:{color:'<?= $theme==='dark'?'#e5e7eb':'#111827' ?>'},grid:{color:'<?= $theme==='dark'?'rgba(148,163,184,.35)':'rgba(0,0,0,.1)' ?>'}},y1:{beginAtZero:true,position:'right',grid:{drawOnChartArea:false,color:'<?= $theme==='dark'?'rgba(148,163,184,.25)':'rgba(0,0,0,.06)' ?>'},ticks:{color:'<?= $theme==='dark'?'#e5e7eb':'#111827' ?>'},title:{display:true,text:'Intensity',color:'<?= $theme==='dark'?'#d1d5db':'#111827' ?>'}},x:{ticks:{color:'<?= $theme==='dark'?'#e5e7eb':'#111827' ?>'},grid:{color:'<?= $theme==='dark'?'rgba(148,163,184,.2)':'rgba(0,0,0,.06)' ?>'}}},plugins:{legend:{labels:{color:'<?= $theme==='dark'?'#f3f4f6':'#111827' ?>'}}}}});
}
function loadOCRAnalytics(){
    const select=document.getElementById('ocr-analytics-store');
    const store=select?select.value:'__all__';
    fetch(`?get_ocr_stats=1&store=${encodeURIComponent(store)}&year=${encodeURIComponent(analyticsYear)}`).then(r=>r.json()).then(d=>{
        if(d.status!=='success'){showNotification(t('failed_load_analytics','Failed to load analytics'),'error');return;}
        if(select&&select.options.length<=1){
            (d.stores||[]).forEach(s=>{const o=document.createElement('option');o.value=s;o.textContent=s;select.appendChild(o);});
        }
        buildOCRAnalyticsChart(d,store);
        updateYearButtons();
    }).catch(e=>showNotification(`${t('network_error','Network error')}: ${e}`,'error'));
}
function changeAnalyticsYear(delta){
    const target=analyticsYear+delta;
    if(!yearHasData(target))return;
    analyticsYear=target;
    loadOCRAnalytics();
}
function showOCRAnalyticsModal(){showModal('ocr-analytics-modal');updateYearButtons();loadOCRAnalytics();}

function highlightCurrentDate() {
    document.querySelectorAll('.current-date-row').forEach(r=>r.classList.remove('current-date-row'));
    const today = new Date().toISOString().split('T')[0];
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
            loadPage(currentPage);
        } else showNotification(d.message||t('error_generic','Error'),'error');
    }).catch(e=>showNotification(`${t('network_error','Network error')}: ${e}`,'error'));
}

function isValidDate(s){return /^\d{4}-\d{2}-\d{2}$/.test(s)&&!isNaN(new Date(s));}

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
    fetch('',{method:'POST',body:f}).then(r=>r.json()).then(d=>{
        if(d.status!=='success'){showNotification(d.message||t('error_generic','Error'),'error');return;}
        userSettings.currency=document.getElementById('currency').value;
        userSettings.monthly_budget=parseFloat(document.getElementById('monthly-budget').value)||0;
        userSettings.show_incomes=!!(showIncomesEl&&showIncomesEl.checked);
        userSettings.show_trend=!!(showTrendEl&&showTrendEl.checked);
        chartCurrency=userSettings.currency;
        document.querySelectorAll('.currency-btn').forEach(b=>b.classList.toggle('active',b.dataset.currency===chartCurrency));
        loadAvailableYears().then(()=>updateChart());
        loadPage(currentPage);
    }).catch(e=>showNotification(`${t('network_error','Network error')}: ${e}`,'error'));
}
function scheduleSaveTableSettings(){
    clearTimeout(debounceTableTimer);
    debounceTableTimer=setTimeout(saveTableSettingsNow,950);
}
function saveTableSettingsNow(){
    const f=new FormData();
    f.append('save_table_settings','1');
    f.append('sort_by',document.getElementById('sort-by').value);
    f.append('rows_per_page',document.getElementById('rows-per-page').value);
    f.append('start_date',document.getElementById('start-date').value);
    document.querySelectorAll('.visibility-toggle').forEach(btn=>{
        const sid=btn.getAttribute('data-store-id');
        const vis=btn.getAttribute('data-visible')==='true';
        f.append(vis?'store_visible[]':'store_hidden[]',sid);
    });
    fetch('',{method:'POST',body:f}).then(r=>r.json()).then(d=>{
        if(d.status!=='success'){showNotification(d.message||t('error_generic','Error'),'error');return;}
        tableSettings.sort_by=document.getElementById('sort-by').value;
        tableSettings.rows_per_page=parseInt(document.getElementById('rows-per-page').value,10)||25;
        tableSettings.start_date=document.getElementById('start-date').value||'';
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
}

// ══ Stores ════════════════════════════════════════════════════════════════
let currentStoreId=null,storePhotoFile=null,pendingOCRStoreRaw='';
function closeAddEditStoreModal(){pendingOCRStoreRaw='';closeModal('add-edit-store-modal');}
function showAddEditStoreModal(id=null,name='',visible=true,ocrRaw=''){
    currentStoreId=id;
    pendingOCRStoreRaw=ocrRaw||'';
    document.getElementById('add-edit-store-title').textContent=id?translations.edit_store:translations.add_store;
    document.getElementById('store-name-input').value=name;
    document.getElementById('store-photo-input').value='';
    document.getElementById('store-photo-preview').style.display='none';
    const csb=document.getElementById('crop-store-btn');
    csb.style.display='none';
    csb.textContent=translations.crop_photo||'Crop Photo';
    if(cropper){cropper.destroy();cropper=null;}
    storePhotoFile=null;
    showModal('add-edit-store-modal');
}
function previewStorePhoto(input){
    if(input.files&&input.files[0]){
        if(cropper){cropper.destroy();cropper=null;}
        const csb=document.getElementById('crop-store-btn');
        csb.textContent=translations.crop_photo||'Crop Photo';
        storePhotoFile=input.files[0];
        const r=new FileReader();
        r.onload=e=>{const p=document.getElementById('store-photo-preview');p.src=e.target.result;p.style.display='block';csb.style.display='block';};
        r.readAsDataURL(input.files[0]);
    }
}
function cropStorePhoto(){
    const p=document.getElementById('store-photo-preview');
    if(!p||!p.getAttribute('src')) return;
    const csb=document.getElementById('crop-store-btn');
    if(!cropper){
        cropper=new Cropper(p,{aspectRatio:1,viewMode:1,guides:false});
        csb.textContent=translations.apply||'Apply';
        return;
    }
    const canvas=cropper.getCroppedCanvas({width:200,height:200});
    if(!canvas){
        showNotification(translations.crop_failed||'Could not crop image, try again','error');
        return;
    }
    canvas.toBlob(blob=>{storePhotoFile=blob;p.src=URL.createObjectURL(blob);cropper.destroy();cropper=null;csb.style.display='none';csb.textContent=translations.crop_photo||'Crop Photo';},'image/jpeg',0.9);
}
function rekeyExpensesDataStore(oldN,newN){
    if (!oldN || oldN === newN) return;
    Object.keys(expensesData).forEach(d => {
        const o = expensesData[d];
        if (o && Object.prototype.hasOwnProperty.call(o, oldN)) {
            o[newN] = o[oldN];
            delete o[oldN];
        }
    });
}
const visEyeSvg = '<svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/></svg>';
const visEyeOffSvg = '<svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M12 7c2.76 0 5 2.24 5 5 0 .65-.13 1.26-.36 1.83l2.92 2.92c1.51-1.26 2.7-2.89 3.43-4.75-1.73-4.39-6-7.5-11-7.5-1.4 0-2.74.25-3.98.7l2.16 2.16C10.74 7.13 11.35 7 12 7zM2 4.27l2.28 2.28.46.46C3.08 8.3 1.78 10.02 1 12c1.73 4.39 6 7.5 11 7.5 1.55 0 3.03-.3 4.38-.84l.42.42L19.73 22 21 20.73 3.27 3 2 4.27zM7.53 9.8l1.55 1.55c-.05.21-.08.43-.08.65 0 1.66 1.34 3 3 3 .22 0 .44-.03.65-.08l1.55 1.55c-.67.33-1.41.53-2.2.53-2.76 0-5-2.24-5-5 0-.79.2-1.53.53-2.2zm4.31-.78l3.15 3.15.02-.16c0-1.66-1.34-3-3-3l-.17.01z"/></svg>';
function applyStoreToSettingsUI(s, withPhoto) {
    const idStr = String(s.id);
    const list = document.getElementById('store-list');
    const prevI = userSettings.stores.findIndex(x => String(x.id) === idStr);
    const oldNm = prevI >= 0 ? userSettings.stores[prevI].name : null;
    rekeyExpensesDataStore(oldNm, s.name);
    if (prevI >= 0) userSettings.stores[prevI] = s; else userSettings.stores.push(s);
    const fnPart = s.name.replace(/[^a-zA-Z0-9]/g, '_');
    const photoU = 'users/' + currentUserFolder + '/store_' + fnPart + '.jpg?t=' + Date.now();
    let row = list.querySelector('.store-item[data-store-id="' + idStr + '"]');
    if (row) {
        row.dataset.storeName = s.name;
        const sp = row.querySelector('span');
        if (sp) sp.textContent = s.name;
        const eBtn = row.querySelector('.edit-store-btn');
        if (eBtn) eBtn.onclick = () => showAddEditStoreModal(s.id, s.name, s.visible);
        const dBtn = row.querySelector('.delete-store-btn');
        if (dBtn) dBtn.onclick = () => confirmDeleteStore(s.id, s.name);
        if (withPhoto) {
            let im = row.querySelector('img');
            if (!im) {
                im = document.createElement('img');
                im.className = 'store-icon';
                im.alt = s.name;
                row.insertBefore(im, row.firstChild);
            }
            im.src = photoU;
            im.style.display = '';
        }
    } else {
        const tpl = list.querySelector('.store-item');
        if (tpl) {
            const c = tpl.cloneNode(true);
            c.dataset.storeId = idStr;
            c.dataset.storeName = s.name;
            c.querySelector('span').textContent = s.name;
            c.querySelector('.edit-store-btn').onclick = () => showAddEditStoreModal(s.id, s.name, s.visible);
            c.querySelector('.delete-store-btn').onclick = () => confirmDeleteStore(s.id, s.name);
            if (withPhoto) {
                let im = c.querySelector('img');
                if (!im) { im = document.createElement('img'); im.className = 'store-icon'; c.insertBefore(im, c.firstChild); }
                im.src = photoU; im.alt = s.name; im.style.display = '';
            } else { c.querySelectorAll('img').forEach(n => n.remove()); }
            list.appendChild(c);
        } else {
            const d = document.createElement('div');
            d.className = 'store-item';
            d.dataset.storeId = idStr;
            d.dataset.storeName = s.name;
            if (withPhoto) {
                const im = document.createElement('img');
                im.className = 'store-icon';
                im.src = photoU;
                im.alt = s.name;
                d.appendChild(im);
            }
            const sp = document.createElement('span');
            sp.style.flex = '1';
            sp.textContent = s.name;
            d.appendChild(sp);
            const eBtn = document.createElement('button');
            eBtn.className = 'edit-store-btn';
            eBtn.textContent = translations.edit_store || 'Edit';
            eBtn.onclick = () => showAddEditStoreModal(s.id, s.name, s.visible);
            d.appendChild(eBtn);
            const dBtn = document.createElement('button');
            dBtn.className = 'delete-store-btn';
            dBtn.innerHTML = '<svg viewBox="0 0 24 24"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg> ' + (translations.delete || 'Delete');
            dBtn.onclick = () => confirmDeleteStore(s.id, s.name);
            d.appendChild(dBtn);
            list.appendChild(d);
        }
    }
    const vtb = document.querySelector('.store-visibility-table tbody');
    if (!vtb) return;
    const tr0 = vtb.querySelector('tr');
    const oldVisBtn = vtb.querySelector('.visibility-toggle[data-store-id="' + idStr + '"]');
    if (oldVisBtn) {
        const tr = oldVisBtn.closest('tr');
        tr.querySelector('td').textContent = s.name;
        oldVisBtn.setAttribute('data-visible', s.visible ? 'true' : 'false');
        oldVisBtn.innerHTML = s.visible ? visEyeSvg : visEyeOffSvg;
        oldVisBtn.onclick = () => toggleStoreVisibility(oldVisBtn);
    } else if (tr0) {
        const tr = tr0.cloneNode(true);
        tr.querySelector('td').textContent = s.name;
        const vb = tr.querySelector('.visibility-toggle');
        vb.setAttribute('data-store-id', s.id);
        vb.setAttribute('data-visible', s.visible ? 'true' : 'false');
        vb.innerHTML = s.visible ? visEyeSvg : visEyeOffSvg;
        vb.onclick = () => toggleStoreVisibility(vb);
        vtb.appendChild(tr);
    } else {
        const tr = document.createElement('tr');
        const td1 = document.createElement('td');
        td1.textContent = s.name;
        const td2 = document.createElement('td');
        const vb = document.createElement('button');
        vb.className = 'visibility-toggle';
        vb.setAttribute('data-store-id', s.id);
        vb.setAttribute('data-visible', s.visible ? 'true' : 'false');
        vb.innerHTML = s.visible ? visEyeSvg : visEyeOffSvg;
        vb.onclick = () => toggleStoreVisibility(vb);
        td2.appendChild(vb);
        tr.appendChild(td1);
        tr.appendChild(td2);
        vtb.appendChild(tr);
    }
}
function saveStore(){
    const name=document.getElementById('store-name-input').value.trim();
    if(!name){showNotification((translations.store_name||'Store name')+' is required','error');return;}
    const withPhoto=!!storePhotoFile;
    const pendingRaw=pendingOCRStoreRaw;
    const fd=new FormData();
    fd.append('add_edit_store','1');
    fd.append('store_id',currentStoreId||Date.now());
    fd.append('store_name',name);
    if(storePhotoFile) fd.append('store_photo',storePhotoFile,'store_photo.jpg');
    fetch('',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
        if(d.status!=='success'){ showNotification(d.message||t('error_generic','Error'),'error'); return; }
        closeAddEditStoreModal();
        showNotification(translations.store_saved || 'Store saved', 'success');
        if (d.store) applyStoreToSettingsUI(d.store, withPhoto);
        if(pendingRaw&&d.store){const selected=readUnresolvedSelections();selected[pendingRaw]=d.store.name;renderUnresolvedModal(selected);}
        storePhotoFile=null;
    }).catch(e=>showNotification(`${t('network_error','Network error')}: ${e}`,'error'));
}
function confirmDeleteStore(id,name){
    pendingDelete={type:'store',id,name};
    document.getElementById('confirm-delete-message').textContent=translations.confirm_delete_store||`Delete store "${name}"?`;
    showModal('confirm-delete-modal');
}
function executeDelete(){if(pendingDelete.type==='store')deleteStore(pendingDelete.id,pendingDelete.name);}
function removeDataTableStoreColumnByName(storeName) {
    const ths = document.querySelectorAll('#data-table thead th');
    let col = -1;
    ths.forEach((th, idx) => { if (idx > 0 && th.textContent.trim() === storeName) col = idx; });
    if (col < 0) return;
    document.querySelectorAll('#data-table tr').forEach(tr => { const c = tr.children[col]; if (c) c.remove(); });
    if (typeof updateChart === 'function') updateChart();
}
function deleteStore(id,name){
    const fd=new FormData();fd.append('delete_store','1');fd.append('store_id',id);fd.append('store_name',name);
    fetch('',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
        if(d.status!=='success'){ showNotification(d.message||t('error_generic','Error'),'error'); return; }
        const idS=String(id);
        userSettings.stores=userSettings.stores.filter(s=>String(s.id)!==idS);
        Object.keys(expensesData).forEach(dt=>{if(expensesData[dt]&&Object.prototype.hasOwnProperty.call(expensesData[dt],name)) delete expensesData[dt][name];});
        const row=document.querySelector('#store-list .store-item[data-store-id="'+idS+'"]');
        if(row) row.remove();
        const vb=document.querySelector('.store-visibility-table .visibility-toggle[data-store-id="'+idS+'"]');
        if(vb) vb.closest('tr').remove();
        removeDataTableStoreColumnByName(name);
        showNotification(translations.delete_store_ok||'Store deleted','success');
        closeModal('confirm-delete-modal');
    }).catch(e=>showNotification(`${t('network_error','Network error')}: ${e}`,'error'));
}
function toggleStoreVisibility(btn){
    const cur=btn.getAttribute('data-visible')==='true';
    const nv=!cur;
    btn.setAttribute('data-visible',nv);
    btn.innerHTML=nv?
        '<svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/></svg>':
        '<svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M12 7c2.76 0 5 2.24 5 5 0 .65-.13 1.26-.36 1.83l2.92 2.92c1.51-1.26 2.7-2.89 3.43-4.75-1.73-4.39-6-7.5-11-7.5-1.4 0-2.74.25-3.98.7l2.16 2.16C10.74 7.13 11.35 7 12 7zM2 4.27l2.28 2.28.46.46C3.08 8.3 1.78 10.02 1 12c1.73 4.39 6 7.5 11 7.5 1.55 0 3.03-.3 4.38-.84l.42.42L19.73 22 21 20.73 3.27 3 2 4.27zM7.53 9.8l1.55 1.55c-.05.21-.08.43-.08.65 0 1.66 1.34 3 3 3 .22 0 .44-.03.65-.08l1.55 1.55c-.67.33-1.41.53-2.2.53-2.76 0-5-2.24-5-5 0-.79.2-1.53.53-2.2zm4.31-.78l3.15 3.15.02-.16c0-1.66-1.34-3-3-3l-.17.01z"/></svg>';
    scheduleSaveTableSettings();
}

// ══ Crop modal ═════════════════════════════════════════════════════════════
function closeCropModal(){closeModal('crop-modal');if(cropper){cropper.destroy();cropper=null;}}
function cropAndSave(){
    if(!cropper)return;
    const canvas=cropper.getCroppedCanvas({width:200,height:200});
    if(!canvas){
        showNotification(translations.crop_failed||'Could not crop image, try again','error');
        return;
    }
    canvas.toBlob(blob=>{
        if (currentCropType !== 'store' || !currentCropId) { closeCropModal(); return; }
        const st = (userSettings.stores || []).find(x => String(x.id) === String(currentCropId));
        const n = (st && st.name) || '';
        if (!n) { showNotification(t('store_not_found','Store not found'), 'error'); return; }
        const fd = new FormData();
        fd.append('add_edit_store', '1');
        fd.append('store_id', currentCropId);
        fd.append('store_name', n);
        fd.append('store_photo', blob, 'store_photo.jpg');
        fetch('', { method: 'POST', body: fd }).then(r => r.json()).then(d => {
            if (d.status !== 'success') { showNotification(d.message || t('error_generic','Error'), 'error'); return; }
            closeCropModal();
            if (d.store) applyStoreToSettingsUI(d.store, true);
            else showNotification(translations.store_saved || 'Store saved', 'success');
        });
    },'image/jpeg',0.9);
}

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
    if(!confirm('Are you sure you want to delete this backup?'))return;
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
    const safeUser = currentUser.replace(/[^a-zA-Z0-9]/g,'_');
    fetch(`backups/${safeUser}/${fn}`).then(r=>r.text()).then(data=>{
        const preview=document.getElementById('backup-preview');
        const lines=data.trim().split('\n');
        if(!lines.length){preview.innerHTML='<div style="text-align:center;padding:20px;color:#666;">Empty backup</div>';showModal('view-backup-modal');return;}
        const headers=lines[0].split(',').map(h=>h.trim());
        let html=`<div style="margin-bottom:20px;"><h4 style="margin:0 0 5px;color:var(--text-color);">${fn}</h4><div style="font-size:12px;color:#666;">${lines.length-1} records</div></div><div style="overflow:auto;max-height:65vh;"><table style="width:100%;border-collapse:collapse;color:var(--text-color);font-size:14px;"><thead><tr style="background:var(--primary-color);color:white;">`;
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
        if(total>0)html+=`<tfoot><tr style="background:var(--primary-color);color:white;"><td colspan="${headers.length}" style="padding:12px;text-align:right;font-weight:bold;">Total:</td><td style="padding:12px;text-align:right;font-family:monospace;font-weight:bold;">${total.toFixed(2)}</td></tr></tfoot>`;
        html+='</table></div>';
        preview.innerHTML=html;showModal('view-backup-modal');
    }).catch(e=>showNotification(`${t('error_loading_backup','Error loading backup')}: ${e}`,'error'));
}
function formatDateWithMonthName(s){
    const months=['January','February','March','April','May','June','July','August','September','October','November','December'];
    s=s.replace(/['"]/g,'');
    let d;
    if(s.match(/^\d{4}-\d{2}-\d{2}$/)){const p=s.split('-');d=new Date(p[0],p[1]-1,p[2]);}
    else if(s.match(/^\d{2}-\d{2}-\d{4}$/)){const p=s.split('-');d=new Date(p[2],p[1]-1,p[0]);}
    else if(s.match(/^\d{2}-\d{2}-\d{2}$/)){const p=s.split('-');d=new Date('20'+p[2],p[1]-1,p[0]);}
    else d=new Date(s);
    if(isNaN(d)) return s;
    return `${d.getDate()} ${months[d.getMonth()]} ${d.getFullYear()}`;
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
window.handleEmbeddedOCRFinished=function(message){closeImportModal();showNotification(message||ocrT.done,'success');setTimeout(()=>window.location.reload(),250);};
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
    cd.datasets.forEach((d,i)=>{d.borderColor=storeColors[i%storeColors.length];d.backgroundColor=storeColors[i%storeColors.length]+'80';d.borderWidth=3;d.pointRadius=5;d.tension=0.4;});
    return cd;
}
function shareChartOptions(isMobile,titleSize){
    return {responsive:false,maintainAspectRatio:false,animation:false,scales:{y:{beginAtZero:true,title:{display:true,text:`Amount (${chartCurrency})`,color:'#333'},ticks:{color:'#333'}},x:{title:{display:true,text:'Month',color:'#333'},ticks:{color:'#333'}}},plugins:{legend:{labels:{color:'#333'}},title:{display:true,text:`Expense Tracker - ${currentChartYear}`,color:'#333',font:{size:titleSize||(isMobile?14:18),weight:'bold'}}}};
}
function renderShareChartCanvas(width,height,isMobile,titleSize){
    const canvas=document.createElement('canvas');
    canvas.width=width;canvas.height=height;
    const ctx=canvas.getContext('2d');
    ctx.fillStyle='white';ctx.fillRect(0,0,width,height);
    const inst=new Chart(ctx,{type:'line',data:getShareChartData(),options:shareChartOptions(isMobile,titleSize)});
    inst.update('none');
    return {canvas,chart:inst};
}
function canvasToPngBlob(canvas){
    return new Promise((resolve,reject)=>canvas.toBlob(blob=>blob?resolve(blob):reject(new Error('empty')),'image/png'));
}
async function copyImageBlob(blob){
    if(navigator.clipboard&&window.isSecureContext&&window.ClipboardItem){
        await navigator.clipboard.write([new ClipboardItem({'image/png':blob})]);
        return;
    }
    const url=URL.createObjectURL(blob);
    const img=new Image();
    await new Promise((resolve,reject)=>{img.onload=resolve;img.onerror=reject;img.src=url;});
    const box=document.createElement('div');
    box.contentEditable='true';
    box.style.cssText='position:fixed;left:-9999px;top:0;opacity:0;';
    box.appendChild(img);
    document.body.appendChild(box);
    const range=document.createRange();
    range.selectNode(img);
    const sel=window.getSelection();
    sel.removeAllRanges();
    sel.addRange(range);
    const ok=document.execCommand('copy');
    document.body.removeChild(box);
    sel.removeAllRanges();
    URL.revokeObjectURL(url);
    if(!ok)throw new Error('copy');
}
function showShareModal(){
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
            ctx.fillStyle='white';ctx.fillRect(0,0,w,h);
            shareChart=new Chart(ctx,{type:'line',data:getShareChartData(),options:shareChartOptions(isMobile)});
            shareChart.update('none');
        }catch(e){showNotification(translations.copy_failed||'Failed to copy','error');}
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
async function copyChartToClipboard(){
    if(!chart){showNotification(t('chart_not_available','Chart not available'),'error');return;}
    try{
        const {canvas,chart:dc}=renderShareChartCanvas(1000,600,false,24);
        const blob=await canvasToPngBlob(canvas);
        dc.destroy();
        await copyImageBlob(blob);
        showNotification(translations.chart_copied||'Chart copied','success');
    }catch(e){showNotification(translations.copy_failed||'Failed to copy','error');}
}
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

// ══ DOMContentLoaded ═══════════════════════════════════════════════════════
document.addEventListener('DOMContentLoaded',function(){
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
    if(iw)iw.addEventListener('click',e=>{e.stopPropagation();closeImportMenu();openImportModal();});
    if(ic)ic.addEventListener('click',e=>{e.stopPropagation();closeImportMenu();document.getElementById('bank-csv-input').click();});
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
    const sb=document.getElementById('sort-by');
    if(sb)sb.addEventListener('change',scheduleSaveTableSettings);
    const rpp=document.getElementById('rows-per-page');
    if(rpp){rpp.addEventListener('input',scheduleSaveTableSettings);rpp.addEventListener('change',scheduleSaveTableSettings);}
    const sd=document.getElementById('start-date');
    if(sd)sd.addEventListener('change',scheduleSaveTableSettings);
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
    im.addEventListener('click',e=>{if(e.target===im)closeImportModal();});
    if(location.hash==='#ocr'){openImportModal();}
    window.addEventListener('hashchange',()=>{
        if(location.hash==='#ocr'){openImportModal();return;}
        const im=document.getElementById('import-modal');
        if(im&&im.style.display==='flex')closeImportModal();
    });
    window.addEventListener('message',e=>{
        if(e.data&&e.data.type==='ocr_progress'){setOCRLog((e.data.lines||[]).map(localizeOCRLine));return;}
        if(e.data&&e.data.type==='ocr_finished'){closeImportModal();showNotification(e.data.message||ocrT.done,'success');setTimeout(()=>window.location.reload(),250);return;}
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

    renderPagination();

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
