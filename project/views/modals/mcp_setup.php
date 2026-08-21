<?php
$mcpT = [
    'title' => $translations['mcp_setup_title'] ?? 'MCP for AI agents',
    'subtitle' => $translations['mcp_setup_subtitle'] ?? 'Connect Cursor, Claude Desktop, VS Code or OpenCode to this AppDashboard. One command — no local build.',
    'req' => $translations['mcp_setup_req'] ?? 'Requires Node.js 18+ on your PC.',
    'copy' => $translations['mcp_setup_copy'] ?? 'Copy',
    'copied' => $translations['mcp_setup_copied'] ?? 'Copied',
    'step1' => $translations['mcp_setup_step1'] ?? '1. Install Node.js 18+ if needed',
    'step2' => $translations['mcp_setup_step2'] ?? '2. Copy the command for your agent and run it in a terminal',
    'step3' => $translations['mcp_setup_step3'] ?? '3. Restart the agent, then open the browser auth link when prompted',
    'not_ready' => $translations['mcp_setup_not_ready'] ?? 'Package is not built on this server yet. Ask an admin to redeploy main.',
    'download' => $translations['mcp_setup_download'] ?? 'Download package',
];
?>
<style>
#mcp-setup-modal{display:none;position:fixed;inset:0;z-index:10050;background:rgba(6,10,16,.32);backdrop-filter:blur(20px) saturate(150%);-webkit-backdrop-filter:blur(20px) saturate(150%);align-items:center;justify-content:center;padding:16px;}
#mcp-setup-content{width:min(720px,96vw);max-height:90vh;overflow:auto;background:var(--modal-bg);color:var(--text-color);border:1px solid var(--border-color);border-radius:var(--casa-radius,16px);box-shadow:var(--glass-shadow,0 20px 50px rgba(0,0,0,.25));padding:28px 24px 20px;position:relative;}
#close-mcp-setup{position:absolute;top:10px;right:12px;background:none;border:none;color:var(--text-color);font-size:22px;cursor:pointer;line-height:1;}
#close-mcp-setup:hover{color:#ef4444;}
.mcp-setup-lead{opacity:.78;margin:0 0 16px;line-height:1.45;font-size:14px;}
.mcp-setup-meta{display:flex;flex-wrap:wrap;gap:8px;margin:0 0 16px;}
.mcp-setup-pill{font-size:12px;padding:5px 10px;border-radius:999px;border:1px solid var(--border-color);background:var(--group-block-bg,rgba(127,127,127,.08));}
.mcp-setup-steps{margin:0 0 14px;padding-left:18px;font-size:13px;line-height:1.5;opacity:.9;}
.mcp-setup-row{margin:0 0 12px;}
.mcp-setup-row label{display:block;font-size:12px;font-weight:600;letter-spacing:.04em;text-transform:uppercase;opacity:.7;margin-bottom:6px;}
.mcp-setup-cmd{display:flex;gap:8px;align-items:stretch;}
.mcp-setup-cmd code,.mcp-setup-cmd pre{flex:1;margin:0;padding:10px 12px;border-radius:10px;border:1px solid var(--border-color);background:rgba(0,0,0,.16);font:12px/1.4 ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;white-space:pre-wrap;word-break:break-all;max-height:90px;overflow:auto;}
.mcp-setup-copy{flex:0 0 auto;border:none;border-radius:10px;padding:0 14px;cursor:pointer;background:var(--primary-color,#2563eb);color:#fff;font-weight:600;font-size:13px;}
.mcp-setup-copy:hover{filter:brightness(1.08);}
.mcp-setup-foot{display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-top:8px;font-size:13px;}
.mcp-setup-foot a{color:var(--primary-color,#2563eb);}
.mcp-setup-err{color:#ef4444;font-size:13px;margin:0 0 10px;}
</style>
<div id="mcp-setup-modal" role="dialog" aria-modal="true" aria-labelledby="mcp-setup-title">
    <div id="mcp-setup-content">
        <button type="button" id="close-mcp-setup" aria-label="Close">&times;</button>
        <h2 id="mcp-setup-title" style="margin:0 0 8px;font-size:22px;"><?php echo my_htmlspecialchars($mcpT['title']); ?></h2>
        <p class="mcp-setup-lead"><?php echo my_htmlspecialchars($mcpT['subtitle']); ?></p>
        <div class="mcp-setup-meta">
            <span class="mcp-setup-pill" id="mcp-setup-version">—</span>
            <span class="mcp-setup-pill"><?php echo my_htmlspecialchars($mcpT['req']); ?></span>
        </div>
        <p class="mcp-setup-err" id="mcp-setup-err" style="display:none;"></p>
        <ol class="mcp-setup-steps">
            <li><?php echo my_htmlspecialchars($mcpT['step1']); ?></li>
            <li><?php echo my_htmlspecialchars($mcpT['step2']); ?></li>
            <li><?php echo my_htmlspecialchars($mcpT['step3']); ?></li>
        </ol>
        <div id="mcp-setup-commands"></div>
        <div class="mcp-setup-foot">
            <a id="mcp-setup-download" href="/mcp/download" download="appdashboard-mcp.tgz"><?php echo my_htmlspecialchars($mcpT['download']); ?></a>
        </div>
    </div>
</div>
<script>
(function(){
    const modal=document.getElementById('mcp-setup-modal');
    const closeBtn=document.getElementById('close-mcp-setup');
    const openBtn=document.getElementById('mcp-setup-open-btn');
    const cmdsEl=document.getElementById('mcp-setup-commands');
    const errEl=document.getElementById('mcp-setup-err');
    const verEl=document.getElementById('mcp-setup-version');
    const copyLabel=<?php echo json_encode($mcpT['copy'], JSON_UNESCAPED_UNICODE); ?>;
    const copiedLabel=<?php echo json_encode($mcpT['copied'], JSON_UNESCAPED_UNICODE); ?>;
    const notReady=<?php echo json_encode($mcpT['not_ready'], JSON_UNESCAPED_UNICODE); ?>;
    const agentLabels={cursor:'Cursor',claude:'Claude Desktop',vscode:'VS Code / Copilot',opencode:'OpenCode'};
    function openModal(){
        if(!modal)return;
        modal.style.display='flex';
        loadInfo();
    }
    function closeModal(){ if(modal) modal.style.display='none'; }
    async function loadInfo(){
        errEl.style.display='none';
        cmdsEl.innerHTML='';
        try{
            const res=await fetch('/mcp/info',{credentials:'same-origin'});
            const data=await res.json();
            if(!data.ok) throw new Error(data.error||'failed');
            verEl.textContent=data.ready ? ('v'+data.version) : 'not built';
            if(!data.ready){
                errEl.textContent=notReady;
                errEl.style.display='block';
                return;
            }
            const order=['cursor','claude','vscode','opencode'];
            order.forEach(function(agent){
                const cmd=data.commands&&data.commands[agent];
                if(!cmd)return;
                const row=document.createElement('div');
                row.className='mcp-setup-row';
                row.innerHTML='<label></label><div class="mcp-setup-cmd"><pre></pre><button type="button" class="mcp-setup-copy"></button></div>';
                row.querySelector('label').textContent=agentLabels[agent]||agent;
                row.querySelector('pre').textContent=cmd;
                const btn=row.querySelector('button');
                btn.textContent=copyLabel;
                btn.addEventListener('click',async function(){
                    try{
                        await navigator.clipboard.writeText(cmd);
                        btn.textContent=copiedLabel;
                        setTimeout(function(){btn.textContent=copyLabel;},1600);
                    }catch(e){
                        const range=document.createRange();
                        range.selectNodeContents(row.querySelector('pre'));
                        const sel=window.getSelection();
                        sel.removeAllRanges();
                        sel.addRange(range);
                    }
                });
                cmdsEl.appendChild(row);
            });
        }catch(e){
            errEl.textContent=String(e&&e.message?e.message:e);
            errEl.style.display='block';
        }
    }
    if(openBtn) openBtn.addEventListener('click',function(){
        const sm=document.getElementById('settings-modal');
        if(sm) sm.style.display='none';
        openModal();
    });
    if(closeBtn) closeBtn.addEventListener('click',closeModal);
    if(modal) modal.addEventListener('click',function(e){ if(e.target===modal) closeModal(); });
    window.openMcpSetupModal=openModal;
})();
</script>
