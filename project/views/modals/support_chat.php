<!-- Messenger Modal -->
<div id="support-chat-modal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(6,10,16,0.28);z-index:100000;align-items:center;justify-content:center;backdrop-filter:blur(24px) saturate(160%);-webkit-backdrop-filter:blur(24px) saturate(160%);animation:fadeIn 0.3s ease;">
    <div id="chat-modal-container" style="background:var(--modal-bg);border-radius:var(--casa-radius);backdrop-filter:blur(var(--glass-blur)) saturate(var(--glass-sat));-webkit-backdrop-filter:blur(var(--glass-blur)) saturate(var(--glass-sat));border:1px solid var(--border-color);width:95%;max-width:1400px;height:85%;max-height:min(700px,85vh);min-height:0;display:flex;flex-direction:row;box-shadow:0 20px 60px rgba(0,0,0,0.4);overflow:hidden;position:relative;animation:slideUp 0.4s cubic-bezier(0.4, 0, 0.2, 1);">
        <!-- Animated background -->
        <div style="position:absolute;top:0;left:0;width:100%;height:100%;pointer-events:none;z-index:0;">
            <div class="chat-bg-animation"></div>
        </div>

        <!-- Chat list sidebar -->
        <div id="admin-chat-list" style="display:none;width:320px;min-height:0;flex-shrink:0;border-right:1px solid var(--border-color);flex-direction:column;position:relative;z-index:1;background:rgba(0,0,0,0.02);">
            <div style="padding:24px;background:linear-gradient(135deg, var(--chat-color-light) 0%, var(--chat-color-medium) 100%);border-bottom:1px solid var(--border-color);display:flex;justify-content:space-between;align-items:center;gap:12px;">
                <h2 style="margin:0;font-size:20px;font-weight:600;color:var(--text-color);flex:1;min-width:0;">Messenger</h2>
                <button type="button" id="admin-chat-list-close" title="<?php echo my_htmlspecialchars($translations['close'] ?? 'Close'); ?>" aria-label="<?php echo my_htmlspecialchars($translations['close'] ?? 'Close'); ?>" class="std-topbar-close">&times;</button>
            </div>
            <div style="flex:1;min-height:0;overflow-y:auto;padding:16px;">
                <button id="new-chat-btn" style="width:100%;padding:14px;background:transparent;color:<?php echo $config['color']; ?>;border:1px solid <?php echo $config['color']; ?>;border-radius:12px;cursor:pointer;font-weight:600;margin-bottom:16px;transition:all 0.2s;" onmouseover="this.style.transform='translateY(-2px)';this.style.background='<?php echo $config['color']; ?>11';this.style.boxShadow='0 6px 20px <?php echo $config['color']; ?>33';" onmouseout="this.style.transform='translateY(0)';this.style.background='transparent';this.style.boxShadow='none';">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle;margin-right:8px;"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    <?php echo my_htmlspecialchars($translations['support_chat_new_title'] ?? 'New chat'); ?>
                </button>
                <div id="chat-list-container"></div>
            </div>
        </div>

        <!-- Chat messages area -->
        <div id="chat-messages-container" style="flex:1;min-height:0;overflow:hidden;display:flex;flex-direction:column;position:relative;z-index:1;">
            <!-- Header with gradient -->
            <div style="padding:24px;background:linear-gradient(135deg, var(--chat-color-light) 0%, var(--chat-color-medium) 100%);border-bottom:1px solid var(--border-color);display:flex;justify-content:space-between;align-items:center;gap:10px;">
                <div style="display:flex;align-items:center;gap:10px;flex:1;min-width:0;">
                    <button type="button" id="chat-admin-mobile-back" style="flex-shrink:0;width:40px;height:40px;border:none;border-radius:12px;background:rgba(255,255,255,0.12);color:var(--text-color);cursor:pointer;align-items:center;justify-content:center;padding:0;" title="<?php echo my_htmlspecialchars($translations['support_chat_back_list'] ?? 'Chats'); ?>" aria-label="<?php echo my_htmlspecialchars($translations['support_chat_back_list'] ?? 'Back to chats'); ?>"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 18l-6-6 6-6"/></svg></button>
                    <img id="chat-header-avatar" src="default_avatar.png" alt="" style="width:40px;height:40px;border-radius:50%;object-fit:cover;flex-shrink:0;display:none;">
                    <h2 id="chat-modal-title" style="margin:0;font-size:22px;font-weight:600;color:var(--text-color);background:linear-gradient(135deg, <?php echo $config['color']; ?> 0%, <?php echo $config['color']; ?>cc 100%);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?php echo my_htmlspecialchars($translations['support_chat_title'] ?? 'Chat with Support'); ?></h2>
                </div>
                <div style="display:flex;gap:8px;align-items:center;">
                    <button id="delete-chat-btn" style="display:none;align-items:center;justify-content:center;gap:6px;white-space:nowrap;line-height:1;min-width:100px;background:rgba(255,59,48,0.15);border:1px solid rgba(255,59,48,0.3);font-size:14px;cursor:pointer;color:#ff3b30;padding:8px 16px;border-radius:10px;transition:all 0.2s;font-weight:500;" onmouseover="this.style.background='rgba(255,59,48,0.25)';" onmouseout="this.style.background='rgba(255,59,48,0.15)';">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex:0 0 16px;"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                        Delete
                    </button>
                    <button id="block-chat-btn" style="display:none;background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.25);font-size:14px;cursor:pointer;color:#ef4444;padding:8px 16px;border-radius:10px;transition:all 0.2s;font-weight:500;">Block</button>
                    <button id="close-chat-modal" class="std-topbar-close">&times;</button>
                </div>
            </div>

            <div id="chat-messages-area" style="display:flex;flex-direction:column;flex:1;min-height:0;overflow:hidden;">
                <div id="chat-messages" class="chat-messages-scroll" tabindex="-1" style="flex:1;overflow-y:auto;overflow-x:hidden;padding:24px;display:flex;flex-direction:column;gap:16px;min-height:0;-webkit-overflow-scrolling:touch;outline:none;"></div>

                <div id="chat-composer-row" style="padding:20px;background:rgba(255,255,255,0.02);border-top:1px solid var(--border-color);display:flex;gap:12px;align-items:center;">
                    <input type="file" id="chat-file-input" accept="image/*,video/*,audio/*,text/*,.txt,.md,.json,.xml,.csv,.log,.html,.css,.js,.py,.java,.c,.cpp,.php,.rb,.go,.rs,.sql,.yaml,.yml,.sh,.bat,.ini,.conf,.config" style="display:none;">
                    <button type="button" id="chat-cancel-edit-btn" style="display:none;flex-shrink:0;padding:10px 14px;border-radius:12px;border:1px solid var(--border-color);background:var(--group-block-bg);color:var(--text-color);cursor:pointer;font-size:13px;font-weight:600;"><?php echo my_htmlspecialchars($translations['cancel'] ?? 'Cancel'); ?></button>
                    <button type="button" id="chat-upload-btn" title="<?php echo my_htmlspecialchars($translations['support_chat_upload'] ?? 'Upload file'); ?>" style="flex-shrink:0;background:linear-gradient(135deg, var(--chat-color-light) 0%, var(--chat-color-medium) 100%);color:var(--text-color);border:1px solid var(--chat-color-border);padding:10px;border-radius:14px;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:transform 0.2s,box-shadow 0.2s;" onmouseover="this.style.transform='scale(1.05)';" onmouseout="this.style.transform='scale(1)';" aria-label="<?php echo my_htmlspecialchars($translations['support_chat_upload'] ?? 'Upload file'); ?>"><svg version="1.0" xmlns="http://www.w3.org/2000/svg" width="22" height="20" viewBox="0 0 1280 1188" preserveAspectRatio="xMidYMid meet" aria-hidden="true"><g transform="translate(0,1188) scale(0.1,-0.1)" fill="currentColor" stroke="none"><path d="M7835 11870 c-555 -59 -1064 -296 -1524 -710 -160 -144 -371 -399 -492 -595 -61 -98 -2515 -4298 -2621 -4485 -466 -822 -641 -1663 -477 -2295 67 -259 222 -503 409 -644 367 -277 835 -291 1345 -42 249 122 483 292 724 526 249 242 463 509 654 815 141 227 1322 2258 1340 2305 25 65 33 196 17 266 -49 211 -237 359 -455 359 -179 0 -320 -86 -421 -256 -22 -38 -301 -514 -619 -1059 -318 -544 -602 -1030 -633 -1080 -283 -463 -627 -821 -973 -1014 -93 -52 -216 -92 -299 -98 -71 -5 -77 -4 -107 21 -41 34 -68 95 -89 197 -25 122 -15 389 20 559 65 316 185 633 354 935 131 234 2581 4424 2640 4515 296 458 770 777 1250 841 134 18 345 6 467 -25 125 -33 279 -107 383 -186 503 -379 624 -1191 283 -1895 -61 -127 -3739 -6430 -3822 -6550 -464 -673 -1395 -921 -2144 -570 -114 53 -767 425 -900 513 -206 136 -439 401 -554 631 -238 475 -240 1035 -6 1502 34 66 813 1402 2808 4814 341 583 636 1094 656 1135 31 65 36 86 39 167 5 107 -6 160 -52 248 -77 148 -240 245 -411 245 -129 -1 -230 -44 -325 -140 -57 -57 -103 -131 -391 -625 -180 -308 -942 -1613 -1694 -2900 -752 -1287 -1390 -2380 -1418 -2430 -150 -265 -258 -596 -303 -925 -24 -170 -24 -490 0 -660 56 -412 198 -791 420 -1124 111 -165 187 -258 330 -401 220 -220 326 -294 815 -575 620 -356 719 -402 1035 -485 258 -68 358 -79 676 -79 308 0 403 10 650 70 356 88 754 285 1036 514 192 157 394 378 531 585 60 90 3729 6364 3816 6525 146 271 261 622 314 960 22 145 25 576 5 715 -65 432 -208 788 -440 1095 -72 95 -236 265 -327 340 -275 225 -609 374 -975 435 -112 19 -430 27 -545 15z"/></g></svg></button>
                    <button type="button" id="chat-voice-btn" title="<?php echo my_htmlspecialchars($translations['support_chat_voice'] ?? 'Voice message'); ?>" style="flex-shrink:0;background:linear-gradient(135deg, var(--chat-color-light) 0%, var(--chat-color-medium) 100%);color:var(--text-color);border:1px solid var(--chat-color-border);padding:10px;border-radius:14px;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:transform 0.2s,box-shadow 0.2s;" onmouseover="this.style.transform='scale(1.05)';" onmouseout="this.style.transform='scale(1)';" aria-label="<?php echo my_htmlspecialchars($translations['support_chat_voice'] ?? 'Voice message'); ?>"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/><line x1="12" y1="19" x2="12" y2="23"/><line x1="8" y1="23" x2="16" y2="23"/></svg></button>
                    <input type="text" id="chat-input" placeholder="<?php echo my_htmlspecialchars($translations['support_chat_placeholder'] ?? 'Type your message...'); ?>" style="flex:1;min-width:0;padding:14px 18px;border:1px solid var(--border-color);border-radius:20px;background:var(--modal-bg);color:var(--text-color);font-size:15px;transition:all 0.2s;" onfocus="this.style.borderColor='<?php echo $config['color']; ?>';this.style.boxShadow='0 0 0 3px <?php echo $config['color']; ?>22';" onblur="this.style.borderColor='var(--border-color)';this.style.boxShadow='none';">
                    <button type="button" id="chat-send-btn" title="<?php echo my_htmlspecialchars($translations['support_chat_send'] ?? 'Send'); ?>" style="flex-shrink:0;width:48px;height:48px;padding:0;border-radius:50%;border:none;background:linear-gradient(135deg, <?php echo $config['color']; ?> 0%, <?php echo $config['color']; ?>dd 100%);color:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:transform 0.2s,box-shadow 0.2s;box-shadow:0 4px 12px <?php echo $config['color']; ?>66;" onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 6px 20px <?php echo $config['color']; ?>88';" onmouseout="this.style.transform='translateY(0)';this.style.boxShadow='0 4px 12px <?php echo $config['color']; ?>66';" aria-label="<?php echo my_htmlspecialchars($translations['support_chat_send'] ?? 'Send'); ?>"><svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3.714 3.048a.498.498 0 0 0-.683.627l2.843 7.627a2 2 0 0 1 0 1.396l-2.842 7.627a.498.498 0 0 0 .682.627l18-8.5a.5.5 0 0 0 0-.904z"/><path d="M6 12h16"/></svg></button>
                </div>
            </div>
        </div>
    </div>
</div>
<div id="chat-media-editor-modal" style="display:none;position:fixed;inset:0;z-index:100110;background:rgba(0,0,0,.94);flex-direction:column;box-sizing:border-box;padding:max(8px,env(safe-area-inset-top)) max(8px,env(safe-area-inset-right)) max(8px,env(safe-area-inset-bottom)) max(8px,env(safe-area-inset-left));" aria-modal="true" role="dialog">
    <div style="flex-shrink:0;display:flex;flex-wrap:wrap;gap:8px;align-items:center;justify-content:center;padding:8px 4px;border-bottom:1px solid rgba(255,255,255,.12);">
        <button type="button" id="chat-media-tool-crop" class="chat-media-tool-btn" style="padding:10px 14px;border-radius:12px;border:1px solid rgba(255,255,255,.2);background:rgba(255,255,255,.08);color:#fff;font-size:14px;cursor:pointer;"><?php echo my_htmlspecialchars($translations['support_chat_editor_crop'] ?? 'Crop'); ?></button>
        <button type="button" id="chat-media-tool-arrow" class="chat-media-tool-btn" style="padding:10px 14px;border-radius:12px;border:1px solid rgba(255,255,255,.2);background:rgba(255,255,255,.08);color:#fff;font-size:14px;cursor:pointer;"><?php echo my_htmlspecialchars($translations['support_chat_editor_arrow'] ?? 'Arrow'); ?></button>
        <button type="button" id="chat-media-tool-draw" class="chat-media-tool-btn" style="padding:10px 14px;border-radius:12px;border:1px solid rgba(255,255,255,.2);background:rgba(255,255,255,.08);color:#fff;font-size:14px;cursor:pointer;"><?php echo my_htmlspecialchars($translations['support_chat_editor_draw'] ?? 'Draw'); ?></button>
        <button type="button" id="chat-media-tool-clear" style="padding:10px 14px;border-radius:12px;border:1px solid rgba(255,255,255,.2);background:rgba(255,255,255,.06);color:#fca5a5;font-size:14px;cursor:pointer;"><?php echo my_htmlspecialchars($translations['support_chat_editor_clear'] ?? 'Clear marks'); ?></button>
        <button type="button" id="chat-media-apply-crop" style="display:none;padding:10px 14px;border-radius:12px;border:1px solid #4ade80;background:rgba(34,197,94,.25);color:#bbf7d0;font-size:14px;cursor:pointer;"><?php echo my_htmlspecialchars($translations['support_chat_editor_apply_crop'] ?? 'Apply crop'); ?></button>
    </div>
    <div id="chat-media-editor-scroll" style="flex:1;min-height:0;overflow:auto;display:flex;align-items:center;justify-content:center;-webkit-overflow-scrolling:touch;">
        <div id="chat-media-editor-stage" style="position:relative;touch-action:none;margin:auto;">
            <canvas id="chat-media-editor-base" style="display:block;max-width:92vw;vertical-align:top;"></canvas>
            <canvas id="chat-media-editor-layer" style="position:absolute;left:0;top:0;display:block;max-width:92vw;pointer-events:auto;"></canvas>
        </div>
    </div>
    <div style="flex-shrink:0;display:flex;gap:12px;justify-content:stretch;padding:12px 8px;border-top:1px solid rgba(255,255,255,.12);">
        <button type="button" id="chat-media-editor-cancel" style="flex:1;padding:14px;border-radius:14px;border:1px solid rgba(255,255,255,.25);background:rgba(255,255,255,.08);color:#fff;font-size:16px;font-weight:600;cursor:pointer;"><?php echo my_htmlspecialchars($translations['cancel'] ?? 'Cancel'); ?></button>
        <button type="button" id="chat-media-editor-send" style="flex:1;padding:14px;border-radius:14px;border:none;background:linear-gradient(135deg,<?php echo $config['color']; ?> 0%,<?php echo $config['color']; ?>cc 100%);color:#fff;font-size:16px;font-weight:700;cursor:pointer;"><?php echo my_htmlspecialchars($translations['support_chat_editor_send'] ?? 'Send'); ?></button>
    </div>
</div>
<div id="support-chat-lightbox" style="display:none;position:fixed;inset:0;z-index:100110;background:rgba(0,0,0,.94);align-items:center;justify-content:center;flex-direction:column;box-sizing:border-box;padding:max(12px,env(safe-area-inset-top)) max(12px,env(safe-area-inset-right)) max(12px,env(safe-area-inset-bottom)) max(12px,env(safe-area-inset-left));" aria-modal="true" role="dialog">
    <button type="button" id="support-chat-lightbox-close" style="position:absolute;top:max(10px,env(safe-area-inset-top));right:max(14px,env(safe-area-inset-right));width:44px;height:44px;border:none;border-radius:50%;background:rgba(255,255,255,.14);color:#fff;font-size:22px;line-height:1;cursor:pointer;z-index:2;display:flex;align-items:center;justify-content:center;">×</button>
    <div id="support-chat-lightbox-inner" style="max-width:100%;max-height:88vh;width:100%;display:flex;align-items:center;justify-content:center;overflow:auto;touch-action:manipulation;-webkit-overflow-scrolling:touch;"></div>
</div>
<div id="support-chat-delete-modal" style="display:none;position:fixed;inset:0;z-index:100108;align-items:center;justify-content:center;padding:max(16px,env(safe-area-inset-top)) max(16px,env(safe-area-inset-right)) max(16px,env(safe-area-inset-bottom)) max(16px,env(safe-area-inset-left));box-sizing:border-box;background:rgba(0,0,0,.58);backdrop-filter:blur(10px);-webkit-backdrop-filter:blur(10px);" role="alertdialog" aria-modal="true" aria-labelledby="support-chat-delete-heading" tabindex="-1">
    <div id="support-chat-delete-card" style="width:100%;max-width:420px;border-radius:22px;overflow:hidden;box-shadow:0 28px 80px rgba(0,0,0,.5),0 0 0 1px color-mix(in srgb,var(--border-color) 80%,transparent);background:linear-gradient(165deg,color-mix(in srgb,var(--modal-bg) 96%,#6366f1 4%) 0%,var(--modal-bg) 55%);animation:supportDelModalIn .32s ease;">
        <div style="padding:28px 26px 20px;text-align:center;border-bottom:1px solid color-mix(in srgb,var(--border-color) 70%,transparent);">
            <div style="width:64px;height:64px;margin:0 auto 18px;border-radius:50%;background:linear-gradient(145deg,rgba(239,68,68,.25),rgba(220,38,38,.12));display:flex;align-items:center;justify-content:center;box-shadow:0 8px 28px rgba(239,68,68,.25);">
                <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="#f87171" stroke-width="1.85" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
            </div>
            <h2 id="support-chat-delete-heading" style="margin:0 0 10px;font-size:20px;font-weight:700;color:var(--text-color);letter-spacing:-.02em;"><?php echo my_htmlspecialchars($translations['support_chat_delete_modal_title'] ?? 'Delete this chat?'); ?></h2>
            <div id="support-chat-delete-email" style="font-size:14px;font-weight:600;color:<?php echo my_htmlspecialchars($config['color']); ?>;word-break:break-all;line-height:1.45;margin-bottom:12px;"></div>
            <p style="margin:0;font-size:14px;line-height:1.55;color:var(--text-color);opacity:.82;"><?php echo my_htmlspecialchars($translations['support_chat_delete_modal_body'] ?? 'All messages in this conversation will be permanently removed.'); ?></p>
        </div>
        <div style="display:flex;gap:12px;padding:18px 20px 20px;justify-content:stretch;background:color-mix(in srgb,var(--modal-bg) 88%,transparent);">
            <button type="button" id="support-chat-delete-cancel" style="flex:1;padding:14px 16px;border-radius:14px;border:1px solid var(--border-color);background:var(--group-block-bg);color:var(--text-color);font-size:15px;font-weight:600;cursor:pointer;"><?php echo my_htmlspecialchars($translations['cancel'] ?? 'Cancel'); ?></button>
            <button type="button" id="support-chat-delete-confirm" style="flex:1;padding:14px 16px;border-radius:14px;border:none;background:linear-gradient(135deg,#ef4444 0%,#b91c1c 100%);color:#fff;font-size:15px;font-weight:700;cursor:pointer;box-shadow:0 6px 22px rgba(239,68,68,.4);"><?php echo my_htmlspecialchars($translations['support_chat_delete_btn'] ?? 'Delete permanently'); ?></button>
        </div>
    </div>
</div>
<?php if ($isAdmin): ?>
<div id="chat-type-modal" style="display:none;position:fixed;inset:0;z-index:100002;background:rgba(6,10,16,0.28);align-items:center;justify-content:center;backdrop-filter:blur(24px) saturate(160%);-webkit-backdrop-filter:blur(24px) saturate(160%);">
    <div style="background:var(--modal-bg);border:1px solid var(--border-color);border-radius:16px;width:92%;max-width:420px;display:flex;flex-direction:column;box-shadow:0 20px 50px rgba(0,0,0,0.35);overflow:hidden;">
        <div style="padding:18px 20px;border-bottom:1px solid var(--border-color);font-weight:600;font-size:17px;color:var(--text-color);">New chat</div>
        <div style="padding:16px;display:flex;flex-direction:column;gap:10px;">
            <button type="button" id="chat-type-support" style="display:flex;align-items:center;gap:12px;width:100%;padding:14px;border:1px solid var(--border-color);border-radius:12px;background:var(--group-block-bg);color:var(--text-color);cursor:pointer;text-align:left;font-size:15px;font-weight:600;">Support</button>
            <button type="button" id="chat-type-peer" style="display:flex;align-items:center;gap:12px;width:100%;padding:14px;border:1px solid var(--border-color);border-radius:12px;background:var(--group-block-bg);color:var(--text-color);cursor:pointer;text-align:left;font-size:15px;font-weight:600;">User chat</button>
        </div>
        <div style="padding:14px 16px 16px;display:flex;justify-content:flex-end;gap:10px;border-top:1px solid var(--border-color);">
            <button type="button" id="chat-type-cancel" style="padding:10px 18px;border-radius:10px;border:1px solid var(--border-color);background:var(--group-block-bg);color:var(--text-color);cursor:pointer;font-size:14px;"><?php echo my_htmlspecialchars($translations['cancel'] ?? 'Cancel'); ?></button>
        </div>
    </div>
</div>
<?php endif; ?>
<div id="chat-user-search-modal" style="display:none;position:fixed;inset:0;z-index:100002;background:rgba(6,10,16,0.28);align-items:center;justify-content:center;backdrop-filter:blur(24px) saturate(160%);-webkit-backdrop-filter:blur(24px) saturate(160%);">
    <div style="background:var(--modal-bg);border:1px solid var(--border-color);border-radius:16px;width:92%;max-width:440px;max-height:85vh;display:flex;flex-direction:column;box-shadow:0 20px 50px rgba(0,0,0,0.35);overflow:hidden;">
        <div style="padding:18px 20px;border-bottom:1px solid var(--border-color);font-weight:600;font-size:17px;color:var(--text-color);"><?php echo my_htmlspecialchars($translations['support_chat_new_title'] ?? 'New chat'); ?></div>
        <div style="padding:14px 16px 0;">
            <input type="email" id="chat-user-search-input" autocomplete="off" placeholder="Full email address" style="width:100%;box-sizing:border-box;padding:12px 14px;border:1px solid var(--border-color);border-radius:12px;background:var(--input-bg);color:var(--text-color);font-size:15px;">
        </div>
        <div id="chat-user-search-results" style="flex:1;min-height:120px;max-height:280px;overflow-y:auto;margin:12px 16px;padding:4px;border:1px solid var(--border-color);border-radius:12px;background:rgba(0,0,0,0.03);"></div>
        <div style="padding:14px 16px 16px;display:flex;justify-content:flex-end;gap:10px;border-top:1px solid var(--border-color);">
            <button type="button" id="chat-user-search-cancel" style="padding:10px 18px;border-radius:10px;border:1px solid var(--border-color);background:var(--group-block-bg);color:var(--text-color);cursor:pointer;font-size:14px;"><?php echo my_htmlspecialchars($translations['cancel'] ?? 'Cancel'); ?></button>
        </div>
    </div>
</div>

<!-- Monaco Editor Modal for Text Files -->
<div id="chat-text-editor-modal" style="display:none;position:fixed;inset:0;z-index:100111;background:var(--modal-bg);flex-direction:column;" aria-modal="true" role="dialog">
    <div style="flex-shrink:0;display:flex;align-items:center;justify-content:space-between;padding:16px 20px;border-bottom:1px solid var(--border-color);background:var(--group-block-bg);">
        <div style="display:flex;align-items:center;gap:12px;flex:1;min-width:0;">
            <h3 id="chat-text-editor-title" style="margin:0;font-size:16px;font-weight:600;color:var(--text-color);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">File Viewer</h3>
            <span id="chat-text-editor-readonly-badge" style="display:none;padding:4px 10px;background:rgba(59,130,246,0.15);color:#3b82f6;border-radius:6px;font-size:12px;font-weight:600;">Read-only</span>
        </div>
        <div style="display:flex;gap:8px;">
            <button type="button" id="chat-text-editor-save" style="display:none;padding:8px 16px;border-radius:50px;border:none;background:linear-gradient(135deg,<?php echo $config['color']; ?> 0%,<?php echo $config['color']; ?>dd 100%);color:#fff;cursor:pointer;font-size:13px;font-weight:700;">Save Changes</button>
            <button type="button" id="chat-text-editor-close" style="padding:8px;border-radius:10px;border:1px solid var(--border-color);background:var(--group-block-bg);color:var(--text-color);cursor:pointer;font-size:20px;line-height:1;width:36px;height:36px;display:flex;align-items:center;justify-content:center;">×</button>
        </div>
    </div>
    <div id="chat-text-editor-container" style="flex:1;min-height:0;"></div>
</div>

<!-- Voice Recording Modal -->
<div id="chat-voice-modal" style="display:none;position:fixed;inset:0;z-index:100111;background:rgba(6,10,16,0.28);align-items:center;justify-content:center;backdrop-filter:blur(24px) saturate(160%);-webkit-backdrop-filter:blur(24px) saturate(160%);" aria-modal="true" role="dialog">
    <div style="background:var(--modal-bg);border:1px solid var(--border-color);border-radius:20px;width:92%;max-width:400px;padding:32px 24px;box-shadow:0 20px 60px rgba(0,0,0,0.5);text-align:center;">
        <div style="margin-bottom:24px;">
            <div id="chat-voice-icon" style="width:80px;height:80px;margin:0 auto 16px;border-radius:50%;background:linear-gradient(135deg,<?php echo $config['color']; ?>22 0%,<?php echo $config['color']; ?>11 100%);display:flex;align-items:center;justify-content:center;position:relative;">
                <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="<?php echo $config['color']; ?>" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/><line x1="12" y1="19" x2="12" y2="23"/><line x1="8" y1="23" x2="16" y2="23"/></svg>
                <div id="chat-voice-pulse" style="display:none;position:absolute;inset:0;border-radius:50%;background:<?php echo $config['color']; ?>33;animation:voicePulse 1.5s ease-out infinite;"></div>
            </div>
            <h3 style="margin:0 0 8px;font-size:18px;font-weight:600;color:var(--text-color);">Voice Message</h3>
            <p id="chat-voice-status" style="margin:0;font-size:14px;color:var(--text-color);opacity:0.7;">Click to start recording</p>
            <div id="chat-voice-timer" style="display:none;margin-top:12px;font-size:24px;font-weight:700;color:<?php echo $config['color']; ?>;font-variant-numeric:tabular-nums;">0:00</div>
        </div>
        <canvas id="chat-voice-waveform" width="320" height="80" style="width:100%;max-width:320px;height:80px;margin:0 auto 24px;display:none;border-radius:12px;background:rgba(0,0,0,0.1);"></canvas>
        <div style="display:flex;gap:12px;justify-content:center;">
            <button type="button" id="chat-voice-cancel" style="flex:1;padding:14px;border-radius:14px;border:1px solid var(--border-color);background:var(--group-block-bg);color:var(--text-color);font-size:15px;font-weight:600;cursor:pointer;">Cancel</button>
            <button type="button" id="chat-voice-record" style="flex:1;padding:14px;border-radius:14px;border:none;background:linear-gradient(135deg,#ef4444 0%,#dc2626 100%);color:#fff;font-size:15px;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;">
                <span id="chat-voice-record-icon">●</span>
                <span id="chat-voice-record-text">Record</span>
            </button>
            <button type="button" id="chat-voice-send" style="display:none;flex:1;padding:14px;border-radius:14px;border:none;background:linear-gradient(135deg,<?php echo $config['color']; ?> 0%,<?php echo $config['color']; ?>dd 100%);color:#fff;font-size:15px;font-weight:700;cursor:pointer;">Send</button>
        </div>
    </div>
</div>

<!-- Text Formatting Context Menu -->
<div id="chat-format-menu" style="display:none;position:fixed;background:var(--modal-bg);border:1px solid var(--border-color);border-radius:12px;box-shadow:0 8px 24px rgba(0,0,0,0.3);padding:6px;z-index:100112;min-width:180px;">
    <div class="chat-format-option" data-format="bold" style="padding:10px 14px;cursor:pointer;border-radius:8px;color:var(--text-color);font-size:14px;display:flex;align-items:center;gap:10px;transition:background 0.2s;" onmouseover="this.style.background='var(--hover-bg)';" onmouseout="this.style.background='transparent';">
        <span style="font-weight:700;width:20px;">B</span>
        <span>Bold</span>
    </div>
    <div class="chat-format-option" data-format="italic" style="padding:10px 14px;cursor:pointer;border-radius:8px;color:var(--text-color);font-size:14px;display:flex;align-items:center;gap:10px;transition:background 0.2s;" onmouseover="this.style.background='var(--hover-bg)';" onmouseout="this.style.background='transparent';">
        <span style="font-style:italic;width:20px;">I</span>
        <span>Italic</span>
    </div>
    <div class="chat-format-option" data-format="strikethrough" style="padding:10px 14px;cursor:pointer;border-radius:8px;color:var(--text-color);font-size:14px;display:flex;align-items:center;gap:10px;transition:background 0.2s;" onmouseover="this.style.background='var(--hover-bg)';" onmouseout="this.style.background='transparent';">
        <span style="text-decoration:line-through;width:20px;">S</span>
        <span>Strikethrough</span>
    </div>
    <div class="chat-format-option" data-format="code" style="padding:10px 14px;cursor:pointer;border-radius:8px;color:var(--text-color);font-size:14px;display:flex;align-items:center;gap:10px;transition:background 0.2s;" onmouseover="this.style.background='var(--hover-bg)';" onmouseout="this.style.background='transparent';">
        <span style="font-family:monospace;width:20px;">&lt;/&gt;</span>
        <span>Code</span>
    </div>
    <div class="chat-format-option" data-format="quote" style="padding:10px 14px;cursor:pointer;border-radius:8px;color:var(--text-color);font-size:14px;display:flex;align-items:center;gap:10px;transition:background 0.2s;" onmouseover="this.style.background='var(--hover-bg)';" onmouseout="this.style.background='transparent';">
        <span style="width:20px;">"</span>
        <span>Quote</span>
    </div>
    <div class="chat-format-option" data-format="monospace" style="padding:10px 14px;cursor:pointer;border-radius:8px;color:var(--text-color);font-size:14px;display:flex;align-items:center;gap:10px;transition:background 0.2s;" onmouseover="this.style.background='var(--hover-bg)';" onmouseout="this.style.background='transparent';">
        <span style="font-family:monospace;width:20px;">M</span>
        <span>Monospace</span>
    </div>
</div>

<script>
// Set CSS variables for chat colors
document.documentElement.style.setProperty('--chat-color', '<?php echo $config['color']; ?>');
document.documentElement.style.setProperty('--chat-color-light', '<?php echo $config['color']; ?>11');
document.documentElement.style.setProperty('--chat-color-medium', '<?php echo $config['color']; ?>22');
document.documentElement.style.setProperty('--chat-color-lighter', '<?php echo $config['color']; ?>08');
document.documentElement.style.setProperty('--chat-color-shadow', '<?php echo $config['color']; ?>66');
document.documentElement.style.setProperty('--chat-color-glow', '<?php echo $config['color']; ?>99');
document.documentElement.style.setProperty('--chat-color-ring', '<?php echo $config['color']; ?>33');
document.documentElement.style.setProperty('--chat-color-border', '<?php echo $config['color']; ?>44');
</script>

<!-- Context Menu -->
<div id="chat-context-menu" style="display:none;position:fixed;background:var(--modal-bg);border:1px solid var(--border-color);border-radius:12px;box-shadow:0 8px 24px rgba(0,0,0,0.3);padding:8px;z-index:100001;min-width:160px;">
    <div id="context-edit" style="padding:10px 14px;cursor:pointer;border-radius:8px;color:var(--text-color);font-size:14px;transition:background 0.2s;" onmouseover="this.style.background='var(--hover-bg)';" onmouseout="this.style.background='transparent';">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle;margin-right:8px;"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
        Edit
    </div>
    <div id="context-delete" style="padding:10px 14px;cursor:pointer;border-radius:8px;color:#ef4444;font-size:14px;transition:background 0.2s;" onmouseover="this.style.background='rgba(239, 68, 68, 0.1)';" onmouseout="this.style.background='transparent';">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle;margin-right:8px;"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
        Delete
    </div>
</div>
