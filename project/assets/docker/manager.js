        if (window.monitorTimer) clearInterval(window.monitorTimer);
        var DOCKER_CFG = window.DOCKER_CFG || {};
        var I18N = DOCKER_CFG.I18N || {};
        var I18N_EN = DOCKER_CFG.I18N_EN || {};
        var sectionTitles = DOCKER_CFG.sectionTitles || {};
        var startupModals = DOCKER_CFG.startupModals || [];
        var modalIcons = DOCKER_CFG.modalIcons || {};
        var modalLabels = DOCKER_CFG.modalLabels || {};
        function t(key){return I18N[key] ?? I18N_EN[key] ?? key;}
        function tf(key, vars){let text=t(key);for(const n in (vars||{})){text=text.split('{'+n+'}').join(vars[n]);}return text;}
        function dockerNavigate(href){
            if(window.DockerPanel && window.DockerPanel.mounted()) window.DockerPanel.navigate(href);
            else window.location.href = href;
        }
        function dockerReload(){
            if(window.DockerPanel && window.DockerPanel.mounted()) window.DockerPanel.refresh();
            else window.location.reload();
        }
        var dockerRoot = function(){ return document.getElementById('docker-app'); };
        var pendingActionHref = '';
        var liveBusy = false;
        var monitorContainerId = '';
        var monitorTimer = null;
        var chartHistory = {};
        function toBytesText(v){
            const n = Number(v||0);
            if(!Number.isFinite(n)||n<=0)return '0 B';
            const u=['B','KB','MB','GB','TB']; let x=n; let i=0;
            while(x>=1024&&i<u.length-1){x/=1024;i++;}
            return `${x.toFixed(i===0?0:2)} ${u[i]}`;
        }
        function pushMetric(name,val){
            if(!chartHistory[name])chartHistory[name]=[];
            chartHistory[name].push(Number(val)||0);
            if(chartHistory[name].length>36)chartHistory[name].shift();
        }
        function drawChart(canvasId, keys, scaleLabelId, unit){
            const c=document.getElementById(canvasId); if(!c)return 0;
            const dpr=window.devicePixelRatio||1;
            const cw=c.clientWidth||300; const ch=c.clientHeight||86;
            c.width=Math.floor(cw*dpr); c.height=Math.floor(ch*dpr);
            const ctx=c.getContext('2d'); ctx.setTransform(dpr,0,0,dpr,0,0);
            const w=cw; const h=ch; const p={l:56,r:8,t:8,b:12};
            const pw=w-p.l-p.r; const ph=h-p.t-p.b;
            ctx.clearRect(0,0,w,h);
            ctx.fillStyle='rgba(0,0,0,0.08)'; ctx.fillRect(p.l,p.t,pw,ph);
            ctx.strokeStyle='rgba(255,255,255,0.09)'; ctx.lineWidth=1;
            for(let i=0;i<=4;i++){const y=p.t+(ph*i/4);ctx.beginPath();ctx.moveTo(p.l,y);ctx.lineTo(p.l+pw,y);ctx.stroke();}
            let max=1;
            keys.forEach(key=>{const arr=chartHistory[key]||[];if(arr.length){max=Math.max(max,...arr);}});
            const colors=['#22d3ee','#f59e0b','#4ade80'];
            keys.forEach((key,idx)=>{
                const arr=chartHistory[key]||[]; if(arr.length<2)return;
                ctx.strokeStyle=colors[idx]||'#22d3ee'; ctx.lineWidth=2; ctx.beginPath();
                arr.forEach((v,i)=>{const x=p.l+(i*(pw/(arr.length-1))); const y=p.t+ph-((v/max)*ph); if(i===0)ctx.moveTo(x,y); else ctx.lineTo(x,y);});
                ctx.stroke();
            });
            const topLabel=unit==='bytes'?toBytesText(max):(unit==='percent'?`${max.toFixed(0)}%`:Math.round(max).toString());
            ctx.fillStyle='rgba(148,163,184,0.9)'; ctx.font='10px sans-serif';
            ctx.textAlign='right'; ctx.textBaseline='middle';
            const fmt=v=>unit==='bytes'?toBytesText(v):(unit==='percent'?`${v.toFixed(0)}%`:Math.round(v).toString());
            [[0,max],[2,max/2],[4,0]].forEach(([i,v])=>ctx.fillText(fmt(v),p.l-6,Math.min(h-6,Math.max(6,p.t+ph*i/4))));
            const sl=document.getElementById(scaleLabelId);
            if(sl)sl.textContent=unit==='bytes'?tf('max_label',{value:toBytesText(max)}):(unit==='percent'?tf('max_label',{value:max.toFixed(1)+'%'}):tf('max_label',{value:String(Math.round(max))}));
            return max;
        }
        function updateLiveUi(stats){
            if(!stats)return;
            const set=(id,val)=>{const el=document.getElementById(id);if(el)el.textContent=val;};
            set('mCpu', `${Number(stats.cpu_total||0).toFixed(1)}%`);
            set('mMem', `${toBytesText(stats.mem_used)} / ${toBytesText(stats.mem_limit)}`);
            set('mNet', `${toBytesText(stats.net_rx)} / ${toBytesText(stats.net_tx)}`);
            set('mIo', `${toBytesText(stats.blk_read)} / ${toBytesText(stats.blk_write)}`);
            set('mPids', String(stats.pids || 0));
            set('mContainers', monitorContainerId ? monitorContainerId.slice(0,12) : t('na'));
            pushMetric('cpu',stats.cpu_total); pushMetric('mem',stats.mem_used); pushMetric('rx',stats.net_rx); pushMetric('tx',stats.net_tx); pushMetric('br',stats.blk_read); pushMetric('bw',stats.blk_write); pushMetric('pids',stats.pids);
            if(monitorContainerId)try{localStorage.setItem('docker_chart_'+monitorContainerId,JSON.stringify(chartHistory));}catch(e){}
            drawChart('chartCpu',['cpu'],'sCpu','percent');
            drawChart('chartMem',['mem'],'sMem','bytes');
            drawChart('chartNet',['rx','tx'],'sNet','bytes');
            drawChart('chartIo',['br','bw'],'sIo','bytes');
            drawChart('chartPids',['pids'],'sPids','count');
            drawChart('chartContainers',['cpu','pids'],'sContainer','count');
        }
        async function refreshLive(){
            if(document.hidden||liveBusy||!monitorContainerId)return;
            liveBusy=true;
            try{
                const u=new URL(window.DockerPanel && window.DockerPanel.mounted() ? window.DockerPanel.baseUrl() : window.location.href, window.location.origin);
                u.searchParams.set('view','stats_data');
                u.searchParams.set('id',monitorContainerId);
                u.searchParams.set('_ts',Date.now().toString());
                const r=await fetch(u.toString(),{credentials:'same-origin'});
                const d=await r.json();
                if(d&&d.ok&&d.stats)updateLiveUi(d.stats);
            }catch(e){}
            liveBusy=false;
        }
        function startContainerMonitor(containerId, containerName){
            if(!containerId)return;
            monitorContainerId=containerId;
            Object.keys(chartHistory).forEach(k=>{chartHistory[k]=[];});
            const sec=document.getElementById('chartsSection');
            if(sec)sec.style.display='block';
            const mt=document.getElementById('monitorTitle');
            if(mt)mt.textContent=tf('live_monitoring_colon',{name:containerName||containerId.slice(0,12)});
            var saved=localStorage.getItem('docker_chart_'+containerId);
            if(saved){try{var p=JSON.parse(saved);if(p&&typeof p==='object'){Object.keys(p).forEach(function(k){if(Array.isArray(p[k]))chartHistory[k]=p[k];});}}catch(e){}}
            if(Object.keys(chartHistory).some(function(k){return chartHistory[k].length>0;})){
                drawChart('chartCpu',['cpu'],'sCpu','percent');
                drawChart('chartMem',['mem'],'sMem','bytes');
                drawChart('chartNet',['rx','tx'],'sNet','bytes');
                drawChart('chartIo',['br','bw'],'sIo','bytes');
                drawChart('chartPids',['pids'],'sPids','count');
                drawChart('chartContainers',['cpu','pids'],'sContainer','count');
            }
            refreshLive();
            if(monitorTimer)clearInterval(monitorTimer);
            monitorTimer=setInterval(refreshLive,5000);
        }
        function applyContainerFilter(stackName){
            const cf=document.getElementById('containerFilter');
            if(cf)cf.value=stackName||'';
            const q=(stackName||'').trim().toLowerCase();
            document.querySelectorAll('#containersSection .c-row').forEach(row=>{
                const s=(row.getAttribute('data-search')||'').toLowerCase();
                const ok=!q||s.indexOf(q)!==-1;
                row.style.display=ok?'':'none';
                const det=row.nextElementSibling;
                if(det&&det.classList.contains('c-actions')&&!ok){det.style.display='none';row.classList.remove('expanded');}
            });
        }
        function showSection(section, el) {
            ['stacks','containers','networks','volumes','images','info'].forEach(s => {
                const n = document.getElementById(s + 'Section');
                if (n) n.style.display = 'none';
            });
            const sec = document.getElementById(section + 'Section');
            if (sec) sec.style.display = 'block';
            document.getElementById('pageTitle').innerText = sectionTitles[section] || section;
            document.querySelectorAll('.nav-item').forEach(item => { item.classList.remove('active'); });
            if (el) el.classList.add('active');
            const fb = document.getElementById('containerFilterBar');
            if (fb) fb.style.display = (section === 'containers' || section === 'stacks') ? 'flex' : 'none';
            const charts = document.getElementById('chartsSection');
            if (charts && section !== 'containers') charts.style.display = 'none';
            if (charts && section === 'containers' && monitorContainerId) charts.style.display = 'block';
            dockerSaveState();
        }
        function dockerSaveState() {
            try {
                const active = document.querySelector('.nav-item.active');
                const section = active && active.getAttribute('onclick') ? (active.getAttribute('onclick').match(/showSection\('(\w+)'/) || [])[1] : '';
                if (section) localStorage.setItem('docker_active_section', section);
                const cf = document.getElementById('containerFilter');
                localStorage.setItem('docker_filter', cf ? cf.value : '');
                const expanded = Array.from(document.querySelectorAll('.c-row.expanded')).map(r => r.getAttribute('data-search') || '');
                localStorage.setItem('docker_expanded', JSON.stringify(expanded));
            } catch (e) {}
        }
        function dockerRestoreState() {
            let section = 'stacks';
            try {
                const saved = localStorage.getItem('docker_active_section');
                if (saved && document.getElementById(saved + 'Section')) section = saved;
            } catch (e) {}
            showSection(section, document.querySelector('.nav-item[data-section="' + section + '"]'));
            let filter = '';
            try { filter = localStorage.getItem('docker_filter') || ''; } catch (e) {}
            if (filter) applyContainerFilter(filter);
            let expanded = [];
            try { expanded = JSON.parse(localStorage.getItem('docker_expanded') || '[]') || []; } catch (e) {}
            expanded.forEach(function(key) {
                const row = document.querySelector('.c-row[data-search="' + String(key).replace(/"/g, '\\"') + '"]');
                if (row && !row.classList.contains('expanded')) row.click();
            });
        }
        function toggleSidebar() {
            document.getElementById('dockerSidebar').classList.toggle('open');
            document.getElementById('sidebarBackdrop').classList.toggle('show');
        }
        function closeSidebar() {
            document.getElementById('dockerSidebar').classList.remove('open');
            document.getElementById('sidebarBackdrop').classList.remove('show');
        }
        function openDockerModal(e, href, title) {
            e.preventDefault();
            e.stopPropagation();
            const modal = document.getElementById('dockerModal');
            const body = document.getElementById('dockerModalBody');
            document.getElementById('dockerModalTitle').textContent = title || '';
            body.innerHTML = '';
            modal.classList.add('active');
            modal.setAttribute('aria-hidden', 'false');
            if (window.DockerPanel) window.DockerPanel.loadInto(body, href);
        }
        function closeDockerModal() {
            const modal = document.getElementById('dockerModal');
            if (!modal) return;
            modal.classList.remove('active');
            modal.setAttribute('aria-hidden', 'true');
            document.getElementById('dockerModalBody').innerHTML = '';
        }
        function closeDockerModalOnBackdrop(e) {
            if (e.target && e.target.id === 'dockerModal') closeDockerModal();
        }
        function setModalIcon(target, type) {
            target.classList.remove('success', 'error', 'warn');
            if (type === 'success' || type === 'error' || type === 'warn') target.classList.add(type);
            target.innerHTML = modalIcons[type] || modalIcons.info || '';
        }
        function showDockerToast(notice) {
            const type = notice.type === 'error' ? 'error' : (notice.type === 'success' ? 'success' : 'info');
            const notification = document.createElement('div');
            notification.className = 'notification ' + type;
            notification.innerHTML =
                (modalIcons[type] || modalIcons.info || '') +
                '<span class="notification-content"></span>' +
                '<button class="notification-close" type="button">&times;</button>';
            notification.querySelector('.notification-content').textContent =
                (notice.title ? notice.title + ': ' : '') + (notice.message || '');
            dockerRoot().appendChild(notification);
            const dismiss = () => {
                if (!notification.parentNode) return;
                notification.classList.remove('show');
                setTimeout(() => { notification.remove(); updateDockerToastStack(); }, 250);
            };
            notification.querySelector('.notification-close').addEventListener('click', dismiss);
            updateDockerToastStack();
            setTimeout(() => { notification.classList.add('show'); }, 50);
            setTimeout(dismiss, 4000);
        }
        function updateDockerToastStack() {
            let offset = 0;
            Array.from(document.querySelectorAll('.notification')).reverse().forEach(el => {
                el.style.bottom = (24 + offset) + 'px';
                offset += el.offsetHeight + 10;
            });
        }
        function confirmDockerAction(e, link) {
            e.preventDefault();
            e.stopPropagation();
            pendingActionHref = link.href;
            document.getElementById('confirmModalTitle').textContent = modalLabels.confirmTitle || '';
            document.getElementById('confirmModalText').textContent = link.getAttribute('data-confirm') || '';
            const modal = document.getElementById('confirmModal');
            modal.classList.add('active');
            modal.setAttribute('aria-hidden', 'false');
        }
        function closeConfirmModal() {
            pendingActionHref = '';
            const modal = document.getElementById('confirmModal');
            modal.classList.remove('active');
            modal.setAttribute('aria-hidden', 'true');
        }
        function closeConfirmModalOnBackdrop(e) {
            if (e.target && e.target.id === 'confirmModal') closeConfirmModal();
        }
        function submitConfirmedAction() {
            const href = pendingActionHref;
            closeConfirmModal();
            if (href) dockerNavigate(href);
        }
        if (!window.__dockerKeyBound) {
            window.__dockerKeyBound = true;
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && dockerRoot()) {
                    closeDockerModal();
                    closeConfirmModal();
                }
            });
        }
        dockerRoot().addEventListener('click', function(e) {
            const a = e.target.closest('a[href*="/project/docker/?"]');
            if (!a || a.target === '_blank' || e.defaultPrevented) return;
            e.preventDefault();
            const inModal = a.closest('#dockerModalBody');
            if (inModal && window.DockerPanel) window.DockerPanel.loadInto(inModal, a.href);
            else dockerNavigate(a.href);
        });
        dockerRoot().addEventListener('submit', function(e) {
            const form = e.target.closest('#dockerModalBody form');
            if (!form || !window.DockerPanel) return;
            e.preventDefault();
            window.DockerPanel.submitInto(document.getElementById('dockerModalBody'), form);
        });
        document.querySelectorAll('.containers-table-wrap').forEach(wrap => {
            const syncPanelOffset = () => {
                wrap.style.setProperty('--table-scroll-left', `${wrap.scrollLeft}px`);
            };
            wrap.addEventListener('scroll', syncPanelOffset, { passive: true });
            syncPanelOffset();
        });
        document.querySelectorAll('.c-row').forEach(row => {
            row.addEventListener('click', function(e) {
                if (e.target.closest('a')) return;
                const open = !this.classList.contains('expanded');
                document.querySelectorAll('.c-row').forEach(r => { r.classList.remove('expanded'); });
                document.querySelectorAll('.c-actions').forEach(a => { a.style.display = 'none'; });
                if (open) {
                    this.classList.add('expanded');
                    const next = this.nextElementSibling;
                    if (next && next.classList.contains('c-actions')) next.style.display = 'table-row';
                    if (this.closest('#containersSection')) {
                        startContainerMonitor(this.getAttribute('data-container-id') || '', this.getAttribute('data-container-name') || '');
                    }
                }
                dockerSaveState();
            });
            row.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); this.click(); }
            });
        });
        var cf = document.getElementById('containerFilter');
        if (cf) cf.addEventListener('input', function() {
            const q = this.value.trim().toLowerCase();
            document.querySelectorAll('#containersSection .c-row, #imagesSection .c-row, #volumesSection .c-row, #stacksSection .c-row').forEach(row => {
                const s = row.getAttribute('data-search') || '';
                const ok = !q || s.indexOf(q) !== -1;
                row.style.display = ok ? '' : 'none';
                const det = row.nextElementSibling;
                if (det && det.classList.contains('c-actions')) {
                    if (!ok) { det.style.display = 'none'; row.classList.remove('expanded'); }
                }
            });
            dockerSaveState();
        });
        startupModals.forEach(showDockerToast);
        dockerRestoreState();
        setTimeout(function(){
            document.querySelectorAll('#stacksSection .c-row').forEach(function(r){
                var cells=r.querySelectorAll('td');
                if(cells.length>=2&&cells[1].textContent.trim()==='1'){r.click();}
            });
        },100);

