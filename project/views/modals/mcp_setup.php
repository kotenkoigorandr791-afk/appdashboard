<?php
$mcpT = [
    'title' => $translations['mcp_setup_title'],
    'subtitle' => $translations['mcp_setup_subtitle'],
    'req' => $translations['mcp_setup_req'],
    'copy' => $translations['mcp_setup_copy'],
    'copied' => $translations['mcp_setup_copied'],
    'step1' => $translations['mcp_setup_step1'],
    'step2' => $translations['mcp_setup_step2'],
    'step3' => $translations['mcp_setup_step3'],
    'not_ready' => $translations['mcp_setup_not_ready'],
    'not_built' => $translations['mcp_setup_not_built'],
    'info_failed' => $translations['mcp_setup_info_failed'],
    'agent_cursor' => $translations['mcp_setup_agent_cursor'],
    'agent_claude' => $translations['mcp_setup_agent_claude'],
    'agent_vscode' => $translations['mcp_setup_agent_vscode'],
    'agent_opencode' => $translations['mcp_setup_agent_opencode'],
    'download' => $translations['mcp_setup_download'],
    'token_title' => $translations['mcp_setup_token_title'],
    'token_hint' => $translations['mcp_setup_token_hint'],
    'token_name' => $translations['mcp_setup_token_name'],
    'token_generate' => $translations['mcp_setup_token_generate'],
    'token_default_name' => $translations['mcp_setup_token_default_name'],
    'token_failed' => $translations['mcp_setup_token_failed'],
    'token_copy_now' => $translations['mcp_token_copy_now'],
    'endpoint' => $translations['mcp_setup_endpoint'],
];
$mcpHttpEndpoint = ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/mcp-api/mcp';
?>
<style>
#mcp-setup-modal{display:none;position:fixed;inset:0;z-index:10050;background:rgba(6,10,16,.32);backdrop-filter:blur(20px) saturate(150%);-webkit-backdrop-filter:blur(20px) saturate(150%);align-items:center;justify-content:center;padding:16px;}
#mcp-setup-content{width:min(720px,96vw);max-height:90vh;overflow:auto;background:var(--modal-bg);color:var(--text-color);border:1px solid var(--border-color);border-radius:var(--casa-radius,16px);box-shadow:var(--glass-shadow,0 20px 50px rgba(0,0,0,.25));padding:28px 24px 20px;position:relative;}
#close-mcp-setup{position:absolute;top:10px;right:12px;background:none;border:none;color:var(--text-color);font-size:22px;cursor:pointer;line-height:1;}
#close-mcp-setup:hover{color:#ef4444;}
.mcp-setup-lead{opacity:.78;margin:0 0 16px;line-height:1.45;font-size:14px;}
.mcp-setup-meta{display:flex;flex-wrap:wrap;gap:8px;margin:0 0 16px;}
.mcp-setup-pill{font-size:12px;padding:5px 10px;border-radius:999px;border:1px solid var(--border-color);background:var(--group-block-bg,rgba(127,127,127,.08));}
.mcp-setup-pill.warn{display:inline-flex;align-items:center;gap:7px;font-weight:600;border-color:color-mix(in srgb,#f59e0b 55%,transparent);background:color-mix(in srgb,#f59e0b 15%,transparent);}
.mcp-setup-pill.warn::before{content:"";flex:0 0 auto;width:7px;height:7px;border-radius:50%;background:#f59e0b;box-shadow:0 0 6px color-mix(in srgb,#f59e0b 60%,transparent);}
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
.mcp-token-panel{margin:16px 0;padding:14px;border:1px solid var(--border-color);border-radius:10px;background:var(--group-block-bg,rgba(127,127,127,.08));}
.mcp-token-panel h3{margin:0 0 6px;font-size:15px;}
.mcp-token-panel p{margin:0 0 10px;font-size:13px;opacity:.78;}
.mcp-token-controls{display:flex;gap:8px;align-items:center;margin-bottom:10px;}
.mcp-token-controls input{flex:1;min-width:0;padding:10px 12px;border-radius:10px;border:1px solid var(--border-color);background:var(--input-bg,var(--modal-bg));color:var(--text-color);}
.mcp-token-controls button{border:none;border-radius:10px;padding:10px 14px;cursor:pointer;background:var(--primary-color,#2563eb);color:#fff;font-weight:600;}
.mcp-token-output{display:none;margin-top:10px;padding:10px;border-radius:10px;border:1px solid color-mix(in srgb,#22c55e 45%,transparent);background:color-mix(in srgb,#22c55e 16%,transparent);}
.mcp-token-output strong{display:block;margin-bottom:8px;font-size:13px;}
.mcp-token-output code{display:block;word-break:break-all;font:12px/1.4 ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;}
@media(max-width:640px){.mcp-token-controls{flex-direction:column;align-items:stretch;}}
</style>
<div id="mcp-setup-modal" role="dialog" aria-modal="true" aria-labelledby="mcp-setup-title">
    <div id="mcp-setup-content">
        <button type="button" id="close-mcp-setup" aria-label="<?php echo my_htmlspecialchars($translations['close'] ?? 'Close'); ?>">&times;</button>
        <h2 id="mcp-setup-title" style="margin:0 0 8px;font-size:22px;"><?php echo my_htmlspecialchars($mcpT['title']); ?></h2>
        <p class="mcp-setup-lead"><?php echo my_htmlspecialchars($mcpT['subtitle']); ?></p>
        <div class="mcp-setup-meta">
            <span class="mcp-setup-pill" id="mcp-setup-version">—</span>
            <span class="mcp-setup-pill"><?php echo my_htmlspecialchars($mcpT['req']); ?></span>
        </div>
        <p class="mcp-setup-err" id="mcp-setup-err" style="display:none;"></p>
        <div class="mcp-token-panel">
            <h3><?php echo my_htmlspecialchars($mcpT['token_title']); ?></h3>
            <p><?php echo my_htmlspecialchars($mcpT['token_hint']); ?></p>
            <div class="mcp-setup-row">
                <label><?php echo my_htmlspecialchars($mcpT['endpoint']); ?></label>
                <div class="mcp-setup-cmd"><pre id="mcp-http-endpoint"><?php echo my_htmlspecialchars($mcpHttpEndpoint); ?></pre><button type="button" class="mcp-setup-copy" id="mcp-copy-endpoint"><?php echo my_htmlspecialchars($mcpT['copy']); ?></button></div>
            </div>
            <div class="mcp-token-controls">
                <input type="text" id="mcp-token-name" value="<?php echo my_htmlspecialchars($mcpT['token_default_name']); ?>" maxlength="80" aria-label="<?php echo my_htmlspecialchars($mcpT['token_name']); ?>">
                <button type="button" id="mcp-generate-token"><?php echo my_htmlspecialchars($mcpT['token_generate']); ?></button>
            </div>
            <p class="mcp-setup-err" id="mcp-token-err" style="display:none;"></p>
            <div class="mcp-token-output" id="mcp-token-output">
                <strong><?php echo my_htmlspecialchars($mcpT['token_copy_now']); ?></strong>
                <code id="mcp-token-value"></code>
            </div>
        </div>
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
    const tokenBtn=document.getElementById('mcp-generate-token');
    const tokenName=document.getElementById('mcp-token-name');
    const tokenErr=document.getElementById('mcp-token-err');
    const tokenOut=document.getElementById('mcp-token-output');
    const tokenValue=document.getElementById('mcp-token-value');
    const endpointCopy=document.getElementById('mcp-copy-endpoint');
    const endpointValue=document.getElementById('mcp-http-endpoint');
    const copyLabel=<?php echo json_encode($mcpT['copy'], JSON_UNESCAPED_UNICODE); ?>;
    const copiedLabel=<?php echo json_encode($mcpT['copied'], JSON_UNESCAPED_UNICODE); ?>;
    const notReady=<?php echo json_encode($mcpT['not_ready'], JSON_UNESCAPED_UNICODE); ?>;
    const notBuilt=<?php echo json_encode($mcpT['not_built'], JSON_UNESCAPED_UNICODE); ?>;
    const loadFailed=<?php echo json_encode($mcpT['info_failed'], JSON_UNESCAPED_UNICODE); ?>;
    const tokenDefaultName=<?php echo json_encode($mcpT['token_default_name'], JSON_UNESCAPED_UNICODE); ?>;
    const tokenFailed=<?php echo json_encode($mcpT['token_failed'], JSON_UNESCAPED_UNICODE); ?>;
    const agentLabels={
        cursor:<?php echo json_encode($mcpT['agent_cursor'], JSON_UNESCAPED_UNICODE); ?>,
        claude:<?php echo json_encode($mcpT['agent_claude'], JSON_UNESCAPED_UNICODE); ?>,
        vscode:<?php echo json_encode($mcpT['agent_vscode'], JSON_UNESCAPED_UNICODE); ?>,
        opencode:<?php echo json_encode($mcpT['agent_opencode'], JSON_UNESCAPED_UNICODE); ?>
    };
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
            if(!data.ok) throw new Error(data.error||loadFailed);
            verEl.textContent=data.ready ? ('v'+data.version) : notBuilt;
            verEl.classList.toggle('warn', !data.ready);
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
    if(endpointCopy) endpointCopy.addEventListener('click',async function(){
        const text=endpointValue ? endpointValue.textContent.trim() : '';
        try{
            await navigator.clipboard.writeText(text);
            endpointCopy.textContent=copiedLabel;
            setTimeout(function(){endpointCopy.textContent=copyLabel;},1600);
        }catch(e){}
    });
    if(tokenBtn) tokenBtn.addEventListener('click',async function(){
        tokenErr.style.display='none';
        tokenOut.style.display='none';
        tokenBtn.disabled=true;
        try{
            const fd=new FormData();
            fd.append('action','generate_mcp_token');
            fd.append('generate_mcp_token','1');
            fd.append('ajax','1');
            fd.append('mcp_token_name',(tokenName&&tokenName.value.trim())||tokenDefaultName);
            const res=await fetch('/main',{method:'POST',body:fd,credentials:'same-origin'});
            const data=await res.json();
            if(!data.success||!data.token) throw new Error(data.error||tokenFailed);
            tokenValue.textContent=data.token;
            tokenOut.style.display='block';
            try{await navigator.clipboard.writeText(data.token);}catch(e){}
        }catch(e){
            tokenErr.textContent=String(e&&e.message?e.message:e);
            tokenErr.style.display='block';
        }finally{
            tokenBtn.disabled=false;
        }
    });
    window.openMcpSetupModal=openModal;
})();
</script>
