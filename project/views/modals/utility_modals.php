<!-- Universal confirm/alert modal -->
<div id="fm-confirm-modal" style="display:none;position:fixed;inset:0;z-index:101000;background:rgba(0,0,0,.5);align-items:center;justify-content:center;">
  <div style="background:var(--modal-bg);border:1px solid var(--border-color);border-radius:10px;padding:24px 28px;min-width:300px;max-width:420px;width:90%;box-shadow:0 8px 40px rgba(0,0,0,.35);">
    <div id="fm-confirm-msg" style="font-size:14px;font-family:sans-serif;color:var(--text-color);margin-bottom:20px;line-height:1.5;"></div>
    <div style="display:flex;gap:10px;justify-content:flex-end;">
      <button id="fm-confirm-cancel" type="button" style="background:var(--group-block-bg);border:1px solid var(--border-color);color:var(--text-color);border-radius:6px;padding:7px 18px;font-size:13px;cursor:pointer;">Cancel</button>
      <button id="fm-confirm-ok"     type="button" style="background:#dc3545;border:none;color:#fff;border-radius:6px;padding:7px 18px;font-size:13px;cursor:pointer;font-weight:600;">OK</button>
    </div>
  </div>
</div>
<style>
@keyframes fmAlertIn{from{opacity:0;transform:translateY(14px) scale(.97)}to{opacity:1;transform:translateY(0) scale(1)}}
#fm-alert-modal.fm-alert-open{display:flex!important;align-items:center;justify-content:center;padding:max(16px,env(safe-area-inset-top)) max(16px,env(safe-area-inset-right)) max(16px,env(safe-area-inset-bottom)) max(16px,env(safe-area-inset-left));box-sizing:border-box;backdrop-filter:blur(10px);-webkit-backdrop-filter:blur(10px);background:rgba(0,0,0,.52)!important;}
#fm-alert-modal.fm-alert-open #fm-alert-card{animation:fmAlertIn .32s cubic-bezier(.22,1,.36,1) forwards;}
#fm-alert-card{max-width:min(420px,100%);width:100%;border-radius:20px;overflow:hidden;box-shadow:0 28px 80px rgba(0,0,0,.45),0 0 0 1px color-mix(in srgb,var(--border-color) 65%,transparent);background:linear-gradient(165deg,color-mix(in srgb,var(--modal-bg) 94%,var(--primary-color) 6%) 0%,var(--modal-bg) 50%);position:relative;}
#fm-alert-card-inner{padding:28px 26px 22px;text-align:center;}
#fm-alert-icon-wrap{width:56px;height:56px;margin:0 auto 16px;border-radius:50%;background:linear-gradient(145deg,color-mix(in srgb,var(--primary-color) 35%,transparent),color-mix(in srgb,var(--primary-color) 12%,transparent));display:flex;align-items:center;justify-content:center;box-shadow:0 8px 28px color-mix(in srgb,var(--primary-color) 25%,transparent);}
#fm-alert-title{font-size:18px;font-weight:700;color:var(--text-color);letter-spacing:-.02em;margin:0 0 10px;line-height:1.3;}
#fm-alert-msg{font-size:15px;line-height:1.55;color:var(--text-color);opacity:.9;word-wrap:break-word;margin:0;}
#fm-alert-actions{padding:0 22px 22px;display:flex;justify-content:stretch;}
#fm-alert-ok{flex:1;padding:14px 20px;border:none;border-radius:14px;font-size:15px;font-weight:700;cursor:pointer;color:#fff;background:linear-gradient(135deg,var(--primary-color) 0%,color-mix(in srgb,var(--primary-color) 78%,#000) 100%);box-shadow:0 6px 22px color-mix(in srgb,var(--primary-color) 42%,transparent);transition:transform .15s,box-shadow .15s;}
#fm-alert-ok:hover{transform:translateY(-1px);box-shadow:0 8px 26px color-mix(in srgb,var(--primary-color) 50%,transparent);}
</style>
<div id="fm-alert-modal" style="display:none;position:fixed;inset:0;z-index:101000;flex-direction:column;" aria-hidden="true" role="presentation">
  <div id="fm-alert-card" role="alertdialog" aria-modal="true" aria-labelledby="fm-alert-title" aria-describedby="fm-alert-msg" onclick="event.stopPropagation()">
    <div id="fm-alert-card-inner">
      <div id="fm-alert-icon-wrap" aria-hidden="true"><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="var(--primary-color)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg></div>
      <h2 id="fm-alert-title" style="font-size:18px;font-weight:700;color:var(--text-color);letter-spacing:-.02em;margin:0 0 10px;line-height:1.3;"><?php echo my_htmlspecialchars($translations['alert_modal_title'] ?? 'Notice'); ?></h2>
      <p id="fm-alert-msg" style="font-size:15px;line-height:1.55;color:var(--text-color);opacity:.9;word-wrap:break-word;margin:0;"></p>
    </div>
    <div id="fm-alert-actions">
      <button type="button" id="fm-alert-ok"><?php echo my_htmlspecialchars($translations['confirm_label'] ?? $translations['save'] ?? 'OK'); ?></button>
    </div>
  </div>
</div>
<!-- Media viewer modal -->
<div id="fm-media-modal" style="display:none;position:fixed;inset:0;z-index:99998;background:rgba(0,0,0,.92);align-items:center;justify-content:center;flex-direction:column;">
  <button id="fm-media-close" type="button" style="position:absolute;top:18px;right:24px;background:none;border:none;color:#fff;font-size:28px;cursor:pointer;z-index:2;line-height:1;">&times;</button>
  <div id="fm-media-content" style="max-width:92vw;max-height:88vh;display:flex;align-items:center;justify-content:center;"></div>
  <div id="fm-media-name" style="color:rgba(255,255,255,.55);font-size:12px;margin-top:12px;font-family:'Courier New',monospace;"></div>
</div>

<!-- ══ OTP Generator Modal ══════════════════════════════════════════════════ -->
<div id="otp-gen-modal" style="display:none;position:fixed;inset:0;z-index:20000;background:rgba(6,10,16,0.28);align-items:center;justify-content:center;backdrop-filter:blur(24px) saturate(160%);-webkit-backdrop-filter:blur(24px) saturate(160%);">
  <div id="otp-gen-content" style="background:var(--modal-bg);padding:30px;border-radius:12px;width:90%;max-width:400px;box-shadow:0 10px 30px rgba(0,0,0,.4);position:relative;text-align:center;max-height:90vh;overflow-y:auto;">
    <button onclick="closeOtpGeneratorModal()" style="position:absolute;top:10px;right:10px;background:none;border:none;color:#ff4444;font-size:22px;cursor:pointer;line-height:1;padding:4px;">&times;</button>

    <!-- Title -->
    <div style="font-size:20px;font-weight:700;margin-bottom:6px;color:var(--text-color);"><?php echo my_htmlspecialchars($translations['otp_gen_title']??'Generate One-Time PIN'); ?></div>
    <div style="font-size:13px;opacity:.6;margin-bottom:20px;line-height:1.5;"><?php echo my_htmlspecialchars($translations['otp_gen_subtitle']??'Share this code to allow one-time login into your account.'); ?></div>

    <!-- TTL selector -->
    <label style="font-size:13px;display:block;text-align:left;margin-bottom:6px;color:var(--text-color);"><?php echo my_htmlspecialchars($translations['otp_ttl_label']??'Valid for:'); ?></label>
    <select id="otp-ttl-select" style="width:100%;padding:10px;margin-bottom:16px;border:1px solid var(--border-color);border-radius:6px;background:var(--modal-bg);color:var(--text-color);font-size:14px;box-sizing:border-box;">
        <option value="60"><?php echo my_htmlspecialchars($translations['otp_ttl_1min']??'1 minute'); ?></option>
        <option value="180"><?php echo my_htmlspecialchars($translations['otp_ttl_3min']??'3 minutes'); ?></option>
        <option value="300" selected><?php echo my_htmlspecialchars($translations['otp_ttl_5min']??'5 minutes (default)'); ?></option>
        <option value="600"><?php echo my_htmlspecialchars($translations['otp_ttl_10min']??'10 minutes'); ?></option>
    </select>

    <!-- Warning -->
    <div style="font-size:12px;color:#ff9800;background:rgba(255,152,0,.1);border:1px solid rgba(255,152,0,.35);border-radius:6px;padding:9px 12px;margin-bottom:18px;line-height:1.5;">
        &#9888; <?php echo my_htmlspecialchars($translations['otp_warning_3attempts']??'Recipient has only 3 attempts to enter the code. After 3 failures, their IP is blocked for 3 minutes.'); ?>
    </div>

    <!-- Generate button -->
    <button onclick="doGenerateOtp()" id="otp-gen-btn" style="width:100%;padding:12px;background:var(--primary-color);color:#fff;border:none;border-radius:24px;font-size:15px;font-weight:600;cursor:pointer;margin-bottom:16px;transition:opacity .2s;">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" style="vertical-align:middle;margin-right:6px;"><path d="M12 1C8.676 1 6 3.676 6 7v1H4a1 1 0 0 0-1 1v13a1 1 0 0 0 1 1h16a1 1 0 0 0 1-1V9a1 1 0 0 0-1-1h-2V7c0-3.324-2.676-6-6-6zm0 2c2.276 0 4 1.724 4 4v1H8V7c0-2.276 1.724-4 4-4zm0 9a2 2 0 1 1 0 4 2 2 0 0 1 0-4z"/></svg>
        <?php echo my_htmlspecialchars($translations['otp_generate']??'Generate PIN'); ?>
    </button>

    <!-- Result box (hidden until generated) -->
    <div id="otp-gen-result" style="display:none;border:1px solid var(--border-color);border-radius:8px;padding:18px;background:var(--group-block-bg);">
        <div style="font-size:12px;opacity:.55;margin-bottom:8px;"><?php echo my_htmlspecialchars($translations['otp_your_pin']??'Your one-time PIN:'); ?></div>
        <div id="otp-gen-pin-display" style="font-size:46px;font-weight:900;letter-spacing:10px;color:var(--primary-color);margin-bottom:6px;font-family:monospace;display:flex;align-items:center;justify-content:center;gap:14px;">
            <span id="otp-gen-pin-left">---</span>
            <span id="otp-gen-pin-dot" style="opacity:.35;font-weight:700;user-select:none;">•</span>
            <span id="otp-gen-pin-right">---</span>
        </div>
        <div id="otp-gen-expiry" style="font-size:12px;opacity:.45;margin-bottom:14px;"></div>

        <div style="font-size:12px;opacity:.55;margin-bottom:6px;"><?php echo my_htmlspecialchars($translations['otp_link_hint']??'Send the recipient this link:'); ?></div>
        <div id="otp-gen-link-box" style="font-family:monospace;font-size:13px;background:var(--modal-bg);border:1px solid var(--border-color);padding:8px 10px;border-radius:6px;word-break:break-all;margin-bottom:12px;text-align:left;"></div>

        <button onclick="copyOtpLink()" style="padding:8px 20px;background:var(--primary-color);color:#fff;border:none;border-radius:20px;cursor:pointer;font-size:13px;font-weight:600;display:inline-flex;align-items:center;gap:6px;">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M16 1H4c-1.1 0-2 .9-2 2v14h2V3h12V1zm3 4H8c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h11c1.1 0 2-.9 2-2V7c0-1.1-.9-2-2-2zm0 16H8V7h11v14z"/></svg>
            <?php echo my_htmlspecialchars($translations['copy_link']??'Copy Link'); ?>
        </button>
    </div>

    <div id="otp-gen-error" style="color:#ff4444;font-size:13px;margin-top:12px;min-height:18px;"></div>
  </div>
</div>

<script>
function openOtpGeneratorModal(){
    var m=document.getElementById('otp-gen-modal');
    if(!m)return;
    m.style.display='flex';
    document.getElementById('otp-gen-result').style.display='none';
    document.getElementById('otp-gen-error').textContent='';
    document.getElementById('otp-gen-pin-left').textContent='---';
    document.getElementById('otp-gen-pin-right').textContent='---';
}
function closeOtpGeneratorModal(){
    var m=document.getElementById('otp-gen-modal');
    if(m)m.style.display='none';
}
var _otpGenModal=document.getElementById('otp-gen-modal');
if(_otpGenModal){_otpGenModal.addEventListener('click',function(e){if(e.target===this)closeOtpGeneratorModal();});}
function doGenerateOtp(){
    var ttl=document.getElementById('otp-ttl-select').value;
    var errEl=document.getElementById('otp-gen-error');
    var btn=document.getElementById('otp-gen-btn');
    errEl.textContent='';
    btn.disabled=true;
    btn.style.opacity='.6';
    var fd=new FormData();
    fd.append('generate_otp','1');
    fd.append('ajax','1');
    fd.append('ttl_seconds',ttl);
    fetch(window.location.pathname,{method:'POST',body:fd})
        .then(function(r){return r.json();})
        .then(function(data){
            btn.disabled=false;btn.style.opacity='1';
            if(data.success){
                document.getElementById('otp-gen-result').style.display='block';
                var pin=String(data.pin||'');
                var left=pin.slice(0,3);
                var right=pin.slice(3,6);
                document.getElementById('otp-gen-pin-left').textContent=left;
                document.getElementById('otp-gen-pin-right').textContent=right;
                var mins=Math.round(data.expires_in/60);
                document.getElementById('otp-gen-expiry').textContent=
                    <?php echo json_encode($translations['otp_expires_in']??'Expires in'); ?> + ' ' + mins + ' ' + <?php echo json_encode($translations['otp_minutes']??'min'); ?>;
                var link=location.origin+'/one-time-pin';
                document.getElementById('otp-gen-link-box').textContent=link;
            }else{
                errEl.textContent=data.error||<?php echo json_encode($translations['fm_network_error']??'Error'); ?>;
            }
        })
        .catch(function(){
            btn.disabled=false;btn.style.opacity='1';
            errEl.textContent=<?php echo json_encode($translations['fm_network_error']??'Network error'); ?>;
        });
}
function copyOtpLink(){
    var link=document.getElementById('otp-gen-link-box').textContent;
    if(!link)return;
    navigator.clipboard.writeText(link).then(function(){
        if(typeof showTempNotification==='function')
            showTempNotification(<?php echo json_encode($translations['fm_copied']??'Copied!'); ?>,'success');
    }).catch(function(){
        // Fallback for older browsers
        var ta=document.createElement('textarea');
        ta.value=link;ta.style.position='fixed';ta.style.opacity='0';
        document.body.appendChild(ta);ta.select();
        try{document.execCommand('copy');}catch(e){}
        document.body.removeChild(ta);
        if(typeof showTempNotification==='function')
            showTempNotification(<?php echo json_encode($translations['fm_copied']??'Copied!'); ?>,'success');
    });
}

</script>
