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
    'installers' => $translations['mcp_setup_installers'],
    'tokens_tab' => $translations['mcp_setup_tokens_tab'],
    'select_agent' => $translations['mcp_setup_select_agent'],
    'command' => $translations['mcp_setup_command'],
    'remove_command' => $translations['mcp_setup_remove_command'],
    'remove_hint' => $translations['mcp_setup_remove_hint'],
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
$mcpModalInitialOpen=isset($_POST['generate_mcp_token'])||isset($_POST['revoke_mcp_token'])||($_POST['action']??'')==='generate_mcp_token'||($_POST['action']??'')==='revoke_mcp_token';
$mcpInitialTab=isset($_POST['generate_mcp_token'])||isset($_POST['revoke_mcp_token'])||($_POST['action']??'')==='generate_mcp_token'||($_POST['action']??'')==='revoke_mcp_token'?'tokens':'installers';
$mcpGeneratedToken=(string)($_SESSION['mcp_generated_token']??'');
unset($_SESSION['mcp_generated_token']);
$mcpTokens=[];
try{
    $mcpUserEmail=(string)($_SESSION['user']['email']??'');
    if($mcpUserEmail!==''){
        $tokenDb=getDB();
        ensureClientAuthTokensSchema($tokenDb);
        $stmt=$tokenDb->prepare("SELECT * FROM client_auth_tokens WHERE email = :email AND revoked = 0 AND app_id = 'mcp' ORDER BY created_at DESC");
        $stmt->bindValue(':email',$mcpUserEmail,PDO::PARAM_STR);
        $result=$stmt->execute();
        while($row=$result->fetch(PDO::FETCH_ASSOC)){
            $mcpTokens[]=$row;
        }
    }
}catch(Throwable $e){}
?>
<style>
#mcp-setup-modal{display:none;position:fixed;inset:0;z-index:10050;background:rgba(6,10,16,.32);backdrop-filter:blur(20px) saturate(150%);-webkit-backdrop-filter:blur(20px) saturate(150%);align-items:center;justify-content:center;padding:16px;}
#mcp-setup-content{width:min(760px,96vw);max-height:90vh;overflow:hidden;background:var(--modal-bg);color:var(--text-color);border:1px solid var(--border-color);border-radius:var(--casa-radius,16px);box-shadow:var(--glass-shadow,0 20px 50px rgba(0,0,0,.25));position:relative;display:flex;flex-direction:column;}
.mcp-agent-menu-open #mcp-setup-content{overflow:visible;}
.mcp-setup-scroll{flex:1;overflow-y:auto;overflow-x:hidden;padding:28px 24px 20px;scrollbar-width:thin;scrollbar-color:transparent transparent;}
.mcp-setup-scroll::-webkit-scrollbar{width:6px;height:6px;}
.mcp-setup-scroll::-webkit-scrollbar-track{background:transparent;border-radius:10px;}
.mcp-setup-scroll::-webkit-scrollbar-thumb{background:rgba(255,255,255,.14);border-radius:10px;min-width:6px;min-height:6px;}
.mcp-setup-scroll:hover::-webkit-scrollbar-thumb{background:rgba(255,255,255,.2);}
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
.mcp-setup-copy{flex:0 0 auto;border:none;border-radius:10px;padding:0 14px;cursor:pointer;background:var(--primary-color,#2563eb);color:#fff;font-weight:600;font-size:13px;display:inline-flex;align-items:center;justify-content:center;gap:7px;min-width:104px;transition:transform .16s ease,filter .16s ease,background .16s ease;}
.mcp-setup-copy:hover{filter:brightness(1.08);transform:translateY(-1px);}
.mcp-setup-copy.copied{background:#16a34a;animation:mcp-copy-pop .28s ease;}
.mcp-copy-icon{width:15px;height:15px;display:inline-block;}
@keyframes mcp-copy-pop{50%{transform:scale(1.05);}}
.mcp-setup-foot{display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-top:8px;font-size:13px;}
.mcp-setup-foot a{color:var(--primary-color,#2563eb);}
.mcp-setup-err{color:#ef4444;font-size:13px;margin:0 0 10px;}
.mcp-token-panel,.mcp-installer-panel{margin:16px 0;padding:14px;border:1px solid var(--border-color);border-radius:10px;background:var(--group-block-bg,rgba(127,127,127,.08));}
.mcp-token-panel h3{margin:0 0 6px;font-size:15px;}
.mcp-token-panel p{margin:0 0 10px;font-size:13px;opacity:.78;}
.mcp-token-controls{display:flex;gap:8px;align-items:center;margin-bottom:10px;}
.mcp-token-controls input{flex:1;min-width:0;padding:10px 12px;border-radius:10px;border:1px solid var(--border-color);background:var(--input-bg,var(--modal-bg));color:var(--text-color);}
.mcp-token-controls button{border:none;border-radius:10px;padding:10px 14px;cursor:pointer;background:var(--primary-color,#2563eb);color:#fff;font-weight:600;}
.mcp-token-output{display:none;margin-top:10px;padding:10px;border-radius:10px;border:1px solid color-mix(in srgb,#22c55e 45%,transparent);background:color-mix(in srgb,#22c55e 16%,transparent);}
.mcp-token-output strong{display:block;margin-bottom:8px;font-size:13px;}
.mcp-token-output code{display:block;word-break:break-all;font:12px/1.4 ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;}
.mcp-setup-tabs{display:flex;gap:8px;margin:0 0 14px;border-bottom:1px solid var(--border-color);padding-bottom:10px;}
.mcp-setup-tab{border:1px solid var(--border-color);background:var(--group-block-bg,rgba(127,127,127,.08));color:var(--text-color);border-radius:999px;padding:8px 12px;cursor:pointer;font-size:13px;font-weight:600;}
.mcp-setup-tab.active{background:var(--primary-color,#2563eb);border-color:var(--primary-color,#2563eb);color:#fff;}
.mcp-setup-pane{display:none;}
.mcp-setup-pane.active{display:block;}
.mcp-agent-picker{position:relative;margin-bottom:12px;}
.mcp-agent-current{width:100%;height:46px;display:flex;align-items:center;justify-content:space-between;gap:10px;padding:0 12px;border:1px solid var(--border-color);border-radius:12px;background:var(--input-bg,var(--modal-bg));color:var(--text-color);cursor:pointer;font:inherit;}
.mcp-agent-current-main{display:flex;align-items:center;gap:10px;min-width:0;}
.mcp-agent-icon{width:22px;height:22px;flex:0 0 auto;color:var(--primary-color,#2563eb);}
.mcp-agent-menu{display:none;position:absolute;top:calc(100% + 6px);left:0;width:100%;z-index:999999;padding:6px;border:1px solid var(--border-color);border-radius:12px;background:var(--modal-bg,#ffffff);box-shadow:0 14px 32px rgba(0,0,0,.22);overflow:hidden;box-sizing:border-box;}
body.dark-theme .mcp-agent-menu{background:#18202b;}
.mcp-agent-picker.open .mcp-agent-menu{display:block;}
.mcp-agent-option{width:100%;height:42px;border:0;border-radius:9px;background:transparent;color:var(--text-color);display:flex;align-items:center;gap:10px;padding:0 10px;cursor:pointer;font:inherit;text-align:left;}
.mcp-agent-option:hover,.mcp-agent-option.active{background:color-mix(in srgb,var(--primary-color,#2563eb) 14%,transparent);}
.mcp-installer-output{display:none;}
.mcp-remove-hint{font-size:12px;opacity:.72;margin:-4px 0 8px;}
@media(max-width:640px){.mcp-token-controls,.mcp-setup-cmd{flex-direction:column;align-items:stretch;}.mcp-setup-copy{height:40px;}}
</style>
<div id="mcp-setup-modal" role="dialog" aria-modal="true" aria-labelledby="mcp-setup-title"<?php echo $mcpModalInitialOpen?' style="display:flex;"':''; ?> data-initial-tab="<?php echo my_htmlspecialchars($mcpInitialTab); ?>">
    <div id="mcp-setup-content">
        <div class="std-topbar">
            <h2 class="std-topbar-title"><?php echo my_htmlspecialchars($mcpT['title']); ?></h2>
            <button type="button" id="close-mcp-setup" aria-label="<?php echo my_htmlspecialchars($translations['close'] ?? 'Close'); ?>" class="std-topbar-close">&times;</button>
        </div>
        <div class="mcp-setup-scroll">
            <p class="mcp-setup-lead"><?php echo my_htmlspecialchars($mcpT['subtitle']); ?></p>
            <div class="mcp-setup-meta">
                <span class="mcp-setup-pill" id="mcp-setup-version">—</span>
                <span class="mcp-setup-pill"><?php echo my_htmlspecialchars($mcpT['req']); ?></span>
            </div>
            <p class="mcp-setup-err" id="mcp-setup-err" style="display:none;"></p>
            <div class="mcp-setup-tabs" role="tablist">
                <button type="button" class="mcp-setup-tab active" data-mcp-tab="installers"><?php echo my_htmlspecialchars($mcpT['installers']); ?></button>
                <button type="button" class="mcp-setup-tab" data-mcp-tab="tokens"><?php echo my_htmlspecialchars($mcpT['tokens_tab']); ?></button>
            </div>
            <div class="mcp-installer-panel mcp-setup-pane active" data-mcp-pane="installers">
                <ol class="mcp-setup-steps">
                    <li><?php echo my_htmlspecialchars($mcpT['step1']); ?></li>
                    <li><?php echo my_htmlspecialchars($mcpT['step2']); ?></li>
                    <li><?php echo my_htmlspecialchars($mcpT['step3']); ?></li>
                </ol>
                <div class="mcp-setup-row">
                    <label><?php echo my_htmlspecialchars($mcpT['select_agent']); ?></label>
                    <div class="mcp-agent-picker" id="mcp-agent-picker">
                        <button type="button" class="mcp-agent-current" id="mcp-agent-current" aria-haspopup="listbox" aria-expanded="false">
                            <span class="mcp-agent-current-main"><span class="mcp-agent-icon" id="mcp-agent-current-icon"></span><span id="mcp-agent-current-label"><?php echo my_htmlspecialchars($mcpT['select_agent']); ?></span></span>
                            <span>⌄</span>
                        </button>
                        <div class="mcp-agent-menu" id="mcp-agent-menu" role="listbox"></div>
                    </div>
                </div>
                <div class="mcp-setup-row mcp-installer-output" id="mcp-installer-output">
                    <label><?php echo my_htmlspecialchars($mcpT['command']); ?></label>
                    <div class="mcp-setup-cmd"><pre id="mcp-generated-command"></pre><button type="button" class="mcp-setup-copy" id="mcp-copy-command"></button></div>
                </div>
                <div class="mcp-setup-row mcp-installer-output" id="mcp-remove-output">
                    <label><?php echo my_htmlspecialchars($mcpT['remove_command']); ?></label>
                    <p class="mcp-remove-hint"><?php echo my_htmlspecialchars($mcpT['remove_hint']); ?></p>
                    <div class="mcp-setup-cmd"><pre id="mcp-remove-command"></pre><button type="button" class="mcp-setup-copy" id="mcp-copy-remove"></button></div>
                </div>
                <div class="mcp-setup-foot">
                    <a id="mcp-setup-download" href="/mcp/download" download="appdashboard-mcp.tgz"><?php echo my_htmlspecialchars($mcpT['download']); ?></a>
                </div>
            </div>
            <div class="mcp-token-panel mcp-setup-pane" data-mcp-pane="tokens">
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
                    <button type="button" class="mcp-setup-copy" id="mcp-copy-token"></button>
                </div>
                <?php if($mcpGeneratedToken!==''): ?>
                <div class="mcp-token-output" style="display:block;">
                    <strong><?php echo my_htmlspecialchars($mcpT['token_copy_now']); ?></strong>
                    <code><?php echo my_htmlspecialchars($mcpGeneratedToken); ?></code>
                    <button type="button" class="mcp-setup-copy" id="mcp-copy-generated-token"><?php echo my_htmlspecialchars($mcpT['copy']); ?></button>
                </div>
                <?php endif; ?>
                <table class="mcp-token-table">
                    <thead><tr><th><?php echo my_htmlspecialchars($translations['mcp_token_device']); ?></th><th><?php echo my_htmlspecialchars($translations['created']); ?></th><th><?php echo my_htmlspecialchars($translations['last_activity']); ?></th><th><?php echo my_htmlspecialchars($translations['expires']); ?></th><th></th></tr></thead>
                    <tbody>
                    <?php if(empty($mcpTokens)): ?>
                    <tr><td colspan="5"><?php echo my_htmlspecialchars($translations['no_tokens']); ?></td></tr>
                    <?php else: foreach($mcpTokens as $mcpToken): $mcpDeviceLabel=trim(($mcpToken['device_name']??'').' / '.($mcpToken['platform']??''),' /'); if(stripos($mcpDeviceLabel,'agora')!==false)$mcpDeviceLabel=$mcpT['token_default_name']; ?>
                    <tr>
                        <td><?php echo my_htmlspecialchars($mcpDeviceLabel); ?></td>
                        <td><?php echo my_htmlspecialchars(date('Y-m-d H:i',(int)$mcpToken['created_at'])); ?></td>
                        <td><?php echo my_htmlspecialchars(date('Y-m-d H:i',(int)$mcpToken['last_used_at'])); ?></td>
                        <td><?php echo my_htmlspecialchars(date('Y-m-d H:i',(int)$mcpToken['expires_at'])); ?></td>
                        <td><form method="post" action="/main" style="margin:0;"><?php echo apSecCsrfField(); ?><input type="hidden" name="action" value="revoke_mcp_token"><input type="hidden" name="token_hash" value="<?php echo my_htmlspecialchars($mcpToken['token_hash']??''); ?>"><button type="submit" name="revoke_mcp_token" class="mcp-token-revoke"><?php echo my_htmlspecialchars($translations['revoke']); ?></button></form></td>
                    </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<script>
(function(){
    const modal=document.getElementById('mcp-setup-modal');
    const closeBtn=document.getElementById('close-mcp-setup');
    const openBtn=document.getElementById('mcp-setup-open-btn');
    const errEl=document.getElementById('mcp-setup-err');
    const verEl=document.getElementById('mcp-setup-version');
    const agentPicker=document.getElementById('mcp-agent-picker');
    const agentCurrent=document.getElementById('mcp-agent-current');
    const agentCurrentIcon=document.getElementById('mcp-agent-current-icon');
    const agentCurrentLabel=document.getElementById('mcp-agent-current-label');
    const agentMenu=document.getElementById('mcp-agent-menu');
    const installerOut=document.getElementById('mcp-installer-output');
    const generatedCommand=document.getElementById('mcp-generated-command');
    const commandCopy=document.getElementById('mcp-copy-command');
    const removeOut=document.getElementById('mcp-remove-output');
    const removeCommand=document.getElementById('mcp-remove-command');
    const removeCopy=document.getElementById('mcp-copy-remove');
    const tokenBtn=document.getElementById('mcp-generate-token');
    const tokenName=document.getElementById('mcp-token-name');
    const tokenErr=document.getElementById('mcp-token-err');
    const tokenOut=document.getElementById('mcp-token-output');
    const tokenValue=document.getElementById('mcp-token-value');
    const endpointCopy=document.getElementById('mcp-copy-endpoint');
    const endpointValue=document.getElementById('mcp-http-endpoint');
    const tabs=modal?modal.querySelectorAll('.mcp-setup-tab'):[];
    const panes=modal?modal.querySelectorAll('.mcp-setup-pane'):[];
    const copyLabel=<?php echo json_encode($mcpT['copy'], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG); ?>;
    const copiedLabel=<?php echo json_encode($mcpT['copied'], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG); ?>;
    const notReady=<?php echo json_encode($mcpT['not_ready'], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG); ?>;
    const notBuilt=<?php echo json_encode($mcpT['not_built'], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG); ?>;
    const loadFailed=<?php echo json_encode($mcpT['info_failed'], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG); ?>;
    const tokenDefaultName=<?php echo json_encode($mcpT['token_default_name'], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG); ?>;
    const tokenFailed=<?php echo json_encode($mcpT['token_failed'], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG); ?>;
    const agentLabels={
        cursor:<?php echo json_encode($mcpT['agent_cursor'], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG); ?>,
        claude:<?php echo json_encode($mcpT['agent_claude'], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG); ?>,
        vscode:<?php echo json_encode($mcpT['agent_vscode'], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG); ?>,
        opencode:<?php echo json_encode($mcpT['agent_opencode'], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG); ?>
    };
    let mcpCommands={};
    let mcpRemoveCommands={};
    let selectedAgent='';
    function mcpAgentIcon(agent){
        const icons={
            cursor:'<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M11.503.131 1.891 5.678a.84.84 0 0 0-.42.726v11.188c0 .3.162.575.42.724l9.609 5.55a1 1 0 0 0 .998 0l9.61-5.55a.84.84 0 0 0 .42-.724V6.404a.84.84 0 0 0-.42-.726L12.497.131a1.01 1.01 0 0 0-.996 0M2.657 6.338h18.55c.263 0 .43.287.297.515L12.23 22.918c-.062.107-.229.064-.229-.06V12.335a.59.59 0 0 0-.295-.51l-9.11-5.257c-.109-.063-.064-.23.061-.23"/></svg>',
            claude:'<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="#d97706" d="m4.7144 15.9555 4.7174-2.6471.079-.2307-.079-.1275h-.2307l-.7893-.0486-2.6956-.0729-2.3375-.0971-2.2646-.1214-.5707-.1215-.5343-.7042.0546-.3522.4797-.3218.686.0608 1.5179.1032 2.2767.1578 1.6514.0972 2.4468.255h.3886l.0546-.1579-.1336-.0971-.1032-.0972L6.973 9.8356l-2.55-1.6879-1.3356-.9714-.7225-.4918-.3643-.4614-.1578-1.0078.6557-.7225.8803.0607.2246.0607.8925.686 1.9064 1.4754 2.4893 1.8336.3643.3035.1457-.1032.0182-.0728-.164-.2733-1.3539-2.4467-1.445-2.4893-.6435-1.032-.17-.6194c-.0607-.255-.1032-.4674-.1032-.7285L6.287.1335 6.6997 0l.9957.1336.419.3642.6192 1.4147 1.0018 2.2282 1.5543 3.0296.4553.8985.2429.8318.091.255h.1579v-.1457l.1275-1.706.2368-2.0947.2307-2.6957.0789-.7589.3764-.9107.7468-.4918.5828.2793.4797.686-.0668.4433-.2853 1.8517-.5586 2.9021-.3643 1.9429h.2125l.2429-.2429.9835-1.3053 1.6514-2.0643.7286-.8196.85-.9046.5464-.4311h1.0321l.759 1.1293-.34 1.1657-1.0625 1.3478-.8804 1.1414-1.2628 1.7-.7893 1.36.0729.1093.1882-.0183 2.8535-.607 1.5421-.2794 1.8396-.3157.8318.3886.091.3946-.3278.8075-1.967.4857-2.3072.4614-3.4364.8136-.0425.0304.0486.0607 1.5482.1457.6618.0364h1.621l3.0175.2247.7892.522.4736.6376-.079.4857-1.2142.6193-1.6393-.3886-3.825-.9107-1.3113-.3279h-.1822v.1093l1.0929 1.0686 2.0035 1.8092 2.5075 2.3314.1275.5768-.3218.4554-.34-.0486-2.2039-1.6575-.85-.7468-1.9246-1.621h-.1275v.17l.4432.6496 2.3436 3.5214.1214 1.0807-.17.3521-.6071.2125-.6679-.1214-1.3721-1.9246L14.38 17.959l-1.1414-1.9428-.1397.079-.674 7.2552-.3156.3703-.7286.2793-.6071-.4614-.3218-.7468.3218-1.4753.3886-1.9246.3157-1.53.2853-1.9004.17-.6314-.0121-.0425-.1397.0182-1.4328 1.9672-2.1796 2.9446-1.7243 1.8456-.4128.164-.7164-.3704.0667-.6618.4008-.5889 2.386-3.0357 1.4389-1.882.929-1.0868-.0062-.1579h-.0546l-6.3385 4.1164-1.1293.1457-.4857-.4554.0608-.7467.2307-.2429 1.9064-1.3114Z"/></svg>',
            vscode:'<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="#0078d4" d="M22.5 4.7 17.7 2.4a1.1 1.1 0 0 0-1.3.2L7.2 11 3.2 8a.7.7 0 0 0-.9 0L1 9.2a.7.7 0 0 0 0 1l3.5 3.2L1 16.6a.7.7 0 0 0 0 1l1.3 1.2a.7.7 0 0 0 .9.1l4-3 9.2 8.4a1.1 1.1 0 0 0 1.3.2l4.8-2.3c.3-.2.5-.5.5-.9V5.6c0-.4-.2-.7-.5-.9ZM17.2 17.4l-7-4.4 7-4.4v8.8Z"/></svg>',
            opencode:'<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M22 24H2V0h20zM17 4.8H7v14.4h10z"/></svg>'
        };
        return icons[agent]||'<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="10" fill="currentColor" opacity=".16"/></svg>';
    }
    function copyButtonContent(label, copied){
        const icon=copied?'<svg class="mcp-copy-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M20 6L9 17l-5-5"/></svg>':'<svg class="mcp-copy-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>';
        return icon+'<span>'+label+'</span>';
    }
    function setCopied(btn){
        btn.classList.add('copied');
        btn.innerHTML=copyButtonContent(copiedLabel,true);
        setTimeout(function(){btn.classList.remove('copied');btn.innerHTML=copyButtonContent(copyLabel,false);},1600);
    }
    function renderAgentMenu(order){
        if(!agentMenu)return;
        agentMenu.innerHTML='';
        order.forEach(function(agent){
            if(!mcpCommands[agent])return;
            const btn=document.createElement('button');
            btn.type='button';
            btn.className='mcp-agent-option';
            btn.setAttribute('role','option');
            btn.dataset.agent=agent;
            btn.innerHTML='<span class="mcp-agent-icon">'+mcpAgentIcon(agent)+'</span><span></span>';
            btn.querySelector('span:last-child').textContent=agentLabels[agent]||agent;
            btn.addEventListener('click',function(){selectAgent(agent);});
            agentMenu.appendChild(btn);
        });
    }
    function selectAgent(agent){
        selectedAgent=agent;
        if(agentCurrentLabel)agentCurrentLabel.textContent=agentLabels[agent]||agent;
        if(agentCurrentIcon)agentCurrentIcon.innerHTML=mcpAgentIcon(agent);
        if(generatedCommand)generatedCommand.textContent=mcpCommands[agent]||'';
        if(removeCommand)removeCommand.textContent=mcpRemoveCommands[agent]||'';
        if(installerOut)installerOut.style.display=mcpCommands[agent]?'block':'none';
        if(removeOut)removeOut.style.display=mcpRemoveCommands[agent]?'block':'none';
        if(agentPicker)agentPicker.classList.remove('open');
        if(agentCurrent)agentCurrent.setAttribute('aria-expanded','false');
        if(agentMenu)agentMenu.querySelectorAll('.mcp-agent-option').forEach(function(btn){btn.classList.toggle('active',btn.dataset.agent===agent);});
        showMcpTab('tokens');
        const tokenPane=modal?modal.querySelector('.mcp-token-panel.mcp-setup-pane'):null;
        if(tokenPane&&typeof tokenPane.scrollIntoView==='function')tokenPane.scrollIntoView({behavior:'smooth',block:'start'});
    }
    function showMcpTab(name){
        tabs.forEach(function(tab){tab.classList.toggle('active',tab.getAttribute('data-mcp-tab')===name);});
        panes.forEach(function(pane){pane.classList.toggle('active',pane.getAttribute('data-mcp-pane')===name);});
    }
    tabs.forEach(function(tab){tab.addEventListener('click',function(){showMcpTab(tab.getAttribute('data-mcp-tab')||'generate');});});
    showMcpTab(modal ? (modal.getAttribute('data-initial-tab')||'generate') : 'generate');
    function openModal(){
        if(!modal)return;
        if(typeof window.hideSettingsTooltips==='function') window.hideSettingsTooltips();
        modal.style.display='flex';
        loadInfo();
    }
    function closeModal(){ if(modal) modal.style.display='none'; }
    async function loadInfo(){
        errEl.style.display='none';
        mcpCommands={};
        mcpRemoveCommands={};
        selectedAgent='';
        if(installerOut)installerOut.style.display='none';
        if(removeOut)removeOut.style.display='none';
        if(agentCurrentIcon)agentCurrentIcon.innerHTML=mcpAgentIcon('');
        if(agentCurrentLabel)agentCurrentLabel.textContent=<?php echo json_encode($mcpT['select_agent'], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG); ?>;
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
            mcpCommands=data.commands||{};
            mcpRemoveCommands=data.remove_commands||{};
            renderAgentMenu(order);
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
    if(agentCurrent) agentCurrent.addEventListener('click',function(){
        if(!agentPicker)return;
        agentPicker.classList.toggle('open');
        const isOpen=agentPicker.classList.contains('open');
        if(agentMenu){
            if(isOpen){
                const r=agentPicker.getBoundingClientRect();
                agentMenu.style.display='block';
                agentMenu.style.position='fixed';
                agentMenu.style.left=r.left+'px';
                agentMenu.style.top=(r.bottom + 6)+'px';
                agentMenu.style.width=r.width+'px';
                agentMenu.style.right='auto';
                agentMenu.style.bottom='auto';
                agentMenu.style.zIndex='999999';
            }else{
                agentMenu.style.display='none';
                agentMenu.style.position='absolute';
                agentMenu.style.left='';
                agentMenu.style.top='';
                agentMenu.style.width='';
                agentMenu.style.right='';
                agentMenu.style.bottom='';
                agentMenu.style.zIndex='';
            }
        }
        agentCurrent.setAttribute('aria-expanded',isOpen?'true':'false');
    });
    document.addEventListener('click',function(e){
        if(agentPicker&&!agentPicker.contains(e.target)){
            agentPicker.classList.remove('open');
            if(agentMenu){
                agentMenu.style.display='none';
                agentMenu.style.position='absolute';
                agentMenu.style.left='';
                agentMenu.style.top='';
                agentMenu.style.width='';
                agentMenu.style.right='';
                agentMenu.style.bottom='';
                agentMenu.style.zIndex='';
            }
            if(agentCurrent)agentCurrent.setAttribute('aria-expanded','false');
        }
    });
    if(commandCopy){
        commandCopy.innerHTML=copyButtonContent(copyLabel,false);
        commandCopy.addEventListener('click',async function(){
            const text=generatedCommand ? generatedCommand.textContent.trim() : '';
            try{
                await navigator.clipboard.writeText(text);
                setCopied(commandCopy);
            }catch(e){
                if(generatedCommand){
                    const range=document.createRange();
                    range.selectNodeContents(generatedCommand);
                    const sel=window.getSelection();
                    sel.removeAllRanges();
                    sel.addRange(range);
                }
                setCopied(commandCopy);
            }
        });
    }
    if(removeCopy){
        removeCopy.innerHTML=copyButtonContent(copyLabel,false);
        removeCopy.addEventListener('click',async function(){
            const text=removeCommand ? removeCommand.textContent.trim() : '';
            try{
                await navigator.clipboard.writeText(text);
                setCopied(removeCopy);
            }catch(e){
                if(removeCommand){
                    const range=document.createRange();
                    range.selectNodeContents(removeCommand);
                    const sel=window.getSelection();
                    sel.removeAllRanges();
                    sel.addRange(range);
                }
                setCopied(removeCopy);
            }
        });
    }
    if(endpointCopy) endpointCopy.addEventListener('click',async function(){
        const text=endpointValue ? endpointValue.textContent.trim() : '';
        try{
            await navigator.clipboard.writeText(text);
            setCopied(endpointCopy);
        }catch(e){setCopied(endpointCopy);}
    });
    if(endpointCopy)endpointCopy.innerHTML=copyButtonContent(copyLabel,false);
    const copyTokenBtn=document.getElementById('mcp-copy-token');
    const copyGeneratedTokenBtn=document.getElementById('mcp-copy-generated-token');
    if(copyTokenBtn){
        copyTokenBtn.innerHTML=copyButtonContent(copyLabel,false);
        copyTokenBtn.addEventListener('click',async function(){
            const text=tokenValue?tokenValue.textContent.trim():'';
            try{await navigator.clipboard.writeText(text);}catch(e){}
            setCopied(copyTokenBtn);
        });
    }
    if(copyGeneratedTokenBtn){
        copyGeneratedTokenBtn.addEventListener('click',async function(){
            const code=copyGeneratedTokenBtn.parentElement?copyGeneratedTokenBtn.parentElement.querySelector('code'):null;
            const text=code?code.textContent.trim():'';
            try{await navigator.clipboard.writeText(text);}catch(e){}
            setCopied(copyGeneratedTokenBtn);
        });
    }
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
            fd.append('mcp_token_purpose','mcp_agent');
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
